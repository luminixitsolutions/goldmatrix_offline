<?php

session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auragold_require_login.php';
require_once __DIR__ . '/includes/auragold_set_software_menu_lock.php';

if (empty($_SESSION['Admin'])) {
    header('Location: index.php');
    exit;
}

auragold_require_login_or_exit();

require_once __DIR__ . '/includes/auragold_set_software_page_guard.php';
auragold_set_software_page_guard();

$conn = isset($conn) && $conn instanceof mysqli ? $conn : null;
if ($conn === null) {
    die('Database connection is not available.');
}

auragold_ensure_set_software_menu_lock_table($conn);

$flash = ['type' => '', 'message' => ''];
if (isset($_SESSION['menu_settings_flash']) && is_array($_SESSION['menu_settings_flash'])) {
    $flash = array_merge(
        $flash,
        array_intersect_key(
            $_SESSION['menu_settings_flash'],
            array_flip(['type', 'message'])
        )
    );
    unset($_SESSION['menu_settings_flash']);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (($_POST['menu_settings_action'] ?? '') === 'save')) {
    $locked = [];
    if (isset($_POST['locked_menus']) && is_array($_POST['locked_menus'])) {
        foreach ($_POST['locked_menus'] as $k) {
            $locked[] = (string) $k;
        }
    }

    $newPassword = null;
    $pwd = isset($_POST['menu_password']) ? trim((string) $_POST['menu_password']) : '';
    $pwdConfirm = isset($_POST['menu_password_confirm']) ? trim((string) $_POST['menu_password_confirm']) : '';
    if ($pwd !== '') {
        if ($pwd !== $pwdConfirm) {
            $_SESSION['menu_settings_flash'] = [
                'type'    => 'danger',
                'message' => function_exists('auragold_t') ? (string) auragold_t('set_software.menu_settings_pwd_mismatch') : 'Passwords do not match.',
            ];
            header('Location: menu-settings.php');
            exit;
        }
        $newPassword = $pwd;
    }

    $hasLocked = count($locked) > 0;
    $hasPwd = auragold_set_software_menu_has_password($conn) || $newPassword !== null;
    if ($hasLocked && !$hasPwd) {
        $_SESSION['menu_settings_flash'] = [
            'type'    => 'danger',
            'message' => function_exists('auragold_t') ? (string) auragold_t('set_software.menu_settings_pwd_required') : 'Set a menu password before locking any menu.',
        ];
        header('Location: menu-settings.php');
        exit;
    }

    $ok = auragold_set_software_menu_lock_save($conn, $locked, $newPassword);
    $_SESSION['menu_settings_flash'] = [
        'type'    => $ok ? 'success' : 'danger',
        'message' => $ok
            ? (function_exists('auragold_t') ? (string) auragold_t('set_software.menu_settings_saved') : 'Menu settings saved.')
            : (function_exists('auragold_t') ? (string) auragold_t('set_software.menu_settings_save_error') : 'Could not save menu settings.'),
    ];
    header('Location: menu-settings.php');
    exit;
}

$hasMenuPassword = auragold_set_software_menu_has_password($conn);
$lockedKeys = auragold_set_software_menu_locked_keys($conn);
$lockedMap = [];
foreach ($lockedKeys as $k) {
    $lockedMap[$k] = true;
}

$menuRegistry = auragold_set_software_menu_registry();
$grouped = [];
foreach ($menuRegistry as $item) {
    $group = (string) ($item['group'] ?? '');
    if ($group === '') {
        $group = '_top';
    }
    if (!isset($grouped[$group])) {
        $grouped[$group] = [];
    }
    $grouped[$group][] = $item;
}

$t = static function (string $key, string $fallback = ''): string {
    if (function_exists('auragold_t')) {
        $s = (string) auragold_t($key);
        if ($s !== '' && $s !== $key) {
            return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        }
    }

    return htmlspecialchars($fallback !== '' ? $fallback : $key, ENT_QUOTES, 'UTF-8');
};

$page_title = function_exists('auragold_t')
    ? (string) auragold_t('set_software.menu_settings_page_title')
    : 'Menu Setting - Set Software - GoldMatrix';

?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
    <?php include 'header-script.php'; ?>
    <link rel="stylesheet" href="set-software-sidebar.css">
</head>
<style>
html, body { height: 100%; overflow-x: hidden; }
.layout-content { height: calc(100vh - 60px); overflow: hidden; display: flex; flex-direction: column; }
.set-software-wrapper { flex: 1; min-height: 0; }
.set-software-main { overflow-y: auto; }
.ss-menu-settings-page { padding: 24px; max-width: 820px; }
.ss-menu-settings-page h1 { font-size: 1.35rem; font-weight: 700; color: #0f172a; margin: 0 0 8px; }
.ss-menu-settings-page .lead { color: #64748b; font-size: 0.9rem; margin-bottom: 20px; line-height: 1.5; }
.ss-menu-group { border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 14px; background: #fff; overflow: hidden; }
.ss-menu-group-head { padding: 10px 16px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-weight: 700; color: #11294b; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.03em; }
.ss-menu-items { padding: 10px 16px 14px; }
.ss-menu-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 8px 0; border-bottom: 1px solid #f1f5f9; }
.ss-menu-row:last-child { border-bottom: none; }
.ss-menu-row label { margin: 0; display: flex; align-items: center; gap: 10px; cursor: pointer; flex: 1; color: #334155; font-size: 0.9rem; }
.ss-menu-row .lock-badge { font-size: 0.72rem; color: #64748b; background: #f1f5f9; padding: 2px 8px; border-radius: 999px; }
.ss-menu-row.is-not-lockable { opacity: 0.7; }
.ss-pwd-card { border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px 16px; background: #fff; margin-bottom: 20px; }
.ss-pwd-card h2 { font-size: 0.95rem; font-weight: 700; color: #0f172a; margin: 0 0 12px; }
.ss-pwd-card .hint { font-size: 0.78rem; color: #94a3b8; margin-top: 8px; }
.ss-pwd-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.ss-menu-settings-actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-top: 4px; }
.ss-menu-overlay { position: fixed; inset: 0; z-index: 10050; background: rgba(15, 23, 42, 0.45); display: none; align-items: center; justify-content: center; padding: 20px; }
.ss-menu-overlay.is-open { display: flex; }
.ss-menu-dialog { background: #fff; border-radius: 12px; box-shadow: 0 12px 32px rgba(15, 23, 42, 0.18); max-width: 420px; width: 100%; overflow: hidden; }
.ss-menu-dialog__head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 18px 10px; }
.ss-menu-dialog__title { font-size: 1.05rem; font-weight: 700; color: #0f172a; margin: 0; }
.ss-menu-dialog__close { background: none; border: 0; font-size: 1.4rem; line-height: 1; color: #64748b; cursor: pointer; padding: 0 4px; }
.ss-menu-dialog__body { padding: 0 18px 12px; }
.ss-menu-dialog__lead { color: #64748b; font-size: 0.88rem; margin: 0 0 12px; line-height: 1.5; }
.ss-menu-dialog__label { display: block; font-size: 0.82rem; font-weight: 600; color: #334155; margin-bottom: 6px; }
.ss-menu-dialog__input { width: 100%; }
.ss-menu-dialog__err { color: #dc2626; font-size: 0.85rem; margin-top: 8px; min-height: 1.2em; }
.ss-menu-dialog__foot { display: flex; justify-content: flex-end; gap: 10px; padding: 12px 18px 16px; }
@media (max-width: 640px) { .ss-pwd-row { grid-template-columns: 1fr; } }
</style>
<body>
<?php include 'sidebar.php'; ?>
<div class="layout-content">
    <div class="container-fluid flex-grow-1" style="padding-top: 0; padding-bottom: 0;">
        <div class="set-software-wrapper">
            <?php include 'set-software-sidebar.php'; ?>
            <div class="set-software-main">
                <?php include __DIR__ . '/includes/set-software-branches-tabs.php'; ?>
                <div class="ss-menu-settings-page">
                    <h1><?php echo $t('set_software.menu_setting', 'Menu Setting'); ?></h1>
                    <p class="lead"><?php echo $t('set_software.menu_settings_lead', 'Lock Set Software sidebar menus with a password. Locked menus require the password (or master password) before opening.'); ?></p>

                    <?php if ($flash['message'] !== ''): ?>
                        <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="menu-settings.php" id="menu-settings-form">
                        <input type="hidden" name="menu_settings_action" value="save">

                        <div class="ss-pwd-card">
                            <h2><?php echo $t('set_software.menu_settings_password_heading', 'Menu unlock password'); ?></h2>
                            <div class="ss-pwd-row">
                                <div class="form-group mb-0">
                                    <label for="menu_password"><?php echo $t('set_software.menu_lock_password', 'Password'); ?></label>
                                    <input type="password" class="form-control" name="menu_password" id="menu_password" autocomplete="new-password" placeholder="<?php echo auragold_set_software_menu_has_password($conn) ? 'Leave blank to keep current' : 'Set password'; ?>">
                                </div>
                                <div class="form-group mb-0">
                                    <label for="menu_password_confirm"><?php echo $t('set_software.menu_settings_confirm_password', 'Confirm password'); ?></label>
                                    <input type="password" class="form-control" name="menu_password_confirm" id="menu_password_confirm" autocomplete="new-password">
                                </div>
                            </div>
                            <p class="hint mb-0"><?php echo $t('set_software.menu_lock_master_hint', 'If you forgot your password, use the master password.'); ?></p>
                            <?php if ($hasMenuPassword): ?>
                                <div class="mt-3">
                                    <button type="button" class="btn btn-outline-danger" id="ssMenuRemovePwdOpen"><?php echo $t('set_software.menu_settings_remove_password', 'Remove password'); ?></button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <h2 style="font-size:1rem;font-weight:700;color:#0f172a;margin:0 0 12px;"><?php echo $t('set_software.menu_settings_lock_heading', 'Lock menus'); ?></h2>

                        <?php foreach ($grouped as $groupName => $items): ?>
                            <section class="ss-menu-group">
                                <?php if ($groupName !== '_top'): ?>
                                    <div class="ss-menu-group-head"><?php echo htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                                <div class="ss-menu-items">
                                    <?php foreach ($items as $item): ?>
                                        <?php
                                        $key = (string) ($item['key'] ?? '');
                                        $isLocked = !empty($lockedMap[$key]);
                                        ?>
                                        <div class="ss-menu-row">
                                            <label>
                                                <input type="checkbox" name="locked_menus[]" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $isLocked ? ' checked' : ''; ?>>
                                                <span><?php echo htmlspecialchars((string) ($item['label'] ?? $key), ENT_QUOTES, 'UTF-8'); ?></span>
                                            </label>
                                            <?php if ($isLocked): ?>
                                                <span class="lock-badge"><i class="feather icon-lock" style="width:12px;height:12px;"></i> <?php echo $t('set_software.menu_settings_locked', 'Locked'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>

                        <div class="ss-menu-settings-actions">
                            <button type="submit" class="btn btn-primary"><?php echo $t('set_software.menu_settings_save', 'Save settings'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php if ($hasMenuPassword): ?>
<div id="ssMenuRemovePwdOverlay" class="ss-menu-overlay" role="dialog" aria-modal="true" aria-labelledby="ssMenuRemovePwdTitle" hidden>
    <div class="ss-menu-dialog">
        <div class="ss-menu-dialog__head">
            <h2 id="ssMenuRemovePwdTitle" class="ss-menu-dialog__title"><?php echo $t('set_software.menu_settings_remove_pwd_title', 'Remove menu password'); ?></h2>
            <button type="button" class="ss-menu-dialog__close" id="ssMenuRemovePwdClose" aria-label="Close">&times;</button>
        </div>
        <div class="ss-menu-dialog__body">
            <p class="ss-menu-dialog__lead"><?php echo $t('set_software.menu_settings_remove_pwd_lead', 'Enter the menu password (or master password) to continue.'); ?></p>
            <label class="ss-menu-dialog__label" for="ssMenuRemovePwdInput"><?php echo $t('set_software.menu_lock_password', 'Password'); ?></label>
            <input type="password" id="ssMenuRemovePwdInput" class="form-control ss-menu-dialog__input" autocomplete="current-password">
            <div id="ssMenuRemovePwdErr" class="ss-menu-dialog__err" role="alert"></div>
        </div>
        <div class="ss-menu-dialog__foot">
            <button type="button" class="btn btn-outline-secondary" id="ssMenuRemovePwdCancel"><?php echo $t('set_software.menu_settings_remove_cancel', 'Cancel'); ?></button>
            <button type="button" class="btn btn-primary" id="ssMenuRemovePwdContinue"><?php echo $t('set_software.menu_settings_remove_continue', 'Continue'); ?></button>
        </div>
    </div>
</div>
<div id="ssMenuRemoveConfirmOverlay" class="ss-menu-overlay" role="dialog" aria-modal="true" aria-labelledby="ssMenuRemoveConfirmTitle" hidden>
    <div class="ss-menu-dialog">
        <div class="ss-menu-dialog__head">
            <h2 id="ssMenuRemoveConfirmTitle" class="ss-menu-dialog__title"><?php echo $t('set_software.menu_settings_remove_confirm_title', 'Are you sure?'); ?></h2>
            <button type="button" class="ss-menu-dialog__close" id="ssMenuRemoveConfirmClose" aria-label="Close">&times;</button>
        </div>
        <div class="ss-menu-dialog__body">
            <p class="ss-menu-dialog__lead mb-0"><?php echo $t('set_software.menu_settings_remove_confirm_lead', 'This will remove the menu password and unlock all locked menus.'); ?></p>
            <div id="ssMenuRemoveConfirmErr" class="ss-menu-dialog__err" role="alert"></div>
        </div>
        <div class="ss-menu-dialog__foot">
            <button type="button" class="btn btn-outline-secondary" id="ssMenuRemoveConfirmNo"><?php echo $t('set_software.menu_settings_remove_no', 'No'); ?></button>
            <button type="button" class="btn btn-danger" id="ssMenuRemoveConfirmYes"><?php echo $t('set_software.menu_settings_remove_yes', 'Yes'); ?></button>
        </div>
    </div>
</div>
<script>
(function () {
    var pwdOverlay = document.getElementById('ssMenuRemovePwdOverlay');
    var confirmOverlay = document.getElementById('ssMenuRemoveConfirmOverlay');
    var openBtn = document.getElementById('ssMenuRemovePwdOpen');
    var input = document.getElementById('ssMenuRemovePwdInput');
    var err = document.getElementById('ssMenuRemovePwdErr');
    var confirmErr = document.getElementById('ssMenuRemoveConfirmErr');
    var continueBtn = document.getElementById('ssMenuRemovePwdContinue');
    var yesBtn = document.getElementById('ssMenuRemoveConfirmYes');
    var pendingPassword = '';

    function showOverlay(el) {
        if (!el) return;
        el.hidden = false;
        el.classList.add('is-open');
    }
    function hideOverlay(el) {
        if (!el) return;
        el.classList.remove('is-open');
        el.hidden = true;
    }
    function setErr(el, msg) {
        if (!el) return;
        el.textContent = msg || '';
    }
    function postAction(action, password) {
        var body = 'action=' + encodeURIComponent(action) + '&password=' + encodeURIComponent(password);
        return fetch('ajax/remove-set-software-menu-password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body,
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); });
    }
    function openPwd() {
        pendingPassword = '';
        if (input) {
            input.value = '';
            input.disabled = false;
        }
        setErr(err, '');
        if (continueBtn) continueBtn.disabled = false;
        showOverlay(pwdOverlay);
        setTimeout(function () { if (input) input.focus(); }, 40);
    }
    function closeAll() {
        pendingPassword = '';
        hideOverlay(pwdOverlay);
        hideOverlay(confirmOverlay);
        if (input) input.value = '';
        setErr(err, '');
        setErr(confirmErr, '');
        if (continueBtn) continueBtn.disabled = false;
        if (yesBtn) yesBtn.disabled = false;
    }

    function verifyPassword() {
        var pw = String(input && input.value || '');
        if (!pw.trim()) {
            setErr(err, 'Enter password');
            return;
        }
        if (continueBtn) continueBtn.disabled = true;
        setErr(err, '');
        postAction('verify', pw)
            .then(function (d) {
                if (d && d.status === 'ok') {
                    pendingPassword = pw;
                    hideOverlay(pwdOverlay);
                    setErr(confirmErr, '');
                    if (yesBtn) yesBtn.disabled = false;
                    showOverlay(confirmOverlay);
                    return;
                }
                if (continueBtn) continueBtn.disabled = false;
                setErr(err, (d && d.message) ? d.message : 'Incorrect password');
            })
            .catch(function () {
                if (continueBtn) continueBtn.disabled = false;
                setErr(err, 'Network error');
            });
    }

    function confirmRemove() {
        if (!pendingPassword) {
            hideOverlay(confirmOverlay);
            openPwd();
            return;
        }
        if (yesBtn) yesBtn.disabled = true;
        setErr(confirmErr, '');
        postAction('remove', pendingPassword)
            .then(function (d) {
                if (d && d.status === 'ok') {
                    window.location.reload();
                    return;
                }
                if (yesBtn) yesBtn.disabled = false;
                setErr(confirmErr, (d && d.message) ? d.message : 'Could not remove the menu password.');
            })
            .catch(function () {
                if (yesBtn) yesBtn.disabled = false;
                setErr(confirmErr, 'Network error');
            });
    }

    if (openBtn) openBtn.addEventListener('click', openPwd);
    if (continueBtn) continueBtn.addEventListener('click', verifyPassword);
    if (yesBtn) yesBtn.addEventListener('click', confirmRemove);
    ['ssMenuRemovePwdClose', 'ssMenuRemovePwdCancel', 'ssMenuRemoveConfirmClose', 'ssMenuRemoveConfirmNo'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('click', closeAll);
    });
    if (input) {
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                verifyPassword();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                closeAll();
            }
        });
    }
    [pwdOverlay, confirmOverlay].forEach(function (overlay) {
        if (!overlay) return;
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeAll();
        });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && ((pwdOverlay && pwdOverlay.classList.contains('is-open')) || (confirmOverlay && confirmOverlay.classList.contains('is-open')))) {
            closeAll();
        }
    });
})();
</script>
<?php endif; ?>
</body>
</html>
