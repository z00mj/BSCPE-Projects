<?php
require_once __DIR__ . '/../backend/inc/init.php';
require_once __DIR__ . '/../backend/inc/db.php';
$database = Database::getInstance();
$pdo = $database->getPdo();

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin' || !isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Support fetching by user_id for dashboard modal
if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $user_id = (int)$_GET['user_id'];
    $stmt = $pdo->prepare("SELECT k.*, u.username, u.email, u.rawr_balance, u.ticket_balance, u.created_at as user_created_at
        FROM kyc_requests k
        JOIN users u ON k.user_id = u.id
        WHERE k.user_id = ? ORDER BY k.submitted_at DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $kyc = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($kyc) {
        // Ensure image paths are always strings, never null
        $kyc['id_image_path'] = isset($kyc['id_image_path']) && $kyc['id_image_path'] !== null ? $kyc['id_image_path'] : '';
        $kyc['id_image_back_path'] = isset($kyc['id_image_back_path']) && $kyc['id_image_back_path'] !== null ? $kyc['id_image_back_path'] : '';
        // Only expose the fields needed by the frontend
        $response = [
            'id' => $kyc['id'],
            'username' => $kyc['username'],
            'email' => $kyc['email'],
            'user_id' => $kyc['user_id'],
            'rawr_balance' => $kyc['rawr_balance'],
            'ticket_balance' => $kyc['ticket_balance'],
            'user_created_at' => $kyc['user_created_at'],
            'status' => $kyc['status'],
            'full_name' => $kyc['full_name'],
            'submitted_at' => $kyc['submitted_at'],
            'reviewed_at' => $kyc['reviewed_at'],
            'rejection_reason' => $kyc['rejection_reason'],
            'id_image_path' => $kyc['id_image_path'],
            'id_image_back_path' => $kyc['id_image_back_path'],
            'date_of_birth' => $kyc['date_of_birth'] ?? null,
            'contact_number' => $kyc['contact_number'] ?? null,
            'address' => $kyc['address'] ?? null,
            'city' => $kyc['city'] ?? null,
            'state_province' => $kyc['state_province'] ?? null,
            'postal_code' => $kyc['postal_code'] ?? null
        ];
        // Always include id_image_path and id_image_back_path keys, even if empty
        if (!isset($response['id_image_path'])) $response['id_image_path'] = '';
        if (!isset($response['id_image_back_path'])) $response['id_image_back_path'] = '';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'No KYC found for user']);
        exit;
    }
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request ID']);
    exit;
}

$request_id = (int)$_GET['id'];
$database = Database::getInstance();
$pdo = $database->getPdo();

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
    $kyc = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($kyc) {
        echo json_encode($kyc);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Request not found']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
