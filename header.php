<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="icon" type="image/png" href="images/logo/SB.png">
<link rel="shortcut icon" type="image/png" href="images/logo/SB.png">
<link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
/* ══════════════════════════════════════
   HEADER — Admin-style (Precious Satin Bouquet)
   ══════════════════════════════════════ */
.header {
  --h-cream:     #fdf6f9;
  --h-blush:     #fce8f0;
  --h-petal:     #f7c5d8;
  --h-rose:      #e8729a;
  --h-deep-rose: #c4517a;
  --h-mauve:     #9b3060;
  --h-muted:     #a07080;
  --h-ink:       #2d1a22;
  --h-border:    rgba(232,114,154,0.18);

  position: sticky;
  top: 0;
  z-index: 200;
  height: 76px;
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  align-items: center;
  padding: 0 40px;
  background: rgba(253,246,249,0.88);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border-bottom: 1px solid var(--h-border);
  box-shadow: 0 2px 12px rgba(196,81,122,0.08);
  overflow: visible;
}

/* Petal accent line */
.header::after {
  content: '';
  position: absolute;
  bottom: -1px;
  left: 50%;
  transform: translateX(-50%);
  width: 120px;
  height: 2px;
  background: linear-gradient(90deg, transparent, var(--h-rose), transparent);
  border-radius: 2px;
  pointer-events: none;
}

/* ── Left zone ── */
.header-left {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 8px;
}

/* ── Search ── */
.search-form {
  display: flex;
  align-items: center;
  background: rgba(255,255,255,0.80);
  border: 1.5px solid var(--h-border);
  border-radius: 50px;
  height: 38px;
  transition: border-color 0.25s, box-shadow 0.25s, transform 0.25s;
  overflow: hidden;
}
.search-form:focus-within {
  border-color: var(--h-deep-rose);
  box-shadow: 0 0 0 3px rgba(196,81,122,0.12);
  transform: translateY(-1px);
}
.search-input {
  border: none;
  outline: none;
  background: transparent;
  font-size: 0.82rem;
  font-family: 'Jost', sans-serif;
  color: var(--h-ink);
  width: 145px;
  padding: 0 8px 0 14px;
  transition: width 0.3s cubic-bezier(0.34,1.56,0.64,1);
}
.search-form:focus-within .search-input { width: 168px; }
.search-input::placeholder { color: rgba(160,112,128,0.5); font-style: italic; }

.search-form .search-btn {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px; height: 32px; min-width: 32px;
  margin: 3px;
  padding: 0;
  background: linear-gradient(135deg, #f9bdd0 0%, #e04888 100%);
  border: none;
  border-radius: 50%;
  flex-shrink: 0;
  cursor: pointer;
  color: #fff;
  box-shadow: 0 3px 10px rgba(224,72,136,0.30);
  transition: transform 0.2s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.2s;
}
/* kill any leftover fake-magnifier pseudo-elements */
.search-form .search-btn::before,
.search-form .search-btn::after { content: none !important; display: none !important; }
.search-form .search-btn svg { width: 16px; height: 16px; display: block; pointer-events: none; }
.search-form .search-btn:hover  { transform: scale(1.1); box-shadow: 0 5px 16px rgba(224,72,136,0.45); }
.search-form .search-btn:active { transform: scale(0.94); }
.search-form .search-btn:focus-visible { outline: 3px solid rgba(224,72,136,0.45); outline-offset: 2px; }

/* ── Center brand — grid column 2, always centered ── */
.header-center {
  display: flex;
  justify-content: center;
  align-items: center;
}

.logo-center {
  text-decoration: none;
  text-align: center;
  display: block;
  line-height: 1;
}

.logo-text {
  font-family: 'Great Vibes', cursive;
  font-size: 30px;
  color: var(--h-deep-rose);
  letter-spacing: 0.5px;
  display: block;
  line-height: 1.5;
  padding-top: 8px;
  overflow: visible;
  transition: color 0.2s;
}
.logo-center:hover .logo-text { color: var(--h-mauve); }

.logo-tagline {
  font-family: 'Jost', sans-serif;
  font-size: 9px;
  font-weight: 500;
  letter-spacing: 3px;
  text-transform: uppercase;
  color: var(--h-muted);
  display: block;
  margin-top: 1px;
}

/* ── Right nav icons ── */
.nav-icons {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 14px;
}

/* Nav icon wrapper — holds button + label tooltip */
.nav-icon-wrap {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
}

/* Floating label beneath icon */
.nav-icon-label {
  position: absolute;
  top: calc(100% + 8px);
  left: 50%;
  transform: translateX(-50%) translateY(-4px) scale(0.88);
  background: linear-gradient(135deg, #c4517a 0%, #9b3060 100%);
  color: #fff;
  font-family: 'Jost', sans-serif;
  font-size: 0.67rem;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  padding: 4px 10px;
  border-radius: 20px;
  white-space: nowrap;
  pointer-events: none;
  opacity: 0;
  z-index: 9999;
  box-shadow: 0 4px 14px rgba(196,81,122,0.38);
  transition: opacity 0.18s ease, transform 0.24s cubic-bezier(0.34,1.56,0.64,1);
}
/* Little arrow */
.nav-icon-label::before {
  content: '';
  position: absolute;
  top: -5px;
  left: 50%;
  transform: translateX(-50%);
  border-left: 5px solid transparent;
  border-right: 5px solid transparent;
  border-bottom: 5px solid #c4517a;
}

.nav-icon-wrap:hover .nav-icon-label {
  opacity: 1;
  transform: translateX(-50%) translateY(0) scale(1);
}

/* Circular icon buttons */
.nav-icon-btn {
  position: relative;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: rgba(255,255,255,0.60);
  border: 1.5px solid var(--h-border);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--h-muted);
  text-decoration: none;
  transition: all 0.28s cubic-bezier(.34,1.56,.64,1);
  cursor: pointer;
  flex-shrink: 0;
  overflow: hidden;
  backdrop-filter: blur(6px);
}

/* Petal shimmer overlay */
.nav-icon-btn::before {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: 50%;
  background: radial-gradient(circle at 35% 35%, rgba(255,255,255,0.55) 0%, transparent 65%);
  opacity: 0;
  transition: opacity 0.25s ease;
  pointer-events: none;
}

/* Ripple ring on hover */
.nav-icon-btn::after {
  content: '';
  position: absolute;
  inset: -3px;
  border-radius: 50%;
  border: 2px solid var(--h-rose);
  opacity: 0;
  transform: scale(0.7);
  transition: opacity 0.3s ease, transform 0.35s cubic-bezier(0.34,1.56,0.64,1);
  pointer-events: none;
}

.nav-icon-btn svg {
  transition: transform 0.22s cubic-bezier(0.34,1.56,0.64,1);
  width: 17px; height: 17px;
  display: block;
  position: relative;
  z-index: 1;
}

.nav-icon-btn:hover {
  background: linear-gradient(135deg, var(--h-rose) 0%, var(--h-deep-rose) 100%);
  border-color: transparent;
  color: #fff;
  box-shadow:
    0 6px 20px rgba(196,81,122,0.42),
    0 2px 6px rgba(196,81,122,0.22),
    inset 0 1px 0 rgba(255,255,255,0.25);
  transform: translateY(-3px) scale(1.05);
}
.nav-icon-btn:hover::before { opacity: 1; }
.nav-icon-btn:hover::after  { opacity: 1; transform: scale(1); }
.nav-icon-btn:hover svg     { transform: scale(1.14) rotate(-4deg); }
.nav-icon-btn:active        { transform: translateY(0) scale(0.95); }

/* Active page: gradient fill, glow */
.nav-icon-btn--active {
  background: linear-gradient(135deg, rgba(196,81,122,0.18) 0%, rgba(155,48,96,0.10) 100%);
  border-color: var(--h-deep-rose);
  border-width: 1.5px;
  color: var(--h-deep-rose);
  box-shadow: 0 2px 10px rgba(196,81,122,0.18), inset 0 1px 0 rgba(255,255,255,0.5);
}
/* Dot indicator below active icon */
.nav-icon-btn--active + .nav-icon-label,
.nav-icon-wrap:has(.nav-icon-btn--active) .nav-icon-label {
  /* keep hidden unless hovered */
}
.nav-icon-btn--active::after {
  opacity: 0.4;
  transform: scale(1.05);
  border-color: var(--h-deep-rose);
}

/* Active dot below the icon (always visible) */
.nav-icon-dot {
  display: none;
  width: 4px;
  height: 4px;
  border-radius: 50%;
  background: var(--h-deep-rose);
  margin-top: 3px;
  box-shadow: 0 0 6px rgba(196,81,122,0.6);
  animation: dotPulse 1.8s ease-in-out infinite;
}
@keyframes dotPulse {
  0%, 100% { transform: scale(1); opacity: 1; }
  50%       { transform: scale(1.5); opacity: 0.6; }
}
.nav-icon-btn--active ~ .nav-icon-dot { display: block; }

/* ══════════════════════════════════════
   CUTE GIRLY SEARCH TOOLTIP
   ══════════════════════════════════════ */
.search-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.search-tooltip {
  position: absolute;
  top: calc(100% + 10px);
  left: 50%;
  transform: translateX(-50%) translateY(-6px) scale(0.92);
  background: linear-gradient(135deg, #fff0f6 0%, #ffe4f0 100%);
  border: 1.5px solid #f4a7c4;
  border-radius: 20px;
  padding: 9px 15px 9px 12px;
  white-space: nowrap;
  font-family: 'Jost', sans-serif;
  font-size: 0.78rem;
  font-weight: 500;
  color: #c4517a;
  box-shadow:
    0 8px 28px rgba(232,114,154,0.22),
    0 2px 8px rgba(196,81,122,0.12),
    inset 0 1px 0 rgba(255,255,255,0.8);
  pointer-events: none;
  opacity: 0;
  z-index: 9999;
  transition: opacity 0.18s ease, transform 0.28s cubic-bezier(0.34,1.56,0.64,1);
  display: flex;
  align-items: center;
  gap: 6px;
}

/* Arrow pointing up */
.search-tooltip::before {
  content: '';
  position: absolute;
  top: -7px;
  left: 50%;
  transform: translateX(-50%);
  border-left: 7px solid transparent;
  border-right: 7px solid transparent;
  border-bottom: 7px solid #f4a7c4;
}
.search-tooltip::after {
  content: '';
  position: absolute;
  top: -5px;
  left: 50%;
  transform: translateX(-50%);
  border-left: 6px solid transparent;
  border-right: 6px solid transparent;
  border-bottom: 6px solid #fff0f6;
}

.search-tooltip.visible {
  opacity: 1;
  transform: translateX(-50%) translateY(0) scale(1);
}

/* Sparkle emoji pulse */
.search-tooltip .sparkle {
  display: inline-block;
  animation: sparklePop 0.7s ease infinite alternate;
}
@keyframes sparklePop {
  0%   { transform: scale(1) rotate(-5deg); }
  100% { transform: scale(1.25) rotate(8deg); }
}

/* Floating hearts inside tooltip */
.search-tooltip .heart {
  display: inline-block;
  color: #e8729a;
  animation: heartFloat 1.1s ease-in-out infinite alternate;
  font-size: 0.72rem;
}
@keyframes heartFloat {
  0%   { transform: translateY(0); opacity: 0.7; }
  100% { transform: translateY(-3px); opacity: 1; }
}

/* Wiggle the search form on invalid attempt */
@keyframes wiggleForm {
  0%, 100% { transform: translateX(0) translateY(-1px); }
  20%       { transform: translateX(-5px) translateY(-1px); }
  40%       { transform: translateX(5px) translateY(-1px); }
  60%       { transform: translateX(-4px) translateY(-1px); }
  80%       { transform: translateX(4px) translateY(-1px); }
}
.search-form.wiggle {
  animation: wiggleForm 0.42s cubic-bezier(0.36,0.07,0.19,0.97);
  border-color: #e8729a !important;
  box-shadow: 0 0 0 3px rgba(232,114,154,0.22) !important;
}

/* ── Mobile ── */
@media (max-width: 640px) {
  .header { padding: 0 16px; height: 68px; overflow: visible; }
  .search-input { width: 88px; }
  .search-form:focus-within .search-input { width: 110px; }
  .logo-text { font-size: 24px; }
  .logo-tagline { display: none; }
  .nav-icons { gap: 10px; }
  .nav-icon-btn { width: 34px; height: 34px; }
  .nav-icon-label { font-size: 0.6rem; padding: 3px 7px; }
}
</style>

<header class="header">

  <!-- LEFT: hamburger + search -->
  <div class="header-left">
    <?php if (!strpos($_SERVER['REQUEST_URI'], 'admin.php')): ?>
      <?php include 'sidebar.php'; ?>
      <div class="search-wrapper">
        <form action="search.php" method="GET" class="search-form" autocomplete="off" novalidate id="searchForm">
          <input type="text" name="query" class="search-input" placeholder="Search blooms…" aria-label="Search blooms" id="searchInput">
          <button type="submit" class="search-btn" aria-label="Search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
          </button>
        </form>
        <div class="search-tooltip" id="searchTooltip" role="alert" aria-live="polite">
          <span class="sparkle">✨</span>
          <span>Pretty please type something first!</span>
          <span class="heart">♡</span>
        </div>
      </div>

      <script>
      (function() {
        var form    = document.getElementById('searchForm');
        var input   = document.getElementById('searchInput');
        var tooltip = document.getElementById('searchTooltip');
        var hideTimer = null;

        function showTooltip() {
          clearTimeout(hideTimer);
          tooltip.classList.add('visible');
          hideTimer = setTimeout(hideTooltip, 2800);
        }

        function hideTooltip() {
          tooltip.classList.remove('visible');
        }

        form.addEventListener('submit', function(e) {
          if (!input.value.trim()) {
            e.preventDefault();
            showTooltip();
            form.classList.remove('wiggle');
            void form.offsetWidth;
            form.classList.add('wiggle');
            form.addEventListener('animationend', function() {
              form.classList.remove('wiggle');
            }, { once: true });
            input.focus();
          }
        });

        input.addEventListener('input', function() {
          if (input.value.trim()) hideTooltip();
        });

        document.addEventListener('click', function(e) {
          if (!form.contains(e.target)) hideTooltip();
        });
      })();
      </script>
    <?php endif; ?>
  </div>

  <!-- CENTER: Brand (absolutely centered so icons never shift it) -->
  <div class="header-center">
    <a href="index.php" class="logo-center" aria-label="Precious Satin Bouquet Home">
      <span class="logo-text">Precious Satin Bouquet</span>
      <?php if (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin' && $currentPage === 'admin.php'): ?>
        <span class="logo-tagline">Admin Dashboard</span>
      <?php endif; ?>
    </a>
  </div>

  <!-- RIGHT: Nav icons -->
  <nav class="nav-icons" aria-label="Main navigation">
    <?php if (!strpos($_SERVER['REQUEST_URI'], 'admin.php')): ?>

      <!-- Home -->
      <div class="nav-icon-wrap">
        <a href="index.php" class="nav-icon-btn <?= $currentPage === 'index.php' ? 'nav-icon-btn--active' : '' ?>" aria-label="Home">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
        </a>
        <span class="nav-icon-label">Home</span>
        <span class="nav-icon-dot"></span>
      </div>

      <!-- Cart -->
      <div class="nav-icon-wrap">
        <a href="cart.php" class="nav-icon-btn <?= $currentPage === 'cart.php' ? 'nav-icon-btn--active' : '' ?>" aria-label="Cart">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
            <line x1="3" y1="6" x2="21" y2="6"/>
            <path d="M16 10a4 4 0 0 1-8 0"/>
          </svg>
        </a>
        <span class="nav-icon-label">Cart</span>
        <span class="nav-icon-dot"></span>
      </div>

      <!-- Orders -->
      <div class="nav-icon-wrap">
        <a href="orders.php" class="nav-icon-btn <?= $currentPage === 'orders.php' ? 'nav-icon-btn--active' : '' ?>" aria-label="Orders">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
            <line x1="9" y1="13" x2="15" y2="13"/>
            <line x1="9" y1="17" x2="13" y2="17"/>
          </svg>
        </a>
        <span class="nav-icon-label">Orders</span>
        <span class="nav-icon-dot"></span>
      </div>

    <?php endif; ?>

    <?php if(isset($_SESSION['user_id'])): ?>
      <!-- Profile -->
      <div class="nav-icon-wrap">
        <a href="profile.php" class="nav-icon-btn <?= $currentPage === 'profile.php' ? 'nav-icon-btn--active' : '' ?>" aria-label="Profile">
          <svg viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/>
          </svg>
        </a>
        <span class="nav-icon-label">Profile</span>
        <span class="nav-icon-dot"></span>
      </div>
    <?php else: ?>
      <!-- Login -->
      <div class="nav-icon-wrap">
        <a href="login.php" class="nav-icon-btn <?= $currentPage === 'login.php' ? 'nav-icon-btn--active' : '' ?>" aria-label="Login">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
        </a>
        <span class="nav-icon-label">Login</span>
        <span class="nav-icon-dot"></span>
      </div>
    <?php endif; ?>

    <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
      <!-- Admin -->
      <div class="nav-icon-wrap">
        <a href="admin.php" class="nav-icon-btn <?= $currentPage === 'admin.php' ? 'nav-icon-btn--active' : '' ?>" aria-label="Admin">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="3"/>
            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
          </svg>
        </a>
        <span class="nav-icon-label">Admin</span>
        <span class="nav-icon-dot"></span>
      </div>
    <?php endif; ?>

  </nav>

</header>