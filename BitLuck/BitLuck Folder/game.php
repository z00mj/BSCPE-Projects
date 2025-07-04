<?php
date_default_timezone_set('Asia/Manila');
session_start();
include 'backend/config.php'; // DB connection

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "ecasinosite");
$userId = $_SESSION['user_id'];
$user = $conn->query("SELECT * FROM users WHERE id=$userId")->fetch_assoc();
$wallet = $conn->query("SELECT token_balance FROM wallets WHERE user_id = $userId")->fetch_assoc();

$mineMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mine'])) {
    $miningRate = 10;
    $miningCooldown = 300; // 5 minutes

    $last = $conn->query("SELECT mined_at FROM mining_log WHERE user_id = $userId ORDER BY mined_at DESC LIMIT 1");
    $lastTime = $last->fetch_assoc();

    if ($lastTime && (time() - strtotime($lastTime['mined_at'])) < $miningCooldown) {
        $mineMessage = "⏳ You must wait before mining again.";
    } else {
        $conn->query("UPDATE wallets SET token_balance = token_balance + $miningRate WHERE user_id = $userId");
        $conn->query("INSERT INTO mining_log (user_id, mined_tokens) VALUES ($userId, $miningRate)");
        $mineMessage = "Mined $miningRate BTL!";
    }

    // Redirect with message as a GET parameter
    header("Location: game.php?mineMessage=" . urlencode($mineMessage));
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Games</title>
    <link rel="stylesheet" href="css/account.css">
    <link rel="icon" href="images/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Custom Modal Styles */
        /* Casino Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, rgba(31, 41, 55, 0.9), rgba(55, 65, 81, 0.9));
            backdrop-filter: blur(8px);
        }

        .modal-content {
            background: linear-gradient(145deg, #1F2937 0%, #374151 50%, #1F2937 100%);
            margin: 3% auto;
            padding: 0;
            border-radius: 20px;
            width: 90%;
            max-width: 520px;
            box-shadow:
                0 0 40px rgba(228, 180, 76, 0.3),
                0 20px 60px rgba(0, 0, 0, 0.8),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
            animation: casinoSlideIn 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            position: relative;
            border: 2px solid rgba(228, 180, 76, 0.2);
            overflow: hidden;
        }

        .modal-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background:
                radial-gradient(circle at 20% 20%, rgba(228, 180, 76, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(228, 180, 76, 0.1) 0%, transparent 50%);
            pointer-events: none;
            z-index: 1;
        }

        @keyframes casinoSlideIn {
            0% {
                opacity: 0;
                transform: translateY(-100px) scale(0.8) rotateX(15deg);
            }

            50% {
                opacity: 0.8;
                transform: translateY(-20px) scale(0.95) rotateX(5deg);
            }

            100% {
                opacity: 1;
                transform: translateY(0) scale(1) rotateX(0deg);
            }
        }

        .modal-header {
            background: linear-gradient(135deg, #E4B44C 0%, #d4a142 50%, #E4B44C 100%);
            color: #1F2937;
            padding: 25px 30px;
            border-radius: 20px 20px 0 0;
            position: relative;
            overflow: hidden;
            z-index: 2;
            box-shadow: 0 4px 20px rgba(228, 180, 76, 0.4);
        }

        .modal-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 200%;
            height: 100%;
            background: linear-gradient(90deg,
                    transparent 0%,
                    rgba(255, 255, 255, 0.2) 25%,
                    rgba(255, 255, 255, 0.4) 50%,
                    rgba(255, 255, 255, 0.2) 75%,
                    transparent 100%);
            animation: casinoShimmer 4s ease-in-out infinite;
        }

        .modal-header::after {
            content: '♠ ♥ ♦ ♣';
            position: absolute;
            top: 10px;
            right: 60px;
            font-size: 12px;
            opacity: 0.3;
            letter-spacing: 8px;
            animation: cardSuits 3s ease-in-out infinite;
        }

        @keyframes casinoShimmer {

            0%,
            100% {
                left: -100%;
            }

            50% {
                left: 100%;
            }
        }

        @keyframes cardSuits {

            0%,
            100% {
                opacity: 0.3;
                transform: translateY(0);
            }

            50% {
                opacity: 0.6;
                transform: translateY(-2px);
            }
        }

        .modal-header h4 {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            position: relative;
            z-index: 3;
            letter-spacing: 1px;
        }

        .modal-header h4::before {
            content: '';
            margin-right: 10px;
            animation: slotSpin 2s ease-in-out infinite;
        }

        @keyframes slotSpin {

            0%,
            100% {
                transform: rotate(0deg);
            }

            25% {
                transform: rotate(90deg);
            }

            50% {
                transform: rotate(180deg);
            }

            75% {
                transform: rotate(270deg);
            }
        }

        .close {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(31, 41, 55, 0.2);
            border: 2px solid rgba(31, 41, 55, 0.3);
            color: #1F2937;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            padding: 0;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
            z-index: 4;
        }

        .close:hover {
            background: rgba(31, 41, 55, 0.4);
            transform: translateY(-50%) rotate(90deg) scale(1.1);
            box-shadow: 0 0 15px rgba(31, 41, 55, 0.5);
        }

        .modal-body {
            padding: 35px 30px;
            position: relative;
            z-index: 2;
        }

        .wallet-info {
            background: linear-gradient(135deg, rgba(228, 180, 76, 0.1) 0%, rgba(55, 65, 81, 0.3) 100%);
            padding: 25px;
            border-radius: 15px;
            margin-top: -30px;
            margin-bottom: 25px;
            border: 2px solid rgba(228, 180, 76, 0.3);
            color: #FFF;
            position: relative;
            overflow: hidden;
            box-shadow:
                0 8px 25px rgba(0, 0, 0, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
        }

        .wallet-info::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #E4B44C, #d4a142, #E4B44C);
            background-size: 200% 100%;
            animation: casinoGlow 3s linear infinite;
        }

        @keyframes casinoGlow {
            0% {
                background-position: -200% 0;
            }

            100% {
                background-position: 200% 0;
            }
        }

        .wallet-info h3 {
            margin: 0 0 20px 0;
            color: #E4B44C;
            font-size: 1.4rem;
            font-weight: 700;
            text-shadow: 0 0 10px rgba(228, 180, 76, 0.5);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .wallet-info h3::before {
            content: '';
            font-size: 20px;
            animation: gemGlow 2s ease-in-out infinite alternate;
        }

        @keyframes gemGlow {
            0% {
                filter: brightness(1) drop-shadow(0 0 5px rgba(228, 180, 76, 0.5));
            }

            100% {
                filter: brightness(1.3) drop-shadow(0 0 15px rgba(228, 180, 76, 0.8));
            }
        }

        .wallet-info p {
            margin: 15px 0;
            font-size: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: rgba(255, 255, 255, 0.9);
        }

        .wallet-info span {
            font-weight: 700;
            color: #E4B44C;
            font-family: 'Courier New', monospace;
            text-shadow: 0 0 8px rgba(228, 180, 76, 0.4);
        }

        #connectBtn {
            background: linear-gradient(135deg, #E4B44C 0%, #d4a142 50%, #E4B44C 100%);
            color: #1F2937;
            padding: 14px 28px;
            border: none;
            border-radius: 25px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.4s ease;
            margin-bottom: 20px;
            box-shadow:
                0 6px 20px rgba(228, 180, 76, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        #connectBtn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.6s ease;
        }

        #connectBtn:hover::before {
            left: 100%;
        }

        #connectBtn:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow:
                0 10px 30px rgba(228, 180, 76, 0.6),
                0 0 20px rgba(228, 180, 76, 0.4);
        }

        #connectBtn:active {
            transform: translateY(-1px) scale(1.01);
        }

        #depositForm {
            display: block;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 12px;
            display: block;
            color: #E4B44C;
            font-size: 16px;
            text-shadow: 0 0 8px rgba(228, 180, 76, 0.3);
            letter-spacing: 0.5px;
        }

        .form-control {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid rgba(228, 180, 76, 0.3);
            border-radius: 12px;
            font-size: 16px;
            background: rgba(31, 41, 55, 0.6);
            color: #FFF;
            transition: all 0.3s ease;
            box-shadow: inset 0 2px 5px rgba(0, 0, 0, 0.2);
            box-sizing: border-box;
        }

        .form-control::-webkit-outer-spin-button,
        .form-control::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

       

        .form-control:focus {
            outline: none;
            border-color: #E4B44C;
            box-shadow:
                0 0 0 3px rgba(228, 180, 76, 0.2),
                inset 0 2px 5px rgba(0, 0, 0, 0.2),
                0 0 15px rgba(228, 180, 76, 0.4);
            background: rgba(31, 41, 55, 0.8);
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.6);
            font-style: italic;
        }

        .btn-submit,
        .btn-withdraw {
            background: linear-gradient(135deg, #E4B44C 0%, #d4a142 25%, #E4B44C 50%, #d4a142 75%, #E4B44C 100%);
            background-size: 300% 100%;
            color: #1F2937;
            padding: 18px 35px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 18px;
            font-weight: 700;
            width: 100%;
            transition: all 0.4s ease;
            box-shadow:
                0 8px 25px rgba(228, 180, 76, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
            text-transform: uppercase;
            letter-spacing: 1px;
            animation: buttonGlow 3s ease-in-out infinite;
        }

        @keyframes buttonGlow {

            0%,
            100% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }
        }

        .btn-submit::before {
            content: '';
            margin-right: 8px;
            animation: coinFlip 1.5s ease-in-out infinite;
        }

        @keyframes coinFlip {

            0%,
            100% {
                transform: rotateY(0deg);
            }

            50% {
                transform: rotateY(180deg);
            }
        }

        .btn-submit:hover {
            transform: translateY(-4px) scale(1.02);
            box-shadow:
                0 15px 40px rgba(228, 180, 76, 0.6),
                0 0 25px rgba(228, 180, 76, 0.5),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
            animation-duration: 1s;
        }

        .btn-submit:active {
            transform: translateY(-2px) scale(1.01);
        }

        /* Casino-style loading animation */
        #connectBtn.loading::after {
            content: '';
            width: 18px;
            height: 18px;
            margin-left: 10px;
            border: 3px solid rgba(31, 41, 55, 0.3);
            border-top: 3px solid #1F2937;
            border-radius: 50%;
            animation: casinoSpin 1s linear infinite;
            display: inline-block;
        }

        @keyframes casinoSpin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                margin: 10px auto;
            }

            .modal-header {
                padding: 20px 25px;
            }

            .modal-header h4 {
                font-size: 1.5rem;
            }

            .modal-body {
                padding: 25px 20px;
            }

            .wallet-info {
                padding: 20px;
            }
        }

        .avatar-thumb {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            cursor: pointer;
            border: 2px solid #ccc;
            transition: 0.2s;
        }

        .avatar-thumb:hover {
            border-color: #e4b44c;
            transform: scale(1.05);
        }

        /*play now button*/
        .play-now-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: #e4b44c;
            color: #1f2937;
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: bold;
            font-size: 16px;
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
            z-index: 2;
        }

        .game-card:hover .play-now-overlay {
            opacity: 1;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo" style="display: flex; justify-content: center;">
                <img src="images/logo.png" alt="BitLuck Logo" style="height: 40px; width: auto;">
            </div>
            <nav>
                <ul class="nav-menu">
                    <li><a href="main.php"><i class="fas fa-home icon"></i> Home</a></li>
                    <li><a href="game.php" class="active"><i class="fas fa-gamepad icon"></i> Games</a></li>
                    <li><a href="account.php"><i class="fas fa-user icon"></i> Account</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-left">
                    <div class="balance"><?= number_format($wallet['token_balance']) ?> BTL</div>
                    <div class="header-buttons">
                        <button class="btn btn-primary btn-lg" onclick="openDepositModal()">Deposit
                        </button>
                        <button class="btn btn-secondary" onclick="openWithdrawModal()">Withdraw</button>
                        <form method="POST">
                            <button class="btn btn-secondary" type="submit" name="mine">Mine Tokens</button>
                        </form>
                    </div>
                </div>

                <div class="user-info" onclick="toggleUserPanel()" style="cursor: pointer;">
                    <span style="display: flex; align-items: center; gap: 8px;">
                        <img src="images/avatars/<?= htmlspecialchars($user['profile_pic']) ?>" alt="User Avatar"
                            style="width: 35px; height: 35px; border-radius: 50%;">
                        <?= htmlspecialchars($user['username']) ?></span>
                </div>
            </header>

            <!-- Deposit Modal -->
            <div id="depositModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4>Deposit Tokens to Casino</h4>
                        <button class="close" onclick="closeDepositModal()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="wallet-info">
                            <h3>Web3 Wallet Info</h3>
                            <button id="connectBtn">Connect MetaMask</button>
                            <p>Wallet Address: <span id="address">Not connected</span></p>
                            <p>Token Balance: <span id="balance">0</span> tokens</p>
                        </div>

                        <form id="depositForm" action="backend/deposit.php" method="POST" onsubmit="return checkBeforeSubmit();" style="background: transparent; border: none;">
                            <input type="hidden" name="wallet_address" id="walletAddressInput">
                            <input type="hidden" name="token_balance" id="tokenBalanceInput">

                            <div class="form-group">
                                <label for="deposit_amount">Amount to deposit:</label>
                                <input type="number"
                                    name="deposit_amount"
                                    id="depositAmountInput"
                                    class="form-control"
                                    step="1"
                                    min="0"
                                    placeholder="Enter amount"
                                    required>
                            </div>

                            <button type="submit" class="btn-submit">
                                Deposit to Casino
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Withdraw Modal -->
            <div id="withdrawModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4>Withdraw Tokens</h4>
                        <button class="close" onclick="closeWithdrawModal()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="backend/withdraw.php" onsubmit="return validateWithdraw()" style="
                        margin-top: -20px;">
                            <div class="form-group">
                                <label for="withdraw_amount">Amount to withdraw:</label>
                                <input class="form-control" type="number" name="withdraw_amount" placeholder="Enter amount" required>
                            </div>
                            <button type="submit" class="btn-withdraw">
                                Withdraw
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Updated Popular Games Section with filtering -->
            <section class="popular-games-section" style="margin-top: 5px;">
                <div class="section-header"
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 id="section-title" style="font-size: 24px; font-weight: bold; color: white;">All Games</h2>
                </div>

                <div class="games-grid" id="games-grid"
                    style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <!-- Game 1 - Blackjack -->
                    <div class="game-card" data-category="popular recent" data-game="blackjack" onclick="window.location.href='games/blackjack.php'"
                        style="position: relative; background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%); border-radius: 15px; overflow: hidden; cursor: pointer; transition: all 0.3s ease;">
                        <div class="game-image"
                            style="height: 200px; background: url('images/BLACKJACK.png') center/cover;">
                            <div class="game-overlay"
                                style="position: absolute; inset: 0; background: linear-gradient(to bottom, transparent 0%, rgba(0,0,0,0.7) 100%); display: flex; flex-direction: column; justify-content: flex-end; padding: 20px;">
                                <div style="color: white;">
                                    <h3 style="font-size: 18px; font-weight: bold; margin-bottom: 5px;">Blackjack 21</h3>
                                    <p style="font-size: 14px; opacity: 0.9;">Classic card game</p>
                                </div>
                            </div>
                            <div class="play-now-overlay">Play Now</div>
                        </div>
                    </div>

                    <!-- Game 2 - Coin Flip -->
                    <div class="game-card" data-category="popular" data-game="coin-flip" onclick="window.location.href='games/coinflip.php'"
                        style="position: relative; background: linear-gradient(135deg, #4ecdc4 0%, #44a08d 100%); border-radius: 15px; overflow: hidden; cursor: pointer; transition: all 0.3s ease;">
                        <div class="game-image"
                            style="height: 200px; background: url('images/COINFLIP.png') center/cover;">
                            <div class="game-overlay"
                                style="position: absolute; inset: 0; background: linear-gradient(to bottom, transparent 0%, rgba(0,0,0,0.7) 100%); display: flex; flex-direction: column; justify-content: flex-end; padding: 20px;">
                                <div style="color: white;">
                                    <h3 style="font-size: 18px; font-weight: bold; margin-bottom: 5px;">Coin Flip</h3>
                                    <p style="font-size: 14px; opacity: 0.9;">Heads or tails</p>
                                </div>
                            </div>
                            <div class="play-now-overlay">Play Now</div>
                        </div>
                    </div>

                    <!-- Game 3 - Tower Climb -->
                    <div class="game-card" data-category="popular recent" data-game="tower-climb" onclick="window.location.href='games/towerclimb.php'"
                        style="position: relative; background: linear-gradient(135deg, #45b7d1 0%, #2980b9 100%); border-radius: 15px; overflow: hidden; cursor: pointer; transition: all 0.3s ease;">
                        <div class="game-image"
                            style="height: 200px; background: url('images/TOWER-CLIMB.png') center/cover;">
                            <div class="game-overlay"
                                style="position: absolute; inset: 0; background: linear-gradient(to bottom, transparent 0%, rgba(0,0,0,0.7) 100%); display: flex; flex-direction: column; justify-content: flex-end; padding: 20px;">
                                <div style="color: white;">
                                    <h3 style="font-size: 18px; font-weight: bold; margin-bottom: 5px;">Tower Climb</h3>
                                    <p style="font-size: 14px; opacity: 0.9;">Climb to win</p>
                                </div>
                            </div>
                            <div class="play-now-overlay">Play Now</div>
                        </div>
                    </div>

                    <!-- Game 4 - Color Bet -->
                    <div class="game-card" data-category="popular" data-game="color-bet" onclick="window.location.href='games/colorbet.php'"
                        style="position: relative; background: linear-gradient(135deg, #96ceb4 0%, #27ae60 100%); border-radius: 15px; overflow: hidden; cursor: pointer; transition: all 0.3s ease;">
                        <div class="game-image"
                            style="height: 200px; background: url('images/COLOR\ BET.png') center/cover;">
                            <div class="game-overlay"
                                style="position: absolute; inset: 0; background: linear-gradient(to bottom, transparent 0%, rgba(0,0,0,0.7) 100%); display: flex; flex-direction: column; justify-content: flex-end; padding: 20px;">
                                <div style="color: white;">
                                    <h3 style="font-size: 18px; font-weight: bold; margin-bottom: 5px;">Color Bet</h3>
                                    <p style="font-size: 14px; opacity: 0.9;">Bet on colors</p>
                                </div>
                            </div>
                            <div class="play-now-overlay">Play Now</div>
                        </div>
                    </div>

                    <!-- Game 5 - High Low -->
                    <div class="game-card" data-category="popular recent" data-game="high-low" onclick="window.location.href='games/highlow.php'"
                        style="position: relative; background: linear-gradient(135deg, #feca57 0%, #ff9ff3 100%); border-radius: 15px; overflow: hidden; cursor: pointer; transition: all 0.3s ease;">
                        <div class="game-image"
                            style="height: 200px; background: url('images/HIGH-LOW.png') center/cover;">
                            <div class="game-overlay"
                                style="position: absolute; inset: 0; background: linear-gradient(to bottom, transparent 0%, rgba(0,0,0,0.7) 100%); display: flex; flex-direction: column; justify-content: flex-end; padding: 20px;">
                                <div style="color: white;">
                                    <h3 style="font-size: 18px; font-weight: bold; margin-bottom: 5px;">High Low</h3>
                                    <p style="font-size: 14px; opacity: 0.9;">Guess the next card</p>
                                </div>
                            </div>
                            <div class="play-now-overlay">Play Now</div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ===== FOOTER SECTION (NEWLY ADDED) ===== -->
            <footer class="footer">
                <div class="footer-content">
                    <div class="footer-column">
                        <div class="footer-logo">
                            <img src="images/logo.png" alt="BitLuck Logo">
                        </div>
                        <p class="footer-description">Experience the ultimate online casino gaming with BitLuck. Play
                            your
                            favorite games and win big!</p>
                        <div class="social-links">
                            <a href="#" class="social-link">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path
                                        d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z" />
                                </svg>
                            </a>
                            <a href="#" class="social-link">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path
                                        d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                                </svg>
                            </a>
                            <a href="#" class="social-link">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path
                                        d="M12.017 0C5.396 0 .029 5.367.029 11.987c0 5.079 3.158 9.417 7.618 11.174-.105-.949-.199-2.403.042-3.441.219-.937 1.407-5.965 1.407-5.965s-.359-.719-.359-1.782c0-1.668.967-2.914 2.171-2.914 1.023 0 1.518.769 1.518 1.69 0 1.029-.655 2.568-.994 3.995-.283 1.194.599 2.169 1.777 2.169 2.133 0 3.772-2.249 3.772-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738.098.119.112.224.083.345l-.333 1.36c-.053.22-.174.267-.402.161-1.499-.698-2.436-2.888-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.464-6.227 7.464-1.216 0-2.357-.631-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24.009 12.017 24c6.624 0 11.99-5.367 11.99-11.988C24.007 5.367 18.641.001 12.017.001z" />
                                </svg>
                            </a>
                            <a href="#" class="social-link">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path
                                        d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z" />
                                </svg>
                            </a>
                        </div>
                    </div>

                    <div class="footer-column">
                        <h3 class="footer-title">Quick Links</h3>
                        <ul class="footer-links">
                            <li><a href="#">Home</a></li>
                            <li><a href="#">Games</a></li>
                            <li><a href="#">Leaderboard</a></li>
                        </ul>
                    </div>

                    <div class="footer-column">
                        <h3 class="footer-title">Support</h3>
                        <ul class="footer-links">
                            <li><a href="#">FAQ</a></li>
                            <li><a href="#">Contact Us</a></li>
                            <li><a href="#">Terms & Conditions</a></li>
                            <li><a href="#">Privacy Policy</a></li>
                            <li><a href="#">Responsible Gaming</a></li>
                        </ul>
                    </div>

                    <div class="footer-column">
                        <h3 class="footer-title">Payment Methods</h3>
                        <div class="payment-methods">
                            <div class="payment-icon">
                                <i class="fa-solid fa-wallet"></i>
                            </div>
                        </div>
                        <div class="contact-info">
                            <h4 class="contact-title">Contact Us</h4>
                            <div class="contact-item">
                                <span class="contact-icon"><i class="fa-solid fa-envelope"></i></span>
                                <span>support@bitluck.com</span>
                            </div>
                            <div class="contact-item">
                                <span class="contact-icon"><i class="fa-solid fa-comment"></i></span>
                                <span>24/7 Live Chat Support</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="footer-bottom">
                    <p class="copyright">© 2025 BitLuck. All rights reserved. Gambling can be addictive, please
                        play responsibly.</p>
                    <div class="certification-badges">
                        <div class="cert-badge">18+</div>
                        <div class="cert-badge">SSL</div>
                        <div class="cert-badge">Licensed</div>
                    </div>
                </div>
            </footer>
        </main>
    </div>

    <!-- User Panel Overlay -->
    <div class="overlay" id="userPanelOverlay" onclick="toggleUserPanel()"></div>
    <!-- User Panel -->
    <div class="user-panel" id="userPanel">
        <div class="user-panel-header">
            <div class="user-avatar" onclick="openAvatarModal()">
                <img src="images/avatars/<?= htmlspecialchars($user['profile_pic']) ?>" id="currentAvatar">
                <div class="avatar-edit-overlay">
                    <i class="fas fa-edit"></i>
                </div>
            </div>
            <div class="user-details">
                <h3><?= htmlspecialchars($user['username']) ?></h3>
                <div class="user-id">
                    ID: <?= htmlspecialchars($user['user_id']) ?>
                    <i class="fas fa-copy copy-icon" onclick="copyUserId('<?= $user['user_id'] ?>')"></i>
                </div>
            </div>
        </div>


        <!-- Avatar Modal -->
        <div class="avatar-modal" id="avatarModal">
            <div class="avatar-modal-content">
                <button class="modal-close" onclick="closeAvatarModal()">&times;</button>
                <div class="modal-header">
                    <h3>Change Avatar</h3>
                    <p>Choose how you'd like to update your profile picture</p>
                </div>

                <div class="avatar-options">
                    <div class="avatar-option" onclick="triggerFileUpload()">
                        <i class="fas fa-upload"></i>
                        <div>
                            <div style="font-weight: bold;">Upload Photo</div>
                            <div style="font-size: 12px; color: #888;">Choose from your device</div>
                        </div>
                    </div>

                    <div class="avatar-option" onclick="showPredefinedAvatars()">
                        <i class="fas fa-user-circle"></i>
                        <div>
                            <div style="font-weight: bold;">Choose Avatar</div>
                            <div style="font-size: 12px; color: #888;">Select from preset options</div>
                        </div>
                    </div>

                    <div class="avatar-option" onclick="generateRandomAvatar()">
                        <i class="fas fa-dice"></i>
                        <div>
                            <div style="font-weight: bold;">Random Avatar</div>
                            <div style="font-size: 12px; color: #888;">Generate a random profile picture</div>
                        </div>
                    </div>
                </div>

                <div class="predefined-avatars" id="predefinedAvatars" style="display: none;">
                    <!-- Predefined avatars will be populated here -->
                </div>
            </div>
        </div>

        <!-- Hidden file input -->
        <input type="file" id="avatarInput" accept="image/*" onchange="handleFileUpload(event)">

        <div class="menu-item" onclick="openTransactionModal()">
            <div class="menu-item-left">
                <i class="fas fa-history"></i>
                <span>History</span>
            </div>
            <i class="fas fa-chevron-right"></i>
        </div>

        <div class="menu-item">
            <div class="menu-item-left">
                <i class="fas fa-cog"></i>
                <a href="account.php"><span>Account Management</span></a>
            </div>
            <i class="fas fa-chevron-right"></i>
        </div>

        <button class="logout-btn" onclick="window.location.href='index.php'">Log Out</button>
    </div>

    <!-- Transaction History Modal -->
    <div class="transaction-modal" id="transactionModal">
        <div class="transaction-modal-content">
            <div class="transaction-modal-header">
                <h2 class="transaction-modal-title">Transaction History</h2>
                <button class="transaction-modal-close" onclick="closeTransactionModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="transaction-tabs">
                <button class="transaction-tab active" onclick="switchTransactionTab('betting')">Betting</button>
                <button class="transaction-tab" onclick="switchTransactionTab('deposit')">Deposit</button>
                <button class="transaction-tab" onclick="switchTransactionTab('withdrawal')">Withdrawal</button>
            </div>

            <div class="transaction-filters">
                <div class="filter-group" id="gameFilterGroup">
                    <label>Games</label>
                    <select class="filter-select" id="gameFilter">
                        <option value="all">All</option>
                        <option value="Blackjack 21">Blackjack 21</option>
                        <option value="Coin Flip">Coin Flip</option>
                        <option value="Tower Climb">Tower Climb</option>
                        <option value="Color Bet">Color Bet</option>
                        <option value="High-Low">High-Low</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Start Date</label>
                    <input type="datetime-local" class="filter-input" id="startDate" value="2025-06-06T12:00">
                </div>
                <div class="filter-group">
                    <label>End Date</label>
                    <input type="datetime-local" class="filter-input" id="endDate" value="2025-06-07T23:59">
                </div>

                <div class="filter-group">

                    <button class="search-btn" onclick="applyFilters()">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>

            <div class="transaction-table-container">
                <table class="transaction-table">
                    <thead>
                        <tr>
                            <th>Date/Time</th>
                            <th>Game Name</th>
                            <th>Amount</th>
                            <th>Win/Loss</th>
                        </tr>
                    </thead>
                    <tbody id="transactionTableBody">
                        <!-- No records message -->
                    </tbody>
                </table>
                <div class="no-record-message">
                    <p>No Record</p>
                </div>
            </div>
        </div>
    </div>

    

    <script>
        // Open the deposit modal
        function openDepositModal() {
            const modal = document.getElementById('depositModal');
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden'; // Prevent body scroll

            // Focus on the input field after modal opens
            setTimeout(() => {
                document.getElementById('depositAmountInput').focus();
            }, 300);

            // Example: Simulate loading wallet data
            loadWalletData();
        }

        // Close the deposit modal
        function closeDepositModal() {
            const modal = document.getElementById('depositModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto'; // Restore body scroll

            // Clear form when closing
            document.getElementById('depositForm').reset();
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('depositModal');
            if (event.target === modal) {
                closeDepositModal();
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeDepositModal();
            }
        });

        // Simulate loading wallet data (replace with actual MetaMask integration)
        function loadWalletData() {
            // Example wallet data - replace with actual MetaMask calls
            const exampleWalletAddress = "0x742d35Cc6635C0532925a3b8D8C6b6C3E9b08123";
            const exampleTokenBalance = "1,234.5678";

            // Update display
            document.getElementById('walletAddressDisplay').textContent =
                exampleWalletAddress.substring(0, 6) + '...' + exampleWalletAddress.slice(-4);
            document.getElementById('tokenBalanceDisplay').textContent = exampleTokenBalance;

            // Update hidden form fields
            document.getElementById('walletAddressInput').value = exampleWalletAddress;
            document.getElementById('tokenBalanceInput').value = exampleTokenBalance.replace(',', '');
        }

        // Form validation before submit
        function checkBeforeSubmit() {
            const depositAmount = parseFloat(document.getElementById('depositAmountInput').value);
            const tokenBalance = parseFloat(document.getElementById('tokenBalanceInput').value);

            if (!depositAmount || depositAmount <= 0) {
                alert('Please enter a valid deposit amount.');
                return false;
            }

            if (depositAmount > tokenBalance) {
                alert('Insufficient balance. You cannot deposit more than you have.');
                return false;
            }

            // Show confirmation
            const confirmed = confirm(`Are you sure you want to deposit ${depositAmount} tokens?`);
            if (confirmed) {
                console.log('Form submitted with amount:', depositAmount);
                // Close modal after successful submission
                closeDepositModal();
                return true;
            }

            return false;
        }

        // Open the withdraw modal
        function openWithdrawModal() {
            const modal = document.getElementById('withdrawModal');
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';

            // Focus on input after modal opens
            setTimeout(() => {
                document.getElementById('withdraw_amount').focus();
            }, 300);
        }

        // Close the withdraw modal
        function closeWithdrawModal() {
            const modal = document.getElementById('withdrawModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';

            // Clear form
            document.getElementById('withdraw_amount').value = '';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('withdrawModal');
            if (event.target === modal) {
                closeWithdrawModal();
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeWithdrawModal();
            }
        });

        // Basic form validation
        function validateWithdraw() {
            const amount = parseFloat(document.getElementById('withdraw_amount').value);

            if (!amount || amount <= 0) {
                alert('Please enter a valid withdrawal amount.');
                return false;
            }

            // Show confirmation
            const confirmed = confirm(`Are you sure you want to withdraw ${amount} tokens?`);
            if (confirmed) {
                // Close modal after confirmation
                closeWithdrawModal();
                return true;
            }

            return false;
        }

        // Game filtering and favorites system
        let favorites = new Set();
        let currentFilter = 'popular';

        function filterGames(category) {
            currentFilter = category;
            const gameCards = document.querySelectorAll('.game-card');
            const tabs = document.querySelectorAll('.tab-btn');
            const sectionTitle = document.getElementById('section-title');
            const emptyState = document.getElementById('empty-state');

            // Update active tab
            tabs.forEach(tab => {
                tab.classList.remove('active');
                tab.style.background = 'transparent';
                tab.style.color = '#888';
                tab.style.borderColor = '#555';
            });

            const activeTab = document.querySelector(`[data-tab="${category}"]`);
            activeTab.classList.add('active');
            activeTab.style.background = 'rgba(228, 180, 76, 0.2)';
            activeTab.style.color = '#e4b44c';
            activeTab.style.borderColor = '#e4b44c';

            // Update section title
            const titles = {
                'popular': 'All Games',
                'recent': 'Recently Played',
                'favorites': 'Your Favorites'
            };
            sectionTitle.textContent = titles[category];

            let visibleCount = 0;

            // Filter games
            gameCards.forEach(card => {
                const categories = card.dataset.category.split(' ');
                const gameId = card.dataset.game;
                let shouldShow = false;

                if (category === 'popular' && categories.includes('popular')) {
                    shouldShow = true;
                } else if (category === 'recent' && categories.includes('recent')) {
                    shouldShow = true;
                } else if (category === 'favorites' && favorites.has(gameId)) {
                    shouldShow = true;
                }

                if (shouldShow) {
                    card.classList.remove('hidden');
                    visibleCount++;
                } else {
                    card.classList.add('hidden');
                }
            });

            // Show empty state for favorites if no games
            if (category === 'favorites' && visibleCount === 0) {
                emptyState.style.display = 'block';
            } else {
                emptyState.style.display = 'none';
            }
        }

        function toggleFavorite(event, gameId) {
            event.stopPropagation();
            const favoriteBtn = event.currentTarget;
            const heartIcon = favoriteBtn.querySelector('i');

            if (favorites.has(gameId)) {
                favorites.delete(gameId);
                favoriteBtn.classList.remove('favorited');
                heartIcon.className = 'far fa-heart';
            } else {
                favorites.add(gameId);
                favoriteBtn.classList.add('favorited');
                heartIcon.className = 'fas fa-heart';
            }

            // If currently viewing favorites, refresh the view
            if (currentFilter === 'favorites') {
                filterGames('favorites');
            }
        }

        // Add click tracking for recent games
        document.querySelectorAll('.game-card').forEach(card => {
            card.addEventListener('click', function () {
                const gameId = this.dataset.game;
                const categories = this.dataset.category.split(' ');

                // Add to recent if not already there
                if (!categories.includes('recent')) {
                    categories.push('recent');
                    this.dataset.category = categories.join(' ');
                }
            });
        });

        function toggleUserPanel() {
            const panel = document.getElementById('userPanel');
            const overlay = document.getElementById('userPanelOverlay');

            panel.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        function showTab(tabName) {
            // Hide all tab contents
            const tabs = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => tab.classList.remove('active'));

            // Remove active class from all menu items
            const menuItems = document.querySelectorAll('.account-menu-item');
            menuItems.forEach(item => item.classList.remove('active'));

            // Show selected tab
            document.getElementById(tabName).classList.add('active');

            // Add active class to clicked menu item
            event.target.closest('.account-menu-item').classList.add('active');
        }

        //modify
        function editField(fieldName) {
            const displayElement = document.getElementById(fieldName + '-display');
            const editElement = document.getElementById(fieldName + '-edit');
            const modifyBtn = displayElement.parentElement.querySelector('.modify-btn');

            // Hide display, show edit
            displayElement.style.display = 'none';
            editElement.style.display = 'flex';
            modifyBtn.style.display = 'none';

            // Focus on input field
            const inputElement = document.getElementById(fieldName + '-input');
            if (inputElement.tagName === 'INPUT') {
                inputElement.focus();
                inputElement.select();
            }
        }

        function saveField(fieldName) {
            const displayElement = document.getElementById(fieldName + '-display');
            const editElement = document.getElementById(fieldName + '-edit');
            const inputElement = document.getElementById(fieldName + '-input');
            const modifyBtn = displayElement.parentElement.querySelector('.modify-btn');

            // Get new value
            let newValue = inputElement.value.trim();

            // Validation
            if (!newValue) {
                alert('Please enter a valid value');
                return;
            }

            if (fieldName === 'email') {
                const currentEmail = document.getElementById('current-email-input').value.trim();
                const newEmail = document.getElementById('new-email-input').value.trim();

                if (!currentEmail || !newEmail) {
                    alert('Please enter both current and new email addresses');
                    return;
                }

                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(currentEmail) || !emailRegex.test(newEmail)) {
                    alert('Please enter valid email addresses');
                    return;
                }

                // Partially hide new email for display
                const [localPart, domain] = newEmail.split('@');
                const hiddenLocal = localPart.substring(0, 2) + '*'.repeat(Math.max(localPart.length - 2, 3));
                displayElement.textContent = hiddenLocal + '@' + domain;
            } else {
                displayElement.textContent = newValue;
            }

            // Show display, hide edit
            displayElement.style.display = 'block';
            editElement.style.display = 'none';
            modifyBtn.style.display = 'block';

            // Show success message
            showSuccessMessage('Information updated successfully!');
        }

        function cancelEdit(fieldName) {
            const displayElement = document.getElementById(fieldName + '-display');
            const editElement = document.getElementById(fieldName + '-edit');
            const inputElement = document.getElementById(fieldName + '-input');
            const modifyBtn = displayElement.parentElement.querySelector('.modify-btn');

            // Reset input value to original
            if (fieldName === 'nickname') {
                inputElement.value = displayElement.textContent;
            } else if (fieldName === 'gender') {
                inputElement.value = displayElement.textContent;
            } else if (fieldName === 'email') {
                document.getElementById('current-email-input').value = '';
                document.getElementById('new-email-input').value = '';
            }

            // Show display, hide edit
            displayElement.style.display = 'block';
            editElement.style.display = 'none';
            modifyBtn.style.display = 'block';
        }

        function showSuccessMessage(message) {
            // Create temporary success message
            const successDiv = document.createElement('div');
            successDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #10b981;
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        font-weight: bold;
        z-index: 10000;
        animation: slideIn 0.3s ease;
    `;
            successDiv.textContent = message;

            // Add animation keyframe
            if (!document.querySelector('#success-animation')) {
                const style = document.createElement('style');
                style.id = 'success-animation';
                style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        `;
                document.head.appendChild(style);
            }

            document.body.appendChild(successDiv);

            // Remove after 3 seconds
            setTimeout(() => {
                successDiv.remove();
            }, 3000);
        }

        //TRANSACTION 

        function openTransactionModal() {
            document.getElementById('transactionModal').classList.add('active');
            document.body.style.overflow = 'hidden';

            // Show game filter for initial betting tab
            document.getElementById('gameFilterGroup').style.display = 'block';

            // Load initial betting data
            loadTransactionData('betting');
        }

        function closeTransactionModal() {
            document.getElementById('transactionModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        document.addEventListener('DOMContentLoaded', () => {
            const startInput = document.getElementById('startDate');
            const endInput = document.getElementById('endDate');

            const now = new Date();
            const startOfDay = new Date(now);
            startOfDay.setHours(0, 0, 0, 0); // 00:00

            const formatDate = (date) => {
                const pad = (n) => n.toString().padStart(2, '0');
                return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
            };

            startInput.value = formatDate(startOfDay);
            endInput.value = formatDate(now);
        });


        let currentTab = 'betting';


        function switchTransactionTab(tabName) {
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.transaction-tab');
            tabs.forEach(tab => tab.classList.remove('active'));

            // Add active class to clicked tab
            event.target.classList.add('active');

            // Update current tab
            currentTab = tabName;

            // Show/hide game filter based on tab
            const gameFilterGroup = document.getElementById('gameFilterGroup');
            if (tabName === 'betting') {
                gameFilterGroup.style.display = 'block';
            } else {
                gameFilterGroup.style.display = 'none';
            }

            // Load transaction data for the selected tab
            loadTransactionData(tabName);
        }

        function applyFilters() {
            const gameFilter = document.getElementById('gameFilter').value;
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;

            loadTransactionData(currentTab, {
                gameFilter,
                startDate,
                endDate
            });
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('transactionModal');
            if (event.target === modal) {
                closeTransactionModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeTransactionModal();
            }
        });

        async function loadTransactionData(tabType, filters = {}) {
            const tableBody = document.getElementById('transactionTableBody');
            const noRecordMessage = document.querySelector('.no-record-message');
            const tableHeader = document.querySelector('.transaction-table thead tr');

            // Clear content
            tableBody.innerHTML = '';
            noRecordMessage.style.display = 'none';

            // Update headers
            if (tabType === 'betting') {
                tableHeader.innerHTML = `
        <th>Date/Time</th><th>Game Name</th><th>Amount</th><th>Win/Loss</th>`;
            } else {
                tableHeader.innerHTML = `
        <th>Date/Time</th><th>Method</th><th>Amount</th><th>Status</th>`;
            }

            // Query string params
            const params = new URLSearchParams({
                tab: tabType,
                gameFilter: filters.gameFilter || 'all',
                startDate: filters.startDate || '',
                endDate: filters.endDate || ''
            });

            try {
                const response = await fetch(`backend/get_transactions.php?${params.toString()}`);
                const data = await response.json();

                let filteredData = data;

                // ✅ Apply filters
                if (tabType === 'betting' && (filters.gameFilter || filters.startDate || filters.endDate)) {
                    filteredData = filterTransactions(data, tabType, filters);
                }

                // No records
                if (!filteredData.length) {
                    noRecordMessage.style.display = 'block';
                    return;
                }

                // ✅ Loop through filtered results
                filteredData.forEach(transaction => {
                    const row = document.createElement('tr');

                    if (tabType === 'betting') {
                        row.innerHTML = `
            <td>${transaction.datetime}</td>
            <td>${transaction.gameName}</td>
            <td>${transaction.amount}</td>
            <td style="color: ${transaction.status === 'win' ? '#10b981' : '#ef4444'}; font-weight: bold;">
                ${transaction.profit}
            </td>`;
                    } else {
                        // Deposit / Withdrawal
                        const statusColor =
                            transaction.status === 'Completed' ? '#10b981' :
                            transaction.status === 'Pending' || transaction.status === 'Processing' ? '#f59e0b' :
                            '#6b7280';

                        row.innerHTML = `
            <td>${transaction.datetime}</td>
            <td>${transaction.method}</td>
            <td style="color: ${tabType === 'deposit' ? '#10b981' : '#ef4444'}; font-weight: bold;">${transaction.amount}</td>
            <td style="color: ${statusColor}; font-weight: bold;">${transaction.status}</td>`;
                    }

                    tableBody.appendChild(row);
                });


            } catch (err) {
                console.error("Failed to load data:", err);
                noRecordMessage.style.display = 'block';
            }
        }


        function filterTransactions(data, tabType, filters) {
            return data.filter(transaction => {
                if (tabType === 'betting' && filters.gameFilter && filters.gameFilter !== 'all') {
                    if (transaction.gameName !== filters.gameFilter) {
                        return false;
                    }
                }

                if (filters.startDate || filters.endDate) {
                    const transactionDate = new Date(transaction.datetime.replace(' ', 'T'));

                    if (filters.startDate) {
                        const startDate = new Date(filters.startDate);
                        if (transactionDate < startDate) return false;
                    }

                    if (filters.endDate) {
                        const endDate = new Date(filters.endDate);
                        if (transactionDate > endDate) return false;
                    }
                }

                return true;
            });
        }

        //  END TRANSACTION 

        function openAvatarModal() {
            document.getElementById('avatarModal').style.display = 'flex';
        }

        function closeAvatarModal() {
            document.getElementById('avatarModal').style.display = 'none';
            document.getElementById('predefinedAvatars').style.display = 'none';
        }

        function triggerFileUpload() {
            document.getElementById('avatarInput').click();
        }

        function handleFileUpload(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('currentAvatar').src = e.target.result;
                    closeAvatarModal();
                };
                reader.readAsDataURL(file);
            }
        }

        function closeAvatarModal() {
            document.getElementById('avatarModal').style.display = 'none';
        }

        function triggerFileUpload() {
            document.getElementById('avatarInput').click();
        }

        function handleFileUpload(event) {
            const file = event.target.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('avatar', file);

            fetch('backend/update_avatar.php', {
                    method: 'POST',
                    body: formData
                }).then(res => res.text())
                .then(response => {
                    alert(response);
                    location.reload(); // or update the avatar src dynamically
                });
        }

        function showPredefinedAvatars() {
            const avatars = ['avatar1.png', 'avatar2.png', 'avatar3.png', 'avatar4.png'];
            const container = document.getElementById('predefinedAvatars');
            container.innerHTML = ''; // clear old avatars
            container.style.display = 'flex';

            avatars.forEach(filename => {
                const img = document.createElement('img');
                img.src = 'images/avatars/' + filename;
                img.className = 'avatar-thumb';
                img.style.width = '60px';
                img.style.margin = '5px';
                img.onclick = () => {
                    fetch('backend/update_avatar.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: 'avatar=' + encodeURIComponent(filename)
                        }).then(res => res.text())
                        .then(response => {
                            alert(response);
                            location.reload();
                        });
                };
                container.appendChild(img);
            });
        }

        function generateRandomAvatar() {
            const avatars = ['avatar1.png', 'avatar2.png', 'avatar3.png', 'avatar4.png'];
            const random = avatars[Math.floor(Math.random() * avatars.length)];
            fetch('backend/update_avatar.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'avatar=' + encodeURIComponent(random)
                }).then(res => res.text())
                .then(response => {
                    alert("Random avatar set!");
                    location.reload();
                });
        }


        function copyUserId(id) {
            navigator.clipboard.writeText(id).then(() => {
                alert("User ID copied!");
            });
        }

        // Close modal when clicking outside
        document.getElementById('avatarModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAvatarModal();
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/ethers@5.7.2/dist/ethers.umd.min.js"></script>

    <?php if (isset($_GET['mineMessage'])): ?>
        <script>
            alert("<?= htmlspecialchars($_GET['mineMessage']) ?>");
            // Remove the query string to prevent repeat alerts on refresh
            if (history.replaceState) {
                const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
                history.replaceState(null, "", cleanUrl);
            }
        </script>
    <?php endif; ?>

</body>

</html>