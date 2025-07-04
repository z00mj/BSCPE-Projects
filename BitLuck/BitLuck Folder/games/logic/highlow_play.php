<?php
session_start();
$conn = new mysqli("localhost", "root", "", "ecasinosite");
$userId = $_SESSION['user_id'];

$betAmount = floatval($_POST['bet_amount']);
$guess = $_POST['guess'];

$res = $conn->query("SELECT token_balance FROM wallets WHERE user_id = $userId");
$data = $res->fetch_assoc();
$balance = $data['token_balance'];

if ($betAmount > $balance) exit("❌ Insufficient balance.");

$cardNames = [2, 3, 4, 5, 6, 7, 8, 9, 10, 'J', 'Q', 'K', 'A'];
$cardValues = range(2, 14);

$firstIndex = rand(0, 12);
$secondIndex = rand(0, 12);
$firstCard = $cardNames[$firstIndex];
$secondCard = $cardNames[$secondIndex];

$firstValue = $cardValues[$firstIndex];
$secondValue = $cardValues[$secondIndex];

$result = "";
$win = false;

if (($guess === "Higher" && $secondValue > $firstValue) ||
    ($guess === "Lower" && $secondValue < $firstValue)) {
    
    // Win logic
    $_SESSION['highlow_streak'] = ($_SESSION['highlow_streak'] ?? 0) + 1;
    $streak = $_SESSION['highlow_streak'];
    $multiplier = 2 + ($streak - 1) * 0.1; // +10% per streak
    $payout = round($betAmount * $multiplier, 2);
    
    $newBalance = $balance + $payout;
    $conn->query("UPDATE casino_status SET token_balance = token_balance - $payout WHERE id = 1");
    $conn->query("INSERT INTO transactions (user_id, type, amount) VALUES ($userId, 'win', $payout)");
    
    $result = "✅ You guessed right! You won $payout tokens (Streak: $streak)";
    $win = true;
} else {
    $_SESSION['highlow_streak'] = 0;
    $newBalance = $balance - $betAmount;
    $conn->query("UPDATE casino_status SET token_balance = token_balance + $betAmount WHERE id = 1");
    $conn->query("INSERT INTO transactions (user_id, type, amount) VALUES ($userId, 'bet', $betAmount)");
    
    $result = "❌ Wrong guess. You lost $betAmount tokens.";
}

$conn->query("UPDATE wallets SET token_balance = $newBalance WHERE user_id = $userId");
$conn->query("INSERT INTO games (user_id, game_type, result, bet_amount) VALUES ($userId, 'High-Low', '$result', $betAmount)");
?>

<h3>First Card: <?= $firstCard ?></h3>
<h3>Second Card: <?= $secondCard ?></h3>
<p><?= $result ?></p>
<p>New Balance: <?= $newBalance ?> tokens</p>

<a href="../highlow.php">🎮 Play Again</a>
<br><a href="../main.php">🔙 Back to Dashboard</a>
