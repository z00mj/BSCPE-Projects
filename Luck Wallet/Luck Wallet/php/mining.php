<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include database connection and session handler
require_once __DIR__ . '/session_handler.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

// Get current user ID
$userId = $_SESSION['user_id'];

// Initialize database connection
try {
    require_once __DIR__ . '/../db_connect.php';
    
    // Ensure mining_sessions table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS luck_wallet_mining_sessions (
        session_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        start_time DATETIME NOT NULL,
        end_time DATETIME NOT NULL,
        is_completed TINYINT(1) NOT NULL DEFAULT 0,
        mined_amount DECIMAL(24, 8) NOT NULL DEFAULT 0,
        last_update DATETIME NOT NULL,
        INDEX idx_user (user_id),
        INDEX idx_end_time (end_time)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit();
}

// Handle different actions
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'start':
        startMining($pdo, $userId);
        break;
        
    case 'status':
        getMiningStatus($pdo, $userId);
        break;
        
    case 'claim':
        claimRewards($pdo, $userId);
        break;
        
    default:
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

/**
 * Start a new mining session
 */
function startMining($pdo, $userId) {
    header('Content-Type: application/json');
    
    try {
        // Check if user already has an active mining session
        $stmt = $pdo->prepare("
            SELECT * FROM luck_wallet_mining_sessions 
            WHERE user_id = ? AND is_completed = 0
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'error' => 'Mining already in progress']);
            return;
        }
        
        // Start new mining session (24 hours from now)
        $startTime = date('Y-m-d H:i:s');
        $endTime = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $stmt = $pdo->prepare("
            INSERT INTO luck_wallet_mining_sessions 
            (user_id, start_time, end_time, is_completed, mined_amount, last_update)
            VALUES (?, ?, ?, 0, 0, NOW())
        ");
        
        $stmt->execute([$userId, $startTime, $endTime]);
        $sessionId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'sessionId' => $sessionId,
            'startTime' => $startTime,
            'endTime' => $endTime
        ]);
        
    } catch (PDOException $e) {
        error_log("Start mining error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to start mining']);
    }
}

/**
 * Get current mining status
 */
function getMiningStatus($pdo, $userId) {
    header('Content-Type: application/json');
    
    try {
        // Get the most recent mining session
        $stmt = $pdo->prepare("
            SELECT * FROM luck_wallet_mining_sessions 
            WHERE user_id = ? 
            ORDER BY start_time DESC 
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            // No active or completed sessions
            echo json_encode([
                'success' => true,
                'status' => 'inactive',
                'isActive' => false,
                'progress' => 0,
                'minedAmount' => 0,
                'timeLeft' => 0,
                'isCompleted' => true
            ]);
            return;
        }
        
        $now = new DateTime();
        $endTime = new DateTime($session['end_time']);
        $isCompleted = (bool)$session['is_completed'];
        
        // Check if session should be completed
        if (!$isCompleted && $now >= $endTime) {
            // Only complete the session if it hasn't been completed yet and has rewards
            $stmt = $pdo->prepare("
                UPDATE luck_wallet_mining_sessions 
                SET is_completed = 1, 
                    last_update = NOW()
                WHERE session_id = ? AND is_completed = 0
                AND (mined_amount > 0 OR last_update < ? - INTERVAL 1 HOUR)
            ");
            $oneHourAgo = (new DateTime())->modify('-1 hour')->format('Y-m-d H:i:s');
            $stmt->execute([$session['session_id'], $oneHourAgo]);
            
            if ($stmt->rowCount() > 0) {
                // Only set mined_amount if this is a fresh completion
                $isCompleted = true;
                $session['is_completed'] = 1;
                
                // Only set mined_amount if it's not already set
                if ((float)$session['mined_amount'] <= 0) {
                    $stmt = $pdo->prepare("
                        UPDATE luck_wallet_mining_sessions 
                        SET mined_amount = 35.00
                        WHERE session_id = ? AND (mined_amount IS NULL OR mined_amount <= 0)
                    ");
                    $stmt->execute([$session['session_id']]);
                    $session['mined_amount'] = '35.00';
                }
            } else {
                // If no rows were updated, check if the session is already completed
                $stmt = $pdo->prepare("
                    SELECT is_completed, mined_amount 
                    FROM luck_wallet_mining_sessions 
                    WHERE session_id = ?
                ");
                $stmt->execute([$session['session_id']]);
                $updatedSession = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($updatedSession) {
                    $isCompleted = (bool)$updatedSession['is_completed'];
                    $session['is_completed'] = $updatedSession['is_completed'];
                    $session['mined_amount'] = $updatedSession['mined_amount'];
                }
            }
        }
        
        // Calculate progress and time left
        $progress = 0;
        $timeLeft = 0;
        $minedAmount = max(0, (float)$session['mined_amount']);
        
        // If session is completed but has no rewards, treat as inactive
        if ($isCompleted && $minedAmount <= 0) {
            echo json_encode([
                'success' => true,
                'status' => 'inactive',
                'isActive' => false,
                'progress' => 0,
                'minedAmount' => 0,
                'timeLeft' => 0,
                'isCompleted' => true
            ]);
            return;
        }
        
        if (!$isCompleted) {
            $startTime = new DateTime($session['start_time']);
            $totalSeconds = $endTime->getTimestamp() - $startTime->getTimestamp();
            $elapsed = $now->getTimestamp() - $startTime->getTimestamp();
            $progress = min(100, ($elapsed / $totalSeconds) * 100);
            $timeLeft = max(0, $endTime->getTimestamp() - $now->getTimestamp());
            
            // Calculate mined amount based on progress (up to 35 LUCK)
            $minedAmount = min(35.00, ($progress / 100) * 35);
            
            // Update the mined amount in the database periodically
            if (time() % 60 === 0) { // Update every minute
                $stmt = $pdo->prepare("
                    UPDATE luck_wallet_mining_sessions 
                    SET mined_amount = ?, last_update = NOW()
                    WHERE session_id = ?
                ");
                $stmt->execute([$minedAmount, $session['session_id']]);
            }
        }
        
        echo json_encode([
            'success' => true,
            'status' => $isCompleted ? 'completed' : 'mining',
            'isActive' => !$isCompleted,
            'sessionId' => $session['session_id'],
            'startTime' => $session['start_time'],
            'endTime' => $session['end_time'],
            'minedAmount' => $minedAmount,
            'progress' => $progress,
            'timeLeft' => $timeLeft,
            'isCompleted' => $isCompleted
        ]);
        
    } catch (PDOException $e) {
        error_log("Mining status error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to get mining status']);
    }
}

/**
 * Claim mining rewards
 */
function claimRewards($pdo, $userId) {
    header('Content-Type: application/json');
    
    try {
        $pdo->beginTransaction();
        
        // First, check if there's a completed session with unclaimed rewards
        $stmt = $pdo->prepare("
            SELECT * FROM luck_wallet_mining_sessions 
            WHERE user_id = ? AND is_completed = 1 
            ORDER BY end_time DESC 
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([$userId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            $pdo->rollBack();
            error_log("No completed mining session found for user $userId");
            echo json_encode(['success' => false, 'error' => 'No mining session found']);
            return;
        }
        
        // If session is completed but mined_amount is 0, check if it's a completed session
        if ((float)$session['mined_amount'] <= 0) {
            error_log("Session #{$session['session_id']} has no mined amount. Marking as claimed.");
            // Mark as claimed by setting mined_amount to 0
            $stmt = $pdo->prepare("
                UPDATE luck_wallet_mining_sessions 
                SET mined_amount = 0 
                WHERE session_id = ?
            ");
            $stmt->execute([$session['session_id']]);
            
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'No rewards available to claim']);
            return;
        }
        
        $rewardAmount = (float)$session['mined_amount'];
        
        // Add transaction record
        $stmt = $pdo->prepare("
            INSERT INTO luck_wallet_transactions 
            (sender_user_id, receiver_user_id, amount, transaction_type, notes)
            VALUES (NULL, ?, ?, 'mining_reward', ?)
        ");
        $notes = "Mining reward from session #" . $session['session_id'];
        $stmt->execute([$userId, $rewardAmount, $notes]);
        
        // Update user's balance
        $stmt = $pdo->prepare("
            UPDATE users 
            SET balance = balance + ? 
            WHERE id = ?
        ");
        $stmt->execute([$rewardAmount, $userId]);
        
        // Mark rewards as claimed and set is_completed to 1 to prevent double claims
        $stmt = $pdo->prepare("
            UPDATE luck_wallet_mining_sessions 
            SET mined_amount = 0,
                is_completed = 1,
                last_update = NOW()
            WHERE session_id = ?
        ");
        $stmt->execute([$session['session_id']]);
        
        $pdo->commit();
        
        // Get updated balance
        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'rewardAmount' => $rewardAmount,
            'newBalance' => (float)$user['balance']
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Claim reward error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to claim rewards']);
    }
}
?>
