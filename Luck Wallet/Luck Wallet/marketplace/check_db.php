<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once __DIR__ . '/../db_connect.php';

try {
    echo "<h1>Database Connection Successful</h1>";
    
    // Check if users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    $tableExists = $stmt->fetch();
    
    if (!$tableExists) {
        throw new Exception("The 'users' table does not exist in the database.");
    }
    
    // Check users table structure
    echo "<h2>Users Table Structure</h2>";
    $stmt = $pdo->query("DESCRIBE users");
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
    
    // Check if the current user exists in the database
    session_start();
    if (isset($_SESSION['user_id'])) {
        echo "<h2>Current User (ID: " . htmlspecialchars($_SESSION['user_id']) . ")</h2>";
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user) {
            echo "<p>User found in database:</p>";
            echo "<pre>" . print_r($user, true) . "</pre>";
            
            // Check if profile_image column exists
            echo "<h3>Profile Image Path:</h3>";
            if (isset($user['profile_image'])) {
                echo "<p>Current profile image: " . htmlspecialchars($user['profile_image']) . "</p>";
                
                // Check if the file exists
                if (!empty($user['profile_image'])) {
                    $file_path = __DIR__ . '/' . $user['profile_image'];
                    if (file_exists($file_path)) {
                        echo "<p>File exists at: " . htmlspecialchars($file_path) . "</p>";
                        echo "<p>File size: " . filesize($file_path) . " bytes</p>";
                        echo "<p>MIME type: " . mime_content_type($file_path) . "</p>";
                        
                        // Display the image
                        $image_url = $user['profile_image'] . '?t=' . time();
                        echo "<img src='" . htmlspecialchars($image_url) . "' alt='Profile Image' style='max-width: 300px; margin-top: 20px;'>";
                    } else {
                        echo "<p style='color: red;'>File does not exist at: " . htmlspecialchars($file_path) . "</p>";
                    }
                } else {
                    echo "<p>No profile image set.</p>";
                }
            } else {
                echo "<p style='color: orange;'>profile_image column not found in the users table.</p>";
            }
        } else {
            echo "<p style='color: red;'>User not found in the database.</p>";
        }
    } else {
        echo "<p>Not logged in. Please log in to check user data.</p>";
    }
    
} catch (Exception $e) {
    echo "<h1>Database Error</h1>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    
    // Show PDO connection error if available
    if (isset($pdo) && $pdo->errorInfo()) {
        $errorInfo = $pdo->errorInfo();
        echo "<h3>PDO Error Info:</h3>";
        echo "<pre>" . print_r($errorInfo, true) . "</pre>";
    }
}
?>
