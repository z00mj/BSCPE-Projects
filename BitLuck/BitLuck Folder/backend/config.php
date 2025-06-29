<?php
$host = 'localhost';
$db   = 'ecasinosite';         // ✅ Your database name
$user = 'root';            // ✅ Default XAMPP user
$pass = '';                // ✅ Leave blank for XAMPP
$charset = 'utf8mb4';

// Create DB connection
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
