<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session to access user data
session_start();

// Set content type to JSON
header('Content-Type: application/json');

// Database configuration
$db_host = 'localhost';
$db_name = 'login';
$db_user = 'root';
$db_pass = '';

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST method is allowed']);
    exit;
}

// Get raw POST data
$input = file_get_contents('php://input');
parse_str($input, $data);

// Get NFT ID and new price from POST data
$nft_id = isset($data['nft_id']) ? trim($data['nft_id']) : '';
$new_price = isset($data['new_price']) ? floatval($data['new_price']) : 0;

// Validate input
if (empty($nft_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'NFT ID is required']);
    exit;
}

if ($new_price <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Price must be greater than 0']);
    exit;
}

try {
    // Connect to the database
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Check if NFT exists and is listed by the current user
    $stmt = $pdo->prepare("SELECT * FROM luck_wallet_nfts WHERE nft_id = ? AND listed_for_sale = 1 AND current_owner_user_id = ?");
    $stmt->execute([$nft_id, $_SESSION['user_id']]);
    $nft = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$nft) {
        throw new Exception('NFT not found, not listed, or you do not have permission to edit this listing');
    }
    
    // Update the listing price
    $updateStmt = $pdo->prepare("UPDATE luck_wallet_nfts SET price_luck = ?, listing_date = NOW() WHERE nft_id = ?");
    $updateStmt->execute([$new_price, $nft_id]);
    
    // Try to log the action (optional)
    if (isset($_SESSION['user_id'])) {
        try {
            // Check if the logs table exists before trying to insert
            $tableExists = $pdo->query("SHOW TABLES LIKE 'luck_wallet_nft_logs'")->rowCount() > 0;
            
            if ($tableExists) {
                $logStmt = $pdo->prepare("INSERT INTO luck_wallet_nft_logs (nft_id, user_id, action, created_at) VALUES (?, ?, 'price_updated', NOW())");
                $logStmt->execute([$nft_id, $_SESSION['user_id']]);
            } else {
                error_log("luck_wallet_nft_logs table does not exist. Skipping logging.");
            }
        } catch (Exception $logError) {
            // Log the error but don't fail the request
            error_log("Error logging NFT price update: " . $logError->getMessage());
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Listing updated successfully',
        'nft_id' => $nft_id,
        'new_price' => $new_price
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
