<?php
declare(strict_types=1);

// Site Configuration
define('SITE_NAME', 'RAWR - The Lion\'s Game');
define('BASE_URL', 'http://localhost/RAWR/public/');
define('SITE_ROOT', realpath(dirname(__DIR__)) . '/');
define('UPLOAD_DIR', SITE_ROOT . 'uploads/kyc_docs/');
define('DEV_MODE', true);

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'rawr_casino');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Game Settings
// UPDATED: Cooldown changed to 10 minutes (600 seconds)
define('MINING_COOLDOWN', 3600); 
// UPDATED: Base reward is now 1 RAWR per minute for a 10-minute session.
define('MINING_BASE_REWARD_PER_MINUTE', 1); 
define('CONVERSION_RATE', 20); // 20 RAWR = 1 Ticket
define('MAX_DAILY_STREAK', 7);
define('MAX_UPGRADE_LEVEL', 10); // Increased max level for more progression

// Security Settings
define('SALT', 'your_random_salt_here_32chars_long');
define('SESSION_TIMEOUT', 2592000); // 30 days
define('CSRF_TOKEN_LIFE', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// File Upload Settings
define('MAX_FILE_SIZE', 8 * 1024 * 1024); // 8 MB in bytes
define('ALLOWED_FILE_TYPES', ['image/jpeg', 'image/png', 'application/pdf']);

// Timezone
date_default_timezone_set('UTC');

if (DEV_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}
