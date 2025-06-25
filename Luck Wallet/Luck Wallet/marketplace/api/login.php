<?php
// marketplace/api/login.php
require_once __DIR__ . '/../../db_connect.php';

// Set JSON header
header('Content-Type: application/json');

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'redirect' => ''
];

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_type']) && $_POST['login_type'] === 'standard') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $response['message'] = 'Both email and password are required.';
        echo json_encode($response);
        exit;
    }

    try {
        // Query the database for the user
        $stmt = $pdo->prepare("SELECT user_id, username, email, password_hash, e_casino_linked FROM luck_wallet_users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verify user exists and the password is correct (for both standard and LuckyTime users)
        if ($user && password_verify($password, $user['password_hash'])) {
            // Start session and store user data
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['logged_in_type'] = 'standard';

            // Set success response
            $response['success'] = true;
            $response['message'] = 'Login successful';
            // No redirect - let the frontend handle it
        } else if ($user && $user['e_casino_linked'] == 1) {
            // Allow LuckyTime users to log in normally
            $response['message'] = 'Invalid email or password.';
        } else {
            $response['message'] = 'Invalid email or password.';
        }
    } catch (PDOException $e) {
        error_log('Login error: ' . $e->getMessage());
        $response['message'] = 'An error occurred. Please try again later.';
    }
} else {
    $response['message'] = 'Invalid request method or missing parameters.';
}

// Return JSON response
echo json_encode($response);
