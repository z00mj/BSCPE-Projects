<?php
require_once __DIR__ . '/../backend/inc/init.php';
require_once __DIR__ . '/../backend/inc/db.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /RAWR/admin/kyc_requests.php");
    exit;
}

// Verify admin permissions
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin' || !isset($_SESSION['admin_id'])) {
    header("Location: /RAWR/public/login.php");
    exit;
}

$database = Database::getInstance();
$pdo = $database->getPdo();

// Initialize variables
$request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';
$rejection_reason = isset($_POST['rejection_reason']) ? trim($_POST['rejection_reason']) : '';
$admin_id = $_SESSION['admin_id'];

// Validate input
if ($request_id <= 0 || !in_array($action, ['approve', 'reject'])) {
    header("Location: /RAWR/admin/kyc_requests.php?error=" . urlencode("Invalid request parameters"));
    exit;
}

if ($action === 'reject' && empty($rejection_reason)) {
    header("Location: /RAWR/admin/kyc_requests.php?error=" . urlencode("Please provide a rejection reason"));
    exit;
}

try {
    // Begin transaction
    $pdo->beginTransaction();

    // Get the KYC request details
    $stmt = $pdo->prepare("
        SELECT k.id, k.user_id, k.status, u.kyc_status 
        FROM kyc_requests k
        JOIN users u ON k.user_id = u.id
        WHERE k.id = ? FOR UPDATE
    ");
    $stmt->execute([$request_id]);
    $kyc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$kyc) {
        throw new Exception("KYC request not found");
    }

    // Check if request is already processed
    if (!in_array($kyc['status'], ['not_verified', 'pending'])) {
        throw new Exception("This KYC request has already been processed");
    }

    // Process based on action
    if ($action === 'approve') {
        // Update KYC request
        $stmt = $pdo->prepare("
            UPDATE kyc_requests 
            SET status = 'approved', 
                reviewed_by = ?, 
                reviewed_at = NOW(),
                rejection_reason = NULL
            WHERE id = ?
        ");
        $stmt->execute([$admin_id, $request_id]);

        // Update user's KYC status
        $stmt = $pdo->prepare("UPDATE users SET kyc_status = 'approved' WHERE id = ?");
        $stmt->execute([$kyc['user_id']]);

        // Log the approval in audit log
        $stmt = $pdo->prepare("
            INSERT INTO admin_audit_log (admin_id, action, target_type, target_id, details)
            VALUES (?, 'kyc_approval', 'kyc', ?, 'KYC request approved')
        ");
        $stmt->execute([$admin_id, $request_id]);

        $success_message = "KYC request approved successfully";

    } elseif ($action === 'reject') {
        // Update KYC request
        $stmt = $pdo->prepare("
            UPDATE kyc_requests 
            SET status = 'rejected', 
                reviewed_by = ?, 
                reviewed_at = NOW(),
                rejection_reason = ?
            WHERE id = ?
        ");
        $stmt->execute([$admin_id, $rejection_reason, $request_id]);

        // Update user's KYC status
        $stmt = $pdo->prepare("UPDATE users SET kyc_status = 'rejected' WHERE id = ?");
        $stmt->execute([$kyc['user_id']]);

        // Log the rejection in audit log
        $stmt = $pdo->prepare("
            INSERT INTO admin_audit_log (admin_id, action, target_type, target_id, details)
            VALUES (?, 'kyc_rejection', 'kyc', ?, ?)
        ");
        $stmt->execute([$admin_id, $request_id, "KYC request rejected. Reason: $rejection_reason"]);

        $success_message = "KYC request rejected successfully";
    }

    // Commit transaction
    $pdo->commit();

    // Redirect with success message
    header("Location: /RAWR/admin/kyc_requests.php?success=" . urlencode($success_message));
    exit;

} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Log the error
    error_log("KYC processing error: " . $e->getMessage());

    // Redirect with error message
    header("Location: /RAWR/admin/kyc_requests.php?error=" . urlencode($e->getMessage()));
    exit;
}