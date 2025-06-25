<?php
require_once __DIR__ . '/session_handler.php';
require_once __DIR__ . '/db_connect.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

try {
    $userId = $_SESSION['user_id'];
    
    // Get current balance
    $stmt = $pdo->prepare("SELECT luck_balance as balance FROM luck_wallet_users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'balance' => (float)$result['balance'],
            'message' => 'Balance retrieved successfully'
        ]);
    } else {
        throw new Exception('User not found');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch balance: ' . $e->getMessage()
    ]);
}
