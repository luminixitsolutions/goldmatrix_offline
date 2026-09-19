<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/includes/auragold_require_login.php';
auragold_require_login_or_exit();
$auragold_mac = [
    'dir'   => 'amount_to_metal',
    'title' => 'Amount to Metal',
];
?><!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Amount to Metal - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
<?php include 'header-script.php'; ?>
</head>
<style>
html, body { height: 100%; overflow-x: hidden; }
.layout-content { height: calc(100vh - 60px); overflow-y: auto; }
</style>
<body>
<?php include 'sidebar.php'; ?>
<?php include __DIR__ . '/metal-amount-conversion-chunk.php'; ?>
<?php include 'footer-script.php'; ?>
</body>
</html>
