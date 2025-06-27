<?php
// api_get_recent_collections.php
require_once __DIR__ . '/../db_connect.php';

header('Content-Type: application/json');

try {
    $query = "
        SELECT 
            c.collection_id,
            c.name,
            c.logo_url,
            MIN(n.price_luck) as floor_price,
            COUNT(n.nft_id) as nft_count
        FROM 
            luck_wallet_nft_collections c
        LEFT JOIN 
            luck_wallet_nfts n ON c.collection_id = n.collection_id
        WHERE 
            n.listed_for_sale = 1
        GROUP BY 
            c.collection_id
        ORDER BY 
            c.created_at DESC
        LIMIT 5
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $collections = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response = [
        'success' => true,
        'data' => array_map(function($collection) {
            return [
                'id' => $collection['collection_id'],
                'name' => $collection['name'],
                'logo' => $collection['logo_url'],
                'floor_price' => $collection['floor_price'] ? number_format($collection['floor_price'], 2) . ' LUCK' : 'N/A',
                'nft_count' => (int)$collection['nft_count']
            ];
        }, $collections)
    ];

    echo json_encode($response);
} catch (Exception $e) {
    error_log('Error fetching recent collections: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch collections'
    ]);
}
