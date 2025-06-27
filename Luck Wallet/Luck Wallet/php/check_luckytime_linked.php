<?php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/session_handler.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

try {
    // Connect to the LuckyTime database
    $luckytime_db = new mysqli('localhost', 'root', '', 'login');
    if ($luckytime_db->connect_error) {
        throw new Exception("Failed to connect to LuckyTime database: " . $luckytime_db->connect_error);
    }
    
    // Check if the email exists in both databases
    $email = $_SESSION['email'];
    
    // Check Luck Wallet user
    $stmt = $pdo->prepare("SELECT user_id, username FROM luck_wallet_users WHERE email = ?");
    $stmt->execute([$email]);
    $wallet_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$wallet_user) {
        echo json_encode(['success' => false, 'message' => 'User not found in Luck Wallet']);
        exit;
    }
    
    // Check LuckyTime user
    $stmt = $luckytime_db->prepare("SELECT id, firstName, lastName FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $luckytime_user = $result->fetch_assoc();
    
    $is_linked = ($luckytime_user !== null);
    $username = $is_linked ? $luckytime_user['firstName'] . ' ' . $luckytime_user['lastName'] : '';
    
    echo json_encode([
        'success' => true,
        'is_linked' => $is_linked,
        'luckytime_username' => $username,
        'email' => $email
    ]);
    
} catch (Exception $e) {
    error_log('Error checking account link: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
