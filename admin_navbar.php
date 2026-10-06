<?php
/**
 * admin_navbar.php — Shared navbar include for all Precious Satin Bouquet admin pages.
 *
 * REQUIRES (set before including this file):
 *   $conn          — active MySQLi connection
 *   $activePage    — one of: 'dashboard' | 'inventory' | 'orders' | 'customers'
 *                            'categories' | 'payments' | 'messages' | 'reviews' | 'profile'
 *
 * Automatically fetches live badge counts on every page load so
 * GCash-pending and unread-messages notifications are always fresh.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

// ── Live notification counts ──────────────────────────────────────────────────
$_nav_pendingGcash   = (int)($conn->query(
    "SELECT COUNT(*) AS c FROM orders WHERE payment_method='gcash' AND status='pending_verification'"
)->fetch_assoc()['c'] ?? 0);

$_nav_unreadMessages = (int)($conn->query(
    "SELECT COUNT(*) AS c FROM contact_messages WHERE is_read=0"
)->fetch_assoc()['c'] ?? 0);

// ── Unseen orders: create tracking table once, sync new orders, count unseen ──
$conn->query("
    CREATE TABLE IF NOT EXISTS admin_order_seen (
        order_id INT NOT NULL PRIMARY KEY,
        seen     TINYINT(1) NOT NULL DEFAULT 0
    )
");
// Add any new orders not yet in the tracking table (unseen by default)
$conn->query("INSERT IGNORE INTO admin_order_seen (order_id, seen) SELECT id, 0 FROM orders");
$_nav_unseenOrders = (int)($conn->query(
    "SELECT COUNT(*) AS c FROM admin_order_seen WHERE seen = 0"
)->fetch_assoc()['c'] ?? 0);

// ── Total badge for the gear icon ────────────────────────────────────────────
$_nav_totalBadge = $_nav_pendingGcash + $_nav_unreadMessages + $_nav_unseenOrders;

// ── Helper: is this link the current page? ───────────────────────────────────
function _nav_active(string $page, string $current): string {
    return $page === $current ? ' active-page' : '';
}
?>

<?php /* ═══════════════════════════ NAVBAR CSS ═══════════════════════════ */ ?>
<style>
/* ── Shared navbar styles (safe to declare on every page) ── */
.adm-nav{
    position:fixed;top:0;left:0;right:0;z-index:300;
    height:62px;display:flex;align-items:center;padding:0 28px;
    background:rgba(255,248,242,0.95);
    backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);
    border-bottom:1px solid var(--border);
}
.adm-nav::after{
    content:'';position:absolute;bottom:-1px;left:50%;transform:translateX(-50%);
    width:100px;height:2px;
    background:linear-gradient(90deg,transparent,#f08050,transparent);
    border-radius:2px;
}
.adm-nav-left  {flex:1;}
.adm-nav-center{flex:1;display:flex;justify-content:center;}
.adm-nav-right {flex:1;display:flex;align-items:center;justify-content:flex-end;gap:8px;}

.adm-nav-brand {
    font-family:'Great Vibes',cursive;font-size:28px;
    color:var(--deep-rose);text-decoration:none;
    letter-spacing:0.5px;line-height:1;
}
.adm-nav-tagline{
    font-size:9px;font-weight:500;letter-spacing:3px;text-transform:uppercase;
    color:var(--muted);display:block;text-align:center;margin-top:-2px;
}

/* Icon buttons */
.adm-icon{
    width:36px;height:36px;border-radius:50%;
    background:transparent;border:1.5px solid var(--border);
    display:flex;align-items:center;justify-content:center;
    color:var(--muted);text-decoration:none;cursor:pointer;
    transition:all 0.2s;position:relative;
}
.adm-icon:hover{
    background:var(--rose);border-color:var(--rose);color:#fff;
    box-shadow:0 4px 16px rgba(208,90,40,0.30);transform:translateY(-2px);
}
.adm-icon.active{background:var(--deep-rose);border-color:var(--deep-rose);color:#fff;}

/* Pulsing dot on the gear icon */
.adm-icon .pending-dot{
    position:absolute;top:3px;right:3px;
    width:9px;height:9px;border-radius:50%;
    background:#e53e3e;border:2px solid var(--cream);
    animation:pulseDot 1.6s ease-in-out infinite;
}
@keyframes pulseDot{
    0%,100%{opacity:1;transform:scale(1);}
    50%{opacity:.55;transform:scale(1.3);}
}

/* Settings dropdown */
.adm-settings-wrap{position:relative;}
.adm-settings-dropdown{
    display:none;position:absolute;top:calc(100% + 12px);right:0;
    width:280px;background:#fff;border:1px solid var(--border);
    border-radius:20px;box-shadow:0 20px 60px rgba(208,90,40,0.18);
    z-index:400;overflow:hidden;
    animation:dropIn 0.22s cubic-bezier(.34,1.56,.64,1);
}
.adm-settings-dropdown.open{display:block;}
@keyframes dropIn{from{opacity:0;transform:translateY(-10px) scale(0.97);}to{opacity:1;transform:translateY(0) scale(1);}}

.adm-settings-head{
    background:var(--blush);padding:14px 20px;
    font-size:10px;font-weight:700;color:var(--deep-rose);
    letter-spacing:2.5px;text-transform:uppercase;
    border-bottom:1px solid var(--border);
}
.adm-settings-item{
    display:flex;align-items:center;gap:14px;
    padding:13px 20px;text-decoration:none;
    color:var(--ink);font-size:14px;font-weight:500;
    transition:background 0.15s;
    border-bottom:1px solid rgba(247,197,216,0.25);
}
.adm-settings-item:last-child{border-bottom:none;}
.adm-settings-item:hover,.adm-settings-item.active-page{
    background:var(--blush);color:var(--deep-rose);
}
.adm-settings-item-icon{
    width:34px;height:34px;border-radius:10px;
    background:linear-gradient(135deg,var(--petal),#f08050);
    display:flex;align-items:center;justify-content:center;
    font-size:16px;flex-shrink:0;
}
.adm-settings-item-text{flex:1;min-width:0;}
.adm-settings-item-title{font-size:14px;font-weight:600;color:inherit;}
.adm-settings-item-sub{font-size:11px;color:var(--muted);font-weight:400;margin-top:1px;}

/* Notification badges */
.nav-badge{
    display:inline-flex;align-items:center;justify-content:center;
    min-width:18px;height:18px;padding:0 5px;border-radius:99px;
    font-size:10px;font-weight:800;line-height:1;
    background:#e53e3e;color:#fff;margin-left:auto;flex-shrink:0;
    animation:badgePop 0.4s cubic-bezier(.34,1.56,.64,1);
}
@keyframes badgePop{from{transform:scale(0);opacity:0;}to{transform:scale(1);opacity:1;}}

/* Gear icon total badge (small circle top-right) */
.gear-badge{
    position:absolute;top:1px;right:1px;
    min-width:15px;height:15px;padding:0 3px;
    border-radius:99px;font-size:9px;font-weight:900;
    background:#e53e3e;color:#fff;
    display:flex;align-items:center;justify-content:center;
    border:1.5px solid var(--cream);line-height:1;
    animation:badgePop 0.4s cubic-bezier(.34,1.56,.64,1);
}
</style>

<?php /* ═══════════════════════════ NAVBAR HTML ══════════════════════════ */ ?>
<nav class="adm-nav">
    <div class="adm-nav-left"></div>

    <div class="adm-nav-center">
        <a href="admin.php" style="text-decoration:none;text-align:center;display:block;">
            <span class="adm-nav-brand">Precious Satin Bouquet</span>
            <span class="adm-nav-tagline">Admin Dashboard</span>
        </a>
    </div>

    <div class="adm-nav-right">

        <!-- Dashboard -->
        <a href="admin.php" class="adm-icon<?= _nav_active('dashboard', $activePage) ?>" title="Dashboard">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor">
                <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
            </svg>
        </a>

        <!-- Profile -->
        <a href="admin_profile.php" class="adm-icon<?= _nav_active('profile', $activePage) ?>" title="My Profile">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/>
            </svg>
        </a>

        <!-- Settings gear + dropdown -->
        <div class="adm-settings-wrap">
            <div class="adm-icon" id="settingsBtn" title="Manage Store">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="3"/>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                </svg>
                <?php if ($_nav_totalBadge > 0): ?>
                    <span class="gear-badge"><?= $_nav_totalBadge ?></span>
                <?php endif; ?>
            </div>

            <div class="adm-settings-dropdown" id="settingsDropdown">
                <div class="adm-settings-head">Manage Store</div>

                <a href="admin_inventory.php" class="adm-settings-item<?= _nav_active('inventory', $activePage) ?>">
                    <div class="adm-settings-item-icon">🗂️</div>
                    <div class="adm-settings-item-text">
                        <div class="adm-settings-item-title">Inventory</div>
                        <div class="adm-settings-item-sub">Manage products &amp; stock</div>
                    </div>
                </a>

                <a href="admin_orders.php" class="adm-settings-item<?= _nav_active('orders', $activePage) ?>">
                    <div class="adm-settings-item-icon">🛒</div>
                    <div class="adm-settings-item-text">
                        <div class="adm-settings-item-title">Orders</div>
                        <div class="adm-settings-item-sub">View &amp; track all orders</div>
                    </div>
                    <?php if ($_nav_unseenOrders > 0): ?>
                        <span class="nav-badge"><?= $_nav_unseenOrders ?></span>
                    <?php endif; ?>
                </a>

                <a href="admin_customers.php" class="adm-settings-item<?= _nav_active('customers', $activePage) ?>">
                    <div class="adm-settings-item-icon">👤</div>
                    <div class="adm-settings-item-text">
                        <div class="adm-settings-item-title">Customers</div>
                        <div class="adm-settings-item-sub">Manage customer accounts</div>
                    </div>
                </a>

                <a href="admin_categories.php" class="adm-settings-item<?= _nav_active('categories', $activePage) ?>">
                    <div class="adm-settings-item-icon">🏷️</div>
                    <div class="adm-settings-item-text">
                        <div class="adm-settings-item-title">Categories</div>
                        <div class="adm-settings-item-sub">Manage product categories</div>
                    </div>
                </a>

                <!-- GCash Payments — badge shows pending count -->
                <a href="admin_payments.php" class="adm-settings-item<?= _nav_active('payments', $activePage) ?>">
                    <div class="adm-settings-item-icon" style="background:#fff;padding:0;overflow:hidden;border-radius:10px;">
                        <img src="images/logo/gcash.png" alt="GCash" style="width:34px;height:34px;object-fit:contain;display:block;border-radius:10px;">
                    </div>
                    <div class="adm-settings-item-text">
                        <div class="adm-settings-item-title">GCash Payments</div>
                        <div class="adm-settings-item-sub">Verify customer payments</div>
                    </div>
                    <?php if ($_nav_pendingGcash > 0): ?>
                        <span class="nav-badge"><?= $_nav_pendingGcash ?></span>
                    <?php endif; ?>
                </a>

                <!-- Messages — badge shows unread count -->
                <a href="admin_messages.php" class="adm-settings-item<?= _nav_active('messages', $activePage) ?>">
                    <div class="adm-settings-item-icon" style="background:linear-gradient(135deg,#fda4af,#f43f5e);">💌</div>
                    <div class="adm-settings-item-text">
                        <div class="adm-settings-item-title">Messages</div>
                        <div class="adm-settings-item-sub">Customer contact messages</div>
                    </div>
                    <?php if ($_nav_unreadMessages > 0): ?>
                        <span class="nav-badge"><?= $_nav_unreadMessages ?></span>
                    <?php endif; ?>
                </a>

                <!-- Reviews -->
                <a href="admin_reviews.php" class="adm-settings-item<?= _nav_active('reviews', $activePage) ?>" style="background:<?= $activePage==='reviews' ? 'var(--blush)' : 'rgba(232,121,168,0.06)' ?>;">
                    <div class="adm-settings-item-icon" style="background:linear-gradient(135deg,#f9a8c9,#e879a8);">⭐</div>
                    <div class="adm-settings-item-text">
                        <div class="adm-settings-item-title">Reviews</div>
                        <div class="adm-settings-item-sub">Customer ratings &amp; feedback</div>
                    </div>
                </a>

            </div><!-- /#settingsDropdown -->
        </div><!-- /.adm-settings-wrap -->

    </div><!-- /.adm-nav-right -->
</nav>
<!-- Spacer so page content clears the fixed navbar -->
<div style="height:62px;flex-shrink:0;"></div>

<script>
(function() {
    var btn = document.getElementById('settingsBtn');
    var dd  = document.getElementById('settingsDropdown');
    if (!btn || !dd) return;

    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        var open = dd.classList.toggle('open');
        btn.classList.toggle('active', open);
    });
    document.addEventListener('click', function() {
        dd.classList.remove('open');
        btn.classList.remove('active');
    });
    dd.addEventListener('click', function(e) { e.stopPropagation(); });
})();
</script>