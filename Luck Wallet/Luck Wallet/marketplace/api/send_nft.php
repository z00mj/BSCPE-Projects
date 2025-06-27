<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get the raw POST data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate input
if (!isset($data['nft_id']) || !isset($data['recipient_address'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$nftId = intval($data['nft_id']);
$recipientAddress = trim($data['recipient_address']);
$message = isset($data['message']) ? trim($data['message']) : '';

// Basic validation
if ($nftId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid NFT ID']);
    exit();
}

// Check if recipient address is not empty
if (empty($recipientAddress)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Recipient address is required']);
    exit();
}

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$userId = $_SESSION['user_id'];

// Database connection
$host = 'localhost';
$dbname = 'login';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Check if NFT exists and is owned by the user
    $stmt = $pdo->prepare("SELECT nft_id, current_owner_user_id, listed_for_sale FROM luck_wallet_nfts WHERE nft_id = ? AND current_owner_user_id = ?");
    $stmt->execute([$nftId, $userId]);
    $nft = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$nft) {
        throw new Exception('NFT not found or not owned by user');
    }
    
    // Check if NFT is listed for sale (optional: prevent sending listed NFTs)
    if ($nft['listed_for_sale']) {
        throw new Exception('Please remove the NFT from sale before sending');
    }
    
    // Check if recipient exists in the system
    $stmt = $pdo->prepare("SELECT user_id FROM luck_wallet_users WHERE wallet_address = ?");
    $stmt->execute([$recipientAddress]);
    $recipient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$recipient) {
        throw new Exception('Recipient not found in our system');
    }
    
    $recipientId = $recipient['user_id'];
    
    // Update the NFT ownership
    $updateStmt = $pdo->prepare("UPDATE luck_wallet_nfts SET current_owner_user_id = ?, listed_for_sale = 0, price_luck = NULL, listing_date = NULL WHERE nft_id = ?");
    $updateStmt->execute([$recipientId, $nftId]);
    
    // Insert transaction record
    $transactionStmt = $pdo->prepare("INSERT INTO luck_wallet_transactions (sender_user_id, receiver_user_id, amount, transaction_type, transaction_timestamp, notes) VALUES (?, ?, 0, 'nft_transfer', NOW(), ?)");
    $transactionStmt->execute([$userId, $recipientId, "NFT transfer"]);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'NFT transferred successfully',
        'nft_id' => $nftId,
        'recipient_id' => $recipientId
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage(),
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'error' => $e->getMessage()
    ]);
}
