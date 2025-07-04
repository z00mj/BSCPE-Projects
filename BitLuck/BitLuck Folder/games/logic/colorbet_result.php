<?php
session_start();
$conn = new mysqli("localhost", "root", "", "ecasinosite");
$userId = $_SESSION['user_id'];

if (!isset($_POST['bet_amount']) || !isset($_POST['color'])) {
    header("Location: colorbet.php");
    exit();
}

$betAmount = floatval($_POST['bet_amount']);
$chosenColor = $_POST['color'];

$res = $conn->query("SELECT token_balance FROM wallets WHERE user_id = $userId");
$data = $res->fetch_assoc();
$balance = $data['token_balance'];

if ($betAmount > $balance) exit("❌ Insufficient funds");

// Determine random outcome
$rand = rand(1, 100);
if ($rand <= 45) $outcome = "Red";
elseif ($rand <= 90) $outcome = "Black";
elseif ($rand <= 95) $outcome = "Green";
else $outcome = "Blue";

// Calculate win/loss
$win = $chosenColor === $outcome;
$payout = 0;

if ($win) {
    switch ($outcome) {
        case "Green":
            $payout = $betAmount * 14;
            break;
        case "Blue":
            $payout = $betAmount * 5;
            break;
        default: // Red or Black
            $payout = $betAmount * 2;
            break;
    }

    $newBalance = $balance + $payout;
    $conn->query("UPDATE casino_status SET token_balance = token_balance - $payout WHERE id = 1");
} else {
    $newBalance = $balance - $betAmount;
    $conn->query("UPDATE casino_status SET token_balance = token_balance + $betAmount WHERE id = 1");
}


$conn->query("UPDATE wallets SET token_balance = $newBalance WHERE user_id = $userId");
$conn->query("INSERT INTO games (user_id, game_type, result, bet_amount) VALUES ($userId, 'Color Bet', '$outcome', $betAmount)");
if ($win) {
    $conn->query("INSERT INTO transactions (user_id, type, amount) VALUES ($userId, 'win', $payout)");
} else {
    $conn->query("INSERT INTO transactions (user_id, type, amount) VALUES ($userId, 'bet', $betAmount)");
}


?>

<h3>The wheel landed on <b><?= $outcome ?></b>!</h3>
<h3>You <?= $win ? "won ₱$payout!" : "lost ₱$betAmount." ?></h3>
<h3>New Balance: ₱<?= $newBalance ?></h3>

<a href="colorbet.php">🎮 Play Again</a>
<br>
<a href="../main.php">🔙 Back to Dashboard</a>