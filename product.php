<?php
session_start();
include "config.php";

// ✅ Sanitize input
$id = intval($_GET['id']);
if ($id <= 0) {
    die("Invalid product.");
}

// ✅ Use prepared statement
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    die("Product not found.");
}

$message = "";

if (isset($_POST['add'])) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }

    $uid = intval($_SESSION['user_id']);

    // ✅ Re-fetch stock fresh from DB (prevents stale cache exploit)
    $stockStmt = $conn->prepare("SELECT stock FROM products WHERE id = ?");
    $stockStmt->bind_param("i", $id);
    $stockStmt->execute();
    $freshStock = $stockStmt->get_result()->fetch_assoc()['stock'];

    if ($freshStock <= 0) {
        $message = "error";
    } else {
        // ✅ Check existing cart quantity vs available stock
        $checkStmt = $conn->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $checkStmt->bind_param("ii", $uid, $id);
        $checkStmt->execute();
        $cartRow = $checkStmt->get_result()->fetch_assoc();

        $currentQty = $cartRow ? $cartRow['quantity'] : 0;

        if ($currentQty + 1 > $freshStock) {
            $message = "maxstock"; // can't add more than available
        } elseif ($cartRow) {
            $upStmt = $conn->prepare("UPDATE cart SET quantity = quantity + 1 WHERE user_id = ? AND product_id = ?");
            $upStmt->bind_param("ii", $uid, $id);
            $upStmt->execute();
            $message = "success";
        } else {
            $insStmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)");
            $insStmt->bind_param("ii", $uid, $id);
            $insStmt->execute();
            $message = "success";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Product</title>
<link rel="icon" type="image/png" href="images/logo/SB.png">
<link rel="stylesheet" href="style.css">
<style>
  .out-of-stock-badge {
    display: inline-block;
    background-color: #e53e3e;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: bold;
    margin-bottom: 10px;
  }
  .in-stock-badge {
    display: inline-block;
    background-color: #38a169;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: bold;
    margin-bottom: 10px;
  }
  .btn-disabled {
    background-color: #ccc;
    color: #666;
    cursor: not-allowed;
    border: none;
    padding: 8px 16px;
    border-radius: 5px;
  }
  .alert { padding: 8px 12px; border-radius: 5px; margin-top: 10px; font-weight: bold; }
  .alert-error   { background: #fff5f5; color: #e53e3e; border: 1px solid #feb2b2; }
  .alert-success { background: #f0fff4; color: #276749; border: 1px solid #9ae6b4; }
  .alert-warn    { background: #fffbeb; color: #b7791f; border: 1px solid #fcd34d; }
</style>
<?php include __DIR__ . '/pwa_head.php'; ?>
</head>
<body>
<?php include "header.php"; ?>

<h2><?= htmlspecialchars($product['name']) ?></h2>
<img src="images/<?= htmlspecialchars($product['image']) ?>" width="200">
<p><?= htmlspecialchars($product['description']) ?></p>
<p>₱<?= number_format($product['price'], 2) ?></p>

<?php if ($product['stock'] <= 0): ?>
  <span class="out-of-stock-badge">⚠ Out of Stock</span><br>
  <button class="btn-disabled" disabled>Add to Cart</button>
  <p style="color:#999; font-size:13px;">This item is currently unavailable.</p>

<?php else: ?>
  <span class="in-stock-badge">✔ In Stock — <?= $product['stock'] ?> left</span>

  <form method="POST">
    <button name="add">Add to Cart</button>
  </form>

  <?php if ($message === "success"): ?>
    <p class="alert alert-success">✔ Added to cart!</p>
  <?php elseif ($message === "error"): ?>
    <p class="alert alert-error">✘ This item is out of stock.</p>
  <?php elseif ($message === "maxstock"): ?>
    <p class="alert alert-warn">⚠ You've reached the maximum available quantity for this item.</p>
  <?php endif; ?>

<?php endif; ?>

</body>
</html>