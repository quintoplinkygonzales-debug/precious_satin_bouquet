<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$currentPage = basename($_SERVER['PHP_SELF']);

// Count unread admin replies for the logged-in user
$_sb_notif = 0;
if (!empty($_SESSION['user_id']) && isset($conn)) {
    $_sb_uid = (int)$_SESSION['user_id'];
    $conn->query("ALTER TABLE message_replies ADD COLUMN IF NOT EXISTS is_read TINYINT(1) DEFAULT 0");
    $s = $conn->prepare("
        SELECT COUNT(*) AS c
        FROM   message_replies  mr
        JOIN   contact_messages cm ON cm.id = mr.message_id
        WHERE  cm.user_id = ?
          AND  mr.sender  = 'admin'
          AND  mr.is_read = 0
    ");
    $s->bind_param("i", $_sb_uid);
    $s->execute();
    $_sb_notif = (int)$s->get_result()->fetch_assoc()['c'];
    $s->close();
}
?>

<style>
/* ── Hamburger wrapper (holds button + red dot) ── */
.sb-hamburger-wrap {
  position: relative;
  display: inline-flex;
  flex-shrink: 0;
}

/* ── Hamburger ── */
.sb-hamburger {
  background: none;
  border: none;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  gap: 5px;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 10px;
  padding: 4px;
  transition: background 0.2s;
}
.sb-hamburger:hover { background: rgba(232,84,122,0.1); }
.sb-hamburger span {
  display: block;
  width: 20px;
  height: 2px;
  background: #c0577d;
  border-radius: 2px;
  transition: all 0.25s ease;
  transform-origin: center;
}
.sb-hamburger.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
.sb-hamburger.open span:nth-child(2) { opacity: 0; }
.sb-hamburger.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

/* ── Red dot — sibling of button, not inside flex ── */
.sb-red-dot {
  position: absolute;
  top: 2px;
  right: 2px;
  width: 9px;
  height: 9px;
  border-radius: 50%;
  background: #e8324a;
  border: 2px solid #fff8fa;
  pointer-events: none;
  animation: dotPop 0.3s cubic-bezier(.34,1.56,.64,1) both;
}
@keyframes dotPop {
  from { transform: scale(0); opacity: 0; }
  to   { transform: scale(1); opacity: 1; }
}

/* ── Overlay ── */
.sb-overlay {
  display: none;
  position: fixed;
  inset: 0;
  z-index: 8000;
}
.sb-overlay.active { display: block; }

/* ── Dropdown panel ── */
.sb-dropdown {
  position: fixed;
  top: 68px;
  left: 16px;
  width: 220px;
  background: #fff8fa;
  border: 1.5px solid #f7c4d8;
  border-radius: 18px;
  box-shadow: 0 12px 40px rgba(200,60,110,0.15);
  z-index: 9000;
  padding: 8px;
  opacity: 0;
  transform: translateY(-8px) scale(0.96);
  pointer-events: none;
  transition: opacity 0.22s ease, transform 0.22s ease;
  font-family: 'Nunito', 'Poppins', sans-serif;
}
.sb-dropdown.active {
  opacity: 1;
  transform: translateY(0) scale(1);
  pointer-events: all;
}

/* ── Row base ── */
.sb-dropdown a {
  display: flex;
  align-items: center;
  gap: 11px;
  padding: 10px 12px;
  border-radius: 12px;
  text-decoration: none;
  color: #7a2e50;
  font-size: 13.5px;
  font-weight: 700;
  transition: background 0.15s, color 0.15s, padding-left 0.15s;
  position: relative;
  background: transparent;
}
.sb-dropdown a:hover {
  background: rgba(232,84,122,0.09);
  color: #c0406a;
  padding-left: 16px;
}
.sb-dropdown a.sb-active {
  background: rgba(232,84,122,0.11);
  color: #c0406a;
}
.sb-dropdown a.sb-active::before {
  content: '';
  position: absolute;
  left: 0; top: 20%; height: 60%;
  width: 3px;
  background: #e8547a;
  border-radius: 0 3px 3px 0;
}

/* ── Icon bubble ── */
.sb-ico {
  width: 32px;
  height: 32px;
  border-radius: 9px;
  background: rgba(232,84,122,0.1);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 15px;
  flex-shrink: 0;
  font-style: normal;
  transition: background 0.15s;
}
.sb-dropdown a:hover .sb-ico,
.sb-dropdown a.sb-active .sb-ico { background: rgba(232,84,122,0.18); }

.sb-lbl { flex: 1; }

/* ── Unread count pill on My Messages ── */
.sb-notif-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border-radius: 99px;
  background: #e8324a;
  color: #fff;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.3px;
  line-height: 1;
  animation: dotPop 0.3s cubic-bezier(.34,1.56,.64,1) both;
}

/* ── Divider ── */
.sb-hr {
  height: 1px;
  background: #fad4e4;
  margin: 5px 6px;
}
</style>

<!-- Overlay catches outside clicks -->
<div class="sb-overlay" id="sbOverlay" onclick="closeSidebar()"></div>

<!-- Dropdown menu -->
<div class="sb-dropdown" id="sbDropdown">

  <a href="https://www.facebook.com/profile.php?id=61577825431395" target="_blank">
    <i class="sb-ico">📘</i>
    <span class="sb-lbl">FB Page</span>
  </a>

  <a href="contact.php" onclick="closeSidebar()"
     class="<?= $currentPage === 'contact.php' ? 'sb-active' : '' ?>">
    <i class="sb-ico">📞</i>
    <span class="sb-lbl">Contact</span>
  </a>

  <a href="about.php" onclick="closeSidebar()"
     class="<?= $currentPage === 'about.php' ? 'sb-active' : '' ?>">
    <i class="sb-ico">🌸</i>
    <span class="sb-lbl">About Us</span>
  </a>

  <?php if (!empty($_SESSION['user_id'])): ?>
  <div class="sb-hr"></div>
  <a href="my_messages.php" onclick="closeSidebar()"
     class="<?= $currentPage === 'my_messages.php' ? 'sb-active' : '' ?>">
    <i class="sb-ico">💌</i>
    <span class="sb-lbl">My Messages</span>
    <?php if ($_sb_notif > 0): ?>
    <span class="sb-notif-pill"><?= $_sb_notif ?></span>
    <?php endif; ?>
  </a>
  <?php endif; ?>

</div>

<!-- Hamburger wrapper: position:relative here so dot is anchored to this, not the flex button -->
<div class="sb-hamburger-wrap">
  <button class="sb-hamburger" id="sbHamburger" onclick="toggleSidebar()" aria-label="Open menu">
    <span></span><span></span><span></span>
  </button>
  <span class="sb-red-dot" id="sb-red-dot" <?= $_sb_notif > 0 ? '' : 'style="display:none"' ?>></span>
</div>

<script>
function toggleSidebar() {
  document.getElementById('sbDropdown').classList.contains('active') ? closeSidebar() : openSidebar();
}
function openSidebar() {
  document.getElementById('sbDropdown').classList.add('active');
  document.getElementById('sbOverlay').classList.add('active');
  document.getElementById('sbHamburger').classList.add('open');
}
function closeSidebar() {
  document.getElementById('sbDropdown').classList.remove('active');
  document.getElementById('sbOverlay').classList.remove('active');
  document.getElementById('sbHamburger').classList.remove('open');
}

// Live poll every 30s
(function () {
  const dot = document.getElementById('sb-red-dot');
  if (!dot) return;
  setInterval(function () {
    fetch('get_notifications.php')
      .then(r => r.json())
      .then(d => { dot.style.display = (d.count > 0) ? 'block' : 'none'; })
      .catch(() => {});
  }, 30000);
})();
</script>