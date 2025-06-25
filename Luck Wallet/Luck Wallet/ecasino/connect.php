<?php

$host="localhost";
$user="root";
$pass="";
$db="login";

$conn=new mysqli($host,$user,$pass,$db);

// Check connection
if($conn->connect_error){
    // Instead of echoing, log the error and terminate script execution.
    // This prevents any plain text output that would break the JSON response.
    error_log("Failed to connect to DB: " . $conn->connect_error);
    // You might want to return a JSON error here if connect.php was designed to always output JSON
    // but in the context of it being included, simply exiting is better to prevent corrupted output.
    http_response_code(500); // Set HTTP status code to 500 for server error
    echo json_encode(['success' => false, 'message' => 'Database connection error.']);
    exit();
}

// No echo statements here!
?>