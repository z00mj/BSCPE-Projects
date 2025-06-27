<?php
header('Content-Type: application/json');

// Include database connection
require_once __DIR__ . '/../../db_connect.php';

// Get the raw POST data
$data = json_decode(file_get_contents('php://input'), true);
$walletAddress = isset($data['wallet_address']) ? trim($data['wallet_address']) : '';

// Initialize response
$response = [
    'exists' => false,
    'error' => null
];

try {
    if (empty($walletAddress)) {
        throw new Exception('Wallet address is required');
    }

    // Prepare and execute query to check if wallet exists (case-insensitive)
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM luck_wallet_users WHERE LOWER(wallet_address) = LOWER(?)");
    $stmt->execute([$walletAddress]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $response['exists'] = ($result['count'] > 0);
    
} catch (Exception $e) {
    http_response_code(400);
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>
