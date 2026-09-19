<?php
/**
 * Template 8 – ARJUN JEWELLERS retail tax invoice.
 * Header with logo/company/GSTIN, customer box, particulars table, bank/GST/HM summary, signatures.
 */
if (!isset($items) || !is_array($items)) {
    $items = [];
}
$print_settings = is_array($print_settings ?? null) ? $print_settings : [];

if (function_exists('mergeInvoicePrintColumnLabels')) {
    $col_labels = mergeInvoicePrintColumnLabels($col_labels ?? [], $print_settings);
}

$t8_cols = (isset($selected_columns) && is_array($selected_columns) && !empty($selected_columns))
    ? array_values($selected_columns)
    : ['sr_no', 'item_name', 'hsn', 'purity', 'metal_qty', 'gross_weight', 'net_weight', 'wastage_wt', 'rate', 'making_charge', 'stone_amount', 'amount'];
$t8_rows = (isset($item_rows) && is_array($item_rows)) ? $item_rows : [];
$t8_labels = is_array($col_labels ?? null) ? $col_labels : [];
$t8_numeric = function_exists('getInvoicePrintSaleInvoiceNumericColumns') ? getInvoicePrintSaleInvoiceNumericColumns() : [];
$t8_use_catalog = isset($selected_columns) && is_array($selected_columns) && !empty($selected_columns);

$t8_td_align = static function ($col_key) use ($t8_numeric) {
    if ($col_key === 'sr_no') {
        return 'center';
    }
    if (in_array($col_key, ['item_name', 'category', 'huid', 'design_no', 'barcode', 'item_code', 'short_code', 'product_category'], true)) {
        return 'left';
    }
    if (in_array($col_key, $t8_numeric, true)) {
        return 'right';
    }
    return 'center';
};

$t8_item_sum = static function (array $item, $col_key) {
    switch ($col_key) {
        case 'metal_qty':
        case 'quantity':
            return (float) ($item['metal_qty'] ?? $item['quantity'] ?? 0);
        case 'gross_weight':
            return (float) ($item['gross_weight'] ?? 0);
        case 'net_weight':
            return (float) ($item['net_weight'] ?? $item['final_weight'] ?? 0);
        case 'wastage_wt':
            return (float) ($item['wastage_wt'] ?? 0);
        case 'stone_amount':
            return (float) ($item['stone_amount'] ?? 0) + (float) ($item['diamond_amount'] ?? 0);
        case 'sale_amount':
            $sa = (float) ($item['sale_amount'] ?? 0);
            if ($sa > 0) {
                return $sa;
            }
            return (float) ($item['net_amount'] ?? $item['amount'] ?? 0);
        case 'sale_amount_with':
        case 'net_amt_with_tax':
            // Match buildInvoicePrintItemRowFromSaleItem: fall back when sale_amount_with is 0/empty in DB.
            $saw = (float) ($item['sale_amount_with'] ?? 0);
            if ($saw > 0) {
                return $saw;
            }
            $netTax = (float) ($item['net_amt_with_tax'] ?? 0);
            if ($netTax > 0) {
                return $netTax;
            }
            return (float) ($item['net_amount'] ?? $item['amount'] ?? 0)
                + (float) ($item['tax_amount'] ?? $item['tax'] ?? 0);
        case 'amount':
        case 'net_amt':
            return (float) ($item['net_amount'] ?? $item['amount'] ?? 0);
        case 'making_charge':
            return (float) ($item['making_amount'] ?? $item['making'] ?? 0);
        case 'tax':
            return (float) ($item['tax_amount'] ?? $item['tax'] ?? 0);
        default:
            if (isset($item[$col_key]) && is_numeric($item[$col_key])) {
                return (float) $item[$col_key];
            }
            return 0.0;
    }
};

$t8_col_totals = [];
foreach ($t8_cols as $ck) {
    if ($ck === 'sr_no' || !in_array($ck, $t8_numeric, true)) {
        continue;
    }
    $t8_col_totals[$ck] = 0.0;
}
// Prefer display rows (already apply sale_amount_with → net_amt_with_tax fallback) so Total matches cells.
if (!empty($t8_rows) && is_array($t8_rows)) {
    foreach ($t8_rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        foreach (array_keys($t8_col_totals) as $ck) {
            $raw = isset($row[$ck]) ? str_replace(',', '', trim((string) $row[$ck])) : '';
            if ($raw !== '' && is_numeric($raw)) {
                $t8_col_totals[$ck] += (float) $raw;
            }
        }
    }
} elseif (!empty($items) && is_array($items)) {
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        foreach (array_keys($t8_col_totals) as $ck) {
            $t8_col_totals[$ck] += $t8_item_sum($item, $ck);
        }
    }
}

$h = static function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};

$min_rows = (int) ($print_settings['t8_min_item_rows'] ?? 2);
if ($min_rows < 1) {
    $min_rows = 1;
}
if ($min_rows > 40) {
    $min_rows = 40;
}

$company_gstin = trim((string) ($print_settings['company_gst'] ?? ''));
if ($company_gstin === '') {
    $company_gstin = trim((string) ($company_trn ?? ''));
}
$company_pan = trim((string) ($print_settings['company_pan'] ?? ''));

$doc_date_t8 = $doc_date ?? '';
if (!empty($invoice['invoice_date'])) {
    $doc_date_t8 = date('d/m/Y', strtotime($invoice['invoice_date']));
}

$cust_mobile = '';
$cust_address = '';
$cust_pan = '';
$cust_aadhar = '';
$cust_gstin = '';
if (!empty($invoice['customer_id']) && isset($conn) && $conn) {
    $crow = @getRecord('SELECT * FROM tbl_customers WHERE id = ' . (int) $invoice['customer_id'] . ' LIMIT 1');
    if (is_array($crow)) {
        $cust_mobile = trim((string) ($crow['mobile_no'] ?? $crow['phone_no'] ?? $crow['phone'] ?? ''));
        $parts = array_filter([
            trim((string) ($crow['billing_address1'] ?? $crow['address'] ?? $crow['address_line'] ?? '')),
            trim((string) ($crow['billing_address2'] ?? '')),
            trim((string) ($crow['billing_city'] ?? $crow['city'] ?? '')),
            trim((string) ($crow['billing_state'] ?? $crow['state'] ?? '')),
            trim((string) ($crow['billing_zip_code'] ?? $crow['pincode'] ?? '')),
        ]);
        $cust_address = implode(', ', $parts);
        $cust_pan = trim((string) ($crow['trade_no'] ?? $crow['registration_no'] ?? ''));
        $cust_aadhar = trim((string) ($crow['national_id'] ?? $crow['identity_no'] ?? ''));
        $cust_gstin = trim((string) ($crow['gstin'] ?? $crow['gst_no'] ?? ''));
    }
}

$bank_name = trim((string) ($print_settings['t8_bank_name'] ?? ''));
$bank_account_no = trim((string) ($print_settings['t8_bank_account_no'] ?? ''));
$bank_ifsc = trim((string) ($print_settings['t8_bank_ifsc'] ?? ''));
$bank_branch = trim((string) ($print_settings['t8_bank_branch'] ?? ''));
if ($bank_name === '' && function_exists('auragold_settings_branch_id')) {
    $bid = auragold_settings_branch_id();
    if ($bid > 0 && function_exists('getRecordMaster')) {
        $br = getRecordMaster('SELECT bank_name, bank_account_no, bank_ifsc, name FROM tbl_branches WHERE id = ' . (int) $bid . ' LIMIT 1');
        if (is_array($br)) {
            if ($bank_name === '') {
                $bank_name = trim((string) ($br['bank_name'] ?? ''));
            }
            if ($bank_account_no === '') {
                $bank_account_no = trim((string) ($br['bank_account_no'] ?? ''));
            }
            if ($bank_ifsc === '') {
                $bank_ifsc = trim((string) ($br['bank_ifsc'] ?? ''));
            }
            if ($bank_branch === '') {
                $bank_branch = trim((string) ($br['name'] ?? ''));
            }
        }
    }
}

$fmt_wt = static function ($n) {
    return number_format((float) $n, 3, '.', '');
};
$fmt_amt = static function ($n) use ($h) {
    return $h(number_format((float) $n, 2, '.', ''));
};
$fmt_qty = static function ($n) {
    $n = (float) $n;
    if (abs($n - round($n)) < 0.0001) {
        return (string) (int) round($n);
    }
    return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
};

$t8_format_total = static function ($col_key, $value) use ($fmt_wt, $fmt_qty, $fmt_amt, $h, $t8_numeric) {
    if (in_array($col_key, ['gross_weight', 'less_weight', 'net_weight', 'final_weight', 'wastage_wt', 'stone_weight', 'metal_weight'], true)) {
        return $h($fmt_wt($value));
    }
    if (in_array($col_key, ['metal_qty', 'quantity'], true)) {
        return $h($fmt_qty($value));
    }
    if (in_array($col_key, $t8_numeric, true)) {
        return $fmt_amt($value);
    }
    return $h((string) $value);
};

$purity_label = static function ($item) use ($h) {
    $purity = trim((string) ($item['purity'] ?? ''));
    $carat = trim((string) ($item['carat'] ?? ''));
    if ($purity !== '' && is_numeric($purity)) {
        $purity = rtrim(rtrim(number_format((float) $purity, 0, '.', ''), '0'), '.');
    }
    if ($carat !== '' && is_numeric($carat)) {
        $carat = rtrim(rtrim(number_format((float) $carat, 0, '.', ''), '0'), '.');
    }
    if ($purity !== '' && $carat !== '' && $purity !== $carat) {
        return $h($purity . '/' . $carat) . '<br>(K)';
    }
    if ($purity !== '') {
        return $h($purity);
    }
    if ($carat !== '') {
        return $h($carat);
    }
    return '&nbsp;';
};

$making_pct_label = static function ($item) {
    $mr = (float) ($item['making_rate'] ?? 0);
    if ($mr > 0) {
        return rtrim(rtrim(number_format($mr, 2, '.', ''), '0'), '.') . '%';
    }
    $making = (float) ($item['making_amount'] ?? $item['making'] ?? 0);
    $base = (float) ($item['metal_value'] ?? 0);
    if ($base <= 0) {
        $base = (float) ($item['net_amount'] ?? $item['amount'] ?? 0) - $making;
    }
    if ($base > 0.0001 && $making > 0) {
        return rtrim(rtrim(number_format(($making / $base) * 100, 2, '.', ''), '0'), '.') . '%';
    }
    return $making > 0 ? '0.00%' : '0.00%';
};

$item_lines = [];
$tot_pcs = 0.0;
$tot_gross = 0.0;
$tot_net = 0.0;
$tot_wstg = 0.0;
$tot_stone = 0.0;
$tot_line_amt = 0.0;
$sr = 0;
foreach ($items as $item) {
    $sr++;
    $pname = trim((string) ($item['product_name'] ?? ('Product #' . ($item['product_id'] ?? ''))));
    $hsn = trim((string) ($item['hsn'] ?? $item['hsn_code'] ?? ''));
    if ($hsn === '' && !empty($item['barcode'])) {
        $hsn = preg_replace('/\D/', '', (string) $item['barcode']);
        if (strlen($hsn) > 8) {
            $hsn = substr($hsn, 0, 8);
        }
    }
    if ($hsn === '') {
        $hsn = '7113';
    }
    $pcs = (float) ($item['metal_qty'] ?? $item['quantity'] ?? 1);
    $gross = (float) ($item['gross_weight'] ?? 0);
    $net = (float) ($item['net_weight'] ?? $item['final_weight'] ?? 0);
    $wstg = (float) ($item['wastage_wt'] ?? $item['wastage_wt'] ?? 0);
    $rate = (float) ($item['metal_rate'] ?? $item['rate'] ?? 0);
    $stone = (float) ($item['stone_amount'] ?? 0) + (float) ($item['diamond_amount'] ?? 0);
    $line_amt = (float) ($item['net_amount'] ?? $item['amount'] ?? 0);
    if ($line_amt <= 0) {
        $line_amt = (float) ($item['net_amt_with_tax'] ?? 0) - (float) ($item['tax_amount'] ?? 0);
    }

    $tot_pcs += $pcs;
    $tot_gross += $gross;
    $tot_net += $net;
    $tot_wstg += $wstg;
    $tot_stone += $stone;
    $tot_line_amt += $line_amt;

    $item_lines[] = [
        'sr' => $sr,
        'desc' => $pname,
        'hsn' => $hsn,
        'purity_html' => $purity_label($item),
        'pcs' => $pcs,
        'gross' => $gross,
        'net' => $net,
        'wstg' => $wstg,
        'rate' => $rate,
        'making_pct' => $making_pct_label($item),
        'stone' => $stone,
        'amount' => $line_amt,
    ];
}

$pad_rows = max(0, $min_rows - count($t8_use_catalog ? $t8_rows : $item_lines));
$display_rows = ($t8_use_catalog ? count($t8_rows) : count($item_lines)) + $pad_rows;
$last_row_idx = $display_rows;
$t8_col_count = count($t8_cols);

$subtotal_t8 = (float) ($total_before_vat ?? 0);
if ($subtotal_t8 <= 0) {
    $subtotal_t8 = $tot_line_amt > 0 ? $tot_line_amt : ((float) ($subtotal ?? 0) - (float) ($discount_amt ?? 0) + (float) ($additional_amt ?? 0));
}
$gst_total = (float) ($tax_amount ?? 0);
$gst_half = $gst_total / 2.0;
$pct_each = 0.0;
if ($subtotal_t8 > 0.0001 && $gst_total > 0) {
    $pct_each = ($gst_half / $subtotal_t8) * 100.0;
}
$pct_fmt = rtrim(rtrim(number_format($pct_each, 2, '.', ''), '0'), '.');

$grand_t8 = (float) ($grand_total ?? ($subtotal_t8 + $gst_total));
$paid_t8 = (float) ($paid_amt ?? 0);
$balance_t8 = (float) ($balance_amt ?? 0);
$round_up = $grand_t8 - ($subtotal_t8 + $gst_total);
$urd_amt = (float) ($payment_totals['scrap'] ?? 0);

$doc_title_t8 = trim((string) ($print_settings['invoice_title'] ?? ''));
if ($doc_title_t8 === '') {
    $doc_title_t8 = 'TAX INVOICE';
}

$pay_desc_parts = [];
$cur_sym = isset($currency_symbol) ? trim((string) $currency_symbol) : '₹';
if (!empty($payment_totals) && is_array($payment_totals)) {
    $pay_map = [
        'cash' => 'Cash',
        'card' => 'Card',
        'bank' => 'Bank',
        'cheque' => 'Cheque',
        'upi' => 'UPI',
    ];
    foreach ($pay_map as $pk => $plab) {
        $pv = (float) ($payment_totals[$pk] ?? 0);
        if ($pv > 0.0001) {
            $pay_desc_parts[] = $plab . ': ' . $cur_sym . number_format($pv, 2, '.', '');
        }
    }
}
$payment_desc = !empty($pay_desc_parts) ? implode(', ', $pay_desc_parts) : '';

$amount_words_line = trim((string) ($amount_words ?? ''));
if ($amount_words_line !== '' && stripos($amount_words_line, 'rupee') === false) {
    $amount_words_line = 'Rupees ' . $amount_words_line;
}

$auth_sig = trim((string) ($print_settings['authorized_signature'] ?? ''));
if ($auth_sig === '') {
    $auth_sig = 'For: ' . ($company_name ?? '');
}

$terms_display = trim((string) ($print_settings['terms_conditions'] ?? ''));
$show_terms = ($print_settings['footer_terms_conditions'] ?? '1') === '1' && $terms_display !== '';
$thank_you = trim((string) ($print_settings['thank_you_message'] ?? ''));
$show_thank_you = ($print_settings['footer_thank_you_message'] ?? '1') === '1' && $thank_you !== '';

$logo_initial = 'A';
if (!empty($company_name)) {
    $words = preg_split('/\s+/', trim($company_name), 2);
    $logo_initial = strtoupper(substr($words[0], 0, 1));
}
?>
<style>
.invoice.inv-arjun {
    font-family: Arial, Helvetica, sans-serif !important;
    font-size: 12px !important;
    color: #111 !important;
    background: #fff !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    max-width: 210mm !important;
    margin: 0 auto !important;
    padding: 0 !important;
    overflow: visible !important;
}
.invoice.inv-arjun .t8-wrap {
    width: 100%;
    max-width: 210mm;
    margin: 0 auto;
    padding: 10mm 9mm;
    box-sizing: border-box;
    background: #fff;
}
.invoice.inv-arjun table { width: 100%; border-collapse: collapse; }
.invoice.inv-arjun td,
.invoice.inv-arjun th {
    border: 1px solid #777;
    padding: 4px 5px;
    font-size: 11px;
    vertical-align: middle;
}
.invoice.inv-arjun .t8-logo-cell { width: 12%; text-align: center; padding: 5px; }
.invoice.inv-arjun .t8-logo-cell img { max-width: 75px; max-height: 75px; object-fit: contain; }
.invoice.inv-arjun .t8-logo-ph {
    font-family: Georgia, serif;
    font-size: 42px;
    font-weight: bold;
    color: #161b63;
    line-height: 1;
}
.invoice.inv-arjun .t8-company-cell {
    width: 63%;
    text-align: center;
    border-left: none !important;
    border-right: none !important;
}
.invoice.inv-arjun .t8-company-name {
    font-family: Georgia, "Times New Roman", serif;
    font-size: 27px;
    font-weight: bold;
    color: #161b63;
    letter-spacing: 1px;
}
.invoice.inv-arjun .t8-company-address { margin-top: 12px; font-size: 13px; font-weight: bold; }
.invoice.inv-arjun .t8-company-contact {
    width: 25%;
    text-align: right;
    vertical-align: top;
    font-weight: bold;
    font-size: 13px;
    line-height: 1.7;
}
.invoice.inv-arjun .t8-customer-wrap { border: 1px solid #777; margin-top: 4px; }
.invoice.inv-arjun .t8-customer-table td { border: none; padding: 4px 6px; font-size: 11px; }
.invoice.inv-arjun .t8-field-label { font-weight: bold; width: 10%; white-space: nowrap; }
.invoice.inv-arjun .t8-colon { width: 2%; text-align: center; font-weight: bold; }
.invoice.inv-arjun .t8-field-value { width: 38%; }
.invoice.inv-arjun .t8-invoice-title-cell { text-align: center; vertical-align: top !important; padding-top: 2px !important; }
.invoice.inv-arjun .t8-invoice-title {
    display: inline-block;
    background: #000;
    color: #fff;
    font-weight: bold;
    font-size: 14px;
    padding: 4px 12px;
}
.invoice.inv-arjun .t8-items-table { margin-top: 5px; table-layout: fixed; }
.invoice.inv-arjun .t8-items-table th { font-size: 10px; text-align: center; font-weight: bold; padding: 4px 2px; }
.invoice.inv-arjun .t8-items-table td { font-size: 10px; padding: 5px 3px; }
.invoice.inv-arjun .center { text-align: center; }
.invoice.inv-arjun .right { text-align: right; }
.invoice.inv-arjun .left { text-align: left; }
.invoice.inv-arjun .t8-items-table tbody .product-row td {
    border-top: none;
    border-bottom: none;
    vertical-align: top;
    height: 34px;
}
.invoice.inv-arjun .t8-items-table tbody .last-product td {
    height: 100px;
    border-bottom: 1px solid #777;
}
.invoice.inv-arjun .total-row td { font-weight: bold; font-size: 12px; padding: 5px 4px; }
.invoice.inv-arjun .total-label { text-align: left; font-size: 14px !important; }
.invoice.inv-arjun .t8-bottom-table { margin-top: 7px; }
.invoice.inv-arjun .t8-bottom-table td,
.invoice.inv-arjun .t8-bottom-table th { font-size: 10px; padding: 4px; }
.invoice.inv-arjun .section-heading { text-align: center; font-size: 12px !important; font-weight: bold; }
.invoice.inv-arjun .bank-details { line-height: 1.55; font-weight: bold; }
.invoice.inv-arjun .tax-label { font-weight: bold; }
.invoice.inv-arjun .amount-label { font-weight: bold; width: 55%; }
.invoice.inv-arjun .amount-value { text-align: right; width: 45%; }
.invoice.inv-arjun .words-row { font-size: 11px !important; font-weight: bold; padding: 6px !important; }
.invoice.inv-arjun .payment-desc { font-weight: bold; padding: 6px !important; }
.invoice.inv-arjun .t8-terms {
    font-size: 10px;
    line-height: 1.45;
    padding: 6px !important;
    vertical-align: top;
}
.invoice.inv-arjun .t8-terms-label { font-weight: bold; }
.invoice.inv-arjun .t8-thank-you { margin-top: 4px; font-weight: bold; font-size: 11px; }
.invoice.inv-arjun .t8-signature-table td {
    height: 65px;
    vertical-align: bottom;
    text-align: center;
    font-size: 13px;
    font-weight: bold;
    padding-bottom: 6px;
}
.invoice.inv-arjun .authorised { text-align: right !important; font-style: italic; padding-right: 25px !important; }
.invoice.inv-arjun .t8-nested { width: 100%; border-collapse: collapse; }
.invoice.inv-arjun .t8-nested td { border: none; padding: 2px 4px; font-size: 10px; }
@media print {
    .invoice.inv-arjun .t8-wrap { margin: 0; padding: 8mm; width: 210mm; min-height: 297mm; }
}
</style>

<div class="invoice inv-arjun template_8">
<div class="t8-wrap">

    <table class="header-table">
        <tr>
            <td class="t8-logo-cell">
                <?php if (($print_settings['header_company_logo'] ?? '1') === '1' && !empty($has_logo)): ?>
                <img src="<?php echo $h($company_logo); ?>" alt="<?php echo $h($company_name); ?>">
                <?php else: ?>
                <div class="t8-logo-ph"><?php echo $h($logo_initial); ?></div>
                <?php endif; ?>
            </td>
            <td class="t8-company-cell">
                <?php if (($print_settings['header_company_name'] ?? '1') === '1'): ?>
                <div class="t8-company-name"><?php echo $h($company_name); ?></div>
                <?php endif; ?>
                <div class="t8-company-address"><?php echo $h($company_address); ?></div>
            </td>
            <td class="t8-company-contact">
                <?php if (($print_settings['header_gst_number'] ?? '1') === '1' && $company_gstin !== ''): ?>
                GSTIN : <?php echo $h($company_gstin); ?><br>
                <?php endif; ?>
                <?php if (($print_settings['header_phone'] ?? '1') === '1' && !empty($company_phone)): ?>
                Mobile No : <?php echo $h($company_phone); ?>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <div class="t8-customer-wrap">
        <table class="t8-customer-table">
            <tr>
                <td class="t8-field-label">Bill No.</td>
                <td class="t8-colon">:</td>
                <td class="t8-field-value"><?php echo $h($doc_no); ?></td>
                <td rowspan="4" class="t8-invoice-title-cell">
                    <?php if (($print_settings['header_invoice_title'] ?? '1') === '1'): ?>
                    <span class="t8-invoice-title"><?php echo $h($doc_title_t8); ?></span>
                    <?php endif; ?>
                </td>
                <td class="t8-field-label">Bill Date</td>
                <td class="t8-colon">:</td>
                <td class="t8-field-value"><?php echo $h($doc_date_t8); ?></td>
            </tr>
            <tr>
                <td class="t8-field-label">Name</td>
                <td class="t8-colon">:</td>
                <td><?php echo $h($party_name); ?></td>
                <td class="t8-field-label">Pan No</td>
                <td class="t8-colon">:</td>
                <td><?php echo $cust_pan !== '' ? $h($cust_pan) : '&nbsp;'; ?></td>
            </tr>
            <tr>
                <td class="t8-field-label">Address</td>
                <td class="t8-colon">:</td>
                <td><?php echo $cust_address !== '' ? $h($cust_address) : '&nbsp;'; ?></td>
                <td class="t8-field-label">Aadhar No</td>
                <td class="t8-colon">:</td>
                <td><?php echo $cust_aadhar !== '' ? $h($cust_aadhar) : '&nbsp;'; ?></td>
            </tr>
            <tr>
                <td class="t8-field-label">Mob No</td>
                <td class="t8-colon">:</td>
                <td><?php echo $cust_mobile !== '' ? $h($cust_mobile) : '&nbsp;'; ?></td>
                <td class="t8-field-label">GST No</td>
                <td class="t8-colon">:</td>
                <td><?php echo $cust_gstin !== '' ? $h($cust_gstin) : '&nbsp;'; ?></td>
            </tr>
        </table>
    </div>

    <table class="t8-items-table">
        <thead>
        <tr>
            <?php foreach ($t8_cols as $col_key):
                if (!isset($t8_labels[$col_key])) {
                    continue;
                }
                $th_align = $t8_td_align($col_key);
            ?>
            <th class="<?php echo $h($th_align); ?>"><?php echo $h($t8_labels[$col_key]); ?></th>
            <?php endforeach; ?>
        </tr>
        </thead>
        <tbody>
        <?php
        $row_idx = 0;
        if ($t8_use_catalog):
            foreach ($t8_rows as $row):
                $row_idx++;
                $row_cls = 'product-row' . ($row_idx === $last_row_idx ? ' last-product' : '');
        ?>
        <tr class="<?php echo $h($row_cls); ?>">
            <?php foreach ($t8_cols as $col_key):
                if (!isset($t8_labels[$col_key])) {
                    continue;
                }
                $align = $t8_td_align($col_key);
            ?>
            <td class="<?php echo $h($align); ?>"><?php echo htmlspecialchars((string) ($row[$col_key] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            <?php endforeach; ?>
        </tr>
        <?php
            endforeach;
        else:
            foreach ($item_lines as $line):
                $row_idx++;
                $row_cls = 'product-row' . ($row_idx === $last_row_idx ? ' last-product' : '');
        ?>
        <tr class="<?php echo $h($row_cls); ?>">
            <td class="center"><?php echo (int) $line['sr']; ?></td>
            <td class="left"><?php echo $h($line['desc']); ?></td>
            <td class="center"><?php echo $h($line['hsn']); ?></td>
            <td class="center"><?php echo $line['purity_html']; ?></td>
            <td class="center"><?php echo $h($fmt_qty($line['pcs'])); ?></td>
            <td class="right"><?php echo $h($fmt_wt($line['gross'])); ?></td>
            <td class="right"><?php echo $h($fmt_wt($line['net'])); ?></td>
            <td class="right"><?php echo $h($fmt_wt($line['wstg'])); ?></td>
            <td class="right"><?php echo $h(number_format((float) $line['rate'], 2, '.', '')); ?></td>
            <td class="center"><?php echo $h($line['making_pct']); ?></td>
            <td class="right"><?php echo $fmt_amt($line['stone']); ?></td>
            <td class="right"><?php echo $fmt_amt($line['amount']); ?></td>
        </tr>
        <?php
            endforeach;
        endif;
        for ($pi = 0; $pi < $pad_rows; $pi++):
            $row_idx++;
            $row_cls = 'product-row' . ($row_idx === $last_row_idx ? ' last-product' : '');
        ?>
        <tr class="<?php echo $h($row_cls); ?>">
            <?php for ($ci = 0; $ci < $t8_col_count; $ci++): ?>
            <td>&nbsp;</td>
            <?php endfor; ?>
        </tr>
        <?php endfor; ?>
        <?php if (($t8_use_catalog && empty($t8_rows)) || (!$t8_use_catalog && empty($item_lines))): ?>
        <tr class="product-row last-product"><td colspan="<?php echo max(1, $t8_col_count); ?>" class="center">No items</td></tr>
        <?php else: ?>
        <tr class="total-row">
            <?php if ($t8_use_catalog):
                $total_label_shown = false;
                foreach ($t8_cols as $col_key):
                    if (!isset($t8_labels[$col_key])) {
                        continue;
                    }
                    $align = $t8_td_align($col_key);
                    if ($col_key === 'sr_no') {
                        echo '<td></td>';
                        continue;
                    }
                    if (!$total_label_shown) {
                        $total_label_shown = true;
                        echo '<td class="total-label ' . $h($align) . '">Total :</td>';
                        continue;
                    }
                    if (isset($t8_col_totals[$col_key])) {
                        echo '<td class="' . $h($align) . '">' . $t8_format_total($col_key, $t8_col_totals[$col_key]) . '</td>';
                    } else {
                        echo '<td></td>';
                    }
                endforeach;
            else: ?>
            <td></td>
            <td class="total-label">Total :</td>
            <td></td>
            <td></td>
            <td class="center"><?php echo $h($fmt_qty($tot_pcs)); ?></td>
            <td class="right"><?php echo $h($fmt_wt($tot_gross)); ?></td>
            <td class="right"><?php echo $h($fmt_wt($tot_net)); ?></td>
            <td class="right"><?php echo $h($fmt_wt($tot_wstg)); ?></td>
            <td></td>
            <td></td>
            <td class="right"><?php echo $fmt_amt($tot_stone); ?></td>
            <td class="right"><?php echo $fmt_amt($tot_line_amt); ?></td>
            <?php endif; ?>
        </tr>
        <?php endif; ?>
        </tbody>
    </table>

    <table class="t8-bottom-table">
        <tr>
            <th style="width:34%;" class="section-heading">Bank Details</th>
            <th style="width:22%;" class="section-heading">GST Details</th>
            <th style="width:22%;" class="section-heading">HM Tax</th>
            <th style="width:12%;" class="section-heading">Amount</th>
            <td class="right"><?php echo $fmt_amt($subtotal_t8); ?></td>
        </tr>
        <tr>
            <td rowspan="3" class="bank-details">
                <?php if ($bank_name !== ''): ?>Bank Name : <?php echo $h($bank_name); ?><br><?php endif; ?>
                <?php if ($bank_account_no !== ''): ?>Acc. No. &nbsp;&nbsp;&nbsp;: <?php echo $h($bank_account_no); ?><br><?php endif; ?>
                <?php if ($bank_ifsc !== ''): ?>IFSC &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: <?php echo $h($bank_ifsc); ?><br><?php endif; ?>
                <?php if ($bank_branch !== ''): ?>Branch &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: <?php echo $h($bank_branch); ?><?php endif; ?>
                <?php if ($bank_name === '' && $bank_account_no === '' && $bank_ifsc === '' && $bank_branch === ''): ?>&nbsp;<?php endif; ?>
            </td>
            <td>
                <table class="t8-nested">
                    <tr>
                        <td class="tax-label">CGST <?php echo $h($pct_fmt); ?>%</td>
                        <td class="right"><?php echo $fmt_amt($gst_half); ?></td>
                    </tr>
                    <tr>
                        <td class="tax-label">SGST <?php echo $h($pct_fmt); ?>%</td>
                        <td class="right"><?php echo $fmt_amt($gst_half); ?></td>
                    </tr>
                </table>
            </td>
            <td>
                <table class="t8-nested">
                    <tr>
                        <td class="tax-label">CGST <?php echo $h($pct_fmt); ?>%</td>
                        <td class="right">0.00</td>
                    </tr>
                    <tr>
                        <td class="tax-label">SGST <?php echo $h($pct_fmt); ?>%</td>
                        <td class="right">0.00</td>
                    </tr>
                </table>
            </td>
            <td class="amount-label">Amt With HM</td>
            <td class="amount-value"><?php echo $fmt_amt($subtotal_t8); ?></td>
        </tr>
        <tr>
            <td colspan="2" rowspan="2"></td>
            <td class="amount-label">Amt With Tax</td>
            <td class="amount-value"><?php echo $fmt_amt($grand_t8); ?></td>
        </tr>
        <tr>
            <td class="amount-label">URD Amt</td>
            <td class="amount-value"><?php echo $fmt_amt($urd_amt); ?></td>
        </tr>
        <tr>
            <td colspan="3" class="words-row"><?php echo $amount_words_line !== '' ? $h($amount_words_line) : '&nbsp;'; ?></td>
            <td class="amount-label">Round Up</td>
            <td class="amount-value"><?php echo $fmt_amt($round_up); ?></td>
        </tr>
        <tr>
            <td colspan="3" class="words-row">URD Desc :- &nbsp;&nbsp;<?php echo $fmt_amt($urd_amt); ?></td>
            <td class="amount-label">Final Amt</td>
            <td class="amount-value"><?php echo $fmt_amt($grand_t8); ?></td>
        </tr>
        <tr>
            <td colspan="3" class="payment-desc">
                Payment Desc : <?php echo $payment_desc !== '' ? $h($payment_desc) : '&nbsp;'; ?>
            </td>
            <td class="amount-label">Paid Amt</td>
            <td class="amount-value"><?php echo $fmt_amt($paid_t8); ?></td>
        </tr>
        <tr>
            <td colspan="3" class="t8-terms">
                <?php if ($show_terms): ?>
                <span class="t8-terms-label">T&amp;C :</span> <?php echo nl2br($h($terms_display)); ?>
                <?php endif; ?>
                <?php if ($show_thank_you): ?>
                <div class="t8-thank-you"><?php echo $h($thank_you); ?></div>
                <?php endif; ?>
                <?php if (!$show_terms && !$show_thank_you): ?>&nbsp;<?php endif; ?>
            </td>
            <td class="amount-label">Balance Amt</td>
            <td class="amount-value"><?php echo $fmt_amt($balance_t8); ?></td>
        </tr>
    </table>

    <?php if (($print_settings['footer_authorized_signature'] ?? '1') === '1'): ?>
    <table class="t8-signature-table">
        <tr>
            <td style="width:50%;">Customer Signature</td>
            <td style="width:50%;" class="authorised"><?php echo $h($auth_sig); ?></td>
        </tr>
    </table>
    <?php endif; ?>

</div>
</div>

<div class="invoice-btns no-print">
    <a href="javascript:window.print()">Print</a>
    <a href="<?php echo $h($back_url ?? 'sale-invoice.php'); ?>">Back</a>
</div>
