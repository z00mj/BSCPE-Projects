<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Store the current URL in session for redirect after login if needed
$_SESSION['redirect_url'] = 'nft.php';
?>

<script>
// Function to open the login modal - defined in global scope at the very beginning
window.openLoginModal = function() {
    console.log('openLoginModal called');
    const modal = document.getElementById('loginModal');
    if (modal) {
        console.log('Modal found, showing...');
        modal.style.display = 'flex';
        modal.style.opacity = '1';
        modal.style.zIndex = '9999';
        document.body.style.overflow = 'hidden';
        
        // Make sure login view is shown
        const loginView = document.getElementById('loginView');
        if (loginView) {
            loginView.style.display = 'block';
        }
        
        // Hide other views
        document.querySelectorAll('.modal-body:not(#loginView)').forEach(view => {
            view.style.display = 'none';
        });
        
        // Update modal title
        const title = document.querySelector('.modal-header h2');
        if (title) {
            title.textContent = 'Login to Your Account';
        }
        
        return true;
    }
    console.error('Login modal not found');
    return false;
};

// Make toggleDropdown globally available
window.toggleDropdown = function(event) {
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }
    
    const dropdown = document.getElementById('walletDropdown');
    if (dropdown) {
        dropdown.classList.toggle('show');
        
        // Update aria-expanded state
        const button = document.querySelector('.wallet-btn');
        if (button) {
            const isExpanded = button.getAttribute('aria-expanded') === 'true';
            button.setAttribute('aria-expanded', !isExpanded);
        }
    }
};

// Handle logout functionality
window.handleLogout = async function(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    // Get current URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const collectionId = urlParams.get('collection_id');
    
    try {
        // Call the logout API
        const response = await fetch('api/logout.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'Cache-Control': 'no-cache, no-store, must-revalidate',
                'Pragma': 'no-cache',
                'Expires': '0'
            },
            credentials: 'same-origin',
            body: 'logout=true',
            cache: 'no-store'
        });

        const result = await response.json();
        
        if (result.success) {
            // Build the redirect URL with the collection_id if it exists
            let redirectUrl = window.location.pathname;
            
            // Add collection_id if it exists
            if (collectionId) {
                redirectUrl += `?collection_id=${collectionId}`;
            }
            
            // Add timestamp to prevent caching
            redirectUrl += (collectionId ? '&' : '?') + 't=' + new Date().getTime();
            
            // Force a hard reload to clear any cached data
            window.location.href = redirectUrl;
            window.location.reload(true);
        } else {
            console.error('Logout failed:', result.message);
            alert('Logout failed: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error during logout:', error);
        alert('An error occurred during logout. Please try again.');
    }
};


</script>
<?php
session_start();

// Include database connection
require_once __DIR__ . '/../db_connect.php';

// Initialize variables
$isLoggedIn = false;
$walletAddress = '';
$username = '';
$collection = null;
$nfts = [];
$isFavorite = false;

// Check if PDO connection is available
if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('Database connection failed. Please check your configuration.');
}

// No collection_id required - we'll show all NFTs
$collectionId = isset($_GET['collection_id']) ? intval($_GET['collection_id']) : null;

try {
    // Check if user is logged in
    if (isset($_SESSION['user_id'])) {
        $isLoggedIn = true;
        $userId = $_SESSION['user_id'];
        
        try {
            // Get user data
            $stmt = $pdo->prepare("SELECT username, wallet_address FROM luck_wallet_users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $walletAddress = $user['wallet_address'] ?? '';
                $username = $user['username'] ?? '';
            }
            
            // Check if user has any favorites
            $favStmt = $pdo->prepare("SELECT 1 FROM luck_wallet_user_favorites WHERE user_id = ? LIMIT 1");
            $favStmt->execute([$userId]);
            $isFavorite = $favStmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            throw new Exception("Error accessing user data");
        }
    }

    // No need to fetch a specific collection since we're showing all NFTs
    $collection = [
        'name' => 'All NFTs',
        'description' => 'Browse all available NFTs in the marketplace',
        'banner_image_url' => 'path/to/default-banner.jpg',
        'logo_url' => 'path/to/default-logo.png',
        'nft_count' => 0,
        'owner_count' => 0,
        'floor_price' => 0,
        'total_volume' => 0
    ];

    try {
        // Get all NFTs from all collections
        $query = "
            SELECT 
                n.nft_id as id,
                n.name,
                n.description,
                n.image_url,
                n.price_luck as price,
                n.current_owner_user_id as owner_id,
                u.wallet_address as owner_wallet_address,
                'default-avatar.png' as owner_avatar,
                c.name as collection_name,
                c.logo_url as collection_logo,
                (SELECT COUNT(*) FROM luck_wallet_user_favorites WHERE nft_id = n.nft_id) as like_count,
                (SELECT 1 FROM luck_wallet_user_favorites WHERE user_id = ? AND nft_id = n.nft_id) as user_liked,
                n.listing_date as created_at
            FROM luck_wallet_nfts n
            JOIN luck_wallet_nft_collections c ON n.collection_id = c.collection_id
            LEFT JOIN luck_wallet_users u ON n.current_owner_user_id = u.user_id
            ORDER BY n.listing_date DESC
        ";
        
        $nftsStmt = $pdo->prepare($query);
        $nftsStmt->execute([$isLoggedIn ? $userId : 0]);
        $nfts = [];
        
        while ($nft = $nftsStmt->fetch(PDO::FETCH_ASSOC)) {
            $nft['is_favorite'] = $isLoggedIn && !empty($nft['user_liked']);
            $nft['owner_username'] = $nft['owner_username'] ?? 'Unknown';
            $nfts[] = $nft;
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        throw new Exception("Error accessing NFT data");
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("An error occurred: " . $e->getMessage() . "<br>Please check the error log for more details.");
} catch (Exception $e) {
    error_log("Error in collection.php: " . $e->getMessage());
    die("An error occurred: " . $e->getMessage() . "<br>Please check the error log for more details.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All NFTs - LUCK Marketplace</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="styles/nft.css">
    <style>
        /* Sidebar Hover Effect */
        .sidebar-left {
            transition: width 0.3s ease, transform 0.3s ease;
            overflow: hidden;
        }
        
        .sidebar-logo-container {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            height: 70px;
            padding: 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 0;
            box-sizing: border-box;
        }
        
        .sidebar-logo {
            width: 60px; /* Fixed width */
            height: auto;
            object-fit: contain;
            margin: 0 auto; /* Center the logo */
            display: block; /* Ensure it's a block element */
        }
        
        .sidebar-left .sidebar-text {
            opacity: 0;
            white-space: nowrap;
            transition: opacity 0.2s ease 0.1s;
        }
        
        .sidebar-left .material-icons {
            transition: margin-right 0.3s ease;
        }
        
        @media (min-width: 1025px) {
            .sidebar-left:hover {
                width: 250px;
            }
            
            .sidebar-left:hover .sidebar-text {
                opacity: 1;
            }
            
            .sidebar-left:hover .material-icons {
                margin-right: 16px;
            }
            
            .sidebar-left:hover .sidebar-logo-container {
                justify-content: flex-start;
                padding-left: 20px;
            }
            
            .sidebar-left:hover .sidebar-logo {
                width: 60px; /* Keep the same size on hover */
            }
        }
        
        /* Ensure content doesn't shift when sidebar expands */
        .container {
            transition: margin-left 0.3s ease;
        }
        
        @media (max-width: 1024px) {
            .sidebar-left {
                transform: translateX(-100%);
                width: 250px;
            }
            
            .sidebar-left.mobile-visible {
                transform: translateX(0);
            }
            
            .sidebar-left .sidebar-text {
                opacity: 1;
            }
            
            .sidebar-left .material-icons {
                margin-right: 16px;
            }
            
            .sidebar-left .sidebar-logo-container {
                justify-content: flex-start;
                padding-left: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="main-content-overlay" id="mainContentOverlay"></div>
    <aside class="sidebar-left" id="sidebarLeft">
        <a href="#" class="sidebar-logo-container" id="mobileMenuBtn">
            <img src="logo.png" alt="LUCK MARKETPLACE" class="sidebar-logo">
        </a>
        <a href="marketplace.php" class="sidebar-item"><span class="material-icons">home</span> <span class="sidebar-text">Home</span></a>
        <a href="profile.php" class="sidebar-item"><span class="material-icons">person</span> <span class="sidebar-text">Profile</span></a>
        <a href="nft.php" class="sidebar-item active"><span class="material-icons">image</span> <span class="sidebar-text">NFTs</span></a>
        <a href="../index.html" class="sidebar-item" id="luckWebsiteBtn">
            <span class="material-icons">public</span>
            <span class="sidebar-text">Luck Website</span>
        </a>
    </aside>

    <header class="header">
        <div class="header-content">
            <div class="header-brand">
                <button class="mobile-sidebar-toggle-btn" id="mobileSidebarToggleBtn">
                    <span class="material-icons">menu</span>
                </button>
                <h1 class="header-title">LUCK MARKETPLACE</h1>
            </div>
            <div class="header-right">
                <div class="search-bar" id="searchBar">
                    <span class="material-icons search-icon">search</span>
                    <input type="text" id="searchInput" placeholder="Search NFTs..." autocomplete="off">
                    <div class="search-results" id="searchResults"></div>
                </div>
                <?php if ($isLoggedIn && !empty($walletAddress)): ?>
                    <div class="wallet-dropdown">
                        <button class="wallet-btn" onclick="toggleDropdown()" aria-haspopup="true" aria-expanded="false">
                            <span class="material-icons">account_balance_wallet</span>
                            <span class="wallet-address" title="<?php echo htmlspecialchars($walletAddress); ?>">
                                <?php 
                                // Format the wallet address for better readability
                                $formattedAddress = substr($walletAddress, 0, 6) . '...' . substr($walletAddress, -4);
                                echo htmlspecialchars($formattedAddress);
                                ?>
                            </span>
                            <span class="material-icons" style="font-size: 18px; margin-left: 4px;">expand_more</span>
                        </button>
                        <div id="walletDropdown" class="dropdown-content" role="menu">
                            <div style="padding: 12px 16px; border-bottom: 1px solid #2a2a3c;">
                                <div style="font-size: 12px; color: #8a8a9e; margin-bottom: 4px;">Connected with Web3</div>
                                <div style="font-family: 'Roboto Mono', monospace; font-size: 13px; word-break: break-all;">
                                    <?php echo htmlspecialchars($walletAddress); ?>
                                </div>
                            </div>
                            <a href="profile.php" role="menuitem">
                                <span class="material-icons">person_outline</span>
                                <span>My Profile</span>
                            </a>
                            <a href="/Luck%20Wallet/dashboard.php" role="menuitem">
                                <span class="material-icons">account_balance_wallet</span>
                                <span>My Wallet</span>
                            </a>
                            <a href="#" onclick="handleLogout(event); return false;" role="menuitem" style="color: #ff6b6b;">
                                <span class="material-icons" style="color: #ff6b6b;">logout</span>
                                <span>Disconnect</span>
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <button class="connect-wallet" id="connectWallet">
                        <span class="material-icons">account_balance_wallet</span>
                        <span>Connect Wallet</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="container" id="main-container">
        <main class="main-content">

            <section class="all-nfts">
                <div class="section-header">
                    <h2 style="color: #e0e0e0; font-weight: 500; letter-spacing: 0.5px; margin: 0 0 20px 0; font-size: 1.5em;"><?php echo htmlspecialchars($collection['name']); ?> Collection</h2>
                    <div class="sort-options">
                        <span>Sort by:</span>
                        <select id="sortBy">
                            <option value="recent">Most Recent</option>
                            <option value="price-low">Price: Low to High</option>
                            <option value="price-high">Price: High to Low</option>
                            <option value="likes">Most Liked</option>
                        </select>
                    </div>
                </div>
                <div class="nft-grid" id="nftGrid">
                    <?php if (empty($nfts)): ?>
                        <div class="no-nfts">
                            <p>No NFTs found in this collection yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($nfts as $nft): ?>
                            <div class="nft-card-small" 
                                 data-nft-id="<?php echo $nft['id']; ?>"
                                 data-created="<?php echo strtotime($nft['created_at'] ?? 'now'); ?>"
                                 data-likes="<?php echo $nft['like_count'] ?? 0; ?>"
                                 data-price="<?php echo $nft['price'] ?? 0; ?>">
                                <div class="nft-card-inner">
                                    <div class="nft-card-front">
                                        <div class="nft-logo-container">
                                            <?php 
                                                $collectionLogoPath = !empty($nft['collection_logo']) ? 
                                                    (strpos($nft['collection_logo'], 'http') === 0 ? $nft['collection_logo'] : '../' . ltrim($nft['collection_logo'], '/')) : 
                                                    'default-logo.png';
                                            ?>
                                            <img src="<?php echo htmlspecialchars($collectionLogoPath); ?>" 
                                                 alt="<?php echo htmlspecialchars($nft['collection_name']); ?>"
                                                 onerror="this.onerror=null; this.src='default-logo.png'">
                                        </div>
                                        <button class="favorite-button" data-nft-id="<?php echo $nft['id']; ?>">
                                            <span class="material-icons"><?php echo !empty($nft['user_liked']) ? 'star' : 'star_border'; ?></span>
                                        </button>
                                        <div class="nft-image-container">
                                            <?php 
                                                $imagePath = !empty($nft['image_url']) ? 
                                                    (strpos($nft['image_url'], 'http') === 0 ? $nft['image_url'] : '../' . ltrim($nft['image_url'], '/')) : 
                                                    'default-nft.png';
                                            ?>
                                            <img src="<?php echo htmlspecialchars($imagePath); ?>" 
                                                 alt="<?php echo htmlspecialchars($nft['name']); ?>" 
                                                 class="nft-image"
                                                 onerror="this.onerror=null; this.src='default-nft.png'">
                                        </div>
                                        <div class="nft-id-display">
                                            <span>NFT ID: <?php echo htmlspecialchars($nft['id']); ?></span>
                                        </div>
                                        <div class="info">
                                            <h3><?php echo htmlspecialchars($nft['collection_name']); ?></h3>
                                            <p class="price"><?php echo number_format($nft['price'], 3); ?> LUCK</p>
                                        </div>
                                    </div>
                                    <div class="nft-card-back">
                                        <p><strong>NFT ID:</strong> #<?php echo $nft['id']; ?></p>
                                        <p><strong>Owner:</strong> <?php echo htmlspecialchars($nft['owner_wallet_address'] ?? 'Unknown'); ?></p>
                                        <?php if ($isLoggedIn && $nft['owner_id'] == $userId): ?>
                                            <button class="buy-button owned-button" disabled>Owned</button>
                                        <?php else: ?>
                                            <button class="buy-button" data-nft-id="<?php echo $nft['id']; ?>" 
                                                onclick="event.stopPropagation(); 
                                                event.preventDefault();
                                                <?php if (!$isLoggedIn): ?>
                                                    const loginModal = document.getElementById('loginModal');
                                                    if (loginModal) {
                                                        loginModal.style.display = 'flex';
                                                        loginModal.classList.add('active');
                                                        loginModal.style.zIndex = '9999';
                                                        loginModal.style.opacity = '1';
                                                    }
                                                    return false;
                                                <?php else: ?>
                                                    console.log('Initiating purchase for NFT ID: <?php echo $nft['id']; ?>');
                                                    const purchaseModal = document.getElementById('purchaseConfirmationModal');
                                                    if (!purchaseModal) return;
                                                    
                                                    // Reset modal state
                                                    const purchaseForm = document.getElementById('purchaseForm');
                                                    const purchaseSuccess = document.getElementById('purchaseSuccess');
                                                    const closeAfterBtn = document.getElementById('closeAfterPurchaseBtn');
                                                    const cancelBtn = document.getElementById('cancelPurchaseBtn');
                                                    const confirmBtn = document.getElementById('confirmPurchaseBtn');
                                                    const insufficientBalance = document.getElementById('insufficientBalance');
                                                    
                                                    purchaseForm.style.display = 'block';
                                                    purchaseSuccess.style.display = 'none';
                                                    closeAfterBtn.style.display = 'none';
                                                    cancelBtn.style.display = 'inline-block';
                                                    confirmBtn.disabled = true;
                                                    insufficientBalance.style.display = 'none';
                                                    
                                                    // Show and position modal
                                                    purchaseModal.style.display = 'flex';
                                                    purchaseModal.classList.add('active');
                                                    purchaseModal.style.zIndex = '9999';
                                                    purchaseModal.style.opacity = '1';
                                                    
                                                    // Set NFT details
                                                    const nftName = '<?php echo addslashes($nft['name']); ?>';
                                                    const nftPrice = parseFloat('<?php echo $nft['price'] ?? 0; ?>');
                                                    const formattedPrice = nftPrice.toFixed(2);
                                                    
                                                    document.getElementById('purchaseItemName').textContent = nftName;
                                                    document.getElementById('purchaseItemPrice').textContent = formattedPrice + ' LUCKY';
                                                    document.getElementById('purchaseTotal').textContent = formattedPrice + ' LUCKY';
                                                    
                                                    // Set data attributes for purchase
                                                    confirmBtn.setAttribute('data-nft-id', '<?php echo $nft['id']; ?>');
                                                    confirmBtn.setAttribute('data-price', nftPrice);
                                                    
                                                    // Fetch user balance
                                                    fetch('api/get_balance.php')
                                                        .then(response => response.json())
                                                        .then(data => {
                                                            if (data.success) {
                                                                const balance = parseFloat(data.balance);
                                                                document.getElementById('userBalance').textContent = balance.toFixed(2) + ' LUCKY';
                                                                
                                                                // Enable/disable purchase button based on balance
                                                                if (balance >= nftPrice) {
                                                                    confirmBtn.disabled = false;
                                                                } else {
                                                                    document.getElementById('insufficientBalance').style.display = 'block';
                                                                }
                                                            } else {
                                                                console.error('Failed to load balance:', data.message);
                                                                document.getElementById('userBalance').textContent = 'Error loading balance';
                                                            }
                                                        })
                                                        .catch(error => {
                                                            console.error('Error fetching balance:', error);
                                                            document.getElementById('userBalance').textContent = 'Error';
                                                        });
                                                    
                                                    // Scroll to top of modal
                                                    purchaseModal.scrollTo(0, 0);
                                                    
                                                    return false;
                                                <?php endif; ?>">
                                                <?php echo $isLoggedIn ? 'Buy Now' : 'Login to Buy'; ?>
                                            </button>
                                        <?php endif; ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>

    <!-- Login Modal Structure -->
    <div id="loginModal" class="modal">
        <div class="modal-content">
            <div class="modal-header" style="position: relative;">
                <span class="close-button" style="position: absolute; right: 20px; top: 20px; font-size: 24px; cursor: pointer;">&times;</span>
                <h2 style="color: #e0e0e0; font-weight: 500; letter-spacing: 0.5px; text-align: center; width: 100%; margin: 0; padding: 0 40px;">Login to Your Account</h2>
            </div>
            <!-- Login Form (Default View) -->
            <div class="modal-body" id="loginView">
                <p style="text-align: center; margin-bottom: 20px; color: #e0e0e0;">
                    Login using your Luck Wallet Account
                </p>
                <form id="loginForm">
                    <div class="input-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" class="form-input" required>
                    </div>
                    <div class="input-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" class="form-input" required>
                    </div>
                    <button type="submit" class="login-submit-button">Login with Email</button>
                </form>
                
                <div class="divider">
                    <span>or</span>
                </div>
                
                <button type="button" id="showLuckyTimeLogin" class="luckytime-login-button">
                    <span class="material-icons">casino</span>
                    <span>Login with LuckyTime</span>
                </button>
                
                <div class="modal-footer-text" style="text-align: center; margin-top: 15px;">
                    <a href="#" class="switch-to-signup" style="color: #00BFBF; text-decoration: none;">Create account</a>
                    <span style="margin: 0 10px; color: #666;">|</span>
                    <a href="#" class="forgot-password" style="color: #00BFBF; text-decoration: none;">Forgot Password</a>
                </div>
            </div>

            <!-- Signup Form (Hidden by default) -->
            <div class="modal-body" id="signupView" style="display: none;">
                <p style="text-align: center; margin-bottom: 20px; color: #e0e0e0;">
                    Create Your Account
                </p>
                <form id="signupForm">
                    <div class="input-group">
                        <label for="signupName">Full Name</label>
                        <input type="text" id="signupName" class="form-input" required>
                    </div>
                    <div class="input-group">
                        <label for="signupEmail">Email</label>
                        <input type="email" id="signupEmail" class="form-input" required>
                    </div>
                    <div class="input-group">
                        <label for="signupPassword">Password</label>
                        <input type="password" id="signupPassword" class="form-input" required>
                    </div>
                    <div class="input-group">
                        <label for="confirmPassword">Confirm Password</label>
                        <input type="password" id="confirmPassword" class="form-input" required>
                    </div>
                    <button type="submit" class="login-submit-button">Create Account</button>
                </form>
                <div class="modal-footer-text" style="text-align: center; margin-top: 15px;">
                    <span>Already have an account? </span>
                    <a href="#" id="backToLogin" style="color: #00BFBF; text-decoration: none;">Login here</a>
                </div>
            </div>

            <!-- LuckyTime Login Form (Hidden by default) -->
            <div class="modal-body" id="luckyTimeView" style="display: none;">
                <p style="text-align: center; margin-bottom: 20px; color: #e0e0e0;">
                    Login with LuckyTime
                </p>
                <form id="luckyTimeLoginForm">
                    <div class="input-group">
                        <label for="luckyTimeEmail">Email or Username</label>
                        <input type="text" id="luckyTimeEmail" class="form-input" required>
                    </div>
                    <div class="input-group">
                        <label for="luckyTimePassword">Password</label>
                        <input type="password" id="luckyTimePassword" class="form-input" required>
                    </div>
                    <button type="submit" class="login-submit-button">Login with LuckyTime</button>
                </form>
                <div class="modal-footer-text" style="text-align: center; margin-top: 15px;">
                    <a href="#" class="back-to-login" style="color: #00BFBF; text-decoration: none;">
                        &larr; Back to Login
                    </a>
                </div>
            </div>

            <!-- Forgot Password Form (Hidden by default) -->
            <div class="modal-body" id="forgotView" style="display: none;">
                <p style="text-align: center; margin-bottom: 20px; color: #e0e0e0;">
                    Reset Your Password
                </p>
                <p style="text-align: center; margin-bottom: 20px; color: #aaa; font-size: 0.9em;">
                    Enter your email and we'll send you a link to reset your password.
                </p>
                <form id="forgotForm">
                    <div class="input-group">
                        <label for="forgotEmail">Email</label>
                        <input type="email" id="forgotEmail" class="form-input" required>
                    </div>
                    <div class="input-group">
                        <label for="newPassword">New Password</label>
                        <input type="password" id="newPassword" class="form-input" required>
                    </div>
                    <div class="input-group">
                        <label for="confirmNewPassword">Confirm New Password</label>
                        <input type="password" id="confirmNewPassword" class="form-input" required>
                    </div>
                    <button type="submit" class="login-submit-button">Reset Password</button>
                </form>
                <div class="modal-footer-text" style="text-align: center; margin-top: 15px;">
                    <a href="#" id="backToLogin2" style="color: #00BFBF; text-decoration: none;">
                        &larr; Back to Login
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Purchase Confirmation Modal -->
    <div id="purchaseConfirmationModal" class="modal">
        <div class="modal-content" style="max-width: 450px; position: relative;">
            <div class="modal-header">
                <span class="close-button" id="closePurchaseConfirmationModal" style="position: absolute; right: 20px; top: 20px; font-size: 24px; cursor: pointer;">&times;</span>
                <h2 style="color: #e0e0e0; font-weight: 500; letter-spacing: 0.5px; text-align: center; width: 100%; margin: 0; padding: 0 40px;">Confirm Purchase</h2>
            </div>
            <div class="modal-body" style="text-align: center; padding: 20px;">
                <div id="purchaseForm" style="margin-bottom: 25px;">
                    <i class="material-icons" style="font-size: 60px; color: #00BFBF; margin-bottom: 15px;">shopping_cart</i>
                    <h3 style="margin: 10px 0; color: #e0e0e0;">Complete Your Purchase</h3>
                    <p style="color: #b0b0b0; margin-bottom: 5px;">You are about to purchase:</p>
                    <p id="purchaseItemName" style="font-size: 1.2em; font-weight: 600; margin: 5px 0 20px; color: #ffffff;"></p>
                    
                    <div style="background: #1e1e1e; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: left;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span style="color: #b0b0b0;">Your Balance:</span>
                            <span id="userBalance" style="font-weight: 600; color: #ffffff;">Loading...</span>
                        </div>
                        <div style="height: 1px; background: #333; margin: 10px 0;"></div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span style="color: #b0b0b0;">Price:</span>
                            <span id="purchaseItemPrice" style="font-weight: 600; color: #ffffff;"></span>
                        </div>
                        <div style="height: 1px; background: #333; margin: 10px 0;"></div>
                        <div style="display: flex; justify-content: space-between; font-size: 1.1em; margin-top: 15px;">
                            <span style="color: #e0e0e0;">Total:</span>
                            <span id="purchaseTotal" style="font-weight: 600; color: #00BFBF;"></span>
                        </div>
                        <div id="insufficientBalance" style="color: #ff6b6b; margin-top: 15px; display: none;">
                            <i class="material-icons" style="vertical-align: middle; font-size: 18px;">error_outline</i>
                            <span>Insufficient balance to complete this purchase</span>
                        </div>
                    </div>
                </div>
                
                <div id="purchaseSuccess" style="display: none; margin: 20px 0;">
                    <div style="background: #1e3a1e; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                        <i class="material-icons" style="font-size: 60px; color: #4caf50; margin-bottom: 15px;">check_circle</i>
                        <h3 style="margin: 10px 0; color: #e0e0e0;">Purchase Successful!</h3>
                        <p style="color: #b0b0b0; margin-bottom: 15px;">Your NFT has been added to your collection.</p>
                        <p id="transactionId" style="color: #888; font-size: 0.9em; word-break: break-all;"></p>
                    </div>
                </div>
                
                <div style="display: flex; justify-content: center; gap: 15px; margin-top: 20px;">
                    <button id="confirmPurchaseBtn" class="btn-buy" style="padding: 12px 30px; font-size: 1.1em;" disabled>
                        <i class="material-icons" style="vertical-align: middle; margin-right: 8px;">check_circle</i>
                        Confirm Purchase
                    </button>
                    <button id="cancelPurchaseBtn" class="btn-cancel" style="padding: 12px 25px; font-size: 1.1em;" id="cancelPurchaseBtn">
                        Cancel
                    </button>
                    <button id="closeAfterPurchaseBtn" class="btn-cancel" style="padding: 12px 25px; font-size: 1.1em; display: none;">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Purchase Success Modal -->
    <div id="purchaseSuccessModal" class="modal">
        <div class="modal-content" style="max-width: 400px; text-align: center;">
            <div class="modal-header" style="border-bottom: none; padding-bottom: 0;">
                <span class="close-button" id="closePurchaseSuccessModal">&times;</span>
                <div style="width: 80px; height: 80px; background: rgba(0, 191, 191, 0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                    <i class="material-icons" style="font-size: 40px; color: #00BFBF;">check_circle</i>
                </div>
                <h2 style="margin: 10px 0 15px; color: #e0e0e0; font-weight: 500; letter-spacing: 0.5px;">Purchase Complete!</h2>
            </div>
            <div class="modal-body" style="padding-top: 0;">
                <p style="color: #b0b0b0; margin-bottom: 25px; line-height: 1.5;">
                    Your NFT has been successfully added to your wallet.
                    <br>You can view it in your collection.
                </p>
                <button id="okPurchaseSuccessModal" class="btn-buy" style="padding: 12px 30px; font-size: 1em; margin: 0 auto;">
                    OK
                </button>
            </div>
        </div>
    </div>

    <script>
        // Global variable to track the currently flipped card
        window.currentlyFlippedCard = window.currentlyFlippedCard || null;
        
        // Close the dropdown if clicked outside
        document.addEventListener('click', function(event) {
            const walletDropdown = document.getElementById('walletDropdown');
            const walletButton = document.querySelector('.wallet-btn');
            
            if (walletDropdown && walletButton) {
                if (!event.target.closest('.wallet-dropdown') && !event.target.matches('.wallet-btn, .wallet-btn *')) {
                    walletDropdown.classList.remove('show');
                    walletButton.setAttribute('aria-expanded', 'false');
                }
            }
        });
        
        // Close dropdown when pressing Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const dropdown = document.getElementById('walletDropdown');
                const button = document.querySelector('.wallet-btn');
                if (dropdown && dropdown.classList.contains('show')) {
                    dropdown.classList.remove('show');
                    if (button) {
                        button.setAttribute('aria-expanded', 'false');
                    }
                }
            }
        });

        // Handle logout
        async function handleLogout(event) {
            event.preventDefault();
            
            try {
                // Call the logout API
                const response = await fetch('api/logout.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'same-origin' // Include cookies for session handling
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Redirect to marketplace page after successful logout
                    window.location.href = 'collection.php';
                } else {
                    console.error('Logout failed:', result.message);
                    alert('Failed to log out. Please try again.');
                }
            } catch (error) {
                console.error('Error during logout:', error);
                alert('An error occurred during logout. Please try again.');
            }
        }

        // Initialize the currently flipped card variable
        let currentlyFlippedCard = null;
        
        // Call initialize function when DOM is fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            initializeCardFunctionality();
        });

        function initializeCardFunctionality() {
            // Event delegation for card interactions
            document.addEventListener('click', function(event) {
                // Handle favorite button clicks
                const favoriteBtn = event.target.closest('.favorite-button');
                if (favoriteBtn) {
                    event.stopPropagation();
                    event.preventDefault();
                    // Make sure we're passing the button element, not the event
                    handleFavoriteButtonClick({ currentTarget: favoriteBtn });
                    
                    // Here you would typically make an API call to save the like state
                    // For example: saveLikeState(favoriteBtn.dataset.nftId, isLiked);
                    
                    return;
                }


                // Handle buy now button clicks
                const buyNowButton = event.target.closest('.buy-now-button');
                if (buyNowButton) {
                    event.preventDefault();
                    event.stopPropagation();
                    
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id']): ?>
                        // User is logged in, show purchase confirmation
                        const purchaseConfirmationModal = document.getElementById('purchaseConfirmationModal');
                        if (purchaseConfirmationModal) {
                            purchaseConfirmationModal.style.display = 'flex';
                            document.body.style.overflow = 'hidden';
                            
                            // Set the NFT ID for the purchase
                            const nftId = buyNowButton.closest('.nft-card-small')?.dataset?.nftId;
                            if (nftId) {
                                purchaseConfirmationModal.dataset.nftId = nftId;
                            }
                        }
                    <?php else: ?>
                        // User is not logged in, show login required modal
                        const loginToBuyModal = document.getElementById('loginToBuyModal');
                        if (loginToBuyModal) {
                            loginToBuyModal.style.display = 'flex';
                            document.body.style.overflow = 'hidden';
                        }
                    <?php endif; ?>
                    return;
                }


                // Handle make offer button clicks
                const makeOfferButton = event.target.closest('.make-offer-button');
                if (makeOfferButton) {
                    event.preventDefault();
                    event.stopPropagation();
                    
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id']): ?>
                        // User is logged in, handle make offer
                        alert('Make offer functionality will be implemented here');
                        // You can implement the make offer logic here
                    <?php else: ?>
                        // User is not logged in, show login required modal
                        const loginToBuyModal = document.getElementById('loginToBuyModal');
                        if (loginToBuyModal) {
                            loginToBuyModal.style.display = 'flex';
                            document.body.style.overflow = 'hidden';
                        }
                    <?php endif; ?>
                    return;
                }


                // Handle card flip - only if not clicking on interactive elements
                const interactiveElements = ['A', 'BUTTON', 'INPUT', 'SELECT', 'TEXTAREA', 'LABEL', 'I'];
                const clickedElement = event.target;
                
                // Check if the click is on an interactive element or its parent
                const isInteractive = interactiveElements.some(tagName => 
                    clickedElement.closest(tagName) || 
                    clickedElement.tagName === tagName
                );
                
                // Check if the click is on a favorite button or its icon
                const isFavoriteButton = clickedElement.closest('.favorite-button') || 
                                       clickedElement.classList.contains('material-icons') && 
                                       (clickedElement.textContent === 'star' || clickedElement.textContent === 'star_border');
                
                if (!isInteractive || clickedElement.closest('.nft-card-inner') && !isFavoriteButton) {
                    const card = event.target.closest('.nft-card-small');
                    if (card) {
                        event.preventDefault();
                        event.stopPropagation();
                        
                        // If another card is flipped, flip it back first
                        if (window.currentlyFlippedCard && window.currentlyFlippedCard !== card) {
                            window.currentlyFlippedCard.classList.remove('flipped');
                            // Remove the click outside listener for the previously flipped card
                            if (window.currentClickOutsideHandler) {
                                document.removeEventListener('click', window.currentClickOutsideHandler);
                            }
                        }

                        // Toggle the clicked card
                        const isFlipping = !card.classList.contains('flipped');
                        card.classList.toggle('flipped');

                        // Update the currently flipped card reference
                        if (isFlipping) {
                            window.currentlyFlippedCard = card;
                            
                            // Add click outside handler to close card when clicking outside
                            const handleClickOutside = (e) => {
                                if (!card.contains(e.target)) {
                                    card.classList.remove('flipped');
                                    window.currentlyFlippedCard = null;
                                    document.removeEventListener('click', handleClickOutside);
                                    window.currentClickOutsideHandler = null;
                                }
                            };
                            
                            // Store the handler reference so we can remove it later
                            window.currentClickOutsideHandler = handleClickOutside;
                            
                            // Add event listener with a small delay to avoid immediate close
                            setTimeout(() => {
                                document.addEventListener('click', handleClickOutside);
                            }, 100);
                        } else {
                            currentlyFlippedCard = null;
                            if (window.currentClickOutsideHandler) {
                                document.removeEventListener('click', window.currentClickOutsideHandler);
                                window.currentClickOutsideHandler = null;
                            }
                        }
                    }
                }
            });
            
            // Function to close a modal with animation
            function closeModal(modal) {
                if (modal) {
                    modal.style.opacity = '0';
                    setTimeout(() => {
                        modal.style.display = 'none';
                        modal.classList.remove('active');
                        document.body.style.overflow = 'auto';
                    }, 300);
                }
            }

            // Function to close a modal with animation
            function closeModal(modal) {
                if (modal) {
                    modal.style.opacity = '0';
                    setTimeout(() => {
                        modal.style.display = 'none';
                        modal.classList.remove('active');
                        document.body.style.overflow = 'auto';
                    }, 300);
                }
            }

            // Close modals when clicking outside
            document.addEventListener('click', function(event) {
                if (event.target.classList.contains('modal')) {
                    closeModal(event.target);
                }
            }, true);

            // Close modals with Escape key
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    const modals = document.querySelectorAll('.modal');
                    for (let i = 0; i < modals.length; i++) {
                        if (window.getComputedStyle(modals[i]).display !== 'none') {
                            closeModal(modals[i]);
                            break;
                        }
                    }
                }
            }, true);

            // Initialize purchase confirmation modal
            function setupPurchaseModal() {
                const modal = document.getElementById('purchaseConfirmationModal');
                if (!modal) return;
                
                // Close button (X)
                const closeBtn = modal.querySelector('.close-button');
                if (closeBtn) {
                    closeBtn.onclick = function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        closeModal(modal);
                        return false;
                    };
                }
                
                // Cancel button
                const cancelBtn = document.getElementById('cancelPurchaseBtn');
                if (cancelBtn) {
                    cancelBtn.onclick = function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        closeModal(modal);
                        return false;
                    };
                }
                
                // Confirm purchase button
                const confirmBtn = document.getElementById('confirmPurchaseBtn');
                if (confirmBtn) {
                    confirmBtn.onclick = async function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        const nftId = this.getAttribute('data-nft-id');
                        const price = parseFloat(this.getAttribute('data-price'));
                        const button = this;
                        
                        if (!nftId || isNaN(price)) {
                            console.error('Invalid NFT ID or price');
                            return;
                        }
                        
                        // Disable button and show loading state
                        button.disabled = true;
                        button.innerHTML = '<i class="material-icons" style="vertical-align: middle; margin-right: 8px;">hourglass_empty</i> Processing...';
                        
                        try {
                            const response = await fetch('api/purchase_nft.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    nftId: nftId,
                                    price: price
                                })
                            });
                            
                            const result = await response.json();
                            
                            if (result.success) {
                                // Show success message
                                document.getElementById('purchaseForm').style.display = 'none';
                                document.getElementById('purchaseSuccess').style.display = 'block';
                                document.getElementById('transactionId').textContent = 'Transaction ID: ' + (result.transactionId || 'N/A');
                                
                                // Update UI
                                document.getElementById('cancelPurchaseBtn').style.display = 'none';
                                document.getElementById('closeAfterPurchaseBtn').style.display = 'inline-block';
                                
                                // Close modal after 5 seconds
                                setTimeout(() => {
                                    closeModal(modal);
                                    window.location.reload();
                                }, 5000);
                            } else {
                                throw new Error(result.message || 'Purchase failed');
                            }
                        } catch (error) {
                            console.error('Purchase error:', error);
                            alert('Purchase failed: ' + (error.message || 'Unknown error'));
                            button.disabled = false;
                            button.innerHTML = '<i class="material-icons" style="vertical-align: middle; margin-right: 8px;">check_circle</i> Confirm Purchase';
                        }
                        return false;
                    };
                }
                
                // Close after purchase button
                const closeAfterBtn = document.getElementById('closeAfterPurchaseBtn');
                if (closeAfterBtn) {
                    closeAfterBtn.onclick = function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        closeModal(modal);
                        window.location.reload();
                        return false;
                    };
                }
            }
            
            // Initialize close buttons for other modals
            function initializeCloseButtons() {
                document.querySelectorAll('.modal .close-button').forEach(button => {
                    button.onclick = function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        const modal = this.closest('.modal');
                        closeModal(modal);
                        return false;
                    };
                });
            }
            
            // Function to handle purchase confirmation
            async function handlePurchase(confirmBtn) {
                const nftId = confirmBtn.getAttribute('data-nft-id');
                const price = parseFloat(confirmBtn.getAttribute('data-price'));
                const button = confirmBtn;
                
                if (!nftId || isNaN(price)) {
                    console.error('Invalid NFT ID or price');
                    return;
                }
                
                // Disable button and show loading state
                button.disabled = true;
                button.innerHTML = '<i class="material-icons" style="vertical-align: middle; margin-right: 8px;">hourglass_empty</i> Processing...';
                
                try {
                    const response = await fetch('api/purchase_nft.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            nftId: nftId,
                            price: price
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        // Show success message
                        document.getElementById('purchaseForm').style.display = 'none';
                        document.getElementById('purchaseSuccess').style.display = 'block';
                        document.getElementById('transactionId').textContent = 'Transaction ID: ' + (result.transactionId || 'N/A');
                        
                        // Update UI
                        document.getElementById('cancelPurchaseBtn').style.display = 'none';
                        document.getElementById('closeAfterPurchaseBtn').style.display = 'inline-block';
                        
                        // Close modal after 5 seconds
                        setTimeout(() => {
                            const modal = document.getElementById('purchaseConfirmationModal');
                            closeModal(modal);
                            window.location.reload();
                        }, 5000);
                    } else {
                        throw new Error(result.message || 'Purchase failed');
                    }
                } catch (error) {
                    console.error('Purchase error:', error);
                    alert('Purchase failed: ' + (error.message || 'Unknown error'));
                    button.disabled = false;
                    button.innerHTML = '<i class="material-icons" style="vertical-align: middle; margin-right: 8px;">check_circle</i> Confirm Purchase';
                }
            }

            // Initialize all modals when the page loads
            document.addEventListener('DOMContentLoaded', function() {
                // Setup modal close buttons
                document.querySelectorAll('.modal .close-button').forEach(button => {
                    button.onclick = function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        const modal = this.closest('.modal');
                        closeModal(modal);
                        return false;
                    };
                });
                
                // Setup purchase confirmation modal buttons
                const purchaseModal = document.getElementById('purchaseConfirmationModal');
                if (purchaseModal) {
                    // Close button (X)
                    const closeBtn = purchaseModal.querySelector('.close-button');
                    if (closeBtn) {
                        closeBtn.onclick = function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            closeModal(purchaseModal);
                            return false;
                        };
                    }
                    
                    // Cancel button
                    const cancelBtn = document.getElementById('cancelPurchaseBtn');
                    if (cancelBtn) {
                        cancelBtn.onclick = function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            closeModal(purchaseModal);
                            return false;
                        };
                    }
                    
                    // Confirm purchase button
                    const confirmBtn = document.getElementById('confirmPurchaseBtn');
                    if (confirmBtn) {
                        confirmBtn.onclick = function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            handlePurchase(this);
                            return false;
                        };
                    }
                    
                    // Close after purchase button
                    const closeAfterBtn = document.getElementById('closeAfterPurchaseBtn');
                    if (closeAfterBtn) {
                        closeAfterBtn.onclick = function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            closeModal(purchaseModal);
                            window.location.reload();
                            return false;
                        };
                    }
                }
                
                // Close modal when clicking outside
                document.addEventListener('click', function(event) {
                    if (event.target.classList.contains('modal')) {
                        closeModal(event.target);
                    }
                }, true);
                
                // Close modal with Escape key
                document.addEventListener('keydown', function(event) {
                    if (event.key === 'Escape') {
                        const modals = document.querySelectorAll('.modal');
                        for (let i = 0; i < modals.length; i++) {
                            if (window.getComputedStyle(modals[i]).display !== 'none') {
                                closeModal(modals[i]);
                                break;
                            }
                        }
                    }
                }, true);
            });
            
            // Handle login to buy now button
            const loginToBuyNowBtn = document.getElementById('loginToBuyNowBtn');
            if (loginToBuyNowBtn) {
                loginToBuyNowBtn.addEventListener('click', function() {
                    const loginToBuyModal = document.getElementById('loginToBuyModal');
                    if (loginToBuyModal) {
                        loginToBuyModal.style.display = 'none';
                    }
                    // Removed openLoginModal();
                });
            }
            
            // Handle cancel login to buy button
            const cancelLoginToBuyBtn = document.getElementById('cancelLoginToBuyBtn');
            if (cancelLoginToBuyBtn) {
                cancelLoginToBuyBtn.addEventListener('click', function() {
                    const loginToBuyModal = document.getElementById('loginToBuyModal');
                    if (loginToBuyModal) {
                        loginToBuyModal.style.display = 'none';
                        document.body.style.overflow = 'auto';
                    }
                });
            }
        }

        // Global variable to track the currently flipped card
        if (typeof window.currentlyFlippedCard === 'undefined') {
            window.currentlyFlippedCard = null;
        }
        
        // Function to handle card flip
        function handleCardFlip(card) {
            // Don't flip if clicking on interactive elements
            if (event.target.closest('button, a, input, select, textarea, .favorite-button')) {
                return;
            }
            
            // If another card is flipped, flip it back first
            if (currentlyFlippedCard && currentlyFlippedCard !== card) {
                currentlyFlippedCard.classList.remove('flipped');
                if (window.currentClickOutsideHandler) {
                    document.removeEventListener('click', window.currentClickOutsideHandler);
                    window.currentClickOutsideHandler = null;
                }
            }
            
            // Toggle the clicked card
            const isFlipping = !card.classList.contains('flipped');
            card.classList.toggle('flipped');
            
            // Update the currently flipped card reference
            if (isFlipping) {
                currentlyFlippedCard = card;
                
                // Add click outside handler
                const handleClickOutside = (e) => {
                    if (!card.contains(e.target)) {
                        card.classList.remove('flipped');
                        currentlyFlippedCard = null;
                        document.removeEventListener('click', handleClickOutside);
                        window.currentClickOutsideHandler = null;
                    }
                };
                
                // Store handler for cleanup
                window.currentClickOutsideHandler = handleClickOutside;
                
                // Add with delay to avoid immediate trigger
                setTimeout(() => {
                    document.addEventListener('click', handleClickOutside);
                }, 100);
            } else {
                currentlyFlippedCard = null;
                if (window.currentClickOutsideHandler) {
                    document.removeEventListener('click', window.currentClickOutsideHandler);
                    window.currentClickOutsideHandler = null;
                }
            }
        }
        
        // Safe storage access with error handling
        function getStorageItem(key) {
            try {
                return localStorage.getItem(key);
            } catch (e) {
                console.warn('Could not access localStorage:', e);
                return null;
            }
        }

        function setStorageItem(key, value) {
            try {
                localStorage.setItem(key, value);
                return true;
            } catch (e) {
                console.warn('Could not write to localStorage:', e);
                return false;
            }
        }

        // Initialize the application when DOM is ready
        function initializeApp() {
            console.log('Initializing favorite buttons and card flip...');
            
            // Initialize card flip for all NFT cards
            const nftCards = document.querySelectorAll('.nft-card-small');
            
            // Only initialize if not already initialized
            if (window.appInitialized) return;
            window.appInitialized = true;
            nftCards.forEach(card => {
                card.style.cursor = 'pointer';
                
                card.addEventListener('click', function(event) {
                    handleCardFlip(this);
                });
                
                // Add hover effect
                card.addEventListener('mouseenter', function() {
                    if (!this.classList.contains('flipped')) {
                        this.style.transform = 'translateY(-10px)';
                        this.style.boxShadow = '0 10px 25px rgba(0, 191, 191, 0.3)';
                    }
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = '';
                    this.style.boxShadow = '';
                });
            });
            
            console.log(`Initialized ${nftCards.length} NFT cards with flip functionality`);
            
            // Initialize wallet dropdown and buttons
            const walletDropdown = document.getElementById('walletDropdown');
            const walletButton = document.querySelector('.wallet-btn');
            const disconnectWalletBtn = document.querySelector('[onclick*="handleLogout"]');
            const connectWalletBtn = document.getElementById('connectWallet');
            const walletDropdownContent = document.querySelector('.wallet-dropdown-content');

            // Make toggleWalletDropdown globally accessible
            window.toggleWalletDropdown = function(event) {
                if (event) {
                    event.stopPropagation();
                    event.preventDefault();
                }
                
                if (walletDropdownContent) {
                    walletDropdownContent.classList.toggle('show');
                    const isExpanded = walletDropdownContent.classList.contains('show');
                    
                    // Update ARIA attributes
                    if (walletButton) {
                        walletButton.setAttribute('aria-expanded', isExpanded);
                    }
                    
                    // Close when clicking outside
                    if (isExpanded) {
                        const closeDropdown = (e) => {
                            if (!walletDropdown.contains(e.target) && e.target !== walletButton) {
                                walletDropdownContent.classList.remove('show');
                                if (walletButton) {
                                    walletButton.setAttribute('aria-expanded', 'false');
                                }
                                document.removeEventListener('click', closeDropdown);
                            }
                        };
                        // Use setTimeout to avoid immediate close
                        setTimeout(() => document.addEventListener('click', closeDropdown), 0);
                    }
                }
            };

            // Make handleLogout globally accessible
            window.handleLogout = async function(e) {
                if (e) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                
                try {
                    console.log('Logging out...');
                    
                    // Get the current collection_id from the URL
                    const urlParams = new URLSearchParams(window.location.search);
                    const collectionId = urlParams.get('collection_id');
                    
                    const response = await fetch('api/logout.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Cache-Control': 'no-cache, no-store, must-revalidate',
                            'Pragma': 'no-cache',
                            'Expires': '0'
                        },
                        body: 'logout=true',
                        credentials: 'same-origin',
                        cache: 'no-store'
                    });

                    const data = await response.json();
                    console.log('Logout response:', data);

                    if (data.success) {
                        // Close any open modals
                        document.querySelectorAll('.modal').forEach(modal => {
                            modal.style.display = 'none';
                        });
                        
                        // Close wallet dropdown if open
                        if (walletDropdownContent) {
                            walletDropdownContent.classList.remove('show');
                        }
                        
                        // Build the redirect URL with the collection_id if it exists
                        let redirectUrl = window.location.pathname;
                        
                        // Preserve the collection_id in the URL and add cache-busting
                        const params = new URLSearchParams();
                        if (collectionId) params.set('collection_id', collectionId);
                        params.set('t', Date.now());
                        
                        // Update URL and reload
                        window.location.search = params.toString();
                    } else {
                        console.error('Logout failed:', data.message);
                        alert('Failed to log out. Please try again.');
                    }
                } catch (error) {
                    console.error('Error during logout:', error);
                    alert('An error occurred while disconnecting. Please try again.');
                }
            }

            // Function to show a specific view in the modal
            window.showView = function(viewId) {
                // Hide all views first
                const views = document.querySelectorAll('.modal-body');
                views.forEach(view => {
                    view.style.display = 'none';
                });
                
                // Show the selected view
                const view = document.getElementById(viewId);
                if (view) {
                    view.style.display = 'block';
                }
                
                // Update modal title based on view
                const title = document.querySelector('.modal-header h2');
                if (viewId === 'loginView') {
                    title.textContent = 'Login to Your Account';
                } else if (viewId === 'signupView') {
                    title.textContent = 'Create New Account';
                } else if (viewId === 'forgotView') {
                    title.textContent = 'Reset Your Password';
                } else if (viewId === 'luckyTimeView') {
                    title.textContent = 'Login with LuckyTime';
                }
            }

            function openLoginModal() {
                const loginModal = document.getElementById('loginModal');
                if (loginModal) {
                    loginModal.style.display = 'flex';
                    document.body.style.overflow = 'hidden';
                    // Reset to login view when opening modal
                    showView('loginView');
                }
            }

            if (walletButton) {
                walletButton.addEventListener('click', toggleWalletDropdown);
            }

            if (disconnectWalletBtn) {
                disconnectWalletBtn.addEventListener('click', handleLogout);
            }

            if (connectWalletBtn) {
                connectWalletBtn.addEventListener('click', openLoginModal);
            }




                }
            }


            // Handle login form submission
            if (loginForm) {
                loginForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    const email = document.getElementById('email').value;
                    const password = document.getElementById('password').value;
                    
                    if (!email || !password) {
                        alert('Please fill in all fields');
                        return;
                    }
                    
                    // Show loading state
                    const submitBtn = e.target.querySelector('button[type="submit"]');
                    const originalBtnText = submitBtn.textContent;
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Logging in...';
                    
                    try {
                        // Send login request to our API endpoint
                        const response = await fetch('api/login.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: `login_type=standard&email=${encodeURIComponent(email)}&password=${encodeURIComponent(password)}`,
                            credentials: 'same-origin'
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            // Close the modal
                            const loginModal = document.getElementById('loginModal');
                            if (loginModal) {
                                loginModal.style.display = 'none';
                                document.body.style.overflow = 'auto';
                            }
                            
                            // Get current URL parameters
                            const urlParams = new URLSearchParams(window.location.search);
                            const collectionId = urlParams.get('collection_id');
                            
                            // Build the redirect URL with the collection_id if it exists
                            let redirectUrl = window.location.pathname;
                            
                            // Add collection_id if it exists
                            if (collectionId) {
                                redirectUrl += `?collection_id=${collectionId}`;
                            }
                            
                            // Add timestamp to prevent caching
                            redirectUrl += (collectionId ? '&' : '?') + 't=' + new Date().getTime();
                            
                            // Redirect to the same page with collection_id preserved
                            window.location.href = redirectUrl;
                        } else {
                            // Show error message
                            alert(data.message || 'Login failed. Please try again.');
                            submitBtn.disabled = false;
                            submitBtn.textContent = originalBtnText;
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('An error occurred. Please try again.');
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalBtnText;
                    }
                });
            }

            // Handle LuckyTime login form submission
            if (luckyTimeLoginForm) {
                luckyTimeLoginForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    const email = document.getElementById('luckyTimeEmail').value;
                    const password = document.getElementById('luckyTimePassword').value;
                    const submitBtn = this.querySelector('button[type="submit"]');
                    const originalBtnText = submitBtn.innerHTML;
                    
                    try {
                        // Show loading state
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<span class="material-icons" style="vertical-align: middle; margin-right: 5px;">hourglass_empty</span> Logging in...';
                        
                        // Call the LuckyTime login API with credentials and handle redirect manually
                        const response = await fetch('/Luck%20Wallet/auth/process_luckytime_login.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'X-Requested-With': 'XMLHttpRequest' // Indicate this is an AJAX request
                            },
                            credentials: 'same-origin', // Include cookies for session
                            body: new URLSearchParams({
                                'username': email,
                                'password': password
                            })
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            // Reload the page to show the logged-in state
                            window.location.reload();
                        } else {
                            // Show error message
                            alert(data.message || 'Login failed. Please try again.');
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalBtnText;
                        }
                    } catch (error) {
                        console.error('Login error:', error);
                        alert('An error occurred. Please try again.');
                        submitBtn.disabled = false;
                isTransitioning = true;
                const isOpening = !sidebarLeft.classList.contains('mobile-visible');

                if (isOpening) {
                    sidebarLeft.classList.add('mobile-visible');
                    overlay.classList.add('active');
                    document.body.style.overflow = 'hidden';
                } else {
                    sidebarLeft.classList.remove('mobile-visible');
                    overlay.classList.remove('active');
                    document.body.style.overflow = '';
                }

                setTimeout(() => {
                    isTransitioning = false;
                }, TRANSITION_DURATION);
            }

            function closeAllMenus() {
                if (isTransitioning) return;

                isTransitioning = true;
                if (overlay) overlay.classList.remove('active');
                if (sidebarLeft) sidebarLeft.classList.remove('mobile-visible');
                if (searchBar) searchBar.classList.remove('expanded-mobile');
                document.body.style.overflow = '';

                if (document.activeElement) {
                    document.activeElement.blur();
                }

                setTimeout(() => {
                    isTransitioning = false;
                }, TRANSITION_DURATION);
            }

            if (mobileSidebarToggleBtn && sidebarLeft) {
                mobileSidebarToggleBtn.addEventListener('click', toggleMobileMenu);
            }

            if (overlay) {
                overlay.addEventListener('click', closeAllMenus);
            }

            sidebarItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    // Only prevent default for non-navigation items
                    if (!this.getAttribute('href') || this.getAttribute('href') === '#') {
                        e.preventDefault();
                    }

                    // Update active state
                    sidebarItems.forEach(i => i.classList.remove('active'));
                    this.classList.add('active');

                    // Close menu on mobile after clicking
                    if (window.innerWidth <= 1024) {
                        closeAllMenus();
                    }
                });
            });

            // Adjust desktop sidebar hover logic to use specific CSS classes
            if (window.innerWidth > 1024 && sidebarLeft) {
                sidebarLeft.addEventListener('mouseover', () => {
                    sidebarLeft.style.width = '250px'; /* Force expand */
                    sidebarLeft.style.alignItems = 'flex-start'; /* Align text to start */
                    sidebarLeftItemIcons.forEach(icon => {
                        icon.style.marginRight = '16px'; /* Show margin */
                    });
                    document.querySelectorAll('.sidebar-text').forEach(span => {
                        span.style.opacity = '1'; /* Show text */
                    });
                    if (mainContentOverlay) {
                        mainContentOverlay.classList.add('active');
                    }
                });

                sidebarLeft.addEventListener('mouseout', () => {
                    sidebarLeft.style.width = '80px'; /* Collapse */
                    sidebarLeft.style.alignItems = 'center'; /* Center items */
                    sidebarLeftItemIcons.forEach(icon => {
                        icon.style.marginRight = '0px'; /* Hide margin */
                    });
                    document.querySelectorAll('.sidebar-text').forEach(span => {
                        span.style.opacity = '0'; /* Hide text */
                    });
                    if (mainContentOverlay) {
                        mainContentOverlay.classList.remove('active');
                    }
                });
            }

            // Connect Wallet button behavior - already handled above
            // The connectWalletBtn click handler is now part of the main initialization

            // Close modals when clicking outside
            window.onclick = function(event) {
                if (event.target == loginModal) {
                    loginModal.style.display = "none";
                }
                if (event.target == loginToBuyModal) {
                    loginToBuyModal.style.display = "none";
                }
                if (event.target == purchaseConfirmationModal) {
                    purchaseConfirmationModal.style.display = "none";
                }
                if (event.target == purchaseSuccessModal) {
                    purchaseSuccessModal.style.display = "none";
                }
            }

            // Login form submission
            document.querySelector('#loginModal form').addEventListener('submit', async function(event) {
                event.preventDefault();
                
                const formData = new FormData(this);
                
                try {
                    const response = await fetch('api/login.php', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        // Only update UI on successful login
                        loginModal.style.display = "none";
                        // The page will reload after successful login, so no need to update the button here
                        window.location.reload();
                    } else {
                        // Show error message
                        const errorMessage = result.message || 'Login failed. Please try again.';
                        alert(errorMessage);
                    }
                } catch (error) {
                    console.error('Login error:', error);
                    alert('An error occurred during login. Please try again.');
                }
            });

            document.getElementById('signupLink').addEventListener('click', function(event) {
                event.preventDefault();
                alert('Redirecting to Sign Up page (demo)');
            });

            document.getElementById('forgotPasswordLink').addEventListener('click', function(event) {
                event.preventDefault();
                alert('Redirecting to Forgot Password page (demo)');
            });

            document.querySelectorAll('.wallet-button').forEach(button => {
                button.addEventListener('click', function() {
                    alert('Connecting to ' + this.textContent.trim() + ' (demo)');
                });
            });

            // Search bar interaction
            function toggleSearchBarExpansion() {
                if (window.innerWidth <= 768) {
                    if (isTransitioning) return;
                    isTransitioning = true;
                    const isExpanded = searchBar.classList.contains('expanded-mobile');

                    if (!isExpanded) {
                        sidebarLeft.classList.remove('mobile-visible'); /* Close sidebar if open */
                        searchBar.classList.add('expanded-mobile');
                        overlay.classList.add('active');
                        document.body.style.overflow = 'hidden';

                        if (searchInput) {
                            setTimeout(() => {
                                searchInput.focus();
                                searchInput.select();
                            }, 300);
                        }
                    } else {
                        searchBar.classList.remove('expanded-mobile');
                        overlay.classList.remove('active');
                        document.body.style.overflow = '';
                        if (searchInput) {
                            searchInput.value = '';
                            searchInput.blur();
                        }
                    }
                    setTimeout(() => {
                        isTransitioning = false;
                    }, TRANSITION_DURATION);
                } else {
                    if (searchInput === document.activeElement) {
                        searchInput.blur();
                    } else {
                        searchInput.focus();
                    }
                }
            }

            if (searchBar) {
                searchBar.addEventListener('click', function(event) {
                    event.stopPropagation();
                    toggleSearchBarExpansion();
                });
                searchBar.querySelector('.search-icon').addEventListener('click', function(event) {
                    event.preventDefault();
                });
            }

            // Function to handle favorite button clicks
            async function handleFavoriteButtonClick(event) {
                console.log('Favorite button clicked (global handler)');
                event.preventDefault();
                event.stopPropagation();
                event.stopImmediatePropagation();
                
                let favoriteButton = event.currentTarget;
                console.log('Button element:', favoriteButton);
                
                // If we got here from the global click handler, we need to find the button
                if (event.type === 'click' && !favoriteButton.classList.contains('favorite-button')) {
                    favoriteButton = event.target.closest('.favorite-button');
                    if (!favoriteButton) {
                        console.error('Could not find favorite button element');
                        return;
                    }
                }
                
                // Check if user is logged in first using a fresh check
                const isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
                console.log('User logged in:', isLoggedIn);
                
                if (!isLoggedIn) {
                    console.log('User not logged in, showing login modal');
                    // Show the login modal
                    const modal = document.getElementById('loginModal');
                    if (modal) {
                        modal.style.display = 'flex';
                        modal.style.opacity = '1';
                        modal.style.zIndex = '9999';
                        document.body.style.overflow = 'hidden';
                        
                        // Show login view
                        const loginView = document.getElementById('loginView');
                        if (loginView) loginView.style.display = 'block';
                        
                        // Hide other views
                        document.querySelectorAll('.modal-body:not(#loginView)').forEach(view => {
                            view.style.display = 'none';
                        });
                        
                        // Store the favorite button that was clicked
                        sessionStorage.setItem('pendingFavoriteAction', 'true');
                        
                        // Store the NFT ID for the favorite action
                        const nftId = favoriteButton.getAttribute('data-nft-id');
                        if (nftId) {
                            sessionStorage.setItem('pendingFavoriteNftId', nftId);
                        }
                    }
                    return false; // Prevent any default behavior
                }
                
                // If we get here, user is logged in
                await toggleFavoriteStatus(favoriteButton);
            }
            
            // Function to toggle favorite status (separated for reusability)
            async function toggleFavoriteStatus(favoriteButton) {
                if (!favoriteButton) {
                    console.error('No favorite button provided');
                    return;
                }
                
                // Ensure we have the button element
                if (favoriteButton.tagName !== 'BUTTON') {
                    favoriteButton = favoriteButton.closest('button');
                    if (!favoriteButton) {
                        console.error('Could not find button element');
                        return;
                    }
                }
                
                // Disable button during request to prevent double-clicks
                favoriteButton.disabled = true;
                
                // Get the icon element safely - find it fresh each time in case the button was cloned
                let icon = favoriteButton.querySelector('.material-icons');
                if (!icon) {
                    // If we can't find the icon, try to find it in the button's children
                    icon = Array.from(favoriteButton.children).find(el => 
                        el.classList && el.classList.contains('material-icons')
                    );
                    
                    if (!icon) {
                        console.error('Could not find icon element in button:', favoriteButton);
                        favoriteButton.disabled = false;
                        return;
                    }
                }
                
                // Get NFT ID and current favorite state
                const nftId = favoriteButton.getAttribute('data-nft-id');
                if (!nftId) {
                    console.error('No NFT ID found on button');
                    favoriteButton.disabled = false;
                    return;
                }
                
                const isFavorited = favoriteButton.classList.contains('favorited');
                const newState = !isFavorited;
                
                // Update the like count if element exists
                const likeCountElement = favoriteButton.closest('.nft-card-small')?.querySelector('.like-count');
                const currentCount = likeCountElement ? parseInt(likeCountElement.textContent) || 0 : 0;
                
                try {
                    // Optimistically update the UI
                    favoriteButton.classList.toggle('favorited', newState);
                    icon.textContent = newState ? 'star' : 'star_border';
                    
                    if (likeCountElement) {
                        likeCountElement.textContent = newState ? currentCount + 1 : Math.max(0, currentCount - 1);
                    }
                    
                    // Send request to toggle favorite status
                    console.log('Sending request to toggle_favorite.php with:', {
                        nftId,
                        action: newState ? 'add' : 'remove',
                        userId: <?php echo $isLoggedIn ? $_SESSION['user_id'] : 'null'; ?>
                    });
                    
                    const formData = new URLSearchParams();
                    formData.append('nft_id', nftId);
                    formData.append('action', newState ? 'add' : 'remove');
                    
                    const response = await fetch('toggle_favorite.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: formData,
                        credentials: 'same-origin' // Important for sessions
                    });
                    
                    console.log('Received response status:', response.status, response.statusText);
                    
                    // Parse the response as JSON
                    const text = await response.text();
                    const result = text ? JSON.parse(text) : { success: false, message: 'Empty response' };
                    
                    console.log('Parsed result:', result);
                    
                    if (!response.ok || !result.success) {
                        const errorMessage = result.message || `HTTP ${response.status} - ${response.statusText}`;
                        console.error('Failed to update favorite:', errorMessage);
                        
                        // Revert the UI if the server operation failed
                        favoriteButton.classList.toggle('favorited');
                        icon.textContent = favoriteButton.classList.contains('favorited') ? 'star' : 'star_border';
                        
                        if (likeCountElement) {
                            likeCountElement.textContent = currentCount; // Revert to original count
                        }
                        
                        throw new Error(errorMessage);
                    }
                    
                    // Update like count with server value if available
                    if (likeCountElement && typeof result.newLikeCount !== 'undefined') {
                        likeCountElement.textContent = result.newLikeCount;
                    }
                    
                    // Show success message if not a no-op
                    if (result.action !== 'no_change') {
                        showNotification(result.message || (newState ? 'Added to favorites' : 'Removed from favorites'));
                    }
                    
                    console.log('Favorite status updated successfully');
                    
                } catch (error) {
                    console.error('Error updating favorite status:', error);
                    
                    // Revert UI on error
                    favoriteButton.classList.toggle('favorited', isFavorited);
                    icon.textContent = isFavorited ? 'star' : 'star_border';
                    
                    if (likeCountElement) {
                        likeCountElement.textContent = currentCount;
                    }
                    
                    showNotification(
                        error.message || 
                        'An error occurred while updating favorite status.', 
                        true
                    );
                } finally {
                    // Re-enable the button
                    favoriteButton.disabled = false;
                }
            }
            
            // Initialize favorite buttons immediately (don't wait for DOMContentLoaded)
            console.log('Initializing favorite buttons');
            
            // Function to initialize favorite buttons
            function initFavoriteButtons() {
                const favoriteButtons = document.querySelectorAll('.favorite-button');
                console.log('Found', favoriteButtons.length, 'favorite buttons');
                
                favoriteButtons.forEach(button => {
                    // Store the current favorite state before cloning
                    const isFavorited = button.classList.contains('favorited');
                    
                    // Clone the button to remove any existing event listeners
                    const newButton = button.cloneNode(true);
                    
                    // Copy over the favorited state
                    if (isFavorited) {
                        newButton.classList.add('favorited');
                    } else {
                        newButton.classList.remove('favorited');
                    }
                    
                    // Update the icon based on the favorited state
                    const icon = newButton.querySelector('.material-icons');
                    if (icon) {
                        icon.textContent = isFavorited ? 'star' : 'star_border';
                    }
                    
                    // Replace the old button with the new one
                    button.parentNode.replaceChild(newButton, button);
                    
                    // Add our click handler
                    newButton.addEventListener('click', handleFavoriteButtonClick, true); // Use capture phase
                    
                    // Make sure the button is clickable
                    newButton.style.pointerEvents = 'auto';
                    newButton.style.cursor = 'pointer';
                    
                    console.log('Initialized favorite button:', newButton, 'favorited:', isFavorited);
                });
            }
            
            // Run initialization now and also on DOMContentLoaded
            initFavoriteButtons();
            document.addEventListener('DOMContentLoaded', initFavoriteButtons);
            
            // Also add a global click handler as a fallback
            document.addEventListener('click', function(event) {
                const favoriteButton = event.target.closest('.favorite-button');
                if (favoriteButton) {
                    console.log('Fallback handler: Favorite button clicked');
                    event.stopPropagation();
                    event.stopImmediatePropagation();
                    handleFavoriteButtonClick(event);
                }
            }, true); // Use capture phase
            });
            
            // JavaScript for NFT Card Flip (updated for single flip)
            console.log('Setting up card flip listeners');
            document.querySelectorAll('.nft-card-small').forEach(card => {
                card.addEventListener('click', function(event) {
                    console.log('Card clicked, target:', event.target);
                    
                    // Check if the click was on a favorite button or its children
                    const favoriteButton = event.target.closest('.favorite-button');
                    const buyButton = event.target.closest('.buy-button');
                    
                    console.log('Favorite button clicked:', !!favoriteButton, 'Buy button clicked:', !!buyButton);
                    
                    // Only flip if the click target is not the favorite or buy button, or their icons
                    if (!favoriteButton && !buyButton) {
                        console.log('Flipping card');
                        // If another card is flipped, flip it back first
                        if (currentlyFlippedCard && currentlyFlippedCard !== this) {
                            console.log('Flipping back other card');
                            currentlyFlippedCard.classList.remove('flipped');
                        }

                        // Toggle the clicked card
                        this.classList.toggle('flipped');
                        console.log('Card flipped state:', this.classList.contains('flipped'));

                        // Update the currently flipped card reference
                        if (this.classList.contains('flipped')) {
                            currentlyFlippedCard = this;
                        } else {
                            currentlyFlippedCard = null; // No card is flipped
                        }
                    }
                });
            });
            
            // Debug: Log when the script loads
            console.log('Buy button handler script loaded');
            
            // Function to show login modal - make it globally accessible
                
// Send request to toggle favorite status
console.log('Sending request to toggle_favorite.php with:', {
    nftId,
    action: newState ? 'add' : 'remove',
    userId: <?php echo $isLoggedIn ? $_SESSION['user_id'] : 'null'; ?>
});
                // Buy button handling is now done directly in the HTML onclick handler
                // This prevents duplicate event handling and makes the code more maintainable
            
            // Buy button functionality is now handled directly in the HTML onclick handler
            // This prevents duplicate event handlers and makes the code more maintainable
            
            // Handle purchase success modal
            const okPurchaseSuccessModal = document.getElementById('okPurchaseSuccess');
            const purchaseSuccessModal = document.getElementById('purchaseSuccessModal');
            if (okPurchaseSuccessModal) {
                okPurchaseSuccessModal.onclick = function() {
                    purchaseSuccessModal.style.display = "none";
                }
            }
        });
    </script>
    
    
    
    
    
    <script>
        // Global function to show login modal (used by other parts of the app)
        window.openLoginModal = function() {
            const modal = document.getElementById('loginModal');
            if (modal) {
                modal.style.display = 'flex';
                modal.classList.add('active');
                modal.style.zIndex = '9999';
                modal.style.opacity = '1';
                modal.scrollIntoView({ behavior: 'smooth', block: 'center' });
                // Reset to login view when opening modal
                if (typeof showLoginView === 'function') {
                    showLoginView();
                }
                return true;
            }
            return false;
        };
        
        // Function to analyze and fix favorite buttons
        function analyzeAndFixFavoriteButtons() {
            console.clear();
            const favButtons = document.querySelectorAll('.favorite-button');
            console.log(`Found ${favButtons.length} favorite buttons`);
            
            if (favButtons.length === 0) {
                console.error('No favorite buttons found!');
                return;
            }
            
            const firstButton = favButtons[0];
            
            // Create debug information
            const debugInfo = {
                button: {
                    id: firstButton.id || 'none',
                    className: firstButton.className,
                    html: firstButton.outerHTML,
                    rect: firstButton.getBoundingClientRect(),
                    styles: window.getComputedStyle(firstButton),
                    isConnected: firstButton.isConnected,
                    isContentEditable: firstButton.isContentEditable,
                    disabled: firstButton.disabled,
                },
                parents: []
            };
            
            // Check parent elements
            let parent = firstButton.parentElement;
            let depth = 0;
            while (parent && depth < 10) {
                debugInfo.parents.push({
                    tag: parent.tagName,
                    id: parent.id || 'none',
                    className: parent.className,
                    pointerEvents: window.getComputedStyle(parent).pointerEvents,
                    position: window.getComputedStyle(parent).position,
                    zIndex: window.getComputedStyle(parent).zIndex,
                    overflow: window.getComputedStyle(parent).overflow,
                    hasTransform: window.getComputedStyle(parent).transform !== 'none'
                });
                
                if (parent === document.body) break;
                parent = parent.parentElement;
                depth++;
            }
            
            // Apply fixes
            const fixes = [];
            
            // 1. Ensure button is clickable
            firstButton.style.pointerEvents = 'auto';
            firstButton.style.cursor = 'pointer';
            fixes.push('Set pointer-events: auto and cursor: pointer');
            
            // 2. Ensure button is above other elements
            firstButton.style.zIndex = '100';
            fixes.push('Set z-index: 100');
            
            // 3. Add a visual indicator
            firstButton.style.boxShadow = '0 0 10px 5px rgba(0, 191, 191, 0.7)';
            setTimeout(() => {
                firstButton.style.boxShadow = '';
            }, 3000);
            
            // 4. Add a test click handler directly to the button
            const testClick = (e) => {
                console.log('Direct click handler fired!', e);
                e.stopPropagation();
                e.preventDefault();
                alert('Favorite button clicked!');
                return false;
            };
            
            firstButton.addEventListener('click', testClick, true);
            fixes.push('Added direct click event listener with capture');
            
            // Log debug information
            console.log('Debug Info:', debugInfo);
            console.log('Applied fixes:', fixes);
            
            // Show debug overlay
            const overlay = document.createElement('div');
            overlay.className = 'debug-overlay';
            overlay.innerHTML = `
                <h2>Favorite Button Debug Info</h2>
                <pre>${JSON.stringify(debugInfo, (key, value) => {
                    if (typeof value === 'function') return 'function';
                    if (value instanceof CSSStyleDeclaration) return 'CSSStyleDeclaration';
                    if (value instanceof DOMRect) return {
                        x: value.x, y: value.y, width: value.width, height: value.height
                    };
                    return value;
                }, 2)}</pre>
                <p>Applied fixes: ${fixes.join(', ')}</p>
                <button onclick="this.parentNode.remove()">Close</button>
            `;
            document.body.appendChild(overlay);
            
            // Try to click the button
            setTimeout(() => {
                console.log('Attempting to click the button...');
                firstButton.dispatchEvent(new MouseEvent('click', {
                    view: window,
                    bubbles: true,
                    cancelable: true
                }));
            }, 500);
        }
        
        // Set up test button
        const testButton = document.getElementById('testButton');
        if (testButton) {
            testButton.addEventListener('click', analyzeAndFixFavoriteButtons);
        }
        
        // Define the click handler for favorite buttons
        async function handleFavoriteButtonClick(event) {
            console.log('Favorite button clicked');
            
            // Get the button element from the event
            const favoriteButton = event.currentTarget;
            
            // Get the icon and like count elements
            const icon = favoriteButton.querySelector('.material-icons');
            const likeCount = favoriteButton.closest('.nft-card-small')?.querySelector('.like-count');
            const nftCard = favoriteButton.closest('.nft-card-small');
            const nftId = nftCard ? nftCard.dataset.nftId : null;
            
            if (!nftId) {
                console.error('No NFT ID found for favorite button');
                return;
            }

            // Check if user is logged in
            const isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;

            if (!isLoggedIn) {
                console.log('User not logged in, showing login modal');
                // Store the current scroll position
                sessionStorage.setItem('scrollPosition', window.pageYOffset || document.documentElement.scrollTop);
                
                // Store the favorite button that was clicked
                sessionStorage.setItem('pendingFavoriteAction', 'true');
                sessionStorage.setItem('pendingFavoriteNftId', nftId);
                
                // Show login modal using the global function
                if (typeof window.openLoginModal === 'function') {
                    window.openLoginModal();
                } else {
                    // Fallback in case the global function is not available
                    const modal = document.getElementById('loginModal');
                    if (modal) {
                        modal.style.display = 'flex';
                        modal.style.zIndex = '9999';
                        document.body.style.overflow = 'hidden';
                        
                        // Show login view
                        const loginView = document.getElementById('loginView');
                        if (loginView) loginView.style.display = 'block';
                        
                        // Hide other views
                        document.querySelectorAll('.modal-body:not(#loginView)').forEach(view => {
                            view.style.display = 'none';
                        });
                    }
                }
                return false;
            }

            // Toggle the visual state
            const isFavorited = favoriteButton.classList.toggle('favorited');
            if (icon) {
                icon.textContent = isFavorited ? 'star' : 'star_border';
            }

            // Update like count
            const likeCountElement = nftCard.querySelector('.like-count');
            if (likeCountElement) {
                const currentCount = parseInt(likeCountElement.textContent) || 0;
                likeCountElement.textContent = isFavorited ? currentCount + 1 : Math.max(0, currentCount - 1);
            }
            
            try {
                const response = await fetch('api/toggle_favorite.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        nft_id: nftId,
                        action: isFavorited ? 'add' : 'remove'
                    })
                });
                
                const result = await response.json();
                
                if (!result.success) {
                    // Revert UI on error
                    favoriteButton.classList.toggle('favorited');
                    if (icon) {
                        icon.textContent = favoriteButton.classList.contains('favorited') ? 'star' : 'star_border';
                    }
                    
                    if (likeCountElement) {
                        const currentCount = parseInt(likeCountElement.textContent) || 0;
                        likeCountElement.textContent = isFavorited ? Math.max(0, currentCount - 1) : currentCount + 1;
                    }
                    
                    alert(result.message || 'Failed to update favorite');
                }
            } catch (error) {
                console.error('Error:', error);
                // Revert UI on error
                favoriteButton.classList.toggle('favorited');
                if (icon) {
                    icon.textContent = favoriteButton.classList.contains('favorited') ? 'star' : 'star_border';
                }
                
                if (likeCountElement) {
                    const currentCount = parseInt(likeCountElement.textContent) || 0;
                    likeCountElement.textContent = isFavorited ? currentCount + 1 : Math.max(0, currentCount - 1);
                }
                
                alert('An error occurred while updating favorite status.');
            }
        }
        
        // Initialize favorite buttons
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Initializing favorite buttons...');
            
            // Initialize favorite buttons
            const favButtons = document.querySelectorAll('.favorite-button');
            favButtons.forEach(button => {
                // Remove any existing click handlers to prevent duplicates
                button.replaceWith(button.cloneNode(true));
            });
            
            // Re-select buttons after clone and add click handlers
            const freshButtons = document.querySelectorAll('.favorite-button');
            freshButtons.forEach(button => {
                button.addEventListener('click', handleFavoriteButtonClick);
            });
            
            console.log(`Initialized ${freshButtons.length} favorite buttons`);
        });
        
        // Check for pending favorite action after login
        document.addEventListener('DOMContentLoaded', function() {
            if (sessionStorage.getItem('pendingFavoriteAction') === 'true') {
                sessionStorage.removeItem('pendingFavoriteAction');
                
                // Get the NFT ID from session storage
                const nftId = sessionStorage.getItem('pendingFavoriteNftId');
                let favoriteBtn = null;
                
                if (nftId) {
                    // Find the specific favorite button that was clicked
                    favoriteBtn = document.querySelector(`.favorite-button[data-nft-id="${nftId}"]`);
                    sessionStorage.removeItem('pendingFavoriteNftId');
                }
                
                // If no specific button found, try to find any favorite button
                if (!favoriteBtn) {
                    favoriteBtn = document.querySelector('.favorite-button');
                }
                
                // Trigger click on the button if found
                if (favoriteBtn) {
                    setTimeout(() => {
                        handleFavoriteButtonClick(favoriteBtn);
                    }, 100);
                }
            }
        });
        
        // Function to sort NFT cards
        function sortNFTs(event) {
            const sortBy = event.target.value;
            const nftGrid = document.getElementById('nftGrid');
            if (!nftGrid) return;

            const nftCards = Array.from(nftGrid.querySelectorAll('.nft-card-small'));
            
            nftCards.sort((a, b) => {
                switch(sortBy) {
                    case 'recent':
                        // Sort by NFT ID (higher ID = newer)
                        const idA = parseInt(a.dataset.nftId) || 0;
                        const idB = parseInt(b.dataset.nftId) || 0;
                        return idB - idA; // Higher ID first (newer)
                        
                    case 'price-low':
                        const priceA = parseFloat(a.querySelector('.price')?.textContent || 0);
                        const priceB = parseFloat(b.querySelector('.price')?.textContent || 0);
                        return priceA - priceB;
                        
                    case 'price-high':
                        const priceHighA = parseFloat(a.querySelector('.price')?.textContent || 0);
                        const priceHighB = parseFloat(b.querySelector('.price')?.textContent || 0);
                        return priceHighB - priceHighA;
                        
                    case 'likes':
                        const likesA = parseInt(a.dataset.likes || 0);
                        const likesB = parseInt(b.dataset.likes || 0);
                        return likesB - likesA; // Most likes first
                        
                    default:
                        return 0;
                }
            });

            // Remove all cards
            while (nftGrid.firstChild) {
                nftGrid.removeChild(nftGrid.firstChild);
            }

            // Add back sorted cards
            nftCards.forEach(card => {
                nftGrid.appendChild(card);
            });
            
            console.log(`Sorted by: ${sortBy}`);
        }

        // Global variable to track the currently flipped card
        let currentlyFlippedCard = null;
        
        // Function to handle card flip
        function handleCardFlip(card, event) {
            // Don't flip if clicking on interactive elements
            if (event && event.target.closest('button, a, input, select, textarea, .favorite-button')) {
                return;
            }
            
            // If another card is flipped, flip it back first
            if (currentlyFlippedCard && currentlyFlippedCard !== card) {
                currentlyFlippedCard.classList.remove('flipped');
                if (window.currentClickOutsideHandler) {
                    document.removeEventListener('click', window.currentClickOutsideHandler);
                    window.currentClickOutsideHandler = null;
                }
            }
            
            // Toggle the clicked card
            const isFlipping = !card.classList.contains('flipped');
            card.classList.toggle('flipped');
            
            // Update the currently flipped card reference
            if (isFlipping) {
                currentlyFlippedCard = card;
                
                // Add click outside handler
                const handleClickOutside = (e) => {
                    if (!card.contains(e.target)) {
                        card.classList.remove('flipped');
                        currentlyFlippedCard = null;
                        document.removeEventListener('click', handleClickOutside);
                        window.currentClickOutsideHandler = null;
                    }
                };
                
                // Store handler for cleanup
                window.currentClickOutsideHandler = handleClickOutside;
                
                // Add with delay to avoid immediate trigger
                setTimeout(() => {
                    document.addEventListener('click', handleClickOutside);
                }, 100);
            } else {
                currentlyFlippedCard = null;
                if (window.currentClickOutsideHandler) {
                    document.removeEventListener('click', window.currentClickOutsideHandler);
                    window.currentClickOutsideHandler = null;
                }
            }
        }
        
        // Initialize card flip functionality
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Initializing card flip functionality...');
            
            // Initialize card flip functionality
            const nftCards = document.querySelectorAll('.nft-card-small');
            nftCards.forEach(card => {
                card.style.cursor = 'pointer';
                
                card.addEventListener('click', function(event) {
                    handleCardFlip(this, event);
                    
                    const cardInner = this.querySelector('.nft-card-inner');
                    if (!cardInner) return;
                    
                    // If another card is flipped, flip it back first
                    if (currentlyFlippedCard && currentlyFlippedCard !== this) {
                        const prevCardInner = currentlyFlippedCard.querySelector('.nft-card-inner');
                        if (prevCardInner) {
                            prevCardInner.classList.remove('flipped');
                            if (window.currentClickOutsideHandler) {
                                document.removeEventListener('click', window.currentClickOutsideHandler);
                                window.currentClickOutsideHandler = null;
                            }
                        }
                    }
                    
                    // Toggle the clicked card
                    cardInner.classList.toggle('flipped');
                    
                    // Update the currently flipped card reference
                    if (cardInner.classList.contains('flipped')) {
                        currentlyFlippedCard = this;
                        
                        // Add click outside handler
                        const handleClickOutside = (e) => {
                            if (!this.contains(e.target)) {
                                cardInner.classList.remove('flipped');
                                currentlyFlippedCard = null;
                                document.removeEventListener('click', handleClickOutside);
                                window.currentClickOutsideHandler = null;
                            }
                        };
                        
                        // Store handler for cleanup
                        window.currentClickOutsideHandler = handleClickOutside;
                        
                        // Add with delay to avoid immediate trigger
                        setTimeout(() => {
                            document.addEventListener('click', handleClickOutside);
                        }, 100);
                    } else {
                        currentlyFlippedCard = null;
                        if (window.currentClickOutsideHandler) {
                            document.removeEventListener('click', window.currentClickOutsideHandler);
                            window.currentClickOutsideHandler = null;
                        }
                    }
                });
            });
            
            console.log(`Initialized ${nftCards.length} NFT cards with flip functionality`);
            
            // Hide the test and fix buttons as they're no longer needed
            const testButton = document.getElementById('testButton');
            const fixButton = document.getElementById('fixButton');
            if (testButton) testButton.style.display = 'none';
            if (fixButton) fixButton.style.display = 'none';
        });

        // Debug function to test Connect Wallet functionality
        function testConnectWallet() {
            console.log('=== DEBUG: Testing Connect Wallet functionality ===');
            
            // Test 1: Check if openLoginModal exists
            console.log('Test 1: Checking if openLoginModal function exists...');
            if (typeof window.openLoginModal === 'function') {
                console.log('✅ openLoginModal is a function');
                
                // Test 2: Try to get the login modal element
                const modal = document.getElementById('loginModal');
                console.log('Test 2: Login modal element:', modal ? 'Found' : 'Not found');
                
                // Test 3: Try to call the function
                console.log('Test 3: Calling openLoginModal()...');
                try {
                    const result = window.openLoginModal();
                    console.log('openLoginModal() returned:', result);
                    
                    // Check if modal is visible
                    setTimeout(() => {
                        const modal = document.getElementById('loginModal');
                        const isVisible = modal && window.getComputedStyle(modal).display !== 'none';
                        console.log('Test 4: Modal visibility check:', isVisible ? '✅ Visible' : '❌ Not visible');
                        
                        if (!isVisible) {
                            console.warn('Modal is not visible. Checking styles...');
                            if (modal) {
                                console.log('Current modal display:', window.getComputedStyle(modal).display);
                                console.log('Current modal opacity:', window.getComputedStyle(modal).opacity);
                                console.log('Current modal z-index:', window.getComputedStyle(modal).zIndex);
                            }
                        }
                    }, 100);
                } catch (error) {
                    console.error('Error calling openLoginModal:', error);
                }
            } else {
                console.error('❌ openLoginModal is not a function');
                console.log('Available window properties:', Object.keys(window).filter(k => k.toLowerCase().includes('login') || k === 'openLoginModal'));
            }
        }
        
        // Add click handler for Connect Wallet button
        document.addEventListener('DOMContentLoaded', function() {
            // Connect Wallet button
            const connectBtn = document.getElementById('connectWallet');
            if (connectBtn) {
                connectBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Connect Wallet button clicked');
                    window.openLoginModal();
                });
                console.log('Connect Wallet button initialized');
            } else {
                console.warn('Connect Wallet button not found in DOM');
            }
        });

        // Function to open the login modal - defined in global scope
        window.openLoginModal = function() {
            const modal = document.getElementById('loginModal');
            if (modal) {
                modal.style.display = 'flex';
                modal.style.opacity = '1';
                modal.style.zIndex = '9999';
                document.body.style.overflow = 'hidden'; // Prevent scrolling when modal is open
                
                // Make sure login view is shown
                const loginView = document.getElementById('loginView');
                if (loginView) {
                    loginView.style.display = 'block';
                }
                
                // Update modal title
                const title = document.querySelector('.modal-header h2');
                if (title) {
                    title.textContent = 'Login to Your Account';
                }
                
                return true;
            }
            return false;
        };

        // Login Modal Functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Get the modal and buttons
            const modal = document.getElementById('loginModal');
            const connectWalletBtn = document.getElementById('connectWallet');
            const closeButton = document.querySelector('.close-button');
            
            // Function to show a specific view in the modal
            function showView(viewId) {
                // Hide all views
                document.querySelectorAll('.modal-body').forEach(view => {
                    view.style.display = 'none';
                });
                
                // Show the requested view
                const view = document.getElementById(viewId);
                if (view) {
                    view.style.display = 'block';
                }
                
                // Update modal title based on view
                const title = document.querySelector('.modal-header h2');
                if (viewId === 'loginView') {
                    title.textContent = 'Login to Your Account';
                } else if (viewId === 'signupView') {
                    title.textContent = 'Create New Account';
                } else if (viewId === 'forgotView') {
                    title.textContent = 'Reset Your Password';
                }
            }

            // Function to close the login modal and reset scroll
            function closeLoginModal() {
                modal.style.display = 'none';
                document.body.style.overflow = ''; // Reset overflow to default
                // Reset to login view when closing
                setTimeout(() => showView('loginView'), 300);
            }
            
            // When the user clicks on the 'x' (close) button, close the modal
            if (closeButton) {
                closeButton.onclick = function(e) {
                    e.preventDefault();
                    closeLoginModal();
                };
            }

            // When the user clicks anywhere outside of the modal, close it
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    closeLoginModal();
                }
            });
            
            // Close with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && modal.style.display === 'flex') {
                    closeLoginModal();
                }
            });

            // View navigation event listeners
            const signupLink = document.querySelector('.switch-to-signup');
            if (signupLink) {
                signupLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    showView('signupView');
                });
            }

            // Handle show LuckyTime login form
            const showLuckyTimeBtn = document.getElementById('showLuckyTimeLogin');
            if (showLuckyTimeBtn) {
                showLuckyTimeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    showView('luckyTimeView');
                });
            }

            const forgotPasswordLink = document.querySelector('.forgot-password');
            if (forgotPasswordLink) {
                forgotPasswordLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    showView('forgotView');
                });
            }

            // Back to login from forgot password/LuckyTime
            const backToLoginLinks = document.querySelectorAll('.back-to-login, #backToLogin2');
            backToLoginLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    showView('loginView');
                });
            });
            
            // Back to login from signup
            const backToLogin = document.getElementById('backToLogin');
            if (backToLogin) {
                backToLogin.addEventListener('click', function(e) {
                    e.preventDefault();
                    showView('loginView');
                });
            }

            // Form submissions
            document.getElementById('loginForm')?.addEventListener('submit', function(event) {
                event.preventDefault();
                
                const email = document.getElementById('email').value;
                const password = document.getElementById('password').value;
                
                if (!email || !password) {
                    alert('Please fill in all fields');
                    return;
                }
                
                // Show loading state
                const submitBtn = event.target.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.textContent = 'Logging in...';
                
                // Send login request to our API endpoint
                fetch('api/login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `login_type=standard&email=${encodeURIComponent(email)}&password=${encodeURIComponent(password)}`,
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Check if there was a pending favorite action
                        const pendingFavorite = sessionStorage.getItem('pendingFavoriteAction');
                        
                        // Close the modal and reset scroll
                        closeLoginModal();
                        
                        if (pendingFavorite === 'true') {
                            // Clear the flag
                            sessionStorage.removeItem('pendingFavoriteAction');
                            
                            // Show a message or automatically trigger the favorite action
                            console.log('Completing pending favorite action...');
                            // You can add code here to automatically favorite the item if needed
                            // or just show a message to the user
                        } else {
                            // Reload the page to show the logged-in state
                            window.location.reload();
                        }
                    } else {
                        // Show error message
                        alert(data.message || 'Login failed. Please try again.');
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalBtnText;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalBtnText;
                });
            });

            // Handle signup form submission
            document.getElementById('signupForm')?.addEventListener('submit', function(event) {
                event.preventDefault();
                
                const username = document.getElementById('signupName').value.trim();
                const email = document.getElementById('signupEmail').value.trim();
                const password = document.getElementById('signupPassword').value;
                const confirmPassword = document.getElementById('confirmPassword').value;
                
                // Client-side validation
                if (!username || !email || !password || !confirmPassword) {
                    alert('Please fill in all fields');
                    return;
                }
                
                if (password !== confirmPassword) {
                    alert('Passwords do not match');
                    return;
                }
                
                if (password.length < 8) {
                    alert('Password must be at least 8 characters long');
                    return;
                }
                
                // Show loading state
                const submitBtn = event.target.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.textContent = 'Creating Account...';
                
                // Send signup request
                fetch('api/register.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `username=${encodeURIComponent(username)}&email=${encodeURIComponent(email)}&password=${encodeURIComponent(password)}`,
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success message and switch to login view
                        alert('Account created successfully! Please log in.');
                        showView('loginView');
                    } else {
                        // Show error message
                        alert(data.message || 'Registration failed. Please try again.');
                    }
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalBtnText;
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalBtnText;
                });
            });
            
            // Handle forgot password form submission
            document.getElementById('forgotForm')?.addEventListener('submit', function(event) {
                event.preventDefault();
                
                const email = document.getElementById('forgotEmail').value.trim();
                const newPassword = document.getElementById('newPassword').value;
                const confirmNewPassword = document.getElementById('confirmNewPassword').value;
                
                // Client-side validation
                if (!email || !newPassword || !confirmNewPassword) {
                    alert('Please fill in all fields');
                    return;
                }
                
                if (newPassword !== confirmNewPassword) {
                    alert('New passwords do not match');
                    return;
                }
                
                if (newPassword.length < 8) {
                    alert('Password must be at least 8 characters long');
                    return;
                }
                
                // Show loading state
                const submitBtn = event.target.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.textContent = 'Resetting Password...';
                
                // Send password reset request
                fetch('api/reset_password.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `email=${encodeURIComponent(email)}&new_password=${encodeURIComponent(newPassword)}`,
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success message and switch to login view
                        alert('Password reset successfully! Please log in with your new password.');
                        showView('loginView');
                    } else {
                        // Show error message
                        alert(data.message || 'Password reset failed. Please try again.');
                    }
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalBtnText;
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalBtnText;
                });
            });
            
            // Handle LuckyTime login form submission
            document.getElementById('luckyTimeLoginForm')?.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const username = document.getElementById('luckyTimeEmail').value.trim();
                const password = document.getElementById('luckyTimePassword').value;
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.innerHTML;
                
                if (!username || !password) {
                    alert('Please enter both username and password');
                    return;
                }
                
                try {
                    // Show loading state
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="material-icons" style="vertical-align: middle; margin-right: 5px;">hourglass_empty</span> Logging in...';
                    
                    // Call the LuckyTime login API with credentials
                    const response = await fetch('/Luck%20Wallet/auth/process_luckytime_login.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: `username=${encodeURIComponent(username)}&password=${encodeURIComponent(password)}`,
                        credentials: 'same-origin'
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        // Close the modal and reload the page to show the logged-in state
                        modal.style.display = 'none';
                        window.location.reload();
                    } else {
                        // Show error message
                        alert(data.message || 'LuckyTime login failed. Please check your credentials and try again.');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnText;
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                }
            });

            // When the "Connect Wallet" button is clicked, display the modal
            if (connectWalletBtn) {
                connectWalletBtn.onclick = function() {
                    modal.style.display = "flex";
                }
            }
        });
   
    // Sort functionality
    function initializeSorting() {
        console.log('Initializing sorting...');
        const sortSelect = document.getElementById('sortBy');
        if (!sortSelect) {
            console.error('Sort select element not found');
            return;
        }

        sortSelect.addEventListener('change', function() {
            const sortBy = this.value;
            console.log('Sorting by:', sortBy);
            
            const nftGrid = document.getElementById('nftGrid');
            if (!nftGrid) {
                console.error('NFT grid element not found');
                return;
            }
            
            const nftCards = Array.from(nftGrid.querySelectorAll('.nft-card-small'));
            console.log('Found', nftCards.length, 'NFT cards to sort');
            
            if (nftCards.length === 0) {
                console.log('No NFT cards found to sort');
                return;
            }

            // Debug: Log first card's data attributes
            console.log('First card data:', {
                id: nftCards[0].dataset.nftId,
                created: nftCards[0].dataset.created,
                price: nftCards[0].dataset.price,
                likes: nftCards[0].dataset.likes
            });

            try {
                nftCards.sort((a, b) => {
                    let result = 0;
                    switch(sortBy) {
                        case 'recent':
                            // Sort by NFT ID in descending order (higher ID = more recent)
                            result = (parseInt(b.dataset.nftId) || 0) - (parseInt(a.dataset.nftId) || 0);
                            break;
                        case 'price-low':
                            result = (parseFloat(a.dataset.price) || 0) - (parseFloat(b.dataset.price) || 0);
                            break;
                        case 'price-high':
                            result = (parseFloat(b.dataset.price) || 0) - (parseFloat(a.dataset.price) || 0);
                            break;
                        case 'likes':
                            result = (parseInt(b.dataset.likes) || 0) - (parseInt(a.dataset.likes) || 0);
                            break;
                        default:
                            result = 0;
                    }
                    return result;
                });

                // Create a document fragment for better performance
                const fragment = document.createDocumentFragment();
                nftCards.forEach(card => {
                    fragment.appendChild(card);
                });

                // Clear and repopulate the grid
                nftGrid.innerHTML = '';
                nftGrid.appendChild(fragment);
                
                console.log('Sorting completed');
            } catch (error) {
                console.error('Error during sorting:', error);
            }
        });
        
        console.log('Sorting initialized');
    }


    // Purchase Modal Functionality
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM fully loaded, initializing components...');
        
        // Initialize sorting
        try {
            initializeSorting();
            console.log('Sorting initialization attempted');
            
            // Manually trigger change event to ensure sort is applied
            const sortSelect = document.getElementById('sortBy');
            if (sortSelect) {
                console.log('Sort select found, adding debug event');
                sortSelect.addEventListener('change', function() {
                    console.log('Sort select changed to:', this.value);
                });
            } else {
                console.error('Sort select not found in DOM');
            }
        } catch (error) {
            console.error('Error during initialization:', error);
        }
        const modal = document.getElementById('purchaseConfirmationModal');
        const closeBtn = document.getElementById('closePurchaseConfirmationModal');
        const cancelBtn = document.getElementById('cancelPurchaseBtn');
        const confirmBtn = document.getElementById('confirmPurchaseBtn');
        const closeAfterBtn = document.getElementById('closeAfterPurchaseBtn');

        // Close modal function
        function closeModal() {
            if (modal) {
                modal.style.opacity = '0';
                setTimeout(() => {
                    modal.style.display = 'none';
                    modal.classList.remove('active');
                }, 300);
            }
        }

        // Close button (X)
        if (closeBtn) {
            closeBtn.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                closeModal();
                return false;
            };
        }

        // Cancel button
        if (cancelBtn) {
            cancelBtn.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                closeModal();
                return false;
            };
        }

        // Confirm button
        if (confirmBtn) {
            confirmBtn.onclick = async function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                if (this.disabled) return false;
                
                const nftId = this.getAttribute('data-nft-id');
                const price = parseFloat(this.getAttribute('data-price'));
                
                if (!nftId || isNaN(price)) {
                    console.error('Missing NFT ID or price');
                    alert('Error: Missing NFT information');
                    return false;
                }
                
                // Disable button during purchase
                this.disabled = true;
                
                try {
                    console.log('Sending purchase request for NFT:', nftId, 'Price:', price);
                    const response = await fetch('api/purchase_nft.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            nftId: nftId,
                            price: price
                        })
                    });
                    
                    console.log('Purchase response status:', response.status);
                    
                    // Clone the response so we can read it multiple times if needed
                    const responseClone = response.clone();
                    let result;
                    
                    try {
                        result = await response.json();
                        console.log('Purchase response data:', result);
                    } catch (jsonError) {
                        console.error('Failed to parse JSON response:', jsonError);
                        // Read from the cloned response
                        const responseText = await responseClone.text();
                        console.error('Raw response:', responseText);
                        throw new Error('Invalid response from server. Please try again.');
                    }
                    
                    if (!response.ok) {
                        console.error('Purchase failed with status:', response.status);
                        const errorMsg = result?.message || `Server error: ${response.status} ${response.statusText}`;
                        throw new Error(errorMsg);
                    }
                    
                    if (result.success) {
                        console.log('Purchase successful, updating UI...');
                        // Show success message and hide buttons
                        document.getElementById('purchaseForm').style.display = 'none';
                        document.getElementById('purchaseSuccess').style.display = 'block';
                        document.getElementById('confirmPurchaseBtn').style.display = 'none';
                        document.getElementById('cancelPurchaseBtn').style.display = 'none';
                        document.getElementById('closeAfterPurchaseBtn').style.display = 'inline-block';
                        
                        // Update UI to reflect the purchase
                        const nftCard = document.querySelector(`.nft-card-small[data-nft-id="${nftId}"]`);
                        if (nftCard) {
                            const buyButton = nftCard.querySelector('.buy-button');
                            if (buyButton) {
                                buyButton.textContent = 'Owned';
                                buyButton.disabled = true;
                                buyButton.classList.add('owned-button');
                                buyButton.style.opacity = '0.7';
                                console.log('Updated buy button for NFT:', nftId);
                            }
                        }
                        
                        // Refresh user balance
                        console.log('Refreshing user balance...');
                        try {
                            const balanceResponse = await fetch('api/get_balance.php');
                            const balanceData = await balanceResponse.json();
                            console.log('Balance response:', balanceData);
                            if (balanceData.success) {
                                const balance = parseFloat(balanceData.balance);
                                document.getElementById('userBalance').textContent = balance.toFixed(2) + ' LUCKY';
                                console.log('Updated balance display');
                            }
                        } catch (balanceError) {
                            console.error('Error refreshing balance:', balanceError);
                            // Don't fail the whole purchase if balance refresh fails
                        }
                        
                        console.log('Purchase flow completed successfully');
                    } else {
                        // Show error message from server
                        const errorMsg = result?.message || 'Purchase failed. Please try again.';
                        console.error('Purchase failed:', errorMsg);
                        throw new Error(errorMsg);
                    }
                } catch (error) {
                    console.error('Error during purchase:', {
                        error: error,
                        name: error.name,
                        message: error.message,
                        stack: error.stack
                    });
                    
                    // More specific error messages based on error type
                    let userMessage = 'An error occurred during purchase. ';
                    
                    if (error.name === 'TypeError' && error.message.includes('Failed to fetch')) {
                        userMessage += 'Network error. Please check your connection and try again.';
                    } else if (error.name === 'SyntaxError') {
                        userMessage += 'Invalid response from server. Please try again.';
                    } else {
                        userMessage += error.message || 'Please try again later.';
                    }
                    
                    alert(userMessage);
                    // Re-enable the confirm button
                    this.disabled = false;
                }
                
                return false;
            };
        }

        // Close after purchase button
        if (closeAfterBtn) {
            closeAfterBtn.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                closeModal();
                return false;
            };
        }

        // Close when clicking outside modal
        window.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal();
            }
        });

        // Close with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.style.display === 'flex') {
                closeModal();
            }
        });


    });
    </script>
    <script>
    // Search functionality
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Initializing search functionality...');
        
        const searchInput = document.getElementById('searchInput');
        const searchResults = document.getElementById('searchResults');
        const searchBar = document.getElementById('searchBar');
        let searchTimeout;
        
        if (!searchInput || !searchResults || !searchBar) {
            console.error('Search elements not found:', { searchInput, searchResults, searchBar });
            return;
        }
        
        console.log('Search elements found, setting up event listeners');
        
        // Handle search input
        searchInput.addEventListener('input', function(e) {
            const query = e.target.value.trim();
            console.log('Input event, query:', query);
            
            // Clear previous timeout
            clearTimeout(searchTimeout);
            
            // Hide results if query is empty
            if (!query) {
                searchResults.style.display = 'none';
                return;
            }
            
            // Show loading immediately
            searchResults.innerHTML = '<div class="no-results">Searching...</div>';
            searchResults.style.display = 'block';
            
            // Debounce search
            searchTimeout = setTimeout(() => {
                searchCollections(query);
            }, 300);
        });
        
        // Hide results when clicking outside
        document.addEventListener('click', function(e) {
            if (!searchBar.contains(e.target)) {
                searchResults.style.display = 'none';
            }
        });
        
        // Prevent closing when clicking inside search results
        searchResults.addEventListener('click', function(e) {
            e.stopPropagation();
            
            // Handle clicks on search result items
            const resultItem = e.target.closest('.search-result-item');
            if (resultItem && resultItem.dataset.collectionId) {
                window.location.href = `collection.php?collection_id=${resultItem.dataset.collectionId}`;
            }
        });
        
        // Function to search collections
        async function searchCollections(query) {
            console.log('Searching for:', query);
            
            if (!query) {
                searchResults.style.display = 'none';
                return;
            }
            
            try {
                const searchUrl = `api/search_collections.php?q=${encodeURIComponent(query)}`;
                console.log('Fetching from:', searchUrl);
                
                const response = await fetch(searchUrl, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Cache-Control': 'no-cache',
                        'Pragma': 'no-cache'
                    },
                    cache: 'no-store'
                });
                
                console.log('Response status:', response.status);
                
                // Check if response is OK
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                // Check if response is JSON
                const contentType = response.headers.get('content-type') || '';
                if (!contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error('Non-JSON response:', text);
                    throw new Error('Invalid response format from server');
                }
                
                const data = await response.json();
                console.log('Search results:', data);
                
                if (data && data.success !== undefined) {
                    if (data.results && data.results.length > 0) {
                        displaySearchResults(data.results);
                    } else {
                        displayNoResults('No matching collections found');
                    }
                } else {
                    throw new Error('Invalid response format');
                }
            } catch (error) {
                console.error('Search error:', error);
                displayNoResults('Error: ' + (error.message || 'Please try again.'));
            }
        }
        
        // Function to create a placeholder icon when logo is missing
        function createPlaceholderIcon() {
            const placeholder = document.createElement('div');
            placeholder.className = 'collection-placeholder';
            placeholder.innerHTML = '<span class="material-icons">image</span>';
            return placeholder;
        }

        // Function to display search results
        function displaySearchResults(results) {
            console.log('Displaying search results:', results);
            searchResults.innerHTML = '';
            
            if (!results || results.length === 0) {
                displayNoResults('No collections found');
                return;
            }
            
            // Create a container for the results
            const resultsContainer = document.createElement('div');
            resultsContainer.className = 'search-results-container';
            
            // Add each result to the container
            results.forEach(result => {
                const item = document.createElement('div');
                item.className = 'search-result-item';
                item.dataset.collectionId = result.collection_id;
                
                // Ensure logo URL is properly formatted
                let logoUrl = result.logo_url || '';
                // If logo URL is relative, prepend the base URL
                if (logoUrl && !logoUrl.startsWith('http') && !logoUrl.startsWith('/')) {
                    logoUrl = logoUrl.startsWith('uploads/') ? `../${logoUrl}` : `../uploads/collections/${logoUrl}`;
                }
                
                item.innerHTML = `
                    <div class="search-result-image">
                        ${logoUrl ? 
                            `<img src="${logoUrl}" alt="${result.collection_name || 'Collection'}" onerror="this.onerror=null; this.parentNode.replaceChild(createPlaceholderIcon(), this);">` : 
                            createPlaceholderIcon().outerHTML}
                    </div>
                    <div class="search-result-details">
                        <div class="search-result-title">${result.collection_name || 'Unnamed Collection'}</div>
                        <div class="search-result-subtitle">Collection #${result.collection_id}</div>
                    </div>
                `;
                
                resultsContainer.appendChild(item);
            });
            
            searchResults.appendChild(resultsContainer);
            searchResults.style.display = 'block';
        }
        
        // Function to display no results message
        function displayNoResults(message = 'No collections found') {
            console.log('Displaying no results message:', message);
            searchResults.innerHTML = `
                <div class="search-no-results">
                    <span class="material-icons">search_off</span>
                    <p>${message}</p>
                </div>`;
            searchResults.style.display = 'block';
        }
        
        console.log('Search functionality initialized');
    });
    </script>
</body>
</html>