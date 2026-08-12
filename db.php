<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "carbon_platform_community"; // <-- Make sure spelling is correct

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
