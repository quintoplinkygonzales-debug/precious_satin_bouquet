<?php
include "config.php";

$username = "admin";
$password = "123456";
$hash = password_hash($password, PASSWORD_DEFAULT);

$conn->query("DELETE FROM users WHERE username='admin'");

$conn->query("INSERT INTO users (username, password, role) 
VALUES ('$username', '$hash', 'admin')");

echo "✅ ADMIN READY<br>";
echo "Username: admin<br>Password: 123456";
?>