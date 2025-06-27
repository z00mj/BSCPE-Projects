<?php
header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$host = 'localhost';
$dbname = 'login';  // Changed from 'luck_wallet' to 'login' based on the SQL file
$username = 'root';
$password = '';

try {
    // Get the raw POST data
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    
    // Validate required fields
    if (!isset($data['nft_id']) || empty($data['nft_id'])) {
        throw new Exception('NFT ID is required');
    }
    
    $nft_id = intval($data['nft_id']);
    
    // Create database connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Check if NFT exists and is listed
        $stmt = $pdo->prepare("SELECT * FROM luck_wallet_nfts WHERE nft_id = ? AND listed_for_sale = 1");
        $stmt->execute([$nft_id]);
        $nft = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$nft) {
            throw new Exception('NFT not found or not currently listed for sale');
        }
        
        // Verify the current user is the owner
        if (!isset($_SESSION['user_id']) || $nft['current_owner_user_id'] != $_SESSION['user_id']) {
            throw new Exception('You do not have permission to remove this listing');
        }
        
        // Remove from listings
        $stmt = $pdo->prepare("UPDATE luck_wallet_nfts SET listed_for_sale = 0, price_luck = NULL, listing_date = NULL WHERE nft_id = ?");
        $stmt->execute([$nft_id]);
        
        // Try to log the action (optional)
        if (isset($_SESSION['user_id'])) {
            try {
                // Check if the logs table exists before trying to insert
                $tableExists = $pdo->query("SHOW TABLES LIKE 'luck_wallet_nft_logs'")->rowCount() > 0;
                
                if ($tableExists) {
                    $logStmt = $pdo->prepare("INSERT INTO luck_wallet_nft_logs (nft_id, user_id, action, created_at) VALUES (?, ?, 'unlisted', NOW())");
                    $logStmt->execute([$nft_id, $_SESSION['user_id']]);
                } else {
                    error_log("luck_wallet_nft_logs table does not exist. Skipping logging.");
                }
            } catch (Exception $logError) {
                // Log the error but don't fail the request
                error_log("Error logging NFT unlist action: " . $logError->getMessage());
            }
        }
        
        $pdo->commit();
        
        // Return success response with NFT ID
        echo json_encode([
            'success' => true,
            'message' => 'NFT removed from listings successfully',
            'nft_id' => $nft_id,
            'redirect_url' => '/marketplace/try.php' // Add redirect URL if needed
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
