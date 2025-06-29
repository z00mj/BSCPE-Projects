<?php
session_start();
$conn = new mysqli("localhost", "root", "", "ecasinosite");


if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

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
