<?php
session_start();

// Only log out when the modal confirmation form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmed'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Direct GET access (no confirmation) → bounce back
$redirect = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
header("Location: " . $redirect);
exit();