<?php
include "config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

$id      = intval($_GET['id']);
$product = $conn->query("
    SELECT p.*, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.id = $id
")->fetch_assoc();

if (!$product) {
    echo "<p style='padding:30px;color:#fff;'>Product not found.</p>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Customize – <?= htmlspecialchars($product['name']) ?></title>
<link rel="icon" type="image/png" href="images/logo/SB.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --rose:    #c9748a;
  --rose-lt: #f5e6ea;
  --rose-dk: #8a4558;
  --blush:   #f9f0f2;
  --sage:    #7a9e8e;
  --cream:   #fdf8f4;
  --warm:    #3a2e2a;
  --mid:     #7a6b65;
  --border:  rgba(201,116,138,0.18);
  --gold:    #b89a6e;
  --gold-lt: #f7f1e8;
}

/* ── Breadcrumb ── */
.cx-breadcrumb {
  position: fixed;
  top: 0; left: 0; right: 0;
  z-index: 150;
  background: rgba(253,248,244,0.97);
  backdrop-filter: blur(10px);
  border-bottom: 1px solid var(--border);
  padding: 15px 36px;
  display: flex;
  align-items: center;
  gap: 12px;
  font-family: 'Cormorant Garamond', serif;
  font-size: 18px;
  font-weight: 600;
  font-style: normal;
  letter-spacing: 0.02em;
  color: var(--rose);
}
.cx-breadcrumb a {
  color: var(--mid);
  text-decoration: none;
  transition: color 0.15s;
}
.cx-breadcrumb a:hover { color: var(--rose-dk); }
.cx-breadcrumb .sep { color: var(--rose-pale, #e8b4c0); font-size: 18px; font-style: normal; }
.cx-breadcrumb .current { color: var(--rose); font-weight: 600; }

/* ── No-scroll landscape shell ── */
html, body {
  height: 100%;
  overflow: hidden;
  font-family: 'DM Sans', sans-serif;
  background: var(--cream);
  color: var(--warm);
}

body::before {
  content: '';
  position: fixed; inset: 0;
  background:
    radial-gradient(ellipse 60% 40% at 10% 20%, rgba(201,116,138,0.08) 0%, transparent 70%),
    radial-gradient(ellipse 50% 50% at 90% 80%, rgba(122,158,142,0.07) 0%, transparent 60%);
  pointer-events: none; z-index: 0;
}

/* ── Three-column grid ── */
.cx-shell {
  position: relative; z-index: 1;
  display: grid;
  grid-template-columns: 310px 1fr 1fr;
  height: calc(100vh - 56px);
  margin-top: 56px;
  overflow: hidden;
}

/* ══════════════════════════════
   LEFT – product card
══════════════════════════════ */
.cx-left {
  background: linear-gradient(160deg, #fdf2f5 0%, #f5ede8 100%);
  border-right: 1px solid var(--border);
  display: flex; flex-direction: column; align-items: center;
  padding: 28px 22px;
  gap: 14px;
  position: relative; overflow: hidden;
}
.cx-left::after {
  content: '✿';
  position: absolute; bottom: 16px; right: 16px;
  font-size: 72px; opacity: .05; pointer-events: none; line-height: 1;
}

.cx-product-img {
  width: 100%; aspect-ratio: 1;
  border-radius: 18px; overflow: hidden;
  box-shadow: 0 10px 32px rgba(138,69,88,.18);
  flex-shrink: 0;
  max-height: 50vh;
}
.cx-product-img img { width: 100%; height: 100%; object-fit: cover; display: block; }

.cx-product-name {
  font-family: 'Cormorant Garamond', serif;
  font-size: 28px; font-weight: 600;
  color: var(--rose-dk); text-align: center; line-height: 1.2;
}
.cx-product-desc {
  font-size: 15px; color: var(--mid);
  text-align: center; line-height: 1.7;
}
.cx-base-price {
  display: inline-flex; align-items: center; gap: 6px;
  background: var(--rose-lt); color: var(--rose-dk);
  border-radius: 100px; padding: 7px 18px;
  font-size: 15px; font-weight: 500;
}

/* total pinned at bottom of left panel */
.cx-footer {
  margin-top: auto; width: 100%;
  background: #fff; border-radius: 14px;
  border: 1px solid var(--border);
  padding: 14px 20px;
}
.cx-total-label { font-size: 11px; letter-spacing: .1em; text-transform: uppercase; color: var(--mid); font-weight: 600; margin-bottom: 4px; }
.cx-total-amt   { font-family: 'Cormorant Garamond', serif; font-size: 32px; font-weight: 600; color: var(--rose-dk); line-height: 1; }

/* ══════════════════════════════
   MIDDLE – wrapper + ribbon
══════════════════════════════ */
.cx-mid {
  border-right: 1px solid var(--border);
  padding: 24px 22px;
  overflow-y: auto;
  display: flex; flex-direction: column; gap: 22px;
}

/* ══════════════════════════════
   RIGHT – add-ons
══════════════════════════════ */
.cx-right {
  padding: 24px 22px;
  overflow-y: auto;
  display: flex; flex-direction: column; gap: 22px;
}

/* ── Panel header ── */
.cx-panel-title {
  font-family: 'Cormorant Garamond', serif;
  font-size: 28px; font-weight: 600; font-style: italic;
  color: var(--warm); line-height: 1.1;
}
.cx-panel-title span {
  display: block; font-size: 12px;
  font-family: 'Cormorant Garamond', serif; font-weight: 400; font-style: italic;
  color: var(--mid); margin-top: 4px;
  letter-spacing: .06em;
}

/* ── Section label ── */
.section-label {
  display: flex; align-items: center; gap: 10px; margin-bottom: 10px;
}
.section-label h4 {
  font-size: 13px; font-weight: 600;
  letter-spacing: .12em; text-transform: uppercase; color: var(--warm);
  white-space: nowrap;
}
.price-badge {
  font-size: 11px; background: var(--gold-lt); color: var(--gold);
  border-radius: 100px; padding: 3px 10px; font-weight: 500; white-space: nowrap;
}
.section-line { flex: 1; height: 1px; background: var(--border); }

/* ── Chips ── */
.chip-grid { display: flex; flex-wrap: wrap; gap: 7px; }
.chip-grid label { cursor: pointer; }
.chip-grid input[type="radio"] { display: none; }
.chip-grid .chip {
  display: inline-block; padding: 8px 17px;
  border-radius: 100px; border: 1.5px solid var(--border);
  font-size: 14px; font-weight: 500; color: var(--warm);
  background: #fff; transition: all .18s; user-select: none;
}
.chip-grid input[type="radio"]:checked + .chip {
  background: var(--rose); border-color: var(--rose); color: #fff;
  box-shadow: 0 2px 10px rgba(201,116,138,.35);
}
.chip-grid label:hover .chip { border-color: var(--rose); color: var(--rose); }

/* ── Add-ons ── */
.addon-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.addon-row {
  display: flex; align-items: center; gap: 10px;
  padding: 12px 14px; border-radius: 12px;
  border: 1.5px solid var(--border); background: #fff;
  cursor: pointer; transition: all .18s; user-select: none;
}
.addon-row:hover { border-color: var(--rose); }
.addon-row.active { border-color: var(--rose); background: var(--rose-lt); }
.addon-row input[type="checkbox"] { display: none; }
.addon-icon {
  width: 36px; height: 36px; border-radius: 9px;
  background: var(--blush); display: flex; align-items: center;
  justify-content: center; font-size: 18px; flex-shrink: 0;
  transition: background .18s;
}
.addon-row.active .addon-icon { background: var(--rose); }
.addon-info { flex: 1; min-width: 0; }
.addon-name { font-size: 14px; font-weight: 600; color: var(--warm); display: block; }
.addon-amt  { font-size: 12.5px; color: var(--rose-dk); font-weight: 500; }

/* ── Popups ── */
.popup-overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(58,46,42,.4); backdrop-filter: blur(4px);
  z-index: 200; align-items: center; justify-content: center;
}
.popup-overlay.open { display: flex; }
.popup-box {
  background: #fff; border-radius: 20px;
  padding: 30px 26px 22px; width: 320px;
  box-shadow: 0 20px 60px rgba(58,46,42,.2);
  animation: popIn .22s ease;
}
@keyframes popIn {
  from { transform: scale(.92) translateY(10px); opacity: 0; }
  to   { transform: scale(1) translateY(0); opacity: 1; }
}
.popup-box h3 { font-family: 'Cormorant Garamond', serif; font-size: 21px; font-weight: 600; color: var(--rose-dk); margin-bottom: 6px; }
.popup-box p  { font-size: 13px; color: var(--mid); margin-bottom: 18px; line-height: 1.55; }
.popup-input  {
  width: 100%; border: 1.5px solid var(--border); border-radius: 10px;
  padding: 11px 14px; font-size: 15px; font-family: 'DM Sans', sans-serif;
  color: var(--warm); outline: none; transition: border-color .15s; margin-bottom: 14px;
}
.popup-input:focus { border-color: var(--rose); }
.popup-actions { display: flex; gap: 10px; }
.btn-ok {
  flex: 1; background: var(--rose); color: #fff; border: none;
  border-radius: 10px; padding: 13px; font-size: 14px; font-weight: 600;
  cursor: pointer; font-family: 'DM Sans', sans-serif; transition: background .15s;
}
.btn-ok:hover { background: var(--rose-dk); }
.btn-cancel {
  background: var(--blush); color: var(--mid); border: none;
  border-radius: 10px; padding: 13px 18px; font-size: 14px; font-weight: 600;
  cursor: pointer; font-family: 'DM Sans', sans-serif; transition: background .15s;
}
.btn-cancel:hover { background: var(--rose-lt); color: var(--rose-dk); }

/* ── Submit button — fixed to bottom-right corner ── */
.cx-submit {
  position: fixed;
  bottom: 28px;
  right: 28px;
  z-index: 100;
  background: var(--rose); color: #fff; border: none;
  border-radius: 14px; padding: 16px 32px;
  font-size: 16px; font-weight: 600; font-family: 'DM Sans', sans-serif;
  cursor: pointer; white-space: nowrap;
  box-shadow: 0 6px 24px rgba(201,116,138,.45);
  transition: background .18s, transform .12s, box-shadow .18s;
  letter-spacing: .02em;
}
.cx-submit:hover  { background: var(--rose-dk); box-shadow: 0 8px 28px rgba(138,69,88,.45); transform: translateY(-2px); }
.cx-submit:active { transform: scale(.97); }

/* ── Scrollbar styling ── */
.cx-mid::-webkit-scrollbar, .cx-right::-webkit-scrollbar { width: 4px; }
.cx-mid::-webkit-scrollbar-track, .cx-right::-webkit-scrollbar-track { background: transparent; }
.cx-mid::-webkit-scrollbar-thumb, .cx-right::-webkit-scrollbar-thumb { background: var(--border); border-radius: 99px; }

/* ── Responsive fallback ── */
@media (max-width: 820px) {
  html, body { overflow: auto; }
  .cx-shell { grid-template-columns: 1fr; height: auto; }
  .cx-left  { border-right: none; border-bottom: 1px solid var(--border); }
  .cx-mid   { border-right: none; border-bottom: 1px solid var(--border); overflow-y: visible; }
  .cx-right { overflow-y: visible; }
  .addon-grid { grid-template-columns: 1fr; }
}
</style>
<?php include __DIR__ . '/pwa_head.php'; ?>
</head>
<body>

<?php
  $categoryName = htmlspecialchars($product['category_name'] ?? 'Shop');
  $productName  = htmlspecialchars($product['name']);
  $categoryLink = 'index.php?category=' . urlencode($product['category_name'] ?? '') . '#products';
?>
<nav class="cx-breadcrumb">
  <a href="index.php">Home</a>
  <span class="sep">›</span>
  <a href="index.php?category=all#products">Shop</a>
  <span class="sep">›</span>
  <a href="<?= $categoryLink ?>"><?= $categoryName ?></a>
  <span class="sep">›</span>
  <span class="current"><?= $productName ?></span>
</nav>

<form method="POST" action="add_to_cart.php">
<input type="hidden" name="product_id"     value="<?= $product['id'] ?>">
<input type="hidden" name="total"           id="totalInput">
<input type="hidden" name="wrapper_name"    id="wrapperName">
<input type="hidden" name="ribbon_name"     id="ribbonName">
<input type="hidden" name="addons_data"     id="addonsData">
<input type="hidden" name="letter_message"  id="letterMessageInput">

<div class="cx-shell">

  <!-- ══ LEFT: product info + total ══ -->
  <aside class="cx-left">
    <div class="cx-product-img">
      <img src="images/<?= htmlspecialchars($product['image']) ?>"
           alt="<?= htmlspecialchars($product['name']) ?>">
    </div>

    <div class="cx-product-name"><?= htmlspecialchars($product['name']) ?></div>
    <div class="cx-product-desc"><?= htmlspecialchars($product['description']) ?></div>
    <div class="cx-base-price">✦ Base: ₱<?= number_format($product['price'], 2) ?></div>

    <div class="cx-footer">
      <div class="cx-total-label">Total</div>
      <div class="cx-total-amt">₱<span id="total"><?= number_format($product['price'], 2) ?></span></div>
    </div>
  </aside>

  <!-- ══ MIDDLE: wrapper + ribbon ══ -->
  <section class="cx-mid">
    <div class="cx-panel-title">
      Customize Your Bouquet
      <span>Make it uniquely yours</span>
    </div>

    <!-- Wrapper -->
    <div>
      <div class="section-label">
        <h4>Wrapper</h4>
        <div class="price-badge">+₱50.00</div>
        <div class="section-line"></div>
      </div>
      <div class="chip-grid">
        <?php
        $wrappers = ['Pink/Purple','Black/White','Gold/White','Red','Orange','Yellow','Green','Blue','Purple'];
        foreach ($wrappers as $w): ?>
          <label>
            <input type="radio" name="wrapper" value="50" data-name="<?= $w ?>">
            <span class="chip"><?= $w ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Ribbon -->
    <div>
      <div class="section-label">
        <h4>Ribbon</h4>
        <div class="price-badge">+₱25.00</div>
        <div class="section-line"></div>
      </div>
      <div class="chip-grid">
        <?php
        $ribbons = ['Pink','Blue','Red','Orange','Yellow','Green','Purple','Black','White','Gold'];
        foreach ($ribbons as $r): ?>
          <label>
            <input type="radio" name="ribbon" value="25" data-name="<?= $r ?>">
            <span class="chip"><?= $r ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ══ RIGHT: add-ons ══ -->
  <section class="cx-right">
    <div class="cx-panel-title">
      Add-ons
      <span>Personalize even more</span>
    </div>

    <div class="addon-grid">

      <label class="addon-row" id="row-Chocolate">
        <input type="checkbox" class="addon-check" data-name="Chocolate">
        <div class="addon-icon">🍫</div>
        <div class="addon-info">
          <span class="addon-name">Chocolate</span>
          <span class="addon-amt" id="price-Chocolate">Set budget</span>
        </div>
      </label>

      <label class="addon-row" id="row-Makeup">
        <input type="checkbox" class="addon-check" data-name="Makeup">
        <div class="addon-icon">💄</div>
        <div class="addon-info">
          <span class="addon-name">Makeup</span>
          <span class="addon-amt" id="price-Makeup">Set budget</span>
        </div>
      </label>

      <label class="addon-row" id="row-Money">
        <input type="checkbox" class="addon-check" data-name="Money">
        <div class="addon-icon">💵</div>
        <div class="addon-info">
          <span class="addon-name">Money</span>
          <span class="addon-amt" id="price-Money">Set budget</span>
        </div>
      </label>

      <label class="addon-row" id="row-Random">
        <input type="checkbox" class="addon-check" data-name="Random">
        <div class="addon-icon">🎁</div>
        <div class="addon-info">
          <span class="addon-name">Random</span>
          <span class="addon-amt" id="price-Random">Set budget</span>
        </div>
      </label>

      <label class="addon-row" id="row-LED">
        <input type="checkbox" class="addon-check" data-name="LED">
        <div class="addon-icon">✨</div>
        <div class="addon-info">
          <span class="addon-name">LED Lights</span>
          <span class="addon-amt" id="price-LED">₱15 each</span>
        </div>
      </label>

      <label class="addon-row" id="row-Letter">
        <input type="checkbox" class="addon-check" data-name="Letter" data-type="letter">
        <div class="addon-icon">💌</div>
        <div class="addon-info">
          <span class="addon-name">Letter</span>
          <span class="addon-amt" id="price-Letter">Add message</span>
        </div>
      </label>

    </div>

    <button type="submit" class="cx-submit">Add to Cart →</button>

  </section>

</div><!-- .cx-shell -->
</form>

<!-- ── Budget popup ── -->
<div id="addonPopup" class="popup-overlay">
  <div class="popup-box">
    <h3 id="addonTitle">Set Budget</h3>
    <p>Enter your preferred budget for this add-on. The amount will be added to your total.</p>
    <input type="number" id="addonPrice" placeholder="₱ amount" class="popup-input" min="1">
    <div class="popup-actions">
      <button type="button" onclick="saveAddon()" class="btn-ok">Confirm</button>
      <button type="button" onclick="closePopup()" class="btn-cancel">Cancel</button>
    </div>
  </div>
</div>

<!-- ── Letter popup ── -->
<div id="letterPopup" class="popup-overlay">
  <div class="popup-box">
    <h3>💌 Write Your Message</h3>
    <p>Enter the message you'd like to include in the letter for your receiver.</p>
    <textarea id="letterMessage" placeholder="e.g. Happy Birthday! Wishing you all the best…"
      class="popup-input" rows="4"
      style="resize:vertical;height:100px;padding:10px;font-family:inherit;font-size:14px;"></textarea>
    <div class="popup-actions">
      <button type="button" onclick="saveLetter()" class="btn-ok">Confirm</button>
      <button type="button" onclick="closeLetter()" class="btn-cancel">Cancel</button>
    </div>
  </div>
</div>

<script>
let base = <?= floatval($product['price']) ?>;
let addons = {};
let currentAddon = null;

const totalText    = document.getElementById("total");
const totalInput   = document.getElementById("totalInput");
const wrapperName  = document.getElementById("wrapperName");
const ribbonName   = document.getElementById("ribbonName");
const addonsData   = document.getElementById("addonsData");
const popup        = document.getElementById("addonPopup");
const addonTitle   = document.getElementById("addonTitle");
const addonPrice   = document.getElementById("addonPrice");

function update() {
  let total = base;
  let w = document.querySelector("input[name=wrapper]:checked");
  let r = document.querySelector("input[name=ribbon]:checked");

  wrapperName.value = w ? w.dataset.name : "none";
  ribbonName.value  = r ? r.dataset.name : "none";
  if (w) total += parseFloat(w.value);
  if (r) total += parseFloat(r.value);
  for (let k in addons) total += addons[k];

  totalInput.value   = total;
  totalText.innerText = total.toFixed(2);
  addonsData.value   = JSON.stringify(addons);
}

document.querySelectorAll(".addon-check").forEach(cb => {
  const row = cb.closest(".addon-row");

  cb.addEventListener("change", function () {
    currentAddon = this;
    if (this.checked) {
      if (this.dataset.type === "letter") {
        openLetterPopup(this);
      } else {
        addonTitle.innerText = this.dataset.name;
        addonPrice.value = addons[this.dataset.name] || "";
        popup.classList.add("open");
      }
    } else {
      delete addons[this.dataset.name];
      row.classList.remove("active");
      if (this.dataset.type === "letter") {
        letterMsgInput.value = "";
        document.getElementById("price-Letter").innerText = "Add message";
      } else {
        document.getElementById("price-" + this.dataset.name).innerText = "Set budget";
      }
      update();
    }
  });

  row.addEventListener("click", function (e) {
    if (cb.checked && e.target.tagName !== "INPUT") {
      currentAddon = cb;
      if (cb.dataset.type === "letter") {
        openLetterPopup(cb);
      } else {
        addonTitle.innerText = "Edit – " + cb.dataset.name;
        addonPrice.value = addons[cb.dataset.name] || "";
        popup.classList.add("open");
      }
    }
  });
});

function saveAddon() {
  let price = parseFloat(addonPrice.value);
  if (isNaN(price) || price <= 0) { alert("Please enter a valid amount."); return; }
  const name = currentAddon.dataset.name;
  addons[name] = price;
  document.getElementById("price-" + name).innerText = "₱" + price.toFixed(2);
  document.getElementById("row-" + name).classList.add("active");
  popup.classList.remove("open");
  update();
}

function closePopup() {
  popup.classList.remove("open");
  if (currentAddon && !addons[currentAddon.dataset.name]) {
    currentAddon.checked = false;
    document.getElementById("row-" + currentAddon.dataset.name).classList.remove("active");
  }
}

// ── Letter popup ──
const letterPopup    = document.getElementById("letterPopup");
const letterMsgBox   = document.getElementById("letterMessage");
const letterMsgInput = document.getElementById("letterMessageInput");
let letterAddon = null;

function openLetterPopup(cb) {
  letterAddon = cb;
  letterMsgBox.value = letterMsgInput.value || "";
  letterPopup.classList.add("open");
  setTimeout(() => letterMsgBox.focus(), 100);
}

function saveLetter() {
  const msg = letterMsgBox.value.trim();
  if (!msg) { alert("Please enter a message for your receiver."); return; }
  letterMsgInput.value = msg;
  addons["Letter"] = 0;
  document.getElementById("price-Letter").innerText = "✓ Message added";
  document.getElementById("row-Letter").classList.add("active");
  letterPopup.classList.remove("open");
  update();
}

function closeLetter() {
  letterPopup.classList.remove("open");
  if (letterAddon && !addons["Letter"]) {
    letterAddon.checked = false;
    document.getElementById("row-Letter").classList.remove("active");
    document.getElementById("price-Letter").innerText = "Add message";
  }
}

letterPopup.addEventListener("click", e => { if (e.target === letterPopup) closeLetter(); });
letterMsgBox.addEventListener("keydown", e => { if (e.key === "Enter" && e.ctrlKey) { e.preventDefault(); saveLetter(); } });
addonPrice.addEventListener("keydown", e => { if (e.key === "Enter") { e.preventDefault(); saveAddon(); } });
popup.addEventListener("click", e => { if (e.target === popup) closePopup(); });
document.querySelectorAll("input[type=radio]").forEach(el => el.addEventListener("change", update));

update();
</script>
</body>
</html>