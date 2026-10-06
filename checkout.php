<?php
ob_start();
include "config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$uid = intval($_SESSION['user_id']);

/* USER */
$userStmt = $conn->prepare("SELECT *, IFNULL(barangay,'') as barangay FROM users WHERE id = ?");
$userStmt->bind_param('i', $uid);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

// ── Delivery fee — uses shared shipping_fees.php ──
include_once "shipping_fees.php";
// Ensure municipality/barangay columns exist for old accounts
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS barangay     VARCHAR(120) DEFAULT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS municipality VARCHAR(120) DEFAULT NULL");

// Try: barangay first, then municipality column, then full address string
$barangayRaw     = trim($user['barangay']     ?? '');
$municipalityRaw = trim($user['municipality'] ?? '');
$addressRaw      = trim($user['address']      ?? '');

$deliveryInfo = ['fee' => 0, 'label' => '', 'found' => false];
if ($barangayRaw !== '')                               $deliveryInfo = getShippingFee($barangayRaw);
if (!$deliveryInfo['found'] && $municipalityRaw !== '') $deliveryInfo = getShippingFee($municipalityRaw);
if (!$deliveryInfo['found'] && $addressRaw !== '')      $deliveryInfo = getShippingFee($addressRaw);

$deliveryFee      = $deliveryInfo['fee'];
$deliveryLocation = $deliveryInfo['label'];
$deliveryFound    = $deliveryInfo['found'];

// ── Selected cart items ───────────────────────────────────────────────────────
$selectedItems = $_POST['selected_items'] ?? '';
$selectedIds   = $selectedItems ? array_filter(array_map('intval', explode(',', $selectedItems)), fn($v) => $v > 0) : [];

if (empty($selectedIds)) {
    die("Please select at least one product to checkout.");
}

$idsString = implode(',', $selectedIds);

$result = $conn->query("
    SELECT cart.*,
           products.name    AS name,
           products.image,
           products.price,
           products.stock
    FROM cart
    JOIN products ON cart.product_id = products.id
    WHERE cart.user_id = $uid AND cart.id IN ($idsString)
");

if (!$result || $result->num_rows == 0) {
    die("Your cart is empty.");
}

$cartItems = $result->fetch_all(MYSQLI_ASSOC);

$total          = 0;
$hasStockIssue  = false;
$payment_method = $_POST['payment_method'] ?? 'gcash';
$delivery_mode  = $_POST['delivery_mode']  ?? 'delivery';

foreach ($cartItems as $row) {
    if ($row['quantity'] > $row['stock']) {
        $hasStockIssue = true;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Checkout — SB Blooms</title>
<link rel="icon" type="image/png" href="images/logo/SB.png">
<link rel="shortcut icon" type="image/png" href="images/logo/SB.png">
<link rel="stylesheet" href="style.css">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  html, body {
    height: 100%;
    overflow: auto;
    background: #fdf8f5;
    font-family: 'Inter', sans-serif;
    color: #2d2d2d;
  }

  /* Landscape enforcement overlay */
  .rotate-prompt {
    display: none;
    position: fixed;
    inset: 0;
    background: #fdf8f5;
    z-index: 9999;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    gap: 16px;
  }
  .rotate-prompt .rp-icon { font-size: 52px; animation: spin90 1.2s ease-in-out infinite alternate; }
  @keyframes spin90 { from { transform: rotate(0deg); } to { transform: rotate(90deg); } }
  .rotate-prompt p { font-size: 15px; color: #888; max-width: 220px; }
  .rotate-prompt strong { display: block; font-size: 17px; color: #be185d; margin-bottom: 4px; }

  @media (orientation: portrait) {
    .rotate-prompt { display: flex; }
    .co-page       { display: none; }
  }

  /* Full-screen page shell */
  .co-page {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    padding: 0;
    overflow: visible;
  }

  /* Hero — slim single-line bar */
  .co-hero {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 8px 20px 6px;
    flex-shrink: 0;
    border-bottom: 1px solid #f0e8e4;
    margin-bottom: 0;
  }
  .co-hero .badge {
    display: inline-flex;
    align-items: center;
    background: #fce7f3;
    color: #be185d;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .12em;
    text-transform: uppercase;
    padding: 3px 10px;
    border-radius: 99px;
  }
  .co-hero h1 {
    font-family: 'Playfair Display', serif;
    font-size: 20px;
    color: #1a1a1a;
    line-height: 1;
  }
  .co-hero p { display: none; }

  /* Two-col grid — fills remaining vertical space, no outer scroll */
  .co-grid {
    display: grid;
    grid-template-columns: 1fr 360px;
    gap: 16px;
    align-items: start;
    flex: 1;
    min-height: 0;
    padding: 10px 16px 10px;
    overflow: visible;
  }

  /* Card */
  .co-card {
    background: #fff;
    border-radius: 12px;
    border: 1px solid #f0e8e4;
    padding: 10px 14px;
    margin-bottom: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,.03);
  }
  .co-card-title {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: #be185d;
    margin-bottom: 8px;
  }

  /* Left column scrolls internally */
  .co-left {
    height: auto;
    overflow-y: visible;
    padding-right: 6px;
  }

  /* Customer */
  .customer-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
  @media (max-width: 540px) { .customer-grid { grid-template-columns: 1fr; } }
  .customer-field { background: #fdf8f5; border-radius: 8px; padding: 7px 12px; font-size: 12px; }
  .customer-field .cf-label { font-size: 9px; font-weight: 700; color: #be185d; text-transform: uppercase; letter-spacing: .08em; margin-bottom: 1px; }
  .customer-field .cf-value { color: #333; font-weight: 500; }

  /* Toggle options */
  .toggle-group { display: flex; gap: 10px; }
  .toggle-opt {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border: 1.5px solid #e8ddd9;
    border-radius: 10px;
    cursor: pointer;
    transition: all .18s;
    font-size: 12px;
    font-weight: 500;
    color: #555;
    background: #fdf8f5;
    user-select: none;
  }
  .toggle-opt input { display: none; }
  .toggle-opt .icon { font-size: 18px; }
  .toggle-opt.selected { border-color: #f472b6; background: #fdf2f8; color: #be185d; box-shadow: 0 0 0 3px rgba(244,114,182,.12); }
  .toggle-opt:hover:not(.selected) { border-color: #e9a8c7; background: #fff7fc; }

  /* Stock banner */
  .stock-banner {
    background: #fff5f5;
    border: 1px solid #fca5a5;
    border-radius: 12px;
    padding: 10px 14px;
    margin: 0 20px 10px;
    display: flex;
    gap: 12px;
    align-items: flex-start;
    flex-shrink: 0;
  }
  .stock-banner .sb-icon { font-size: 20px; flex-shrink: 0; }
  .stock-banner strong { color: #b91c1c; font-size: 14px; display: block; margin-bottom: 2px; }
  .stock-banner p { color: #7f1d1d; font-size: 12px; }

  /* Product rows */
  .product-row {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    padding: 12px 0;
    border-bottom: 1px solid #f5ede9;
  }
  .product-row:last-child { border-bottom: none; padding-bottom: 0; }
  .product-row.issue { background: #fff5f5; border-radius: 10px; padding: 10px; margin-bottom: 4px; border-bottom: none; }

  .pr-img {
    width: 60px; height: 60px;
    border-radius: 10px;
    object-fit: cover;
    flex-shrink: 0;
    border: 1px solid #f0e8e4;
  }
  .pr-img.dimmed { opacity: .45; filter: grayscale(.4); }

  .pr-body { flex: 1; min-width: 0; }
  .pr-name { font-weight: 600; font-size: 14px; color: #1a1a1a; margin-bottom: 4px; }
  .pr-meta { font-size: 12px; color: #888; line-height: 1.7; }
  .pr-stock-ok   { color: #16a34a; font-size: 11px; font-weight: 600; margin-top: 5px; display: inline-block; }
  .pr-stock-warn { color: #dc2626; font-size: 11px; font-weight: 600; margin-top: 5px; background: #fff0f0; padding: 3px 8px; border-radius: 6px; display: inline-block; }

  .pr-price { text-align: right; flex-shrink: 0; }
  .pr-unit { font-size: 11px; color: #aaa; margin-bottom: 2px; }
  .pr-sub { font-size: 15px; font-weight: 700; color: #1a1a1a; }
  .pr-qty { font-size: 12px; color: #888; margin-top: 2px; }

  /* Right summary panel — fills column height */
  .co-summary {
    display: flex;
    flex-direction: column;
    height: auto;
    overflow-y: visible;
    position: sticky;
    top: 10px;
  }
  .co-summary .co-card { flex: 1; display: flex; flex-direction: column; margin-bottom: 0; }
  .summary-lines { margin-bottom: 12px; }
  .sl-row { display: flex; justify-content: space-between; font-size: 13px; color: #666; padding: 4px 0; }
  .sl-row.total { font-size: 17px; font-weight: 700; color: #1a1a1a; border-top: 1px solid #f0e8e4; margin-top: 8px; padding-top: 10px; }

  /* CTA */
  .btn-order {
    width: 100%;
    padding: 13px;
    border-radius: 12px;
    border: none;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: .04em;
    cursor: pointer;
    transition: all .2s;
    background: linear-gradient(135deg, #f472b6 0%, #ec4899 100%);
    color: #fff;
    box-shadow: 0 4px 18px rgba(236,72,153,.35);
  }
  .btn-order:hover { transform: translateY(-2px); box-shadow: 0 8px 26px rgba(236,72,153,.45); }
  .btn-order:active { transform: translateY(0); }
  .btn-order.disabled { background: #e2e8f0; color: #94a3b8; box-shadow: none; cursor: not-allowed; pointer-events: none; }

  .back-link { display: block; text-align: center; margin-top: 12px; font-size: 12px; color: #be185d; text-decoration: none; }
  .back-link:hover { text-decoration: underline; }
  .secure-note { display: flex; align-items: center; justify-content: center; gap: 5px; font-size: 11px; color: #bbb; margin-top: 14px; }


  .location-panel { display: none; margin-top: 10px; animation: panelIn .22s ease; }
  .location-panel.show { display: block; }
  @keyframes panelIn {
    from { opacity: 0; transform: translateY(-6px); }
    to   { opacity: 1; transform: translateY(0); }
  }
  .delivery-info-box {
    background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 100%);
    border: 1.5px solid #f9a8d4; border-radius: 10px; padding: 10px 12px;
    display: flex; flex-direction: column; gap: 7px;
  }
  .dib-row { display: flex; justify-content: space-between; align-items: center; gap: 10px; }
  .dib-label    { font-size: 11px; color: #888; font-weight: 500; white-space: nowrap; }
  .dib-location { font-size: 11px; color: #444; font-weight: 500; text-align: right; }
  .dib-fee-row  { border-top: 1px solid #f9a8d4; padding-top: 7px; }
  .dib-fee      { font-size: 15px; font-weight: 800; color: #be185d; }
  .dib-fee.free { color: #16a34a; }
  .delivery-warn-box {
    margin-top: 12px; background: #fff5f5; border: 1.5px solid #fca5a5;
    border-radius: 12px; padding: 14px 16px;
    display: flex; gap: 10px; align-items: flex-start; font-size: 13px;
  }
  .delivery-warn-box strong { color: #b91c1c; display: block; margin-bottom: 3px; }
  .delivery-warn-box p { color: #7f1d1d; font-size: 12px; line-height: 1.5; }
  .delivery-warn-box a { color: #be185d; font-weight: 600; }
</style>
<?php include __DIR__ . '/pwa_head.php'; ?>
</head>
<body>

<!-- Shown only in portrait mode -->
<div class="rotate-prompt">
  <div class="rp-icon">📱</div>
  <div>
    <strong>Rotate your device</strong>
    <p>Please switch to landscape mode for the best checkout experience.</p>
  </div>
</div>

<?php include "header.php"; ?>

<div class="co-page">

  <div class="co-hero">
    <div class="badge">🌸 Order Review</div>
    <h1>Checkout</h1>
    <p>Review your bouquet, confirm details, and place your order.</p>
  </div>

  <?php if ($hasStockIssue): ?>
  <div class="stock-banner">
    <span class="sb-icon">⚠️</span>
    <div>
      <strong>Stock issue detected</strong>
      <p>One or more items exceed available stock. Please update your cart before placing your order.</p>
    </div>
  </div>
  <?php endif; ?>

  <div class="co-grid">
    <div class="co-left">

      <!-- Customer info -->
      <div class="co-card">
        <div class="co-card-title">👤 Customer Details</div>
        <div class="customer-grid">
          <div class="customer-field">
            <div class="cf-label">Name</div>
            <div class="cf-value"><?= htmlspecialchars($user['full_name'] ?: $user['username']) ?></div>
          </div>
          <div class="customer-field">
            <div class="cf-label">Phone</div>
            <div class="cf-value"><?= htmlspecialchars($user['phone'] ?? '—') ?></div>
          </div>
          <div class="customer-field">
            <div class="cf-label">Address</div>
            <div class="cf-value"><?= htmlspecialchars($user['address'] ?? '—') ?></div>
          </div>
          <div class="customer-field">
            <div class="cf-label">Facebook</div>
            <div class="cf-value"><?= htmlspecialchars($user['facebook'] ?? '—') ?></div>
          </div>
        </div>
      </div>

      <!-- Payment -->
      <div class="co-card">
        <div class="co-card-title">💳 Payment Method</div>
        <div class="toggle-group" id="paymentGroup">
          <label class="toggle-opt <?= $payment_method == 'gcash' ? 'selected' : '' ?>">
            <input type="radio" name="payment_method" value="gcash" <?= $payment_method == 'gcash' ? 'checked' : '' ?> onchange="selectToggle('payment', this)">
            <span class="icon"><img src="images/logo/Gcash.png" alt="GCash" style="height:22px;width:auto;vertical-align:middle;"></span> GCash
          </label>
          <label class="toggle-opt <?= $payment_method == 'cash' ? 'selected' : '' ?>">
            <input type="radio" name="payment_method" value="cash" <?= $payment_method == 'cash' ? 'checked' : '' ?> onchange="selectToggle('payment', this)">
            <span class="icon">💵</span> Cash on Delivery
          </label>
        </div>


      </div>

      <!-- Delivery -->
      <div class="co-card">
        <div class="co-card-title">🚚 Delivery Mode</div>
        <div class="toggle-group" id="deliveryGroup">
          <label class="toggle-opt <?= $delivery_mode == 'delivery' ? 'selected' : '' ?>">
            <input type="radio" name="delivery_mode" value="delivery" <?= $delivery_mode == 'delivery' ? 'checked' : '' ?> onchange="selectToggle('delivery', this)">
            <span class="icon">🏠</span> Home Delivery
          </label>
          <label class="toggle-opt <?= $delivery_mode == 'pickup' ? 'selected' : '' ?>">
            <input type="radio" name="delivery_mode" value="pickup" <?= $delivery_mode == 'pickup' ? 'checked' : '' ?> onchange="selectToggle('delivery', this)">
            <span class="icon">🏪</span> Store Pickup
          </label>
        </div>

        <div class="location-panel <?= $delivery_mode == 'delivery' ? 'show' : '' ?>" id="locationPanel">
          <?php if ($deliveryFound): ?>
            <div class="delivery-info-box">
              <div class="dib-row">
                <span class="dib-label">📍 Delivery to</span>
                <span class="dib-location"><?= htmlspecialchars(ucwords(strtolower($user['address'] ?? ''))) ?></span>
              </div>
              <div class="dib-row dib-fee-row">
                <span class="dib-label">🛵 Delivery Fee</span>
                <span class="dib-fee <?= $deliveryFee == 0 ? 'free' : '' ?>">
                  <?= $deliveryFee == 0 ? '🎉 Free' : '₱' . number_format($deliveryFee, 2) ?>
                </span>
              </div>
            </div>
          <?php else: ?>
            <div class="delivery-warn-box">
              <span>ℹ️</span>
              <div>
                <strong>Delivery fee to be confirmed</strong>
                <p>We'll calculate your delivery fee based on your address (<em><?= htmlspecialchars($user['address'] ?? 'not set') ?></em>) and contact you to confirm. You can still place your order now.</p>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Items -->
      <div class="co-card">
        <div class="co-card-title">🌺 Your Items (<?= count($cartItems) ?>)</div>

        <?php foreach ($cartItems as $row):
          $price      = !empty($row['custom_price']) ? $row['custom_price'] : $row['price'];
          $sub        = $price * $row['quantity'];
          $total     += $sub;
          $outOfStock = $row['stock'] <= 0;
          $overStock  = $row['quantity'] > $row['stock'] && !$outOfStock;
          $hasIssue   = $outOfStock || $overStock;
        ?>
        <div class="product-row <?= $hasIssue ? 'issue' : '' ?>">
          <img src="images/<?= htmlspecialchars($row['image']) ?>"
               class="pr-img <?= $hasIssue ? 'dimmed' : '' ?>"
               alt="<?= htmlspecialchars($row['name']) ?>">
          <div class="pr-body">
            <div class="pr-name"><?= htmlspecialchars($row['name']) ?></div>
            <div class="pr-meta">
              <?php if (!empty($row['wrapper']) && $row['wrapper'] !== 'none'): ?>Wrapper: <?= htmlspecialchars($row['wrapper']) ?><br><?php endif; ?>
              <?php if (!empty($row['ribbon'])  && $row['ribbon']  !== 'none'): ?>Ribbon: <?= htmlspecialchars($row['ribbon']) ?><br><?php endif; ?>
              <?php if (!empty($row['addons'])): ?>
                <?php $addons = json_decode($row['addons'], true); ?>
                <?php if (is_array($addons)): foreach ($addons as $k => $v): ?>
                  <?= htmlspecialchars($k) ?>: ₱<?= number_format($v, 2) ?><br>
                <?php endforeach; else: ?>
                  Add-ons: <?= htmlspecialchars($row['addons']) ?>
                <?php endif; ?>
              <?php endif; ?>
            </div>
            <?php if ($outOfStock): ?>
              <span class="pr-stock-warn">❌ Out of stock — remove from cart</span>
            <?php elseif ($overStock): ?>
              <span class="pr-stock-warn">⚠️ Only <?= $row['stock'] ?> left · you have <?= $row['quantity'] ?></span>
            <?php else: ?>
              <span class="pr-stock-ok">✔ In stock (<?= $row['stock'] ?> available)</span>
            <?php endif; ?>
          </div>
          <div class="pr-price">
            <div class="pr-unit">₱<?= number_format($price, 2) ?> each</div>
            <div class="pr-sub">₱<?= number_format($sub, 2) ?></div>
            <div class="pr-qty">Qty: <?= $row['quantity'] ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

    </div><!-- /co-left -->

    <!-- Summary panel -->
    <div class="co-summary">
      <div class="co-card">
        <div class="co-card-title">🧾 Order Summary</div>

        <?php $grandTotal = $total + ($delivery_mode === 'delivery' ? $deliveryFee : 0); ?>
        <div class="summary-lines">
          <div class="sl-row">
            <span>Subtotal (<?= count($cartItems) ?> item<?= count($cartItems) > 1 ? 's' : '' ?>)</span>
            <span>₱<?= number_format($total, 2) ?></span>
          </div>
          <div class="sl-row" id="summaryDeliveryRow">
            <span>Delivery fee</span>
            <span id="summaryDeliveryFee" style="color:<?= ($delivery_mode === 'pickup' || $deliveryFee == 0) ? '#16a34a' : '#be185d' ?>;">
              <?php if ($delivery_mode === 'pickup'): ?>
                Free (Store Pickup)
              <?php elseif ($deliveryFee == 0): ?>
                🎉 Free
              <?php else: ?>
                ₱<?= number_format($deliveryFee, 2) ?>
              <?php endif; ?>
            </span>
          </div>
          <div class="sl-row total">
            <span>Total</span>
            <span id="summaryTotal">₱<?= number_format($grandTotal, 2) ?></span>
          </div>
        </div>


        <form method="POST" action="place_order.php" id="checkoutForm">
          <input type="hidden" name="subtotal"          value="<?= $total ?>">
          <input type="hidden" name="total"             id="fTotal"       value="<?= $grandTotal ?>">
          <input type="hidden" name="delivery_fee"      id="fDeliveryFee" value="<?= $deliveryFee ?>">
          <input type="hidden" name="delivery_location" value="<?= htmlspecialchars($deliveryLocation) ?>">
          <input type="hidden" name="payment_method"    id="fPayment"     value="<?= htmlspecialchars($payment_method) ?>">
          <input type="hidden" name="delivery_mode"     id="fDelivery"    value="<?= htmlspecialchars($delivery_mode) ?>">
          <input type="hidden" name="selected_items"    value="<?= htmlspecialchars($selectedItems) ?>">


          <?php if ($hasStockIssue): ?>
            <button type="button" class="btn-order disabled">⚠️ Fix Stock Issues First</button>
            <a href="cart.php" class="back-link">← Update quantities in cart</a>
          <?php else: ?>
            <button type="submit" class="btn-order">Place Order →</button>
          <?php endif; ?>
        </form>

        <div class="secure-note">🔒 Secure &amp; encrypted checkout</div>
      </div>
    </div>

  </div><!-- /co-grid -->
</div>

<script>
const baseTotal    = <?= $total ?>;
const deliveryFee  = <?= $deliveryFee ?>;
const deliveryFound = <?= $deliveryFound ? 'true' : 'false' ?>;

function selectToggle(type, radio) {
  const groupId  = type === 'payment' ? 'paymentGroup'  : 'deliveryGroup';
  const hiddenId = type === 'payment' ? 'fPayment'      : 'fDelivery';
  document.querySelectorAll(`#${groupId} .toggle-opt`).forEach(o => o.classList.remove('selected'));
  radio.closest('.toggle-opt').classList.add('selected');
  document.getElementById(hiddenId).value = radio.value;

  if (type === 'delivery') {
    const panel   = document.getElementById('locationPanel');
    const feeEl   = document.getElementById('summaryDeliveryFee');
    const totalEl = document.getElementById('summaryTotal');
    const fTotal  = document.getElementById('fTotal');
    const fFee    = document.getElementById('fDeliveryFee');

    panel.classList.toggle('show', radio.value === 'delivery');

    if (radio.value === 'pickup') {
      feeEl.style.color   = '#16a34a';
      feeEl.textContent   = 'Free (Store Pickup)';
      totalEl.textContent = '₱' + baseTotal.toFixed(2);
      fTotal.value = baseTotal;
      fFee.value   = 0;
    } else {
      const fee   = deliveryFound ? deliveryFee : 0;
      const grand = baseTotal + fee;
      feeEl.style.color   = fee === 0 ? '#16a34a' : '#be185d';
      feeEl.textContent   = fee === 0 ? (deliveryFound ? '🎉 Free' : '— Location unknown') : '₱' + fee.toFixed(2);
      totalEl.textContent = '₱' + grand.toFixed(2);
      fTotal.value = grand;
      fFee.value   = fee;
    }
  }
}
</script>

</body>
</html>