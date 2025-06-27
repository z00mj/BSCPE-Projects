<?php
declare(strict_types=1);

// Initialize the application environment, session, and database connection
require_once __DIR__ . '/../../backend/inc/init.php';

// Ensure the user is logged in to play the game
userOnly();

$db = Database::getInstance();
$user_id = $_SESSION['user_id'] ?? 0;

// --- API Endpoint for Handling Spins ---
// This block handles the POST request sent by the game's JavaScript when the user spins the wheel.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // Helper for JSON response
    function send_json_response($data, $http_code = 200) {
        http_response_code($http_code);
        echo json_encode($data);
        exit;
    }

    // Basic security checks
    if (
        !isset($_POST['betAmount']) ||
        !isset($_POST['csrf_token']) ||
        !validateCsrfToken($_POST['csrf_token'])
    ) {
        send_json_response(['status' => 'error', 'message' => 'Invalid request or session expired. Please refresh.'], 400);
    }

    $betAmount = filter_input(INPUT_POST, 'betAmount', FILTER_VALIDATE_INT);
    // Detect if a free spin is being used (client should send this, fallback to 0 if not present)
    $freeSpinsUsed = isset($_POST['freeSpinsUsed']) ? (bool)$_POST['freeSpinsUsed'] : false;
    $game_type_id = 2; // Safari Roulette

    // Validate the bet amount and free spin usage
    if ($betAmount <= 0 && !$freeSpinsUsed) {
        send_json_response(['status' => 'error', 'message' => 'Invalid bet amount.'], 400);
    }

    // --- Server-Side Game Logic ---
    function determineWinningOutcome(): string {
        $probabilities = [
            'LOSE' => 0.695, '2X' => 0.105, '+1 Spin' => 0.105, '3X' => 0.025,
            '+2 Spins' => 0.025, '5X' => 0.015, '+3 Spins' => 0.015, '10X' => 0.01, '20X' => 0.005,
        ];
        $rand = mt_rand() / mt_getrandmax();
        $cumulativeProb = 0;
        foreach ($probabilities as $outcome => $probability) {
            $cumulativeProb += $probability;
            if ($rand <= $cumulativeProb) {
                return $outcome;
            }
        }
        return 'LOSE'; // Fallback
    }

    // Start transaction
    $db->beginTransaction();

    try {
        // Get user's ticket balance with lock
        $user = $db->fetchOne("SELECT ticket_balance, id FROM users WHERE id = ? FOR UPDATE", [$user_id]);

        if (!$user) {
            throw new Exception("User not found.");
        }

        $currentBalance = (int)$user['ticket_balance'];
        $finalBalance = $currentBalance;

        // Determine outcome
        $winningOutcome = determineWinningOutcome();
        $payout = 0;
        $spinsWon = 0;
        $gameOutcomeStatus = 'loss';

        $wheelSections = [
            '20X' => ['type' => 'multiplier', 'value' => 20], 
            '10X' => ['type' => 'multiplier', 'value' => 10],
            '5X' => ['type' => 'multiplier', 'value' => 5], 
            '3X' => ['type' => 'multiplier', 'value' => 3],
            '2X' => ['type' => 'multiplier', 'value' => 2], 
            '+3 Spins' => ['type' => 'spins', 'value' => 3],
            '+2 Spins' => ['type' => 'spins', 'value' => 2], 
            '+1 Spin' => ['type' => 'spins', 'value' => 1],
            'LOSE' => ['type' => 'loss', 'value' => 0]
        ];

        $resultData = $wheelSections[$winningOutcome];

        // Deduct for LOSE
        if ($resultData['type'] === 'loss' && !$freeSpinsUsed) {
            if ($finalBalance < $betAmount) {
                throw new Exception("Insufficient ticket balance.");
            }
            $finalBalance -= $betAmount;
        }

        // Add payout for multiplier
        if ($resultData['type'] === 'multiplier') {
            $payout = $betAmount * $resultData['value'];
            $finalBalance += $payout;
            if ($payout > 0) {
                $gameOutcomeStatus = 'win';
            }
        } elseif ($resultData['type'] === 'spins') {
            $spinsWon = $resultData['value'];
            $gameOutcomeStatus = 'win';
        }

        // Update ticket balance in DB only once
        if ($finalBalance !== $currentBalance) {
            $db->executeQuery("UPDATE users SET ticket_balance = ? WHERE id = ?", [$finalBalance, $user_id]);
        }

        // Log the game result
        $gameDetails = json_encode([
            'outcome' => $winningOutcome, 
            'bet' => $betAmount, 
            'free_spin_used' => $freeSpinsUsed,
            'multiplier' => $resultData['type'] === 'multiplier' ? $resultData['value'] : null,
            'spins_won' => $resultData['type'] === 'spins' ? $resultData['value'] : null
        ]);

        $db->insert('game_results', [
            'user_id' => $user_id,
            'game_type_id' => $game_type_id,
            'bet_amount' => $betAmount,
            'payout' => $payout,
            'outcome' => $gameOutcomeStatus,
            'game_details' => $gameDetails
        ]);

        // Commit transaction
        $db->commit();

        // Send the result back to the game client
        send_json_response([
            'status' => 'success',
            'outcome' => $winningOutcome,
            'payout' => $payout,
            'spinsWon' => $spinsWon,
            'newBalance' => $finalBalance
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        send_json_response(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit; // Terminate the script after sending the JSON response
}


// --- Page Load Logic ---
// This part runs for a normal GET request to load the game page.
$user = $db->fetchOne("SELECT ticket_balance FROM users WHERE id = ?", [$user_id]);
$initial_ticket_balance = $user['ticket_balance'] ?? 0;
$csrf_token = generateCsrfToken();

?>
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
            --primary: #FFD700; --primary-light: #FFDF40; --secondary: #FFA500; --accent: #FF6B35; --dark-bg: #0d0d0d;
            --dark-bg-gradient: linear-gradient(135deg, #1a1a1a 0%, #2d1810 100%); --card-bg: rgba(30, 30, 30, 0.6);
            --text-light: #f0f0f0; --text-muted: #ccc; --border-radius: 12px; --transition: all 0.3s ease;
            --glass-bg: rgba(40, 40, 40, 0.25); --glass-border: rgba(255, 215, 0, 0.1); --glow: 0 0 15px rgba(255, 215, 0, 0.3);
            --success: #28a745; --danger: #dc3545; --info: #3b82f6;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: var(--dark-bg-gradient); color: var(--text-light); min-height: 100vh; overflow-x: hidden; position: relative; }
        body::before { content: ""; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><path d="M20,20 Q40,5 60,20 T100,20 Q85,40 100,60 T100,100 Q60,85 20,100 T0,100 Q5,60 0,20 T20,20 Z" fill="none" stroke="rgba(255,215,0,0.05)" stroke-width="0.5"/></svg>'); background-size: 300px; opacity: 0.3; z-index: -1; }
        .glass-card { background: var(--glass-bg); backdrop-filter: blur(10px); border: 1px solid var(--glass-border); border-radius: var(--border-radius); padding: 2rem; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2); }
        .close-btn { position: fixed; top: 20px; right: 20px; background: rgba(255, 215, 0, 0.1); border: 1px solid var(--glass-border); color: var(--primary); width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; cursor: pointer; z-index: 101; transition: var(--transition); }
        .close-btn:hover { background: rgba(255,215,0,0.2); transform: scale(1.1); }
        .game-wrapper { padding: 100px 2rem 80px; max-width: 1400px; margin: 0 auto; }
        .game-grid { display: grid; grid-template-columns: 1fr 400px; gap: 2rem; align-items: flex-start; }
        .game-title-section { text-align: center; margin-bottom: 2rem; }
        .game-title-section h1 { font-size: 3rem; background: linear-gradient(to right, var(--primary), var(--secondary)); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; text-shadow: 0 0 20px rgba(255, 215, 0, 0.2); }
        .game-title-section p { color: var(--text-muted); font-size: 1.2rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { text-align: center; padding: 1.5rem; }
        .stat-label { font-size: 1rem; color: var(--text-muted); margin-bottom: 0.5rem; }
        .stat-value { font-size: 1.8rem; font-weight: 700; color: var(--primary-light); }
        .stat-icon { margin-right: 0.5rem; }
        .roulette-container { position: relative; max-width: 600px; margin: 0 auto; aspect-ratio: 1/1; }
        #rouletteCanvas { display: block; width: 100%; height: 100%; }
        .roulette-pointer { position: absolute; top: 50%; right: -20px; transform: translateY(-50%); width: 0; height: 0; border-top: 20px solid transparent; border-bottom: 20px solid transparent; border-right: 30px solid var(--primary); filter: drop-shadow(0 2px 4px rgba(0,0,0,0.7)); z-index: 5; }
        .roulette-border { position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 4px solid var(--primary); border-radius: 50%; box-shadow: 0 0 30px var(--glow), inset 0 0 20px rgba(0,0,0,0.5); pointer-events: none; }
        .roulette-center-hub { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 15%; height: 15%; background: radial-gradient(circle, var(--primary), var(--secondary)); border-radius: 50%; border: 3px solid #1a1a1a; z-index: 5; }
        .controls-column { display: flex; flex-direction: column; gap: 2rem; position: sticky; top: 120px; height: 600px; max-height: 80vh; min-height: 400px; }
        .controls-card { flex-shrink: 0; }
        .bet-label { font-size: 1.2rem; font-weight: 600; text-align: center; margin-bottom: 1rem; color: var(--primary); }
        .bet-input { font-family: 'Poppins', sans-serif; font-size: 2rem; font-weight: 700; color: var(--primary); background: rgba(0,0,0,0.4); border: 2px solid var(--glass-border); border-radius: 8px; text-align: center; width: 100%; padding: 1rem; outline: none; }
        .quick-bet-buttons { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; margin-top: 1rem; }
        .quick-bet-btn { background: rgba(255, 215, 0, 0.1); border: 1px solid var(--glass-border); color: var(--primary); padding: 0.75rem; border-radius: 8px; cursor: pointer; transition: background 0.2s; }
        .quick-bet-btn:hover { background: rgba(255, 215, 0, 0.2); }
        .spin-buttons { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1.5rem; }
        .spin-btn, .auto-spin-btn { border: none; padding: 1rem; border-radius: 8px; font-size: 1.1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; }
        .spin-btn { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: #1a1a1a; }
        .auto-spin-btn { background: transparent; border: 1px solid var(--primary);  /* Changed from --info to --primary */ color: var(--primary);             /* Changed from --info to --primary */ }
        .auto-spin-btn.active { background: var(--primary);        /* Changed from --info to --primary */ color: #1a1a1a;                    /* Changed from white to dark for contrast */ animation: pulse-gold 2s infinite; /* Changed animation name */ }
        .spin-btn:disabled, .auto-spin-btn:disabled { background: #555 !important; border-color: #555 !important; color: #999 !important; cursor: not-allowed; animation: none; }
        @keyframes pulse-gold {                /* New animation for gold color */ 0% { box-shadow: 0 0 0 0 rgba(255, 215, 0, 0.7); } 100% { box-shadow: 0 0 0 10px rgba(255, 215, 0, 0); } }
        .history-card .history-title { font-size: 1.5rem; text-align: center; color: var(--primary); margin-bottom: 1rem; }
       /* ...existing CSS... */
.history-items {
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
    max-height: calc(2 * 60px + 1.6rem); /* 2 items + gap */
    overflow-y: auto;
    padding-right: 4px;
    box-sizing: border-box;
    /* Each .history-item is min-height: 60px; gap: 0.8rem between items */
}
/* ...existing CSS... */
        .history-item { background: rgba(0, 0, 0, 0.3); border: 1px solid var(--glass-border); border-radius: 8px; padding: 12px; display: flex; align-items: center; gap: 12px; min-height: 60px; font-size: 1rem; }
        .history-icon { font-size: 1.4rem; min-width: 35px; text-align: center; }
        .history-win { color: var(--success); } .history-loss { color: var(--danger); } .history-spins { color: var(--info); }
        .history-details { flex-grow: 1; } .history-outcome { font-weight: 600; font-size: 1rem; margin-bottom: 3px; }
        .history-value { font-weight: 500; color: var(--text-light); font-size: 0.9rem; }
        .history-amount { font-weight: 600; font-size: 1.1rem; min-width: 70px; text-align: right; }
        .history-amount.win { color: var(--success); } .history-amount.loss { color: var(--danger); } .history-amount.spins { color: var(--info); }
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
        @media (max-width: 1200px) { .game-grid { grid-template-columns: 1fr; } .controls-column { position: static; max-width: 600px; margin: 2rem auto 0; } }
        @media (max-width: 768px) { .game-grid { gap: 1.5rem; } .game-title-section h1 { font-size: 2.5rem; } .stats-grid { grid-template-columns: repeat(3, 1fr); gap: 1rem; } .stat-value { font-size: 1.5rem; } .roulette-container { max-width: 90vw; } }
        @media (max-width: 480px) { .game-wrapper { padding-left: 1rem; padding-right: 1rem; } .game-title-section h1 { font-size: 2rem; } .stat-value { font-size: 1.2rem; } .spin-buttons { grid-template-columns: 1fr; } }
    </style>
    
    <script>
        const serverData = {
            ticketBalance: <?php echo json_encode($initial_ticket_balance); ?>,
            csrfToken: "<?php echo htmlspecialchars($csrf_token); ?>"
        };
    </script>
</head>
<body>
    <button class="close-btn" id="closeBtn" onclick="window.location.href='../games.php'">&times;</button>

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
                        <input type="number" id="betInput" value="10" min="1" class="bet-input">
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
            <button class="close-btn" id="winModalCloseBtn">&times;</button>
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
                
                // State initialized from server
                this.ticketBalance = serverData.ticketBalance;
                this.csrfToken = serverData.csrfToken;
                this.currentBet = 10;
                this.freeSpins = 0;
                this.isSpinning = false;
                this.isAutoSpinning = false;
                this.history = [];
                this.currentRotation = 0;
                this.winningSliceIndex = null;
                
                // Wheel Config (must match server-side logic for visual representation)
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
                this.POINTER_ANGLE = 0; // 0 radians = right side

                this.bindDOM();
                this.addEventListeners();
                this.updateBet(this.currentBet);
                this.drawWheel(this.currentRotation, 0);
                this.updateUI();
            }

            bindDOM() {
                this.balanceDisplay = document.getElementById('balanceDisplay');
                this.lastWinDisplay = document.getElementById('lastWinDisplay');
                this.freeSpinsDisplay = document.getElementById('freeSpinsDisplay');
                this.historyItemsEl = document.getElementById('historyItems');
                this.betInput = document.getElementById('betInput');
                this.quickBetBtns = document.querySelectorAll('.quick-bet-btn');
                this.spinBtn = document.getElementById('spinBtn');
                this.autoSpinBtn = document.getElementById('autoSpinBtn');
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
            }

            handleQuickBet(e) {
                const betValue = e.target.id === 'maxBetBtn' ? this.ticketBalance : parseInt(e.target.dataset.bet);
                this.updateBet(betValue);
            }

            updateBet(amount) {
                if (isNaN(amount) || amount < 1) amount = 1;
                if (amount > this.ticketBalance && this.freeSpins === 0) {
                    amount = this.ticketBalance > 0 ? this.ticketBalance : 1;
                }
                this.currentBet = amount;
                this.betInput.value = this.currentBet;
                this.updateUI();
            }
            
            getSliceIndexByOutcome(outcome) {
                const matchingSlices = [];
                this.wheelSections.forEach((section, index) => {
                    if (section.outcome === outcome) matchingSlices.push(index);
                });
                if (matchingSlices.length === 0) return 1; // Default to a LOSE slice
                return matchingSlices[Math.floor(Math.random() * matchingSlices.length)];
            }
            
            drawWheel(rotation, elapsedTime) {
                const radius = this.canvas.width / 2;
                this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
                this.ctx.save();
                this.ctx.translate(radius, radius);
                this.ctx.rotate(this.POINTER_ANGLE);
                this.wheelSections.forEach((section, i) => {
                    const startAngle = (i * this.sectionAngle) + rotation;
                    const endAngle = startAngle + this.sectionAngle;
                    this.ctx.beginPath();
                    this.ctx.moveTo(0, 0);
                    this.ctx.arc(0, 0, radius, startAngle, endAngle);
                    this.ctx.closePath();
                    this.ctx.fillStyle = section.color;
                    this.ctx.fill();
                    if (!this.isSpinning && this.winningSliceIndex === i) {
                        const glowIntensity = Math.abs(Math.sin(elapsedTime / 300));
                        this.ctx.fillStyle = `rgba(255, 255, 255, ${0.2 + glowIntensity * 0.2})`;
                        this.ctx.shadowColor = 'white';
                        this.ctx.shadowBlur = 20 + glowIntensity * 10;
                        this.ctx.fill();
                        this.ctx.shadowBlur = 0;
                    }
                    this.ctx.beginPath();
                    this.ctx.moveTo(0, 0);
                    this.ctx.arc(0, 0, radius, startAngle, endAngle);
                    this.ctx.closePath();
                    this.ctx.strokeStyle = '#1a1a1a';
                    this.ctx.lineWidth = 2;
                    this.ctx.stroke();
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
                
                const isFreeSpin = this.freeSpins > 0;
                
                if (!isFreeSpin && this.currentBet > this.ticketBalance) {
                    this.showWinModal({ outcome: 'Error', value: 0, type: 'error' }, 0, 'error', 'Insufficient ticket balance!');
                    return;
                }

                this.isSpinning = true;
                this.winningSliceIndex = null;
                this.updateUI();

                // Decrement free spins if using one
                if (isFreeSpin) {
                    this.freeSpins -= 1;
                }

                // Prepare form data
                const formData = new FormData();
                formData.append('betAmount', this.currentBet);
                formData.append('csrf_token', this.csrfToken);
                formData.append('freeSpinsUsed', isFreeSpin ? '1' : '0');

                fetch('safariRoulette.php', { 
                    method: 'POST', // <-- ensure POST method
                    body: new URLSearchParams(formData) 
                })
                .then(response => response.json())
                .then(data => {
                    // --- SYNC TICKET BALANCE WITH SERVER IMMEDIATELY ---
                    if (typeof data.newBalance === 'number') {
                        this.ticketBalance = data.newBalance;
                        this.updateUI(); // reflect new balance right away
                    }
                    // Use the server-provided winningSliceIndex if present
                    if (typeof data.winningSliceIndex === 'number') {
                        this.winningSliceIndex = data.winningSliceIndex;
                    } else if (data.outcome) {
                        // fallback: pick a random matching slice for the outcome
                        this.winningSliceIndex = this.getSliceIndexByOutcome(data.outcome);
                    }
                    this.animateWheel(data);
                })
                .catch(error => {
                    this.showWinModal({ outcome: 'Error', type: 'error' }, 0, 'error', 'Network error. Please try again.');
                });
            }
            
            animateWheel(serverResult) {
                // Use the server-provided winningSliceIndex for animation
                const targetSliceIndex = (typeof serverResult.winningSliceIndex === 'number')
                    ? serverResult.winningSliceIndex
                    : this.getSliceIndexByOutcome(serverResult.outcome);
                const spinDuration = 3000 + Math.random() * 2000;
                const baseRotations = 8 + Math.random() * 4;
                const startTime = Date.now();
                const startRotation = this.currentRotation;
                const targetAngle = (targetSliceIndex * this.sectionAngle) + (this.sectionAngle / 2);
                const targetRotation = this.POINTER_ANGLE - targetAngle;
                const totalRotation = (baseRotations * 2 * Math.PI) + targetRotation;

                const animate = () => {
                    const elapsed = Date.now() - startTime;
                    const progress = Math.min(elapsed / spinDuration, 1);
                    const easeOut = 1 - Math.pow(1 - progress, 3);
                    this.currentRotation = startRotation + (totalRotation * easeOut);
                    this.drawWheel(this.currentRotation, elapsed);
                    if (progress < 1) {
                        requestAnimationFrame(animate);
                    } else {
                        // Always use the slice at the pointer (targetSliceIndex) as the result
                        this.handleSpinResult(serverResult, targetSliceIndex);
                    }
                };
                animate();
            }

            handleSpinResult(result, sliceIndex) {
                this.isSpinning = false;

                // --- Determine the actual winning slice by pointer position ---
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

                // Calculate outcome for display only (do not change ticketBalance here)
                let payout = 0;
                let spinsWon = 0;
                let displayAmount = '';
                if (section.type === 'multiplier') {
                    payout = this.currentBet * section.value;
                    displayAmount = `+${payout}`;
                } else if (section.type === 'spins') {
                    spinsWon = section.value;
                    this.freeSpins += spinsWon;
                    displayAmount = `+${spinsWon} SPIN${spinsWon > 1 ? 'S' : ''}`;
                } else {
                    // For losses, just show 0 (no deduction shown, since balance is already updated)
                    displayAmount = '0';
                }

                this.history.unshift({
                    outcome: section.outcome,
                    type: section.type,
                    displayAmount: displayAmount,
                    payout: payout,
                    spinsWon: spinsWon
                });
                if (this.history.length > 20) this.history.pop();

                // --- Always use the server's ticket balance ---
                if (typeof result.newBalance === 'number') {
                    this.ticketBalance = result.newBalance;
                }
                this.updateUI();
                this.showWinModal(section, payout, section.type);

                if (this.isAutoSpinning) {
                    setTimeout(() => { if (this.isAutoSpinning) this.spin(); }, 2000);
                }
                this.startGlowAnimation();
            }
            
            startGlowAnimation() {
                const startTime = Date.now();
                const animate = () => {
                    if (this.isSpinning || this.winningSliceIndex === null) return;
                    const elapsed = Date.now() - startTime;
                    this.drawWheel(this.currentRotation, elapsed);
                    if (elapsed < 5000) { requestAnimationFrame(animate); } 
                    else { this.winningSliceIndex = null; this.drawWheel(this.currentRotation, 0); }
                };
                animate();
            }
            
            showWinModal(section, winAmount, type, customMessage = '') {
                if (customMessage) {
                    this.winModalTitle.textContent = 'Error';
                    this.winModalAmount.textContent = customMessage;
                    this.winModal.className = 'win-modal loss';
                    this.winModalOverlay.classList.add('active');
                    return;
                }

                let title, message;
                if (type === 'multiplier' && winAmount > 0) {
                    title = 'WIN!'; message = `You won ${winAmount} Tickets!`;
                    this.winModal.className = 'win-modal'; this.createConfetti();
                } else if (type === 'spins') {
                    title = 'FREE SPINS!'; message = `You got ${section.value} free spin${section.value > 1 ? 's' : ''}!`;
                    this.winModal.className = 'win-modal'; this.createConfetti();
                } else {
                    title = 'TRY AGAIN'; message = `You lost ${this.currentBet} Tickets`;
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
            
            toggleAutoSpin() {
                this.isAutoSpinning = !this.isAutoSpinning;
                this.autoSpinBtn.classList.toggle('active', this.isAutoSpinning);
                this.autoSpinBtn.textContent = this.isAutoSpinning ? 'Stop Auto' : 'Auto Spin';
                if (this.isAutoSpinning && !this.isSpinning) this.spin();
            }
        
            updateUI() {
                this.balanceDisplay.textContent = this.ticketBalance.toLocaleString();
                this.freeSpinsDisplay.textContent = this.freeSpins.toString();

                // Find the last win (either multiplier win or free spins)
                const lastWin = this.history.find(h => h.type === 'multiplier' && h.payout > 0);
                const lastFreeSpins = this.history.find(h => h.type === 'spins' && h.spinsWon > 0);
                
                // Show either the last ticket win or free spins won
                if (lastWin) {
                    this.lastWinDisplay.textContent = `+${lastWin.payout}`;
                } else if (lastFreeSpins) {
                    this.lastWinDisplay.textContent = `+${lastFreeSpins.spinsWon} SPIN${lastFreeSpins.spinsWon > 1 ? 'S' : ''}`;
                } else {
                    this.lastWinDisplay.textContent = '0';
                }

                // Rest of the updateUI method remains the same...
                this.historyItemsEl.innerHTML = '';
                this.history.slice(0, 20).forEach(item => {
                    const historyItem = document.createElement('div');
                    historyItem.className = 'history-item';
                    let iconClass, iconEmoji, amountClass, outcomeText;
                    if (item.type === 'multiplier') {
                        const isWin = item.payout > 0;
                        [iconClass, iconEmoji, amountClass, outcomeText] = [
                            isWin ? 'history-win' : 'history-loss',
                            isWin ? '<i class="fas fa-coins"></i>' : '<i class="fas fa-times-circle"></i>',
                            isWin ? 'win' : 'loss',
                            isWin ? 'Win' : 'Loss'
                        ];
                    } else if (item.type === 'spins') {
                        [iconClass, iconEmoji, amountClass, outcomeText] = [
                            'history-spins',
                            '<i class="fas fa-sync-alt"></i>',
                            'spins',
                            'Free Spins'
                        ];
                    } else {
                        [iconClass, iconEmoji, amountClass, outcomeText] = [
                            'history-loss',
                            '<i class="fas fa-times-circle"></i>',
                            'loss',
                            'Loss'
                        ];
                    }

                    historyItem.innerHTML = `
                        <div class="history-icon ${iconClass}">${iconEmoji}</div>
                        <div class="history-details">
                            <div class="history-outcome">${outcomeText}</div>
                            <div class="history-value">${new Date().toLocaleTimeString()}</div>
                        </div>
                        <div class="history-amount ${amountClass}">${item.displayAmount}</div>
                    `;
                    this.historyItemsEl.appendChild(historyItem);
                });

                // Enable/disable spin buttons
                const canSpin = (this.ticketBalance >= this.currentBet || this.freeSpins > 0) && !this.isSpinning;
                this.spinBtn.disabled = !canSpin;
                this.autoSpinBtn.disabled = !canSpin;
            }
        }

        const game = new SafariRoulette();
        document.getElementById('winModalOverlay').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) game.closeWinModal();
        });
    });
    </script>
</body>
</html>