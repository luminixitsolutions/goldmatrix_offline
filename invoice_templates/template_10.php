<?php
/**
 * Template 10 – Monaco Jewellery Sales Invoice (A4 landscape, bilingual EN/AR, gold theme).
 */
require_once __DIR__ . '/../includes/auragold_carat_arabic_name_schema.php';
if (isset($conn) && $conn) {
    auragold_ensure_tbl_carat_arabic_name($conn);
}
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
    return rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.');
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
    return number_format((float) $n, 2, '.', '');
};
$fmt_disc_pct = static function ($n) {
    $n = (float) $n;
    if ($n <= 0) {
        return '';
    }
    return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
};
/** Gold karat label (18K, 21K, …) — not diamond carat weight. */
$fmt_gold_karat = static function ($item) {
    if (!is_array($item)) {
        return '';
    }
    $conn_ref = (isset($GLOBALS['conn']) && $GLOBALS['conn']) ? $GLOBALS['conn'] : null;
    if (!$conn_ref && isset($conn) && $conn) {
        $conn_ref = $conn;
    }
    $carat_raw = trim((string) ($item['carat'] ?? ''));
    $candidates = [
        $item['product_carat'] ?? '',
        $item['karat'] ?? '',
    ];
    if ($carat_raw !== '' && !is_numeric($carat_raw)) {
        $candidates[] = $carat_raw;
    } elseif ($carat_raw !== '' && is_numeric($carat_raw)) {
        $cn = (float) $carat_raw;
        if ($cn >= 8 && $cn <= 24 && abs($cn - round($cn)) < 0.001) {
            $candidates[] = $carat_raw;
        }
    }
    $candidates[] = $item['purity'] ?? '';
    $candidates[] = $item['opening_purity'] ?? '';
    $candidates[] = $item['requested_purity'] ?? '';
    foreach ($candidates as $raw) {
        $raw = trim((string) $raw);
        if ($raw === '' || $raw === '0' || $raw === '0.0' || $raw === '0.00') {
            continue;
        }
        $label = '';
        if (function_exists('auragold_barcode_format_carat_label')) {
            $label = auragold_barcode_format_carat_label($raw, $conn_ref);
        }
        if ($label === '' && !is_numeric($raw)) {
            $label = $raw;
        }
        if ($label === '' && is_numeric($raw)) {
            $n = (float) $raw;
            $whole = (abs($n - round($n)) < 0.001) ? (int) round($n) : null;
            if ($whole !== null && $whole >= 8 && $whole <= 24) {
                $label = $whole . 'K';
            }
        }
        if ($label !== '') {
            if (function_exists('auragold_carat_karat_label_with_arabic')) {
                return auragold_carat_karat_label_with_arabic($raw, $conn_ref, $label);
            }
            if (preg_match('/^(\d+)\s*K?$/i', trim($label), $m)) {
                return $m[1] . 'K';
            }
            return $label;
        }
    }
    return '';
};

$amount_curr = trim((string) ($print_settings['t10_amount_currency_label'] ?? 'QAR'));
if ($amount_curr === '') {
    $amount_curr = 'QAR';
}

$address_ar = trim((string) ($print_settings['t10_address_ar'] ?? ''));
$cr_no = trim((string) ($print_settings['t10_cr_no'] ?? ''));
$website = trim((string) ($print_settings['t10_website'] ?? ''));
$instagram = trim((string) ($print_settings['t10_instagram'] ?? ''));

$branch_t10_bid = (int) ($print_settings_branch_id ?? 0);
if ($branch_t10_bid <= 0 && function_exists('auragold_effective_branch_id')) {
    $branch_t10_bid = (int) auragold_effective_branch_id();
}
$branch_t10 = [];
if ($branch_t10_bid > 0 && function_exists('getRecordMaster')) {
    if (function_exists('auragold_ensure_tbl_branches_profile_columns') && !empty($GLOBALS['conn_master'])) {
        auragold_ensure_tbl_branches_profile_columns($GLOBALS['conn_master']);
    }
    $branch_row = getRecordMaster(
        'SELECT website, cr_no, instagram, gst_no, business_license_no FROM tbl_branches WHERE id = '
        . (int) $branch_t10_bid . ' LIMIT 1'
    );
    if (is_array($branch_row)) {
        $branch_t10 = $branch_row;
    }
}
if ($website === '') {
    $website = trim((string) ($branch_t10['website'] ?? ''));
}
if ($instagram === '') {
    $instagram = trim((string) ($branch_t10['instagram'] ?? ''));
}
if ($cr_no === '') {
    $cr_no = trim((string) ($branch_t10['cr_no'] ?? ''));
}
if ($cr_no === '') {
    $cr_no = trim((string) ($branch_t10['business_license_no'] ?? ''));
}
if ($cr_no === '') {
    $cr_no = trim((string) ($print_settings['company_gst'] ?? ($company_trn ?? '')));
}
$brand_sub = trim((string) ($print_settings['t10_brand_sub'] ?? 'jewelry'));
$min_item_rows = max(1, (int) ($print_settings['t10_min_item_rows'] ?? 5));

$gold_rate_karat = trim((string) ($print_settings['t10_gold_rate_karat'] ?? '24K'));
if (!isset($gold_rates[$gold_rate_karat])) {
    $gold_rate_karat = '24K';
}
$today_gold_rate = (float) ($gold_rates[$gold_rate_karat] ?? 0);

$terms_en = trim((string) ($print_settings['terms_conditions'] ?? ''));
if ($terms_en === '') {
    $terms_en = "Items may be exchanged or returned within 3 days of purchase.\nCustomized, resized, or discounted items are not eligible for exchange or return.\nDefective items must be reported within 48–72 hours of purchase.\nA valid receipt or invoice is required for all exchanges, returns, and defect claims.";
}
$terms_ar = trim((string) ($print_settings['t10_terms_arabic'] ?? ''));
if ($terms_ar === '') {
    $terms_ar = "يمكن استبدال أو إرجاع المنتجات خلال 3 أيام من تاريخ الشراء.\nالمنتجات المعدلة أو التي تم تغيير مقاسها أو المباعة بخصم لا يمكن استبدالها أو إرجاعها.\nيجب الإبلاغ عن أي عيوب في المنتج خلال 48 إلى 72 ساعة من تاريخ الشراء.\nيجب تقديم الفاتورة أو الإيصال الأصلي لجميع طلبات الاستبدال أو الإرجاع أو العيوب.";
}
$show_terms = ($print_settings['footer_terms_conditions'] ?? '1') === '1';

$doc_date_t10 = $doc_date ?? '';
if (!empty($invoice['invoice_date'])) {
    $doc_date_t10 = date('d/m/Y', strtotime($invoice['invoice_date']));
}

$cust_mobile = '';
$cust_dob = '';
if (!empty($invoice['customer_id']) && isset($conn) && $conn) {
    $crow = @getRecord('SELECT * FROM tbl_customers WHERE id = ' . (int) $invoice['customer_id'] . ' LIMIT 1');
    if (is_array($crow)) {
        $cust_mobile = trim((string) ($crow['mobile_no'] ?? $crow['phone_no'] ?? $crow['phone'] ?? $crow['mobile'] ?? ''));
        $dob_raw = trim((string) ($crow['date_of_birth'] ?? $crow['dob'] ?? $crow['birth_date'] ?? ''));
        if ($dob_raw !== '' && $dob_raw !== '0000-00-00') {
            $dob_ts = strtotime($dob_raw);
            if ($dob_ts) {
                $cust_dob = date('d/m/Y', $dob_ts);
            }
        }
    }
}

$item_lines = [];
foreach ($items as $idx => $item) {
    if (!is_array($item)) {
        continue;
    }
    $pname = trim((string) ($item['product_name'] ?? ('Product #' . ($item['product_id'] ?? ''))));
    $pname_ar = trim((string) ($item['product_alternate_name'] ?? $item['alternate_name'] ?? ''));
    $design_no = trim((string) ($item['design_no'] ?? $item['barcode'] ?? ''));
    $desc = $design_no !== '' ? ($design_no . ' / ' . $pname) : $pname;
    if ($pname_ar !== '') {
        $desc .= ' / ' . $pname_ar;
    }

    $gold_wt = (float) ($item['net_weight'] ?? $item['final_weight'] ?? $item['gross_weight'] ?? 0);
    $diamond_wt = (float) ($item['diamond_carat'] ?? $item['stone_weight'] ?? 0);
    $qty = (float) ($item['metal_qty'] ?? $item['quantity'] ?? 1);

    $line_amt = (float) ($item['net_amount'] ?? $item['amount'] ?? 0);
    $tax_amt = (float) ($item['tax_amount'] ?? $item['tax'] ?? 0);
    if ($line_amt <= 0) {
        $line_amt = (float) ($item['net_amt_with_tax'] ?? 0) - $tax_amt;
        if ($line_amt < 0) {
            $line_amt = 0;
        }
    }
    $final_with_tax = (float) ($item['net_amt_with_tax'] ?? 0);
    if ($final_with_tax <= 0 && $line_amt > 0) {
        $final_with_tax = $line_amt + $tax_amt;
    }
    if ($final_with_tax <= 0) {
        $final_with_tax = $line_amt;
    }

    $disc_amt = (float) ($item['discount'] ?? 0);
    $disc_pct = 0.0;
    if ($disc_amt > 0 && $line_amt > 0) {
        $disc_pct = ($disc_amt / ($line_amt + $disc_amt)) * 100;
    }

    $tax_factor = ($line_amt > 0 && $tax_amt > 0) ? (1 + ($tax_amt / $line_amt)) : 1.0;
    $value_with_tax = $final_with_tax;
    if ($disc_amt > 0) {
        $value_with_tax = ($line_amt + $disc_amt) * $tax_factor;
    }
    $disc_amt_with_tax = $disc_amt > 0 ? ($disc_amt * $tax_factor) : 0.0;

    $item_lines[] = [
        'sn' => $idx + 1,
        'desc' => $desc,
        'karat' => $fmt_gold_karat($item),
        'gold_wt' => $gold_wt,
        'diamond_wt' => $diamond_wt,
        'qty' => $qty,
        'value' => $value_with_tax,
        'disc_pct' => $disc_pct,
        'final' => $final_with_tax,
    ];
}

$grand_t10 = (float) ($grand_total ?? 0);
$paid_t10 = (float) ($paid_amt ?? 0);
$balance_t10 = (float) ($balance_amt ?? 0);

$pay_cash = (float) ($payment_totals['cash'] ?? 0) > 0.0001;
$pay_bank = ((float) ($payment_totals['bank'] ?? 0) + (float) ($payment_totals['cheque'] ?? 0) + (float) ($payment_totals['upi'] ?? 0)) > 0.0001;
$pay_card = (float) ($payment_totals['card'] ?? 0) > 0.0001;
$pay_other = ((float) ($payment_totals['metal_exchange'] ?? 0) + (float) ($payment_totals['scrap'] ?? 0)) > 0.0001;

$company_display = trim((string) ($company_name ?? ''));
$company_plain = trim(preg_replace('/<br\s*\/?>/i', ' ', $company_display));
$company_plain = preg_replace('/\s+/', ' ', $company_plain);
$footer_address = trim((string) ($company_address ?? ''));
$footer_phone = trim((string) ($company_phone ?? ''));
$footer_email = trim((string) ($company_email ?? ''));

$brand_main = $company_plain !== '' ? strtoupper($company_plain) : 'MONACO';
$brand_words = preg_split('/\s+/', $brand_main, 2);
$brand_line1 = $brand_words[0] ?? 'MONACO';

$doc_title_t10 = trim((string) ($print_settings['invoice_title'] ?? ''));
if ($doc_title_t10 === '') {
    $doc_title_t10 = 'SALES INVOICE';
}

$terms_en_lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n|<br\s*\/?>/i', $terms_en))));
$terms_ar_lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n|<br\s*\/?>/i', $terms_ar))));
?>
<style>
.invoice.inv-monaco {
    --gold: #b87514;
    --gold-soft: #d8b06f;
    --ink: #111;
    --paper: #fffefa;
    font-family: Arial, Helvetica, sans-serif !important;
    font-size: 12px !important;
    color: var(--ink) !important;
    background: transparent !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    max-width: 297mm !important;
    margin: 0 auto !important;
    padding: 0 !important;
    overflow: visible !important;
}
.invoice.inv-monaco .t10-page {
    width: 297mm;
    min-height: 210mm;
    margin: 0 auto;
    background: var(--paper);
    position: relative;
    padding: 12mm 10mm 11mm;
    box-sizing: border-box;
    overflow: visible;
}
.invoice.inv-monaco .t10-page:before,
.invoice.inv-monaco .t10-page:after {
    content: "";
    position: absolute;
    inset: 3.5mm;
    border: 1.6px solid var(--gold);
    pointer-events: none;
}
.invoice.inv-monaco .t10-page:after { inset: 5.2mm; border-width: .7px; }
.invoice.inv-monaco .corner {
    position: absolute; width: 12mm; height: 12mm; border-color: var(--gold); z-index: 2;
}
.invoice.inv-monaco .corner.tl { left: 3.5mm; top: 3.5mm; border-left: 2px solid var(--gold); border-top: 2px solid var(--gold); border-radius: 0 0 10mm 0; }
.invoice.inv-monaco .corner.tr { right: 3.5mm; top: 3.5mm; border-right: 2px solid var(--gold); border-top: 2px solid var(--gold); border-radius: 0 0 0 10mm; }
.invoice.inv-monaco .corner.bl { left: 3.5mm; bottom: 3.5mm; border-left: 2px solid var(--gold); border-bottom: 2px solid var(--gold); border-radius: 0 10mm 0 0; }
.invoice.inv-monaco .corner.br { right: 3.5mm; bottom: 3.5mm; border-right: 2px solid var(--gold); border-bottom: 2px solid var(--gold); border-radius: 10mm 0 0 0; }
.invoice.inv-monaco .spark { position: absolute; color: var(--gold); font-size: 18px; line-height: 1; z-index: 2; }
.invoice.inv-monaco .s1 { left: 2.2mm; top: 2.2mm; }
.invoice.inv-monaco .s2 { right: 2.2mm; top: 2.2mm; }
.invoice.inv-monaco .s3 { left: 2.2mm; bottom: 2.2mm; }
.invoice.inv-monaco .s4 { right: 2.2mm; bottom: 2.2mm; }
.invoice.inv-monaco .header {
    display: grid;
    grid-template-columns: 1.05fr .9fr 1.15fr;
    gap: 8mm;
    align-items: start;
    margin: 2mm 4mm 3mm;
}
.invoice.inv-monaco .contact { font-size: 11px; line-height: 1.7; padding-top: 2mm; }
.invoice.inv-monaco .contact-row { display: flex; gap: 8px; align-items: baseline; flex-wrap: wrap; }
.invoice.inv-monaco .contact .ar { direction: rtl; font-family: Tahoma, Arial, sans-serif; }
.invoice.inv-monaco .logo { text-align: center; padding-top: 5mm; }
.invoice.inv-monaco .logo img { width: 60mm; height: 25mm; object-fit: contain; }
.invoice.inv-monaco .logo .brand {
    color: var(--gold);
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 30px;
    letter-spacing: 1px;
    line-height: .9;
}
.invoice.inv-monaco .logo .brand .a { display: inline-block; transform: scaleX(.86); }
.invoice.inv-monaco .logo .sub {
    font-family: 'Brush Script MT', 'Segoe Script', cursive;
    font-size: 34px;
    margin-top: 2mm;
    transform: rotate(-3deg);
}
.invoice.inv-monaco .invoice-title {
    font-family: Georgia, 'Times New Roman', serif;
    color: var(--gold);
    font-size: 20px;
    text-align: center;
    font-weight: 700;
    margin-bottom: 3mm;
}
.invoice.inv-monaco .mini-table { width: 100%; border-collapse: separate; border-spacing: 0; font-family: Georgia, 'Times New Roman', serif; font-size: 10px; }
.invoice.inv-monaco .mini-table td { border: 1px solid var(--gold-soft); padding: 3mm 3mm; height: 9mm; text-align: center; vertical-align: middle; }
.invoice.inv-monaco .mini-table tr:first-child td:first-child { border-radius: 2mm 0 0 0; }
.invoice.inv-monaco .mini-table tr:first-child td:last-child { border-radius: 0 2mm 0 0; }
.invoice.inv-monaco .mini-table tr:last-child td:first-child { border-radius: 0 0 0 2mm; }
.invoice.inv-monaco .mini-table tr:last-child td:last-child { border-radius: 0 0 2mm 0; }
.invoice.inv-monaco .gold-rate { margin-top: 3mm; border: 1px solid var(--gold-soft); border-radius: 2mm; display: grid; grid-template-columns: 1.55fr 1fr; font-family: Georgia, 'Times New Roman', serif; font-size: 10px; }
.invoice.inv-monaco .gold-rate > div { padding: 3mm; text-align: center; }
.invoice.inv-monaco .gold-rate > div + div { border-left: 1px solid var(--gold-soft); }
.invoice.inv-monaco .divider { display: flex; align-items: center; gap: 3mm; margin: 0 4mm 3mm; width: 33%; }
.invoice.inv-monaco .divider .line { height: 1px; background: var(--gold-soft); flex: 1; }
.invoice.inv-monaco .divider .diamond { color: var(--gold); font-size: 16px; }
.invoice.inv-monaco .upper-panels { display: grid; grid-template-columns: 1fr 1fr; gap: 6mm; margin: 0 4mm 3mm; }
.invoice.inv-monaco .panel { border: 1px solid var(--gold-soft); border-radius: 2mm; padding: 3.2mm 4mm; min-height: 39mm; }
.invoice.inv-monaco .customer-grid { display: grid; grid-template-columns: 45mm 4mm 1fr; row-gap: 2.3mm; font-size: 10.5px; align-items: end; }
.invoice.inv-monaco .customer-grid .line-field { border-bottom: 1px solid #777; height: 5mm; line-height: 5mm; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
.invoice.inv-monaco .payment-title { font-family: Georgia, 'Times New Roman', serif; font-size: 13px; font-weight: 700; margin-bottom: 2mm; }
.invoice.inv-monaco .payment-wrap { display: grid; grid-template-columns: 1.05fr .95fr; gap: 4mm; font-size: 10.5px; }
.invoice.inv-monaco .pay-options { border-right: 1px solid var(--gold-soft); padding-right: 4mm; }
.invoice.inv-monaco .pay-row { display: grid; grid-template-columns: 1fr 8mm; align-items: center; margin-bottom: 2.6mm; }
.invoice.inv-monaco .box { width: 3.2mm; height: 3.2mm; border: 1px solid #555; justify-self: center; display: flex; align-items: center; justify-content: center; font-size: 8px; line-height: 1; }
.invoice.inv-monaco .box.on { background: rgba(184, 117, 20, 0.15); }
.invoice.inv-monaco .amounts { padding-left: 1mm; }
.invoice.inv-monaco .amount-row { display: grid; grid-template-columns: minmax(0, 1fr) minmax(36mm, 42mm); gap: 2mm; align-items: end; margin: 2mm 0 7mm; }
.invoice.inv-monaco .amount-row .amount-label { min-width: 0; line-height: 1.25; }
.invoice.inv-monaco .amount-row .amount-label .ar { display: block; direction: rtl; text-align: left; }
.invoice.inv-monaco .amount-row .amount-field { display: flex; align-items: flex-end; gap: 1.5mm; min-width: 0; }
.invoice.inv-monaco .amount-row .amount-curr { flex-shrink: 0; white-space: nowrap; line-height: 5mm; }
.invoice.inv-monaco .amount-row .amount-line { flex: 1; border-bottom: 1px solid #777; height: 5mm; line-height: 5mm; text-align: right; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; padding: 0 1mm; font-variant-numeric: tabular-nums; }
.invoice.inv-monaco .items { width: calc(100% - 8mm); margin: 0 4mm; border-collapse: separate; border-spacing: 0; font-family: Georgia, 'Times New Roman', serif; font-size: 9px; table-layout: fixed; }
.invoice.inv-monaco .items th, .invoice.inv-monaco .items td { border-right: 1px solid var(--gold-soft); border-bottom: 1px solid #ead8bc; height: 7.6mm; padding: 1.5mm; text-align: center; vertical-align: middle; }
.invoice.inv-monaco .items th { height: 10.5mm; font-weight: 700; border-top: 1px solid var(--gold-soft); border-bottom: 1px solid var(--gold-soft); color: var(--ink) !important; background: #fff !important; text-transform: none !important; letter-spacing: normal !important; }
.invoice.inv-monaco .items th:first-child, .invoice.inv-monaco .items td:first-child { border-left: 1px solid var(--gold-soft); }
.invoice.inv-monaco .items tr:first-child th:first-child { border-radius: 2mm 0 0 0; }
.invoice.inv-monaco .items tr:first-child th:last-child { border-radius: 0 2mm 0 0; }
.invoice.inv-monaco .items col.sn { width: 6.8%; }
.invoice.inv-monaco .items col.desc { width: 20%; }
.invoice.inv-monaco .items col.karat { width: 9%; }
.invoice.inv-monaco .items col.gw { width: 10.2%; }
.invoice.inv-monaco .items col.dw { width: 11.4%; }
.invoice.inv-monaco .items col.qty { width: 6.2%; }
.invoice.inv-monaco .items col.val { width: 11.2%; }
.invoice.inv-monaco .items col.disc { width: 9%; }
.invoice.inv-monaco .items col.final { width: 13.2%; }
.invoice.inv-monaco .items .desc { text-align: left; }
.invoice.inv-monaco .items tfoot.items-total td { font-weight: 700; font-size: 10px; vertical-align: middle; border-bottom: 1px solid var(--gold-soft); }
.invoice.inv-monaco .items tfoot.items-total .total-spacer { background: #fff; }
.invoice.inv-monaco .items tfoot.items-total .total-label { line-height: 1.25; white-space: normal; }
.invoice.inv-monaco .items tfoot.items-total .total-label .ar { display: inline; direction: rtl; }
.invoice.inv-monaco .items tfoot.items-total .total-amt { font-variant-numeric: tabular-nums; white-space: nowrap; }
.invoice.inv-monaco .items tfoot.items-total tr td:first-child { border-radius: 0 0 0 2mm; }
.invoice.inv-monaco .items tfoot.items-total tr td:last-child { border-radius: 0 0 2mm 0; }
.invoice.inv-monaco .items-total .t10-amt-compact,
.invoice.inv-monaco .amount-line.t10-amt-compact { font-size: 9.5px; letter-spacing: -0.02em; }
.invoice.inv-monaco .bottom { display: grid; grid-template-columns: minmax(0, 31fr) minmax(0, 39fr) 22.86%; gap: 5mm; width: calc(100% - 8mm); margin: 4mm 4mm 0; align-items: stretch; }
.invoice.inv-monaco .left-stack { display: flex; flex-direction: column; height: 100%; min-height: 37mm; }
.invoice.inv-monaco .smallbox { border: 1px solid var(--gold-soft); border-radius: 2mm; padding: 2.6mm 3mm; font-size: 9.5px; box-sizing: border-box; }
.invoice.inv-monaco .smallrow { display: grid; grid-template-columns: 45mm 3mm 1fr; align-items: end; margin: 1.3mm 0; }
.invoice.inv-monaco .smallrow .line-field { border-bottom: 1px solid #777; height: 5mm; line-height: 5mm; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
.invoice.inv-monaco .notice {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-height: 37mm;
    padding: 3mm 3.5mm;
    font-family: Georgia, 'Times New Roman', serif;
    line-height: 1.5;
    font-size: 9.2px;
}
.invoice.inv-monaco .notice-en { text-align: left; }
.invoice.inv-monaco .notice-ar {
    margin-top: 2mm;
    text-align: right;
    direction: rtl;
    font-family: Tahoma, Arial, sans-serif;
    font-size: 8.8px;
    line-height: 1.45;
}
.invoice.inv-monaco .notice strong { font-size: 11px; }
.invoice.inv-monaco .terms { padding: 1mm 2mm; border-left: 1px dotted var(--gold-soft); border-right: 1px dotted var(--gold-soft); }
.invoice.inv-monaco .terms-title { display: flex; align-items: center; gap: 2mm; justify-content: center; color: var(--gold); font-family: Georgia, 'Times New Roman', serif; font-size: 12px; margin-bottom: 2mm; }
.invoice.inv-monaco .terms ul { margin: 0; padding-left: 5mm; font-size: 8.2px; line-height: 1.42; }
.invoice.inv-monaco .terms li { margin-bottom: 1.3mm; }
.invoice.inv-monaco .terms .ar { display: block; direction: rtl; font-family: Tahoma, Arial, sans-serif; font-size: 7.8px; }
.invoice.inv-monaco .signature { width: 100%; box-sizing: border-box; border: 1px solid var(--gold-soft); border-radius: 2mm; min-height: 37mm; padding: 4mm; text-align: center; font-family: Georgia, 'Times New Roman', serif; font-size: 10px; }
.invoice.inv-monaco .signature .sig-line { border-bottom: 1px solid #555; margin: 8mm 0 5mm; }
.invoice.inv-monaco .footer-star { position: absolute; color: var(--gold); font-size: 18px; bottom: 5mm; left: 50%; transform: translateX(-50%); }
.invoice.inv-monaco .ar { font-family: Tahoma, Arial, sans-serif; }
@media print {
    body { background: #fff !important; padding: 0 !important; margin: 0 !important; }
    .invoice.inv-monaco { max-width: 297mm !important; width: 297mm !important; }
    .invoice.inv-monaco .t10-page {
        margin: 0;
        box-shadow: none;
        width: 297mm;
        min-height: 0;
        height: auto;
        max-height: none;
        overflow: visible;
        padding: 10mm 8mm 10mm;
        page-break-after: avoid;
        page-break-inside: avoid;
    }
    .invoice.inv-monaco .header { margin: 2mm 3mm 2mm; gap: 5mm; }
    .invoice.inv-monaco .contact { font-size: 10px; line-height: 1.45; padding-top: 0; }
    .invoice.inv-monaco .logo { padding-top: 2mm; }
    .invoice.inv-monaco .logo img { width: 60mm; height: 25mm; }
    .invoice.inv-monaco .invoice-title { font-size: 17px; margin-bottom: 2mm; }
    .invoice.inv-monaco .mini-table td { padding: 2mm; height: 7mm; }
    .invoice.inv-monaco .gold-rate { margin-top: 2mm; font-size: 9px; }
    .invoice.inv-monaco .gold-rate > div { padding: 2mm; }
    .invoice.inv-monaco .divider { margin: 0 3mm 2mm; }
    .invoice.inv-monaco .upper-panels { margin: 0 3mm 2mm; gap: 4mm; }
    .invoice.inv-monaco .panel { min-height: 0; padding: 2.5mm 3mm; }
    .invoice.inv-monaco .customer-grid { row-gap: 1.6mm; font-size: 9.5px; }
    .invoice.inv-monaco .customer-grid .line-field { height: 4.5mm; line-height: 4.5mm; }
    .invoice.inv-monaco .payment-title { font-size: 11px; margin-bottom: 1.5mm; }
    .invoice.inv-monaco .payment-wrap { font-size: 9.5px; }
    .invoice.inv-monaco .pay-row { margin-bottom: 1.8mm; }
    .invoice.inv-monaco .amount-row { margin: 1.5mm 0 4mm; }
    .invoice.inv-monaco .items { width: calc(100% - 6mm); margin: 0 3mm; font-size: 8.5px; }
    .invoice.inv-monaco .items th { height: 8mm; padding: 1mm; }
    .invoice.inv-monaco .items th,
    .invoice.inv-monaco .items td { height: 6mm; padding: 1mm 1.2mm; }
    .invoice.inv-monaco .items tfoot.items-total td { font-size: 9px; padding: 1mm 1.2mm; height: 7mm; }
    .invoice.inv-monaco .bottom { width: calc(100% - 6mm); margin: 2mm 3mm 3mm; gap: 3mm; align-items: stretch; padding-bottom: 0; }
    .invoice.inv-monaco .left-stack { min-height: 0; }
    .invoice.inv-monaco .smallbox { padding: 2mm 2.5mm; font-size: 8.8px; }
    .invoice.inv-monaco .smallrow { margin: 1mm 0; }
    .invoice.inv-monaco .smallrow .line-field { height: 4.5mm; line-height: 4.5mm; }
    .invoice.inv-monaco .notice { min-height: 0; padding: 2.5mm 3mm; font-size: 8.5px; line-height: 1.4; }
    .invoice.inv-monaco .notice-ar { margin-top: 1.5mm; font-size: 8px; line-height: 1.35; }
    .invoice.inv-monaco .notice strong { font-size: 9.5px; }
    .invoice.inv-monaco .terms { padding: 0.5mm 1.5mm; }
    .invoice.inv-monaco .terms-title { font-size: 10px; margin-bottom: 1mm; }
    .invoice.inv-monaco .terms ul { font-size: 7.4px; line-height: 1.3; padding-left: 4mm; }
    .invoice.inv-monaco .terms li { margin-bottom: 0.7mm; }
    .invoice.inv-monaco .terms .ar { font-size: 7px; }
    .invoice.inv-monaco .signature { min-height: 0; padding: 2.5mm; font-size: 9px; }
    .invoice.inv-monaco .signature .sig-line { margin: 5mm 0 3mm; }
    .invoice.inv-monaco .footer-star { bottom: 5mm; font-size: 14px; }
    @page { size: A4 landscape; margin: 0; }
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
}
</style>

<div class="invoice inv-monaco template_10">
<div class="t10-page">
    <span class="spark s1">✦</span><span class="spark s2">✦</span><span class="spark s3">✦</span><span class="spark s4">✦</span>
    <span class="corner tl"></span><span class="corner tr"></span><span class="corner bl"></span><span class="corner br"></span>

    <div class="header">
        <div class="contact">
            <?php if ($footer_address !== ''): ?>
            <div class="contact-row">
                <?php echo $h($footer_address); ?>
                <?php if ($address_ar !== ''): ?><span>/</span> <span class="ar"><?php echo $h($address_ar); ?></span><?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if ($cr_no !== ''): ?>
            <div class="contact-row">CR No. :&nbsp; <?php echo $h($cr_no); ?> <span>/</span> <span class="ar">السجل التجاري</span></div>
            <?php endif; ?>
            <?php if (($print_settings['header_phone'] ?? '1') === '1' && $footer_phone !== ''): ?>
            <div class="contact-row">Phone Number :&nbsp; <?php echo $h($footer_phone); ?> <span>/</span> <span class="ar">رقم الهاتف</span></div>
            <?php endif; ?>
            <?php if ($footer_email !== ''): ?>
            <div class="contact-row">Email :&nbsp; <?php echo $h($footer_email); ?> <span>/</span> <span class="ar">البريد الإلكتروني</span></div>
            <?php endif; ?>
            <?php if ($website !== ''): ?>
            <div class="contact-row">Website :&nbsp; <?php echo $h($website); ?> <span>/</span> <span class="ar">الموقع الإلكتروني</span></div>
            <?php endif; ?>
            <?php if ($instagram !== ''): ?>
            <div class="contact-row">Instagram :&nbsp; <?php echo $h($instagram); ?> <span>/</span> <span class="ar">انستغرام</span></div>
            <?php endif; ?>
        </div>

        <div class="logo">
            <?php if (($print_settings['header_company_logo'] ?? '1') === '1' && !empty($has_logo)): ?>
            <img src="<?php echo $h($company_logo); ?>" alt="<?php echo $h($company_display); ?>">
            <?php else: ?>
            <div class="brand"><?php echo $h($brand_line1); ?></div>
            <?php if ($brand_sub !== ''): ?><div class="sub"><?php echo $h($brand_sub); ?></div><?php endif; ?>
            <?php endif; ?>
        </div>

        <div>
            <?php if (($print_settings['header_invoice_title'] ?? '1') === '1'): ?>
            <div class="invoice-title"><?php echo $h($doc_title_t10); ?> / <span class="ar">فاتورة مبيعات</span></div>
            <?php endif; ?>
            <table class="mini-table">
                <tr>
                    <td>Invoice No. / <span class="ar">رقم الفاتورة</span></td>
                    <td>Date / <span class="ar">التاريخ</span></td>
                </tr>
                <tr>
                    <td><?php echo $h($doc_no ?? ''); ?></td>
                    <td><?php echo $h($doc_date_t10); ?></td>
                </tr>
            </table>
            <div class="gold-rate">
                <div>Today's Gold Rate / <span class="ar">سعر الذهب اليوم</span> (<?php echo $h($gold_rate_karat); ?>)</div>
                <div><?php echo $h($amount_curr); ?> &nbsp; <?php echo $today_gold_rate > 0 ? $h($fmt_amt($today_gold_rate)) : '__________'; ?></div>
            </div>
        </div>
    </div>

    <div class="divider"><div class="line"></div><div class="diamond">✦</div><div class="line"></div></div>

    <div class="upper-panels">
        <div class="panel">
            <div class="customer-grid">
                <div>Salesman / <span class="ar">البائع</span></div><div>:</div><div class="line-field"><?php echo $h($person_value ?? ''); ?></div>
                <div>Customer Name / <span class="ar">اسم العميل</span></div><div>:</div><div class="line-field"><?php echo $h($party_name ?? ''); ?></div>
                <div>Customer No. / <span class="ar">رقم العميل</span></div><div>:</div><div class="line-field"><?php echo $h($party_ref ?? ''); ?></div>
                <div>Customer ID / <span class="ar">رقم الهوية</span></div><div>:</div><div class="line-field"><?php echo $h($customer_identity_no ?? ''); ?></div>
                <div>Date of Birth / <span class="ar">تاريخ الميلاد</span></div><div>:</div><div class="line-field"><?php echo $h($cust_dob); ?></div>
                <div>Customer Mobile / <span class="ar">رقم الجوال</span></div><div>:</div><div class="line-field"><?php echo $h($cust_mobile); ?></div>
            </div>
        </div>

        <div class="panel">
            <div class="payment-title">Payment Details / <span class="ar">تفاصيل الدفع</span></div>
            <div class="payment-wrap">
                <div class="pay-options">
                    <div class="pay-row"><span>Cash / <span class="ar">نقداً</span></span><span class="box<?php echo $pay_cash ? ' on' : ''; ?>"><?php echo $pay_cash ? '✓' : ''; ?></span></div>
                    <div class="pay-row"><span>Bank Transfer / <span class="ar">تحويل بنكي</span></span><span class="box<?php echo $pay_bank ? ' on' : ''; ?>"><?php echo $pay_bank ? '✓' : ''; ?></span></div>
                    <div class="pay-row"><span>Card / <span class="ar">بطاقة</span></span><span class="box<?php echo $pay_card ? ' on' : ''; ?>"><?php echo $pay_card ? '✓' : ''; ?></span></div>
                    <div class="pay-row"><span>Other / <span class="ar">أخرى</span></span><span class="box<?php echo $pay_other ? ' on' : ''; ?>"><?php echo $pay_other ? '✓' : ''; ?></span></div>
                </div>
                <div class="amounts">
                    <?php
                    $paid_fmt_t10 = $paid_t10 > 0 ? $fmt_amt($paid_t10) : '';
                    $balance_fmt_t10 = $balance_t10 > 0 ? $fmt_amt($balance_t10) : '';
                    $paid_amt_class = strlen($paid_fmt_t10) > 8 ? ' t10-amt-compact' : '';
                    $balance_amt_class = strlen($balance_fmt_t10) > 8 ? ' t10-amt-compact' : '';
                    ?>
                    <div class="amount-row">
                        <span class="amount-label">Amount Paid /<br><span class="ar">المبلغ المدفوع</span></span>
                        <div class="amount-field">
                            <span class="amount-curr"><?php echo $h($amount_curr); ?></span>
                            <span class="amount-line<?php echo $paid_amt_class; ?>"><?php echo $paid_fmt_t10 !== '' ? $h($paid_fmt_t10) : ''; ?></span>
                        </div>
                    </div>
                    <div class="amount-row">
                        <span class="amount-label">Balance /<br><span class="ar">الرصيد</span></span>
                        <div class="amount-field">
                            <span class="amount-curr"><?php echo $h($amount_curr); ?></span>
                            <span class="amount-line<?php echo $balance_amt_class; ?>"><?php echo $balance_fmt_t10 !== '' ? $h($balance_fmt_t10) : ''; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    $grand_fmt_t10 = $fmt_amt($grand_t10);
    $grand_amt_class = strlen($grand_fmt_t10) > 8 ? ' t10-amt-compact' : '';
    ?>
    <table class="items">
        <colgroup>
            <col class="sn"><col class="desc"><col class="karat"><col class="gw"><col class="dw"><col class="qty"><col class="val"><col class="disc"><col class="final">
        </colgroup>
        <thead>
            <tr>
                <th class="sn">S/No. / <span class="ar">م</span></th>
                <th class="desc">Code / Description / <span class="ar">الرمز والوصف</span></th>
                <th class="karat">Karat<br><span class="ar">العيار</span></th>
                <th class="gw">Gold Wt (g)<br><span class="ar">وزن الذهب (جم)</span></th>
                <th class="dw">Diamond Wt (ct)<br><span class="ar">وزن الألماس (قيراط)</span></th>
                <th class="qty">Qty<br><span class="ar">الكمية</span></th>
                <th class="val">Value<br><span class="ar">القيمة (<?php echo $h($amount_curr); ?>)</span></th>
                <th class="disc">Discount %<br><span class="ar">الخصم</span></th>
                <th class="final">Final Price<br><span class="ar">السعر النهائي (<?php echo $h($amount_curr); ?>)</span></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $item_line_count = count($item_lines);
            $table_min_rows = $item_line_count > 0
                ? max($item_line_count, min(3, $min_item_rows))
                : $min_item_rows;
            $pad_rows = max(0, $table_min_rows - $item_line_count);
            if (empty($item_lines)):
                for ($pi = 0; $pi < $min_item_rows; $pi++):
            ?>
            <tr><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
            <?php
                endfor;
            else:
                foreach ($item_lines as $ln):
            ?>
            <tr>
                <td><?php echo $h($ln['sn']); ?></td>
                <td class="desc"><?php echo $h($ln['desc']); ?></td>
                <td><?php
                    $karat_parts = preg_split('#\s*/\s*#u', (string) ($ln['karat'] ?? ''), 2);
                    if (is_array($karat_parts) && count($karat_parts) === 2 && trim($karat_parts[1]) !== '') {
                        echo $h(trim($karat_parts[0])) . ' / <span class="ar">' . $h(trim($karat_parts[1])) . '</span>';
                    } else {
                        echo $h($ln['karat']);
                    }
                ?></td>
                <td><?php echo $h($fmt_wt($ln['gold_wt'])); ?></td>
                <td><?php echo $h($fmt_wt($ln['diamond_wt'])); ?></td>
                <td><?php echo $h($fmt_qty($ln['qty'])); ?></td>
                <td><?php echo $ln['value'] > 0 ? $h($fmt_amt($ln['value'])) : ''; ?></td>
                <td><?php echo $ln['disc_pct'] > 0 ? $h($fmt_disc_pct($ln['disc_pct'])) : ''; ?></td>
                <td><?php echo $ln['final'] > 0 ? $h($fmt_amt($ln['final'])) : ''; ?></td>
            </tr>
            <?php
                endforeach;
                for ($pi = 0; $pi < $pad_rows; $pi++):
            ?>
            <tr><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
            <?php
                endfor;
            endif;
            ?>
        </tbody>
        <tfoot class="items-total">
            <tr>
                <td colspan="7" class="total-spacer"></td>
                <td class="disc total-label">Total / <span class="ar">الإجمالي</span></td>
                <td class="final total-amt<?php echo $grand_amt_class; ?>"><?php echo $h($amount_curr); ?> <?php echo $h($grand_fmt_t10); ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="bottom">
        <div class="left-stack">
            <div class="smallbox notice">
                <div class="notice-en">Dear Customer: Any notes call on the<br>Ministry of Economy and Trade Free: <strong>16001</strong></div>
                <div class="notice-ar">عزيزي العميل: لأي ملاحظات يرجى الاتصال<br>بوزارة التجارة والصناعة على الرقم المجاني <strong>16001</strong></div>
            </div>
        </div>

        <?php if ($show_terms): ?>
        <div class="terms">
            <div class="terms-title"><span>✦ ─</span> Terms &amp; Conditions / <span class="ar">الشروط والأحكام</span> <span>─ ✦</span></div>
            <ul>
                <?php
                $term_count = max(count($terms_en_lines), count($terms_ar_lines));
                for ($ti = 0; $ti < $term_count; $ti++):
                    $en_line = $terms_en_lines[$ti] ?? '';
                    $ar_line = $terms_ar_lines[$ti] ?? '';
                    if ($en_line === '' && $ar_line === '') {
                        continue;
                    }
                ?>
                <li>
                    <?php if ($en_line !== ''): ?><?php echo $h($en_line); ?><?php endif; ?>
                    <?php if ($ar_line !== ''): ?><span class="ar"><?php echo $h($ar_line); ?></span><?php endif; ?>
                </li>
                <?php endfor; ?>
            </ul>
        </div>
        <?php else: ?>
        <div></div>
        <?php endif; ?>

        <?php if (($print_settings['footer_authorized_signature'] ?? '1') === '1'): ?>
        <div class="signature">
            <div>Customer Signature / <span class="ar">توقيع العميل</span></div>
            <div class="sig-line"></div>
            <div>Sales Representative / <span class="ar">مندوب المبيعات</span></div>
            <div class="sig-line"></div>
        </div>
        <?php endif; ?>
    </div>

    <span class="footer-star">✦</span>
</div>
</div>

<div class="invoice-btns no-print">
    <a href="javascript:window.print()">Print</a>
    <a href="<?php echo $h($back_url ?? 'sale-invoice.php'); ?>">Back</a>
</div>
