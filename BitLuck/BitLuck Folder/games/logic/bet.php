<?php
session_start();
$conn = new mysqli("localhost", "root", "", "ecasinosite");
$userId = $_SESSION['user_id'];

$betAmount = floatval($_POST['bet_amount']);
$choice = $_POST['choice'];

// Get user's balance and wallet
$res = $conn->query("SELECT token_balance, wallet_address FROM wallets WHERE user_id = $userId");
$data = $res->fetch_assoc();
$balance = $data['token_balance'];
$walletAddress = $data['wallet_address'];

if ($betAmount > $balance) exit("❌ Insufficient funds");

$outcome = rand(0, 1) ? "Heads" : "Tails";
$win = $choice == $outcome;

// Update user balance
$newBalance = $balance + ($win ? $betAmount : -$betAmount);
$conn->query("UPDATE wallets SET token_balance = $newBalance WHERE user_id = $userId");
// Update casino's token balance
if ($win) {
    // Casino loses tokens
    $conn->query("UPDATE casino_status SET token_balance = token_balance - $betAmount WHERE id = 1");
    
} else {
    // Casino gains tokens
    $conn->query("UPDATE casino_status SET token_balance = token_balance + $betAmount WHERE id = 1");
}

// Log game and transaction
$conn->query("INSERT INTO games (user_id, game_type, result, bet_amount) VALUES ($userId, 'Coin Flip', '$outcome', $betAmount)");
$conn->query("INSERT INTO transactions (user_id, type, amount) VALUES ($userId, '" . ($win ? "win" : "bet") . "', $betAmount)");

$userId = $_SESSION['user_id'];
$newBalance = $_POST['balance'] ?? null;

if ($newBalance !== null) {
    $stmt = $conn->prepare("UPDATE wallets SET token_balance = ? WHERE user_id = ?");
    $stmt->bind_param("di", $newBalance, $userId);
    $stmt->execute();
    echo "Balance updated";
} else {
    echo "Invalid data";
}

?>

<h3>The coin landed on <b><?= $outcome ?></b>!</h3>
<h3>You <?= $win ? "won!" : "lost." ?></h3>
<a href="coinflip.php">Play again</a>
<a href="../main.php">Back to Dashboard</a>

