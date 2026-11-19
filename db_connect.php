<?php
$host = "localhost";
$username = "root";       // Default XAMPP MySQL username
$password = "";           // Default XAMPP MySQL password is empty
$database = "hive_pup_db"; // Your existing database name

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set character encoding to support special characters (e.g., ₱)
$conn->set_charset("utf8mb4");
?>