<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/session_login_type.php';
require_once __DIR__ . '/includes/auragold_employee_management_schema.php';

if (empty($_SESSION['Admin'])) {
    header('Location: index.php');
    exit;
}
if (!auragold_session_is_admin_login_type()) {
    header('Location: dashboard.php');
    exit;
}

$employee_master_type = 'designations';
require __DIR__ . '/includes/employee_master_management_page.php';
