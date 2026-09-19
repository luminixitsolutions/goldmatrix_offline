<?php
/**
 * Manufacturing Process — Order Tracking modal payload (sale order + JWO + timeline).
 */
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$jwo_id = isset($_GET['jobwork_order_id']) ? (int)$_GET['jobwork_order_id'] : 0;
if ($jwo_id < 1) {
    echo json_encode(['ok' => false, 'message' => 'Invalid job work order']);
    exit;
}

if (!function_exists('getRecord') || !function_exists('getList')) {
    echo json_encode(['ok' => false, 'message' => 'Server error']);
    exit;
}

$jwo = getRecord('SELECT * FROM tbl_jobwork_orders WHERE id = ' . $jwo_id . ' LIMIT 1');
if (!$jwo) {
    echo json_encode(['ok' => false, 'message' => 'Job work order not found']);
    exit;
}

$sale_order_id = (int)($jwo['sale_order_id'] ?? 0);
$so = null;
if ($sale_order_id > 0) {
    $so = getRecord('SELECT * FROM tbl_sale_orders WHERE id = ' . $sale_order_id . ' LIMIT 1');
}

function mp_ot_fmt_date(?string $d): string
{
    if ($d === null || $d === '') {
        return '—';
    }
    $t = strtotime($d);
    return $t ? date('d/m/Y', $t) : '—';
}

function mp_ot_fmt_dt(?string $d): string
{
    if ($d === null || $d === '') {
        return '—';
    }
    $t = strtotime($d);
    return $t ? date('d/m/Y H:i', $t) : '—';
}

/** "CASTING (AJAY)" → CASTING */
function mp_ot_parse_dept_label(string $lbl): string
{
    $lbl = trim($lbl);
    if ($lbl === '' || $lbl === '—') {
        return '';
    }
    $pos = strpos($lbl, ' (');
    if ($pos !== false) {
        return trim(substr($lbl, 0, $pos));
    }

    return $lbl;
}

$dept_name = '';
$worker_name = '';
$did = isset($jwo['department_id']) ? (int)$jwo['department_id'] : 0;
$wid = isset($jwo['department_user_id']) ? (int)$jwo['department_user_id'] : 0;
if ($did > 0) {
    $dr = getRecord('SELECT dept_name FROM tbl_departments WHERE id = ' . $did . ' LIMIT 1');
    if ($dr && isset($dr['dept_name'])) {
        $dept_name = trim((string)$dr['dept_name']);
    }
}
if ($wid > 0) {
    $wr = getRecord('SELECT name FROM tbl_customers WHERE id = ' . $wid . ' LIMIT 1');
    if ($wr && isset($wr['name'])) {
        $worker_name = trim((string)$wr['name']);
    }
}

$tag_no = '';
$soi = @mysqli_query(
    $conn,
    'SELECT barcode FROM tbl_sale_order_items WHERE order_id = ' . $sale_order_id . ' AND TRIM(IFNULL(barcode,\'\')) != \'\' ORDER BY id ASC LIMIT 1'
);
if ($soi && mysqli_num_rows($soi) > 0) {
    $br = mysqli_fetch_assoc($soi);
    $tag_no = trim((string)($br['barcode'] ?? ''));
    mysqli_free_result($soi);
}
if ($tag_no === '') {
    $joi = @mysqli_query(
        $conn,
        'SELECT barcode FROM tbl_jobwork_order_items WHERE jobwork_order_id = ' . $jwo_id . ' AND TRIM(IFNULL(barcode,\'\')) != \'\' ORDER BY id ASC LIMIT 1'
    );
    if ($joi && mysqli_num_rows($joi) > 0) {
        $br = mysqli_fetch_assoc($joi);
        $tag_no = trim((string)($br['barcode'] ?? ''));
        mysqli_free_result($joi);
    }
}
if ($tag_no === '') {
    $tag_no = 'JWO-' . $jwo_id;
}

$jwo_status = trim((string)($jwo['status'] ?? ''));
$so_status = $so ? trim((string)($so['status'] ?? '')) : '';

$completed_date = 'NA';
$completed_raw = null;
if ($jwo_status !== '' && stripos($jwo_status, 'completed') !== false) {
    $completed_raw = $jwo['updated_at'] ?? $jwo['due_date'] ?? null;
}
if ($so && $completed_raw === null && $so_status !== '' && stripos($so_status, 'completed') !== false) {
    $completed_raw = $so['updated_at'] ?? $so['due_date'] ?? null;
}
if ($completed_raw) {
    $completed_date = mp_ot_fmt_date(is_string($completed_raw) ? $completed_raw : '');
}

$order_date_header = $so ? mp_ot_fmt_date($so['order_date'] ?? '') : mp_ot_fmt_date($jwo['order_date'] ?? '');

$sale_block = [
    'order_no' => $so ? trim((string)($so['order_no'] ?? '')) : trim((string)($jwo['sale_order_no'] ?? '')),
    'customer_name' => $so ? trim((string)($so['customer_name'] ?? '')) : trim((string)($jwo['customer_name'] ?? '')),
    'order_date' => $so ? mp_ot_fmt_date($so['order_date'] ?? '') : mp_ot_fmt_date($jwo['order_date'] ?? ''),
    'delivery_date' => $so ? mp_ot_fmt_date($so['due_date'] ?? '') : mp_ot_fmt_date($jwo['due_date'] ?? ''),
    'status' => $so_status !== '' ? $so_status : ($jwo_status !== '' ? $jwo_status : 'Processing'),
];

$jwo_queue_header = '';
if (isset($jwo['jobwork_queue_no'])) {
    $jwo_queue_header = trim((string)$jwo['jobwork_queue_no']);
}

$job_block = [
    'jobwork_no' => trim((string)($jwo['jobwork_no'] ?? '')) !== '' ? trim((string)$jwo['jobwork_no']) : ('JWO-' . $jwo_id),
    'jobwork_queue_no' => $jwo_queue_header !== '' ? $jwo_queue_header : '—',
    'customer_name' => trim((string)($jwo['customer_name'] ?? '')),
    'assign_to' => $worker_name !== '' ? strtoupper($worker_name) : '—',
    'order_date' => mp_ot_fmt_date($jwo['order_date'] ?? ''),
    'delivery_date' => mp_ot_fmt_date($jwo['due_date'] ?? ''),
];

$ji_cols_ot = [];
$icq_ot = @mysqli_query($conn, 'SHOW COLUMNS FROM tbl_jobwork_order_items');
if ($icq_ot) {
    while ($icr = mysqli_fetch_assoc($icq_ot)) {
        $ji_cols_ot[$icr['Field']] = true;
    }
    mysqli_free_result($icq_ot);
}

/** @return array{0: float, 1: float} [weight_sum, qty_sum] */
function mp_ot_line_totals_from_items($conn, int $jwo_id, array $ji_cols): array
{
    if (empty($ji_cols)) {
        return [0.0, 0.0];
    }
    $whens = [];
    if (!empty($ji_cols['final_weight'])) {
        $whens[] = 'WHEN COALESCE(ji.final_weight, 0) > 0.0000001 THEN ji.final_weight';
    }
    if (!empty($ji_cols['net_weight'])) {
        $whens[] = 'WHEN COALESCE(ji.net_weight, 0) > 0.0000001 THEN ji.net_weight';
    }
    if (!empty($ji_cols['gross_weight'])) {
        $whens[] = 'WHEN COALESCE(ji.gross_weight, 0) > 0.0000001 THEN ji.gross_weight';
    }
    if (empty($whens)) {
        return [0.0, 0.0];
    }
    $case = 'CASE ' . implode(' ', $whens) . ' ELSE 0 END';
    $sql = 'SELECT COALESCE(SUM(' . $case . '), 0) AS sw, COALESCE(SUM(COALESCE(ji.quantity, 0)), 0) AS sq FROM tbl_jobwork_order_items ji WHERE ji.jobwork_order_id = ' . $jwo_id;
    $r = function_exists('getRecord') ? getRecord($sql) : null;
    if (!$r) {
        return [0.0, 0.0];
    }
    return [(float)($r['sw'] ?? 0), (float)($r['sq'] ?? 0)];
}

list($cur_wt_sum, $cur_qty_sum) = mp_ot_line_totals_from_items($conn, $jwo_id, $ji_cols_ot);

$fmt_wt = function ($v) {
    if ($v === null || $v <= 0) {
        return 'NA';
    }
    return number_format($v, 3, '.', '');
};
$fmt_qty = function ($v) {
    if ($v === null || $v <= 0) {
        return 'NA';
    }
    $n = round((float)$v, 4);
    if (abs($n - round($n)) < 0.0001) {
        return (string)(int)round($n);
    }
    return rtrim(rtrim(sprintf('%.2f', $n), '0'), '.');
};

$arrival_by_dept = [];
$has_dept_transfer_logged = false;
$queue_activity_history = [];
$achk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_queue_activity'");
if ($achk && mysqli_num_rows($achk) > 0) {
    mysqli_free_result($achk);
    $act_sel = 'id, to_dept_id, from_dept_id, jobwork_queue_no, created_at';
    $act_has_action = false;
    $act_acol = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_queue_activity LIKE 'activity_action'");
    if ($act_acol && mysqli_num_rows($act_acol) > 0) {
        $act_sel .= ', activity_action';
        $act_has_action = true;
    }
    if ($act_acol) {
        mysqli_free_result($act_acol);
    }
    $act_has_totals = false;
    $act_twcol = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_queue_activity LIKE 'total_wt_after'");
    if ($act_twcol && mysqli_num_rows($act_twcol) > 0) {
        $act_sel .= ', total_wt_after, total_qty_after';
        $act_has_totals = true;
    }
    if ($act_twcol) {
        mysqli_free_result($act_twcol);
    }
    $act_uicol = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_queue_activity LIKE 'from_user_id'");
    $act_has_users = ($act_uicol && mysqli_num_rows($act_uicol) > 0);
    if ($act_uicol) {
        mysqli_free_result($act_uicol);
    }
    if ($act_has_users) {
        $act_sel .= ', from_user_id, to_user_id';
    }
    $act_list = getList(
        'SELECT ' . $act_sel . ' FROM tbl_jobwork_queue_activity WHERE jobwork_order_id = ' . $jwo_id . ' ORDER BY id ASC'
    );
    if (is_array($act_list)) {
        foreach ($act_list as $ar) {
            $an = isset($ar['activity_action']) ? strtolower(trim((string)$ar['activity_action'])) : '';
            if ($an === 'department_transfer') {
                $has_dept_transfer_logged = true;
                break;
            }
        }
    }
    if (is_array($act_list)) {
        foreach ($act_list as $ar) {
            $tid = (int)($ar['to_dept_id'] ?? 0);
            if ($tid < 1) {
                continue;
            }
            $wt = null;
            if (isset($ar['total_wt_after']) && $ar['total_wt_after'] !== null && $ar['total_wt_after'] !== '') {
                $wt = (float)$ar['total_wt_after'];
                if (!is_finite($wt) || $wt <= 0.0000001) {
                    $wt = null;
                }
            }
            $q = null;
            if (isset($ar['total_qty_after']) && $ar['total_qty_after'] !== null && $ar['total_qty_after'] !== '') {
                $q = (float)$ar['total_qty_after'];
                if (!is_finite($q) || $q <= 0.0000001) {
                    $q = null;
                }
            }
            $qn_arrival = trim((string)($ar['jobwork_queue_no'] ?? ''));
            $arrival_by_dept[$tid] = [
                'wt' => $wt,
                'qty' => $q,
                'queue_no' => $qn_arrival !== '' ? $qn_arrival : null,
                'arrival_at' => mp_ot_fmt_dt($ar['created_at'] ?? ''),
            ];
        }
    }

    $hist_sql = 'SELECT a.id, a.jobwork_queue_no, a.created_at, a.from_dept_id, a.to_dept_id';
    if ($act_has_action) {
        $hist_sql .= ', a.activity_action';
    } else {
        $hist_sql .= ', NULL AS activity_action';
    }
    if ($act_has_totals) {
        $hist_sql .= ', a.total_wt_after, a.total_qty_after';
    } else {
        $hist_sql .= ', NULL AS total_wt_after, NULL AS total_qty_after';
    }
    $hist_sql .= ', fd.dept_name AS from_dept_name, td.dept_name AS to_dept_name';
    if ($act_has_users) {
        $hist_sql .= ', fu.name AS from_user_name, tu.name AS to_user_name';
    } else {
        $hist_sql .= ', NULL AS from_user_name, NULL AS to_user_name';
    }
    $hist_sql .= ' FROM tbl_jobwork_queue_activity a'
        . ' LEFT JOIN tbl_departments fd ON fd.id = a.from_dept_id'
        . ' LEFT JOIN tbl_departments td ON td.id = a.to_dept_id';
    if ($act_has_users) {
        $hist_sql .= ' LEFT JOIN tbl_customers fu ON fu.id = a.from_user_id'
            . ' LEFT JOIN tbl_customers tu ON tu.id = a.to_user_id';
    }
    $hist_sql .= ' WHERE a.jobwork_order_id = ' . $jwo_id . ' ORDER BY a.id ASC';
    $hist_list = getList($hist_sql);
    if (is_array($hist_list)) {
        foreach ($hist_list as $hr) {
            $act = isset($hr['activity_action']) ? strtolower(trim((string)$hr['activity_action'])) : '';
            $act_label = $act === 'department_transfer' ? 'Department transfer' : ($act === 'jobwork_create' ? 'Job work created' : ($act !== '' ? $act : 'Queue event'));
            $fqn = trim((string)($hr['jobwork_queue_no'] ?? ''));
            if ($fqn === '') {
                $fqn = '—';
            }
            $fdn = strtoupper(trim((string)($hr['from_dept_name'] ?? '')));
            $tdn = strtoupper(trim((string)($hr['to_dept_name'] ?? '')));
            $fun = trim((string)($hr['from_user_name'] ?? ''));
            $tun = trim((string)($hr['to_user_name'] ?? ''));
            $from_lbl = $fdn !== '' ? $fdn : '—';
            if ($fun !== '') {
                $from_lbl .= ' (' . strtoupper($fun) . ')';
            }
            $to_lbl = $tdn !== '' ? $tdn : '—';
            if ($tun !== '') {
                $to_lbl .= ' (' . strtoupper($tun) . ')';
            }
            $tw_h = isset($hr['total_wt_after']) ? (float)$hr['total_wt_after'] : 0.0;
            $tq_h = isset($hr['total_qty_after']) ? (float)$hr['total_qty_after'] : 0.0;
            $queue_activity_history[] = [
                'queue_no' => $fqn,
                'date_time' => mp_ot_fmt_dt($hr['created_at'] ?? ''),
                'action' => $act_label,
                'to_department' => $to_lbl,
                'from_department' => $from_lbl,
                'to_dept_id' => (int) ($hr['to_dept_id'] ?? 0),
                'from_dept_id' => (int) ($hr['from_dept_id'] ?? 0),
                'flow' => $from_lbl . ' → ' . $to_lbl,
                'total_weight' => ($tw_h > 0.0000001) ? number_format($tw_h, 3, '.', '') : '—',
                'total_quantity' => ($tq_h > 0.0000001) ? $fmt_qty($tq_h) : '—',
            ];
        }
    }

    /* Current JWQ cycle missing from activity log (older saves) — show it in history for multi-visit departments. */
    if ($jwo_queue_header !== '' && $queue_activity_history !== []) {
        $qn_listed = [];
        foreach ($queue_activity_history as $hx) {
            $qn_listed[trim((string) ($hx['queue_no'] ?? ''))] = true;
        }
        if (!isset($qn_listed[$jwo_queue_header])) {
            $last_h = $queue_activity_history[count($queue_activity_history) - 1];
            $to_lbl_cur = $dept_name !== '' ? strtoupper($dept_name) : '—';
            if ($worker_name !== '') {
                $to_lbl_cur .= ' (' . strtoupper($worker_name) . ')';
            }
            $from_lbl_cur = trim((string) ($last_h['to_department'] ?? ''));
            if ($from_lbl_cur === '') {
                $from_lbl_cur = '—';
            }
            $dt_raw = $jwo['updated_at'] ?? $jwo['order_date'] ?? null;
            $queue_activity_history[] = [
                'queue_no' => $jwo_queue_header,
                'date_time' => mp_ot_fmt_dt(is_string($dt_raw) ? $dt_raw : null),
                'action' => 'Department transfer',
                'to_department' => $to_lbl_cur,
                'from_department' => $from_lbl_cur,
                'to_dept_id' => $cur_dept_id,
                'from_dept_id' => (int) ($last_h['to_dept_id'] ?? 0),
                'flow' => $from_lbl_cur . ' → ' . $to_lbl_cur,
                'total_weight' => $fmt_wt($cur_wt_sum),
                'total_quantity' => $fmt_qty($cur_qty_sum),
            ];
        }
    }
} elseif ($achk) {
    mysqli_free_result($achk);
}

$jwo_od_str = mp_ot_fmt_date($jwo['order_date'] ?? '');
$cur_dept_id = isset($jwo['department_id']) ? (int) $jwo['department_id'] : 0;

/** Diagram = one node per department transfer (destination), same order as job queue history table. */
$process_steps = [];
$transfer_hist = [];
foreach ($queue_activity_history as $h) {
    if (!is_array($h)) {
        continue;
    }
    $act = strtolower(trim((string) ($h['action'] ?? '')));
    if ($act !== 'department transfer') {
        continue;
    }
    $transfer_hist[] = $h;
}

if ($transfer_hist !== []) {
    $first_from_name = mp_ot_parse_dept_label((string) ($transfer_hist[0]['from_department'] ?? ''));
    $first_to_name = mp_ot_parse_dept_label((string) ($transfer_hist[0]['to_department'] ?? ''));
    if ($first_from_name !== '' && strcasecmp($first_from_name, $first_to_name) !== 0) {
        $process_steps[] = [
            'title' => strtoupper($first_from_name),
            'state' => 'completed',
            'order_date' => $jwo_od_str,
            'completed_date' => 'NA',
            'total_weight' => 'NA',
            'total_quantity' => 'NA',
            'job_queue_no' => 'NA',
            'transfer_at' => 'NA',
        ];
    }
    foreach ($transfer_hist as $h) {
        $to_name = mp_ot_parse_dept_label((string) ($h['to_department'] ?? ''));
        if ($to_name === '') {
            continue;
        }
        $qn = trim((string) ($h['queue_no'] ?? ''));
        $process_steps[] = [
            'title' => strtoupper($to_name),
            'state' => 'completed',
            'order_date' => $jwo_od_str,
            'completed_date' => 'NA',
            'total_weight' => (string) ($h['total_weight'] ?? '—'),
            'total_quantity' => (string) ($h['total_quantity'] ?? '—'),
            'job_queue_no' => ($qn !== '' && $qn !== '—') ? $qn : 'NA',
            'transfer_at' => (string) ($h['date_time'] ?? 'NA'),
            '_to_dept_id' => (int) ($h['to_dept_id'] ?? 0),
        ];
    }
    $last_ix = count($process_steps) - 1;
    if ($last_ix >= 0) {
        $last_to_id = (int) ($process_steps[$last_ix]['_to_dept_id'] ?? 0);
        if ($cur_dept_id > 0 && $last_to_id === $cur_dept_id) {
            $process_steps[$last_ix]['state'] = 'active';
            if (!$has_dept_transfer_logged) {
                $process_steps[$last_ix]['total_weight'] = 'NA';
                $process_steps[$last_ix]['total_quantity'] = 'NA';
            } elseif ($cur_wt_sum > 0.0000001 || $cur_qty_sum > 0.0000001) {
                $process_steps[$last_ix]['total_weight'] = $fmt_wt($cur_wt_sum);
                $process_steps[$last_ix]['total_quantity'] = $fmt_qty($cur_qty_sum);
            }
        } else {
            $process_steps[$last_ix]['state'] = 'completed';
        }
        for ($i = 0; $i < $last_ix; $i++) {
            $process_steps[$i]['state'] = 'completed';
        }
    }
    foreach ($process_steps as &$ps_clean) {
        unset($ps_clean['_to_dept_id']);
    }
    unset($ps_clean);
} else {
    $dept_rows = getList(
        "SELECT id, dept_name FROM tbl_departments WHERE status = 1 AND LOWER(TRIM(dept_name)) NOT LIKE '%cancel%' ORDER BY id ASC"
    );
    if (!is_array($dept_rows)) {
        $dept_rows = [];
    }
    $cur_idx = null;
    foreach ($dept_rows as $ix => $dr) {
        if ((int) ($dr['id'] ?? 0) === $cur_dept_id) {
            $cur_idx = $ix;
            break;
        }
    }
    $mp_ot_step_queue_no = function ($state, $dept_id) use ($arrival_by_dept, $jwo_queue_header) {
        if ($state === 'pending') {
            return 'NA';
        }
        if ($state === 'active') {
            if ($jwo_queue_header !== '') {
                return $jwo_queue_header;
            }
            if (isset($arrival_by_dept[$dept_id]['queue_no']) && $arrival_by_dept[$dept_id]['queue_no'] !== null && $arrival_by_dept[$dept_id]['queue_no'] !== '') {
                return (string) $arrival_by_dept[$dept_id]['queue_no'];
            }

            return 'NA';
        }
        if (isset($arrival_by_dept[$dept_id]['queue_no']) && $arrival_by_dept[$dept_id]['queue_no'] !== null && $arrival_by_dept[$dept_id]['queue_no'] !== '') {
            return (string) $arrival_by_dept[$dept_id]['queue_no'];
        }

        return 'NA';
    };
    $mp_ot_step_transfer_at = function ($state, $dept_id) use ($arrival_by_dept) {
        if ($state === 'pending') {
            return 'NA';
        }
        if (isset($arrival_by_dept[$dept_id]['arrival_at']) && $arrival_by_dept[$dept_id]['arrival_at'] !== '' && $arrival_by_dept[$dept_id]['arrival_at'] !== '—') {
            return (string) $arrival_by_dept[$dept_id]['arrival_at'];
        }

        return 'NA';
    };
    $mp_ot_step_wt_qty = function ($state, $dept_id) use ($fmt_wt, $fmt_qty, $arrival_by_dept, $cur_wt_sum, $cur_qty_sum, $has_dept_transfer_logged) {
        if ($state === 'active') {
            if (!$has_dept_transfer_logged) {
                return ['NA', 'NA'];
            }

            return [$fmt_wt($cur_wt_sum), $fmt_qty($cur_qty_sum)];
        }
        if ($state === 'pending') {
            return ['NA', 'NA'];
        }
        if (isset($arrival_by_dept[$dept_id])) {
            $a = $arrival_by_dept[$dept_id];

            return [$fmt_wt($a['wt']), $fmt_qty($a['qty'])];
        }

        return ['NA', 'NA'];
    };

    if ($cur_idx === null && $cur_dept_id > 0) {
        $dept_u = $dept_name !== '' ? strtoupper($dept_name) : 'DEPARTMENT';
        list($tw_s, $tq_s) = $mp_ot_step_wt_qty('active', $cur_dept_id);
        $process_steps = [[
            'title' => $dept_u,
            'state' => 'active',
            'order_date' => $jwo_od_str,
            'completed_date' => 'NA',
            'total_weight' => $tw_s,
            'total_quantity' => $tq_s,
            'job_queue_no' => $mp_ot_step_queue_no('active', $cur_dept_id),
            'transfer_at' => $mp_ot_step_transfer_at('active', $cur_dept_id),
        ]];
    } elseif ($dept_rows === []) {
        $dept_u = $dept_name !== '' ? strtoupper($dept_name) : 'DEPARTMENT';
        list($tw_s, $tq_s) = $mp_ot_step_wt_qty('active', $cur_dept_id);
        $process_steps = [[
            'title' => $dept_u,
            'state' => 'active',
            'order_date' => $jwo_od_str,
            'completed_date' => 'NA',
            'total_weight' => $tw_s,
            'total_quantity' => $tq_s,
            'job_queue_no' => $mp_ot_step_queue_no('active', $cur_dept_id),
            'transfer_at' => $mp_ot_step_transfer_at('active', $cur_dept_id),
        ]];
    } else {
        if ($cur_idx === null) {
            $cur_idx = 0;
        }
        foreach ($dept_rows as $ix => $dr) {
            $title = strtoupper(trim((string) ($dr['dept_name'] ?? '')));
            if ($title === '') {
                continue;
            }
            $did = (int) ($dr['id'] ?? 0);
            if ($ix < $cur_idx) {
                $state = 'completed';
            } elseif ($ix === $cur_idx) {
                $state = 'active';
            } else {
                $state = 'pending';
            }
            list($tw_s, $tq_s) = $mp_ot_step_wt_qty($state, $did);
            $process_steps[] = [
                'title' => $title,
                'state' => $state,
                'order_date' => $jwo_od_str,
                'completed_date' => 'NA',
                'total_weight' => $tw_s,
                'total_quantity' => $tq_s,
                'job_queue_no' => $mp_ot_step_queue_no($state, $did),
                'transfer_at' => $mp_ot_step_transfer_at($state, $did),
            ];
        }
    }
}

// Legacy timeline (optional: comments) — kept for compatibility; UI uses process_steps
$timeline = [];
$comments_timeline = [];
$chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_order_comments'");
if ($chk && mysqli_num_rows($chk) > 0) {
    mysqli_free_result($chk);
    $cq = @mysqli_query(
        $conn,
        'SELECT comment_text, created_at FROM tbl_jobwork_order_comments WHERE jobwork_order_id = '
        . $jwo_id . ' ORDER BY id ASC LIMIT 30'
    );
    if ($cq) {
        while ($cr = mysqli_fetch_assoc($cq)) {
            $comments_timeline[] = [
                'kind' => 'comment',
                'title' => 'COMMENT',
                'subtitle' => trim((string)($cr['comment_text'] ?? '')),
                'at' => mp_ot_fmt_dt($cr['created_at'] ?? ''),
            ];
        }
        mysqli_free_result($cq);
    }
} elseif ($chk) {
    mysqli_free_result($chk);
}
$timeline = $comments_timeline;

$images = [];

echo json_encode([
    'ok' => true,
    'tag_no' => $tag_no,
    'title_bar' => $tag_no . ' - Order Tracking',
    'sale_order' => $sale_block,
    'jobwork_order' => $job_block,
    'order_date_top' => $order_date_header,
    'completed_date' => $completed_date,
    'process_steps' => $process_steps,
    'queue_activity_history' => $queue_activity_history,
    'timeline' => $timeline,
    'images' => $images,
], JSON_UNESCAPED_UNICODE);
