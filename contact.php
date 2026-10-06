<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include "config.php";

// Save message to DB on POST
$success = false;
$sender_name = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name    = htmlspecialchars(trim($_POST['name']));
    $email   = htmlspecialchars(trim($_POST['email']));
    $phone   = htmlspecialchars(trim($_POST['phone'] ?? ''));
    $message = htmlspecialchars(trim($_POST['message']));
    $sender_name = $name;
    $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

    // Create table if it doesn't exist (with user_id column)
    $conn->query("CREATE TABLE IF NOT EXISTS contact_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT NULL,
        name VARCHAR(150) NOT NULL,
        email VARCHAR(200) NOT NULL,
        phone VARCHAR(30),
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Ensure user_id column exists on older installs
    $conn->query("ALTER TABLE contact_messages ADD COLUMN IF NOT EXISTS user_id INT DEFAULT NULL");

    $stmt = $conn->prepare("INSERT INTO contact_messages (user_id, name, email, phone, message) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $user_id, $name, $email, $phone, $message);
    if ($stmt->execute()) {
        $success = true;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Contact - Precious Satin Bouquet</title>
<link rel="icon" type="image/jpg" href="images/logo/SB.png">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">

<style>
:root {
  --blush:      #f9d0dd;
  --petal:      #d4789a;
  --rose:       #c45070;
  --deep-rose:  #9e3358;
  --champagne:  #fdf3f6;
  --soft-white: #fffafc;
  --text-dark:  #2a1018;
  --text-mid:   #5c2d40;
  --shadow:     rgba(180, 80, 110, 0.15);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: 'Jost', sans-serif;
  background-color: var(--champagne);
  background-image:
    radial-gradient(ellipse at 10% 0%, #fce8f0 0%, transparent 55%),
    radial-gradient(ellipse at 90% 100%, #f9d0dd 0%, transparent 55%);
  min-height: 100vh;
  color: var(--text-dark);
}

.petal-field { position: fixed; inset: 0; pointer-events: none; overflow: hidden; z-index: 0; }
.petal {
  position: absolute; width: 10px; height: 14px;
  border-radius: 60% 40% 60% 40% / 50% 50% 50% 50%;
  opacity: 0; animation: drift linear infinite;
}
.petal:nth-child(1)  { left:5%;  background:#f2a7bf; animation-duration:14s; animation-delay:0s;   width:8px;  height:12px; }
.petal:nth-child(2)  { left:15%; background:#f9d0dd; animation-duration:18s; animation-delay:2s;   width:11px; height:15px; }
.petal:nth-child(3)  { left:28%; background:#f2a7bf; animation-duration:12s; animation-delay:5s; }
.petal:nth-child(4)  { left:42%; background:#fce0ea; animation-duration:16s; animation-delay:1s;   width:9px;  height:13px; }
.petal:nth-child(5)  { left:57%; background:#f2a7bf; animation-duration:20s; animation-delay:3s; }
.petal:nth-child(6)  { left:70%; background:#f9d0dd; animation-duration:13s; animation-delay:7s;   width:7px;  height:11px; }
.petal:nth-child(7)  { left:83%; background:#f2a7bf; animation-duration:17s; animation-delay:0.5s; }
.petal:nth-child(8)  { left:92%; background:#fce0ea; animation-duration:15s; animation-delay:4s;   width:12px; height:16px; }
@keyframes drift {
  0%   { transform: translateY(-20px) rotate(0deg);   opacity: 0; }
  10%  { opacity: 0.55; }
  90%  { opacity: 0.4; }
  100% { transform: translateY(100vh) rotate(360deg); opacity: 0; }
}

.page-wrap {
  position: relative; z-index: 1; min-height: 100vh;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  padding: 80px 20px 60px;
}

.card {
  background: var(--soft-white); border-radius: 32px; max-width: 600px; width: 100%;
  padding: 56px 48px 48px;
  box-shadow: 0 2px 0px var(--petal), 0 20px 60px var(--shadow), 0 4px 16px rgba(180,80,110,0.08);
  border: 1.5px solid rgba(242,167,191,0.4); position: relative; overflow: hidden;
  animation: fadeUp 0.7s cubic-bezier(.22,1,.36,1) both;
}
@keyframes fadeUp { from { opacity:0; transform:translateY(30px); } to { opacity:1; transform:translateY(0); } }
.card::before {
  content:''; position:absolute; top:-60px; right:-60px; width:180px; height:180px;
  background: radial-gradient(circle,#fce8f0 0%,transparent 70%);
  border-radius:50%; pointer-events:none;
}

.card-eyebrow { font-family:'Jost',sans-serif; font-size:11px; letter-spacing:4px; text-transform:uppercase; color:var(--rose); text-align:center; margin-bottom:10px; }
.card-title { font-family:'Cormorant Garamond',serif; font-size:clamp(36px,7vw,50px); font-weight:300; color:var(--deep-rose); text-align:center; line-height:1.1; letter-spacing:1px; margin-bottom:6px; }
.card-title em { font-style:italic; font-weight:400; color:var(--rose); }

.divider { display:flex; align-items:center; gap:12px; margin:18px 0 24px; }
.divider-line { flex:1; height:1px; background:linear-gradient(to right,transparent,var(--petal),transparent); }
.divider-icon { font-size:15px; color:var(--petal); }

.quick-links { display:flex; flex-direction:column; gap:8px; margin-bottom:32px; }
.quick-link-item { display:flex; align-items:center; gap:10px; font-size:14px; color:var(--text-dark); font-weight:400; }
.quick-link-item .ql-icon { font-size:15px; width:28px; height:28px; background:var(--champagne); border:1px solid rgba(242,167,191,0.4); border-radius:8px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.quick-link-item a { color:var(--rose); text-decoration:none; font-weight:500; transition:color 0.2s; }
.quick-link-item a:hover { color:var(--deep-rose); }

.form-section-label { font-size:10px; letter-spacing:3px; text-transform:uppercase; color:var(--rose); font-weight:600; margin-bottom:16px; }
.form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px; }
.field { display:flex; flex-direction:column; gap:5px; margin-bottom:12px; }
.field label { font-size:10px; letter-spacing:2px; text-transform:uppercase; color:var(--text-dark); font-weight:600; }
.field input, .field textarea { font-family:'Jost',sans-serif; font-size:14px; font-weight:400; color:var(--text-dark); background:var(--champagne); border:1.5px solid rgba(242,167,191,0.4); border-radius:12px; padding:13px 16px; outline:none; transition:border-color 0.2s,box-shadow 0.2s,background 0.2s; width:100%; }
.field input::placeholder, .field textarea::placeholder { color:#a8728a; }
.field input:focus, .field textarea:focus { border-color:var(--petal); background:var(--soft-white); box-shadow:0 0 0 3px rgba(242,167,191,0.2); }
.field textarea { height:120px; resize:none; }
.form-row .field { margin-bottom:0; }

.btn-submit { font-family:'Jost',sans-serif; font-size:13px; font-weight:500; letter-spacing:3px; text-transform:uppercase; color:#fff; background:linear-gradient(135deg,var(--rose),var(--deep-rose)); border:none; border-radius:14px; padding:16px 32px; width:100%; cursor:pointer; margin-top:8px; transition:transform 0.2s,box-shadow 0.2s,filter 0.2s; box-shadow:0 6px 20px rgba(180,80,110,0.3); }
.btn-submit:hover { transform:translateY(-2px); box-shadow:0 10px 28px rgba(180,80,110,0.4); filter:brightness(1.05); }
.btn-submit:active { transform:translateY(0); box-shadow:0 4px 12px rgba(180,80,110,0.25); }

.success-msg { margin-top:20px; background:linear-gradient(135deg,#fce8f0,#f9d0dd); border:1px solid rgba(242,167,191,0.5); border-radius:14px; padding:16px 20px; text-align:center; font-size:14px; color:var(--deep-rose); font-weight:400; animation:fadeUp 0.4s ease both; }

.back-link { display:inline-flex; align-items:center; gap:7px; font-size:11px; font-weight:500; letter-spacing:2.5px; text-transform:uppercase; color:var(--rose); text-decoration:none; margin-bottom:28px; transition:color 0.2s, gap 0.2s; }
.back-link:hover { color:var(--deep-rose); gap:10px; }
.back-link svg { flex-shrink:0; transition:transform 0.2s; }
.back-link:hover svg { transform:translateX(-3px); }

@media (max-width:600px) {
  .card { padding:36px 22px 32px; border-radius:24px; }
  .card-title { font-size:34px; }
  .form-row { grid-template-columns:1fr; }
}
</style>
<?php include __DIR__ . '/pwa_head.php'; ?>
</head>
<body>

<div class="petal-field">
  <div class="petal"></div><div class="petal"></div><div class="petal"></div>
  <div class="petal"></div><div class="petal"></div><div class="petal"></div>
  <div class="petal"></div><div class="petal"></div>
</div>

<div class="page-wrap">

  <a href="index.php" class="back-link">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
      <polyline points="15 18 9 12 15 6"/>
    </svg>
    Back to Shop
  </a>

  <div class="card">
    <p class="card-eyebrow">Get in Touch</p>
    <h1 class="card-title">Say <em>Hello</em></h1>

    <div class="divider">
      <div class="divider-line"></div>
      <span class="divider-icon">💌</span>
      <div class="divider-line"></div>
    </div>

    <div class="quick-links">
      <div class="quick-link-item">
        <div class="ql-icon">✉️</div>
        Email us at <a href="mailto:PreciousSatinBouquet@gmail.com">PreciousSatinBouquet@gmail.com</a>
      </div>
      <div class="quick-link-item">
        <div class="ql-icon">💙</div>
        Find us on <a href="https://www.facebook.com/PreciousSatinBouquet" target="_blank">Facebook: Precious Satin Bouquet</a>
      </div>
    </div>

    <p class="form-section-label">Send a Message</p>

    <?php if (!$success): ?>
    <form method="POST" action="">
      <div class="form-row">
        <div class="field">
          <label for="name">Name</label>
          <input type="text" id="name" name="name" placeholder="Your name" required>
        </div>
        <div class="field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" placeholder="your@email.com" required>
        </div>
      </div>
      <div class="field">
        <label for="phone">Phone <span style="opacity:.5;font-weight:300">(optional)</span></label>
        <input type="text" id="phone" name="phone" placeholder="0900 000 0000">
      </div>
      <div class="field">
        <label for="message">Message</label>
        <textarea id="message" name="message" placeholder="Write your message here…" required></textarea>
      </div>
      <button type="submit" class="btn-submit">Send Message</button>
    </form>
    <?php else: ?>
    <div class="success-msg">
      🌸 Thank you, <strong><?= $sender_name ?></strong>! Your message has been received. We'll be in touch soon.
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include 'footer.php'; ?>
</body>
</html>