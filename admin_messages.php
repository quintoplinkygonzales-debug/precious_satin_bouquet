<?php
session_start();
include "config.php";

if (!isset($_SESSION['user_id'])) die("Login first");

$uid  = $_SESSION['user_id'];
$user = $conn->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();
if ($user['role'] != 'admin') die("Access denied");

$adminName = $user['username'] ?? 'Admin';

// ── Actions ────────────────────────────────────────────────────────────
if (isset($_GET['read'])) {
    $rid = (int)$_GET['read'];
    $conn->query("UPDATE contact_messages SET is_read=1 WHERE id=$rid");
    header("Location: admin_messages.php?selected=$rid");
    exit;
}
if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $conn->query("DELETE FROM message_replies WHERE message_id=$did");
    $conn->query("DELETE FROM contact_messages WHERE id=$did");
    header("Location: admin_messages.php");
    exit;
}
if (isset($_GET['readall'])) {
    $conn->query("UPDATE contact_messages SET is_read=1");
    header("Location: admin_messages.php");
    exit;
}

// ── Admin reply POST ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_to'])) {
    $replyTo   = (int)$_POST['reply_to'];
    $replyBody = trim($_POST['reply_body'] ?? '');
    if ($replyTo > 0 && $replyBody !== '') {
        $stmt = $conn->prepare("INSERT INTO message_replies (message_id, sender, body) VALUES (?, 'admin', ?)");
        $stmt->bind_param("is", $replyTo, $replyBody);
        $stmt->execute();
        $stmt->close();
        $conn->query("UPDATE contact_messages SET is_read=1 WHERE id=$replyTo");
    }
    header("Location: admin_messages.php?selected=$replyTo");
    exit;
}

// ── Fetch messages ─────────────────────────────────────────────────────
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$selected = (int)($_GET['selected'] ?? 0);

$where = match($filter) {
    'unread' => "WHERE is_read=0",
    'read'   => "WHERE is_read=1",
    default  => ""
};
if ($search !== '') {
    $s     = $conn->real_escape_string($search);
    $cond  = "(name LIKE '%$s%' OR email LIKE '%$s%' OR message LIKE '%$s%')";
    $where = $where === '' ? "WHERE $cond" : "$where AND $cond";
}

$messages     = $conn->query("SELECT * FROM contact_messages $where ORDER BY created_at DESC");
$unread_count = $conn->query("SELECT COUNT(*) AS c FROM contact_messages WHERE is_read=0")->fetch_assoc()['c'];
$total_count  = $conn->query("SELECT COUNT(*) AS c FROM contact_messages")->fetch_assoc()['c'];

// Load selected message detail
$selMsg     = null;
$selReplies = [];
if ($selected > 0) {
    $selMsg = $conn->query("SELECT * FROM contact_messages WHERE id=$selected")->fetch_assoc();
    if ($selMsg) {
        $rq = $conn->query("SELECT * FROM message_replies WHERE message_id=$selected ORDER BY created_at ASC");
        while ($r = $rq->fetch_assoc()) $selReplies[] = $r;
        // Auto-mark as read when opened
        $conn->query("UPDATE contact_messages SET is_read=1 WHERE id=$selected");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Messages — Precious Satin Bouquet Admin</title>
<link rel="icon" type="image/png" href="images/logo/">
<link rel="stylesheet" href="style.css">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=Jost:wght@300;400;500;600;700&family=Great+Vibes&display=swap" rel="stylesheet">
<style>
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
    --border:    rgba(255,170,130,0.22);
    --shadow-sm: 0 2px 12px rgba(208,90,40,0.08);
    --shadow-md: 0 8px 32px rgba(208,90,40,0.13);
    --radius:    14px;
    --green:     #4caf88;
    --red:       #e05c6a;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    background: var(--cream);
    font-family: 'Jost', sans-serif;
    font-size: 14px;
    min-height: 100vh;
    color: var(--ink);
    overflow-x: hidden;
}
body::before {
    content: ''; position: fixed; inset: 0; pointer-events: none; z-index: 0;
    background:
        radial-gradient(ellipse 60% 40% at 90% 10%, rgba(255,200,160,0.35) 0%, transparent 70%),
        radial-gradient(ellipse 50% 60% at 5% 80%,  rgba(252,232,240,0.40) 0%, transparent 65%);
}

/* ── NAVBAR ── */
.adm-nav {
    position: sticky; top: 0; z-index: 300; height: 60px;
    display: flex; align-items: center; padding: 0 32px;
    background: rgba(255,248,242,0.92);
    backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
    border-bottom: 1px solid var(--border);
}
.adm-nav-left  { flex: 1; }
.adm-nav-center { flex: 1; display: flex; justify-content: center; }
.adm-nav-right { flex: 1; display: flex; align-items: center; justify-content: flex-end; gap: 8px; }
.adm-nav::after {
    content: ''; position: absolute; bottom: -1px; left: 50%; transform: translateX(-50%);
    width: 100px; height: 2px;
    background: linear-gradient(90deg, transparent, var(--rose), transparent);
}
.adm-nav-brand { font-family: 'Great Vibes', cursive; font-size: 26px; color: var(--deep-rose); text-decoration: none; letter-spacing: 0.5px; line-height: 1; }
.adm-nav-tagline { font-size: 9px; font-weight: 500; letter-spacing: 3px; text-transform: uppercase; color: var(--muted); display: block; text-align: center; margin-top: -2px; }
.adm-icon {
    width: 34px; height: 34px; border-radius: 50%; background: transparent;
    border: 1.5px solid var(--border); display: flex; align-items: center; justify-content: center;
    color: var(--muted); text-decoration: none;
    transition: all 0.2s; cursor: pointer; position: relative;
}
.adm-icon:hover { background: var(--rose); border-color: var(--rose); color: #fff; }
.adm-icon.active { background: var(--deep-rose); border-color: var(--deep-rose); color: #fff; }

/* ── SETTINGS DROPDOWN ── */
.adm-settings-wrap { position: relative; }
.adm-settings-dropdown {
    display: none; position: absolute; top: calc(100% + 12px); right: 0;
    width: 250px; background: var(--white); border: 1px solid var(--border);
    border-radius: 16px; box-shadow: var(--shadow-md); z-index: 400;
    overflow: hidden; animation: dropIn 0.2s ease;
}
.adm-settings-dropdown.open { display: block; }
@keyframes dropIn { from { opacity:0; transform:translateY(-8px); } to { opacity:1; transform:translateY(0); } }
.adm-settings-head { background:var(--blush); padding:10px 16px; font-size:11px; font-weight:700; color:var(--deep-rose); letter-spacing:2px; text-transform:uppercase; border-bottom:1px solid var(--border); }
.adm-settings-item { display:flex; align-items:center; gap:12px; padding:10px 16px; text-decoration:none; color:var(--ink); font-size:13.5px; font-weight:500; transition:background 0.15s; border-bottom:1px solid rgba(247,197,216,0.2); }
.adm-settings-item:last-child { border-bottom:none; }
.adm-settings-item:hover, .adm-settings-item.active-page { background:var(--blush); color:var(--deep-rose); }
.adm-settings-item-icon { width:30px; height:30px; border-radius:8px; background:var(--blush); display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; }
.adm-settings-item-sub { font-size:11px; color:var(--muted); font-weight:400; }
.adm-settings-item .item-badge { margin-left:auto; background:var(--rose); color:#fff; font-size:11px; font-weight:700; padding:2px 6px; border-radius:8px; }

/* ── LAYOUT ── */
.adm-layout {
    position: relative; z-index: 1;
    display: flex;
    height: calc(100vh - 60px);
    overflow: hidden;
}

/* ── LEFT PANEL ── */
.msg-sidebar {
    width: 340px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    border-right: 1px solid var(--border);
    background: var(--white);
    overflow: hidden;
}

.msg-sidebar-header {
    padding: 16px 18px 12px;
    border-bottom: 1px solid var(--border);
    background: var(--blush);
}
.msg-sidebar-title {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 10px;
}
.msg-sidebar-title h2 {
    font-family: 'Cormorant Garamond', serif;
    font-size: 19px; font-weight: 600; color: var(--deep-rose);
}
.msg-sidebar-title .count-badge {
    background: var(--rose); color: #fff;
    font-size: 13px; font-weight: 700;
    padding: 2px 8px; border-radius: 10px;
}

.sidebar-stats {
    display: flex; gap: 8px; margin-bottom: 10px;
}
.sidebar-stat {
    flex: 1; background: var(--white); border: 1px solid var(--border);
    border-radius: 8px; padding: 6px 8px; text-align: center;
}
.sidebar-stat-val { font-size: 17px; font-weight: 700; color: var(--deep-rose); line-height: 1; }
.sidebar-stat-lbl { font-size: 13px; color: var(--muted); letter-spacing: 1px; text-transform: uppercase; margin-top: 2px; }
.sidebar-stat.unread .sidebar-stat-val { color: var(--rose); }

.sidebar-toolbar {
    display: flex; gap: 6px; align-items: center;
    padding: 10px 18px;
    border-bottom: 1px solid var(--border);
    background: var(--white);
    flex-shrink: 0;
}
.filter-tab {
    font-size: 14px; font-weight: 600; letter-spacing: 0.5px;
    padding: 5px 11px; border-radius: 8px;
    border: 1.5px solid var(--border); background: transparent;
    color: var(--muted); cursor: pointer; text-decoration: none;
    transition: all 0.18s;
}
.filter-tab:hover, .filter-tab.active { background: var(--rose); color: #fff; border-color: var(--rose); }
.filter-tab.readall-btn { color: var(--green); border-color: rgba(76,175,136,0.4); margin-left: auto; }
.filter-tab.readall-btn:hover { background: var(--green); color: #fff; border-color: var(--green); }

.sidebar-search {
    padding: 8px 18px;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
}
.sidebar-search form { display: flex; }
.sidebar-search input {
    width: 100%; font-family: 'Jost', sans-serif; font-size: 14px;
    background: var(--cream); border: 1.5px solid var(--border);
    border-radius: 8px; padding: 7px 12px; outline: none; color: var(--ink);
    transition: border-color 0.2s;
}
.sidebar-search input:focus { border-color: var(--petal); }

.msg-list {
    flex: 1; overflow-y: auto; padding: 8px;
}
.msg-list::-webkit-scrollbar { width: 4px; }
.msg-list::-webkit-scrollbar-track { background: transparent; }
.msg-list::-webkit-scrollbar-thumb { background: var(--petal); border-radius: 4px; }

.msg-item {
    padding: 11px 13px;
    border-radius: 10px;
    cursor: pointer;
    transition: background 0.15s;
    margin-bottom: 4px;
    border: 1.5px solid transparent;
    position: relative;
}
.msg-item:hover { background: var(--blush); }
.msg-item.selected { background: var(--blush); border-color: var(--petal); }
.msg-item.unread { border-left: 3px solid var(--rose); }

.msg-item-top {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 3px;
}
.msg-item-name {
    font-size: 14px; font-weight: 600; color: var(--ink);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    max-width: 160px;
}
.msg-item-time { font-size: 13px; color: var(--muted); flex-shrink: 0; }
.msg-item-email { font-size: 14px; color: var(--muted); margin-bottom: 3px; }
.msg-item-preview {
    font-size: 13.5px; color: var(--muted);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    line-height: 1.4;
}
.msg-item-badges { display: flex; gap: 5px; margin-top: 4px; align-items: center; }
.badge-unread { background: var(--rose); color: #fff; font-size: 13px; font-weight: 700; padding: 1px 6px; border-radius: 6px; letter-spacing: 0.5px; }
.badge-replies { background: var(--blush); color: var(--rose); font-size: 13px; font-weight: 600; padding: 1px 6px; border-radius: 6px; border: 1px solid var(--petal); }

/* ── RIGHT PANEL ── */
.msg-detail {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: var(--cream);
}

/* Empty state */
.msg-detail-empty {
    flex: 1;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    color: var(--muted);
    gap: 12px;
}
.msg-detail-empty .empty-icon { font-size: 50px; opacity: 0.3; }
.msg-detail-empty p { font-size: 14px; }

/* Detail header */
.detail-header {
    padding: 16px 24px;
    border-bottom: 1px solid var(--border);
    background: var(--white);
    display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;
    flex-shrink: 0;
}
.detail-header-left {}
.detail-sender-name {
    font-family: 'Cormorant Garamond', serif;
    font-size: 21px; font-weight: 600; color: var(--deep-rose);
    margin-bottom: 3px;
}
.detail-meta { display: flex; gap: 14px; flex-wrap: wrap; }
.detail-meta span { font-size: 13.5px; color: var(--muted); display: flex; align-items: center; gap: 4px; }
.detail-meta a { color: var(--rose); text-decoration: none; font-weight: 500; }
.detail-meta a:hover { color: var(--deep-rose); }
.detail-header-actions { display: flex; gap: 6px; flex-shrink: 0; }
.btn-sm {
    font-size: 14px; font-weight: 600; letter-spacing: 0.5px;
    padding: 6px 12px; border-radius: 7px; cursor: pointer;
    text-decoration: none; border: 1.5px solid; transition: all 0.18s;
    display: inline-flex; align-items: center; gap: 4px;
    white-space: nowrap;
}
.btn-delete { color: var(--red); border-color: rgba(224,92,106,0.35); background: transparent; }
.btn-delete:hover { background: var(--red); color: #fff; border-color: var(--red); }
.btn-read { color: var(--green); border-color: rgba(76,175,136,0.4); background: transparent; }
.btn-read:hover { background: var(--green); color: #fff; border-color: var(--green); }

/* Original message */
.detail-original {
    padding: 16px 24px;
    border-bottom: 1px solid var(--border);
    background: var(--white);
    flex-shrink: 0;
}
.detail-original-label {
    font-size: 13px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase;
    color: var(--muted); margin-bottom: 8px;
}
.detail-original-body {
    font-size: 14px; color: var(--ink); line-height: 1.7;
    background: var(--cream); border: 1px solid var(--border);
    border-radius: 10px; padding: 12px 16px;
}

/* Thread */
.detail-thread {
    flex: 1; overflow-y: auto;
    padding: 16px 24px;
    display: flex; flex-direction: column; gap: 10px;
}
.detail-thread::-webkit-scrollbar { width: 4px; }
.detail-thread::-webkit-scrollbar-thumb { background: var(--petal); border-radius: 4px; }

.reply-bubble {
    max-width: 72%; padding: 10px 14px; border-radius: 14px;
    font-size: 14px; line-height: 1.65;
}
.reply-bubble.admin {
    align-self: flex-end;
    background: linear-gradient(135deg, var(--deep-rose), var(--rose));
    color: #fff; border-bottom-right-radius: 4px;
}
.reply-bubble.user {
    align-self: flex-start;
    background: var(--white); border: 1px solid var(--border);
    color: var(--ink); border-bottom-left-radius: 4px;
}
.reply-bubble-meta {
    font-size: 13px; margin-top: 5px; opacity: 0.65; letter-spacing: 0.3px;
}
.reply-bubble.admin .reply-bubble-meta { text-align: right; }
.no-replies { font-size: 14px; color: var(--muted); font-style: italic; text-align: center; padding: 24px 0; }

/* Reply form */
.detail-reply {
    padding: 12px 24px 16px;
    border-top: 1px solid var(--border);
    background: var(--white);
    flex-shrink: 0;
}
.reply-form { display: flex; gap: 10px; align-items: flex-end; }
.reply-form textarea {
    flex: 1; font-family: 'Jost', sans-serif; font-size: 13.5px; color: var(--ink);
    background: var(--cream); border: 1.5px solid var(--border); border-radius: 10px;
    padding: 9px 13px; outline: none; resize: none; height: 64px;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.reply-form textarea:focus { border-color: var(--petal); box-shadow: 0 0 0 3px rgba(247,197,216,0.18); }
.reply-form button {
    font-family: 'Jost', sans-serif; font-size: 14px; font-weight: 600;
    letter-spacing: 1px; text-transform: uppercase;
    background: linear-gradient(135deg, var(--rose), var(--deep-rose));
    color: #fff; border: none; border-radius: 10px; padding: 0 20px; height: 64px;
    cursor: pointer; white-space: nowrap;
    transition: filter 0.2s, transform 0.18s;
    box-shadow: 0 4px 14px rgba(208,90,40,0.25);
}
.reply-form button:hover { filter: brightness(1.08); transform: translateY(-1px); }

@keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }

/* Notification badges — added for sidebar alerts */
.nav-badge{display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 5px;border-radius:99px;font-size:10px;font-weight:800;line-height:1;background:#e53e3e;color:#fff;margin-left:auto;flex-shrink:0;animation:badgePop 0.4s cubic-bezier(.34,1.56,.64,1);}
@keyframes badgePop{from{transform:scale(0);opacity:0;}to{transform:scale(1);opacity:1;}}
.adm-icon .pending-dot{position:absolute;top:3px;right:3px;width:9px;height:9px;border-radius:50%;background:#e53e3e;border:2px solid var(--cream);animation:pulseDot 1.6s ease-in-out infinite;}
@keyframes pulseDot{0%,100%{opacity:1;transform:scale(1);}50%{opacity:.55;transform:scale(1.3);}}
</style>
<?php include __DIR__ . '/pwa_head.php'; ?>
</head>
<body>

<!-- ── NAVBAR ── -->
<?php $activePage = 'messages'; include 'admin_navbar.php'; ?>

<!-- ── TWO-PANEL LAYOUT ── -->
<div class="adm-layout">

    <!-- LEFT: Message list -->
    <aside class="msg-sidebar">

        <div class="msg-sidebar-header">
            <div class="msg-sidebar-title">
                <h2>💌 Messages</h2>
                <?php if ($unread_count > 0): ?>
                <span class="count-badge"><?= $unread_count ?> unread</span>
                <?php endif; ?>
            </div>
            <div class="sidebar-stats">
                <div class="sidebar-stat">
                    <div class="sidebar-stat-val"><?= $total_count ?></div>
                    <div class="sidebar-stat-lbl">Total</div>
                </div>
                <div class="sidebar-stat unread">
                    <div class="sidebar-stat-val"><?= $unread_count ?></div>
                    <div class="sidebar-stat-lbl">Unread</div>
                </div>
                <div class="sidebar-stat">
                    <div class="sidebar-stat-val"><?= $total_count - $unread_count ?></div>
                    <div class="sidebar-stat-lbl">Read</div>
                </div>
            </div>
        </div>

        <div class="sidebar-toolbar">
            <a href="?filter=all<?= $selected ? '&selected='.$selected : '' ?>"    class="filter-tab <?= $filter==='all'    ? 'active' : '' ?>">All</a>
            <a href="?filter=unread<?= $selected ? '&selected='.$selected : '' ?>" class="filter-tab <?= $filter==='unread' ? 'active' : '' ?>">Unread</a>
            <a href="?filter=read<?= $selected ? '&selected='.$selected : '' ?>"   class="filter-tab <?= $filter==='read'   ? 'active' : '' ?>">Read</a>
            <?php if ($unread_count > 0): ?>
            <a href="?readall" class="filter-tab readall-btn">✓ All Read</a>
            <?php endif; ?>
        </div>

        <div class="sidebar-search">
            <form method="GET">
                <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                <?php if ($selected): ?><input type="hidden" name="selected" value="<?= $selected ?>"><?php endif; ?>
                <input type="text" name="search" placeholder="Search messages…" value="<?= htmlspecialchars($search) ?>">
            </form>
        </div>

        <div class="msg-list">
        <?php
        // Re-fetch for list display (already filtered above)
        $listResult = $conn->query("SELECT cm.*, (SELECT COUNT(*) FROM message_replies WHERE message_id=cm.id) AS reply_count FROM contact_messages cm $where ORDER BY cm.created_at DESC");
        if ($listResult && $listResult->num_rows > 0):
            while ($msg = $listResult->fetch_assoc()):
                $isSelected = ($msg['id'] == $selected);
                $isUnread   = !$msg['is_read'];
                $preview    = mb_substr($msg['message'], 0, 60);
                $timeAgo    = date('M j', strtotime($msg['created_at']));
        ?>
        <div class="msg-item <?= $isSelected ? 'selected' : '' ?> <?= $isUnread ? 'unread' : '' ?>"
             onclick="location.href='?filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>&selected=<?= $msg['id'] ?>'">
            <div class="msg-item-top">
                <span class="msg-item-name"><?= htmlspecialchars($msg['name']) ?></span>
                <span class="msg-item-time"><?= $timeAgo ?></span>
            </div>
            <div class="msg-item-email"><?= htmlspecialchars($msg['email']) ?></div>
            <div class="msg-item-preview"><?= htmlspecialchars($preview) ?>…</div>
            <div class="msg-item-badges">
                <?php if ($isUnread): ?><span class="badge-unread">NEW</span><?php endif; ?>
                <?php if ($msg['reply_count'] > 0): ?><span class="badge-replies">💬 <?= $msg['reply_count'] ?></span><?php endif; ?>
            </div>
        </div>
        <?php endwhile;
        else: ?>
            <div style="padding:32px 16px;text-align:center;color:var(--muted);font-size:13px;">
                <?= $search ? 'No messages match your search.' : 'No messages yet.' ?>
            </div>
        <?php endif; ?>
        </div>

    </aside>

    <!-- RIGHT: Message detail -->
    <section class="msg-detail">
    <?php if ($selMsg): ?>

        <!-- Header -->
        <div class="detail-header">
            <div class="detail-header-left">
                <div class="detail-sender-name"><?= htmlspecialchars($selMsg['name']) ?></div>
                <div class="detail-meta">
                    <span>✉️ <a href="mailto:<?= htmlspecialchars($selMsg['email']) ?>"><?= htmlspecialchars($selMsg['email']) ?></a></span>
                    <?php if (!empty($selMsg['phone'])): ?>
                    <span>📞 <?= htmlspecialchars($selMsg['phone']) ?></span>
                    <?php endif; ?>
                    <span>🕐 <?= date('M j, Y · g:i A', strtotime($selMsg['created_at'])) ?></span>
                    <span><?= count($selReplies) > 0 ? '💬 '.count($selReplies).' repl'.(count($selReplies)===1?'y':'ies') : '💬 No replies yet' ?></span>
                </div>
            </div>
            <div class="detail-header-actions">
                <?php if (!$selMsg['is_read']): ?>
                <a href="?read=<?= $selected ?>&filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>&selected=<?= $selected ?>" class="btn-sm btn-read">✓ Mark Read</a>
                <?php endif; ?>
                <a href="?delete=<?= $selected ?>&filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>"
                   class="btn-sm btn-delete"
                   onclick="return confirm('Delete this message and all replies?')">🗑 Delete</a>
            </div>
        </div>

        <!-- Original message -->
        <div class="detail-original">
            <div class="detail-original-label">Original Message</div>
            <div class="detail-original-body"><?= nl2br(htmlspecialchars($selMsg['message'])) ?></div>
        </div>

        <!-- Reply thread -->
        <div class="detail-thread" id="threadScroll">
            <?php if (!empty($selReplies)): ?>
                <?php foreach ($selReplies as $r): ?>
                <div class="reply-bubble <?= $r['sender'] ?>">
                    <?= nl2br(htmlspecialchars($r['body'])) ?>
                    <div class="reply-bubble-meta">
                        <?= $r['sender'] === 'admin' ? '🌸 Admin' : '👤 '.$selMsg['name'] ?> &nbsp;·&nbsp;
                        <?= date('M j · g:i A', strtotime($r['created_at'])) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="no-replies">No replies yet — start the conversation below.</p>
            <?php endif; ?>
        </div>

        <!-- Reply form -->
        <div class="detail-reply">
            <form method="POST" action="" class="reply-form">
                <input type="hidden" name="reply_to" value="<?= $selected ?>">
                <input type="hidden" name="filter"   value="<?= htmlspecialchars($filter) ?>">
                <input type="hidden" name="search"   value="<?= htmlspecialchars($search) ?>">
                <textarea name="reply_body" placeholder="Reply to <?= htmlspecialchars($selMsg['name']) ?>…" required></textarea>
                <button type="submit">Send ✈</button>
            </form>
        </div>

    <?php else: ?>
        <div class="msg-detail-empty">
            <div class="empty-icon">💌</div>
            <p>Select a message on the left to view and reply.</p>
        </div>
    <?php endif; ?>
    </section>

</div>

<script>
// Settings dropdown });

// Auto-scroll thread to bottom
const thread = document.getElementById('threadScroll');
if (thread) thread.scrollTop = thread.scrollHeight;
</script>
</body>
</html>