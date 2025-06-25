<?php
// Enable error reporting for debugging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../error_log.txt');
error_reporting(E_ALL);

// Start output buffering
ob_start();

// Set content type to JSON
header('Content-Type: application/json');

// Include required files
require_once __DIR__ . DIRECTORY_SEPARATOR . 'session_handler.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'db_connect.php';

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

// Function to clean and send JSON response
function sendJsonResponse($data, $statusCode = 200) {
    // Clear any previous output
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

try {
    debug_log("Starting check_daily_bonus.php");
    
    // Check if user is logged in
    $currentUser = getCurrentUser();
    if (!$currentUser) {
        debug_log("User not authenticated");
        sendJsonResponse([
            'success' => false, 
            'message' => 'Not authenticated',
            'canClaim' => false
        ], 401);
    }
    
    $userId = $currentUser['user_id'];
    debug_log("Checking daily bonus status for user ID: " . $userId);
    
    // Get database connection
    $db = getDBConnection();
    
    // Check if user has already claimed today using the same date comparison as claim_daily_bonus.php
    $query = "SELECT 
                CASE 
                    WHEN last_daily_claim IS NULL THEN 1
                    WHEN DATE(last_daily_claim) < CURDATE() THEN 1
                    ELSE 0 
                END as can_claim,
                luck_balance,
                last_daily_claim
              FROM luck_wallet_users 
              WHERE user_id = ?";
    
    $stmt = $db->prepare($query);
    debug_log("Checking if user can claim daily bonus");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        debug_log("User not found in database");
        sendJsonResponse([
            'success' => false,
            'message' => 'User not found',
            'canClaim' => false
        ]);
    }
    
    $row = $result->fetch_assoc();
    $canClaim = (bool)$row['can_claim'];
    $lastClaim = $row['last_daily_claim'];
    
    debug_log("Can claim: " . ($canClaim ? 'yes' : 'no') . ", Last claim: " . ($lastClaim ?: 'never'));
    
    $response = [
        'success' => true,
        'canClaim' => $canClaim,
        'lastClaim' => $lastClaim
    ];
    
    debug_log("Sending response: " . json_encode($response));
    sendJsonResponse($response);
    
} catch (Exception $e) {
    $errorMsg = 'Error in check_daily_bonus.php: ' . $e->getMessage();
    debug_log($errorMsg);
    
    // In case of error, default to not allowing claim
    sendJsonResponse([
        'success' => false, 
        'message' => 'An error occurred while checking your bonus status',
        'canClaim' => false,
        'error' => $errorMsg
    ], 500);
}
