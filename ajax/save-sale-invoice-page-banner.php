<?php
session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auragold_sale_invoice_page_banner.php';

header('Content-Type: application/json');

if (empty($_SESSION['Admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$upload_dir = dirname(__DIR__) . '/uploads/sale-invoice/';
$bid = function_exists('auragold_settings_branch_id') ? (int) auragold_settings_branch_id() : 0;
if ($bid > 0) {
    $upload_dir .= 'branch_' . $bid . '/';
}

if (!empty($_POST['delete'])) {
    if (is_dir($upload_dir)) {
        foreach (glob($upload_dir . 'page-banner.*') ?: [] as $old) {
            if (is_file($old)) {
                @unlink($old);
            }
        }
    }
    if (function_exists('auragold_ensure_branch_id_on_settings_tables')) {
        auragold_ensure_branch_id_on_settings_tables($conn);
    }
    if (!function_exists('saveInvoicePrintSetting') || !saveInvoicePrintSetting(auragold_sale_invoice_page_banner_setting_key(), auragold_sale_invoice_page_banner_hidden_value(), 'default')) {
        echo json_encode(['success' => false, 'message' => 'Could not remove banner']);
        exit;
    }
    echo json_encode([
        'success' => true,
        'message' => 'Banner deleted',
        'hidden' => true,
    ]);
    exit;
}

if (empty($_FILES['banner']['tmp_name']) || !is_uploaded_file($_FILES['banner']['tmp_name'])) {
    echo json_encode(['success' => false, 'message' => 'No image selected']);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($_FILES['banner']['tmp_name']);
$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($mime, $allowed, true)) {
    echo json_encode(['success' => false, 'message' => 'Only JPEG, PNG, GIF, or WebP images are allowed']);
    exit;
}

$ext = 'png';
if (preg_match('/^image\/(jpeg|png|gif|webp)$/', $mime, $m)) {
    $ext = $m[1] === 'jpeg' ? 'jpg' : $m[1];
}

if (!is_dir($upload_dir) && !@mkdir($upload_dir, 0755, true)) {
    echo json_encode(['success' => false, 'message' => 'Could not create upload folder']);
    exit;
}

$dest = $upload_dir . 'page-banner.' . $ext;
if (!move_uploaded_file($_FILES['banner']['tmp_name'], $dest)) {
    echo json_encode(['success' => false, 'message' => 'Could not save uploaded image']);
    exit;
}

$relative = 'uploads/sale-invoice/';
if ($bid > 0) {
    $relative .= 'branch_' . $bid . '/';
}
$relative .= 'page-banner.' . $ext;

if (function_exists('auragold_ensure_branch_id_on_settings_tables')) {
    auragold_ensure_branch_id_on_settings_tables($conn);
}

if (!function_exists('saveInvoicePrintSetting') || !saveInvoicePrintSetting(auragold_sale_invoice_page_banner_setting_key(), $relative, 'default')) {
    echo json_encode(['success' => false, 'message' => 'Image saved but setting could not be updated']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Banner updated',
    'path' => $relative,
    'url' => $relative . '?v=' . time(),
]);
