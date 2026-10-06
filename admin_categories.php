<?php
session_start();
include "config.php";

if (!isset($_SESSION['user_id'])) die("Login first");

$uid = $_SESSION['user_id'];
$user = $conn->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();
if ($user['role'] != 'admin') die("Access denied");

// ── ADD CATEGORY ─────────────────────────────────────────────────────────────
if (isset($_POST['add_category'])) {
    $name = trim($_POST['cat_name']);
    if ($name !== '') {
        $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
        $stmt->bind_param("s", $name);
        $stmt->execute();
    }
    header("Location: admin_categories.php"); exit();
}

// ── RENAME CATEGORY ──────────────────────────────────────────────────────────
if (isset($_POST['rename_category'])) {
    $cat_id  = intval($_POST['cat_id']);
    $newname = trim($_POST['cat_name']);
    if ($cat_id > 0 && $newname !== '') {
        $stmt = $conn->prepare("UPDATE categories SET name=? WHERE id=?");
        $stmt->bind_param("si", $newname, $cat_id);
        $stmt->execute();
    }
    header("Location: admin_categories.php"); exit();
}

// ── DELETE CATEGORY ──────────────────────────────────────────────────────────
if (isset($_POST['delete_category'])) {
    $cat_id = intval($_POST['cat_id']);
    if ($cat_id > 0) {
        $conn->query("UPDATE products SET category_id=NULL WHERE category_id=$cat_id");
        $conn->query("DELETE FROM categories WHERE id=$cat_id");
    }
    header("Location: admin_categories.php"); exit();
}

// ── LOAD CATEGORIES ──────────────────────────────────────────────────────────
$categories = [];
$result = $conn->query(
    "SELECT c.id, c.name, COUNT(p.id) AS product_count
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id
     GROUP BY c.id, c.name
     ORDER BY c.name ASC"
);
if ($result) while ($row = $result->fetch_assoc()) $categories[] = $row;
$totalCategories = count($categories);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Categories — Precious Satin Bouquet</title>
<link rel="icon" type="image/png" href="images/logo/SB.png">
<link rel="shortcut icon" type="image/png" href="images/logo/SB.png">
<link rel="stylesheet" href="style.css">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Jost:wght@300;400;500;600;700&family=Great+Vibes&display=swap" rel="stylesheet">
<style>
:root {
    --blush:     #fff3ed;
    --petal:     #ffc9a0;
    --rose:      #f08050;
    --deep-rose: #d05a28;
    --cream:     #fff8f4;
    --white:     #ffffff;
    --ink:       #4a2c1e;
    --muted:     #9a6b5a;
    --border:    rgba(220,120,70,0.22);
    --shadow-sm: 0 2px 12px rgba(208,90,40,0.10);
    --shadow-md: 0 8px 32px rgba(208,90,40,0.14);
    --shadow-lg: 0 20px 60px rgba(208,90,40,0.18);
    --radius:    18px;
    --radius-sm: 10px;
    --danger:    #e05c5c;
    --danger-bg: #fdeaea;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: var(--cream); font-family: 'Jost', sans-serif; min-height: 100vh; color: var(--ink); overflow-x: hidden; }
body::before {
    content: ''; position: fixed; inset: 0;
    background:
        radial-gradient(ellipse 60% 40% at 90% 10%, rgba(255,200,160,0.4) 0%, transparent 70%),
        radial-gradient(ellipse 50% 60% at 5% 80%, rgba(255,215,185,0.5) 0%, transparent 65%);
    pointer-events: none; z-index: 0;
}

/* ── NAV ── */
.adm-nav { position: sticky; top: 0; z-index: 300; height: 68px; display: flex; align-items: center; padding: 0 40px; background: rgba(255,248,242,0.90); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border-bottom: 1px solid var(--border); }
.adm-nav::after { content: ''; position: absolute; bottom: -1px; left: 50%; transform: translateX(-50%); width: 120px; height: 2px; background: linear-gradient(90deg, transparent, #f08050, transparent); border-radius: 2px; }
.adm-nav-left  { flex: 1; }
.adm-nav-center { flex: 1; display: flex; justify-content: center; }
.adm-nav-right { flex: 1; display: flex; align-items: center; justify-content: flex-end; gap: 8px; }
.adm-nav-brand { font-family: 'Great Vibes', cursive; font-size: 28px; color: var(--deep-rose); text-decoration: none; letter-spacing: 0.5px; line-height: 1; }
.adm-nav-tagline { font-family: 'Jost', sans-serif; font-size: 9px; font-weight: 500; letter-spacing: 3px; text-transform: uppercase; color: var(--muted); display: block; text-align: center; margin-top: -2px; }
.adm-icon { width: 38px; height: 38px; border-radius: 50%; background: transparent; border: 1.5px solid var(--border); display: flex; align-items: center; justify-content: center; color: var(--muted); text-decoration: none; transition: all 0.25s cubic-bezier(.34,1.56,.64,1); cursor: pointer; }
.adm-icon:hover { background: var(--rose); border-color: var(--rose); color: #fff; box-shadow: 0 4px 16px rgba(208,90,40,0.30); transform: translateY(-2px); }
.adm-icon.active { background: var(--deep-rose); border-color: var(--deep-rose); color: #fff; }
.adm-settings-wrap { position: relative; }
.adm-settings-dropdown { display: none; position: absolute; top: calc(100% + 14px); right: 0; width: 270px; background: var(--white); border: 1px solid var(--border); border-radius: 20px; box-shadow: var(--shadow-lg); z-index: 400; overflow: hidden; animation: dropIn 0.22s cubic-bezier(.34,1.56,.64,1); }
.adm-settings-dropdown.open { display: block; }
@keyframes dropIn { from { opacity: 0; transform: translateY(-10px) scale(0.97); } to { opacity: 1; transform: translateY(0) scale(1); } }
.adm-settings-head { background: var(--blush); padding: 14px 20px; font-size: 11px; font-weight: 700; color: var(--deep-rose); letter-spacing: 2.5px; text-transform: uppercase; border-bottom: 1px solid var(--border); }
.adm-settings-item { display: flex; align-items: center; gap: 14px; padding: 13px 20px; text-decoration: none; color: var(--ink); font-size: 15px; font-weight: 500; transition: background 0.15s ease; border-bottom: 1px solid rgba(247,197,216,0.25); }
.adm-settings-item:last-child { border-bottom: none; }
.adm-settings-item:hover { background: var(--blush); color: var(--deep-rose); }
.adm-settings-item-icon { width: 36px; height: 36px; border-radius: 10px; background: linear-gradient(135deg, var(--petal), #f08050); display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
.adm-settings-item-sub { font-size: 12px; color: var(--muted); font-weight: 400; margin-top: 1px; }

/* ── PAGE ── */
.adm-body { position: relative; z-index: 1; max-width: 1200px; margin: 0 auto; padding: 40px 28px 80px; }

.page-header { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 28px; flex-wrap: wrap; gap: 16px; }
.page-eyebrow { font-size: 12px; font-weight: 600; letter-spacing: 3px; text-transform: uppercase; color: var(--muted); margin-bottom: 6px; }
.page-title { font-family: 'Cormorant Garamond', serif; font-size: 46px; font-weight: 700; color: var(--deep-rose); line-height: 1; }
.page-subtitle { font-size: 15px; color: var(--muted); margin-top: 4px; }

/* ── TWO-COLUMN LANDSCAPE GRID ── */
.adm-grid {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 24px;
    align-items: start;
}
@media (max-width: 860px) {
    .adm-grid { grid-template-columns: 1fr; }
}

/* Add/Edit panel (right column) */
.side-panel {
    background: var(--white); border-radius: var(--radius);
    border: 1px solid var(--border); box-shadow: var(--shadow-sm);
    overflow: hidden; position: sticky; top: 88px;
    animation: fadeUp 0.4s ease both 0.1s;
}
.side-panel-head {
    padding: 16px 24px; font-size: 13px; font-weight: 700;
    color: var(--deep-rose); letter-spacing: 2px; text-transform: uppercase;
    border-bottom: 1px solid var(--border); background: var(--blush);
    display: flex; align-items: center; gap: 8px;
}
.side-panel-body { padding: 24px; }
.side-label { font-size: 12px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: var(--muted); margin-bottom: 8px; display: block; }
.side-input {
    width: 100%; padding: 11px 16px; border: 1.5px solid var(--border);
    border-radius: var(--radius-sm); font-family: 'Jost', sans-serif;
    font-size: 15px; color: var(--ink); background: var(--cream);
    outline: none; transition: border-color 0.2s, box-shadow 0.2s;
    margin-bottom: 16px;
}
.side-input:focus { border-color: var(--rose); box-shadow: 0 0 0 3px rgba(220,120,70,0.18); }

/* Add button */
.btn-add {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    background: linear-gradient(135deg, var(--rose), var(--deep-rose));
    color: #fff; border: none; border-radius: 50px;
    width: 100%; padding: 12px 22px; font-family: 'Jost', sans-serif;
    font-size: 14px; font-weight: 600; cursor: pointer;
    box-shadow: 0 4px 18px rgba(208,90,40,0.30);
    transition: all 0.22s cubic-bezier(.34,1.56,.64,1);
    text-decoration: none;
}
.btn-add:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(208,90,40,0.38); }

/* Panel */
.panel { background: var(--white); border-radius: var(--radius); border: 1px solid var(--border); box-shadow: var(--shadow-sm); overflow: hidden; animation: fadeUp 0.4s ease both; }
.panel-head { padding: 16px 24px; font-size: 13px; font-weight: 700; color: var(--deep-rose); letter-spacing: 2px; text-transform: uppercase; border-bottom: 1px solid var(--border); background: var(--blush); display: flex; align-items: center; gap: 8px; }
.panel-head-dot { width: 8px; height: 8px; background: var(--rose); border-radius: 50%; }

/* Category rows */
.category-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 15px 24px;
    border-bottom: 1px solid rgba(247,197,216,0.22);
    transition: background 0.15s;
    gap: 12px;
}
.category-row:last-child { border-bottom: none; }
.category-row:hover { background: var(--blush); }

.cat-icon {
    width: 42px; height: 42px; border-radius: 12px;
    background: linear-gradient(135deg, var(--petal), #f08050);
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; flex-shrink: 0;
}
.cat-info { flex: 1; min-width: 0; }
.cat-name { font-weight: 600; font-size: 16px; color: var(--ink); }
.cat-count { font-size: 13px; color: var(--muted); margin-top: 2px; }

.row-actions { display: flex; gap: 6px; flex-shrink: 0; }
.action-btn {
    width: 32px; height: 32px; border-radius: 8px; border: 1.5px solid var(--border);
    background: transparent; color: var(--muted); cursor: pointer; font-size: 14px;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.2s ease; text-decoration: none; flex-shrink: 0;
}
.action-btn:hover { background: var(--rose); border-color: var(--rose); color: #fff; }
.action-btn.danger:hover { background: var(--danger); border-color: var(--danger); color: #fff; }

.empty-state { padding: 48px 24px; text-align: center; color: var(--muted); font-size: 16px; }
.empty-state-icon { font-size: 40px; display: block; margin-bottom: 12px; }

/* ── MODALS ── */
.modal-backdrop {
    display: none; position: fixed; inset: 0; z-index: 500;
    background: rgba(74,44,30,0.35); backdrop-filter: blur(4px);
    align-items: center; justify-content: center;
}
.modal-backdrop.active { display: flex; }
.modal {
    background: var(--white); border-radius: 24px; width: 100%; max-width: 420px;
    box-shadow: var(--shadow-lg); animation: modalIn 0.26s cubic-bezier(.34,1.56,.64,1);
    overflow: hidden; margin: 20px;
}
@keyframes modalIn { from { opacity: 0; transform: scale(0.92) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }
.modal-head { background: var(--blush); padding: 20px 24px; border-bottom: 1px solid var(--border); }
.modal-title { font-family: 'Cormorant Garamond', serif; font-size: 26px; font-weight: 700; color: var(--deep-rose); }
.modal-body { padding: 24px; }
.modal-label { font-size: 12px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: var(--muted); margin-bottom: 8px; display: block; }
.modal-input {
    width: 100%; padding: 11px 16px; border: 1.5px solid var(--border);
    border-radius: var(--radius-sm); font-family: 'Jost', sans-serif;
    font-size: 15px; color: var(--ink); background: var(--cream);
    outline: none; transition: border-color 0.2s, box-shadow 0.2s;
}
.modal-input:focus { border-color: var(--rose); box-shadow: 0 0 0 3px rgba(220,120,70,0.18); }
.modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
.btn-cancel {
    padding: 10px 20px; border-radius: 50px; border: 1.5px solid var(--border);
    background: transparent; font-family: 'Jost', sans-serif; font-size: 14px;
    font-weight: 600; color: var(--muted); cursor: pointer; transition: all 0.2s;
}
.btn-cancel:hover { background: var(--blush); color: var(--ink); }
.btn-save {
    padding: 10px 24px; border-radius: 50px; border: none;
    background: linear-gradient(135deg, var(--rose), var(--deep-rose));
    font-family: 'Jost', sans-serif; font-size: 14px; font-weight: 600;
    color: #fff; cursor: pointer; box-shadow: 0 4px 14px rgba(208,90,40,0.28);
    transition: all 0.22s cubic-bezier(.34,1.56,.64,1);
}
.btn-save:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(208,90,40,0.36); }
.btn-delete {
    padding: 10px 24px; border-radius: 50px; border: none;
    background: var(--danger); font-family: 'Jost', sans-serif;
    font-size: 14px; font-weight: 600; color: #fff; cursor: pointer;
    box-shadow: 0 4px 14px rgba(224,92,92,0.25); transition: all 0.2s;
}
.btn-delete:hover { opacity: 0.88; }
.delete-warning { font-size: 14px; color: var(--muted); line-height: 1.6; }
.delete-warning strong { color: var(--ink); }

@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }

/* ── CUSTOM VALIDATION MESSAGE ── */
.field-wrap { position: relative; }
.val-msg {
    display: none;
    align-items: center; gap: 8px;
    margin-top: 8px;
    padding: 10px 14px;
    background: linear-gradient(135deg, #fff0f0, #fde8e8);
    border: 1px solid rgba(230,100,100,0.22);
    border-radius: 10px;
    font-family: 'Jost', sans-serif;
    font-size: 13px; font-weight: 500;
    color: #c03030;
    animation: valPop 0.25s cubic-bezier(.34,1.56,.64,1);
}
.val-msg.show { display: flex; }
.val-msg-icon {
    width: 20px; height: 20px; border-radius: 50%;
    background: linear-gradient(135deg, #f47070, #c03030);
    color: #fff; font-size: 11px; font-weight: 800;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; line-height: 1;
}
@keyframes valPop {
    from { opacity: 0; transform: translateY(-6px) scale(0.97); }
    to   { opacity: 1; transform: translateY(0)   scale(1); }
}
/* shake the input when invalid */
@keyframes shake {
    0%,100% { transform: translateX(0); }
    20%      { transform: translateX(-6px); }
    40%      { transform: translateX(6px); }
    60%      { transform: translateX(-4px); }
    80%      { transform: translateX(4px); }
}
.input-shake { animation: shake 0.35s ease; border-color: #e05c5c !important; box-shadow: 0 0 0 3px rgba(224,92,92,0.18) !important; }
/* ── END VALIDATION ── */

/* Notification badges — added for sidebar alerts */
.nav-badge{display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 5px;border-radius:99px;font-size:10px;font-weight:800;line-height:1;background:#e53e3e;color:#fff;margin-left:auto;flex-shrink:0;animation:badgePop 0.4s cubic-bezier(.34,1.56,.64,1);}
@keyframes badgePop{from{transform:scale(0);opacity:0;}to{transform:scale(1);opacity:1;}}
.adm-icon .pending-dot{position:absolute;top:3px;right:3px;width:9px;height:9px;border-radius:50%;background:#e53e3e;border:2px solid var(--cream);animation:pulseDot 1.6s ease-in-out infinite;}
@keyframes pulseDot{0%,100%{opacity:1;transform:scale(1);}50%{opacity:.55;transform:scale(1.3);}}
</style>
<?php include __DIR__ . '/pwa_head.php'; ?>
</head>
<body>

<!-- NAV -->
<?php $activePage = 'categories'; include 'admin_navbar.php'; ?>

<!-- PAGE -->
<div class="adm-body">
  <div class="page-header">
    <div>
      <div class="page-eyebrow">Store Management</div>
      <div class="page-title">Categories</div>
      <div class="page-subtitle"><?= $totalCategories ?> product categor<?= $totalCategories != 1 ? 'ies' : 'y' ?></div>
    </div>
  </div>

  <div class="adm-grid">

    <!-- LEFT: Categories list -->
    <div class="panel">
      <div class="panel-head"><span class="panel-head-dot"></span> All Categories (<?= $totalCategories ?>)</div>
      <?php if ($totalCategories > 0): ?>
        <?php foreach ($categories as $cat): ?>
        <div class="category-row">
          <div class="cat-icon">🏷️</div>
          <div class="cat-info">
            <div class="cat-name"><?= htmlspecialchars($cat['name']) ?></div>
            <div class="cat-count"><?= $cat['product_count'] ?> product<?= $cat['product_count'] != 1 ? 's' : '' ?></div>
          </div>
          <div class="row-actions">
            <button class="action-btn" title="Rename"
              onclick="openRenameModal(<?= $cat['id'] ?>, '<?= htmlspecialchars(addslashes($cat['name'])) ?>')">✎</button>
            <button class="action-btn danger" title="Delete"
              onclick="openDeleteModal(<?= $cat['id'] ?>, '<?= htmlspecialchars(addslashes($cat['name'])) ?>', <?= $cat['product_count'] ?>)">🗑️</button>
          </div>
        </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="empty-state">
          <span class="empty-state-icon">🏷️</span>
          No categories yet. Add one to get started.
        </div>
      <?php endif; ?>
    </div>

    <!-- RIGHT: Add category form -->
    <div class="side-panel">
      <div class="side-panel-head"><span class="panel-head-dot"></span> Add New Category</div>
      <div class="side-panel-body">
        <form method="POST" id="sideAddForm" novalidate>
          <input type="hidden" name="add_category" value="1">
          <label class="side-label">Category Name</label>
          <div class="field-wrap">
            <input class="side-input" type="text" name="cat_name" id="sideCatName" placeholder="e.g. Fresh, Crochet…">
            <div class="val-msg" id="sideValMsg">
              <span class="val-msg-icon">!</span>
              Please enter a category name.
            </div>
          </div>
          <button type="submit" class="btn-add">＋ Add Category</button>
        </form>
      </div>
    </div>

  </div>
</div>

<!-- ADD MODAL -->
<div class="modal-backdrop" id="addModal">
  <div class="modal">
    <div class="modal-head"><div class="modal-title">Add Category</div></div>
    <div class="modal-body">
      <form method="POST" id="modalAddForm" novalidate>
        <input type="hidden" name="add_category" value="1">
        <label class="modal-label">Category Name</label>
        <div class="field-wrap">
            <input class="modal-input" type="text" name="cat_name" id="addCatName" placeholder="e.g. Fresh, Crochet…" autofocus>
            <div class="val-msg" id="addValMsg">
              <span class="val-msg-icon">!</span>
              Please enter a category name.
            </div>
          </div>
        <div class="modal-actions">
          <button type="button" class="btn-cancel" onclick="closeAddModal()">Cancel</button>
          <button type="submit" class="btn-save">Add Category</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- RENAME MODAL -->
<div class="modal-backdrop" id="renameModal">
  <div class="modal">
    <div class="modal-head"><div class="modal-title">Rename Category</div></div>
    <div class="modal-body">
      <form method="POST" id="renameForm" novalidate>
        <input type="hidden" name="rename_category" value="1">
        <input type="hidden" name="cat_id" id="renameCatId">
        <label class="modal-label">New Name</label>
        <div class="field-wrap">
            <input class="modal-input" type="text" name="cat_name" id="renameCatName">
            <div class="val-msg" id="renameValMsg">
              <span class="val-msg-icon">!</span>
              Please enter a category name.
            </div>
          </div>
        <div class="modal-actions">
          <button type="button" class="btn-cancel" onclick="closeRenameModal()">Cancel</button>
          <button type="submit" class="btn-save">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- DELETE MODAL -->
<div class="modal-backdrop" id="deleteModal">
  <div class="modal">
    <div class="modal-head"><div class="modal-title">Delete Category</div></div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="delete_category" value="1">
        <input type="hidden" name="cat_id" id="deleteCatId">
        <p class="delete-warning" id="deleteWarningText"></p>
        <div class="modal-actions">
          <button type="button" class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
          <button type="submit" class="btn-delete">Delete</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Settings dropdown
(function(){
   });
})();

// ── Custom validation helper ──────────────────────────────────────────────────
function validateField(inputId, msgId) {
  var input = document.getElementById(inputId);
  var msg   = document.getElementById(msgId);
  if (!input || !msg) return true;
  var val = input.value.trim();
  if (val === '') {
    msg.classList.add('show');
    input.classList.add('input-shake');
    input.addEventListener('animationend', function(){ input.classList.remove('input-shake'); }, { once: true });
    input.addEventListener('input', function() {
      if (input.value.trim() !== '') msg.classList.remove('show');
    }, { once: true });
    input.focus();
    return false;
  }
  msg.classList.remove('show');
  return true;
}

// Side panel Add form
document.getElementById('sideAddForm').addEventListener('submit', function(e) {
  if (!validateField('sideCatName', 'sideValMsg')) { e.preventDefault(); }
});

// Modal Add form
document.getElementById('modalAddForm').addEventListener('submit', function(e) {
  if (!validateField('addCatName', 'addValMsg')) { e.preventDefault(); }
});

// Rename form
document.getElementById('renameForm').addEventListener('submit', function(e) {
  if (!validateField('renameCatName', 'renameValMsg')) { e.preventDefault(); }
});

// Add modal
function openAddModal()    { document.getElementById('addModal').classList.add('active'); }
function closeAddModal()   { document.getElementById('addModal').classList.remove('active'); }

// Rename modal
function openRenameModal(id, name) {
  document.getElementById('renameCatId').value   = id;
  document.getElementById('renameCatName').value = name;
  document.getElementById('renameModal').classList.add('active');
}
function closeRenameModal() { document.getElementById('renameModal').classList.remove('active'); }

// Delete modal
function openDeleteModal(id, name, count) {
  document.getElementById('deleteCatId').value = id;
  var msg = 'Are you sure you want to delete <strong>' + name + '</strong>?';
  if (count > 0) msg += ' The ' + count + ' product' + (count!=1?'s':'') + ' in this category will become uncategorised.';
  document.getElementById('deleteWarningText').innerHTML = msg;
  document.getElementById('deleteModal').classList.add('active');
}
function closeDeleteModal() { document.getElementById('deleteModal').classList.remove('active'); }

// Close on backdrop click
['addModal','renameModal','deleteModal'].forEach(function(id){
  document.getElementById(id).addEventListener('click', function(e){
    if (e.target === this) this.classList.remove('active');
  });
});
</script>
</body>
</html>