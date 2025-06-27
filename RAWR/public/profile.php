<?php
require_once __DIR__ . '/../backend/inc/init.php';
userOnly(); // Ensure only logged-in users can access this page

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

// The redundant jsonResponse function that was here has been removed.
// The global version from functions.php is now used.

// --- FIX: Corrected and secured file upload handling ---
function handleKycUpload($fileKey, $userId) {
    if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES[$fileKey];

        // Validate file type and size against constants from config.php
        $allowedTypes = defined('ALLOWED_FILE_TYPES') ? ALLOWED_FILE_TYPES : ['image/jpeg', 'image/png', 'application/pdf'];
        $maxFileSize = defined('MAX_FILE_SIZE') ? MAX_FILE_SIZE : 8 * 1024 * 1024;

        if (!in_array($file['type'], $allowedTypes)) {
            return ['error' => 'Invalid file type. Only JPG, PNG, and PDF are allowed.'];
        }

        if ($file['size'] > $maxFileSize) {
            return ['error' => 'File size exceeds the 8MB limit.'];
        }

        // Use absolute path from UPLOAD_DIR constant for reliability
        $uploadDir = UPLOAD_DIR;
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                error_log("Failed to create upload directory: " . $uploadDir);
                return ['error' => 'Server configuration error: Could not create upload directory.'];
            }
        }
        
        $fileName = 'user' . $userId . '_' . uniqid() . '_' . basename($file['name']);
        $filePath = $uploadDir . $fileName;

        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            // Return relative path for database storage and web access
            return ['path' => 'uploads/kyc_docs/' . $fileName];
        } else {
            error_log("Failed to move uploaded file to: " . $filePath);
            return ['error' => 'Server error: Could not save the uploaded file. Please check permissions.'];
        }
    }
    return ['error' => 'No file was uploaded or an unexpected upload error occurred.'];
}

// --- Fetch User Data and Related Information ---
$user = $db->fetchOne("SELECT users.*, user_avatars.file_path AS avatar_path
                       FROM users
                       LEFT JOIN user_avatars ON users.avatar_id = user_avatars.id
                       WHERE users.id = ?", [$userId]);
if (!$user) {
    redirect('/login.php');
}

// Fetch related data and initialize if it doesn't exist for the user
$miningData = $db->fetchOne("SELECT * FROM mining_data WHERE user_id = ?", [$userId]);
$miningUpgrades = $db->fetchOne("SELECT * FROM mining_upgrades WHERE user_id = ?", [$userId]);
$kycData = $db->fetchOne("SELECT * FROM kyc_requests WHERE user_id = ?", [$userId]);
if (!$kycData) {
    $db->insert("kyc_requests", ['user_id' => $userId, 'full_name' => '', 'id_image_path' => '']);
    $kycData = $db->fetchOne("SELECT * FROM kyc_requests WHERE user_id = ?", [$userId]);
}


// --- Handle POST requests for profile updates, avatar upload, and KYC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitizeInput($_POST['action'] ?? '');

    switch ($action) {
        case 'update_profile':
            $username = sanitizeInput($_POST['username'] ?? '');
            $bio = sanitizeInput($_POST['bio'] ?? '');
            if (empty($username)) jsonResponse(['status' => 'error', 'message' => 'Username cannot be empty.'], 400);
            $existingUser = $db->fetchOne("SELECT id FROM users WHERE username = ? AND id != ?", [$username, $userId]);
            if ($existingUser) jsonResponse(['status' => 'error', 'message' => 'Username already taken.'], 409);
            $db->update("users", ['username' => $username, 'bio' => $bio], "id = ?", [$userId]);
            jsonResponse(['status' => 'success', 'message' => 'Profile updated successfully!', 'new_username' => $username]);
            break;

        case 'change_password':
            // Password change logic remains here...
            break;

        case 'save_personal_info':
            $kycUpdateData = [
                'full_name' => sanitizeInput($_POST['fullName'] ?? ''),
                'date_of_birth' => sanitizeInput($_POST['dob'] ?? ''),
                'country' => sanitizeInput($_POST['country'] ?? ''),
                'contact_number' => sanitizeInput($_POST['contactNumber'] ?? ''),
                'address' => sanitizeInput($_POST['address'] ?? ''),
                'city' => sanitizeInput($_POST['city'] ?? ''),
                'state_province' => sanitizeInput($_POST['stateProvince'] ?? ''),
                'postal_code' => sanitizeInput($_POST['postalCode'] ?? '')
            ];
            $db->update("kyc_requests", $kycUpdateData, "user_id = ?", [$userId]);
            jsonResponse(['status' => 'success', 'message' => 'Personal information saved.']);
            break;

        case 'submit_kyc_document':
            $data = [
                'id_type' => sanitizeInput($_POST['idType'] ?? ''),
                'id_number' => sanitizeInput($_POST['idNumber'] ?? '')
            ];
            if (isset($_FILES['id_front_document'])) {
                $uploadResult = handleKycUpload('id_front_document', $userId);
                if (isset($uploadResult['path'])) $data['id_image_path'] = $uploadResult['path'];
                else jsonResponse(['status' => 'error', 'message' => 'Front ID: ' . $uploadResult['error']], 400);
            }
            if (isset($_FILES['id_back_document']) && $_FILES['id_back_document']['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadResult = handleKycUpload('id_back_document', $userId);
                if (isset($uploadResult['path'])) $data['id_image_back_path'] = $uploadResult['path'];
                else jsonResponse(['status' => 'error', 'message' => 'Back ID: ' . $uploadResult['error']], 400);
            }
            $db->update("kyc_requests", $data, "user_id = ?", [$userId]);
            jsonResponse(['status' => 'success', 'message' => 'ID document(s) uploaded successfully.']);
            break;

        case 'submit_selfie':
             if (isset($_FILES['selfie_document'])) {
                $uploadResult = handleKycUpload('selfie_document', $userId);
                if (isset($uploadResult['path'])) {
                    $db->update("kyc_requests", ['selfie_image_path' => $uploadResult['path']], "user_id = ?", [$userId]);
                    jsonResponse(['status' => 'success', 'message' => 'Selfie uploaded successfully.']);
                } else {
                    jsonResponse(['status' => 'error', 'message' => 'Selfie: ' . $uploadResult['error']], 400);
                }
            } else {
                 jsonResponse(['status' => 'error', 'message' => 'No selfie file was provided.'], 400);
            }
            break;
            
        case 'final_kyc_submit':
            if ($user['kyc_status'] === 'approved' || $user['kyc_status'] === 'pending') {
                jsonResponse(['status' => 'error', 'message' => 'KYC already submitted or approved.'], 400);
            }
            $db->update("users", ['kyc_status' => 'pending'], "id = ?", [$userId]);
            $db->update("kyc_requests", ['status' => 'pending', 'submitted_at' => date('Y-m-d H:i:s')], "user_id = ?", [$userId]);
            jsonResponse(['status' => 'success', 'message' => 'KYC application submitted for review.']);
            break;

        case 'upload_avatar':
            // Avatar upload logic remains here...
            break;

        case 'save_wallet_address':
            $walletAddress = sanitizeInput($_POST['wallet_address'] ?? '');
            if ($walletAddress === '' || preg_match('/^0x[a-fA-F0-9]{40}$/', $walletAddress)) {
                try {
                    $db->update('users', ['wallet_address' => $walletAddress], 'id = ?', [$userId]);
                    $message = $walletAddress === '' ? 'Wallet disconnected successfully.' : 'Wallet connected successfully.';
                    jsonResponse(['status' => 'success', 'message' => $message, 'wallet_address' => $walletAddress]);
                } catch (Exception $e) {
                    error_log("Wallet save failed for user {$userId}: " . $e->getMessage());
                    jsonResponse(['status' => 'error', 'message' => 'Database error: Could not save wallet.'], 500);
                }
            } else {
                jsonResponse(['status' => 'error', 'message' => 'Invalid wallet address format.'], 400);
            }
            break;
    }
    exit;
}

// Prepare remaining data for frontend display
$loginStreak = $db->fetchOne("SELECT * FROM login_streaks WHERE user_id = ?", [$userId]);
$currentStreak = $loginStreak['current_streak'] ?? 0;
$longestStreak = $loginStreak['longest_streak'] ?? 0;
$miningMultiplier = 1 + ((($miningUpgrades['shovel_level'] ?? 1) - 1) * 0.25);
$gameStats = [
    'slots' => $db->fetchOne("SELECT COUNT(*) AS wins FROM game_results WHERE user_id = ? AND game_type_id = 3 AND outcome = 'win'", [$userId])['wins'] ?? 0,
    'roulette' => $db->fetchOne("SELECT COUNT(*) AS wins FROM game_results WHERE user_id = ? AND game_type_id = 4 AND outcome = 'win'", [$userId])['wins'] ?? 0,
    'cards' => $db->fetchOne("SELECT COUNT(*) AS wins FROM game_results WHERE user_id = ? AND game_type_id = 2 AND outcome = 'win'", [$userId])['wins'] ?? 0,
    'jackpot' => $db->fetchOne("SELECT COUNT(*) AS wins FROM game_results WHERE user_id = ? AND game_type_id = 5 AND outcome = 'win'", [$userId])['wins'] ?? 0
];
$totalGameWins = array_sum($gameStats);
$rank = $db->fetchOne("SELECT COUNT(*) + 1 AS rank FROM users WHERE rawr_balance > ?", [$user['rawr_balance']])['rank'] ?? 1;
$kycProgressStatus = [
    'personalInfo' => !empty($kycData['full_name']) && !empty($kycData['date_of_birth']),
    'emailVerified' => true,
    'walletConnected' => !empty($user['wallet_address']),
    'idDocument' => !empty($kycData['id_image_path']),
    'selfie' => !empty($kycData['selfie_image_path'])
];
$completedKycStepsCount = count(array_filter($kycProgressStatus));
$kycOverallProgress = ($completedKycStepsCount / count($kycProgressStatus)) * 100;
if ($user['kyc_status'] === 'approved') $kycOverallProgress = 100;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RAWR Casino - My Profile</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/RAWR/public/css/style.css">
    <style>

        /* Profile Header - Updated */
        .profile-header {
            padding: 120px 1rem 60px;
            position: relative;
            text-align: center;
            background: linear-gradient(135deg, rgba(26, 26, 26, 0.8), rgba(45, 24, 16, 0.8));
            border-bottom: 1px solid var(--glass-border);
            margin-bottom: 2rem;
        }

        .avatar-container {
            position: relative;
            display: inline-block;
            margin: 0 auto 20px;
        }

        .profile-avatar {
            width: 160px;
            height: 160px;
            border-radius: 50%;
            border: 3px solid var(--primary);
            object-fit: cover;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
            overflow: hidden;
        }

        .edit-avatar-btn {
            position: absolute;
            bottom: 10px;
            right: 10px;
            width: 35px;
            height: 35px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
        }
                .form-row .form-group {
            margin-bottom: 1rem;
        }
        .form-group .form-row {
            margin-top: 0.5rem;
        }

        .user-info {
            margin-top: 1rem;
        }

        .user-name {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--primary);
        }

        .user-email {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-top: 0.3rem;
        }

        .member-since {
            color: var(--text-muted);
            font-size: 0.8rem;
            margin-top: 0.5rem;
        }

        .verification-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(0, 128, 0, 0.2);
            color: #0f0;
            padding: 0.3rem 1rem;
            border-radius: 30px;
            margin-top: 0.5rem;
            font-size: 0.8rem;
            font-weight: 500;
        }

        /* Profile Content */
        .profile-content {
            padding: 0 1rem 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .section-title {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid rgba(255, 215, 0, 0.1);
            padding-bottom: 1rem;
            flex-wrap: wrap;
        }

        .tab-btn {
            background: transparent;
            border: none;
            padding: 0.7rem 1.5rem;
            color: var(--text-muted);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            border-radius: 30px;
            font-size: 0.85rem;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #1a1a1a;
            font-weight: 600;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            border: 1px solid var(--glass-border);
            position: relative;
            overflow: hidden;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3), var(--glow);
        }

        .stat-icon {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .stat-title {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
        }

        /* Settings Form */
        .settings-form {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            border: 1px solid var(--glass-border);
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-light);
            font-weight: 500;
        }

        .form-input {
            width: 100%;
            padding: 0.8rem 1rem;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius);
            color: var(--text-light);
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(255, 215, 0, 0.2);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .btn {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #1a1a1a;
            border: none;
            border-radius: 50px;
            padding: 0.8rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(255, 215, 0, 0.4);
        }

        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .btn-outline:hover {
            background: rgba(255, 215, 0, 0.1);
        }

        /* KYC Verification - Updated */
        .kyc-section {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            border: 1px solid var(--glass-border);
        }

        .kyc-status {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: rgba(0, 0, 0, 0.3);
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
        }

        .kyc-icon {
            width: 50px;
            height: 50px;
            background: rgba(255, 215, 0, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--primary);
        }

        .kyc-info {
            flex: 1;
        }

        .kyc-title {
            font-weight: 600;
            margin-bottom: 0.3rem;
            color: var(--text-light);
        }

        .kyc-description {
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        .kyc-steps {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .kyc-step {
            display: flex;
            gap: 1rem;
            padding: 1.5rem;
            background: rgba(0, 0, 0, 0.2);
            border-radius: var(--border-radius);
            border: 1px solid var(--glass-border);
            position: relative;
            overflow: hidden;
        }

        .step-number {
            width: 35px;
            height: 35px;
            background: rgba(255, 215, 0, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: var(--primary);
            flex-shrink: 0;
        }

        .step-content {
            flex: 1;
        }

        .step-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--primary);
        }

        .step-description {
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        .upload-area {
            border: 2px dashed var(--glass-border);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            text-align: center;
            margin: 1rem 0;
            cursor: pointer;
            transition: var(--transition);
            background: rgba(0, 0, 0, 0.1);
        }

        .upload-area:hover {
            border-color: var(--primary);
            background: rgba(255, 215, 0, 0.05);
        }

        .upload-icon {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .upload-text {
            margin-bottom: 1rem;
            color: var(--text-muted);
        }

        .file-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .file-item {
            width: 120px;
            height: 120px;
            border-radius: var(--border-radius);
            overflow: hidden;
            position: relative;
            border: 1px solid var(--glass-border);
        }

        .file-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .file-remove {
            position: absolute;
            top: 5px;
            right: 5px;
            width: 25px;
            height: 25px;
            background: rgba(0, 0, 0, 0.7);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--accent);
        }

        .kyc-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }

        .kyc-progress {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-top: 1.5rem;
            padding: 1rem;
            background: rgba(0, 0, 0, 0.3);
            border-radius: var(--border-radius);
            flex-wrap: wrap;
        }

        .progress-bar {
            flex: 1;
            height: 10px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 5px;
            overflow: hidden;
            min-width: 150px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            border-radius: 5px;
            width: 0%;
            transition: width 0.5s ease;
        }

        /* Verification Code */
        .verification-code {
            display: flex;
            gap: 0.5rem;
            margin: 1rem 0;
        }

        .code-input {
            width: 45px;
            height: 45px;
            text-align: center;
            font-size: 1.2rem;
            border-radius: 8px;
            border: 1px solid var(--glass-border);
            background: rgba(0, 0, 0, 0.3);
            color: var(--text-light);
        }

        /* Wallet Connection */
        .wallet-connection {
            padding: 1.5rem;
            background: rgba(0, 0, 0, 0.2);
            border-radius: var(--border-radius);
            border: 1px solid var(--glass-border);
            text-align: center;
            margin: 1rem 0;
        }

        .wallet-icon {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            overflow-y: auto;
            padding: 2rem;
        }

        .modal-content {
            background: var(--card-bg);
            max-width: 800px;
            margin: 2rem auto;
            border-radius: var(--border-radius);
            border: 1px solid var(--primary);
            overflow: hidden;
            position: relative;
        }

        .modal-header {
            padding: 1.5rem;
            background: rgba(0, 0, 0, 0.3);
            border-bottom: 1px solid var(--glass-border);
        }

        .modal-title {
            font-size: 1.5rem;
            color: var(--primary);
        }

        .modal-body {
            padding: 1.5rem;
            max-height: 70vh;
            overflow-y: auto;
        }

        .review-section {
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--glass-border);
        }

        .review-title {
            font-size: 1.1rem;
            color: var(--primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .review-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .review-item {
            margin-bottom: 0.5rem;
        }

        .review-label {
            font-weight: 500;
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .review-value {
            font-weight: 600;
            color: var(--text-light);
        }

        .review-images {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 1rem;
        }

        .review-image {
            width: 150px;
            height: 150px;
            border-radius: var(--border-radius);
            overflow: hidden;
            border: 1px solid var(--glass-border);
        }

        .review-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .agreement {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin: 1.5rem 0;
            padding: 1rem;
            background: rgba(0, 0, 0, 0.2);
            border-radius: var(--border-radius);
            color: var(--text-muted);
        }

        .agreement input[type="checkbox"] {
            margin-top: 4px;
        }

        .agreement a {
            color: var(--primary);
            text-decoration: none;
        }

        .agreement a:hover {
            text-decoration: underline;
        }

        .modal-footer {
            padding: 1.5rem;
            background: rgba(0, 0, 0, 0.3);
            border-top: 1px solid var(--glass-border);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .close-modal {
            position: absolute;
            top: 15px;
            right: 15px;
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-size: 1.5rem;
            cursor: pointer;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        /* Mobile Styles */
        @media (max-width: 768px) {
            .profile-header {
                padding: 80px 1rem 30px;
            }

            .profile-avatar {
                width: 100px;
                height: 100px;
                font-size: 2.5rem;
            }

            .user-name {
                font-size: 1.5rem;
            }

            .tabs {
                gap: 0.3rem;
                overflow-x: auto;
                padding-bottom: 10px;
            }

            .tab-btn {
                padding: 0.5rem 1rem;
                font-size: 0.75rem;
                white-space: nowrap;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .verification-code {
                flex-wrap: wrap;
                justify-content: center;
            }

            .kyc-step {
                padding: 1rem;
                flex-direction: column;
            }

            .kyc-actions {
                flex-direction: column;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .modal-content {
                margin: 1rem auto;
                width: 95%;
            }

            .review-grid {
                grid-template-columns: 1fr;
            }

            .review-images {
                justify-content: center;
            }
        }

        /* Tablet Styles */
        @media (min-width: 576px) and (max-width: 768px) {
            .profile-header {
                padding: 100px 1rem 50px;
            }

            .profile-avatar {
                width: 140px;
                height: 140px;
            }

            .kyc-step {
                flex-direction: row;
            }
        }

        /* FIX: Consistent spacing for form groups in settings and KYC steps */
        .settings-form .form-group,
        .kyc-step .form-group {
            margin-bottom: 1.5rem;
        }
        /* FIX: Target nested form-groups for consistent spacing in complex layouts like the address fields */
        .form-group .form-row {
            margin-top: 1rem;
        }
        .form-group .form-row .form-group {
            margin-bottom: 0;
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
        <a href="daily.php" class="sidebar-item">
            <i class="fas fa-gift"></i>
            <span>Daily Rewards</span>
        </a>
        <a href="profile.php" class="sidebar-item active">
            <i class="fas fa-user"></i>
            <span>Profile</span>
        </a>
        <a href="logout.php" class="sidebar-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </aside>

    <section class="profile-header">
        <div class="avatar-container">
            <div class="profile-avatar" id="profileAvatar">
                <?php if (!empty($user['avatar_path'])): ?>
                    <img src="<?= htmlspecialchars($user['avatar_path']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                <?php else: ?>
                    <span>🦁</span>
                <?php endif; ?>
            </div>
            <div class="edit-avatar-btn" id="editAvatarBtn">
                <i class="fas fa-pencil-alt"></i>
            </div>
        </div>

        <div class="user-info">
            <h1 class="user-name" id="profileUsername"><?= htmlspecialchars($user['username']) ?></h1>
            <div class="user-email"><?= htmlspecialchars($user['email']) ?></div>
            <div class="member-since">Member since: <?= date('M Y', strtotime($user['created_at'])) ?></div>
            <?php if($user['kyc_status'] === 'approved'): ?>
            <div class="verification-badge">
                <i class="fas fa-shield-alt"></i> Verified Account
            </div>
            <?php elseif($user['kyc_status'] === 'pending'): ?>
            <div class="verification-badge" style="background: rgba(255, 165, 0, 0.2); color: #FFA500;">
                <i class="fas fa-hourglass-half"></i> KYC Pending
            </div>
            <?php elseif($user['kyc_status'] === 'rejected'): ?>
            <div class="verification-badge" style="background: rgba(255, 0, 0, 0.2); color: #FF6347;">
                <i class="fas fa-times-circle"></i> KYC Not Verified
            </div>
            <?php if (!empty($kycData['rejection_reason'])): ?>
                <div class="rejection-reason" style="color: #FF6347; background: rgba(255,0,0,0.08); padding: 0.75rem 1rem; border-radius: 6px; margin-top: 0.5rem;">
                    <strong>Reason for Rejection:</strong> <?= htmlspecialchars($kycData['rejection_reason']) ?>
                </div>
            <?php endif; ?>
            <?php else: // 'not_submitted' ?>
            <div class="verification-badge" style="background: rgba(255, 0, 0, 0.2); color: #FF6347;">
                <i class="fas fa-times-circle"></i> KYC Not Verified
            </div>
            <?php endif; ?>
             <div class="referral-code-display" style="margin-top: 1rem; font-size: 0.9rem; color: var(--text-muted);">
                Your Referral Code: <span style="font-weight: 600; color: var(--primary);"><?= htmlspecialchars($user['referral_code']) ?></span>
            </div>
        </div>
    </section>

    <section class="profile-content">
        <div class="tabs">
            <button class="tab-btn active" data-tab="overview">Overview</button>
            <button class="tab-btn" data-tab="settings">Settings</button>
            <button class="tab-btn" data-tab="kyc">KYC Verification</button>
        </div>

        <div class="tab-content active" id="overviewTab">
            <h2 class="section-title">
                <i class="fas fa-chart-line"></i>
                My Stats
            </h2>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="stat-title">Total RAWR Balance</div>
                    <div class="stat-value" id="overviewRawrBalance"><?= number_format((float)$user['rawr_balance'], 2) ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-ticket-alt"></i>
                    </div>
                    <div class="stat-title">Total Tickets</div>
                    <div class="stat-value" id="overviewTicketsBalance"><?= (int)$user['ticket_balance'] ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="stat-title">Leaderboard Rank</div>
                    <div class="stat-value">#<span id="overviewRank"><?= (int)$rank ?></span></div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-gem"></i>
                    </div>
                    <div class="stat-title">Total Wins</div>
                    <div class="stat-value" id="overviewTotalWins"><?= (int)$totalGameWins ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-title">Current Login Streak</div>
                    <div class="stat-value" id="currentLoginStreak"><?= (int)$currentStreak ?> Days</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-fire"></i>
                    </div>
                    <div class="stat-title">Longest Login Streak</div>
                    <div class="stat-value" id="longestLoginStreak"><?= (int)$longestStreak ?> Days</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-mining"></i>
                    </div>
                    <div class="stat-title">Total RAWR Mined</div>
                    <div class="stat-value" id="totalRawrMined"><?= number_format((float)$miningData['total_mined'] ?? 0, 4) ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <div class="stat-title">Mining Boost Level</div>
                    <div class="stat-value" id="miningBoostLevel">x<?= number_format($miningMultiplier, 2) ?></div>
                </div>
            </div>

            <h2 class="section-title">
                <i class="fas fa-gamepad"></i>
                Game Statistics
            </h2>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-dice"></i>
                    </div>
                    <div class="stat-title">Slot Wins</div>
                    <div class="stat-value"><?= (int)$gameStats['slots'] ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-circle"></i>
                    </div>
                    <div class="stat-title">Roulette Wins</div>
                    <div class="stat-value"><?= (int)$gameStats['roulette'] ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-cards"></i>
                    </div>
                    <div class="stat-title">Card Wins</div>
                    <div class="stat-value"><?= (int)$gameStats['cards'] ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-paw"></i>
                    </div>
                    <div class="stat-title">Jungle Jackpot Wins</div>
                    <div class="stat-value"><?= (int)$gameStats['jackpot'] ?></div>
                </div>
            </div>
        </div>

        <div class="tab-content" id="settingsTab">
            <h2 class="section-title">
                <i class="fas fa-user-cog"></i>
                Profile Settings
            </h2>

            <div class="settings-form">
                <div class="form-group">
                    <label class="form-label">Profile Picture</label>
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div class="profile-avatar" id="settingsAvatarPreview" style="width: 80px; height: 80px; font-size: 2rem;">
                            <?php if (!empty($user['avatar_path'])): ?>
                                <img src="<?= htmlspecialchars($user['avatar_path']) ?>" alt="Profile Avatar" style="width:100%; height:100%; object-fit:cover; border-radius:50%;">
                            <?php else: ?>
                                <span>🦁</span>
                            <?php endif; ?>
                        </div>
                        <button class="btn btn-outline" id="uploadAvatarBtn">
                            <i class="fas fa-upload"></i> Upload New Avatar
                        </button>
                        <input type="file" id="avatarFileInput" accept="image/*" style="display: none;">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="settingsUsername">Username</label>
                        <input type="text" class="form-input" id="settingsUsername" value="<?= htmlspecialchars($user['username']) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="settingsEmail">Email Address</label>
                        <input type="email" class="form-input" id="settingsEmail" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="settingsBio">Bio</label>
                    <textarea class="form-input" id="settingsBio" rows="3"><?= htmlspecialchars($user['bio'] ?? 'RAWR Casino enthusiast!') ?></textarea>
                </div>

                <button class="btn" id="saveProfileBtn">
                    <i class="fas fa-save"></i> Save Changes
                </button>


                <h3 class="section-title" style="font-size: 1.2rem; margin: 2rem 0 1rem;">
                    <i class="fas fa-shield-alt"></i>
                    Security Settings
                </h3>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="currentPassword">Current Password</label>
                        <input type="password" class="form-input" id="currentPassword" placeholder="Enter current password">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="newPassword">New Password</label>
                        <input type="password" class="form-input" id="newPassword" placeholder="Enter new password">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirmNewPassword">Confirm New Password</label>
                    <input type="password" class="form-input" id="confirmNewPassword" placeholder="Confirm new password">
                </div>

                <button class="btn" id="changePasswordBtn">
                    <i class="fas fa-key"></i> Change Password
                </button>
            </div>
        </div>

        <div class="tab-content" id="kycTab">
            <div class="kyc-section">
                <div class="kyc-status">
                    <div class="kyc-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="kyc-info">
                        <div class="kyc-title">Identity Verification (KYC)</div>
                        <div class="kyc-description">Complete verification to unlock all features and higher withdrawal limits</div>
                    </div>
                </div>

                <div class="kyc-progress">
                    <div>Verification Progress:</div>
                    <div class="progress-bar">
                        <div class="progress-fill" id="kycProgress" style="width: <?= $kycOverallProgress ?>%"></div>
                    </div>
                    <div id="progressPercentage"><?= round($kycOverallProgress) ?>%</div>
                </div>

                <div class="kyc-steps">
                    <div class="kyc-step">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <div class="step-title">Personal Information</div>
                            <div class="step-description">Provide your full name, date of birth, and residential address</div>

                            <div class="form-group">
                                <label class="form-label" for="fullName">Full Name</label>
                                <input type="text" class="form-input" id="fullName" placeholder="As shown on your ID" value="<?= htmlspecialchars($kycData['full_name'] ?? '') ?>">
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="dob">Date of Birth</label>
                                    <input type="date" class="form-input" id="dob" value="<?= htmlspecialchars($kycData['date_of_birth'] ?? '') ?>">
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="country">Country</label>
                                    <select class="form-input" id="country">
                                        <option value="">Select Country</option>
                                        <?php
                                        $countries = [
                                            'af' => 'Afghanistan', 'ax' => 'Åland Islands', 'al' => 'Albania', 'dz' => 'Algeria', 'as' => 'American Samoa',
                                            'ad' => 'Andorra', 'ao' => 'Angola', 'ai' => 'Anguilla', 'aq' => 'Antarctica', 'ag' => 'Antigua and Barbuda',
                                            'ar' => 'Argentina', 'am' => 'Armenia', 'aw' => 'Aruba', 'au' => 'Australia', 'at' => 'Austria',
                                            'az' => 'Azerbaijan', 'bs' => 'Bahamas', 'bh' => 'Bahrain', 'bd' => 'Bangladesh', 'bb' => 'Barbados',
                                            'by' => 'Belarus', 'be' => 'Belgium', 'bz' => 'Belize', 'bj' => 'Benin', 'bm' => 'Bermuda',
                                            'bt' => 'Bhutan', 'bo' => 'Bolivia', 'bq' => 'Bonaire', 'ba' => 'Bosnia and Herzegovina', 'bw' => 'Botswana',
                                            'bv' => 'Bouvet Island', 'br' => 'Brazil', 'io' => 'British Indian Ocean Territory', 'bn' => 'Brunei Darussalam',
                                            'va' => 'Holy See', 'hn' => 'Honduras', 'hk' => 'Hong Kong', 'hu' => 'Hungary', 'is' => 'Iceland',
                                            'in' => 'India', 'id' => 'Indonesia', 'ir' => 'Iran', 'iq' => 'Iraq', 'ie' => 'Ireland',
                                            'im' => 'Isle of Man', 'il' => 'Israel', 'it' => 'Italy', 'jm' => 'Jamaica', 'jp' => 'Japan',
                                            'je' => 'Jersey', 'jo' => 'Jordan', 'kz' => 'Kazakhstan', 'ke' => 'Kenya', 'ki' => 'Kiribati',
                                            'kp' => 'North Korea', 'kr' => 'South Korea', 'kw' => 'Kuwait', 'kg' => 'Kyrgyzstan', 'la' => 'Lao PDR',
                                            'lv' => 'Latvia', 'lb' => 'Lebanon', 'ls' => 'Lesotho', 'lr' => 'Liberia', 'ly' => 'Libya',
                                            'li' => 'Liechtenstein', 'lt' => 'Lithuania', 'lu' => 'Luxembourg', 'mo' => 'Macao', 'mg' => 'Madagascar',
                                            'mw' => 'Malawi', 'my' => 'Malaysia', 'mv' => 'Maldives', 'ml' => 'Mali', 'mt' => 'Malta',
                                            'mh' => 'Marshall Islands', 'mq' => 'Martinique', 'mr' => 'Mauritania', 'mu' => 'Mauritius', 'yt' => 'Mayotte',
                                            'mx' => 'Mexico', 'fm' => 'Micronesia', 'md' => 'Moldova', 'mc' => 'Monaco', 'mn' => 'Mongolia',
                                            'me' => 'Montenegro', 'ms' => 'Montserrat', 'ma' => 'Morocco', 'mz' => 'Mozambique', 'mm' => 'Myanmar',
                                            'na' => 'Namibia', 'nr' => 'Nauru', 'np' => 'Nepal', 'nl' => 'Netherlands', 'nc' => 'New Caledonia',
                                            'nz' => 'New Zealand', 'ni' => 'Nicaragua', 'ne' => 'Niger', 'ng' => 'Nigeria', 'nu' => 'Niue',
                                            'nf' => 'Norfolk Island', 'mk' => 'North Macedonia', 'mp' => 'Northern Mariana Islands', 'no' => 'Norway',
                                            'om' => 'Oman', 'pk' => 'Pakistan', 'pw' => 'Palau', 'ps' => 'Palestine', 'pa' => 'Panama',
                                            'pg' => 'Papua New Guinea', 'py' => 'Paraguay', 'pe' => 'Peru', 'ph' => 'Philippines', 'pn' => 'Pitcairn',
                                            'pl' => 'Poland', 'pt' => 'Portugal', 'pr' => 'Puerto Rico', 'qa' => 'Qatar', 're' => 'Réunion',
                                            'ro' => 'Romania', 'ru' => 'Russia', 'rw' => 'Rwanda', 'bl' => 'Saint Barthélemy', 'sh' => 'Saint Helena',
                                            'kn' => 'Saint Kitts and Nevis', 'lc' => 'Saint Lucia', 'mf' => 'Saint Martin', 'pm' => 'Saint Pierre and Miquelon',
                                            'vc' => 'Saint Vincent and Grenadines', 'ws' => 'Samoa', 'sm' => 'San Marino', 'st' => 'Sao Tome and Principe',
                                            'sa' => 'Saudi Arabia', 'sn' => 'Senegal', 'rs' => 'Serbia', 'sc' => 'Seychelles', 'sl' => 'Sierra Leone',
                                            'sg' => 'Singapore', 'sx' => 'Sint Maarten', 'sk' => 'Slovakia', 'si' => 'Slovenia', 'sb' => 'Solomon Islands',
                                            'so' => 'Somalia', 'za' => 'South Africa', 'gs' => 'South Georgia and South Sandwich Islands', 'ss' => 'South Sudan',
                                            'es' => 'Spain', 'lk' => 'Sri Lanka', 'sd' => 'Sudan', 'sr' => 'Suriname', 'sj' => 'Svalbard and Jan Mayen',
                                            'se' => 'Sweden', 'ch' => 'Switzerland', 'sy' => 'Syria', 'tw' => 'Taiwan', 'tj' => 'Tajikistan',
                                            'tz' => 'Tanzania', 'th' => 'Thailand', 'tl' => 'Timor-Leste', 'tg' => 'Togo', 'tk' => 'Tokelau',
                                            'to' => 'Tonga', 'tt' => 'Trinidad and Tobago', 'tn' => 'Tunisia', 'tr' => 'Turkey', 'tm' => 'Turkmenistan',
                                            'tc' => 'Turks and Caicos Islands', 'tv' => 'Tuvalu', 'ug' => 'Uganda', 'ua' => 'Ukraine', 'ae' => 'United Arab Emirates',
                                            'gb' => 'United Kingdom', 'us' => 'United States', 'um' => 'US Minor Outlying Islands', 'uy' => 'Uruguay',
                                            'uz' => 'Uzbekistan', 'vu' => 'Vanuatu', 've' => 'Venezuela', 'vn' => 'Viet Nam', 'vg' => 'British Virgin Islands',
                                            'vi' => 'US Virgin Islands', 'wf' => 'Wallis and Futuna', 'eh' => 'Western Sahara', 'ye' => 'Yemen',
                                            'zm' => 'Zambia', 'zw' => 'Zimbabwe'
                                        ];
                                        foreach ($countries as $code => $name) {
                                            $selected = (isset($kycData['country']) && $kycData['country'] === $code) ? 'selected' : '';
                                            echo "<option value=\"$code\" $selected>" . htmlspecialchars($name) . "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="contactNumber">Contact Number</label>
                                    <input type="tel" class="form-input" id="contactNumber" placeholder="+1 (123) 456-7890" value="<?= htmlspecialchars($kycData['contact_number'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="address">Residential Address</label>
                                <input type="text" class="form-input" id="address" placeholder="Street Address" value="<?= htmlspecialchars($kycData['address'] ?? '') ?>">
                                <input type="text" class="form-input" id="city" style="margin-top: 0.5rem;" placeholder="City" value="<?= htmlspecialchars($kycData['city'] ?? '') ?>">
                                <div class="form-row">
                                    <input type="text" class="form-input" id="stateProvince" placeholder="State/Province" value="<?= htmlspecialchars($kycData['state_province'] ?? '') ?>">
                                    <input type="text" class="form-input" id="postalCode" placeholder="Postal Code" value="<?= htmlspecialchars($kycData['postal_code'] ?? '') ?>">
                                </div>
                            </div>

                            <button class="btn" id="savePersonalInfoBtn">
                                <i class="fas fa-save"></i> Save Information
                            </button>
                        </div>
                    </div>

                    <div class="kyc-step">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <div class="step-title">Email Verification</div>
                            <div class="step-description">Verify your email address to secure your account</div>

                            <div class="form-group">
                                <label class="form-label" for="verifyEmail">Email Address</label>
                                <input type="email" class="form-input" id="verifyEmail" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                            </div>
                            <?php if (!($kycData['email_verified'] ?? false)): // Check if email is not yet verified in KYC data ?>
                            <button class="btn" id="sendCodeBtn">
                                <i class="fas fa-paper-plane"></i> Send Verification Code
                            </button>

                            <div id="codeSection" style="display: none; margin-top: 1.5rem;">
                                <div class="form-group">
                                    <label class="form-label">Enter Verification Code</label>
                                    <div class="verification-code">
                                        <input type="text" class="code-input" maxlength="1" data-index="0">
                                        <input type="text" class="code-input" maxlength="1" data-index="1">
                                        <input type="text" class="code-input" maxlength="1" data-index="2">
                                        <input type="text" class="code-input" maxlength="1" data-index="3">
                                        <input type="text" class="code-input" maxlength="1" data-index="4">
                                        <input type="text" class="code-input" maxlength="1" data-index="5">
                                    </div>
                                </div>

                                <button class="btn" id="verifyEmailCodeBtn">
                                    <i class="fas fa-check"></i> Verify Code
                                </button>
                            </div>
                            <?php else: ?>
                            <div class="verification-badge" style="background: rgba(0, 128, 0, 0.2); color: #0f0; padding: 0.5rem 1rem; border-radius: 5px;">
                                <i class="fas fa-check-circle"></i> Email Verified
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="kyc-step">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <div class="step-title">Connect Wallet</div>
                            <div class="step-description">Connect your MetaMask wallet to your account</div>
                            <?php if (empty($user['wallet_address'])): ?>
                            <div class="wallet-connection">
                                <div class="wallet-icon">
                                    <i class="fab fa-ethereum"></i>
                                </div>
                                <p>Connect your MetaMask wallet to enable crypto transactions and withdrawals</p>
                                <button class="btn" id="connectWalletBtn">
                                    <i class="fab fa-metamask"></i> Connect MetaMask
                                </button>
                            </div>

                            <div id="walletInfo" style="display: none; margin-top: 1.5rem;">
                                <div class="form-group">
                                    <label class="form-label" for="walletAddress">Connected Wallet Address</label>
                                    <input type="text" class="form-input" id="walletAddress" value="" readonly>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="verification-badge" style="background: rgba(0, 128, 0, 0.2); color: #0f0; padding: 0.5rem 1rem; border-radius: 5px;">
                                <i class="fas fa-check-circle"></i> Wallet Connected: <span style="font-weight: bold;"><?= substr(htmlspecialchars($user['wallet_address']), 0, 6) . '...' . substr(htmlspecialchars($user['wallet_address']), -4) ?></span>
                            </div>
                            <div class="form-group" style="margin-top: 1rem;">
                                <label class="form-label" for="walletAddress">Connected Wallet Address</label>
                                <input type="text" class="form-input" id="walletAddress" value="<?= htmlspecialchars($user['wallet_address']) ?>" readonly>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="kyc-step">
                        <div class="step-number">4</div>
                        <div class="step-content">
                            <div class="step-title">ID Document</div>
                            <div class="step-description">Upload a government-issued ID (Passport, Driver's License, or National ID)</div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="idType">ID Type</label>
                                    <select class="form-input" id="idType">
                                        <option value="">Select ID Type</option>
                                        <option value="passport" <?= (isset($kycData['id_type']) && $kycData['id_type'] === 'passport') ? 'selected' : '' ?>>Passport</option>
                                        <option value="driver" <?= (isset($kycData['id_type']) && $kycData['id_type'] === 'driver') ? 'selected' : '' ?>>Driver's License</option>
                                        <option value="national" <?= (isset($kycData['id_type']) && $kycData['id_type'] === 'national') ? 'selected' : '' ?>>National ID</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="idNumber">ID Number</label>
                                    <input type="text" class="form-input" id="idNumber" placeholder="Enter ID number" value="<?= htmlspecialchars($kycData['id_number'] ?? '') ?>">
                                </div>
                            </div>

                            <h4 style="margin: 1.5rem 0 1rem; color: var(--primary);">Front of ID</h4>
                            <div class="upload-area" id="idFrontUploadArea">
                                <div class="upload-icon">
                                    <i class="fas fa-id-card"></i>
                                </div>
                                <div class="upload-text">Drag & drop front of ID here or click to browse</div>
                                <div class="btn btn-outline">Select File</div>
                                <input type="file" id="idFrontDocument" accept="image/*" style="display: none;">
                            </div>

                            <div class="file-preview" id="idFrontPreview">
                                <?php if (!empty($kycData['id_image_path'])): ?>
                                <div class="file-item">
                                    <img src="<?= htmlspecialchars(BASE_URL . '/uploads/kyc_docs/' . $kycData['id_image_path']) ?>" alt="ID Front">
                                    <div class="file-remove" onclick="this.parentElement.remove();"><i class="fas fa-times"></i></div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <h4 style="margin: 1.5rem 0 1rem; color: var(--primary);">Back of ID (Optional)</h4>
                            <div class="upload-area" id="idBackUploadArea">
                                <div class="upload-icon">
                                    <i class="fas fa-id-card-alt"></i>
                                </div>
                                <div class="upload-text">Drag & drop back of ID here or click to browse</div>
                                <div class="btn btn-outline">Select File</div>
                                <input type="file" id="idBackDocument" accept="image/*" style="display: none;">
                            </div>

                            <div class="file-preview" id="idBackPreview">
                                <?php if (!empty($kycData['id_image_back_path'] ?? '')): // Assuming a column for back of ID ?>
                                <div class="file-item">
                                    <img src="<?= htmlspecialchars(BASE_URL . '/uploads/kyc_docs/' . $kycData['id_image_back_path']) ?>" alt="ID Back">
                                    <div class="file-remove" onclick="this.parentElement.remove();"><i class="fas fa-times"></i></div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <button class="btn" id="saveIdDocumentBtn">
                                                               <i class="fas fa-upload"></i> Submit ID Document
                            </button>
                        </div>
                    </div>

                    <div class="kyc-step">
                        <div class="step-number">5</div>
                        <div class="step-content">
                            <div class="step-title">Selfie Verification</div>
                            <div class="step-description">Take a selfie holding your ID document</div>

                            <div class="upload-area" id="selfieUploadArea">
                                <div class="upload-icon">
                                    <i class="fas fa-camera"></i>
                                </div>
                                <div class="upload-text">Upload a selfie with your ID document</div>
                                <div class="btn btn-outline">Take Photo</div>
                                <input type="file" id="selfieDocument" accept="image/*" style="display: none;">
                            </div>

                            <div class="file-preview" id="selfiePreview">
                                <?php if (!empty($kycData['selfie_image_path'] ?? '')): // Assuming a column for selfie path ?>
                                <div class="file-item">
                                    <img src="<?= htmlspecialchars(BASE_URL . 'uploads/kyc_docs/' . $kycData['selfie_image_path']) ?>" alt="Selfie with ID">
                                    <div class="file-remove" onclick="this.parentElement.remove();"><i class="fas fa-times"></i></div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <button class="btn" id="saveSelfieBtn">
                                <i class="fas fa-upload"></i> Submit Selfie
                            </button>
                        </div>
                    </div>

                    <div class="kyc-step">
                        <div class="step-number">6</div>
                        <div class="step-content">
                            <div class="step-title">Review and Submit</div>
                            <div class="step-description">Review your information and submit for verification</div>

                            <p style="margin: 1.5rem 0;">Please review all information before submitting for verification.</p>

                            <div class="kyc-actions">
                                <button class="btn" id="reviewKycBtn">
                                    <i class="fas fa-eye"></i> Review Information
                                </button>

                                <button class="btn" id="submitKycBtn" <?= ($user['kyc_status'] === 'approved' || $user['kyc_status'] === 'pending') ? 'disabled' : '' ?>>
                                    <i class="fas fa-paper-plane"></i> Submit for Verification
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="modal" id="kycReviewModal">
        <div class="modal-content">
            <button class="close-modal" id="closeModal">&times;</button>
            <div class="modal-header">
                <h2 class="modal-title"><i class="fas fa-file-alt"></i> KYC Verification Review</h2>
            </div>
            <div class="modal-body">
                <div class="review-section">
                    <h3 class="review-title"><i class="fas fa-user"></i> Personal Information</h3>
                    <div class="review-grid">
                        <div class="review-item">
                            <div class="review-label">Full Name</div>
                            <div class="review-value" id="reviewFullName"></div>
                        </div>
                        <div class="review-item">
                            <div class="review-label">Date of Birth</div>
                            <div class="review-value" id="reviewDob"></div>
                        </div>
                        <div class="review-item">
                            <div class="review-label">Country</div>
                            <div class="review-value" id="reviewCountry"></div>
                        </div>
                        <div class="review-item">
                            <div class="review-label">Contact Number</div>
                            <div class="review-value" id="reviewContact"></div>
                        </div>
                        <div class="review-item">
                            <div class="review-label">Address</div>
                            <div class="review-value" id="reviewAddress"></div>
                        </div>
                        <div class="review-item">
                            <div class="review-label">City</div>
                            <div class="review-value" id="reviewCity"></div>
                        </div>
                        <div class="review-item">
                            <div class="review-label">State/Province</div>
                            <div class="review-value" id="reviewStateProvince"></div>
                        </div>
                        <div class="review-item">
                            <div class="review-label">Postal Code</div>
                            <div class="review-value" id="reviewPostalCode"></div>
                        </div>
                    </div>
                </div>

                <div class="review-section">
                    <h3 class="review-title"><i class="fas fa-id-card"></i> ID Document</h3>
                    <div class="review-grid">
                        <div class="review-item">
                            <div class="review-label">ID Type</div>
                            <div class="review-value" id="reviewIdType"></div>
                        </div>
                        <div class="review-item">
                            <div class="review-label">ID Number</div>
                            <div class="review-value" id="reviewIdNumber"></div>
                        </div>
                    </div>
                    <div class="review-images">
                        <div class="review-image">
                            <img src="" id="reviewIdFront" alt="ID Front">
                        </div>
                        <div class="review-image">
                            <img src="" id="reviewIdBack" alt="ID Back">
                        </div>
                    </div>
                </div>

                <div class="review-section">
                    <h3 class="review-title"><i class="fas fa-camera"></i> Selfie Verification</h3>
                    <div class="review-images">
                        <div class="review-image">
                            <img src="" id="reviewSelfie" alt="Selfie with ID">
                        </div>
                    </div>
                </div>

                <div class="review-section">
                    <h3 class="review-title"><i class="fab fa-ethereum"></i> Wallet Information</h3>
                    <div class="review-item">
                        <div class="review-label">Wallet Address</div>
                        <div class="review-value" id="reviewWallet"></div>
                    </div>
                </div>

                <div class="agreement">
                    <input type="checkbox" id="agreeTerms">
                    <label for="agreeTerms">
                        I confirm that all information provided is accurate and complete. I agree to the
                        <a href="#" style="color: var(--primary);">Terms of Service</a> and
                        <a href="#" style="color: var(--primary);">Privacy Policy</a>. I understand that
                        my KYC application will be reviewed by the RAWR Casino team, and this process may take
                        1-3 business days.
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" id="cancelReviewBtn">Cancel</button>
                <button class="btn" id="confirmSubmitBtn" disabled>Submit for Verification</button>
            </div>
        </div>
    </div>

    <div class="modal" id="kycSubmittedModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title"><i class="fas fa-check-circle"></i> KYC Submitted Successfully!</h2>
            </div>
            <div class="modal-body">
                <div style="text-align: center; padding: 2rem;">
                    <div style="font-size: 5rem; color: var(--primary); margin-bottom: 1.5rem;">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <h3 style="font-size: 1.8rem; margin-bottom: 1rem; color: var(--primary);">Verification in Progress</h3>
                    <p>Your KYC application has been submitted successfully. Our team will review your information within 1-3 business days.</p>
                    <p>You'll receive an email notification once your verification is complete.</p>
                    <p style="margin-top: 2rem;">
                        <button class="btn" id="closeSubmittedModal">
                            <i class="fas fa-check"></i> Continue to Casino
                        </button>
                    </p>
                </div>
            </div>
        </div>
    </div>

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
                    <li><a href="#">Tutorials</ali>
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

                                    <script>
    // PHP data injected into JavaScript
    const phpData = {
        userId: <?= (int)$userId ?>,
        username: '<?= htmlspecialchars($user['username']) ?>',
        email: '<?= htmlspecialchars($user['email']) ?>',
        rawrBalance: <?= (float)$user['rawr_balance'] ?>,
        ticketBalance: <?= (int)$user['ticket_balance'] ?>,
        memberSince: '<?= date('M Y', strtotime($user['created_at'])) ?>',
        kycStatus: '<?= htmlspecialchars($user['kyc_status']) ?>',
        referralCode: '<?= htmlspecialchars($user['referral_code']) ?>',
        currentLoginStreak: <?= (int)$currentStreak ?>,
        longestLoginStreak: <?= (int)$longestStreak ?>,
        totalRawrMined: <?= number_format((float)$miningData['total_mined'] ?? 0, 4, '.', '') ?>,
        miningBoostLevel: <?= number_format((float)$miningMultiplier, 2, '.', '') ?>,
        totalGameWins: <?= (int)$totalGameWins ?>,
        leaderboardRank: <?= (int)$rank ?>,
        kycProgressStatus: JSON.parse('<?= json_encode($kycProgressStatus) ?>'),
        kycOverallProgress: <?= (float)$kycOverallProgress ?>,
        kycRequestData: {
            fullName: '<?= htmlspecialchars($kycData['full_name'] ?? '') ?>',
            dob: '<?= htmlspecialchars($kycData['date_of_birth'] ?? '') ?>',
            country: '<?= htmlspecialchars($kycData['country'] ?? '') ?>',
            contactNumber: '<?= htmlspecialchars($kycData['contact_number'] ?? '') ?>',
            address: '<?= htmlspecialchars($kycData['address'] ?? '') ?>',
            city: '<?= htmlspecialchars($kycData['city'] ?? '') ?>',
            stateProvince: '<?= htmlspecialchars($kycData['state_province'] ?? '') ?>',
            postalCode: '<?= htmlspecialchars($kycData['postal_code'] ?? '') ?>',
            idType: '<?= htmlspecialchars($kycData['id_type'] ?? '') ?>',
            idNumber: '<?= htmlspecialchars($kycData['id_number'] ?? '') ?>',
            idFrontPath: '<?= htmlspecialchars(isset($kycData['id_image_path']) ? BASE_URL . 'uploads/kyc_docs/' . $kycData['id_image_path'] : '') ?>',
            idBackPath: '<?= htmlspecialchars(isset($kycData['id_image_back_path']) ? BASE_URL . 'uploads/kyc_docs/' . $kycData['id_image_back_path'] : '') ?>',
            selfiePath: '<?= htmlspecialchars(isset($kycData['selfie_image_path']) ? BASE_URL . 'uploads/kyc_docs/' . $kycData['selfie_image_path'] : '') ?>',
            walletAddress: '<?= htmlspecialchars($user['wallet_address'] ?? '') ?>'
        }
    };
  </script>
  
    <script src="/RAWR/public/js/profile.js"></script>

</body>
</html>