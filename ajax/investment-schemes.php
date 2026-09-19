<?php
/**
 * JSON API for tbl_investment_schemes (Create Scheme modal).
 * action=list (GET) | save (POST JSON) | delete (POST JSON)
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';

if (!auragold_is_logged_in_session()) {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

function if_investment_schemes_table_exists($conn) {
    $r = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_investment_schemes'");
    return $r && mysqli_num_rows($r) > 0;
}

function if_map_scheme_row(array $r) {
    $bonus = [];
    if (!empty($r['bonus_rows'])) {
        $dec = json_decode($r['bonus_rows'], true);
        if (is_array($dec)) {
            $bonus = $dec;
        }
    }

    return [
        'id' => (string) $r['id'],
        'scheme_name' => $r['scheme_name'],
        'redemption_on' => $r['redemption_on'] ?? '',
        'carat_id' => isset($r['carat_id']) && $r['carat_id'] !== null && $r['carat_id'] !== '' ? (int) $r['carat_id'] : null,
        'carat_label' => $r['carat_label'] ?? '',
        'duration_value' => (int) ($r['duration_value'] ?? 12),
        'duration_unit' => $r['duration_unit'] ?? 'Month',
        'installment_type' => $r['installment_type'] ?? '',
        'installment_amt' => (float) ($r['installment_amt'] ?? 0),
        'minimum_amt_enabled' => !empty($r['minimum_amt_enabled']),
        'minimum_amt' => (float) ($r['minimum_amt'] ?? 0),
        'active' => !isset($r['active']) || (int) $r['active'] === 1,
        'bonus_rows' => $bonus,
    ];
}

global $conn;
if (!if_investment_schemes_table_exists($conn)) {
    echo json_encode(['ok' => false, 'message' => 'Table tbl_investment_schemes does not exist. Run admin/sql/create_tbl_investment_schemes.sql']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = isset($_GET['action']) ? trim((string) $_GET['action']) : '';
if ($action === '' && isset($_POST['action'])) {
    $action = trim((string) $_POST['action']);
}

if ($method === 'GET' && $action === 'list') {
    $rows = getList('SELECT * FROM tbl_investment_schemes ORDER BY id DESC');
    $out = [];
    if (is_array($rows)) {
        foreach ($rows as $r) {
            $out[] = if_map_scheme_row($r);
        }
    }
    echo json_encode(['ok' => true, 'schemes' => $out]);
    exit;
}

if ($method !== 'POST') {
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$in = json_decode($raw, true);
if (!is_array($in)) {
    $in = $_POST;
}

if ($action === 'delete') {
    $id = isset($in['id']) ? (int) $in['id'] : 0;
    if ($id < 1) {
        echo json_encode(['ok' => false, 'message' => 'Invalid id']);
        exit;
    }
    $idEsc = (int) $id;
    mysqli_query($conn, "DELETE FROM tbl_investment_schemes WHERE id = $idEsc LIMIT 1");
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'save') {
    $name = isset($in['scheme_name']) ? trim((string) $in['scheme_name']) : '';
    if ($name === '') {
        echo json_encode(['ok' => false, 'message' => 'Scheme name is required']);
        exit;
    }

    $redemption = isset($in['redemption_on']) ? trim((string) $in['redemption_on']) : '';
    $caratId = isset($in['carat_id']) && $in['carat_id'] !== '' && $in['carat_id'] !== null ? (int) $in['carat_id'] : null;
    $caratLabel = isset($in['carat_label']) ? trim((string) $in['carat_label']) : '';
    if (strcasecmp($redemption, 'Amount') === 0) {
        $caratId = null;
        $caratLabel = '';
    }
    $durVal = isset($in['duration_value']) ? (int) $in['duration_value'] : 12;
    if ($durVal < 1) {
        $durVal = 1;
    }
    $durUnit = isset($in['duration_unit']) ? trim((string) $in['duration_unit']) : 'Month';
    if (!in_array($durUnit, ['Month', 'Year', 'Day'], true)) {
        $durUnit = 'Month';
    }
    $instType = isset($in['installment_type']) ? trim((string) $in['installment_type']) : '';
    $instAmt = isset($in['installment_amt']) ? (float) $in['installment_amt'] : 0;
    $minEn = !empty($in['minimum_amt_enabled']);
    $minAmt = isset($in['minimum_amt']) ? (float) $in['minimum_amt'] : 0;
    $active = !isset($in['active']) || !empty($in['active']);
    $bonusJson = '[]';
    if (isset($in['bonus_rows']) && is_array($in['bonus_rows'])) {
        $bonusJson = json_encode($in['bonus_rows']);
    }

    $nameEsc = mysqli_real_escape_string($conn, $name);
    $redEsc = mysqli_real_escape_string($conn, $redemption);
    $caratLabelEsc = mysqli_real_escape_string($conn, $caratLabel);
    $durUnitEsc = mysqli_real_escape_string($conn, $durUnit);
    $instTypeEsc = mysqli_real_escape_string($conn, $instType);
    $bonusEsc = mysqli_real_escape_string($conn, $bonusJson);

    $caratSql = $caratId === null ? 'NULL' : (string) (int) $caratId;
    $minEnInt = $minEn ? 1 : 0;
    $activeInt = $active ? 1 : 0;

    $existingId = 0;
    if (!empty($in['id']) && ctype_digit((string) $in['id'])) {
        $existingId = (int) $in['id'];
    }

    if ($existingId > 0) {
        $chk = getRecord('SELECT id FROM tbl_investment_schemes WHERE id = ' . $existingId . ' LIMIT 1');
        if ($chk && !empty($chk['id'])) {
            $sql = "UPDATE tbl_investment_schemes SET
                scheme_name = '$nameEsc',
                redemption_on = '$redEsc',
                carat_id = $caratSql,
                carat_label = '$caratLabelEsc',
                duration_value = $durVal,
                duration_unit = '$durUnitEsc',
                installment_type = '$instTypeEsc',
                installment_amt = $instAmt,
                minimum_amt_enabled = $minEnInt,
                minimum_amt = $minAmt,
                active = $activeInt,
                bonus_rows = '$bonusEsc'
                WHERE id = $existingId LIMIT 1";
            if (!mysqli_query($conn, $sql)) {
                echo json_encode(['ok' => false, 'message' => mysqli_error($conn) ?: 'Update failed']);
                exit;
            }
            echo json_encode(['ok' => true, 'id' => $existingId]);
            exit;
        }
    }

    $sql = "INSERT INTO tbl_investment_schemes (
        scheme_name, redemption_on, carat_id, carat_label,
        duration_value, duration_unit, installment_type, installment_amt,
        minimum_amt_enabled, minimum_amt, active, bonus_rows
    ) VALUES (
        '$nameEsc', '$redEsc', $caratSql, '$caratLabelEsc',
        $durVal, '$durUnitEsc', '$instTypeEsc', $instAmt,
        $minEnInt, $minAmt, $activeInt, '$bonusEsc'
    )";
    if (!mysqli_query($conn, $sql)) {
        echo json_encode(['ok' => false, 'message' => mysqli_error($conn) ?: 'Insert failed']);
        exit;
    }
    $newId = (int) mysqli_insert_id($conn);
    echo json_encode(['ok' => true, 'id' => $newId]);
    exit;
}

echo json_encode(['ok' => false, 'message' => 'Unknown action']);
