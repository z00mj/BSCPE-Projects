<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function registerUser(array $data): array {
        // Validate input
        $errors = [];
        
        if (empty($data['username']) || strlen($data['username']) < 4) {
            $errors[] = 'Username must be at least 4 characters';
        }
        
        if (!validateEmail($data['email'])) {
            $errors[] = 'Invalid email address';
        }
        
        if (strlen($data['password']) < 8) {
            $errors[] = 'Password must be at least 8 characters';
        }
        
        if ($data['password'] !== $data['confirm_password']) {
            $errors[] = 'Passwords do not match';
        }
        
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        // Check if username/email exists
        $existing = $this->db->fetchOne(
            "SELECT id FROM users WHERE username = ? OR email = ?",
            [$data['username'], $data['email']]
        );
        
        if ($existing) {
            return ['success' => false, 'errors' => ['Username or email already exists']];
        }
        
        // Handle referral code
        $referredBy = null;
        if (!empty($data['referral_code'])) {
            $referrer = $this->db->fetchOne(
                "SELECT id FROM users WHERE referral_code = ?",
                [$data['referral_code']]
            );
            
            if (!$referrer) {
                // If the referral code is invalid, stop the registration
                return ['success' => false, 'errors' => ['Invalid referral code provided.']];
            }
            $referredBy = $referrer['id'];
        }
        
        // Create user
        $referralCode = generateReferralCode();
        $passwordHash = passwordHash($data['password']);
        
        try {
            $this->db->beginTransaction();
            
            $userId = $this->db->insert('users', [
                'username' => $data['username'],
                'email' => $data['email'],
                'password_hash' => $passwordHash,
                'referral_code' => $referralCode,
                'referred_by' => $referredBy,
                'kyc_status' => 'not_verified',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Initialize mining data
            $this->db->insert('mining_data', [
                'user_id' => $userId,
                'boost_level' => 1,
                'total_mined' => 0
            ]);
            
            // Record referral if applicable. This is the SINGLE source of truth for referral creation.
            if ($referredBy) {
                // The `referred_id` column in the `referrals` table is UNIQUE.
                // A user can only be referred once, so we just need to insert.
                // The check for an existing referral is implicitly handled by the unique constraint.
                $this->db->insert('referrals', [
                    'referrer_id' => $referredBy,
                    'referred_id' => $userId,
                    'referred_at' => date('Y-m-d H:i:s'),
                    'bonus_awarded' => 0 // Bonus is awarded on the new user's first login
                ]);

                // Update the referrer's "Referral Master" challenge progress using the stored procedure
                $referralChallengeId = 7; // The ID for the "Referral Master" challenge
                $this->db->executeQuery(
                    "CALL UpdateChallengeProgress(?, ?, 1, FALSE)",
                    [$referredBy, $referralChallengeId]
                );
            }
            
            $this->db->commit();
            
            return ['success' => true, 'user_id' => $userId];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Registration failed: " . $e->getMessage());
            // Provide a more user-friendly error message
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                 return ['success' => false, 'errors' => ['An error occurred with the referral. Please try again.']];
            }
            return ['success' => false, 'errors' => ['Registration failed due to a server error.']];
        }
    }
    
    public function awardReferralBonus(int $referrerId, int $referredId): void {
        try {
            $this->db->beginTransaction();
            
            // Award new user (50 RAWR)
            $this->db->executeQuery(
                "UPDATE users SET rawr_balance = rawr_balance + 50 WHERE id = ?",
                [$referredId]
            );
            
            // Award referrer (100 RAWR)
            $this->db->executeQuery(
                "UPDATE users SET rawr_balance = rawr_balance + 100 WHERE id = ?",
                [$referrerId]
            );
            
            // Mark bonus as awarded
            $this->db->executeQuery(
                "UPDATE referrals SET bonus_awarded = 1 
                 WHERE referrer_id = ? AND referred_id = ?",
                [$referrerId, $referredId]
            );
            
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Referral bonus failed: " . $e->getMessage());
        }
    }
    
    public function loginUser(string $username, string $password): array {
        $user = $this->db->fetchOne(
            "SELECT id, username, password_hash, is_banned, referred_by FROM users 
             WHERE username = ? OR email = ?",
            [$username, $username]
        );
        
        if (!$user) {
            return ['success' => false, 'errors' => ['Invalid username or password']];
        }
        
        if (!password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'errors' => ['Invalid username or password']];
        }
        
        if ($user['is_banned']) {
            return ['success' => false, 'errors' => ['This account has been banned']];
        }
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['last_activity'] = time();
        
        $this->db->update('users', [
            'last_login' => date('Y-m-d H:i:s')
        ], 'id = ?', [$user['id']]);
        
        return [
            'success' => true,
            'user_id' => $user['id'],
            'referred_by' => $user['referred_by']
        ];
    }
    
    public function logout(): void {
        $_SESSION = [];
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
    }
}
