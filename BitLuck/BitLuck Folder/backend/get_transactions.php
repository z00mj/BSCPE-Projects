<?php
session_start();
$conn = new mysqli("localhost", "root", "", "ecasinosite");
$userId = $_SESSION['user_id'];

$tab = $_GET['tab'] ?? 'betting';
$gameFilter = $_GET['gameFilter'] ?? 'all';
$startDate = $_GET['startDate'] ?? '';
$endDate = $_GET['endDate'] ?? '';

$response = [];

if ($tab === 'betting') {
    $sql = "SELECT g.timestamp, g.game_type, g.bet_amount, t.type, t.amount 
            FROM games g
            JOIN transactions t ON g.user_id = t.user_id AND g.timestamp = t.timestamp
            WHERE g.user_id = ?";

    $params = [$userId];
    $types = "i";

    if ($gameFilter !== 'all') {
        $sql .= " AND g.game_type = ?";
        $params[] = $gameFilter;
        $types .= "s";
    }
    if (!empty($startDate)) {
        $sql .= " AND g.timestamp >= ?";
        $params[] = $startDate;
        $types .= "s";
    }
    if (!empty($endDate)) {
        $sql .= " AND g.timestamp <= ?";
        $params[] = $endDate;
        $types .= "s";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $status = $row['type'] === 'win' ? 'win' : 'loss';
        $profit = $status === 'win'
            ? "+" . number_format($row['amount'], 2) . " BTL"
            : "-" . number_format($row['bet_amount'], 2) . " BTL";


        $response[] = [
            'datetime' => $row['timestamp'],
            'gameName' => $row['game_type'],
            'amount' => number_format($row['amount'], 2) . ' BTL',
            'profit' => $profit,
            'status' => $status
        ];
    }
} elseif ($tab === 'deposit' || $tab === 'withdrawal') {
    $action = $tab === 'deposit' ? 'deposit' : 'withdraw';

    $sql = "SELECT * FROM wallet_activity WHERE user_id = ? AND action = ?";
    $params = [$userId, $action];
    $types = "is";

    if (!empty($startDate)) {
        $sql .= " AND created_at >= ?";
        $params[] = $startDate;
        $types .= "s";
    }
    if (!empty($endDate)) {
        $sql .= " AND created_at <= ?";
        $params[] = $endDate;
        $types .= "s";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $response[] = [
            'datetime' => $row['created_at'],
            'method' => 'Wallet', // You can update this if you track method
            'amount' => number_format($row['amount'], 2) . ' BTL',
            'status' => 'Completed',
            'transactionId' => strtoupper($action) . '-' . $row['id']
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($response);
