<?php
require_once dirname(__DIR__) . '/includes/session_init.php';
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/dashboard_metal_rates_branch_schema.php';
require_once dirname(__DIR__) . '/includes/auragold_branch_data_scope.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Session expired']);
    exit;
}

if (!$conn) {
    echo json_encode(['ok' => false, 'message' => 'Database unavailable']);
    exit;
}

require_once dirname(__DIR__) . '/includes/dashboard_rate_history_schema.php';
auragold_ensure_tbl_dashboard_metal_rate_history($conn);

auragold_ensure_dashboard_metal_rates_branch_columns($conn);
$has_branch_rates = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_dashboard_metal_rates', 'branch_id');
$req_branch = isset($_GET['branch']) ? (int) $_GET['branch'] : 0;
$branch_id = auragold_resolve_branch_id_for_session($req_branch);

$today24_base = 0.0;
if ($has_branch_rates && $branch_id > 0) {
    $rq = @mysqli_query(
        $conn,
        'SELECT rate FROM tbl_dashboard_metal_rates WHERE metal = \'gold\' AND carat_label = \'24K\' AND branch_id = ' . (int) $branch_id . ' LIMIT 1'
    );
    if ($rq && ($row = mysqli_fetch_assoc($rq))) {
        $today24_base = (float) ($row['rate'] ?? 0);
    }
    if ($rq) {
        mysqli_free_result($rq);
    }
    if ($today24_base <= 0) {
        $rq = @mysqli_query(
            $conn,
            "SELECT rate FROM tbl_dashboard_metal_rates WHERE metal = 'gold' AND carat_label = '24K' AND branch_id = 0 LIMIT 1"
        );
        if ($rq && ($row = mysqli_fetch_assoc($rq))) {
            $today24_base = (float) ($row['rate'] ?? 0);
        }
        if ($rq) {
            mysqli_free_result($rq);
        }
    }
} else {
    $rq = @mysqli_query(
        $conn,
        "SELECT rate FROM tbl_dashboard_metal_rates WHERE metal = 'gold' AND carat_label = '24K' LIMIT 1"
    );
    if ($rq && ($row = mysqli_fetch_assoc($rq))) {
        $today24_base = (float) ($row['rate'] ?? 0);
    }
    if ($rq) {
        mysqli_free_result($rq);
    }
}

$hist_branch_sql = '';
$has_hist_branch = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_dashboard_metal_rate_history', 'branch_id');
if ($has_hist_branch && $branch_id > 0) {
    $hist_branch_sql = ' AND branch_id IN (0, ' . (int) $branch_id . ')';
} elseif ($has_hist_branch) {
    $hist_branch_sql = ' AND branch_id = 0';
}

$hist = [];
$hist_select = $has_hist_branch
    ? 'SELECT rate, recorded_at, branch_id FROM tbl_dashboard_metal_rate_history'
    : 'SELECT rate, recorded_at FROM tbl_dashboard_metal_rate_history';

$dateFromRaw = isset($_GET['date_from']) ? trim((string) $_GET['date_from']) : '';
$dateToRaw = isset($_GET['date_to']) ? trim((string) $_GET['date_to']) : '';
$histDateSql = ' AND recorded_at >= DATE_SUB(NOW(), INTERVAL 120 DAY) ';
if ($dateFromRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFromRaw)
    && $dateToRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateToRaw)) {
    $df = $dateFromRaw;
    $dt = $dateToRaw;
    if ($df > $dt) {
        $tmp = $df;
        $df = $dt;
        $dt = $tmp;
    }
    $dfEsc = mysqli_real_escape_string($conn, $df);
    $dtEsc = mysqli_real_escape_string($conn, $dt);
    $histDateSql = " AND DATE(recorded_at) >= '$dfEsc' AND DATE(recorded_at) <= '$dtEsc' ";
}

$hq = @mysqli_query(
    $conn,
    "$hist_select
     WHERE metal = 'gold' AND carat_label = '24K'
     $histDateSql
     $hist_branch_sql
     ORDER BY recorded_at ASC"
);
if ($hq) {
    while ($r = mysqli_fetch_assoc($hq)) {
        $hist[] = [
            'rate'          => (float) ($r['rate'] ?? 0),
            'recorded_at'   => (string) ($r['recorded_at'] ?? ''),
            'branch_id_row' => $has_hist_branch ? (int) ($r['branch_id'] ?? 0) : 0,
        ];
    }
    mysqli_free_result($hq);
}

/** @var array<string,float> $lastPerDay Y-m-d => rate (prefer branch-specific row when duplicate day) */
$lastPerDay = [];
$byDay = [];
foreach ($hist as $h) {
    $ts = strtotime($h['recorded_at']);
    if (!$ts) {
        continue;
    }
    $d = date('Y-m-d', $ts);
    if (!isset($byDay[$d])) {
        $byDay[$d] = [];
    }
    $byDay[$d][] = $h;
}
foreach ($byDay as $d => $rows) {
    $pick = $rows[0];
    if ($branch_id > 0) {
        foreach ($rows as $h) {
            if ((int) ($h['branch_id_row'] ?? 0) === $branch_id) {
                $pick = $h;
                break;
            }
        }
    } else {
        foreach ($rows as $h) {
            if ((int) ($h['branch_id_row'] ?? 0) === 0) {
                $pick = $h;
                break;
            }
        }
    }
    $lastPerDay[$d] = $pick['rate'];
}

$todayD = date('Y-m-d');
if ($today24_base > 0) {
    $lastPerDay[$todayD] = $today24_base;
}

$yesterdayD = date('Y-m-d', strtotime('-1 day'));
$yesterdayBase = isset($lastPerDay[$yesterdayD]) ? (float) $lastPerDay[$yesterdayD] : null;

ksort($lastPerDay);
$series = [];
foreach ($lastPerDay as $d => $rate) {
    $series[] = ['date' => $d, 'rate' => $rate];
}

$changePct = null;
if ($yesterdayBase !== null && $yesterdayBase > 0 && $today24_base > 0) {
    $changePct = (($today24_base - $yesterdayBase) / $yesterdayBase) * 100.0;
}

echo json_encode([
    'ok'              => true,
    'today24Base'     => $today24_base,
    'yesterday24Base' => $yesterdayBase,
    'changePct'       => $changePct,
    'series'          => $series,
    'hasHistory'      => count($hist) > 0 || $today24_base > 0,
], JSON_UNESCAPED_UNICODE);
