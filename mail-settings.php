<?php

session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auragold_require_login.php';
require_once __DIR__ . '/includes/auragold_mail_settings_schema.php';
require_once __DIR__ . '/includes/auragold_mail_settings_defaults.php';
require_once __DIR__ . '/includes/auragold_mail_deliverability.php';

auragold_require_login_or_exit();

require_once __DIR__ . '/includes/auragold_set_software_page_guard.php';
auragold_set_software_page_guard();

$conn = isset($conn) && $conn instanceof mysqli ? $conn : null;
if ($conn === null) {
    die('Database connection is not available.');
}

auragold_ensure_mail_settings_table($conn);

$flash = ['type' => '', 'message' => ''];
if (isset($_SESSION['mail_settings_flash']) && is_array($_SESSION['mail_settings_flash'])) {
    $flash = array_merge(
        $flash,
        array_intersect_key(
            $_SESSION['mail_settings_flash'],
            array_flip(['type', 'message'])
        )
    );
    unset($_SESSION['mail_settings_flash']);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (($_POST['mail_settings_action'] ?? '') === 'save')) {
    $m = isset($_POST['mail']) && is_array($_POST['mail']) ? $_POST['mail'] : [];
    $ok = auragold_save_mail_settings_from_post($conn, $m);
    $_SESSION['mail_settings_flash'] = [
        'type'    => $ok ? 'success' : 'danger',
        'message' => $ok
            ? (function_exists('auragold_t') ? (string) auragold_t('mail_settings.saved') : 'Mail settings saved.')
            : (function_exists('auragold_t') ? (string) auragold_t('mail_settings.save_error') : 'Could not save mail settings.'),
    ];
    header('Location: mail-settings.php');
    exit;
}

$row = auragold_get_mail_settings_row($conn);
$row = auragold_mail_settings_with_defaults($row);
$has_smtp_password = isset($row['smtp_password']) && trim((string) $row['smtp_password']) !== '';
$smtp_user = trim((string) ($row['smtp_username'] ?? ''));
$from_email_cfg = trim((string) ($row['from_email'] ?? ''));
$mail_from_mismatch = ($smtp_user !== '' && $from_email_cfg !== '' && strcasecmp($smtp_user, $from_email_cfg) !== 0);
$mail_cfg_for_send = auragold_mail_settings_merge_for_send($row);
$mail_ready = auragold_mail_settings_is_ready($mail_cfg_for_send);
$mail_deliverability = auragold_mail_deliverability_report(
    (string) ($mail_cfg_for_send['from_email'] ?? $mail_cfg_for_send['smtp_username'] ?? ''),
    (string) ($mail_cfg_for_send['smtp_host'] ?? '')
);

$t = static function (string $key, string $fallback = ''): string {
    if (function_exists('auragold_t')) {
        $s = (string) auragold_t($key);
        if ($s !== '' && $s !== $key) {
            return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        }
    }

    return htmlspecialchars($fallback !== '' ? $fallback : $key, ENT_QUOTES, 'UTF-8');
};

$page_title = function_exists('auragold_t') ? (string) auragold_t('mail_settings.page_title') : 'Mail Setting - Set Software - ' . auragold_app_name();

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
.auragold-mail-page { padding: 24px; max-width: 800px; }
.auragold-mail-page h1 { font-size: 1.35rem; font-weight: 700; color: #0f172a; margin: 0 0 8px; }
.auragold-mail-page .lead { color: #64748b; font-size: 0.9rem; margin-bottom: 20px; }
.mail-secure-box { border: 2px solid #1e5a8a; border-radius: 8px; overflow: hidden; margin-bottom: 20px; background: #fff; }
.mail-secure-box h2 { background: #1e5a8a; color: #fff; font-size: 0.95rem; font-weight: 600; margin: 0; padding: 10px 14px; }
.mail-secure-box .inner { padding: 14px 16px; font-size: 0.875rem; }
.mail-secure-box .note { font-size: 0.8rem; color: #475569; margin-top: 12px; padding-top: 10px; border-top: 1px solid #e2e8f0; }
.mail-field-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 20px; }
@media (max-width: 640px) { .mail-field-grid { grid-template-columns: 1fr; } }
.mail-field-grid .full { grid-column: 1 / -1; }
details.mail-nonssl { margin-bottom: 16px; }
details.mail-nonssl summary { cursor: pointer; color: #1e5a8a; font-weight: 600; font-size: 0.9rem; }
</style>
<body>
<?php include 'sidebar.php'; ?>
<div class="layout-content">
    <div class="container-fluid flex-grow-1" style="padding-top: 0; padding-bottom: 0;">
        <div class="set-software-wrapper">
            <?php include 'set-software-sidebar.php'; ?>
            <div class="set-software-main">
                <?php include __DIR__ . '/includes/set-software-branch-banner.php'; ?>
                <div class="auragold-mail-page">
                    <h1><?php echo $t('mail_settings.heading', 'Mail Setting'); ?></h1>
                    <p class="lead"><?php echo $t('mail_settings.lead', 'Configure SMTP for sending mail from the application, and incoming server details for staff setting up email clients (IMAP / POP3).'); ?></p>

                    <?php if ($flash['message'] !== ''): ?>
                        <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($mail_deliverability['warnings'])): ?>
                        <div class="alert alert-warning" role="alert">
                            <strong>Deliverability issue detected</strong>
                            <ul class="mb-0 pl-3 mt-2">
                                <?php foreach ($mail_deliverability['warnings'] as $w): ?>
                                    <li><?php echo htmlspecialchars($w, ENT_QUOTES, 'UTF-8'); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <p class="mb-0 mt-2 small">GoldMatrix SMTP is working; Gmail may still reject mail until your host fixes server-side DNS/reputation. Use cPanel → <strong>Track Delivery</strong> for the exact error.</p>
                        </div>
                    <?php endif; ?>

                    <?php if ($mail_ready['ok']): ?>
                        <div class="alert alert-success" role="alert">
                            SMTP is configured for <strong><?php echo htmlspecialchars((string) ($mail_cfg_for_send['smtp_username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                            via <?php echo htmlspecialchars((string) ($mail_cfg_for_send['smtp_host'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>.
                            Use <strong>Send test email</strong> below to verify delivery.
                        </div>
                    <?php endif; ?>

                    <?php if ($mail_from_mismatch): ?>
                        <div class="alert alert-warning" role="alert">
                            <strong>From email does not match SMTP username.</strong>
                            SMTP login is <code><?php echo htmlspecialchars($smtp_user, ENT_QUOTES, 'UTF-8'); ?></code>
                            but From email is <code><?php echo htmlspecialchars($from_email_cfg, ENT_QUOTES, 'UTF-8'); ?></code>.
                            Click <strong>Save</strong> below — From email will be corrected automatically to match SMTP username.
                        </div>
                    <?php endif; ?>

                    <form method="post" action="mail-settings.php" autocomplete="off" id="mail-settings-form">
                        <input type="hidden" name="mail_settings_action" value="save">

                        <div class="card shadow-sm mb-3">
                            <div class="card-body">
                                <h5 class="card-title mb-3"><?php echo $t('mail_settings.section_outgoing', 'Outgoing mail (SMTP)'); ?></h5>
                                <div class="mail-field-grid">
                                    <div class="form-group full">
                                        <label for="smtp_host"><?php echo $t('mail_settings.smtp_host', 'SMTP server'); ?></label>
                                        <input type="text" class="form-control" id="smtp_host" name="mail[smtp_host]" value="<?php echo htmlspecialchars((string) ($row['smtp_host'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="mail.example.com">
                                    </div>
                                    <div class="form-group">
                                        <label for="smtp_port"><?php echo $t('mail_settings.smtp_port', 'SMTP port'); ?></label>
                                        <input type="number" class="form-control" id="smtp_port" name="mail[smtp_port]" value="<?php echo (int) ($row['smtp_port'] ?? 465); ?>" min="1" max="65535">
                                    </div>
                                    <div class="form-group">
                                        <label for="smtp_encryption"><?php echo $t('mail_settings.smtp_encryption', 'Encryption'); ?></label>
                                        <select class="form-control" id="smtp_encryption" name="mail[smtp_encryption]">
                                            <?php
                                            $enc = (string) ($row['smtp_encryption'] ?? 'ssl');
                                            foreach (['ssl' => 'SSL', 'tls' => 'TLS', 'none' => 'None'] as $k => $lab) {
                                                $sel = ($enc === $k) ? ' selected' : '';
                                                echo '<option value="' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>' . htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="form-group full">
                                        <label for="smtp_username"><?php echo $t('mail_settings.smtp_username', 'Username (email)'); ?></label>
                                        <input type="text" class="form-control" id="smtp_username" name="mail[smtp_username]" value="<?php echo htmlspecialchars((string) ($row['smtp_username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                    <div class="form-group full">
                                        <label for="smtp_password"><?php echo $t('mail_settings.smtp_password', 'Password'); ?></label>
                                        <input type="password" class="form-control" id="smtp_password" name="mail[smtp_password]" value="" placeholder="<?php echo $has_smtp_password ? $t('mail_settings.password_keep', 'Leave blank to keep the saved password') : $t('mail_settings.password_enter', 'Account password'); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow-sm mb-3">
                            <div class="card-body">
                                <h5 class="card-title mb-3"><?php echo $t('mail_settings.section_sender', 'Sender identity'); ?></h5>
                                <div class="mail-field-grid">
                                    <div class="form-group">
                                        <label for="from_name"><?php echo $t('mail_settings.from_name', 'From name'); ?></label>
                                        <input type="text" class="form-control" id="from_name" name="mail[from_name]" value="<?php echo htmlspecialchars((string) ($row['from_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="from_email"><?php echo $t('mail_settings.from_email', 'From email'); ?></label>
                                        <input type="email" class="form-control" id="from_email" name="mail[from_email]" value="<?php echo htmlspecialchars((string) ($row['from_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                        <small class="form-text text-muted">Should match SMTP username for best delivery.</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow-sm mb-3">
                            <div class="card-body">
                                <h5 class="card-title mb-3"><?php echo $t('mail_settings.section_incoming', 'Incoming mail (for email clients)'); ?></h5>
                                <p class="text-muted small mb-3"><?php echo $t('mail_settings.incoming_hint', 'These values are stored for reference (e.g. Outlook / phone mail setup). They do not configure server-side mail by themselves.'); ?></p>
                                <div class="mail-field-grid">
                                    <div class="form-group full">
                                        <label for="incoming_host"><?php echo $t('mail_settings.incoming_host', 'Incoming server'); ?></label>
                                        <input type="text" class="form-control" id="incoming_host" name="mail[incoming_host]" value="<?php echo htmlspecialchars((string) ($row['incoming_host'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="mail.example.com">
                                    </div>
                                    <div class="form-group">
                                        <label for="imap_port"><?php echo $t('mail_settings.imap_port', 'IMAP port (SSL)'); ?></label>
                                        <input type="number" class="form-control" id="imap_port" name="mail[imap_port]" value="<?php echo (int) ($row['imap_port'] ?? 993); ?>" min="1" max="65535">
                                    </div>
                                    <div class="form-group">
                                        <label for="pop3_port"><?php echo $t('mail_settings.pop3_port', 'POP3 port (SSL)'); ?></label>
                                        <input type="number" class="form-control" id="pop3_port" name="mail[pop3_port]" value="<?php echo (int) ($row['pop3_port'] ?? 995); ?>" min="1" max="65535">
                                    </div>
                                </div>

                                <div class="mail-secure-box mt-3">
                                    <h2><?php echo $t('mail_settings.manual_ssl_title', 'Secure SSL/TLS settings (typical)'); ?></h2>
                                    <div class="inner">
                                        <p class="mb-1"><strong><?php echo $t('mail_settings.manual_username', 'Username'); ?>:</strong> <?php echo htmlspecialchars((string) ($row['smtp_username'] ?? ''), ENT_QUOTES, 'UTF-8') !== '' ? htmlspecialchars((string) $row['smtp_username'], ENT_QUOTES, 'UTF-8') : '—'; ?></p>
                                        <p class="mb-1"><strong><?php echo $t('mail_settings.manual_password_note', 'Password'); ?>:</strong> <?php echo $t('mail_settings.manual_password_text', 'Use the email account\'s password.'); ?></p>
                                        <p class="mb-1"><strong><?php echo $t('mail_settings.manual_incoming', 'Incoming server'); ?>:</strong>
                                            <?php
                                            $ih = trim((string) ($row['incoming_host'] ?? ''));
                                            echo $ih !== '' ? htmlspecialchars($ih, ENT_QUOTES, 'UTF-8') : '—';
                                            ?>
                                            — IMAP <?php echo (int) ($row['imap_port'] ?? 993); ?>, POP3 <?php echo (int) ($row['pop3_port'] ?? 995); ?>
                                        </p>
                                        <p class="mb-0"><strong><?php echo $t('mail_settings.manual_outgoing', 'Outgoing server'); ?>:</strong>
                                            <?php
                                            $sh = trim((string) ($row['smtp_host'] ?? ''));
                                            echo $sh !== '' ? htmlspecialchars($sh, ENT_QUOTES, 'UTF-8') : '—';
                                            ?>
                                            — SMTP <?php echo (int) ($row['smtp_port'] ?? 465); ?>
                                        </p>
                                        <p class="note mb-0"><?php echo $t('mail_settings.manual_auth_note', 'IMAP, POP3, and SMTP usually require authentication with the same username and password.'); ?></p>
                                    </div>
                                </div>

                                <details class="mail-nonssl mt-3">
                                    <summary><?php echo $t('mail_settings.show_non_ssl', 'Show non-SSL/TLS ports (reference)'); ?></summary>
                                    <p class="text-muted small mt-2 mb-0"><?php echo $t('mail_settings.non_ssl_text', 'Common non-secure ports: IMAP 143, POP3 110, SMTP 25 or 587 (STARTTLS). Your host may differ; prefer SSL/TLS when available.'); ?></p>
                                </details>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary"><?php echo $t('mail_settings.save', 'Save'); ?></button>
                        <button type="button" class="btn btn-outline-secondary ml-2" id="btn-test-smtp"><?php echo $t('mail_settings.test_connection', 'Test SMTP connection'); ?></button>
                        <button type="button" class="btn btn-outline-secondary ml-2" id="btn-check-deliverability">Check DNS / deliverability</button>
                        <div class="d-flex flex-wrap align-items-center mt-3" style="gap:8px;">
                            <input type="email" class="form-control" id="test_mail_to" placeholder="Test recipient email" style="max-width:280px;" value="rajatdh07@gmail.com">
                            <button type="button" class="btn btn-outline-primary" id="btn-send-test-mail">Send test email</button>
                            <button type="button" class="btn btn-outline-secondary" id="btn-send-test-self" title="Send to noreply@goldmatrixsoft.com and check Roundcube/webmail">Send to noreply inbox</button>
                        </div>
                        <p class="text-muted small mt-2 mb-0" id="mail-test-result" style="display:none;"></p>
                        <div id="mail-deliverability-panel" class="card shadow-sm mt-3" style="display:none;">
                            <div class="card-body">
                                <h6 class="card-title mb-2">Deliverability check</h6>
                                <pre id="mail-deliverability-body" class="small mb-0" style="white-space:pre-wrap;background:#f8fafc;border:1px solid #e2e8f0;padding:12px;border-radius:6px;max-height:320px;overflow:auto;"></pre>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'footer-script.php'; ?>
<script>
(function () {
    var btn = document.getElementById('btn-test-smtp');
    var out = document.getElementById('mail-test-result');
    var sendBtn = document.getElementById('btn-send-test-mail');
    var sendSelfBtn = document.getElementById('btn-send-test-self');
    var toInput = document.getElementById('test_mail_to');
    var delivBtn = document.getElementById('btn-check-deliverability');
    var delivPanel = document.getElementById('mail-deliverability-panel');
    var delivBody = document.getElementById('mail-deliverability-body');
    if (!btn || !out) return;
    btn.addEventListener('click', function () {
        var host = (document.getElementById('smtp_host') || {}).value || '';
        var port = parseInt((document.getElementById('smtp_port') || {}).value || '0', 10);
        var enc = (document.getElementById('smtp_encryption') || {}).value || 'ssl';
        out.style.display = 'block';
        out.className = 'text-muted small mt-2 mb-0';
        out.textContent = <?php echo json_encode(function_exists('auragold_t') ? (string) auragold_t('mail_settings.testing') : 'Testing connection…', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
        btn.disabled = true;
        fetch('ajax/test-mail-smtp.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ smtp_host: host, smtp_port: port, smtp_encryption: enc })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                btn.disabled = false;
                if (data && data.ok) {
                    out.className = 'small mt-2 mb-0 text-success';
                    out.textContent = data.message || 'OK';
                } else {
                    out.className = 'small mt-2 mb-0 text-danger';
                    out.textContent = (data && data.message) ? data.message : <?php echo json_encode(function_exists('auragold_t') ? (string) auragold_t('mail_settings.test_failed') : 'Test failed.', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
                }
            })
            .catch(function () {
                btn.disabled = false;
                out.className = 'small mt-2 mb-0 text-danger';
                out.textContent = <?php echo json_encode(function_exists('auragold_t') ? (string) auragold_t('mail_settings.test_network_error') : 'Network error.', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
            });
    });
    function mailPayloadFromForm() {
        return {
            smtp_host: (document.getElementById('smtp_host') || {}).value || '',
            smtp_port: parseInt((document.getElementById('smtp_port') || {}).value || '465', 10),
            smtp_encryption: (document.getElementById('smtp_encryption') || {}).value || 'ssl',
            smtp_username: (document.getElementById('smtp_username') || {}).value || '',
            smtp_password: (document.getElementById('smtp_password') || {}).value || '',
            from_name: (document.getElementById('from_name') || {}).value || '',
            from_email: (document.getElementById('from_email') || {}).value || ''
        };
    }
    function sendTestMail(to, triggerBtn) {
        to = (to || '').trim();
        if (!to) {
            alert('Enter a test recipient email address.');
            if (toInput) toInput.focus();
            return;
        }
        out.style.display = 'block';
        out.className = 'text-muted small mt-2 mb-0';
        out.textContent = 'Sending test email…';
        if (triggerBtn) triggerBtn.disabled = true;
        if (sendBtn) sendBtn.disabled = true;
        if (sendSelfBtn) sendSelfBtn.disabled = true;
        fetch('ajax/send-test-mail.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ to: to, mail: mailPayloadFromForm() })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (sendBtn) sendBtn.disabled = false;
                if (sendSelfBtn) sendSelfBtn.disabled = false;
                if (triggerBtn) triggerBtn.disabled = false;
                if (data && data.ok) {
                    out.className = 'small mt-2 mb-0 text-success';
                    out.style.whiteSpace = 'pre-wrap';
                    out.textContent = data.message || 'Test email sent.';
                } else {
                    out.className = 'small mt-2 mb-0 text-danger';
                    out.style.whiteSpace = 'pre-wrap';
                    out.textContent = (data && data.message) ? data.message : 'Send failed.';
                }
            })
            .catch(function () {
                if (sendBtn) sendBtn.disabled = false;
                if (sendSelfBtn) sendSelfBtn.disabled = false;
                if (triggerBtn) triggerBtn.disabled = false;
                out.className = 'small mt-2 mb-0 text-danger';
                out.textContent = 'Network error.';
            });
    }
    if (sendBtn && toInput) {
        sendBtn.addEventListener('click', function () {
            sendTestMail(toInput.value, sendBtn);
        });
    }
    if (sendSelfBtn) {
        sendSelfBtn.addEventListener('click', function () {
            var selfMail = (document.getElementById('smtp_username') || {}).value || 'noreply@goldmatrixsoft.com';
            sendTestMail(selfMail, sendSelfBtn);
        });
    }
    if (delivBtn && delivPanel && delivBody) {
        delivBtn.addEventListener('click', function () {
            delivPanel.style.display = 'block';
            delivBody.textContent = 'Checking SPF, DKIM, DMARC, MX…';
            delivBtn.disabled = true;
            fetch('ajax/check-mail-deliverability.php', {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    delivBtn.disabled = false;
                    if (!data || !data.ok || !data.report) {
                        delivBody.textContent = (data && data.message) ? data.message : 'Check failed.';
                        return;
                    }
                    var rep = data.report;
                    var lines = [];
                    lines.push('Domain: ' + (rep.domain || '—'));
                    if (rep.smtp_host) lines.push('SMTP host: ' + rep.smtp_host);
                    if (rep.smtp_ip) lines.push('SMTP IP: ' + rep.smtp_ip);
                    if (rep.ptr && rep.ptr.length) lines.push('PTR: ' + rep.ptr.join(', '));
                    else if (rep.smtp_ip) lines.push('PTR: (none — Gmail may block mail)');
                    if (rep.mx && rep.mx.length) lines.push('MX: ' + rep.mx.join(', '));
                    if (rep.spf && rep.spf.length) lines.push('SPF: ' + rep.spf.join(' | '));
                    if (rep.dkim && rep.dkim.length) lines.push('DKIM: ' + rep.dkim.join('\n       '));
                    if (rep.dmarc && rep.dmarc.length) lines.push('DMARC: ' + rep.dmarc.join(' | '));
                    if (rep.warnings && rep.warnings.length) {
                        lines.push('');
                        lines.push('Issues to fix in cPanel:');
                        rep.warnings.forEach(function (w) { lines.push('• ' + w); });
                    }
                    if (rep.tips && rep.tips.length) {
                        lines.push('');
                        lines.push('Tips:');
                        rep.tips.forEach(function (t) { lines.push('• ' + t); });
                    }
                    delivBody.textContent = lines.join('\n');
                })
                .catch(function () {
                    delivBtn.disabled = false;
                    delivBody.textContent = 'Network error.';
                });
        });
    }
})();
</script>
</body>
</html>
