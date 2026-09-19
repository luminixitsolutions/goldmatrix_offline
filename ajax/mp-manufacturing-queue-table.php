<?php
/**
 * Manufacturing Process — full table: Jobwork Queue activity + weight adjustments (all mfg queue columns).
 */
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_department_schema.php';
auragold_ensure_department_schema($conn);
require_once __DIR__ . '/../includes/auragold_mfg_jobwork_queue_line_weights.php';

header('Content-Type: application/json; charset=utf-8');

/** When set, only rows for this job work order are returned (job card print / history). */
$filter_jobwork_order_id = isset($_GET['jobwork_order_id']) ? (int) $_GET['jobwork_order_id'] : 0;

$jchk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_order_items'");
$has_items = ($jchk && mysqli_num_rows($jchk) > 0);
if ($jchk) {
    mysqli_free_result($jchk);
}

$ji_cols = [];
if ($has_items) {
    $ji_cols_q = @mysqli_query($conn, 'SHOW COLUMNS FROM tbl_jobwork_order_items');
    if ($ji_cols_q) {
        while ($c = mysqli_fetch_assoc($ji_cols_q)) {
            $fn = (string)($c['Field'] ?? '');
            if ($fn !== '') {
                $ji_cols[$fn] = true;
            }
        }
        mysqli_free_result($ji_cols_q);
    }
}

$ji = function ($expr) use ($has_items) {
    return $has_items ? '(SELECT ' . $expr . ' FROM tbl_jobwork_order_items ji WHERE ji.jobwork_order_id = j.id ORDER BY ji.id ASC LIMIT 1)' : 'NULL';
};

$ji_tag = $ji('ji.barcode');
$ji_design = $ji('ji.design_no');
$ji_product = $ji('ji.product_name');
$ji_qty = $ji('ji.quantity');
$ji_carat = $ji('ji.carat');
$ji_final = $ji('ji.final_weight');
$ji_net = $ji('ji.net_weight');
$ji_gross = $ji('ji.gross_weight');
$ji_less = $ji('ji.less_weight');
$ji_purity_w = $ji('ji.purity_weight');
$ji_net_amt = $ji('ji.net_amount');
$ji_diamond_expr = 'NULL';
if (!empty($ji_cols['diamond_weight']) && !empty($ji_cols['diamond_wt'])) {
    $ji_diamond_expr = 'COALESCE(ji.diamond_weight, ji.diamond_wt, 0)';
} elseif (!empty($ji_cols['diamond_weight'])) {
    $ji_diamond_expr = 'COALESCE(ji.diamond_weight, 0)';
} elseif (!empty($ji_cols['diamond_wt'])) {
    $ji_diamond_expr = 'COALESCE(ji.diamond_wt, 0)';
}
$ji_diamond = $ji($ji_diamond_expr);

/** First line loss saved from Jobwork Queue (manual loss); matches mp-save-jobwork-queue.php column choice. */
$ji_line_loss = 'NULL';
if (!empty($ji_cols['gold_loss_1'])) {
    $ji_line_loss = $ji('COALESCE(ji.gold_loss_1, 0)');
} elseif (!empty($ji_cols['loss_wt'])) {
    $ji_line_loss = $ji('COALESCE(ji.loss_wt, 0)');
}

$mfg_sec = '';
$sc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_orders LIKE 'manufacturing_time_seconds'");
if ($sc && mysqli_num_rows($sc) > 0) {
    $mfg_sec = ', j.manufacturing_time_seconds';
}
if ($sc) {
    mysqli_free_result($sc);
}

function mp_mfg_fmt_wt($n) {
    if ($n === null || $n === '') {
        return '—';
    }
    $f = (float)$n;
    return number_format($f, 3, '.', '');
}

function mp_mfg_fmt_money($n) {
    if ($n === null || $n === '') {
        return '—';
    }
    $f = (float)$n;
    return number_format($f, 2, '.', '');
}

function mp_mfg_dt($dt) {
    if ($dt === null || $dt === '') {
        return '—';
    }
    $t = strtotime($dt);
    if ($t === false) {
        return '—';
    }
    return date('d-m-Y H:i:s', $t);
}

function mp_mfg_sort_ts($dt) {
    if ($dt === null || $dt === '') {
        return 0;
    }
    $t = strtotime($dt);
    return ($t === false) ? 0 : $t;
}

function mp_mfg_infer_metal($product) {
    $p = strtolower((string)$product);
    if (strpos($p, 'gold') !== false) {
        return 'Gold';
    }
    if (strpos($p, 'silver') !== false) {
        return 'Silver';
    }
    if (strpos($p, 'platinum') !== false) {
        return 'Platinum';
    }
    return '—';
}

/** Job card / queue: "CASTING (VIJAY) ==> SETTING (AJAY)" style labels. */
function mp_mfg_flow_stage($dept, $user) {
    $d = strtoupper(trim((string)$dept));
    $u = strtoupper(trim((string)$user));
    if ($d === '' && $u === '') {
        return '—';
    }
    if ($d === '') {
        return $u !== '' ? $u : '—';
    }
    if ($u === '') {
        return $d;
    }
    return $d . ' (' . $u . ')';
}

function mp_mfg_diamond_wt($storedDiamond, $gross, $net, $less) {
    $sd = ($storedDiamond !== null && $storedDiamond !== '') ? (float)$storedDiamond : null;
    if ($sd !== null && is_finite($sd) && $sd >= 0) {
        return mp_mfg_fmt_wt($sd);
    }
    $g = ($gross !== null && $gross !== '') ? (float)$gross : null;
    $n = ($net !== null && $net !== '') ? (float)$net : null;
    $l = ($less !== null && $less !== '') ? (float)$less : null;
    if ($g === null || !is_finite($g)) {
        return '—';
    }
    if ($n === null || !is_finite($n)) {
        $n = 0;
    }
    if ($l === null || !is_finite($l)) {
        $l = 0;
    }
    $d = $g - $n - $l;
    if ($d < 0) {
        $d = 0;
    }
    return mp_mfg_fmt_wt($d);
}

/**
 * Order line diamond (first order line) when greater than zero, else total issued diamond weight for the jobwork order
 * (material grid / tbl_jobwork_queue_diamond_stock_issue). Returns null when both are zero so
 * mp_mfg_diamond_wt can infer from gross / net / less.
 *
 * @param array<string,mixed> $r
 * @param array<int,float>    $sumByJwo jobwork_order_id to grams
 */
function mp_mfg_merged_stored_diamond_for_history(array $r, array $sumByJwo): ?float
{
    $jid = (int) ($r['jobwork_order_id'] ?? 0);
    $raw = $r['item_diamond_wt'] ?? null;
    $sv = ($raw !== null && $raw !== '') ? (float) $raw : 0.0;
    if (!is_finite($sv) || $sv < 0) {
        $sv = 0.0;
    }
    if ($sv > 0.00005) {
        return $sv;
    }
    if ($jid > 0) {
        $is = (float) ($sumByJwo[$jid] ?? 0.0);
        if (is_finite($is) && $is > 0.00005) {
            return $is;
        }
    }

    return null;
}

/**
 * Loss (grams) for manufacturing stock grid: saved line loss, else auto-loss adjustment match, else net − final.
 */
function mp_mfg_resolve_loss_grams($lineLossSaved, $transferLossAdj, $netWt, $finalWt) {
    $ll = $lineLossSaved !== null && $lineLossSaved !== '' ? (float) $lineLossSaved : 0.0;
    if (is_finite($ll) && $ll > 0.00001) {
        return $ll;
    }
    $ta = $transferLossAdj !== null && $transferLossAdj !== '' ? (float) $transferLossAdj : 0.0;
    if (is_finite($ta) && $ta > 0.00001) {
        return $ta;
    }
    $n = $netWt !== null && $netWt !== '' ? (float) $netWt : null;
    $f = $finalWt !== null && $finalWt !== '' ? (float) $finalWt : null;
    if ($n !== null && $f !== null && is_finite($n) && is_finite($f) && $n > $f + 0.00001) {
        return $n - $f;
    }
    return null;
}

/**
 * Per-transfer weight display for activity rows (uses snapshot columns when present).
 *
 * @param array<string,mixed> $r
 * @param array<string,bool>  $ji_cols
 * @param array<int,float>    $sumByJwo
 * @return array{total_wt:string,metal_wt:string,loss_wt:string,dust_wastage_wt:string,balance_wt:string,loss_grams:?float}
 */
function mp_mfg_activity_history_weights(array $r, array $ji_cols, array $sumByJwo): array
{
    $twAfter = (isset($r['total_wt_after']) && $r['total_wt_after'] !== null && $r['total_wt_after'] !== '')
        ? (float) $r['total_wt_after'] : null;
    $mwAfter = (isset($r['metal_wt_after']) && $r['metal_wt_after'] !== null && $r['metal_wt_after'] !== '')
        ? (float) $r['metal_wt_after'] : null;
    $lwAfter = (isset($r['loss_wt_after']) && $r['loss_wt_after'] !== null && $r['loss_wt_after'] !== '')
        ? (float) $r['loss_wt_after'] : null;

    $fw = isset($r['item_final_wt']) ? (float) $r['item_final_wt'] : null;
    $nw = isset($r['item_net_wt']) ? (float) $r['item_net_wt'] : null;
    $gross = $r['item_gross_wt'] ?? null;
    $gw = ($gross !== null && $gross !== '') ? (float) $gross : null;
    $lineLossSaved = $r['item_line_loss_wt'] ?? null;
    $tloss = isset($r['transfer_loss_wt']) ? (float) $r['transfer_loss_wt'] : 0.0;
    if (!is_finite($tloss)) {
        $tloss = 0.0;
    }

    $lossGrams = ($lwAfter !== null && is_finite($lwAfter) && $lwAfter > 0.00001)
        ? $lwAfter
        : null;
    if ($lossGrams === null && $tloss > 0.00001) {
        $lossGrams = $tloss;
    }
    if ($lossGrams === null) {
        $n = $nw !== null && is_finite($nw) ? (float) $nw : null;
        $f = $fw !== null && is_finite($fw) ? (float) $fw : null;
        if ($n !== null && $f !== null && $n > $f + 0.00001) {
            $lossGrams = $n - $f;
        }
    }

    if ($twAfter === null || !is_finite($twAfter) || $twAfter <= 0.0000001) {
        $mergedLineDiamond = mp_mfg_merged_stored_diamond_for_history($r, $sumByJwo);
        $diamondForSynth = $mergedLineDiamond !== null ? $mergedLineDiamond : ($r['item_diamond_wt'] ?? null);
        $actSynth = [
            'final_weight' => ($fw !== null && is_finite($fw) && (float) $fw > 0.0000001) ? (float) $fw : null,
            'net_weight' => $nw,
            'gross_weight' => $gw,
            'diamond_weight' => $diamondForSynth,
            'diamond_wt' => $diamondForSynth,
            'gold_loss_1' => $lineLossSaved,
            'loss_wt' => $lineLossSaved,
        ];
        $twAfter = function_exists('auragold_mfg_jobwork_line_calculated_total_wt')
            ? auragold_mfg_jobwork_line_calculated_total_wt($actSynth, $ji_cols)
            : 0.0;
    }

    $diamondGrams = 0.0;
    $mergedDiamond = mp_mfg_merged_stored_diamond_for_history($r, $sumByJwo);
    if ($mergedDiamond !== null && is_finite($mergedDiamond) && $mergedDiamond > 0.00005) {
        $diamondGrams = (float) $mergedDiamond;
    } else {
        $rawD = $r['item_diamond_wt'] ?? null;
        if ($rawD !== null && $rawD !== '' && is_finite((float) $rawD) && (float) $rawD > 0.00005) {
            $diamondGrams = (float) $rawD;
        }
    }
    $lossAdd = ($lossGrams !== null && is_finite($lossGrams) && $lossGrams > 0) ? (float) $lossGrams : 0.0;

    /* Each inward/outward row = one transfer event — use that activity's snapshot weights first. */
    $snapMw = ($mwAfter !== null && is_finite($mwAfter) && $mwAfter > 0.0000001) ? (float) $mwAfter : null;
    if ($snapMw === null && $twAfter !== null && is_finite($twAfter) && $twAfter > 0.0000001) {
        /* Legacy rows: metal snapshot missing — carrying total is the metal column on transfer. */
        $snapMw = max(0.0, (float) $twAfter - $diamondGrams);
    }
    if ($snapMw !== null) {
        $mwAfter = $snapMw;
    } elseif ($nw !== null && is_finite($nw) && $nw > 0.0000001) {
        $mwAfter = (float) $nw;
        if ($lossAdd <= 0.0000001 && $diamondGrams <= 0.0000001 && $nw < (float) $twAfter - 0.051) {
            $mwAfter = (float) $twAfter;
        }
    } else {
        $mwAfter = max(0.0, (float) $twAfter - $diamondGrams + $lossAdd);
    }

    $totalFmt = mp_mfg_fmt_wt($twAfter);
    $metalFmt = mp_mfg_fmt_wt($mwAfter);
    $lossFmt = ($lossGrams !== null && is_finite($lossGrams) && $lossGrams > 0.00001)
        ? mp_mfg_fmt_wt($lossGrams) : '—';

    return [
        'total_wt' => $totalFmt,
        'metal_wt' => $metalFmt,
        'loss_wt' => $lossFmt,
        'dust_wastage_wt' => '—',
        'balance_wt' => $totalFmt,
        'loss_grams' => ($lossGrams !== null && is_finite($lossGrams) && $lossGrams > 0.00001) ? (float) $lossGrams : null,
    ];
}

/**
 * Job card history: one row per dept transfer, drop auto-loss duplicate weight rows.
 *
 * @param array<int,array<string,mixed>> $rows
 * @return array<int,array<string,mixed>>
 */
function mp_mfg_normalize_job_card_history_rows(array $rows): array
{
    $byAct = [];
    $out = [];

    foreach ($rows as $r) {
        $kind = (string) ($r['row_kind'] ?? '');
        if ($kind === 'weight') {
            $we = (string) ($r['weight_event'] ?? '');
            if ($we === 'loss') {
                continue;
            }
            $tw = trim((string) ($r['total_wt'] ?? ''));
            if ($tw !== '' && $tw !== '—' && strpos($tw, '-') === 0) {
                continue;
            }
            $out[] = $r;
            continue;
        }
        if ($kind === 'diamond_issue') {
            if ((string) ($r['activity_side'] ?? '') === 'in') {
                continue;
            }
            $out[] = $r;
            continue;
        }
        if ($kind === 'activity' && (string) ($r['flow_source'] ?? '') === 'department_transfer') {
            $sid = (int) ($r['source_id'] ?? 0);
            if ($sid < 1) {
                $out[] = $r;
                continue;
            }
            if (!isset($byAct[$sid])) {
                $byAct[$sid] = ['in' => null, 'out' => null];
            }
            $side = (string) ($r['activity_side'] ?? '');
            if ($side === 'in') {
                $byAct[$sid]['in'] = $r;
            } elseif ($side === 'out') {
                $byAct[$sid]['out'] = $r;
            } else {
                $out[] = $r;
            }
            continue;
        }
        $out[] = $r;
    }

    foreach ($byAct as $pair) {
        $in = $pair['in'];
        $outRow = $pair['out'];
        $base = $outRow ?? $in;
        if (!$base) {
            continue;
        }
        $merged = $base;
        $srcName = $outRow ? trim((string) ($outRow['department_name'] ?? '')) : '';
        $destName = $in ? trim((string) ($in['department_name'] ?? '')) : '';
        $merged['activity_side'] = 'transfer';
        $merged['src_department_name'] = $srcName !== '' ? $srcName : '—';
        $merged['dest_department_name'] = $destName !== '' ? $destName : '—';
        $merged['department_name'] = $destName !== '' ? $destName : ($srcName !== '' ? $srcName : '—');
        $flow = trim((string) ($base['department_flow'] ?? ''));
        $flow = preg_replace('/\s*·\s*(In|Out)\s*$/', '', $flow);
        $merged['department_flow'] = $flow !== '' ? $flow : '—';
        $merged['comment'] = 'Transfer · ' . ($flow !== '' ? $flow : '—');
        $lossDisp = trim((string) ($merged['loss_wt'] ?? ''));
        if ($lossDisp !== '' && $lossDisp !== '—') {
            $merged['issue_wt'] = $lossDisp;
        }
        $merged['receive_wt'] = '—';
        $out[] = $merged;
    }

    return $out;
}

$rows = [];

/** Per jobwork_order_id: SUM(weight) from diamond stock issue rows (Jobwork Queue material grid). */
$mp_jwq_diamond_sum_by_order = [];
$diamond_issue_tbl = 'tbl_jobwork_queue_diamond_stock_issue';
$diamond_chk = @mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $diamond_issue_tbl) . "'");
if ($diamond_chk && mysqli_num_rows($diamond_chk) > 0) {
    mysqli_free_result($diamond_chk);
    $dSumSql = 'SELECT jobwork_order_id, COALESCE(SUM(`weight`),0) AS s FROM `' . $diamond_issue_tbl . '` WHERE jobwork_order_id IS NOT NULL AND jobwork_order_id > 0 GROUP BY jobwork_order_id';
    $dSumList = function_exists('getList') ? @getList($dSumSql) : null;
    if (is_array($dSumList)) {
        foreach ($dSumList as $dr) {
            $dj = (int) ($dr['jobwork_order_id'] ?? 0);
            if ($dj > 0) {
                $mp_jwq_diamond_sum_by_order[$dj] = (float) ($dr['s'] ?? 0);
            }
        }
    }
    /* Diamond material issues: outward row for source dept + inward row for destination dept. */
    $di_cols = [];
    $di_cols_q = @mysqli_query($conn, 'SHOW COLUMNS FROM `' . $diamond_issue_tbl . '`');
    if ($di_cols_q) {
        while ($dc = mysqli_fetch_assoc($di_cols_q)) {
            $di_cols[(string) ($dc['Field'] ?? '')] = true;
        }
        mysqli_free_result($di_cols_q);
    }
    $di_w_expr = !empty($di_cols['weight_out']) ? 'COALESCE(di.weight_out, di.weight, 0)' : 'COALESCE(di.weight, 0)';
    $di_q_expr = !empty($di_cols['qty_out']) ? 'COALESCE(di.qty_out, di.qty, 0)' : 'COALESCE(di.qty, 0)';
    $di_add_dept_expr = !empty($di_cols['added_by_dept_id']) ? 'di.added_by_dept_id' : 'NULL';
    $di_add_user_expr = !empty($di_cols['added_by_user_id']) ? 'di.added_by_user_id' : 'NULL';
    $di_sql = 'SELECT di.id AS issue_id, di.jobwork_order_id, di.barcode, di.product_name, di.diamond_category,
        ' . $di_w_expr . ' AS issue_weight,
        ' . $di_q_expr . ' AS issue_qty,
        di.from_dept_id, di.to_dept_id, di.from_user_id, di.to_user_id,
        ' . $di_add_dept_expr . ' AS added_by_dept_id, ' . $di_add_user_expr . ' AS added_by_user_id, di.created_at,
        j.jobwork_queue_no, j.jobwork_no, j.sale_order_id, j.sale_order_no, j.status AS jwo_status,
        fd.dept_name AS from_dept_name, fu.name AS from_user_name,
        td.dept_name AS to_dept_name, tu.name AS to_user_name,
        ad.dept_name AS added_dept_name, au.name AS added_user_name
        FROM `' . $diamond_issue_tbl . '` di
        INNER JOIN tbl_jobwork_orders j ON j.id = di.jobwork_order_id
        LEFT JOIN tbl_departments fd ON fd.id = di.from_dept_id
        LEFT JOIN tbl_customers fu ON fu.id = di.from_user_id
        LEFT JOIN tbl_departments td ON td.id = di.to_dept_id
        LEFT JOIN tbl_customers tu ON tu.id = di.to_user_id
        LEFT JOIN tbl_departments ad ON ad.id = ' . $di_add_dept_expr . '
        LEFT JOIN tbl_customers au ON au.id = ' . $di_add_user_expr . '
        ORDER BY di.created_at DESC, di.id DESC
        LIMIT 500';
    $di_list = function_exists('getList') ? @getList($di_sql) : null;
    if (!is_array($di_list)) {
        $di_list = [];
    }
    foreach ($di_list as $dr) {
        $dw = (float) ($dr['issue_weight'] ?? 0);
        if (!is_finite($dw) || $dw <= 0.00005) {
            continue;
        }
        $dJwoId = (int) ($dr['jobwork_order_id'] ?? 0);
        $dQn = trim((string) ($dr['jobwork_queue_no'] ?? ''));
        if ($dQn === '') {
            $dQn = 'JWQ-' . $dJwoId;
        }
        $dProduct = trim((string) ($dr['product_name'] ?? ''));
        if ($dProduct === '') {
            $dProduct = trim((string) ($dr['diamond_category'] ?? 'Diamond'));
        }
        $dTag = trim((string) ($dr['barcode'] ?? ''));
        if ($dTag === '') {
            $dTag = '—';
        }
        $dCa = $dr['created_at'] ?? null;
        $dQty = (float) ($dr['issue_qty'] ?? 0);
        $dWtDisp = mp_mfg_fmt_wt($dw);
        /* Source dept: saved from_dept on issue row; legacy rows may use added_by dept when no to-dept branch transfer. */
        $dFromDept = (int) ($dr['from_dept_id'] ?? 0);
        $dFromUser = (int) ($dr['from_user_id'] ?? 0);
        $dFromDeptName = trim((string) ($dr['from_dept_name'] ?? ''));
        $dFromUserName = trim((string) ($dr['from_user_name'] ?? ''));
        $dToDept = (int) ($dr['to_dept_id'] ?? 0);
        $dToUser = (int) ($dr['to_user_id'] ?? 0);
        if ($dFromDept < 1 && $dToDept < 1) {
            $dFromDept = (int) ($dr['added_by_dept_id'] ?? 0);
            $dFromUser = (int) ($dr['added_by_user_id'] ?? 0);
            $dFromDeptName = trim((string) ($dr['added_dept_name'] ?? ''));
            $dFromUserName = trim((string) ($dr['added_user_name'] ?? ''));
        }
        $dToDeptName = trim((string) ($dr['to_dept_name'] ?? ''));
        $dToUserName = trim((string) ($dr['to_user_name'] ?? ''));

        $dCommon = [
            '_sort' => mp_mfg_sort_ts($dCa),
            'row_kind' => 'diamond_issue',
            'source_id' => (int) ($dr['issue_id'] ?? 0),
            'adjustment_id' => 0,
            'jobwork_order_id' => $dJwoId,
            'jobwork_queue_no_attr' => $dQn,
            'jobwork_no' => trim((string) ($dr['jobwork_no'] ?? '')),
            'sale_order_id' => (int) ($dr['sale_order_id'] ?? 0),
            'sale_order_no' => trim((string) ($dr['sale_order_no'] ?? '')),
            'first_product' => $dProduct,
            'manufacturing_seconds' => 0,
            'queue_no' => $dQn,
            'product_name' => $dProduct,
            'active' => isset($dr['jwo_status']) && $dr['jwo_status'] !== '' ? (string) $dr['jwo_status'] : '—',
            'image_urls' => '—',
            'against_queue' => $dQn,
            'against_invoice' => trim((string) ($dr['sale_order_no'] ?? '')) !== '' ? trim((string) $dr['sale_order_no']) : '—',
            'metal' => 'Diamond',
            'description' => $dProduct,
            'dust_wastage_wt' => '—',
            'loss_wt' => '—',
            'profit_wt' => '—',
            'tag_no' => $dTag,
            'total_wt' => $dWtDisp,
            'metal_wt' => '—',
            'diamond_wt' => $dWtDisp,
            'purity_wt' => '—',
            'carat_name' => '—',
            'total_quantity' => $dQty > 0.00005 ? mp_mfg_fmt_wt($dQty) : '—',
            'date_time' => mp_mfg_dt($dCa),
            'branch_name' => '—',
            'design_no' => '—',
            'status' => isset($dr['jwo_status']) && $dr['jwo_status'] !== '' ? (string) $dr['jwo_status'] : '—',
            'balance_wt' => '—',
            'weight_event' => 'diamond_issue',
            'flow_source' => 'diamond_issue',
        ];
        $dFlow = mp_mfg_flow_stage($dFromDeptName, $dFromUserName) . ' ==> ' . mp_mfg_flow_stage($dToDeptName, $dToUserName);

        if ($dFromDept > 0) {
            $dCommentOut = 'Outward · Diamond · ' . $dTag . ' · to ' . ($dToDeptName !== '' ? $dToDeptName : ('Dept #' . $dToDept));
            if ($dToUserName !== '') {
                $dCommentOut .= ' · ' . $dToUserName;
            }
            $rows[] = array_merge($dCommon, [
                'department_id' => $dFromDept,
                'department_user_id' => $dFromUser,
                'comment' => $dCommentOut,
                'department_name' => $dToDeptName !== '' ? $dToDeptName : '—',
                'user_name' => $dToUserName !== '' ? $dToUserName : '—',
                'issue_wt' => $dWtDisp,
                'receive_wt' => '—',
                'activity_side' => 'out',
                'stock_flow_type' => 'outward',
                'department_flow' => $dFlow . ' · Out',
            ]);
        }
        if ($dToDept > 0) {
            $dCommentIn = 'Inward · Diamond · ' . $dTag;
            if ($dFromDept > 0) {
                $dCommentIn .= ' · from ' . ($dFromDeptName !== '' ? $dFromDeptName : ('Dept #' . $dFromDept));
                if ($dFromUserName !== '') {
                    $dCommentIn .= ' · ' . $dFromUserName;
                }
            } else {
                $dCommentIn .= ' · from branch stock';
            }
            $rows[] = array_merge($dCommon, [
                'department_id' => $dToDept,
                'department_user_id' => $dToUser,
                'comment' => $dCommentIn,
                'department_name' => $dFromDeptName !== '' ? $dFromDeptName : '—',
                'user_name' => $dFromUserName !== '' ? $dFromUserName : '—',
                'issue_wt' => '—',
                'receive_wt' => $dWtDisp,
                'activity_side' => 'in',
                'stock_flow_type' => 'inward',
                'department_flow' => $dFlow . ' · In',
            ]);
        }
    }
} elseif ($diamond_chk) {
    mysqli_free_result($diamond_chk);
}

$wchk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_weight_adjustments'");
$has_weight = ($wchk && mysqli_num_rows($wchk) > 0);
if ($wchk) {
    mysqli_free_result($wchk);
}

$achk_w = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_queue_activity'");
$has_activity_for_weight = ($achk_w && mysqli_num_rows($achk_w) > 0);
if ($achk_w) {
    mysqli_free_result($achk_w);
}

if ($has_weight) {
    $wsrc_mp = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_weight_adjustments LIKE 'source_department_id'");
    if (!$wsrc_mp || mysqli_num_rows($wsrc_mp) === 0) {
        @mysqli_query($conn, 'ALTER TABLE tbl_jobwork_weight_adjustments ADD COLUMN source_department_id int(11) DEFAULT NULL AFTER created_by_user_id, ADD COLUMN source_user_id int(11) DEFAULT NULL AFTER source_department_id');
    }
    if ($wsrc_mp) {
        mysqli_free_result($wsrc_mp);
    }

    $queue_at_event_sql = $has_activity_for_weight
        ? '(SELECT a.jobwork_queue_no FROM tbl_jobwork_queue_activity a WHERE a.jobwork_order_id = w.jobwork_order_id AND a.created_at <= w.created_at ORDER BY a.created_at DESC, a.id DESC LIMIT 1) AS queue_at_event'
        : 'NULL AS queue_at_event';

    $sql = 'SELECT w.id AS adjustment_id, w.jobwork_order_id, w.adjustment_type, w.weight_grams, w.remark, w.created_at,
        j.jobwork_queue_no, j.jobwork_no, j.sale_order_id, j.sale_order_no, j.customer_name,
        ' . $queue_at_event_sql . ',
        COALESCE(w.source_department_id, j.department_id) AS stock_dept_id,
        (CASE WHEN w.source_user_id IS NOT NULL AND w.source_user_id > 0 THEN w.source_user_id ELSE j.department_user_id END) AS stock_user_id,
        j.status AS jwo_status,
        d.dept_name,
        cu.name AS worker_name
        ' . $mfg_sec . '
        , ' . $ji_tag . ' AS item_tag,
        ' . $ji_design . ' AS item_design,
        ' . $ji_product . ' AS item_product,
        ' . $ji_qty . ' AS item_qty,
        ' . $ji_carat . ' AS item_carat,
        ' . $ji_less . ' AS item_less_wt,
        ' . $ji_purity_w . ' AS item_purity_wt,
        ' . $ji_gross . ' AS item_gross_wt,
        ' . $ji_net . ' AS item_net_wt,
        ' . $ji_diamond . ' AS item_diamond_wt,
        ' . $ji_net_amt . ' AS item_net_amount,
        ' . $ji_line_loss . ' AS item_line_loss_wt,
        ' . $ji_final . ' AS item_final_wt
        FROM tbl_jobwork_weight_adjustments w
        INNER JOIN tbl_jobwork_orders j ON j.id = w.jobwork_order_id
        LEFT JOIN tbl_departments d ON d.id = COALESCE(w.source_department_id, j.department_id)
        LEFT JOIN tbl_customers cu ON cu.id = (CASE WHEN w.source_user_id IS NOT NULL AND w.source_user_id > 0 THEN w.source_user_id ELSE j.department_user_id END)
        ORDER BY w.created_at DESC, w.id DESC
        LIMIT 500';

    $list = function_exists('getList') ? @getList($sql) : null;
    if (!is_array($list)) {
        $list = [];
    }

    foreach ($list as $r) {
        $adj = isset($r['adjustment_type']) && $r['adjustment_type'] === 'add' ? 'add' : 'reduce';
        $w = isset($r['weight_grams']) ? (float)$r['weight_grams'] : 0;
        $remark = isset($r['remark']) ? trim((string)$r['remark']) : '';
        $qn = trim((string)($r['queue_at_event'] ?? ''));
        if ($qn === '') {
            $qn = trim((string)($r['jobwork_queue_no'] ?? ''));
        }
        if ($qn === '') {
            $qn = 'JWQ-' . (int)($r['jobwork_order_id'] ?? 0);
        }
        $tag = trim((string)($r['item_tag'] ?? ''));
        if ($tag === '') {
            $tag = '—';
        }
        $design = trim((string)($r['item_design'] ?? ''));
        if ($design === '') {
            $design = '—';
        }
        $product = trim((string)($r['item_product'] ?? ''));
        $act_label = $adj === 'add' ? 'Add weight' : 'Reduce weight';
        $is_loss_reduce = ($adj === 'reduce' && stripos($remark, 'auto loss') !== false);
        $comment = $remark !== '' ? ($act_label . ': ' . $remark) : $act_label;
        if ($is_loss_reduce) {
            $comment = 'Outward · loss · ' . ($remark !== '' ? $remark : $act_label);
        }

        /* Positive magnitude — stock_flow_type (inward/outward) controls balance direction. */
        $wt_display = mp_mfg_fmt_wt($w);

        $ca = $r['created_at'] ?? null;
        $gross = $r['item_gross_wt'] ?? null;
        $netw = $r['item_net_wt'] ?? null;
        $lessw = $r['item_less_wt'] ?? null;
        $finalAdj = $r['item_final_wt'] ?? null;
        $lineLossSavedW = $r['item_line_loss_wt'] ?? null;

        $issueWt = '—';
        $recvWt = '—';
        if ($adj === 'reduce') {
            $issueWt = mp_mfg_fmt_wt($w);
        } else {
            $recvWt = mp_mfg_fmt_wt($w);
        }

        $jwoId = (int)($r['jobwork_order_id'] ?? 0);
        $deptId = (int)($r['stock_dept_id'] ?? 0);
        $userId = (int)($r['stock_user_id'] ?? 0);
        $mfgSec = isset($r['manufacturing_time_seconds']) ? (int)$r['manufacturing_time_seconds'] : 0;

        $lossGramsW = mp_mfg_resolve_loss_grams($lineLossSavedW, ($is_loss_reduce && $adj === 'reduce' && $w > 0.00001) ? $w : null, $netw, $finalAdj);
        if (($lossGramsW === null || $lossGramsW <= 0.00001) && $adj === 'reduce' && $w > 0.00001) {
            $lossGramsW = (float) $w;
        }
        $lossWtDisp = ($lossGramsW !== null && $lossGramsW > 0.00001) ? mp_mfg_fmt_wt($lossGramsW) : '—';

        $rows[] = [
            '_sort' => mp_mfg_sort_ts($ca),
            'row_kind' => 'weight',
            'source_id' => (int)($r['adjustment_id'] ?? 0),
            'adjustment_id' => (int)($r['adjustment_id'] ?? 0),
            'jobwork_order_id' => $jwoId,
            'jobwork_queue_no_attr' => $qn,
            'department_id' => $deptId,
            'department_user_id' => $userId,
            'jobwork_no' => trim((string)($r['jobwork_no'] ?? '')),
            'sale_order_id' => (int)($r['sale_order_id'] ?? 0),
            'sale_order_no' => trim((string)($r['sale_order_no'] ?? '')),
            'first_product' => $product,
            'manufacturing_seconds' => $mfgSec,
            'queue_no' => $qn,
            'comment' => $comment,
            'product_name' => $product !== '' ? $product : '—',
            'active' => isset($r['jwo_status']) && $r['jwo_status'] !== '' ? (string)$r['jwo_status'] : '—',
            'image_urls' => '—',
            'against_queue' => '—',
            'against_invoice' => trim((string)($r['sale_order_no'] ?? '')) !== '' ? trim((string)$r['sale_order_no']) : '—',
            'metal' => mp_mfg_infer_metal($product),
            'description' => $product !== '' ? $product : '—',
            'dust_wastage_wt' => ($lessw !== null && $lessw !== '') ? mp_mfg_fmt_wt($lessw) : '—',
            'loss_wt' => $lossWtDisp,
            'profit_wt' => isset($r['item_net_amount']) ? mp_mfg_fmt_money($r['item_net_amount']) : '—',
            'tag_no' => $tag,
            'total_wt' => $wt_display,
            'metal_wt' => mp_mfg_fmt_wt($w),
            // Weight adjust rows are metal deltas only — do not put full line diamond/qty into balance.
            'diamond_wt' => '—',
            'purity_wt' => isset($r['item_purity_wt']) ? mp_mfg_fmt_wt($r['item_purity_wt']) : '—',
            'carat_name' => isset($r['item_carat']) && trim((string)$r['item_carat']) !== '' ? trim((string)$r['item_carat']) : '—',
            'total_quantity' => '—',
            'date_time' => mp_mfg_dt($ca),
            'branch_name' => '—',
            'design_no' => $design,
            'department_name' => trim((string)($r['dept_name'] ?? '')) !== '' ? trim((string)$r['dept_name']) : '—',
            'user_name' => trim((string)($r['worker_name'] ?? '')) !== '' ? trim((string)$r['worker_name']) : '—',
            'status' => isset($r['jwo_status']) && $r['jwo_status'] !== '' ? (string)$r['jwo_status'] : '—',
            'issue_wt' => $issueWt,
            'receive_wt' => $recvWt,
            'balance_wt' => '—',
            'stock_flow_type' => $adj === 'add' ? 'inward' : 'outward',
            'weight_event' => $adj === 'add' ? 'add' : ($is_loss_reduce ? 'loss' : 'issue'),
            'department_flow' => mp_mfg_flow_stage($r['dept_name'] ?? '', $r['worker_name'] ?? ''),
        ];
    }
}

$achk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_queue_activity'");
$has_activity = ($achk && mysqli_num_rows($achk) > 0);
if ($achk) {
    mysqli_free_result($achk);
}

$transfer_loss_sel = ', NULL AS transfer_loss_wt';
if ($has_weight) {
    $transfer_loss_sel = ", (SELECT w.weight_grams FROM tbl_jobwork_weight_adjustments w
        WHERE w.jobwork_order_id = j.id AND w.adjustment_type = 'reduce'
        AND (w.remark LIKE '%auto loss%' OR w.remark LIKE '%Auto loss%')
        AND w.created_at <= a.created_at
        AND w.created_at >= DATE_SUB(a.created_at, INTERVAL 60 SECOND)
        ORDER BY w.id DESC LIMIT 1) AS transfer_loss_wt";
}

if ($has_activity) {
    $acf_mp = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_queue_activity LIKE 'from_dept_id'");
    if (!$acf_mp || mysqli_num_rows($acf_mp) === 0) {
        @mysqli_query($conn, 'ALTER TABLE tbl_jobwork_queue_activity ADD COLUMN from_dept_id int(11) DEFAULT NULL AFTER jobwork_queue_no, ADD COLUMN from_user_id int(11) DEFAULT NULL AFTER from_dept_id');
    }
    if ($acf_mp) {
        mysqli_free_result($acf_mp);
    }

    $aca_mp = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_queue_activity LIKE 'activity_action'");
    if (!$aca_mp || mysqli_num_rows($aca_mp) === 0) {
        @mysqli_query($conn, 'ALTER TABLE tbl_jobwork_queue_activity ADD COLUMN activity_action varchar(32) DEFAULT NULL AFTER to_user_id');
    }
    if ($aca_mp) {
        mysqli_free_result($aca_mp);
    }

    $act_snap_sel = '';
    $act_tw_mp = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_queue_activity LIKE 'total_wt_after'");
    if ($act_tw_mp && mysqli_num_rows($act_tw_mp) > 0) {
        $act_snap_sel = ', a.total_wt_after, a.total_qty_after';
    }
    if ($act_tw_mp) {
        mysqli_free_result($act_tw_mp);
    }
    $act_mw_mp = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_queue_activity LIKE 'metal_wt_after'");
    if ($act_mw_mp && mysqli_num_rows($act_mw_mp) > 0) {
        $act_snap_sel .= ', a.metal_wt_after, a.loss_wt_after';
    }
    if ($act_mw_mp) {
        mysqli_free_result($act_mw_mp);
    }

    $asql = 'SELECT a.id AS activity_id, a.jobwork_order_id, a.created_at, a.jobwork_queue_no AS act_queue_no,
        a.activity_action, a.from_dept_id, a.from_user_id, a.to_dept_id, a.to_user_id
        ' . $act_snap_sel . ',
        j.jobwork_queue_no, j.jobwork_no, j.sale_order_id, j.sale_order_no, j.status AS jwo_status,
        j.department_id, j.department_user_id
        ' . $mfg_sec . '
        , td.dept_name AS dest_dept_name,
        tu.name AS dest_user_name,
        fd.dept_name AS src_dept_name,
        fu.name AS src_user_name,
        ' . $ji_tag . ' AS item_tag,
        ' . $ji_design . ' AS item_design,
        ' . $ji_product . ' AS item_product,
        ' . $ji_qty . ' AS item_qty,
        ' . $ji_carat . ' AS item_carat,
        ' . $ji_final . ' AS item_final_wt,
        ' . $ji_net . ' AS item_net_wt,
        ' . $ji_less . ' AS item_less_wt,
        ' . $ji_purity_w . ' AS item_purity_wt,
        ' . $ji_gross . ' AS item_gross_wt,
        ' . $ji_diamond . ' AS item_diamond_wt,
        ' . $ji_net_amt . ' AS item_net_amount,
        ' . $ji_line_loss . ' AS item_line_loss_wt
        ' . $transfer_loss_sel . '
        FROM tbl_jobwork_queue_activity a
        INNER JOIN tbl_jobwork_orders j ON j.id = a.jobwork_order_id
        LEFT JOIN tbl_departments td ON td.id = a.to_dept_id
        LEFT JOIN tbl_customers tu ON tu.id = a.to_user_id
        LEFT JOIN tbl_departments fd ON fd.id = a.from_dept_id
        LEFT JOIN tbl_customers fu ON fu.id = a.from_user_id
        ORDER BY a.created_at DESC, a.id DESC
        LIMIT 500';

    $alist = function_exists('getList') ? @getList($asql) : null;
    if (!is_array($alist)) {
        $alist = [];
    }

    foreach ($alist as $r) {
        // Per-transfer queue no is on the activity row; header j.jobwork_queue_no is the latest only.
        $qn = trim((string)($r['act_queue_no'] ?? ''));
        if ($qn === '') {
            $qn = trim((string)($r['jobwork_queue_no'] ?? ''));
        }
        if ($qn === '') {
            $qn = 'JWQ-' . (int)($r['jobwork_order_id'] ?? 0);
        }
        $tag = trim((string)($r['item_tag'] ?? ''));
        if ($tag === '') {
            $tag = '—';
        }
        $design = trim((string)($r['item_design'] ?? ''));
        if ($design === '') {
            $design = '—';
        }
        $product = trim((string)($r['item_product'] ?? ''));
        $deptn = trim((string)($r['dest_dept_name'] ?? ''));
        $usrn = trim((string)($r['dest_user_name'] ?? ''));
        $srcdeptn = trim((string)($r['src_dept_name'] ?? ''));
        $srcusrn = trim((string)($r['src_user_name'] ?? ''));
        $toDeptId = isset($r['to_dept_id']) && $r['to_dept_id'] !== null && $r['to_dept_id'] !== '' ? (int)$r['to_dept_id'] : 0;
        $toUserId = isset($r['to_user_id']) && $r['to_user_id'] !== null && $r['to_user_id'] !== '' ? (int)$r['to_user_id'] : 0;
        $fromDeptId = isset($r['from_dept_id']) && $r['from_dept_id'] !== null && $r['from_dept_id'] !== '' ? (int)$r['from_dept_id'] : 0;
        $fromUserId = isset($r['from_user_id']) && $r['from_user_id'] !== null && $r['from_user_id'] !== '' ? (int)$r['from_user_id'] : 0;
        $actAction = isset($r['activity_action']) ? trim((string)$r['activity_action']) : '';

        $fw = isset($r['item_final_wt']) ? (float)$r['item_final_wt'] : null;
        $nw = isset($r['item_net_wt']) ? (float)$r['item_net_wt'] : null;
        $gross = $r['item_gross_wt'] ?? null;
        $lessw = $r['item_less_wt'] ?? null;
        $ca = $r['created_at'] ?? null;

        $actWeights = mp_mfg_activity_history_weights($r, $ji_cols, $mp_jwq_diamond_sum_by_order);
        $act_total_wt = $actWeights['total_wt'];
        $act_balance_wt = $actWeights['balance_wt'];
        $activity_loss_wt = $actWeights['loss_wt'];
        $activity_metal_wt = $actWeights['metal_wt'];
        $activity_other_wt = $actWeights['dust_wastage_wt'];

        $jwoId = (int)($r['jobwork_order_id'] ?? 0);
        $jwoDeptId = (int)($r['department_id'] ?? 0);
        $jwoUserId = (int)($r['department_user_id'] ?? 0);
        $mfgSec = isset($r['manufacturing_time_seconds']) ? (int)$r['manufacturing_time_seconds'] : 0;
        $actId = (int)($r['activity_id'] ?? 0);

        $common = [
            '_sort' => mp_mfg_sort_ts($ca),
            'row_kind' => 'activity',
            'source_id' => $actId,
            'adjustment_id' => 0,
            'jobwork_order_id' => $jwoId,
            'jobwork_queue_no_attr' => $qn,
            'jobwork_no' => trim((string)($r['jobwork_no'] ?? '')),
            'sale_order_id' => (int)($r['sale_order_id'] ?? 0),
            'sale_order_no' => trim((string)($r['sale_order_no'] ?? '')),
            'first_product' => $product,
            'manufacturing_seconds' => $mfgSec,
            'queue_no' => $qn,
            'product_name' => $product !== '' ? $product : '—',
            'active' => isset($r['jwo_status']) && $r['jwo_status'] !== '' ? (string)$r['jwo_status'] : '—',
            'image_urls' => '—',
            'against_queue' => '—',
            'against_invoice' => trim((string)($r['sale_order_no'] ?? '')) !== '' ? trim((string)$r['sale_order_no']) : '—',
            'metal' => mp_mfg_infer_metal($product),
            'description' => $product !== '' ? $product : '—',
            'dust_wastage_wt' => $activity_other_wt,
            'loss_wt' => $activity_loss_wt,
            'profit_wt' => isset($r['item_net_amount']) ? mp_mfg_fmt_money($r['item_net_amount']) : '—',
            'tag_no' => $tag,
            'total_wt' => $act_total_wt,
            'metal_wt' => $activity_metal_wt,
            'diamond_wt' => mp_mfg_diamond_wt(mp_mfg_merged_stored_diamond_for_history($r, $mp_jwq_diamond_sum_by_order), $gross, $nw, $lessw),
            'purity_wt' => isset($r['item_purity_wt']) ? mp_mfg_fmt_wt($r['item_purity_wt']) : '—',
            'carat_name' => isset($r['item_carat']) && trim((string)$r['item_carat']) !== '' ? trim((string)$r['item_carat']) : '—',
            'total_quantity' => isset($r['item_qty']) ? mp_mfg_fmt_wt($r['item_qty']) : '—',
            'date_time' => mp_mfg_dt($ca),
            'branch_name' => '—',
            'design_no' => $design,
            'status' => isset($r['jwo_status']) && $r['jwo_status'] !== '' ? (string)$r['jwo_status'] : '—',
            'issue_wt' => '—',
            'receive_wt' => '—',
            'balance_wt' => $act_balance_wt,
        ];

        $filterToDept = $toDeptId > 0 ? $toDeptId : $jwoDeptId;
        $filterToUser = $toUserId > 0 ? $toUserId : $jwoUserId;
        $isJobworkCreate = ($actAction === 'jobwork_create')
            || ($actAction === '' && $fromDeptId < 1);
        $isRealTransfer = ($fromDeptId > 0 && ($fromDeptId !== $toDeptId || $fromUserId !== $toUserId));

        // Manufacturing floor inward/outward: department transfers only (not initial JWO jobwork_create).
        if ($isJobworkCreate) {
            continue;
        }

        $commentIn = 'Inward · Received';
        if ($fromDeptId > 0) {
            $commentIn .= ' · transfer_from: ' . ($srcdeptn !== '' ? $srcdeptn : ('Dept #' . $fromDeptId));
            if ($srcusrn !== '') {
                $commentIn .= ' · ' . $srcusrn;
            }
        }
        $commentIn .= ' · To ' . ($deptn !== '' ? $deptn : '—');
        if ($usrn !== '') {
            $commentIn .= ' · ' . $usrn;
        }

        $fromFlow = mp_mfg_flow_stage($srcdeptn, $srcusrn);
        if ($fromFlow === '—') {
            $jf = strtoupper(trim((string)($r['jobwork_no'] ?? '')));
            $fromFlow = $jf !== '' ? $jf : ('JWQ-' . $jwoId);
        }
        $deptFlowXfer = $fromFlow . ' ==> ' . mp_mfg_flow_stage($deptn, $usrn);

        $rows[] = array_merge($common, [
                'department_id' => $filterToDept,
                'department_user_id' => $filterToUser,
                'comment' => $commentIn,
                'department_name' => $srcdeptn !== '' ? $srcdeptn : '—',
                'user_name' => $srcusrn !== '' ? $srcusrn : '—',
                'src_department_name' => $srcdeptn !== '' ? $srcdeptn : '—',
                'src_user_name' => $srcusrn !== '' ? $srcusrn : '—',
                'dest_department_name' => $deptn !== '' ? $deptn : '—',
                'dest_user_name' => $usrn !== '' ? $usrn : '—',
                'from_dept_id' => $fromDeptId,
                'from_user_id' => $fromUserId,
                'to_dept_id' => $toDeptId,
                'to_user_id' => $toUserId,
                'activity_side' => 'in',
                'stock_flow_type' => 'inward',
                'flow_source' => 'department_transfer',
                'department_flow' => $deptFlowXfer . ' · In',
            ]);

        if ($isRealTransfer) {
            $commentOut = 'Outward · Sent · transfer_to: ' . ($deptn !== '' ? $deptn : ('Dept #' . $toDeptId));
            if ($usrn !== '') {
                $commentOut .= ' · ' . $usrn;
            }
            $rows[] = array_merge($common, [
                'department_id' => $fromDeptId,
                'department_user_id' => $fromUserId > 0 ? $fromUserId : 0,
                'comment' => $commentOut,
                'department_name' => $deptn !== '' ? $deptn : '—',
                'user_name' => $usrn !== '' ? $usrn : '—',
                'src_department_name' => $srcdeptn !== '' ? $srcdeptn : '—',
                'src_user_name' => $srcusrn !== '' ? $srcusrn : '—',
                'dest_department_name' => $deptn !== '' ? $deptn : '—',
                'dest_user_name' => $usrn !== '' ? $usrn : '—',
                'from_dept_id' => $fromDeptId,
                'from_user_id' => $fromUserId,
                'to_dept_id' => $toDeptId,
                'to_user_id' => $toUserId,
                'activity_side' => 'out',
                'stock_flow_type' => 'outward',
                'flow_source' => 'department_transfer',
                'department_flow' => $deptFlowXfer . ' · Out',
            ]);
        }
    }
}

$mi_m = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_material_issues'");
$mi_i = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_material_issue_items'");
$has_mi = ($mi_m && mysqli_num_rows($mi_m) > 0 && $mi_i && mysqli_num_rows($mi_i) > 0);
if ($mi_m) {
    mysqli_free_result($mi_m);
}
if ($mi_i) {
    mysqli_free_result($mi_i);
}
if ($has_mi) {
    $mi_req_col = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_material_issue_items LIKE 'requested_wt'");
    $mi_has_req = ($mi_req_col && mysqli_num_rows($mi_req_col) > 0);
    if ($mi_req_col) {
        mysqli_free_result($mi_req_col);
    }
    if (!$mi_has_req) {
        $has_mi = false;
    }
}
if ($has_mi) {
    $mi_sql = 'SELECT mi.id AS material_issue_id, mi.material_issue_no, mi.sale_order_id, mi.sale_order_no, mi.customer_name,
        mi.department_id, mi.department_user_id, mi.updated_at, mi.created_at,
        mii.id AS mi_item_id, mii.product_name, mii.design_no, mii.barcode, mii.requested_wt, mii.quantity, mii.carat,
        mii.gross_weight, mii.net_weight, mii.less_weight, mii.purity_weight, mii.final_weight,
        d.dept_name, cu.name AS worker_name
        FROM tbl_material_issues mi
        INNER JOIN tbl_material_issue_items mii ON mii.material_issue_id = mi.id
        LEFT JOIN tbl_departments d ON d.id = mi.department_id
        LEFT JOIN tbl_customers cu ON cu.id = mi.department_user_id
        WHERE mi.department_id IS NOT NULL AND mi.department_id > 0
        AND (
            COALESCE(mii.requested_wt, 0) > 0.00005
            OR COALESCE(mii.final_weight, 0) > 0.00005
            OR COALESCE(mii.net_weight, 0) > 0.00005
            OR COALESCE(mii.gross_weight, 0) > 0.00005
        )
        ORDER BY COALESCE(mi.updated_at, mi.created_at) DESC, mii.id DESC
        LIMIT 300';
    $mil = function_exists('getList') ? @getList($mi_sql) : null;
    if (!is_array($mil)) {
        $mil = [];
    }
    $resolve_mi_issue_wt = static function (array $row): float {
        $fw = (float) ($row['final_weight'] ?? 0);
        $nw = (float) ($row['net_weight'] ?? 0);
        $gw = (float) ($row['gross_weight'] ?? 0);
        $metal = $fw > 0.00005 ? $fw : ($nw > 0.00005 ? $nw : $gw);
        $req = (float) ($row['requested_wt'] ?? 0);
        $has_m = $metal > 0.00005;
        $has_r = $req > 0.00005;
        if ($has_m && $has_r) {
            return $req;
        }
        if ($has_r) {
            return $req;
        }
        if ($has_m) {
            return $metal;
        }

        return 0.0;
    };
    foreach ($mil as $r) {
        $rw = $resolve_mi_issue_wt($r);
        if (!is_finite($rw) || $rw <= 0) {
            continue;
        }
        $deptId = (int)($r['department_id'] ?? 0);
        $userId = (int)($r['department_user_id'] ?? 0);
        $ca = $r['updated_at'] ?? ($r['created_at'] ?? null);
        $product = trim((string)($r['product_name'] ?? ''));
        $design = trim((string)($r['design_no'] ?? ''));
        if ($design === '') {
            $design = '—';
        }
        $tag = trim((string)($r['barcode'] ?? ''));
        if ($tag === '') {
            $tag = '—';
        }
        $miNo = trim((string)($r['material_issue_no'] ?? ''));
        $wt_display = mp_mfg_fmt_wt($rw);
        $gross = $r['gross_weight'] ?? null;
        $netw = $r['net_weight'] ?? null;
        $lessw = $r['less_weight'] ?? null;
        $itemId = (int)($r['mi_item_id'] ?? 0);
        $miId = (int)($r['material_issue_id'] ?? 0);
        $comment = 'Inward · Material Issue · ' . ($miNo !== '' ? $miNo : ('MI #' . $miId));
        if ($product !== '') {
            $comment .= ' · ' . $product;
        }

        $rows[] = [
            '_sort' => mp_mfg_sort_ts($ca),
            'row_kind' => 'material_issue',
            'source_id' => $itemId > 0 ? $itemId : $miId,
            'adjustment_id' => 0,
            'jobwork_order_id' => 0,
            'material_issue_id' => $miId,
            'jobwork_queue_no_attr' => '',
            'department_id' => $deptId,
            'department_user_id' => $userId,
            'jobwork_no' => $miNo !== '' ? $miNo : '—',
            'sale_order_id' => (int)($r['sale_order_id'] ?? 0),
            'sale_order_no' => trim((string)($r['sale_order_no'] ?? '')),
            'first_product' => $product,
            'manufacturing_seconds' => 0,
            'queue_no' => $miNo !== '' ? $miNo : ('MI-' . $miId),
            'comment' => $comment,
            'product_name' => $product !== '' ? $product : '—',
            'active' => '—',
            'image_urls' => '—',
            'against_queue' => '—',
            'against_invoice' => trim((string)($r['sale_order_no'] ?? '')) !== '' ? trim((string)($r['sale_order_no'])) : '—',
            'metal' => mp_mfg_infer_metal($product),
            'description' => $product !== '' ? $product : '—',
            'dust_wastage_wt' => ($lessw !== null && $lessw !== '') ? mp_mfg_fmt_wt($lessw) : '—',
            'loss_wt' => '—',
            'profit_wt' => '—',
            'tag_no' => $tag,
            'total_wt' => $wt_display,
            'metal_wt' => ($netw !== null && $netw !== '') ? mp_mfg_fmt_wt($netw) : '—',
            'diamond_wt' => mp_mfg_diamond_wt(mp_mfg_merged_stored_diamond_for_history($r, $mp_jwq_diamond_sum_by_order), $gross, $netw, $lessw),
            'purity_wt' => isset($r['purity_weight']) ? mp_mfg_fmt_wt($r['purity_weight']) : '—',
            'carat_name' => isset($r['carat']) && trim((string)$r['carat']) !== '' ? trim((string)$r['carat']) : '—',
            'total_quantity' => isset($r['quantity']) ? mp_mfg_fmt_wt($r['quantity']) : '—',
            'date_time' => mp_mfg_dt($ca),
            'branch_name' => '—',
            'design_no' => $design,
            'department_name' => trim((string)($r['dept_name'] ?? '')) !== '' ? trim((string)$r['dept_name']) : '—',
            'user_name' => trim((string)($r['worker_name'] ?? '')) !== '' ? trim((string)$r['worker_name']) : '—',
            'status' => '—',
            'issue_wt' => '—',
            'receive_wt' => $wt_display,
            'balance_wt' => '—',
                       'stock_flow_type' => 'inward',
            'weight_event' => 'material_issue',
            'flow_source' => 'material_issue',
            'department_flow' => strtoupper($miNo !== '' ? $miNo : ('MI-' . $miId)) . ' ==> ' . mp_mfg_flow_stage($r['dept_name'] ?? '', $r['worker_name'] ?? ''),
        ];
    }
}

if ($filter_jobwork_order_id > 0) {
    $rows = array_values(array_filter($rows, function ($r) use ($filter_jobwork_order_id) {
        return (int) ($r['jobwork_order_id'] ?? 0) === $filter_jobwork_order_id;
    }));
    $rows = mp_mfg_normalize_job_card_history_rows($rows);
}

usort($rows, function ($a, $b) {
    return ($b['_sort'] ?? 0) <=> ($a['_sort'] ?? 0);
});
$rows = array_slice($rows, 0, 500);

/** Per jobwork order: weight & qty currently assigned to a department (source of truth for floor stock). */
$jobwork_location_totals = [];
if ($has_items) {
    $jloc_has_du = false;
    $jloc_du_chk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_orders LIKE 'department_user_id'");
    if ($jloc_du_chk && mysqli_num_rows($jloc_du_chk) > 0) {
        $jloc_has_du = true;
    }
    if ($jloc_du_chk) {
        mysqli_free_result($jloc_du_chk);
    }
    $jloc_user_expr = $jloc_has_du ? 'COALESCE(j.department_user_id, 0)' : '0';
    $jloc_group_user = $jloc_has_du ? ', j.department_user_id' : '';
    $jloc_dwt = '0';
    if (!empty($ji_cols['diamond_weight']) && !empty($ji_cols['diamond_wt'])) {
        $jloc_dwt = 'COALESCE(ji.diamond_weight, ji.diamond_wt, 0)';
    } elseif (!empty($ji_cols['diamond_weight'])) {
        $jloc_dwt = 'COALESCE(ji.diamond_weight, 0)';
    } elseif (!empty($ji_cols['diamond_wt'])) {
        $jloc_dwt = 'COALESCE(ji.diamond_wt, 0)';
    }
    $jloc_loss = '0';
    if (!empty($ji_cols['gold_loss_1']) && !empty($ji_cols['loss_wt'])) {
        $jloc_loss = 'COALESCE(NULLIF(ji.gold_loss_1, 0), ji.loss_wt, 0)';
    } elseif (!empty($ji_cols['gold_loss_1'])) {
        $jloc_loss = 'COALESCE(ji.gold_loss_1, 0)';
    } elseif (!empty($ji_cols['loss_wt'])) {
        $jloc_loss = 'COALESCE(ji.loss_wt, 0)';
    }
    $jloc_metal = '(CASE WHEN COALESCE(ji.net_weight,0) > 0.0000001 THEN ji.net_weight ELSE COALESCE(ji.gross_weight, 0) END)';
    $jloc_fb = 'GREATEST(0, (' . $jloc_metal . ') - (' . $jloc_loss . ') + (' . $jloc_dwt . '))';
    if (!empty($ji_cols['final_weight'])) {
        $jloc_line_wt = '(CASE WHEN COALESCE(ji.final_weight,0) > 0.0000001 THEN ji.final_weight ELSE (' . $jloc_fb . ') END)';
    } else {
        $jloc_line_wt = $jloc_fb;
    }
    $jloc_sql = 'SELECT j.id AS jobwork_order_id, j.department_id,
        ' . $jloc_user_expr . ' AS department_user_id,
        SUM(' . $jloc_line_wt . ') AS total_wt,
        SUM(COALESCE(ji.quantity, 0)) AS total_qty
        FROM tbl_jobwork_orders j
        INNER JOIN tbl_jobwork_order_items ji ON ji.jobwork_order_id = j.id
        WHERE j.department_id IS NOT NULL AND j.department_id > 0
        GROUP BY j.id, j.department_id' . $jloc_group_user;
    $jl = function_exists('getList') ? @getList($jloc_sql) : null;
    if (is_array($jl)) {
        foreach ($jl as $row) {
            $jobwork_location_totals[] = [
                'jobwork_order_id' => (int) ($row['jobwork_order_id'] ?? 0),
                'department_id' => (int) ($row['department_id'] ?? 0),
                'department_user_id' => (int) ($row['department_user_id'] ?? 0),
                'total_wt' => round((float) ($row['total_wt'] ?? 0), 4),
                'total_qty' => round((float) ($row['total_qty'] ?? 0), 4),
            ];
        }
    }
}

if ($filter_jobwork_order_id > 0) {
    $jobwork_location_totals = array_values(array_filter($jobwork_location_totals, function ($t) use ($filter_jobwork_order_id) {
        return (int) ($t['jobwork_order_id'] ?? 0) === $filter_jobwork_order_id;
    }));
}

/** Same rule as manufacturing-process.php cards / Jobwork Queue modal: floor weight applies only after a real dept transfer. */
$jwo_floor_transfer_map = [];
$jwo_ids_for_floor = [];
foreach ($rows as $r) {
    $jid = (int) ($r['jobwork_order_id'] ?? 0);
    if ($jid > 0) {
        $jwo_ids_for_floor[$jid] = true;
    }
}
if ($has_activity && count($jwo_ids_for_floor) > 0) {
    $ids_in = implode(',', array_map('intval', array_keys($jwo_ids_for_floor)));
    $xfer_cond = '(LOWER(TRIM(IFNULL(a.activity_action,\'\'))) = \'department_transfer\''
        . ' OR ('
        . ' (a.activity_action IS NULL OR TRIM(IFNULL(a.activity_action,\'\')) = \'\')'
        . ' AND IFNULL(a.from_dept_id,0) > 0'
        . ' AND (IFNULL(a.from_dept_id,0) <> IFNULL(a.to_dept_id,0) OR IFNULL(a.from_user_id,0) <> IFNULL(a.to_user_id,0))'
        . '))';
    $fsql = 'SELECT a.jobwork_order_id, COUNT(*) AS c FROM tbl_jobwork_queue_activity a'
        . ' WHERE a.jobwork_order_id IN (' . $ids_in . ') AND ' . $xfer_cond
        . ' GROUP BY a.jobwork_order_id';
    $flist = function_exists('getList') ? @getList($fsql) : null;
    if (is_array($flist)) {
        foreach ($flist as $fr) {
            $fj = (int) ($fr['jobwork_order_id'] ?? 0);
            if ($fj > 0 && (int) ($fr['c'] ?? 0) > 0) {
                $jwo_floor_transfer_map[$fj] = 1;
            }
        }
    }
}
foreach ($rows as &$r) {
    $jid = (int) ($r['jobwork_order_id'] ?? 0);
    $r['jwo_has_floor_transfer'] = ($jid > 0 && !empty($jwo_floor_transfer_map[$jid])) ? 1 : 0;
}
unset($r);

$out = [];
foreach ($rows as $r) {
    unset($r['_sort']);
    $out[] = $r;
}

echo json_encode([
    'ok' => true,
    'rows' => $out,
    'jobwork_location_totals' => $jobwork_location_totals,
], JSON_UNESCAPED_UNICODE);
