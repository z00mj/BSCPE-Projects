<?php
$conn = new mysqli("localhost", "root", "", "ecasinosite");

$sql = "
SELECT u.username, u.profile_pic, g.game_type,
       COUNT(*) as total_games,
       SUM(CASE WHEN t.type = 'win' THEN t.amount ELSE 0 END) as total_winnings,
       ROUND(SUM(CASE WHEN t.type = 'win' THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) as win_rate
FROM games g
JOIN users u ON g.user_id = u.id
JOIN transactions t ON t.user_id = u.id
GROUP BY u.id, g.game_type
ORDER BY total_winnings DESC
LIMIT 5
";


$result = $conn->query($sql);
$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
?>
