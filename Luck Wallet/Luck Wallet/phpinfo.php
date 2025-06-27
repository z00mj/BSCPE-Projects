<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get the current URL
$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
?>
<!DOCTYPE html>
<html>
<head>
    <title>PHP Info - Luck Wallet</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; }
        .info-box { 
            background: #f5f5f5; 
            border: 1px solid #ddd; 
            border-radius: 5px; 
            padding: 15px; 
            margin-bottom: 20px;
        }
        .warning { 
            background: #fff3cd; 
            border-color: #ffeeba; 
            color: #856404;
        }
        pre { 
            background: white; 
            padding: 10px; 
            border: 1px solid #ddd; 
            border-radius: 3px; 
            overflow-x: auto;
        }
        .section {
            margin-top: 20px;
            padding: 10px;
            border: 1px solid #eee;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <h1>PHP Information</h1>
    
    <div class="info-box">
        <h2>Server Information</h2>
        <p><strong>Current URL:</strong> <?php echo htmlspecialchars($currentUrl); ?></p>
        <p><strong>Server Software:</strong> <?php echo htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'N/A'); ?></p>
        <p><strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
        <p><strong>Server Name:</strong> <?php echo htmlspecialchars($_SERVER['SERVER_NAME']); ?></p>
        <p><strong>Document Root:</strong> <?php echo htmlspecialchars($_SERVER['DOCUMENT_ROOT']); ?></p>
    </div>
    
    <div class="info-box">
        <h2>Session Information</h2>
        <?php if (session_status() === PHP_SESSION_ACTIVE): ?>
            <p>Session is active</p>
            <pre>$_SESSION = <?php echo htmlspecialchars(print_r($_SESSION, true)); ?></pre>
        <?php else: ?>
            <p>No active session</p>
        <?php endif; ?>
    </div>
    
    <div class="info-box">
        <h2>PHP Configuration</h2>
        <h3>Important Settings</h3>
        <ul>
            <li>display_errors: <?php echo ini_get('display_errors') ? 'On' : 'Off'; ?></li>
            <li>error_reporting: <?php echo ini_get('error_reporting'); ?></li>
            <li>session.save_path: <?php echo ini_get('session.save_path'); ?></li>
            <li>session.cookie_domain: <?php echo ini_get('session.cookie_domain'); ?></li>
            <li>session.cookie_httponly: <?php echo ini_get('session.cookie_httponly') ? 'On' : 'Off'; ?></li>
            <li>session.cookie_secure: <?php echo ini_get('session.cookie_secure') ? 'On' : 'Off'; ?></li>
        </ul>
    </div>
    
    <div class="info-box">
        <h2>Database Connection Test</h2>
        <?php
        try {
            require_once 'db_connect.php';
            if (isset($pdo) && $pdo instanceof PDO) {
                $stmt = $pdo->query("SELECT DATABASE() as db, USER() as user, VERSION() as version");
                $dbInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                echo "<p class='success'>Database connection successful!</p>";
                echo "<pre>";
                echo "Database: " . htmlspecialchars($dbInfo['db']) . "\n";
                echo "User: " . htmlspecialchars($dbInfo['user']) . "\n";
                echo "MySQL Version: " . htmlspecialchars($dbInfo['version']) . "\n";
                echo "</pre>";
            } else {
                throw new Exception("Database connection not properly initialized");
            }
        } catch (Exception $e) {
            echo "<p class='error'>Database connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        ?>
    </div>
    
    <div class="info-box">
        <h2>PHP Info</h2>
        <p><a href="?phpinfo=1">Show Full PHP Info</a></p>
        
        <?php
        if (isset($_GET['phpinfo'])) {
            phpinfo();
        }
        ?>
    </div>
    
    <div class="info-box">
        <h2>Next Steps</h2>
        <ul>
            <li><a href="test_auth.php">Test Authentication</a> - Check if login works</li>
            <li><a href="view_nfts.php">View All NFTs</a> - View all NFTs in the database</li>
            <li><a href="dashboard.php">Go to Dashboard</a> - Return to the main dashboard</li>
        </ul>
    </div>
</body>
</html>
