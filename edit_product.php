<?php
include "config.php";

$id = $_GET['id'];
$product = $conn->query("SELECT * FROM products WHERE id=$id")->fetch_assoc();

if ($_POST) {
  $name = $_POST['name'];
  $desc = $_POST['description'];
  $price = $_POST['price'];
  $cat = $_POST['category'];
  $stock = $_POST['stock'];

  $conn->query("UPDATE products SET 
    name='$name',
    description='$desc',
    price='$price',
    category='$cat',
    stock='$stock'
    WHERE id=$id");

  header("Location: admin.php");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Product</title>
<link rel="icon" type="image/png" href="images/logo/SB.png">
<link rel="shortcut icon" type="image/png" href="images/logo/SB.png">
<link rel="stylesheet" href="style.css">
<?php include __DIR__ . '/pwa_head.php'; ?>
</head>
<body>
<style>
  body {
    font-family: Arial, sans-serif;
    background-color: #ffe6f2;
    color: #333;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    margin: 0;
  }

  form {
    background: #fff;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    width: 400px;
    display: flex;
    flex-direction: column;
    gap: 15px;
  }

  input, textarea, button {
    font-size: 16px;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 5px;
  }

  input:focus, textarea:focus {
    outline: none;
    border-color: #ff99cc;
    box-shadow: 0 0 5px #ff99cc;
  }

  button {
    background-color: #ff66b2;
    color: white;
    border: none;
    cursor: pointer;
    transition: background-color 0.3s;
  }

  button:hover {
    background-color: #e60073;
  }

  textarea {
    resize: none;
    height: 100px;
  }
</style>

<div>
  <h1>Edit Product</h1>
  <form method="POST">
    <label for="name">Product Name:</label>
    <input type="text" id="name" name="name" value="<?= $product['name'] ?>" required>

    <label for="description">Description:</label>
    <textarea id="description" name="description" required><?= $product['description'] ?></textarea>

    <label for="price">Price:</label>
    <input type="number" id="price" name="price" value="<?= $product['price'] ?>" step="0.01" required>

    <label for="category">Category:</label>
    <select id="category" name="category" required>
      <option value="Fresh" <?= $product['category'] == 'Fresh' ? 'selected' : '' ?>>Fresh</option>
      <option value="Satin" <?= $product['category'] == 'Satin' ? 'selected' : '' ?>>Satin</option>
      <option value="Fuzzywire" <?= $product['category'] == 'Fuzzywire' ? 'selected' : '' ?>>Fuzzywire</option>
      <option value="Read-made" <?= $product['category'] == 'Ready-made' ? 'selected' : '' ?>>Ready-made</option>
      <option value="Crochet" <?= $product['category'] == 'Crochet' ? 'selected' : '' ?>>Crochet</option>
    </select>

    <label for="stock">Stock:</label>
    <input type="number" id="stock" name="stock" value="<?= $product['stock'] ?>" required>

    <button type="submit">Update</button>
  </form>
</div>
</body>
</html>