<?php

require_once dirname(__DIR__) . '/includes/session_init.php';
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auragold_require_login.php';
require_once dirname(__DIR__) . '/includes/auragold_mail_settings_schema.php';

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

$host = isset($data['smtp_host']) ? trim((string) $data['smtp_host']) : '';
$port = isset($data['smtp_port']) ? (int) $data['smtp_port'] : 0;
$enc = isset($data['smtp_encryption']) ? strtolower(trim((string) $data['smtp_encryption'])) : 'ssl';
if ($host === '') {
    echo json_encode(['ok' => false, 'message' => 'SMTP host is required']);
    exit;
}
if ($port < 1 || $port > 65535) {
    echo json_encode(['ok' => false, 'message' => 'Invalid port']);
    exit;
}
if (!in_array($enc, ['ssl', 'tls', 'none'], true)) {
    $enc = 'ssl';
}

$use_tls_wrapper = ($enc === 'ssl') || ($enc === 'tls' && $port === 465);
$target = $use_tls_wrapper ? 'ssl://' . $host . ':' . $port : 'tcp://' . $host . ':' . $port;

$ctx = stream_context_create([
    'ssl' => [
        'verify_peer'       => false,
        'verify_peer_name'  => false,
        'allow_self_signed' => true,
    ],
]);

$errno = 0;
$errstr = '';
$fp = @stream_socket_client($target, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);

if (!$fp) {
    echo json_encode([
        'ok'      => false,
        'message' => $errstr !== '' ? $errstr : ('Connection failed (code ' . $errno . ').'),
    ]);
    exit;
}

fclose($fp);

$hint = '';
if ($enc === 'tls' && $port !== 465) {
    $hint = ' Note: this check only opens a TCP/TLS socket; SMTP on port 587 uses STARTTLS after connect, which is not fully validated here.';
}

echo json_encode([
    'ok'      => true,
    'message' => 'Connected to ' . $host . ':' . $port . ' (' . ($use_tls_wrapper ? 'SSL' : 'plain TCP') . ').' . $hint,
]);
