<?php
// Start the session to access any flash messages
session_start();

// Check for any success/error messages from redirects
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';

// Clear the messages after displaying them
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Sign Up - Luck Wallet</title>
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
            position: sticky;
            top: 0;
            left: 0;
            right: 0;
            background-color: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            z-index: 1000;
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
            padding: 6rem 1rem 4rem; /* Increased top padding for sticky header */
            width: 100%;
            box-sizing: border-box;
            margin: 0 auto;
            max-width: 1200px;
            position: relative;
            z-index: 1;
        }

        .auth-container {
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

        /* Styles for all input types used in the form */
        input[type="email"],
        input[type="text"],
        input[type="password"] {
            display: block;
            width: 100%;
            padding: 0.5rem 1rem;
            border: 1px solid #2d3748;
            border-radius: 0.5rem;
            background-color: rgba(0, 30, 30, 0.8);
            color: #e0e0e0;
            font-size: 0.875rem;
            transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        
        input[type="email"]:focus,
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #00ffff;
            box-shadow: 0 0 0 3px rgba(0, 255, 255, 0.2);
        }
        
        .btn {
            display: inline-block;
            background-color: #00ffff;
            color: #001a1a;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            text-align: center;
            cursor: pointer;
            border: none;
            font-size: 1rem;
            transition: all 0.2s ease-in-out;
            width: 100%;
            margin-top: 1rem;
        }
        
        .btn:hover {
            background-color: #00e6e6;
            transform: translateY(-2px);
        }
        
        .btn-block {
            display: block;
            width: 100%;
        }
        
        .back-to-login {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.875rem;
            color: #a0aec0;
        }
        
        .back-to-login a {
            font-weight: 500;
            color: #00ffff;
            text-decoration: none;
            transition: color 0.2s ease-in-out;
        }
        
        .back-to-login a:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert i {
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        
        .alert-error {
            background-color: rgba(248, 113, 113, 0.1);
            border: 1px solid rgba(248, 113, 113, 0.2);
            color: #f87171;
        }
        
        .alert-success {
            background-color: rgba(74, 222, 128, 0.1);
            border: 1px solid rgba(74, 222, 128, 0.2);
            color: #4ade80;
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
            max-width: 1200px;
            margin: 2rem auto 0;
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
            
            .auth-container {
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
                gap: 2.5rem;
                text-align: center;
                padding: 0 1rem;
            }
            
            .footer-first-column {
                align-items: center;
                margin: 0 auto;
            }
            
            .footer-description {
                font-size: 1.1rem;
            }
            
            .main-footer h4 {
                margin: 1.5rem 0 1rem;
            }
            
            .main-footer h4::after {
                left: 50%;
                transform: translateX(-50%);
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
            
            .auth-container {
                padding: 1.25rem;
                margin: 0.75rem;
            }
            
            .main-content {
                padding: 1.5rem 0.5rem 3.5rem;
            }
            
            h2 {
                font-size: 1.5rem;
            }
            
            .footer-logo {
                height: 80px;
            }
            
            .footer-description {
                font-size: 1.1rem;
            }
            
            .footer-bottom {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
        }
        
        /* Large screens */
        @media (min-width: 1200px) {
            .auth-container {
                max-width: 550px;
                padding: 3rem;
            }
        }
        
        /* Medium screens */
        @media (min-width: 769px) and (max-width: 1024px) {
            .footer-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 3rem 2rem;
            }
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
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
        }

        .container {
            background-color: #ffffff;
            padding: 2rem;
            border-radius: 0.75rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            width: 100%;
            max-width: 28rem;
        }

        h2 {
            font-size: 1.875rem;
            font-weight: 700;
            text-align: center;
            color: #1f2937;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.25rem;
        }

        /* Styles for all input types used in the forms */
        input[type="email"],
        input[type="password"],
        input[type="text"] {
            display: block;
            width: 100%;
            padding: 0.5rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            font-size: 0.875rem;
            outline: none;
            box-sizing: border-box; /* Ensures padding and border are included in the element's total width and height */
        }

        input[type="email"]:focus,
        input[type="password"]:focus,
        input[type="text"]:focus {
            border-color: #3b82f6; /* Blue border on focus */
            box-shadow: 0 0 0 1px #3b82f6; /* Blue shadow on focus */
        }

        .action-button { /* General style for submit buttons */
            width: 100%;
            display: flex;
            justify-content: center;
            padding: 0.5rem 1rem;
            border: 1px solid transparent;
            border-radius: 0.5rem;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            font-size: 1.125rem;
            font-weight: 600;
            color: #ffffff;
            background-color: #2563eb;
            cursor: pointer;
            transition: background-color 0.15s ease-in-out;
            outline: none;
        }

        .action-button:hover {
            background-color: #1d4ed8;
        }

        .action-button:focus {
            box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.5), 0 0 0 4px #3b82f6;
        }

        .divider {
            position: relative;
            margin-top: 2rem;
            margin-bottom: 2rem;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            border-top: 1px solid #d1d5db;
            transform: translateY(-50%);
        }

        .divider-text {
            position: relative;
            display: flex;
            justify-content: center;
            font-size: 0.875rem;
            color: #6b7280;
        }

        .divider-text span {
            padding: 0 0.5rem;
            background-color: #ffffff;
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

        .login-text {
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.875rem;
            color: #4b5563;
        }

        .login-text a {
            font-weight: 500;
            color: #2563eb;
            text-decoration: none;
            transition: color 0.15s ease-in-out;
        }

        .login-text a:hover {
            color: #3b82f6;
        }

        /* Utility class to hide elements */
        .hidden {
            display: none;
        }
        
        /* Hide tab buttons by default */
        .tabs, .tab-btn {
            display: none;
        }
        
        /* Initially hide LuckyTime tab */
        #luckyTime-tab {
            display: none;
        }

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
            <a href="../index.php" class="header-nav-link">Home</a>
            <a href="../pages/whitepaper.html" class="header-nav-link">Whitepaper</a>
            <a href="../ecasino/index.php" class="header-nav-link">LuckyTime</a>
            <a href="http://localhost/luck%20Wallet/marketplace/marketplace.php" class="header-nav-link">LUCK Marketplace</a>
            <a href="../pages/about.html" class="header-nav-link">About</a>
        </nav>
    </header>

    <main class="main-content">
        <div class="auth-container">
            <h2 style="text-align: center; color: #00ffff; margin-bottom: 2rem;">Create Account</h2>
            
            <div id="messageContainer">
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
            </div>

            
            <!-- Standard Signup Form -->
            <div id="standard-tab" class="tab-content" style="display: block;">
                <form id="signupForm" class="space-y-4">
                    <input type="hidden" name="signup_type" value="standard">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required 
                               placeholder="Choose a username" 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required 
                               placeholder="your@email.com" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div style="position: relative;">
                            <input type="password" id="password" name="password" required 
                                   placeholder="••••••••">
                            <button type="button" onclick="togglePassword('password')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #a0aec0; cursor: pointer;">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                        <p style="font-size: 0.75rem; color: #a0aec0; margin-top: 0.25rem;">At least 8 characters, with a number and symbol</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirmPassword">Confirm Password</label>
                        <div style="position: relative;">
                            <input type="password" id="confirmPassword" name="confirmPassword" required 
                                   placeholder="••••••••">
                            <button type="button" onclick="togglePassword('confirmPassword')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #a0aec0; cursor: pointer;">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="referralCode">Referral Code (Optional)</label>
                        <input type="text" id="referralCode" name="referral_code_standard" 
                               placeholder="Enter referral code if any" 
                               value="<?php echo htmlspecialchars($_POST['referral_code_standard'] ?? ''); ?>">
                    </div>
                    
                    <div style="display: flex; align-items: flex-start; margin: 1.5rem 0;">
                        <input type="checkbox" id="terms" name="terms" required 
                               style="margin-right: 0.75rem; margin-top: 0.25rem;">
                        <label for="terms" style="font-size: 0.875rem; color: #a0aec0; line-height: 1.4;">
                            I agree to the <a href="#" style="color: #00ffff; text-decoration: none;">Terms of Service</a> and 
                            <a href="#" style="color: #00ffff; text-decoration: none;">Privacy Policy</a>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn">
                        Create Account
                    </button>
                </form>
                
                <div class="back-to-login" style="margin-top: 1.5rem; text-align: center;">
                    <button type="button" class="btn" style="background-color: #f59e0b; color: #000000; width: 100%; max-width: 300px; margin: 0 auto 1rem;" onclick="switchToTab('luckyTime')">
                        Register using LuckyTime
                    </button>
                    <div style="margin-top: 1rem;">
                        Already have an account? <a href="login.php">Log in</a>
                    </div>
                </div>
            </div>
            
            <!-- LuckyTime Signup Form -->
            <div id="luckyTime-tab" class="tab-content">
                <form id="luckyTimeLoginForm" class="space-y-4">
                    <div class="form-group">
                        <label for="luckyTimeUsername">LuckyTime Username</label>
                        <input type="text" id="luckyTimeUsername" name="username" required 
                               placeholder="Enter your LuckyTime username">
                    </div>
                    
                    <div class="form-group">
                        <label for="luckyTimePassword">Password</label>
                        <div style="position: relative;">
                            <input type="password" id="luckyTimePassword" name="password" required 
                                   placeholder="••••••••">
                            <button type="button" onclick="togglePassword('luckyTimePassword')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #a0aec0; cursor: pointer;">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="luckyTimeReferralCode">Referral Code (Optional)</label>
                        <input type="text" id="luckyTimeReferralCode" name="referral_code" 
                               placeholder="Enter referral code if any" 
                               value="<?php echo htmlspecialchars($_POST['referral_code'] ?? ''); ?>">
                    </div>
                    
                    <button type="submit" class="btn" style="background-color: #f59e0b; color: #000000;">
                        Login with LuckyTime
                    </button>
                </form>
                
                <div class="back-to-login" style="text-align: center; margin-top: 1.5rem;">
                    <div style="margin-bottom: 1rem;">
                        Don't have a LuckyTime account? <a href="#" style="color: #f59e0b;" onclick="switchToTab('standard'); return false;">Register here</a>
                    </div>
                    <div>
                        Already have an account? <a href="login.php">Log in</a>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
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
                    <li><a href="../ecasino/index.html">E-Casino</a></li>
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

        // Function to switch tabs
        function switchToTab(tabId) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.style.display = 'none';
            });
            
            // Show the selected tab content
            const tabToShow = document.getElementById(`${tabId}-tab`);
            if (tabToShow) {
                tabToShow.style.display = 'block';
            }
            
            // Update URL hash
            window.location.hash = tabId;
        }

        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.nextElementSibling.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Function to show message
        function showMessage(message, type = 'error') {
            const messageContainer = document.getElementById('messageContainer');
            if (!messageContainer) return;
            
            messageContainer.innerHTML = `
                <div class="alert alert-${type}">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
                    ${message}
                </div>
            `;
            messageContainer.scrollIntoView({ behavior: 'smooth' });
        }

        // Handle standard signup form submission
        const signupForm = document.getElementById('signupForm');
        if (signupForm) {
            signupForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                // Client-side validation
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirmPassword').value;
                const terms = document.getElementById('terms');
                
                if (password !== confirmPassword) {
                    showMessage('Passwords do not match!');
                    return;
                }
                
                if (!terms.checked) {
                    showMessage('You must accept the Terms of Service and Privacy Policy');
                    return;
                }

                const formData = new FormData(this);
                
                try {
                    const response = await fetch('process_signup.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        // Show success message and redirect
                        showMessage(result.message, 'success');
                        setTimeout(() => {
                            window.location.href = result.redirect || 'login.php';
                        }, 1500);
                    } else {
                        // Show error message
                        showMessage(result.message || 'An error occurred. Please try again.', 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showMessage('An error occurred. Please try again.', 'error');
                }
            });
        }

        // Handle LuckyTime form submission
        const luckyTimeLoginForm = document.getElementById('luckyTimeLoginForm');
        if (luckyTimeLoginForm) {
            luckyTimeLoginForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                formData.append('signup_type', 'luckytime');
                
                // Show loading state
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Account...';
                
                try {
                    const response = await fetch('process_luckytime_login.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        showMessage(result.message, 'success');
                        if (result.redirect) {
                            // Redirect after a short delay to show the success message
                            setTimeout(() => {
                                window.location.href = result.redirect;
                            }, 1500);
                        }
                    } else {
                        showMessage(result.message || 'Invalid LuckyTime credentials. Please try again.');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showMessage('An error occurred. Please try again.');
                }
            });
        }
    </script>
</body>
</html>