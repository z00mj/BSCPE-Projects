<?php
// Disable output buffering and error display
ini_set('output_buffering', 'off');
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Set headers for JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'login'; // Using the login database as per the schema

// Function to send JSON response
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Function to log errors
function logError($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, __DIR__ . '/leaderboard_errors.log');
}

try {
    // Connect to database
    $db = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    // Check connection
    if ($db->connect_error) {
        throw new Exception("Database connection failed: " . $db->connect_error);
    }
    
    // Set charset to utf8mb4
    if (!$db->set_charset("utf8mb4")) {
        throw new Exception("Error loading character set utf8mb4: " . $db->error);
    }

    // Get top 10 users by luck_balance
    $query = "SELECT 
                user_id,
                COALESCE(username, CONCAT('User-', user_id)) as username,
                wallet_address,
                luck_balance
              FROM luck_wallet_users
              WHERE luck_balance > 0
              ORDER BY luck_balance DESC
              LIMIT 10";
    
    $result = $db->query($query);
    
    if ($result === false) {
        throw new Exception("Query failed: " . $db->error);
    }
    
    $leaderboard = [];
    $rank = 1;
    
    while ($row = $result->fetch_assoc()) {
        $leaderboard[] = [
            'rank' => $rank++,
            'username' => $row['username'],
            'wallet_address' => $row['wallet_address'],
            'balance' => number_format($row['luck_balance'], 2, '.', ',')
        ];
    }
    
    // Close database connection
    $db->close();
    
    // Return success response with leaderboard data
    sendJsonResponse([
        'success' => true,
        'data' => $leaderboard
    ]);
    
} catch (Exception $e) {
    // Log the error
    logError("Leaderboard Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    
    // Return error response
    sendJsonResponse([
        'success' => false,
        'error' => 'Failed to load leaderboard. Please try again later.',
        'debug' => (ENVIRONMENT === 'development') ? $e->getMessage() : null
    ], 500);
}
?>
