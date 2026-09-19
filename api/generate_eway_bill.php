<?php
/**
 * WhiteBooks E-Way Bill — generate from saved sale invoice (on-demand API).
 *
 * Usage: POST/GET generate_eway_bill.php?invoice_id=123
 * Requires admin session. Uses tbl_sale_invoices / tbl_sale_invoice_items (falls back to tbl_invoices / tbl_invoice_items if present).
 *
 * Credentials: override via environment or constants below (do not commit production secrets).
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold-gst.php';
require_once __DIR__ . '/eway_generate.php';

// ---------------------------------------------------------------------------
// Config (override with getenv)
// ---------------------------------------------------------------------------
if (!defined('WHITEBOOKS_EWAY_GEN_URL')) {
    define(
        'WHITEBOOKS_EWAY_GEN_URL',
        getenv('WHITEBOOKS_EWAY_GEN_URL') ?: 'https://apisandbox.whitebooks.in/ewaybillapi/v1.03/ewayapi/genewaybill?email=goldmatrixsupport@gmail.com'
    );
}
if (!defined('WHITEBOOKS_EWAY_EMAIL')) {
    define('WHITEBOOKS_EWAY_EMAIL', getenv('WHITEBOOKS_EMAIL') ?: 'goldmatrixsupport@gmail.com');
}
if (!defined('WHITEBOOKS_CLIENT_ID')) {
    define('WHITEBOOKS_CLIENT_ID', getenv('WHITEBOOKS_CLIENT_ID') ?: 'EWBS06efa043-9c63-4638-a142-0c82de5f7687');
}
if (!defined('WHITEBOOKS_CLIENT_SECRET')) {
    define('WHITEBOOKS_CLIENT_SECRET', getenv('WHITEBOOKS_CLIENT_SECRET') ?: 'EWBScb09b008-2f56-459c-b02a-6d1caf5067b9');
}

/** Log file (append). */
function eway_api_log(string $line): void
{
    $dir = dirname(__DIR__) . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $file = $dir . '/eway_log.txt';
    @file_put_contents($file, date('c') . ' ' . $line . "\n", FILE_APPEND | LOCK_EX);
}

function eway_json_fail(string $message, int $http = 400, ?array $extra = null): void
{
    http_response_code($http);
    $out = ['status' => false, 'message' => $message, 'data' => null];
    if ($extra !== null) {
        $out['data'] = $extra;
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function eway_json_ok(string $message, $data): void
{
    echo json_encode(
        ['status' => true, 'message' => $message, 'data' => $data],
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

function eway_table_exists($conn, string $table): bool
{
    $t = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($t === '') {
        return false;
    }
    $r = @mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $t) . "'");
    return $r && mysqli_num_rows($r) > 0;
}

function eway_esc($conn, string $s): string
{
    return mysqli_real_escape_string($conn, $s);
}

/** NIC-style vehicle (letters + digits). */
function eway_normalize_vehicle(string $vehicle): string
{
    return strtoupper(preg_replace('/[^A-Z0-9]/', '', $vehicle));
}

/** GSTIN must be 15 A-Z0-9. */
function eway_valid_gstin(string $g): bool
{
    $g = strtoupper(preg_replace('/\s+/', '', $g));

    return strlen($g) === 15 && preg_match('/^[0-9]{2}[A-Z0-9]{13}$/', $g);
}

/**
 * Resolve invoice + line-item table names (project uses sale invoice schema).
 *
 * @return array{0:string,1:string} [invoice_table, items_table]
 */
function eway_resolve_invoice_tables($conn): array
{
    if (eway_table_exists($conn, 'tbl_invoices') && eway_table_exists($conn, 'tbl_invoice_items')) {
        return ['tbl_invoices', 'tbl_invoice_items'];
    }

    return ['tbl_sale_invoices', 'tbl_sale_invoice_items'];
}

/**
 * @return list<array<string,mixed>>
 */
function eway_fetch_items($conn, string $itemsTable, int $invoiceId): array
{
    $itemsTable = preg_replace('/[^a-zA-Z0-9_]/', '', $itemsTable);
    $id = (int) $invoiceId;
    $sql = "SELECT * FROM `$itemsTable` WHERE invoice_id = $id ORDER BY id ASC";
    $rows = getList($sql);
    return is_array($rows) ? $rows : [];
}

/**
 * Build NIC item lines; IGST = taxable * igstRate / 100 (rates as % on payload).
 *
 * @param list<array<string,mixed>> $lines
 * @return list<array<string,mixed>>
 */
function eway_build_item_list_from_db_rows($conn, array $lines, bool $interstate): array
{
    $out = [];
    foreach ($lines as $row) {
        if (!is_array($row)) {
            continue;
        }
        $taxable = (float) ($row['net_amount'] ?? $row['amount'] ?? 0);
        $tax = (float) ($row['tax_amount'] ?? $row['tax'] ?? 0);
        if ($taxable <= 0 && isset($row['net_amt_with_tax'])) {
            $with = (float) $row['net_amt_with_tax'];
            $taxable = max(0, $with - $tax);
        }
        if ($taxable <= 0) {
            continue;
        }
        $gstRate = 0.0;
        if ($taxable > 0 && $tax > 0) {
            $gstRate = round(($tax / $taxable) * 100, 2);
        }
        $pname = trim((string) ($row['product_name'] ?? $row['name'] ?? 'Goods'));
        if ($pname === '') {
            $pname = 'Goods';
        }
        $qty = (float) ($row['quantity'] ?? $row['qty'] ?? 1);
        if ($qty <= 0) {
            $qty = 1.0;
        }
        $pid = (int) ($row['product_id'] ?? 0);
        $hsn = '711319';
        if ($pid > 0 && function_exists('getRecord')) {
            $pc = @getRecord(
                'SELECT hsn FROM tbl_product_characteristics WHERE product_id = ' . $pid . ' AND status = 1 ORDER BY id ASC LIMIT 1'
            );
            if ($pc && !empty($pc['hsn'])) {
                $hsn = preg_replace('/[^0-9]/', '', (string) $pc['hsn']);
            }
            if ($hsn === '') {
                $mid = @getRecord(
                    'SELECT metal_id FROM tbl_product_characteristics WHERE product_id = ' . $pid . ' AND status = 1 ORDER BY id ASC LIMIT 1'
                );
                $metalId = (int) ($mid['metal_id'] ?? 0);
                if ($metalId > 0) {
                    $mr = @getRecord('SELECT hsn_code FROM tbl_metal WHERE id = ' . $metalId . ' LIMIT 1');
                    if ($mr && !empty($mr['hsn_code'])) {
                        $hsn = preg_replace('/[^0-9]/', '', (string) $mr['hsn_code']);
                    }
                }
            }
        }
        if ($hsn === '') {
            $hsn = '711319';
        }

        if ($interstate) {
            $out[] = [
                'productName' => $pname,
                'productDesc' => $pname,
                'hsnCode' => (int) $hsn,
                'quantity' => round($qty, 3),
                'qtyUnit' => 'NOS',
                'taxableAmount' => round($taxable, 2),
                'cgstRate' => 0,
                'sgstRate' => 0,
                'igstRate' => $gstRate,
            ];
        } else {
            $half = round($gstRate / 2, 2);
            $other = round($gstRate - $half, 2);
            $out[] = [
                'productName' => $pname,
                'productDesc' => $pname,
                'hsnCode' => (int) $hsn,
                'quantity' => round($qty, 3),
                'qtyUnit' => 'NOS',
                'taxableAmount' => round($taxable, 2),
                'cgstRate' => $half,
                'sgstRate' => $other,
                'igstRate' => 0,
            ];
        }
    }

    return $out;
}

// ---------------------------------------------------------------------------
// Auth
// ---------------------------------------------------------------------------
if (empty($_SESSION['Admin'])) {
    eway_json_fail('Unauthorized', 401);
}

// ---------------------------------------------------------------------------
// Input
// ---------------------------------------------------------------------------
$invoiceId = isset($_REQUEST['invoice_id']) ? (int) $_REQUEST['invoice_id'] : 0;
if ($invoiceId <= 0) {
    eway_json_fail('invoice_id is required');
}

[$invTable, $itemsTable] = eway_resolve_invoice_tables($conn);

$invEsc = eway_esc($conn, $invTable);
$row = getRecord("SELECT * FROM `$invEsc` WHERE id = " . $invoiceId . " LIMIT 1");
if (!$row) {
    eway_json_fail('Invoice not found', 404);
}

if (function_exists('auragold_branch_can_access_sale_invoice_row')) {
    if (!auragold_branch_can_access_sale_invoice_row($row)) {
        eway_json_fail('Forbidden', 403);
    }
}

$invoiceNo = trim((string) ($row['invoice_no'] ?? $row['doc_no'] ?? ''));
$invDateRaw = (string) ($row['invoice_date'] ?? $row['doc_date'] ?? '');
$grandTotal = (float) ($row['grand_total'] ?? $row['total'] ?? 0);
$customerId = (int) ($row['customer_id'] ?? $row['supplier_id'] ?? 0);
$branchId = (int) ($row['branch_id'] ?? 0);
$vehicleRaw = (string) ($row['eway_vehicle_no'] ?? $row['vehicle_no'] ?? '');
$distanceKm = (float) ($row['eway_distance_km'] ?? $row['distance_km'] ?? 0);
$ewayDistExplicitZero = array_key_exists('eway_distance_km', $row) && $row['eway_distance_km'] !== null
    && is_numeric($row['eway_distance_km']) && (float) $row['eway_distance_km'] === 0.0;
$toGstinIn = strtoupper(preg_replace('/\s+/', '', (string) ($row['customer_gstin'] ?? $row['buyer_gstin'] ?? $row['gstin'] ?? '')));

if ($grandTotal >= 50000) {
    if (trim($vehicleRaw) === '') {
        $ev = getenv('AURAGOLD_EWAY_DEFAULT_VEHICLE');
        $vehicleRaw = (string) (($ev !== false && trim((string) $ev) !== '') ? $ev : 'APR3214');
    }
    if ($distanceKm <= 0 && ! $ewayDistExplicitZero) {
        $ed = getenv('AURAGOLD_EWAY_DEFAULT_DISTANCE_KM');
        $distanceKm = (float) (($ed !== false && trim((string) $ed) !== '') ? $ed : 100);
    }
}

if ($invoiceNo === '') {
    eway_json_fail('Invoice number is missing on record');
}
if ($invDateRaw === '') {
    eway_json_fail('Invoice date is missing on record');
}

$docDt = \DateTime::createFromFormat('Y-m-d', substr($invDateRaw, 0, 10));
if (!$docDt instanceof \DateTime) {
    $docDt = \DateTime::createFromFormat('d/m/Y', substr($invDateRaw, 0, 10));
}
if (!$docDt instanceof \DateTime) {
    eway_json_fail('Invalid invoice date on record');
}
$docDateDmY = $docDt->format('d/m/Y');

// Branch (seller) — tbl_branches lives on registry DB
$fromGstin = '';
$fromTrdName = 'Seller';
$fromAddr1 = '';
$fromAddr2 = '';
$fromPlace = '';
$fromPin = 0;
$fromStateCode = 0;

if ($branchId > 0 && !empty($conn_master) && $conn_master instanceof mysqli) {
    $br = @getRecordMaster('SELECT * FROM tbl_branches WHERE id = ' . $branchId . ' LIMIT 1');
    if ($br && is_array($br)) {
        $fromTrdName = trim((string) ($br['name'] ?? $br['company_name'] ?? 'Seller')) ?: 'Seller';
        $fromGstin = strtoupper(preg_replace('/\s+/', '', (string) ($br['gst_no'] ?? '')));
        $fromAddr1 = trim((string) ($br['address'] ?? $br['address1'] ?? ''));
        $fromAddr2 = trim((string) ($br['address2'] ?? ''));
        $fromPlace = trim((string) ($br['city'] ?? $br['place'] ?? ''));
        $stLabel = trim((string) ($br['state'] ?? ''));
        if ($fromGstin !== '' && strlen($fromGstin) >= 2) {
            $fromStateCode = (int) substr($fromGstin, 0, 2);
        }
    }
    if (function_exists('auragold_branch_registry_pin_digits')) {
        $pinDigits = auragold_branch_registry_pin_digits($branchId);
        if ($pinDigits !== '') {
            $fromPin = (int) (strlen($pinDigits) >= 6 ? substr($pinDigits, 0, 6) : $pinDigits);
        }
    }
}

if ($fromGstin === '' && function_exists('auragold_branch_gstin_for_eway')) {
    $fromGstin = auragold_branch_gstin_for_eway($conn);
}

// Buyer GSTIN: invoice row, else customer master
if ($toGstinIn === '' && $customerId > 0) {
    $cr = @getRecord('SELECT gstin, billing_state, billing_address, billing_city, alternate_name, name FROM tbl_customers WHERE id = ' . $customerId . ' LIMIT 1');
    if ($cr && !empty($cr['gstin'])) {
        $toGstinIn = strtoupper(preg_replace('/\s+/', '', (string) $cr['gstin']));
    }
}

$toTrdName = trim((string) ($row['customer_name'] ?? $row['supplier_name'] ?? 'Buyer'));
$toAddr1 = '';
$toAddr2 = '';
$toPlace = '';
$toPin = 0;
$toStateCode = 0;

if ($customerId > 0) {
    $cr = @getRecord('SELECT * FROM tbl_customers WHERE id = ' . $customerId . ' LIMIT 1');
    if ($cr && is_array($cr)) {
        if ($toTrdName === '') {
            $toTrdName = trim((string) ($cr['name'] ?? 'Buyer'));
        }
        $toAddr1 = trim((string) ($cr['billing_address'] ?? $cr['address'] ?? ''));
        $toAddr2 = trim((string) ($cr['billing_address2'] ?? $cr['address2'] ?? ''));
        $toPlace = trim((string) ($cr['billing_city'] ?? $cr['city'] ?? ''));
        $pz = function_exists('auragold_customer_billing_pin_digits')
            ? auragold_customer_billing_pin_digits($conn, $customerId)
            : preg_replace('/\D/', '', (string) ($cr['billing_zip_code'] ?? $cr['billing_pincode'] ?? $cr['pincode'] ?? $cr['zip'] ?? ''));
        $toPin = 0;
        if ($pz !== '') {
            $toPin = (int) (strlen($pz) >= 6 ? substr($pz, 0, 6) : $pz);
        }
    }
}

if ($toGstinIn !== '' && strlen($toGstinIn) >= 2) {
    $toStateCode = (int) substr($toGstinIn, 0, 2);
}

// Validations
if (!eway_valid_gstin($fromGstin)) {
    eway_json_fail('Seller GSTIN invalid or missing: set 15-character GSTIN on branch (tbl_branches.gst_no).');
}
if (!eway_valid_gstin($toGstinIn)) {
    eway_json_fail('Buyer GSTIN invalid or missing: save customer GSTIN on the invoice or customer master.');
}
if (strtoupper($fromGstin) === strtoupper($toGstinIn)) {
    eway_json_fail('Buyer GSTIN must differ from seller GSTIN for e-Way Bill.');
}

$veh = eway_normalize_vehicle($vehicleRaw);
if ($veh === '' || strlen($veh) < 4) {
    eway_json_fail('Vehicle number is required (valid format, e.g. MH12AB1234).');
}

if ($distanceKm < 0) {
    eway_json_fail('Distance (km) cannot be negative. Use 0 to let the NIC set distance from the PIN database.');
}

$ownerState = '';
if ($branchId > 0 && !empty($conn_master) && $conn_master instanceof mysqli) {
    $br2 = @getRecordMaster('SELECT state FROM tbl_branches WHERE id = ' . $branchId . ' LIMIT 1');
    if ($br2) {
        $ownerState = trim((string) ($br2['state'] ?? ''));
    }
}
$custState = '';
if ($customerId > 0) {
    $cr2 = @getRecord('SELECT billing_state FROM tbl_customers WHERE id = ' . $customerId . ' LIMIT 1');
    if ($cr2) {
        $custState = trim((string) ($cr2['billing_state'] ?? ''));
    }
}
$interstate = auragold_gst_is_interstate_transaction($ownerState, $custState, $conn);

$lines = eway_fetch_items($conn, $itemsTable, $invoiceId);
$itemList = eway_build_item_list_from_db_rows($conn, $lines, $interstate);
if ($itemList === []) {
    $itemList = [[
        'productName' => 'Goods',
        'productDesc' => 'Goods',
        'hsnCode' => 711319,
        'quantity' => 1,
        'qtyUnit' => 'NOS',
        'taxableAmount' => round(max(0.01, $grandTotal / 1.03), 2),
        'cgstRate' => $interstate ? 0 : 1.5,
        'sgstRate' => $interstate ? 0 : 1.5,
        'igstRate' => $interstate ? 3 : 0,
    ]];
}

$sumTaxable = 0.0;
foreach ($itemList as $il) {
    $sumTaxable += (float) ($il['taxableAmount'] ?? 0);
}
$sumTaxable = round($sumTaxable, 2);

$cgstVal = (float) ($row['gst_cgst_amount'] ?? 0);
$sgstVal = (float) ($row['gst_sgst_amount'] ?? 0);
$igstVal = (float) ($row['gst_igst_amount'] ?? 0);
if (($cgstVal + $sgstVal + $igstVal) < 0.01 && $lines !== []) {
    $sumLineTax = 0.0;
    foreach ($lines as $ln) {
        $sumLineTax += (float) ($ln['tax_amount'] ?? $ln['tax'] ?? 0);
    }
    if ($interstate) {
        $igstVal = round($sumLineTax, 2);
    } else {
        $cgstVal = round($sumLineTax / 2, 2);
        $sgstVal = round($sumLineTax - $cgstVal, 2);
    }
}

$cessVal = 0.0;
$cessNonAdvol = 0.0;
$totInv = round($sumTaxable + $cgstVal + $sgstVal + $igstVal + $cessVal + $cessNonAdvol, 2);
if ($grandTotal > 0) {
    $totInv = round($grandTotal, 2);
}

$payload = [
    'supplyType' => 'O',
    'subSupplyType' => '1',
    'subSupplyDesc' => 'Supply',
    'docType' => 'INV',
    'docNo' => $invoiceNo,
    'docDate' => $docDateDmY,
    'fromGstin' => $fromGstin,
    'fromTrdName' => $fromTrdName,
    'fromAddr1' => $fromAddr1 !== '' ? $fromAddr1 : 'NA',
    'fromAddr2' => $fromAddr2,
    'fromPlace' => $fromPlace !== '' ? $fromPlace : 'NA',
    'fromPincode' => $fromPin > 0 ? $fromPin : 110001,
    'fromStateCode' => $fromStateCode > 0 ? $fromStateCode : (int) substr($fromGstin, 0, 2),
    'toGstin' => $toGstinIn,
    'toTrdName' => $toTrdName !== '' ? $toTrdName : 'Buyer',
    'toAddr1' => $toAddr1 !== '' ? $toAddr1 : 'NA',
    'toAddr2' => $toAddr2,
    'toPlace' => $toPlace !== '' ? $toPlace : 'NA',
    'toPincode' => $toPin > 0 ? $toPin : 110001,
    'toStateCode' => $toStateCode > 0 ? $toStateCode : (int) substr($toGstinIn, 0, 2),
    'transactionType' => 1,
    'totalValue' => $sumTaxable,
    'cgstValue' => round($cgstVal, 2),
    'sgstValue' => round($sgstVal, 2),
    'igstValue' => round($igstVal, 2),
    'cessValue' => $cessVal,
    'cessNonAdvolValue' => $cessNonAdvol,
    'totInvValue' => $totInv,
    'transMode' => '1',
    'transDistance' => (abs($distanceKm) < 0.0000001) ? 0 : (int) max(1, round($distanceKm)),
    'transporterId' => '',
    'transDocNo' => '',
    'transDocDate' => $docDateDmY,
    'vehicleNo' => $veh,
    'vehicleType' => 'R',
    'itemList' => $itemList,
];

// --- Pre-API payload fixes (WhiteBooks / NIC validation) ---
$payload['fromPincode'] = (int) ($row['from_pincode'] ?? $row['pincode'] ?? 0);
$payload['toPincode'] = (int) ($row['customer_pincode'] ?? $row['to_pincode'] ?? 0);
if ($payload['fromPincode'] <= 0) {
    $payload['fromPincode'] = $fromPin > 0 ? (int) $fromPin : 110001;
}
if ($payload['toPincode'] <= 0) {
    $payload['toPincode'] = $toPin > 0 ? (int) $toPin : 110001;
}
$payload['fromPincode'] = isset($payload['fromPincode']) ? (int) $payload['fromPincode'] : 0;
$payload['toPincode'] = isset($payload['toPincode']) ? (int) $payload['toPincode'] : 0;
if ($payload['fromPincode'] <= 0) {
    $payload['fromPincode'] = 110001;
}
if ($payload['toPincode'] <= 0) {
    $payload['toPincode'] = 110001;
}
$payload['actFromStateCode'] = (int) ($payload['fromStateCode'] ?? 0);
$payload['actToStateCode'] = (int) ($payload['toStateCode'] ?? 0);
$payload['vehicleNo'] = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) ($payload['vehicleNo'] ?? '')));
if (empty($payload['transDocDate'])) {
    $payload['transDocDate'] = date('d/m/Y');
}
$payload['transDistance'] = (string) ($payload['transDistance'] ?? '0');
if (empty($payload['transporterName'])) {
    unset($payload['transporterName']);
}
@file_put_contents(
    dirname(__DIR__) . '/eway_payload_debug.json',
    json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)
);
@file_put_contents(
    dirname(__DIR__) . '/eway_final_payload.json',
    json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)
);

$jsonData = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
if ($jsonData === false) {
    eway_json_fail('Failed to encode JSON payload');
}
$jsonBody = $jsonData;

eway_api_log('REQ invoice_id=' . $invoiceId . ' docNo=' . $invoiceNo . ' json=' . (strlen($jsonBody) > 16000 ? substr($jsonBody, 0, 16000) . '…' : $jsonBody));

$headers = [
    'Content-Type: application/json',
    'accept: */*',
    'ip_address: 0.0.0.0',
    'client_id: ' . WHITEBOOKS_CLIENT_ID,
    'client_secret: ' . WHITEBOOKS_CLIENT_SECRET,
    'gstin: ' . $fromGstin,
];

$ch = curl_init(WHITEBOOKS_EWAY_GEN_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 25);
curl_setopt($ch, CURLOPT_TIMEOUT, 90);
$respBody = curl_exec($ch);
$curlErr = curl_error($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

eway_api_log('RESP invoice_id=' . $invoiceId . ' HTTP=' . $httpCode . ' err=' . $curlErr . ' body=' . (is_string($respBody) ? $respBody : ''));

$decoded = null;
if (is_string($respBody) && $respBody !== '') {
    $decoded = json_decode($respBody, true);
}

$ewayNo = '';
$ewayDate = '';
$validUpto = '';
if (is_array($decoded)) {
    $ewayNo = trim((string) ($decoded['ewayBillNo'] ?? $decoded['ewayBillNumber'] ?? $decoded['EwbNo'] ?? ''));
    $dtRaw = $decoded['ewayBillDate'] ?? $decoded['EwbDt'] ?? null;
    if (!empty($dtRaw)) {
        $ts = strtotime((string) $dtRaw);
        $ewayDate = $ts !== false ? date('Y-m-d H:i:s', $ts) : (string) $dtRaw;
    }
    $vu = $decoded['validUpto'] ?? $decoded['ValidUpto'] ?? $decoded['VldUpto'] ?? null;
    if (!empty($vu)) {
        $ts2 = strtotime((string) $vu);
        $validUpto = $ts2 !== false ? date('Y-m-d H:i:s', $ts2) : (string) $vu;
    }
}

$fullResponseJson = json_encode(
    is_array($decoded) ? $decoded : ['raw' => $respBody],
    JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
);
if ($fullResponseJson === false) {
    $fullResponseJson = '{"error":"encode_failed"}';
}

$ok = $ewayNo !== '';
if (!$ok && is_array($decoded)) {
    $sc = $decoded['status_cd'] ?? $decoded['Status'] ?? null;
    if ((string) $sc === '1' || (isset($decoded['success']) && $decoded['success'])) {
        $ok = $ewayNo !== '';
    }
}

$statusFlag = $ok ? 'SUCCESS' : 'FAILED';
$respEsc = eway_esc($conn, $fullResponseJson);
$noEsc = eway_esc($conn, $ewayNo);
$dtEsc = eway_esc($conn, $ewayDate);

$upd = "UPDATE `$invEsc` SET "
    . "eway_bill_no = " . ($ewayNo !== '' ? "'$noEsc'" : 'NULL') . ", "
    . "eway_bill_date = " . ($ewayDate !== '' ? "'$dtEsc'" : 'NULL') . ", "
    . "eway_status = '" . eway_esc($conn, $statusFlag) . "', "
    . "eway_response = '$respEsc'";

if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, $invTable, 'eway_valid_upto') && $validUpto !== '') {
    $upd .= ", eway_valid_upto = '" . eway_esc($conn, $validUpto) . "'";
}
$upd .= ' WHERE id = ' . $invoiceId . ' LIMIT 1';

$hasEwayCols = @mysqli_query($conn, "SHOW COLUMNS FROM `$invEsc` LIKE 'eway_bill_no'");
if ($hasEwayCols && mysqli_num_rows($hasEwayCols) > 0) {
    mysqli_free_result($hasEwayCols);
    if (!@mysqli_query($conn, $upd)) {
        eway_api_log('DB update failed: ' . mysqli_error($conn));
    }
} elseif ($hasEwayCols) {
    mysqli_free_result($hasEwayCols);
}

$apiData = is_array($decoded) ? $decoded : ['raw' => $respBody];
if (!$ok) {
    $errMsg = $curlErr ?: 'E-Way generation failed';
    if (is_array($decoded)) {
        $nested = $decoded['error'] ?? null;
        if (is_array($nested) && isset($nested['message'])) {
            $errMsg = (string) $nested['message'];
        } elseif (!empty($decoded['message'])) {
            $errMsg = (string) $decoded['message'];
        }
    }
    if ($errMsg === '' && is_string($respBody)) {
        $errMsg = substr($respBody, 0, 500);
    }
    eway_json_fail(
        'E-Way Bill Failed: ' . $errMsg,
        200,
        array_merge($apiData, [
            'eway_bill_no' => $ewayNo ?: null,
            'eway_bill_date' => $ewayDate ?: null,
            'valid_upto' => $validUpto ?: null,
            'http_code' => $httpCode,
        ])
    );
}

$dataOut = array_merge($apiData, [
    'eway_bill_no' => $ewayNo,
    'eway_bill_date' => $ewayDate,
    'valid_upto' => $validUpto,
]);
eway_json_ok('E-Way Bill Generated', $dataOut);
