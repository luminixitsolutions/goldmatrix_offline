<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    header('Location: index.php');
    exit;
}

$tab = isset($_GET['tab']) ? (string) $_GET['tab'] : 'tax-return';
if (!preg_match('/^(tax-return|input|output|planet-reconciliation)$/', $tab)) {
    $tab = 'tax-return';
}

$start_date = isset($_GET['start_date']) ? esc($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? esc($_GET['end_date']) : date('Y-m-t');

$ledger_rows = [];
$ledger_totals = ['bill' => 0.0, 'tax' => 0.0, 'taxable' => 0.0];
$planet_rows = [];
$planet_totals = ['bill' => 0.0, 'tax' => 0.0, 'taxable' => 0.0];

/** UAE VAT-style figures for Tax Return tab (from sale / purchase invoice lines in the selected period). */
$trv = [
    'em' => [
        'AD' => ['amt' => 0.0, 'tax' => 0.0],
        'DU' => ['amt' => 0.0, 'tax' => 0.0],
        'SH' => ['amt' => 0.0, 'tax' => 0.0],
        'AJ' => ['amt' => 0.0, 'tax' => 0.0],
        'UAQ' => ['amt' => 0.0, 'tax' => 0.0],
        'RAK' => ['amt' => 0.0, 'tax' => 0.0],
        'FU' => ['amt' => 0.0, 'tax' => 0.0],
        'NONE' => ['amt' => 0.0, 'tax' => 0.0],
    ],
    'tourist_amt' => 0.0,
    'tourist_tax' => 0.0,
    'sales_reverse_amt' => 0.0,
    'sales_reverse_tax' => 0.0,
    'zero_amt' => 0.0,
    'zero_tax' => 0.0,
    'exempt_amt' => 0.0,
    'exempt_tax' => 0.0,
    'import_amt' => 0.0,
    'import_tax' => 0.0,
    'import_adj_amt' => 0.0,
    'import_adj_tax' => 0.0,
    'exp_std_amt' => 0.0,
    'exp_std_tax' => 0.0,
    'exp_rev_amt' => 0.0,
    'exp_rev_tax' => 0.0,
    'total_output_vat' => 0.0,
    'total_input_vat' => 0.0,
    'payable_vat' => 0.0,
];

$tr_supplies_total_amt = 0.0;
$tr_supplies_total_tax = 0.0;
$tr_exp_total_amt = 0.0;
$tr_exp_total_tax = 0.0;

$tr_si_br = function_exists('auragold_sale_invoices_branch_where_sql') ? auragold_sale_invoices_branch_where_sql($conn, 'si') : '';
$tr_pi_br = function_exists('auragold_purchase_invoices_branch_where_sql') ? auragold_purchase_invoices_branch_where_sql($conn, 'pi') : '';

if ($tab === 'tax-return') {
    $sd = esc($start_date);
    $ed = esc($end_date);
    $st_expr = "COALESCE(NULLIF(TRIM(c.billing_state), ''), NULLIF(TRIM(c.shipping_state), ''), '')";

    $row_std = getRecord("
        SELECT
            SUM(CASE WHEN b = 'AD' THEN na ELSE 0 END) AS ad_amt,
            SUM(CASE WHEN b = 'AD' THEN ta ELSE 0 END) AS ad_tax,
            SUM(CASE WHEN b = 'DU' THEN na ELSE 0 END) AS du_amt,
            SUM(CASE WHEN b = 'DU' THEN ta ELSE 0 END) AS du_tax,
            SUM(CASE WHEN b = 'SH' THEN na ELSE 0 END) AS sh_amt,
            SUM(CASE WHEN b = 'SH' THEN ta ELSE 0 END) AS sh_tax,
            SUM(CASE WHEN b = 'AJ' THEN na ELSE 0 END) AS aj_amt,
            SUM(CASE WHEN b = 'AJ' THEN ta ELSE 0 END) AS aj_tax,
            SUM(CASE WHEN b = 'UAQ' THEN na ELSE 0 END) AS uaq_amt,
            SUM(CASE WHEN b = 'UAQ' THEN ta ELSE 0 END) AS uaq_tax,
            SUM(CASE WHEN b = 'RAK' THEN na ELSE 0 END) AS rak_amt,
            SUM(CASE WHEN b = 'RAK' THEN ta ELSE 0 END) AS rak_tax,
            SUM(CASE WHEN b = 'FU' THEN na ELSE 0 END) AS fu_amt,
            SUM(CASE WHEN b = 'FU' THEN ta ELSE 0 END) AS fu_tax,
            SUM(CASE WHEN b = 'NONE' THEN na ELSE 0 END) AS none_amt,
            SUM(CASE WHEN b = 'NONE' THEN ta ELSE 0 END) AS none_tax
        FROM (
            SELECT sii.net_amount AS na, sii.tax_amount AS ta,
                CASE
                    WHEN $st_expr = '' THEN 'NONE'
                    WHEN LOWER($st_expr) LIKE '%abu%dhabi%' OR LOWER(REPLACE($st_expr, ' ', '')) IN ('ad','abudhabi') THEN 'AD'
                    WHEN LOWER($st_expr) LIKE '%dubai%' THEN 'DU'
                    WHEN LOWER($st_expr) LIKE '%sharjah%' THEN 'SH'
                    WHEN LOWER($st_expr) LIKE '%ajman%' THEN 'AJ'
                    WHEN LOWER($st_expr) LIKE '%umm%quwain%' OR LOWER(REPLACE($st_expr, ' ', '')) = 'uaq' THEN 'UAQ'
                    WHEN LOWER($st_expr) LIKE '%ras%khaimah%' OR LOWER($st_expr) LIKE '%al%khaimah%' OR LOWER(REPLACE($st_expr, ' ', '')) IN ('rak','rak,uae') THEN 'RAK'
                    WHEN LOWER($st_expr) LIKE '%fujairah%' THEN 'FU'
                    ELSE 'NONE'
                END AS b
            FROM tbl_sale_invoice_items sii
            INNER JOIN tbl_sale_invoices si ON sii.invoice_id = si.id
            LEFT JOIN tbl_customers c ON si.customer_id = c.id
            WHERE sii.status = 1
            AND si.status != 'cancelled'
            AND DATE(si.invoice_date) BETWEEN '$sd' AND '$ed'
            $tr_si_br
            AND sii.tax_amount > 0.0005
        ) AS em_rows
    ");
    if ($row_std) {
        $trv['em']['AD']['amt'] = (float) ($row_std['ad_amt'] ?? 0);
        $trv['em']['AD']['tax'] = (float) ($row_std['ad_tax'] ?? 0);
        $trv['em']['DU']['amt'] = (float) ($row_std['du_amt'] ?? 0);
        $trv['em']['DU']['tax'] = (float) ($row_std['du_tax'] ?? 0);
        $trv['em']['SH']['amt'] = (float) ($row_std['sh_amt'] ?? 0);
        $trv['em']['SH']['tax'] = (float) ($row_std['sh_tax'] ?? 0);
        $trv['em']['AJ']['amt'] = (float) ($row_std['aj_amt'] ?? 0);
        $trv['em']['AJ']['tax'] = (float) ($row_std['aj_tax'] ?? 0);
        $trv['em']['UAQ']['amt'] = (float) ($row_std['uaq_amt'] ?? 0);
        $trv['em']['UAQ']['tax'] = (float) ($row_std['uaq_tax'] ?? 0);
        $trv['em']['RAK']['amt'] = (float) ($row_std['rak_amt'] ?? 0);
        $trv['em']['RAK']['tax'] = (float) ($row_std['rak_tax'] ?? 0);
        $trv['em']['FU']['amt'] = (float) ($row_std['fu_amt'] ?? 0);
        $trv['em']['FU']['tax'] = (float) ($row_std['fu_tax'] ?? 0);
        $trv['em']['NONE']['amt'] = (float) ($row_std['none_amt'] ?? 0);
        $trv['em']['NONE']['tax'] = (float) ($row_std['none_tax'] ?? 0);
    }

    $row_novat = getRecord("
        SELECT COALESCE(SUM(sii.net_amount), 0) AS net_amt, COALESCE(SUM(sii.tax_amount), 0) AS tax_amt
        FROM tbl_sale_invoice_items sii
        INNER JOIN tbl_sale_invoices si ON sii.invoice_id = si.id
        WHERE sii.status = 1
        AND si.status != 'cancelled'
        AND DATE(si.invoice_date) BETWEEN '$sd' AND '$ed'
        $tr_si_br
        AND ABS(COALESCE(sii.tax_amount, 0)) <= 0.0005
        AND COALESCE(sii.net_amount, 0) > 0
    ");
    if ($row_novat) {
        $trv['exempt_amt'] = (float) ($row_novat['net_amt'] ?? 0);
        $trv['exempt_tax'] = (float) ($row_novat['tax_amt'] ?? 0);
    }

    $out_tax = getRecord("
        SELECT COALESCE(SUM(sii.tax_amount), 0) AS t
        FROM tbl_sale_invoice_items sii
        INNER JOIN tbl_sale_invoices si ON sii.invoice_id = si.id
        WHERE sii.status = 1 AND si.status != 'cancelled'
        AND DATE(si.invoice_date) BETWEEN '$sd' AND '$ed'
        $tr_si_br
    ");
    $in_tax = getRecord("
        SELECT COALESCE(SUM(pii.tax_amount), 0) AS t
        FROM tbl_purchase_invoice_items pii
        INNER JOIN tbl_purchase_invoices pi ON pii.invoice_id = pi.id
        WHERE pii.status = 1 AND pi.status != 'cancelled'
        AND DATE(pi.invoice_date) BETWEEN '$sd' AND '$ed'
        $tr_pi_br
    ");
    $exp_std = getRecord("
        SELECT COALESCE(SUM(pii.net_amount), 0) AS a, COALESCE(SUM(pii.tax_amount), 0) AS t
        FROM tbl_purchase_invoice_items pii
        INNER JOIN tbl_purchase_invoices pi ON pii.invoice_id = pi.id
        WHERE pii.status = 1 AND pi.status != 'cancelled'
        AND DATE(pi.invoice_date) BETWEEN '$sd' AND '$ed'
        $tr_pi_br
        AND pii.tax_amount > 0.0005
    ");
    $exp_rev = getRecord("
        SELECT COALESCE(SUM(pii.net_amount), 0) AS a, COALESCE(SUM(IFNULL(pii.reverse, 0)), 0) AS r
        FROM tbl_purchase_invoice_items pii
        INNER JOIN tbl_purchase_invoices pi ON pii.invoice_id = pi.id
        WHERE pii.status = 1 AND pi.status != 'cancelled'
        AND DATE(pi.invoice_date) BETWEEN '$sd' AND '$ed'
        $tr_pi_br
        AND IFNULL(pii.reverse, 0) > 0.0005
    ");
    if ($out_tax) {
        $trv['total_output_vat'] = (float) ($out_tax['t'] ?? 0);
    }
    if ($in_tax) {
        $trv['total_input_vat'] = (float) ($in_tax['t'] ?? 0);
    }
    if ($exp_std) {
        $trv['exp_std_amt'] = (float) ($exp_std['a'] ?? 0);
        $trv['exp_std_tax'] = (float) ($exp_std['t'] ?? 0);
    }
    if ($exp_rev) {
        $trv['exp_rev_amt'] = (float) ($exp_rev['a'] ?? 0);
        $trv['exp_rev_tax'] = (float) ($exp_rev['r'] ?? 0);
    }
    $trv['payable_vat'] = $trv['total_output_vat'] - $trv['total_input_vat'];

    $tr_supplies_total_amt = $trv['zero_amt'] + $trv['exempt_amt'] + $trv['import_amt'] + $trv['import_adj_amt'];
    $tr_supplies_total_tax = $trv['zero_tax'] + $trv['exempt_tax'] + $trv['import_tax'] + $trv['import_adj_tax'];
    $tr_exp_total_amt = $trv['exp_std_amt'] + $trv['exp_rev_amt'];
    $tr_exp_total_tax = $trv['exp_std_tax'] + $trv['exp_rev_tax'];
}

if ($tab === 'output') {
    $q = "
        SELECT si.id AS invoice_id, si.invoice_date AS date, 'Sales Invoice' AS voucher_type, si.invoice_no AS voucher_no,
               si.customer_name AS ledger,
               COALESCE(NULLIF(TRIM(MAX(c.billing_state)), ''), NULLIF(TRIM(MAX(c.shipping_state)), ''), '') AS state,
               COALESCE(NULLIF(TRIM(MAX(c.trade_no)), ''), NULLIF(TRIM(MAX(c.registration_no)), ''), '') AS trn,
               si.grand_total AS bill_amt,
               COALESCE(SUM(sii.tax_amount), 0) AS tax_amt, si.net_total AS taxable_amt
        FROM tbl_sale_invoices si
        LEFT JOIN tbl_sale_invoice_items sii ON si.id = sii.invoice_id AND sii.status = 1
        LEFT JOIN tbl_customers c ON si.customer_id = c.id
        WHERE si.status != 'cancelled'
        AND DATE(si.invoice_date) BETWEEN '$start_date' AND '$end_date'
        $tr_si_br
        GROUP BY si.id, si.invoice_date, si.invoice_no, si.customer_name, si.grand_total, si.net_total
        ORDER BY si.invoice_date DESC, si.id DESC
        LIMIT 200
    ";
    $ledger_rows = getList($q);
    $tr = getRecord("
        SELECT COALESCE(SUM(si.grand_total), 0) AS b,
               COALESCE(SUM(sii.tax_amount), 0) AS t,
               COALESCE(SUM(si.net_total), 0) AS x
        FROM tbl_sale_invoices si
        LEFT JOIN tbl_sale_invoice_items sii ON si.id = sii.invoice_id AND sii.status = 1
        WHERE si.status != 'cancelled'
        AND DATE(si.invoice_date) BETWEEN '$start_date' AND '$end_date'
        $tr_si_br
    ");
    if ($tr) {
        $ledger_totals['bill'] = (float) ($tr['b'] ?? 0);
        $ledger_totals['tax'] = (float) ($tr['t'] ?? 0);
        $ledger_totals['taxable'] = (float) ($tr['x'] ?? 0);
    }
} elseif ($tab === 'input') {
    $q = "
        SELECT pi.id AS invoice_id, pi.invoice_date AS date, 'Purchase Invoice' AS voucher_type, pi.invoice_no AS voucher_no,
               pi.supplier_name AS ledger,
               COALESCE(NULLIF(TRIM(MAX(c.billing_state)), ''), NULLIF(TRIM(MAX(c.shipping_state)), ''), '') AS state,
               COALESCE(NULLIF(TRIM(MAX(c.trade_no)), ''), NULLIF(TRIM(MAX(c.registration_no)), ''), '') AS trn,
               pi.grand_total AS bill_amt,
               COALESCE(SUM(pii.tax_amount), 0) AS tax_amt, pi.net_total AS taxable_amt
        FROM tbl_purchase_invoices pi
        LEFT JOIN tbl_purchase_invoice_items pii ON pi.id = pii.invoice_id AND pii.status = 1
        LEFT JOIN tbl_customers c ON pi.supplier_id = c.id
        WHERE pi.status != 'cancelled'
        AND DATE(pi.invoice_date) BETWEEN '$start_date' AND '$end_date'
        $tr_pi_br
        GROUP BY pi.id, pi.invoice_date, pi.invoice_no, pi.supplier_name, pi.grand_total, pi.net_total
        ORDER BY pi.invoice_date DESC, pi.id DESC
        LIMIT 200
    ";
    $ledger_rows = getList($q);
    $tr = getRecord("
        SELECT COALESCE(SUM(pi.grand_total), 0) AS b,
               COALESCE(SUM(pii.tax_amount), 0) AS t,
               COALESCE(SUM(pi.net_total), 0) AS x
        FROM tbl_purchase_invoices pi
        LEFT JOIN tbl_purchase_invoice_items pii ON pi.id = pii.invoice_id AND pii.status = 1
        WHERE pi.status != 'cancelled'
        AND DATE(pi.invoice_date) BETWEEN '$start_date' AND '$end_date'
        $tr_pi_br
    ");
    if ($tr) {
        $ledger_totals['bill'] = (float) ($tr['b'] ?? 0);
        $ledger_totals['tax'] = (float) ($tr['t'] ?? 0);
        $ledger_totals['taxable'] = (float) ($tr['x'] ?? 0);
    }
} elseif ($tab === 'planet-reconciliation') {
    /** Planet reconciliation — connect to FTA Planet / your bridge when ready */
    $planet_rows = [];
    $planet_totals = ['bill' => 0.0, 'tax' => 0.0, 'taxable' => 0.0];
}

$DASHBOARD_PAGE_TITLE = 'Tax Return';
$DASHBOARD_EXTRA_CSS = <<<'HTML'
<style>
    .tb-wrap { max-width: 100%; --tb-gold: #c9a227; --tb-gold-mid: #b8941f; --tb-gold-dark: #8b6914; --tb-navy: #11294b; --tb-navy-deep: #0c1f38; }
    .tb-page-title {
        font-weight: 700; font-size: 1.35rem; letter-spacing: -0.02em;
        background: linear-gradient(135deg, #e8c547 0%, var(--tb-gold-mid) 45%, var(--tb-gold-dark) 100%);
        -webkit-background-clip: text; background-clip: text; color: transparent; -webkit-text-fill-color: transparent;
    }
    @supports not (background-clip: text) {
        .tb-page-title { color: var(--tb-gold-dark); -webkit-text-fill-color: var(--tb-gold-dark); }
    }
    .tb-toolbar .form-control.tb-date-range { max-width: 260px; border: 1px solid rgba(201, 162, 39, 0.45); border-radius: 8px; font-size: 13px; }
    .tb-toolbar .input-group-text { border-color: rgba(201, 162, 39, 0.45) !important; }
    .btn-tb-outline {
        border: 1px solid var(--tb-gold-mid) !important; color: var(--tb-gold-dark) !important; background: #fff !important;
        border-radius: 8px; font-weight: 600; font-size: 13px; padding: 0.4rem 1rem;
    }
    .btn-tb-outline:hover { background: #fffbf0 !important; border-color: var(--tb-gold) !important; }
    .btn-tb-primary {
        background: linear-gradient(180deg, #d4af37 0%, var(--tb-gold-mid) 55%, var(--tb-gold-dark) 100%) !important;
        border: 1px solid var(--tb-gold-dark) !important; color: #fff !important; border-radius: 8px;
        font-weight: 600; font-size: 13px; padding: 0.4rem 1rem; text-shadow: 0 1px 0 rgba(0,0,0,.12);
    }
    .btn-tb-primary:hover { filter: brightness(1.05); color: #fff !important; }
    .tr-tabs { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 16px; border-bottom: 2px solid #e2e8f0; padding-bottom: 0; }
    .tr-tabs a {
        display: inline-block; padding: 10px 16px; color: #64748b; text-decoration: none; font-weight: 600; font-size: 13px;
        border-bottom: 3px solid transparent; margin-bottom: -2px;
    }
    .tr-tabs a:hover { color: var(--tb-navy); background: #f8fafc; border-radius: 8px 8px 0 0; }
    .tr-tabs a.active { color: var(--tb-navy); border-bottom-color: #7c3aed; background: #faf5ff; border-radius: 8px 8px 0 0; }
    .bs-panel {
        background: #fff; border-radius: 12px; border: 1px solid rgba(201, 162, 39, 0.25);
        overflow: hidden; box-shadow: 0 4px 18px rgba(17, 41, 75, 0.08);
    }
    .bs-panel .table { margin-bottom: 0; font-size: 14px; }
    .bs-panel thead th {
        background: linear-gradient(180deg, var(--tb-navy) 0%, var(--tb-navy-deep) 100%);
        font-weight: 700; color: #ffffff !important;
        border-color: rgba(255,255,255,.12);
        border-bottom: 2px solid var(--tb-gold-dark) !important;
        padding: 12px 14px;
    }
    .bs-panel tbody td { padding: 10px 14px; border-color: #eef0f3; color: #1e293b; vertical-align: middle; }
    .bs-panel tbody tr:nth-child(odd) td { background: #fff; }
    .bs-panel tbody tr:nth-child(even) td { background: #f8fafc; }
    .bs-panel tbody tr.tr-subhead td { font-weight: 700; background: #eef2ff !important; color: var(--tb-navy); }
    .bs-panel tbody tr.tr-total td { font-weight: 700; background: #fdf2f7 !important; border-top: 2px solid var(--tb-navy) !important; }
    .bs-panel tbody tr.tr-section td { font-weight: 650; background: #f1f5f9 !important; font-size: 13px; text-transform: uppercase; letter-spacing: .03em; color: #475569; }
    .bs-num { text-align: right; font-variant-numeric: tabular-nums; }
    .tr-scroll { max-height: min(65vh, 560px); overflow: auto; }
    .tr-ledger-table thead th.th-sort { white-space: nowrap; }
    .tr-ledger-table thead th.th-taxable .tr-gear { opacity: 0.6; margin-left: 4px; font-size: 13px; vertical-align: middle; }
    .tr-voucher-link { color: #2563eb; font-weight: 600; text-decoration: none; }
    .tr-voucher-link:hover { text-decoration: underline; color: #1d4ed8; }
    .tr-io-toolbar { display: flex; flex-wrap: wrap; justify-content: flex-end; align-items: center; gap: 8px; padding: 10px 14px; border-bottom: 1px solid #eef0f3; background: #fafafa; }
    .tb-export-dd { position: relative; display: inline-block; }
    .tb-export-dd > summary { list-style: none; cursor: pointer; user-select: none; }
    .tb-export-dd > summary::-webkit-details-marker { display: none; }
    .tb-export-menu {
        position: absolute; right: 0; top: 100%; margin-top: 4px; min-width: 140px; padding: 6px 0;
        background: #fff; border: 1px solid rgba(201, 162, 39, 0.35); border-radius: 8px;
        box-shadow: 0 8px 20px rgba(0,0,0,.1); z-index: 20;
    }
    .tb-export-menu a { display: block; padding: 8px 14px; color: #374151; text-decoration: none; font-size: 13px; }
    .tb-export-menu a:hover { background: #fffbf0; color: var(--tb-gold-dark); }
</style>
HTML;

$default_range = date('d-m-Y', strtotime($start_date)) . ' - ' . date('d-m-Y', strtotime($end_date));
$tab_q = 'start_date=' . urlencode($start_date) . '&end_date=' . urlencode($end_date);

$DASHBOARD_FS_PAGE = true;
require __DIR__ . '/includes/dashboard_shell_top.php';
?>
<div class="tb-wrap">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-2">
        <h1 class="tb-page-title mb-0">Tax Return</h1>
        <div class="tb-toolbar d-flex flex-wrap align-items-center gap-2">
            <span class="text-muted small d-none d-md-inline">Return period</span>
            <div class="input-group input-group-sm" style="width: auto;">
                <span class="input-group-text bg-white border-end-0"><i class="feather icon-calendar" style="color:#a67c1a;"></i></span>
                <input type="text" class="form-control tb-date-range border-start-0" id="trDateRange" value="<?php echo htmlspecialchars($default_range); ?>" readonly aria-label="Return period">
            </div>
            <button type="button" class="btn btn-tb-outline" id="trClear">Clear</button>
            <details class="tb-export-dd" data-fs-dynamic="tax-return" data-fs-file="tax-return" data-fs-title="Tax Return">
                <summary class="btn btn-tb-primary">Export <i class="feather icon-chevron-down" style="font-size:14px;vertical-align:middle;"></i></summary>
                <div class="tb-export-menu">
                    <a href="#" class="fs-export-xls">Excel</a>
                    <a href="#" class="fs-export-pdf">PDF</a>
                </div>
            </details>
        </div>
    </div>

    <nav class="tr-tabs" aria-label="Tax return sections">
        <a class="<?php echo $tab === 'tax-return' ? 'active' : ''; ?>" href="tax-return.php?tab=tax-return&amp;<?php echo htmlspecialchars($tab_q); ?>">Tax Return</a>
        <a class="<?php echo $tab === 'input' ? 'active' : ''; ?>" href="tax-return.php?tab=input&<?php echo htmlspecialchars($tab_q); ?>">Input</a>
        <a class="<?php echo $tab === 'output' ? 'active' : ''; ?>" href="tax-return.php?tab=output&<?php echo htmlspecialchars($tab_q); ?>">Output</a>
        <a class="<?php echo $tab === 'planet-reconciliation' ? 'active' : ''; ?>" href="tax-return.php?tab=planet-reconciliation&<?php echo htmlspecialchars($tab_q); ?>">Planet Reconciliation</a>
    </nav>

<?php if ($tab === 'tax-return'): ?>
    <?php $e = $trv['em']; $tr_nf = static function ($v) { return number_format((float) $v, 2); }; ?>
    <form method="get" class="row g-2 align-items-end mb-3">
        <input type="hidden" name="tab" value="tax-return">
        <div class="col-auto">
            <label class="form-label small text-muted mb-0">From</label>
            <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($start_date); ?>">
        </div>
        <div class="col-auto">
            <label class="form-label small text-muted mb-0">To</label>
            <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($end_date); ?>">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-tb-primary btn-sm">Apply</button>
        </div>
    </form>
    <div class="bs-panel mb-3">
        <div class="table-responsive tr-scroll">
            <table id="trTaxTable1" class="table mb-0 acr-col-table">
                <thead>
                    <tr>
                        <th style="width:56px;">#</th>
                        <th>Description</th>
                        <th class="bs-num" style="width:22%;">Amt</th>
                        <th class="bs-num" style="width:22%;">Tax Amt.</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="tr-subhead"><td>1</td><td colspan="3">Standard rated supplies</td></tr>
                    <tr><td></td><td>Standard Rated Supplies in Abu Dhabi</td><td class="bs-num"><?php echo $tr_nf($e['AD']['amt']); ?></td><td class="bs-num"><?php echo $tr_nf($e['AD']['tax']); ?></td></tr>
                    <tr><td></td><td>Standard Rated Supplies in Dubai</td><td class="bs-num"><?php echo $tr_nf($e['DU']['amt']); ?></td><td class="bs-num"><?php echo $tr_nf($e['DU']['tax']); ?></td></tr>
                    <tr><td></td><td>Standard Rated Supplies in Sharjah</td><td class="bs-num"><?php echo $tr_nf($e['SH']['amt']); ?></td><td class="bs-num"><?php echo $tr_nf($e['SH']['tax']); ?></td></tr>
                    <tr><td></td><td>Standard Rated Supplies in Ajman</td><td class="bs-num"><?php echo $tr_nf($e['AJ']['amt']); ?></td><td class="bs-num"><?php echo $tr_nf($e['AJ']['tax']); ?></td></tr>
                    <tr><td></td><td>Standard Rated Supplies in Umm Al Quwain</td><td class="bs-num"><?php echo $tr_nf($e['UAQ']['amt']); ?></td><td class="bs-num"><?php echo $tr_nf($e['UAQ']['tax']); ?></td></tr>
                    <tr><td></td><td>Standard Rated Supplies in Ras Al Khaimah</td><td class="bs-num"><?php echo $tr_nf($e['RAK']['amt']); ?></td><td class="bs-num"><?php echo $tr_nf($e['RAK']['tax']); ?></td></tr>
                    <tr><td></td><td>Standard Rated Supplies in Fujairah</td><td class="bs-num"><?php echo $tr_nf($e['FU']['amt']); ?></td><td class="bs-num"><?php echo $tr_nf($e['FU']['tax']); ?></td></tr>
                    <tr><td></td><td>Standard Rated Supplies NO STATE</td><td class="bs-num"><?php echo $tr_nf($e['NONE']['amt']); ?></td><td class="bs-num"><?php echo $tr_nf($e['NONE']['tax']); ?></td></tr>
                    <tr><td>2</td><td>Tax Refunds for Tourists Scheme</td><td class="bs-num"><?php echo $tr_nf($trv['tourist_amt']); ?></td><td class="bs-num"><?php echo $tr_nf($trv['tourist_tax']); ?></td></tr>
                    <tr><td>3</td><td>Supplies subject to Reverse Charge Provisions</td><td class="bs-num"><?php echo $tr_nf($trv['sales_reverse_amt']); ?></td><td class="bs-num"><?php echo $tr_nf($trv['sales_reverse_tax']); ?></td></tr>
                    <tr><td>4</td><td>Zero Rated Supplies</td><td class="bs-num"><?php echo $tr_nf($trv['zero_amt']); ?></td><td class="bs-num"><?php echo $tr_nf($trv['zero_tax']); ?></td></tr>
                    <tr><td>5</td><td>Exempt Supplies</td><td class="bs-num"><?php echo $tr_nf($trv['exempt_amt']); ?></td><td class="bs-num"><?php echo $tr_nf($trv['exempt_tax']); ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bs-panel mb-3">
        <div class="table-responsive tr-scroll">
            <table id="trTaxTable2" class="table mb-0 acr-col-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Description</th>
                        <th class="bs-num">Amt</th>
                        <th class="bs-num">Tax Amt.</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="tr-section"><td colspan="4">Supplies / imports</td></tr>
                    <tr><td>4</td><td>Zero Rated Supplies</td><td class="bs-num"><?php echo $tr_nf($trv['zero_amt']); ?></td><td class="bs-num"><?php echo $tr_nf($trv['zero_tax']); ?></td></tr>
                    <tr><td>5</td><td>Exempt Supplies</td><td class="bs-num"><?php echo $tr_nf($trv['exempt_amt']); ?></td><td class="bs-num"><?php echo $tr_nf($trv['exempt_tax']); ?></td></tr>
                    <tr><td>6</td><td>Goods Imported into UAE</td><td class="bs-num"><?php echo $tr_nf($trv['import_amt']); ?></td><td class="bs-num"><?php echo $tr_nf($trv['import_tax']); ?></td></tr>
                    <tr><td>7</td><td>Adjustments to goods Imported into the UAE</td><td class="bs-num"><?php echo $tr_nf($trv['import_adj_amt']); ?></td><td class="bs-num"><?php echo $tr_nf($trv['import_adj_tax']); ?></td></tr>
                    <tr class="tr-total"><td></td><td>Total</td><td class="bs-num"><?php echo $tr_nf($tr_supplies_total_amt); ?></td><td class="bs-num"><?php echo $tr_nf($tr_supplies_total_tax); ?></td></tr>
                    <tr class="tr-section"><td colspan="4">VAT on expenses</td></tr>
                    <tr><td>6</td><td>Standard Rated Expenses</td><td class="bs-num"><?php echo $tr_nf($trv['exp_std_amt']); ?></td><td class="bs-num"><?php echo $tr_nf($trv['exp_std_tax']); ?></td></tr>
                    <tr><td>7</td><td>Supplies subject to reverse charge provisions</td><td class="bs-num"><?php echo $tr_nf($trv['exp_rev_amt']); ?></td><td class="bs-num"><?php echo $tr_nf($trv['exp_rev_tax']); ?></td></tr>
                    <tr class="tr-total"><td></td><td>Total</td><td class="bs-num"><?php echo $tr_nf($tr_exp_total_amt); ?></td><td class="bs-num"><?php echo $tr_nf($tr_exp_total_tax); ?></td></tr>
                    <tr class="tr-section"><td colspan="4">Net VAT due</td></tr>
                    <tr><td>9</td><td>Total Value of Tax for the period</td><td class="bs-num"><?php echo $tr_nf($trv['total_output_vat']); ?></td><td class="bs-num">—</td></tr>
                    <tr><td>10</td><td>Total Value of recoverable Tax for the period</td><td class="bs-num"><?php echo $tr_nf($trv['total_input_vat']); ?></td><td class="bs-num">—</td></tr>
                    <tr class="tr-total"><td></td><td>Payable Tax for the period</td><td class="bs-num"><?php echo $tr_nf($trv['payable_vat']); ?></td><td class="bs-num">—</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <p class="text-muted small mb-0">Standard-rated amounts use invoice line <strong>net amount</strong> (taxable) and <strong>tax amount</strong> where VAT applies; emirates follow customer billing/shipping state. Lines with no VAT on file (zero-rated vs exempt) are grouped under Exempt until tagged in master data. Tourist refunds, imports, and sales reverse-charge lines stay at 0 unless linked modules populate them.</p>

<?php elseif ($tab === 'planet-reconciliation'): ?>
    <?php
    $planet_count = is_array($planet_rows) ? count($planet_rows) : 0;
    ?>
    <form method="get" class="row g-2 align-items-end mb-3">
        <input type="hidden" name="tab" value="planet-reconciliation">
        <div class="col-auto">
            <label class="form-label small text-muted mb-0">From</label>
            <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($start_date); ?>">
        </div>
        <div class="col-auto">
            <label class="form-label small text-muted mb-0">To</label>
            <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($end_date); ?>">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-tb-primary btn-sm">Apply</button>
        </div>
    </form>
    <div class="bs-panel">
        <div class="tr-io-toolbar">
            <button type="button" class="btn btn-tb-outline btn-sm position-relative" style="padding:0.35rem 0.55rem;" title="Filter" disabled>
                <i class="feather icon-filter"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:9px;padding:2px 4px;">1</span>
            </button>
            <button type="button" class="btn btn-tb-outline btn-sm" style="padding:0.35rem 0.55rem;" title="Refresh" onclick="window.location.reload();"><i class="feather icon-refresh-cw"></i></button>
            <details class="tb-export-dd" data-fs-root="#trPlanetLedger" data-fs-file="tax-return-planet-reconciliation" data-fs-title="Tax Return — Planet Reconciliation">
                <summary class="btn btn-tb-primary btn-sm">Export <i class="feather icon-chevron-down" style="font-size:12px;vertical-align:middle;"></i></summary>
                <div class="tb-export-menu">
                    <a href="#" class="fs-export-xls">Excel</a>
                    <a href="#" class="fs-export-pdf">PDF</a>
                </div>
            </details>
        </div>
        <div class="table-responsive tr-scroll">
            <table id="trPlanetLedger" class="table mb-0 tr-ledger-table table-striped acr-col-table">
                <thead>
                    <tr>
                        <th class="th-sort" style="width:52px;">Sr No.</th>
                        <th class="th-sort">Date</th>
                        <th class="th-sort">Voucher Type</th>
                        <th class="th-sort">Invoice No.</th>
                        <th class="th-sort">Ledger</th>
                        <th class="th-sort">State</th>
                        <th class="th-sort">TRN</th>
                        <th class="bs-num th-sort">Bill Amt</th>
                        <th class="bs-num th-sort">Tax Amt</th>
                        <th class="bs-num th-sort th-taxable">Taxable Amt<i class="feather icon-settings tr-gear" title="Column options"></i></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (!empty($planet_rows)) {
                        $pi = 1;
                        foreach ($planet_rows as $row) {
                            $pd = !empty($row['date']) ? date('d-m-Y', strtotime($row['date'])) : '';
                            echo '<tr>';
                            echo '<td>' . $pi++ . '</td>';
                            echo '<td>' . htmlspecialchars($pd) . '</td>';
                            echo '<td>' . htmlspecialchars((string) ($row['voucher_type'] ?? '')) . '</td>';
                            echo '<td>' . htmlspecialchars((string) ($row['invoice_no'] ?? '')) . '</td>';
                            echo '<td>' . htmlspecialchars((string) ($row['ledger'] ?? '')) . '</td>';
                            echo '<td>' . htmlspecialchars((string) ($row['state'] ?? '')) . '</td>';
                            echo '<td>' . htmlspecialchars((string) ($row['trn'] ?? '')) . '</td>';
                            echo '<td class="bs-num">' . number_format((float) ($row['bill_amt'] ?? 0), 2) . '</td>';
                            echo '<td class="bs-num">' . number_format((float) ($row['tax_amt'] ?? 0), 2) . '</td>';
                            echo '<td class="bs-num">' . number_format((float) ($row['taxable_amt'] ?? 0), 2) . '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="10" class="text-center text-muted py-4">No Rows To Show</td></tr>';
                    }
                    ?>
                </tbody>
                <tfoot>
                    <tr class="tr-total">
                        <td><?php echo $planet_count > 0 ? 'Totals' : ''; ?></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td class="bs-num"><?php echo $planet_count > 0 ? '—' : '0'; ?></td>
                        <td class="bs-num"><?php echo number_format($planet_totals['bill'], 2); ?></td>
                        <td class="bs-num"><?php echo number_format($planet_totals['tax'], 2); ?></td>
                        <td class="bs-num"><?php echo number_format($planet_totals['taxable'], 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 py-2 border-top" style="border-color:#eef0f3!important;background:#fafafa;">
            <span class="text-muted small">Showing <?php echo $planet_count > 0 ? '1' : '0'; ?> to <?php echo (string) $planet_count; ?> of <?php echo (string) $planet_count; ?> entries</span>
            <div class="d-flex align-items-center gap-2">
                <select class="form-control form-control-sm" style="width:auto;max-width:160px;font-size:12px;" disabled title="Placeholder">
                    <option>Show all items</option>
                </select>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-secondary" disabled title="Previous">&laquo;</button>
                    <button type="button" class="btn btn-outline-secondary" disabled title="Next">&raquo;</button>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'input' || $tab === 'output'): ?>
    <form method="get" class="row g-2 align-items-end mb-3">
        <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
        <div class="col-auto">
            <label class="form-label small text-muted mb-0">From</label>
            <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($start_date); ?>">
        </div>
        <div class="col-auto">
            <label class="form-label small text-muted mb-0">To</label>
            <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($end_date); ?>">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-tb-primary btn-sm">Apply</button>
        </div>
    </form>
    <div class="bs-panel">
        <div class="tr-io-toolbar">
            <button type="button" class="btn btn-tb-outline btn-sm position-relative" style="padding:0.35rem 0.55rem;" title="Filter" disabled>
                <i class="feather icon-filter"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:9px;padding:2px 4px;">1</span>
            </button>
            <button type="button" class="btn btn-tb-outline btn-sm" style="padding:0.35rem 0.55rem;" title="Refresh" onclick="window.location.reload();"><i class="feather icon-refresh-cw"></i></button>
            <details class="tb-export-dd" data-fs-root="#trIoLedger" data-fs-file="tax-return-ledger" data-fs-title="Tax Return — Input / Output">
                <summary class="btn btn-tb-primary btn-sm">Export <i class="feather icon-chevron-down" style="font-size:12px;vertical-align:middle;"></i></summary>
                <div class="tb-export-menu">
                    <a href="#" class="fs-export-xls">Excel</a>
                    <a href="#" class="fs-export-pdf">PDF</a>
                </div>
            </details>
        </div>
        <div class="table-responsive tr-scroll">
            <table id="trIoLedger" class="table mb-0 tr-ledger-table table-striped acr-col-table">
                <thead>
                    <tr>
                        <th class="th-sort" style="width:52px;">Sr. No.</th>
                        <th class="th-sort">Date</th>
                        <th class="th-sort">Voucher Type</th>
                        <th class="th-sort">Voucher No</th>
                        <th class="th-sort">Ledger</th>
                        <th class="th-sort">State</th>
                        <th class="th-sort">TRN</th>
                        <th class="bs-num th-sort">Bill Amount</th>
                        <th class="bs-num th-sort">Tax Amount</th>
                        <th class="bs-num th-sort th-taxable">Taxable Amount<i class="feather icon-settings tr-gear" title="Column options"></i></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $ledger_count = is_array($ledger_rows) ? count($ledger_rows) : 0;
                    if (!empty($ledger_rows)) {
                        $i = 1;
                        foreach ($ledger_rows as $row) {
                            $d = !empty($row['date']) ? date('d-m-Y', strtotime($row['date'])) : '';
                            $invId = (int) ($row['invoice_id'] ?? 0);
                            $vno = (string) ($row['voucher_no'] ?? '');
                            if ($tab === 'output' && $invId > 0) {
                                $voucherCell = '<a class="tr-voucher-link" href="sale-invoice.php?id=' . $invId . '">' . htmlspecialchars($vno) . '</a>';
                            } elseif ($tab === 'input' && $invId > 0) {
                                $voucherCell = '<a class="tr-voucher-link" href="purchase-invoice.php?id=' . $invId . '">' . htmlspecialchars($vno) . '</a>';
                            } else {
                                $voucherCell = htmlspecialchars($vno);
                            }
                            echo '<tr>';
                            echo '<td>' . $i++ . '</td>';
                            echo '<td>' . htmlspecialchars($d) . '</td>';
                            echo '<td>' . htmlspecialchars((string) ($row['voucher_type'] ?? '')) . '</td>';
                            echo '<td>' . $voucherCell . '</td>';
                            echo '<td>' . htmlspecialchars((string) ($row['ledger'] ?? '')) . '</td>';
                            echo '<td>' . htmlspecialchars((string) ($row['state'] ?? '')) . '</td>';
                            echo '<td>' . htmlspecialchars((string) ($row['trn'] ?? '')) . '</td>';
                            echo '<td class="bs-num">' . number_format((float) ($row['bill_amt'] ?? 0), 2) . '</td>';
                            echo '<td class="bs-num">' . number_format((float) ($row['tax_amt'] ?? 0), 2) . '</td>';
                            echo '<td class="bs-num">' . number_format((float) ($row['taxable_amt'] ?? 0), 2) . '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="10" class="text-center text-muted py-4">No Rows To Show</td></tr>';
                    }
                    ?>
                </tbody>
                <tfoot>
                    <tr class="tr-total">
                        <td><?php echo $ledger_count > 0 ? 'Totals' : ''; ?></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td class="bs-num"><?php echo number_format($ledger_totals['bill'], 2); ?></td>
                        <td class="bs-num"><?php echo number_format($ledger_totals['tax'], 2); ?></td>
                        <td class="bs-num"><?php echo number_format($ledger_totals['taxable'], 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 py-2 border-top" style="border-color:#eef0f3!important;background:#fafafa;">
            <span class="text-muted small">Showing <?php echo $ledger_count > 0 ? '1' : '0'; ?> to <?php echo (string) $ledger_count; ?> of <?php echo (string) $ledger_count; ?> entries</span>
            <div class="d-flex align-items-center gap-2">
                <select class="form-control form-control-sm" style="width:auto;max-width:160px;font-size:12px;" disabled title="Placeholder">
                    <option>Show all items</option>
                </select>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-secondary" disabled title="Previous">&laquo;</button>
                    <button type="button" class="btn btn-outline-secondary" disabled title="Next">&raquo;</button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
</div>
<script>
(function () {
    var inp = document.getElementById('trDateRange');
    var def = <?php echo json_encode($default_range); ?>;
    var clr = document.getElementById('trClear');
    if (clr && inp) clr.addEventListener('click', function () { inp.value = def; });
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script src="assets/js/auragold-col-reorder.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.AuragoldColReorder) return;
    AuragoldColReorder.init('#trTaxTable1', { storageKey: 'auragold_colorder_tax_return_supply', fixedFirst: true });
    AuragoldColReorder.init('#trTaxTable2', { storageKey: 'auragold_colorder_tax_return_summary', fixedFirst: true });
    AuragoldColReorder.init('#trPlanetLedger', { storageKey: 'auragold_colorder_tax_planet_ledger', fixedFirst: true });
    AuragoldColReorder.init('#trIoLedger', { storageKey: 'auragold_colorder_tax_io_ledger', fixedFirst: true });
});
</script>
<?php
require __DIR__ . '/includes/dashboard_shell_bottom.php';
