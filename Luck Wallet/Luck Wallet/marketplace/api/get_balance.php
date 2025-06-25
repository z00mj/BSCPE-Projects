<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Include the database connection file from the root directory
require_once __DIR__ . '/../../db_connect.php';

$response = ['success' => false, 'balance' => 0];

try {
    // Check if $pdo is set and is a PDO instance
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception('Database connection not established');
    }

    // First try to get balance from luck_wallet_users table
    $stmt = $pdo->prepare("SELECT luck_balance as balance FROM luck_wallet_users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $walletUser = $stmt->fetch();
    
    if ($walletUser) {
        $response = [
            'success' => true,
            'balance' => (float)$walletUser['balance'],
            'source' => 'luck_wallet_users'
        ];
    } 
    // If not found in luck_wallet_users, try the users table
    else {
        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user) {
            $response = [
                'success' => true,
                'balance' => (float)$user['balance'],
                'source' => 'users'
            ];
        } else {
            $response['message'] = 'User not found in luck_wallet_users or users table';
        }
    }
} catch (PDOException $e) {
    error_log('Database error in get_balance.php: ' . $e->getMessage());
    $response['message'] = 'Database error: ' . $e->getMessage();
} catch (Exception $e) {
    error_log('Error in get_balance.php: ' . $e->getMessage());
    $response['message'] = 'An error occurred: ' . $e->getMessage();
}

// Ensure we're sending valid JSON
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>
