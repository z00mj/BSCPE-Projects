<?php
session_start();

// Database connection
$host = 'localhost';
$dbname = 'login';
$username = 'root'; // Default XAMPP username
$password = '';     // Default XAMPP password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $_SESSION['error'] = "Connection failed: " . $e->getMessage();
    header("Location: forgot-password.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate input
    if (empty($email) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['error'] = "All fields are required!";
        header("Location: forgot-password.php");
        exit();
    }
    
    if ($new_password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match!";
        header("Location: forgot-password.php");
        exit();
    }
    
    if (strlen($new_password) < 6) {
        $_SESSION['error'] = "Password must be at least 6 characters long!";
        header("Location: forgot-password.php");
        exit();
    }
    
    try {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            // Hash the new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update the password in the database
            $updateStmt = $pdo->prepare("UPDATE users SET password = :password WHERE email = :email");
            $updateStmt->bindParam(':password', $hashed_password);
            $updateStmt->bindParam(':email', $email);
            $updateStmt->execute();
            
            $_SESSION['success'] = "Password has been reset successfully! You can now login with your new password.";
            header("Location: index.php");
            exit();
        } else {
            $_SESSION['error'] = "No account found with that email address.";
            header("Location: forgot-password.php");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
        header("Location: forgot-password.php");
        exit();
    }
} else {
    header("Location: forgot-password.php");
    exit();
}
?>
