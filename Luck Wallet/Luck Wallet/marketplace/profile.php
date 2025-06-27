<script>
// Function to open the login modal - defined in global scope at the very beginning
window.openLoginModal = function() {

    const modal = document.getElementById('loginModal');
    if (modal) {

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
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in, if not redirect to login page
if (!isset($_SESSION['user_id']) || !isset($_SESSION['wallet_address'])) {
    // Store the current URL in session to redirect back after login
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header('Location: marketplace.php?login_required=1');
    exit();
}

// Include database connection
require_once __DIR__ . '/../db_connect.php';

// Debug: Check if database connection is working
try {
    // Test database connection
    $test = $pdo->query("SELECT 1")->fetch();
    error_log("Database connection test: " . ($test ? 'Success' : 'Failed'));
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("Database connection error. Please try again later.");
}

// Debug: Check session and get user data
$wallet = '';
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    error_log("User ID in session: " . $userId);
    
    // Get user's wallet address
    try {
        $stmt = $pdo->prepare("SELECT wallet_address FROM luck_wallet_users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if ($user) {
            $wallet = $user['wallet_address'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching user wallet: " . $e->getMessage());
    }
} else {
    error_log("No user ID in session");
}

// Initialize variables
$isLoggedIn = isset($_SESSION['user_id']);
$walletAddress = $wallet; // From the earlier initialization
$username = 'Guest';
$profileImage = 'logo.png'; // Default profile image
$balance = 0;
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;

// Check if PDO connection is available
if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('Database connection failed. Please check your configuration.');
}

// No collection_id required - we'll show all NFTs
session_start();
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
    <style>
        /* Profile Picture Upload Styles */
        .profile-picture-container {
            position: relative;
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            cursor: pointer;
            border: 3px solid #00BFBF;
            box-shadow: 0 0 15px rgba(0, 191, 191, 0.5);
            transition: all 0.3s ease;
        }
        
        .profile-picture-container:hover {
            transform: scale(1.05);
            box-shadow: 0 0 25px rgba(0, 191, 191, 0.7);
        }
        
        .profile-picture-container .edit-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            color: white;
            font-size: 14px;
            text-align: center;
            pointer-events: none;
        }
        
        .profile-picture-container:hover .edit-overlay {
            opacity: 1;
        }
        
        #profileImageInput {
            display: none;
        }
        
        /* Loading Spinner Styles */
        .upload-loading {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid transparent;
            border-top-color: #00BFBF;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Message Toast Styles */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            z-index: 9999;
            transform: translateX(120%);
            transition: transform 0.3s ease-in-out;
            max-width: 350px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .toast.show {
            transform: translateX(0);
        }
        
        .toast.success {
            background: #00C853;
            border-left: 4px solid #00E676;
        }
        
        .toast.error {
            background: #FF3D00;
            border-left: 4px solid #FF6E40;
        }
        
        .toast.info {
            background: #2196F3;
            border-left: 4px solid #64B5F6;
        }
        
        .toast .close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 18px;
            cursor: pointer;
            margin-left: 15px;
            padding: 0 5px;
            opacity: 0.8;
            transition: opacity 0.2s;
        }
        
        .toast .close-btn:hover {
            opacity: 1;
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="/Luck%20Wallet/marketplace/styles/profile.css">
    <style>
        /* Animations for remove listing modal */
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes modalFadeOut {
            from { opacity: 1; transform: translateY(0); }
            to { opacity: 0; transform: translateY(20px); }
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Modal enter/exit animations */
        #removeListingModal[style*="display: block"] .modal-content {
            animation: modalFadeIn 0.3s ease-out forwards;
        }
        
        #removeListingModal[style*="opacity: 0"] .modal-content {
            animation: modalFadeOut 0.3s ease-in forwards;
        }
        
        /* Button hover/focus states */
        #confirmRemoveBtn:focus {
            outline: 2px solid rgba(255, 255, 255, 0.5);
            outline-offset: 2px;
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 20px rgba(255, 77, 77, 0.4) !important;
        }
        
        /* Responsive adjustments */
        @media (max-width: 480px) {
            #removeListingModal .modal-content {
                width: 95% !important;
                margin: 20% auto !important;
                padding: 20px !important;
            }
            
            .modal-footer {
                flex-direction: column;
                gap: 10px !important;
            }
            
            .modal-footer button {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/web3@1.5.2/dist/web3.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@metamask/detect-provider/dist/detect-provider.min.js"></script>
    
    <script>
    // Remove Listing Modal Management
    window.removeListingModal = {
        currentNftId: null,
        isAnimating: false,
        
        init: function() {
            const modal = document.getElementById('removeListingModal');
            const confirmBtn = document.getElementById('confirmRemoveBtn');
            
            if (!modal || !confirmBtn) {
                console.error('Modal elements not found');
                return;
            }
            
            // Handle confirm button click
            confirmBtn.onclick = async (e) => {
                e.stopPropagation();
                if (this.isAnimating) return;
                
                if (!this.currentNftId) {
                    console.error('No NFT ID set for removal');
                    return;
                }
                
                // Add loading state
                const originalText = confirmBtn.innerHTML;
                confirmBtn.disabled = true;
                confirmBtn.innerHTML = '<i class="material-icons" style="margin-right: 8px; animation: spin 1s linear infinite;">autorenew</i> Removing...';
                
                try {
                    const response = await fetch('api/remove_listing.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            nft_id: this.currentNftId
                        })
                    });
                    
                    if (!response.ok) {
                        const errorText = await response.text();
                        throw new Error(`HTTP error! status: ${response.status}, body: ${errorText}`);
                    }
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        // Show success state briefly before reloading
                        confirmBtn.innerHTML = '<i class="material-icons" style="margin-right: 8px;">check_circle</i> Removed!';
                        confirmBtn.style.background = 'linear-gradient(135deg, #4CAF50, #2E7D32)';
                        setTimeout(() => window.location.reload(), 800);
                    } else {
                        throw new Error(result.message || 'Failed to remove listing');
                    }
                } catch (error) {
                    console.error('Error removing listing:', error);
                    // Show error state
                    confirmBtn.innerHTML = '<i class="material-icons" style="margin-right: 8px;">error</i> Error';
                    confirmBtn.style.background = 'linear-gradient(135deg, #f44336, #c62828)';
                    
                    // Revert after delay
                    setTimeout(() => {
                        confirmBtn.innerHTML = originalText;
                        confirmBtn.style.background = 'linear-gradient(135deg, #ff4d4d, #cc0000)';
                        confirmBtn.disabled = false;
                    }, 2000);
                    
                    // Show error message in a non-blocking way
                    const errorMsg = document.createElement('div');
                    errorMsg.textContent = error.message || 'Failed to remove listing. Please try again.';
                    errorMsg.style.color = '#ff6b6b';
                    errorMsg.style.marginTop = '15px';
                    errorMsg.style.fontSize = '0.9rem';
                    confirmBtn.parentNode.insertBefore(errorMsg, confirmBtn.nextSibling);
                    
                    // Remove error message after delay
                    setTimeout(() => {
                        if (errorMsg.parentNode) {
                            errorMsg.parentNode.removeChild(errorMsg);
                        }
                    }, 3000);
                }
            };
            
            // Close modal when clicking outside content or on close button
            modal.addEventListener('click', (e) => {
                if (e.target === modal || e.target.classList.contains('close-modal')) {
                    this.close();
                }
            });
            
            // Handle Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && modal.style.display === 'block') {
                    this.close();
                }
            });
            

        },
        
        open: function(nftId) {
            if (this.isAnimating) return;
            

            this.currentNftId = nftId;
            const modal = document.getElementById('removeListingModal');
            const modalContent = modal ? modal.querySelector('.modal-content') : null;
            
            if (modal && modalContent) {
                this.isAnimating = true;
                
                // Reset modal state
                const confirmBtn = document.getElementById('confirmRemoveBtn');
                if (confirmBtn) {
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = '<i class="material-icons" style="font-size: 1.2rem; margin-right: 8px;">delete_forever</i> Remove Listing';
                    confirmBtn.style.background = 'linear-gradient(135deg, #ff4d4d, #cc0000)';
                }
                
                // Show modal with animation
                modal.style.display = 'block';
                modal.style.opacity = '0';
                modalContent.style.transform = 'translateY(-20px)';
                
                // Trigger reflow
                void modal.offsetHeight;
                
                // Animate in
                modal.style.opacity = '1';
                modalContent.style.transform = 'translateY(0)';
                
                // Focus the confirm button for better keyboard navigation
                setTimeout(() => {
                    if (confirmBtn) confirmBtn.focus();
                    this.isAnimating = false;
                }, 100);
                
                return true;
            } else {
                console.error('Remove listing modal not found');
                // Fallback to confirm dialog
                if (confirm('Are you sure you want to remove this NFT from listing?')) {
                    this.confirmRemove();
                }
                return false;
            }
        },
        
        close: function() {
            if (this.isAnimating) return;
            

            const modal = document.getElementById('removeListingModal');
            const modalContent = modal ? modal.querySelector('.modal-content') : null;
            
            if (modal && modalContent) {
                this.isAnimating = true;
                
                // Animate out
                modal.style.opacity = '0';
                modalContent.style.transform = 'translateY(-20px)';
                
                // Hide after animation
                setTimeout(() => {
                    modal.style.display = 'none';
                    this.currentNftId = null;
                    this.isAnimating = false;
                }, 300);
            }
        },
        
        // Fallback confirm method if needed
        confirmRemove: async function() {
            if (!this.currentNftId) return;
            
            try {
                const response = await fetch('api/remove_listing.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `nft_id=${encodeURIComponent(this.currentNftId)}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    window.location.reload();
                } else {
                    throw new Error(result.message || 'Failed to remove listing');
                }
            } catch (error) {
                console.error('Error removing listing:', error);
                alert('An error occurred while removing the listing: ' + (error.message || 'Unknown error'));
            }
        }
    };
    
    // Send NFT Modal Management
    window.sendNftModal = {
        currentNftId: null,
        currentNftData: null,
        isAnimating: false,
        initialized: false,
        recipientValid: false,
        
        // Open modal
        open: async function(nftId) {
            console.log('Opening modal for NFT ID:', nftId);
            
            if (this.isAnimating) {
                console.log('Modal animation in progress, ignoring open request');
                return;
            }
            
            this.currentNftId = nftId;
            this.recipientValid = false;
            
            // Get modal elements
            const modal = document.getElementById('sendNftModal');
            const recipientInput = document.getElementById('recipientAddress');
            const addressError = document.getElementById('addressError');
            
            if (!modal) {
                console.error('Send NFT modal not found in DOM');
                return;
            }
            
            // Reset form
            if (recipientInput) recipientInput.value = '';
            if (addressError) {
                addressError.textContent = '';
                addressError.style.display = 'none';
            }
            
            // Show loading state
            const nftImagePlaceholder = document.getElementById('nftImagePlaceholder');
            if (nftImagePlaceholder) {
                nftImagePlaceholder.innerHTML = '<i class="material-icons" style="font-size: 40px; color: #2a3a3a;">hourglass_empty</i>';
                nftImagePlaceholder.style.display = 'flex';
            }
            
            // Show modal immediately
            modal.style.display = 'block';
            modal.style.opacity = '1';
            
            // Load NFT data
            try {
                console.log('Loading NFT data...');
                const nftData = await this.loadNftData(nftId);
                console.log('Loaded NFT data:', nftData);
                this.updateNftPreview(nftData);
            } catch (error) {
                console.error('Failed to load NFT data:', error);
                // Show error state in preview
                this.updateNftPreview({
                    id: nftId,
                    name: `NFT #${nftId}`,
                    error: 'Failed to load NFT data'
                });
            }
            
            // Animate in
            setTimeout(() => {
                const modalContent = modal.querySelector('.modal-content');
                if (modalContent) {
                    modalContent.style.transform = 'translateY(0)';
                }
                
                // Focus recipient input
                if (recipientInput) {
                    recipientInput.focus();
                }
            }, 10);
            
            console.log('Modal opened successfully');
        },
        
        // Load NFT data
        loadNftData: async function(nftId) {
            console.log(`Loading NFT data for ID: ${nftId}`);
            
            try {
                // First try to find the NFT in the page
                const nftCard = document.querySelector(`.nft-card-small[data-nft-id="${nftId}"]`);
                
                if (nftCard) {
                    console.log('Found NFT card in DOM, extracting data...');
                    
                    // If NFT card is found in the DOM, extract data from it
                    const nftImage = nftCard.querySelector('.nft-image')?.src || '';
                    const nftName = nftCard.querySelector('.nft-name')?.textContent.trim() || `NFT #${nftId}`;
                    
                    const nftData = {
                        id: nftId,
                        image: nftImage,
                        name: nftName
                    };
                    
                    console.log('Extracted NFT data:', nftData);
                    return nftData;
                } else {
                    // If NFT card not found in DOM, try to fetch from API or use fallback
                    console.log(`NFT card with ID ${nftId} not found in DOM, using fallback data...`);
                    
                    // Fallback data
                    return {
                        id: nftId,
                        image: '', // No image available
                        name: `NFT #${nftId}`,
                        description: 'NFT details not available'
                    };
                }
            } catch (error) {
                console.error('Error loading NFT data:', error);
                // Return a default NFT object on error
                return {
                    id: nftId,
                    image: '',
                    name: `NFT #${nftId}`,
                    error: 'Failed to load NFT data'
                };
            }
        },
        
        // Update NFT preview
        updateNftPreview: function(nftData) {
            console.log('Updating NFT preview with data:', nftData);
            
            if (!nftData) {
                console.error('No NFT data provided to updateNftPreview');
                return;
            }
            
            // Get all required elements
            const modal = document.getElementById('sendNftModal');
            if (!modal) {
                console.error('Send NFT modal not found');
                return;
            }
            
            const nftImage = modal.querySelector('#nftPreviewImage');
            const nftImagePlaceholder = modal.querySelector('#nftImagePlaceholder');
            const nftIdDisplay = modal.querySelector('#nftIdDisplay');
            const nftNameElement = modal.querySelector('#nftName');
            
            // Log which elements were found
            const elementsFound = { 
                nftImage: !!nftImage, 
                nftImagePlaceholder: !!nftImagePlaceholder,
                nftIdDisplay: !!nftIdDisplay,
                nftNameElement: !!nftNameElement
            };
            
            console.log('Preview elements found:', elementsFound);
            
            try {
                // Update NFT ID display
                if (nftIdDisplay) {
                    nftIdDisplay.textContent = `#${nftData.id || 'unknown'}`;
                    console.log('Updated NFT ID display');
                }
                
                // Update NFT name
                if (nftNameElement && nftData.name) {
                    nftNameElement.textContent = nftData.name;
                    console.log('Updated NFT name:', nftData.name);
                }
                
                // Update image if elements exist
                if (nftImage && nftImagePlaceholder) {
                    if (nftData.image) {
                        console.log('Setting NFT image:', nftData.image);
                        
                        // Set up onload handler first
                        nftImage.onload = function() {
                            console.log('NFT image loaded successfully');
                            nftImage.style.display = 'block';
                            nftImagePlaceholder.style.display = 'none';
                        };
                        
                        nftImage.onerror = function() {
                            console.error('Failed to load NFT image');
                            nftImage.style.display = 'none';
                            nftImagePlaceholder.style.display = 'flex';
                            nftImagePlaceholder.innerHTML = '<i class="material-icons" style="font-size: 40px; color: #2a3a3a;">broken_image</i>';
                        };
                        
                        // Set the source last to trigger loading
                        nftImage.src = nftData.image;
                    } else {
                        console.log('No image available for NFT');
                        nftImage.style.display = 'none';
                        nftImagePlaceholder.style.display = 'flex';
                        nftImagePlaceholder.innerHTML = '<i class="material-icons" style="font-size: 40px; color: #2a3a3a;">image_not_supported</i>';
                    }
                } else {
                    console.error('Required image elements not found');
                }
                
                this.currentNftData = nftData;
                console.log('NFT preview updated successfully');
                
            } catch (error) {
                console.error('Error updating NFT preview:', error);
                
                // Try to show an error state
                try {
                    if (nftImagePlaceholder) {
                        nftImagePlaceholder.innerHTML = '<i class="material-icons" style="font-size: 40px; color: #ff6b6b;">error</i>';
                        nftImagePlaceholder.style.display = 'flex';
                    }
                } catch (e) {
                    console.error('Could not update error state:', e);
                }
            }
        },
        
        // Validate Ethereum address
        validateEthAddress: function(address) {
            return /^0x[a-fA-F0-9]{40}$/.test(address);
        },
        
        // Update recipient preview with database check
        updateRecipientPreview: async function(address) {
            const walletIcon = document.getElementById('walletIcon');
            const addressError = document.getElementById('addressError');
            
            if (!address) {
                if (walletIcon) walletIcon.style.display = 'none';
                if (addressError) addressError.style.display = 'none';
                this.recipientValid = false;
                return;
            }
            
            // Show loading state
            if (walletIcon) {
                walletIcon.style.display = 'flex';
                walletIcon.innerHTML = '<div style="width: 20px; height: 20px; border: 2px solid rgba(0, 191, 191, 0.3); border-radius: 50%; border-top-color: #00BFBF; animation: spin 1s ease-in-out infinite;"></div>';
            }
            
            try {
                // Check if wallet exists in database
                const response = await fetch('api/check_wallet.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ wallet_address: address })
                });
                
                const result = await response.json();
                
                if (walletIcon) {
                    if (result.exists) {
                        walletIcon.innerHTML = '<i class="material-icons" style="font-size: 20px; color: #4CAF50;">check_circle</i>';
                        if (addressError) {
                            addressError.innerHTML = '<span style="color: #4CAF50;">✓ Wallet exists</span>';
                            addressError.style.display = 'block';
                        }
                        this.recipientValid = true;
                    } else {
                        walletIcon.innerHTML = '<i class="material-icons" style="font-size: 20px; color: #ff9800;">warning</i>';
                        if (addressError) {
                            addressError.textContent = 'Wallet not found in our system';
                            addressError.style.color = '#ff9800';
                            addressError.style.display = 'block';
                        }
                        this.recipientValid = false;
                    }
                }
                
            } catch (error) {
                console.error('Error checking wallet:', error);
                if (walletIcon) {
                    walletIcon.innerHTML = '<i class="material-icons" style="font-size: 20px; color: #f44336;">error</i>';
                }
                if (addressError) {
                    addressError.textContent = 'Error checking wallet. Please try again.';
                    addressError.style.color = '#f44336';
                    addressError.style.display = 'block';
                }
                this.recipientValid = false;
            }
        },
        
        // Helper method to check if an address belongs to the current user
        isCurrentUser: function(address) {
            // Implement this based on your user system
            // For example, you might store the current user's address in a global variable
            // or fetch it from your authentication system
            const currentUserAddress = window.currentUser?.walletAddress; // Adjust this based on your app
            return currentUserAddress && currentUserAddress.toLowerCase() === address.toLowerCase();
        },
        
        init: function() {
            if (this.initialized) return;
            
            console.log('Initializing Send NFT modal...');
            
            // Get all required elements
            const modal = document.getElementById('sendNftModal');
            const confirmBtn = document.getElementById('confirmSendBtn');
            const cancelBtn = document.getElementById('cancelSendBtn');
            const recipientInput = document.getElementById('recipientAddress');
            const addressError = document.getElementById('addressError');
            
            // Log which elements are missing
            const missingElements = [];
            if (!modal) missingElements.push('sendNftModal');
            if (!confirmBtn) missingElements.push('confirmSendBtn');
            if (!cancelBtn) missingElements.push('cancelSendBtn');
            if (!recipientInput) missingElements.push('recipientAddress');
            if (!addressError) missingElements.push('addressError');
            
            if (missingElements.length > 0) {
                console.error('Missing Send NFT modal elements:', missingElements.join(', '));
                return;
            }
            
            this.initialized = true;
            console.log('Send NFT modal initialized successfully with all required elements');
            
            // Open modal function
            this.open = async function(nftId) {
                if (this.isAnimating) return;
                
                this.currentNftId = nftId;
                this.recipientValid = false;
                
                // Reset form
                if (recipientInput) recipientInput.value = '';
                if (addressError) {
                    addressError.textContent = '';
                    addressError.style.display = 'none';
                }
                
                // Hide recipient preview initially
                const recipientPreview = document.getElementById('recipientPreview');
                if (recipientPreview) recipientPreview.style.display = 'none';
                
                // Show loading state for NFT preview
                const nftImageContainer = document.getElementById('nftImageContainer');
                if (nftImageContainer) {
                    nftImageContainer.innerHTML = '<div style="width: 40px; height: 40px; border: 3px solid rgba(0, 191, 191, 0.3); border-radius: 50%; border-top-color: #00BFBF; animation: spin 1s ease-in-out infinite;"></div>';
                }
                
                // Load NFT data
                try {
                    const nftData = await this.loadNftData(nftId);
                    if (nftData) {
                        this.updateNftPreview(nftData);
                    } else {
                        console.error('Failed to load NFT data');
                        if (nftImageContainer) {
                            nftImageContainer.innerHTML = '<i class="material-icons" style="font-size: 40px; color: #ff6b6b;">error</i>';
                        }
                        return;
                    }
                } catch (error) {
                    console.error('Error loading NFT data:', error);
                    if (nftImageContainer) {
                        nftImageContainer.innerHTML = '<i class="material-icons" style="font-size: 40px; color: #ff6b6b;">error</i>';
                    }
                    return;
                }
                
                // Show modal with animation
                modal.style.display = 'block';
                
                // Keep reference to modal and recipientInput for the setTimeout callback
                const self = this;
                const modalElement = modal;
                const inputElement = recipientInput;
                
                setTimeout(function() {
                    modalElement.style.opacity = '1';
                    const modalContent = modalElement.querySelector('.modal-content');
                    if (modalContent) {
                        modalContent.style.transform = 'translateY(0)';
                    }
                    // Focus on recipient input
                    if (inputElement) inputElement.focus();
                }, 10);
            };
            
            // Close modal function
            this.close = function() {
                if (this.isAnimating) return;
                
                this.isAnimating = true;
                
                // Animate out
                modal.style.opacity = '0';
                const modalContent = modal.querySelector('.modal-content');
                if (modalContent) {
                    modalContent.style.transform = 'translateY(-20px)';
                }
                
                // Keep reference to 'this' for the setTimeout callback
                const self = this;
                
                // Hide after animation
                setTimeout(function() {
                    modal.style.display = 'none';
                    self.currentNftId = null;
                    self.isAnimating = false;
                    
                    // Reset form
                    if (recipientInput) recipientInput.value = '';
                    if (addressError) {
                        addressError.textContent = '';
                        addressError.style.display = 'none';
                    }
                }, 300);
            };
            
            // Close when clicking outside the modal content
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    this.close();
                }
            });
            
            // Close when pressing Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && modal.style.display === 'block') {
                    this.close();
                }
            });
            
            // Handle recipient address input changes
            if (recipientInput) {
                let debounceTimer;
                
                recipientInput.addEventListener('input', (e) => {
                    const address = e.target.value.trim();
                    
                    // Clear any previous debounce timer
                    clearTimeout(debounceTimer);
                    
                    // Hide previous errors
                    if (addressError) {
                        addressError.style.display = 'none';
                    }
                    
                    // If empty, hide preview and return
                    if (!address) {
                        this.updateRecipientPreview('');
                        return;
                    }
                    
                    // Show loading state
                    const walletIcon = document.getElementById('walletIcon');
                    if (walletIcon) {
                        walletIcon.style.display = 'flex';
                        walletIcon.innerHTML = '<div style="width: 20px; height: 20px; border: 2px solid rgba(0, 191, 191, 0.3); border-radius: 50%; border-top-color: #00BFBF; animation: spin 1s ease-in-out infinite;"></div>';
                    }
                    
                    // Debounce the validation to avoid too many requests
                    debounceTimer = setTimeout(() => {
                        this.updateRecipientPreview(address);
                    }, 500);
                });
                
                // Handle paste event
                recipientInput.addEventListener('paste', (e) => {
                    // Let the paste event complete
                    setTimeout(() => {
                        const pastedText = e.target.value.trim();
                        if (pastedText) {
                            this.updateRecipientPreview(pastedText);
                        }
                    }, 10);
                });
            }
            
            // Handle confirm button click
            if (confirmBtn) {
                confirmBtn.onclick = async (e) => {
                    e.stopPropagation();
                    if (this.isAnimating || !recipientInput || !this.currentNftId) return;
                    
                    const recipientAddress = recipientInput.value.trim();
                    
                    // Validate recipient address
                    if (!recipientAddress) {
                        if (addressError) {
                            addressError.textContent = 'Recipient address is required';
                            addressError.style.display = 'block';
                        }
                        return;
                    }
                    
                    // Use the recipientValid flag from the async validation
                    if (!this.recipientValid) {
                        if (addressError) {
                            addressError.textContent = 'Please verify the recipient address is valid';
                            addressError.style.display = 'block';
                        }
                        return;
                    }
                    
                    // Additional check to ensure recipient is not the current user
                    // You'll need to implement this check based on your user system
                    if (this.isCurrentUser(recipientAddress)) {
                        if (addressError) {
                            addressError.textContent = 'You cannot send to yourself';
                            addressError.style.display = 'block';
                        }
                        return;
                    }
                    
                    if (!this.currentNftId) {
                        console.error('No NFT ID set for sending');
                        return;
                    }
                    
                    // Add loading state
                    const originalText = confirmBtn.innerHTML;
                    confirmBtn.disabled = true;
                    confirmBtn.innerHTML = '<i class="material-icons" style="margin-right: 8px; animation: spin 1s linear infinite;">autorenew</i> Sending...';
                    
                    // Hide any previous error messages
                    if (addressError) {
                        addressError.style.display = 'none';
                    }
                    
                    try {
                        const response = await fetch('/luck%20Wallet/marketplace/api/send_nft.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({
                                nft_id: this.currentNftId,
                                recipient_address: recipientAddress
                            })
                        });
                        
                        if (!response.ok) {
                            const errorText = await response.text();
                            throw new Error(`HTTP error! status: ${response.status}, body: ${errorText}`);
                        }
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            // Show success state in the modal
                            const modalBody = document.querySelector('#sendNftModal .modal-body');
                            if (modalBody) {
                                modalBody.innerHTML = `
                                    <div style="text-align: center; padding: 20px 0;">
                                        <div style="width: 80px; height: 80px; background: rgba(76, 175, 80, 0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; border: 2px solid rgba(76, 175, 80, 0.5);">
                                            <i class="material-icons" style="font-size: 40px; color: #4CAF50;">check_circle</i>
                                        </div>
                                        <h3 style="color: #4CAF50; margin-bottom: 15px;">NFT Sent Successfully!</h3>
                                        <p style="color: #e0e0e0; margin-bottom: 25px;">Your NFT has been sent to ${recipientAddress}.</p>
                                        <button onclick="window.location.reload()" style="padding: 10px 25px; background: #00BFBF; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 1rem; font-weight: 500; transition: all 0.3s ease;">
                                            Close
                                        </button>
                                    </div>
                                `;
                                
                                // Hide the modal header and footer
                                const modalHeader = document.querySelector('#sendNftModal .modal-header');
                                const modalFooter = document.querySelector('#sendNftModal .modal-footer');
                                if (modalHeader) modalHeader.style.display = 'none';
                                if (modalFooter) modalFooter.style.display = 'none';
                            } else {
                                // Fallback to old behavior if we can't update the modal
                                confirmBtn.innerHTML = '<i class="material-icons" style="margin-right: 8px;">check_circle</i> Sent!';
                                confirmBtn.style.background = 'linear-gradient(135deg, #4CAF50, #2E7D32)';
                                setTimeout(() => window.location.reload(), 1500);
                            }
                        } else {
                            throw new Error(result.message || 'Failed to send NFT');
                        }
                    } catch (error) {
                        console.error('Error sending NFT:', error);
                        // Show error state
                        confirmBtn.innerHTML = '<i class="material-icons" style="margin-right: 8px;">error</i> Error';
                        confirmBtn.style.background = 'linear-gradient(135deg, #f44336, #c62828)';
                        
                        // Show error message
                        if (addressError) {
                            addressError.textContent = error.message || 'Failed to send NFT. Please try again.';
                            addressError.style.display = 'block';
                        }
                        
                        // Revert after delay
                        setTimeout(() => {
                            confirmBtn.innerHTML = originalText;
                            confirmBtn.style.background = 'linear-gradient(135deg, #00BFBF, #008080)';
                            confirmBtn.disabled = false;
                        }, 2000);
                    }
                };
            }
            
            // Handle cancel button
            if (cancelBtn) {
                cancelBtn.onclick = (e) => {
                    e.stopPropagation();
                    this.close();
                };
            }
            
            // Close modal when clicking outside content
            modal.addEventListener('click', (e) => {
                if (e.target === modal || e.target.classList.contains('close-modal')) {
                    this.close();
                }
            });
            
            // Handle Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && modal.style.display === 'block') {
                    this.close();
                }
            });
        },
        
        // Public method to open the modal
        openModal: function(nftId) {
            try {
                console.log('openModal called with nftId:', nftId);
                
                if (!nftId) {
                    console.error('No nftId provided to openModal');
                    return;
                }
                
                // Ensure we're initialized
                if (!this.initialized) {
                    console.log('Initializing sendNftModal...');
                    this.init();
                    
                    // If still not initialized after init, show error
                    if (!this.initialized) {
                        console.error('Failed to initialize sendNftModal');
                        alert('Failed to initialize the Send NFT feature. Please try again.');
                        return;
                    }
                }
                
                // Check if open method exists
                if (typeof this.open !== 'function') {
                    console.error('this.open is not a function in sendNftModal');
                    // Fallback to direct modal manipulation
                    const modal = document.getElementById('sendNftModal');
                    if (modal) {
                        modal.style.display = 'block';
                        modal.style.opacity = '1';
                        const modalContent = modal.querySelector('.modal-content');
                        if (modalContent) {
                            modalContent.style.transform = 'translateY(0)';
                        }
                    }
                    return;
                }
                
                console.log('Opening modal for NFT ID:', nftId);
                
                // Set current NFT ID and reset state
                this.currentNftId = nftId;
                this.recipientValid = false;
                
                // Get modal elements
                const modal = document.getElementById('sendNftModal');
                if (!modal) {
                    console.error('Send NFT modal not found in DOM');
                    return;
                }
                
                // Show loading state
                const nftImagePlaceholder = modal.querySelector('#nftImagePlaceholder');
                if (nftImagePlaceholder) {
                    nftImagePlaceholder.innerHTML = '<i class="material-icons" style="font-size: 40px; color: #2a3a3a;">hourglass_empty</i>';
                    nftImagePlaceholder.style.display = 'flex';
                }
                
                // Reset form
                const recipientInput = modal.querySelector('#recipientAddress');
                const addressError = modal.querySelector('#addressError');
                if (recipientInput) recipientInput.value = '';
                if (addressError) {
                    addressError.textContent = '';
                    addressError.style.display = 'none';
                }
                
                // Show modal immediately
                modal.style.display = 'block';
                
                // Force reflow to ensure the modal is in the DOM
                void modal.offsetHeight;
                
                // Now set opacity to trigger the transition
                modal.style.opacity = '1';
                
                // Animate in
                setTimeout(() => {
                    const modalContent = modal.querySelector('.modal-content');
                    if (modalContent) {
                        modalContent.style.transform = 'translateY(0)';
                    }
                }, 10);
                
                // Load NFT data
                (async () => {
                    try {
                        console.log('Loading NFT data for ID:', nftId);
                        const nftData = await this.loadNftData(nftId);
                        console.log('NFT data loaded:', nftData);
                        
                        // Update preview after a short delay to ensure elements are rendered
                        setTimeout(() => {
                            this.updateNftPreview(nftData);
                            
                            // Focus recipient input
                            if (recipientInput) {
                                setTimeout(() => {
                                    recipientInput.focus();
                                }, 100);
                            }
                        }, 50);
                        
                    } catch (error) {
                        console.error('Failed to load NFT data:', error);
                        // Show error state in preview
                        this.updateNftPreview({
                            id: nftId,
                            name: `NFT #${nftId}`,
                            error: 'Failed to load NFT data'
                        });
                    }
                })();
            } catch (error) {
                console.error('Error in sendNftModal.openModal:', error);
            }
        }
    };
    
    // List for Sale Modal Management
    window.listForSaleModal = {
        currentNftId: null,
        isAnimating: false,
        initialized: false,
        
        init: function() {
            if (this.initialized) return;
            this.initialized = true;
            
            const modal = document.getElementById('listForSaleModal');
            const confirmBtn = document.getElementById('confirmSaleBtn');
            const cancelBtn = document.getElementById('cancelSaleBtn');
            const salePriceInput = document.getElementById('salePrice');
            const priceError = document.getElementById('salePriceError');
            
            if (!modal || !confirmBtn || !cancelBtn || !salePriceInput) {
                console.error('List for sale modal elements not found');
                return;
            }
            
            // Open modal function
            this.open = (nftId) => {
                if (this.isAnimating) return;
                

                this.currentNftId = nftId;
                
                // Reset form
                if (salePriceInput) salePriceInput.value = '';
                if (priceError) priceError.textContent = '';
                
                // Show modal with animation
                modal.style.display = 'block';
                setTimeout(() => {
                    modal.style.opacity = '1';
                    const modalContent = modal.querySelector('.modal-content');
                    if (modalContent) {
                        modalContent.style.transform = 'translateY(0)';
                    }
                    // Focus on price input
                    if (salePriceInput) salePriceInput.focus();
                }, 10);
            };
            
            // Close modal function
            this.close = () => {
                if (this.isAnimating) return;
                

                this.isAnimating = true;
                
                // Animate out
                modal.style.opacity = '0';
                const modalContent = modal.querySelector('.modal-content');
                if (modalContent) {
                    modalContent.style.transform = 'translateY(-20px)';
                }
                
                // Hide after animation
                setTimeout(() => {
                    modal.style.display = 'none';
                    this.currentNftId = null;
                    this.isAnimating = false;
                }, 300);
            };
            
            // Handle confirm button click
            if (confirmBtn) {
                confirmBtn.onclick = async (e) => {
                    e.stopPropagation();
                    if (this.isAnimating || !salePriceInput) return;
                    
                    const price = parseFloat(salePriceInput.value);
                    
                    // Validate price
                    if (isNaN(price) || price <= 0) {
                        if (priceError) {
                            priceError.textContent = 'Please enter a valid price';
                            priceError.style.display = 'block';
                        }
                        return;
                    }
                    
                    if (!this.currentNftId) {
                        console.error('No NFT ID set for listing');
                        return;
                    }
                    
                    // Add loading state
                    const originalText = confirmBtn.innerHTML;
                    confirmBtn.disabled = true;
                    confirmBtn.innerHTML = '<i class="material-icons" style="margin-right: 8px; animation: spin 1s linear infinite;">autorenew</i> Listing...';
                    
                    try {
                        const response = await fetch('/luck%20Wallet/marketplace/api/list_nft.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({
                                nft_id: this.currentNftId,
                                price: price
                            })
                        });
                        
                        if (!response.ok) {
                            const errorText = await response.text();
                            throw new Error(`HTTP error! status: ${response.status}, body: ${errorText}`);
                        }
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            // Show success state briefly before reloading
                            confirmBtn.innerHTML = '<i class="material-icons" style="margin-right: 8px;">check_circle</i> Listed!';
                            confirmBtn.style.background = 'linear-gradient(135deg, #4CAF50, #2E7D32)';
                            setTimeout(() => window.location.reload(), 800);
                        } else {
                            throw new Error(result.message || 'Failed to list NFT');
                        }
                    } catch (error) {
                        console.error('Error listing NFT:', error);
                        // Show error state
                        confirmBtn.innerHTML = '<i class="material-icons" style="margin-right: 8px;">error</i> Error';
                        confirmBtn.style.background = 'linear-gradient(135deg, #f44336, #c62828)';
                        
                        // Show error message
                        if (priceError) {
                            priceError.textContent = error.message || 'Failed to list NFT. Please try again.';
                            priceError.style.display = 'block';
                        }
                        
                        // Revert after delay
                        setTimeout(() => {
                            confirmBtn.innerHTML = originalText;
                            confirmBtn.style.background = 'linear-gradient(135deg, #00BFBF, #008080)';
                            confirmBtn.disabled = false;
                        }, 2000);
                    }
                };
            }
            
            // Handle cancel button
            if (cancelBtn) {
                cancelBtn.onclick = (e) => {
                    e.stopPropagation();
                    this.close();
                };
            }
            
            // Close modal when clicking outside content
            modal.addEventListener('click', (e) => {
                if (e.target === modal || e.target.classList.contains('close-modal')) {
                    this.close();
                }
            });
            
            // Handle Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && modal.style.display === 'block') {
                    this.close();
                }
            });
            

        },
        
        // Public method to open the modal
        openModal: function(nftId) {
            if (!this.initialized) {

                this.init();
            }
            this.open(nftId);
        }
    };
    
    // Initialize the modals when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOMContentLoaded: Starting modal initialization...');
        console.log('sendNftModal exists:', typeof window.sendNftModal !== 'undefined');
        if (window.sendNftModal) {
            console.log('sendNftModal methods:', Object.keys(window.sendNftModal));
        }
        // Initialize remove listing modal
        try {
            if (window.removeListingModal && typeof window.removeListingModal.init === 'function') {
                window.removeListingModal.init();
            } else {
                console.error('removeListingModal not found or init is not a function');
            }
        } catch (error) {
            console.error('Error initializing remove listing modal:', error);
        }
        
        // Initialize list for sale modal
        try {
            if (window.listForSaleModal && typeof window.listForSaleModal.init === 'function') {
                window.listForSaleModal.init();
            } else {
                console.error('listForSaleModal not found or init is not a function');
            }
        } catch (error) {
            console.error('Error initializing list for sale modal:', error);
        }
        
        // Initialize send NFT modal
        try {
            if (window.sendNftModal && typeof window.sendNftModal.init === 'function') {
                window.sendNftModal.init();
                console.log('Send NFT modal initialized successfully');
            } else {
                console.error('sendNftModal not found or init is not a function');
            }
        } catch (error) {
            console.error('Error initializing send NFT modal:', error);
        }
    });
    
    // Also try to initialize after a short delay in case of dynamic content loading
    setTimeout(() => {

        
        // Initialize remove listing modal
        try {
            if (window.removeListingModal && typeof window.removeListingModal.init === 'function') {
                window.removeListingModal.init();

            } else {
                console.error('Delayed: removeListingModal not found or init is not a function');
            }
        } catch (error) {
            console.error('Delayed: Error initializing remove listing modal:', error);
        }
        
        // Initialize list for sale modal
        try {
            if (window.listForSaleModal && typeof window.listForSaleModal.init === 'function') {
                window.listForSaleModal.init();

            } else {
                console.error('Delayed: listForSaleModal not found or init is not a function');
            }
        } catch (error) {
            console.error('Error during delayed initialization of list for sale modal:', error);
        }
        
        // Initialize send NFT modal
        try {
            if (window.sendNftModal && typeof window.sendNftModal.init === 'function') {
                window.sendNftModal.init();

            } else {
                console.error('Delayed: sendNftModal not found or init is not a function');
            }
        } catch (error) {
            console.error('Error during delayed initialization of send NFT modal:', error);
        }
    }, 1000);
    
    // Global function to show the Send NFT modal
    function showSendNftModal(nftId) {
        if (window.sendNftModal && typeof window.sendNftModal.open === 'function') {
            window.sendNftModal.open(nftId);
        } else {
            console.error('Send NFT modal not initialized');
            // Fallback to alert
            alert('Send NFT functionality is not available at the moment. Please try again later.');
        }
    }
    
    // Global function to show the modal (for use in onclick handlers)
    function showRemoveListingModal(nftId) {
        if (window.removeListingModal && typeof window.removeListingModal.open === 'function') {
            window.removeListingModal.open(nftId);
        } else {
            console.error('Remove listing modal not initialized');
            // Fallback to confirm dialog
            if (confirm('Are you sure you want to remove this NFT from listing?')) {
                // This will need to be handled by your existing removeListing function
                removeListing(nftId);
            }
        }
    }
    </script>
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
        <a href="profile.php" class="sidebar-item active"><span class="material-icons">person</span> <span class="sidebar-text">Profile</span></a>
        <a href="nft.php" class="sidebar-item"><span class="material-icons">image</span> <span class="sidebar-text">NFTs</span></a>
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

    <style>
        /* Ensure proper spacing between sidebar and content */
        .container#main-container {
            margin-left: 80px; /* Match the width of the collapsed sidebar */
            width: calc(100% - 80px);
            transition: margin-left 0.3s ease;
        }
        
        /* When sidebar is expanded on desktop */
        @media (min-width: 1025px) {
            .sidebar-left:hover + .container#main-container {
                margin-left: 250px; /* Match the width of the expanded sidebar */
                width: calc(100% - 250px);
            }
        }
        
        /* For mobile view when sidebar is visible */
        .sidebar-left.mobile-visible + .container#main-container {
            transform: translateX(250px);
        }
        
        /* Adjust main content padding */
        .main-content {
            padding: 0;
            box-sizing: border-box;
            width: 100%;
            max-width: 100%;
        }
    </style>
    
    <div class="container" id="main-container" style="min-height: calc(100vh - 70px); display: flex; flex-direction: column; padding: 0; margin: 0 0 0 80px; width: calc(100% - 80px); max-width: 100%; transition: margin-left 0.3s ease; overflow-x: hidden;">
        <main class="main-content" style="flex: 1; display: flex; flex-direction: column; overflow: hidden; width: 100%; max-width: 100%; padding: 0; margin: 0;">
            <section class="user-picture-section" id="userPictureSection" style="background-color: #0a0a0a; padding: 60px 0 40px; position: relative; min-height: 500px; overflow: hidden; margin: 0; border-bottom: 1px solid rgba(0, 191, 191, 0.2);">
                <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(135deg, #006666, #003333), var(--profile-bg-image, none); background-size: cover; background-position: center; background-blend-mode: overlay; opacity: 0.8; z-index: 0;" id="profileBackground"></div>
                
                <?php
                $profileImage = ''; // Will be set based on user data or default
                $displayName = 'User';
                $displayWallet = '';
                $defaultImage = 'logo.png'; // Path to default image in the same directory
                
                if (isset($_SESSION['user_id'])) {
                    try {
                        // Get user data including profile image and balance
                        $stmt = $pdo->prepare("SELECT username, wallet_address, profile_image, luck_balance FROM luck_wallet_users WHERE user_id = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($user) {
                            $displayName = htmlspecialchars($user['username'] ?? 'User');
                            // Get wallet address and format for display
                            $wallet = $user['wallet_address'] ?? '';
                            $displayWallet = $wallet ? (strlen($wallet) > 12 ? 
                                substr($wallet, 0, 6) . '...' . substr($wallet, -4) : $wallet) : 'Not connected';
                                
                            // Store wallet in session for later use
                            $_SESSION['wallet_address'] = $wallet;
                                
                            if (!empty($user['profile_image'])) {
                                // Get the MIME type of the image
                                $finfo = new finfo(FILEINFO_MIME_TYPE);
                                $mimeType = $finfo->buffer($user['profile_image']);
                                $profileImage = 'data:' . $mimeType . ';base64,' . base64_encode($user['profile_image']);
                                
                                // Store the profile image URL in a JavaScript variable
                                echo '<script>window.profileImageUrl = '.json_encode($profileImage).';</script>';
                            }
                            
                            // Get user's NFTs count and total value
                            $nftStmt = $pdo->prepare("SELECT COUNT(*) as nft_count, COALESCE(SUM(price_luck), 0) as total_value FROM luck_wallet_nfts WHERE current_owner_user_id = ?");
                            $nftStmt->execute([$_SESSION['user_id']]);
                            $nftData = $nftStmt->fetch(PDO::FETCH_ASSOC);
                            
                            $nftCount = $nftData['nft_count'] ?? 0;
                            $totalValue = number_format($nftData['total_value'] ?? 0, 2);
                            $luckBalance = number_format($user['luck_balance'] ?? 0, 2);
                        }
                    } catch (PDOException $e) {
                        error_log("Error fetching user data: " . $e->getMessage());
                    }
                }
                ?>
                <div class="user-picture-container" id="profilePictureContainer" style="width: 180px; height: 180px; border-radius: 50%; background-color: #004d4d; position: absolute; bottom: 40px; left: 40px; z-index: 2; border: 4px solid #00BFBF; overflow: hidden; box-shadow: 0 0 20px rgba(0, 191, 191, 0.5); cursor: pointer; transition: all 0.3s ease;" onclick="document.getElementById('profileImageInput').click()">
                    <img src="<?php echo !empty($profileImage) && strpos($profileImage, 'data:image') === 0 ? $profileImage : $defaultImage; ?>" 
                         onerror="this.onerror=null; this.src='<?php echo $defaultImage; ?>'"
                         alt="User Avatar" style="width: 100%; height: 100%; object-fit: cover; transition: all 0.3s ease;" id="userProfileImage">
                    <div class="edit-overlay" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); display: flex; flex-direction: column; justify-content: center; align-items: center; opacity: 0; transition: opacity 0.3s ease; color: white; text-align: center; padding: 10px; box-sizing: border-box;">
                        <span class="material-icons" style="font-size: 2rem; margin-bottom: 5px;">edit</span>
                        <span style="font-size: 0.9rem; font-weight: 500;">Edit Photo</span>
                    </div>
                    <input type="file" id="profileImageInput" accept="image/*" style="display: none;" onchange="handleFileSelect(event)">
                </div>
                <link rel="stylesheet" href="assets/css/loading-spinner.css">
                <style>
                    .user-picture-container:hover .edit-overlay {
                        opacity: 1;
                    }
                    
                    .user-picture-container:hover img {
                        transform: scale(1.05);
                    }
                    
                    .loading-overlay {
                        position: absolute;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background: rgba(0, 0, 0, 0.7);
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        z-index: 1001;
                    }
                    
                    .loading-spinner {
                        width: 40px;
                        height: 40px;
                        border-width: 4px;
                    }
                </style>
                <script>
                // Single source of truth for upload state
                let isUploading = false;
                
                // Initialize when DOM is loaded
                document.addEventListener('DOMContentLoaded', function() {
                    const container = document.getElementById('profilePictureContainer');
                    const overlay = container.querySelector('.edit-overlay');
                    
                    // Initialize hover effect
                    container.addEventListener('mouseenter', function() {
                        overlay.style.opacity = '1';
                    });
                    
                    container.addEventListener('mouseleave', function() {
                        overlay.style.opacity = '0';
                    });
                });
                
                // Make the function globally available
                function handleFileSelect(event) {
                    console.log('File select triggered');
                    const file = event.target.files[0];
                    if (!file) {
                        console.log('No file selected');
                        return;
                    }
                    if (isUploading) {
                        console.log('Upload already in progress');
                        return;
                    }
                    
                    console.log('Selected file:', file.name, 'Size:', file.size, 'bytes', 'Type:', file.type);
                    
                    const container = document.getElementById('profilePictureContainer');
                    const img = document.getElementById('userProfileImage');
                    const originalSrc = img.src;
                    
                    // Reset the input
                    event.target.value = '';
                    
                    // Show loading state
                    isUploading = true;
                    
                    // Create loading overlay
                    const loadingOverlay = document.createElement('div');
                    loadingOverlay.className = 'loading-overlay';
                    loadingOverlay.id = 'loadingOverlay';
                    loadingOverlay.innerHTML = '<div class="loading-spinner"></div>';
                    container.appendChild(loadingOverlay);
                    
                    const formData = new FormData();
                    formData.append('profile_picture', file);
                    
                    console.log('Starting file upload...');
                    
                    fetch('update_profile_picture.php', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    })
                    .then(async response => {
                        console.log('Received response, status:', response.status);
                        const responseText = await response.text();
                        console.log('Raw response:', responseText);
                        
                        // Try to parse as JSON, but handle non-JSON responses
                        try {
                            const data = JSON.parse(responseText);
                            console.log('Parsed JSON response:', data);
                            
                            if (!response.ok) {
                                throw new Error(data.message || `HTTP error! status: ${response.status}`);
                            }
                            return data;
                        } catch (e) {
                            console.error('Failed to parse JSON response:', e);
                            console.error('Response content type:', response.headers.get('content-type'));
                            throw new Error(`Invalid server response: ${responseText.substring(0, 200)}`);
                        }
                    })
                    .then(data => {
                        console.log('Processing successful response:', data);
                        if (data.success && data.imageUrl) {
                            // Update the image source with cache buster
                            const newSrc = data.imageUrl + (data.imageUrl.includes('?') ? '&' : '?') + 't=' + new Date().getTime();
                            console.log('Updating image source to:', newSrc);
                            img.src = newSrc;
                            showToast('Profile picture updated successfully!', 'success');
                        } else {
                            throw new Error(data.message || 'Server responded with success but no image URL');
                        }
                    })
                    .catch(error => {
                        console.error('Error in file upload process:', error);
                        console.error('Error details:', {
                            name: error.name,
                            message: error.message,
                            stack: error.stack
                        });
                        showToast('Error: ' + (error.message || 'Failed to upload image. Please try again.'), 'error');
                        // Only reset if we haven't already changed the image
                        if (img.src === originalSrc) {
                            img.src = originalSrc;
                        }
                    })
                    .finally(() => {
                        console.log('Upload process completed');
                        isUploading = false;
                        const loadingOverlay = document.getElementById('loadingOverlay');
                        if (loadingOverlay && loadingOverlay.parentNode) {
                            loadingOverlay.parentNode.removeChild(loadingOverlay);
                        }
                    });
                }
                
                function showToast(message, type = 'info') {
                    const toast = document.createElement('div');
                    toast.className = `toast ${type}`;
                    toast.textContent = message;
                    
                    const closeBtn = document.createElement('button');
                    closeBtn.className = 'close-btn';
                    closeBtn.innerHTML = '&times;';
                    closeBtn.onclick = () => {
                        toast.classList.remove('show');
                        setTimeout(() => {
                            if (toast.parentNode) {
                                toast.parentNode.removeChild(toast);
                            }
                        }, 300);
                    };
                    toast.appendChild(closeBtn);
                    
                    document.body.appendChild(toast);
                    
                    // Trigger reflow
                    toast.offsetHeight;
                    toast.classList.add('show');
                    
                    // Auto-remove after 5 seconds
                    setTimeout(() => {
                        toast.classList.remove('show');
                        setTimeout(() => {
                            if (toast.parentNode) {
                                toast.parentNode.removeChild(toast);
                            }
                        }, 300);
                    }, 5000);
                }
                </script>
                <div class="user-details-group" style="position: absolute; bottom: 40px; left: 250px; z-index: 2; color: #ffffff; width: calc(100% - 290px);">
                    <div class="user-info-row" style="display: flex; flex-direction: column; gap: 15px;">
                        <div style="display: flex; flex-direction: column; gap: 5px;">
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <h2 style="color: #00FFCC; margin: 0; font-size: 2.5rem; font-weight: 700; text-shadow: 0 0 15px rgba(0, 255, 204, 0.3);" id="userDisplayName"><?php echo $displayName; ?></h2>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span style="color: #A0A0A0; font-size: 1rem; font-family: 'Courier New', monospace; word-break: break-all; background: rgba(0, 0, 0, 0.3); padding: 4px 8px; border-radius: 4px;" id="userWalletAddress">
                                    <?php 
                                    if (!empty($wallet)) {
                                        echo htmlspecialchars($displayWallet);
                                    } else {
                                        echo '<span style="color: #ff6b6b;">Wallet not connected</span>';
                                    }
                                    ?>
                                </span>
                                <?php if (!empty($wallet)): ?>
                                <button id="copyWalletBtn" style="background: rgba(0, 191, 191, 0.2); border: 1px solid rgba(0, 191, 191, 0.4); color: #00BFBF; border-radius: 4px; padding: 4px 8px; cursor: pointer; display: flex; align-items: center; gap: 4px; font-size: 0.8rem; transition: all 0.2s ease;" title="Copy to clipboard" data-wallet="<?php echo htmlspecialchars($wallet); ?>">
                                    <span class="material-icons" style="font-size: 1rem;">content_copy</span>
                                    <span>Copy</span>
                                </button>
                                <?php endif; ?>
                                <span id="copySuccess" style="color: #00BFBF; font-size: 0.8rem; display: none;">Copied!</span>
                            </div>
                        </div>
                        <div style="display: flex; gap: 15px; margin-top: 10px;">
                            <div class="user-nft-count" style="background: rgba(0, 0, 0, 0.4); padding: 10px 20px; border-radius: 8px; border: 1px solid rgba(0, 191, 191, 0.2);">
                                <div style="font-size: 1.1rem; color: #00BFBF; font-weight: 600;" id="nftCount"><?php echo $nftCount ?? '0'; ?></div>
                                <div style="font-size: 0.8rem; color: #A0A0A0;">Items</div>
                            </div>
                            <div class="user-nft-count" style="background: rgba(0, 0, 0, 0.4); padding: 10px 20px; border-radius: 8px; border: 1px solid rgba(0, 191, 191, 0.2);">
                                <div style="font-size: 1.1rem; color: #00BFBF; font-weight: 600;" id="totalValue"><?php echo $totalValue ?? '0.00'; ?></div>
                                <div style="font-size: 0.8rem; color: #A0A0A0;">Value (LUCK)</div>
                            </div>
                            <div class="user-nft-count" style="background: rgba(0, 0, 0, 0.4); padding: 10px 20px; border-radius: 8px; border: 1px solid rgba(0, 191, 191, 0.2);">
                                <div style="font-size: 1.1rem; color: #00BFBF; font-weight: 600;" id="luckBalance"><?php echo $luckBalance ?? '0.00'; ?></div>
                                <div style="font-size: 0.8rem; color: #A0A0A0;">LUCK Balance</div>
                            </div>
                            <a href="http://localhost/Luck%20Wallet/add_nft_collection.php" class="add-nft-button" style="display: inline-flex; align-items: center; justify-content: center; gap: 8px; background: linear-gradient(45deg, #00BFBF, #008080); color: #0a0a0a; border: none; border-radius: 8px; padding: 10px 20px; font-size: 1rem; font-weight: 600; text-decoration: none; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(0, 191, 191, 0.3);">
                                <span class="material-icons" style="font-size: 1.2rem;">add</span>
                                <span>ADD NFTs</span>
                            </a>
                        </div>
                    </div>
                </div>
            </section>

            <?php
            // Function to generate NFT card HTML
            function generateNFTCard($nft, $currentUserId, $section = 'collection') {
                $isOwner = $nft['current_owner_user_id'] == $currentUserId;
                $isListed = $nft['listed_for_sale'] ?? 0;
                $price = number_format($nft['price_luck'] ?? 0, 3);
                $isFavorite = false;
                
                // Check if this NFT is in user's favorites
                if (isset($_SESSION['user_id'])) {
                    global $pdo;
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM luck_wallet_user_favorites WHERE user_id = ? AND nft_id = ?");
                    $stmt->execute([$currentUserId, $nft['nft_id']]);
                    $isFavorite = $stmt->fetchColumn() > 0;
                }
                
                // Prepare image paths with fallbacks
                $imagePath = !empty($nft['image_url']) ? 
                    (strpos($nft['image_url'], 'http') === 0 ? $nft['image_url'] : '../' . ltrim($nft['image_url'], '/')) : 
                    'default-nft.png';
                    
                $collectionLogoPath = !empty($nft['collection_logo']) ? 
                    (strpos($nft['collection_logo'], 'http') === 0 ? $nft['collection_logo'] : '../' . ltrim($nft['collection_logo'], '/')) : 
                    'default-logo.png';
                
                ob_start();
                ?>
                <div class="nft-card-small" data-nft-id="<?php echo $nft['nft_id']; ?>" 
                     data-created="<?php echo strtotime($nft['created_at'] ?? 'now'); ?>"
                     data-likes="<?php echo $nft['like_count'] ?? 0; ?>"
                     data-price="<?php echo $nft['price_luck'] ?? 0; ?>"
                     data-click-initialized="false">
                    <div class="nft-card-inner">
                        <!-- Front of the card -->
                        <div class="nft-card-front">
                            <!-- Collection logo at top -->
                            <div class="nft-logo-container">
                                <img src="<?php echo htmlspecialchars($collectionLogoPath); ?>" 
                                     alt="<?php echo htmlspecialchars($nft['collection_name']); ?>"
                                     onerror="this.onerror=null; this.src='default-logo.png'">
                            </div>
                            
                            <!-- Favorite button -->
                            <button class="favorite-button" data-nft-id="<?php echo $nft['nft_id']; ?>">
                                <span class="material-icons"><?php echo $isFavorite ? 'star' : 'star_border'; ?></span>
                            </button>
                            
                            <!-- NFT image -->
                            <div class="nft-image-container">
                                <img src="<?php echo htmlspecialchars($imagePath); ?>" 
                                     alt="<?php echo htmlspecialchars($nft['name']); ?>" 
                                     class="nft-image"
                                     onerror="this.onerror=null; this.src='default-nft.png'">
                            </div>
                            
                            <!-- NFT ID display -->
                            <div class="nft-id-display">
                                <span>NFT ID: <?php echo htmlspecialchars($nft['nft_id']); ?></span>
                            </div>
                            
                            <!-- NFT name (using collection name) and price -->
                            <div class="info">
                                <h3 title="<?php echo htmlspecialchars($nft['collection_name'] ?? 'Unnamed Collection'); ?>">
                                    <?php echo htmlspecialchars($nft['collection_name'] ?? 'Unnamed Collection'); ?>
                                </h3>
                                <p class="price" style="color: #e0e0e0; font-weight: 500; margin: 10px 0 0; font-size: 1rem;">
                                    <?php echo $price; ?> LUCK
                                </p>
                            </div>
                        </div>
                        
                        <!-- Back of the card - Show NFT ID and Owner -->
                        <div class="nft-card-back">
                            <div style="text-align: center; height: 100%; display: flex; flex-direction: column; justify-content: flex-start; padding-top: 20px;">
                                <p style="margin: 10px 0; font-size: 1.1rem;">
                                    <strong>NFT ID:</strong> #<?php echo $nft['nft_id']; ?>
                                </p>
                                <p style="margin: 10px 0; font-size: 1.1rem; word-break: break-all;">
                                    <strong>Owner:</strong> <?php echo htmlspecialchars($nft['owner_wallet_address'] ?? 'Unknown'); ?>
                                </p>
                                
                                <?php if (in_array($section, ['collection', 'listed'])): ?>
                                    <!-- Price display for listed items -->
                                    <?php if ($isListed): ?>
                                        <p style="margin: 15px 0; font-size: 1.2rem; color: #00FFCC; font-weight: bold;">
                                            <?php echo number_format($nft['price_luck'], 3); ?> LUCK
                                        </p>
                                    <?php endif; ?>
                                    
                                    <!-- Action Buttons for Collection and Listed sections -->
                                    <div style="margin-top: auto; padding: 15px 0; display: flex; flex-direction: column; gap: 10px;">
                                        <?php if ($isListed): ?>
                                            <!-- If listed: Show Remove Listing and Edit buttons -->
                                            <button class="action-button" 
                                                    style="background-color: #ff4d4d;"
                                                    onclick="event.stopPropagation(); window.removeListingModal && window.removeListingModal.open(<?php echo $nft['nft_id']; ?>);">
                                                Remove Listing
                                            </button>
                                            <button class="action-button" 
                                                    style="background-color: #4d79ff;"
                                                    onclick="event.stopPropagation(); editListing(<?php echo $nft['nft_id']; ?>, <?php echo $nft['price_luck']; ?>)">
                                                Edit Listing
                                            </button>
                                        <?php else: ?>
                                            <!-- If not listed: Show List and Send buttons -->
                                            <button class="action-button" 
                                                    style="background-color: #00BFBF;"
                                                    onclick="event.stopPropagation(); window.listForSaleModal && window.listForSaleModal.openModal(<?php echo $nft['nft_id']; ?>);">
                                                List for Sale
                                            </button>
                                            <button class="action-button" 
                                                    style="background-color: #7a4dff;"
                                                    onclick="event.stopPropagation(); window.sendNftModal && window.sendNftModal.openModal(<?php echo $nft['nft_id']; ?>);">
                                                Send NFT
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
                return ob_get_clean();
            }
            ?>
            
            <section class="toggle-section" style="background-color: rgba(0, 30, 30, 0.9); padding: 20px; box-shadow: 0 0 10px rgba(0, 191, 191, 0.3); margin: 0; display: flex; flex-direction: column; align-items: center; border-radius: 8px; height: 100%; overflow: hidden; flex: 1;">
                <div class="toggle-button-group" style="display: inline-flex; background-color: #0a0a0a; border-radius: 25px; overflow: hidden; border: 1px solid #00BFBF; box-shadow: 0 0 10px rgba(0, 191, 191, 0.7); margin: 0 auto 30px; width: fit-content;">
                    <button class="toggle-button active" data-target="collectionCards" style="padding: 12px 25px; background: linear-gradient(45deg, #00BFBF, #008080); color: #0a0a0a; border: none; cursor: pointer; font-size: 1.1rem; font-weight: 600; transition: all 0.3s ease; text-shadow: 0 0 8px #00FFCC; font-family: 'Inter', sans-serif;">Collection</button>
                    <button class="toggle-button" data-target="createdCards" style="padding: 12px 25px; background: transparent; color: #e0e0e0; border: none; cursor: pointer; font-size: 1.1rem; font-weight: 600; transition: all 0.3s ease; border-left: 1px solid rgba(0, 191, 191, 0.5); font-family: 'Inter', sans-serif;">Created</button>
                    <button class="toggle-button" data-target="listedCards" style="padding: 12px 25px; background: transparent; color: #e0e0e0; border: none; cursor: pointer; font-size: 1.1rem; font-weight: 600; transition: all 0.3s ease; border-left: 1px solid rgba(0, 191, 191, 0.5); font-family: 'Inter', sans-serif;">Listed</button>
                    <button class="toggle-button" data-target="favoritesCards" style="padding: 12px 25px; background: transparent; color: #e0e0e0; border: none; cursor: pointer; font-size: 1.1rem; font-weight: 600; transition: all 0.3s ease; border-left: 1px solid rgba(0, 191, 191, 0.5); font-family: 'Inter', sans-serif;">Favorites</button>
                </div>
                
                <?php
                // Function to fetch NFTs based on type
                function fetchNFTs($type, $userId) {
                    global $pdo;
                    
                    try {
                        $query = "";
                        $params = [];
                        
                        switch($type) {
                            case 'collection':
                                $query = "SELECT n.*, 
                                                u.username as creator_name, 
                                                u.wallet_address as creator_wallet,
                                                c.name as collection_name, 
                                                c.logo_url as collection_logo,
                                                ou.wallet_address as owner_wallet_address
                                          FROM luck_wallet_nfts n 
                                          LEFT JOIN luck_wallet_users u ON n.creator_user_id = u.user_id
                                          LEFT JOIN luck_wallet_users ou ON n.current_owner_user_id = ou.user_id
                                          LEFT JOIN luck_wallet_nft_collections c ON n.collection_id = c.collection_id
                                          WHERE n.current_owner_user_id = ?
                                          ORDER BY n.listing_date DESC";
                                $params = [$userId];
                                break;
                                
                            case 'created':
                                $query = "SELECT n.*, 
                                                u.username as creator_name, 
                                                u.wallet_address as creator_wallet,
                                                c.name as collection_name, 
                                                c.logo_url as collection_logo,
                                                ou.wallet_address as owner_wallet_address
                                          FROM luck_wallet_nfts n 
                                          LEFT JOIN luck_wallet_users u ON n.creator_user_id = u.user_id
                                          LEFT JOIN luck_wallet_users ou ON n.current_owner_user_id = ou.user_id
                                          LEFT JOIN luck_wallet_nft_collections c ON n.collection_id = c.collection_id
                                          WHERE n.creator_user_id = ?
                                          ORDER BY n.listing_date DESC";
                                $params = [$userId];
                                break;
                                
                            case 'listed':
                                $query = "SELECT n.*, 
                                                u.username as creator_name, 
                                                u.wallet_address as creator_wallet,
                                                c.name as collection_name, 
                                                c.logo_url as collection_logo,
                                                ou.wallet_address as owner_wallet_address
                                          FROM luck_wallet_nfts n 
                                          LEFT JOIN luck_wallet_users u ON n.creator_user_id = u.user_id
                                          LEFT JOIN luck_wallet_users ou ON n.current_owner_user_id = ou.user_id
                                          LEFT JOIN luck_wallet_nft_collections c ON n.collection_id = c.collection_id
                                          WHERE n.current_owner_user_id = ? AND n.listed_for_sale = 1
                                          ORDER BY n.listing_date DESC";
                                $params = [$userId];
                                break;
                                
                            case 'favorites':
                                $query = "SELECT n.*, 
                                                u.username as creator_name, 
                                                u.wallet_address as creator_wallet,
                                                c.name as collection_name, 
                                                c.logo_url as collection_logo,
                                                ou.wallet_address as owner_wallet_address
                                          FROM luck_wallet_nfts n 
                                          INNER JOIN luck_wallet_user_favorites f ON n.nft_id = f.nft_id 
                                          LEFT JOIN luck_wallet_users u ON n.creator_user_id = u.user_id
                                          LEFT JOIN luck_wallet_users ou ON n.current_owner_user_id = ou.user_id
                                          LEFT JOIN luck_wallet_nft_collections c ON n.collection_id = c.collection_id
                                          WHERE f.user_id = ?
                                          ORDER BY n.listing_date DESC";
                                $params = [$userId];
                                break;
                        }
                        
                        $stmt = $pdo->prepare($query);
                        $stmt->execute($params);
                        return $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                    } catch (PDOException $e) {
                        error_log("Error fetching $type NFTs: " . $e->getMessage());
                        return [];
                    }
                }
                
                // Fetch all NFTs if user is logged in
                $collectionNFTs = [];
                $createdNFTs = [];
                $listedNFTs = [];
                $favoritesNFTs = [];
                
                if (isset($_SESSION['user_id'])) {
                    error_log("Fetching NFTs for user ID: " . $_SESSION['user_id']);
                    
                    // Test database connection first
                    try {
                        $test = $pdo->query("SHOW TABLES LIKE 'luck_wallet_nfts'")->fetch();
                        error_log("Test query result: " . print_r($test, true));
                        
                        // List all tables for debugging
                        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                        error_log("Available tables: " . print_r($tables, true));
                        
                    } catch (PDOException $e) {
                        error_log("Database error: " . $e->getMessage());
                    }
                    
                    $collectionNFTs = fetchNFTs('collection', $_SESSION['user_id']);
                    error_log("Collection NFTs: " . count($collectionNFTs));
                    
                    $createdNFTs = fetchNFTs('created', $_SESSION['user_id']);
                    error_log("Created NFTs: " . count($createdNFTs));
                    
                    $listedNFTs = fetchNFTs('listed', $_SESSION['user_id']);
                    error_log("Listed NFTs: " . count($listedNFTs));
                    
                    $favoritesNFTs = fetchNFTs('favorites', $_SESSION['user_id']);
                    error_log("Favorite NFTs: " . count($favoritesNFTs));
                } else {
                    error_log("No user ID in session");
                }
                ?>
                
                <!-- Card containers -->
                <div class="toggle-content" style="width: 100%; flex: 1; overflow-y: auto; padding: 0 10px;">
                <!-- Collection Tab Content -->
                <div id="collectionCards" class="nft-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px;">
                    <?php if (!empty($collectionNFTs)): ?>
                        <?php foreach ($collectionNFTs as $nft): ?>
                            <?php echo generateNFTCard($nft, $_SESSION['user_id'] ?? 0, 'collection'); ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="grid-column: 1 / -1; text-align: center; color: #888; padding: 40px 0; background: rgba(10, 30, 30, 0.5); border-radius: 12px; margin: 10px 0;">
                            <i class="material-icons" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.7;">collections</i>
                            <p style="margin: 10px 0 0; font-size: 1.1rem; color: #a0a0a0;">
                                No NFTs in your collection yet.
                            </p>
                            <p style="margin: 10px 0 0; font-size: 0.9rem; color: #666;">
                                Start collecting by purchasing NFTs from the marketplace!
                            </p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Created Tab Content -->
                <div id="createdCards" class="nft-grid" style="display: none; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px;">
                    <?php if (!empty($createdNFTs)): ?>
                        <?php foreach ($createdNFTs as $nft): ?>
                            <?php echo generateNFTCard($nft, $_SESSION['user_id'] ?? 0, 'created'); ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="grid-column: 1 / -1; text-align: center; color: #888; padding: 40px 0; background: rgba(10, 30, 30, 0.5); border-radius: 12px; margin: 10px 0;">
                            <i class="material-icons" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.7;">add_photo_alternate</i>
                            <p style="margin: 10px 0 0; font-size: 1.1rem; color: #a0a0a0;">
                                You haven't created any NFTs yet.
                            </p>
                            <p style="margin: 10px 0 0; font-size: 0.9rem; color: #666;">
                                Create your first NFT to get started!
                            </p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Listed Tab Content -->
                <div id="listedCards" class="nft-grid" style="display: none; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px;">
                    <?php if (!empty($listedNFTs)): ?>
                        <?php foreach ($listedNFTs as $nft): ?>
                            <?php echo generateNFTCard($nft, $_SESSION['user_id'] ?? 0, 'listed'); ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="grid-column: 1 / -1; text-align: center; color: #888; padding: 40px 0; background: rgba(10, 30, 30, 0.5); border-radius: 12px; margin: 10px 0;">
                            <i class="material-icons" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.7;">sell</i>
                            <p style="margin: 10px 0 0; font-size: 1.1rem; color: #a0a0a0;">
                                No NFTs listed for sale.
                            </p>
                            <p style="margin: 10px 0 0; font-size: 0.9rem; color: #666;">
                                List your NFTs to start selling!
                            </p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Favorites Tab Content -->
                <div id="favoritesCards" class="nft-grid" style="display: none; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px;">
                    <?php if (!empty($favoritesNFTs)): ?>
                        <?php foreach ($favoritesNFTs as $nft): ?>
                            <?php echo generateNFTCard($nft, $_SESSION['user_id'] ?? 0, 'favorites'); ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="grid-column: 1 / -1; text-align: center; color: #888; padding: 40px 0; background: rgba(10, 30, 30, 0.5); border-radius: 12px; margin: 10px 0;">
                            <i class="material-icons" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.7;">favorite_border</i>
                            <p style="margin: 10px 0 0; font-size: 1.1rem; color: #a0a0a0;">
                                No favorited NFTs yet.
                            </p>
                            <p style="margin: 10px 0 0; font-size: 0.9rem; color: #666;">
                                Click the heart icon on any NFT to add it to your favorites!
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
                </div>
            </section>
        </main>
    </div>
    
    <script>
    // Toggle functionality for NFT sections
    document.addEventListener('DOMContentLoaded', function() {
        const toggleButtons = document.querySelectorAll('.toggle-button');
        
        toggleButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Remove active class from all buttons
                toggleButtons.forEach(btn => btn.classList.remove('active'));
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

    <!-- Remove Listing Confirmation Modal -->
    <div id="removeListingModal" class="modal" style="display: none; position: fixed; z-index: 10001; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.85); opacity: 0; transition: opacity 0.3s ease; overflow-y: auto; backdrop-filter: blur(5px);">
        <div class="modal-content" style="background-color: #0f1a1a; margin: 10% auto; padding: 30px; border: 1px solid #00BFBF; border-radius: 12px; width: 90%; max-width: 450px; box-shadow: 0 5px 30px rgba(0, 191, 191, 0.3); transform: translateY(-20px); transition: all 0.3s ease; position: relative; overflow: hidden;">
            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: linear-gradient(90deg, #00BFBF, #006666);"></div>
            
            <div class="modal-header" style="text-align: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid rgba(0, 191, 191, 0.2);">
                <div style="width: 70px; height: 70px; background: rgba(255, 77, 77, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; border: 2px solid rgba(255, 77, 77, 0.3);">
                    <i class="material-icons" style="font-size: 36px; color: #ff4d4d;">warning_amber</i>
                </div>
                <h3 style="margin: 0; color: #ff4d4d; font-size: 1.6rem; font-weight: 600; text-shadow: 0 0 10px rgba(255, 77, 77, 0.3);">Remove Listing</h3>
            </div>
            
            <div class="modal-body" style="margin-bottom: 30px; color: #e0e0e0; text-align: center; line-height: 1.6;">
                <p style="font-size: 1.1rem; margin-bottom: 10px;">Are you sure you want to remove this NFT from the marketplace?</p>
                <p style="color: #b0b0b0; font-size: 0.95rem; margin: 0;">This action cannot be undone.</p>
            </div>
            
            <div class="modal-footer" style="display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;">
                <button onclick="window.removeListingModal.close()" 
                        style="background: transparent; color: #fff; border: 1px solid #555; padding: 12px 25px; border-radius: 6px; cursor: pointer; transition: all 0.3s; font-size: 1rem; display: flex; align-items: center; gap: 8px;"
                        onmouseover="this.style.background='#333'; this.style.borderColor='#666'"
                        onmouseout="this.style.background='transparent'; this.style.borderColor='#555'">
                    <i class="material-icons" style="font-size: 1.2rem;">close</i>
                    Cancel
                </button>
                <button id="confirmRemoveBtn" 
                        style="background: linear-gradient(135deg, #ff4d4d, #cc0000); color: white; border: none; padding: 12px 25px; border-radius: 6px; cursor: pointer; font-size: 1rem; font-weight: 500; text-shadow: 0 1px 2px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 8px; transition: all 0.3s; box-shadow: 0 4px 15px rgba(255, 77, 77, 0.3);"
                        onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(255, 77, 77, 0.4)';"
                        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(255, 77, 77, 0.3)'">
                    <i class="material-icons" style="font-size: 1.2rem;">delete_forever</i>
                    Remove Listing
                </button>
            </div>
            
            <div style="margin-top: 25px; text-align: center;">
                <p style="font-size: 0.85rem; color: #888; margin: 0;">
                    <i class="material-icons" style="font-size: 1rem; vertical-align: middle; margin-right: 5px;">info</i>
                    This will remove your NFT from the marketplace listings.
                </p>
            </div>
        </div>
    </div>

    <!-- Edit Listing Modal -->
    <div id="editListingModal" class="modal" style="display: none; position: fixed; z-index: 10002; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.85); opacity: 0; transition: opacity 0.3s ease; overflow-y: auto; backdrop-filter: blur(5px);">
        <div class="modal-content" style="background-color: #0f1a1a; margin: 10% auto; padding: 30px; border: 1px solid #00BFBF; border-radius: 12px; width: 90%; max-width: 450px; box-shadow: 0 5px 30px rgba(0, 191, 191, 0.3); transform: translateY(-20px); transition: all 0.3s ease; position: relative; overflow: hidden;">
            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: linear-gradient(90deg, #00BFBF, #006666);"></div>
            
            <div class="modal-header" style="text-align: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid rgba(0, 191, 191, 0.2);">
                <div style="width: 70px; height: 70px; background: rgba(0, 191, 191, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; border: 2px solid rgba(0, 191, 191, 0.3);">
                    <i class="material-icons" style="font-size: 36px; color: #00BFBF;">edit</i>
                </div>
                <h3 style="margin: 0; color: #00BFBF; font-size: 1.6rem; font-weight: 600; text-shadow: 0 0 10px rgba(0, 191, 191, 0.3);">Edit Listing</h3>
            </div>
            
            <div class="modal-body" style="margin-bottom: 30px; color: #e0e0e0; text-align: center; line-height: 1.6;">
                <p style="font-size: 1.1rem; margin-bottom: 15px;">Update the price for your NFT listing</p>
                
                <div style="background: rgba(0, 191, 191, 0.1); border-radius: 8px; padding: 15px; margin-bottom: 20px; text-align: left;">
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; color: #b0b0b0; font-size: 0.9rem;">Current Price (LUCK)</label>
                        <div style="display: flex; align-items: center; background: rgba(0, 0, 0, 0.3); border: 1px solid #2a3a3a; border-radius: 6px; padding: 10px 15px;">
                            <input type="text" id="currentPrice" readonly style="background: none; border: none; color: #e0e0e0; font-size: 1.1rem; width: 100%; outline: none;">
                            <span style="color: #00BFBF; font-weight: 500; white-space: nowrap; margin-left: 10px;">LUCK</span>
                        </div>
                    </div>
                    
                    <div>
                        <label for="newPrice" style="display: block; margin-bottom: 5px; color: #b0b0b0; font-size: 0.9rem;">New Price (LUCK)</label>
                        <div style="display: flex; align-items: center; background: rgba(0, 0, 0, 0.3); border: 1px solid #2a3a3a; border-radius: 6px; padding: 10px 15px; transition: border-color 0.3s;" id="priceInputContainer">
                            <input type="number" id="newPrice" min="0.01" step="0.01" style="background: none; border: none; color: #fff; font-size: 1.1rem; width: 100%; outline: none;" placeholder="Enter new price">
                            <span style="color: #00BFBF; font-weight: 500; white-space: nowrap; margin-left: 10px;">LUCK</span>
                        </div>
                        <div id="priceError" style="color: #ff6b6b; font-size: 0.85rem; margin-top: 5px; min-height: 20px; text-align: left;"></div>
                    </div>
                </div>
                
                <p style="color: #888; font-size: 0.85rem; margin: 0;">
                    <i class="material-icons" style="font-size: 1rem; vertical-align: middle; margin-right: 5px;">info</i>
                    A 2.5% service fee will be applied to the sale price.
                </p>
            </div>
            
            <div class="modal-footer" style="display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;">
                <button id="cancelEditBtn" style="background: transparent; color: #fff; border: 1px solid #555; padding: 12px 25px; border-radius: 6px; cursor: pointer; transition: all 0.3s; font-size: 1rem; display: flex; align-items: center; gap: 8px;"
                        onmouseover="this.style.background='#333'; this.style.borderColor='#666'"
                        onmouseout="this.style.background='transparent'; this.style.borderColor='#555'">
                    <i class="material-icons" style="font-size: 1.2rem;">close</i>
                    Cancel
                </button>
                <button id="confirmEditBtn" style="background: linear-gradient(135deg, #00BFBF, #008080); color: white; border: none; padding: 12px 25px; border-radius: 6px; cursor: pointer; font-size: 1rem; font-weight: 500; text-shadow: 0 1px 2px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 8px; transition: all 0.3s; box-shadow: 0 4px 15px rgba(0, 191, 191, 0.3);"
                        onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(0, 191, 191, 0.4)';"
                        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(0, 191, 191, 0.3)'">
                    <i class="material-icons" style="font-size: 1.2rem;">check_circle</i>
                    Update Listing
                </button>
            </div>
        </div>
    </div>

    <!-- Send NFT Modal -->
    <div id="sendNftModal" class="modal" style="display: none; position: fixed; z-index: 10004; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.85); opacity: 0; transition: opacity 0.3s ease; overflow-y: auto; backdrop-filter: blur(5px);">
        <div class="modal-content" style="background-color: #0f1a1a; margin: 5% auto; padding: 25px; border: 1px solid #00BFBF; border-radius: 12px; width: 90%; max-width: 450px; box-shadow: 0 5px 30px rgba(0, 191, 191, 0.3); transform: translateY(-20px); transition: all 0.3s ease; position: relative; overflow: hidden;">
            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: linear-gradient(90deg, #00BFBF, #006666);"></div>
            
            <div class="modal-header" style="text-align: center; margin-bottom: 20px; padding-bottom: 15px;">
                <h2 style="color: #00FFCC; margin: 0 0 15px; font-size: 1.8rem; font-weight: 600; text-shadow: 0 0 15px rgba(0, 255, 204, 0.3);">Send NFT</h2>
                <p style="color: #90a4ae; margin: 0; font-size: 0.95rem;">Transfer your NFT to another wallet address</p>
            </div>
            
            <div class="modal-body" style="padding: 0 5px;">
                <!-- NFT Preview Section -->
                <div class="nft-preview" style="background-color: rgba(0, 191, 191, 0.05); border: 1px solid rgba(0, 191, 191, 0.2); border-radius: 10px; padding: 15px; margin-bottom: 20px; text-align: center;">
                    <div id="nftImageContainer" style="width: 150px; height: 150px; margin: 0 auto 15px; border-radius: 8px; overflow: hidden; background-color: #0a0a0a; display: flex; align-items: center; justify-content: center;">
                        <img id="nftPreviewImage" src="" alt="NFT Preview" style="max-width: 100%; max-height: 100%; object-fit: contain; display: none;">
                        <i class="material-icons" id="nftImagePlaceholder" style="font-size: 50px; color: #00BFBF; opacity: 0.5;">image</i>
                    </div>
                    <div id="nftInfo" style="margin-bottom: 15px;">
                        <div style="font-size: 0.9rem; color: #90a4ae; margin-bottom: 5px;">NFT ID: <span id="nftIdDisplay" style="color: #e0e0e0; font-weight: 500;">#12345</span></div>
                    </div>
                </div>
                
                <!-- Recipient Address -->
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="recipientAddress" style="display: block; margin-bottom: 8px; color: #e0e0e0; font-size: 0.95rem;">Recipient Wallet Address</label>
                    <div style="position: relative;">
                        <input type="text" id="recipientAddress" class="form-control" placeholder="0x..." style="width: 100%; padding: 12px 15px; background-color: #0a0a0a; border: 1px solid #1a3a3a; border-radius: 8px; color: #e0e0e0; font-size: 0.9rem; transition: all 0.3s ease; font-family: 'Courier New', monospace;" required>
                        <div id="walletIcon" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #00BFBF; display: none;">
                            <i class="material-icons" style="font-size: 20px;">account_balance_wallet</i>
                        </div>
                    </div>
                    <div id="addressError" style="color: #ff6b6b; font-size: 0.85rem; margin-top: 5px; min-height: 20px; display: none;"></div>
                </div>
                
                <!-- Recipient Preview (shown after address validation) -->
                <div id="recipientPreview" style="display: none; background-color: rgba(0, 191, 191, 0.05); border: 1px solid rgba(0, 191, 191, 0.2); border-radius: 10px; padding: 12px; margin-bottom: 20px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="width: 40px; height: 40px; background-color: rgba(0, 191, 191, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i class="material-icons" style="color: #00BFBF; font-size: 20px;">person</i>
                        </div>
                        <div style="overflow: hidden;">
                            <div style="font-size: 0.9rem; color: #90a4ae;">Sending to:</div>
                            <div id="recipientAddressDisplay" style="font-family: 'Courier New', monospace; font-size: 0.85rem; color: #e0e0e0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"></div>
                        </div>
                    </div>
                </div>
                
                <div class="action-buttons" style="display: flex; gap: 15px; margin-top: 25px;">
                    <button id="cancelSendBtn" class="btn btn-secondary" style="flex: 1; padding: 12px; background: linear-gradient(135deg, #2a3a3a, #1a2a2a); color: #e0e0e0; border: 1px solid #2a4a4a; border-radius: 8px; font-size: 1rem; font-weight: 500; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 8px;" onclick="window.sendNftModal.close()">
                        <i class="material-icons" style="font-size: 20px;">close</i>
                        Cancel
                    </button>
                    <button id="confirmSendBtn" class="btn btn-primary" style="flex: 1; padding: 12px; background: linear-gradient(135deg, #00BFBF, #008080); color: #0a0a0a; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <i class="material-icons" style="font-size: 20px;">send</i>
                        Send NFT
                    </button>
                </div>
                
                <div class="note" style="margin-top: 20px; padding: 12px; background-color: rgba(0, 191, 191, 0.1); border-radius: 6px; border-left: 3px solid #00BFBF;">
                    <p style="margin: 0; color: #90a4ae; font-size: 0.85rem; line-height: 1.5;">
                        <i class="material-icons" style="font-size: 16px; vertical-align: middle; margin-right: 5px; color: #00BFBF;">info</i>
                        This action cannot be undone. Please verify the recipient's wallet address before sending.
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- List for Sale Modal -->
    <div id="listForSaleModal" class="modal" style="display: none; position: fixed; z-index: 10003; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.85); opacity: 0; transition: opacity 0.3s ease; overflow-y: auto; backdrop-filter: blur(5px);">
        <div class="modal-content" style="background-color: #0f1a1a; margin: 10% auto; padding: 30px; border: 1px solid #00BFBF; border-radius: 12px; width: 90%; max-width: 450px; box-shadow: 0 5px 30px rgba(0, 191, 191, 0.3); transform: translateY(-20px); transition: all 0.3s ease; position: relative; overflow: hidden;">
            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: linear-gradient(90deg, #00BFBF, #006666);"></div>
            
            <div class="modal-header" style="text-align: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid rgba(0, 191, 191, 0.2);">
                <div style="width: 70px; height: 70px; background: rgba(0, 191, 191, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; border: 2px solid rgba(0, 191, 191, 0.3);">
                    <i class="material-icons" style="font-size: 36px; color: #00BFBF;">sell</i>
                </div>
                <h3 style="margin: 0; color: #00BFBF; font-size: 1.6rem; font-weight: 600; text-shadow: 0 0 10px rgba(0, 191, 191, 0.3);">List for Sale</h3>
            </div>
            
            <div class="modal-body" style="margin-bottom: 30px; color: #e0e0e0; text-align: center; line-height: 1.6;">
                <p style="font-size: 1.1rem; margin-bottom: 15px;">Set a price for your NFT</p>
                
                <div style="background: rgba(0, 191, 191, 0.1); border-radius: 8px; padding: 15px; margin-bottom: 20px; text-align: left;">
                    <div>
                        <label for="salePrice" style="display: block; margin-bottom: 5px; color: #b0b0b0; font-size: 0.9rem;">Price (LUCK)</label>
                        <div style="display: flex; align-items: center; background: rgba(0, 0, 0, 0.3); border: 1px solid #2a3a3a; border-radius: 6px; padding: 10px 15px; transition: border-color 0.3s;" id="salePriceContainer">
                            <input type="number" id="salePrice" min="0.01" step="0.01" style="background: none; border: none; color: #fff; font-size: 1.1rem; width: 100%; outline: none;" placeholder="Enter price">
                            <span style="color: #00BFBF; font-weight: 500; white-space: nowrap; margin-left: 10px;">LUCK</span>
                        </div>
                        <div id="salePriceError" style="color: #ff6b6b; font-size: 0.85rem; margin-top: 5px; min-height: 20px; text-align: left; display: none;"></div>
                    </div>
                </div>
                
                <p style="color: #888; font-size: 0.85rem; margin: 0;">
                    <i class="material-icons" style="font-size: 1rem; vertical-align: middle; margin-right: 5px;">info</i>
                    A 2.5% service fee will be applied to the sale price.
                </p>
            </div>
            
            <div class="modal-footer" style="display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;">
                <button id="cancelSaleBtn" style="background: transparent; color: #fff; border: 1px solid #555; padding: 12px 25px; border-radius: 6px; cursor: pointer; transition: all 0.3s; font-size: 1rem; display: flex; align-items: center; gap: 8px;"
                        onmouseover="this.style.background='#333'; this.style.borderColor='#666'"
                        onmouseout="this.style.background='transparent'; this.style.borderColor='#555'">
                    <i class="material-icons" style="font-size: 1.2rem;">close</i>
                    Cancel
                </button>
                <button id="confirmSaleBtn" style="background: linear-gradient(135deg, #00BFBF, #008080); color: white; border: none; padding: 12px 25px; border-radius: 6px; cursor: pointer; font-size: 1rem; font-weight: 500; text-shadow: 0 1px 2px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 8px; transition: all 0.3s; box-shadow: 0 4px 15px rgba(0, 191, 191, 0.3);"
                        onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(0, 191, 191, 0.4)';"
                        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(0, 191, 191, 0.3)'">
                    <i class="material-icons" style="font-size: 1.2rem;">check_circle</i>
                    List for Sale
                </button>
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
        
        // Utility function to show messages
        function showMessage(message, type = 'info') {
            // Remove any existing messages
            const existingMessages = document.querySelectorAll('.message-toast');
            existingMessages.forEach(msg => {
                if (msg.parentNode) {
                    msg.parentNode.removeChild(msg);
                }
            });
            
            const messageDiv = document.createElement('div');
            messageDiv.className = `message-toast ${type}`;
            messageDiv.textContent = message;
            
            // Add close button
            const closeBtn = document.createElement('button');
            closeBtn.className = 'close-toast';
            closeBtn.innerHTML = '&times;';
            closeBtn.onclick = () => messageDiv.remove();
            messageDiv.appendChild(closeBtn);
            
            document.body.appendChild(messageDiv);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (messageDiv.parentNode) {
                    messageDiv.style.opacity = '0';
                    setTimeout(() => messageDiv.remove(), 300);
                }
            }, 5000);
            
            return messageDiv;
        }
        
        // Alias for error messages
        function showError(message) {
            return showMessage(message, 'error');
        }
        
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
                if (dropdown && dropdown.classList && dropdown.classList.contains('show')) {
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
                                       (clickedElement && clickedElement.classList && clickedElement.classList.contains('material-icons')) && 
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
                        const isFlipping = !(card && card.classList && card.classList.contains('flipped'));
                        card.classList.toggle('flipped');

                        // Update the currently flipped card reference
                        if (isFlipping) {
                            window.currentlyFlippedCard = card;
                            
                            // Add click outside handler to close card when clicking outside
                            const handleClickOutside = (e) => {
                                if (card && !card.contains(e.target)) {
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
                if (event.target && event.target.classList && event.target.classList.contains('modal')) {
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
                    if (event.target && event.target.classList && event.target.classList.contains('modal')) {
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

        // Function to initialize profile picture upload functionality
        function initializeProfilePictureUpload() {
            const profilePicContainer = document.getElementById('profilePictureContainer');
            const profileImageInput = document.getElementById('profileImageInput');
            const userProfileImage = document.getElementById('userProfileImage');
            const editOverlay = profilePicContainer ? profilePicContainer.querySelector('.edit-overlay') : null;
        window.handleCardClick = function(card, event) {
            if (!card || !event) return;
            
            // Prevent default action
            event.preventDefault();
            event.stopPropagation();
            
            // If the click is on an interactive element, don't flip
            const target = event.target;
            if (target && target.closest && target.closest('button, a, input, select, textarea, .favorite-button')) {
                return;
            }
            
            // Call the card flip handler
            handleCardFlip(card, event);
        };
        
        // Make sure the function is available globally
        if (typeof handleCardClick === 'undefined') {
            window.handleCardClick = function(card, event) {
                if (!card || !event) return;
                event.preventDefault();
                event.stopPropagation();
                handleCardFlip(card, event);
            };
        }
        
        // Function to initialize card click handlers
        function initializeCardClickHandlers() {
            // Find all NFT cards that don't have click handlers yet
            const cards = document.querySelectorAll('.nft-card-small:not([data-click-initialized])');
            
            cards.forEach(card => {
                // Mark as initialized
                card.setAttribute('data-click-initialized', 'true');
                
                // Remove any existing inline onclick handlers to prevent duplicates
                card.removeAttribute('onclick');
                
                // Add event listener for card clicks
                card.addEventListener('click', function(event) {
                    window.handleCardClick(card, event);
                });
                
                // Also handle click on the card's inner elements that might stop propagation
                const innerElements = card.querySelectorAll('*');
                innerElements.forEach(el => {
                    el.addEventListener('click', function(e) {
                        e.stopPropagation();
                    });
                });
            });
        }
        
        // Initialize card click handlers when the DOM is loaded
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeCardClickHandlers);
        } else {
            initializeCardClickHandlers();
        }
        
        // Also initialize when new cards are added dynamically
        const observer = new MutationObserver(function(mutations) {
            let needsUpdate = false;
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length) {
                    needsUpdate = true;
                }
            });
            if (needsUpdate) {
                initializeCardClickHandlers();
            }
        });
        
        // Start observing the document with the configured parameters
        observer.observe(document.body, { childList: true, subtree: true });
        
        // Function to handle card flip
        function handleCardFlip(card) {
            // Don't flip if no card or event
            if (!card || !event) return;
            
            // Don't flip if clicking on interactive elements
            const target = event.target;
            if (target && target.closest && target.closest('button, a, input, select, textarea, .favorite-button')) {
                return;
            }
            
            // If another card is flipped, flip it back first
            if (currentlyFlippedCard && currentlyFlippedCard !== card) {
                if (currentlyFlippedCard.classList) {
                    currentlyFlippedCard.classList.remove('flipped');
                }
                if (window.currentClickOutsideHandler) {
                    document.removeEventListener('click', window.currentClickOutsideHandler);
                    window.currentClickOutsideHandler = null;
                }
            }
            
            // Toggle the clicked card
            const isFlipping = !(card.classList && card.classList.contains('flipped'));
            if (card.classList) {
                card.classList.toggle('flipped');
            }
            
            // Update the currently flipped card reference
            if (isFlipping) {
                currentlyFlippedCard = card;
                
                // Add click outside handler
                const handleClickOutside = (e) => {
                    if (!e || !e.target) return;
                    if (card && !card.contains(e.target)) {
                        if (card.classList) {
                            card.classList.remove('flipped');
                        }
                        currentlyFlippedCard = null;
                        document.removeEventListener('click', handleClickOutside);
                        window.currentClickOutsideHandler = null;
                    }
                };
                
                // Store handler for cleanup
                window.currentClickOutsideHandler = handleClickOutside;
                
                // Add with delay to avoid immediate trigger
                setTimeout(() => {
                    if (document && document.addEventListener) {
                        document.addEventListener('click', handleClickOutside);
                    }
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
                    if (this && this.classList && !this.classList.contains('flipped')) {
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
                    const isExpanded = walletDropdownContent && walletDropdownContent.classList && walletDropdownContent.classList.contains('show');
                    
                    // Update ARIA attributes
                    if (walletButton) {
                        walletButton.setAttribute('aria-expanded', isExpanded);
                    }
                    
                    // Close when clicking outside
                    if (isExpanded) {
                        const closeDropdown = (e) => {
                            if (walletDropdown && !walletDropdown.contains(e.target) && e.target !== walletButton) {
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
                const isOpening = !(sidebarLeft && sidebarLeft.classList && sidebarLeft.classList.contains('mobile-visible'));

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
                    const isExpanded = searchBar && searchBar.classList && searchBar.classList.contains('expanded-mobile');

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
                if (event.type === 'click' && favoriteButton && favoriteButton.classList && !favoriteButton.classList.contains('favorite-button')) {
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
                        el && el.classList && el.classList.contains('material-icons')
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
                
                const isFavorited = favoriteButton && favoriteButton.classList && favoriteButton.classList.contains('favorited');
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
                    const isFavorited = button && button.classList && button.classList.contains('favorited');
                    
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
                    
                    /* Add your custom CSS here */
        
        /* Message toast styles */
        }
        
        try {
            console.log('Sending request to unlist NFT:', nftId);
            const response = await fetch('api/unlist_nft.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ nftId })
            background-color: #4caf50;
        }
        
        .message-toast.error {
            background-color: #f44336;
        }
        
        .message-toast.info {
            background-color: #2196f3;
        }
        
        .close-message {
            background: transparent;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            margin-left: 15px;
            padding: 0 5px;
            line-height: 1;
        }
        
        /* Loading spinner */
        .upload-loading {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            z-index: 2;
        }
        
        .spinner {
            width: 30px;
            height: 30px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        @keyframes slideIn {
            from {
                transform: translate(-50%, 100%);
                opacity: 0;
            }
            to {
                transform: translate(-50%, 0);
                opacity: 1;
            }
        }
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
                        console.log('Card flipped state:', (this && this.classList) ? this.classList.contains('flipped') : 'no classList');

                        // Update the currently flipped card reference
                        if (this.classList && this.classList.contains('flipped')) {
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
        // Function to handle NFT unlisting
        async function removeListing(nftId) {
            if (!confirm('Are you sure you want to remove this NFT from listing?')) {
                return;
            }
            
            try {
                console.log('Sending request to unlist NFT:', nftId);
                const response = await fetch('api/unlist_nft.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ nftId })
                });
                
                // Get the raw response text first
                const responseText = await response.text();
                console.log('Raw response:', responseText);
                
                // Try to parse as JSON
                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (e) {
                    console.error('Failed to parse response as JSON:', e);
                    throw new Error('Server returned an invalid response. Please check the console for details.');
                }
                
                if (result.success) {
                    // Show success message
                    alert('NFT has been unlisted successfully');
                    
                    // Find the card and update its state
                    const card = document.querySelector(`.nft-card-small[data-nft-id="${nftId}"]`);
                    if (card) {
                        // Trigger a page refresh to update all sections
                        window.location.reload();
                    }
                } else {
                    throw new Error(result.message || 'Failed to unlist NFT');
                }
            } catch (error) {
                console.error('Error unlisting NFT:', error);
                alert('Error: ' + (error.message || 'Failed to unlist NFT. Please check the console for more details.'));
            }
        }
        
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
            // Don't flip if no card or event
            if (!card || !event) return;
            
            // Don't flip if clicking on interactive elements
            const target = event.target;
            if (target && target.closest && target.closest('button, a, input, select, textarea, .favorite-button')) {
                return;
            }
            
            // If another card is flipped, flip it back first
            if (currentlyFlippedCard && currentlyFlippedCard !== card) {
                if (currentlyFlippedCard.classList) {
                    currentlyFlippedCard.classList.remove('flipped');
                }
                if (window.currentClickOutsideHandler) {
                    document.removeEventListener('click', window.currentClickOutsideHandler);
                    window.currentClickOutsideHandler = null;
                }
            }
            
            // Toggle the clicked card
            const isFlipping = !(card.classList && card.classList.contains('flipped'));
            if (card.classList) {
                card.classList.toggle('flipped');
            }
            
            // Update the currently flipped card reference
            if (isFlipping) {
                currentlyFlippedCard = card;
                
                // Add click outside handler
                const handleClickOutside = (e) => {
                    if (!e || !e.target) return;
                    if (card && !card.contains(e.target)) {
                        if (card.classList) {
                            card.classList.remove('flipped');
                        }
                        currentlyFlippedCard = null;
                        document.removeEventListener('click', handleClickOutside);
                        window.currentClickOutsideHandler = null;
                    }
                };
                
                // Store handler for cleanup
                window.currentClickOutsideHandler = handleClickOutside;
                
                // Add with delay to avoid immediate trigger
                setTimeout(() => {
                    if (document && document.addEventListener) {
                        document.addEventListener('click', handleClickOutside);
                    }
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
                    if (cardInner && cardInner.classList && cardInner.classList.contains('flipped')) {
                        currentlyFlippedCard = this;
                        
                        // Add click outside handler
                        const handleClickOutside = (e) => {
                            if (this && !this.contains(e.target)) {
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
    // Edit Listing Modal Functionality
    let currentEditNftId = null;

    // Initialize the edit listing modal
    function initEditListingModal() {
        console.log('Initializing edit listing modal...');
        try {
            const modal = document.getElementById('editListingModal');
            const closeBtn = document.getElementById('cancelEditBtn');
            const confirmBtn = document.getElementById('confirmEditBtn');
            const newPriceInput = document.getElementById('newPrice');
            const priceError = document.getElementById('priceError');
            const currentPriceInput = document.getElementById('currentPrice');

            // Check if required elements exist
            if (!modal || !closeBtn || !confirmBtn || !newPriceInput || !priceError || !currentPriceInput) {
                console.error('One or more required elements for edit listing modal not found');
                return false;
            }

            // Open modal function
            window.openEditListingModal = function(nftId, currentPrice) {
                try {
                    if (!nftId || currentPrice === undefined) {
                        throw new Error('Missing required parameters for openEditListingModal');
                    }

                    currentEditNftId = nftId;
                    currentPriceInput.value = currentPrice;
                    newPriceInput.value = '';
                    priceError.textContent = '';
                    
                    // Show modal with animation
                    modal.style.display = 'block';
                    setTimeout(() => {
                        modal.style.opacity = '1';
                        const modalContent = modal.querySelector('.modal-content');
                        if (modalContent) {
                            modalContent.style.transform = 'translateY(0)';
                        }
                    }, 10);
                    
                    // Focus on the new price input
                    setTimeout(() => {
                        try {
                            newPriceInput.focus();
                        } catch (e) {
                            console.error('Error focusing on price input:', e);
                        }
                    }, 100);
                    
                    return true;
                } catch (error) {
                    console.error('Error in openEditListingModal:', error);
                    // Fallback to prompt
                    const newPrice = prompt('Enter new price (LUCK):', currentPrice);
                    if (newPrice !== null) {
                        updateListing(nftId, parseFloat(newPrice));
                    }
                    return false;
                }
            };

            // Close modal function
            function closeEditModal() {
                try {
                    modal.style.opacity = '0';
                    const modalContent = modal.querySelector('.modal-content');
                    if (modalContent) {
                        modalContent.style.transform = 'translateY(-20px)';
                    }
                    
                    setTimeout(() => {
                        modal.style.display = 'none';
                        currentEditNftId = null;
                    }, 300);
                } catch (error) {
                    console.error('Error closing edit modal:', error);
                    if (modal) {
                        modal.style.display = 'none';
                    }
                    currentEditNftId = null;
                }
            }


            // Close button click
            closeBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                closeEditModal();
            });

            // Close when clicking outside modal
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    closeEditModal();
                }
            });

            // Handle confirm button click
            confirmBtn.addEventListener('click', async (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                try {
                    const newPrice = parseFloat(newPriceInput.value);
                    
                    // Validate input
                    if (isNaN(newPrice) || newPrice <= 0) {
                        priceError.textContent = 'Please enter a valid price greater than 0';
                        priceError.style.color = '#ff6b6b';
                        newPriceInput.focus();
                        return;
                    }
                    
                    if (!currentEditNftId) {
                        throw new Error('No NFT selected for update');
                    }
                    
                    confirmBtn.disabled = true;
                    const originalHTML = confirmBtn.innerHTML;
                    confirmBtn.innerHTML = '<i class="material-icons" style="font-size: 1.2rem; margin-right: 5px;">hourglass_empty</i> Updating...';
                    
                    try {
                        const response = await fetch('api/update_listing.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `nft_id=${encodeURIComponent(currentEditNftId)}&new_price=${encodeURIComponent(newPrice)}`
                        });
                        
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        
                        const result = await response.json();
                        
                        if (result && result.success) {
                            // Show success message
                            priceError.textContent = 'Listing updated successfully!';
                            priceError.style.color = '#4caf50';
                            
                            // Close modal and refresh after a short delay
                            setTimeout(() => {
                                closeEditModal();
                                window.location.reload();
                            }, 1000);
                        } else {
                            throw new Error(result && result.message ? result.message : 'Failed to update listing');
                        }
                    } catch (error) {
                        console.error('API Error:', error);
                        throw error; // Re-throw to be caught by outer catch
                    }
                } catch (error) {
                    console.error('Error updating listing:', error);
                    priceError.textContent = error.message || 'An error occurred while updating the listing';
                    priceError.style.color = '#ff6b6b';
                    
                    // Auto-clear error after 5 seconds
                    setTimeout(() => {
                        if (priceError) {
                            priceError.textContent = '';
                        }
                    }, 5000);
                    
                    // Re-enable the button after a short delay to prevent rapid clicking
                    setTimeout(() => {
                        if (confirmBtn) {
                            confirmBtn.disabled = false;
                            confirmBtn.innerHTML = originalHTML || '<i class="material-icons" style="font-size: 1.2rem;">check_circle</i> Update Listing';
                        }
                    }, 1000);
                    
                    return; // Prevent further execution
                }
                
                // This will only be reached if there was no error
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = originalHTML || '<i class="material-icons" style="font-size: 1.2rem;">check_circle</i> Update Listing';
            });

            // Allow pressing Enter in the input to submit
            newPriceInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    confirmBtn.click();
                }
            });
            
            console.log('Edit listing modal initialized successfully');
            return true; // Indicate successful initialization
        } catch (error) {
            console.error('Failed to initialize edit listing modal:', error);
            return false;
        }
    }

    // Global updateListing function
    async function updateListing(nftId, newPrice) {
        try {
            const response = await fetch('api/update_listing.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `nft_id=${encodeURIComponent(nftId)}&new_price=${encodeURIComponent(newPrice)}`
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert('Listing updated successfully!');
                window.location.reload();
            } else {
                throw new Error(result.message || 'Failed to update listing');
            }
        } catch (error) {
            console.error('Error updating listing:', error);
            alert('Error: ' + (error.message || 'Failed to update listing'));
        }
    }
    
    // Global editListing function - defined here to ensure it's available when buttons are clicked
    window.editListing = function(nftId, currentPrice) {
        console.log('editListing called with:', { nftId, currentPrice });
        try {
            // Ensure the modal is initialized
            if (!window.openEditListingModal) {
                console.log('openEditListingModal not found, initializing...');
                if (typeof initEditListingModal === 'function') {
                    initEditListingModal();
                } else {
                    console.error('initEditListingModal function not found');
                    throw new Error('initEditListingModal not found');
                }
            }
            
            // If still not available, use fallback
            if (window.openEditListingModal) {
                console.log('Calling openEditListingModal');
                window.openEditListingModal(nftId, currentPrice);
            } else {
                console.log('openEditListingModal not available, using fallback prompt');
                // Fallback to prompt if modal not initialized
                const newPrice = prompt('Enter new price (LUCK):', currentPrice);
                if (newPrice !== null) {
                    updateListing(nftId, parseFloat(newPrice));
                }
            }
        } catch (error) {
            console.error('Error in editListing:', error);
            // Fallback to prompt on error
            const newPrice = prompt('Enter new price (LUCK):', currentPrice);
            if (newPrice !== null) {
                updateListing(nftId, parseFloat(newPrice));
            }
        }
    };
    
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
            if (searchBar && !searchBar.contains(e.target)) {
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
    <script>
    // Toggle button functionality for NFT categories
    document.addEventListener('DOMContentLoaded', function() {
        const toggleButtons = document.querySelectorAll('.toggle-button');
        const nftGrids = document.querySelectorAll('.nft-grid');
        
        // Show collection tab by default if it exists
        const defaultTab = document.getElementById('collectionCards');
        if (defaultTab && typeof defaultTab.style !== 'undefined') {
            defaultTab.style.display = 'grid';
        }

        toggleButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Remove active class from all buttons
                toggleButtons.forEach(btn => {
                    btn.classList.remove('active');
                    btn.style.background = 'transparent';
                    btn.style.color = '#e0e0e0';
                    btn.style.textShadow = 'none';
                });
                
                // Add active class to the clicked button
                this.classList.add('active');
                this.style.background = 'linear-gradient(45deg, #00BFBF, #008080)';
                this.style.color = '#0a0a0a';
                this.style.textShadow = '0 0 8px #00FFCC';

                // Hide all NFT grids
                nftGrids.forEach(grid => grid.style.display = 'none');

                // Show the corresponding NFT grid
                const targetId = this.getAttribute('data-target');
                const targetGrid = document.getElementById(targetId);
                if (targetGrid) {
                    targetGrid.style.display = 'grid';
                    
                    // Trigger a resize event to ensure proper grid layout
                    setTimeout(() => {
                        window.dispatchEvent(new Event('resize'));
                    }, 0);
                }
            });
            
            // Initialize first button as active
            if (this && this.classList && this.classList.contains('active')) {
                this.click();
            }
        });
        
        // Initialize first tab if none is active
        if (!document.querySelector('.toggle-button.active') && toggleButtons.length > 0) {
            toggleButtons[0].click();
        }
    });
    </script>
    
    <script>
    // Update background image function
    function updateProfileBackground(imageSrc) {
        const profileBackground = document.getElementById('profileBackground');
        if (profileBackground) {
            if (imageSrc) {
                // Create a new image to check if it loads successfully
                const img = new Image();
                img.onload = function() {
                    // Only update if the image loads successfully
                    document.documentElement.style.setProperty('--profile-bg-image', `url(${imageSrc})`);
                };
                img.onerror = function() {
                    // If the image fails to load, use the default background
                    document.documentElement.style.removeProperty('--profile-bg-image');
                };
                img.src = imageSrc;
            } else {
                document.documentElement.style.removeProperty('--profile-bg-image');
            }
        }
    }

    // Update profile background function
    document.addEventListener('DOMContentLoaded', function() {
        const profileImage = document.getElementById('userProfileImage');
        
        // Function to update profile background
        function updateProfileBackground(imageUrl) {
            const profileSection = document.getElementById('userPictureSection');
            if (profileSection) {
                const bgElement = profileSection.querySelector('div:first-child');
                if (bgElement) {
                    bgElement.style.backgroundImage = `linear-gradient(135deg, #006666, #003333), url('${imageUrl}')`;
                }
            }
        }
        
        // Set initial background if profile image exists and it's not the default
        if (window.profileImageUrl) {
            updateProfileBackground(window.profileImageUrl);
        } else if (profileImage && profileImage.src && !profileImage.src.endsWith('logo.png')) {
            updateProfileBackground(profileImage.src);
        }
    });
    
    // Handle wallet address copy functionality
    document.addEventListener('DOMContentLoaded', function() {
        const copyWalletBtn = document.getElementById('copyWalletBtn');
        const copySuccess = document.getElementById('copySuccess');

        if (copyWalletBtn) {
            copyWalletBtn.addEventListener('click', function() {
                const walletText = this.getAttribute('data-wallet');
                if (!walletText) {
                    console.error('No wallet address found');
                    return;
                }
                
                navigator.clipboard.writeText(walletText).then(() => {
                    // Show success message
                    if (copySuccess) {
                        copySuccess.style.display = 'inline';
                        setTimeout(() => {
                            copySuccess.style.display = 'none';
                        }, 2000);
                    }
                }).catch(err => {
                    console.error('Failed to copy text: ', err);
                });
            });
        }
    });

    // Profile picture functionality removed
    </script>
    
    <script>
    // Toggle functionality for NFT sections
    document.addEventListener('DOMContentLoaded', function() {
        const toggleButtons = document.querySelectorAll('.toggle-button');
        
        toggleButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Remove active class from all buttons
                toggleButtons.forEach(btn => {
                    btn.classList.remove('active');
                    btn.style.background = 'transparent';
                    btn.style.color = '#00BFBF';
                });
                
                // Add active class to clicked button
                this.classList.add('active');
                this.style.background = 'linear-gradient(45deg, #00BFBF, #008080)';
                this.style.color = '#0a0a0a';
                
                // Hide all sections
                document.querySelectorAll('.nft-grid').forEach(section => {
                    section.style.display = 'none';
                });
                
                // Show the selected section
                const targetId = this.getAttribute('data-target');
                const targetSection = document.getElementById(targetId);
                if (targetSection) {
                    targetSection.style.display = 'grid';
                }
            });
        });
        
        // Initialize first tab as active
        if (toggleButtons.length > 0) {
            toggleButtons[0].click();
        }
    });
    
    // NFT Action Functions
    function listNFT(nftId) {
        const price = prompt('Enter price in LUCK:');
        if (price === null) return;
        if (isNaN(price) || price <= 0) {
            alert('Please enter a valid price.');
            return;
        }
        
        fetch('api/list_nft.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                nft_id: nftId,
                price: parseFloat(price)
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('NFT listed for sale successfully!');
                location.reload();
            } else {
                alert('Error: ' + (data.error || 'Failed to list NFT'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to list NFT. Please try again.');
        });
    }
    
    function unlistNFT(nftId) {
        if (!confirm('Are you sure you want to unlist this NFT from sale?')) return;
        
        fetch('api/unlist_nft.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ nft_id: nftId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('NFT unlisted successfully!');
                location.reload();
            } else {
                alert('Error: ' + (data.error || 'Failed to unlist NFT'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to unlist NFT. Please try again.');
        });
    }
    
    function transferNFT(nftId) {
        const recipient = prompt('Enter recipient wallet address:');
        if (!recipient) return;
        
        if (!confirm(`Are you sure you want to transfer this NFT to ${recipient}?`)) return;
        
        fetch('api/transfer_nft.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                nft_id: nftId,
                recipient: recipient
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('NFT transferred successfully!');
                location.reload();
            } else {
                alert('Error: ' + (data.error || 'Failed to transfer NFT'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to transfer NFT. Please try again.');
        });
    }
    
    function buyNFT(nftId, price) {
        if (!confirm(`Are you sure you want to buy this NFT for ${price} LUCK?`)) return;
        
        fetch('api/buy_nft.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                nft_id: nftId,
                price: price
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('NFT purchased successfully!');
                location.reload();
            } else {
                alert('Error: ' + (data.error || 'Failed to purchase NFT'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to purchase NFT. Please try again.');
        });
    }
    
    function toggleFavorite(nftId, button) {
        const isFavorited = button && button.classList && button.classList.contains('favorited');
        
        fetch('api/toggle_favorite.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                nft_id: nftId,
                action: isFavorited ? 'remove' : 'add'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (isFavorited) {
                    button.classList.remove('favorited');
                    const icon = button.querySelector('.material-icons');
                    if (icon) {
                        icon.textContent = 'favorite_border';
                    }
                    button.innerHTML = button.innerHTML.replace('Favorited', 'Add to Favorites');
                    button.style.color = '#e0e0e0';
                    button.style.borderColor = 'rgba(255, 255, 255, 0.3)';
                } else {
                    button.classList.add('favorited');
                    const icon = button.querySelector('.material-icons');
                    if (icon) {
                        icon.textContent = 'favorite';
                    }
                    button.innerHTML = button.innerHTML.replace('Add to Favorites', 'Favorited');
                    button.style.color = '#ffd700';
                    button.style.borderColor = '#ffd700';
                }
            } else {
                alert('Error: ' + (data.error || 'Failed to update favorites'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to update favorites. Please try again.');
        });
    }
    </script>
    
    <script>
    // Initialize everything when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM fully loaded, initializing components...');
        
        // Initialize card click handlers
        if (typeof window.initializeCardClickHandlers === 'function') {
            window.initializeCardClickHandlers();
        }
        
        // Also initialize after a short delay to catch any dynamically loaded content
        setTimeout(function() {
            if (typeof window.initializeCardClickHandlers === 'function') {
                window.initializeCardClickHandlers();
            }
        }, 1000);
    });
    </script>

    <!-- Remove Listing Confirmation Modal -->
    <div id="removeListingModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.7);">
        <div class="modal-content" style="background-color: #0a0a0a; margin: 15% auto; padding: 25px; border: 1px solid #00BFBF; border-radius: 10px; width: 90%; max-width: 400px; box-shadow: 0 0 20px rgba(0, 191, 191, 0.5);">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #1a3a3a;">
                <h3 style="margin: 0; color: #00FFCC; font-size: 1.5rem;">Confirm Removal</h3>
                <span class="close-modal" style="color: #aaa; font-size: 24px; font-weight: bold; cursor: pointer; transition: color 0.3s;" onclick="closeRemoveListingModal()">&times;</span>
            </div>
            <div class="modal-body" style="margin-bottom: 25px; color: #e0e0e0;">
                <p>Are you sure you want to remove this NFT from listing?</p>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 15px;">
                <button onclick="closeRemoveListingModal()" style="background: #333; color: #fff; border: 1px solid #555; padding: 8px 20px; border-radius: 5px; cursor: pointer; transition: all 0.3s;">
                    Cancel
                </button>
                <button id="confirmRemoveBtn" style="background: #d32f2f; color: white; border: 1px solid #b71c1c; padding: 8px 20px; border-radius: 5px; cursor: pointer; transition: all 0.3s;">
                    Remove Listing
                </button>
            </div>
        </div>
    </div>

    <script>
    // Global variable to store the current NFT ID being processed
    let currentNftId = null;

    // Function to show the remove listing modal
    function showRemoveListingModal(nftId) {
        currentNftId = nftId;
        const modal = document.getElementById('removeListingModal');
        modal.style.display = 'block';
        
        // Focus the confirm button for better keyboard navigation
        setTimeout(() => {
            const confirmBtn = document.getElementById('confirmRemoveBtn');
            if (confirmBtn) confirmBtn.focus();
        }, 100);
    }

    // Function to close the remove listing modal
    function closeRemoveListingModal() {
        const modal = document.getElementById('removeListingModal');
        if (modal) {
            modal.style.display = 'none';
            currentNftId = null;
        }
    }

    // Global variable to store the current NFT ID being processed
    let currentNftId = null;

    // Function to show the remove listing modal
    function showRemoveListingModal(nftId) {
        console.log('Showing remove listing modal for NFT:', nftId);
        currentNftId = nftId;
        const modal = document.getElementById('removeListingModal');
        if (modal) {
            modal.style.display = 'block';
            // Focus the confirm button for better keyboard navigation
            setTimeout(() => {
                const confirmBtn = document.getElementById('confirmRemoveBtn');
                if (confirmBtn) confirmBtn.focus();
            }, 100);
        } else {
            console.error('Remove listing modal not found');
            // Fallback to old confirm if modal not found
            if (confirm('Are you sure you want to remove this NFT from listing?')) {
                removeListing(nftId);
            }
        }
    }


    // Function to handle the confirm button click
    async function confirmRemoveListing() {
        console.log('Confirming removal for NFT:', currentNftId);
        if (!currentNftId) {
            console.error('No NFT ID provided for removal');
            return;
        }
        
        try {
            const response = await fetch('api/remove_listing.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `nft_id=${encodeURIComponent(currentNftId)}`
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Refresh the page to show updated listing status
                window.location.reload();
            } else {
                alert('Failed to remove listing: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error removing listing:', error);
            alert('An error occurred while removing the listing');
        }
        
        closeRemoveListingModal();
    }

    // Function to close the remove listing modal
    function closeRemoveListingModal() {
        console.log('Closing remove listing modal');
        const modal = document.getElementById('removeListingModal');
        if (modal) {
            modal.style.display = 'none';
        }
        currentNftId = null;
    }

    // Close modal when clicking outside the content
    document.addEventListener('click', function(event) {
        const modal = document.getElementById('removeListingModal');
        if (event.target === modal) {
            closeRemoveListingModal();
        }
    });

    // Make sure the modal is hidden on page load
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('removeListingModal');
        if (modal) {
            modal.style.display = 'none';
        }
    });
</script>

<!-- Edit Listing Modal -->
<div id="editListingModal" class="modal" style="display: none; position: fixed; z-index: 10001; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.8);">
    <div class="modal-content" style="background-color: #0a0a0a; margin: 15% auto; padding: 25px; border: 1px solid #00BFBF; border-radius: 10px; width: 90%; max-width: 400px; box-shadow: 0 0 20px rgba(0, 191, 191, 0.5);">
        <div style="text-align: center; margin-bottom: 20px;">
            <div style="width: 60px; height: 60px; background: rgba(0, 191, 191, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; border: 2px solid rgba(0, 191, 191, 0.3);">
                <i class="material-icons" style="font-size: 30px; color: #00BFBF;">edit</i>
            </div>
            <h3 style="color: #fff; margin: 0 0 15px 0; font-size: 1.4em;">Edit Listing Price</h3>
            <p style="color: #aaa; margin: 0 0 20px 0; font-size: 0.95em; line-height: 1.5;">
                Update the price for your NFT listing
            </p>
        </div>
        
        <div style="margin-bottom: 20px;">
            <div style="margin-bottom: 15px;">
                <label style="display: block; color: #aaa; margin-bottom: 8px; text-align: left; font-size: 0.9em;">Current Price (LUCK)</label>
                <input type="text" id="currentPrice" readonly style="width: 100%; padding: 10px; background: #111; border: 1px solid #333; border-radius: 5px; color: #fff; font-size: 1em;" />
            </div>
            <div>
                <label style="display: block; color: #00BFBF; margin-bottom: 8px; text-align: left; font-size: 0.9em;">New Price (LUCK)</label>
                <input type="number" id="newPrice" placeholder="Enter new price" style="width: 100%; padding: 10px; background: #111; border: 1px solid #00BFBF; border-radius: 5px; color: #fff; font-size: 1em;" />
                <div id="priceError" style="color: #ff6b6b; font-size: 0.85em; margin-top: 5px; min-height: 20px; text-align: left;"></div>
            </div>
        </div>
        
        <div style="display: flex; justify-content: space-between; gap: 10px; margin-top: 25px;">
            <button id="cancelEditBtn" style="flex: 1; padding: 12px; background: #222; border: 1px solid #444; color: #ddd; border-radius: 5px; cursor: pointer; font-size: 0.95em; transition: all 0.2s;">
                Cancel
            </button>
            <button id="confirmEditBtn" style="flex: 1; padding: 12px; background: #00BFBF; border: none; color: #fff; border-radius: 5px; cursor: pointer; font-size: 0.95em; font-weight: 500; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px;">
                <i class="material-icons" style="font-size: 1.2rem;">check_circle</i>
                Update Price
            </button>
        </div>
    </div>
</div>

<!-- List for Sale Modal -->
<div id="listForSaleModal" class="modal" style="display: none; position: fixed; z-index: 10002; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.8);">
    <div class="modal-content" style="background-color: #0a0a0a; margin: 15% auto; padding: 25px; border: 1px solid #00BFBF; border-radius: 10px; width: 90%; max-width: 400px; box-shadow: 0 0 20px rgba(0, 191, 191, 0.5);">
        <div style="text-align: center; margin-bottom: 20px;">
            <div style="width: 60px; height: 60px; background: rgba(0, 191, 191, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; border: 2px solid rgba(0, 191, 191, 0.3);">
                <i class="material-icons" style="font-size: 30px; color: #00BFBF;">sell</i>
            </div>
            <h3 style="color: #fff; margin: 0 0 15px 0; font-size: 1.4em;">List for Sale</h3>
            <p style="color: #aaa; margin: 0 0 20px 0; font-size: 0.95em; line-height: 1.5;">
                Set a price for your NFT
            </p>
        </div>
        
        <div style="margin-bottom: 20px;">
            <div>
                <label style="display: block; color: #00BFBF; margin-bottom: 8px; text-align: left; font-size: 0.9em;">Price (LUCK)</label>
                <input type="number" id="salePrice" placeholder="Enter price in LUCK" style="width: 100%; padding: 10px; background: #111; border: 1px solid #00BFBF; border-radius: 5px; color: #fff; font-size: 1em;" step="0.00000001" min="0" />
                <div id="salePriceError" style="color: #ff6b6b; font-size: 0.85em; margin-top: 5px; min-height: 20px; text-align: left;"></div>
            </div>
        </div>
        
        <div style="display: flex; justify-content: space-between; gap: 10px; margin-top: 25px;">
            <button id="cancelSaleBtn" style="flex: 1; padding: 12px; background: #222; border: 1px solid #444; color: #ddd; border-radius: 5px; cursor: pointer; font-size: 0.95em; transition: all 0.2s;">
                Cancel
            </button>
            <button id="confirmSaleBtn" style="flex: 1; padding: 12px; background: #00BFBF; border: none; color: #fff; border-radius: 5px; cursor: pointer; font-size: 0.95em; font-weight: 500; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px;">
                <i class="material-icons" style="font-size: 1.2rem;">check_circle</i>
                List for Sale
            </button>
        </div>
    </div>
</div>

<!-- Remove Listing Confirmation Modal -->
<div id="removeListingModal" class="modal" style="display: none; position: fixed; z-index: 10001; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.8);">
    <div class="modal-content" style="background-color: #0a0a0a; margin: 15% auto; padding: 25px; border: 1px solid #00BFBF; border-radius: 10px; width: 90%; max-width: 400px; box-shadow: 0 0 20px rgba(0, 191, 191, 0.5);">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #1a3a3a;">
            <h3 style="margin: 0; color: #00FFCC; font-size: 1.5rem;">Confirm Removal</h3>
            <span class="close-modal" style="color: #aaa; font-size: 24px; font-weight: bold; cursor: pointer; transition: color 0.3s;" onclick="closeRemoveListingModal()">&times;</span>
        </div>
        <div class="modal-body" style="margin-bottom: 25px; color: #e0e0e0;">
            <p>Are you sure you want to remove this NFT from listing?</p>
        </div>
        <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 15px;">
            <button onclick="closeRemoveListingModal()" style="background: #333; color: #fff; border: 1px solid #555; padding: 8px 20px; border-radius: 5px; cursor: pointer; transition: all 0.3s;">
                Cancel
            </button>
            <button id="confirmRemoveBtn" style="background: #d32f2f; color: white; border: 1px solid #b71c1c; padding: 8px 20px; border-radius: 5px; cursor: pointer; transition: all 0.3s;" onclick="confirmRemoveListing()">
                Remove Listing
            </button>
        </div>
    </div>
</div>

<script>
// Global variable to store the current NFT ID being processed
let currentNftId = null;

// Function to show the remove listing modal
function showRemoveListingModal(nftId) {
    currentNftId = nftId;
    const modal = document.getElementById('removeListingModal');
    if (modal) {
        modal.style.display = 'block';
        // Focus the confirm button for better keyboard navigation
        setTimeout(() => {
            const confirmBtn = document.getElementById('confirmRemoveBtn');
            if (confirmBtn) confirmBtn.focus();
        }, 100);
    } else {
        // Fallback to old confirm if modal not found
        if (confirm('Are you sure you want to remove this NFT from listing?')) {
            removeListing(nftId);
        }
    }
}

// Function to handle the confirm button click
async function confirmRemoveListing() {
    if (!currentNftId) return;
    
    try {
        const response = await fetch('api/remove_listing.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `nft_id=${encodeURIComponent(currentNftId)}`
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Refresh the page to show updated listing status
            window.location.reload();
        } else {
            alert('Failed to remove listing: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error removing listing:', error);
        alert('An error occurred while removing the listing');
    }
    
    closeRemoveListingModal();
}

// Function to close the remove listing modal
function closeRemoveListingModal() {
    const modal = document.getElementById('removeListingModal');
    if (modal) {
        modal.style.display = 'none';
    }
    currentNftId = null;
}

// Close modal when clicking outside the content
window.onclick = function(event) {
    const modal = document.getElementById('removeListingModal');
    if (event.target === modal) {
        closeRemoveListingModal();
    }
};
</script>

<script>
// Edit Listing Modal Functionality - Moved to global scope at the top of the file
    try {
        const modal = document.getElementById('editListingModal');
        const closeBtn = document.getElementById('cancelEditBtn');
        const confirmBtn = document.getElementById('confirmEditBtn');
        const newPriceInput = document.getElementById('newPrice');
        const priceError = document.getElementById('priceError');
        const currentPriceInput = document.getElementById('currentPrice');

        // Check if required elements exist
        if (!modal || !closeBtn || !confirmBtn || !newPriceInput || !priceError || !currentPriceInput) {
            console.error('One or more required elements for edit listing modal not found');
            return false;
        }

        // Open modal function
        window.openEditListingModal = function(nftId, currentPrice) {
            try {
                if (!nftId || currentPrice === undefined) {
                    throw new Error('Missing required parameters for openEditListingModal');
                }

                currentEditNftId = nftId;
                currentPriceInput.value = currentPrice;
                newPriceInput.value = '';
                priceError.textContent = '';
                
                // Show modal with animation
                modal.style.display = 'block';
                setTimeout(() => {
                    modal.style.opacity = '1';
                    const modalContent = modal.querySelector('.modal-content');
                    if (modalContent) {
                        modalContent.style.transform = 'translateY(0)';
                    }
                }, 10);
                
                // Focus on the new price input
                setTimeout(() => {
                    try {
                        newPriceInput.focus();
                    } catch (e) {
                        console.error('Error focusing on price input:', e);
                    }
                }, 100);
            } catch (error) {
                console.error('Error in openEditListingModal:', error);
                // Fallback to prompt
                const newPrice = prompt('Enter new price (LUCK):', currentPrice);
                if (newPrice !== null) {
                    updateListing(nftId, parseFloat(newPrice));
                }
            }
        };

        // Close modal function
        function closeEditModal() {
            try {
                if (!modal) return;
                
                modal.style.opacity = '0';
                const modalContent = modal.querySelector('.modal-content');
                if (modalContent) {
                    modalContent.style.transform = 'translateY(-20px)';
                }
                
                setTimeout(() => {
                    if (modal) {
                        modal.style.display = 'none';
                    }
                    currentEditNftId = null;
                }, 300);
            } catch (error) {
                console.error('Error closing edit modal:', error);
                if (modal) {
                    modal.style.display = 'none';
                }
                currentEditNftId = null;
            }
        }


        // Close button click
        closeBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            closeEditModal();
        });

        // Close when clicking outside modal
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeEditModal();
            }
        });

        // Handle confirm button click
        confirmBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            e.stopPropagation();
            
            try {
                const newPrice = parseFloat(newPriceInput.value);
                
                // Validate input
                if (isNaN(newPrice) || newPrice <= 0) {
                    priceError.textContent = 'Please enter a valid price greater than 0';
                    priceError.style.color = '#ff6b6b';
                    newPriceInput.focus();
                    return;
                }
                
                if (!currentEditNftId) {
                    throw new Error('No NFT selected for update');
                }
                
                confirmBtn.disabled = true;
                const originalHTML = confirmBtn.innerHTML;
                confirmBtn.innerHTML = '<i class="material-icons" style="font-size: 1.2rem; margin-right: 5px;">hourglass_empty</i> Updating...';
                
                try {
                    const response = await fetch('api/update_listing.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `nft_id=${encodeURIComponent(currentEditNftId)}&new_price=${encodeURIComponent(newPrice)}`
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const result = await response.json();
                    
                    if (result && result.success) {
                        // Show success message
                        priceError.textContent = 'Listing updated successfully!';
                        priceError.style.color = '#4caf50';
                        
                        // Close modal and refresh after a short delay
                        setTimeout(() => {
                            closeEditModal();
                            window.location.reload();
                        }, 1000);
                    } else {
                        throw new Error(result && result.message ? result.message : 'Failed to update listing');
                    }
                } catch (error) {
                    console.error('API Error:', error);
                    throw error; // Re-throw to be caught by outer catch
                }
            } catch (error) {
                console.error('Error updating listing:', error);
                priceError.textContent = error.message || 'An error occurred while updating the listing';
                priceError.style.color = '#ff6b6b';
                
                // Auto-clear error after 5 seconds
                setTimeout(() => {
                    if (priceError) {
                        priceError.textContent = '';
                    }
                }, 5000);
                
                // Re-enable the button after a short delay to prevent rapid clicking
                setTimeout(() => {
                    if (confirmBtn) {
                        confirmBtn.disabled = false;
                        confirmBtn.innerHTML = originalHTML || '<i class="material-icons" style="font-size: 1.2rem;">check_circle</i> Update Listing';
                    }
                }, 1000);
                
                return; // Prevent further execution
            }
            
            // This will only be reached if there was no error
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = originalHTML || '<i class="material-icons" style="font-size: 1.2rem;">check_circle</i> Update Listing';
        });

        // Allow pressing Enter in the input to submit
        newPriceInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                confirmBtn.click();
            }
        });
        
        return true; // Indicate successful initialization
}

// List for Sale Modal Management
window.listForSaleModal = {
    currentNftId: null,
    isAnimating: false,
    initialized: false,
    
    init: function() {
        if (this.initialized) return;
        this.initialized = true;
        const modal = document.getElementById('listForSaleModal');
        const confirmBtn = document.getElementById('confirmSaleBtn');
        const cancelBtn = document.getElementById('cancelSaleBtn');
        const salePriceInput = document.getElementById('salePrice');
        const priceError = document.getElementById('salePriceError');
        
        if (!modal || !confirmBtn || !cancelBtn || !salePriceInput) {
            console.error('List for sale modal elements not found');
            return;
        }
        
        // Open modal function
        this.open = (nftId) => {
            if (this.isAnimating) return;
            
            console.log('Opening list for sale modal for NFT:', nftId);
            this.currentNftId = nftId;
            
            // Reset form
            salePriceInput.value = '';
            priceError.textContent = '';
            
            // Show modal with animation
            modal.style.display = 'block';
            setTimeout(() => {
                modal.style.opacity = '1';
                const modalContent = modal.querySelector('.modal-content');
                if (modalContent) {
                    modalContent.style.transform = 'translateY(0)';
                }
                // Focus on price input
                salePriceInput.focus();
            }, 10);
        };
        
        // Close modal function
        this.close = () => {
            if (this.isAnimating) return;
            
            console.log('Closing list for sale modal');
            this.isAnimating = true;
            
            // Animate out
            modal.style.opacity = '0';
            const modalContent = modal.querySelector('.modal-content');
            if (modalContent) {
                modalContent.style.transform = 'translateY(-20px)';
            }
            
            // Hide after animation
            setTimeout(() => {
                modal.style.display = 'none';
                this.currentNftId = null;
                this.isAnimating = false;
            }, 300);
        };
        
        // Handle confirm button click
        confirmBtn.onclick = async (e) => {
            e.stopPropagation();
            if (this.isAnimating || !this.currentNftId) return;
            
            const price = parseFloat(salePriceInput.value);
            
            // Validate price
            if (isNaN(price) || price <= 0) {
                priceError.textContent = 'Please enter a valid price';
                return;
            }
            
            // Add loading state
            const originalText = confirmBtn.innerHTML;
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<i class="material-icons" style="margin-right: 8px; animation: spin 1s linear infinite;">autorenew</i> Listing...';
            
            try {
                const response = await fetch('api/list_nft.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        nft_id: this.currentNftId,
                        price: price
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Show success message and close modal
                    priceError.style.color = '#4caf50';
                    priceError.textContent = 'NFT listed successfully!';
                    
                    // Refresh the page after a short delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    throw new Error(result.message || 'Failed to list NFT');
                }
            } catch (error) {
                console.error('Error listing NFT:', error);
                priceError.textContent = error.message || 'Failed to list NFT. Please try again.';
                
                // Reset button state after a delay
                setTimeout(() => {
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = originalText;
                }, 2000);
            }
        };
        
        // Handle cancel button click
        cancelBtn.onclick = (e) => {
            e.stopPropagation();
            this.close();
        };
        
        // Close when clicking outside modal
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                this.close();
            }
        });
        
        // Handle Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.style.display === 'block') {
                this.close();
            }
        });
        
        console.log('List for sale modal initialized');
    },
    
    // Public method to open the modal
    openModal: function(nftId) {
        try {
            if (!this.initialized) {
                this.init();
            }
            if (typeof this.open === 'function') {
                this.open(nftId);
            } else {
                console.error('listForSaleModal.open is not a function');
            }
        } catch (error) {
            console.error('Error in openModal:', error);
        }
    }
};

// Initialize the modal when the page loads
document.addEventListener('DOMContentLoaded', function() {
    try {
        initEditListingModal();
    } catch (error) {
        console.error('Failed to initialize edit listing modal:', error);
    }
});

// Fallback function to update listing (if modal fails)
async function updateListing(nftId, newPrice) {
    if (isNaN(newPrice) || newPrice <= 0) {
        alert('Please enter a valid price greater than 0');
        return;
    }
    
    try {
        const response = await fetch('api/update_listing.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `nft_id=${encodeURIComponent(nftId)}&new_price=${encodeURIComponent(newPrice)}`
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Listing updated successfully!');
            window.location.reload();
        } else {
            throw new Error(result.message || 'Failed to update listing');
        }
    } catch (error) {
        console.error('Error updating listing:', error);
        alert('Error: ' + (error.message || 'Failed to update listing'));
    }
}
</script>

    <!-- Send NFT Modal -->
    <div id="sendNftModal" class="modal" style="display: none; position: fixed; z-index: 10004; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.85); opacity: 0; transition: opacity 0.3s ease; overflow-y: auto; backdrop-filter: blur(5px);">
        <div class="modal-content" style="background-color: #0f1a1a; margin: 10% auto; padding: 30px; border: 1px solid #00BFBF; border-radius: 12px; width: 90%; max-width: 450px; box-shadow: 0 5px 30px rgba(0, 191, 191, 0.3); transform: translateY(-20px); transition: all 0.3s ease; position: relative; overflow: hidden;">
            <span class="close-modal" style="position: absolute; right: 20px; top: 15px; color: #90a4ae; font-size: 28px; font-weight: bold; cursor: pointer; z-index: 10;">&times;</span>
            
            <div style="text-align: center; margin-bottom: 25px;">
                <h2 style="color: #e0e0e0; margin: 0 0 20px 0; font-size: 1.5rem; font-weight: 600; letter-spacing: 0.5px;">
                    <i class="material-icons" style="vertical-align: middle; margin-right: 8px; color: #00BFBF;">send</i>
                    Send NFT
                </h2>
            </div>
            
            <!-- NFT Preview Container -->
            <div id="nftPreviewContainer" style="margin-bottom: 25px; text-align: center;">
                <div id="nftImageContainer" style="width: 150px; height: 150px; margin: 0 auto 15px; border-radius: 12px; overflow: hidden; background-color: #1a2a2a; position: relative;">
                    <div id="nftImagePlaceholder" style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #1a2a2a 0%, #0f1a1a 100%);">
                        <i class="material-icons" style="font-size: 40px; color: #2a3a3a;">image</i>
                    </div>
                    <img id="nftPreviewImage" src="" alt="NFT Preview" style="display: none; width: 100%; height: 100%; object-fit: cover;">
                    <div id="nftIdDisplay" style="position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0, 0, 0, 0.7); color: white; padding: 4px 0; text-align: center; font-size: 0.8rem;"></div>
                </div>
                <div id="nftName" style="font-size: 1.1rem; font-weight: 500; color: #e0e0e0; margin-top: 10px;"></div>
            </div>
            
            <!-- Recipient Address Input -->
            <div style="margin-bottom: 20px;">
                <label for="recipientAddress" style="display: block; margin-bottom: 8px; color: #e0e0e0; font-size: 0.9rem; font-weight: 500;">
                    Recipient Wallet Address
                </label>
                <div style="position: relative;">
                    <input type="text" id="recipientAddress" placeholder="0x..." style="width: 100%; padding: 12px 15px; padding-right: 40px; border: 1px solid #2a3a3a; border-radius: 8px; background-color: #1a2a2a; color: #e0e0e0; font-size: 0.9rem; transition: all 0.3s ease;" autocomplete="off">
                    <div id="walletIcon" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #2a3a3a; display: none; width: 24px; height: 24px; align-items: center; justify-content: center;">
                        <i class="material-icons" style="font-size: 20px;">check_circle</i>
                    </div>
                </div>
                <div id="addressError" style="color: #ff6b6b; font-size: 0.8rem; margin-top: 5px; display: none;"></div>
                
                <!-- Warning Message -->
                <div class="note" style="margin-top: 15px; padding: 12px; background-color: rgba(0, 191, 191, 0.1); border-radius: 6px; border-left: 3px solid #00BFBF;">
                    <p style="margin: 0; color: #90a4ae; font-size: 0.85rem; line-height: 1.5;">
                        <i class="material-icons" style="font-size: 16px; vertical-align: middle; margin-right: 5px; color: #00BFBF;">info</i>
                        This action cannot be undone. Please verify the recipient's wallet address before sending.
                    </p>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div style="display: flex; gap: 12px; margin-top: 30px;">
                <button id="cancelSendBtn" style="flex: 1; padding: 12px; background-color: transparent; border: 1px solid #2a3a3a; border-radius: 8px; color: #e0e0e0; font-size: 0.95rem; font-weight: 500; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <i class="material-icons" style="font-size: 1.2rem;">close</i>
                    Cancel
                </button>
                <button id="confirmSendBtn" style="flex: 1; padding: 12px; background: linear-gradient(135deg, #00BFBF, #008080); border: none; border-radius: 8px; color: white; font-size: 0.95rem; font-weight: 500; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <i class="material-icons" style="font-size: 1.2rem;">send</i>
                    Send NFT
                </button>
            </div>
        </div>
    </div>
    
    <script>
    // Initialize Send NFT Modal when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Initializing Send NFT modal...');
        
        // Check if sendNftModal exists
        if (typeof window.sendNftModal !== 'undefined') {
            try {
                // Initialize the modal
                window.sendNftModal.init();
                console.log('Send NFT modal initialized successfully');
                
            } catch (error) {
                console.error('Error initializing Send NFT modal:', error);
            console.error('sendNftModal is not defined');
        }
    });
    </script>

    <!-- Profile Picture Upload Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const profilePicContainer = document.getElementById('profilePictureContainer');
        const profileImageInput = document.getElementById('profileImageInput');
        const userProfileImage = document.getElementById('userProfileImage');
        
        if (!profilePicContainer || !profileImageInput || !userProfileImage) {
            console.log('Profile picture elements not found, will retry...');
            setTimeout(arguments.callee, 500);
            return;
        }
        
        // Handle click on profile picture to trigger file input
        function handleProfilePicClick() {
            profileImageInput.click();
        }
            
        // Add click event listener
        profilePicContainer.addEventListener('click', handleProfilePicClick);
            
        // Handle file selection
        profileImageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            // Validate file type
            const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!validTypes.includes(file.type)) {
                showNotification('Please select a valid image file (JPEG, PNG, GIF, or WebP)', 'error');
                return;
            }
            
            // Validate file size (max 5MB)
            if (file.size > 5 * 1024 * 1024) {
                showNotification('Image size should be less than 5MB', 'error');
                return;
            }
            
            // Show loading state
            const originalSrc = userProfileImage.src;
            userProfileImage.src = 'assets/images/loading.gif';
            
            // Create form data
            const formData = new FormData();
            formData.append('profile_image', file);
            
            // Show loading state with a simple spinner (no external image needed)
            const originalContent = profilePicContainer.innerHTML;
            profilePicContainer.innerHTML = `
                <div style="display: flex; justify-content: center; align-items: center; height: 100%;">
                    <div style="width: 2rem; height: 2rem; border: 0.25rem solid #f3f3f3; border-top: 0.25rem solid #00BFBF; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                </div>
                <style>
                    @keyframes spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                </style>
            `;
            
            // Send the file to the server
            fetch('update_profile_picture.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'  // Include session cookies
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => { 
                        throw new Error(err.message || 'Upload failed with status ' + response.status); 
                    }).catch(() => {
                        throw new Error('Server returned status ' + response.status);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (!data || data.success === undefined) {
                    throw new Error('Invalid response from server');
                }
                
                if (!data.success) {
                    throw new Error(data.message || 'Failed to update profile picture');
                }
                
                // Update the profile image with cache-busting
                const timestamp = new Date().getTime();
                const imageUrl = data.data?.image_url || userProfileImage.src;
                userProfileImage.src = imageUrl + (imageUrl.includes('?') ? '&' : '?') + 't=' + timestamp;
                
                // Update background image if it uses the profile picture
                const bgElements = document.querySelectorAll('[style*="--profile-bg-image"]');
                bgElements.forEach(el => {
                    const currentBg = getComputedStyle(el).getPropertyValue('--profile-bg-image');
                    if (currentBg.includes('get_profile_picture.php')) {
                        el.style.setProperty('--profile-bg-image', `url('${imageUrl}?t=${timestamp}')`);
                    }
                });
                
                // Show success message
                if (typeof showToast === 'function') {
                    showToast('Profile picture updated successfully', 'success');
                } else {
                    alert('Profile picture updated successfully');
                }
            })
            .catch(error => {
                console.error('Error uploading profile picture:', error);
                
                // Show error message
                const errorMessage = error.message || 'Failed to upload profile picture';
                if (typeof showToast === 'function') {
                    showToast(errorMessage, 'error');
                } else {
                    alert('Error: ' + errorMessage);
                }
                
                // Restore original content
                profilePicContainer.innerHTML = originalContent;
                
                // Re-attach event listeners
                const newProfilePicContainer = document.getElementById('profilePictureContainer');
                if (newProfilePicContainer) {
                    newProfilePicContainer.addEventListener('click', handleProfilePicClick);
                }
            });
        });
        
        // Helper function to show messages
        function showMessage(message, type = 'info') {
            // Create or update message element
            let messageEl = document.getElementById('profileMessage');
            if (!messageEl) {
                messageEl = document.createElement('div');
                messageEl.id = 'profileMessage';
                messageEl.style.position = 'fixed';
                messageEl.style.top = '20px';
                messageEl.style.left = '50%';
                messageEl.style.transform = 'translateX(-50%)';
                messageEl.style.padding = '12px 24px';
                messageEl.style.borderRadius = '4px';
                messageEl.style.color = '#fff';
                messageEl.style.fontWeight = '500';
                messageEl.style.zIndex = '9999';
                messageEl.style.transition = 'all 0.3s ease';
                document.body.appendChild(messageEl);
            }
            
            // Set message and style based on type
            messageEl.textContent = message;
            messageEl.style.backgroundColor = type === 'success' ? '#4caf50' : type === 'error' ? '#f44336' : '#2196f3';
            messageEl.style.opacity = '1';
            
            // Auto-hide after 3 seconds
            clearTimeout(window.profileMessageTimeout);
            window.profileMessageTimeout = setTimeout(() => {
                messageEl.style.opacity = '0';
                setTimeout(() => {
                    if (messageEl.parentNode) {
                        messageEl.parentNode.removeChild(messageEl);
                    }
                }, 300);
            }, 3000);
        }
        
        // Handle profile picture container click
        function handleProfilePicClick(e) {
            if (e.target !== profileImageInput) {
                profileImageInput.click();
            }
        }
        
        // Add initial click handler
        profilePicContainer.addEventListener('click', handleProfilePicClick);
    });
    </script>
</body>
</html>
