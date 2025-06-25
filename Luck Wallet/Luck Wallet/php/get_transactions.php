<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php_errors.log');

// Function to log debug information
function logDebug($message, $data = null) {
    $log = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if ($data !== null) {
        $log .= ': ' . print_r($data, true);
    }
    error_log($log);
}

// Function to send JSON response
function sendJsonResponse($success, $data = null, $message = '', $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    $response = ['success' => $success];
    if ($message) $response['message'] = $message;
    if ($data !== null) $response['data'] = $data;
    echo json_encode($response);
    exit();
}

try {
    // Log the start of the script
    logDebug('Starting get_transactions.php');
    
    // Include required files
    logDebug('Including required files');
    require_once __DIR__ . '/../db_connect.php';
    require_once __DIR__ . '/session_handler.php';
    
    // Start session
    if (session_status() === PHP_SESSION_NONE) {
        logDebug('Starting new session');
        session_start();
    }
    
    logDebug('Session data', $_SESSION);
    
    // Check if user is logged in
    if (!function_exists('isLoggedIn') || !isLoggedIn()) {
        $error = 'User not logged in or isLoggedIn function not found';
        logDebug($error);
        sendJsonResponse(false, null, $error, 401);
    }
    
    // Get user ID and validate
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    
    logDebug('User ID from session', $userId);
    logDebug('Limit from request', $limit);
    
    if (empty($userId) || !is_numeric($userId) || $userId <= 0) {
        $error = 'Invalid user ID: ' . var_export($userId, true);
        logDebug($error);
        sendJsonResponse(false, null, $error, 400);
    }
    
    // Ensure transactions table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS luck_wallet_transactions (
        transaction_id INT AUTO_INCREMENT PRIMARY KEY,
        sender_user_id INT NOT NULL,
        receiver_user_id INT NOT NULL,
        amount DECIMAL(24, 8) NOT NULL,
        transaction_type VARCHAR(50) NOT NULL DEFAULT 'transfer',
        notes TEXT,
        transaction_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_user_id) REFERENCES luck_wallet_users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_user_id) REFERENCES luck_wallet_users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    // Check database connection and list all tables for debugging
    try {
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        logDebug('Available tables in database:', $tables);
        
        // Check if transactions table exists
        $tableExists = in_array('luck_wallet_transactions', $tables);
        logDebug('Table luck_wallet_transactions exists:', $tableExists ? 'Yes' : 'No');
        
        if (!$tableExists) {
            logDebug('Transactions table does not exist');
            sendJsonResponse(true, ['transactions' => []]);
            exit;
        }
    } catch (Exception $e) {
        logDebug('Error checking database tables: ' . $e->getMessage());
        sendJsonResponse(false, null, 'Database error', 500);
        exit;
    }

    // Get transactions where the user is either sender or receiver
    $sql = "
        SELECT 
            t.transaction_id,
            t.amount,
            t.transaction_type,
            t.transaction_timestamp as created_at,
            t.notes,
            t.sender_user_id,
            t.receiver_user_id,
            CASE 
                WHEN t.transaction_type LIKE '%php_to_luck%' THEN 'received'  -- PHP to LUCK is receiving LUCK
                WHEN t.transaction_type LIKE '%luck_to_php%' THEN 'sent'      -- LUCK to PHP is sending LUCK
                WHEN t.sender_user_id = ? AND t.receiver_user_id = ? THEN 'conversion'  -- Self-conversion
                WHEN t.sender_user_id = ? THEN 'sent'                         -- Normal send
                WHEN t.receiver_user_id = ? THEN 'received'                    -- Normal receive
                ELSE 'unknown'
            END as direction
        FROM luck_wallet_transactions t
        WHERE t.sender_user_id = ? OR t.receiver_user_id = ?
        ORDER BY t.transaction_timestamp DESC
        LIMIT ?
    ";
    
    logDebug('Preparing SQL query', ['sql' => $sql, 'user_id' => $userId, 'limit' => $limit]);
    
    try {
        $stmt = $pdo->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Failed to prepare statement: ' . print_r($pdo->errorInfo(), true));
        }
        
        logDebug('Binding parameters', ['user_id' => $userId, 'limit' => $limit]);
        
        // Bind parameters for the query
        // First ? is for the CASE statement (self-conversion sender check)
        // Second ? is for the CASE statement (self-conversion receiver check)
        // Third ? is for the CASE statement (normal sender check)
        // Fourth ? is for the CASE statement (normal receiver check)
        // Fifth and sixth ? are for the WHERE clause
        // Seventh ? is for the LIMIT
        $stmt->bindParam(1, $userId, PDO::PARAM_INT);  // Self-conversion sender
        $stmt->bindParam(2, $userId, PDO::PARAM_INT);  // Self-conversion receiver
        $stmt->bindParam(3, $userId, PDO::PARAM_INT);  // Normal sender
        $stmt->bindParam(4, $userId, PDO::PARAM_INT);  // Normal receiver
        $stmt->bindParam(5, $userId, PDO::PARAM_INT);  // WHERE sender
        $stmt->bindParam(6, $userId, PDO::PARAM_INT);  // WHERE receiver
        $stmt->bindParam(7, $limit, PDO::PARAM_INT);   // LIMIT
        
        logDebug('Executing query');
        $executed = $stmt->execute();
        
        if (!$executed) {
            throw new Exception('Failed to execute statement: ' . print_r($stmt->errorInfo(), true));
        }
        
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        logDebug('Fetched ' . count($transactions) . ' transactions');
        
        // Log the first transaction if available for debugging
        if (!empty($transactions)) {
            logDebug('First transaction sample:', $transactions[0]);
        }
        
        // Format the transactions for display
        $formattedTransactions = [];
        foreach ($transactions as $tx) {
            try {
                $isOutgoing = $tx['direction'] === 'sent';
                $otherPartyId = $isOutgoing ? $tx['receiver_user_id'] : $tx['sender_user_id'];
                
                // Format transaction type for display
                $type = $tx['transaction_type'] ?? 'transfer';
                $typeFormatted = str_replace('_', ' ', ucfirst($type));
                
                // Format date for display (e.g., 'May 30, 2025 11:30 PM')
                $date = isset($tx['created_at']) ? date('M j, Y g:i A', strtotime($tx['created_at'])) : date('M j, Y g:i A');
                
                // For system transactions, set other party as 'System'
                $otherPartyUsername = 'System';
                
                // Only try to get username if this is not a system transaction
                if ($tx['transaction_type'] !== 'daily_bonus' && $tx['transaction_type'] !== 'system') {
                    $stmt = $pdo->prepare("SELECT username, wallet_address FROM luck_wallet_users WHERE user_id = ?");
                    if ($stmt->execute([$otherPartyId])) {
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($user) {
                            $otherPartyUsername = !empty($user['username']) ? $user['username'] : 
                                              (!empty($user['wallet_address']) ? $user['wallet_address'] : 'User ' . $otherPartyId);
                        }
                    }
                }
                
                $formattedTx = [
                    'id' => $tx['transaction_id'],
                    'type' => $type,
                    'direction' => $tx['direction'],
                    'amount' => isset($tx['amount']) ? (float)$tx['amount'] : 0,
                    'date' => $date,
                    'other_party' => $otherPartyUsername,
                    'transaction_type' => $type,
                    'notes' => $tx['notes'] ?? '',
                    'created_at' => $tx['created_at'] ?? date('Y-m-d H:i:s')
                ];
                
                // Add wallet address if available
                if (isset($user['wallet_address'])) {
                    $formattedTx['wallet_address'] = $user['wallet_address'];
                }
                
                $formattedTransactions[] = $formattedTx;
            } catch (Exception $e) {
                logDebug('Error formatting transaction: ' . $e->getMessage(), $tx);
                continue;
            }
        }
        
        // Debug: Log the final response
        logDebug('Sending response', ['transaction_count' => count($formattedTransactions)]);
        
        // Send the response with transactions in the expected format
        $response = [
            'success' => true,
            'message' => '',
            'data' => [
                'transactions' => $formattedTransactions
            ],
            'transactions' => $formattedTransactions
        ];
        
        // Debug: Log the final response structure
        logDebug('Sending final response', $response);
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
        
    } catch (Exception $e) {
        $error = 'Database query failed: ' . $e->getMessage();
        logDebug($error);
        throw new Exception($error);
    }
    
} catch (PDOException $e) {
    $errorMsg = 'Database error: ' . $e->getMessage() . "\n" .
               'File: ' . $e->getFile() . ':' . $e->getLine() . "\n" .
               'Trace: ' . $e->getTraceAsString();
    error_log($errorMsg);
    
    // Log the last SQL error if available
    if (isset($pdo)) {
        $errorInfo = $pdo->errorInfo();
        if (!empty($errorInfo[2])) {
            error_log('PDO Error: ' . print_r($errorInfo, true));
        }
    }
    
    sendJsonResponse(false, null, 'Database error occurred: ' . $e->getMessage(), 500);
    
} catch (Exception $e) {
    $errorMsg = 'Unexpected error: ' . $e->getMessage() . "\n" .
               'File: ' . $e->getFile() . ':' . $e->getLine() . "\n" .
               'Trace: ' . $e->getTraceAsString();
    error_log($errorMsg);
    
    sendJsonResponse(false, null, 'An unexpected error occurred: ' . $e->getMessage(), 500);
}
