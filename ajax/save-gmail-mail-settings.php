<?php

require_once dirname(__DIR__) . '/includes/session_init.php';
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auragold_require_login.php';
require_once dirname(__DIR__) . '/includes/auragold_mail_settings_schema.php';
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

$gmail = isset($data['gmail']) ? trim((string) $data['gmail']) : '';
$appPassword = isset($data['app_password']) ? (string) $data['app_password'] : '';
$fromName = isset($data['from_name']) ? trim((string) $data['from_name']) : 'Gold Matrix';
$testTo = isset($data['test_to']) ? trim((string) $data['test_to']) : $gmail;

if (!filter_var($gmail, FILTER_VALIDATE_EMAIL) || stripos($gmail, '@gmail.com') === false) {
    echo json_encode(['ok' => false, 'message' => 'Enter a valid @gmail.com address.']);
    exit;
}

$appPasswordClean = preg_replace('/\s+/', '', trim($appPassword));
if (strlen($appPasswordClean) < 16) {
    echo json_encode([
        'ok' => false,
        'message' => 'Gmail App Password must be 16 characters. In Google Account → Security → App Passwords, create one for Mail and paste it here (spaces are OK).',
    ]);
    exit;
}

if (!auragold_save_gmail_smtp_settings($conn, $gmail, $appPasswordClean, $fromName)) {
    echo json_encode(['ok' => false, 'message' => 'Could not save Gmail settings.']);
    exit;
}

$cfg = auragold_get_mail_settings_row($conn);
$testRecipient = filter_var($testTo, FILTER_VALIDATE_EMAIL) ? $testTo : $gmail;
$subject = 'GoldMatrix Gmail test — ' . date('d-m-Y H:i:s');
$body = '<p>Gmail SMTP is configured correctly in GoldMatrix.</p><p>Time: ' . htmlspecialchars(date('c'), ENT_QUOTES, 'UTF-8') . '</p>';

$result = auragold_smtp_send_message($cfg, $testRecipient, $subject, $body, []);
$result['saved'] = true;
$result['recipient'] = $testRecipient;

if (!empty($result['ok'])) {
    $result['message'] = 'Gmail SMTP saved and test email sent to ' . $testRecipient . ".\n\nCheck your Gmail Inbox now (should arrive within 1 minute).";
} else {
    $result['message'] = 'Gmail settings saved but test send failed: ' . ($result['message'] ?? 'Unknown error')
        . "\n\nCheck the App Password and that 2-Step Verification is ON in your Google Account.";
}

echo json_encode($result);
