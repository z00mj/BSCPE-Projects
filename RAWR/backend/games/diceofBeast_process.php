<?php
require_once __DIR__ . '/../inc/init.php';
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit();
}
$db = Database::getInstance();
$user_id = $_SESSION['user_id'];

$data = json_decode(file_get_contents('php://input'), true);
$bet = isset($data['bet']) ? (int)$data['bet'] : 0;
$animal = isset($data['animal']) ? $data['animal'] : '';

if ($bet < 1 || $bet > 1000 || !in_array($animal, ['lion', 'tiger', 'wolf'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid bet or animal.']);
    exit();
}

// Fetch current ticket balance
$user = $db->fetchOne('SELECT ticket_balance FROM users WHERE id = ?', [$user_id]);
if (!$user || $user['ticket_balance'] < $bet) {
    echo json_encode(['success' => false, 'message' => 'Insufficient tickets.']);
    exit();
}

// Roll the dice (0-5)
$diceFaces = ['lion', 'tiger', 'wolf', 'wolf', 'tiger', 'wolf'];
$resultFace = random_int(0, 5);
$resultAnimal = $diceFaces[$resultFace];

// Calculate win
$winAmount = 0;
if ($resultAnimal === $animal) {
    $multiplier = $animal === 'lion' ? 5 : ($animal === 'tiger' ? 3 : 2);
    $winAmount = $bet * $multiplier;
}

// Update ticket balance
$newBalance = $user['ticket_balance'] - $bet + $winAmount;
$db->executeQuery('UPDATE users SET ticket_balance = ? WHERE id = ?', [$newBalance, $user_id]);

// Optionally: Insert game result into a game_results table here

// Return result
echo json_encode([
    'success' => true,
    'ticket_balance' => $newBalance,
    'result_animal' => $resultAnimal,
    'win_amount' => $winAmount
]);
