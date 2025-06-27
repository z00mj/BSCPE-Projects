<?php
// Start session
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once __DIR__ . '/../db_connect.php';

// Set default headers - we'll update the content type later
header('Content-Type: image/png');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Function to output image from binary data
function outputImageBinary($imageData) {
    if (empty($imageData)) {
        return false;
    }
    
    // Try to determine the image type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->buffer($imageData);
    
    if (strpos($mimeType, 'image/') !== 0) {
        return false;
    }
    
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . strlen($imageData));
    echo $imageData;
    return true;
}

// Function to output default avatar or transparent pixel
function outputDefaultImage() {
    $defaultAvatar = __DIR__ . '/assets/images/default-avatar.png';
    
    // Ensure the path is correct
    if (!file_exists($defaultAvatar)) {
        // Try one level up if not found
        $defaultAvatar = dirname(__DIR__) . '/assets/images/default-avatar.png';
    }
    
    error_log('Looking for default avatar at: ' . $defaultAvatar);
    
    if (file_exists($defaultAvatar)) {
        $imageData = file_get_contents($defaultAvatar);
        if ($imageData !== false) {
            error_log('Successfully loaded default avatar');
            outputImageBinary($imageData);
            return;
        } else {
            error_log('Failed to read default avatar file');
        }
    } else {
        error_log('Default avatar not found at: ' . $defaultAvatar);
    }
    
    // If we get here, serve a 1x1 transparent PNG
    error_log('Falling back to transparent pixel');
    header('Content-Type: image/png');
    echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
}

try {
    // Get user ID from query string
    $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    
    if ($user_id <= 0) {
        throw new Exception('Invalid user ID');
    }
    
    // Check if the luck_wallet_users table exists
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'luck_wallet_users'");
    $stmt->execute();
    $tableExists = $stmt->fetch();
    
    if (!$tableExists) {
        throw new Exception('luck_wallet_users table not found in database');
    }
    
    // Get the profile image data from luck_wallet_users table
    $stmt = $pdo->prepare('SELECT profile_image FROM luck_wallet_users WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If we have image data, output it
    if ($result && !empty($result['profile_image'])) {
        if (outputImageBinary($result['profile_image'])) {
            exit;
        }
    }
    
    // If we get here, serve the default avatar
    outputDefaultImage();
    
} catch (Exception $e) {
    // Log the error
    error_log('Error in get_profile_picture.php: ' . $e->getMessage());
    
    // Serve default image on error
    outputDefaultImage();
}
?>
