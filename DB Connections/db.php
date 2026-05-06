<?php
$host = "localhost";
$user = "root";
$password = "";
$database = "meditrack_bd";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

mysqli_set_charset($conn, "utf8mb4");
?>