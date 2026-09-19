<?php
/**
 * Job work order: list comments (GET), update status or add comment (POST).
 */
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$allowed_statuses = [
    'Completed',
    'Hold',
    'Invoice Created',
    'Not Initiate',
    'Processing',
    'Rejected',
    'Transferred',
    'draft',
];

function mp_jwo_comments_ensure_table(mysqli $conn): bool
{
    $chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_order_comments'");
    if ($chk && mysqli_num_rows($chk) > 0) {
        mysqli_free_result($chk);
        return true;
    }
    if ($chk) {
        mysqli_free_result($chk);
    }
    $sql = "CREATE TABLE IF NOT EXISTS `tbl_jobwork_order_comments` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `jobwork_order_id` int(11) NOT NULL,
      `comment_text` varchar(2000) NOT NULL,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `created_by_user_id` int(11) DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `jobwork_order_id` (`jobwork_order_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    return (bool) @mysqli_query($conn, $sql);
}

function mp_jwo_comments_uid(): int
{
    if (!empty($_SESSION['Admin']['id'])) {
        return (int)$_SESSION['Admin']['id'];
    }
    if (!empty($_SESSION['user_id'])) {
        return (int)$_SESSION['user_id'];
    }
    return 0;
}

$jobwork_order_id = isset($_REQUEST['jobwork_order_id']) ? (int)$_REQUEST['jobwork_order_id'] : 0;

$tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_orders'");
if (!$tbl || mysqli_num_rows($tbl) === 0) {
    if ($tbl) {
        mysqli_free_result($tbl);
    }
    echo json_encode(['ok' => false, 'message' => 'Job work orders table not found']);
    exit;
}
mysqli_free_result($tbl);

if ($jobwork_order_id < 1) {
    echo json_encode(['ok' => false, 'message' => 'Invalid job work order']);
    exit;
}

$exists = @mysqli_query($conn, 'SELECT id, status FROM tbl_jobwork_orders WHERE id = ' . $jobwork_order_id . ' LIMIT 1');
if (!$exists || mysqli_num_rows($exists) === 0) {
    if ($exists) {
        mysqli_free_result($exists);
    }
    echo json_encode(['ok' => false, 'message' => 'Job work order not found']);
    exit;
}
$row = mysqli_fetch_assoc($exists);
mysqli_free_result($exists);
$current_status = isset($row['status']) ? trim((string)$row['status']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    mp_jwo_comments_ensure_table($conn);
    $comments = [];
    $q = @mysqli_query(
        $conn,
        'SELECT id, comment_text, created_at FROM tbl_jobwork_order_comments WHERE jobwork_order_id = '
        . $jobwork_order_id . ' ORDER BY id DESC LIMIT 200'
    );
    if ($q) {
        while ($r = mysqli_fetch_assoc($q)) {
            $comments[] = [
                'id' => (int)$r['id'],
                'comment_text' => (string)$r['comment_text'],
                'created_at' => (string)$r['created_at'],
            ];
        }
        mysqli_free_result($q);
    }
    echo json_encode([
        'ok' => true,
        'status' => $current_status,
        'comments' => $comments,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$action = isset($_POST['action']) ? trim((string)$_POST['action']) : '';

if ($action === 'update_status') {
    $status = isset($_POST['status']) ? trim((string)$_POST['status']) : '';
    if (!in_array($status, $allowed_statuses, true)) {
        echo json_encode(['ok' => false, 'message' => 'Invalid status']);
        exit;
    }
    $stmt = mysqli_prepare($conn, 'UPDATE tbl_jobwork_orders SET status = ? WHERE id = ? LIMIT 1');
    if (!$stmt) {
        echo json_encode(['ok' => false, 'message' => 'Database error']);
        exit;
    }
    mysqli_stmt_bind_param($stmt, 'si', $status, $jobwork_order_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if (!$ok) {
        echo json_encode(['ok' => false, 'message' => 'Could not update status']);
        exit;
    }
    // Keep sale-order-process.php / filters in sync: JWO completed from manufacturing uses this API, not save-jobwork-order.php
    if (strtolower(trim($status)) === 'completed' && function_exists('getRecord')) {
        $jwo_so = getRecord('SELECT sale_order_id FROM tbl_jobwork_orders WHERE id = ' . (int)$jobwork_order_id . ' LIMIT 1');
        $sid = isset($jwo_so['sale_order_id']) ? (int)$jwo_so['sale_order_id'] : 0;
        if ($sid > 0) {
            @mysqli_query($conn, 'UPDATE tbl_sale_orders SET status = \'completed\', updated_at = NOW() WHERE id = ' . $sid);
        }
    }
    echo json_encode(['ok' => true, 'message' => 'Status updated.', 'status' => $status]);
    exit;
}

if ($action === 'add_comment') {
    if (!mp_jwo_comments_ensure_table($conn)) {
        echo json_encode(['ok' => false, 'message' => 'Could not create comments table']);
        exit;
    }
    $text = isset($_POST['comment_text']) ? trim((string)$_POST['comment_text']) : '';
    if ($text === '') {
        echo json_encode(['ok' => false, 'message' => 'Enter a comment']);
        exit;
    }
    if (function_exists('mb_strlen') && mb_strlen($text) > 2000) {
        $text = mb_substr($text, 0, 2000);
    } elseif (strlen($text) > 2000) {
        $text = substr($text, 0, 2000);
    }
    $uid = mp_jwo_comments_uid();
    if ($uid > 0) {
        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO tbl_jobwork_order_comments (jobwork_order_id, comment_text, created_by_user_id) VALUES (?, ?, ?)'
        );
        if (!$stmt) {
            echo json_encode(['ok' => false, 'message' => 'Database error']);
            exit;
        }
        mysqli_stmt_bind_param($stmt, 'isi', $jobwork_order_id, $text, $uid);
    } else {
        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO tbl_jobwork_order_comments (jobwork_order_id, comment_text) VALUES (?, ?)'
        );
        if (!$stmt) {
            echo json_encode(['ok' => false, 'message' => 'Database error']);
            exit;
        }
        mysqli_stmt_bind_param($stmt, 'is', $jobwork_order_id, $text);
    }
    $ok = mysqli_stmt_execute($stmt);
    $new_id = $ok ? (int)mysqli_insert_id($conn) : 0;
    mysqli_stmt_close($stmt);
    if (!$ok || $new_id < 1) {
        echo json_encode(['ok' => false, 'message' => 'Could not save comment']);
        exit;
    }
    $cr = date('Y-m-d H:i:s');
    $fq = @mysqli_query($conn, 'SELECT comment_text, created_at FROM tbl_jobwork_order_comments WHERE id = ' . $new_id . ' LIMIT 1');
    if ($fq && ($fr = mysqli_fetch_assoc($fq))) {
        $text = (string)$fr['comment_text'];
        $cr = (string)$fr['created_at'];
        mysqli_free_result($fq);
    } elseif ($fq) {
        mysqli_free_result($fq);
    }
    echo json_encode([
        'ok' => true,
        'message' => 'Comment saved.',
        'comment' => [
            'id' => $new_id,
            'comment_text' => $text,
            'created_at' => $cr,
        ],
    ]);
    exit;
}

echo json_encode(['ok' => false, 'message' => 'Unknown action']);
