<?php
require_once __DIR__ . '/../../backend/inc/init.php';
userOnly();

$db = Database::getInstance();
$user_id = $_SESSION['user_id'];
$user = $db->fetchOne('SELECT ticket_balance, rawr_balance FROM users WHERE id = ?', [$user_id]);
$ticketBalance = $user ? (int)$user['ticket_balance'] : 0;
$rawrBalance = $user ? (float)$user['rawr_balance'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finding Simba - RAWR Casino</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #FFD700;
            --primary-light: #FFDF40;
            --secondary: #FFA500;
            --accent: #FF6B35;
            --dark-bg: #0d0d0d;
            --dark-bg-gradient: linear-gradient(135deg, #1a1a1a 0%, #2d1810 100%);
            --card-bg: rgba(30, 30, 30, 0.6);
            --text-light: #f0f0f0;
            --text-muted: #ccc;
            --border-radius: 12px;
            --transition: all 0.3s ease;
            --glass-bg: rgba(40, 40, 40, 0.25);
            --glass-border: rgba(255, 215, 0, 0.1);
            --glow: 0 0 15px rgba(255, 215, 0, 0.3);
            --success: #28a745;
            --danger: #dc3545;
            --info: #3b82f6;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--dark-bg-gradient);
            color: var(--text-light);
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
        }

        body::before {
            content: ""; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><path d="M20,20 Q40,5 60,20 T100,20 Q85,40 100,60 T100,100 Q60,85 20,100 T0,100 Q5,60 0,20 T20,20 Z" fill="none" stroke="rgba(255,215,0,0.05)" stroke-width="0.5"/></svg>');
            background-size: 300px; opacity: 0.3; z-index: -1;
        }

        .glass-card {
            background: var(--glass-bg); backdrop-filter: blur(10px); border: 1px solid var(--glass-border);
            border-radius: var(--border-radius); padding: 2rem; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        }

        /* --- Close Button --- */
        .close-btn {
            position: fixed; 
            top: 20px; 
            right: 20px; 
            background: rgba(255, 215, 0, 0.1); 
            border: 1px solid var(--glass-border); 
            color: var(--primary); 
            width: 40px; 
            height: 40px; 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 1.5rem; 
            cursor: pointer; 
            z-index: 101;
            transition: var(--transition);
        }
        
        .close-btn:hover {
            background: rgba(255,215,0,0.2);
            transform: scale(1.1);
        }

        /* --- Main Game Layout --- */
        .game-wrapper { padding: 100px 2rem 80px; max-width: 1400px; margin: 0 auto; }
        .game-grid { display: grid; grid-template-columns: 1fr 400px; gap: 2rem; align-items: flex-start; }
        
        /* Left Column */
        .game-title-section { text-align: center; margin-bottom: 2rem; }
        .game-title-section h1 { font-size: 3rem; background: linear-gradient(to right, var(--primary), var(--secondary)); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; text-shadow: 0 0 20px rgba(255, 215, 0, 0.2); }
        .game-title-section p { color: var(--text-muted); font-size: 1.2rem; }

        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { text-align: center; padding: 1.5rem; }
        .stat-label { font-size: 1rem; color: var(--text-muted); margin-bottom: 0.5rem; }
        .stat-value { font-size: 1.8rem; font-weight: 700; color: var(--primary-light); }
        .stat-icon { margin-right: 0.5rem; }

        /* Cards container */
        .cards-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 3.5rem; /* Increased gap for more space between cards */
            margin: 0 auto;
            max-width: 800px;
            perspective: 1500px;
            position: relative;
            height: 300px;
        }
        .card {
            width: 200px;
            height: 280px;
            position: relative; /* Changed from absolute to relative for flex gap */
            cursor: pointer;
            transform-style: preserve-3d;
            transition: all 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
            will-change: transform, left;
        }
        .card.flipped {
            transform: rotateY(180deg);
        }
        .card.shuffling {
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            z-index: 10;
        }
        .card-face {
            position: absolute;
            width: 100%;
            height: 100%;
            backface-visibility: hidden;
            border-radius: 16px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }
        .card-back {
            background: linear-gradient(135deg, #1a1a1a, #2d1810);
            border: 3px solid var(--primary);
            box-shadow: inset 0 0 30px rgba(0, 0, 0, 0.7), 0 0 20px rgba(255, 215, 0, 0.3);
        }
        .card-back::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at center, var(--primary) 0%, transparent 70%),
                repeating-linear-gradient(45deg, transparent, transparent 5px, rgba(255, 215, 0, 0.1) 5px, rgba(255, 215, 0, 0.1) 10px);
            opacity: 0.3;
        }
        .card-back-content {
            position: relative;
            z-index: 2;
            text-align: center;
            padding: 20px;
        }
        .card-back-logo {
            font-size: 4rem;
            color: var(--primary);
            text-shadow: 0 0 15px rgba(255, 215, 0, 0.8);
            margin-bottom: 10px;
        }
        .card-back-text {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .card-front {
            background: linear-gradient(135deg, #2a1e1c, #3a2b28);
            transform: rotateY(180deg);
            border: 3px solid var(--primary);
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 6rem;
        }
        .card.simba .card-front {
            background: radial-gradient(circle, #ffd700, #ff6b35);
            animation: glow 1.5s infinite alternate;
        }
        @keyframes glow {
            from { box-shadow: 0 0 20px rgba(255, 215, 0, 0.5); }
            to { box-shadow: 0 0 40px rgba(255, 107, 53, 0.8); }
        }
        
        /* Right Column */
        .controls-column { display: flex; flex-direction: column; gap: 2rem; position: sticky; top: 120px; }
        .bet-label { font-size: 1.2rem; font-weight: 600; text-align: center; margin-bottom: 1rem; color: var(--primary); }
        .bet-input { font-family: 'Poppins', sans-serif; font-size: 2rem; font-weight: 700; color: var(--primary); background: rgba(0,0,0,0.4); border: 2px solid var(--glass-border); border-radius: 8px; text-align: center; width: 100%; padding: 1rem; outline: none; }
        .quick-bet-buttons { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; margin-top: 1rem; }
        .quick-bet-btn { background: rgba(255, 215, 0, 0.1); border: 1px solid var(--glass-border); color: var(--primary); padding: 0.75rem; border-radius: 8px; cursor: pointer; transition: background 0.2s; }
        .quick-bet-btn:hover { background: rgba(255, 215, 0, 0.2); }
        .spin-buttons { display: grid; align-items: grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1.5rem; }
        .spin-btn {
            border: none;
            padding: 1rem;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #1a1a1a;
            min-width: 335px;
            font-size: 1.3rem;
            padding: 1.2rem 0;
        }
        .spin-btn:disabled, .auto-spin-btn:disabled { background: #555 !important; border-color: #555 !important; color: #999 !important; cursor: not-allowed; animation: none; }
        
        /* History */
        .history-card .history-title { font-size: 1.5rem; text-align: center; color: var(--primary); margin-bottom: 1rem; }
        .history-items {
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
            max-height: calc(2 * 60px + 1.6rem); /* 2 items + gap */
            overflow-y: auto;
            padding-right: 4px;
            box-sizing: border-box;
        }
        .history-item {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            padding: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 60px;
            font-size: 1rem;
        }
        .history-icon { font-size: 1.4rem; min-width: 35px; text-align: center; }
        .history-icon .fa-coins { color: var(--success); }
        .history-icon .fa-times-circle { color: var(--danger); }
        .history-win { color: var(--success); }
        .history-loss { color: var(--danger); }
        .history-spins { color: var(--info); }
        .history-details { flex-grow: 1; }
        .history-outcome { font-weight: 600; font-size: 1rem; margin-bottom: 3px; }
        .history-outcome.history-win { color: #fff; }
        .history-outcome.history-loss { color: #fff; }
        .history-value { font-weight: 500; color: var(--text-light); font-size: 0.9rem; }
        .history-amount { font-weight: 600; font-size: 1.1rem; min-width: 70px; text-align: right; }
        .history-amount.win { color: var(--success); }
        .history-amount.loss { color: var(--danger); }
        .history-amount.spins { color: var(--info); }

        /* --- Themed Win Modal --- */
        .win-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); display: none; align-items: center; justify-content: center; z-index: 1001; opacity: 0; transition: opacity 0.3s; backdrop-filter: blur(5px); }
        .win-modal-overlay.active { display: flex; opacity: 1; }
        .win-modal { background: var(--dark-bg-gradient); border: 2px solid var(--primary); border-radius: var(--border-radius); padding: 3rem; position: relative; overflow: hidden; text-align: center; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5), var(--glow); width: 90%; max-width: 500px; }
        .win-modal::before { content: ""; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(255, 215, 0, 0.15) 0%, transparent 40%); animation: rotateGlow 10s linear infinite; }
        @keyframes rotateGlow { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .win-modal h2 { font-size: 3rem; margin-bottom: 0.5rem; font-weight: 700; background: linear-gradient(to right, var(--primary), var(--secondary)); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; text-shadow: 0 0 15px rgba(255, 215, 0, 0.3); }
        .win-modal p { font-size: 1.5rem; font-weight: 600; color: var(--text-light); margin-top: 1rem; }
        .win-modal.loss h2 { background: none; -webkit-text-fill-color: var(--danger); text-shadow: 0 0 15px var(--danger); }
        .win-modal .close-btn { position: absolute; top: 1rem; right: 1rem; background: rgba(0,0,0,0.3); border: 1px solid var(--glass-border); color: var(--text-muted); font-size: 1.2rem; cursor: pointer; width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: var(--transition); }
        .win-modal .close-btn:hover { background: rgba(255,215,0,0.1); color: var(--primary); transform: scale(1.1); }
        .confetti-container { position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; }
        .confetti { position: absolute; width: 10px; height: 10px; background: var(--primary); opacity: 0; animation: fall 4s linear forwards; }
        @keyframes fall { 0% { transform: translateY(-20px) rotateZ(0deg); opacity: 1; } 100% { transform: translateY(110vh) rotateZ(720deg); opacity: 0; } }

        /* Shuffle animation */
        @keyframes liftCard {
            0% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-50px) scale(1.05); }
            100% { transform: translateY(0) scale(1); }
        }

        /* --- Responsive Styles --- */
        @media (max-width: 1200px) { 
            .game-grid { grid-template-columns: 1fr; } 
            .controls-column { position: static; max-width: 600px; margin: 2rem auto 0; } 
        }
        @media (max-width: 900px) {
            .cards-container {
                gap: 1.5rem;
                height: auto;
                flex-wrap: wrap;
                position: relative;
                justify-content: center;
            }
            .card {
                position: relative;
                width: 180px;
                height: 250px;
                left: auto !important;
            }
        }
        @media (max-width: 768px) {
            .game-grid { gap: 1.5rem; }
            .game-title-section h1 { font-size: 2.5rem; }
            .stats-grid { grid-template-columns: repeat(3, 1fr); gap: 1rem; }
            .stat-value { font-size: 1.5rem; }
            .card {
                width: 150px;
                height: 210px;
            }
            .card-back-logo {
                font-size: 3rem;
            }
            .card-back-text {
                font-size: 1.2rem;
            }
        }
        @media (max-width: 600px) {
            .spin-btn {
                min-width: 0;
                width: 100%;
                font-size: 1.1rem;
                padding: 1rem 0.5rem;
            }
        }
        @media (max-width: 480px) {
            .game-wrapper { padding-left: 1rem; padding-right: 1rem; }
            .game-title-section h1 { font-size: 2rem; }
            .stat-value { font-size: 1.2rem; }
            .cards-container { 
                gap: 0.8rem;
            }
            .card {
                width: 120px;
                height: 170px;
            }
            .card-back-logo {
                font-size: 2.5rem;
            }
            .card-back-text {
                font-size: 0.9rem;
            }
        }

        /* Add to CSS (in <style>): */
        .cards-container.shuffling-mode {
            position: relative;
            min-height: 300px;
        }
        .cards-container.shuffling-mode .card {
            pointer-events: none;
        }
    </style>
</head>
<body>
    <button class="close-btn" id="closeBtn">×</button>

    <main class="game-wrapper">
        <div class="game-grid">
            <div class="game-main-content">
                <div class="game-title-section">
                    <h1>Finding Simba</h1>
                    <p>Find Simba hidden in one of the cards to win 3x your bet!</p>
                </div>
                <div class="stats-grid">
                    <div class="stat-card glass-card">
                        <div class="stat-label"><i class="fas fa-ticket-alt stat-icon"></i>Ticket Balance</div>
                        <div class="stat-value" id="balanceDisplay">0</div>
                    </div>
                    <div class="stat-card glass-card">
                        <div class="stat-label"><i class="fas fa-trophy stat-icon"></i>Last Win</div>
                        <div class="stat-value" id="lastWinDisplay">0</div>
                    </div>
                    <div class="stat-card glass-card">
                        <div class="stat-label"><i class="fas fa-coins stat-icon"></i>Current Bet</div>
                        <div class="stat-value" id="betDisplay">10</div>
                    </div>
                </div>
                <div class="cards-container" id="cardsContainer">
                    <div class="card" data-card="1">
                        <div class="card-face card-back">
                            <div class="card-back-content">
                                <div class="card-back-logo">🦁</div>
                                <div class="card-back-text">RAWR CASINO</div>
                            </div>
                        </div>
                        <div class="card-face card-front">
                            <span class="card-content">🚫</span>
                        </div>
                    </div>
                    <div class="card" data-card="2">
                        <div class="card-face card-back">
                            <div class="card-back-content">
                                <div class="card-back-logo">🦁</div>
                                <div class="card-back-text">RAWR CASINO</div>
                            </div>
                        </div>
                        <div class="card-face card-front">
                            <span class="card-content">🚫</span>
                        </div>
                    </div>
                    <div class="card" data-card="3">
                        <div class="card-face card-back">
                            <div class="card-back-content">
                                <div class="card-back-logo">🦁</div>
                                <div class="card-back-text">RAWR CASINO</div>
                            </div>
                        </div>
                        <div class="card-face card-front">
                            <span class="card-content">🚫</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="controls-column">
                <div class="controls-card glass-card">
                    <label for="betInput" class="bet-label">Bet Amount (Tickets)</label>
                    <div class="bet-input-container">
                        <input type="number" id="betInput" value="10" min="1" max="1000" class="bet-input">
                    </div>
                    <div class="quick-bet-buttons">
                        <button class="quick-bet-btn" data-bet="10">10</button>
                        <button class="quick-bet-btn" data-bet="25">25</button>
                        <button class="quick-bet-btn" data-bet="50">50</button>
                        <button class="quick-bet-btn" data-bet="100">100</button>
                        <button class="quick-bet-btn" data-bet="250">250</button>
                        <button class="quick-bet-btn" id="maxBetBtn">MAX</button>
                    </div>
                    <div class="spin-buttons">
                        <button class="spin-btn" id="playBtn">🔍 FIND SIMBA!</button>
                    </div>
                </div>
                <div class="history-card glass-card">
                    <h3 class="history-title">Game History</h3>
                    <div class="history-items" id="historyItems"></div>
                </div>
            </div>
        </div>
    </main>

    <div class="win-modal-overlay" id="winModalOverlay">
        <div class="win-modal" id="winModal">
            <div class="confetti-container" id="confettiContainer"></div>
            <button class="close-btn" id="winModalCloseBtn">×</button>
            <h2 id="winModalTitle"></h2>
            <p id="winModalAmount"></p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Injected from PHP
            let rawrBalance = <?php echo json_encode($rawrBalance); ?>;
            let ticketBalance = <?php echo json_encode($ticketBalance); ?>;

            class FindingSimba {
                constructor() {
                    // Game state
                    this.balance = rawrBalance;
                    this.ticketBalance = ticketBalance;
                    this.currentBet = 10;
                    this.isPlaying = false;
                    this.isShuffling = false;
                    this.simbaPosition = 0;
                    this.winAmount = 0;
                    this.history = [];
                    
                    // DOM elements
                    this.balanceDisplay = document.getElementById('balanceDisplay');
                    this.lastWinDisplay = document.getElementById('lastWinDisplay');
                    this.betDisplay = document.getElementById('betDisplay');
                    this.betInput = document.getElementById('betInput');
                    this.playBtn = document.getElementById('playBtn');
                    this.cardsContainer = document.getElementById('cardsContainer');
                    this.cards = Array.from(document.querySelectorAll('.card'));
                    this.historyItemsEl = document.getElementById('historyItems');
                    this.closeBtn = document.getElementById('closeBtn');
                    
                    // Initialize game
                    this.initEventListeners();
                    this.updateUI();
                }

                initEventListeners() {
                    // Quick bet buttons
                    document.querySelectorAll('.quick-bet-btn').forEach(btn => {
                        if(btn.id === 'maxBetBtn') {
                            btn.addEventListener('click', () => {
                                this.currentBet = this.ticketBalance;
                                this.updateBetInput();
                            });
                        } else {
                            btn.addEventListener('click', () => {
                                this.currentBet = parseInt(btn.dataset.bet);
                                this.updateBetInput();
                            });
                        }
                    });

                    // Bet input
                    this.betInput.addEventListener('input', () => {
                        let newBet = parseInt(this.betInput.value) || 1;
                        if(newBet < 1) newBet = 1;
                        if(newBet > this.ticketBalance) newBet = this.ticketBalance;
                        if(newBet > 1000) newBet = 1000;
                        this.currentBet = newBet;
                        this.updateBetInput();
                    });

                    // Play button
                    this.playBtn.addEventListener('click', () => {
                        if(!this.isPlaying && !this.isShuffling && this.ticketBalance >= this.currentBet) {
                            this.playGame();
                        }
                    });
                    
                    // Card click handlers
                    this.cards.forEach(card => {
                        card.addEventListener('click', () => {
                            if(this.isPlaying && !card.classList.contains('flipped')) {
                                this.checkCard(parseInt(card.dataset.card));
                            }
                        });
                    });
                    
                    // Win modal close button
                    document.getElementById('winModalCloseBtn').addEventListener('click', () => {
                        this.closeWinModal();
                    });
                    
                    // Close button
                    this.closeBtn.addEventListener('click', () => {
                        window.location.href = '/RAWR/public/games.php';
                    });
                }

                updateBetInput() {
                    this.betInput.value = this.currentBet;
                    this.betDisplay.textContent = this.currentBet;
                }

                playGame() {
                    // Reset cards
                    this.resetCards();
                    
                    // Deduct bet from balance
                    this.ticketBalance -= this.currentBet;
                    this.updateUI();
                    
                    // Randomly place Simba in one of the cards
                    this.simbaPosition = Math.floor(Math.random() * 3) + 1;
                    
                    // Shuffle cards animation
                    this.shuffleCards();
                }

                resetCards() {
                    this.cards.forEach(card => {
                        card.classList.remove('flipped', 'simba', 'shuffling');
                        const content = card.querySelector('.card-content');
                        content.textContent = '🚫';
                    });
                }

                shuffleCards() {
                    if (this.isShuffling) return;
                    this.isShuffling = true;
                    this.playBtn.disabled = true;
                    const container = this.cardsContainer;
                    const cards = this.cards;
                    container.classList.add('shuffling-mode');
                    // Get container and card sizes
                    const containerRect = container.getBoundingClientRect();
                    const cardWidth = cards[0].offsetWidth;
                    const gap = 56; // 3.5rem in px (1rem=16px)
                    const totalWidth = cardWidth * 3 + gap * 2;
                    const startX = (containerRect.width - totalWidth) / 2;
                    // Move all cards to center
                    const centerX = containerRect.width / 2 - cardWidth / 2;
                    cards.forEach(card => {
                        card.style.position = 'absolute';
                        card.style.left = `${centerX}px`;
                        card.style.top = '0px';
                        card.style.zIndex = 10;
                        card.style.transition = 'left 0.5s cubic-bezier(0.175,0.885,0.32,1.275)';
                    });
                    // Animate lift (bounce)
                    cards.forEach((card, idx) => {
                        setTimeout(() => {
                            card.classList.add('shuffling');
                            card.style.animation = 'liftCard 0.5s ease-in-out';
                            setTimeout(() => {
                                card.classList.remove('shuffling');
                                card.style.animation = '';
                            }, 500);
                        }, idx * 100);
                    });
                    // After bounce, spread cards back to left/center/right (evenly spaced)
                    setTimeout(() => {
                        for (let i = 0; i < 3; i++) {
                            const left = startX + i * (cardWidth + gap);
                            cards[i].style.left = `${left}px`;
                        }
                        // After spread, restore flex layout
                        setTimeout(() => {
                            cards.forEach(card => {
                                card.style.position = '';
                                card.style.left = '';
                                card.style.top = '';
                                card.style.zIndex = '';
                                card.style.transition = '';
                            });
                            container.classList.remove('shuffling-mode');
                            this.isShuffling = false;
                            this.isPlaying = true;
                            this.playBtn.disabled = this.ticketBalance < this.currentBet;
                        }, 600);
                    }, 800);
                }
                
                checkCard(cardNumber) {
                    if(!this.isPlaying) return;
                    this.isPlaying = false;
                    // Always remove and re-add 'flipped' to force reflow and animation
                    const selectedCard = document.querySelector(`.card[data-card="${cardNumber}"]`);
                    selectedCard.classList.remove('flipped');
                    // Force reflow to restart the animation
                    void selectedCard.offsetWidth;
                    selectedCard.classList.add('flipped');
                    const content = selectedCard.querySelector('.card-content');
                    if(cardNumber === this.simbaPosition) {
                        setTimeout(() => {
                            content.textContent = '🦁';
                            selectedCard.classList.add('simba');
                            this.winAmount = this.currentBet * 3; // 3x payout
                            this.balance += this.winAmount;
                            this.showWinModal('win', '🎉 YOU FOUND SIMBA!', `You won ${this.winAmount} Tickets!`);
                            this.addToHistory('win', this.winAmount);
                            this.updateServerBalance(this.currentBet, this.winAmount, true);
                            this.lastWinDisplay.textContent = `+${this.winAmount}`;
                            this.updateUI();
                        }, 500);
                    } else {
                        setTimeout(() => {
                            content.textContent = '🚫';
                            setTimeout(() => {
                                const simbaCard = document.querySelector(`.card[data-card="${this.simbaPosition}"]`);
                                simbaCard.classList.remove('flipped');
                                void simbaCard.offsetWidth;
                                simbaCard.classList.add('flipped', 'simba');
                                const simbaContent = simbaCard.querySelector('.card-content');
                                simbaContent.textContent = '🦁';
                                this.showWinModal('lose', '💥 TRY AGAIN', `Simba was in card ${this.simbaPosition}!`);
                                this.addToHistory('lose', 0);
                                this.updateServerBalance(this.currentBet, 0, false);
                                this.lastWinDisplay.textContent = '0';
                                this.updateUI();
                            }, 1000);
                            this.winAmount = 0;
                        }, 500);
                    }
                }
                
                addToHistory(type, amount) {
                    this.history.unshift({
                        type: type,
                        amount: amount,
                        text: type === 'win' ? `+${amount}` : `-${this.currentBet}`
                    });
                    if (this.history.length > 12) {
                        this.history.pop();
                    }
                    this.updateHistoryUI();
                }
                
                updateHistoryUI() {
                    this.historyItemsEl.innerHTML = '';
                    this.history.forEach(item => {
                        const historyItem = document.createElement('div');
                        historyItem.className = 'history-item';
                        // Icon
                        const icon = document.createElement('span');
                        icon.className = 'history-icon';
                        if (item.type === 'win') {
                            icon.innerHTML = '<i class="fas fa-coins"></i>';
                        } else {
                            icon.innerHTML = '<i class="fas fa-times-circle"></i>';
                        }
                        historyItem.appendChild(icon);
                        // Details
                        const details = document.createElement('div');
                        details.className = 'history-details';
                        details.innerHTML = item.type === 'win'
                            ? '<span class="history-outcome history-win">WIN</span>'
                            : '<span class="history-outcome history-loss">LOSE</span>';
                        historyItem.appendChild(details);
                        // Amount
                        const amount = document.createElement('span');
                        amount.className = 'history-amount ' + (item.type === 'win' ? 'win' : 'loss');
                        amount.textContent = item.text;
                        historyItem.appendChild(amount);
                        this.historyItemsEl.appendChild(historyItem);
                    });
                }

                showWinModal(type, title, message) {
                    const winModal = document.getElementById('winModal');
                    const winModalTitle = document.getElementById('winModalTitle');
                    const winModalAmount = document.getElementById('winModalAmount');
                    const winModalOverlay = document.getElementById('winModalOverlay');
                    
                    winModalTitle.textContent = title;
                    winModalAmount.textContent = message;
                    
                    if (type === 'win') {
                        winModal.className = 'win-modal';
                        this.createConfetti();
                    } else {
                        winModal.className = 'win-modal loss';
                    }
                    
                    winModalOverlay.classList.add('active');
                }

                createConfetti() {
                    const container = document.getElementById('confettiContainer');
                    container.innerHTML = '';
                    const colors = ['#FFD700', '#FFA500', '#FF6B35', '#9C27B0', '#2196F3'];
                    
                    for (let i = 0; i < 50; i++) {
                        const confetti = document.createElement('div');
                        confetti.className = 'confetti';
                        confetti.style.left = Math.random() * 100 + '%';
                        confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                        confetti.style.animationDelay = Math.random() * 2 + 's';
                        confetti.style.animationDuration = (3 + Math.random() * 2) + 's';
                        container.appendChild(confetti);
                    }
                }

                closeWinModal() {
                    document.getElementById('winModalOverlay').classList.remove('active');
                    document.getElementById('confettiContainer').innerHTML = '';
                }

                updateUI() {
                    this.balanceDisplay.textContent = this.ticketBalance;
                    this.betDisplay.textContent = this.currentBet;
                    this.betInput.value = this.currentBet;
                    this.playBtn.disabled = this.ticketBalance < this.currentBet || this.isShuffling;
                }

                updateServerBalance(bet, win, isWin) {
                    // Send AJAX request to update balances in the database
                    fetch('findingsimba_play.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            bet: bet,
                            win: win,
                            isWin: isWin ? 1 : 0
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            // Update balances from server response for accuracy
                            this.balance = parseFloat(data.rawr_balance);
                            this.ticketBalance = parseInt(data.ticket_balance);
                            this.updateUI();
                        }
                    });
                }
            }

            // Initialize the game
            const game = new FindingSimba();

            // Close win modal when clicking overlay
            const winModalOverlay = document.getElementById('winModalOverlay');
            if (winModalOverlay) {
                winModalOverlay.addEventListener('click', (e) => {
                    if (e.target === e.currentTarget) {
                        game.closeWinModal();
                    }
                });
            }
        });
    </script>
</body>
</html>