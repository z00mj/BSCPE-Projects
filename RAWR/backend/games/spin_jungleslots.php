<?php
require_once __DIR__ . '/../inc/init.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

if (!$user) {
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

$ticketBalance = (int)$user['ticket_balance'];
$rawrBalance = (float)$user['rawr_balance'];

$betAmount = isset($_POST['bet']) ? (int)$_POST['bet'] : 0;
if ($betAmount <= 0 || $betAmount > $ticketBalance) {
    echo json_encode(['success' => false, 'error' => 'Invalid bet amount or insufficient tickets']);
    exit;
}

// Slot symbols and logic
$symbols = ['🦁', '💎', '👑', '🐵', '🐗', '🐯'];
$resultIndexes = [
    rand(0, count($symbols)-1),
    rand(0, count($symbols)-1),
    rand(0, count($symbols)-1)
];
$payout = 0;
$outcome = 'loss';

if ($resultIndexes[0] === 0 && $resultIndexes[1] === 0 && $resultIndexes[2] === 0) { // JACKPOT
    $payout = 50000;
    $outcome = 'win';
} elseif ($resultIndexes[0] === $resultIndexes[1] && $resultIndexes[1] === $resultIndexes[2]) {
    $payout = $betAmount * [1000, 500, 300, 200, 150][$resultIndexes[0]];
    $outcome = 'win';
} elseif ($resultIndexes[0] === 0 && $resultIndexes[1] === 0) {
    $payout = $betAmount * 50;
    $outcome = 'win';
} elseif ($resultIndexes[0] === 0 || $resultIndexes[1] === 0 || $resultIndexes[2] === 0) {
    $payout = $betAmount * 5;
    $outcome = 'win';
}

$newTicketBalance = $ticketBalance - $betAmount + $payout;

// Update user balance in database
$db->executeQuery(
    "UPDATE users SET ticket_balance = ? WHERE id = ?",
    [$newTicketBalance, $userId]
);

// Record game result
$db->executeQuery(
    "INSERT INTO game_results (user_id, game_type_id, bet_amount, payout, outcome, game_details) 
    VALUES (?, ?, ?, ?, ?, ?)",
    [
        $userId,
        3, // Slot Machine ID
        $betAmount,
        $payout,
        $outcome,
        json_encode([
            'symbols' => [
                $symbols[$resultIndexes[0]],
                $symbols[$resultIndexes[1]],
                $symbols[$resultIndexes[2]]
            ],
            'bet' => $betAmount
        ])
    ]
);

// Record casino spending
$db->executeQuery(
    "INSERT INTO casino_spending (user_id, game_type_id, tickets_spent)
    VALUES (?, ?, ?)",
    [$userId, 3, $betAmount]
);

// Respond with result
$response = [
    'success' => true,
    'symbols' => [
        $symbols[$resultIndexes[0]],
        $symbols[$resultIndexes[1]],
        $symbols[$resultIndexes[2]]
    ],
    'payout' => $payout,
    'bet' => $betAmount,
    'new_ticket_balance' => $newTicketBalance
];
echo json_encode($response);
