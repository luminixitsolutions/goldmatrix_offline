<?php
/**
 * Public share landing page for Jewellery Catalogue items (WhatsApp / social previews).
 * Shows full catalogue details — no login required; share link works for any active design.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/jewelry_catalogue_create_include.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$catalogue = ($id > 0 && isset($conn) && $conn instanceof mysqli)
    ? auragold_jewelry_catalogue_load_by_id($conn, $id)
    : null;

if (!$catalogue) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>Design not found</title></head><body><p>Catalogue design not found.</p></body></html>';
    exit;
}

$siteUrl = isset($SiteUrl) ? (string) $SiteUrl : '';
$details = auragold_jewelry_catalogue_view_details_payload($conn, $catalogue, $siteUrl);
$detailsHtml = auragold_jewelry_catalogue_render_share_page_body($conn, $catalogue, $siteUrl);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script = $_SERVER['SCRIPT_NAME'] ?? '/jewelry-catalogue-share.php';
$canonical = $scheme . '://' . $host . $script . '?id=' . $id;

$product = (string) ($details['product_name'] ?? 'Jewellery Design');
$designNo = (string) ($details['design_no'] ?? '');
$metal = (string) ($details['metal_name'] ?? '');
$weight = (string) ($details['weight_label'] ?? '');
$amount = (string) ($details['amount_label'] ?? '');
$ogTitle = $product . ' — GoldMatrix Jewellery Catalogue';
$ogDesc = trim(implode(' | ', array_filter([
    $designNo !== '' ? ('Design ' . $designNo) : '',
    $metal !== '' ? $metal : '',
    $weight !== '' ? ($weight . ' g') : '',
    $amount !== '' ? $amount : '',
])));

$ogImage = '';
if (!empty($details['image_urls']) && is_array($details['image_urls'])) {
    $ogImage = trim((string) $details['image_urls'][0]);
}
if ($ogImage !== '' && !preg_match('/^https?:\/\//i', $ogImage)) {
    $base = rtrim($siteUrl, '/');
    $ogImage = ($base !== '' ? $base . '/' : '/') . ltrim($ogImage, '/');
}

$appName = function_exists('auragold_app_name') ? auragold_app_name() : 'GoldMatrix';
$pageTitle = $product;
if ($designNo !== '') {
    $pageTitle = $product . ' — ' . $designNo;
}

$cssFile = __DIR__ . '/assets/css/jewelry-catalogue.css';
$cssVer = is_file($cssFile) ? (int) filemtime($cssFile) : time();

function jcat_share_h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo jcat_share_h($ogTitle); ?></title>
    <meta name="description" content="<?php echo jcat_share_h($ogDesc); ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?php echo jcat_share_h($appName); ?>">
    <meta property="og:title" content="<?php echo jcat_share_h($ogTitle); ?>">
    <meta property="og:description" content="<?php echo jcat_share_h($ogDesc); ?>">
    <meta property="og:url" content="<?php echo jcat_share_h($canonical); ?>">
<?php if ($ogImage !== '') { ?>
    <meta property="og:image" content="<?php echo jcat_share_h($ogImage); ?>">
    <meta property="og:image:secure_url" content="<?php echo jcat_share_h($ogImage); ?>">
    <meta property="og:image:type" content="image/jpeg">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="<?php echo jcat_share_h($ogImage); ?>">
<?php } ?>
    <link rel="stylesheet" href="assets/css/jewelry-catalogue.css?v=<?php echo $cssVer; ?>">
    <style>
        body.jcat-share-page {
            margin: 0;
            background: var(--jcat-bg, #fbfaf7);
            color: var(--jcat-navy, #071a34);
            font-family: var(--jcat-font-sans, system-ui, sans-serif);
        }
        .jcat-share-shell {
            max-width: 920px;
            margin: 0 auto;
            padding: 24px 16px 40px;
        }
        .jcat-share-card {
            background: #fff;
            border: 1px solid var(--jcat-border, #ebe4d7);
            border-radius: 18px;
            box-shadow: var(--jcat-shadow-card);
            overflow: hidden;
        }
        .jcat-share-head {
            padding: 18px 20px;
            border-bottom: 1px solid var(--jcat-border, #ebe4d7);
            background: linear-gradient(180deg, #fff 0%, #faf8f3 100%);
        }
        .jcat-share-kicker {
            margin: 0 0 4px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--jcat-gold-dark, #a8842f);
        }
        .jcat-share-title {
            margin: 0;
            font-size: 1.35rem;
            line-height: 1.25;
            color: var(--jcat-navy, #071a34);
        }
        .jcat-share-body {
            padding: 18px 20px 22px;
        }
        .jcat-share-body .jcat-details-layout {
            margin-bottom: 0;
        }
        @media (max-width: 767px) {
            .jcat-share-shell { padding: 12px 10px 28px; }
            .jcat-share-head,
            .jcat-share-body { padding-left: 14px; padding-right: 14px; }
        }
    </style>
</head>
<body class="jcat-share-page">
<div class="jcat-share-shell">
    <div class="jcat-share-card">
        <header class="jcat-share-head">
            <p class="jcat-share-kicker"><?php echo jcat_share_h($appName); ?> Jewellery Catalogue</p>
            <h1 class="jcat-share-title"><?php echo jcat_share_h($pageTitle); ?></h1>
        </header>
        <div class="jcat-share-body">
            <?php echo $detailsHtml; ?>
        </div>
    </div>
</div>
</body>
</html>
