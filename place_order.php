<?php
// ─────────────────────────────────────────────────────────────────────────────
// place_order.php — Handles order submission from checkout.php
// KEY CHANGE: GCash orders are NOT written to the DB here.
//             They are stored in $_SESSION and only committed to the DB
//             inside save_gcash_proof.php when the customer submits proof.
//             This prevents ghost/abandoned GCash orders from ever appearing
//             in the admin panel.
// ─────────────────────────────────────────────────────────────────────────────
session_start();
include "config.php";

// ── Auth guard ────────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$uid = intval($_SESSION['user_id']);

// ── Only accept POST ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: cart.php");
    exit();
}

// ── Validate total ────────────────────────────────────────────────────────────
if (!isset($_POST['total']) || !is_numeric($_POST['total'])) {
    showError("Invalid order total. Please go back and try again.");
}
$total = floatval($_POST['total']);
if ($total <= 0) {
    showError("Order total must be greater than zero.");
}

// ── Validate selected items ───────────────────────────────────────────────────
$selectedItems = trim($_POST['selected_items'] ?? '');
if (empty($selectedItems)) {
    showError("No items selected. Please go back and select items.");
}
$selectedIds = array_filter(array_map('intval', explode(',', $selectedItems)), fn($v) => $v > 0);
if (empty($selectedIds)) {
    showError("Invalid item selection.");
}
$idsString = implode(',', $selectedIds);

// ── Validate payment method ───────────────────────────────────────────────────
$allowed_payments = ['gcash', 'cod', 'cash'];
$payment_method   = in_array($_POST['payment_method'] ?? '', $allowed_payments)
                    ? $_POST['payment_method']
                    : 'gcash';

// ── Validate delivery mode ────────────────────────────────────────────────────
$delivery_mode = in_array($_POST['delivery_mode'] ?? '', ['delivery', 'pickup'])
                 ? $_POST['delivery_mode']
                 : 'delivery';

// ── Fetch selected cart items (with stock) ────────────────────────────────────
$result = $conn->query("
    SELECT cart.id          AS cart_id,
           cart.product_id,
           cart.quantity,
           cart.custom_price,
           cart.wrapper,
           cart.wrapper_price,
           cart.ribbon,
           cart.ribbon_price,
           cart.addons,
           cart.addons_price,
           products.name    AS product_name,
           products.price   AS product_price,
           products.stock
    FROM cart
    JOIN products ON cart.product_id = products.id
    WHERE cart.user_id = $uid
      AND cart.id IN ($idsString)
");

if (!$result || $result->num_rows === 0) {
    showError("Your cart is empty or those items no longer exist. Please go back and try again.");
}

$cartItems = $result->fetch_all(MYSQLI_ASSOC);

// ── Stock validation BEFORE touching the DB ───────────────────────────────────
$stockErrors = [];
foreach ($cartItems as $row) {
    if ((int)$row['quantity'] > (int)$row['stock']) {
        if ((int)$row['stock'] <= 0) {
            $stockErrors[] = "❌ <strong>" . htmlspecialchars($row['product_name']) . "</strong> is now out of stock.";
        } else {
            $stockErrors[] = "⚠️ <strong>" . htmlspecialchars($row['product_name'])
                           . "</strong>: only {$row['stock']} left, but you have {$row['quantity']} in cart.";
        }
    }
}

if (!empty($stockErrors)) {
    echo "<!DOCTYPE html><html lang='en'><head>
    <meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'>
    <title>Stock Issue</title>
    <style>
      body{font-family:sans-serif;background:#fdf8f5;margin:0;display:flex;align-items:center;justify-content:center;min-height:100vh}
      .box{max-width:520px;width:90%;background:#fff5f5;border:1px solid #feb2b2;border-radius:16px;padding:36px 32px;text-align:center;box-shadow:0 4px 20px rgba(0,0,0,.07)}
      h2{color:#c53030;margin:0 0 14px}
      ul{list-style:none;padding:0;margin:0 0 20px;text-align:left}
      ul li{margin-bottom:10px;color:#555;font-size:14px}
      p.note{color:#888;font-size:13px;margin:0 0 20px}
      .back-btn{display:inline-block;padding:11px 28px;background:#e53e3e;color:#fff;border-radius:10px;text-decoration:none;font-weight:700;font-size:14px}
    </style><?php include __DIR__ . '/pwa_head.php'; ?>
</head><body>
    <div class='box'>
      <h2>🌸 Some items are unavailable</h2>
      <ul>";
    foreach ($stockErrors as $err) echo "<li>$err</li>";
    echo "</ul>
      <p class='note'>Please update your cart and try again.</p>
      <a class='back-btn' href='cart.php'>← Back to Cart</a>
    </div></body></html>";
    exit();
}

// ─────────────────────────────────────────────────────────────────────────────
// GCASH PATH — Store order data in session only. No DB write yet.
// The order is committed to the DB only when the customer submits proof
// in save_gcash_proof.php. If they leave, nothing exists in the DB.
// ─────────────────────────────────────────────────────────────────────────────
if ($payment_method === 'gcash') {

    $pendingItems = [];
    foreach ($cartItems as $row) {
        $price     = !empty($row['custom_price']) ? floatval($row['custom_price']) : floatval($row['product_price']);
        $wrapper   = (!empty($row['wrapper']) && $row['wrapper'] !== 'none') ? $row['wrapper'] : null;
        $ribbon    = (!empty($row['ribbon'])  && $row['ribbon']  !== 'none') ? $row['ribbon']  : null;
        $addonsArr = json_decode($row['addons'] ?? '{}', true);
        $addons    = !empty($addonsArr) ? json_encode($addonsArr) : null;

        $pendingItems[] = [
            'cart_id'      => intval($row['cart_id']),
            'product_id'   => intval($row['product_id']),
            'product_name' => $row['product_name'],
            'quantity'     => intval($row['quantity']),
            'price'        => $price,
            'wrapper'      => $wrapper,
            'ribbon'       => $ribbon,
            'addons'       => $addons,
        ];
    }

    $_SESSION['pending_gcash_order'] = [
        'uid'           => $uid,
        'total'         => $total,
        'delivery_mode' => $delivery_mode,
        'cart_ids'      => array_column($cartItems, 'cart_id'),
        'items'         => $pendingItems,
        'created_at'    => time(),
    ];

    // Redirect to proof page — order does NOT exist in the DB yet
    header("Location: gcash_payment.php");
    exit();
}

// ─────────────────────────────────────────────────────────────────────────────
// COD / CASH PATH — Commit order to DB immediately (no proof needed)
// ─────────────────────────────────────────────────────────────────────────────
$conn->begin_transaction();

try {

    $orderNoRes  = $conn->query("SELECT COALESCE(MAX(user_order_no), 0) + 1 AS next_no FROM orders WHERE user_id = $uid");
    $nextOrderNo = $orderNoRes ? intval($orderNoRes->fetch_assoc()['next_no']) : 1;

    $order_status     = 'pending';
    $gcash_ref        = '';
    $gcash_screenshot = '';

    $stmt = $conn->prepare("
        INSERT INTO orders
            (user_id, user_order_no, total_price, payment_method, delivery_mode, status, gcash_ref, gcash_screenshot)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('iidsssss',
        $uid, $nextOrderNo, $total, $payment_method,
        $delivery_mode, $order_status, $gcash_ref, $gcash_screenshot
    );
    $stmt->execute();
    $order_id = $conn->insert_id;
    $stmt->close();

    if ($order_id <= 0) throw new Exception("Failed to create order record.");

    $itemStmt  = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, price, wrapper, ribbon, addons) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stockStmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");

    foreach ($cartItems as $row) {
        $price = !empty($row['custom_price']) ? floatval($row['custom_price']) : floatval($row['product_price']);
        $pName = $row['product_name'];
        $qty   = intval($row['quantity']);
        $pid   = intval($row['product_id']);
        $wrapper   = (!empty($row['wrapper']) && $row['wrapper'] !== 'none') ? $row['wrapper'] : null;
        $ribbon    = (!empty($row['ribbon'])  && $row['ribbon']  !== 'none') ? $row['ribbon']  : null;
        $addonsArr = json_decode($row['addons'] ?? '{}', true);
        $addons    = !empty($addonsArr) ? json_encode($addonsArr) : null;

        $itemStmt->bind_param('iisissss', $order_id, $pid, $pName, $qty, $price, $wrapper, $ribbon, $addons);
        $itemStmt->execute();

        $stockStmt->bind_param('iii', $qty, $pid, $qty);
        $stockStmt->execute();
        if ($stockStmt->affected_rows === 0) {
            throw new Exception("Stock ran out for: " . htmlspecialchars($pName));
        }
    }

    $itemStmt->close();
    $stockStmt->close();

    $delStmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND id IN ($idsString)");
    $delStmt->bind_param('i', $uid);
    $delStmt->execute();
    $delStmt->close();

    $conn->commit();

    header("Location: thankyou.php?order_id=$order_id");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    echo "<!DOCTYPE html><html lang='en'><head>
    <meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'>
    <title>Order Failed</title>
    <style>
      body{font-family:sans-serif;background:#fdf8f5;margin:0;display:flex;align-items:center;justify-content:center;min-height:100vh}
      .box{max-width:480px;width:90%;background:#fff5f5;border:1px solid #feb2b2;border-radius:16px;padding:36px 32px;text-align:center;box-shadow:0 4px 20px rgba(0,0,0,.07)}
      h2{color:#c53030;margin:0 0 12px} p{color:#555;font-size:14px;margin:0 0 8px}
      p.note{color:#888;font-size:12px;margin:0 0 20px}
      .back-btn{display:inline-block;padding:11px 28px;background:#e53e3e;color:#fff;border-radius:10px;text-decoration:none;font-weight:700;font-size:14px}
    </style></head><body>
    <div class='box'>
      <h2>⚠️ Order could not be placed</h2>
      <p>" . htmlspecialchars($e->getMessage()) . "</p>
      <p class='note'>No charges were made. Please update your cart and try again.</p>
      <a class='back-btn' href='cart.php'>← Back to Cart</a>
    </div></body></html>";
    exit();
}

function showError(string $msg): void {
    echo "<!DOCTYPE html><html lang='en'><head>
    <meta charset='UTF-8'><title>Error</title>
    <style>body{font-family:sans-serif;padding:40px;color:#555;background:#fdf8f5} a{color:#be185d}</style>
    </head><body>
    <p>" . htmlspecialchars($msg) . "</p>
    <p><a href='javascript:history.back()'>← Go Back</a></p>
    </body></html>";
    exit();
}