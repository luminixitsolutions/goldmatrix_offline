<?php
session_start();
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['Admin'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$action = $_POST['action'] ?? '';

function acc_tables_exist($conn) {
    $t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_accounting_financial_years'");
    if (!$t || mysqli_num_rows($t) === 0) {
        return false;
    }
    mysqli_free_result($t);
    return true;
}

if (!acc_tables_exist($conn)) {
    echo json_encode(['status' => 'error', 'message' => 'Accounting tables missing. Run admin/sql/create_tbl_accounting_masters.sql on this database.']);
    exit;
}

if ($action === 'save_calculation') {
    $modeId = (int) ($_POST['mode_id'] ?? 0);
    $amountDec = max(0, min(8, (int) ($_POST['amount_decimal'] ?? 2)));
    $amountRound = !empty($_POST['amount_round']) ? 1 : 0;
    $weightDec = max(0, min(8, (int) ($_POST['weight_decimal'] ?? 3)));
    $weightRound = !empty($_POST['weight_round']) ? 1 : 0;
    $percentDec = max(0, min(8, (int) ($_POST['percent_decimal'] ?? 3)));
    $percentRound = !empty($_POST['percent_round']) ? 1 : 0;

    if ($modeId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Please select a mode from the list.']);
        exit;
    }

    $chk = getRecord("SELECT id FROM tbl_accounting_master_modes WHERE id = $modeId AND status = 1 LIMIT 1");
    if (!$chk) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid mode']);
        exit;
    }

    $exists = getRecord('SELECT id FROM tbl_accounting_calculation_settings ORDER BY id ASC LIMIT 1');
    if ($exists && !empty($exists['id'])) {
        $id = (int) $exists['id'];
        mysqli_query($conn, "
            UPDATE tbl_accounting_calculation_settings SET
                mode_id = $modeId,
                amount_decimal = $amountDec,
                amount_round = $amountRound,
                weight_decimal = $weightDec,
                weight_round = $weightRound,
                percent_decimal = $percentDec,
                percent_round = $percentRound
            WHERE id = $id
        ");
    } else {
        mysqli_query($conn, "
            INSERT INTO tbl_accounting_calculation_settings
            (mode_id, amount_decimal, amount_round, weight_decimal, weight_round, percent_decimal, percent_round)
            VALUES ($modeId, $amountDec, $amountRound, $weightDec, $weightRound, $percentDec, $percentRound)
        ");
    }

    echo json_encode(['status' => 'success']);
    exit;
}

if ($action === 'save_financial_years') {
    $raw = $_POST['years_json'] ?? '[]';
    $rows = json_decode($raw, true);
    if (!is_array($rows)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
        exit;
    }

    if (count($rows) > 0) {
        $activeCount = 0;
        foreach ($rows as $r) {
            if (!empty($r['is_active'])) {
                $activeCount++;
            }
        }
        if ($activeCount !== 1) {
            echo json_encode(['status' => 'error', 'message' => 'Select exactly one active financial year.']);
            exit;
        }
    }

    mysqli_begin_transaction($conn);
    try {
        $keptIds = [];
        foreach ($rows as $r) {
            $start = trim((string) ($r['start_date'] ?? ''));
            $end = trim((string) ($r['end_date'] ?? ''));
            if ($start === '' || $end === '') {
                throw new Exception('Start and end dates are required for each row.');
            }
            if ($start > $end) {
                throw new Exception('Start date must be on or before end date.');
            }
            $isActive = !empty($r['is_active']) ? 1 : 0;
            $rid = isset($r['id']) ? (int) $r['id'] : 0;

            $startEsc = esc($start);
            $endEsc = esc($end);

            if ($rid > 0) {
                $exists = getRecord("SELECT id FROM tbl_accounting_financial_years WHERE id = $rid AND status = 1 LIMIT 1");
                if ($exists) {
                    mysqli_query($conn, "
                        UPDATE tbl_accounting_financial_years SET
                            start_date = '$startEsc',
                            end_date = '$endEsc',
                            is_active = $isActive,
                            updated_at = NOW()
                        WHERE id = $rid AND status = 1
                    ");
                    $keptIds[] = $rid;
                    continue;
                }
            }

            mysqli_query($conn, "
                INSERT INTO tbl_accounting_financial_years (start_date, end_date, is_active, status, created_at)
                VALUES ('$startEsc', '$endEsc', $isActive, 1, NOW())
            ");
            $newId = (int) mysqli_insert_id($conn);
            if ($newId > 0) {
                $keptIds[] = $newId;
            }
        }

        if (count($keptIds) > 0) {
            $inList = implode(',', array_map('intval', $keptIds));
            mysqli_query($conn, "UPDATE tbl_accounting_financial_years SET status = 0, updated_at = NOW() WHERE status = 1 AND id NOT IN ($inList)");
        } else {
            mysqli_query($conn, "UPDATE tbl_accounting_financial_years SET status = 0, updated_at = NOW() WHERE status = 1");
        }

        mysqli_commit($conn);
        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
