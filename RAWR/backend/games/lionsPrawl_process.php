<?php
require_once __DIR__ . '/../inc/init.php';
userOnly();
header('Content-Type: application/json');

$db = Database::getInstance();
$user_id = $_SESSION['user_id'];

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
$amount = isset($data['amount']) ? (int)$data['amount'] : 0;

$user = $db->fetchOne('SELECT ticket_balance FROM users WHERE id = ?', [$user_id]);
if (!$user) {
    echo json_encode(['success' => false, 'error' => 'User not found.']);
    exit;
}
$ticket_balance = (int)$user['ticket_balance'];

switch ($action) {
    case 'bet':
        if ($amount < 1 || $amount > $ticket_balance) {
            echo json_encode(['success' => false, 'error' => 'Invalid bet amount.']);
            exit;
        }
        $ticket_balance -= $amount;
        break;
    case 'collect':
        if ($amount < 0) $amount = 0;
        $ticket_balance += $amount;
        break;
    case 'lose':
        // No-op, as bet is already deducted on 'bet'
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action.']);
        exit;
}

$db->executeQuery('UPDATE users SET ticket_balance = ? WHERE id = ?', [$ticket_balance, $user_id]);
echo json_encode(['success' => true, 'ticket_balance' => $ticket_balance]);
