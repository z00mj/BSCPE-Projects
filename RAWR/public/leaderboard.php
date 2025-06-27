<?php
require_once __DIR__ . '/../backend/inc/init.php';
userOnly();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Fetch current user data (same as dashboard.php)
$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$currentUser = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

// Check if user was found
if (!$currentUser) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Fetch leaderboard data
$tokenLeaderboard = [];
$ticketLeaderboard = [];

try {
    // Tokens leaderboard
    $stmt = $db->prepare("
        SELECT id, username, rawr_balance AS tokens, ticket_balance AS tickets
        FROM users 
        ORDER BY rawr_balance DESC 
        LIMIT 30
    ");
    $stmt->execute();
    $tokenLeaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Tickets leaderboard
    $stmt = $db->prepare("
        SELECT id, username, rawr_balance AS tokens, ticket_balance AS tickets
        FROM users 
        ORDER BY ticket_balance DESC 
        LIMIT 30
    ");
    $stmt->execute();
    $ticketLeaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    // Handle error - show message to user
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RAWR Casino - Leaderboard</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/RAWR/public/css/style.css">
    <style>
        :root {
            --primary: #FFD700;
            --primary-dark: #FFA500;
            --secondary: #FF6B35;
            --bg-dark: #1a1a1a;
            --bg-darker: #121212;
            --bg-gradient: linear-gradient(135deg, #1a1a1a 0%, #2d1810 50%, #1a1a1a 100%);
            --text-light: #f0f0f0;
            --text-muted: #ccc;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg-gradient);
            color: var(--text-light);
            overflow-x: hidden;
            line-height: 1.6;
            position: relative;
        }
        .leaderboard-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800"><defs><radialGradient id="stars"><stop offset="0%" stop-color="%23FFD700" stop-opacity="1"/><stop offset="100%" stop-color="%23FFD700" stop-opacity="0"/></radialGradient></defs><circle cx="100" cy="100" r="2" fill="url(%23stars)"/><circle cx="300" cy="200" r="1.5" fill="url(%23stars)"/><circle cx="500" cy="150" r="1" fill="url(%23stars)"/><circle cx="700" cy="300" r="2" fill="url(%23stars)"/><circle cx="900" cy="250" r="1.5" fill="url(%23stars)"/><circle cx="1100" cy="400" r="1" fill="url(%23stars)"/></svg>') repeat;
            opacity: 0.6;
            animation: twinkle 3s ease-in-out infinite alternate;
            z-index: 0;
            pointer-events: none;
        }
        @keyframes twinkle {
            0% { opacity: 0.6; }
            100% { opacity: 1; }
        }
        /* Page Header */
        .page-header {
            padding: 100px 1rem 40px;
            text-align: center;
            background: rgba(0, 0, 0, 0.3);
            position: relative;
        }
        
        .page-header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(to right, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 20px rgba(255, 215, 0, 0.2);
        }
        
        .page-header p {
            font-size: 1rem;
            max-width: 600px;
            margin: 0 auto;
            color: var(--text-muted);
        }
        
        /* Leaderboard Section */
        .leaderboard-section {
            padding: 2rem 1rem;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .section-title {
            font-size: 1.5rem;
            text-align: center;
            margin-bottom: 1.5rem;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .tabs {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .tab-btn {
            background: var(--card-bg);
            border: 1px solid var(--glass-border);
            border-radius: 30px;
            padding: 0.7rem 1.5rem;
            color: var(--text-muted);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .tab-btn.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #1a1a1a;
            border-color: var(--secondary);
            font-weight: 600;
        }
        
        .tab-btn:not(.active):hover {
            background: rgba(255, 215, 0, 0.1);
            color: var(--primary);
        }
        
        .leaderboard-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            border: 1px solid var(--glass-border);
            position: relative;
            overflow: hidden;
            transition: var(--transition);
            margin-bottom: 2rem;
        }
        
        .leaderboard-header {
            display: grid;
            grid-template-columns: 50px 1fr 150px 150px;
            padding: 1rem;
            background: rgba(0, 0, 0, 0.3);
            font-weight: 600;
            color: var(--primary);
            border-bottom: 1px solid rgba(255, 215, 0, 0.1);
        }
        
        .leaderboard-list {
            max-height: 600px;
            overflow-y: auto;
        }
        
        .leaderboard-list::-webkit-scrollbar {
            width: 6px;
        }
        
        .leaderboard-list::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 4px;
        }
        
        .leaderboard-list::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }
        
        .leaderboard-item {
            display: grid;
            grid-template-columns: 50px 1fr 150px 150px;
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 215, 0, 0.05);
            transition: var(--transition);
        }
        
        .leaderboard-item:hover {
            background: rgba(255, 215, 0, 0.05);
        }
        
        .leaderboard-rank {
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .rank-1, .rank-2, .rank-3 {
            position: relative;
        }
        
        .rank-1::before {
            content: "🥇";
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.5rem;
        }
        
        .rank-2::before {
            content: "🥈";
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.5rem;
        }
        
        .rank-3::before {
            content: "🥉";
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.5rem;
        }
        
        .rank-1 .rank-number,
        .rank-2 .rank-number,
        .rank-3 .rank-number {
            margin-left: 25px;
        }
        
        .leaderboard-user {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            overflow: hidden;
        }
        
        .user-name {
            font-weight: 500;
        }
        
        .user-stats {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .stat-value {
            font-weight: 600;
            color: var(--primary);
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: var(--text-muted);
        }
        
        .load-more {
            display: block;
            width: 100%;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border);
            border-radius: 50px;
            padding: 0.8rem;
            color: var(--primary);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 1rem;
            text-align: center;
        }
        
        .load-more:hover {
            background: rgba(255, 215, 0, 0.1);
            transform: translateY(-3px);
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease forwards;
        }
        
        /* Highlight current user */
        .leaderboard-item.current-user {
            background: rgba(255, 215, 0, 0.1);
            border-left: 3px solid var(--primary);
        }
        
        .you-badge {
            background: var(--primary);
            color: #1a1a1a;
            font-size: 0.7rem;
            font-weight: bold;
            padding: 0.15rem 0.5rem;
            border-radius: 4px;
            margin-left: 0.5rem;
        }

        /* Desktop Styles */
        @media (min-width: 768px) {
            
            .page-header {
                padding: 120px 2rem 60px;
            }
            
            .page-header h1 {
                font-size: 3.5rem;
            }
            
            .page-header p {
                font-size: 1.1rem;
            }
            
            .leaderboard-section {
                padding: 2rem;
            }
        }
        
        @media (max-width: 600px) {
            .leaderboard-header {
                grid-template-columns: 40px 1fr 100px;
            }
            
            .leaderboard-item {
                grid-template-columns: 40px 1fr 100px;
            }
            
            .leaderboard-header div:nth-child(4),
            .leaderboard-item div:nth-child(4) {
                display: none;
            }
        }
    </style>
</head>
<body>
     <!-- Top Navigation -->
    <nav class="top-nav">
        <div class="logo">
            <div class="coin-logo"></div>
            <span>RAWR</span>
        </div>
        
        <div class="nav-actions">
            <div class="wallet-balance">
                <div class="balance-item">
                    <i class="fas fa-coins balance-icon"></i>
                    <span class="balance-label">RAWR:</span>
                    <span class="balance-value"><?= number_format($currentUser['rawr_balance'], 2) ?></span>
                </div>
                <div class="balance-item">
                    <i class="fas fa-ticket-alt balance-icon"></i>
                    <span class="balance-label">Tickets:</span>
                    <span class="balance-value"><?= number_format($currentUser['ticket_balance'], 2) ?></span>
                </div>
            </div>
            <button class="menu-toggle" id="menuToggle">
                <i class="fas fa-bars"></i>
                <i class="fas fa-xmark"></i>
            </button>
        </div>
    </nav>

    <!-- Sidebar Navigation -->
    <aside class="sidebar" id="sidebar">
        <a href="dashboard.php" class="sidebar-item">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        <a href="mining.php" class="sidebar-item">
            <i class="fas fa-digging"></i>
            <span>Mining</span>
        </a>
        <a href="games.php" class="sidebar-item">
            <i class="fas fa-dice"></i>
            <span>Casino</span>
        </a>
        <a href="wallet.php" class="sidebar-item">
            <i class="fas fa-wallet"></i>
            <span>Wallet</span>
        </a>
        <a href="leaderboard.php" class="sidebar-item active">
            <i class="fas fa-trophy"></i>
            <span>Leaderboard</span>
        </a>
        <a href="daily.php" class="sidebar-item">
            <i class="fas fa-gift"></i>
            <span>Daily Rewards</span>
        </a>
        <a href="profile.php" class="sidebar-item">
            <i class="fas fa-user"></i>
            <span>Profile</span>
        </a>
        <a href="logout.php" class="sidebar-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </aside>

    <!-- Page Header -->
    <section class="page-header">
        <h1>RAWR Leaderboard</h1>
        <p>See who rules the jungle with the most RAWR tokens and tickets!</p>
    </section>
    
    <!-- Leaderboard Section -->
    <section class="leaderboard-section">
        <h2 class="section-title">
            <i class="fas fa-crown"></i>
            Top Jungle Players
        </h2>
        
        <div class="tabs">
            <button class="tab-btn active" data-tab="tokens">RAWR Tokens</button>
            <button class="tab-btn" data-tab="tickets">Tickets</button>
        </div>
        
        <div class="leaderboard-card">
            <div class="leaderboard-header">
                <div>Rank</div>
                <div>Player</div>
                <div>RAWR Tokens</div>
                <div>Tickets</div>
            </div>
            
            <div class="leaderboard-list" id="leaderboardList">
                <!-- Top players will be generated here -->
            </div>
        </div>
        
        <button class="load-more" id="loadMoreBtn">
            <i class="fas fa-chevron-down"></i> Show More Players
        </button>
    </section>
    
    <!-- Footer -->
    <footer>
        <div class="footer-content">
            <div class="footer-column">
                <h3>RAWR Casino</h3>
                <p>The ultimate play-to-earn experience in the jungle. Play, win, and earn your way to the top!</p>
                <div class="social-links">
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-discord"></i></a>
                    <a href="#"><i class="fab fa-telegram"></i></a>
                    <a href="#"><i class="fab fa-reddit"></i></a>
                </div>
            </div>
            
            <div class="footer-column">
                <h3>Quick Links</h3>
                <ul class="footer-links">
                    <li><a href="dashboard.php">Home</a></li>
                    <li><a href="mining.php">Mining</a></li>
                    <li><a href="games.php">Casino</a></li>
                    <li><a href="leaderboard.php">Leaderboard</a></li>
                    <li><a href="wallet.php">Wallet</a></li>
                </ul>
            </div>
            
            <div class="footer-column">
                <h3>Resources</h3>
                <ul class="footer-links">
                    <li><a href="#">FAQs</a></li>
                    <li><a href="#">Tutorials</a></li>
                    <li><a href="#">Whitepaper</a></li>
                    <li><a href="#">Tokenomics</a></li>
                    <li><a href="#">Support</a></li>
                </ul>
            </div>
            
            <div class="footer-column">
                <h3>Legal</h3>
                <ul class="footer-links">
                    <li><a href="#">Terms of Service</a></li>
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Disclaimer</a></li>
                    <li><a href="#">AML Policy</a></li>
                </ul>
            </div>
        </div>
        
        <div class="copyright">
            &copy; 2023 RAWR Casino. All rights reserved. The jungle is yours to conquer!
        </div>
    </footer>
    
    <!-- Leaderboard background design -->
    <div class="leaderboard-bg"></div>
    
    <script>
        // Menu Toggle for Mobile with X icon
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        menuToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('active');
            menuToggle.classList.toggle('active');
        });
        // Close sidebar when clicking outside
        document.addEventListener('click', (e) => {
            if (sidebar.classList.contains('active') && 
                !sidebar.contains(e.target) && 
                !menuToggle.contains(e.target)) {
                sidebar.classList.remove('active');
                menuToggle.classList.remove('active');
            }
        });

        // Tab functionality
        const tabBtns = document.querySelectorAll('.tab-btn');
        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                // Remove active class from all buttons
                tabBtns.forEach(b => b.classList.remove('active'));
                // Add active class to clicked button
                btn.classList.add('active');
                
                // Sort leaderboard based on selected tab
                sortLeaderboard(btn.dataset.tab);
            });
        });
        
        const playerData = {
            tokens: <?= json_encode($tokenLeaderboard) ?>,
            tickets: <?= json_encode($ticketLeaderboard) ?>
        };

        const emojiList = ['🦁', '🐯', '🐅', '🐆', '🐘', '🦏', '🦒', '🦓', '🦌', '🐃', 
                        '🐂', '🐄', '🐎', '🐖', '🐏', '🐐', '🦙', '🦘', '🦥', '🦨', 
                        '🦡', '🐿️', '🦔', '🐇', '🦃', '🦚', '🦜', '🦢', '🦩', '🦝'];
                        
        // Current user ID for highlighting
        const currentUserId = <?= $currentUser['id'] ?>;

        // Process player data
        playerData.tokens = playerData.tokens.map(player => ({
            ...player,
            avatar: emojiList[player.id % emojiList.length],
            tokens: parseFloat(player.tokens),
            tickets: parseInt(player.tickets)
        }));

        playerData.tickets = playerData.tickets.map(player => ({
            ...player,
            avatar: emojiList[player.id % emojiList.length],
            tokens: parseFloat(player.tokens),
            tickets: parseInt(player.tickets)
        }));

        let currentSort = 'tokens';
        let displayedPlayers = 15;
        let currentData = playerData.tokens;

        function sortLeaderboard(sortType) {
            currentSort = sortType;
            currentData = playerData[sortType];
            renderLeaderboard();
        }

        function renderLeaderboard() {
            const leaderboardList = document.getElementById('leaderboardList');
            leaderboardList.innerHTML = '';
            for (let i = 0; i < Math.min(displayedPlayers, currentData.length); i++) {
                const player = currentData[i];
                const rank = i + 1;
                const playerEl = document.createElement('div');
                playerEl.classList.add('leaderboard-item', 'fade-in');
                playerEl.style.animationDelay = `${i * 0.05}s`;
                
                // Add current-user class if it's the logged-in user
                if (parseInt(player.id) === currentUserId) {
                    playerEl.classList.add('current-user');
                }
                
                let rankClass = '';
                if (rank === 1) rankClass = 'rank-1';
                else if (rank === 2) rankClass = 'rank-2';
                else if (rank === 3) rankClass = 'rank-3';
                
                // Add "You" badge to username if it's the current user
                const username = player.username + (parseInt(player.id) === currentUserId ? 
                    ' <span class="you-badge">YOU</span>' : '');
                
                playerEl.innerHTML = `
                    <div class="leaderboard-rank ${rankClass}">
                        <span class="rank-number">${rank}</span>
                    </div>
                    <div class="leaderboard-user">
                        <div class="user-avatar">${player.avatar}</div>
                        <div class="user-name">${username}</div>
                    </div>
                    <div class="user-stats">
                        <div class="stat-value">${player.tokens.toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        })}</div>
                        <div class="stat-label">RAWR</div>
                    </div>
                    <div class="user-stats">
                        <div class="stat-value">${player.tickets.toLocaleString()}</div>
                        <div class="stat-label">Tickets</div>
                    </div>
                `;
                leaderboardList.appendChild(playerEl);
            }
        }
        
        document.getElementById('loadMoreBtn').addEventListener('click', () => {
            displayedPlayers += 15;
            if (displayedPlayers >= currentData.length) {
                document.getElementById('loadMoreBtn').textContent = "All Players Loaded";
                document.getElementById('loadMoreBtn').disabled = true;
            }
            renderLeaderboard();
        });
        
        // Initial render
        sortLeaderboard('tokens');
    </script>
</body>
</html>