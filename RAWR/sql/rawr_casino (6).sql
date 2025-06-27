-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 23, 2025 at 06:18 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `rawr_casino`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `UpdateChallengeProgress` (IN `p_user_id` INT UNSIGNED, IN `p_challenge_id` TINYINT UNSIGNED, IN `p_progress_delta` DECIMAL(18,8), IN `p_set_progress` BOOLEAN)   BEGIN
    DECLARE v_current_progress DECIMAL(18,8) UNSIGNED;
    DECLARE v_target_value DECIMAL(18,8) UNSIGNED;
    DECLARE v_reward_type ENUM('tickets','rawr','item');
    DECLARE v_reward_value DECIMAL(18,8) UNSIGNED;
    DECLARE v_completed_at TIMESTAMP;
    DECLARE v_reward_claimed TINYINT(1);
    DECLARE v_item_id INT UNSIGNED;

    -- Get challenge details
    SELECT target_value, reward_type, reward_value 
    INTO v_target_value, v_reward_type, v_reward_value
    FROM challenge_types 
    WHERE id = p_challenge_id;

    -- Create progress record if missing
    INSERT INTO challenge_progress (user_id, challenge_id, progress)
    VALUES (p_user_id, p_challenge_id, 0)
    ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);

    -- Get current progress with lock
    SELECT progress, completed_at, reward_claimed 
    INTO v_current_progress, v_completed_at, v_reward_claimed
    FROM challenge_progress 
    WHERE user_id = p_user_id AND challenge_id = p_challenge_id
    FOR UPDATE;

    -- Update progress
    IF p_set_progress THEN
        SET v_current_progress = p_progress_delta;
    ELSE
        SET v_current_progress = v_current_progress + p_progress_delta;
    END IF;

    UPDATE challenge_progress 
    SET progress = v_current_progress
    WHERE user_id = p_user_id AND challenge_id = p_challenge_id;

    -- Check completion
    IF v_current_progress >= v_target_value 
       AND v_completed_at IS NULL 
       AND v_reward_claimed = 0 THEN
        
        UPDATE challenge_progress
        SET completed_at = CURRENT_TIMESTAMP,
            reward_claimed = 1
        WHERE user_id = p_user_id AND challenge_id = p_challenge_id;

        -- Award reward
        CASE v_reward_type
            WHEN 'tickets' THEN
                UPDATE users 
                SET ticket_balance = ticket_balance + v_reward_value 
                WHERE id = p_user_id;
                
                INSERT INTO reward_logs (user_id, type, ticket_amount)
                VALUES (p_user_id, 'challenge', v_reward_value);
                
            WHEN 'rawr' THEN
                UPDATE users 
                SET rawr_balance = rawr_balance + v_reward_value 
                WHERE id = p_user_id;
                
                INSERT INTO reward_logs (user_id, type, rawr_amount)
                VALUES (p_user_id, 'challenge', v_reward_value);
        END CASE;
    END IF;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `admin_audit_log`
--

CREATE TABLE `admin_audit_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `admin_id` int(10) UNSIGNED NOT NULL,
  `action` varchar(50) NOT NULL,
  `target_type` enum('user','item','kyc','game','purchase') NOT NULL,
  `target_id` int(10) UNSIGNED DEFAULT NULL,
  `details` text DEFAULT NULL,
  `performed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_sessions`
--

CREATE TABLE `admin_sessions` (
  `session_id` varchar(128) NOT NULL,
  `admin_id` int(10) UNSIGNED NOT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('superadmin','admin','moderator') NOT NULL DEFAULT 'admin',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `password_hash`, `full_name`, `role`, `last_login`, `created_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'superadmin', '2025-06-23 09:14:39', '2025-06-12 14:47:38');

-- --------------------------------------------------------

--
-- Table structure for table `casino_spending`
--

CREATE TABLE `casino_spending` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `game_type_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `tickets_spent` int(10) UNSIGNED NOT NULL,
  `spent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `casino_spending`
--

INSERT INTO `casino_spending` (`id`, `user_id`, `game_type_id`, `tickets_spent`, `spent_at`) VALUES
(2, 4, 4, 1, '2025-06-23 12:44:32'),
(3, 4, 4, 1, '2025-06-23 12:44:50'),
(4, 4, 4, 1, '2025-06-23 12:54:11'),
(5, 4, 4, 1, '2025-06-23 12:54:18'),
(6, 4, 4, 1, '2025-06-23 12:54:35'),
(7, 4, 4, 1, '2025-06-23 12:54:46'),
(8, 4, 4, 1, '2025-06-23 12:54:54'),
(9, 4, 4, 1, '2025-06-23 12:55:12'),
(10, 4, 4, 1, '2025-06-23 13:01:18'),
(11, 4, 4, 1, '2025-06-23 13:01:42'),
(12, 4, 4, 10, '2025-06-23 13:02:11'),
(13, 4, 4, 1, '2025-06-23 13:02:23'),
(14, 4, 4, 25, '2025-06-23 13:02:34'),
(15, 4, 4, 20, '2025-06-23 13:02:51'),
(16, 4, 4, 20, '2025-06-23 13:02:58'),
(17, 4, 4, 1, '2025-06-23 13:04:48'),
(18, 4, 4, 1, '2025-06-23 13:04:58'),
(19, 4, 4, 1, '2025-06-23 13:05:16'),
(20, 4, 4, 1, '2025-06-23 13:05:26'),
(21, 4, 4, 1, '2025-06-23 13:05:32'),
(22, 4, 4, 1, '2025-06-23 13:05:39'),
(23, 4, 4, 1, '2025-06-23 13:05:52'),
(24, 4, 4, 1, '2025-06-23 13:06:01'),
(25, 4, 4, 1, '2025-06-23 13:06:09'),
(26, 4, 4, 1, '2025-06-23 13:06:17'),
(27, 4, 2, 10, '2025-06-23 14:21:48'),
(28, 4, 2, 10, '2025-06-23 14:21:58'),
(29, 4, 2, 10, '2025-06-23 14:22:11'),
(30, 4, 2, 1, '2025-06-23 14:32:54'),
(31, 4, 2, 1, '2025-06-23 14:33:02'),
(32, 4, 2, 1, '2025-06-23 14:33:10'),
(33, 4, 2, 1, '2025-06-23 14:33:20'),
(34, 4, 2, 1, '2025-06-23 14:33:33'),
(35, 4, 2, 1, '2025-06-23 14:35:10'),
(36, 4, 2, 1, '2025-06-23 14:35:19'),
(37, 4, 2, 1, '2025-06-23 14:35:39'),
(38, 4, 2, 1, '2025-06-23 14:35:48'),
(39, 4, 2, 1, '2025-06-23 14:35:57'),
(40, 4, 2, 10, '2025-06-23 14:36:28'),
(41, 4, 2, 10, '2025-06-23 15:30:09');

-- --------------------------------------------------------

--
-- Table structure for table `challenge_progress`
--

CREATE TABLE `challenge_progress` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `challenge_id` tinyint(3) UNSIGNED NOT NULL,
  `progress` decimal(18,8) UNSIGNED NOT NULL DEFAULT 0.00000000,
  `completed_at` timestamp NULL DEFAULT NULL,
  `reward_claimed` tinyint(1) NOT NULL DEFAULT 0,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `challenge_progress`
--

INSERT INTO `challenge_progress` (`id`, `user_id`, `challenge_id`, `progress`, `completed_at`, `reward_claimed`, `assigned_at`) VALUES
(1, 4, 3, 40.00000000, '2025-06-23 13:01:42', 1, '2025-06-23 12:44:32'),
(2, 4, 4, 156.00000000, NULL, 0, '2025-06-23 12:44:32'),
(79, 4, 1, 5.00000000, '2025-06-23 14:39:35', 1, '2025-06-23 14:39:35'),
(80, 4, 11, 5.00000000, NULL, 0, '2025-06-23 14:39:35');

-- --------------------------------------------------------

--
-- Table structure for table `challenge_types`
--

CREATE TABLE `challenge_types` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) NOT NULL,
  `reward_type` enum('tickets','rawr','item') NOT NULL,
  `reward_value` decimal(18,8) UNSIGNED NOT NULL,
  `target_value` decimal(18,8) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `challenge_types`
--

INSERT INTO `challenge_types` (`id`, `name`, `description`, `reward_type`, `reward_value`, `target_value`) VALUES
(1, 'Daily Login', 'Log in 3 days in a row', 'tickets', 100.00000000, 3.00000000),
(2, 'Mining Master', 'Mine 500 RAWR tokens', 'tickets', 500.00000000, 500.00000000),
(3, 'Game Enthusiast', 'Play 10 games', 'tickets', 150.00000000, 10.00000000),
(4, 'Big Spender', 'Spend 1000 tickets in the casino', 'tickets', 100.00000000, 1000.00000000),
(5, 'Daily Mining', 'Mine at least 10 RAWR tokens today', 'rawr', 25.00000000, 10.00000000),
(6, 'Casino Enthusiast', 'Play 3 casino games today', 'tickets', 15.00000000, 3.00000000),
(7, 'Referral Master', 'Refer 10 new players', 'rawr', 50.00000000, 10.00000000),
(8, 'RAWR Millionaire', 'Accumulate 1,000,000 RAWR tokens', 'rawr', 1000.00000000, 1000000.00000000),
(9, 'Casino Royal', 'Win 100 casino games', 'tickets', 500.00000000, 100.00000000),
(10, 'Jungle King', 'Become the #1 player on the leaderboard', 'rawr', 200.00000000, 1.00000000),
(11, 'Loyal Lion', 'Maintain a 30-day login streak', 'tickets', 100.00000000, 30.00000000);

-- --------------------------------------------------------

--
-- Table structure for table `conversion_logs`
--

CREATE TABLE `conversion_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `rawr_amount` decimal(18,8) DEFAULT NULL,
  `tickets_received` decimal(18,8) DEFAULT NULL,
  `tickets_spent` decimal(18,8) DEFAULT NULL,
  `rawr_received` decimal(18,8) DEFAULT NULL,
  `converted_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `game_results`
--

CREATE TABLE `game_results` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `game_type_id` tinyint(3) UNSIGNED NOT NULL,
  `bet_amount` int(10) UNSIGNED NOT NULL,
  `payout` int(10) UNSIGNED NOT NULL,
  `outcome` enum('win','loss') NOT NULL,
  `played_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `game_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`game_details`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `game_results`
--

INSERT INTO `game_results` (`id`, `user_id`, `game_type_id`, `bet_amount`, `payout`, `outcome`, `played_at`, `game_details`) VALUES
(2, 4, 4, 1, 0, 'loss', '2025-06-23 12:44:32', '{\"won\":\"LOSE\"}'),
(3, 4, 4, 1, 0, 'loss', '2025-06-23 12:44:50', '{\"won\":\"LOSE\"}'),
(4, 4, 4, 1, 0, 'loss', '2025-06-23 12:54:11', '{\"won\":\"LOSE\"}'),
(5, 4, 4, 1, 0, 'loss', '2025-06-23 12:54:18', '{\"won\":\"LOSE\"}'),
(6, 4, 4, 1, 5, 'win', '2025-06-23 12:54:35', '{\"won\":\"5X\"}'),
(7, 4, 4, 1, 0, 'loss', '2025-06-23 12:54:46', '{\"won\":\"LOSE\"}'),
(8, 4, 4, 1, 0, 'loss', '2025-06-23 12:54:54', '{\"won\":\"LOSE\"}'),
(9, 4, 4, 1, 2, 'win', '2025-06-23 12:55:12', '{\"won\":\"2X\"}'),
(10, 4, 4, 1, 5, 'win', '2025-06-23 13:01:18', '{\"won\":\"5X\"}'),
(11, 4, 4, 1, 0, 'loss', '2025-06-23 13:01:42', '{\"won\":\"LOSE\"}'),
(12, 4, 4, 10, 0, 'loss', '2025-06-23 13:02:11', '{\"won\":\"LOSE\"}'),
(13, 4, 4, 1, 0, 'loss', '2025-06-23 13:02:23', '{\"won\":\"LOSE\"}'),
(14, 4, 4, 25, 0, 'loss', '2025-06-23 13:02:34', '{\"won\":\"+1 Spin\"}'),
(15, 4, 4, 20, 0, 'loss', '2025-06-23 13:02:51', '{\"won\":\"+1 Spin\"}'),
(16, 4, 4, 20, 0, 'loss', '2025-06-23 13:02:58', '{\"won\":\"LOSE\"}'),
(17, 4, 4, 1, 0, 'loss', '2025-06-23 13:04:48', '{\"won\":\"LOSE\"}'),
(18, 4, 4, 1, 0, 'loss', '2025-06-23 13:04:58', '{\"won\":\"LOSE\"}'),
(19, 4, 4, 1, 0, 'loss', '2025-06-23 13:05:16', '{\"won\":\"LOSE\"}'),
(20, 4, 4, 1, 0, 'loss', '2025-06-23 13:05:26', '{\"won\":\"+1 Spin\"}'),
(21, 4, 4, 1, 10, 'win', '2025-06-23 13:05:32', '{\"won\":\"10X\"}'),
(22, 4, 4, 1, 2, 'win', '2025-06-23 13:05:39', '{\"won\":\"2X\"}'),
(23, 4, 4, 1, 0, 'loss', '2025-06-23 13:05:52', '{\"won\":\"+3 Spins\"}'),
(24, 4, 4, 1, 0, 'loss', '2025-06-23 13:06:01', '{\"won\":\"LOSE\"}'),
(25, 4, 4, 1, 0, 'loss', '2025-06-23 13:06:09', '{\"won\":\"LOSE\"}'),
(26, 4, 4, 1, 0, 'loss', '2025-06-23 13:06:17', '{\"won\":\"LOSE\"}'),
(27, 4, 2, 10, 0, 'loss', '2025-06-23 14:21:48', '{\"outcome\":\"LOSE\",\"bet\":10,\"free_spin_used\":false,\"multiplier\":null,\"spins_won\":null}'),
(28, 4, 2, 10, 0, 'loss', '2025-06-23 14:21:58', '{\"outcome\":\"LOSE\",\"bet\":10,\"free_spin_used\":false,\"multiplier\":null,\"spins_won\":null}'),
(29, 4, 2, 10, 0, 'win', '2025-06-23 14:22:11', '{\"outcome\":\"+1 Spin\",\"bet\":10,\"free_spin_used\":false,\"multiplier\":null,\"spins_won\":1}'),
(30, 4, 2, 1, 0, 'loss', '2025-06-23 14:32:54', '{\"outcome\":\"LOSE\",\"bet\":1,\"free_spin_used\":false,\"multiplier\":null,\"spins_won\":null}'),
(31, 4, 2, 1, 0, 'win', '2025-06-23 14:33:02', '{\"outcome\":\"+1 Spin\",\"bet\":1,\"free_spin_used\":false,\"multiplier\":null,\"spins_won\":1}'),
(32, 4, 2, 1, 0, 'win', '2025-06-23 14:33:10', '{\"outcome\":\"+1 Spin\",\"bet\":1,\"free_spin_used\":true,\"multiplier\":null,\"spins_won\":1}'),
(33, 4, 2, 1, 3, 'win', '2025-06-23 14:33:20', '{\"outcome\":\"3X\",\"bet\":1,\"free_spin_used\":true,\"multiplier\":3,\"spins_won\":null}'),
(34, 4, 2, 1, 0, 'win', '2025-06-23 14:33:33', '{\"outcome\":\"+2 Spins\",\"bet\":1,\"free_spin_used\":false,\"multiplier\":null,\"spins_won\":2}'),
(35, 4, 2, 1, 0, 'loss', '2025-06-23 14:35:10', '{\"outcome\":\"LOSE\",\"bet\":1,\"free_spin_used\":false,\"multiplier\":null,\"spins_won\":null}'),
(36, 4, 2, 1, 0, 'loss', '2025-06-23 14:35:19', '{\"outcome\":\"LOSE\",\"bet\":1,\"free_spin_used\":false,\"multiplier\":null,\"spins_won\":null}'),
(37, 4, 2, 1, 0, 'loss', '2025-06-23 14:35:39', '{\"outcome\":\"LOSE\",\"bet\":1,\"free_spin_used\":false,\"multiplier\":null,\"spins_won\":null}'),
(38, 4, 2, 1, 0, 'loss', '2025-06-23 14:35:48', '{\"outcome\":\"LOSE\",\"bet\":1,\"free_spin_used\":false,\"multiplier\":null,\"spins_won\":null}'),
(39, 4, 2, 1, 0, 'loss', '2025-06-23 14:35:57', '{\"outcome\":\"LOSE\",\"bet\":1,\"free_spin_used\":false,\"multiplier\":null,\"spins_won\":null}'),
(40, 4, 2, 10, 0, 'win', '2025-06-23 14:36:28', '{\"outcome\":\"+2 Spins\",\"bet\":10,\"free_spin_used\":false,\"multiplier\":null,\"spins_won\":2}'),
(41, 4, 2, 10, 0, 'loss', '2025-06-23 15:30:09', '{\"outcome\":\"LOSE\",\"bet\":10,\"free_spin_used\":false,\"multiplier\":null,\"spins_won\":null}');

--
-- Triggers `game_results`
--
DELIMITER $$
CREATE TRIGGER `after_game_result_insert` AFTER INSERT ON `game_results` FOR EACH ROW BEGIN
  -- Record casino spending
  INSERT INTO casino_spending (user_id, game_type_id, tickets_spent)
  VALUES (NEW.user_id, NEW.game_type_id, NEW.bet_amount);
  
  -- Update Game Enthusiast challenge (count games)
  CALL UpdateChallengeProgress(NEW.user_id, 3, 1, FALSE);
  
  -- Update Big Spender challenge (casino spending)
  CALL UpdateChallengeProgress(NEW.user_id, 4, NEW.bet_amount, FALSE);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `game_types`
--

CREATE TABLE `game_types` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `slug` varchar(20) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `status` enum('active','coming_soon','disabled') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `game_types`
--

INSERT INTO `game_types` (`id`, `name`, `slug`, `description`, `status`) VALUES
(1, 'Jungle Slots', 'jungle-slots', 'Spin the reels filled with jungle animals. Match 3 lions for the jackpot!', 'active'),
(2, 'Safari Roulette', 'safari-roulette', 'Spin the Safari wheel for multipliers up to 20x or free spins', 'active'),
(3, 'Finding Simba', 'finding-simba', 'Find Simba among the cards and win 3x your bet!', 'active'),
(4, 'Dice of Beast', 'dice-of-beast', 'Choose your beast and roll the dice for up to 5x multiplier', 'active'),
(5, 'Lion\'s Prowl', 'lions-prowl', 'Uncover tiles for multipliers or RAWR prizes while avoiding lions', 'active'),
(6, 'Tiger\'s Roar', 'tigers-roar', 'High-stakes jungle poker game against predators for massive RAWR pots', 'coming_soon'),
(7, 'Monkey Mayhem', 'monkey-mayhem', 'Swing through jungle canopy collecting bananas for RAWR tokens', 'coming_soon');

-- --------------------------------------------------------

--
-- Table structure for table `kyc_requests`
--

CREATE TABLE `kyc_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `country` varchar(2) DEFAULT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state_province` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `id_image_path` varchar(255) NOT NULL,
  `id_type` varchar(20) DEFAULT NULL,
  `id_number` varchar(50) DEFAULT NULL,
  `id_image_back_path` varchar(255) DEFAULT NULL,
  `selfie_image_path` varchar(255) DEFAULT NULL,
  `status` enum('not_verified','pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `kyc_requests`
--

INSERT INTO `kyc_requests` (`id`, `user_id`, `full_name`, `date_of_birth`, `country`, `contact_number`, `address`, `city`, `state_province`, `postal_code`, `id_image_path`, `id_type`, `id_number`, `id_image_back_path`, `selfie_image_path`, `status`, `submitted_at`, `reviewed_by`, `reviewed_at`, `rejection_reason`) VALUES
(1, 4, 'Jonel Andamon', '2003-08-14', 'ph', '09948108334', 'Daang Amaya 2', 'Tanza', 'Cavite', '4108', '', NULL, NULL, NULL, NULL, 'pending', '2025-06-22 05:05:15', NULL, NULL, NULL),
(2, 5, 'Eric DIaz', '2003-04-06', 'ph', '09206742821', 'Ligtong', 'Rosario', 'Cavite', '4106', '', NULL, NULL, NULL, NULL, 'pending', '2025-06-23 10:00:38', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `ip_address` varchar(45) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 1,
  `last_attempt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_streaks`
--

CREATE TABLE `login_streaks` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `current_streak` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `longest_streak` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `last_login_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_streaks`
--

INSERT INTO `login_streaks` (`user_id`, `current_streak`, `longest_streak`, `last_login_date`) VALUES
(1, 2, 5, '2025-06-11'),
(2, 0, 0, '2025-06-13'),
(3, 0, 0, '2025-06-13'),
(4, 5, 5, '2025-06-23'),
(5, 1, 1, '2025-06-19'),
(6, 0, 0, '2025-06-20'),
(7, 0, 0, '2025-06-23'),
(8, 0, 0, '2025-06-23');

--
-- Triggers `login_streaks`
--
DELIMITER $$
CREATE TRIGGER `after_login_streak_update` AFTER UPDATE ON `login_streaks` FOR EACH ROW BEGIN
    CALL UpdateChallengeProgress(
        NEW.user_id, 
        1, 
        NEW.current_streak, 
        TRUE
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `mining_data`
--

CREATE TABLE `mining_data` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `last_mined_at` timestamp NULL DEFAULT NULL,
  `boost_level` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `total_mined` decimal(18,8) NOT NULL DEFAULT 0.00000000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `mining_data`
--

INSERT INTO `mining_data` (`user_id`, `last_mined_at`, `boost_level`, `total_mined`) VALUES
(1, NULL, 1, 0.00000000),
(2, NULL, 1, 0.00000000),
(3, NULL, 1, 0.00000000),
(4, NULL, 1, 0.00000000),
(5, NULL, 1, 0.00000000),
(6, NULL, 1, 0.00000000),
(7, NULL, 1, 0.00000000),
(8, NULL, 1, 0.00000000);

-- --------------------------------------------------------

--
-- Table structure for table `mining_logs`
--

CREATE TABLE `mining_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(18,8) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `mining_logs`
--
DELIMITER $$
CREATE TRIGGER `after_mining_log_insert` AFTER INSERT ON `mining_logs` FOR EACH ROW BEGIN
    CALL UpdateChallengeProgress(
        NEW.user_id, 
        2, 
        NEW.amount, 
        FALSE
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `mining_sessions`
--

CREATE TABLE `mining_sessions` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `start_time` int(11) NOT NULL,
  `duration` int(11) NOT NULL,
  `claimed` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mining_upgrades`
--

CREATE TABLE `mining_upgrades` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `shovel_level` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `energy_level` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `pickaxe_level` tinyint(3) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mining_upgrades`
--

INSERT INTO `mining_upgrades` (`user_id`, `shovel_level`, `energy_level`, `pickaxe_level`) VALUES
(4, 1, 1, 1),
(5, 1, 1, 1),
(6, 1, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `referrals`
--

CREATE TABLE `referrals` (
  `id` int(11) NOT NULL,
  `referrer_id` int(10) UNSIGNED NOT NULL,
  `referred_id` int(10) UNSIGNED NOT NULL,
  `referred_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `kyc_approved_at` timestamp NULL DEFAULT NULL,
  `bonus_awarded` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `referrals`
--

INSERT INTO `referrals` (`id`, `referrer_id`, `referred_id`, `referred_at`, `kyc_approved_at`, `bonus_awarded`) VALUES
(1, 4, 5, '2025-06-16 21:08:03', NULL, 1),
(2, 4, 7, '2025-06-23 02:05:20', NULL, 0),
(3, 5, 8, '2025-06-23 08:01:13', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `reward_logs`
--

CREATE TABLE `reward_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `type` enum('daily','challenge','referral') NOT NULL,
  `rawr_amount` decimal(18,8) NOT NULL DEFAULT 0.00000000,
  `ticket_amount` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reward_logs`
--

INSERT INTO `reward_logs` (`id`, `user_id`, `type`, `rawr_amount`, `ticket_amount`, `created_at`) VALUES
(1, 4, 'challenge', 0.00000000, 150, '2025-06-23 13:01:42'),
(2, 4, 'challenge', 0.00000000, 100, '2025-06-23 14:39:35'),
(3, 4, 'daily', 30.00000000, 5, '2025-06-23 14:39:35');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `type` enum('deposit','withdrawal') NOT NULL,
  `amount` decimal(18,8) NOT NULL,
  `status` enum('pending','completed','failed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `user_id`, `type`, `amount`, `status`, `created_at`, `updated_at`) VALUES
(1, 4, 'withdrawal', 100.00000000, 'completed', '2025-06-22 10:22:18', NULL),
(2, 4, 'deposit', 100.00000000, 'completed', '2025-06-22 10:22:35', NULL),
(3, 4, 'deposit', 100.00000000, 'completed', '2025-06-22 11:56:10', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `rawr_balance` decimal(18,8) NOT NULL DEFAULT 0.00000000,
  `ticket_balance` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `referral_code` varchar(10) NOT NULL,
  `wallet_address` varchar(100) DEFAULT NULL,
  `referred_by` int(10) UNSIGNED DEFAULT NULL,
  `kyc_status` enum('not_verified','pending','approved','rejected') NOT NULL DEFAULT 'not_verified',
  `is_banned` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `avatar_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `rawr_balance`, `ticket_balance`, `referral_code`, `wallet_address`, `referred_by`, `kyc_status`, `is_banned`, `created_at`, `last_login`, `bio`, `avatar_id`) VALUES
(1, 'testuser', 'user@example.com', '$2y$10$TxZ5w1d3c0b1c2d3e4f5g6h7i8j9k0l1m2n3o4p5q6r7s8t9u0v1w2x3y4z', 125.75000000, 500, 'REF123456', NULL, NULL, 'approved', 0, '2025-06-12 14:47:38', NULL, NULL, NULL),
(2, 'nelll', 'jonelandamon06@gmail.com', '$2y$10$e3OhhvMRd6byp5E2yFmQmOfPl5setHJsxAbyronryxuGuujewn7Oi', 0.00000000, 0, 'MB4U7T2N', NULL, NULL, 'pending', 0, '2025-06-12 23:40:43', '2025-06-13 01:56:00', NULL, NULL),
(3, 'danzle', 'd@gmail.com', '$2y$10$49VDemodOY5A5aKWzh6Uu.IHW/Tm0lli.oFgupHcLV16J5vEON1TW', 0.00000000, 0, '2YNNAZ9L', NULL, NULL, 'pending', 0, '2025-06-13 03:09:44', '2025-06-13 07:12:13', NULL, NULL),
(4, 'nell', 'ja@gmail.com', '$2y$10$uwhcnGe78lUr8i70D7LiVO2sWyem8DTxgm.ILP.ryEdzoYQvfe79m', 385.00000000, 139, 'QJU9ZRT4', '0xbda5747bfd65f08deb54cb465eb87d40e51b197e', NULL, 'pending', 0, '2025-06-13 07:20:08', '2025-06-23 07:29:48', NULL, 1),
(5, 'eric', 'diaz@gmail.com', '$2y$10$o6HZ3AXJo0RhqSO1Nk1WGuOkkrgMHcNfGVuIHNodxQDV6VS.gTt0q', 205.00000000, 1, 'T825BBXB', NULL, 4, 'pending', 0, '2025-06-16 21:08:02', '2025-06-23 01:59:09', NULL, NULL),
(6, 'shine', 's@gmail.com', '$2y$10$cO5FgrT2CP8y6MPSpbowsOeCiKRDyYUCOVZGjYrHcQU5OWHpeASWC', 0.00000000, 0, '5YNHUL3Q', NULL, NULL, 'pending', 0, '2025-06-20 06:19:32', '2025-06-20 07:10:20', NULL, NULL),
(7, 'Harold', 'h@gmail.com', '$2y$10$AxlRsaFc37vFH2RzjeZIje6AhuFvPmEyTQdDXP88V3cWUGV1t3aLa', 0.00000000, 0, 'C6Y7NTCL', NULL, 4, 'not_verified', 0, '2025-06-23 02:05:20', NULL, NULL, NULL),
(8, 'rold', 'hr@gmail.com', '$2y$10$aXcunNSIaJsWCM.kA4QR6O3vSm361W7Qj5IRwW.ToSuEqv41auJ92', 50.00000000, 0, '47WCVGZ2', NULL, 5, '', 0, '2025-06-23 08:01:13', '2025-06-23 08:12:46', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_avatars`
--

CREATE TABLE `user_avatars` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `mime_type` varchar(50) NOT NULL,
  `file_size` int(10) UNSIGNED NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_avatars`
--

INSERT INTO `user_avatars` (`id`, `user_id`, `file_name`, `file_path`, `mime_type`, `file_size`, `uploaded_at`) VALUES
(1, 4, 'avatar_685800032c74e_1750597635.png', '/RAWR/public/uploads/avatars/avatar_685800032c74e_1750597635.png', 'image/png', 1115175, '2025-06-22 13:07:15');

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `session_id` varchar(128) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_audit_log`
--
ALTER TABLE `admin_audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `admin_sessions`
--
ALTER TABLE `admin_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `casino_spending`
--
ALTER TABLE `casino_spending`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `challenge_progress`
--
ALTER TABLE `challenge_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_challenge` (`user_id`,`challenge_id`),
  ADD KEY `challenge_id` (`challenge_id`);

--
-- Indexes for table `challenge_types`
--
ALTER TABLE `challenge_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `conversion_logs`
--
ALTER TABLE `conversion_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `game_results`
--
ALTER TABLE `game_results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `game_type_id` (`game_type_id`),
  ADD KEY `played_at` (`played_at`);

--
-- Indexes for table `game_types`
--
ALTER TABLE `game_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `kyc_requests`
--
ALTER TABLE `kyc_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`ip_address`);

--
-- Indexes for table `login_streaks`
--
ALTER TABLE `login_streaks`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `mining_data`
--
ALTER TABLE `mining_data`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `mining_logs`
--
ALTER TABLE `mining_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `mining_sessions`
--
ALTER TABLE `mining_sessions`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `mining_upgrades`
--
ALTER TABLE `mining_upgrades`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `referrals`
--
ALTER TABLE `referrals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `referred_id` (`referred_id`),
  ADD UNIQUE KEY `uq_referral_pair` (`referrer_id`,`referred_id`),
  ADD KEY `referrer_id` (`referrer_id`);

--
-- Indexes for table `reward_logs`
--
ALTER TABLE `reward_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `referral_code` (`referral_code`),
  ADD KEY `referred_by` (`referred_by`),
  ADD KEY `fk_user_avatar` (`avatar_id`);

--
-- Indexes for table `user_avatars`
--
ALTER TABLE `user_avatars`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_audit_log`
--
ALTER TABLE `admin_audit_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `casino_spending`
--
ALTER TABLE `casino_spending`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `challenge_progress`
--
ALTER TABLE `challenge_progress`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=83;

--
-- AUTO_INCREMENT for table `challenge_types`
--
ALTER TABLE `challenge_types`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `conversion_logs`
--
ALTER TABLE `conversion_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `game_results`
--
ALTER TABLE `game_results`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `game_types`
--
ALTER TABLE `game_types`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `kyc_requests`
--
ALTER TABLE `kyc_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `mining_logs`
--
ALTER TABLE `mining_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `referrals`
--
ALTER TABLE `referrals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `reward_logs`
--
ALTER TABLE `reward_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `user_avatars`
--
ALTER TABLE `user_avatars`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_audit_log`
--
ALTER TABLE `admin_audit_log`
  ADD CONSTRAINT `fk_audit_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `admin_sessions`
--
ALTER TABLE `admin_sessions`
  ADD CONSTRAINT `fk_session_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `casino_spending`
--
ALTER TABLE `casino_spending`
  ADD CONSTRAINT `casino_spending_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `challenge_progress`
--
ALTER TABLE `challenge_progress`
  ADD CONSTRAINT `fk_challenge_type` FOREIGN KEY (`challenge_id`) REFERENCES `challenge_types` (`id`),
  ADD CONSTRAINT `fk_progress_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `game_results`
--
ALTER TABLE `game_results`
  ADD CONSTRAINT `fk_game_type` FOREIGN KEY (`game_type_id`) REFERENCES `game_types` (`id`),
  ADD CONSTRAINT `fk_game_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `kyc_requests`
--
ALTER TABLE `kyc_requests`
  ADD CONSTRAINT `fk_kyc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `login_streaks`
--
ALTER TABLE `login_streaks`
  ADD CONSTRAINT `fk_streak_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mining_data`
--
ALTER TABLE `mining_data`
  ADD CONSTRAINT `fk_mining_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mining_logs`
--
ALTER TABLE `mining_logs`
  ADD CONSTRAINT `fk_mininglog_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mining_sessions`
--
ALTER TABLE `mining_sessions`
  ADD CONSTRAINT `mining_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `referrals`
--
ALTER TABLE `referrals`
  ADD CONSTRAINT `fk_referred` FOREIGN KEY (`referred_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_referrer` FOREIGN KEY (`referrer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reward_logs`
--
ALTER TABLE `reward_logs`
  ADD CONSTRAINT `reward_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_user_avatar` FOREIGN KEY (`avatar_id`) REFERENCES `user_avatars` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_avatars`
--
ALTER TABLE `user_avatars`
  ADD CONSTRAINT `fk_avatar_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `fk_session_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
