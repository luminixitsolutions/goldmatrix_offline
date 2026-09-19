<?php
/**
 * Return a barcode string not yet used in tbl_old_jewelry_stock (TRIM match).
 * POST: base — starting value (usually Tag No.); if free, returned as-is.
 */
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'message' => 'Invalid request']);
    exit;
}

if (empty($_SESSION['Admin']['id']) && empty($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'message' => 'Session expired. Please log in again.']);
    exit;
}

$tst = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_stock'");
if (!$tst || mysqli_num_rows($tst) === 0) {
    if ($tst) {
        mysqli_free_result($tst);
    }
    echo json_encode(['ok' => false, 'message' => 'Old Jewellery stock table is not installed.']);
    exit;
}
mysqli_free_result($tst);

$base = isset($_POST['base']) ? trim((string) $_POST['base']) : '';
if ($base === '') {
    echo json_encode(['ok' => false, 'message' => 'Base barcode is required.']);
    exit;
}

function oj_barcode_taken($conn, $barcode)
{
    $esc = mysqli_real_escape_string($conn, $barcode);
    $row = getRecord("SELECT id FROM tbl_old_jewelry_stock WHERE TRIM(barcode) = '" . $esc . "' LIMIT 1");
    return $row && !empty($row['id']);
}

if (!oj_barcode_taken($conn, $base)) {
    echo json_encode(['ok' => true, 'barcode' => $base]);
    exit;
}

for ($i = 2; $i < 10000; $i++) {
    $candidate = $base . '-OJ' . $i;
    if (strlen($candidate) > 64) {
        break;
    }
    if (!oj_barcode_taken($conn, $candidate)) {
        echo json_encode(['ok' => true, 'barcode' => $candidate]);
        exit;
    }
}

$fallback = $base . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
if (strlen($fallback) > 64) {
    $fallback = 'OJ-' . substr(bin2hex(random_bytes(5)), 0, 10);
}
$guard = 0;
while (oj_barcode_taken($conn, $fallback) && $guard < 50) {
    $fallback = 'OJ-' . substr(bin2hex(random_bytes(5)), 0, 10);
    $guard++;
}

echo json_encode(['ok' => true, 'barcode' => $fallback]);
