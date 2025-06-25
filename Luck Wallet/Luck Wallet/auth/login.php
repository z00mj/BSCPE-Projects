<?php
// login.php
require_once __DIR__ . '/../db_connect.php'; // Updated path to point to the correct location

// Initialize variables for feedback messages
$success_message = '';
$error_message = '';

// Check for registration success message from signup.php redirect
if (isset($_GET['registered']) && $_GET['registered'] === 'success') {
    if (isset($_GET['type']) && $_GET['type'] === 'luckytime') {
        $success_message = 'Your LuckyTime account has been linked and Luck Wallet created successfully! Please log in.';
    } else {
        $success_message = 'Account registered successfully! Please log in.';
    }
}

// --- Handle Standard Login Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_type']) && $_POST['login_type'] === 'standard') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error_message = 'Both email and password are required for standard login.';
    } else {
        try {
            // Prepare the statement to retrieve the user by email
            // CORRECTED: Changed 'id' to 'user_id'
            $stmt = $pdo->prepare("SELECT user_id, username, email, password_hash, e_casino_linked FROM luck_wallet_users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verify user exists and the password is correct (for standard users)
            if ($user && $user['e_casino_linked'] == 0 && password_verify($password, $user['password_hash'])) {
                // Password is correct, user is authenticated
                // Start a session and store user data
                session_start();
                $_SESSION['user_id'] = $user['user_id']; // CORRECTED: Changed 'id' to 'user_id'
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['logged_in_type'] = 'standard'; // Indicate login type

                // Redirect to dashboard or home page using absolute path
                header('Location: /Luck Wallet/dashboard.php');
                exit();
            } else if ($user && $user['e_casino_linked'] == 1) {
                $error_message = 'This email is registered via LuckyTime. Please use "Login with LuckyTime" option.';
            } else {
                $error_message = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            $error_message = 'Database error during standard login: ' . $e->getMessage();
            // In production, log this error but show a generic message to the user
            error_log('Standard login error: ' . $e->getMessage()); // Keep logging in production
        }
    }
}

// --- Handle LuckyTime Login Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_type']) && $_POST['login_type'] === 'luckytime') {
    // Start session at the beginning
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Clear any previous session data
    $_SESSION = array();
    session_destroy();
    session_start();
    
    $luckyTimeEmail = trim($_POST['luckyTimeEmail']);
    $luckyTimePassword = $_POST['luckyTimePassword'];
    
    // Debug log the raw input
    error_log("=== LUCKYTIME LOGIN ATTEMPT ===");
    error_log("Email/Username: " . $luckyTimeEmail);
    error_log("Password length: " . strlen($luckyTimePassword));

    if (empty($luckyTimeEmail) || empty($luckyTimePassword)) {
        $error_message = 'Both LuckyTime email/username and password are required.';
        error_log("Validation failed: Missing email or password");
    } else {
        try {
            $hashed_luckyTimePassword = md5($luckyTimePassword);
            error_log("Hashed password (MD5): " . $hashed_luckyTimePassword);
            
            // Debug: Check if user exists in users table and verify password
            error_log("Checking if user exists in users table...");
            $stmt_check_user = $pdo->prepare("SELECT Id, email, password FROM users WHERE email = ?");
            $stmt_check_user->execute([$luckyTimeEmail]);
            $user_data = $stmt_check_user->fetch(PDO::FETCH_ASSOC);
            
            if ($user_data) {
                error_log("User found in users table. User ID: " . $user_data['Id']);
                error_log("Stored hash: " . $user_data['password']);
                
                // Check password using different hashing methods
                $password_verified = false;
                $stored_hash = $user_data['password'];
                
                // 1. Try direct MD5 comparison (for plain MD5 hashes)
                if (md5($luckyTimePassword) === $stored_hash) {
                    error_log("Password verified using MD5");
                    $password_verified = true;
                }
                // 2. Try password_verify (for bcrypt hashes)
                elseif (password_verify($luckyTimePassword, $stored_hash)) {
                    error_log("Password verified using password_verify");
                    $password_verified = true;
                }
                // 3. Try prefix check for bcrypt (some hashes start with $2y$)
                elseif (strpos($stored_hash, '$2y$') === 0 && password_verify($luckyTimePassword, $stored_hash)) {
                    error_log("Password verified using bcrypt");
                    $password_verified = true;
                }
                
                if (!$password_verified) {
                    error_log("Password verification failed");
                    error_log("Input password: " . $luckyTimePassword);
                    error_log("MD5 of input: " . md5($luckyTimePassword));
                    error_log("Password hash length: " . strlen($stored_hash));
                    error_log("Hash prefix: " . substr($stored_hash, 0, 10));
                    $user = false;
                } else {
                    error_log("Password verified successfully");
                    
                    // Now check luck_wallet_users
                    $query = "
                        SELECT 
                            u.Id as user_id,
                            u.email,
                            lwu.user_id as luck_wallet_user_id,
                            lwu.luck_balance,
                            lwu.e_casino_linked
                        FROM users u
                        LEFT JOIN luck_wallet_users lwu ON u.email = lwu.email OR lwu.e_casino_linked_username = u.email
                        WHERE u.email = ?
                        LIMIT 1";
                    
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([$luckyTimeEmail]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($user) {
                        error_log("Luck Wallet user data found. Luck Wallet User ID: " . ($user['luck_wallet_user_id'] ?? 'Not linked'));
                    } else {
                        error_log("No Luck Wallet user data found for this email");
                    }
                }
            } else {
                error_log("No user found with email: " . $luckyTimeEmail);
                $user = false;
            }
            
            if ($user) {
                error_log("Authentication successful for user: " . $user['email']);
                
                // Set session variables
                $_SESSION['user_id'] = $user['luck_wallet_user_id'] ?? $user['user_id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['username'] = $user['email']; // Using email as username
                $_SESSION['logged_in_type'] = 'luckytime';
                $_SESSION['luck_balance'] = $user['luck_balance'] ?? 0;
                
                error_log("Session variables set for user ID: " . $_SESSION['user_id']);
                
                // Redirect to dashboard
                header('Location: /Luck Wallet/dashboard.php');
                exit();
            } else {
                error_log("Authentication failed - Invalid credentials for: " . $luckyTimeEmail);
                $error_message = 'Invalid LuckyTime email/username or password.';
                
                // Additional check to see if it's a linking issue
                $stmt_check_email = $pdo->prepare("SELECT email FROM users WHERE email = ?");
                $stmt_check_email->execute([$luckyTimeEmail]);
                if ($stmt_check_email->fetch()) {
                    error_log("User exists but password is incorrect");
                } else {
                    error_log("No user found with email: " . $luckyTimeEmail);
                }
            }
        } catch (PDOException $e) {
            $error_message = 'Database error during LuckyTime login: ' . $e->getMessage();
            error_log('LuckyTime login error: ' . $e->getMessage()); // Keep logging in production
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Login - Luck Wallet</title>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
      crossorigin="anonymous"
      referrerpolicy="no-referrer"
    />
    <style>
        /* Global HTML and Body Reset */
        html {
            background: linear-gradient(to bottom, #000000, #004d4d, #000000);
            background-attachment: fixed;
            background-size: cover;
            margin: 0;
            padding: 0;
            height: 100%;
            font-size: 16px; /* Base font size */
        }
        
        body {
            font-family: 'Fira Code', monospace, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: var(--primary-color);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            width: 100%;
            max-width: 100%;
            margin: 0 auto;
        }
        
        /* Header Styles */
        .main-header {
            background-color: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            height: 4rem;
            padding: 0 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
            position: sticky;
            top: 0;
            left: 0;
            right: 0;
            width: 100%;
            box-sizing: border-box;
            border-bottom: 1px solid rgba(0, 255, 255, 0.1);
        }
        
        .header-logo {
            height: 8rem;
            margin-top: 1rem;
        }
        
        .header-nav {
            display: flex;
            gap: 1.5rem;
            justify-content: center;
            margin: 0 auto;
            padding: 0 20px;
            width: 100%;
            box-sizing: border-box;
        }
        
        .header-nav-link {
            color: #ffffff;
            font-weight: 500;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 0.25rem;
            transition: color 0.2s ease-in-out, background-color 0.2s ease-in-out, padding 0.2s ease-in-out;
            font-size: 1.125rem;
        }
        
        .header-nav-link:hover {
            color: #ffffff;
            background-color: rgba(0, 50, 50, 0.5);
        }
        
        .menu-icon {
            display: none;
            cursor: pointer;
            font-size: 1.5rem;
            color: white;
            padding: 0.5rem;
            z-index: 100;
        }

        /* Main content wrapper */
        .main-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 6rem 1rem 4rem; /* Increased top padding for sticky header */
            margin: 0;
            width: 100%;
            box-sizing: border-box;
            min-height: 100vh;
            position: relative;
            z-index: 1;
        }

        .login-container {
            background: rgba(0, 20, 20, 0.9);
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 230, 230, 0.3);
            padding: 2rem;
            width: 100%;
            max-width: 500px;
            border: 1px solid rgba(0, 255, 255, 0.2);
            margin: 2rem auto; /* Center the container */
            box-sizing: border-box;
        }

        h2 {
            font-size: 1.875rem;
            font-weight: 700;
            text-align: center;
            color: #00ffff;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #e0e0e0;
            margin-bottom: 0.25rem;
        }

        /* Styles for all input types used in the forms */
        input[type="email"],
        input[type="password"],
        input[type="text"] {
            display: block;
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #2d3748;
            border-radius: 0.5rem;
            background-color: rgba(0, 30, 30, 0.8);
            color: #e0e0e0;
            font-size: 0.875rem;
            outline: none;
            box-sizing: border-box;
            transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        
        input[type="email"]:focus,
        input[type="password"]:focus,
        input[type="text"]:focus {
            border-color: #00ffff;
            box-shadow: 0 0 0 1px #00ffff;
        }

        /* Button styles */
        .action-button {
            width: 100%;
            display: flex;
            justify-content: center;
            padding: 0.75rem 1rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 1.125rem;
            font-weight: 600;
            color: #ffffff;
            background: linear-gradient(45deg, #006666, #00aaaa);
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            margin-top: 1rem;
        }

        .action-button:hover {
            background: linear-gradient(45deg, #008080, #00cccc);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 255, 255, 0.2);
        }

        .action-button:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 255, 255, 0.4);
        }

        .forgot-password {
            text-align: right;
            margin-top: 0.5rem;
        }

        .forgot-password a {
            color: #00ffff;
            text-decoration: none;
            font-size: 0.875rem;
            transition: color 0.2s ease-in-out;
        }

        .forgot-password a:hover {
            color: #ffffff;
            text-decoration: underline;
        }

        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 1.5rem 0;
        }

        .divider::before,
        .divider::after {
            content: "";
            flex: 1;
            border-bottom: 1px solid rgba(0, 255, 255, 0.2);
        }

        .divider-text {
            padding: 0 1rem;
            color: #a0aec0;
            font-size: 0.875rem;
        }
        
        .divider-text span {
            padding: 0 0.5rem;
            background-color: #001a1a;
        }

        .luckytime-button {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            font-size: 1.125rem;
            font-weight: 600;
            color: #374151;
            background-color: #ffffff;
            cursor: pointer;
            transition: background-color 0.15s ease-in-out;
            outline: none;
        }

        .luckytime-button:hover {
            background-color: #f9fafb;
        }

        .luckytime-button:focus {
            box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.5), 0 0 0 4px #e5e7eb;
        }

        .luckytime-button svg {
            width: 1.5rem;
            height: 1.5rem;
            margin-right: 0.5rem;
        }

        .signup-text {
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.875rem;
            color: #4b5563;
        }

        .signup-text a {
            font-weight: 500;
            color: #2563eb;
            text-decoration: none;
            transition: color 0.15s ease-in-out;
        }

        .signup-text a:hover {
            color: #3b82f6;
        }

        /* Utility class to hide elements */
        .hidden {
            display: none;
        }

        /* Styles for messages */
        .message {
            padding: 10px;
            margin-bottom: 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
            text-align: center;
        }

        .success {
            background-color: #d1fae5; /* green-100 */
            color: #065f46; /* green-700 */
            border: 1px solid #34d399; /* green-400 */
        }

        .error {
            background-color: #fee2e2; /* red-100 */
            color: #991b1b; /* red-700 */
            border: 1px solid #ef4444; /* red-400 */
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            html {
                font-size: 14px; /* Slightly smaller base font size for mobile */
            }
            
            .menu-icon {
                display: block;
            }
            
            .header-nav {
                display: none;
                position: fixed;
                top: 4rem;
                left: 0;
                right: 0;
                background-color: rgba(0, 20, 20, 0.98);
                flex-direction: column;
                padding: 1rem 0;
                text-align: center;
                border-bottom: 1px solid rgba(0, 255, 255, 0.2);
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
                z-index: 999;
                margin: 0;
                transform: none;
            }
            
            .header-nav.active {
                display: flex;
            }
            
            .header-nav-link {
                padding: 0.75rem 1rem;
                margin: 0.25rem 1rem;
                background: rgba(0, 40, 40, 0.7);
                border-radius: 0.5rem;
                font-size: 1.1rem;
            }
            
            .login-container {
                margin: 1rem;
                padding: 1.5rem;
                max-width: 100%;
                box-shadow: 0 0 15px rgba(0, 200, 200, 0.2);
            }
            
            .main-content {
                padding: 2rem 0.5rem 4rem;
                min-height: calc(100vh - 4rem);
            }
            
            .footer-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
                text-align: center;
            }
            
            .footer-first-column {
                align-items: center;
                margin: 0 auto;
            }
            
            .footer-logo {
                height: 100px; /* Slightly smaller logo on mobile */
            }
            
            .footer-bottom-text {
                font-size: 0.8rem;
            }
        }
        
        /* Extra small devices (phones, 480px and down) */
        @media (max-width: 480px) {
            html {
                font-size: 13px;
            }
            
            .login-container {
                padding: 1.25rem;
                margin: 0.75rem;
            }
            
            .main-content {
                padding: 1.5rem 0.5rem 3.5rem;
            }
            
            .footer-logo {
                height: 80px;
            }
            
            .footer-description {
                font-size: 1.1rem;
            }
        }
        
        /* Large screens */
        @media (min-width: 1200px) {
            .login-container {
                max-width: 550px;
                padding: 3rem;
            }
        }
        
        /* Footer Styles */
        .main-footer {
            background: linear-gradient(135deg, #001a1a 0%, #003333 100%);
            color: #e0e0e0;
            padding: 3rem 1.5rem 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .footer-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2.5rem;
            text-align: center;
            padding: 0 1rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        @media (min-width: 768px) {
            .footer-grid {
                grid-template-columns: repeat(4, 1fr);
                text-align: left;
            }
            
            .footer-first-column {
                align-items: flex-start;
                text-align: left;
            }
            
            .footer-logo {
                margin-left: 0;
            }
            
            .footer-description {
                text-align: left;
                margin-left: 0;
            }
            
            .social-icons-container {
                justify-content: flex-start;
                margin-left: 0;
            }
            
            .main-footer h4 {
                text-align: left;
            }
            
            .main-footer h4::after {
                left: 0;
                transform: none;
            }
            
            .main-footer ul {
                text-align: left;
            }
        }
        
        .footer-first-column {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        
        .footer-logo {
            height: 120px;
            width: auto;
            margin: 0 auto 1rem;
            display: block;
            object-fit: contain;
        }
        
        .text-gray-600 {
            color: #718096;  /* This is the standard gray-600 color in Tailwind */
        }
        
        .footer-description {
            font-size: 1.25rem;
            font-weight: 600;
            color: #00ffff;
            margin: 0.5rem 0 1rem;
            line-height: 1.4;
            text-align: center;
        }
        
        .social-icons-container {
            display: flex;
            gap: 1rem;
            margin: 1rem auto 2rem;
            justify-content: center;
        }
        
        .social-icon-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(0, 255, 255, 0.1);
            color: #00ffff;
            font-size: 1rem;
            transition: all 0.3s ease;
            text-decoration: none;
            box-shadow: 0 0 10px rgba(0, 230, 230, 0.3);
        }
        
        .social-icon-btn:hover {
            background-color: #006666;
            border-color: #00ffff;
            color: #00ffff;
            transform: scale(1.1);
            box-shadow: 0 0 15px rgba(0, 255, 255, 0.6);
        }
        
        .main-footer h4 {
            color: #00ffff;
            font-size: 1.125rem;
            margin: 1.5rem 0 1rem;
            position: relative;
            padding-bottom: 0.5rem;
            text-align: center;
        }
        
        .main-footer h4::after {
            content: '';
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            bottom: 0;
            width: 40px;
            height: 2px;
            background: linear-gradient(90deg, #00ffff, transparent);
        }
        
        .main-footer ul {
            list-style: none;
            padding: 0;
            margin: 0;
            text-align: center;
        }
        
        .main-footer ul li {
            margin-bottom: 0.75rem;
        }
        
        .main-footer ul li a {
            color: #e0e0e0;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.9375rem;
            display: inline-block;
        }
        
        .main-footer ul li a:hover {
            color: #00ffff;
            padding-left: 5px;
        }
        
        .footer-bottom-text {
            text-align: center;
            padding: 1.5rem 1rem 0;
            margin: 2rem 1rem 0;
            border-top: 1px solid rgba(0, 255, 255, 0.1);
            color: #a0aec0;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <header class="main-header">
        <div class="header-logo-container">
            <a href="../index.php" class="header-logo-button">
                <img src="../assets/images/logo.png" alt="LUCK Logo" class="header-logo" />
            </a>
        </div>
        <div class="menu-icon" id="menuToggle">
            <i class="fas fa-bars"></i>
        </div>
        <nav class="header-nav">
            <a href="../index.html" class="header-nav-link">Home</a>
            <a href="../pages/whitepaper.html" class="header-nav-link">Whitepaper</a>
            <a href="../ecasino/index.php" class="header-nav-link">LuckyTime</a>
            <a href="http://localhost/luck%20Wallet/marketplace/marketplace.php" class="header-nav-link">LUCK Marketplace</a>
            <a href="../pages/about.html" class="header-nav-link">About</a>
        </nav>
    </header>
    
    <div class="main-content">
        <div class="login-container">
            <h2 id="mainHeading" style="text-align: center; color: #00ffff; margin-bottom: 2rem;">Welcome Back!</h2>

        <?php if ($success_message): ?>
            <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <div id="standardLoginSection">
            <form id="loginForm" action="login.php" method="POST">
                <input type="hidden" name="login_type" value="standard"> <div class="form-group">
                    <label for="email">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Enter your email address"
                        required
                    >
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        required
                    >
                    <div class="forgot-password">
                        <a href="forgot_password.php" id="forgotPasswordLink">Forgot password?</a>
                    </div>
                </div>
                <button
                    type="submit"
                    class="action-button"
                >
                    Login
                </button>
            </form>
        </div>

        <div id="luckyTimeLoginSection" class="hidden">
            <form id="luckyTimeLoginForm" action="login.php" method="POST" onsubmit="return validateLuckyTimeForm()">
                <input type="hidden" name="login_type" id="loginType" value="luckytime">
                <p class="text-center text-gray-600 mb-4">Please log in with your LuckyTime credentials.</p>
                <div id="luckyTimeError" class="error-message" style="color: #ff4444; margin-bottom: 15px; display: none;"></div>
                <div class="form-group">
                    <label for="luckyTimeEmail">LuckyTime Email/Username</label>
                    <input
                        type="text"
                        id="luckyTimeEmail"
                        name="luckyTimeEmail"
                        placeholder="Enter your LuckyTime email or username"
                        required
                        autocomplete="username"
                    >
                </div>
                <div class="form-group">
                    <label for="luckyTimePassword">LuckyTime Password</label>
                    <input
                        type="password"
                        id="luckyTimePassword"
                        name="luckyTimePassword"
                        placeholder="Enter your LuckyTime password"
                        required
                        autocomplete="current-password"
                    >
                </div>
                <button
                    type="submit"
                    class="action-button"
                    id="luckyTimeSubmitBtn"
                >
                    <span id="submitText">Login to LuckyTime</span>
                    <span id="submitSpinner" style="display: none;">Processing...</span>
                </button>
            </form>
            
            <script>
            function validateLuckyTimeForm() {
                const form = document.getElementById('luckyTimeLoginForm');
                const email = document.getElementById('luckyTimeEmail').value.trim();
                const password = document.getElementById('luckyTimePassword').value;
                const errorDiv = document.getElementById('luckyTimeError');
                const submitBtn = document.getElementById('luckyTimeSubmitBtn');
                const submitText = document.getElementById('submitText');
                const submitSpinner = document.getElementById('submitSpinner');
                
                // Show loading state
                submitBtn.disabled = true;
                submitText.style.display = 'none';
                submitSpinner.style.display = 'inline';
                
                // Basic validation
                if (!email || !password) {
                    errorDiv.textContent = 'Both email/username and password are required.';
                    errorDiv.style.display = 'block';
                    submitBtn.disabled = false;
                    submitText.style.display = 'inline';
                    submitSpinner.style.display = 'none';
                    return false;
                }
                
                // If we get here, the form will submit
                return true;
            }
            
            // Add event listener to the form
            document.getElementById('luckyTimeLoginForm').addEventListener('submit', function(e) {
                console.log('Form submitted');
                // Let the form submit normally
                return true;
            });
            </script>
        </div>

        <div class="divider">
            <div class="divider-text">
                <span id="orContinueText">Or continue with</span>
            </div>
        </div>

        <button
            id="luckyTimeLoginBtn"
            class="luckytime-button"
        >
            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Login with LuckyTime
        </button>

            <p class="signup-text" style="text-align: center; margin-top: 1.5rem; color: #e0e0e0;">
                Don't have an account? <a href="signup.php" style="color: #00ffff; text-decoration: none;">Sign up here</a>
            </p>
        </div>
    </div>
    
    <footer class="main-footer">
        <div class="footer-grid">
            <div class="footer-first-column">
                <div class="footer-logo-and-text">
                    <img src="../assets/images/logo.png" alt="LUCK Logo" class="footer-logo" />
                </div>
                <p class="footer-description">Experience<br />Luck Now!</p>
                <div class="social-icons-container">
                    <a href="https://www.x.com/" class="social-icon-btn"><i class="fab fa-twitter"></i></a>
                    <a href="https://telegram.org/" class="social-icon-btn"><i class="fab fa-telegram-plane"></i></a>
                    <a href="https://www.discord.com/" class="social-icon-btn"><i class="fab fa-discord"></i></a>
                    <a href="https://www.youtube.com/" class="social-icon-btn"><i class="fab fa-youtube"></i></a>
                </div>
            </div>
            <div>
                <h4>About</h4>
                <ul>
                    <li><a href="../pages/whitepaper.html">Whitepaper</a></li>
                    <li><a href="../pages/whitepaper.html">Roadmap</a></li>
                    <li><a href="../pages/contacts.html">Contacts</a></li>
                </ul>
            </div>
            <div>
                <h4>Explore</h4>
                <ul>
                    <li><a href="../ecasino/index.php">E-Casino</a></li>
                    <li><a href="#">NFT Marketplace</a></li>
                    <li><a href="../pages/referral.html">Referral Program</a></li>
                    <li><a href="login.php">Dashboard</a></li>
                </ul>
            </div>
            <div>
                <h4>Legal</h4>
                <ul>
                    <li><a href="../pages/privacy_policy.html">Privacy Policy</a></li>
                    <li><a href="../pages/contacts.html">Terms of Service</a></li>
                    <li><a href="../pages/disclaimer.html">Disclaimer</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom-text">&copy; 2025 Luck Wallet. All rights reserved.</div>
    </footer>
    
    <script>
        // Mobile menu toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggle = document.getElementById('menuToggle');
            const nav = document.querySelector('.header-nav');
            const menuIcon = document.querySelector('.menu-icon i');
            
            function toggleMenu() {
                nav.classList.toggle('active');
                menuIcon.classList.toggle('fa-bars');
                menuIcon.classList.toggle('fa-times');
                document.body.classList.toggle('no-scroll', nav.classList.contains('active'));
            }
            
            if (menuToggle) {
                menuToggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    toggleMenu();
                });
                
                // Close menu when clicking on a link
                document.querySelectorAll('.header-nav-link').forEach(link => {
                    link.addEventListener('click', () => {
                        nav.classList.remove('active');
                        menuIcon.classList.add('fa-bars');
                        menuIcon.classList.remove('fa-times');
                        document.body.classList.remove('no-scroll');
                    });
                });
                
                // Close menu when clicking outside
                document.addEventListener('click', function(event) {
                    if (!event.target.closest('.header-nav') && !event.target.closest('.menu-icon')) {
                        nav.classList.remove('active');
                        menuIcon.classList.add('fa-bars');
                        menuIcon.classList.remove('fa-times');
                        document.body.classList.remove('no-scroll');
                    }
                });
                
                // Prevent clicks inside the menu from closing it
                nav.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            }
        });

        // Get references to the main elements
        const mainHeading = document.getElementById('mainHeading');
        const standardLoginSection = document.getElementById('standardLoginSection');
        const luckyTimeLoginSection = document.getElementById('luckyTimeLoginSection');
        const luckyTimeLoginBtn = document.getElementById('luckyTimeLoginBtn');
        const orContinueText = document.getElementById('orContinueText');

        // Get references to the forms
        const loginForm = document.getElementById('loginForm');
        const luckyTimeLoginForm = document.getElementById('luckyTimeLoginForm');
        const forgotPasswordLink = document.getElementById('forgotPasswordLink');

        // State to track the current mode (standard login or LuckyTime login)
        let isLuckyTimeMode = false;

        // Add event listener for LuckyTime login button click (toggles forms)
        luckyTimeLoginBtn.addEventListener('click', function() {
            isLuckyTimeMode = !isLuckyTimeMode; // Toggle the mode

            if (isLuckyTimeMode) {
                // Switch to LuckyTime login mode
                standardLoginSection.classList.add('hidden');
                luckyTimeLoginSection.classList.remove('hidden');
                mainHeading.textContent = 'Login to LuckyTime'; // Changed heading
                luckyTimeLoginBtn.innerHTML = '<svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg> Cancel LuckyTime Login'; // Changed button text
                orContinueText.textContent = 'Or login with'; // Change text to reflect option to go back to standard login
            } else {
                // Switch back to standard login mode
                standardLoginSection.classList.remove('hidden');
                luckyTimeLoginSection.classList.add('hidden');
                mainHeading.textContent = 'Welcome Back!';
                luckyTimeLoginBtn.innerHTML = '<svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> Login with LuckyTime';
                orContinueText.textContent = 'Or continue with'; // Change text back
            }
        });

        // The 'Forgot Password' link's JavaScript.
        // The event.preventDefault() has been removed so the href works directly.
        forgotPasswordLink.addEventListener('click', function(event) {
            // event.preventDefault(); // <-- THIS LINE WAS REMOVED
            console.log('Forgot Password link clicked!');
            // The link will now navigate directly via its href="forgot_password.php"
            // without needing an explicit window.location.href here.
        });
    </script>
    <script>
        // Clear browser history and prevent forward navigation
        if (window.history && window.history.pushState) {
            // Replace current history entry to prevent back navigation to dashboard
            window.history.replaceState(null, null, window.location.href);
            
            // Add a new history entry
            window.history.pushState(null, null, window.location.href);
            
            // Listen for popstate event (back/forward button)
            window.onpopstate = function(event) {
                // If coming from dashboard or trying to go forward to dashboard
                if (document.referrer && document.referrer.includes('dashboard') || 
                    window.location.href.includes('dashboard')) {
                    // Clear any sensitive data
                    sessionStorage.clear();
                    localStorage.clear();
                    
                    // Force redirect to login with a fresh request
                    window.location.replace('/Luck Wallet/auth/login.php?session=expired');
                    return;
                }
                
                // Push a new state to prevent forward navigation
                window.history.pushState(null, null, window.location.href);
            };
            
            // Clear any existing forward history
            window.history.forward();
            
            // Handle forward button specifically
            window.onbeforeunload = function() {
                window.history.pushState(null, null, window.location.href);
            };
        }
        
        // Clear any sensitive data from storage
        sessionStorage.clear();
        localStorage.clear();
        
        // Clear form data on page load
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.getElementsByTagName('form');
            for (let form of forms) {
                form.reset();
            }
            
            // If page was loaded via back/forward, force a fresh load
            if (window.performance && window.performance.navigation.type === 2) {
                window.location.reload(true);
            }
        });
        
        // Clear any cached versions of this page
        window.onunload = function(){};
    </script>
</body>
</html>