<?php
include "config.php";

$category = $_GET['category'] ?? '';

if ($category && $category !== 'all') {
    $stmt = $conn->prepare("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE c.name=? ORDER BY p.id DESC");
    $stmt->bind_param("s", $category);
    $stmt->execute();
    $result = $stmt->get_result();
} elseif ($category === 'all') {
    $result = $conn->query("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC");
} else {
    $result = $conn->query("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY RAND() LIMIT 8");
}

if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => $conn->error]);
    exit;
}

// Return JSON: title + products HTML
$title = '';
if ($category && $category !== 'all') {
    $title = htmlspecialchars(ucfirst($category));
} elseif ($category === 'all') {
    $title = 'All Arrangements';
} else {
    $title = 'Featured Blooms';
}

$html = '';
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $imgSrc   = htmlspecialchars($row['image']);
        $name     = htmlspecialchars($row['name']);
        $catName  = htmlspecialchars($row['category_name'] ?? '');
        $desc     = htmlspecialchars(substr($row['description'], 0, 72));
        $price    = number_format($row['price'], 2);
        $id       = (int)$row['id'];

        // Readymade products go to readymade.php; all others go to customize.php
        $isReadymade = (strtolower($row['category_name'] ?? '') === 'readymade');
        $detailLink  = $isReadymade ? "readymade.php?id={$id}" : "customize.php?id={$id}";
        $overlayLabel = $isReadymade ? "View Details" : "Add to Cart";
        $footerLabel  = $isReadymade ? "View Details →" : "Customize →";

        $badgeHtml = $catName
            ? "<span class=\"card-category-badge\">{$catName}</span>"
            : '';

        $html .= <<<CARD
        <div class="product-card reveal">
          <div class="product-card-img">
            <img src="images/{$imgSrc}" alt="{$name}" loading="lazy">
            {$badgeHtml}
            <div class="card-overlay">
              <a href="{$detailLink}" class="card-overlay-btn">{$overlayLabel}</a>
            </div>
          </div>
          <div class="product-card-body">
            <h3 class="product-card-name">{$name}</h3>
            <p class="product-card-desc">{$desc}…</p>
            <div class="product-card-footer">
              <span class="product-card-price">&#8369;{$price}</span>
              <a href="{$detailLink}" class="product-card-link">{$footerLabel}</a>
            </div>
          </div>
        </div>
CARD;
    }
} else {
    $html = '<div class="empty-state"><p>No blooms found in this collection.</p></div>';
}

header('Content-Type: application/json');
echo json_encode(['title' => $title, 'html' => $html]);