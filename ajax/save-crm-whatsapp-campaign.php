<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/crm_whatsapp_schema.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['Admin'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

global $conn;
auragold_ensure_crm_whatsapp_tables($conn);

$caption = isset($_POST['caption']) ? trim((string) $_POST['caption']) : '';
$message = isset($_POST['message']) ? trim((string) $_POST['message']) : '';
$customer_name = isset($_POST['customer_name']) ? trim((string) $_POST['customer_name']) : '';
$contact_no = isset($_POST['contact_no']) ? trim((string) $_POST['contact_no']) : '';

if ($caption === '') {
    echo json_encode(['status' => 'error', 'message' => 'Caption is required']);
    exit;
}

$upload_base = dirname(__DIR__) . '/uploads/crm_whatsapp';
if (!is_dir($upload_base)) {
    @mkdir(dirname(__DIR__) . '/uploads', 0755, true);
    @mkdir($upload_base, 0755, true);
}

$allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
$ext_ok = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
$max_size = 5 * 1024 * 1024;

$list = [];
if (!empty($_FILES['images'])) {
    $f = $_FILES['images'];
    if (is_array($f['tmp_name'])) {
        foreach ($f['tmp_name'] as $i => $tmp) {
            if ($tmp === '' || ($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }
            $list[] = [
                'tmp_name' => $tmp,
                'name'     => $f['name'][$i] ?? '',
                'type'     => $f['type'][$i] ?? '',
                'error'    => $f['error'][$i] ?? 0,
                'size'     => $f['size'][$i] ?? 0,
            ];
        }
    } elseif (isset($f['tmp_name']) && $f['tmp_name'] !== '' && ($f['error'] ?? 0) === UPLOAD_ERR_OK) {
        $list[] = $f;
    }
}

$branch_id = null;
if (!empty($_SESSION['working_branch_id'])) {
    $branch_id = (int) $_SESSION['working_branch_id'];
} elseif (!empty($_SESSION['branch_id'])) {
    $branch_id = (int) $_SESSION['branch_id'];
}

$created_by = 0;
if (!empty($_SESSION['user_id'])) {
    $created_by = (int) $_SESSION['user_id'];
}

$caption_esc = mysqli_real_escape_string($conn, $caption);
$message_esc = mysqli_real_escape_string($conn, $message);
$customer_esc = mysqli_real_escape_string($conn, $customer_name);
$contact_esc = mysqli_real_escape_string($conn, $contact_no);
$branch_sql = $branch_id !== null && $branch_id > 0 ? (string) (int) $branch_id : 'NULL';
$created_by_sql = $created_by > 0 ? (string) (int) $created_by : 'NULL';

$sql = "INSERT INTO tbl_crm_whatsapp_campaigns (caption, customer_name, contact_no, message_body, status, branch_id, created_by, created_at)
        VALUES ('$caption_esc', '$customer_esc', '$contact_esc', '$message_esc', 1, $branch_sql, $created_by_sql, NOW())";
if (!mysqli_query($conn, $sql)) {
    echo json_encode(['status' => 'error', 'message' => 'Could not save campaign']);
    exit;
}

$campaign_id = (int) mysqli_insert_id($conn);
$saved_paths = [];
$sort = 0;

foreach ($list as $file) {
    if (($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
        continue;
    }
    $size = (int) ($file['size'] ?? 0);
    if ($size > $max_size) {
        continue;
    }
    $type = strtolower((string) ($file['type'] ?? ''));
    if (!in_array($type, $allowed, true)) {
        continue;
    }
    $name = (string) ($file['name'] ?? '');
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, $ext_ok, true)) {
        continue;
    }
    $safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '', basename($name));
    if ($safe_name === '') {
        $safe_name = 'image.' . $ext;
    }
    $unique = date('YmdHis') . '_' . substr(md5(uniqid((string) mt_rand(), true)), 0, 10) . '_' . $safe_name;
    $dest = $upload_base . '/' . $unique;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        continue;
    }
    $relative = 'uploads/crm_whatsapp/' . $unique;
    $path_esc = mysqli_real_escape_string($conn, $relative);
    mysqli_query(
        $conn,
        "INSERT INTO tbl_crm_whatsapp_campaign_images (campaign_id, image_path, sort_order, created_at)
         VALUES ($campaign_id, '$path_esc', $sort, NOW())"
    );
    $saved_paths[] = $relative;
    $sort++;
}

echo json_encode([
    'status'   => 'ok',
    'id'       => $campaign_id,
    'message'  => 'Campaign saved',
    'images'   => count($saved_paths),
]);
