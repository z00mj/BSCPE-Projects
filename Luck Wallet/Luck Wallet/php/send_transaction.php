<?php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/session_handler.php';

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log the raw input for debugging
$input = file_get_contents('php://input');
$data = json_decode($input, true);
error_log('Raw input: ' . $input);

// If JSON decode failed, try to get data from POST
if (json_last_error() !== JSON_ERROR_NONE) {
    $data = $_POST;
    error_log('Using POST data instead of JSON: ' . print_r($data, true));
}

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Get the current user ID
if (!isset($_SESSION['user_id'])) {
    error_log('No user_id in session. Session data: ' . print_r($_SESSION, true));
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$senderId = $_SESSION['user_id'];

// Get and validate input
$receiverAddress = isset($data['receiverAddress']) ? trim($data['receiverAddress']) : '';
$amount = isset($data['amount']) ? floatval($data['amount']) : 0;

error_log('Receiver address: ' . $receiverAddress);
error_log('Amount: ' . $amount);

error_log("Processing transaction - Sender: $senderId, Receiver: $receiverAddress, Amount: $amount");

// Validate input
if (empty($receiverAddress)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Receiver address is required']);
    exit();
}

if ($amount <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Amount must be greater than 0']);
    exit();
}

try {
    try {
        if ($pdo->inTransaction()) {
            $pdo->commit(); // Commit any existing transaction
        }
        $pdo->beginTransaction();

        // Get sender's current balance with FOR UPDATE to lock the row
        $stmt = $pdo->prepare("SELECT user_id, luck_balance FROM luck_wallet_users WHERE user_id = ? FOR UPDATE");
        $stmt->execute([$senderId]);
        $sender = $stmt->fetch();

        if (!$sender) {
            throw new Exception('Sender not found');
        }

        // Check if sender has sufficient balance
        if ($sender['luck_balance'] < $amount) {
            throw new Exception('Insufficient balance');
        }

        // Check if receiver exists and is not the same as sender
        $stmt = $pdo->prepare("SELECT user_id, luck_balance as balance, username FROM luck_wallet_users WHERE wallet_address = ?");
        $stmt->execute([$receiverAddress]);
        $receiver = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$receiver) {
            throw new Exception('Recipient not found');
        }
        
        if ($receiver['user_id'] === $senderId) {
            throw new Exception('Cannot send to yourself');
        }

        $receiverId = $receiver['user_id'];

        // Update sender's balance
        $stmt = $pdo->prepare("UPDATE luck_wallet_users SET luck_balance = luck_balance - ? WHERE user_id = ?");
        $stmt->execute([$amount, $senderId]);

        // Update receiver's balance
        $stmt = $pdo->prepare("UPDATE luck_wallet_users SET luck_balance = luck_balance + ? WHERE user_id = ?");
        $stmt->execute([$amount, $receiverId]);

        // Ensure transactions table exists (in case it was dropped)
        $pdo->exec("CREATE TABLE IF NOT EXISTS luck_wallet_transactions (
            transaction_id INT AUTO_INCREMENT PRIMARY KEY,
            sender_user_id INT NOT NULL,
            receiver_user_id INT NOT NULL,
            amount DECIMAL(24, 8) NOT NULL,
            transaction_type VARCHAR(50) NOT NULL DEFAULT 'transfer',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (sender_user_id) REFERENCES luck_wallet_users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (receiver_user_id) REFERENCES luck_wallet_users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Record transaction
        $stmt = $pdo->prepare("
            INSERT INTO luck_wallet_transactions 
            (sender_user_id, receiver_user_id, amount, transaction_type, notes) 
            VALUES (?, ?, ?, 'transfer', ?)
        ");
        
        $notes = 'Transfer to ' . $receiverAddress;
        $stmt->execute([$senderId, $receiverId, $amount, $notes]);
        
        $transactionId = $pdo->lastInsertId();
        error_log("Transaction recorded. ID: $transactionId");

        try {
            // Commit the transaction
            $pdo->commit();
            
            // Get the updated balance
            $stmt = $pdo->prepare("SELECT luck_balance FROM luck_wallet_users WHERE user_id = ?");
            $stmt->execute([$senderId]);
            $updatedBalance = $stmt->fetchColumn();

            // Send success response
            $response = [
                'success' => true, 
                'message' => 'Transfer successful',
                'newBalance' => $updatedBalance,
                'data' => [
                    'success' => true,
                    'message' => 'Transfer successful'
                ]
            ];
            
            error_log('Sending success response: ' . json_encode($response));
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
            
        } catch (Exception $e) {
            // If we get here, there was an error after the transaction was committed
            error_log('Error after commit: ' . $e->getMessage());
            
            // Even though we had an error after commit, the transaction was successful
            $response = [
                'success' => true, 
                'message' => 'Transfer successful',
                'data' => [
                    'success' => true,
                    'message' => 'Transfer successful'
                ]
            ];
            
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }

    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        $errorMessage = $e->getMessage();
        error_log('Error in send_transaction.php: ' . $errorMessage);
        
        $response = [
            'success' => false, 
            'message' => $errorMessage,
            'data' => [
                'success' => false,
                'message' => $errorMessage
            ]
        ];
        
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $errorMessage = $e->getMessage();
    error_log('Error in send_transaction.php: ' . $errorMessage);
    
    $response = [
        'success' => false, 
        'message' => $errorMessage,
        'data' => [
            'success' => false,
            'message' => $errorMessage
        ]
    ];
    
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
