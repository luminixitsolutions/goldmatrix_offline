<?php
/**
 * JSON API for header notifications.
 */
session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_notifications.php';

header('Content-Type: application/json; charset=utf-8');

$uid = (int) ($_SESSION['user_id'] ?? $_SESSION['Admin']['id'] ?? 0);
if ($uid <= 0) {
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

if (!$conn instanceof mysqli) {
    echo json_encode(['ok' => false, 'error' => 'no_db']);
    exit;
}

$action = strtolower(trim((string) ($_REQUEST['action'] ?? 'list')));
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

auragold_ensure_notifications_table($conn);

if ($action === 'mark_one' && $method === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $id_safe = (string) $id;
        @mysqli_query($conn, "UPDATE tbl_auragold_notifications SET read_at = NOW() WHERE id = $id_safe AND read_at IS NULL");
    }
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'mark_all' && $method === 'POST') {
    @mysqli_query($conn, 'UPDATE tbl_auragold_notifications SET read_at = NOW() WHERE read_at IS NULL');
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'count' && $method === 'GET') {
    $n = 0;
    $rq = @mysqli_query($conn, 'SELECT COUNT(*) AS c FROM tbl_auragold_notifications WHERE read_at IS NULL');
    if ($rq && ($rw = mysqli_fetch_assoc($rq))) {
        $n = (int) ($rw['c'] ?? 0);
    }
    if ($rq) {
        mysqli_free_result($rq);
    }
    echo json_encode(['ok' => true, 'unread' => $n]);
    exit;
}

// list (default)
auragold_notifications_seed_due_today($conn);

$filter = strtolower(trim((string) ($_GET['filter'] ?? 'all')));
if (!in_array($filter, ['all', 'unread', 'read'], true)) {
    $filter = 'all';
}
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 80;
if ($limit < 1) {
    $limit = 80;
}
if ($limit > 500) {
    $limit = 500;
}

$sqlWhere = '';
if ($filter === 'unread') {
    $sqlWhere = ' WHERE read_at IS NULL ';
} elseif ($filter === 'read') {
    $sqlWhere = ' WHERE read_at IS NOT NULL ';
}

$unread = 0;
$rqc = @mysqli_query($conn, 'SELECT COUNT(*) AS c FROM tbl_auragold_notifications WHERE read_at IS NULL');
if ($rqc && ($rwc = mysqli_fetch_assoc($rqc))) {
    $unread = (int) ($rwc['c'] ?? 0);
}
if ($rqc) {
    mysqli_free_result($rqc);
}

$items = [];
$sql = 'SELECT id, title, message, created_at, read_at FROM tbl_auragold_notifications '
    . $sqlWhere
    . ' ORDER BY id DESC LIMIT ' . $limit;

$rs = @mysqli_query($conn, $sql);

if ($rs) {
    $now = time();
    while ($row = mysqli_fetch_assoc($rs)) {
        $cid = strtotime((string) ($row['created_at'] ?? ''));
        $ago = '';
        if ($cid !== false) {
            $sec = max(0, $now - $cid);
            if ($sec < 60) {
                $ago = $sec <= 1 ? '1 second ago' : $sec . ' seconds ago';
            } elseif ($sec < 3600) {
                $m = (int) floor($sec / 60);
                $ago = $m === 1 ? '1 minute ago' : $m . ' minutes ago';
            } elseif ($sec < 86400) {
                $h = (int) floor($sec / 3600);
                $ago = $h === 1 ? '1 hour ago' : $h . ' hours ago';
            } else {
                $d = (int) floor($sec / 86400);
                $ago = $d === 1 ? '1 day ago' : $d . ' days ago';
            }
        }
        $items[] = [
            'id' => (int) ($row['id'] ?? 0),
            'title' => (string) ($row['title'] ?? ''),
            'message' => (string) ($row['message'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'unread' => ($row['read_at'] === null || $row['read_at'] === ''),
            'time_ago' => $ago,
        ];
    }
    mysqli_free_result($rs);
}

echo json_encode(['ok' => true, 'unread' => $unread, 'items' => $items], JSON_UNESCAPED_UNICODE);
