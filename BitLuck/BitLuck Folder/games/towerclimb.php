<?php
session_start();
define('MAX_LEVELS', 10);
$conn = new mysqli("localhost", "root", "", "ecasinosite");

if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

$userId = $_SESSION['user_id'];

// --- HELPER FUNCTIONS ---
function calculate_payout($level, $bet) {
    if ($level == 0) return 0;
    $multiplier = 1 + (0.5 * $level);
    return round($bet * $multiplier, 2);
}

function cash_out($userId, $level, $bet, $conn) {
    $payout = calculate_payout($level, $bet);
    if ($payout > 0) {
        $conn->query("UPDATE wallets SET token_balance = token_balance + $payout WHERE user_id = $userId");
        $conn->query("UPDATE casino_status SET token_balance = token_balance - $payout WHERE id = 1");
        $conn->query("INSERT INTO games (user_id, game_type, result, bet_amount) VALUES ($userId, 'Tower Climb', 'Cashed Out at Level $level', $bet)");
        $conn->query("INSERT INTO transactions (user_id, type, amount) VALUES ($userId, 'win', $payout)");
    }
    $_SESSION['tower_active'] = false;
    return $payout;
}

// --- GAME STATE INITIALIZATION & FORM HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $is_active_before_post = $_SESSION['tower_active'] ?? false;
    $bet_before_post = $_SESSION['tower_bet'] ?? 0;
    $level_before_post = $_SESSION['tower_level'] ?? 0;

    // START GAME
    if (isset($_POST['start_game'])) {
        $wallet = $conn->query("SELECT token_balance FROM wallets WHERE user_id = $userId")->fetch_assoc();
        $balance = $wallet['token_balance'];
        $bet_amount = floatval($_POST['bet_amount']);
        if ($bet_amount > 0 && $bet_amount <= $balance) {
            $_SESSION['tower_bet'] = $bet_amount;
            $_SESSION['tower_level'] = 0;
            $_SESSION['tower_active'] = true;
            $_SESSION['tower_message'] = ["text" => "Game started! You are at Level 1.", "type" => "info"];
        } else {
            $_SESSION['tower_message'] = ["text" => "Invalid bet or insufficient balance.", "type" => "lose"];
        }
    }
    // PICK TILE
    elseif (isset($_POST['choice']) && $is_active_before_post) {
        $losing_tile = rand(1, 3);
        if (intval($_POST['choice']) !== $losing_tile) {
            $_SESSION['tower_level']++;
            if ($_SESSION['tower_level'] === MAX_LEVELS) {
                $payout = cash_out($userId, $_SESSION['tower_level'], $bet_before_post, $conn);
                $_SESSION['tower_message'] = ["text" => "INCREDIBLE! You reached the top and won " . number_format($payout, 2) . "!", "type" => "win"];
            } else {
                $payout = calculate_payout($_SESSION['tower_level'], $bet_before_post);
                $_SESSION['tower_message'] = ["text" => "Correct! Advanced to Level " . ($_SESSION['tower_level'] + 1) . ". Payout: " . number_format($payout, 2), "type" => "win"];
            }
        } else {
            $conn->query("UPDATE wallets SET token_balance = token_balance - $bet_before_post WHERE user_id = $userId");
            $conn->query("UPDATE casino_status SET token_balance = token_balance + $bet_before_post WHERE id = 1");
            $conn->query("INSERT INTO games (user_id, game_type, result, bet_amount) VALUES ($userId, 'Tower Climb', 'Failed at Level $level_before_post', $bet_before_post)");
            $_SESSION['tower_active'] = false;
            $_SESSION['tower_message'] = ["text" => "Wrong tile! You lost " . number_format($bet_before_post, 2) . ".", "type" => "lose"];
        }
    }
    // CASHOUT
    elseif (isset($_POST['cashout']) && $is_active_before_post) {
        if ($level_before_post > 0) {
            $payout = cash_out($userId, $level_before_post, $bet_before_post, $conn);
            $_SESSION['tower_message'] = ["text" => "Cashed out! You won " . number_format($payout, 2) . ".", "type" => "win"];
        } else {
            $_SESSION['tower_message'] = ["text" => "You must climb at least one level to cash out.", "type" => "lose"];
        }
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$wallet = $conn->query("SELECT token_balance FROM wallets WHERE user_id = $userId")->fetch_assoc();
$balance = $wallet['token_balance'];
$is_active = $_SESSION['tower_active'] ?? false;
$level = $_SESSION['tower_level'] ?? 0;
$bet = $_SESSION['tower_bet'] ?? 0;
$potential_payout = $is_active ? calculate_payout($level, $bet) : 0;
$message_data = $_SESSION['tower_message'] ?? null;
unset($_SESSION['tower_message']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <title>Tower Climb | BitLuck</title>
    <link rel="icon" href="../images/TOWER-CLIMB-LOGO.png" type="image/png">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap');
        :root {
            --gold: #FFD700; --red: #D10000; --red-glow: rgba(209, 0, 0, 0.6);
            --black: #0A0A0A; --black-light: #121212; --white: #FFFFFF;
            --gray: #333; --green: #28a745; --blue: #007bff;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Montserrat', sans-serif; background-color: var(--black);
            color: var(--white); min-height: 100vh; padding: 20px;
            display: flex; align-items: center; justify-content: center;
        }
        .game-container {
            width: 100%; max-width: 900px; background-color: var(--black-light);
            border-radius: 16px; padding: 40px; border: 2px solid var(--gold);
            position: relative; padding-bottom: 100px;
        }
        h2 {
            font-weight: 700; color: var(--gold); font-size: 2rem; text-align: center;
            margin-bottom: 1rem; text-transform: uppercase;
        }
        h2::after {
            content: ''; display: block; width: 80px; height: 2px;
            background: var(--gold); margin: 10px auto 2rem;
        }
        .balance {
            font-size: 1.1rem; text-align: center; margin-bottom: 2rem;
            color: var(--white); font-weight: 600; background: var(--gray);
            padding: 12px 20px; border-radius: 50px; border: 1px solid var(--gold);
        }
        .balance i { color: var(--gold); margin-right: 8px; }
        .message-area { min-height: 60px; display: flex; align-items: center; justify-content: center; margin-bottom: 2rem; }
        .result {
            padding: 15px 25px; border-left: 4px solid; width: 100%;
            max-width: 550px; background-color: var(--gray); border-radius: 8px;
        }
        .result.win { border-color: var(--gold); }
        .result.lose { border-color: var(--red); }
        .result.info { border-color: var(--blue); }
        .result h3 {
            font-size: 1.1rem; font-weight: 700; text-transform: uppercase; text-align: center;
        }
        .result.win h3 { color: var(--gold); }
        .result.lose h3 { color: var(--red); }
        .result.info h3 { color: var(--blue); }
        .result h3 i { font-size: 1.3rem; margin-right: 8px; }
        
        .game-content {
            display: flex;
            gap: 2rem;
            margin-top: 2rem;
        }

        .tower-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;           
        }

        .tower {
            display: flex;
            flex-direction: column-reverse;
            gap: 0.5rem;
            width: 100%;
        }

        .tower-level {
            width: 100%;
            height: 40px !important;
            background-color: var(--gray);
            border: 2px solid var(--black);
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--white);
            font-weight: 600;
            transition: all 0.4s ease;
            padding: 0 1rem;
            font-size: 1rem !important;
        }
        .tower-level.active {
            background-color: var(--gold);
            border-color: var(--gold);
            color: var(--black);
            transform: scale(1.05);
            box-shadow: 0 0 15px var(--gold);
        }
        .tower-level.active .level-multiplier {
            color: var(--black);
        }
        .tower-level.completed {
            background-color: var(--green);
            border-color: #25a25a;
            opacity: 0.7;
        }
        .level-multiplier {
            font-weight: 700;
            color: var(--gold);
            font-size: 1rem;
        }

        .game-area { 
            flex: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.5rem;
        }
        .start-form { display: flex; flex-direction: column; align-items: center; gap: 1.5rem; }
        .bet-input-group label {
            display: block; font-size: 1rem; color: var(--white);
            font-weight: 600; margin-bottom: 1rem; text-transform: uppercase;
        }
        .bet-input-group label i { color: var(--gold); margin-right: 8px; }
        .bet-input-container { position: relative; }
        .bet-input-container i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--gold); }
        .bet-input-group input {
            background: var(--gray); border: 2px solid var(--gold); color: var(--white);
            padding: 12px 18px 12px 40px; font-size: 1.1rem; border-radius: 8px;
            width: 220px; text-align: center;
        }
        .bet-input-group input:focus { outline: none; box-shadow: 0 0 10px var(--gold); }
        .btn {
            padding: 12px 28px; font-size: 1rem; font-weight: 700;
            border-radius: 50px; cursor: pointer; text-transform: uppercase;
            border: 2px solid transparent; transition: all 0.3s ease; text-decoration: none;
            color: var(--white); display: inline-flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-action { background: var(--red); box-shadow: 0 0 15px var(--red-glow); }
        .btn-action:hover { transform: scale(1.05); }
        .btn-green { background: var(--green); box-shadow: 0 0 15px rgba(40, 167, 69, 0.6); }
        .btn-green:hover { transform: scale(1.05); }
        .btn-blue { background: var(--blue); box-shadow: 0 0 15px rgba(0, 123, 255, 0.6); }
        .btn-blue:hover { transform: scale(1.05); }

        .shine-effect {
            position: relative;
            overflow: hidden;
        }

        .shine-effect::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                to bottom right,
                rgba(255, 255, 255, 0) 0%,
                rgba(255, 255, 255, 0) 45%,
                rgba(255, 255, 255, 0.3) 48%,
                rgba(255, 255, 255, 0.3) 52%,
                rgba(255, 255, 255, 0) 55%,
                rgba(255, 255, 255, 0) 100%
            );
            transform: rotate(30deg);
            animation: shine 5s infinite;
        }

        @keyframes shine {
            0% { transform: rotate(30deg) translate(-30%, -30%); }
            100% { transform: rotate(30deg) translate(30%, 30%); }
        }

        .game-stats { display: flex; justify-content: center; gap: 2rem; margin-bottom: 1rem; font-size: 1.2rem; }
        .stat { background: var(--gray); padding: 10px 20px; border-radius: 8px; }
        .stat strong { color: var(--gold); }
        .tiles { display: flex; gap: 1rem; justify-content: center; margin-bottom: 1rem; }
        .tile-btn { width: 120px; height: 120px; font-size: 2rem; border-radius: 16px; }
        .cashout-form { margin-top: 1rem; }
        .cashout-form .btn { padding: 12px 40px; }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-top: 40px;
            color: var(--gold);
            text-decoration: none;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            padding: 12px 25px;
            border-radius: 50px;
            background: rgba(209, 0, 0, 0.3);
            border: 1px solid var(--gold);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .back-link::before {
            content: '\f104'; /* fa-angle-left */
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
        }
        .back-link:hover {
            background: rgba(209, 0, 0, 0.5);
            color: #FFECB3;
            transform: translateX(-5px);
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.5);
        }

        /* ===== RESPONSIVE DESIGN ===== */
        @media (max-width: 768px) {
            .game-container {
                padding: 30px 20px;
                margin: 10px;
                padding-bottom: 40px;
            }
            
            .game-content {
                flex-direction: column;
                align-items: center;
            }

            h2 {
                font-size: 2.2rem;
                margin-bottom: 1rem;
            }
            
            .balance {
                font-size: 1rem;
                padding: 10px 15px;
                margin-bottom: 2rem;
            }
            
            .tower-container {
                width: 200px;
                height: 300px;
                margin-bottom: 1.5rem;
            }
            
            .tower-level {
                height: 40px;
                font-size: 1.2rem;
            }
            
            .bet-form {
                gap: 1.5rem;
            }
            
            .bet-input-group input {
                width: 200px;
                font-size: 1rem;
                padding: 10px 15px 10px 35px;
            }
            
            .btn-group {
                flex-direction: column;
                align-items: center;
                gap: 0.8rem;
            }
            
            .btn {
                width: 200px;
                padding: 12px 20px;
                font-size: 0.9rem;
            }
            
            .result-box {
                padding: 12px 20px;
                max-width: 100%;
            }
            
            .result-box h3 {
                font-size: 1rem;
            }
            
            .back-link {
                position: relative;
                bottom: auto;
                left: auto;
                margin-top: 2rem;
                width: 200px;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .game-container {
                padding: 20px 15px;
                border-radius: 12px;
            }
            
            h2 {
                font-size: 1.8rem;
            }
            
            .balance {
                font-size: 0.9rem;
                padding: 8px 12px;
            }
            
            .tower-container {
                width: 150px;
                height: 225px;
            }
            
            .tower-level {
                height: 30px;
                font-size: 1rem;
            }
            
            .bet-input-group input {
                width: 180px;
                font-size: 0.9rem;
            }
            
            .btn {
                width: 180px;
                font-size: 0.8rem;
                padding: 10px 16px;
            }
            
            .result-box h3 {
                font-size: 0.9rem;
            }
            
            .back-link {
                bottom: 15px;
                left: 15px;
                width: 220px;
                padding: 10px 20px;
                font-size: 0.9rem;
                max-width: calc(100% - 30px);
            }
        }

        @media (max-width: 360px) {
            .game-container {
                padding: 15px 10px;
            }
            
            h2 {
                font-size: 1.5rem;
            }
            
            .tower-container {
                width: 120px;
                height: 180px;
            }
            
            .tower-level {
                height: 25px;
                font-size: 0.9rem;
            }
            
            .bet-input-group input {
                width: 160px;
            }
            
            .btn {
                width: 160px;
                font-size: 0.75rem;
            }
            
            .back-link {
                bottom: 10px;
                left: 10px;
                width: 200px;
                padding: 8px 16px;
                font-size: 0.8rem;
                max-width: calc(100% - 20px);
                white-space: nowrap;
                text-align: center;
            }
        }

        @media (min-width: 1200px) {
            .game-container {
                max-width: 1000px;
                padding: 50px;
            }
            
            h2 {
                font-size: 2.5rem;
            }
            
            .tower-container {
                width: 300px;
                height: 450px;
            }
            
            .tower-level {
                height: 60px;
                font-size: 1.8rem;
            }
            
            .bet-input-group input {
                width: 250px;
                font-size: 1.2rem;
            }
            
            .btn {
                padding: 15px 35px;
                font-size: 1.1rem;
            }
        }

        @media (orientation: landscape) and (max-height: 600px) {
            .game-container {
                padding: 20px 40px;
            }
            
            h2 {
                font-size: 1.8rem;
                margin-bottom: 0.8rem;
            }
            
            .balance {
                margin-bottom: 1.5rem;
                padding: 8px 15px;
            }
            
            .tower-container {
                width: 150px;
                height: 225px;
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="game-container">
        <h2>Tower Climb</h2>
        <div class="balance"><i class=""></i>🪙 Your Balance: <?= number_format($balance, 0) ?> BTL</div>

        <div class="message-area">
            <?php if ($message_data): ?>
                <div class="result <?= $message_data['type'] ?>">
                    <h3>
                        <?php if ($message_data['type'] == 'win'): ?><i class="fas fa-trophy"></i>
                        <?php elseif ($message_data['type'] == 'lose'): ?><i class="fas fa-times-circle"></i>
                        <?php else: ?><i class="fas fa-info-circle"></i><?php endif; ?>
                        <?= $message_data['text'] ?>
                    </h3>
                </div>
            <?php endif; ?>
        </div>

        <div class="game-content">
            <div class="tower-container">
                <div class="tower">
                    <?php for ($i = MAX_LEVELS; $i >= 1; $i--): ?>
                        <?php
                        $level_class = 'tower-level';
                        if ($is_active) {
                            if ($i == $level + 1) $level_class .= ' active';
                            elseif ($i <= $level) $level_class .= ' completed';
                        }
                        ?>
                        <div class="<?= $level_class ?>">
                            <span>Level <?= $i ?></span>
                            <span class="level-multiplier">x<?= number_format(1 + (0.5 * $i), 1) ?></span>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="game-area">
                <?php if (!$is_active): ?>
                    <form method="POST" class="start-form">
                        <div class="bet-input-group">
                            <label for="bet_amount"><i class=""></i>🎲 Enter Bet Amount:</label>
                            <div class="bet-input-container">
                                <span style="
                                position: absolute;
                                left: 15px;
                                top: 48%;
                                transform: translateY(-50%);
                                font-size: 1.2rem;">🪙</span>
                                <input type="number" id="bet_amount" name="bet_amount" min="10" max="10000" required>
                            </div>
                        </div>
                        <button type="submit" name="start_game" class="btn btn-action shine-effect">Start Game</button>
                    </form>
                <?php else: ?>
                    <div class="game-stats">
                        <div class="stat">Level: <strong><?= $level ?> / <?= MAX_LEVELS ?></strong></div>
                        <div class="stat">Bet: <strong><?= number_format($bet, 2) ?></strong></div>
                        <div class="stat">Payout: <strong><?= number_format($potential_payout, 2) ?></strong></div>
                    </div>
                    <div class="tiles">
                        <form method="POST" style="display: contents;">
                            <button type="submit" name="choice" value="1" class="btn btn-blue tile-btn">?</button>
                            <button type="submit" name="choice" value="2" class="btn btn-blue tile-btn">?</button>
                            <button type="submit" name="choice" value="3" class="btn btn-blue tile-btn">?</button>
                        </form>
                    </div>
                    <?php if ($level > 0): ?>
                        <form method="POST" class="cashout-form">
                            <button type="submit" name="cashout" class="btn btn-green">Claim Tokens</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
         <div style="text-align: center;">
            <a href="../main.php" class="back-link">Back to Dashboard</a>
        </div>
    </div>
</body>
</html>