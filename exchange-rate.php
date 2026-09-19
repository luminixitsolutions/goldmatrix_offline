<?php

session_start();
require_once __DIR__ . '/config.php';

if (empty($_SESSION['Admin'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/includes/auragold_set_software_page_guard.php';
auragold_set_software_page_guard();
require_once __DIR__ . '/includes/auragold_metal_exchange_rate_schema.php';

auragold_ensure_branch_id_on_settings_tables($conn);
auragold_ensure_metal_exchange_rate_table($conn);
$settings_branch_id = auragold_settings_branch_id();

if (!function_exists('masters_req')) {
    function masters_req()
    {
        return ' <span class="text-danger">*</span>';
    }
}

$currencyRows = getList(
    'SELECT * FROM tbl_currency WHERE status=1 '
    . auragold_master_list_sql_suffix($conn, 'tbl_currency')
    . ' ORDER BY is_base DESC, name ASC, id DESC'
);
if (!is_array($currencyRows)) {
    $currencyRows = [];
}

$rateSql = "
    SELECT r.*, c.name AS currency_name
    FROM tbl_currency_exchange_rate r
    JOIN tbl_currency c ON c.id = r.currency_id
    WHERE r.status IN (0, 1)
    " . auragold_master_list_sql_suffix($conn, 'tbl_currency_exchange_rate', 'r.branch_id') . "
    ORDER BY r.status DESC, r.id DESC
";
$rateRows = getList($rateSql);
if (!is_array($rateRows)) {
    $rateRows = [];
}

$baseCurrencyName = '';
foreach ($currencyRows as $cr) {
    if (!empty($cr['is_base'])) {
        $baseCurrencyName = (string) ($cr['name'] ?? '');
        break;
    }
}

$metalRows = getList(
    'SELECT id, display_name, system_name FROM tbl_metal WHERE status = 1 '
    . auragold_master_list_sql_suffix($conn, 'tbl_metal')
    . ' ORDER BY display_name ASC, id ASC'
);
if (!is_array($metalRows)) {
    $metalRows = [];
}

$unitRows = getList(
    'SELECT id, name FROM tbl_unit WHERE status = 1 '
    . auragold_master_list_sql_suffix($conn, 'tbl_unit')
    . ' ORDER BY name ASC, id ASC'
);
if (!is_array($unitRows)) {
    $unitRows = [];
}

$currencyRateMap = [];
foreach ($rateRows as $_cr) {
    if ((int) ($_cr['status'] ?? 0) !== 1) {
        continue;
    }
    $cid = (int) ($_cr['currency_id'] ?? 0);
    if ($cid > 0 && !isset($currencyRateMap[$cid])) {
        $currencyRateMap[$cid] = (string) ($_cr['rate'] ?? '1');
    }
}

$metalRateSql = "
    SELECT r.*,
           m.display_name AS metal_name,
           c.name AS currency_name,
           u.name AS unit_name
    FROM tbl_metal_exchange_rate r
    LEFT JOIN tbl_metal m ON m.id = r.metal_id
    LEFT JOIN tbl_currency c ON c.id = r.currency_id
    LEFT JOIN tbl_unit u ON u.id = r.unit_id
    WHERE r.status IN (0, 1)
    " . auragold_master_list_sql_suffix($conn, 'tbl_metal_exchange_rate', 'r.branch_id') . "
    ORDER BY r.status DESC, r.rate_date DESC, r.id DESC
";
$metalRateRows = getList($metalRateSql);
if (!is_array($metalRateRows)) {
    $metalRateRows = [];
}

$page_title = 'Exchange Rate — Set Software — ' . auragold_app_name();
$currencyCount = count($currencyRows);
$rateCount = 0;
foreach ($rateRows as $_rr) {
    if ((int) ($_rr['status'] ?? 0) === 1) {
        $rateCount++;
    }
}
$metalRateCount = 0;
foreach ($metalRateRows as $_mr) {
    if ((int) ($_mr['status'] ?? 0) === 1) {
        $metalRateCount++;
    }
}
$todayDate = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
    <?php include __DIR__ . '/header-script.php'; ?>
    <link rel="stylesheet" href="set-software-sidebar.css">
    <style>
        :root {
            --er-navy: #11294b;
            --er-navy-deep: #0a1a30;
            --er-gold: #c5a864;
            --er-gold-soft: #e8d9a8;
            --er-ink: #1e293b;
            --er-muted: #64748b;
            --er-line: #e2e8f0;
            --er-surface: #f4f6f9;
        }
        .exchange-rate-page {
            padding: 0 0 40px;
            width: 100%;
            box-sizing: border-box;
            background:
                radial-gradient(1200px 420px at 100% -10%, rgba(197,168,100,0.18), transparent 55%),
                linear-gradient(180deg, #eef2f7 0%, var(--er-surface) 38%, #fff 100%);
            min-height: calc(100vh - 70px);
        }
        .er-hero {
            position: relative;
            overflow: hidden;
            padding: 28px 28px 22px;
            color: #fff;
            background:
                linear-gradient(125deg, var(--er-navy-deep) 0%, var(--er-navy) 48%, #1a3a62 100%);
        }
        .er-hero::after {
            content: '';
            position: absolute;
            right: -40px;
            top: -40px;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(197,168,100,0.35), transparent 70%);
            pointer-events: none;
        }
        .er-hero-inner {
            position: relative;
            z-index: 1;
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            justify-content: space-between;
            gap: 16px;
        }
        .er-hero h1 {
            margin: 0 0 6px;
            font-size: 1.75rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: #fff;
        }
        .er-hero p {
            margin: 0;
            max-width: 42rem;
            color: rgba(255,255,255,0.78);
            font-size: 0.92rem;
            line-height: 1.45;
        }
        .er-hero-stats {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .er-stat {
            min-width: 108px;
            padding: 10px 14px;
            border-radius: 10px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(197,168,100,0.35);
            backdrop-filter: blur(4px);
        }
        .er-stat strong {
            display: block;
            font-size: 1.25rem;
            line-height: 1.1;
            color: var(--er-gold-soft);
            font-variant-numeric: tabular-nums;
        }
        .er-stat span {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: rgba(255,255,255,0.7);
        }
        .er-shell {
            padding: 0 28px;
            margin-top: -14px;
            position: relative;
            z-index: 2;
        }
        .er-panel {
            background: #fff;
            border: 1px solid var(--er-line);
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(17, 41, 75, 0.08);
            overflow: hidden;
        }
        .er-tabs {
            display: flex;
            gap: 0;
            border-bottom: 1px solid var(--er-line);
            background: #fafbfc;
            padding: 0 8px;
        }
        .er-tab {
            appearance: none;
            border: 0;
            background: transparent;
            color: var(--er-muted);
            font-size: 0.9rem;
            font-weight: 600;
            padding: 14px 18px;
            cursor: pointer;
            position: relative;
            border-bottom: 3px solid transparent;
            margin-bottom: -1px;
        }
        .er-tab:hover { color: var(--er-navy); }
        .er-tab.active {
            color: var(--er-navy);
            border-bottom-color: var(--er-gold);
        }
        .er-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 18px;
            border-bottom: 1px solid var(--er-line);
        }
        .er-toolbar-meta {
            font-size: 0.82rem;
            color: var(--er-muted);
        }
        .er-toolbar-meta b { color: var(--er-navy); font-weight: 700; }
        .er-toolbar-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }
        .er-search {
            position: relative;
        }
        .er-search i {
            position: absolute;
            left: 11px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 14px;
            pointer-events: none;
        }
        .er-search input {
            width: 220px;
            max-width: 100%;
            height: 38px;
            padding: 8px 12px 8px 34px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13px;
            background: #fff;
        }
        .er-search input:focus {
            outline: none;
            border-color: var(--er-navy);
            box-shadow: 0 0 0 3px rgba(17,41,75,0.1);
        }
        .er-btn-primary {
            height: 38px;
            padding: 0 16px;
            border: none;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--er-navy) 0%, var(--er-navy-deep) 100%);
            color: #fff;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        .er-btn-primary:hover { filter: brightness(1.05); }
        .er-view { display: none; }
        .er-view.active { display: block; }
        .er-table-wrap {
            overflow: auto;
            max-height: calc(100vh - 320px);
            min-height: 280px;
        }
        .er-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        .er-table thead th {
            position: sticky;
            top: 0;
            z-index: 1;
            text-align: left;
            padding: 12px 16px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: var(--er-muted);
            background: #fff;
            border-bottom: 2px solid var(--er-gold);
            white-space: nowrap;
        }
        .er-table tbody td {
            padding: 14px 16px;
            border-bottom: 1px solid #edf2f7;
            color: var(--er-ink);
            vertical-align: middle;
        }
        .er-table tbody tr {
            transition: background 0.12s ease;
        }
        .er-table tbody tr:hover {
            background: rgba(197,168,100,0.08);
        }
        .er-code {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 52px;
            padding: 5px 10px;
            border-radius: 999px;
            background: rgba(17,41,75,0.08);
            color: var(--er-navy);
            font-weight: 700;
            font-size: 0.82rem;
            letter-spacing: 0.03em;
        }
        .er-name {
            font-weight: 700;
            color: var(--er-navy);
        }
        .er-sub {
            display: block;
            margin-top: 2px;
            font-size: 0.78rem;
            color: var(--er-muted);
            font-weight: 400;
        }
        .er-rate {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-weight: 700;
            font-size: 1.05rem;
            color: var(--er-navy);
            font-variant-numeric: tabular-nums;
        }
        .er-badge-base {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 999px;
            background: var(--er-gold);
            color: var(--er-navy);
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .er-badge-muted {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            background: #f1f5f9;
            color: #94a3b8;
            font-size: 0.72rem;
            font-weight: 600;
        }
        .er-badge-active {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 999px;
            background: #dcfce7;
            color: #166534;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .er-badge-inactive {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 999px;
            background: #fee2e2;
            color: #991b1b;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .er-row-inactive td { opacity: 0.72; }
        .er-actions {
            display: inline-flex;
            gap: 6px;
        }
        .er-actions button {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid var(--er-line);
            background: #fff;
            color: var(--er-navy);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
        }
        .er-actions button:hover {
            border-color: var(--er-gold);
            background: rgba(197,168,100,0.12);
        }
        .er-actions button.danger { color: #dc2626; }
        .er-actions button.danger:hover {
            border-color: #fecaca;
            background: #fef2f2;
        }
        .er-empty {
            text-align: center;
            padding: 48px 20px !important;
            color: var(--er-muted);
        }
        .er-empty i {
            display: block;
            font-size: 28px;
            color: var(--er-gold);
            margin-bottom: 8px;
        }
        #currencyModal .modal-header,
        #currencyRateModal .modal-header {
            background: var(--er-navy);
            color: #fff;
            border-bottom: 0;
        }
        #currencyModal .modal-header .close,
        #currencyRateModal .modal-header .close {
            color: #fff;
            opacity: 0.9;
            text-shadow: none;
        }
        #currencyModal .btn-primary,
        #currencyRateModal .btn-primary {
            background: var(--er-navy);
            border-color: var(--er-navy);
        }
        .er-check-inline {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            margin: 0;
            cursor: pointer;
        }
        .er-check-inline input { width: auto; margin: 0; }
        #metalRateModal .modal-header {
            background: var(--er-navy);
            color: #fff;
            border-bottom: 0;
        }
        #metalRateModal .modal-header .close { color: #fff; opacity: 0.9; text-shadow: none; }
        #metalRateModal .btn-primary { background: var(--er-navy); border-color: var(--er-navy); }
        .er-form-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }
        .er-form-row > label {
            width: 150px;
            margin: 0;
            font-size: 0.86rem;
            font-weight: 600;
            color: var(--er-ink);
            flex-shrink: 0;
        }
        .er-form-row > .er-field {
            flex: 1 1 0;
            min-width: 0;
        }
        .er-form-row.er-form-row-full > .er-field { flex: 1 1 100%; }
        .er-input-group {
            display: flex;
            align-items: stretch;
            gap: 6px;
        }
        .er-input-group .form-control { flex: 1 1 auto; min-width: 0; }
        .er-input-group .er-refresh-btn {
            width: 36px;
            flex-shrink: 0;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: #fff;
            color: var(--er-navy);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
        }
        .er-input-group .er-refresh-btn:hover {
            border-color: var(--er-gold);
            background: rgba(197,168,100,0.12);
        }
        .er-calc-field {
            background: #f8fafc;
            font-weight: 700;
            color: var(--er-navy);
            font-variant-numeric: tabular-nums;
        }
        .er-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0 18px;
        }
        @media (max-width: 575.98px) {
            .er-form-grid { grid-template-columns: 1fr; }
            .er-form-row > label { width: 120px; }
        }
        @media (max-width: 767.98px) {
            .er-hero, .er-shell { padding-left: 16px; padding-right: 16px; }
            .er-search input { width: 160px; }
            .er-table-wrap { max-height: none; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="layout-content">
    <div class="container-fluid flex-grow-1" style="padding-top:0;padding-bottom:0;">
        <div class="set-software-wrapper">
            <?php include __DIR__ . '/set-software-sidebar.php'; ?>
            <div class="set-software-main">
                <div class="exchange-rate-page">
                    <div class="er-hero">
                        <div class="er-hero-inner">
                            <div>
                                <h1>Exchange Rate</h1>
                                <p>Define currencies and conversion rates used on invoices, metal pricing, and branch reporting.</p>
                            </div>
                            <div class="er-hero-stats">
                                <div class="er-stat">
                                    <strong id="erCurrencyCount"><?php echo (int) $currencyCount; ?></strong>
                                    <span>Currencies</span>
                                </div>
                                <div class="er-stat">
                                    <strong id="erRateCount"><?php echo (int) $rateCount; ?></strong>
                                    <span>Rates</span>
                                </div>
                                <div class="er-stat">
                                    <strong id="erMetalRateCount"><?php echo (int) $metalRateCount; ?></strong>
                                    <span>Metal Rates</span>
                                </div>
                                <div class="er-stat">
                                    <strong id="erBaseLabel"><?php echo $baseCurrencyName !== '' ? htmlspecialchars($baseCurrencyName, ENT_QUOTES, 'UTF-8') : '—'; ?></strong>
                                    <span>Base</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" id="settingsBranchId" value="<?php echo (int) $settings_branch_id; ?>">

                    <div class="er-shell">
                        <div class="er-panel">
                            <div class="er-tabs" role="tablist">
                                <button type="button" class="er-tab active" data-er-tab="currency" role="tab" aria-selected="true">Currencies</button>
                                <button type="button" class="er-tab" data-er-tab="rates" role="tab" aria-selected="false">Exchange Rates</button>
                                <button type="button" class="er-tab" data-er-tab="metal" role="tab" aria-selected="false">Metal Exchange Rate</button>
                            </div>

                            <div class="er-view active" id="erViewCurrency" role="tabpanel">
                                <div class="er-toolbar">
                                    <div class="er-toolbar-meta">Master list of currencies. Mark one as <b>base</b> for conversions.</div>
                                    <div class="er-toolbar-actions">
                                        <div class="er-search">
                                            <i class="feather icon-search"></i>
                                            <input type="search" id="erCurrencySearch" placeholder="Search currency…" autocomplete="off">
                                        </div>
                                        <button type="button" class="er-btn-primary" onclick="openCurrencyModal()">
                                            <i class="feather icon-plus"></i> Add Currency
                                        </button>
                                    </div>
                                </div>
                                <div class="er-table-wrap">
                                    <table class="er-table">
                                        <thead>
                                            <tr>
                                                <th>Currency</th>
                                                <th>Decimals</th>
                                                <th>Symbol</th>
                                                <th>Description</th>
                                                <th>Status</th>
                                                <th style="width:100px;">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="currencyTableBody">
                                        <?php if (empty($currencyRows)): ?>
                                            <tr id="noCurrencyRow">
                                                <td colspan="6" class="er-empty">
                                                    <i class="feather icon-layers"></i>
                                                    No currencies yet. Add your first currency to get started.
                                                </td>
                                            </tr>
                                        <?php else: foreach ($currencyRows as $r):
                                            $isBase = !empty($r['is_base']);
                                            $searchBlob = strtolower(trim(($r['name'] ?? '') . ' ' . ($r['symbol'] ?? '') . ' ' . ($r['description'] ?? '')));
                                        ?>
                                            <tr id="currency_<?php echo (int) $r['id']; ?>" data-search="<?php echo htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8'); ?>">
                                                <td>
                                                    <span class="er-name"><?php echo htmlspecialchars((string) $r['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                </td>
                                                <td><?php echo (int) $r['decimal_places']; ?></td>
                                                <td><span class="er-code"><?php echo htmlspecialchars((string) (($r['symbol'] ?? '') !== '' ? $r['symbol'] : '—'), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                                <td><?php echo htmlspecialchars((string) (($r['description'] ?? '') !== '' ? $r['description'] : '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td>
                                                    <?php if ($isBase): ?>
                                                        <span class="er-badge-base"><i class="feather icon-check" style="width:12px;height:12px;"></i> Base</span>
                                                    <?php else: ?>
                                                        <span class="er-badge-muted">Secondary</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="er-actions">
                                                        <button type="button" title="Edit"
                                                            onclick="editCurrency(<?php echo (int) $r['id']; ?>, <?php echo htmlspecialchars(json_encode((string) $r['name']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo (int) $r['decimal_places']; ?>, <?php echo htmlspecialchars(json_encode((string) ($r['symbol'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode((string) ($r['description'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>, <?php echo (int) ($r['is_base'] ?? 0); ?>)">
                                                            <i class="feather icon-edit-2"></i>
                                                        </button>
                                                        <button type="button" class="danger" title="Delete" onclick="deleteCurrency(<?php echo (int) $r['id']; ?>)">
                                                            <i class="feather icon-trash-2"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="er-view" id="erViewRates" role="tabpanel">
                                <div class="er-toolbar">
                                    <div class="er-toolbar-meta">
                                        Rates relative to base currency
                                        <?php if ($baseCurrencyName !== ''): ?>
                                            <b id="erBaseHint"><?php echo htmlspecialchars($baseCurrencyName, ENT_QUOTES, 'UTF-8'); ?></b>
                                        <?php else: ?>
                                            <b id="erBaseHint">—</b>
                                        <?php endif; ?>.
                                    </div>
                                    <div class="er-toolbar-actions">
                                        <div class="er-search">
                                            <i class="feather icon-search"></i>
                                            <input type="search" id="erRateSearch" placeholder="Search rate…" autocomplete="off">
                                        </div>
                                        <button type="button" class="er-btn-primary" onclick="openCurrencyRateModal()">
                                            <i class="feather icon-plus"></i> Add Rate
                                        </button>
                                    </div>
                                </div>
                                <div class="er-table-wrap">
                                    <table class="er-table">
                                        <thead>
                                            <tr>
                                                <th>Currency</th>
                                                <th>Exchange Rate</th>
                                                <th>Description</th>
                                                <th>Status</th>
                                                <th style="width:100px;">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="currencyRateTableBody">
                                        <?php if (empty($rateRows)): ?>
                                            <tr id="noCurrencyRateRow">
                                                <td colspan="5" class="er-empty">
                                                    <i class="feather icon-trending-up"></i>
                                                    No exchange rates yet. Add a rate for each foreign currency.
                                                </td>
                                            </tr>
                                        <?php else: foreach ($rateRows as $r):
                                            $rateActive = ((int) ($r['status'] ?? 0) === 1);
                                            $searchBlob = strtolower(trim(($r['currency_name'] ?? '') . ' ' . ($r['rate'] ?? '') . ' ' . ($r['description'] ?? '') . ' ' . ($rateActive ? 'active' : 'inactive')));
                                        ?>
                                            <tr id="rate_<?php echo (int) $r['id']; ?>" class="<?php echo $rateActive ? '' : 'er-row-inactive'; ?>" data-search="<?php echo htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8'); ?>" data-status="<?php echo $rateActive ? '1' : '0'; ?>">
                                                <td>
                                                    <span class="er-name"><?php echo htmlspecialchars((string) $r['currency_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <span class="er-sub">vs base</span>
                                                </td>
                                                <td><span class="er-rate"><?php echo htmlspecialchars((string) $r['rate'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                                <td><?php echo htmlspecialchars((string) (($r['description'] ?? '') !== '' ? $r['description'] : '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td>
                                                    <?php if ($rateActive): ?>
                                                        <span class="er-badge-active">Active</span>
                                                    <?php else: ?>
                                                        <span class="er-badge-inactive">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="er-actions">
                                                        <button type="button" title="Edit"
                                                            onclick="editCurrencyRate(<?php echo (int) $r['id']; ?>, <?php echo (int) $r['currency_id']; ?>, <?php echo htmlspecialchars(json_encode((string) $r['rate']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode((string) ($r['description'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>, <?php echo $rateActive ? 1 : 0; ?>)">
                                                            <i class="feather icon-edit-2"></i>
                                                        </button>
                                                        <button type="button" class="danger" title="Delete" onclick="deleteCurrencyRate(<?php echo (int) $r['id']; ?>)">
                                                            <i class="feather icon-trash-2"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="er-view" id="erViewMetal" role="tabpanel">
                                <div class="er-toolbar">
                                    <div class="er-toolbar-meta">Daily metal rates by ounce, unit, and currency conversion.</div>
                                    <div class="er-toolbar-actions">
                                        <div class="er-search">
                                            <i class="feather icon-search"></i>
                                            <input type="search" id="erMetalRateSearch" placeholder="Search metal rate…" autocomplete="off">
                                        </div>
                                        <button type="button" class="er-btn-primary" onclick="openMetalRateModal()">
                                            <i class="feather icon-plus"></i> Add Metal Rate
                                        </button>
                                    </div>
                                </div>
                                <div class="er-table-wrap">
                                    <table class="er-table">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Metal</th>
                                                <th>Currency</th>
                                                <th>Ounce Rate</th>
                                                <th>Rate / Gram</th>
                                                <th>Status</th>
                                                <th style="width:100px;">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="metalRateTableBody">
                                        <?php if (empty($metalRateRows)): ?>
                                            <tr id="noMetalRateRow">
                                                <td colspan="7" class="er-empty">
                                                    <i class="feather icon-activity"></i>
                                                    No metal exchange rates yet. Add your first metal rate.
                                                </td>
                                            </tr>
                                        <?php else: foreach ($metalRateRows as $r):
                                            $mrActive = ((int) ($r['status'] ?? 0) === 1);
                                            $metalName = (string) ($r['metal_name'] ?? '—');
                                            $currencyName = (string) ($r['currency_name'] ?? '—');
                                            $rateDateDisp = !empty($r['rate_date']) ? date('d/m/Y', strtotime((string) $r['rate_date'])) : '—';
                                            $searchBlob = strtolower(trim(
                                                ($r['rate_date'] ?? '') . ' ' . $metalName . ' ' . $currencyName . ' '
                                                . ($r['ounce_rate'] ?? '') . ' ' . ($r['metal_rate_per_gram'] ?? '') . ' '
                                                . ($mrActive ? 'active' : 'inactive')
                                            ));
                                        ?>
                                            <tr id="metalRate_<?php echo (int) $r['id']; ?>" class="<?php echo $mrActive ? '' : 'er-row-inactive'; ?>" data-search="<?php echo htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8'); ?>" data-status="<?php echo $mrActive ? '1' : '0'; ?>">
                                                <td><?php echo htmlspecialchars($rateDateDisp, ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><span class="er-name"><?php echo htmlspecialchars($metalName, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                                <td><?php echo htmlspecialchars($currencyName, ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><span class="er-rate"><?php echo htmlspecialchars((string) $r['ounce_rate'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                                <td><span class="er-rate"><?php echo htmlspecialchars((string) $r['metal_rate_per_gram'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                                <td>
                                                    <?php if ($mrActive): ?>
                                                        <span class="er-badge-active">Active</span>
                                                    <?php else: ?>
                                                        <span class="er-badge-inactive">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="er-actions">
                                                        <button type="button" title="Edit"
                                                            onclick="editMetalRate(<?php echo (int) $r['id']; ?>, <?php echo htmlspecialchars(json_encode((string) ($r['rate_date'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>, <?php echo (int) $r['metal_id']; ?>, <?php echo htmlspecialchars(json_encode((string) $r['ounce_rate']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo (int) ($r['unit_id'] ?? 0); ?>, <?php echo htmlspecialchars(json_encode((string) ($r['unit_conversion_rate'] ?? '31.1035')), ENT_QUOTES, 'UTF-8'); ?>, <?php echo (int) $r['currency_id']; ?>, <?php echo htmlspecialchars(json_encode((string) ($r['rate_at'] ?? '1')), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode((string) ($r['metal_rate_per_gram'] ?? '0')), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode((string) ($r['description'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>, <?php echo $mrActive ? 1 : 0; ?>)">
                                                            <i class="feather icon-edit-2"></i>
                                                        </button>
                                                        <button type="button" class="danger" title="Delete" onclick="deleteMetalRate(<?php echo (int) $r['id']; ?>)">
                                                            <i class="feather icon-trash-2"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="currencyModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-md modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Add Currency</h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="currencyForm">
                    <input type="hidden" id="currencyId">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Name <?php echo masters_req(); ?></label>
                            <input type="text" id="currencyName" class="form-control" autocomplete="off">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>No Of Decimal <?php echo masters_req(); ?></label>
                            <input type="number" id="currencyDecimal" class="form-control" value="2" min="0" max="8">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Symbol</label>
                            <input type="text" id="currencySymbol" class="form-control" autocomplete="off">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Description</label>
                            <input type="text" id="currencyDesc" class="form-control" autocomplete="off">
                        </div>
                        <div class="col-md-12 form-group mb-0">
                            <label class="er-check-inline" for="currencyBase">
                                <input type="checkbox" id="currencyBase">
                                <span>Base Currency</span>
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="saveCurrency()">Save</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="currencyRateModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-md modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Add Exchange Rate</h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="currencyRateForm">
                    <input type="hidden" id="currencyRateId">
                    <div class="form-group">
                        <label>Currency <?php echo masters_req(); ?></label>
                        <select id="currencyRateCurrency" class="form-control">
                            <option value="">Select Currency</option>
                            <?php foreach ($currencyRows as $c): ?>
                                <option value="<?php echo (int) $c['id']; ?>"><?php echo htmlspecialchars((string) $c['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Rate <?php echo masters_req(); ?></label>
                        <input type="number" step="0.000001" id="currencyRateValue" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" id="currencyRateDesc" class="form-control" autocomplete="off">
                    </div>
                    <div class="form-group mb-0">
                        <label>Status <?php echo masters_req(); ?></label>
                        <select id="currencyRateStatus" class="form-control">
                            <option value="1" selected>Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="saveCurrencyRate()">Save</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="metalRateModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Exchange Rate</h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="metalRateForm">
                    <input type="hidden" id="metalRateId">
                    <input type="hidden" id="metalRateCurrencyRate" value="1">

                    <div class="er-form-row er-form-row-full">
                        <label for="metalRateDate">Date <?php echo masters_req(); ?></label>
                        <div class="er-field">
                            <div class="er-input-group">
                                <input type="date" id="metalRateDate" class="form-control" value="<?php echo htmlspecialchars($todayDate, ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="button" class="er-refresh-btn" title="Today" onclick="setMetalRateToday()"><i class="feather icon-calendar"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="er-form-grid">
                        <div class="er-form-row">
                            <label for="metalRateMetal">Metal <?php echo masters_req(); ?></label>
                            <div class="er-field">
                                <div class="er-input-group">
                                    <select id="metalRateMetal" class="form-control">
                                        <option value="">Select Metal</option>
                                        <?php foreach ($metalRows as $m): ?>
                                            <option value="<?php echo (int) $m['id']; ?>"><?php echo htmlspecialchars((string) ($m['display_name'] ?? $m['system_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="er-refresh-btn" title="Refresh ounce rate" onclick="refreshMetalRateLookup('metal')"><i class="feather icon-refresh-cw"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="er-form-row">
                            <label for="metalRateOunce">Ounce Rate <?php echo masters_req(); ?></label>
                            <div class="er-field">
                                <input type="number" step="0.000001" id="metalRateOunce" class="form-control">
                            </div>
                        </div>

                        <div class="er-form-row">
                            <label for="metalRateUnit">Unit <?php echo masters_req(); ?></label>
                            <div class="er-field">
                                <div class="er-input-group">
                                    <select id="metalRateUnit" class="form-control">
                                        <option value="">Select Unit</option>
                                        <?php foreach ($unitRows as $u): ?>
                                            <option value="<?php echo (int) $u['id']; ?>"<?php echo strtolower((string) $u['name']) === 'gram' ? ' selected' : ''; ?>><?php echo htmlspecialchars((string) $u['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="er-refresh-btn" title="Refresh unit conversion" onclick="refreshMetalRateLookup('unit')"><i class="feather icon-refresh-cw"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="er-form-row">
                            <label for="metalRateUnitConv">Unit Conversion Rate <?php echo masters_req(); ?></label>
                            <div class="er-field">
                                <input type="number" step="0.000001" id="metalRateUnitConv" class="form-control" value="31.1035">
                            </div>
                        </div>

                        <div class="er-form-row">
                            <label for="metalRateCurrency">Currency <?php echo masters_req(); ?></label>
                            <div class="er-field">
                                <div class="er-input-group">
                                    <select id="metalRateCurrency" class="form-control">
                                        <option value="">Select Currency</option>
                                        <?php foreach ($currencyRows as $c): ?>
                                            <option value="<?php echo (int) $c['id']; ?>"><?php echo htmlspecialchars((string) $c['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="er-refresh-btn" title="Refresh currency rate" onclick="refreshMetalRateLookup('currency')"><i class="feather icon-refresh-cw"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="er-form-row">
                            <label for="metalRateAt">Rate @ <?php echo masters_req(); ?></label>
                            <div class="er-field">
                                <input type="number" step="0.000001" id="metalRateAt" class="form-control" value="1">
                            </div>
                        </div>
                    </div>

                    <div class="er-form-row er-form-row-full">
                        <label for="metalRatePerGram">Metal Rate In Per Gram</label>
                        <div class="er-field">
                            <input type="text" id="metalRatePerGram" class="form-control er-calc-field" readonly>
                        </div>
                    </div>

                    <div class="row mt-2">
                        <div class="col-md-8 form-group">
                            <label>Description</label>
                            <input type="text" id="metalRateDesc" class="form-control" autocomplete="off">
                        </div>
                        <div class="col-md-4 form-group mb-0">
                            <label>Status <?php echo masters_req(); ?></label>
                            <select id="metalRateStatus" class="form-control">
                                <option value="1" selected>Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearMetalRateForm()">Clear</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="saveMetalRate()">Save</button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer-script.php'; ?>
<script>
window.ER_CURRENCY_RATES = <?php echo json_encode($currencyRateMap, JSON_UNESCAPED_UNICODE); ?>;
(function () {
    function showLoaderSafe() {
        if (typeof showLoader === 'function') showLoader();
    }
    function hideLoaderSafe() {
        if (typeof hideLoader === 'function') hideLoader();
    }
    function escAttr(s) {
        return String(s == null ? '' : s).replace(/\\/g, '\\\\').replace(/'/g, "\\'");
    }
    function escHtml(s) {
        return $('<div/>').text(s == null ? '' : String(s)).html();
    }
    function syncRateCurrencyOption(id, name) {
        var $sel = $('#currencyRateCurrency');
        var $opt = $sel.find('option[value="' + id + '"]');
        if ($opt.length) {
            $opt.text(name);
        } else {
            $sel.append($('<option/>').val(id).text(name));
        }
    }
    function refreshCounts() {
        var cur = $('#currencyTableBody tr[id^="currency_"]').length;
        var rates = $('#currencyRateTableBody tr[id^="rate_"][data-status="1"]').length;
        var metalRates = $('#metalRateTableBody tr[id^="metalRate_"][data-status="1"]').length;
        $('#erCurrencyCount').text(cur);
        $('#erRateCount').text(rates);
        $('#erMetalRateCount').text(metalRates);
        var $base = $('#currencyTableBody .er-badge-base').closest('tr').find('.er-name').first();
        var baseName = $base.length ? $.trim($base.text()) : '—';
        $('#erBaseLabel').text(baseName || '—');
        $('#erBaseHint').text(baseName || '—');
    }
    function filterRows(inputSel, tbodySel) {
        var q = $.trim($(inputSel).val() || '').toLowerCase();
        $(tbodySel + ' tr[data-search]').each(function () {
            var hay = ($(this).attr('data-search') || '');
            $(this).toggle(!q || hay.indexOf(q) !== -1);
        });
    }
    function currencyRowHtml(id, name, decimal, symbol, desc, isBase) {
        var search = (name + ' ' + symbol + ' ' + desc).toLowerCase();
        var status = isBase
            ? '<span class="er-badge-base"><i class="feather icon-check" style="width:12px;height:12px;"></i> Base</span>'
            : '<span class="er-badge-muted">Secondary</span>';
        return ''
            + '<tr id="currency_' + id + '" data-search="' + escHtml(search) + '">'
            + '<td><span class="er-name">' + escHtml(name) + '</span></td>'
            + '<td>' + decimal + '</td>'
            + '<td><span class="er-code">' + escHtml(symbol || '—') + '</span></td>'
            + '<td>' + escHtml(desc || '—') + '</td>'
            + '<td>' + status + '</td>'
            + '<td><div class="er-actions">'
            + '<button type="button" title="Edit" onclick="editCurrency(' + id + ',\'' + escAttr(name) + '\',\'' + decimal + '\',\'' + escAttr(symbol) + '\',\'' + escAttr(desc) + '\',\'' + isBase + '\')"><i class="feather icon-edit-2"></i></button>'
            + '<button type="button" class="danger" title="Delete" onclick="deleteCurrency(' + id + ')"><i class="feather icon-trash-2"></i></button>'
            + '</div></td></tr>';
    }
    function rateRowHtml(id, currencyId, currencyName, rate, desc, rowStatus) {
        var active = Number(rowStatus) === 1;
        var search = (currencyName + ' ' + rate + ' ' + desc + ' ' + (active ? 'active' : 'inactive')).toLowerCase();
        var statusHtml = active
            ? '<span class="er-badge-active">Active</span>'
            : '<span class="er-badge-inactive">Inactive</span>';
        return ''
            + '<tr id="rate_' + id + '" class="' + (active ? '' : 'er-row-inactive') + '" data-search="' + escHtml(search) + '" data-status="' + (active ? '1' : '0') + '">'
            + '<td><span class="er-name">' + escHtml(currencyName) + '</span><span class="er-sub">vs base</span></td>'
            + '<td><span class="er-rate">' + escHtml(rate) + '</span></td>'
            + '<td>' + escHtml(desc || '—') + '</td>'
            + '<td>' + statusHtml + '</td>'
            + '<td><div class="er-actions">'
            + '<button type="button" title="Edit" onclick="editCurrencyRate(' + id + ',' + currencyId + ',\'' + escAttr(rate) + '\',\'' + escAttr(desc) + '\',' + (active ? 1 : 0) + ')"><i class="feather icon-edit-2"></i></button>'
            + '<button type="button" class="danger" title="Delete" onclick="deleteCurrencyRate(' + id + ')"><i class="feather icon-trash-2"></i></button>'
            + '</div></td></tr>';
    }
    function formatRateDate(iso) {
        if (!iso) return '—';
        var p = String(iso).split('-');
        if (p.length !== 3) return iso;
        return p[2] + '/' + p[1] + '/' + p[0];
    }
    function getCurrencyExchangeRate(currencyId) {
        var map = window.ER_CURRENCY_RATES || {};
        var r = parseFloat(map[String(currencyId)] || map[currencyId] || '1');
        return r > 0 ? r : 1;
    }
    function calcMetalRatePerGram() {
        var ounce = parseFloat($('#metalRateOunce').val()) || 0;
        var unitConv = parseFloat($('#metalRateUnitConv').val()) || 0;
        var rateAt = parseFloat($('#metalRateAt').val()) || 0;
        var currencyId = $('#metalRateCurrency').val();
        var currencyRate = getCurrencyExchangeRate(currencyId);
        $('#metalRateCurrencyRate').val(String(currencyRate));
        if (ounce <= 0 || unitConv <= 0) {
            $('#metalRatePerGram').val('');
            return 0;
        }
        if (rateAt <= 0) rateAt = 1;
        var perGram = (ounce / unitConv) * rateAt * currencyRate;
        $('#metalRatePerGram').val(perGram.toFixed(6));
        return perGram;
    }

    $('.er-tab').on('click', function () {
        var tab = $(this).data('er-tab');
        $('.er-tab').removeClass('active').attr('aria-selected', 'false');
        $(this).addClass('active').attr('aria-selected', 'true');
        $('.er-view').removeClass('active');
        if (tab === 'rates') {
            $('#erViewRates').addClass('active');
        } else if (tab === 'metal') {
            $('#erViewMetal').addClass('active');
        } else {
            $('#erViewCurrency').addClass('active');
        }
    });
    $('#erCurrencySearch').on('input', function () { filterRows('#erCurrencySearch', '#currencyTableBody'); });
    $('#erRateSearch').on('input', function () { filterRows('#erRateSearch', '#currencyRateTableBody'); });
    $('#erMetalRateSearch').on('input', function () { filterRows('#erMetalRateSearch', '#metalRateTableBody'); });

    $('#metalRateOunce, #metalRateUnitConv, #metalRateAt, #metalRateCurrency').on('input change', calcMetalRatePerGram);
    $('#metalRateMetal, #metalRateUnit, #metalRateCurrency').on('change', function () {
        refreshMetalRateLookup('all');
    });

    window.openCurrencyModal = function () {
        $('#currencyForm')[0].reset();
        $('#currencyId').val('');
        $('#currencyDecimal').val('2');
        $('#currencyModal .modal-title').text('Add Currency');
        $('#currencyModal').modal('show');
    };

    window.editCurrency = function (id, name, decimal, symbol, desc, isBase) {
        $('#currencyId').val(id);
        $('#currencyName').val(name);
        $('#currencyDecimal').val(decimal);
        $('#currencySymbol').val(symbol);
        $('#currencyDesc').val(desc);
        $('#currencyBase').prop('checked', Number(isBase) === 1);
        $('#currencyModal .modal-title').text('Edit Currency');
        $('#currencyModal').modal('show');
    };

    window.saveCurrency = function () {
        var id = $('#currencyId').val();
        var name = $.trim($('#currencyName').val());
        var decimal = $('#currencyDecimal').val();
        var symbol = $.trim($('#currencySymbol').val());
        var desc = $.trim($('#currencyDesc').val());
        var isBase = $('#currencyBase').is(':checked') ? 1 : 0;
        if (name === '' || decimal === '') {
            alert('Name and No Of Decimal are required');
            return;
        }
        $.ajax({
            url: 'ajax/currency.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: id ? 'update' : 'add',
                id: id,
                name: name,
                decimal_places: decimal,
                symbol: symbol,
                description: desc,
                is_base: isBase
            },
            beforeSend: showLoaderSafe,
            success: function (res) {
                if (!res || res.status !== 'success') {
                    alert((res && res.message) || 'Save failed');
                    return;
                }
                $('#noCurrencyRow').remove();
                if (isBase === 1) {
                    $('#currencyTableBody .er-badge-base').each(function () {
                        $(this).replaceWith('<span class="er-badge-muted">Secondary</span>');
                    });
                }
                var row = currencyRowHtml(res.id, name, decimal, symbol, desc, isBase);
                if (id) {
                    $('#currency_' + res.id).replaceWith(row);
                } else {
                    $('#currencyTableBody').prepend(row);
                }
                syncRateCurrencyOption(res.id, name);
                refreshCounts();
                $('#currencyForm')[0].reset();
                $('#currencyId').val('');
                $('#currencyModal').modal('hide');
            },
            error: function () { alert('Server error'); },
            complete: hideLoaderSafe
        });
    };

    window.deleteCurrency = function (id) {
        if (!confirm('Delete this currency?')) return;
        $.ajax({
            url: 'ajax/currency.php',
            type: 'POST',
            dataType: 'json',
            data: { action: 'delete', id: id },
            beforeSend: showLoaderSafe,
            success: function (res) {
                if (res && res.status === 'success') {
                    $('#currency_' + id).remove();
                    $('#currencyRateCurrency option[value="' + id + '"]').remove();
                    if (!$('#currencyTableBody tr[id^="currency_"]').length) {
                        $('#currencyTableBody').html('<tr id="noCurrencyRow"><td colspan="6" class="er-empty"><i class="feather icon-layers"></i>No currencies yet. Add your first currency to get started.</td></tr>');
                    }
                    refreshCounts();
                } else {
                    alert((res && res.message) || 'Delete failed');
                }
            },
            error: function () { alert('Server error'); },
            complete: hideLoaderSafe
        });
    };

    window.openCurrencyRateModal = function () {
        $('#currencyRateForm')[0].reset();
        $('#currencyRateId').val('');
        $('#currencyRateStatus').val('1');
        $('#currencyRateModal .modal-title').text('Add Exchange Rate');
        $('#currencyRateModal').modal('show');
    };

    window.editCurrencyRate = function (id, currencyId, rate, desc, rowStatus) {
        $('#currencyRateId').val(id);
        $('#currencyRateCurrency').val(String(currencyId));
        $('#currencyRateValue').val(rate);
        $('#currencyRateDesc').val(desc);
        $('#currencyRateStatus').val(Number(rowStatus) === 0 ? '0' : '1');
        $('#currencyRateModal .modal-title').text('Edit Exchange Rate');
        $('#currencyRateModal').modal('show');
    };

    window.saveCurrencyRate = function () {
        var id = $('#currencyRateId').val();
        var currency = $('#currencyRateCurrency').val();
        var rate = $('#currencyRateValue').val();
        var desc = $.trim($('#currencyRateDesc').val());
        var rowStatus = $('#currencyRateStatus').val() === '0' ? 0 : 1;
        if (currency === '' || rate === '') {
            alert('Currency and rate are required');
            return;
        }
        var currencyName = $('#currencyRateCurrency option:selected').text();
        $.ajax({
            url: 'ajax/currency-exchange-rate.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: id ? 'update' : 'add',
                id: id,
                currency_id: currency,
                rate: rate,
                description: desc,
                status: rowStatus
            },
            beforeSend: showLoaderSafe,
            success: function (res) {
                if (!res || res.status !== 'success') {
                    alert((res && res.message) || 'Save failed');
                    return;
                }
                $('#noCurrencyRateRow').remove();
                var savedStatus = (typeof res.row_status !== 'undefined') ? res.row_status : rowStatus;
                var row = rateRowHtml(res.id, currency, currencyName, rate, desc, savedStatus);
                if (id) {
                    $('#rate_' + res.id).replaceWith(row);
                } else {
                    $('#currencyRateTableBody').prepend(row);
                }
                refreshCounts();
                $('#currencyRateForm')[0].reset();
                $('#currencyRateId').val('');
                window.ER_CURRENCY_RATES = window.ER_CURRENCY_RATES || {};
                window.ER_CURRENCY_RATES[String(currency)] = rate;
                $('#currencyRateModal').modal('hide');
            },
            error: function () { alert('Server error'); },
            complete: hideLoaderSafe
        });
    };

    window.deleteCurrencyRate = function (id) {
        if (!confirm('Set this exchange rate to Inactive?')) return;
        $.ajax({
            url: 'ajax/currency-exchange-rate.php',
            type: 'POST',
            dataType: 'json',
            data: { action: 'delete', id: id },
            beforeSend: showLoaderSafe,
            success: function (res) {
                if (res && res.status === 'success') {
                    var $tr = $('#rate_' + id);
                    if ($tr.length) {
                        var currencyId = 0;
                        var rate = $.trim($tr.find('.er-rate').text());
                        var desc = $.trim($tr.find('td').eq(2).text());
                        if (desc === '—') desc = '';
                        var currencyName = $.trim($tr.find('.er-name').text());
                        var onclick = $tr.find('button[title="Edit"]').attr('onclick') || '';
                        var m = onclick.match(/editCurrencyRate\(\s*(\d+)\s*,\s*(\d+)/);
                        if (m) currencyId = parseInt(m[2], 10) || 0;
                        $tr.replaceWith(rateRowHtml(id, currencyId, currencyName, rate, desc, 0));
                    }
                    refreshCounts();
                } else {
                    alert((res && res.message) || 'Delete failed');
                }
            },
            error: function () { alert('Server error'); },
            complete: hideLoaderSafe
        });
    };

    window.setMetalRateToday = function () {
        var d = new Date();
        var iso = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        $('#metalRateDate').val(iso);
    };

    window.clearMetalRateForm = function () {
        $('#metalRateForm')[0].reset();
        $('#metalRateId').val('');
        $('#metalRateUnitConv').val('31.1035');
        $('#metalRateAt').val('1');
        $('#metalRateStatus').val('1');
        setMetalRateToday();
        $('#metalRatePerGram').val('');
        $('#metalRateCurrencyRate').val('1');
    };

    window.refreshMetalRateLookup = function (scope) {
        scope = scope || 'all';
        var metalId = $('#metalRateMetal').val();
        var unitId = $('#metalRateUnit').val();
        var currencyId = $('#metalRateCurrency').val();
        if (scope === 'currency' && currencyId) {
            var cr = getCurrencyExchangeRate(currencyId);
            $('#metalRateCurrencyRate').val(String(cr));
            calcMetalRatePerGram();
            return;
        }
        if (scope === 'all' || scope === 'metal' || scope === 'unit' || scope === 'currency') {
            $.ajax({
                url: 'ajax/metal-exchange-rate.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'lookup',
                    metal_id: metalId || '',
                    unit_id: unitId || '',
                    currency_id: currencyId || ''
                },
                success: function (res) {
                    if (!res || res.status !== 'success') return;
                    if ((scope === 'all' || scope === 'metal') && res.ounce_rate && parseFloat(res.ounce_rate) > 0) {
                        $('#metalRateOunce').val(res.ounce_rate);
                    }
                    if ((scope === 'all' || scope === 'unit') && res.unit_conversion_rate && parseFloat(res.unit_conversion_rate) > 0) {
                        $('#metalRateUnitConv').val(res.unit_conversion_rate);
                    }
                    if ((scope === 'all' || scope === 'currency') && res.currency_rate && parseFloat(res.currency_rate) > 0) {
                        $('#metalRateCurrencyRate').val(res.currency_rate);
                        if (currencyId) {
                            window.ER_CURRENCY_RATES = window.ER_CURRENCY_RATES || {};
                            window.ER_CURRENCY_RATES[String(currencyId)] = res.currency_rate;
                        }
                    }
                    calcMetalRatePerGram();
                }
            });
        }
    };

    window.openMetalRateModal = function () {
        clearMetalRateForm();
        $('#metalRateModal .modal-title').text('Exchange Rate');
        $('#metalRateModal').modal('show');
        refreshMetalRateLookup('all');
    };

    window.editMetalRate = function (id, rateDate, metalId, ounce, unitId, unitConv, currencyId, rateAt, perGram, desc, rowStatus) {
        $('#metalRateId').val(id);
        $('#metalRateDate').val(rateDate);
        $('#metalRateMetal').val(String(metalId));
        $('#metalRateOunce').val(ounce);
        $('#metalRateUnit').val(unitId ? String(unitId) : '');
        $('#metalRateUnitConv').val(unitConv || '31.1035');
        $('#metalRateCurrency').val(String(currencyId));
        $('#metalRateAt').val(rateAt || '1');
        $('#metalRateDesc').val(desc || '');
        $('#metalRateStatus').val(Number(rowStatus) === 0 ? '0' : '1');
        $('#metalRateCurrencyRate').val(String(getCurrencyExchangeRate(currencyId)));
        $('#metalRatePerGram').val(perGram || '');
        $('#metalRateModal .modal-title').text('Edit Exchange Rate');
        $('#metalRateModal').modal('show');
        calcMetalRatePerGram();
    };

    window.saveMetalRate = function () {
        var id = $('#metalRateId').val();
        var rateDate = $('#metalRateDate').val();
        var metalId = $('#metalRateMetal').val();
        var ounce = $('#metalRateOunce').val();
        var unitId = $('#metalRateUnit').val();
        var unitConv = $('#metalRateUnitConv').val();
        var currencyId = $('#metalRateCurrency').val();
        var rateAt = $('#metalRateAt').val();
        var desc = $.trim($('#metalRateDesc').val());
        var rowStatus = $('#metalRateStatus').val() === '0' ? 0 : 1;
        if (!rateDate || !metalId || !currencyId || !ounce || !unitConv) {
            alert('Date, metal, currency, ounce rate, and unit conversion rate are required');
            return;
        }
        var metalName = $('#metalRateMetal option:selected').text();
        var currencyName = $('#metalRateCurrency option:selected').text();
        $.ajax({
            url: 'ajax/metal-exchange-rate.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: id ? 'update' : 'add',
                id: id,
                rate_date: rateDate,
                metal_id: metalId,
                ounce_rate: ounce,
                unit_id: unitId,
                unit_conversion_rate: unitConv,
                currency_id: currencyId,
                rate_at: rateAt || 1,
                description: desc,
                status: rowStatus
            },
            beforeSend: showLoaderSafe,
            success: function (res) {
                if (!res || res.status !== 'success') {
                    alert((res && res.message) || 'Save failed');
                    return;
                }
                $('#noMetalRateRow').remove();
                var perGram = res.metal_rate_per_gram || $('#metalRatePerGram').val();
                var row = ''
                    + '<tr id="metalRate_' + res.id + '" class="' + (rowStatus ? '' : 'er-row-inactive') + '" data-search="' + escHtml((rateDate + ' ' + metalName + ' ' + currencyName + ' ' + ounce + ' ' + perGram).toLowerCase()) + '" data-status="' + (rowStatus ? '1' : '0') + '">'
                    + '<td>' + escHtml(formatRateDate(rateDate)) + '</td>'
                    + '<td><span class="er-name">' + escHtml(metalName) + '</span></td>'
                    + '<td>' + escHtml(currencyName) + '</td>'
                    + '<td><span class="er-rate">' + escHtml(ounce) + '</span></td>'
                    + '<td><span class="er-rate">' + escHtml(perGram) + '</span></td>'
                    + '<td>' + (rowStatus ? '<span class="er-badge-active">Active</span>' : '<span class="er-badge-inactive">Inactive</span>') + '</td>'
                    + '<td><div class="er-actions">'
                    + '<button type="button" title="Edit" onclick="editMetalRate(' + res.id + ',\'' + escAttr(rateDate) + '\',' + metalId + ',\'' + escAttr(ounce) + '\',' + (unitId || 0) + ',\'' + escAttr(unitConv) + '\',' + currencyId + ',\'' + escAttr(rateAt || '1') + '\',\'' + escAttr(perGram) + '\',\'' + escAttr(desc) + '\',' + rowStatus + ')"><i class="feather icon-edit-2"></i></button>'
                    + '<button type="button" class="danger" title="Delete" onclick="deleteMetalRate(' + res.id + ')"><i class="feather icon-trash-2"></i></button>'
                    + '</div></td></tr>';
                if (id) {
                    $('#metalRate_' + res.id).replaceWith(row);
                } else {
                    $('#metalRateTableBody').prepend(row);
                }
                refreshCounts();
                clearMetalRateForm();
                $('#metalRateModal').modal('hide');
            },
            error: function () { alert('Server error'); },
            complete: hideLoaderSafe
        });
    };

    window.deleteMetalRate = function (id) {
        if (!confirm('Set this metal exchange rate to Inactive?')) return;
        $.ajax({
            url: 'ajax/metal-exchange-rate.php',
            type: 'POST',
            dataType: 'json',
            data: { action: 'delete', id: id },
            beforeSend: showLoaderSafe,
            success: function (res) {
                if (res && res.status === 'success') {
                    var $tr = $('#metalRate_' + id);
                    if ($tr.length) {
                        var editOnclick = $tr.find('button[title="Edit"]').attr('onclick') || '';
                        var em = editOnclick.match(/editMetalRate\(\s*(\d+)\s*,\s*'([^']*)'\s*,\s*(\d+)\s*,\s*'([^']*)'\s*,\s*(\d+)\s*,\s*'([^']*)'\s*,\s*(\d+)\s*,\s*'([^']*)'\s*,\s*'([^']*)'\s*,\s*'([^']*)'\s*,\s*(\d+)\s*\)/);
                        if (em) {
                            var dateTxt = $.trim($tr.find('td').eq(0).text());
                            var metalName = $.trim($tr.find('.er-name').text());
                            var currencyName = $.trim($tr.find('td').eq(2).text());
                            var ounce = $.trim($tr.find('.er-rate').eq(0).text());
                            var perGram = $.trim($tr.find('.er-rate').eq(1).text());
                            var search = (em[2] + ' ' + metalName + ' ' + currencyName + ' ' + ounce + ' ' + perGram + ' inactive').toLowerCase();
                            $tr.replaceWith(
                                '<tr id="metalRate_' + id + '" class="er-row-inactive" data-search="' + escHtml(search) + '" data-status="0">'
                                + '<td>' + escHtml(dateTxt) + '</td>'
                                + '<td><span class="er-name">' + escHtml(metalName) + '</span></td>'
                                + '<td>' + escHtml(currencyName) + '</td>'
                                + '<td><span class="er-rate">' + escHtml(ounce) + '</span></td>'
                                + '<td><span class="er-rate">' + escHtml(perGram) + '</span></td>'
                                + '<td><span class="er-badge-inactive">Inactive</span></td>'
                                + '<td><div class="er-actions">'
                                + '<button type="button" title="Edit" onclick="editMetalRate(' + id + ',\'' + escAttr(em[2]) + '\',' + em[3] + ',\'' + escAttr(em[4]) + '\',' + em[5] + ',\'' + escAttr(em[6]) + '\',' + em[7] + ',\'' + escAttr(em[8]) + '\',\'' + escAttr(em[9]) + '\',\'' + escAttr(em[10]) + '\',0)"><i class="feather icon-edit-2"></i></button>'
                                + '<button type="button" class="danger" title="Delete" onclick="deleteMetalRate(' + id + ')"><i class="feather icon-trash-2"></i></button>'
                                + '</div></td></tr>'
                            );
                        } else {
                            $tr.addClass('er-row-inactive').attr('data-status', '0');
                            $tr.find('.er-badge-active').replaceWith('<span class="er-badge-inactive">Inactive</span>');
                        }
                    }
                    refreshCounts();
                } else {
                    alert((res && res.message) || 'Delete failed');
                }
            },
            error: function () { alert('Server error'); },
            complete: hideLoaderSafe
        });
    };
})();
</script>
</body>
</html>
