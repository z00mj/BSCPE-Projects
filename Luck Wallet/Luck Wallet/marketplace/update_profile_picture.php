<?php
// Enable error reporting for debugging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/update_profile_errors.log');

// Log the start of the script
error_log('--- Starting profile picture upload ---');

function log_debug($message, $data = null) {
    $log = date('[Y-m-d H:i:s] ') . $message;
    if ($data !== null) {
        $log .= ': ' . (is_string($data) ? $data : json_encode($data, JSON_PRETTY_PRINT));
    }
    error_log($log);
    return $log;
}

// Set headers first to ensure proper JSON response
header('Content-Type: application/json');

try {
    // Start session and check authentication
    session_start();
    
    // Log request details
    log_debug('Session started', session_id());
    log_debug('Session data', $_SESSION);
    log_debug('POST data', $_POST);
    log_debug('FILES data', $_FILES);
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not authenticated');
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['profile_picture'])) {
        log_debug('No file was uploaded (FILES is not set)');
        throw new Exception('No file was uploaded');
    }
    
    if ($_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
        $error = 'File upload error: ';
        switch ($_FILES['profile_picture']['error']) {
            case UPLOAD_ERR_INI_SIZE:
                $error .= 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $error .= 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form';
                break;
            case UPLOAD_ERR_PARTIAL:
                $error .= 'The uploaded file was only partially uploaded';
                break;
            case UPLOAD_ERR_NO_FILE:
                $error .= 'No file was uploaded';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $error .= 'Missing a temporary folder';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $error .= 'Failed to write file to disk';
                break;
            case UPLOAD_ERR_EXTENSION:
                $error .= 'A PHP extension stopped the file upload';
                break;
            default:
                $error .= 'Unknown error (' . $_FILES['profile_picture']['error'] . ')';
        }
        log_debug('File upload error', $error);
        throw new Exception($error);
    }
    
    $file = $_FILES['profile_picture'];
    $userId = $_SESSION['user_id'];
    $uploadDir = 'uploads/profile_pictures/';
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/bmp' => 'bmp',
        'image/tiff' => 'tiff',
        'image/svg+xml' => 'svg'
    ];
    $maxFileSize = 5 * 1024 * 1024; // 5MB
    
    // Get file info
    if (!file_exists($file['tmp_name'])) {
        log_debug('Temporary file does not exist', $file['tmp_name']);
        throw new Exception('Temporary file not found');
    }
    
    log_debug('Temporary file exists', [
        'size' => filesize($file['tmp_name']),
        'temp_name' => $file['tmp_name']
    ]);
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if (!$finfo) {
        throw new Exception('Failed to open file info database');
    }
    
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    log_debug('Detected MIME type', $mimeType);
    
    // Validate file type
    if (!array_key_exists($mimeType, $allowedTypes)) {
        $supportedTypes = implode(', ', array_unique(array_values($allowedTypes)));
        log_debug('Invalid file type detected', [
            'detected_type' => $mimeType,
            'allowed_types' => array_keys($allowedTypes)
        ]);
        throw new Exception("Invalid file type. Supported formats are: " . $supportedTypes);
    }
    
    // Validate file size
    if ($file['size'] > $maxFileSize) {
        throw new Exception('File is too large. Maximum size is 5MB.');
    }
    
    // Create upload directory if it doesn't exist
    if (!file_exists($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        throw new Exception('Failed to create upload directory');
    }
    
    // Generate a unique filename
    $fileExt = $allowedTypes[$mimeType];
    $fileName = 'profile_' . $userId . '_' . time() . '.' . $fileExt;
    $filePath = $uploadDir . $fileName;
    
    // Move the uploaded file to the destination
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        $error = error_get_last();
        log_debug('Failed to move uploaded file', [
            'source' => $file['tmp_name'],
            'destination' => $filePath,
            'error' => $error
        ]);
        throw new Exception('Failed to save uploaded file. ' . ($error['message'] ?? ''));
    }
    
    // Include database connection
    require_once __DIR__ . '/../db_connect.php';
    
    // Read the image file
    $imageData = file_get_contents($filePath);
    if ($imageData === false) {
        throw new Exception('Failed to read uploaded file');
    }
    
    // Store image data in database
    log_debug('Attempting to update database', [
        'user_id' => $userId,
        'image_size' => strlen($imageData)
    ]);
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // First, check if user exists
        $checkStmt = $pdo->prepare("SELECT user_id FROM luck_wallet_users WHERE user_id = ?");
        $checkStmt->execute([$userId]);
        
        if ($checkStmt->rowCount() === 0) {
            throw new Exception('No user found with the specified ID');
        }
        
        // Update the profile image
        $stmt = $pdo->prepare("UPDATE luck_wallet_users SET profile_image = ? WHERE user_id = ?");
        $result = $stmt->execute([$imageData, $userId]);
        
        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            throw new Exception('Failed to update profile picture in database: ' . ($errorInfo[2] ?? 'Unknown error'));
        }
        
        $affectedRows = $stmt->rowCount();
        log_debug('Database update result', [
            'affected_rows' => $affectedRows,
            'result' => $result
        ]);
        
        // Commit the transaction
        $pdo->commit();
        
    } catch (PDOException $e) {
        // Rollback the transaction on error
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        log_debug('PDO Exception', [
            'code' => $e->getCode(),
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        throw new Exception('Database error: ' . $e->getMessage());
    }
    
    // Prepare success response
    $response = [
        'success' => true,
        'imageUrl' => 'get_profile_picture.php?user_id=' . $userId . '&t=' . time(),
        'timestamp' => time(),
        'fileInfo' => [
            'name' => $file['name'],
            'type' => $mimeType,
            'size' => $file['size'],
            'savedPath' => $filePath
        ]
    ];
    
    log_debug('Profile picture upload successful', $response['fileInfo']);
    
    log_debug('Sending success response', $response);
    
    // Send response
    echo json_encode($response);
    
    // Log completion
    log_debug('Profile picture update completed successfully');
    
} catch (Exception $e) {
    // Log the error with full details
    $errorData = [
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ];
    
    log_debug('Profile picture upload error', $errorData);
    
    // Return error response
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] === '127.0.0.1') ? $errorData : null
    ]);
}
?>
