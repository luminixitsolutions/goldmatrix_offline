<?php

require_once dirname(__DIR__) . '/includes/session_init.php';
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auragold_require_login.php';
require_once dirname(__DIR__) . '/includes/auragold_mail_settings_schema.php';
require_once dirname(__DIR__) . '/includes/auragold_mail_settings_defaults.php';
require_once dirname(__DIR__) . '/includes/auragold_mail_deliverability.php';

header('Content-Type: application/json; charset=utf-8');

auragold_require_login_or_exit();

if (!$conn instanceof mysqli) {
    echo json_encode(['ok' => false, 'message' => 'Database unavailable']);
    exit;
}

auragold_ensure_mail_settings_table($conn);
$cfg = auragold_mail_settings_merge_for_send(auragold_get_mail_settings_row($conn));
$from = trim((string) ($cfg['from_email'] ?? $cfg['smtp_username'] ?? ''));
$host = trim((string) ($cfg['smtp_host'] ?? ''));

if ($from === '') {
    echo json_encode(['ok' => false, 'message' => 'Save SMTP username / From email first.']);
    exit;
}

$report = auragold_mail_deliverability_report($from, $host);

echo json_encode([
    'ok'       => true,
    'report'   => $report,
    'has_issues' => !empty($report['warnings']),
]);
