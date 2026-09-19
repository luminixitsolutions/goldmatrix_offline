<?php
/**
 * Template 11 – Heron General Trading UAE Tax Invoice (A4 portrait).
 * Columns: No. | Item Details | Qty. | Gross Wt. | Gr.Sales | VAT % | VAT | Amount
 */
if (!isset($items) || !is_array($items)) {
    $items = [];
}
$print_settings = is_array($print_settings ?? null) ? $print_settings : [];

$h = static function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};
$h_br = static function ($s) use ($h) {
    $s = (string) $s;
    $parts = preg_split('/<br\s*\/?>/i', $s);
    if ($parts === false) {
        return $h($s);
    }
    return implode('<br>', array_map($h, $parts));
};

$fmt_wt = static function ($n) {
    $n = (float) $n;
    if ($n <= 0) {
        return '';
    }
    return number_format($n, 3, '.', ',');
};
$fmt_qty = static function ($n) {
    $n = (float) $n;
    if ($n <= 0) {
        return '';
    }
    if (abs($n - round($n)) < 0.0001) {
        return (string) (int) round($n);
    }
    return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
};
$fmt_amt = static function ($n) {
    return number_format((float) $n, 2, '.', ',');
};
$fmt_vat_pct = static function ($n) {
    $n = (float) $n;
    if ($n <= 0) {
        return '';
    }
    if (abs($n - round($n)) < 0.0001) {
        return (string) (int) round($n);
    }
    return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
};

$amount_curr = trim((string) ($print_settings['t11_amount_currency_label'] ?? 'DHS'));
if ($amount_curr === '') {
    $amount_curr = 'DHS';
}
$brand_sub = trim((string) ($print_settings['t11_brand_sub'] ?? 'JEWELLERY'));
$show_planet = ($print_settings['t11_show_planet_box'] ?? '1') === '1';
$planet_notice = trim((string) ($print_settings['t11_planet_notice'] ?? ''));
if ($planet_notice === '') {
    $planet_notice = "By Using our service, you agree to our T&Cs and Privacy Policy - visit\nwww.planetpayment.ae for full details. Planet has been authorised by FTA.\nBe Sure to present your travel documents, tag, receipt and goods for inspection by Planet BEFORE THE CHECK IN so we can complete your VAT refund process.";
}

$gold_rate_karat = trim((string) ($print_settings['t11_gold_rate_karat'] ?? '21K'));
if (!isset($gold_rates[$gold_rate_karat])) {
    $gold_rate_karat = '21K';
}
$today_gold_rate = (float) ($gold_rates[$gold_rate_karat] ?? 0);

$company_trn_t11 = trim((string) ($print_settings['company_gst'] ?? ''));
if ($company_trn_t11 === '') {
    $company_trn_t11 = trim((string) ($company_trn ?? ''));
}

$doc_date_t11 = $doc_date ?? '';
if (!empty($invoice['invoice_date'])) {
    $doc_date_t11 = date('d/m/Y', strtotime($invoice['invoice_date']));
}
$doc_time_t11 = trim((string) ($doc_time ?? ''));
if ($doc_time_t11 !== '') {
    $doc_time_t11 = str_replace(' ', '', $doc_time_t11);
}

$cust_mobile = '';
if (!empty($invoice['customer_id']) && isset($conn) && $conn) {
    $crow = @getRecord('SELECT * FROM tbl_customers WHERE id = ' . (int) $invoice['customer_id'] . ' LIMIT 1');
    if (is_array($crow)) {
        $cust_mobile = trim((string) ($crow['mobile_no'] ?? $crow['phone_no'] ?? $crow['phone'] ?? $crow['mobile'] ?? ''));
    }
}

$item_lines = [];
$tot_qty = 0.0;
$tot_gross_wt = 0.0;
$tot_gr_sales = 0.0;
$tot_vat = 0.0;
$tot_amount = 0.0;

foreach ($items as $idx => $item) {
    if (!is_array($item)) {
        continue;
    }
    $pname = trim((string) ($item['product_name'] ?? ('Product #' . ($item['product_id'] ?? ''))));
    $design_no = trim((string) ($item['design_no'] ?? $item['barcode'] ?? ''));
    $desc = $design_no !== '' ? ($design_no . ' ' . $pname) : $pname;

    $qty = (float) ($item['metal_qty'] ?? $item['quantity'] ?? 1);
    $gross_wt = (float) ($item['gross_weight'] ?? $item['net_weight'] ?? $item['final_weight'] ?? 0);

    $gr_sales = (float) ($item['net_amount'] ?? $item['amount'] ?? $item['sale_amount'] ?? 0);
    $tax_amt = (float) ($item['tax_amount'] ?? $item['tax'] ?? 0);
    if ($gr_sales <= 0) {
        $gr_sales = (float) ($item['net_amt_with_tax'] ?? 0) - $tax_amt;
        if ($gr_sales < 0) {
            $gr_sales = 0;
        }
    }
    $line_amount = (float) ($item['net_amt_with_tax'] ?? $item['sale_amount_with'] ?? 0);
    if ($line_amount <= 0) {
        $line_amount = $gr_sales + $tax_amt;
    }

    $vat_pct = 0.0;
    if (!empty($item['tax_per']) && (float) $item['tax_per'] > 0) {
        $vat_pct = (float) $item['tax_per'];
    } elseif ($tax_amt > 0 && $gr_sales > 0) {
        $vat_pct = ($tax_amt / $gr_sales) * 100;
    } elseif ($tax_amt > 0) {
        $vat_pct = 5.0;
    }

    $item_lines[] = [
        'sn' => $idx + 1,
        'desc' => $desc,
        'qty' => $qty,
        'gross_wt' => $gross_wt,
        'gr_sales' => $gr_sales,
        'vat_pct' => $vat_pct,
        'vat_amt' => $tax_amt,
        'amount' => $line_amount,
    ];

    $tot_qty += $qty;
    $tot_gross_wt += $gross_wt;
    $tot_gr_sales += $gr_sales;
    $tot_vat += $tax_amt;
    $tot_amount += $line_amount;
}

$taxable_t11 = (float) ($total_before_vat ?? 0);
if ($taxable_t11 <= 0) {
    $taxable_t11 = $tot_gr_sales > 0 ? $tot_gr_sales : ((float) ($subtotal ?? 0) - (float) ($discount_amt ?? 0) + (float) ($additional_amt ?? 0));
}
$vat_t11 = (float) ($tax_amount ?? 0);
if ($vat_t11 <= 0) {
    $vat_t11 = $tot_vat;
}
$net_t11 = (float) ($grand_total ?? 0);
if ($net_t11 <= 0) {
    $net_t11 = $taxable_t11 + $vat_t11;
}

$amount_words_t11 = trim((string) ($amount_words ?? ''));
if ($amount_words_t11 === '' && function_exists('numberToWords')) {
    $amount_words_t11 = numberToWords($net_t11, $amount_curr);
}

$t11_payment_lines = [];
$t11_payments_src = is_array($payments ?? null) ? $payments : [];
foreach ($t11_payments_src as $pay_row) {
    if (!is_array($pay_row)) {
        continue;
    }
    if (function_exists('auragold_payment_merge_stored_details')) {
        $pay_row = auragold_payment_merge_stored_details($pay_row);
    } elseif (!empty($pay_row['payment_details']) && is_string($pay_row['payment_details'])) {
        $pay_json = json_decode($pay_row['payment_details'], true);
        if (is_array($pay_json)) {
            $pay_row = array_merge($pay_row, $pay_json);
        }
    }
    $pay_amt = (float) ($pay_row['amount'] ?? 0);
    if ($pay_amt <= 0.0001) {
        continue;
    }
    $pay_type = strtolower(trim((string) ($pay_row['payment_type'] ?? $pay_row['type'] ?? 'cash')));
    if (strpos($pay_type, 'cash') !== false) {
        $pay_label = 'Cash';
    } elseif (strpos($pay_type, 'card') !== false) {
        $pay_label = 'Card';
    } elseif (strpos($pay_type, 'bank') !== false || strpos($pay_type, 'transfer') !== false) {
        $pay_label = 'Bank Transfer';
    } elseif (strpos($pay_type, 'cheque') !== false || strpos($pay_type, 'check') !== false) {
        $pay_label = 'Cheque';
    } elseif (strpos($pay_type, 'upi') !== false) {
        $pay_label = 'UPI';
    } else {
        $pay_label = ucfirst($pay_type !== '' ? $pay_type : 'Payment');
    }
    $t11_payment_lines[] = ['label' => $pay_label, 'amount' => $pay_amt];
}
if (empty($t11_payment_lines) && (float) ($paid_amt ?? 0) > 0.0001) {
    $t11_payment_lines[] = ['label' => 'Cash', 'amount' => (float) $paid_amt];
}

$company_display = trim((string) ($company_name ?? ''));
$company_plain = trim(preg_replace('/<br\s*\/?>/i', ' ', $company_display));
$company_plain = preg_replace('/\s+/', ' ', $company_plain);
$footer_address = trim((string) ($company_address ?? ''));
$footer_phone = trim((string) ($company_phone ?? ''));
$footer_landline = trim((string) ($company_landline ?? ''));
$phone_display = $footer_phone;
if ($footer_landline !== '' && $footer_phone !== '' && $footer_landline !== $footer_phone) {
    $phone_display = $footer_phone . ' / ' . $footer_landline;
} elseif ($footer_landline !== '' && $footer_phone === '') {
    $phone_display = $footer_landline;
}

$brand_main = $company_plain !== '' ? strtoupper($company_plain) : 'COMPANY';
$brand_words = preg_split('/\s+/', $brand_main, 2);
$brand_line1 = $brand_words[0] ?? 'COMPANY';

$doc_title_t11 = trim((string) ($print_settings['invoice_title'] ?? ''));
if ($doc_title_t11 === '') {
    $doc_title_t11 = 'TAX INVOICE';
}

$planet_lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $planet_notice))));
$items_min_height = max(180, min(330, 70 + (count($item_lines) * 32)));
?>
<style>
.invoice.inv-heron {
    font-family: Arial, Helvetica, sans-serif !important;
    font-size: 13px !important;
    color: #111 !important;
    background: #fff !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    max-width: 210mm !important;
    margin: 0 auto !important;
    padding: 0 !important;
    overflow: visible !important;
}
.invoice.inv-heron .t11-page {
    width: 210mm;
    min-height: 297mm;
    margin: 0 auto;
    padding: 12mm;
    background: #fff;
    box-sizing: border-box;
}
.invoice.inv-heron .header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}
.invoice.inv-heron .logo-box {
    width: 140px;
    text-align: center;
    flex-shrink: 0;
}
.invoice.inv-heron .logo {
    width: 105px;
    height: 105px;
    border-radius: 50%;
    background: #222;
    color: #fff;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    margin: auto;
    font-weight: bold;
    letter-spacing: 2px;
    overflow: hidden;
}
.invoice.inv-heron .logo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
}
.invoice.inv-heron .logo .brand {
    font-size: 15px;
    margin-top: 5px;
    line-height: 1.1;
}
.invoice.inv-heron .logo .small {
    font-size: 7px;
    letter-spacing: 1px;
}
.invoice.inv-heron .company-info {
    flex: 1;
    text-align: right;
    padding-left: 10px;
}
.invoice.inv-heron .company-info h1 {
    margin: 0;
    font-size: 27px;
    letter-spacing: 1px;
    line-height: 1.15;
}
.invoice.inv-heron .company-info p {
    margin: 5px 0;
    font-size: 13px;
    font-weight: 600;
}
.invoice.inv-heron .company-info .tax-title {
    font-size: 16px;
    font-weight: 700;
    margin-top: 10px;
}
.invoice.inv-heron .company-info .trn {
    font-size: 15px;
    font-weight: 700;
    margin-top: 18px;
}
.invoice.inv-heron .info-row {
    display: grid;
    grid-template-columns: 1.35fr 1fr;
    gap: 80px;
    margin-top: 5px;
}
.invoice.inv-heron .box {
    border: 2px solid #444;
}
.invoice.inv-heron .customer-name {
    padding: 9px 6px;
    font-weight: 700;
    font-size: 14px;
    border-bottom: 1px solid #555;
}
.invoice.inv-heron .customer-details {
    padding: 8px 6px;
    line-height: 24px;
}
.invoice.inv-heron .customer-details .label {
    display: inline-block;
    width: 85px;
    font-weight: 600;
}
.invoice.inv-heron .invoice-details {
    padding: 10px;
}
.invoice.inv-heron .invoice-details .detail-row {
    display: grid;
    grid-template-columns: 110px 1fr;
    line-height: 28px;
}
.invoice.inv-heron .invoice-details .detail-label {
    font-weight: 700;
}
.invoice.inv-heron .invoice-details .detail-value {
    text-align: right;
    font-weight: 600;
}
.invoice.inv-heron .planet-box {
    border: 2px solid #444;
    margin-top: 12px;
    display: grid;
    grid-template-columns: 110px 1fr;
    min-height: 105px;
    align-items: center;
}
.invoice.inv-heron .planet-logo {
    text-align: center;
    font-size: 48px;
    font-weight: bold;
}
.invoice.inv-heron .planet-circle {
    width: 68px;
    height: 68px;
    background: #333;
    color: #fff;
    border-radius: 50%;
    margin: auto;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 35px;
    font-weight: bold;
}
.invoice.inv-heron .planet-text {
    font-weight: 600;
    font-size: 12.5px;
    line-height: 19px;
    padding: 8px 12px;
    text-align: center;
}
.invoice.inv-heron .items-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 13px;
    table-layout: fixed;
}
.invoice.inv-heron .items-table th,
.invoice.inv-heron .items-table td {
    border-left: 1px solid #555;
    border-right: 1px solid #555;
}
.invoice.inv-heron .items-table th {
    border-top: 2px solid #444;
    border-bottom: 1px solid #555;
    padding: 8px 5px;
    font-size: 13px;
    text-align: center;
    background: #f3f3f3 !important;
    color: #111 !important;
    text-transform: none !important;
    letter-spacing: normal !important;
    font-weight: 700;
}
.invoice.inv-heron .items-table tbody td {
    padding: 14px 7px;
    vertical-align: top;
    min-height: 180px;
    font-weight: 600;
    font-size: 12px;
}
.invoice.inv-heron .items-table .number { width: 7%; text-align: center; }
.invoice.inv-heron .items-table .item { width: 28%; }
.invoice.inv-heron .items-table .qty { width: 7%; text-align: center; }
.invoice.inv-heron .items-table .weight { width: 12%; text-align: right; }
.invoice.inv-heron .items-table .sales { width: 14%; text-align: right; }
.invoice.inv-heron .items-table .vat-percent { width: 8%; text-align: center; }
.invoice.inv-heron .items-table .vat { width: 12%; text-align: right; }
.invoice.inv-heron .items-table .amount { width: 14%; text-align: right; }
.invoice.inv-heron .item-line {
    margin-bottom: 32px;
    display: block;
}
.invoice.inv-heron .item-line:last-child {
    margin-bottom: 0;
}
.invoice.inv-heron .items-table tfoot td {
    border-top: 2px solid #444;
    border-bottom: 2px solid #444;
    padding: 9px 6px;
    font-size: 13px;
    font-weight: 700;
    background: #f2f2f2 !important;
    color: #111 !important;
}
.invoice.inv-heron .bottom-section {
    display: grid;
    grid-template-columns: 1.45fr 1fr;
    border-left: 1px solid #555;
    border-right: 1px solid #555;
    border-bottom: 1px solid #555;
}
.invoice.inv-heron .amount-words {
    padding: 15px 12px;
    font-size: 14px;
    font-weight: 700;
    line-height: 22px;
    min-height: 165px;
}
.invoice.inv-heron .totals {
    border-left: 1px solid #555;
}
.invoice.inv-heron .total-row {
    display: grid;
    grid-template-columns: 1fr 125px;
    padding: 5px 10px;
    font-size: 14px;
    font-weight: 700;
}
.invoice.inv-heron .total-row .value {
    text-align: right;
}
.invoice.inv-heron .payment-title {
    text-align: center;
    font-size: 16px;
    font-style: italic;
    text-decoration: underline;
    margin: 6px 0 10px;
}
.invoice.inv-heron .payment-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    padding: 8px 10px 12px;
    font-weight: 700;
}
.invoice.inv-heron .payment-row .value {
    text-align: right;
}
.invoice.inv-heron .signature-section {
    display: flex;
    justify-content: space-between;
    margin-top: 25px;
    padding: 0 10px;
}
.invoice.inv-heron .signature {
    width: 230px;
    text-align: center;
    font-weight: 700;
}
.invoice.inv-heron .signature-line {
    border-top: 2px solid #333;
    padding-top: 6px;
    margin-top: 35px;
    font-size: 15px;
}
.invoice.inv-heron .invoice-by-name {
    text-align: center;
    margin-bottom: 7px;
    font-size: 15px;
    font-weight: 500;
}
@media print {
    body { background: #fff !important; padding: 0 !important; margin: 0 !important; }
    .invoice.inv-heron { max-width: 210mm !important; width: 210mm !important; }
    .invoice.inv-heron .t11-page {
        margin: 0;
        width: 210mm;
        min-height: 0;
        padding: 10mm;
        page-break-after: avoid;
        page-break-inside: avoid;
    }
    @page { size: A4; margin: 0; }
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
}
</style>

<div class="invoice inv-heron template_11">
<div class="t11-page">

    <div class="header">
        <div class="logo-box">
            <div class="logo">
                <?php if (($print_settings['header_company_logo'] ?? '1') === '1' && !empty($has_logo)): ?>
                <img src="<?php echo $h($company_logo); ?>" alt="<?php echo $h($company_display); ?>">
                <?php else: ?>
                <div class="brand"><?php echo $h($brand_line1); ?></div>
                <?php if ($brand_sub !== ''): ?><div class="small"><?php echo $h($brand_sub); ?></div><?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="company-info">
            <?php if (($print_settings['header_company_name'] ?? '1') === '1'): ?>
            <h1><?php echo $company_plain !== '' ? $h($company_plain) : 'COMPANY NAME'; ?></h1>
            <?php endif; ?>
            <?php if ($footer_address !== ''): ?>
            <p><?php echo $h_br($footer_address); ?></p>
            <?php endif; ?>
            <?php if (($print_settings['header_phone'] ?? '1') === '1' && $phone_display !== ''): ?>
            <p><?php echo $h($phone_display); ?></p>
            <?php endif; ?>
            <?php if (($print_settings['header_invoice_title'] ?? '1') === '1'): ?>
            <div class="tax-title"><?php echo $h($doc_title_t11); ?></div>
            <?php endif; ?>
            <?php if (($print_settings['header_gst_number'] ?? '1') === '1' && $company_trn_t11 !== ''): ?>
            <div class="trn"><?php echo $h($company_trn_t11); ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="info-row">
        <div>
            <div style="font-weight:700;font-size:16px;margin-bottom:3px;"><?php echo $h($doc_title_t11); ?></div>
            <div class="box">
                <div class="customer-name"><?php echo $h($party_name ?? ''); ?></div>
                <div class="customer-details">
                    <?php if ($cust_mobile !== ''): ?>
                    <div><span class="label">Contact</span><?php echo $h($cust_mobile); ?></div>
                    <?php endif; ?>
                    <?php if ($today_gold_rate > 0): ?>
                    <div><span class="label">Gold Rate:</span><?php echo $h($fmt_amt($today_gold_rate)); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="box">
            <div class="invoice-details">
                <div class="detail-row">
                    <span class="detail-label">Invoice No.</span>
                    <span class="detail-value"><?php echo $h($doc_no ?? ''); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Date</span>
                    <span class="detail-value"><?php echo $h($doc_date_t11); ?></span>
                </div>
                <?php if ($doc_time_t11 !== ''): ?>
                <div class="detail-row">
                    <span class="detail-label">Time</span>
                    <span class="detail-value"><?php echo $h($doc_time_t11); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($show_planet): ?>
    <div class="planet-box">
        <div class="planet-logo"><div class="planet-circle">P</div></div>
        <div class="planet-text">
            <?php
            $t11_planet_line = static function ($pline) use ($h) {
                $markers = ['www.planetpayment.ae', 'BEFORE THE CHECK IN'];
                foreach ($markers as $marker) {
                    if (stripos($pline, $marker) !== false) {
                        $parts = preg_split('/(' . preg_quote($marker, '/') . ')/i', $pline, -1, PREG_SPLIT_DELIM_CAPTURE);
                        if (is_array($parts)) {
                            $out = '';
                            foreach ($parts as $part) {
                                if (strcasecmp($part, $marker) === 0) {
                                    $out .= '<strong>' . $h($part) . '</strong>';
                                } else {
                                    $out .= $h($part);
                                }
                            }
                            return $out;
                        }
                    }
                }
                return $h($pline);
            };
            foreach ($planet_lines as $pi => $pline):
            ?>
            <?php if ($pi > 0): ?><br><?php endif; ?><?php echo $t11_planet_line($pline); ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <table class="items-table">
        <thead>
            <tr>
                <th class="number">No.</th>
                <th class="item">Item Details</th>
                <th class="qty">Qty.</th>
                <th class="weight">Gross Wt.</th>
                <th class="sales">Gr.Sales</th>
                <th class="vat-percent">VAT %</th>
                <th class="vat">VAT (<?php echo $h($amount_curr); ?>)</th>
                <th class="amount">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr style="min-height:<?php echo (int) $items_min_height; ?>px;">
                <td class="number">
                    <?php if (empty($item_lines)): ?>
                    <span class="item-line">&nbsp;</span>
                    <?php else: foreach ($item_lines as $ln): ?>
                    <span class="item-line"><?php echo $h($ln['sn']); ?></span>
                    <?php endforeach; endif; ?>
                </td>
                <td class="item">
                    <?php if (empty($item_lines)): ?>
                    <span class="item-line">&nbsp;</span>
                    <?php else: foreach ($item_lines as $ln): ?>
                    <span class="item-line"><?php echo $h($ln['desc']); ?></span>
                    <?php endforeach; endif; ?>
                </td>
                <td class="qty">
                    <?php if (empty($item_lines)): ?>
                    <span class="item-line">&nbsp;</span>
                    <?php else: foreach ($item_lines as $ln): ?>
                    <span class="item-line"><?php echo $h($fmt_qty($ln['qty'])); ?></span>
                    <?php endforeach; endif; ?>
                </td>
                <td class="weight">
                    <?php if (empty($item_lines)): ?>
                    <span class="item-line">&nbsp;</span>
                    <?php else: foreach ($item_lines as $ln): ?>
                    <span class="item-line"><?php echo $h($fmt_wt($ln['gross_wt'])); ?></span>
                    <?php endforeach; endif; ?>
                </td>
                <td class="sales">
                    <?php if (empty($item_lines)): ?>
                    <span class="item-line">&nbsp;</span>
                    <?php else: foreach ($item_lines as $ln): ?>
                    <span class="item-line"><?php echo $ln['gr_sales'] > 0 ? $h($fmt_amt($ln['gr_sales'])) : ''; ?></span>
                    <?php endforeach; endif; ?>
                </td>
                <td class="vat-percent">
                    <?php if (empty($item_lines)): ?>
                    <span class="item-line">&nbsp;</span>
                    <?php else: foreach ($item_lines as $ln): ?>
                    <span class="item-line"><?php echo $ln['vat_pct'] > 0 ? $h($fmt_vat_pct($ln['vat_pct'])) : ''; ?></span>
                    <?php endforeach; endif; ?>
                </td>
                <td class="vat">
                    <?php if (empty($item_lines)): ?>
                    <span class="item-line">&nbsp;</span>
                    <?php else: foreach ($item_lines as $ln): ?>
                    <span class="item-line"><?php echo $ln['vat_amt'] > 0 ? $h($fmt_amt($ln['vat_amt'])) : ''; ?></span>
                    <?php endforeach; endif; ?>
                </td>
                <td class="amount">
                    <?php if (empty($item_lines)): ?>
                    <span class="item-line">&nbsp;</span>
                    <?php else: foreach ($item_lines as $ln): ?>
                    <span class="item-line"><?php echo $ln['amount'] > 0 ? $h($fmt_amt($ln['amount'])) : ''; ?></span>
                    <?php endforeach; endif; ?>
                </td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <td></td>
                <td></td>
                <td style="text-align:center;"><?php echo $tot_qty > 0 ? $h($fmt_qty($tot_qty)) : ''; ?></td>
                <td style="text-align:right;"><?php echo $tot_gross_wt > 0 ? $h($fmt_wt($tot_gross_wt)) : ''; ?></td>
                <td style="text-align:right;"><?php echo $taxable_t11 > 0 ? $h($fmt_amt($taxable_t11)) : ''; ?></td>
                <td></td>
                <td style="text-align:right;"><?php echo $vat_t11 > 0 ? $h($fmt_amt($vat_t11)) : ''; ?></td>
                <td style="text-align:right;"><?php echo $net_t11 > 0 ? $h($fmt_amt($net_t11)) : ''; ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="bottom-section">
        <div class="amount-words">
            <?php echo $h($amount_curr); ?> &nbsp; <?php echo $amount_words_t11 !== '' ? $h($amount_words_t11) : ''; ?>
        </div>
        <div class="totals">
            <div class="total-row">
                <span>Taxable Amount</span>
                <span class="value"><?php echo $h($fmt_amt($taxable_t11)); ?></span>
            </div>
            <div class="total-row">
                <span>VAT Amount</span>
                <span class="value"><?php echo $h($fmt_amt($vat_t11)); ?></span>
            </div>
            <div class="total-row">
                <span>Net Amount</span>
                <span class="value"><?php echo $h($fmt_amt($net_t11)); ?></span>
            </div>
            <?php if (!empty($t11_payment_lines)): ?>
            <div class="payment-title">Mode of Payment :</div>
            <?php foreach ($t11_payment_lines as $pay_ln): ?>
            <div class="payment-row">
                <span><?php echo $h($pay_ln['label']); ?></span>
                <span class="value"><?php echo $h($fmt_amt($pay_ln['amount'])); ?></span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if (($print_settings['footer_authorized_signature'] ?? '1') === '1'): ?>
    <div class="signature-section">
        <div class="signature">
            <div class="signature-line">CUSTOMER SIGNATURE</div>
        </div>
        <div class="signature">
            <?php if (trim((string) ($person_value ?? '')) !== ''): ?>
            <div class="invoice-by-name"><?php echo $h($person_value); ?></div>
            <?php endif; ?>
            <div class="signature-line">INVOICED BY</div>
        </div>
    </div>
    <?php endif; ?>

</div>
</div>

<div class="invoice-btns no-print">
    <a href="javascript:window.print()">Print</a>
    <a href="<?php echo $h($back_url ?? 'sale-invoice.php'); ?>">Back</a>
</div>
