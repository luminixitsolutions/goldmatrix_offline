<?php

session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auragold_cheque_entry_schema.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['Admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

auragold_ensure_branch_id_on_settings_tables($conn);
$branch_id = auragold_cheque_entry_resolve_branch_id(
    isset($_REQUEST['settings_branch_id']) ? (int) $_REQUEST['settings_branch_id'] : null
);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = trim((string) ($_REQUEST['action'] ?? ''));

if ($method === 'GET' && ($action === '' || $action === 'list')) {
    $search = trim((string) ($_GET['q'] ?? ''));
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 500;
    $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
    $filters = [
        'cheque_date_from' => $_GET['cheque_date_from'] ?? '',
        'cheque_date_to' => $_GET['cheque_date_to'] ?? '',
        'pay_date_from' => $_GET['pay_date_from'] ?? '',
        'pay_date_to' => $_GET['pay_date_to'] ?? '',
        'branch_name' => $_GET['branch_name'] ?? '',
        'account_ledger' => $_GET['account_ledger'] ?? '',
        'pdc_voucher_type' => $_GET['pdc_voucher_type'] ?? '',
        'bank_name' => $_GET['bank_name'] ?? '',
        'status' => $_GET['status'] ?? '',
        'ref_invoice_no' => $_GET['ref_invoice_no'] ?? '',
        'cheque_no' => $_GET['cheque_no'] ?? '',
        'account_no' => $_GET['account_no'] ?? '',
    ];
    $entries = auragold_get_cheque_entries($conn, $branch_id, $search, $limit, $offset, $filters);
    $total_amount = 0.0;
    foreach ($entries as $e) {
        $total_amount += (float) ($e['amount'] ?? 0);
    }
    echo json_encode([
        'success' => true,
        'entries' => $entries,
        'count' => count($entries),
        'total_amount' => round($total_amount, 2),
        'next_pdc_no' => auragold_cheque_entry_next_pdc_no($conn, $branch_id),
    ]);
    exit;
}

if ($method === 'GET' && $action === 'get') {
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $entry = auragold_get_cheque_entry_by_id($conn, $id, $branch_id);
    if (!$entry) {
        echo json_encode(['success' => false, 'message' => 'Cheque entry not found.']);
        exit;
    }
    echo json_encode(['success' => true, 'entry' => $entry]);
    exit;
}

if ($method !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$action = trim((string) ($_POST['action'] ?? 'save'));

if ($action === 'delete') {
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $result = auragold_delete_cheque_entry($conn, $id, $branch_id);
    echo json_encode([
        'success' => $result['ok'],
        'message' => $result['message'],
    ]);
    exit;
}

$save_data = [
    'id' => isset($_POST['id']) ? (int) $_POST['id'] : 0,
    'status' => $_POST['status'] ?? '',
    'bounced_cleared_date' => $_POST['bounced_cleared_date'] ?? '',
    'nsf_fees' => $_POST['nsf_fees'] ?? 0,
    'recoverable' => isset($_POST['recoverable']) ? (int) $_POST['recoverable'] : 0,
    'limited_update' => !empty($_POST['limited_update']) ? 1 : 0,
];

if (empty($save_data['limited_update'])) {
    $save_data = array_merge($save_data, [
        'pdc_no' => $_POST['pdc_no'] ?? '',
        'account_no' => $_POST['account_no'] ?? '',
        'account_ledger' => $_POST['account_ledger'] ?? '',
        'bank_name' => $_POST['bank_name'] ?? '',
        'cheque_no' => $_POST['cheque_no'] ?? '',
        'cheque_date' => $_POST['cheque_date'] ?? '',
        'pay_date' => $_POST['pay_date'] ?? '',
        'amount' => $_POST['amount'] ?? 0,
        'branch_name' => $_POST['branch_name'] ?? '',
        'against_voucher_no' => $_POST['against_voucher_no'] ?? '',
        'against_voucher_type' => $_POST['against_voucher_type'] ?? '',
        'invoice_date' => $_POST['invoice_date'] ?? '',
        'reference_voucher_type' => $_POST['reference_voucher_type'] ?? '',
        'ref_invoice_no' => $_POST['ref_invoice_no'] ?? '',
        'pdc_voucher_type' => $_POST['pdc_voucher_type'] ?? '',
    ]);
}

$result = auragold_save_cheque_entry($conn, $branch_id, $save_data);

echo json_encode([
    'success' => $result['ok'],
    'message' => $result['message'],
    'id' => $result['id'] ?? null,
    'entry' => $result['entry'] ?? null,
]);
