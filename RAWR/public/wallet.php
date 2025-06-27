<?php
require_once __DIR__ . '/../backend/inc/init.php';
userOnly();

$userId = $_SESSION['user_id'];
$db = Database::getInstance();
$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

// Get conversion rate from config
$conversionRate = defined('CONVERSION_RATE') ? CONVERSION_RATE : 20;
$error = '';
$success = '';
$conversionAmount = 0;
$conversionDirection = 'rawr_to_tickets';
$depositAmount = 0;
$withdrawalAmount = 0;

// Create transactions table if it doesn't exist
$db->executeQuery("
    CREATE TABLE IF NOT EXISTS transactions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        type ENUM('deposit','withdrawal') NOT NULL,
        amount DECIMAL(18,8) NOT NULL,
        status ENUM('pending','completed','failed') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// Handle RAWR to Tickets conversion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['convert'])) {
    $conversionAmount = floatval($_POST['amount']);
    $conversionDirection = $_POST['direction'];
    
    if ($conversionAmount <= 0) {
        $error = "Please enter a valid amount";
    } else {
        try {
            $db->beginTransaction();
            
            if ($conversionDirection === 'rawr_to_tickets') {
                if ($user['rawr_balance'] < $conversionAmount) {
                    $error = "Insufficient RAWR balance";
                } else {
                    $tickets = $conversionAmount / $conversionRate;
                    $db->executeQuery(
                        "UPDATE users SET rawr_balance = rawr_balance - ?, ticket_balance = ticket_balance + ? WHERE id = ?",
                        [$conversionAmount, $tickets, $userId]
                    );
                    
                    $db->insert('conversion_logs', [
                        'user_id' => $userId,
                        'rawr_amount' => $conversionAmount,
                        'tickets_received' => $tickets
                    ]);
                    
                    $success = "Converted " . number_format($conversionAmount, 2) . " RAWR to " . number_format($tickets) . " Tickets";
                }
            } else {
                if ($user['ticket_balance'] < $conversionAmount) {
                    $error = "Insufficient Tickets balance";
                } else {
                    $rawrAmount = $conversionAmount * $conversionRate;
                    $db->executeQuery(
                        "UPDATE users SET ticket_balance = ticket_balance - ?, rawr_balance = rawr_balance + ? WHERE id = ?",
                        [$conversionAmount, $rawrAmount, $userId]
                    );
                    
                    $db->insert('conversion_logs', [
                        'user_id' => $userId,
                        'tickets_spent' => $conversionAmount,
                        'rawr_received' => $rawrAmount
                    ]);
                    
                    $success = "Converted " . number_format($conversionAmount) . " Tickets to " . number_format($rawrAmount, 2) . " RAWR";
                }
            }
            
            $db->commit();
            
            // Refresh user data
            $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Conversion failed: " . $e->getMessage();
        }
    }
}

// Handle deposit simulation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deposit'])) {
    $depositAmount = floatval($_POST['deposit_amount']);
    
    if ($depositAmount <= 0) {
        $error = "Please enter a valid deposit amount";
    } else {
        try {
            $db->executeQuery(
                "UPDATE users SET rawr_balance = rawr_balance + ? WHERE id = ?",
                [$depositAmount, $userId]
            );
            
            // Record transaction
            $db->insert('transactions', [
                'user_id' => $userId,
                'type' => 'deposit',
                'amount' => $depositAmount,
                'status' => 'completed'
            ]);
            
            $success = "Successfully deposited " . number_format($depositAmount, 2) . " RAWR";
            
            // Refresh user data
            $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
        } catch (Exception $e) {
            $error = "Deposit failed: " . $e->getMessage();
        }
    }
}

// Handle withdrawal simulation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw'])) {
    $withdrawalAmount = floatval($_POST['withdrawal_amount']);
    
    if ($withdrawalAmount <= 0) {
        $error = "Please enter a valid withdrawal amount";
    } elseif ($user['rawr_balance'] < $withdrawalAmount) {
        $error = "Insufficient RAWR balance";
    } else {
        try {
            $db->executeQuery(
                "UPDATE users SET rawr_balance = rawr_balance - ? WHERE id = ?",
                [$withdrawalAmount, $userId]
            );
            
            // Record transaction
            $db->insert('transactions', [
                'user_id' => $userId,
                'type' => 'withdrawal',
                'amount' => $withdrawalAmount,
                'status' => 'completed'
            ]);
            
            $success = "Withdrawal request for " . number_format($withdrawalAmount, 2) . " RAWR submitted";
            
            // Refresh user data
            $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
        } catch (Exception $e) {
            $error = "Withdrawal failed: " . $e->getMessage();
        }
    }
}

// Fetch transaction history
$transactions = $db->fetchAll("
    (SELECT id, user_id, 'deposit' AS type, amount, NULL AS converted_amount, created_at 
     FROM transactions 
     WHERE user_id = ? AND type = 'deposit'
    )
    UNION
    (SELECT id, user_id, 'withdrawal' AS type, amount, NULL, created_at 
     FROM transactions 
     WHERE user_id = ? AND type = 'withdrawal'
    )
    UNION
    (SELECT id, user_id, 'conversion_rawr_to_tickets' AS type, rawr_amount AS amount, tickets_received AS converted_amount, converted_at AS created_at 
     FROM conversion_logs 
     WHERE user_id = ? AND rawr_amount IS NOT NULL
    )
    UNION
    (SELECT id, user_id, 'conversion_tickets_to_rawr' AS type, tickets_spent AS amount, rawr_received AS converted_amount, converted_at AS created_at 
     FROM conversion_logs 
     WHERE user_id = ? AND tickets_spent IS NOT NULL
    )
    ORDER BY created_at DESC 
    LIMIT 20
", [$userId, $userId, $userId, $userId]);

if (!$transactions) $transactions = [];

$pageTitle = "Wallet - RAWR Casino";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/RAWR/public/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/web3@1.5.2/dist/web3.min.js"></script>
    <style>
        .wallet-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1.5rem; /* MODIFIED: Added vertical padding for spacing */
        }
        
        .wallet-section {
            margin-bottom: 2.5rem;
        }
        
        .wallet-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .wallet-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
        }
        
        .action-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .action-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            transition: var(--transition);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3), var(--glow);
        }
        
        /* ADDED: Style for MetaMask card to ensure spacing */
        #metamask-card {
            margin-bottom: 1.5rem;
        }
        
        .action-title {
            font-size: 1.3rem;
            color: var(--primary-light);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .conversion-rate {
            background: rgba(255, 215, 0, 0.1);
            border-radius: 8px;
            padding: 0.5rem 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            text-align: center;
        }
        
        .form-group {
            margin-bottom: 1.2rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        
        .form-input {
            width: 100%;
            padding: 0.8rem 1rem;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 215, 0, 0.2);
            border-radius: 8px;
            color: var(--text-light);
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .form-input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.2);
        }
        
        .form-select {
            width: 100%;
            padding: 0.8rem 1rem;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 215, 0, 0.2);
            border-radius: 8px;
            color: var(--text-light);
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23FFD700' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1em;
        }
        
        .form-select:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.2);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #1a1a1a;
            border: none;
            border-radius: 8px;
            padding: 0.8rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            display: block;
            text-align: center;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(255, 215, 0, 0.4);
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-outline:hover {
            background: rgba(255, 215, 0, 0.1);
        }
        
        .transaction-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .transaction-table th {
            text-align: left;
            padding: 1rem;
            background: rgba(0, 0, 0, 0.3);
            color: var(--primary);
            font-weight: 500;
            border-bottom: 1px solid rgba(255, 215, 0, 0.1);
        }
        
        .transaction-table td {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .transaction-table tr:hover {
            background: rgba(255, 215, 0, 0.05);
        }
        
        .transaction-type {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .type-deposit {
            background: rgba(40, 167, 69, 0.15);
            color: #28a745;
        }
        
        .type-withdrawal {
            background: rgba(220, 53, 69, 0.15);
            color: #dc3545;
        }
        
        .type-conversion {
            background: rgba(23, 162, 184, 0.15);
            color: #17a2b8;
        }
        
        .transaction-amount {
            font-weight: 600;
            color: var(--primary);
        }
        
        .alert {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert-success {
            background: rgba(40, 167, 69, 0.15);
            border: 1px solid rgba(40, 167, 69, 0.3);
            color: #28a745;
        }
        
        .alert-error {
            background: rgba(220, 53, 69, 0.15);
            border: 1px solid rgba(220, 53, 69, 0.3);
            color: #dc3545;
        }
        
        .metamask-connect {
            background: linear-gradient(135deg, #f6851b, #e2761b);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.8rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .metamask-connect:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(246, 133, 27, 0.4);
        }
        
        .metamask-connected {
            background: rgba(40, 167, 69, 0.15);
            border: 1px solid rgba(40, 167, 69, 0.3);
            color: #28a745;
            padding: 0.8rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .metamask-address {
            font-size: 0.85rem;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 150px;
        }
        
        @media (max-width: 768px) {
            .transaction-table {
                display: block;
                overflow-x: auto;
            }
            
            .balance-cards, .action-cards {
                grid-template-columns: 1fr;
            }
            
            .metamask-address {
                max-width: 100px;
            }
        }

        /* Wallet Page Header Styles (similar to leaderboard.php) */
        .page-header {
            padding: 100px 1rem 40px;
            text-align: center;
            background: rgba(0, 0, 0, 0.3);
            position: relative;
        }
        .page-header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(to right, #FFD700, #FF6B35);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 20px rgba(255, 215, 0, 0.2);
        }
        .page-header p {
            font-size: 1rem;
            max-width: 600px;
            margin: 0 auto;
            color: #ccc;
        }
        @media (min-width: 768px) {
            .page-header {
                padding: 120px 2rem 60px;
            }
            .page-header h1 {
                font-size: 3.5rem;
            }
            .page-header p {
                font-size: 1.1rem;
            }
        }
        
        /* MODIFIED: Added styles for scrollable transaction history */
        .transaction-history-scrollable {
            max-height: 330px; /* Approx height for 5 rows */
            overflow-y: auto;
            padding-right: 8px; /* Avoid content hiding behind scrollbar */
        }
        
        /* Custom scrollbar for a better look */
        .transaction-history-scrollable::-webkit-scrollbar {
            width: 8px;
        }
        .transaction-history-scrollable::-webkit-scrollbar-track {
            background: rgba(0,0,0,0.2);
            border-radius: 10px;
        }
        .transaction-history-scrollable::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
        }
        .transaction-history-scrollable::-webkit-scrollbar-thumb:hover {
            background: var(--secondary);
        }
    </style>
</head>
<body>
    <nav class="top-nav">
        <div class="logo">
            <div class="coin-logo"></div>
            <span>RAWR</span>
        </div>
        
        <div class="nav-actions">
            <div class="wallet-balance">
                <div class="balance-item">
                    <i class="fas fa-coins balance-icon"></i>
                    <span class="balance-label">RAWR:</span>
                    <span class="balance-value"><?= number_format($user['rawr_balance'], 2) ?></span>
                </div>
                <div class="balance-item">
                    <i class="fas fa-ticket-alt balance-icon"></i>
                    <span class="balance-label">Tickets:</span>
                    <span class="balance-value"><?= number_format($user['ticket_balance'], 2) ?></span>
                </div>
            </div>
            <button class="menu-toggle" id="menuToggle">
                <i class="fas fa-bars"></i>
                <i class="fas fa-xmark"></i>
            </button>
        </div>
    </nav>
    
    <aside class="sidebar" id="sidebar">
        <a href="dashboard.php" class="sidebar-item">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        <a href="mining.php" class="sidebar-item">
            <i class="fas fa-digging"></i>
            <span>Mining</span>
        </a>
        <a href="games.php" class="sidebar-item">
            <i class="fas fa-dice"></i>
            <span>Casino</span>
        </a>
        <a href="wallet.php" class="sidebar-item active">
            <i class="fas fa-wallet"></i>
            <span>Wallet</span>
        </a>
        <a href="leaderboard.php" class="sidebar-item">
            <i class="fas fa-trophy"></i>
            <span>Leaderboard</span>
        </a>
        <a href="daily.php" class="sidebar-item">
            <i class="fas fa-gift"></i>
            <span>Daily Rewards</span>
        </a>
        <a href="profile.php" class="sidebar-item">
            <i class="fas fa-user"></i>
            <span>Profile</span>
        </a>
        <a href="logout.php" class="sidebar-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </aside>
    
    <section class="page-header">
        <h1>My Wallet</h1>
        <p>Manage your RAWR tokens and tickets. Deposit, withdraw, convert, and connect your blockchain wallet.</p>
    </section>
    
    <main class="wallet-container">
        <div class="wallet-section">
            <div class="wallet-header">
                <div class="wallet-icon">
                    <i class="fas fa-wallet"></i>
                </div>
                <h1>Your Wallet</h1>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= $error ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= $success ?>
                </div>
            <?php endif; ?>
            
            <div class="action-card" id="metamask-card"> <h2 class="action-title">
                    <i class="fab fa-ethereum"></i>
                    Blockchain Wallet
                </h2>
                
                <div id="metamaskStatus">
                    <p>Connect your MetaMask wallet to transfer RAWR tokens</p>
                    <button class="metamask-connect" id="connectMetamask">
                        <i class="fab fa-metamask"></i>
                        Connect MetaMask
                    </button>
                </div>
                
                <div id="metamaskInfo" style="display: none;">
                    <div class="form-group">
                        <label class="form-label">Connected Wallet</label>
                        <div class="metamask-connected">
                            <i class="fab fa-ethereum"></i>
                            <span class="metamask-address" id="walletAddress"></span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Network</label>
                        <div class="form-input" id="networkInfo">Localhost:8545</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">RAWR Balance</label>
                        <div class="form-input" id="blockchainBalance">0 RAWR</div>
                    </div>

                    <button class="btn-primary btn-outline" id="disconnectMetamask" style="margin-top: 1rem;">
                        Disconnect
                    </button>
                    </div>
            </div>
            
            <div class="action-cards">
                <div class="action-card">
                    <h2 class="action-title">
                        <i class="fas fa-exchange-alt"></i>
                        Convert Currency
                    </h2>
                    
                    <div class="conversion-rate">
                        <i class="fas fa-info-circle"></i>
                        Current rate: <?= $conversionRate ?> RAWR = 1 Ticket
                    </div>
                    
                    <form method="post">
                        <div class="form-group">
                            <label class="form-label">Conversion Direction</label>
                            <select name="direction" class="form-select" id="conversionDirection">
                                <option value="rawr_to_tickets" <?= $conversionDirection === 'rawr_to_tickets' ? 'selected' : '' ?>>RAWR to Tickets</option>
                                <option value="tickets_to_rawr" <?= $conversionDirection === 'tickets_to_rawr' ? 'selected' : '' ?>>Tickets to RAWR</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" id="amountLabel">Amount to Convert (RAWR)</label>
                            <input 
                                type="number" 
                                name="amount" 
                                class="form-input" 
                                step="any" 
                                min="0.01" 
                                value="<?= $conversionAmount ?>" 
                                required
                                placeholder="Enter amount"
                            >
                        </div>
                        
                        <div class="form-group" id="conversionResult">
                            <label class="form-label">You Will Receive</label>
                            <div class="form-input" style="background: rgba(0,0,0,0.2);">
                                <span id="resultAmount">0</span> 
                                <span id="resultCurrency">Tickets</span>
                            </div>
                        </div>
                        
                        <button type="submit" name="convert" class="btn-primary">
                            Convert Now
                        </button>
                    </form>
                </div>
                
                <div class="action-card">
                    <h2 class="action-title">
                        <i class="fas fa-arrow-down"></i>
                        Deposit RAWR
                    </h2>
                    
                    <form method="post">
                        <div class="form-group">
                            <label class="form-label">Deposit Amount (RAWR)</label>
                            <input 
                                type="number" 
                                name="deposit_amount" 
                                class="form-input" 
                                step="any" 
                                min="0.01" 
                                value="<?= $depositAmount ?>" 
                                required
                                placeholder="Enter amount"
                            >
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Wallet Address</label>
                            <input 
                                type="text" 
                                class="form-input" 
                                id="depositWalletAddress"
                                value="<?= htmlspecialchars($user['wallet_address'] ?? 'Not Connected') ?>" 
                                readonly
                            >
                        </div>
                        
                        <button type="submit" name="deposit" class="btn-primary">
                            Simulate Deposit
                        </button>
                    </form>
                </div>
                
                <div class="action-card">
                    <h2 class="action-title">
                        <i class="fas fa-arrow-up"></i>
                        Withdraw RAWR
                    </h2>
                    
                    <form method="post">
                        <div class="form-group">
                            <label class="form-label">Withdrawal Amount (RAWR)</label>
                            <input 
                                type="number" 
                                name="withdrawal_amount" 
                                class="form-input" 
                                step="any" 
                                min="0.01" 
                                value="<?= $withdrawalAmount ?>" 
                                required
                                placeholder="Enter amount"
                            >
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Wallet Address</label>
                            <input 
                                type="text" 
                                class="form-input" 
                                id="withdrawalWalletAddress"
                                value="<?= htmlspecialchars($user['wallet_address'] ?? 'Not Connected') ?>" 
                                readonly
                            >
                        </div>
                        
                        <button type="submit" name="withdraw" class="btn-primary btn-outline">
                            Request Withdrawal
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="wallet-section">
            <h2 class="section-title">
                <i class="fas fa-history"></i>
                Transaction History
            </h2>
            
            <?php if (empty($transactions)): ?>
                <div class="alert">
                    <i class="fas fa-info-circle"></i>
                    No transactions found
                </div>
            <?php else: ?>
                <?php
                    // MODIFIED: Determine if the container should be scrollable
                    $transactionCount = count($transactions);
                    $scrollableClass = ($transactionCount >= 6) ? 'transaction-history-scrollable' : '';
                ?>
                <div class="<?= $scrollableClass ?>" style="overflow-x: auto;">
                    <table class="transaction-table">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $tx): ?>
                                <tr>
                                    <td><?= date('M d, Y H:i', strtotime($tx['created_at'])) ?></td>
                                    <td>
                                        <?php 
                                            $typeClass = '';
                                            $typeLabel = '';
                                            
                                            switch ($tx['type']) {
                                                case 'deposit':
                                                    $typeClass = 'type-deposit';
                                                    $typeLabel = 'Deposit';
                                                    break;
                                                case 'withdrawal':
                                                    $typeClass = 'type-withdrawal';
                                                    $typeLabel = 'Withdrawal';
                                                    break;
                                                case 'conversion_rawr_to_tickets':
                                                    $typeClass = 'type-conversion';
                                                    $typeLabel = 'Conversion';
                                                    break;
                                                case 'conversion_tickets_to_rawr':
                                                    $typeClass = 'type-conversion';
                                                    $typeLabel = 'Conversion';
                                                    break;
                                                default:
                                                    $typeClass = '';
                                                    $typeLabel = $tx['type'];
                                            }
                                        ?>
                                        <span class="transaction-type <?= $typeClass ?>">
                                            <?= $typeLabel ?>
                                        </span>
                                    </td>
                                    <td class="transaction-amount">
                                        <?php 
                                            if ($tx['type'] === 'deposit') {
                                                echo '+' . number_format($tx['amount'], 2) . ' RAWR';
                                            } elseif ($tx['type'] === 'withdrawal') {
                                                echo '-' . number_format($tx['amount'], 2) . ' RAWR';
                                            } elseif ($tx['type'] === 'conversion_rawr_to_tickets') {
                                                echo '-' . number_format($tx['amount'], 2) . ' RAWR';
                                            } elseif ($tx['type'] === 'conversion_tickets_to_rawr') {
                                                echo '-' . number_format($tx['amount']) . ' Tickets';
                                            }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                            if ($tx['type'] === 'conversion_rawr_to_tickets') {
                                                echo 'Converted to ' . number_format($tx['converted_amount']) . ' Tickets';
                                            } elseif ($tx['type'] === 'conversion_tickets_to_rawr') {
                                                echo 'Converted to ' . number_format($tx['converted_amount'], 2) . ' RAWR';
                                            } else {
                                                echo 'Completed';
                                            }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <footer>
        <div class="footer-content">
            <div class="footer-column">
                <h3>RAWR Casino</h3>
                <p>The ultimate play-to-earn experience in the jungle. Mine, play, and earn your way to the top!</p>
                <div class="social-links">
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-discord"></i></a>
                    <a href="#"><i class="fab fa-telegram"></i></a>
                    <a href="#"><i class="fab fa-reddit"></i></a>
                </div>
            </div>
            
            <div class="footer-column">
                <h3>Quick Links</h3>
                <ul class="footer-links">
                    <li><a href="#">Home</a></li>
                    <li><a href="#">Mining</a></li>
                    <li><a href="#">Casino</a></li>
                    <li><a href="#">Leaderboard</a></li>
                    <li><a href="#">Shop</a></li>
                </ul>
            </div>
            
            <div class="footer-column">
                <h3>Resources</h3>
                <ul class="footer-links">
                    <li><a href="#">FAQs</a></li>
                    <li><a href="#">Tutorials</a></li>
                    <li><a href="#">Whitepaper</a></li>
                    <li><a href="#">Tokenomics</a></li>
                    <li><a href="#">Support</a></li>
                </ul>
            </div>
            
            <div class="footer-column">
                <h3>Legal</h3>
                <ul class="footer-links">
                    <li><a href="#">Terms of Service</a></li>
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Disclaimer</a></li>
                    <li><a href="#">AML Policy</a></li>
                </ul>
            </div>
        </div>
        
        <div class="copyright">
            &copy; <?= date('Y') ?> RAWR Casino. All rights reserved. The jungle is yours to conquer!
        </div>
    </footer>
    
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // --- DOM Elements ---
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const conversionDirection = document.getElementById('conversionDirection');
        const amountInput = document.querySelector('input[name="amount"]');
        const resultAmount = document.getElementById('resultAmount');
        const resultCurrency = document.getElementById('resultCurrency');
        const amountLabel = document.getElementById('amountLabel');
        
        // MetaMask Elements
        const connectMetamaskBtn = document.getElementById('connectMetamask');
        const disconnectMetamaskBtn = document.getElementById('disconnectMetamask');
        const metamaskStatus = document.getElementById('metamaskStatus');
        const metamaskInfo = document.getElementById('metamaskInfo');
        const walletAddress = document.getElementById('walletAddress');
        const networkInfo = document.getElementById('networkInfo');
        const blockchainBalance = document.getElementById('blockchainBalance');
        const depositAddressInput = document.getElementById('depositWalletAddress');
        const withdrawalAddressInput = document.getElementById('withdrawalWalletAddress');
        
        const defaultWalletText = 'Not Connected';

        // --- Sidebar Logic ---
        menuToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('active');
            menuToggle.classList.toggle('active');
        });
        
        document.addEventListener('click', (e) => {
            if (sidebar.classList.contains('active') && !sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                sidebar.classList.remove('active');
                menuToggle.classList.remove('active');
            }
        });

        // --- Conversion Calculator Logic ---
        function updateConversion() {
            if (!amountInput) return; // Guard clause if element doesn't exist
            const amount = parseFloat(amountInput.value) || 0;
            const direction = conversionDirection.value;
            const conversionRate = <?= $conversionRate ?>;
            
            if (direction === 'rawr_to_tickets') {
                const tickets = amount / conversionRate;
                resultAmount.textContent = tickets.toFixed(2);
                resultCurrency.textContent = 'Tickets';
                amountLabel.textContent = 'Amount to Convert (RAWR)';
            } else {
                const rawr = amount * conversionRate;
                resultAmount.textContent = rawr.toFixed(2);
                resultCurrency.textContent = 'RAWR';
                amountLabel.textContent = 'Amount to Convert (Tickets)';
            }
        }
        
        if (conversionDirection && amountInput) {
            conversionDirection.addEventListener('change', updateConversion);
            amountInput.addEventListener('input', updateConversion);
            updateConversion(); // Initial calculation
        }

        // --- MetaMask Integration Logic ---
        function getNetworkName(chainId) {
            switch (chainId) {
                case '0x1': return 'Ethereum Mainnet';
                case '0x3': return 'Ropsten Testnet';
                case '0x4': return 'Rinkeby Testnet';
                case '0x5': return 'Goerli Testnet';
                case '0x2a': return 'Kovan Testnet';
                case '0x539': return 'Localhost';
                default: return `Unknown Network (${chainId})`;
            }
        }
        
        // Sets UI to disconnected state
        function setDisconnectedState() {
            metamaskStatus.style.display = 'block';
            metamaskInfo.style.display = 'none';
            if (depositAddressInput) depositAddressInput.value = defaultWalletText;
            if (withdrawalAddressInput) withdrawalAddressInput.value = defaultWalletText;
            walletAddress.textContent = '';
        }

        // Sets UI to connected state
        async function updateConnectionDetails(account) {
            walletAddress.textContent = account;
            if (depositAddressInput) depositAddressInput.value = account;
            if (withdrawalAddressInput) withdrawalAddressInput.value = account;

            metamaskStatus.style.display = 'none';
            metamaskInfo.style.display = 'block';

            try {
                const chainId = await window.ethereum.request({ method: 'eth_chainId' });
                networkInfo.textContent = getNetworkName(chainId);
                
                // Mock balance for demo
                const balance = Math.random() * 1000;
                blockchainBalance.textContent = balance.toFixed(2) + ' RAWR';

                // Listen for changes
                setupEthereumListeners();
            } catch (error) {
                console.error("Could not get wallet details:", error);
                setDisconnectedState();
            }
        }

        // Connect wallet function
        async function connectMetaMask() {
            if (typeof window.ethereum === 'undefined') {
                alert('Please install MetaMask to use this feature!');
                return;
            }
            
            try {
                const accounts = await window.ethereum.request({ method: 'eth_requestAccounts' });
                if (accounts.length > 0) {
                    await updateConnectionDetails(accounts[0]);
                }
            } catch (error) {
                console.error('MetaMask connection error:', error);
                alert('Failed to connect MetaMask. Please try again.');
            }
        }

        // Disconnect wallet function
        function disconnectMetaMask() {
            // Note: True disconnection is managed by the user in MetaMask.
            // This function just resets the UI on the site.
            console.log('Wallet disconnected from UI.');
            setDisconnectedState();
        }

        // Setup listeners for account or network changes
        function setupEthereumListeners() {
            window.ethereum.removeAllListeners(); // Prevent duplicate listeners
            
            window.ethereum.on('accountsChanged', (accounts) => {
                if (accounts.length === 0) {
                    // User locked or disconnected all accounts
                    disconnectMetaMask();
                } else {
                    updateConnectionDetails(accounts[0]); // Handle account switch
                }
            });
            
            window.ethereum.on('chainChanged', (chainId) => {
                networkInfo.textContent = getNetworkName(chainId);
            });
        }
        
        // Check for existing connection on page load
        async function checkForExistingConnection() {
            if (typeof window.ethereum !== 'undefined') {
                try {
                    const accounts = await window.ethereum.request({ method: 'eth_accounts' });
                    if (accounts.length > 0) {
                        console.log('MetaMask already connected.');
                        await updateConnectionDetails(accounts[0]);
                    } else {
                        console.log('MetaMask is installed but not connected.');
                        setDisconnectedState();
                    }
                } catch (err) {
                    console.error('Error checking for existing MetaMask connection:', err);
                    setDisconnectedState();
                }
            } else {
                 setDisconnectedState();
            }
        }
        
        // --- Initialize ---
        if(connectMetamaskBtn) {
            connectMetamaskBtn.addEventListener('click', connectMetaMask);
        }
        if(disconnectMetamaskBtn) {
            disconnectMetamaskBtn.addEventListener('click', disconnectMetaMask);
        }
        checkForExistingConnection(); // Check connection on page load

    });
    </script>
    </body>
</html>