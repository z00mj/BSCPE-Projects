
<?php
// Start session
session_start();

// Include necessary files
require_once __DIR__ . '/php/session_handler.php';

// Initialize variables with default values
$displayWallet = 'Connect Wallet';
$balance = 0;
$luckBalance = 0;
$conversionRate = 63.0; // Default conversion rate (1 LUCK = 63 PHP)
$error = '';
$success = '';
$isLoggedIn = false;

// Get current user data if logged in
if (isset($_SESSION['user_id'])) {
    $currentUser = getCurrentUser();
    if ($currentUser) {
        $isLoggedIn = true;
        $displayWallet = $currentUser['wallet_address'] ?? 'Connect Wallet';
        $balance = $currentUser['balance'] ?? 0;
        $luckBalance = $currentUser['luck_balance'] ?? 0;
    } else {
        // If session exists but user data can't be loaded, clear the session
        session_unset();
        session_destroy();
        header('Location: index.html');
        exit();
    }
} else {
    // If not logged in, redirect to index.html
    header('Location: index.html');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['convert'])) {
    $fromCurrency = $_POST['from_currency'] ?? '';
    $toCurrency = $_POST['to_currency'] ?? '';
    $amount = floatval($_POST['amount'] ?? 0);
    
    // Validate input
    if ($amount <= 0) {
        $error = 'Please enter a valid amount to convert.';
    } elseif ($fromCurrency === $toCurrency) {
        $error = 'Cannot convert to the same currency.';
    } else {
        global $pdo;
        
        try {
            $pdo->beginTransaction();
            
            // Get fresh user data with locks to prevent race conditions
            $stmt = $pdo->prepare("
                SELECT lwu.*, u.balance as ecasino_balance 
                FROM luck_wallet_users lwu
                LEFT JOIN users u ON lwu.email = u.email
                WHERE lwu.user_id = ?
                FOR UPDATE
            ");
            $stmt->execute([$currentUser['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                throw new Exception('User not found');
            }
            
            // Check if user has sufficient balance
            if ($fromCurrency === 'LUCK' && $user['luck_balance'] < $amount) {
                throw new Exception('Insufficient LUCK balance');
            } elseif ($fromCurrency === 'PHP' && $user['ecasino_balance'] < $amount) {
                throw new Exception('Insufficient PHP balance');
            }
            
            // Calculate conversion
            $rate = ($fromCurrency === 'LUCK') ? $conversionRate : (1 / $conversionRate);
            $convertedAmount = $amount * $rate;
            $fee = $convertedAmount * 0.005; // 0.5% fee
            $finalAmount = $convertedAmount - $fee;
            
            // Update balances
            if ($fromCurrency === 'LUCK') {
                // Convert from LUCK to PHP
                $newLuckBalance = $user['luck_balance'] - $amount;
                $newPhpBalance = $user['ecasino_balance'] + $finalAmount;
                
                // Update LUCK balance
                $stmt = $pdo->prepare("UPDATE luck_wallet_users SET luck_balance = ? WHERE user_id = ?");
                $stmt->execute([$newLuckBalance, $user['user_id']]);
                
                // Update PHP balance
                $stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE email = ?");
                $stmt->execute([$newPhpBalance, $user['email']]);
                
                $transactionType = 'LUCK_TO_PHP';
            } else {
                // Convert from PHP to LUCK
                $newPhpBalance = $user['ecasino_balance'] - $amount;
                $newLuckBalance = $user['luck_balance'] + $finalAmount;
                
                // Update PHP balance
                $stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE email = ?");
                $stmt->execute([$newPhpBalance, $user['email']]);
                
                // Update LUCK balance
                $stmt = $pdo->prepare("UPDATE luck_wallet_users SET luck_balance = ? WHERE user_id = ?");
                $stmt->execute([$newLuckBalance, $user['user_id']]);
                
                $transactionType = 'PHP_TO_LUCK';
            }
            
            // Record transaction
            $stmt = $pdo->prepare("
                INSERT INTO luck_wallet_transactions 
                (sender_user_id, receiver_user_id, amount, transaction_type, notes) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            // Store the LUCK amount for consistency in transaction history
            // When converting from PHP to LUCK: Store the final LUCK amount as positive
            // When converting from LUCK to PHP: Store the LUCK amount as negative
            $transactionAmount = ($fromCurrency === 'LUCK') ? -$amount : $finalAmount;
            
            // For PHP to LUCK conversion, we need to ensure we're storing the LUCK amount
            if ($fromCurrency === 'PHP' && $toCurrency === 'LUCK') {
                $transactionAmount = $finalAmount; // This is the LUCK amount after conversion
            }
            
            $notes = sprintf('Converted %s %s to %s %s (Fee: %s %s)', 
                number_format($amount, 2),
                $fromCurrency,
                number_format($finalAmount, 2),
                $toCurrency,
                number_format($fee, 2),
                $toCurrency
            );
            
            $stmt->execute([
                $user['user_id'],
                $user['user_id'], // Same user for both sender and receiver in conversion
                $transactionAmount, // Store LUCK amount (negative when converting from LUCK, positive when converting to LUCK)
                'convert_' . strtolower($transactionType),
                $notes
            ]);
            
            $pdo->commit();
            
            // Update session data
            $_SESSION['user_balance'] = $newPhpBalance;
            $_SESSION['luck_balance'] = $newLuckBalance;
            
            // Store success message in session for display after redirect
            $_SESSION['success_message'] = sprintf('Successfully converted %s %s to %s %s (Fee: %s %s)', 
                number_format($amount, 2),
                $fromCurrency,
                number_format($finalAmount, 2),
                $toCurrency,
                number_format($fee, 2),
                $toCurrency
            );
            
            // Redirect to clear POST data and prevent form resubmission
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
            
            // Refresh user data
            $currentUser = getCurrentUser();
            $balance = $currentUser['ecasino_balance'] ?? 0;
            $luckBalance = $currentUser['luck_balance'] ?? 0;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Conversion error: ' . $e->getMessage());
            $error = 'Conversion failed: ' . $e->getMessage();
        }
    }
}
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
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background: radial-gradient(circle at center, #003333 0%, #000000 100%);
            color: #ffffff;
            transition: background 0.5s ease, color 0.5s ease;
        }

        /* ===== Light Mode ===== */
        body.light-mode {
            background: radial-gradient(circle at center, #99C2CC 0%, #547A9A 100%);
            color: #333333;
        }
        
        /* ===== Navbar Layout ===== */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 2rem;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: 4rem;
            border-bottom: 1px solid rgba(0, 255, 255, 0.3);
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
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
            height: 36px;
            padding: 0 16px;
            margin: 0 4px;
            border-radius: 20px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(0, 255, 255, 0.3);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            box-sizing: border-box;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        
        .balance-display::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: 0.5s;
        }
        
        .balance-display:hover::before {
            left: 100%;
        }
        
        .balance-display:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 255, 255, 0.3);
        }

        .balance-display i {
            margin-right: 8px;
            font-size: 1.1em;
        }

        .balance-display span {
            font-weight: 600;
            font-size: 0.95em;
        }

        .luck-balance {
            background: linear-gradient(145deg, rgba(60, 10, 30, 0.8), rgba(120, 20, 60, 0.8)) !important;
            border-color: rgba(255, 105, 180, 0.4) !important;
        }

        .ecasino-balance {
            background: linear-gradient(145deg, rgba(60, 50, 10, 0.8), rgba(120, 100, 20, 0.8)) !important;
            border-color: rgba(255, 215, 0, 0.4) !important;
        }

        /* Light mode balance styles */
        body.light-mode .balance-display {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(0, 0, 0, 0.1);
        }
        
        body.light-mode .balance-display:hover {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        /* Nav sections */
        .nav-left, .nav-center, .nav-right {
            display: flex;
            align-items: center;
            height: 100%;
            width: 33.33%;
            transition: all 0.3s ease;
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
            background: rgba(0, 255, 255, 0.1);
            color: #00ffff;
            padding: 8px 15px;
            border-radius: 20px;
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 255, 255, 0.3);
            text-shadow: 0 0 8px rgba(0, 255, 255, 0.5);
        }
        
        .dashboard-btn:hover {
            background: rgba(0, 255, 255, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 0 15px rgba(0, 255, 255, 0.5);
            text-shadow: 0 0 10px rgba(0, 255, 255, 1);
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
        
        .account-id:hover {
            background: rgba(0, 255, 255, 0.1);
            box-shadow: 0 0 10px rgba(0, 255, 255, 0.5);
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
        
        .dropdown-content a:hover {
            background-color: rgba(0, 255, 255, 0.1);
            color: white;
        }
        
        .theme-toggle-button {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(0, 255, 255, 0.3);
            color: #00ffff;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .theme-toggle-button:hover {
            background: rgba(0, 255, 255, 0.1);
            transform: rotate(30deg);
            box-shadow: 0 0 10px rgba(0, 255, 255, 0.5);
        }

        .header-logo-container {
            /* Removed flex properties as it's now centered using margin auto */
            /* Removed absolute positioning to allow it to flow to the left */
            position: static; /* Changed to static to remove absolute positioning */
            left: auto; /* Reset left */
            transform: none; /* Reset transform */
            height: auto;
            font-size: 2rem;
            color: #00ffff;
            text-shadow: 0 0 10px #00ffff,
                         0 0 20px rgba(0, 255, 255, 0.7);
            font-weight: bold;
            letter-spacing: 2px;
            transition: text-shadow 0.3s ease-in-out, color 0.3s ease-in-out;
            margin-left: 546px; /* Add margin to separate from the back button */
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

        footer {
            margin-top: auto;
            padding: 10px 30px;
            background-color: rgba(0, 0, 0, 0.5);
            color: #ffffff;
            text-align: center; /* Center the text */
            display: flex;
            justify-content: center; /* Center content horizontally */
            align-items: center; /* Center content vertically */
            height: 4rem;
            transition: all 0.4s ease-in-out;
        }

        /* Light mode for footer */
        body.light-mode footer {
            background-color: rgba(176, 224, 230, 0.6); /* Lighter background for footer */
            color: #333333; /* Darker text (close to black) */
            border-top: 0.5px solid #4682B4; /* Darker border */
            box-shadow: 0 -5px 25px rgba(70, 130, 180, 0.3),
                        0 0 30px rgba(70, 130, 180, 0.2);
        }

        footer p {
            margin: 0;
            font-size: 0.9em;
            opacity: 0.8;
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
            <div class="logo">🎰 LuckyTime</div>
        </div>
        
        <div class="nav-center">
            <div class="balance-display luck-balance" style="color: #ff69b4; border-color: rgba(255, 105, 180, 0.4);">
                <img src="assets/images/logo.png" alt="LUCK" style="width: 32px; height: 32px; margin-right: 12px; object-fit: contain;">
                <span><?php echo number_format($luckBalance, 2); ?> LUCK</span>
            </div>
            <div class="balance-display ecasino-balance" style="color: #FFD700; border-color: rgba(255, 215, 0, 0.4);">
                <i class="fas fa-coins"></i>
                <span>₱<?php echo number_format($currentUser['ecasino_balance'] ?? 0, 2); ?></span>
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
        <div class="convert-container">
            <h2>Convert Tokens</h2>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <div class="balance-info">
                <p>Your Balance:</p>
                <p>PHP: <strong>₱<?php echo number_format($currentUser['ecasino_balance'] ?? 0, 2); ?></strong></p>
                <p>LUCK: <strong><?php echo number_format($currentUser['luck_balance'] ?? 0, 4); ?> $LUCK</strong></p>
            </div>
            
            <form method="POST" id="conversionForm" action="">
                <div class="currency-selection-row">
                    <div class="form-group">
                        <label for="fromCurrency">From:</label>
                        <select id="fromCurrency" name="from_currency" required>
                            <option value="LUCK">$LUCK (Luck)</option>
                            <option value="PHP">Philippine Peso (₱)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="toCurrency">To:</label>
                        <select id="toCurrency" name="to_currency" required>
                            <option value="PHP">Philippine Peso (₱)</option>
                            <option value="LUCK">$LUCK (Luck Token)</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="amountInput">Amount:</label>
                    <input type="number" id="amountInput" name="amount" placeholder="Enter amount" min="0.01" step="0.01" required>
                    <small id="maxAmount" class="max-amount"><span></span></small>
                </div>
                <div class="conversion-details">
                    <p>Current Rate: <strong id="estimatedRate">1 $LUCK = ₱<?php echo number_format($conversionRate, 2); ?></strong></p>
                    <p>You Will Get: <strong id="youWillGet">0.00</strong></p>
                    <p>Conversion Fee (0.5%): <strong id="conversionFees">0.00</strong></p>
                </div>
                <button type="submit" name="convert" class="execute-button" id="executeConvertButton" disabled>Convert</button>
            </form>
        </div>
    </main>

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

    <script>
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
            
            // Format wallet address if it exists
            const walletText = document.getElementById('walletText');
            if (walletText) {
                const address = walletText.textContent.trim();
                if (address.length > 10) {
                    walletText.textContent = `${address.substring(0, 6)}...${address.substring(address.length - 4)}`;
                }
            }
        }
        
        // ===== Conversion Calculator =====
        function setupConversionCalculator() {
            const fromCurrency = document.getElementById('fromCurrency');
            const toCurrency = document.getElementById('toCurrency');
            const amountInput = document.getElementById('amountInput');
            const estimatedRate = document.getElementById('estimatedRate');
            const youWillGet = document.getElementById('youWillGet');
            const conversionFees = document.getElementById('conversionFees');
            const executeConvertButton = document.getElementById('executeConvertButton');

            // Hardcoded conversion rates
            const rates = {
                'LUCK_PHP': 63.0, // 1 $LUCK = 63 Philippine Pesos
                'PHP_LUCK': 1 / 63.0  // 1 Philippine Peso = 1/63 $LUCK
            };

            const feeRate = 0.005; // 0.5% fee

            function updateConversionDetails() {
                const from = fromCurrency.value;
                const to = toCurrency.value;
                const amount = parseFloat(amountInput.value);

                if (isNaN(amount) || amount <= 0 || from === to) {
                    estimatedRate.textContent = 'N/A';
                    youWillGet.textContent = '0.00 ' + (to === 'LUCK' ? '$LUCK' : '₱');
                    conversionFees.textContent = '0.00';
                    executeConvertButton.disabled = true;
                    return;
                }


                const rateKey = `${from}_${to}`;
                const rate = rates[rateKey];

                if (rate) {
                    const receivedAmount = amount * rate;
                    const fees = receivedAmount * feeRate;
                    const netReceivedAmount = receivedAmount - fees;

                    // Determine currency symbol for display
                    let toSymbol = to === 'LUCK' ? '$LUCK' : '₱';

                    estimatedRate.textContent = `1 ${from === 'LUCK' ? '$LUCK' : '₱'} = ${rate.toFixed(2)} ${toSymbol}`;
                    youWillGet.textContent = `${netReceivedAmount.toFixed(2)} ${toSymbol}`;
                    conversionFees.textContent = `${fees.toFixed(2)} ${toSymbol}`;
                    executeConvertButton.disabled = false;
                } else {
                    estimatedRate.textContent = 'No rate available for this pair.';
                    youWillGet.textContent = '0.00 ' + (to === 'LUCK' ? '$LUCK' : '₱');
                    conversionFees.textContent = '0.00';
                    executeConvertButton.disabled = true;
                }
            }

            // Event Listeners
            fromCurrency.addEventListener('change', updateConversionDetails);
            toCurrency.addEventListener('change', updateConversionDetails);
            amountInput.addEventListener('input', updateConversionDetails);
            
            // Initialize
            updateConversionDetails();
        }

        // ===== Initialize Everything =====
        document.addEventListener('DOMContentLoaded', function() {
            setupTheme();
            setupUserProfile();
            setupConversionCalculator();
        });
    </script>
</body>
</html>