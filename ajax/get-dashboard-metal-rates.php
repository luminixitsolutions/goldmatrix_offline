<?php
/**
 * Returns carat/purity → effective rate maps from tbl_dashboard_metal_rates (same source as dashboard.php).
 * Used by product modal: when user picks Karat, Rate / Metal Rate auto-fill.
 */
require_once dirname(__DIR__) . '/includes/session_init.php';
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/dashboard_metal_rates_branch_schema.php';
require_once dirname(__DIR__) . '/includes/auragold_branch_data_scope.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired. Please log in again.']);
    exit;
}

if (!$conn) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

$chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_dashboard_metal_rates'");
if (!$chk || mysqli_num_rows($chk) === 0) {
    if ($chk) {
        mysqli_free_result($chk);
    }
    echo json_encode(['status' => 'ok', 'rates' => ['gold' => new stdClass(), 'silver' => new stdClass()]]);
    exit;
}
mysqli_free_result($chk);

auragold_ensure_dashboard_metal_rates_branch_columns($conn);
$has_branch = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_dashboard_metal_rates', 'branch_id');
$req_b = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : 0;
$bid = auragold_resolve_branch_id_for_session($req_b);

$rates = ['gold' => [], 'silver' => []];

if ($has_branch && $bid > 0) {
    $merged = ['gold' => [], 'silver' => []];
    $rq0 = @mysqli_query(
        $conn,
        "SELECT metal, carat_label, rate, conversion_rate FROM tbl_dashboard_metal_rates WHERE branch_id = 0 AND metal IN ('gold','silver') ORDER BY metal, sort_order, id"
    );
    if ($rq0) {
        while ($row = mysqli_fetch_assoc($rq0)) {
            $m = $row['metal'];
            $cl = (string) $row['carat_label'];
            $merged[$m][$cl] = $row;
        }
        mysqli_free_result($rq0);
    }
    $rqb = @mysqli_query(
        $conn,
        'SELECT metal, carat_label, rate, conversion_rate FROM tbl_dashboard_metal_rates WHERE branch_id = ' . (int) $bid . " AND metal IN ('gold','silver') ORDER BY metal, sort_order, id"
    );
    if ($rqb) {
        while ($row = mysqli_fetch_assoc($rqb)) {
            $m = $row['metal'];
            $cl = (string) $row['carat_label'];
            $merged[$m][$cl] = $row;
        }
        mysqli_free_result($rqb);
    }
    foreach (['gold', 'silver'] as $m) {
        foreach ($merged[$m] as $cl => $row) {
            $r = (float) $row['rate'];
            $conv = isset($row['conversion_rate']) ? (float) $row['conversion_rate'] : 1.0;
            if ($conv <= 0) {
                $conv = 1.0;
            }
            $rates[$m][$cl] = round($r * $conv, 6);
        }
    }
} else {
    $rq = @mysqli_query(
        $conn,
        "SELECT metal, carat_label, rate, conversion_rate FROM tbl_dashboard_metal_rates WHERE metal IN ('gold','silver') ORDER BY metal, sort_order, id"
    );
    if ($rq) {
        while ($row = mysqli_fetch_assoc($rq)) {
            $m = $row['metal'];
            if (!isset($rates[$m])) {
                continue;
            }
            $r = (float) $row['rate'];
            $conv = isset($row['conversion_rate']) ? (float) $row['conversion_rate'] : 1.0;
            if ($conv <= 0) {
                $conv = 1.0;
            }
            $effective = $r * $conv;
            $key = (string) $row['carat_label'];
            $rates[$m][$key] = round($effective, 6);
        }
        mysqli_free_result($rq);
    }
}

echo json_encode(['status' => 'ok', 'rates' => $rates]);
