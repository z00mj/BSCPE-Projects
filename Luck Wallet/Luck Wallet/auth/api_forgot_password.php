<?php
// api_forgot_password.php
require_once __DIR__ . '/../db_connect.php';

header('Content-Type: application/json');

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');
$newPassword = $data['newPassword'] ?? '';
$confirmPassword = $data['confirmPassword'] ?? '';

// Validate input
if (empty($email) || empty($newPassword) || empty($confirmPassword)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

if ($newPassword !== $confirmPassword) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
    exit;
}

if (strlen($newPassword) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long']);
    exit;
}

try {
    // Check if user exists and is a standard user (not LuckyTime-linked)
    $stmt = $pdo->prepare("SELECT user_id, e_casino_linked FROM luck_wallet_users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Don't reveal if user exists for security
        echo json_encode(['success' => true, 'message' => 'If an account exists with this email, the password has been reset']);
        exit;
    }

    // Check if user is linked to LuckyTime
    if ($user['e_casino_linked'] == 1) {
        echo json_encode([
            'success' => false, 
            'message' => 'This account is linked to LuckyTime. Please use the "Login with LuckyTime" option.'
        ]);
        exit;
    }

    // Hash the new password
    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

    // Update the password
    $updateStmt = $pdo->prepare("UPDATE luck_wallet_users SET password_hash = ? WHERE user_id = ?");
    $updateStmt->execute([$newPasswordHash, $user['user_id']]);

    if ($updateStmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Password has been reset successfully']);
    } else {
        throw new Exception('Failed to update password');
    }
} catch (Exception $e) {
    error_log('Password reset error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
}
?>
