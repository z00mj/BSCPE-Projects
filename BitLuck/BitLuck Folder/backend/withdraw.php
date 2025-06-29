<?php
session_start();
$conn = new mysqli("localhost", "root", "", "ecasinosite");

if (!isset($_SESSION['user_id'])) {
    exit("You must be logged in.");
}

$userId = $_SESSION['user_id'];
$withdrawAmount = floatval($_POST['withdraw_amount']);

if ($withdrawAmount <= 0) {
    exit("Invalid withdraw amount.");
}

// Step 1: Sync on-chain casino balance
shell_exec("node ../my-token/syncCasinoBalance.js");

// Step 2: Get user balance and wallet
$res = $conn->query("SELECT token_balance, wallet_address FROM wallets WHERE user_id = $userId");
$data = $res->fetch_assoc();
$userBalance = $data['token_balance'];
$userWallet = $data['wallet_address'];

// Step 3: Get updated casino balance
$res2 = $conn->query("SELECT token_balance FROM casino_status WHERE id = 1");
$casinoBalance = $res2->fetch_assoc()['token_balance'];

// Step 4: Validate balances
if ($withdrawAmount > $userBalance) exit("Not enough balance.");
if ($withdrawAmount > $casinoBalance) exit("Casino can't pay. Try again later.");

// Step 5: Execute JS to send tokens
$escapedAddress = escapeshellarg($userWallet);
$escapedAmount = escapeshellarg($withdrawAmount);
$cmd = "node ../my-token/sendToken.js $escapedAddress $escapedAmount 2>&1";
$output = shell_exec($cmd);

// Output HTML wrapper
echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <title>BitLuck</title>
    <link rel='icon' href='../images/logo.png' type='image/png'>
    
</head>
<body style='margin: 0; background-color: #0f3730; color: white; font-family: sans-serif;'>";

if (strpos($output, "Successfully sent") !== false) {
    $conn->query("UPDATE wallets SET token_balance = token_balance - $withdrawAmount WHERE user_id = $userId");
    $conn->query("UPDATE casino_status SET token_balance = token_balance - $withdrawAmount WHERE id = 1");
    $conn->query("INSERT INTO transactions (user_id, type, amount) VALUES ($userId, 'withdraw', $withdrawAmount)");

    preg_match('/Tx Hash:\s*(0x[a-fA-F0-9]+)/', $output, $matches);
    $txHash = $matches[1] ?? null;

    $stmt = $conn->prepare("INSERT INTO wallet_activity (user_id, action, amount, tx_hash) VALUES (?, 'withdraw', ?, ?)");
    $stmt->bind_param("ids", $userId, $withdrawAmount, $txHash);
    $stmt->execute();
    $stmt->close();

    echo "<div style='
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background-color: #374151;
        color: #FFF;
        padding: 25px 35px;
        border: 2px solid #E4B44C;
        border-radius: 12px;
        box-shadow: 0 0 20px rgba(228, 180, 76, 0.6);
        text-align: center;
        max-width: 600px;
        white-space: pre-wrap;
    '>
        <strong style='top:100px;'>Withdrawal of {$withdrawAmount} tokens completed.</strong>
        <pre style='
            background-color: #1F2937;
            color: #FFF;
            padding: 10px;
            border-radius: 8px;
            overflow-x: auto;
            text-align: left;
        '>$output</pre>
        <a href='/bitluck/main.php' style='
            color: #E4B44C;
            font-weight: bold;
            text-decoration: none;
        '>Back to Dashboard</a>
    </div>";
} else {
    echo "<div style='
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background-color: #374151;
        color: #FFF;
        padding: 25px 35px;
        border: 2px solid #E4B44C;
        border-radius: 12px;
        box-shadow: 0 0 20px rgba(228, 180, 76, 0.6);
        text-align: center;
        max-width: 600px;
        white-space: pre-wrap;
    '>
        <strong>Withdrawal failed. Details:</strong>
        <pre style='
            background-color: #1F2937;
            color: #FFF;
            padding: 10px;
            border-radius: 8px;
            overflow-x: auto;
            text-align: left;
        '>$output</pre>
        <a href='/bitluck/main.php' style='
            color: #E4B44C;
            font-weight: bold;
            text-decoration: none;
        '>Back to Dashboard</a>
    </div>";
}

echo "</body></html>";
$conn->close();
