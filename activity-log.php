<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/session_login_type.php';
require_once __DIR__ . '/includes/activity_logger.php';
require_once __DIR__ . '/includes/auragold_sidebar_nav_permissions.php';

if (empty($_SESSION['Admin']) && (int) ($_SESSION['user_id'] ?? 0) <= 0) {
    header('Location: index.php');
    exit;
}

if (function_exists('auragold_nav_can_page_keys') && !auragold_nav_can_page_keys('administration', 'activity_log')) {
    header('Location: dashboard.php');
    exit;
}

auragold_ensure_user_activity_tables($conn);

$filter_user = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
$filter_branch = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : 0;
$filter_action = isset($_GET['action']) ? strtolower(trim((string) $_GET['action'])) : '';
$filter_page = isset($_GET['page_name']) ? trim((string) $_GET['page_name']) : '';
$filter_status = isset($_GET['status']) ? strtolower(trim((string) $_GET['status'])) : '';
$filter_date_from = isset($_GET['date_from']) ? trim((string) $_GET['date_from']) : '';
$filter_date_to = isset($_GET['date_to']) ? trim((string) $_GET['date_to']) : '';
$filter_q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$filter_message = isset($_GET['message']) ? trim((string) $_GET['message']) : '';
$page_num = max(1, (int) ($_GET['p'] ?? 1));
$per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 10;
if (!in_array($per_page, [10, 25, 50, 100], true)) {
    $per_page = 10;
}
$offset = ($page_num - 1) * $per_page;

$allowed_actions = ['', 'login', 'logout', 'page_view', 'create', 'update', 'delete', 'view', 'other'];
if (!in_array($filter_action, $allowed_actions, true)) {
    $filter_action = '';
}
if ($filter_status !== '' && !in_array($filter_status, ['success', 'failed'], true)) {
    $filter_status = '';
}
if ($filter_date_from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date_from)) {
    $filter_date_from = '';
}
if ($filter_date_to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date_to)) {
    $filter_date_to = '';
}

function al_esc_like(mysqli $conn, string $s): string
{
    return mysqli_real_escape_string($conn, str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s));
}

function al_event_type_label(string $action): string
{
    $map = [
        'login' => 'Sign In',
        'logout' => 'Sign Out',
        'page_view' => 'View',
        'create' => 'Insert',
        'update' => 'Update',
        'delete' => 'Delete',
        'view' => 'View',
        'other' => 'Other',
    ];
    $a = strtolower(trim($action));
    return $map[$a] ?? ucfirst($a !== '' ? $a : 'Other');
}

function al_page_label(string $page): string
{
    $page = trim($page);
    if ($page === '') {
        return '—';
    }
    if (preg_match('/sign[\s_-]?in|login/i', $page)) {
        return 'Sign In';
    }
    $base = preg_replace('/\.php$/i', '', $page);
    $base = str_replace(['_', '-'], ' ', (string) $base);
    return ucwords(trim($base));
}

function al_fmt_dt(?string $dt): string
{
    if ($dt === null || $dt === '' || $dt === '0000-00-00 00:00:00') {
        return '—';
    }
    $ts = strtotime($dt);
    return $ts ? date('d-m-Y g:i A', $ts) : htmlspecialchars($dt, ENT_QUOTES, 'UTF-8');
}

function al_status_label(array $log): string
{
    $meta = [];
    if (!empty($log['meta_json'])) {
        $decoded = json_decode((string) $log['meta_json'], true);
        if (is_array($decoded)) {
            $meta = $decoded;
        }
    }
    $st = strtolower(trim((string) ($meta['status'] ?? $log['status'] ?? 'success')));
    if (in_array($st, ['fail', 'failed', 'error', 'failure'], true)) {
        return 'Failed';
    }
    return 'Success';
}

/** @return array<int,string> */
function al_branch_name_map(): array
{
    static $map = null;
    if (is_array($map)) {
        return $map;
    }
    $map = [];
    if (function_exists('getListMaster')) {
        $rows = getListMaster('SELECT id, name FROM tbl_branches ORDER BY name ASC');
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $id = (int) ($r['id'] ?? 0);
                if ($id > 0) {
                    $map[$id] = trim((string) ($r['name'] ?? '')) ?: ('Branch #' . $id);
                }
            }
        }
    }
    return $map;
}

$branch_map = al_branch_name_map();

$users_for_filter = [];
$uq = @mysqli_query(
    $conn,
    'SELECT DISTINCT user_id, username, user_name FROM tbl_user_activity_logs
     WHERE user_id > 0 ORDER BY user_name ASC, username ASC LIMIT 500'
);
if ($uq) {
    while ($row = mysqli_fetch_assoc($uq)) {
        $users_for_filter[] = $row;
    }
    mysqli_free_result($uq);
}

$pages_for_filter = [];
$pq = @mysqli_query(
    $conn,
    "SELECT DISTINCT page FROM tbl_user_activity_logs WHERE page IS NOT NULL AND TRIM(page) <> '' ORDER BY page ASC LIMIT 300"
);
if ($pq) {
    while ($row = mysqli_fetch_assoc($pq)) {
        $pages_for_filter[] = (string) ($row['page'] ?? '');
    }
    mysqli_free_result($pq);
}

$where = ['1=1'];
if ($filter_date_from !== '') {
    $where[] = "DATE(created_at) >= '" . mysqli_real_escape_string($conn, $filter_date_from) . "'";
}
if ($filter_date_to !== '') {
    $where[] = "DATE(created_at) <= '" . mysqli_real_escape_string($conn, $filter_date_to) . "'";
}
if ($filter_user > 0) {
    $where[] = 'user_id = ' . (int) $filter_user;
}
if ($filter_branch > 0) {
    $where[] = 'branch_id = ' . (int) $filter_branch;
}
if ($filter_action !== '') {
    $where[] = "action = '" . mysqli_real_escape_string($conn, $filter_action) . "'";
}
if ($filter_page !== '') {
    $where[] = "page = '" . mysqli_real_escape_string($conn, $filter_page) . "'";
}
$searchText = $filter_message !== '' ? $filter_message : $filter_q;
if ($searchText !== '') {
    $eq = al_esc_like($conn, $searchText);
    $where[] = "(username LIKE '%{$eq}%' OR user_name LIKE '%{$eq}%' OR ip_address LIKE '%{$eq}%'
        OR page LIKE '%{$eq}%' OR description LIKE '%{$eq}%' OR entity_id LIKE '%{$eq}%' OR entity_type LIKE '%{$eq}%')";
}
$where_sql = implode(' AND ', $where);

$total_rows = 0;
$logs = [];
$cntRes = @mysqli_query($conn, "SELECT COUNT(*) AS c FROM tbl_user_activity_logs WHERE {$where_sql}");
if ($cntRes && ($cr = mysqli_fetch_assoc($cntRes))) {
    $total_rows = (int) ($cr['c'] ?? 0);
}
if ($cntRes) {
    mysqli_free_result($cntRes);
}

$sql = "SELECT * FROM tbl_user_activity_logs WHERE {$where_sql}
        ORDER BY created_at DESC, id DESC LIMIT {$per_page} OFFSET {$offset}";
$res = @mysqli_query($conn, $sql);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        if ($filter_status !== '') {
            $st = strtolower(al_status_label($row));
            if ($filter_status === 'success' && $st !== 'success') {
                continue;
            }
            if ($filter_status === 'failed' && $st !== 'failed') {
                continue;
            }
        }
        $logs[] = $row;
    }
    mysqli_free_result($res);
}

$total_pages = max(1, (int) ceil(max(1, $total_rows) / $per_page));
if ($page_num > $total_pages) {
    $page_num = $total_pages;
}

$active_filter_count = 0;
if ($filter_date_from !== '') {
    $active_filter_count++;
}
if ($filter_date_to !== '') {
    $active_filter_count++;
}
if ($filter_user > 0) {
    $active_filter_count++;
}
if ($filter_branch > 0) {
    $active_filter_count++;
}
if ($filter_action !== '') {
    $active_filter_count++;
}
if ($filter_page !== '') {
    $active_filter_count++;
}
if ($filter_status !== '') {
    $active_filter_count++;
}
if ($filter_message !== '') {
    $active_filter_count++;
}

$qs_base = [
    'user_id' => $filter_user,
    'branch_id' => $filter_branch,
    'action' => $filter_action,
    'page_name' => $filter_page,
    'status' => $filter_status,
    'date_from' => $filter_date_from,
    'date_to' => $filter_date_to,
    'q' => $filter_q,
    'message' => $filter_message,
    'per_page' => $per_page,
];

$showing_from = $total_rows === 0 ? 0 : (($page_num - 1) * $per_page + 1);
$showing_to = min($total_rows, $page_num * $per_page);

$page_title = 'Activity Log — ' . (function_exists('auragold_app_name') ? auragold_app_name() : 'Gold Matrix');
$actionOpts = [
    'login' => 'Sign In',
    'logout' => 'Sign Out',
    'page_view' => 'View',
    'create' => 'Insert',
    'update' => 'Update',
    'delete' => 'Delete',
    'view' => 'View',
    'other' => 'Other',
];
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
    <?php include __DIR__ . '/header-script.php'; ?>
    <style>
        :root {
            --al-navy: #11294b;
            --al-gold: #c5a864;
            --al-border: #e2e8f0;
            --al-muted: #64748b;
            --al-text: #334155;
            --al-success: #16a34a;
            --al-success-bg: #ecfdf5;
            --al-success-border: #86efac;
        }
        /* Page scroll: match administration list pages (UM/CRM) */
        html.default-style, body {
            height: auto !important;
            min-height: 100%;
            overflow-x: hidden !important;
            overflow-y: auto !important;
        }
        .layout-wrapper,
        .layout-inner,
        .layout-container {
            height: auto !important;
            min-height: 0 !important;
            overflow: visible !important;
        }
        .layout-content {
            height: auto !important;
            min-height: calc(100vh - 120px) !important;
            overflow: visible !important;
            padding-bottom: 80px !important;
        }
        .al-page { padding: 16px 20px 40px; max-width: 100%; }
        .al-scroll-spacer { height: 48px; }
        .al-toolbar {
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
            gap: 12px; margin-bottom: 12px;
        }
        .al-title-wrap { display: flex; align-items: center; gap: 10px; }
        .al-title-wrap h1 { margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--al-navy); }
        .al-pill {
            display: inline-flex; align-items: center; padding: 4px 12px; border-radius: 999px;
            background: #f3e8ff; color: #6d28d9; font-size: 12px; font-weight: 700;
        }
        .al-tools { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .al-search {
            position: relative; min-width: 220px;
        }
        .al-search input {
            width: 100%; height: 36px; border: 1px solid var(--al-border); border-radius: 8px;
            padding: 0 12px 0 34px; font-size: 13px; color: var(--al-text); background: #fff;
        }
        .al-search i {
            position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
            color: var(--al-muted); font-size: 14px;
        }
        .al-icon-btn {
            position: relative; width: 36px; height: 36px; border: 1px solid var(--al-border);
            border-radius: 8px; background: #fff; color: var(--al-navy); display: inline-flex;
            align-items: center; justify-content: center; cursor: pointer; text-decoration: none;
        }
        .al-icon-btn:hover { background: #f8fafc; }
        .al-icon-btn#alColSettingsBtn {
            background: var(--al-navy);
            border: 2px solid var(--al-gold);
            color: var(--al-gold);
            box-shadow: 0 2px 8px rgba(17, 41, 75, 0.25);
        }
        .al-icon-btn#alColSettingsBtn:hover {
            background: #0c1d36;
            color: #e8c547;
        }
        .al-icon-btn .al-badge-count {
            position: absolute; top: -6px; right: -6px; min-width: 18px; height: 18px;
            border-radius: 999px; background: #ef4444; color: #fff; font-size: 10px; font-weight: 700;
            display: inline-flex; align-items: center; justify-content: center; padding: 0 4px;
        }
        .al-export {
            height: 36px; border: 1px solid var(--al-border); border-radius: 8px; background: #fff;
            color: var(--al-text); font-size: 13px; font-weight: 600; padding: 0 12px; cursor: pointer;
        }
        .al-card {
            background: #fff; border: 1px solid var(--al-border); border-radius: 10px; overflow: visible;
            margin-bottom: 24px;
        }
        .al-table-wrap {
            overflow-x: auto;
            border-radius: 10px 10px 0 0;
            border: 1px solid #cbd5e1;
            border-bottom: none;
            background: #fff;
        }
        .al-table { width: 100%; border-collapse: collapse; font-size: 13px; table-layout: fixed; }
        .al-table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            text-align: left;
            padding: 11px 12px;
            background: var(--al-navy);
            color: #fff;
            font-weight: 600;
            white-space: nowrap;
            font-size: 12px;
            letter-spacing: .02em;
            border: none;
            border-right: 1px solid rgba(255, 255, 255, 0.12);
            box-shadow: 0 1px 0 rgba(0, 0, 0, 0.12);
            vertical-align: middle;
        }
        .al-table thead th:last-child { border-right: none; }
        .al-table th .al-sort { opacity: .7; margin-left: 4px; font-size: 11px; color: rgba(255,255,255,.85); }
        .al-table tbody td {
            padding: 11px 12px;
            border: 1px solid #e2e8f0;
            color: var(--al-text);
            vertical-align: middle;
            background: #fff;
        }
        .al-table tbody tr:nth-child(even) td { background: #f8fafc; }
        .al-table tbody tr:hover td { background: #f1f5f9; }
        .al-table th.al-col-hidden,
        .al-table td.al-col-hidden { display: none !important; }
        .al-col-drag {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            vertical-align: middle;
            margin-right: 6px;
            cursor: grab;
            line-height: 0;
            user-select: none;
            touch-action: none;
            color: var(--al-gold);
        }
        .al-col-drag .feather {
            width: 15px;
            height: 15px;
            color: var(--al-gold);
        }
        .al-col-drag:active { cursor: grabbing; }
        .al-table thead th.al-col-dragging { opacity: 0.55; }
        .al-table thead th.al-col-drag-over {
            box-shadow: inset 0 0 0 2px rgba(255, 255, 255, 0.75);
        }
        .al-tools .al-settings-wrap { position: relative; }
        .al-col-menu {
            display: none; position: absolute; right: 0; top: calc(100% + 6px); width: 220px;
            background: #fff; border: 1px solid var(--al-border); border-radius: 10px;
            box-shadow: 0 12px 28px rgba(15, 23, 42, .14); z-index: 12000; padding: 8px 0;
        }
        .al-col-menu.show { display: block; }
        .al-col-menu h6 {
            margin: 0; padding: 8px 14px 6px; font-size: 12px; font-weight: 700; color: var(--al-navy);
            text-transform: uppercase; letter-spacing: .03em;
        }
        .al-col-menu label {
            display: flex; align-items: center; gap: 8px; margin: 0; padding: 8px 14px;
            font-size: 13px; color: var(--al-text); cursor: pointer; font-weight: 500;
        }
        .al-col-menu label:hover { background: #f8fafc; }
        .al-col-menu input { margin: 0; }
        .al-empty { padding: 48px 20px; text-align: center; color: var(--al-muted); }
        .al-status {
            display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 6px;
            border: 1px solid var(--al-success-border); background: var(--al-success-bg);
            color: var(--al-success); font-size: 12px; font-weight: 700;
        }
        .al-status.is-failed {
            border-color: #fecaca; background: #fef2f2; color: #dc2626;
        }
        .al-msg { max-width: 420px; line-height: 1.4; overflow: hidden; text-overflow: ellipsis; }
        .al-footer-bar {
            display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center;
            gap: 12px; padding: 12px 16px; border-top: 1px solid var(--al-border);
            background: #fafafa; font-size: 13px; color: var(--al-muted);
            border-radius: 0 0 10px 10px;
        }
        .al-footer-right { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .al-footer-bar select {
            height: 32px; border: 1px solid var(--al-border); border-radius: 6px;
            font-size: 12px; padding: 0 10px; background: #fff; color: var(--al-text);
        }
        .al-pager { display: flex; align-items: center; gap: 4px; }
        .al-pager a, .al-pager span {
            min-width: 32px; height: 32px; padding: 0 8px; border: 1px solid var(--al-border);
            border-radius: 6px; display: inline-flex; align-items: center; justify-content: center;
            color: var(--al-navy); text-decoration: none; background: #fff; font-weight: 600; font-size: 12px;
        }
        .al-pager a:hover { background: #f1f5f9; }
        .al-pager .al-page-num, .al-pager a.active {
            min-width: 32px; border-radius: 999px; border: none;
            background: #6d28d9; color: #fff; cursor: default;
        }
        .al-pager span.is-disabled { opacity: .45; cursor: not-allowed; }
        .al-modal-backdrop {
            display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, .45);
            z-index: 10800; align-items: center; justify-content: center; padding: 16px;
            overflow: auto; -webkit-overflow-scrolling: touch;
        }
        .al-modal-backdrop.show { display: flex; }
        .al-modal {
            width: 100%; max-width: 520px; max-height: min(92vh, 860px);
            background: #fff; border-radius: 12px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, .25);
            overflow: hidden; display: flex; flex-direction: column;
            margin: auto; flex-shrink: 0;
        }
        .al-modal-head {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 16px; border-bottom: 1px solid var(--al-border);
            flex-shrink: 0; background: #fff;
        }
        .al-modal-head h3 { margin: 0; font-size: 1rem; color: var(--al-navy); font-weight: 700; }
        .al-modal-close {
            width: 32px; height: 32px; border: none; background: transparent; font-size: 20px;
            color: var(--al-muted); cursor: pointer; border-radius: 6px;
        }
        .al-modal form {
            display: flex; flex-direction: column; min-height: 0; flex: 1 1 auto; overflow: hidden;
        }
        .al-modal-body {
            padding: 16px; display: grid; gap: 12px;
            overflow-x: hidden; overflow-y: auto; -webkit-overflow-scrolling: touch;
            flex: 1 1 auto; min-height: 0;
        }
        .al-modal-body label {
            display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;
        }
        .al-modal-body .form-control {
            width: 100%; height: 38px; border: 1px solid var(--al-border); border-radius: 8px;
            padding: 0 10px; font-size: 13px;
        }
        .al-modal-foot {
            display: flex; justify-content: flex-end; gap: 8px; padding: 12px 16px;
            border-top: 1px solid var(--al-border); flex-shrink: 0; background: #fff;
        }
        .al-btn {
            height: 36px; border-radius: 8px; font-size: 13px; font-weight: 700; padding: 0 14px;
            cursor: pointer; border: 1px solid transparent;
        }
        .al-btn-apply { background: #fff; color: #6d28d9; border-color: #c4b5fd; }
        .al-btn-apply:hover { background: #f5f3ff; }
        .al-btn-clear { background: #fff; color: #e11d48; border-color: #fda4af; }
        .al-btn-clear:hover { background: #fff1f2; }
        @media (max-width: 768px) {
            .al-search { min-width: 100%; width: 100%; }
            .al-msg { max-width: 220px; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="layout-content">
    <div class="al-page">
                    <div class="al-toolbar">
                        <div class="al-title-wrap">
                            <h1>Activity Log</h1>
                            <span class="al-pill">Administration</span>
                        </div>
                        <div class="al-tools">
                            <form class="al-search" method="get" action="activity-log.php" id="alQuickSearch">
                                <?php foreach ($qs_base as $k => $v) {
                                    if ($k === 'q' || $k === 'p') {
                                        continue;
                                    }
                                    if ($v === '' || $v === 0 || $v === '0') {
                                        continue;
                                    }
                                    echo '<input type="hidden" name="' . htmlspecialchars((string) $k, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . '">';
                                } ?>
                                <i class="feather icon-search"></i>
                                <input type="text" name="q" value="<?php echo htmlspecialchars($filter_q, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search...">
                            </form>
                            <button type="button" class="al-export" id="alExportBtn" title="Export visible rows">Export</button>
                            <button type="button" class="al-icon-btn" id="alFilterBtn" title="Advance Filter">
                                <i class="feather icon-filter"></i>
                                <?php if ($active_filter_count > 0) { ?>
                                    <span class="al-badge-count"><?php echo (int) $active_filter_count; ?></span>
                                <?php } ?>
                            </button>
                            <a class="al-icon-btn" href="activity-log.php?<?php echo htmlspecialchars(http_build_query($qs_base), ENT_QUOTES, 'UTF-8'); ?>" title="Refresh">
                                <i class="feather icon-refresh-cw"></i>
                            </a>
                            <div class="al-settings-wrap">
                                <button type="button" class="al-icon-btn" id="alColSettingsBtn" title="Show / hide columns" aria-label="Column settings" aria-haspopup="true">
                                    <i class="feather icon-settings"></i>
                                </button>
                                <div class="al-col-menu" id="alColSettingsMenu" role="menu">
                                    <h6>Show / Hide Columns</h6>
                                    <label><input type="checkbox" data-al-col="page" checked> Page Name</label>
                                    <label><input type="checkbox" data-al-col="event" checked> Event Type</label>
                                    <label><input type="checkbox" data-al-col="user" checked> User Name</label>
                                    <label><input type="checkbox" data-al-col="branch" checked> Branch Name</label>
                                    <label><input type="checkbox" data-al-col="message" checked> Message</label>
                                    <label><input type="checkbox" data-al-col="datetime" checked> Date &amp; Time</label>
                                    <label><input type="checkbox" data-al-col="status" checked> Status</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="al-card">
                        <div class="al-table-wrap">
                            <table class="al-table" id="alTable">
                                <thead>
                                    <tr id="alHeadRow">
                                        <th data-col="page"><span class="al-col-drag" title="Drag to reorder"><i class="feather icon-move"></i></span>Page Name <span class="al-sort">↕</span></th>
                                        <th data-col="event"><span class="al-col-drag" title="Drag to reorder"><i class="feather icon-move"></i></span>Event Type <span class="al-sort">↕</span></th>
                                        <th data-col="user"><span class="al-col-drag" title="Drag to reorder"><i class="feather icon-move"></i></span>User Name <span class="al-sort">↕</span></th>
                                        <th data-col="branch"><span class="al-col-drag" title="Drag to reorder"><i class="feather icon-move"></i></span>Branch Name <span class="al-sort">↕</span></th>
                                        <th data-col="message"><span class="al-col-drag" title="Drag to reorder"><i class="feather icon-move"></i></span>Message</th>
                                        <th data-col="datetime"><span class="al-col-drag" title="Drag to reorder"><i class="feather icon-move"></i></span>Date &amp; Time <span class="al-sort">↕</span></th>
                                        <th data-col="status"><span class="al-col-drag" title="Drag to reorder"><i class="feather icon-move"></i></span>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (!$logs) { ?>
                                    <tr><td colspan="7" class="al-empty">No activity found for this filter.</td></tr>
                                <?php } else {
                                    foreach ($logs as $log) {
                                        $uname = trim((string) ($log['user_name'] ?? ''));
                                        if ($uname === '') {
                                            $uname = (string) ($log['username'] ?? '');
                                        }
                                        $bid = (int) ($log['branch_id'] ?? 0);
                                        $bname = $bid > 0 && isset($branch_map[$bid]) ? $branch_map[$bid] : ($bid > 0 ? ('Branch #' . $bid) : '—');
                                        $msg = trim((string) ($log['description'] ?? ''));
                                        if ($msg === '') {
                                            $msg = al_event_type_label((string) ($log['action'] ?? '')) . ' — ' . al_page_label((string) ($log['page'] ?? ''));
                                        }
                                        $ip = trim((string) ($log['ip_address'] ?? ''));
                                        if ($ip !== '' && stripos($msg, 'ip') === false && strtolower((string) ($log['action'] ?? '')) === 'login') {
                                            $msg .= ', IpAddress : ' . $ip;
                                        }
                                        $status = al_status_label($log);
                                        ?>
                                        <tr>
                                            <td data-col="page"><?php echo htmlspecialchars(al_page_label((string) ($log['page'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td data-col="event"><?php echo htmlspecialchars(al_event_type_label((string) ($log['action'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td data-col="user"><?php echo htmlspecialchars($uname !== '' ? $uname : '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td data-col="branch"><?php echo htmlspecialchars($bname, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td data-col="message"><div class="al-msg"><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></div></td>
                                            <td data-col="datetime"><?php echo al_fmt_dt($log['created_at'] ?? null); ?></td>
                                            <td data-col="status">
                                                <span class="al-status<?php echo $status === 'Failed' ? ' is-failed' : ''; ?>">
                                                    <?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php }
                                } ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="al-footer-bar">
                            <span>Showing <?php echo (int) $showing_from; ?> to <?php echo (int) $showing_to; ?> of <?php echo (int) $total_rows; ?> entries</span>
                            <div class="al-footer-right">
                                <form method="get" action="activity-log.php" class="al-per-page">
                                    <?php foreach ($qs_base as $k => $v) {
                                        if ($k === 'per_page' || $k === 'p') {
                                            continue;
                                        }
                                        if ($v === '' || $v === 0 || $v === '0') {
                                            continue;
                                        }
                                        echo '<input type="hidden" name="' . htmlspecialchars((string) $k, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . '">';
                                    } ?>
                                    <select name="per_page" onchange="this.form.submit()" aria-label="Rows per page">
                                        <?php foreach ([10, 25, 50, 100] as $n) { ?>
                                            <option value="<?php echo $n; ?>"<?php echo $per_page === $n ? ' selected' : ''; ?>>Show <?php echo $n; ?> Items</option>
                                        <?php } ?>
                                    </select>
                                </form>
                                <div class="al-pager">
                                    <?php
                                    $mk = function ($p) use ($qs_base) {
                                        return 'activity-log.php?' . htmlspecialchars(http_build_query(array_merge($qs_base, ['p' => $p])), ENT_QUOTES, 'UTF-8');
                                    };
                                    if ($page_num > 1) {
                                        echo '<a href="' . $mk(1) . '" title="First"><i class="feather icon-chevrons-left"></i></a>';
                                        echo '<a href="' . $mk($page_num - 1) . '" title="Previous"><i class="feather icon-chevron-left"></i></a>';
                                    } else {
                                        echo '<span class="is-disabled" title="First"><i class="feather icon-chevrons-left"></i></span>';
                                        echo '<span class="is-disabled" title="Previous"><i class="feather icon-chevron-left"></i></span>';
                                    }
                                    echo '<span class="al-page-num">' . (int) $page_num . '</span>';
                                    if ($page_num < $total_pages) {
                                        echo '<a href="' . $mk($page_num + 1) . '" title="Next"><i class="feather icon-chevron-right"></i></a>';
                                        echo '<a href="' . $mk($total_pages) . '" title="Last"><i class="feather icon-chevrons-right"></i></a>';
                                    } else {
                                        echo '<span class="is-disabled" title="Next"><i class="feather icon-chevron-right"></i></span>';
                                        echo '<span class="is-disabled" title="Last"><i class="feather icon-chevrons-right"></i></span>';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <br><br><br>
                    <div class="al-scroll-spacer" aria-hidden="true"></div>
    </div>
</div>

<div class="al-modal-backdrop" id="alFilterModal" role="dialog" aria-modal="true" aria-labelledby="alFilterTitle">
    <div class="al-modal">
        <div class="al-modal-head">
            <h3 id="alFilterTitle">Advance Filter</h3>
            <button type="button" class="al-modal-close" id="alFilterClose" aria-label="Close">&times;</button>
        </div>
        <form method="get" action="activity-log.php" id="alFilterForm">
            <input type="hidden" name="per_page" value="<?php echo (int) $per_page; ?>">
            <div class="al-modal-body">
                <div>
                    <label for="alDateFrom">Date From</label>
                    <input type="date" class="form-control" id="alDateFrom" name="date_from" value="<?php echo htmlspecialchars($filter_date_from, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div>
                    <label for="alDateTo">Date To</label>
                    <input type="date" class="form-control" id="alDateTo" name="date_to" value="<?php echo htmlspecialchars($filter_date_to, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div>
                    <label for="alMessage">Message</label>
                    <input type="text" class="form-control" id="alMessage" name="message" placeholder="Search message" value="<?php echo htmlspecialchars($filter_message, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div>
                    <label for="alBranch">Branch</label>
                    <select class="form-control" id="alBranch" name="branch_id">
                        <option value="0">Select Branch</option>
                        <?php foreach ($branch_map as $bid => $bname) { ?>
                            <option value="<?php echo (int) $bid; ?>"<?php echo $filter_branch === (int) $bid ? ' selected' : ''; ?>><?php echo htmlspecialchars($bname, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div>
                    <label for="alUser">User Name</label>
                    <select class="form-control" id="alUser" name="user_id">
                        <option value="0">Select User Name</option>
                        <?php foreach ($users_for_filter as $u) {
                            $uid = (int) ($u['user_id'] ?? 0);
                            $label = trim((string) ($u['user_name'] ?? ''));
                            if ($label === '') {
                                $label = (string) ($u['username'] ?? '');
                            }
                            ?>
                            <option value="<?php echo $uid; ?>"<?php echo $filter_user === $uid ? ' selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div>
                    <label for="alPageName">Page Name</label>
                    <select class="form-control" id="alPageName" name="page_name">
                        <option value="">Select Page Name</option>
                        <?php foreach ($pages_for_filter as $pg) { ?>
                            <option value="<?php echo htmlspecialchars($pg, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $filter_page === $pg ? ' selected' : ''; ?>><?php echo htmlspecialchars(al_page_label($pg), ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div>
                    <label for="alEventType">Event Type</label>
                    <select class="form-control" id="alEventType" name="action">
                        <option value="">Select Event Type</option>
                        <?php foreach ($actionOpts as $k => $lab) { ?>
                            <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $filter_action === $k ? ' selected' : ''; ?>><?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div>
                    <label for="alStatus">Status</label>
                    <select class="form-control" id="alStatus" name="status">
                        <option value="">Select Status</option>
                        <option value="success"<?php echo $filter_status === 'success' ? ' selected' : ''; ?>>Success</option>
                        <option value="failed"<?php echo $filter_status === 'failed' ? ' selected' : ''; ?>>Failed</option>
                    </select>
                </div>
            </div>
            <div class="al-modal-foot">
                <button type="submit" class="al-btn al-btn-apply">Apply Filter</button>
                <a class="al-btn al-btn-clear" href="activity-log.php" style="display:inline-flex;align-items:center;text-decoration:none;">Clear Filter</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/footer-script.php'; ?>
<script>
(function () {
    var modal = document.getElementById('alFilterModal');
    var openBtn = document.getElementById('alFilterBtn');
    var closeBtn = document.getElementById('alFilterClose');
    function openModal() {
        if (!modal) return;
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    function closeModal() {
        if (!modal) return;
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }
    if (openBtn) openBtn.addEventListener('click', openModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeModal();
            closeColMenu();
        }
    });

    var exportBtn = document.getElementById('alExportBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function () {
            var table = document.getElementById('alTable');
            if (!table) return;
            var rows = table.querySelectorAll('tr');
            var csv = [];
            rows.forEach(function (tr) {
                var cols = tr.querySelectorAll('th,td');
                var line = [];
                cols.forEach(function (td) {
                    if (td.classList.contains('al-col-hidden')) {
                        return;
                    }
                    var t = (td.innerText || '').replace(/\s+/g, ' ').trim().replace(/"/g, '""');
                    line.push('"' + t + '"');
                });
                if (line.length) csv.push(line.join(','));
            });
            var blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'activity-log.csv';
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        });
    }

    var COL_VIS_KEY = 'auragold_activity_log_col_vis';
    var COL_ORDER_KEY = 'auragold_activity_log_col_order_v3';
    var settingsBtn = document.getElementById('alColSettingsBtn');
    var settingsMenu = document.getElementById('alColSettingsMenu');
    var table = document.getElementById('alTable');
    var headRow = document.getElementById('alHeadRow');

    function closeColMenu() {
        if (settingsMenu) settingsMenu.classList.remove('show');
    }

    function applyColVisibility(state) {
        if (!table || !state) return;
        Object.keys(state).forEach(function (key) {
            var show = !!state[key];
            table.querySelectorAll('th[data-col="' + key + '"], td[data-col="' + key + '"]').forEach(function (el) {
                el.classList.toggle('al-col-hidden', !show);
            });
            if (settingsMenu) {
                var cb = settingsMenu.querySelector('input[data-al-col="' + key + '"]');
                if (cb) cb.checked = show;
            }
        });
    }

    function loadColVisibility() {
        try {
            var raw = localStorage.getItem(COL_VIS_KEY);
            if (!raw) return null;
            var o = JSON.parse(raw);
            return o && typeof o === 'object' ? o : null;
        } catch (e) {
            return null;
        }
    }

    function saveColVisibility() {
        if (!settingsMenu) return;
        var state = {};
        settingsMenu.querySelectorAll('input[data-al-col]').forEach(function (cb) {
            state[cb.getAttribute('data-al-col')] = !!cb.checked;
        });
        try {
            localStorage.setItem(COL_VIS_KEY, JSON.stringify(state));
        } catch (e) {}
        applyColVisibility(state);
    }

    function currentColOrder() {
        if (!headRow) return [];
        return Array.prototype.map.call(headRow.querySelectorAll('th[data-col]'), function (th) {
            return th.getAttribute('data-col');
        });
    }

    function applyColOrder(order) {
        if (!table || !headRow || !Array.isArray(order) || !order.length) return;
        var thMap = {};
        headRow.querySelectorAll('th[data-col]').forEach(function (th) {
            thMap[th.getAttribute('data-col')] = th;
        });
        order.forEach(function (key) {
            if (thMap[key]) headRow.appendChild(thMap[key]);
        });
        table.querySelectorAll('tbody tr').forEach(function (tr) {
            if (tr.querySelector('td[colspan]')) return;
            var tdMap = {};
            tr.querySelectorAll('td[data-col]').forEach(function (td) {
                var k = td.getAttribute('data-col');
                if (k && !tdMap[k]) tdMap[k] = td;
            });
            order.forEach(function (key) {
                if (tdMap[key]) tr.appendChild(tdMap[key]);
            });
        });
    }

    function loadColOrder() {
        try {
            var raw = localStorage.getItem(COL_ORDER_KEY);
            if (!raw) return null;
            var o = JSON.parse(raw);
            return Array.isArray(o) ? o : null;
        } catch (e) {
            return null;
        }
    }

    function saveColOrder(order) {
        try {
            localStorage.setItem(COL_ORDER_KEY, JSON.stringify(order));
        } catch (e) {}
    }

    function normalizeColOrder(saved, current) {
        if (!saved || !saved.length) return current.slice();
        var set = {};
        current.forEach(function (k) { set[k] = true; });
        var out = [];
        saved.forEach(function (k) {
            if (set[k]) {
                out.push(k);
                delete set[k];
            }
        });
        current.forEach(function (k) {
            if (set[k]) out.push(k);
        });
        return out;
    }

    function initPointerColDrag() {
        if (!table || !headRow) return;
        var defaultOrder = currentColOrder();
        var savedOrder = normalizeColOrder(loadColOrder(), defaultOrder);
        if (savedOrder.length === defaultOrder.length) {
            applyColOrder(savedOrder);
        }

        headRow.querySelectorAll('th[data-col]').forEach(function (th) {
            var handle = th.querySelector('.al-col-drag');
            if (!handle) return;

            function clearDropHighlights() {
                headRow.querySelectorAll('th.al-col-drag-over').forEach(function (h) {
                    h.classList.remove('al-col-drag-over');
                });
            }

            function thFromPoint(clientX, clientY) {
                var el = document.elementFromPoint(clientX, clientY);
                if (!el || !el.closest) return null;
                return el.closest('#alHeadRow th[data-col]') || null;
            }

            handle.addEventListener('pointerdown', function (e) {
                if (e.button !== 0) return;
                var dragFromKey = th.getAttribute('data-col');
                if (!dragFromKey) return;
                e.preventDefault();
                th.classList.add('al-col-dragging');
                try {
                    handle.setPointerCapture(e.pointerId);
                } catch (errCap) { /* ignore */ }

                function onMove(ev) {
                    clearDropHighlights();
                    var over = thFromPoint(ev.clientX, ev.clientY);
                    if (over && over !== th) {
                        over.classList.add('al-col-drag-over');
                    }
                }

                function onEnd(ev) {
                    th.classList.remove('al-col-dragging');
                    clearDropHighlights();
                    try {
                        handle.releasePointerCapture(ev.pointerId);
                    } catch (errRel) { /* ignore */ }
                    handle.removeEventListener('pointermove', onMove);
                    handle.removeEventListener('pointerup', onEnd);
                    handle.removeEventListener('pointercancel', onEnd);

                    var over = thFromPoint(ev.clientX, ev.clientY);
                    if (!over || over === th) return;
                    var toKey = over.getAttribute('data-col');
                    if (!toKey || toKey === dragFromKey) return;

                    var order = currentColOrder();
                    var fromIdx = order.indexOf(dragFromKey);
                    var toIdx = order.indexOf(toKey);
                    if (fromIdx < 0 || toIdx < 0) return;
                    order.splice(fromIdx, 1);
                    order.splice(toIdx, 0, dragFromKey);
                    applyColOrder(order);
                    saveColOrder(order);
                }

                handle.addEventListener('pointermove', onMove);
                handle.addEventListener('pointerup', onEnd);
                handle.addEventListener('pointercancel', onEnd);
            });
        });
    }

    if (settingsBtn && settingsMenu) {
        settingsBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            settingsMenu.classList.toggle('show');
        });
        settingsMenu.addEventListener('click', function (e) {
            e.stopPropagation();
        });
        settingsMenu.querySelectorAll('input[data-al-col]').forEach(function (cb) {
            cb.addEventListener('change', function () {
                var checked = settingsMenu.querySelectorAll('input[data-al-col]:checked');
                if (!checked.length) {
                    cb.checked = true;
                    return;
                }
                saveColVisibility();
            });
        });
        document.addEventListener('click', function (e) {
            if (!settingsMenu.contains(e.target) && !settingsBtn.contains(e.target)) {
                closeColMenu();
            }
        });
        var savedVis = loadColVisibility();
        if (savedVis) applyColVisibility(savedVis);
    }

    initPointerColDrag();
})();
</script>
</body>
</html>
