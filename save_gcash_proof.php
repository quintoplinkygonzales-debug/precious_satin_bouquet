<?php
// ─────────────────────────────────────────────────────────────────────────────
// save_gcash_proof.php
// KEY CHANGE: This file now CREATES the order in the database for the first
//             time (not just updates it). The order was held in session by
//             place_order.php and never written to DB. Only customers who
//             complete this step will ever appear in the admin panel.
// ─────────────────────────────────────────────────────────────────────────────
session_start();
include "config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$uid     = intval($_SESSION['user_id']);
$pending = $_SESSION['pending_gcash_order'] ?? null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$pending || $pending['uid'] !== $uid) {
    header("Location: cart.php");
    exit();
}

// Expire stale sessions (2 hours)
if (time() - ($pending['created_at'] ?? 0) > 7200) {
    unset($_SESSION['pending_gcash_order']);
    showError("Your session expired. Please go back to your cart and try again.");
}

// ── Validate reference number ─────────────────────────────────────────────────
$gcash_ref = preg_replace('/\D/', '', trim($_POST['gcash_ref'] ?? ''));
if (strlen($gcash_ref) < 8) {
    header("Location: gcash_payment.php?err=ref");
    exit();
}

// ── Handle screenshot upload ──────────────────────────────────────────────────
if (!isset($_FILES['gcash_screenshot']) || $_FILES['gcash_screenshot']['error'] !== UPLOAD_ERR_OK) {
    header("Location: gcash_payment.php?err=ss");
    exit();
}

$file         = $_FILES['gcash_screenshot'];
$allowedMime  = ['image/jpeg', 'image/png', 'image/webp'];
$finfo        = finfo_open(FILEINFO_MIME_TYPE);
$detectedMime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($detectedMime, $allowedMime)) {
    showError("Invalid file type. Only JPG, PNG, or WEBP screenshots are allowed.");
}
if ($file['size'] > 5 * 1024 * 1024) {
    showError("Screenshot too large. Max 5 MB.");
}

$uploadDir = __DIR__ . '/uploads/gcash/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$ext      = match($detectedMime) {
    'image/png'  => 'png',
    'image/webp' => 'webp',
    default      => 'jpg',
};
$filename = 'gcash_' . $uid . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$destPath = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    showError("Failed to save your screenshot. Please try again.");
}

$gcash_screenshot = 'uploads/gcash/' . $filename;

// ── Commit order to database (first time it touches the DB) ──────────────────
$total         = floatval($pending['total']);
$delivery_mode = $pending['delivery_mode'];
$items         = $pending['items'];
$cart_ids      = $pending['cart_ids'];

// Re-validate stock one more time before committing
$stockErrors = [];
foreach ($items as $item) {
    $pid = intval($item['product_id']);
    $qty = intval($item['quantity']);
    $stockRes = $conn->query("SELECT stock, name FROM products WHERE id = $pid");
    if ($stockRes) {
        $stockRow = $stockRes->fetch_assoc();
        if ($qty > intval($stockRow['stock'])) {
            $stockErrors[] = htmlspecialchars($stockRow['name'] ?? $item['product_name']);
        }
    }
}
if (!empty($stockErrors)) {
    if (file_exists($destPath)) unlink($destPath);
    unset($_SESSION['pending_gcash_order']);
    showError("Sorry, some items ran out of stock while you were on the payment page: " . implode(', ', $stockErrors) . ". Please return to your cart.");
}

$conn->begin_transaction();

try {
    // Get next per-user order number
    $orderNoRes  = $conn->query("SELECT COALESCE(MAX(user_order_no), 0) + 1 AS next_no FROM orders WHERE user_id = $uid");
    $nextOrderNo = $orderNoRes ? intval($orderNoRes->fetch_assoc()['next_no']) : 1;

    $payment_method = 'gcash';
    $order_status   = 'pending_verification';

    // Insert order with proof already attached — goes straight to pending_verification
    $stmt = $conn->prepare("
        INSERT INTO orders
            (user_id, user_order_no, total_price, payment_method, delivery_mode, status, gcash_ref, gcash_screenshot)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('iidsssss',
        $uid, $nextOrderNo, $total,
        $payment_method, $delivery_mode,
        $order_status, $gcash_ref, $gcash_screenshot
    );
    $stmt->execute();
    $order_id = $conn->insert_id;
    $stmt->close();

    if ($order_id <= 0) throw new Exception("Failed to create order record.");

    // Insert order items & deduct stock
    $itemStmt  = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, price, wrapper, ribbon, addons) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stockStmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");

    foreach ($items as $item) {
        $pid   = intval($item['product_id']);
        $pName = $item['product_name'];
        $qty   = intval($item['quantity']);
        $price = floatval($item['price']);
        $wrap  = $item['wrapper'];
        $rib   = $item['ribbon'];
        $add   = $item['addons'];

        $itemStmt->bind_param('iisissss', $order_id, $pid, $pName, $qty, $price, $wrap, $rib, $add);
        $itemStmt->execute();

        $stockStmt->bind_param('iii', $qty, $pid, $qty);
        $stockStmt->execute();
        if ($stockStmt->affected_rows === 0) {
            throw new Exception("Stock ran out for: " . htmlspecialchars($pName));
        }
    }

    $itemStmt->close();
    $stockStmt->close();

    // Remove ordered items from cart
    if (!empty($cart_ids)) {
        $cartIdStr = implode(',', array_map('intval', $cart_ids));
        $delStmt   = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND id IN ($cartIdStr)");
        $delStmt->bind_param('i', $uid);
        $delStmt->execute();
        $delStmt->close();
    }

    $conn->commit();

    // Clear pending session data — order is now in the DB
    unset($_SESSION['pending_gcash_order']);

    header("Location: thankyou.php?order_id=$order_id");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    // Clean up uploaded screenshot since order wasn't saved
    if (file_exists($destPath)) unlink($destPath);
    showError("Could not place your order: " . htmlspecialchars($e->getMessage()) . " Please try again.");
}

// ── Helper ────────────────────────────────────────────────────────────────────
function showError(string $msg): void {
    echo "<!DOCTYPE html><html lang='en'><head>
    <meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'>
    <title>Error</title>
    <style>
      body{font-family:sans-serif;background:#fdf8f5;margin:0;display:flex;align-items:center;justify-content:center;min-height:100vh}
      .box{max-width:460px;width:90%;background:#fff5f5;border:1px solid #feb2b2;border-radius:16px;padding:36px 32px;text-align:center;box-shadow:0 4px 20px rgba(0,0,0,.07)}
      h2{color:#c53030;margin:0 0 12px} p{color:#555;font-size:14px;line-height:1.6;margin:0 0 20px}
      .btn{display:inline-block;padding:11px 28px;background:#e53e3e;color:#fff;border-radius:10px;text-decoration:none;font-weight:700;font-size:14px}
    </style><?php include __DIR__ . '/pwa_head.php'; ?>
</head><body>
    <div class='box'><h2>⚠️ Something went wrong</h2>
    <p>" . htmlspecialchars($msg) . "</p>
    <a class='btn' href='cart.php'>← Back to Cart</a></div></body></html>";
    exit();
}