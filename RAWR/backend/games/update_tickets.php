<?php
require_once __DIR__ . '/../inc/init.php';
userOnly();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

$db = Database::getInstance();
$user_id = $_SESSION['user_id'] ?? null;
$new_balance = isset($_POST['ticket_balance']) ? (int)$_POST['ticket_balance'] : null;

if (!$user_id || $new_balance === null) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters.']);
    exit;
}

$updated = $db->query('UPDATE users SET ticket_balance = ? WHERE id = ?', [$new_balance, $user_id]);

if ($updated) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database update failed.']);
}
