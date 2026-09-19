<?php
/**
 * Shared department report column defs + table builder.
 */
require_once __DIR__ . '/auragold_department_schema.php';

if (!function_exists('auragold_department_report_columns')) {
    function auragold_department_report_columns(): array
    {
        return [
            ['key' => 'opening', 'label' => 'OPENING'],
            ['key' => 'other_in', 'label' => 'OTHER IN'],
            ['key' => 'other_out', 'label' => 'OTHER OUT'],
            ['key' => 'other_net', 'label' => 'OTHER NET'],
            ['key' => 'main_office_in', 'label' => 'MAIN OFFICE IN'],
            ['key' => 'main_office_out', 'label' => 'MAIN OFFICE OUT'],
            ['key' => 'main_office_net', 'label' => 'MAIN OFFICE NET'],
            ['key' => 'casting_in', 'label' => 'CASTING IN'],
            ['key' => 'loss', 'label' => 'LOSS'],
            ['key' => 'closing', 'label' => 'CLOSING'],
        ];
    }
}

if (!function_exists('auragold_department_report_load_inhouse_departments')) {
    function auragold_department_report_load_inhouse_departments(mysqli $conn): array
    {
        auragold_ensure_department_schema($conn);
        $departments = auragold_get_inhouse_departments($conn);
        if (!is_array($departments)) {
            $departments = [];
        }
        if ($departments === []) {
            $tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_departments'");
            if ($tbl && mysqli_num_rows($tbl) > 0) {
                mysqli_free_result($tbl);
                $departments = function_exists('getList')
                    ? getList('SELECT id, dept_name FROM tbl_departments WHERE status = 1 ORDER BY dept_name ASC')
                    : [];
                if (!is_array($departments)) {
                    $departments = [];
                }
            } elseif ($tbl) {
                mysqli_free_result($tbl);
            }
        }
        return $departments;
    }
}

if (!function_exists('auragold_department_report_load_outsource_departments')) {
    /** Manufacturing Outsource departments only (department.php process type). */
    function auragold_department_report_load_outsource_departments(mysqli $conn): array
    {
        auragold_ensure_department_schema($conn);
        $departments = auragold_get_outsource_departments($conn);
        return is_array($departments) ? $departments : [];
    }
}

if (!function_exists('auragold_department_report_build_tables')) {
/**
 * Build department report rows from jobwork queue activity, order line weights, and weight adjustments.
 *
 * @param mysqli $conn
 * @param array<int,array{id:int,dept_name:string}> $departments
 * @param array $report_columns
 * @param string $dateDmY dd-mm-yyyy
 * @return array<int,array{title:string,rows:array,sums:array}>
 */
function auragold_department_report_build_tables($conn, array $departments, array $report_columns, string $dateDmY): array
{
    $dt = DateTime::createFromFormat('d-m-Y', $dateDmY);
    $reportYmd = $dt ? $dt->format('Y-m-d') : date('Y-m-d');

    $keys = array_column($report_columns, 'key');
    $emptyRow = function () use ($keys) {
        $r = ['name' => '—'];
        foreach ($keys as $k) {
            $r[$k] = 0.0;
        }
        return $r;
    };

    $findDeptId = static function (array $depts, array $needles): ?int {
        foreach ($depts as $d) {
            $n = strtoupper(trim((string) ($d['dept_name'] ?? '')));
            foreach ($needles as $needle) {
                if ($n !== '' && strpos($n, $needle) !== false) {
                    return (int) $d['id'];
                }
            }
        }
        return null;
    };
    // Resolve Main/Casting from full department list (may be outside inhouse tabs).
    $allDeptsForLookup = $departments;
    if (function_exists('getList')) {
        $allLookup = getList('SELECT id, dept_name FROM tbl_departments WHERE status = 1 ORDER BY dept_name ASC');
        if (is_array($allLookup) && $allLookup !== []) {
            $allDeptsForLookup = $allLookup;
        }
    }
    $mainDeptId = $findDeptId($allDeptsForLookup, ['MAIN OFFICE', 'MAIN']);
    $castDeptId = $findDeptId($allDeptsForLookup, ['CASTING', 'CAST']);

    $hasActivity = false;
    $tAct = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_queue_activity'");
    if ($tAct && mysqli_num_rows($tAct) > 0) {
        $hasActivity = true;
    }
    if ($tAct) {
        mysqli_free_result($tAct);
    }

    $wtCol = 'final_weight';
    $tCol = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_order_items LIKE 'final_weight'");
    if (!$tCol || mysqli_num_rows($tCol) === 0) {
        $wtCol = 'gross_weight';
    }
    if ($tCol) {
        mysqli_free_result($tCol);
    }

    $jwoWt = [];
    $tItems = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_order_items'");
    if ($tItems && mysqli_num_rows($tItems) > 0) {
        mysqli_free_result($tItems);
        $qWt = 'SELECT jobwork_order_id, COALESCE(SUM(`' . mysqli_real_escape_string($conn, $wtCol) . '`), 0) AS w FROM tbl_jobwork_order_items GROUP BY jobwork_order_id';
        $rw = @mysqli_query($conn, $qWt);
        if ($rw) {
            while ($row = mysqli_fetch_assoc($rw)) {
                $jwoWt[(int) $row['jobwork_order_id']] = (float) $row['w'];
            }
            mysqli_free_result($rw);
        }
    } elseif ($tItems) {
        mysqli_free_result($tItems);
    }

    $hasFromDeptCol = false;
    $hasFromUserCol = false;
    $hasTotalWtCol = false;
    $hasMetalWtCol = false;
    $hasLossWtCol = false;
    if ($hasActivity) {
        $cfd = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_queue_activity LIKE 'from_dept_id'");
        $hasFromDeptCol = ($cfd && mysqli_num_rows($cfd) > 0);
        if ($cfd) {
            mysqli_free_result($cfd);
        }
        $cfu = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_queue_activity LIKE 'from_user_id'");
        $hasFromUserCol = ($cfu && mysqli_num_rows($cfu) > 0);
        if ($cfu) {
            mysqli_free_result($cfu);
        }
        $ctw = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_queue_activity LIKE 'total_wt_after'");
        $hasTotalWtCol = ($ctw && mysqli_num_rows($ctw) > 0);
        if ($ctw) {
            mysqli_free_result($ctw);
        }
        $cmw = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_queue_activity LIKE 'metal_wt_after'");
        $hasMetalWtCol = ($cmw && mysqli_num_rows($cmw) > 0);
        if ($cmw) {
            mysqli_free_result($cmw);
        }
        $clw = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_queue_activity LIKE 'loss_wt_after'");
        $hasLossWtCol = ($clw && mysqli_num_rows($clw) > 0);
        if ($clw) {
            mysqli_free_result($clw);
        }
    }

    /** @var array<int,array<int,array<string,float>>> $cell */
    $cell = [];
    $touch = static function (&$cell, int $deptId, int $userId) use ($emptyRow) {
        if ($deptId < 1) {
            return;
        }
        // userId 0 = department transfer without assigned worker
        if ($userId < 0) {
            return;
        }
        if (!isset($cell[$deptId])) {
            $cell[$deptId] = [];
        }
        if (!isset($cell[$deptId][$userId])) {
            $cell[$deptId][$userId] = $emptyRow();
            $cell[$deptId][$userId]['name'] = '';
        }
    };

    $actWeight = static function (array $act, array $jwoWt) use ($hasTotalWtCol, $hasMetalWtCol): float {
        if ($hasTotalWtCol && isset($act['total_wt_after']) && $act['total_wt_after'] !== null && $act['total_wt_after'] !== '') {
            $w = (float) $act['total_wt_after'];
            if (abs($w) > 0.0000001) {
                return $w;
            }
        }
        if ($hasMetalWtCol && isset($act['metal_wt_after']) && $act['metal_wt_after'] !== null && $act['metal_wt_after'] !== '') {
            $w = (float) $act['metal_wt_after'];
            if (abs($w) > 0.0000001) {
                return $w;
            }
        }
        $jwoId = (int) ($act['jobwork_order_id'] ?? 0);
        return isset($jwoWt[$jwoId]) ? (float) $jwoWt[$jwoId] : 0.0;
    };

    $applyActivity = static function (array $act, ?array $prev, array &$cell) use (
        $touch,
        $actWeight,
        $jwoWt,
        $mainDeptId,
        $castDeptId,
        $hasFromDeptCol,
        $hasFromUserCol
    ) {
        $jwoId = (int) ($act['jobwork_order_id'] ?? 0);
        $T = (int) ($act['to_dept_id'] ?? 0);
        $W = (int) ($act['to_user_id'] ?? 0);
        if ($jwoId < 1 || $T < 1) {
            return;
        }
        if ($W < 0) {
            $W = 0;
        }
        $wt = $actWeight($act, $jwoWt);

        $F = 0;
        $PU = 0;
        if ($hasFromDeptCol) {
            $F = (int) ($act['from_dept_id'] ?? 0);
        }
        if ($hasFromUserCol) {
            $PU = (int) ($act['from_user_id'] ?? 0);
        }
        if ($F < 1 && $prev) {
            $F = (int) ($prev['to_dept_id'] ?? 0);
            if ($PU < 1) {
                $PU = (int) ($prev['to_user_id'] ?? 0);
            }
        }
        if ($PU < 0) {
            $PU = 0;
        }

        $fEff = $F > 0 ? $F : ($mainDeptId !== null ? (int) $mainDeptId : 0);

        $touch($cell, $T, $W);
        if ($mainDeptId !== null && (int) $T === (int) $mainDeptId) {
            $cell[$T][$W]['main_office_in'] += $wt;
        }
        if ($castDeptId !== null && (int) $T === (int) $castDeptId) {
            $cell[$T][$W]['casting_in'] += $wt;
            if ($mainDeptId !== null && $fEff === (int) $mainDeptId) {
                $cell[$T][$W]['main_office_in'] += $wt;
            }
        }
        $isFromOther = $fEff > 0
            && ($mainDeptId === null || $fEff !== (int) $mainDeptId)
            && ($castDeptId === null || $fEff !== (int) $castDeptId);
        if ($isFromOther) {
            $cell[$T][$W]['other_in'] += $wt;
        }
        // First receipt into a department with no usable "from" still counts as other_in.
        if ($fEff < 1 && !($mainDeptId !== null && (int) $T === (int) $mainDeptId)
            && !($castDeptId !== null && (int) $T === (int) $castDeptId)) {
            $cell[$T][$W]['other_in'] += $wt;
        }

        if ($fEff > 0 && $T > 0 && $fEff !== $T) {
            if ($mainDeptId !== null && $fEff === (int) $mainDeptId) {
                $touch($cell, (int) $mainDeptId, $PU);
                $cell[$mainDeptId][$PU]['main_office_out'] += $wt;
            } else {
                $touch($cell, $fEff, $PU);
                $cell[$fEff][$PU]['other_out'] += $wt;
            }
        }
    };

    $chains = [];
    $openingCell = [];
    if ($hasActivity) {
        $ymd = mysqli_real_escape_string($conn, $reportYmd);

        // Opening: all activity before report date
        $sqlBefore = 'SELECT * FROM tbl_jobwork_queue_activity WHERE DATE(created_at) < \'' . $ymd . '\' ORDER BY jobwork_order_id ASC, created_at ASC, id ASC';
        $beforeActs = function_exists('getList') ? getList($sqlBefore) : [];
        if (!is_array($beforeActs)) {
            $beforeActs = [];
        }
        $beforeChains = [];
        foreach ($beforeActs as $a) {
            $jid = (int) ($a['jobwork_order_id'] ?? 0);
            if ($jid < 1) {
                continue;
            }
            if (!isset($beforeChains[$jid])) {
                $beforeChains[$jid] = [];
            }
            $beforeChains[$jid][] = $a;
        }
        foreach ($beforeActs as $act) {
            $jid = (int) ($act['jobwork_order_id'] ?? 0);
            $chain = $beforeChains[$jid] ?? [];
            $prev = null;
            foreach ($chain as $row) {
                if ((int) ($row['id'] ?? 0) === (int) ($act['id'] ?? 0)) {
                    break;
                }
                $prev = $row;
            }
            $applyActivity($act, $prev, $openingCell);
        }

        $sqlDay = 'SELECT * FROM tbl_jobwork_queue_activity WHERE DATE(created_at) = \'' . $ymd . '\' ORDER BY jobwork_order_id ASC, created_at ASC, id ASC';
        $dayActs = function_exists('getList') ? getList($sqlDay) : [];
        if (!is_array($dayActs)) {
            $dayActs = [];
        }

        $jwoIds = [];
        foreach ($dayActs as $a) {
            $jwoIds[(int) ($a['jobwork_order_id'] ?? 0)] = true;
        }
        $jwoIds = array_keys(array_filter($jwoIds));

        if (!empty($jwoIds)) {
            $in = implode(',', array_map('intval', $jwoIds));
            $sqlAll = 'SELECT * FROM tbl_jobwork_queue_activity WHERE jobwork_order_id IN (' . $in . ') ORDER BY jobwork_order_id ASC, created_at ASC, id ASC';
            $allActs = function_exists('getList') ? getList($sqlAll) : [];
            if (!is_array($allActs)) {
                $allActs = [];
            }
            foreach ($allActs as $a) {
                $jid = (int) ($a['jobwork_order_id'] ?? 0);
                if ($jid < 1) {
                    continue;
                }
                if (!isset($chains[$jid])) {
                    $chains[$jid] = [];
                }
                $chains[$jid][] = $a;
            }
        }

        foreach ($dayActs as $act) {
            $jwoId = (int) ($act['jobwork_order_id'] ?? 0);
            $chain = $chains[$jwoId] ?? [];
            $prev = null;
            foreach ($chain as $row) {
                if ((int) ($row['id'] ?? 0) === (int) ($act['id'] ?? 0)) {
                    break;
                }
                $prev = $row;
            }
            $applyActivity($act, $prev, $cell);
        }
    }

    $lossByDeptUser = [];
    $lossOpeningByDeptUser = [];
    $tWadj = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_weight_adjustments'");
    $hasWadj = ($tWadj && mysqli_num_rows($tWadj) > 0);
    if ($tWadj) {
        mysqli_free_result($tWadj);
    }
    $hasDeptUser = false;
    $cdu = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_orders LIKE 'department_user_id'");
    if ($cdu && mysqli_num_rows($cdu) > 0) {
        $hasDeptUser = true;
    }
    if ($cdu) {
        mysqli_free_result($cdu);
    }
    $hasDeptCol = false;
    $cdd = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_orders LIKE 'department_id'");
    if ($cdd && mysqli_num_rows($cdd) > 0) {
        $hasDeptCol = true;
    }
    if ($cdd) {
        mysqli_free_result($cdd);
    }
    $hasSrcDept = false;
    $hasSrcUser = false;
    if ($hasWadj) {
        $csd = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_weight_adjustments LIKE 'source_department_id'");
        $hasSrcDept = ($csd && mysqli_num_rows($csd) > 0);
        if ($csd) {
            mysqli_free_result($csd);
        }
        $csu = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_weight_adjustments LIKE 'source_user_id'");
        $hasSrcUser = ($csu && mysqli_num_rows($csu) > 0);
        if ($csu) {
            mysqli_free_result($csu);
        }
    }

    $collectLoss = static function (string $dateSql, array &$bucket) use (
        $conn,
        $hasWadj,
        $hasDeptCol,
        $hasDeptUser,
        $hasSrcDept,
        $hasSrcUser
    ) {
        if (!$hasWadj) {
            return;
        }
        $sel = 'w.jobwork_order_id, w.adjustment_type, w.weight_grams';
        if ($hasSrcDept) {
            $sel .= ', w.source_department_id';
        }
        if ($hasSrcUser) {
            $sel .= ', w.source_user_id';
        }
        if ($hasDeptCol) {
            $sel .= ', j.department_id';
        } else {
            $sel .= ', 0 AS department_id';
        }
        if ($hasDeptUser) {
            $sel .= ', j.department_user_id';
        } else {
            $sel .= ', 0 AS department_user_id';
        }
        $join = $hasDeptCol
            ? ' INNER JOIN tbl_jobwork_orders j ON j.id = w.jobwork_order_id'
            : ' LEFT JOIN tbl_jobwork_orders j ON j.id = w.jobwork_order_id';
        $sqlLoss = 'SELECT ' . $sel . ' FROM tbl_jobwork_weight_adjustments w' . $join
            . ' WHERE DATE(w.created_at) ' . $dateSql;
        $lossRows = function_exists('getList') ? getList($sqlLoss) : [];
        if (!is_array($lossRows)) {
            $lossRows = [];
        }
        foreach ($lossRows as $lr) {
            $dd = $hasSrcDept ? (int) ($lr['source_department_id'] ?? 0) : 0;
            $du = $hasSrcUser ? (int) ($lr['source_user_id'] ?? 0) : 0;
            if ($dd < 1) {
                $dd = (int) ($lr['department_id'] ?? 0);
            }
            if ($du < 1) {
                $du = $hasDeptUser ? (int) ($lr['department_user_id'] ?? 0) : 0;
            }
            if ($dd < 1) {
                continue;
            }
            if ($du < 0) {
                $du = 0;
            }
            $wg = (float) ($lr['weight_grams'] ?? 0);
            $typ = strtolower(trim((string) ($lr['adjustment_type'] ?? 'reduce')));
            $signed = ($typ === 'add') ? -$wg : $wg;
            if (!isset($bucket[$dd])) {
                $bucket[$dd] = [];
            }
            if (!isset($bucket[$dd][$du])) {
                $bucket[$dd][$du] = 0.0;
            }
            $bucket[$dd][$du] += $signed;
        }
    };

    $ymd = mysqli_real_escape_string($conn, $reportYmd);
    $collectLoss('< \'' . $ymd . '\'', $lossOpeningByDeptUser);
    $collectLoss('= \'' . $ymd . '\'', $lossByDeptUser);

    // Fold prior-day movement into opening, then seed today's cells.
    foreach ($openingCell as $deptId => $users) {
        foreach ($users as $uid => $r) {
            $touch($cell, (int) $deptId, (int) $uid);
            $otherNet = (float) ($r['other_in'] ?? 0) - (float) ($r['other_out'] ?? 0);
            $mainNet = (float) ($r['main_office_in'] ?? 0) - (float) ($r['main_office_out'] ?? 0);
            $lossPrior = (float) ($lossOpeningByDeptUser[$deptId][$uid] ?? 0);
            $cell[$deptId][$uid]['opening'] += $otherNet + $mainNet + (float) ($r['casting_in'] ?? 0) - $lossPrior;
        }
    }
    foreach ($lossOpeningByDeptUser as $deptId => $users) {
        foreach ($users as $uid => $lv) {
            if (!isset($openingCell[$deptId][$uid])) {
                $touch($cell, (int) $deptId, (int) $uid);
                $cell[$deptId][$uid]['opening'] -= (float) $lv;
            }
        }
    }

    $userIds = [];
    foreach ($cell as $du => $users) {
        foreach ($users as $uid => $_) {
            if ($uid > 0) {
                $userIds[$uid] = true;
            }
        }
    }
    foreach ($lossByDeptUser as $du => $users) {
        foreach ($users as $uid => $_) {
            if ($uid > 0) {
                $userIds[$uid] = true;
            }
        }
    }
    $names = [];
    if (!empty($userIds)) {
        $in = implode(',', array_map('intval', array_keys($userIds)));
        $nq = 'SELECT id, name FROM tbl_customers WHERE id IN (' . $in . ')';
        $nr = @mysqli_query($conn, $nq);
        if ($nr) {
            while ($row = mysqli_fetch_assoc($nr)) {
                $names[(int) $row['id']] = trim((string) ($row['name'] ?? ''));
            }
            mysqli_free_result($nr);
        }
        // Fallback: admin/users table if workers live there
        if (count($names) < count($userIds)) {
            $tu = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_users'");
            if ($tu && mysqli_num_rows($tu) > 0) {
                mysqli_free_result($tu);
                $missing = [];
                foreach (array_keys($userIds) as $uid) {
                    if (!isset($names[(int) $uid]) || $names[(int) $uid] === '') {
                        $missing[] = (int) $uid;
                    }
                }
                if ($missing !== []) {
                    $in2 = implode(',', $missing);
                    $uq = 'SELECT id, name FROM tbl_users WHERE id IN (' . $in2 . ')';
                    $ur = @mysqli_query($conn, $uq);
                    if ($ur) {
                        while ($row = mysqli_fetch_assoc($ur)) {
                            $nm = trim((string) ($row['name'] ?? ''));
                            if ($nm !== '') {
                                $names[(int) $row['id']] = $nm;
                            }
                        }
                        mysqli_free_result($ur);
                    }
                }
            } elseif ($tu) {
                mysqli_free_result($tu);
            }
        }
    }

    $out = [];
    foreach ($departments as $d) {
        $deptId = (int) ($d['id'] ?? 0);
        $label = trim((string) ($d['dept_name'] ?? ''));
        if ($label === '' || $deptId < 1) {
            continue;
        }

        $rows = [];
        $deptUsers = isset($cell[$deptId]) ? $cell[$deptId] : [];
        foreach ($lossByDeptUser[$deptId] ?? [] as $uid => $lv) {
            if (!isset($deptUsers[$uid])) {
                $deptUsers[$uid] = $emptyRow();
                $deptUsers[$uid]['name'] = '';
            }
        }

        if (empty($deptUsers)) {
            $rows[] = $emptyRow();
        } else {
            ksort($deptUsers, SORT_NUMERIC);
            foreach ($deptUsers as $uid => $r) {
                if ($uid < 0) {
                    continue;
                }
                if ((int) $uid === 0) {
                    $r['name'] = 'Unassigned';
                } else {
                    $r['name'] = isset($names[$uid]) && $names[$uid] !== '' ? $names[$uid] : ('User #' . $uid);
                }
                if (isset($lossByDeptUser[$deptId][$uid])) {
                    $r['loss'] += (float) $lossByDeptUser[$deptId][$uid];
                }
                $r['other_net'] = (float) $r['other_in'] - (float) $r['other_out'];
                $r['main_office_net'] = (float) $r['main_office_in'] - (float) $r['main_office_out'];
                $opening = (float) $r['opening'];
                $r['closing'] = $opening + $r['main_office_net'] + $r['other_net'] + (float) $r['casting_in'] - (float) $r['loss'];
                $rows[] = $r;
            }
        }

        $sums = [];
        foreach ($report_columns as $col) {
            $sums[$col['key']] = 0.0;
        }
        foreach ($rows as $r) {
            foreach ($report_columns as $col) {
                $k = $col['key'];
                $sums[$k] += (float) ($r[$k] ?? 0);
            }
        }

        $out[] = [
            'id' => $deptId,
            'title' => strtoupper($label),
            'rows' => $rows,
            'sums' => $sums,
        ];
    }

    return $out;
}
}

if (!function_exists('auragold_format_dept_report_num')) {
function auragold_format_dept_report_num(float $v): string
{
    return number_format($v, 3, '.', '');
}
}

