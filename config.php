<?php
// Database Configuration
$host = "localhost";  // Change if using a remote database
$username = "root";   // Default XAMPP username
$password = "Adnan@66202";       // Default XAMPP password (empty)
$dbname = "expense_tracker";  // Your database name

// Create Connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check Connection
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}
?>
