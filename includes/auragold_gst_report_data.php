<?php
/**
 * GST report data fetchers for GoldMatrix.
 */
require_once __DIR__ . '/auragold_gst_reports_catalog.php';
if (!function_exists('auragold_tbl_has_column')) {
    require_once __DIR__ . '/auragold_branch_data_scope.php';
}

/** B2C large invoice threshold (INR) — GSTR-1 Table 5. */
if (!defined('AURAGOLD_GST_B2C_LARGE_THRESHOLD')) {
    define('AURAGOLD_GST_B2C_LARGE_THRESHOLD', 250000.0);
}

/**
 * @return array{columns: list<array{key:string,label:string,align?:string}>, rows: list<array<string,mixed>>, totals: array<string,float|int|string>, note?: string}
 */
function auragold_gst_report_fetch(mysqli $conn, string $type, string $fromDate, string $toDate, string $section = ''): array
{
    $empty = ['columns' => [], 'rows' => [], 'totals' => [], 'note' => '', 'sections' => []];
    if (!auragold_gst_report_is_valid($type)) {
        return $empty;
    }

    switch ($type) {
        case 'gstr1':
            return auragold_gst_report_gstr1($conn, $fromDate, $toDate, $section);
        case 'gstr3b':
            return auragold_gst_report_gstr3b_summary($conn, $fromDate, $toDate);
        case 'gstr2b':
            return auragold_gst_report_gstr2b($conn, $fromDate, $toDate);
        case 'gstr9':
            return auragold_gst_report_gstr9($conn, $fromDate, $toDate);
        default:
            return $empty;
    }
}

/**
 * GSTR-1 — section tabs for outward supplies.
 */
function auragold_gst_report_gstr1(mysqli $conn, string $from, string $to, string $section = ''): array
{
    $sections = auragold_gst_gstr1_sections();
    if ($section === '' || !isset($sections[$section])) {
        $section = 'b2b';
    }

    switch ($section) {
        case 'b2c_large':
            $data = auragold_gst_report_sales_register($conn, $from, $to, 'b2c_large');
            break;
        case 'b2c_small':
            $data = auragold_gst_report_sales_register($conn, $from, $to, 'b2c_small');
            break;
        case 'credit_note':
            $data = auragold_gst_report_note_register($conn, $from, $to, 'credit');
            break;
        case 'debit_note':
            $data = auragold_gst_report_note_register($conn, $from, $to, 'debit');
            break;
        case 'hsn':
            $data = auragold_gst_report_hsn_summary($conn, $from, $to);
            break;
        case 'b2b':
        default:
            $data = auragold_gst_report_sales_register($conn, $from, $to, 'b2b');
            break;
    }

    $data['section'] = $section;
    $data['sections'] = array_values($sections);
    $data['note'] = ($data['note'] ?? '') !== ''
        ? ('GSTR-1 — ' . ($sections[$section]['label'] ?? $section) . '. ' . $data['note'])
        : ('GSTR-1 — ' . ($sections[$section]['label'] ?? $section));

    return $data;
}

/**
 * GSTR-2B recon — purchase books side (portal 2B import later).
 */
function auragold_gst_report_gstr2b(mysqli $conn, string $from, string $to): array
{
    $data = auragold_gst_report_purchase_register($conn, $from, $to, 'itc_register');
    $data['note'] = 'Books purchase register for GSTR-2B reconciliation. Match these rows with GSTR-2B from the GST portal (portal import coming soon).';

    return $data;
}

/**
 * GSTR-9 annual-style summary from sales + purchases in the selected period.
 */
function auragold_gst_report_gstr9(mysqli $conn, string $from, string $to): array
{
    $out = auragold_gst_report_sales_register($conn, $from, $to, 'liability');
    $b2b = auragold_gst_report_sales_register($conn, $from, $to, 'b2b');
    $b2cL = auragold_gst_report_sales_register($conn, $from, $to, 'b2c_large');
    $b2cS = auragold_gst_report_sales_register($conn, $from, $to, 'b2c_small');
    $purchase = auragold_gst_report_purchase_register($conn, $from, $to, 'itc_register');

    $mk = static function (string $label, array $src, string $taxableKey = 'taxable_value', string $taxKey = 'tax'): array {
        return [
            'particulars' => $label,
            'docs' => (int) ($src['totals']['count'] ?? 0),
            'taxable' => (float) ($src['totals'][$taxableKey] ?? 0),
            'cgst' => (float) ($src['totals']['cgst'] ?? 0),
            'sgst' => (float) ($src['totals']['sgst'] ?? 0),
            'igst' => (float) ($src['totals']['igst'] ?? 0),
            'tax' => (float) ($src['totals'][$taxKey] ?? 0),
        ];
    };

    $rows = [
        $mk('Outward supplies — B2B', $b2b),
        $mk('Outward supplies — B2C Large', $b2cL),
        $mk('Outward supplies — B2C Small', $b2cS),
        $mk('Total outward (taxed sales)', $out),
        $mk('Inward supplies (purchases / ITC)', $purchase, 'taxable', 'tax_amount'),
    ];

    $totTaxable = 0.0;
    $totTax = 0.0;
    $totCgst = 0.0;
    $totSgst = 0.0;
    $totIgst = 0.0;
    foreach (array_slice($rows, 0, 3) as $r) {
        $totTaxable += $r['taxable'];
        $totTax += $r['tax'];
        $totCgst += $r['cgst'];
        $totSgst += $r['sgst'];
        $totIgst += $r['igst'];
    }

    return [
        'columns' => [
            ['key' => 'particulars', 'label' => 'Particulars'],
            ['key' => 'docs', 'label' => 'Documents', 'align' => 'right'],
            ['key' => 'taxable', 'label' => 'Taxable Value', 'align' => 'right'],
            ['key' => 'cgst', 'label' => 'CGST', 'align' => 'right'],
            ['key' => 'sgst', 'label' => 'SGST', 'align' => 'right'],
            ['key' => 'igst', 'label' => 'IGST', 'align' => 'right'],
            ['key' => 'tax', 'label' => 'Total Tax', 'align' => 'right'],
        ],
        'rows' => $rows,
        'totals' => [
            'taxable' => round($totTaxable, 2),
            'cgst' => round($totCgst, 2),
            'sgst' => round($totSgst, 2),
            'igst' => round($totIgst, 2),
            'tax' => round($totTax, 2),
            'count' => count($rows),
        ],
        'note' => 'GSTR-9 style annual summary from books for the selected period. Use the financial year date range when filing.',
    ];
}

function auragold_gst_report_si_branch_sql(mysqli $conn, string $alias = 'si'): string
{
    if (function_exists('auragold_sale_invoices_branch_where_sql')) {
        return auragold_sale_invoices_branch_where_sql($conn, $alias);
    }

    return '';
}

function auragold_gst_report_pi_branch_sql(mysqli $conn, string $alias = 'pi'): string
{
    if (function_exists('auragold_purchase_invoices_branch_where_sql')) {
        return auragold_purchase_invoices_branch_where_sql($conn, $alias);
    }

    return '';
}

/**
 * Active document filter for varchar statuses (draft/final/…) and legacy numeric 0/1.
 * Excludes cancelled / void / deleted only — same idea as sale/purchase lists.
 */
function auragold_gst_report_active_doc_sql(string $alias, string $col = 'status'): string
{
    return " AND LOWER(TRIM(CAST(IFNULL({$alias}.{$col}, '') AS CHAR))) NOT IN ('0', 'cancelled', 'canceled', 'void', 'deleted')";
}

/** Line-item tax total for a sale invoice. */
function auragold_gst_report_sale_line_tax_sql(string $siAlias = 'si'): string
{
    return "(SELECT IFNULL(SUM(IFNULL(sii.tax_amount, 0)), 0) FROM tbl_sale_invoice_items sii WHERE sii.invoice_id = {$siAlias}.id)";
}

/** Line-item taxable (pre-tax amount) for a sale invoice. */
function auragold_gst_report_sale_line_taxable_sql(string $siAlias = 'si'): string
{
    return "(SELECT IFNULL(SUM(IFNULL(sii.amount, IFNULL(sii.net_amount, 0))), 0) FROM tbl_sale_invoice_items sii WHERE sii.invoice_id = {$siAlias}.id)";
}

/**
 * Effective CGST/SGST/IGST SQL — prefer header breakdown; else split line tax by supply mode.
 *
 * @return array{cgst:string,sgst:string,igst:string,tax:string,taxable:string,hdr_tax:string,line_tax:string}
 */
function auragold_gst_report_sale_gst_exprs(mysqli $conn, string $siAlias = 'si'): array
{
    $lineTax = auragold_gst_report_sale_line_tax_sql($siAlias);
    $lineTaxable = auragold_gst_report_sale_line_taxable_sql($siAlias);
    $hasCgst = auragold_tbl_has_column($conn, 'tbl_sale_invoices', 'gst_cgst_amount');
    $hasMode = auragold_tbl_has_column($conn, 'tbl_sale_invoices', 'gst_supply_mode');

    if ($hasCgst) {
        $hdrTax = "(IFNULL({$siAlias}.gst_cgst_amount,0) + IFNULL({$siAlias}.gst_sgst_amount,0) + IFNULL({$siAlias}.gst_igst_amount,0))";
        $modeExpr = $hasMode
            ? "LOWER(TRIM(IFNULL({$siAlias}.gst_supply_mode, '')))"
            : "''";
        $isInter = "({$modeExpr} IN ('interstate', 'export', 'zero_rated') OR ({$hdrTax} = 0 AND IFNULL({$siAlias}.gst_igst_amount,0) > 0))";

        $cgst = "CASE
            WHEN {$hdrTax} > 0 THEN IFNULL({$siAlias}.gst_cgst_amount, 0)
            WHEN {$isInter} THEN 0
            ELSE ROUND({$lineTax} / 2, 2)
        END";
        $sgst = "CASE
            WHEN {$hdrTax} > 0 THEN IFNULL({$siAlias}.gst_sgst_amount, 0)
            WHEN {$isInter} THEN 0
            ELSE ROUND({$lineTax} - ROUND({$lineTax} / 2, 2), 2)
        END";
        $igst = "CASE
            WHEN {$hdrTax} > 0 THEN IFNULL({$siAlias}.gst_igst_amount, 0)
            WHEN {$isInter} THEN {$lineTax}
            ELSE 0
        END";
        $modeSel = $hasMode ? "IFNULL({$siAlias}.gst_supply_mode, '')" : "''";
    } else {
        $hdrTax = '0';
        $cgst = "ROUND({$lineTax} / 2, 2)";
        $sgst = "ROUND({$lineTax} - ROUND({$lineTax} / 2, 2), 2)";
        $igst = '0';
        $modeSel = "''";
    }

    $tax = "({$cgst} + {$sgst} + {$igst})";
    $taxable = "CASE
        WHEN {$lineTaxable} > 0 THEN {$lineTaxable}
        WHEN {$tax} > 0 THEN GREATEST(IFNULL({$siAlias}.grand_total, 0) - {$tax}, 0)
        ELSE IFNULL({$siAlias}.net_total, IFNULL({$siAlias}.subtotal, IFNULL({$siAlias}.grand_total, 0)))
    END";

    return [
        'cgst' => $cgst,
        'sgst' => $sgst,
        'igst' => $igst,
        'tax' => $tax,
        'taxable' => $taxable,
        'hdr_tax' => $hdrTax,
        'line_tax' => $lineTax,
        'mode' => $modeSel,
    ];
}

/**
 * Effective GSTIN on a sale invoice (invoice field, else customer master).
 * Wrapped in CONVERT to avoid collation mix between branch/customer tables.
 */
function auragold_gst_report_gstin_expr(string $siAlias = 'si', string $cAlias = 'c'): string
{
    return "CONVERT(UPPER(TRIM(COALESCE(NULLIF(TRIM({$siAlias}.customer_gstin), ''), NULLIF(TRIM(IFNULL({$cAlias}.gstin, '')), ''), ''))) USING utf8mb4)";
}

/** SQL fragment: party has GSTIN (B2B). */
function auragold_gst_report_has_gstin_sql(string $siAlias = 'si', string $cAlias = 'c'): string
{
    return 'CHAR_LENGTH(' . auragold_gst_report_gstin_expr($siAlias, $cAlias) . ') > 0';
}

/** SQL fragment: party has no GSTIN (B2C). */
function auragold_gst_report_no_gstin_sql(string $siAlias = 'si', string $cAlias = 'c'): string
{
    return 'CHAR_LENGTH(' . auragold_gst_report_gstin_expr($siAlias, $cAlias) . ') = 0';
}

/**
 * @return array{columns: list, rows: list, totals: array, note?: string}
 */
function auragold_gst_report_sales_register(mysqli $conn, string $from, string $to, string $mode): array
{
    $fromEsc = mysqli_real_escape_string($conn, $from);
    $toEsc = mysqli_real_escape_string($conn, $to);
    $br = auragold_gst_report_si_branch_sql($conn, 'si');
    $gstinExpr = auragold_gst_report_gstin_expr('si', 'c');
    $threshold = (float) AURAGOLD_GST_B2C_LARGE_THRESHOLD;
    $active = auragold_gst_report_active_doc_sql('si');
    $gst = auragold_gst_report_sale_gst_exprs($conn, 'si');

    $whereExtra = '';
    $note = '';
    if ($mode === 'b2b') {
        $whereExtra = ' AND ' . auragold_gst_report_has_gstin_sql('si', 'c');
        $note = 'Invoices with party GSTIN (B2B / GSTR-1 Table 4).';
    } elseif ($mode === 'b2c_large') {
        $whereExtra = ' AND ' . auragold_gst_report_no_gstin_sql('si', 'c') . " AND IFNULL(si.grand_total,0) > {$threshold}";
        $note = 'Unregistered parties with invoice value above ₹2,50,000 (B2C Large / Table 5).';
    } elseif ($mode === 'b2c_small') {
        $whereExtra = ' AND ' . auragold_gst_report_no_gstin_sql('si', 'c') . " AND IFNULL(si.grand_total,0) <= {$threshold}";
        $note = 'Unregistered parties with invoice value up to ₹2,50,000 (B2C Small).';
    } elseif ($mode === 'nil_exempt') {
        $whereExtra = " AND ({$gst['tax']}) = 0 AND IFNULL(si.grand_total,0) > 0";
        $note = 'Sale invoices with zero CGST/SGST/IGST in the period.';
    } elseif ($mode === 'export') {
        $whereExtra = " AND IFNULL(si.gst_supply_mode,'') IN ('export','EXPORT','zero_rated','ZERO_RATED')";
        $note = 'Invoices marked with export / zero-rated supply mode (if recorded).';
    } elseif ($mode === 'liability') {
        $whereExtra = " AND ({$gst['tax']}) > 0";
        $note = 'Output GST liability from sale invoices.';
    }

    $sql = "
        SELECT
            si.id,
            si.invoice_no,
            si.invoice_date,
            IFNULL(si.customer_name, '') AS party_name,
            {$gstinExpr} AS gstin,
            IFNULL(si.grand_total, 0) AS invoice_value,
            ({$gst['taxable']}) AS taxable_value,
            ({$gst['cgst']}) AS cgst,
            ({$gst['sgst']}) AS sgst,
            ({$gst['igst']}) AS igst,
            ({$gst['mode']}) AS supply_mode
        FROM tbl_sale_invoices si
        LEFT JOIN tbl_customers c ON c.id = si.customer_id
        WHERE si.invoice_date BETWEEN '{$fromEsc}' AND '{$toEsc}'
          {$active}
          {$br}
          {$whereExtra}
        ORDER BY si.invoice_date ASC, si.id ASC
    ";

    $rows = [];
    $totals = [
        'invoice_value' => 0.0,
        'taxable_value' => 0.0,
        'cgst' => 0.0,
        'sgst' => 0.0,
        'igst' => 0.0,
        'tax' => 0.0,
        'count' => 0,
    ];
    $res = false;
    try {
        $res = mysqli_query($conn, $sql);
    } catch (Throwable $e) {
        $res = false;
    }
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $cgst = (float) ($r['cgst'] ?? 0);
            $sgst = (float) ($r['sgst'] ?? 0);
            $igst = (float) ($r['igst'] ?? 0);
            $tax = $cgst + $sgst + $igst;
            $inv = (float) ($r['invoice_value'] ?? 0);
            $taxable = (float) ($r['taxable_value'] ?? 0);
            $rows[] = [
                'invoice_no' => (string) ($r['invoice_no'] ?? ''),
                'invoice_date' => (string) ($r['invoice_date'] ?? ''),
                'party_name' => (string) ($r['party_name'] ?? ''),
                'gstin' => (string) ($r['gstin'] ?? ''),
                'supply_mode' => (string) ($r['supply_mode'] ?? ''),
                'taxable_value' => round($taxable, 2),
                'cgst' => round($cgst, 2),
                'sgst' => round($sgst, 2),
                'igst' => round($igst, 2),
                'tax' => round($tax, 2),
                'invoice_value' => round($inv, 2),
            ];
            $totals['invoice_value'] += $inv;
            $totals['taxable_value'] += $taxable;
            $totals['cgst'] += $cgst;
            $totals['sgst'] += $sgst;
            $totals['igst'] += $igst;
            $totals['tax'] += $tax;
            $totals['count']++;
        }
    }

    foreach (['invoice_value', 'taxable_value', 'cgst', 'sgst', 'igst', 'tax'] as $k) {
        $totals[$k] = round((float) $totals[$k], 2);
    }

    $columns = [
        ['key' => 'invoice_no', 'label' => 'Invoice No'],
        ['key' => 'invoice_date', 'label' => 'Date'],
        ['key' => 'party_name', 'label' => 'Party'],
        ['key' => 'gstin', 'label' => 'GSTIN'],
        ['key' => 'taxable_value', 'label' => 'Taxable', 'align' => 'right'],
        ['key' => 'cgst', 'label' => 'CGST', 'align' => 'right'],
        ['key' => 'sgst', 'label' => 'SGST', 'align' => 'right'],
        ['key' => 'igst', 'label' => 'IGST', 'align' => 'right'],
        ['key' => 'tax', 'label' => 'Total Tax', 'align' => 'right'],
        ['key' => 'invoice_value', 'label' => 'Invoice Value', 'align' => 'right'],
    ];

    return ['columns' => $columns, 'rows' => $rows, 'totals' => $totals, 'note' => $note];
}

function auragold_gst_report_note_register(mysqli $conn, string $from, string $to, string $kind): array
{
    $fromEsc = mysqli_real_escape_string($conn, $from);
    $toEsc = mysqli_real_escape_string($conn, $to);
    $isCredit = ($kind === 'credit');
    $table = $isCredit ? 'tbl_credit_notes' : 'tbl_debit_notes';
    $noCol = $isCredit ? 'credit_note_no' : 'debit_note_no';
    $dateCol = $isCredit ? 'credit_note_date' : 'debit_note_date';
    $itemsTable = $isCredit ? 'tbl_credit_note_items' : 'tbl_debit_note_items';

    $chk = @mysqli_query($conn, "SHOW TABLES LIKE '{$table}'");
    if (!$chk || mysqli_num_rows($chk) === 0) {
        return [
            'columns' => [],
            'rows' => [],
            'totals' => [],
            'note' => ucfirst($kind) . ' note table not found.',
        ];
    }

    $hasTaxOnItems = false;
    $fk = $isCredit ? 'credit_note_id' : 'debit_note_id';
    $itChk = @mysqli_query($conn, "SHOW TABLES LIKE '{$itemsTable}'");
    if ($itChk && mysqli_num_rows($itChk) > 0 && auragold_tbl_has_column($conn, $itemsTable, 'tax_amount')) {
        $hasTaxOnItems = true;
    }

    $taxSel = '0 AS tax_amount';

    // Detect item FK column
    if ($hasTaxOnItems) {
        $fkChk = @mysqli_query($conn, "SHOW COLUMNS FROM `{$itemsTable}` LIKE '{$fk}'");
        if (!$fkChk || mysqli_num_rows($fkChk) === 0) {
            $fkAlt = 'note_id';
            $fkChk2 = @mysqli_query($conn, "SHOW COLUMNS FROM `{$itemsTable}` LIKE '{$fkAlt}'");
            if ($fkChk2 && mysqli_num_rows($fkChk2) > 0) {
                $fk = $fkAlt;
            } else {
                $hasTaxOnItems = false;
            }
        }
        if ($hasTaxOnItems) {
            $taxSel = "(SELECT IFNULL(SUM(tax_amount),0) FROM {$itemsTable} it WHERE it.{$fk} = n.id) AS tax_amount";
        }
    }

    $active = auragold_gst_report_active_doc_sql('n');
    $sql = "
        SELECT
            n.{$noCol} AS doc_no,
            n.{$dateCol} AS doc_date,
            IFNULL(n.customer_name, '') AS party_name,
            IFNULL(n.grand_total, 0) AS doc_value,
            {$taxSel}
        FROM {$table} n
        WHERE n.{$dateCol} BETWEEN '{$fromEsc}' AND '{$toEsc}'
          {$active}
        ORDER BY n.{$dateCol} ASC, n.id ASC
    ";

    $rows = [];
    $totals = ['doc_value' => 0.0, 'tax_amount' => 0.0, 'count' => 0];
    $res = @mysqli_query($conn, $sql);
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $val = (float) ($r['doc_value'] ?? 0);
            $tax = (float) ($r['tax_amount'] ?? 0);
            $rows[] = [
                'doc_no' => (string) ($r['doc_no'] ?? ''),
                'doc_date' => (string) ($r['doc_date'] ?? ''),
                'party_name' => (string) ($r['party_name'] ?? ''),
                'tax_amount' => round($tax, 2),
                'doc_value' => round($val, 2),
            ];
            $totals['doc_value'] += $val;
            $totals['tax_amount'] += $tax;
            $totals['count']++;
        }
    }
    $totals['doc_value'] = round($totals['doc_value'], 2);
    $totals['tax_amount'] = round($totals['tax_amount'], 2);

    return [
        'columns' => [
            ['key' => 'doc_no', 'label' => $isCredit ? 'Credit Note No' : 'Debit Note No'],
            ['key' => 'doc_date', 'label' => 'Date'],
            ['key' => 'party_name', 'label' => 'Party'],
            ['key' => 'tax_amount', 'label' => 'Tax', 'align' => 'right'],
            ['key' => 'doc_value', 'label' => 'Value', 'align' => 'right'],
        ],
        'rows' => $rows,
        'totals' => $totals,
        'note' => 'GSTR-1 Table 9 — ' . ($isCredit ? 'credit' : 'debit') . ' notes in the selected period.',
    ];
}

function auragold_gst_report_hsn_summary(mysqli $conn, string $from, string $to): array
{
    $fromEsc = mysqli_real_escape_string($conn, $from);
    $toEsc = mysqli_real_escape_string($conn, $to);
    $br = auragold_gst_report_si_branch_sql($conn, 'si');
    $gstinExpr = auragold_gst_report_gstin_expr('si', 'c');

    $hasPcHsn = auragold_tbl_has_column($conn, 'tbl_product_characteristics', 'hsn');
    $hasMetalOnProduct = auragold_tbl_has_column($conn, 'tbl_products', 'metal_id');
    $hasMetalTable = false;
    $mtChk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_metal'");
    if ($mtChk && mysqli_num_rows($mtChk) > 0) {
        $hasMetalTable = true;
    }

    $joins = "
        FROM tbl_sale_invoice_items sii
        INNER JOIN tbl_sale_invoices si ON si.id = sii.invoice_id
        LEFT JOIN tbl_customers c ON c.id = si.customer_id
    ";
    if ($hasPcHsn) {
        $joins .= " LEFT JOIN tbl_product_characteristics pc ON pc.id = sii.product_characteristic_id ";
    }
    if ($hasMetalOnProduct && $hasMetalTable) {
        $joins .= " LEFT JOIN tbl_products p ON p.id = sii.product_id LEFT JOIN tbl_metal m ON m.id = p.metal_id ";
    }

    if ($hasPcHsn && $hasMetalOnProduct && $hasMetalTable) {
        $hsnExpr = "COALESCE(NULLIF(TRIM(pc.hsn), ''), NULLIF(TRIM(m.hsn_code), ''), '7113')";
    } elseif ($hasPcHsn) {
        $hsnExpr = "COALESCE(NULLIF(TRIM(pc.hsn), ''), '7113')";
    } elseif ($hasMetalOnProduct && $hasMetalTable) {
        $hsnExpr = "COALESCE(NULLIF(TRIM(m.hsn_code), ''), '7113')";
    } else {
        $hsnExpr = "'7113'";
    }

    $hasHdrGst = auragold_tbl_has_column($conn, 'tbl_sale_invoices', 'gst_igst_amount');
    $hasMode = auragold_tbl_has_column($conn, 'tbl_sale_invoices', 'gst_supply_mode');
    if ($hasHdrGst || $hasMode) {
        $interCondParts = [];
        if ($hasHdrGst) {
            $interCondParts[] = 'IFNULL(si.gst_igst_amount, 0) > 0';
        }
        if ($hasMode) {
            $interCondParts[] = "LOWER(TRIM(IFNULL(si.gst_supply_mode, ''))) IN ('interstate', 'export', 'zero_rated')";
        }
        $interCond = '(' . implode(' OR ', $interCondParts) . ')';
        $igstSum = "SUM(CASE WHEN {$interCond} THEN IFNULL(sii.tax_amount, 0) ELSE 0 END)";
        $cgstSum = "SUM(CASE WHEN {$interCond} THEN 0 ELSE ROUND(IFNULL(sii.tax_amount, 0) / 2, 2) END)";
        $sgstSum = "SUM(CASE WHEN {$interCond} THEN 0 ELSE ROUND(IFNULL(sii.tax_amount, 0) - ROUND(IFNULL(sii.tax_amount, 0) / 2, 2), 2) END)";
    } else {
        $igstSum = '0';
        $cgstSum = 'ROUND(SUM(IFNULL(sii.tax_amount, 0)) / 2, 2)';
        $sgstSum = 'ROUND(SUM(IFNULL(sii.tax_amount, 0)) - ROUND(SUM(IFNULL(sii.tax_amount, 0)) / 2, 2), 2)';
    }

    $active = auragold_gst_report_active_doc_sql('si');
    $sql = "
        SELECT
            {$hsnExpr} AS hsn,
            CASE WHEN CHAR_LENGTH({$gstinExpr}) > 0 THEN 'B2B' ELSE 'B2C' END AS supply_type,
            COUNT(DISTINCT si.id) AS invoice_count,
            SUM(IFNULL(sii.quantity, 0)) AS qty,
            SUM(IFNULL(sii.amount, IFNULL(sii.net_amount, 0))) AS taxable,
            SUM(IFNULL(sii.tax_amount, 0)) AS tax,
            SUM(IFNULL(sii.net_amt_with_tax, IFNULL(sii.net_amount, 0) + IFNULL(sii.tax_amount, 0))) AS total,
            {$igstSum} AS igst,
            {$cgstSum} AS cgst,
            {$sgstSum} AS sgst
        {$joins}
        WHERE si.invoice_date BETWEEN '{$fromEsc}' AND '{$toEsc}'
          {$active}
          {$br}
        GROUP BY hsn, supply_type
        ORDER BY hsn ASC, supply_type ASC
    ";

    $rows = [];
    $totals = ['qty' => 0.0, 'taxable' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'igst' => 0.0, 'tax' => 0.0, 'total' => 0.0, 'count' => 0];
    $res = false;
    try {
        $res = mysqli_query($conn, $sql);
    } catch (Throwable $e) {
        $res = false;
    }
    if (!$res) {
        $sql2 = "
            SELECT
                '7113' AS hsn,
                CASE WHEN CHAR_LENGTH({$gstinExpr}) > 0 THEN 'B2B' ELSE 'B2C' END AS supply_type,
                COUNT(DISTINCT si.id) AS invoice_count,
                SUM(IFNULL(sii.quantity, 0)) AS qty,
                SUM(IFNULL(sii.amount, IFNULL(sii.net_amount, 0))) AS taxable,
                SUM(IFNULL(sii.tax_amount, 0)) AS tax,
                SUM(IFNULL(sii.net_amt_with_tax, IFNULL(sii.net_amount, 0) + IFNULL(sii.tax_amount, 0))) AS total,
                0 AS igst,
                ROUND(SUM(IFNULL(sii.tax_amount, 0)) / 2, 2) AS cgst,
                ROUND(SUM(IFNULL(sii.tax_amount, 0)) - ROUND(SUM(IFNULL(sii.tax_amount, 0)) / 2, 2), 2) AS sgst
            FROM tbl_sale_invoice_items sii
            INNER JOIN tbl_sale_invoices si ON si.id = sii.invoice_id
            LEFT JOIN tbl_customers c ON c.id = si.customer_id
            WHERE si.invoice_date BETWEEN '{$fromEsc}' AND '{$toEsc}'
              {$active}
              {$br}
            GROUP BY supply_type
            ORDER BY supply_type ASC
        ";
        try {
            $res = mysqli_query($conn, $sql2);
        } catch (Throwable $e) {
            $res = false;
        }
    }

    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $qty = (float) ($r['qty'] ?? 0);
            $taxable = (float) ($r['taxable'] ?? 0);
            $cgst = (float) ($r['cgst'] ?? 0);
            $sgst = (float) ($r['sgst'] ?? 0);
            $igst = (float) ($r['igst'] ?? 0);
            $tax = (float) ($r['tax'] ?? ($cgst + $sgst + $igst));
            $total = (float) ($r['total'] ?? 0);
            $rows[] = [
                'hsn' => (string) ($r['hsn'] ?? ''),
                'supply_type' => (string) ($r['supply_type'] ?? ''),
                'invoice_count' => (int) ($r['invoice_count'] ?? 0),
                'qty' => round($qty, 3),
                'taxable' => round($taxable, 2),
                'cgst' => round($cgst, 2),
                'sgst' => round($sgst, 2),
                'igst' => round($igst, 2),
                'tax' => round($tax, 2),
                'total' => round($total, 2),
            ];
            $totals['qty'] += $qty;
            $totals['taxable'] += $taxable;
            $totals['cgst'] += $cgst;
            $totals['sgst'] += $sgst;
            $totals['igst'] += $igst;
            $totals['tax'] += $tax;
            $totals['total'] += $total;
            $totals['count']++;
        }
    }
    foreach (['qty', 'taxable', 'cgst', 'sgst', 'igst', 'tax', 'total'] as $k) {
        $totals[$k] = round((float) $totals[$k], $k === 'qty' ? 3 : 2);
    }

    return [
        'columns' => [
            ['key' => 'hsn', 'label' => 'HSN'],
            ['key' => 'supply_type', 'label' => 'B2B / B2C'],
            ['key' => 'invoice_count', 'label' => 'Invoices', 'align' => 'right'],
            ['key' => 'qty', 'label' => 'Qty', 'align' => 'right'],
            ['key' => 'taxable', 'label' => 'Taxable', 'align' => 'right'],
            ['key' => 'cgst', 'label' => 'CGST', 'align' => 'right'],
            ['key' => 'sgst', 'label' => 'SGST', 'align' => 'right'],
            ['key' => 'igst', 'label' => 'IGST', 'align' => 'right'],
            ['key' => 'tax', 'label' => 'Total Tax', 'align' => 'right'],
            ['key' => 'total', 'label' => 'Total', 'align' => 'right'],
        ],
        'rows' => $rows,
        'totals' => $totals,
        'note' => 'HSN summary split by B2B / B2C with CGST/SGST/IGST (GSTR-1 Table 12).',
    ];
}

function auragold_gst_report_purchase_register(mysqli $conn, string $from, string $to, string $type): array
{
    $fromEsc = mysqli_real_escape_string($conn, $from);
    $toEsc = mysqli_real_escape_string($conn, $to);
    $br = auragold_gst_report_pi_branch_sql($conn, 'pi');
    $active = auragold_gst_report_active_doc_sql('pi');

    $hasItems = false;
    $chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_purchase_invoice_items'");
    if ($chk && mysqli_num_rows($chk) > 0) {
        $hasItems = true;
    }

    $lineTax = $hasItems
        ? '(SELECT IFNULL(SUM(IFNULL(pii.tax_amount, IFNULL(pii.tax, 0))),0) FROM tbl_purchase_invoice_items pii WHERE pii.invoice_id = pi.id)'
        : '0';
    $lineTaxable = $hasItems
        ? '(SELECT IFNULL(SUM(IFNULL(pii.amount, IFNULL(pii.net_amount, 0))),0) FROM tbl_purchase_invoice_items pii WHERE pii.invoice_id = pi.id)'
        : '0';

    $hasHdr = auragold_tbl_has_column($conn, 'tbl_purchase_invoices', 'gst_cgst_amount');
    if ($hasHdr) {
        $hdrTax = '(IFNULL(pi.gst_cgst_amount,0) + IFNULL(pi.gst_sgst_amount,0) + IFNULL(pi.gst_igst_amount,0))';
        $cgstExpr = "CASE WHEN {$hdrTax} > 0 THEN IFNULL(pi.gst_cgst_amount,0) ELSE ROUND({$lineTax} / 2, 2) END";
        $sgstExpr = "CASE WHEN {$hdrTax} > 0 THEN IFNULL(pi.gst_sgst_amount,0) ELSE ROUND({$lineTax} - ROUND({$lineTax} / 2, 2), 2) END";
        $igstExpr = "CASE WHEN {$hdrTax} > 0 THEN IFNULL(pi.gst_igst_amount,0) ELSE 0 END";
    } else {
        $cgstExpr = "ROUND({$lineTax} / 2, 2)";
        $sgstExpr = "ROUND({$lineTax} - ROUND({$lineTax} / 2, 2), 2)";
        $igstExpr = '0';
    }
    $taxExpr = "({$cgstExpr} + {$sgstExpr} + {$igstExpr})";
    $taxableExpr = "CASE
        WHEN {$lineTaxable} > 0 THEN {$lineTaxable}
        WHEN {$taxExpr} > 0 THEN GREATEST(IFNULL(pi.grand_total, 0) - {$taxExpr}, 0)
        ELSE IFNULL(pi.net_total, IFNULL(pi.subtotal, IFNULL(pi.grand_total, 0)))
    END";

    $sql = "
        SELECT
            pi.invoice_no,
            pi.invoice_date,
            IFNULL(pi.supplier_name, '') AS party_name,
            IFNULL(pi.grand_total, 0) AS invoice_value,
            ({$taxableExpr}) AS taxable,
            ({$cgstExpr}) AS cgst,
            ({$sgstExpr}) AS sgst,
            ({$igstExpr}) AS igst,
            ({$taxExpr}) AS tax_amount
        FROM tbl_purchase_invoices pi
        WHERE pi.invoice_date BETWEEN '{$fromEsc}' AND '{$toEsc}'
          {$active}
          {$br}
        ORDER BY pi.invoice_date ASC, pi.id ASC
    ";

    $rows = [];
    $totals = [
        'invoice_value' => 0.0,
        'tax_amount' => 0.0,
        'taxable' => 0.0,
        'cgst' => 0.0,
        'sgst' => 0.0,
        'igst' => 0.0,
        'count' => 0,
    ];
    $res = @mysqli_query($conn, $sql);
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $inv = (float) ($r['invoice_value'] ?? 0);
            $cgst = (float) ($r['cgst'] ?? 0);
            $sgst = (float) ($r['sgst'] ?? 0);
            $igst = (float) ($r['igst'] ?? 0);
            $tax = (float) ($r['tax_amount'] ?? ($cgst + $sgst + $igst));
            $taxable = (float) ($r['taxable'] ?? max($inv - $tax, 0));
            $rows[] = [
                'invoice_no' => (string) ($r['invoice_no'] ?? ''),
                'invoice_date' => (string) ($r['invoice_date'] ?? ''),
                'party_name' => (string) ($r['party_name'] ?? ''),
                'taxable' => round($taxable, 2),
                'cgst' => round($cgst, 2),
                'sgst' => round($sgst, 2),
                'igst' => round($igst, 2),
                'tax_amount' => round($tax, 2),
                'invoice_value' => round($inv, 2),
            ];
            $totals['invoice_value'] += $inv;
            $totals['tax_amount'] += $tax;
            $totals['taxable'] += $taxable;
            $totals['cgst'] += $cgst;
            $totals['sgst'] += $sgst;
            $totals['igst'] += $igst;
            $totals['count']++;
        }
    }
    foreach (['invoice_value', 'tax_amount', 'taxable', 'cgst', 'sgst', 'igst'] as $k) {
        $totals[$k] = round((float) $totals[$k], 2);
    }

    $note = 'Purchase invoices for ITC verification.';
    if ($type === 'itc_register') {
        $note = 'Input tax credit from purchase invoices in the period (CGST / SGST / IGST).';
    } elseif ($type === 'metal_purchase') {
        $note = 'Purchase invoices (metal / goods). Filter further by supplier type if needed.';
    }

    return [
        'columns' => [
            ['key' => 'invoice_no', 'label' => 'Invoice No'],
            ['key' => 'invoice_date', 'label' => 'Date'],
            ['key' => 'party_name', 'label' => 'Supplier'],
            ['key' => 'taxable', 'label' => 'Taxable', 'align' => 'right'],
            ['key' => 'cgst', 'label' => 'CGST', 'align' => 'right'],
            ['key' => 'sgst', 'label' => 'SGST', 'align' => 'right'],
            ['key' => 'igst', 'label' => 'IGST', 'align' => 'right'],
            ['key' => 'tax_amount', 'label' => 'Total Tax (ITC)', 'align' => 'right'],
            ['key' => 'invoice_value', 'label' => 'Invoice Value', 'align' => 'right'],
        ],
        'rows' => $rows,
        'totals' => $totals,
        'note' => $note,
    ];
}

function auragold_gst_report_gstr3b_summary(mysqli $conn, string $from, string $to): array
{
    $out = auragold_gst_report_sales_register($conn, $from, $to, 'liability');
    $purchase = auragold_gst_report_purchase_register($conn, $from, $to, 'itc_register');

    $outCgst = (float) ($out['totals']['cgst'] ?? 0);
    $outSgst = (float) ($out['totals']['sgst'] ?? 0);
    $outIgst = (float) ($out['totals']['igst'] ?? 0);
    $outTax = (float) ($out['totals']['tax'] ?? 0);
    $outTaxable = (float) ($out['totals']['taxable_value'] ?? 0);

    $inCgst = (float) ($purchase['totals']['cgst'] ?? 0);
    $inSgst = (float) ($purchase['totals']['sgst'] ?? 0);
    $inIgst = (float) ($purchase['totals']['igst'] ?? 0);
    $itc = (float) ($purchase['totals']['tax_amount'] ?? 0);
    $inTaxable = (float) ($purchase['totals']['taxable'] ?? 0);

    $payCgst = max($outCgst - $inCgst, 0);
    $paySgst = max($outSgst - $inSgst, 0);
    $payIgst = max($outIgst - $inIgst, 0);
    $payable = max($outTax - $itc, 0);

    $rows = [
        ['particulars' => 'Outward taxable supplies (sale invoices)', 'taxable' => $outTaxable, 'cgst' => $outCgst, 'sgst' => $outSgst, 'igst' => $outIgst, 'tax' => $outTax],
        ['particulars' => 'Inward supplies — ITC (purchases)', 'taxable' => $inTaxable, 'cgst' => $inCgst, 'sgst' => $inSgst, 'igst' => $inIgst, 'tax' => $itc],
        ['particulars' => 'Net GST payable (Output − ITC)', 'taxable' => 0, 'cgst' => $payCgst, 'sgst' => $paySgst, 'igst' => $payIgst, 'tax' => $payable],
    ];

    return [
        'columns' => [
            ['key' => 'particulars', 'label' => 'Particulars'],
            ['key' => 'taxable', 'label' => 'Taxable', 'align' => 'right'],
            ['key' => 'cgst', 'label' => 'CGST', 'align' => 'right'],
            ['key' => 'sgst', 'label' => 'SGST', 'align' => 'right'],
            ['key' => 'igst', 'label' => 'IGST', 'align' => 'right'],
            ['key' => 'tax', 'label' => 'Tax', 'align' => 'right'],
        ],
        'rows' => $rows,
        'totals' => [
            'output_tax' => round($outTax, 2),
            'itc' => round($itc, 2),
            'payable' => round($payable, 2),
            'cgst' => round($payCgst, 2),
            'sgst' => round($paySgst, 2),
            'igst' => round($payIgst, 2),
            'count' => 3,
        ],
        'note' => 'GSTR-3B style summary from sale (output CGST/SGST/IGST) and purchase (ITC) data.',
    ];
}

function auragold_gst_report_eway_or_einvoice(mysqli $conn, string $from, string $to, string $kind): array
{
    $fromEsc = mysqli_real_escape_string($conn, $from);
    $toEsc = mysqli_real_escape_string($conn, $to);
    $br = auragold_gst_report_si_branch_sql($conn, 'si');

    $hasEway = auragold_tbl_has_column($conn, 'tbl_sale_invoices', 'eway_bill_no');
    if (!$hasEway) {
        return [
            'columns' => [],
            'rows' => [],
            'totals' => [],
            'note' => 'E-Way Bill columns are not available on sale invoices.',
        ];
    }

    $whereExtra = $kind === 'eway'
        ? " AND (NULLIF(TRIM(si.eway_bill_no), '') IS NOT NULL OR IFNULL(si.eway_status, '') <> '')"
        : " AND (NULLIF(TRIM(si.eway_bill_no), '') IS NOT NULL OR IFNULL(si.eway_status, '') <> '')";

    $note = $kind === 'eway'
        ? 'Sale invoices with e-Way Bill details.'
        : 'E-Invoice / IRN tracking uses e-Way related fields where IRN is not stored separately yet.';

    $active = auragold_gst_report_active_doc_sql('si');
    $sql = "
        SELECT
            si.invoice_no,
            si.invoice_date,
            IFNULL(si.customer_name, '') AS party_name,
            IFNULL(si.eway_bill_no, '') AS eway_bill_no,
            IFNULL(si.eway_bill_date, '') AS eway_bill_date,
            IFNULL(si.eway_status, '') AS eway_status,
            IFNULL(si.eway_valid_upto, '') AS eway_valid_upto,
            IFNULL(si.grand_total, 0) AS invoice_value
        FROM tbl_sale_invoices si
        WHERE si.invoice_date BETWEEN '{$fromEsc}' AND '{$toEsc}'
          {$active}
          {$br}
          {$whereExtra}
        ORDER BY si.invoice_date ASC, si.id ASC
    ";

    $rows = [];
    $totals = ['invoice_value' => 0.0, 'count' => 0];
    $res = @mysqli_query($conn, $sql);
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $inv = (float) ($r['invoice_value'] ?? 0);
            $rows[] = [
                'invoice_no' => (string) ($r['invoice_no'] ?? ''),
                'invoice_date' => (string) ($r['invoice_date'] ?? ''),
                'party_name' => (string) ($r['party_name'] ?? ''),
                'eway_bill_no' => (string) ($r['eway_bill_no'] ?? ''),
                'eway_bill_date' => (string) ($r['eway_bill_date'] ?? ''),
                'eway_status' => (string) ($r['eway_status'] ?? ''),
                'eway_valid_upto' => (string) ($r['eway_valid_upto'] ?? ''),
                'invoice_value' => round($inv, 2),
            ];
            $totals['invoice_value'] += $inv;
            $totals['count']++;
        }
    }
    $totals['invoice_value'] = round($totals['invoice_value'], 2);

    return [
        'columns' => [
            ['key' => 'invoice_no', 'label' => 'Invoice No'],
            ['key' => 'invoice_date', 'label' => 'Date'],
            ['key' => 'party_name', 'label' => 'Party'],
            ['key' => 'eway_bill_no', 'label' => 'E-Way Bill No'],
            ['key' => 'eway_bill_date', 'label' => 'EWB Date'],
            ['key' => 'eway_status', 'label' => 'Status'],
            ['key' => 'eway_valid_upto', 'label' => 'Valid Upto'],
            ['key' => 'invoice_value', 'label' => 'Invoice Value', 'align' => 'right'],
        ],
        'rows' => $rows,
        'totals' => $totals,
        'note' => $note,
    ];
}

function auragold_gst_report_old_gold(mysqli $conn, string $from, string $to): array
{
    $fromEsc = mysqli_real_escape_string($conn, $from);
    $toEsc = mysqli_real_escape_string($conn, $to);
    $table = 'tbl_old_jewelry_scrap_invoices';
    $chk = @mysqli_query($conn, "SHOW TABLES LIKE '{$table}'");
    if (!$chk || mysqli_num_rows($chk) === 0) {
        return ['columns' => [], 'rows' => [], 'totals' => [], 'note' => 'Old gold / scrap invoice table not found.'];
    }

    $dateCol = auragold_tbl_has_column($conn, $table, 'invoice_date') ? 'invoice_date' : 'created_at';
    $noCol = auragold_tbl_has_column($conn, $table, 'invoice_no') ? 'invoice_no' : 'id';
    $partyCol = auragold_tbl_has_column($conn, $table, 'customer_name') ? 'customer_name' : (auragold_tbl_has_column($conn, $table, 'supplier_name') ? 'supplier_name' : "''");
    $valCol = auragold_tbl_has_column($conn, $table, 'grand_total') ? 'grand_total' : (auragold_tbl_has_column($conn, $table, 'net_total') ? 'net_total' : '0');

    $partySel = $partyCol === "''" ? "'' AS party_name" : "IFNULL({$partyCol}, '') AS party_name";
    $sql = "
        SELECT {$noCol} AS doc_no, {$dateCol} AS doc_date, {$partySel}, IFNULL({$valCol}, 0) AS doc_value
        FROM {$table}
        WHERE DATE({$dateCol}) BETWEEN '{$fromEsc}' AND '{$toEsc}'
        ORDER BY {$dateCol} ASC, id ASC
    ";

    $rows = [];
    $totals = ['doc_value' => 0.0, 'count' => 0];
    $res = @mysqli_query($conn, $sql);
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $val = (float) ($r['doc_value'] ?? 0);
            $rows[] = [
                'doc_no' => (string) ($r['doc_no'] ?? ''),
                'doc_date' => (string) ($r['doc_date'] ?? ''),
                'party_name' => (string) ($r['party_name'] ?? ''),
                'doc_value' => round($val, 2),
            ];
            $totals['doc_value'] += $val;
            $totals['count']++;
        }
    }
    $totals['doc_value'] = round($totals['doc_value'], 2);

    return [
        'columns' => [
            ['key' => 'doc_no', 'label' => 'Doc No'],
            ['key' => 'doc_date', 'label' => 'Date'],
            ['key' => 'party_name', 'label' => 'Party'],
            ['key' => 'doc_value', 'label' => 'Value', 'align' => 'right'],
        ],
        'rows' => $rows,
        'totals' => $totals,
        'note' => 'Old gold / scrap purchases — often non-GST; kept for reference and reconciliation.',
    ];
}

function auragold_gst_report_simple_doc(mysqli $conn, string $from, string $to, string $kind): array
{
    $map = [
        'jobwork' => ['table' => 'tbl_jobwork_invoices', 'date' => 'invoice_date', 'no' => 'invoice_no', 'party' => 'customer_name', 'value' => 'grand_total', 'label' => 'Jobwork'],
        'advance' => ['table' => 'tbl_advance_payments', 'date' => 'payment_date', 'no' => 'voucher_no', 'party' => 'customer_name', 'value' => 'grand_total', 'label' => 'Advance'],
        'repair' => ['table' => 'tbl_repair_invoices', 'date' => 'invoice_date', 'no' => 'invoice_no', 'party' => 'customer_name', 'value' => 'grand_total', 'label' => 'Repair'],
    ];
    if (!isset($map[$kind])) {
        return ['columns' => [], 'rows' => [], 'totals' => [], 'note' => 'Unknown document type.'];
    }
    $cfg = $map[$kind];
    $table = $cfg['table'];
    $chk = @mysqli_query($conn, "SHOW TABLES LIKE '{$table}'");
    if (!$chk || mysqli_num_rows($chk) === 0) {
        return ['columns' => [], 'rows' => [], 'totals' => [], 'note' => $cfg['label'] . ' table not found.'];
    }

    $fromEsc = mysqli_real_escape_string($conn, $from);
    $toEsc = mysqli_real_escape_string($conn, $to);

    $dateCol = auragold_tbl_has_column($conn, $table, $cfg['date']) ? $cfg['date'] : (auragold_tbl_has_column($conn, $table, 'created_at') ? 'created_at' : null);
    if ($dateCol === null) {
        return ['columns' => [], 'rows' => [], 'totals' => [], 'note' => 'No date column on ' . $table];
    }
    $noCol = auragold_tbl_has_column($conn, $table, $cfg['no']) ? $cfg['no'] : 'id';
    $partyCol = auragold_tbl_has_column($conn, $table, $cfg['party']) ? $cfg['party'] : null;
    $valCol = auragold_tbl_has_column($conn, $table, $cfg['value'])
        ? $cfg['value']
        : (auragold_tbl_has_column($conn, $table, 'net_total') ? 'net_total' : (auragold_tbl_has_column($conn, $table, 'amount') ? 'amount' : null));

    $partySel = $partyCol ? "IFNULL({$partyCol}, '') AS party_name" : "'' AS party_name";
    $valSel = $valCol ? "IFNULL({$valCol}, 0) AS doc_value" : '0 AS doc_value';

    $sql = "
        SELECT {$noCol} AS doc_no, {$dateCol} AS doc_date, {$partySel}, {$valSel}
        FROM {$table}
        WHERE DATE({$dateCol}) BETWEEN '{$fromEsc}' AND '{$toEsc}'
        ORDER BY {$dateCol} ASC, id ASC
    ";

    $rows = [];
    $totals = ['doc_value' => 0.0, 'count' => 0];
    $res = @mysqli_query($conn, $sql);
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $val = (float) ($r['doc_value'] ?? 0);
            $rows[] = [
                'doc_no' => (string) ($r['doc_no'] ?? ''),
                'doc_date' => (string) ($r['doc_date'] ?? ''),
                'party_name' => (string) ($r['party_name'] ?? ''),
                'doc_value' => round($val, 2),
            ];
            $totals['doc_value'] += $val;
            $totals['count']++;
        }
    }
    $totals['doc_value'] = round($totals['doc_value'], 2);

    return [
        'columns' => [
            ['key' => 'doc_no', 'label' => 'Doc No'],
            ['key' => 'doc_date', 'label' => 'Date'],
            ['key' => 'party_name', 'label' => 'Party'],
            ['key' => 'doc_value', 'label' => 'Value', 'align' => 'right'],
        ],
        'rows' => $rows,
        'totals' => $totals,
        'note' => $cfg['label'] . ' documents in the selected period.',
    ];
}

function auragold_gst_report_branch_transfer(mysqli $conn, string $from, string $to): array
{
    $candidates = ['tbl_stock_transfers', 'tbl_stock_transfer', 'tbl_stock_transfer_pending'];
    $table = null;
    foreach ($candidates as $t) {
        $chk = @mysqli_query($conn, "SHOW TABLES LIKE '{$t}'");
        if ($chk && mysqli_num_rows($chk) > 0) {
            $table = $t;
            break;
        }
    }
    if ($table === null) {
        return ['columns' => [], 'rows' => [], 'totals' => [], 'note' => 'Stock transfer table not found.'];
    }

    $fromEsc = mysqli_real_escape_string($conn, $from);
    $toEsc = mysqli_real_escape_string($conn, $to);
    $dateCol = auragold_tbl_has_column($conn, $table, 'transfer_date')
        ? 'transfer_date'
        : (auragold_tbl_has_column($conn, $table, 'created_at') ? 'created_at' : null);
    if ($dateCol === null) {
        return ['columns' => [], 'rows' => [], 'totals' => [], 'note' => 'No date column on stock transfer.'];
    }

    $cols = [];
    $cr = mysqli_query($conn, "SHOW COLUMNS FROM `{$table}`");
    while ($cr && $row = mysqli_fetch_assoc($cr)) {
        $cols[$row['Field']] = true;
    }
    $noCol = isset($cols['transfer_no']) ? 'transfer_no' : (isset($cols['voucher_no']) ? 'voucher_no' : 'id');
    $fromBr = isset($cols['from_branch_id']) ? 'from_branch_id' : (isset($cols['source_branch_id']) ? 'source_branch_id' : null);
    $toBr = isset($cols['to_branch_id']) ? 'to_branch_id' : (isset($cols['dest_branch_id']) ? 'dest_branch_id' : null);
    $statusCol = isset($cols['status']) ? 'status' : null;

    $fromSel = $fromBr ? "IFNULL({$fromBr}, '') AS from_branch" : "'' AS from_branch";
    $toSel = $toBr ? "IFNULL({$toBr}, '') AS to_branch" : "'' AS to_branch";
    $stSel = $statusCol ? "IFNULL({$statusCol}, '') AS status" : "'' AS status";

    $sql = "
        SELECT {$noCol} AS doc_no, {$dateCol} AS doc_date, {$fromSel}, {$toSel}, {$stSel}
        FROM {$table}
        WHERE DATE({$dateCol}) BETWEEN '{$fromEsc}' AND '{$toEsc}'
        ORDER BY {$dateCol} ASC, id ASC
        LIMIT 2000
    ";

    $rows = [];
    $totals = ['count' => 0];
    $res = @mysqli_query($conn, $sql);
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $rows[] = [
                'doc_no' => (string) ($r['doc_no'] ?? ''),
                'doc_date' => (string) ($r['doc_date'] ?? ''),
                'from_branch' => (string) ($r['from_branch'] ?? ''),
                'to_branch' => (string) ($r['to_branch'] ?? ''),
                'status' => (string) ($r['status'] ?? ''),
            ];
            $totals['count']++;
        }
    }

    return [
        'columns' => [
            ['key' => 'doc_no', 'label' => 'Transfer No'],
            ['key' => 'doc_date', 'label' => 'Date'],
            ['key' => 'from_branch', 'label' => 'From Branch'],
            ['key' => 'to_branch', 'label' => 'To Branch'],
            ['key' => 'status', 'label' => 'Status'],
        ],
        'rows' => $rows,
        'totals' => $totals,
        'note' => 'Inter-branch stock transfers for GST / stock movement reference.',
    ];
}

function auragold_gst_report_placeholder(string $type): array
{
    $meta = auragold_gst_report_meta($type);
    $label = $meta['label'] ?? $type;

    return [
        'columns' => [
            ['key' => 'info', 'label' => 'Information'],
        ],
        'rows' => [
            ['info' => $label . ' — portal import / payment mapping will be added in a later update. Use related registers (Purchase, GSTR-3B, Liability) for now.'],
        ],
        'totals' => ['count' => 0],
        'note' => 'Coming soon: requires GST portal data import or dedicated payment vouchers.',
    ];
}
