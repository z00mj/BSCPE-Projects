<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// For demo purposes, we'll just generate a random wallet address
// In a real app, you would validate and store the wallet address from the request
$wallet_address = '0x' . bin2hex(random_bytes(20));

// Store wallet address in session
$_SESSION['wallet_address'] = $wallet_address;

// In a real app, you would also save this to the database
// $user_id = $_SESSION['user_id'];
// $stmt = $pdo->prepare("UPDATE users SET wallet_address = ? WHERE id = ?");
// $stmt->execute([$wallet_address, $user_id]);

echo json_encode([
    'success' => true,
    'wallet_address' => $wallet_address,
    'message' => 'Wallet connected successfully'
]);
?>
