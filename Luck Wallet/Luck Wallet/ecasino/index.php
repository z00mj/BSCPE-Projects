<?php
session_start();

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Only redirect if we're not already on the homepage and not on the signup page
$current_page = basename($_SERVER['PHP_SELF']);
$show_signup = isset($_GET['show']) && $_GET['show'] === 'signup';

if (isset($_SESSION['email']) && $current_page !== 'homepage.php' && !$show_signup) {
    header("Location: homepage.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>LuckyTime</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="style.css" />
  <style>
    /* --- Forgot Password Link --- */
    .forgot-password {
      text-align: center;
      margin: 15px 0;
    }
    
    .forgot-password-link {
      color: #FFD700;
      text-decoration: none;
      font-size: 14px;
      transition: color 0.3s;
    }
    
    .forgot-password-link:hover {
      color: #FFA500;
      text-decoration: underline;
    }

    /* --- New Top Navbar --- */
    .top-navbar {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 60px;
  background-color: #1e1e1e;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0 20px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.7);
  z-index: 10000;
  user-select: none;
}

.casino-name {
  color: #FFD700;
  font-weight: bolder;
  font-size: 24px;
  letter-spacing: 1px;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.navbar-right {
  color: #ccc;
  font-size: 12px;
  font-family: Arial, sans-serif;
}



.background-area {
  position: fixed;
  top: 0; left: 0;
  width: 100%;
  height: 100vh;
  z-index: 0;
  pointer-events: none;
  overflow: hidden;
  background: #121212;
}

.particle {
  position: absolute;
  bottom: -20px;
  background: rgba(255, 215, 0, 0.3);
  border-radius: 50%;
  animation: float 10s infinite ease-in-out;
  opacity: 0.6;
}

    @keyframes float {
      0% {
        transform: translateY(0) scale(0.5);
        opacity: 0;
      }
      50% {
        opacity: 1;
      }
      100% {
        transform: translateY(-110vh) scale(1.2);
        opacity: 0;
      }
    }

.container {  
  position: relative;
  z-index: 1;
  padding-top: 100x;       
}

#messageBox {
  display: none;
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  background-color: #f44336;
  color: white;
  padding: 10px 20px;
  border-radius: 5px;
  font-family: Arial, sans-serif;
  font-size: 14px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.3);
  z-index: 1000;
  max-width: 80%;
  text-align: center;
}

  </style>
</head>
<body>

  <!-- Top navbar -->
  <div class="top-navbar">
  <div class="casino-name">🎰 LuckyTime</div>
  <div class="navbar-right">©   LuckyTime   2025</div>
</div>


  <!-- Floating particles background -->
  <div class="background-area">
    <?php for ($i = 0; $i < 30; $i++): ?>
      <div class="particle" style="
        width: <?= rand(5, 12) ?>px;
        height: <?= rand(5, 12) ?>px;
        left: <?= rand(0, 100) ?>%;
        animation-delay: <?= rand(0, 10) ?>s;
        animation-duration: <?= rand(8, 16) ?>s;
      "></div>
    <?php endfor; ?>
  </div>

  <!-- Signup container -->
  <div class="container" id="signup" style="display:<?php echo (isset($_GET['show']) && $_GET['show'] === 'signup') ? 'block' : 'none'; ?>;">
    <h1 class="form-title">Register</h1>
    <form method="post" action="register.php">
      <div class="input-group">
        <i class="fas fa-user"></i>
        <input type="text" name="fName" id="fName" placeholder="First Name" required />
        <label for="fname">First Name</label>
      </div>
      <div class="input-group">
        <i class="fas fa-user"></i>
        <input type="text" name="lName" id="lName" placeholder="Last Name" required />
        <label for="lName">Last Name</label>
      </div>
      <div class="input-group">
        <i class="fas fa-envelope"></i>
        <input type="email" name="email" id="email" placeholder="Email" required />
        <label for="email">Email</label>
      </div>
      <div class="input-group">
        <i class="fas fa-lock"></i>
        <input type="password" name="password" id="password" placeholder="Password" required />
        <label for="password">Password</label>
      </div>
      <input type="submit" class="btn" value="Sign Up" name="signUp" />
    </form>

    <div class="links">
      <p>Already Have Account ?</p>
      <button id="signInButton">Sign In</button>
    </div>
  </div>

  <!-- Sign In container -->
  <div class="container" id="signIn" style="display:<?php echo (isset($_GET['show']) && $_GET['show'] === 'signup') ? 'none' : 'block'; ?>;">
    <h2>Welcome to LuckyTime</h2>
    <h1 class="form-title">Sign In</h1>
    <form method="post" action="register.php">
      <div class="input-group">
        <i class="fas fa-envelope"></i>
        <input type="email" name="email" id="email" placeholder="Email" required />
        <label for="email">Email</label>
      </div>
      <div class="input-group">
        <i class="fas fa-lock"></i>
        <input type="password" name="password" id="password" placeholder="Password" required />
        <label for="password">Password</label>
      </div>

      <input type="submit" class="btn" value="Sign In" name="signIn" />
      <div class="forgot-password">
        <a href="forgot-password.php" class="forgot-password-link">Forgot Password?</a>
      </div>
    </form>

    <div class="links">
      <p>Don't have account yet?</p>
      <button id="signUpButton">Sign Up</button>
    </div>
  </div>

  <!-- Notification message box -->
  <div id="messageBox"></div>

  <?php
  // Show popup message if there's a login error or signup success
  if (isset($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
    $message = '';
    if ($error === 'invalid_email') {
      $message = 'Invalid email. Please enter a registered email.';
    } elseif ($error === 'incorrect_password') {
      $message = 'Incorrect password. Please try again.';
    }
    if ($message !== '') {
      echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
          const msgBox = document.getElementById('messageBox');
          msgBox.style.backgroundColor = '#f44336';
          msgBox.textContent = '" . addslashes($message) . "';
          msgBox.style.display = 'block';
          setTimeout(() => { msgBox.style.display = 'none'; }, 5000);
        });
      </script>";
    }
  }
  
  // Show registration success message
  if (isset($_SESSION['signup_success'])) {
    $success_message = $_SESSION['signup_success'];
    unset($_SESSION['signup_success']);
    echo "<script>
      document.addEventListener('DOMContentLoaded', function() {
        const msgBox = document.getElementById('messageBox');
        msgBox.style.backgroundColor = '#4CAF50';
        msgBox.textContent = '" . addslashes($success_message) . "';
        msgBox.style.display = 'block';
        setTimeout(() => { msgBox.style.display = 'none'; }, 5000);
      });
    </script>";
  }
  
  // Show signup error message
  if (isset($_SESSION['signup_error'])) {
    $error_message = $_SESSION['signup_error'];
    unset($_SESSION['signup_error']);
    echo "<script>
      document.addEventListener('DOMContentLoaded', function() {
        const msgBox = document.getElementById('messageBox');
          msgBox.textContent = '$message';
          msgBox.style.display = 'block';
          setTimeout(() => { msgBox.style.display = 'none'; }, 4000);
        });
      </script>";
    }
  

  // Optional: show signup errors if any
  if (isset($_SESSION['signup_error'])) {
    $signup_error = $_SESSION['signup_error'];
    unset($_SESSION['signup_error']);
    echo "<script>
      document.addEventListener('DOMContentLoaded', function() {
        const msgBox = document.getElementById('messageBox');
        msgBox.textContent = '".addslashes($signup_error)."';
        msgBox.style.display = 'block';
        setTimeout(() => { msgBox.style.display = 'none'; }, 4000);
      });
    </script>";
  }
  ?>

  <script src="script.js"></script>
</body>
</html>
