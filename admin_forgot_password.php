<?php
include "config.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $newPass = $_POST['new_password'];
    $confirmPass = $_POST['confirm_password'];

    if ($newPass !== $confirmPass) {
        $error = "Passwords do not match.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND role = 'admin'");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->bind_param("si", $hash, $row['id']);
            if ($update->execute()) {
                $success = "Password updated. You can now log in.";
            } else {
                $error = "Unable to update password. Please try again.";
            }
        } else {
            $error = "Admin account not found with that email/username.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin – Reset Password · Precious Satin Bouquet</title>
<link rel="icon" type="image/png" href="images/logo/SB.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --blush:       #fff3ed;
    --petal:       #ffc9a0;
    --rose:        #f08050;
    --deep-rose:   #d05a28;
    --mauve:       #c8775a;
    --cream:       #fff8f4;
    --text-dark:   #4a2c1e;
    --text-mid:    #9a6b5a;
    --text-soft:   #c49a88;
    --white:       #ffffff;
    --shadow:      rgba(232, 133, 90, 0.12);
  }

  html, body {
    height: 100%;
    font-family: 'Jost', sans-serif;
    background-color: var(--cream);
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

  /* ── Petal background decorations ── */
  .bg-petals {
    position: fixed;
    inset: 0;
    pointer-events: none;
    z-index: 0;
    overflow: hidden;
  }

  .bg-petals::before {
    content: '';
    position: absolute;
    top: -120px; right: -120px;
    width: 480px; height: 480px;
    background: radial-gradient(circle, #ffc9a0 0%, transparent 70%);
    opacity: 0.6;
    border-radius: 50%;
    animation: floatA 8s ease-in-out infinite alternate;
  }

  .bg-petals::after {
    content: '';
    position: absolute;
    bottom: -100px; left: -100px;
    width: 420px; height: 420px;
    background: radial-gradient(circle, #ffe4d0 0%, transparent 70%);
    opacity: 0.5;
    border-radius: 50%;
    animation: floatB 10s ease-in-out infinite alternate;
  }

  @keyframes floatA { from { transform: translate(0,0) scale(1); } to { transform: translate(-30px, 30px) scale(1.08); } }
  @keyframes floatB { from { transform: translate(0,0) scale(1); } to { transform: translate(25px, -20px) scale(1.06); } }

  /* Scattered small petals SVG */
  .petal-scatter {
    position: fixed;
    inset: 0;
    pointer-events: none;
    z-index: 0;
  }

  /* ── Card ── */
  .card-wrap {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 460px;
    padding: 1.5rem;
    animation: fadeUp 0.7s cubic-bezier(0.22, 1, 0.36, 1) both;
  }

  @keyframes fadeUp {
    from { opacity: 0; transform: translateY(28px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  .card {
    background: var(--white);
    border-radius: 28px;
    padding: 3rem 2.8rem 2.8rem;
    box-shadow: 0 8px 48px var(--shadow), 0 2px 8px rgba(208,90,40,0.06);
    border: 1px solid rgba(255,200,160,0.6);
    position: relative;
    overflow: hidden;
  }

  /* top accent bar */
  .card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 5px;
    background: linear-gradient(90deg, #ffc9a0, #f08050, #d05a28, #f08050, #ffc9a0);
  }

  /* ── Logo / Brand ── */
  .brand {
    text-align: center;
    margin-bottom: 2rem;
  }

  .flower-icon {
    display: inline-block;
    margin-bottom: 0.6rem;
    animation: spinSlow 20s linear infinite;
  }

  @keyframes spinSlow {
    from { transform: rotate(0deg); }
    to   { transform: rotate(360deg); }
  }

  .brand-name {
    font-family: 'Cormorant Garamond', serif;
    font-size: 2rem;
    font-weight: 600;
    letter-spacing: 0.12em;
    color: var(--text-dark);
    line-height: 1;
    text-transform: uppercase;
  }

  .brand-badge {
    display: inline-block;
    margin-top: 0.45rem;
    font-size: 0.65rem;
    font-weight: 500;
    letter-spacing: 0.22em;
    text-transform: uppercase;
    color: var(--white);
    background: linear-gradient(135deg, #f08050, #d05a28);
    padding: 0.25em 0.9em;
    border-radius: 20px;
  }

  /* ── Heading ── */
  .card-title {
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.55rem;
    font-weight: 400;
    font-style: italic;
    color: var(--text-dark);
    margin-bottom: 0.35rem;
    text-align: center;
  }

  .card-sub {
    font-size: 0.82rem;
    font-weight: 300;
    color: var(--text-soft);
    text-align: center;
    letter-spacing: 0.01em;
    margin-bottom: 2rem;
    line-height: 1.55;
  }

  /* ── Alerts ── */
  .alert {
    border-radius: 12px;
    padding: 0.85rem 1rem;
    font-size: 0.83rem;
    font-weight: 400;
    margin-bottom: 1.4rem;
    display: flex;
    align-items: center;
    gap: 0.6rem;
  }

  .alert-success {
    background: #fff3ed;
    color: var(--mauve);
    border: 1px solid #ffc9a0;
  }

  .alert-error {
    background: #fff8f4;
    color: #c85a3a;
    border: 1px solid #ffc9a0;
  }

  .alert svg { flex-shrink: 0; }

  /* ── Form ── */
  .field {
    position: relative;
    margin-bottom: 1.1rem;
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
    padding: 0.9rem 1rem 0.9rem 2.8rem;
    border: 1.5px solid #ffc9a0;
    border-radius: 14px;
    font-family: 'Jost', sans-serif;
    font-size: 0.88rem;
    font-weight: 300;
    color: var(--text-dark);
    background: var(--cream);
    outline: none;
    transition: border-color 0.25s, box-shadow 0.25s, background 0.25s;
  }

  .field input::placeholder { color: var(--text-soft); }

  .field input:focus {
    border-color: #f08050;
    box-shadow: 0 0 0 4px rgba(240, 168, 130, 0.18);
    background: var(--white);
  }

  .field input:focus + .field-label,
  .field input:not(:placeholder-shown) + .field-label {
    opacity: 1;
    transform: translateY(0);
  }

  .field input:focus ~ .field-icon { color: var(--rose); }

  /* ── Button ── */
  .btn-reset {
    width: 100%;
    margin-top: 0.6rem;
    padding: 1rem;
    background: linear-gradient(135deg, #ffb090 0%, #e86838 100%);
    color: var(--white);
    border: none;
    border-radius: 14px;
    font-family: 'Jost', sans-serif;
    font-size: 0.9rem;
    font-weight: 500;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    transition: transform 0.18s, box-shadow 0.18s;
    box-shadow: 0 4px 20px rgba(232, 133, 90, 0.25);
  }

  .btn-reset::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(255,255,255,0.15), transparent);
    transition: opacity 0.2s;
  }

  .btn-reset:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 28px rgba(232, 133, 90, 0.3);
  }

  .btn-reset:active { transform: translateY(0); }

  /* ── Footer link ── */
  .back-link {
    text-align: center;
    margin-top: 1.6rem;
    font-size: 0.82rem;
    font-weight: 300;
    color: var(--text-soft);
  }

  .back-link a {
    color: var(--deep-rose);
    text-decoration: none;
    font-weight: 500;
    border-bottom: 1px solid transparent;
    transition: border-color 0.2s;
  }

  .back-link a:hover { border-color: var(--deep-rose); }

  /* ── Divider ── */
  .divider {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    margin-bottom: 1.6rem;
    color: #ffd0b8;
    font-size: 1rem;
  }
  .divider::before, .divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: linear-gradient(to right, transparent, #ffd0b8);
  }
  .divider::after { background: linear-gradient(to left, transparent, #ffd0b8); }
</style>
<?php include __DIR__ . '/pwa_head.php'; ?>
</head>
<body>

<div class="bg-petals"></div>

<!-- Scattered petal SVGs -->
<svg class="petal-scatter" viewBox="0 0 1200 700" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <ellipse cx="80" cy="120" rx="18" ry="10" fill="#f2c4d8" opacity="0.4" transform="rotate(-30 80 120)"/>
  <ellipse cx="1100" cy="200" rx="14" ry="8" fill="#f08050" opacity="0.3" transform="rotate(20 1100 200)"/>
  <ellipse cx="200" cy="600" rx="20" ry="11" fill="#f2c4d8" opacity="0.35" transform="rotate(40 200 600)"/>
  <ellipse cx="950" cy="560" rx="16" ry="9" fill="#f08050" opacity="0.25" transform="rotate(-15 950 560)"/>
  <ellipse cx="600" cy="50" rx="12" ry="7" fill="#ffc9a0" opacity="0.2" transform="rotate(10 600 50)"/>
  <ellipse cx="1050" cy="620" rx="22" ry="12" fill="#f2c4d8" opacity="0.3" transform="rotate(-45 1050 620)"/>
  <ellipse cx="150" cy="350" rx="10" ry="6" fill="#f08050" opacity="0.2" transform="rotate(60 150 350)"/>
</svg>

<div class="card-wrap">
  <div class="card">

    <!-- Brand -->
    <div class="brand">
      <div class="flower-icon">
        <svg width="44" height="44" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="22" cy="22" r="5" fill="#f08050"/>
          <ellipse cx="22" cy="10" rx="5" ry="8" fill="#ffc9a0"/>
          <ellipse cx="22" cy="34" rx="5" ry="8" fill="#ffc9a0"/>
          <ellipse cx="10" cy="22" rx="8" ry="5" fill="#fff3ed"/>
          <ellipse cx="34" cy="22" rx="8" ry="5" fill="#fff3ed"/>
          <ellipse cx="13.5" cy="13.5" rx="5" ry="8" fill="#ffc9a0" transform="rotate(-45 13.5 13.5)"/>
          <ellipse cx="30.5" cy="30.5" rx="5" ry="8" fill="#ffc9a0" transform="rotate(-45 30.5 30.5)"/>
          <ellipse cx="30.5" cy="13.5" rx="5" ry="8" fill="#fff3ed" transform="rotate(45 30.5 13.5)"/>
          <ellipse cx="13.5" cy="30.5" rx="5" ry="8" fill="#fff3ed" transform="rotate(45 13.5 30.5)"/>
          <circle cx="22" cy="22" r="5" fill="#ffc9a0"/>
        </svg>
      </div>
      <div class="brand-name">Precious Satin Bouquet</div>
      <div><span class="brand-badge">Admin Portal</span></div>
    </div>

    <div class="divider">✿</div>

    <h2 class="card-title">Reset Admin Password</h2>
    <p class="card-sub">Enter your admin email and choose a new password to restore access.</p>

    <?php if(isset($success)): ?>
      <div class="alert alert-success">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="#d05a28" stroke-width="1.8"/><path d="M8 12l3 3 5-5" stroke="#d05a28" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <?= htmlspecialchars($success) ?>
      </div>
    <?php endif; ?>

    <?php if(isset($error)): ?>
      <div class="alert alert-error">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="#c85a3a" stroke-width="1.8"/><path d="M12 8v4m0 4h.01" stroke="#c85a3a" stroke-width="1.8" stroke-linecap="round"/></svg>
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <div class="field">
        <input type="text" name="username" placeholder="Admin Email / Username" required>
        <svg class="field-icon" width="16" height="16" fill="none" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="1.8"/></svg>
      </div>
      <div class="field">
        <input type="password" name="new_password" placeholder="New Password" required>
        <svg class="field-icon" width="16" height="16" fill="none" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M7 11V7a5 5 0 0 1 10 0v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      </div>
      <div class="field">
        <input type="password" name="confirm_password" placeholder="Confirm Password" required>
        <svg class="field-icon" width="16" height="16" fill="none" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M7 11V7a5 5 0 0 1 10 0v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M9 16l2 2 4-3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </div>
      <button type="submit" class="btn-reset">Reset Password</button>
    </form>

    <p class="back-link">← Back to <a href="admin_login.php">Admin Sign In</a></p>
  </div>
</div>

</body>
</html>