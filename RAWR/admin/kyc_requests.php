<?php
require_once __DIR__ . '/../backend/inc/init.php';
error_log('ADMIN DASHBOARD SESSION: ' . print_r($_SESSION, true));
// Only allow access if role is 'superadmin' and admin_id is set
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin' || !isset($_SESSION['admin_id'])) {
    header("Location: /RAWR/public/login.php");
    exit;
}

require_once __DIR__ . '/../backend/inc/db.php';
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

// Initialize arrays
$pending_kyc = [];
$approved_kyc = [];
$rejected_kyc = [];
$approved_kyc_users = [];
$error_message = '';
$success_message = '';

// Fetch pending KYC users from users table
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.email, u.rawr_balance, u.ticket_balance, u.created_at, k.id AS kyc_request_id
        FROM users u
        JOIN kyc_requests k ON k.user_id = u.id AND k.status = 'pending'
        WHERE u.kyc_status = 'pending'
        ORDER BY u.created_at DESC
    ");
    $stmt->execute();
    $pending_kyc = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching pending KYC users: " . $e->getMessage());
    $error_message = "Error loading pending KYC users.";
}

// Fetch approved KYC requests
try {
    $stmt = $pdo->prepare("
        SELECT k.id, u.username, u.email, k.full_name, k.id_image_path, 
               k.status, k.submitted_at, k.reviewed_at, k.rejection_reason,
               u.id as user_id, u.rawr_balance, u.ticket_balance, u.created_at as user_created_at
        FROM kyc_requests k
        JOIN users u ON k.user_id = u.id
        WHERE k.status = 'approved'
        ORDER BY k.reviewed_at DESC
        LIMIT 50
    ");
    $stmt->execute();
    $approved_kyc = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching approved KYC requests: " . $e->getMessage());
    $error_message = "Error loading approved KYC requests.";
}

// Fetch rejected KYC requests
try {
    $stmt = $pdo->prepare("
        SELECT k.id, u.username, u.email, k.full_name, k.id_image_path, 
               k.status, k.submitted_at, k.reviewed_at, k.rejection_reason,
               u.id as user_id, u.rawr_balance, u.ticket_balance, u.created_at as user_created_at
        FROM kyc_requests k
        JOIN users u ON k.user_id = u.id
        WHERE k.status = 'rejected'
        ORDER BY k.reviewed_at DESC
        LIMIT 50
    ");
    $stmt->execute();
    $rejected_kyc = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching rejected KYC requests: " . $e->getMessage());
    $error_message = "Error loading rejected KYC requests.";
}

// Fetch approved KYC users from users table (move this before counting)
try {
    $stmt = $pdo->prepare("
        SELECT id, username, email, rawr_balance, ticket_balance, created_at
        FROM users
        WHERE kyc_status = 'approved' AND is_banned = 0
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $approved_kyc_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching approved KYC users: " . $e->getMessage());
    $error_message = "Error loading approved KYC users.";
}

// Count total requests (after all fetches)
$total_pending = count($pending_kyc);
$total_approved = count($approved_kyc_users); // Only count users table for stat card
$total_rejected = count($rejected_kyc);

// Handle modal view requests
$view_request = null;

if (isset($_GET['view_request']) && is_numeric($_GET['view_request'])) {
    $request_id = (int)$_GET['view_request'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT k.id, u.username, u.email, k.full_name, k.id_image_path, k.id_image_back_path,
                   k.status, k.submitted_at, k.reviewed_at, k.rejection_reason,
                   k.date_of_birth, k.contact_number, k.address, k.city, k.state_province, k.postal_code, k.selfie_image_path,
                   u.id as user_id, u.rawr_balance, u.ticket_balance, u.created_at as user_created_at
            FROM kyc_requests k
            JOIN users u ON k.user_id = u.id
            WHERE k.id = ?
        ");
        $stmt->execute([$request_id]);
        $view_request = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching KYC request details: " . $e->getMessage());
        $error_message = "Error loading KYC request details.";
    }
}

// Handle success/error messages from URL parameters
if (isset($_GET['success'])) {
    $success_message = urldecode($_GET['success']);
}
if (isset($_GET['error'])) {
    $error_message = urldecode($_GET['error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>RAWR Casino - KYC Requests</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../public/css/admin-dashboard.css">
    <style>
        /* Use all the styles from admin_dashboard.php */
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

        /* Include all the CSS from admin_dashboard.php here */
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
            padding-left: 15rem;
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
            padding: 0.5rem 1rem;
            color: var(--text-light);
            text-decoration: none;
            transition: all 0.3s ease;
            border-radius: 5px;
            margin-left: 0.5rem;
        }
        .profile-menu-item:hover {
            background: rgba(255, 215, 0, 0.1);
            color: var(--primary);
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

        /* Additional styles for KYC specific elements */
        .document-frame {
            width: 100%;
            height: 500px;
            max-width: 100%;
            aspect-ratio: 3/2;
            border: 1px solid var(--card-border);
            border-radius: 8px;
            margin-bottom: 1rem;
            background: #111;
            display: block;
        }
        .modal-section {
            margin-bottom: 2rem;
        }
        .modal-section .document-placeholder {
            width: 100%;
            height: 500px;
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
        .modal-section .id-documents-row {
            display: flex;
            flex-direction: column;
            gap: 24px;
            flex-wrap: nowrap;
            justify-content: center;
            align-items: stretch;
        }
        .modal-section .id-doc-col {
            width: 100%;
            max-width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        @media (max-width: 900px) {
            .modal-dialog {
                max-width: 98vw;
            }
            .document-frame, .modal-section .document-placeholder {
                width: 100%;
                height: 320px;
                max-width: 100vw;
                min-width: 0;
            }
        }

        .rejection-reason {
            background: rgba(220, 53, 69, 0.1);
            border-left: 3px solid #dc3545;
            padding: 0.75rem 1rem;
            margin: 1rem 0;
            border-radius: 0 5px 5px 0;
        }

        .tabs {
            display: flex;
            gap: 4px;
            background: rgba(255, 215, 0, 0.1);
            padding: 4px;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .tab-btn {
            padding: 0.75rem 1.5rem;
            background: transparent;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tab-btn.active {
            background: var(--card-bg);
            color: var(--primary);
            box-shadow: 0 2px 10px rgba(255, 215, 0, 0.1);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .no-requests {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-muted);
        }

        .no-requests i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: rgba(255, 215, 0, 0.3);
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

.status-badge.not-verified {
    background: rgba(108, 117, 125, 0.2);
    color: #6c757d;
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

        .dashboard-content {
            display: flex;
            flex: 1;
            margin-top: var(--header-height);
            margin-left: 250px; /* Offset for sidebar */
            width: calc(100vw - 250px); /* Ensure content fills the rest of the viewport */
            box-sizing: border-box;
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
        
        @media (max-width: 992px) {
            .dashboard-content {
                margin-left: 0;
                width: 100vw;
            }
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 24px;
            margin-left: 0;
            width: 100%; /* Ensure main content fills the available space */
            box-sizing: border-box;
            transition: margin-left 0.3s ease-in-out;
        }
        .sidebar-collapsed .main-content {
            margin-left: 0;
        }

        /* Modal styles - hide by default, show with .show */
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
            width: 100%;
            max-width: 900px;
            margin: 20px auto;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-content {
            background: #222;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            padding: 0;
            position: relative;
            animation: modalSlideIn 0.3s ease-out;
        }
        @keyframes modalSlideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .modal-header, .modal-footer, .modal-body {
            padding: 24px;
        }
        .modal-close {
            position: absolute;
            top: 18px;
            right: 18px;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #aaa;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: all 0.2s;
        }
        .modal-close:hover {
            background: #333;
            color: #fff;
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
                <div class="header-actions">
                    <div class="profile-dropdown">
                        <div class="profile-toggle">
                            <img src="<?php echo $profile_image_url; ?>" alt="Admin">
                            <span><?php echo htmlspecialchars($username); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="dashboard-content">
            <!-- Sidebar -->
            <aside class="admin-sidebar">
                <div class="sidebar-nav">
                    <div class="sidebar-header">Main Menu</div>
                    <a href="admin_dashboard.php" class="sidebar-item">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="manage_users.php" class="sidebar-item">
                        <i class="fas fa-users"></i>
                        <span>Manage Users</span>
                    </a>
                    <a href="kyc_requests.php" class="sidebar-item active">
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
                    <span class="section-subtitle">User Verification</span>
                    <h1 class="section-title">KYC Requests</h1>
                    <p class="section-description">Review and manage Know Your Customer verification requests submitted by users.</p>
                </div>

                <!-- Stats Cards -->
                <div class="dashboard-stats">
                    <div class="stat-card animate-fadeIn" style="animation-delay: 0.1s;">
                        <div class="stat-card-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-card-title">Pending Requests</div>
                        <div class="stat-card-value"><?php echo $total_pending; ?></div>
                    </div>
                    
                    <div class="stat-card animate-fadeIn" style="animation-delay: 0.2s;">
                        <div class="stat-card-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-card-title">Approved</div>
                        <div class="stat-card-value"><?php echo $total_approved; ?></div>
                    </div>
                    
                    <div class="stat-card animate-fadeIn" style="animation-delay: 0.3s;">
                        <div class="stat-card-icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-card-title">Rejected</div>
                        <div class="stat-card-value"><?php echo $total_rejected; ?></div>
                    </div>
                </div>

                <!-- KYC Requests Section -->
                <div class="dashboard-section animate-fadeIn" style="animation-delay: 0.4s;">
                    <div class="tabs">
                        <button class="tab-btn active" data-tab="pending">
                            <i class="fas fa-clock"></i> Pending
                        </button>
                        <button class="tab-btn" data-tab="approved">
                            <i class="fas fa-check"></i> Approved
                        </button>
                        <button class="tab-btn" data-tab="rejected">
                            <i class="fas fa-times"></i> Rejected
                        </button>
                    </div>
                    
                    <div id="pending" class="tab-content active">
                        <?php if (count($pending_kyc) === 0): ?>
                            <div class="no-requests">
                                <i class="fas fa-check-circle"></i>
                                <p>No pending KYC requests at this time.</p>
                            </div>
                        <?php else: ?>
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
                                <?php foreach ($pending_kyc as $user): ?>
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
                                            <span class="status-badge pending">Pending</span>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <button class="btn btn-sm btn-view" onclick="openKycModal(<?php echo $user['kyc_request_id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div id="approved" class="tab-content">
                        <?php 
                        $has_approved = count($approved_kyc) > 0 || count($approved_kyc_users) > 0;
                        if (!$has_approved): ?>
                            <div class="no-requests">
                                <i class="fas fa-check-circle"></i>
                                <p>No approved KYC requests found.</p>
                            </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>User ID</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>RAWR Balance</th>
                                        <th>Ticket Balance</th>
                                        <th>Joined</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php 
                                // Show users from kyc_requests table (old logic)
                                foreach ($approved_kyc as $kyc): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($kyc['user_id']); ?></td>
                                        <td><?php echo htmlspecialchars($kyc['username']); ?></td>
                                        <td><?php echo htmlspecialchars($kyc['email']); ?></td>
                                        <td><?php echo htmlspecialchars($kyc['rawr_balance']); ?></td>
                                        <td><?php echo htmlspecialchars($kyc['ticket_balance']); ?></td>
                                        <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($kyc['user_created_at']))); ?></td>
                                        <td>
                                            <a href="javascript:void(0);" class="btn btn-sm btn-view" title="View" onclick="openKycModal(<?php echo $kyc['id']; ?>)"><i class="fas fa-eye"></i></a>
                                            <a href="ban_user.php?id=<?php echo $kyc['user_id']; ?>" class="btn btn-sm btn-delete" title="Ban" onclick="return confirm('Are you sure you want to ban this user?');"><i class="fas fa-ban"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php 
                                // Show users from users table (new logic)
                                foreach ($approved_kyc_users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['id']); ?></td>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['rawr_balance']); ?></td>
                                        <td><?php echo htmlspecialchars($user['ticket_balance']); ?></td>
                                        <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($user['created_at']))); ?></td>
                                        <td>
                                            <a href="manage_users.php?view_user=<?php echo $user['id']; ?>" class="btn btn-sm btn-view" title="View"><i class="fas fa-eye"></i></a>
                                            <a href="ban_user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-delete" title="Ban" onclick="return confirm('Are you sure you want to ban this user?');"><i class="fas fa-ban"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div id="rejected" class="tab-content">
                        <?php if (count($rejected_kyc) === 0): ?>
                            <div class="no-requests">
                                <i class="fas fa-times-circle"></i>
                                <p>No rejected KYC requests found.</p>
                            </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Request ID</th>
                                        <th>User</th>
                                        <th>Full Name</th>
                                        <th>Submitted At</th>
                                        <th>Rejected At</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($rejected_kyc as $kyc): ?>
                                    <tr>
                                        <td>#<?php echo str_pad($kyc['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                        <td>
                                            <div class="user-info">
                                                <img src="https://avatar.iran.liara.run/public/boy?username=<?php echo urlencode($kyc['username']); ?>" 
                                                     alt="User Avatar" class="user-avatar">
                                                <span><?php echo htmlspecialchars($kyc['username']); ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($kyc['full_name']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($kyc['submitted_at'])); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($kyc['reviewed_at'])); ?></td>
                                        <td><span class="status-badge rejected">Rejected</span></td>
                                        <td>
                                            <div class="actions">
                                                <button type="button" class="btn btn-sm btn-view" onclick="openKycModal(<?php echo $kyc['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- KYC Details Modal -->
    <div id="kycModal" class="modal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <button type="button" class="modal-close" aria-label="Close" onclick="closeKycModal()">
                    <i class="fas fa-times"></i>
                </button>
                <div class="modal-header">
                    <h2 id="modalTitle" class="modal-title">KYC Request Details</h2>
                </div>
                <div class="modal-body" id="modalBody">
                    <!-- Content will be loaded here -->
                </div>
                <div class="modal-footer" id="modalFooter">
                    <!-- Buttons will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Rejection Reason Modal -->
    <div id="rejectionModal" class="modal">
        <div class="modal-dialog" style="max-width: 500px;">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">Rejection Reason</h2>
                    <button type="button" class="modal-close" onclick="closeRejectionModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form id="rejectionForm" method="post" action="process_kyc.php" onsubmit="return validateRejection()">
                    <div class="modal-body">
                        <p>Please provide a reason for rejecting this KYC request:</p>
                        <textarea name="rejection_reason" id="rejectionReason" 
                            style="width: 100%; min-height: 100px; padding: 8px; margin-top: 10px; border: 1px solid var(--card-border); border-radius: 4px; background: var(--bg-darker); color: var(--text-light);"
                            required></textarea>
                        <input type="hidden" name="request_id" id="rejectionRequestId">
                        <input type="hidden" name="action" value="reject">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" onclick="closeRejectionModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Rejection</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="kycImageLightbox" style="display:none;position:fixed;z-index:2000;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.85);align-items:center;justify-content:center;backdrop-filter:blur(2px);">
        <button id="kycLightboxCloseBtn" style="position:fixed;top:32px;right:32px;z-index:2100;background:rgba(0,0,0,0.7);color:#fff;border:none;border-radius:50%;width:44px;height:44px;font-size:2rem;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px #0006;">
            &times;
        </button>
        <img id="kycLightboxImg" src="" alt="Enlarged KYC Document" style="max-width:90vw;max-height:90vh;border-radius:12px;box-shadow:0 8px 32px #000a;display:block;margin:auto;z-index:2001;">
    </div>
    <script>
        // Tab Functionality
        document.querySelectorAll('.tab-btn').forEach(button => {
            button.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                
                // Remove active class from all tabs and contents
                document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                
                // Add active class to clicked tab and corresponding content
                this.classList.add('active');
                document.getElementById(tabId).classList.add('active');
            });
        });

        // KYC Modal Functionality
        function openKycModal(requestId) {
            // Show loading state
            const modal = document.getElementById('kycModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalBody = document.getElementById('modalBody');
            const modalFooter = document.getElementById('modalFooter');
            
            modalTitle.textContent = 'Loading...';
            modalBody.innerHTML = '<div class="loading" style="margin: 20px auto;"></div>';
            modalFooter.innerHTML = '';
            modal.classList.add('show');

            // Fetch KYC data via AJAX
            fetch('get_kyc_request.php?id=' + encodeURIComponent(requestId))
                .then(response => {
                    if (!response.ok) throw new Error('Failed to fetch KYC data');
                    return response.json();
                })
                .then(data => {
                    if (data && !data.error) {
                        showKycModal(data);
                    } else {
                        modalTitle.textContent = 'Error';
                        modalBody.innerHTML = '<div style="color:red;">' + (data.error || 'Failed to load data') + '</div>';
                    }
                })
                .catch(err => {
                    modalTitle.textContent = 'Error';
                    modalBody.innerHTML = '<div style="color:red;">Failed to load data</div>';
                });
        }

        function showKycModal(kycData) {
            const modal = document.getElementById('kycModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalBody = document.getElementById('modalBody');
            const modalFooter = document.getElementById('modalFooter');
            
            modalTitle.textContent = `KYC Request #${kycData.id}`;
            
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
                        <h4>Rejection Reason</h4>
                        <p>${kycData.rejection_reason}</p>
                    </div>
                `;
            }
            // KYC extra fields
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
            let selfieSection = '';
            if (kycData.selfie_image_path) {
                selfieSection = `
                    <div class="modal-section">
                        <h3><i class='fas fa-user-circle'></i> Selfie</h3>
                        <div style="width:100%;display:flex;justify-content:center;">
                            <img src="${getKycImageUrl(kycData.selfie_image_path)}" alt="Selfie" style="max-width:220px;max-height:220px;border-radius:12px;border:2px solid var(--primary);background:#111;object-fit:cover;">
                        </div>
                    </div>
                `;
            }
            
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
                ${selfieSection}
                <div class="modal-section">
                    <h3><i class="fas fa-file-image"></i> ID Document</h3>
                    <div class="id-documents-row" style="flex-direction:row;gap:24px;justify-content:center;align-items:stretch;">
                        <div class="id-doc-col">
                            <div style="font-weight:600;margin-bottom:0.5rem;">Front</div>
                            ${kycData.id_image_path ? 
                                `<img src="${getKycImageUrl(kycData.id_image_path)}" alt="ID Front" class="document-frame kyc-enlarge-img" data-img-src="${getKycImageUrl(kycData.id_image_path)}" style="max-width:220px;max-height:160px;border:2px solid #ccc;border-radius:8px;object-fit:contain;background:#fff;box-shadow:0 2px 8px #0001;margin-bottom:8px;cursor:zoom-in;" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"> <div class='document-placeholder' style='display:none;'>No front image uploaded</div>` 
                                : `<div class="document-placeholder">No front image uploaded</div>`
                            }
                        </div>
                        <div class="id-doc-col">
                            <div style="font-weight:600;margin-bottom:0.5rem;">Back</div>
                            ${kycData.id_image_back_path ? 
                                `<img src="${getKycImageUrl(kycData.id_image_back_path)}" alt="ID Back" class="document-frame kyc-enlarge-img" data-img-src="${getKycImageUrl(kycData.id_image_back_path)}" style="max-width:220px;max-height:160px;border:2px solid #ccc;border-radius:8px;object-fit:contain;background:#fff;box-shadow:0 2px 8px #0001;margin-bottom:8px;cursor:zoom-in;" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"> <div class='document-placeholder' style='display:none;'>No back image uploaded</div>` 
                                : `<div class="document-placeholder">No back image uploaded</div>`
                            }
                        </div>
                    </div>
                </div>
            `;
            
            if (kycData.status === 'pending') {
                modalFooter.innerHTML = `
                    <form method="post" action="process_kyc.php" style="display: inline;">
                        <input type="hidden" name="request_id" value="${kycData.id}">
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Are you sure you want to approve this KYC request?')">
                            <i class="fas fa-check"></i> Approve
                        </button>
                    </form>
                    <button type="button" class="btn btn-outline" onclick="showRejectionModal(${kycData.id})">
                        <i class="fas fa-times"></i> Reject
                    </button>
                `;
            } else {
                modalFooter.innerHTML = `
                    <button type="button" class="btn btn-outline" onclick="closeKycModal()">
                        <i class="fas fa-times"></i> Close
                    </button>
                `;
            }
            
            modal.classList.add('show');

            // Add event listeners for image enlargement after modal content is set
            setTimeout(() => {
                document.querySelectorAll('.kyc-enlarge-img').forEach(img => {
                    img.addEventListener('click', function() {
                        showKycImageLightbox(this.getAttribute('data-img-src'));
                    });
                });
            }, 0);
        }

        function closeKycModal() {
            const modal = document.getElementById('kycModal');
            modal.classList.remove('show');
            
            // Remove URL parameters
            const url = new URL(window.location);
            url.searchParams.delete('view_request');
            window.history.replaceState({}, document.title, url);
        }

        // Rejection Modal Functionality
        function showRejectionModal(requestId) {
            document.getElementById('rejectionRequestId').value = requestId;
            document.getElementById('rejectionReason').value = '';
            document.getElementById('rejectionModal').classList.add('show');
        }

        function closeRejectionModal() {
            document.getElementById('rejectionModal').classList.remove('show');
        }

        function validateRejection() {
            const reason = document.getElementById('rejectionReason').value.trim();
            if (!reason) {
                alert('Please provide a reason for rejection.');
                return false;
            }
            return confirm('Are you sure you want to reject this KYC request?');
        }

        // Close modals when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                if (document.getElementById('kycModal').classList.contains('show')) {
                    closeKycModal();
                }
                if (document.getElementById('rejectionModal').classList.contains('show')) {
                    closeRejectionModal();
                }
            }
        });

        // Close modals with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (document.getElementById('kycModal').classList.contains('show')) {
                    closeKycModal();
                }
                if (document.getElementById('rejectionModal').classList.contains('show')) {
                    closeRejectionModal();
                }
            }
        });

        // Initialize if viewing a specific request
        <?php if ($view_request): ?>
        window.addEventListener('DOMContentLoaded', function() {
            showKycModal(<?php echo json_encode($view_request); ?>);
        });
        <?php endif; ?>

        // Toggle mobile sidebar
        document.querySelector('.menu-toggle').addEventListener('click', function() {
            document.querySelector('.admin-sidebar').classList.toggle('active');
            
            // Create or toggle backdrop for mobile
            let backdrop = document.querySelector('.sidebar-backdrop');
            if (!backdrop) {
                backdrop = document.createElement('div');
                backdrop.className = 'sidebar-backdrop';
                document.body.appendChild(backdrop);
            }
            backdrop.classList.toggle('active');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.admin-sidebar');
            const menuToggle = document.querySelector('.menu-toggle');
            const backdrop = document.querySelector('.sidebar-backdrop');
            
            if (sidebar && sidebar.classList.contains('active') && 
                !sidebar.contains(event.target) && 
                !menuToggle.contains(event.target)) {
                sidebar.classList.remove('active');
                if (backdrop) backdrop.classList.remove('active');
            }
        });

        // Add this helper function in your <script> section before showKycModal:
        function getKycImageUrl(path) {
            // Remove any leading slashes
            path = path.replace(/^\/+/, '');
            // If path starts with 'uploads/', use /RAWR/public/ + path
            if (path.startsWith('uploads/')) {
                return '/RAWR/public/' + path;
            }
            // If path already starts with /RAWR/public/, return as is
            if (path.startsWith('/RAWR/public/')) {
                return path;
            }
            // Otherwise, fallback to /RAWR/public/uploads/ + path
            return '/RAWR/public/uploads/' + path;
        }

        // KYC Image Lightbox Logic
        function showKycImageLightbox(src) {
            const lightbox = document.getElementById('kycImageLightbox');
            const img = document.getElementById('kycLightboxImg');
            img.src = src;
            lightbox.style.display = 'flex';
        }

        function closeKycImageLightbox() {
            const lightbox = document.getElementById('kycImageLightbox');
            const img = document.getElementById('kycLightboxImg');
            lightbox.style.display = 'none';
            img.src = '';
        }

        // Event listeners for lightbox
        document.getElementById('kycImageLightbox').addEventListener('click', function(e) {
            // Close if clicking on background (not the image itself)
            if (e.target === this || e.target.id === 'kycLightboxCloseBtn') {
                closeKycImageLightbox();
            }
        });

        document.getElementById('kycLightboxCloseBtn').addEventListener('click', function(e) {
            e.stopPropagation();
            closeKycImageLightbox();
        });

        window.addEventListener('keydown', function(e) {
            const lightbox = document.getElementById('kycImageLightbox');
            if (e.key === 'Escape' && lightbox.style.display === 'flex') {
                closeKycImageLightbox();
            }
        });

        // Add click handlers to all KYC images (this should already exist in your code)
        document.querySelectorAll('.kyc-enlarge-img').forEach(img => {
            img.addEventListener('click', function() {
                showKycImageLightbox(this.getAttribute('data-img-src'));
            });
        });
    </script>
</body>
</html>