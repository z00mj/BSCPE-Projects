<?php
require_once __DIR__ . '/../../backend/inc/init.php';
userOnly();
header('Content-Type: application/json');

$db = Database::getInstance();
$user_id = $_SESSION['user_id'];

$data = json_decode(file_get_contents('php://input'), true);
$bet = isset($data['bet']) ? (int)$data['bet'] : 0;
$win = isset($data['win']) ? (int)$data['win'] : 0;
$isWin = isset($data['isWin']) ? (int)$data['isWin'] : 0;

if ($bet < 1 || $bet > 1000) {
    echo json_encode(['success' => false, 'message' => 'Invalid bet.']);
    exit();
}

// Fetch current balances
$user = $db->fetchOne('SELECT ticket_balance, rawr_balance FROM users WHERE id = ?', [$user_id]);
if (!$user || $user['ticket_balance'] < $bet) {
    echo json_encode(['success' => false, 'message' => 'Insufficient tickets.']);
    exit();
}

$newTicketBalance = $user['ticket_balance'] - $bet + $win;
$db->executeQuery('UPDATE users SET ticket_balance = ? WHERE id = ?', [$newTicketBalance, $user_id]);

// Optionally: Insert game result into a game_results table here

// Return updated balances
echo json_encode([
    'success' => true,
    'ticket_balance' => $newTicketBalance,
    'rawr_balance' => $user['rawr_balance']
]);
