<?php
session_start();
include("connect.php");

if (isset($_SESSION['email'])) {
    $email = $_SESSION['email'];
    $result = mysqli_query($conn, "SELECT balance FROM users WHERE email='$email'");
    if ($row = mysqli_fetch_assoc($result)) {
        echo $row['balance'];
        exit;
    }
}
echo "0";
?>
