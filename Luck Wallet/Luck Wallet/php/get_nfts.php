<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../php_errors.log');

// Start output buffering to catch any unexpected output
if (ob_get_level() === 0) {
    ob_start();
}

// Log the start of the request
error_log("=== Starting get_nfts.php request ===");
error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
error_log("Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));

// Function to send JSON response and exit
function sendJsonResponse($data, $statusCode = 200) {
    // Log the response being sent
    error_log("Sending JSON response. Status: $statusCode");
    
    // Clear any previous output
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Set headers
    http_response_code($statusCode);
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Add debug info if not in production
    if (!isset($data['debug']) && $statusCode === 200) {
        $data['debug'] = [
            'memory_usage' => round(memory_get_usage() / 1024 / 1024, 2) . 'MB',
            'execution_time' => round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2) . 'ms'
        ];
    }
    
    // Output the JSON
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
    // Log the end of the request
    error_log("=== Completed get_nfts.php request ===\n");
    exit();
}

try {
    // Log the start of processing
    error_log("Starting NFT retrieval process");
    
    // Include required files with validation
    $baseDir = __DIR__ . '/..';
    $requiredFiles = [
        'db_connect.php' => $baseDir . '/db_connect.php',
        'session_handler.php' => $baseDir . '/php/session_handler.php',
        'nft_functions.php' => $baseDir . '/php/nft_functions.php'
    ];
    
    foreach ($requiredFiles as $name => $file) {
        if (!file_exists($file)) {
            $error = "Required file not found: $name at $file";
            error_log($error);
            throw new Exception($error);
        }
        error_log("Including file: $file");
        require_once $file;
    }

    // Check if user is logged in
    error_log("Checking if user is logged in...");
    $currentUser = getCurrentUser();
    if (!$currentUser || !isset($currentUser['user_id'])) {
        $error = 'User not authenticated or invalid user session';
        error_log($error);
        error_log("Session data: " . print_r($_SESSION, true));
        throw new Exception($error);
    }
    
    error_log("Current user data: " . print_r($currentUser, true));

    // Validate database connection
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        $error = 'Database connection failed or not initialized';
        error_log($error);
        throw new Exception($error);
    }
    
    // Test database connection
    try {
        $pdo->query('SELECT 1');
    } catch (PDOException $e) {
        $error = 'Database connection test failed: ' . $e->getMessage();
        error_log($error);
        throw new Exception($error);
    }
    
    // Get and validate user ID
    $userId = (int)$currentUser['user_id'];
    error_log("Processing NFTs for user ID: $userId (type: " . gettype($userId) . ")");
    
    if ($userId <= 0) {
        $error = "Invalid user ID: $userId";
        error_log($error);
        throw new Exception($error);
    }
    
    // Get NFTs for the current user
    error_log("Calling getNFTsByOwner for user ID: $userId");
    $nfts = getNFTsByOwner($pdo, $userId);
    
    // Log the results
    $nftCount = is_array($nfts) ? count($nfts) : 0;
    error_log("Retrieved $nftCount NFTs from getNFTsByOwner");
    
    if ($nftCount > 0) {
        error_log("First NFT sample: " . print_r($nfts[0], true));
    }
    
    // Run a direct query to verify NFTs exist
    try {
        $directQuery = "SELECT nft_id, name, current_owner_user_id FROM luck_wallet_nfts WHERE current_owner_user_id = ?";
        $directStmt = $pdo->prepare($directQuery);
        $directStmt->execute([$userId]);
        $directResults = $directStmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Direct query found " . count($directResults) . " NFTs for user $userId");
        if (!empty($directResults)) {
            error_log("Direct query results: " . print_r($directResults, true));
        }
    } catch (Exception $e) {
        error_log("Debug direct query failed: " . $e->getMessage());
    }
    
    // Format the NFTs data
    $formattedNFTs = [];
    if (is_array($nfts) && !empty($nfts)) {
        foreach ($nfts as $nft) {
            if (!is_array($nft)) continue;
            
            $formattedNFTs[] = [
                'id' => (int)($nft['nft_id'] ?? 0),
                'name' => htmlspecialchars($nft['name'] ?? 'Unnamed NFT', ENT_QUOTES, 'UTF-8'),
                'collection_id' => !empty($nft['collection_id']) ? (int)$nft['collection_id'] : null,
                'collection_name' => !empty($nft['collection_name']) ? htmlspecialchars($nft['collection_name'], ENT_QUOTES, 'UTF-8') : null,
                'description' => htmlspecialchars($nft['description'] ?? '', ENT_QUOTES, 'UTF-8'),
                'image_url' => filter_var($nft['image_url'] ?? 'img/default-nft.svg', FILTER_SANITIZE_URL),
                'price_luck' => isset($nft['price_luck']) ? number_format((float)$nft['price_luck'], 0, '.', ',') : '0',
                'listing_date' => !empty($nft['listing_date']) ? date('M j, Y', strtotime($nft['listing_date'])) : 'Not listed',
                'is_listed' => (bool)($nft['listed_for_sale'] ?? false),
                'owner_username' => htmlspecialchars($nft['owner_username'] ?? 'Unknown', ENT_QUOTES, 'UTF-8'),
                'raw_data' => $nft // Include raw data for debugging
            ];
        }
    }
    
    // Prepare response with debug info
    $response = [
        'success' => true,
        'data' => $formattedNFTs,
        'count' => count($formattedNFTs),
        'debug' => [
            'userId' => $userId,
            'userEmail' => $currentUser['email'] ?? 'unknown',
            'nfts_count' => count($formattedNFTs),
            'memory_usage' => round(memory_get_usage() / 1024 / 1024, 2) . 'MB',
            'direct_query_count' => count($directResults ?? []),
            'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
            'php_version' => phpversion(),
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ];
    
    // Log the response before sending
    error_log("Sending response with " . count($formattedNFTs) . " NFTs");
    
    // Send the response
    sendJsonResponse($response);
    
} catch (PDOException $e) {
    $errorDetails = [
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ];
    
    error_log('Database error in get_nfts.php: ' . print_r($errorDetails, true));
    
    sendJsonResponse([
        'success' => false,
        'error' => 'Database error occurred',
        'debug' => $errorDetails
    ], 500);
    
} catch (Exception $e) {
    $errorDetails = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
        'session' => $_SESSION ?? []
    ];
    
    error_log('Error in get_nfts.php: ' . print_r($errorDetails, true));
    
    sendJsonResponse([
        'success' => false,
        'error' => 'An error occurred while processing your request',
        'debug' => $errorDetails
    ], 400);
}

// Final exit point - should never be reached
exit(0);
