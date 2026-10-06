<?php
session_start();
include "config.php";

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=my_messages.php");
    exit;
}

$uid      = (int)$_SESSION['user_id'];
$userRow  = $conn->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();
$userName = htmlspecialchars($userRow['full_name'] ?: $userRow['username']);

// ── User posts a reply ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_to'])) {
    $replyTo   = (int)$_POST['reply_to'];
    $replyBody = trim($_POST['reply_body'] ?? '');

    $check = $conn->prepare("SELECT id FROM contact_messages WHERE id=? AND user_id=?");
    $check->bind_param("ii", $replyTo, $uid);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0 && $replyBody !== '') {
        $stmt = $conn->prepare("INSERT INTO message_replies (message_id, sender, body) VALUES (?, 'user', ?)");
        $stmt->bind_param("is", $replyTo, $replyBody);
        $stmt->execute();
        $stmt->close();
    }
    $check->close();
    header("Location: my_messages.php?open=$replyTo");
    exit;
}

// ── Ensure is_read column exists ──────────────────────────────────────
$conn->query("ALTER TABLE message_replies ADD COLUMN IF NOT EXISTS is_read TINYINT(1) DEFAULT 0");

// ── Fetch this user's messages ─────────────────────────────────────────
$messages = $conn->prepare("SELECT * FROM contact_messages WHERE user_id=? ORDER BY created_at DESC");
$messages->bind_param("i", $uid);
$messages->execute();
$result = $messages->get_result();
$allMessages = $result->fetch_all(MYSQLI_ASSOC);

// ── Fetch unread admin reply counts per message (BEFORE marking read) ──
$unreadCounts = [];
$uq = $conn->prepare("
    SELECT mr.message_id, COUNT(*) as cnt
    FROM   message_replies mr
    JOIN   contact_messages cm ON cm.id = mr.message_id
    WHERE  cm.user_id = ?
      AND  mr.sender  = 'admin'
      AND  mr.is_read = 0
    GROUP BY mr.message_id
");
$uq->bind_param("i", $uid);
$uq->execute();
$uqResult = $uq->get_result();
while ($row = $uqResult->fetch_assoc()) {
    $unreadCounts[$row['message_id']] = (int)$row['cnt'];
}
$uq->close();

// ── Mark all unread admin replies as read ─────────────────────────────
$markRead = $conn->prepare("
    UPDATE message_replies mr
    JOIN   contact_messages cm ON cm.id = mr.message_id
    SET    mr.is_read = 1
    WHERE  cm.user_id = ?
      AND  mr.sender  = 'admin'
      AND  mr.is_read = 0
");
$markRead->bind_param("i", $uid);
$markRead->execute();
$markRead->close();

// ── Which thread is open? ─────────────────────────────────────────────
$openId = isset($_GET['open']) ? (int)$_GET['open'] : (count($allMessages) > 0 ? (int)$allMessages[0]['id'] : 0);

// ── Load replies for open thread ──────────────────────────────────────
$openReplies = [];
$openMsg = null;
foreach ($allMessages as $m) {
    if ((int)$m['id'] === $openId) { $openMsg = $m; break; }
}
if ($openMsg) {
    $rq = $conn->prepare("SELECT * FROM message_replies WHERE message_id=? ORDER BY created_at ASC");
    $rq->bind_param("i", $openId);
    $rq->execute();
    $openReplies = $rq->get_result()->fetch_all(MYSQLI_ASSOC);
    $rq->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Messages — Precious Satin Bouquet</title>
<link rel="icon" type="image/jpg" href="images/logo/SB.png">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root {
  --blush:      #f9d0dd;
  --petal:      #d4789a;
  --rose:       #c45070;
  --deep-rose:  #9e3358;
  --champagne:  #fdf3f6;
  --soft-white: #fffafc;
  --panel-bg:   #fff8fb;
  --text-dark:  #2a1018;
  --text-mid:   #5c2d40;
  --text-muted: #a07888;
  --border:     rgba(212,120,154,0.18);
  --shadow:     rgba(180,80,110,0.12);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html, body {
  height: 100%;
  overflow: hidden;
  font-family: 'Jost', sans-serif;
  background: var(--champagne);
  color: var(--text-dark);
}

/* Floating petals */
.petal-field { position: fixed; inset: 0; pointer-events: none; overflow: hidden; z-index: 0; }
.petal { position: absolute; width: 9px; height: 13px; border-radius: 60% 40% 60% 40% / 50% 50% 50% 50%; opacity: 0; animation: drift linear infinite; }
.petal:nth-child(1) { left: 8%;  background: #f2a7bf; animation-duration: 14s; animation-delay: 0s; }
.petal:nth-child(2) { left: 30%; background: #f9d0dd; animation-duration: 18s; animation-delay: 3s; }
.petal:nth-child(3) { left: 62%; background: #f2a7bf; animation-duration: 13s; animation-delay: 6s; }
.petal:nth-child(4) { left: 82%; background: #fce0ea; animation-duration: 16s; animation-delay: 1s; }
@keyframes drift {
  0%   { transform: translateY(-20px) rotate(0deg);   opacity: 0; }
  10%  { opacity: .45; }
  90%  { opacity: .3; }
  100% { transform: translateY(100vh) rotate(360deg); opacity: 0; }
}

/* ══════════════════════════════════════
   FULL-VIEWPORT SHELL
   ══════════════════════════════════════ */
.shell {
  position: relative; z-index: 1;
  display: flex;
  flex-direction: column;
  height: 100vh;
}

/* TOP BAR */
.topbar {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: 18px;
  height: 62px;
  padding: 0 28px;
  background: rgba(253,243,246,0.92);
  backdrop-filter: blur(16px);
  border-bottom: 1px solid var(--border);
  box-shadow: 0 2px 12px var(--shadow);
}
.topbar-back {
  display: inline-flex; align-items: center; gap: 6px;
  font-size: 11px; font-weight: 600; letter-spacing: 2px; text-transform: uppercase;
  color: var(--rose); text-decoration: none;
  transition: color 0.2s, transform 0.2s;
}
.topbar-back:hover { color: var(--deep-rose); transform: translateX(-2px); }
.topbar-divider { width: 1px; height: 22px; background: var(--border); }
.topbar-title {
  font-family: 'Cormorant Garamond', serif;
  font-size: 22px; font-weight: 400; color: var(--deep-rose);
}
.topbar-title em { font-style: italic; }
.topbar-spacer { flex: 1; }
.topbar-user {
  font-size: 12px; font-weight: 500; color: var(--text-mid);
  background: rgba(212,120,154,0.12); border-radius: 20px;
  padding: 5px 14px;
}

/* TWO-PANEL MAIN */
.main-area {
  flex: 1;
  display: grid;
  grid-template-columns: 280px 1fr;
  overflow: hidden;
  min-height: 0; /* critical for flex children to shrink */
}

/* ══════════════════════════════════════
   LEFT PANEL — thread list
   ══════════════════════════════════════ */
.sidebar {
  border-right: 1px solid var(--border);
  background: var(--panel-bg);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  min-height: 0;
}
.sidebar-header {
  flex-shrink: 0;
  padding: 16px 20px 12px;
  border-bottom: 1px solid var(--border);
  background: linear-gradient(180deg, #fff0f6 0%, var(--panel-bg) 100%);
}
.sidebar-label {
  font-size: 9px; letter-spacing: 3.5px; text-transform: uppercase;
  color: var(--text-muted); margin-bottom: 2px;
}
.sidebar-count {
  font-family: 'Cormorant Garamond', serif;
  font-size: 17px; color: var(--deep-rose); font-weight: 300;
}

.thread-list {
  flex: 1;
  overflow-y: auto;
  padding: 6px 0;
  scrollbar-width: thin;
  scrollbar-color: var(--blush) transparent;
  min-height: 0;
}
.thread-list::-webkit-scrollbar { width: 4px; }
.thread-list::-webkit-scrollbar-thumb { background: var(--blush); border-radius: 4px; }

.thread-item {
  display: block;
  padding: 14px 20px;
  cursor: pointer;
  text-decoration: none;
  border-left: 3px solid transparent;
  transition: background 0.18s, border-color 0.18s;
  position: relative;
  border-bottom: 1px solid rgba(212,120,154,0.08);
}
.thread-item:hover { background: rgba(249,208,221,0.22); }
.thread-item.active {
  background: linear-gradient(90deg, rgba(249,208,221,0.45), rgba(253,243,246,0.2));
  border-left-color: var(--rose);
}
.thread-item-date {
  font-size: 9.5px; letter-spacing: 1.5px; text-transform: uppercase;
  color: var(--text-muted); margin-bottom: 4px;
}
.thread-item-preview {
  font-size: 12.5px; line-height: 1.45; color: var(--text-dark);
  overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
  padding-right: 24px;
}
.thread-item.active .thread-item-preview { color: var(--deep-rose); font-weight: 500; }

.thread-unread {
  position: absolute; top: 14px; right: 14px;
  min-width: 20px; height: 20px; padding: 0 6px;
  border-radius: 10px;
  background: linear-gradient(135deg, var(--rose), var(--deep-rose));
  color: #fff; font-size: 9px; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  animation: badgePop 0.35s cubic-bezier(.34,1.56,.64,1);
}
@keyframes badgePop { from { transform: scale(0); opacity: 0; } to { transform: scale(1); opacity: 1; } }

.sidebar-empty {
  padding: 32px 20px; text-align: center;
  font-size: 13px; color: var(--text-muted); line-height: 1.7;
}
.sidebar-empty a { color: var(--rose); font-weight: 600; text-decoration: none; }

/* ══════════════════════════════════════
   RIGHT PANEL — conversation
   ══════════════════════════════════════ */
.convo-panel {
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: var(--soft-white);
  background-image: radial-gradient(ellipse at 80% 10%, rgba(249,208,221,0.18) 0%, transparent 60%);
  min-height: 0;
}

.convo-topbar {
  flex-shrink: 0;
  padding: 15px 28px 13px;
  border-bottom: 1px solid var(--border);
  background: linear-gradient(135deg, rgba(249,208,221,0.22), rgba(253,243,246,0.35));
}
.convo-date { font-size: 10px; letter-spacing: 2px; text-transform: uppercase; color: var(--rose); margin-bottom: 3px; }
.convo-subject {
  font-family: 'Cormorant Garamond', serif;
  font-size: 18px; color: var(--deep-rose); font-weight: 400;
}

/* Scrollable chat — the ONLY thing that scrolls */
.chat-scroll {
  flex: 1;
  overflow-y: auto;
  padding: 22px 28px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  scrollbar-width: thin;
  scrollbar-color: var(--blush) transparent;
  min-height: 0;
}
.chat-scroll::-webkit-scrollbar { width: 5px; }
.chat-scroll::-webkit-scrollbar-thumb { background: var(--blush); border-radius: 5px; }

.bubble-wrap { display: flex; flex-direction: column; }
.bubble-wrap.user-side { align-items: flex-end; }
.bubble-wrap.admin-side { align-items: flex-start; }

.chat-bubble {
  max-width: 60%;
  padding: 12px 16px;
  border-radius: 18px;
  font-size: 13.5px;
  line-height: 1.65;
  animation: bubbleIn 0.28s cubic-bezier(.22,1,.36,1) both;
}
@keyframes bubbleIn { from { opacity: 0; transform: translateY(6px) scale(0.98); } to { opacity: 1; transform: none; } }

.chat-bubble.user-bubble {
  background: linear-gradient(135deg, var(--rose), var(--deep-rose));
  color: #fff;
  border-bottom-right-radius: 4px;
}
.chat-bubble.admin-bubble {
  background: linear-gradient(135deg, #fff0f6, var(--champagne));
  border: 1px solid rgba(212,120,154,0.22);
  color: var(--text-dark);
  border-bottom-left-radius: 4px;
}
.admin-label {
  font-size: 9px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase;
  color: var(--rose); margin-bottom: 5px; display: block;
}
.bubble-meta {
  font-size: 9.5px; margin-top: 6px; opacity: 0.58; letter-spacing: 0.3px;
}
.user-bubble .bubble-meta { text-align: right; }

/* Reply pinned to bottom */
.reply-area {
  flex-shrink: 0;
  padding: 12px 28px 16px;
  border-top: 1px dashed rgba(212,120,154,0.28);
  background: rgba(253,243,246,0.65);
  backdrop-filter: blur(8px);
}
.reply-row { display: flex; gap: 10px; align-items: flex-end; }
.reply-textarea {
  flex: 1;
  font-family: 'Jost', sans-serif;
  font-size: 13px;
  color: var(--text-dark);
  background: #fff;
  border: 1.5px solid rgba(212,120,154,0.28);
  border-radius: 14px;
  padding: 11px 16px;
  outline: none;
  resize: none;
  height: 46px;
  max-height: 120px;
  transition: border-color 0.2s, box-shadow 0.2s;
  line-height: 1.5;
  overflow-y: hidden;
}
.reply-textarea:focus {
  border-color: var(--petal);
  box-shadow: 0 0 0 3px rgba(212,120,154,0.13);
  height: 70px;
  overflow-y: auto;
}
.btn-send {
  flex-shrink: 0;
  font-family: 'Jost', sans-serif;
  font-size: 10px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase;
  color: #fff;
  background: linear-gradient(135deg, var(--rose), var(--deep-rose));
  border: none; border-radius: 12px;
  padding: 0 22px; height: 46px;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(180,80,110,0.26);
  transition: transform 0.2s, box-shadow 0.2s, filter 0.2s;
  white-space: nowrap;
}
.btn-send:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(180,80,110,0.35); filter: brightness(1.06); }

/* Empty convo placeholder */
.convo-empty {
  flex: 1; display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  gap: 12px; color: var(--text-muted);
  font-size: 14px; text-align: center; padding: 40px;
}
.convo-empty-icon { font-size: 52px; opacity: 0.45; }

/* ══ MOBILE — stack panels ══ */
@media (max-width: 680px) {
  html, body { overflow: auto; height: auto; }
  .shell { height: auto; min-height: 100vh; }
  .main-area { grid-template-columns: 1fr; overflow: visible; }
  .sidebar { max-height: 220px; border-right: none; border-bottom: 1px solid var(--border); }
  .convo-panel { min-height: 60vh; }
  .chat-scroll { max-height: 50vh; }
  .reply-area { padding: 10px 14px 14px; }
  .topbar { padding: 0 14px; height: 56px; }
  .chat-bubble { max-width: 86%; }
  .convo-topbar { padding: 12px 16px; }
  .chat-scroll { padding: 16px; }
}
</style>
<?php include __DIR__ . '/pwa_head.php'; ?>
</head>
<body>

<div class="petal-field">
  <div class="petal"></div><div class="petal"></div>
  <div class="petal"></div><div class="petal"></div>
</div>

<div class="shell">

  <!-- TOP BAR -->
  <div class="topbar">
    <a href="index.php" class="topbar-back">← Shop</a>
    <div class="topbar-divider"></div>
    <div class="topbar-title">My <em>Messages</em> 💌</div>
    <div class="topbar-spacer"></div>
    <div class="topbar-user">👤 <?= $userName ?></div>
  </div>

  <!-- TWO-PANEL MAIN -->
  <div class="main-area">

    <!-- LEFT: Thread list -->
    <aside class="sidebar">
      <div class="sidebar-header">
        <div class="sidebar-label">Conversations</div>
        <div class="sidebar-count">
          <?= count($allMessages) ?> thread<?= count($allMessages) !== 1 ? 's' : '' ?>
        </div>
      </div>

      <div class="thread-list">
        <?php if (empty($allMessages)): ?>
          <div class="sidebar-empty">
            No messages yet.<br>
            <a href="contact.php">Send one? 🌸</a>
          </div>
        <?php else: ?>
          <?php foreach ($allMessages as $m):
            $mid      = (int)$m['id'];
            $isActive = ($mid === $openId);
            $unread   = $unreadCounts[$mid] ?? 0;
          ?>
          <a href="?open=<?= $mid ?>" class="thread-item <?= $isActive ? 'active' : '' ?>">
            <div class="thread-item-date"><?= date('M j, Y', strtotime($m['created_at'])) ?></div>
            <div class="thread-item-preview">
              <?= htmlspecialchars(mb_strimwidth($m['message'], 0, 90, '…')) ?>
            </div>
            <?php if ($unread > 0): ?>
              <span class="thread-unread"><?= $unread ?></span>
            <?php endif; ?>
          </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </aside>

    <!-- RIGHT: Conversation -->
    <div class="convo-panel">
      <?php if ($openMsg): ?>

        <div class="convo-topbar">
          <div class="convo-date">📅 <?= date('F j, Y · g:i A', strtotime($openMsg['created_at'])) ?></div>
          <div class="convo-subject">Re: <?= htmlspecialchars(mb_strimwidth($openMsg['message'], 0, 80, '…')) ?></div>
        </div>

        <div class="chat-scroll" id="chatScroll">

          <!-- Original message -->
          <div class="bubble-wrap user-side">
            <div class="chat-bubble user-bubble">
              <?= nl2br(htmlspecialchars($openMsg['message'])) ?>
              <div class="bubble-meta">
                👤 You &nbsp;·&nbsp; <?= date('M j · g:i A', strtotime($openMsg['created_at'])) ?>
              </div>
            </div>
          </div>

          <!-- Replies -->
          <?php foreach ($openReplies as $r): ?>
          <div class="bubble-wrap <?= $r['sender'] === 'admin' ? 'admin-side' : 'user-side' ?>">
            <div class="chat-bubble <?= $r['sender'] === 'admin' ? 'admin-bubble' : 'user-bubble' ?>">
              <?php if ($r['sender'] === 'admin'): ?>
                <span class="admin-label">🌸 Precious Satin Bouquet</span>
              <?php endif; ?>
              <?= nl2br(htmlspecialchars($r['body'])) ?>
              <div class="bubble-meta">
                <?= $r['sender'] === 'admin' ? '🌸 Admin' : '👤 You' ?>
                &nbsp;·&nbsp; <?= date('M j · g:i A', strtotime($r['created_at'])) ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>

        </div><!-- end .chat-scroll -->

        <!-- Reply input — always pinned to bottom -->
        <div class="reply-area">
          <form method="POST" action="">
            <input type="hidden" name="reply_to" value="<?= $openId ?>">
            <div class="reply-row">
              <textarea
                name="reply_body"
                id="replyBox"
                class="reply-textarea"
                placeholder="Write a follow-up… 🌷"
                required></textarea>
              <button type="submit" class="btn-send">Send ✈</button>
            </div>
          </form>
        </div>

      <?php else: ?>
        <div class="convo-empty">
          <div class="convo-empty-icon">💌</div>
          <div>Select a conversation<br>or <a href="contact.php" style="color:var(--rose);font-weight:600;">send your first message!</a></div>
        </div>
      <?php endif; ?>
    </div>

  </div><!-- end .main-area -->

</div><!-- end .shell -->

<script>
// Auto-scroll chat to bottom on load
(function(){
  var cs = document.getElementById('chatScroll');
  if (cs) cs.scrollTop = cs.scrollHeight;
})();

// Auto-grow textarea as user types
(function(){
  var box = document.getElementById('replyBox');
  if (!box) return;
  box.addEventListener('input', function(){
    this.style.height = '46px';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
  });
})();
</script>

<?php include 'footer.php'; ?>
</body>
</html>