<?php
// ─────────────────────────────────────────────────────────────────────────────
// cancel_gcash_order.php
// Called (via fetch) when a customer leaves gcash_payment.php without
// submitting proof. Cancels the order and restores stock — only if the
// order is still "proof-less" (gcash_ref and gcash_screenshot are empty).
// ─────────────────────────────────────────────────────────────────────────────
session_start();
include "config.php";

header('Content-Type: application/json');

// ── Auth guard ────────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'unauthenticated']);
    exit();
}

$uid      = intval($_SESSION['user_id']);
$order_id = intval($_POST['order_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $order_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'bad_request']);
    exit();
}

// ── Fetch the order — must belong to this user, be GCash, and have NO proof yet ──
$stmt = $conn->prepare("
    SELECT id, status, gcash_ref, gcash_screenshot
    FROM orders
    WHERE id = ? AND user_id = ? AND payment_method = 'gcash'
");
$stmt->bind_param('ii', $order_id, $uid);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    echo json_encode(['ok' => false, 'error' => 'not_found']);
    exit();
}

// Safety: if proof was already submitted, do NOT cancel
if (!empty($order['gcash_ref']) || !empty($order['gcash_screenshot'])) {
    echo json_encode(['ok' => false, 'error' => 'proof_already_submitted']);
    exit();
}

// ── Begin transaction ─────────────────────────────────────────────────────────
$conn->begin_transaction();

try {
    // 1. Restore stock for each ordered item
    $items = $conn->query("SELECT product_id, quantity FROM order_items WHERE order_id = $order_id");
    if ($items && $items->num_rows > 0) {
        $restoreStmt = $conn->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
        while ($row = $items->fetch_assoc()) {
            $qty = intval($row['quantity']);
            $pid = intval($row['product_id']);
            $restoreStmt->bind_param('ii', $qty, $pid);
            $restoreStmt->execute();
        }
        $restoreStmt->close();
    }

    // 2. Delete order items
    $conn->query("DELETE FROM order_items WHERE order_id = $order_id");

    // 3. Delete the order itself
    $del = $conn->prepare("DELETE FROM orders WHERE id = ? AND user_id = ?");
    $del->bind_param('ii', $order_id, $uid);
    $del->execute();
    $del->close();

    $conn->commit();

    echo json_encode(['ok' => true]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}