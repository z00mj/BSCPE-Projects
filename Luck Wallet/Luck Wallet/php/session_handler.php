<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
$requiredFile = __DIR__ . '/../db_connect.php';
if (!file_exists($requiredFile)) {
    error_log("Database connection file not found: $requiredFile");
    throw new Exception("Database configuration error");
}
require_once $requiredFile;

function isLoggedIn() {
    $isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    error_log("isLoggedIn check - User ID in session: " . ($_SESSION['user_id'] ?? 'none'));
    return $isLoggedIn;
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        error_log("getCurrentUser: No user_id in session");
        return null;
    }
    
    global $pdo;
    
    try {
        $userId = $_SESSION['user_id'];
        error_log("Fetching user with ID: $userId");
        
        // Get user data with eCasino balance from users table
        $stmt = $pdo->prepare("
            SELECT lwu.*, u.balance as ecasino_balance 
            FROM luck_wallet_users lwu
            LEFT JOIN users u ON lwu.email = u.email
            WHERE lwu.user_id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If no eCasino balance found, set it to 0
        if ($user && !isset($user['ecasino_balance'])) {
            $user['ecasino_balance'] = 0.00;
        }
        
        if (!$user) {
            error_log("No user found with ID: $userId");
            // Clear invalid session
            unset($_SESSION['user_id']);
            return null;
        }
        
        error_log("User found: " . ($user['email'] ?? 'No email'));
        return $user;
        
    } catch (PDOException $e) {
        error_log("Database error in getCurrentUser: " . $e->getMessage());
        return null;
    }
}

// Redirect to login if not logged in (for protected pages)
function requireLogin($redirectTo = '/auth/login.php') {
    if (!isLoggedIn()) {
        $currentUrl = $_SERVER['REQUEST_URI'];
        error_log("User not logged in, redirecting to login. Current URL: $currentUrl");
        header('Location: ' . $redirectTo . '?redirect=' . urlencode($currentUrl));
        exit();
    }
}
?>
