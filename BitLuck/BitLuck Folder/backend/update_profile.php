<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$userId = $_SESSION['user_id'];
$field = $_POST['field'] ?? '';
$newValue = trim($_POST['value'] ?? '');

if (empty($field) || empty($newValue)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit();
}

// Field whitelist
$allowedFields = ['username', 'email'];
if (!in_array($field, $allowedFields)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid field']);
    exit();
}

if ($field === 'email') {
    if (!filter_var($newValue, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email format']);
        exit();
    }
}

$stmt = $conn->prepare("UPDATE users SET $field = ? WHERE id = ?");
$stmt->bind_param("si", $newValue, $userId);

if ($stmt->execute()) {
    if ($field === 'email') {
        // ✅ Log the user out for security after email update
        session_destroy();
        echo json_encode(['success' => true, 'redirect' => true]);
    } else {
        echo json_encode(['success' => true]);
    }
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update profile']);
}
$stmt->close();
$conn->close();
?>