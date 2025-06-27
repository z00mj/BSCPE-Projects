<?php
require_once __DIR__ . '/../backend/inc/init.php';
userOnly();

$userId = $_SESSION['user_id'];
$db = Database::getInstance();

// Function to get or create user's mining records
function getOrCreateMiningData($db, $userId) {
    $data = $db->fetchOne("
        SELECT 
            u.rawr_balance, 
            u.ticket_balance,
            md.last_mined_at,
            md.total_mined,
            mu.shovel_level,
            mu.energy_level,
            mu.pickaxe_level
        FROM users u
        LEFT JOIN mining_data md ON u.id = md.user_id
        LEFT JOIN mining_upgrades mu ON u.id = mu.user_id
        WHERE u.id = ?
    ", [$userId]);

    if (!$data || $data['shovel_level'] === null) {
        $db->beginTransaction();
        try {
            if ($db->fetchOne("SELECT user_id FROM mining_data WHERE user_id = ?", [$userId]) === null) {
                $db->insert("mining_data", ['user_id' => $userId, 'total_mined' => 0.0, 'last_mined_at' => null]);
            }
            if ($db->fetchOne("SELECT user_id FROM mining_upgrades WHERE user_id = ?", [$userId]) === null) {
                $db->insert("mining_upgrades", [
                    'user_id' => $userId, 
                    'shovel_level' => 1, 
                    'energy_level' => 1, 
                    'pickaxe_level' => 1
                ]);
            }
            $db->commit();
            return getOrCreateMiningData($db, $userId);
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Mining init failed for user {$userId}: " . $e->getMessage());
            jsonResponse(['status' => 'error', 'message' => 'System error during initialization.'], 500);
        }
    }
    return $data;
}

// Handle mining actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitizeInput($_POST['action'] ?? '');
    
    switch ($action) {
        case 'mine':
            $db->beginTransaction();
            try {
                // Lock user row and get balance
                $user = $db->fetchOne("SELECT rawr_balance FROM users WHERE id = ? FOR UPDATE", [$userId]);
                if (!$user) {
                    throw new Exception("User not found during transaction.");
                }

                // Get dependent data
                $miningData = $db->fetchOne("SELECT * FROM mining_data WHERE user_id = ?", [$userId]);
                $upgrades = $db->fetchOne("SELECT * FROM mining_upgrades WHERE user_id = ?", [$userId]);

                // Check and create if missing
                if (!$miningData || !$upgrades) {
                    if (!$miningData) {
                        $db->insert("mining_data", ['user_id' => $userId, 'last_mined_at' => null, 'total_mined' => 0.0]);
                    }
                    if (!$upgrades) {
                        $db->insert("mining_upgrades", ['user_id' => $userId, 'shovel_level' => 1, 'energy_level' => 1, 'pickaxe_level' => 1]);
                    }
                    $db->commit();
                    jsonResponse(['status' => 'error', 'message' => 'Account finalizing. Please click Mine again.'], 503);
                    exit;
                }
                
                // Combine data and proceed
                $fullData = array_merge($user, $miningData, $upgrades);
                $cooldown = getMiningCooldown((int)$fullData['energy_level']);
                $lastMinedTimestamp = $fullData['last_mined_at'] ? strtotime($fullData['last_mined_at']) : 0;
                
                if ($lastMinedTimestamp !== 0 && (time() - $lastMinedTimestamp) < $cooldown) {
                    $db->rollBack();
                    jsonResponse(['status' => 'error', 'message' => 'Mining is on cooldown.'], 429);
                    exit;
                }

                $totalReward = 0;
                $bonus = 0;
                if ($lastMinedTimestamp !== 0) {
                    $reward = calculateMiningReward((int)$fullData['shovel_level'], 10);
                    if (mt_rand(1, 100) <= ((int)$fullData['pickaxe_level'] * 10)) {
                        $bonus = $reward * (mt_rand(50, 200) / 100);
                    }
                    $totalReward = $reward + $bonus;

                    $newRawrBalance = (float)$fullData['rawr_balance'] + $totalReward;
                    $newTotalMined = (float)$fullData['total_mined'] + $totalReward;

                    $db->update("users", ['rawr_balance' => $newRawrBalance], "id = ?", [$userId]);
                    $db->update("mining_data", ['total_mined' => $newTotalMined], "user_id = ?", [$userId]);
                    $db->insert("mining_logs", ['user_id' => $userId, 'amount' => $totalReward]);
                }
                
                $db->update("mining_data", ['last_mined_at' => date('Y-m-d H:i:s')], "user_id = ?", [$userId]);
                $db->commit();

                $finalBalance = (float)$db->fetchOne("SELECT rawr_balance FROM users WHERE id = ?", [$userId])['rawr_balance'];

                jsonResponse([
                    'status' => 'success',
                    'reward' => round($totalReward, 4),
                    'bonus' => round($bonus, 4),
                    'message' => $lastMinedTimestamp === 0 ? 'Mining started!' : 'Reward claimed!',
                    'new_balance' => round($finalBalance, 4),
                    'new_cooldown' => $cooldown
                ]);

            } catch (Exception $e) {
                $db->rollBack();
                error_log("Claim reward DB error for user {$userId}: " . $e->getMessage());
                jsonResponse(['status' => 'error', 'message' => 'A database error occurred. Please try again.'], 500);
            }
            break;

        case 'upgrade_equipment':
             $type = sanitizeInput($_POST['type'] ?? '');
            if (!in_array($type, ['shovel', 'energy', 'pickaxe'])) {
                jsonResponse(['status' => 'error', 'message' => 'Invalid upgrade type'], 400);
            }
            
            $db->beginTransaction();
            try {
                $upgradeData = $db->fetchOne("
                    SELECT rawr_balance, shovel_level, energy_level, pickaxe_level 
                    FROM users u
                    JOIN mining_upgrades mu ON u.id = mu.user_id
                    WHERE u.id = ? FOR UPDATE", [$userId]
                );
                
                if(!$upgradeData) {
                    throw new Exception("Could not fetch upgrade data for user.");
                }

                $currentLevel = (int)$upgradeData["{$type}_level"];
                if ($currentLevel >= MAX_UPGRADE_LEVEL) {
                    $db->rollBack();
                    jsonResponse(['status' => 'error', 'message' => 'Max level reached!'], 400);
                    exit;
                }

                $baseCosts = ['shovel' => 15, 'energy' => 25, 'pickaxe' => 50];
                $cost = ceil($baseCosts[$type] * pow(1.8, $currentLevel - 1));

                if ((float)$upgradeData['rawr_balance'] < $cost) {
                    $db->rollBack();
                    jsonResponse(['status' => 'error', 'message' => 'Insufficient RAWR balance.'], 402);
                    exit;
                }

                $newRawrBalance = (float)$upgradeData['rawr_balance'] - $cost;
                $newLevel = $currentLevel + 1;
                
                $db->update("users", ['rawr_balance' => $newRawrBalance], "id = ?", [$userId]);
                $db->update("mining_upgrades", ["{$type}_level" => $newLevel], "user_id = ?", [$userId]);
                
                $db->commit();
                
                $newCooldown = ($type === 'energy') ? getMiningCooldown($newLevel) : getMiningCooldown((int)$upgradeData['energy_level']);
                
                jsonResponse([
                    'status' => 'success',
                    'new_level' => $newLevel,
                    'new_balance' => round($newRawrBalance, 4),
                    'new_cooldown' => $newCooldown
                ]);

            } catch (Exception $e) {
                $db->rollBack();
                error_log("Upgrade failed for user {$userId}, type {$type}: " . $e->getMessage());
                jsonResponse(['status' => 'error', 'message' => 'Upgrade failed due to a database error.'], 500);
            }
            break;
    }
    exit;
}

// Initial page load data
$userData = getOrCreateMiningData($db, $userId);
$shovelLevel = (int)($userData['shovel_level'] ?? 1);
$energyLevel = (int)($userData['energy_level'] ?? 1);
$pickaxeLevel = (int)($userData['pickaxe_level'] ?? 1);
$totalMined = (float)($userData['total_mined'] ?? 0);
$lastMinedAt = $userData['last_mined_at'];
$currentCooldown = getMiningCooldown($energyLevel);
$miningRatePerMinute = MINING_BASE_REWARD_PER_MINUTE * $shovelLevel;
$nextReward = $miningRatePerMinute * (MINING_COOLDOWN / 60);
$remainingTime = 0;
if ($lastMinedAt) {
    $lastMinedTimestamp = strtotime($lastMinedAt);
    $timeSince = time() - $lastMinedTimestamp;
    if ($timeSince < $currentCooldown) {
        $remainingTime = $currentCooldown - $timeSince;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RAWR Crypto Mining</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/RAWR/public/css/style.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <style>
        :root {
            --primary: #FFD700;
            --primary-light: #FFDF40;
            --secondary: #FFA500;
            --accent: #FF6B35;
            --dark-bg: #0d0d0d;
            --dark-bg-gradient: linear-gradient(135deg, #1a1a1a 0%, #2d1810 100%);
            --card-bg: rgba(30, 30, 30, 0.7);
            --text-light: #f0f0f0;
            --text-muted: #aaa;
            --border-radius: 12px;
            --transition: all 0.3s ease;
            --glass-bg: rgba(40, 40, 40, 0.35);
            --glass-border: rgba(255, 215, 0, 0.15);
            --glow: 0 0 15px rgba(255, 215, 0, 0.3);
            --section-spacing: 3rem;
        }

        /* Body and mining hero background styles */
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #1a1a1a 0%, #2d1810 50%, #1a1a1a 100%);
            color: var(--text-light);
            overflow-x: hidden;
            line-height: 1.6;
            position: relative;
            min-height: 100vh;
        }

        .mining-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 0;
            pointer-events: none;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800"><defs><radialGradient id="stars"><stop offset="0%" stop-color="%23FFD700" stop-opacity="1"/><stop offset="100%" stop-color="%23FFD700" stop-opacity="0"/></radialGradient></defs><circle cx="100" cy="100" r="2" fill="url(%23stars)"/><circle cx="300" cy="200" r="1.5" fill="url(%23stars)"/><circle cx="500" cy="150" r="1" fill="url(%23stars)"/><circle cx="700" cy="300" r="2" fill="url(%23stars)"/><circle cx="900" cy="250" r="1.5" fill="url(%23stars)"/><circle cx="1100" cy="400" r="1" fill="url(%23stars)"/></svg>') repeat;
            opacity: 0.6;
            animation: mining-twinkle 3s ease-in-out infinite alternate;
        }

        @keyframes mining-twinkle {
            0% { opacity: 0.6; }
            100% { opacity: 1; }
        }

        .mining-hero {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 120px 1rem 2rem;
            position: relative;
            overflow: hidden;
            background: radial-gradient(ellipse at center, rgba(255,215,0,0.08) 0%, transparent 70%);
            z-index: 1;
        }

        /* Full-screen hero section */
        .mining-hero {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 120px 1rem 2rem; /* Increased top padding for more margin above the mining hero */
            position: relative;
            overflow: hidden;
        }

        .mining-hero::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background:
                radial-gradient(circle at 20% 30%, rgba(255, 215, 0, 0.05) 0%, transparent 30%),
                radial-gradient(circle at 80% 70%, rgba(255, 107, 53, 0.05) 0%, transparent 30%);
            z-index: -1;
        }

        .hero-content {
            max-width: 1200px;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            z-index: 2;
        }

        .mining-hero h1 {
            font-size: 3.5rem;
            margin-bottom: 1.5rem;
            background: linear-gradient(to right, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 20px rgba(255, 215, 0, 0.2);
            line-height: 1.1;
        }

        .mining-hero p {
            font-size: 1.25rem;
            max-width: 700px;
            margin: 0 auto 2.5rem;
            color: var(--text-muted);
            padding: 0 1rem;
            line-height: 1.7;
        }

        .mining-character {
            position: relative;
            width: 280px;
            height: 280px;
            margin: 1rem auto 1.2rem;
            perspective: 1000px;
        }

        .character-container {
            position: relative;
            width: 100%;
            height: 100%;
            transform-style: preserve-3d;
            transition: transform 0.5s ease;
        }

        .mining-lion {
            position: absolute;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14rem;
            transform: translateZ(50px);
            filter: drop-shadow(0 0 30px rgba(255, 215, 0, 0.5));
            transition: all 0.3s ease;
            cursor: pointer;
            animation: idle-bounce 3s infinite ease-in-out;
        }

        .mining-progress-container {
            max-width: 600px;
            width: 100%;
            margin: 1.5rem auto 0;
            padding: 1.8rem;
            background: var(--card-bg);
            border-radius: var(--border-radius);
            border: 1px solid var(--glass-border);
            box-shadow: 0 10px 35px rgba(0, 0, 0, 0.25);
            backdrop-filter: blur(5px);
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1.2rem;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .progress-title {
            font-size: 1.2rem;
            color: var(--primary);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .progress-time {
            color: var(--text-muted);
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .progress-bar-container {
            height: 20px;
            background: rgba(0, 0, 0, 0.4);
            border-radius: 10px;
            overflow: hidden;
            position: relative;
            margin-bottom: 0.8rem;
            border: 1px solid rgba(0, 0, 0, 0.2);
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            border-radius: 10px;
            width: 45%;
            position: relative;
            overflow: hidden;
        }

        .progress-bar::after {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg,
                transparent,
                rgba(255, 255, 255, 0.3),
                transparent);
            animation: progress-shine 1.5s infinite;
        }

        .progress-stats {
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
            font-size: 0.95rem;
            color: var(--text-muted);
            margin-top: 1.2rem;
        }

        .progress-stats span {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stat-value {
            font-weight: 600;
            color: var(--primary);
        }

        .mining-controls {
            display: flex;
            gap: 1.5rem;
            margin-top: 2.5rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .mining-btn {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #1a1a1a;
            border: none;
            border-radius: 50px;
            padding: 1.1rem 2rem;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.7rem;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
            min-width: 220px;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .mining-btn::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg,
                rgba(255, 255, 255, 0.3),
                rgba(255, 255, 255, 0.1));
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: -1;
        }

        .mining-btn:hover::after {
            opacity: 1;
        }

        .mining-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(255, 215, 0, 0.4);
        }

        .mining-btn.secondary {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .mining-btn.secondary:hover {
            background: rgba(255, 215, 0, 0.1);
        }

        .mining-btn.pulse {
            animation: static-pulse 1.5s infinite ease-in-out;
        }

        /* Stats Section */
        .mining-stats {
            padding: var(--section-spacing) 1rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .section-title {
            font-size: 2rem;
            text-align: center;
            margin-bottom: 2.5rem;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.8rem;
            position: relative;
            padding-bottom: 1rem;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            border-radius: 3px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.8rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius);
            padding: 1.8rem;
            text-align: center;
            transition: var(--transition);
            box-shadow: 0 10px 35px rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3), var(--glow);
            border-color: rgba(255, 215, 0, 0.3);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1.2rem;
            color: var(--primary);
        }

        .stat-title {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            color: var(--text-muted);
            flex-grow: 1;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
            text-shadow: 0 0 10px rgba(255, 215, 0, 0.2);
        }

        .stat-subtext {
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        /* Upgrade Section */
        .upgrade-section {
            padding: var(--section-spacing) 1rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .upgrade-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.8rem;
        }

        .upgrade-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 1.8rem;
            border: 1px solid var(--glass-border);
            position: relative;
            overflow: hidden;
            transition: var(--transition);
            backdrop-filter: blur(5px);
        }

        .upgrade-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3), var(--glow);
        }

        .upgrade-header {
            display: flex;
            align-items: center;
            gap: 1.2rem;
            margin-bottom: 1.2rem;
        }

        .upgrade-icon {
            font-size: 2rem;
            color: var(--primary);
            width: 50px;
            height: 50px;
            background: rgba(255, 215, 0, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .upgrade-title {
            font-weight: 600;
            font-size: 1.2rem;
            color: var(--text-light);
            margin-bottom: 0.2rem;
        }

        .upgrade-level {
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        .upgrade-description {
            font-size: 0.95rem;
            margin-bottom: 1.8rem;
            color: var(--text-muted);
            line-height: 1.6;
        }

        .upgrade-cost {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cost-value {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: var(--primary);
            font-size: 1.1rem;
        }

        .upgrade-btn {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #1a1a1a;
            border: none;
            border-radius: 30px;
            padding: 0.7rem 1.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .upgrade-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(255, 215, 0, 0.4);
        }

        /* History Section */
        .mining-history {
            padding: var(--section-spacing) 1rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .history-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius);
            padding: 1.8rem;
            box-shadow: 0 10px 35px rgba(0, 0, 0, 0.2);
        }

        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1.2rem;
            border-bottom: 1px solid rgba(255, 215, 0, 0.15);
        }

        .history-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .history-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            max-height: 350px;
            overflow-y: auto;
            padding-right: 0.8rem;
        }

        .history-list::-webkit-scrollbar {
            width: 8px;
        }

        .history-list::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 4px;
        }

        .history-list::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }

        .history-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-radius: var(--border-radius);
            background: rgba(0, 0, 0, 0.3);
            transition: var(--transition);
            font-size: 0.9rem;
        }

        .history-item:hover {
            background: rgba(255, 215, 0, 0.08);
            transform: translateX(5px);
        }

        .history-icon {
            width: 40px;
            height: 40px;
            background: rgba(255, 215, 0, 0.12);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .history-details {
            flex: 1;
        }

        .history-description {
            font-weight: 500;
            margin-bottom: 0.3rem;
            color: var(--text-light);
        }

        .history-time {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .history-amount {
            font-weight: 700;
            color: var(--primary);
            font-size: 1rem;
            white-space: nowrap;
        }

        /* Animations */
        @keyframes idle-bounce {
            0%, 100% { transform: translateY(0) translateZ(50px); }
            50% { transform: translateY(-20px) translateZ(50px); }
        }

        @keyframes static-pulse {
            0%, 100% {
                box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3),
                            0 0 0 0 rgba(255, 215, 0, 0.6);
            }
            50% {
                box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3),
                            0 0 0 15px rgba(255, 215, 0, 0);
            }
        }

        @keyframes progress-shine {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        @keyframes mining-shake {
            0% { transform: rotate(0deg) translateZ(50px); }
            25% { transform: rotate(5deg) translateZ(50px); }
            50% { transform: rotate(0deg) translateZ(50px); }
            75% { transform: rotate(-5deg) translateZ(50px); }
            100% { transform: rotate(0deg) translateZ(50px); }
        }

        @keyframes lion-roar {
            0% { transform: scale(1) translateZ(50px); }
            20% { transform: scale(1.3) translateZ(50px); }
            40% { transform: scale(0.9) translateZ(50px); }
            60% { transform: scale(1.2) translateZ(50px); }
            80% { transform: scale(0.95) translateZ(50px); }
            100% { transform: scale(1) translateZ(50px); }
        }

        @keyframes coin-fly {
            0% {
                transform: translate(0, 0) scale(1);
                opacity: 1;
            }
            100% {
                transform: translate(var(--tx, 0), var(--ty, -100px)) scale(0.3);
                opacity: 0;
            }
        }

        .roar-text {
            position: absolute;
            top: -30px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--accent);
            text-shadow: 0 0 15px rgba(255, 107, 53, 0.8);
            opacity: 0;
            z-index: 10;
            animation: roar-fade 1.5s forwards;
        }

        @keyframes roar-fade {
            0% { opacity: 0; transform: translateX(-50%) scale(0.5); }
            30% { opacity: 1; transform: translateX(-50%) scale(1.2); }
            70% { opacity: 1; transform: translateX(-50%) scale(1); }
            100% { opacity: 0; transform: translateX(-50%) translateY(-50px) scale(0.8); }
        }

        /* Mobile responsiveness */
        @media (max-width: 992px) {
            .mining-hero h1 {
                font-size: 2.8rem;
            }

            .mining-character {
                width: 240px;
                height: 240px;
            }

            .mining-lion {
                font-size: 12rem;
            }
        }

        @media (max-width: 768px) {
            .mining-hero {
                padding: 80px 1rem 2rem;
            }

            .mining-hero h1 {
                font-size: 2.3rem;
            }

            .mining-hero p {
                font-size: 1.1rem;
            }

            .mining-character {
                width: 200px;
                height: 200px;
                margin: 1rem auto 1.8rem;
            }

            .mining-lion {
                font-size: 10rem;
            }

            .mining-progress-container {
                padding: 1.5rem;
            }

            .mining-controls {
                flex-direction: column;
                gap: 1rem;
                width: 100%;
                max-width: 400px;
            }

            .mining-btn {
                width: 100%;
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .progress-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .progress-time {
                width: 100%;
                justify-content: space-between;
            }
        }

        @media (max-width: 480px) {
            .mining-hero h1 {
                font-size: 2rem;
            }

            .mining-hero p {
                font-size: 1rem;
            }

            .mining-character {
                width: 180px;
                height: 180px;
            }

            .mining-lion {
                font-size: 8rem;
            }

            .section-title {
                font-size: 1.7rem;
            }
        }
    </style>
</head>
<body>
    <div class="mining-bg"></div>
    <!-- Top Navigation -->
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
                    <span class="balance-value" id="rawrBalance"><?= number_format((float)$userData['rawr_balance'], 2) ?></span>
                </div>
                <div class="balance-item">
                    <i class="fas fa-ticket-alt balance-icon"></i>
                    <span class="balance-label">Tickets:</span>
                    <span class="balance-value" id="ticketsBalance"><?= number_format((float)$userData['ticket_balance'], 2) ?></span>
                </div>
            </div>
            <button class="menu-toggle" id="menuToggle">
                <i class="fas fa-bars"></i>
                <i class="fas fa-xmark"></i>
            </button>
        </div>
    </nav>

    <!-- Updated Sidebar Navigation -->
    <aside class="sidebar" id="sidebar">
        <a href="dashboard.php" class="sidebar-item">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        <a href="mining.php" class="sidebar-item active">
            <i class="fas fa-digging"></i>
            <span>Mining</span>
        </a>
        <a href="games.php" class="sidebar-item">
            <i class="fas fa-dice"></i>
            <span>Lobby</span>
        </a>
        <a href="wallet.php" class="sidebar-item">
            <i class="fas fa-wallet"></i>
            <span>Wallet</span>
        </a>
        <a href="leaderboard.php" class="sidebar-item">
            <i class="fas fa-trophy"></i>
            <span>Leaderboard</span>
        </a>
        <a href="daily.php" class="sidebar-item">
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

    <!-- Mining Hero Section -->
    <section class="mining-hero">
        <div class="hero-content">
            <h1>Mine RAWR Tokens</h1>
            <p>Your lion is hard at work digging for treasures! Collect rewards every hour and maximize your earnings with upgrades.</p>

            <div class="mining-character">
                <div class="character-container">
                    <div class="mining-lion" id="miningLion">
                        <img src="../public/assets/logo.png" alt="Lion Logo" style="width: 100%; height: 100%; object-fit: contain; display: block;">
                    </div>
                </div>
            </div>

            <div class="mining-progress-container">
                <div class="progress-header">
                    <div class="progress-title"><i class="fas fa-hourglass-half"></i> Mining Progress</div>
                    <div class="progress-time"><i class="fas fa-clock"></i> Next reward in: <span id="timeLeft"><?= $remainingTime > 0 ? gmdate("i:s", $remainingTime) : '00:00' ?></span></div>
                </div>
                <div class="progress-bar-container">
                    <div class="progress-bar" id="miningProgress" style="width: <?= $remainingTime > 0 ? (100 - ($remainingTime / $currentCooldown * 100)) : 0 ?>%"></div>
                </div>
                <div class="progress-stats">
                    <span>
                        <div><i class="fas fa-tachometer-alt"></i> Current rate:</div>
                        <div class="stat-value" id="miningRate"><?= number_format($miningRatePerMinute * 60, 2) ?> RAWR/hr</div>
                    </span>
                    <span>
                        <div><i class="fas fa-gem"></i> Next reward:</div>
                        <div class="stat-value" id="nextReward"><?= number_format($nextReward, 2) ?> RAWR</div>
                    </span>
                </div>
            </div>

            <div class="mining-controls">
                <button class="mining-btn <?= $remainingTime > 0 ? 'pulse' : '' ?>" id="mineButton">
                    <i class="fas <?= $remainingTime > 0 ? 'fa-sync-alt fa-spin' : 'fa-play' ?>"></i>
                    <?= $remainingTime > 0 ? 'Mining...' : 'Start Mining' ?>
                </button>
                <button class="mining-btn secondary" id="claimReward" <?= $remainingTime > 0 ? 'disabled' : '' ?>>
                    <i class="fas fa-gem"></i>
                    Claim Reward (<span id="rewardAmount"><?= number_format($nextReward, 2) ?></span> RAWR)
                </button>
            </div>
        </div>
    </section>

    <!-- Mining Stats Section -->
    <section class="mining-stats">
        <h2 class="section-title">
            <i class="fas fa-chart-line"></i>
            Mining Statistics
        </h2>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="stat-title">Total Mined</div>
                <div class="stat-value" id="totalMined"><?= number_format($totalMined, 4) ?> RAWR</div>
                <div class="stat-subtext">Lifetime earnings</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-bolt"></i>
                </div>
                <div class="stat-title">Mining Rate</div>
                <div class="stat-value" id="currentRate"><?= number_format($miningRatePerMinute * 60, 2) ?> RAWR/hr</div>
                <div class="stat-subtext">With current equipment</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-rocket"></i>
                </div>
                <div class="stat-title">Active Boosts</div>
                <div class="stat-value" id="activeBoosts">x<?= number_format($shovelLevel, 1) ?></div>
                <div class="stat-subtext">Total multiplier</div>
            </div>
        </div>
    </section>

    <!-- Upgrade Section -->
    <section class="upgrade-section">
        <h2 class="section-title">
            <i class="fas fa-tools"></i>
            Mining Upgrades
        </h2>

        <div class="upgrade-grid">
            <div class="upgrade-card">
                <div class="upgrade-header">
                    <div class="upgrade-icon">
                        <i class="fas fa-hand-fist"></i>
                    </div>
                    <div>
                        <div class="upgrade-title">Stronger Shovel</div>
                        <div class="upgrade-level">Level <span id="shovelLevel"><?= $shovelLevel ?></span></div>
                    </div>
                </div>
                <div class="upgrade-description">
                    Increases your mining efficiency by 25% per level. Dig deeper and find more RAWR!
                </div>
                <div class="upgrade-cost">
                    <div class="cost-value">
                        <i class="fas fa-coins"></i>
                        <span id="shovelCost"><?= ceil(15 * pow(1.8, $shovelLevel - 1)) ?></span>
                    </div>
                    <button class="upgrade-btn" id="upgradeShovel">Upgrade</button>
                </div>
            </div>

            <div class="upgrade-card">
                <div class="upgrade-header">
                    <div class="upgrade-icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <div>
                        <div class="upgrade-title">Energy Boost</div>
                        <div class="upgrade-level">Level <span id="energyLevel"><?= $energyLevel ?></span></div>
                    </div>
                </div>
                <div class="upgrade-description">
                    Reduces mining time by 10% per level. Mine faster and collect rewards more frequently!
                </div>
                <div class="upgrade-cost">
                    <div class="cost-value">
                        <i class="fas fa-coins"></i>
                        <span id="energyCost"><?= ceil(25 * pow(1.8, $energyLevel - 1)) ?></span>
                    </div>
                    <button class="upgrade-btn" id="upgradeEnergy">Upgrade</button>
                </div>
            </div>

            <div class="upgrade-card">
                <div class="upgrade-header">
                    <div class="upgrade-icon">
                        <i class="fas fa-crown"></i>
                    </div>
                    <div>
                        <div class="upgrade-title">Royal Pickaxe</div>
                        <div class="upgrade-level">Level <span id="pickaxeLevel"><?= $pickaxeLevel ?></span></div>
                    </div>
                </div>
                <div class="upgrade-description">
                    Chance to find bonus RAWR with each mining session. Higher levels increase bonus chance.
                </div>
                <div class="upgrade-cost">
                    <div class="cost-value">
                        <i class="fas fa-coins"></i>
                        <span id="pickaxeCost"><?= ceil(50 * pow(1.8, $pickaxeLevel - 1)) ?></span>
                    </div>
                    <button class="upgrade-btn" id="upgradePickaxe">Upgrade</button>
                </div>
            </div>
        </div>
    </section>

    <!-- Mining History Section -->
    <section class="mining-history">
        <div class="history-card">
            <div class="history-header">
                <h2 class="history-title">
                    <i class="fas fa-history"></i>
                    Mining History
                </h2>
                <button class="mining-btn secondary" id="clearHistory">
                    <i class="fas fa-trash"></i>
                    Clear
                </button>
            </div>
            <div class="history-list" id="historyList">
                <?php 
                $history = $db->fetchAll("
                    SELECT amount, created_at
                    FROM mining_logs
                    WHERE user_id = ?
                    ORDER BY created_at DESC
                    LIMIT 10
                ", [$userId]);
                
                foreach ($history as $entry): 
                ?>
                <div class="history-item">
                    <div class="history-icon">⛏️</div>
                    <div class="history-details">
                        <div class="history-description">Mined RAWR Tokens</div>
                        <div class="history-time"><?= date('M j, H:i', strtotime($entry['created_at'])) ?></div>
                    </div>
                    <div class="history-amount">+<?= number_format((float)$entry['amount'], 4) ?> RAWR</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="footer-content">
            <div class="footer-column">
                <h3>RAWR Casino</h3>
                <p>The ultimate play-to-earn experience in the jungle. Play, win, and earn your way to the top!</p>
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
            &copy; 2023 RAWR Casino. All rights reserved. The jungle is yours to conquer!
        </div>
    </footer>

    <!-- Claim Not Ready Modal -->
    <div id="claimModal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100vw; height:100vh; background:rgba(20,20,20,0.65); align-items:center; justify-content:center;">
        <div style="background: var(--card-bg); border-radius: 18px; border: 2px solid var(--primary); box-shadow: 0 8px 32px rgba(0,0,0,0.35); padding: 2.5rem 2rem; max-width: 350px; width:90%; text-align:center; color: var(--text-light); position:relative;">
            <div style="font-size:2.5rem; margin-bottom:1rem; color:var(--primary);"><i class="fas fa-hourglass-half"></i></div>
            <div style="font-size:1.25rem; font-weight:600; margin-bottom:0.7rem;">Not Time to Claim</div>
            <div style="color:var(--text-muted); margin-bottom:1.5rem;">It's not time to claim your rewards yet.<br>Please wait for the mining session to finish.</div>
            <button id="closeClaimModal" style="background:linear-gradient(135deg, var(--primary), var(--secondary)); color:#1a1a1a; border:none; border-radius:30px; padding:0.7rem 2.2rem; font-weight:600; font-size:1.1rem; cursor:pointer; box-shadow:0 6px 20px rgba(0,0,0,0.2);">OK</button>
        </div>
    </div>

    <script>
        // DOM Elements
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const miningLion = document.getElementById('miningLion');
        const mineButton = document.getElementById('mineButton');
        const claimRewardBtn = document.getElementById('claimReward');
        const miningProgress = document.getElementById('miningProgress');
        const timeLeft = document.getElementById('timeLeft');
        const historyList = document.getElementById('historyList');
        const clearHistoryBtn = document.getElementById('clearHistory');
        const rawrBalance = document.getElementById('rawrBalance');
        const ticketsBalance = document.getElementById('ticketsBalance');
        const totalMinedElement = document.getElementById('totalMined');
        const miningRateElement = document.getElementById('miningRate');
        const currentRateElement = document.getElementById('currentRate');
        const activeBoostsElement = document.getElementById('activeBoosts');
        const nextRewardElement = document.getElementById('nextReward');
        const rewardAmountElement = document.getElementById('rewardAmount');
        const upgradeShovelBtn = document.getElementById('upgradeShovel');
        const upgradeEnergyBtn = document.getElementById('upgradeEnergy');
        const upgradePickaxeBtn = document.getElementById('upgradePickaxe');
        const shovelCostElement = document.getElementById('shovelCost');
        const energyCostElement = document.getElementById('energyCost');
        const pickaxeCostElement = document.getElementById('pickaxeCost');
        const shovelLevelElement = document.getElementById('shovelLevel');
        const energyLevelElement = document.getElementById('energyLevel');
        const pickaxeLevelElement = document.getElementById('pickaxeLevel');

        // Mining state
        let state = {
            rawrBalance: <?= (float)$userData['rawr_balance'] ?>,
            ticketsBalance: <?= (int)$userData['ticket_balance'] ?>,
            totalMined: <?= (float)$totalMined ?>,
            miningRate: <?= $miningRatePerMinute * 60 ?>,
            activeBoosts: <?= $shovelLevel ?>,
            shovelLevel: <?= $shovelLevel ?>,
            energyLevel: <?= $energyLevel ?>,
            pickaxeLevel: <?= $pickaxeLevel ?>,
            isMining: <?= $remainingTime > 0 ? 'true' : 'false' ?>,
            remainingTime: <?= (int)$remainingTime ?>,
            miningDuration: <?= (int)$currentCooldown ?>,
            nextReward: <?= $nextReward ?>
        };

        // Toggle sidebar
        menuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('active');
            menuToggle.classList.toggle('active');
        });

        // Close sidebar when clicking outside
        document.addEventListener('click', function(e) {
            if (sidebar.classList.contains('active') &&
                !sidebar.contains(e.target) &&
                !menuToggle.contains(e.target)
            ) {
                sidebar.classList.remove('active');
                menuToggle.classList.remove('active');
            }
        });

        // Format time for display
        function formatTime(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        }

        // Update UI elements with latest data
        function updateUI() {
            rawrBalance.textContent = state.rawrBalance.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
            ticketsBalance.textContent = state.ticketsBalance.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
            totalMinedElement.textContent = `${state.totalMined.toFixed(4)} RAWR`;
            miningRateElement.textContent = `${state.miningRate.toFixed(2)} RAWR/hr`;
            currentRateElement.textContent = `${state.miningRate.toFixed(2)} RAWR/hr`;
            nextRewardElement.textContent = `${state.nextReward.toFixed(2)} RAWR`;
            rewardAmountElement.textContent = state.nextReward.toFixed(2);
            activeBoostsElement.textContent = `x${state.shovelLevel.toFixed(1)}`;
            shovelLevelElement.textContent = state.shovelLevel;
            energyLevelElement.textContent = state.energyLevel;
            pickaxeLevelElement.textContent = state.pickaxeLevel;
            shovelCostElement.textContent = Math.ceil(15 * Math.pow(1.8, state.shovelLevel - 1));
            energyCostElement.textContent = Math.ceil(25 * Math.pow(1.8, state.energyLevel - 1));
            pickaxeCostElement.textContent = Math.ceil(50 * Math.pow(1.8, state.pickaxeLevel - 1));
            
            mineButton.innerHTML = state.isMining ? 
                `<i class="fas fa-sync-alt fa-spin"></i> Mining...` : 
                `<i class="fas fa-play"></i> Start Mining`;
                
            mineButton.classList.toggle('pulse', state.isMining);
            claimRewardBtn.disabled = state.isMining && state.remainingTime > 0;
            timeLeft.textContent = formatTime(state.remainingTime);
            
            const progress = state.miningDuration > 0 ? 
                100 - (state.remainingTime / state.miningDuration * 100) : 0;
            miningProgress.style.width = `${progress}%`;
        }

        // Handle mining action
        async function handleMine() {
            const response = await fetch('mining.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=mine'
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                state.rawrBalance = data.new_balance;
                state.totalMined += data.reward;
                state.remainingTime = data.new_cooldown;
                state.miningDuration = data.new_cooldown;
                state.isMining = true;
                
                if (data.reward > 0) {
                    createCoinAnimation(data.reward);
                    addHistoryEntry(data.reward);
                    
                    if (data.bonus > 0) {
                        displayMessage(`BONUS! +${data.bonus.toFixed(4)} RAWR`, 'success');
                    }
                }
                
                startMiningTimer();
                updateUI();
            } else {
                displayMessage(data.message, 'error');
            }
        }

        // Start mining timer
        function startMiningTimer() {
            clearInterval(miningTimer);
            
            if (state.remainingTime > 0) {
                miningTimer = setInterval(() => {
                    state.remainingTime--;
                    updateUI();
                    
                    if (state.remainingTime <= 0) {
                        clearInterval(miningTimer);
                        state.isMining = false;
                        displayMessage('Mining complete! Claim your reward.', 'success');
                        updateUI();
                    }
                }, 1000);
            }
        }

        // Create coin animation for reward claim
        function createCoinAnimation(reward) {
            const lionRect = miningLion.getBoundingClientRect();
            const balanceRect = document.querySelector('.balance-item').getBoundingClientRect();

            // Create coins based on reward amount
            const coinCount = Math.min(10, Math.floor(reward * 2));
            
            for (let i = 0; i < coinCount; i++) {
                const coin = document.createElement('div');
                coin.classList.add('coin-animation');
                coin.innerHTML = '<i class="fas fa-coins"></i>';
                coin.style.position = 'fixed';
                coin.style.color = '#FFD700';
                coin.style.fontSize = '1.5rem';
                coin.style.zIndex = '1000';
                coin.style.left = `${lionRect.left + lionRect.width / 2}px`;
                coin.style.top = `${lionRect.top + lionRect.height / 2}px`;
                
                document.body.appendChild(coin);

                // Random offset for end position
                const offsetX = (Math.random() - 0.5) * 40;
                const offsetY = (Math.random() - 0.5) * 40;
                
                // Animate coin
                const animation = coin.animate([
                    { transform: 'translate(0, 0) scale(1)', opacity: 1 },
                    { transform: `translate(${balanceRect.left - lionRect.left + offsetX}px, ${balanceRect.top - lionRect.top + offsetY}px) scale(0.3)`, opacity: 0 }
                ], { 
                    duration: 800 + Math.random() * 400,
                    easing: 'ease-out',
                    fill: 'forwards'
                });
                
                // Remove coin after animation
                animation.onfinish = () => coin.remove();
            }
        }

        // Add an entry to the history list
        function addHistoryEntry(amount) {
            const now = new Date();
            const timeString = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            const dateString = now.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });

            const historyItem = document.createElement('div');
            historyItem.classList.add('history-item');
            historyItem.innerHTML = `
                <div class="history-icon">⛏️</div>
                <div class="history-details">
                    <div class="history-description">Mined RAWR Tokens</div>
                    <div class="history-time">${dateString}, ${timeString}</div>
                </div>
                <div class="history-amount">+${amount.toFixed(4)} RAWR</div>
            `;
            historyList.insertBefore(historyItem, historyList.firstChild);
        }

        // Display message
        function displayMessage(message, type = 'info') {
            const messageElement = document.createElement('div');
            messageElement.classList.add('roar-text');
            messageElement.textContent = message;
            
            if (type === 'error') {
                messageElement.style.color = '#ff5555';
            } else if (type === 'success') {
                messageElement.style.color = '#00ff9d';
            }
            
            document.querySelector('.mining-character').appendChild(messageElement);

            setTimeout(() => {
                messageElement.remove();
            }, 2000);
        }

        // Handle equipment upgrade
        async function upgradeEquipment(type) {
            const response = await fetch('mining.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=upgrade_equipment&type=${type}`
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                state.rawrBalance = data.new_balance;
                state[`${type}Level`] = data.new_level;
                
                if (type === 'energy') {
                    state.miningDuration = data.new_cooldown;
                }
                
                updateUI();
                displayMessage('UPGRADED!', 'success');
            } else {
                displayMessage(data.message, 'error');
            }
        }

        // Event listeners
        mineButton.addEventListener('click', handleMine);
        claimRewardBtn.addEventListener('click', handleMine);
        upgradeShovelBtn.addEventListener('click', () => upgradeEquipment('shovel'));
        upgradeEnergyBtn.addEventListener('click', () => upgradeEquipment('energy'));
        upgradePickaxeBtn.addEventListener('click', () => upgradeEquipment('pickaxe'));
        miningLion.addEventListener('click', () => displayMessage('ROAR!', 'success'));
        clearHistoryBtn.addEventListener('click', () => {
            historyList.innerHTML = '';
            displayMessage('History cleared', 'info');
        });
        
        document.getElementById('closeClaimModal').addEventListener('click', () => {
            document.getElementById('claimModal').style.display = 'none';
        });

        // Initialize
        let miningTimer;
        updateUI();
        startMiningTimer();
    </script>
</body>
</html>