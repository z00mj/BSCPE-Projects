<?php

// Database connection parameters
// !! IMPORTANT !!: For a production environment, avoid hardcoding these directly.
// Consider using environment variables or a more secure configuration management.
define('DB_HOST', '127.0.0.1'); // Or 'localhost' if that works for your setup
define('DB_NAME', 'login');    // Your database name
define('DB_USER', 'root');     // Your MySQL username
define('DB_PASS', '');         // Your MySQL password (leave empty if none, or specify it)

// Attempt to connect to the database using PDO
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch results as associative arrays
        PDO::ATTR_EMULATE_PREPARES   => false,                  // Disable emulation for prepared statements (crucial for security)
    ];

    // Create the PDO instance
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

    // Optional: For debugging, you can uncomment the line below to confirm connection.
    // echo "Database connection successful!<br>";

} catch (PDOException $e) {
    // Handle connection errors
    // In a production environment, DO NOT display $e->getMessage() to users.
    // Instead, log the error to a file and show a generic error message.
    die("Database connection failed: " . $e->getMessage());
}

// The $pdo object is now available for use in any file that includes db_connect.php
?>