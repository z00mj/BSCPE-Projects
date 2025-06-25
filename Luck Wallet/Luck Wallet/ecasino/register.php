<?php
session_start();
include 'connect.php';

// Connect to the main Luck Wallet database
$luck_wallet_db = new mysqli('localhost', 'root', '', 'luck_wallet');
if ($luck_wallet_db->connect_error) {
    error_log("Failed to connect to Luck Wallet DB: " . $luck_wallet_db->connect_error);
    $_SESSION['signup_error'] = "Database connection error. Please try again later.";
    header("Location: index.php");
    exit();
}

if (isset($_POST['signUp'])) {
    $firstName = trim($_POST['fName']);
    $lastName = trim($_POST['lName']);
    $email = trim($_POST['email']);
    $raw_password = $_POST['password'];
    
    // Validate input
    if (empty($firstName) || empty($lastName) || empty($email) || empty($raw_password)) {
        $_SESSION['signup_error'] = "All fields are required!";
        header("Location: index.php");
        exit();
    }
    
    if (strlen($raw_password) < 6) {
        $_SESSION['signup_error'] = "Password must be at least 6 characters long!";
        header("Location: index.php");
        exit();
    }
    
    // Check if email already exists using prepared statement
    $checkEmail = "SELECT id FROM users WHERE email = ?";
    $stmt = $conn->prepare($checkEmail);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['signup_error'] = "Email address already exists!";
        header("Location: index.php");
        exit();
    } else {
        // Hash the password using the current default algorithm (bcrypt by default in PHP 7.0+)
        $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);
        
        // Insert new user with prepared statement
        $insertQuery = "INSERT INTO users (firstName, lastName, email, password) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($insertQuery);
        $stmt->bind_param("ssss", $firstName, $lastName, $email, $hashed_password);
        
        if ($stmt->execute()) {
            $new_user_id = $conn->insert_id;
            
            // Check if there's a Luck Wallet account with the same email
            error_log("Checking for Luck Wallet account with email: " . $email);
            $luck_wallet_result = null;
            
            try {
                $check_luck_wallet = $luck_wallet_db->prepare(
                    "SELECT user_id, username FROM luck_wallet_users WHERE email = ? AND e_casino_linked = 0"
                );
                
                if ($check_luck_wallet === false) {
                    throw new Exception("Prepare failed: " . $luck_wallet_db->error);
                }
                
                $email_param = $email;
                if (!$check_luck_wallet->bind_param("s", $email_param)) {
                    throw new Exception("Binding parameters failed: " . $check_luck_wallet->error);
                }
                
                if (!$check_luck_wallet->execute()) {
                    throw new Exception("Execute failed: " . $check_luck_wallet->error);
                }
                
                $luck_wallet_result = $check_luck_wallet->get_result();
                error_log("Query executed successfully. Found " . ($luck_wallet_result ? $luck_wallet_result->num_rows : 0) . " matching accounts");
                
            } catch (Exception $e) {
                error_log("Error in Luck Wallet account check: " . $e->getMessage());
                // Continue with registration even if there's an error checking
            }
            
            if ($luck_wallet_result && $luck_wallet_result->num_rows > 0) {
                // Link the accounts
                $wallet_user = $luck_wallet_result->fetch_assoc();
                $luckytime_username = "$firstName $lastName";
                
                try {
                    // Update Luck Wallet user record
                    $update_wallet = $luck_wallet_db->prepare(
                        "UPDATE luck_wallet_users SET 
                        e_casino_linked = 1, 
                        e_casino_linked_username = ?,
                        e_casino_linked_date = NOW()
                        WHERE user_id = ?"
                    );
                    
                    if ($update_wallet === false) {
                        throw new Exception("Prepare failed: " . $luck_wallet_db->error);
                    }
                    
                    if (!$update_wallet->bind_param("si", $luckytime_username, $wallet_user['user_id'])) {
                        throw new Exception("Binding parameters failed: " . $update_wallet->error);
                    }
                    
                    if (!$update_wallet->execute()) {
                        throw new Exception("Update failed: " . $update_wallet->error);
                    }
                    
                    error_log("Successfully updated Luck Wallet user " . $wallet_user['user_id'] . " with casino link");
                    
                    // Add transaction record for the link
                    $insert_transaction = $luck_wallet_db->prepare(
                        "INSERT INTO luck_wallet_transactions 
                        (user_id, amount, type, status, notes, created_at) 
                        VALUES (?, 0, 'account_link', 'completed', 'Linked with LuckyTime account', NOW())"
                    );
                    
                    if ($insert_transaction === false) {
                        throw new Exception("Prepare failed for transaction: " . $luck_wallet_db->error);
                    }
                    
                    if (!$insert_transaction->bind_param("i", $wallet_user['user_id'])) {
                        throw new Exception("Binding parameters failed for transaction: " . $insert_transaction->error);
                    }
                    
                    if (!$insert_transaction->execute()) {
                        throw new Exception("Transaction insert failed: " . $insert_transaction->error);
                    }
                    
                    error_log("Successfully added transaction record for user " . $wallet_user['user_id']);
                    
                    $_SESSION['signup_success'] = "Registration successful! Your account has been linked to your Luck Wallet.";
                    
                } catch (Exception $e) {
                    error_log("Error linking accounts: " . $e->getMessage());
                    $_SESSION['signup_success'] = "Registration successful! However, there was an error linking your accounts. Please contact support.";
                }
            } else {
                $_SESSION['signup_success'] = "Registration successful! You can now login.";
            }
            
            header("Location: index.php");
            exit();
        } else {
            $_SESSION['signup_error'] = "Error: " . $stmt->error;
            header("Location: index.php");
            exit();
        }
    }
}

if (isset($_POST['signIn'])) {
    $email = $_POST['email'];
    $password = md5($_POST['password']);

    $checkEmail = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($checkEmail);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        $_SESSION['login_error'] = 'invalid_email';
        header("Location: index.php");
        exit();
    } else {
        $row = $result->fetch_assoc();
        $stored_password = $row['password'];
        
        // Check if the stored password is hashed with password_hash() or MD5
        if (password_verify($_POST['password'], $stored_password) || 
            (strlen($stored_password) === 32 && $stored_password === md5($_POST['password']))) {
            
            // If password was in MD5, update it to password_hash()
            if (strlen($stored_password) === 32) {
                $new_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $update = "UPDATE users SET password = ? WHERE email = ?";
                $updateStmt = $conn->prepare($update);
                $updateStmt->bind_param("ss", $new_hash, $email);
                $updateStmt->execute();
            }
            
            $_SESSION['email'] = $row['email'];
            header("Location: homepage.php");
            exit();
        } else {
            $_SESSION['login_error'] = 'incorrect_password';
            header("Location: index.php");
            exit();
        }
    }
}
?>
