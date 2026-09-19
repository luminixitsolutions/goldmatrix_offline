<?php
session_start();
require_once __DIR__ . '/config.php';
if (empty($_SESSION['Admin']) && empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/includes/auragold_employee_management_schema.php';
require_once __DIR__ . '/includes/auragold_employee_management_menu.php';

if (!empty($_GET['export']) && $_GET['export'] === 'csv') {
    if (!auragold_employee_management_can_view_page('employee_attendance_report')) {
        http_response_code(403);
        exit('Forbidden');
    }
    global $conn;
    $em = auragold_em_bootstrap_page($conn);
    auragold_em_export_attendance_datewise_csv($conn, $em, $_GET);
    exit;
}

$employee_page_key = 'employee_attendance_report';
require __DIR__ . '/includes/employee_management_page_layout.php';
