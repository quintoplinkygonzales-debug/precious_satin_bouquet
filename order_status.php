<?php
session_start();
include "config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$uid = (int) $_SESSION['user_id'];

// Get order_id from URL
$order_id = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
if ($order_id <= 0) {
    header("Location: orders.php");
    exit();
}

// ── Handle customer confirmation ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_received'])) {
    $oid  = (int) $_POST['order_id'];
    $mode = $_POST['confirm_mode'] ?? 'delivery'; // 'delivery' or 'pickup'

    if ($oid > 0 && $oid === $order_id) {
        if ($mode === 'pickup') {
            // Pickup: only when Ready for Pickup
            $chk = $conn->prepare("
                SELECT id FROM orders
                WHERE id=? AND user_id=? AND delivery_mode='pickup'
                  AND status='Ready for Pickup' AND customer_confirmed=0
            ");
            $chk->bind_param('ii', $oid, $uid);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $upd = $conn->prepare("UPDATE orders SET status='Picked Up', customer_confirmed=1 WHERE id=?");
                $upd->bind_param('i', $oid);
                $upd->execute();
                $upd->close();
            }
            $chk->close();
        } else {
            // Delivery: allow Out for Delivery OR already Delivered (admin-set) but not yet confirmed
            $chk = $conn->prepare("
                SELECT id FROM orders
                WHERE id=? AND user_id=? AND delivery_mode='delivery'
                  AND status IN ('Out for Delivery','Delivered')
                  AND customer_confirmed=0
            ");
            $chk->bind_param('ii', $oid, $uid);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $upd = $conn->prepare("UPDATE orders SET status='Delivered', customer_confirmed=1 WHERE id=?");
                $upd->bind_param('i', $oid);
                $upd->execute();
                $upd->close();
            }
            $chk->close();
        }
    }
    header("Location: order_status.php?order_id=$order_id&confirmed=1");
    exit();
}

// Fetch order — must belong to this user
$orderStmt = $conn->prepare("
    SELECT o.id, o.user_order_no, o.total_price, o.status,
           o.payment_method, o.delivery_mode, o.customer_confirmed, o.created_at
    FROM orders o
    WHERE o.id = ? AND o.user_id = ?
");
$orderStmt->bind_param('ii', $order_id, $uid);
$orderStmt->execute();
$order = $orderStmt->get_result()->fetch_assoc();
$orderStmt->close();

if (!$order) {
    header("Location: orders.php");
    exit();
}

// Fetch items
$itemsResult = $conn->query("
    SELECT product_name, quantity, price, wrapper, ribbon, addons
    FROM order_items
    WHERE order_id = $order_id
");
$items = $itemsResult ? $itemsResult->fetch_all(MYSQLI_ASSOC) : [];

// ── Timeline steps definition ─────────────────────────────────────────────────
$isPickup = strtolower($order['delivery_mode'] ?? '') === 'pickup';

$steps = $isPickup
  ? [
      ['key' => 'Pending',           'label' => 'Order Placed',     'icon' => '🌸', 'desc' => 'We\'ve received your order and it\'s being reviewed.'],
      ['key' => 'Preparing',         'label' => 'Preparing',        'icon' => '✂️', 'desc' => 'Our team is crafting your bouquet with love.'],
      ['key' => 'Ready for Pickup',  'label' => 'Ready for Pickup', 'icon' => '🏪', 'desc' => 'Your bouquet is ready! Come pick it up at our store.'],
    ]
  : [
      ['key' => 'Pending',           'label' => 'Order Placed',     'icon' => '🌸', 'desc' => 'We\'ve received your order and it\'s being reviewed.'],
      ['key' => 'Preparing',         'label' => 'Preparing',        'icon' => '✂️', 'desc' => 'Our team is crafting your bouquet with love.'],
      ['key' => 'Out for Delivery',  'label' => 'Out for Delivery', 'icon' => '🚗', 'desc' => 'Your blooms are on their way to you!'],
      ['key' => 'Delivered',         'label' => 'Delivered',        'icon' => '💐', 'desc' => 'Your order has arrived. Enjoy your flowers!'],
    ];

// Determine active step index
$statusMap = $isPickup
  ? [
      'Pending'          => 0,
      'Preparing'        => 1,
      'Ready for Pickup' => 2,
    ]
  : [
      'Pending'          => 0,
      'Preparing'        => 1,
      'Out for Delivery' => 2,
      'To Deliver'       => 2, // legacy alias
      'Delivered'        => 3,
    ];
$currentStep = $statusMap[$order['status']] ?? 0;

// Helper: format addons
function fmtAddons(?string $json): string {
    if (!$json) return '';
    $arr = json_decode($json, true);
    if (empty($arr)) return '';
    $parts = [];
    foreach ($arr as $name => $price) {
        $parts[] = htmlspecialchars($name) . ' (₱' . number_format((float)$price, 2) . ')';
    }
    return implode(', ', $parts);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order Status · Precious Satin Bouquet</title>
<link rel="icon" type="image/jpeg" href="images/logo/SB.png">
<link rel="stylesheet" href="style.css">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,400&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>

  /* ── Root & page ───────────────────────────────────────────────── */
  :root {
    --cream:   #faf8f5;
    --white:   #ffffff;
    --bark:    #2c1f14;
    --gold:    #c9a97a;
    --gold-lt: #fdf3e8;
    --muted:   #9b8e84;
    --border:  #ede8e2;
    --green:   #22c55e;
    --blue:    #3b82f6;
    --amber:   #f59e0b;
  }

  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    background: var(--cream);
    font-family: 'DM Sans', sans-serif;
    color: var(--bark);
    min-height: 100vh;
  }

  .os-page {
    padding: 3rem 1rem 6rem;
    max-width: 660px;
    margin: 0 auto;
  }

  /* ── Back link ─────────────────────────────────────────────────── */
  .os-back {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.85rem;
    color: var(--muted);
    text-decoration: none;
    margin-bottom: 2rem;
    transition: color 0.15s;
  }
  .os-back:hover { color: var(--bark); }

  /* ── Order header card ─────────────────────────────────────────── */
  .os-header {
    background: var(--bark);
    border-radius: 18px;
    padding: 2rem 2rem 1.8rem;
    margin-bottom: 1.2rem;
    position: relative;
    overflow: hidden;
  }

  .os-header::before {
    content: '🌸';
    position: absolute;
    right: -10px;
    top: -10px;
    font-size: 7rem;
    opacity: 0.06;
    line-height: 1;
    pointer-events: none;
  }

  .os-order-label {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.15em;
    color: var(--gold);
    font-weight: 500;
    margin-bottom: 0.4rem;
  }

  .os-order-num {
    font-family: 'Cormorant Garamond', serif;
    font-size: 2.6rem;
    font-weight: 600;
    color: #fff;
    line-height: 1.1;
    margin-bottom: 0.9rem;
  }

  .os-meta-row {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
  }

  .os-meta-chip {
    font-size: 0.78rem;
    background: rgba(255,255,255,0.1);
    color: #f5ede0;
    border-radius: 20px;
    padding: 4px 12px;
    font-weight: 400;
  }

  .os-status-chip {
    font-size: 0.78rem;
    border-radius: 20px;
    padding: 4px 12px;
    font-weight: 600;
    color: #fff;
  }

  /* ── Timeline card ─────────────────────────────────────────────── */
  .os-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 18px;
    padding: 2rem;
    margin-bottom: 1.2rem;
  }

  .os-section-title {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.14em;
    color: var(--muted);
    font-weight: 500;
    margin-bottom: 1.6rem;
  }

  /* ── Timeline ──────────────────────────────────────────────────── */
  .os-timeline {
    position: relative;
    padding-left: 0;
    list-style: none;
  }

  /* vertical line */
  .os-timeline::before {
    content: '';
    position: absolute;
    left: 22px;
    top: 22px;
    bottom: 22px;
    width: 2px;
    background: var(--border);
    border-radius: 2px;
  }

  .os-step {
    display: flex;
    gap: 1.2rem;
    align-items: flex-start;
    padding-bottom: 2rem;
    position: relative;
    animation: fadeUp 0.5s ease both;
  }

  .os-step:last-child { padding-bottom: 0; }

  /* dot */
  .os-dot {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    flex-shrink: 0;
    position: relative;
    z-index: 1;
    transition: transform 0.3s ease;
    border: 2px solid var(--border);
    background: var(--cream);
  }

  .os-step.done .os-dot {
    background: var(--gold-lt);
    border-color: var(--gold);
  }

  .os-step.active .os-dot {
    background: var(--bark);
    border-color: var(--bark);
    box-shadow: 0 0 0 5px rgba(201,169,122,0.20);
    animation: pulse 2s infinite;
  }

  .os-step.active .os-dot .os-dot-icon {
    filter: grayscale(0);
  }

  .os-step.future .os-dot {
    opacity: 0.4;
  }

  .os-dot-icon {
    line-height: 1;
  }

  /* step text */
  .os-step-body {
    padding-top: 8px;
    flex: 1;
  }

  .os-step-label {
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--bark);
    line-height: 1.2;
  }

  .os-step.future .os-step-label { color: var(--muted); }

  .os-step-desc {
    font-size: 0.88rem;
    color: var(--muted);
    margin-top: 3px;
    line-height: 1.5;
  }

  .os-step-badge {
    display: inline-block;
    margin-top: 6px;
    font-size: 0.75rem;
    background: var(--gold-lt);
    color: #a07640;
    border-radius: 10px;
    padding: 2px 10px;
    font-weight: 600;
    letter-spacing: 0.04em;
  }

  /* ── Items card ────────────────────────────────────────────────── */
  .os-item-row {
    padding: 0.8rem 0;
    border-bottom: 1px dashed var(--border);
  }
  .os-item-row:last-child { border-bottom: none; }

  .os-item-main {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 1rem;
  }

  .os-item-name {
    font-size: 1rem;
    color: var(--bark);
    font-weight: 500;
  }

  .os-item-price {
    font-size: 0.95rem;
    color: var(--muted);
    white-space: nowrap;
  }

  .os-item-price strong { color: var(--bark); }

  .os-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    margin-top: 6px;
  }

  .os-tag {
    font-size: 0.73rem;
    background: var(--gold-lt);
    color: #a07640;
    border-radius: 12px;
    padding: 2px 10px;
    font-weight: 500;
  }

  /* ── Total row ─────────────────────────────────────────────────── */
  .os-total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 1.2rem;
    padding-top: 1rem;
    border-top: 1.5px solid var(--border);
  }

  .os-total-label {
    font-size: 0.82rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--muted);
    font-weight: 500;
  }

  .os-total-amount {
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.7rem;
    font-weight: 600;
    color: var(--bark);
  }

  /* ── Animations ────────────────────────────────────────────────── */
  @keyframes pulse {
    0%   { box-shadow: 0 0 0 0   rgba(201,169,122,0.35); }
    70%  { box-shadow: 0 0 0 10px rgba(201,169,122,0);   }
    100% { box-shadow: 0 0 0 0   rgba(201,169,122,0);    }
  }

  @keyframes fadeUp {
    from { opacity: 0; transform: translateY(14px); }
    to   { opacity: 1; transform: translateY(0);    }
  }

  .os-step:nth-child(1) { animation-delay: 0.05s; }
  .os-step:nth-child(2) { animation-delay: 0.15s; }
  .os-step:nth-child(3) { animation-delay: 0.25s; }
  .os-step:nth-child(4) { animation-delay: 0.35s; }

  /* ── Progress bar (top of timeline card) ──────────────────────── */
  .os-progress-wrap {
    background: var(--border);
    border-radius: 99px;
    height: 5px;
    margin-bottom: 2rem;
    overflow: hidden;
  }

  .os-progress-bar {
    height: 100%;
    border-radius: 99px;
    background: linear-gradient(90deg, var(--gold), #e8c890);
    transition: width 0.8s cubic-bezier(0.4,0,0.2,1);
  }

  /* ── Confirm received ──────────────────────────────────────────── */
  .os-confirm-wrap {
    background: linear-gradient(135deg, #f0fdf4, #dcfce7);
    border: 1.5px solid #86efac;
    border-radius: 16px;
    padding: 1.2rem 1.4rem;
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    margin-bottom: 0;
  }

  .os-confirm-text { flex: 1; min-width: 180px; }
  .os-confirm-text strong {
    display: block;
    font-size: 0.9rem;
    color: #15803d;
    font-weight: 700;
    margin-bottom: 3px;
  }
  .os-confirm-text span {
    font-size: 0.8rem;
    color: #16a34a;
    line-height: 1.4;
  }

  .os-confirm-btn {
    background: #16a34a;
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 11px 22px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.86rem;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.18s, transform 0.15s;
    white-space: nowrap;
    flex-shrink: 0;
  }
  .os-confirm-btn:hover { background: #15803d; transform: translateY(-1px); }

  .os-confirmed-banner {
    background: #f0fdf4;
    border: 1.5px solid #86efac;
    border-radius: 14px;
    padding: 14px 18px;
    color: #15803d;
    font-size: 0.9rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 0;
  }

  .os-success-toast {
    background: #f0fdf4;
    border: 1.5px solid #86efac;
    border-radius: 14px;
    padding: 14px 20px;
    margin-bottom: 1.2rem;
    color: #15803d;
    font-weight: 600;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 10px;
  }

</style>
<?php include __DIR__ . '/pwa_head.php'; ?>
</head>
<body>

<?php include "header.php"; ?>

<div class="os-page">

  <a href="orders.php" class="os-back">
    ← Back to My Orders
  </a>

  <?php if (isset($_GET['confirmed'])): ?>
  <script>window._showThankyou = true;</script>
  <?php endif; ?>

  <!-- ── Order header ── -->
  <div class="os-header">
    <div class="os-order-label">Order Number</div>
    <div class="os-order-num">#<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?></div>
    <div class="os-meta-row">
      <span class="os-meta-chip">📅 <?= date('F j, Y', strtotime($order['created_at'])) ?></span>
      <?php if (!empty($order['payment_method'])): ?>
        <span class="os-meta-chip">💳 <?= strtoupper(htmlspecialchars($order['payment_method'])) ?></span>
      <?php endif; ?>
      <?php
        $sc = match(strtolower($order['status'])) {
            'delivered'        => '#22c55e',
            'out for delivery',
            'to deliver'       => '#3b82f6',
            'preparing'        => '#a855f7',
            default            => '#f59e0b',
        };
      ?>
      <span class="os-status-chip" style="background:<?= $sc ?>;">
        <?= htmlspecialchars($order['status']) ?>
      </span>
    </div>
  </div>

  <!-- ── Timeline card ── -->
  <div class="os-card">
    <div class="os-section-title">Delivery Progress</div>

    <!-- Progress bar -->
    <?php $pct = round(($currentStep / (count($steps) - 1)) * 100); ?>
    <div class="os-progress-wrap">
      <div class="os-progress-bar" style="width:<?= $pct ?>%;"></div>
    </div>

    <ul class="os-timeline">
      <?php foreach ($steps as $i => $step):
        if ($i < $currentStep)       $cls = 'done';
        elseif ($i === $currentStep) $cls = 'active';
        else                         $cls = 'future';
      ?>
        <li class="os-step <?= $cls ?>">
          <div class="os-dot">
            <span class="os-dot-icon"><?= $step['icon'] ?></span>
          </div>
          <div class="os-step-body">
            <div class="os-step-label"><?= htmlspecialchars($step['label']) ?></div>
            <div class="os-step-desc"><?= htmlspecialchars($step['desc']) ?></div>
            <?php if ($cls === 'active'): ?>
              <span class="os-step-badge">Current Status</span>
            <?php elseif ($cls === 'done'): ?>
              <span class="os-step-badge" style="background:#f0fdf4;color:#16a34a;">✓ Done</span>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>

  <!-- ── Order items card ── -->
  <div class="os-card">
    <div class="os-section-title">Order Items</div>

    <?php if (!empty($items)): ?>
      <?php foreach ($items as $item): ?>
        <div class="os-item-row">
          <div class="os-item-main">
            <span class="os-item-name"><?= htmlspecialchars($item['product_name']) ?></span>
            <span class="os-item-price">
              x<?= (int)$item['quantity'] ?> &nbsp;
              <strong>₱<?= number_format($item['price'] * $item['quantity'], 2) ?></strong>
            </span>
          </div>
          <?php
            $tags = [];
            if (!empty($item['wrapper'])) $tags[] = '🎀 ' . htmlspecialchars($item['wrapper']);
            if (!empty($item['ribbon']))  $tags[] = '🎗️ ' . htmlspecialchars($item['ribbon']);
            $ao = fmtAddons($item['addons'] ?? null);
            if ($ao) $tags[] = '🍫 ' . $ao;
          ?>
          <?php if (!empty($tags)): ?>
            <div class="os-tags">
              <?php foreach ($tags as $t): ?>
                <span class="os-tag"><?= $t ?></span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p style="font-size:0.88rem;color:var(--muted);">No item details found.</p>
    <?php endif; ?>

    <div class="os-total-row">
      <span class="os-total-label">Order Total</span>
      <span class="os-total-amount">₱<?= number_format($order['total_price'], 2) ?></span>
    </div>
  </div>

  <?php
    $alreadyConfirmed = !empty($order['customer_confirmed']);
    $statusLower      = strtolower($order['status']);
    $canConfirmDel    = !$isPickup && in_array($statusLower, ['out for delivery', 'delivered']) && !$alreadyConfirmed;
    $canConfirmPick   = $isPickup  && $statusLower === 'ready for pickup' && !$alreadyConfirmed;
  ?>


  <?php if ($canConfirmDel || $canConfirmPick): ?>
  <div class="os-card" style="padding:0;overflow:hidden;border:none;background:transparent;">
    <div class="os-confirm-wrap">
      <div class="os-confirm-text">
        <?php if ($canConfirmPick): ?>
          <strong>🏪 Ready to pick up your order?</strong>
          <span>Tap below to confirm you've picked up your bouquet at our store.</span>
        <?php else: ?>
          <strong>📦 Did you receive your order?</strong>
          <span>Tap below to confirm your bouquet has been delivered successfully.</span>
        <?php endif; ?>
      </div>
      <form id="os-confirm-form" method="POST" action="order_status.php?order_id=<?= $order_id ?>">
        <input type="hidden" name="confirm_received" value="1">
        <input type="hidden" name="order_id" value="<?= $order_id ?>">
        <input type="hidden" name="confirm_mode" value="<?= $isPickup ? 'pickup' : 'delivery' ?>">
        <button type="button" class="os-confirm-btn"
          onclick="openOsConfirmPopup('<?= $canConfirmPick ? 'pickup' : 'delivery' ?>')">
          <?= $canConfirmPick ? '🏪 Confirm Pickup' : '💐 Confirm Delivery' ?>
        </button>
      </form>
    </div>
  </div>
  <?php elseif ($alreadyConfirmed): ?>
  <div class="os-confirmed-banner">
    ✅ You confirmed <?= $isPickup ? 'pickup' : 'delivery' ?> of this order. Thank you!
  </div>
  <?php endif; ?>

</div><!-- .os-page -->

<!-- ── Girly Confirm Popup ── -->
<div id="os-popup-overlay" class="os-popup-overlay" onclick="closeOsConfirmPopup()">
  <div class="os-popup" onclick="event.stopPropagation()">
    <span class="os-pop-petal p1">🌸</span>
    <span class="os-pop-petal p2">🌺</span>
    <span class="os-pop-petal p3">🌸</span>
    <span class="os-pop-petal p4">💮</span>

    <div class="os-pop-icon" id="os-pop-icon">💐</div>
    <h2 class="os-pop-title" id="os-pop-title">Did you receive your bouquet?</h2>
    <p class="os-pop-desc" id="os-pop-desc">Once confirmed, your order will be marked as delivered. This can't be undone!</p>

    <div class="os-pop-btns">
      <button class="os-pop-cancel" onclick="closeOsConfirmPopup()">Not yet 🙈</button>
      <button class="os-pop-ok" id="os-pop-ok" onclick="document.getElementById('os-confirm-form').submit()">Yes, I got it! 💕</button>
    </div>
  </div>
</div>

<style>
.os-popup-overlay {
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
.os-popup-overlay.open { display: flex; animation: os-fade-in 0.22s ease; }
@keyframes os-fade-in { from { opacity:0; } to { opacity:1; } }

.os-popup {
  background: #fff;
  border-radius: 28px;
  padding: 2.4rem 2rem 2rem;
  max-width: 360px;
  width: 100%;
  text-align: center;
  position: relative;
  overflow: hidden;
  box-shadow: 0 24px 80px rgba(180,80,110,0.22), 0 4px 20px rgba(0,0,0,0.06);
  animation: os-pop-in 0.32s cubic-bezier(0.34,1.56,0.64,1);
  border: 1.5px solid #ede8e2;
}
@keyframes os-pop-in {
  from { opacity:0; transform: scale(0.82) translateY(24px); }
  to   { opacity:1; transform: scale(1)    translateY(0);    }
}
.os-popup::before {
  content: '';
  position: absolute;
  top:0; left:0; right:0;
  height: 6px;
  background: linear-gradient(90deg, #f9a8c9, #f7c5d8, #f9a8c9);
  border-radius: 28px 28px 0 0;
}
.os-pop-petal {
  position: absolute;
  pointer-events: none;
  animation: os-float 3s ease-in-out infinite alternate;
}
.os-pop-petal.p1 { top:14px;    left:18px;   font-size:1.3rem; animation-delay:0s;   }
.os-pop-petal.p2 { top:12px;    right:20px;  font-size:1rem;   animation-delay:0.6s; }
.os-pop-petal.p3 { bottom:60px; left:14px;   font-size:0.9rem; animation-delay:1.1s; }
.os-pop-petal.p4 { bottom:55px; right:16px;  font-size:1rem;   animation-delay:0.3s; }
@keyframes os-float {
  from { transform: translateY(0) rotate(-8deg); }
  to   { transform: translateY(-8px) rotate(8deg); }
}
.os-pop-icon {
  font-size: 3.4rem;
  line-height: 1;
  margin-bottom: 0.9rem;
  display: block;
  filter: drop-shadow(0 4px 12px rgba(200,80,120,0.2));
}
.os-pop-title {
  font-family: 'Cormorant Garamond', serif;
  font-size: 1.65rem;
  font-weight: 600;
  color: #2c1f14;
  margin: 0 0 0.5rem;
  line-height: 1.2;
}
.os-pop-desc {
  font-size: 0.84rem;
  color: #9b8e84;
  line-height: 1.55;
  margin: 0 0 1.6rem;
}
.os-pop-btns { display: flex; gap: 10px; justify-content: center; }
.os-pop-cancel {
  flex: 1; padding: 0.7rem 1rem; border-radius: 50px;
  border: 1.5px solid var(--border); background: #fff;
  color: var(--muted); font-family: 'DM Sans', sans-serif;
  font-size: 0.85rem; font-weight: 600; cursor: pointer;
  transition: background 0.18s, border-color 0.18s;
}
.os-pop-cancel:hover { background: #fdf3e8; border-color: var(--gold); }
.os-pop-ok {
  flex: 1; padding: 0.7rem 1rem; border-radius: 50px; border: none;
  background: linear-gradient(135deg, #f472b6, #ec4899);
  color: #fff; font-family: 'DM Sans', sans-serif;
  font-size: 0.85rem; font-weight: 700; cursor: pointer;
  box-shadow: 0 4px 16px rgba(236,72,153,0.30);
  transition: transform 0.15s, box-shadow 0.15s;
}
.os-pop-ok:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(236,72,153,0.38); }
</style>

<script>
function openOsConfirmPopup(mode) {
  var icon  = document.getElementById('os-pop-icon');
  var title = document.getElementById('os-pop-title');
  var desc  = document.getElementById('os-pop-desc');
  var okBtn = document.getElementById('os-pop-ok');

  if (mode === 'pickup') {
    icon.textContent  = '🏪';
    title.textContent = 'Picked up your bouquet?';
    desc.textContent  = "Once confirmed, your order will be marked as picked up. This can't be undone!";
    okBtn.textContent = 'Yes, I got it! 🛍️';
  } else {
    icon.textContent  = '💐';
    title.textContent = 'Did you receive your bouquet?';
    desc.textContent  = "Once confirmed, your order will be marked as delivered. This can't be undone!";
    okBtn.textContent = 'Yes, I got it! 💕';
  }
  document.getElementById('os-popup-overlay').classList.add('open');
}
function closeOsConfirmPopup() {
  document.getElementById('os-popup-overlay').classList.remove('open');
}
</script>

<!-- ── Thank You Popup ── -->
<div id="os-thankyou-overlay" class="os-popup-overlay" onclick="closeOsThankyouPopup()">
  <div class="os-popup os-thankyou-popup" onclick="event.stopPropagation()">
    <span class="os-pop-petal p1">🌸</span>
    <span class="os-pop-petal p2">🌺</span>
    <span class="os-pop-petal p3">💐</span>
    <span class="os-pop-petal p4">🌷</span>
    <span class="os-pop-petal p5">✨</span>

    <div class="os-ty-burst">🎉</div>
    <div class="os-pop-icon">💐</div>
    <h2 class="os-pop-title">Yay! So glad it arrived!</h2>
    <p class="os-pop-desc">
      Thank you for confirming, lovely! 🌸<br>
      We hope your blooms bring you so much joy. 💕
    </p>
    <button class="os-pop-ok os-ty-btn" onclick="closeOsThankyouPopup()">
      Aww, thank you! 🎀
    </button>
  </div>
</div>

<style>
.os-thankyou-popup::before {
  background: linear-gradient(90deg, #f9a8c9, #c084fc, #f9a8c9) !important;
}
.os-pop-petal.p5 { top: 50%; left: 10px; animation-delay: 0.8s; font-size: 0.85rem; }
.os-ty-burst {
  font-size: 2.2rem;
  display: block;
  margin-bottom: 0.3rem;
  animation: os-burst 0.5s cubic-bezier(0.34,1.56,0.64,1) both;
  animation-delay: 0.15s;
}
@keyframes os-burst {
  from { opacity:0; transform: scale(0) rotate(-20deg); }
  to   { opacity:1; transform: scale(1) rotate(0deg);   }
}
.os-ty-btn { width: 100%; display: block; flex: unset; }
</style>

<script>
function closeOsConfirmPopup() {
  document.getElementById('os-popup-overlay').classList.remove('open');
}
function closeOsThankyouPopup() {
  document.getElementById('os-thankyou-overlay').classList.remove('open');
  history.replaceState({}, '', window.location.pathname + '?order_id=<?= $order_id ?>');
}
function openOsConfirmPopup(mode) {
  var icon  = document.getElementById('os-pop-icon');
  var title = document.getElementById('os-pop-title');
  var desc  = document.getElementById('os-pop-desc');
  var okBtn = document.getElementById('os-pop-ok');

  if (mode === 'pickup') {
    icon.textContent  = '🏪';
    title.textContent = 'Picked up your bouquet?';
    desc.textContent  = "Once confirmed, your order will be marked as picked up. This can't be undone!";
    okBtn.textContent = 'Yes, I got it! 🛍️';
  } else {
    icon.textContent  = '💐';
    title.textContent = 'Did you receive your bouquet?';
    desc.textContent  = "Once confirmed, your order will be marked as delivered. This can't be undone!";
    okBtn.textContent = 'Yes, I got it! 💕';
  }
  document.getElementById('os-popup-overlay').classList.add('open');
}

if (window._showThankyou) {
  window.addEventListener('DOMContentLoaded', function() {
    document.getElementById('os-thankyou-overlay').classList.add('open');
  });
}
</script>

</body>
</html>