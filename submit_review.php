<?php
session_start();
include "config.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['submit_review'])) {
    echo json_encode(['ok' => false, 'error' => 'Invalid request']);
    exit();
}

$uid     = (int) $_SESSION['user_id'];
$orderId = (int) ($_POST['order_id'] ?? 0);
$rating  = (int) ($_POST['rating']   ?? 0);
$message = trim($_POST['message']    ?? '');

// Validate rating
if ($rating < 1 || $rating > 5) {
    echo json_encode(['ok' => false, 'error' => 'Invalid rating']);
    exit();
}

// Validate order belongs to user and is confirmed
$check = $conn->prepare("
    SELECT id FROM orders
    WHERE id = ? AND user_id = ? AND customer_confirmed = 1
");
$check->bind_param('ii', $orderId, $uid);
$check->execute();
if ($check->get_result()->num_rows === 0) {
    echo json_encode(['ok' => false, 'error' => 'Order not eligible']);
    exit();
}
$check->close();

// Prevent duplicate review
$dup = $conn->prepare("SELECT id FROM reviews WHERE order_id = ? AND user_id = ?");
$dup->bind_param('ii', $orderId, $uid);
$dup->execute();
if ($dup->get_result()->num_rows > 0) {
    echo json_encode(['ok' => true, 'duplicate' => true]);
    exit();
}
$dup->close();

// Insert review
$ins = $conn->prepare("
    INSERT INTO reviews (order_id, user_id, rating, message, created_at)
    VALUES (?, ?, ?, ?, NOW())
");
$ins->bind_param('iiis', $orderId, $uid, $rating, $message);
$ins->execute();
$ins->close();

echo json_encode(['ok' => true]);