<?php

require_once dirname(__DIR__) . '/includes/session_init.php';
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auragold_require_login.php';
require_once dirname(__DIR__) . '/includes/auragold_mail_settings_schema.php';
require_once dirname(__DIR__) . '/includes/auragold_mail_settings_defaults.php';
require_once dirname(__DIR__) . '/includes/auragold_smtp_mail_send.php';

header('Content-Type: application/json; charset=utf-8');

auragold_require_login_or_exit();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['ok' => false, 'message' => 'Invalid method']);
    exit;
}

if (!$conn instanceof mysqli) {
    echo json_encode(['ok' => false, 'message' => 'Database unavailable']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    echo json_encode(['ok' => false, 'message' => 'Invalid JSON']);
    exit;
}

$to = isset($data['to']) ? trim((string) $data['to']) : '';
if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'message' => 'Enter a valid test recipient email.']);
    exit;
}

auragold_ensure_mail_settings_table($conn);
$saved = auragold_get_mail_settings_row($conn);
$overrides = isset($data['mail']) && is_array($data['mail']) ? $data['mail'] : [];
$cfg = auragold_mail_settings_merge_for_send($saved, $overrides);
$ready = auragold_mail_settings_is_ready($cfg);
if (empty($ready['ok'])) {
    echo json_encode(['ok' => false, 'message' => $ready['message']]);
    exit;
}

$subject = 'GoldMatrix mail test — ' . date('d-m-Y H:i');
$body = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#1e293b;">'
    . '<p>Hello,</p>'
    . '<p>This message confirms that <strong>GoldMatrix</strong> can send mail through your SMTP server.</p>'
    . '<p>Sent at: ' . htmlspecialchars(date('d-m-Y H:i:s T'), ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p style="color:#64748b;font-size:12px;">If this landed in Spam, enable SPF/DKIM in cPanel → Email Deliverability.</p>'
    . '</div>';

$result = auragold_smtp_send_message($cfg, $to, $subject, $body, []);
if (!empty($result['ok'])) {
    $result['recipient'] = $to;
}
echo json_encode($result);
