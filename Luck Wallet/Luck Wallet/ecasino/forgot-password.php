<?php 
session_start();
$error = '';
$success = '';

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Forgot Password - LuckyTime</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="style.css" />
  <style>
    /* --- Forgot Password Container --- */
    .forgot-container {
      background: #1c1c1c;
      width: 450px;
      padding: 1.5rem;
      margin: 100px auto 0;
      border-radius: 10px;
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.6);
      position: relative;
      z-index: 1;
      text-align: center;
    }

    .forgot-container h1 {
      margin-bottom: 30px;
      color: #FFD700;
      font-size: 28px;
    }

    .forgot-container p {
      margin-bottom: 25px;
      color: #ccc;
      line-height: 1.5;
    }

    form {
      margin: 0 2rem;
    }

    .input-group {
      padding: 1% 0;
      position: relative;
      margin-bottom: 1.5rem;
    }

    .input-group i {
      position: absolute;
      color: #888;
      left: 15px;
      top: 50%;
      transform: translateY(-50%);
      font-size: 18px;
    }

    .input-group input {
      color: #fff;
      width: 100%;
      background-color: transparent;
      border: none;
      border-bottom: 1px solid #555;
      padding: 12px 15px 12px 45px;
      font-size: 15px;
      transition: all 0.3s;
      height: 45px;
    }
    
    .input-group input:focus {
      background-color: transparent;
      outline: none;
      border-bottom: 2px solid #6c63ff;
      box-shadow: 0 2px 5px rgba(108, 99, 255, 0.2);
    }
    
    .input-group input::placeholder {
      color: transparent;
    }
    
    .input-group label {
      color: #999;
      position: absolute;
      left: 45px;
      top: 50%;
      transform: translateY(-50%);
      cursor: text;
      transition: 0.3s ease all;
      font-size: 15px;
      pointer-events: none;
    }
    
    .input-group input:focus ~ label,
    .input-group input:not(:placeholder-shown) ~ label {
      top: -20px;
      left: 0;
      color: #6c63ff;
      font-size: 12px;
    }

    .input-group input:focus {
      outline: none;
      border-color: #FFD700;
      background: rgba(255, 255, 255, 0.15);
    }

    .btn {
      width: 100%;
      padding: 12px;
      background: #FFD700;
      color: #1e1e1e;
      border: none;
      border-radius: 5px;
      font-size: 16px;
      font-weight: bold;
      cursor: pointer;
      margin-top: 10px;
      transition: all 0.3s;
    }

    .btn:hover {
      background: #5a52d4;
      transform: translateY(-2px);
    }

    .back-to-login {
      margin-top: 20px;
      text-align: center;
    }

    .back-to-login a {
      color: #FFD700;
      text-decoration: none;
      transition: color 0.3s;
    }

    .back-to-login a:hover {
      color: #FFA500;
      text-decoration: underline;
    }

    /* --- Top Navbar --- */
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

    /* --- Background Area --- */
    .background-area {
      position: fixed;
      top: 0; 
      left: 0;
      width: 100%;
      height: 100vh;
      z-index: 0;
      pointer-events: none;
      overflow: hidden;
      background: #121212;
    }
    
    body {
      position: relative;
      z-index: 1;
      min-height: 100vh;
      overflow-x: hidden;
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
  </style>
</head>
<body>
  <!-- Top navbar -->
  <div class="top-navbar">
    <div class="casino-name">🎰 LuckyTime</div>
    <div class="navbar-right">© LuckyTime 2025</div>
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

  <!-- Forgot Password Form -->
  <div class="forgot-container">
    <h1 class="form-title">Reset Password</h1>
    
    <?php if (!empty($error)): ?>
        <div class="error-message" style="color: #ff6b6b; margin-bottom: 20px; text-align: center; background: rgba(255, 0, 0, 0.1); padding: 10px; border-radius: 5px; border-left: 4px solid #ff6b6b;">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="success-message" style="color: #51cf66; margin-bottom: 20px; text-align: center; background: rgba(81, 207, 102, 0.1); padding: 10px; border-radius: 5px; border-left: 4px solid #51cf66;">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>
    
    <form method="post" action="process-reset.php">
      <div class="input-group">
        <i class="fas fa-envelope"></i>
        <input type="email" name="email" id="email" placeholder="Email" required />
        <label for="email">Email</label>
      </div>
      
      <div class="input-group">
        <i class="fas fa-lock"></i>
        <input type="password" name="new_password" id="new_password" placeholder="New Password" required />
        <label for="new_password">New Password</label>
      </div>
      
      <div class="input-group">
        <i class="fas fa-lock"></i>
        <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm New Password" required />
        <label for="confirm_password">Confirm Password</label>
      </div>
      
      <input type="submit" class="btn" value="Reset Password" name="resetPassword" />
    </form>
    
    <div class="links">
      <p>Remember your password?</p>
      <button onclick="window.location.href='index.php'">Back to Login</button>
    </div>
  </div>
</body>
</html>
