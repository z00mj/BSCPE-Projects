<?php
/**
 * Database connection handler
 */

// Include debug helper if it exists
$debugHelperPath = __DIR__ . DIRECTORY_SEPARATOR . 'debug_helper.php';
if (file_exists($debugHelperPath)) {
    require_once $debugHelperPath;
} else {
    // Define a simple debug_log function if debug_helper.php doesn't exist
    if (!function_exists('debug_log')) {
        function debug_log($message) {
            // Do nothing if debug helper is not available
        }
    }
}

function getDBConnection() {
    static $conn = null;
    
    // Return existing connection if available
    if ($conn !== null) {
        return $conn;
    }
    
    // Database configuration
    $db_host = 'localhost';
    $db_user = 'root';     // Default XAMPP username
    $db_pass = '';         // Default XAMPP password is empty
    $db_name = 'login';    // Database name is 'login' in your setup
    
    debug_log("Attempting to connect to database: $db_name on $db_host");
    
    try {
        // Create connection
        $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
        
        // Check connection
        if ($conn->connect_error) {
            $errorMsg = "Connection failed: " . $conn->connect_error;
            debug_log($errorMsg);
            throw new Exception($errorMsg);
        }
        
        debug_log("Successfully connected to database: $db_name");
        
        // Set charset to ensure proper encoding
        if (!$conn->set_charset("utf8mb4")) {
            $errorMsg = "Error loading character set utf8mb4: " . $conn->error;
            debug_log($errorMsg);
            throw new Exception($errorMsg);
        }
        
        return $conn;
        
    } catch (Exception $e) {
        // Log the error with debug helper
        debug_log('Database connection error: ' . $e->getMessage());
        
        // Don't expose database errors to users in production
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            die(json_encode(['error' => 'Database connection error: ' . $e->getMessage()]));
        } else {
            die(json_encode(['error' => 'Could not connect to the database. Please try again later.']));
        }
    }
}

// Test connection if accessed directly
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    try {
        $conn = getDBConnection();
        echo "Database connection successful!";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
}
?>
