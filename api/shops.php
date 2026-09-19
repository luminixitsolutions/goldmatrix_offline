<?php
/**
 * Shop list API — main branches only (main_branch_id = 0).
 *
 * GET /api/shops.php              (public — JSON response)
 * GET /api/shops.php?debug=1      (HTML print_r for testing)
 *
 * DB connection uses config.php clone-source settings:
 *   $auragold_schema_clone_source_db
 *   $auragold_clone_source_mysql_user
 *   $auragold_clone_source_mysql_pass
 * If those are empty, falls back to the main app DB / credentials (same as clone logic).
 *
 * Each shop includes a fixed access_token (auto-created once on tbl_branches if missing).
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auragold_api_shop_connection.php';
require_once dirname(__DIR__) . '/includes/auragold_access_token.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (!function_exists('auragold_shops_api_json_error')) {
    function auragold_shops_api_json_error(int $status, string $message): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('auragold_shops_api_debug_print')) {
    function auragold_shops_api_debug_print(array $response): void
    {
        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        echo '<pre>';
        print_r($response);
        echo '</pre>';
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    auragold_shops_api_json_error(405, 'Method not allowed. Use GET.');
}

$link = auragold_api_connect_clone_source();
if (!$link) {
    auragold_shops_api_json_error(500, 'Could not connect to shop database.');
}

auragold_ensure_tbl_branches_access_token_column($link);

$sql = 'SELECT * FROM tbl_branches WHERE main_branch_id = 0 ORDER BY id ASC';
$rs  = mysqli_query($link, $sql);
if (!$rs) {
    $err = mysqli_error($link);
    auragold_api_close_meta_link($link);
    auragold_shops_api_json_error(500, 'Query failed' . ($err !== '' ? ': ' . $err : '.'));
}

$data = [];
while ($row = mysqli_fetch_assoc($rs)) {
    $shopId = (int) ($row['id'] ?? 0);
    $token  = trim((string) ($row['access_token'] ?? ''));
    if ($token === '' && $shopId > 0) {
        $token = auragold_ensure_shop_access_token($link, $shopId);
    }
    if ($token === '') {
        continue;
    }
    $contact = auragold_api_resolve_shop_list_contact($row);
    $data[$token] = [
        'id'           => $shopId,
        'name'         => (string) ($row['name'] ?? ''),
        'email'        => $contact['email'],
        'phone'        => $contact['phone'],
        'access_token' => $token,
    ];
}
mysqli_free_result($rs);
auragold_api_close_meta_link($link);

$response = [
    'ok'    => 1,
    'count' => count($data),
    'data'  => $data,
];

$jsonResponse = json_encode($response, JSON_UNESCAPED_UNICODE);
if ($jsonResponse === false || $jsonResponse === '') {
    auragold_shops_api_json_error(500, 'Failed to encode API response.');
}

$decoded = json_decode($jsonResponse, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    auragold_shops_api_json_error(500, 'Invalid JSON response: ' . json_last_error_msg());
}

if (!isset($decoded['data']) || !is_array($decoded['data'])) {
    auragold_shops_api_json_error(500, 'Invalid API response: data is missing.');
}

$debug = isset($_GET['debug']) && in_array(strtolower(trim((string) $_GET['debug'])), ['1', 'true', 'yes'], true);
if ($debug) {
    auragold_shops_api_debug_print($decoded);
}

header('Content-Type: application/json; charset=utf-8');
echo $jsonResponse;
