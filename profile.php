<?php
session_start();
include "config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$uid    = $_SESSION['user_id'];
$errors = [];
$success = false;

// ── HANDLE AVATAR UPLOAD ───────────────────────────────────────────────────
$avatarSuccess = false;
$avatarError   = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file     = $_FILES['avatar'];
    $allowed  = ['image/jpeg','image/png','image/gif','image/webp'];
    $maxBytes = 2 * 1024 * 1024; // 2 MB

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $avatarError = "Upload failed (error code {$file['error']}).";
    } elseif (!in_array($file['type'], $allowed)) {
        $avatarError = "Only JPG, PNG, GIF, or WebP images are allowed.";
    } elseif ($file['size'] > $maxBytes) {
        $avatarError = "Image must be 2 MB or smaller.";
    } else {
        $uploadDir = __DIR__ . '/uploads/avatars/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'user_' . $uid . '_' . time() . '.' . strtolower($ext);
        $dest     = $uploadDir . $filename;
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $dbPath = 'uploads/avatars/' . $filename;
            $s = $conn->prepare("UPDATE users SET avatar=? WHERE id=?");
            $s->bind_param("si", $dbPath, $uid);
            $s->execute();
            $avatarSuccess = true;
        } else {
            $avatarError = "Could not save the image. Please try again.";
        }
    }
}

// ── HANDLE PROFILE UPDATE ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $username  = trim($_POST['username']  ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email']     ?? '');
    $phone     = trim($_POST['phone']     ?? '');
    $fb_acc    = trim($_POST['fb_acc']    ?? '');

    if (empty($username))                                             $errors[] = "Username is required.";
    if (empty($full_name))                                            $errors[] = "Full name is required.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "A valid email address is required.";
    if (!preg_match('/^[0-9]{11}$/', $phone))                        $errors[] = "Phone number must be exactly 11 digits (numbers only).";

    if (empty($errors)) {
        $u  = $conn->real_escape_string($username);
        $fn = $conn->real_escape_string($full_name);
        $em = $conn->real_escape_string($email);
        $ph = $conn->real_escape_string($phone);
        $fb = $conn->real_escape_string($fb_acc);
        $conn->query("UPDATE users SET username='$u', full_name='$fn', email='$em', phone='$ph', facebook='$fb' WHERE id=$uid");
        $success = true;
    }
}

// ── HANDLE ADDRESS UPDATE ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_address'])) {
    $municipality = $conn->real_escape_string($_POST['municipality'] ?? '');
    $barangay     = $conn->real_escape_string($_POST['barangay']     ?? '');
    $street       = $conn->real_escape_string($_POST['street']       ?? '');
    $lat          = floatval($_POST['lat'] ?? 0);
    $lng          = floatval($_POST['lng'] ?? 0);
    $addressParts = array_filter([$street, $barangay, $municipality, 'Misamis Oriental']);
    $full_address = implode(', ', $addressParts);
    $conn->query("UPDATE users SET address='$full_address', barangay='$barangay', latitude=$lat, longitude=$lng WHERE id=$uid");
    header("Location: profile.php?addr=1");
    exit();
}

// ── HANDLE PASSWORD CHANGE ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $cur  = $_POST['current_password'] ?? '';
    $new  = $_POST['new_password']     ?? '';
    $conf = $_POST['confirm_password'] ?? '';

    $stmt = $conn->prepare("SELECT password FROM users WHERE id=?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!password_verify($cur, $row['password'])) $errors[] = "Current password is incorrect.";
    elseif (strlen($new) < 8)                      $errors[] = "New password must be at least 8 characters.";
    elseif ($new !== $conf)                         $errors[] = "Passwords do not match.";
    else {
        $hashed = password_hash($new, PASSWORD_DEFAULT);
        $s2 = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $s2->bind_param("si", $hashed, $uid);
        $s2->execute();
        header("Location: profile.php?pw=1");
        exit();
    }
}

// ── FETCH USER ─────────────────────────────────────────────────────────────
// Ensure lat/lng columns exist before trying to SELECT them
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS latitude  DECIMAL(10,7) DEFAULT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) DEFAULT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS barangay  VARCHAR(120)  DEFAULT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS gender    ENUM('Male','Female','Non-binary','Prefer not to say') DEFAULT NULL");

$stmt = $conn->prepare("SELECT username, full_name, email, phone, address, latitude, longitude, facebook, role, avatar, gender FROM users WHERE id=?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Guard: if user not found, kill session and redirect to login
if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$showAddress = !empty($user['role']) && $user['role'] !== 'admin';

// Re-populate on validation error
if (!empty($errors) && isset($_POST['update_profile'])) {
    $user['username']  = $_POST['username']  ?? $user['username'];
    $user['full_name'] = $_POST['full_name'] ?? $user['full_name'];
    $user['email']     = $_POST['email']     ?? $user['email'];
    $user['phone']     = $_POST['phone']     ?? $user['phone'];
    $user['facebook']  = $_POST['fb_acc']    ?? $user['facebook'];
}

$misamis_oriental = [
    "Jasaan" => ["coordinates" => [8.6536, 124.8553], "barangays" => [
        "Bobontugan","Jampason","Lower Jasaan","Upper Jasaan",
        "Kimaya","San Antonio","Danao","Luz Banzon","Solana",
        "Aplaya","Corrales","Ignacio S. Cruz","Natubo",
        "San Isidro","San Nicolas","San Pedro","Sta. Cruz","Poblacion"
    ]],
    "Villanueva" => ["coordinates" => [8.5719, 124.7722], "barangays" => [
        "Balacanas","Dayawan","Imelda","Katipunan","Kimaya",
        "Looc","Poblacion 1","Poblacion 2","Poblacion 3","San Martin","Tambobong"
    ]],
    "Balingasag" => ["coordinates" => [8.4436, 124.7783], "barangays" => [
        "Balagnan","Baliwagan",
        "Barangay 1 (Poblacion)","Barangay 2 (Poblacion)","Barangay 3 (Poblacion)",
        "Barangay 4 (Poblacion)","Barangay 5 (Poblacion)","Barangay 6 (Poblacion)",
        "Binitinan","Blanco","Calawag","Camuayan","Cogon","Dansuli","Dumarait",
        "Hermano","Kibanban","Linabu","Linggangao","Mambayaan","Mandangoa",
        "Napaliran","Quezon","Rosario","Samay","San Francisco","San Isidro",
        "San Juan","Talusan","Waterfall"
    ]],
    "Bugo" => ["coordinates" => [8.5125, 124.6517], "barangays" => [
        "Bugo"
    ]],
    "Lagonglong" => ["coordinates" => [8.7833, 124.8000], "barangays" => [
        "Banglay","Dampil","Gaston","Kabulawan","Kauswagan",
        "Lumbo","Manaol","Poblacion","Tabok","Umagos"
    ]],
    "Tagoloan" => ["coordinates" => [8.7878, 124.7564], "barangays" => [
        "Baluarte","Casinglot","Gracia","Mohon","Natumolan",
        "Poblacion","Rosario","Santa Ana","Santa Cruz","Sugbongcogon"
    ]],
    "Puerto" => ["coordinates" => [8.7792, 124.4395], "barangays" => [
        "Puerto"
    ]],
    "Salay" => ["coordinates" => [8.8167, 124.8333], "barangays" => [
        "Alipuaton","Ampenican","Bunal","Casulog","Dinagsaan",
        "Guinalaban","Ili-ilihon","Inobulan","Looc","Matampa",
        "Membuli","Poblacion","Salagsag","Salay River I","Salay River II",
        "Saray","Tinagaan","Yungod"
    ]],
];

// Which tab should be active on load?
$activeTab = 'profile';
if (isset($_GET['addr']) || (isset($_POST['update_address']))) $activeTab = 'address';
if (isset($_GET['pw'])   || (!empty($errors) && isset($_POST['update_password']))) $activeTab = 'password';
if (!empty($errors) && isset($_POST['update_profile'])) $activeTab = 'profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — Precious Satin Bouquet</title>
    <link rel="icon" type="image/png" href="images/logo/SB.png">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css"/>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --blush:      #f9d0dd;
    --petal:      #f2a7bf;
    --rose:       #d96b8a;
    --deep-rose:  #b84d6a;
    --darker:     #3d1122;
    --champagne:  #fdf3f6;
    --soft-white: #fffafc;
    --text-dark:  #1e0d14;
    --text-mid:   #5a2a3a;
    --text-hint:  #a06070;
    --shadow:     rgba(180,80,110,0.15);
}

body {
    font-family: 'Jost', sans-serif;
    background: var(--champagne);
    min-height: 100vh;
}

/* ── OUTER WRAPPER ── */
.profile-shell {
    max-width: 1080px;
    margin: 0 auto;
    padding: 32px 20px 48px;
}

/* ── LANDSCAPE GRID ── */
.profile-grid {
    display: grid !important;
    grid-template-columns: 260px 1fr !important;
    gap: 24px;
    align-items: start;
}

/* Reset any inherited stacking from style.css */
.profile-shell *,
.profile-shell *::before,
.profile-shell *::after {
    box-sizing: border-box;
}

/* ══════════════════════════════════════════
   LEFT SIDEBAR
══════════════════════════════════════════ */
.profile-sidebar {
    background: var(--soft-white);
    border-radius: 24px;
    box-shadow: 0 6px 32px var(--shadow);
    overflow: hidden;
    position: sticky;
    top: 24px;
}

.profile-sidebar-top {
    background: linear-gradient(160deg, var(--rose) 0%, var(--deep-rose) 100%);
    padding: 32px 20px 28px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    position: relative;
}

/* Decorative petal shapes */
.profile-sidebar-top::before,
.profile-sidebar-top::after {
    content: '';
    position: absolute;
    border-radius: 50%;
    background: rgba(255,255,255,0.07);
    pointer-events: none;
}
.profile-sidebar-top::before { width: 140px; height: 140px; top: -40px; right: -40px; }
.profile-sidebar-top::after  { width: 80px;  height: 80px;  bottom: -20px; left: -20px; }

/* Avatar */
.avatar-upload-wrap { position: relative; display: inline-block; cursor: pointer; }
.avatar-upload-wrap input[type=file] { display: none; }

.avatar-ring {
    width: 88px; height: 88px; border-radius: 50%;
    background: rgba(255,255,255,0.22);
    display: flex; align-items: center; justify-content: center;
    font-family: 'Cormorant Garamond', serif; font-size: 38px;
    font-weight: 600; color: #fff;
    border: 3px solid rgba(255,255,255,0.5);
    overflow: hidden;
    transition: transform 0.2s;
}
.avatar-ring img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
.avatar-upload-wrap:hover .avatar-ring { transform: scale(1.04); }

.avatar-cam {
    position: absolute; bottom: 2px; right: 2px;
    background: #fff; border-radius: 50%;
    width: 26px; height: 26px;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.18);
    transition: transform 0.2s;
}
.avatar-upload-wrap:hover .avatar-cam { transform: scale(1.15); }
.avatar-cam svg { color: var(--rose); }

.profile-sidebar-name {
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.45rem; font-weight: 400; font-style: italic;
    color: #fff; text-align: center; line-height: 1.3;
}
.profile-sidebar-role {
    font-size: 0.68rem; font-weight: 600; letter-spacing: 0.22em;
    text-transform: uppercase; color: rgba(255,255,255,0.9);
    background: rgba(255,255,255,0.2);
    padding: 4px 14px; border-radius: 20px;
}
.avatar-hint {
    font-size: 0.68rem; color: rgba(255,255,255,0.75);
    letter-spacing: 0.04em; text-align: center;
}

/* Avatar flash messages */
.avatar-flash {
    font-size: 0.72rem; text-align: center;
    padding: 6px 12px; border-radius: 10px; margin-top: 2px;
}
.avatar-flash.ok  { background: rgba(255,255,255,0.2); color: #fff; }
.avatar-flash.err { background: rgba(255,255,255,0.15); color: #ffe0e8; }

/* Nav tabs */
.profile-sidebar-nav { padding: 16px 12px; display: flex; flex-direction: column; gap: 4px; }

.tab-btn {
    display: flex; align-items: center; gap: 10px;
    width: 100%; padding: 11px 16px; border-radius: 12px;
    border: none; background: none; cursor: pointer;
    font-family: 'Jost', sans-serif; font-size: 0.9rem;
    font-weight: 500; letter-spacing: 0.04em;
    color: var(--text-mid); text-align: left;
    transition: background 0.18s, color 0.18s;
}
.tab-btn svg { flex-shrink: 0; opacity: 0.65; transition: opacity 0.18s; }
.tab-btn:hover { background: var(--blush); color: var(--deep-rose); }
.tab-btn:hover svg { opacity: 1; }
.tab-btn.active {
    background: linear-gradient(135deg, var(--blush), #fce4ee);
    color: var(--deep-rose); font-weight: 600;
}
.tab-btn.active svg { opacity: 1; }

/* Logout */
.profile-sidebar-footer { padding: 12px 12px 20px; }
.btn-logout {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    width: 100%; padding: 11px 16px; border-radius: 12px;
    border: 1.5px solid var(--petal); background: none; cursor: pointer;
    font-family: 'Jost', sans-serif; font-size: 0.85rem;
    font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase;
    color: var(--deep-rose); text-decoration: none;
    transition: background 0.2s, color 0.2s, border-color 0.2s;
}
.btn-logout:hover {
    background: linear-gradient(135deg, var(--rose), var(--deep-rose));
    color: #fff; border-color: transparent;
}

/* ══════════════════════════════════════════
   RIGHT PANEL
══════════════════════════════════════════ */
.panel-area { position: relative; }

.tab-panel {
    display: none;
    background: var(--soft-white);
    border-radius: 24px;
    box-shadow: 0 6px 32px var(--shadow);
    overflow: hidden;
    animation: fadeUp 0.25s ease;
}
.tab-panel.active { display: block; }

@keyframes fadeUp {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}

.panel-header {
    background: linear-gradient(135deg, var(--blush), #fce4ee);
    border-bottom: 1.5px solid var(--petal);
    padding: 20px 28px;
    display: flex; align-items: center; gap: 12px;
}
.panel-header svg { color: var(--rose); flex-shrink: 0; }
.panel-header-title {
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.5rem; font-weight: 600; color: var(--text-dark);
}
.panel-body { padding: 28px 28px 24px; }

/* ── Alerts ── */
.alert {
    border-radius: 12px; padding: 12px 16px; font-size: 0.92rem;
    margin-bottom: 20px; display: flex; align-items: flex-start; gap: 10px;
}
.alert-success { background: #f0faf4; border: 1.5px solid #7dcea0; color: #276244; }
.alert-error   { background: #fff0f6; border: 1.5px solid var(--petal); color: var(--deep-rose); }
.alert strong  { font-weight: 600; display: block; margin-bottom: 2px; }
.alert ul { padding-left: 1.1rem; margin-top: 4px; }
.alert ul li { line-height: 1.7; font-weight: 300; }

/* ── Fields ── */
.field { margin-bottom: 16px; }
.field label {
    display: block; font-size: 0.75rem; letter-spacing: 0.14em;
    text-transform: uppercase; color: var(--text-mid); font-weight: 600; margin-bottom: 6px;
}
.field input,
.field select {
    font-family: 'Jost', sans-serif; font-size: 0.98rem; font-weight: 400;
    color: var(--text-dark); width: 100%; background: var(--champagne);
    border: 1.5px solid rgba(242,167,191,0.4); border-radius: 12px;
    padding: 11px 14px; outline: none;
    transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
}
.field input::placeholder { color: var(--text-hint); }
.field input:focus,
.field select:focus {
    border-color: var(--petal); background: var(--soft-white);
    box-shadow: 0 0 0 3px rgba(242,167,191,0.2);
}
.field input[readonly] { background: #f5e8ed; color: var(--text-hint); cursor: not-allowed; }

/* ── Custom girly dropdowns ── */
.field select:not(#gender) { display: none; } /* hide native selects (municipality/barangay use custom UI) */

.custom-select-wrap {
    position: relative;
    width: 100%;
    user-select: none;
    z-index: 1000;
}

.custom-select-wrap.open {
    z-index: 1001;
}

.custom-select-trigger {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    width: 100%;
    padding: 11px 14px;
    background: var(--champagne);
    border: 1.5px solid rgba(242,167,191,0.45);
    border-radius: 14px;
    font-family: 'Jost', sans-serif;
    font-size: 0.95rem;
    font-weight: 400;
    color: var(--text-dark);
    cursor: pointer;
    transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
    outline: none;
}
.custom-select-trigger .trigger-text {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.custom-select-trigger .trigger-text.placeholder { color: var(--text-hint); font-style: italic; }

.custom-select-trigger .trigger-arrow {
    width: 28px; height: 28px;
    background: linear-gradient(135deg, var(--blush), #fce4ee);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    transition: transform 0.25s cubic-bezier(.34,1.56,.64,1), background 0.2s;
}
.custom-select-trigger .trigger-arrow svg {
    color: var(--rose);
    transition: transform 0.25s cubic-bezier(.34,1.56,.64,1);
}

.custom-select-wrap.open .custom-select-trigger {
    border-color: var(--petal);
    background: var(--soft-white);
    box-shadow: 0 0 0 3px rgba(242,167,191,0.22);
}
.custom-select-wrap.open .trigger-arrow {
    background: linear-gradient(135deg, var(--petal), var(--rose));
    transform: rotate(0deg);
}
.custom-select-wrap.open .trigger-arrow svg {
    color: #fff;
    transform: rotate(180deg);
}

.custom-select-dropdown {
    display: none;
    position: absolute;
    top: calc(100% + 6px);
    left: 0; right: 0;
    background: var(--soft-white);
    border: 1.5px solid rgba(242,167,191,0.5);
    border-radius: 16px;
    box-shadow: 0 12px 40px rgba(180,80,110,0.18), 0 2px 8px rgba(180,80,110,0.1);
    z-index: 1100;
    overflow: hidden;
    animation: dropIn 0.22s cubic-bezier(.34,1.56,.64,1);
}
@keyframes dropIn {
    from { opacity: 0; transform: translateY(-8px) scale(0.97); }
    to   { opacity: 1; transform: translateY(0)   scale(1); }
}

.custom-select-wrap.open .custom-select-dropdown { display: block; }

.dropdown-list {
    max-height: 210px;
    overflow-y: auto;
    padding: 6px 6px;
    scrollbar-width: thin;
    scrollbar-color: var(--petal) transparent;
}
.dropdown-list::-webkit-scrollbar { width: 4px; }
.dropdown-list::-webkit-scrollbar-track { background: transparent; }
.dropdown-list::-webkit-scrollbar-thumb { background: var(--petal); border-radius: 4px; }

.dropdown-option {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 9px 12px;
    border-radius: 10px;
    font-family: 'Jost', sans-serif;
    font-size: 0.92rem;
    color: var(--text-mid);
    cursor: pointer;
    transition: background 0.15s, color 0.15s, padding-left 0.15s;
}
.dropdown-option:hover {
    background: linear-gradient(135deg, var(--blush), #fce4ee);
    color: var(--deep-rose);
    padding-left: 16px;
}
.dropdown-option.selected {
    background: linear-gradient(135deg, var(--petal), #f7c0d5);
    color: var(--deep-rose);
    font-weight: 600;
}
.dropdown-option.placeholder-opt {
    color: var(--text-hint);
    font-style: italic;
    font-size: 0.85rem;
}
.dropdown-option .opt-dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    background: var(--petal);
    flex-shrink: 0;
    transition: background 0.15s, transform 0.15s;
}
.dropdown-option.selected .opt-dot {
    background: var(--deep-rose);
    transform: scale(1.4);
}
.dropdown-option:hover .opt-dot { background: var(--rose); }
.dropdown-option.placeholder-opt .opt-dot { opacity: 0; }
.dropdown-no-results {
    text-align: center;
    padding: 14px;
    color: var(--text-hint);
    font-style: italic;
    font-size: 0.82rem;
}

.field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px; }

.field-hint { font-size: 0.75rem; color: var(--text-hint); margin-top: 4px; letter-spacing: 0.03em; }
.field-hint.ok   { color: #276244; }
.field-hint.warn { color: var(--deep-rose); }

/* Password */
.pw-wrap { position: relative; }
.pw-wrap input { padding-right: 68px; }
.pw-toggle {
    position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
    background: none; border: none; font-family: 'Jost', sans-serif;
    font-size: 0.68rem; font-weight: 600; letter-spacing: 0.08em;
    color: var(--rose); cursor: pointer; padding: 4px; text-transform: uppercase;
}
.pw-toggle:hover { color: var(--deep-rose); }

/* Save button */
.btn-save {
    font-family: 'Jost', sans-serif; font-size: 0.78rem; font-weight: 600;
    letter-spacing: 0.16em; text-transform: uppercase; color: #fff;
    background: linear-gradient(135deg, var(--rose), var(--deep-rose));
    border: none; border-radius: 12px; padding: 13px 28px; cursor: pointer;
    margin-top: 18px; width: 100%;
    transition: transform 0.2s, box-shadow 0.2s, filter 0.2s;
    box-shadow: 0 6px 20px rgba(180,80,110,0.28);
}
.btn-save:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(180,80,110,0.38); filter: brightness(1.05); }
.btn-save:active { transform: translateY(0); }

/* Map */
.map-container {
    display: block;
    margin-top: 20px;
    border-radius: 16px;
    overflow: hidden;
    border: 2px solid var(--petal);
    box-shadow: 0 4px 20px rgba(180,80,110,0.13);
}
.map-topbar {
    background: linear-gradient(135deg, var(--blush), #fce4ee);
    padding: 9px 14px;
    display: flex;
    align-items: flex-start;
    gap: 8px;
    border-bottom: 1px solid var(--petal);
    font-size: 0.78rem;
    font-weight: 500;
    letter-spacing: 0.06em;
    color: var(--text-mid);
    flex-wrap: wrap;
}
.map-topbar svg { color: var(--rose); flex-shrink: 0; }
#customer-map { width: 100%; height: 340px; }
.map-preview {
    background: var(--champagne);
    border-top: 1px solid var(--petal);
    padding: 9px 14px;
    font-size: 0.82rem;
    color: var(--text-dark);
    display: flex;
    align-items: center;
    gap: 7px;
    min-height: 38px;
}
.map-preview-coords {
    margin-left: auto;
    font-size: 0.7rem;
    color: var(--text-hint);
    white-space: nowrap;
}
.map-loading { text-align: center; padding: 10px 14px; color: var(--rose); font-size: 0.82rem; }

/* ── Leaflet popup theming ── */
.leaflet-popup-content-wrapper {
    border-radius: 16px !important;
    box-shadow: 0 8px 32px rgba(180,80,110,0.22) !important;
    border: 1.5px solid #f2a7bf !important;
    padding: 0 !important;
    overflow: hidden;
}
.leaflet-popup-content { margin: 14px 16px !important; min-width: 160px !important; }
.leaflet-popup-tip { background: #fff !important; box-shadow: none !important; }
.leaflet-popup-close-button { color: #d96b8a !important; font-size: 18px !important; top: 6px !important; right: 8px !important; }

/* ── Responsive ── */
@media (max-width: 720px) {
    .profile-grid { grid-template-columns: 1fr; }
    .profile-sidebar { position: static; }
    .profile-sidebar-nav { flex-direction: row; flex-wrap: wrap; }
    .tab-btn { flex: 1 1 auto; justify-content: center; }
}
@media (max-width: 480px) {
    .field-row { grid-template-columns: 1fr; }
}
</style>
<!-- Loaded AFTER profile styles so our layout rules take priority -->
<link rel="stylesheet" href="style.css">
<style>
/* Nuclear override — ensure nothing from style.css collapses our grid */
.profile-grid { display: grid !important; grid-template-columns: 260px 1fr !important; }
.profile-shell { max-width: 1080px !important; }
.profile-sidebar { display: block !important; width: auto !important; }
.tab-panel.active { display: block !important; }
</style>
<?php include __DIR__ . '/pwa_head.php'; ?>
</head>
<body>

<?php include "header.php"; ?>

<div class="profile-shell">
<div class="profile-grid">

    <!-- ══════════ SIDEBAR ══════════ -->
    <aside class="profile-sidebar">

        <div class="profile-sidebar-top">
            <!-- Avatar upload -->
            <form method="POST" enctype="multipart/form-data" id="avatarForm">
                <label class="avatar-upload-wrap" for="avatarInput" title="Change profile photo">
                    <div class="avatar-ring">
                        <?php if (!empty($user['avatar']) && file_exists(__DIR__ . '/' . $user['avatar'])): ?>
                            <img src="<?= htmlspecialchars($user['avatar']) ?>?v=<?= time() ?>" alt="Profile photo">
                        <?php else: ?>
                            <?= strtoupper(substr($user['username'] ?? 'U', 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <span class="avatar-cam">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                            <circle cx="12" cy="13" r="4"/>
                        </svg>
                    </span>
                    <input type="file" id="avatarInput" name="avatar"
                           accept="image/jpeg,image/png,image/gif,image/webp"
                           onchange="document.getElementById('avatarForm').submit()">
                </label>
            </form>

            <div class="profile-sidebar-name"><?= htmlspecialchars($user['full_name'] ?: ($user['username'] ?? 'My Profile')) ?></div>
            <div class="profile-sidebar-role"><?= strtoupper($user['role'] ?? 'Customer') ?></div>
            <p class="avatar-hint">Click photo to change · Max 2 MB</p>

            <?php if ($avatarSuccess): ?>
                <div class="avatar-flash ok">✓ Photo updated!</div>
            <?php elseif ($avatarError): ?>
                <div class="avatar-flash err"><?= htmlspecialchars($avatarError) ?></div>
            <?php endif; ?>
        </div>

        <!-- Nav -->
        <nav class="profile-sidebar-nav">
            <button class="tab-btn <?= $activeTab === 'profile'  ? 'active' : '' ?>" onclick="switchTab('profile')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                </svg>
                Personal Info
            </button>
            <?php if ($showAddress): ?>
            <button class="tab-btn <?= $activeTab === 'address' ? 'active' : '' ?>" onclick="switchTab('address')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0z"/><circle cx="12" cy="10" r="3"/>
                </svg>
                Delivery Address
            </button>
            <?php endif; ?>
            <button class="tab-btn <?= $activeTab === 'password' ? 'active' : '' ?>" onclick="switchTab('password')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
                Change Password
            </button>
        </nav>

        <div class="profile-sidebar-footer">
            <button type="button" class="btn-logout" id="logoutTrigger">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
                Logout
            </button>
        </div>
    </aside>

    <!-- ══════════ RIGHT PANELS ══════════ -->
    <div class="panel-area">

        <!-- Flash banners (addr / pw success) -->
        <?php if (isset($_GET['addr'])): ?>
            <div class="alert alert-success" style="margin-bottom:16px;border-radius:16px;">
                <strong>✓ Address updated successfully!</strong>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['pw'])): ?>
            <div class="alert alert-success" style="margin-bottom:16px;border-radius:16px;">
                <strong>✓ Password changed successfully!</strong>
            </div>
        <?php endif; ?>

        <!-- ── PANEL: Personal Info ── -->
        <div class="tab-panel <?= $activeTab === 'profile' ? 'active' : '' ?>" id="panel-profile">
            <div class="panel-header">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                </svg>
                <span class="panel-header-title">Personal Information</span>
            </div>
            <div class="panel-body">
                <?php if ($success): ?>
                    <div class="alert alert-success"><strong>✓ Profile updated successfully!</strong></div>
                <?php elseif (!empty($errors) && isset($_POST['update_profile'])): ?>
                    <div class="alert alert-error">
                        <div><strong>Please fix the following:</strong>
                        <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
                    </div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <div class="field-row">
                        <div class="field">
                            <label for="full_name">Full Name</label>
                            <input type="text" id="full_name" name="full_name"
                                   placeholder="Your full name"
                                   value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" required>
                        </div>
                        <div class="field">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username"
                                   placeholder="Choose a username"
                                   value="<?= htmlspecialchars($user['username'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="field-row">
                        <div class="field">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email"
                                   placeholder="your@email.com"
                                   value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                        </div>
                        <div class="field">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone"
                                   placeholder="09XXXXXXXXX"
                                   value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                                   maxlength="11" inputmode="numeric" pattern="[0-9]{11}" required>
                            <p class="field-hint" id="phone-hint">11 digits only — no spaces, dashes, or letters.</p>
                        </div>
                    </div>

                    <div class="field">
                        <label for="fb_acc">Facebook Account</label>
                        <input type="text" id="fb_acc" name="fb_acc"
                               placeholder="e.g. facebook.com/yourprofile"
                               value="<?= htmlspecialchars($user['facebook'] ?? '') ?>">
                    </div>

                    <div class="field">
                        <label>Gender</label>
                        <?php
                            $icons = ['Male'=>'♂','Female'=>'♀','Non-binary'=>'⚧','Prefer not to say'=>'–'];
                            $curG  = $user['gender'] ?? '';
                        ?>
                        <input type="text"
                               value="<?= $curG ? ($icons[$curG] ?? '') . '  ' . htmlspecialchars($curG) : 'Not specified' ?>"
                               readonly>
                    </div>

                    <button type="submit" name="update_profile" class="btn-save">Save Profile</button>
                </form>
            </div>
        </div>

        <!-- ── PANEL: Delivery Address ── -->
        <?php if ($showAddress): ?>
        <div class="tab-panel <?= $activeTab === 'address' ? 'active' : '' ?>" id="panel-address">
            <div class="panel-header">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0z"/><circle cx="12" cy="10" r="3"/>
                </svg>
                <span class="panel-header-title">Delivery Address <span style="font-size:0.8rem;font-weight:300;color:var(--text-hint);">(Misamis Oriental)</span></span>
            </div>
            <div class="panel-body">
                <form method="POST" novalidate>
                    <div class="field-row">
                        <div class="field">
                            <label>Province</label>
                            <input type="text" value="Misamis Oriental" readonly>
                        </div>
                        <div class="field">
                            <label for="municipalitySelect">Municipality / City</label>
                            <select name="municipality" id="municipalitySelect" required>
                                <option value="">— Select Municipality —</option>
                                <?php foreach (array_keys($misamis_oriental) as $m): ?>
                                    <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="custom-select-wrap" id="muniWrap">
                                <div class="custom-select-trigger" id="muniTrigger" tabindex="0">
                                    <span class="trigger-text placeholder" id="muniTriggerText">— Select Municipality —</span>
                                    <span class="trigger-arrow">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                                    </span>
                                </div>
                                <div class="custom-select-dropdown" id="muniDropdown">
                                    <div class="dropdown-list" id="muniList">
                                        <div class="dropdown-option placeholder-opt" data-value=""><span class="opt-dot"></span>— Select Municipality —</div>
                                        <?php foreach (array_keys($misamis_oriental) as $m): ?>
                                            <div class="dropdown-option" data-value="<?= htmlspecialchars($m) ?>"><span class="opt-dot"></span><?= htmlspecialchars($m) ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="field-row">
                        <div class="field">
                            <label for="barangaySelect">Barangay</label>
                            <select name="barangay" id="barangaySelect" required>
                                <option value="">— Select Barangay —</option>
                            </select>
                            <div class="custom-select-wrap" id="bgyWrap">
                                <div class="custom-select-trigger" id="bgyTrigger" tabindex="0">
                                    <span class="trigger-text placeholder" id="bgyTriggerText">— Select Barangay —</span>
                                    <span class="trigger-arrow">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                                    </span>
                                </div>
                                <div class="custom-select-dropdown" id="bgyDropdown">
                                    <div class="dropdown-list" id="bgyList">
                                        <div class="dropdown-option placeholder-opt" data-value=""><span class="opt-dot"></span>— Select Barangay —</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="field">
                            <label for="streetInput">Purok / Street / House No.</label>
                            <input type="text" name="street" id="streetInput"
                                   placeholder="e.g. Purok 1, Block 2"
                                   value="<?php
                                       if (!empty($user['address'])) {
                                           $parts = explode(',', $user['address']);
                                           echo htmlspecialchars(trim($parts[0]));
                                       }
                                   ?>">
                        </div>
                    </div>

                    <!-- Hidden lat/lng sent with form -->
                    <input type="hidden" name="lat" id="hiddenLat">
                    <input type="hidden" name="lng" id="hiddenLng">

                    <button type="submit" name="update_address" class="btn-save" id="btnSave" onclick="return injectCoords()">
                        📍 Save Address & Pin Location
                    </button>
                </form>

                <div id="map-loading" class="map-loading" style="display:none;"></div>

                <div id="map-container" class="map-container">
                    <div class="map-topbar">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                            <path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0z"/><circle cx="12" cy="10" r="3"/>
                        </svg>
                        Drag the pin or click the map to set your exact delivery spot
                    </div>
                    <div id="customer-map"></div>
                    <div class="map-preview" id="mapPreview">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        <span id="previewText" style="color:var(--text-hint);font-style:italic;">Drag the pin to update address</span>
                        <span class="map-preview-coords" id="previewCoords"></span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── PANEL: Change Password ── -->
        <div class="tab-panel <?= $activeTab === 'password' ? 'active' : '' ?>" id="panel-password">
            <div class="panel-header">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
                <span class="panel-header-title">Change Password</span>
            </div>
            <div class="panel-body">
                <?php if (!empty($errors) && isset($_POST['update_password'])): ?>
                    <div class="alert alert-error">
                        <div><strong>Please fix the following:</strong>
                        <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
                    </div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <div class="field">
                        <label for="current_password">Current Password</label>
                        <div class="pw-wrap">
                            <input type="password" id="current_password" name="current_password"
                                   placeholder="Enter current password" required>
                            <button type="button" class="pw-toggle" onclick="togglePw('current_password',this)">Show</button>
                        </div>
                    </div>

                    <div class="field-row">
                        <div class="field">
                            <label for="new_password">New Password</label>
                            <div class="pw-wrap">
                                <input type="password" id="new_password" name="new_password"
                                       placeholder="Minimum 8 characters" required>
                                <button type="button" class="pw-toggle" onclick="togglePw('new_password',this)">Show</button>
                            </div>
                        </div>
                        <div class="field">
                            <label for="confirm_password">Confirm New Password</label>
                            <div class="pw-wrap">
                                <input type="password" id="confirm_password" name="confirm_password"
                                       placeholder="Repeat new password" required>
                                <button type="button" class="pw-toggle" onclick="togglePw('confirm_password',this)">Show</button>
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="update_password" class="btn-save">Update Password</button>
                </form>
            </div>
        </div>

    </div><!-- end panel-area -->
</div><!-- end profile-grid -->
</div><!-- end profile-shell -->

<script>
// ── Tab switching ──
function switchTab(name) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    const panel = document.getElementById('panel-' + name);
    if (panel) panel.classList.add('active');
    document.querySelectorAll('.tab-btn').forEach(b => {
        if (b.getAttribute('onclick') === "switchTab('" + name + "')") b.classList.add('active');
    });
    if (name === 'address' && typeof updateMap === 'function') {
        setTimeout(() => { if (map) map.invalidateSize(); }, 150);
    }
}

// ── Phone: digits only, max 11 ──
const phoneInput = document.getElementById('phone');
const phoneHint  = document.getElementById('phone-hint');
if (phoneInput) {
    phoneInput.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 11);
        const len = this.value.length;
        if (len === 0) {
            phoneHint.textContent = '11 digits only — no spaces, dashes, or letters.';
            phoneHint.className = 'field-hint';
        } else if (len < 11) {
            phoneHint.textContent = `${len} / 11 digits entered.`;
            phoneHint.className = 'field-hint warn';
        } else {
            phoneHint.textContent = '✓ Looks good!';
            phoneHint.className = 'field-hint ok';
        }
    });
}

// ── Password toggle ──
function togglePw(id, btn) {
    const inp = document.getElementById(id);
    if (inp.type === 'password') { inp.type = 'text';     btn.textContent = 'Hide'; }
    else                         { inp.type = 'password'; btn.textContent = 'Show'; }
}
</script>

<?php if ($showAddress): ?>
<script>
const misamis_oriental_data = <?= json_encode($misamis_oriental); ?>;
const currentAddress = <?= json_encode($user['address'] ?? '') ?>;
const savedLat = <?= !empty($user['latitude'])  ? floatval($user['latitude'])  : 'null' ?>;
const savedLng = <?= !empty($user['longitude']) ? floatval($user['longitude']) : 'null' ?>;

let map = null, marker = null;
let pinLat = null, pinLng = null;
// Track whether the user has manually placed a pin this session
let pinManuallyPlaced = false;

// ── Pink draggable pin ──
const pinkIcon = L.divIcon({
    className: '',
    html: `<div style="filter:drop-shadow(0 4px 8px rgba(180,80,110,0.45));cursor:grab;">
        <svg width="34" height="44" viewBox="0 0 34 44" xmlns="http://www.w3.org/2000/svg">
            <path d="M17 2C10.4 2 5 7.4 5 14c0 9.5 12 28 12 28S29 23.5 29 14C29 7.4 23.6 2 17 2z"
                  fill="#d96b8a" stroke="#fff" stroke-width="1.5"/>
            <circle cx="17" cy="14" r="6" fill="#fff" opacity="0.95"/>
            <circle cx="17" cy="14" r="3" fill="#d96b8a"/>
        </svg>
    </div>`,
    iconSize: [34, 44], iconAnchor: [17, 44], popupAnchor: [0, -46]
});

// ── Reverse geocode via Nominatim ──
function reverseGeocode(lat, lng, cb) {
    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`,
          { headers: { 'Accept-Language': 'en' } })
        .then(r => r.json())
        .then(d => cb(d?.display_name || ''))
        .catch(() => cb(''));
}

// ── Update preview bar ──
function setPreview(lat, lng, addr) {
    pinLat = lat; pinLng = lng;
    document.getElementById('previewCoords').textContent = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
    if (addr) {
        document.getElementById('previewText').innerHTML = `<strong style="color:var(--deep-rose);">📍</strong> ${addr}`;
    } else {
        document.getElementById('previewText').innerHTML = `<span style="color:var(--text-hint);font-style:italic;">Resolving address…</span>`;
    }
}

// ── Place pin at exact coordinates (user-initiated: click or drag) ──
function placePinAt(lat, lng) {
    if (!marker) return;
    pinManuallyPlaced = true;
    marker.setLatLng([lat, lng]);
    map.panTo([lat, lng]);
    setPreview(lat, lng, '');
    reverseGeocode(lat, lng, addr => {
        setPreview(lat, lng, addr);
        marker.getPopup()?.setContent(popupHtml(addr || `${lat.toFixed(5)}, ${lng.toFixed(5)}`));
        marker.openPopup();
    });
}

function popupHtml(label) {
    return `<div style="font-family:'Jost',sans-serif;min-width:160px;max-width:220px;padding:4px 2px;">
        <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px;">
            <span style="display:inline-flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#f2a7bf,#d96b8a);border-radius:7px;width:24px;height:24px;flex-shrink:0;">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="white" stroke="none"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
            </span>
            <span style="font-size:0.63rem;font-weight:700;letter-spacing:0.15em;text-transform:uppercase;color:#b84d6a;">Your Pin</span>
        </div>
        <div style="font-size:0.88rem;font-weight:600;color:#3d1122;line-height:1.55;word-break:break-word;">${label}</div>
        <div style="margin-top:9px;padding-top:7px;border-top:1px dashed #f2a7bf;display:flex;align-items:center;gap:5px;">
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#d96b8a" stroke-width="2.5" stroke-linecap="round"><path d="M5 9l7 7 7-7"/></svg>
            <span style="font-size:0.67rem;color:#a06070;letter-spacing:0.04em;">Drag to adjust</span>
        </div>
    </div>`;
}

// ── Inject coords before submit ──
function injectCoords() {
    if (pinLat !== null && pinLng !== null) {
        document.getElementById('hiddenLat').value = pinLat;
        document.getElementById('hiddenLng').value = pinLng;
    }
    return true;
}

// ── Init Leaflet map (only creates map once) ──
function initMap(centerLat, centerLng, zoom) {
    if (map) {
        // Map already exists — just resize it, don't move anything
        map.invalidateSize();
        return;
    }
    map = L.map('customer-map', { zoomControl: true }).setView([centerLat, centerLng], zoom);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19
    }).addTo(map);

    marker = L.marker([centerLat, centerLng], { icon: pinkIcon, draggable: true })
        .bindPopup(popupHtml(currentAddress || 'Your location'), { maxWidth: 260 })
        .addTo(map)
        .openPopup();

    // Drag = user manually repositioning pin
    marker.on('dragend', e => {
        const { lat, lng } = e.target.getLatLng();
        placePinAt(lat, lng);
    });

    // Click on map = user placing pin at that spot
    map.on('click', e => placePinAt(e.latlng.lat, e.latlng.lng));

    pinLat = centerLat; pinLng = centerLng;
    if (currentAddress) setPreview(centerLat, centerLng, currentAddress);
    setTimeout(() => map.invalidateSize(), 100);
}

// ── Show map for the first time ──
function showMap(lat, lng, zoom) {
    document.getElementById('map-loading').style.display = 'none';
    initMap(lat, lng, zoom);
}

// ── Pan map + move pin to new geocoded location (dropdown-triggered only) ──
// Only moves pin if user has NOT manually placed it yet this session
function panMapTo(lat, lng, zoom) {
    if (!map) {
        showMap(lat, lng, zoom);
        return;
    }
    map.setView([lat, lng], zoom);
    // Only move the pin if user hasn't manually placed it
    if (!pinManuallyPlaced) {
        marker.setLatLng([lat, lng]);
        pinLat = lat; pinLng = lng;
        reverseGeocode(lat, lng, addr => {
            setPreview(lat, lng, addr);
            marker.getPopup()?.setContent(popupHtml(addr || `${lat.toFixed(5)}, ${lng.toFixed(5)}`));
            marker.openPopup();
        });
    }
}

// ── Geocode via Nominatim ──
function geocodeAddress(q) {
    return fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(q) + '&limit=1&countrycodes=ph',
                 { headers: { 'Accept-Language': 'en' } })
        .then(r => r.json()).then(d => d.length ? d[0] : null).catch(() => null);
}

// ── Called when municipality or barangay changes ──
function updateBarangayOptions() {
    const muni = document.getElementById('municipalitySelect').value;
    const sel  = document.getElementById('barangaySelect');
    sel.innerHTML = '<option value="">— Select Barangay —</option>';
    const data = misamis_oriental_data[muni];

    // Reset custom barangay dropdown
    const bgyList = document.getElementById('bgyList');
    bgyList.innerHTML = '<div class="dropdown-option placeholder-opt" data-value=""><span class="opt-dot"></span>— Select Barangay —</div>';
    setCustomSelect('bgy', '', '— Select Barangay —', true);

    if (data?.barangays?.length) {
        data.barangays.forEach(b => {
            const o = document.createElement('option');
            o.value = o.textContent = b;
            sel.appendChild(o);
            const div = document.createElement('div');
            div.className = 'dropdown-option';
            div.dataset.value = b;
            div.innerHTML = `<span class="opt-dot"></span>${b}`;
            div.addEventListener('click', () => {
                sel.value = b;
                setCustomSelect('bgy', b, b, false);
                closeAllDropdowns();
                sel.dispatchEvent(new Event('change'));
            });
            bgyList.appendChild(div);
        });
        sel.required = true;
    } else {
        sel.required = false;
    }
}

// ── Update map when dropdowns change ──
// Geocodes the selected address and pans/pins to it (unless user already manually pinned)
function updateMapFromDropdowns() {
    const muni = document.getElementById('municipalitySelect').value;
    const bgy  = document.getElementById('barangaySelect').value;
    const data = misamis_oriental_data[muni];
    if (!muni || !data) return;

    const queries = [];
    if (bgy) queries.push(`${bgy}, ${muni}, Misamis Oriental, Philippines`);
    queries.push(`${muni}, Misamis Oriental, Philippines`);

    (function tryNext(i) {
        if (i >= queries.length) {
            // Fall back to hardcoded coordinates
            const zoom = bgy ? 15 : 13;
            if (!map) showMap(data.coordinates[0], data.coordinates[1], zoom);
            else panMapTo(data.coordinates[0], data.coordinates[1], zoom);
            return;
        }
        geocodeAddress(queries[i]).then(r => {
            if (r && r.lat && r.lon) {
                const zoom = bgy ? 16 : 13;
                panMapTo(parseFloat(r.lat), parseFloat(r.lon), zoom);
            } else {
                tryNext(i + 1);
            }
        });
    })(0);
}

// ── Custom select helpers ──
function setCustomSelect(prefix, value, label, isPlaceholder) {
    const triggerText = document.getElementById(prefix + 'TriggerText');
    triggerText.textContent = label;
    triggerText.classList.toggle('placeholder', isPlaceholder);
    // Mark selected option
    document.querySelectorAll('#' + prefix + 'List .dropdown-option').forEach(opt => {
        opt.classList.toggle('selected', opt.dataset.value === value && value !== '');
    });
}

function closeAllDropdowns() {
    document.querySelectorAll('.custom-select-wrap.open').forEach(w => w.classList.remove('open'));
}

function initCustomSelect(prefix, nativeId, listId, triggerId, wrapId) {
    const wrap    = document.getElementById(wrapId);
    const trigger = document.getElementById(triggerId);
    const list    = document.getElementById(listId);
    const native  = document.getElementById(nativeId);

    trigger.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = wrap.classList.contains('open');
        closeAllDropdowns();
        if (!isOpen) wrap.classList.add('open');
    });

    trigger.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); trigger.click(); }
        if (e.key === 'Escape') closeAllDropdowns();
    });

    // Static options (municipality only — barangay options added dynamically)
    list.querySelectorAll('.dropdown-option:not(.placeholder-opt)').forEach(opt => {
        opt.addEventListener('click', () => {
            const val = opt.dataset.value;
            native.value = val;
            setCustomSelect(prefix, val, val || '— Select —', !val);
            closeAllDropdowns();
            native.dispatchEvent(new Event('change'));
        });
    });
}

// Close on outside click
document.addEventListener('click', closeAllDropdowns);

// Init municipality dropdown
initCustomSelect('muni', 'municipalitySelect', 'muniList', 'muniTrigger', 'muniWrap');
// Init barangay dropdown
initCustomSelect('bgy',  'barangaySelect',     'bgyList',  'bgyTrigger',  'bgyWrap');

// ── Wire up native selects (still fire map updates) ──
document.getElementById('municipalitySelect').addEventListener('change', function () {
    updateBarangayOptions();
    updateMapFromDropdowns();
});
document.getElementById('barangaySelect').addEventListener('change', updateMapFromDropdowns);

// ── Initialize map on load ──
function initMapOnLoad() {
    const addrLower = currentAddress.toLowerCase();

    // Pre-fill dropdowns from saved address text
    if (addrLower) {
        for (const [muni, data] of Object.entries(misamis_oriental_data)) {
            if (addrLower.includes(muni.toLowerCase())) {
                document.getElementById('municipalitySelect').value = muni;
                setCustomSelect('muni', muni, muni, false);
                updateBarangayOptions();
                for (const bgy of (data.barangays || [])) {
                    if (addrLower.includes(bgy.toLowerCase())) {
                        document.getElementById('barangaySelect').value = bgy;
                        setCustomSelect('bgy', bgy, bgy, false);
                        break;
                    }
                }
                break;
            }
        }
    }

    if (savedLat && savedLng) {
        // User has a saved pin — show map at that exact spot, mark as already placed
        showMap(savedLat, savedLng, 17);
        pinManuallyPlaced = true;
        setPreview(savedLat, savedLng, currentAddress);
        // Open popup with saved address
        if (marker) {
            marker.getPopup()?.setContent(popupHtml(currentAddress || `${savedLat.toFixed(5)}, ${savedLng.toFixed(5)}`));
            marker.openPopup();
        }
    } else if (addrLower) {
        // No pin saved yet — geocode from address text to suggest a starting point
        updateMapFromDropdowns();
    } else {
        // No address at all — show overview of Misamis Oriental
        showMap(8.5696, 124.7220, 11);
    }
}

// ── Fix map size on tab switch ──
const _origSwitch = window.switchTab;
window.switchTab = function(name) {
    _origSwitch(name);
    if (name === 'address') {
        setTimeout(() => {
            if (map) {
                map.invalidateSize();
            } else {
                initMapOnLoad();
            }
        }, 180);
    }
};

// ── Auto-init map when page loads with address tab active ──
document.addEventListener('DOMContentLoaded', function() {
    const addressPanel = document.getElementById('panel-address');
    if (addressPanel && addressPanel.classList.contains('active')) {
        setTimeout(initMapOnLoad, 150);
    }
});
</script>
<?php endif; ?>

<!-- ── Logout Confirmation Modal ── -->
<style>
#logoutOverlay {
  display: none;
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: rgba(45,17,34,0.50);
  backdrop-filter: blur(5px);
  -webkit-backdrop-filter: blur(5px);
  align-items: center;
  justify-content: center;
}
#logoutOverlay.is-open { display: flex; }
#logoutModal {
  background: linear-gradient(160deg,#fff8fb 0%,#ffeef5 100%);
  border: 1.5px solid rgba(210,100,140,0.28);
  border-radius: 24px;
  padding: 36px 40px 32px;
  max-width: 330px;
  width: 90%;
  text-align: center;
  box-shadow: 0 24px 60px rgba(185,77,106,0.22), 0 6px 18px rgba(185,77,106,0.14);
  transform: translateY(24px);
  transition: transform 0.30s cubic-bezier(0.34,1.56,0.64,1);
}
#logoutOverlay.is-open #logoutModal { transform: translateY(0); }
.lm-icon  { font-size: 2.4rem; display: block; margin-bottom: 10px; }
.lm-title { font-family: 'Cormorant Garamond', serif; font-size: 1.7rem; color: var(--deep-rose); margin: 0 0 8px; line-height: 1.3; }
.lm-msg   { font-family: 'Jost', sans-serif; font-size: 0.84rem; color: var(--text-hint); margin: 0 0 26px; line-height: 1.6; }
.lm-actions { display: flex; gap: 12px; justify-content: center; }
.lm-stay {
  font-family: 'Jost', sans-serif; font-size: 0.82rem; font-weight: 600;
  letter-spacing: 0.06em; text-transform: uppercase;
  padding: 10px 24px; border-radius: 50px; cursor: pointer;
  background: rgba(255,255,255,0.85); border: 1.5px solid var(--petal);
  color: var(--text-hint); transition: all 0.2s;
}
.lm-stay:hover { background: var(--blush); color: var(--deep-rose); }
.lm-signout {
  font-family: 'Jost', sans-serif; font-size: 0.82rem; font-weight: 600;
  letter-spacing: 0.06em; text-transform: uppercase;
  padding: 10px 24px; border-radius: 50px; cursor: pointer; border: none;
  background: linear-gradient(135deg, var(--rose), var(--deep-rose));
  color: #fff; box-shadow: 0 4px 14px rgba(185,77,106,0.32); transition: all 0.2s;
}
.lm-signout:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(185,77,106,0.40); }
</style>

<div id="logoutOverlay">
  <div id="logoutModal" role="dialog" aria-modal="true" aria-labelledby="lmTitle">
    <span class="lm-icon">🌸</span>
    <p class="lm-title" id="lmTitle">Leaving so soon?</p>
    <p class="lm-msg">Are you sure you want to sign out of Precious Satin Bouquet?</p>
    <div class="lm-actions">
      <button type="button" class="lm-stay" id="logoutCancel">Stay</button>
      <form method="POST" action="logout.php" style="margin:0;">
        <input type="hidden" name="confirmed" value="1">
        <button type="submit" class="lm-signout">Sign Out</button>
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