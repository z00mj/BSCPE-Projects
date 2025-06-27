<?php
declare(strict_types=1);
require_once 'config.php';

// backend/inc/functions.php
// Sanitize input for XSS and unwanted tags
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Hash password securely
function passwordHash(string $password): string {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Check if user is logged in and session is valid
function isLoggedIn(): bool {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    return !empty($_SESSION['user_id']) 
        && !empty($_SESSION['last_activity']) 
        && (time() - $_SESSION['last_activity']) < (defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 3600);
}

// Redirect to login if not logged in
function userOnly(): void {
    if (!isLoggedIn()) {
        redirect('/RAWR/public/login.php');
    }
}

// Redirect function
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

// Generate a random referral code
function generateReferralCode(): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 8; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

// JSON response helper
function jsonResponse(array $data, int $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Generate a CSRF token and store in session
function generateCsrfToken(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate a CSRF token
function validateCsrfToken(string $token): bool {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Verify a password against a hash
function verifyPassword(string $password, string $hash): bool {
    return password_verify($password, $hash);
}

// Generate a random string (hex)
function generateRandomString(int $length = 32): string {
    return bin2hex(random_bytes($length / 2));
}

// Validate email address
function validateEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Format a balance with decimals
function formatBalance(float $amount, int $decimals = 8): string {
    return number_format($amount, $decimals, '.', '');
}

// Restrict access to admins only
function adminOnly(): void {
    if (!isLoggedIn() || empty($_SESSION['admin_id'])) {
        redirect('/admin/login.php');
    }
}

// Create login_attempts table if not exists
function createLoginAttemptsTable() {
    $db = Database::getInstance();
    $db->executeQuery("
        CREATE TABLE IF NOT EXISTS login_attempts (
            ip_address VARCHAR(45) PRIMARY KEY,
            attempts INT NOT NULL DEFAULT 1,
            last_attempt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");
}

// Check file upload
function checkFileUpload(array $file): array {
    $errors = [];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = match($file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File is too large',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            default => 'Unknown upload error',
        };
        return [false, $errors];
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        $errors[] = 'File exceeds maximum allowed size';
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime, ALLOWED_FILE_TYPES)) {
        $errors[] = 'Invalid file type';
    }
    
    return [empty($errors), $errors];
}

/**
 * UPDATED: Calculates the total mining reward for a session.
 * The shovel level now acts as a direct multiplier on the base reward.
 * e.g., Shovel Lvl 1 = 1x, Lvl 2 = 2x, etc.
 */
function calculateMiningReward(int $shovelLevel, int $minutesMined): float {
    $baseRewardPerMinute = MINING_BASE_REWARD_PER_MINUTE; // 1 RAWR/min
    $baseTotal = $baseRewardPerMinute * $minutesMined;
    $reward = $baseTotal * $shovelLevel; // Shovel level is a direct multiplier
    return (float)$reward;
}

/**
 * UPDATED: Calculates the mining cooldown in seconds based on energy level.
 * Each level reduces the cooldown by 10%.
 */
function getMiningCooldown(int $energyLevel): int {
    $baseCooldown = MINING_COOLDOWN; // 600 seconds
    $reductionFactor = pow(0.9, $energyLevel - 1); // 10% reduction per level
    return (int)ceil($baseCooldown * $reductionFactor);
}

function calculateConversion(int $rawr_amount): int {
    return floor($rawr_amount / CONVERSION_RATE);
}
