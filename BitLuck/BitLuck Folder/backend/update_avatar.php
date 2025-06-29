<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo "Not logged in.";
    exit;
}

$userId = $_SESSION['user_id'];
$avatar = $_POST['avatar'] ?? '';

if (!$avatar) {
    echo "No avatar selected.";
    exit;
}

// Optionally: Validate filename to avoid abuse
$allowedAvatars = ['avatar1.png', 'avatar2.png', 'avatar3.png', 'avatar4.png'];
if (!in_array($avatar, $allowedAvatars)) {
    echo "Invalid avatar.";
    exit;
}

// Update in DB
$stmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
$stmt->bind_param("si", $avatar, $userId);

if ($stmt->execute()) {
    echo "Avatar updated.";
} else {
    echo "DB error: " . $stmt->error;
}
?>
