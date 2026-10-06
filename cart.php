<?php
ob_start();
include "config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['user_id'];

/* REMOVE ITEM */
if (isset($_GET['remove'])) {
    $id = intval($_GET['remove']);
    $conn->query("DELETE FROM cart WHERE id=$id AND user_id=$uid");
    header("Location: cart.php");
    exit();
}

/* UPDATE QUANTITY */
if (isset($_POST['update'])) {
    foreach ($_POST['qty'] as $id => $qty) {
        $id = intval($id);
        $qty = max(1, intval($qty));
        $conn->query("UPDATE cart SET quantity=$qty WHERE id=$id AND user_id=$uid");
    }
    header("Location: cart.php");
    exit();
}

/* FETCH CART */
$sql = "
SELECT cart.id, cart.product_id, products.name, products.price, products.image, cart.quantity, cart.custom_price, cart.wrapper, cart.ribbon, cart.addons
FROM cart
JOIN products ON cart.product_id = products.id
WHERE cart.user_id = $uid
";

$result = $conn->query($sql);

if (!$result) {
    die("Query Error: " . $conn->error);
}

$total = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Your Cart</title>
<link rel="icon" type="image/png" href="images/logo/SB.png">
<link rel="shortcut icon" type="image/png" href="images/logo/SB.png">
<link rel="stylesheet" href="style.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,400;1,600&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
  @import url('https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400;1,600&family=Jost:wght@300;400;500;600&display=swap');

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
    --sage:       #7a9478;
    --sand:       #e8ddd6;
    --cream:      #fdfaf8;
    --white:      #ffffff;
    --text-main:  #231410;
    --text-sub:   #6b3d30;
    --text-muted: #a07060;
    --serif:      'Cormorant Garamond', Georgia, serif;
    --sans:       'Jost', system-ui, sans-serif;
    --radius-sm:  6px;
    --radius:     14px;
    --radius-lg:  24px;
  }

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  html, body {
    height: 100%;
    overflow: hidden;
  }

  body {
    background: var(--blush);
    color: var(--text-main);
    font-family: var(--sans);
    font-size: 15px;
    font-weight: 400;
    line-height: 1.4;
    -webkit-font-smoothing: antialiased;
    height: 100vh;
    overflow: hidden;
  }

  /* ── Ambient background texture ── */
  body::before {
    content: '';
    position: fixed;
    inset: 0;
    background:
      radial-gradient(ellipse 80% 50% at 10% 0%, rgba(212,115,95,0.08) 0%, transparent 60%),
      radial-gradient(ellipse 60% 60% at 90% 100%, rgba(184,92,74,0.06) 0%, transparent 55%),
      radial-gradient(ellipse 40% 40% at 50% 50%, rgba(249,237,232,0.4) 0%, transparent 70%);
    pointer-events: none;
    z-index: 0;
  }

  /* ── Page ── */
  .cart-page {
    max-width: 1280px;
    margin: 0 auto;
    padding: 12px 20px 12px;
    position: relative;
    z-index: 1;
    height: calc(100vh - 60px); /* account for header */
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }

  /* ── Header ── */
  .cart-heading {
    text-align: center;
    margin-bottom: 10px;
    flex-shrink: 0;
  }

  .cart-heading .eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    font-size: 11px;
    letter-spacing: 0.28em;
    text-transform: uppercase;
    color: var(--rose-warm);
    font-weight: 500;
    margin-bottom: 4px;
    font-family: var(--sans);
  }

  .cart-heading .eyebrow::before,
  .cart-heading .eyebrow::after {
    content: '';
    display: block;
    width: 32px;
    height: 1px;
    background: var(--rose-pale);
  }

  .cart-heading h1 {
    font-family: var(--serif);
    font-size: clamp(1.8rem, 3.5vw, 2.4rem);
    font-weight: 300;
    color: var(--bark);
    line-height: 1;
    letter-spacing: -0.01em;
  }

  .cart-heading h1 em {
    font-style: italic;
    font-weight: 400;
    color: var(--rose);
  }

  .cart-heading .item-count {
    display: inline-block;
    margin-top: 4px;
    font-size: 13px;
  }

  /* ── Layout ── */
  .cart-layout {
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: 20px;
    align-items: start;
    flex: 1;
    overflow: hidden;
    min-height: 0;
  }

  @media (max-width: 860px) { .cart-layout { grid-template-columns: 1fr; } }

  /* ── Select-all bar ── */
  .select-all-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 14px;
    background: var(--white);
    border-radius: var(--radius);
    border: 1px solid var(--sand);
    margin-bottom: 8px;
    font-size: 13px;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    font-weight: 500;
    color: var(--text-muted);
    cursor: pointer;
    user-select: none;
    transition: border-color 0.2s;
    flex-shrink: 0;
  }

  .select-all-bar:hover { border-color: var(--rose-pale); color: var(--rose); }

  /* ── Cart Item Card ── */
  .cart-items {
    display: flex;
    flex-direction: column;
    gap: 7px;
    overflow-y: auto;
    max-height: calc(100vh - 200px);
    padding-right: 4px;
  }

  .cart-item-card {
    background: var(--white);
    border-radius: var(--radius);
    border: 1px solid var(--sand);
    padding: 10px 14px;
    display: grid;
    grid-template-columns: 20px 90px 1fr auto;
    gap: 12px;
    align-items: center;
    transition: border-color 0.2s, box-shadow 0.2s, transform 0.2s;
    animation: slideIn 0.4s ease both;
    flex-shrink: 0;
  }

  .cart-item-card:hover {
    border-color: var(--rose-pale);
    box-shadow: 0 4px 24px rgba(184,92,74,0.08);
    transform: translateY(-1px);
  }

  @keyframes slideIn {
    from { opacity: 0; transform: translateY(16px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  /* staggered delay */
  .cart-item-card:nth-child(1) { animation-delay: 0.05s; }
  .cart-item-card:nth-child(2) { animation-delay: 0.1s; }
  .cart-item-card:nth-child(3) { animation-delay: 0.15s; }
  .cart-item-card:nth-child(4) { animation-delay: 0.2s; }
  .cart-item-card:nth-child(5) { animation-delay: 0.25s; }

  /* ── Checkbox ── */
  .cart-checkbox {
    appearance: none;
    width: 18px; height: 18px;
    border: 1.5px solid var(--sand);
    border-radius: 5px;
    cursor: pointer;
    flex-shrink: 0;
    transition: all 0.18s;
    position: relative;
    background: var(--white);
  }

  .cart-checkbox:checked {
    background: var(--rose);
    border-color: var(--rose);
  }

  .cart-checkbox:checked::after {
    content: '';
    position: absolute;
    left: 4px; top: 1.5px;
    width: 6px; height: 9px;
    border: 2px solid #fff;
    border-top: none;
    border-left: none;
    transform: rotate(42deg);
  }

  /* ── Product Image ── */
  .cart-item-img {
    width: 90px; height: 68px;
    border-radius: var(--radius-sm);
    overflow: hidden;
    flex-shrink: 0;
    position: relative;
    background: linear-gradient(135deg, var(--petal) 0%, var(--petal-mid) 100%);
    border: 1px solid var(--petal-mid);
  }

  .cart-item-img::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse at 30% 30%, rgba(255,255,255,0.4) 0%, transparent 60%);
    z-index: 1;
  }

  .cart-item-img .img-placeholder-icon {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    font-size: 26px;
    opacity: 0.4;
    z-index: 0;
  }

  .cart-item-img img {
    position: absolute;
    inset: 0;
    width: 100%; height: 100%;
    object-fit: cover;
    z-index: 2;
    transition: transform 0.4s ease;
  }

  .cart-item-card:hover .cart-item-img img { transform: scale(1.04); }

  /* ── Item Info ── */
  .cart-item-info { min-width: 0; }

  .cart-item-name {
    font-family: var(--serif);
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--bark);
    line-height: 1.2;
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .cart-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-bottom: 4px;
  }

  .cart-tag {
    font-size: 12px;
    font-weight: 500;
    letter-spacing: 0.04em;
    padding: 3px 10px;
    border-radius: 20px;
    background: var(--petal);
    color: var(--rose-deep);
    border: 1px solid var(--rose-pale);
  }

  .cart-unit-price {
    font-size: 14px;
    color: var(--text-muted);
    letter-spacing: 0.02em;
  }

  /* ── Controls ── */
  .cart-item-controls {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 6px;
    min-width: 100px;
  }

  .qty-wrapper {
    display: flex;
    align-items: center;
    border: 1px solid var(--sand);
    border-radius: 30px;
    overflow: hidden;
    background: var(--blush);
    transition: border-color 0.2s;
  }

  .qty-wrapper:focus-within { border-color: var(--rose-pale); }

  .qty-btn {
    width: 28px; height: 28px;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 16px;
    color: var(--text-muted);
    transition: background 0.15s, color 0.15s;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
  }

  .qty-btn:hover { background: var(--petal); color: var(--rose-deep); }

  .qty-input {
    width: 36px;
    border: none;
    text-align: center;
    font-family: var(--sans);
    font-size: 16px;
    -moz-appearance: textfield;
    outline: none;
  }

  .qty-input::-webkit-outer-spin-button,
  .qty-input::-webkit-inner-spin-button { -webkit-appearance: none; }

  .cart-subtotal {
    font-family: var(--serif);
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--bark);
    text-align: right;
    letter-spacing: -0.01em;
  }

  .cart-remove-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px; height: 28px;
    border-radius: 6px;
    background: transparent;
    color: var(--text-muted);
    text-decoration: none;
    font-size: 14px;
    border: 1px solid var(--sand);
    transition: all 0.15s;
  }

  .cart-remove-btn:hover {
    background: #fce8e6;
    color: #9b2f25;
    border-color: #f5c0bb;
  }

  /* ── Summary Card ── */
  .cart-summary {
    background: var(--white);
    border-radius: var(--radius-lg);
    padding: 20px 20px;
    position: sticky;
    top: 0;
    border: 1px solid var(--sand);
    box-shadow: 0 8px 40px rgba(35,20,16,0.08);
    animation: slideIn 0.45s ease 0.1s both;
    overflow-y: auto;
    max-height: calc(100vh - 160px);
  }

  .summary-title {
    font-family: var(--serif);
    font-size: 1.6rem;
    font-weight: 400;
    color: var(--bark);
    margin-bottom: 4px;
    letter-spacing: 0.01em;
  }

  .summary-subtitle {
    font-size: 12px;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--text-muted);
    margin-bottom: 14px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--sand);
  }

  .summary-row .label {
    font-size: 14px;
    font-weight: 500;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--text-muted);
  }

  .summary-row .value {
    font-family: var(--serif);
    font-size: 1.3rem;
    font-weight: 600;
    color: var(--bark);
  }

  .summary-divider {
    height: 1px;
    background: var(--sand);
    margin: 12px 0;
  }

  .summary-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 8px;
  }

  .summary-total .label {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-sub);
    letter-spacing: 0.08em;
    text-transform: uppercase;
  }

  .summary-total .value {
    font-family: var(--serif);
    font-size: 2rem;
    font-weight: 300;
    color: var(--rose-deep);
    letter-spacing: -0.02em;
    line-height: 1;
  }

  .btn-checkout {
    display: block;
    width: 100%;
    margin-top: 16px;
    padding: 12px;
    background: var(--rose);
    color: var(--white);
    border: none;
    border-radius: 30px;
    font-family: var(--sans);
    font-size: 12px;
    transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
    box-shadow: 0 4px 18px rgba(184,92,74,0.32);
  }

  .btn-checkout:hover {
    background: var(--rose-deep);
    transform: translateY(-2px);
    box-shadow: 0 8px 28px rgba(140,61,45,0.38);
  }

  .btn-checkout:active { transform: translateY(0); box-shadow: none; }

  .summary-perks {
    margin-top: 14px;
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .perk {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 13px;
    color: var(--text-muted);
  }

  .perk-icon {
    width: 20px; height: 20px;
    background: var(--petal);
    border-radius: 5px;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px;
    flex-shrink: 0;
  }

  /* ── Empty State ── */
  .cart-empty-wrap {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: center;
    gap: 48px;
    padding: 20px 40px;
    flex: 1;
    height: 100%;
  }

  .cart-empty-visual {
    width: 160px; height: 160px;
    background: var(--white);
    border: 1px solid var(--sand);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    position: relative;
    box-shadow: 0 8px 40px rgba(184,92,74,0.10);
    animation: float 4s ease-in-out infinite;
  }

  @keyframes float {
    0%, 100% { transform: translateY(0); }
    50%       { transform: translateY(-10px); }
  }

  .cart-empty-visual .petal-ring {
    position: absolute;
    inset: -14px;
    border-radius: 50%;
    border: 1px dashed var(--rose-pale);
    animation: spin 20s linear infinite;
  }

  @keyframes spin { to { transform: rotate(360deg); } }

  .cart-empty-visual .petal-ring::before,
  .cart-empty-visual .petal-ring::after {
    content: '🌸';
    position: absolute;
    font-size: 14px;
    top: -10px; left: 50%;
    transform: translateX(-50%);
  }

  .cart-empty-visual .petal-ring::after {
    top: auto; bottom: -10px;
    content: '🌺';
  }

  .cart-empty-visual .big-icon {
    font-size: 52px;
    filter: grayscale(0.3) opacity(0.65);
  }

  .cart-empty h2 {
    font-family: var(--serif);
    font-size: 3rem;
    font-weight: 300;
    color: var(--bark);
    margin-bottom: 10px;
    text-align: left;
  }

  .cart-empty h2 em { font-style: italic; color: var(--rose); }

  .cart-empty p {
    color: var(--text-muted);
    font-size: 17px;
    text-align: left;
    max-width: 340px;
    line-height: 1.7;
    margin-bottom: 28px;
  }

  .btn-shop {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 15px 40px;
    background: var(--rose);
    color: var(--white);
    text-decoration: none;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    transition: all 0.2s;
    box-shadow: 0 4px 18px rgba(184,92,74,0.30);
  }

  .btn-shop:hover {
    background: var(--rose-deep);
    transform: translateY(-2px);
    box-shadow: 0 8px 28px rgba(140,61,45,0.35);
  }

  .btn-shop .arrow { font-size: 14px; transition: transform 0.2s; }
  .btn-shop:hover .arrow { transform: translateX(4px); }

  /* ── Responsive ── */
  @media (max-width: 640px) {
    .cart-item-card {
      grid-template-columns: 18px 95px 1fr;
      grid-template-rows: auto auto;
      gap: 12px;
      padding: 14px 14px;
    }

    .cart-item-img { width: 95px; height: 72px; }

    .cart-item-controls {
      grid-column: 2 / -1;
      flex-direction: row;
      align-items: center;
      justify-content: space-between;
    }

    .cart-item-name { font-size: 1.05rem; }
  }
</style>
<?php include __DIR__ . '/pwa_head.php'; ?>
</head>

<body>
<?php include "header.php"; ?>

<div class="cart-page">

  <!-- Heading -->
  <div class="cart-heading">
    <div class="eyebrow">Shopping Bag</div>
    <h1>Your <em>Cart</em></h1>
    <?php if ($result->num_rows > 0): ?>
      <span class="item-count"><?= $result->num_rows ?> item<?= $result->num_rows > 1 ? 's' : '' ?> waiting for you</span>
    <?php endif; ?>
  </div>

  <?php if ($result->num_rows > 0): ?>

    <div class="cart-layout">

      <!-- Left: Items -->
      <div style="display:flex;flex-direction:column;overflow:hidden;min-height:0;height:100%;">
        <!-- Select All bar -->
        <label class="select-all-bar">
          <input type="checkbox" id="selectAll" style="appearance:none;width:16px;height:16px;border:1.5px solid var(--sand);border-radius:4px;cursor:pointer;position:relative;background:var(--white);flex-shrink:0;transition:all .18s;" checked>
          Select all items
        </label>

        <form method="POST" class="cart-form" id="cartForm">
          <div class="cart-items">
            <?php while($row = $result->fetch_assoc()):
              $price = $row['custom_price'] ? $row['custom_price'] : $row['price'];
              $sub   = $price * $row['quantity'];
            ?>
            <div class="cart-item-card">

              <input type="checkbox"
                name="selected_items[]"
                value="<?= $row['id'] ?>"
                class="cart-checkbox"
                data-price="<?= $sub ?>"
                checked>

              <div class="cart-item-img">
                <span class="img-placeholder-icon">🌸</span>
                <img src="images/<?= htmlspecialchars($row['image']) ?>"
                     alt="<?= htmlspecialchars($row['name']) ?>"
                     onerror="this.style.display='none'">
              </div>

              <div class="cart-item-info">
                <div class="cart-item-name"><?= htmlspecialchars($row['name']) ?></div>

                <?php if ($row['wrapper'] || $row['ribbon'] || $row['addons']): ?>
                  <div class="cart-tags">
                    <?php if ($row['wrapper']): ?>
                      <span class="cart-tag">🎀 <?= htmlspecialchars($row['wrapper']) ?></span>
                    <?php endif; ?>
                    <?php if ($row['ribbon']): ?>
                      <span class="cart-tag">🎗️ <?= htmlspecialchars($row['ribbon']) ?></span>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>

                <div class="cart-unit-price">₱<?= number_format($price, 2) ?> each</div>
              </div>

              <div class="cart-item-controls">
                <div class="qty-wrapper">
                  <button type="button" class="qty-btn" onclick="changeQty(this,-1)">−</button>
                  <input type="number"
                    name="qty[<?= $row['id'] ?>]"
                    value="<?= $row['quantity'] ?>"
                    min="1"
                    class="qty-input"
                    data-price="<?= $price ?>"
                    onchange="updateSubtotal(this)">
                  <button type="button" class="qty-btn" onclick="changeQty(this,1)">+</button>
                </div>
                <div class="cart-subtotal" data-subtotal="<?= $sub ?>">
                  ₱<?= number_format($sub, 2) ?>
                </div>
                <a href="?remove=<?= $row['id'] ?>" class="cart-remove-btn" title="Remove item">✕</a>
              </div>

            </div>
            <?php endwhile; ?>
          </div>
        </form>
      </div>

      <!-- Right: Summary -->
      <div class="cart-summary">
        <div class="summary-title">Order Summary</div>
        <div class="summary-subtitle">Selected items only</div>

        <div class="summary-row">
          <span class="label">Subtotal</span>
          <span class="value" id="cartTotal">₱0.00</span>
        </div>

        <div class="summary-divider"></div>

        <div class="summary-row summary-total">
          <span class="label">Total</span>
          <span class="value" id="cartTotalFinal">₱0.00</span>
        </div>

        <form method="POST" action="checkout.php" id="checkoutForm">
          <input type="hidden" name="selected_items" id="selectedItemsInput" value="">
          <button type="button" class="btn-checkout" onclick="proceedToCheckout()">
            Proceed to Checkout →
          </button>
        </form>

        <div class="summary-perks">
          <div class="perk">
            <div class="perk-icon">🛵</div>
            Delivery fee calculated at checkout
          </div>
          <div class="perk">
            <div class="perk-icon">🌸</div>
            Fresh blooms, carefully arranged
          </div>
          <div class="perk">
            <div class="perk-icon">💌</div>
            Free gift message included
          </div>
        </div>
      </div>

    </div>

  <?php else: ?>

    <div class="cart-empty-wrap">
      <div class="cart-empty-visual">
        <div class="petal-ring"></div>
        <span class="big-icon">🌷</span>
      </div>
      <div class="cart-empty">
        <h2>Nothing here <em>yet</em></h2>
        <p>Discover our beautiful arrangements and add something special to your day.</p>
        <a href="index.php" class="btn-shop">
          Continue Shopping <span class="arrow">→</span>
        </a>
      </div>
    </div>

  <?php endif; ?>

</div>

<script>
function changeQty(btn, delta) {
  const input = btn.parentElement.querySelector('.qty-input');
  const newVal = Math.max(1, parseInt(input.value || 1) + delta);
  input.value = newVal;
  updateSubtotal(input);
}

function updateSubtotal(input) {
  const card   = input.closest('.cart-item-card');
  const price  = parseFloat(input.getAttribute('data-price'));
  const qty    = Math.max(1, parseInt(input.value) || 1);
  const newSub = price * qty;

  const subtotalEl = card.querySelector('.cart-subtotal');
  subtotalEl.textContent = '₱' + newSub.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  subtotalEl.setAttribute('data-subtotal', newSub);

  const checkbox = card.querySelector('.cart-checkbox');
  checkbox.setAttribute('data-price', newSub);

  calculateTotal();
}

function calculateTotal() {
  let total = 0;
  document.querySelectorAll('.cart-checkbox:checked').forEach(cb => {
    total += parseFloat(cb.getAttribute('data-price')) || 0;
  });
  const formatted = '₱' + total.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  document.getElementById('cartTotal').textContent      = formatted;
  document.getElementById('cartTotalFinal').textContent = formatted;
}

function proceedToCheckout() {
  const ids = [...document.querySelectorAll('.cart-checkbox:checked')].map(cb => cb.value);
  if (!ids.length) { alert('Please select at least one item to checkout.'); return; }
  document.getElementById('selectedItemsInput').value = ids.join(',');
  document.getElementById('checkoutForm').submit();
}

document.querySelectorAll('.cart-checkbox').forEach(cb => {
  cb.addEventListener('change', calculateTotal);
});

// Select all toggle
const selectAll = document.getElementById('selectAll');
if (selectAll) {
  selectAll.addEventListener('change', () => {
    document.querySelectorAll('.cart-checkbox').forEach(cb => {
      cb.checked = selectAll.checked;
    });
    calculateTotal();
  });

  // Style the select-all checkbox like others
  const style = selectAll.style;
  selectAll.addEventListener('change', () => {
    if (selectAll.checked) {
      style.background = 'var(--rose)';
      style.borderColor = 'var(--rose)';
    } else {
      style.background = 'var(--white)';
      style.borderColor = 'var(--sand)';
    }
  });
  // Initial state
  selectAll.style.background = 'var(--rose)';
  selectAll.style.borderColor = 'var(--rose)';
}

calculateTotal();
</script>

</body>
</html>