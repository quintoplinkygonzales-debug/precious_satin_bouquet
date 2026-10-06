<?php
include "config.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>About - Precious Satin Bouquet</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">

<style>
:root {
  --blush:      #f9d0dd;
  --petal:      #f2a7bf;
  --rose:       #d96b8a;
  --deep-rose:  #b84d6a;
  --champagne:  #fdf3f6;
  --soft-white: #fffafc;
  --text-dark:  #3a1f28;
  --text-mid:   #7a4459;
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

/* ── Petal scatter decoration ── */
.petal-field {
  position: fixed;
  inset: 0;
  pointer-events: none;
  overflow: hidden;
  z-index: 0;
}
.petal {
  position: absolute;
  width: 10px;
  height: 14px;
  border-radius: 60% 40% 60% 40% / 50% 50% 50% 50%;
  opacity: 0;
  animation: drift linear infinite;
}
.petal:nth-child(1)  { left:5%;   background:#f2a7bf; animation-duration:14s; animation-delay:0s;   width:8px; height:12px; }
.petal:nth-child(2)  { left:15%;  background:#f9d0dd; animation-duration:18s; animation-delay:2s;   width:11px; height:15px; }
.petal:nth-child(3)  { left:28%;  background:#f2a7bf; animation-duration:12s; animation-delay:5s;   }
.petal:nth-child(4)  { left:42%;  background:#fce0ea; animation-duration:16s; animation-delay:1s;   width:9px; height:13px; }
.petal:nth-child(5)  { left:57%;  background:#f2a7bf; animation-duration:20s; animation-delay:3s;   }
.petal:nth-child(6)  { left:70%;  background:#f9d0dd; animation-duration:13s; animation-delay:7s;   width:7px; height:11px; }
.petal:nth-child(7)  { left:83%;  background:#f2a7bf; animation-duration:17s; animation-delay:0.5s; }
.petal:nth-child(8)  { left:92%;  background:#fce0ea; animation-duration:15s; animation-delay:4s;   width:12px; height:16px; }

@keyframes drift {
  0%   { transform: translateY(-20px) rotate(0deg);   opacity: 0; }
  10%  { opacity: 0.55; }
  90%  { opacity: 0.4; }
  100% { transform: translateY(100vh) rotate(360deg); opacity: 0; }
}

/* ── Page layout ── */
.page-wrap {
  position: relative;
  z-index: 1;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 20px 60px;
}

/* ── Card ── */
.card {
  background: var(--soft-white);
  border-radius: 32px;
  max-width: 560px;
  width: 100%;
  padding: 56px 48px 48px;
  box-shadow:
    0 2px 0px var(--petal),
    0 20px 60px var(--shadow),
    0 4px 16px rgba(180, 80, 110, 0.08);
  border: 1.5px solid rgba(242, 167, 191, 0.4);
  position: relative;
  overflow: hidden;
  animation: fadeUp 0.7s cubic-bezier(.22,1,.36,1) both;
}
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(30px); }
  to   { opacity: 1; transform: translateY(0); }
}

.card::before {
  content: '';
  position: absolute;
  top: -60px; right: -60px;
  width: 180px; height: 180px;
  background: radial-gradient(circle, #fce8f0 0%, transparent 70%);
  border-radius: 50%;
  pointer-events: none;
}

/* ── Header ── */
.card-eyebrow {
  font-family: 'Jost', sans-serif;
  font-size: 11px;
  letter-spacing: 4px;
  text-transform: uppercase;
  color: var(--petal);
  text-align: center;
  margin-bottom: 10px;
}

.card-title {
  font-family: 'Cormorant Garamond', serif;
  font-size: clamp(38px, 7vw, 52px);
  font-weight: 300;
  color: var(--deep-rose);
  text-align: center;
  line-height: 1.1;
  margin-bottom: 6px;
  letter-spacing: 1px;
}

.card-title em {
  font-style: italic;
  font-weight: 400;
  color: var(--rose);
}

.divider {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 20px 0 28px;
}
.divider-line {
  flex: 1;
  height: 1px;
  background: linear-gradient(to right, transparent, var(--petal), transparent);
}
.divider-icon {
  font-size: 16px;
  color: var(--petal);
}

/* ── Body text ── */
.card-body {
  font-size: 15px;
  line-height: 1.8;
  color: var(--text-mid);
  text-align: center;
  font-weight: 300;
  margin-bottom: 32px;
}

.card-body strong {
  color: var(--deep-rose);
  font-weight: 500;
}

/* ── Info pills ── */
.info-list {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 36px;
}

.info-item {
  display: flex;
  align-items: center;
  gap: 14px;
  background: var(--champagne);
  border: 1px solid rgba(242, 167, 191, 0.35);
  border-radius: 14px;
  padding: 14px 18px;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.info-item:hover {
  border-color: var(--petal);
  box-shadow: 0 4px 16px var(--shadow);
}

.info-icon {
  width: 36px; height: 36px;
  border-radius: 10px;
  background: linear-gradient(135deg, var(--blush), var(--petal));
  display: flex; align-items: center; justify-content: center;
  font-size: 16px;
  flex-shrink: 0;
}

.info-text {
  display: flex;
  flex-direction: column;
  gap: 1px;
}
.info-label {
  font-size: 10px;
  letter-spacing: 2px;
  text-transform: uppercase;
  color: var(--petal);
  font-weight: 500;
}
.info-value {
  font-size: 14px;
  color: var(--text-dark);
  font-weight: 400;
}
.info-value a {
  color: var(--rose);
  text-decoration: none;
  font-weight: 500;
  transition: color 0.2s;
}
.info-value a:hover { color: var(--deep-rose); }

/* ── Map ── */
.map-wrap {
  border-radius: 20px;
  overflow: hidden;
  border: 1.5px solid rgba(242, 167, 191, 0.4);
  box-shadow: 0 8px 28px var(--shadow);
}
.map-wrap iframe { display: block; }

/* ── Back button ── */
.back-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  text-decoration: none;
  font-family: 'Jost', sans-serif;
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 3px;
  text-transform: uppercase;
  color: var(--petal);
  background: none;
  border: none;
  padding: 0;
  margin-bottom: 28px;
  align-self: center;
  transition: color 0.2s, gap 0.2s;
}
.back-btn:hover {
  color: var(--deep-rose);
  gap: 10px;
}
.back-btn svg {
  width: 13px;
  height: 13px;
  stroke: currentColor;
  fill: none;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

/* ── Responsive ── */
@media (max-width: 600px) {
  .card { padding: 36px 24px 32px; border-radius: 24px; }
  .card-title { font-size: 36px; }
}
</style>
<?php include __DIR__ . '/pwa_head.php'; ?>
<link rel="icon" type="image/png" href="/precious_satin/images/logo/SB.png?v=3">
<link rel="shortcut icon" type="image/png" href="/precious_satin/images/logo/SB.png?v=3">
</head>

<body>

<div class="petal-field">
  <div class="petal"></div><div class="petal"></div><div class="petal"></div>
  <div class="petal"></div><div class="petal"></div><div class="petal"></div>
  <div class="petal"></div><div class="petal"></div>
</div>

<div class="page-wrap">
  <a href="index.php" class="back-btn">
    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
    Back to Home
  </a>
  <div class="card">

    <p class="card-eyebrow">Our Story</p>
    <h1 class="card-title">About <em>Us</em></h1>

    <div class="divider">
      <div class="divider-line"></div>
      <span class="divider-icon">🌸</span>
      <div class="divider-line"></div>
    </div>

    <p class="card-body">
      Welcome to <strong>Precious Satin Bouquet</strong>. We are passionate about bringing joy and beauty to your life through our unique bouquets and floral creations. Whether it's a special occasion or just because, our blooms are crafted with love and care to make every moment magical.
    </p>

    <ul class="info-list">
      <li class="info-item">
        <div class="info-icon">📞</div>
        <div class="info-text">
          <span class="info-label">Phone</span>
          <span class="info-value"><a href="tel:09678772667">0967 877 2667</a></span>
        </div>
      </li>
      <li class="info-item">
        <div class="info-icon">📍</div>
        <div class="info-text">
          <span class="info-label">Address</span>
          <span class="info-value">Purok 4, Bobontugan, Jasaan, Misamis Oriental</span>
        </div>
      </li>
    </ul>

    <div class="map-wrap">
      <iframe
        src="https://www.google.com/maps?q=Purok+4,+Bobontugan,+Jasaan,+Misamis+Oriental&output=embed"
        width="100%" height="260"
        style="border:0;"
        allowfullscreen="" loading="lazy"
        referrerpolicy="no-referrer-when-downgrade">
      </iframe>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
</body>
</html>