<!-- final highlow game -->
<?php
session_start();
$conn = new mysqli("localhost", "root", "", "ecasinosite");

if (!isset($_SESSION['user_id'])) {
  header("Location: index.php");
  exit();
}

$userId = $_SESSION['user_id'];

// Function to generate a card with value, name, and suit
function createCard()
{
  $names = ['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'];
  $values = range(2, 14);
  $suits = ['♥', '♦', '♣', '♠'];

  $index = array_rand($names);
  $suit = $suits[array_rand($suits)];

  return [
    'name' => $names[$index],
    'value' => $values[$index],
    'suit' => $suit
  ];
}

// Function to render a card as HTML
function renderCard($card, $isHidden = false)
{
  if ($isHidden) {
    return '<div class="card hidden"></div>';
  }
  $colorClass = in_array($card['suit'], ['♥', '♦']) ? 'red-card' : 'black-card';
  $rank = $card['name'];
  $suit = $card['suit'];
  return '<div class="card ' . $colorClass . '">' .
    '<span>' . $rank . $suit . '</span>' .
    '</div>';
}

// Reset streak if needed
if (!isset($_SESSION['highlow_streak'])) {
  $_SESSION['highlow_streak'] = 0;
}

$wallet = $conn->query("SELECT token_balance FROM wallets WHERE user_id = $userId")->fetch_assoc();
$balance = $wallet['token_balance'];

// Game logic trigger
$result = "";
$firstCard = null;
$secondCard = null;
$newBalance = $balance;
$payout = 0;
$streak = $_SESSION['highlow_streak'];
$showResult = false;
$result_db = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guess'])) {
  $betAmount = floatval($_POST['bet_amount']);
  $guess = $_POST['guess'];

  if ($betAmount > $balance) {
    $result = "<i class='fas fa-times-circle'></i> Insufficient balance.";
  } else {
    $firstCard = createCard();
    do {
      $secondCard = createCard();
    } while ($secondCard['value'] === $firstCard['value']);

    $showResult = true;

    if (($guess === "Higher" && $secondCard['value'] > $firstCard['value']) || ($guess === "Lower" && $secondCard['value'] < $firstCard['value'])) {
      // Win
      $_SESSION['highlow_streak']++;
      $streak = $_SESSION['highlow_streak'];
      $multiplier = 1.9; // Base multiplier for a correct guess
      $payout = round($betAmount * $multiplier, 0);
      $newBalance = $balance + $payout;

      $conn->query("UPDATE wallets SET token_balance = token_balance + $payout WHERE user_id = $userId");
      $conn->query("UPDATE casino_status SET token_balance = token_balance - $payout WHERE id = 1");
      $conn->query("INSERT INTO transactions (user_id, type, amount) VALUES ($userId, 'win', $payout)");

      $result_db = "Win";
      $result = "<i class='fas fa-trophy'></i> Correct! You won " . number_format($payout, 0) . " (Streak: $streak)";
    } else if ($secondCard['value'] === $firstCard['value']) {
      // Push (tie)
      $newBalance = $balance; // Balance remains unchanged
      $result_db = "Push";
      $result = "<i class='fas fa-info-circle'></i> Push! The cards were the same. Your bet is returned.";
    } else {
      // Lose
      $_SESSION['highlow_streak'] = 0;
      $streak = 0;
      $newBalance = $balance - $betAmount;

      $conn->query("UPDATE wallets SET token_balance = token_balance - $betAmount WHERE user_id = $userId");
      $conn->query("UPDATE casino_status SET token_balance = token_balance + $betAmount WHERE id = 1");
      $conn->query("INSERT INTO transactions (user_id, type, amount) VALUES ($userId, 'bet', $betAmount)");

      $result_db = "Lose";
      $result = "<i class='fas fa-times-circle'></i> Wrong guess. You lost " . number_format($betAmount, 0);
    }

    if (!empty($result_db)) {
      $conn->query("INSERT INTO games (user_id, game_type, result, bet_amount) VALUES ($userId, 'High-Low', '$result_db', $betAmount)");
    }
    $balance = $newBalance;
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <title>High-Low | BitLuck</title>
  <link rel="icon" href="../images/HIGH-LOW-LOGO.png" type="image/png">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap');

    :root {
      --gold: #FFD700;
      --red: #D10000;
      --red-glow: rgba(209, 0, 0, 0.6);
      --black: #0A0A0A;
      --black-light: #121212;
      --white: #FFFFFF;
      --gray: #333;
      --green: #28a745;
      --blue: #007BFF;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Montserrat', sans-serif;
      background-color: var(--black);
      color: var(--white);
      min-height: 100vh;
      padding: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .game-container {
      width: 100%;
      max-width: 900px;
      background-color: var(--black-light);
      border-radius: 16px;
      padding: 40px;
      border: 2px solid var(--gold);
      position: relative;
      padding-bottom: 100px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    }

    h2 {
      font-weight: 700;
      color: var(--gold);
      font-size: 2rem;
      text-align: center;
      margin-bottom: 1rem;
      text-transform: uppercase;
    }

    h2::after {
      content: '';
      display: block;
      width: 80px;
      height: 2px;
      background: var(--gold);
      margin: 10px auto 2rem;
    }

    .balance {
      font-size: 1.1rem;
      text-align: center;
      margin-bottom: 2rem;
      color: var(--white);
      font-weight: 600;
      background: var(--gray);
      padding: 12px 20px;
      border-radius: 50px;
      border: 1px solid var(--gold);
    }

    .balance i {
      color: var(--gold);
      margin-right: 8px;
    }

    .message-area {
      min-height: 60px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 2rem;
    }

    .result-box {
      padding: 15px 25px;
      border-left: 4px solid;
      width: 100%;
      max-width: 550px;
      background-color: var(--gray);
      border-radius: 8px;
    }

    .result-box.win {
      border-color: var(--gold);
    }

    .result-box.lose {
      border-color: var(--red);
    }

    .result-box.info {
      border-color: var(--blue);
    }

    .result-box h3 {
      font-size: 1.1rem;
      font-weight: 700;
      text-transform: uppercase;
      text-align: center;
    }

    .result-box.win h3 {
      color: var(--gold);
    }

    .result-box.lose h3 {
      color: var(--red);
    }

    .result-box.info h3 {
      color: var(--blue);
    }

    .result-box h3 i {
      font-size: 1.3rem;
      margin-right: 8px;
    }

    .game-area {
      text-align: center;
    }

    .cards-display {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 2rem;
      margin-bottom: 2rem;
      min-height: 180px;
    }

    .card {
      width: 120px;
      height: 170px;
      border-radius: 12px;
      display: flex;
      justify-content: center;
      /* Center content horizontally */
      align-items: center;
      /* Center content vertically */
      padding: 10px;
      font-size: 2.8rem;
      /* Main font size for rank+suit */
      font-weight: bold;
      position: relative;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
      transition: all 0.3s ease;
      background: linear-gradient(135deg, #fff, #f5f5f5);
    }

    .card.red-card {
      color: var(--red);
    }

    .card.black-card {
      color: var(--black);
    }

    .card:hover {
      transform: translateY(-10px) rotate(2deg);
      box-shadow: 0 15px 25px rgba(0, 0, 0, 0.4);
    }

    .card.hidden {
      background: linear-gradient(135deg, var(--black-light), var(--black));
      color: transparent;
      border: 2px solid var(--red);
    }

    .card.hidden::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background:
        repeating-linear-gradient(45deg,
          rgba(255, 82, 82, 0.1),
          rgba(255, 82, 82, 0.1) 2px,
          transparent 2px,
          transparent 4px);
      border-radius: 8px;
    }

    .card.hidden::after {
      content: '?';
      color: var(--red);
      position: absolute;
      transform: none;
      font-size: 2.5rem;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
    }

    .vs {
      font-size: 2rem;
      color: var(--gold);
      font-weight: bold;
    }

    .bet-form {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 1.5rem;
    }

    .bet-input-group label {
      display: block;
      font-size: 1rem;
      color: var(--white);
      font-weight: 600;
      margin-bottom: 1rem;
      text-transform: uppercase;
    }

    .bet-input-group label i {
      color: var(--gold);
      margin-right: 8px;
    }

    .bet-input-container {
      position: relative;
    }

    .bet-input-container i {
      position: absolute;
      left: 15px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--gold);
    }

    .bet-input-group input {
      background: var(--gray);
      border: 2px solid var(--gold);
      color: var(--white);
      padding: 12px 18px 12px 40px;
      font-size: 1.1rem;
      border-radius: 8px;
      width: 220px;
      text-align: center;
    }

    .bet-input-group input:focus {
      outline: none;
      box-shadow: 0 0 10px var(--gold);
    }

    .btn-group {
      display: flex;
      gap: 1rem;
      justify-content: center;
    }

    .btn {
      padding: 12px 28px;
      font-size: 1rem;
      font-weight: 700;
      border-radius: 50px;
      cursor: pointer;
      text-transform: uppercase;
      border: 2px solid transparent;
      transition: all 0.3s ease;
      text-decoration: none;
      color: var(--white);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .btn:hover {
      transform: scale(1.05);
    }

    .btn-higher {
      background: var(--green);
      box-shadow: 0 0 15px rgba(40, 167, 69, 0.6);
    }

    .btn-lower {
      background: var(--red);
      box-shadow: 0 0 15px var(--red-glow);
    }

    .shine-effect {
      position: relative;
      overflow: hidden;
    }

    .shine-effect::before {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: linear-gradient(to bottom right,
          rgba(255, 255, 255, 0) 0%,
          rgba(255, 255, 255, 0) 45%,
          rgba(255, 255, 255, 0.3) 48%,
          rgba(255, 255, 255, 0.3) 52%,
          rgba(255, 255, 255, 0) 55%,
          rgba(255, 255, 255, 0) 100%);
      transform: rotate(30deg);
      animation: shine 5s infinite;
    }

    @keyframes shine {
      0% {
        transform: rotate(30deg) translate(-30%, -30%);
      }

      100% {
        transform: rotate(30deg) translate(30%, 30%);
      }
    }

    .play-again-link {
      margin-top: 2rem;
      display: inline-block;
    }

    .back-link {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      color: var(--gold);
      text-decoration: none;
      font-size: 1.1rem;
      transition: all 0.3s ease;
      padding: 12px 25px;
      border-radius: 50px;
      background: rgba(209, 0, 0, 0.3);
      border: 1px solid var(--gold);
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 1px;
      width: 260px;
      max-width: 100%;
      margin: 20px auto 0 auto;
      white-space: nowrap;
      overflow: hidden;
      margin-top: 50px;
      margin-bottom: -50px;
    }

    .back-link:hover {
      background: rgba(209, 0, 0, 0.5);
      color: #FFECB3;
      transform: translateX(-5px);
      box-shadow: 0 0 15px rgba(255, 215, 0, 0.5);
    }

    .back-link::before {
      content: '\f104';
      font-family: 'Font Awesome 6 Free';
      font-weight: 900;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
      .game-container {
        padding: 30px 20px;
        padding-bottom: 80px;
      }

      .cards-display {
        gap: 1rem;
      }

      .card {
        width: 90px;
        height: 130px;
        font-size: 2.2rem;
      }

      .btn-group {
        flex-direction: column;
        gap: 0.8rem;
      }

      .btn {
        width: 200px;
      }
    }

    @media (max-width: 480px) {
      h2 {
        font-size: 1.8rem;
      }

      .card {
        width: 70px;
        height: 100px;
        font-size: 1.8rem;
        border-width: 3px;
      }

      .bet-input-group input {
        width: 180px;
      }

      .btn {
        width: 180px;
        font-size: 0.9rem;
      }

      .back-link {
        bottom: 15px;
        left: 15px;
        font-size: 1rem;
        padding: 10px 20px;
      }
    }
  </style>
</head>

<body>
  <div class="game-container">
    <h2>High-Low</h2>
    <div class="balance">
      <i class=""></i>🪙 Your Balance: <?= number_format($balance, 0) ?> BTL
      <span style="margin-left: 20px; font-weight: 700;">Streak: <i class="fas fa-fire"></i> <?= $streak ?></span>
    </div>

    <div class="message-area">
      <?php if ($result): ?>
        <div class="result-box <?= (strpos($result, 'won') !== false || strpos($result, 'Correct') !== false) ? 'win' : ((strpos($result, 'lost') !== false) ? 'lose' : 'info') ?>">
          <h3>
            <?= $result ?>
          </h3>
        </div>
      <?php endif; ?>
    </div>

    <div class="game-area">
      <div class="cards-display">
        <?= renderCard($firstCard, !$firstCard) ?>
        <div class="vs">VS</div>
        <?= renderCard($secondCard, !$showResult) ?>
      </div>

      <?php if ($showResult): ?>
        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-higher play-again-link">Play Again</a>
      <?php else: ?>
        <form method="POST" class="bet-form">
          <div class="bet-input-group">
            <label for="bet_amount"><i class=""></i>🎲 Enter Bet Amount:</label>
            <div class="bet-input-container">
              <span style="
                            position: absolute;
                            left: 15px;
                            top: 48%;
                            transform: translateY(-50%);
                            font-size: 1.2rem;">🪙</span>
              <input type="number" id="bet_amount" name="bet_amount" min="10" max="10000" required>
            </div>
          </div>
          <div class="btn-group">
            <button type="submit" name="guess" value="Higher" class="btn btn-higher shine-effect"><i class="fas fa-arrow-up"></i> Higher</button>
            <button type="submit" name="guess" value="Lower" class="btn btn-lower shine-effect"><i class="fas fa-arrow-down"></i> Lower</button>
          </div>
        </form>
      <?php endif; ?>
    </div>
    <div style="text-align: center;">
      <a href="../main.php" class="back-link">Back to Dashboard</a>
    </div>
  </div>
</body>

</html>