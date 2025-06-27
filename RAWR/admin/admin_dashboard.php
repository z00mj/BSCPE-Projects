<?php
require_once __DIR__ . '/../backend/inc/init.php';
error_log('ADMIN DASHBOARD SESSION: ' . print_r($_SESSION, true));
// Only allow access if role is 'superadmin' and admin_id is set
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin' || !isset($_SESSION['admin_id'])) {
    header("Location: /RAWR/public/login.php");
    exit;
}
$database = Database::getInstance();
$pdo = $database->getPdo();

// Fetch admin data
$admin_data = null;
$admin_id = $_SESSION["admin_id"];

try {
    $sql = "SELECT * FROM admin_users WHERE id = :admin_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':admin_id', $admin_id, PDO::PARAM_INT);
    $stmt->execute();
    $admin_data = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Database error fetching admin data: " . $e->getMessage());
}

// Prepare variables for display
$username = $admin_data['username'] ?? 'Admin';
$profile_image_url = 'https://avatar.iran.liara.run/public/boy?username=' . urlencode($username);

// Fetch dashboard stats
$stats = [
    'total_users' => 0,
    'active_users' => 0,
    'banned_users' => 0,
    'total_kyc' => 0,
    'pending_kyc' => 0,
    'total_games' => 0,
    'total_rawr' => 0,
    'total_tickets' => 0,
    'recent_users' => []
];

// Total users
$stmt = $pdo->query("SELECT COUNT(*) FROM users");
$stats['total_users'] = $stmt->fetchColumn();

// Active users
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_banned = 0");
$stats['active_users'] = $stmt->fetchColumn();

// Banned users
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_banned = 1");
$stats['banned_users'] = $stmt->fetchColumn();

// Total KYC requests
$stmt = $pdo->query("SELECT COUNT(*) FROM kyc_requests");
$stats['total_kyc'] = $stmt->fetchColumn();

// Pending KYC requests
$stmt = $pdo->query("SELECT COUNT(*) FROM kyc_requests WHERE status = 'pending'");
$stats['pending_kyc'] = $stmt->fetchColumn();

// Total games played
$stmt = $pdo->query("SELECT COUNT(*) FROM game_results");
$stats['total_games'] = $stmt->fetchColumn();

// Total RAWR in circulation
$stmt = $pdo->query("SELECT SUM(rawr_balance) FROM users");
$stats['total_rawr'] = number_format($stmt->fetchColumn(), 2);

// Total tickets in circulation
$stmt = $pdo->query("SELECT SUM(ticket_balance) FROM users");
$stats['total_tickets'] = number_format($stmt->fetchColumn(), 0);

// Recent users (last 5)
$stmt = $pdo->query("SELECT u.id, u.username, u.email, u.rawr_balance, u.ticket_balance, u.created_at, u.is_banned, (SELECT status FROM kyc_requests WHERE user_id = u.id ORDER BY submitted_at DESC LIMIT 1) as kyc_status FROM users u ORDER BY u.created_at DESC LIMIT 5");
$stats['recent_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pending KYC requests
$stmt = $pdo->query("SELECT k.id, u.username, u.email, k.full_name, k.submitted_at 
                     FROM kyc_requests k 
                     JOIN users u ON k.user_id = u.id 
                     WHERE k.status = 'pending' 
                     ORDER BY k.submitted_at DESC LIMIT 5");
$pending_kyc = $stmt->fetchAll(PDO::FETCH_ASSOC);

// User growth (last 30 days)
$user_growth = array_fill(0, 30, 0);
$stmt = $pdo->query("SELECT DATE(created_at) as date, COUNT(*) as count 
                     FROM users 
                     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
                     GROUP BY DATE(created_at)");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $days_ago = (int)((strtotime(date('Y-m-d')) - strtotime($row['date'])) / (60 * 60 * 24));
    if ($days_ago >= 0 && $days_ago < 30) {
        $user_growth[29 - $days_ago] = (int)$row['count'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>RAWR Casino - Admin Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.0/chart.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #FFD700;
            --primary-dark: #FFA500;
            --secondary: #FF6B35;
            --bg-dark: #1a1a1a;
            --bg-darker: #121212;
            --bg-gradient: linear-gradient(135deg, #1a1a1a 0%, #2d1810 50%, #1a1a1a 100%);
            --text-light: #f0f0f0;
            --text-muted: #ccc;
            --card-bg: rgba(30, 30, 30, 0.8);
            --card-border: rgba(255, 215, 0, 0.3);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: var(--bg-gradient);
            color: var(--text-light);
            min-height: 100vh;
            overflow-x: hidden;
            line-height: 1.6;
        }

        .admin-dashboard {
            display: grid;
            grid-template-rows: auto 1fr;
            min-height: 100vh;
        }

        .admin-header {
            background: rgba(26, 26, 26, 0.9);
            border-bottom: 1px solid var(--primary);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(5px);
        }

        .admin-header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            padding: 10px 2rem; /* Add horizontal padding here */
            max-width: none;
            margin: 0;
        }

        .logo-area {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding-left: 15rem; /* Add space from left edge */
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 900;
            background: linear-gradient(45deg, var(--primary), var(--primary-dark), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .logo i {
            font-size: 1.5rem;
        }

        .header-actions {
            display: flex;
            align-items: center;
            margin-left: auto; /* Push to right edge */
            gap: 0; /* Remove extra gap */
        }

        .profile-dropdown {
            position: relative;
        }

        .profile-toggle {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            background: rgba(255, 215, 0, 0.1);
            border: 1px solid rgba(255, 215, 0, 0.3);
            transition: all 0.3s ease;
            position: relative;
            text-decoration: none;
        }
        .profile-toggle:hover {
            background: rgba(255, 215, 0, 0.2);
        }
        .profile-toggle img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary);
        }
        .profile-toggle span {
            font-weight: 600;
            color: var(--text-light);
        }
        .profile-toggle i {
            color: var(--primary);
        }

        .profile-menu {
            position: absolute;
            right: 0;
            top: 100%;
            background: var(--bg-darker);
            border: 1px solid var(--primary);
            border-radius: 10px;
            padding: 0.5rem 0;
            min-width: 200px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: all 0.3s ease;
            z-index: 10;
        }

        .profile-menu.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .profile-menu-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            color: var(--text-light);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .profile-menu-item:hover {
            background: rgba(255, 215, 0, 0.1);
            color: var(--primary);
        }

        .menu-toggle {
            display: none;
            background: transparent;
            border: none;
            color: var(--primary);
            font-size: 1.5rem;
            cursor: pointer;
        }

        .dashboard-content {
            /* Remove grid layout, use flex for better compatibility with fixed sidebar */
            display: flex;
            flex-direction: row;
            margin-left: 250px;
            min-height: 100vh;
        }

        .admin-sidebar {
            background: rgba(26, 26, 26, 0.9);
            border-right: 1px solid rgba(255, 215, 0, 0.2);
            padding: 1.5rem 0;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            overflow-y: auto;
            z-index: 101;
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .sidebar-header {
            color: var(--primary);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 0.75rem 1.5rem;
            margin-top: 1rem;
        }

        .sidebar-header:first-child {
            margin-top: 0;
        }

        .sidebar-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.5rem;
            color: var(--text-light);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .sidebar-item i {
            width: 20px;
            text-align: center;
        }

        .sidebar-item:hover {
            background: rgba(255, 215, 0, 0.1);
            color: var(--primary);
        }

        .sidebar-item.active {
            background: rgba(255, 215, 0, 0.1);
            color: var(--primary);
            border-left: 3px solid var(--primary);
        }

        .main-content {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }

        .section-title-wrapper {
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
            background: linear-gradient(45deg, var(--primary), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .section-subtitle {
            display: block;
            color: var(--primary);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.5rem;
        }

        .section-description {
            color: var(--text-muted);
            max-width: 800px;
        }

        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 15px;
            padding: 1.5rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            backdrop-filter: blur(5px);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(255, 215, 0, 0.1);
        }

        .stat-card-icon {
            font-size: 1.8rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .stat-card-title {
            color: var(--text-muted);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.5rem;
        }

        .stat-card-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .stat-card-trend {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.9rem;
        }

        .stat-card-trend.trend-up {
            color: #28a745;
        }

        .stat-card-trend.trend-down {
            color: #dc3545;
        }

        .dashboard-section {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            backdrop-filter: blur(5px);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .section-title {
            font-size: 1.5rem;
            color: var(--primary);
            margin-bottom: 0;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card-bg);
            border-radius: 10px;
            overflow: hidden;
        }

        .data-table th {
            background: rgba(255, 215, 0, 0.1);
            color: var(--primary);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 1px;
        }

        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 215, 0, 0.1);
            color: var(--text-light);
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        .data-table tr:hover td {
            background: rgba(255, 215, 0, 0.05);
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .badge-success {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }

        .badge-danger {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }

        .badge-warning {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }

        .badge-info {
            background: rgba(23, 162, 184, 0.2);
            color: #17a2b8;
        }

        .badge-primary {
            background: rgba(0, 123, 255, 0.2);
            color: #007bff;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.875rem;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(45deg, var(--primary), var(--primary-dark));
            color: #121212;
        }

        .btn-primary:hover {
            background: linear-gradient(45deg, var(--primary-dark), var(--primary));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 215, 0, 0.3);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--primary);
            color: var(--primary);
        }

        .btn-outline:hover {
            background: rgba(255, 215, 0, 0.1);
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        .btn-view {
            background: rgba(0, 123, 255, 0.2);
            color: #007bff;
        }

        .btn-edit {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }

        .btn-delete {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }

        .btn-view:hover, .btn-edit:hover, .btn-delete:hover {
            opacity: 0.8;
        }

        .actions {
            display: flex;
            gap: 0.5rem;
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        .analytics-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary);
        }

        .table-responsive {
            overflow-x: auto;
        }

        @media (max-width: 1200px) {
            .analytics-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 992px) {
            .dashboard-content {
                grid-template-columns: 1fr;
            }
            
            .admin-sidebar {
                position: fixed;
                top: 0;
                left: -100%;
                width: 280px;
                height: 100vh;
                z-index: 1000;
                transition: left 0.3s ease;
            }
            
            .admin-sidebar.active {
                left: 0;
            }
            
            .menu-toggle {
                display: block;
            }
            
            .sidebar-backdrop {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 999;
                opacity: 0;
                visibility: hidden;
                transition: all 0.3s ease;
            }
            
            .sidebar-backdrop.active {
                opacity: 1;
                visibility: visible;
            }
        }

        @media (max-width: 768px) {
            .dashboard-stats {
                grid-template-columns: 1fr 1fr;
            }
            
            .admin-header {
                padding: 1rem;
            }
            
            .main-content {
                padding: 1rem;
            }
        }

        @media (max-width: 576px) {
            .dashboard-stats {
                grid-template-columns: 1fr;
            }
            
            .stat-card {
                padding: 1rem;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .animate-fadeIn {
            animation: fadeIn 0.5s ease forwards;
        }

        /* Tooltip */
        .tooltip {
            position: relative;
            display: inline-block;
        }

        .tooltip .tooltiptext {
            visibility: hidden;
            width: 200px;
            background-color: rgba(0, 0, 0, 0.8);
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 0.5rem;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 0.9rem;
            font-weight: normal;
            backdrop-filter: blur(5px);
        }

        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }

        /* Status badges */
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-badge.pending {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }

        .status-badge.approved {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }

        .status-badge.rejected {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }

        .status-badge.win {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }

        .status-badge.loss {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }

        .status-badge.active {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }

        .status-badge.banned {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0,0,0,0.6);
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }
        .modal.show {
            display: flex;
        }
        .modal-dialog {
            background: #222;
            border-radius: 12px;
            max-width: 900px; /* Wide modal for KYC */
            width: 100%;
            margin: 40px auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-content {
            padding: 2rem;
            position: relative;
        }
        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            color: #fff;
            font-size: 1.5rem;
            cursor: pointer;
        }
        .modal-header {
            margin-bottom: 1rem;
        }
        .modal-title {
            color: var(--primary);
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
        }
        .modal-body {
            color: var(--text-light);
            font-size: 0.9rem;
        }
        /* KYC Modal Section Styles */
        .modal-section {
            margin-bottom: 2rem;
        }
        .id-documents-row {
            display: flex;
            flex-direction: row;
            gap: 24px;
            flex-wrap: wrap;
            justify-content: center;
            align-items: stretch;
        }
        .id-doc-col {
            width: 100%;
            max-width: 320px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .document-frame {
            width: 100%;
            height: 220px;
            max-width: 100%;
            aspect-ratio: 3/2;
            border: 1px solid var(--card-border);
            border-radius: 8px;
            margin-bottom: 1rem;
            background: #111;
            object-fit: contain;
            display: block;
        }
        .document-placeholder {
            width: 100%;
            height: 220px;
            max-width: 100%;
            background: var(--bg-darker);
            border: 2px dashed var(--primary);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            font-style: italic;
            margin-bottom: 1rem;
        }
        .rejection-reason {
            background: rgba(220, 53, 69, 0.1);
            border-left: 3px solid #dc3545;
            padding: 0.75rem 1rem;
            margin: 1rem 0;
            border-radius: 0 5px 5px 0;
        }

        /* --- MOBILE NAVIGATION IMPROVEMENTS --- */
        @media (max-width: 992px) {
            .dashboard-content {
                margin-left: 0;
            }
            .admin-sidebar {
                position: fixed;
                top: 0;
                left: -100%;
                width: 70vw;
                min-width: 180px;
                max-width: 320px;
                height: 100vh;
                z-index: 1000;
                transition: left 0.3s ease;
                box-shadow: 2px 0 20px #000a;
            }
            .admin-sidebar.active {
                left: 0;
            }
            .sidebar-backdrop {
                position: fixed;
                top: 0;
                left: 0;
                width: 100vw;
                height: 100vh;
                background: rgba(0,0,0,0.5);
                z-index: 999;
                opacity: 1;
                visibility: visible;
                transition: all 0.3s;
            }
            .admin-header-content {
                padding: 10px 1rem;
            }
            .logo-area {
                padding-left: 0;
            }
            .menu-toggle {
                display: block !important;
                margin-left: 1rem;
                font-size: 2rem;
                background: none;
                border: none;
                color: var(--primary);
                cursor: pointer;
            }
        }
        @media (max-width: 576px) {
            .dashboard-stats {
                grid-template-columns: 1fr;
            }
            .stat-card {
                padding: 1rem;
            }
            .main-content {
                padding: 0.5rem;
            }
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            .admin-sidebar {
                width: 90vw;
                min-width: 120px;
            }
        }
        /* --- ICON-ONLY SIDEBAR ON SMALL SCREENS --- */
        @media (max-width: 480px) {
            .admin-sidebar {
                width: 60vw;
                min-width: 80px;
                max-width: 200px;
            }
            .sidebar-item span,
            .sidebar-header {
                display: none !important;
            }
            .sidebar-item {
                justify-content: center;
                padding: 1rem 0.5rem;
            }
            .sidebar-item i {
                font-size: 1.4rem;
            }
        }
        /* Hide profile dropdown on all screens */
        .profile-dropdown, .profile-toggle, .profile-menu {
            display: none !important;
        }
    </style>
</head>
<body>
    <div class="admin-dashboard">
        <header class="admin-header">
            <div class="admin-header-content">
                <div class="logo-area">
                    <a href="admin_dashboard.php" class="logo">
                        <i class="fas fa-paw"></i>
                        <span>RAWR Admin</span>
                    </a>
                </div>
                <button class="menu-toggle" id="adminMenuToggle" aria-label="Open Menu">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </header>

        <div class="sidebar-backdrop" id="sidebarBackdrop" style="display:none;"></div>
        <div class="dashboard-content">
            <!-- Sidebar -->
            <aside class="admin-sidebar" id="adminSidebar">
                <div class="sidebar-nav">
                    <div class="sidebar-header">Main Menu</div>
                    <a href="admin_dashboard.php" class="sidebar-item active">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="manage_users.php" class="sidebar-item">
                        <i class="fas fa-users"></i>
                        <span>Manage Users</span>
                    </a>                    
                    <a href="kyc_requests.php" class="sidebar-item">
                        <i class="fas fa-id-card"></i>
                        <span>KYC Requests</span>
                    </a>
                    <div class="sidebar-header">System</div>
                    <a href="/RAWR/public/logout.php" class="sidebar-item">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </aside>

            <!-- Main Content -->
            <main class="main-content">
                <!-- Dashboard Header -->
                <div class="section-title-wrapper">
                    <span class="section-subtitle">Dashboard Overview</span>
                    <h1 class="section-title">Admin Dashboard</h1>
                    <p class="section-description">Welcome to the RAWR Casino administration panel. Monitor system statistics and manage platform activities.</p>
                </div>

                <!-- Dashboard Stats -->
                <div class="dashboard-stats">
                    <div class="stat-card animate-fadeIn" style="animation-delay: 0.1s;">
                        <div class="stat-card-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-card-title">Total Users</div>
                        <div class="stat-card-value"><?php echo $stats['total_users']; ?></div>
                    </div>
                    
                    <div class="stat-card animate-fadeIn" style="animation-delay: 0.2s;">
                        <div class="stat-card-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="stat-card-title">Active Users</div>
                        <div class="stat-card-value"><?php echo $stats['active_users']; ?></div>
                    </div>
                    
                    <div class="stat-card animate-fadeIn" style="animation-delay: 0.3s;">
                        <div class="stat-card-icon">
                            <i class="fas fa-user-slash"></i>
                        </div>
                        <div class="stat-card-title">Banned Users</div>
                        <div class="stat-card-value"><?php echo $stats['banned_users']; ?></div>
                    </div>
                    
                    <div class="stat-card animate-fadeIn" style="animation-delay: 0.4s;">
                        <div class="stat-card-icon">
                            <i class="fas fa-id-card"></i>
                        </div>
                        <div class="stat-card-title">Pending KYC</div>
                        <div class="stat-card-value"><?php echo $stats['pending_kyc']; ?></div>
                    </div>
                    
                    <div class="stat-card animate-fadeIn" style="animation-delay: 0.5s;">
                        <div class="stat-card-icon">
                            <i class="fas fa-gamepad"></i>
                        </div>
                        <div class="stat-card-title">Total Games</div>
                        <div class="stat-card-value"><?php echo $stats['total_games']; ?></div>
                    </div>
                    
                    <div class="stat-card animate-fadeIn" style="animation-delay: 0.6s;">
                        <div class="stat-card-icon">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="stat-card-title">Total RAWR</div>
                        <div class="stat-card-value"><?php echo $stats['total_rawr']; ?></div>
                    </div>
                    
                    <div class="stat-card animate-fadeIn" style="animation-delay: 0.7s;">
                        <div class="stat-card-icon">
                            <i class="fas fa-ticket-alt"></i>
                        </div>
                        <div class="stat-card-title">Total Tickets</div>
                        <div class="stat-card-value"><?php echo $stats['total_tickets']; ?></div>
                    </div>
                </div>

                <!-- Recent Users Section -->
                <div class="dashboard-section animate-fadeIn" style="animation-delay: 0.8s;">
                    <div class="section-header">
                        <div class="section-title">Recent Users</div>
                        <a href="manage_users.php" class="btn btn-primary">
                            <i class="fas fa-eye"></i> View All Users
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>RAWR</th>
                                    <th>Tickets</th>
                                    <th>Joined</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (
                                    $stats['recent_users'] as $user): ?>
                                <tr>
                                    <td>
                                        <div class="user-info">
                                            <img src="https://avatar.iran.liara.run/public/boy?username=<?php echo urlencode($user['username']); ?>" 
                                                 alt="User Avatar" class="user-avatar">
                                            <span><?php echo htmlspecialchars($user['username']); ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo number_format($user['rawr_balance'], 2); ?></td>
                                    <td><?php echo number_format($user['ticket_balance'], 0); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $user['is_banned'] ? 'banned' : 'active'; ?>">
                                            <?php echo $user['is_banned'] ? 'Banned' : 'Active'; ?>
                                        </span>
                                        <?php if (!empty($user['kyc_status'])): ?>
                                            <span class="status-badge <?php echo $user['kyc_status']; ?>" style="margin-left: 4px;">
                                                <?php echo ucfirst($user['kyc_status']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn btn-sm btn-view" onclick='openUserKycModal(<?php echo (int)$user['id']; ?>)'>
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Analytics Section -->
                <div class="analytics-grid">
                    <div class="dashboard-section animate-fadeIn" style="animation-delay: 1.1s;">
                        <div class="section-header">
                            <div class="section-title">User Growth (30 Days)</div>
                        </div>
                        <div class="chart-container">
                            <canvas id="userGrowthChart"></canvas>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- User Details Modal -->
    <div id="userModal" class="modal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <button type="button" class="modal-close" aria-label="Close" onclick="closeUserModal()"><i class="fas fa-times"></i></button>
                <div class="modal-header">
                    <h2 id="modalTitle" class="modal-title">User Details</h2>
                </div>
                <div class="modal-body" id="modalBody">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>
    <!-- KYC Details Modal -->
    <div id="userKycModal" class="modal" tabindex="-1">
        <div class="modal-dialog" style="max-width:900px;">
            <div class="modal-content">
                <button type="button" class="modal-close" aria-label="Close" onclick="closeUserKycModal()"><i class="fas fa-times"></i></button>
                <div class="modal-header">
                    <h2 id="userKycModalTitle" class="modal-title">KYC Details</h2>
                </div>
                <div class="modal-body" id="userKycModalBody">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>
    <div id="kycImageLightbox" style="display:none;position:fixed;z-index:2000;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.85);align-items:center;justify-content:center;backdrop-filter:blur(2px);">
        <img id="kycLightboxImg" src="" alt="Enlarged KYC Document" style="max-width:90vw;max-height:90vh;border-radius:12px;box-shadow:0 8px 32px #000a;display:block;margin:auto;">
    </div>
    <script>
    function openUserModal(user) {
        var modal = document.getElementById('userModal');
        var modalBody = document.getElementById('modalBody');
        var modalTitle = document.getElementById('modalTitle');
        modalTitle.textContent = 'User Details - ' + user.username;
        modalBody.innerHTML = `
            <div style="text-align:center; margin-bottom:1rem;">
                <img src="https://avatar.iran.liara.run/public/boy?username=${encodeURIComponent(user.username)}" alt="User Avatar" style="width:64px; height:64px; border-radius:50%; border:2px solid var(--primary); margin-bottom:0.5rem;">
                <div style="font-weight:600; font-size:1.1rem; color:var(--primary);">${user.username}</div>
            </div>
            <div><strong>Email:</strong> ${user.email}</div>
            <div><strong>RAWR Balance:</strong> ${parseFloat(user.rawr_balance).toFixed(2)}</div>
            <div><strong>Ticket Balance:</strong> ${parseInt(user.ticket_balance)}</div>
            <div><strong>Status:</strong> <span class="status-badge ${user.is_banned ? 'banned' : 'active'}">${user.is_banned ? 'Banned' : 'Active'}</span></div>
            <div><strong>Joined:</strong> ${new Date(user.created_at).toLocaleDateString()}</div>
        `;
        modal.style.display = 'flex';
    }
    function closeUserModal() {
        var modal = document.getElementById('userModal');
        modal.style.display = 'none';
    }
    window.addEventListener('click', function(e) {
        var modal = document.getElementById('userModal');
        if (modal && modal.style.display === 'flex' && e.target === modal) {
            closeUserModal();
        }
    });
    window.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeUserModal();
    });
    function openUserKycModal(userId) {
        var modal = document.getElementById('userKycModal');
        var modalBody = document.getElementById('userKycModalBody');
        var modalTitle = document.getElementById('userKycModalTitle');
        modalTitle.textContent = 'KYC Details';
        modalBody.innerHTML = '<div style="text-align:center; padding:2rem;">Loading...</div>';
        modal.style.display = 'flex';
        fetch('get_kyc_request.php?user_id=' + encodeURIComponent(userId))
            .then(response => response.json())
            .then(kycData => {
                console.log('KYC Data:', kycData); // Debug: see what fields are returned
                if (!kycData || !kycData.id) {
                    modalBody.innerHTML = '<div style="color:#dc3545;">No KYC data found for this user.</div>';
                    return;
                }
                let statusBadge = '';
                if (kycData.status === 'not_verified') {
                    statusBadge = '<span class="status-badge not-verified">Not Verified</span>';
                } else if (kycData.status === 'pending') {
                    statusBadge = '<span class="status-badge pending">Pending</span>';
                } else if (kycData.status === 'approved') {
                    statusBadge = '<span class="status-badge approved">Approved</span>';
                } else {
                    statusBadge = '<span class="status-badge rejected">Rejected</span>';
                }
                let rejectionSection = '';
                if (kycData.status === 'rejected' && kycData.rejection_reason) {
                    rejectionSection = `
                        <div class="rejection-reason">
                            <strong>Reason for Rejection:</strong> ${kycData.rejection_reason}
                        </div>
                    `;
                }
                let kycExtraFields = '';
                if (kycData.date_of_birth) {
                    kycExtraFields += `<p><strong>Date of Birth:</strong> ${kycData.date_of_birth}</p>`;
                }
                if (kycData.contact_number) {
                    kycExtraFields += `<p><strong>Contact Number:</strong> ${kycData.contact_number}</p>`;
                }
                if (kycData.address) {
                    kycExtraFields += `<p><strong>Address:</strong> ${kycData.address}</p>`;
                }
                if (kycData.city) {
                    kycExtraFields += `<p><strong>City:</strong> ${kycData.city}</p>`;
                }
                if (kycData.state_province) {
                    kycExtraFields += `<p><strong>State/Province:</strong> ${kycData.state_province}</p>`;
                }
                if (kycData.postal_code) {
                    kycExtraFields += `<p><strong>Postal Code:</strong> ${kycData.postal_code}</p>`;
                }
                // Robust image path logic (match kyc_requests.php)
                function getKycImageUrl(path) {
                    if (!path) return null;
                    path = path.replace(/^\/+/,''); // Remove leading slashes
                    // If path starts with 'uploads/', use /RAWR/public/ + path
                    if (path.startsWith('uploads/')) return '/RAWR/public/' + path;
                    // If path already starts with /RAWR/public/, return as is
                    if (path.startsWith('/RAWR/public/')) return path;
                    // Otherwise, fallback to /RAWR/public/uploads/kyc_docs/ + path
                    return '/RAWR/public/uploads/kyc_docs/' + path;
                }
                // Use only id_image_path and id_image_back_path as in kyc_requests.php
                let idFrontPath = kycData.id_image_path || '';
                let idBackPath = kycData.id_image_back_path || '';
                let idFrontUrl = getKycImageUrl(idFrontPath);
                let idBackUrl = getKycImageUrl(idBackPath);
                let idFront = idFrontUrl ? `<img src="${idFrontUrl}" alt="ID Front" class="document-frame kyc-enlarge-img" data-img-src="${idFrontUrl}" style="max-width:220px;max-height:160px;border:2px solid #ccc;border-radius:8px;object-fit:contain;background:#fff;box-shadow:0 2px 8px #0001;margin-bottom:8px;cursor:zoom-in;" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"> <div class='document-placeholder' style='display:none;'>No front image uploaded</div>` : '<div class="document-placeholder">No front image uploaded</div>';
                let idBack = idBackUrl ? `<img src="${idBackUrl}" alt="ID Back" class="document-frame kyc-enlarge-img" data-img-src="${idBackUrl}" style="max-width:220px;max-height:160px;border:2px solid #ccc;border-radius:8px;object-fit:contain;background:#fff;box-shadow:0 2px 8px #0001;margin-bottom:8px;cursor:zoom-in;" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"> <div class='document-placeholder' style='display:none;'>No back image uploaded</div>` : '<div class="document-placeholder">No back image uploaded</div>';
                modalBody.innerHTML = `
                    <div class="modal-section">
                        <h3><i class="fas fa-user"></i> User Information</h3>
                        <p><strong>Username:</strong> ${kycData.username}</p>
                        <p><strong>Email:</strong> ${kycData.email}</p>
                        <p><strong>Registered:</strong> ${new Date(kycData.user_created_at).toLocaleDateString()}</p>
                        <p><strong>RAWR Balance:</strong> ${parseFloat(kycData.rawr_balance).toFixed(2)}</p>
                        <p><strong>Tickets:</strong> ${kycData.ticket_balance}</p>
                    </div>
                    <div class="modal-section">
                        <h3><i class="fas fa-id-card"></i> KYC Details</h3>
                        <p><strong>Status:</strong> ${statusBadge}</p>
                        <p><strong>Full Name:</strong> ${kycData.full_name}</p>
                        <p><strong>Submitted At:</strong> ${new Date(kycData.submitted_at).toLocaleString()}</p>
                        ${kycData.reviewed_at ? `<p><strong>Reviewed At:</strong> ${new Date(kycData.reviewed_at).toLocaleString()}</p>` : ''}
                        ${kycExtraFields}
                        ${rejectionSection}
                    </div>
                    <div class="modal-section">
                        <h3><i class="fas fa-file-image"></i> ID Document</h3>
                        <div class="id-documents-row">
                            <div class="id-doc-col">
                                <div style="font-weight:600;margin-bottom:0.5rem;">Front</div>
                                ${idFront}
                            </div>
                            <div class="id-doc-col">
                                <div style="font-weight:600;margin-bottom:0.5rem;">Back</div>
                                ${idBack}
                            </div>
                        </div>
                    </div>
                `;
                // Add event listeners for image enlargement
                setTimeout(() => {
                    document.querySelectorAll('.kyc-enlarge-img').forEach(img => {
                        img.addEventListener('click', function() {
                            showKycImageLightbox(this.getAttribute('data-img-src'));
                        });
                    });
                }, 0);
            })
            .catch(() => {
                modalBody.innerHTML = '<div style="color:#dc3545;">Failed to load KYC data.</div>';
            });
    }
    function closeUserKycModal() {
        var modal = document.getElementById('userKycModal');
        modal.style.display = 'none';
    }
    window.addEventListener('click', function(e) {
        var modal = document.getElementById('userKycModal');
        if (modal && modal.style.display === 'flex' && e.target === modal) {
            closeUserKycModal();
        }
    });
    window.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeUserKycModal();
    });
    function showKycImageLightbox(src) {
        var lightbox = document.getElementById('kycImageLightbox');
        var img = document.getElementById('kycLightboxImg');
        img.src = src;
        lightbox.style.display = 'flex';
    }
    document.getElementById('kycImageLightbox').addEventListener('click', function(e) {
        if (e.target === this) this.style.display = 'none';
    });
    window.addEventListener('keydown', function(e) {
        var lightbox = document.getElementById('kycImageLightbox');
        if (e.key === 'Escape' && lightbox.style.display === 'flex') {
            lightbox.style.display = 'none';
        }
    });
    </script>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.0/chart.min.js"></script>
    <script>
        // User Growth Chart Data from PHP
        const userGrowthData = <?php echo json_encode(array_values($user_growth)); ?>;
        
        // User Growth Chart
        const userGrowthCtx = document.getElementById('userGrowthChart').getContext('2d');
        const userGrowthChart = new Chart(userGrowthCtx, {
            type: 'line',
            data: {
                labels: Array.from({length: 30}, (_, i) => {
                    const date = new Date();
                    date.setDate(date.getDate() - (29 - i));
                    return date.toLocaleDateString('en-US', {month: 'short', day: 'numeric'});
                }),
                datasets: [{
                    label: 'New Users',
                    data: userGrowthData,
                    fill: true,
                    backgroundColor: 'rgba(255, 215, 0, 0.2)',
                    borderColor: 'rgba(255, 215, 0, 1)',
                    tension: 0.4,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: 'rgba(255, 215, 0, 1)',
                    pointBorderWidth: 2,
                    pointHoverBackgroundColor: 'rgba(255, 215, 0, 1)',
                    pointHoverBorderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        
        // --- MOBILE SIDEBAR TOGGLE LOGIC ---
        const menuToggle = document.getElementById('adminMenuToggle');
        const sidebar = document.getElementById('adminSidebar');
        const backdrop = document.getElementById('sidebarBackdrop');

        function openSidebar() {
            sidebar.classList.add('active');
            backdrop.style.display = 'block';
        }
        function closeSidebar() {
            sidebar.classList.remove('active');
            backdrop.style.display = 'none';
        }
        menuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            openSidebar();
        });
        backdrop.addEventListener('click', function() {
            closeSidebar();
        });
        // Close sidebar on navigation click (mobile)
        document.querySelectorAll('.sidebar-item').forEach(item => {
            item.addEventListener('click', () => {
                if (window.innerWidth <= 992) closeSidebar();
            });
        });
        // Close sidebar on resize if desktop
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992) closeSidebar();
        });
        
        // Toggle profile dropdown
        document.querySelector('.profile-toggle').addEventListener('click', function(e) {
            e.stopPropagation();
            document.querySelector('.profile-menu').classList.toggle('active');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            document.querySelector('.profile-menu').classList.remove('active');
        });
        
        // Prevent closing when clicking inside dropdown
        document.querySelector('.profile-menu').addEventListener('click', function(e) {
            e.stopPropagation();
        });
        
        // Set initial theme from localStorage
        const savedTheme = localStorage.getItem('theme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
        
        // Update theme icon
        const themeIcon = document.querySelector('.theme-toggle i');
        if (savedTheme === 'dark') {
            themeIcon.classList.remove('fa-moon');
            themeIcon.classList.add('fa-sun');
        }
        
        // Save theme preference
        document.querySelector('.theme-toggle').addEventListener('click', function() {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            localStorage.setItem('theme', currentTheme === 'dark' ? 'light' : 'dark');
        });
    </script>
</body>
</html>