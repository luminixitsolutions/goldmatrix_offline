<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$suffix = function_exists('auragold_master_list_sql_suffix')
    ? auragold_master_list_sql_suffix($conn, 'tbl_tax_master')
    : '';

$taxes = getList("SELECT id, name, default_value AS rate FROM tbl_tax_master WHERE status = 1 $suffix ORDER BY sort_order ASC, id ASC");
if (!is_array($taxes)) {
    $taxes = [];
}

echo json_encode(['status' => 'success', 'taxes' => $taxes]);
