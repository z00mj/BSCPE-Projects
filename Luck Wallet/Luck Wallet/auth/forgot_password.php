<?php
// forgot_password.php
require_once __DIR__ . '/../db_connect.php'; // Updated path to point to the correct location

$message = '';
$message_type = ''; // 'success' or 'error'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $newPassword = $_POST['newPassword'];
    $confirmNewPassword = $_POST['confirmNewPassword'];

    // Basic server-side validation
    if (empty($username) || empty($email) || empty($newPassword) || empty($confirmNewPassword)) {
        $message = 'All fields are required.';
        $message_type = 'error';
    } elseif ($newPassword !== $confirmNewPassword) {
        $message = 'New passwords do not match.';
        $message_type = 'error';
    } elseif (strlen($newPassword) < 6) { // Minimum password length
        $message = 'New password must be at least 6 characters long.';
        $message_type = 'error';
    } else {
        try {
            // Step 1: Check if the user exists AND is a standard (not LuckyTime-linked) user
            $stmt = $pdo->prepare("SELECT user_id, password_hash, e_casino_linked FROM luck_wallet_users WHERE username = ? AND email = ?");
            $stmt->execute([$username, $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // User found, now check if they are a standard user (e_casino_linked = 0)
                if ($user['e_casino_linked'] == 0) {
                    // This is a standard user, proceed with password reset
                    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

                    $update_stmt = $pdo->prepare("UPDATE luck_wallet_users SET password_hash = ? WHERE user_id = ?");
                    if ($update_stmt->execute([$newPasswordHash, $user['user_id']])) {
                        $message = 'Your password has been successfully reset. You can now log in with your new password.';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to update password. Please try again.';
                        $message_type = 'error';
                        error_log('Failed to update password for user ' . $username . ': PDO ErrorInfo ' . implode(', ', $update_stmt->errorInfo()));
                    }
                } else {
                    // This is a LuckyTime-linked user
                    $message = 'This account is linked to LuckyTime. Please log in using the "Login with LuckyTime" option. Password reset is not available here.';
                    $message_type = 'error';
                }
            } else {
                // User not found or credentials don't match
                $message = 'Username or email not found.';
                $message_type = 'error';
            }
        } catch (PDOException $e) {
            $message = 'Database error during password reset. Please try again later.';
            $message_type = 'error';
            error_log('Forgot password database error: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Reset Password - Luck Wallet</title>
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
            background-size: 100% auto;
            margin: 0;
            padding: 0;
            height: 100%;
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: 'Fira Code', sans-serif;
            color: #e0e0e0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-color: transparent;
            overflow-x: hidden;
            width: 100%;
            max-width: 100%;
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

        .reset-container {
            background: rgba(0, 20, 20, 0.9);
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 230, 230, 0.3);
            padding: 2.5rem;
            width: 100%;
            max-width: 500px;
            border: 1px solid rgba(0, 255, 255, 0.2);
            margin: 2rem 0; /* Added margin to top and bottom of container */
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
            outline: none;
            box-sizing: border-box;
            transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }

        input[type="email"]:focus,
        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: #00ffff;
            box-shadow: 0 0 0 1px #00ffff;
        }

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

        .back-to-login {
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.875rem;
            color: #a0aec0;
        }

        .back-to-login a {
            font-weight: 500;
            color: #00ffff;
            text-decoration: none;
            transition: color 0.2s ease-in-out;
            margin-left: 0.5rem;
        }

        .back-to-login a:hover {
            color: #ffffff;
            text-decoration: underline;
        }

        /* Message styles */
        .message {
            padding: 12px 16px;
            margin-bottom: 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            text-align: center;
            font-size: 0.9375rem;
            line-height: 1.5;
        }

        .success {
            background-color: rgba(16, 185, 129, 0.2);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .error {
            background-color: rgba(239, 68, 68, 0.2);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
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
            
            .reset-container {
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
            
            .reset-container {
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
            .reset-container {
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
            margin-top: auto;
            width: 100%;
            box-sizing: border-box;
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
            
            .reset-container {
                margin: 1rem;
                padding: 1.5rem;
                max-width: 100%;
                box-shadow: 0 0 15px rgba(0, 200, 200, 0.2);
            }
            
            .main-content {
                padding: 2rem 0.5rem 4rem;
                min-height: calc(100vh - 4rem);
            }
        }
        
        @media (max-width: 480px) {
            .reset-container {
                padding: 1.25rem;
            }
            
            h2 {
                font-size: 1.5rem;
                margin-bottom: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <header class="main-header">
        <div class="header-logo-container">
            <a href="../index.html" class="header-logo-button">
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
        <div class="reset-container">
            <h2>Reset Your Password</h2>
            <p style="text-align: center; color: #6b7280; margin-bottom: 1.5rem;">
                Please enter your username, email, and new password to reset your account.
            </p>
        <p style="text-align: center; color: #6b7280; margin-bottom: 1.5rem;">
            Please enter your username, email, and new password to reset your account.
        </p>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form id="forgotPasswordForm" action="forgot_password.php" method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Enter your username"
                    required
                >
            </div>
            <div class="form-group">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your email address"
                    required
                >
            </div>
            <div class="form-group">
                <label for="newPassword">New Password</label>
                <input
                    type="password"
                    id="newPassword"
                    name="newPassword"
                    placeholder="Enter your new password"
                    required
                >
            </div>
            <div class="form-group">
                <label for="confirmNewPassword">Confirm New Password</label>
                <input
                    type="password"
                    id="confirmNewPassword"
                    name="confirmNewPassword"
                    placeholder="Confirm your new password"
                    required
                >
            </div>
            <button
                type="submit"
                class="action-button"
            >
                Reset Password
            </button>
        </form>

        <p class="back-to-login">
            Remember your password?
            <a href="login.php">Back to Login</a>
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
        // Client-side password match validation
        const forgotPasswordForm = document.getElementById('forgotPasswordForm');

        forgotPasswordForm.addEventListener('submit', function(event) {
            const newPassword = document.getElementById('newPassword').value;
            const confirmNewPassword = document.getElementById('confirmNewPassword').value;

            if (newPassword !== confirmNewPassword) {
                alert('New passwords do not match! Please try again.');
                event.preventDefault(); // Prevent form submission
                return;
            }
            if (newPassword.length < 6) { // Client-side check for minimum length
                alert('New password must be at least 6 characters long.');
                event.preventDefault();
                return;
            }
        });
        
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
    </script>
</body>
</html>