<?php
/**
 * Search ledger / department users for Job Work Order "Name" field.
 * Empty search: first 5 accounts. Typed search: filtered list (up to 30).
 */
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$search_term = isset($_GET['q']) ? esc($_GET['q']) : '';
$department_id = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
$all_ledgers = isset($_GET['all_ledgers']) && (string) $_GET['all_ledgers'] === '1';
$format_select2 = isset($_GET['format']) && (string) $_GET['format'] === 'select2';
$term_len = strlen($search_term);

$cust_tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_customers'");
if (!$cust_tbl || mysqli_num_rows($cust_tbl) === 0) {
    if ($cust_tbl) {
        mysqli_free_result($cust_tbl);
    }
    $empty = ['status' => 'success', 'users' => []];
    if ($format_select2) {
        $empty['results'] = [];
        $empty['pagination'] = ['more' => false];
    }
    echo json_encode($empty);
    exit;
}
mysqli_free_result($cust_tbl);

$job_worker_type_id = 0;
$jw_result = @mysqli_query($conn, "SELECT id FROM tbl_customer_types WHERE LOWER(name) = 'job worker' AND status = 1 LIMIT 1");
if ($jw_result && mysqli_num_rows($jw_result) > 0) {
    $jw_row = mysqli_fetch_assoc($jw_result);
    $job_worker_type_id = (int) $jw_row['id'];
    mysqli_free_result($jw_result);
} elseif ($jw_result) {
    mysqli_free_result($jw_result);
}

$type_sql = ($job_worker_type_id > 0) ? ' AND c.customer_type_id = ' . (int) $job_worker_type_id : '';

$search_sql = '';
if ($term_len > 0) {
    $like = $search_term;
    $search_sql = " AND (c.name LIKE '%$like%' OR IFNULL(c.alternate_name,'') LIKE '%$like%' OR IFNULL(c.mobile_no,'') LIKE '%$like%' "
        . "OR IFNULL(c.mail_id,'') LIKE '%$like%' OR IFNULL(c.first_name,'') LIKE '%$like%' OR IFNULL(c.last_name,'') LIKE '%$like%') ";
}

$limit = ($term_len === 0) ? 5 : 30;

if ($all_ledgers || $department_id < 1) {
    $users = getList(
        "SELECT c.id, c.name AS user_name, c.mobile_no, c.alternate_name FROM tbl_customers c "
        . "WHERE c.status = 1" . $search_sql
        . " ORDER BY c.name ASC LIMIT " . (int) $limit
    );
} else {
    $map_tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_department_user_map'");
    if (!$map_tbl || mysqli_num_rows($map_tbl) === 0) {
        if ($map_tbl) {
            mysqli_free_result($map_tbl);
        }
        $empty = ['status' => 'success', 'users' => [], 'message' => 'Department user map missing'];
        if ($format_select2) {
            $empty['results'] = [];
            $empty['pagination'] = ['more' => false];
        }
        echo json_encode($empty);
        exit;
    }
    mysqli_free_result($map_tbl);

    $did = (int) $department_id;
    $users = getList(
        "SELECT c.id, c.name AS user_name, c.mobile_no, c.alternate_name FROM tbl_customers c "
        . "INNER JOIN tbl_department_user_map m ON c.id = m.user_id AND m.status = 1 "
        . "WHERE m.department_id = $did AND c.status = 1"
        . $type_sql
        . $search_sql
        . " ORDER BY c.name ASC LIMIT " . (int) $limit
    );
}

if (!is_array($users)) {
    $users = [];
}

$out = ['status' => 'success', 'users' => $users];

if ($format_select2) {
    $results = [];
    foreach ($users as $u) {
        $name = trim((string) ($u['user_name'] ?? ''));
        $text = $name;
        if (!empty($u['mobile_no'])) {
            $text .= ($text !== '' ? ' — ' : '') . trim((string) $u['mobile_no']);
        }
        $results[] = [
            'id' => (string) (int) ($u['id'] ?? 0),
            'text' => $text !== '' ? $text : ('Account #' . (int) ($u['id'] ?? 0)),
            'user_name' => $name,
            'mobile_no' => (string) ($u['mobile_no'] ?? ''),
            'alternate_name' => (string) ($u['alternate_name'] ?? ''),
        ];
    }
    $out['results'] = $results;
    $out['pagination'] = ['more' => false];
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
