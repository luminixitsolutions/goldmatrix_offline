<?php
/**
 * JSON login (same rules as login_submit.php).
 */
require_once dirname(__DIR__) . '/includes/session_init.php';
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/login_authenticate.php';
require_once dirname(__DIR__) . '/includes/branch_working_context.php';
require_once dirname(__DIR__) . '/includes/login_financial_years_helper.php';

$username        = trim((string) ($_POST['username'] ?? ''));
$password        = trim((string) ($_POST['password'] ?? ''));
$login_branch_id = isset($_POST['login_branch_id']) ? (int) $_POST['login_branch_id'] : 0;
$posted_db_name  = isset($_POST['login_db_name']) ? trim((string) $_POST['login_db_name']) : '';

if ($posted_db_name !== '' && function_exists('auragold_login_expected_db_name_for_branch_id')) {
    $expected = auragold_login_expected_db_name_for_branch_id($login_branch_id);
    if ($expected === '' || strcasecmp($posted_db_name, $expected) !== 0) {
        echo json_encode(['status' => 0, 'msg' => 'Invalid branch selection.']);
        exit;
    }
}

$result = auragold_attempt_login($username, $password, $login_branch_id);

if (!empty($result['success'])) {
    auragold_apply_login_page_user_role($login_branch_id);
    $ctx = auragold_apply_branch_working_context($login_branch_id);
    if (!$ctx['ok']) {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        echo json_encode([
            'status' => 0,
            'msg'    => $ctx['message'] ?? 'Could not open branch database.',
        ]);
        exit;
    }
    $ls = isset($_SESSION['login_source']) ? (string) $_SESSION['login_source'] : '';
    if ($ls === 'user') {
        if ($login_branch_id > 0) {
            $_SESSION['branch_id'] = $login_branch_id;
        } else {
            $mainBid = function_exists('auragold_registry_main_branch_id_for_login') ? auragold_registry_main_branch_id_for_login() : 0;
            if ($mainBid > 0) {
                $_SESSION['branch_id'] = $mainBid;
            } else {
                unset($_SESSION['branch_id']);
            }
        }
    } elseif ($ls === 'branch' && $login_branch_id > 0) {
        $_SESSION['branch_id'] = $login_branch_id;
    }

    if (function_exists('auragold_timezone_for_branch_id')) {
        $tzBid = (int) ($_SESSION['working_branch_id'] ?? $_SESSION['branch_id'] ?? $login_branch_id);
        if ($tzBid > 0) {
            $_SESSION['auragold_timezone'] = auragold_timezone_for_branch_id($tzBid);
            @date_default_timezone_set($_SESSION['auragold_timezone']);
            if (isset($conn) && $conn instanceof mysqli && function_exists('auragold_apply_mysql_session_timezone')) {
                auragold_apply_mysql_session_timezone($conn, $_SESSION['auragold_timezone']);
            }
        }
    }

    $fyRes = auragold_financial_year_login_validate_and_store();
    if (empty($fyRes['ok'])) {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        echo json_encode([
            'status' => 0,
            'msg'    => $fyRes['message'] ?? 'Financial year required.',
        ]);
        exit;
    }

    $remember = !empty($_POST['remember']) && (string) $_POST['remember'] !== '0';
    if ($remember) {
        $_SESSION['auragold_remember_me'] = 1;
    } else {
        unset($_SESSION['auragold_remember_me']);
    }

    if (function_exists('session_regenerate_id')) {
        @session_regenerate_id(true);
    }
    if (function_exists('auragold_session_refresh_live_cookie')) {
        auragold_session_refresh_live_cookie();
    }

    if (isset($conn) && $conn instanceof mysqli) {
        require_once dirname(__DIR__) . '/includes/activity_logger.php';
        if (function_exists('auragold_activity_log_login')) {
            $alConn = function_exists('auragold_activity_operational_conn')
                ? auragold_activity_operational_conn($conn)
                : $conn;
            auragold_activity_log_login($alConn);
            if ($alConn instanceof mysqli && $alConn !== $conn) {
                @mysqli_close($alConn);
            }
        }
    }

    if (function_exists('auragold_bind_conn_to_working_session')) {
        auragold_bind_conn_to_working_session();
    }
    if (function_exists('auragold_get_user_login_dashboard')) {
        require_once dirname(__DIR__) . '/includes/auragold_user_login_dashboard.php';
        if (!empty($_SESSION['Admin']) && is_array($_SESSION['Admin'])) {
            unset($_SESSION['Admin']['login_dashboard']);
        }
        auragold_get_user_login_dashboard((int) ($_SESSION['user_id'] ?? 0));
    }

    $redirect = 'dashboard.php';
    if (function_exists('auragold_resolve_post_login_redirect_url')) {
        $redirect = auragold_resolve_post_login_redirect_url((int) ($_SESSION['user_id'] ?? 0));
    }

    echo json_encode([
        'status'   => 1,
        'msg'      => $result['message'] ?? 'Login success',
        'redirect' => $redirect,
    ]);
    exit;
}

echo json_encode([
    'status' => 0,
    'msg'    => $result['message'] ?? 'Invalid username or password',
]);
exit;
