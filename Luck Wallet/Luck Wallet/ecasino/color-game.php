<?php
session_start();
include("connect.php");

// Get initial balance
$balance = 0;
if (isset($_SESSION['email'])) {
    $email = $_SESSION['email'];
    $result = mysqli_query($conn, "SELECT balance FROM users WHERE email='$email'");
    if ($row = mysqli_fetch_assoc($result)) {
        $balance = $row['balance'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>LuckyTime Color Game</title>
<style>
  * {
    box-sizing: border-box;
  }

  body {
    margin: 0;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #1c1a17 0%, #3c352a 100%);
    color: #d4af37;
   
  }

  .hud {
    display: flex;
    flex-direction: column;
    height: 100vh;
  }

  .hud-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(90deg, #5c4a1a, #a68939);
    box-shadow: 0 4px 12px rgba(212, 175, 55, 0.6);
    padding: 15px 40px;
    border-bottom: 2px solid #c9b037;
    letter-spacing: 1px;
  }

  .btn-back {
    background: #d4af37;
    border: none;
    color: #3b2e08;
    padding: 12px 24px;
    font-size: 18px;
    font-weight: bold;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 0 8px #b28f2d inset;
    text-transform: uppercase;
    font-family: 'Arial Black', Gadget, sans-serif;
  }

  .btn-back:hover {
    background: #c9b037;
    box-shadow: 0 0 15px #f7dc6f;
  }

  .balance {
    font-size: 22px;
    font-weight: 700;
    text-shadow: 0 0 6px #b38b00, 0 0 12px #9a7a00;
    color: rgb(0, 0, 0);
  }

  .game-port {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    background: #2e2a1f;
    background-image: radial-gradient(circle at center, rgba(212, 175, 55, 0.1) 0%, transparent 80%);

  }

  .game-port iframe {
    width: 100%;
    height: 100%;
    border: none;
  }
</style>
</head>
<body>

<div class="hud">
  <div class="hud-top">
    <button class="btn-back" onclick="window.location.href='homepage.php#welcome'">← Go Back</button>
    <div class="balance">Balance: ₱<span id="balance-amount"><?php echo number_format($balance, 2); ?></span></div>
  </div>

  <div class="game-port">
    <iframe src="demo-game.html" allowfullscreen></iframe>
  </div>
</div>

<script>
// Function to update the balance display on this page
function updateBalanceDisplay(newBalance) {
    document.getElementById('balance-amount').textContent = parseFloat(newBalance).toFixed(2);
}

// Listen for messages from the iframe
window.addEventListener('message', function(event) {
    // Ensure the message is from your iframe (for security)
    // You might want to check event.origin if your iframe is on a different domain
    // For a school project, just checking for a specific message type might be enough
    if (event.data && event.data.type === 'GAME_OVER' && typeof event.data.newBalance !== 'undefined') {
        console.log("Received new balance from iframe:", event.data.newBalance);
        updateBalanceDisplay(event.data.newBalance);
    }
});
</script>

</body>
</html>
