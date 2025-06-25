<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once __DIR__ . '/../db_connect.php';

echo "<h1>Marketplace Database Check</h1>";

try {
    // Check if luck_wallet_users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'luck_wallet_users'");
    $tableExists = $stmt->fetch();
    
    if (!$tableExists) {
        throw new Exception("The 'luck_wallet_users' table does not exist in the database.");
    }
    
    // Check luck_wallet_users table structure
    echo "<h2>luck_wallet_users Table Structure</h2>";
    $stmt = $pdo->query("DESCRIBE luck_wallet_users");
    $columns = $stmt->fetchAll();
    
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($column['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check if the uploads directory exists and is writable
    $uploadDir = __DIR__ . '/uploads/profile_pictures';
    echo "<h2>Upload Directory Check</h2>";
    if (!file_exists($uploadDir)) {
        echo "<p>Upload directory does not exist. Attempting to create it...</p>";
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception("Failed to create upload directory at: " . $uploadDir);
        }
        echo "<p>Successfully created upload directory at: " . htmlspecialchars($uploadDir) . "</p>";
    } else {
        echo "<p>Upload directory exists at: " . htmlspecialchars($uploadDir) . "</p>";
    }
    
    if (!is_writable($uploadDir)) {
        throw new Exception("Upload directory is not writable. Please check permissions for: " . $uploadDir);
    } else {
        echo "<p>Upload directory is writable.</p>";
    }
    
    // Check session and current user
    session_start();
    if (isset($_SESSION['user_id'])) {
        echo "<h2>Current User (ID: " . htmlspecialchars($_SESSION['user_id']) . ")</h2>";
        
        $stmt = $pdo->prepare("SELECT * FROM luck_wallet_users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user) {
            echo "<p>User found in database:</p>";
            echo "<pre>" . htmlspecialchars(print_r($user, true)) . "</pre>";
            
            // Check if profile_image column exists and its type
            echo "<h3>Profile Image Info</h3>";
            $stmt = $pdo->query("SHOW COLUMNS FROM luck_wallet_users LIKE 'profile_image'");
            $profileImageColumn = $stmt->fetch();
            
            if ($profileImageColumn) {
                echo "<p>Profile image column type: " . htmlspecialchars($profileImageColumn['Type']) . "</p>";
                
                // Check if the current user has a profile image
                $stmt = $pdo->prepare("SELECT LENGTH(profile_image) as img_size FROM luck_wallet_users WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $imgSize = $stmt->fetch();
                
                if ($imgSize && $imgSize['img_size'] > 0) {
                    echo "<p>User has a profile image (size: " . $imgSize['img_size'] . " bytes)</p>";
                } else {
                    echo "<p>User does not have a profile image set.</p>";
                }
            } else {
                echo "<p>Warning: 'profile_image' column not found in luck_wallet_users table.</p>";
            }
        } else {
            echo "<p>User not found in the database.</p>";
        }
    } else {
        echo "<p>No user is currently logged in.</p>";
    }
    
} catch (PDOException $e) {
    echo "<div style='color:red;'><h2>Database Error:</h2><p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Error Code: " . $e->getCode() . "</p>";
    echo "<p>In file: " . htmlspecialchars($e->getFile()) . " on line " . $e->getLine() . "</p></div>";
} catch (Exception $e) {
    echo "<div style='color:red;'><h2>Error:</h2><p>" . htmlspecialchars($e->getMessage()) . "</p></div>";
}

// Show PHP info for debugging
if (isset($_GET['phpinfo'])) {
    phpinfo();
} else {
    echo "<p><a href='?phpinfo=1'>Show PHP Info</a></p>";
}
?>
