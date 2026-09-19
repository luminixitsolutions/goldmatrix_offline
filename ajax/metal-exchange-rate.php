<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../includes/auragold_metal_exchange_rate_schema.php';

header('Content-Type: application/json');

$user = $_SESSION['Admin']['id'] ?? 0;
$action = $_POST['action'] ?? '';
$table = 'tbl_metal_exchange_rate';

if (!auragold_ensure_metal_exchange_rate_table($conn)) {
    echo json_encode(['status' => 'error', 'message' => 'Metal exchange rate table unavailable']);
    exit;
}

if ($action === 'lookup') {
    $currencyId = (int) ($_POST['currency_id'] ?? 0);
    $unitId = (int) ($_POST['unit_id'] ?? 0);
    $metalId = (int) ($_POST['metal_id'] ?? 0);
    $out = [
        'status' => 'success',
        'currency_rate' => '1',
        'unit_conversion_rate' => '31.1035',
        'ounce_rate' => '0',
    ];
    if ($currencyId > 0) {
        $suffix = auragold_master_list_sql_suffix($conn, 'tbl_currency_exchange_rate', 'branch_id');
        $row = getRecord(
            "SELECT rate FROM tbl_currency_exchange_rate
             WHERE currency_id = $currencyId AND status = 1 $suffix
             ORDER BY id DESC LIMIT 1"
        );
        if ($row && isset($row['rate'])) {
            $out['currency_rate'] = (string) $row['rate'];
        }
    }
    if ($unitId > 0) {
        $suffix = auragold_master_list_sql_suffix($conn, 'tbl_unit_conversion', 'uc.branch_id');
        $row = getRecord(
            "SELECT uc.conversion_rate
             FROM tbl_unit_conversion uc
             WHERE uc.unit_id = $unitId AND uc.status = 1 $suffix
             ORDER BY uc.id DESC LIMIT 1"
        );
        if ($row && isset($row['conversion_rate']) && (float) $row['conversion_rate'] > 0) {
            $out['unit_conversion_rate'] = (string) $row['conversion_rate'];
        }
    }
    if ($metalId > 0 && auragold_tbl_has_column($conn, 'tbl_dashboard_metal_meta', 'ounce_rate')) {
        $rateDate = trim((string) ($_POST['rate_date'] ?? ''));
        if ($rateDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rateDate)) {
            $rateDateEsc = mysqli_real_escape_string($conn, $rateDate);
            $branchSql = '';
            if (auragold_tbl_has_column($conn, $table, 'branch_id')) {
                $bid = auragold_settings_branch_id();
                if ($bid > 0) {
                    $branchSql = ' AND branch_id IN (0, ' . (int) $bid . ')';
                }
            }
            $mer = getRecord(
                "SELECT ounce_rate, unit_conversion_rate, rate_at, metal_rate_per_gram
                 FROM `$table`
                 WHERE metal_id = $metalId AND rate_date = '$rateDateEsc' AND status = 1 $branchSql
                 ORDER BY id DESC LIMIT 1"
            );
            if ($mer && isset($mer['ounce_rate']) && (float) $mer['ounce_rate'] > 0) {
                $out['ounce_rate'] = (string) $mer['ounce_rate'];
                if (isset($mer['unit_conversion_rate']) && (float) $mer['unit_conversion_rate'] > 0) {
                    $out['unit_conversion_rate'] = (string) $mer['unit_conversion_rate'];
                }
                if (isset($mer['rate_at']) && (float) $mer['rate_at'] > 0) {
                    $out['rate_at'] = (string) $mer['rate_at'];
                }
                if (isset($mer['metal_rate_per_gram']) && (float) $mer['metal_rate_per_gram'] > 0) {
                    $out['metal_rate_per_gram'] = (string) $mer['metal_rate_per_gram'];
                }
                $out['source'] = 'metal_exchange_rate';
                echo json_encode($out);
                exit;
            }
        }
        $metal = getRecord("SELECT display_name, system_name FROM tbl_metal WHERE id = $metalId AND status = 1 LIMIT 1");
        if ($metal) {
            $label = trim((string) ($metal['display_name'] ?? ''));
            if ($label === '') {
                $label = trim((string) ($metal['system_name'] ?? ''));
            }
            if ($label !== '') {
                $esc = mysqli_real_escape_string($conn, $label);
                $bid = auragold_settings_branch_id();
                $branchSql = '';
                if (auragold_tbl_has_column($conn, 'tbl_dashboard_metal_meta', 'branch_id') && $bid > 0) {
                    $branchSql = ' AND branch_id IN (0, ' . (int) $bid . ')';
                }
                $meta = getRecord(
                    "SELECT ounce_rate FROM tbl_dashboard_metal_meta
                     WHERE metal = '$esc' $branchSql
                     ORDER BY branch_id DESC LIMIT 1"
                );
                if ($meta && isset($meta['ounce_rate']) && (float) $meta['ounce_rate'] > 0) {
                    $out['ounce_rate'] = (string) $meta['ounce_rate'];
                }
            }
        }
    }
    echo json_encode($out);
    exit;
}

if ($action === 'upsert_by_date') {
    $rateDate = esc($_POST['rate_date'] ?? date('Y-m-d'));
    $metalId = (int) ($_POST['metal_id'] ?? 0);
    $ounceRate = (float) ($_POST['ounce_rate'] ?? 0);
    $currencyId = (int) ($_POST['currency_id'] ?? 0);
    $unitConv = (float) ($_POST['unit_conversion_rate'] ?? 31.1035);
    $rateAt = (float) ($_POST['rate_at'] ?? 1);
    $perGramPost = isset($_POST['metal_rate_per_gram']) && $_POST['metal_rate_per_gram'] !== ''
        ? (float) $_POST['metal_rate_per_gram'] : 0.0;

    if ($metalId <= 0 || $ounceRate <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $rateDate)) {
        echo json_encode(['status' => 'error', 'message' => 'Valid date, metal, and ounce rate are required']);
        exit;
    }
    if ($unitConv <= 0) {
        $unitConv = 31.1035;
    }
    if ($rateAt <= 0) {
        $rateAt = 1.0;
    }
    if ($currencyId <= 0) {
        $baseCur = getRecord('SELECT id FROM tbl_currency WHERE status = 1 AND is_base = 1 ORDER BY id ASC LIMIT 1');
        if (!$baseCur) {
            $baseCur = getRecord('SELECT id FROM tbl_currency WHERE status = 1 ORDER BY id ASC LIMIT 1');
        }
        $currencyId = $baseCur ? (int) ($baseCur['id'] ?? 0) : 0;
    }
    if ($currencyId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Currency is required']);
        exit;
    }

    $currencyRate = 1.0;
    $suffix = auragold_master_list_sql_suffix($conn, 'tbl_currency_exchange_rate', 'branch_id');
    $cr = getRecord(
        "SELECT rate FROM tbl_currency_exchange_rate
         WHERE currency_id = $currencyId AND status = 1 $suffix
         ORDER BY id DESC LIMIT 1"
    );
    if ($cr && isset($cr['rate']) && (float) $cr['rate'] > 0) {
        $currencyRate = (float) $cr['rate'];
    }

    $perGram = $perGramPost > 0
        ? $perGramPost
        : auragold_calc_metal_rate_per_gram($ounceRate, $unitConv, $rateAt, $currencyRate);

    $branchSql = '';
    $bid = auragold_master_branch_id_for_writes($conn, $table);
    if (auragold_tbl_has_column($conn, $table, 'branch_id')) {
        $branchSql = " AND branch_id = '$bid'";
    }
    $existing = getRecord(
        "SELECT id FROM `$table`
         WHERE metal_id = $metalId AND rate_date = '$rateDate' AND status = 1 $branchSql
         ORDER BY id DESC LIMIT 1"
    );
    $desc = esc('Sale invoice ounce rate');

    if ($existing && !empty($existing['id'])) {
        $id = (int) $existing['id'];
        if (!auragold_master_can_mutate_row($conn, $table, $id)) {
            echo json_encode(['status' => 'error', 'message' => 'Access denied for this branch']);
            exit;
        }
        mysqli_query($conn, "
            UPDATE `$table`
            SET ounce_rate = '$ounceRate',
                unit_conversion_rate = '$unitConv',
                currency_id = '$currencyId',
                rate_at = '$rateAt',
                metal_rate_per_gram = '$perGram',
                description = '$desc',
                modified_by = '$user'
            WHERE id = '$id'
        ");
        echo json_encode([
            'status' => 'success',
            'id' => $id,
            'metal_rate_per_gram' => number_format($perGram, 6, '.', ''),
            'ounce_rate' => (string) $ounceRate,
        ]);
        exit;
    }

    mysqli_query($conn, "
        INSERT INTO `$table`
        (branch_id, rate_date, metal_id, ounce_rate, unit_id, unit_conversion_rate,
         currency_id, rate_at, metal_rate_per_gram, description, status, created_by)
        VALUES (
            '$bid', '$rateDate', '$metalId', '$ounceRate', NULL, '$unitConv',
            '$currencyId', '$rateAt', '$perGram', '$desc', 1, '$user'
        )
    ");
    echo json_encode([
        'status' => 'success',
        'id' => mysqli_insert_id($conn),
        'metal_rate_per_gram' => number_format($perGram, 6, '.', ''),
        'ounce_rate' => (string) $ounceRate,
    ]);
    exit;
}

if ($action === 'add' || $action === 'update') {
    $id = (int) ($_POST['id'] ?? 0);
    $rateDate = esc($_POST['rate_date'] ?? date('Y-m-d'));
    $metalId = (int) ($_POST['metal_id'] ?? 0);
    $ounceRate = (float) ($_POST['ounce_rate'] ?? 0);
    $unitId = (int) ($_POST['unit_id'] ?? 0);
    $unitConv = (float) ($_POST['unit_conversion_rate'] ?? 31.1035);
    $currencyId = (int) ($_POST['currency_id'] ?? 0);
    $rateAt = (float) ($_POST['rate_at'] ?? 1);
    $desc = esc($_POST['description'] ?? '');
    $rowStatus = isset($_POST['status']) ? ((int) $_POST['status'] === 1 ? 1 : 0) : 1;

    if ($metalId <= 0 || $currencyId <= 0 || $rateDate === '') {
        echo json_encode(['status' => 'error', 'message' => 'Date, metal, and currency are required']);
        exit;
    }
    if ($ounceRate <= 0 || $unitConv <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Ounce rate and unit conversion rate must be greater than zero']);
        exit;
    }

    $currencyRate = 1.0;
    $suffix = auragold_master_list_sql_suffix($conn, 'tbl_currency_exchange_rate', 'branch_id');
    $cr = getRecord(
        "SELECT rate FROM tbl_currency_exchange_rate
         WHERE currency_id = $currencyId AND status = 1 $suffix
         ORDER BY id DESC LIMIT 1"
    );
    if ($cr && isset($cr['rate']) && (float) $cr['rate'] > 0) {
        $currencyRate = (float) $cr['rate'];
    }

    $perGram = auragold_calc_metal_rate_per_gram($ounceRate, $unitConv, $rateAt, $currencyRate);
    $unitSql = $unitId > 0 ? (string) $unitId : 'NULL';

    if ($action === 'add') {
        $bid = auragold_master_branch_id_for_writes($conn, $table);
        mysqli_query($conn, "
            INSERT INTO tbl_metal_exchange_rate
            (branch_id, rate_date, metal_id, ounce_rate, unit_id, unit_conversion_rate,
             currency_id, rate_at, metal_rate_per_gram, description, status, created_by)
            VALUES (
                '$bid', '$rateDate', '$metalId', '$ounceRate', $unitSql, '$unitConv',
                '$currencyId', '$rateAt', '$perGram', '$desc', '$rowStatus', '$user'
            )
        ");
        echo json_encode([
            'status' => 'success',
            'id' => mysqli_insert_id($conn),
            'row_status' => $rowStatus,
            'metal_rate_per_gram' => number_format($perGram, 6, '.', ''),
        ]);
        exit;
    }

    if (!auragold_master_can_mutate_row($conn, $table, $id)) {
        echo json_encode(['status' => 'error', 'message' => 'Access denied for this branch']);
        exit;
    }

    mysqli_query($conn, "
        UPDATE tbl_metal_exchange_rate
        SET rate_date = '$rateDate',
            metal_id = '$metalId',
            ounce_rate = '$ounceRate',
            unit_id = $unitSql,
            unit_conversion_rate = '$unitConv',
            currency_id = '$currencyId',
            rate_at = '$rateAt',
            metal_rate_per_gram = '$perGram',
            description = '$desc',
            status = '$rowStatus',
            modified_by = '$user'
        WHERE id = '$id'
    ");

    echo json_encode([
        'status' => 'success',
        'id' => $id,
        'row_status' => $rowStatus,
        'metal_rate_per_gram' => number_format($perGram, 6, '.', ''),
    ]);
    exit;
}

if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    if (!auragold_master_can_mutate_row($conn, $table, $id)) {
        echo json_encode(['status' => 'error', 'message' => 'Access denied for this branch']);
        exit;
    }
    mysqli_query($conn, "
        UPDATE tbl_metal_exchange_rate
        SET status = 0, modified_by = '$user'
        WHERE id = '$id'
    ");
    echo json_encode(['status' => 'success']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
