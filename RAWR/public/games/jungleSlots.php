<?php
require_once __DIR__ . '/../../backend/inc/init.php';
userOnly();

$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

$rawrBalance = number_format($user['rawr_balance'], 2);
$ticketBalance = (int)$user['ticket_balance'];

// Handle spin action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['spin'])) {
    $betAmount = (int)$_POST['bet'];
    
    // Validate bet
    if ($betAmount <= 0 || $betAmount > $user['ticket_balance']) {
        $_SESSION['error'] = "Invalid bet amount or insufficient tickets";
        header("Location: jungleSlots.php");
        exit;
    }

    // Deduct bet from tickets
    $newTicketBalance = $user['ticket_balance'] - $betAmount;
    
    // Generate random result
    $symbols = ['🦁', '💎', '👑', '🐵', '🐗', '🐯'];
    $resultIndexes = [
        rand(0, count($symbols)-1),
        rand(0, count($symbols)-1),
        rand(0, count($symbols)-1)
    ];
    
    // Calculate payout (IN TICKETS)
    $payout = 0;
    $outcome = 'loss';
    
    if ($resultIndexes[0] === 0 && $resultIndexes[1] === 0 && $resultIndexes[2] === 0) { // JACKPOT
        $payout = 50000;
        $outcome = 'win';
    } elseif ($resultIndexes[0] === $resultIndexes[1] && $resultIndexes[1] === $resultIndexes[2]) {
        $payout = $betAmount * [1000, 500, 300, 200, 150][$resultIndexes[0]];
        $outcome = 'win';
    } elseif ($resultIndexes[0] === 0 && $resultIndexes[1] === 0) {
        $payout = $betAmount * 50;
        $outcome = 'win';
    } elseif ($resultIndexes[0] === 0 || $resultIndexes[1] === 0 || $resultIndexes[2] === 0) {
        $payout = $betAmount * 5;
        $outcome = 'win';
    }

    // Update TICKET balance if won
    if ($payout > 0) {
        $newTicketBalance += $payout;
    }

    // Update user balance in database
    $db->executeQuery(
        "UPDATE users SET ticket_balance = ? WHERE id = ?",
        [$newTicketBalance, $userId]
    );
    
    // Refresh user data after update
    $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

    // Record game result
    $db->executeQuery(
        "INSERT INTO game_results (user_id, game_type_id, bet_amount, payout, outcome, game_details) 
        VALUES (?, ?, ?, ?, ?, ?)",
        [
            $userId,
            3, // Slot Machine ID
            $betAmount,
            $payout,
            $outcome,
            json_encode([
                'symbols' => [
                    $symbols[$resultIndexes[0]],
                    $symbols[$resultIndexes[1]],
                    $symbols[$resultIndexes[2]]
                ],
                'bet' => $betAmount
            ])
        ]
    );

    // Record casino spending
    $db->executeQuery(
        "INSERT INTO casino_spending (user_id, game_type_id, tickets_spent)
        VALUES (?, ?, ?)",
        [$userId, 3, $betAmount]
    );

    // Update session data
    $_SESSION['last_spin_result'] = [
        'symbols' => [
            $symbols[$resultIndexes[0]],
            $symbols[$resultIndexes[1]],
            $symbols[$resultIndexes[2]]
        ],
        'payout' => $payout,
        'new_balances' => [
            'rawr' => $user['rawr_balance'], // Use refreshed data
            'tickets' => $newTicketBalance
        ]
    ];

    header("Location: jungleSlots.php");
    exit;
}

// Refresh ticket balance after potential updates
$ticketBalance = (int)$user['ticket_balance'];
$lastResult = $_SESSION['last_spin_result'] ?? null;
unset($_SESSION['last_spin_result']);
?>
<span id="rawrBalance" style="display:none;"><?= $rawrBalance ?></span>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RAWR Casino - LuckyLion Slots</title>
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
        
        /* --- Close Button (from Safari Roulette) --- */
        .close-btn { position: fixed; top: 20px; right: 20px; background: rgba(255, 215, 0, 0.1); border: 1px solid var(--glass-border); color: var(--primary); width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; cursor: pointer; z-index: 101; transition: var(--transition); }
        .close-btn:hover { background: rgba(255,215,0,0.2); transform: scale(1.1) rotate(90deg); }

        /* --- Main Layout --- */
        .game-container { padding: 120px 2rem 80px; max-width: 1200px; margin: 0 auto; display: flex; flex-direction: column; gap: 2rem; }
        .main-flex-row { display: flex; flex-direction: row; gap: 2rem; align-items: flex-start; justify-content: center; width: 100%; }
        .slot-machine-container { max-width: 600px; width: 100%; flex: 1 1 350px; }
        .slot-controls-wrap { display: flex; flex-direction: column; gap: 2rem; flex: 1 1 350px; min-width: 320px; max-width: 400px; }
        .bottom-flex-row { display: flex; flex-direction: row; gap: 2rem; align-items: flex-start; justify-content: center; width: 100%; }
        .paytable, .history-card { flex: 1 1 320px; max-width: 600px; min-width: 0; }
        @media (max-width: 1100px) {
            .main-flex-row, .bottom-flex-row { flex-direction: column; align-items: center; }
            .slot-controls-wrap, .slot-machine-container, .paytable, .history-card { max-width: 600px; width:100%; }
        }

        /* --- Improved Mobile Responsiveness for jungleSlots.php --- */
        @media (max-width: 900px) {
    .game-container {
        padding: 90px 0.5rem 40px;
        gap: 1.2rem;
    }
    .main-flex-row,
    .bottom-flex-row {
        flex-direction: column;
        gap: 1.2rem;
        align-items: stretch;
    }
    .slot-machine-container,
    .slot-controls-wrap,
    .paytable,
    .history-card {
        max-width: 100%;
        min-width: 0;
        width: 100%;
    }
    .slot-controls-wrap {
        margin-top: 1.2rem;
    }
}

@media (max-width: 600px) {
    .game-header {
        margin-bottom: 1rem;
    }
    .game-title {
        font-size: 2rem;
    }
    .game-subtitle {
        font-size: 1rem;
    }
    .stats-grid {
        grid-template-columns: 1fr;
        gap: 0.7rem;
        margin-bottom: 1.2rem;
    }
    .stat-card {
        padding: 1rem;
        font-size: 1rem;
    }
    .stat-label {
        font-size: 0.9rem;
    }
    .stat-value {
        font-size: 1.2rem;
    }
    .slot-machine-container {
        max-width: 100%;
        min-width: 0;
    }
    #slotCanvas {
        width: 100% !important;
        height: auto !important;
        min-width: 0;
    }
    .slot-controls-wrap {
        padding: 0.5rem 0;
        gap: 1rem;
    }
    .controls {
        padding: 1rem 0.5rem;
    }
    .bet-label {
        font-size: 1rem;
        margin-bottom: 0.7rem;
    }
    .bet-input {
        font-size: 1.1rem;
        padding: 0.5rem;
    }
    .quick-bet-buttons {
        grid-template-columns: repeat(3, 1fr);
        gap: 0.3rem;
        margin-top: 0.5rem;
    }
    .quick-bet-btn {
        font-size: 0.95rem;
        padding: 0.5rem;
    }
    .spin-buttons {
        gap: 0.7rem;
        margin-top: 1rem;
    }
    .spin-btn, .auto-spin-btn {
        font-size: 1rem;
        padding: 0.7rem;
    }
    .paytable {
        padding: 1rem 0.5rem;
    }
    .paytable-title {
        font-size: 1.2rem;
        margin-bottom: 1rem;
    }
    .paytable-grid {
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
    }
    .paytable-item {
        padding: 0.7rem;
        font-size: 0.95rem;
    }
    .paytable-symbols {
        font-size: 1.2rem;
        margin-bottom: 0.5rem;
    }
    .paytable-payout {
        font-size: 1rem;
    }
    .history-card {
        padding: 1rem 0.5rem;
        margin-top: 1rem;
    }
    .history-title {
        font-size: 1rem;
    }
    .history-items {
        max-height: 120px;
        gap: 0.3rem;
        padding-right: 2px;
    }
    .history-item {
        font-size: 0.95rem;
        min-height: 38px;
        padding: 7px;
        gap: 7px;
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

        /* --- Game Header & Stats --- */
        .game-header { text-align: center; margin-bottom: 1.5rem; }
        .game-title { font-size: 2.8rem; background: linear-gradient(to right, var(--primary), var(--secondary)); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; text-shadow: 0 0 20px rgba(255, 215, 0, 0.2); margin-bottom: 0.5rem; }
        .game-subtitle { font-size: 1.2rem; color: var(--text-muted); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: var(--glass-bg); backdrop-filter: blur(10px); border: 1px solid var(--glass-border); border-radius: var(--border-radius); padding: 1.5rem; text-align: center; transition: var(--transition); box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2); border: 2px solid rgba(251, 191, 36, 0.3); }
        .stat-card:hover { border-color: var(--primary); transform: translateY(-5px); box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3), var(--glow); }
        .stat-label { color: var(--primary); font-size: 0.95rem; margin-bottom: 0.5rem; font-weight: 500; }
        .stat-value { font-size: 1.6rem; font-weight: 600; color: var(--primary); text-shadow: 0 0 10px rgba(251, 191, 36, 0.5); }
        
        /* --- Slot machine --- */
        .slot-machine-container { position: relative; margin: 0 auto; border-radius: 20px; border: 4px solid var(--primary); box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5), inset 0 2px 4px rgba(255, 255, 255, 0.1), 0 0 30px rgba(251, 191, 36, 0.3); background: linear-gradient(135deg, #1e293b, #334155, #475569); overflow: hidden; max-width: 600px; border: 2px solid rgba(251, 191, 36, 0.3); }
        #slotCanvas { display: block; width: 100%; height: auto; position: relative; z-index: 2; border-radius: 20px; border: 2px solid rgba(251, 191, 36, 0.3); }

        /* --- Controls --- */
        .bet-label { font-size: 1.2rem; font-weight: 600; text-align: center; margin-bottom: 1rem; color: var(--primary); }
        .bet-input { font-family: 'Poppins', sans-serif; font-size: 2rem; font-weight: 700; color: var(--primary); background: rgba(0,0,0,0.4); border: 2px solid var(--glass-border); border-radius: 8px; text-align: center; width: 100%; padding: 1rem; outline: none; }
        .quick-bet-buttons { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; margin-top: 1rem; }
        .quick-bet-btn { background: rgba(255, 215, 0, 0.1); border: 1px solid var(--glass-border); color: var(--primary); padding: 0.75rem; border-radius: 8px; cursor: pointer; transition: background 0.2s; }
        .quick-bet-btn:hover { background: rgba(255, 215, 0, 0.2); }
        .spin-buttons { display: flex; flex-direction:column; gap: 1rem; margin-top: 1.5rem; }
        .spin-btn, .auto-spin-btn { border: none; padding: 1rem; border-radius: 8px; font-size: 1.1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; }
        .spin-btn { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: #1a1a1a; }
        .auto-spin-btn { background: transparent; border: 1px solid var(--primary); color: var(--primary); }
        .auto-spin-btn.active { background: var(--primary); color: #1a1a1a; animation: pulse-gold 2s infinite; }
        .spin-btn:disabled, .auto-spin-btn:disabled { background: #555 !important; border-color: #555 !important; color: #999 !important; cursor: not-allowed; animation: none; }
        @keyframes pulse-gold { 0% { box-shadow: 0 0 0 0 rgba(255, 215, 0, 0.7); } 100% { box-shadow: 0 0 0 10px rgba(255, 215, 0, 0); } }

        /* --- History --- */
        .history-card .history-title { font-size: 1.5rem; text-align: center; color: var(--primary); margin-bottom: 1rem; }
        .history-items { display: flex; flex-direction: column; gap: 0.8rem; max-height: 300px; overflow-y: auto; padding-right: 4px; }
        .history-item { background: rgba(0, 0, 0, 0.3); border: 1px solid var(--glass-border); border-radius: 8px; padding: 12px; display: flex; align-items: center; gap: 12px; min-height: 60px; font-size: 1rem; }
        .history-icon { font-size: 1.4rem; min-width: 35px; text-align: center; }
        .history-win { color: var(--success); } .history-loss { color: var(--danger); }
        .history-details { flex-grow: 1; } .history-outcome { font-weight: 600; font-size: 1rem; margin-bottom: 3px; }
        .history-value { font-weight: 500; color: var(--text-light); font-size: 0.9rem; }
        .history-amount { font-weight: 600; font-size: 1.1rem; min-width: 80px; text-align: right; }
        .history-amount.win { color: var(--success); } .history-amount.loss { color: var(--danger); }
        
        /* --- Paytable --- */
        .paytable { background: var(--glass-bg); border: 1.5px solid var(--glass-border); border-radius: 16px; padding: 1.5rem; backdrop-filter: blur(5px); max-width: 600px; margin: 0 auto; width: 100%; border: 2px solid rgba(251, 191, 36, 0.3); }
        .paytable-title { color: var(--primary); font-size: 1.8rem; text-align: center; margin: 0 0 1.5rem 0; text-shadow: 0 0 10px rgba(251, 191, 36, 0.5); }
        .paytable-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; }
        .paytable-item { background: rgba(255,255,255,0.08); border: 1px solid var(--glass-border); border-radius: 12px; padding: 1rem; }
        .paytable-item:hover { border-color: var(--primary); background: rgba(255, 215, 0, 0.08); }
        .paytable-symbols { font-size: 1.8rem; margin-bottom: 0.8rem; }
        .paytable-payout { color: var(--primary); font-size: 1.1rem; font-weight: 600; }
        
        /* --- Win Modal (from Safari Roulette) --- */
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

        /* New Stop Auto Button */
        .stop-auto-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(255, 0, 0, 0.8);
            color: white;
            border: none;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            font-size: 1.2rem;
            cursor: pointer;
            z-index: 1000;
            display: none;
            box-shadow: 0 4px 20px rgba(255, 0, 0, 0.5);
            transition: all 0.3s ease;
            animation: pulse-red 2s infinite;
        }
        .stop-auto-btn:hover {
            background: rgba(255, 0, 0, 1);
            transform: scale(1.1);
        }
        @keyframes pulse-red {
            0% { box-shadow: 0 0 0 0 rgba(255, 0, 0, 0.7); }
            70% { box-shadow: 0 0 0 15px rgba(255, 0, 0, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 0, 0, 0); }
        }
        .stop-auto-btn.active {
            display: block;
        }
        .stop-auto-btn i {
            font-size: 24px;
        }

    </style>
</head>
<body>
    <!-- Close Button -->
    <button class="close-btn" onclick="window.location.href='../games.php'">&times;</button>
    
    <!-- Stop Auto Spin Button -->
    <button class="stop-auto-btn" id="stopAutoBtn" title="Stop Auto Spin">
        <i class="fas fa-stop"></i>
    </button>
    
    <div class="game-container">
        <div class="game-header">
            <h1 class="game-title">🦁 Jungle Slots 🎰</h1>
            <p class="game-subtitle">Spin to win big with Leo the Lion!</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Ticket Balance</div>
                <div class="stat-value" id="ticketBalance"><?= $ticketBalance ?> 🎫</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Last Win</div>
                <div class="stat-value" id="lastWin">0 🦁</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Jackpot</div>
                <div class="stat-value" id="jackpot">50,000 💎</div>
            </div>
        </div>
        
        <div class="main-flex-row">
            <div class="slot-machine-container">
                <canvas id="slotCanvas" width="600" height="300"></canvas>
            </div>
            
            <div class="slot-controls-wrap">
                <form method="POST" class="controls glass-card" id="slotForm">
                    <label for="betInput" class="bet-label">💰 Bet Amount (Tickets)</label>
                    <div class="bet-input-container">
                        <input type="number" id="betInput" name="bet" value="10" min="1" max="<?= $ticketBalance ?>" class="bet-input">
                    </div>
                    <div class="quick-bet-buttons">
                        <button type="button" class="quick-bet-btn" data-bet="10">10</button>
                        <button type="button" class="quick-bet-btn" data-bet="25">25</button>
                        <button type="button" class="quick-bet-btn" data-bet="50">50</button>
                        <button type="button" class="quick-bet-btn" data-bet="100">100</button>
                        <button type="button" class="quick-bet-btn" data-bet="250">250</button>
                        <button type="button" class="quick-bet-btn" id="maxBetBtn">MAX</button>
                    </div>
                    <div class="spin-buttons">
                        <button type="submit" name="spin" class="spin-btn" id="spinBtn">
                            <span id="spinBtnText">Spin</span>
                        </button>
                        <button type="button" class="auto-spin-btn" id="autoSpinBtn">
                            <span id="autoSpinBtnText">Auto Spin</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="bottom-flex-row">
            <div class="paytable">
                <h3 class="paytable-title">💰 Paytable - Win Multipliers 💰</h3>
                <div class="paytable-grid">
                    <div class="paytable-item"><div class="paytable-symbols">🦁🦁🦁</div><div class="paytable-payout">JACKPOT!</div></div>
                    <div class="paytable-item"><div class="paytable-symbols">💎💎💎</div><div class="paytable-payout">1000x</div></div>
                    <div class="paytable-item"><div class="paytable-symbols">👑👑👑</div><div class="paytable-payout">500x</div></div>
                    <div class="paytable-item"><div class="paytable-symbols">🐵🐵🐵</div><div class="paytable-payout">300x</div></div>
                    <div class="paytable-item"><div class="paytable-symbols">🐗🐗🐗</div><div class="paytable-payout">200x</div></div>
                    <div class="paytable-item"><div class="paytable-symbols">🐯🐯🐯</div><div class="paytable-payout">150x</div></div>
                    <div class="paytable-item"><div class="paytable-symbols">🦁🦁</div><div class="paytable-payout">50x</div></div>
                    <div class="paytable-item"><div class="paytable-symbols">🦁</div><div class="paytable-payout">5x</div></div>
                </div>
            </div>
            
            <div class="history-card glass-card">
                <h3 class="history-title">Spin History</h3>
                <div class="history-items" id="historyItems">
                    <!-- History items will be appended here -->
                </div>
            </div>
        </div>
    </div>

    <div class="win-modal-overlay" id="winModalOverlay">
        <div class="win-modal" id="winModal">
            <div class="confetti-container" id="confettiContainer"></div>
            <button class="close-btn" id="winModalCloseBtn">&times;</button>
            <h2 id="winModalTitle"></h2>
            <p id="winModalAmount"></p>
            <!-- Removed Stop Auto Spin button from modal -->
        </div>
    </div>

    <script>
document.addEventListener('DOMContentLoaded', () => {
    new LuckyLionSlots();
});

class LuckyLionSlots {
    constructor() {
        this.canvas = document.getElementById('slotCanvas');
        this.ctx = this.canvas.getContext('2d');
        this.ctx.textAlign = 'center';
        this.ctx.textBaseline = 'middle';
        this.ctx.font = 'bold 70px Arial';

        this.ticketBalance = parseInt(document.getElementById('ticketBalance').textContent);
        this.currentBet = 10;
        this.jackpotAmount = 50000;
        this.isSpinning = false;
        this.isAutoSpinning = false;
        this.reels = [[], [], []];
        this.winAmount = 0;
        this.history = [];

        this.SYMBOL_HEIGHT = 100;
        this.REEL_WIDTH = this.canvas.width / 3;
        this.symbols = [
            { symbol: '🦁', weight: 1, color: '#ffec4b', value: 'lion' }, { symbol: '💎', weight: 15, color: '#a5f3fc', value: 'diamond' },
            { symbol: '👑', weight: 15, color: '#fde68a', value: 'crown' }, { symbol: '🐵', weight: 20, color: '#fda4af', value: 'circus' },
            { symbol: '🐗', weight: 25, color: '#d8b4fe', value: 'laugh' }, { symbol: '🐯', weight: 24, color: '#bef264', value: 'crazy' }
        ];

        this.bindDOM();
        this.initEventListeners();
        this.generateReels();
        this.drawReels();
        this.updateUI();
    }

    bindDOM() {
        this.ticketBalanceEl = document.getElementById('ticketBalance');
        this.lastWinEl = document.getElementById('lastWin');
        this.jackpotEl = document.getElementById('jackpot');
        this.betInput = document.getElementById('betInput');
        this.spinBtn = document.getElementById('spinBtn');
        this.autoSpinBtn = document.getElementById('autoSpinBtn');
        this.historyItemsEl = document.getElementById('historyItems');
        this.form = document.getElementById('slotForm');
        this.winModalOverlay = document.getElementById('winModalOverlay');
        this.winModal = document.getElementById('winModal');
        this.winModalTitle = document.getElementById('winModalTitle');
        this.winModalAmount = document.getElementById('winModalAmount');
        this.winModalCloseBtn = document.getElementById('winModalCloseBtn');
        this.stopAutoBtn = document.getElementById('stopAutoBtn');
        // Removed: this.stopAutoSpinInModalBtn = document.getElementById('stopAutoSpinInModal');
    }

    initEventListeners() {
        document.querySelectorAll('.quick-bet-btn').forEach(btn => {
            if(btn.id === 'maxBetBtn') btn.addEventListener('click', () => this.updateBet(this.ticketBalance));
            else btn.addEventListener('click', () => this.updateBet(parseInt(btn.dataset.bet)));
        });
        this.betInput.addEventListener('input', () => this.updateBet(parseInt(this.betInput.value) || 1));
        this.form.addEventListener('submit', (e) => { e.preventDefault(); if(!this.isSpinning && !this.isAutoSpinning) this.spin(); });
        this.autoSpinBtn.addEventListener('click', (e) => { e.preventDefault(); if(!this.isSpinning) this.toggleAutoSpin(); });
        this.winModalCloseBtn.addEventListener('click', () => this.closeWinModal());
        this.winModalOverlay.addEventListener('click', (e) => { if (e.target === e.currentTarget) this.closeWinModal(); });
        this.stopAutoBtn.addEventListener('click', () => this.stopAutoSpin());
        // Removed: this.stopAutoSpinInModalBtn.addEventListener('click', () => this.stopAutoSpin());
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.code === 'Space') {
                e.preventDefault();
                if (!this.isSpinning && !this.isAutoSpinning) {
                    this.toggleAutoSpin();
                }
            }
            if (e.code === 'Escape') {
                this.stopAutoSpin();
            }
        });
    }

    updateBet(amount = 10) {
        if (isNaN(amount) || amount < 1) amount = 1;
        if (amount > this.ticketBalance) amount = this.ticketBalance > 0 ? this.ticketBalance : 1;
        this.currentBet = amount;
        this.betInput.value = this.currentBet;
        this.updateUI();
    }

    toggleAutoSpin() {
        this.isAutoSpinning = !this.isAutoSpinning;
        this.autoSpinBtn.classList.toggle('active', this.isAutoSpinning);
        this.autoSpinBtn.querySelector('#autoSpinBtnText').textContent = this.isAutoSpinning ? 'Stop Auto' : 'Auto Spin';
        this.stopAutoBtn.classList.toggle('active', this.isAutoSpinning);
        
        if (this.isAutoSpinning && !this.isSpinning) {
            this.spin();
        }
    }

    stopAutoSpin() {
        if (this.isAutoSpinning) {
            this.isAutoSpinning = false;
            this.autoSpinBtn.classList.remove('active');
            this.autoSpinBtn.querySelector('#autoSpinBtnText').textContent = 'Auto Spin';
            this.stopAutoBtn.classList.remove('active');
            // Removed: this.stopAutoSpinInModalBtn.style.display = 'none';
        }
    }

    generateReels() {
        for(let i = 0; i < 3; i++) {
            this.reels[i] = [];
            for(let j = 0; j < 4; j++) this.reels[i].push(this.getRandomSymbol());
        }
    }

    getRandomSymbol() {
        const totalWeight = this.symbols.reduce((sum, symbol) => sum + symbol.weight, 0);
        let random = Math.floor(Math.random() * totalWeight);
        for(const symbol of this.symbols) {
            if(random < symbol.weight) return symbol;
            random -= symbol.weight;
        }
        return this.symbols[0];
    }

    drawReels(isWinning = false) {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        for(let i = 0; i < 3; i++) {
            this.ctx.fillStyle = 'rgba(0, 0, 0, 0.3)';
            this.ctx.fillRect(i * this.REEL_WIDTH, 0, this.REEL_WIDTH, this.canvas.height);
            this.ctx.strokeStyle = 'rgba(251, 191, 36, 0.5)';
            this.ctx.lineWidth = 4;
            this.ctx.strokeRect(i * this.REEL_WIDTH, 0, this.REEL_WIDTH, this.canvas.height);
        }
        for(let i = 0; i < 3; i++) {
            for(let j = 0; j < 4; j++) {
                const symbol = this.reels[i][j];
                const x = i * this.REEL_WIDTH + this.REEL_WIDTH / 2;
                const y = j * this.SYMBOL_HEIGHT - (this.reelPositions ? (this.reelPositions[i] || 0) : 0) + this.SYMBOL_HEIGHT/2;

                if (isWinning && j === 1) { // Middle row
                    const timePassed = Date.now() - this.winAnimationStartTime;
                    const pulse = Math.abs(Math.sin(timePassed / 200));
                    this.ctx.shadowColor = symbol.color;
                    this.ctx.shadowBlur = 20 + pulse * 20;
                }
                this.ctx.fillStyle = symbol.color;
                this.ctx.fillText(symbol.symbol, x, y);
                this.ctx.shadowColor = 'transparent';
                this.ctx.shadowBlur = 0;
            }
        }
    }

    spin() {
        if(this.isSpinning || this.ticketBalance < this.currentBet) {
            if (this.isAutoSpinning) this.stopAutoSpin();
            return;
        }
        this.isSpinning = true;
        this.updateUI();

        fetch('../../backend/games/spin_jungleslots.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'bet=' + encodeURIComponent(this.currentBet)
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) throw new Error(data.error || 'Spin failed.');
            this.backendSpinResult = data;
            this.startSpinAnimationWithResult(data.symbols);
        })
        .catch(err => {
            this.isSpinning = false;
            this.stopAutoSpin();
            this.updateUI();
            this.showWinModal(false, 0, 'Error', err.message);
        });
    }

    startSpinAnimationWithResult(targetSymbols) {
        this.reelSpeeds = [Math.random() * 8 + 28, Math.random() * 8 + 28, Math.random() * 8 + 28];
        this.reelPositions = [0, 0, 0];
        this.spinningReels = [true, true, true];
        const now = Date.now();
        this.reelStopTimes = [ now + 1200, now + 1800, now + 2400 ];
        this._targetSymbols = targetSymbols;
        this.animateSpin();
    }

    animateSpin() {
        const currentTime = Date.now();
        let allReelsStopped = true;
        for(let i = 0; i < 3; i++) {
            if(this.spinningReels[i]) {
                allReelsStopped = false;
                if(currentTime >= this.reelStopTimes[i]) {
                    this.reelSpeeds[i] *= 0.92;
                    if(this.reelSpeeds[i] < 2) {
                        this.spinningReels[i] = false;
                        this.reelPositions[i] = 0;
                        this.reels[i][1] = this.symbols.find(s => s.symbol === this._targetSymbols[i]);
                        for (let j = 0; j < 4; j++) { if (j !== 1) this.reels[i][j] = this.getRandomSymbol(); }
                    }
                }
                this.reelPositions[i] += this.reelSpeeds[i];
                if(this.reelPositions[i] >= this.SYMBOL_HEIGHT) {
                    this.reelPositions[i] %= this.SYMBOL_HEIGHT;
                    this.reels[i].unshift(this.getRandomSymbol());
                    this.reels[i].pop();
                }
            }
        }
        this.drawReels();
        if(allReelsStopped) setTimeout(() => this.spinComplete(), 200);
        else requestAnimationFrame(() => this.animateSpin());
    }

    spinComplete() {
        this.isSpinning = false;
        if (this.backendSpinResult) {
            this.winAmount = this.backendSpinResult.payout;
            this.ticketBalance = this.backendSpinResult.new_ticket_balance;
            this.lastWinEl.textContent = `${this.winAmount.toLocaleString()} 🎫`;
            
            const isWin = this.winAmount > 0;
            this.showWinModal(isWin, this.winAmount);
            if (isWin) this.startGlowAnimation();
        }
        this.addToHistory(this.winAmount > 0, this.winAmount);
        this.updateUI();
        if (this.isAutoSpinning) {
            if (this.ticketBalance >= this.currentBet) setTimeout(() => this.spin(), 2000);
            else this.stopAutoSpin();
        }
    }

    startGlowAnimation() {
        this.winAnimationStartTime = Date.now();
        const animate = () => {
            if (Date.now() - this.winAnimationStartTime > 4000) {
                this.drawReels(false); // Stop glowing
                return;
            }
            this.drawReels(true); // Continue glowing
            requestAnimationFrame(animate);
        };
        animate();
    }

    showWinModal(isWin, payout, title = '', customMessage = '') {
        let modalTitle, modalMessage;
        
        if (title === 'Error') {
            modalTitle = 'Error';
            modalMessage = customMessage;
            this.winModal.className = 'win-modal loss';
        } else if (isWin) {
            modalTitle = 'WIN!';
            modalMessage = `You won ${payout.toLocaleString()} Tickets!`;
            this.winModal.className = 'win-modal';
            this.createConfetti();
        } else {
            modalTitle = 'TRY AGAIN';
            modalMessage = `You lost ${this.currentBet.toLocaleString()} Tickets`;
            this.winModal.className = 'win-modal loss';
        }
        
        this.winModalTitle.textContent = modalTitle;
        this.winModalAmount.textContent = modalMessage;
        // Removed: this.stopAutoSpinInModalBtn.style.display = this.isAutoSpinning ? 'block' : 'none';
        this.winModalOverlay.classList.add('active');
    }

    closeWinModal() {
        this.winModalOverlay.classList.remove('active');
        document.getElementById('confettiContainer').innerHTML = '';
        // Removed: this.stopAutoSpinInModalBtn.style.display = 'none';
    }

    createConfetti() {
        const container = document.getElementById('confettiContainer');
        container.innerHTML = '';
        const colors = ['#FFD700', '#FFA500', '#FF6B35', '#f0f0f0'];
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
    
    addToHistory(isWin, payout) {
        const symbols = this.reels.map(reel => reel[1].symbol).join(' ');
        if (this.history.length >= 15) this.history.pop();
        this.history.unshift({ symbols, win: isWin, amount: payout, bet: this.currentBet });
        this.renderHistory();
    }

    renderHistory() {
        this.historyItemsEl.innerHTML = '';
        this.history.forEach(item => {
            const historyItem = document.createElement('div');
            historyItem.className = 'history-item';
            const [iconClass, iconEmoji, amountClass, outcomeText, displayAmount] = item.win
                ? ['history-win', '<i class="fas fa-coins"></i>', 'win', 'Win', `+${item.amount.toLocaleString()}`]
                : ['history-loss', '<i class="fas fa-times-circle"></i>', 'loss', 'Loss', `-${item.bet.toLocaleString()}`];
            
            historyItem.innerHTML = `
                <div class="history-icon ${iconClass}">${iconEmoji}</div>
                <div class="history-details">
                    <div class="history-outcome">${outcomeText}</div>
                    <div class="history-value">${item.symbols}</div>
                </div>
                <div class="history-amount ${amountClass}">${displayAmount} 🎫</div>`;
            this.historyItemsEl.appendChild(historyItem);
        });
    }

    updateUI() {
        this.ticketBalanceEl.textContent = `${this.ticketBalance.toLocaleString()} 🎫`;
        this.jackpotEl.textContent = `${this.jackpotAmount.toLocaleString()} 💎`;
        this.betInput.value = this.currentBet;
        this.betInput.max = this.ticketBalance;
        
        const canSpin = !this.isSpinning && (this.ticketBalance >= this.currentBet);
        this.spinBtn.disabled = !canSpin;
        this.autoSpinBtn.disabled = this.isSpinning;
        if(this.isAutoSpinning && this.ticketBalance < this.currentBet) this.stopAutoSpin();
    }
}
    </script>
</body>
</html>