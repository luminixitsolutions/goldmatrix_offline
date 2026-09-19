<?php
/**
 * JSON: branch dropdown filter for tbl_users.branch_labels (by username typed on login page).
 */
require_once dirname(__DIR__) . '/includes/session_init.php';
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/login_authenticate.php';

header('Content-Type: application/json; charset=utf-8');

$username = esc($_POST['username'] ?? $_GET['username'] ?? '');
if ($username === '') {
    echo json_encode(['scope' => 'all', 'allow_main' => true, 'sub_ids' => []]);
    exit;
}

$user = getRecord(
    "SELECT * FROM tbl_users WHERE LOWER(TRIM(Username)) = LOWER(TRIM('$username')) LIMIT 1"
);
if (!$user || !auragold_user_active($user)) {
    echo json_encode(['scope' => 'all', 'allow_main' => true, 'sub_ids' => []]);
    exit;
}

$f = auragold_login_branch_filter_for_user_row($user);
echo json_encode([
    'scope'      => $f['scope'],
    'allow_main' => !empty($f['allow_main']),
    'sub_ids'    => array_map('intval', $f['sub_ids'] ?? []),
]);
