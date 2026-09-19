<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/login_authenticate.php';

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    header('Location: index.php');
    exit;
}

/**
 * Username column is `username` (branches) or `Username` (users); keys match case-insensitively.
 */
function auragold_change_account_row_username(array $row): string {
    foreach ($row as $k => $v) {
        if (strcasecmp((string) $k, 'username') === 0) {
            return trim((string) ($v ?? ''));
        }
    }
    return '';
}

/**
 * True if another account already uses this login name (branch login checks branches first, then users).
 */
function auragold_username_taken_elsewhere(string $usernamePlain, int $excludeId, string $src): bool {
    $u = trim($usernamePlain);
    if ($u === '') {
        return true;
    }
    $e = esc($u);
    if ($src === 'branch') {
        $b = getRecordMaster(
            "SELECT id FROM tbl_branches WHERE username IS NOT NULL AND TRIM(username) <> '' AND LOWER(TRIM(username)) = LOWER(TRIM('$e')) AND id != $excludeId LIMIT 1"
        );
        if ($b) {
            return true;
        }
        $usr = getRecord(
            "SELECT id FROM tbl_users WHERE Username IS NOT NULL AND TRIM(Username) <> '' AND LOWER(TRIM(Username)) = LOWER(TRIM('$e')) LIMIT 1"
        );
        return (bool) $usr;
    }
    $usr = getRecord(
        "SELECT id FROM tbl_users WHERE Username IS NOT NULL AND TRIM(Username) <> '' AND LOWER(TRIM(Username)) = LOWER(TRIM('$e')) AND id != $excludeId LIMIT 1"
    );
    if ($usr) {
        return true;
    }
    $b = getRecordMaster(
        "SELECT id FROM tbl_branches WHERE username IS NOT NULL AND TRIM(username) <> '' AND LOWER(TRIM(username)) = LOWER(TRIM('$e')) LIMIT 1"
    );
    return (bool) $b;
}

$uid = (int) $_SESSION['user_id'];
$src = isset($_SESSION['login_source']) ? (string) $_SESSION['login_source'] : '';

$display_username = '';
if (!empty($_SESSION['Admin']) && is_array($_SESSION['Admin'])) {
    $display_username = auragold_change_account_row_username($_SESSION['Admin']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = trim((string) ($_POST['current_password'] ?? ''));
    $newUser = trim((string) ($_POST['new_username'] ?? ''));
    $new     = trim((string) ($_POST['new_password'] ?? ''));
    $confirm = trim((string) ($_POST['confirm_password'] ?? ''));

    $fail = function (string $msg) {
        $_SESSION['auragold_toast'] = ['type' => 'danger', 'message' => $msg];
        header('Location: change-password.php');
        exit;
    };

    if ($current === '') {
        $fail('Current password is required.');
    }

    if ($src !== 'branch' && $src !== 'user') {
        $fail('Could not update this account. Please log in again.');
    }

    $row = null;
    if ($src === 'branch') {
        $row = getRecordMaster('SELECT * FROM tbl_branches WHERE id = ' . $uid . ' LIMIT 1');
    } else {
        $row = getRecord('SELECT * FROM tbl_users WHERE id = ' . $uid . ' LIMIT 1');
    }

    if (!$row) {
        $fail('Account record was not found.');
    }

    $stored = auragold_row_password_field($row);
    if ($stored === '' && $src === 'user' && isset($row['Password']) && $row['Password'] !== null && $row['Password'] !== '') {
        $stored = trim((string) $row['Password']);
    }

    if ($stored === '' || !hash_equals($stored, $current)) {
        $fail('Current password is incorrect.');
    }

    $currentUsername = auragold_change_account_row_username($row);
    $wantUserChange  = ($newUser !== '' && strcasecmp($newUser, $currentUsername) !== 0);
    $wantPassChange  = ($new !== '' || $confirm !== '');

    if (!$wantUserChange && !$wantPassChange) {
        $fail('Enter a new username and/or new password to update.');
    }

    if ($wantPassChange) {
        if ($new === '' || $confirm === '') {
            $fail('Please fill in new password and confirmation.');
        }
        if ($new !== $confirm) {
            $fail('New password and confirmation do not match.');
        }
        if (strlen($new) < 4) {
            $fail('New password must be at least 4 characters.');
        }
        if (hash_equals($new, $current)) {
            $fail('New password must be different from your current password.');
        }
    }

    if ($wantUserChange) {
        if (strlen($newUser) < 2) {
            $fail('Username must be at least 2 characters.');
        }
        if (strlen($newUser) > 100) {
            $fail('Username is too long.');
        }
        if (auragold_username_taken_elsewhere($newUser, $uid, $src)) {
            $fail('That username is already in use. Choose another.');
        }
    }

    global $conn_master, $conn;
    $parts = [];
    if ($wantUserChange) {
        $userEsc = esc($newUser);
        if ($src === 'branch') {
            $parts[] = "username = '$userEsc'";
        } else {
            $parts[] = "`Username` = '$userEsc'";
        }
    }
    if ($wantPassChange) {
        $newEsc = esc($new);
        if ($src === 'branch') {
            $parts[] = "password = '$newEsc'";
        } else {
            $parts[] = "`Password` = '$newEsc'";
        }
    }

    $sql = '';
    if ($src === 'branch') {
        $sql = 'UPDATE tbl_branches SET ' . implode(', ', $parts) . ' WHERE id = ' . $uid . ' LIMIT 1';
    } else {
        $sql = 'UPDATE tbl_users SET ' . implode(', ', $parts) . ', ModifiedBy = ' . $uid . ' WHERE id = ' . $uid . ' LIMIT 1';
    }

    $writeLink = ($src === 'user' && isset($conn) && $conn instanceof mysqli) ? $conn : $conn_master;
    $ok        = mysqli_query($writeLink, $sql);
    if (!$ok) {
        $fail('Could not save changes. Please try again.');
    }

    if ($wantUserChange && !empty($_SESSION['Admin']) && is_array($_SESSION['Admin'])) {
        $_SESSION['Admin']['Username'] = $newUser;
        $_SESSION['Admin']['username'] = $newUser;
    }

    if ($wantUserChange && $wantPassChange) {
        $successMsg = 'Your username and password have been updated.';
    } elseif ($wantUserChange) {
        $successMsg = 'Your username has been updated.';
    } else {
        $successMsg = 'Your password has been updated.';
    }
    $_SESSION['auragold_toast'] = [
        'type'    => 'success',
        'message' => $successMsg,
    ];
    header('Location: change-password.php');
    exit;
}

$DASHBOARD_PAGE_TITLE = 'Change Password';
$DASHBOARD_EXTRA_CSS   = <<<'CSS'
<style>
    .cp-card {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 8px 22px rgba(0,0,0,.07);
        border: 1px solid #ece9ff;
        padding: 24px 26px;
        max-width: 480px;
    }
    .cp-card h1 {
        font-weight: 650;
        color: #1d2c4f;
        font-size: 1.2rem;
        margin-bottom: 6px;
    }
    .cp-card .cp-sub {
        color: #64748b;
        font-size: 13px;
        margin-bottom: 22px;
    }
    .cp-card label {
        font-weight: 600;
        color: #334155;
        font-size: 13px;
        margin-bottom: 6px;
    }
    .cp-card .form-control {
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }
    .cp-card .btn-save {
        background: #11294b;
        border: none;
        color: #fff;
        font-weight: 600;
        padding: 10px 22px;
        border-radius: 8px;
    }
    .cp-card .btn-save:hover {
        background: #0d213d;
        color: #fff;
    }
</style>
CSS;

require __DIR__ . '/includes/dashboard_shell_top.php';
?>

<div class="cp-card">
    <h1>Change username &amp; password</h1>
    <p class="cp-sub">Your current password is required. Update your login name, password, or both.</p>
    <form method="post" action="change-password.php" autocomplete="off">
        <div class="form-group mb-3">
            <label>Current username</label>
            <input type="text" class="form-control" value="<?php echo htmlspecialchars($display_username); ?>" readonly
                   style="background:#f8fafc;">
        </div>
        <div class="form-group mb-3">
            <label for="new_username">New username <span class="text-muted font-weight-normal">(optional)</span></label>
            <input type="text" class="form-control" id="new_username" name="new_username" maxlength="100"
                   placeholder="Leave blank to keep current username" autocomplete="username">
        </div>
        <div class="form-group mb-3">
            <label for="current_password">Current password</label>
            <input type="password" class="form-control" id="current_password" name="current_password" required
                   autocomplete="current-password">
        </div>
        <div class="form-group mb-3">
            <label for="new_password">New password <span class="text-muted font-weight-normal">(optional)</span></label>
            <input type="password" class="form-control" id="new_password" name="new_password" minlength="4"
                   placeholder="Leave blank to keep current password" autocomplete="new-password">
        </div>
        <div class="form-group mb-4">
            <label for="confirm_password">Confirm new password</label>
            <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="4"
                   autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-save">Save changes</button>
        <a href="dashboard.php" class="btn btn-light ml-2" style="border-radius:8px;">Cancel</a>
    </form>
</div>

<?php
require __DIR__ . '/includes/dashboard_shell_bottom.php';
