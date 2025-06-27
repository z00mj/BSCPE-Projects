<?php
require_once __DIR__ . '/../db_connect.php';

/**
 * Get all NFTs owned by a specific user
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 * @return array Array of NFTs
 */
/**
 * Get all NFTs owned by a specific user
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 * @return array Array of NFTs with default values if empty
 */
function getNFTsByOwner($pdo, $userId) {
    // Enable error logging
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    // Log function entry
    error_log("getNFTsByOwner called with user ID: " . $userId . " (type: " . gettype($userId) . ")");
    
    if (!$pdo) {
        error_log("Database connection is not valid");
        return [];
    }

    if (!$userId) {
        error_log("Invalid user ID provided");
        return [];
    }

    try {
        // Convert user ID to integer to ensure type consistency
        $userId = (int)$userId;
        error_log("Converted user ID to integer: " . $userId);
        
        // First, verify the user exists
        $userCheck = $pdo->prepare("SELECT user_id, email FROM luck_wallet_users WHERE user_id = ?");
        $userCheck->execute([$userId]);
        $user = $userCheck->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            error_log("User with ID $userId not found in the database");
            return [];
        }
        
        error_log("Found user: " . $user['email'] . " (ID: " . $user['user_id'] . ")");
        
        // Check if the NFTs table has any data
        $nftCount = $pdo->query("SELECT COUNT(*) as count FROM luck_wallet_nfts")->fetch()['count'];
        error_log("Total NFTs in database: " . $nftCount);
        
        // Debug: Check if there are any NFTs for this user using a direct query
        $directCheck = $pdo->prepare("SELECT COUNT(*) as count FROM luck_wallet_nfts WHERE current_owner_user_id = ?");
        $directCheck->execute([$userId]);
        $userNftCount = $directCheck->fetch()['count'];
        error_log("NFTs found for user $userId: " . $userNftCount);
        
        // If no NFTs found, return empty array
        if ($userNftCount == 0) {
            error_log("No NFTs found for user $userId");
            return [];
        }
        
        // Main query to get NFTs with owner information
        $query = "
            SELECT 
                n.nft_id,
                n.name,
                n.description,
                n.image_url,
                n.price_luck,
                n.listed_for_sale,
                n.listing_date,
                n.current_owner_user_id,
                n.creator_user_id,
                n.collection_id,
                c.name as collection_name,
                u.username as owner_username,
                u.email as owner_email
            FROM luck_wallet_nfts n
            LEFT JOIN luck_wallet_users u ON n.current_owner_user_id = u.user_id
            LEFT JOIN luck_wallet_nft_collections c ON n.collection_id = c.collection_id
            WHERE n.current_owner_user_id = ?
            ORDER BY n.listing_date DESC, n.nft_id DESC
        ";
        
        error_log("Executing query: " . str_replace("\n", " ", $query));
        $stmt = $pdo->prepare($query);
        
        if (!$stmt) {
            $error = $pdo->errorInfo();
            throw new PDOException("Failed to prepare statement: " . ($error[2] ?? 'Unknown error'));
        }
        
        error_log("Executing with user ID: " . $userId);
        $stmt->execute([$userId]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Query returned " . count($result) . " NFTs");
        
        // Log first NFT for debugging
        if (!empty($result)) {
            error_log("First NFT data: " . print_r($result[0], true));
        }
        
        // Ensure all required fields have default values
        $formatted = [];
        foreach ($result as $nft) {
            $formatted[] = [
                'nft_id' => (int)($nft['nft_id'] ?? 0),
                'name' => $nft['name'] ?? 'Unnamed NFT',
                'description' => $nft['description'] ?? '',
                'image_url' => $nft['image_url'] ?? 'img/default-nft.svg',
                'price_luck' => isset($nft['price_luck']) ? (float)$nft['price_luck'] : 0,
                'listed_for_sale' => (bool)($nft['listed_for_sale'] ?? false),
                'listing_date' => $nft['listing_date'] ?? null,
                'current_owner_user_id' => (int)($nft['current_owner_user_id'] ?? 0),
                'creator_user_id' => (int)($nft['creator_user_id'] ?? 0),
                'collection_id' => isset($nft['collection_id']) ? (int)$nft['collection_id'] : null,
                'collection_name' => $nft['collection_name'] ?? null,
                'owner_username' => $nft['owner_username'] ?? 'Unknown',
                'owner_email' => $nft['owner_email'] ?? ''
            ];
        }
        
        error_log("Returning " . count($formatted) . " formatted NFTs");
        return $formatted;
        
    } catch (PDOException $e) {
        $error = "Database Error in getNFTsByOwner: " . $e->getMessage() . "\n" . $e->getTraceAsString();
        error_log($error);
        return [];
    } catch (Exception $e) {
        $error = "Unexpected Error in getNFTsByOwner: " . $e->getMessage() . "\n" . $e->getTraceAsString();
        error_log($error);
        return [];
    }
}

/**
 * Get NFT by ID
 * @param PDO $pdo Database connection
 * @param int $nftId NFT ID
 * @return array|null NFT data or null if not found
 */
function getNFTById($pdo, $nftId) {
    try {
        $stmt = $pdo->prepare("
            SELECT n.*, u.username as owner_username, 
                   c.username as creator_username
            FROM luck_wallet_nfts n
            LEFT JOIN luck_wallet_users u ON n.current_owner_user_id = u.user_id
            LEFT JOIN luck_wallet_users c ON n.creator_user_id = c.user_id
            WHERE n.nft_id = ?
        ");
        $stmt->execute([$nftId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching NFT: " . $e->getMessage());
        return null;
    }
}

/**
 * Get all NFTs listed for sale
 * @param PDO $pdo Database connection
 * @return array Array of NFTs
 */
function getNFTsForSale($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT n.*, u.username as owner_username, 
                   c.username as creator_username
            FROM luck_wallet_nfts n
            LEFT JOIN luck_wallet_users u ON n.current_owner_user_id = u.user_id
            LEFT JOIN luck_wallet_users c ON n.creator_user_id = c.user_id
            WHERE n.listed_for_sale = 1
            ORDER BY n.listing_date DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching NFTs for sale: " . $e->getMessage());
        return [];
    }
}

/**
 * Format price with LUCK token
 * @param float $price Price in LUCK
 * @return string Formatted price
 */
function formatPrice($price) {
    return number_format($price, 0, '.', ',') . ' LUCK';
}

/**
 * Format date to a readable format
 * @param string $date Date string
 * @return string Formatted date
 */
function formatDate($date) {
    return date('M j, Y', strtotime($date));
}

/**
 * Get the relative time since the NFT was listed
 * @param string $date Date string
 * @return string Relative time (e.g., "2 days ago")
 */
function getTimeAgo($date) {
    $time = strtotime($date);
    $time_difference = time() - $time;

    if ($time_difference < 60) {
        return 'just now';
    }

    $condition = [
        12 * 30 * 24 * 60 * 60  =>  'year',
        30 * 24 * 60 * 60       =>  'month',
        24 * 60 * 60            =>  'day',
        60 * 60                 =>  'hour',
        60                      =>  'minute',
        1                       =>  'second'
    ];

    foreach ($condition as $secs => $str) {
        $d = $time_difference / $secs;
        if ($d >= 1) {
            $t = round($d);
            return $t . ' ' . $str . ($t > 1 ? 's' : '') . ' ago';
        }
    }
}
?>
