<?php
require_once __DIR__ . '/../includes/session_init.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/jewelry_catalogue_create_include.php';

header('Content-Type: application/json; charset=utf-8');
ob_start();

try {
    $authed = (isset($_SESSION['Admin']['id']) && (int) $_SESSION['Admin']['id'] > 0)
        || (isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0);
    if (!$authed) {
        throw new RuntimeException('Unauthorized');
    }

    $designNo = isset($_GET['design_no']) ? trim((string) $_GET['design_no']) : '';
    $catalogueId = isset($_GET['catalogue_id']) ? (int) $_GET['catalogue_id'] : 0;
    if ($designNo === '' && isset($_GET['id'])) {
        $catalogueId = (int) $_GET['id'];
    }

    if ($designNo === '' && $catalogueId <= 0) {
        throw new RuntimeException('Design No. is required.');
    }

    if (!$conn instanceof mysqli) {
        throw new RuntimeException('Database unavailable.');
    }

    $siteUrl = isset($SiteUrl) ? (string) $SiteUrl : '';
    $result = auragold_jewelry_catalogue_get_for_modal($conn, $designNo, $catalogueId, $siteUrl);
    if (!$result) {
        throw new RuntimeException('Catalogue not found for this Design No.');
    }

    $payload = [
        'success' => true,
        'modal_rows' => $result['modal_rows'] ?? [],
        'design_no' => (string) ($result['design_no'] ?? ''),
        'title' => (string) ($result['title'] ?? ''),
        'metal_id' => (int) ($result['metal_id'] ?? 0),
        'weight' => (string) ($result['weight'] ?? ''),
        'amount' => (string) ($result['amount'] ?? ''),
        'group_image' => (string) ($result['group_image'] ?? ''),
        'details' => auragold_jewelry_catalogue_view_details_payload(
            $conn,
            is_array($result['catalogue'] ?? null) ? $result['catalogue'] : [],
            $siteUrl
        ),
    ];

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        throw new RuntimeException('Could not encode catalogue data.');
    }

    ob_end_clean();
    echo $json;
} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Could not load catalogue design.',
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}
