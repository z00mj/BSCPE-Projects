<?php
// Enable error reporting for debugging
ini_set('display_errors', 0);  // Don't show errors to users
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../error_log.txt');
error_reporting(E_ALL);

// Start output buffering to prevent any accidental output
ob_start();

// Set content type to JSON
header('Content-Type: application/json');

// Include required files
require_once __DIR__ . DIRECTORY_SEPARATOR . 'session_handler.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'db_connect.php';

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

// Get current user
$currentUser = getCurrentUser();
if (!$currentUser) {
    sendJsonResponse(['success' => false, 'message' => 'Not authenticated'], 401);
}

// Get user ID
$userId = $currentUser['user_id'];

// Amount of LUCK to award for daily bonus
$bonusAmount = 50;

try {
    debug_log("Starting claim_daily_bonus.php");
    
    // Include required files - using DIRECTORY_SEPARATOR for cross-platform compatibility
    $sessionFile = __DIR__ . DIRECTORY_SEPARATOR . 'session_handler.php';
    $dbFile = __DIR__ . DIRECTORY_SEPARATOR . 'db_connect.php';
    
    debug_log("Including files: $sessionFile and $dbFile");
    
    if (!file_exists($sessionFile) || !file_exists($dbFile)) {
        $error = "Required files not found. Session: " . (file_exists($sessionFile) ? 'exists' : 'missing') . 
                ", DB: " . (file_exists($dbFile) ? 'exists' : 'missing');
        debug_log($error);
        sendJsonResponse(['success' => false, 'message' => 'System error. Please try again later.'], 500);
    }
    
    require_once $sessionFile;
    require_once $dbFile;

    // Check if user is logged in
    $currentUser = getCurrentUser();
    debug_log('Current user: ' . ($currentUser ? 'Logged in as ' . ($currentUser['email'] ?? 'unknown') : 'Not logged in'));
    
    if (!$currentUser) {
        debug_log('User not authenticated');
        sendJsonResponse([
            'success' => false, 
            'message' => 'Not authenticated'
        ], 401);
    }

    // Get database connection
    debug_log('Getting database connection');
    $db = getDBConnection();
    if (!$db) {
        $error = 'Could not connect to database';
        debug_log($error);
        throw new Exception($error);
    }

    // Check if user has already claimed today
    $query = "SELECT last_daily_claim, luck_balance FROM luck_wallet_users WHERE user_id = ?";
    debug_log("Preparing query: $query");
    
    $stmt = $db->prepare($query);
    if (!$stmt) {
        $error = 'Failed to prepare statement: ' . $db->error;
        debug_log($error);
        throw new Exception($error);
    }
    
    $userId = $currentUser['user_id'];
    debug_log("Binding user_id: $userId");
    
    $stmt->bind_param('i', $userId);
    if (!$stmt->execute()) {
        $error = 'Failed to execute query: ' . $stmt->error;
        debug_log($error);
        throw new Exception($error);
    }
    
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    debug_log("User found in database");
    
    // Check if user has already claimed today
    $now = new DateTime();
    $today = $now->format('Y-m-d');
    debug_log("Today's date: " . $today);
    
    if ($user['last_daily_claim']) {
        $lastClaim = new DateTime($user['last_daily_claim']);
        $lastClaimDate = $lastClaim->format('Y-m-d');
        debug_log("Last claim date: " . $lastClaimDate);
        
        if ($lastClaimDate === $today) {
            sendJsonResponse([
                'success' => false,
                'message' => 'You have already claimed your daily bonus today. Come back tomorrow!',
                'canClaim' => false
            ]);
        }
    }
    
        // Start transaction
    $db->begin_transaction();
    debug_log("Starting database transaction");
    
    try {
        // First, check if user has already claimed today using a single query
        $checkQuery = "SELECT luck_balance FROM luck_wallet_users 
                      WHERE user_id = ? AND 
                      DATE(last_daily_claim) = CURDATE()
                      FOR UPDATE";
                      
        $checkStmt = $db->prepare($checkQuery);
        debug_log("Checking if user has already claimed today");
        $checkStmt->bind_param('i', $userId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            debug_log("User has already claimed today");
            $db->rollback();
            sendJsonResponse([
                'success' => false,
                'message' => 'You have already claimed your daily bonus today. Come back tomorrow!',
                'canClaim' => false
            ]);
        }
        
        // Get current balance with FOR UPDATE to lock the row
        $stmt = $db->prepare("SELECT luck_balance FROM luck_wallet_users WHERE user_id = ? FOR UPDATE");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('User not found');
        }
        
        $user = $result->fetch_assoc();
        debug_log("Current balance: " . $user['luck_balance']);
        // Update user's balance and last claim date
        $newBalance = $user['luck_balance'] + $bonusAmount;
        $updateStmt = $db->prepare("UPDATE luck_wallet_users SET last_daily_claim = NOW(), luck_balance = ? WHERE user_id = ?");
        debug_log("Preparing update query: UPDATE luck_wallet_users SET last_daily_claim = NOW(), luck_balance = ? WHERE user_id = ?");
        debug_log("Binding params - newBalance: $newBalance, userId: $userId");
        
        $updateStmt->bind_param('di', $newBalance, $userId);
        $updateStmt->execute();
        debug_log("Update executed. Rows affected: " . $updateStmt->affected_rows);
        
        if ($updateStmt->affected_rows === 0) {
            throw new Exception('Failed to update user balance');
        }
        
        // Log the transaction
        $transactionType = 'daily_bonus';
        $transactionQuery = "INSERT INTO luck_wallet_transactions (receiver_user_id, amount, transaction_type, notes) VALUES (?, ?, ?, ?)";
        debug_log("Preparing transaction query: INSERT INTO luck_wallet_transactions (receiver_user_id, amount, transaction_type, notes) VALUES (?, ?, ?, ?)");
        
        $transactionStmt = $db->prepare($transactionQuery);
        if (!$transactionStmt) {
            $error = 'Failed to prepare transaction statement: ' . $db->error;
            debug_log($error);
            throw new Exception($error);
        }
        
        $description = "Daily login bonus. New balance: $newBalance LUCK";
        debug_log("Binding transaction params - receiver_user_id: $userId, amount: $bonusAmount, type: $transactionType, description: $description");
        
        $transactionStmt->bind_param('iiss', $userId, $bonusAmount, $transactionType, $description);
        if (!$transactionStmt->execute()) {
            $error = 'Failed to log transaction: ' . $transactionStmt->error;
            debug_log($error);
            throw new Exception($error);
        }
        
        debug_log("Transaction logged successfully with ID: " . $db->insert_id);
        
        // Commit transaction
        $db->commit();
        debug_log("Committing transaction");
        
        // Return success response
        debug_log("Daily bonus claimed successfully!");
        sendJsonResponse([
            'success' => true,
            'message' => 'Daily bonus claimed successfully!',
            'newBalance' => $newBalance,
            'canClaim' => false
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if (isset($db) && $db) {
            $db->rollback();
            debug_log("Transaction rolled back: " . $e->getMessage());
        }
        throw $e;
    }
    
} catch (Exception $e) {
    // Log the error with full stack trace
    $errorMsg = 'Claim Daily Bonus Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine();
    debug_log($errorMsg);
    debug_log('Stack trace: ' . $e->getTraceAsString());
    
    // Return error response
    $errorResponse = [
        'success' => false,
        'message' => 'An error occurred while processing your request. Please try again.'
    ];
    
    // In development, include more error details
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        $errorResponse['error'] = $e->getMessage();
        $errorResponse['file'] = $e->getFile();
        $errorResponse['line'] = $e->getLine();
    }
    
    sendJsonResponse($errorResponse, 500);
    
    debug_log('Error response sent to client');
}
?>
