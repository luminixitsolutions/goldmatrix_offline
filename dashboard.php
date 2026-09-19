<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config.php';

// Require active user session; redirect to login if not logged in
if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/includes/branch_profile_schema.php';
require_once __DIR__ . '/includes/auragold_branch_data_scope.php';
require_once __DIR__ . '/includes/dashboard_currency_display.php';
require_once __DIR__ . '/includes/auragold_metal_rate_urls.php';
auragold_ensure_tbl_branches_profile_columns(isset($conn_master) ? $conn_master : null);

$dash_metal_rate_urls = [];
if (isset($conn) && $conn instanceof mysqli) {
    auragold_ensure_tbl_metal_rate_urls($conn);
    $dash_mru_branch = function_exists('auragold_settings_branch_id')
        ? (int) auragold_settings_branch_id()
        : 0;
    $dash_metal_rate_urls = auragold_get_metal_rate_urls($conn, $dash_mru_branch, true);
}
if ($dash_metal_rate_urls === []) {
    // Fallback to built-in seeds if table empty / unavailable
    foreach (auragold_metal_rate_url_default_seeds() as $seed) {
        $dash_metal_rate_urls[] = [
            'url' => (string) ($seed['url'] ?? ''),
            'label' => (string) ($seed['label'] ?? ''),
            'display' => trim((string) ($seed['label'] ?? '')) !== ''
                ? (string) $seed['label']
                : (string) ($seed['url'] ?? ''),
        ];
    }
}

/** Which branch’s dashboard rates to show and save (0 = legacy global rows). Branch logins are always scoped to their branch. */
$dash_rates_branch_id = isset($_GET['branch']) ? (int) $_GET['branch'] : 0;
$dash_effective_branch = function_exists('auragold_effective_branch_id') ? (int) auragold_effective_branch_id() : 0;
if ($dash_effective_branch > 0) {
    $dash_rates_branch_id = $dash_effective_branch;
}
$dash_branch_list = [];
if (isset($conn_master) && $conn_master && function_exists('getListMaster')) {
    $dash_branch_list = getListMaster('SELECT id, name FROM tbl_branches WHERE status = 1 ORDER BY name ASC');
}
if (!is_array($dash_branch_list)) {
    $dash_branch_list = [];
}

$currencies = getList("SELECT id, name, symbol, is_base FROM tbl_currency WHERE status = 1 ORDER BY is_base DESC, name ASC");
if (!is_array($currencies)) {
    $currencies = [];
}
$base_currency_row = auragold_dashboard_resolve_base_currency($currencies);
$base_currency_id = $base_currency_row ? (int) ($base_currency_row['id'] ?? 0) : 0;
$base_currency_label = $base_currency_row
    ? trim((string) ($base_currency_row['symbol'] ?? '')) ?: trim((string) ($base_currency_row['name'] ?? ''))
    : 'AED';
if ($base_currency_label === '') {
    $base_currency_label = 'AED';
}

$pref_currency_id = 0;
$dash_bid = auragold_dashboard_branch_id_for_currency_preferences();
if ($dash_bid > 0 && isset($conn_master) && $conn_master) {
    $brCur = @mysqli_query(
        $conn_master,
        'SELECT profile_base_currency_id FROM tbl_branches WHERE id = ' . (int) $dash_bid . ' LIMIT 1'
    );
    if ($brCur && ($brRow = mysqli_fetch_assoc($brCur))) {
        $pref_currency_id = (int) ($brRow['profile_base_currency_id'] ?? 0);
    }
    if ($brCur) {
        mysqli_free_result($brCur);
    }
}

$display_res = auragold_dashboard_resolve_display_currency($currencies, $base_currency_row, $base_currency_id, $pref_currency_id);
$display_currency_row = $display_res['row'];
$display_currency_id = (int) ($display_res['id'] ?? 0);

$currency = $display_currency_row
    ? trim((string) ($display_currency_row['symbol'] ?? '')) ?: trim((string) ($display_currency_row['name'] ?? ''))
    : 'AED';
if ($currency === '') {
    $currency = 'AED';
}

$dashboard_exchange_rates = auragold_dashboard_currency_exchange_map(isset($conn) ? $conn : null);
$dash_currency_payload = [
    'baseId'  => $base_currency_id,
    'rates'   => $dashboard_exchange_rates,
    'currencies' => array_map(static function ($c) {
        $sym = trim((string) ($c['symbol'] ?? ''));
        $nm = trim((string) ($c['name'] ?? ''));
        return [
            'id'     => (int) ($c['id'] ?? 0),
            'name'   => $nm,
            'symbol' => $sym !== '' ? $sym : $nm,
        ];
    }, $currencies),
    'displayCurrencyId' => $display_currency_id,
];
$rates_updated = date('d-m-Y  g:i A');

$dash_date_from = isset($_GET['date_from']) ? trim((string) $_GET['date_from']) : '';
$dash_date_to = isset($_GET['date_to']) ? trim((string) $_GET['date_to']) : '';
if ($dash_date_from === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dash_date_from)) {
    $dash_date_from = date('Y-m-d');
}
if ($dash_date_to === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dash_date_to)) {
    $dash_date_to = date('Y-m-d');
}
if ($dash_date_from > $dash_date_to) {
    $tmpD = $dash_date_from;
    $dash_date_from = $dash_date_to;
    $dash_date_to = $tmpD;
}

/** Metal-wise & carat-wise defaults; merged from tbl_dashboard_metal_rates when present. */
$dashboard_metals = [
    'gold' => [
        'label'       => 'Gold',
        'short'       => 'Au',
        'hero_class'  => 'metal-accent-gold',
        'source_url'  => 'https://igold.ae/gold-rate',
        'ounce_rate'  => '0',
        'headline_rate' => '544.57',
        'headline_carat' => '24K',
        'table_carat_label' => 'Carat / Purity',
        'rows' => [
            ['carat' => '24K', 'new_rate' => '544.57', 'sell_premium' => '—', 'conv' => '1', 'current' => '544.57'],
            ['carat' => '22K', 'new_rate' => '504.20', 'sell_premium' => '—', 'conv' => '1', 'current' => '504.20'],
            ['carat' => '21K', 'new_rate' => '481.26', 'sell_premium' => '—', 'conv' => '1', 'current' => '481.26'],
            ['carat' => '18K', 'new_rate' => '412.51', 'sell_premium' => '—', 'conv' => '1', 'current' => '412.51'],
            ['carat' => '14K', 'new_rate' => '321.50', 'sell_premium' => '—', 'conv' => '1', 'current' => '321.50'],
            ['carat' => '10K', 'new_rate' => '229.40', 'sell_premium' => '—', 'conv' => '1', 'current' => '229.40'],
        ],
        'cards' => [
            ['label' => '24K', 'value' => '544.57', 'class' => 'c24'],
            ['label' => '22K', 'value' => '504.20', 'class' => 'c22'],
            ['label' => '21K', 'value' => '481.26', 'class' => 'c21'],
            ['label' => '18K', 'value' => '412.51', 'class' => 'c18'],
            ['label' => '14K', 'value' => '321.50', 'class' => 'c14'],
            ['label' => '10K', 'value' => '229.40', 'class' => 'c10'],
        ],
    ],
    'silver' => [
        'label'       => 'Silver',
        'short'       => 'Ag',
        'hero_class'  => 'metal-accent-silver',
        'source_url'  => 'https://www.kitco.com/charts/livesilver.html',
        'ounce_rate'  => '0',
        'headline_rate' => '3.85',
        'headline_carat' => '999',
        'table_carat_label' => 'Purity',
        'rows' => [
            ['carat' => '999', 'new_rate' => '3.85', 'sell_premium' => '—', 'conv' => '1', 'current' => '3.85'],
            ['carat' => '958', 'new_rate' => '3.69', 'sell_premium' => '—', 'conv' => '1', 'current' => '3.69'],
            ['carat' => '925', 'new_rate' => '3.56', 'sell_premium' => '—', 'conv' => '1', 'current' => '3.56'],
            ['carat' => '875', 'new_rate' => '3.37', 'sell_premium' => '—', 'conv' => '1', 'current' => '3.37'],
        ],
        'cards' => [
            ['label' => '999', 'value' => '3.85', 'class' => 's999'],
            ['label' => '958', 'value' => '3.69', 'class' => 's958'],
            ['label' => '925', 'value' => '3.56', 'class' => 's925'],
            ['label' => '875', 'value' => '3.37', 'class' => 's875'],
        ],
    ],
    'platinum' => [
        'label'       => 'Platinum',
        'short'       => 'Pt',
        'hero_class'  => 'metal-accent-platinum',
        'source_url'  => '',
        'ounce_rate'  => '0',
        'headline_rate' => '0.00',
        'headline_carat' => '950',
        'table_carat_label' => 'Purity',
        'rows' => [
            ['carat' => '999', 'new_rate' => '0.00', 'sell_premium' => '—', 'conv' => '1', 'current' => '0.00'],
            ['carat' => '950', 'new_rate' => '0.00', 'sell_premium' => '—', 'conv' => '1', 'current' => '0.00'],
            ['carat' => '900', 'new_rate' => '0.00', 'sell_premium' => '—', 'conv' => '1', 'current' => '0.00'],
        ],
        'cards' => [
            ['label' => '999', 'value' => '0.00', 'class' => 'pt999'],
            ['label' => '950', 'value' => '0.00', 'class' => 'pt950'],
            ['label' => '900', 'value' => '0.00', 'class' => 'pt900'],
        ],
    ],
    'diamond' => [
        'label'       => 'Diamond',
        'short'       => 'Di',
        'hero_class'  => 'metal-accent-diamond',
        'source_url'  => '—',
        'ounce_rate'  => '—',
        'headline_rate' => '12,500',
        'headline_carat' => '1.00 ct RAP',
        'table_carat_label' => 'Size / Carat',
        'rows' => [
            ['carat' => '0.30 ct', 'new_rate' => '2,800', 'sell_premium' => '—', 'conv' => '1', 'current' => '2,800'],
            ['carat' => '0.50 ct', 'new_rate' => '5,200', 'sell_premium' => '—', 'conv' => '1', 'current' => '5,200'],
            ['carat' => '0.70 ct', 'new_rate' => '7,900', 'sell_premium' => '—', 'conv' => '1', 'current' => '7,900'],
            ['carat' => '1.00 ct', 'new_rate' => '12,500', 'sell_premium' => '—', 'conv' => '1', 'current' => '12,500'],
            ['carat' => '1.50 ct', 'new_rate' => '21,000', 'sell_premium' => '—', 'conv' => '1', 'current' => '21,000'],
            ['carat' => '2.00 ct', 'new_rate' => '38,000', 'sell_premium' => '—', 'conv' => '1', 'current' => '38,000'],
        ],
        'cards' => [
            ['label' => '0.30', 'value' => '2,800', 'class' => 'd03'],
            ['label' => '0.50', 'value' => '5,200', 'class' => 'd05'],
            ['label' => '0.70', 'value' => '7,900', 'class' => 'd07'],
            ['label' => '1.00', 'value' => '12,500', 'class' => 'd10'],
            ['label' => '1.50', 'value' => '21,000', 'class' => 'd15'],
            ['label' => '2.00', 'value' => '38,000', 'class' => 'd20'],
        ],
    ],
];

require_once __DIR__ . '/includes/dashboard_carat_master.php';
require_once __DIR__ . '/includes/auragold_dashboard_metal_images.php';
if (isset($conn) && $conn) {
    auragold_dashboard_apply_carat_master_rows($conn, $dashboard_metals);
}

require_once __DIR__ . '/includes/dashboard_metal_rates_db.php';
$__db_rates = auragold_load_dashboard_metals_from_db(isset($conn) ? $conn : null, $dashboard_metals, $dash_rates_branch_id);
$dashboard_metals = $__db_rates['metals'];
if (!empty($__db_rates['rates_updated'])) {
    $rates_updated = $__db_rates['rates_updated'];
}

if (isset($conn) && $conn) {
    require_once __DIR__ . '/includes/auragold_metal_dashboard_image_schema.php';
    auragold_ensure_tbl_metal_dashboard_images($conn);
    $dashboard_metals = auragold_dashboard_filter_metals_by_master_visibility($conn, $dashboard_metals);
}

/** Hero metal thumbs: only Masters images (Carat / Metal upload or URL). No stock placeholders. */
$dash_dashboard_metal_img_urls = [];
if (isset($conn) && $conn) {
    $dash_dashboard_metal_img_urls = array_merge(
        auragold_dashboard_metal_images_from_carats($conn),
        auragold_dashboard_metal_images_from_tbl_metal($conn)
    );
}

/** Dashboard has no DataTables tables — skip heavy CDN table libraries on this page. */
$AURAGOLD_USE_DATATABLES = false;
$AURAGOLD_USE_DATATABLE_BUTTONS = false;
?>
<!DOCTYPE html>
<html lang="en" class="default-style">

<head>
    <title><?php echo htmlspecialchars('Dashboard — ' . auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0, user-scalable=no, 
          minimum-scale=1.0, maximum-scale=1.0">

    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
<?php include 'header-script.php';?>
<?php auragold_echo_stylesheet('assets/libs/select2/select2.css'); ?>
    <style>
        html.default-style, html.default-style body {
            background: linear-gradient(160deg, #f3f1ec 0%, #eef1f6 42%, #f7f5f1 100%) !important;
            min-height: 100vh;
        }
        .layout-wrapper.layout-2 {
            min-height: 100vh;
            background: transparent;
        }
        .layout-content {
            min-height: calc(100vh - 48px) !important;
            width: 100% !important;
            max-width: 100% !important;
        }
        .dash-mobile-root {
            --dash-ink: #1a2332;
            --dash-ink-soft: #3d4a5c;
            --dash-gold: #b8954a;
            --dash-gold-soft: #f4ead4;
            --dash-gold-deep: #8a6b2e;
            --dash-line: #e8e4dc;
            --dash-muted: #6b7280;
            --dash-surface: #ffffff;
            --dash-shadow: 0 1px 2px rgba(26, 35, 50, 0.04), 0 10px 28px rgba(26, 35, 50, 0.07);
        }

        .dash-hero {
            position: relative;
            border-radius: 24px;
            padding: 28px 32px;
            margin-bottom: 24px;
            overflow: hidden;
            background: linear-gradient(125deg, #1a1f35 0%, #2d3560 48%, #1f2847 100%);
            box-shadow: 0 20px 50px rgba(26, 31, 53, 0.35);
            color: #fff;
        }
        .dash-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 90% 20%, rgba(212, 175, 55, 0.18), transparent 55%),
                radial-gradient(ellipse 60% 50% at 10% 80%, rgba(147, 197, 253, 0.12), transparent 50%);
            pointer-events: none;
        }
        .dash-hero-inner { position: relative; z-index: 1; }
        .dash-hero h1 {
            font-size: 1.65rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            margin: 0 0 6px 0;
        }
        .dash-hero p {
            margin: 0;
            opacity: 0.88;
            font-size: 0.95rem;
        }
        .dash-hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 14px;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(255,255,255,0.1);
            font-size: 0.85rem;
            backdrop-filter: blur(8px);
        }
        .dash-currency-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            margin-top: 14px;
        }
        .dash-currency-bar label {
            margin: 0;
            font-size: 0.88rem;
            opacity: 0.92;
        }
        .dash-currency-bar select {
            max-width: 260px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.35);
            background: rgba(255,255,255,0.12);
            color: #fff;
            font-size: 0.9rem;
            padding: 6px 10px;
        }
        .dash-currency-bar select option {
            color: #1a1f35;
        }

        .dash-summary-row {
            margin-bottom: 16px;
        }
        .dash-summary-card {
            border-radius: 16px;
            padding: 16px 18px;
            color: #fff;
            position: relative;
            overflow: hidden;
            box-shadow: var(--dash-shadow);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            height: 100%;
            min-height: 128px;
            border: 1px solid rgba(255,255,255,0.12);
        }
        .dash-summary-card::after {
            content: '';
            position: absolute;
            top: -30%;
            right: -12%;
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            pointer-events: none;
        }
        .dash-summary-card:has(.dash-summary-metal-img) {
            padding-right: 78px;
        }
        .dash-summary-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 32px rgba(26, 35, 50, 0.16);
        }
        .dash-summary-card.metal-accent-gold {
            background: linear-gradient(145deg, #d4af37 0%, #b8954a 48%, #8a6b2e 100%);
        }
        .dash-summary-card.metal-accent-silver {
            background: linear-gradient(145deg, #9aa8b8 0%, #6b7c8f 50%, #4a5a6f 100%);
        }
        .dash-summary-card.metal-accent-diamond {
            background: linear-gradient(145deg, #6b8fb5 0%, #4a6f94 50%, #355675 100%);
        }
        .dash-summary-card.metal-accent-platinum {
            background: linear-gradient(145deg, #a8b4c4 0%, #7d8b9c 50%, #5b6878 100%);
        }
        .dash-summary-card.metal-accent-other {
            background: linear-gradient(145deg, #6d7a8c 0%, #475569 100%);
        }
        .dash-summary-card .dash-metal-icon-wrap {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.22);
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
            z-index: 1;
            backdrop-filter: blur(4px);
        }
        .dash-summary-card .dash-metal-icon {
            font-size: 1.2rem;
            opacity: 0.98;
            line-height: 1;
            color: #fff;
        }
        .dash-summary-card .dash-summary-metal-img {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 48px;
            height: 48px;
            max-width: 48px;
            max-height: 48px;
            object-fit: contain;
            pointer-events: none;
            opacity: 0.95;
            filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.22));
            flex-shrink: 0;
            z-index: 1;
        }
        .dash-summary-card .sym {
            font-size: 0.68rem;
            opacity: 0.88;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            font-weight: 650;
        }
        .dash-summary-card .metal-name {
            font-weight: 700;
            font-size: 1rem;
            margin-top: 3px;
            letter-spacing: -0.01em;
        }
        .dash-summary-card .big-rate {
            font-size: 1.55rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            margin-top: 10px;
            line-height: 1.15;
        }
        .dash-summary-card .sub {
            font-size: 0.72rem;
            opacity: 0.88;
            margin-top: 5px;
        }

        .dash-page-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px 16px;
            flex-wrap: wrap;
            margin-bottom: 14px;
            padding: 14px 16px;
            background: var(--dash-surface);
            border: 1px solid var(--dash-line);
            border-radius: 16px;
            box-shadow: var(--dash-shadow);
        }
        .dash-page-toolbar .dash-toolbar-left {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 2px;
            min-width: 0;
        }
        .dash-page-toolbar .dash-toolbar-greeting {
            font-size: 12px;
            color: var(--dash-gold-deep);
            font-weight: 650;
            letter-spacing: 0.02em;
        }
        .dash-page-toolbar .dash-toolbar-title {
            font-size: 1.25rem;
            font-weight: 750;
            color: var(--dash-ink);
            margin: 0;
            letter-spacing: -0.03em;
            line-height: 1.2;
        }
        .dash-page-toolbar .dash-branch-chip {
            font-size: 12px;
            color: var(--dash-muted);
            font-weight: 600;
            margin-top: 2px;
        }
        .dash-page-toolbar .dash-branch-chip strong { color: var(--dash-ink-soft); }
        .dash-page-toolbar .dash-toolbar-meta {
            font-size: 11px;
            color: var(--dash-muted);
        }
        .dash-page-toolbar form.dash-date-filter {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            margin: 0;
            padding: 6px 8px;
            background: #faf9f6;
            border: 1px solid var(--dash-line);
            border-radius: 12px;
        }
        .dash-page-toolbar form.dash-date-filter input[type="date"] {
            height: 34px;
            width: 134px;
            border: 1px solid var(--dash-line);
            border-radius: 8px;
            padding: 0 8px;
            font-size: 12px;
            color: var(--dash-ink);
            background: #fff;
        }
        .dash-page-toolbar form.dash-date-filter select.form-control {
            height: 34px;
            max-width: 200px;
            font-size: 12px;
            border-radius: 8px;
            border-color: var(--dash-line);
            padding: 0 8px;
            background: #fff;
        }
        .dash-page-toolbar .btn-dash-apply {
            height: 34px;
            padding: 0 14px;
            border: none;
            border-radius: 8px;
            background: linear-gradient(135deg, #c9a864 0%, #b8954a 100%);
            color: #1a160c;
            font-weight: 750;
            font-size: 12px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(184, 149, 74, 0.28);
        }
        .dash-page-toolbar .btn-dash-today {
            height: 34px;
            padding: 0 12px;
            border-radius: 8px;
            border: 1px solid var(--dash-line);
            background: #fff;
            color: var(--dash-ink-soft);
            font-weight: 650;
            font-size: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        .dash-page-toolbar .btn-dash-today:hover { color: var(--dash-ink); text-decoration: none; }

        .white-box {
            background: #fff;
            border-radius: 16px;
            box-shadow: var(--dash-shadow);
            border: 1px solid var(--dash-line);
            padding: 18px 20px;
        }

        .sec-title {
            font-weight: 700;
            color: var(--dash-ink);
            font-size: 1rem;
            margin-bottom: 14px;
            letter-spacing: -0.01em;
        }

        .label-small {
            font-size: 13px;
            color: var(--dash-muted);
            font-weight: 600;
        }

        .input-box {
            border-radius: 10px;
            border: 1px solid var(--dash-line);
            height: 40px;
        }

        .table { font-size: 14px; }
        .table th {
            background: #faf9f6 !important;
            font-weight: 700;
            color: var(--dash-ink-soft);
            vertical-align: middle;
            border-bottom: 1px solid var(--dash-line) !important;
        }
        .table-striped tbody tr:nth-of-type(odd) {
            background: #fcfbf9;
        }

        .icon-btn {
            background: var(--dash-gold-soft);
            border: none;
            border-radius: 10px;
            padding: 5px 10px;
            font-size: 16px;
            color: var(--dash-gold-deep);
            cursor: pointer;
        }
        .icon-btn:hover { background: #efe0be; }

        .btn-save {
            background: linear-gradient(135deg, #1a2332 0%, #2c3a4f 100%);
            color: #fff;
            border-radius: 10px;
            border: none;
            padding: 8px 22px;
            font-weight: 650;
            box-shadow: 0 6px 16px rgba(26, 35, 50, 0.2);
        }
        .btn-save:hover { filter: brightness(1.06); color: #fff; }

        .rate-card {
            background: #fff;
            border-radius: 14px;
            padding: 14px 16px;
            box-shadow: 0 2px 10px rgba(26, 35, 50, 0.05);
            border: 1px solid var(--dash-line);
            display: flex;
            align-items: center;
            gap: 14px;
            min-height: 88px;
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
        }
        .rate-card > .dash-snap-carat-img {
            width: 48px;
            height: 48px;
            object-fit: contain;
            flex-shrink: 0;
            pointer-events: none;
        }
        .rate-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--dash-shadow);
            border-color: #d8cfae;
        }

        .circle {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            border: 2.5px solid;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 14px;
            font-weight: 800;
            flex-shrink: 0;
            background: #faf9f6;
        }

        .c24{border-color:#7fb4c9;color:#4a707e}
        .c22{border-color:#e0b147;color:#bc8f25}
        .c21{border-color:#4aa772;color:#2b6f4e}
        .c18{border-color:#5a9c5b;color:#316d36}
        .c14{border-color:#d4a629;color:#b08718}
        .c10{border-color:#d15063;color:#aa2f44}

        .s999{border-color:#c0cad6;color:#5a6575}
        .s958{border-color:#9eb0c4;color:#4a5a6a}
        .s925{border-color:#7a9ab8;color:#3d5a75}
        .s875{border-color:#6b8fa3;color:#2f4d5c}

        .pt999{border-color:#cbd5e1;color:#475569}
        .pt950{border-color:#94a3b8;color:#334155}
        .pt900{border-color:#64748b;color:#1e293b}

        .circle[class^="cc-"] { border-color: #94a3b8; color: #475569; }

        .d03{border-color:#93c5fd;color:#2563eb}
        .d05{border-color:#7dd3fc;color:#0284c7}
        .d07{border-color:#67e8f9;color:#0e7490}
        .d10{border-color:#a5b4fc;color:#4f46e5}
        .d15{border-color:#c4b5fd;color:#6d28d9}
        .d20{border-color:#e9d5ff;color:#7c3aed}

        .cc-other{border-color:#64748b;color:#334155}

        .card-value { font-size: 20px; font-weight: 700; color: #2c3b60; }
        .card-date { font-size: 12px; color: #868686; margin-top: 4px; }
        .card-unit { font-size: 11px; color: #9ca3af; margin-top: 2px; }

        .metal-tabs-wrap {
            background: #fff;
            border-radius: 16px;
            padding: 10px 12px 0;
            box-shadow: var(--dash-shadow);
            border: 1px solid var(--dash-line);
            margin-bottom: 16px;
        }
        /*
         * Theme (shreerang-material.css) sets .nav-tabs .nav-link:not(.active) { color: #fff } for dark navbars.
         * On this white card that hides Gold/Silver — only the active tab looked visible.
         */
        .metal-tabs-wrap .nav-tabs .nav-link:not(.active) {
            color: #5c6478 !important;
        }
        .metal-tabs-wrap .nav-tabs .nav-link:not(.active):hover,
        .metal-tabs-wrap .nav-tabs .nav-link:not(.active):focus {
            color: #1a2332 !important;
        }
        .metal-tabs-wrap .nav-link {
            border-radius: 11px !important;
            padding: 11px 18px;
            font-weight: 650;
            color: #5c6478;
            border: none !important;
            margin: 0 3px 8px;
            transition: background .15s ease, color .15s ease, box-shadow .15s ease;
        }
        .metal-tabs-wrap .nav-link:hover { color: #1a2332; background: #faf9f6; }
        .metal-tabs-wrap .nav-link.active {
            color: #fff !important;
            background: linear-gradient(135deg, #1a2332 0%, #2c3a4f 100%) !important;
            box-shadow: 0 6px 16px rgba(26, 35, 50, 0.22);
        }
        .metal-tabs-wrap .nav-item-gold .nav-link.active {
            background: linear-gradient(135deg, #c9a864 0%, #b8954a 100%) !important;
            color: #1a160c !important;
            box-shadow: 0 6px 16px rgba(184, 149, 74, 0.35);
        }
        .metal-tabs-wrap .nav-item-silver .nav-link.active {
            background: linear-gradient(135deg, #7c8ea3 0%, #5a6b7e 100%) !important;
            box-shadow: 0 6px 16px rgba(100, 116, 139, 0.28);
        }
        .metal-tabs-wrap .nav-item-diamond .nav-link.active,
        .metal-tabs-wrap .nav-item-platinum .nav-link.active {
            background: linear-gradient(135deg, #1a2332 0%, #3d4a5c 100%) !important;
            box-shadow: 0 6px 16px rgba(26, 35, 50, 0.22);
        }

        .rate-input {
            max-width: 118px;
            margin-left: auto;
            margin-right: auto;
            font-weight: 600;
        }
        .rate-hint-gold {
            font-size: 13px;
            color: var(--dash-ink-soft);
            line-height: 1.45;
            margin-bottom: 10px;
            padding: 12px 14px;
            background: linear-gradient(135deg, #fffdf8 0%, #f4ead4 100%);
            border-radius: 12px;
            border: 1px solid #e6d7b0;
        }

        .metal-tabs-wrap .nav-link i {
            margin-right: 6px;
            opacity: 0.92;
        }

        .dash-gold-analytics-card {
            background: #fff !important;
            border: 1px solid var(--dash-line) !important;
            box-shadow: var(--dash-shadow) !important;
        }
        .dash-gold-analytics-side {
            height: 100%;
        }
        .dash-gold-analytics-side .dash-gold-chart-wrap {
            height: 220px;
        }
        .dash-gold-analytics-side .dash-stat-pill {
            height: 100%;
        }
        #dashMetalSnapshotBar.is-hidden {
            display: none;
        }
        .dash-analytics-title {
            font-size: 1.05rem;
            font-weight: 750;
            color: var(--dash-ink);
            letter-spacing: -0.02em;
        }
        .dash-stat-pill {
            background: #faf9f6;
            border-radius: 14px;
            padding: 14px 16px;
            border: 1px solid var(--dash-line);
            box-shadow: none;
        }
        .dash-stat-pill .label {
            display: block;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--dash-muted);
            font-weight: 650;
            margin-bottom: 6px;
        }
        .dash-stat-pill .value {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--dash-ink);
            letter-spacing: -0.02em;
        }
        .dash-stat-pill .value.positive { color: #15803d; }
        .dash-stat-pill .value.negative { color: #b91c1c; }
        .dash-gold-chart-wrap {
            position: relative;
            height: 260px;
        }
        .dash-gold-chart-mode .btn {
            border-radius: 10px !important;
            font-weight: 600;
            font-size: 0.82rem;
        }
        .dash-gold-chart-mode .btn.active {
            background: linear-gradient(135deg, #c9a227 0%, #a16207 100%) !important;
            color: #fff !important;
            border-color: transparent !important;
        }

        .dash-rate-form-card {
            border: 1px solid rgba(99, 102, 241, 0.12) !important;
            background: linear-gradient(180deg, #ffffff 0%, #fafbff 100%) !important;
        }
        .dash-rate-form-head {
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 1px solid #eef0f7;
        }
        .dash-rate-form-head h3 {
            font-size: 1.15rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0 0 4px 0;
        }
        .dash-rate-form-head p {
            margin: 0;
            font-size: 0.88rem;
            color: #64748b;
        }
        .dash-rate-conversions {
            margin-bottom: 18px;
        }
        .dash-rate-conv-card {
            background: linear-gradient(135deg, #fffdf8 0%, #ffffff 52%, #f8fafc 100%);
            border: 1px solid rgba(184, 149, 74, 0.22);
            border-radius: 16px;
            padding: 16px 18px 14px;
            box-shadow: 0 4px 22px rgba(26, 35, 50, 0.07);
        }
        .dash-rate-conv-header {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 12px;
        }
        .dash-rate-conv-badge {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: linear-gradient(145deg, #d4af37 0%, #b8954a 55%, #8a6b2e 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            box-shadow: 0 4px 14px rgba(184, 149, 74, 0.38);
            flex-shrink: 0;
        }
        .dash-rate-conv-titles label {
            display: block;
            font-weight: 750;
            font-size: 1rem;
            color: #1a2332;
            margin: 0 0 2px 0;
            letter-spacing: -0.01em;
        }
        .dash-rate-conv-sub {
            display: block;
            font-size: 0.8rem;
            color: #64748b;
            line-height: 1.35;
        }
        .dash-rate-conv-select-wrap {
            position: relative;
        }
        .dash-rate-conv-select-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 2;
            color: #b8954a;
            font-size: 1.05rem;
            pointer-events: none;
        }
        .dash-rate-conv-select-wrap .select2-container {
            width: 100% !important;
        }
        .dash-rate-conv-select-wrap .select2-container--default .select2-selection--single {
            height: 48px;
            border: 2px solid rgba(184, 149, 74, 0.24);
            border-radius: 12px;
            background: linear-gradient(180deg, #ffffff 0%, #fffef9 100%);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .dash-rate-conv-select-wrap .select2-container--default.select2-container--focus .select2-selection--single,
        .dash-rate-conv-select-wrap .select2-container--default.select2-container--open .select2-selection--single {
            border-color: #b8954a;
            box-shadow: 0 0 0 4px rgba(184, 149, 74, 0.14);
        }
        .dash-rate-conv-select-wrap .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 44px;
            padding-left: 42px;
            padding-right: 36px;
            color: #1a2332;
            font-weight: 600;
            font-size: 0.92rem;
        }
        .dash-rate-conv-select-wrap .select2-container--default .select2-selection--single .select2-selection__placeholder {
            color: #94a3b8;
            font-weight: 500;
        }
        .dash-rate-conv-select-wrap .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 46px;
            width: 38px;
        }
        .dash-rate-conv-select-wrap .select2-container--default .select2-selection--single .select2-selection__arrow b {
            border-color: #b8954a transparent transparent transparent;
            border-width: 6px 5px 0 5px;
        }
        .dash-rate-conv-select-wrap .select2-container--default.select2-container--open .select2-selection--single .select2-selection__arrow b {
            border-color: transparent transparent #b8954a transparent;
            border-width: 0 5px 6px 5px;
        }
        .dash-rate-conv-select-wrap .select2-container--default .select2-selection--single .select2-selection__clear {
            color: #b8954a;
            font-size: 1.1rem;
            margin-right: 6px;
        }
        .dash-rate-conv-select-wrap .select2-container--default.select2-container--disabled .select2-selection--single {
            background: #f8fafc;
            border-color: rgba(148, 163, 184, 0.35);
            opacity: 0.85;
            cursor: not-allowed;
        }
        .select2-dropdown.dash-rate-conv-dropdown {
            border: 2px solid rgba(184, 149, 74, 0.2);
            border-radius: 12px;
            box-shadow: 0 14px 40px rgba(26, 35, 50, 0.14);
            overflow: hidden;
            margin-top: 4px;
            z-index: 1060 !important;
        }
        .select2-dropdown.dash-rate-conv-dropdown .select2-search--dropdown {
            padding: 10px 12px 8px;
            background: #fffdf8;
            border-bottom: 1px solid rgba(184, 149, 74, 0.12);
        }
        .select2-dropdown.dash-rate-conv-dropdown .select2-search__field {
            border: 1px solid rgba(184, 149, 74, 0.25);
            border-radius: 8px;
            padding: 8px 10px;
            font-size: 0.88rem;
        }
        .select2-dropdown.dash-rate-conv-dropdown .select2-search__field:focus {
            border-color: #b8954a;
            outline: none;
            box-shadow: 0 0 0 3px rgba(184, 149, 74, 0.12);
        }
        .select2-dropdown.dash-rate-conv-dropdown .select2-results__option {
            padding: 10px 16px;
            font-size: 0.9rem;
            color: #334155;
            transition: background 0.15s ease, color 0.15s ease;
        }
        .select2-dropdown.dash-rate-conv-dropdown .select2-results__option--highlighted[aria-selected] {
            background: linear-gradient(90deg, #f4ead4 0%, #fff9ed 100%);
            color: #8a6b2e;
        }
        .select2-dropdown.dash-rate-conv-dropdown .select2-results__option[aria-selected=true] {
            background: #fffdf8;
            color: #b8954a;
            font-weight: 650;
        }
        .dash-rate-conv-status {
            margin: 10px 0 0;
            min-height: 1.25rem;
            font-size: 0.82rem;
        }
        .dash-rate-conv-status.text-success {
            color: #166534 !important;
            font-weight: 600;
        }
        .dash-rate-conv-status.text-danger {
            color: #b91c1c !important;
            font-weight: 600;
        }
        .dash-rates-table {
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid #e8eaf2;
        }
        .dash-rates-table thead th {
            background: #faf9f6 !important;
            color: #3d4a5c !important;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 12px 10px !important;
            border-bottom: 1px solid #e8e4dc !important;
            vertical-align: middle !important;
        }
        .dash-rates-table tbody td {
            padding: 12px 10px !important;
            vertical-align: middle !important;
            border-color: #f0ebe3 !important;
        }
        .dash-rates-table tbody tr:hover {
            background: rgba(184, 149, 74, 0.06) !important;
        }
        .dash-rates-table tbody td:first-child {
            font-weight: 700;
            color: #3d4a5c;
        }
        .dash-rates-table .form-control {
            border-radius: 10px;
            border: 1px solid #e8e4dc;
            font-weight: 600;
        }
        .dash-rates-table .icon-btn {
            border-radius: 10px;
            background: #f4ead4;
            color: #8a6b2e;
        }
        .btn-dash-save-all {
            background: linear-gradient(135deg, #1a2332 0%, #2c3a4f 100%);
            color: #fff !important;
            border: none;
            border-radius: 12px;
            padding: 11px 24px;
            font-weight: 700;
            letter-spacing: 0.02em;
            box-shadow: 0 8px 20px rgba(26, 35, 50, 0.22);
        }
        .btn-dash-save-all:hover {
            filter: brightness(1.06);
            color: #fff !important;
        }

        .dash-metal-snapshot-bar {
            margin-bottom: 18px;
        }
        .dash-metal-snapshot-pane {
            display: none;
        }
        .dash-metal-snapshot-pane.active {
            display: block;
        }
        .today-rate-panel,
        .today-rate-panel.metal-snap-gold {
            border: 1px solid rgba(234, 179, 8, 0.2) !important;
            background: linear-gradient(165deg, #fffdf8 0%, #ffffff 45%, #fffef6 100%) !important;
        }
        .today-rate-panel.metal-snap-other {
            border-color: rgba(100, 116, 139, 0.3) !important;
            background: linear-gradient(165deg, #f8fafc 0%, #ffffff 45%, #f1f5f9 100%) !important;
        }
        .today-rate-panel.metal-snap-silver {
            border-color: rgba(148, 163, 184, 0.35) !important;
            background: linear-gradient(165deg, #f8fafc 0%, #ffffff 45%, #f1f5f9 100%) !important;
        }
        .today-rate-panel.metal-snap-platinum {
            border-color: rgba(100, 116, 139, 0.3) !important;
            background: linear-gradient(165deg, #f8fafc 0%, #ffffff 45%, #eef2f7 100%) !important;
        }
        .today-rate-panel.metal-snap-diamond {
            border-color: rgba(59, 130, 246, 0.25) !important;
            background: linear-gradient(165deg, #f8fbff 0%, #ffffff 45%, #f0f7ff 100%) !important;
        }
        .today-rate-panel .sec-title {
            font-size: 1.12rem;
            font-weight: 750;
            color: #92400e;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .today-rate-panel.metal-snap-silver .sec-title,
        .today-rate-panel.metal-snap-other .sec-title { color: #475569; }
        .today-rate-panel.metal-snap-platinum .sec-title { color: #334155; }
        .today-rate-panel.metal-snap-diamond .sec-title { color: #1e40af; }
        .today-rate-panel .sec-title i {
            font-size: 1.25rem;
            color: #ca8a04;
        }
        .today-rate-panel.metal-snap-silver .sec-title i,
        .today-rate-panel.metal-snap-other .sec-title i { color: #64748b; }
        .today-rate-panel.metal-snap-platinum .sec-title i { color: #64748b; }
        .today-rate-panel.metal-snap-diamond .sec-title i { color: #3b82f6; }
        .today-rate-grid {
            margin-left: 0;
            margin-right: 0;
        }
        .today-rate-grid > [class*="col-"] {
            padding-left: 7px;
            padding-right: 7px;
        }
        @media (max-width: 767.98px) {
            .today-rate-grid > .col-md-3,
            .today-rate-grid > .col-md-2 {
                margin-bottom: 10px;
            }
        }
        .gold-snap-card {
            display: flex;
            align-items: stretch;
            gap: 0;
            padding: 0 !important;
            min-height: 0;
            border-radius: 18px !important;
            overflow: hidden;
            border: 1px solid rgba(226, 232, 240, 0.95) !important;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06) !important;
            background: #fff !important;
        }
        .gold-snap-card .dash-snap-carat-img {
            width: 56px;
            min-width: 56px;
            align-self: center;
            max-height: 88px;
            object-fit: contain;
            padding: 6px 4px;
            flex-shrink: 0;
            pointer-events: none;
        }
        .gold-snap-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 36px rgba(15, 23, 42, 0.1) !important;
        }
        .gold-snap-card .circle {
            width: 76px;
            min-height: 100%;
            border-radius: 0 !important;
            border: none !important;
            font-size: 0.95rem;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .dash-snap-grid-diamond .gold-snap-card .circle {
            width: 52px;
            font-size: 0.78rem;
        }
        .dash-snap-grid-diamond .gold-snap-body {
            padding: 10px 12px;
        }
        .dash-snap-grid-diamond .gold-snap-body .card-value {
            font-size: 1.05rem;
        }
        .dash-snap-grid-diamond .gold-snap-body .card-unit,
        .dash-snap-grid-diamond .gold-snap-body .card-date {
            font-size: 0.68rem;
        }
        /* Karat strip + card tint follow the selected metal (not per-carat rainbow). */
        .gold-snap-card.metal-card-gold {
            background: linear-gradient(135deg, #fffdf8 0%, #fff7e6 100%) !important;
            border-color: rgba(184, 149, 74, 0.32) !important;
        }
        .gold-snap-card.metal-card-gold .circle {
            background: linear-gradient(180deg, #f3d58a 0%, #d4af37 55%, #b8954a 100%);
            color: #4a3508;
        }
        .gold-snap-card.metal-card-silver {
            background: linear-gradient(135deg, #f8fafc 0%, #eef2f6 100%) !important;
            border-color: rgba(148, 163, 184, 0.42) !important;
        }
        .gold-snap-card.metal-card-silver .circle {
            background: linear-gradient(180deg, #e8eef4 0%, #c5d0db 55%, #9aa8b8 100%);
            color: #334155;
        }
        .gold-snap-card.metal-card-platinum {
            background: linear-gradient(135deg, #f8fafc 0%, #eef1f4 100%) !important;
            border-color: rgba(100, 116, 139, 0.38) !important;
        }
        .gold-snap-card.metal-card-platinum .circle {
            background: linear-gradient(180deg, #f1f5f9 0%, #cbd5e1 55%, #94a3b8 100%);
            color: #1e293b;
        }
        .gold-snap-card.metal-card-diamond {
            background: linear-gradient(135deg, #f8fbff 0%, #eef6ff 100%) !important;
            border-color: rgba(59, 130, 246, 0.3) !important;
        }
        .gold-snap-card.metal-card-diamond .circle {
            background: linear-gradient(180deg, #dbeafe 0%, #93c5fd 55%, #60a5fa 100%);
            color: #1e3a8a;
        }
        .gold-snap-card.metal-card-other {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%) !important;
            border-color: rgba(100, 116, 139, 0.32) !important;
        }
        .gold-snap-card.metal-card-other .circle {
            background: linear-gradient(180deg, #e2e8f0 0%, #94a3b8 100%);
            color: #1e293b;
        }
        .gold-snap-body {
            flex: 1;
            padding: 14px 16px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .gold-snap-body .card-value {
            font-size: 1.35rem;
            font-weight: 800;
            color: #0f172a;
        }
        .gold-snap-body .card-unit {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 2px;
        }
        .gold-snap-body .card-date {
            margin-top: 8px;
            font-size: 0.72rem;
            color: #94a3b8;
        }

        @media (max-width: 767.98px) {
            .dash-mobile-root {
                max-width: 100%;
                overflow-x: hidden;
            }
            .dash-branch-rates-bar {
                flex-direction: column !important;
                align-items: stretch !important;
            }
            .dash-branch-rates-bar .form-control {
                max-width: 100% !important;
            }
            .metal-tabs-wrap .nav-link {
                padding: 10px 12px;
                margin: 0 2px;
                font-size: 0.82rem;
            }
            .dash-gold-chart-wrap {
                height: 220px;
            }
        }
        /*
         * Below lg, layout-content forces .row { margin: 0 }, so Bootstrap’s negative row margin
         * no longer cancels column horizontal padding — rate cards stay inset vs .dash-summary.
         * Drop horizontal padding on tab columns so white panels match headline card width.
         */
        @media (max-width: 991.98px) {
            .dash-mobile-root .metal-tabs-wrap,
            .dash-mobile-root #metalRateTabContent {
                width: 100%;
                max-width: 100%;
                box-sizing: border-box;
            }
            .dash-mobile-root #metalRateTabContent .tab-pane > .row {
                margin-left: 0 !important;
                margin-right: 0 !important;
            }
            .dash-mobile-root #metalRateTabContent .tab-pane > .row > [class*="col-"] {
                padding-left: 0 !important;
                padding-right: 0 !important;
            }
            .dash-mobile-root #metalRateTabContent .tab-pane > .row > [class*="col-"]:not(:last-child) {
                margin-bottom: 1rem;
            }
            .dash-mobile-root #metalRateTabContent .dash-gold-analytics-card .row {
                margin-left: 0 !important;
                margin-right: 0 !important;
            }
            .dash-mobile-root #metalRateTabContent .dash-gold-analytics-card .row > [class*="col-"] {
                padding-left: 0 !important;
                padding-right: 0 !important;
            }
            .dash-mobile-root #metalRateTabContent .dash-gold-analytics-card .row > [class*="col-"]:not(:last-child) {
                margin-bottom: 0.75rem;
            }
            .dash-mobile-root #metalRateTabContent .white-box .row > [class*="col-"] {
                padding-left: 0 !important;
                padding-right: 0 !important;
            }
        }
    </style>
</head>

<body>

<div class="layout-wrapper layout-2">
    <div class="layout-inner">
        <div id="layout-sidenav" class="layout-sidenav sidenav sidenav-vertical bg-white logo-dark" aria-hidden="true"></div>
        <div class="layout-container">
            <nav class="layout-navbar navbar navbar-expand-lg align-items-lg-center bg-dark container-p-x" id="layout-navbar" aria-hidden="true"></nav>
            <div class="layout-content">
                <div class="container-fluid flex-grow-1" style="padding-top:0;padding-bottom:0;">

<?php include 'sidebar.php'; ?>

<div class="row">
<div class="col-12 p-3">

<div class="dash-mobile-root">

<?php
$dash_branch_name = '';
if ($dash_effective_branch > 0) {
    foreach ($dash_branch_list as $br) {
        if ((int) ($br['id'] ?? 0) === (int) $dash_rates_branch_id) {
            $dash_branch_name = (string) ($br['name'] ?? '');
            break;
        }
    }
    if ($dash_branch_name === '') {
        $dash_branch_name = '#' . (int) $dash_rates_branch_id;
    }
}
$dash_today_q = [];
if ($dash_effective_branch <= 0 && (int) $dash_rates_branch_id > 0) {
    $dash_today_q['branch'] = (string) (int) $dash_rates_branch_id;
}
$dash_today_href = 'dashboard.php' . ($dash_today_q !== [] ? ('?' . http_build_query($dash_today_q)) : '');
$dashGreetH = (int) date('G');
if ($dashGreetH < 12) {
    $dashGreeting = 'Good morning';
} elseif ($dashGreetH < 17) {
    $dashGreeting = 'Good afternoon';
} else {
    $dashGreeting = 'Good evening';
}
?>
<div class="dash-page-toolbar">
  <div class="dash-toolbar-left">
    <div class="dash-toolbar-greeting"><?= htmlspecialchars($dashGreeting, ENT_QUOTES, 'UTF-8') ?></div>
    <h1 class="dash-toolbar-title">Live Gold Rate</h1>
    <?php if ($dash_effective_branch > 0) { ?>
    <span class="dash-branch-chip">Branch: <strong><?= htmlspecialchars($dash_branch_name, ENT_QUOTES, 'UTF-8') ?></strong></span>
    <?php } ?>
    <span class="dash-toolbar-meta">Updated <?= htmlspecialchars($rates_updated, ENT_QUOTES, 'UTF-8') ?></span>
  </div>
  <form class="dash-date-filter" method="get" action="dashboard.php" id="dashDateFilterForm">
    <?php if ($dash_effective_branch <= 0 && !empty($dash_branch_list)) { ?>
    <label class="sr-only" for="dashBranchRatesSelect">Rates for branch</label>
    <select name="branch" id="dashBranchRatesSelect" class="form-control form-control-sm" title="Rates for branch">
      <option value="0"<?= (int) $dash_rates_branch_id === 0 ? ' selected' : ''; ?>>Default (shared)</option>
      <?php foreach ($dash_branch_list as $br) {
          $bid = (int) ($br['id'] ?? 0);
          if ($bid <= 0) {
              continue;
          }
          ?>
      <option value="<?= $bid ?>"<?= (int) $dash_rates_branch_id === $bid ? ' selected' : ''; ?>><?= htmlspecialchars((string) ($br['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
      <?php } ?>
    </select>
    <?php } elseif ($dash_effective_branch <= 0 && (int) $dash_rates_branch_id > 0) { ?>
    <input type="hidden" name="branch" value="<?= (int) $dash_rates_branch_id ?>">
    <?php } ?>
    <input type="date" name="date_from" id="dash_date_from" title="From date" value="<?= htmlspecialchars($dash_date_from, ENT_QUOTES, 'UTF-8') ?>" required>
    <input type="date" name="date_to" id="dash_date_to" title="To date" value="<?= htmlspecialchars($dash_date_to, ENT_QUOTES, 'UTF-8') ?>" required>
    <button type="submit" class="btn-dash-apply">Apply</button>
    <a href="<?= htmlspecialchars($dash_today_href, ENT_QUOTES, 'UTF-8') ?>" class="btn-dash-today">Today</a>
  </form>
</div>

<?php
$dash_metal_icons = [
    'gold'     => 'feather icon-award',
    'silver'   => 'feather icon-layers',
    'platinum' => 'feather icon-circle',
    'diamond'  => 'feather icon-heart',
];
?>
<select id="dashDisplayCurrency" class="sr-only" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;pointer-events:none;" aria-hidden="true" tabindex="-1" autocomplete="off">
<?php foreach ($currencies as $c) {
    $cid = (int) ($c['id'] ?? 0);
    $lab = trim((string) ($c['symbol'] ?? '')) ?: trim((string) ($c['name'] ?? ''));
    if ($lab === '') {
        continue;
    }
    $sel = ($display_currency_id > 0 && $cid === $display_currency_id) ? ' selected' : '';
?>
  <option value="<?= $cid ?>"<?= $sel ?>><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars(trim((string) ($c['name'] ?? '')), ENT_QUOTES, 'UTF-8') ?></option>
<?php } ?>
<?php if (empty($currencies)) { ?>
  <option value="<?= (int) $base_currency_id ?>"><?= htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') ?></option>
<?php } ?>
</select>

<div class="metal-tabs-wrap">
  <ul class="nav nav-tabs border-0 justify-content-center flex-wrap" id="metalRateTabs" role="tablist">
    <?php
    $i = 0;
    foreach ($dashboard_metals as $key => $m) {
        $active = $i === 0 ? ' active' : '';
        $coreTabs = ['gold', 'silver', 'platinum', 'diamond'];
        if (in_array((string) $key, $coreTabs, true)) {
            $itemClass = 'nav-item nav-item-' . (string) $key;
        } else {
            $itemClass = 'nav-item nav-item-extra-' . preg_replace('/[^a-zA-Z0-9_-]/', 'x', (string) $key);
        }
    ?>
    <?php $tab_icon = $dash_metal_icons[$key] ?? 'fa fa-circle'; ?>
    <li class="<?= htmlspecialchars($itemClass, ENT_QUOTES, 'UTF-8') ?>" role="presentation">
      <a class="nav-link<?= $active ?>" id="tab-<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" data-toggle="tab" href="#pane-<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" role="tab"><i class="<?= htmlspecialchars($tab_icon, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i><?= htmlspecialchars($m['label'], ENT_QUOTES, 'UTF-8') ?></a>
    </li>
    <?php
        $i++;
    }
    ?>
  </ul>
</div>

<div class="dash-metal-snapshot-bar" id="dashMetalSnapshotBar" aria-live="polite">
  <?php
  $snap_i = 0;
  foreach ($dashboard_metals as $snap_key => $snap_m) {
      $snap_active = $snap_i === 0 ? ' active' : '';
      $snap_title = ($snap_key === 'gold')
          ? 'Today Gold Rate'
          : (($snap_key === 'silver')
              ? 'Today Silver Rate'
              : (($snap_key === 'platinum')
                  ? 'Today Platinum Rate'
                  : (($snap_key === 'diamond')
                      ? 'Today Diamond Rate'
                      : ('Today ' . trim((string) ($snap_m['label'] ?? '')) . ' Rate'))));
      $snap_panel_class = 'today-rate-panel';
      $snap_metal_slug = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $snap_key);
      if (in_array($snap_metal_slug, ['gold', 'silver', 'platinum', 'diamond'], true)) {
          $snap_panel_class .= ' metal-snap-' . $snap_metal_slug;
          $snap_card_metal_class = 'metal-card-' . $snap_metal_slug;
      } else {
          $snap_panel_class .= ' metal-snap-other';
          $snap_card_metal_class = 'metal-card-other';
      }
      $is_dm_snap = ($snap_key === 'diamond');
      $snap_unit = $is_dm_snap ? 'per carat (ref.)' : 'per gram (ref.)';
      $snap_col_class = $is_dm_snap ? 'col-6 col-md-2' : 'col-12 col-md-3';
      $snap_grid_class = $is_dm_snap ? 'row g-3 today-rate-grid dash-snap-grid-diamond' : 'row g-3 today-rate-grid';
  ?>
  <div class="dash-metal-snapshot-pane<?= $snap_active ?>" id="dash-snapshot-<?= htmlspecialchars($snap_key, ENT_QUOTES, 'UTF-8') ?>" data-metal="<?= htmlspecialchars($snap_key, ENT_QUOTES, 'UTF-8') ?>">
    <div class="white-box h-100 <?= htmlspecialchars($snap_panel_class, ENT_QUOTES, 'UTF-8') ?>">
      <div class="sec-title">
        <?php if ($snap_key === 'gold'): ?>
        <i class="fa fa-sun" aria-hidden="true"></i>
        <?php elseif ($snap_key === 'silver'): ?>
        <i class="fa fa-ring" aria-hidden="true"></i>
        <?php elseif ($snap_key === 'platinum'): ?>
        <i class="fa fa-circle" aria-hidden="true"></i>
        <?php elseif ($snap_key === 'diamond'): ?>
        <i class="fa fa-gem" aria-hidden="true"></i>
        <?php else: ?>
        <i class="fa fa-layer-group" aria-hidden="true"></i>
        <?php endif; ?>
        <?= htmlspecialchars($snap_title, ENT_QUOTES, 'UTF-8') ?>
      </div>
      <div class="<?= htmlspecialchars($snap_grid_class, ENT_QUOTES, 'UTF-8') ?>">
        <?php foreach ($snap_m['cards'] as $c) {
            $cv_raw = (string) ($c['value'] ?? '0');
            $cv_base = (float) str_replace([',', ' '], '', $cv_raw);
        ?>
        <div class="<?= htmlspecialchars($snap_col_class, ENT_QUOTES, 'UTF-8') ?>">
        <div class="gold-snap-card rate-card h-100 <?= htmlspecialchars($snap_card_metal_class, ENT_QUOTES, 'UTF-8') ?>">
          <div class="circle <?= htmlspecialchars($c['class'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($c['label'], ENT_QUOTES, 'UTF-8') ?></div>
          <?php
          $snap_img = trim((string) ($c['image_url'] ?? ''));
          if ($snap_img !== '') {
          ?>
          <img class="dash-snap-carat-img" src="<?= htmlspecialchars($snap_img, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy" decoding="async">
          <?php } ?>
          <div class="gold-snap-body">
            <div class="card-value"><span class="js-metal-snapshot js-dash-num" data-metal="<?= htmlspecialchars($snap_key, ENT_QUOTES, 'UTF-8') ?>" data-carat-label="<?= htmlspecialchars($c['label'], ENT_QUOTES, 'UTF-8') ?>" data-is-diamond="<?= $is_dm_snap ? '1' : '0' ?>" data-base="<?= htmlspecialchars((string) $cv_base, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($c['value'], ENT_QUOTES, 'UTF-8') ?></span></div>
            <div class="card-unit"><?= htmlspecialchars($snap_unit, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="card-date"><i class="fa fa-clock mr-1" aria-hidden="true"></i><?= htmlspecialchars($rates_updated, ENT_QUOTES, 'UTF-8') ?></div>
          </div>
        </div>
        </div>
        <?php } ?>
      </div>
    </div>
  </div>
  <?php
      $snap_i++;
  }
  ?>
</div>

<div class="tab-content" id="metalRateTabContent">
  <?php
  $i = 0;
  foreach ($dashboard_metals as $key => $m) {
      $show = $i === 0 ? ' show active' : '';
  ?>
  <div class="tab-pane fade<?= $show ?>" id="pane-<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" role="tabpanel">
    <div class="row g-4">
      <div class="col-12<?= $key === 'gold' ? ' col-lg-6' : '' ?>">
        <div class="white-box dash-rate-form-card">
          <div class="dash-rate-form-head">
            <?php
            $dash_rate_conv_uid = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $key);
            ?>
            <div class="dash-rate-conversions">
              <div class="dash-rate-conv-card">
                <div class="dash-rate-conv-header">
                  <span class="dash-rate-conv-badge" aria-hidden="true"><i class="feather icon-activity"></i></span>
                  <div class="dash-rate-conv-titles">
                    <label for="dashRateConversionUrls-<?= htmlspecialchars($dash_rate_conv_uid, ENT_QUOTES, 'UTF-8') ?>">Rate Conversions</label>
                    <span class="dash-rate-conv-sub">Fetch live rates from trusted sources</span>
                  </div>
                </div>
                <div class="dash-rate-conv-select-wrap">
                  <i class="feather icon-globe dash-rate-conv-select-icon" aria-hidden="true"></i>
                  <select id="dashRateConversionUrls-<?= htmlspecialchars($dash_rate_conv_uid, ENT_QUOTES, 'UTF-8') ?>"
                    class="js-dash-rate-source-url dash-rate-conv-select" autocomplete="off"
                    data-metal="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">
                    <option value=""></option>
                    <?php foreach ($dash_metal_rate_urls as $mru): ?>
                      <?php
                      $mruUrl = trim((string) ($mru['url'] ?? ''));
                      if ($mruUrl === '') {
                          continue;
                      }
                      $mruLabel = trim((string) ($mru['display'] ?? $mru['label'] ?? ''));
                      if ($mruLabel === '') {
                          $mruLabel = $mruUrl;
                      }
                      ?>
                    <option value="<?= htmlspecialchars($mruUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($mruLabel, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <p class="js-dash-fetch-status dash-rate-conv-status text-muted" aria-live="polite"></p>
              </div>
              <input type="hidden" class="js-meta-source" value="<?= htmlspecialchars((string) ($m['source_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" class="js-meta-ounce" value="<?= htmlspecialchars(preg_replace('/[^\d.]/', '', (string) ($m['ounce_rate'] ?? '0')), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <?php if ($key === 'gold'): ?>
            <h3>Gold rate sheet</h3>
            <p>Enter values in <strong class="js-base-cur-label"><?= htmlspecialchars($base_currency_label, ENT_QUOTES, 'UTF-8') ?></strong> per gram. Saving updates the dashboard, snapshots, and analysis.</p>
            <?php else: ?>
            <h3><?= htmlspecialchars($m['label'], ENT_QUOTES, 'UTF-8') ?> — rate conversions</h3>
            <p class="mb-0">Amounts in <strong class="js-base-cur-label"><?= htmlspecialchars($base_currency_label, ENT_QUOTES, 'UTF-8') ?></strong> (base currency).</p>
            <?php endif; ?>
          </div>

          <div class="table-responsive">
            <table class="table table-striped text-center mb-0 dash-rates-table">
              <thead>
                <tr>
                  <th class="text-left"><?= htmlspecialchars($m['table_carat_label'], ENT_QUOTES, 'UTF-8') ?></th>
                  <th>New rate</th>
                  <th>Sell premium</th>
                  <th>Conv.</th>
                  <th>Current</th>
                  <th style="width:52px;"></th>
                </tr>
              </thead>
              <tbody>
                <?php
                $row_idx = 0;
                foreach ($m['rows'] as $row) {
                    $row_idx++;
                    $carat_key = htmlspecialchars($row['carat'], ENT_QUOTES, 'UTF-8');
                    $karat_num = null;
                    if ($key === 'gold' && preg_match('/(\d+)/', (string) $row['carat'], $km)) {
                        $karat_num = (int) $km[1];
                    }
                    $is_diamond = ($key === 'diamond');
                    $prem = $row['sell_premium'];
                    $prem_is_dash = ($prem === '—' || $prem === '-' || $prem === '');
                    $prem_input_val = $prem_is_dash ? '' : preg_replace('/[^\d.-]/', '', (string) $prem);
                ?>
                <tr<?= $karat_num !== null ? ' data-karat="' . (int) $karat_num . '"' : '' ?> data-metal="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" data-carat-label="<?= htmlspecialchars($row['carat'], ENT_QUOTES, 'UTF-8') ?>">
                  <td><?= $carat_key ?></td>
                  <td>
                    <?php if ($is_diamond) { ?>
                    <input type="text" inputmode="decimal" class="form-control form-control-sm text-center rate-input input-box js-rate-new" name="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>_new_<?= (int) $row_idx ?>" value="<?= htmlspecialchars($row['new_rate'], ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                    <?php } else { ?>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm text-center rate-input input-box js-rate-new" name="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>_new_<?= (int) $row_idx ?>" value="<?= htmlspecialchars(preg_replace('/[^\d.]/', '', (string) $row['new_rate']), ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                    <?php } ?>
                  </td>
                  <td>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm text-center rate-input input-box js-rate-premium" name="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>_prem_<?= (int) $row_idx ?>" value="<?= htmlspecialchars($prem_input_val, ENT_QUOTES, 'UTF-8') ?>" placeholder="—" autocomplete="off">
                  </td>
                  <td><?= htmlspecialchars($row['conv'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="js-rate-current"><?= htmlspecialchars($row['current'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><button type="button" class="icon-btn js-save-metal-rates" title="Save to database">💾</button></td>
                </tr>
                <?php } ?>
              </tbody>
            </table>
          </div>

          <button type="button" class="btn btn-dash-save-all mt-4 float-end js-save-metal-rates">
            <i class="fa fa-check mr-1" aria-hidden="true"></i> Save all rates
          </button>
          <div class="clearfix"></div>
        </div>
      </div>
      <?php if ($key === 'gold'): ?>
      <div class="col-12 col-lg-6">
        <div class="dash-gold-analytics-card dash-gold-analytics-side white-box h-100">
          <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
              <h3 class="dash-analytics-title mb-1">Gold rate analysis</h3>
              <p class="text-muted small mb-0">24K trend for the selected date range — yesterday vs today comparison when available</p>
            </div>
            <div class="btn-group btn-group-sm dash-gold-chart-mode" role="group" aria-label="Chart range">
              <button type="button" class="btn btn-light border active js-gold-chart-range" data-range="week">7 days</button>
              <button type="button" class="btn btn-light border js-gold-chart-range" data-range="month">30 days</button>
            </div>
          </div>
          <div class="row g-3">
            <div class="col-12 col-sm-4">
              <div class="dash-stat-pill">
                <span class="label">Yesterday (24K)</span>
                <span class="value js-gold-stat-yesterday">—</span>
              </div>
            </div>
            <div class="col-12 col-sm-4">
              <div class="dash-stat-pill">
                <span class="label">Today (24K)</span>
                <span class="value js-gold-stat-today">—</span>
              </div>
            </div>
            <div class="col-12 col-sm-4">
              <div class="dash-stat-pill">
                <span class="label">Change vs yesterday</span>
                <span class="value js-gold-stat-change">—</span>
              </div>
            </div>
          </div>
          <div class="dash-gold-chart-wrap mt-3">
            <canvas id="goldRateChart" height="110"></canvas>
            <p class="text-muted small mt-2 mb-0 js-gold-chart-empty text-center d-none">Save gold rates to build history and unlock the chart.</p>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php
      $i++;
  }
  ?>
</div>
</div>

</div>
</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer-script.php'; ?>
<?php auragold_echo_script('assets/libs/select2/select2.js'); ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
<script>
window.AURAGOLD_DASH_CURRENCY = <?= json_encode($dash_currency_payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
window.AURAGOLD_DASH_BASE_LABEL = <?= json_encode($base_currency_label, JSON_UNESCAPED_UNICODE) ?>;
window.AURAGOLD_DASH_BRANCH_ID = <?= (int) $dash_rates_branch_id ?>;
window.AURAGOLD_DASH_DATE_FROM = <?= json_encode($dash_date_from, JSON_UNESCAPED_UNICODE) ?>;
window.AURAGOLD_DASH_DATE_TO = <?= json_encode($dash_date_to, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script>
(function () {
    var STORAGE_KEY = 'auragold_dashboard_currency_id';

    function findCur(id) {
        var list = (window.AURAGOLD_DASH_CURRENCY && window.AURAGOLD_DASH_CURRENCY.currencies) ? window.AURAGOLD_DASH_CURRENCY.currencies : [];
        var sid = String(id);
        for (var i = 0; i < list.length; i++) {
            if (String(list[i].id) === sid) {
                return list[i];
            }
        }
        return null;
    }

    function rateFor(currencyId) {
        var r = window.AURAGOLD_DASH_CURRENCY && window.AURAGOLD_DASH_CURRENCY.rates;
        if (!r) {
            return 0;
        }
        var v = r[currencyId];
        if (v == null) {
            v = r[String(currencyId)];
        }
        return parseFloat(v, 10) || 0;
    }

    function convertBaseToDisplay(baseNum, currencyId) {
        var bid = window.AURAGOLD_DASH_CURRENCY ? window.AURAGOLD_DASH_CURRENCY.baseId : 0;
        if (!currencyId || String(currencyId) === String(bid)) {
            return baseNum;
        }
        var rt = rateFor(currencyId);
        if (rt <= 0) {
            return baseNum;
        }
        return baseNum / rt;
    }

    function formatDisp(n, isDiamond) {
        if (isDiamond) {
            var x = Math.round(n * 100) / 100;
            return (Math.abs(x - Math.round(x)) < 1e-6) ? String(Math.round(x)) : x.toFixed(2);
        }
        return (Math.round(n * 100) / 100).toFixed(2);
    }

    function applyDashCurrency() {
        var sel = document.getElementById('dashDisplayCurrency');
        if (!sel) {
            return;
        }
        var cid = sel.value;
        var cur = findCur(cid);
        var code = cur ? (cur.symbol || cur.name || '') : (window.AURAGOLD_DASH_BASE_LABEL || '');
        var baseLabel = window.AURAGOLD_DASH_BASE_LABEL || '';

        document.querySelectorAll('.js-base-cur-label').forEach(function (el) {
            el.textContent = baseLabel;
        });

        document.querySelectorAll('.js-dash-cur').forEach(function (el) {
            el.textContent = code || baseLabel;
        });

        document.querySelectorAll('.js-dash-num').forEach(function (el) {
            var isDm = el.getAttribute('data-is-diamond') === '1';
            var base = parseFloat(el.getAttribute('data-base'), 10);
            if (el.classList.contains('js-metal-snapshot') && typeof jQuery !== 'undefined') {
                var metal = el.getAttribute('data-metal') || 'gold';
                var lbl = el.getAttribute('data-carat-label') || '';
                var $inp = jQuery('#pane-' + metal + ' tbody tr').filter(function () {
                    return jQuery(this).attr('data-carat-label') === lbl;
                }).find('.js-rate-new').first();
                if ($inp.length) {
                    var pv = parseFloat(String($inp.val()).replace(/,/g, ''), 10);
                    if (!isNaN(pv)) {
                        base = pv;
                    }
                }
            }
            if (isNaN(base)) {
                base = 0;
            }
            var disp = convertBaseToDisplay(base, cid);
            el.textContent = formatDisp(disp, isDm);
        });
    }

    window.applyDashCurrencyDisplay = applyDashCurrency;
    window.auragoldDashConvertBaseToDisplay = convertBaseToDisplay;
    window.auragoldDashFindCur = findCur;
    window.auragoldDashGetDisplayCurrencyId = function () {
        var s = document.getElementById('dashDisplayCurrency');
        return s ? s.value : null;
    };

    document.addEventListener('DOMContentLoaded', function () {
        var sel = document.getElementById('dashDisplayCurrency');
        if (!sel) {
            return;
        }
        try {
            var pref = window.AURAGOLD_DASH_CURRENCY && window.AURAGOLD_DASH_CURRENCY.displayCurrencyId;
            if (pref && String(pref) !== '0') {
                sel.value = String(pref);
            } else {
                var saved = localStorage.getItem(STORAGE_KEY);
                if (saved !== null && saved !== '') {
                    var ok = false;
                    Array.prototype.forEach.call(sel.options, function (o) {
                        if (o.value === saved) {
                            ok = true;
                        }
                    });
                    if (ok) {
                        sel.value = saved;
                    }
                }
            }
        } catch (e) {}
        sel.addEventListener('change', function () {
            try {
                localStorage.setItem(STORAGE_KEY, sel.value);
            } catch (e2) {}
            applyDashCurrency();
            if (typeof window.refreshGoldDashboardAnalytics === 'function') {
                window.refreshGoldDashboardAnalytics();
            }
        });
        applyDashCurrency();
    });
})();
</script>
<script>
(function () {
    var goldChart = null;
    var goldRange = 'week';
    var lastPayload = null;

    function money2(n) {
        return (Math.round(n * 100) / 100).toFixed(2);
    }

    function curCode() {
        var cid = window.auragoldDashGetDisplayCurrencyId ? window.auragoldDashGetDisplayCurrencyId() : null;
        var c = window.auragoldDashFindCur ? window.auragoldDashFindCur(cid) : null;
        return c ? (c.symbol || c.name || '') : '';
    }

    function sliceRange(series, range) {
        if (!series || !series.length) {
            return [];
        }
        var n = range === 'month' ? 30 : 7;
        if (series.length <= n) {
            return series.slice();
        }
        return series.slice(-n);
    }

    function fmtDay(dstr) {
        var p = String(dstr).split('-');
        if (p.length !== 3) {
            return dstr;
        }
        var mo = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return parseInt(p[2], 10) + ' ' + mo[parseInt(p[1], 10) - 1];
    }

    function renderChart(series) {
        var canvas = document.getElementById('goldRateChart');
        if (!canvas || typeof Chart === 'undefined') {
            return;
        }
        var conv = window.auragoldDashConvertBaseToDisplay;
        var cid = window.auragoldDashGetDisplayCurrencyId ? window.auragoldDashGetDisplayCurrencyId() : null;
        if (!conv) {
            return;
        }
        var emptyEl = document.querySelector('.js-gold-chart-empty');
        var data = sliceRange(series || [], goldRange);
        if (emptyEl) {
            if (!data.length) {
                emptyEl.classList.remove('d-none');
            } else {
                emptyEl.classList.add('d-none');
            }
        }
        var labels = data.map(function (x) {
            return fmtDay(x.date);
        });
        var vals = data.map(function (x) {
            return conv(parseFloat(x.rate, 10), cid);
        });

        if (goldChart) {
            goldChart.destroy();
            goldChart = null;
        }
        if (!data.length) {
            return;
        }

        var ctx = canvas.getContext('2d');
        goldChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: '24K',
                    data: vals,
                    borderColor: '#b8860b',
                    backgroundColor: 'rgba(201, 162, 39, 0.15)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx2) {
                                return money2(ctx2.parsed.y) + ' ' + curCode();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        grid: { color: 'rgba(15, 23, 42, 0.06)' },
                        ticks: {
                            callback: function (v) {
                                return money2(v);
                            }
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { maxRotation: 45, minRotation: 0 }
                    }
                }
            }
        });
    }

    function updateStats(payload) {
        var conv = window.auragoldDashConvertBaseToDisplay;
        var cid = window.auragoldDashGetDisplayCurrencyId ? window.auragoldDashGetDisplayCurrencyId() : null;
        if (!conv) {
            return;
        }
        var yT = document.querySelector('.js-gold-stat-yesterday');
        var tT = document.querySelector('.js-gold-stat-today');
        var cT = document.querySelector('.js-gold-stat-change');
        if (!yT || !tT || !cT) {
            return;
        }
        var cc = curCode();
        var t = conv(parseFloat(payload.today24Base, 10), cid);
        tT.textContent = money2(t) + (cc ? ' ' + cc : '');

        if (payload.yesterday24Base !== null && payload.yesterday24Base !== undefined && !isNaN(parseFloat(payload.yesterday24Base, 10))) {
            yT.textContent = money2(conv(parseFloat(payload.yesterday24Base, 10), cid)) + (cc ? ' ' + cc : '');
        } else {
            yT.textContent = '—';
        }

        if (payload.changePct === null || payload.changePct === undefined || payload.yesterday24Base === null) {
            cT.textContent = '—';
            cT.className = 'value js-gold-stat-change';
        } else {
            var p = parseFloat(payload.changePct, 10);
            cT.textContent = (p >= 0 ? '+' : '') + p.toFixed(2) + '%';
            cT.className = 'value js-gold-stat-change ' + (p >= 0 ? 'positive' : 'negative');
        }
    }

    function loadRemote() {
        var br = (typeof window.AURAGOLD_DASH_BRANCH_ID !== 'undefined' && window.AURAGOLD_DASH_BRANCH_ID !== null)
            ? String(window.AURAGOLD_DASH_BRANCH_ID) : '0';
        var df = (typeof window.AURAGOLD_DASH_DATE_FROM === 'string') ? window.AURAGOLD_DASH_DATE_FROM : '';
        var dt = (typeof window.AURAGOLD_DASH_DATE_TO === 'string') ? window.AURAGOLD_DASH_DATE_TO : '';
        var url = 'ajax/dashboard-gold-analytics.php?branch=' + encodeURIComponent(br);
        if (df && dt) {
            url += '&date_from=' + encodeURIComponent(df) + '&date_to=' + encodeURIComponent(dt);
        }
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) {
                return r.json();
            })
            .then(function (data) {
                if (!data || !data.ok) {
                    return;
                }
                lastPayload = data;
                updateStats(data);
                renderChart(data.series);
            })
            .catch(function () {});
    }

    window.refreshGoldDashboardAnalytics = function () {
        if (lastPayload) {
            updateStats(lastPayload);
            renderChart(lastPayload.series);
        } else {
            loadRemote();
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        loadRemote();
        document.querySelectorAll('.js-gold-chart-range').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.js-gold-chart-range').forEach(function (b) {
                    b.classList.remove('active');
                });
                btn.classList.add('active');
                goldRange = btn.getAttribute('data-range') || 'week';
                if (lastPayload) {
                    renderChart(lastPayload.series);
                }
            });
        });
    });
})();
</script>
<script>
(function ($) {
    var dashRateFetchInFlight = false;

    function resolveDashRatePane($sel) {
        var $pane = $sel.closest('.tab-pane');
        if ($pane.length) {
            return $pane;
        }
        var metal = $sel.attr('data-metal') || 'gold';
        return $('#pane-' + metal);
    }

    function initDashRateSourceSelects() {
        if (!$.fn.select2) {
            return;
        }
        $('.js-dash-rate-source-url').each(function () {
            var $sel = $(this);
            if ($sel.hasClass('select2-hidden-accessible')) {
                return;
            }
            $sel.select2({
                placeholder: 'Select URL to fetch live rates…',
                allowClear: true,
                width: '100%',
                dropdownCssClass: 'dash-rate-conv-dropdown',
                minimumResultsForSearch: 6
            });
            $sel.off('select2:select.dashRateFetch select2:clear.dashRateFetch');
            $sel.on('select2:select.dashRateFetch', function () {
                fetchDashRatesFromSelect($(this));
            });
            $sel.on('select2:clear.dashRateFetch', function () {
                resolveDashRatePane($(this)).find('.js-dash-fetch-status').first()
                    .removeClass('text-success text-danger').addClass('text-muted').text('');
            });
        });
    }

    function setDashRateSourceDisabled($sel, disabled) {
        $sel.prop('disabled', disabled);
        if ($sel.hasClass('select2-hidden-accessible')) {
            $sel.trigger('change.select2');
        }
    }

    function resetDashRateSourceSelectSilent($sel) {
        $sel.data('dashSilentReset', 1);
        $sel.val(null);
        if ($sel.hasClass('select2-hidden-accessible')) {
            $sel.trigger('change.select2');
        }
        $sel.removeData('dashSilentReset');
    }

    function fetchDashRatesFromSelect($sel) {
        if ($sel.data('dashSilentReset') || dashRateFetchInFlight) {
            return;
        }
        var url = $.trim($sel.val() || '');
        var $pane = resolveDashRatePane($sel);
        var $status = $pane.find('.js-dash-fetch-status').first();
        if (!url) {
            $status.removeClass('text-success text-danger').addClass('text-muted').text('');
            return;
        }
        if (!$pane.length) {
            $status.removeClass('text-muted text-success').addClass('text-danger')
                .text('Could not find the rate sheet for this metal tab.');
            return;
        }
        var metal = $sel.attr('data-metal') || ($pane.attr('id') || '').replace('pane-', '') || 'gold';
        var labels = [];
        $pane.find('tbody tr[data-carat-label]').each(function () {
            var lab = $(this).attr('data-carat-label');
            if (lab) {
                labels.push(lab);
            }
        });
        dashRateFetchInFlight = true;
        setDashRateSourceDisabled($sel, true);
        $status.removeClass('text-danger text-success').addClass('text-muted').text('Fetching live rates…');
        $.ajax({
            url: 'ajax/fetch-dashboard-rates.php',
            method: 'POST',
            contentType: 'application/json; charset=UTF-8',
            data: JSON.stringify({ url: url, metal: metal, labels: labels }),
            dataType: 'json'
        }).done(function (res) {
            if (!res || res.status !== 'ok') {
                $status.removeClass('text-muted text-success').addClass('text-danger')
                    .text((res && res.message) ? res.message : 'Could not fetch rates.');
                return;
            }
            $pane.find('.js-meta-source').val(url);
            if (res.ounce_rate !== undefined && res.ounce_rate !== null) {
                $pane.find('.js-meta-ounce').val(String(res.ounce_rate));
            }
            var n = applyFetchedRates($pane, res.rows || []);
            $status.removeClass('text-muted text-danger').addClass('text-success')
                .text((res.message || 'Rates loaded.') + (n ? ' (' + n + ' rows filled) — click Save to store.' : ''));
        }).fail(function (xhr) {
            var msg = 'Could not fetch rates.';
            try {
                var j = JSON.parse(xhr.responseText);
                if (j && j.message) {
                    msg = j.message;
                }
            } catch (e) {}
            $status.removeClass('text-muted text-success').addClass('text-danger').text(msg);
        }).always(function () {
            dashRateFetchInFlight = false;
            setDashRateSourceDisabled($sel, false);
            resetDashRateSourceSelectSilent($sel);
        });
    }

    $(function () {
        initDashRateSourceSelects();
    });

    function syncMetalSnapshot($pane) {
        var metal = ($pane.attr('id') || '').replace('pane-', '');
        $pane.find('tbody tr[data-carat-label]').each(function () {
            var $tr = $(this);
            var label = $.trim($tr.attr('data-carat-label') || $tr.find('td:first').text());
            var v = $tr.find('.js-rate-new').val();
            $tr.find('.js-rate-current').text(v);
            var $snap = $('#dash-snapshot-' + metal + ' .js-metal-snapshot[data-carat-label="' + label + '"]');
            $snap.text(v);
            var pv = parseFloat(String(v).replace(/,/g, ''), 10);
            if (!isNaN(pv)) {
                $snap.attr('data-base', String(pv));
            }
        });
        if (typeof window.applyDashCurrencyDisplay === 'function') {
            window.applyDashCurrencyDisplay();
        }
    }

    function updateDashSnapshotBar(metal) {
        var $bar = $('#dashMetalSnapshotBar');
        if (!$bar.length) {
            return;
        }
        $bar.removeClass('is-hidden');
        $bar.find('.dash-metal-snapshot-pane').removeClass('active');
        var $pane = $('#dash-snapshot-' + metal);
        if ($pane.length) {
            $pane.addClass('active');
        }
    }

    $('#metalRateTabs a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        var href = $(e.target).attr('href') || '';
        var metal = href.replace('#pane-', '');
        updateDashSnapshotBar(metal);
    });

    $(function () {
        var $activeTab = $('#metalRateTabs .nav-link.active').first();
        if ($activeTab.length) {
            var href = $activeTab.attr('href') || '';
            updateDashSnapshotBar(href.replace('#pane-', ''));
        }
    });

    $('#metalRateTabContent .tab-pane').on('input', '.js-rate-new', function () {
        var $pane = $(this).closest('.tab-pane');
        var metal = ($pane.attr('id') || '').replace('pane-', '');
        var $tr = $(this).closest('tr');
        var v = $(this).val();
        $tr.find('.js-rate-current').text(v);
        var label = $.trim($tr.attr('data-carat-label') || $tr.find('td:first').text());
        var $snap = $('#dash-snapshot-' + metal + ' .js-metal-snapshot[data-carat-label="' + label + '"]');
        $snap.text(v);
        var pv = parseFloat(String(v).replace(/,/g, ''), 10);
        if (!isNaN(pv)) {
            $snap.attr('data-base', String(pv));
        }
        if (typeof window.applyDashCurrencyDisplay === 'function') {
            window.applyDashCurrencyDisplay();
        }
    });

    $('#pane-gold').on('click', '#goldFillFrom24k', function () {
        var $pane = $('#pane-gold');
        var $r24 = $pane.find('tr[data-karat="24"] .js-rate-new').first();
        var raw = $r24.val();
        var v = parseFloat(String(raw).replace(/,/g, ''), 10);
        if (isNaN(v) || v <= 0) {
            window.alert('Enter today’s 24K rate (' + (window.AURAGOLD_DASH_BASE_LABEL || 'base') + ' per gram) in the 24K row first.');
            return;
        }
        $pane.find('tbody tr[data-karat]').each(function () {
            var k = parseInt($(this).attr('data-karat'), 10);
            if (!k || k < 1 || k > 24) {
                return;
            }
            var nv = (v * k / 24).toFixed(2);
            $(this).find('.js-rate-new').val(nv);
        });
        syncMetalSnapshot($pane);
    });

    function collectMetalPane($pane) {
        var metal = ($pane.attr('id') || '').replace('pane-', '');
        var source = $pane.find('.js-meta-source').length ? ($pane.find('.js-meta-source').val() || '') : '';
        var ounce = $pane.find('.js-meta-ounce').length ? ($pane.find('.js-meta-ounce').val() || '0') : '0';
        var branchId = (typeof window.AURAGOLD_DASH_BRANCH_ID !== 'undefined' && window.AURAGOLD_DASH_BRANCH_ID !== null)
            ? parseInt(window.AURAGOLD_DASH_BRANCH_ID, 10) : 0;
        if (isNaN(branchId)) {
            branchId = 0;
        }
        var rows = [];
        $pane.find('tbody tr[data-carat-label]').each(function () {
            var carat = $(this).attr('data-carat-label');
            var rate = $(this).find('.js-rate-new').val();
            var premInp = $(this).find('.js-rate-premium');
            var sell_premium = premInp.length ? String(premInp.val() || '').trim() : '';
            rows.push({ carat: carat, rate: rate, conv: '1', sell_premium: sell_premium });
        });
        return { metal: metal, branch_id: branchId, source_url: source, ounce_rate: ounce, rows: rows };
    }

    function saveMetalRates($pane) {
        var payload = collectMetalPane($pane);
        if (!payload.rows.length) {
            window.alert('No rate rows to save.');
            return;
        }
        var $btns = $pane.find('.js-save-metal-rates').prop('disabled', true);
        $.ajax({
            url: 'ajax/save-dashboard-rates.php',
            method: 'POST',
            contentType: 'application/json; charset=UTF-8',
            data: JSON.stringify(payload),
            dataType: 'json'
        }).done(function (res) {
            if (res && res.status === 'ok') {
                window.location.reload();
            } else {
                window.alert((res && res.message) ? res.message : 'Save failed.');
            }
        }).fail(function (xhr) {
            var msg = 'Could not save rates.';
            try {
                var j = JSON.parse(xhr.responseText);
                if (j && j.message) msg = j.message;
            } catch (e) {}
            window.alert(msg);
        }).always(function () {
            $btns.prop('disabled', false);
        });
    }

    $(document).on('click', '.js-save-metal-rates', function () {
        var $pane = $(this).closest('.tab-pane');
        if (!$pane.length) return;
        saveMetalRates($pane);
    });

    function applyFetchedRates($pane, rows) {
        if (!rows || !rows.length) {
            return 0;
        }
        var byLabel = {};
        rows.forEach(function (r) {
            if (!r || !r.carat) return;
            byLabel[String(r.carat)] = r.rate;
        });
        var filled = 0;
        $pane.find('tbody tr[data-carat-label]').each(function () {
            var label = $(this).attr('data-carat-label');
            if (!label || !(label in byLabel)) {
                return;
            }
            var rate = byLabel[label];
            $(this).find('.js-rate-new').val(rate).trigger('input');
            $(this).find('.js-rate-current').text(rate);
            filled++;
        });
        if ($pane.length) {
            syncMetalSnapshot($pane);
        }
        return filled;
    }

    $(document).on('change', '.js-dash-rate-source-url', function () {
        if (!$(this).hasClass('select2-hidden-accessible')) {
            fetchDashRatesFromSelect($(this));
        }
    });

    $(document).on('change', '#dashBranchRatesSelect', function () {
        var u = new URL(window.location.href);
        u.searchParams.set('branch', $(this).val() || '0');
        var df = $('#dash_date_from').val();
        var dt = $('#dash_date_to').val();
        if (df) {
            u.searchParams.set('date_from', df);
        }
        if (dt) {
            u.searchParams.set('date_to', dt);
        }
        window.location.href = u.toString();
    });
})(jQuery);
</script>
</body>
</html>
