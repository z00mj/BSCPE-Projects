<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Set content type first to ensure proper JSON response
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Include database connection after session check
try {
    require_once __DIR__ . '/../../db_connect.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$nftId = $data['nftId'] ?? null;

if (!$nftId) {
    echo json_encode(['success' => false, 'message' => 'NFT ID is required']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // First, verify the NFT exists and is owned by the current user
    $stmt = $pdo->prepare(
        "SELECT nft_id, current_owner_user_id, listed_for_sale 
         FROM luck_wallet_nfts 
         WHERE nft_id = ?"
    );
    $stmt->execute([$nftId]);
    $nft = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$nft) {
        throw new Exception('NFT not found');
    }
    
    if ($nft['current_owner_user_id'] != $_SESSION['user_id']) {
        throw new Exception('You do not own this NFT');
    }
    
    if (!$nft['listed_for_sale']) {
        throw new Exception('This NFT is not listed for sale');
    }
    
    // Update the NFT to unlist it
    $updateStmt = $pdo->prepare(
        "UPDATE luck_wallet_nfts 
         SET listed_for_sale = 0, 
             listing_date = NULL,
             price_luck = NULL
         WHERE nft_id = ?"
    );
    $updateStmt->execute([$nftId]);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'NFT successfully unlisted',
        'nftId' => $nftId
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
