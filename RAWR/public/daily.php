<?php
require_once __DIR__ . '/../backend/inc/init.php';
userOnly();

$userId = $_SESSION['user_id'];
$db = Database::getInstance();
$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

// Fetch all challenges (daily and achievements)
$challenges = $db->fetchAll("
    SELECT 
        ct.id,
        ct.name,
        ct.description,
        ct.reward_type,
        ct.reward_value,
        ct.target_value,
        COALESCE(cp.progress, 0) AS progress,
        cp.completed_at,
        cp.reward_claimed
    FROM challenge_types ct
    LEFT JOIN challenge_progress cp
        ON cp.challenge_id = ct.id AND cp.user_id = ?
", [$userId]);

if (!$challenges) $challenges = [];

// --- Update the description for "Big Spender" challenge if needed ---
foreach ($challenges as &$challenge) {
    if (
        isset($challenge['name']) &&
        stripos($challenge['name'], 'big spender') !== false
    ) {
        $challenge['description'] = 'Spend 1,000 tickets in the casino';
        $challenge['reward_type'] = 'tickets';
        $challenge['reward_value'] = 100;
    }
}
unset($challenge);

// Add this after fetching challenges
$allChallenges = $challenges;

// Separate challenges into daily missions and achievements
$dailyMissions = array_filter($allChallenges, function($c) {
    return in_array($c['name'], [
        'Daily Login', 
        'Daily Mining',
        'Casino Enthusiast',
        'Referral Master',
        'Big Spender'
    ]);
});

$achievements = array_filter($allChallenges, function($c) {
    return in_array($c['name'], [
        'Mining Master',
        'Game Enthusiast',
        'RAWR Millionaire',
        'Casino Royal',
        'Jungle King',
        'Loyal Lion'
    ]);
});

// Handle challenge claims
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['claim_challenge'])) {
    $challengeId = (int)$_POST['claim_challenge'];
    foreach ($challenges as $challenge) {
        if ($challenge['id'] == $challengeId && 
            $challenge['completed_at'] && 
            !$challenge['reward_claimed']) {
            try {
                $db->beginTransaction();
                $db->executeQuery(
                    "UPDATE challenge_progress SET reward_claimed = 1 WHERE user_id = ? AND challenge_id = ?",
                    [$userId, $challengeId]
                );
                $rewardType = $challenge['reward_type'];
                $rewardValue = $challenge['reward_value'];
                if ($rewardType === 'rawr') {
                    $db->executeQuery(
                        "UPDATE users SET rawr_balance = rawr_balance + ? WHERE id = ?",
                        [$rewardValue, $userId]
                    );
                } elseif ($rewardType === 'tickets') {
                    $db->executeQuery(
                        "UPDATE users SET ticket_balance = ticket_balance + ? WHERE id = ?",
                        [$rewardValue, $userId]
                    );
                }
                $db->commit();
                
                // Log the challenge reward
                $db->insert('reward_logs', [
                    'user_id' => $userId,
                    'type' => 'challenge',
                    'rawr_amount' => ($rewardType === 'rawr') ? $rewardValue : 0,
                    'ticket_amount' => ($rewardType === 'tickets') ? $rewardValue : 0
                ]);

                // Set reward popup data
                $showRewardPopup = true;
                $rewardPopupData = [
                    'rawr' => ($rewardType === 'rawr') ? $rewardValue : 0,
                    'tickets' => ($rewardType === 'tickets') ? $rewardValue : 0
                ];
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Challenge claim failed: " . $e->getMessage());
            }
        }
    }
}

// Handle challenge progress updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process challenge actions
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $challengeId = (int)($_POST['challenge_id'] ?? 0);

        // Get challenge details
        $challenge = $db->fetchOne("SELECT * FROM challenge_types WHERE id = ?", [$challengeId]);

        if ($challenge) {
            // Get or create progress record
            $progress = $db->fetchOne(
                "SELECT * FROM challenge_progress 
                WHERE user_id = ? AND challenge_id = ?",
                [$userId, $challengeId]
            );

            if (!$progress) {
                $db->insert('challenge_progress', [
                    'user_id' => $userId,
                    'challenge_id' => $challengeId,
                    'progress' => 0,
                    'completed_at' => null,
                    'reward_claimed' => 0
                ]);
                $progress = ['progress' => 0];
            }

            // Handle different actions
            switch ($action) {
                case 'update_progress':
                    $increment = (int)($_POST['increment'] ?? 1);
                    $newProgress = min(
                        $challenge['target_value'], 
                        $progress['progress'] + $increment
                    );

                    $db->executeQuery(
                        "UPDATE challenge_progress 
                        SET progress = ? 
                        WHERE user_id = ? AND challenge_id = ?",
                        [$newProgress, $userId, $challengeId]
                    );

                    // Check if completed
                    if ($newProgress >= $challenge['target_value']) {
                        $db->executeQuery(
                            "UPDATE challenge_progress 
                            SET completed_at = NOW() 
                            WHERE user_id = ? AND challenge_id = ?",
                            [$userId, $challengeId]
                        );
                    }
                    break;

                case 'claim_reward':
                    if ($progress['completed_at'] && !$progress['reward_claimed']) {
                        $rewardType = $challenge['reward_type'];
                        $rewardValue = $challenge['reward_value'];

                        if ($rewardType === 'rawr') {
                            $db->executeQuery(
                                "UPDATE users 
                                SET rawr_balance = rawr_balance + ? 
                                WHERE id = ?",
                                [$rewardValue, $userId]
                            );
                        } elseif ($rewardType === 'tickets') {
                            $db->executeQuery(
                                "UPDATE users 
                                SET ticket_balance = ticket_balance + ? 
                                WHERE id = ?",
                                [$rewardValue, $userId]
                            );
                        }

                        $db->executeQuery(
                            "UPDATE challenge_progress 
                            SET reward_claimed = 1 
                            WHERE user_id = ? AND challenge_id = ?",
                            [$userId, $challengeId]
                        );

                        // Log reward
                        $db->insert('reward_logs', [
                            'user_id' => $userId,
                            'type' => 'challenge',
                            'rawr_amount' => ($rewardType === 'rawr') ? $rewardValue : 0,
                            'ticket_amount' => ($rewardType === 'tickets') ? $rewardValue : 0
                        ]);

                        $showRewardPopup = true;
                        $rewardPopupData = [
                            'rawr' => ($rewardType === 'rawr') ? $rewardValue : 0,
                            'tickets' => ($rewardType === 'tickets') ? $rewardValue : 0
                        ];
                    }
                    break;
            }
        }
    }
}

// Refresh user data
$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

$pageTitle = "Daily Missions - RAWR Casino";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/RAWR/public/css/style.css">
    <style>
        /* Updated Hero Section */
        .daily-hero {
            padding: 120px 2rem 60px;
            text-align: center;
            background: linear-gradient(135deg, rgba(30, 30, 30, 0.9) 0%, rgba(45, 24, 16, 0.9) 100%);
            position: relative;
            overflow: hidden;
            border-bottom: 1px solid rgba(255, 215, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .daily-hero::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><path d="M20,20 Q40,5 60,20 T100,20 Q85,40 100,60 T100,100 Q60,85 20,100 T0,100 Q5,60 0,20 T20,20 Z" fill="none" stroke="rgba(255,215,0,0.05)" stroke-width="0.5"/></svg>');
            background-size: 300px;
            opacity: 0.3;
            z-index: -1;
        }
        
        .daily-hero h1 {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            background: linear-gradient(to right, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 20px rgba(255, 215, 0, 0.2);
        }
        
        .daily-hero p {
            font-size: 1.2rem;
            max-width: 700px;
            margin: 0 auto 2rem;
            color: var(--text-muted);
        }
        
        .streak-display {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin: 1.5rem 0;
            flex-wrap: wrap;
        }
        
        .streak-stat {
            text-align: center;
            padding: 1.5rem 2rem;
            background: rgba(0, 0, 0, 0.3);
            border-radius: var(--border-radius);
            border: 1px solid rgba(255, 215, 0, 0.2);
            min-width: 150px;
        }
        
        .streak-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.25rem;
        }
        
        .streak-label {
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        
        /* Improved Card Spacing */
        .mission-section, .achievement-section {
            padding: 0 2rem 3rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .challenges-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            margin: 0 1rem;
        }
        
        .challenge-card {
            background: rgba(30, 30, 30, 0.7);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            padding: 2rem;
            border: 1px solid rgba(255, 215, 0, 0.15);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.25);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .challenge-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3), var(--glow);
        }
        
        .challenge-header {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .challenge-icon {
            width: 60px;
            height: 60px;
            background: rgba(255, 215, 0, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: var(--primary);
            flex-shrink: 0;
        }
        
        .challenge-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary);
        }
        
        .challenge-desc {
            margin-bottom: 1.5rem;
            color: var(--text-muted);
            font-size: 1.1rem;
            line-height: 1.6;
        }
        
        .progress-container {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 12px;
            height: 26px; /* Smaller bar height */
            margin-bottom: 1.2rem;
            overflow: hidden;
            position: relative;
            display: flex;
            align-items: center;
        }
        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            border-radius: 12px;
            position: absolute;
            left: 0;
            top: 0;
            transition: width 1s ease;
            z-index: 1;
        }
        .progress-text {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            font-size: 1.05rem; /* Smaller text */
            color: var(--text-muted); /* Gray color like description */
            font-weight: 700;
            z-index: 2;
            letter-spacing: 0.5px;
            text-shadow: none;
            width: 100%;
            text-align: center;
            pointer-events: none;
        }
        
        .challenge-reward {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.1rem;
            color: var(--primary);
            margin-top: 1.5rem;
            font-weight: 500;
        }
        
        .challenge-btn {
            background: rgba(255, 215, 0, 0.1);
            border: 1px solid var(--glass-border);
            color: var(--primary);
            border-radius: 8px;
            padding: 0.8rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 1.5rem;
            width: 100%;
            max-width: 250px;
            text-decoration: none; /* <-- Add this line to remove underline */
            display: inline-block;  /* Ensure anchor looks like button */
        }
        
        .challenge-btn:hover {
            background: rgba(255, 215, 0, 0.2);
            transform: translateY(-3px);
        }
        
        .achievement-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: var(--primary);
            color: #1a1a1a;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.5);
        }
        
        /* Reward Popup */
        .reward-popup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(20, 20, 20, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 215, 0, 0.1);
            border-radius: var(--border-radius);
            padding: 3rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            z-index: 200;
            text-align: center;
            display: none;
            width: 90%;
            max-width: 500px;
        }

        .reward-popup h3 {
            font-size: 2rem;
            margin-bottom: 1.5rem;
            color: var(--primary);
        }

        .reward-amount {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 1rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
        }

        .reward-amount div {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .reward-message {
            font-size: 1.1rem;
            color: var(--text-light);
            margin-bottom: 2rem;
        }

        #closePopup {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #1a1a1a;
            border: none;
            border-radius: 8px;
            padding: 1rem 2.5rem;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1rem;
        }

        #closePopup:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(255, 215, 0, 0.4);
        }

        /* Overlay for Popup */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 100;
            display: none;
        }
        
        /* Section Titles */
        .section-title {
            font-size: 2.2rem;
            text-align: center;
            margin-bottom: 2.5rem;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1.2rem;
            padding: 0 1rem;
        }
        
        /* Mission Categories */
        .mission-categories {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin-bottom: 2.5rem;
            flex-wrap: wrap;
            padding: 0 1rem;
        }
        
        .mission-category {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 215, 0, 0.2);
            padding: 0.8rem 2rem;
            border-radius: 50px;
            cursor: pointer;
            transition: var(--transition);
            font-size: 1.1rem;
            font-weight: 500;
        }
        
        .mission-category.active, .mission-category:hover {
            background: rgba(255, 215, 0, 0.1);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        @media (max-width: 768px) {
            .daily-hero h1 {
                font-size: 2.8rem;
            }
            
            .streak-display {
                gap: 1rem;
            }
            
            .streak-stat {
                padding: 1rem;
                min-width: 120px;
            }
            
            .streak-value {
                font-size: 2rem;
            }
            
            .checkin-day {
                min-width: 140px;
            }
            
            .challenges-grid {
                grid-template-columns: 1fr;
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <nav class="top-nav">
        <div class="logo">
            <div class="coin-logo"></div>
            <span>RAWR</span>
        </div>
        
        <div class="nav-actions">
            <div class="wallet-balance">
                <div class="balance-item">
                    <i class="fas fa-coins balance-icon"></i>
                    <span class="balance-label">RAWR:</span>
                    <span class="balance-value"><?= number_format($user['rawr_balance'], 2) ?></span>
                </div>
                <div class="balance-item">
                    <i class="fas fa-ticket-alt balance-icon"></i>
                    <span class="balance-label">Tickets:</span>
                    <span class="balance-value"><?= number_format($user['ticket_balance'], 2) ?></span>
                </div>
            </div>
            <button class="menu-toggle" id="menuToggle">
                <i class="fas fa-bars"></i>
                <i class="fas fa-xmark"></i>
            </button>
        </div>
    </nav>
    
    <aside class="sidebar" id="sidebar">
        <a href="dashboard.php" class="sidebar-item">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        <a href="mining.php" class="sidebar-item">
            <i class="fas fa-digging"></i>
            <span>Mining</span>
        </a>
        <a href="games.php" class="sidebar-item">
            <i class="fas fa-dice"></i>
            <span>Casino</span>
        </a>
        <a href="wallet.php" class="sidebar-item">
            <i class="fas fa-wallet"></i>
            <span>Wallet</span>
        </a>
        <a href="leaderboard.php" class="sidebar-item">
            <i class="fas fa-trophy"></i>
            <span>Leaderboard</span>
        </a>
        <a href="daily.php" class="sidebar-item active">
            <i class="fas fa-gift"></i>
            <span>Daily Rewards</span>
        </a>
        <a href="profile.php" class="sidebar-item">
            <i class="fas fa-user"></i>
            <span>Profile</span>
        </a>
        <a href="logout.php" class="sidebar-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </aside>
    
    <section class="daily-hero">
        <h1>Daily Missions & Challenges</h1>
        <p>Complete daily missions to earn extra RAWR tokens and tickets. Keep your streak going for bigger rewards!</p>
    </section>
    
    <section class="mission-section">
        <h2 class="section-title">
            <i class="fas fa-tasks"></i>
            Daily Missions
        </h2>
        
        <div class="challenges-grid">
            <?php foreach ($dailyMissions as $challenge): ?>
            <div class="challenge-card">
                <?php if ($challenge['completed_at'] && !$challenge['reward_claimed']): ?>
                    <span class="achievement-badge">Reward Ready!</span>
                <?php elseif ($challenge['completed_at']): ?>
                    <span class="achievement-badge">Completed</span>
                <?php endif; ?>
                
                <div class="challenge-header">
                    <div class="challenge-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <h3 class="challenge-title"><?= htmlspecialchars($challenge['name']) ?></h3>
                </div>
                <p class="challenge-desc"><?= htmlspecialchars($challenge['description']) ?></p>
                <div class="progress-container">
                    <?php
                        $progress = 0;
                        if ($challenge['completed_at']) {
                            $progress = 100;
                        } elseif ($challenge['progress'] > 0) {
                            $progress = min(100, round($challenge['progress'] / $challenge['target_value'] * 100));
                        }
                        $current = isset($challenge['progress']) ? $challenge['progress'] : 0;
                        $target = isset($challenge['target_value']) ? $challenge['target_value'] : 1;
                    ?>
                    <div class="progress-bar" style="width: <?= $progress ?>%"></div>
                    <span class="progress-text"><?= number_format($current, 2) ?>/<?= number_format($target, 2) ?></span>
                </div>
                <div class="challenge-reward">
                    <?php if ($challenge['reward_type'] === 'tickets'): ?>
                        <i class="fas fa-ticket-alt"></i> 
                        Reward: <?= (int)$challenge['reward_value'] ?> Tickets
                    <?php elseif ($challenge['reward_type'] === 'rawr'): ?>
                        <i class="fas fa-coins"></i> 
                        Reward: <?= number_format($challenge['reward_value'], 2) ?> RAWR
                    <?php elseif ($challenge['reward_type'] === 'item'): ?>
                        <?php
                            $item = $db->fetchOne(
                                "SELECT name FROM shop_items WHERE id = ?",
                                [(int)$challenge['reward_value']]
                            );
                            $itemName = $item ? $item['name'] : 'Mystery Item';
                        ?>
                        <i class="fas fa-gift"></i> 
                        Reward: <?= htmlspecialchars($itemName) ?>
                    <?php endif; ?>
                </div>
                <?php
                    // Determine the correct href for the Continue button
                    $continueHref = '';
                    if (stripos($challenge['name'], 'referral') !== false) {
                        $continueHref = 'dashboard.php';
                    } elseif (stripos($challenge['name'], 'mining') !== false) {
                        $continueHref = 'mining.php';
                    } elseif (stripos($challenge['name'], 'casino') !== false) {
                        $continueHref = 'games.php';
                    } elseif (stripos($challenge['name'], 'big spender') !== false) {
                        $continueHref = 'games.php';
                    } elseif (stripos($challenge['name'], 'login') !== false) {
                        $continueHref = 'dashboard.php';
                    }
                ?>
                <form method="post">
                    <input type="hidden" name="claim_challenge" value="<?= $challenge['id'] ?>">
                    <?php if ($challenge['completed_at']): ?>
                        <button type="submit" class="challenge-btn"
                            <?= $challenge['reward_claimed'] ? 'disabled' : '' ?>>
                            <?= $challenge['reward_claimed'] ? 'Completed' : 'Claim Reward' ?>
                        </button>
                    <?php else: ?>
                        <a href="<?= $continueHref ?>" class="challenge-btn">Continue</a>
                    <?php endif; ?>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
<section class="achievement-section">
    <h2 class="section-title">
        <i class="fas fa-medal"></i>
        Achievements
    </h2>
    
    <div class="challenges-grid">
        <?php foreach ($achievements as $challenge): ?>
        <div class="challenge-card">
            <?php if ($challenge['completed_at'] && !$challenge['reward_claimed']): ?>
                <span class="achievement-badge">Reward Ready!</span>
            <?php elseif ($challenge['completed_at']): ?>
                <span class="achievement-badge">Completed</span>
            <?php endif; ?>
            
            <div class="challenge-header">
                <div class="challenge-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <h3 class="challenge-title"><?= htmlspecialchars($challenge['name']) ?></h3>
            </div>
            <p class="challenge-desc"><?= htmlspecialchars($challenge['description']) ?></p>
            <div class="progress-container">
                <?php
                    $progress = 0;
                    if ($challenge['completed_at']) {
                        $progress = 100;
                    } elseif ($challenge['progress'] > 0) {
                        $progress = min(100, round($challenge['progress'] / $challenge['target_value'] * 100));
                    }
                    $current = isset($challenge['progress']) ? $challenge['progress'] : 0;
                    $target = isset($challenge['target_value']) ? $challenge['target_value'] : 1;
                ?>
                <div class="progress-bar" style="width: <?= $progress ?>%"></div>
                <span class="progress-text"><?= number_format($current, 2) ?>/<?= number_format($target, 2) ?></span>
            </div>
            <div class="challenge-reward">
                <?php if ($challenge['reward_type'] === 'tickets'): ?>
                    <i class="fas fa-ticket-alt"></i> 
                    Reward: <?= (int)$challenge['reward_value'] ?> Tickets
                <?php elseif ($challenge['reward_type'] === 'rawr'): ?>
                    <i class="fas fa-coins"></i> 
                    Reward: <?= number_format($challenge['reward_value'], 2) ?> RAWR
                <?php elseif ($challenge['reward_type'] === 'item'): ?>
                    <?php
                        $item = $db->fetchOne(
                            "SELECT name FROM shop_items WHERE id = ?",
                            [(int)$challenge['reward_value']]
                        );
                        $itemName = $item ? $item['name'] : 'Mystery Item';
                    ?>
                    <i class="fas fa-gift"></i> 
                    Reward: <?= htmlspecialchars($itemName) ?>
                <?php endif; ?>
            </div>
            <form method="post">
                <input type="hidden" name="claim_challenge" value="<?= $challenge['id'] ?>">
                <?php
                    // Determine the correct href for the Continue button
                    $continueHref = '';
                    if (stripos($challenge['name'], 'referral') !== false) {
                        $continueHref = 'dashboard.php';
                    } elseif (stripos($challenge['name'], 'mining') !== false) {
                        $continueHref = 'mining.php';
                    } elseif (stripos($challenge['name'], 'casino') !== false) {
                        $continueHref = 'games.php';
                    } elseif (stripos($challenge['name'], 'big spender') !== false) {
                        $continueHref = 'games.php';
                    } elseif (stripos($challenge['name'], 'login') !== false) {
                        $continueHref = 'dashboard.php';
                    } elseif (stripos($challenge['name'], 'game enthusiast') !== false) {
                        $continueHref = 'games.php';
                    } elseif (stripos($challenge['name'], 'mining master') !== false) {
                        $continueHref = 'mining.php';
                    } elseif (stripos($challenge['name'], 'casino royal') !== false) {
                        $continueHref = 'games.php';
                    } elseif (stripos($challenge['name'], 'jungle king') !== false) {
                        $continueHref = 'leaderboard.php';
                    } elseif (stripos($challenge['name'], 'loyal lion') !== false) {
                        $continueHref = 'dashboard.php';
                    } elseif (stripos($challenge['name'], 'rawr millionaire') !== false) {
                        $continueHref = 'dashboard.php';
                    }
                ?>
                <?php if ($challenge['completed_at']): ?>
                    <button type="submit" class="challenge-btn"
                        <?= $challenge['reward_claimed'] ? 'disabled' : '' ?>>
                        <?= $challenge['reward_claimed'] ? 'Completed' : 'Claim Reward' ?>
                    </button>
                <?php elseif ($progress >= 100): ?>
                    <button type="submit" class="challenge-btn">Claim Reward</button>
                <?php else: ?>
                    <?php if ($continueHref): ?>
                        <a href="<?= $continueHref ?>" class="challenge-btn" style="text-align:center;display:inline-block;">Continue</a>
                    <?php else: ?>
                        <button type="button" class="challenge-btn" disabled>Continue</button>
                    <?php endif; ?>
                <?php endif; ?>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
</section>
    
    <footer>
        <div class="footer-content">
            <div class="footer-column">
                <h3>RAWR Casino</h3>
                <p>The ultimate play-to-earn experience in the jungle. Mine, play, and earn your way to the top!</p>
                <div class="social-links">
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-discord"></i></a>
                    <a href="#"><i class="fab fa-telegram"></i></a>
                    <a href="#"><i class="fab fa-reddit"></i></a>
                </div>
            </div>
            
            <div class="footer-column">
                <h3>Quick Links</h3>
                <ul class="footer-links">
                    <li><a href="#">Home</a></li>
                    <li><a href="#">Mining</a></li>
                    <li><a href="#">Casino</a></li>
                    <li><a href="#">Leaderboard</a></li>
                    <li><a href="#">Shop</a></li>
                </ul>
            </div>
            
            <div class="footer-column">
                <h3>Resources</h3>
                <ul class="footer-links">
                    <li><a href="#">FAQs</a></li>
                    <li><a href="#">Tutorials</a></li>
                    <li><a href="#">Whitepaper</a></li>
                    <li><a href="#">Tokenomics</a></li>
                    <li><a href="#">Support</a></li>
                </ul>
            </div>
            
            <div class="footer-column">
                <h3>Legal</h3>
                <ul class="footer-links">
                    <li><a href="#">Terms of Service</a></li>
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Disclaimer</a></li>
                    <li><a href="#">AML Policy</a></li>
                </ul>
            </div>
        </div>
        
        <div class="copyright">
            &copy; <?= date('Y') ?> RAWR Casino. All rights reserved. The jungle is yours to conquer!
        </div>
    </footer>
    
    <div class="overlay" id="overlay"></div>
    <div class="reward-popup" id="rewardPopup">
        <h3>Reward Claimed!</h3>
        <div class="reward-amount" id="rewardAmount">
            <?php if ($rewardPopupData['rawr'] > 0): ?>
                <div><i class="fas fa-coins"></i> +<?= $rewardPopupData['rawr'] ?> RAWR</div>
            <?php endif; ?>
            <?php if ($rewardPopupData['tickets'] > 0): ?>
                <div><i class="fas fa-ticket-alt"></i> +<?= $rewardPopupData['tickets'] ?> Tickets</div>
            <?php endif; ?>
        </div>
        <p class="reward-message">Your reward has been added to your balance</p>
        <button class="feature-btn" id="closePopup">Close</button>
    </div>
    
    <script>
        // DOM Elements
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        const rewardPopup = document.getElementById('rewardPopup');
        const closePopupBtn = document.getElementById('closePopup');
        
        // Menu Toggle for Mobile with X icon
        menuToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('active');
            menuToggle.classList.toggle('active');
        });
        
        // Close sidebar when clicking outside
        document.addEventListener('click', (e) => {
            if (sidebar.classList.contains('active') && 
                !sidebar.contains(e.target) && 
                !menuToggle.contains(e.target)) {
                sidebar.classList.remove('active');
                menuToggle.classList.remove('active');
            }
        });
        
        // Reward Popup Handling
        <?php if (isset($showRewardPopup) && $showRewardPopup): ?>
            document.addEventListener('DOMContentLoaded', function() {
                overlay.style.display = 'block';
                rewardPopup.style.display = 'block';
            });
        <?php endif; ?>
        
        // Close popup button
        closePopupBtn?.addEventListener('click', function() {
            overlay.style.display = 'none';
            rewardPopup.style.display = 'none';
        });
        
        // Close popup when clicking on overlay
        overlay?.addEventListener('click', function() {
            overlay.style.display = 'none';
            rewardPopup.style.display = 'none';
        });
        
        // Mission category selection
        const missionCategories = document.querySelectorAll('.mission-category');
        missionCategories.forEach(category => {
            category.addEventListener('click', () => {
                // Remove active class from all categories
                missionCategories.forEach(cat => cat.classList.remove('active'));
                // Add active class to clicked category
                category.classList.add('active');
            });
        });
        
        // Challenge card animations
        const challengeCards = document.querySelectorAll('.challenge-card');
        challengeCards.forEach(card => {
            card.addEventListener('mouseenter', () => {
                card.style.transform = 'translateY(-10px)';
                card.style.boxShadow = '0 15px 30px rgba(0, 0, 0, 0.4), 0 0 20px rgba(255, 215, 0, 0.4)';
            });
            
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'translateY(0)';
                card.style.boxShadow = '0 8px 25px rgba(0, 0, 0, 0.2)';
            });
        });
    </script>
</body>
</html>