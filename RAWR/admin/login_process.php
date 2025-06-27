<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';

// Debug: Check if session is started
if (session_status() !== PHP_SESSION_ACTIVE) {
    error_log('SESSION NOT STARTED!');
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /login.php');
    exit;
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
    header('Location: /login.php?error=csrf');
    exit;
}

// Sanitize inputs
$username = sanitizeInput($_POST['username'] ?? '');
$password = sanitizeInput($_POST['password'] ?? '');

if (empty($username) || empty($password)) {
    header('Location: /RAWR/public/login.php?error=empty');
    exit;
}

// First check if this is an admin user
$db = Database::getInstance();
$admin = $db->fetchOne(
    "SELECT id, password_hash, role FROM admin_users WHERE username = ?", 
    [$username]
);

// Debug: Log admin fetch result
error_log('ADMIN FETCH: ' . print_r($admin, true));

if ($admin) {
    // Debug: Log password check
    $passwordCheck = password_verify($password, $admin['password_hash']);
    error_log('ADMIN PASSWORD VERIFY: ' . ($passwordCheck ? 'SUCCESS' : 'FAIL'));
    // Verify admin password
    if ($passwordCheck) {
        // Admin login successful
        session_regenerate_id(true);
        // Store admin session data
        unset($_SESSION['user_id']); // Only remove user_id
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['role'] = $admin['role'];
        $_SESSION['last_activity'] = time();
        $_SESSION['logged_in'] = true;
        error_log('LOGIN SESSION (admin): ' . print_r($_SESSION, true));
        // Update last login
        $db->executeQuery(
            "UPDATE admin_users SET last_login = NOW() WHERE id = ?",
            [$admin['id']]
        );
        // Check for headers sent
        if (headers_sent($file, $line)) {
            error_log("HEADERS ALREADY SENT at $file:$line");
            echo "<b>Headers already sent at $file:$line. Cannot redirect. Check for whitespace or output before PHP tags.</b>";
            exit;
        }
        // Redirect to admin dashboard
        header('Location: /RAWR/admin/admin_dashboard.php');
        exit;
    } else {
        // Debug: Password failed
        error_log('ADMIN LOGIN FAILED: Password incorrect');
    }
} else {
    // Debug: Admin not found
    error_log('ADMIN LOGIN FAILED: Admin not found');
}

// If not admin, proceed with regular user login
$auth = new Auth();
$result = $auth->loginUser($username, $password);

if ($result['success']) {
    // Check for referral bonus
    if ($result['referred_by']) {
        $db = Database::getInstance();
        
        // Check if bonus already awarded
        $referral = $db->fetchOne(
            "SELECT bonus_awarded FROM referrals 
             WHERE referrer_id = ? AND referred_id = ?",
            [$result['referred_by'], $result['user_id']]
        );
        
        // Award bonus if not already given
        if ($referral && !$referral['bonus_awarded']) {
            $auth->awardReferralBonus($result['referred_by'], $result['user_id']);
        }
    }
    
    // Set logged in session
    unset($_SESSION['admin_id']); // Only remove admin_id
    $_SESSION['user_id'] = $result['user_id'];
    $_SESSION['user_type'] = 'user';
    $_SESSION['last_activity'] = time();
    $_SESSION['logged_in'] = true;
    error_log('LOGIN SESSION (user): ' . print_r($_SESSION, true));
    header('Location: /RAWR/public/dashboard.php');
    exit;
} else {
    // Handle failed login
    $error = urlencode(implode(', ', $result['errors']));
    header("Location: /RAWR/public/login.php?error=$error");
    exit;
}