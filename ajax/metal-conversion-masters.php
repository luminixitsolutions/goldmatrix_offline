<?php
/**
 * JSON: all tbl_metal rows (status=1) + latest dashboard rate for each metal’s dashboard bucket
 * (gold/silver/platinum/diamond). branch_id from session / GET.
 */
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_metal_conversion_masters.php';
require_once __DIR__ . '/../includes/dashboard_metal_rates_db.php';

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/auragold_require_login.php';
auragold_require_login_or_exit();

$branch_id = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : 0;
if ($branch_id <= 0 && function_exists('auragold_effective_branch_id')) {
    $branch_id = (int) auragold_effective_branch_id();
}
if ($branch_id <= 0 && !empty($_SESSION['branch_id'])) {
    $branch_id = (int) $_SESSION['branch_id'];
}

$metals = auragold_metal_conversion_master_list($conn, $branch_id);

$ratesByKey  = ['gold' => null, 'silver' => null, 'platinum' => null, 'diamond' => null];
if (function_exists('auragold_latest_dashboard_rate_for_metal') && $conn) {
    foreach (['gold', 'silver', 'platinum', 'diamond'] as $k) {
        $ratesByKey[$k] = auragold_latest_dashboard_rate_for_metal($conn, $k, $branch_id);
    }
}

$mx = null;
$chk = $conn ? @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_dashboard_metal_rates'") : null;
if ($chk && mysqli_num_rows($chk) > 0) {
    if ($chk) {
        mysqli_free_result($chk);
    }
    if (is_file(__DIR__ . '/../includes/dashboard_metal_rates_branch_schema.php')) {
        require_once __DIR__ . '/../includes/dashboard_metal_rates_branch_schema.php';
        if (function_exists('auragold_ensure_dashboard_metal_rates_branch_columns')) {
            auragold_ensure_dashboard_metal_rates_branch_columns($conn);
        }
    }
    if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_dashboard_metal_rates', 'branch_id') && $branch_id > 0) {
        $g = getRecord("SELECT MAX(updated_at) AS mx FROM tbl_dashboard_metal_rates WHERE branch_id IN (0, " . (int) $branch_id . ")");
    } else {
        $g = getRecord('SELECT MAX(updated_at) AS mx FROM tbl_dashboard_metal_rates');
    }
    if ($g) {
        $mx = $g['mx'] ?? null;
    }
} elseif ($chk) {
    mysqli_free_result($chk);
}

echo json_encode([
    'status'          => 'success',
    'branch_id'       => $branch_id,
    'metals'          => $metals,
    'latest_by_key'  => $ratesByKey,
    'rates_max_updated' => $mx,
]);
