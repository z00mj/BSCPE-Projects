<?php
require_once __DIR__ . '/../backend/inc/init.php';
userOnly();

$userId = $_SESSION['user_id'];
$db = Database::getInstance();
$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RAWR Casino - Game Lobby</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/RAWR/public/css/style.css">
    <style>
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
        
        /* Featured Games Section */
        .featured-games {
            padding: 2rem 1rem;
            max-width: 1200px;
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
        
        .games-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }
        
        .game-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            border: 1px solid var(--glass-border);
            position: relative;
            overflow: hidden;
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .game-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3), var(--glow);
            border-color: rgba(255, 215, 0, 0.3);
        }
        
        .game-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.2rem;
        }
        
        .game-icon {
            width: 60px;
            height: 60px;
            background: rgba(255, 215, 0, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: var(--primary);
            flex-shrink: 0;
        }
        
        .game-info {
            flex: 1;
        }
        
        .game-title {
            font-weight: 600;
            font-size: 1.2rem;
            color: var(--text-light);
            margin-bottom: 0.2rem;
        }
        
        .game-tag {
            display: inline-block;
            padding: 0.25rem 0.6rem;
            background: rgba(255, 107, 53, 0.2);
            color: var(--accent);
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        .game-description {
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            color: var(--text-muted);
            line-height: 1.6;
            flex: 1;
        }
        
        /* Game Previews */
        .game-preview {
            height: 160px;
            background: rgba(0, 0, 0, 0.4);
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background-size: cover;
            background-position: center;
        }
        
        .preview-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.9rem;
            text-align: center;
            padding: 1rem;
            opacity: 0;
            transition: var(--transition);
            backdrop-filter: blur(2px);
        }
        
        .game-preview:hover .preview-overlay {
            opacity: 1;
        }
        
        /* Remove old gradient classes */
        /* .slot-preview, .roulette-preview, etc. have been removed */
        
.play-btn {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #1a1a1a;
    border: none;
    border-radius: 50px;
    padding: 0.8rem;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
    width: 100%;
    margin-top: auto;
    text-decoration: none; /* Remove underline */
}
        
        .play-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(255, 215, 0, 0.4);
        }

        /* Coming Soon Section */
.coming-soon {
    padding: 2rem 1rem;
    max-width: 1200px;
    margin: 0 auto;
}

        /* Coming Soon Section */
.coming-soon {
    padding: 2rem 1rem;
    max-width: 1200px;
    margin: 0 auto;
}

/* Coming Soon Button */
.coming-soon-btn {
    background: rgba(100, 100, 100, 0.4);
    color: var(--text-muted);
    border: none;
    border-radius: 50px;
    padding: 0.8rem;
    font-size: 1rem;
    font-weight: 600;
    cursor: not-allowed;
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    box-shadow: none;
    width: 100%;
    margin-top: auto;
    border: 1px solid rgba(255,255,255,0.1);
}
        
        /* Animations */
        @keyframes coin-pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.2); opacity: 0.8; }
            100% { transform: scale(1); opacity: 1; }
        }
        
        .pulse {
            animation: coin-pulse 1.5s infinite;
        }
        
        /* Tablet Styles */
        @media (min-width: 576px) {
            .page-header {
                padding: 100px 1rem 50px;
            }
            
            .page-header h1 {
                font-size: 3rem;
            }
            
            .games-grid {
                grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            }
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
                    <span class="balance-value"><?= number_format($user['rawr_balance'], 2) ?></span>
                </div>
                <div class="balance-item">
                    <i class="fas fa-ticket-alt balance-icon"></i>
                    <span class="balance-label">Tickets:</span>
                    <span class="balance-value"><?= number_format($user['ticket_balance'], 2) ?></span>
                </div>
            </div>
            <button class="menu-toggle" id="menuToggle">
                <i class="fas fa-bars"></i>
                <i class="fas fa-xmark"></i>
            </button>
        </div>
    </nav>
    
    <!-- Updated Sidebar Navigation -->
    <aside class="sidebar" id="sidebar">
        <a href="dashboard.php" class="sidebar-item">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        <a href="mining.php" class="sidebar-item">
            <i class="fas fa-digging"></i>
            <span>Mining</span>
        </a>
        <a href="games.php" class="sidebar-item active">
            <i class="fas fa-dice"></i>
            <span>Casino</span>
        </a>
        <a href="wallet.php" class="sidebar-item">
            <i class="fas fa-wallet"></i>
            <span>Wallet</span>
        </a>
        <a href="leaderboard.php" class="sidebar-item">
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
        <h1>RAWR Casino Games</h1>
        <p>Step into the jungle casino and try your luck at our exotic games</p>
    </section>
    
    <!-- Featured Games Section -->
    <section class="featured-games">
        <h2 class="section-title">
            <i class="fas fa-star"></i>
            Featured Casino Games
        </h2>
        
        <div class="games-grid">
            <!-- Slot Machine -->
            <div class="game-card">
                <div class="game-preview" style="background-image: url('/RAWR/public/assets/slot-image.png');">
                    <div class="preview-overlay">Spin the reels and match symbols for massive ticket payouts!</div>
                </div>
                <div class="game-header">
                    <div class="game-icon">
                        <i class="fas fa-dice"></i>
                    </div>
                    <div class="game-info">
                        <h3 class="game-title">Jungle Slots</h3>
                        <span class="game-tag">Popular</span>
                    </div>
                </div>
                <div class="game-description">
                    Spin the reels filled with jungle animals. Match 3 lions for the jackpot, or other combos for big ticket wins!
                </div>
                <a href="games/jungleSlots.php" class="play-btn">
                    <i class="fas fa-play"></i>
                    Play Now
                </a>
            </div>
            
            <!-- Safari Roulette -->
            <div class="game-card">
                <div class="game-preview" style="background-image: url('/RAWR/public/assets/roulette-image.png');">
                    <div class="preview-overlay">Spin the wheel for multipliers or free spins. Bet tickets, win tickets!</div>
                </div>
                <div class="game-header">
                    <div class="game-icon">
                        <i class="fas fa-circle"></i>
                    </div>
                    <div class="game-info">
                        <h3 class="game-title">Safari Roulette</h3>
                        <span class="game-tag">Classic</span>
                    </div>
                </div>
                <div class="game-description">
                    Spin the Safari wheel for a chance at multipliers up to 20x or free spins. Lose your bet or win big—every spin is a thrill!
                </div>
                <a href="games/safariRoulette.php" class="play-btn">
                    <i class="fas fa-play"></i>
                    Play Now
                </a>
            </div>
            
            <!-- Finding Simba -->
            <div class="game-card">
                <div class="game-preview" style="background-image: url('/RAWR/public/assets/finding-simba.png');">
                    <div class="preview-overlay">Find Simba among the cards and win 3x your bet!</div>
                </div>
                <div class="game-header">
                    <div class="game-icon">
                        <i class="fas fa-clover"></i>
                    </div>
                    <div class="game-info">
                        <h3 class="game-title">Finding Simba</h3>
                        <span class="game-tag">Skill Game</span>
                    </div>
                </div>
                <div class="game-description">
                    Place your bet, shuffle the cards, and pick one. Find Simba to win 3x your ticket bet—miss and lose your bet!
                </div>
                <a href="games/findingsimba.php" class="play-btn">
                    <i class="fas fa-play"></i>
                    Play Now
                </a>
            </div>
            
            <!-- Dice of Beast -->
            <div class="game-card">
                <div class="game-preview" style="background-image: url('/RAWR/public/assets/dob-image.png');">
                    <div class="preview-overlay">Pick Lion, Tiger, or Wolf and roll the dice for up to 5x payout!</div>
                </div>
                <div class="game-header">
                    <div class="game-icon">
                        <i class="fas fa-dice"></i>
                    </div>
                    <div class="game-info">
                        <h3 class="game-title">Dice of Beast</h3>
                        <span class="game-tag">Fast</span>
                    </div>
                </div>
                <div class="game-description">
                    Choose your beast: Lion (5x), Tiger (3x), or Wolf (2x). Roll the dice—if your beast lands, win the multiplier!
                </div>
                <a href="games/diceofBeast.php" class="play-btn">
                    <i class="fas fa-play"></i>
                    Play Now
                </a>
            </div>
            
            <!-- Lion's Prowl -->
            <div class="game-card">
                <div class="game-preview" style="background-image: url('/RAWR/public/assets/panthers-image.png');">
                    <div class="preview-overlay">Uncover treasures, multipliers, or lions. Collect winnings before you get caught!</div>
                </div>
                <div class="game-header">
                    <div class="game-icon">
                        <i class="fas fa-paw"></i>
                    </div>
                    <div class="game-info">
                        <h3 class="game-title">Lion's Prowl</h3>
                        <span class="game-tag">Adventure</span>
                    </div>
                </div>
                <div class="game-description">
                    Bet tickets and uncover tiles for multipliers or RAWR prizes. Avoid lions or lose your bet—collect winnings anytime!
                </div>
                <a href="games/lionsPrawl.php" class="play-btn">
                    <i class="fas fa-play"></i>
                    Play Now
                </a>
            </div>
        </div>
    </section>

   <!-- Add after the featured-games section -->
<section class="coming-soon">
    <h2 class="section-title">
        <i class="fas fa-hourglass-half"></i>
        Coming Soon to the Jungle
    </h2>
    
    <div class="games-grid">
        <!-- Tiger's Roar -->
        <div class="game-card">
            <div class="game-preview tiger-preview">
                <div class="preview-overlay">Test your poker skills against jungle predators in this high-stakes card game!</div>
                <i class="fas fa-paw" style="font-size: 3rem; color: white;"></i>
            </div>
            <div class="game-header">
                <div class="game-icon">
                    <i class="fas fa-paw"></i>
                </div>
                <div class="game-info">
                    <h3 class="game-title">Tiger's Roar</h3>
                    <span class="game-tag">Coming Soon</span>
                </div>
            </div>
            <div class="game-description">
                Unleash the tiger's fury in this high-stakes jungle poker game. Compete against other predators for massive RAWR pots!
            </div>
            <button class="coming-soon-btn">
                <i class="fas fa-clock"></i>
                Coming Soon
            </button>
        </div>
        
        <!-- Monkey Mayhem -->
        <div class="game-card">
            <div class="game-preview monkey-preview">
                <div class="preview-overlay">Swing through jungle canopies collecting bananas while avoiding obstacles!</div>
                <i class="fas fa-bolt" style="font-size: 3rem; color: white;"></i>
            </div>
            <div class="game-header">
                <div class="game-icon">
                    <i class="fas fa-bolt"></i>
                </div>
                <div class="game-info">
                    <h3 class="game-title">Monkey Mayhem</h3>
                    <span class="game-tag">Coming Soon</span>
                </div>
            </div>
            <div class="game-description">
                Swing through the jungle canopy collecting bananas while avoiding obstacles. Each banana collected earns you RAWR tokens!
            </div>
            <button class="coming-soon-btn">
                <i class="fas fa-clock"></i>
                Coming Soon
            </button>
        </div>
    </div>
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
    
    <script>
    const menuToggle = document.getElementById('menuToggle');
          // Menu Toggle for Mobile with X icon
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
        
        // Game card animations
        const gameCards = document.querySelectorAll('.game-card');
        gameCards.forEach(card => {
            card.addEventListener('mouseenter', () => {
                card.style.transform = 'translateY(-10px)';
                card.style.boxShadow = '0 15px 30px rgba(0, 0, 0, 0.4), 0 0 20px rgba(255, 215, 0, 0.4)';
            });
            
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'translateY(0)';
                card.style.boxShadow = '0 8px 25px rgba(0, 0, 0, 0.2)';
            });
        });
        
        // Play button animations
        const playButtons = document.querySelectorAll('.play-btn');
        playButtons.forEach(button => {
            button.addEventListener('mouseenter', () => {
                button.style.transform = 'translateY(-5px) scale(1.02)';
            });
            
            button.addEventListener('mouseleave', () => {
                button.style.transform = 'translateY(0) scale(1)';
            });
            
            button.addEventListener('click', function() {
                // Pulse animation on click
                this.classList.add('pulse');
                setTimeout(() => {
                    this.classList.remove('pulse');
                }, 500);
                
                // Get game title
                const gameTitle = this.closest('.game-card').querySelector('.game-title').textContent;
                
                // Create notification
                const notification = document.createElement('div');
                notification.style.position = 'fixed';
                notification.style.bottom = '20px';
                notification.style.right = '20px';
                notification.style.backgroundColor = 'rgba(30, 30, 30, 0.9)';
                notification.style.color = 'var(--primary)';
                notification.style.padding = '15px 25px';
                notification.style.borderRadius = '8px';
                notification.style.border = '1px solid var(--primary)';
                notification.style.boxShadow = '0 5px 15px rgba(0, 0, 0, 0.3)';
                notification.style.zIndex = '1000';
                notification.style.transition = 'transform 0.3s ease';
                notification.style.transform = 'translateX(120%)';
                notification.innerHTML = `<i class="fas fa-play-circle"></i> Launching ${gameTitle}...`;
                
                document.body.appendChild(notification);
                
                // Animate in
                setTimeout(() => {
                    notification.style.transform = 'translateX(0)';
                }, 10);
                
                // Remove after 3 seconds
                setTimeout(() => {
                    notification.style.transform = 'translateX(120%)';
                    setTimeout(() => {
                        notification.remove();
                    }, 300);
                }, 3000);
            });
        });
    </script>
</body>
</html>