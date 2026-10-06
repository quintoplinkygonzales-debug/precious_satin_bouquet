<?php
include "config.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $newPass = $_POST['new_password'];
    $confirmPass = $_POST['confirm_password'];

    if ($newPass !== $confirmPass) {
        $error = "Passwords do not match.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND role = 'customer'");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->bind_param("si", $hash, $row['id']);
            if ($update->execute()) {
                $success = "Password updated successfully. You can now log in.";
            } else {
                $error = "Unable to update password. Please try again.";
            }
        } else {
            $error = "Customer account not found with that username.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password · Precious Satin Bouquet</title>
<link rel="icon" type="image/png" href="images/logo/SB.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --blush:       #f9e4ec;
    --petal:       #f2c4d8;
    --rose:        #e8829f;
    --deep-rose:   #c4596e;
    --mauve:       #9b4d63;
    --cream:       #fdf6f9;
    --text-dark:   #3a1a24;
    --text-mid:    #7a4057;
    --text-soft:   #b07a90;
    --white:       #ffffff;
    --shadow:      rgba(196, 89, 110, 0.15);
  }

  html, body {
    height: 100%;
    font-family: 'Jost', sans-serif;
    background: var(--cream);
    color: var(--text-dark);
  }

  body {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    overflow: hidden;
    position: relative;
  }

  /* ── Split background ── */
  .bg-split {
    position: fixed;
    inset: 0;
    pointer-events: none;
    z-index: 0;
  }

  .bg-split::before {
    content: '';
    position: absolute;
    left: 0; top: 0;
    width: 42%; height: 100%;
    background: linear-gradient(160deg, #fce8f0 0%, #f7d6e8 50%, var(--petal) 100%);
    opacity: 0.55;
  }

  .bg-glow {
    position: fixed;
    pointer-events: none;
    z-index: 0;
    width: 600px; height: 600px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(242,196,216,0.5) 0%, transparent 70%);
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    animation: pulse 6s ease-in-out infinite;
  }

  @keyframes pulse {
    0%, 100% { transform: translate(-50%, -50%) scale(1); opacity: 0.7; }
    50%       { transform: translate(-50%, -50%) scale(1.08); opacity: 0.9; }
  }

  /* Falling petals */
  .petals-fall { position: fixed; inset: 0; pointer-events: none; z-index: 0; overflow: hidden; }

  .petal {
    position: absolute;
    border-radius: 0 100% 0 100%;
    opacity: 0;
    animation: fall linear infinite;
  }

  .petal:nth-child(1) { width:12px; height:18px; left:10%; background:var(--petal); animation-duration:7s; animation-delay:0s; }
  .petal:nth-child(2) { width:9px; height:14px; left:25%; background:#f7d6e8; animation-duration:9s; animation-delay:1.5s; }
  .petal:nth-child(3) { width:14px; height:20px; left:50%; background:var(--rose); opacity:.3; animation-duration:8s; animation-delay:3s; }
  .petal:nth-child(4) { width:10px; height:15px; left:70%; background:var(--petal); animation-duration:11s; animation-delay:0.8s; }
  .petal:nth-child(5) { width:8px; height:12px; left:85%; background:#fce8f0; animation-duration:6.5s; animation-delay:2s; }
  .petal:nth-child(6) { width:13px; height:19px; left:38%; background:var(--rose); opacity:.25; animation-duration:10s; animation-delay:4s; }

  @keyframes fall {
    0%   { transform: translateY(-20px) rotate(0deg); opacity: 0; }
    10%  { opacity: 0.6; }
    90%  { opacity: 0.3; }
    100% { transform: translateY(110vh) rotate(360deg); opacity: 0; }
  }

  /* ── Card ── */
  .card-wrap {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 480px;
    padding: 1.5rem;
    animation: fadeUp 0.7s cubic-bezier(0.22, 1, 0.36, 1) both;
  }

  @keyframes fadeUp {
    from { opacity: 0; transform: translateY(32px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  .card {
    background: var(--white);
    border-radius: 32px;
    padding: 2.6rem 2.8rem 2.8rem;
    box-shadow:
      0 4px 6px rgba(196,89,110,0.06),
      0 20px 60px rgba(196,89,110,0.14),
      inset 0 1px 0 rgba(255,255,255,0.9);
    border: 1.5px solid rgba(242,196,216,0.6);
    position: relative;
    overflow: hidden;
  }

  /* Decorative corner bloom */
  .card::after {
    content: '✿';
    position: absolute;
    top: 1.2rem; right: 1.6rem;
    font-size: 1.8rem;
    color: var(--petal);
    opacity: 0.7;
    animation: spinSlow 30s linear infinite;
    line-height: 1;
  }

  @keyframes spinSlow { to { transform: rotate(360deg); } }

  /* ── Brand ── */
  .brand {
    display: flex;
    align-items: center;
    gap: 0.9rem;
    margin-bottom: 2rem;
  }

  .brand-logo {
    width: 52px; height: 52px;
    background: linear-gradient(135deg, var(--blush), var(--petal));
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 4px 14px rgba(196,89,110,0.2);
  }

  .brand-text { flex: 1; }

  .brand-name {
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.5rem;
    font-weight: 600;
    letter-spacing: 0.1em;
    color: var(--text-dark);
    text-transform: uppercase;
    line-height: 1;
  }

  .brand-tagline {
    font-size: 0.7rem;
    font-weight: 300;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: var(--text-soft);
    margin-top: 0.2rem;
  }

  /* ── Section divider ── */
  .section-rule {
    border: none;
    height: 1px;
    background: linear-gradient(to right, transparent, var(--petal) 30%, var(--petal) 70%, transparent);
    margin-bottom: 1.8rem;
  }

  /* ── Headings ── */
  .card-title {
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.7rem;
    font-weight: 400;
    font-style: italic;
    color: var(--text-dark);
    margin-bottom: 0.3rem;
    line-height: 1.2;
  }

  .card-sub {
    font-size: 0.82rem;
    font-weight: 300;
    color: var(--text-soft);
    margin-bottom: 1.8rem;
    line-height: 1.6;
  }

  /* ── Alerts ── */
  .alert {
    border-radius: 14px;
    padding: 0.85rem 1rem;
    font-size: 0.82rem;
    margin-bottom: 1.4rem;
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    line-height: 1.45;
  }

  .alert-success {
    background: linear-gradient(135deg, #fff5f8, #ffedf3);
    color: var(--mauve);
    border: 1px solid rgba(232,130,159,0.3);
  }

  .alert-error {
    background: #fff5f5;
    color: #b94060;
    border: 1px solid #f5c0cc;
  }

  /* ── Form fields ── */
  .field {
    position: relative;
    margin-bottom: 1rem;
  }

  .field-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-soft);
    pointer-events: none;
    transition: color 0.2s;
  }

  .field input {
    width: 100%;
    padding: 0.95rem 1rem 0.95rem 2.8rem;
    border: 1.5px solid var(--petal);
    border-radius: 16px;
    font-family: 'Jost', sans-serif;
    font-size: 0.88rem;
    font-weight: 300;
    color: var(--text-dark);
    background: #fdf9fb;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
  }

  .field input::placeholder { color: var(--text-soft); }

  .field input:focus {
    border-color: var(--rose);
    background: var(--white);
    box-shadow: 0 0 0 4px rgba(232,130,159,0.14);
  }

  .field input:focus ~ .field-icon { color: var(--rose); }

  /* Strength hint */
  .hint {
    font-size: 0.73rem;
    color: var(--text-soft);
    margin-top: 0.35rem;
    padding-left: 0.4rem;
  }

  /* ── Button ── */
  .btn-reset {
    width: 100%;
    margin-top: 0.8rem;
    padding: 1rem 1.5rem;
    background: linear-gradient(135deg, #e8829f 0%, var(--deep-rose) 100%);
    color: var(--white);
    border: none;
    border-radius: 16px;
    font-family: 'Jost', sans-serif;
    font-size: 0.88rem;
    font-weight: 500;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    box-shadow: 0 6px 24px rgba(196,89,110,0.38);
    transition: transform 0.18s, box-shadow 0.18s;
    position: relative;
    overflow: hidden;
  }

  .btn-reset::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(to bottom, rgba(255,255,255,0.12), transparent);
  }

  .btn-reset:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 32px rgba(196,89,110,0.48);
  }

  .btn-reset:active { transform: translateY(0); box-shadow: 0 4px 12px rgba(196,89,110,0.3); }

  /* ── Footer ── */
  .back-link {
    text-align: center;
    margin-top: 1.6rem;
    font-size: 0.8rem;
    font-weight: 300;
    color: var(--text-soft);
  }

  .back-link a {
    color: var(--deep-rose);
    font-weight: 500;
    text-decoration: none;
    border-bottom: 1px solid transparent;
    transition: border-color 0.2s;
  }

  .back-link a:hover { border-color: var(--deep-rose); }

  /* floral row decoration */
  .floral-row {
    text-align: center;
    color: var(--petal);
    font-size: 1rem;
    letter-spacing: 0.4em;
    margin: 1.5rem 0 0;
    opacity: 0.7;
  }
</style>
<?php include __DIR__ . '/pwa_head.php'; ?>
</head>
<body>

<div class="bg-split"></div>
<div class="bg-glow"></div>

<!-- Falling petals -->
<div class="petals-fall">
  <div class="petal"></div>
  <div class="petal"></div>
  <div class="petal"></div>
  <div class="petal"></div>
  <div class="petal"></div>
  <div class="petal"></div>
</div>

<div class="card-wrap">
  <div class="card">

    <!-- Brand -->
    <div class="brand">
      <div class="brand-logo">
        <svg width="28" height="28" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="22" cy="22" r="5" fill="#e8829f"/>
          <ellipse cx="22" cy="10" rx="4.5" ry="7.5" fill="#f2c4d8"/>
          <ellipse cx="22" cy="34" rx="4.5" ry="7.5" fill="#f2c4d8"/>
          <ellipse cx="10" cy="22" rx="7.5" ry="4.5" fill="#fce8f0"/>
          <ellipse cx="34" cy="22" rx="7.5" ry="4.5" fill="#fce8f0"/>
          <ellipse cx="13.5" cy="13.5" rx="4.5" ry="7.5" fill="#f2c4d8" transform="rotate(-45 13.5 13.5)"/>
          <ellipse cx="30.5" cy="30.5" rx="4.5" ry="7.5" fill="#f2c4d8" transform="rotate(-45 30.5 30.5)"/>
          <ellipse cx="30.5" cy="13.5" rx="4.5" ry="7.5" fill="#fce8f0" transform="rotate(45 30.5 13.5)"/>
          <ellipse cx="13.5" cy="30.5" rx="4.5" ry="7.5" fill="#fce8f0" transform="rotate(45 13.5 30.5)"/>
          <circle cx="22" cy="22" r="4.5" fill="#c4596e"/>
        </svg>
      </div>
      <div class="brand-text">
        <div class="brand-name">Precious Satin Bouquet</div>
        <div class="brand-tagline">Flowers &amp; Gifts</div>
      </div>
    </div>

    <hr class="section-rule">

    <h2 class="card-title">Forgot your password?</h2>
    <p class="card-sub">No worries — enter your account username and we'll help you set a new one.</p>

    <?php if(isset($success)): ?>
      <div class="alert alert-success">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10" stroke="#c4596e" stroke-width="1.8"/><path d="M8 12l3 3 5-5" stroke="#c4596e" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <?= htmlspecialchars($success) ?>
      </div>
    <?php endif; ?>

    <?php if(isset($error)): ?>
      <div class="alert alert-error">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10" stroke="#b94060" stroke-width="1.8"/><path d="M12 8v4m0 4h.01" stroke="#b94060" stroke-width="1.8" stroke-linecap="round"/></svg>
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <div class="field">
        <input type="text" name="username" placeholder="Email / Username" required>
        <svg class="field-icon" width="15" height="15" fill="none" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="1.8"/></svg>
      </div>

      <div class="field">
        <input type="password" name="new_password" placeholder="New Password" required>
        <svg class="field-icon" width="15" height="15" fill="none" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M7 11V7a5 5 0 0 1 10 0v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        <p class="hint">At least 8 characters recommended</p>
      </div>

      <div class="field">
        <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
        <svg class="field-icon" width="15" height="15" fill="none" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M7 11V7a5 5 0 0 1 10 0v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M9 16l2 2 4-3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </div>

      <button type="submit" class="btn-reset">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24"><path d="M12 2v4m0 12v4M4.93 4.93l2.83 2.83m8.48 8.48 2.83 2.83M2 12h4m12 0h4M4.93 19.07l2.83-2.83m8.48-8.48 2.83-2.83" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        Update Password
      </button>
    </form>

    <p class="back-link">← Back to <a href="login.php">Sign In</a></p>

    <div class="floral-row">✿ ✾ ✿</div>
  </div>
</div>

</body>
</html>