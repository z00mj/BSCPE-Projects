<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
$isAuthenticated = isset($_SESSION['user_id']);

// Prepare response
$response = [
    'authenticated' => $isAuthenticated,
    'username' => $_SESSION['username'] ?? null,
    'email' => $_SESSION['email'] ?? null,
    'wallet_address' => $_SESSION['wallet_address'] ?? null,
    'balance' => $_SESSION['balance'] ?? 0
];

// Set JSON header
header('Content-Type: application/json');

// Return JSON response
echo json_encode($response);
