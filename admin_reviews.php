<?php
session_start();
include "config.php";

if (!isset($_SESSION['user_id'])) die("Login first");
$uid  = $_SESSION['user_id'];
$user = $conn->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();
if ($user['role'] != 'admin') die("Access denied");

// ── Load reviews ─────────────────────────────────────────────────────────────
$filterStar = isset($_GET['star']) ? (int)$_GET['star'] : 0;
$search     = trim($_GET['q'] ?? '');

$where = "WHERE 1=1";
if ($filterStar >= 1 && $filterStar <= 5) $where .= " AND r.rating = $filterStar";
if ($search !== '') {
    $s = $conn->real_escape_string($search);
    $where .= " AND (u.full_name LIKE '%$s%' OR u.username LIKE '%$s%' OR r.message LIKE '%$s%')";
}

$reviews = $conn->query("
    SELECT r.id, r.order_id, r.rating, r.message, r.created_at,
           u.username, u.full_name, u.avatar,
           o.total_price, o.delivery_mode,
           GROUP_CONCAT(oi.product_name ORDER BY oi.id SEPARATOR ', ') AS products
    FROM reviews r
    LEFT JOIN users u  ON u.id  = r.user_id
    LEFT JOIN orders o ON o.id  = r.order_id
    LEFT JOIN order_items oi ON oi.order_id = r.order_id
    $where
    GROUP BY r.id
    ORDER BY r.created_at DESC
");
$totalReviews = $reviews ? $reviews->num_rows : 0;

// ── Summary stats ─────────────────────────────────────────────────────────────
$stats = $conn->query("SELECT COUNT(*) AS total, AVG(rating) AS avg_rating, 
    SUM(rating=5) AS five, SUM(rating=4) AS four, SUM(rating=3) AS three,
    SUM(rating=2) AS two, SUM(rating=1) AS one FROM reviews")->fetch_assoc();
$avgRating = $stats['total'] > 0 ? round((float)$stats['avg_rating'], 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reviews — Precious Satin Bouquet Admin</title>
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
  --pink:      #e879a8;
  --pink-light:#fce8f3;
  --pink-border:#f4b8d8;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
html, body {
  min-height: 100%; background: var(--cream);
  font-family: 'Jost', sans-serif; color: var(--ink);
}
body::before {
  content: ''; position: fixed; inset: 0; pointer-events: none; z-index: 0;
  background: radial-gradient(ellipse 60% 40% at 90% 10%, rgba(255,200,160,0.25) 0%, transparent 70%),
              radial-gradient(ellipse 50% 60% at 5% 80%, rgba(255,215,185,0.35) 0%, transparent 65%);
}

/* NAV */
.adm-nav {
  position: fixed; top: 0; left: 0; right: 0; z-index: 300;
  height: var(--nav-h);
  display: flex; align-items: center; padding: 0 28px;
  background: rgba(255,248,242,0.95);
  backdrop-filter: blur(20px);
  border-bottom: 1px solid var(--border);
}
.adm-nav::after {
  content: ''; position: absolute; bottom: -1px; left: 50%;
  transform: translateX(-50%); width: 100px; height: 2px;
  background: linear-gradient(90deg, transparent, var(--rose), transparent); border-radius: 2px;
}
.adm-nav-left  { flex: 1; }
.adm-nav-center { flex: 1; display: flex; justify-content: center; }
.adm-nav-right { flex: 1; display: flex; align-items: center; justify-content: flex-end; gap: 8px; }
.adm-nav-brand   { font-family: 'Great Vibes', cursive; font-size: 26px; color: var(--deep-rose); text-decoration: none; letter-spacing: 0.5px; line-height: 1; }
.adm-nav-tagline { font-size: 9px; font-weight: 500; letter-spacing: 3px; text-transform: uppercase; color: var(--muted); display: block; text-align: center; margin-top: -2px; }
.adm-icon { width: 36px; height: 36px; border-radius: 50%; background: transparent; border: 1.5px solid var(--border); display: flex; align-items: center; justify-content: center; color: var(--muted); text-decoration: none; cursor: pointer; transition: all 0.2s; }
.adm-icon:hover { background: var(--blush); color: var(--deep-rose); }
.adm-settings-wrap { position: relative; }
.adm-settings-dropdown { display: none; position: absolute; top: calc(100% + 12px); right: 0; width: 270px; background: var(--white); border: 1px solid var(--border); border-radius: 20px; box-shadow: var(--shadow-lg); z-index: 400; overflow: hidden; animation: dropIn 0.22s cubic-bezier(.34,1.56,.64,1); }
.adm-settings-dropdown.open { display: block; }
@keyframes dropIn { from { opacity: 0; transform: translateY(-10px) scale(0.97); } to { opacity: 1; transform: translateY(0) scale(1); } }
.adm-settings-head { background: var(--blush); padding: 12px 20px; font-size: 11px; font-weight: 700; color: var(--deep-rose); letter-spacing: 2.5px; text-transform: uppercase; border-bottom: 1px solid var(--border); }
.adm-settings-item { display: flex; align-items: center; gap: 14px; padding: 12px 20px; text-decoration: none; color: var(--ink); font-size: 14px; font-weight: 500; transition: background 0.15s; border-bottom: 1px solid rgba(247,197,216,0.25); }
.adm-settings-item:last-child { border-bottom: none; }
.adm-settings-item:hover { background: var(--blush); color: var(--deep-rose); }
.adm-settings-item-icon { width: 34px; height: 34px; border-radius: 10px; background: linear-gradient(135deg, var(--petal), var(--rose)); display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0; }
.adm-settings-item-sub { font-size: 11px; color: var(--muted); font-weight: 400; margin-top: 1px; }

/* Page wrapper */
.rv-page {
  position: relative; z-index: 1;
  padding: calc(var(--nav-h) + 28px) 28px 40px;
  max-width: 1300px; margin: 0 auto;
}

/* Header */
.rv-header {
  display: flex; align-items: baseline; justify-content: space-between;
  flex-wrap: wrap; gap: 10px; margin-bottom: 24px;
}
.rv-title {
  font-family: 'Cormorant Garamond', serif;
  font-size: 46px; font-weight: 700; color: var(--deep-rose); line-height: 1;
}
.rv-count { font-size: 14px; color: var(--muted); font-weight: 500; }

/* ── TWO-COLUMN LANDSCAPE GRID ── */
.rv-landscape {
  display: grid;
  grid-template-columns: 300px 1fr;
  gap: 24px;
  align-items: start;
}
@media (max-width: 860px) {
  .rv-landscape { grid-template-columns: 1fr; }
}

/* LEFT PANEL — sticky sidebar */
.rv-sidebar {
  position: sticky;
  top: calc(var(--nav-h) + 16px);
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* Stats block */
.rv-stats {
  background: var(--white); border-radius: 18px; padding: 20px 24px;
  border: 1px solid var(--border);
  box-shadow: var(--shadow-sm);
  margin-bottom: 20px;
}
.rv-avg-block {
  text-align: center; padding-bottom: 16px;
  border-bottom: 1px solid var(--border);
  margin-bottom: 14px;
}
.rv-avg-number {
  font-family: 'Cormorant Garamond', serif;
  font-size: 52px; font-weight: 700; color: var(--ink); line-height: 1;
}
.rv-avg-stars { color: var(--pink); font-size: 1.2rem; margin: 4px 0; }
.rv-avg-sub   { font-size: 12px; color: var(--muted); }
.rv-bars { display: flex; flex-direction: column; gap: 7px; }
.rv-bar-row   { display: flex; align-items: center; gap: 10px; }
.rv-bar-label { font-size: 13px; color: var(--muted); min-width: 28px; text-align: right; }
.rv-bar-track {
  flex: 1; height: 10px; background: #f5ede8; border-radius: 10px; overflow: hidden;
}
.rv-bar-fill {
  height: 100%; border-radius: 10px;
  background: linear-gradient(90deg, #f9c49a, var(--pink));
  transition: width 0.6s ease;
}
.rv-bar-count { font-size: 12px; color: var(--muted); min-width: 24px; }

/* Filters block */
.rv-filters-panel {
  background: var(--white); border-radius: 18px; padding: 18px 20px;
  border: 1px solid var(--border); box-shadow: var(--shadow-sm);
  display: flex; flex-direction: column; gap: 12px;
}
.rv-filters-label {
  font-size: 11px; font-weight: 700; letter-spacing: 2px;
  text-transform: uppercase; color: var(--muted);
}

/* Filters */
.rv-filters {
  display: flex; flex-direction: column; gap: 14px; margin-bottom: 24px;
}
.rv-search {
  width: 100%;
  padding: 9px 16px 9px 36px; border: 1.5px solid var(--border);
  border-radius: 50px; background: var(--white);
  font-family: 'Jost', sans-serif; font-size: 14px; color: var(--ink);
  outline: none; transition: border-color 0.2s;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 20 20' fill='none' stroke='%23c07a8a' stroke-width='2'%3E%3Ccircle cx='9' cy='9' r='6'/%3E%3Cpath d='M15 15l-3.5-3.5'/%3E%3C/svg%3E");
  background-repeat: no-repeat; background-position: 13px center;
}
.rv-search:focus { border-color: var(--rose); }
.rv-star-filter {
  display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin: 10px 0 4px;
}
.rv-star-btn {
  padding: 10px 22px; border-radius: 50px;
  border: 1.5px solid var(--border); background: var(--white);
  font-family: 'Jost', sans-serif; font-size: 16px; font-weight: 600;
  color: var(--muted); cursor: pointer; transition: all 0.18s; text-decoration: none;
  letter-spacing: 2px;
}
.rv-star-btn:hover, .rv-star-btn.active {
  background: var(--pink-light); border-color: var(--pink);
  color: var(--pink);
}
.rv-star-btn.active { box-shadow: 0 2px 10px rgba(232,121,168,0.25); }

/* RIGHT: Cards grid */
.rv-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 16px;
}

/* Review card */
.rv-card {
  background: var(--white); border-radius: 18px;
  border: 1px solid var(--border); padding: 18px 20px;
  box-shadow: var(--shadow-sm);
  display: flex; flex-direction: column; gap: 10px;
  transition: box-shadow 0.18s, transform 0.18s;
  animation: fadeUp 0.3s ease both;
}
.rv-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
@keyframes fadeUp { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }

.rv-card-top {
  display: flex; align-items: center; gap: 12px; justify-content: space-between;
}
.rv-avatar {
  width: 44px; height: 44px; border-radius: 50%;
  background: linear-gradient(135deg, var(--petal), var(--pink));
  display: flex; align-items: center; justify-content: center;
  font-size: 1.2rem; color: #fff; font-weight: 700;
  flex-shrink: 0; overflow: hidden;
}
.rv-avatar img { width: 100%; height: 100%; object-fit: cover; }
.rv-user-info { flex: 1; min-width: 0; }
.rv-user-name {
  font-size: 15px; font-weight: 700; color: var(--ink);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.rv-user-sub  { font-size: 12px; color: var(--muted); }

.rv-stars-display { display: flex; gap: 3px; flex-shrink: 0; margin-left: 8px; }
.rv-star-filled { color: var(--pink); font-size: 1.15rem; }
.rv-star-empty  { color: #e8d0d8; font-size: 1.15rem; }

.rv-order-chip {
  font-size: 11px; font-weight: 700; padding: 3px 10px;
  border-radius: 20px; background: var(--blush);
  color: var(--deep-rose); border: 1px solid var(--border);
  white-space: nowrap;
}
.rv-products {
  font-size: 12px; color: var(--muted);
  border-top: 1px solid rgba(220,120,70,0.12); padding-top: 8px;
  line-height: 1.5;
}
.rv-message {
  font-size: 14px; color: var(--ink); line-height: 1.65;
  background: var(--pink-light); border-radius: 12px;
  padding: 10px 14px; border-left: 3px solid var(--pink);
  font-style: italic;
}
.rv-no-message {
  font-size: 13px; color: #c8a8b8; font-style: italic;
}
.rv-date {
  font-size: 11px; color: var(--muted); text-align: right; margin-top: 2px;
}

/* Empty */
.rv-empty {
  grid-column: 1 / -1; text-align: center;
  padding: 60px 20px; color: var(--muted);
}
.rv-empty-icon { font-size: 3rem; opacity: 0.4; margin-bottom: 12px; }
.rv-empty-text { font-family: 'Cormorant Garamond', serif; font-size: 1.6rem; color: var(--ink); margin-bottom: 6px; }
.rv-empty-sub  { font-size: 14px; }
</style>
<?php include __DIR__ . '/pwa_head.php'; ?>
</head>
<body>

<!-- NAV -->
<?php $activePage = 'reviews'; include 'admin_navbar.php'; ?>

<div class="rv-page">

  <!-- Header -->
  <div class="rv-header">
    <div>
      <div class="rv-title">⭐ Reviews</div>
    </div>
    <div class="rv-count"><?= $totalReviews ?> review<?= $totalReviews != 1 ? 's' : '' ?> total</div>
  </div>

  <!-- Stats -->
  <?php if ($stats['total'] > 0): ?>
  <div class="rv-stats">
    <div class="rv-avg-block">
      <div class="rv-avg-number"><?= number_format($avgRating, 1) ?></div>
      <div class="rv-avg-stars">
        <?php for ($i = 1; $i <= 5; $i++): ?>
          <?= $i <= round($avgRating) ? '★' : '☆' ?>
        <?php endfor; ?>
      </div>
      <div class="rv-avg-sub">out of 5</div>
    </div>
    <div class="rv-bars">
      <?php foreach ([5,4,3,2,1] as $n):
        $count = (int)($stats[$n == 5 ? 'five' : ($n == 4 ? 'four' : ($n == 3 ? 'three' : ($n == 2 ? 'two' : 'one')))] ?? 0);
        $pct   = $stats['total'] > 0 ? round(($count / $stats['total']) * 100) : 0;
      ?>
      <div class="rv-bar-row">
        <div class="rv-bar-label"><?= $n ?>★</div>
        <div class="rv-bar-track"><div class="rv-bar-fill" style="width:<?= $pct ?>%"></div></div>
        <div class="rv-bar-count"><?= $count ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Filters -->
  <form method="GET" action="admin_reviews.php" class="rv-filters">
    <input type="text" name="q" class="rv-search"
      placeholder="Search by name or message…"
      value="<?= htmlspecialchars($search) ?>">
    <div class="rv-star-filter">
      <a href="admin_reviews.php<?= $search ? '?q='.urlencode($search) : '' ?>"
         class="rv-star-btn <?= $filterStar == 0 ? 'active' : '' ?>">All</a>
      <?php for ($n = 5; $n >= 1; $n--): ?>
      <a href="admin_reviews.php?star=<?= $n ?><?= $search ? '&q='.urlencode($search) : '' ?>"
         class="rv-star-btn <?= $filterStar == $n ? 'active' : '' ?>">
        <?= str_repeat('★', $n) . str_repeat('☆', 5 - $n) ?>
      </a>
      <?php endfor; ?>
    </div>
  </form>

  <!-- Cards -->
  <div class="rv-grid">
  <?php if ($totalReviews > 0):
    while ($rv = $reviews->fetch_assoc()):
      $name    = !empty($rv['full_name']) ? $rv['full_name'] : $rv['username'];
      $initial = strtoupper(substr($name, 0, 1));
      $ordNum  = 'ORD-' . str_pad($rv['order_id'], 3, '0', STR_PAD_LEFT);
  ?>
    <div class="rv-card">
      <!-- Top: avatar + user + stars -->
      <div class="rv-card-top">
        <div class="rv-avatar">
          <?php if (!empty($rv['avatar'])): ?>
            <img src="<?= htmlspecialchars($rv['avatar']) ?>" alt="">
          <?php else: ?>
            <?= $initial ?>
          <?php endif; ?>
        </div>
        <div class="rv-user-info">
          <div class="rv-user-name"><?= htmlspecialchars($name) ?></div>
          <div class="rv-user-sub">@<?= htmlspecialchars($rv['username']) ?></div>
        </div>
        <div class="rv-stars-display">
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <span class="<?= $i <= $rv['rating'] ? 'rv-star-filled' : 'rv-star-empty' ?>">★</span>
          <?php endfor; ?>
        </div>
      </div>

      <!-- Order chip + products -->
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <span class="rv-order-chip">
          <a href="admin_orders.php?order_id=<?= $rv['order_id'] ?>" style="color:inherit;text-decoration:none;">
            <?= $ordNum ?> ↗
          </a>
        </span>
        <?php if (!empty($rv['delivery_mode'])): ?>
          <span style="font-size:11px;color:var(--muted);"><?= $rv['delivery_mode'] === 'pickup' ? '🏪 Pickup' : '🚗 Delivery' ?></span>
        <?php endif; ?>
      </div>

      <?php if (!empty($rv['products'])): ?>
      <div class="rv-products">🌸 <?= htmlspecialchars($rv['products']) ?></div>
      <?php endif; ?>

      <!-- Review message -->
      <?php if (!empty(trim($rv['message']))): ?>
        <div class="rv-message">"<?= htmlspecialchars($rv['message']) ?>"</div>
      <?php else: ?>
        <div class="rv-no-message">No written review left.</div>
      <?php endif; ?>

      <div class="rv-date"><?= date('M j, Y · g:i A', strtotime($rv['created_at'])) ?></div>
    </div>
  <?php endwhile;
  else: ?>
    <div class="rv-empty">
      <div class="rv-empty-icon">⭐</div>
      <div class="rv-empty-text">No reviews yet</div>
      <div class="rv-empty-sub">Customer reviews will appear here after they confirm delivery.</div>
    </div>
  <?php endif; ?>
  </div>

</div><!-- /rv-page -->

<script>
(function(){
   });
})();
</script>

</body>
</html>