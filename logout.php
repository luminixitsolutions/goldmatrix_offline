<?php
require_once __DIR__ . '/includes/session_init.php';

$timeout = isset($_GET['timeout']) && (string) $_GET['timeout'] !== '' && (string) $_GET['timeout'] !== '0';
$bid     = (int) ($_SESSION['working_branch_id'] ?? $_SESSION['branch_id'] ?? 0);

try {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/includes/branch_working_context.php';

    if (function_exists('auragold_resolve_logout_branch_entry_id')) {
        $resolved = auragold_resolve_logout_branch_entry_id();
        if ($resolved > 0) {
            $bid = $resolved;
        }
    }

    if (isset($conn) && $conn instanceof mysqli && function_exists('auragold_is_logged_in_session') && auragold_is_logged_in_session()) {
        require_once __DIR__ . '/includes/activity_logger.php';
        if (function_exists('auragold_activity_log_logout')) {
            try {
                auragold_activity_log_logout($conn, $timeout ? 'timeout' : 'logout');
            } catch (Throwable $e) {
                if (function_exists('error_log')) {
                    @error_log('logout activity: ' . $e->getMessage());
                }
            }
        }
    }
} catch (Throwable $e) {
    if (function_exists('error_log')) {
        @error_log('logout.php: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    }
}

if ($timeout) {
    $msg = 'Session closed due to inactivity. Please sign in again.';
    if ($bid > 0) {
        $loc = 'index.php?branch_entry=' . $bid . '&login_error=' . rawurlencode($msg);
    } else {
        $loc = 'index.php?login_error=' . rawurlencode($msg);
    }
} else {
    $loc = $bid > 0 ? 'index.php?branch_entry=' . $bid : 'index.php';
}

$_SESSION = [];
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

header('Location: ' . $loc);
exit;
