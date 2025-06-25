<?php
session_start();
include("connect.php");

// Redirect to login if not logged in
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$email = $_SESSION['email'];
$query = mysqli_query($conn, "SELECT firstName, lastName, email, balance, date_joined FROM users WHERE email='$email'");

if (!$query || mysqli_num_rows($query) == 0) {
    echo "User not found.";
    exit();
}

$user = mysqli_fetch_assoc($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>LuckyTime | Profile</title>
<style>
  

body {
  margin: 0; 
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  background-image: url('hero.webp'); /* Add this line */
  background-size: cover; /* Add this line */
  background-position: center; /* Add this line */
  background-repeat: no-repeat; /* Add this line */
  background-attachment: fixed; /* Add this line */
}
body::before {
  content: "";
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.7); /* Add this line */
  z-index: -1; /* Add this line */
}
.container {
  max-width: 500px;
  margin: 120px auto 40px;
  padding: 30px;
  border-radius: 15px;
  box-shadow: 0 0 20px rgba(255, 215, 0, 0.2);
  text-align: center;
  background-color: rgb(29, 27, 27); /* Change alpha to 1 for solid color */
  position: relative; /* Add this line */
  z-index: 100; /* Add this line */
}

  h1 {
    color: #FFD700;
    margin-bottom: 20px;
  }
  p {
    text-align: center;
    color: white;
    font-size: 18px;
    margin: 10px 0;
  }
  .label {
    font-weight: bold;
    color: #FFD700;
  }
  .balance {
    background: rgba(255, 215, 0, 0.2);
    color: #FFD700;
    padding: 8px 15px;
    border-radius: 20px;
    display: inline-block;
    font-weight: bold;
    margin-top: 10px;
  }
  .btn-link {
    display: inline-block;
    margin-top: 25px;
    padding: 10px 20px;
    background-color: #FFD700;
    color: #121212;
    font-weight: bold;
    text-decoration: none;
    border-radius: 8px;
    transition: background-color 0.3s ease;
  }
  .btn-link:hover {
    background-color: #e6c200;
  }
  .go-back {
    display: inline-block;
    margin: 40px auto 0;
    padding: 10px 20px;
    background-color: #FFD700;
    color: #121212;
    font-weight: bold;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-family: inherit;
    transition: background-color 0.3s ease;
  }
  .go-back:hover {
    background-color: #e6c200;
  }

   .background-area {
      position: fixed;
      top: 0; left: 0;
      width: 100%;
      height: 100vh;
      z-index: 0;
      pointer-events: none;
      overflow: hidden;
    }

    .particle {
      position: absolute;
      background: rgba(255, 215, 0, 0.3);
      border-radius: 50%;
      animation: float 10s infinite ease-in-out;
      opacity: 0.6;
    }

    @keyframes float {
      0% { transform: translateY(100vh) scale(0.5); opacity: 0; }
      50% { opacity: 1; }
      100% { transform: translateY(-10vh) scale(1.2); opacity: 0; }
    }

</style>
</head>
<body>

  <div class="container">
    <h1>Your Profile</h1>

    <p><span class="label">Name:</span> <?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName']); ?></p>
    <p><span class="label">Email:</span> <?php echo htmlspecialchars($user['email']); ?></p>
    <p><span class="label">Joined:</span> <?php echo date("F j, Y", strtotime($user['date_joined'])); ?></p>
    <p><span class="label">Balance:</span> 
      <span class="balance">₱<?php echo number_format($user['balance'], 2); ?></span>
    </p>

   

    <button class="go-back" onclick="history.back()">Go Back</button>
  </div>

    <div class="background-area">
    <?php for ($i = 0; $i < 30; $i++): ?>
      <div class="particle" style="
        width: <?= rand(5, 12) ?>px;
        height: <?= rand(5, 12) ?>px;
        left: <?= rand(0, 100) ?>%;
        animation-delay: <?= rand(0, 10) ?>s;
        animation-duration: <?= rand(8, 15) ?>s;
      "></div>
    <?php endfor; ?>
  </div>


</body>
</html>
