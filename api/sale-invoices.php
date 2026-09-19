<?php
/**
 * Sale Invoices API for a login shop (main branch).
 *
 * GET /api/sale-invoices.php?access_token=SHOP_ACCESS_TOKEN
 * GET /api/sale-invoices.php?access_token=…&id=123
 * GET /api/sale-invoices.php?access_token=…&invoice_no=SI-001
 * GET /api/sale-invoices.php?access_token=…&customer_id=45
 *
 * Use the shop access_token from /api/shops.php (not shop_id, not user/My Profile token).
 * Each shop token returns that shop's database invoices only.
 * Returns full sale invoice records (all header fields + items + payments).
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/branch_credentials.php';
require_once dirname(__DIR__) . '/includes/auragold_api_shop_connection.php';
require_once dirname(__DIR__) . '/includes/auragold_access_token.php';
require_once dirname(__DIR__) . '/includes/auragold_payment_details_merge.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Access-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed. Use GET.']);
    exit;
}

$shopAccessToken = trim((string) ($_GET['access_token'] ?? $_GET['shop_access_token'] ?? $_GET['token'] ?? ''));
if ($shopAccessToken === '' && !empty($_SERVER['HTTP_X_ACCESS_TOKEN'])) {
    $shopAccessToken = trim((string) $_SERVER['HTTP_X_ACCESS_TOKEN']);
}
if ($shopAccessToken === '' && !empty($_SERVER['HTTP_AUTHORIZATION'])
    && preg_match('/^\s*Bearer\s+(\S+)\s*$/i', (string) $_SERVER['HTTP_AUTHORIZATION'], $m)) {
    $shopAccessToken = trim((string) $m[1]);
}

if ($shopAccessToken === '') {
    http_response_code(400);
    echo json_encode([
        'ok'      => false,
        'message' => 'Pass shop access_token from /api/shops.php (per-shop token), e.g. /api/sale-invoices.php?access_token=…',
    ]);
    exit;
}

$filterId         = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$filterInvoiceNo  = trim((string) ($_GET['invoice_no'] ?? ''));
$filterCustomerId = isset($_GET['customer_id']) ? (int) $_GET['customer_id'] : 0;

try {
$connShop = auragold_api_connect_shop_database_by_access_token($shopAccessToken);
if (empty($connShop['ok']) || !($connShop['link'] instanceof mysqli)) {
    http_response_code(401);
    echo json_encode([
        'ok'      => false,
        'message' => (string) ($connShop['message'] ?? 'Shop database unavailable.'),
    ]);
    exit;
}

/** @var mysqli $link */
$link     = $connShop['link'];
$branch   = is_array($connShop['branch'] ?? null) ? $connShop['branch'] : [];
$shopId   = (int) ($branch['id'] ?? 0);
$shopName = trim((string) ($branch['name'] ?? ''));

/**
 * @param mysqli $link
 */
function auragold_sale_invoices_api_table_exists($link, string $table): bool
{
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($table === '') {
        return false;
    }
    $rs = @mysqli_query($link, "SHOW TABLES LIKE '" . $table . "'");
    $ok = $rs && mysqli_num_rows($rs) > 0;
    if ($rs) {
        mysqli_free_result($rs);
    }
    return $ok;
}

/**
 * Fetch all rows as assoc arrays.
 *
 * @param mysqli $link
 * @return list<array<string,mixed>>
 */
function auragold_sale_invoices_api_fetch_all($link, string $sql): array
{
    $rs = mysqli_query($link, $sql);
    if (!$rs) {
        return [];
    }
    $rows = [];
    while ($row = mysqli_fetch_assoc($rs)) {
        $rows[] = $row;
    }
    mysqli_free_result($rs);
    return $rows;
}

/**
 * Decode known JSON columns in-place; leave others as strings.
 *
 * @param array<string,mixed> $row
 * @param list<string> $jsonKeys
 * @return array<string,mixed>
 */
function auragold_sale_invoices_api_decode_json_fields(array $row, array $jsonKeys): array
{
    foreach ($jsonKeys as $key) {
        if (!array_key_exists($key, $row)) {
            continue;
        }
        $raw = $row[$key];
        if ($raw === null || $raw === '') {
            $row[$key] = null;
            continue;
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $row[$key] = $decoded;
            }
        }
    }
    return $row;
}

/**
 * Cast numeric-looking DB strings for common money/qty columns when present.
 *
 * @param array<string,mixed> $row
 * @return array<string,mixed>
 */
function auragold_sale_invoices_api_cast_row(array $row): array
{
    $intKeys = [
        'id', 'branch_id', 'customer_id', 'against_id', 'layaways_id', 'created_by',
        'invoice_id', 'source_sale_order_item_id', 'sort_order', 'product_id',
        'product_characteristic_id', 'location_id', 'merge_group_index',
    ];
    $floatKeys = [
        'previous_balance', 'previous_gold', 'previous_silver', 'previous_diamond', 'previous_gemstone',
        'use_previous_balance', 'previous_balance_used_amt', 'adjusted_balance_used',
        'subtotal', 'additional_amt', 'net_total', 'reward_points', 'coupon_discount',
        'discount_amt', 'discount_percent', 'redeem_points', 'grand_total', 'advance_payment',
        'metal_amt', 'round_off', 'paid_amt', 'balance_amt',
        'gst_cgst_amount', 'gst_sgst_amount', 'gst_igst_amount',
        'quantity', 'gross_weight', 'less_weight', 'net_weight', 'pure_weight', 'final_weight',
        'metal_qty', 'metal_weight', 'stone_weight', 'purity', 'purity_weight', 'rate',
        'metal_rate', 'metal_value', 'making_amount', 'amount', 'tax_amount', 'net_amount',
        'net_amt_with_tax', 'diamond_amount', 'stone_amount',
        'amount', 'previous_balance_amount', 'current_order_amount',
    ];

    foreach ($intKeys as $k) {
        if (array_key_exists($k, $row) && $row[$k] !== null && $row[$k] !== '') {
            $row[$k] = (int) $row[$k];
        }
    }
    foreach ($floatKeys as $k) {
        if (array_key_exists($k, $row) && $row[$k] !== null && $row[$k] !== '') {
            $row[$k] = round((float) $row[$k], 4);
        }
    }
    if (array_key_exists('status', $row) && is_numeric($row['status']) && !is_string($row['status'])) {
        // keep as-is
    } elseif (array_key_exists('status', $row) && is_string($row['status']) && ctype_digit($row['status'])) {
        // item/payment tinyint often comes as string "1"
        // leave string statuses (draft/deleted) alone; only cast pure digit tinyints on child tables via context
    }

    return $row;
}

if (!auragold_sale_invoices_api_table_exists($link, 'tbl_sale_invoices')) {
    mysqli_close($link);
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'tbl_sale_invoices not found in shop database.']);
    exit;
}

$hasItems    = auragold_sale_invoices_api_table_exists($link, 'tbl_sale_invoice_items');
$hasPayments = auragold_sale_invoices_api_table_exists($link, 'tbl_sale_invoice_payments');
$hasForming  = auragold_sale_invoices_api_table_exists($link, 'invoice_forming_mapping');

$where = ["LOWER(IFNULL(si.status, '')) NOT IN ('deleted')"];
if ($filterId > 0) {
    $where[] = 'si.id = ' . $filterId;
}
if ($filterInvoiceNo !== '') {
    $where[] = "si.invoice_no = '" . mysqli_real_escape_string($link, $filterInvoiceNo) . "'";
}
if ($filterCustomerId > 0) {
    $where[] = 'si.customer_id = ' . $filterCustomerId;
}
$whereSql = implode(' AND ', $where);

$invoices = auragold_sale_invoices_api_fetch_all($link, "
    SELECT si.*
    FROM tbl_sale_invoices si
    WHERE {$whereSql}
    ORDER BY si.invoice_date DESC, si.id DESC
");

if ($filterId > 0 && count($invoices) === 0) {
    mysqli_close($link);
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Sale invoice not found.']);
    exit;
}
if ($filterInvoiceNo !== '' && count($invoices) === 0) {
    mysqli_close($link);
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Sale invoice not found for invoice_no.']);
    exit;
}

$invoiceIds = [];
foreach ($invoices as $inv) {
    $iid = (int) ($inv['id'] ?? 0);
    if ($iid > 0) {
        $invoiceIds[] = $iid;
    }
}

$itemsByInvoice    = [];
$paymentsByInvoice = [];
$formingByInvoice  = [];

if ($invoiceIds !== []) {
    $idList = implode(',', $invoiceIds);

    if ($hasItems) {
        $hasSortOrder = false;
        $colRs = @mysqli_query($link, "SHOW COLUMNS FROM tbl_sale_invoice_items LIKE 'sort_order'");
        if ($colRs && mysqli_num_rows($colRs) > 0) {
            $hasSortOrder = true;
        }
        if ($colRs) {
            mysqli_free_result($colRs);
        }
        $itemOrderSql = $hasSortOrder ? 'ORDER BY sort_order ASC, id ASC' : 'ORDER BY id ASC';
        $itemRows = auragold_sale_invoices_api_fetch_all($link, "
            SELECT * FROM tbl_sale_invoice_items
            WHERE invoice_id IN ({$idList})
            {$itemOrderSql}
        ");
        foreach ($itemRows as $it) {
            $iid = (int) ($it['invoice_id'] ?? 0);
            $it  = auragold_sale_invoices_api_decode_json_fields($it, ['images', 'extra_fields_json']);
            $it  = auragold_sale_invoices_api_cast_row($it);
            if (isset($it['status']) && is_numeric($it['status'])) {
                $it['status'] = (int) $it['status'];
            }
            $itemsByInvoice[$iid][] = $it;
        }
    }

    if ($hasPayments) {
        $payRows = auragold_sale_invoices_api_fetch_all($link, "
            SELECT * FROM tbl_sale_invoice_payments
            WHERE invoice_id IN ({$idList})
            ORDER BY id ASC
        ");
        if (function_exists('auragold_merge_payment_details_into_payments')) {
            auragold_merge_payment_details_into_payments($payRows);
        }
        foreach ($payRows as $pr) {
            $iid = (int) ($pr['invoice_id'] ?? 0);
            $pr  = auragold_sale_invoices_api_decode_json_fields($pr, ['payment_details']);
            $pr  = auragold_sale_invoices_api_cast_row($pr);
            if (isset($pr['status']) && is_numeric($pr['status'])) {
                $pr['status'] = (int) $pr['status'];
            }
            $paymentsByInvoice[$iid][] = $pr;
        }
    }

    if ($hasForming) {
        $formRows = auragold_sale_invoices_api_fetch_all($link, "
            SELECT *
            FROM invoice_forming_mapping
            WHERE source_type = 'sale_invoice'
              AND source_transaction_id IN ({$idList})
              AND IFNULL(status, 1) = 1
            ORDER BY id DESC
        ");
        foreach ($formRows as $fr) {
            $iid = (int) ($fr['source_transaction_id'] ?? 0);
            if ($iid > 0 && !isset($formingByInvoice[$iid])) {
                $formingByInvoice[$iid] = auragold_sale_invoices_api_cast_row($fr);
            }
        }
    }
}

mysqli_close($link);

$data = [];
foreach ($invoices as $inv) {
    $iid = (int) ($inv['id'] ?? 0);
    $row = auragold_sale_invoices_api_decode_json_fields($inv, ['payment_comments']);
    $row = auragold_sale_invoices_api_cast_row($row);

    // Aliases useful for consumers (same as ajax/get-sale-invoice.php)
    $row['order_id']   = $iid;
    $row['order_no']   = (string) ($row['invoice_no'] ?? '');
    $row['order_date'] = $row['invoice_date'] ?? null;

    $row['items']            = $itemsByInvoice[$iid] ?? [];
    $row['payments']         = $paymentsByInvoice[$iid] ?? [];
    $row['forming_mapping']  = $formingByInvoice[$iid] ?? null;
    $row['items_count']      = count($row['items']);
    $row['payments_count']   = count($row['payments']);

    $data[] = $row;
}

echo json_encode([
    'ok'           => true,
    'shop_id'      => $shopId,
    'shop_name'    => $shopName,
    'access_token' => $shopAccessToken,
    'count'        => count($data),
    'data'         => $data,
], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'message' => 'Sale invoices API error: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
