<?php
session_start();
include "config.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payment</title>
<link rel="icon" type="image/png" href="images/logo/SB.png">
<link rel="stylesheet" href="style.css">
<?php include __DIR__ . '/pwa_head.php'; ?>
</head>
<body>
<?php include "header.php"; ?>

<?php
echo "<h2>GCash Payment (Demo)</h2>";
echo "<p>Imagine payment gateway here...</p>";

echo "<a href='checkout.php'>Confirm Payment</a>";