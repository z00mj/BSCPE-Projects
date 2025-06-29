<!-- final colorbet game -->
<?php
session_start();
$conn = new mysqli("localhost", "root", "", "ecasinosite");

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$userId = $_SESSION['user_id'];

// --- FORM HANDLING & GAME LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $wallet = $conn->query("SELECT token_balance FROM wallets WHERE user_id = $userId")->fetch_assoc();
    $balance = $wallet['token_balance'];
    
    $betAmount = floatval($_POST['bet_amount'] ?? 0);
    $chosenColor = $_POST['color'] ?? '';

    if (!$betAmount || !$chosenColor) {
        $_SESSION['colorbet_message'] = ["text" => "Please fill in all fields.", "type" => "lose"];
    } elseif ($betAmount <= 0 || $betAmount > $balance) {
        $_SESSION['colorbet_message'] = ["text" => "Invalid bet or insufficient balance.", "type" => "lose"];
    } else {
        // Determine random outcome
        $rand = rand(1, 5);
        if ($rand == 1) $outcome = "Red";
        elseif ($rand == 2) $outcome = "Black";
        elseif ($rand == 3) $outcome = "Green";
        elseif ($rand == 4) $outcome = "Blue";
        else $outcome = "Yellow";

        $win = ($chosenColor === $outcome);

        if ($win) {
            $payout_multiplier = 4; // 5 colors, 4x payout
            $payout = $betAmount * $payout_multiplier;
            $newBalance = $balance + $payout;
            $conn->query("UPDATE casino_status SET token_balance = token_balance - $payout WHERE id = 1");
            $_SESSION['colorbet_message'] = ["text" => "Winner! The color was $outcome. You won<br>" . number_format($payout, 2), "type" => "win", "outcome" => $outcome];
        } else {
            $newBalance = $balance - $betAmount;
            $conn->query("UPDATE casino_status SET token_balance = token_balance + $betAmount WHERE id = 1");
            $_SESSION['colorbet_message'] = ["text" => "You lost. The color was $outcome. You lost<br>" . number_format($betAmount, 2), "type" => "lose", "outcome" => $outcome];
        }

        $conn->query("UPDATE wallets SET token_balance = $newBalance WHERE user_id = $userId");
        $conn->query("INSERT INTO games (user_id, game_type, result, bet_amount) VALUES ($userId, 'Color Bet', '$outcome', $betAmount)");
        $conn->query("INSERT INTO transactions (user_id, type, amount) VALUES ($userId, '" . ($win ? "win" : "bet") . "', $betAmount)");
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// --- PAGE DATA ---
$wallet = $conn->query("SELECT token_balance FROM wallets WHERE user_id = $userId")->fetch_assoc();
$balance = $wallet['token_balance'];
$message_data = $_SESSION['colorbet_message'] ?? null;
unset($_SESSION['colorbet_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <title>Color Bet | BitLuck</title>
    <link rel="icon" href="../images/COLOR-BET-LOGO.png" type="image/png">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap');
        :root {
            --gold: #FFD700; --red-bet: #D10000; --red-glow: rgba(209,0,0,0.6);
            --black: #0A0A0A; --black-light: #121212; --white: #FFFFFF;
            --gray: #333; --green-bet: #28a745; --black-bet: #222;
            --blue-bet: #007bff; --yellow-bet: #ffc107;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Montserrat', sans-serif; background-color: var(--black);
            color: var(--white); min-height: 100vh; padding: 20px;
            display: flex; align-items: center; justify-content: center;
        }
        .game-container {
            width: 100%;
            max-width: 900px;
            background-color: var(--black-light);
            border-radius: 16px;
            padding: 40px;
            border: 2px solid var(--gold);
            position: relative;
            padding-bottom: 80px; /* Base padding for desktop */
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
        .message-area { display: flex; align-items: center; justify-content: center; margin-bottom: 2rem; }
        .result-box {
            padding: 15px 25px; border-left: 4px solid;
            max-width: 550px; background-color: var(--gray); border-radius: 8px;
        }
        .result-box.win { border-color: var(--gold); }
        .result-box.lose { border-color: var(--red-bet); }
        .result-box h3 { font-size: 1.1rem; font-weight: 700; text-transform: uppercase; text-align: center; }
        .result-box.win h3 { color: var(--gold); }
        .result-box.lose h3 { color: var(--red-bet); }
        .result-box h3 i { font-size: 1.3rem; margin-right: 8px; }

        .game-area { text-align: center; }
        
        .wheel-container {
            position: relative;
            width: 200px;
            height: 200px;
            margin: 0 auto 2rem auto;
        }
        .wheel-container::before {
            content: '';
            position: absolute;
            top: -25px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10;
            width: 0;
            height: 0;
            border-left: 15px solid transparent;
            border-right: 15px solid transparent;
            border-top: 25px solid var(--gold);
        }
        .wheel {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: conic-gradient(
                var(--red-bet) 0deg 72deg,
                var(--black-bet) 72deg 144deg,
                var(--green-bet) 144deg 216deg,
                var(--blue-bet) 216deg 288deg,
                var(--yellow-bet) 288deg 360deg
            );
            border: 8px solid var(--gray);
            box-shadow: 0 0 20px rgba(0,0,0,0.5);
            transition: transform 4s cubic-bezier(0.25, 1, 0.5, 1);
        }

        #result-area { 
            opacity: 0; 
            transition: opacity 0.5s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
        }
        #result-area.show { opacity: 1; }

        .bet-form {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2rem;
        }
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
        .btn-group { display: flex; gap: 1rem; justify-content: center; }
        .btn {
            padding: 12px 28px; font-size: 1rem; font-weight: 700;
            border-radius: 50px; cursor: pointer; text-transform: uppercase;
            border: 2px solid transparent; transition: all 0.3s ease; text-decoration: none;
            color: var(--white); display: inline-flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn:hover { transform: scale(1.05); }
        .btn-red { background: var(--red-bet); box-shadow: 0 0 15px var(--red-glow); }
        .btn-black { background: var(--black-bet); border: 2px solid var(--white); }
        .btn-green { background: var(--green-bet); box-shadow: 0 0 15px rgba(40, 167, 69, 0.6); }
        .btn-blue { background: var(--blue-bet); box-shadow: 0 0 15px rgba(0, 123, 255, 0.6); }
        .btn-yellow { background: var(--yellow-bet); box-shadow: 0 0 15px rgba(255, 193, 7, 0.6); color: var(--black); }

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

        .back-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
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
            width: 260px;
            max-width: 100%;
            margin: 20px auto 0 auto;
            white-space: nowrap;
            overflow: hidden;
        }
        .back-link::before {
            content: '\f104'; /* fa-arrow-left */
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
        }
        .back-link:hover {
            background: rgba(209, 0, 0, 0.5);
            color: #FFECB3;
            transform: translateX(-5px);
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.5);
        }

        @media (max-width: 768px) {
            .game-container {
                padding: 30px 20px;
                margin: 10px;
                padding-bottom: 100px; /* Increased padding for stacked buttons */
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
            
            .wheel-container {
                width: 150px;
                height: 150px;
                margin-bottom: 1.5rem;
            }
            
            .wheel {
                border-width: 6px;
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
                left: 20px;
                bottom: 20px;
                padding: 10px 20px;
                font-size: 1rem;
            }
        }

        @media (max-width: 480px) {
            .game-container {
                padding: 20px 15px;
                border-radius: 12px;
                padding-bottom: 100px; /* Consistent increased padding */
            }
            
            h2 {
                font-size: 1.8rem;
            }
            
            .balance {
                font-size: 0.9rem;
                padding: 8px 12px;
            }
            
            .wheel-container {
                width: 120px;
                height: 120px;
            }
            
            .wheel {
                border-width: 5px;
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
                left: 15px;
                bottom: 15px;
                padding: 10px 15px;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 360px) {
            .game-container {
                padding: 15px 10px;
                padding-bottom: 100px; /* Consistent increased padding */
            }
            
            h2 {
                font-size: 1.5rem;
            }
            
            .wheel-container {
                width: 100px;
                height: 100px;
            }
            
            .wheel {
                border-width: 4px;
            }
            
            .bet-input-group input {
                width: 160px;
            }
            
            .btn {
                width: 160px;
                font-size: 0.75rem;
            }

            .back-link {
                left: 10px;
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
            
            .wheel-container {
                width: 250px;
                height: 250px;
            }
            
            .wheel {
                border-width: 10px;
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
            
            .wheel-container {
                width: 120px;
                height: 120px;
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
<div class="game-container">
    <h2> Color Bet</h2>
    <div class="balance"><i class=""></i>🪙 Your Balance: <?= number_format($balance, 0) ?> BTL</div>

    <div class="message-area">
        <?php if ($message_data && !isset($message_data['outcome'])): ?>
            <div class="result-box <?= $message_data['type'] ?>"><h3><?= $message_data['text'] ?></h3></div>
        <?php endif; ?>
    </div>

    <div class="game-area">
        <div class="wheel-container">
            <div id="color-wheel" class="wheel" data-outcome="<?= $message_data['outcome'] ?? '' ?>"></div>
        </div>

        <?php if (isset($message_data)): ?>
            <div id="result-area">
                <div class="result-box <?= $message_data['type'] ?>"><h3><?= $message_data['text'] ?></h3></div>
                <a href="colorbet.php" class="btn btn-red">Play Again</a>
            </div>
        <?php else: ?>
            <form method="POST" class="bet-form">
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
                <div class="btn-group">
                    <button type="submit" name="color" value="Red" class="btn btn-red shine-effect">Bet Red (4x)</button>
                    <button type="submit" name="color" value="Black" class="btn btn-black shine-effect">Bet Black (4x)</button>
                    <button type="submit" name="color" value="Green" class="btn btn-green shine-effect">Bet Green (4x)</button>
                    <button type="submit" name="color" value="Blue" class="btn btn-blue shine-effect">Bet Blue (4x)</button>
                    <button type="submit" name="color" value="Yellow" class="btn btn-yellow shine-effect">Bet Yellow (4x)</button>
                </div>
            </form>
        <?php endif; ?>
         <a href="../main.php" class="back-link">Back to Dashboard</a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const wheel = document.getElementById('color-wheel');
    const resultArea = document.getElementById('result-area');
    if (!wheel || !wheel.dataset.outcome) return;

    if(resultArea) resultArea.style.opacity = '0';

    const outcome = wheel.dataset.outcome;
    let targetAngle;

    // Angle ranges to match the new 5-slice wheel
    const redRange = [1, 71];
    const blackRange = [73, 143];
    const greenRange = [145, 215];
    const blueRange = [217, 287];
    const yellowRange = [289, 359];

    if (outcome === 'Red') {
        targetAngle = Math.random() * (redRange[1] - redRange[0]) + redRange[0];
    } else if (outcome === 'Black') {
        targetAngle = Math.random() * (blackRange[1] - blackRange[0]) + blackRange[0];
    } else if (outcome === 'Green') {
        targetAngle = Math.random() * (greenRange[1] - greenRange[0]) + greenRange[0];
    } else if (outcome === 'Blue') {
        targetAngle = Math.random() * (blueRange[1] - blueRange[0]) + blueRange[0];
    } else { // Yellow
        targetAngle = Math.random() * (yellowRange[1] - yellowRange[0]) + yellowRange[0];
    }

    const spinOffset = 360 * 5; // 5 full spins for effect
    const finalAngle = spinOffset + (360 - targetAngle);

    // Use a short timeout to allow the browser to render the initial state
    // before applying the transform, ensuring the CSS transition fires.
    setTimeout(() => {
        wheel.style.transform = `rotate(${finalAngle}deg)`;
    }, 100);
    
    setTimeout(() => {
        if (resultArea) resultArea.style.opacity = '1';
    }, 4100);
});
</script>
</body>
</html>