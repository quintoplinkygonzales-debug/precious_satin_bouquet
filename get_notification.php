<?php
// get_notifications.php
// Returns JSON with unread admin-reply count for the logged-in user.
// Called via fetch() from the sidebar every 30 seconds.

session_start();
include "config.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0]);
    exit;
}

$uid = (int)$_SESSION['user_id'];

// Ensure the is_read column exists on message_replies (safe to run every time)
$conn->query("ALTER TABLE message_replies ADD COLUMN IF NOT EXISTS is_read TINYINT(1) DEFAULT 0");

// Count admin replies on this user's messages that haven't been read yet
$stmt = $conn->prepare("
    SELECT COUNT(*) AS c
    FROM message_replies  mr
    JOIN contact_messages cm ON cm.id = mr.message_id
    WHERE cm.user_id  = ?
      AND mr.sender   = 'admin'
      AND mr.is_read  = 0
");
$stmt->bind_param("i", $uid);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo json_encode(['count' => (int)$row['c']]);