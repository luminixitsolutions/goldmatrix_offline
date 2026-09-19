<?php
/**
 * Options for Add Diamond master pickers (Cut, Color, Shape, etc.).
 * Masters screen stores names in tbl_cut, tbl_color, tbl_shape, tbl_clarity, tbl_sieve_size, tbl_size.
 * Product rows may also have values in tbl_product_characteristics — we merge both sources.
 */
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

$field = isset($_GET['field']) ? trim((string)$_GET['field']) : '';
$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';

$pcMap = [
    'cut' => ['cut'],
    'color' => ['color'],
    'seivesize' => ['seive_size', 'sieve_size', 'seivesize', 'size_seive'],
    'size' => ['size'],
    'shape' => ['shape'],
    'clarity' => ['clarity'],
];

$masterTableMap = [
    'cut' => 'tbl_cut',
    'color' => 'tbl_color',
    'shape' => 'tbl_shape',
    'clarity' => 'tbl_clarity',
    'seivesize' => 'tbl_sieve_size',
    'size' => 'tbl_size',
];

if (!isset($pcMap[$field]) || !isset($masterTableMap[$field])) {
    echo json_encode(['success' => false, 'message' => 'Invalid field']);
    exit;
}

function mpTableExists($conn, $table)
{
    $t = mysqli_real_escape_string($conn, $table);
    $q = @mysqli_query($conn, "SHOW TABLES LIKE '{$t}'");
    if (!$q) {
        return false;
    }
    $ok = mysqli_num_rows($q) > 0;
    mysqli_free_result($q);
    return $ok;
}

function mpTableColumns($conn, $table)
{
    static $cache = [];
    // Cache per connection + table — same table name can differ across branch vs registry DB.
    $cid = (is_object($conn) && function_exists('spl_object_id')) ? spl_object_id($conn) : 0;
    $key = $cid . '|' . $table;
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $cols = [];
    $t = mysqli_real_escape_string($conn, $table);
    $res = @mysqli_query($conn, "SHOW COLUMNS FROM `{$t}`");
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $cols[strtolower((string)$r['Field'])] = true;
        }
        mysqli_free_result($res);
    }
    $cache[$key] = $cols;
    return $cols;
}

function fetchNamesFromMasterTable($conn, $table, $search)
{
    if (!mpTableExists($conn, $table)) {
        return [];
    }
    $cols = mpTableColumns($conn, $table);
    if (empty($cols['name'])) {
        return [];
    }
    $where = "TRIM(COALESCE(`name`,'')) <> ''";
    if (!empty($cols['status'])) {
        $where .= " AND `status` = 1";
    }
    if ($search !== '') {
        $s = mysqli_real_escape_string($conn, $search);
        $where .= " AND `name` LIKE '%{$s}%'";
    }
    $t = mysqli_real_escape_string($conn, $table);
    // ORDER BY must use alias/expression matching SELECT — MySQL 8+ ONLY_FULL_GROUP_BY rejects ORDER BY `name` with DISTINCT TRIM(name).
    $sql = "SELECT DISTINCT TRIM(`name`) AS v FROM `{$t}` WHERE {$where} ORDER BY v ASC LIMIT 200";
    // Must use $conn here — getList() always uses global $conn and can differ from the passed connection (branch vs main DB).
    $rows = [];
    $res = @mysqli_query($conn, $sql);
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $v = trim((string)($r['v'] ?? $r['V'] ?? ''));
            if ($v === '' && is_array($r) && count($r)) {
                $v = trim((string)reset($r));
            }
            if ($v !== '') {
                $rows[$v] = true;
            }
        }
        mysqli_free_result($res);
    }
    return array_keys($rows);
}

function fetchFromProductCharacteristics($conn, $field, $search)
{
    global $pcMap;
    $tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_product_characteristics'");
    if (!$tbl || mysqli_num_rows($tbl) === 0) {
        if ($tbl) {
            mysqli_free_result($tbl);
        }
        return [];
    }
    mysqli_free_result($tbl);

    $colRes = @mysqli_query($conn, 'SHOW COLUMNS FROM tbl_product_characteristics');
    $cols = [];
    if ($colRes) {
        while ($r = mysqli_fetch_assoc($colRes)) {
            $cols[strtolower((string)$r['Field'])] = true;
        }
        mysqli_free_result($colRes);
    }

    $targetCol = '';
    foreach ($pcMap[$field] as $cand) {
        if (!empty($cols[strtolower($cand)])) {
            $targetCol = $cand;
            break;
        }
    }
    if ($targetCol === '') {
        return [];
    }

    $where = "TRIM(COALESCE(pc.`{$targetCol}`,'')) <> ''";
    if ($search !== '') {
        $s = mysqli_real_escape_string($conn, $search);
        $where .= " AND pc.`{$targetCol}` LIKE '%{$s}%'";
    }

    $sql = "SELECT DISTINCT TRIM(pc.`{$targetCol}`) AS v
            FROM tbl_product_characteristics pc
            WHERE {$where}
            ORDER BY v ASC
            LIMIT 200";

    $rows = function_exists('getList') ? @getList($sql) : [];
    if (!is_array($rows)) {
        $rows = [];
    }
    $out = [];
    foreach ($rows as $r) {
        $v = trim((string)($r['v'] ?? ''));
        if ($v !== '') {
            $out[$v] = true;
        }
    }
    return array_keys($out);
}

$masterTable = $masterTableMap[$field];
$masterSet = [];
foreach (fetchNamesFromMasterTable($conn, $masterTable, $search) as $v) {
    if ($v !== '') {
        $masterSet[$v] = true;
    }
}
// Same master rows may exist only in the registry/main DB (e.g. SQL insert in phpMyAdmin) while the app uses branch $conn.
if (isset($conn_master) && $conn_master && $conn_master !== $conn) {
    foreach (fetchNamesFromMasterTable($conn_master, $masterTable, $search) as $v) {
        if ($v !== '') {
            $masterSet[$v] = true;
        }
    }
}
$fromMaster = array_keys($masterSet);
$fromPc = fetchFromProductCharacteristics($conn, $field, $search);

$merged = [];
foreach ($fromMaster as $v) {
    $merged[$v] = true;
}
foreach ($fromPc as $v) {
    $merged[$v] = true;
}
$out = array_keys($merged);
usort($out, 'strnatcasecmp');
if (count($out) > 200) {
    $out = array_slice($out, 0, 200);
}

// If branch/working DB has no master rows but Masters were added in the registry DB (phpMyAdmin on auragold), load from registry.
if (count($out) === 0 && defined('AURAGOLD_REGISTRY_DB')) {
    $regDb = AURAGOLD_REGISTRY_DB;
    $bootUser = getenv('AURAGOLD_BOOTSTRAP_USER') ?: 'root';
    $bootPass = getenv('AURAGOLD_BOOTSTRAP_PASS');
    if ($bootPass === false) {
        $bootPass = '';
    }
    $regConn = @mysqli_connect(DB_HOST, $bootUser, $bootPass, $regDb);
    if ($regConn) {
        mysqli_set_charset($regConn, 'utf8mb4');
        foreach (fetchNamesFromMasterTable($regConn, $masterTable, $search) as $v) {
            if ($v !== '') {
                $merged[$v] = true;
            }
        }
        mysqli_close($regConn);
        $out = array_keys($merged);
        usort($out, 'strnatcasecmp');
        if (count($out) > 200) {
            $out = array_slice($out, 0, 200);
        }
    }
}

echo json_encode(['success' => true, 'options' => $out], JSON_UNESCAPED_UNICODE);
