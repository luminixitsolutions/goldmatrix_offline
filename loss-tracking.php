<?php
/**
 * Loss Tracking — manufacturing weight sent out (department transfers) vs recorded loss
 * (reduce weight entries on jobwork, including auto loss on transfer).
 */
session_start();
require_once __DIR__ . '/config.php';

$departments = [];
$tblDept = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_departments'");
if ($tblDept && mysqli_num_rows($tblDept) > 0) {
    mysqli_free_result($tblDept);
    $departments = function_exists('getList') ? @getList('SELECT id, dept_name FROM tbl_departments WHERE status = 1 ORDER BY dept_name ASC') : [];
}
if (!is_array($departments)) {
    $departments = [];
}

$job_worker_type_id = 0;
$jw_result = @mysqli_query($conn, "SELECT id FROM tbl_customer_types WHERE LOWER(name) = 'job worker' AND status = 1 LIMIT 1");
if ($jw_result && mysqli_num_rows($jw_result) > 0) {
    $jw_row = mysqli_fetch_assoc($jw_result);
    $job_worker_type_id = (int) $jw_row['id'];
}
if ($jw_result) {
    mysqli_free_result($jw_result);
}

$department_users = [];
foreach ($departments as $dept) {
    $dept_id = (int) $dept['id'];
    $department_users[$dept_id] = [];
    $map_tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_department_user_map'");
    if ($map_tbl && mysqli_num_rows($map_tbl) > 0) {
        mysqli_free_result($map_tbl);
        $users_query = '
            SELECT c.id, c.name
            FROM tbl_customers c
            INNER JOIN tbl_department_user_map dum ON c.id = dum.user_id AND dum.status = 1
            WHERE dum.department_id = ' . $dept_id . '
            AND c.status = 1
            ' . ($job_worker_type_id > 0 ? 'AND c.customer_type_id = ' . $job_worker_type_id : '') . '
            ORDER BY c.name ASC
        ';
        $users_result = @mysqli_query($conn, $users_query);
        if ($users_result) {
            while ($user_row = mysqli_fetch_assoc($users_result)) {
                $department_users[$dept_id][] = $user_row;
            }
            mysqli_free_result($users_result);
        }
    } elseif ($map_tbl) {
        mysqli_free_result($map_tbl);
    }
}

$f_date_from = isset($_GET['f_date_from']) ? trim((string) $_GET['f_date_from']) : '';
$f_date_to = isset($_GET['f_date_to']) ? trim((string) $_GET['f_date_to']) : '';
$f_department_id = isset($_GET['f_department_id']) ? (int) $_GET['f_department_id'] : 0;
$f_user_id = isset($_GET['f_user_id']) ? (int) $_GET['f_user_id'] : 0;

if ($f_date_from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_date_from)) {
    $f_date_from = '';
}
if ($f_date_to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_date_to)) {
    $f_date_to = '';
}

$filter_active_count = 0;
if ($f_date_from !== '') {
    $filter_active_count++;
}
if ($f_date_to !== '') {
    $filter_active_count++;
}
if ($f_department_id > 0) {
    $filter_active_count++;
}
if ($f_user_id > 0) {
    $filter_active_count++;
}

/**
 * @return array<int,float> jobwork_order_id => total line weight (net / final / gross preference per line)
 */
function auragold_loss_tracking_jwo_line_weights(mysqli $conn): array
{
    $out = [];
    $chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_order_items'");
    if (!$chk || mysqli_num_rows($chk) === 0) {
        if ($chk) {
            mysqli_free_result($chk);
        }

        return $out;
    }
    mysqli_free_result($chk);

    $cols = [];
    $cq = @mysqli_query($conn, 'SHOW COLUMNS FROM tbl_jobwork_order_items');
    if ($cq) {
        while ($c = mysqli_fetch_assoc($cq)) {
            $fn = (string) ($c['Field'] ?? '');
            if ($fn !== '') {
                $cols[$fn] = true;
            }
        }
        mysqli_free_result($cq);
    }

    $parts = [];
    if (!empty($cols['net_weight'])) {
        $parts[] = 'WHEN COALESCE(ji.net_weight, 0) > 0.0000001 THEN ji.net_weight';
    }
    if (!empty($cols['final_weight'])) {
        $parts[] = 'WHEN COALESCE(ji.final_weight, 0) > 0.0000001 THEN ji.final_weight';
    }
    if (!empty($cols['gross_weight'])) {
        $parts[] = 'WHEN COALESCE(ji.gross_weight, 0) > 0.0000001 THEN ji.gross_weight';
    }
    if (empty($parts)) {
        return $out;
    }

    $case = 'CASE ' . implode(' ', $parts) . ' ELSE 0 END';
    $sql = 'SELECT ji.jobwork_order_id, COALESCE(SUM(' . $case . '), 0) AS w FROM tbl_jobwork_order_items ji GROUP BY ji.jobwork_order_id';
    $list = function_exists('getList') ? @getList($sql) : [];
    if (!is_array($list)) {
        return $out;
    }
    foreach ($list as $r) {
        $jid = (int) ($r['jobwork_order_id'] ?? 0);
        if ($jid > 0) {
            $out[$jid] = (float) ($r['w'] ?? 0);
        }
    }

    return $out;
}

/**
 * @return array<int, array{out:float, loss:float}>
 */
function auragold_loss_tracking_aggregate(mysqli $conn, string $dateFromYmd, string $dateToYmd, int $filterDept, int $filterUser): array
{
    $buckets = [];

    $touch = static function (&$buckets, int $d, int $u) {
        if ($d < 1 || $u < 1) {
            return;
        }
        $k = $d . '|' . $u;
        if (!isset($buckets[$k])) {
            $buckets[$k] = ['dept_id' => $d, 'user_id' => $u, 'out' => 0.0, 'loss' => 0.0];
        }
    };

    $df = mysqli_real_escape_string($conn, $dateFromYmd);
    $dt = mysqli_real_escape_string($conn, $dateToYmd);

    $jwoWt = auragold_loss_tracking_jwo_line_weights($conn);

    $actChk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_queue_activity'");
    $hasAct = ($actChk && mysqli_num_rows($actChk) > 0);
    if ($actChk) {
        mysqli_free_result($actChk);
    }

    if ($hasAct) {
        $acf = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_queue_activity LIKE 'from_dept_id'");
        if (!$acf || mysqli_num_rows($acf) === 0) {
            if ($acf) {
                mysqli_free_result($acf);
            }
            $hasAct = false;
        } else {
            mysqli_free_result($acf);
        }
    }

    if ($hasAct) {
        $sqlAct = 'SELECT a.id, a.jobwork_order_id, a.created_at, a.activity_action,
            a.from_dept_id, a.from_user_id, a.to_dept_id, a.to_user_id
            FROM tbl_jobwork_queue_activity a
            WHERE a.created_at >= \'' . $df . ' 00:00:00\' AND a.created_at <= \'' . $dt . ' 23:59:59\'
            ORDER BY a.jobwork_order_id ASC, a.created_at ASC, a.id ASC';
        $acts = function_exists('getList') ? @getList($sqlAct) : [];
        if (!is_array($acts)) {
            $acts = [];
        }

        foreach ($acts as $a) {
            $fromDept = isset($a['from_dept_id']) && $a['from_dept_id'] !== null && $a['from_dept_id'] !== '' ? (int) $a['from_dept_id'] : 0;
            $fromUser = isset($a['from_user_id']) && $a['from_user_id'] !== null && $a['from_user_id'] !== '' ? (int) $a['from_user_id'] : 0;
            $toDept = isset($a['to_dept_id']) && $a['to_dept_id'] !== null && $a['to_dept_id'] !== '' ? (int) $a['to_dept_id'] : 0;
            $toUser = isset($a['to_user_id']) && $a['to_user_id'] !== null && $a['to_user_id'] !== '' ? (int) $a['to_user_id'] : 0;
            $actAction = isset($a['activity_action']) ? trim((string) $a['activity_action']) : '';
            $jid = (int) ($a['jobwork_order_id'] ?? 0);
            if ($jid < 1 || $fromDept < 1 || $fromUser < 1) {
                continue;
            }
            if ($actAction === 'jobwork_create') {
                continue;
            }
            $isRealTransfer = ($fromDept > 0 && ($fromDept !== $toDept || $fromUser !== $toUser));
            if (!$isRealTransfer) {
                continue;
            }
            $wt = isset($jwoWt[$jid]) ? (float) $jwoWt[$jid] : 0.0;
            if ($wt <= 0.0000001) {
                continue;
            }
            if ($filterDept > 0 && $fromDept !== $filterDept) {
                continue;
            }
            if ($filterUser > 0 && $fromUser !== $filterUser) {
                continue;
            }
            $touch($buckets, $fromDept, $fromUser);
            $k = $fromDept . '|' . $fromUser;
            $buckets[$k]['out'] += $wt;
        }
    }

    $wChk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_weight_adjustments'");
    $hasW = ($wChk && mysqli_num_rows($wChk) > 0);
    if ($wChk) {
        mysqli_free_result($wChk);
    }

    if ($hasW) {
        $wSrcD = 'NULL AS source_department_id';
        $wSrcU = 'NULL AS source_user_id';
        $wcq = @mysqli_query($conn, 'SHOW COLUMNS FROM tbl_jobwork_weight_adjustments');
        if ($wcq) {
            $wcf = [];
            while ($c = mysqli_fetch_assoc($wcq)) {
                $fn = (string) ($c['Field'] ?? '');
                if ($fn !== '') {
                    $wcf[$fn] = true;
                }
            }
            mysqli_free_result($wcq);
            if (!empty($wcf['source_department_id'])) {
                $wSrcD = 'w.source_department_id AS source_department_id';
            }
            if (!empty($wcf['source_user_id'])) {
                $wSrcU = 'w.source_user_id AS source_user_id';
            }
        }
        $jDeptSel = '0 AS department_id';
        $jUserSel = '0 AS department_user_id';
        $jchk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_orders'");
        if ($jchk && mysqli_num_rows($jchk) > 0) {
            mysqli_free_result($jchk);
            $jcf = [];
            $jcq = @mysqli_query($conn, 'SHOW COLUMNS FROM tbl_jobwork_orders');
            if ($jcq) {
                while ($c = mysqli_fetch_assoc($jcq)) {
                    $fn = (string) ($c['Field'] ?? '');
                    if ($fn !== '') {
                        $jcf[$fn] = true;
                    }
                }
                mysqli_free_result($jcq);
            }
            if (!empty($jcf['department_id'])) {
                $jDeptSel = 'j.department_id AS department_id';
            }
            if (!empty($jcf['department_user_id'])) {
                $jUserSel = 'j.department_user_id AS department_user_id';
            }
        } elseif ($jchk) {
            mysqli_free_result($jchk);
        }

        $sqlLoss = 'SELECT w.weight_grams, w.adjustment_type, ' . $wSrcD . ', ' . $wSrcU . ', '
            . $jDeptSel . ', ' . $jUserSel . '
            FROM tbl_jobwork_weight_adjustments w
            INNER JOIN tbl_jobwork_orders j ON j.id = w.jobwork_order_id
            WHERE w.adjustment_type = \'reduce\'
            AND w.created_at >= \'' . $df . ' 00:00:00\' AND w.created_at <= \'' . $dt . ' 23:59:59\'';
        $lossRows = function_exists('getList') ? @getList($sqlLoss) : [];
        if (!is_array($lossRows)) {
            $lossRows = [];
        }
        foreach ($lossRows as $lr) {
            $wg = (float) ($lr['weight_grams'] ?? 0);
            if ($wg <= 0.0000001) {
                continue;
            }
            $srcD = isset($lr['source_department_id']) ? (int) $lr['source_department_id'] : 0;
            $srcU = isset($lr['source_user_id']) ? (int) $lr['source_user_id'] : 0;
            $jd = isset($lr['department_id']) && $lr['department_id'] !== null && $lr['department_id'] !== '' ? (int) $lr['department_id'] : 0;
            $ju = isset($lr['department_user_id']) && $lr['department_user_id'] !== null && $lr['department_user_id'] !== '' ? (int) $lr['department_user_id'] : 0;
            $d = $srcD > 0 ? $srcD : $jd;
            $u = $srcU > 0 ? $srcU : $ju;
            if ($d < 1 || $u < 1) {
                continue;
            }
            if ($filterDept > 0 && $d !== $filterDept) {
                continue;
            }
            if ($filterUser > 0 && $u !== $filterUser) {
                continue;
            }
            $touch($buckets, $d, $u);
            $k = $d . '|' . $u;
            $buckets[$k]['loss'] += $wg;
        }
    }

    return $buckets;
}

$report_rows = [];
$tot_out = 0.0;
$tot_loss = 0.0;

$default_from = date('Y-m-01');
$default_to = date('Y-m-d');
$use_from = $f_date_from !== '' ? $f_date_from : $default_from;
$use_to = $f_date_to !== '' ? $f_date_to : $default_to;

$buckets = auragold_loss_tracking_aggregate($conn, $use_from, $use_to, $f_department_id, $f_user_id);

$deptNames = [];
foreach ($departments as $d) {
    $deptNames[(int) ($d['id'] ?? 0)] = trim((string) ($d['dept_name'] ?? ''));
}

$userIds = [];
foreach ($buckets as $b) {
    $uid = (int) ($b['user_id'] ?? 0);
    if ($uid > 0) {
        $userIds[$uid] = true;
    }
}
$custMap = [];
if (!empty($userIds)) {
    $in = implode(',', array_map('intval', array_keys($userIds)));
    $custList = function_exists('getList') ? @getList(
        'SELECT id, name, registration_no, trade_no, national_id, bank_account_no FROM tbl_customers WHERE id IN (' . $in . ')'
    ) : [];
    if (is_array($custList)) {
        foreach ($custList as $c) {
            $cid = (int) ($c['id'] ?? 0);
            if ($cid < 1) {
                continue;
            }
            $acct = '';
            foreach (['registration_no', 'trade_no', 'national_id', 'bank_account_no'] as $acf) {
                $v = trim((string) ($c[$acf] ?? ''));
                if ($v !== '') {
                    $acct = $v;
                    break;
                }
            }
            $custMap[$cid] = [
                'name' => trim((string) ($c['name'] ?? '')),
                'account_no' => $acct,
            ];
        }
    }
}

foreach ($buckets as $k => $b) {
    $d = (int) $b['dept_id'];
    $u = (int) $b['user_id'];
    $outW = round((float) $b['out'], 3);
    $lossW = round((float) $b['loss'], 3);
    if ($outW <= 0.0000001 && $lossW <= 0.0000001) {
        continue;
    }
    $dn = $deptNames[$d] ?? ('Dept #' . $d);
    $un = $custMap[$u]['name'] ?? ('User #' . $u);
    $acct = $custMap[$u]['account_no'] ?? '';
    $report_rows[] = [
        'department_id' => $d,
        'department_user_id' => $u,
        'department_name' => $dn,
        'ledger_name' => $un,
        'account_no' => $acct,
        'out_sum' => $outW,
        'loss_sum' => $lossW,
    ];
    $tot_out += $outW;
    $tot_loss += $lossW;
}

usort($report_rows, static function ($a, $b) {
    $c = strcasecmp($a['department_name'] ?? '', $b['department_name'] ?? '');
    if ($c !== 0) {
        return $c;
    }

    return strcasecmp($a['ledger_name'] ?? '', $b['ledger_name'] ?? '');
});

function lt_fmt_wt($v): string
{
    if ($v === null || $v === '') {
        return '0.00';
    }
    $f = (float) $v;
    if (!is_finite($f)) {
        return '0.00';
    }

    return number_format($f, 2, '.', '');
}

$lt_columns = [
    ['key' => 'sr_no', 'label' => 'Sr No.'],
    ['key' => 'ledger_name', 'label' => 'Ledger Name'],
    ['key' => 'account_no', 'label' => 'Account No.'],
    ['key' => 'department_name', 'label' => 'Department'],
    ['key' => 'out_sum', 'label' => 'Out (Sum)'],
    ['key' => 'loss_sum', 'label' => 'Loss (Sum)'],
];

function lt_body_cell(array $row, int $sr, string $key): string
{
    switch ($key) {
        case 'sr_no':
            return (string) (int) $sr;
        case 'ledger_name':
            $mpHref = 'manufacturing-process.php?department_id=' . (int) ($row['department_id'] ?? 0) . '&user_id=' . (int) ($row['department_user_id'] ?? 0);
            $lab = trim((string) ($row['ledger_name'] ?? ''));
            if ($lab === '') {
                $lab = '—';
            }

            return '<a class="lt-link" href="' . htmlspecialchars($mpHref, ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') . '</a>';
        case 'account_no':
            $a = trim((string) ($row['account_no'] ?? ''));

            return htmlspecialchars($a !== '' ? $a : '—', ENT_QUOTES, 'UTF-8');
        case 'department_name':
            $d = trim((string) ($row['department_name'] ?? ''));

            return htmlspecialchars($d !== '' ? $d : '—', ENT_QUOTES, 'UTF-8');
        case 'out_sum':
            return htmlspecialchars(lt_fmt_wt($row['out_sum'] ?? 0), ENT_QUOTES, 'UTF-8');
        case 'loss_sum':
            return htmlspecialchars(lt_fmt_wt($row['loss_sum'] ?? 0), ENT_QUOTES, 'UTF-8');
        default:
            return '—';
    }
}

function lt_footer_cell(string $key, float $tot_out, float $tot_loss): string
{
    switch ($key) {
        case 'sr_no':
            return '';
        case 'ledger_name':
        case 'account_no':
            return '—';
        case 'department_name':
            return 'Total';
        case 'out_sum':
            return htmlspecialchars(lt_fmt_wt($tot_out), ENT_QUOTES, 'UTF-8');
        case 'loss_sum':
            return htmlspecialchars(lt_fmt_wt($tot_loss), ENT_QUOTES, 'UTF-8');
        default:
            return '—';
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Loss Tracking - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/header-script.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <link rel="stylesheet" href="assets/css/advance-filter-global.css">
    <link rel="stylesheet" href="assets/css/mfg-pages-mobile.css">
    <style>
        :root {
            --lt-navy: #11294b;
            --lt-gold: #c9a24a;
            --lt-gold-light: #e8d48a;
            --lt-gold-pale: #faf6eb;
        }
        .lt-wrap { padding: 12px 14px 24px; max-width: 100%; }
        .lt-head { margin-bottom: 10px; }
        .lt-head h1 { font-size: 1.25rem; font-weight: 700; color: var(--lt-navy); margin: 0; }
        .lt-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            padding: 12px 0;
            margin-bottom: 12px;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            background: #fff;
        }
        .lt-search-wrap {
            position: relative;
            flex: 1 1 auto;
            min-width: 200px;
            max-width: 480px;
        }
        .lt-search-input {
            width: 100%;
            border-radius: 999px;
            padding: 0.5rem 2.5rem 0.5rem 1rem;
            border: 1px solid #cbd5e1;
            font-size: 0.9375rem;
            color: #1e293b;
            background: #fff;
        }
        .lt-search-input:focus {
            outline: none;
            border-color: var(--lt-navy);
            box-shadow: 0 0 0 2px rgba(17, 41, 75, 0.12);
        }
        .lt-search-input::placeholder { color: #94a3b8; }
        .lt-search-icon {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            pointer-events: none;
            display: flex;
            align-items: center;
        }
        .lt-search-icon i { width: 18px; height: 18px; }
        .lt-bar-actions { display: flex; align-items: center; gap: 8px; flex-shrink: 0; flex-wrap: wrap; }
        .lt-bar-actions .btn {
            border-radius: 8px;
            font-weight: 600;
            border-color: var(--lt-navy);
            color: var(--lt-navy);
            background: #fff;
        }
        .lt-bar-actions .btn:hover {
            background: var(--lt-gold-pale);
            border-color: var(--lt-navy);
            color: var(--lt-navy);
        }
        .lt-bar-actions .dropdown .btn.dropdown-toggle {
            background: var(--lt-gold-pale);
        }
        .lt-toolbar-wrap { position: relative; display: inline-block; }
        .lt-columns-panel {
            position: absolute;
            right: 0;
            top: 100%;
            margin-top: 6px;
            width: 280px;
            max-height: 400px;
            background: #fff;
            border: 1px solid #d6dbea;
            border-radius: 10px;
            box-shadow: 0 10px 28px rgba(17, 41, 75, 0.15);
            z-index: 2000;
            display: none;
            flex-direction: column;
        }
        .lt-columns-panel.show { display: flex; }
        .lt-columns-panel-header {
            padding: 8px 10px;
            border-bottom: 1px solid #e8ecf2;
            font-weight: 700;
            color: var(--lt-navy);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .lt-columns-list { overflow: auto; padding: 8px; }
        .lt-columns-list label { display: flex; align-items: center; gap: 8px; padding: 4px 0; font-size: 13px; cursor: pointer; }
        .lt-filter-btn-wrap { position: relative; display: inline-block; }
        .lt-filter-badge {
            position: absolute; top: -7px; right: -8px; min-width: 18px; height: 18px; padding: 0 5px;
            border-radius: 999px; background: #ef4444; color: #fff; font-size: 11px; font-weight: 700;
            line-height: 18px; text-align: center;
        }
        .lt-table-scroll {
            overflow: auto;
            border: 1px solid #d6dbea;
            border-radius: 10px;
            background: #fff;
            max-height: calc(100vh - 220px);
        }
        .lt-table {
            border-collapse: collapse;
            width: max-content;
            min-width: 100%;
            font-size: 13px;
            table-layout: fixed;
        }
        .lt-table thead th {
            background: linear-gradient(180deg, var(--lt-navy) 0%, #1a3a5c 100%);
            color: var(--lt-gold-light); font-weight: 700; padding: 8px 10px; text-align: left;
            border-bottom: 1px solid #0a1f36; white-space: nowrap;
            position: relative;
            min-width: 72px;
            user-select: none;
        }
        .lt-table thead th .lt-th-inner { display: flex; align-items: center; gap: 4px; }
        .lt-table thead th .lt-drag-hint {
            opacity: 0.85;
            cursor: grab;
            display: inline-flex;
            align-items: center;
        }
        .lt-table thead th .lt-drag-hint i.feather { width: 14px; height: 14px; }
        .lt-resize-handle {
            position: absolute;
            right: 0;
            top: 0;
            width: 6px;
            height: 100%;
            cursor: col-resize;
            z-index: 2;
        }
        .lt-table thead th.lt-num-th,
        .lt-table td.lt-num { text-align: right; }
        .lt-table tbody td { padding: 8px 12px; border-bottom: 1px solid #e8ecf2; color: #1e293b; }
        .lt-table tbody tr:nth-child(even) td { background: #fafbfc; }
        .lt-num { font-variant-numeric: tabular-nums; }
        .col-hidden { display: none !important; }
        .lt-link { color: #2563eb; font-weight: 600; text-decoration: none; }
        .lt-link:hover { text-decoration: underline; }
        .lt-foot-row td { font-weight: 700; background: #eef2f6 !important; border-top: 2px solid #cbd5e1; padding: 10px 12px !important; }
        .lt-empty { text-align: center; padding: 36px; color: #64748b; font-weight: 600; }
        .lt-adv-filter-modal.filter-modal { width: min(520px, calc(100vw - 32px)); border: 1px solid #a78bfa; box-shadow: 0 16px 40px rgba(91, 33, 182, 0.18); }
        .lt-adv-filter-modal .filter-modal-head { border-bottom: 1px solid #ddd6fe; background: linear-gradient(180deg, #faf5ff 0%, #f3e8ff 100%); color: #5b21b6; }
        #ltAdvanceFilterOverlay.filter-modal-overlay { z-index: 1500; }
        #ltPrintArea .dataTables_wrapper .dataTables_length,
        #ltPrintArea .dataTables_wrapper .dataTables_info,
        #ltPrintArea .dataTables_wrapper .dataTables_paginate,
        #ltPrintArea .dataTables_wrapper .dataTables_filter { display: none !important; }
        @media print {
            .sidebar, .layout-navbar, .layout-footer, .layout-overlay, .sidenav, .layout-sidenav, .lt-bar { display: none !important; }
            .layout-content { margin: 0 !important; padding: 8px !important; }
        }
    </style>
</head>
<body class="mfg-page loss-tracking-page">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="layout-content">
    <div class="lt-wrap container-fluid flex-grow-1">
        <div class="lt-head">
            <h1><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('mfg.loss_tracking'), ENT_QUOTES, 'UTF-8') : 'Loss Tracking'; ?></h1>
        </div>
        <div class="lt-bar">
            <div class="lt-search-wrap">
                <label for="ltSearchInput" class="sr-only">Search</label>
                <input type="search" class="lt-search-input" id="ltSearchInput" placeholder="Search" autocomplete="off" aria-label="Search table">
                <span class="lt-search-icon" aria-hidden="true"><i class="feather icon-search"></i></span>
            </div>
            <div class="lt-bar-actions">
                <span class="lt-filter-btn-wrap">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="ltBtnFilter" title="Advance Filter" aria-label="Advance Filter">
                        <i class="feather icon-filter"></i>
                    </button>
                    <?php if ($filter_active_count > 0): ?>
                    <span class="lt-filter-badge" id="ltFilterBadge"><?php echo (int) $filter_active_count; ?></span>
                    <?php endif; ?>
                </span>
                <button type="button" class="btn btn-sm btn-outline-primary" id="ltBtnRefresh" title="Refresh" aria-label="Refresh"><i class="feather icon-refresh-cw"></i></button>
                <div class="dropdown">
                    <button type="button" class="btn btn-sm dropdown-toggle" id="ltBtnExport" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Export <i class="feather icon-chevron-down" style="width:14px;height:14px;vertical-align:middle;"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="ltBtnExport">
                        <a class="dropdown-item" href="#" id="ltExportExcel"><i class="feather icon-download" style="width:16px;height:16px;"></i> Excel</a>
                        <a class="dropdown-item" href="#" id="ltExportPdf"><i class="feather icon-file-text" style="width:16px;height:16px;"></i> PDF (Print)</a>
                    </div>
                </div>
                <div class="lt-toolbar-wrap">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="ltBtnColumns" title="Show / hide columns" aria-label="Columns">
                        <i class="feather icon-settings"></i>
                    </button>
                    <div class="lt-columns-panel" id="ltColumnsPanel">
                        <div class="lt-columns-panel-header">
                            <span>Columns</span>
                            <button type="button" class="close" id="ltColumnsClose" aria-label="Close">&times;</button>
                        </div>
                        <div class="lt-columns-list" id="ltColumnsList"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="lt-table-scroll" id="ltPrintArea">
            <table class="lt-table" id="ltTable" style="margin:0;">
                <thead>
                    <tr id="ltHeaderRow">
                        <?php foreach ($lt_columns as $col): ?>
                        <?php
                        $ck = $col['key'];
                        $numTh = ($ck === 'out_sum' || $ck === 'loss_sum') ? ' lt-num-th' : '';
                        ?>
                        <th<?php echo $numTh !== '' ? ' class="' . htmlspecialchars(trim($numTh), ENT_QUOTES, 'UTF-8') . '"' : ''; ?> data-col="<?php echo htmlspecialchars($ck, ENT_QUOTES, 'UTF-8'); ?>" data-heading="<?php echo htmlspecialchars($col['label'], ENT_QUOTES, 'UTF-8'); ?>" style="width:110px;">
                            <span class="lt-th-inner"><span class="lt-drag-hint" title="Drag to reorder"><i class="feather icon-move"></i></span><?php echo htmlspecialchars($col['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="lt-resize-handle" title="Resize"></span>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody id="ltTableBody">
                    <?php if (empty($report_rows)): ?>
                    <tr><td colspan="<?php echo count($lt_columns); ?>" class="lt-empty">No Rows To Show</td></tr>
                    <?php else: ?>
                    <?php $sr = 0; foreach ($report_rows as $row): $sr++; ?>
                    <tr>
                        <?php foreach ($lt_columns as $col): ?>
                        <?php
                        $ck = $col['key'];
                        $numTd = ($ck === 'out_sum' || $ck === 'loss_sum') ? ' lt-num' : '';
                        ?>
                        <td<?php echo $numTd !== '' ? ' class="' . htmlspecialchars(trim($numTd), ENT_QUOTES, 'UTF-8') . '"' : ''; ?> data-col="<?php echo htmlspecialchars($ck, ENT_QUOTES, 'UTF-8'); ?>"><?php echo lt_body_cell($row, $sr, $ck); ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="lt-foot-row" id="ltFooterRow">
                        <?php foreach ($lt_columns as $col): ?>
                        <?php
                        $ck = $col['key'];
                        $numTd = ($ck === 'out_sum' || $ck === 'loss_sum') ? ' lt-num' : '';
                        ?>
                        <td<?php echo $numTd !== '' ? ' class="' . htmlspecialchars(trim($numTd), ENT_QUOTES, 'UTF-8') . '"' : ''; ?> data-col="<?php echo htmlspecialchars($ck, ENT_QUOTES, 'UTF-8'); ?>"><?php echo lt_footer_cell($ck, $tot_out, $tot_loss); ?></td>
                        <?php endforeach; ?>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<div class="filter-modal-overlay" id="ltAdvanceFilterOverlay" aria-hidden="true">
    <div class="filter-modal lt-adv-filter-modal" role="dialog" aria-modal="true" aria-labelledby="ltAdvFilterTitle">
        <div class="filter-modal-head">
            <span id="ltAdvFilterTitle">Advance Filter</span>
            <button type="button" class="filter-modal-close" id="ltFilterCloseBtn" aria-label="Close">&times;</button>
        </div>
        <form class="filter-modal-body" id="ltAdvanceFilterForm" method="get" action="loss-tracking.php">
            <div class="filter-grid">
                <div class="filter-field filter-field-full">
                    <label for="ltFilterDateFrom">Activity / adjustment date</label>
                    <div class="date-range-inputs">
                        <input type="date" name="f_date_from" id="ltFilterDateFrom" value="<?php echo htmlspecialchars($f_date_from !== '' ? $f_date_from : $default_from, ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="date-range-sep">to</span>
                        <input type="date" name="f_date_to" id="ltFilterDateTo" value="<?php echo htmlspecialchars($f_date_to !== '' ? $f_date_to : $default_to, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
                <div class="filter-field">
                    <label for="ltFilterDept">Department</label>
                    <select name="f_department_id" id="ltFilterDept" class="form-control form-control-sm">
                        <option value="">All</option>
                        <?php foreach ($departments as $d): ?>
                        <option value="<?php echo (int) $d['id']; ?>"<?php echo $f_department_id === (int) $d['id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars($d['dept_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-field filter-field-full">
                    <label for="ltFilterUser">User (ledger)</label>
                    <select name="f_user_id" id="ltFilterUser" class="form-control form-control-sm">
                        <option value="">All</option>
                        <?php
                        if ($f_department_id > 0 && !empty($department_users[$f_department_id])) {
                            foreach ($department_users[$f_department_id] as $uu) {
                                $uid = (int) ($uu['id'] ?? 0);
                                $un = trim((string) ($uu['name'] ?? ''));
                                $sel = ($f_user_id === $uid) ? ' selected' : '';
                                echo '<option value="' . $uid . '"' . $sel . '>' . htmlspecialchars($un, ENT_QUOTES, 'UTF-8') . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="filter-modal-foot">
                <button type="submit" class="btn-filter-apply">Apply Filter</button>
                <button type="button" class="btn-filter-clear" id="ltFilterClearBtn">Clear Filter</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/footer-script.php'; ?>
<script>
(function () {
    function ltDestroyDataTableIfAny() {
        if (typeof jQuery === 'undefined') {
            return;
        }
        try {
            if (jQuery.fn.DataTable && jQuery.fn.DataTable.isDataTable('#ltTable')) {
                jQuery('#ltTable').DataTable().destroy(false);
            }
        } catch (e) {}
    }
    ltDestroyDataTableIfAny();
    if (typeof jQuery !== 'undefined') {
        jQuery(ltDestroyDataTableIfAny);
    }
    window.addEventListener('load', ltDestroyDataTableIfAny);
    setTimeout(ltDestroyDataTableIfAny, 0);
    setTimeout(ltDestroyDataTableIfAny, 400);

    var STORAGE_ORDER = 'loss_tracking_column_order_v1';
    var STORAGE_WIDTHS = 'loss_tracking_column_widths_v1';
    var STORAGE_HIDDEN = 'loss_tracking_column_hidden_v1';

    var table = document.getElementById('ltTable');
    var headerRow = document.getElementById('ltHeaderRow');
    var tbody = document.getElementById('ltTableBody');
    var footerRow = document.getElementById('ltFooterRow');
    var searchEl = document.getElementById('ltSearchInput');

    var overlay = document.getElementById('ltAdvanceFilterOverlay');
    var btnFilter = document.getElementById('ltBtnFilter');
    var btnClose = document.getElementById('ltFilterCloseBtn');
    var btnClear = document.getElementById('ltFilterClearBtn');
    var btnRefresh = document.getElementById('ltBtnRefresh');
    var selDept = document.getElementById('ltFilterDept');
    var selUser = document.getElementById('ltFilterUser');

    if (!table || !headerRow || !tbody) return;

    var defaultOrder = <?php echo json_encode(array_column($lt_columns, 'key')); ?>;
    var colDefs = <?php echo json_encode($lt_columns, JSON_UNESCAPED_UNICODE); ?>;
    var ltExportDates = <?php echo json_encode(['from' => $use_from, 'to' => $use_to], JSON_UNESCAPED_UNICODE); ?>;

    function loadJson(key, fallback) {
        try {
            var r = localStorage.getItem(key);
            return r ? JSON.parse(r) : fallback;
        } catch (e) { return fallback; }
    }

    function saveJson(key, val) {
        try { localStorage.setItem(key, JSON.stringify(val)); } catch (e) {}
    }

    function applyColumnOrder(order) {
        if (!Array.isArray(order) || order.length < 1) return;
        var ths = Array.prototype.slice.call(headerRow.querySelectorAll('th[data-col]'));
        var map = {};
        ths.forEach(function (th) { map[th.getAttribute('data-col')] = th; });
        order.forEach(function (key) {
            var th = map[key];
            if (th) headerRow.appendChild(th);
        });
        tbody.querySelectorAll('tr').forEach(function (tr) {
            if (tr.querySelector('.lt-empty')) return;
            var tds = {};
            tr.querySelectorAll('td[data-col]').forEach(function (td) {
                tds[td.getAttribute('data-col')] = td;
            });
            order.forEach(function (key) {
                var td = tds[key];
                if (td) tr.appendChild(td);
            });
        });
        if (footerRow) {
            var ftds = {};
            footerRow.querySelectorAll('td[data-col]').forEach(function (td) {
                ftds[td.getAttribute('data-col')] = td;
            });
            order.forEach(function (key) {
                var td = ftds[key];
                if (td) footerRow.appendChild(td);
            });
        }
    }

    function applyWidths(widths) {
        if (!widths || typeof widths !== 'object') return;
        headerRow.querySelectorAll('th[data-col]').forEach(function (th) {
            var k = th.getAttribute('data-col');
            if (widths[k] != null && widths[k] > 20) {
                th.style.width = widths[k] + 'px';
                var col = th.cellIndex;
                tbody.querySelectorAll('tr').forEach(function (tr) {
                    var c = tr.children[col];
                    if (c) c.style.width = widths[k] + 'px';
                });
                if (footerRow) {
                    var fc = footerRow.children[col];
                    if (fc) fc.style.width = widths[k] + 'px';
                }
            }
        });
    }

    function applyHidden(hidden) {
        if (!Array.isArray(hidden)) return;
        table.querySelectorAll('th[data-col], td[data-col]').forEach(function (el) {
            var k = el.getAttribute('data-col');
            if (hidden.indexOf(k) >= 0) el.classList.add('col-hidden');
            else el.classList.remove('col-hidden');
        });
    }

    var order = loadJson(STORAGE_ORDER, defaultOrder);
    if (order && order.length) applyColumnOrder(order);
    applyWidths(loadJson(STORAGE_WIDTHS, {}));
    applyHidden(loadJson(STORAGE_HIDDEN, []));

    if (typeof Sortable !== 'undefined' && headerRow) {
        Sortable.create(headerRow, {
            animation: 150,
            handle: '.lt-th-inner',
            draggable: 'th',
            filter: '.lt-resize-handle',
            preventOnFilter: false,
            onEnd: function () {
                var keys = Array.prototype.map.call(headerRow.querySelectorAll('th[data-col]'), function (th) {
                    return th.getAttribute('data-col');
                });
                saveJson(STORAGE_ORDER, keys);
            }
        });
    }

    var resizing = null;
    headerRow.querySelectorAll('.lt-resize-handle').forEach(function (handle) {
        handle.addEventListener('mousedown', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (e.stopImmediatePropagation) e.stopImmediatePropagation();
            var th = handle.closest('th');
            if (!th) return;
            resizing = { th: th, startX: e.pageX, startW: th.offsetWidth };
        });
    });
    document.addEventListener('mousemove', function (e) {
        if (!resizing) return;
        var dx = e.pageX - resizing.startX;
        var nw = Math.max(48, resizing.startW + dx);
        resizing.th.style.width = nw + 'px';
        var col = resizing.th.cellIndex;
        tbody.querySelectorAll('tr').forEach(function (tr) {
            var c = tr.children[col];
            if (c) c.style.width = nw + 'px';
        });
        if (footerRow) {
            var fc = footerRow.children[col];
            if (fc) fc.style.width = nw + 'px';
        }
    });
    document.addEventListener('mouseup', function () {
        if (!resizing) return;
        var widths = loadJson(STORAGE_WIDTHS, {});
        var k = resizing.th.getAttribute('data-col');
        widths[k] = resizing.th.offsetWidth;
        saveJson(STORAGE_WIDTHS, widths);
        resizing = null;
    });

    function rowVisible(tr) {
        if (tr.style && tr.style.display === 'none') return false;
        return true;
    }

    function recalcFooter() {
        if (!footerRow) return;
        var sums = { out_sum: 0, loss_sum: 0 };
        tbody.querySelectorAll('tr').forEach(function (tr) {
            if (tr.querySelector('.lt-empty')) return;
            if (!rowVisible(tr)) return;
            ['out_sum', 'loss_sum'].forEach(function (k) {
                var td = tr.querySelector('td[data-col="' + k + '"]');
                if (!td) return;
                var t = (td.textContent || '').replace(/,/g, '').trim();
                if (t === '' || t === '—') return;
                var n = parseFloat(t);
                if (isFinite(n)) sums[k] += n;
            });
        });
        var fo = footerRow.querySelector('td[data-col="out_sum"]');
        var fl = footerRow.querySelector('td[data-col="loss_sum"]');
        if (fo) fo.textContent = sums.out_sum.toFixed(2);
        if (fl) fl.textContent = sums.loss_sum.toFixed(2);
    }

    function applySearch() {
        var q = (searchEl && searchEl.value) ? String(searchEl.value).trim().toLowerCase() : '';
        tbody.querySelectorAll('tr').forEach(function (tr) {
            if (tr.querySelector('.lt-empty')) return;
            var show = !q;
            if (!show) {
                tr.querySelectorAll('td[data-col]').forEach(function (td) {
                    if (td.classList.contains('col-hidden')) return;
                    var txt = (td.textContent || '').toLowerCase();
                    if (txt.indexOf(q) >= 0) show = true;
                });
            }
            tr.style.display = show ? '' : 'none';
        });
        recalcFooter();
    }

    if (searchEl) {
        searchEl.addEventListener('keyup', applySearch);
        searchEl.addEventListener('input', applySearch);
    }

    function openFilter() { if (overlay) { overlay.classList.add('show'); overlay.setAttribute('aria-hidden', 'false'); } }
    function closeFilter() { if (overlay) { overlay.classList.remove('show'); overlay.setAttribute('aria-hidden', 'true'); } }

    if (btnFilter) btnFilter.addEventListener('click', openFilter);
    if (btnClose) btnClose.addEventListener('click', closeFilter);
    if (overlay) overlay.addEventListener('click', function (e) { if (e.target === overlay) closeFilter(); });

    if (btnClear) btnClear.addEventListener('click', function () {
        window.location.href = 'loss-tracking.php';
    });
    if (btnRefresh) btnRefresh.addEventListener('click', function () {
        window.location.reload();
    });

    var deptUsers = <?php echo json_encode($department_users, JSON_UNESCAPED_UNICODE); ?>;
    function rebuildUserOptions(deptId) {
        if (!selUser) return;
        var cur = selUser.value;
        selUser.innerHTML = '<option value="">All</option>';
        var list = deptUsers[String(deptId)] || deptUsers[deptId] || [];
        if (Array.isArray(list)) {
            list.forEach(function (u) {
                var id = String(u.id || '');
                var name = String(u.name || '');
                var opt = document.createElement('option');
                opt.value = id;
                opt.textContent = name;
                if (cur && id === cur) opt.selected = true;
                selUser.appendChild(opt);
            });
        }
    }
    if (selDept) {
        selDept.addEventListener('change', function () {
            var d = parseInt(selDept.value, 10) || 0;
            rebuildUserOptions(d);
        });
    }

    var colPanel = document.getElementById('ltColumnsPanel');
    var colList = document.getElementById('ltColumnsList');
    var btnColumns = document.getElementById('ltBtnColumns');
    var btnColumnsClose = document.getElementById('ltColumnsClose');

    function countVisibleCols(hidden) {
        var n = 0;
        colDefs.forEach(function (c) {
            if (hidden.indexOf(c.key) < 0) n++;
        });
        return n;
    }

    function buildColumnPanel() {
        if (!colList) return;
        colList.innerHTML = '';
        var hidden = loadJson(STORAGE_HIDDEN, []);
        colDefs.forEach(function (c) {
            var lab = document.createElement('label');
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.checked = hidden.indexOf(c.key) < 0;
            cb.setAttribute('data-col', c.key);
            lab.appendChild(cb);
            lab.appendChild(document.createTextNode(' ' + c.label));
            colList.appendChild(lab);
        });
    }
    buildColumnPanel();

    if (colList) {
        colList.addEventListener('change', function (e) {
            if (e.target.type !== 'checkbox') return;
            var hidden = [];
            colList.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
                if (!cb.checked) hidden.push(cb.getAttribute('data-col'));
            });
            if (countVisibleCols(hidden) < 1) {
                e.target.checked = true;
                return;
            }
            saveJson(STORAGE_HIDDEN, hidden);
            applyHidden(hidden);
            recalcFooter();
        });
    }
    if (btnColumns && colPanel) {
        btnColumns.addEventListener('click', function (e) {
            e.stopPropagation();
            colPanel.classList.toggle('show');
        });
    }
    if (btnColumnsClose && colPanel) {
        btnColumnsClose.addEventListener('click', function () {
            colPanel.classList.remove('show');
        });
    }
    document.addEventListener('click', function (e) {
        if (!colPanel || !btnColumns) return;
        if (!colPanel.contains(e.target) && !btnColumns.contains(e.target)) {
            colPanel.classList.remove('show');
        }
    });

    recalcFooter();

    function getVisibleColKeys() {
        var keys = [];
        headerRow.querySelectorAll('th[data-col]').forEach(function (th) {
            if (th.classList.contains('col-hidden')) return;
            keys.push(th.getAttribute('data-col'));
        });
        return keys;
    }

    function collectLossTrackingExportPayload() {
        var keys = getVisibleColKeys();
        var columns = keys.map(function (k) {
            var th = headerRow.querySelector('th[data-col="' + k + '"]');
            var h = th ? (th.getAttribute('data-heading') || '').trim() : '';
            if (!h && th) h = th.textContent.replace(/\s+/g, ' ').trim();
            return { key: k, label: h || k };
        });
        var rows = [];
        tbody.querySelectorAll('tr').forEach(function (tr) {
            if (tr.querySelector('.lt-empty')) return;
            if (!rowVisible(tr)) return;
            var row = {};
            keys.forEach(function (k) {
                var td = tr.querySelector('td[data-col="' + k + '"]');
                row[k] = td ? String(td.textContent || '').trim() : '';
            });
            rows.push(row);
        });
        var footer = {};
        keys.forEach(function (k) {
            var td = footerRow ? footerRow.querySelector('td[data-col="' + k + '"]') : null;
            footer[k] = td ? String(td.textContent || '').trim() : '';
        });
        var dfInp = document.getElementById('ltFilterDateFrom');
        var dtInp = document.getElementById('ltFilterDateTo');
        var dateFrom = (dfInp && dfInp.value) ? dfInp.value : (ltExportDates.from || '');
        var dateTo = (dtInp && dtInp.value) ? dtInp.value : (ltExportDates.to || '');
        return { columns: columns, rows: rows, footer: footer, date_from: dateFrom, date_to: dateTo };
    }

    function runLossTrackingExcelExport() {
        var pack = collectLossTrackingExportPayload();
        if (!pack.columns.length) return;
        fetch('ajax/export-loss-tracking-excel.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(pack),
            credentials: 'same-origin'
        })
            .then(function (res) {
                if (!res.ok) throw new Error('bad');
                var ct = (res.headers.get('Content-Type') || '').toLowerCase();
                if (ct.indexOf('spreadsheetml') === -1 && ct.indexOf('octet-stream') === -1) throw new Error('bad');
                return res.blob();
            })
            .then(function (blob) {
                var stamp = pack.date_to || pack.date_from || new Date().toISOString().slice(0, 10);
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'Loss_Tracking_Report_' + stamp + '.xlsx';
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
            })
            .catch(function () {
                alert('Excel export failed. Please try again.');
            });
    }

    var ex = document.getElementById('ltExportExcel');
    if (ex) ex.addEventListener('click', function (e) {
        e.preventDefault();
        runLossTrackingExcelExport();
    });
    var pdf = document.getElementById('ltExportPdf');
    if (pdf) pdf.addEventListener('click', function (e) {
        e.preventDefault();
        window.print();
    });
})();
</script>
</body>
</html>
