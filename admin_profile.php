<?php
session_start();
include "config.php";

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$uid = $_SESSION['user_id'];
$user = $conn->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();
if ($user['role'] != 'admin') { header("Location: profile.php"); exit(); }

$nameError = $emailError = $passError = '';
$activeTab = 'info';

// HANDLE NAME + EMAIL UPDATE (combined "Admin Info" panel)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_info'])) {
    $activeTab = 'info';
    $nameInput  = trim($_POST['username'] ?? '');
    $emailInput = trim($_POST['email']    ?? '');
    $errors = [];
    if (empty($nameInput))                              $errors[] = 'Username cannot be empty.';
    if (!filter_var($emailInput, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email.';
    if (empty($errors)) {
        $username = $conn->real_escape_string($nameInput);
        $email    = $conn->real_escape_string($emailInput);
        $conn->query("UPDATE users SET username='$username', email='$email' WHERE id=$uid");
        header("Location: admin_profile.php?saved=1"); exit();
    } else { $nameError = implode(' ', $errors); }
}

// HANDLE PASSWORD UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $activeTab = 'password';
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if (!password_verify($current, $user['password'])) {
        $passError = 'Current password is incorrect.';
    } elseif (strlen($new) < 6) {
        $passError = 'New password must be at least 6 characters.';
    } elseif ($new !== $confirm) {
        $passError = 'Passwords do not match.';
    } else {
        $hashed = password_hash($new, PASSWORD_DEFAULT);
        $conn->query("UPDATE users SET password='$hashed' WHERE id=$uid");
        header("Location: admin_profile.php?pw=1"); exit();
    }
}

// Tab from GET flash redirects
if (isset($_GET['pw'])) $activeTab = 'password';

// Refresh user
$stmt = $conn->prepare("SELECT username, email, role FROM users WHERE id=?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$adminName    = $user['username'];
$adminEmail   = $user['email'] ?? '';
$adminInitial = strtoupper(substr($adminName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Profile — Precious Satin Bouquet</title>
<link rel="icon" type="image/png" href="images/logo/SB.png">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Jost:wght@300;400;500;600;700&family=Great+Vibes&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --blush:     #fff3ed;
    --petal:     #ffc9a0;
    --rose:      #f08050;
    --deep-rose: #d05a28;
    --mauve:     #c8775a;
    --cream:     #fff8f4;
    --white:     #ffffff;
    --ink:       #2d1a22;
    --muted:     #a07080;
    --border:    rgba(255,170,203,0.22);
    --shadow-sm: 0 2px 12px rgba(208,90,40,0.10);
    --shadow-md: 0 8px 32px rgba(208,90,40,0.14);
    --shadow-lg: 0 20px 60px rgba(208,90,40,0.18);
    --radius:    18px;
    --radius-sm: 10px;
}

body {
    font-family: 'Jost', sans-serif;
    background: var(--cream);
    min-height: 100vh;
    color: var(--ink);
    overflow-x: hidden;
}
body::before {
    content: '';
    position: fixed; inset: 0;
    background:
        radial-gradient(ellipse 60% 40% at 90% 10%, rgba(255,200,160,0.4) 0%, transparent 70%),
        radial-gradient(ellipse 50% 60% at 5% 80%, rgba(252,232,240,0.5) 0%, transparent 65%),
        radial-gradient(ellipse 40% 30% at 50% 50%, rgba(253,246,249,0.8) 0%, transparent 80%);
    pointer-events: none; z-index: 0;
}

/* ── LAYOUT ── */
.adm-shell { max-width: 1020px; margin: 0 auto; padding: 36px 20px 60px; position: relative; }
.adm-grid  { display: grid; grid-template-columns: 260px 1fr; gap: 28px; align-items: start; }

/* ── SIDEBAR ── */
.adm-profile-sidebar {
    background: var(--white);
    border-radius: var(--radius);
    box-shadow: var(--shadow-md);
    border: 1px solid var(--border);
    overflow: hidden;
    position: sticky;
    top: 86px;
}
.adm-sidebar-top {
    background: linear-gradient(160deg, var(--rose) 0%, var(--deep-rose) 100%);
    padding: 36px 20px 28px;
    display: flex; flex-direction: column; align-items: center; gap: 12px;
    position: relative;
}
.adm-sidebar-top::before { content:''; position:absolute; border-radius:50%; background:rgba(255,255,255,0.07); pointer-events:none; width:140px; height:140px; top:-45px; right:-35px; }
.adm-sidebar-top::after  { content:''; position:absolute; border-radius:50%; background:rgba(255,255,255,0.05); pointer-events:none; width:75px; height:75px; bottom:-18px; left:-18px; }

.adm-avatar-wrap { position: relative; width: 90px; height: 90px; cursor: pointer; }
.adm-avatar-circle {
    width: 90px; height: 90px; border-radius: 50%;
    background: rgba(255,255,255,0.22); border: 3px solid rgba(255,255,255,0.5);
    display: flex; align-items: center; justify-content: center;
    font-family: 'Cormorant Garamond', serif; font-size: 40px; font-weight: 600; color: #fff;
    box-shadow: 0 4px 18px rgba(0,0,0,0.12); overflow: hidden;
    transition: filter 0.2s;
}
.adm-avatar-circle img { width: 100%; height: 100%; object-fit: cover; }
.adm-avatar-overlay {
    position: absolute; bottom: 2px; right: 2px;
    width: 26px; height: 26px; border-radius: 50%;
    background: var(--white); border: 2px solid #fff;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}
.adm-avatar-overlay svg { color: var(--rose); }
.adm-avatar-hint { font-size: 10px; font-weight: 400; letter-spacing: 0.03em; color: rgba(255,255,255,0.65); text-align: center; line-height: 1.3; margin-top: 2px; }

.adm-sidebar-name { font-family: 'Great Vibes', cursive; font-size: 1.6rem; color: #fff; text-align: center; line-height: 1.2; }
.adm-sidebar-role { font-family: 'Jost', sans-serif; font-size: 9px; font-weight: 600; letter-spacing: 3px; text-transform: uppercase; color: rgba(255,255,255,0.75); background: rgba(255,255,255,0.15); padding: 4px 14px; border-radius: 20px; }

.adm-sidebar-nav { padding: 14px 10px; display: flex; flex-direction: column; gap: 3px; }
.adm-tab-btn {
    display: flex; align-items: center; gap: 10px; width: 100%; padding: 12px 14px; border-radius: var(--radius-sm);
    border: none; background: none; cursor: pointer;
    font-family: 'Jost', sans-serif; font-size: 13px; font-weight: 500;
    color: var(--muted); text-align: left; transition: background 0.18s, color 0.18s;
}
.adm-tab-btn svg { flex-shrink: 0; opacity: 0.6; transition: opacity 0.18s; }
.adm-tab-btn:hover { background: var(--blush); color: var(--deep-rose); }
.adm-tab-btn:hover svg { opacity: 1; }
.adm-tab-btn.active { background: var(--blush); color: var(--deep-rose); font-weight: 600; }
.adm-tab-btn.active svg { opacity: 1; }

.adm-sidebar-footer { padding: 10px 10px 18px; }
.adm-btn-logout {
    display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%;
    padding: 11px 14px; border-radius: var(--radius-sm);
    border: 1.5px solid var(--border); background: none; cursor: pointer;
    font-family: 'Jost', sans-serif; font-size: 11px; font-weight: 600; letter-spacing: 2px;
    text-transform: uppercase; color: var(--muted); text-decoration: none;
    transition: background 0.2s, color 0.2s, border-color 0.2s;
}
.adm-btn-logout:hover { background: var(--deep-rose); color: #fff; border-color: transparent; }

/* ── PANELS ── */
.adm-panel-area { position: relative; }
.adm-tab-panel { display: none; background: var(--white); border-radius: var(--radius); box-shadow: var(--shadow-md); border: 1px solid var(--border); overflow: hidden; animation: fadeUp 0.22s ease; }
.adm-tab-panel.active { display: block; }
@keyframes fadeUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

.adm-panel-header {
    background: var(--blush);
    border-bottom: 1px solid var(--border);
    padding: 22px 32px;
    display: flex; align-items: center; gap: 12px;
}
.adm-panel-header svg { color: var(--rose); flex-shrink: 0; }
.adm-panel-title { font-family: 'Cormorant Garamond', serif; font-size: 1.4rem; font-weight: 600; color: var(--ink); letter-spacing: 0.02em; }

.adm-panel-body { padding: 30px 32px 28px; }

.adm-flash { border-radius: var(--radius-sm); padding: 12px 16px; font-size: 13px; font-weight: 500; margin-bottom: 22px; }
.adm-flash.ok  { background: #f0faf4; border: 1px solid #7dcea0; color: #276244; }
.adm-flash.err { background: #fff5f0; border: 1px solid var(--petal); color: var(--deep-rose); }

.adm-field-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px 24px; margin-bottom: 24px; }
.adm-field-full { grid-column: 1 / -1; }

.adm-field { margin-bottom: 0; }
.adm-field label {
    display: block; font-family: 'Jost', sans-serif; font-size: 10px; letter-spacing: 2.5px;
    text-transform: uppercase; color: var(--muted); font-weight: 600; margin-bottom: 8px;
}
.adm-field input {
    font-family: 'Jost', sans-serif; font-size: 14px; font-weight: 400; color: var(--ink);
    width: 100%; background: var(--cream); border: 1px solid var(--border);
    border-radius: var(--radius-sm); padding: 13px 16px; outline: none;
    transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
}
.adm-field input:focus { border-color: var(--petal); background: var(--white); box-shadow: 0 0 0 3px rgba(255,201,160,0.2); }
.adm-field input::placeholder { color: var(--muted); font-weight: 300; opacity: 0.7; }

.adm-field-hint { font-size: 11px; color: var(--muted); margin-top: 6px; font-weight: 400; }

.adm-pw-wrap { position: relative; }
.adm-pw-wrap input { padding-right: 68px; }
.adm-pw-toggle {
    position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
    background: none; border: none; font-family: 'Jost', sans-serif;
    font-size: 10px; font-weight: 600; letter-spacing: 1.5px;
    color: var(--rose); cursor: pointer; padding: 4px; text-transform: uppercase;
}
.adm-pw-toggle:hover { color: var(--deep-rose); }

.adm-btn-save {
    font-family: 'Jost', sans-serif; font-size: 11px; font-weight: 600; letter-spacing: 2.5px;
    text-transform: uppercase; color: #fff;
    background: linear-gradient(135deg, var(--rose), var(--deep-rose));
    border: none; border-radius: var(--radius-sm); padding: 15px 28px; cursor: pointer;
    width: 100%; margin-top: 4px;
    transition: transform 0.2s, box-shadow 0.2s, filter 0.2s;
    box-shadow: var(--shadow-sm);
}
.adm-btn-save:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(208,90,40,0.30); filter: brightness(1.05); }
.adm-btn-save:active { transform: translateY(0); }

@media (max-width: 780px) {
    .adm-grid { grid-template-columns: 1fr; }
    .adm-profile-sidebar { position: static; }
    .adm-sidebar-nav { flex-direction: row; flex-wrap: wrap; }
    .adm-tab-btn { flex: 1 1 auto; justify-content: center; }
}
@media (max-width: 520px) {
    .adm-field-grid { grid-template-columns: 1fr; }
    .adm-field-full { grid-column: 1; }
}

/* Notification badges — added for sidebar alerts */
.nav-badge{display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 5px;border-radius:99px;font-size:10px;font-weight:800;line-height:1;background:#e53e3e;color:#fff;margin-left:auto;flex-shrink:0;animation:badgePop 0.4s cubic-bezier(.34,1.56,.64,1);}
@keyframes badgePop{from{transform:scale(0);opacity:0;}to{transform:scale(1);opacity:1;}}
.adm-icon .pending-dot{position:absolute;top:3px;right:3px;width:9px;height:9px;border-radius:50%;background:#e53e3e;border:2px solid var(--cream);animation:pulseDot 1.6s ease-in-out infinite;}
@keyframes pulseDot{0%,100%{opacity:1;transform:scale(1);}50%{opacity:.55;transform:scale(1.3);}}
</style>
<?php include __DIR__ . '/pwa_head.php'; ?>
</head>
<body>

<!-- NAVBAR -->
<?php $activePage = 'profile'; include 'admin_navbar.php'; ?>
<!-- BODY -->
<div class="adm-shell">
<div class="adm-grid">

    <!-- ══ SIDEBAR ══ -->
    <aside class="adm-profile-sidebar">
        <div class="adm-sidebar-top">
            <!-- Avatar with camera icon -->
            <div class="adm-avatar-wrap" onclick="document.getElementById('avatarInput').click()" title="Click to change photo">
                <div class="adm-avatar-circle" id="avatarCircle">
                    <img src="images/logo/SB.png" alt="Admin Avatar" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                </div>
                <div class="adm-avatar-overlay">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                        <circle cx="12" cy="13" r="4"/>
                    </svg>
                </div>
                <input type="file" id="avatarInput" accept="image/*" style="display:none" onchange="previewAvatar(this)">
            </div>
            <div class="adm-avatar-hint">Click photo to change · Max 2 MB</div>
            <div class="adm-sidebar-name"><?= htmlspecialchars($adminName) ?></div>
            <div class="adm-sidebar-role">Administrator</div>
        </div>

        <nav class="adm-sidebar-nav">
            <button class="adm-tab-btn <?= $activeTab === 'info'     ? 'active' : '' ?>" onclick="admTab('info')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                </svg>
                Admin Info
            </button>
            <button class="adm-tab-btn <?= $activeTab === 'password' ? 'active' : '' ?>" onclick="admTab('password')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
                Change Password
            </button>
        </nav>

        <div class="adm-sidebar-footer">
            <button type="button" class="adm-btn-logout" id="logoutTrigger">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
                Logout
            </button>
        </div>
    </aside>

    <!-- ══ PANELS ══ -->
    <div class="adm-panel-area">

        <!-- Flash banners -->
        <?php if (isset($_GET['saved'])): ?>
            <div class="adm-flash ok" style="margin-bottom:18px;border-radius:16px;">✓ Profile updated successfully!</div>
        <?php endif; ?>
        <?php if (isset($_GET['pw'])): ?>
            <div class="adm-flash ok" style="margin-bottom:18px;border-radius:16px;">✓ Password changed successfully!</div>
        <?php endif; ?>

        <!-- ── Admin Info (username + email combined) ── -->
        <div class="adm-tab-panel <?= $activeTab === 'info' ? 'active' : '' ?>" id="adm-panel-info">
            <div class="adm-panel-header">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                </svg>
                <span class="adm-panel-title">Admin Information</span>
            </div>
            <div class="adm-panel-body">
                <?php if (!empty($nameError)): ?>
                    <div class="adm-flash err"><?= htmlspecialchars($nameError) ?></div>
                <?php endif; ?>
                <form method="POST" novalidate>
                    <div class="adm-field-grid">
                        <!-- Row 1: Username + Email -->
                        <div class="adm-field">
                            <label for="adm_username">Username</label>
                            <input type="text" id="adm_username" name="username"
                                   value="<?= htmlspecialchars($adminName) ?>"
                                   placeholder="Enter username" required>
                        </div>
                        <div class="adm-field">
                            <label for="adm_email">Email Address</label>
                            <input type="email" id="adm_email" name="email"
                                   value="<?= htmlspecialchars($adminEmail) ?>"
                                   placeholder="your@email.com" required>
                        </div>
                        <!-- Row 2: Role (read-only) -->
                        <div class="adm-field">
                            <label>Role</label>
                            <input type="text" value="Administrator" readonly
                                   style="background:#f5e8ef;cursor:default;color:var(--text-mid);">
                        </div>
                        <!-- Save button full-width -->
                        <div class="adm-field adm-field-full" style="margin-top:4px;">
                            <button type="submit" name="update_info" class="adm-btn-save">Save Profile</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- ── Change Password ── -->
        <div class="adm-tab-panel <?= $activeTab === 'password' ? 'active' : '' ?>" id="adm-panel-password">
            <div class="adm-panel-header">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
                <span class="adm-panel-title">Change Password</span>
            </div>
            <div class="adm-panel-body">
                <?php if (!empty($passError)): ?>
                    <div class="adm-flash err"><?= htmlspecialchars($passError) ?></div>
                <?php endif; ?>
                <form method="POST" novalidate>
                    <div class="adm-field-grid">
                        <div class="adm-field adm-field-full">
                            <label for="adm_cur_pw">Current Password</label>
                            <div class="adm-pw-wrap">
                                <input type="password" id="adm_cur_pw" name="current_password"
                                       placeholder="Enter current password" required>
                                <button type="button" class="adm-pw-toggle" onclick="admTogglePw('adm_cur_pw',this)">Show</button>
                            </div>
                        </div>
                        <div class="adm-field">
                            <label for="adm_new_pw">New Password</label>
                            <div class="adm-pw-wrap">
                                <input type="password" id="adm_new_pw" name="new_password"
                                       placeholder="Min 6 characters" required>
                                <button type="button" class="adm-pw-toggle" onclick="admTogglePw('adm_new_pw',this)">Show</button>
                            </div>
                        </div>
                        <div class="adm-field">
                            <label for="adm_conf_pw">Confirm Password</label>
                            <div class="adm-pw-wrap">
                                <input type="password" id="adm_conf_pw" name="confirm_password"
                                       placeholder="Repeat new password" required>
                                <button type="button" class="adm-pw-toggle" onclick="admTogglePw('adm_conf_pw',this)">Show</button>
                            </div>
                        </div>
                        <div class="adm-field adm-field-full" style="margin-top:4px;">
                            <button type="submit" name="update_password" class="adm-btn-save">Update Password</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

    </div><!-- end panel-area -->
</div><!-- end adm-grid -->
</div><!-- end adm-shell -->

<script>
function admTab(name) {
    document.querySelectorAll('.adm-tab-panel').forEach(function(p){ p.classList.remove('active'); });
    document.querySelectorAll('.adm-tab-btn').forEach(function(b){ b.classList.remove('active'); });
    var panel = document.getElementById('adm-panel-' + name);
    if (panel) panel.classList.add('active');
    document.querySelectorAll('.adm-tab-btn').forEach(function(b){
        if (b.getAttribute('onclick') === "admTab('" + name + "')") b.classList.add('active');
    });
}
function admTogglePw(id, btn) {
    var inp = document.getElementById(id);
    if (inp.type === 'password') { inp.type = 'text'; btn.textContent = 'Hide'; }
    else { inp.type = 'password'; btn.textContent = 'Show'; }
}
function previewAvatar(input) {
    if (!input.files || !input.files[0]) return;
    if (input.files[0].size > 2 * 1024 * 1024) {
        alert('Image must be under 2 MB.');
        input.value = '';
        return;
    }
    var reader = new FileReader();
    reader.onload = function(e) {
        var circle = document.getElementById('avatarCircle');
        circle.innerHTML = '<img src="' + e.target.result + '" alt="avatar" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">';
    };
    reader.readAsDataURL(input.files[0]);
}
</script>
<!-- ── Logout Confirmation Modal ── -->
<style>
#logoutOverlay {
  display: none;
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: rgba(30,15,5,0.50);
  backdrop-filter: blur(5px);
  -webkit-backdrop-filter: blur(5px);
  align-items: center;
  justify-content: center;
}
#logoutOverlay.is-open { display: flex; }
#logoutModal {
  background: linear-gradient(160deg,#fffaf7 0%,#fff0e8 100%);
  border: 1.5px solid rgba(240,128,80,0.28);
  border-radius: 24px;
  padding: 36px 40px 32px;
  max-width: 330px;
  width: 90%;
  text-align: center;
  box-shadow: 0 24px 60px rgba(208,90,40,0.20), 0 6px 18px rgba(208,90,40,0.12);
  transform: translateY(24px);
  transition: transform 0.30s cubic-bezier(0.34,1.56,0.64,1);
}
#logoutOverlay.is-open #logoutModal { transform: translateY(0); }
</style>

<div id="logoutOverlay">
  <div id="logoutModal" role="dialog" aria-modal="true">
    <div style="font-size:2.4rem;margin-bottom:10px;">🔐</div>
    <p style="font-size:1.2rem;font-weight:600;color:var(--deep-rose);margin:0 0 8px;">Sign out of Admin?</p>
    <p style="font-size:0.84rem;color:#a07060;margin:0 0 26px;line-height:1.6;">Are you sure you want to sign out of the Admin Dashboard?</p>
    <div style="display:flex;gap:12px;justify-content:center;">
      <button type="button" id="logoutCancel" style="
          font-size:0.82rem;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;
          padding:10px 24px;border-radius:50px;cursor:pointer;
          background:rgba(255,255,255,0.85);border:1.5px solid var(--petal);
          color:#a07060;transition:all 0.2s;">
        Stay
      </button>
      <form method="POST" action="logout.php" style="margin:0;">
        <input type="hidden" name="confirmed" value="1">
        <button type="submit" style="
            font-size:0.82rem;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;
            padding:10px 24px;border-radius:50px;cursor:pointer;border:none;
            background:linear-gradient(135deg,var(--rose),var(--deep-rose));
            color:#fff;box-shadow:0 4px 14px rgba(208,90,40,0.30);transition:all 0.2s;">
          Sign Out
        </button>
      </form>
    </div>
  </div>
</div>

<script>
(function () {
  var overlay = document.getElementById('logoutOverlay');
  var trigger = document.getElementById('logoutTrigger');
  var cancel  = document.getElementById('logoutCancel');

  trigger.addEventListener('click', function () {
    overlay.classList.add('is-open');
    cancel.focus();
  });

  function closeModal() {
    overlay.classList.remove('is-open');
    trigger.focus();
  }

  cancel.addEventListener('click', closeModal);
  overlay.addEventListener('click', function (e) { if (e.target === overlay) closeModal(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });
})();
</script>
</body>
</html>