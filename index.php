<?php
include "config.php";

/* CATEGORY FILTER */
$category = $_GET['category'] ?? 'Satin'; // opens on Satin by default

// ── AJAX REQUEST: return only the products grid HTML ──
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    if ($category && $category !== 'all') {
        $stmt = $conn->prepare("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE c.name=? ORDER BY p.id DESC");
        $stmt->bind_param("s", $category);
        $stmt->execute();
        $result = $stmt->get_result();
    } else if ($category === 'all') {
        $result = $conn->query("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC");
    } else {
        $result = $conn->query("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY RAND() LIMIT 8");
    }
    if (!$result) { echo ''; exit; }

    header('Content-Type: application/json');
    $title = $category && $category !== 'all'
        ? htmlspecialchars(ucfirst($category))
        : ($category === 'all' ? 'All Arrangements' : 'Featured Blooms');

    ob_start();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $isReadymade = strtolower($row['category_name'] ?? '') === 'readymade';
            $productLink = $isReadymade
                ? "ready_made.php?id={$row['id']}"
                : "customize.php?id={$row['id']}";
            $btnLabel = $isReadymade ? 'View Bouquet' : 'Customize'; ?>
        <div class="product-card reveal">
          <div class="product-card-img">
            <img src="images/<?= htmlspecialchars($row['image']) ?>"
                 alt="<?= htmlspecialchars($row['name']) ?>"
                 loading="lazy">
            <?php if (!empty($row['category_name'])): ?>
              <span class="card-category-badge"><?= htmlspecialchars($row['category_name']) ?></span>
            <?php endif; ?>
            <div class="card-overlay">
              <a href="<?= $productLink ?>" class="card-overlay-btn"><?= $btnLabel ?></a>
            </div>
          </div>
          <div class="product-card-body">
            <h3 class="product-card-name"><?= htmlspecialchars($row['name']) ?></h3>
            <p class="product-card-desc"><?= htmlspecialchars(substr($row['description'], 0, 72)) ?>…</p>
            <div class="product-card-footer">
              <span class="product-card-price">&#8369;<?= number_format($row['price'], 2) ?></span>
              <a href="<?= $productLink ?>" class="product-card-link">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
                <?= $btnLabel ?>
              </a>
            </div>
          </div>
        </div>
        <?php }
    } else { ?>
        <div class="empty-state">
          <p>No blooms found in this collection.</p>
        </div>
    <?php }
    $cardsHtml = ob_get_clean();

    echo json_encode(['title' => $title, 'cards' => $cardsHtml]);
    exit;
}

// ── NORMAL PAGE LOAD ──
// Load categories dynamically from DB
$catRows = [];
$catResult = $conn->query("SELECT id, name FROM categories ORDER BY FIELD(name,'Satin') DESC, id ASC");
if ($catResult) {
    while ($c = $catResult->fetch_assoc()) {
        $catRows[] = $c;
    }
}

if ($category && $category !== 'all') {
    $stmt = $conn->prepare("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE c.name=? ORDER BY p.id DESC");
    $stmt->bind_param("s", $category);
    $stmt->execute();
    $result = $stmt->get_result();
} else if ($category === 'all') {
    $result = $conn->query("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC");
} else {
    $result = $conn->query("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY RAND() LIMIT 8");
}

if (!$result) {
    die("Query Error: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Precious Satin Bouquet</title>
<link rel="icon" type="image/png" href="images/logo/SB.png">
<link rel="shortcut icon" type="image/png" href="images/logo/SB.png">
<link rel="stylesheet" href="style.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Raleway:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
  /* ── RESET & BASE ── */
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    /* ── Pink Palette ── */
    --pk-lightest:  #faf0ff;   /* page background */
    --pk-light:     #f4e4fc;   /* cards, subtle fills */
    --pk-soft:      #ebc8f9;   /* blush mid-tone */
    --pk-mid:       #dda0f4;   /* petal / borders */
    --pk-base:      #ca72e8;   /* medium pink accents */
    --pk-deep:      #b44dd6;   /* active states, prices */
    --pk-dark:      #8e30b0;   /* headings, strong text */
    --pk-darkest:   #621a7b;   /* footer, deepest text */

    --font-serif: 'Cormorant Garamond', Georgia, serif;
    --font-sans:  'Raleway', sans-serif;
    --ease-out:   cubic-bezier(0.16, 1, 0.3, 1);
  }

  html { scroll-behavior: smooth; }

  body {
    background: var(--pk-lightest);
    color: var(--pk-darkest);
    font-family: var(--font-sans);
    font-weight: 300;
    overflow-x: hidden;
  }

  /* ── LOADER ── */
  #loader {
    position: fixed;
    inset: 0;
    background: linear-gradient(145deg, var(--pk-darkest) 0%, var(--pk-dark) 50%, var(--pk-base) 100%);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    transition: opacity 0.8s var(--ease-out), visibility 0.8s;
    overflow: hidden;
  }
  #loader.fade-out { opacity: 0; visibility: hidden; }

  #loader::before {
    content: '';
    position: absolute;
    width: 340px; height: 340px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(235,200,249,0.18) 0%, transparent 70%);
    top: -80px; right: -80px;
    pointer-events: none;
  }
  #loader::after {
    content: '';
    position: absolute;
    width: 260px; height: 260px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(221,160,244,0.14) 0%, transparent 70%);
    bottom: -60px; left: -60px;
    pointer-events: none;
  }

  .loader-bloom {
    width: 72px; height: 72px;
    margin-bottom: 28px;
    animation: loaderSpin 3s linear infinite;
    filter: drop-shadow(0 0 18px rgba(221,160,244,0.5));
  }
  @keyframes loaderSpin { to { transform: rotate(360deg); } }

  .loader-text {
    font-family: var(--font-serif);
    font-size: clamp(1.1rem, 3vw, 1.6rem);
    font-weight: 300;
    font-style: italic;
    letter-spacing: 0.38em;
    color: var(--pk-soft);
    text-transform: uppercase;
  }

  .loader-line {
    width: 0; height: 1px;
    background: linear-gradient(to right, transparent, var(--pk-mid), transparent);
    margin-top: 18px;
    animation: loaderLine 1.6s var(--ease-out) 0.3s forwards;
  }
  @keyframes loaderLine { to { width: 140px; } }

  /* ── HERO ── */
  #hero {
    position: relative;
    height: 100svh;
    min-height: 560px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    /* light → mid pink gradient — bright & airy */
    background: linear-gradient(160deg, #f4e4fc 0%, #ebc8f9 35%, #dda0f4 70%, #ca72e8 100%);
  }

  .hero-bg-texture {
    position: absolute;
    inset: 0;
    background-image:
      radial-gradient(ellipse 80% 60% at 60% 40%, rgba(255,255,255,0.22) 0%, transparent 60%),
      radial-gradient(ellipse 50% 70% at 20% 80%, rgba(142,48,176,0.10) 0%, transparent 55%),
      radial-gradient(ellipse 40% 50% at 85% 15%, rgba(235,200,249,0.18) 0%, transparent 50%);
    pointer-events: none;
  }

  .hero-eyebrow {
    font-family: var(--font-sans);
    font-size: 0.82rem;
    font-weight: 500;
    letter-spacing: 0.42em;
    text-transform: uppercase;
    color: var(--pk-dark);
    margin-bottom: 20px;
    opacity: 0;
    animation: fadeUp 1s var(--ease-out) 1.2s forwards;
  }

  .hero-title {
    font-family: var(--font-serif);
    font-size: clamp(3.2rem, 10vw, 9rem);
    font-weight: 300;
    font-style: italic;
    line-height: 0.92;
    color: var(--pk-darkest);
    text-align: center;
    letter-spacing: -0.01em;
    position: relative;
    z-index: 2;
    opacity: 0;
    animation: fadeUp 1.1s var(--ease-out) 1.4s forwards;
    text-shadow: 0 2px 40px rgba(142,48,176,0.12);
  }
  .hero-title span {
    display: block;
    color: var(--pk-dark);
  }

  .hero-sub {
    font-family: var(--font-sans);
    font-size: 0.95rem;
    font-weight: 400;
    letter-spacing: 0.22em;
    text-transform: uppercase;
    color: var(--pk-darkest);
    opacity: 0;
    margin-top: 28px;
    animation: fadeUp 1s var(--ease-out) 1.7s forwards;
  }

  .hero-cta {
    margin-top: 44px;
    opacity: 0;
    animation: fadeUp 1s var(--ease-out) 1.9s forwards;
  }
  .hero-cta a {
    display: inline-block;
    padding: 15px 44px;
    border: 1.5px solid var(--pk-dark);
    color: var(--pk-dark);
    background: rgba(255,255,255,0.25);
    backdrop-filter: blur(6px);
    font-family: var(--font-sans);
    font-size: 0.82rem;
    font-weight: 600;
    letter-spacing: 0.32em;
    text-transform: uppercase;
    text-decoration: none;
    border-radius: 3px;
    transition: background 0.3s, color 0.3s, border-color 0.3s;
  }
  .hero-cta a:hover {
    background: var(--pk-dark);
    color: #fff;
    border-color: var(--pk-dark);
  }

  .hero-scroll-hint {
    position: absolute;
    bottom: 32px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    opacity: 0;
    animation: fadeUp 1s var(--ease-out) 2.3s forwards;
  }
  .hero-scroll-hint span {
    font-size: 0.72rem;
    letter-spacing: 0.3em;
    text-transform: uppercase;
    color: var(--pk-darkest);
    opacity: 0.5;
  }
  .scroll-line {
    width: 1px; height: 40px;
    background: linear-gradient(to bottom, var(--pk-dark), transparent);
    animation: scrollPulse 2s ease-in-out infinite;
  }
  @keyframes scrollPulse {
    0%,100% { opacity: 0.4; transform: scaleY(1); }
    50%      { opacity: 1;   transform: scaleY(1.15); }
  }

  /* petal canvas */
  .hero-petals-canvas {
    position: absolute;
    inset: 0;
    pointer-events: none;
    overflow: hidden;
  }
  .hero-petal-wrap {
    position: absolute;
    top: -60px;
    animation: petalFall linear infinite;
  }
  .hero-petal { animation: petalSway ease-in-out infinite alternate; }
  @keyframes petalFall {
    from { transform: translateY(0); }
    to   { transform: translateY(110vh); }
  }
  @keyframes petalSway {
    from { transform: rotate(-18deg) translateX(-8px); }
    to   { transform: rotate(18deg)  translateX(8px); }
  }

  /* ── CATEGORY NAV ── */
  .category-nav {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-wrap: wrap;
    gap: 10px;
    padding: 28px 40px;
    position: sticky;
    top: 76px;
    z-index: 90;
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    background: rgba(244,228,252,0.92);
    border-bottom: 1px solid rgba(180,77,214,0.14);
    box-shadow: 0 2px 16px rgba(142,48,176,0.07);
  }

  .category-nav a {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 9px 22px;
    font-family: var(--font-sans);
    font-size: 0.8rem;
    font-weight: 500;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    text-decoration: none;
    white-space: nowrap;
    color: var(--pk-dark);
    border-radius: 50px;
    border: 1.5px solid rgba(180,77,214,0.25);
    background: rgba(255,255,255,0.65);
    transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
    box-shadow: 0 2px 8px rgba(142,48,176,0.06);
    position: relative;
    overflow: hidden;
  }

  .category-nav a:hover {
    color: var(--pk-darkest);
    border-color: var(--pk-deep);
    background: rgba(255,255,255,0.9);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(180,77,214,0.18);
  }

  .category-nav a.active {
    color: #fff;
    background: linear-gradient(135deg, var(--pk-deep) 0%, var(--pk-dark) 100%);
    border-color: transparent;
    box-shadow: 0 6px 22px rgba(180,77,214,0.38);
    font-weight: 600;
    transform: translateY(-1px);
  }

  .category-nav a.active::after {
    content: '✿';
    font-size: 0.7rem;
    opacity: 0.8;
    margin-left: 4px;
  }

  /* ── CATEGORY BUTTON CLICK EFFECTS ── */

  /* Press-down scale on click */
  .category-nav a:active {
    transform: scale(0.93) translateY(1px);
    transition: transform 0.08s ease;
  }

  /* Ripple element injected via JS */
  .cat-ripple {
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.55);
    transform: scale(0);
    animation: catRippleAnim 0.55s cubic-bezier(0.22, 0.61, 0.36, 1) forwards;
    pointer-events: none;
  }
  @keyframes catRippleAnim {
    to {
      transform: scale(4);
      opacity: 0;
    }
  }

  /* Bloom-burst glow ring on active */
  .category-nav a.active {
    animation: activeBloom 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
  }
  @keyframes activeBloom {
    0%   { box-shadow: 0 0 0 0 rgba(180,77,214,0.55), 0 6px 22px rgba(180,77,214,0.38); }
    50%  { box-shadow: 0 0 0 8px rgba(180,77,214,0.12), 0 6px 22px rgba(180,77,214,0.38); }
    100% { box-shadow: 0 0 0 0 rgba(180,77,214,0), 0 6px 22px rgba(180,77,214,0.38); }
  }

  /* Petal pop particles (injected via JS) */
  .cat-petal-pop {
    position: fixed;
    pointer-events: none;
    z-index: 9999;
    font-size: 0.85rem;
    animation: petalPopAnim 0.7s var(--ease-out) forwards;
  }
  @keyframes petalPopAnim {
    0%   { opacity: 1; transform: translate(0, 0) scale(1) rotate(0deg); }
    100% { opacity: 0; transform: translate(var(--tx), var(--ty)) scale(0.4) rotate(var(--tr)); }
  }

  /* ── SECTION HEADER ── */
  .section-header {
    text-align: center;
    padding: 72px 24px 48px;
  }
  .section-eyebrow {
    font-size: 0.78rem;
    letter-spacing: 0.4em;
    text-transform: uppercase;
    color: var(--pk-deep);
    margin-bottom: 14px;
  }
  .section-title {
    font-family: var(--font-serif);
    font-size: clamp(2rem, 5vw, 3.6rem);
    font-weight: 300;
    font-style: italic;
    color: var(--pk-darkest);
    line-height: 1.1;
  }
  .section-rule {
    width: 60px; height: 2px;
    background: linear-gradient(to right, var(--pk-mid), var(--pk-deep));
    margin: 20px auto 0;
    border-radius: 2px;
  }

  /* ── PRODUCT GRID ── */
  .products-section {
    padding: 0 clamp(16px, 4vw, 64px) 96px;
    max-width: 1440px;
    margin: 0 auto;
  }
  .products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
    gap: 24px;
  }

  /* ── PRODUCT CARD ── */
  .product-card {
    position: relative;
    background: #fff;
    border: 1px solid rgba(180,77,214,0.18);
    border-radius: 20px;
    overflow: hidden;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    transition: transform 0.25s var(--ease-out), box-shadow 0.25s var(--ease-out), border-color 0.25s;
  }
  .product-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 20px 48px rgba(142,48,176,0.13);
    border-color: var(--pk-mid);
    z-index: 2;
  }

  .product-card-img {
    position: relative;
    aspect-ratio: 4/3;
    overflow: hidden;
    background: var(--pk-light);
    border-radius: 20px 20px 0 0;
  }
  .product-card-img img {
    width: 100%; height: 100%;
    object-fit: cover;
    transition: transform 0.6s var(--ease-out);
    display: block;
  }
  .product-card:hover .product-card-img img { transform: scale(1.06); }

  .card-category-badge {
    position: absolute;
    top: 14px; left: 14px;
    background: rgba(255,255,255,0.92);
    backdrop-filter: blur(6px);
    padding: 5px 13px;
    font-size: 0.7rem;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: var(--pk-deep);
    font-weight: 600;
    border-radius: 30px;
    border: 1px solid rgba(180,77,214,0.18);
  }

  /* No hover overlay — button is always visible in footer */
  .card-overlay { display: none; }

  .product-card-body {
    padding: 20px 22px 24px;
    border-top: 1px solid var(--pk-light);
    background: #fff;
    display: flex;
    flex-direction: column;
    flex: 1;
    border-radius: 0 0 20px 20px;
  }

  .product-card-name {
    font-family: var(--font-serif);
    font-size: 1.55rem;
    font-weight: 400;
    color: #1a1a1a;
    margin-bottom: 8px;
    line-height: 1.15;
  }

  .product-card-desc {
    font-size: 0.95rem;
    color: rgba(0, 0, 0, 0.68);
    line-height: 1.6;
    flex: 1;
    margin-bottom: 18px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }

  .product-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding-top: 16px;
    border-top: 1px solid var(--pk-light);
    flex-wrap: wrap;
  }

  .product-card-price {
    font-family: var(--font-serif);
    font-size: 1.4rem;
    font-weight: 600;
    color: #1a1a1a;
    letter-spacing: -0.3px;
    min-width: 0;
    flex-shrink: 1;
  }

  .product-card-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #1a1a1a;
    color: #fff;
    font-family: var(--font-sans);
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    text-decoration: none;
    padding: 10px 16px;
    border-radius: 40px;
    white-space: nowrap;
    flex-shrink: 0;
    transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
    box-shadow: 0 2px 8px rgba(0,0,0,0.18);
  }
  .product-card-link:hover {
    background: var(--pk-darkest);
    transform: scale(1.04);
    box-shadow: 0 4px 16px rgba(98,26,123,0.28);
  }

  /* ── IMAGE PLACEHOLDER (while loading / broken image) ── */
  .product-card-img img[src="images/"] ,
  .product-card-img img:not([src]),
  .product-card-img img.img-error {
    opacity: 0;
  }
  .product-card-img::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
      linear-gradient(135deg, var(--pk-light) 0%, var(--pk-soft) 50%, var(--pk-light) 100%);
    z-index: 0;
  }
  .product-card-img img {
    position: relative;
    z-index: 1;
  }
  /* Shimmer skeleton while image loads */
  .product-card-img.loading::after {
    content: '';
    position: absolute;
    inset: 0;
    z-index: 2;
    background: linear-gradient(
      90deg,
      transparent 0%,
      rgba(255,255,255,0.45) 50%,
      transparent 100%
    );
    background-size: 200% 100%;
    animation: shimmer 1.4s infinite;
  }
  @keyframes shimmer {
    from { background-position: -200% 0; }
    to   { background-position:  200% 0; }
  }

  /* ── EMPTY STATE ── */
  .empty-state {
    text-align: center;
    padding: 96px 24px;
    grid-column: 1 / -1;
  }
  .empty-state p {
    font-family: var(--font-serif);
    font-size: 1.4rem;
    font-style: italic;
    color: rgba(142,48,176,0.3);
  }

  /* ── FOOTER STRIP ── */
  .footer-strip {
    background: linear-gradient(180deg, #f6e8fc 0%, #e9c5f7 100%);
    color: #a651c4;
    text-align: center;
    padding: 28px 24px;
    font-size: 0.78rem;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    border-top: 2px solid #dda0f4;
  }
  .footer-strip span { color: #ca72e8; }

  /* ── ANIMATIONS ── */
  @keyframes fadeUp {
    from { opacity: 0; transform: translateY(22px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  .reveal {
    opacity: 0;
    transform: translateY(30px);
    transition: opacity 0.7s var(--ease-out), transform 0.7s var(--ease-out);
  }
  .reveal.visible { opacity: 1; transform: none; }

  .products-grid .product-card:nth-child(1)  { transition-delay: 0.05s; }
  .products-grid .product-card:nth-child(2)  { transition-delay: 0.10s; }
  .products-grid .product-card:nth-child(3)  { transition-delay: 0.15s; }
  .products-grid .product-card:nth-child(4)  { transition-delay: 0.20s; }
  .products-grid .product-card:nth-child(5)  { transition-delay: 0.25s; }
  .products-grid .product-card:nth-child(6)  { transition-delay: 0.30s; }
  .products-grid .product-card:nth-child(7)  { transition-delay: 0.35s; }
  .products-grid .product-card:nth-child(8)  { transition-delay: 0.40s; }
</style>
<?php include __DIR__ . '/pwa_head.php'; ?>
</head>
<body>

<!-- ── LOADER ── -->
<div id="loader">
  <svg class="loader-bloom" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
    <g opacity="0.95">
      <ellipse cx="32" cy="18" rx="7" ry="14" fill="#ecc6f9" transform="rotate(0 32 32)"/>
      <ellipse cx="32" cy="18" rx="7" ry="14" fill="#dda0f4" transform="rotate(60 32 32)"/>
      <ellipse cx="32" cy="18" rx="7" ry="14" fill="#ca72e8" transform="rotate(120 32 32)"/>
      <ellipse cx="32" cy="18" rx="7" ry="14" fill="#ecc6f9" transform="rotate(180 32 32)"/>
      <ellipse cx="32" cy="18" rx="7" ry="14" fill="#dda0f4" transform="rotate(240 32 32)"/>
      <ellipse cx="32" cy="18" rx="7" ry="14" fill="#ca72e8" transform="rotate(300 32 32)"/>
      <circle cx="32" cy="32" r="6" fill="#f2d6ff"/>
    </g>
  </svg>
  <div class="loader-text">Precious Satin Bouquet</div>
  <div class="loader-line"></div>
</div>

<?php include "header.php"; ?>

<!-- ── HERO ── -->
<section id="hero">
  <div class="hero-bg-texture"></div>
  <div class="hero-petals-canvas" id="petalsCanvas"></div>

  <p class="hero-eyebrow"> Est. 2026</p>
  <h1 class="hero-title">
    Fleur de Satin <br>
    <span>Collection</span>
  </h1>
  <p class="hero-sub">Curated bouquets for every occasion</p>
  <div class="hero-cta">
    <a href="#products">Explore Collection</a>
  </div>

  <div class="hero-scroll-hint">
    <div class="scroll-line"></div>
    <span></span>
  </div>
</section>

<!-- ── CATEGORY NAV ── -->
<nav class="category-nav" id="products">
  <?php foreach ($catRows as $cat): ?>
    <a href="index.php?category=<?= urlencode($cat['name']) ?>"
       data-category="<?= htmlspecialchars($cat['name']) ?>"
       class="cat-btn <?= $category == $cat['name'] ? 'active' : '' ?>">
      <?= htmlspecialchars($cat['name']) ?>
    </a>
  <?php endforeach; ?>
  <!-- All button removed -->
</nav>

<!-- ── PRODUCTS ── -->
<div class="products-section">
  <div class="section-header">
    <p class="section-eyebrow">Our Collection</p>
    <h2 class="section-title">
      <?php if ($category && $category !== 'all'): ?>
        <?= htmlspecialchars(ucfirst($category)) ?>
      <?php elseif ($category === 'all'): ?>
        All Arrangements
      <?php else: ?>
        Featured Blooms
      <?php endif; ?>
    </h2>
    <div class="section-rule"></div>
  </div>

  <div class="products-grid">
    <?php if ($result->num_rows > 0): ?>
      <?php while($row = $result->fetch_assoc()):
          $isReadymade = strtolower($row['category_name'] ?? '') === 'readymade';
          $productLink = $isReadymade
              ? "ready_made.php?id={$row['id']}"
              : "customize.php?id={$row['id']}";
          $btnLabel = $isReadymade ? 'View Bouquet' : 'Customize';
        ?>
        <div class="product-card reveal">
          <div class="product-card-img">
            <img src="images/<?= htmlspecialchars($row['image']) ?>"
                 alt="<?= htmlspecialchars($row['name']) ?>"
                 loading="lazy">
            <?php if (!empty($row['category_name'])): ?>
              <span class="card-category-badge"><?= htmlspecialchars($row['category_name']) ?></span>
            <?php endif; ?>
            <div class="card-overlay">
              <a href="<?= $productLink ?>" class="card-overlay-btn"><?= $btnLabel ?></a>
            </div>
          </div>
          <div class="product-card-body">
            <h3 class="product-card-name"><?= htmlspecialchars($row['name']) ?></h3>
            <p class="product-card-desc"><?= htmlspecialchars(substr($row['description'], 0, 72)) ?>…</p>
            <div class="product-card-footer">
              <span class="product-card-price">&#8369;<?= number_format($row['price'], 2) ?></span>
              <a href="<?= $productLink ?>" class="product-card-link">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 001.99 1.61h9.72a2 2 0 001.99-1.61L23 6H6"/></svg>
                <?= $btnLabel ?>
              </a>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="empty-state">
        <p>No blooms found in this collection.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ── FOOTER STRIP ── -->
<footer class="footer-strip">
  © 2026 <span>Precious Satin Bouquet</span> · Fleur de Satin Collection · All rights reserved
</footer>

<?php
// ── WELCOME POPUP ──
// Only fires once right after login; clears the flag immediately
$showWelcome = !empty($_SESSION['just_logged_in']);
$isNewUser   = !empty($_SESSION['is_new_user']);
$welcomeName = htmlspecialchars($_SESSION['username'] ?? 'there');
unset($_SESSION['just_logged_in'], $_SESSION['is_new_user']);
?>

<?php if ($showWelcome): ?>
<!-- ══ WELCOME POPUP ══ -->
<div id="wOverlay" role="dialog" aria-modal="true" aria-label="Welcome">

  <!-- Ambient floating petals BEHIND the card -->
  <canvas id="wCanvas" aria-hidden="true"></canvas>

  <div id="wCard">

    <!-- Decorative SVG bloom at top -->
    <div id="wBloomWrap" aria-hidden="true">
      <svg id="wBloomSvg" viewBox="-60 -60 120 120" xmlns="http://www.w3.org/2000/svg">
        <!-- outer ring petals -->
        <g id="wOuterPetals">
          <?php for($i=0;$i<8;$i++): $angle = $i*45; ?>
          <ellipse rx="24" ry="9" fill="<?= $isNewUser ? '#a8e6cf' : '#edc0ff' ?>" opacity="0.82"
            transform="rotate(<?= $angle ?>)" />
          <?php endfor; ?>
        </g>
        <!-- inner ring -->
        <g id="wInnerPetals">
          <?php for($i=0;$i<6;$i++): $angle = $i*60 + 22.5; ?>
          <ellipse rx="16" ry="6" fill="<?= $isNewUser ? '#52c788' : '#de85ff' ?>" opacity="0.90"
            transform="rotate(<?= $angle ?>)" />
          <?php endfor; ?>
        </g>
        <!-- center -->
        <circle r="11" fill="<?= $isNewUser ? '#f8f0ff' : '#faf0ff' ?>"/>
        <circle r="7"  fill="<?= $isNewUser ? '#f9c74f' : '#e9b3ff' ?>"/>
        <circle r="3.5" fill="<?= $isNewUser ? '#f8961e' : '#c82dff' ?>"/>
      </svg>
    </div>

    <!-- Sparkle dots -->
    <div id="wSparkles" aria-hidden="true">
      <span class="wSpark" style="top:18%;left:12%;animation-delay:0s"></span>
      <span class="wSpark" style="top:14%;right:14%;animation-delay:.4s"></span>
      <span class="wSpark" style="top:40%;left:5%;animation-delay:.8s"></span>
      <span class="wSpark" style="top:38%;right:6%;animation-delay:.2s"></span>
      <span class="wSpark" style="bottom:22%;left:10%;animation-delay:.6s"></span>
      <span class="wSpark" style="bottom:20%;right:9%;animation-delay:.3s"></span>
    </div>

    <!-- Thin decorative rule -->
    <div id="wRule"><span></span></div>

    <!-- Badge -->
    <div id="wBadge"><?= $isNewUser ? '✦ NEW MEMBER ✦' : '✦ WELCOME BACK ✦' ?></div>

    <!-- Heading -->
    <h2 id="wHeading">
      <?= $isNewUser
        ? '<em>Welcome</em> to<br>Precious Satin Bouquet'
        : 'Lovely to<br>see you again'
      ?>
    </h2>

    <!-- Name highlight -->
    <div id="wName"><?= $welcomeName ?></div>

    <!-- Divider line with flower -->
    <div id="wDivider"><span></span><span class="wDivFlower"><?= $isNewUser ? '✿' : '❀' ?></span><span></span></div>

    <!-- Subtitle -->
    <p id="wSub">
      <?= $isNewUser
        ? 'Every petal tells a story — yours starts today. Explore our handcrafted arrangements and find your signature bloom.'
        : 'Your petals have been patiently waiting. Step in, breathe deep, and let us brighten your day.'
      ?>
    </p>

    <!-- CTA button -->
    <button id="wBtn" onclick="wClose()">
      <span id="wBtnText"><?= $isNewUser ? 'Begin Your Journey' : 'Explore Blooms' ?></span>
      <span id="wBtnIcon">→</span>
    </button>

    <!-- Dismiss hint -->
    <p id="wDismiss">Press <kbd>Esc</kbd> or click outside to dismiss</p>

  </div>
</div>

<style>
/* ── Overlay ── */
#wOverlay {
  position: fixed; inset: 0; z-index: 99999;
  display: flex; align-items: center; justify-content: center;
  background: radial-gradient(ellipse at 40% 40%, rgba(91,10,120,0.72) 0%, rgba(29,5,40,0.88) 100%);
  backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
  animation: wFadeIn .5s ease both;
  padding: 1rem;
}
@keyframes wFadeIn { from{opacity:0} to{opacity:1} }

/* ── Petal canvas (full-screen behind card) ── */
#wCanvas {
  position: absolute; inset: 0;
  width: 100%; height: 100%;
  pointer-events: none;
}

/* ── Card ── */
#wCard {
  position: relative; z-index: 2;
  background:
    radial-gradient(ellipse at 20% 0%,   rgba(237,200,255,0.28) 0%, transparent 55%),
    radial-gradient(ellipse at 80% 100%, rgba(220,150,255,0.18) 0%, transparent 55%),
    linear-gradient(160deg, #fdf8ff 0%, #f8eefd 45%, #f9f2fd 100%);
  border: 1px solid rgba(233,180,255,0.45);
  border-radius: 36px;
  padding: 0 2.8rem 2.6rem;
  max-width: 460px; width: 100%;
  text-align: center;
  box-shadow:
    0 2px 0 rgba(255,255,255,0.95) inset,
    0 -1px 0 rgba(164,80,200,0.15) inset,
    0 40px 100px rgba(124,30,160,0.35),
    0 8px 30px rgba(164,80,200,0.20);
  animation: wCardIn .65s cubic-bezier(0.34,1.4,0.64,1) .1s both;
  overflow: hidden;
}
@keyframes wCardIn {
  from { opacity:0; transform: translateY(40px) scale(0.88) rotateX(6deg); }
  to   { opacity:1; transform: translateY(0)    scale(1)    rotateX(0deg); }
}

/* ── Top bloom ── */
#wBloomWrap {
  margin: -20px auto 0;
  width: 110px; height: 110px;
  display: flex; align-items: center; justify-content: center;
  animation: wBloomIn .8s cubic-bezier(0.34,1.56,0.64,1) .35s both;
  filter: drop-shadow(0 6px 20px rgba(163,60,200,0.38));
}
@keyframes wBloomIn {
  from { opacity:0; transform: scale(0.3) rotate(-30deg); }
  to   { opacity:1; transform: scale(1)   rotate(0deg);   }
}
#wBloomSvg { width: 100%; height: 100%; overflow: visible; }
#wOuterPetals { animation: wBloomSpin 12s linear infinite; transform-origin: 0 0; }
#wInnerPetals { animation: wBloomSpin 8s linear infinite reverse; transform-origin: 0 0; }
@keyframes wBloomSpin { to { transform: rotate(360deg); } }

/* ── Sparkles ── */
#wSparkles { position: absolute; inset: 0; pointer-events: none; }
.wSpark {
  position: absolute;
  width: 6px; height: 6px;
  border-radius: 50%;
  background: radial-gradient(circle, #f1ccff 0%, rgba(233,180,255,0) 70%);
  animation: wSparkPulse 2.5s ease-in-out infinite;
  box-shadow: 0 0 6px 3px rgba(220,150,255,0.5);
}
@keyframes wSparkPulse {
  0%,100% { opacity:.2; transform: scale(0.6); }
  50%     { opacity:1;  transform: scale(1.4); }
}

/* ── Decorative top rule ── */
#wRule { display:flex; align-items:center; justify-content:center; margin: 0 auto 1.1rem; }
#wRule span {
  display:block; width:60px; height:1px;
  background: linear-gradient(90deg, transparent, rgba(180,77,214,0.6), transparent);
}

/* ── Badge ── */
#wBadge {
  font-family: 'Jost', 'Raleway', sans-serif;
  font-size: 0.65rem; font-weight: 600;
  letter-spacing: 0.28em; text-transform: uppercase;
  color: #b44dd6;
  background: rgba(180,77,214,0.10);
  border: 1px solid rgba(180,77,214,0.25);
  border-radius: 50px;
  padding: 5px 18px;
  display: inline-block;
  margin-bottom: 1.1rem;
  animation: wSlideUp .5s ease .55s both;
}

/* ── Heading ── */
#wHeading {
  font-family: 'Cormorant Garamond', Georgia, serif;
  font-size: clamp(2rem, 6vw, 2.9rem);
  font-weight: 300;
  line-height: 1.18;
  color: #621a7b;
  margin-bottom: .6rem;
  animation: wSlideUp .5s ease .65s both;
}
#wHeading em { font-style: italic; font-weight: 600; color: #9018c9; }

/* ── Name ── */
#wName {
  font-family: 'Cormorant Garamond', Georgia, serif;
  font-size: clamp(1.15rem, 3.5vw, 1.4rem);
  font-weight: 400; font-style: italic;
  color: #8e30b0;
  margin-bottom: 1.1rem;
  animation: wSlideUp .5s ease .72s both;
  opacity: 0.9;
}

/* ── Divider ── */
#wDivider {
  display: flex; align-items: center; gap: 10px;
  justify-content: center; margin-bottom: 1.1rem;
  animation: wSlideUp .5s ease .78s both;
}
#wDivider span:not(.wDivFlower) {
  display:block; flex:1; max-width:60px; height:1px;
  background: linear-gradient(90deg, transparent, rgba(164,80,200,0.35), transparent);
}
.wDivFlower { font-size: 1rem; color: #ca72e8; line-height:1; }

/* ── Subtitle ── */
#wSub {
  font-family: 'Raleway', sans-serif;
  font-size: 0.9rem; font-weight: 300; line-height: 1.75;
  color: #79309b; opacity: .85;
  margin-bottom: 1.9rem; max-width: 340px; margin-left: auto; margin-right: auto;
  animation: wSlideUp .5s ease .84s both;
}

/* ── CTA Button ── */
#wBtn {
  position: relative; overflow: hidden;
  display: inline-flex; align-items: center; gap: 10px;
  background: linear-gradient(135deg, #ca72e8 0%, #9018c9 55%, #6d129b 100%);
  color: #fff; border: none; border-radius: 60px;
  padding: 1rem 2.6rem;
  font-family: 'Raleway', 'Jost', sans-serif;
  font-size: 0.88rem; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase;
  cursor: pointer;
  box-shadow: 0 10px 32px rgba(133,24,180,0.45), 0 2px 0 rgba(255,255,255,0.18) inset;
  transition: transform .25s cubic-bezier(0.34,1.56,0.64,1), box-shadow .25s;
  animation: wSlideUp .5s ease .9s both;
}
#wBtn::before {
  content:''; position:absolute; inset:0;
  background: linear-gradient(135deg, rgba(255,255,255,0.18) 0%, transparent 60%);
  pointer-events: none;
}
#wBtn::after {
  content:''; position:absolute;
  width:200%; height:200%; top:-50%; left:-110%;
  background: linear-gradient(105deg, transparent 30%, rgba(255,255,255,0.25) 50%, transparent 70%);
  transform: skewX(-20deg);
  transition: left .5s ease;
  pointer-events: none;
}
#wBtn:hover::after { left:110%; }
#wBtn:hover { transform: translateY(-3px) scale(1.04); box-shadow: 0 18px 44px rgba(133,24,180,0.52), 0 2px 0 rgba(255,255,255,0.18) inset; }
#wBtn:active { transform: scale(0.97); }
#wBtnIcon { font-size: 1.1rem; transition: transform .25s; }
#wBtn:hover #wBtnIcon { transform: translateX(4px); }

/* ── Dismiss hint ── */
#wDismiss {
  font-family: 'Jost', sans-serif; font-size: 0.68rem;
  color: rgba(121,48,155,0.45); margin-top: 1.1rem; letter-spacing: 0.06em;
  animation: wSlideUp .4s ease 1.1s both;
}
#wDismiss kbd {
  display:inline-block; font-family: inherit; font-size: 0.65rem;
  border:1px solid rgba(164,80,200,0.3); border-radius:4px;
  padding: 1px 5px; background: rgba(164,80,200,0.07);
}

/* ── Shared slide-up ── */
@keyframes wSlideUp {
  from { opacity:0; transform: translateY(14px); }
  to   { opacity:1; transform: translateY(0); }
}

/* ── Exit ── */
#wOverlay.wOut { animation: wFadeOut .4s ease forwards; }
@keyframes wFadeOut { to { opacity:0; transform: scale(0.97); } }
</style>

<script>
(function(){
  /* ── Petal canvas ── */
  var cv  = document.getElementById('wCanvas');
  var ctx = cv.getContext('2d');
  var W, H, petals = [];

  function resize(){
    W = cv.width  = window.innerWidth;
    H = cv.height = window.innerHeight;
  }
  resize();
  window.addEventListener('resize', resize);

  var COLORS = <?= $isNewUser
    ? "['#a8e6cf','#74c69d','#52b788','#95d5b2','#b7e4c7']"
    : "['#e9b3ff','#de85ff','#f0c8ff','#db5cff','#ebacff']" ?>;

  function Petal(){
    this.x     = Math.random() * 1600 - 300;
    this.y     = Math.random() * -200 - 30;
    this.r     = 6 + Math.random() * 11;
    this.ry    = this.r * (0.45 + Math.random() * 0.35);
    this.rot   = Math.random() * Math.PI * 2;
    this.vx    = (Math.random() - 0.5) * 1.2;
    this.vy    = 1.2 + Math.random() * 2.2;
    this.vr    = (Math.random() - 0.5) * 0.06;
    this.alpha = 0.5 + Math.random() * 0.4;
    this.color = COLORS[Math.floor(Math.random()*COLORS.length)];
  }
  Petal.prototype.update = function(){
    this.x   += this.vx + Math.sin(this.y * 0.012) * 0.6;
    this.y   += this.vy;
    this.rot += this.vr;
    if(this.y > H + 20) { this.y = -30; this.x = Math.random() * W; }
  };
  Petal.prototype.draw = function(){
    ctx.save();
    ctx.translate(this.x, this.y);
    ctx.rotate(this.rot);
    ctx.globalAlpha = this.alpha;
    ctx.fillStyle   = this.color;
    ctx.beginPath();
    ctx.ellipse(0, 0, this.r, this.ry, 0, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();
  };

  for(var i=0; i<55; i++) petals.push(new Petal());

  var raf;
  function loop(){
    ctx.clearRect(0,0,W,H);
    for(var i=0;i<petals.length;i++){ petals[i].update(); petals[i].draw(); }
    raf = requestAnimationFrame(loop);
  }
  loop();

  /* ── Close ── */
  window.wClose = function(){
    cancelAnimationFrame(raf);
    var ov = document.getElementById('wOverlay');
    ov.classList.add('wOut');
    ov.addEventListener('animationend', function(){ ov.remove(); }, {once:true});
  };

  document.getElementById('wOverlay').addEventListener('click', function(e){
    if(e.target === this) wClose();
  });
  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape') wClose();
  });
})();
</script>
<?php endif; ?>

<script src="script.js"></script>
<script>
/* ── LOADER: only on true reload ── */
(function () {
  const loader = document.getElementById('loader');
  const isReload = (
    performance.navigation?.type === 1 ||
    sessionStorage.getItem('sb_visited') === null
  );
  if (isReload) {
    sessionStorage.setItem('sb_visited', '1');
    window.addEventListener('load', () => {
      setTimeout(() => loader.classList.add('fade-out'), 1400);
    });
  } else {
    loader.classList.add('fade-out');
  }
})();

/* ── FALLING PETALS ── */
(function () {
  const canvas = document.getElementById('petalsCanvas');
  const petalPairs = [
    ['#f2d6ff','#e2a8f9'],['#eab6ff','#ce7af0'],
    ['#ecc6f9','#bb50e8'],['#dc99ff','#aa40d4'],
    ['#f5e0ff','#c67ae8'],['#dda0f4','#a747c2'],
    ['#f8eaff','#d88af4'],['#f1ccff','#bc5ae0'],
  ];

  function makePetal(w, h, c1, c2) {
    const uid = 'g' + Math.random().toString(36).slice(2, 8);
    const shapes = [
      '<ellipse cx="'+w/2+'" cy="'+h/2+'" rx="'+w/2+'" ry="'+h/2+'" fill="url(#'+uid+')"/>',
      '<path d="M'+w/2+' 0 Q'+w+' '+(h*.3)+' '+w/2+' '+h+' Q0 '+(h*.3)+' '+w/2+' 0Z" fill="url(#'+uid+')"/>',
      '<path d="M'+w/2+' 0 C'+(w*1.1)+' '+(h*.1)+' '+(w*.9)+' '+(h*.9)+' '+w/2+' '+h+' C'+(w*.1)+' '+(h*.9)+' '+(-w*.1)+' '+(h*.1)+' '+w/2+' 0Z" fill="url(#'+uid+')"/>',
    ];
    return '<svg xmlns="http://www.w3.org/2000/svg" width="'+w+'" height="'+h+'" viewBox="0 0 '+w+' '+h+'">'
      +'<defs><radialGradient id="'+uid+'" cx="40%" cy="35%" r="65%">'
      +'<stop offset="0%" stop-color="'+c1+'" stop-opacity="0.9"/>'
      +'<stop offset="100%" stop-color="'+c2+'" stop-opacity="0.65"/>'
      +'</radialGradient></defs>'+shapes[Math.floor(Math.random()*shapes.length)]+'</svg>';
  }

  for (let i = 0; i < 32; i++) {
    const wrap = document.createElement('div');
    wrap.className = 'hero-petal-wrap';
    const sd = 2.5 + Math.random() * 4;
    wrap.style.cssText = 'left:'+Math.random()*100+'%;animation-duration:'+sd+'s;animation-delay:'+-(Math.random()*sd)+'s;';
    const petal = document.createElement('div');
    petal.className = 'hero-petal';
    const w = 10 + Math.random() * 14;
    const h = w * (1.2 + Math.random() * 0.6);
    const fd = 4 + Math.random() * 5;
    const pair = petalPairs[Math.floor(Math.random() * petalPairs.length)];
    petal.style.cssText = 'width:'+w+'px;height:'+h+'px;animation-duration:'+fd+'s;animation-delay:'+-(Math.random()*8)+'s;display:block;';
    petal.innerHTML = makePetal(Math.round(w), Math.round(h), pair[0], pair[1]);
    wrap.appendChild(petal);
    canvas.appendChild(wrap);
  }
})();

/* ── IMAGE LOADING STATES ── */
function initImageHandlers() {
  document.querySelectorAll('.product-card-img').forEach(function(wrapper) {
    var img = wrapper.querySelector('img');
    if (!img) return;
    wrapper.classList.add('loading');
    function onLoad() { wrapper.classList.remove('loading'); }
    function onError() {
      wrapper.classList.remove('loading');
      img.classList.add('img-error');
      if (!wrapper.querySelector('.img-placeholder-svg')) {
        var svg = document.createElementNS('http://www.w3.org/2000/svg','svg');
        svg.setAttribute('viewBox','0 0 120 120');
        svg.setAttribute('class','img-placeholder-svg');
        svg.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;z-index:1;padding:28px;opacity:0.35;';
        svg.innerHTML = '<g fill="none"><ellipse cx="60" cy="40" rx="10" ry="20" fill="#ca72e8" transform="rotate(0 60 60)"/><ellipse cx="60" cy="40" rx="10" ry="20" fill="#b44dd6" transform="rotate(60 60 60)"/><ellipse cx="60" cy="40" rx="10" ry="20" fill="#dda0f4" transform="rotate(120 60 60)"/><ellipse cx="60" cy="40" rx="10" ry="20" fill="#ca72e8" transform="rotate(180 60 60)"/><ellipse cx="60" cy="40" rx="10" ry="20" fill="#b44dd6" transform="rotate(240 60 60)"/><ellipse cx="60" cy="40" rx="10" ry="20" fill="#dda0f4" transform="rotate(300 60 60)"/><circle cx="60" cy="60" r="9" fill="#f4e4fc"/></g>';
        wrapper.appendChild(svg);
      }
    }
    if (img.complete) { img.naturalWidth ? onLoad() : onError(); }
    else { img.addEventListener('load', onLoad); img.addEventListener('error', onError); }
  });
}
initImageHandlers();

/* ── SCROLL REVEAL ── */
function initReveal() {
  var revealObserver = new IntersectionObserver(function(entries) {
    entries.forEach(function(e) { if (e.isIntersecting) { e.target.classList.add('visible'); } });
  }, { threshold: 0.1 });
  document.querySelectorAll('.reveal').forEach(function(el) { revealObserver.observe(el); });
}
initReveal();

/* ── AJAX CATEGORY SWITCHING ── */
(function () {
  var grid    = document.querySelector('.products-grid');
  var titleEl = document.querySelector('.section-title');
  var catBtns = document.querySelectorAll('.cat-btn');

  function loadCategory(cat) {
    grid.style.opacity = '0.4';
    grid.style.transition = 'opacity 0.2s';

    fetch('index.php?ajax=1&category=' + encodeURIComponent(cat))
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (titleEl) titleEl.textContent = data.title;
        grid.innerHTML = data.cards;
        grid.style.opacity = '1';
        initReveal();
        initImageHandlers();
      })
      .catch(function() { grid.style.opacity = '1'; });
  }

  /* ── Ripple helper ── */
  function spawnRipple(btn, e) {
    var rect = btn.getBoundingClientRect();
    var size = Math.max(rect.width, rect.height) * 1.6;
    var x = (e.clientX - rect.left) - size / 2;
    var y = (e.clientY - rect.top)  - size / 2;
    var ripple = document.createElement('span');
    ripple.className = 'cat-ripple';
    ripple.style.cssText = 'width:'+size+'px;height:'+size+'px;left:'+x+'px;top:'+y+'px;';
    btn.appendChild(ripple);
    ripple.addEventListener('animationend', function() { ripple.remove(); });
  }

  /* ── Petal pop helper ── */
  var petalEmojis = ['🌸','✿','❀','🌺','💮','🏵️'];
  function spawnPetals(btn) {
    var rect = btn.getBoundingClientRect();
    var cx = rect.left + rect.width  / 2;
    var cy = rect.top  + rect.height / 2;
    var count = 6;
    for (var i = 0; i < count; i++) {
      var el = document.createElement('span');
      el.className = 'cat-petal-pop';
      var angle = (i / count) * 2 * Math.PI - Math.PI / 2;
      var dist  = 38 + Math.random() * 24;
      var tx = Math.round(Math.cos(angle) * dist);
      var ty = Math.round(Math.sin(angle) * dist);
      var tr = (Math.random() * 120 - 60) + 'deg';
      el.style.cssText = 'left:'+cx+'px;top:'+cy+'px;--tx:'+tx+'px;--ty:'+ty+'px;--tr:'+tr+';animation-delay:'+(i*0.04)+'s;';
      el.textContent = petalEmojis[Math.floor(Math.random() * petalEmojis.length)];
      document.body.appendChild(el);
      el.addEventListener('animationend', function() { el.remove(); });
    }
  }

  catBtns.forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();

      var cat = this.dataset.category;

      /* visual effects */
      spawnRipple(this, e);
      spawnPetals(this);

      catBtns.forEach(function(b) { b.classList.remove('active'); });
      this.classList.add('active');

      var newUrl = cat ? 'index.php?category=' + encodeURIComponent(cat) : 'index.php';
      history.pushState({ category: cat }, '', newUrl);

      loadCategory(cat);
    });
  });

  window.addEventListener('popstate', function(e) {
    var cat = (e.state && e.state.category) ? e.state.category : '';
    catBtns.forEach(function(b) { b.classList.remove('active'); });
    var matchingBtn = document.querySelector('.cat-btn[data-category="' + cat + '"]');
    if (matchingBtn) matchingBtn.classList.add('active');
    loadCategory(cat);
  });
})();
</script>
</body>
</html>