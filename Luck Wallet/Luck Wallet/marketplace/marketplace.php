<?php
session_start();

// Database connection
$host = 'localhost';
$dbname = 'login';
$username_db = 'root';
$password_db = '';

// Initialize variables
$isLoggedIn = false;
$walletAddress = '';
$username = '';

// Create database connection
$conn = new mysqli($host, $username_db, $password_db, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

try {
    // Check if user is logged in
    if (isset($_SESSION['user_id'])) {
        $isLoggedIn = true;
        
        // Get user data from database
        $stmt = $conn->prepare("SELECT username, wallet_address FROM luck_wallet_users WHERE user_id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $username = $user['username'];
            $walletAddress = $user['wallet_address'];
            
            // Update session with fresh data
            $_SESSION['username'] = $username;
            $_SESSION['wallet_address'] = $walletAddress;
        } else {
            // User not found in database
            session_destroy();
            $isLoggedIn = false;
        }
        
        $stmt->close();
    }
} catch (Exception $e) {
    // Log the error (in a production environment, log to a file)
    error_log("Error fetching user data: " . $e->getMessage());
    // Don't expose errors to the user in production
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>LUCK Marketplace</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="styles/marketplace.css">
    <style>
        /* Wallet Dropdown Styles */
        .wallet-dropdown {
            position: relative;
            display: inline-block;
            margin-left: 10px;
        }
        
        .wallet-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        background: rgba(0, 191, 191, 0.1);
        border: 1px solid rgba(0, 191, 191, 0.3);
        border-radius: 8px;
        padding: 8px 16px;
        color: #e0e0e0;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 14px;
        font-weight: 500;
        }
        
        .wallet-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(106, 17, 203, 0.25);
        }
        
        .wallet-address {
            max-width: 100px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: inline-block;
            vertical-align: middle;
            font-family: 'Roboto Mono', monospace;
            font-size: 13px;
            letter-spacing: 0.3px;
        }
        
        .wallet-btn .material-icons {
            font-size: 18px;
            color: rgba(255, 255, 255, 0.9);
        }
        
        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background-color: #1e1e2d;
            min-width: 220px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
            z-index: 1000;
            border-radius: 8px;
            overflow: hidden;
            margin-top: 8px;
            border: 1px solid #2a2a3c;
        }
        
        .dropdown-content a {
            color: #e0e0e0;
            padding: 12px 16px;
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: all 0.2s ease;
            font-size: 14px;
            font-weight: 400;
            border-bottom: 1px solid #2a2a3c;
        }
        
        .dropdown-content a:last-child {
            border-bottom: none;
        }
        
        .dropdown-content a:hover {
            background-color: #2a2a3c;
            color: #ffffff;
        }
        
        .dropdown-content .material-icons {
            margin-right: 12px;
            font-size: 20px;
            color: #8a8a9e;
            width: 20px;
            text-align: center;
        }
        
        .show {
            display: block;
            animation: fadeIn 0.15s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Connect wallet button styles */
        .connect-wallet {
            display: flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #4a90e2, #6a11cb);
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            height: 40px;
        }
        
        .connect-wallet:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(106, 17, 203, 0.25);
        }
        
        .connect-wallet .material-icons {
            font-size: 18px;
        }
    </style>

</head>
<body>
    <div class="main-content-overlay" id="mainContentOverlay"></div>
    <aside class="sidebar-left" id="sidebarLeft">
        <a href="#" class="sidebar-logo-container" id="mobileMenuBtn">
            <img src="logo.png" alt="LUCK MARKETPLACE" class="sidebar-logo">
        </a>
        <a href="marketplace.php" class="sidebar-item active"><span class="material-icons">home</span> <span class="sidebar-text">Home</span></a>
        <a href="profile.php" class="sidebar-item"><span class="material-icons">person</span> <span class="sidebar-text">Profile</span></a>
        <a href="nft.php" class="sidebar-item"><span class="material-icons">image</span> <span class="sidebar-text">NFTs</span></a>
        <a href="http://localhost/Luck%20Wallet/index.html" class="sidebar-item" id="luckWebsiteBtn">
            <span class="material-icons">public</span>
            <span class="sidebar-text">Luck Website</span>
        </a>
    </aside>

    <div class="container" id="main-container">
        <header class="header">
            <div class="header-content">
                <div class="header-brand">
                    <!-- NEW BURGER ICON BUTTON FOR MOBILE -->
                    <button class="mobile-sidebar-toggle-btn" id="mobileSidebarToggleBtn">
                        <span class="material-icons">menu</span>
                    </button>
                    <h1 class="header-title">LUCK MARKETPLACE</h1>
                </div>
                <div class="header-right">
                    <div class="search-bar" id="searchBar">
                        <span class="material-icons search-icon">search</span>
                        <input type="text" id="searchInput" placeholder="Search collections..." autocomplete="off">
                        <div id="searchResults" class="search-results"></div>
                    </div>
                    <?php if ($isLoggedIn): ?>
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
                                <a href="#" onclick="logout(); return false;" role="menuitem" style="color: #ff6b6b;">
                                    <span class="material-icons" style="color: #ff6b6b;">logout</span>
                                    <span>Disconnect</span>
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <button class="connect-wallet" id="connectWalletBtn">
                            <span class="material-icons">account_balance_wallet</span>
                            <span>Connect Wallet</span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <main class="main-content">
            <section class="featured-collections">
    <div class="slideshow-container" id="featured-slideshow">
        <!-- Each slide is now wrapped in a 'single-slide-item' div -->
        <div class="single-slide-item">
            <img class="slideshow-image" src="slideshow/pixel-lads-hero.png" alt="Featured NFT 1">
            <div class="slideshow-text-overlay">
                 <h2>PixelLads</h2>
                 <p>Collect one-of-a-kind pixel art NFTs and join a dynamic digital community.</p>
            </div>
        </div>

        <div class="single-slide-item">
            <img class="slideshow-image" src="slideshow/dragonville-hero.png" alt="Featured NFT 2">
            <div class="slideshow-text-overlay">
                 <h2>DragonVille</h2>
                 <p>Collect unique pixel art dragons and join a legendary digital realm.</p>
            </div>
        </div>

        <div class="single-slide-item">
            <img class="slideshow-image" src="slideshow/gearheadz-hero.png" alt="Featured NFT 3">
            <div class="slideshow-text-overlay">
                 <h2>GearHeadz</h2>
                 <p>GearHeadz is a high-octane NFT collection that celebrates the most iconic cars ever built </p>
            </div>
        </div>

        <div class="single-slide-item">
            <!-- Placeholder image for demonstration, replace with actual image path -->
            <img class="slideshow-image" src="slideshow/pokemon.jpg" alt="Featured NFT 4">
            <div class="slideshow-text-overlay">
                 <h2>Pokemon</h2>
                 <p>Pokemon: Legends Reborn is an epic collection of pixel art NFTs, celebrating the most powerful and revered Pokemon of all time</p>
            </div>
        </div>

        <div class="single-slide-item">
            <img class="slideshow-image" src="slideshow/tinypaws-hero.png" alt="Featured NFT 5">
            <div class="slideshow-text-overlay">
                 <h2>tiny paws</h2>
                 <p>Say hello to Tiny Paws, the cutest pack on the blockchain! This heart-melting NFT collection features pixel-perfect pups from beloved breeds around the world.</p>
            </div>
        </div>

        

    </div>
</section>

            <section class="featured-nfts">
                <h2>Featured NFTs</h2>
                <div class="scroll-container">
                    <button class="scroll-button left" onclick="scrollCards('featured-nfts-grid', -1)"><span class="material-icons">chevron_left</span></button>
                    <div class="collections-grid" id="featured-nfts-grid">
                    <?php
                    // Featured collections with their IDs and hardcoded logo paths
                    $featuredCollections = [
                        ['id' => 8, 'name' => 'PixelLads', 'logo' => 'logos/pixel-lads-logo.png'],
                        ['id' => 3, 'name' => 'DragonVille', 'logo' => 'logos/dragonville-logo.png'],
                        ['id' => 6, 'name' => 'GearHeadz', 'logo' => 'logos/gearheadz-logo.png'],
                        ['id' => 10, 'name' => 'Pokemon', 'logo' => 'logos/pokeball.png'],
                        ['id' => 7, 'name' => 'Tiny Paws', 'logo' => 'logos/tinypaws-logo.png']
                    ];

                    foreach ($featuredCollections as $collection) {
                        // Get floor price for the collection
                        $floorPriceQuery = "SELECT MIN(CAST(price_luck AS DECIMAL(18,2))) as floor_price 
                                         FROM luck_wallet_nfts 
                                         WHERE collection_id = ? AND listed_for_sale = 1";
                        $stmt = $conn->prepare($floorPriceQuery);
                        $stmt->bind_param('i', $collection['id']);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $floorPrice = $result->fetch_assoc()['floor_price'] ?? '0.00';
                        $stmt->close();
                        
                        // Format the floor price
                        $formattedFloorPrice = number_format((float)$floorPrice, 2, '.', '');
                        ?>
                        <div class="collection-card" onclick="viewCollection(<?php echo $collection['id']; ?>)" style="cursor: pointer;">
                            <img src="<?php echo htmlspecialchars($collection['logo']); ?>" alt="<?php echo htmlspecialchars($collection['name']); ?>">
                            <div class="card-details">
                                <h3><?php echo htmlspecialchars($collection['name']); ?></h3>
                                <p>Floor price: <?php echo $formattedFloorPrice; ?> LUCK</p>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                    </div>
                    <button class="scroll-button right" onclick="scrollCards('featured-nfts-grid', 1)"><span class="material-icons">chevron_right</span></button>
                </div>
            </section>

            <!-- Mobile NFT List (visible only on small screens) -->
            <section class="mobile-nft-list" id="mobile-nft-list">
                <h2>NFT List</h2>
                <div class="scroll-container">
                    <button class="scroll-button left" onclick="scrollCards('mobile-nft-grid', -1)"><span class="material-icons">chevron_left</span></button>
                    <div class="collections-grid" id="mobile-nft-grid">
                        <div class="collection-card" onclick="viewCollection(8)">
                            <img src="logos/pixel-lads-logo.png" alt="PixelLads Logo">
                            <div class="card-details">
                                <h3>PixelLads</h3>
                            </div>
                        </div>
                        <div class="collection-card" onclick="viewCollection(3)">
                            <img src="logos/dragonville-logo.png" alt="DragonVille Logo">
                            <div class="card-details">
                                <h3>DragonVille</h3>
                            </div>
                        </div>
                        <div class="collection-card" onclick="viewCollection(6)">
                            <img src="logos/gearheadz-logo.png" alt="GearHeadz Logo">
                            <div class="card-details">
                                <h3>GearHeadz</h3>
                            </div>
                        </div>
                        <div class="collection-card" onclick="viewCollection(18)">
                            <img src="logos/purr-traits-logo.png" alt="Sanrio Logo">
                            <div class="card-details">
                                <h3>Sanrio</h3>
                            </div>
                        </div>
                        <div class="collection-card" onclick="viewCollection(12)">
                            <img src="logos/worldwar1-logo.png" alt="World War 1">
                            <div class="card-details">
                                <h3>World War 1</h3>
                            </div>
                        </div>
                        <div class="collection-card" onclick="viewCollection(13)">
                            <img src="logos/worldwar2-logo.png" alt="World War 2">
                            <div class="card-details">
                                <h3>World War 2</h3>
                            </div>
                        </div>
                        <div class="collection-card" onclick="document.querySelectorAll('.collection-item-button')[6].click()">
                            <img src="logos/cool-cats-logo.png" alt="Cool Cats Logo">
                            <div class="card-details">
                                <h3>Cool Cats</h3>
                                <p>Floor: 0.35 ETH <span class="price-change negative">-0.8%</span></p>
                            </div>
                        </div>
                        <div class="collection-card" onclick="document.querySelectorAll('.collection-item-button')[7].click()">
                            <img src="logos/voxies-logo.png" alt="Voxies Logo">
                            <div class="card-details">
                                <h3>Voxies</h3>
                                <p>Floor: 0.42 ETH <span class="price-change positive">+1.2%</span></p>
                            </div>
                        </div>
                        <div class="collection-card" onclick="document.querySelectorAll('.collection-item-button')[8].click()">
                            <img src="logos/solana-monkey-logo.png" alt="Solana Monkey Business Logo">
                            <div class="card-details">
                                <h3>Solana Monkeys</h3>
                                <p>Floor: 0.65 ETH <span class="price-change positive">+3.1%</span></p>
                            </div>
                        </div>
                        <div class="collection-card" onclick="document.querySelectorAll('.collection-item-button')[9].click()">
                            <img src="logos/cinnamoroll.png" alt="Cinnamoroll Logo">
                            <div class="card-details">
                                <h3>Cinnamoroll</h3>
                                <p>Floor: 0.18 ETH <span class="price-change positive">+0.9%</span></p>
                            </div>
                        </div>
                    </div>
                    <button class="scroll-button right" onclick="scrollCards('mobile-nft-grid', 1)"><span class="material-icons">chevron_right</span></button>
                </div>
            </section>

            <section class="recent-collections">
                <h2>Recent Collections</h2>
                <div class="scroll-container">
                    <button class="scroll-button left" onclick="scrollCards('recent-collections-grid', -1)">
                        <span class="material-icons">chevron_left</span>
                    </button>
                    <div class="collections-grid" id="recent-collections-grid">
                        <div class="loading">Loading collections...</div>
                    </div>
                    <button class="scroll-button right" onclick="scrollCards('recent-collections-grid', 1)">
                        <span class="material-icons">chevron_right</span>
                    </button>
                </div>
            </section>

            <section class="nft-101">
                <h2>NFT 101</h2>
                <div class="scroll-container">
                    <button class="scroll-button left" onclick="scrollCards('nft-101-grid', -1)"><span class="material-icons">chevron_left</span></button>
                    <div class="collections-grid" id="nft-101-grid">
                        <div class="collection-card" onclick="window.open('https://www.investopedia.com/non-fungible-tokens-nft-5115211', '_blank');">
                            <img src="101/what-is-nft.png" alt="What is NFT?">
                            <div class="card-details">
                                <h3>What is NFT?</h3>
                            </div>
                        </div>
                        <div class="collection-card" onclick="window.open('https://www.investopedia.com/terms/b/bitcoin-wallet.asp', '_blank');">
                            <img src="101/what-is-crypto-wallet.png" alt="What is a crypto wallet?">
                            <div class="card-details">
                                <h3>What is a crypto wallet?</h3>
                            </div>
                        </div>
                        <div class="collection-card" onclick="window.open('https://www.investopedia.com/how-to-buy-and-sell-nfts-6361693#:~:text=Non%2Dfungible%20tokens%20(NFTs)%20can%20be%20bought%20from%20marketplaces,compatible%20with%20the%20NFT%20platform.', '_blank');">
                            <img src="101/how-to-buy-nft.png" alt="How to buy an NFT">
                            <div class="card-details">
                                <h3>How to buy an NFT?</h3>
                            </div>
                        </div>
                        <div class="collection-card" onclick="window.open('https://corporatefinanceinstitute.com/resources/cryptocurrency/minting-crypto/', '_blank');">
                            <img src="101/what-is-minting.png" alt="What is minting?">
                            <div class="card-details">
                                <h3>What is minting?</h3>
                            </div>
                        </div>
                        <div class="collection-card" onclick="window.open('https://chainpatrol.io/blog/learning/ways-to-stay-safe-in-web3/', '_blank');">
                            <img src="101/stay-protected-web3.png" alt="How to stay protected in Web3">
                            <div class="card-details">
                                <h3>How to stay protected in Web3</h3>
                            </div>
                        </div>
                    </div>
                    <button class="scroll-button right" onclick="scrollCards('nft-101-grid', 1)"><span class="material-icons">chevron_right</span></button>
                </div>
            </section>
            
        </main>

        <aside class="sidebar-right" id="sidebar-right">
            <div class="sidebar-right-top-filters">
                 <h3 class="sidebar-collection-title">NFT List</h3>
            </div>
            <div class="collection-list">
                <button class="collection-item-button" onclick="viewCollection(8)">
                    <img src="logos/pixel-lads-logo.png" alt="PixelLads Logo" class="category-logo">
                </button>
                <button class="collection-item-button" onclick="viewCollection(3)">
                    <img src="logos/dragonville-logo.png" alt="DragonVille Logo" class="category-logo">
                </button>
                <button class="collection-item-button" onclick="viewCollection(6)">
                    <img src="logos/gearheadz-logo.png" alt="GearHeadz Logo" class="category-logo">
                </button>
                <button class="collection-item-button" onclick="viewCollection(9)">
                    <img src="logos/meowna lisa.png" alt="Sanrio Logo" class="category-logo">
                </button>
                <button class="collection-item-button" onclick="viewCollection(7)">
                    <img src="logos/tinypaws-logo.png" alt="Tiny Paws Logo" class="category-logo">
                </button>
                <button class="collection-item-button" onclick="viewCollection(17)">
                    <img src="logos/pixlrei.png" alt="Pixl.Rei" class="category-logo">
                </button>
                <button class="collection-item-button" onclick="viewCollection(10)">
                    <img src="logos/pokeball.png" alt="Pokemon Logo" class="category-logo">
                </button>
                <button class="collection-item-button" onclick="viewCollection(13)">
                    <img src="logos/worldwar.webp" alt="WorldWar Heroes - Mage" class="category-logo">
                </button>
                <button class="collection-item-button" onclick="viewCollection(12)">
                    <img src="logos/worldwar.webp" alt="WorldWar Heroes - Fighter" class="category-logo">

                </button>
                <button class="collection-item-button" onclick="viewCollection(18)">
                    <img src="logos/cinnamoroll.png" alt="Sanrio Logo" class="category-logo">

                </button>
            </div>
        </aside>
    </div>

    <!-- Login Modal Structure -->
    <div id="loginModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close-button">&times;</span>
                <h2>Login to Your Account</h2>
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
    </div>

    <style>
        /* Search Results Dropdown */
        .search-results {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #1e1e2d;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            margin-top: 5px;
            border: 1px solid #2a2a3c;
            scrollbar-width: thin;
            scrollbar-color: #3a3a4d #252538;
        }
        
        /* Webkit (Chrome, Safari, Edge) */
        .search-results::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        
        .search-results::-webkit-scrollbar-track {
            background: #252538;
            border-radius: 3px;
        }
        
        .search-results::-webkit-scrollbar-thumb {
            background-color: #3a3a4d;
            border-radius: 3px;
            transition: background-color 0.2s;
        }
        
        .search-results::-webkit-scrollbar-thumb:hover {
            background-color: #4a4a5d;
        }
        
        /* Firefox */
        @supports (scrollbar-color: auto) {
            .search-results {
                scrollbar-width: thin;
                scrollbar-color: #3a3a4d #252538;
            }
        }
        
        .search-result-item {
            padding: 12px 15px;
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .search-result-item:hover {
            background: #2a2a3c;
        }
        
        .search-result-item img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
        }
        
        .search-result-item .result-details {
            flex: 1;
            overflow: hidden;
        }
        
        .search-result-item h4 {
            margin: 0;
            font-size: 14px;
            font-weight: 500;
            color: #e0e0e0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .search-result-item p {
            margin: 2px 0 0;
            font-size: 12px;
            color: #8a8a9e;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .no-results {
            padding: 12px 15px;
            color: #8a8a9e;
            font-size: 14px;
            text-align: center;
        }
    </style>
    
    <script>
        // Search functionality
        let searchTimeout;
        const searchInput = document.getElementById('searchInput');
        const searchResults = document.getElementById('searchResults');
        const searchBar = document.getElementById('searchBar');
        
        if (searchInput && searchResults && searchBar) {
            // Handle search input
            searchInput.addEventListener('input', function(e) {
                const query = e.target.value.trim();
                
                // Clear previous timeout
                clearTimeout(searchTimeout);
                
                // Hide results if query is empty
                if (!query) {
                    searchResults.style.display = 'none';
                    return;
                }
                
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
            });
        }
        
        // Function to search collections
        async function searchCollections(query) {
            if (!query) {
                searchResults.style.display = 'none';
                return;
            }
            
            // Show loading state
            searchResults.innerHTML = '<div class="no-results">Searching...</div>';
            searchResults.style.display = 'block';
            
            try {
                // Trim and clean the query
                const cleanQuery = query.trim();
                if (!cleanQuery) {
                    searchResults.style.display = 'none';
                    return;
                }
                
                // Debug: Log the search URL
                const searchUrl = `api/search_collections.php?q=${encodeURIComponent(cleanQuery)}`;
                console.log('Searching with URL:', searchUrl);
                
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
                console.error('Search error details:', {
                    error: error.message,
                    stack: error.stack
                });
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
            searchResults.innerHTML = '';
            
            results.forEach(result => {
                const item = document.createElement('div');
                item.className = 'search-result-item';
                
                // Ensure logo URL is properly formatted
                let logoUrl = result.logo_url;
                // If logo URL is relative, prepend the base URL
                if (logoUrl && !logoUrl.startsWith('http') && !logoUrl.startsWith('/')) {
                    logoUrl = logoUrl.startsWith('uploads/') ? `../${logoUrl}` : `../uploads/collections/${logoUrl}`;
                }
                
                item.innerHTML = `
                    ${logoUrl ? 
                        `<img src="${logoUrl}" alt="${result.collection_name || 'Collection'}" onerror="this.onerror=null; this.parentNode.replaceChild(createPlaceholderIcon(), this);">` : 
                        createPlaceholderIcon().outerHTML}
                    <div class="result-details">
                        <h4>${result.collection_name || 'Unnamed Collection'}</h4>
                        <p>Collection #${result.collection_id}</p>
                    </div>
                `;
                
                item.addEventListener('click', () => {
                    window.location.href = `collection.php?collection_id=${result.collection_id}`;
                });
                
                searchResults.appendChild(item);
            });
            
            searchResults.style.display = 'block';
        }
        

        
        // Function to create a placeholder icon when logo is missing
        function createPlaceholderIcon() {
            const placeholder = document.createElement('div');
            placeholder.className = 'collection-placeholder';
            placeholder.innerHTML = '<span class="material-icons">image</span>';
            return placeholder;
        }

        // Function to display no results message
        function displayNoResults(message = 'No collections found') {
            searchResults.innerHTML = `<div class="no-results">${message}</div>`;
            searchResults.style.display = 'block';
        }
        
        // Function to handle collection clicks from other parts of the page
        function viewCollection(collectionId) {
            console.log('Viewing collection:', collectionId);
            window.location.href = `collection.php?collection_id=${collectionId}`;
        }

        // Toggle wallet dropdown
        function toggleDropdown() {
            document.getElementById('walletDropdown').classList.toggle('show');
        }

        // Close the dropdown if clicked outside
        window.onclick = function(event) {
            if (!event.target.matches('.wallet-btn') && !event.target.closest('.wallet-btn')) {
                const dropdowns = document.getElementsByClassName('dropdown-content');
                for (let i = 0; i < dropdowns.length; i++) {
                    const openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
                }
            }
        }

        // Handle logout
        async function logout() {
            try {
                const response = await fetch('api/logout.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'same-origin'
                });
                
                const result = await response.json();
                if (result.success) {
                    // Reload the page to reflect the logged out state
                    window.location.reload();
                } else {
                    console.error('Logout failed:', result.message);
                    alert('Failed to log out. Please try again.');
                }
            } catch (error) {
                console.error('Error during logout:', error);
                alert('An error occurred during logout. Please try again.');
            }
        }

        let slideIndex = 0;
        let slideshowInterval; // Variable to hold the interval ID

        document.addEventListener('DOMContentLoaded', function() {
            // Get all DOM elements needed for mobile functionality
            const mobileMenuBtn = document.getElementById('mobileMenuBtn'); // This was the sidebar logo
            const sidebarLeft = document.querySelector('.sidebar-left');
            const mobileSearchToggle = document.getElementById('mobileSearchToggle');
            const searchBar = document.getElementById('searchBar'); /* Get by ID for clarity */
            const searchInput = searchBar.querySelector('input'); // Get input inside search bar
            const overlay = document.querySelector('.main-content-overlay');
            const sidebarItems = document.querySelectorAll('.sidebar-item');
            const sidebarLeftItemIcons = document.querySelectorAll('.sidebar-item .material-icons'); // Corrected selector for icons
            const mainContentOverlay = document.querySelector('.main-content-overlay');
            const connectWalletBtn = document.getElementById("connectWalletBtn");
            const modal = document.getElementById("loginModal");
            const closeButton = document.getElementsByClassName("close-button")[0];

            // NEW: Get the mobile sidebar toggle button
            const mobileSidebarToggleBtn = document.getElementById('mobileSidebarToggleBtn');

            let isTransitioning = false;
            const TRANSITION_DURATION = 300; // Match this with CSS transition duration

            // Function to toggle mobile menu visibility
            function toggleMobileMenu() {
                if (isTransitioning) return; // Prevent rapid clicks during transition
                
                isTransitioning = true;
                const isOpening = !sidebarLeft.classList.contains('mobile-visible');
                
                if (isOpening) {
                    // Close search if open
                    searchBar.classList.remove('expanded-mobile'); /* Ensure search bar is not expanded */
                    sidebarLeft.classList.add('mobile-visible');
                    overlay.classList.add('active');
                    document.body.style.overflow = 'hidden'; // Prevent scrolling when menu is open
                } else {
                    sidebarLeft.classList.remove('mobile-visible');
                    overlay.classList.remove('active');
                    document.body.style.overflow = ''; // Re-enable scrolling
                }
                
                // Allow time for CSS transition to complete before re-enabling clicks
                setTimeout(() => {
                    isTransitioning = false;
                }, TRANSITION_DURATION);
            }

            // Function to toggle search bar visibility and expansion
            function toggleSearchBarExpansion() {
                if (isTransitioning) return; // Prevent rapid clicks during transition
                
                isTransitioning = true;
                const isExpanded = searchBar.classList.contains('expanded-mobile');
                
                if (!isExpanded) {
                    // Expand search bar
                    // Close sidebar if open
                    sidebarLeft.classList.remove('mobile-visible');
                    searchBar.classList.add('expanded-mobile');
                    // Don't add overlay when search is active to prevent blurring
                    // overlay.classList.add('active');
                    document.body.classList.add('search-expanded');
                    
                    if (searchInput) {
                        // Small delay to ensure the input is visible before focusing
                        setTimeout(() => {
                            searchInput.focus({ preventScroll: true });
                            // Select text only if there's content
                            if (searchInput.value) {
                                searchInput.select();
                            }
                        }, 100);
                    }
                } else {
                    // Collapse search bar
                    searchBar.classList.remove('expanded-mobile');
                    // Only remove overlay if it's not needed for other menus
                    if (!sidebarLeft.classList.contains('mobile-visible')) {
                        overlay.classList.remove('active');
                    }
                    document.body.classList.remove('search-expanded');
                    
                    if (searchInput) {
                        // Don't clear the input to preserve search history
                        searchInput.blur();
                    }
                }
                
                setTimeout(() => {
                    isTransitioning = false;
                }, TRANSITION_DURATION);
            }


            // Function to close all mobile menus (sidebar and search bar)
            function closeAllMenus() {
                if (isTransitioning) return; // Prevent rapid clicks during transition
                
                isTransitioning = true;
                if (overlay) overlay.classList.remove('active');
                if (sidebarLeft) sidebarLeft.classList.remove('mobile-visible');
                if (searchBar) searchBar.classList.remove('expanded-mobile'); /* Collapse search bar */
                document.body.style.overflow = ''; // Re-enable scrolling
                
                // Remove focus from any focused elements
                if (document.activeElement) {
                    document.activeElement.blur();
                }
                
                // Allow time for CSS transition to complete before re-enabling clicks
                setTimeout(() => {
                    isTransitioning = false;
                }, TRANSITION_DURATION);
            }

            // Event listener for the NEW mobile sidebar toggle button
            if (mobileSidebarToggleBtn && sidebarLeft) {
                mobileSidebarToggleBtn.addEventListener('click', toggleMobileMenu);
            }

            // Event listener for search bar (or its icon) to toggle expansion
            if (searchBar) {
                searchBar.addEventListener('click', function(event) {
                    // Prevent propagation to overlay if clicking inside search bar
                    event.stopPropagation();
                    toggleSearchBarExpansion();
                });
                // Prevent form submission if the search icon is clicked
                searchBar.querySelector('.search-icon').addEventListener('click', function(event) {
                    event.preventDefault();
                });
            }


            // Close menus when clicking the overlay
            if (overlay) {
                overlay.addEventListener('click', closeAllMenus);
            }

            // Handle sidebar item clicks for both mobile and desktop
            sidebarItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    // Only prevent default for non-navigation items
                    if (!this.getAttribute('href') || this.getAttribute('href') === '#') {
                        e.preventDefault();
                        
                        // Update active state for non-navigation items
                        sidebarItems.forEach(i => i.classList.remove('active'));
                        this.classList.add('active');
                        
                        // Close menu on mobile after clicking
                        if (window.innerWidth <= 1024) {
                            closeAllMenus();
                        }
                        
                        // Close search results
                        const searchResults = document.getElementById('searchResults');
                        if (searchResults) {
                            searchResults.style.display = 'none';
                        }
                    } else {
                        // For navigation links, close menu on mobile after clicking
                        if (window.innerWidth <= 1024) {
                            closeAllMenus();
                        }
                        // Allow the default navigation to happen
                        return true;
                    }
                });
            });

            // Handle right sidebar general filter buttons active state (for time filters like Top, Trending, 1d)
            const filterButtonsSmall = document.querySelectorAll('.filter-button-small');
            filterButtonsSmall.forEach(button => {
                button.addEventListener('click', () => {
                    filterButtonsSmall.forEach(btn => btn.classList.remove('active'));
                    button.classList.add('active');
                    console.log(`Filter selected: ${button.textContent}`);
                });
            });

            // Handle collection item buttons active state in the right sidebar
            const collectionItemButtons = document.querySelectorAll('.collection-item-button');
            collectionItemButtons.forEach(button => {
                button.addEventListener('click', () => {
                    collectionItemButtons.forEach(btn => btn.classList.remove('active'));
                    button.classList.add('active');
                    // In a real application, you might filter the displayed NFTs here
                    console.log(`Collection item selected: ${button.textContent}`);
                });
            });

            // Left sidebar hover functionality - only for desktop
            // This expands the sidebar on hover and adds an overlay to the main content
            if (window.innerWidth > 1024 && sidebarLeft) {
                sidebarLeft.addEventListener('mouseover', () => {
                    sidebarLeft.style.width = '250px'; // Expand left sidebar
                    
                    // Adjust icon margin for expanded view
                    if (sidebarLeftItemIcons) {
                        sidebarLeftItemIcons.forEach(icon => {
                            icon.style.marginRight = '10px';
                        });
                    }

                    // Activate overlay to dim main content
                    if (mainContentOverlay) {
                        mainContentOverlay.classList.add('active');
                    }
                });

                sidebarLeft.addEventListener('mouseout', () => {
                    sidebarLeft.style.width = '80px'; // Collapse left sidebar
                    
                    // Revert icon margin for collapsed view
                    if (sidebarLeftItemIcons) {
                        sidebarLeftItemIcons.forEach(icon => {
                            icon.style.marginRight = '0px';
                        });
                    }
                    // Deactivate overlay
                    if (mainContentOverlay) {
                        mainContentOverlay.classList.remove('active');
                    }
                });
            }

            // Initialize slideshow and start automatic playback
            const allSlides = document.getElementsByClassName("single-slide-item");
            for (let i = 5; i < allSlides.length; i++) {
                allSlides[i].style.display = 'none';
            }
            showSlides(0); // Start with first slide
            startSlideshow();

            // When the "Connect Wallet" button is clicked, display the modal
            if (connectWalletBtn) { // Check if the button exists before attaching event listener
                connectWalletBtn.onclick = function() {
                    modal.style.display = "flex"; // Use flex to center the modal content
                }
            }

            // Function to switch between views
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

            // When the user clicks on the 'x' (close) button, hide the modal and reset to login view
            if (closeButton) {
                closeButton.onclick = function() {
                    modal.style.display = "none";
                    // Reset to login view when closing
                    setTimeout(() => showView('loginView'), 300);
                }
            }

            // When the user clicks anywhere outside of the modal, close it and reset to login view
            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = "none";
                    // Reset to login view when closing
                    setTimeout(() => showView('loginView'), 300);
                }
            }

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
            
            // Handle LuckyTime login form submission
            document.getElementById('luckyTimeLoginForm')?.addEventListener('submit', async function(e) {
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
                    submitBtn.innerHTML = originalBtnText;
                }
            });

            document.getElementById('backToLogin2')?.addEventListener('click', function(e) {
                e.preventDefault();
                showView('loginView');
            });

            document.querySelectorAll('.back-to-login').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    showView('loginView');
                });
            });

            document.getElementById('backToLogin2')?.addEventListener('click', function(e) {
                e.preventDefault();
                showView('loginView');
            });

            // Form submissions
            document.getElementById('loginForm')?.addEventListener('submit', function(event) {
                event.preventDefault();
                
                const email = document.getElementById('email').value;
                const password = document.getElementById('password').value;
                const redirectUrl = '<?php echo isset($_SESSION['redirect_url']) ? htmlspecialchars($_SESSION['redirect_url'], ENT_QUOTES, 'UTF-8') : 'profile.php'; ?>';
                
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
                        // Redirect to the stored URL or profile page
                        window.location.href = redirectUrl || 'profile.php';
                        
                        // Clear the redirect URL from session
                        fetch('api/clear_redirect.php', {
                            method: 'POST',
                            credentials: 'same-origin'
                        });
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
                    alert('Passwords do not match!');
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
                submitBtn.textContent = 'Creating account...';
                
                // Prepare form data
                const formData = new URLSearchParams();
                formData.append('signup_type', 'standard');
                formData.append('username', username);
                formData.append('email', email);
                formData.append('password', password);
                formData.append('confirmPassword', confirmPassword);
                
                // Send signup request
                fetch('/Luck%20Wallet/auth/process_signup.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        alert('Account created successfully! Please log in.');
                        showView('loginView');
                    } else {
                        throw new Error(data.message || 'Error creating account');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert(error.message || 'An error occurred. Please try again.');
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalBtnText;
                });
            });

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
                
                // Basic email validation
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    alert('Please enter a valid email address');
                    return;
                }
                
                // Password validation
                if (newPassword.length < 6) {
                    alert('Password must be at least 6 characters long');
                    return;
                }
                
                if (newPassword !== confirmNewPassword) {
                    alert('Passwords do not match');
                    return;
                }
                
                // Show loading state
                const submitBtn = event.target.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.textContent = 'Resetting...';
                
                // Prepare request data
                const requestData = {
                    email: email,
                    newPassword: newPassword,
                    confirmPassword: confirmNewPassword
                };
                
                // Send password reset request
                fetch('/Luck%20Wallet/auth/api_forgot_password.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(requestData),
                    credentials: 'same-origin'
                })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(err => {
                            throw new Error(err.message || 'Network response was not ok');
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        alert('Password has been reset successfully! You can now log in with your new password.');
                        // Clear form
                        document.getElementById('forgotForm').reset();
                        // Show login view
                        showView('loginView');
                    } else {
                        throw new Error(data.message || 'Failed to reset password');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert(error.message || 'An error occurred. Please try again.');
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalBtnText;
                });
            });

            // Handle "Sign Up" link click (for demonstration)
            document.getElementById('signupLink').addEventListener('click', function(event) {
                event.preventDefault();
                // In a real application, you would navigate to a sign-up page or show a sign-up form.
                console.log('Redirecting to Sign Up page (demo)');
            });

            // Handle "Forgot Password" link click (for demonstration)
            document.getElementById('forgotPasswordLink').addEventListener('click', function(event) {
                event.preventDefault();
                // In a real application, you would navigate to a forgot password page or show a reset form.
                console.log('Redirecting to Forgot Password page (demo)');
            });

            // Handle clicks on wallet connection buttons (for demonstration)
            document.querySelectorAll('.wallet-button').forEach(button => {
                button.addEventListener('click', function() {
                    // In a real application, you would initiate wallet connection logic here.
                    // Instead of alert(), you could show a custom message or update UI
                    console.log('Connecting to ' + this.textContent.trim() + ' (demo)');
                });
            });
        }); /* End of DOMContentLoaded */

        function loadRecentCollections() {
            const container = document.getElementById('recent-collections-grid');
            if (!container) return;

            // Create a loading indicator
            const loadingIndicator = document.createElement('div');
            loadingIndicator.className = 'loading';
            loadingIndicator.textContent = 'Loading collections...';
            container.innerHTML = '';
            container.appendChild(loadingIndicator);

            // Add a timestamp to prevent caching issues
            const timestamp = new Date().getTime();
            
            fetch(`/Luck%20Wallet/auth/api_get_recent_collections.php?t=${timestamp}`, {
                cache: 'no-store',
                headers: {
                    'Cache-Control': 'no-cache, no-store, must-revalidate',
                    'Pragma': 'no-cache',
                    'Expires': '0'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json().then(data => {
                    console.log('API Response:', JSON.stringify(data, null, 2));
                    return data;
                });
            })
            .then(data => {
                container.innerHTML = ''; // Clear loading indicator
                
                if (data.success && Array.isArray(data.data) && data.data.length > 0) {
                    data.data.forEach(collection => {
                        const card = document.createElement('div');
                        card.className = 'collection-card';
                        // Add click handler to navigate to collection page
                        card.onclick = function() {
                            window.location.href = 'collection.php?collection_id=' + (collection.collection_id || collection.id || '');
                        };
                        card.style.cursor = 'pointer'; // Add pointer cursor to indicate clickable
                        
                        // Get logo URL from response (checking both 'logo' and 'logo_url')
                        let logoUrl = collection.logo || collection.logo_url || '';
                        
                        // If logo URL is relative, make it absolute
                        if (logoUrl && !logoUrl.startsWith('http') && !logoUrl.startsWith('/')) {
                            logoUrl = '/Luck%20Wallet/' + logoUrl;
                        }
                        
                        // If no valid logo URL, use placeholder
                        const finalLogoUrl = logoUrl || 'https://placehold.co/300x200/1a1a1a/00bfbf?text=No+Image';
                        
                        card.innerHTML = `
                            <img src="${finalLogoUrl}" 
                                 alt="${collection.name || 'Collection'}" 
                                 onerror="this.onerror=null; this.src='https://placehold.co/300x200/1a1a1a/00bfbf?text=No+Image'">
                            <div class="card-details">
                                <h3>${collection.name || 'Unnamed Collection'}</h3>
                                <p>Floor: ${collection.floor_price || collection.floor || 'N/A'}</p>
                                <p>Items: ${collection.nft_count || collection.item_count || 0}</p>
                            </div>
                        `;
                        container.appendChild(card);
                    });
                } else {
                    container.innerHTML = '<div class="loading">No collections available</div>';
                }
            })
            .catch(error => {
                console.error('Error loading collections:', error);
                container.innerHTML = '<div class="loading" style="color: #ff6b6b;">Error loading collections</div>';
            });
        }

        // Load recent collections when the page loads
        document.addEventListener('DOMContentLoaded', loadRecentCollections);

        // Function to start the automatic slideshow
        function startSlideshow() {
            // Clear any existing interval to prevent multiple intervals running concurrently
            clearInterval(slideshowInterval);
            // Set a new interval for automatic slide changes (e.g., every 3 seconds)
            slideshowInterval = setInterval(() => {
                plusSlides(1); // Call plusSlides to advance to the next slide
            }, 3000); // Change image every 3 seconds
        }

        // Function to advance or go back in the slideshow
        function plusSlides(n) {
            let newIndex = slideIndex + n;
            // Only show slides 0-4 (5 slides total)
            if (newIndex >= 5) newIndex = 0;
            if (newIndex < 0) newIndex = 4;
            showSlides(newIndex);
        }

        // Main slideshow logic to display a specific slide
        function showSlides(n) {
            // Ensure we only work with the first 5 slides
            const totalSlides = 5;
            
            // Update the slide index
            slideIndex = n;
            
            // Get all slide elements
            let slides = document.getElementsByClassName("single-slide-item");
            
            // If no slides are found, exit the function
            if (slides.length === 0) return;
            
            // Ensure we don't go out of bounds
            if (slideIndex >= totalSlides) slideIndex = 0;
            if (slideIndex < 0) slideIndex = totalSlides - 1;
            
            // Hide all slides by removing the 'active' class
            for (let i = 0; i < totalSlides; i++) {
                if (i < slides.length) {
                    slides[i].classList.remove('active');
                }
            }
            
            // Show the current slide by adding the 'active' class
            if (slideIndex < slides.length) {
                slides[slideIndex].classList.add('active');
            }
        }
    
        // Function to scroll cards horizontally within a given container
        function scrollCards(containerId, direction) {
            const container = document.getElementById(containerId);
            if (container) {
                let scrollAmount;
                // Determine scroll amount based on the container (mobile NFT grid has smaller cards)
                if (containerId === 'mobile-nft-grid') {
                    scrollAmount = 160 * direction; // 150px card width + 10px margin
                } else {
                    scrollAmount = 320 * direction; // Default 300px card width + 20px margin
                }
                
                // Perform the smooth scroll
                container.scrollBy({
                    left: scrollAmount,
                    behavior: 'smooth'
                });
            }
        }
    </script>
</body>
</html>
