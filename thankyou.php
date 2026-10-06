<?php 
session_start();
include "config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$order_id = intval($_GET['order_id'] ?? 0);
$uid = $_SESSION['user_id'];

if ($order_id <= 0) {
    header("Location: checkout.php");
    exit();
}

$order = $conn->query("SELECT * FROM orders WHERE id=$order_id AND user_id=$uid")->fetch_assoc();
if (!$order) { header("Location: orders.php"); exit(); }

$user  = $conn->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();
$items = $conn->query("SELECT oi.* FROM order_items oi WHERE oi.order_id = $order_id");

// First product image
$firstImage = 'logo/SB.png';
$imgCheck = $conn->query("
    SELECT p.image FROM order_items oi
    JOIN products p ON p.name = oi.product_name
    WHERE oi.order_id = $order_id
    ORDER BY oi.id ASC LIMIT 1
");
if ($imgCheck && $r = $imgCheck->fetch_assoc()) $firstImage = $r['image'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Thank You — Precious Satin Bouquet</title>
<link rel="icon" type="image/png" href="images/logo/SB.png">
<link rel="stylesheet" href="style.css">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  html, body {
    height: 100%;
    overflow: hidden;
    background: #fce8ef;
    font-family: 'Inter', sans-serif;
    color: #2d2d2d;
  }

  /* ── Shimmer bar at very top ── */
  .ty-shimmer {
    height: 4px;
    background: linear-gradient(90deg, #f9a8d4, #f472b6, #ec4899, #f9a8d4);
    background-size: 200% 100%;
    animation: shimmer 2.5s linear infinite;
    position: fixed; top: 0; left: 0; right: 0; z-index: 999;
  }
  @keyframes shimmer { 0%{background-position:0 0} 100%{background-position:200% 0} }

  /* ── Two-column landscape shell ── */
  .ty-shell {
    display: grid;
    grid-template-columns: 360px 1fr;
    height: calc(100vh - 4px);
    margin-top: 4px;
    overflow: hidden;
  }

  /* ══ LEFT panel ══ */
  .ty-left {
    background: linear-gradient(160deg, #fff0f5 0%, #fce8ef 100%);
    border-right: 1px solid rgba(249,168,212,0.3);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 32px 28px;
    gap: 18px;
    position: relative;
    overflow: hidden;
  }

  /* decorative petals */
  .ty-left::before {
    content: '🌸';
    position: absolute; top: 16px; left: 20px;
    font-size: 48px; opacity: 0.12; pointer-events: none;
  }
  .ty-left::after {
    content: '💐';
    position: absolute; bottom: 20px; right: 18px;
    font-size: 56px; opacity: 0.10; pointer-events: none;
  }

  .ty-bloom {
    width: 80px; height: 80px;
    background: linear-gradient(135deg, #fce7f3, #fbcfe8);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 36px;
    box-shadow: 0 0 0 10px rgba(252,231,243,.5), 0 0 0 20px rgba(252,231,243,.2);
    animation: pulse 2.5s ease-in-out infinite;
    flex-shrink: 0;
  }
  @keyframes pulse {
    0%,100% { box-shadow: 0 0 0 10px rgba(252,231,243,.5), 0 0 0 20px rgba(252,231,243,.2); }
    50%      { box-shadow: 0 0 0 14px rgba(252,231,243,.4), 0 0 0 26px rgba(252,231,243,.1); }
  }

  .ty-left h1 {
    font-family: 'Playfair Display', serif;
    font-size: 36px;
    color: #1a1a1a;
    text-align: center;
    line-height: 1.2;
  }

  .ty-left .sub {
    font-size: 13px;
    color: #888;
    text-align: center;
    line-height: 1.7;
    max-width: 280px;
  }

  .ty-order-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #fdf2f8;
    border: 1px solid #f9a8d4;
    border-radius: 99px;
    padding: 6px 18px;
    font-size: 13px;
    font-weight: 600;
    color: #be185d;
  }

  /* Meta chips */
  .ty-meta-chips {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    width: 100%;
  }

  .ty-chip {
    background: #fff;
    border: 1px solid rgba(249,168,212,0.35);
    border-radius: 12px;
    padding: 10px 12px;
  }
  .ty-chip .cl { font-size: 9px; font-weight: 700; color: #be185d; text-transform: uppercase; letter-spacing: .1em; margin-bottom: 3px; }
  .ty-chip .cv { font-size: 12px; font-weight: 600; color: #333; }

  /* Action buttons */
  .ty-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    width: 100%;
  }
  .ty-btn {
    display: flex; align-items: center; justify-content: center;
    gap: 6px; padding: 12px 10px;
    border-radius: 12px; font-size: 13px; font-weight: 600;
    text-decoration: none; border: none; cursor: pointer;
    transition: all .18s;
  }
  .ty-btn-primary {
    background: linear-gradient(135deg, #f472b6, #ec4899);
    color: #fff;
    box-shadow: 0 4px 14px rgba(236,72,153,.3);
  }
  .ty-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(236,72,153,.4); }
  .ty-btn-outline {
    background: #fff; color: #be185d;
    border: 1.5px solid #f9a8d4;
  }
  .ty-btn-outline:hover { background: #fdf2f8; }

  /* ══ RIGHT panel ══ */
  .ty-right {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    padding: 28px 32px;
    gap: 16px;
    background: #fdf8f5;
  }

  .ty-right-title {
    font-size: 10px; font-weight: 700;
    letter-spacing: .12em; text-transform: uppercase;
    color: #be185d;
  }

  /* FB note */
  .fb-note {
    background: linear-gradient(135deg, #fdf2f8, #fce7f3);
    border: 1px solid #f9a8d4;
    border-radius: 12px;
    padding: 12px 16px;
    font-size: 12px; color: #6b2163; line-height: 1.6;
    display: flex; gap: 10px; align-items: flex-start;
    flex-shrink: 0;
  }
  .fb-note .fb-icon { font-size: 18px; flex-shrink: 0; }
  .fb-note a { color: #be185d; font-weight: 700; }

  /* Items container — scrollable if many items */
  .ty-items-wrap {
    flex: 1;
    overflow-y: auto;
    background: #fff;
    border: 1px solid #f0e8e4;
    border-radius: 16px;
    padding: 16px 20px;
    min-height: 0;
  }
  .ty-items-wrap::-webkit-scrollbar { width: 4px; }
  .ty-items-wrap::-webkit-scrollbar-thumb { background: #f9a8d4; border-radius: 4px; }

  .item-row {
    display: flex; gap: 14px; align-items: flex-start;
    padding: 12px 0;
    border-bottom: 1px solid #f5ede9;
  }
  .item-row:last-child { border-bottom: none; padding-bottom: 0; }
  .item-img {
    width: 54px; height: 54px;
    border-radius: 10px; object-fit: cover;
    border: 1px solid #f0e8e4; flex-shrink: 0;
  }
  .item-body { flex: 1; min-width: 0; }
  .item-name { font-weight: 600; font-size: 14px; color: #1a1a1a; margin-bottom: 3px; }
  .item-meta { font-size: 11px; color: #aaa; }
  .item-price { text-align: right; flex-shrink: 0; }
  .item-sub  { font-size: 14px; font-weight: 700; color: #1a1a1a; }
  .item-unit { font-size: 11px; color: #bbb; margin-top: 2px; }

  /* Total row */
  .total-row {
    display: flex; justify-content: space-between; align-items: center;
    padding-top: 14px; margin-top: 10px;
    border-top: 2px solid #fce7f3;
    flex-shrink: 0;
  }
  .total-row .tl { font-size: 12px; font-weight: 600; color: #888; text-transform: uppercase; letter-spacing: .08em; }
  .total-row .tv { font-size: 22px; font-weight: 700; color: #be185d; }

  /* What's next */
  .next-steps { list-style: none; flex-shrink: 0; }
  .next-steps li {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 7px 0; font-size: 12px; color: #555;
    border-bottom: 1px solid #f5ede9; line-height: 1.5;
  }
  .next-steps li:last-child { border-bottom: none; }
  .next-steps .ns-icon {
    width: 24px; height: 24px;
    background: #fce7f3; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; flex-shrink: 0;
  }
  .next-steps a { color: #be185d; font-weight: 600; text-decoration: none; }
  .next-steps a:hover { text-decoration: underline; }

  .ty-section-card {
    background: #fff;
    border: 1px solid #f0e8e4;
    border-radius: 14px;
    padding: 14px 18px;
    flex-shrink: 0;
  }
</style>
<?php include __DIR__ . '/pwa_head.php'; ?>
</head>
<body>

<div class="ty-shimmer"></div>

<?php include "header.php"; ?>

<div class="ty-shell">

  <!-- ══ LEFT: hero + meta + actions ══ -->
  <div class="ty-left">

    <div class="ty-bloom">🌸</div>

    <h1>Thank You!</h1>
    <p class="sub">Your order was placed successfully. We can't wait to prepare your bouquet! 💐</p>

    <div class="ty-order-badge">✨ Order #<?= str_pad($order_id, 5, '0', STR_PAD_LEFT) ?></div>

    <!-- Meta chips -->
    <div class="ty-meta-chips">
      <div class="ty-chip">
        <div class="cl">Payment</div>
        <div class="cv"><?= htmlspecialchars(ucfirst($order['payment_method'] ?? '—')) ?></div>
      </div>
      <div class="ty-chip">
        <div class="cl">Delivery</div>
        <div class="cv">
          <?php
            $dm = $order['delivery_mode'] ?? '';
            if ($dm === 'delivery')   echo '🏠 Home';
            elseif ($dm === 'pickup') echo '🏪 Pickup';
            else echo '—';
          ?>
        </div>
      </div>
      <div class="ty-chip">
        <div class="cl">Date</div>
        <div class="cv"><?= date('M j, Y', strtotime($order['created_at'] ?? 'now')) ?></div>
      </div>
      <div class="ty-chip">
        <div class="cl">Status</div>
        <div class="cv" style="color:#d97706;">⏳ <?= htmlspecialchars(ucfirst($order['status'] ?? 'Pending')) ?></div>
      </div>
    </div>

    <!-- Action buttons -->
    <div class="ty-actions">
      <a href="orders.php" class="ty-btn ty-btn-outline">📝 My Orders</a>
      <a href="index.php"  class="ty-btn ty-btn-primary">🛒 Shop More</a>
    </div>

  </div>

  <!-- ══ RIGHT: items + next steps ══ -->
  <div class="ty-right">

    <!-- FB note -->
    <div class="fb-note">
      <span class="fb-icon">💬</span>
      <span>For updates, we'll reach out via your Facebook account. You can also message us on our <a href="https://www.facebook.com/share/1E9LxeeMYQ/" target="_blank">Facebook page "Precious Satin Bouquet"</a>.</span>
    </div>

    <!-- Items ordered -->
    <div class="ty-right-title">🌺 Items Ordered</div>
    <div class="ty-items-wrap">
      <?php if ($items && $items->num_rows > 0):
        $items->data_seek(0);
        while ($item = $items->fetch_assoc()):
          $prodResult = $conn->query("SELECT p.image, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.name = '" . $conn->real_escape_string($item['product_name']) . "' LIMIT 1");
          $prodRow    = $prodResult ? $prodResult->fetch_assoc() : null;
          $itemImage  = $prodRow['image']         ?? 'logo/SB.png';
          $itemCat    = $prodRow['category_name'] ?? '';
      ?>
      <div class="item-row">
        <img src="images/<?= htmlspecialchars($itemImage) ?>" class="item-img" alt="<?= htmlspecialchars($item['product_name']) ?>">
        <div class="item-body">
          <div class="item-name"><?= htmlspecialchars($item['product_name']) ?></div>
          <div class="item-meta">Qty: <strong><?= $item['quantity'] ?></strong><?= $itemCat ? ' &nbsp;·&nbsp; ' . htmlspecialchars($itemCat) : '' ?></div>
        </div>
        <div class="item-price">
          <div class="item-sub">₱<?= number_format($item['price'] * $item['quantity'], 2) ?></div>
          <div class="item-unit">₱<?= number_format($item['price'], 2) ?> each</div>
        </div>
      </div>
      <?php endwhile; endif; ?>

      <div class="total-row">
        <span class="tl">Order Total</span>
        <span class="tv">₱<?= number_format($order['total_price'], 2) ?></span>
      </div>
    </div>

    <!-- What's next -->
    <div class="ty-section-card">
      <div class="ty-right-title" style="margin-bottom:10px;">📌 What's Next?</div>
      <ul class="next-steps">
        <li><span class="ns-icon">💐</span> We'll prepare your beautiful bouquet with care.</li>
        <li><span class="ns-icon">📨</span> You'll receive a confirmation via our Facebook page.</li>
        <li><span class="ns-icon">📦</span> Track your order in <a href="orders.php">My Orders</a>.</li>
        <li><span class="ns-icon">❓</span> Questions? Message us on <a href="https://www.facebook.com/share/1E9LxeeMYQ/" target="_blank">Facebook</a>.</li>
      </ul>
    </div>

  </div><!-- /ty-right -->

</div><!-- /ty-shell -->

<?php include "footer.php"; ?>
</body>
</html>