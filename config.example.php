<?php
// Database connection
$host     = "localhost";
$user     = "";
$password = "";
$database = "";

$conn = new mysqli($host, $user, $password, $database);

// Stop everything if database connection fails
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
