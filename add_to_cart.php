<?php
ob_start();
include "config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['user_id'];

$product_id = intval($_POST['product_id']);
$total      = floatval($_POST['total']);
$addQty     = max(1, intval($_POST['qty'] ?? 1));

/* CUSTOM DATA */
$wrapper = $_POST['wrapper_name'] ?? 'none';
$ribbon  = $_POST['ribbon_name']  ?? 'none';
$addons  = $_POST['addons_data']  ?? '';

/* CHECK IF SAME ITEM EXISTS IN CART */
$check = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ? AND wrapper = ? AND ribbon = ? AND addons = ?");
$check->bind_param("iisss", $uid, $product_id, $wrapper, $ribbon, $addons);
$check->execute();
$row = $check->get_result()->fetch_assoc();
$check->close();

if ($row) {
    $newQty = $row['quantity'] + $addQty;
    $upStmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
    $upStmt->bind_param("ii", $newQty, $row['id']);
    $upStmt->execute();
    $upStmt->close();
} else {
    $insStmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity, custom_price, wrapper, ribbon, addons) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $insStmt->bind_param("iiidsss", $uid, $product_id, $addQty, $total, $wrapper, $ribbon, $addons);
    $insStmt->execute();
    $insStmt->close();
}

header("Location: cart.php");
exit();