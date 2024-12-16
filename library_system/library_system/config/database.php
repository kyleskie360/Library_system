<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');         // Default XAMPP username
define('DB_PASS', '');             // Default XAMPP password is empty
define('DB_NAME', 'library_management');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4"); 