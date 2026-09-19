<?php
/**
 * Bulk Excel import for product-opening.php — creates product master + characteristics.
 * POST: excel_file (.xlsx / .xls)
 */
session_start();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

if (empty($_SESSION['Admin']['id']) && empty($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired']);
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_product_branch_login_context.php';
require_once __DIR__ . '/../includes/product_opening_excel_import.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

if (empty($_FILES['excel_file']['tmp_name']) || !is_uploaded_file($_FILES['excel_file']['tmp_name'])) {
    echo json_encode(['status' => 'error', 'message' => 'Please upload an Excel file (.xlsx)']);
    exit;
}

$ext = strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['xlsx', 'xls'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Only .xlsx or .xls files are supported']);
    exit;
}

if ($ext === 'xlsx' && !class_exists('ZipArchive', false)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'PHP zip extension is required for .xlsx. Enable extension=zip in php.ini and restart Apache, or use an .xls file.',
    ]);
    exit;
}

@set_time_limit(0);
@ini_set('memory_limit', '512M');

$branchIds = [];
$bid = auragold_get_logged_in_branch_id();
if ($bid > 0) {
    $branchIds[] = $bid;
} else {
    $main = getRecordMaster('SELECT id FROM tbl_branches WHERE IFNULL(main_branch_id,0)=0 AND status = 1 ORDER BY id ASC LIMIT 1');
    if ($main) {
        $branchIds[] = (int) $main['id'];
    }
}
if (empty($branchIds)) {
    echo json_encode(['status' => 'error', 'message' => 'Could not resolve branch for import. Log in with a branch selected.']);
    exit;
}

$ctx = auragold_product_opening_mysqli_for_login($conn);
$ctxOk = !empty($ctx['ok']) && $ctx['link'] instanceof mysqli;
$saveConn = $ctxOk ? $ctx['link'] : $conn;
$closeAfter = $ctxOk && !empty($ctx['close_after']);
if (!$ctxOk) {
    $msg = isset($ctx['message']) && (string) $ctx['message'] !== ''
        ? (string) $ctx['message']
        : 'Could not determine which database to save products. Check branch setup.';
    echo json_encode(['status' => 'error', 'message' => $msg]);
    exit;
}

try {
    $spreadsheet = IOFactory::load($_FILES['excel_file']['tmp_name']);
} catch (Throwable $e) {
    if ($closeAfter) {
        mysqli_close($saveConn);
    }
    echo json_encode(['status' => 'error', 'message' => 'Could not read Excel: ' . $e->getMessage()]);
    exit;
}

$sheet = $spreadsheet->getActiveSheet();
$highestRow = (int) $sheet->getHighestDataRow();
try {
    $highestCol = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
} catch (Throwable $e) {
    if ($closeAfter) {
        mysqli_close($saveConn);
    }
    echo json_encode(['status' => 'error', 'message' => 'Could not read sheet columns.']);
    exit;
}

if ($highestRow < 2) {
    if ($closeAfter) {
        mysqli_close($saveConn);
    }
    echo json_encode(['status' => 'error', 'message' => 'Excel has no data rows.']);
    exit;
}

if ($highestRow > 5001) {
    if ($closeAfter) {
        mysqli_close($saveConn);
    }
    echo json_encode(['status' => 'error', 'message' => 'Maximum 5000 rows per upload. Split the file and import in batches.']);
    exit;
}

try {
    $parsed = auragold_po_excel_parse_rows($sheet, $saveConn, $highestRow, $highestCol);
} catch (Throwable $e) {
    if ($closeAfter) {
        mysqli_close($saveConn);
    }
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

$groups = $parsed['groups'] ?? [];
$rowSkipped = $parsed['skipped'] ?? [];
$parseErrors = $parsed['errors'] ?? [];

if (empty($groups)) {
    if ($closeAfter) {
        mysqli_close($saveConn);
    }
    $msg = 'No importable rows found. Each row needs Product Name and Metal.';
    if (!empty($rowSkipped)) {
        $msg .= ' ' . count($rowSkipped) . ' row(s) skipped (missing name or metal).';
    }
    echo json_encode(['status' => 'error', 'message' => $msg]);
    exit;
}

$result = auragold_po_excel_import_groups($saveConn, $groups, $branchIds);

if ($closeAfter) {
    mysqli_close($saveConn);
}

$allErrors = array_merge($parseErrors, $result['errors'] ?? []);
$errorPreview = array_slice($allErrors, 0, 15);
$imported = (int) ($result['imported'] ?? 0);
$skipped = (int) ($result['skipped'] ?? 0) + count($rowSkipped);

if ($imported <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No products were imported.' . (!empty($errorPreview) ? ' ' . implode(' ', $errorPreview) : ''),
        'imported' => 0,
        'skipped' => $skipped,
        'errors' => $allErrors,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$message = $imported . ' product(s) imported successfully.';
if ($skipped > 0) {
    $message .= ' ' . $skipped . ' row(s)/product(s) skipped.';
}

echo json_encode([
    'status' => 'success',
    'message' => $message,
    'imported' => $imported,
    'skipped' => $skipped,
    'errors' => $allErrors,
    'error_preview' => $errorPreview,
], JSON_UNESCAPED_UNICODE);
