<?php
session_start();
include "config.php";
include "mailer.php";

if (!isset($_SESSION['user_id'])) die("Login first");

$uid  = $_SESSION['user_id'];
$user = $conn->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();
if ($user['role'] != 'admin') die("Access denied");

// ── Handle status update POST ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $oid        = (int) $_POST['order_id'];
    $newStatus  = $_POST['new_status'] ?? '';
    $allowed    = ['Pending', 'Preparing', 'Out for Delivery', 'Delivered', 'Ready for Pickup', 'Picked Up', 'Cancelled'];

    if ($oid > 0 && in_array($newStatus, $allowed)) {
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $newStatus, $oid);
        $stmt->execute();
        $stmt->close();

        // ── Send status notification email to customer ────────────────────
        $orderRow = $conn->query("
            SELECT o.*, u.email, u.full_name, u.username
            FROM orders o
            LEFT JOIN users u ON u.id = o.user_id
            WHERE o.id = $oid
        ")->fetch_assoc();

        if (!empty($orderRow['email'])) {
            $toEmail  = $orderRow['email'];
            $toName   = !empty($orderRow['full_name']) ? $orderRow['full_name'] : $orderRow['username'];
            $orderNum = str_pad($oid, 5, '0', STR_PAD_LEFT);

            // Status message per step
            $msgs = [
                'Preparing'        => ['✂️ We\'re Preparing Your Order!',  'Our team is now crafting your bouquet with love and care.'],
                'Out for Delivery' => ['🚗 Your Order is On Its Way!',      'Your blooms have been handed to our delivery team and are heading to you now.'],
                'Ready for Pickup' => ['🏪 Your Order is Ready!',           'Your bouquet is ready for pickup at our store. We can\'t wait to see you!'],
                'Delivered'        => ['💐 Your Order Has Been Delivered!', 'We hope your flowers brought you joy. Thank you for choosing Precious Satin Bouquet!'],
                'Pending'          => ['🌸 Order Update',                   'Your order status has been updated to Pending.'],
                'Cancelled'        => ['❌ Your Order Has Been Cancelled',  'Unfortunately, your order has been cancelled due to a rejected or unverified payment. Please contact us if you have any questions.'],
            ];
            [$subject, $message] = $msgs[$newStatus] ?? ['🌸 Order Update', 'Your order status has been updated.'];

            sendStatusEmail($toEmail, $toName, $orderNum, $newStatus, $subject, $message);
        }
    }

    // Redirect back to same order modal
    header("Location: admin_orders.php?order_id=$oid&updated=1");
    exit();
}

// ── Load modal order if requested ────────────────────────────────────────────
$modalOrder    = null;
$modalItems    = [];
$modalIsNew    = false;  // was this order unseen before this visit?
if (isset($_GET['order_id'])) {
    $oid = intval($_GET['order_id']);
    $modalOrder = $conn->query("
        SELECT o.*, u.username, u.email
        FROM orders o
        LEFT JOIN users u ON u.id = o.user_id
        WHERE o.id = $oid
    ")->fetch_assoc();
    $itemsResult = $conn->query("
        SELECT * FROM order_items WHERE order_id = $oid
    ");
    if ($itemsResult) {
        while ($row = $itemsResult->fetch_assoc()) $modalItems[] = $row;
    }
    // Check if unseen BEFORE marking seen
    $seenRow = $conn->query("SELECT seen FROM admin_order_seen WHERE order_id = $oid")->fetch_assoc();
    $modalIsNew = $seenRow && (int)$seenRow['seen'] === 0;
    // Mark as seen now
    $conn->query("UPDATE admin_order_seen SET seen = 1 WHERE order_id = $oid");
}

// ── Collect unseen order IDs for the list cards ───────────────────────────────
$unseenIds = [];
$unseenRes = $conn->query("SELECT order_id FROM admin_order_seen WHERE seen = 0");
if ($unseenRes) { while ($r = $unseenRes->fetch_assoc()) $unseenIds[] = (int)$r['order_id']; }

// ── All orders ────────────────────────────────────────────────────────────────
$orders = $conn->query("
    SELECT o.id, o.user_order_no, o.total_price, o.status, o.payment_method,
           o.delivery_mode, o.customer_confirmed, o.created_at,
           u.username, u.email, u.full_name, COUNT(oi.id) AS item_count
    FROM orders o
    LEFT JOIN users u ON u.id = o.user_id
    LEFT JOIN order_items oi ON oi.order_id = o.id
    GROUP BY o.id, o.user_order_no, o.total_price, o.status, o.payment_method,
             o.delivery_mode, o.customer_confirmed, o.created_at, u.username, u.email, u.full_name
    ORDER BY o.id DESC
");
$totalOrders = $orders->num_rows;

// ── Status email helper ───────────────────────────────────────────────────────
function sendStatusEmail(
    string $toEmail, string $toName,
    string $orderNum, string $newStatus,
    string $subject, string $message
): void {
    // Status → icon + color
    $colors = [
        'Preparing'        => ['#a855f7', '✂️'],
        'Out for Delivery' => ['#3b82f6', '🚗'],
        'Delivered'        => ['#22c55e', '💐'],
        'Pending'          => ['#f59e0b', '🌸'],
        'Cancelled'        => ['#dc2626', '❌'],
    ];
    [$color, $icon] = $colors[$newStatus] ?? ['#c9a97a', '🌸'];

    // Reuse PHPMailer setup from mailer.php via a local build
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = GMAIL_ADDRESS;
        $mail->Password   = GMAIL_APP_PASSWORD;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom(GMAIL_ADDRESS, SHOP_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = SHOP_NAME . ' — ' . $subject;
        $mail->isHTML(true);

        $mail->Body = '
<!DOCTYPE html><html><head><meta charset="UTF-8">
</head>
<body style="margin:0;padding:0;background:#faf8f5;font-family:\'DM Sans\',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#faf8f5;padding:40px 16px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

  <!-- Header -->
  <tr><td style="background:#2c1f14;border-radius:14px 14px 0 0;padding:36px 40px;text-align:center;">
    <div style="font-size:28px;letter-spacing:2px;color:#c9a97a;font-family:Georgia,serif;">🌸 ' . SHOP_NAME . '</div>
    <div style="color:#f5ede0;font-size:13px;margin-top:6px;letter-spacing:1px;text-transform:uppercase;">Order Update</div>
  </td></tr>

  <!-- Body -->
  <tr><td style="background:#ffffff;padding:36px 40px;border:1px solid #ede8e2;border-top:none;">

    <p style="font-size:16px;color:#2c1f14;margin:0 0 6px;">Hi <strong>' . htmlspecialchars($toName) . '</strong> 👋</p>
    <p style="font-size:15px;color:#7a6c62;margin:0 0 28px;line-height:1.6;">' . htmlspecialchars($message) . '</p>

    <!-- Status badge -->
    <div style="text-align:center;margin-bottom:28px;">
      <div style="display:inline-block;background:' . $color . ';color:#fff;border-radius:50px;
          padding:12px 28px;font-size:16px;font-weight:600;letter-spacing:0.5px;">
        ' . $icon . ' ' . htmlspecialchars($newStatus) . '
      </div>
    </div>

    <!-- Order number -->
    <div style="background:#fdf3e8;border-radius:10px;padding:14px 20px;margin-bottom:24px;">
      <span style="font-size:13px;color:#9b8e84;text-transform:uppercase;letter-spacing:1px;">Order Number</span><br>
      <span style="font-size:22px;font-family:Georgia,serif;font-weight:600;color:#2c1f14;">#' . $orderNum . '</span>
    </div>

    <p style="font-size:14px;color:#9b8e84;margin:0;line-height:1.7;">
      You can track your order anytime by visiting My Orders on our website. 💐
    </p>
  </td></tr>

  <!-- Footer -->
  <tr><td style="background:#fdf3e8;border-radius:0 0 14px 14px;padding:20px 40px;
      text-align:center;border:1px solid #ede8e2;border-top:none;">
    <p style="margin:0;font-size:12px;color:#b8a898;">© ' . date('Y') . ' ' . SHOP_NAME . ' · Made with 🌸</p>
  </td></tr>

</table></td></tr></table>
</body></html>';

        $mail->AltBody = "$subject\n\nHi $toName,\n$message\n\nOrder #$orderNum is now: $newStatus";
        $mail->send();
    } catch (\Exception $e) {
        error_log('Status email error: ' . $mail->ErrorInfo);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Orders — Precious Satin Bouquet</title>
<link rel="icon" type="image/png" href="images/logo/SB.png">
<link rel="stylesheet" href="style.css">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Jost:wght@300;400;500;600;700&family=Great+Vibes&display=swap" rel="stylesheet">
<style>
:root {
    --blush:     #fff3ed;
    --petal:     #ffc9a0;
    --rose:      #f08050;
    --deep-rose: #d05a28;
    --cream:     #fff8f4;
    --white:     #ffffff;
    --ink:       #4a2c1e;
    --muted:     #9a6b5a;
    --border:    rgba(220,120,70,0.22);
    --shadow-sm: 0 2px 12px rgba(208,90,40,0.10);
    --shadow-md: 0 8px 32px rgba(208,90,40,0.14);
    --shadow-lg: 0 20px 60px rgba(208,90,40,0.18);
    --radius:    18px;
    --radius-sm: 10px;
    --nav-h:     62px;
}
* { box-sizing: border-box; margin: 0; padding: 0; }

/* ── Lock full-screen, no page scroll ── */
html, body {
    height: 100%;
    overflow: hidden;
    background: var(--cream);
    font-family: 'Jost', sans-serif;
    color: var(--ink);
}
body::before {
    content: ''; position: fixed; inset: 0;
    background: radial-gradient(ellipse 60% 40% at 90% 10%, rgba(255,200,160,0.3) 0%, transparent 70%),
                radial-gradient(ellipse 50% 60% at 5% 80%, rgba(255,215,185,0.4) 0%, transparent 65%);
    pointer-events: none; z-index: 0;
}

/* ══════════════════════════════
   NAV — compact, fixed height
══════════════════════════════ */
.adm-nav {
    position: fixed; top: 0; left: 0; right: 0; z-index: 300;
    height: var(--nav-h);
    display: flex; align-items: center; padding: 0 28px;
    background: rgba(255,248,242,0.95);
    backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
    border-bottom: 1px solid var(--border);
}
.adm-nav::after {
    content: ''; position: absolute; bottom: -1px; left: 50%;
    transform: translateX(-50%); width: 100px; height: 2px;
    background: linear-gradient(90deg, transparent, #f08050, transparent); border-radius: 2px;
}
.adm-nav-left  { flex: 1; }
.adm-nav-center { flex: 1; display: flex; justify-content: center; }
.adm-nav-right { flex: 1; display: flex; align-items: center; justify-content: flex-end; gap: 8px; }
.adm-nav-brand   { font-family: 'Great Vibes', cursive; font-size: 26px; color: var(--deep-rose); text-decoration: none; letter-spacing: 0.5px; line-height: 1; }
.adm-nav-tagline { font-size: 9px; font-weight: 500; letter-spacing: 3px; text-transform: uppercase; color: var(--muted); display: block; text-align: center; margin-top: -2px; }
.adm-icon { width: 36px; height: 36px; border-radius: 50%; background: transparent; border: 1.5px solid var(--border); display: flex; align-items: center; justify-content: center; color: var(--muted); text-decoration: none; cursor: pointer; transition: all 0.2s; }
.adm-icon.active { background: var(--deep-rose); border-color: var(--deep-rose); color: #fff; }
.adm-settings-wrap { position: relative; }
.adm-settings-dropdown { display: none; position: absolute; top: calc(100% + 12px); right: 0; width: 270px; background: var(--white); border: 1px solid var(--border); border-radius: 20px; box-shadow: var(--shadow-lg); z-index: 400; overflow: hidden; animation: dropIn 0.22s cubic-bezier(.34,1.56,.64,1); }
.adm-settings-dropdown.open { display: block; }
@keyframes dropIn { from { opacity: 0; transform: translateY(-10px) scale(0.97); } to { opacity: 1; transform: translateY(0) scale(1); } }
.adm-settings-head { background: var(--blush); padding: 12px 20px; font-size: 11px; font-weight: 700; color: var(--deep-rose); letter-spacing: 2.5px; text-transform: uppercase; border-bottom: 1px solid var(--border); }
.adm-settings-item { display: flex; align-items: center; gap: 14px; padding: 12px 20px; text-decoration: none; color: var(--ink); font-size: 14px; font-weight: 500; transition: background 0.15s; border-bottom: 1px solid rgba(247,197,216,0.25); }
.adm-settings-item:last-child { border-bottom: none; }
.adm-settings-item:hover { background: var(--blush); color: var(--deep-rose); }
.adm-settings-item-icon { width: 34px; height: 34px; border-radius: 10px; background: linear-gradient(135deg, var(--petal), #f08050); display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0; }
.adm-settings-item-sub { font-size: 11px; color: var(--muted); font-weight: 400; margin-top: 1px; }

/* ══════════════════════════════
   LANDSCAPE SHELL
   Left: orders list  |  Right: detail panel
══════════════════════════════ */
.ls-shell {
    position: fixed;
    top: var(--nav-h); left: 0; right: 0; bottom: 0;
    display: grid;
    grid-template-columns: 420px 1fr;
    z-index: 1;
    overflow: hidden;
}

/* ── LEFT: Orders list column ── */
.ls-left {
    display: flex;
    flex-direction: column;
    border-right: 1px solid var(--border);
    background: var(--white);
    overflow: hidden;
}

.ls-left-head {
    padding: 18px 22px 14px;
    border-bottom: 1px solid var(--border);
    background: var(--blush);
    flex-shrink: 0;
}
.ls-left-title-row {
    display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 12px;
}
.ls-page-title {
    font-family: 'Cormorant Garamond', serif;
    font-size: 32px; font-weight: 700; color: var(--deep-rose); line-height: 1;
}
.ls-page-count {
    font-size: 13px; color: var(--muted); font-weight: 500;
}
.search-wrap { position: relative; }
.search-wrap input {
    width: 100%; padding: 10px 16px 10px 38px;
    border: 1.5px solid var(--border); border-radius: 50px;
    background: var(--white); font-family: 'Jost', sans-serif;
    font-size: 14px; color: var(--ink);
    transition: border-color 0.2s, box-shadow 0.2s; outline: none;
}
.search-wrap input:focus { border-color: var(--rose); box-shadow: 0 0 0 3px rgba(220,120,70,0.15); }
.search-wrap::before { content: '🔍'; position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-size: 13px; pointer-events: none; }

/* Scrollable orders list */
.ls-orders-scroll {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
}
.ls-orders-scroll::-webkit-scrollbar { width: 5px; }
.ls-orders-scroll::-webkit-scrollbar-track { background: transparent; }
.ls-orders-scroll::-webkit-scrollbar-thumb { background: var(--petal); border-radius: 10px; }

/* Order row cards */
.order-card {
    display: flex; align-items: center;
    padding: 14px 18px 14px 22px;
    border-bottom: 1px solid rgba(248,190,210,0.28);
    cursor: pointer; position: relative;
    transition: none !important;
    animation: none !important;
}
.order-card:last-child { border-bottom: none; }
.order-card:hover,
.order-card:focus { background: transparent !important; outline: none !important; box-shadow: none !important; border-color: transparent !important; transform: none !important; transition: none !important; }
.order-card.active { background: var(--blush); }
.order-card::before { display: none; }

.oc-left { display: flex; flex-direction: column; gap: 4px; min-width: 0; flex: 1; }
.oc-top  { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; }

.order-id {
    font-family: 'Cormorant Garamond', serif;
    font-size: 19px; font-weight: 700;
    color: #b5546a; line-height: 1;
}
.oc-tags { display: flex; gap: 4px; align-items: center; flex-wrap: wrap; }
.order-customer { font-size: 13px; color: var(--ink); font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 260px; }
.order-customer span { color: var(--muted); font-weight: 400; }
.order-date { font-size: 12px; color: #c0909a; }

.oc-chip {
    display: inline-flex; align-items: center; gap: 3px;
    font-size: 10px; font-weight: 700; letter-spacing: 0.4px;
    text-transform: uppercase; padding: 2px 8px;
    border-radius: 20px; border: 1px solid transparent;
}
.oc-chip-delivery { background: #edf3ff; color: #2b5cc7; border-color: #c3d4ff; }
.oc-chip-pickup   { background: #f4eeff; color: #6d28d9; border-color: #daccf8; }
.oc-chip-gcash    { background: #e8f2ff; color: #1a50b5; border-color: #b5d0f8; }
.oc-chip-cash     { background: #edfaf2; color: #166534; border-color: #a7e8c5; }
.oc-chip-cod      { background: #fdf8e6; color: #925a08; border-color: #ecd88a; }

.oc-amount { text-align: right; padding-left: 10px; flex-shrink: 0; }
.oc-pending-warn {
    display: flex; align-items: center; justify-content: flex-end;
    margin-top: 5px;
    font-size: 11px; font-weight: 700; color: #92400e;
    gap: 3px; white-space: nowrap;
}
.oc-pending-warn svg { flex-shrink: 0; }
.order-amount {
    font-family: 'Cormorant Garamond', serif;
    font-size: 20px; font-weight: 700; color: #3a2020; line-height: 1;
}
.order-items { font-size: 11px; color: var(--muted); margin-top: 2px; }

/* Status pills */
.status-pill {
    display: inline-flex; align-items: center; gap: 3px;
    padding: 2px 9px; border-radius: 20px;
    font-size: 10px; font-weight: 700;
    letter-spacing: 0.6px; text-transform: uppercase;
    white-space: nowrap; border: 1px solid transparent;
}
.status-pill.pending              { background: #fff8e5; color: #966a08; border-color: #f2d465; }
.status-pill.pending_verification { background: #fff8e5; color: #966a08; border-color: #f2d465; }
.status-pill.preparing            { background: #f4eeff; color: #6d28d9; border-color: #daccf8; }
.status-pill.out-for-delivery     { background: #edf3ff; color: #2b5cc7; border-color: #c3d4ff; }
.status-pill.delivered            { background: #edfaf2; color: #166534; border-color: #a7e8c5; }
.status-pill.picked-up            { background: #edfaf2; color: #166534; border-color: #a7e8c5; }
.status-pill.paid                 { background: #edfaf2; color: #166534; border-color: #a7e8c5; }
.status-pill.cancelled            { background: #fff0f3; color: #b81c38; border-color: #f8b4c3; }
.status-pill.payment_rejected     { background: #fff0f3; color: #b81c38; border-color: #f8b4c3; }
.status-pill.ready-for-pickup     { background: #f4eeff; color: #6d28d9; border-color: #daccf8; }

.confirmed-badge {
    display: inline-flex; align-items: center; gap: 4px;
    background: #edfaf2; color: #166534; border: 1px solid #a7e8c5;
    border-radius: 20px; padding: 2px 8px;
    font-size: 10px; font-weight: 700; white-space: nowrap;
}
.empty-state { padding: 48px 24px; text-align: center; color: var(--muted); font-size: 16px; }

/* ── RIGHT: Detail panel ── */
.ls-right {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: var(--cream);
}

/* Detail panel header bar */
.ls-detail-head {
    padding: 16px 28px;
    border-bottom: 1px solid var(--border);
    background: rgba(255,248,242,0.8);
    backdrop-filter: blur(10px);
    flex-shrink: 0;
    display: flex; align-items: center; gap: 14px;
}
.ls-detail-head-title {
    font-family: 'Cormorant Garamond', serif;
    font-size: 26px; font-weight: 700; color: var(--deep-rose);
}
.ls-detail-head-meta { font-size: 13px; color: var(--muted); }

/* Scrollable detail content */
.ls-detail-scroll {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 22px 28px 28px;
}
.ls-detail-scroll::-webkit-scrollbar { width: 5px; }
.ls-detail-scroll::-webkit-scrollbar-track { background: transparent; }
.ls-detail-scroll::-webkit-scrollbar-thumb { background: var(--petal); border-radius: 10px; }

/* Two-column layout inside detail for info + items */
.ls-detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
    margin-bottom: 14px;
}
.ls-detail-full { grid-column: 1 / -1; }

/* Sections */
.adm-modal-section { background: var(--white); border-radius: 14px; padding: 16px 20px; border: 1px solid var(--border); }
.adm-modal-section-title { font-size: 11px; font-weight: 700; color: var(--rose); text-transform: uppercase; letter-spacing: 2.5px; margin-bottom: 12px; }
.adm-modal-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(220,120,70,0.14); font-size: 15px; }
.adm-modal-row:last-child { border-bottom: none; }
.adm-modal-label { color: var(--muted); font-weight: 500; }
.adm-modal-value { font-weight: 600; color: var(--ink); text-align: right; }

.adm-modal-item { display: flex; justify-content: space-between; align-items: flex-start; padding: 11px 0; border-bottom: 1px solid rgba(220,120,70,0.14); font-size: 15px; gap: 10px; }
.adm-modal-item:last-child { border-bottom: none; }
.adm-modal-item-name { font-weight: 600; color: var(--ink); font-size: 15px; }
.adm-modal-item-qty  { font-size: 13px; color: var(--muted); margin-top: 3px; }
.adm-modal-item-price { font-weight: 700; color: var(--deep-rose); white-space: nowrap; font-size: 15px; }

.adm-modal-total { display: flex; justify-content: space-between; align-items: center; padding: 18px 22px; background: linear-gradient(135deg, #f08050, #d05a28); border-radius: 14px; }
.adm-modal-total-label { font-weight: 700; font-size: 16px; color: #fff; }
.adm-modal-total-value { font-family: 'Cormorant Garamond', serif; font-size: 30px; font-weight: 700; color: #fff; }

/* Status updater */
.status-updater { background: var(--white); border-radius: 14px; padding: 16px 20px; border: 1px solid var(--border); }
.status-updater-title { font-size: 11px; font-weight: 700; color: var(--rose); text-transform: uppercase; letter-spacing: 2.5px; margin-bottom: 12px; }
.status-buttons { display: flex; flex-wrap: wrap; gap: 8px; }
.status-btn {
    flex: 1; min-width: 110px;
    padding: 10px 12px;
    border-radius: 10px; border: 2px solid transparent;
    font-family: 'Jost', sans-serif; font-size: 13px; font-weight: 700;
    cursor: pointer; text-align: center;
    background: var(--cream); color: var(--ink);
}
.status-btn.current { cursor: default; }
.status-btn[data-status="Pending"]                  { border-color: #f5d97a; }
.status-btn[data-status="Pending"].current          { background: #fff8e6; color: #a0740a; }
.status-btn[data-status="Preparing"]                { border-color: #d8b4fe; }
.status-btn[data-status="Preparing"].current        { background: #f3e8ff; color: #7e22ce; }
.status-btn[data-status="Out for Delivery"]         { border-color: #a3c2f5; }
.status-btn[data-status="Out for Delivery"].current { background: #e6f0ff; color: #1a4da0; }
.status-btn[data-status="Ready for Pickup"]         { border-color: #d8b4fe; }
.status-btn[data-status="Ready for Pickup"].current { background: #f3e8ff; color: #6b21a8; }
.status-btn[data-status="Delivered"]                { border-color: #b2e8cf; }
.status-btn[data-status="Delivered"].current        { background: #e6f9f0; color: #1a7a4a; }
.status-btn[data-status="Cancelled"]                { border-color: #fca5a5; color: #b81c38; }
.status-btn[data-status="Cancelled"].current        { background: #fff0f3; color: #b81c38; }

.cancel-order-btn {
    width: 100%; padding: 12px 16px; margin-top: 10px;
    border-radius: 12px; border: 2px solid #fca5a5;
    background: #fff0f3; color: #b81c38;
    font-family: 'Jost', sans-serif; font-size: 14px; font-weight: 700;
    cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px;
    transition: background .15s, border-color .15s;
}
.cancel-order-btn:hover { background: #fee2e2; border-color: #f87171; }

.email-note { font-size: 12px; color: var(--muted); margin-top: 10px; display: flex; align-items: center; gap: 5px; }

.confirmed-banner {
    background: #f0fdf4; border: 1.5px solid #86efac;
    border-radius: 12px; padding: 12px 16px;
    color: #15803d; font-size: 14px; font-weight: 600;
    display: flex; align-items: center; gap: 10px;
    margin-bottom: 14px;
}

.adm-item-tags { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 5px; }
.adm-item-tag  { font-size: 12px; background: rgba(220,120,70,0.1); color: var(--deep-rose); border-radius: 10px; padding: 2px 9px; font-weight: 500; }

/* Empty / no-selection state */
.ls-no-selection {
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    height: 100%; text-align: center;
    color: var(--muted); gap: 12px;
}
.ls-no-selection-icon { font-size: 52px; opacity: 0.4; }
.ls-no-selection-text { font-size: 16px; font-weight: 500; }
.ls-no-selection-sub  { font-size: 13px; opacity: 0.7; }

/* Toast */
.toast { position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%) translateY(80px); background: #2d1a22; color: #fff; padding: 12px 24px; border-radius: 50px; font-size: 14px; font-weight: 500; z-index: 999; opacity: 0; transition: all 0.35s cubic-bezier(.34,1.56,.64,1); pointer-events: none; white-space: nowrap; }
.toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }

@keyframes fadeUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

/* Notification badges — added for sidebar alerts */
.nav-badge{display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 5px;border-radius:99px;font-size:10px;font-weight:800;line-height:1;background:#e53e3e;color:#fff;margin-left:auto;flex-shrink:0;animation:badgePop 0.4s cubic-bezier(.34,1.56,.64,1);}
@keyframes badgePop{from{transform:scale(0);opacity:0;}to{transform:scale(1);opacity:1;}}
.adm-icon .pending-dot{position:absolute;top:3px;right:3px;width:9px;height:9px;border-radius:50%;background:#e53e3e;border:2px solid var(--cream);animation:pulseDot 1.6s ease-in-out infinite;}
@keyframes pulseDot{0%,100%{opacity:1;transform:scale(1);}50%{opacity:.55;transform:scale(1.3);}}

/* ── NEW order indicator ── */
.new-order-chip {
    display: inline-flex; align-items: center; gap: 4px;
    background: #e53e3e; color: #fff;
    font-size: 10px; font-weight: 800; letter-spacing: 0.8px; text-transform: uppercase;
    padding: 2px 8px; border-radius: 20px;
    animation: badgePop 0.4s cubic-bezier(.34,1.56,.64,1);
    flex-shrink: 0;
}
.new-order-chip::before { content: '●'; font-size: 7px; animation: pulseDot 1.2s ease-in-out infinite; }
.new-order-banner {
    display: flex; align-items: center; gap: 10px;
    background: #fff5f5; border: 1.5px solid #feb2b2;
    border-radius: 12px; padding: 11px 16px; margin-bottom: 14px;
    font-size: 14px; font-weight: 600; color: #c53030;
    animation: badgePop 0.35s cubic-bezier(.34,1.56,.64,1);
}
.pending-reminder-banner {
    display: flex; align-items: center; gap: 10px;
    background: #fffbeb; border: 1.5px solid #fcd34d;
    border-radius: 12px; padding: 11px 16px; margin-bottom: 14px;
    font-size: 14px; font-weight: 600; color: #92400e;
    animation: badgePop 0.35s cubic-bezier(.34,1.56,.64,1);
}

</style>
</head>
<body>

<!-- ══════ NAV ══════ -->
<?php $activePage = 'orders'; include 'admin_navbar.php'; ?>

<!-- ══════ LANDSCAPE SHELL ══════ -->
<div class="ls-shell">

  <!-- ── LEFT: Orders list ── -->
  <div class="ls-left">
    <div class="ls-left-head">
      <div class="ls-left-title-row">
        <div class="ls-page-title">Orders</div>
        <div class="ls-page-count"><?= $totalOrders ?> order<?= $totalOrders != 1 ? 's' : '' ?></div>
      </div>
      <div class="search-wrap">
        <input type="text" id="ordersSearch" placeholder="Search by order ID, name, username or email…">
      </div>
    </div>
    <div class="ls-orders-scroll">
      <?php if ($totalOrders > 0): ?>
        <?php while ($order = $orders->fetch_assoc()):
          $rawStatus   = $order['status'] ?? 'Pending';
          $statusClass = strtolower(str_replace([' ', '_'], ['-', '_'], $rawStatus));
          $isPickup    = strtolower($order['delivery_mode'] ?? '') === 'pickup';
          $payMethod   = strtolower($order['payment_method'] ?? '');
          $isActive    = $modalOrder && $modalOrder['id'] == $order['id'];
          $isNew       = in_array((int)$order['id'], $unseenIds);
        ?>
        <div class="order-card <?= $isActive ? 'active' : '' ?>"
             data-filter="<?= strtolower('ord-' . $order['id'] . ' ' . ($order['email'] ?? '') . ' ' . ($order['username'] ?? '') . ' ' . ($order['full_name'] ?? '')) ?>"
             onclick="selectOrder(<?= $order['id'] ?>)">
          <div class="oc-left">
            <div class="oc-top">
              <span class="order-id">ORD-<?= str_pad($order['id'], 3, '0', STR_PAD_LEFT) ?></span>
              <?php if ($isNew): ?>
                <span class="new-order-chip">NEW</span>
              <?php endif; ?>
              <div class="oc-tags">
                <span class="status-pill <?= $statusClass ?>"><?= htmlspecialchars($rawStatus) ?></span>
                <span class="oc-chip <?= $isPickup ? 'oc-chip-pickup' : 'oc-chip-delivery' ?>">
                  <?= $isPickup ? '🏪 Pickup' : '🚗 Delivery' ?>
                </span>
                <span class="oc-chip oc-chip-<?= $payMethod ?>"><?= strtoupper($payMethod) ?></span>
                <?php if (!empty($order['customer_confirmed'])): ?>
                  <span class="confirmed-badge">✅ Confirmed</span>
                <?php endif; ?>
              </div>
            </div>
            <div class="order-customer">
              <?= htmlspecialchars(!empty($order['full_name']) ? $order['full_name'] : ($order['username'] ?? 'Guest')) ?>
              <?php if (!empty($order['full_name']) && !empty($order['username'])): ?>
                <span>(<?= htmlspecialchars($order['username']) ?>)</span>
              <?php endif; ?>
              <?php if ($order['email']): ?>
                <span>· <?= htmlspecialchars($order['email']) ?></span>
              <?php endif; ?>
            </div>
            <div class="order-date"><?= date('M j, Y · g:i A', strtotime($order['created_at'])) ?></div>
          </div>
          <div class="oc-amount">
            <div class="order-amount">₱<?= number_format($order['total_price'], 2) ?></div>
            <div class="order-items"><?= intval($order['item_count']) ?> item<?= $order['item_count'] != 1 ? 's' : '' ?></div>
            <?php if (strtolower($order['status'] ?? '') === 'pending'): ?>
            <div class="oc-pending-warn">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
              Not preparing
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="empty-state">No orders yet 🌱</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── RIGHT: Detail panel ── -->
  <div class="ls-right">
    <?php if ($modalOrder):
      $rawStatus   = $modalOrder['status'] ?? 'Pending';
      $statusClass = strtolower(str_replace(' ', '-', $rawStatus));
      $hasEmail    = !empty($modalOrder['email']);
      $isPickupOrder     = strtolower($modalOrder['delivery_mode'] ?? '') === 'pickup';
      $statusOptions     = $isPickupOrder
          ? ['Pending' => '🌸', 'Preparing' => '✂️', 'Ready for Pickup' => '🏪']
          : ['Pending' => '🌸', 'Preparing' => '✂️', 'Out for Delivery' => '🚗', 'Delivered' => '💐'];
      $isPaymentRejected = in_array(strtolower($rawStatus), ['payment_rejected', 'pending_verification']);
      $isCancelled       = strtolower($rawStatus) === 'cancelled';
    ?>
    <!-- Detail header -->
    <div class="ls-detail-head">
      <div>
        <div class="ls-detail-head-title">ORD-<?= str_pad($modalOrder['id'], 3, '0', STR_PAD_LEFT) ?></div>
        <div class="ls-detail-head-meta">Placed on <?= date('F j, Y \a\t g:i A', strtotime($modalOrder['created_at'])) ?></div>
      </div>
      <?php if ($modalIsNew): ?>
        <span class="new-order-chip" style="font-size:11px;padding:4px 12px;">NEW</span>
      <?php endif; ?>
      <span class="status-pill <?= $statusClass ?>" style="font-size:12px;padding:5px 14px;"><?= htmlspecialchars($rawStatus) ?></span>
    </div>

    <!-- Scrollable detail body -->
    <div class="ls-detail-scroll">

      <?php if ($modalIsNew): ?>
      <div class="new-order-banner">
        🆕 This is a new order — you're viewing it for the first time!
      </div>
      <?php endif; ?>
      <?php if (strtolower($rawStatus) === 'pending' && !$isCancelled): ?>
      <div class="pending-reminder-banner">
        ⏰ This order hasn't been prepared yet — don't forget to click <strong>Preparing</strong> below!
      </div>
      <?php endif; ?>
      <?php if (!empty($modalOrder['customer_confirmed'])): ?>
      <div class="confirmed-banner">
        <?= $isPickupOrder ? '✅ Customer confirmed pickup — the bouquet was collected successfully.' : '✅ Customer confirmed delivery — the bouquet was received successfully.' ?>
      </div>
      <?php endif; ?>

      <div class="ls-detail-grid">

        <!-- Customer info -->
        <div class="adm-modal-section">
          <div class="adm-modal-section-title">👤 Customer</div>
          <div class="adm-modal-row"><span class="adm-modal-label">Name</span><span class="adm-modal-value"><?= htmlspecialchars($modalOrder['username'] ?? 'Guest') ?></span></div>
          <div class="adm-modal-row"><span class="adm-modal-label">Email</span><span class="adm-modal-value"><?= htmlspecialchars($modalOrder['email'] ?? '—') ?></span></div>
          <div class="adm-modal-row"><span class="adm-modal-label">Ref #</span><span class="adm-modal-value">#<?= htmlspecialchars($modalOrder['user_order_no'] ?? $modalOrder['id']) ?></span></div>
        </div>

        <!-- Order info -->
        <div class="adm-modal-section">
          <div class="adm-modal-section-title">📋 Order Info</div>
          <div class="adm-modal-row"><span class="adm-modal-label">Payment</span><span class="adm-modal-value"><?= htmlspecialchars(strtoupper($modalOrder['payment_method'] ?? 'N/A')) ?></span></div>
          <div class="adm-modal-row"><span class="adm-modal-label">Mode</span><span class="adm-modal-value"><?= htmlspecialchars(ucfirst($modalOrder['delivery_mode'] ?? 'N/A')) ?></span></div>
          <div class="adm-modal-row"><span class="adm-modal-label">Status</span><span class="adm-modal-value"><span class="status-pill <?= $statusClass ?>"><?= htmlspecialchars($rawStatus) ?></span></span></div>
        </div>

        <!-- Status updater — full width -->
        <div class="adm-modal-section ls-detail-full">
          <div class="status-updater-title">📦 Update Order Status</div>
          <form method="POST" action="admin_orders.php" id="statusForm">
            <input type="hidden" name="update_status" value="1">
            <input type="hidden" name="order_id" value="<?= $modalOrder['id'] ?>">
            <input type="hidden" name="new_status" id="newStatusInput" value="">
            <div class="status-buttons">
              <?php foreach ($statusOptions as $s => $icon):
                $isCurrent = ($s === $rawStatus);
              ?>
                <button type="button"
                  class="status-btn <?= $isCurrent ? 'current' : '' ?>"
                  data-status="<?= $s ?>"
                  onclick="<?= $isCurrent ? '' : "setStatus('$s')" ?>"
                  <?= $isCurrent ? 'disabled' : '' ?>>
                  <?= $icon ?> <?= $s ?>
                </button>
              <?php endforeach; ?>
            </div>
            <?php if ($hasEmail): ?>
              <div class="email-note">📧 Customer will be notified by email when status changes.</div>
            <?php else: ?>
              <div class="email-note" style="color:#d05a28;">⚠️ No email on file — customer won't be notified.</div>
            <?php endif; ?>
            <?php if ($isPaymentRejected && !$isCancelled): ?>
              <button type="button" class="cancel-order-btn" onclick="openCancelConfirmPopup()">
                ❌ Cancel Order (Payment Rejected)
              </button>
            <?php elseif ($isCancelled): ?>
              <div class="email-note" style="color:#b81c38;margin-top:8px;">🚫 This order has been cancelled.</div>
            <?php endif; ?>
          </form>
        </div>

        <!-- Items ordered — full width -->
        <div class="adm-modal-section ls-detail-full">
          <div class="adm-modal-section-title">🌸 Items Ordered</div>
          <?php if (!empty($modalItems)): ?>
            <?php foreach ($modalItems as $item): ?>
            <div class="adm-modal-item">
              <div style="flex:1;">
                <div class="adm-modal-item-name"><?= htmlspecialchars($item['product_name']) ?></div>
                <div class="adm-modal-item-qty">Qty: <?= intval($item['quantity']) ?> &times; ₱<?= number_format($item['price'], 2) ?></div>
                <?php
                  $tags = [];
                  if (!empty($item['wrapper'])) $tags[] = '🎀 ' . htmlspecialchars($item['wrapper']);
                  if (!empty($item['ribbon']))  $tags[] = '🎗️ ' . htmlspecialchars($item['ribbon']);
                  if (!empty($item['addons'])) {
                      $ao = json_decode($item['addons'], true);
                      if (!empty($ao)) foreach ($ao as $n => $p) $tags[] = '🍫 ' . htmlspecialchars($n);
                  }
                ?>
                <?php if (!empty($tags)): ?>
                  <div class="adm-item-tags">
                    <?php foreach ($tags as $t): ?><span class="adm-item-tag"><?= $t ?></span><?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
              <div class="adm-modal-item-price">₱<?= number_format($item['price'] * $item['quantity'], 2) ?></div>
            </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p style="color:var(--muted);font-size:14px;text-align:center;padding:10px 0;">No items found.</p>
          <?php endif; ?>
        </div>

      </div><!-- .ls-detail-grid -->

      <div class="adm-modal-total">
        <span class="adm-modal-total-label">Order Total</span>
        <span class="adm-modal-total-value">₱<?= number_format($modalOrder['total_price'], 2) ?></span>
      </div>

    </div><!-- .ls-detail-scroll -->

    <?php else: ?>
    <!-- No order selected yet -->
    <div class="ls-no-selection">
      <div class="ls-no-selection-icon">🌸</div>
      <div class="ls-no-selection-text">No order selected</div>
      <div class="ls-no-selection-sub">Click any order on the left to view its details here.</div>
    </div>
    <?php endif; ?>
  </div><!-- .ls-right -->

</div><!-- .ls-shell -->

<!-- Toast -->
<div class="toast" id="toast"></div>

<!-- Status Confirm Popup -->
<div id="sc-popup-overlay" onclick="if(event.target===this)closeStatusConfirmPopup()" style="display:none;position:fixed;inset:0;z-index:9999;align-items:center;justify-content:center;padding:1rem;background:rgba(100,40,50,0.25);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);">
  <div style="background:#fff;border-radius:26px;padding:2.2rem 2rem 1.8rem;max-width:340px;width:100%;text-align:center;position:relative;overflow:hidden;box-shadow:0 24px 70px rgba(220,110,80,0.22),0 4px 20px rgba(0,0,0,0.06);border:1.5px solid #fdd5bc;animation:scPopIn 0.3s cubic-bezier(0.34,1.56,0.64,1);">
    <div style="position:absolute;top:0;left:0;right:0;height:5px;background:linear-gradient(90deg,#f9c49a,#f08050,#f9c49a);border-radius:26px 26px 0 0;"></div>
    <span style="position:absolute;top:14px;left:18px;font-size:1.2rem;pointer-events:none;animation:scFloat 3s ease-in-out infinite alternate;">&#127800;</span>
    <span style="position:absolute;top:12px;right:20px;font-size:1rem;pointer-events:none;animation:scFloat 3s ease-in-out infinite alternate;animation-delay:0.6s;">&#127825;</span>
    <span style="position:absolute;bottom:60px;left:14px;font-size:0.85rem;pointer-events:none;animation:scFloat 3s ease-in-out infinite alternate;animation-delay:1s;">&#127802;</span>
    <span style="position:absolute;bottom:55px;right:16px;font-size:0.9rem;pointer-events:none;animation:scFloat 3s ease-in-out infinite alternate;animation-delay:0.3s;">&#10024;</span>
    <div style="font-size:2.8rem;line-height:1;margin-bottom:0.8rem;filter:drop-shadow(0 4px 10px rgba(220,110,80,0.25));">&#128230;</div>
    <h2 style="font-family:'Cormorant Garamond',serif;font-size:1.55rem;font-weight:700;color:#4a2c1e;margin:0 0 0.4rem;line-height:1.2;">Update Order Status?</h2>
    <p style="font-size:0.83rem;color:#b08070;line-height:1.6;margin:0 0 0.5rem;">
      Changing status to<br>
      <strong style="color:#e07050;font-size:0.96rem;" id="sc-status-name"></strong>
    </p>
    <p style="font-size:0.78rem;color:#c0a090;margin:0 0 1.6rem;">&#128231; The customer will be notified by email.</p>
    <div style="display:flex;gap:10px;justify-content:center;">
      <button onclick="closeStatusConfirmPopup()" style="flex:1;padding:0.7rem 1rem;border-radius:50px;border:1.5px solid #fdd5bc;background:#fff;color:#c07050;font-family:'Jost',sans-serif;font-size:0.85rem;font-weight:600;cursor:pointer;">Cancel &#128584;</button>
      <button id="sc-confirm-btn" style="flex:1;padding:0.7rem 1rem;border-radius:50px;border:none;background:linear-gradient(135deg,#f9c49a,#f0a072);color:#fff;font-family:'Jost',sans-serif;font-size:0.85rem;font-weight:700;cursor:pointer;box-shadow:0 4px 16px rgba(240,150,100,0.35);">Yes, update! &#127825;</button>
    </div>
  </div>
</div>

<style>
#sc-popup-overlay.open { display: flex !important; animation: scFadeIn 0.2s ease; }
@keyframes scFadeIn  { from { opacity:0; } to { opacity:1; } }
@keyframes scPopIn   { from { opacity:0; transform:scale(0.84) translateY(20px); } to { opacity:1; transform:scale(1) translateY(0); } }
@keyframes scFloat   { from { transform:translateY(0) rotate(-8deg); } to { transform:translateY(-7px) rotate(8deg); } }
</style>

<script>
document.getElementById('ordersSearch').addEventListener('input', function() {
  const filter = this.value.toLowerCase().trim();
  document.querySelectorAll('.order-card').forEach(card => {
    card.style.display = card.getAttribute('data-filter').includes(filter) ? 'flex' : 'none';
  });
});

function selectOrder(orderId) {
  window.location.href = 'admin_orders.php?order_id=' + orderId;
}

function setStatus(newStatus) {
  openStatusConfirmPopup(newStatus);
}

function openStatusConfirmPopup(newStatus) {
  document.getElementById('sc-status-name').textContent = newStatus;
  document.getElementById('sc-popup-overlay').classList.add('open');
  document.getElementById('sc-confirm-btn').onclick = function() {
    document.getElementById('sc-popup-overlay').classList.remove('open');
    document.getElementById('newStatusInput').value = newStatus;
    document.getElementById('statusForm').submit();
  };
}
function closeStatusConfirmPopup() {
  document.getElementById('sc-popup-overlay').classList.remove('open');
}

function openCancelConfirmPopup() {
  document.getElementById('cancel-popup-overlay').classList.add('open');
}
function closeCancelConfirmPopup() {
  document.getElementById('cancel-popup-overlay').classList.remove('open');
}
document.addEventListener('DOMContentLoaded', function() {
  var cancelConfirmBtn = document.getElementById('cancel-confirm-btn');
  if (cancelConfirmBtn) {
    cancelConfirmBtn.addEventListener('click', function() {
      closeCancelConfirmPopup();
      document.getElementById('newStatusInput').value = 'Cancelled';
      document.getElementById('statusForm').submit();
    });
  }
});

// Show toast if redirected after update
<?php if (isset($_GET['updated'])): ?>
showToast('✅ Status updated & customer notified!');
<?php endif; ?>

function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 3500);
}
</script>

<!-- ── Cancel Order Confirmation Popup ── -->
<div id="cancel-popup-overlay" onclick="if(event.target===this)closeCancelConfirmPopup()" style="display:none;position:fixed;inset:0;z-index:9999;align-items:center;justify-content:center;padding:1rem;background:rgba(80,10,20,0.32);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);">
  <div style="background:#fff;border-radius:26px;padding:2.2rem 2rem 1.8rem;max-width:360px;width:100%;text-align:center;position:relative;overflow:hidden;box-shadow:0 24px 70px rgba(180,20,40,0.22),0 4px 20px rgba(0,0,0,0.07);border:1.5px solid #fecaca;animation:scPopIn 0.3s cubic-bezier(0.34,1.56,0.64,1);">
    <div style="position:absolute;top:0;left:0;right:0;height:5px;background:linear-gradient(90deg,#fca5a5,#ef4444,#fca5a5);border-radius:26px 26px 0 0;"></div>
    <div style="font-size:2.8rem;line-height:1;margin-bottom:0.8rem;">❌</div>
    <h2 style="font-family:'Cormorant Garamond',serif;font-size:1.55rem;font-weight:700;color:#4a2c1e;margin:0 0 0.5rem;line-height:1.2;">Cancel This Order?</h2>
    <p style="font-size:0.84rem;color:#b08070;line-height:1.7;margin:0 0 0.4rem;">
      This will mark the order as <strong style="color:#dc2626;">Cancelled</strong> due to rejected or unverified payment.
    </p>
    <p style="font-size:0.78rem;color:#c0a090;margin:0 0 1.6rem;">📧 The customer will be notified by email.</p>
    <div style="display:flex;gap:10px;justify-content:center;">
      <button onclick="closeCancelConfirmPopup()" style="flex:1;padding:0.7rem 1rem;border-radius:50px;border:1.5px solid #fecaca;background:#fff;color:#dc2626;font-family:'Jost',sans-serif;font-size:0.85rem;font-weight:600;cursor:pointer;">Keep Order 🙈</button>
      <button id="cancel-confirm-btn" style="flex:1;padding:0.7rem 1rem;border-radius:50px;border:none;background:linear-gradient(135deg,#f87171,#dc2626);color:#fff;font-family:'Jost',sans-serif;font-size:0.85rem;font-weight:700;cursor:pointer;box-shadow:0 4px 16px rgba(220,38,38,0.35);">Yes, Cancel ❌</button>
    </div>
  </div>
</div>
<style>
#cancel-popup-overlay.open { display: flex !important; animation: scFadeIn 0.2s ease; }
</style>
<!-- ── Auto-refresh (60s) ── -->
<div id="refresh-bar" style="
  position: fixed; bottom: 20px; right: 20px; z-index: 8000;
  display: flex; align-items: center; gap: 10px;
  background: rgba(45,26,34,0.88); backdrop-filter: blur(10px);
  color: #fff; font-family: 'Jost', sans-serif; font-size: 12px; font-weight: 500;
  padding: 8px 14px 8px 12px; border-radius: 50px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.25);
  opacity: 0.85; transition: opacity 0.2s;
" onmouseenter="this.style.opacity=1" onmouseleave="this.style.opacity=0.85">
  <svg id="refresh-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#f9a8c9" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
    <polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/>
    <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
  </svg>
  <span>Refreshing in <strong id="refresh-countdown">60</strong>s</span>
  <button onclick="cancelAutoRefresh()" title="Cancel auto-refresh" style="
    background: none; border: none; color: rgba(255,255,255,0.45);
    cursor: pointer; font-size: 14px; line-height: 1; padding: 0; margin-left: 2px;
    transition: color 0.15s;
  " onmouseenter="this.style.color='#fff'" onmouseleave="this.style.color='rgba(255,255,255,0.45)'">&#x2715;</button>
</div>

<script>
(function () {
  const INTERVAL = 60;
  let remaining = INTERVAL;
  let timer = null;
  let paused = false;

  const bar       = document.getElementById('refresh-bar');
  const countdown = document.getElementById('refresh-countdown');
  const icon      = document.getElementById('refresh-icon');

  function tick() {
    if (paused) return;
    remaining--;
    countdown.textContent = remaining;
    if (remaining <= 0) doRefresh();
  }

  function doRefresh() {
    icon.style.transition = 'transform 0.6s ease';
    icon.style.transform  = 'rotate(360deg)';
    setTimeout(() => { window.location.reload(); }, 600);
  }

  function resetTimer() {
    remaining = INTERVAL;
    countdown.textContent = remaining;
  }

  window.cancelAutoRefresh = function () {
    clearInterval(timer);
    paused = true;
    bar.innerHTML = '<span style="font-family:&apos;Jost&apos;,sans-serif;font-size:12px;color:#f9a8c9;">Auto-refresh off</span>';
    setTimeout(() => { bar.style.display = 'none'; }, 2000);
  };

  function isModalOpen() {
    const overlay = document.getElementById('cancel-popup-overlay');
    return overlay && overlay.classList.contains('open');
  }

  ['click', 'keydown'].forEach(ev =>
    document.addEventListener(ev, resetTimer, { passive: true })
  );

  timer = setInterval(function () {
    if (isModalOpen()) { resetTimer(); return; }
    tick();
  }, 1000);
})();
</script>
</body>
</html>