<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Safari Casino - Safari Roulette</title>
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

        .roulette-container {
            position: relative; max-width: 600px; margin: 0 auto;
            aspect-ratio: 1/1;
        }
        #rouletteCanvas { display: block; width: 100%; height: 100%; }
        .roulette-pointer {
            position: absolute; top: 50%; right: -20px; transform: translateY(-50%);
            width: 0; height: 0; border-top: 20px solid transparent;
            border-bottom: 20px solid transparent; border-right: 30px solid var(--primary);
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.7)); z-index: 5;
        }
        .roulette-border { position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 4px solid var(--primary); border-radius: 50%; box-shadow: 0 0 30px var(--glow), inset 0 0 20px rgba(0,0,0,0.5); pointer-events: none; }
        .roulette-center-hub { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 15%; height: 15%; background: radial-gradient(circle, var(--primary), var(--secondary)); border-radius: 50%; border: 3px solid #1a1a1a; z-index: 5; }
        
        /* Right Column */
        .controls-column { display: flex; flex-direction: column; gap: 2rem; position: sticky; top: 120px; }
        .bet-label { font-size: 1.2rem; font-weight: 600; text-align: center; margin-bottom: 1rem; color: var(--primary); }
        .bet-input { font-family: 'Poppins', sans-serif; font-size: 2rem; font-weight: 700; color: var(--primary); background: rgba(0,0,0,0.4); border: 2px solid var(--glass-border); border-radius: 8px; text-align: center; width: 100%; padding: 1rem; outline: none; }
        .quick-bet-buttons { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; margin-top: 1rem; }
        .quick-bet-btn { background: rgba(255, 215, 0, 0.1); border: 1px solid var(--glass-border); color: var(--primary); padding: 0.75rem; border-radius: 8px; cursor: pointer; transition: background 0.2s; }
        .quick-bet-btn:hover { background: rgba(255, 215, 0, 0.2); }
        .spin-buttons { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1.5rem; }
        .spin-btn, .auto-spin-btn { border: none; padding: 1rem; border-radius: 8px; font-size: 1.1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; }
        .spin-btn { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: #1a1a1a; }
        .auto-spin-btn { background: transparent; border: 1px solid var(--info); color: var(--info); }
        .auto-spin-btn.active { background: var(--info); color: white; animation: pulse-blue 2s infinite; }
        .spin-btn:disabled, .auto-spin-btn:disabled { background: #555 !important; border-color: #555 !important; color: #999 !important; cursor: not-allowed; animation: none; }
        @keyframes pulse-blue { 0% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.7); } 100% { box-shadow: 0 0 0 10px rgba(59, 130, 246, 0); } }
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
            .game-grid { gap: 1.5rem; }
            .game-title-section h1 { font-size: 2.5rem; }
            .stats-grid { grid-template-columns: repeat(3, 1fr); gap: 1rem; }
            .stat-value { font-size: 1.5rem; }
            .roulette-container { max-width: 90vw; }
        }
        @media (max-width: 480px) {
            .game-wrapper { padding-left: 1rem; padding-right: 1rem; }
            .game-title-section h1 { font-size: 2rem; }
            .stat-value { font-size: 1.2rem; }
            .spin-buttons { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <button class="close-btn" id="closeBtn">×</button>

    <main class="game-wrapper">
        <div class="game-grid">
            <div class="game-main-content">
                <div class="game-title-section">
                    <h1>Safari Roulette</h1>
                    <p>Bet with Tickets, win Tickets! Land on free spins or huge multipliers.</p>
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
                        <div class="stat-label"><i class="fas fa-sync-alt stat-icon"></i>Free Spins</div>
                        <div class="stat-value" id="freeSpinsDisplay">0</div>
                    </div>
                </div>
                <div class="roulette-container">
                    <div class="roulette-border"></div>
                    <canvas id="rouletteCanvas" width="600" height="600"></canvas>
                    <div class="roulette-pointer"></div>
                    <div class="roulette-center-hub"></div>
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
                        <button class="spin-btn" id="spinBtn">Spin</button>
                        <button class="auto-spin-btn" id="autoSpinBtn">Auto Spin</button>
                    </div>
                </div>
                <div class="history-card glass-card">
                    <h3 class="history-title">Spin History</h3>
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
        class SafariRoulette {
            constructor() {
                this.canvas = document.getElementById('rouletteCanvas');
                this.ctx = this.canvas.getContext('2d');
                
                // State
                this.ticketBalance = 1000;
                this.currentBet = 10;
                this.freeSpins = 0;
                this.isSpinning = false;
                this.isAutoSpinning = false;
                this.history = [];
                this.currentRotation = 0;
                this.winningSliceIndex = null;
                
                // Wheel Config with 16 Slices for better distribution
                this.wheelSections = [
                    { outcome: '20X', label: '20X', type: 'multiplier', value: 20, color: '#9C27B0' },
                    { outcome: 'LOSE', label: 'LOSE', type: 'loss', value: 0, color: '#f44336' },
                    { outcome: '+1 Spin', label: '+1 SPIN', type: 'spins', value: 1, color: '#00BCD4' },
                    { outcome: '2X', label: '2X', type: 'multiplier', value: 2, color: '#FFC107' },
                    { outcome: 'LOSE', label: 'LOSE', type: 'loss', value: 0, color: '#f44336' },
                    { outcome: '5X', label: '5X', type: 'multiplier', value: 5, color: '#4CAF50' },
                    { outcome: '+2 Spins', label: '+2 SPINS', type: 'spins', value: 2, color: '#2196F3' },
                    { outcome: 'LOSE', label: 'LOSE', type: 'loss', value: 0, color: '#f44336' },
                    { outcome: '10X', label: '10X', type: 'multiplier', value: 10, color: '#E91E63' },
                    { outcome: 'LOSE', label: 'LOSE', type: 'loss', value: 0, color: '#f44336' },
                    { outcome: '+3 Spins', label: '+3 SPINS', type: 'spins', value: 3, color: '#03A9F4' },
                    { outcome: '3X', label: '3X', type: 'multiplier', value: 3, color: '#FF9800' },
                    { outcome: 'LOSE', label: 'LOSE', type: 'loss', value: 0, color: '#f44336' },
                    { outcome: '2X', label: '2X', type: 'multiplier', value: 2, color: '#FFC107' },
                    { outcome: '+1 Spin', label: '+1 SPIN', type: 'spins', value: 1, color: '#00BCD4' },
                    { outcome: 'LOSE', label: 'LOSE', type: 'loss', value: 0, color: '#f44336' },
                ];
                this.sectionAngle = (2 * Math.PI) / this.wheelSections.length;

                // Probability Map
                this.probabilities = {
                    'LOSE': 0.695, '2X': 0.105, '+1 Spin': 0.105, '3X': 0.025,
                    '+2 Spins': 0.025, '5X': 0.015, '+3 Spins': 0.015, '10X': 0.01, '20X': 0.005,
                };
                this.historyItemColors = { multiplier: '#4CAF50', spins: '#2196F3', loss: '#f44336' };

                this.POINTER_ANGLE = 0; // 0 radians = right side. Use -Math.PI/2 for top

                this.bindDOM();
                this.addEventListeners();
                this.updateBet(this.currentBet);
                this.drawWheel(this.currentRotation, 0); // Initial draw
                this.updateUI();
            }

            bindDOM() {
                // Displays
                this.balanceDisplay = document.getElementById('balanceDisplay');
                this.lastWinDisplay = document.getElementById('lastWinDisplay');
                this.freeSpinsDisplay = document.getElementById('freeSpinsDisplay');
                this.historyItemsEl = document.getElementById('historyItems');
                
                // Controls
                this.betInput = document.getElementById('betInput');
                this.quickBetBtns = document.querySelectorAll('.quick-bet-btn');
                this.spinBtn = document.getElementById('spinBtn');
                this.autoSpinBtn = document.getElementById('autoSpinBtn');
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
                this.spinBtn.addEventListener('click', () => this.spin());
                this.autoSpinBtn.addEventListener('click', () => this.toggleAutoSpin());
                this.winModalCloseBtn.addEventListener('click', () => this.closeWinModal());
                this.closeBtn.addEventListener('click', () => {
                    // Go back to dashboard or close the game
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

            determineWinningOutcome() {
                const rand = Math.random();
                let cumulativeProb = 0;
                for (const outcome in this.probabilities) {
                    cumulativeProb += this.probabilities[outcome];
                    if (rand <= cumulativeProb) return outcome;
                }
                return 'LOSE'; // Fallback
            }

            getSliceIndexByOutcome(outcome) {
                const matchingSlices = [];
                this.wheelSections.forEach((section, index) => {
                    if (section.outcome === outcome) matchingSlices.push(index);
                });
                if (matchingSlices.length === 0) return 1;
                return matchingSlices[Math.floor(Math.random() * matchingSlices.length)];
            }
            
            drawWheel(rotation, elapsedTime) {
                const radius = this.canvas.width / 2;
                this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
                this.ctx.save();
                this.ctx.translate(radius, radius);
                // Apply pointer angle offset so 0 radians is always at pointer
                this.ctx.rotate(this.POINTER_ANGLE);
                this.wheelSections.forEach((section, i) => {
                    const startAngle = (i * this.sectionAngle) + rotation;
                    const endAngle = startAngle + this.sectionAngle;
                    
                    // Draw Slice
                    this.ctx.beginPath();
                    this.ctx.moveTo(0, 0);
                    this.ctx.arc(0, 0, radius, startAngle, endAngle);
                    this.ctx.closePath();
                    this.ctx.fillStyle = section.color;
                    this.ctx.fill();
                    
                    // Glowing effect
                    if (!this.isSpinning && this.winningSliceIndex === i) {
                        const glowIntensity = Math.abs(Math.sin(elapsedTime / 300));
                        this.ctx.fillStyle = `rgba(255, 255, 255, ${0.2 + glowIntensity * 0.2})`;
                        this.ctx.shadowColor = 'white';
                        this.ctx.shadowBlur = 20 + glowIntensity * 10;
                        this.ctx.fill();
                        this.ctx.shadowBlur = 0;
                    }
                    
                    // Draw Border
                    this.ctx.beginPath();
                    this.ctx.moveTo(0, 0);
                    this.ctx.arc(0, 0, radius, startAngle, endAngle);
                    this.ctx.closePath();
                    this.ctx.strokeStyle = '#1a1a1a';
                    this.ctx.lineWidth = 2;
                    this.ctx.stroke();
                    
                    // Draw Text
                    this.ctx.save();
                    this.ctx.rotate(startAngle + this.sectionAngle / 2);
                    this.ctx.fillStyle = 'white';
                    this.ctx.font = 'bold 18px Poppins';
                    this.ctx.textAlign = 'center';
                    this.ctx.textBaseline = 'middle';
                    this.ctx.fillText(section.label, radius * 0.7, 0);
                    this.ctx.restore();
                });
                
                this.ctx.restore();
            }

            spin() {
                if (this.isSpinning) return;
                if (this.freeSpins === 0 && this.currentBet > this.ticketBalance) {
                    alert('Insufficient tickets for this bet!');
                    return;
                }

                this.isSpinning = true;
                this.spinBtn.disabled = true;
                this.autoSpinBtn.disabled = true;
                this.winningSliceIndex = null;

                // Deduct bet (only if not using free spins)
                if (this.freeSpins === 0) {
                    this.ticketBalance -= this.currentBet;
                } else {
                    this.freeSpins--;
                }

                // Determine outcome and target slice
                const winningOutcome = this.determineWinningOutcome();
                const targetSliceIndex = this.getSliceIndexByOutcome(winningOutcome);

                // Animation parameters
                const spinDuration = 3000 + Math.random() * 2000;
                const baseRotations = 8 + Math.random() * 4;
                const startTime = Date.now();
                const startRotation = this.currentRotation;

                // Calculate the target rotation to align winning slice with pointer
                const targetAngle = (targetSliceIndex * this.sectionAngle) + (this.sectionAngle / 2);
                // We want the center of the target slice to align with the pointer
                // So, rotate so that targetAngle aligns with POINTER_ANGLE
                const targetRotation = this.POINTER_ANGLE - targetAngle;

                // Calculate total rotation including full spins
                const totalRotation = (baseRotations * 2 * Math.PI) + targetRotation;

                const animate = () => {
                    const elapsed = Date.now() - startTime;
                    const progress = Math.min(elapsed / spinDuration, 1);
                    
                    // Easing function for smooth deceleration
                    const easeOut = 1 - Math.pow(1 - progress, 3);
                    this.currentRotation = startRotation + (totalRotation * easeOut);
                    
                    this.drawWheel(this.currentRotation, elapsed);
                    
                    if (progress < 1) {
                        requestAnimationFrame(animate);
                    } else {
                        this.handleSpinResult(winningOutcome, targetSliceIndex);
                    }
                };
                
                animate();
            }
            
            handleSpinResult(outcome, sliceIndex) {
                this.isSpinning = false;
                this.spinBtn.disabled = false;
                this.autoSpinBtn.disabled = false;

                // --- Determine the actual winning slice by pointer position ---
                // Pointer is at 0 radians (right side)
                const finalRotationRad = this.currentRotation % (2 * Math.PI);
                const finalRotationDeg = ((finalRotationRad * 180) / Math.PI + 360) % 360;
                const pointerAngleDeg = 0; // right side
                const landedAngle = (360 - finalRotationDeg + pointerAngleDeg) % 360;
                const sectionAngle = 360 / this.wheelSections.length;
                let winningSliceIndex = null;
                for (let i = 0; i < this.wheelSections.length; i++) {
                    const start = i * sectionAngle;
                    const end = start + sectionAngle;
                    if (landedAngle >= start && landedAngle < end) {
                        winningSliceIndex = i;
                        break;
                    }
                }
                this.winningSliceIndex = winningSliceIndex;
                const section = this.wheelSections[winningSliceIndex];
                let winAmount = 0;
                let modalType = 'loss';
                let displayAmount = '';
                if (section.type === 'multiplier') {
                    winAmount = this.currentBet * section.value;
                    this.ticketBalance += winAmount;
                    modalType = 'win';
                    displayAmount = `+${winAmount} Tickets`;
                } else if (section.type === 'spins') {
                    this.freeSpins += section.value;
                    modalType = 'spins';
                    displayAmount = `+${section.value} Spins`;
                } else {
                    displayAmount = `-${this.currentBet} Tickets`;
                }
                // Add to history
                this.history.unshift({
                    outcome: section.outcome,
                    type: section.type,
                    displayAmount: displayAmount
                });
                if (this.history.length > 20) this.history.pop();
                this.updateUI();
                this.showWinModal(section, winAmount, modalType);
                // Continue auto-spin if active
                if (this.isAutoSpinning) {
                    setTimeout(() => {
                        if (this.isAutoSpinning) this.spin();
                    }, 2000);
                }
                // Start glow animation for winning slice
                this.startGlowAnimation();
            }
            
            startGlowAnimation() {
                const startTime = Date.now();
                const animate = () => {
                    if (this.isSpinning || this.winningSliceIndex === null) return;
                    
                    const elapsed = Date.now() - startTime;
                    this.drawWheel(this.currentRotation, elapsed);
                    
                    if (elapsed < 5000) { // Glow for 5 seconds
                        requestAnimationFrame(animate);
                    } else {
                        this.winningSliceIndex = null;
                        this.drawWheel(this.currentRotation, 0);
                    }
                };
                animate();
            }
            
            showWinModal(section, winAmount, type) {
                let title, message;
                
                if (type === 'win') {
                    title = '🎉 WIN!';
                    message = `You won ${winAmount} Tickets!`;
                    this.winModal.className = 'win-modal';
                    this.createConfetti();
                } else if (type === 'spins') {
                    title = '🎯 FREE SPINS!';
                    message = `You got ${section.value} free spin${section.value > 1 ? 's' : ''}!`;
                    this.winModal.className = 'win-modal';
                    this.createConfetti();
                } else {
                    title = '💥 TRY AGAIN';
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
            
            toggleAutoSpin() {
                this.isAutoSpinning = !this.isAutoSpinning;
                this.autoSpinBtn.classList.toggle('active', this.isAutoSpinning);
                this.autoSpinBtn.textContent = this.isAutoSpinning ? 'Stop Auto' : 'Auto Spin';
                
                if (this.isAutoSpinning && !this.isSpinning) {
                    this.spin();
                }
            }
            
            updateUI() {
                // Update all displays
                this.balanceDisplay.textContent = this.ticketBalance.toString();
                this.freeSpinsDisplay.textContent = this.freeSpins.toString();
                
                // Update last win
                const lastWin = this.history.find(h => h.type === 'multiplier');
                this.lastWinDisplay.textContent = lastWin ? lastWin.displayAmount : '0';
                
                // Update history display
                this.historyItemsEl.innerHTML = '';
                this.history.slice(0, 12).forEach(item => {
                    const historyItem = document.createElement('div');
                    historyItem.className = 'history-item';
                    historyItem.style.backgroundColor = this.historyItemColors[item.type];
                    historyItem.textContent = item.displayAmount || item.outcome;
                    this.historyItemsEl.appendChild(historyItem);
                });
                
                // Disable spin button if no tickets and no free spins
                if (this.ticketBalance === 0 && this.freeSpins === 0) {
                    this.spinBtn.disabled = true;
                    this.autoSpinBtn.disabled = true;
                } else {
                    this.spinBtn.disabled = this.isSpinning;
                    this.autoSpinBtn.disabled = this.isSpinning;
                }
            }
        }

        // Initialize the game
        const game = new SafariRoulette();
        
        // Close win modal when clicking overlay
        document.getElementById('winModalOverlay').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) {
                game.closeWinModal();
            }
        });
    });
    </script>
</body>
</html>