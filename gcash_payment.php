<?php
// ─────────────────────────────────────────────────────────────────────────────
// gcash_payment.php  –  Landscape no-scroll redesign
// KEY CHANGE: Order data comes from $_SESSION['pending_gcash_order'],
//             not from the database. Nothing is stored in DB until the
//             customer submits proof in save_gcash_proof.php.
// ─────────────────────────────────────────────────────────────────────────────
session_start();
include "config.php";

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$uid     = intval($_SESSION['user_id']);
$pending = $_SESSION['pending_gcash_order'] ?? null;

// Guard: must have a pending order in session that belongs to this user
if (!$pending || $pending['uid'] !== $uid) {
    header("Location: cart.php"); exit();
}

// Expire stale sessions older than 2 hours
if (time() - ($pending['created_at'] ?? 0) > 7200) {
    unset($_SESSION['pending_gcash_order']);
    header("Location: cart.php"); exit();
}

$total         = $pending['total'];
$delivery_mode = $pending['delivery_mode'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GCash Payment — SB Blooms</title>
<link rel="icon" type="image/png" href="images/logo/SB.png">
<link rel="shortcut icon" type="image/png" href="images/logo/SB.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
/* ── Reset ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --gcash-blue:  #0070e0;
  --gcash-light: #00b4f5;
  --gcash-dark:  #004fa3;
  --cream:       #fdf8f4;
  --warm-white:  #ffffff;
  --ink:         #1a1a2e;
  --muted:       #7c7c9a;
  --pink-soft:   #fdf0f5;
  --pink-border: #f4b8d3;
  --pink-text:   #c2185b;
  --gold:        #e8a020;
  --gold-bg:     #fffbeb;
  --gold-border: #fde68a;
  --green:       #16a34a;
  --green-bg:    #f0fdf4;
  --green-bd:    #86efac;
  --red:         #dc2626;
}

html, body {
  height: 100%;
  overflow: hidden;            /* ← the key — no scroll on landscape */
  font-family: 'DM Sans', sans-serif;
  background: var(--cream);
  color: var(--ink);
}

/* ── Two-column shell ── */
.gp-shell {
  display: grid;
  grid-template-columns: 420px 1fr;
  height: 100vh;
  overflow: hidden;
}

/* ════════════════════════════════
   LEFT PANEL  – GCash info
════════════════════════════════ */
.gp-left {
  background: linear-gradient(160deg, #004fa3 0%, #0070e0 55%, #00b4f5 100%);
  display: flex;
  flex-direction: column;
  padding: 32px 28px;
  position: relative;
  overflow: hidden;
  color: #fff;
}

/* decorative blobs */
.gp-left::before, .gp-left::after {
  content: '';
  position: absolute;
  border-radius: 50%;
  opacity: .12;
  pointer-events: none;
}
.gp-left::before { width: 320px; height: 320px; background: #fff; top: -80px; right: -100px; }
.gp-left::after  { width: 200px; height: 200px; background: #fff; bottom: -50px; left: -60px; }

/* brand */
.gp-brand {
  display: flex; align-items: center; gap: 10px;
  margin-bottom: 24px;
  position: relative; z-index: 1;
}
.gp-brand-icon {
  width: 40px; height: 40px; background: rgba(255,255,255,.22);
  border-radius: 12px; display: flex; align-items: center;
  justify-content: center; font-size: 20px;
  backdrop-filter: blur(4px);
}
.gp-brand-name { font-size: 15px; font-weight: 700; opacity: .95; }
.gp-brand-tag  { font-size: 10px; font-weight: 500; opacity: .65; text-transform: uppercase; letter-spacing: .1em; }

/* steps */
.gp-steps {
  display: flex; align-items: center; gap: 0;
  margin-bottom: 20px; position: relative; z-index: 1;
}
.gp-step { display: flex; flex-direction: column; align-items: center; flex: 1; text-align: center; gap: 5px; }
.gp-step-circle {
  width: 32px; height: 32px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 12px; font-weight: 700;
  background: rgba(255,255,255,.15); color: rgba(255,255,255,.6); border: 2px solid rgba(255,255,255,.25);
}
.gp-step.done   .gp-step-circle { background: rgba(255,255,255,.3); color: #fff; border-color: rgba(255,255,255,.6); }
.gp-step.active .gp-step-circle { background: #fff; color: var(--gcash-blue); border-color: #fff; box-shadow: 0 0 0 5px rgba(255,255,255,.2); }
.gp-step-label  { font-size: 9px; font-weight: 600; opacity: .55; text-transform: uppercase; letter-spacing: .07em; }
.gp-step.done .gp-step-label, .gp-step.active .gp-step-label { opacity: 1; }
.gp-step-line   { flex: 1; height: 2px; background: rgba(255,255,255,.2); max-width: 36px; margin-bottom: 18px; }
.gp-step-line.done { background: rgba(255,255,255,.55); }

/* order badge */
.gp-order-badge {
  background: rgba(255,255,255,.14); backdrop-filter: blur(6px);
  border: 1.5px solid rgba(255,255,255,.25);
  border-radius: 14px; padding: 12px 16px;
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 18px; position: relative; z-index: 1;
}
.gp-order-badge-label { font-size: 10px; opacity: .7; text-transform: uppercase; letter-spacing: .1em; font-weight: 600; }
.gp-order-badge-no    { font-size: 15px; font-weight: 700; margin-top: 2px; }
.gp-order-badge-amt   { font-size: 26px; font-weight: 800; line-height: 1; }
.gp-order-badge-amtl  { font-size: 9px; font-weight: 600; opacity: .6; text-transform: uppercase; letter-spacing: .1em; text-align: right; margin-top: 2px; }

/* gcash account card */
.gcash-acct {
  background: rgba(255,255,255,.13); backdrop-filter: blur(8px);
  border: 1.5px solid rgba(255,255,255,.2);
  border-radius: 16px; padding: 16px 18px;
  position: relative; z-index: 1; flex: 1;
  display: flex; flex-direction: column; gap: 10px;
}
.gcash-acct-title {
  font-size: 9px; font-weight: 700; letter-spacing: .12em;
  text-transform: uppercase; opacity: .65; margin-bottom: 2px;
}
.gcash-acct-row {
  background: rgba(255,255,255,.12); border-radius: 10px;
  padding: 10px 14px;
  display: flex; align-items: center; justify-content: space-between;
}
.gar-label { font-size: 9px; font-weight: 600; opacity: .65; text-transform: uppercase; letter-spacing: .08em; margin-bottom: 3px; }
.gar-value { font-size: 17px; font-weight: 800; letter-spacing: .02em; }
.gar-copy  {
  background: rgba(255,255,255,.22); color: #fff;
  border: 1.5px solid rgba(255,255,255,.35);
  border-radius: 7px; padding: 5px 12px;
  font-size: 11px; font-weight: 700; cursor: pointer;
  transition: background .15s; flex-shrink: 0;
}
.gar-copy:hover  { background: rgba(255,255,255,.38); }
.gar-copy.copied { background: rgba(34,197,94,.5); border-color: transparent; }

/* trust strip */
.gp-trust {
  display: flex; align-items: flex-start; gap: 8px;
  background: rgba(255,255,255,.1); border-radius: 10px;
  padding: 10px 12px; position: relative; z-index: 1; margin-top: 4px;
}
.gp-trust-icon { font-size: 16px; flex-shrink: 0; margin-top: 1px; }
.gp-trust p    { font-size: 10px; opacity: .85; line-height: 1.6; }
.gp-trust strong { display: block; opacity: 1; font-weight: 700; margin-bottom: 2px; }

/* ════════════════════════════════
   RIGHT PANEL  – proof form
════════════════════════════════ */
.gp-right {
  display: flex; flex-direction: column;
  padding: 32px 36px;
  overflow: hidden;
  background: var(--cream);
}

.gp-right-header { margin-bottom: 22px; }
.gp-right-header h1 {
  font-family: 'DM Serif Display', serif;
  font-size: clamp(22px, 2.4vw, 30px);
  color: var(--ink); line-height: 1.15;
}
.gp-right-header h1 em { font-style: italic; color: var(--gcash-blue); }
.gp-right-header p { font-size: 13px; color: var(--muted); margin-top: 5px; line-height: 1.6; }

/* section label */
.gp-section-label {
  font-size: 10px; font-weight: 700; color: var(--gcash-blue);
  letter-spacing: .12em; text-transform: uppercase;
  margin-bottom: 8px;
}

/* form layout */
.gp-form-body { display: flex; flex-direction: column; gap: 14px; flex: 1; }

/* reference input */
.gp-field { display: flex; flex-direction: column; gap: 6px; }
.gp-label {
  font-size: 11px; font-weight: 600; color: var(--gcash-blue);
  text-transform: uppercase; letter-spacing: .09em;
}
.gp-required { color: var(--red); }
.gp-input {
  width: 100%; padding: 12px 15px;
  border: 1.5px solid #bfdbfe; border-radius: 12px;
  font-size: 15px; font-family: 'DM Sans', sans-serif;
  background: #f8fbff; color: #1e3a8a;
  transition: border-color .18s, box-shadow .18s; outline: none;
}
.gp-input:focus  { border-color: var(--gcash-blue); box-shadow: 0 0 0 3px rgba(0,112,224,.12); }
.gp-input.valid  { border-color: var(--green); background: var(--green-bg); }
.gp-input.invalid{ border-color: var(--red); background: #fff5f5; }
.gp-hint { font-size: 11px; min-height: 15px; }
.gp-hint.ok  { color: var(--green); }
.gp-hint.err { color: var(--red); }

/* upload row: upload box + preview side-by-side */
.gp-upload-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px; align-items: start;
}
.gp-upload-area {
  border: 2px dashed #93c5fd; border-radius: 14px;
  padding: 22px 14px; text-align: center; cursor: pointer;
  background: #f8fbff;
  transition: border-color .18s, background .18s;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  min-height: 110px;
}
.gp-upload-area:hover      { border-color: var(--gcash-blue); background: #eff6ff; }
.gp-upload-area.has-file   { border-color: var(--green); background: var(--green-bg); }
.gp-upload-icon   { font-size: 26px; margin-bottom: 6px; }
.gp-upload-text   { font-size: 12px; font-weight: 600; color: var(--gcash-blue); margin-bottom: 2px; }
.gp-upload-hint   { font-size: 10px; color: var(--muted); }

.gp-preview-wrap {
  position: relative; display: none;
  border-radius: 12px; overflow: hidden;
  border: 1.5px solid var(--green-bd);
  min-height: 110px;
}
.gp-preview-wrap img {
  width: 100%; height: 100%; max-height: 140px;
  object-fit: cover; display: block;
}
.gp-remove-btn {
  position: absolute; top: 6px; right: 6px;
  background: rgba(0,0,0,.55); color: #fff;
  border: none; border-radius: 7px; padding: 3px 10px;
  font-size: 11px; font-weight: 700; cursor: pointer;
}
.gp-remove-btn:hover { background: rgba(220,38,38,.75); }

/* submit */
.gp-submit {
  width: 100%; padding: 14px;
  background: var(--gcash-blue); color: #fff;
  border: none; border-radius: 14px;
  font-size: 15px; font-weight: 700; font-family: 'DM Sans', sans-serif;
  cursor: pointer; letter-spacing: .02em;
  transition: background .18s, transform .12s, box-shadow .18s;
  box-shadow: 0 4px 18px rgba(0,112,224,.3);
  margin-top: 2px;
}
.gp-submit:disabled { background: #93c5fd; cursor: not-allowed; box-shadow: none; }
.gp-submit:not(:disabled):hover { background: var(--gcash-dark); transform: translateY(-1px); box-shadow: 0 6px 22px rgba(0,112,224,.4); }
.gp-submit:not(:disabled):active { transform: translateY(0); }

.gp-note {
  font-size: 11px; color: var(--muted); text-align: center;
  line-height: 1.6; margin-top: 4px;
}
.gp-note strong { color: var(--ink); }

/* ── Responsive fallback: stack on small/portrait screens ── */
@media (max-width: 768px) {
  html, body { overflow: auto; }
  .gp-shell  { grid-template-columns: 1fr; height: auto; }
  .gp-left   { min-height: auto; }
  .gp-right  { overflow: visible; }
  .gp-upload-row { grid-template-columns: 1fr; }
}
</style>
<?php include __DIR__ . '/pwa_head.php'; ?>
</head>
<body>

<?php include "header.php"; ?>

<div class="gp-shell">

  <!-- ════ LEFT: Payment Info ════ -->
  <aside class="gp-left">

    <div class="gp-brand">
      <div class="gp-brand-icon"><img src="images/logo/Gcash.png" alt="GCash" style="width:26px;height:26px;object-fit:contain;filter:brightness(0) invert(1);"></div>
      <div>
        <div class="gp-brand-name">Precious Satin Bouquet</div>
        <div class="gp-brand-tag">Secure GCash Checkout</div>
      </div>
    </div>

    <!-- Step tracker -->
    <div class="gp-steps">
      <div class="gp-step done">
        <div class="gp-step-circle">✓</div>
        <div class="gp-step-label">Order</div>
      </div>
      <div class="gp-step-line done"></div>
      <div class="gp-step active">
        <div class="gp-step-circle">2</div>
        <div class="gp-step-label">Pay</div>
      </div>
      <div class="gp-step-line"></div>
      <div class="gp-step">
        <div class="gp-step-circle">3</div>
        <div class="gp-step-label">Verified</div>
      </div>
    </div>

    <!-- Order summary -->
    <div class="gp-order-badge">
      <div>
        <div class="gp-order-badge-label">🌸 Order</div>
        <div class="gp-order-badge-no">#<?= $uid . '-pending' ?></div>
      </div>
      <div style="text-align:right">
        <div class="gp-order-badge-amt">₱<?= number_format($total, 2) ?></div>
        <div class="gp-order-badge-amtl">Total to send</div>
      </div>
    </div>

    <!-- GCash account -->
    <div class="gcash-acct">
      <div class="gcash-acct-title"><img src="images/logo/Gcash.png" alt="GCash" style="height:14px;vertical-align:middle;margin-right:5px;filter:brightness(0) invert(1);"> Send payment to</div>

      <div class="gcash-acct-row">
        <div>
          <div class="gar-label">GCash Number</div>
          <div class="gar-value">0967 987 7267</div>
        </div>
        <button type="button" class="gar-copy" onclick="copyText(this,'09679877267')">Copy</button>
      </div>

      <div class="gcash-acct-row">
        <div>
          <div class="gar-label">Account Name</div>
          <div class="gar-value">Plinky Quinto</div>
        </div>
        <button type="button" class="gar-copy" onclick="copyText(this,'Plinky Quinto')">Copy</button>
      </div>

      <!-- Trust note -->
      <div class="gp-trust">
        <div class="gp-trust-icon">🛡️</div>
        <p><strong>100% Safe &amp; Verified</strong>We only process after admin verification. You will never be asked to pay outside this page.</p>
      </div>
    </div>

  </aside>

  <!-- ════ RIGHT: Proof Form ════ -->
  <main class="gp-right">

    <div class="gp-right-header">
      <h1>Submit Your <em>Payment Proof</em></h1>
      <p>Enter your GCash reference number and upload your receipt screenshot below.</p>
    </div>

    <form method="POST" action="save_gcash_proof.php" id="gcashProofForm"
          enctype="multipart/form-data" class="gp-form-body">
      <input type="hidden" name="order_id" value="">

      <!-- Reference number -->
      <div class="gp-field">
        <label class="gp-label" for="gcashRef">
          GCash Reference Number <span class="gp-required">*</span>
        </label>
        <input
          type="text" id="gcashRef" name="gcash_ref"
          class="gp-input" maxlength="20"
          placeholder="e.g. 1234567890123"
          autocomplete="off" inputmode="numeric"
          oninput="validateRef(this)"
        >
        <span class="gp-hint" id="refHint"></span>
      </div>

      <!-- Screenshot upload + preview side by side -->
      <div class="gp-field">
        <label class="gp-label" for="gcashScreenshot">
          Payment Screenshot / Receipt <span class="gp-required">*</span>
        </label>
        <div class="gp-upload-row">
          <div class="gp-upload-area" id="uploadArea"
               onclick="document.getElementById('gcashScreenshot').click()">
            <div class="gp-upload-icon">📷</div>
            <div class="gp-upload-text">Tap to upload receipt</div>
            <div class="gp-upload-hint">JPG, PNG, WEBP · Max 5 MB</div>
          </div>
          <div class="gp-preview-wrap" id="previewWrap">
            <img id="previewImg" src="" alt="Receipt preview">
            <button type="button" class="gp-remove-btn" onclick="removeFile()">✕ Remove</button>
          </div>
        </div>
        <input type="file" id="gcashScreenshot" name="gcash_screenshot"
               accept="image/jpeg,image/png,image/webp"
               style="display:none" onchange="previewFile(this)">
        <span class="gp-hint" id="ssHint"></span>
      </div>

      <button type="submit" class="gp-submit" id="submitBtn" disabled>
        <img src="images/logo/Gcash.png" alt="GCash" style="height:18px;vertical-align:middle;margin-right:6px;filter:brightness(0) invert(1);"> Submit Payment Proof
      </button>

      <p class="gp-note">⏳ Status: <strong>Pending Verification</strong> — admin will confirm your payment shortly.</p>
    </form>

  </main>

</div><!-- .gp-shell -->

<script>
let refOk = false, ssOk = false;

function updateSubmit() {
  document.getElementById('submitBtn').disabled = !(refOk && ssOk);
}

function copyText(btn, text) {
  navigator.clipboard.writeText(text).then(() => {
    btn.textContent = 'Copied!'; btn.classList.add('copied');
    setTimeout(() => { btn.textContent = 'Copy'; btn.classList.remove('copied'); }, 2000);
  });
}

function validateRef(input) {
  const hint = document.getElementById('refHint');
  const val  = input.value.replace(/\D/g, '');
  input.value = val;
  if (!val.length) {
    input.className = 'gp-input'; hint.textContent = ''; hint.className = 'gp-hint'; refOk = false;
  } else if (val.length < 8) {
    input.className = 'gp-input invalid';
    hint.textContent = 'Too short — GCash refs are usually 13 digits';
    hint.className = 'gp-hint err'; refOk = false;
  } else {
    input.className = 'gp-input valid';
    hint.textContent = '✔ Looks good!'; hint.className = 'gp-hint ok'; refOk = true;
  }
  updateSubmit();
}

function previewFile(input) {
  const hint = document.getElementById('ssHint');
  const area = document.getElementById('uploadArea');
  const wrap = document.getElementById('previewWrap');
  const img  = document.getElementById('previewImg');
  if (!input.files?.[0]) return;
  const file = input.files[0];
  if (file.size > 5 * 1024 * 1024) {
    hint.textContent = '⚠️ File too large. Max 5 MB.';
    hint.className = 'gp-hint err'; input.value = ''; ssOk = false; updateSubmit(); return;
  }
  const reader = new FileReader();
  reader.onload = e => {
    img.src = e.target.result;
    wrap.style.display = 'block';
    area.classList.add('has-file');
    area.querySelector('.gp-upload-text').textContent = '✔ ' + file.name;
    hint.textContent = '✔ Screenshot ready!'; hint.className = 'gp-hint ok';
    ssOk = true; updateSubmit();
  };
  reader.readAsDataURL(file);
}

function removeFile() {
  const input = document.getElementById('gcashScreenshot');
  const area  = document.getElementById('uploadArea');
  const wrap  = document.getElementById('previewWrap');
  const hint  = document.getElementById('ssHint');
  input.value = ''; wrap.style.display = 'none';
  area.classList.remove('has-file');
  area.querySelector('.gp-upload-text').textContent = 'Tap to upload receipt';
  hint.textContent = ''; hint.className = 'gp-hint'; ssOk = false; updateSubmit();
}

// ── State flags ──
let formSubmitted = false;

document.getElementById('gcashProofForm').addEventListener('submit', function() {
  formSubmitted = true; // set BEFORE page starts unloading
  const btn = document.getElementById('submitBtn');
  btn.disabled = true; btn.textContent = '⏳ Submitting…';
});

// ── Push dummy state so back button hits us first ──
history.pushState({ page: 'gcash' }, '');

window.addEventListener('popstate', function() {
  if (formSubmitted) return;
  document.getElementById('backModal').style.display = 'flex';
  history.pushState({ page: 'gcash' }, '');
});

function closeBackModal() {
  document.getElementById('backModal').style.display = 'none';
}

function forceBack() {
  // No DB record was ever created, so just navigate away — session clears itself
  window.location.href = 'index.php';
}
</script>

<!-- ── Back-navigation warning popup ── -->
<div id="backModal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,.48); backdrop-filter:blur(5px); align-items:center; justify-content:center;">
  <div style="background:#fff; border-radius:22px; padding:36px 30px; max-width:370px; width:90%; text-align:center; box-shadow:0 24px 64px rgba(0,0,0,.22); animation:popIn .22s cubic-bezier(.34,1.56,.64,1);">
    <div style="font-size:52px; margin-bottom:12px;">⚠️</div>
    <h2 style="font-family:'DM Serif Display',serif; font-size:21px; color:#1a1a2e; margin-bottom:10px; line-height:1.2;">Payment Not Yet Submitted</h2>
    <p style="font-size:13px; color:#7c7c9a; line-height:1.7; margin-bottom:24px;">
      Sorry, your order cannot be placed yet.<br>
      Please provide your <strong style="color:#0070e0;">GCash reference number</strong> and upload your <strong style="color:#0070e0;">receipt screenshot</strong> before leaving this page.
    </p>
    <div style="display:flex; gap:10px;">
      <button onclick="closeBackModal()" style="flex:1; padding:13px 0; background:#0070e0; color:#fff; border:none; border-radius:13px; font-size:14px; font-weight:700; cursor:pointer; font-family:'DM Sans',sans-serif; box-shadow:0 4px 14px rgba(0,112,224,.3);">
        Stay &amp; Complete
      </button>
      <button onclick="forceBack()" style="flex:1; padding:13px 0; background:#f3f4f6; color:#6b7280; border:none; border-radius:13px; font-size:14px; font-weight:600; cursor:pointer; font-family:'DM Sans',sans-serif;">
        Leave Anyway
      </button>
    </div>
  </div>
</div>
<style>
@keyframes popIn {
  from { transform:scale(.85); opacity:0; }
  to   { transform:scale(1);   opacity:1; }
}
</style>

</body>
</html>