<?php
/**
 * Customers API — all saved customer data, scoped by shop access_token.
 *
 * Single shop (full tbl_customers rows + optional summary):
 *   GET /api/customers.php?access_token=SHOP_ACCESS_TOKEN
 *   GET /api/customers.php?access_token=…&id=123
 *   GET /api/customers.php?access_token=…&summary=0   (omit invoice/balance totals)
 *   GET /api/customers.php?access_token=…&type=all   (include Supplier, Employee, etc.)
 *
 * All shops token-wise (customers grouped by access_token):
 *   GET /api/customers.php
 *   GET /api/customers.php?summary=0
 *
 * By default only Customer Type = "Customer" records are returned.
 * Each customer includes: total_sale_invoice, total_sale_invoice_amount,
 * total_previous_balance_amount, total_purchase_invoice (and total_purchase_invoice_amount).
 *
 * Token sources: ?access_token=, ?shop_access_token=, ?token=,
 *                header X-Access-Token, header Authorization: Bearer …
 *
 * Use shop access_token from /api/shops.php (not shop_id, not user/My Profile token).
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/branch_credentials.php';
require_once dirname(__DIR__) . '/includes/auragold_api_shop_connection.php';
require_once dirname(__DIR__) . '/includes/auragold_access_token.php';
require_once dirname(__DIR__) . '/includes/auragold_api_customers.php';

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

$shopAccessToken = auragold_api_customers_resolve_access_token();
$filterId        = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($filterId <= 0 && isset($_GET['customer_id'])) {
    $filterId = (int) $_GET['customer_id'];
}
$filterType = trim((string) ($_GET['type'] ?? 'Customer'));
$includeSummary = !isset($_GET['summary'])
    || !in_array(strtolower(trim((string) $_GET['summary'])), ['0', 'false', 'no'], true);

$filters = [
    'id'               => $filterId,
    'type'             => $filterType,
    'include_summary'  => $includeSummary,
];

try {
    if ($shopAccessToken === '') {
        $result = auragold_api_customers_all_shops_token_wise($filters);
        if (empty($result['ok'])) {
            http_response_code(500);
            echo json_encode([
                'ok'      => false,
                'message' => (string) ($result['message'] ?? 'Failed to load customers.'),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode([
            'ok'    => true,
            'count' => (int) ($result['count'] ?? 0),
            'data'  => $result['data'] ?? [],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

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

    if (!auragold_api_customers_table_exists($link, 'tbl_customers')) {
        mysqli_close($link);
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'tbl_customers not found in shop database.']);
        exit;
    }

    $customers = auragold_api_customers_list_for_shop($link, $filters);
    mysqli_close($link);

    if ($filterId > 0 && count($customers) === 0) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Customer not found.']);
        exit;
    }

    echo json_encode([
        'ok'           => true,
        'shop_id'      => $shopId,
        'shop_name'    => $shopName,
        'access_token' => $shopAccessToken,
        'count'        => count($customers),
        'data'         => $customers,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'message' => 'Customers API error: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
