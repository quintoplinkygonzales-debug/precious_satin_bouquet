<?php
// START SESSION ALWAYS
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection: use environment variables when available.
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';
$dbName = getenv('DB_NAME') ?: 'precious_satin';

define('GMAIL_ADDRESS',      'your_gmail@gmail.com');
define('GMAIL_APP_PASSWORD', 'xxxx xxxx xxxx xxxx');
define('SHOP_NAME',          'Precious Satin Bouquet');

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}
?>