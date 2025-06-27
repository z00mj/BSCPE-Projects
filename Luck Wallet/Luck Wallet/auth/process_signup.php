<?php
// Include the database connection file
require_once __DIR__ . '/../db_connect.php';

// Start the session
session_start();

// Define constants
const REFERRAL_BONUS_LUCK = 500.00;

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'redirect' => ''
];

// --- Helper function to get referrer ID ---
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

// --- Helper function to add LUCK to a user's balance ---
function addLuckToUser($pdo, $userId, $amount) {
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT luck_balance FROM luck_wallet_users WHERE user_id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $currentBalance = $stmt->fetchColumn();

        if ($currentBalance !== false) {
            $newBalance = $currentBalance + $amount;
            $stmt = $pdo->prepare("UPDATE luck_wallet_users SET luck_balance = ? WHERE user_id = ?");
            $stmt->execute([$newBalance, $userId]);
            $pdo->commit();
            return true;
        } else {
            $pdo->rollBack();
            error_log("Attempted to add LUCK to non-existent user ID: " . $userId);
            return false;
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Database error adding LUCK: ' . $e->getMessage());
        return false;
    }
}

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $signupType = $_POST['signup_type'] ?? '';
    
    // Handle Standard Sign Up
    if ($signupType === 'standard') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirmPassword'] ?? '';
        $referralCode = trim($_POST['referral_code_standard'] ?? '');

        // Validation
        if (empty($username) || empty($email) || empty($password) || empty($confirmPassword)) {
            $response['message'] = 'All fields are required for standard sign up.';
        } elseif ($password !== $confirmPassword) {
            $response['message'] = 'Passwords do not match.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['message'] = 'Invalid email format.';
        } else {
            try {
                // Check if username or email already exists
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM luck_wallet_users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                if ($stmt->fetchColumn() > 0) {
                    $response['message'] = 'Username or email already exists in Luck Wallet. Please choose another.';
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

                    // Generate unique wallet address and referral code
                    $walletAddress = 'LW-' . uniqid() . bin2hex(random_bytes(8));
                    $userReferralCode = '';
                    do {
                        $userReferralCode = substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 8);
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM luck_wallet_users WHERE referral_code = ?");
                        $stmt->execute([$userReferralCode]);
                    } while ($stmt->fetchColumn() > 0);

                    // Hash the password
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                    // Set initial balance (100 LUCK if referred, 0 otherwise)
                    $initialBalance = ($referredByUserId !== null) ? 100.00 : 0.00;
                    
                    // Insert new user
                    $stmt = $pdo->prepare(
                        "INSERT INTO luck_wallet_users (username, email, password_hash, wallet_address, luck_balance, referral_code, referred_by_user_id, e_casino_linked, e_casino_linked_username, join_date) " .
                        "VALUES (?, ?, ?, ?, ?, ?, ?, 0, NULL, NOW())"
                    );

                    if ($stmt->execute([$username, $email, $passwordHash, $walletAddress, $initialBalance, $userReferralCode, $referredByUserId])) {
                        $newUserId = $pdo->lastInsertId();
                        
                        // If user was referred, handle bonuses
                        if ($referredByUserId !== null) {
                            // 1. Add 500 LUCK to referrer
                            addLuckToUser($pdo, $referredByUserId, REFERRAL_BONUS_LUCK);
                            
                            // 2. Log referrer's bonus transaction
                            $stmt = $pdo->prepare(
                                "INSERT INTO luck_wallet_transactions 
                                (sender_user_id, receiver_user_id, amount, transaction_type, notes) 
                                VALUES (NULL, ?, ?, 'referral_bonus', 'Referral bonus for new user registration')"
                            );
                            $stmt->execute([$referredByUserId, REFERRAL_BONUS_LUCK]);
                            
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
                        
                        $response['success'] = true;
                        $response['message'] = 'Account created successfully! You can now log in.';
                        $response['redirect'] = 'login.php?registered=success';
                    } else {
                        $response['message'] = 'Failed to create account. Please try again.';
                    }
                }
            } catch (PDOException $e) {
                error_log('Database error during standard signup: ' . $e->getMessage());
                $response['message'] = 'A database error occurred. Please try again later.';
            }
        }
    }
        // Handle LuckyTime Sign Up
    elseif ($signupType === 'luckytime') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $referralCode = trim($_POST['referral_code'] ?? '');

        // Basic validation
        if (empty($username) || empty($password)) {
            $response['message'] = 'Username and password are required.';
        } else {
            try {
                // Check if username already exists in LuckyTime (users table)
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                $stmt->execute([$username]);
                
                if ($stmt->fetchColumn() > 0) {
                    $response['message'] = 'This LuckyTime username already exists. Please log in instead.';
                } else {
                    // Check if there's a Luck Wallet account with this email
                    $stmt = $pdo->prepare("SELECT user_id, email FROM luck_wallet_users WHERE email = ? AND e_casino_linked = 0");
                    $stmt->execute([$username]);
                    $existingWallet = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Hash the password (MD5 for LuckyTime compatibility)
                    $hashedPassword = md5($password);
                    
                    // Insert into LuckyTime users table
                    $stmt = $pdo->prepare("INSERT INTO users (email, password, balance, created_at) VALUES (?, ?, 1000.00, NOW())");
                    $stmt->execute([$username, $hashedPassword]);
                    $newUserId = $pdo->lastInsertId();
                    
                    // If there's an existing Luck Wallet with this email, link them
                    if ($existingWallet) {
                        $stmt = $pdo->prepare(
                            "UPDATE luck_wallet_users SET e_casino_linked = 1, e_casino_linked_username = ? WHERE user_id = ?"
                        );
                        $stmt->execute([$username, $existingWallet['user_id']]);
                        
                        // Log the linking event
                        $stmt = $pdo->prepare(
                            "INSERT INTO luck_wallet_transactions 
                            (sender_user_id, receiver_user_id, amount, transaction_type, notes) 
                            VALUES (NULL, ?, 0, 'account_linked', 'Account linked with LuckyTime')"
                        );
                        $stmt->execute([$existingWallet['user_id']]);
                    }
                    
                    // Process referral code if provided
                    if (!empty($referralCode)) {
                        $referredByUserId = getReferrerId($pdo, $referralCode);
                        if ($referredByUserId !== null) {
                            // Add bonus to referrer
                            addLuckToUser($pdo, $referredByUserId, REFERRAL_BONUS_LUCK);
                            
                            // Log referrer's bonus transaction
                            $stmt = $pdo->prepare(
                                "INSERT INTO luck_wallet_transactions 
                                (sender_user_id, receiver_user_id, amount, transaction_type, notes) 
                                VALUES (NULL, ?, ?, 'referral_bonus', 'Referral bonus for new LuckyTime user')"
                            );
                            $stmt->execute([$referredByUserId, REFERRAL_BONUS_LUCK]);
                        }
                    }
                    
                    $response['success'] = true;
                    $response['message'] = 'LuckyTime account created successfully! Your account has been linked to your existing Luck Wallet.';
                    $response['redirect'] = 'login.php?registered=success&type=luckytime';
                }
            } catch (PDOException $e) {
                error_log('Database error during LuckyTime signup: ' . $e->getMessage());
                $response['message'] = 'A database error occurred. Please try again later.';
                
                // If we created a LuckyTime account but failed to link, delete it to maintain consistency
                if (isset($newUserId)) {
                    try {
                        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$newUserId]);
                    } catch (Exception $deleteError) {
                        error_log('Error cleaning up after failed LuckyTime signup: ' . $deleteError->getMessage());
                    }
                }
            }
        }
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
