<?php
include "config.php";

/* CATEGORY FILTER */
$category = $_GET['category'] ?? '';

// ── REUSABLE QUERY FUNCTION ──────────────────────────────────────────────────
/**
 * Fetch products joined with their category name.
 * @param  mysqli $conn      Active DB connection
 * @param  string $category  Category name, 'all', or '' (featured/random)
 * @return mysqli_result
 */
function getProductsByCategory(mysqli $conn, string $category): mysqli_result {
    if ($category && $category !== 'all') {
        // Specific category — use prepared statement to prevent SQL injection
        $stmt = $conn->prepare(
            "SELECT p.*, c.name AS category_name
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE c.name = ?
             ORDER BY p.id DESC"
        );
        $stmt->bind_param("s", $category);
        $stmt->execute();
        return $stmt->get_result();
    }

    if ($category === 'all') {
        // All products
        return $conn->query(
            "SELECT p.*, c.name AS category_name
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             ORDER BY p.id DESC"
        );
    }

    // Default — featured/random sample
    return $conn->query(
        "SELECT p.*, c.name AS category_name
         FROM products p
         LEFT JOIN categories c ON p.category_id = c.id
         ORDER BY RAND() LIMIT 8"
    );
}
// ─────────────────────────────────────────────────────────────────────────────

// ── AJAX REQUEST: return only the products grid HTML ─────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    $result = getProductsByCategory($conn, $category);

    if (!$result) { echo ''; exit; }

    header('Content-Type: application/json');
    $title = $category && $category !== 'all'
        ? htmlspecialchars(ucfirst($category))
        : ($category === 'all' ? 'All Arrangements' : 'Featured Blooms');

    ob_start();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $isReadymade  = strtolower($row['category_name'] ?? '') === 'readymade';
            $productLink  = $isReadymade
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
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 001.99 1.61h9.72a2 2 0 001.99-1.61L23 6H6"/></svg>
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

// ── NORMAL PAGE LOAD ──────────────────────────────────────────────────────────
// Load categories dynamically from DB
$catRows   = [];
$catResult = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
if ($catResult) {
    while ($c = $catResult->fetch_assoc()) {
        $catRows[] = $c;
    }
}

// Single call — no more duplicate query blocks
$result = getProductsByCategory($conn, $category);

if (!$result) {
    die("Query Error: " . $conn->error);
}
?>
