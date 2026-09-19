<?php
/**
 * JSON: financial years for login dropdown (depends on selected branch).
 */
require_once dirname(__DIR__) . '/includes/session_init.php';
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/login_financial_years_helper.php';

header('Content-Type: application/json; charset=utf-8');

$login_branch_id = isset($_POST['login_branch_id']) ? (int) $_POST['login_branch_id'] : (isset($_GET['login_branch_id']) ? (int) $_GET['login_branch_id'] : 0);

$years = auragold_fetch_financial_years_for_branch_login($login_branch_id);

echo json_encode([
    'status' => 'success',
    'years'  => $years,
]);
