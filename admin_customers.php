<?php
// ─────────────────────────────────────────────────────────────────────────────
// admin_customers.php — Admin: view customer details
// ─────────────────────────────────────────────────────────────────────────────
session_start();
include "config.php";

// ── Admin auth guard ──────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$uid  = $_SESSION['user_id'];
$user = $conn->query("SELECT * FROM users WHERE id=" . intval($uid))->fetch_assoc();
if (!$user || $user['role'] !== 'admin') { header("Location: index.php"); exit(); }
$adminName = $user['username'] ?? 'Admin';

// ── Count badges ──────────────────────────────────────────────────────────────
// ── Search ────────────────────────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$searchSql = '';
if ($search !== '') {
    $s = $conn->real_escape_string($search);
    $searchSql = "AND (u.full_name LIKE '%$s%' OR u.username LIKE '%$s%' OR u.email LIKE '%$s%' OR u.phone LIKE '%$s%' OR u.address LIKE '%$s%' OR u.barangay LIKE '%$s%' OR u.gender LIKE '%$s%')";
}

// ── Fetch customers with order stats ─────────────────────────────────────────
$customers = $conn->query("
    SELECT
        u.id,
        u.username,
        u.full_name,
        u.email,
        u.phone,
        u.address,
        u.barangay,
        u.facebook,
        u.avatar,
        u.latitude,
        u.longitude,
        u.gender,
        u.created_at,
        COUNT(o.id)          AS total_orders,
        COALESCE(SUM(o.total_price), 0) AS total_spent,
        MAX(o.created_at)    AS last_order_at
    FROM users u
    LEFT JOIN orders o ON o.user_id = u.id
    WHERE u.role = 'customer'
    $searchSql
    GROUP BY u.id
    ORDER BY u.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$totalCustomers = count($customers);

function orderBadgeClass(string $s): string {
    return match(true) {
        str_contains($s,'pending') || $s==='Pending'                         => 'badge-pending',
        $s==='paid'                                                           => 'badge-paid',
        str_contains($s,'rejected')                                           => 'badge-rejected',
        $s==='Preparing'                                                      => 'badge-preparing',
        str_contains($s,'Delivery') || str_contains($s,'delivery')           => 'badge-out',
        $s==='Delivered'                                                      => 'badge-delivered',
        str_contains($s,'Pickup') || str_contains($s,'pickup')               => 'badge-pickup',
        default                                                               => 'badge-default',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Customers — Precious Satin Bouquet Admin</title>
<link rel="icon" type="image/png" href="images/logo/SB.png">
<link rel="shortcut icon" type="image/png" href="images/logo/SB.png">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700&family=Jost:wght@300;400;500;600;700&family=Great+Vibes&display=swap" rel="stylesheet">
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
    --border:    rgba(255,170,203,0.22);
    --shadow-sm: 0 2px 12px rgba(208,90,40,0.10);
    --shadow-md: 0 8px 32px rgba(208,90,40,0.14);
    --shadow-lg: 0 20px 60px rgba(208,90,40,0.18);
    --radius:    18px;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    background: var(--cream);
    font-family: 'Jost', sans-serif;
    color: var(--ink);
    min-height: 100vh;
}

/* ── NAVBAR ── */
.adm-nav {
    position: sticky; top: 0; z-index: 300;
    height: 68px; display: flex; align-items: center; padding: 0 40px;
    background: rgba(255,248,242,0.90);
    backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
    border-bottom: 1px solid var(--border);
}
.adm-nav-left   { flex: 1; }
.adm-nav-center { flex: 1; display: flex; justify-content: center; }
.adm-nav-right  { flex: 1; display: flex; align-items: center; justify-content: flex-end; gap: 8px; }
.adm-nav::after {
    content: ''; position: absolute; bottom: -1px; left: 50%; transform: translateX(-50%);
    width: 120px; height: 2px;
    background: linear-gradient(90deg, transparent, #f08050, transparent); border-radius: 2px;
}
.adm-nav-brand   { font-family: 'Great Vibes', cursive; font-size: 28px; color: var(--deep-rose); text-decoration: none; }
.adm-nav-tagline { font-family: 'Jost',sans-serif; font-size: 9px; font-weight:500; letter-spacing:3px; text-transform:uppercase; color:var(--muted); display:block; text-align:center; margin-top:-2px; }

.adm-icon {
    width: 38px; height: 38px; border-radius: 50%;
    background: transparent; border: 1.5px solid var(--border);
    display: flex; align-items: center; justify-content: center;
    color: var(--muted); text-decoration: none;
    transition: all 0.25s cubic-bezier(.34,1.56,.64,1);
    cursor: pointer; position: relative;
}
.adm-icon:hover  { background: var(--rose); border-color: var(--rose); color: #fff; box-shadow: 0 4px 16px rgba(208,90,40,.30); transform: translateY(-2px); }
.adm-icon.active { background: var(--deep-rose); border-color: var(--deep-rose); color: #fff; }

.adm-settings-wrap { position: relative; }
.adm-settings-dropdown {
    display: none; position: absolute; top: calc(100% + 14px); right: 0;
    width: 270px; background: var(--white); border: 1px solid var(--border);
    border-radius: 20px; box-shadow: var(--shadow-lg); z-index: 400; overflow: hidden;
    animation: dropIn 0.22s cubic-bezier(.34,1.56,.64,1);
}
.adm-settings-dropdown.open { display: block; }
@keyframes dropIn { from{opacity:0;transform:translateY(-10px) scale(.97)} to{opacity:1;transform:translateY(0) scale(1)} }

.adm-settings-head { background:var(--blush);padding:14px 20px;font-size:10px;font-weight:700;color:var(--deep-rose);letter-spacing:2.5px;text-transform:uppercase;border-bottom:1px solid var(--border); }
.adm-settings-item { display:flex;align-items:center;gap:14px;padding:13px 20px;text-decoration:none;color:var(--ink);font-size:14px;font-weight:500;transition:background .18s;border-bottom:1px solid var(--border); }
.adm-settings-item:last-child { border-bottom:none; }
.adm-settings-item:hover { background:var(--blush); }
.adm-settings-item-icon { width:32px;height:32px;border-radius:10px;background:var(--blush);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0; }
.adm-settings-item-sub  { font-size:11px;color:var(--muted);font-weight:400;margin-top:1px; }
.nav-badge { display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 5px;border-radius:99px;font-size:10px;font-weight:800;line-height:1;background:#e53e3e;color:#fff;margin-left:auto;flex-shrink:0; }
.pending-dot { position:absolute;top:3px;right:3px;width:9px;height:9px;border-radius:50%;background:#e53e3e;border:2px solid var(--cream);animation:pulseDot 1.6s ease-in-out infinite; }
@keyframes pulseDot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.55;transform:scale(1.3)} }

/* ── PAGE ── */
.adm-body { max-width: 1100px; margin: 0 auto; padding: 40px 28px 80px; }

.page-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 28px; flex-wrap: wrap; gap: 12px;
}
.page-title { font-family: 'Cormorant Garamond', serif; font-size: 30px; font-weight: 700; color: var(--ink); }
.page-title span { color: var(--deep-rose); }

/* ── Search bar ── */
.search-wrap {
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    margin-bottom: 24px;
}
.search-form {
    display: flex; align-items: center;
    background: var(--white); border: 1.5px solid var(--border);
    border-radius: 99px; padding: 0 16px 0 14px;
    box-shadow: var(--shadow-sm); flex: 1; max-width: 420px;
    transition: border-color .18s;
}
.search-form:focus-within { border-color: var(--rose); }
.search-form input {
    border: none; outline: none; background: transparent;
    font-family: 'Jost', sans-serif; font-size: 14px; color: var(--ink);
    padding: 11px 8px; flex: 1;
}
.search-form input::placeholder { color: var(--muted); }
.search-form button {
    background: none; border: none; cursor: pointer;
    color: var(--muted); display: flex; align-items: center;
    transition: color .15s; padding: 0;
}
.search-form button:hover { color: var(--deep-rose); }

.count-pill {
    background: var(--white); border: 1px solid var(--border);
    border-radius: 99px; padding: 6px 16px;
    font-size: 12px; color: var(--muted); white-space: nowrap;
}
.count-pill strong { color: var(--deep-rose); }

/* ── Customer cards ── */
.customer-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 0;
    margin-bottom: 14px;
    box-shadow: var(--shadow-sm);
    transition: box-shadow .2s;
    overflow: hidden;
}
.customer-card:hover { box-shadow: var(--shadow-md); }

.cc-header {
    display: flex; align-items: center; gap: 16px;
    padding: 18px 24px;
    cursor: pointer;
    user-select: none;
}
.cc-avatar {
    width: 52px; height: 52px; border-radius: 50%; flex-shrink: 0;
    background: linear-gradient(135deg, var(--petal), var(--rose));
    display: flex; align-items: center; justify-content: center;
    font-family: 'Cormorant Garamond', serif;
    font-size: 22px; font-weight: 700; color: #fff;
    overflow: hidden; border: 2px solid var(--border);
}
.cc-avatar img { width: 100%; height: 100%; object-fit: cover; }

.cc-name-wrap { flex: 1; min-width: 0; }
.cc-full-name {
    font-family: 'Cormorant Garamond', serif;
    font-size: 19px; font-weight: 700; color: var(--ink);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.cc-username { font-size: 12px; color: var(--muted); margin-top: 1px; }

.cc-stats {
    display: flex; gap: 20px; align-items: center;
    flex-shrink: 0;
}
.cc-stat { text-align: center; }
.cc-stat-val { font-size: 16px; font-weight: 700; color: var(--deep-rose); }
.cc-stat-lbl { font-size: 10px; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; }

.cc-chevron {
    color: var(--muted); transition: transform .25s ease; flex-shrink: 0;
    font-size: 18px; margin-left: 8px;
}
.customer-card.open .cc-chevron { transform: rotate(180deg); }

/* ── Expanded details ── */
.cc-details {
    display: none;
    border-top: 1px solid var(--border);
    padding: 22px 24px 24px;
    background: var(--blush);
    animation: detailsIn .22s ease;
}
@keyframes detailsIn { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }
.customer-card.open .cc-details { display: block; }

.cc-details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 14px;
    margin-bottom: 18px;
}

.cc-info-item {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 12px 16px;
    display: flex; gap: 10px; align-items: flex-start;
}
.cc-info-icon { font-size: 18px; flex-shrink: 0; margin-top: 1px; }
.cc-info-label { font-size: 10px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .07em; margin-bottom: 3px; }
.cc-info-value { font-size: 14px; color: var(--ink); font-weight: 500; word-break: break-word; }
.cc-info-value.empty { color: var(--muted); font-style: italic; font-weight: 400; }

/* Map link */
.map-link {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 11px; color: var(--deep-rose); font-weight: 600;
    text-decoration: none; margin-top: 4px;
    transition: opacity .15s;
}
.map-link:hover { opacity: .75; }

/* Orders section */
.cc-orders-title {
    font-size: 11px; font-weight: 700; color: var(--muted);
    text-transform: uppercase; letter-spacing: .08em;
    margin-bottom: 10px;
}
.cc-orders-list {
    display: flex; flex-direction: column; gap: 6px;
}
.cc-order-row {
    background: var(--white); border: 1px solid var(--border);
    border-radius: 10px; padding: 10px 16px;
    display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
    font-size: 13px;
}
.cc-order-no { font-weight: 700; color: var(--ink); }
.cc-order-amount { font-weight: 700; color: var(--deep-rose); margin-left: auto; }
.cc-order-badge {
    padding: 2px 10px; border-radius: 99px; font-size: 11px; font-weight: 600;
}
.badge-pending      { background:#fef3c7; color:#92400e; }
.badge-paid         { background:#dcfce7; color:#166534; }
.badge-rejected     { background:#fee2e2; color:#991b1b; }
.badge-preparing    { background:#dbeafe; color:#1e40af; }
.badge-out          { background:#fce7f3; color:#9d174d; }
.badge-delivered    { background:#f0fdf4; color:#166534; }
.badge-pickup       { background:#ede9fe; color:#5b21b6; }
.badge-default      { background:#f3f4f6; color:#374151; }
.cc-order-date { font-size: 11px; color: #bbb; }

.no-orders {
    text-align: center; padding: 20px;
    color: var(--muted); font-size: 13px;
    background: var(--white); border-radius: 10px;
    border: 1px dashed var(--border);
}

/* Empty state */
.empty-state {
    text-align: center; padding: 70px 20px;
    color: var(--muted); font-size: 14px;
    background: var(--white); border-radius: var(--radius);
    border: 1px solid var(--border);
}
.empty-icon { font-size: 52px; margin-bottom: 14px; }

@media (max-width: 640px) {
    .cc-stats { display: none; }
    .cc-details-grid { grid-template-columns: 1fr; }
}
</style>
<?php include __DIR__ . '/pwa_head.php'; ?>
</head>
<body>

<!-- ── NAVBAR ── -->
<?php $activePage = 'customers'; include 'admin_navbar.php'; ?>

<!-- ── PAGE BODY ── -->
<div class="adm-body">

    <div class="page-header">
        <div>
            <div class="page-title">👤 Our <span>Customers</span></div>
            <div style="font-size:13px;color:var(--muted);margin-top:4px;">
                Full contact and delivery details for every customer
            </div>
        </div>
    </div>

    <!-- Search + count -->
    <div class="search-wrap">
        <form class="search-form" method="GET" action="">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--muted);flex-shrink:0"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" name="q" placeholder="Search by name, email, phone, address…" value="<?= htmlspecialchars($search) ?>">
            <button type="submit">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        </form>
        <div class="count-pill">
            <strong><?= $totalCustomers ?></strong> customer<?= $totalCustomers !== 1 ? 's' : '' ?>
            <?= $search ? ' found' : ' total' ?>
        </div>
        <?php if ($search): ?>
            <a href="admin_customers.php" style="font-size:13px;color:var(--muted);text-decoration:none;">✕ Clear</a>
        <?php endif; ?>
    </div>

    <!-- Customer list -->
    <?php if (empty($customers)): ?>
        <div class="empty-state">
            <div class="empty-icon">🔍</div>
            <?= $search ? 'No customers match your search.' : 'No customers yet.' ?>
        </div>

    <?php else: foreach ($customers as $c):
        $cid = (int)$c['id'];

        // Avatar initials fallback
        $displayName = $c['full_name'] ?: $c['username'];
        $initials = strtoupper(substr($displayName, 0, 1));

    ?>
    <div class="customer-card" id="card-<?= $cid ?>">
        <div class="cc-header" onclick="toggleCard(<?= $cid ?>)">

            <!-- Avatar -->
            <div class="cc-avatar">
                <?php if (!empty($c['avatar'])): ?>
                    <img src="<?= htmlspecialchars($c['avatar']) ?>" alt="">
                <?php else: ?>
                    <?= $initials ?>
                <?php endif; ?>
            </div>

            <!-- Name + username -->
            <div class="cc-name-wrap">
                <div class="cc-full-name"><?= htmlspecialchars($displayName) ?></div>
                <div class="cc-username">@<?= htmlspecialchars($c['username']) ?> · Joined <?= date('M j, Y', strtotime($c['created_at'])) ?></div>
            </div>

            <!-- Stats -->
            <div class="cc-stats">
                <div class="cc-stat">
                    <div class="cc-stat-val"><?= (int)$c['total_orders'] ?></div>
                    <div class="cc-stat-lbl">Orders</div>
                </div>
                <div class="cc-stat">
                    <div class="cc-stat-val">₱<?= number_format((float)$c['total_spent'], 0) ?></div>
                    <div class="cc-stat-lbl">Total Spent</div>
                </div>
            </div>

            <div class="cc-chevron">▾</div>
        </div>

        <!-- Expanded details -->
        <div class="cc-details">
            <div class="cc-details-grid">

                <!-- Full Name -->
                <div class="cc-info-item">
                    <div class="cc-info-icon">🧑🏼</div>
                    <div>
                        <div class="cc-info-label">Full Name</div>
                        <div class="cc-info-value <?= empty($c['full_name']) ? 'empty' : '' ?>">
                            <?= $c['full_name'] ? htmlspecialchars($c['full_name']) : 'Not provided' ?>
                        </div>
                    </div>
                </div>

                <!-- Email -->
                <div class="cc-info-item">
                    <div class="cc-info-icon">📧</div>
                    <div>
                        <div class="cc-info-label">Email</div>
                        <div class="cc-info-value <?= empty($c['email']) ? 'empty' : '' ?>">
                            <?php if (!empty($c['email'])): ?>
                                <a href="mailto:<?= htmlspecialchars($c['email']) ?>" style="color:var(--deep-rose);text-decoration:none;">
                                    <?= htmlspecialchars($c['email']) ?>
                                </a>
                            <?php else: ?>
                                Not provided
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Phone -->
                <div class="cc-info-item">
                    <div class="cc-info-icon">📞</div>
                    <div>
                        <div class="cc-info-label">Phone</div>
                        <div class="cc-info-value <?= empty($c['phone']) ? 'empty' : '' ?>">
                            <?php if (!empty($c['phone'])): ?>
                                <a href="tel:<?= htmlspecialchars($c['phone']) ?>" style="color:var(--deep-rose);text-decoration:none;">
                                    <?= htmlspecialchars($c['phone']) ?>
                                </a>
                            <?php else: ?>
                                Not provided
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Delivery Address -->
                <div class="cc-info-item" style="grid-column: span 1;">
                    <div class="cc-info-icon">📍</div>
                    <div>
                        <div class="cc-info-label">Delivery Address</div>
                        <div class="cc-info-value <?= empty($c['address']) ? 'empty' : '' ?>">
                            <?php if (!empty($c['address'])): ?>
                                <?= htmlspecialchars($c['address']) ?>
                                <?php if (!empty($c['barangay'])): ?>
                                    <span style="color:var(--muted);font-size:12px;display:block;margin-top:2px;">Brgy. <?= htmlspecialchars($c['barangay']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($c['latitude']) && !empty($c['longitude'])): ?>
                                    <a class="map-link" href="https://www.google.com/maps?q=<?= $c['latitude'] ?>,<?= $c['longitude'] ?>" target="_blank" rel="noopener">
                                        🗺️ View on Map
                                    </a>
                                <?php endif; ?>
                            <?php else: ?>
                                No address on file
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Gender -->
                <div class="cc-info-item">
                    <div class="cc-info-icon">
                        <?php
                            $g = $c['gender'] ?? '';
                            echo match($g) {
                                'Male'              => '♂️',
                                'Female'            => '♀️',
                                'Non-binary'        => '⚧️',
                                'Prefer not to say' => '🔒',
                                default             => '👤',
                            };
                        ?>
                    </div>
                    <div>
                        <div class="cc-info-label">Gender</div>
                        <div class="cc-info-value <?= empty($c['gender']) ? 'empty' : '' ?>">
                            <?= !empty($c['gender']) ? htmlspecialchars($c['gender']) : 'Not provided' ?>
                        </div>
                    </div>
                </div>

                <!-- Facebook -->
                <?php if (!empty($c['facebook'])): ?>
                <div class="cc-info-item">
                    <div class="cc-info-icon" style="background:transparent;padding:0;">
                        <img src="images/logo/fb.png" alt="Facebook" style="width:28px;height:28px;border-radius:50%;object-fit:cover;">
                    </div>
                    <div>
                        <div class="cc-info-label">Facebook</div>
                        <div class="cc-info-value"><?= htmlspecialchars($c['facebook']) ?></div>
                    </div>
                </div>
                <?php endif; ?>

            </div>

        </div><!-- /cc-details -->
    </div><!-- /customer-card -->
    <?php endforeach; endif; ?>

</div>

<script>
/* Navbar dropdown */

/* Card toggle */
function toggleCard(id) {
    const card = document.getElementById('card-' + id);
    card.classList.toggle('open');
}
</script>

</body>
</html>