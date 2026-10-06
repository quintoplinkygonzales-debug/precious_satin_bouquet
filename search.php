<?php
include "config.php";

$query = trim($_GET['query'] ?? '');
$results = [];

if ($query !== '') {
    $like = '%' . $query . '%';
    $stmt = $conn->prepare("
        SELECT p.*, c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE c.name = 'Satin' AND (p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)
        ORDER BY p.id DESC
    ");
    $stmt->bind_param("sss", $like, $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }
    $stmt->close();
}

// Count unread admin replies for the logged-in user
$_sb_notif = 0;
if (!empty($_SESSION['user_id'])) {
    $_sb_uid = (int)$_SESSION['user_id'];
    $conn->query("ALTER TABLE message_replies ADD COLUMN IF NOT EXISTS is_read TINYINT(1) DEFAULT 0");
    $s = $conn->prepare("
        SELECT COUNT(*) AS c
        FROM message_replies mr
        JOIN contact_messages cm ON cm.id = mr.message_id
        WHERE cm.user_id = ? AND mr.sender = 'admin' AND mr.is_read = 0
    ");
    $s->bind_param("i", $_sb_uid);
    $s->execute();
    $_sb_notif = (int)$s->get_result()->fetch_assoc()['c'];
    $s->close();
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Search: <?= htmlspecialchars($query) ?> — Precious Satin Bouquet</title>
<link rel="icon" type="image/jpg" href="images/logo/SB.png">
<link rel="stylesheet" href="style.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,700;1,400;1,500&family=Jost:wght@200;300;400;500&family=Pinyon+Script&display=swap" rel="stylesheet">

<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --cream:      #f6eefd;
  --parchment:  #f5e9d8;
  --blush:      #cfc0f0;
  --rose:       #d4786a;
  --deep-rose:  #b85450;
  --burgundy:   #7a2e36;
  --wine:       #461a4e;
  --sage:       #8a9e7e;
  --sage-light: #c4d4ba;
  --gold:       #c9a96e;
  --gold-light: #e8d5b0;
  --ink:        #271a2a;

  --serif:  'Playfair Display', Georgia, serif;
  --script: 'Pinyon Script', cursive;
  --sans:   'Jost', sans-serif;
  --ease:   cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

html { scroll-behavior: smooth; }

body {
  background-color: var(--cream);
  background-image:
    radial-gradient(ellipse at 10% 0%, rgba(212,120,106,0.08) 0%, transparent 60%),
    radial-gradient(ellipse at 90% 100%, rgba(138,158,126,0.10) 0%, transparent 60%),
    url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='400' height='400'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.75' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='400' height='400' filter='url(%23n)' opacity='0.02'/%3E%3C/svg%3E");
  color: var(--ink);
  font-family: var(--sans);
  font-weight: 300;
  min-height: 100vh;
  overflow-x: hidden;
}

/* ──────────────────── TOPBAR ──────────────────── */
.topbar {
  position: sticky;
  top: 0;
  z-index: 600;
  height: 72px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 clamp(1.2rem, 5vw, 3rem);
  gap: 20px;
  background: rgba(246,238,253,0.88);
  backdrop-filter: blur(18px);
  -webkit-backdrop-filter: blur(18px);
  border-bottom: 1px solid var(--gold-light);
}

/* Decorative vine line on topbar bottom */
.topbar::after {
  content: '';
  position: absolute;
  bottom: -1px;
  left: 0; right: 0;
  height: 3px;
  background: linear-gradient(90deg,
    transparent 0%,
    var(--gold-light) 15%,
    var(--gold) 35%,
    var(--rose) 50%,
    var(--gold) 65%,
    var(--gold-light) 85%,
    transparent 100%
  );
  opacity: 0.6;
}

.topbar-logo {
  display: flex;
  align-items: center;
  gap: 12px;
  text-decoration: none;
  flex-shrink: 0;
}
.topbar-logo img {
  width: 40px; height: 40px;
  object-fit: contain;
  border-radius: 50%;
  border: 1.5px solid var(--gold-light);
  padding: 2px;
}
.topbar-logo-text {
  font-family: var(--serif);
  font-size: 1.3rem;
  font-weight: 500;
  color: var(--burgundy);
  letter-spacing: 0.02em;
  line-height: 1;
}
.topbar-logo-tagline {
  font-family: var(--script);
  font-size: 0.85rem;
  color: var(--rose);
  display: block;
  letter-spacing: 0.03em;
  margin-top: 1px;
}

/* ──────────────────── SEARCH BAR ──────────────────── */
.topbar-search {
  flex: 1;
  max-width: 520px;
  display: flex;
  align-items: center;
  gap: 10px;
  background: rgba(255,255,255,0.7);
  border: 1.5px solid var(--gold-light);
  border-radius: 4px;
  padding: 0 18px;
  position: relative;
  transition: border-color 0.25s, box-shadow 0.25s, background 0.25s;
}
.topbar-search:focus-within {
  border-color: var(--gold);
  background: #fff;
  box-shadow: 0 4px 24px rgba(201,169,110,0.18);
}
.topbar-search svg {
  color: var(--gold);
  flex-shrink: 0;
  opacity: 0.8;
}
.topbar-search input {
  flex: 1;
  border: none;
  outline: none;
  background: transparent;
  font-family: var(--sans);
  font-size: 0.88rem;
  font-weight: 300;
  color: var(--ink);
  padding: 13px 0;
  letter-spacing: 0.04em;
}
.topbar-search input::placeholder {
  color: var(--rose);
  opacity: 0.7;
  font-style: italic;
}
.search-submit-btn {
  background: none;
  border: none;
  cursor: pointer;
  color: var(--rose);
  display: flex;
  align-items: center;
  padding: 4px;
  opacity: 0.7;
  transition: opacity 0.2s, color 0.2s;
}
.search-submit-btn:hover { opacity: 1; color: var(--burgundy); }

/* ──────────────────── PAGE LAYOUT ──────────────────── */
.search-page {
  max-width: 1320px;
  margin: 0 auto;
  padding: 0 clamp(1.2rem, 4vw, 3rem) 4rem;
}

/* ──────────────────── HERO BANNER HEADER ──────────────────── */
.search-header {
  position: relative;
  overflow: hidden;
  background: linear-gradient(135deg,
    #ffffff 0%,
    #f6e8fc 30%,
    #e7b8f9 65%,
    #c660f0 100%
  );
  padding: clamp(3rem, 7vw, 5rem) clamp(1.5rem, 5vw, 3.5rem) clamp(2.5rem, 6vw, 4rem);
  margin-bottom: 4rem;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  min-height: 260px;
}

/* Diagonal petal texture overlay */
.search-header::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image:
    radial-gradient(ellipse at 80% 20%, rgba(255,255,255,0.25) 0%, transparent 55%),
    radial-gradient(ellipse at 10% 90%, rgba(240,200,255,0.2) 0%, transparent 50%);
  pointer-events: none;
}

/* Large decorative script watermark */
.search-header-watermark {
  position: absolute;
  top: 50%;
  right: 5%;
  transform: translateY(-50%);
  font-family: var(--script);
  font-size: clamp(6rem, 14vw, 12rem);
  color: rgba(104,20,140,0.07);
  line-height: 1;
  pointer-events: none;
  user-select: none;
  white-space: nowrap;
}

/* Gold ruled line at top of header */
.search-header-rule {
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  background: linear-gradient(90deg,
    transparent 0%,
    var(--gold) 20%,
    var(--gold-light) 50%,
    var(--gold) 80%,
    transparent 100%
  );
}

.search-eyebrow {
  font-family: var(--sans);
  font-size: 0.65rem;
  font-weight: 600;
  letter-spacing: 0.3em;
  text-transform: uppercase;
  color: #58107a;
  margin-bottom: 1rem;
  display: flex;
  align-items: center;
  gap: 12px;
  position: relative;
  z-index: 1;
}
.search-eyebrow::before,
.search-eyebrow::after {
  content: '';
  display: inline-block;
  height: 1px;
  background: #58107a;
  opacity: 0.5;
}
.search-eyebrow::before { width: 32px; }
.search-eyebrow::after  { width: 16px; }

.search-heading {
  font-family: var(--serif);
  font-size: clamp(2.2rem, 5.5vw, 3.8rem);
  font-weight: 400;
  font-style: italic;
  color: #630088 !important;
  -webkit-text-fill-color: #630088 !important;
  line-height: 1.1;
  letter-spacing: -0.015em;
  position: relative;
  z-index: 1;
}
.search-heading .query-word {
  font-style: normal;
  font-weight: 700;
  color: #630088 !important;
  -webkit-text-fill-color: #630088 !important;
  display: inline-block;
  padding: 0 4px;
}

.search-count {
  font-size: 0.72rem;
  color: #58107a;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  font-weight: 500;
  margin-top: 1.25rem;
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  gap: 10px;
}
.count-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: rgba(255,255,255,0.6);
  border: 1px solid rgba(88,16,122,0.3);
  color: #410a5a;
  font-size: 0.78rem;
  font-weight: 600;
  font-family: var(--serif);
  letter-spacing: 0.02em;
  padding: 2px 14px;
  border-radius: 20px;
}

/* ──────────────────── PRODUCT GRID ──────────────────── */
.products-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
  gap: 32px;
}

/* ──────────────────── PRODUCT CARD ──────────────────── */
.product-card {
  background: #fff;
  border-radius: 18px;
  overflow: hidden;
  box-shadow: 0 2px 16px rgba(163,80,180,0.07);
  opacity: 0;
  transform: translateY(28px);
  transition:
    opacity 0.55s var(--ease),
    transform 0.55s var(--ease),
    box-shadow 0.3s;
  display: flex;
  flex-direction: column;
}
.product-card.visible {
  opacity: 1;
  transform: translateY(0);
}
.product-card:hover {
  box-shadow: 0 10px 40px rgba(163,80,180,0.15);
  transform: translateY(-4px);
}

/* Image */
.product-card-img {
  position: relative;
  aspect-ratio: 1/1;
  overflow: hidden;
  background: var(--parchment);
  flex-shrink: 0;
}
.product-card-img img {
  width: 100%; height: 100%;
  object-fit: cover;
  transition: transform 0.6s var(--ease);
}
.product-card:hover .product-card-img img { transform: scale(1.05); }

/* No overlay — clean like index.php */
.card-overlay { display: none; }
.card-category-badge { display: none; }

/* Card body */
.product-card-body {
  padding: 16px 18px 20px;
  display: flex;
  flex-direction: column;
  flex: 1;
}

.product-card-category { display: none; }

.product-card-name {
  font-family: var(--serif);
  font-size: 1.15rem;
  font-weight: 500;
  color: var(--ink);
  margin-bottom: 6px;
  line-height: 1.25;
  letter-spacing: -0.01em;
}

.product-card-desc {
  font-size: 0.8rem;
  color: #888;
  line-height: 1.55;
  margin-bottom: 16px;
  font-weight: 300;
  flex: 1;
}

.product-card-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-top: auto;
}

.product-card-price {
  font-family: var(--serif);
  font-size: 1.2rem;
  font-weight: 600;
  color: var(--ink);
  letter-spacing: -0.02em;
}
/* No ::before — price already includes ₱ symbol from PHP */

.product-card-link {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  font-family: var(--sans);
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #fff;
  text-decoration: none;
  padding: 10px 18px;
  border-radius: 50px;
  background: var(--ink);
  border: none;
  transition: background 0.25s, transform 0.2s;
  white-space: nowrap;
}
.product-card-link:hover {
  background: var(--burgundy);
  transform: scale(1.03);
}

/* ──────────────────── STAGGER DELAYS ──────────────────── */
.product-card:nth-child(1) { transition-delay: 0.00s; }
.product-card:nth-child(2) { transition-delay: 0.08s; }
.product-card:nth-child(3) { transition-delay: 0.16s; }
.product-card:nth-child(4) { transition-delay: 0.24s; }
.product-card:nth-child(5) { transition-delay: 0.32s; }
.product-card:nth-child(6) { transition-delay: 0.40s; }
.product-card:nth-child(7) { transition-delay: 0.48s; }
.product-card:nth-child(8) { transition-delay: 0.56s; }

/* ──────────────────── EMPTY STATE ──────────────────── */
.empty-state {
  grid-column: 1 / -1;
  text-align: center;
  padding: 6rem 2rem;
  position: relative;
}
.empty-petal {
  font-size: 3rem;
  margin-bottom: 1.5rem;
  display: block;
  opacity: 0.4;
  animation: sway 3s ease-in-out infinite;
}
@keyframes sway {
  0%, 100% { transform: rotate(-5deg); }
  50%       { transform: rotate(5deg); }
}
.empty-state h2 {
  font-family: var(--serif);
  font-size: 2rem;
  font-weight: 400;
  font-style: italic;
  color: var(--wine);
  margin-bottom: 0.75rem;
}
.empty-state p {
  font-size: 0.88rem;
  color: var(--rose);
  line-height: 1.7;
  margin-bottom: 2rem;
  font-weight: 300;
}
.empty-state-cta {
  display: inline-block;
  padding: 12px 36px;
  background: var(--wine);
  color: var(--cream);
  border-radius: 1px;
  text-decoration: none;
  font-size: 0.72rem;
  font-weight: 500;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  transition: background 0.25s, letter-spacing 0.3s;
}
.empty-state-cta:hover {
  background: var(--burgundy);
  letter-spacing: 0.22em;
}

/* ──────────────────── FOOTER ──────────────────── */
.footer-strip {
  position: relative;
  text-align: center;
  padding: 2.5rem 1.5rem;
  font-size: 0.75rem;
  color: var(--rose);
  border-top: 1px solid var(--gold-light);
  margin-top: 5rem;
  letter-spacing: 0.08em;
  font-weight: 300;
}
.footer-strip::before {
  content: '— ✦ —';
  display: block;
  font-size: 0.7rem;
  color: var(--gold);
  margin-bottom: 0.5rem;
  letter-spacing: 0.2em;
}
.footer-strip strong {
  color: var(--burgundy);
  font-weight: 500;
  font-family: var(--serif);
  font-style: italic;
}

/* ──────────────────── IMAGE LOADING SHIMMER ──────────────────── */
.product-card-img.loading::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(
    105deg,
    var(--parchment) 25%,
    var(--blush) 50%,
    var(--parchment) 75%
  );
  background-size: 200% 100%;
  animation: shimmer 1.6s infinite;
}
@keyframes shimmer { to { background-position: -200% 0; } }

/* ──────────────────── RESPONSIVE ──────────────────── */
@media (max-width: 600px) {
  .topbar-logo-tagline { display: none; }
  .products-grid { grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; }
  .search-heading { font-size: 1.8rem; }
}
</style>
<?php include __DIR__ . '/pwa_head.php'; ?>
</head>
<body>

<!-- ── TOPBAR ── -->
<header class="topbar">
  <?php include "sidebar.php"; ?>

  <a href="index.php" class="topbar-logo">
    <img src="images/logo/SB.png" alt="Precious Satin Bouquet">
    <span class="topbar-logo-text">
      Precious Satin Bouquet
      <span class="topbar-logo-tagline">Fleur de Satin Collection</span>
    </span>
  </a>

  <form class="topbar-search" action="search.php" method="GET">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
    </svg>
    <input
      type="text"
      name="query"
      placeholder="Search our arrangements…"
      value="<?= htmlspecialchars($query) ?>"
      autofocus
    >
    <button class="search-submit-btn" type="submit" aria-label="Search">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="9 18 15 12 9 6"/>
      </svg>
    </button>
  </form>
</header>

<!-- ── MAIN ── -->
<main class="search-page">

  <div class="search-header">
    <div class="search-header-rule"></div>
    <span class="search-header-watermark">Precious Satin Bouquet</span>
    <?php if ($query !== ''): ?>
      <p class="search-eyebrow">Search Results</p>
      <h1 class="search-heading" style="color:#630088!important;-webkit-text-fill-color:#630088!important;">
        Blooms found for <span class="query-word" style="color:#630088!important;-webkit-text-fill-color:#630088!important;"><?= htmlspecialchars($query) ?></span>
      </h1>
      <p class="search-count">
        <span class="count-pill"><?= count($results) ?></span>
        arrangement<?= count($results) !== 1 ? 's' : '' ?> in our collection
      </p>
    <?php else: ?>
      <p class="search-eyebrow">Our Collection</p>
      <h1 class="search-heading">Search our <em>blooms</em></h1>
      <p class="search-count">Type something above to explore the collection</p>
    <?php endif; ?>
  </div>

  <div class="products-grid">
    <?php if (!empty($results)): ?>
      <?php foreach ($results as $row): ?>
      <div class="product-card reveal">
        <div class="product-card-img">
          <img
            src="images/<?= htmlspecialchars($row['image']) ?>"
            alt="<?= htmlspecialchars($row['name']) ?>"
            loading="lazy">
        </div>
        <div class="product-card-body">
          <h3 class="product-card-name"><?= htmlspecialchars($row['name']) ?></h3>
          <p class="product-card-desc"><?= htmlspecialchars(substr($row['description'], 0, 80)) ?>…</p>
          <div class="product-card-footer">
            <span class="product-card-price">&#8369;<?= number_format($row['price'], 2) ?></span>
            <a href="customize.php?id=<?= $row['id'] ?>" class="product-card-link">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/>
                <line x1="3" y1="6" x2="21" y2="6"/>
                <path d="M16 10a4 4 0 01-8 0"/>
              </svg>
              Add to Cart
            </a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>

    <?php elseif ($query !== ''): ?>
      <div class="empty-state">
        <span class="empty-petal">🌸</span>
        <h2>No blooms found</h2>
        <p>We couldn't find anything matching "<?= htmlspecialchars($query) ?>".<br>Try a different keyword or browse our full collection.</p>
        <a href="index.php" class="empty-state-cta">Browse All Arrangements</a>
      </div>
    <?php endif; ?>
  </div>

</main>

<footer class="footer-strip">
  © 2026 <strong>Precious Satin Bouquet</strong> · Fleur de Satin Collection · All rights reserved
</footer>

<script>
/* ── SCROLL REVEAL ── */
(function () {
  var observer = new IntersectionObserver(function(entries) {
    entries.forEach(function(e) {
      if (e.isIntersecting) {
        e.target.classList.add('visible');
        observer.unobserve(e.target);
      }
    });
  }, { threshold: 0.08 });
  document.querySelectorAll('.reveal').forEach(function(el) { observer.observe(el); });
})();

/* ── IMAGE LOADING STATES ── */
document.querySelectorAll('.product-card-img').forEach(function(wrapper) {
  var img = wrapper.querySelector('img');
  if (!img) return;
  wrapper.classList.add('loading');
  function onLoad()  { wrapper.classList.remove('loading'); }
  function onError() { wrapper.classList.remove('loading'); img.style.opacity = '0.4'; }
  if (img.complete) { img.naturalWidth ? onLoad() : onError(); }
  else { img.addEventListener('load', onLoad); img.addEventListener('error', onError); }
});
</script>
</body>
</html>