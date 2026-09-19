<?php
/**
 * POST excel import for customer-details-import.php
 */
session_start();

use PhpOffice\PhpSpreadsheet\IOFactory;

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

if (empty($_SESSION['Admin']['id']) && empty($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired']);
    exit;
}

try {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../includes/auragold_require_login.php';
    require_once __DIR__ . '/../includes/customer_details_excel_import.php';
    require_once __DIR__ . '/../vendor/autoload.php';

    auragold_require_login_or_exit();

    if (empty($_FILES['excel_file']['tmp_name']) || !is_uploaded_file($_FILES['excel_file']['tmp_name'])) {
        echo json_encode(['status' => 'error', 'message' => 'Please upload an Excel file (.xlsx or .xls)']);
        exit;
    }

    $ext = strtolower(pathinfo((string) $_FILES['excel_file']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'xls'], true)) {
        echo json_encode(['status' => 'error', 'message' => 'Only .xlsx or .xls files are supported']);
        exit;
    }

    if ($ext === 'xlsx' && !class_exists('ZipArchive', false)) {
        echo json_encode(['status' => 'error', 'message' => 'PHP zip extension is required for .xlsx files']);
        exit;
    }

    @set_time_limit(0);

    $defaultCustomerTypeId = 0;
    $ctypeRow = getRecord("SELECT id FROM tbl_customer_types WHERE status = 1 AND LOWER(TRIM(name)) = 'customer' LIMIT 1");
    if (is_array($ctypeRow)) {
        $defaultCustomerTypeId = (int) ($ctypeRow['id'] ?? 0);
    }
    if ($defaultCustomerTypeId <= 0) {
        $ctypeRow2 = getRecord('SELECT id FROM tbl_customer_types WHERE status = 1 ORDER BY id ASC LIMIT 1');
        $defaultCustomerTypeId = is_array($ctypeRow2) ? (int) ($ctypeRow2['id'] ?? 0) : 1;
    }

    $spreadsheet = IOFactory::load($_FILES['excel_file']['tmp_name']);

    $sheet = $spreadsheet->getSheetByName('Customer Details');
    if ($sheet === null) {
        $sheet = $spreadsheet->getActiveSheet();
    }

    $highestRow = (int) $sheet->getHighestDataRow();
    if ($highestRow < 2) {
        echo json_encode(['status' => 'error', 'message' => 'Excel has no data rows']);
        exit;
    }
    if ($highestRow > 5001) {
        echo json_encode(['status' => 'error', 'message' => 'Maximum 5000 rows per upload']);
        exit;
    }

    $headerRow = null;
    $headerRowNum = 1;
    for ($tryRow = 1; $tryRow <= 5; ++$tryRow) {
        $candidate = [];
        for ($c = 1; $c <= 50; ++$c) {
            $candidate[] = auragold_account_ledger_excel_cell_value(
                auragold_account_ledger_excel_ws_cell($sheet, $c, $tryRow)
            );
        }
        $tryMap = auragold_customer_details_excel_col_map($candidate);
        if (isset($tryMap['name'])) {
            $headerRow = $candidate;
            $headerRowNum = $tryRow;
            break;
        }
    }
    if ($headerRow === null) {
        echo json_encode(['status' => 'error', 'message' => 'Missing Name column. Download Sample Excel for the correct headers.']);
        exit;
    }
    $colMap = auragold_customer_details_excel_col_map($headerRow);
    $forceCreate = !empty($_POST['create_new']);

    $nameCol1 = isset($colMap['name']) ? ((int) $colMap['name'] + 1) : 1;
    $firstCol1 = isset($colMap['first_name']) ? ((int) $colMap['first_name'] + 1) : 0;
    $lastReal = $headerRowNum;
    for ($r = $highestRow; $r > $headerRowNum; --$r) {
        $n = trim(auragold_account_ledger_excel_cell_value(auragold_account_ledger_excel_ws_cell($sheet, $nameCol1, $r)));
        $f = $firstCol1 > 0
            ? trim(auragold_account_ledger_excel_cell_value(auragold_account_ledger_excel_ws_cell($sheet, $firstCol1, $r)))
            : '';
        if ($n !== '' || $f !== '') {
            $lastReal = $r;
            break;
        }
    }
    $highestRow = $lastReal;

    $created = 0;
    $updated = 0;
    $failed = 0;
    $skipped = 0;
    $errors = [];

    mysqli_begin_transaction($conn);
    try {
        for ($r = $headerRowNum + 1; $r <= $highestRow; ++$r) {
            $parsed = [];
            foreach ($colMap as $key => $idx) {
                $parsed[$key] = auragold_account_ledger_excel_cell_value(
                    auragold_account_ledger_excel_ws_cell($sheet, $idx + 1, $r)
                );
            }
            $parsed['name'] = auragold_customer_details_excel_resolve_name($parsed);
            $parsed['mobile_no'] = auragold_customer_details_excel_normalize_mobile((string) ($parsed['mobile_no'] ?? ''));
            if ($parsed['name'] === '') {
                ++$skipped;
                continue;
            }
            $res = auragold_customer_details_import_row($conn, $parsed, $defaultCustomerTypeId, $forceCreate);
            if (empty($res['ok'])) {
                ++$failed;
                $errors[] = ($res['name'] !== '' ? $res['name'] . ': ' : '') . ($res['message'] ?? 'Failed');
                continue;
            }
            if (($res['action'] ?? '') === 'created') {
                ++$created;
            } else {
                ++$updated;
            }
        }
        mysqli_commit($conn);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        echo json_encode(['status' => 'error', 'message' => 'Import failed: ' . $e->getMessage()]);
        exit;
    }

    $msg = "Import complete: {$created} created, {$updated} updated";
    if ($failed > 0) {
        $msg .= ", {$failed} failed";
    }
    if ($skipped > 0 && ($created + $updated + $failed) === 0) {
        $msg .= ". No Name/First Name found in data rows. Fill column A (Name).";
    }
    if ($created === 0 && $updated > 0 && !$forceCreate) {
        $msg .= ". Those names/mobiles already exist, so they were updated. Tick \"Always create new customers\" to insert new records.";
    }

    echo json_encode([
        'status'  => $failed > 0 && ($created + $updated) === 0 ? 'error' : 'success',
        'message' => $msg,
        'created' => $created,
        'updated' => $updated,
        'failed'  => $failed,
        'skipped' => $skipped,
        'errors'  => array_slice($errors, 0, 20),
    ]);
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        @mysqli_rollback($conn);
    }
    echo json_encode(['status' => 'error', 'message' => 'Import failed: ' . $e->getMessage()]);
}
