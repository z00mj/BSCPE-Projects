<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear the redirect URL from session
unset($_SESSION['redirect_url']);

// Send success response
header('Content-Type: application/json');
echo json_encode(['success' => true]);
?>
