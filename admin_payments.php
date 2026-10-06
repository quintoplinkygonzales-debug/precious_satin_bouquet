<?php
session_start();
include "config.php";

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$uid  = $_SESSION['user_id'];
$user = $conn->query("SELECT * FROM users WHERE id=" . intval($uid))->fetch_assoc();
if (!$user || $user['role'] !== 'admin') { header("Location: index.php"); exit(); }
$adminName = $user['username'] ?? 'Admin';

// ── Handle: Confirm Paid ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_order_id'])) {
    $oid = intval($_POST['confirm_order_id']);
    if ($oid > 0) {
        $stmt = $conn->prepare("UPDATE orders SET status='paid' WHERE id=? AND status='pending_verification'");
        $stmt->bind_param('i', $oid); $stmt->execute(); $stmt->close();
    }
    header("Location: admin_payments.php?tab=" . urlencode($_POST['tab'] ?? 'pending_verification') . "&selected=$oid");
    exit();
}

// ── Handle: Reject ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_order_id'])) {
    $oid = intval($_POST['reject_order_id']);
    if ($oid > 0) {
        $stmt = $conn->prepare("UPDATE orders SET status='payment_rejected' WHERE id=? AND status='pending_verification'");
        $stmt->bind_param('i', $oid); $stmt->execute(); $stmt->close();
    }
    header("Location: admin_payments.php?tab=" . urlencode($_POST['tab'] ?? 'pending_verification') . "&selected=$oid");
    exit();
}

// ── Ensure admin_payment_seen table exists ────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS admin_payment_seen (
        order_id INT PRIMARY KEY,
        seen     TINYINT(1) NOT NULL DEFAULT 0
    )
");
// Seed any GCash orders not yet in the seen table
$conn->query("
    INSERT IGNORE INTO admin_payment_seen (order_id, seen)
    SELECT id, 0 FROM orders WHERE payment_method = 'gcash'
");

// ── Counts ────────────────────────────────────────────────────────────
// ── Tab & Selected ────────────────────────────────────────────────────
$validTabs   = ['all', 'pending_verification', 'paid', 'payment_rejected'];
$tab         = in_array($_GET['tab'] ?? '', $validTabs) ? $_GET['tab'] : 'all';
$selected    = (int)($_GET['selected'] ?? 0);

// ── Unseen tracking ───────────────────────────────────────────────────
$selIsNew = false;
if ($selected > 0) {
    $seenRow  = $conn->query("SELECT seen FROM admin_payment_seen WHERE order_id = $selected")->fetch_assoc();
    $selIsNew = $seenRow && (int)$seenRow['seen'] === 0;
    $conn->query("UPDATE admin_payment_seen SET seen = 1 WHERE order_id = $selected");
}
// Collect all still-unseen IDs for the list chips
$unseenPayIds = [];
$unseenPayRes = $conn->query("SELECT order_id FROM admin_payment_seen WHERE seen = 0");
if ($unseenPayRes) { while ($r = $unseenPayRes->fetch_assoc()) $unseenPayIds[] = (int)$r['order_id']; }

$whereClause = ($tab === 'all')
    ? "WHERE o.payment_method = 'gcash'"
    : "WHERE o.payment_method = 'gcash' AND o.status = '" . $conn->real_escape_string($tab) . "'";

$orders = $conn->query("
    SELECT o.id, o.user_order_no, o.total_price, o.payment_method, o.delivery_mode,
           o.status, o.gcash_ref, o.gcash_screenshot, o.created_at,
           COALESCE(NULLIF(u.full_name,''), u.username) AS customer_name,
           u.email AS customer_email, u.phone AS customer_phone
    FROM orders o
    JOIN users u ON o.user_id = u.id
    $whereClause
    ORDER BY o.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Count per status for tab badges
$countMap = ['pending_verification' => 0, 'paid' => 0, 'payment_rejected' => 0];
$totalGcash = 0;
foreach ($conn->query("SELECT status, COUNT(*) AS cnt FROM orders WHERE payment_method='gcash' GROUP BY status")->fetch_all(MYSQLI_ASSOC) as $c) {
    if (array_key_exists($c['status'], $countMap)) {
        $countMap[$c['status']] = (int)$c['cnt'];
    }
    $totalGcash += (int)$c['cnt'];
}

// Selected order detail
$selOrder = null;
if ($selected > 0) {
    foreach ($orders as $o) { if ($o['id'] == $selected) { $selOrder = $o; break; } }
    // If not in current tab, fetch directly
    if (!$selOrder) {
        $selOrder = $conn->query("
            SELECT o.id, o.user_order_no, o.total_price, o.payment_method, o.delivery_mode,
                   o.status, o.gcash_ref, o.gcash_screenshot, o.created_at,
                   COALESCE(NULLIF(u.full_name,''), u.username) AS customer_name,
                   u.email AS customer_email, u.phone AS customer_phone
            FROM orders o JOIN users u ON o.user_id = u.id
            WHERE o.id = $selected
        ")->fetch_assoc();
    }
}

function statusBadge(string $s): string {
    return match($s) {
        'pending_verification'          => '<span class="badge badge-pending">⏳ Pending</span>',
        'paid'                          => '<span class="badge badge-paid">✅ Paid</span>',
        'payment_rejected'              => '<span class="badge badge-rejected">❌ Rejected</span>',
        'Paid'                          => '<span class="badge badge-paid">✅ Paid</span>',
        'Delivered'                     => '<span class="badge badge-paid">📦 Delivered</span>',
        'Out for Delivery'              => '<span class="badge" style="background:#dbeafe;color:#1e40af;">🚚 Out for Delivery</span>',
        'Preparing'                     => '<span class="badge" style="background:#fef9c3;color:#854d0e;">🔧 Preparing</span>',
        'Picked Up'                     => '<span class="badge badge-paid">✅ Picked Up</span>',
        'Cancelled'                     => '<span class="badge badge-rejected">🚫 Cancelled</span>',
        'Pending'                       => '<span class="badge badge-pending">⏳ Pending</span>',
        default                         => '<span class="badge">'.htmlspecialchars($s).'</span>',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="refresh" content="60">
<title>GCash Payments — Precious Satin Bouquet Admin</title>
<link rel="icon" type="image/png" href="images/logo/SB.png">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700&family=Jost:wght@300;400;500;600;700&family=Great+Vibes&display=swap" rel="stylesheet">
<style>
:root {
    --blush:     #fff3ed;
    --petal:     #ffc9a0;
    --rose:      #f08050;
    --deep-rose: #d05a28;
    --mauve:     #c8775a;
    --cream:     #fff8f4;
    --white:     #ffffff;
    --ink:       #2d1a22;
    --muted:     #a07080;
    --border:    rgba(255,170,130,0.22);
    --shadow-sm: 0 2px 12px rgba(208,90,40,0.08);
    --shadow-md: 0 8px 32px rgba(208,90,40,0.13);
    --radius:    14px;
    --green:     #16a34a;
    --red:       #e53e3e;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    background: var(--cream);
    font-family: 'Jost', sans-serif;
    font-size: 14px;
    color: var(--ink);
    min-height: 100vh;
    overflow-x: hidden;
}
body::before {
    content: ''; position: fixed; inset: 0; pointer-events: none; z-index: 0;
    background:
        radial-gradient(ellipse 60% 40% at 90% 10%, rgba(255,200,160,0.35) 0%, transparent 70%),
        radial-gradient(ellipse 50% 60% at 5% 80%,  rgba(252,232,240,0.40) 0%, transparent 65%);
}

/* ── NAVBAR ── */
.adm-nav {
    position: sticky; top: 0; z-index: 300; height: 60px;
    display: flex; align-items: center; padding: 0 32px;
    background: rgba(255,248,242,0.92);
    backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
    border-bottom: 1px solid var(--border);
}
.adm-nav-left  { flex: 1; }
.adm-nav-center { flex: 1; display: flex; justify-content: center; }
.adm-nav-right { flex: 1; display: flex; align-items: center; justify-content: flex-end; gap: 8px; }
.adm-nav::after {
    content: ''; position: absolute; bottom: -1px; left: 50%; transform: translateX(-50%);
    width: 100px; height: 2px;
    background: linear-gradient(90deg, transparent, var(--rose), transparent);
}
.adm-nav-brand   { font-family: 'Great Vibes', cursive; font-size: 26px; color: var(--deep-rose); text-decoration: none; letter-spacing: 0.5px; line-height: 1; }
.adm-nav-tagline { font-size: 9px; font-weight: 500; letter-spacing: 3px; text-transform: uppercase; color: var(--muted); display: block; text-align: center; margin-top: -2px; }

.adm-icon {
    width: 34px; height: 34px; border-radius: 50%;
    background: transparent; border: 1.5px solid var(--border);
    display: flex; align-items: center; justify-content: center;
    color: var(--muted); text-decoration: none;
    transition: all 0.2s; cursor: pointer; position: relative;
}
.adm-icon:hover  { background: var(--rose); border-color: var(--rose); color: #fff; }
.adm-icon.active { background: var(--deep-rose); border-color: var(--deep-rose); color: #fff; }

.pending-dot { position: absolute; top: 3px; right: 3px; width: 8px; height: 8px; border-radius: 50%; background: #e53e3e; border: 2px solid var(--cream); animation: pulseDot 1.6s ease-in-out infinite; }
@keyframes pulseDot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.55;transform:scale(1.3)} }

.adm-settings-wrap { position: relative; }
.adm-settings-dropdown {
    display: none; position: absolute; top: calc(100% + 12px); right: 0;
    width: 250px; background: var(--white); border: 1px solid var(--border);
    border-radius: 16px; box-shadow: var(--shadow-md); z-index: 400;
    overflow: hidden; animation: dropIn 0.2s ease;
}
.adm-settings-dropdown.open { display: block; }
@keyframes dropIn { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }
.adm-settings-head { background: var(--blush); padding: 10px 16px; font-size: 11px; font-weight: 700; color: var(--deep-rose); letter-spacing: 2.5px; text-transform: uppercase; border-bottom: 1px solid var(--border); }
.adm-settings-item { display: flex; align-items: center; gap: 12px; padding: 10px 16px; text-decoration: none; color: var(--ink); font-size: 14px; font-weight: 500; transition: background 0.15s; border-bottom: 1px solid rgba(247,197,216,0.2); }
.adm-settings-item:last-child { border-bottom: none; }
.adm-settings-item:hover, .adm-settings-item.active-page { background: var(--blush); color: var(--deep-rose); }
.adm-settings-item-icon { width: 30px; height: 30px; border-radius: 8px; background: var(--blush); display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0; }
.adm-settings-item-sub { font-size: 11px; color: var(--muted); font-weight: 400; margin-top: 1px; }
.nav-badge { margin-left: auto; background: var(--rose); color: #fff; font-size: 11px; font-weight: 700; padding: 2px 6px; border-radius: 8px; }

/* ── TWO-PANEL LAYOUT ── */
.adm-layout {
    position: relative; z-index: 1;
    display: flex;
    height: calc(100vh - 60px);
    overflow: hidden;
}

/* ── LEFT SIDEBAR ── */
.pay-sidebar {
    width: 420px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    border-right: 1px solid var(--border);
    background: var(--white);
    overflow: hidden;
}

/* Sidebar header — matches orders ls-left-head */
.pay-sidebar-header {
    padding: 18px 22px 14px;
    border-bottom: 1px solid var(--border);
    background: var(--blush);
    flex-shrink: 0;
}
.pay-sidebar-title-row {
    display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 12px;
}
.pay-sidebar-main-title {
    font-family: 'Cormorant Garamond', serif;
    font-size: 32px; font-weight: 700; color: var(--deep-rose); line-height: 1;
    display: flex; align-items: center; gap: 10px;
}
.pay-sidebar-count { font-size: 13px; color: var(--muted); font-weight: 500; }

/* Tabs row */
.pay-tabs {
    display: flex; gap: 5px;
    padding: 10px 14px;
    border-bottom: 1px solid var(--border);
    background: var(--white);
    flex-shrink: 0;
    flex-wrap: nowrap;
}
.tab-btn {
    padding: 5px 9px; border-radius: 20px; border: 1.5px solid var(--border);
    background: transparent; font-family: 'Jost', sans-serif;
    font-size: 11.5px; font-weight: 700; color: var(--muted);
    cursor: pointer; text-decoration: none;
    display: inline-flex; align-items: center; gap: 4px;
    transition: all 0.16s; white-space: nowrap; letter-spacing: 0.2px;
    flex-shrink: 0;
}
.tab-btn:hover  { border-color: var(--deep-rose); color: var(--deep-rose); }
.tab-btn.active { background: var(--deep-rose); border-color: var(--deep-rose); color: #fff; }
.tab-count {
    border-radius: 8px; padding: 1px 5px; font-size: 10px; font-weight: 700;
    background: rgba(255,255,255,0.28);
}
.tab-btn:not(.active) .tab-count { background: var(--blush); color: var(--mauve); }

/* Scrollable list */
.pay-list {
    flex: 1; overflow-y: auto; overflow-x: hidden;
}
.pay-list::-webkit-scrollbar { width: 5px; }
.pay-list::-webkit-scrollbar-track { background: transparent; }
.pay-list::-webkit-scrollbar-thumb { background: var(--petal); border-radius: 10px; }

/* ── Payment card — mirrors .order-card exactly ── */
.pay-item {
    display: flex; align-items: center;
    padding: 14px 18px 14px 22px;
    border-bottom: 1px solid rgba(248,190,210,0.28);
    cursor: pointer; position: relative;
    transition: none !important; animation: none !important;
}
.pay-item:last-child { border-bottom: none; }
.pay-item:hover, .pay-item:focus { background: transparent !important; outline: none !important; }
.pay-item.active { background: var(--blush); }

/* Left content column */
.pi-left { display: flex; flex-direction: column; gap: 4px; min-width: 0; flex: 1; }
.pi-top  { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; }

/* Order ID — large serif like .order-id */
.pi-order-id {
    font-family: 'Cormorant Garamond', serif;
    font-size: 19px; font-weight: 700;
    color: #b5546a; line-height: 1;
}

/* Chips row */
.pi-chips { display: flex; gap: 4px; align-items: center; flex-wrap: wrap; }

/* Customer name */
.pi-customer { font-size: 13px; color: var(--ink); font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 260px; }
.pi-customer span { color: var(--muted); font-weight: 400; }

/* Date */
.pi-date { font-size: 12px; color: #c0909a; }

/* Right amount column */
.pi-right { text-align: right; padding-left: 10px; flex-shrink: 0; }
.pi-amount {
    font-family: 'Cormorant Garamond', serif;
    font-size: 20px; font-weight: 700; color: #3a2020; line-height: 1;
}
.pi-items { font-size: 11px; color: var(--muted); margin-top: 2px; }

/* Chips — reuse .oc-chip styles */
.oc-chip {
    display: inline-flex; align-items: center; gap: 3px;
    font-size: 10px; font-weight: 700; letter-spacing: 0.4px;
    text-transform: uppercase; padding: 2px 8px;
    border-radius: 20px; border: 1px solid transparent;
}
.oc-chip-delivery { background: #edf3ff; color: #2b5cc7; border-color: #c3d4ff; }
.oc-chip-pickup   { background: #f4eeff; color: #6d28d9; border-color: #daccf8; }
.oc-chip-gcash    { background: #e8f2ff; color: #1a50b5; border-color: #b5d0f8; }

/* Status pills — matches orders page */
.status-pill {
    display: inline-flex; align-items: center; gap: 3px;
    padding: 2px 9px; border-radius: 20px;
    font-size: 10px; font-weight: 700;
    letter-spacing: 0.6px; text-transform: uppercase;
    white-space: nowrap; border: 1px solid transparent;
}
.status-pill.pending_verification { background: #fff8e5; color: #966a08; border-color: #f2d465; }
.status-pill.paid                 { background: #edfaf2; color: #166534; border-color: #a7e8c5; }
.status-pill.payment_rejected     { background: #fff0f3; color: #b81c38; border-color: #f8b4c3; }
.status-pill.preparing            { background: #f4eeff; color: #6d28d9; border-color: #daccf8; }
.status-pill.out-for-delivery     { background: #edf3ff; color: #2b5cc7; border-color: #c3d4ff; }
.status-pill.delivered            { background: #edfaf2; color: #166534; border-color: #a7e8c5; }
.status-pill.picked-up            { background: #edfaf2; color: #166534; border-color: #a7e8c5; }
.status-pill.cancelled            { background: #fff0f3; color: #b81c38; border-color: #f8b4c3; }
.status-pill.pending              { background: #fff8e5; color: #966a08; border-color: #f2d465; }

.badge { display: inline-block; padding: 2px 8px; border-radius: 99px; font-size: 10px; font-weight: 600; }
.badge-pending  { background: #fef3c7; color: #92400e; }
.badge-paid     { background: #dcfce7; color: #166534; }
.badge-rejected { background: #fee2e2; color: #991b1b; }

/* ── RIGHT DETAIL ── */
.pay-detail {
    flex: 1; display: flex; flex-direction: column;
    overflow: hidden; background: var(--cream);
}

.pay-detail-empty {
    flex: 1; display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    color: var(--muted); gap: 10px;
}
.pay-detail-empty .empty-icon { font-size: 46px; opacity: 0.3; }
.pay-detail-empty p { font-size: 13.5px; }

/* Detail header */
.detail-header {
    padding: 14px 24px;
    border-bottom: 1px solid var(--border);
    background: var(--white);
    display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;
    flex-shrink: 0;
}
.detail-order-no {
    font-family: 'Cormorant Garamond', serif;
    font-size: 19px; font-weight: 700; color: var(--ink); margin-bottom: 4px;
}
.detail-meta { display: flex; gap: 12px; flex-wrap: wrap; }
.detail-meta span { font-size: 13px; color: var(--muted); display: flex; align-items: center; gap: 4px; }

/* Detail body */
.detail-body {
    flex: 1; overflow-y: auto; padding: 20px 24px;
    display: flex; flex-direction: column; gap: 16px;
}
.detail-body::-webkit-scrollbar { width: 4px; }
.detail-body::-webkit-scrollbar-thumb { background: var(--petal); border-radius: 4px; }

.detail-section-label {
    font-size: 11px; font-weight: 700; letter-spacing: 2.5px; text-transform: uppercase;
    color: var(--muted); margin-bottom: 8px;
}

/* GCash ref */
.gcash-ref-box {
    display: inline-flex; align-items: center; gap: 10px;
    background: #fef3c7; border: 1px solid #fcd34d;
    border-radius: 10px; padding: 10px 16px; width: fit-content;
}
.ref-label  { font-size: 14px; font-weight: 700; color: #92400e; text-transform: uppercase; letter-spacing: .07em; margin-bottom: 2px; }
.ref-number { font-weight: 800; color: #78350f; font-size: 14px; letter-spacing: .08em; font-family: 'Courier New', monospace; }
.no-ref { font-size: 13.5px; color: var(--red); }

/* Screenshot */
.screenshot-thumb {
    width: 100%; max-width: 300px; max-height: 220px;
    object-fit: contain; border-radius: 10px;
    border: 1.5px solid var(--border); background: var(--blush);
    cursor: pointer; transition: transform .2s, box-shadow .2s; display: block;
}
.screenshot-thumb:hover { transform: scale(1.02); box-shadow: var(--shadow-md); }
.no-screenshot { font-size: 13.5px; color: var(--red); background: #fff5f5; border: 1px dashed #fca5a5; border-radius: 8px; padding: 7px 12px; display: inline-block; }

/* Action buttons */
.detail-actions {
    padding: 12px 24px 16px;
    border-top: 1px solid var(--border);
    background: var(--white);
    display: flex; gap: 10px; flex-shrink: 0;
}
.btn-confirm {
    padding: 9px 20px; background: var(--green); color: #fff; border: none;
    border-radius: 9px; font-size: 13.5px; font-weight: 700; cursor: pointer;
    font-family: 'Jost', sans-serif; transition: background .15s;
}
.btn-confirm:hover { background: #15803d; }
.btn-reject {
    padding: 9px 20px; background: var(--white); color: var(--red);
    border: 1.5px solid var(--red); border-radius: 9px;
    font-size: 13.5px; font-weight: 700; cursor: pointer;
    font-family: 'Jost', sans-serif; transition: all .15s;
}
.btn-reject:hover { background: #fff5f5; }
.status-done {
    padding: 9px 20px; border-radius: 9px; font-size: 13.5px; font-weight: 700;
    cursor: default; border: 1.5px solid;
}
.status-done.paid { background: #f0fdf4; color: var(--green); border-color: #bbf7d0; }
.status-done.rejected { background: #fff5f5; color: var(--red); border-color: #fca5a5; }

/* ── MODAL ── */
.sb-modal-overlay { display: none; position: fixed; inset: 0; z-index: 10000; background: rgba(45,26,34,0.45); backdrop-filter: blur(6px); align-items: center; justify-content: center; }
.sb-modal-overlay.open { display: flex; }
.sb-modal-card { background: linear-gradient(145deg, #fff8f4, #fff3ed); border: 1.5px solid var(--petal); border-radius: 24px; padding: 32px 28px 24px; max-width: 340px; width: calc(100% - 40px); text-align: center; box-shadow: 0 24px 64px rgba(208,90,40,.22); position: relative; overflow: hidden; animation: modalPop .3s cubic-bezier(.34,1.56,.64,1); }
@keyframes modalPop { from{opacity:0;transform:scale(.88) translateY(16px)} to{opacity:1;transform:scale(1) translateY(0)} }
.sb-modal-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; background:linear-gradient(90deg,var(--petal),var(--rose),var(--deep-rose),var(--rose),var(--petal)); }
.sb-modal-icon { font-size: 46px; margin-bottom: 8px; animation: iconBounce .5s cubic-bezier(.34,1.56,.64,1) .1s both; }
@keyframes iconBounce { from{transform:scale(0) rotate(-20deg)} to{transform:scale(1) rotate(0)} }
.sb-modal-title { font-family: 'Cormorant Garamond', serif; font-size: 18px; font-weight: 700; color: var(--ink); margin-bottom: 6px; }
.sb-modal-msg { font-size: 14px; color: var(--muted); line-height: 1.55; margin-bottom: 20px; }
.sb-modal-msg strong { color: var(--deep-rose); font-weight: 700; }
.sb-modal-actions { display: flex; gap: 8px; justify-content: center; }
.sb-btn-cancel { padding: 9px 20px; border: 1.5px solid var(--border); background: var(--white); border-radius: 99px; font-family: 'Jost', sans-serif; font-size: 13.5px; font-weight: 600; color: var(--muted); cursor: pointer; transition: all .18s; }
.sb-btn-cancel:hover { border-color: var(--muted); color: var(--ink); }
.sb-btn-ok { padding: 9px 22px; border: none; border-radius: 99px; font-family: 'Jost', sans-serif; font-size: 13.5px; font-weight: 700; color: #fff; cursor: pointer; transition: all .18s; }
.sb-btn-ok.type-confirm { background: linear-gradient(135deg, var(--green), #15803d); box-shadow: 0 4px 14px rgba(22,163,74,.28); }
.sb-btn-ok.type-reject  { background: linear-gradient(135deg, var(--rose), var(--deep-rose)); box-shadow: 0 4px 14px rgba(208,90,40,.28); }
.sb-btn-ok:hover { filter: brightness(1.08); transform: translateY(-1px); }

/* ── LIGHTBOX ── */
.lightbox-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.85); z-index: 9999; align-items: center; justify-content: center; padding: 20px; cursor: pointer; }
.lightbox-overlay.open { display: flex; }
.lightbox-overlay img { max-width: 95vw; max-height: 90vh; border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,.5); cursor: default; }
.lightbox-close { position: fixed; top: 18px; right: 20px; color: #fff; font-size: 25px; font-weight: 700; cursor: pointer; background: rgba(0,0,0,.4); border: none; border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; font-family: 'Jost', sans-serif; }
.lightbox-close:hover { background: rgba(229,62,62,.7); }

.refresh-dot { width: 7px; height: 7px; border-radius: 50%; background: #16a34a; animation: pulseDot 1.4s ease-in-out infinite; display: inline-block; margin-right: 4px; vertical-align: middle; }

/* ── NEW payment chip ── */
@keyframes badgePop { from{transform:scale(0);opacity:0;} to{transform:scale(1);opacity:1;} }
@keyframes pulseDot2 { 0%,100%{opacity:1;transform:scale(1);} 50%{opacity:.55;transform:scale(1.3);} }
.new-pay-chip {
    display: inline-flex; align-items: center; gap: 3px;
    background: #e53e3e; color: #fff;
    font-size: 10px; font-weight: 800; letter-spacing: 0.8px; text-transform: uppercase;
    padding: 2px 7px; border-radius: 20px;
    animation: badgePop 0.4s cubic-bezier(.34,1.56,.64,1);
    flex-shrink: 0; vertical-align: middle; margin-left: 4px;
}
.new-pay-chip::before { content: '●'; font-size: 7px; animation: pulseDot2 1.2s ease-in-out infinite; }

/* ── NEW + reminder banners in detail panel ── */
.new-pay-banner {
    display: flex; align-items: center; gap: 10px;
    background: #fff5f5; border: 1.5px solid #feb2b2;
    border-radius: 12px; padding: 11px 16px;
    font-size: 14px; font-weight: 600; color: #c53030;
    animation: badgePop 0.35s cubic-bezier(.34,1.56,.64,1);
}
.unverified-reminder-banner {
    display: flex; align-items: center; gap: 10px;
    background: #fffbeb; border: 1.5px solid #fcd34d;
    border-radius: 12px; padding: 11px 16px;
    font-size: 14px; font-weight: 600; color: #92400e;
    animation: badgePop 0.35s cubic-bezier(.34,1.56,.64,1);
}
</style>
<?php include __DIR__ . '/pwa_head.php'; ?>
</head>
<body>

<!-- ── NAVBAR ── -->
<?php $activePage = 'payments'; include 'admin_navbar.php'; ?>

<!-- ── TWO-PANEL LAYOUT ── -->
<div class="adm-layout">

    <!-- LEFT: Order list -->
    <aside class="pay-sidebar">

        <div class="pay-sidebar-header">
            <div class="pay-sidebar-title-row">
                <div class="pay-sidebar-main-title">
                    <img src="images/logo/gcash.png" alt="GCash" style="width:28px;height:28px;object-fit:contain;border-radius:7px;">
                    GCash Payments
                </div>
                <div class="pay-sidebar-count"><?= $totalGcash ?> payment<?= $totalGcash != 1 ? 's' : '' ?></div>
            </div>
        </div>

        <div class="pay-tabs">
            <a href="?tab=all<?= $selected ? '&selected='.$selected : '' ?>"                  class="tab-btn <?= $tab==='all'                  ? 'active' : '' ?>">All <span class="tab-count"><?= $totalGcash ?></span></a>
            <a href="?tab=pending_verification<?= $selected ? '&selected='.$selected : '' ?>" class="tab-btn <?= $tab==='pending_verification' ? 'active' : '' ?>">⏳ Pending <span class="tab-count"><?= $countMap['pending_verification'] ?></span></a>
            <a href="?tab=paid<?= $selected ? '&selected='.$selected : '' ?>"                 class="tab-btn <?= $tab==='paid'                 ? 'active' : '' ?>">✅ Paid <span class="tab-count"><?= $countMap['paid'] ?></span></a>
            <a href="?tab=payment_rejected<?= $selected ? '&selected='.$selected : '' ?>"     class="tab-btn <?= $tab==='payment_rejected'     ? 'active' : '' ?>">❌ Rejected <span class="tab-count"><?= $countMap['payment_rejected'] ?></span></a>
        </div>

        <div class="pay-list">
        <?php if (empty($orders)): ?>
            <div style="padding:48px 24px;text-align:center;color:var(--muted);font-size:16px;">
                <?= $tab === 'pending_verification' ? '🎉 No pending verifications!' : 'No payments in this category.' ?>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $o):
                $isSelected = ($o['id'] == $selected);
                $isPayNew   = in_array((int)$o['id'], $unseenPayIds);
                $rawSt      = $o['status'];
                $stClass    = strtolower(str_replace([' ', '_'], ['-', '_'], $rawSt));
                $isPickup   = strtolower($o['delivery_mode'] ?? '') === 'pickup';
            ?>
            <div class="pay-item <?= $isSelected ? 'active' : '' ?>"
                 onclick="location.href='?tab=<?= urlencode($tab) ?>&selected=<?= $o['id'] ?>'">
                <div class="pi-left">
                    <div class="pi-top">
                        <span class="pi-order-id">ORD-<?= str_pad((int)$o['user_order_no'], 3, '0', STR_PAD_LEFT) ?></span>
                        <?php if ($isPayNew): ?><span class="new-pay-chip">NEW</span><?php endif; ?>
                        <div class="pi-chips">
                            <span class="status-pill <?= $stClass ?>"><?= htmlspecialchars($rawSt) ?></span>
                            <span class="oc-chip <?= $isPickup ? 'oc-chip-pickup' : 'oc-chip-delivery' ?>">
                                <?= $isPickup ? '🏪 Pickup' : '🚗 Delivery' ?>
                            </span>
                            <span class="oc-chip oc-chip-gcash">💙 GCash</span>
                        </div>
                    </div>
                    <div class="pi-customer"><?= htmlspecialchars($o['customer_name']) ?>
                        <?php if (!empty($o['customer_email'])): ?>
                            <span>· <?= htmlspecialchars($o['customer_email']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="pi-date"><?= date('M j, Y · g:i A', strtotime($o['created_at'])) ?></div>
                </div>
                <div class="pi-right">
                    <div class="pi-amount">₱<?= number_format((float)$o['total_price'], 2) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>

    </aside>

    <!-- RIGHT: Payment detail -->
    <section class="pay-detail">
    <?php if ($selOrder): ?>

        <!-- Header -->
        <div class="detail-header">
            <div>
                <div class="detail-order-no">
                    Order #<?= str_pad((int)$selOrder['user_order_no'], 3, '0', STR_PAD_LEFT) ?>
                    &nbsp;<?= statusBadge($selOrder['status']) ?>
                    <?php if ($selIsNew): ?><span class="new-pay-chip" style="font-size:11px;padding:3px 10px;">NEW</span><?php endif; ?>
                </div>
                <div class="detail-meta">
                    <span>👤 <?= htmlspecialchars($selOrder['customer_name']) ?></span>
                    <?php if (!empty($selOrder['customer_email'])): ?>
                    <span>📧 <?= htmlspecialchars($selOrder['customer_email']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($selOrder['customer_phone'])): ?>
                    <span>📞 <?= htmlspecialchars($selOrder['customer_phone']) ?></span>
                    <?php endif; ?>
                    <span>🚚 <?= ucfirst(htmlspecialchars($selOrder['delivery_mode'])) ?></span>
                    <span>💰 ₱<?= number_format((float)$selOrder['total_price'], 2) ?></span>
                    <span>🕐 <?= date('M j, Y · g:i A', strtotime($selOrder['created_at'])) ?></span>
                </div>
            </div>
            <div style="font-size:15px;color:var(--muted);">
                <span class="refresh-dot"></span> Auto-refreshes every 60s
            </div>
        </div>

        <!-- Body -->
        <div class="detail-body">

            <?php if ($selIsNew): ?>
            <div class="new-pay-banner">
                🆕 This is a new payment — you're reviewing it for the first time!
            </div>
            <?php endif; ?>
            <?php if ($selOrder['status'] === 'pending_verification'): ?>
            <div class="unverified-reminder-banner">
                ⏰ This payment has <strong>not been verified yet</strong> — review the screenshot and reference number below, then confirm or reject!
            </div>
            <?php endif; ?>

            <!-- GCash Ref -->
            <div>
                <div class="detail-section-label">GCash Reference Number</div>
                <?php if (!empty($selOrder['gcash_ref'])): ?>
                <div class="gcash-ref-box">
                    <div>
                        <div class="ref-label">Ref #</div>
                        <div class="ref-number"><?= htmlspecialchars($selOrder['gcash_ref']) ?></div>
                    </div>
                </div>
                <?php else: ?>
                <span class="no-ref">⚠️ No reference number submitted</span>
                <?php endif; ?>
            </div>

            <!-- Screenshot -->
            <div>
                <div class="detail-section-label">📸 Payment Screenshot</div>
                <?php if (!empty($selOrder['gcash_screenshot']) && file_exists(__DIR__ . '/' . $selOrder['gcash_screenshot'])): ?>
                <img class="screenshot-thumb"
                     src="<?= htmlspecialchars($selOrder['gcash_screenshot']) ?>"
                     alt="GCash receipt"
                     onclick="openLightbox(this.src)"
                     title="Click to enlarge">
                <div style="font-size:14px;color:var(--muted);margin-top:4px;">Click image to enlarge</div>
                <?php else: ?>
                <span class="no-screenshot">⚠️ No screenshot uploaded</span>
                <?php endif; ?>
            </div>

            <!-- DB info -->
            <div style="font-size:15px;color:var(--muted);">
                Order DB ID: #<?= (int)$selOrder['id'] ?>
            </div>

        </div>

        <!-- Action buttons -->
        <div class="detail-actions">
            <?php if ($selOrder['status'] === 'pending_verification'): ?>
            <form method="POST" id="form-confirm-<?= (int)$selOrder['id'] ?>" style="margin:0;">
                <input type="hidden" name="confirm_order_id" value="<?= (int)$selOrder['id'] ?>">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                <button type="button" class="btn-confirm"
                    onclick="showModal('confirm', <?= (int)$selOrder['id'] ?>, <?= (int)$selOrder['user_order_no'] ?>, '<?= number_format((float)$selOrder['total_price'], 2) ?>')">
                    ✅ Confirm Paid
                </button>
            </form>
            <form method="POST" id="form-reject-<?= (int)$selOrder['id'] ?>" style="margin:0;">
                <input type="hidden" name="reject_order_id" value="<?= (int)$selOrder['id'] ?>">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                <button type="button" class="btn-reject"
                    onclick="showModal('reject', <?= (int)$selOrder['id'] ?>, <?= (int)$selOrder['user_order_no'] ?>, '<?= number_format((float)$selOrder['total_price'], 2) ?>')">
                    ❌ Reject
                </button>
            </form>
            <?php elseif (in_array($selOrder['status'], ['paid', 'Paid', 'Delivered', 'Picked Up', 'Out for Delivery', 'Preparing'])): ?>
            <div class="status-done paid">✅ Payment Confirmed</div>
            <?php elseif (in_array($selOrder['status'], ['payment_rejected', 'Cancelled'])): ?>
            <div class="status-done rejected">❌ Payment Rejected</div>
            <?php else: ?>
            <div class="status-done paid"><?= htmlspecialchars($selOrder['status']) ?></div>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <div class="pay-detail-empty">
            <div class="empty-icon"><img src="images/logo/gcash.png" alt="GCash" style="width:64px;height:64px;object-fit:contain;opacity:0.5;border-radius:14px;"></div>
            <p>Select a payment on the left to review it.</p>
        </div>
    <?php endif; ?>
    </section>

</div>

<!-- ── MODAL ── -->
<div class="sb-modal-overlay" id="sbModal" onclick="closeModal()">
    <div class="sb-modal-card" onclick="event.stopPropagation()">
        <div class="sb-modal-icon" id="sbModalIcon"></div>
        <div class="sb-modal-title" id="sbModalTitle"></div>
        <div class="sb-modal-msg" id="sbModalMsg"></div>
        <div class="sb-modal-actions">
            <button class="sb-btn-cancel" onclick="closeModal()">Cancel</button>
            <button class="sb-btn-ok" id="sbModalOk">Confirm</button>
        </div>
    </div>
</div>

<!-- ── LIGHTBOX ── -->
<div class="lightbox-overlay" id="lightbox" onclick="closeLightbox()">
    <button class="lightbox-close" onclick="closeLightbox()">✕</button>
    <img id="lightboxImg" src="" alt="Receipt screenshot" onclick="event.stopPropagation()">
</div>

<script>
// Settings dropdown

// Modal
function showModal(type, orderId, orderNo, amount) {
    const icon  = document.getElementById('sbModalIcon');
    const title = document.getElementById('sbModalTitle');
    const msg   = document.getElementById('sbModalMsg');
    const okBtn = document.getElementById('sbModalOk');
    if (type === 'confirm') {
        icon.textContent  = '✅';
        title.textContent = 'Confirm Payment';
        msg.innerHTML     = `Mark <strong>Order #${orderNo}</strong> as <strong>PAID</strong>?<br><span style="font-size:14px;">Amount: ₱${amount}</span>`;
        okBtn.textContent = 'Yes, mark paid!';
        okBtn.className   = 'sb-btn-ok type-confirm';
        okBtn.onclick     = () => document.getElementById('form-confirm-' + orderId).submit();
    } else {
        icon.textContent  = '❌';
        title.textContent = 'Reject Payment?';
        msg.innerHTML     = `Reject GCash payment for <strong>Order #${orderNo}</strong>?<br><span style="font-size:14px;">This cannot be undone.</span>`;
        okBtn.textContent = 'Yes, reject it';
        okBtn.className   = 'sb-btn-ok type-reject';
        okBtn.onclick     = () => document.getElementById('form-reject-' + orderId).submit();
    }
    document.getElementById('sbModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeModal() {
    document.getElementById('sbModal').classList.remove('open');
    document.body.style.overflow = '';
}

// Lightbox
function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightbox').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    document.getElementById('lightbox').classList.remove('open');
    document.getElementById('lightboxImg').src = '';
    document.body.style.overflow = '';
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeModal(); closeLightbox(); } });
</script>
</body>
</html>