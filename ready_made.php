<?php
ob_start();
include "config.php";

// Sanitize input
$id = intval($_GET['id']);
if ($id <= 0) {
    die("Invalid product.");
}

// Fetch product with category
$stmt = $conn->prepare("
    SELECT p.*, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    die("Product not found.");
}

// Only allow readymade products on this page
if (strtolower($product['category_name']) !== 'readymade') {
    header("Location: customize.php?id=$id");
    exit;
}

$message = "";

// Handle Add to Cart
if (isset($_POST['add_to_cart'])) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }

    $uid     = intval($_SESSION['user_id']);
    $addQty  = max(1, intval($_POST['qty'] ?? 1));

    // Re-fetch fresh stock
    $stockStmt = $conn->prepare("SELECT stock FROM products WHERE id = ?");
    $stockStmt->bind_param("i", $id);
    $stockStmt->execute();
    $freshStock = intval($stockStmt->get_result()->fetch_assoc()['stock']);

    if ($freshStock <= 0) {
        $message = "outofstock";
    } else {
        $checkStmt = $conn->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $checkStmt->bind_param("ii", $uid, $id);
        $checkStmt->execute();
        $cartRow    = $checkStmt->get_result()->fetch_assoc();
        $currentQty = $cartRow ? $cartRow['quantity'] : 0;

        if ($currentQty + $addQty > $freshStock) {
            $message = "maxstock";
        } elseif ($cartRow) {
            $upStmt = $conn->prepare("UPDATE cart SET quantity = quantity + ? WHERE user_id = ? AND product_id = ?");
            $upStmt->bind_param("iii", $addQty, $uid, $id);
            $upStmt->execute();
            header("Location: cart.php");
            exit;
        } else {
            $insStmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity, wrapper, ribbon, addons) VALUES (?, ?, ?, 'none', 'none', '')");
            $insStmt->bind_param("iii", $uid, $id, $addQty);
            $insStmt->execute();
            header("Location: cart.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($product['name']) ?> – Precious Satin Bouquet</title>
<link rel="icon" type="image/png" href="images/logo/SB.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400;1,600&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --petal:      #f9ede8;
  --petal-mid:  #f0d5cb;
  --blush:      #fdf7f5;
  --rose:       #b85c4a;
  --rose-warm:  #d4735f;
  --rose-deep:  #8c3d2d;
  --rose-pale:  #f5c4b8;
  --bark:       #231410;
  --bark-mid:   #4a2218;
  --sand:       #e8ddd6;
  --cream:      #fdfaf8;
  --white:      #ffffff;
  --text-main:  #231410;
  --text-sub:   #6b3d30;
  --text-muted: #a07060;
  --serif:      'Cormorant Garamond', Georgia, serif;
  --sans:       'Jost', system-ui, sans-serif;
  --radius:     14px;
  --radius-lg:  24px;
}

html, body {
  height: 100%;
  overflow: hidden;
  background: var(--blush);
  color: var(--text-main);
  font-family: var(--sans);
  font-size: 15px;
  font-weight: 400;
  line-height: 1.5;
  -webkit-font-smoothing: antialiased;
}

body::before {
  content: '';
  position: fixed; inset: 0;
  background:
    radial-gradient(ellipse 70% 50% at 5% 0%, rgba(212,115,95,0.09) 0%, transparent 60%),
    radial-gradient(ellipse 60% 60% at 95% 100%, rgba(184,92,74,0.07) 0%, transparent 55%);
  pointer-events: none; z-index: 0;
}

/* ── Breadcrumb — sits below site header ── */
.rm-breadcrumb {
  display: flex;
  align-items: center;
  gap: 12px;
  font-family: var(--serif);
  font-size: 18px;
  font-weight: 600;
  letter-spacing: 0.02em;
  color: var(--text-muted);
  padding: 10px 32px;
  background: rgba(253,250,248,0.97);
  backdrop-filter: blur(10px);
  border-bottom: 1px solid var(--sand);
  position: relative; z-index: 2;
  flex-shrink: 0;
}

.rm-breadcrumb a { color: var(--text-muted); text-decoration: none; transition: color 0.2s; }
.rm-breadcrumb a:hover { color: var(--rose); }
.rm-breadcrumb .sep { color: #e8b4c0; }
.rm-breadcrumb .current { color: var(--rose-warm); font-weight: 600; }

/* ── Main shell: fills viewport below header+breadcrumb ── */
.rm-shell {
  position: relative; z-index: 1;
  display: grid;
  grid-template-columns: 480px 1fr;
  height: calc(var(--shell-height, 100vh) - var(--header-h, 60px) - var(--breadcrumb-h, 45px));
  overflow: hidden;
}

/* ── Left: image panel ── */
.rm-image-wrap {
  position: relative;
  overflow: hidden;
  background: linear-gradient(140deg, var(--petal) 0%, var(--petal-mid) 100%);
  display: flex;
  align-items: center;
  justify-content: center;
}

.rm-image-wrap img {
  max-width: 100%;
  max-height: 100%;
  width: auto;
  height: auto;
  object-fit: contain;
  display: block;
  transition: transform 0.6s ease;
}
.rm-image-wrap:hover img { transform: scale(1.03); }

.rm-image-wrap::before {
  content: '';
  position: absolute; top: 18px; right: 18px;
  width: 48px; height: 48px;
  border-top: 2px solid rgba(255,255,255,0.55);
  border-right: 2px solid rgba(255,255,255,0.55);
  border-radius: 0 8px 0 0; z-index: 2; pointer-events: none;
}
.rm-image-wrap::after {
  content: '';
  position: absolute; bottom: 18px; left: 18px;
  width: 48px; height: 48px;
  border-bottom: 2px solid rgba(255,255,255,0.55);
  border-left: 2px solid rgba(255,255,255,0.55);
  border-radius: 0 0 0 8px; z-index: 2; pointer-events: none;
}

/* ── Right: info panel, scrolls only if needed ── */
.rm-info {
  padding: 28px 40px 32px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 0;
  background: var(--cream);
}

.rm-info::-webkit-scrollbar { width: 4px; }
.rm-info::-webkit-scrollbar-track { background: transparent; }
.rm-info::-webkit-scrollbar-thumb { background: var(--sand); border-radius: 99px; }

/* Eyebrow */
.rm-eyebrow {
  display: inline-flex; align-items: center; gap: 8px;
  font-size: 11px; letter-spacing: 0.25em; text-transform: uppercase;
  font-weight: 600; color: var(--rose-warm); margin-bottom: 10px;
}
.rm-eyebrow::before {
  content: ''; display: block; width: 24px; height: 1.5px;
  background: var(--rose-pale);
}

/* Stock badge */
.rm-stock-badge {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 12px; font-weight: 600; letter-spacing: 0.06em;
  padding: 4px 12px; border-radius: 20px; margin-bottom: 12px;
}
.rm-stock-badge.in-stock  { background: #f0fff4; color: #276749; border: 1px solid #9ae6b4; }
.rm-stock-badge.out-stock { background: #fff5f5; color: #c53030; border: 1px solid #feb2b2; }

/* Name */
.rm-name {
  font-family: var(--serif);
  font-size: clamp(2rem, 3.2vw, 3rem);
  font-weight: 300; color: var(--bark);
  line-height: 1.1; letter-spacing: -0.01em; margin-bottom: 12px;
}

/* Price */
.rm-price-row { display: flex; align-items: baseline; gap: 10px; margin-bottom: 16px; }
.rm-price { font-family: var(--serif); font-size: 2rem; font-weight: 600; color: var(--rose-deep); letter-spacing: -0.02em; }
.rm-price-label { font-size: 12px; letter-spacing: 0.1em; text-transform: uppercase; color: var(--text-muted); }

/* Divider */
.rm-divider { height: 1px; background: linear-gradient(to right, var(--sand), transparent); margin: 14px 0; }

/* Description */
.rm-desc-label { font-size: 11px; font-weight: 600; letter-spacing: 0.2em; text-transform: uppercase; color: var(--rose-warm); margin-bottom: 8px; }
.rm-description { font-family: var(--serif); font-size: 1.05rem; font-weight: 300; color: var(--text-sub); line-height: 1.8; margin-bottom: 16px; }

/* Highlights */
.rm-highlights { list-style: none; display: flex; flex-direction: column; gap: 7px; margin-bottom: 18px; }
.rm-highlights li { display: flex; align-items: center; gap: 10px; font-size: 13.5px; color: var(--text-sub); }
.rm-highlights .dot { width: 6px; height: 6px; border-radius: 50%; background: var(--rose-pale); flex-shrink: 0; }

/* Alerts */
.rm-alert { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 10px; font-size: 13px; font-weight: 500; margin-bottom: 12px; }
.rm-alert.error { background: #fff5f5; color: #c53030; border: 1px solid #feb2b2; }
.rm-alert.warn  { background: #fffbeb; color: #92400e; border: 1px solid #fcd34d; }

/* Cart actions */
.rm-actions { display: flex; flex-direction: column; gap: 10px; }
.rm-qty-row { display: flex; align-items: center; gap: 14px; }
.rm-qty-label { font-size: 12px; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-muted); min-width: 36px; }

.qty-control { display: flex; align-items: center; border: 1.5px solid var(--sand); border-radius: 30px; overflow: hidden; background: var(--white); transition: border-color 0.2s; }
.qty-control:focus-within { border-color: var(--rose-pale); }
.qty-btn { width: 36px; height: 36px; background: none; border: none; cursor: pointer; font-size: 18px; color: var(--text-muted); transition: background 0.15s, color 0.15s; display: flex; align-items: center; justify-content: center; }
.qty-btn:hover { background: var(--petal); color: var(--rose-deep); }
.qty-num { width: 40px; text-align: center; font-family: var(--sans); font-size: 16px; font-weight: 500; border: none; outline: none; background: transparent; -moz-appearance: textfield; color: var(--bark); }
.qty-num::-webkit-outer-spin-button, .qty-num::-webkit-inner-spin-button { -webkit-appearance: none; }

.btn-add-cart {
  display: flex; align-items: center; justify-content: center; gap: 10px;
  width: 100%; padding: 14px 28px;
  background: var(--rose); color: var(--white); border: none;
  border-radius: 30px; font-family: var(--sans); font-size: 13px;
  font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase;
  cursor: pointer; transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
  box-shadow: 0 6px 24px rgba(184,92,74,0.30);
}
.btn-add-cart:hover { background: var(--rose-deep); transform: translateY(-2px); box-shadow: 0 10px 32px rgba(140,61,45,0.36); }
.btn-add-cart:active { transform: translateY(0); }
.btn-add-cart:disabled { background: #ccc; color: #999; cursor: not-allowed; box-shadow: none; transform: none; }

/* Perks */
.rm-perks { display: flex; gap: 14px; margin-top: 18px; padding-top: 18px; border-top: 1px solid var(--sand); }
.rm-perk { display: flex; align-items: center; gap: 7px; flex: 1; font-size: 12px; color: var(--text-muted); line-height: 1.4; }
.rm-perk .perk-icon { font-size: 18px; flex-shrink: 0; }

@keyframes fadeUp {
  from { opacity: 0; transform: translateY(20px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ── Mobile fallback ── */
@media (max-width: 760px) {
  html, body { overflow: auto; }
  .rm-shell { grid-template-columns: 1fr; height: auto; }
  .rm-image-wrap { height: 55vw; min-height: 260px; }
  .rm-info { padding: 24px 20px; overflow-y: visible; }
}
</style>
<?php include __DIR__ . '/pwa_head.php'; ?>
</head>
<body>
<?php include "header.php"; ?>

<nav class="rm-breadcrumb">
  <a href="index.php">Home</a>
  <span class="sep">›</span>
  <a href="index.php?category=all#products">Shop</a>
  <span class="sep">›</span>
  <a href="index.php?category=<?= urlencode($product['category_name'] ?? 'Readymade') ?>#products"><?= htmlspecialchars($product['category_name'] ?? 'Ready-Made') ?></a>
  <span class="sep">›</span>
  <span class="current"><?= htmlspecialchars($product['name']) ?></span>
</nav>

<div class="rm-shell">

  <!-- Left: Product Image -->
  <div class="rm-image-wrap">
    <img
      src="images/<?= htmlspecialchars($product['image']) ?>"
      alt="<?= htmlspecialchars($product['name']) ?>"
      onerror="this.src='images/placeholder.jpg'">
  </div>

  <!-- Right: Product Info -->
  <div class="rm-info">

    <div class="rm-eyebrow">Ready-Made Bouquet</div>

    <?php if ($product['stock'] > 0): ?>
      <span class="rm-stock-badge in-stock">✔ In Stock — <?= (int)$product['stock'] ?> left</span>
    <?php else: ?>
      <span class="rm-stock-badge out-stock">✘ Out of Stock</span>
    <?php endif; ?>

      <h1 class="rm-name"><?= htmlspecialchars($product['name']) ?></h1>

      <div class="rm-price-row">
        <span class="rm-price">₱<?= number_format($product['price'], 2) ?></span>
        <span class="rm-price-label">per bouquet</span>
      </div>

      <div class="rm-divider"></div>

      <div class="rm-desc-label">About this Bouquet</div>
      <p class="rm-description"><?= nl2br(htmlspecialchars($product['description'])) ?></p>

      <ul class="rm-highlights">
        <li><span class="dot"></span> Beautifully arranged and ready to gift</li>
        <li><span class="dot"></span> No customization needed — perfect as-is</li>
        <li><span class="dot"></span> Fresh from our collection</li>
        <li><span class="dot"></span> Available for pickup or delivery</li>
      </ul>

      <!-- Alert messages -->
      <?php if ($message === "outofstock"): ?>
        <div class="rm-alert error">✘ This item is currently out of stock.</div>
      <?php elseif ($message === "maxstock"): ?>
        <div class="rm-alert warn">⚠ You've reached the maximum available quantity for this item.</div>
      <?php endif; ?>

      <!-- Cart Actions -->
      <?php if ($product['stock'] > 0): ?>
        <form method="POST" action="add_to_cart.php" class="rm-actions" id="rmForm">
          <input type="hidden" name="product_id" value="<?= $id ?>">
          <input type="hidden" name="total"       value="<?= $product['price'] ?>">

          <div class="rm-qty-row">
            <span class="rm-qty-label">Qty</span>
            <div class="qty-control">
              <button type="button" class="qty-btn" onclick="changeQty(-1)">−</button>
              <input type="number" name="qty" id="qtyInput" class="qty-num" value="1" min="1" max="<?= (int)$product['stock'] ?>">
              <button type="button" class="qty-btn" onclick="changeQty(1)">+</button>
            </div>
          </div>

          <button type="submit" class="btn-add-cart">
            🛒 Add to Cart
          </button>

        </form>
      <?php else: ?>
        <div class="rm-actions">
          <button class="btn-add-cart" disabled>Out of Stock</button>
          <p style="font-size:13px;color:var(--text-muted);text-align:center;">This bouquet is currently unavailable. Check back soon!</p>
        </div>
      <?php endif; ?>

      <!-- Perks -->
      <div class="rm-perks">
        <div class="rm-perk">
          <span class="perk-icon">🛵</span>
          <span>Delivery available</span>
        </div>
        <div class="rm-perk">
          <span class="perk-icon">🌸</span>
          <span>Carefully arranged</span>
        </div>
        <div class="rm-perk">
          <span class="perk-icon">💌</span>
          <span>Free gift message</span>
        </div>
      </div>

    </div><!-- /.rm-info -->
</div><!-- /.rm-shell -->

<script>
function changeQty(delta) {
  const input = document.getElementById('qtyInput');
  const max   = parseInt(input.getAttribute('max')) || 99;
  const val   = Math.min(max, Math.max(1, parseInt(input.value || 1) + delta));
  input.value = val;
}

// Measure actual header + breadcrumb height so shell fits perfectly
function setShellHeight() {
  const header = document.querySelector('header, .site-header, nav.header, #header, [class*="header"]');
  const breadcrumb = document.querySelector('.rm-breadcrumb');
  const headerH = header ? header.getBoundingClientRect().height : 60;
  const bcH = breadcrumb ? breadcrumb.getBoundingClientRect().height : 45;
  document.documentElement.style.setProperty('--header-h', headerH + 'px');
  document.documentElement.style.setProperty('--breadcrumb-h', bcH + 'px');
  document.documentElement.style.setProperty('--shell-height', '100dvh');
}
setShellHeight();
window.addEventListener('resize', setShellHeight);

// Handle multi-qty add: duplicate hidden inputs for quantity
// The server reads qty from POST and inserts that many times via loop
// OR we can keep it simple — just submit normally, the form sends qty
document.getElementById('rmForm')?.addEventListener('submit', function() {
  const btn = this.querySelector('.btn-add-cart');
  if (btn) {
    btn.disabled = true;
    btn.textContent = 'Adding…';
  }
});
</script>

</body>
</html>