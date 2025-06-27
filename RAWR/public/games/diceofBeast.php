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
    <title>Safari Casino - Dice of Beast</title>
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
            --lion-color: #ff8c00;
            --tiger-color: #ff4500;
            --wolf-color: #708090;
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

        /* Dice Game Area */
        .dice-game-area {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .animal-selection {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            width: 100%;
            max-width: 600px;
        }

        .animal-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 2px solid transparent;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .animal-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent, rgba(255, 215, 0, 0.1), transparent);
            transform: translateX(-100%);
            transition: transform 0.6s;
        }

        .animal-card:hover::before {
            transform: translateX(100%);
        }

        .animal-card:hover {
            border-color: var(--primary);
            box-shadow: var(--glow);
            transform: translateY(-5px);
        }

        .animal-card.selected {
            border-color: var(--primary);
            box-shadow: var(--glow);
            background: rgba(255, 215, 0, 0.1);
        }

        .animal-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
        }

        .lion .animal-icon { color: var(--lion-color); }
        .tiger .animal-icon { color: var(--tiger-color); }
        .wolf .animal-icon { color: var(--wolf-color); }

        .animal-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-light);
            margin-bottom: 0.5rem;
        }

        .animal-odds {
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        /* Dice Container */
        .dice-container {
            position: relative;
            perspective: 1000px;
            margin: 2rem 0;
        }

        .dice {
            width: 120px;
            height: 120px;
            position: relative;
            transform-style: preserve-3d;
            transition: transform 2s ease-out;
            margin: 0 auto;
        }

        .dice-face {
            position: absolute;
            width: 120px;
            height: 120px;
            background: var(--glass-bg);
            border: 2px solid var(--primary);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: bold;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
        }

        .dice-face.front { transform: rotateY(0deg) translateZ(60px); }
        .dice-face.back { transform: rotateY(180deg) translateZ(60px); }
        .dice-face.right { transform: rotateY(90deg) translateZ(60px); }
        .dice-face.left { transform: rotateY(-90deg) translateZ(60px); }
        .dice-face.top { transform: rotateX(90deg) translateZ(60px); }
        .dice-face.bottom { transform: rotateX(-90deg) translateZ(60px); }

        .dice-face.lion { color: var(--lion-color); }
        .dice-face.tiger { color: var(--tiger-color); }
        .dice-face.wolf { color: var(--wolf-color); }

        /* Roll Button */
        .roll-button {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #1a1a1a;
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-size: 1.2rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 150px;
        }

        .roll-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 215, 0, 0.3);
        }

        .roll-button:disabled {
            background: #555 !important;
            color: #999 !important;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Right Column */
        .controls-column { display: flex; flex-direction: column; gap: 2rem; position: sticky; top: 120px; }
        .bet-label { font-size: 1.2rem; font-weight: 600; text-align: center; margin-bottom: 1rem; color: var(--primary); }
        .bet-input { font-family: 'Poppins', sans-serif; font-size: 2rem; font-weight: 700; color: var(--primary); background: rgba(0,0,0,0.4); border: 2px solid var(--glass-border); border-radius: 8px; text-align: center; width: 100%; padding: 1rem; outline: none; }
        .quick-bet-buttons { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; margin-top: 1rem; }
        .quick-bet-btn { background: rgba(255, 215, 0, 0.1); border: 1px solid var(--glass-border); color: var(--primary); padding: 0.75rem; border-radius: 8px; cursor: pointer; transition: background 0.2s; }
        .quick-bet-btn:hover { background: rgba(255, 215, 0, 0.2); }

        .history-card .history-title { font-size: 1.5rem; text-align: center; color: var(--primary); margin-bottom: 1rem; }
        .history-items { display: flex; flex-wrap: wrap; gap: 0.5rem; justify-content: center; }
        .history-item { width: 45px; height: 45px; border-radius: 8px; display: flex; justify-content: center; align-items: center; font-weight: bold; color: white; font-size: 0.8rem; text-align:center; }

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
        @media (max-width: 1200px) { .game-grid { grid-template-columns: 1fr; } .controls-column { position: static; max-width: 600px; margin: 2rem auto 0; } }
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
                flex-wrap: wrap;
                justify-content: flex-start;
            }
            .history-item {
                font-size: 0.95rem;
                min-height: 38px;
                min-width: 38px;
                width: 38px;
                height: 38px;
                padding: 6px;
                gap: 6px;
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
            .history-title {
                font-size: 1rem;
            }
            .history-items {
                max-height: 100px;
                gap: 0.3rem;
            }
            .history-item {
                font-size: 0.9rem;
                min-height: 30px;
                min-width: 30px;
                width: 30px;
                height: 30px;
                padding: 3px;
                gap: 3px;
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
                    <h1>Dice of Beast</h1>
                    <p>Choose your beast and roll the dice! Each beast has 2 faces on the die.</p>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card glass-card">
                        <div class="stat-label"><i class="fas fa-ticket-alt stat-icon"></i>Ticket Balance</div>
                        <div class="stat-value" id="balanceDisplay">1000</div>
                    </div>
                    <div class="stat-card glass-card">
                        <div class="stat-label"><i class="fas fa-trophy stat-icon"></i>Last Win</div>
                        <div class="stat-value" id="lastWinDisplay">0</div>
                    </div>
                    <div class="stat-card glass-card">
                        <div class="stat-label"><i class="fas fa-dice stat-icon"></i>Total Rolls</div>
                        <div class="stat-value" id="totalRollsDisplay">0</div>
                    </div>
                </div>

                <div class="dice-game-area glass-card">
                    <h3 style="color: var(--primary); font-size: 1.3rem; margin-bottom: 1rem;">Choose Your Beast</h3>
                    
                    <div class="animal-selection">
                        <div class="animal-card lion" data-animal="lion">
                            <span class="animal-icon">🦁</span>
                            <div class="animal-name">Lion</div>
                            <div class="animal-odds">1 face • 5x payout</div>
                        </div>
                        <div class="animal-card tiger" data-animal="tiger">
                            <span class="animal-icon">🐅</span>
                            <div class="animal-name">Tiger</div>
                            <div class="animal-odds">2 faces • 3x payout</div>
                        </div>
                        <div class="animal-card wolf" data-animal="wolf">
                            <span class="animal-icon">🐺</span>
                            <div class="animal-name">Wolf</div>
                            <div class="animal-odds">3 faces • 2x payout</div>
                        </div>
                    </div>

                    <div class="dice-container">
                        <div class="dice" id="dice">
                            <div class="dice-face front lion">🦁</div>
                            <div class="dice-face back tiger">🐅</div>
                            <div class="dice-face right wolf">🐺</div>
                            <div class="dice-face left wolf">🐺</div>
                            <div class="dice-face top tiger">🐅</div>
                            <div class="dice-face bottom wolf">🐺</div>
                        </div>
                    </div>

                    <button class="roll-button" id="rollBtn" disabled>Select Beast & Roll</button>
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
                </div>
                
                <div class="history-card glass-card">
                    <h3 class="history-title">Roll History</h3>
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
        class JungleDice {
            constructor() {
                // State
                this.ticketBalance = <?php echo $ticketBalance; ?>;
                this.currentBet = 10;
                this.selectedAnimal = null;
                this.isRolling = false;
                this.history = [];
                this.totalRolls = 0;

                // 1. Updated dice faces: lion (1), tiger (2), wolf (3)
                this.diceFaces = ['lion', 'tiger', 'wolf', 'wolf', 'tiger', 'wolf'];

                // Dice rotation mappings for each face
                this.faceRotations = {
                    0: { x: 0, y: 0 },      // front - lion
                    1: { x: 0, y: 180 },    // back - tiger  
                    2: { x: 0, y: -90 },    // right - wolf
                    3: { x: 0, y: 90 },     // left - wolf
                    4: { x: -90, y: 0 },    // top - tiger
                    5: { x: 90, y: 0 }      // bottom - wolf
                };

                this.historyItemColors = {
                    lion: '#ff8c00',
                    tiger: '#ff4500', 
                    wolf: '#708090'
                };

                this.bindDOM();
                this.addEventListeners();
                this.updateUI();
            }

            bindDOM() {
                // Displays
                this.balanceDisplay = document.getElementById('balanceDisplay');
                this.lastWinDisplay = document.getElementById('lastWinDisplay');
                this.totalRollsDisplay = document.getElementById('totalRollsDisplay');
                this.historyItemsEl = document.getElementById('historyItems');
                
                // Controls
                this.betInput = document.getElementById('betInput');
                this.quickBetBtns = document.querySelectorAll('.quick-bet-btn');
                this.animalCards = document.querySelectorAll('.animal-card');
                this.rollBtn = document.getElementById('rollBtn');
                this.dice = document.getElementById('dice');
                this.closeBtn = document.getElementById('closeBtn');

                // Modal
                this.winModalOverlay = document.getElementById('winModalOverlay');
                this.winModal = document.getElementById('winModal');
                this.winModalTitle = document.getElementById('winModalTitle');
                this.winModalAmount = document.getElementById('winModalAmount');
                this.winModalCloseBtn = document.getElementById('winModalCloseBtn');
            }

            addEventListeners() {
                this.quickBetBtns.forEach(btn => btn.addEventListener('click', this.handleQuickBet.bind(this)));
                this.betInput.addEventListener('change', () => this.updateBet(parseInt(this.betInput.value)));
                this.animalCards.forEach(card => card.addEventListener('click', this.selectAnimal.bind(this)));
                this.rollBtn.addEventListener('click', () => this.rollDice());
                this.winModalCloseBtn.addEventListener('click', () => this.closeWinModal());
                this.closeBtn.addEventListener('click', () => {
                    window.location.href = '/RAWR/public/games.php';
                });
            }

            handleQuickBet(e) {
                const betValue = e.target.id === 'maxBetBtn' ? Math.min(this.ticketBalance, 1000) : parseInt(e.target.dataset.bet);
                this.updateBet(betValue);
            }

            updateBet(amount) {
                if (isNaN(amount) || amount < 1) amount = 1;
                if (amount > this.ticketBalance && this.ticketBalance > 0) {
                    amount = this.ticketBalance;
                } else if (this.ticketBalance === 0) {
                    amount = 1;
                }
                if (amount > 1000) amount = 1000;
                this.currentBet = amount;
                this.betInput.value = this.currentBet;
                this.updateUI();
            }

            selectAnimal(e) {
                this.animalCards.forEach(card => card.classList.remove('selected'));
                const card = e.currentTarget;
                card.classList.add('selected');
                this.selectedAnimal = card.dataset.animal;
                this.updateUI();
            }

            rollDice() {
                if (this.isRolling || !this.selectedAnimal || this.currentBet > this.ticketBalance) return;

                this.isRolling = true;
                this.rollBtn.disabled = true;
                this.totalRolls++;

                // Reset dice transition and transform for smooth animation
                this.dice.style.transition = 'none';
                this.dice.style.transform = 'rotateX(0deg) rotateY(0deg)';
                // Force reflow
                void this.dice.offsetWidth;

                // AJAX to backend for roll
                fetch('/RAWR/backend/games/diceofBeast_process.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        bet: this.currentBet,
                        animal: this.selectedAnimal
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        this.ticketBalance = data.ticket_balance;
                        // Find the face index for the result animal
                        const faceIndexes = this.diceFaces
                            .map((animal, idx) => animal === data.result_animal ? idx : -1)
                            .filter(idx => idx !== -1);
                        // Pick a random face index for the result animal
                        const resultFace = faceIndexes[Math.floor(Math.random() * faceIndexes.length)];
                        // 3D dice rotation
                        const baseRotations = 6;
                        const finalRotation = this.faceRotations[resultFace];
                        const totalRotationX = (baseRotations * 360) + finalRotation.x;
                        const totalRotationY = (baseRotations * 360) + finalRotation.y;
                        // Animate
                        setTimeout(() => {
                            this.dice.style.transition = 'transform 2s cubic-bezier(0.23, 1, 0.32, 1)';
                            this.dice.style.transform = `rotateX(${totalRotationX}deg) rotateY(${totalRotationY}deg)`;
                        }, 10);
                        setTimeout(() => {
                            this.handleRollResult(data.result_animal, data.win_amount);
                        }, 2010);
                    } else {
                        alert(data.message || 'Error processing roll.');
                        this.isRolling = false;
                        this.rollBtn.disabled = false;
                    }
                })
                .catch(() => {
                    alert('Network error.');
                    this.isRolling = false;
                    this.rollBtn.disabled = false;
                });
            }

            handleRollResult(resultAnimal, winAmount) {
                this.isRolling = false;
                this.rollBtn.disabled = false;

                let modalType = winAmount > 0 ? 'win' : 'loss';
                let displayAmount = winAmount > 0 ? `+${winAmount}` : `-${this.currentBet}`;

                // 4. Updated payout calculation
                if (resultAnimal === this.selectedAnimal) {
                    let multiplier;
                    if (this.selectedAnimal === 'lion') {
                        multiplier = 5;
                    } else if (this.selectedAnimal === 'tiger') {
                        multiplier = 3;
                    } else { // wolf
                        multiplier = 2;
                    }
                    winAmount = this.currentBet * multiplier;
                    this.ticketBalance += winAmount;
                    modalType = 'win';
                    displayAmount = `+${winAmount}`;
                }

                this.history.unshift({
                    animal: resultAnimal,
                    selected: this.selectedAnimal,
                    won: resultAnimal === this.selectedAnimal,
                    displayAmount: displayAmount
                });
                if (this.history.length > 20) this.history.pop();

                this.updateUI();
                this.showWinModal(resultAnimal, winAmount, modalType);
            }

            showWinModal(resultAnimal, winAmount, type) {
                let title, message;
                const animalEmojis = { lion: '🦁', tiger: '🐅', wolf: '🐺' };
                if (type === 'win') {
                    title = `${animalEmojis[resultAnimal]} WIN!`;
                    message = `You won ${winAmount} Tickets!`;
                    this.winModal.className = 'win-modal';
                    this.createConfetti();
                } else {
                    title = `${animalEmojis[resultAnimal]} TRY AGAIN`;
                    message = `You lost ${this.currentBet} Tickets`;
                    this.winModal.className = 'win-modal loss';
                }
                this.winModalTitle.textContent = title;
                this.winModalAmount.textContent = message;
                this.winModalOverlay.classList.add('active');
            }

            closeWinModal() {
                this.winModalOverlay.classList.remove('active');
                document.getElementById('confettiContainer').innerHTML = '';
            }

            createConfetti() {
                const container = document.getElementById('confettiContainer');
                const colors = ['#FFD700', '#FFA500', '#FF6B35', '#ff8c00', '#ff4500'];
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

            updateUI() {
                this.balanceDisplay.textContent = this.ticketBalance.toString();
                this.totalRollsDisplay.textContent = this.totalRolls.toString();
                const lastWin = this.history.find(h => h.won);
                this.lastWinDisplay.textContent = lastWin ? (lastWin.displayAmount.replace('+', '')) : '0';
                if (this.selectedAnimal && this.currentBet <= this.ticketBalance && !this.isRolling) {
                    this.rollBtn.disabled = false;
                    this.rollBtn.textContent = `Roll Dice (${this.currentBet} Tickets)`;
                } else if (!this.selectedAnimal) {
                    this.rollBtn.disabled = true;
                    this.rollBtn.textContent = 'Select Beast & Roll';
                } else if (this.currentBet > this.ticketBalance) {
                    this.rollBtn.disabled = true;
                    this.rollBtn.textContent = 'Insufficient Tickets';
                } else if (this.isRolling) {
                    this.rollBtn.disabled = true;
                    this.rollBtn.textContent = 'Rolling...';
                }
                this.betInput.max = Math.min(this.ticketBalance, 1000);
                this.updateHistoryDisplay();
            }

            updateHistoryDisplay() {
                this.historyItemsEl.innerHTML = '';
                this.history.forEach(item => {
                    const historyItem = document.createElement('div');
                    historyItem.className = 'history-item';
                    historyItem.style.backgroundColor = this.historyItemColors[item.animal];
                    const emoji = document.createElement('span');
                    if (item.animal === 'lion') {
                        emoji.textContent = '🦁';
                    } else if (item.animal === 'tiger') {
                        emoji.textContent = '🐅';
                    } else {
                        emoji.textContent = '🐺';
                    }
                    historyItem.appendChild(emoji);
                    if (item.won) {
                        historyItem.style.border = '2px solid #28a745';
                        historyItem.style.boxShadow = '0 0 10px rgba(40, 167, 69, 0.5)';
                    } else {
                        historyItem.style.border = '2px solid #dc3545';
                        historyItem.style.boxShadow = '0 0 10px rgba(220, 53, 69, 0.3)';
                    }
                    this.historyItemsEl.appendChild(historyItem);
                });
            }
        }

        // Initialize the game
        new JungleDice();
    });
    </script>
</body>
</html>