<?php
/**
 * Normal form POST login (works without JavaScript).
 */
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/login_authenticate.php';
require_once __DIR__ . '/includes/branch_working_context.php';
require_once __DIR__ . '/includes/login_financial_years_helper.php';
require_once __DIR__ . '/includes/auragold_user_login_dashboard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$username        = trim((string) ($_POST['username'] ?? ''));
$password        = trim((string) ($_POST['password'] ?? ''));
$login_branch_id = isset($_POST['login_branch_id']) ? (int) $_POST['login_branch_id'] : 0;
$posted_db_name  = isset($_POST['login_db_name']) ? trim((string) $_POST['login_db_name']) : '';
$target_url = trim((string) ($_POST['login_target_url'] ?? ''));
unset($_SESSION['login_target_ip']);
if ($target_url !== '' && strlen($target_url) <= 500) {
    $_SESSION['login_target_url'] = $target_url;
} else {
    unset($_SESSION['login_target_url']);
}

if ($target_url === '' || strlen($target_url) > 500) {
    header('Location: index.php?login_error=' . rawurlencode('IP address / server URL is required.'));
    exit;
}

$url_scope_id = 0;
if (function_exists('auragold_registry_branch_id_for_login_target_url')) {
    $url_scope_id = auragold_registry_branch_id_for_login_target_url($target_url);
}
$is_branch_portal_url = !auragold_super_portal_login_target_ok($target_url)
    && !auragold_gm_portal_login_target_ok($target_url);
// Branch subdomain URL (e.g. ratnapuram.goldmatrixsoft.com) always opens that registry row.
if ($url_scope_id > 0 && $is_branch_portal_url) {
    $login_branch_id = $url_scope_id;
}
if ($url_scope_id <= 0) {
    $localDev = defined('AURAGOLD_PROJECT') && (string) AURAGOLD_PROJECT === 'local';
    if (!$localDev) {
        header('Location: index.php?login_error=' . rawurlencode('This server address is not assigned to any branch. Set subdomain URL or IP in Branch settings.'));
        exit;
    }
} elseif (!auragold_super_portal_login_target_ok($target_url)
    && !(auragold_gm_portal_login_target_ok($target_url) && auragold_username_is_superadmin($username) && $login_branch_id === 0)
    && $login_branch_id !== $url_scope_id) {
    header('Location: index.php?login_error=' . rawurlencode('Selected branch does not match the server address.'));
    exit;
}

if ($username === '' || $password === '') {
    header('Location: index.php?login_error=' . rawurlencode('Username & Password are required'));
    exit;
}

if ($posted_db_name !== '' && function_exists('auragold_login_expected_db_name_for_branch_id')) {
    $expected = auragold_login_expected_db_name_for_branch_id($login_branch_id);
    if ($expected === '' || strcasecmp($posted_db_name, $expected) !== 0) {
        header('Location: index.php?login_error=' . rawurlencode('Invalid branch selection.'));
        exit;
    }
}

$portalOk = auragold_super_portal_login_target_ok($target_url);
$gmPortalOk = auragold_gm_portal_login_target_ok($target_url);
if (!$portalOk && !$gmPortalOk) {
    if (strcasecmp($username, 'superbranch') === 0) {
        header('Location: index.php?login_error=' . rawurlencode('Super branch login is only allowed from the main GoldMatrix portal URL (main.goldmatrixsoft.com).'));
        exit;
    }
    if ($login_branch_id === 0 && auragold_username_is_superadmin($username)) {
        header('Location: index.php?login_error=' . rawurlencode('Superadmin login to the default main branch is only allowed from the main GoldMatrix portal URL (main.goldmatrixsoft.com) or the GM portal URL (gm.goldmatrixsoft.com).'));
        exit;
    }
}

$result = auragold_attempt_login($username, $password, $login_branch_id);

if (!empty($result['success'])) {
    try {
        auragold_apply_login_page_user_role($login_branch_id);
        $ctx = auragold_apply_branch_working_context($login_branch_id);
        if (!$ctx['ok']) {
            // Sub-branch (or any non-main) context failed: do not fall back to main DB while "logged in".
            auragold_login_abort_to_index($ctx['message'] ?? 'Could not open branch database.');
        }
        $_SESSION['auragold_login_branch_id'] = $login_branch_id;
        if (auragold_username_is_superadmin($username) && auragold_gm_portal_login_target_ok($target_url)) {
            $_SESSION['auragold_may_create_main_branch'] = 1;
        } else {
            unset($_SESSION['auragold_may_create_main_branch']);
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

        // Branch timezone for all subsequent date()/NOW()-aligned saves in this session
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

        if (strcasecmp($username, 'superbranch') === 0) {
            $py = (int) ($_POST['financial_year_id'] ?? 0);
            if ($py <= 0) {
                $suggested = auragold_login_default_financial_year_id_for_branch($login_branch_id);
                if ($suggested > 0) {
                    $_POST['financial_year_id'] = $suggested;
                }
            }
        }

        $fyRes = auragold_financial_year_login_validate_and_store();
        if (empty($fyRes['ok'])) {
            auragold_login_abort_after_failed_financial_year($fyRes['message'] ?? 'Financial year required.');
        }

        $remember = !empty($_POST['remember']) && (string) $_POST['remember'] !== '0';
        if ($remember) {
            $_SESSION['auragold_remember_me'] = 1;
        } else {
            unset($_SESSION['auragold_remember_me']);
        }

        if (function_exists('session_regenerate_id')) {
            try {
                @session_regenerate_id(true);
            } catch (Throwable $e) {
                if (function_exists('error_log')) {
                    @error_log('login_submit session_regenerate_id: ' . $e->getMessage());
                }
            }
        }
        if (function_exists('auragold_session_refresh_live_cookie')) {
            auragold_session_refresh_live_cookie();
        }

        try {
            if (isset($conn) && $conn instanceof mysqli) {
                require_once __DIR__ . '/includes/activity_logger.php';
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
        } catch (Throwable $e) {
            if (function_exists('error_log')) {
                @error_log('login_submit activity log: ' . $e->getMessage());
            }
        }

        // working_db is set in-session above; rebind $conn so preference is read from the shop DB.
        if (function_exists('auragold_bind_conn_to_working_session')) {
            auragold_bind_conn_to_working_session();
        }
        try {
            if (function_exists('auragold_get_user_login_dashboard')) {
                if (!empty($_SESSION['Admin']) && is_array($_SESSION['Admin'])) {
                    unset($_SESSION['Admin']['login_dashboard']);
                }
                auragold_get_user_login_dashboard((int) ($_SESSION['user_id'] ?? 0));
            }
        } catch (Throwable $e) {
            if (function_exists('error_log')) {
                @error_log('login_submit dashboard pref: ' . $e->getMessage());
            }
        }

        header('Location: ' . auragold_resolve_post_login_redirect_url((int) ($_SESSION['user_id'] ?? 0)));
        exit;
    } catch (Throwable $e) {
        if (function_exists('error_log')) {
            @error_log('login_submit: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
        $abortMsg = 'Sign-in could not be completed. Please try again.';
        if (defined('AURAGOLD_PROJECT') && (string) AURAGOLD_PROJECT === 'local') {
            $abortMsg .= ' (' . $e->getMessage() . ')';
        }
        if (function_exists('auragold_login_abort_to_index')) {
            auragold_login_abort_to_index($abortMsg);
        } else {
            header('Location: index.php?login_error=' . rawurlencode($abortMsg));
            exit;
        }
    }
}

$msg = isset($result['message']) ? $result['message'] : 'Login failed';
header('Location: index.php?login_error=' . rawurlencode($msg));
exit;
