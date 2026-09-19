<?php
/**
 * Expects: $DASHBOARD_PAGE_TITLE (string), optional $DASHBOARD_EXTRA_CSS (string),
 * optional $DASHBOARD_AFTER_BODY (HTML right after <body>, e.g. full-page loader),
 * optional $DASHBOARD_SKIP_PAGE_LOADER (bool) — skip shared brand loader from includes/brand_page_loader.php
 * optional $DASHBOARD_FS_PAGE (bool) — Financial Statement submenu: body.fs-page + fs-pages-mobile.css
 */
if (!defined('AURAGOLD_DASHBOARD_SHELL')) {
    define('AURAGOLD_DASHBOARD_SHELL', true);
}
/** Dashboard shell pages do not use DataTables — skip heavy table CDN bundles. */
if (!isset($AURAGOLD_USE_DATATABLES)) {
    $AURAGOLD_USE_DATATABLES = false;
}
if (!isset($AURAGOLD_USE_DATATABLE_BUTTONS)) {
    $AURAGOLD_USE_DATATABLE_BUTTONS = false;
}
if (!isset($DASHBOARD_PAGE_TITLE) || trim((string) $DASHBOARD_PAGE_TITLE) === '') {
    $DASHBOARD_PAGE_TITLE = 'Dashboard';
}
require_once __DIR__ . '/brand_page_loader.php';
if (auragold_brand_page_loader_should_show()) {
    $DASHBOARD_AFTER_BODY = auragold_brand_page_loader_after_body_html() . (!empty($DASHBOARD_AFTER_BODY) ? $DASHBOARD_AFTER_BODY : '');
}
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title><?php echo htmlspecialchars($DASHBOARD_PAGE_TITLE . ' — ' . auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
<?php include __DIR__ . '/../header-script.php'; ?>
    <style>
        html.default-style, html.default-style body {
            background: linear-gradient(135deg, #f5f7fa 0%, #eeeeee 100%) !important;
            min-height: 100vh;
        }
        .layout-wrapper.layout-2 { min-height: 100vh; background: transparent; }
        .layout-content {
            min-height: calc(100vh - 48px) !important;
            width: 100% !important;
            max-width: 100% !important;
        }
        .dash-stat-card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid #ece9ff;
            box-shadow: 0 8px 22px rgba(0,0,0,.07);
            padding: 18px 20px;
        }
        .dash-stat-card .lbl { font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: .04em; font-weight: 600; }
        .dash-stat-card .val { font-size: 22px; font-weight: 700; color: #1e293b; margin-top: 4px; }
        .dash-table-wrap { background: #fff; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; }
        .dash-table-wrap table { margin-bottom: 0; font-size: 14px; }
        .dash-table-wrap thead th { background: #f8fafc; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0; }
        .dash-page-title { font-weight: 650; color: #1d2c4f; font-size: 1.25rem; margin-bottom: 4px; }
        .dash-page-sub { color: #64748b; font-size: 13px; margin-bottom: 18px; }
    </style>
    <?php if (!empty($DASHBOARD_EXTRA_CSS)) { echo $DASHBOARD_EXTRA_CSS; } ?>
    <?php if (!empty($DASHBOARD_FS_PAGE)): ?>
    <link rel="stylesheet" href="assets/css/fs-pages-mobile.css">
    <?php endif; ?>
</head>
<body<?php echo !empty($DASHBOARD_FS_PAGE) ? ' class="fs-page"' : ''; ?>>
<?php if (!empty($DASHBOARD_AFTER_BODY)) { echo $DASHBOARD_AFTER_BODY; } ?>
<div class="layout-wrapper layout-2">
    <div class="layout-inner">
        <div id="layout-sidenav" class="layout-sidenav sidenav sidenav-vertical bg-white logo-dark" aria-hidden="true"></div>
        <div class="layout-container">
            <nav class="layout-navbar navbar navbar-expand-lg align-items-lg-center bg-dark container-p-x" id="layout-navbar" aria-hidden="true"></nav>
            <div class="layout-content">
                <div class="container-fluid flex-grow-1" style="padding-top:0;padding-bottom:0;">
<?php include __DIR__ . '/../sidebar.php'; ?>
<div class="row">
<div class="col-12 p-3">
