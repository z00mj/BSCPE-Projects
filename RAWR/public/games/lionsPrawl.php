<?php
require_once __DIR__ . '/../../backend/inc/init.php';
userOnly();

$db = Database::getInstance();
$user_id = $_SESSION['user_id'];
$user = $db->fetchOne("SELECT ticket_balance FROM users WHERE id = ?", [$user_id]);
$ticketBalance = $user ? (int)$user['ticket_balance'] : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lion's Prowl - RAWR Casino</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

        /* Game Board */
        .game-board-container {
            position: relative;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: var(--border-radius);
            border: 1px solid var(--glass-border);
        }

        .game-grid-container {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            margin: 0 auto;
            max-width: 500px;
        }

        .grid-cell {
            aspect-ratio: 1;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 215, 0, 0.2);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .grid-cell:hover:not(.revealed) {
            border-color: var(--primary);
            transform: scale(1.05);
            box-shadow: var(--glow);
            background: rgba(255, 215, 0, 0.15);
        }

        .grid-cell.revealed {
            cursor: default;
            background: rgba(0, 0, 0, 0.4);
        }

        .grid-cell.lion {
            background: rgba(255, 107, 53, 0.2);
            color: var(--accent);
        }

        .grid-cell.multiplier {
            background: rgba(255, 215, 0, 0.2);
            color: var(--primary);
        }

        .grid-cell.cash {
            background: rgba(76, 175, 80, 0.2);
            color: var(--success);
        }

        /* Right Column */
        .controls-column { display: flex; flex-direction: column; gap: 2rem; position: sticky; top: 120px; }
        .bet-label { font-size: 1.2rem; font-weight: 600; text-align: center; margin-bottom: 1rem; color: var(--primary); }
        .bet-input { font-family: 'Poppins', sans-serif; font-size: 2rem; font-weight: 700; color: var(--primary); background: rgba(0,0,0,0.4); border: 2px solid var(--glass-border); border-radius: 8px; text-align: center; width: 100%; padding: 1rem; outline: none; }
        .quick-bet-buttons { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; margin-top: 1rem; }
        .quick-bet-btn { background: rgba(255, 215, 0, 0.1); border: 1px solid var(--glass-border); color: var(--primary); padding: 0.75rem; border-radius: 8px; cursor: pointer; transition: background 0.2s; }
        .quick-bet-btn:hover { background: rgba(255, 215, 0, 0.2); }
        .action-buttons { display: grid; gap: 1rem; margin-top: 1.5rem; }
        .action-btn { border: none; padding: 1rem; border-radius: 8px; font-size: 1.1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .action-btn:disabled { background: #555 !important; border-color: #555 !important; color: #999 !important; cursor: not-allowed; }
        .collect-btn { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: #1a1a1a; }
        .new-game-btn { background: transparent; border: 1px solid var(--primary); color: var(--primary); }
        .history-card .history-title { font-size: 1.5rem; text-align: center; color: var(--primary); margin-bottom: 1rem; }
        .history-items { display: flex; flex-direction: column; gap: 0.8rem; }
        .history-item {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            padding: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .history-icon { font-size: 1.4rem; min-width: 35px; text-align: center; }
        .history-win { color: var(--success); }
        .history-loss { color: var(--danger); }
        .history-details { flex-grow: 1; }
        .history-outcome { font-weight: 600; font-size: 1rem; margin-bottom: 3px; }
        .history-value { font-weight: 500; color: var(--text-light); font-size: 0.9rem; }
        .history-amount { font-weight: 600; font-size: 1.1rem; min-width: 70px; text-align: right; }
        .history-amount.win { color: var(--success); }
        .history-amount.loss { color: var(--danger); }

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

        /* --- Responsive Styles --- */
        @media (max-width: 1200px) { 
            .game-grid { grid-template-columns: 1fr; } 
            .controls-column { position: static; max-width: 600px; margin: 2rem auto 0; } 
        }
        @media (max-width: 768px) {
            .game-grid {
                grid-template-columns: 1fr;
                gap: 1.2rem;
            }
            .controls-column {
                position: static;
                max-width: 100%;
                margin: 1.5rem auto 0;
                min-width: 0;
                width: 100%;
            }
            .controls-card {
                padding: 1.2rem 1rem;
            }
            .bet-input-container {
                width: 100%;
            }
            .bet-input {
                font-size: 1.5rem;
                padding: 0.7rem;
                width: 100%;
            }
            .quick-bet-buttons {
                grid-template-columns: repeat(3, 1fr);
                gap: 0.4rem;
                margin-top: 0.7rem;
            }
            .quick-bet-btn {
                padding: 0.6rem;
                font-size: 1rem;
            }
            .action-buttons {
                grid-template-columns: 1fr;
                gap: 0.7rem;
                margin-top: 1rem;
            }
            .action-btn {
                font-size: 1rem;
                padding: 0.8rem;
                min-width: 0;
            }
            .history-card {
                padding: 1.2rem 1rem;
                margin-top: 1.2rem;
            }
            .history-title {
                font-size: 1.2rem;
            }
            .history-items {
                max-height: 140px;
                gap: 0.5rem;
                padding-right: 2px;
            }
            .history-item {
                font-size: 0.95rem;
                min-height: 48px;
                padding: 8px;
                gap: 8px;
            }
            .history-icon {
                font-size: 1.1rem;
                min-width: 28px;
            }
            .history-amount {
                font-size: 1rem;
                min-width: 50px;
            }
        }

        @media (max-width: 480px) {
            .game-wrapper {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }
            .controls-card,
            .history-card {
                padding: 1rem 0.5rem;
            }
            .bet-label {
                font-size: 1rem;
            }
            .bet-input {
                font-size: 1.1rem;
                padding: 0.5rem;
            }
            .quick-bet-btn {
                font-size: 0.95rem;
                padding: 0.5rem;
            }
            .action-btn {
                font-size: 0.95rem;
                padding: 0.7rem;
            }
            .history-title {
                font-size: 1rem;
            }
            .history-items {
                max-height: 100px;
                gap: 0.3rem;
            }
            .history-item {
                font-size: 0.9rem;
                min-height: 38px;
                padding: 6px;
                gap: 6px;
            }
            .history-icon {
                font-size: 1rem;
                min-width: 22px;
            }
            .history-amount {
                font-size: 0.95rem;
                min-width: 36px;
            }
        }
    </style>
</head>
<body>
    <button class="close-btn" id="closeBtn">×</button>

    <main class="game-wrapper">
        <div class="game-grid">
            <div class="game-main-content">
                <div class="game-title-section">
                    <h1>Lion's Prowl</h1>
                    <p>Uncover treasures with your tickets! Avoid lions to collect your winnings in RAWR tokens.</p>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card glass-card">
                        <div class="stat-label"><i class="fas fa-gift stat-icon"></i>Current Prize</div>
                        <div class="stat-value" id="currentPrize">0.00</div>
                    </div>
                    <div class="stat-card glass-card">
                        <div class="stat-label"><i class="fas fa-ticket-alt stat-icon"></i>Tickets</div>
                        <div class="stat-value" id="ticketBalance"><?php echo $ticketBalance; ?></div>
                    </div>
                    <div class="stat-card glass-card">
                        <div class="stat-label"><i class="fas fa-trophy stat-icon"></i>Current Multiplier</div>
                        <div class="stat-value" id="currentMultiplier">1x</div>
                    </div>
                </div>
                
                <div class="game-board-container">
                    <div class="game-grid-container" id="gameGrid"></div>
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
                    <div class="action-buttons">
                        <button class="action-btn collect-btn" id="collectBtn" disabled>
                            <i class="fas fa-coins"></i> Collect Winnings
                        </button>
                        <button class="action-btn new-game-btn" id="newGameBtn">
                            <i class="fas fa-play-circle"></i> New Game
                        </button>
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
    // Pass PHP ticket balance to JS
    const USER_TICKET_BALANCE = <?php echo $ticketBalance; ?>;
        document.addEventListener('DOMContentLoaded', () => {
            class LionsProwlGame {
                constructor() {
                    // Game state
                    this.ticketBalance = USER_TICKET_BALANCE;
                    this.currentMultiplier = 1;
                    this.roundWinnings = 0;
                    this.totalWins = 0;
                    this.path = [];
                    this.gameActive = false;
                    this.gridSize = 5;
                    this.gridValues = [];
                    this.betAmount = 10;
                    this.gameHistory = [];

                    // DOM elements
                    this.gameGrid = document.getElementById('gameGrid');
                    this.ticketBalanceEl = document.getElementById('ticketBalance');
                    this.currentMultiplierEl = document.getElementById('currentMultiplier');
                    this.collectBtn = document.getElementById('collectBtn');
                    this.newGameBtn = document.getElementById('newGameBtn');
                    this.betAmountInput = document.getElementById('betInput');
                    this.quickBetBtns = document.querySelectorAll('.quick-bet-btn');
                    this.historyItems = document.getElementById('historyItems');
                    this.closeBtn = document.getElementById('closeBtn');
                    this.winModalOverlay = document.getElementById('winModalOverlay');
                    this.winModal = document.getElementById('winModal');
                    this.winModalTitle = document.getElementById('winModalTitle');
                    this.winModalAmount = document.getElementById('winModalAmount');
                    this.winModalCloseBtn = document.getElementById('winModalCloseBtn');
                    this.currentPrizeEl = document.getElementById('currentPrize');

                    // Initialize game
                    this.initEventListeners();
                    this.updateBet(10);
                    this.newGame();
                }

                initEventListeners() {
                    // Action buttons
                    this.collectBtn.addEventListener('click', () => this.collectWinnings());
                    this.newGameBtn.addEventListener('click', () => {
                        if (this.newGameBtn.disabled) return;
                        if (this.ticketBalance < this.betAmount) {
                            this.showWinModal('Not Enough Tickets', `You need at least ${this.betAmount} tickets to play!`, 'error');
                            return;
                        }
                        this.ticketBalance -= this.betAmount;
                        this.updateBalances();
                        this.newGameBtn.disabled = true;
                        this.newGame();
                    });
                    
                    // Betting controls
                    this.betAmountInput.addEventListener('input', () => {
                        let value = parseInt(this.betAmountInput.value);
                        if (isNaN(value)) value = 10;
                        if (value < 1) value = 1;
                        if (value > 1000) value = 1000;
                        this.updateBet(value);
                    });
                    
                    this.quickBetBtns.forEach(btn => {
                        btn.addEventListener('click', (e) => {
                            const betValue = e.target.id === 'maxBetBtn' ? Math.min(this.ticketBalance, 1000) : parseInt(e.target.dataset.bet);
                            this.updateBet(betValue);
                        });
                    });
                    
                    // Close buttons
                    this.closeBtn.addEventListener('click', () => {
                        if (confirm('Are you sure you want to exit the game? Your current progress will be saved.')) {
                            // In a real app, this would redirect to the casino lobby
                            window.location.href = '/RAWR/public/games.php';
                        }
                    });
                    
                    this.winModalCloseBtn.addEventListener('click', () => this.closeWinModal());
                    this.winModalOverlay.addEventListener('click', (e) => {
                        if (e.target === e.currentTarget) {
                            this.closeWinModal();
                        }
                    });
                }

                updateBet(amount) {
                    this.betAmount = amount;
                    this.betAmountInput.value = this.betAmount;
                    
                    // Update active button
                    this.quickBetBtns.forEach(btn => {
                        const btnValue = btn.id === 'maxBetBtn' ? Math.min(this.ticketBalance, 1000) : parseInt(btn.dataset.bet);
                        if (btnValue === this.betAmount) {
                            btn.classList.add('active');
                        } else {
                            btn.classList.remove('active');
                        }
                    });
                }

                updateBalances() {
                    // this.rawrBalanceEl.textContent = this.rawrBalance.toLocaleString(); // REMOVE THIS LINE
                    this.ticketBalanceEl.textContent = this.ticketBalance;
                }

                newGame() {
                    // Deduct bet from server and update local ticket balance
                    this.sendTicketAction('bet', this.betAmount, (newBalance) => {
                        this.ticketBalance = newBalance;
                        // Reset game state
                        this.currentMultiplier = 1;
                        this.roundWinnings = 0;
                        this.path = [];
                        this.gameActive = true;
                        // Generate grid values with exactly 6 lions
                        this.generateGridValues();
                        // Update UI
                        this.updateUI();
                        this.renderGrid();
                        this.collectBtn.disabled = true;
                    });
                }

                generateGridValues() {
                    this.gridValues = Array(this.gridSize).fill().map(() => Array(this.gridSize).fill(null));

                    // Place exactly 6 lions in random positions
                    this.placeRandomItems('lion', 6);

                    // Place multipliers (25% of cells)
                    const multiplierCount = Math.floor(this.gridSize * this.gridSize * 0.25);
                    this.placeRandomItems('multiplier', multiplierCount, () => {
                        const multipliers = [1.5, 2, 2.5, 3, 5];
                        return multipliers[Math.floor(Math.random() * multipliers.length)];
                    });

                    // Place cash prizes (remaining cells)
                    for (let row = 0; row < this.gridSize; row++) {
                        for (let col = 0; col < this.gridSize; col++) {
                            if (!this.gridValues[row][col]) {
                                const cashValues = [10, 20, 30, 50, 75, 100];
                                this.gridValues[row][col] = {
                                    type: 'cash',
                                    value: cashValues[Math.floor(Math.random() * cashValues.length)]
                                };
                            }
                        }
                    }
                }

                placeRandomItems(type, count, valueFn = () => 0) {
                    let placed = 0;
                    while (placed < count) {
                        const row = Math.floor(Math.random() * this.gridSize);
                        const col = Math.floor(Math.random() * this.gridSize);
                        
                        if (!this.gridValues[row][col]) {
                            this.gridValues[row][col] = {
                                type: type,
                                value: valueFn()
                            };
                            placed++;
                        }
                    }
                }

                renderGrid() {
                    this.gameGrid.innerHTML = '';
                    for (let row = 0; row < this.gridSize; row++) {
                        for (let col = 0; col < this.gridSize; col++) {
                            const cell = document.createElement('div');
                            cell.className = 'grid-cell';
                            cell.dataset.row = row;
                            cell.dataset.col = col;
                            const cellData = this.gridValues[row][col];
                            
                            // Mark visited cells
                            const isVisited = this.path.some(pos => pos.row === row && pos.col === col);
                            if (isVisited && cellData) {
                                cell.classList.add('revealed');
                                switch (cellData.type) {
                                    case 'multiplier':
                                        cell.classList.add('multiplier');
                                        cell.textContent = `${cellData.value}x`;
                                        break;
                                    case 'cash':
                                        cell.classList.add('cash');
                                        cell.textContent = `${cellData.value}`;
                                        break;
                                    case 'lion':
                                        cell.classList.add('lion');
                                        cell.textContent = '🦁';
                                        break;
                                }
                            } else {
                                // Show all cells as clickable from the start
                                cell.textContent = '';
                            }
                            
                            // Add click handler
                            cell.addEventListener('click', () => this.handleCellClick(row, col));
                            this.gameGrid.appendChild(cell);
                        }
                    }
                }

                handleCellClick(row, col) {
                    if (!this.gameActive) return;
                    
                    // Check if already visited
                    if (this.path.some(pos => pos.row === row && pos.col === col)) {
                        this.showWinModal('Already Visited', 'You already uncovered this tile!', 'error');
                        return;
                    }
                    
                    // Add to path
                    this.path.push({ row, col });
                    
                    // Get cell data
                    const cellData = this.gridValues[row][col];
                    
                    // Handle cell type
                    switch (cellData.type) {
                        case 'multiplier':
                            this.currentMultiplier *= cellData.value;
                            this.roundWinnings *= cellData.value;
                            this.showWinModal('Multiplier Found!', `Your multiplier is now ${this.currentMultiplier.toFixed(1)}x`, 'multiplier');
                            break;
                            
                        case 'cash':
                            // Cash value is scaled by the bet amount
                            const cashValue = cellData.value * (this.betAmount / 10) * this.currentMultiplier;
                            this.roundWinnings += cashValue;
                            this.showWinModal('Treasure Found!', `+${cashValue.toFixed(2)} Tickets added to your winnings!`, 'cash');
                            break;
                            
                        case 'lion':
                            // Sync ticket balance with backend on loss
                            this.sendTicketAction('lose', 0, (newBalance) => {
                                this.ticketBalance = newBalance;
                                this.showWinModal('Lion Attack!', 'The lion ended your journey!', 'error');
                                this.addToHistory(false);
                                this.endGame(false);
                            });
                            return;
                    }
                    
                    // Update UI
                    this.updateUI();
                    this.renderGrid();
                    
                    // Enable collect button after first move
                    if (this.path.length > 0) {
                        this.collectBtn.disabled = false;
                    }
                }

                collectWinnings() {
                    if (!this.gameActive || this.roundWinnings <= 0) return;
                    const ticketsGained = Math.floor(this.roundWinnings);
                    this.sendTicketAction('collect', ticketsGained, (newBalance) => {
                        this.ticketBalance = newBalance;
                        this.totalWins++;
                        this.showWinModal('Winnings Collected!', `You gained ${ticketsGained} Tickets!`, 'success');
                        this.addToHistory(true, ticketsGained);
                        this.endGame(true);
                    });
                }

                sendTicketAction(action, amount, callback) {
                    fetch('/RAWR/backend/games/lionsPrawl_process.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action, amount })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            if (callback) callback(data.ticket_balance);
                        } else {
                            alert('Ticket update failed: ' + (data.error || 'Unknown error'));
                        }
                    })
                    .catch(err => alert('Error updating ticket balance: ' + err));
                }

                endGame(voluntary) {
                    this.gameActive = false;
                    this.collectBtn.disabled = true;
                    this.newGameBtn.disabled = false;
                    
                    // Reveal all cells
                    if (!voluntary) {
                        for (let row = 0; row < this.gridSize; row++) {
                            for (let col = 0; col < this.gridSize; col++) {
                                const cellData = this.gridValues[row][col];
                                if (cellData && !this.path.some(pos => pos.row === row && pos.col === col)) {
                                    const cell = document.querySelector(`.grid-cell[data-row="${row}"][data-col="${col}"]`);
                                    if (cell) {
                                        cell.classList.add('revealed');
                                        switch (cellData.type) {
                                            case 'multiplier':
                                                cell.classList.add('multiplier');
                                                cell.textContent = `${cellData.value}x`;
                                                break;
                                            case 'cash':
                                                cell.classList.add('cash');
                                                cell.textContent = `${cellData.value}`;
                                                break;
                                            case 'lion':
                                                cell.classList.add('lion');
                                                cell.textContent = '🦁';
                                                break;
                                        }
                                    }
                                }
                            }
                        }
                    }
                    
                    // Update UI
                    this.updateBalances();
                    this.updateUI();
                }

                addToHistory(win, amount = 0) {
                    // Add to history
                    this.gameHistory.unshift({
                        win: win,
                        amount: win ? amount : this.betAmount,
                        timestamp: new Date()
                    });
                    
                    if (this.gameHistory.length > 6) {
                        this.gameHistory.pop();
                    }
                    
                    // Update history display
                    this.updateHistoryDisplay();
                }
                
                updateHistoryDisplay() {
                    this.historyItems.innerHTML = '';
                    
                    this.gameHistory.forEach(history => {
                        const historyItem = document.createElement('div');
                        historyItem.className = 'history-item';
                        
                        if (history.win) {
                            historyItem.innerHTML = `
                                <div class="history-icon history-win">
                                    <i class="fas fa-coins"></i>
                                </div>
                                <div class="history-details">
                                    <div class="history-outcome">Win</div>
                                    <div class="history-value">${history.timestamp.toLocaleTimeString()}</div>
                                </div>
                                <div class="history-amount win">+${history.amount.toFixed(2)}</div>
                            `;
                        } else {
                            historyItem.innerHTML = `
                                <div class="history-icon history-loss">
                                    <i class="fas fa-lion"></i>
                                </div>
                                <div class="history-details">
                                    <div class="history-outcome">Loss</div>
                                    <div class="history-value">${history.timestamp.toLocaleTimeString()}</div>
                                </div>
                                <div class="history-amount loss">-${history.amount}</div>
                            `;
                        }
                        
                        this.historyItems.appendChild(historyItem);
                    });
                }

                updateUI() {
                    this.currentMultiplierEl.textContent = `${this.currentMultiplier.toFixed(1)}x`;
                    this.updateBalances();
                    this.currentPrizeEl.textContent = Math.floor(this.roundWinnings);
                }

                showWinModal(title, message, type) {
                    this.winModalTitle.textContent = title;
                    this.winModalAmount.textContent = message;
                    
                    // Set modal style based on type
                    this.winModal.className = 'win-modal';
                    if (type === 'error') {
                        this.winModal.className = 'win-modal loss';
                    }
                    
                    // Add confetti for wins
                    if (type === 'success' || type === 'multiplier' || type === 'cash') {
                        this.createConfetti();
                    }
                    
                    this.winModalOverlay.classList.add('active');
                }
                
                closeWinModal() {
                    this.winModalOverlay.classList.remove('active');
                    document.getElementById('confettiContainer').innerHTML = '';
                }
                
                createConfetti() {
                    const container = document.getElementById('confettiContainer');
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
            }
            
            // Initialize the game
            new LionsProwlGame();
        });
    </script>
</body>
</html>