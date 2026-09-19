<?php
/**
 * Password gate for locked Set Software menu pages.
 * Expects: $menuKey, $menuLabel, $returnUrl
 */
$gateTitle = function_exists('auragold_t')
    ? (string) auragold_t('set_software.menu_lock_gate_title')
    : 'Menu locked';
$gateLead = function_exists('auragold_t')
    ? (string) auragold_t('set_software.menu_lock_gate_lead')
    : 'This menu is locked. Enter your menu password to continue.';
$gateMasterHint = function_exists('auragold_t')
    ? (string) auragold_t('set_software.menu_lock_master_hint')
    : 'If you forgot your password, use the master password.';
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title><?php echo htmlspecialchars($gateTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
    <?php include dirname(__DIR__) . '/header-script.php'; ?>
    <style>
        body { background: #f1f5f9; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .ss-menu-lock-gate { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 8px 24px rgba(15,23,42,0.08); max-width: 420px; width: 100%; padding: 28px; }
        .ss-menu-lock-gate h1 { font-size: 1.25rem; font-weight: 700; color: #0f172a; margin: 0 0 8px; display: flex; align-items: center; gap: 10px; }
        .ss-menu-lock-gate .lead { color: #64748b; font-size: 0.9rem; margin: 0 0 6px; line-height: 1.5; }
        .ss-menu-lock-gate .menu-name { font-weight: 700; color: #11294b; margin-bottom: 16px; }
        .ss-menu-lock-gate .hint { font-size: 0.78rem; color: #94a3b8; margin-top: 10px; }
        .ss-menu-lock-gate .err { color: #dc2626; font-size: 0.85rem; margin-top: 8px; min-height: 1.2em; }
        .ss-menu-lock-gate .btns { display: flex; gap: 10px; margin-top: 18px; }
        .ss-menu-lock-gate .btns .btn-primary { background: linear-gradient(135deg, #11294b, #0d1f38); border: none; }
    </style>
</head>
<body>
<div class="ss-menu-lock-gate">
    <h1><i class="feather icon-lock"></i> <?php echo htmlspecialchars($gateTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
    <p class="lead"><?php echo htmlspecialchars($gateLead, ENT_QUOTES, 'UTF-8'); ?></p>
    <div class="menu-name"><?php echo htmlspecialchars($menuLabel ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
    <div class="form-group mb-0">
        <label for="ssMenuLockPwd"><?php echo function_exists('auragold_t') ? htmlspecialchars((string) auragold_t('set_software.menu_lock_password'), ENT_QUOTES, 'UTF-8') : 'Password'; ?></label>
        <input type="password" class="form-control" id="ssMenuLockPwd" autocomplete="current-password">
    </div>
    <div class="err" id="ssMenuLockErr" role="alert"></div>
    <p class="hint"><?php echo htmlspecialchars($gateMasterHint, ENT_QUOTES, 'UTF-8'); ?></p>
    <div class="btns">
        <button type="button" class="btn btn-primary" id="ssMenuLockSubmit"><?php echo function_exists('auragold_t') ? htmlspecialchars((string) auragold_t('set_software.menu_lock_unlock'), ENT_QUOTES, 'UTF-8') : 'Unlock'; ?></button>
        <a href="menu-settings.php" class="btn btn-outline-secondary"><?php echo function_exists('auragold_t') ? htmlspecialchars((string) auragold_t('set_software.menu_setting'), ENT_QUOTES, 'UTF-8') : 'Menu Setting'; ?></a>
    </div>
</div>
<script>
(function () {
    var menuKey = <?php echo json_encode($menuKey ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var returnUrl = <?php echo json_encode($returnUrl ?? 'index.php', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var input = document.getElementById('ssMenuLockPwd');
    var err = document.getElementById('ssMenuLockErr');
    var btn = document.getElementById('ssMenuLockSubmit');

    function submit() {
        var pw = String(input && input.value || '');
        if (!pw.trim()) {
            if (err) err.textContent = 'Enter password';
            return;
        }
        if (btn) btn.disabled = true;
        if (err) err.textContent = '';
        var body = 'menu_key=' + encodeURIComponent(menuKey) + '&password=' + encodeURIComponent(pw) + '&return=' + encodeURIComponent(returnUrl);
        fetch('ajax/verify-set-software-menu-password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body,
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d && d.status === 'ok' && d.redirect) {
                    window.location.href = d.redirect;
                    return;
                }
                if (btn) btn.disabled = false;
                if (err) err.textContent = (d && d.message) ? d.message : 'Incorrect password';
            })
            .catch(function () {
                if (btn) btn.disabled = false;
                if (err) err.textContent = 'Network error';
            });
    }

    if (btn) btn.addEventListener('click', submit);
    if (input) {
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                submit();
            }
        });
        input.focus();
    }
})();
</script>
</body>
</html>
