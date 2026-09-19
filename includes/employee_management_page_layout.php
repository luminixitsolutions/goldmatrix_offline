<?php

/**
 * Shared layout for Employee Management pages.
 * Expects: $employee_page_key, $employee_page_title (optional), $employee_page_lead (optional)
 */
if (!isset($employee_page_key) || trim((string) $employee_page_key) === '') {
    http_response_code(500);
    exit('Employee page key missing.');
}

require_once __DIR__ . '/auragold_employee_management_menu.php';
require_once __DIR__ . '/auragold_employee_management_schema.php';

$employee_menu_item = null;
foreach (auragold_employee_management_all_page_items() as $_emItem) {
    if (($_emItem['key'] ?? '') === $employee_page_key) {
        $employee_menu_item = $_emItem;
        break;
    }
}
if (!$employee_menu_item) {
    http_response_code(404);
    exit('Employee page not found.');
}

if (!auragold_employee_management_can_view_page($employee_page_key)) {
    header('Location: dashboard.php');
    exit;
}

if (empty($employee_page_title)) {
    $employee_page_title = (string) ($employee_menu_item['label'] ?? 'Employee Management');
}
if (!isset($employee_page_lead)) {
    $employee_page_lead = (string) ($employee_menu_item['lead'] ?? '');
}

$page_title = $employee_page_title . ' — Employee Management — ' . auragold_app_name();
$current_page = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
$em = auragold_em_bootstrap_page($conn);
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
    <?php include __DIR__ . '/../header-script.php'; ?>
    <link rel="stylesheet" href="set-software-sidebar.css">
    <link rel="stylesheet" href="assets/css/employee-management.css">
</head>
<body>
    <?php include __DIR__ . '/../sidebar.php'; ?>
    <div class="layout-content">
        <div class="container-fluid flex-grow-1" style="padding-top: 0; padding-bottom: 0;">
            <div class="set-software-wrapper">
                <?php include __DIR__ . '/../employee-management-sidebar.php'; ?>
                <div class="set-software-main">
                    <div class="auragold-employee-page">
                        <h1><?php echo htmlspecialchars($employee_page_title, ENT_QUOTES, 'UTF-8'); ?></h1>
                        <?php if (trim((string) $employee_page_lead) !== ''): ?>
                        <p class="em-lead"><?php echo htmlspecialchars((string) $employee_page_lead, ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endif; ?>
                        <?php
                        require_once __DIR__ . '/employee_management/render_page.php';
                        auragold_em_render_page($employee_page_key, $em, $conn);
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/../footer-script.php'; ?>
    <script src="assets/js/employee-management.js"></script>
</body>
</html>
