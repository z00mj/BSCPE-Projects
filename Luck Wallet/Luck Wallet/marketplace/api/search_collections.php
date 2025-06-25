<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/search_errors.log');

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database connection
$host = 'localhost';
$dbname = 'login';
$username_db = 'root';
$password_db = '';

// Log function for debugging
function logError($message) {
    $logMessage = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    error_log($logMessage, 3, __DIR__ . '/search_errors.log');
}

// Function to send JSON response
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Log the request
    logError('New request: ' . json_encode([
        'method' => $_SERVER['REQUEST_METHOD'],
        'query' => $_GET,
        'ip' => $_SERVER['REMOTE_ADDR']
    ]));
    
    if (!isset($_GET['q'])) {
        throw new Exception('Missing search query parameter');
    }
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username_db, $password_db);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get search query
    $query = isset($_GET['q']) ? trim($_GET['q']) : '';
    
    if (empty($query)) {
        sendResponse(['success' => false, 'message' => 'Search query is required'], 400);
    }
    
    // First try exact match by ID if query is numeric
    if (is_numeric($query)) {
        try {
            $stmt = $conn->prepare("SELECT id, name, logo_url, description FROM collections WHERE id = :id LIMIT 1");
            $stmt->bindValue(':id', $query, PDO::PARAM_INT);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to execute ID query');
            }
            
            $exactMatch = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($exactMatch) {
                // Add default logo if not set
                if (empty($exactMatch['logo_url'])) {
                    $exactMatch['logo_url'] = 'https://via.placeholder.com/50';
                }
                
                logError('Found exact match by ID: ' . json_encode($exactMatch));
                
                sendResponse([
                    'success' => true,
                    'results' => [$exactMatch]
                ]);
            }
        } catch (Exception $e) {
            logError('Error in ID search: ' . $e->getMessage());
            // Continue with name search if ID search fails
        }
    }

    // If no exact ID match, search by name
    try {
        $searchTerm = "%$query%";
        logError('Searching for collections with query: ' . $query);
        
        $sql = "SELECT 
                    collection_id as collection_id,
                    name as collection_name,
                    COALESCE(logo_url, 'assets/images/placeholder-logo.png') as logo_url,
                    description
                FROM luck_wallet_nft_collections 
                WHERE 
                    LOWER(name) LIKE LOWER(:query) OR 
                    LOWER(name) LIKE LOWER(:query_start) OR
                    LOWER(name) LIKE LOWER(:query_contains) OR
                    collection_id = :id_query
                ORDER BY 
                    CASE 
                        WHEN LOWER(name) = LOWER(:exact_match) THEN 1
                        WHEN LOWER(name) LIKE LOWER(:query_start) THEN 2
                        WHEN LOWER(name) LIKE LOWER(:query_contains) THEN 3
                        ELSE 4
                    END
                LIMIT 10";

        logError('SQL Query: ' . $sql);
        
        $stmt = $conn->prepare($sql);
        
        $params = [
            'exact_match' => $query,
            'query' => $query,
            'query_start' => $query . '%',
            'query_contains' => '%' . $query . '%',
            'id_query' => $query
        ];
        logError('Query Parameters: ' . json_encode($params));

        $stmt->bindValue(':exact_match', $query);
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_start', $query . '%');
        $stmt->bindValue(':query_contains', '%' . $query . '%');
        $stmt->bindValue(':id_query', $query);

        if (!$stmt->execute()) {
            throw new Exception('Failed to execute name search query');
        }
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        logError('Found ' . count($results) . ' results');
        
        logError('Sending response with ' . count($results) . ' results');
        
        sendResponse([
            'success' => true,
            'results' => $results
        ]);

        // Add default logo if not set
        foreach ($results as &$result) {
            if (empty($result['logo_url'])) {
                $result['logo_url'] = 'https://via.placeholder.com/50';
            }
        }
    } catch (Exception $e) {
        logError('Error in name search: ' . $e->getMessage());
        throw $e;
    }
    
    if (!isset($results)) {
        $results = [];
    }
    
    logError('Sending response with ' . count($results) . ' results');
    
    sendResponse([
        'success' => true,
        'results' => $results
    ]);

} catch(PDOException $e) {
    $errorMsg = 'Database error: ' . $e->getMessage();
    logError($errorMsg);
    sendResponse([
        'success' => false,
        'message' => 'Database error occurred',
        'debug' => (ENVIRONMENT === 'development') ? $e->getMessage() : null
    ], 500);
} catch(Exception $e) {
    $errorMsg = 'Error: ' . $e->getMessage();
    logError($errorMsg);
    sendResponse([
        'success' => false,
        'message' => 'An error occurred',
        'debug' => (ENVIRONMENT === 'development') ? $e->getMessage() : null
    ], 500);
}
?>
