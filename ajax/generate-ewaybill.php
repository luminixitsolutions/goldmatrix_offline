<?php
/**
 * Regenerate (or first-time) e-Way Bill for a saved sale invoice — uses e-Way Bill API settings in admin.
 * POST: invoice_id (required), regenerate=1 to clear a previously generated e-Way no first (confirm in UI first).
 */
require_once __DIR__ . '/../includes/session_init.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_require_login.php';
if (is_file(__DIR__ . '/../includes/ewaybill_api_helper.php')) {
    require_once __DIR__ . '/../includes/ewaybill_api_helper.php';
}
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['Admin']) && (int) ($_SESSION['user_id'] ?? 0) <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$invoice_id  = isset($_POST['invoice_id']) ? (int) $_POST['invoice_id'] : 0;
$regenerate   = !empty($_POST['regenerate']) && (string) $_POST['regenerate'] === '1';

if ($invoice_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid invoice_id']);
    exit;
}
if (!isset($conn) || !$conn instanceof mysqli) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection is not available']);
    exit;
}

$inv = getRecord('SELECT id, invoice_no, eway_bill_no FROM tbl_sale_invoices WHERE id = ' . $invoice_id . ' LIMIT 1');
if (!is_array($inv) || empty($inv['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invoice ID not found. Please save invoice first.']);
    exit;
}
if (!empty($inv['eway_bill_no']) && !$regenerate) {
    $prev = @getRecord('SELECT eway_response, eway_bill_no, eway_bill_date, eway_valid_upto FROM tbl_sale_invoices WHERE id = ' . (int) $invoice_id . ' LIMIT 1');
    $ewPrev = [
        'status'       => 'error',
        'ewayBillNo'   => (string) ($prev['eway_bill_no'] ?? $inv['eway_bill_no'] ?? ''),
        'ewayBillDate' => (string) ($prev['eway_bill_date'] ?? ''),
        'validUpto'    => (string) ($prev['eway_valid_upto'] ?? ''),
        'message'      => 'E-Way Bill already generated. Check "Regenerate" in the app or pass regenerate=1.',
    ];
    if (is_array($prev) && !empty($prev['eway_response']) && (string) $prev['eway_response'] !== '') {
        $ewPrev['api_response'] = (string) $prev['eway_response'];
    }
    echo json_encode(
        [
            'status'    => 'error',
            'message'   => $ewPrev['message'],
            'eway_bill' => $ewPrev,
        ],
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

if ($regenerate && !empty($inv['eway_bill_no'])) {
    @mysqli_query(
        $conn,
        'UPDATE tbl_sale_invoices SET eway_bill_no = NULL, eway_bill_date = NULL, eway_valid_upto = NULL, eway_status = NULL, eway_response = NULL, eway_generated_at = NULL WHERE id = ' . (int) $invoice_id . ' LIMIT 1'
    );
}

if (!function_exists('ewaybill_generate_from_sale_invoice')) {
    echo json_encode(['status' => 'error', 'message' => 'e-Way Bill helper is not available']);
    exit;
}

if ($regenerate) {
    @mysqli_query(
        $conn,
        "UPDATE tbl_sale_invoices SET eway_trans_distance = '0', eway_distance_km = 0 WHERE id = " . (int) $invoice_id . ' LIMIT 1'
    );
}

$out   = ewaybill_generate_from_sale_invoice($conn, (int) $invoice_id);
$rowEw = @getRecord(
    'SELECT eway_status, eway_response, eway_bill_no, eway_bill_date, eway_valid_upto FROM tbl_sale_invoices WHERE id = ' . (int) $invoice_id . ' LIMIT 1'
);
$ekeys = is_array($out['eway_bill'] ?? null) ? $out['eway_bill'] : [];
$ewBillJson = [
    'status'       => (string) ($ekeys['status'] ?? ($out['ok'] ? 'success' : 'error')),
    'ewayBillNo'   => (string) ($ekeys['ewayBillNo'] ?? $out['ewayBillNo'] ?? ''),
    'ewayBillDate' => (string) ($ekeys['ewayBillDate'] ?? ''),
    'validUpto'    => (string) ($ekeys['validUpto'] ?? $out['validUpto'] ?? ''),
    'message'      => (string) ($ekeys['message'] ?? $out['message'] ?? ''),
];
if (is_array($rowEw) && isset($rowEw['eway_response']) && (string) $rowEw['eway_response'] !== '') {
    $rawEw = (string) $rowEw['eway_response'];
    $ewBillJson['api_response'] = function_exists('ewaybill_sanitize_eway_api_json_for_ui')
        ? ewaybill_sanitize_eway_api_json_for_ui($rawEw)
        : $rawEw;
}

$savedEwayStatus = is_array($rowEw) ? (string) ($rowEw['eway_status'] ?? '') : '';
$effectiveStatus = $savedEwayStatus !== '' ? $savedEwayStatus : (!empty($out['eway_db_status']) ? (string) $out['eway_db_status'] : '');
$savedHasBillNo  = is_array($rowEw) && !empty(trim((string) ($rowEw['eway_bill_no'] ?? '')));

$payloadOut = [
    'status'       => !empty($out['ok']) ? 'success' : 'error',
    'message'      => !empty($out['ok'])
        ? ((string) ($out['message'] ?? '') !== '' ? (string) $out['message'] : 'E-Way Bill response saved')
        : (string) ($out['message'] ?? ''),
    'invoice_id'   => (int) $invoice_id,
    'eway_status'  => $effectiveStatus,
    'eway_bill'    => $ewBillJson,
];
if ($savedHasBillNo) {
    $payloadOut['eway_generated'] = true;
}
if ($effectiveStatus === 'success_no_eway_number' || $effectiveStatus === 'sandbox_success_no_eway_number') {
    $payloadOut['eway_pending'] = true;
}
if (!empty($out['ok']) && is_array($rowEw) && isset($rowEw['eway_response']) && (string) $rowEw['eway_response'] !== '') {
    $payloadOut['api_response'] = function_exists('ewaybill_sanitize_eway_api_json_for_ui')
        ? ewaybill_sanitize_eway_api_json_for_ui((string) $rowEw['eway_response'])
        : (string) $rowEw['eway_response'];
}
if (!empty($out['eway_debug_payload'])) {
    $payloadOut['eway_debug_payload'] = (string) $out['eway_debug_payload'];
}
if (!empty($out['final_payload_sent_to_api'])) {
    $payloadOut['final_payload_sent_to_api'] = (string) $out['final_payload_sent_to_api'];
} elseif (!empty($out['eway_debug_payload'])) {
    $payloadOut['final_payload_sent_to_api'] = function_exists('ewaybill_format_payload_ui_debug')
        ? ewaybill_format_payload_ui_debug((string) $out['eway_debug_payload'])
        : (string) $out['eway_debug_payload'];
}
if (!empty($out['eway_debug_message'])) {
    $payloadOut['eway_debug_message'] = (string) $out['eway_debug_message'];
}
if (function_exists('ewaybill_collect_eway_debug_urls') && function_exists('ewaybill_merged_config')) {
    $payloadOut['eway_debug_urls'] = ewaybill_collect_eway_debug_urls(ewaybill_merged_config($conn));
}
echo json_encode(
    $payloadOut,
    JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
);
