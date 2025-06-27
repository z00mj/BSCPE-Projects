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

// Check if user ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: manage_users.php');
    exit;
}

$userId = intval($_GET['id']);

// Fetch admin data
$admin_data = null;
$admin_id = $_SESSION["admin_id"];

try {
    $sql = "SELECT * FROM admin_users WHERE id = :admin_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':admin_id', $admin_id, PDO::PARAM_INT);
    $stmt->execute();
    $admin_data = $stmt->fetch();
    
    // Get user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        header('Location: manage_users.php');
        exit;
    }
    
    // Handle form submission
// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isBanned = isset($_POST['is_banned']) ? 1 : 0;
    $kycStatus = $_POST['kyc_status'];
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Update user data
        $stmt = $pdo->prepare("UPDATE users SET is_banned = ?, kyc_status = ? WHERE id = ?");
        $stmt->execute([$isBanned, $kycStatus, $userId]);
        
        // Update KYC request status if it exists
        if ($kycStatus !== 'not_verified') {
            $stmt = $pdo->prepare("UPDATE kyc_requests SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE user_id = ?");
            $stmt->execute([$kycStatus, $admin_id, $userId]);
            
            // If rejecting, ensure we have a reason
            if ($kycStatus === 'rejected' && empty($_POST['rejection_reason'])) {
                throw new Exception("Rejection reason is required when rejecting KYC");
            }
            
            // Add rejection reason if provided
            if ($kycStatus === 'rejected' && !empty($_POST['rejection_reason'])) {
                $stmt = $pdo->prepare("UPDATE kyc_requests SET rejection_reason = ? WHERE user_id = ?");
                $stmt->execute([$_POST['rejection_reason'], $userId]);
            }
        }
        
        // Log the KYC status change in admin audit log if it changed
        if ($user['kyc_status'] !== $kycStatus) {
            $action = 'kyc_' . $kycStatus;
            $details = 'Changed KYC status from ' . $user['kyc_status'] . ' to ' . $kycStatus;
            
            $stmt = $pdo->prepare("INSERT INTO admin_audit_log (admin_id, action, target_type, target_id, details) 
                                  VALUES (?, ?, 'user', ?, ?)");
            $stmt->execute([$admin_id, $action, $userId, $details]);
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Redirect back to the same page with success message
        $_SESSION['success_message'] = 'User updated successfully';
        header("Location: edit_user.php?id=$userId");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error updating user: " . $e->getMessage());
        $_SESSION['error_message'] = 'Error updating user: ' . $e->getMessage();
        header("Location: edit_user.php?id=$userId");
        exit;
    }
}
    
} catch(PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    header('Location: manage_users.php');
    exit;
}

// Prepare variables for display
$username = $admin_data['username'] ?? 'Admin';
$profile_image_url = 'https://avatar.iran.liara.run/public/boy?username=' . urlencode($username);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>RAWR Casino - Edit User</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;900&display=swap" rel="stylesheet">
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
            padding: 10px 2rem;
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
            margin-left: auto;
            gap: 0;
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
            transition: left 0.3s;
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
        textarea.form-control {
            resize: none;
            min-height: 100px;
            width: 100%;
            padding: 10px;
            border: 1px solid rgba(255, 215, 0, 0.3);
            border-radius: 4px;
            transition: border-color 0.3s;
            background: rgba(30, 30, 30, 0.5);
            color: var(--text-light);
        }
        textarea.form-control:focus {
            border-color: var(--primary);
            outline: none;
        }
        .form-section {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(5px);
            box-shadow: 0 4px 24px rgba(0,0,0,0.12);
        }
        .form-group {
            margin-bottom: 1rem;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-light);
            font-weight: 600;
        }
        input[type="text"],
        input[type="email"],
        input[type="number"],
        input[type="password"],
        select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid rgba(255, 215, 0, 0.3);
            border-radius: 8px;
            background: rgba(30, 30, 30, 0.5);
            color: var(--text-light);
            transition: all 0.3s ease;
        }
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="number"]:focus,
        input[type="password"]:focus,
        select:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.1);
        }
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
        }
        .checkbox-custom {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid var(--primary);
            border-radius: 4px;
            position: relative;
            transition: all 0.3s ease;
        }
        input[type="checkbox"] {
            display: none;
        }
        input[type="checkbox"]:checked + .checkbox-custom::after {
            content: '\f00c';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: var(--primary);
            font-size: 12px;
        }
        .radio-group {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .radio-label {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
        }
        .radio-custom {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 2px solid var(--primary);
            border-radius: 50%;
            position: relative;
            transition: all 0.3s ease;
        }
        input[type="radio"] {
            display: none;
        }
        input[type="radio"]:checked + .radio-custom::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 10px;
            height: 10px;
            background: var(--primary);
            border-radius: 50%;
        }
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }
        .form-help {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }
        .user-avatar-large {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
            margin-bottom: 1rem;
        }
        .dashboard-content {
            margin-left: 250px !important;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            transition: margin-left 0.3s;
        }
        .main-content {
            width: 100%;
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem 2rem;
            box-sizing: border-box;
        }
        .section-title-wrapper {
            margin-bottom: 2rem;
            text-align: left;
        }
        .section-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.25rem;
        }
        .section-subtitle {
            font-size: 1rem;
            color: var(--secondary);
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .section-description {
            color: var(--text-muted);
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }
        /* Status badges */
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }
        .status-not_verified {
            background-color: rgba(108, 117, 125, 0.2);
            color: #6c757d;
            border: 1px solid #6c757d;
        }
        .status-pending {
            background-color: rgba(255, 193, 7, 0.2);
            color: #ffc107;
            border: 1px solid #ffc107;
        }
        .status-approved {
            background-color: rgba(40, 167, 69, 0.2);
            color: #28a745;
            border: 1px solid #28a745;
        }
        .status-rejected {
            background-color: rgba(220, 53, 69, 0.2);
            color: #dc3545;
            border: 1px solid #dc3545;
        }
        .themed-btn {
            background: linear-gradient(90deg, var(--primary), var(--primary-dark));
            color: #1a1a1a;
            border: none;
            border-radius: 8px;
            padding: 0.75rem 2rem;
            font-weight: 700;
            font-size: 1rem;
            box-shadow: 0 2px 8px rgba(255, 215, 0, 0.08);
            transition: background 0.3s, color 0.3s, box-shadow 0.3s;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .themed-btn:hover, .themed-btn:focus {
            background: linear-gradient(90deg, var(--primary-dark), var(--secondary));
            color: #fff;
            box-shadow: 0 4px 16px rgba(255, 215, 0, 0.15);
            outline: none;
        }
        .themed-btn-outline {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
            border-radius: 8px;
            padding: 0.75rem 2rem;
            font-weight: 700;
            font-size: 1rem;
            transition: background 0.3s, color 0.3s, border-color 0.3s;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-left: 0.5rem;
            text-decoration: none;
        }
        .themed-btn-outline:hover, .themed-btn-outline:focus {
            background: var(--primary);
            color: #1a1a1a;
            border-color: var(--primary-dark);
            outline: none;
            text-decoration: none;
        }
        /* --- IMPROVED RESPONSIVENESS --- */
        @media (max-width: 1200px) {
            .logo-area {
                padding-left: 2rem;
            }
            .main-content {
                max-width: 98vw;
                padding: 1.5rem 1rem;
            }
        }
        @media (max-width: 900px) {
            .dashboard-content {
                margin-left: 0 !important;
            }
            .admin-sidebar {
                left: -260px;
                width: 220px;
                transition: left 0.3s;
            }
            .admin-sidebar.active {
                left: 0;
            }
            .main-content {
                padding: 1rem 0.5rem;
            }
            .admin-header-content {
                padding: 10px 1rem;
            }
            .logo-area {
                padding-left: 1rem;
            }
        }
        @media (max-width: 700px) {
            .admin-header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            .logo-area {
                padding-left: 0;
            }
            .main-content {
                padding: 0.5rem 0.25rem;
            }
            .form-section {
                padding: 1rem;
            }
            .user-avatar-large {
                width: 60px;
                height: 60px;
            }
            .section-title {
                font-size: 1.3rem;
            }
        }
        @media (max-width: 600px) {
            .admin-header-content {
                padding: 10px 0.5rem;
            }
            .main-content {
                padding: 0.25rem 0.1rem;
            }
            .form-section {
                padding: 0.5rem;
            }
            .user-info {
                flex-direction: column;
                align-items: flex-start;
            }
            .form-actions {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
        /* Make user-info section responsive */
        .user-info {
            display: flex;
            align-items: flex-start;
            gap: 1.5rem;
            flex-wrap: wrap;
        }
        .user-details {
            font-size: 0.97rem;
        }
        /* Scrollbar styling for sidebar */
        .admin-sidebar {
            scrollbar-width: thin;
            scrollbar-color: var(--primary) var(--bg-dark);
        }
        .admin-sidebar::-webkit-scrollbar {
            width: 6px;
        }
        .admin-sidebar::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 6px;
        }
        .admin-sidebar::-webkit-scrollbar-track {
            background: var(--bg-dark);
        }
        /* Mobile sidebar toggle button */
        @media (max-width: 900px) {
            .menu-toggle {
                display: block;
                margin-left: 1rem;
            }
        }
        /* Overlay for sidebar on mobile */
        .sidebar-backdrop {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 100;
        }
        .sidebar-backdrop.active {
            display: block;
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
                    <button class="menu-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>
        </header>

        <!-- Sidebar (moved outside dashboard-content) -->
        <aside class="admin-sidebar">
            <div class="sidebar-nav">
                <div class="sidebar-header">Main Menu</div>
                <a href="admin_dashboard.php" class="sidebar-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="manage_users.php" class="sidebar-item active">
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

        <div class="dashboard-content">
            <!-- Main Content -->
            <main class="main-content">
                <!-- Dashboard Header -->
            <div class="section-title-wrapper">
                <span class="section-subtitle">User Management</span>
                <h1 class="section-title">Edit User</h1>
                <p class="section-description">Update user account details and status.</p>
                
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success" style="background: rgba(40, 167, 69, 0.2); color: #28a745; padding: 10px; border-radius: 5px; margin-top: 1rem;">
                        <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                    </div>
                <?php endif; ?>
            </div>

                <form method="POST" class="user-edit-form" action="edit_user.php?id=<?php echo $userId; ?>">
                    <div class="form-section">
                        <div class="user-info" style="margin-bottom: 1.5rem;">
                            <img src="https://avatar.iran.liara.run/public/boy?username=<?php echo urlencode($user['username']); ?>" 
                                 alt="User Avatar" class="user-avatar-large">
                            <div>
                                <h3><?php echo htmlspecialchars($user['username']); ?>
                                    <span class="status-badge status-<?php echo $user['kyc_status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $user['kyc_status'])); ?>
                                    </span>
                                </h3>
                                <p><?php echo htmlspecialchars($user['email']); ?></p>
                                <p>Joined: <?php echo date('M j, Y', strtotime($user['created_at'])); ?></p>
                                <div class="user-details" style="margin-top:1rem; background:rgba(255,215,0,0.05); border-radius:10px; padding:1rem;">
                                    <?php
                                    // Fetch KYC request details (including full_name)
                                    $kycDetails = null;
                                    $stmt = $pdo->prepare("SELECT full_name, submitted_at, reviewed_at, date_of_birth, contact_number, address, city, state_province, postal_code FROM kyc_requests WHERE user_id = ? ORDER BY submitted_at DESC LIMIT 1");
                                    $stmt->execute([$userId]);
                                    $kycDetails = $stmt->fetch(PDO::FETCH_ASSOC);
                                    ?>
                                    <p><strong>Full Name:</strong> <?php echo $kycDetails && $kycDetails['full_name'] ? htmlspecialchars($kycDetails['full_name']) : 'N/A'; ?></p>
                                    <p><strong>Date of Birth:</strong> <?php echo $kycDetails && $kycDetails['date_of_birth'] ? htmlspecialchars($kycDetails['date_of_birth']) : 'N/A'; ?></p>
                                    <p><strong>Contact Number:</strong> <?php echo $kycDetails && $kycDetails['contact_number'] ? htmlspecialchars($kycDetails['contact_number']) : 'N/A'; ?></p>
                                    <p><strong>Address:</strong> <?php echo $kycDetails && $kycDetails['address'] ? htmlspecialchars($kycDetails['address']) : 'N/A'; ?></p>
                                    <p><strong>City:</strong> <?php echo $kycDetails && $kycDetails['city'] ? htmlspecialchars($kycDetails['city']) : 'N/A'; ?></p>
                                    <p><strong>State/Province:</strong> <?php echo $kycDetails && $kycDetails['state_province'] ? htmlspecialchars($kycDetails['state_province']) : 'N/A'; ?></p>
                                    <p><strong>Postal Code:</strong> <?php echo $kycDetails && $kycDetails['postal_code'] ? htmlspecialchars($kycDetails['postal_code']) : 'N/A'; ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="is_banned" <?= $user['is_banned'] ? 'checked' : '' ?>>
                                <span class="checkbox-custom"></span>
                                <span>Banned User</span>
                            </label>
                            <p class="form-help">Check to ban this user from accessing the platform</p>
                        </div>
                    </div>

<div class="form-section">
    <h3 class="section-title">KYC Status</h3>
    
    <div class="form-group">
        <label>Current Verification Status</label>
        <div class="radio-group">
            <label class="radio-label">
                <input type="radio" name="kyc_status" value="not_verified" <?= $user['kyc_status'] === 'not_verified' ? 'checked' : '' ?>>
                <span class="radio-custom"></span>
                <span>Not Verified</span>
            </label>
            
            <label class="radio-label">
                <input type="radio" name="kyc_status" value="pending" <?= $user['kyc_status'] === 'pending' ? 'checked' : '' ?>>
                <span class="radio-custom"></span>
                <span>Pending</span>
            </label>
            
            <label class="radio-label">
                <input type="radio" name="kyc_status" value="approved" <?= $user['kyc_status'] === 'approved' ? 'checked' : '' ?>>
                <span class="radio-custom"></span>
                <span>Approved</span>
            </label>
            
            <label class="radio-label">
                <input type="radio" name="kyc_status" value="rejected" <?= $user['kyc_status'] === 'rejected' ? 'checked' : '' ?>>
                <span class="radio-custom"></span>
                <span>Rejected</span>
            </label>
        </div>
        <p class="form-help">Changing this status will be recorded in the audit log</p>
    </div>
    
    <div class="form-group" id="rejection-reason-group" style="<?= $user['kyc_status'] === 'rejected' ? '' : 'display: none;' ?>">
        <label for="rejection_reason">Rejection Reason (required if rejecting)</label>
        <textarea name="rejection_reason" id="rejection_reason" class="form-control"><?php 
            // Fetch existing rejection reason if available
            if ($user['kyc_status'] === 'rejected') {
                $stmt = $pdo->prepare("SELECT rejection_reason FROM kyc_requests WHERE user_id = ?");
                $stmt->execute([$userId]);
                $kycRequest = $stmt->fetch();
                if ($kycRequest && $kycRequest['rejection_reason']) {
                    echo htmlspecialchars($kycRequest['rejection_reason']);
                }
            }
        ?></textarea>
    </div>
</div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary themed-btn">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <a href="manage_users.php" class="btn btn-outline themed-btn-outline">
                            Cancel
                        </a>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.0/chart.min.js"></script>
    <script>
        // Toggle sidebar on mobile
        document.querySelector('.menu-toggle').addEventListener('click', function() {
            document.querySelector('.admin-sidebar').classList.toggle('active');
            document.querySelector('.sidebar-backdrop').classList.toggle('active');
        });
        
        // Close sidebar when clicking backdrop
        document.querySelector('.sidebar-backdrop')?.addEventListener('click', function() {
            document.querySelector('.admin-sidebar').classList.remove('active');
            this.classList.remove('active');
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
        document.querySelector('.profile-menu')?.addEventListener('click', function(e) {
            e.stopPropagation();
        });

        // Show/hide rejection reason based on selected status
        document.querySelectorAll('input[name="kyc_status"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.getElementById('rejection-reason-group').style.display = 
                    this.value === 'rejected' ? 'block' : 'none';
            });
        });
    </script>
</body>
</html>