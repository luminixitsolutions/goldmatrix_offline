<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_barcode_scan_log_schema.php';

header('Content-Type: application/json; charset=utf-8');

function bsl_json_out(array $payload): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['Admin'])) {
    bsl_json_out(['success' => false, 'message' => 'Unauthorized']);
}

if (empty($conn) || !($conn instanceof mysqli)) {
    bsl_json_out(['success' => false, 'message' => 'Database unavailable']);
}

auragold_ensure_tbl_barcode_scan_log($conn);

$raw = file_get_contents('php://input');
$in = [];
if ($raw !== false && trim($raw) !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $in = $decoded;
    }
}
if (!$in && !empty($_POST)) {
    $in = $_POST;
}

$scan_code = trim((string) ($in['scan_code'] ?? $in['barcode'] ?? ''));
if ($scan_code === '') {
    bsl_json_out(['success' => false, 'message' => 'Scan code is required']);
}

$scan_status = strtolower(trim((string) ($in['scan_status'] ?? 'matched')));
if ($scan_status !== 'unknown') {
    $scan_status = 'matched';
}

$user_id = isset($_SESSION['Admin']['id']) ? (int) $_SESSION['Admin']['id'] : 0;
$user_name = '';
if (!empty($_SESSION['Admin']['name'])) {
    $user_name = trim((string) $_SESSION['Admin']['name']);
} elseif (!empty($_SESSION['Admin']['username'])) {
    $user_name = trim((string) $_SESSION['Admin']['username']);
}

$branch_id = 0;
if (function_exists('auragold_effective_branch_id')) {
    $branch_id = (int) auragold_effective_branch_id();
} elseif (!empty($_SESSION['working_branch_id'])) {
    $branch_id = (int) $_SESSION['working_branch_id'];
}

$now = new DateTime('now');
$scan_date = $now->format('Y-m-d');
$scan_time = $now->format('H:i:s');
$scanned_at = $now->format('Y-m-d H:i:s');

$str = static function ($v, int $max = 255) use ($conn): string {
    $s = trim((string) $v);
    if (strlen($s) > $max) {
        $s = substr($s, 0, $max);
    }
    return mysqli_real_escape_string($conn, $s);
};

$num = static function ($v): string {
    if ($v === null || $v === '') {
        return 'NULL';
    }
    $n = is_numeric($v) ? (float) $v : null;
    if ($n === null) {
        return 'NULL';
    }
    return (string) $n;
};

$barcode = $str($in['barcode'] ?? ($scan_status === 'matched' ? $scan_code : ''));
$rfid_code = $str($in['rfid_code'] ?? '');
$product_code = $str($in['product_code'] ?? '', 64);
$product_name = $str($in['product_name'] ?? '');
$article = $str($in['article'] ?? '');
$metal_name = $str($in['metal_name'] ?? $in['metal'] ?? '');
$branch_name = $str($in['branch_name'] ?? $in['branch'] ?? '');
$location = $str($in['location'] ?? '');
$voucher_type = $str($in['voucher_type'] ?? '', 128);
$invoice_no = $str($in['invoice_no'] ?? '', 128);
$scan_code_esc = $str($scan_code, 128);
$scan_status_esc = $str($scan_status, 16);
$user_name_esc = $str($user_name, 191);

$sql = "INSERT INTO tbl_barcode_scan_log (
    branch_id, user_id, user_name, scan_code, scan_status,
    barcode, rfid_code, product_code, product_name, article,
    metal_name, branch_name, location, qty, gross_wt, net_wt, final_wt,
    voucher_type, invoice_no, scan_date, scan_time, scanned_at
) VALUES (
    " . ($branch_id > 0 ? (int) $branch_id : 'NULL') . ",
    " . ($user_id > 0 ? (int) $user_id : 'NULL') . ",
    " . ($user_name_esc !== '' ? "'$user_name_esc'" : 'NULL') . ",
    '$scan_code_esc', '$scan_status_esc',
    " . ($barcode !== '' ? "'$barcode'" : 'NULL') . ",
    " . ($rfid_code !== '' ? "'$rfid_code'" : 'NULL') . ",
    " . ($product_code !== '' ? "'$product_code'" : 'NULL') . ",
    " . ($product_name !== '' ? "'$product_name'" : 'NULL') . ",
    " . ($article !== '' ? "'$article'" : 'NULL') . ",
    " . ($metal_name !== '' ? "'$metal_name'" : 'NULL') . ",
    " . ($branch_name !== '' ? "'$branch_name'" : 'NULL') . ",
    " . ($location !== '' ? "'$location'" : 'NULL') . ",
    " . $num($in['qty'] ?? null) . ",
    " . $num($in['gross_wt'] ?? null) . ",
    " . $num($in['net_wt'] ?? null) . ",
    " . $num($in['final_wt'] ?? null) . ",
    " . ($voucher_type !== '' ? "'$voucher_type'" : 'NULL') . ",
    " . ($invoice_no !== '' ? "'$invoice_no'" : 'NULL') . ",
    '$scan_date', '$scan_time', '$scanned_at'
)";

if (!@mysqli_query($conn, $sql)) {
    bsl_json_out(['success' => false, 'message' => 'Could not save scan log']);
}

bsl_json_out([
    'success' => true,
    'id' => (int) mysqli_insert_id($conn),
    'scan_date' => date('d-m-Y', strtotime($scan_date)),
    'scan_time' => $scan_time,
    'scanned_at' => $scanned_at,
]);
