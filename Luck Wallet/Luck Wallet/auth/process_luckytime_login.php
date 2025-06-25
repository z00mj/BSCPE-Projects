<?php
// Include the database connection file
require_once __DIR__ . '/../db_connect.php';

// Start the session
session_start();

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'redirect' => ''
];

// Helper function to get referrer ID
function getReferrerId($pdo, $referralCode) {
    try {
        $stmt = $pdo->prepare("SELECT user_id FROM luck_wallet_users WHERE referral_code = ?");
        $stmt->execute([$referralCode]);
        $referrer = $stmt->fetch(PDO::FETCH_ASSOC);
        return $referrer ? $referrer['user_id'] : null;
    } catch (PDOException $e) {
        error_log('Error fetching referrer ID: ' . $e->getMessage());
        return null;
    }
}

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $referralCode = trim($_POST['referral_code'] ?? '');

    // Validate input
    if (empty($username) || empty($password)) {
        $response['message'] = 'Username and password are required.';
    } else {
        try {
            // Check if the user exists in the users table
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // Check if this user already has a linked Luck Wallet account
                $stmt = $pdo->prepare("SELECT * FROM luck_wallet_users WHERE e_casino_linked_username = ?");
                $stmt->execute([$username]);
                $walletUser = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($walletUser) {
                    // User exists, log them in
                    $_SESSION['user_id'] = $walletUser['user_id'];
                    $_SESSION['username'] = $walletUser['username'];
                    
                    $response['success'] = true;
                    $response['message'] = 'Login successful!';
                    $response['redirect'] = '../index.php';
                } else {
                    // Process referral code if provided
                    $referredByUserId = null;
                    if (!empty($referralCode)) {
                        $referredByUserId = getReferrerId($pdo, $referralCode);
                        if ($referredByUserId === null) {
                            $response['message'] = 'Invalid referral code provided.';
                            echo json_encode($response);
                            exit;
                        }
                    }

                    // Set initial balance (100 LUCK if referred, 0 otherwise)
                    $initialBalance = ($referredByUserId !== null) ? 100.00 : 0.00;
                    
                    // Create a new Luck Wallet account linked to this user
                    $walletAddress = 'LW-' . uniqid() . bin2hex(random_bytes(8));
                    
                    // Generate a unique referral code for the new user
                    $userReferralCode = '';
                    do {
                        $userReferralCode = substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 8);
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM luck_wallet_users WHERE referral_code = ?");
                        $stmt->execute([$userReferralCode]);
                    } while ($stmt->fetchColumn() > 0);
                    
                    $stmt = $pdo->prepare(
                        "INSERT INTO luck_wallet_users 
                        (username, email, password_hash, wallet_address, luck_balance, referral_code, e_casino_linked, e_casino_linked_username, referred_by_user_id) 
                        VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)"
                    );
                    
                    // Use the casino username as both username and email for the wallet
                    $walletUsername = $username . '_wallet';
                    $passwordHash = password_hash($password . 'luck_wallet_salt', PASSWORD_DEFAULT);
                    
                    if ($stmt->execute([$walletUsername, $walletUsername, $passwordHash, $walletAddress, $initialBalance, $userReferralCode, $username, $referredByUserId])) {
                        $newUserId = $pdo->lastInsertId();
                        
                        // If user was referred, handle bonuses
                        if ($referredByUserId !== null) {
                            // 1. Add 500 LUCK to referrer
                            $referrerBonus = 500.00;
                            $stmt = $pdo->prepare("UPDATE luck_wallet_users SET luck_balance = luck_balance + ? WHERE user_id = ?");
                            $stmt->execute([$referrerBonus, $referredByUserId]);
                            
                            // 2. Log referrer's bonus transaction
                            $stmt = $pdo->prepare(
                                "INSERT INTO luck_wallet_transactions 
                                (sender_user_id, receiver_user_id, amount, transaction_type, notes) 
                                VALUES (NULL, ?, ?, 'referral_bonus', 'Referral bonus for new user registration')"
                            );
                            $stmt->execute([$referredByUserId, $referrerBonus]);
                            
                            // 3. Log new user's welcome bonus
                            if ($initialBalance > 0) {
                                $stmt = $pdo->prepare(
                                    "INSERT INTO luck_wallet_transactions 
                                    (sender_user_id, receiver_user_id, amount, transaction_type, notes) 
                                    VALUES (NULL, ?, ?, 'welcome_bonus', 'Welcome bonus for using referral code')"
                                );
                                $stmt->execute([$newUserId, $initialBalance]);
                            }
                        }
                        $userId = $pdo->lastInsertId();
                        
                        // Set session
                        $_SESSION['user_id'] = $userId;
                        $_SESSION['username'] = $walletUsername;
                        
                        $response['success'] = true;
                        $response['message'] = 'Account created and logged in successfully!';
                        $response['redirect'] = '../index.php';
                    } else {
                        $response['message'] = 'Failed to create wallet account.';
                    }
                }
            } else {
                $response['message'] = 'Invalid LuckyTime credentials.';
            }
        } catch (PDOException $e) {
            error_log('Database error during LuckyTime login: ' . $e->getMessage());
            $response['message'] = 'A database error occurred. Please try again later.';
        }
    }
} else {
    $response['message'] = 'Invalid request method.';
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
