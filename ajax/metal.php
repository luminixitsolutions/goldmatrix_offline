<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_metal_dashboard_image_schema.php';
require_once __DIR__ . '/../includes/auragold_metal_opening_fields_schema.php';

header('Content-Type: application/json');

$user   = $_SESSION['Admin']['id'] ?? 0;
$action = $_POST['action'] ?? ($_GET['action'] ?? '');
$table  = 'tbl_metal';

auragold_ensure_tbl_metal_dashboard_images($conn);
auragold_ensure_tbl_metal_opening_fields($conn);

$has_img_cols = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_metal', 'dashboard_image_path');
$has_show_dash = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_metal', 'show_on_dashboard');
$has_opening_fields = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_metal', 'product_opening_name');

$metal_delete_dashboard_upload = static function (string $relativePath): void {
    $relativePath = str_replace('\\', '/', $relativePath);
    $relativePath = ltrim($relativePath, '/');
    if ($relativePath === '' || strpos($relativePath, '..') !== false) {
        return;
    }
    if (strpos($relativePath, 'uploads/metal-dashboard/') !== 0) {
        return;
    }
    $full = realpath(__DIR__ . '/../' . $relativePath);
    $base = realpath(__DIR__ . '/../uploads/metal-dashboard');
    if ($full === false || $base === false || strpos($full, $base) !== 0) {
        return;
    }
    if (is_file($full)) {
        @unlink($full);
    }
};

/** @return array{ok:bool, path:string, message:string} */
$metal_save_dashboard_upload = static function (int $metal_id): array {
    if ($metal_id <= 0 || empty($_FILES['dashboard_image']) || !isset($_FILES['dashboard_image']['error'])) {
        return ['ok' => false, 'path' => '', 'message' => ''];
    }
    if ((int) $_FILES['dashboard_image']['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'path' => '', 'message' => 'Upload failed'];
    }
    $tmp = (string) ($_FILES['dashboard_image']['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'path' => '', 'message' => 'Invalid upload'];
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
    $mime = $finfo ? (string) finfo_file($finfo, $tmp) : '';
    if ($finfo) {
        finfo_close($finfo);
    }
    if ($mime === '' || !isset($allowed[$mime])) {
        return ['ok' => false, 'path' => '', 'message' => 'Only JPG, PNG, GIF, WEBP allowed'];
    }
    $ext = $allowed[$mime];
    $maxBytes = 3 * 1024 * 1024;
    $size = filesize($tmp);
    if ($size === false || $size > $maxBytes) {
        return ['ok' => false, 'path' => '', 'message' => 'Image too large (max 3MB)'];
    }
    $dir = __DIR__ . '/../uploads/metal-dashboard';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return ['ok' => false, 'path' => '', 'message' => 'Could not create upload folder'];
    }
    $name = 'metal_' . $metal_id . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $destFs = $dir . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($tmp, $destFs)) {
        return ['ok' => false, 'path' => '', 'message' => 'Could not save file'];
    }
    return ['ok' => true, 'path' => 'uploads/metal-dashboard/' . $name, 'message' => ''];
};

$normalize_ext_url = static function (string $u): string {
    $u = trim($u);
    if ($u === '' || strlen($u) > 1000) {
        return '';
    }
    if (!preg_match('#^https?://#i', $u)) {
        return '';
    }
    return $u;
};

if ($action === 'get') {
    $id = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid id']);
        exit;
    }
    if (!auragold_master_can_mutate_row($conn, $table, $id)) {
        echo json_encode(['status' => 'error', 'message' => 'Access denied']);
        exit;
    }
    $cols = 'id,display_name,hsn_code,system_name';
    if ($has_opening_fields) {
        $cols .= ',product_opening_name,order_no';
    }
    if ($has_img_cols) {
        $cols .= ',dashboard_image_path,dashboard_image_url';
    }
    if ($has_show_dash) {
        $cols .= ',show_on_dashboard';
    }
    $r = getRecord('SELECT ' . $cols . ' FROM tbl_metal WHERE id = ' . $id . ' AND status = 1 LIMIT 1');
    if (!is_array($r)) {
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
        exit;
    }
    echo json_encode(['status' => 'success', 'row' => $r], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($action === 'add') {

    $name   = esc($_POST['display_name'] ?? '');
    $hsn    = esc($_POST['hsn_code'] ?? '');
    $system = esc($_POST['system_name'] ?? '');
    $product_opening_name = esc($_POST['product_opening_name'] ?? '');
    $order_no = isset($_POST['order_no']) ? (int) $_POST['order_no'] : 0;
    if ($order_no < 0) {
        $order_no = 0;
    }
    $ext_url_in = $normalize_ext_url((string) ($_POST['dashboard_image_url'] ?? ''));
    $ext_url_sql = mysqli_real_escape_string($conn, $ext_url_in);
    $clear_img = isset($_POST['clear_dashboard_image']) && (string) $_POST['clear_dashboard_image'] === '1';
    $bid    = auragold_master_branch_id_for_writes($conn, $table);

    if ($name === '') {
        echo json_encode(["status" => "error", "message" => "Display name required"]);
        exit;
    }

    $show_dash_val = 0;
    if ($has_show_dash) {
        $show_dash_val = isset($_POST['show_on_dashboard']) && (string) $_POST['show_on_dashboard'] === '1' ? 1 : 0;
    }

    $ins_fields = ['display_name', 'hsn_code', 'system_name'];
    $ins_vals = ["'$name'", "'$hsn'", "'$system'"];
    if ($has_opening_fields) {
        $ins_fields[] = 'product_opening_name';
        $ins_fields[] = 'order_no';
        $ins_vals[] = "'$product_opening_name'";
        $ins_vals[] = (string) (int) $order_no;
    }
    if ($has_img_cols) {
        $ins_fields[] = 'dashboard_image_url';
        $ins_vals[] = "'" . ($clear_img ? '' : $ext_url_sql) . "'";
    }
    if ($has_show_dash) {
        $ins_fields[] = 'show_on_dashboard';
        $ins_vals[] = (string) (int) $show_dash_val;
    }
    $ins_fields[] = 'branch_id';
    $ins_fields[] = 'created_by';
    $ins_vals[] = "'" . mysqli_real_escape_string($conn, (string) $bid) . "'";
    $ins_vals[] = "'" . mysqli_real_escape_string($conn, (string) $user) . "'";
    mysqli_query(
        $conn,
        'INSERT INTO tbl_metal (' . implode(',', $ins_fields) . ') VALUES (' . implode(',', $ins_vals) . ')'
    );

    $new_id = (int) mysqli_insert_id($conn);

    $saved_path_display = '';
    if ($has_img_cols && !$clear_img && !empty($_FILES['dashboard_image']) && (int) ($_FILES['dashboard_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $up = $metal_save_dashboard_upload($new_id);
        if (!$up['ok'] && $up['message'] !== '') {
            echo json_encode(['status' => 'error', 'message' => $up['message']]);
            exit;
        }
        if ($up['ok'] && $up['path'] !== '') {
            $saved_path_display = $up['path'];
            $pe = mysqli_real_escape_string($conn, $up['path']);
            mysqli_query($conn, "UPDATE tbl_metal SET dashboard_image_path = '$pe' WHERE id = " . $new_id);
        }
    }

    $out = [
        "status" => "success",
        "id" => $new_id,
        "display_name" => $name,
        "hsn_code" => $hsn,
        "system_name" => $system,
    ];
    if ($has_opening_fields) {
        $out['product_opening_name'] = $product_opening_name;
        $out['order_no'] = $order_no;
    }
    if ($has_img_cols) {
        $out['dashboard_image_path'] = $saved_path_display;
        $out['dashboard_image_url'] = $clear_img ? '' : $ext_url_in;
        $out['has_dashboard_thumb'] = ($saved_path_display !== '' || (!$clear_img && $ext_url_in !== ''));
    }
    if ($has_show_dash) {
        $out['show_on_dashboard'] = $show_dash_val;
    }
    echo json_encode($out);
    exit;
}

if ($action === 'update') {

    $id     = (int) ($_POST['id'] ?? 0);
    $name   = esc($_POST['display_name'] ?? '');
    $hsn    = esc($_POST['hsn_code'] ?? '');
    $system = esc($_POST['system_name'] ?? '');
    $product_opening_name = esc($_POST['product_opening_name'] ?? '');
    $order_no = isset($_POST['order_no']) ? (int) $_POST['order_no'] : 0;
    if ($order_no < 0) {
        $order_no = 0;
    }
    $ext_url_in = $normalize_ext_url((string) ($_POST['dashboard_image_url'] ?? ''));
    $ext_url_sql = mysqli_real_escape_string($conn, $ext_url_in);
    $clear_img = isset($_POST['clear_dashboard_image']) && (string) $_POST['clear_dashboard_image'] === '1';

    $opening_sql = '';
    if ($has_opening_fields) {
        $opening_sql = ",product_opening_name='$product_opening_name',order_no=" . (int) $order_no;
    }

    $show_dash_sql = '';
    if ($has_show_dash) {
        $sd = isset($_POST['show_on_dashboard']) && (string) $_POST['show_on_dashboard'] === '1' ? 1 : 0;
        $show_dash_sql = ',show_on_dashboard=' . (int) $sd;
    }

    if ($id <= 0 || $name === '') {
        echo json_encode(["status" => "error", "message" => "Invalid data"]);
        exit;
    }

    if (!auragold_master_can_mutate_row($conn, $table, $id)) {
        echo json_encode(["status"=>"error","message"=>"Access denied for this branch"]);
        exit;
    }

    $prev_path = '';
    if ($has_img_cols) {
        $prev = @getRecord('SELECT dashboard_image_path FROM tbl_metal WHERE id = ' . $id . ' LIMIT 1');
        if (is_array($prev)) {
            $prev_path = trim((string) ($prev['dashboard_image_path'] ?? ''));
        }
    }

    if ($has_img_cols && $clear_img) {
        if ($prev_path !== '') {
            $metal_delete_dashboard_upload($prev_path);
        }
        mysqli_query($conn, "
            UPDATE tbl_metal
            SET display_name='$name',
                hsn_code='$hsn',
                system_name='$system',
                dashboard_image_path=NULL,
                dashboard_image_url=NULL,
                modified_by='$user'
                $opening_sql
                $show_dash_sql
            WHERE id='$id'
        ");
    } elseif ($has_img_cols) {
        $path_sql = mysqli_real_escape_string($conn, $prev_path);
        if (!empty($_FILES['dashboard_image']) && (int) ($_FILES['dashboard_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $up = $metal_save_dashboard_upload($id);
            if (!$up['ok']) {
                echo json_encode(['status' => 'error', 'message' => $up['message'] !== '' ? $up['message'] : 'Upload failed']);
                exit;
            }
            if ($up['ok'] && $up['path'] !== '') {
                if ($prev_path !== '' && $prev_path !== $up['path']) {
                    $metal_delete_dashboard_upload($prev_path);
                }
                $path_sql = mysqli_real_escape_string($conn, $up['path']);
            }
        }
        mysqli_query($conn, "
            UPDATE tbl_metal
            SET display_name='$name',
                hsn_code='$hsn',
                system_name='$system',
                dashboard_image_path='$path_sql',
                dashboard_image_url='$ext_url_sql',
                modified_by='$user'
                $opening_sql
                $show_dash_sql
            WHERE id='$id'
        ");
    } else {
        mysqli_query($conn, "
            UPDATE tbl_metal
            SET display_name='$name',
                hsn_code='$hsn',
                system_name='$system',
                modified_by='$user'
                $opening_sql
                $show_dash_sql
            WHERE id='$id'
        ");
    }

    $out = [
        "status"=>"success",
        "id"=>$id,
        "display_name"=>$name,
        "hsn_code"=>$hsn,
        "system_name"=>$system
    ];
    if ($has_opening_fields) {
        $out['product_opening_name'] = $product_opening_name;
        $out['order_no'] = $order_no;
    }
    if ($has_img_cols) {
        $r2 = @getRecord('SELECT dashboard_image_path, dashboard_image_url FROM tbl_metal WHERE id = ' . $id . ' LIMIT 1');
        $dp = is_array($r2) ? trim((string) ($r2['dashboard_image_path'] ?? '')) : '';
        $du = is_array($r2) ? trim((string) ($r2['dashboard_image_url'] ?? '')) : '';
        $out['dashboard_image_path'] = $dp;
        $out['dashboard_image_url'] = $du;
        $out['has_dashboard_thumb'] = ($dp !== '' || $du !== '');
    }
    if ($has_show_dash) {
        $sf = @getRecord('SELECT show_on_dashboard FROM tbl_metal WHERE id = ' . $id . ' LIMIT 1');
        $out['show_on_dashboard'] = is_array($sf) ? (int) ($sf['show_on_dashboard'] ?? 0) : 0;
    }
    echo json_encode($out);
    exit;
}

if ($action === 'delete') {

    $id = (int) ($_POST['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(["status" => "error", "message" => "Invalid id"]);
        exit;
    }

    if (!auragold_master_can_mutate_row($conn, $table, $id)) {
        echo json_encode(["status" => "error", "message" => "Access denied for this branch"]);
        exit;
    }

    if ($has_img_cols) {
        $prev = @getRecord('SELECT dashboard_image_path FROM tbl_metal WHERE id = ' . $id . ' LIMIT 1');
        if (is_array($prev)) {
            $p = trim((string) ($prev['dashboard_image_path'] ?? ''));
            if ($p !== '') {
                $metal_delete_dashboard_upload($p);
            }
        }
    }

    mysqli_query($conn, "
        UPDATE tbl_metal
        SET status=0, modified_by='$user'
        WHERE id='$id'
    ");

    echo json_encode(["status" => "success"]);
    exit;
}

echo json_encode(["status"=>"error","message"=>"Invalid action"]);
