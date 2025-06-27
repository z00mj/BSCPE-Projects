<?php
// Database configuration
$db_host = '127.0.0.1';
$db_name = 'login';
$db_user = 'root';
$db_pass = '';

// Initialize variables
$collections = [];
$collection = null;
$nfts = [];
$search_query = $_GET['search'] ?? '';
$collection_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Create database connection
try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get all collections with NFT count
$sql = "SELECT c.*, 
               (SELECT COUNT(*) FROM luck_wallet_nfts WHERE collection_id = c.collection_id) as nft_count
        FROM luck_wallet_nft_collections c";

$params = [];

// Add search condition if search query exists
if (!empty($search_query)) {
    $sql .= " WHERE c.name LIKE ? OR c.description LIKE ?";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$sql .= " ORDER BY c.name";

// Fetch collections
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$collections = $stmt->fetchAll();

// If a specific collection is requested
if ($collection_id > 0) {
    // Get collection details
    $stmt = $pdo->prepare("
        SELECT c.*, u.username as creator_name
        FROM luck_wallet_nft_collections c
        LEFT JOIN luck_wallet_users u ON c.creator_user_id = u.user_id
        WHERE c.collection_id = ?
    ");
    $stmt->execute([$collection_id]);
    $collection = $stmt->fetch();

    if ($collection) {
        // Get NFTs in this collection
        $stmt = $pdo->prepare("
            SELECT n.*, u.username as owner_username
            FROM luck_wallet_nfts n
            LEFT JOIN luck_wallet_users u ON n.current_owner_user_id = u.user_id
            WHERE n.collection_id = ?
            ORDER BY n.listing_date DESC
        ");
        $stmt->execute([$collection_id]);
        $nfts = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $collection ? htmlspecialchars($collection['name']) : 'Collections'; ?> - Luck Wallet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .collection-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 20px;
            cursor: pointer;
        }
        .collection-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .collection-logo {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        .nft-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        .nft-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .nft-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        .back-link {
            color: #6f42c1;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <?php if ($collection): ?>
            <!-- Collection Detail View -->
            <a href="?" class="back-link">
                <i class="fas fa-arrow-left me-2"></i> Back to Collections
            </a>
            
            <!-- Collection Header -->
            <div class="row mb-5">
                <div class="col-md-3 text-center">
                    <img src="<?php echo htmlspecialchars($collection['logo_url']); ?>" 
                         class="img-fluid rounded shadow" 
                         alt="<?php echo htmlspecialchars($collection['name']); ?>"
                         style="max-height: 200px;">
                </div>
                <div class="col-md-9">
                    <h1 class="display-5 fw-bold"><?php echo htmlspecialchars($collection['name']); ?></h1>
                    <p class="lead"><?php echo nl2br(htmlspecialchars($collection['description'])); ?></p>
                    <div class="d-flex gap-3">
                        <div>
                            <div class="text-muted small">Items</div>
                            <div class="h4 mb-0"><?php echo count($nfts); ?></div>
                        </div>
                        <?php if (!empty($collection['creator_name'])): ?>
                            <div>
                                <div class="text-muted small">Creator</div>
                                <div class="h6 mb-0"><?php echo htmlspecialchars($collection['creator_name']); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- NFTs Grid -->
            <h3 class="mb-4">NFTs in this Collection</h3>
            <?php if (empty($nfts)): ?>
                <div class="alert alert-info">No NFTs found in this collection.</div>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
                    <?php foreach ($nfts as $nft): ?>
                        <div class="col">
                            <div class="card h-100 nft-card">
                                <img src="<?php echo htmlspecialchars($nft['image_url']); ?>" 
                                     class="card-img-top nft-image" 
                                     alt="<?php echo htmlspecialchars($nft['name'] ?? 'NFT #' . $nft['nft_id']); ?>">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <?php 
                                        if (!empty($nft['name'])) {
                                            echo htmlspecialchars($nft['name']);
                                        } else {
                                            echo htmlspecialchars($collection['name']);
                                        }
                                        ?>
                                    </h5>
                                    <p class="text-muted small">ID: <?php echo $nft['nft_id']; ?></p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-primary fw-bold">
                                            <?php echo number_format($nft['price_luck'], 2); ?> LUCK
                                        </span>
                                        <span class="badge bg-<?php echo $nft['listed_for_sale'] ? 'success' : 'secondary'; ?>">
                                            <?php echo $nft['listed_for_sale'] ? 'For Sale' : 'Not Listed'; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Collections List View -->
            <div class="row mb-4">
                <div class="col-md-8 mx-auto">
                    <form action="" method="get" class="mb-4">
                        <div class="input-group">
                            <input type="text" 
                                   name="search" 
                                   class="form-control form-control-lg" 
                                   placeholder="Search collections..."
                                   value="<?php echo htmlspecialchars($search_query); ?>">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                            <?php if (!empty($search_query)): ?>
                                <a href="?" class="btn btn-outline-secondary">
                                    Clear
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <h2 class="mb-4">All Collections</h2>
            
            <?php if (empty($collections)): ?>
                <div class="alert alert-info">No collections found.</div>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
                    <?php foreach ($collections as $col): ?>
                        <div class="col">
                            <a href="?id=<?php echo $col['collection_id']; ?>" class="text-decoration-none text-dark">
                                <div class="card h-100 collection-card">
                                    <img src="<?php echo htmlspecialchars($col['logo_url']); ?>" 
                                         class="card-img-top collection-logo" 
                                         alt="<?php echo htmlspecialchars($col['name']); ?>">
                                    <div class="card-body">
                                        <h5 class="card-title mb-1"><?php echo htmlspecialchars($col['name']); ?></h5>
                                        <p class="text-muted small mb-2">
                                            <?php echo (int)$col['nft_count']; ?> items
                                        </p>
                                        <p class="card-text small text-truncate">
                                            <?php echo htmlspecialchars(substr($col['description'] ?? 'No description', 0, 100)); ?>
                                            <?php echo strlen($col['description'] ?? '') > 100 ? '...' : ''; ?>
                                        </p>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
