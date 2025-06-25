<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once __DIR__ . '/../db_connect.php';

// Set headers for JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Function to generate a random referral code
function generateReferralCode($length = 8) {
    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $code;
}

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not authenticated');
    }

    // Get user's referral code
    $userId = $_SESSION['user_id'];
    
    // First, try to get the user's referral code
    $stmt = $pdo->prepare("SELECT referral_code FROM luck_wallet_users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('User not found in database');
    }
    
    // If no referral code exists, generate one
    if (empty($user['referral_code'])) {
        $code = generateReferralCode();
        
        // Update the user's record with the new code
        $updateStmt = $pdo->prepare("UPDATE luck_wallet_users SET referral_code = ? WHERE user_id = ?");
        $updateStmt->execute([$code, $userId]);
        
        $user['referral_code'] = $code;
    }
    
    // Return success response with referral code
    echo json_encode([
        'success' => true,
        'referral_code' => $user['referral_code'],
        'message' => 'Referral code retrieved successfully'
    ]);
    
} catch (Exception $e) {
    // Log the error
    error_log('Referral Code Error: ' . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to retrieve referral code. Please try again later.'
    ]);
}
?>
