<?php
include 'config.php'; // DB connection file

$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$password = $_POST['password'] ?? '';

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Generate a random username
function generateUsername($prefix = 'user') {
    return $prefix . rand(1000, 9999); // e.g., user4352
}

// Generate a unique user_id (e.g., ECASINO582347)
function generateUserId($conn) {
    do {
        $randomId = 'ECASINO' . rand(100000, 999999);
        $check = $conn->prepare("SELECT id FROM users WHERE user_id = ?");
        $check->bind_param("s", $randomId);
        $check->execute();
        $check->store_result();
    } while ($check->num_rows > 0);
    return $randomId;
}

// Make sure email or phone is provided
if (empty($email) && empty($phone)) {
    die("Email or phone is required.");
}

// Try generating a unique username
do {
    $username = generateUsername();
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $checkStmt->bind_param("s", $username);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
} while ($checkResult->num_rows > 0); // keep trying until unique

// Generate the custom user ID
$customUserId = generateUserId($conn);

// Insert user
if (!empty($email)) {
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, user_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $email, $hashedPassword, $customUserId);
} else {
    $stmt = $conn->prepare("INSERT INTO users (username, phone, password, user_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $phone, $hashedPassword, $customUserId);
}

if ($stmt->execute()) {
    $userId = $conn->insert_id;
    $stmt->close();

    // ✅ Make sure wallet is created and check for errors
    $walletStmt = $conn->prepare("INSERT INTO wallets (user_id, token_balance) VALUES (?, 0)");
    $walletStmt->bind_param("i", $userId);
    if (!$walletStmt->execute()) {
        die("Wallet creation failed: " . $walletStmt->error);
    }
    $walletStmt->close();

    header("Location: ../index.php");
    exit();
} else {
    die("Signup failed: " . $stmt->error);
}
?>
