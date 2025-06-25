<?php
session_start();

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Redirect to login if not logged in
if (!isset($_SESSION['email'])) {
    header("Location: index.php");
    exit();
}

include("connect.php");

// Initialize user data
$user = null;
$balance = null;

if (isset($_SESSION['email'])) {
    $email = $_SESSION['email'];
    $result = mysqli_query($conn, "SELECT firstName, lastName, balance FROM users WHERE email='$email'");
    if ($row = mysqli_fetch_assoc($result)) {
        $user = $row['firstName'] . ' ' . $row['lastName'];
        $balance = $row['balance'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <title>LuckyTime | Welcome</title>
  <script>
    // Single point of truth for session check
    function checkSession() {
      // This would be better as an AJAX call to a session check endpoint
      // For now, we'll just check if we're on the homepage
      if (window.location.pathname.includes('homepage.php') && !document.cookie.includes('PHPSESSID')) {
        window.location.href = 'index.php';
        return false;
      }
      return true;
    }

    // Set up back button prevention
    (function() {
      // Only run this on the homepage
      if (!window.location.pathname.includes('homepage.php')) return;
      
      // Store the current URL
      const currentUrl = window.location.href;
      
      // Push a new state to prevent back button
      window.history.pushState(null, null, currentUrl);
      
      // Handle back/forward button
      window.onpopstate = function(event) {
        if (checkSession()) {
          // If session is still valid, keep them on the page
          window.history.pushState(null, null, currentUrl);
        }
      };
      
      // Check session periodically
      setInterval(checkSession, 1000);
    })();
  </script>
  <style>
    /* --- Reset & Body --- */
    body {
  margin: 0;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  background: #121212;
  color: #fff;
  overflow-x: hidden;
  position: relative;
  scroll-behavior: smooth;
  background-image: url('hero.webp'); 
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
  background-attachment: fixed;
}

    

    /* --- Background floating particles --- */
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

    /* --- Header --- */
    .header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 15px 35px;
      background-color: #1e1e1e;
      position: fixed;
      top: 0; width: 100%;
      box-shadow: 0 2px 10px rgba(0,0,0,0.5);
      z-index: 20;
    }

   .logo {
  font-weight: bold;
  font-size: 24px;
  color: #FFD700;
  cursor: pointer;
  animation: scalePulse 4s ease-in-out infinite;
  display: inline-block;
}

@keyframes scalePulse {
  0%, 100% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.05);
  }
}

.logo:hover {
  animation: shake 0.5s ease-in-out, colorFlash 0.5s ease-in-out;
  color: #fff;
}

@keyframes shake {
  0%, 100% { transform: translateX(0); }
  20%, 60% { transform: translateX(-5px); }
  40%, 80% { transform: translateX(5px); }
}

@keyframes colorFlash {
  0%, 100% { color: #FFD700; }
  50% { color: #fff; }
}



.balance-bubble {
  margin: 10px 16px;
  padding: 8px 14px;
  background-color: rgba(255, 215, 0, 0.3);
  color: #FFD700;
  font-weight: bold;
  font-size: 11px;
  border-radius: 50px;
  text-align: center;
  user-select: none;
  margin-top: 20px;
  /* Optional glowing animation */
  animation: pulseGlow 2s infinite;
  width: fit-content;
}

@keyframes pulseGlow {
  0%, 100% { box-shadow: 0 0 8px #FFD700; }
  50% { box-shadow: 0 0 20px #FFD700; }
}

    .amount-badge {
      background-color: rgba(255, 215, 0, 0.8);
      color: #121212;
      padding: 4px 12px;
      border-radius: 50px;
      font-weight: bold;
      margin-left: 8px;
      display: inline-block;
    }

    .nav-buttons {
      display: flex;
      gap: 25px;
      align-items: center;
      margin-right: 70px;
    }

    .nav-buttons a {
      color: #fff;
      text-decoration: none;
      font-weight: 500;
      position: relative;
    }

    .nav-buttons a:not(.logout)::after {
      content: '';
      position: absolute;
      width: 0%;
      height: 2px;
      bottom: -5px;
      left: 0;
      background-color: #FFD700;
      transition: 0.3s;
    }

    .nav-buttons a:not(.logout):hover::after {
      width: 100%;
    }

    .nav-buttons a:hover {
      color: gainsboro;
    }

    .logout {
      padding: 10px 20px;
      background-color: #ff4d4d;
      color: #fff;
      text-decoration: none;
      border-radius: 50px;
      font-weight: bold;
      transition: background-color 0.3s ease;
      
    }

    .logout:hover {
      background-color: #cc0000;
    }

    /* --- Hero Section --- */
    .hero {
  height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  z-index: 1;
  text-align: center;
  margin-left: 220px; /* to clear sidebar*/

}

    .hero::before {
      content: "";
      position: absolute;
      inset: 0;
      background: rgba(0, 0, 0, 0.7);
      z-index: 1;
    }

    .hero-content {
      position: relative;
      z-index: 2;
      color: #FFD700;
      max-width: 90%;
    }

    .hero-content h1 {
      font-size: 48px;
      margin-bottom: 20px;
    }

    .hero-content p {
      font-size: 22px;
      margin-bottom: 30px;
      color: #f0f0f0;
    }

    .hero-btn {
      padding: 12px 24px;
      background-color: #FFD700;
      color: #121212;
      font-weight: bold;
      text-decoration: none;
      border-radius: 8px;
      transition: background-color 0.3s ease;
      cursor: pointer;
      display: inline-block;
    }

    .hero-btn:hover {
      background-color: #e6c200;
    }

    /* --- Container for welcome and games --- */
    .container {
      position: relative;
      z-index: 5;
      background-color: rgb(11, 8, 8);
      padding:30px 70px;
      margin: 50px auto 40px;
      max-width: 600px;
      border-radius: 20px;
      box-shadow: 0 0 20px rgba(255, 215, 0, 0.2),
                  inset 0 0 10px rgba(255, 215, 0, 0.1);
      text-align: center;
        }

  .center-wrapper {
  display: flex;
  justify-content: center;
  align-items: center;
  height: 100vh;
  margin-left: 13%;
  position: relative; /* Add this line */
}
.center-wrapper::before {
  content: "";
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.7); /* Add this line */
  z-index: 1;
}



    h1 {
      font-size: 48px;
      color: #FFD700;
      margin-bottom: 10px;
    }

    h2 {
  font-size: 24px;
  color:rgb(21, 20, 11);
  text-shadow: 0 0 3px #FFD700, 0 0 6px #FFA500;
  animation: soft-glow 2s ease-in-out infinite alternate;
}

h4{
  font-size: 18px;
  margin-top: 10px;
  text-align: justify;
  color: white;
  font-weight: 200;

}
@keyframes soft-glow {
  from {
    text-shadow: 0 0 3px #FFD700, 0 0 6px #FFA500;
  }
  to {
    text-shadow: 0 0 6px #FFD700, 0 0 10px #FFA500;
  }
}


    p {
      text-align: center;
      font-size: 18px;
      margin-top: 10px;
    }

    .button-group {
      flex-wrap: wrap;
      justify-content: center;
      align-items: center;
      align-content: center;
      margin-top: 30px;
    }

    .btn {
      margin: 10px 10px;
      padding: 14px 25px;
      background-color: #FFD700;
      color: #121212;
      text-decoration: none;
      font-weight: bold;
      border-radius: 8px;
      transition: background-color 0.3s ease;
      min-width: 160px;
      display: inline-block;
    }

    .btn:hover {
      background-color: #e6c200;
    }

    /* --- Sidebar styles --- */
    .sidebar {
      position: fixed;
      top: 50px; /* height of top header */
      left: 0;
      width: 220px;
      height: calc(100vh - 40px);
      background-color: #1a1a1a;
      padding: 20px;
      box-sizing: border-box;
      overflow-y: auto;
      z-index: 15;
    }

    .sidebar a,
    .sidebar .dropbtn {
      display: block;
      padding: 10px 16px;
      margin: 4px 10px;
      border-radius: 6px;
      color: #ccc;
      text-align: left;
      background: none;
      border: none;
      font-weight: bold;
      text-decoration: none;
      font-size: 16px;
      cursor: pointer;
      transition: background-color 0.3s ease, color 0.3s ease;
      user-select: none;
    }

    .sidebar a:hover,
    .sidebar .dropbtn:hover {
      background-color: rgba(255, 215, 0, 0.15);
      color: #FFD700;
    }

    /* Collapsible styles */
    .collapsible-btn {
      display: block;
      padding: 10px 16px;
      margin: 4px 10px;
      border-radius: 6px;
      color: #ccc;
      background: none;
      border: none;
      font-weight: bold;
      font-size: 16px;
      text-align: left;
      cursor: pointer;
      user-select: none;
      transition: background-color 0.3s ease, color 0.3s ease;
      position: relative;
    }

    .collapsible-btn::after {
      content: "▾";
      position: absolute;
      left: 70px;
      font-weight: bold;
      color: #FFD700;
      transition: transform 0.3s ease;
    }

    .collapsible-btn.active::after {
      transform: rotate(-180deg);
    }

    .collapsible-content {
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.3s ease-out;
      padding-left: 25px;
      margin-bottom: 10px;
      display: flex;
      flex-direction: column;
    }

    .collapsible-content a {
      color: #bbb;
      font-weight: bold;
      font-size: 14px;
      margin: 4px 10px;
      padding: 8px 12px;
      border-radius: 6px;
      text-decoration: none;
    }

    .collapsible-content a:hover {
      background-color: rgba(255, 215, 0, 0.15);
      color: #FFD700;
    }
    html {
    scroll-behavior: smooth;
}
#welcome {
  scroll-margin-top: 100px; 

}




#hero {
  scroll-margin-top: 100px;
}

/* Modal overlay */
.modal {
  display: none; /* Hidden by default */
  position: fixed;
  z-index: 50; /* On top of other elements */
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  overflow: auto; /* Enable scroll if needed */
  background-color: rgba(0, 0, 0, 0.7); /* Black with opacity */
  backdrop-filter: blur(4px);
}

/* Modal content box */
.modal-content {
  background-color: #1a1a1a;
  margin: 10% auto;
  padding: 20px 30px;
  border-radius: 12px;
  box-shadow: 0 0 25px rgba(255, 215, 0, 0.5);
  color: #FFD700;
  max-width: 500px;
  position: relative;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  line-height: 1.5;
}

/* Close button */
.close-btn {
  position: absolute;
  top: 12px;
  right: 20px;
  color:rgb(244, 75, 75);
  font-size: 28px;
  font-weight: bold;
  cursor: pointer;
  user-select: none;
  transition: color 0.3s ease;
}

.close-btn:hover {
  color: red;
}

.game-container {
  display: grid;
  grid-template-columns: repeat(3, 1fr); /* 3 columns per row */
  gap: 20px;
  max-width: 800px;
  margin: 0 auto;
  padding: 20px;
}
.game-item:nth-child(4),
.game-item:nth-child(5) {
  grid-column: span 1;
}
.game-buttons {
  display: flex;
  justify-content: center;  /* center the items */
  gap: 20px;                /* space between buttons */
  flex-wrap: nowrap;
  padding-top: 20px;
}

.game-item {
  flex: 0 0 calc(20% - 10px); /* each item takes 20% width minus gap */
  max-width: 120px;            /* keep the max width */
  box-sizing: border-box;
}

.btn-square {
  position: relative;
  display: block;
  width: 120px;
  height: 140px;
  border-radius: 10px;
  overflow: hidden;
  text-decoration: none;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
  transition: transform 0.2s ease;
}

.btn-square img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.btn-square:hover {
  transform: scale(1.05);
}

.game-label {
  position: absolute;
  bottom: 0;
  left: 0;
  width: 100%;
  background: rgba(0, 0, 0, 0.6);
  color: #fff;
  font-size: 14px;
  text-align: center;
  padding: 6px 0;
  opacity: 0;
  transition: opacity 0.3s ease;
}

.btn-square:hover .game-label {
  opacity: 1;
}


  </style>
</head>
<body>

  <!-- About Us Popup Modal -->
<div id="aboutModal" class="modal">
  <div class="modal-content">
    <span class="close-btn">&times;</span>
    <h2>About LuckyTime Online Casino</h2>
  <h4> At LuckyTime, we are dedicated to offering a reliable and enjoyable online casino experience focused on classic and popular games like poker, blackjack, and roulette. We emphasize fairness, security, and responsible gaming to ensure that every player feels safe and confident while enjoying our platform. Our team works hard to provide excellent customer service and regularly updates the site to enhance your experience. Whether you’re a seasoned player or just starting out, LuckyTime is committed to delivering fun and excitement in a trustworthy environment.</h4>
  </div>
</div>

<!-- Terms & Conditions Modal -->
<div id="termsModal" class="modal">
  <div class="modal-content">
    <span class="close-btn terms-close">&times;</span>
    <h2>Terms & Conditions</h2>
    <h4>By using LuckyTime, you agree to follow these Terms and Conditions. You must be 18 or older to play, and you’re responsible for keeping your account secure. Please use the site fairly and avoid any cheating or abuse.</h4>
    <h4>LuckyTime games are based on chance, and winnings are not guaranteed. We may suspend or close accounts that break the rules. We are not responsible for any losses from playing on our platform.</h4>
    <h4>We may update these Terms from time to time. Using LuckyTime after updates means you accept the changes. If you have questions, contact our support team.</h4>
  </div>
</div>



  <!-- Sidebar Navigation -->
  <div class="sidebar">
  <?php if ($user): ?>
    <div class="balance-bubble" title="Your current balance" style="margin-bottom: 20px; margin-left: 0;">
      Balance: <span class="amount-badge">₱<?= number_format($balance, 2) ?></span>
    </div>

    
  <?php endif; ?>

  <a href="#hero" class="hero-section">Home</a>
  <a href="profile.php">Profile</a>
  
  <div class="dropdown">
     <button class="collapsible-btn">Games</button>
    <div class="collapsible-content">
      <a href=#welcome class=" ">Color Game</a>
      <a href=#welcome class=" ">Hi-Lo</a>
      <a href=#welcome class=" ">Roulette</a>
      <a href=#welcome class=" ">Baccarat</a>
      <a href=#welcome class=" ">Mines</a>
    </div>
  </div>

  <a href="about.php">About Us</a>
  <a href="#" id="termsLink">Terms & Conditions</a>

</div>





  <!-- Background floating particles -->
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

  <!-- Top Header -->
  <header class="header">
    <div class="logo">🎰 LuckyTime</div>


    <nav class="nav-buttons">
      <?php if ($user): ?>
        <a href="logout.php" class="logout">Logout</a>
      <?php else: ?>
        <a href="login.php">Login</a>
        <a href="register.php">Register</a>
      <?php endif; ?>
    </nav>
  </header>

  <!-- Hero Section -->
  <section id="hero" class="hero">
    <div class="hero-content">
      <h1>Welcome to LuckyTime</h1>
      <p>Play exciting games and win big prizes!</p>
      <a href="#welcome" class="hero-btn">Play Now</a>


    </div>
  </section>

  <!-- Welcome Container -->
<div class="center-wrapper">
  <div class="container" id="welcome">
    <?php if ($user): ?>
      <h1>Welcome, <?= htmlspecialchars($user) ?>!</h1>
      <h3>Ready to try your luck today?</h3>
      <div class="game-buttons">
<div class="game-buttons">
  <div class="game-item">
    <a href="color-game.php" class="btn-square">
      <img src="cg.png" alt="Color Game">
      <span class="game-label">Color Game</span>
    </a>
  </div>

  <div class="game-item">
    <a href="hi-lo.php" class="btn-square">
      <img src="hilo.png" alt="Hi-Lo">
      <span class="game-label">Hi-Lo</span>
    </a>
  </div>

  <div class="game-item">
    <a href="roulette.php" class="btn-square">
      <img src="roulette.png" alt="Roulette">
      <span class="game-label">Roulette</span>
    </a>
  </div>

  <div class="game-item">
    <a href="baccarat.php" class="btn-square">
      <img src="bcrt.png" alt="Baccarat">
      <span class="game-label">Baccarat</span>
    </a>
  </div>

  <div class="game-item">
    <a href="mines.php" class="btn-square">
      <img src="mines.webp" alt="Mines">
      <span class="game-label">Mines</span>
    </a>
  </div>
</div>

</div>

    <?php endif; ?>
  </div>
</div>


  <script>
    // Collapsible Sidebar Games menu
    const coll = document.getElementsByClassName("collapsible-btn");
    for (let i = 0; i < coll.length; i++) {
      coll[i].addEventListener("click", function () {
        this.classList.toggle("active");
        const content = this.nextElementSibling;
        if (content.style.maxHeight) {
          content.style.maxHeight = null;
        } else {
          content.style.maxHeight = content.scrollHeight + "px";
        }
      });
    }
  </script>

  


<script>
  // About Us Modal
  const aboutModal = document.getElementById("aboutModal");
  const aboutBtn = document.querySelector('a[href="about.php"]');
  const aboutClose = document.querySelector("#aboutModal .close-btn");

  if (aboutBtn) {
    aboutBtn.addEventListener("click", function (e) {
      e.preventDefault();
      aboutModal.style.display = "block";
    });
  }

  if (aboutClose) {
    aboutClose.addEventListener("click", function () {
      aboutModal.style.display = "none";
    });
  }

  // Terms Modal
  const termsModal = document.getElementById("termsModal");
  const termsLink = document.getElementById("termsLink");
  const termsClose = document.querySelector(".terms-close");

  if (termsLink) {
    termsLink.addEventListener("click", function (e) {
      e.preventDefault();
      termsModal.style.display = "block";
    });
  }

  if (termsClose) {
    termsClose.addEventListener("click", function () {
      termsModal.style.display = "none";
    });
  }

  // Close any modal when clicking outside
  window.addEventListener("click", function (e) {
    if (e.target === aboutModal) aboutModal.style.display = "none";
    if (e.target === termsModal) termsModal.style.display = "none";
  });
</script>

</body>
</html>
