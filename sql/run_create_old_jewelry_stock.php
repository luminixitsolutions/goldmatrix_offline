<?php
/**
 * One-time setup: creates tbl_old_jewelry_stock.
 * Run once via: php run_create_old_jewelry_stock.php (from admin/sql/) or open in browser.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$config = realpath(dirname(__DIR__) . '/config.php');
if (!$config || !is_file($config)) {
    die('Config not found');
}
require_once $config;

$sql_file = __DIR__ . '/create_old_jewelry_stock_table.sql';
if (!is_file($sql_file)) {
    die('SQL file not found: ' . $sql_file);
}

$sql = file_get_contents($sql_file);
// Remove comments and empty lines for multi_query if needed; use single query for CREATE TABLE
$sql = trim(preg_replace('/--.*$/m', '', $sql));

if (mysqli_query($conn, $sql)) {
    echo "OK: Table tbl_old_jewelry_stock created successfully.\n";
} else {
    echo "Error: " . mysqli_error($conn) . "\n";
    exit(1);
}
