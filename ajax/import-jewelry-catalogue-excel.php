<?php
/**
 * Bulk Excel import for jewellery catalogue — creates tbl_jewelry_catalogue rows.
 * POST: excel_file (.xlsx / .xls)
 */
session_start();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

if (empty($_SESSION['Admin']['id']) && empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired']);
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/jewelry_catalogue_excel_import.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

if (empty($_FILES['excel_file']['tmp_name']) || !is_uploaded_file($_FILES['excel_file']['tmp_name'])) {
    echo json_encode(['success' => false, 'message' => 'Please upload an Excel file (.xlsx)']);
    exit;
}

$ext = strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['xlsx', 'xls'], true)) {
    echo json_encode(['success' => false, 'message' => 'Only .xlsx or .xls files are supported']);
    exit;
}

if ($ext === 'xlsx' && !class_exists('ZipArchive', false)) {
    echo json_encode([
        'success' => false,
        'message' => 'PHP zip extension is required for .xlsx. Enable extension=zip in php.ini and restart Apache, or use an .xls file.',
    ]);
    exit;
}

@set_time_limit(0);
@ini_set('memory_limit', '512M');

try {
    $spreadsheet = IOFactory::load($_FILES['excel_file']['tmp_name']);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Could not read Excel file: ' . $e->getMessage()]);
    exit;
}

$sheet = $spreadsheet->getSheet(0);
$highestRow = (int) $sheet->getHighestDataRow();
$highestCol = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

if ($highestRow < 2) {
    echo json_encode(['success' => false, 'message' => 'Excel file has no data rows. Use the sample template.']);
    exit;
}

try {
    $parsed = auragold_jcat_excel_parse_rows($sheet, $conn, $highestRow, $highestCol);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

$parseErrors = $parsed['errors'] ?? [];
$rows = $parsed['rows'] ?? [];

if ($rows === []) {
    $msg = 'No valid catalogue rows found in the file.';
    if ($parseErrors !== []) {
        $msg .= ' ' . implode(' ', array_slice($parseErrors, 0, 3));
    }
    echo json_encode([
        'success' => false,
        'message' => $msg,
        'errors' => $parseErrors,
    ]);
    exit;
}

$result = auragold_jcat_excel_import_rows($conn, $rows);
$allErrors = array_merge($parseErrors, $result['errors'] ?? []);
$imported = (int) ($result['imported'] ?? 0);

echo json_encode([
    'success' => $imported > 0,
    'message' => (string) ($result['message'] ?? ''),
    'imported' => $imported,
    'failed' => (int) ($result['failed'] ?? 0),
    'errors' => $allErrors,
], JSON_UNESCAPED_UNICODE);
