<?php
include "config.php";

$totalUsers = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
$totalOrders = $conn->query("SELECT COUNT(*) as c FROM orders")->fetch_assoc()['c'];
$totalSales = $conn->query("SELECT SUM(total_price) as s FROM orders")->fetch_assoc()['s'];

include "header.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Analytics</title>
<link rel="icon" type="image/png" href="images/logo/SB.png">
<link rel="shortcut icon" type="image/png" href="images/logo/SB.png">
<link rel="stylesheet" href="style.css">
<?php include __DIR__ . '/pwa_head.php'; ?>
</head>
<body>

<h2>Analytics</h2>

<div class="card">Users: <?= $totalUsers ?></div>
<div class="card">Orders: <?= $totalOrders ?></div>
<div class="card">Sales: ₱<?= $totalSales ?></div>