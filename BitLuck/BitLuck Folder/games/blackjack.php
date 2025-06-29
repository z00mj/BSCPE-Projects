<!--final blackjack game-->
<?php
session_start();
$conn = new mysqli("localhost", "root", "", "ecasinosite");
$userId = $_SESSION['user_id'];
$wallet = $conn->query("SELECT token_balance FROM wallets WHERE user_id = $userId")->fetch_assoc();
$balance = $wallet['token_balance'];

function drawCard()
{
    $suits = ['♠', '♥', '♦', '♣'];
    $ranks = ['2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6, '7' => 7, '8' => 8, '9' => 9, '10' => 10, 'J' => 10, 'Q' => 10, 'K' => 10, 'A' => 11];
    $rank = array_rand($ranks);
    $suit = $suits[array_rand($suits)];
    return ['card' => "$rank$suit", 'value' => $ranks[$rank]];
}

function handValue($hand)
{
    $total = 0;
    $aces = 0;
    foreach ($hand as $c) {
        $total += $c['value'];
        if (strpos($c['card'], 'A') !== false) $aces++;
    }
    while ($total > 21 && $aces--) $total -= 10;
    return $total;
}

// Handle bet placement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_bet'])) {
    $bet = floatval($_POST['bet_amount']);
    if ($bet <= 0 || $bet > $balance) {
        $error = "<i class='fas fa-times-circle'> </i> Invalid bet.";
    } else {
        $_SESSION['bj_bet'] = $bet;
        $_SESSION['bj_player'] = [drawCard(), drawCard()];
        $_SESSION['bj_dealer'] = [drawCard()];
        $_SESSION['bj_active'] = true;

        // Deduct bet from player and add to casino
        $conn->query("UPDATE wallets SET token_balance = token_balance - $bet WHERE user_id = $userId");
        $conn->query("UPDATE casino_status SET token_balance = token_balance + $bet WHERE id = 1");
    }
}

// Hit
if (isset($_POST['hit']) && $_SESSION['bj_active']) {
    $_SESSION['bj_player'][] = drawCard();
    if (handValue($_SESSION['bj_player']) > 21) {
        $_SESSION['bj_result'] = "<i class='fas fa-bomb'></i> You busted!";
        $_SESSION['bj_active'] = false;
        $conn->query("INSERT INTO games (user_id, game_type, result, bet_amount) VALUES ($userId, 'Blackjack', 'Bust', {$_SESSION['bj_bet']})");
        $conn->query("INSERT INTO transactions (user_id, type, amount) VALUES ($userId, 'bet', {$_SESSION['bj_bet']})");
    }
}

// Stand
if (isset($_POST['stand']) && $_SESSION['bj_active']) {
    while (handValue($_SESSION['bj_dealer']) < 17) {
        $_SESSION['bj_dealer'][] = drawCard();
    }

    $playerVal = handValue($_SESSION['bj_player']);
    $dealerVal = handValue($_SESSION['bj_dealer']);
    $bet = $_SESSION['bj_bet'];

    if ($dealerVal > 21 || $playerVal > $dealerVal) {
        $winAmount = $bet * 2;
        $_SESSION['bj_result'] = "<i class='fas fa-trophy'></i> You won! (+ $winAmount tokens)";
        $conn->query("UPDATE wallets SET token_balance = token_balance + $winAmount + $bet WHERE user_id = $userId");
        $conn->query("UPDATE casino_status SET token_balance = token_balance - $winAmount WHERE id = 1");
        $conn->query("INSERT INTO transactions (user_id, type, amount) VALUES ($userId, 'win', $winAmount)");
        $conn->query("INSERT INTO games (user_id, game_type, result, bet_amount) VALUES ($userId, 'Blackjack', 'Win', $bet)");
    } elseif ($playerVal == $dealerVal) {
        // Push: return bet
        $_SESSION['bj_result'] = "<i class='fas fa-handshake'></i> Push. You get your bet back.";
        $conn->query("UPDATE wallets SET token_balance = token_balance + $bet WHERE user_id = $userId");
        $conn->query("UPDATE casino_status SET token_balance = token_balance - $bet WHERE id = 1");
        $conn->query("INSERT INTO games (user_id, game_type, result, bet_amount) VALUES ($userId, 'Blackjack', 'Push', $bet)");
    } else {
        $_SESSION['bj_result'] = "<i class='fas fa-times-circle'></i> Dealer wins.";
        $conn->query("INSERT INTO games (user_id, game_type, result, bet_amount) VALUES ($userId, 'Blackjack', 'Lose', $bet)");
        $conn->query("INSERT INTO transactions (user_id, type, amount) VALUES ($userId, 'bet', $bet)");
    }

    $_SESSION['bj_active'] = false;
}

// Restart game
if (isset($_POST['reset'])) {
    unset($_SESSION['bj_bet'], $_SESSION['bj_player'], $_SESSION['bj_dealer'], $_SESSION['bj_result'], $_SESSION['bj_active']);
    header("Location: blackjack.php");
    exit();
}

// UI helper
function renderHand($hand)
{
    $cards = array_map(fn($c) => $c['card'], $hand);
    return implode(", ", $cards);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <title>Blackjack 21 | BitLuck</title>
    <link rel="icon" href="../images/BLACKJACK-LOGO.png" type="image/png">
    <style>
        /* ===== LUXURY CASINO THEME ===== */
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Montserrat:wght@400;600;700&display=swap');

        :root {
            --gold: #FFD700;
            --gold-light: #FFECB3;
            --gold-dark: #C9A227;
            --red: #D10000;
            --red-dark: #9C0000;
            --red-light: #FF5252;
            --diamond: #B9F2FF;
            --silver: #C0C0C0;
            --black: #0A0A0A;
            --black-light: #1A1A1A;
            --green: #00A86B;
            --white: #FFFFFF;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            --glow: 0 0 15px rgba(255, 215, 0, 0.5);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background:
                radial-gradient(circle at center, var(--black-light) 0%, var(--black) 100%),
                url('https://www.transparenttextures.com/patterns/dark-leather.png');
            color: var(--white);
            min-height: 100vh;
            padding: 40px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        /* ===== GAME CONTAINER ===== */
        .game-container {
            position: relative;
            width: 100%;
            max-width: 900px;
            background:
                linear-gradient(135deg, rgba(10, 10, 10, 0.9) 0%, rgba(26, 26, 26, 0.9) 100%),
                url('https://www.transparenttextures.com/patterns/carbon-fibre.png');
            border-radius: 16px;
            padding: 40px;
            box-shadow: var(--shadow);
            border: 1px solid var(--gold);
            overflow: hidden;
            z-index: 1;
        }

        .game-container::before {
            content: '';
            position: absolute;
            top: -10px;
            left: -10px;
            right: -10px;
            bottom: -10px;
            border: 2px solid var(--red);
            border-radius: 20px;
            z-index: -1;
            opacity: 0.5;
        }

        h2 {
            font-family: 'Montserrat', sans-serif;
            color: var(--gold);
            font-size: 2.8rem;
            text-align: center;
            margin-bottom: 1.5rem;
            text-shadow: 0 2px 10px rgba(255, 215, 0, 0.3);
            letter-spacing: 2px;
            position: relative;
            padding-bottom: 15px;
        }

        h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 150px;
            height: 3px;
            background: linear-gradient(90deg, transparent 0%, var(--gold) 50%, transparent 100%);
        }

        .balance {
            font-size: 1.3rem;
            text-align: center;
            margin-bottom: 2rem;
            color: var(--gold);
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: rgba(0, 0, 0, 0.5);
            padding: 15px 25px;
            border-radius: 50px;
            border: 1px solid var(--gold);
            box-shadow: var(--glow);
        }

        .balance::before {
            content: '🪙';
            /* fa-coins */
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            font-size: 1.5rem;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
        }

        /* ===== BETTING FORM ===== */
        .bet-form {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.8rem;
            margin-bottom: 2rem;
        }

        .bet-form label {
            font-size: 1.2rem;
            color: var(--gold-light);
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .bet-form label::before {
            content: '🎲';
            /* fa-slot-machine */
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
        }

        .bet-input-container {
            position: relative;
            display: flex;
            align-items: center;
        }

        .bet-input-container::before {
            content: '🪙';
            /* fa-gem */
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            left: 15px;
            font-size: 1.3rem;
            z-index: 1;
        }

        .bet-form input {
            padding: 15px 20px 15px 50px;
            border-radius: 8px;
            border: 2px solid var(--gold);
            background: rgba(0, 0, 0, 0.5);
            color: var(--gold-light);
            font-size: 1.2rem;
            width: 250px;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: var(--glow);
            font-weight: 600;
        }

        .bet-form input:focus {
            outline: none;
            border-color: var(--red-light);
            box-shadow: 0 0 15px rgba(255, 82, 82, 0.5);
        }

        .bet-form button {
            background: linear-gradient(135deg, var(--red), var(--red-dark));
            color: var(--gold-light);
            border: none;
            padding: 15px 40px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(209, 0, 0, 0.4);
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
            overflow: hidden;
        }

        .bet-form button::before {
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

        .bet-form button:hover {
            background: linear-gradient(135deg, var(--red-light), var(--red));
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 82, 82, 0.6);
        }

        /* ===== HANDS CONTAINER ===== */
        .hand-container {
            display: flex;
            justify-content: space-between;
            gap: 30px;
            margin: 40px 0;
        }

        .hand {
            flex: 1;
            padding: 30px;
            background: rgba(0, 0, 0, 0.4);
            border-radius: 12px;
            border: 1px solid var(--gold);
            min-height: 280px;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            box-shadow: var(--glow);
            position: relative;
            overflow: hidden;
        }

        .hand::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background:
                radial-gradient(circle at 20% 30%, rgba(255, 215, 0, 0.05) 0%, transparent 30%),
                linear-gradient(to bottom, transparent 0%, rgba(209, 0, 0, 0.05) 100%);
            z-index: -1;
        }

        .hand:hover {
            border-color: var(--red-light);
            box-shadow: 0 0 20px rgba(255, 82, 82, 0.5);
        }

        .hand-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--gold);
            margin-bottom: 25px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
        }

        .player-hand .hand-title::before {
            content: '\f007';
            /* fa-user */
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            font-size: 1.5rem;
        }

        .dealer-hand .hand-title::before {
            content: '🎩';
            /* fa-hat-wizard (closest to top hat) */
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
        }

        .cards {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: auto;
        }

        /* ===== CARD STYLES ===== */
        .card {
            width: 90px;
            height: 130px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            font-weight: bold;
            position: relative;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
            background: var(--white);
            color: var(--black);
            transform-style: preserve-3d;
        }

        /* Red cards (hearts and diamonds) */
        .card:not(.hidden):contains('♥'),
        .card:not(.hidden):contains('♦') {
            color: var(--red);
            background: linear-gradient(135deg, #fff, #f5f5f5);
        }

        /* Black cards (spades and clubs) */
        .card:not(.hidden):contains('♠'),
        .card:not(.hidden):contains('♣') {
            color: var(--black);
            background: linear-gradient(135deg, #fff, #f5f5f5);
        }

        .card::before,
        .card::after {
            position: absolute;
            font-size: 1.2rem;
            font-weight: bold;
        }

        .card::before {
            top: 10px;
            left: 10px;
        }

        .card::after {
            bottom: 10px;
            right: 10px;
            transform: rotate(180deg);
        }

        .card:hover {
            transform: translateY(-10px) rotate(2deg);
            box-shadow: 0 15px 25px rgba(0, 0, 0, 0.4);
        }

        /* Hidden dealer card */
        .dealer-hand .card.hidden {
            background: linear-gradient(135deg, var(--black-light), var(--black));
            color: transparent;
            position: relative;
            border: 2px solid var(--red);
        }

        .dealer-hand .card.hidden::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background:
                repeating-linear-gradient(45deg,
                    rgba(255, 82, 82, 0.1),
                    rgba(255, 82, 82, 0.1) 2px,
                    transparent 2px,
                    transparent 4px);
            border-radius: 8px;
        }

        .dealer-hand .card.hidden::after {
            content: '?';
            color: var(--red);
            position: absolute;
            transform: none;
            font-size: 2.5rem;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
        }

        /* ===== CONTROLS ===== */
        .controls {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 40px;
        }

        .controls button {
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: var(--black);
            border: none;
            padding: 15px 30px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(255, 215, 0, 0.3);
            min-width: 150px;
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
            overflow: hidden;
        }

        .controls button[name="hit"] {
            background: linear-gradient(135deg, var(--green), #008552);
        }

        .controls button[name="stand"] {
            background: linear-gradient(135deg, var(--silver), #a0a0a0);
            color: var(--black);
        }

        .controls button[name="reset"] {
            background: linear-gradient(135deg, var(--red), var(--red-dark));
            color: var(--gold-light);
        }

        .controls button:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.4);
        }

        /* ===== MESSAGES ===== */
        .message {
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin: 30px 0;
            font-weight: 700;
            font-size: 1.3rem;
            animation: fadeIn 0.5s ease;
            border-left: 4px solid;
            background: rgba(0, 0, 0, 0.5);
            box-shadow: var(--shadow);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .win {
            color: var(--gold);
            border-color: var(--gold);
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
        }

        .win::before {
            content: '';
            /* fa-bomb */
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
        }

        .lose {
            color: var(--red-light);
            border-color: var(--red-light);
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
        }

        .lose::before {
            content: '';
            margin-right: 10px;
            font-size: 1.5rem;
        }


        .push {
            color: var(--silver);
            border-color: var(--silver);
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
        }

        .push::before {
            content: '🤝';
            /* fa-handshake */
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
        }

        /* ===== BACK LINK ===== */
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
            content: '\f104';
            /* fa-arrow-left */
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
        }

        .back-link:hover {
            background: rgba(209, 0, 0, 0.5);
            color: var(--gold-light);
            transform: translateX(-5px);
            box-shadow: var(--glow);
        }

        /* ===== RESPONSIVE DESIGN ===== */
        @media (max-width: 768px) {
            .game-container {
                padding: 30px 20px;
            }

            h2 {
                font-size: 2.2rem;
            }

            .hand-container {
                flex-direction: column;
                gap: 20px;
            }

            .hand {
                min-height: 220px;
                padding: 20px;
            }

            .card {
                width: 70px;
                height: 110px;
                font-size: 1.8rem;
            }

            .controls {
                flex-direction: column;
                gap: 15px;
            }

            .controls button {
                width: 100%;
            }
        }

        /* ===== RESPONSIVE DESIGN ===== */
        @media (max-width: 768px) {
            .game-container {
                padding: 30px 20px;
                margin: 10px;
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

            .hand-container {
                flex-direction: column;
                gap: 20px;
                margin: 1.5rem 0;
            }

            .hand {
                min-height: 200px;
                padding: 15px;
            }

            .hand-title {
                font-size: 1.1rem;
                margin-bottom: 1rem;
            }

            .cards {
                gap: 10px;
            }

            .card {
                width: 60px;
                height: 90px;
                font-size: 1.5rem;
            }

            .bet-form {
                gap: 1.5rem;
            }

            .bet-form label {
                font-size: 1rem;
                margin-bottom: 0.8rem;
            }

            .bet-input-container input {
                width: 200px;
                font-size: 1rem;
                padding: 10px 15px;
            }

            .controls {
                flex-direction: column;
                align-items: center;
                gap: 0.8rem;
            }

            .controls button {
                width: 200px;
                padding: 12px 20px;
                font-size: 0.9rem;
            }

            .message {
                padding: 15px 20px;
                font-size: 1.1rem;
                margin: 1.5rem 0;
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
            }

            h2 {
                font-size: 1.8rem;
            }

            .balance {
                font-size: 0.9rem;
                padding: 8px 12px;
            }

            .hand {
                min-height: 180px;
                padding: 12px;
            }

            .hand-title {
                font-size: 1rem;
            }

            .card {
                width: 50px;
                height: 75px;
                font-size: 1.3rem;
            }

            .bet-input-container input {
                width: 180px;
                font-size: 0.9rem;
            }

            .controls button {
                width: 180px;
                font-size: 0.8rem;
                padding: 10px 16px;
            }

            .message {
                padding: 12px 15px;
                font-size: 1rem;
            }
        }

        @media (max-width: 360px) {
            .game-container {
                padding: 15px 10px;
            }

            h2 {
                font-size: 1.5rem;
            }

            .hand {
                min-height: 160px;
                padding: 10px;
            }

            .card {
                width: 45px;
                height: 68px;
                font-size: 1.1rem;
            }

            .bet-input-container input {
                width: 160px;
            }

            .controls button {
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

            .hand-container {
                gap: 40px;
            }

            .hand {
                min-height: 280px;
                padding: 30px;
            }

            .card {
                width: 90px;
                height: 135px;
                font-size: 2.5rem;
            }

            .bet-input-container input {
                width: 250px;
                font-size: 1.2rem;
            }

            .controls button {
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

            .hand-container {
                flex-direction: row;
                gap: 20px;
                margin: 1rem 0;
            }

            .hand {
                min-height: 160px;
                padding: 15px;
            }

            .card {
                width: 50px;
                height: 75px;
                font-size: 1.3rem;
            }
        }
    </style>
</head>

<body>
    <div class="game-container">
        <h2> BLACKJACK 21</h2>
        <p class="balance">Your Balance: <?= number_format($balance) ?> BTL</p>

        <?php if (!isset($_SESSION['bj_bet'])): ?>
            <form method="POST" class="bet-form">
                <label>Enter Bet Amount:</label>
                <div class="bet-input-container">
                    <input type="number" name="bet_amount" min="10" max="10000" required>
                </div>
                <button type="submit" name="place_bet">Place Bet</button>
            </form>
            <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>

        <?php else: ?>
            <div class="hand-container">
                <div class="hand player-hand">
                    <div class="hand-title">Your Hand (<?= handValue($_SESSION['bj_player']) ?>)</div>
                    <div class="cards">
                        <?php foreach ($_SESSION['bj_player'] as $card): ?>
                            <div class="card"><?= $card['card'] ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="hand dealer-hand">
                    <div class="hand-title">Dealer's Hand (<?= $_SESSION['bj_active'] ? "?" : handValue($_SESSION['bj_dealer']) ?>)</div>
                    <div class="cards">
                        <?php foreach ($_SESSION['bj_dealer'] as $i => $card): ?>
                            <div class="card <?= ($i == 0 && $_SESSION['bj_active']) ? 'hidden' : '' ?>">
                                <?= $card['card'] ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <?php if (isset($_SESSION['bj_active']) && $_SESSION['bj_active']): ?>
                <form method="POST" class="controls">
                    <button type="submit" name="hit">Hit</button>
                    <button type="submit" name="stand">Stand</button>
                </form>
            <?php else: ?>
                <?php if (isset($_SESSION['bj_result'])): ?>
                    <?php
                    $msgClass = 'message';
                    if (strpos($_SESSION['bj_result'], 'won') !== false) $msgClass .= ' win';
                    elseif (strpos($_SESSION['bj_result'], 'busted') !== false || strpos($_SESSION['bj_result'], 'Dealer wins') !== false) $msgClass .= ' lose';
                    elseif (strpos($_SESSION['bj_result'], 'Push') !== false) $msgClass .= ' push';
                    ?>
                    <div class="<?= $msgClass ?>"><?= $_SESSION['bj_result'] ?></div>
                <?php endif; ?>
                <form method="POST" class="controls">
                    <button type="submit" name="reset">Play Again</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
        <div style="text-align: center;">
            <a href="../main.php" class="back-link">Back to Dashboard</a>
        </div>
    </div>
</body>

</html>