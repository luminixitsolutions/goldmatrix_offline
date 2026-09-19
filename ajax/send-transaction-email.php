<?php

require_once dirname(__DIR__) . '/includes/session_init.php';
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auragold_require_login.php';
require_once dirname(__DIR__) . '/includes/auragold_mail_settings_schema.php';
require_once dirname(__DIR__) . '/includes/auragold_mail_settings_defaults.php';
require_once dirname(__DIR__) . '/includes/auragold_smtp_mail_send.php';
require_once dirname(__DIR__) . '/includes/auragold_transaction_report_party_email.php';
require_once dirname(__DIR__) . '/includes/auragold_transaction_report_email.php';

header('Content-Type: application/json; charset=utf-8');

auragold_require_login_or_exit();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['ok' => false, 'message' => 'Invalid method']);
    exit;
}

if (!$conn instanceof mysqli) {
    echo json_encode(['ok' => false, 'message' => 'Database connection failed.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    echo json_encode(['ok' => false, 'message' => 'Invalid JSON']);
    exit;
}

$type = isset($data['type']) ? strtolower(trim((string) $data['type'])) : '';
$id = isset($data['id']) ? (int) $data['id'] : 0;
$customSubject = isset($data['subject']) ? trim((string) $data['subject']) : '';
$customBody = isset($data['body']) ? (string) $data['body'] : '';
$customTo = isset($data['to']) ? trim((string) $data['to']) : '';

$allowed = array_keys(auragold_tr_email_type_labels());
if ($id <= 0 || !in_array($type, $allowed, true)) {
    echo json_encode(['ok' => false, 'message' => 'Invalid transaction.']);
    exit;
}

$to = $customTo !== '' ? $customTo : auragold_transaction_report_party_email($conn, $type, $id);
if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'message' => 'Enter a valid recipient email address.']);
    exit;
}

auragold_ensure_mail_settings_table($conn);
$cfg = auragold_mail_settings_merge_for_send(auragold_get_mail_settings_row($conn));
$ready = auragold_mail_settings_is_ready($cfg);
if (empty($ready['ok'])) {
    echo json_encode(['ok' => false, 'message' => $ready['message']]);
    exit;
}

$rendered = auragold_tr_email_render_message($conn, $type, $id);
$subject = $customSubject !== '' ? $customSubject : $rendered['subject'];
$htmlBody = $customBody !== '' ? $customBody : $rendered['body'];

if (strlen($subject) > 500) {
    $subject = substr($subject, 0, 500);
}
if (strlen($htmlBody) > 65535) {
    $htmlBody = substr($htmlBody, 0, 65535);
}

$attachments = [];

if ($type === 'sale_invoice') {
    $meta = auragold_tr_email_fetch_meta($conn, $type, $id);
    $invNo = is_array($meta) ? trim((string) ($meta['doc_no'] ?? '')) : '';

    $GLOBALS['AURAGOLD_SALE_INVOICE_MAIL_CAPTURE'] = true;
    $GLOBALS['auragold_sale_invoice_mail_html'] = '';
    $_GET['id'] = (string) $id;
    if (!isset($_GET['lang'])) {
        $_GET['lang'] = 'en';
    }
    include dirname(__DIR__) . '/sale-invoice-print.php';
    $htmlInvoice = (string) ($GLOBALS['auragold_sale_invoice_mail_html'] ?? '');
    unset($GLOBALS['AURAGOLD_SALE_INVOICE_MAIL_CAPTURE'], $GLOBALS['auragold_sale_invoice_mail_html']);

    if ($htmlInvoice === '') {
        echo json_encode(['ok' => false, 'message' => 'Could not build invoice HTML for attachment.']);
        exit;
    }

    $pdfBinary = null;
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
    }
    if (class_exists('\Dompdf\Dompdf') && class_exists('\Dompdf\Options')) {
        try {
            $root = realpath(dirname(__DIR__));
            if ($root !== false) {
                $options = new \Dompdf\Options();
                $options->set('isRemoteEnabled', true);
                $options->setChroot($root);
                $dompdf = new \Dompdf\Dompdf($options);
                $baseUri = 'file:///' . str_replace(DIRECTORY_SEPARATOR, '/', $root) . '/';
                if (stripos($htmlInvoice, '<head') !== false) {
                    $htmlInvoice = preg_replace('/<head([^>]*)>/i', '<head$1><base href="' . htmlspecialchars($baseUri, ENT_QUOTES, 'UTF-8') . '">', $htmlInvoice, 1);
                }
                $dompdf->loadHtml($htmlInvoice, 'UTF-8');
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                $pdfBinary = $dompdf->output();
            }
        } catch (Throwable $e) {
            $pdfBinary = null;
        }
    }

    $safeFile = preg_replace('/[^A-Za-z0-9._-]+/', '_', $invNo !== '' ? $invNo : 'invoice') . '.pdf';
    if (is_string($pdfBinary) && $pdfBinary !== '') {
        $attachments[] = [
            'filename' => $safeFile,
            'mime'     => 'application/pdf',
            'data'     => $pdfBinary,
        ];
    } else {
        $attachments[] = [
            'filename' => preg_replace('/\.pdf$/i', '', $safeFile) . '-invoice.html',
            'mime'     => 'text/html',
            'data'     => $htmlInvoice,
        ];
    }
}

$result = auragold_smtp_send_message($cfg, $to, $subject, $htmlBody, $attachments);
if (!empty($result['ok'])) {
    $result['recipient'] = $to;
}
echo json_encode($result);
