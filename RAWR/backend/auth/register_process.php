<?php
require_once __DIR__ . '/../inc/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../register.php');
    exit;
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
    header('Location: /RAWR/public/register.php?error=csrf');
    exit;
}

// Sanitize input
$data = [
    'username' => sanitizeInput($_POST['username']),
    'email' => sanitizeInput($_POST['email']),
    'password' => $_POST['password'],
    'confirm_password' => $_POST['confirm_password'],
    'referral_code' => isset($_POST['referral_code']) ? sanitizeInput($_POST['referral_code']) : null,
    'terms' => isset($_POST['terms']) ? true : false,
];

// Validate terms acceptance
if (!$data['terms']) {
   header('Location: /RAWR/public/register.php?error=terms');
    exit;
}

// Validate password match
if ($data['password'] !== $data['confirm_password']) {
    header('Location: /RAWR/public/register.php?error=password');
    exit;
}

// Validate password strength
$validPassword = strlen($data['password']) >= 8 && 
    preg_match('/[A-Z]/', $data['password']) && 
    preg_match('/[a-z]/', $data['password']) && 
    preg_match('/[0-9]/', $data['password']);

if (!$validPassword) {
    header('Location: /RAWR/public/register.php?error=weak_password');
    exit;
}

// Process registration
$auth = new Auth();
$result = $auth->registerUser($data);

if ($result['success']) {
    // --- FIX: All referral logic has been moved to Auth::registerUser ---
    // The code that previously caused the duplicate entry error has been removed from this file.
    
    // Redirect to login with success message
    header('Location: /RAWR/public/login.php?registered=1');
    exit;
} else {
    // Registration failed, redirect back with an error
    $error = urlencode($result['errors'][0] ?? 'registration_failed');
    header('Location: /RAWR/public/register.php?error=' . $error);
    exit;
}
