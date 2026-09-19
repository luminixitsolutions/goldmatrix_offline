<?php
session_start();
require_once __DIR__ . '/config.php';
if (empty($_SESSION['Admin']) && empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$employee_page_key = 'employee_tasks';
require __DIR__ . '/includes/employee_management_page_layout.php';
