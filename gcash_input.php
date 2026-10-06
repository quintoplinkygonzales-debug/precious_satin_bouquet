<?php
// ─────────────────────────────────────────────────────────────────────────────
// gcash_input.php
// USAGE: <?php include 'gcash_input.php'; ?> inside your checkout.php,
//        placed inside the GCash payment box section.
//
// REQUIRES in checkout.php:
//   - A hidden/select input with id="fPayment" that holds the payment method
//   - A form with id="checkoutForm"
//   - The gcashBox div toggled by your existing payment-selection logic
// ─────────────────────────────────────────────────────────────────────────────

// Read current payment method for initial show/hide (set in checkout.php before including this)
// Defaults to 'gcash' if not set so the box shows during first load when gcash is default.
$payment_method = $payment_method ?? 'gcash';
?>

<!-- ══════════════════════════════════════════════════════
     GCash Payment Box — include inside your gcashBox div
     ══════════════════════════════════════════════════════ -->
<div class="gcash-box <?= $payment_method === 'gcash' ? 'show' : '' ?>" id="gcashBox">

  <!-- Payment instructions -->
  <div class="gcash-instructions">
    <div class="gcash-inst-inner">
      <p class="gcash-inst-title">💙 How to Pay via GCash</p>
      <ol class="gcash-steps">
        <li>Open your <strong>GCash app</strong> and tap <strong>Send Money</strong></li>
        <li>Send to: <strong class="gcash-number">09XX-XXX-XXXX</strong></li>
        <li>Copy the <strong>13-digit reference number</strong> from your GCash receipt</li>
        <li>Paste it in the field below and place your order</li>
      </ol>
      <p class="gcash-note">⏳ Your order will be marked <em>Pending Verification</em> until the admin confirms your payment.</p>
    </div>
  </div>

  <!-- Reference number input -->
  <div class="gcash-ref-wrap">
    <label class="gcash-ref-label" for="gcashRefInput">
      📋 GCash Reference Number <span class="gcash-required">*</span>
    </label>
    <input
      type="text"
      id="gcashRefInput"
      name="gcash_ref"
      maxlength="20"
      placeholder="e.g. 1234567890123"
      autocomplete="off"
      inputmode="numeric"
      oninput="validateGcashRef(this)"
    >
    <span class="gcash-ref-hint" id="gcashRefHint"></span>
  </div>

</div><!-- /gcashBox -->

<!-- ══════════════════════════════════════════════════════
     Styles
     ══════════════════════════════════════════════════════ -->
<style>
/* Box visibility (your existing JS toggles .show) */
.gcash-box { display: none; }
.gcash-box.show { display: block; }

/* Instructions card */
.gcash-inst-inner {
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 12px;
  padding: 16px 18px;
  margin-bottom: 16px;
}
.gcash-inst-title {
  font-weight: 700;
  font-size: 13px;
  color: #1d4ed8;
  margin-bottom: 10px;
}
.gcash-steps {
  padding-left: 18px;
  margin: 0 0 10px;
  font-size: 13px;
  color: #334155;
  line-height: 1.7;
}
.gcash-number {
  color: #1d4ed8;
  letter-spacing: .04em;
}
.gcash-note {
  font-size: 12px;
  color: #64748b;
  margin: 0;
}

/* Input */
.gcash-ref-wrap { margin-top: 4px; }

.gcash-ref-label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: #be185d;
  text-transform: uppercase;
  letter-spacing: .08em;
  margin-bottom: 6px;
}
.gcash-required { color: #e53e3e; }

.gcash-ref-wrap input {
  width: 100%;
  padding: 11px 14px;
  border: 1.5px solid #e8ddd9;
  border-radius: 10px;
  font-size: 14px;
  font-family: 'Inter', sans-serif;
  background: #fdf8f5;
  color: #2d2d2d;
  transition: border-color .18s, box-shadow .18s;
  outline: none;
  box-sizing: border-box;
}
.gcash-ref-wrap input:focus {
  border-color: #be185d;
  box-shadow: 0 0 0 3px rgba(190,24,93,.1);
}
.gcash-ref-wrap input.valid   { border-color: #16a34a; }
.gcash-ref-wrap input.invalid { border-color: #e53e3e; }

.gcash-ref-hint {
  display: block;
  font-size: 11px;
  margin-top: 4px;
  min-height: 16px;
}
.gcash-ref-hint.ok  { color: #16a34a; }
.gcash-ref-hint.err { color: #e53e3e; }
</style>

<!-- ══════════════════════════════════════════════════════
     Scripts
     ══════════════════════════════════════════════════════ -->
<script>
/**
 * Live-validate the GCash reference number field.
 * Strips non-digit characters as the user types.
 */
function validateGcashRef(input) {
  const hint = document.getElementById('gcashRefHint');
  const val  = input.value.replace(/\D/g, ''); // digits only
  input.value = val; // strip non-digits live

  if (val.length === 0) {
    input.className = '';
    hint.textContent = '';
    hint.className = 'gcash-ref-hint';
  } else if (val.length < 8) {
    input.classList.remove('valid');
    input.classList.add('invalid');
    hint.textContent = 'Too short — GCash refs are usually 13 digits';
    hint.className = 'gcash-ref-hint err';
  } else {
    input.classList.remove('invalid');
    input.classList.add('valid');
    hint.textContent = '✔ Looks good!';
    hint.className = 'gcash-ref-hint ok';
  }
}

/**
 * Block form submission if GCash is selected but ref number is missing/invalid.
 * Attach this to your checkoutForm submit event.
 */
(function () {
  const form = document.getElementById('checkoutForm');
  if (!form) return; // safety: form not found

  form.addEventListener('submit', function (e) {
    // Read payment method from your hidden/select input (adjust id if needed)
    const paymentInput = document.getElementById('fPayment');
    const paymentVal   = paymentInput ? paymentInput.value : '';

    if (paymentVal === 'gcash') {
      const refInput = document.getElementById('gcashRefInput');
      const ref      = refInput ? refInput.value.trim() : '';

      if (ref.length < 8) {
        e.preventDefault();

        if (refInput) {
          refInput.focus();
          refInput.classList.remove('valid');
          refInput.classList.add('invalid');
        }

        const hint = document.getElementById('gcashRefHint');
        if (hint) {
          hint.textContent = '⚠️ Please enter your GCash reference number before placing the order.';
          hint.className   = 'gcash-ref-hint err';
        }

        // Scroll into view smoothly so user notices the error
        if (refInput) {
          refInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      }
    }
  });
})();
</script>