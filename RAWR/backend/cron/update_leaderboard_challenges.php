<?php
<?php
require_once __DIR__ . '/../inc/init.php';

$db = Database::getInstance();

// Get Jungle King challenge ID
$jungleKing = $db->fetchOne("SELECT id FROM challenge_types WHERE name = 'Jungle King'");
if (!$jungleKing) die("Jungle King challenge not found");

// Get current leaderboard top player
$topPlayer = $db->fetchOne(
    "SELECT id FROM users 
     WHERE is_banned = 0 
     ORDER BY rawr_balance DESC, created_at ASC 
     LIMIT 1"
);

if ($topPlayer) {
    // Update progress for top player
    $db->callProcedure('UpdateChallengeProgress', [
        $topPlayer['id'],
        $jungleKing['id'],
        1, // Set progress to 1 (achieved)
        1  // Set progress directly
    ]);
}

// Log execution
file_put_contents(__DIR__ . '/../logs/leaderboard_update.log', 
    "[" . date('Y-m-d H:i:s') . "] Leaderboard challenges updated\n", 
    FILE_APPEND
);