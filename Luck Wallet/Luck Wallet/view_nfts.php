<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'db_connect.php';

try {
    // Get all NFTs
    $nfts = $pdo->query("SELECT * FROM luck_wallet_nfts")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h1>All NFTs in Database (" . count($nfts) . ")</h1>";
    
    if (count($nfts) > 0) {
        echo "<div style='display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px;'>";
        foreach ($nfts as $nft) {
            $imageUrl = !empty($nft['image_url']) ? $nft['image_url'] : 'img/default-nft.svg';
            echo "<div style='border: 1px solid #ddd; padding: 15px; border-radius: 8px;'>";
            echo "<img src='$imageUrl' style='max-width: 100%; height: 200px; object-fit: contain;' onerror=\"this.src='img/default-nft.svg'\">";
            echo "<h3>" . htmlspecialchars($nft['name'] ?? 'Unnamed NFT') . "</h3>";
            echo "<p><strong>ID:</strong> " . htmlspecialchars($nft['nft_id']) . "</p>";
            echo "<p><strong>Owner ID:</strong> " . htmlspecialchars($nft['current_owner_user_id']) . "</p>";
            echo "<p><strong>Price:</strong> " . number_format($nft['price_luck'] ?? 0, 2) . " LUCK</p>";
            echo "<p><strong>Listed:</strong> " . ($nft['listed_for_sale'] ? 'Yes' : 'No') . "</p>";
            echo "</div>";
        }
        echo "</div>";
    } else {
        echo "<p>No NFTs found in the database.</p>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>

<style>
    body {
        font-family: Arial, sans-serif;
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }
    h1 {
        color: #333;
    }
    .nft-card {
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        overflow: hidden;
    }
</style>
