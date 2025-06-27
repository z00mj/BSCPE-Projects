<?php
require_once __DIR__ . '/../backend/inc/init.php';
error_log('MANAGE USERS SESSION: ' . print_r($_SESSION, true));
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

// Pagination setup
$per_page = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$kyc_filter = isset($_GET['kyc']) ? $_GET['kyc'] : 'all';

error_log("Search term: '" . $search . "'"); // Debugging
error_log("Status filter: " . $status_filter);
error_log("KYC filter: " . $kyc_filter);

// Base query
$query = "SELECT u.*, 
                 (SELECT status FROM kyc_requests WHERE user_id = u.id ORDER BY submitted_at DESC LIMIT 1) as kyc_status
          FROM users u";
$count_query = "SELECT COUNT(*) FROM users u";

// Where conditions
$where = [];
$params = [];

// Search condition
if (!empty($search)) {
    $where[] = "(u.username LIKE :search OR u.email LIKE :search)";
    $params[':search'] = '%' . $search . '%';
    error_log("Adding search condition with parameter: " . $params[':search']);
}

if ($status_filter !== 'all') {
    $where[] = "u.is_banned = :is_banned";
    $params[':is_banned'] = $status_filter === 'banned' ? 1 : 0;
    error_log("Adding status filter: " . $params[':is_banned']);
}
if ($kyc_filter !== 'all') {
    $where[] = "(SELECT status FROM kyc_requests WHERE user_id = u.id ORDER BY submitted_at DESC LIMIT 1) = :kyc_status";
    $params[':kyc_status'] = $kyc_filter;
    error_log("Adding KYC filter: " . $params[':kyc_status']);
}

// Build final queries
if (!empty($where)) {
    $where_clause = " WHERE " . implode(" AND ", $where);
    $query .= $where_clause;
    $count_query .= $where_clause;
}

// Add sorting and pagination
$query .= " ORDER BY u.created_at DESC LIMIT :limit OFFSET :offset";

error_log("Final count query: " . $count_query);
error_log("Final select query: " . $query);
error_log("Parameters: " . print_r($params, true));

// Get total count
$total_users = 0;
try {
    $count_stmt = $pdo->prepare($count_query);
    foreach ($params as $key => $value) {
        if ($key === ':is_banned') {
            $count_stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $count_stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }
    $count_stmt->execute();
    $total_users = (int)$count_stmt->fetchColumn();
    error_log("Total users found: " . $total_users);
} catch (PDOException $e) {
    error_log("Error counting users: " . $e->getMessage());
    $total_users = 0;
}

// Get users
$users = [];
try {
    $stmt = $pdo->prepare($query);
    // Bind search/filter params
    foreach ($params as $key => $value) {
        if ($key === ':is_banned') {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }
    // Bind pagination params
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Users fetched: " . count($users));
    if (!empty($users)) {
        error_log("First user: " . print_r($users[0], true));
    }
} catch (PDOException $e) {
    error_log("Error fetching users: " . $e->getMessage());
    error_log("SQL Error Info: " . print_r($stmt->errorInfo(), true));
}

// Calculate total pages - THIS WAS MISSING!
$total_pages = ceil($total_users / $per_page);
error_log("Total pages calculated: " . $total_pages);

// Debug: Let's also check if we have any users at all
try {
    $debug_stmt = $pdo->prepare("SELECT COUNT(*) FROM users");
    $debug_stmt->execute();
    $debug_total = $debug_stmt->fetchColumn();
    error_log("DEBUG: Total users in database: " . $debug_total);
    
    // Also check first few usernames/emails
    $debug_stmt2 = $pdo->prepare("SELECT username, email FROM users LIMIT 5");
    $debug_stmt2->execute();
    $debug_users = $debug_stmt2->fetchAll(PDO::FETCH_ASSOC);
    error_log("DEBUG: Sample users: " . print_r($debug_users, true));
} catch (PDOException $e) {
    error_log("DEBUG query error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>RAWR Casino - Manage Users</title>
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

        .dashboard-content {
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

        .search-filter-container {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border-radius: 50px;
            border: 1px solid var(--primary);
            background: var(--bg-darker);
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
        }

        .filter-select {
            padding: 0.75rem 1rem;
            border-radius: 50px;
            border: 1px solid var(--primary);
            background: var(--bg-darker);
            color: var(--text-light);
            font-size: 0.9rem;
            min-width: 150px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .pagination-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--bg-darker);
            color: var(--text-light);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .pagination-link:hover {
            background: rgba(255, 215, 0, 0.1);
            color: var(--primary);
        }

        .pagination-link.active {
            background: var(--primary);
            color: var(--bg-darker);
            font-weight: 600;
        }

        .pagination-link.disabled {
            opacity: 0.5;
            pointer-events: none;
        }

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
            max-width: 900px;
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
            cursor: zoom-in;
        }
        .document-placeholder {
            width: 100%;
            height: 220px;
            max-width: 100%;
            background: var(--bg-darker);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #888;
            border-radius: 8px;
            font-size: 1rem;
        }

        /* Add these CSS rules to your existing styles */
.kyc-lightbox {
    display: none;
    position: fixed;
    z-index: 2000;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    align-items: center;
    justify-content: center;
}

.lightbox-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.85);
    backdrop-filter: blur(2px);
}

.lightbox-content {
    position: relative;
    max-width: 90vw;
    max-height: 90vh;
    z-index: 2001;
}

.lightbox-close {
    position: absolute;
    top: -40px;
    right: 0;
    background: rgba(255, 255, 255, 0.9);
    border: none;
    border-radius: 50%;
    width: 35px;
    height: 35px;
    font-size: 20px;
    font-weight: bold;
    cursor: pointer;
    color: #000;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.3s ease;
}

.lightbox-close:hover {
    background: rgba(255, 255, 255, 1);
}

#kycLightboxImg {
    max-width: 90vw;
    max-height: 90vh;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.6);
    display: block;
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
            .admin-header {
                padding: 1rem;
            }
            
            .main-content {
                padding: 1rem;
            }
            
            .search-filter-container {
                flex-direction: column;
            }
            
            .search-box, .filter-select {
                width: 100%;
            }
        }

        @media (max-width: 576px) {
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .data-table td, .data-table th {
                padding: 0.75rem;
            }
            
            .actions {
                flex-direction: column;
                gap: 0.25rem;
            }
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
                    <button class="menu-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
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

            <!-- Main Content -->
            <main class="main-content">
                <!-- Dashboard Header -->
                <div class="section-title-wrapper">
                    <span class="section-subtitle">User Management</span>
                    <h1 class="section-title">Manage Users</h1>
                    <p class="section-description">View and manage all registered users on the RAWR Casino platform.</p>
                </div>

                <!-- Search and Filter -->
                <form method="get" action="manage_users.php">
                    <div class="search-filter-container">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" placeholder="Search by username or email..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <select class="filter-select" name="status">
                            <option value="all" <?php if ($status_filter === 'all') echo 'selected'; ?>>All Status</option>
                            <option value="active" <?php if ($status_filter === 'active') echo 'selected'; ?>>Active</option>
                            <option value="banned" <?php if ($status_filter === 'banned') echo 'selected'; ?>>Banned</option>
                        </select>
                        <select class="filter-select" name="kyc">
                            <option value="all" <?php if ($kyc_filter === 'all') echo 'selected'; ?>>All KYC</option>
                            <option value="pending" <?php if ($kyc_filter === 'pending') echo 'selected'; ?>>Pending</option>
                            <option value="approved" <?php if ($kyc_filter === 'approved') echo 'selected'; ?>>Approved</option>
                            <option value="rejected" <?php if ($kyc_filter === 'rejected') echo 'selected'; ?>>Rejected</option>
                        </select>
                        <button type="submit" class="btn btn-primary">Search</button>
                    </div>
                </form>

                <!-- Users Table -->
                <div class="dashboard-section">
                    <div class="section-header">
                        <div class="section-title">All Users</div>
                        <div>
                            Showing <?php echo min($per_page, count($users)); ?> of <?php echo $total_users; ?> users
                        </div>
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
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center;">No users found matching your criteria</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users as $user): ?>
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
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="pagination-link" title="First Page">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="pagination-link" title="Previous Page">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        <?php else: ?>
                            <span class="pagination-link disabled"><i class="fas fa-angle-double-left"></i></span>
                            <span class="pagination-link disabled"><i class="fas fa-angle-left"></i></span>
                        <?php endif; ?>

                        <?php 
                        // Show page numbers
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1) {
                            echo '<span class="pagination-link disabled">...</span>';
                        }
                        
                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="pagination-link <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; 
                        
                        if ($end_page < $total_pages) {
                            echo '<span class="pagination-link disabled">...</span>';
                        }
                        ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="pagination-link" title="Next Page">
                                <i class="fas fa-angle-right"></i>
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" class="pagination-link" title="Last Page">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="pagination-link disabled"><i class="fas fa-angle-right"></i></span>
                            <span class="pagination-link disabled"><i class="fas fa-angle-double-right"></i></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
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

<!-- Replace your existing KYC Image Lightbox HTML with this: -->
<div id="kycImageLightbox" class="kyc-lightbox">
    <div class="lightbox-backdrop"></div>
    <div class="lightbox-content">
        <button class="lightbox-close" onclick="closeKycLightbox()">&times;</button>
        <img id="kycLightboxImg" src="" alt="Enlarged KYC Document">
    </div>
</div>
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
        document.querySelector('.profile-menu').addEventListener('click', function(e) {
            e.stopPropagation();
        });

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
                    function getKycImageUrl(path) {
                        if (!path) return null;
                        path = path.replace(/^\/+/,'')
                        if (path.startsWith('uploads/')) return '/RAWR/public/' + path;
                        if (path.startsWith('/RAWR/public/')) return path;
                        return '/RAWR/public/uploads/kyc_docs/' + path;
                    }
                    let idFrontPath = kycData.id_image_path || '';
                    let idBackPath = kycData.id_image_back_path || '';
                    let idFrontUrl = getKycImageUrl(idFrontPath);
                    let idBackUrl = getKycImageUrl(idBackPath);
                    let idFront = idFrontUrl ? `<img src="${idFrontUrl}" alt="ID Front" class="document-frame" style="cursor:zoom-in;" onclick="showKycImageLightbox('${idFrontUrl.replace(/'/g, '\'')}')" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"> <div class='document-placeholder' style='display:none;'>No front image uploaded</div>` : '<div class="document-placeholder">No front image uploaded</div>';
                    let idBack = idBackUrl ? `<img src="${idBackUrl}" alt="ID Back" class="document-frame" style="cursor:zoom-in;" onclick="showKycImageLightbox('${idBackUrl.replace(/'/g, '\'')}')" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"> <div class='document-placeholder' style='display:none;'>No back image uploaded</div>` : '<div class="document-placeholder">No back image uploaded</div>';
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

// Replace ALL your existing lightbox JavaScript with this:

function showKycImageLightbox(src) {
    var lightbox = document.getElementById('kycImageLightbox');
    var img = document.getElementById('kycLightboxImg');
    if (lightbox && img) {
        img.src = src;
        lightbox.style.display = 'flex';
        document.body.style.overflow = 'hidden'; // Prevent scrolling
    }
}

function closeKycLightbox() {
    var lightbox = document.getElementById('kycImageLightbox');
    if (lightbox) {
        lightbox.style.display = 'none';
        document.body.style.overflow = 'auto'; // Restore scrolling
    }
}

// Set up event listeners when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    var lightbox = document.getElementById('kycImageLightbox');
    var backdrop = lightbox ? lightbox.querySelector('.lightbox-backdrop') : null;
    var img = document.getElementById('kycLightboxImg');
    
    // Close when clicking the backdrop
    if (backdrop) {
        backdrop.addEventListener('click', closeKycLightbox);
    }
    
    // Close when clicking anywhere on the lightbox except the image
    if (lightbox) {
        lightbox.addEventListener('click', function(e) {
            // Only close if the click target is the lightbox itself (not its children)
            if (e.target === lightbox) {
                closeKycLightbox();
            }
        });
    }
    
    // Prevent image clicks from bubbling up and closing the lightbox
    if (img) {
        img.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
    
    // Close with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeKycLightbox();
        }
    });
});
    </script>
</body>
</html>