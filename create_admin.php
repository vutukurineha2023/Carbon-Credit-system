<?php
session_start();
include 'db.php';

// Admin details
$name = "Super Admin";
$email = "admin@cccp.com";
$password = password_hash("admin123", PASSWORD_DEFAULT); // secure hash
$role = "admin";
$status = "approved"; // ✅ valid ENUM value

// Check if admin already exists
$check = $conn->prepare("SELECT id FROM users WHERE email=?");
$check->bind_param("s", $email);
$check->execute();
$check->store_result();

if ($check->num_rows == 0) {
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, status) 
                            VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $name, $email, $password, $role, $status);
    
    if ($stmt->execute()) {
        echo "✅ Admin account created successfully!<br>";
        echo "Email: $email<br>Password: admin123";
    } else {
        echo "❌ Error: " . $stmt->error;
    }
    $stmt->close();
} else {
    echo "⚠️ Admin already exists.";
}

$check->close();
$conn->close();
?>
