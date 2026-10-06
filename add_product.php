<?php
session_start();
include "config.php";

if (!isset($_SESSION['user_id'])) {
    die("Login first");
}

$uid = $_SESSION['user_id'];
$user = $conn->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();

if ($user['role'] !== 'admin') {
    die("Access denied");
}

$insert_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name   = $conn->real_escape_string(trim($_POST['name']));
    $desc   = $conn->real_escape_string(trim($_POST['description']));
    $price  = floatval($_POST['price']);
    $cat_id = intval($_POST['category']);
    $stock  = intval($_POST['stock']);

    // Handle image upload
    $img = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $img = basename($_FILES['image']['name']);
        $upload_dir = __DIR__ . '/images/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $img)) {
            $insert_error = 'Image upload failed. Check that the images/ folder exists and is writable.';
        }
    }

    // Only insert if no upload error
    if (empty($insert_error)) {
        $sql = "INSERT INTO products (name, description, price, image, category_id, stock) VALUES ('$name', '$desc', $price, '$img', $cat_id, $stock)";
        if ($conn->query($sql)) {
            header("Location: admin_inventory.php");
            exit;
        } else {
            $insert_error = 'Database error: ' . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add Product</title>
<link rel="icon" type="image/png" href="images/logo/SB.png">
<link rel="shortcut icon" type="image/png" href="images/logo/SB.png">
<link rel="stylesheet" href="style.css">
<?php include __DIR__ . '/pwa_head.php'; ?>
</head>
<body>
<?php include "admin_header.php"; ?>

<div class="add-product-page">
    <div class="add-product-card">
        <h2>ADD PRODUCT</h2>
        <?php if (!empty($insert_error)): ?>
            <div style="background:#f8d7da;color:#721c24;padding:12px 16px;border-radius:10px;margin-bottom:16px;border:2px solid #f5c6cb;font-weight:700;">
                ⚠️ <?= htmlspecialchars($insert_error) ?>
            </div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-row">
                <label for="name">Product Name</label>
                <input id="name" name="name" placeholder="Red rose bouquet" required>
            </div>
            <div class="form-row">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="5" placeholder="Enter product details..."></textarea>
            </div>
            <div class="form-row">
                <label for="category">Category</label>
                <select id="category" name="category" required>
                    <?php
                    $catResult = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
                    while ($cat = $catResult->fetch_assoc()):
                    ?>
                        <option value="<?= intval($cat['id']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-row form-grid">
                <div>
                    <label for="price">Price</label>
                    <input id="price" name="price" type="number" step="0.01" placeholder="150.00" required>
                </div>
                <div>
                    <label for="stock">Stock</label>
                    <input id="stock" name="stock" type="number" placeholder="50" required>
                </div>
            </div>
            <div class="form-row">
                <label for="image">Image</label>
                <input id="image" type="file" name="image" accept="image/*" required>
            </div>
            <div class="form-actions">
                <a href="admin_inventory.php" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary">Add</button>
            </div>
        </form>
    </div>
</div>