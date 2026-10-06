<?php
session_start();
include "config.php";

if (!isset($_SESSION['user_id'])) die("Login first");

$uid  = $_SESSION['user_id'];
$user = $conn->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();
if ($user['role'] != 'admin') die("Access denied");

$adminName = $user['username'] ?? 'Admin';

$ordersSummary  = $conn->query("SELECT COUNT(*) AS total_orders, COALESCE(SUM(total_price), 0) AS total_sales FROM orders")->fetch_assoc();
$totalProducts  = $conn->query("SELECT COUNT(*) AS total_products FROM products")->fetch_assoc()['total_products'];
$totalCustomers = $conn->query("SELECT COUNT(*) AS total_customers FROM users WHERE role='customer'")->fetch_assoc()['total_customers'];

$recentOrders = $conn->query(
    "SELECT o.id, o.total_price, o.status, o.created_at, u.username
     FROM orders o
     LEFT JOIN users u ON u.id = o.user_id
     ORDER BY o.created_at DESC
     LIMIT 5"
);

$unreadMessages = $conn->query("SELECT COUNT(*) AS c FROM contact_messages WHERE is_read=0")->fetch_assoc()['c'] ?? 0;

// ── GCash pending count — shown as badge in nav ───────────────────────────────
$pendingGcash = (int)($conn->query(
    "SELECT COUNT(*) AS c FROM orders WHERE payment_method='gcash' AND status='pending_verification'"
)->fetch_assoc()['c'] ?? 0);

$topProducts = $conn->query(
    "SELECT p.id, p.name, p.image, COALESCE(SUM(oi.quantity), 0) AS sold, p.price
     FROM products p
     LEFT JOIN order_items oi ON oi.product_name = p.name
     GROUP BY p.id, p.name, p.image, p.price
     ORDER BY sold DESC
     LIMIT 5"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — Precious Satin Bouquet</title>
<link rel="icon" type="image/png" href="images/logo/SB.png">
<link rel="stylesheet" href="style.css">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Jost:wght@300;400;500;600;700&family=Great+Vibes&display=swap" rel="stylesheet">
<style>
/* ── VARIABLES ── */
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
    --border:    rgba(255,170,203,0.22);
    --shadow-sm: 0 2px 12px rgba(208,90,40,0.10);
    --shadow-md: 0 8px 32px rgba(208,90,40,0.14);
    --shadow-lg: 0 20px 60px rgba(208,90,40,0.18);
    --radius:    18px;
    --radius-sm: 10px;
}

* { box-sizing: border-box; margin: 0; padding: 0; }

body {
    background: var(--cream);
    font-family: 'Jost', sans-serif;
    min-height: 100vh;
    color: var(--ink);
    overflow-x: hidden;
}

body::before {
    content: '';
    position: fixed;
    inset: 0;
    background:
        radial-gradient(ellipse 60% 40% at 90% 10%, rgba(255,200,160,0.4) 0%, transparent 70%),
        radial-gradient(ellipse 50% 60% at 5% 80%, rgba(252,232,240,0.5) 0%, transparent 65%),
        radial-gradient(ellipse 40% 30% at 50% 50%, rgba(253,246,249,0.8) 0%, transparent 80%);
    pointer-events: none;
    z-index: 0;
}

/* ── Navbar styles live in admin_navbar.php ── */

/* ── PAGE BODY ── */
.adm-body {
    position: relative;
    z-index: 1;
    max-width: 1200px;
    margin: 0 auto;
    padding: 48px 40px 80px;
}

/* Welcome banner */
.adm-welcome {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--white);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    padding: 32px 40px;
    margin-bottom: 40px;
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    position: relative;
}
.adm-welcome::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(255,200,160,0.3) 0%, transparent 60%);
    pointer-events: none;
}
.adm-welcome-eyebrow {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 3px;
    text-transform: uppercase;
    color: var(--rose);
    margin-bottom: 8px;
}
.adm-welcome-text h1 {
    font-family: 'Cormorant Garamond', serif;
    font-size: 36px;
    font-weight: 700;
    color: var(--ink);
    line-height: 1.1;
    margin-bottom: 6px;
}
.adm-welcome-text p { font-size: 14px; color: var(--muted); }
.adm-welcome-deco { font-size: 72px; opacity: 0.18; line-height: 1; }

/* Section label */
.adm-section-label {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 3px;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 16px;
}

/* Stats row */
.adm-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 40px;
}
@media (max-width: 900px)  { .adm-stats { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 540px)  { .adm-stats { grid-template-columns: 1fr; } }

.adm-stat {
    background: var(--white);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    padding: 28px 24px;
    text-align: center;
    box-shadow: var(--shadow-sm);
    transition: transform 0.25s, box-shadow 0.25s;
}
.adm-stat:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-md);
}
.adm-stat-icon  { font-size: 28px; display: block; margin-bottom: 10px; }
.adm-stat-label { font-size: 11px; font-weight: 600; letter-spacing: 2px; text-transform: uppercase; color: var(--muted); margin-bottom: 8px; }
.adm-stat-value { font-size: 38px; font-weight: 700; color: var(--ink); line-height: 1; font-family: 'Cormorant Garamond', serif; }

/* Cards row */
.adm-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
@media (max-width: 800px) { .adm-row { grid-template-columns: 1fr; } }

.adm-card {
    background: var(--white);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
}
.adm-card-head {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 18px 24px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 2.5px;
    text-transform: uppercase;
    color: var(--muted);
    border-bottom: 1px solid var(--border);
    background: var(--blush);
}
.adm-card-head-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    background: var(--rose);
    flex-shrink: 0;
}
.adm-card-body { padding: 8px 0; }

/* Order rows */
.adm-order-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 24px;
    border-bottom: 1px solid var(--border);
    transition: background 0.15s;
}
.adm-order-row:last-child { border-bottom: none; }
.adm-order-row:hover { background: var(--blush); }
.adm-order-id   { font-size: 13px; font-weight: 700; color: var(--ink); margin-bottom: 2px; }
.adm-order-user { font-size: 11px; color: var(--muted); }
.adm-order-price { font-size: 15px; font-weight: 700; color: var(--ink); margin-bottom: 4px; }

.adm-status {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 99px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}
.adm-status.delivered        { background: #dcfce7; color: #166534; }
.adm-status.pending          { background: #fef9c3; color: #854d0e; }
.adm-status.pending_verification { background: #fef3c7; color: #92400e; }
.adm-status.out-for-delivery { background: #dbeafe; color: #1e40af; }
.adm-status.preparing        { background: #fce7f3; color: #9d174d; }
.adm-status.payment_rejected { background: #fee2e2; color: #991b1b; }
.adm-status.paid             { background: #dcfce7; color: #166534; }
.adm-status.picked-up        { background: #fce7f3; color: #be185d; }
.adm-status.cancelled        { background: #fee2e2; color: #991b1b; }

/* Product rows */
.adm-product-row {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 24px;
    border-bottom: 1px solid var(--border);
    transition: background 0.15s;
}
.adm-product-row:last-child { border-bottom: none; }
.adm-product-row:hover { background: var(--blush); }
.adm-product-thumb {
    width: 44px; height: 44px;
    border-radius: 10px;
    object-fit: cover;
    border: 1px solid var(--border);
    flex-shrink: 0;
}
.adm-product-name  { font-size: 13px; font-weight: 600; color: var(--ink); margin-bottom: 2px; }
.adm-product-price { font-size: 11px; color: var(--muted); }
.adm-sold-badge {
    margin-left: auto;
    background: var(--blush);
    color: var(--mauve);
    font-size: 10px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 99px;
    white-space: nowrap;
}

.adm-empty { padding: 24px; text-align: center; color: var(--muted); font-size: 13px; }

/* ── ANIMATIONS ── */
.adm-stat, .adm-card, .adm-welcome { animation: fadeUp 0.5s ease both; }
.adm-stat:nth-child(1) { animation-delay: 0.05s; }
.adm-stat:nth-child(2) { animation-delay: 0.10s; }
.adm-stat:nth-child(3) { animation-delay: 0.15s; }
.adm-stat:nth-child(4) { animation-delay: 0.20s; }
.adm-card:nth-child(1) { animation-delay: 0.25s; }
.adm-card:nth-child(2) { animation-delay: 0.30s; }

@keyframes fadeUp {
    from { opacity: 0; transform: translateY(18px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>
<?php include __DIR__ . '/pwa_head.php'; ?>
</head>
<body>

<?php $activePage = 'dashboard'; include 'admin_navbar.php'; ?>

<!-- ── PAGE BODY ── -->
<div class="adm-body">

    <!-- Welcome Banner -->
    <div class="adm-welcome">
        <div class="adm-welcome-text">
            <div class="adm-welcome-eyebrow">Welcome back</div>
            <h1><?= htmlspecialchars($adminName) ?></h1>
            <p>Here's how your store is blooming today.</p>
        </div>
        <div class="adm-welcome-deco">🌸</div>
    </div>

    <!-- GCash alert banner — only shows when there are pending verifications -->
    <?php if ($pendingGcash > 0): ?>
    <a href="admin_payments.php" style="text-decoration:none;display:block;margin-bottom:28px;">
        <div style="
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 1.5px solid #fcd34d;
            border-radius: 14px;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 2px 12px rgba(251,191,36,.2);
            transition: box-shadow .2s;
        ">
            <span style="font-size:26px;">💙</span>
            <div>
                <div style="font-size:14px;font-weight:700;color:#92400e;">
                    <?= $pendingGcash ?> GCash payment<?= $pendingGcash > 1 ? 's' : '' ?> awaiting verification
                </div>
                <div style="font-size:12px;color:#a16207;margin-top:2px;">
                    Click here to review and confirm customer payments →
                </div>
            </div>
            <span style="margin-left:auto;background:#f59e0b;color:#fff;font-size:11px;font-weight:800;padding:4px 12px;border-radius:99px;">
                <?= $pendingGcash ?> Pending
            </span>
        </div>
    </a>
    <?php endif; ?>

    <!-- Stats -->
    <p class="adm-section-label">Store Overview</p>
    <div class="adm-stats">
        <div class="adm-stat">
            <span class="adm-stat-icon">📦</span>
            <div class="adm-stat-label">Orders</div>
            <div class="adm-stat-value"><?= $ordersSummary['total_orders'] ?></div>
        </div>
        <div class="adm-stat">
            <span class="adm-stat-icon">🌸</span>
            <div class="adm-stat-label">Products</div>
            <div class="adm-stat-value"><?= $totalProducts ?></div>
        </div>
        <div class="adm-stat">
            <span class="adm-stat-icon">👥</span>
            <div class="adm-stat-label">Customers</div>
            <div class="adm-stat-value"><?= $totalCustomers ?></div>
        </div>
        <div class="adm-stat">
            <span class="adm-stat-icon">💰</span>
            <div class="adm-stat-label">Total Sales</div>
            <div class="adm-stat-value" style="font-size:32px;">₱<?= number_format($ordersSummary['total_sales'], 0) ?></div>
        </div>
    </div>

    <!-- Cards Row -->
    <p class="adm-section-label">Activity</p>
    <div class="adm-row">

        <div class="adm-card">
            <div class="adm-card-head">
                <span class="adm-card-head-dot"></span>
                Recent Orders
            </div>
            <div class="adm-card-body">
                <?php if ($recentOrders->num_rows > 0): ?>
                    <?php while($o = $recentOrders->fetch_assoc()):
                        $rawStatus   = $o['status'] ?? 'Pending';
                        $statusClass = strtolower(str_replace([' ', '_'], '-', $rawStatus));
                    ?>
                    <div class="adm-order-row">
                        <div>
                            <div class="adm-order-id">ORD-<?= str_pad($o['id'], 3, '0', STR_PAD_LEFT) ?></div>
                            <div class="adm-order-user"><?= htmlspecialchars($o['username'] ?: 'Guest') ?></div>
                        </div>
                        <div style="text-align:right;">
                            <div class="adm-order-price">₱<?= number_format($o['total_price'], 2) ?></div>
                            <div class="adm-status <?= $statusClass ?>"><?= htmlspecialchars($rawStatus) ?></div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="adm-empty">No orders yet 🌱</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="adm-card">
            <div class="adm-card-head">
                <span class="adm-card-head-dot"></span>
                Top Products
            </div>
            <div class="adm-card-body">
                <?php if ($topProducts->num_rows > 0): ?>
                    <?php while($p = $topProducts->fetch_assoc()): ?>
                    <div class="adm-product-row">
                        <img class="adm-product-thumb" src="images/<?= htmlspecialchars($p['image'] ?: 'placeholder.png') ?>" alt="">
                        <div>
                            <div class="adm-product-name"><?= htmlspecialchars($p['name']) ?></div>
                            <div class="adm-product-price">₱<?= number_format($p['price'], 2) ?></div>
                        </div>
                        <div class="adm-sold-badge"><?= (int)$p['sold'] ?> sold</div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="adm-empty">No products yet 🌱</p>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>



<?php
// ── ADMIN WELCOME POPUP ──
$showAdminWelcome = !empty($_SESSION['admin_just_logged_in']);
$adminPopupName   = htmlspecialchars($_SESSION['username'] ?? 'Admin');
unset($_SESSION['admin_just_logged_in']);
?>

<?php if ($showAdminWelcome): ?>
<!-- ══ ADMIN WELCOME POPUP ══ -->
<div id="awOverlay" role="dialog" aria-modal="true" aria-label="Admin welcome">

  <canvas id="awCanvas" aria-hidden="true"></canvas>

  <div id="awCard">

    <!-- Sparkle dots -->
    <div id="awSparkles" aria-hidden="true">
      <span class="awSk" style="top:16%;left:10%;animation-delay:0s"></span>
      <span class="awSk" style="top:12%;right:12%;animation-delay:.4s"></span>
      <span class="awSk" style="top:44%;left:4%;animation-delay:.9s"></span>
      <span class="awSk" style="top:42%;right:5%;animation-delay:.2s"></span>
      <span class="awSk" style="bottom:24%;left:8%;animation-delay:.65s"></span>
      <span class="awSk" style="bottom:22%;right:8%;animation-delay:.35s"></span>
    </div>

    <!-- Animated SVG bloom -->
    <div id="awBloomWrap" aria-hidden="true">
      <svg id="awBloomSvg" viewBox="-60 -60 120 120" xmlns="http://www.w3.org/2000/svg" overflow="visible">
        <g id="awOuter">
          <?php for($i=0;$i<8;$i++): ?>
          <ellipse rx="24" ry="9" fill="#ffc9a0" opacity="0.88" transform="rotate(<?= $i*45 ?>)"/>
          <?php endfor; ?>
        </g>
        <g id="awInner">
          <?php for($i=0;$i<6;$i++): ?>
          <ellipse rx="16" ry="6.5" fill="#f08050" opacity="0.92" transform="rotate(<?= $i*60+22 ?>)"/>
          <?php endfor; ?>
        </g>
        <circle r="12"  fill="#fff5ee"/>
        <circle r="7.5" fill="#ffc9a0"/>
        <circle r="3.8" fill="#d05a28"/>
      </svg>
    </div>

    <!-- Thin rule -->
    <div id="awRule"><span></span></div>

    <!-- Badge -->
    <div id="awBadge">✦ ADMIN PORTAL ✦</div>

    <!-- Heading -->
    <h2 id="awHeading">Welcome <em>back,</em><br>Commander</h2>

    <!-- Name -->
    <div id="awName"><?= $adminPopupName ?></div>

    <!-- Divider -->
    <div id="awDivider"><span></span><span class="awDf">✦</span><span></span></div>

    <!-- Subtitle -->
    <p id="awSub">Your dashboard is ready. Here's how your store is blooming today — orders, products, and customers await your attention.</p>

    <!-- CTA -->
    <button id="awBtn" onclick="awClose()">
      <span id="awBtnText">Open Dashboard</span>
      <span id="awBtnArrow">→</span>
    </button>

    <!-- Dismiss hint -->
    <p id="awDismiss">Press <kbd>Esc</kbd> or click outside to dismiss</p>

  </div>
</div>

<style>
#awOverlay {
  position: fixed; inset: 0; z-index: 99999;
  display: flex; align-items: center; justify-content: center;
  background: radial-gradient(ellipse at 40% 35%, rgba(100,35,10,0.78) 0%, rgba(30,10,3,0.93) 100%);
  backdrop-filter: blur(9px); -webkit-backdrop-filter: blur(9px);
  padding: 1rem;
  animation: awFadeIn .5s ease both;
}
@keyframes awFadeIn { from{opacity:0} to{opacity:1} }

#awCanvas {
  position: absolute; inset: 0;
  width: 100%; height: 100%; pointer-events: none;
}

#awCard {
  position: relative; z-index: 2;
  background:
    radial-gradient(ellipse at 18% 0%,   rgba(255,210,170,0.35) 0%, transparent 55%),
    radial-gradient(ellipse at 82% 100%, rgba(255,180,130,0.22) 0%, transparent 55%),
    linear-gradient(160deg, #fffaf5 0%, #fff3ea 45%, #fff8f2 100%);
  border: 1px solid rgba(255,185,140,0.50);
  border-radius: 36px;
  padding: 0 2.8rem 2.4rem;
  max-width: 460px; width: 100%;
  text-align: center;
  box-shadow:
    0 2px 0 rgba(255,255,255,0.95) inset,
    0 -1px 0 rgba(208,90,40,0.15) inset,
    0 48px 110px rgba(120,40,10,0.50),
    0 8px 30px rgba(208,90,40,0.28);
  animation: awCardIn .65s cubic-bezier(0.34,1.4,0.64,1) .1s both;
  overflow: hidden;
}
@keyframes awCardIn {
  from { opacity:0; transform: translateY(42px) scale(0.87); }
  to   { opacity:1; transform: translateY(0)    scale(1); }
}

#awBloomWrap {
  margin: -22px auto 0; width: 110px; height: 110px;
  display: flex; align-items: center; justify-content: center;
  animation: awBloomIn .9s cubic-bezier(0.34,1.56,0.64,1) .35s both;
  filter: drop-shadow(0 6px 22px rgba(208,90,40,0.55));
}
@keyframes awBloomIn {
  from { opacity:0; transform: scale(0.2) rotate(-40deg); }
  to   { opacity:1; transform: scale(1)   rotate(0deg); }
}
#awBloomSvg { width:100%; height:100%; overflow:visible; }
#awOuter { animation: awSpin 11s linear infinite; transform-origin:0 0; }
#awInner { animation: awSpin 7s linear infinite reverse; transform-origin:0 0; }
@keyframes awSpin { to { transform: rotate(360deg); } }

#awSparkles { position:absolute; inset:0; pointer-events:none; }
.awSk {
  position:absolute; width:6px; height:6px; border-radius:50%;
  background: radial-gradient(circle, #ffd0a8 0%, transparent 70%);
  box-shadow: 0 0 8px 4px rgba(240,149,106,0.55);
  animation: awSkPulse 2.5s ease-in-out infinite;
}
@keyframes awSkPulse {
  0%,100% { opacity:.15; transform:scale(0.5); }
  50%     { opacity:1;   transform:scale(1.5); }
}

#awRule { display:flex; align-items:center; justify-content:center; margin:0 auto 1rem; }
#awRule span { display:block; width:64px; height:1px; background:linear-gradient(90deg,transparent,rgba(208,90,40,0.5),transparent); }

#awBadge {
  font-family: 'Jost', sans-serif; font-size:.62rem; font-weight:600;
  letter-spacing:.28em; text-transform:uppercase; color:#d05a28;
  background:rgba(208,90,40,0.10); border:1px solid rgba(208,90,40,0.28);
  border-radius:50px; padding:5px 18px; display:inline-block;
  margin-bottom:1rem; animation:awUp .5s ease .55s both;
}

#awHeading {
  font-family:'Cormorant Garamond',Georgia,serif;
  font-size:clamp(2rem,6vw,2.75rem); font-weight:300; line-height:1.18;
  color:#4a2c1e; margin-bottom:.55rem;
  animation:awUp .5s ease .65s both;
}
#awHeading em { font-style:italic; font-weight:600; color:#d05a28; }

#awName {
  font-family:'Cormorant Garamond',Georgia,serif;
  font-size:clamp(1.1rem,3.5vw,1.38rem); font-weight:400; font-style:italic;
  color:#9a5540; margin-bottom:1rem; opacity:.92;
  animation:awUp .5s ease .72s both;
}

#awDivider {
  display:flex; align-items:center; gap:10px; justify-content:center;
  margin-bottom:1rem; animation:awUp .5s ease .78s both;
}
#awDivider span:not(.awDf) { display:block; flex:1; max-width:55px; height:1px; background:linear-gradient(90deg,transparent,rgba(208,90,40,0.35),transparent); }
.awDf { font-size:.9rem; color:#f08050; line-height:1; }

#awSub {
  font-family:'Jost',sans-serif; font-size:.88rem; font-weight:300; line-height:1.78;
  color:#7a4430; opacity:.82; margin-bottom:1.8rem;
  max-width:330px; margin-left:auto; margin-right:auto;
  animation:awUp .5s ease .84s both;
}

#awBtn {
  position:relative; overflow:hidden;
  display:inline-flex; align-items:center; gap:10px;
  background:linear-gradient(135deg, #f08050 0%, #d05a28 55%, #a83e12 100%);
  color:#fff; border:none; border-radius:60px; padding:1rem 2.6rem;
  font-family:'Jost',sans-serif; font-size:.88rem; font-weight:600;
  letter-spacing:.12em; text-transform:uppercase; cursor:pointer;
  box-shadow:0 10px 36px rgba(180,60,20,0.50), 0 2px 0 rgba(255,255,255,0.18) inset;
  transition:transform .25s cubic-bezier(0.34,1.56,0.64,1), box-shadow .25s;
  animation:awUp .5s ease .9s both;
}
#awBtn::before {
  content:''; position:absolute; inset:0;
  background:linear-gradient(135deg,rgba(255,255,255,0.2) 0%,transparent 60%);
  pointer-events:none;
}
#awBtn::after {
  content:''; position:absolute;
  width:200%; height:200%; top:-50%; left:-110%;
  background:linear-gradient(105deg,transparent 30%,rgba(255,255,255,0.28) 50%,transparent 70%);
  transform:skewX(-20deg); transition:left .5s ease; pointer-events:none;
}
#awBtn:hover::after { left:110%; }
#awBtn:hover { transform:translateY(-3px) scale(1.04); box-shadow:0 20px 50px rgba(160,50,10,0.58),0 2px 0 rgba(255,255,255,0.18) inset; }
#awBtn:active { transform:scale(0.97); }
#awBtnArrow { font-size:1.1rem; transition:transform .25s; }
#awBtn:hover #awBtnArrow { transform:translateX(4px); }

#awDismiss {
  font-family:'Jost',sans-serif; font-size:.67rem;
  color:rgba(154,107,90,0.45); margin-top:1rem; letter-spacing:.06em;
  animation:awUp .4s ease 1.1s both;
}
#awDismiss kbd {
  font-family:inherit; font-size:.62rem;
  border:1px solid rgba(208,90,40,0.3); border-radius:4px;
  padding:1px 5px; background:rgba(208,90,40,0.07);
}

@keyframes awUp {
  from { opacity:0; transform:translateY(14px); }
  to   { opacity:1; transform:translateY(0); }
}

#awOverlay.awOut { animation:awFadeOut .4s ease forwards; }
@keyframes awFadeOut { to { opacity:0; transform:scale(0.97); } }
</style>

<script>
(function(){
  var cv  = document.getElementById('awCanvas');
  var ctx = cv.getContext('2d');
  var W, H; var pts = [];

  function resize(){ W=cv.width=window.innerWidth; H=cv.height=window.innerHeight; }
  resize(); window.addEventListener('resize', resize);

  var COLS = ['#ffc9a0','#f08050','#fde0c8','#fac9a0','#ffb888','#f09060'];

  function Pt(){
    this.x=Math.random()*1600-300; this.y=Math.random()*-200-20;
    this.r=5+Math.random()*10; this.ry=this.r*(0.4+Math.random()*0.35);
    this.rot=Math.random()*Math.PI*2; this.vx=(Math.random()-.5)*1.3;
    this.vy=1.4+Math.random()*2.4; this.vr=(Math.random()-.5)*0.06;
    this.al=0.45+Math.random()*0.45;
    this.col=COLS[Math.floor(Math.random()*COLS.length)];
  }
  Pt.prototype.update=function(){
    this.x+=this.vx+Math.sin(this.y*.012)*.7; this.y+=this.vy; this.rot+=this.vr;
    if(this.y>H+20){this.y=-28;this.x=Math.random()*W;}
  };
  Pt.prototype.draw=function(){
    ctx.save(); ctx.translate(this.x,this.y); ctx.rotate(this.rot);
    ctx.globalAlpha=this.al; ctx.fillStyle=this.col;
    ctx.beginPath(); ctx.ellipse(0,0,this.r,this.ry,0,0,Math.PI*2); ctx.fill();
    ctx.restore();
  };

  for(var i=0;i<55;i++) pts.push(new Pt());

  var raf;
  function loop(){ ctx.clearRect(0,0,W,H); for(var i=0;i<pts.length;i++){pts[i].update();pts[i].draw();} raf=requestAnimationFrame(loop); }
  loop();

  window.awClose = function(){
    cancelAnimationFrame(raf);
    var ov=document.getElementById('awOverlay');
    ov.classList.add('awOut');
    ov.addEventListener('animationend',function(){ov.remove();},{once:true});
  };
  document.getElementById('awOverlay').addEventListener('click',function(e){ if(e.target===this) awClose(); });
  document.addEventListener('keydown',function(e){ if(e.key==='Escape') awClose(); });
})();
</script>
<?php endif; ?>

</body>
</html>