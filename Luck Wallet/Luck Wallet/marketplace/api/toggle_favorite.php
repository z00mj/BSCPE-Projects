<?php
header('Content-Type: application/json');
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$nftId = filter_var($data['nft_id'] ?? 0, FILTER_VALIDATE_INT);
$action = $data['action'] ?? ''; // 'add' or 'remove'

if (!$nftId || !in_array($action, ['add', 'remove'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

// Database connection
$host = 'localhost';
$dbname = 'login';
$username_db = 'root';
$password_db = '';

try {
    $conn = new mysqli($host, $username_db, $password_db, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    $userId = $_SESSION['user_id'];
    
    if ($action === 'add') {
        // Check if already favorited
        $checkStmt = $conn->prepare("SELECT favorite_id FROM luck_wallet_user_favorites WHERE user_id = ? AND nft_id = ?");
        $checkStmt->bind_param("ii", $userId, $nftId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows === 0) {
            // Add to favorites
            $insertStmt = $conn->prepare("INSERT INTO luck_wallet_user_favorites (user_id, nft_id, created_at) VALUES (?, ?, NOW())");
            $insertStmt->bind_param("ii", $userId, $nftId);
            $insertStmt->execute();
            $insertStmt->close();
        }
        $checkStmt->close();
    } else {
        // Remove from favorites
        $deleteStmt = $conn->prepare("DELETE FROM luck_wallet_user_favorites WHERE user_id = ? AND nft_id = ?");
        $deleteStmt->bind_param("ii", $userId, $nftId);
        $deleteStmt->execute();
        $deleteStmt->close();
    }
    
    $conn->close();
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    error_log("Error in toggle_favorite.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>
