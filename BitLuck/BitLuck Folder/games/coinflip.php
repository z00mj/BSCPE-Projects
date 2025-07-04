<?php
session_start();
$conn = new mysqli("localhost", "root", "", "ecasinosite");

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$userId = $_SESSION['user_id'];

// Get user wallet
$wallet = $conn->query("SELECT token_balance, wallet_address FROM wallets WHERE user_id = $userId")->fetch_assoc();
$balance = $wallet['token_balance'];
$walletAddress = $wallet['wallet_address'];

$outcome = null;
$win = null;
$betAmount = 0;
$previousBalance = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $previousBalance = $balance;
    $betAmount = floatval($_POST['bet_amount']);
    $choice = $_POST['choice'];

    if ($betAmount <= 0 || $betAmount > $balance) {
        $error = "<i class='fas fa-times-circle'></i> Invalid or insufficient balance for bet.";
    } else {
        $outcome = rand(0, 1) ? "Heads" : "Tails";
        $win = $choice === $outcome;

        // Update balances
        $newBalance = $balance + ($win ? $betAmount : -$betAmount);
        $conn->query("UPDATE wallets SET token_balance = $newBalance WHERE user_id = $userId");

        if ($win) {
            $conn->query("UPDATE casino_status SET token_balance = token_balance - $betAmount WHERE id = 1");
        } else {
            $conn->query("UPDATE casino_status SET token_balance = token_balance + $betAmount WHERE id = 1");
        }

        // Log game and transaction
        $conn->query("INSERT INTO games (user_id, game_type, result, bet_amount) VALUES ($userId, 'Coin Flip', '$outcome', $betAmount)");
        $conn->query("INSERT INTO transactions (user_id, type, amount) VALUES ($userId, '" . ($win ? "win" : "bet") . "', $betAmount)");

        // Update latest balance for display
        $balance = $newBalance;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <title>Coin Flip | BitLuck</title>
    <link rel="icon" href="../images/COINFLIP-LOGO.png" type="image/png">
    <style>
        /* ===== NEW LUXURY CASINO THEME ===== */
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap');

        :root {
            --gold: #FFD700;
            --green: #28a745;
            --green-glow: rgba(40, 167, 69, 0.6);
            --red: #D10000;
            --red-glow: rgba(209, 0, 0, 0.6);
            --black: #0A0A0A;
            --black-light: #121212;
            --white: #FFFFFF;
            --gray: #333;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background-color: var(--black);
            color: var(--white);
            min-height: 100vh;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .game-container {
            width: 100%;
            max-width: 900px;
            background-color: var(--black-light);
            border-radius: 16px;
            padding: 40px;
            border: 2px solid var(--gold);
            position: relative;
        }

        h2 {
            font-weight: 700;
            color: var(--gold);
            font-size: 2rem;
            text-align: center;
            margin-bottom: 1rem;
            text-transform: uppercase;
        }

        h2::after {
            content: '';
            display: block;
            width: 80px;
            height: 2px;
            background: var(--gold);
            margin: 10px auto 0;
        }

        .balance {
            font-size: 1.1rem;
            text-align: center;
            margin-bottom: 2.5rem;
            color: var(--white);
            font-weight: 600;
            background: var(--gray);
            padding: 12px 20px;
            border-radius: 50px;
            border: 1px solid var(--gold);
            transition: all 0.3s ease;
        }

        .balance i {
            color: var(--gold);
            margin-right: 8px;
        }

        .coin-area {
            margin-bottom: 2.5rem;
        }

        .coin-container {
            perspective: 1200px;
            width: 180px;
            height: 180px;
            margin: 0 auto;
            position: relative;
        }

        .coin {
            width: 100%;
            height: 100%;
            position: relative;
            transform-style: preserve-3d;
        }

        .coin.flip {
            animation: flip-coin 1.5s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards;
        }

        @keyframes flip-coin {
            100% {
                transform: rotateY(<?= $outcome === 'Heads' ? '1800' : '1980' ?>deg);
            }
        }

        .coin-face {
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            backface-visibility: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
            background: radial-gradient(circle at 50% 40%, #fde047, #fbbf24);
            border: 8px solid #fcd34d;
            box-shadow: inset 0 0 0 5px #f59e0b, inset 0 0 15px 5px rgba(0, 0, 0, 0.2);
        }

        .coin-face::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0) 40%);
            border-radius: 50%;
        }

        .heads {
            z-index: 2;
        }

        .tails {
            transform: rotateY(180deg);
        }

        .coin-icon {
            width: 90px;
            height: 90px;
        }

        .coin-icon path {
            fill: none;
            stroke: #fef08a;
            stroke-width: 6;
            stroke-linecap: round;
            stroke-linejoin: round;
            filter: drop-shadow(0px 1px 0px #c27803) drop-shadow(0px 2px 0px #c27803) drop-shadow(0px 3px 5px rgba(0, 0, 0, 0.3));
        }

        .controls-form,
        .result-area {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.5rem;
        }

        .bet-input-group {
            text-align: center;
        }

        .bet-input-group label {
            display: block;
            font-size: 1rem;
            color: var(--white);
            font-weight: 600;
            margin-bottom: 1rem;
            text-transform: uppercase;
        }

        .bet-input-group label i {
            color: var(--gold);
            margin-right: 8px;
        }

        .bet-input-container {
            position: relative;
        }

        .bet-input-container i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gold);
        }

        .bet-input-group input {
            background: var(--gray);
            border: 2px solid var(--gold);
            color: var(--white);
            padding: 12px 18px 12px 40px;
            font-size: 1.1rem;
            border-radius: 8px;
            width: 220px;
            text-align: center;
        }

        .bet-input-group input:focus {
            outline: none;
            box-shadow: 0 0 10px var(--gold);
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
        }

        .btn {
            padding: 12px 28px;
            font-size: 1rem;
            font-weight: 700;
            border-radius: 50px;
            cursor: pointer;
            text-transform: uppercase;
            border: 2px solid transparent;
            transition: all 0.3s ease;
            text-decoration: none;
            color: var(--white);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-action {
            background: var(--red);
            box-shadow: 0 0 15px var(--red-glow);
        }

        .btn-action:hover {
            transform: scale(1.05);
        }

        .btn-green {
            background: var(--green);
            box-shadow: 0 0 15px var(--green-glow);
        }

        .btn-green:hover {
            transform: scale(1.05);
        }

        .btn-secondary {
            background-color: transparent;
            border-color: var(--gold);
            color: var(--gold);
        }

        .btn-secondary:hover {
            background-color: var(--gold);
            color: var(--black);
        }

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
            background: linear-gradient(to bottom right,
                    rgba(255, 255, 255, 0) 0%,
                    rgba(255, 255, 255, 0) 45%,
                    rgba(255, 255, 255, 0.3) 48%,
                    rgba(255, 255, 255, 0.3) 52%,
                    rgba(255, 255, 255, 0) 55%,
                    rgba(255, 255, 255, 0) 100%);
            transform: rotate(30deg);
            animation: shine 5s infinite;
        }

        @keyframes shine {
            0% {
                transform: rotate(30deg) translate(-30%, -30%);
            }

            100% {
                transform: rotate(30deg) translate(30%, 30%);
            }
        }

        #result-area {
            opacity: 0;
            transition: opacity 0.3s 1.5s;
        }

        #result-area.show {
            opacity: 1;
        }

        .result-area .action-buttons {
            flex-direction: column;
        }

        .result {
            padding: 15px 25px;
            border-left: 4px solid;
            width: 100%;
            max-width: 450px;
            background-color: var(--gray);
            border-radius: 8px;
        }

        .result.win {
            border-color: var(--gold);
        }

        .result.lose {
            border-color: var(--red);
        }

        .result h3 {
            font-size: 1.1rem;
            font-weight: 700;
            text-transform: uppercase;
            text-align: center;
        }

        .result.win h3 {
            color: var(--gold);
        }

        .result.lose h3 {
            color: var(--red);
        }

        .result h3 i {
            font-size: 1.3rem;
            margin-right: 8px;
        }

        /* ===== RESPONSIVE DESIGN ===== */
        @media (max-width: 768px) {
            .game-container {
                padding: 30px 20px;
                margin: 10px;
                padding-bottom: 110px;
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

            .coin-area {
                margin-bottom: 2rem;
            }

            .coin-container {
                width: 150px;
                height: 150px;
            }

            .coin-icon {
                width: 70px;
                height: 70px;
            }

            .bet-input-group input {
                width: 200px;
                font-size: 1rem;
                padding: 10px 15px 10px 35px;
            }

            .action-buttons {
                flex-direction: column;
                align-items: center;
                gap: 0.8rem;
            }

            .result-area {
                gap: 1rem;
            }

            .result {
                padding: 12px 20px;
                max-width: 100%;
            }

            .result h3 {
                font-size: 1rem;
            }

            .back-link {
                margin-top: 2rem;
                width: 200px;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .game-container {
                padding: 20px 15px;
                border-radius: 12px;
                padding-bottom: 100px;
            }

            h2 {
                font-size: 1.8rem;
            }

            .balance {
                font-size: 0.9rem;
                padding: 8px 12px;
            }

            .coin-container {
                width: 120px;
                height: 120px;
            }

            .coin-icon {
                width: 60px;
                height: 60px;
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

            .result h3 {
                font-size: 0.9rem;
            }

            .back-link {
                width: 160px;
                font-size: 0.9rem;
                padding: 10px 0;
            }
        }

        @media (max-width: 360px) {
            .game-container {
                padding: 15px 10px;
            }

            h2 {
                font-size: 1.5rem;
            }

            .coin-container {
                width: 100px;
                height: 100px;
            }

            .coin-icon {
                width: 50px;
                height: 50px;
            }

            .bet-input-group input {
                width: 160px;
            }

            .btn {
                width: 160px;
                font-size: 0.75rem;
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

            .coin-container {
                width: 220px;
                height: 220px;
            }

            .coin-icon {
                width: 110px;
                height: 110px;
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

            .coin-area {
                margin-bottom: 1.5rem;
            }

            .coin-container {
                width: 120px;
                height: 120px;
            }
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
            content: '\f104';
            /* fa-arrow-left */
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
        }

        .back-link:hover {
            background: rgba(209, 0, 0, 0.5);
            color: #FFECB3;
            transform: translateX(-5px);
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.5);
        }
    </style>
</head>

<body>
    <div class="game-container">
        <h2>COIN FLIP</h2>
        <div class="balance" data-start-balance="<?= $previousBalance ?? $balance ?>" data-end-balance="<?= $balance ?>">
            <i class=""></i>🪙 Your Balance: <span id="balance-display"><?= number_format($previousBalance ?? $balance) ?> BTL</span>
        </div>

        <?php if (isset($error)): ?>
            <div class="result lose" style="margin-bottom: 1.5rem;">
                <h3 style="color:var(--white);"><?= $error ?></h3>
            </div>
        <?php endif; ?>

        <div class="coin-area">
            <div class="coin-container">
                <div class="coin <?= $outcome ? 'flip' : '' ?>">
                    <div class="coin-face heads">
                        <svg class="coin-icon" viewBox="0 0 100 100">
                            <path d="M50,25 C40,25 30,35 30,47 C30,60 40,65 40,75 L60,75 C60,65 70,60 70,47 C70,35 60,25 50,25 Z" />
                        </svg>
                    </div>
                    <div class="coin-face tails">
                        <svg class="coin-icon" viewBox="0 0 100 100">
                            <path d="M30,50 C40,30 70,70 80,50" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($outcome): ?>
            <div class="result-area" id="result-area">
                <div class="result <?= $win ? 'win' : 'lose' ?>">
                    <h3>
                        <?php if ($win): ?>
                            <i class="fas fa-trophy"></i> You Won! The result was <?= $outcome ?> (<?= number_format($betAmount) ?>)
                        <?php else: ?>
                            <i class="fas fa-times-circle"></i> You Lost. The result was <?= $outcome ?> (<?= number_format($betAmount) ?>)
                        <?php endif; ?>
                    </h3>
                </div>
                <div class="action-buttons">
                    <a href="coinflip.php" class="btn btn-action">
                        <i class="fas fa-redo"></i> Play Again
                    </a>
                </div>
                <a href="../main.php" class="back-link">Back to Dashboard</a>
            </div>
        <?php else: ?>
            <form method="POST" action="coinflip.php" class="controls-form">
                <div class="bet-input-group">
                    <label for="bet_amount"><i class=""></i>🎲 Enter Bet Amount:</label>
                    <div class="bet-input-container">
                        <span style="
                        position: absolute;
                        left: 15px;
                        top: 48%;
                        transform: translateY(-50%);
                        font-size: 1.2rem;">🪙</span>
                        <input type="number" id="bet_amount" name="bet_amount" min="10" step="0.01" max="10000" required>
                    </div>
                </div>
                <div class="action-buttons">
                    <button type="submit" name="choice" value="Heads" class="btn btn-action shine-effect">
                        <i class="fas fa-crown"></i> Bet Heads
                    </button>
                    <button type="submit" name="choice" value="Tails" class="btn btn-green shine-effect">
                        <i class="fas fa-star"></i> Bet Tails
                    </button>
                </div>
                <a href="../main.php" class="back-link">Back to Dashboard</a>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($outcome): ?>
        <script>
            // Show result area
            setTimeout(() => {
                const resultArea = document.getElementById('result-area');
                if (resultArea) resultArea.classList.add('show');
            }, 0);

            // Animate balance (no color/animation change)
            document.addEventListener('DOMContentLoaded', () => {
                const balanceEl = document.querySelector('.balance');
                const balanceDisplay = document.getElementById('balance-display');
                if (!balanceEl || !balanceDisplay) return;

                const startBalance = parseFloat(balanceEl.dataset.startBalance);
                const endBalance = parseFloat(balanceEl.dataset.endBalance);

                if (startBalance !== endBalance) {
                    const duration = 1200;
                    const frameDuration = 1000 / 60;
                    const totalFrames = Math.round(duration / frameDuration);
                    const easeOutQuad = t => t * (2 - t);
                    let frame = 0;

                    const counter = setInterval(() => {
                        frame++;
                        const progress = easeOutQuad(frame / totalFrames);
                        const currentVal = startBalance + (endBalance - startBalance) * progress;
                        const formattedVal = Math.floor(currentVal).toLocaleString();
                        balanceDisplay.textContent = formattedVal;
                        if (frame === totalFrames) {
                            clearInterval(counter);
                            balanceDisplay.textContent = Math.floor(endBalance).toLocaleString();
                        }
                    }, frameDuration);
                }
            });
        </script>
    <?php endif; ?>
</body>

</html>