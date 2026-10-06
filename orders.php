<?php
session_start();
include "config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$uid = (int) $_SESSION['user_id'];
// ── Handle customer delivery/pickup confirmation ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delivery'])) {
    $oid = (int) $_POST['order_id'];
    if ($oid > 0) {
        // Delivery: allow confirmation when Out for Delivery OR already Delivered (admin-set) but not yet customer-confirmed
        $chkDel = $conn->prepare("
            SELECT id FROM orders
            WHERE id=? AND user_id=? AND delivery_mode='delivery'
              AND status='Delivered'
              AND customer_confirmed=0
        ");
        $chkDel->bind_param('ii', $oid, $uid);
        $chkDel->execute();
        if ($chkDel->get_result()->num_rows > 0) {
            $upd = $conn->prepare("UPDATE orders SET status='Delivered', customer_confirmed=1 WHERE id=?");
            $upd->bind_param('i', $oid);
            $upd->execute();
            $upd->close();
        }
        $chkDel->close();

        // Pickup: allow confirmation when Ready for Pickup and not yet customer-confirmed
        $chkPick = $conn->prepare("
            SELECT id FROM orders
            WHERE id=? AND user_id=? AND delivery_mode='pickup'
              AND status='Ready for Pickup'
              AND customer_confirmed=0
        ");
        $chkPick->bind_param('ii', $oid, $uid);
        $chkPick->execute();
        if ($chkPick->get_result()->num_rows > 0) {
            $upd = $conn->prepare("UPDATE orders SET status='Picked Up', customer_confirmed=1 WHERE id=?");
            $upd->bind_param('i', $oid);
            $upd->execute();
            $upd->close();
        }
        $chkPick->close();
    }
    header("Location: orders.php?confirmed=$oid");
    exit();
}

$result = $conn->query("
    SELECT o.id, o.total_price, o.created_at, o.status, o.payment_method,
           o.delivery_mode, o.customer_confirmed,
           COUNT(oi.id) AS item_count
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.id
    WHERE o.user_id = $uid
    GROUP BY o.id, o.total_price, o.created_at, o.status, o.payment_method,
             o.delivery_mode, o.customer_confirmed
    ORDER BY o.created_at DESC
");

$orders = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}

// ── Detect "Out for Delivery" and "Ready for Pickup" orders for notification popups ──
$hasOutForDelivery = false;
$outForDeliveryIds = [];
$hasReadyForPickup = false;
$readyForPickupIds = [];
foreach ($orders as $o) {
    if (strtolower($o['status']) === 'out for delivery' && empty($o['customer_confirmed'])) {
        $hasOutForDelivery = true;
        $outForDeliveryIds[] = 'ORD-' . str_pad($o['id'], 3, '0', STR_PAD_LEFT);
    }
    if (strtolower($o['status']) === 'ready for pickup' && empty($o['customer_confirmed'])) {
        $hasReadyForPickup = true;
        $readyForPickupIds[] = 'ORD-' . str_pad($o['id'], 3, '0', STR_PAD_LEFT);
    }
}

$orderItems = [];
if (!empty($orders)) {
    $orderIds   = implode(',', array_column($orders, 'id'));
    $itemsQuery = $conn->query("
        SELECT oi.order_id, oi.product_name, oi.quantity, oi.price,
               oi.wrapper, oi.ribbon, oi.addons
        FROM order_items oi
        WHERE oi.order_id IN ($orderIds)
    ");
    if ($itemsQuery) {
        while ($row = $itemsQuery->fetch_assoc()) {
            $orderItems[$row['order_id']][] = $row;
        }
    }
}

function formatAddons(?string $json): string {
    if (!$json) return '';
    $arr = json_decode($json, true);
    if (empty($arr)) return '';
    $parts = [];
    foreach ($arr as $name => $price) {
        $parts[] = htmlspecialchars($name) . ' (₱' . number_format((float)$price, 2) . ')';
    }
    return implode(', ', $parts);
}

function statusColor(string $s): string {
    return match(strtolower($s)) {
        'delivered'            => '#22c55e',
        'out for delivery'     => '#3b82f6',
        'preparing'            => '#a855f7',
        'pending'              => '#f59e0b',
        'pending_verification' => '#f59e0b',
        'paid'                 => '#22c55e',
        'payment_rejected'     => '#ef4444',
        'cancelled'            => '#dc2626',
        default                => '#9b8e84',
    };
}

function statusIcon(string $s): string {
    return match(strtolower($s)) {
        'delivered'            => '✅',
        'out for delivery'     => '🚗',
        'preparing'            => '✂️',
        'pending'              => '⏳',
        'pending_verification' => '⏳',
        'paid'                 => '✅',
        'payment_rejected'     => '❌',
        'cancelled'            => '🚫',
        default                => '•',
    };
}

function statusLabel(string $s): string {
    return match(strtolower($s)) {
        'pending_verification' => 'Pending Verification',
        'payment_rejected'     => 'Payment Rejected',
        'out for delivery'     => 'Out for Delivery',
        'cancelled'            => 'Cancelled',
        default                => ucwords($s),
    };
}

// Returns step index for the order progress tracker
// Delivery:  Pending(0) → Preparing(1) → Out for Delivery(2) → Delivered(3)
// Pickup:    Pending(0) → Preparing(1) → Ready for Pickup(2)
function orderStep(string $s): int {
    return match(strtolower($s)) {
        'pending', 'pending_verification', 'paid' => 0,
        'preparing'                               => 1,
        'out for delivery'                        => 2,
        'ready for pickup'                        => 2,
        'delivered'                               => 3,
        default                                   => 0,
    };
}

function gcashPaymentInfo(string $status): ?array {
    return match(strtolower($status)) {
        'pending_verification' => ['color'=>'#f59e0b','bg'=>'#fffbeb','border'=>'#fde68a','icon'=>'⏳','label'=>'Pending Verification','desc'=>'Your GCash payment is being reviewed by our team. We\'ll confirm it shortly.'],
        'paid'                 => ['color'=>'#16a34a','bg'=>'#f0fdf4','border'=>'#bbf7d0','icon'=>'✅','label'=>'Payment Confirmed','desc'=>'Your GCash payment has been verified and confirmed. Thank you!'],
        'payment_rejected'     => ['color'=>'#dc2626','bg'=>'#fef2f2','border'=>'#fecaca','icon'=>'❌','label'=>'Payment Rejected','desc'=>'Your GCash payment could not be verified. Please contact us for assistance.'],
        'cancelled'            => ['color'=>'#dc2626','bg'=>'#fef2f2','border'=>'#fecaca','icon'=>'🚫','label'=>'Order Cancelled','desc'=>'This order has been cancelled due to a rejected or unverified payment. Please contact us if you need further help.'],
        default                => null,
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Orders — Precious Satin Bouquet</title>
<link rel="icon" type="image/jpeg" href="images/logo/SB.png">
<link rel="stylesheet" href="style.css">

<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400;1,500&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>

  /* ─── Page shell ─── */
  .ob-page {
    min-height: calc(100vh - 70px);
    background: #fce8ef;
    font-family: 'DM Sans', sans-serif;
    position: relative;
    overflow: hidden;
  }

  /* ─── Landscape two-column wrapper ─── */
  .ob-layout {
    display: flex;
    height: calc(100vh - 70px);
    overflow: hidden;
  }

  /* ─── LEFT SIDEBAR ─── */
  .ob-sidebar {
    width: 340px;
    min-width: 300px;
    max-width: 360px;
    background: #fff;
    border-right: 1px solid #fce0ea;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    flex-shrink: 0;
  }

  .ob-sidebar-head {
    padding: 1.4rem 1.5rem 1rem;
    border-bottom: 1px solid #fce0ea;
    background: #fff;
    flex-shrink: 0;
  }

  .ob-heading {
    font-family: 'Cormorant Garamond', serif;
    font-size: 2.2rem;
    font-weight: 400;
    color: #2c1f14;
    letter-spacing: -0.01em;
    line-height: 1.1;
    margin: 0;
  }

  .ob-heading em {
    font-style: italic;
    color: #c07a8a;
  }

  .ob-sub {
    font-size: 0.82rem;
    color: #b08a98;
    margin: 0.25rem 0 0;
    font-weight: 400;
  }

  /* Search bar */
  .ob-search-wrap {
    padding: 0.75rem 1.2rem;
    border-bottom: 1px solid #fce0ea;
    flex-shrink: 0;
  }

  .ob-search {
    width: 100%;
    box-sizing: border-box;
    background: #fdf2f5;
    border: 1px solid #fce0ea;
    border-radius: 10px;
    padding: 0.5rem 0.9rem 0.5rem 2.2rem;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.82rem;
    color: #2c1f14;
    outline: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 20 20' fill='none' stroke='%23c07a8a' stroke-width='2'%3E%3Ccircle cx='9' cy='9' r='6'/%3E%3Cpath d='M15 15l-3.5-3.5'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: 0.7rem center;
    transition: border-color 0.18s;
  }

  .ob-search:focus { border-color: #e8a0b4; }
  .ob-search::placeholder { color: #c8a0b0; }

  /* Sidebar order list */
  .ob-sidebar-list {
    flex: 1;
    overflow-y: auto;
    padding: 0.5rem 0;
  }

  .ob-sidebar-list::-webkit-scrollbar { width: 4px; }
  .ob-sidebar-list::-webkit-scrollbar-thumb { background: #fce0ea; border-radius: 4px; }

  /* Sidebar item */
  .ob-list-item {
    padding: 0.85rem 1.5rem;
    cursor: pointer;
    border-left: 3px solid transparent;
    transition: background 0.15s, border-color 0.15s;
    border-bottom: 1px solid #fdf0f4;
  }

  .ob-list-item:hover {
    background: #fff5f8;
  }

  .ob-list-item.active {
    background: #fef5f8;
    border-left-color: #c07a8a;
  }

  .ob-list-item-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4px;
  }

  .ob-list-id {
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.2rem;
    font-weight: 600;
    color: #2c1f14;
  }

  .ob-list-price {
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.1rem;
    font-weight: 600;
    color: #2c1f14;
  }

  .ob-list-item-bottom {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
  }

  .ob-list-date {
    font-size: 0.76rem;
    color: #b08a98;
  }

  .ob-list-tag {
    font-size: 0.68rem;
    font-weight: 600;
    padding: 2px 7px;
    border-radius: 10px;
    border: 1.5px solid;
    letter-spacing: 0.04em;
    text-transform: uppercase;
  }

  .ob-list-count {
    font-size: 0.72rem;
    color: #b08a98;
    margin-left: auto;
  }

  /* ─── RIGHT DETAIL PANEL ─── */
  .ob-detail-panel {
    flex: 1;
    overflow-y: auto;
    background: #fce8ef;
    display: flex;
    flex-direction: column;
  }

  .ob-detail-panel::-webkit-scrollbar { width: 5px; }
  .ob-detail-panel::-webkit-scrollbar-thumb { background: #f4b8c8; border-radius: 4px; }

  /* Empty / no-selection state */
  .ob-panel-empty {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem 2rem;
    text-align: center;
    gap: 1rem;
  }

  .ob-panel-empty-icon { font-size: 3.5rem; opacity: 0.5; }

  .ob-panel-empty-title {
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.6rem;
    font-weight: 400;
    color: #2c1f14;
    margin: 0;
  }

  .ob-panel-empty-sub {
    font-size: 0.85rem;
    color: #b08a98;
    margin: 0;
  }

  /* Detail content */
  .ob-detail-content {
    display: none;
    padding: 2rem 2.2rem 3rem;
    flex-direction: column;
    gap: 1.4rem;
  }

  .ob-detail-content.visible {
    display: flex;
  }

  /* Detail header */
  .ob-detail-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
  }

  .ob-detail-order-id {
    font-family: 'Cormorant Garamond', serif;
    font-size: 2rem;
    font-weight: 600;
    color: #2c1f14;
    line-height: 1;
  }

  .ob-detail-date {
    font-size: 0.82rem;
    color: #b08a98;
    margin-top: 2px;
  }

  /* Two-column info grid */
  .ob-detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
  }

  .ob-detail-card {
    background: #fff;
    border-radius: 16px;
    padding: 1.2rem 1.4rem;
    border: 1px solid rgba(240,190,205,0.4);
  }

  .ob-detail-card-title {
    font-size: 0.68rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.14em;
    color: #c07a8a;
    margin-bottom: 0.9rem;
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .ob-detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.35rem 0;
    border-bottom: 1px solid #fdf0f4;
  }

  .ob-detail-row:last-child { border-bottom: none; }

  .ob-detail-key {
    font-size: 0.8rem;
    color: #b08a98;
    font-weight: 500;
  }

  .ob-detail-val {
    font-size: 0.85rem;
    color: #2c1f14;
    font-weight: 600;
    text-align: right;
  }

  /* Status badge inside detail */
  .ob-status-badge {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 3px 11px;
    border-radius: 20px;
    border: 1.5px solid;
    letter-spacing: 0.05em;
    text-transform: uppercase;
  }

  /* Progress tracker */
  .ob-progress-card {
    background: #fff;
    border-radius: 16px;
    padding: 1.2rem 1.4rem;
    border: 1px solid rgba(240,190,205,0.4);
  }

  .ob-section-label {
    font-size: 0.68rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.14em;
    color: #c07a8a;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .ob-progress {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    position: relative;
    padding: 0 4px;
  }

  .ob-progress::before {
    content: '';
    position: absolute;
    top: 18px;
    left: 18px;
    right: 18px;
    height: 2px;
    background: #fce0ea;
    z-index: 0;
  }

  .ob-progress-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    flex: 1;
    position: relative;
    z-index: 1;
  }

  .ob-progress-dot {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: 2px solid #fce0ea;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    transition: all 0.3s ease;
    color: #d4a0b0;
  }

  .ob-progress-step.done .ob-progress-dot {
    background: #22c55e;
    border-color: #22c55e;
    color: #fff;
    box-shadow: 0 0 0 4px rgba(34,197,94,0.15);
  }

  .ob-progress-step.active .ob-progress-dot {
    background: #c07a8a;
    border-color: #c07a8a;
    color: #fff;
    box-shadow: 0 0 0 4px rgba(192,122,138,0.2);
  }

  .ob-progress-step.future .ob-progress-dot {
    background: #fce8ef;
    border-color: #fce0ea;
    color: #e0b0c0;
  }

  .ob-progress-label {
    font-size: 0.65rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: #d4a0b0;
    text-align: center;
    line-height: 1.3;
    max-width: 72px;
  }

  .ob-progress-step.done .ob-progress-label  { color: #22c55e; }
  .ob-progress-step.active .ob-progress-label { color: #c07a8a; }

  .ob-progress-connector {
    position: absolute;
    top: 18px;
    left: calc(50% + 18px);
    right: calc(-50% + 18px);
    height: 2px;
    background: #fce0ea;
    z-index: 0;
  }

  .ob-progress-step.done .ob-progress-connector { background: #22c55e; }

  /* Items card */
  .ob-items-card {
    background: #fff;
    border-radius: 16px;
    padding: 1.2rem 1.4rem;
    border: 1px solid rgba(240,190,205,0.4);
  }

  .ob-item-row {
    padding: 0.75rem 0;
    border-bottom: 1px dashed #fce0ea;
  }

  .ob-item-row:last-child { border-bottom: none; }

  .ob-item-main {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 1rem;
  }

  .ob-item-name {
    font-size: 1rem;
    color: #2c1f14;
    font-weight: 400;
  }

  .ob-item-right {
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
    white-space: nowrap;
    flex-shrink: 0;
  }

  .ob-item-qty { font-size: 0.82rem; color: #b08a98; }

  .ob-item-price {
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.1rem;
    font-weight: 600;
    color: #2c1f14;
  }

  .ob-item-unit { font-size: 0.78rem; color: #c0a0a8; margin-top: 1px; }

  .ob-custom {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    margin-top: 7px;
  }

  .ob-tag {
    font-size: 0.73rem;
    background: #fce8ef;
    color: #c07a8a;
    border-radius: 12px;
    padding: 2px 10px;
    font-weight: 500;
    border: 1px solid rgba(200,120,150,0.15);
  }

  /* Total footer */
  .ob-totals {
    padding-top: 1rem;
    border-top: 1.5px solid #fce0ea;
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-top: 0.8rem;
  }

  .ob-total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .ob-total-label {
    font-size: 0.82rem;
    color: #b08a98;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-weight: 500;
  }

  .ob-total-amount {
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.6rem;
    font-weight: 600;
    color: #2c1f14;
  }

  /* GCash status */
  .ob-gcash-status {
    border-radius: 14px;
    border: 1.5px solid;
    padding: 14px 16px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
  }

  .ob-gcash-status-icon { font-size: 1.4rem; line-height: 1; flex-shrink: 0; margin-top: 2px; }
  .ob-gcash-status-body { flex: 1; }
  .ob-gcash-status-label { font-size: 0.82rem; font-weight: 700; letter-spacing: 0.04em; margin-bottom: 3px; }
  .ob-gcash-status-desc { font-size: 0.78rem; line-height: 1.5; opacity: 0.85; }

  /* Confirm delivery */
  .ob-confirm-wrap {
    padding: 1rem 1.2rem;
    background: linear-gradient(135deg, #f0fdf4, #dcfce7);
    border: 1.5px solid #86efac;
    border-radius: 14px;
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
  }

  .ob-confirm-text { flex: 1; min-width: 180px; }
  .ob-confirm-text strong { display: block; font-size: 0.88rem; color: #15803d; font-weight: 700; margin-bottom: 2px; }
  .ob-confirm-text span { font-size: 0.78rem; color: #16a34a; line-height: 1.4; }

  .ob-confirm-btn {
    background: #16a34a;
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 10px 20px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.84rem;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.18s, transform 0.15s;
    white-space: nowrap;
    flex-shrink: 0;
  }

  .ob-confirm-btn:hover { background: #15803d; transform: translateY(-1px); }

  .ob-confirmed-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #f0fdf4;
    border: 1.5px solid #86efac;
    color: #15803d;
    border-radius: 10px;
    padding: 8px 14px;
    font-size: 0.82rem;
    font-weight: 700;
    width: 100%;
  }

  /* ─── EMPTY STATE (full page) ─── */
  .ob-empty-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: calc(100vh - 70px);
    gap: 0;
    padding: 3rem 1rem;
  }

  .ob-empty-illustration {
    position: relative;
    width: 190px;
    height: 190px;
    margin-bottom: 1.6rem;
  }

  .ob-empty-circle {
    width: 160px;
    height: 160px;
    background: #fff;
    border-radius: 50%;
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 24px rgba(180,100,120,0.10);
  }

  .ob-empty-circle-ring {
    width: 185px;
    height: 185px;
    border: 2px dashed #f4b8c8;
    border-radius: 50%;
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    animation: spin-slow 18s linear infinite;
  }

  @keyframes spin-slow {
    to { transform: translate(-50%, -50%) rotate(360deg); }
  }

  .ob-empty-flower-icon {
    font-size: 3rem;
    line-height: 1;
    filter: grayscale(20%);
  }

  /* Corner petals */
  .ob-petal {
    position: absolute;
    font-size: 1.15rem;
    animation: float-petal 3s ease-in-out infinite alternate;
  }
  .ob-petal:nth-child(1) { top: 6px; right: 20px; animation-delay: 0s; }
  .ob-petal:nth-child(2) { bottom: 10px; left: 14px; animation-delay: 0.8s; }
  .ob-petal:nth-child(3) { top: 50%; left: 2px; transform: translateY(-50%); animation-delay: 1.4s; }

  @keyframes float-petal {
    from { transform: translateY(0) rotate(0deg); }
    to   { transform: translateY(-8px) rotate(20deg); }
  }

  /* Empty card */
  .ob-empty-card {
    background: #fff;
    border-radius: 24px;
    padding: 2.8rem 2.4rem 2.4rem;
    text-align: center;
    max-width: 400px;
    width: 100%;
    box-shadow: 0 2px 30px rgba(180,100,120,0.08);
    border: 1px solid rgba(240,190,205,0.4);
  }

  .ob-empty-title {
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.9rem;
    font-weight: 400;
    color: #2c1f14;
    margin: 0 0 0.5rem;
    line-height: 1.2;
  }

  .ob-empty-title em {
    font-style: italic;
    color: #c07a8a;
  }

  .ob-empty-desc {
    font-size: 0.88rem;
    color: #b08a98;
    margin: 0 0 1.8rem;
    line-height: 1.6;
  }

  .ob-shop-btn {
    display: inline-block;
    background: #2c1f14;
    color: #faf8f5;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.84rem;
    font-weight: 500;
    padding: 0.7rem 2rem;
    border-radius: 40px;
    text-decoration: none;
    letter-spacing: 0.04em;
    transition: background 0.18s, color 0.18s, transform 0.15s;
  }

  .ob-shop-btn:hover {
    background: #c07a8a;
    color: #fff;
    transform: translateY(-1px);
  }

  /* ─── Responsive: collapse to vertical on small screens ─── */
  @media (max-width: 700px) {
    .ob-layout { flex-direction: column; height: auto; }
    .ob-sidebar { width: 100%; max-width: 100%; height: auto; max-height: 280px; border-right: none; border-bottom: 1px solid #fce0ea; }
    .ob-detail-panel { min-height: 60vh; }
    .ob-detail-grid { grid-template-columns: 1fr; }
  }
  .ob-no-items { font-size: 0.84rem; color: #b08a98; margin: 0.5rem 0; }
</style>
<?php include __DIR__ . '/pwa_head.php'; ?>
</head>
<body>

<?php include "header.php"; ?>

<?php if (isset($_GET['confirmed'])): ?>
<script>window._showThankyou = true;</script>
<?php endif; ?>

<div class="ob-page">

<?php if (empty($orders)): ?>

  <!-- ── EMPTY STATE ── -->
  <div class="ob-empty-wrapper">
    <div class="ob-empty-illustration">
      <span class="ob-petal">🌸</span>
      <span class="ob-petal">🌺</span>
      <span class="ob-petal">🌸</span>
      <div class="ob-empty-circle-ring"></div>
      <div class="ob-empty-circle">
        <span class="ob-empty-flower-icon">🌷</span>
      </div>
    </div>
    <div class="ob-empty-card">
      <h2 class="ob-empty-title">Nothing here <em>yet</em></h2>
      <p class="ob-empty-desc">
        Your order history will bloom here.<br>
        Discover our beautiful arrangements and place your first order!
      </p>
      <a href="index.php" class="ob-shop-btn">Browse Our Blooms</a>
    </div>
  </div>

<?php else: ?>

  <!-- ── LANDSCAPE TWO-COLUMN LAYOUT ── -->
  <div class="ob-layout">

    <!-- LEFT: Order list sidebar -->
    <div class="ob-sidebar">
      <div class="ob-sidebar-head">
        <h1 class="ob-heading">My <em>Orders</em></h1>
        <p class="ob-sub"><?= count($orders) ?> order<?= count($orders) !== 1 ? 's' : '' ?> placed</p>

      </div>

      <div class="ob-search-wrap">
        <input class="ob-search" type="text" id="ob-search-input"
               placeholder="Search by order ID or status…"
               oninput="filterOrders(this.value)">
      </div>

      <div class="ob-sidebar-list" id="ob-sidebar-list">
        <?php foreach ($orders as $idx => $row):
          $status = $row['status'] ?? 'Pending';
          $color  = statusColor($status);
          $sLabel = statusLabel($status);
          $mode   = strtolower($row['delivery_mode'] ?? 'delivery');
          $modeIcon = $mode === 'pickup' ? '🏪' : '🚗';
        ?>
        <div class="ob-list-item<?= $idx === 0 ? ' active' : '' ?>"
             data-id="<?= $row['id'] ?>"
             onclick="selectOrder(this, <?= $row['id'] ?>)">
          <div class="ob-list-item-top">
            <span class="ob-list-id">ORD-<?= str_pad($row['id'], 3, '0', STR_PAD_LEFT) ?></span>
            <span class="ob-list-price">₱<?= number_format($row['total_price'], 2) ?></span>
          </div>
          <div class="ob-list-item-bottom">
            <span class="ob-list-date"><?= date('M j, Y · g:i A', strtotime($row['created_at'])) ?></span>
          </div>
          <div class="ob-list-item-bottom" style="margin-top:5px;">
            <span class="ob-list-tag"
                  style="color:<?= $color ?>;border-color:<?= $color ?>20;background:<?= $color ?>12;">
              <?= htmlspecialchars($sLabel) ?>
            </span>
            <span class="ob-list-tag"
                  style="color:#b08a98;border-color:#fce0ea;background:#fce8ef;">
              <?= $modeIcon ?> <?= ucfirst($mode) ?>
            </span>
            <?php if (!empty($row['payment_method'])): ?>
            <span class="ob-list-tag"
                  style="color:#9b8a7a;border-color:#f0e0d0;background:#fdf4ee;">
              <?= htmlspecialchars(strtoupper($row['payment_method'])) ?>
            </span>
            <?php endif; ?>
            <span class="ob-list-count"><?= $row['item_count'] ?> item<?= $row['item_count'] != 1 ? 's' : '' ?></span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div><!-- /sidebar -->

    <!-- RIGHT: Detail panel -->
    <div class="ob-detail-panel" id="ob-detail-panel">

      <?php foreach ($orders as $idx => $row):
        $items    = $orderItems[$row['id']] ?? [];
        $status   = $row['status'] ?? 'Pending';
        $color    = statusColor($status);
        $sIcon    = statusIcon($status);
        $sLabel   = statusLabel($status);
        $isPickup = strtolower($row['delivery_mode'] ?? '') === 'pickup';
        $step     = orderStep($status);
        $steps    = $isPickup
          ? [['icon'=>'🌸','label'=>'Order Placed'],['icon'=>'✂️','label'=>'Preparing'],['icon'=>'🏪','label'=>'Ready for Pickup']]
          : [['icon'=>'🌸','label'=>'Order Placed'],['icon'=>'✂️','label'=>'Preparing'],['icon'=>'🚗','label'=>'On the Way'],['icon'=>'💐','label'=>'Delivered']];

        $gcashInfo = null;
        if (strtolower($row['payment_method']) === 'gcash') {
            $gcashInfo = gcashPaymentInfo($status);
        }

        $statusLower         = strtolower($row['status']);
        $isDelivery          = !$isPickup;
        $isOutForDel         = $statusLower === 'out for delivery';
        $isDelivered         = $statusLower === 'delivered';
        $isReadyPickup       = $statusLower === 'ready for pickup';
        $alreadyConfirmed    = !empty($row['customer_confirmed']);
        $showDeliveryConfirm = $isDelivery && $isDelivered && !$alreadyConfirmed;
        $showPickupConfirm   = $isPickup && $isReadyPickup && !$alreadyConfirmed;
      ?>
      <div class="ob-detail-content<?= $idx === 0 ? ' visible' : '' ?>" id="ob-detail-<?= $row['id'] ?>">

        <!-- Detail header -->
        <div class="ob-detail-header">
          <div>
            <div class="ob-detail-order-id">ORD-<?= str_pad($row['id'], 3, '0', STR_PAD_LEFT) ?></div>
            <div class="ob-detail-date">Placed on <?= date('F j, Y \a\t g:i A', strtotime($row['created_at'])) ?></div>
          </div>
          <span class="ob-status-badge"
                style="color:<?= $color ?>;border-color:<?= $color ?>30;background:<?= $color ?>12;">
            <?= htmlspecialchars($sLabel) ?>
          </span>
        </div>

        <!-- Two info cards -->
        <div class="ob-detail-grid">

          <!-- Customer info card (minimal for user — just order mode/payment/status) -->
          <div class="ob-detail-card">
            <div class="ob-detail-card-title">🧾 Order Info</div>
            <?php if (!empty($row['payment_method'])): ?>
            <div class="ob-detail-row">
              <span class="ob-detail-key">Payment</span>
              <span class="ob-detail-val"><?= htmlspecialchars(strtoupper($row['payment_method'])) ?></span>
            </div>
            <?php endif; ?>
            <div class="ob-detail-row">
              <span class="ob-detail-key">Mode</span>
              <span class="ob-detail-val"><?= $isPickup ? '🏪 Pickup' : '🚗 Delivery' ?></span>
            </div>
            <div class="ob-detail-row">
              <span class="ob-detail-key">Status</span>
              <span class="ob-detail-val">
                <span class="ob-status-badge"
                      style="color:<?= $color ?>;border-color:<?= $color ?>30;background:<?= $color ?>12;">
                  <?= htmlspecialchars($sLabel) ?>
                </span>
              </span>
            </div>
          </div>

          <!-- GCash status or order summary card -->
          <div class="ob-detail-card">
            <?php if ($gcashInfo): ?>
            <div class="ob-detail-card-title">💙 GCash Payment</div>
            <div class="ob-gcash-status" style="background:<?= $gcashInfo['bg'] ?>;border-color:<?= $gcashInfo['border'] ?>;color:<?= $gcashInfo['color'] ?>;margin-bottom:0;">
              <div class="ob-gcash-status-icon"><?= $gcashInfo['icon'] ?></div>
              <div class="ob-gcash-status-body">
                <div class="ob-gcash-status-label"><?= $gcashInfo['label'] ?></div>
                <div class="ob-gcash-status-desc"><?= $gcashInfo['desc'] ?></div>
              </div>
            </div>
            <?php else: ?>
            <div class="ob-detail-card-title">📦 Summary</div>
            <div class="ob-detail-row">
              <span class="ob-detail-key">Items</span>
              <span class="ob-detail-val"><?= $row['item_count'] ?> item<?= $row['item_count'] != 1 ? 's' : '' ?></span>
            </div>
            <div class="ob-detail-row">
              <span class="ob-detail-key">Order Total</span>
              <span class="ob-detail-val" style="font-family:'Cormorant Garamond',serif;font-size:1.1rem;">
                ₱<?= number_format($row['total_price'], 2) ?>
              </span>
            </div>
            <?php endif; ?>
          </div>

        </div><!-- /grid -->

        <!-- Order Progress -->
        <div class="ob-progress-card">
          <div class="ob-section-label">🌸 Order Progress</div>
          <div class="ob-progress">
            <?php foreach ($steps as $i => $s):
              if ($i < $step)       $cls = 'done';
              elseif ($i === $step) $cls = 'active';
              else                  $cls = 'future';
            ?>
            <div class="ob-progress-step <?= $cls ?>">
              <?php if ($i < count($steps) - 1): ?>
                <div class="ob-progress-connector <?= $i < $step ? 'done' : '' ?>"></div>
              <?php endif; ?>
              <div class="ob-progress-dot"><?= $s['icon'] ?></div>
              <div class="ob-progress-label"><?= $s['label'] ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Items Ordered -->
        <div class="ob-items-card">
          <div class="ob-section-label">🌺 Items Ordered</div>

          <?php if (!empty($items)): ?>
            <?php foreach ($items as $item): ?>
            <div class="ob-item-row">
              <div class="ob-item-main">
                <span class="ob-item-name"><?= htmlspecialchars($item['product_name']) ?></span>
                <div class="ob-item-right">
                  <span class="ob-item-qty">×<?= (int)$item['quantity'] ?></span>
                  <span class="ob-item-price">₱<?= number_format($item['price'] * $item['quantity'], 2) ?></span>
                </div>
              </div>
              <div class="ob-item-unit">₱<?= number_format($item['price'], 2) ?> each</div>
              <?php
                $tags = [];
                if (!empty($item['wrapper'])) $tags[] = '🎀 ' . htmlspecialchars($item['wrapper']);
                if (!empty($item['ribbon']))  $tags[] = '🎗️ ' . htmlspecialchars($item['ribbon']);
                $addonsStr = formatAddons($item['addons'] ?? null);
                if ($addonsStr) $tags[] = '🍫 ' . $addonsStr;
              ?>
              <?php if (!empty($tags)): ?>
              <div class="ob-custom">
                <?php foreach ($tags as $tag): ?>
                  <span class="ob-tag"><?= $tag ?></span>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p class="ob-no-items">No item details available.</p>
          <?php endif; ?>

          <div class="ob-totals">
            <div class="ob-total-row">
              <span class="ob-total-label">Order Total</span>
              <span class="ob-total-amount">₱<?= number_format($row['total_price'], 2) ?></span>
            </div>
          </div>
        </div>

        <!-- Confirm Delivery / Pickup -->
        <?php if ($showDeliveryConfirm): ?>
        <div class="ob-confirm-wrap">
          <div class="ob-confirm-text">
            <strong>📦 Did you receive your order?</strong>
            <span>Tap below to confirm your bouquet has been delivered successfully.</span>
          </div>
          <form id="confirm-form-<?= $row['id'] ?>" method="POST" action="orders.php">
            <input type="hidden" name="confirm_delivery" value="1">
            <input type="hidden" name="order_id" value="<?= $row['id'] ?>">
            <button type="button" class="ob-confirm-btn"
              onclick="openConfirmPopup('<?= $row['id'] ?>', 'delivery')">
              💐 Confirm Delivery
            </button>
          </form>
        </div>
        <?php elseif ($showPickupConfirm): ?>
        <div class="ob-confirm-wrap">
          <div class="ob-confirm-text">
            <strong>🏪 Ready to pick up your order?</strong>
            <span>Tap below to confirm you've collected your bouquet at our store.</span>
          </div>
          <form id="confirm-form-<?= $row['id'] ?>" method="POST" action="orders.php">
            <input type="hidden" name="confirm_delivery" value="1">
            <input type="hidden" name="order_id" value="<?= $row['id'] ?>">
            <button type="button" class="ob-confirm-btn"
              onclick="openConfirmPopup('<?= $row['id'] ?>', 'pickup')">
              🏪 Confirm Pickup
            </button>
          </form>
        </div>
        <?php elseif ($alreadyConfirmed):
          // Check if user already left a review for this order
          $revCheck = $conn->prepare("SELECT id FROM reviews WHERE order_id=? AND user_id=?");
          $revCheck->bind_param('ii', $row['id'], $uid);
          $revCheck->execute();
          $hasReview = $revCheck->get_result()->num_rows > 0;
          $revCheck->close();
        ?>
        <div class="ob-confirmed-badge" style="flex-direction:column;gap:8px;align-items:flex-start;">
          <span><?= $isPickup ? '🏪' : '💐' ?> You confirmed <?= $isPickup ? 'pickup' : 'delivery' ?> of this order. Thank you!</span>
          <?php if (!$hasReview): ?>
          <button class="ob-confirm-btn" style="background:linear-gradient(135deg,#e879a8,#c05088);width:100%;margin-top:2px;"
            onclick="openReviewPopup('<?= $row['id'] ?>')">
            ⭐ Leave a Review
          </button>
          <?php else: ?>
          <span style="font-size:0.78rem;color:#22c55e;font-weight:600;">⭐ Review submitted — thank you! 💕</span>
          <?php endif; ?>
        </div>
        <?php endif; ?>

      </div><!-- /ob-detail-content -->
      <?php endforeach; ?>

      <!-- Placeholder shown only if somehow no order selected (JS-managed) -->
      <div class="ob-panel-empty" id="ob-panel-placeholder" style="display:none;">
        <div class="ob-panel-empty-icon">🌸</div>
        <h2 class="ob-panel-empty-title">Select an order</h2>
        <p class="ob-panel-empty-sub">Choose an order from the list to view details.</p>
      </div>

    </div><!-- /detail-panel -->

  </div><!-- /ob-layout -->

<?php endif; ?>

</div><!-- /ob-page -->

<!-- ── Girly Confirm Popup ── -->
<div id="ob-popup-overlay" class="ob-popup-overlay" onclick="closeConfirmPopup()">
  <div class="ob-popup" onclick="event.stopPropagation()">

    <!-- Floating petals -->
    <span class="ob-pop-petal p1">🌸</span>
    <span class="ob-pop-petal p2">🌺</span>
    <span class="ob-pop-petal p3">🌸</span>
    <span class="ob-pop-petal p4">💮</span>

    <div class="ob-pop-icon" id="ob-pop-icon">💐</div>
    <h2 class="ob-pop-title" id="ob-pop-title">Did you receive your bouquet?</h2>
    <p class="ob-pop-desc" id="ob-pop-desc">Once confirmed, your order will be marked as delivered. This can't be undone!</p>

    <div class="ob-pop-btns">
      <button class="ob-pop-cancel" onclick="closeConfirmPopup()">Not yet 🙈</button>
      <button class="ob-pop-ok" id="ob-pop-ok" onclick="submitConfirmForm()">Yes, I got it! 💕</button>
    </div>
  </div>
</div>

<style>
/* ── Popup overlay ── */
.ob-popup-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(180, 80, 110, 0.22);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
  z-index: 9999;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}
.ob-popup-overlay.open {
  display: flex;
  animation: ob-fade-in 0.22s ease;
}
@keyframes ob-fade-in {
  from { opacity: 0; }
  to   { opacity: 1; }
}

/* ── Popup card ── */
.ob-popup {
  background: #fff;
  border-radius: 28px;
  padding: 2.4rem 2rem 2rem;
  max-width: 360px;
  width: 100%;
  text-align: center;
  position: relative;
  overflow: hidden;
  box-shadow: 0 24px 80px rgba(180, 80, 110, 0.22), 0 4px 20px rgba(0,0,0,0.06);
  animation: ob-pop-in 0.32s cubic-bezier(0.34, 1.56, 0.64, 1);
  border: 1.5px solid #fce0ea;
}
@keyframes ob-pop-in {
  from { opacity: 0; transform: scale(0.82) translateY(24px); }
  to   { opacity: 1; transform: scale(1)    translateY(0);    }
}

/* Soft gradient top strip */
.ob-popup::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 6px;
  background: linear-gradient(90deg, #f9a8c9, #f7c5d8, #f9a8c9);
  border-radius: 28px 28px 0 0;
}

/* ── Floating petals ── */
.ob-pop-petal {
  position: absolute;
  font-size: 1.1rem;
  pointer-events: none;
  animation: ob-float 3s ease-in-out infinite alternate;
}
.ob-pop-petal.p1 { top: 14px;  left: 18px;  animation-delay: 0s;    font-size: 1.3rem; }
.ob-pop-petal.p2 { top: 12px;  right: 20px; animation-delay: 0.6s;  font-size: 1rem;   }
.ob-pop-petal.p3 { bottom: 60px; left: 14px; animation-delay: 1.1s; font-size: 0.9rem; }
.ob-pop-petal.p4 { bottom: 55px; right: 16px; animation-delay: 0.3s; font-size: 1rem;  }
@keyframes ob-float {
  from { transform: translateY(0) rotate(-8deg); }
  to   { transform: translateY(-8px) rotate(8deg); }
}

/* ── Icon ── */
.ob-pop-icon {
  font-size: 3.4rem;
  line-height: 1;
  margin-bottom: 0.9rem;
  display: block;
  filter: drop-shadow(0 4px 12px rgba(200, 80, 120, 0.2));
}

/* ── Title ── */
.ob-pop-title {
  font-family: 'Cormorant Garamond', serif;
  font-size: 1.65rem;
  font-weight: 600;
  color: #2c1f14;
  margin: 0 0 0.5rem;
  line-height: 1.2;
}

/* ── Description ── */
.ob-pop-desc {
  font-size: 0.84rem;
  color: #b08a98;
  line-height: 1.55;
  margin: 0 0 1.6rem;
}

/* ── Buttons ── */
.ob-pop-btns {
  display: flex;
  gap: 10px;
  justify-content: center;
}

.ob-pop-cancel {
  flex: 1;
  padding: 0.7rem 1rem;
  border-radius: 50px;
  border: 1.5px solid #fce0ea;
  background: #fff;
  color: #c07a8a;
  font-family: 'DM Sans', sans-serif;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.18s, border-color 0.18s;
}
.ob-pop-cancel:hover {
  background: #fce8ef;
  border-color: #f4b0c5;
}

.ob-pop-ok {
  flex: 1;
  padding: 0.7rem 1rem;
  border-radius: 50px;
  border: none;
  background: linear-gradient(135deg, #f472b6, #ec4899);
  color: #fff;
  font-family: 'DM Sans', sans-serif;
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 4px 16px rgba(236, 72, 153, 0.30);
  transition: transform 0.15s, box-shadow 0.15s;
}
.ob-pop-ok:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 22px rgba(236, 72, 153, 0.38);
}
</style>

<script>
var _confirmFormId = null;

function openConfirmPopup(orderId, mode) {
  _confirmFormId = 'confirm-form-' + orderId;

  var icon  = document.getElementById('ob-pop-icon');
  var title = document.getElementById('ob-pop-title');
  var desc  = document.getElementById('ob-pop-desc');
  var okBtn = document.getElementById('ob-pop-ok');

  if (mode === 'pickup') {
    icon.textContent  = '🏪';
    title.textContent = 'Picked up your bouquet?';
    desc.textContent  = 'Once confirmed, your order will be marked as picked up. This can\'t be undone!';
    okBtn.textContent = 'Yes, I got it! 🛍️';
  } else {
    icon.textContent  = '💐';
    title.textContent = 'Did you receive your bouquet?';
    desc.textContent  = 'Once confirmed, your order will be marked as delivered. This can\'t be undone!';
    okBtn.textContent = 'Yes, I got it! 💕';
  }

  document.getElementById('ob-popup-overlay').classList.add('open');
}

function closeConfirmPopup() {
  document.getElementById('ob-popup-overlay').classList.remove('open');
  _confirmFormId = null;
}

function submitConfirmForm() {
  if (_confirmFormId) {
    document.getElementById(_confirmFormId).submit();
  }
}

function selectOrder(el, id) {
  // Sidebar highlight
  document.querySelectorAll('.ob-list-item').forEach(i => i.classList.remove('active'));
  el.classList.add('active');
  // Show correct detail panel
  document.querySelectorAll('.ob-detail-content').forEach(p => p.classList.remove('visible'));
  const panel = document.getElementById('ob-detail-' + id);
  if (panel) panel.classList.add('visible');
}

function filterOrders(query) {
  const q = query.toLowerCase().trim();
  document.querySelectorAll('#ob-sidebar-list .ob-list-item').forEach(item => {
    const text = item.textContent.toLowerCase();
    item.style.display = (!q || text.includes(q)) ? '' : 'none';
  });
}
</script>

<!-- ── Review Popup ── -->
<div id="ob-review-overlay" class="ob-popup-overlay">
  <div class="ob-popup ob-review-popup" onclick="event.stopPropagation()">
    <span class="ob-pop-petal p1">🌸</span>
    <span class="ob-pop-petal p2">🌺</span>
    <span class="ob-pop-petal p3">🌷</span>
    <span class="ob-pop-petal p4">💮</span>
    <span class="ob-pop-petal p5">✨</span>

    <div class="ob-pop-icon">🌸</div>
    <h2 class="ob-pop-title">How was your bouquet?</h2>
    <p class="ob-pop-desc">We'd love to hear from you! Tap the stars to rate your experience 💕</p>

    <!-- Stars -->
    <div class="ob-stars-row" id="ob-stars-row">
      <span class="ob-star" data-v="1" onclick="selectStar(1)">★</span>
      <span class="ob-star" data-v="2" onclick="selectStar(2)">★</span>
      <span class="ob-star" data-v="3" onclick="selectStar(3)">★</span>
      <span class="ob-star" data-v="4" onclick="selectStar(4)">★</span>
      <span class="ob-star" data-v="5" onclick="selectStar(5)">★</span>
    </div>
    <div class="ob-star-label" id="ob-star-label">Tap a star to rate</div>

    <!-- Message input -->
    <textarea id="ob-review-msg" class="ob-review-textarea"
      placeholder="Share your thoughts about your blooms... (optional)" maxlength="500"></textarea>

    <div style="display:flex;gap:10px;width:100%;margin-top:0.2rem;">
      <button class="ob-pop-ok ob-review-skip-btn" onclick="skipReview()">Skip 🙈</button>
      <button class="ob-pop-ok ob-review-submit-btn" id="ob-review-submit" onclick="submitReview()" disabled>Send Review 💌</button>
    </div>
  </div>
</div>

<!-- ── Thank You Popup ── -->
<div id="ob-thankyou-overlay" class="ob-popup-overlay">
  <div class="ob-popup ob-thankyou-popup" onclick="event.stopPropagation()">
    <span class="ob-pop-petal p1">🌸</span>
    <span class="ob-pop-petal p2">🌺</span>
    <span class="ob-pop-petal p3">💐</span>
    <span class="ob-pop-petal p4">🌷</span>
    <span class="ob-pop-petal p5">✨</span>

    <div class="ob-pop-icon">💐</div>
    <h2 class="ob-pop-title" id="ob-ty-title">Yay! So glad it arrived!</h2>
    <p class="ob-pop-desc" id="ob-ty-desc">
      Thank you for confirming, lovely! 🌸<br>
      We hope your blooms bring you so much joy. 💕
    </p>
    <button class="ob-pop-ok ob-ty-btn" onclick="closeObThankyouPopup()">
      Aww, thank you! 🎀
    </button>
  </div>
</div>

<style>
/* ── Review popup ── */
.ob-review-popup::before {
  background: linear-gradient(90deg, #f9a8c9, #e879a8, #f9a8c9) !important;
}
.ob-pop-petal.p5 { top: 50%; left: 10px; animation-delay: 0.8s; font-size: 0.85rem; }

/* Stars */
.ob-stars-row {
  display: flex; gap: 8px; justify-content: center;
  margin: 0.6rem 0 0.3rem;
}
.ob-star {
  font-size: 2.4rem;
  color: #e8d0d8;
  cursor: pointer;
  transition: color 0.18s, transform 0.18s;
  line-height: 1;
  user-select: none;
}
.ob-star.lit     { color: #e8619a; }
.ob-star.pop     { animation: ob-star-pop 0.35s cubic-bezier(0.34,1.56,0.64,1); }
.ob-star:hover   { transform: scale(1.18); }
@keyframes ob-star-pop {
  0%   { transform: scale(1); }
  45%  { transform: scale(1.45) rotate(-8deg); }
  70%  { transform: scale(0.9)  rotate(4deg);  }
  100% { transform: scale(1)   rotate(0deg);   }
}
.ob-star-label {
  font-size: 0.78rem; color: #c07a8a; font-weight: 600;
  letter-spacing: 0.04em; min-height: 1.2em; margin-bottom: 0.5rem;
  text-align: center;
}

/* Review textarea */
.ob-review-textarea {
  width: 100%; box-sizing: border-box;
  border: 1.5px solid #fce0ea; border-radius: 12px;
  background: #fdf5f8; padding: 0.7rem 1rem;
  font-family: 'DM Sans', sans-serif; font-size: 0.84rem;
  color: #2c1f14; resize: none; height: 90px; outline: none;
  transition: border-color 0.18s;
}
.ob-review-textarea:focus { border-color: #e8a0b4; }
.ob-review-textarea::placeholder { color: #d4a0b8; }

/* Review buttons */
.ob-review-skip-btn {
  flex: 1; background: #fce8ef !important;
  color: #c07a8a !important; box-shadow: none !important;
  border: 1.5px solid #fce0ea !important;
}
.ob-review-skip-btn:hover { background: #fbd8e8 !important; }
.ob-review-submit-btn {
  flex: 2;
  background: linear-gradient(135deg, #e879a8, #c05088) !important;
  box-shadow: 0 4px 16px rgba(200,80,140,0.3) !important;
}
.ob-review-submit-btn:disabled {
  background: #f0c0d8 !important;
  box-shadow: none !important;
  cursor: not-allowed !important;
}
.ob-review-submit-btn:not(:disabled):hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 22px rgba(200,80,140,0.4) !important;
}

/* Thank you popup */
.ob-thankyou-popup::before {
  background: linear-gradient(90deg, #f9a8c9, #c084fc, #f9a8c9) !important;
}
.ob-ty-btn { width: 100%; display: block; flex: unset; }
</style>

<script>
/* ── Review globals ── */
var _reviewOrderId  = null;
var _reviewStarVal  = 0;
var _starLabels = ['','💔 Poor','😕 Fair','😊 Good','😍 Great','🤩 Amazing!'];

function openReviewPopup(orderId) {
  _reviewOrderId = orderId;
  _reviewStarVal = 0;
  document.querySelectorAll('.ob-star').forEach(s => s.classList.remove('lit','pop'));
  document.getElementById('ob-star-label').textContent = 'Tap a star to rate';
  document.getElementById('ob-review-msg').value = '';
  document.getElementById('ob-review-submit').disabled = true;
  document.getElementById('ob-review-overlay').classList.add('open');
}

function selectStar(val) {
  _reviewStarVal = val;
  document.querySelectorAll('.ob-star').forEach(function(s) {
    var v = parseInt(s.dataset.v);
    s.classList.toggle('lit', v <= val);
    if (v === val) {
      s.classList.remove('pop');
      void s.offsetWidth; // reflow
      s.classList.add('pop');
    } else {
      s.classList.remove('pop');
    }
  });
  document.getElementById('ob-star-label').textContent = _starLabels[val] || '';
  document.getElementById('ob-review-submit').disabled = false;
}

function skipReview() {
  document.getElementById('ob-review-overlay').classList.remove('open');
  showThankyouPopup(false);
}

function submitReview() {
  if (!_reviewStarVal) return;
  var msg = document.getElementById('ob-review-msg').value.trim();
  var btn = document.getElementById('ob-review-submit');
  btn.disabled = true;
  btn.textContent = 'Sending... 🌸';

  var fd = new FormData();
  fd.append('submit_review', '1');
  fd.append('order_id', _reviewOrderId);
  fd.append('rating', _reviewStarVal);
  fd.append('message', msg);

  fetch('submit_review.php', { method: 'POST', body: fd })
    .then(function(r){ return r.json(); })
    .catch(function(){ return {ok:true}; }) // fail silently
    .finally(function(){
      document.getElementById('ob-review-overlay').classList.remove('open');
      showThankyouPopup(true);
    });
}

/* ── Thank you popup ── */
function showThankyouPopup(withReview) {
  if (withReview) {
    document.getElementById('ob-ty-title').textContent = 'Thank you for your review! 🎀';
    document.getElementById('ob-ty-desc').innerHTML = 'Your feedback means the world to us! 🌸<br>We\'re so glad you loved your blooms. 💕';
  } else {
    document.getElementById('ob-ty-title').textContent = 'Yay! So glad it arrived!';
    document.getElementById('ob-ty-desc').innerHTML = 'Thank you for confirming, lovely! 🌸<br>We hope your blooms bring you so much joy. 💕';
  }
  document.getElementById('ob-thankyou-overlay').classList.add('open');
}

function closeObThankyouPopup() {
  document.getElementById('ob-thankyou-overlay').classList.remove('open');
  history.replaceState({}, '', window.location.pathname);
}

if (window._showThankyou) {
  window.addEventListener('DOMContentLoaded', function() {
    // get order id from URL ?confirmed=ID
    var m = window.location.search.match(/confirmed=(\d+)/);
    var oid = m ? m[1] : null;
    openReviewPopup(oid);
  });
}
</script>

<!-- ── Out for Delivery Notification Popup ── -->
<div id="ob-ofd-overlay" class="ob-popup-overlay" onclick="closeOfdPopup()">
  <div class="ob-popup ob-ofd-popup" onclick="event.stopPropagation()">
    <span class="ob-pop-petal p1">🌸</span>
    <span class="ob-pop-petal p2">💨</span>
    <span class="ob-pop-petal p3">🌺</span>
    <span class="ob-pop-petal p4">✨</span>

    <span class="ob-pop-icon">🚗</span>
    <h2 class="ob-pop-title">Your order is on its way!</h2>
    <p class="ob-pop-desc">
      Great news! 🌸 Your bouquet is currently <strong style="color:#2563eb;">Out for Delivery</strong>.<br>
      Please be ready to receive it. We can't wait for you to see it! 💐
    </p>
    <div id="ob-ofd-order-list" style="margin-bottom:1.2rem;font-size:0.82rem;color:#2563eb;font-weight:700;letter-spacing:0.04em;"></div>
    <button class="ob-pop-ok ob-ofd-btn" onclick="closeOfdPopup()">Got it, I'm ready! 🎀</button>
  </div>
</div>

<style>
.ob-ofd-popup::before {
  background: linear-gradient(90deg, #93c5fd, #60a5fa, #93c5fd) !important;
}
.ob-ofd-btn {
  width: 100%;
  display: block;
  flex: unset;
  background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
  box-shadow: 0 4px 16px rgba(59, 130, 246, 0.32) !important;
}
.ob-ofd-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 22px rgba(59, 130, 246, 0.42) !important;
}
</style>

<script>
function closeOfdPopup() {
  document.getElementById('ob-ofd-overlay').classList.remove('open');
  sessionStorage.setItem('ofd_dismissed', '1');
}

<?php if ($hasOutForDelivery): ?>
window.addEventListener('DOMContentLoaded', function () {
  if (!sessionStorage.getItem('ofd_dismissed')) {
    var orders = <?= json_encode($outForDeliveryIds) ?>;
    var listEl = document.getElementById('ob-ofd-order-list');
    if (orders.length > 0) {
      listEl.textContent = 'Order' + (orders.length > 1 ? 's' : '') + ': ' + orders.join(', ');
    }
    document.getElementById('ob-ofd-overlay').classList.add('open');
  }
});
<?php endif; ?>
</script>

<!-- ── Ready for Pickup Notification Popup ── -->
<div id="ob-rfp-overlay" class="ob-popup-overlay" onclick="closeRfpPopup()">
  <div class="ob-popup ob-rfp-popup" onclick="event.stopPropagation()">
    <span class="ob-pop-petal p1">🌸</span>
    <span class="ob-pop-petal p2">🛍️</span>
    <span class="ob-pop-petal p3">🌺</span>
    <span class="ob-pop-petal p4">✨</span>

    <span class="ob-pop-icon">🏪</span>
    <h2 class="ob-pop-title">Your order is ready!</h2>
    <p class="ob-pop-desc">
      Exciting news! 🌸 Your bouquet is now <strong style="color:#d97706;">Ready for Pickup</strong>.<br>
      Please visit our store at your convenience to collect it! 💐
    </p>
    <div id="ob-rfp-order-list" style="margin-bottom:1.2rem;font-size:0.82rem;color:#d97706;font-weight:700;letter-spacing:0.04em;"></div>
    <button class="ob-pop-ok ob-rfp-btn" onclick="closeRfpPopup()">On my way! 🎀</button>
  </div>
</div>

<style>
.ob-rfp-popup::before {
  background: linear-gradient(90deg, #fcd34d, #fbbf24, #fcd34d) !important;
}
.ob-rfp-btn {
  width: 100%;
  display: block;
  flex: unset;
  background: linear-gradient(135deg, #f59e0b, #d97706) !important;
  box-shadow: 0 4px 16px rgba(245, 158, 11, 0.32) !important;
}
.ob-rfp-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 22px rgba(245, 158, 11, 0.42) !important;
}
</style>

<script>
function closeRfpPopup() {
  document.getElementById('ob-rfp-overlay').classList.remove('open');
  sessionStorage.setItem('rfp_dismissed', '1');
}

<?php if ($hasReadyForPickup): ?>
window.addEventListener('DOMContentLoaded', function () {
  if (!sessionStorage.getItem('rfp_dismissed')) {
    var orders = <?= json_encode($readyForPickupIds) ?>;
    var listEl = document.getElementById('ob-rfp-order-list');
    if (orders.length > 0) {
      listEl.textContent = 'Order' + (orders.length > 1 ? 's' : '') + ': ' + orders.join(', ');
    }
    document.getElementById('ob-rfp-overlay').classList.add('open');
  }
});
<?php endif; ?>
</script>

</body>
</html>