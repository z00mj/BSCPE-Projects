<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die("You must be logged in.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'];
    $depositAmount = floatval($_POST['deposit_amount'] ?? 0);
    $walletAddress = trim($_POST['wallet_address'] ?? '');

    if ($depositAmount <= 0) {
        die("Invalid deposit amount.");
    }

    if (empty($walletAddress)) {
        die("Wallet address not provided.");
    }

    $conn = new mysqli("localhost", "root", "", "ecasinosite");
    if ($conn->connect_error) {
        die("DB Connection failed: " . $conn->connect_error);
    }

    // Step 1: Update user's token balance and wallet address
    $stmt = $conn->prepare("
        UPDATE wallets 
        SET 
            token_balance = token_balance + ?, 
            wallet_address = IFNULL(NULLIF(wallet_address, ''), ?) 
        WHERE user_id = ?
    ");
    $stmt->bind_param("dsi", $depositAmount, $walletAddress, $userId);
    $stmt->execute();

    // Step 2: Update casino's on-chain balance record
    $conn->query("UPDATE casino_status SET token_balance = token_balance + $depositAmount WHERE id = 1");

    // Step 3: Log to wallet_activity table
    $txHash = $_POST['tx_hash'] ?? null; // Optional if available from frontend
    $logStmt = $conn->prepare("
        INSERT INTO wallet_activity (user_id, action, amount, tx_hash) 
        VALUES (?, 'deposit', ?, ?)
    ");
    $logStmt->bind_param("ids", $userId, $depositAmount, $txHash);
    $logStmt->execute();

    if ($stmt->affected_rows >= 0) {
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
        font-family: sans-serif;
        z-index: 9999;
        text-align: center;
    '>
        <strong>Deposit successful!</strong><br><br>
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
        font-family: sans-serif;
        z-index: 9999;
        text-align: center;
    '>
        <strong>Deposit failed!</strong><br><br>
        <a href='/bitluck/main.php' style='
            color: #E4B44C;
            font-weight: bold;
            text-decoration: none;
        '>Back to Dashboard</a>
    </div>";
}


    $stmt->close();
    $logStmt->close();
    $conn->close();
}
?>
