<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$customer_id = isset($_GET['customer_id']) ? (int) $_GET['customer_id'] : 0;
if ($customer_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid customer id']);
    exit;
}

$schema = __DIR__ . '/../includes/document_types_schema.php';
if (is_file($schema)) {
    require_once $schema;
    if (function_exists('auragold_ensure_tbl_document_types') && $conn instanceof mysqli) {
        auragold_ensure_tbl_document_types($conn);
    }
}

$customer = getRecord(
    "SELECT id, name, mobile_country_code, mobile_no, mail_id, date1, identity_issue_date, registration_date, kyc, aml, 
        billing_address1, billing_address2, billing_city, billing_state, billing_zip_code, billing_country, share_holder_documents 
     FROM tbl_customers WHERE id = $customer_id AND status = 1 LIMIT 1"
);

if (!$customer) {
    echo json_encode(['status' => 'error', 'message' => 'Customer not found']);
    exit;
}

$type_map = [];
$type_rows = getList('SELECT id, name FROM tbl_document_types WHERE status = 1');
if (!is_array($type_rows)) {
    $type_rows = [];
}
foreach ($type_rows as $tr) {
    $tid = (int) ($tr['id'] ?? 0);
    if ($tid > 0) {
        $type_map[$tid] = $tr['name'] ?? '';
    }
}

$docs_raw = [];
if (!empty($customer['share_holder_documents'])) {
    $decoded = json_decode((string) $customer['share_holder_documents'], true);
    if (is_array($decoded)) {
        $docs_raw = $decoded;
    }
}

$documents = [];
foreach ($docs_raw as $d) {
    if (!is_array($d)) {
        continue;
    }
    $path = isset($d['path']) ? trim((string) $d['path']) : '';
    if ($path === '' || !preg_match('#^uploads/customers/share_holders/[A-Za-z0-9_.-]+$#', $path)) {
        continue;
    }
    $type_id = isset($d['document_type_id']) ? (int) $d['document_type_id'] : 0;
    $type_name = $type_id > 0 && isset($type_map[$type_id]) ? $type_map[$type_id] : '';
    if ($type_name === '') {
        $type_name = $type_id > 0 ? ('Type #' . $type_id) : '—';
    }
    $name = isset($d['name']) ? (string) $d['name'] : basename($path);
    $expiry = isset($d['expiry_date']) && $d['expiry_date'] !== null && $d['expiry_date'] !== ''
        ? (string) $d['expiry_date'] : '';
    $issue = isset($d['issue_date']) && $d['issue_date'] !== null && $d['issue_date'] !== ''
        ? (string) $d['issue_date'] : '';
    $documents[] = [
        'document_type' => $type_name,
        'name'          => $name,
        'issue_date'    => $issue,
        'expiry_date'   => $expiry,
        'path'          => $path,
    ];
}

$cc = trim((string) ($customer['mobile_country_code'] ?? ''));
$mn = trim((string) ($customer['mobile_no'] ?? ''));
$contact = trim($cc . ' ' . $mn);

$addr_bits = array_filter([
    trim((string) ($customer['billing_address1'] ?? '')),
    trim((string) ($customer['billing_address2'] ?? '')),
    trim((string) ($customer['billing_city'] ?? '')),
    trim((string) ($customer['billing_state'] ?? '')),
    trim((string) ($customer['billing_zip_code'] ?? '')),
    trim((string) ($customer['billing_country'] ?? '')),
]);
$address = implode(', ', $addr_bits);

$kyc_date = '';
if (!empty($customer['identity_issue_date'])) {
    $kyc_date = (string) $customer['identity_issue_date'];
} elseif (!empty($customer['registration_date'])) {
    $kyc_date = (string) $customer['registration_date'];
}

$kyc_flag = static function ($v): bool {
    if ($v === null || $v === '') {
        return false;
    }
    if (is_bool($v)) {
        return $v;
    }
    if (is_numeric($v)) {
        return (int) $v === 1;
    }
    $s = strtolower(trim((string) $v));
    return in_array($s, ['1', 'yes', 'y', 'true', 'on'], true);
};

$payload = [
    'id'                      => (int) $customer['id'],
    'name'                    => (string) ($customer['name'] ?? ''),
    'contact'                 => $contact,
    'email'                   => (string) ($customer['mail_id'] ?? ''),
    'dob'                     => (string) ($customer['date1'] ?? ''),
    'kyc_verification_date'   => $kyc_date,
    'address'                 => $address,
    'kyc'                     => $kyc_flag($customer['kyc'] ?? null),
    'aml'                     => $kyc_flag($customer['aml'] ?? null),
];

echo json_encode([
    'status'     => 'success',
    'customer'   => $payload,
    'documents'  => $documents,
    'doc_count'  => count($documents),
]);
