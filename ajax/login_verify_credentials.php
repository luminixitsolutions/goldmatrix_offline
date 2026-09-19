<?php
/**
 * JSON: after valid username + password, return branch dropdown options (tbl_users may live in each branch DB).
 */
require_once dirname(__DIR__) . '/includes/session_init.php';
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/login_authenticate.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$username         = trim((string) ($_POST['username'] ?? ''));
$password         = trim((string) ($_POST['password'] ?? ''));
$branch_entry     = isset($_POST['branch_entry']) ? (int) $_POST['branch_entry'] : 0;
$login_target_url = trim((string) ($_POST['login_target_url'] ?? ''));

if ($login_target_url === '') {
    echo json_encode(['success' => false, 'message' => 'IP address / server URL is required.']);
    exit;
}
if (strlen($login_target_url) > 500) {
    echo json_encode(['success' => false, 'message' => 'IP address / URL is too long.']);
    exit;
}
if (session_status() === PHP_SESSION_ACTIVE) {
    unset($_SESSION['login_target_ip']);
    if ($login_target_url !== '') {
        $_SESSION['login_target_url'] = $login_target_url;
    } else {
        unset($_SESSION['login_target_url']);
    }
}

$disc = auragold_discover_branch_logins_for_credentials($username, $password, $branch_entry, $login_target_url);
if (empty($disc['success'])) {
    echo json_encode([
        'success' => false,
        'message' => $disc['message'] ?? 'Invalid username or password',
    ]);
    exit;
}

if (strcasecmp($username, 'superbranch') === 0) {
    if (!auragold_super_portal_login_target_ok($login_target_url)) {
        echo json_encode([
            'success' => false,
            'message' => 'Super branch login is only allowed from the main GoldMatrix portal URL (main.goldmatrixsoft.com).',
        ]);
        exit;
    }
    $db0 = function_exists('auragold_login_expected_db_name_for_branch_id')
        ? trim((string) auragold_login_expected_db_name_for_branch_id(0))
        : (defined('DB_NAME') ? trim((string) DB_NAME) : '');
    if ($db0 === '' && defined('AURAGOLD_REGISTRY_DB')) {
        $db0 = trim((string) AURAGOLD_REGISTRY_DB);
    }
    echo json_encode([
        'success'              => true,
        'superbranch_direct'   => true,
        'login_branch_id'      => 0,
        'login_db_name'         => $db0,
        'is_superadmin'         => !empty($disc['is_superadmin']),
    ]);
    exit;
}

if (!empty($disc['is_superadmin'])) {
    $saBranches = (isset($disc['branches']) && is_array($disc['branches'])) ? $disc['branches'] : [];
    echo json_encode([
        'success'         => true,
        'is_superadmin'   => true,
        'branches'        => $saBranches,
    ]);
    exit;
}

echo json_encode([
    'success'    => true,
    'is_superadmin' => false,
    'branches'   => $disc['branches'] ?? [],
]);
