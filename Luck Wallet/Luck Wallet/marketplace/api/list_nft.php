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
if (!isset($data['nft_id']) || !isset($data['price'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$nftId = intval($data['nft_id']);
$price = floatval($data['price']);

if ($nftId <= 0 || $price <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid NFT ID or price']);
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
$dbname = 'login';  // Changed from 'luck_wallet' to 'login'
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
    
    // Check if already listed
    if ($nft['listed_for_sale']) {
        throw new Exception('NFT is already listed for sale');
    }
    
    // Update the NFT to be listed for sale
    $updateStmt = $pdo->prepare("UPDATE luck_wallet_nfts SET listed_for_sale = 1, price_luck = ?, listing_date = NOW() WHERE nft_id = ? AND current_owner_user_id = ?");
    $updateStmt->execute([$price, $nftId, $userId]);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'NFT listed for sale successfully',
        'nft_id' => $nftId,
        'price' => $price
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
?>
