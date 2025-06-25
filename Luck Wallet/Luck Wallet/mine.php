
<?php
// Start session
session_start();

// Include necessary files
require_once __DIR__ . '/php/session_handler.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.html');
    exit();
}

// Get current user data
$currentUser = getCurrentUser();
if (!$currentUser) {
    // If session exists but user data can't be loaded, clear the session
    session_unset();
    session_destroy();
    header('Location: index.html');
    exit();
}

// Initialize variables
$displayWallet = $currentUser['wallet_address'] ?? 'Connect Wallet';
$balance = $currentUser['balance'] ?? 0;
$luckBalance = $currentUser['luck_balance'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Convert Now!</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@300..700&display=swap" rel="stylesheet">
    <style>
                /* ===== Responsive Design ===== */
        @media (max-width: 992px) {
            .header {
                padding: 0 1rem;
            }
            
            .logo {
                font-size: 1.5rem;
            }
            
            .balance-display {
                padding: 0 12px;
                font-size: 0.9em;
            }
            
            .balance-display i {
                margin-right: 6px;
                font-size: 1em;
            }
            
            /* Hide balances on mobile when burger menu is visible */
            @media (max-width: 768px) {
                .nav-center {
                    display: none !important;
                }
            }
        }
        
        /* Mobile Burger Menu Styles */
        .burger-menu {
            display: none;
            flex-direction: column;
            justify-content: space-around;
            width: 2rem;
            height: 2rem;
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 0;
            z-index: 1001;
        }
        
        .burger-menu:focus {
            outline: none;
        }
        
        .burger-menu span {
            width: 2rem;
            height: 0.25rem;
            background: #00ffff;
            border-radius: 10px;
            transition: all 0.3s linear;
            position: relative;
            transform-origin: 1px;
        }
        
        .mobile-nav {
            position: fixed;
            top: 0;
            right: -100%;
            width: 80%;
            max-width: 300px;
            height: 100vh;
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(10px);
            padding: 5rem 1.5rem 2rem;
            transition: right 0.3s ease-in-out;
            z-index: 1000;
            border-left: 1px solid rgba(0, 255, 255, 0.2);
            overflow-y: auto;
        }
        
        .mobile-nav.active {
            right: 0;
        }
        
        .mobile-nav-content {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .mobile-nav-item {
            display: flex;
            align-items: center;
            padding: 0.8rem 1rem;
            color: #00ffff;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 255, 255, 0.2);
        }
        
        .mobile-nav-item i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .mobile-nav-item:hover {
            background: rgba(0, 255, 255, 0.1);
            transform: translateX(5px);
        }
        
        .mobile-wallet-address {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(0, 0, 0, 0.5);
            padding: 0.8rem 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            border: 1px solid rgba(0, 255, 255, 0.2);
        }
        
        .wallet-text {
            flex: 1;
            font-family: 'Fira Code', monospace;
            font-size: 0.8em;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            margin: 0 10px;
        }
        
        .copy-wallet {
            background: none;
            border: none;
            color: #00ffff;
            cursor: pointer;
            padding: 5px;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        
        .copy-wallet:hover {
            background: rgba(0, 255, 255, 0.2);
        }
        
        .logout {
            color: #ff6b6b !important;
            border-color: rgba(255, 107, 107, 0.3) !important;
        }
        
        .logout:hover {
            background: rgba(255, 107, 107, 0.1) !important;
        }
        
        /* Mobile Balances in Side Menu */
        .mobile-balances {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 0 0.5rem;
        }
        
        .mobile-balance {
            display: flex;
            align-items: center;
            padding: 0.8rem 1rem;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(0, 255, 255, 0.1);
            transition: all 0.3s ease;
            color: #ffffff;
        }
        
        .mobile-balance:hover {
            transform: translateX(5px);
            background: rgba(255, 255, 255, 0.08);
        }
        
        .mobile-balance.luck-balance {
            border-color: rgba(255, 105, 180, 0.3);
            background: linear-gradient(145deg, rgba(60, 10, 30, 0.8), rgba(120, 20, 60, 0.8));
        }
        
        .mobile-balance.php-balance {
            border-color: rgba(255, 215, 0, 0.3);
            background: linear-gradient(145deg, rgba(60, 50, 10, 0.8), rgba(120, 100, 20, 0.8));
        }
        
        /* Light mode styles */
        body.light-mode .mobile-balance {
            color: #333;
            border-color: rgba(0, 0, 0, 0.1);
        }
        
        body.light-mode .mobile-balance.luck-balance {
            background: linear-gradient(145deg, rgba(255, 182, 193, 0.9), rgba(255, 105, 180, 0.9));
            color: #fff;
        }
        
        body.light-mode .mobile-balance.php-balance {
            background: linear-gradient(145deg, rgba(255, 223, 0, 0.9), rgba(255, 191, 0, 0.9));
            color: #333;
        }
        
        body.light-mode .mobile-balance .mobile-balance-label {
            opacity: 0.9;
        }
        
        .mobile-balance-icon {
            width: 28px;
            height: 28px;
            margin-right: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2em;
            color: #fff;
        }
        
        .mobile-balance-info {
            display: flex;
            flex-direction: column;
        }
        
        .mobile-balance-amount {
            font-weight: 600;
            font-size: 1.1em;
            line-height: 1.2;
        }
        
        .mobile-balance-label {
            font-size: 0.8em;
            opacity: 0.8;
        }
        
        .mobile-nav-divider {
            height: 1px;
            background: rgba(0, 255, 255, 0.1);
            margin: 1rem 0;
        }
        
        @media (max-width: 768px) {
            .desktop-nav {
                display: none !important;
            }
            
            .burger-menu {
                display: flex;
            }
            
            .header {
                padding: 0.5rem 1rem;
                justify-content: space-between;
                border-bottom: 1px solid rgba(0, 255, 255, 0.1);
            }
            
            .nav-left {
                width: auto;
                order: 1;
            }
            
            .nav-center {
                order: 2;
                width: auto;
                margin: 0;
                flex-grow: 1;
                justify-content: flex-end;
                padding-right: 1rem;
                display: none; /* Hide balances from header on mobile */
            }
            
            .mobile-menu {
                order: 3;
                display: flex;
                align-items: center;
            }
            
            .convert-container {
                margin-top: 5rem;
                padding: 15px;
            }
        }
        
        @media (max-width: 480px) {
            .nav-center {
                justify-content: flex-end;
            }
            
            .balance-display {
                padding: 0 8px !important;
                font-size: 0.7em !important;
            }
            
            .balance-display span {
                display: none;
            }
            
            .balance-display.luck-balance span {
                display: inline;
            }
            .convert-container {
                padding: 15px 10px;
                margin: 7rem 10px 1rem;
                width: calc(100% - 20px);
            }
            
            .balance-display {
                font-size: 0.8em;
                padding: 0 8px;
            }
            
            .logo {
                font-size: 1.3rem;
            }
            
            .nav-buttons {
                gap: 8px;
            }
            
            .dashboard-btn {
                padding: 6px 12px;
                font-size: 0.9em;
            }
        }
        
        /* ===== Base Styles ===== */
        body {
            font-family: 'Fira Code', monospace;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background: radial-gradient(circle at center, #0f0f23 0%, #000000 100%);
            color: #00ff00;
            display: flex;
            flex-direction: column;
            transition: background 0.5s ease, color 0.5s ease;
        }

        /* ===== Light Mode ===== */
        body.light-mode {
            background: radial-gradient(circle at center, #99C2CC 0%, #547A9A 100%);
            color: #003366;
        }
        
        /* Ensure all text in light mode is visible */
        body.light-mode,
        body.light-mode p,
        body.light-mode span:not(.balance-display),
        body.light-mode div:not(.mining-container *) {
            color: #001a33 !important;
        }
        
        /* Specific text elements that need adjustment */
        body.light-mode .mining-header p,
        body.light-mode .mining-tip,
        body.light-mode .mining-tip span {
            color: #001a33 !important;
            opacity: 0.95;
        }

        body.light-mode .header {
            background: linear-gradient(to right, #B0E0E6, #4682B4, #B0E0E6);
            border-bottom: 1px solid #003366;
            box-shadow: 0 5px 25px rgba(0, 51, 102, 0.3),
                        0 0 30px rgba(0, 51, 102, 0.2);
        }

        body.light-mode .header-logo-container {
            color: #003366;
            text-shadow: 0 0 5px rgba(0, 51, 102, 0.5),
                         0 0 10px rgba(0, 51, 102, 0.3);
        }

        body.light-mode .header-logo-container:hover {
            text-shadow: 0 0 8px rgba(0, 51, 102, 0.7),
                         0 0 15px rgba(0, 51, 102, 0.5);
        }

        body.light-mode .balance-display,
        body.light-mode .balance-display span {
            color: #003366;
            text-shadow: 0 0 5px rgba(0, 51, 102, 0.5);
        }
        
        body.light-mode .nav-right a,
        body.light-mode .nav-right button {
            color: #003366;
        }
        
        body.light-mode .user-profile-bubble {
            color: #003366;
            border-color: #003366;
        }
        
        /* Ensure wallet address is visible in light mode */
        body.light-mode .account-id,
        body.light-mode #walletText {
            color: #003366 !important;
        }
        
        /* Style for dropdown in light mode */
        body.light-mode .dropdown-content {
            background-color: #B0C4DE;
            border: 1px solid #003366;
        }
        
        body.light-mode .dropdown-content a {
            color: #003366;
        }
        
        body.light-mode .dropdown-content a:hover {
            background-color: #4682B4;
            color: white;
        }
        
        /* ===== Navbar Layout ===== */
        .header {
            background: linear-gradient(to right, #0a0a0a, #004d4d, #0a0a0a);
            padding: 10px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #ffffff;
            height: 4rem;
            border-bottom: 0.5px solid #00ffff;
            box-shadow: 0 5px 25px rgba(0, 255, 255, 0.5),
                        0 0 30px rgba(0, 255, 255, 0.3);
            z-index: 1000;
            position: sticky;
            top: 0;
            transition: all 0.4s ease-in-out;
        }

        /* Light mode header styles */
        body.light-mode .header {
            background: rgba(176, 224, 230, 0.9);
            border-bottom: 1px solid rgba(0, 51, 102, 0.3);
            box-shadow: 0 2px 15px rgba(0, 51, 102, 0.2);
        }

        /* Balance Display Styles */
        .balance-display {
            display: flex;
            align-items: center;
            height: 40px;
            padding: 0 20px;
            margin: 0 4px;
            border-radius: 25px;
            background: rgba(0, 0, 0, 0.3);
            border: 2px solid #00ffff;
            box-shadow: 0 0 15px rgba(0, 255, 255, 0.4);
            color: #00ffff;
            transition: all 0.3s ease;
            box-sizing: border-box;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            font-weight: 500;
            letter-spacing: 0.5px;
            text-shadow: 0 0 8px rgba(0, 255, 255, 0.7);
        }
        
        .balance-display::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(0, 255, 255, 0.1), transparent);
            transition: 0.5s;
        }
        
        .balance-display:hover::before {
            left: 100%;
        }
        
        .balance-display:hover {
            transform: translateY(-3px);
            background: rgba(0, 255, 255, 0.15);
            box-shadow: 0 0 25px rgba(0, 255, 255, 0.7);
            text-shadow: 0 0 15px rgba(0, 255, 255, 1);
        }
        
        .balance-display:active {
            transform: translateY(0);
            box-shadow: 0 0 10px rgba(0, 255, 255, 0.5);
        }

        .balance-display i {
            margin-right: 10px;
            font-size: 1.1em;
        }

        .balance-display span {
            font-weight: 500;
            font-size: 0.95em;
        }

        .luck-balance {
            background: rgba(0, 0, 0, 0.3) !important;
            border-color: #00ffff !important;
            color: #00ffff !important;
        }

        .ecasino-balance {
            background: rgba(0, 0, 0, 0.3) !important;
            border-color: #00ffff !important;
            color: #00ffff !important;
        }

        /* Light mode balance styles */
        body.light-mode .balance-display {
            background: rgba(255, 255, 255, 0.3);
            border-color: #003366;
            color: #003366;
            box-shadow: 0 0 15px rgba(0, 51, 102, 0.2);
            text-shadow: 0 0 5px rgba(0, 51, 102, 0.3);
        }
        
        body.light-mode .balance-display:hover {
            background: rgba(0, 51, 102, 0.1);
            box-shadow: 0 0 25px rgba(0, 102, 204, 0.4);
            text-shadow: 0 0 5px rgba(0, 51, 102, 0.5);
            transform: translateY(-3px);
        }
        
        body.light-mode .balance-display:active {
            box-shadow: 0 0 8px rgba(0, 51, 102, 0.3);
        }
        
        body.light-mode .luck-balance,
        body.light-mode .ecasino-balance {
            background: rgba(255, 255, 255, 0.3) !important;
            border-color: #003366 !important;
        }

        /* Nav sections */
        .nav-left, .nav-center, .nav-right {
            display: flex;
            align-items: center;
            height: 100%;
            transition: all 0.3s ease;
        }
        
        .nav-left {
            width: 0;
            flex: 1;
        }
        
        .nav-center {
            width: auto;
            justify-content: center;
            flex: 0 0 auto;
        }
        
        .nav-right {
            width: auto;
            flex: 1;
            justify-content: flex-end;
        }

        .nav-center {
            justify-content: center;
            gap: 15px;
        }

        .nav-right {
            justify-content: flex-end;
        }

        .nav-right {
            justify-content: flex-end;
            gap: 15px;
        }

        /* Logo styles */
        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            color: #FFD700;
            text-shadow: 0 0 10px rgba(255, 215, 0, 0.7);
            letter-spacing: 1px;
            transition: all 0.3s ease;
        }


        body.light-mode .logo {
            color: #003366;
            text-shadow: 0 0 5px rgba(0, 51, 102, 0.5);
        }

        .logo:hover {
            text-shadow: 0 0 15px #FFD700,
                         0 0 30px rgba(255, 215, 0, 0.9);
        }


        /* ===== Modal Styles ===== */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .modal.show {
            display: flex;
            opacity: 1;
        }
        

        
        .nav-buttons {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .dashboard-btn {
            background: rgba(0, 0, 0, 0.3);
            color: #00ffff;
            padding: 10px 25px;
            border-radius: 25px;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            border: 2px solid #00ffff;
            text-shadow: 0 0 8px rgba(0, 255, 255, 0.7);
            font-weight: 500;
            letter-spacing: 0.5px;
            box-shadow: 0 0 15px rgba(0, 255, 255, 0.4);
            min-width: 120px;
        }
        
        /* Light mode styles */
        body.light-mode .dashboard-btn {
            background: rgba(255, 255, 255, 0.3);
            border-color: #003366;
            color: #003366;
            text-shadow: 0 0 5px rgba(0, 51, 102, 0.3);
            box-shadow: 0 0 15px rgba(0, 51, 102, 0.2);
        }
        
        .dashboard-btn:hover {
            background: rgba(0, 255, 255, 0.15);
            transform: translateY(-3px);
            box-shadow: 0 0 25px rgba(0, 255, 255, 0.7);
            text-shadow: 0 0 15px rgba(0, 255, 255, 1);
        }
        
        body.light-mode .dashboard-btn:hover {
            background: rgba(0, 51, 102, 0.1);
            box-shadow: 0 0 25px rgba(0, 102, 204, 0.4);
            text-shadow: 0 0 5px rgba(0, 51, 102, 0.5);
        }
        
        .dashboard-btn:active {
            transform: translateY(0);
            box-shadow: 0 0 10px rgba(0, 255, 255, 0.5);
        }
        
        body.light-mode .dashboard-btn:active {
            box-shadow: 0 0 8px rgba(0, 51, 102, 0.3);
        }

        .user-profile-bubble {
            position: relative;
            display: inline-block;
            cursor: pointer;
        }
        
        .account-id {
            display: flex;
            align-items: center;
            background: rgba(0, 0, 0, 0.3);
            padding: 8px 15px;
            border-radius: 20px;
            border: 1px solid rgba(0, 255, 255, 0.3);
            color: #00ffff;
            transition: all 0.3s ease;
        }
        
        body.light-mode .account-id {
            background: rgba(176, 196, 222, 0.5);
            border-color: rgba(0, 51, 102, 0.3);
            color: #001a33;
            box-shadow: 0 2px 10px rgba(0, 51, 102, 0.15);
        }
        
        .account-id:hover {
            background: rgba(0, 255, 255, 0.1);
            box-shadow: 0 0 10px rgba(0, 255, 255, 0.5);
        }
        
        body.light-mode .account-id:hover {
            background: rgba(176, 196, 222, 0.7);
            box-shadow: 0 0 15px rgba(0, 51, 102, 0.25);
        }
        
        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background-color: rgba(0, 30, 60, 0.95);
            min-width: 160px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            z-index: 1;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid rgba(0, 255, 255, 0.3);
        }
        
        body.light-mode .dropdown-content {
            background-color: rgba(176, 196, 222, 0.95);
            border-color: rgba(0, 51, 102, 0.3);
            box-shadow: 0 8px 16px rgba(0, 51, 102, 0.15);
        }
        
        .user-profile-bubble:hover .dropdown-content {
            display: block;
        }
        
        .dropdown-content a {
            color: #00ffff;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            transition: all 0.3s ease;
        }
        
        body.light-mode .dropdown-content a {
            color: #001a33;
        }
        
        .dropdown-content a:hover {
            background-color: rgba(0, 255, 255, 0.1);
            color: white;
        }
        
        body.light-mode .dropdown-content a:hover {
            background-color: rgba(0, 51, 102, 0.1);
            color: #000;
        }
        
        .theme-toggle-button {
            background-color: #004d4d;
            border: 2px solid #00ffff;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            box-shadow: 0 0 15px rgba(0, 255, 255, 0.7);
            transition: all 0.3s ease;
            color: #ffffff;
            font-size: 1.4em;
            padding: 0;
        }
        
        /* Light mode styles */
        body.light-mode .theme-toggle-button {
            background-color: #B0C4DE; /* Light steel blue */
            border-color: #003366;
            box-shadow: 0 0 15px rgba(0, 51, 102, 0.5);
            color: #003366;
        }
        
        .theme-toggle-button:hover {
            background-color: #006666;
            box-shadow: 0 0 25px rgba(0, 255, 255, 1);
            transform: rotate(30deg);
        }
        
        body.light-mode .theme-toggle-button:hover {
            background-color: #ADD8E6; /* Lighter blue on hover */
            box-shadow: 0 0 25px rgba(0, 51, 102, 0.8);
        }
        
        .theme-toggle-button:active {
            transform: rotate(30deg) scale(0.95);
        }
        
        /* Ensure the sun/moon icon is visible in both themes */
        .theme-toggle-button i {
            filter: drop-shadow(0 0 2px rgba(0, 0, 0, 0.3));
        }
        
        body.light-mode .theme-toggle-button i {
            filter: drop-shadow(0 0 2px rgba(255, 255, 255, 0.7));
        }

        .nav-left {
            display: flex;
            align-items: center;
            margin-right: auto; /* Pushes other elements to the right */
        }
        
        .header-logo-container {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            width: auto;
            position: relative;
            height: auto;
            font-size: 2rem;
            color: #00ffff;
            text-shadow: 0 0 10px #00ffff,
                         0 0 20px rgba(0, 255, 255, 0.7);
            font-weight: bold;
            letter-spacing: 2px;
            transition: text-shadow 0.3s ease-in-out, color 0.3s ease-in-out;
            padding: 0 20px;
        }
        
        .header-logo-container:hover {
            text-shadow: 0 0 15px #00ffff,
                         0 0 30px rgba(0, 255, 255, 0.9);
        }



        /* Back Button Styles */
        .back-button {
            background-color: transparent;
            border: 2px solid #00e6e6;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            box-shadow: 0 0 10px rgba(0, 230, 230, 0.7);
            transition: all 0.3s ease;
            color: #00ffff;
            font-size: 1.2em;
            padding: 0;
        }

        .back-button:hover {
            background-color: rgba(0, 230, 230, 0.2);
            box-shadow: 0 0 20px rgba(0, 230, 230, 1);
        }


        /* --- Existing Convert Page Main Styles --- */
        main {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 10px; /* Reduced padding */
        }

        .convert-container {
            background-color: rgba(0, 0, 0, 0.7);
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
            border: 1px solid #00ffff;
            box-sizing: border-box;
        }

        .convert-container h2 {
            color: #00ffff;
            margin-bottom: 20px; /* Reduced margin */
            text-shadow: 0 0 10px #00ffff;
            font-size: 1.8em; /* Slightly smaller font for heading */
        }

        /* New flex container for From and To fields */
        .currency-selection-row {
            display: flex;
            gap: 15px; /* Space between the two currency select fields */
            margin-bottom: 20px;
        }

        .form-group {
            flex: 1; /* Allow each form group to take equal space */
            margin-bottom: 0; /* Remove bottom margin here, handled by row container */
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #b0e0e6;
            font-weight: bold;
            font-size: 0.9em; /* Slightly smaller font */
        }

        .form-group select,
        .form-group input[type="number"] {
            width: 100%;
            padding: 10px; /* Reduced padding */
            border-radius: 8px;
            border: 1px solid #00cccc;
            background-color: rgba(0, 51, 51, 0.7);
            color: #ffffff;
            font-size: 0.9em; /* Slightly smaller font */
            box-sizing: border-box;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .form-group select:focus,
        .form-group input[type="number"]:focus {
            border-color: #00ffff;
            box-shadow: 0 0 10px rgba(0, 255, 255, 0.5);
            outline: none;
        }

        .conversion-details p {
            font-size: 0.9em; /* Slightly smaller font */
            color: #b0e0e6;
            margin-bottom: 8px; /* Reduced margin */
            text-align: left;
        }

        .conversion-details strong {
            color: #00ffff;
            text-shadow: 0 0 5px rgba(0, 255, 255, 0.5);
        }

        .execute-button {
            background-color: #008080;
            color: #ffffff;
            padding: 12px 25px; /* Reduced padding */
            border: 1px solid #00ffff;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1em; /* Slightly smaller font */
            font-weight: bold;
            box-shadow: 0 0 15px rgba(0, 255, 255, 0.7);
            transition: background-color 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
            width: 100%;
            margin-top: 15px; /* Reduced margin */
        }

        .execute-button:hover:not(:disabled) {
            background-color: #00cccc;
            box-shadow: 0 0 25px rgba(0, 255, 255, 1);
        }

        .execute-button:disabled {
            background-color: #333333;
            color: #888888;
            border-color: #555555;
            cursor: not-allowed;
            box-shadow: none;
            opacity: 0.7;
        }

        /* Light mode specific styles for Convert Page */
        body.light-mode .convert-container {
            background-color: rgba(194, 238, 255, 0.7); /* Lighter background */
            box-shadow: 0 0 30px rgba(70, 130, 180, 0.7);
            border: 1px solid #4682B4; /* Darker border */
        }

        body.light-mode .convert-container h2 {
            color: #003366; /* Darker blue heading */
            text-shadow: 0 0 10px rgba(0, 51, 102, 0.3);
        }

        body.light-mode .form-group label {
            color: #003366; /* Darker label text */
        }

        body.light-mode .form-group select,
        body.light-mode .form-group input[type="number"] {
            border: 1px solid #4682B4; /* Darker border for inputs */
            background-color: #E0FFFF; /* Lighter input background */
            color: #333333; /* Darker input text */
        }

        body.light-mode .form-group select:focus,
        body.light-mode .form-group input[type="number"]:focus {
            border-color: #003366; /* Darker focus border */
            box-shadow: 0 0 10px rgba(0, 51, 102, 0.5);
        }

        body.light-mode .conversion-details p {
            color: #003366; /* Darker details text */
        }

        body.light-mode .conversion-details strong {
            color: #004080; /* Darker strong text */
            text-shadow: 0 0 5px rgba(0, 64, 128, 0.5);
        }

        body.light-mode .execute-button {
            background-color: #6495ED; /* Lighter blue button */
            color: #ffffff;
            border: 1px solid #003366; /* Darker button border */
            box-shadow: 0 0 15px rgba(0, 51, 102, 0.7);
        }

        body.light-mode .execute-button:hover:not(:disabled) {
            background-color: #4682B4; /* Slightly darker hover */
            box-shadow: 0 0 25px rgba(0, 51, 102, 1);
        }

        body.light-mode .execute-button:disabled {
            background-color: #CCCCCC;
            color: #666666;
            border-color: #999999;
        }

        /* --- FOOTER STYLES (Matching dashboard.php) --- */
        footer {
            background-color: rgba(0, 0, 0, 0.5);
            padding: 10px 30px; /* Added horizontal padding */
            display: flex; /* Use flexbox */
            justify-content: center; /* Center content horizontally */
            align-items: center; /* Vertically centers items */
            color: #ffffff;
            transition: background-color 0.5s ease, color 0.5s ease;
            text-align: center; /* Ensure text is centered */
            height: 2.7rem;
            border-top: 1px solid rgba(0, 255, 255, 0.3);
            box-shadow: 0 -5px 25px rgba(0, 255, 255, 0.3),
                        0 0 30px rgba(0, 255, 255, 0.2);
        }

        body.light-mode footer {
            background-color: rgba(255, 255, 255, 0.6);
            color: #333333;
        }

        footer p {
            margin: 0;
            font-size: 0.95rem;
            color: #ffffff;
            font-weight: 400;
            letter-spacing: 0.3px;
        }
        
        body.light-mode footer p {
            color: #333333;
        }

        /* ===== Modal Styles ===== */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
        }

        .modal.show {
            display: flex;
            justify-content: center;
            align-items: center;
            opacity: 1;
        }

        .modal-content {
            background: linear-gradient(145deg, #0a1929, #0a1a2e);
            padding: 25px;
            border-radius: 15px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 5px 30px rgba(0, 255, 255, 0.2);
            border: 1px solid rgba(0, 255, 255, 0.2);
            position: relative;
            animation: modalFadeIn 0.3s ease-out;
        }

        @keyframes modalFadeIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .close-button {
            position: absolute;
            right: 15px;
            top: 10px;
            font-size: 28px;
            font-weight: bold;
            color: #00ffff;
            cursor: pointer;
            transition: color 0.3s ease;
        }


        .close-button:hover {
            color: #ff69b4;
        }

        #notificationMessage {
            margin: 20px 0;
            font-size: 16px;
            line-height: 1.5;
            color: #fff;
        }


        .confirm-button {
            background: linear-gradient(45deg, #00b4d8, #0096c7);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 30px;
            font-size: 16px;
            cursor: pointer;
            margin-top: 15px;
            width: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 180, 216, 0.3);
        }


        .confirm-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 180, 216, 0.4);
        }


        .success {
            color: #4caf50;
        }


        .error {
            color: #ff6b6b;
        }
    </style>
</head>
<body>
    
    <header class="header">
        <div class="nav-left">
            <div class="header-logo-container">
                LUCK WALLET
            </div>
        </div>
        
        <div class="nav-center">
            <div class="balance-display luck-balance" style="color: #ff69b4; border-color: rgba(255, 105, 180, 0.4);">
                <img src="assets/images/logo.png" alt="LUCK" style="width: 32px; height: 32px; margin-right: 12px; object-fit: contain;">
                <span><?php echo number_format($luckBalance, 2); ?> LUCK</span>
            </div>
        </div>
        
        <!-- Desktop Navigation -->
        <div class="nav-right desktop-nav">
            <div class="nav-buttons">
                <a href="/Luck%20Wallet/dashboard.php" class="dashboard-btn">
                    <i class="fas fa-tachometer-alt" style="margin-right: 5px;"></i>Dashboard
                </a>
                <button id="themeToggleButton" class="theme-toggle-button" aria-label="Toggle dark/light mode">
                    <i class="fas fa-sun"></i>
                </button>
            </div>
            <div class="user-profile-bubble" id="userProfileBubble">
                <span class="account-id" id="userAccountId">
                    <i class="fas fa-wallet" style="margin-right: 6px;"></i>
                    <span id="walletText"><?php echo htmlspecialchars($displayWallet); ?></span>
                </span>
                <div class="dropdown-content">
                    <a href="/Luck%20Wallet/auth/logout.php" class="logout-button">Logout</a>
                </div>
            </div>
        </div>
        
        <!-- Mobile Burger Menu -->
        <div class="mobile-menu">
            <button class="burger-menu" id="burgerMenu">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <div class="mobile-nav" id="mobileNav">
                <div class="mobile-nav-content">
                    <!-- Mobile Balances -->
                    <div class="mobile-balances">
                        <div class="mobile-balance luck-balance">
                            <img src="assets/images/logo.png" alt="LUCK" class="mobile-balance-icon">
                            <div class="mobile-balance-info">
                                <span class="mobile-balance-amount"><?php echo number_format($luckBalance, 2); ?></span>
                                <span class="mobile-balance-label">LUCK</span>
                            </div>
                        </div>
                        <div class="mobile-balance php-balance">
                            <i class="fas fa-coins mobile-balance-icon"></i>
                            <div class="mobile-balance-info">
                                <span class="mobile-balance-amount">₱<?php echo number_format($currentUser['ecasino_balance'] ?? 0, 2); ?></span>
                                <span class="mobile-balance-label">PHP</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mobile-nav-divider"></div>
                    
                    <a href="/Luck%20Wallet/dashboard.php" class="mobile-nav-item">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <button class="mobile-nav-item" id="mobileThemeToggle">
                        <i class="fas fa-sun"></i> Toggle Theme
                    </button>
                    <div class="mobile-nav-item mobile-wallet-address">
                        <i class="fas fa-wallet"></i>
                        <span class="wallet-text"><?php echo htmlspecialchars($displayWallet); ?></span>
                        <button class="copy-wallet" data-wallet="<?php echo htmlspecialchars($currentUser['wallet_address']); ?>">
                            <i class="far fa-copy"></i>
                        </button>
                    </div>
                    <a href="/Luck%20Wallet/auth/logout.php" class="mobile-nav-item logout">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
        </nav>
    </header>

    <main>
        <div class="mining-container">
            <div class="mining-header">
                <h1>Mine $LUCK</h1>
                <p>Earn passive $LUCK by mining. Click start to begin your 24-hour mining session!</p>
            </div>
            
            <div class="mining-card">
                <div class="mining-animation" id="miningAnimation">
                    <div class="mining-pickaxe">
                        <img src="assets/images/picaxe.png" alt="Mining Pickaxe" class="pickaxe-image">
                    </div>
                    <div class="mining-rock">
                        <img src="assets/images/rock.png" alt="Mining Rock" class="rock-image">
                        <div class="crack"></div>
                        <div class="crack"></div>
                        <div class="crack"></div>
                    </div>
                    <div class="mining-sparks"></div>
                    <div class="mining-particles" id="miningParticles"></div>
                </div>
                
                <div class="mining-stats">
                    <div class="stat">
                        <span class="stat-label">Current Session</span>
                        <span class="stat-value" id="miningTime">00:00:00</span>
                    </div>
                    <div class="stat">
                        <span class="stat-label">Mined This Session</span>
                        <span class="stat-value" id="minedAmount">0.00 LUCK</span>
                    </div>
                    <div class="stat">
                        <span class="stat-label">Estimated Daily</span>
                        <span class="stat-value">~35.00 LUCK</span>
                    </div>
                </div>
                
                <button class="mining-button" id="startMiningBtn">
                    <span class="button-text">Start Mining</span>
                    <div class="button-loader"></div>
                </button>
                
                <div class="mining-progress">
                    <div class="progress-bar" id="miningProgress"></div>
                    <div class="progress-text" id="progressText">0% Complete</div>
                </div>
            </div>
            
            <div class="mining-tip">
                <i class="fas fa-lightbulb"></i>
                <span>Your mining session will continue even if you close this page. Come back in 24 hours to claim your rewards!</span>
            </div>
        </div>
    </main>
    
    <style>
        /* Mining Container */
        .mining-container {
            max-width: 800px;
            width: 100%;
            padding: 2rem;
            margin: 0 auto;
            text-align: center;
        }
        
        .mining-header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(45deg, #00ffff, #ff69b4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }
        
        body.light-mode .mining-header h1 {
            background: linear-gradient(45deg, #006666, #2c5282);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .mining-header p {
            color: #aaa;
            margin-bottom: 2rem;
            font-size: 1.1rem;
            transition: color 0.3s ease;
        }
        
        /* Mining Card */
        .mining-card {
            background: rgba(20, 20, 40, 0.8);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
        }
        
        /* Mining Animation */
        .mining-animation {
            width: 200px;
            height: 200px;
            margin: 0 auto 2rem;
            position: relative;
            transform: scale(0.9);
            opacity: 0.8;
            transition: all 0.3s ease;
        }
        
        .mining-card:hover .mining-animation {
            transform: scale(1);
            opacity: 1;
        }
        
        .mining-pickaxe {
            position: absolute;
            width: 100px;    /* Reduced from 150px */
            height: 100px;   /* Reduced from 150px */
            top: 25%;       /* Increased from 15% to lower the pickaxe */
            left: 10%;
            transform-origin: center 30%;
            transform: translateX(-50%) rotate(-20deg);
            z-index: 2;
            transition: transform 0.5s ease-out;
            pointer-events: none;
        }
        
        .pickaxe-image {
            width: 100%;
            height: 100%;
            object-fit: contain;
            image-rendering: -webkit-optimize-contrast;
            image-rendering: crisp-edges;
            -ms-interpolation-mode: nearest-neighbor;
            pointer-events: none;
        }
        
        .mining-card.mining .mining-pickaxe {
            animation: mine 2s infinite ease-in-out;
        }
        
        /* Hide the old pickaxe parts */
        .pickaxe-handle, .pickaxe-head {
            display: none;
        }
        
        .mining-rock {
            position: absolute;
            width: 180px;
            height: 120px;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: flex-end;
        }
        
        .rock-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 10px;
            position: relative;
            z-index: 1;
        }
        
        .crack {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .crack:nth-child(1) {
            width: 20px;
            height: 20px;
            top: 30%;
            left: 30%;
        }
        
        .crack:nth-child(2) {
            width: 15px;
            height: 15px;
            top: 50%;
            right: 25%;
        }
        
        .crack:nth-child(3) {
            width: 25px;
            height: 25px;
            bottom: 20%;
            left: 40%;
        }
        
        .mining-sparks {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
            opacity: 0;
        }
        
        .mining-particles {
            position: absolute;
            width: 180px;  /* Match rock width */
            height: 200px;  /* Height for the falling area */
            bottom: -800px;     /* Align with bottom of rock */
            left: 350%;     /* Center horizontally */
            transform: translateX(-50%);
            pointer-events: none;
            overflow: visible;
            z-index: 1000;
            /* Add a subtle guide (can be removed in production) */
            /* border: 1px dashed rgba(255,255,255,0.3); */
        }
        
        .mining-particle {
            position: absolute;
            width: 60px;
            height: 60px;
            background-image: url('assets/images/circle.png');
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            background-color: gold; /* Fallback in case image doesn't load */
            border-radius: 50%;
            opacity: 1;
            z-index: 1001;
            pointer-events: none;
            will-change: transform, opacity;
            box-shadow: 0 0 5px rgba(255, 215, 0, 0.5);
        }
        
        @keyframes particle-fall {
            /* Initial launch */
            0% {
                transform: translate(0, 0) rotate(0deg) scale(1);
                opacity: 1;
                filter: drop-shadow(0 0 10px rgba(255, 215, 0, 0.9));
                animation-timing-function: cubic-bezier(0.5, 0.8, 0.8, 1);
            }
            /* Upward motion */
            20% {
                transform: translate(calc(var(--tx, 30) * 0.5px), -100px) rotate(90deg) scale(1);
                opacity: 1;
                filter: drop-shadow(0 0 15px rgba(255, 215, 0, 0.9));
                animation-timing-function: cubic-bezier(0.2, 0.5, 0.8, 1);
            }
            /* Peak height */
            35% {
                transform: translate(calc(var(--tx, 30) * 1px), -120px) rotate(180deg) scale(1);
                opacity: 1;
                animation-timing-function: cubic-bezier(0.2, 0.4, 0.8, 1);
            }
            /* Start descending */
            50% {
                transform: translate(calc(var(--tx, 30) * 1.2px), -80px) rotate(270deg) scale(1);
                opacity: 1;
                animation-timing-function: cubic-bezier(0.2, 0.3, 0.8, 1);
            }
            /* First impact */
            65% {
                transform: translate(calc(var(--tx, 30) * 1.4px), 0) rotate(360deg) scale(1.1, 0.8);
                opacity: 0.9;
                animation-timing-function: cubic-bezier(0.2, 0.9, 0.6, 1);
            }
            /* First bounce up */
            75% {
                transform: translate(calc(var(--tx, 30) * 1.5px), -40px) rotate(390deg) scale(0.95, 1.05);
                animation-timing-function: cubic-bezier(0.2, 0.6, 0.8, 1);
            }
            /* Second impact */
            85% {
                transform: translate(calc(var(--tx, 30) * 1.6px), 0) rotate(420deg) scale(1.05, 0.9);
                animation-timing-function: cubic-bezier(0.2, 0.8, 0.6, 1);
            }
            /* Second bounce up */
            90% {
                transform: translate(calc(var(--tx, 30) * 1.65px), -15px) rotate(435deg) scale(0.98, 1.02);
                opacity: 0.8;
                animation-timing-function: cubic-bezier(0.2, 0.7, 0.8, 1);
            }
            /* Settle */
            96% {
                transform: translate(calc(var(--tx, 30) * 1.7px), 0) rotate(445deg) scale(1.02, 0.98);
                opacity: 0.7;
                animation-timing-function: cubic-bezier(0.2, 0.3, 0.8, 1);
            }
            /* Final fade out */
            100% {
                transform: translate(calc(var(--tx, 30) * 1.8px), 0) rotate(450deg) scale(1);
                opacity: 0;
                filter: drop-shadow(0 0 5px rgba(255, 165, 0, 0.3));
            }
        }
        
        .mining-card.mining .mining-sparks {
            animation: sparkle 2s infinite;
        }
        
        /* Stats */
        .mining-stats {
            display: flex;
            justify-content: space-between;
            margin: 2rem 0;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .stat {
            flex: 1;
            min-width: 150px;
            background: rgba(0, 0, 0, 0.3);
            padding: 1rem;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .stat-label {
            display: block;
            color: #aaa;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            transition: color 0.3s ease;
        }
        
        .stat-value {
            font-size: 1.2rem;
            font-weight: bold;
            color: #fff;
            transition: color 0.3s ease;
        }
        
        /* Stats container adjustments */
        .mining-stats {
            background: rgba(0, 0, 0, 0.15);
            padding: 1.5rem;
            border-radius: 12px;
            margin: 1.5rem 0;
        }
        
        body.light-mode .mining-stats {
            background: rgba(255, 255, 255, 0.7);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        /* Stats text styling */
        .stat-label {
            color: #ddd !important;
            font-weight: 600;
            font-size: 0.95rem;
            letter-spacing: 0.5px;
            margin-bottom: 0.3rem;
            text-transform: uppercase;
        }
        
        .stat-value {
            color: #fff !important;
            font-size: 1.4rem;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        /* Light mode specific */
        body.light-mode .stat-label {
            color: #555 !important;
            opacity: 0.9;
        }
        
        body.light-mode .stat-value {
            color: #001a33 !important;
            text-shadow: none;
            background: linear-gradient(45deg, #0055aa, #0088ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        /* Mining Button */
        .mining-button {
            position: relative;
            background: linear-gradient(45deg, #00ffff, #0088ff);
            color: white;
            border: none;
            padding: 1rem 2rem;
            font-size: 1.1rem;
            border-radius: 50px;
            cursor: pointer;
            margin: 1.5rem 0;
            overflow: hidden;
            transition: all 0.3s ease;
            width: 100%;
            max-width: 300px;
            box-shadow: 0 5px 15px rgba(0, 200, 255, 0.4);
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        /* Light mode button */
        body.light-mode .mining-button {
            background: linear-gradient(45deg, #0088cc, #0066aa);
            color: white;
            box-shadow: 0 5px 15px rgba(0, 100, 200, 0.4);
        }
        
        body.light-mode .mining-button:hover {
            box-shadow: 0 8px 20px rgba(0, 100, 200, 0.6);
        }
        
        .mining-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 200, 255, 0.6);
        }
        
        .mining-button:active {
            transform: translateY(0);
        }
        
        .mining-button:disabled {
            background: #444;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        body.light-mode .mining-button:disabled {
            background: #aaa;
            color: #666;
        }
        
        .button-text {
            position: relative;
            z-index: 1;
        }
        
        .button-loader {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, #0088ff, #00aaff);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .mining-button.loading .button-text {
            opacity: 0;
        }
        
        .mining-button.loading .button-loader {
            opacity: 1;
            animation: loading 1.5s infinite;
        }
        
        /* Progress Bar */
        .mining-progress {
            width: 100%;
            height: 10px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 5px;
            overflow: hidden;
            margin-top: 2rem;
            position: relative;
        }
        
        .progress-bar {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #00ffff, #ff69b4);
            border-radius: 5px;
            transition: width 0.5s ease;
            position: relative;
            overflow: hidden;
        }
        
        .progress-bar::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(
                90deg,
                rgba(255, 255, 255, 0) 0%,
                rgba(255, 255, 255, 0.4) 50%,
                rgba(255, 255, 255, 0) 100%
            );
            animation: shine 2s infinite;
        }
        
        .progress-text {
            position: absolute;
            top: -25px;
            left: 0;
            width: 100%;
            text-align: center;
            font-size: 0.9rem;
            color: #aaa;
        }
        
        /* Mining Tip */
        .mining-tip {
            margin-top: 2rem;
            padding: 1rem;
            background: rgba(0, 200, 255, 0.1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            color: #00aaff;
            font-size: 0.9rem;
        }
        
        .mining-tip i {
            font-size: 1.2rem;
        }
        
        /* Animations */
        @keyframes mine {
            0%, 100% {
                transform: translateX(-50%) rotate(-20deg);
            }
            50% {
                transform: translateX(-50%) rotate(10deg);
            }
        }
        
        @keyframes sparkle {
            0%, 100% {
                opacity: 0;
            }
            50% {
                opacity: 1;
            }
        }
        
        @keyframes loading {
            0% {
                transform: translateX(-100%);
            }
            100% {
                transform: translateX(100%);
            }
        }
        
        @keyframes shine {
            0% {
                transform: translateX(-100%) skewX(-30deg);
            }
            100% {
                transform: translateX(100%) skewX(-30deg);
            }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .mining-stats {
                flex-direction: column;
                gap: 1rem;
            }
            
            .stat {
                width: 100%;
            }
            
            .mining-header h1 {
                font-size: 2rem;
            }
        }
    </style>

    <!-- Notification Modal -->
    <div id="notificationModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <div id="notificationMessage"></div>
            <button id="notificationConfirm" class="confirm-button">OK</button>
        </div>
    </div>

    <footer>
        <p>&copy; 2025 LuckyWallet. All rights reserved.</p>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/web3@1.5.2/dist/web3.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Mining functionality
        document.addEventListener('DOMContentLoaded', function() {
            const miningCard = document.querySelector('.mining-card');
            const startMiningBtn = document.getElementById('startMiningBtn');
            const miningProgress = document.getElementById('miningProgress');
            const progressText = document.getElementById('progressText');
            const miningTime = document.getElementById('miningTime');
            const minedAmount = document.getElementById('minedAmount');
            
            // Check for existing mining session on page load
            checkMiningSession();
            
            // Set up periodic status check (every 30 seconds)
            setInterval(checkMiningSession, 30000);
            
            // Add click handler for claim button
            startMiningBtn.addEventListener('click', handleMiningButtonClick);
            
            // Start mining function is now handled by the new implementation
            // All mining logic is now in the handleMiningButtonClick, startMining, and updateMiningUI functions
            
            // Create particle effect with falling and bouncing
            function createParticles(x, y, count = 5) {
                const particlesContainer = document.getElementById('miningParticles');
                const containerRect = particlesContainer.getBoundingClientRect();
                
                for (let i = 0; i < count; i++) {
                    const particle = document.createElement('div');
                    particle.className = 'mining-particle';
                    
                    // Random horizontal movement (centered around 0 with spread)
                    const horizontalMovement = 100 + Math.random() * 150; // More controlled horizontal distance
                    
                    // Random size between 20px and 40px
                    const size = 20 + Math.random() * 20;
                    
                    // Random duration between 1.5s and 2.5s
                    const duration = 1.5 + Math.random();
                    
                    // Calculate position relative to container
                    const posX = x - containerRect.left;
                    const posY = y - containerRect.top;
                    
                    // Random rotation direction (left or right)
                    const rotationDirection = Math.random() > 0.5 ? 1 : -1;
                    
                    // Apply styles
                    Object.assign(particle.style, {
                        left: `${posX}px`,
                        top: `${posY}px`,
                        width: `${size}px`,
                        height: `${size}px`,
                        '--tx': `${horizontalMovement * rotationDirection}`, // Horizontal movement
                        'animation': `particle-fall ${duration}s ease-out forwards`,
                        'transform-origin': 'center center',
                        'will-change': 'transform, opacity',
                        'position': 'absolute',
                        'z-index': '1001'
                    });
                    
                    // Add to container
                    particlesContainer.appendChild(particle);
                    
                    // Remove after animation
                    setTimeout(() => {
                        if (particle.parentNode === particlesContainer) {
                            particle.style.opacity = '0';
                            setTimeout(() => {
                                if (particle.parentNode === particlesContainer) {
                                    particlesContainer.removeChild(particle);
                                }
                            }, 500);
                        }
                    }, (duration * 1000) - 500);
                }
            }
            
            // Start mining animation
            function startMiningAnimation() {
                console.log('Starting mining animation...');
                // Get the pickaxe element
                const pickaxe = document.querySelector('.mining-pickaxe');
                const rock = document.querySelector('.mining-rock');
                
                if (!pickaxe || !rock) {
                    console.error('Could not find pickaxe or rock element');
                    return;
                }
                
                // Get the position where particles should appear (bottom of pickaxe)
                const getParticleOrigin = () => {
                    const pickaxeRect = pickaxe.getBoundingClientRect();
                    const rockRect = rock.getBoundingClientRect();
                    
                    // Calculate the impact point (where pickaxe hits the rock)
                    const x = pickaxeRect.left + pickaxeRect.width / 2;
                    const y = rockRect.top + 10;
                    
                    console.log('Particle origin:', { x, y });
                    
                    return { x, y };
                };
                
                // Create particles on each animation iteration
                const onAnimationIteration = () => {
                    console.log('Pickaxe animation iteration');
                    const { x, y } = getParticleOrigin();
                    createParticles(x, y, 3 + Math.floor(Math.random() * 4)); // 3-6 particles per hit
                };
                
                // Add the event listener
                pickaxe.addEventListener('animationiteration', onAnimationIteration);
                
                // Also trigger on animation start
                pickaxe.addEventListener('animationstart', () => {
                    console.log('Pickaxe animation started');
                    const { x, y } = getParticleOrigin();
                    createParticles(x, y, 3 + Math.floor(Math.random() * 4));
                });
                
                // Create some initial particles
                const { x, y } = getParticleOrigin();
                createParticles(x, y, 5);
                
                // Return cleanup function
                return () => {
                    pickaxe.removeEventListener('animationiteration', onAnimationIteration);
                };
            }
            
            // Event listeners
            startMiningBtn.addEventListener('click', function() {
                if (this.disabled) return;
                
                // Show confirmation dialog
                Swal.fire({
                    title: 'Start Mining?',
                    text: 'Start a 24-hour mining session to earn up to 35 LUCK?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#00ffff',
                    cancelButtonColor: '#ff6b6b',
                    confirmButtonText: 'Yes, start mining!',
                    cancelButtonText: 'Cancel',
                    background: '#1a1a2e',
                    color: '#fff',
                    backdrop: `
                        rgba(0,0,0,0.8)
                        url("/images/nyan-cat.gif")
                        left top
                        no-repeat
                    `
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show loading state
                        this.classList.add('loading');
                        
                        // Simulate API call delay
                        setTimeout(() => {
                            this.classList.remove('loading');
                            startMining();
                        }, 1500);
                    }
                });
            });
            
            // Check for existing mining session on page load
            checkMiningSession();
            
            // Set up periodic status check (every 30 seconds)
            setInterval(checkMiningSession, 30000);
            
            // Single click handler for the mining button
            startMiningBtn.addEventListener('click', handleMiningButtonClick);
            
            // Set up periodic status check (every 30 seconds)
            setInterval(checkMiningSession, 30000);
        });
        
        // ===== Mining Button Click Handler =====
        async function handleMiningButtonClick() {
            const startMiningBtn = document.getElementById('startMiningBtn');
            if (startMiningBtn.disabled) return;
            
            // Check if we're claiming rewards or starting a new session
            const isClaiming = startMiningBtn.querySelector('.button-text').textContent === 'Claim Rewards';
            
            if (isClaiming) {
                await claimMiningRewards();
            } else {
                // Show confirmation dialog for starting mining
                Swal.fire({
                    title: 'Start Mining?',
                    text: 'Start a 24-hour mining session to earn up to 35 LUCK?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#00ffff',
                    cancelButtonColor: '#ff6b6b',
                    confirmButtonText: 'Yes, start mining!',
                    cancelButtonText: 'Cancel',
                    background: '#1a1a2e',
                    color: '#fff'
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        // Show loading state
                        startMiningBtn.classList.add('loading');
                        
                        try {
                            await startMining();
                        } catch (error) {
                            console.error('Mining error:', error);
                            Swal.fire({
                                title: 'Error',
                                text: error.message || 'Failed to start mining',
                                icon: 'error',
                                confirmButtonColor: '#00ffff',
                                background: '#1a1a2e',
                                color: '#fff'
                            });
                        } finally {
                            startMiningBtn.classList.remove('loading');
                        }
                    }
                });
            }
        }
        
        // ===== Start Mining Animation =====
        function startMiningAnimation() {
            console.log('Starting mining animation...');
            const pickaxe = document.querySelector('.mining-pickaxe');
            const rock = document.querySelector('.mining-rock');
            
            if (!pickaxe || !rock) {
                console.error('Mining elements not found');
                return;
            }
            
            // Add animation classes
            pickaxe.classList.add('mining');
            rock.classList.add('mining');
            
            // Remove animation classes after animation completes
            setTimeout(() => {
                pickaxe.classList.remove('mining');
                rock.classList.remove('mining');
            }, 2000);
        }
        
        // ===== Start Mining =====
        async function startMining() {
            try {
                const response = await fetch('php/mining.php?action=start', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.error || 'Failed to start mining');
                }
                
                // Update UI
                const startMiningBtn = document.getElementById('startMiningBtn');
                startMiningBtn.disabled = true;
                startMiningBtn.querySelector('.button-text').textContent = 'Mining in Progress';
                document.querySelector('.mining-card').classList.add('mining');
                
                // Start animations
                startMiningAnimation();
                
                // Show success message
                Swal.fire({
                    title: 'Mining Started!',
                    text: 'Your mining session has begun. Come back later to claim your rewards!',
                    icon: 'success',
                    confirmButtonColor: '#00ffff',
                    background: '#1a1a2e',
                    color: '#fff'
                });
                
                // Update status immediately
                await checkMiningSession();
                
            } catch (error) {
                console.error('Start mining error:', error);
                throw error;
            }
        }
        
        // ===== Check Mining Status =====
        async function checkMiningSession() {
            console.log('checkMiningSession: Checking mining status...');
            const startMiningBtn = document.getElementById('startMiningBtn');
            
            try {
                console.log('checkMiningSession: Sending status request...');
                const response = await fetch('php/mining.php?action=status', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'Cache-Control': 'no-cache, no-store, must-revalidate',
                        'Pragma': 'no-cache',
                        'Expires': '0'
                    },
                    cache: 'no-store'
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                console.log('checkMiningSession: Received status response:', data);
                
                if (!data.success) {
                    console.error('checkMiningSession: Failed to get mining status:', data.error);
                    return;
                }
                
                await updateMiningUI(data);
                
            } catch (error) {
                console.error('checkMiningSession: Error checking mining status:', error);
                // Log button state on error
                if (startMiningBtn) {
                    console.error('Button state on error:', {
                        text: startMiningBtn.querySelector('.button-text')?.textContent,
                        disabled: startMiningBtn.disabled,
                        classList: Array.from(startMiningBtn.classList)
                    });
                }
            }
        }
        
        // ===== Update Mining UI =====
        function updateMiningUI(data) {
            console.log('updateMiningUI: Updating UI with data:', data);
            
            const miningCard = document.querySelector('.mining-card');
            const startMiningBtn = document.getElementById('startMiningBtn');
            const miningProgress = document.getElementById('miningProgress');
            const progressText = document.getElementById('progressText');
            const miningTime = document.getElementById('miningTime');
            const minedAmount = document.getElementById('minedAmount');
            
            // Log current button state before update
            if (startMiningBtn) {
                console.log('updateMiningUI: Current button state before update:', {
                    text: startMiningBtn.querySelector('.button-text')?.textContent,
                    disabled: startMiningBtn.disabled,
                    classList: Array.from(startMiningBtn.classList)
                });
            }
            
            if (data.status === 'inactive') {
                console.log('updateMiningUI: No active mining session');
                // No active or completed sessions
                if (miningCard) miningCard.classList.remove('mining');
                if (startMiningBtn) {
                    startMiningBtn.disabled = false;
                    const buttonText = startMiningBtn.querySelector('.button-text');
                    if (buttonText) buttonText.textContent = 'Start Mining';
                }
                if (miningProgress) miningProgress.style.width = '0%';
                if (progressText) progressText.textContent = '0% Complete';
                if (miningTime) miningTime.textContent = '24:00:00';
                if (minedAmount) minedAmount.textContent = '0.00 LUCK';
                return;
            }
            
            if (data.status === 'mining') {
                console.log('updateMiningUI: Active mining session');
                // Active mining session
                if (miningCard) miningCard.classList.add('mining');
                if (startMiningBtn) {
                    startMiningBtn.disabled = true;
                    const buttonText = startMiningBtn.querySelector('.button-text');
                    if (buttonText) buttonText.textContent = 'Mining in Progress';
                }
                
                // Update progress bar
                if (miningProgress) miningProgress.style.width = `${data.progress}%`;
                if (progressText) progressText.textContent = `${Math.round(data.progress)}% Complete`;
                
                // Update timer
                const hours = Math.floor(data.timeLeft / 3600);
                const minutes = Math.floor((data.timeLeft % 3600) / 60);
                const seconds = data.timeLeft % 60;
                miningTime.textContent = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                
                // Update mined amount
                minedAmount.textContent = `${data.minedAmount.toFixed(2)} LUCK`;
                
            } else if (data.status === 'completed') {
                console.log('updateMiningUI: Mining completed, ready to claim');
                // Mining completed, ready to claim
                if (miningCard) miningCard.classList.remove('mining');
                if (startMiningBtn) {
                    startMiningBtn.disabled = false;
                    const buttonText = startMiningBtn.querySelector('.button-text');
                    if (buttonText) buttonText.textContent = 'Claim Rewards';
                }
                
                // Update progress bar
                miningProgress.style.width = '100%';
                progressText.textContent = '100% Complete';
                miningTime.textContent = '00:00:00';
                
                // Update mined amount
                minedAmount.textContent = `${data.minedAmount.toFixed(2)} LUCK`;
                
                // Show completion message if not already shown
                if (!window.miningCompleteShown) {
                    window.miningCompleteShown = true;
                    Swal.fire({
                        title: 'Mining Complete!',
                        text: 'You have successfully mined 35 LUCK! Click the button to claim your rewards.',
                        icon: 'success',
                        confirmButtonColor: '#00ffff',
                        background: '#1a1a2e',
                        color: '#fff'
                    });
                }
            }
        }
        
        // ===== Claim Mining Rewards =====
        async function claimMiningRewards() {
            console.log('claimMiningRewards: Starting claim process...');
            const startMiningBtn = document.getElementById('startMiningBtn');
            if (!startMiningBtn) {
                console.error('claimMiningRewards: Start mining button not found');
                return;
            }
            
            console.log('claimMiningRewards: Disabling button and showing loading state');
            startMiningBtn.disabled = true;
            startMiningBtn.classList.add('loading');
            
            // Log button state
            console.log('claimMiningRewards: Button state after update:', {
                text: startMiningBtn.querySelector('.button-text')?.textContent,
                disabled: startMiningBtn.disabled,
                classList: Array.from(startMiningBtn.classList)
            });
            
            try {
                const response = await fetch('php/mining.php?action=claim', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'Cache-Control': 'no-cache, no-store, must-revalidate',
                        'Pragma': 'no-cache',
                        'Expires': '0'
                    },
                    cache: 'no-store'
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                console.log('claimMiningRewards: Claim response:', data);
                
                if (!data.success) {
                    throw new Error(data.error || 'Failed to claim rewards');
                }
                
                // Show success message
                await Swal.fire({
                    title: 'Rewards Claimed!',
                    text: `You have successfully claimed ${data.rewardAmount} LUCK!`,
                    icon: 'success',
                    confirmButtonColor: '#00ffff',
                    background: '#1a1a2e',
                    color: '#fff',
                    allowOutsideClick: false,
                    allowEscapeKey: false
                });
                
                // Manually reset the UI to show Start Mining
                const miningCard = document.querySelector('.mining-card');
                const miningProgress = document.getElementById('miningProgress');
                const progressText = document.getElementById('progressText');
                const miningTime = document.getElementById('miningTime');
                const minedAmount = document.getElementById('minedAmount');
                
                if (miningCard) miningCard.classList.remove('mining');
                if (startMiningBtn) {
                    startMiningBtn.disabled = false;
                    const buttonText = startMiningBtn.querySelector('.button-text');
                    if (buttonText) buttonText.textContent = 'Start Mining';
                }
                if (miningProgress) miningProgress.style.width = '0%';
                if (progressText) progressText.textContent = '0% Complete';
                if (miningTime) miningTime.textContent = '24:00:00';
                if (minedAmount) minedAmount.textContent = '0.00 LUCK';
                
                // Update balance display if the function exists
                if (typeof updateBalanceDisplay === 'function') {
                    updateBalanceDisplay(data.newBalance);
                }
                
                // Force a fresh status check to ensure everything is in sync
                await checkMiningSession();
                
            } catch (error) {
                console.error('Claim reward error:', error);
                
                await Swal.fire({
                    title: 'Error',
                    text: error.message || 'Failed to claim rewards',
                    icon: 'error',
                    confirmButtonColor: '#00ffff',
                    background: '#1a1a2e',
                    color: '#fff',
                    allowOutsideClick: false,
                    allowEscapeKey: false
                });
                
                // Re-enable button on error
                if (startMiningBtn) {
                    startMiningBtn.disabled = false;
                    startMiningBtn.classList.remove('loading');
                }
                
                // Refresh status to ensure UI is in sync with server
                await checkMiningSession();
                
            } finally {
                if (startMiningBtn) {
                    startMiningBtn.classList.remove('loading');
                }
            }
        }
        
        // ===== Mobile Menu Toggle =====
        const burgerMenu = document.getElementById('burgerMenu');
        const mobileNav = document.getElementById('mobileNav');
        const mobileThemeToggle = document.getElementById('mobileThemeToggle');
        const themeToggleButton = document.getElementById('themeToggleButton');
        
        // Toggle mobile menu
        burgerMenu.addEventListener('click', () => {
            burgerMenu.classList.toggle('active');
            mobileNav.classList.toggle('active');
            document.body.style.overflow = mobileNav.classList.contains('active') ? 'hidden' : '';
            
            // Animate burger icon to X
            const spans = burgerMenu.querySelectorAll('span');
            if (burgerMenu.classList.contains('active')) {
                spans[0].style.transform = 'rotate(45deg)';
                spans[1].style.opacity = '0';
                spans[2].style.transform = 'rotate(-45deg)';
                spans[0].style.marginBottom = '-0.25rem';
                spans[2].style.marginTop = '-0.25rem';
            } else {
                spans[0].style.transform = 'rotate(0)';
                spans[1].style.opacity = '1';
                spans[2].style.transform = 'rotate(0)';
                spans[0].style.marginBottom = '0';
                spans[2].style.marginTop = '0';
            }
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', (e) => {
            if (mobileNav.classList.contains('active') && 
                !mobileNav.contains(e.target) && 
                !burgerMenu.contains(e.target)) {
                burgerMenu.click();
            }
        });
        
        // Close menu when clicking on a nav item
        document.querySelectorAll('.mobile-nav-item').forEach(item => {
            item.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    burgerMenu.click();
                }
            });
        });
        
        // Sync mobile theme toggle with desktop
        mobileThemeToggle.addEventListener('click', () => {
            themeToggleButton.click();
        });
        
        // Copy wallet address
        document.querySelectorAll('.copy-wallet').forEach(button => {
            button.addEventListener('click', (e) => {
                e.stopPropagation();
                const walletAddress = button.getAttribute('data-wallet');
                navigator.clipboard.writeText(walletAddress).then(() => {
                    const icon = button.querySelector('i');
                    const originalIcon = icon.className;
                    icon.className = 'fas fa-check';
                    setTimeout(() => {
                        icon.className = originalIcon;
                    }, 2000);
                });
            });
        });
        
        // ===== Notification System =====
        const modal = document.getElementById('notificationModal');
        const modalMessage = document.getElementById('notificationMessage');
        const closeButton = document.querySelector('.close-button');
        const confirmButton = document.getElementById('notificationConfirm');
        
        function showNotification(message, isError = false) {
            modalMessage.innerHTML = message;
            modalMessage.className = isError ? 'error' : 'success';
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
        
        function hideNotification() {
            modal.classList.remove('show');
            document.body.style.overflow = 'auto';
        }
        
        closeButton.onclick = hideNotification;
        confirmButton.onclick = hideNotification;
        
        window.onclick = function(event) {
            if (event.target === modal) {
                hideNotification();
            }
        };
        
        // Show success/error message from session if exists
        <?php 
        if (isset($_SESSION['success_message'])) {
            echo "showNotification('" . addslashes($_SESSION['success_message']) . "', false);";
            unset($_SESSION['success_message']);
        } elseif (!empty($error)) {
            echo "showNotification('" . addslashes($error) . "', true);";
        }
        ?>

        // ===== Theme Management =====
        function setupTheme() {
            const themeToggleButton = document.getElementById('themeToggleButton');
            const currentTheme = localStorage.getItem('theme') || 'dark';
            
            // Apply saved theme
            if (currentTheme === 'light') {
                document.body.classList.add('light-mode');
                themeToggleButton.innerHTML = '<i class="fas fa-moon"></i>';
                themeToggleButton.setAttribute('aria-label', 'Switch to dark mode');
            }
            
            // Toggle theme
            themeToggleButton.addEventListener('click', function() {
                document.body.classList.toggle('light-mode');
                
                if (document.body.classList.contains('light-mode')) {
                    localStorage.setItem('theme', 'light');
                    themeToggleButton.innerHTML = '<i class="fas fa-moon"></i>';
                    themeToggleButton.setAttribute('aria-label', 'Switch to dark mode');
                } else {
                    localStorage.setItem('theme', 'dark');
                    themeToggleButton.innerHTML = '<i class="fas fa-sun"></i>';
                    themeToggleButton.setAttribute('aria-label', 'Switch to light mode');
                }
            });
        }
        
        // ===== User Profile Dropdown =====
        function setupUserProfile() {
            const userProfileBubble = document.getElementById('userProfileBubble');
            const dropdownContent = userProfileBubble ? userProfileBubble.querySelector('.dropdown-content') : null;
            
            if (userProfileBubble && dropdownContent) {
                userProfileBubble.addEventListener('click', function(event) {
                    event.stopPropagation();
                    dropdownContent.classList.toggle('show');
                });

                document.addEventListener('click', function(event) {
                    if (!userProfileBubble.contains(event.target)) {
                        dropdownContent.classList.remove('show');
                    }
                });
            }
            
            // Show full wallet address
            const walletText = document.getElementById('walletText');
            if (walletText) {
                // No truncation - show full address
                const address = walletText.textContent.trim();
                walletText.textContent = address;
            }
        }
        
        // ===== Initialize Application =====
        function initializeApp() {
            // Application initialization code will go here
        }

        // ===== Initialize Everything =====
        document.addEventListener('DOMContentLoaded', function() {
            setupTheme();
            setupUserProfile();
            initializeApp();
        });
    </script>
</body>
</html>