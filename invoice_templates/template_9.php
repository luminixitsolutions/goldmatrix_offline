<?php
/**
 * Template 9 – Royal Design Jewellery A5 Tax Invoice (UAE bilingual red form).
 * Columns: Qty | Description | Grams | C.T. | Amount (Dhs.)
 */
if (!isset($items) || !is_array($items)) {
    $items = [];
}
$print_settings = is_array($print_settings ?? null) ? $print_settings : [];

$h = static function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};
/** Escape HTML but allow intentional <br> line breaks from settings fields. */
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
/** Gold karat for C.T. column (18k, 24k) — not diamond carat weight. */
$fmt_gold_karat = static function ($item) {
    if (!is_array($item)) {
        return '';
    }
    $conn_ref = (isset($GLOBALS['conn']) && $GLOBALS['conn']) ? $GLOBALS['conn'] : null;
    if (!$conn_ref && isset($conn) && $conn) {
        $conn_ref = $conn;
    }
    $candidates = [
        $item['purity'] ?? '',
        $item['karat'] ?? '',
        $item['opening_purity'] ?? '',
        $item['requested_purity'] ?? '',
    ];
    $carat_raw = trim((string) ($item['carat'] ?? ''));
    if ($carat_raw !== '' && !is_numeric($carat_raw)) {
        $candidates[] = $carat_raw;
    } elseif ($carat_raw !== '' && is_numeric($carat_raw)) {
        $cn = (float) $carat_raw;
        if ($cn >= 8 && $cn <= 24 && abs($cn - round($cn)) < 0.001) {
            $candidates[] = $carat_raw;
        }
    }
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
            if (preg_match('/^(\d+)\s*K?$/i', trim($label), $m)) {
                return $m[1] . 'k';
            }
            return $label;
        }
    }
    $name_hint = trim((string) ($item['product_name'] ?? ''));
    if ($name_hint === '') {
        $name_hint = trim((string) ($item['design_no'] ?? ''));
    }
    if ($name_hint !== '') {
        if (preg_match('/\b(8|9|10|14|18|21|22|24)\s*[Kk]\b/', $name_hint, $m)) {
            return $m[1] . 'k';
        }
        if (preg_match('/^(8|9|10|14|18|21|22|24)\s*[KkBG]/i', $name_hint, $m)) {
            return $m[1] . 'k';
        }
    }
    return '';
};

$company_trn_t9 = trim((string) ($print_settings['company_gst'] ?? ''));
if ($company_trn_t9 === '') {
    $company_trn_t9 = trim((string) ($company_trn ?? ''));
}

$arabic_company = trim((string) ($print_settings['t9_arabic_company_name'] ?? ''));
$qr_path = trim((string) ($print_settings['t9_qr_image_path'] ?? ''));
if ($qr_path === '') {
    $qr_path = 'qrcode.png';
}
$amount_curr = trim((string) ($print_settings['t9_amount_currency_label'] ?? 'Dhs.'));
if ($amount_curr === '') {
    $amount_curr = 'Dhs.';
}

$terms_en = trim((string) ($print_settings['terms_conditions'] ?? ''));
if ($terms_en === '') {
    $terms_en = 'NOTE: If any of the item in this bill is returned we will be responsible to refund the value of gold at current market rate, making charges and cost of stones are not refundable. Item once sold we are not responsible for any damage. Item once sold will not be returned.';
}
$terms_ar = trim((string) ($print_settings['t9_terms_arabic'] ?? ''));
if ($terms_ar === '') {
    $terms_ar = 'ملاحظة: في حال إرجاع أي من الأصناف المذكورة في هذه الفاتورة ستكون مسؤوليتنا عن استرداد قيمة الذهب بسعر السوق الحالي، أما رسوم التصنيع وتكلفة الأحجار الكريمة فهي غير قابلة للاسترداد بعد بيع الصنف.';
}
$show_terms = ($print_settings['footer_terms_conditions'] ?? '1') === '1';

$doc_date_t9 = $doc_date ?? '';
if (!empty($invoice['invoice_date'])) {
    $doc_date_t9 = date('d/m/Y', strtotime($invoice['invoice_date']));
}

$cust_mobile = '';
if (!empty($invoice['customer_id']) && isset($conn) && $conn) {
    $crow = @getRecord('SELECT * FROM tbl_customers WHERE id = ' . (int) $invoice['customer_id'] . ' LIMIT 1');
    if (is_array($crow)) {
        $cust_mobile = trim((string) ($crow['mobile_no'] ?? $crow['phone_no'] ?? $crow['phone'] ?? $crow['mobile'] ?? ''));
    }
}

$item_lines = [];
foreach ($items as $item) {
    if (!is_array($item)) {
        continue;
    }
    $pname = trim((string) ($item['product_name'] ?? ('Product #' . ($item['product_id'] ?? ''))));
    $barcode_no = trim((string) ($item['barcode'] ?? $item['barcode_no'] ?? $item['design_no'] ?? ''));
    $desc = $pname;
    if ($barcode_no !== '') {
        $desc = $pname . ' - ' . $barcode_no;
    }
    $pcs = (float) ($item['metal_qty'] ?? $item['quantity'] ?? 1);
    $grams = (float) ($item['net_weight'] ?? $item['final_weight'] ?? $item['gross_weight'] ?? 0);
    $ct = $fmt_gold_karat($item);
    $line_amt = (float) ($item['net_amount'] ?? $item['amount'] ?? 0);
    if ($line_amt <= 0) {
        $line_amt = (float) ($item['net_amt_with_tax'] ?? 0) - (float) ($item['tax_amount'] ?? 0);
    }
    $item_lines[] = [
        'qty' => $pcs,
        'desc' => $desc,
        'grams' => $grams,
        'ct' => $ct,
        'amount' => $line_amt,
    ];
}

$subtotal_t9 = (float) ($total_before_vat ?? 0);
if ($subtotal_t9 <= 0) {
    $sum_lines = 0.0;
    foreach ($item_lines as $ln) {
        $sum_lines += (float) $ln['amount'];
    }
    $subtotal_t9 = $sum_lines > 0 ? $sum_lines : ((float) ($subtotal ?? 0) - (float) ($discount_amt ?? 0) + (float) ($additional_amt ?? 0));
}
$vat_t9 = (float) ($tax_amount ?? 0);
$net_t9 = (float) ($grand_total ?? ($subtotal_t9 + $vat_t9));

$amount_words_t9 = trim((string) ($amount_words ?? ''));
if ($amount_words_t9 === '' && function_exists('numberToWords')) {
    $amount_words_t9 = numberToWords($net_t9, $amount_curr);
}

$t9_payment_lines = [];
$t9_payments_src = is_array($payments ?? null) ? $payments : [];
foreach ($t9_payments_src as $pay_row) {
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
    $pay_dep = strtolower(trim((string) ($pay_row['deposit_into'] ?? '')));
    $is_scrap_pay = ($pay_dep === 'scrap') || (strpos($pay_type, 'scrap') !== false);
    if ($is_scrap_pay) {
        $scrap_metal = '';
        $scrap_prod = trim((string) ($pay_row['scrap_product_name'] ?? $pay_row['product_name'] ?? ''));
        if ($scrap_prod !== '' && preg_match('/\(([^)]+)\)/', $scrap_prod, $scrap_m)) {
            $scrap_metal = trim((string) $scrap_m[1]);
        }
        if ($scrap_metal === '' && !empty($pay_row['scrap_metal_id']) && isset($conn) && $conn) {
            $scrap_mid = (int) $pay_row['scrap_metal_id'];
            $scrap_mrow = @getRecord('SELECT display_name, system_name FROM tbl_metal WHERE id = ' . $scrap_mid . ' LIMIT 1');
            if (is_array($scrap_mrow)) {
                $scrap_metal = trim((string) ($scrap_mrow['display_name'] ?? $scrap_mrow['system_name'] ?? ''));
            }
        }
        if ($scrap_metal === '') {
            $scrap_metal = 'Gold';
        }
        $scrap_wt = (float) ($pay_row['scrap_gross_wt'] ?? $pay_row['scrap_net_wt'] ?? $pay_row['weight'] ?? 0);
        $scrap_wt_txt = rtrim(rtrim(number_format($scrap_wt, 3, '.', ''), '0'), '.');
        if ($scrap_wt_txt === '') {
            $scrap_wt_txt = '0';
        }
        $t9_payment_lines[] = [
            'label' => 'Scrap',
            'amount' => $pay_amt,
            'detail' => ' ( ' . $scrap_metal . ' : ' . $scrap_wt_txt . 'gm )',
        ];
        continue;
    }
    if (strpos($pay_type, 'cash') !== false) {
        $pay_label = 'Cash';
    } elseif (strpos($pay_type, 'upi') !== false || strpos($pay_type, 'mobile') !== false) {
        $pay_label = 'UPI';
    } elseif (strpos($pay_type, 'bank') !== false || strpos($pay_type, 'transfer') !== false) {
        $pay_label = 'Bank';
    } elseif (strpos($pay_type, 'cheque') !== false || strpos($pay_type, 'check') !== false) {
        $pay_label = 'Cheque';
    } elseif (strpos($pay_type, 'card') !== false) {
        $pay_label = 'Card';
    } else {
        $pay_label = ucfirst($pay_type !== '' ? $pay_type : 'Payment');
    }
    $t9_payment_lines[] = ['label' => $pay_label, 'amount' => $pay_amt, 'detail' => ''];
}

$company_display = trim((string) ($company_name ?? ''));
$company_plain = trim(preg_replace('/<br\s*\/?>/i', ' ', $company_display));
$company_plain = preg_replace('/\s+/', ' ', $company_plain);
$footer_phone = trim((string) ($company_phone ?? ''));
$header_landline = trim((string) ($company_landline ?? ''));
$footer_address = trim((string) ($company_address ?? ''));

$has_qr = false;
$qr_src = '';
if ($qr_path !== '') {
    $qr_fs = dirname(__DIR__) . '/' . ltrim(str_replace('\\', '/', $qr_path), '/');
    if (is_file($qr_fs) || preg_match('#^(https?:)?//#i', $qr_path) || strpos($qr_path, 'data:') === 0) {
        $has_qr = true;
        $qr_src = $qr_path;
    }
}

$logo_initial = 'R';
if ($company_plain !== '') {
    $words = preg_split('/\s+/', $company_plain, 2);
    $logo_initial = strtoupper(substr($words[0], 0, 1));
}

$doc_title_t9 = trim((string) ($print_settings['invoice_title'] ?? ''));
if ($doc_title_t9 === '') {
    $doc_title_t9 = 'TAX INVOICE';
}
?>
<style>
.invoice.inv-royal {
    --invoice-red: #9d1d1d;
    font-family: Arial, Helvetica, sans-serif !important;
    font-size: 12px !important;
    color: var(--invoice-red) !important;
    background: #fff !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    max-width: 148mm !important;
    margin: 0 auto !important;
    padding: 0 !important;
    overflow: visible !important;
}
.invoice.inv-royal .t9-page {
    width: 148mm;
    min-height: 210mm;
    margin: 0 auto;
    background: #fff;
    padding: 3.5mm 5mm 10mm 5mm;
    position: relative;
    box-sizing: border-box;
    overflow: hidden;
}
.invoice.inv-royal .t9-header {
    height: 43mm;
    border: 1.4px solid var(--invoice-red);
    position: relative;
    padding: 2.5mm 2.5mm 1.5mm;
    box-sizing: border-box;
}
.invoice.inv-royal .t9-top-row {
    display: grid;
    grid-template-columns: 24mm 1fr 21mm;
    align-items: start;
    min-height: 22mm;
}
.invoice.inv-royal .t9-logo-box {
    display: flex;
    justify-content: center;
    align-items: flex-start;
}
.invoice.inv-royal .t9-logo-box img {
    width: 22mm;
    max-height: 22mm;
    object-fit: contain;
}
.invoice.inv-royal .t9-logo-ph {
    width: 21mm;
    height: 20mm;
    text-align: center;
    font-family: Georgia, serif;
    font-size: 8px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    line-height: 1.1;
}
.invoice.inv-royal .t9-logo-ph .crown { font-size: 18px; line-height: 16px; }
.invoice.inv-royal .t9-logo-ph .r { font-size: 25px; font-family: Georgia, serif; }
.invoice.inv-royal .t9-company-area {
    text-align: center;
    line-height: 1;
    padding-top: 1mm;
}
.invoice.inv-royal .t9-arabic-company {
    direction: rtl;
    font-family: Tahoma, Arial, sans-serif;
    font-weight: bold;
    font-size: 16px;
    margin-bottom: 2mm;
    white-space: normal;
    line-height: 1.2;
}
.invoice.inv-royal .t9-company-name {
    font-family: Georgia, "Times New Roman", serif;
    font-size: 15px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: -0.3px;
    line-height: 1.25;
    white-space: normal;
}
.invoice.inv-royal .t9-company-address {
    font-family: Georgia, "Times New Roman", serif;
    font-size: 7px;
    font-weight: bold;
    line-height: 1.2;
    margin-top: 0.6mm;
    white-space: normal;
}
.invoice.inv-royal .t9-company-contact-line {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 7px;
    font-weight: bold;
    line-height: 1.2;
    margin-top: 0.8mm;
    display: flex;
    justify-content: center;
    align-items: center;
    flex-wrap: nowrap;
    white-space: nowrap;
    gap: 3mm;
}
.invoice.inv-royal .t9-company-contact-line span {
    display: inline-block;
    white-space: nowrap;
}
.invoice.inv-royal .t9-qr-box { text-align: center; }
.invoice.inv-royal .t9-qr-box img {
    width: 18mm;
    height: 18mm;
    object-fit: contain;
}
.invoice.inv-royal .t9-qr-ph {
    width: 18mm;
    height: 18mm;
    border: 2px solid var(--invoice-red);
    display: grid;
    place-items: center;
    font-size: 8px;
    font-weight: bold;
    margin-left: auto;
}
.invoice.inv-royal .t9-tax-middle {
    position: absolute;
    top: 22.5mm;
    left: 50%;
    transform: translateX(-50%);
    text-align: center;
    font-size: 8px;
    line-height: 1.1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5mm;
}
.invoice.inv-royal .t9-tax-title-box {
    display: inline-block;
    border: 1px solid var(--invoice-red);
    padding: 1px 3px;
    background: #fff;
}
.invoice.inv-royal .t9-tax-title-box .arabic {
    direction: rtl;
    font-size: 8px;
}
.invoice.inv-royal .t9-tax-title-box strong { font-size: 8px; }
.invoice.inv-royal .t9-trn {
    background: var(--invoice-red);
    color: #fff;
    display: block;
    padding: 1px 2px;
    font-weight: bold;
    font-size: 6.8px;
    white-space: nowrap;
}
.invoice.inv-royal .t9-invoice-info {
    position: absolute;
    left: 2.5mm;
    right: 2.5mm;
    bottom: 1.5mm;
    font-size: 8.5px;
    font-weight: bold;
}
.invoice.inv-royal .t9-info-line {
    min-height: 4mm;
    display: flex;
    align-items: center;
}
.invoice.inv-royal .t9-info-line.no-date { justify-content: space-between; }
.invoice.inv-royal .t9-dots {
    border-bottom: 2px dotted var(--invoice-red);
    height: 2.5mm;
    flex: 1;
    margin: 0 1.5mm;
    min-width: 8mm;
}
.invoice.inv-royal .t9-date-dots {
    width: auto;
    border-bottom: none;
    height: auto;
    margin-left: 1mm;
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    font-size: 8.5px;
    font-weight: bold;
}
.invoice.inv-royal .t9-fill {
    flex: 1;
    border-bottom: none;
    min-height: auto;
    margin: 0 1.5mm;
    padding: 0 1mm;
    font-weight: bold;
    font-size: 8.5px;
    line-height: normal;
}
.invoice.inv-royal .t9-customer-arabic {
    direction: rtl;
    font-size: 9px;
    white-space: nowrap;
}
.invoice.inv-royal .t9-items-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    margin-top: 1mm;
}
.invoice.inv-royal .t9-items-table th,
.invoice.inv-royal .t9-items-table td {
    border: 1.3px solid var(--invoice-red);
}
.invoice.inv-royal .t9-items-table thead th {
    height: 9mm;
    text-align: center;
    vertical-align: middle;
    font-size: 9px;
    line-height: 1;
    font-weight: bold;
    padding: 0.5mm;
    color: var(--invoice-red) !important;
    background: #fff !important;
    text-transform: none !important;
    letter-spacing: normal !important;
}
.invoice.inv-royal .t9-items-table thead .arabic-head {
    display: block;
    direction: rtl;
    font-size: 7px;
    font-weight: normal;
    margin-bottom: 1mm;
}
.invoice.inv-royal .t9-items-table tbody {
    position: relative;
}
.invoice.inv-royal .t9-items-table tbody td {
    vertical-align: top;
    padding: 1.5mm 1mm;
    font-size: 9px;
    font-weight: bold;
    height: auto;
    min-height: 8mm;
}
.invoice.inv-royal .t9-items-body-wrap {
    position: relative;
    min-height: 90mm;
}
.invoice.inv-royal .t9-items-table tbody tr.t9-item-row td {
    border-top: none;
    border-bottom: 1px dotted rgba(157, 29, 29, 0.35);
    height: auto;
}
.invoice.inv-royal .t9-items-table tbody tr.t9-item-row:last-child td {
    border-bottom: 1.3px solid var(--invoice-red);
}
.invoice.inv-royal .t9-items-table tbody tr.t9-pad-row td {
    border-top: none;
    border-bottom: none;
    height: 8mm;
}
.invoice.inv-royal .t9-items-table tbody tr.t9-pad-row:last-child td {
    border-bottom: 1.3px solid var(--invoice-red);
}
.invoice.inv-royal .qty-col { width: 10%; text-align: center; }
.invoice.inv-royal .desc-col { width: 50%; text-align: left; position: relative; }
.invoice.inv-royal .gram-col { width: 9%; text-align: center; }
.invoice.inv-royal .ct-col { width: 9%; text-align: center; }
.invoice.inv-royal .amount-col { width: 22%; text-align: right; }
.invoice.inv-royal .t9-watermark {
    position: absolute;
    top: 45%;
    left: 50%;
    transform: translate(-50%, -50%);
    opacity: 0.055;
    text-align: center;
    font-family: Georgia, serif;
    font-weight: bold;
    width: 40mm;
    font-size: 13px;
    pointer-events: none;
    z-index: 0;
}
.invoice.inv-royal .t9-watermark .wr { font-size: 34px; display: block; }
.invoice.inv-royal .t9-item-row td { position: relative; z-index: 1; }
.invoice.inv-royal .t9-items-table tfoot td {
    border: 1.3px solid var(--invoice-red);
    vertical-align: top;
    padding: 10px;
}
.invoice.inv-royal .t9-amount-words-cell {
    padding: 0;
    font-size: 8.5px;
    font-weight: bold;
    min-height: 23mm;
    vertical-align: top;
}
.invoice.inv-royal .t9-items-table tfoot td.t9-payment-total-cell {
    padding: 0;
    border: none;
    vertical-align: top;
    min-height: 23mm;
    height: 23mm;
    box-sizing: border-box;
}
.invoice.inv-royal .t9-totals-table {
    width: 100%;
    height: 100%;
    min-height: 23mm;
    border-collapse: collapse;
    table-layout: fixed;
}
.invoice.inv-royal .t9-totals-table td {
    border: none;
    border-bottom: 1.3px solid var(--invoice-red);
    height: 7.67mm;
    vertical-align: middle;
    font-size: 9px;
    font-weight: bold;
    padding: 0 2.5mm;
    box-sizing: border-box;
}
.invoice.inv-royal .t9-totals-table tr:last-child td {
    border-bottom: 1.3px solid var(--invoice-red);
}
.invoice.inv-royal .t9-totals-table .t9-total-label {
    text-align: left;
    border-right: 1.3px solid var(--invoice-red);
}
.invoice.inv-royal .t9-totals-table .t9-total-value {
    text-align: right;
    border-right: 1.3px solid var(--invoice-red);
}
.invoice.inv-royal .t9-amount-words {
    display: block;
}
.invoice.inv-royal .t9-amount-words-label {
    font-size: 8px;
    margin: 0 0 2mm;
    text-transform: uppercase;
    letter-spacing: 0.2px;
}
.invoice.inv-royal .t9-amount-words-value {
    font-size: 8.5px;
    font-weight: normal;
    line-height: 1.35;
    text-transform: capitalize;
    width: 100%;
    padding-bottom: 1.5mm;
    margin-bottom: 0;
    border-bottom: 1.5px dotted var(--invoice-red);
}
.invoice.inv-royal .t9-payment-breakdown {
    margin-top: 4mm;
    font-size: 8.5px;
    font-weight: bold;
}
.invoice.inv-royal .t9-payment-breakdown-row {
    margin-top: 1.5mm;
}
.invoice.inv-royal .t9-payment-breakdown-row:first-child {
    margin-top: 0;
}
.invoice.inv-royal .t9-payment-breakdown-row .val {
    font-weight: normal;
}
.invoice.inv-royal .t9-terms {
    font-size: 5.3px;
    font-weight: bold;
    color: var(--invoice-red);
    margin-top: 3mm;
    padding: 1.5mm 1mm 0;
    line-height: 1.1;
}
.invoice.inv-royal .t9-terms-arabic {
    direction: rtl;
    font-family: Tahoma, Arial, sans-serif;
    font-size: 5px;
    margin-bottom: 1mm;
    text-align: justify;
}
.invoice.inv-royal .t9-terms-english { text-align: justify; }
.invoice.inv-royal .t9-signatures {
    display: flex;
    justify-content: space-between;
    margin-top: 8mm;
    margin-bottom: 10mm;
    padding: 0 1mm;
    font-family: Georgia, "Times New Roman", serif;
    font-size: 8px;
    font-weight: bold;
}
.invoice.inv-royal .t9-signature-section {
    width: 48%;
    display: flex;
    align-items: flex-end;
}
.invoice.inv-royal .t9-signature-line {
    border-bottom: 1.5px dotted var(--invoice-red);
    flex: 1;
    margin: 0 1mm;
    min-width: 20mm;
}
.invoice.inv-royal .t9-arabic-sign {
    direction: rtl;
    white-space: nowrap;
}
.invoice.inv-royal .t9-footer {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    height: 8mm;
    background: var(--invoice-red);
    color: white;
    display: grid;
    grid-template-columns: 34% 66%;
    align-items: center;
    padding: 0 5mm;
    font-family: Georgia, "Times New Roman", serif;
    font-size: 7.5px;
    box-sizing: border-box;
}
.invoice.inv-royal .t9-footer .phone {
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 3mm;
    flex-wrap: nowrap;
}
.invoice.inv-royal .t9-footer .phone-item {
    white-space: nowrap;
}
.invoice.inv-royal .t9-footer .address {
    text-align: right;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.invoice.inv-royal .t9-footer .icon {
    font-family: Arial, sans-serif;
    margin-right: 1.5mm;
}
@media print {
    body { background: #fff !important; padding: 0 !important; }
    .invoice.inv-royal {
        max-width: 148mm !important;
        width: 148mm !important;
    }
    .invoice.inv-royal .t9-page {
        margin: 0;
        width: 148mm;
        min-height: 210mm;
        height: 210mm;
        box-shadow: none;
    }
    @page { size: A5 portrait; margin: 0; }
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
</style>

<div class="invoice inv-royal template_9">
<div class="t9-page">

    <div class="t9-header">
        <div class="t9-top-row">
            <div class="t9-logo-box">
                <?php if (($print_settings['header_company_logo'] ?? '1') === '1' && !empty($has_logo)): ?>
                <img src="<?php echo $h($company_logo); ?>" alt="<?php echo $h($company_display); ?>">
                <?php else: ?>
                <div class="t9-logo-ph">
                    <div class="crown">♛</div>
                    <div class="r"><?php echo $h($logo_initial); ?></div>
                    <strong><?php echo $h(strtoupper(substr($company_plain !== '' ? $company_plain : 'ROYAL DESIGN', 0, 12))); ?></strong>
                </div>
                <?php endif; ?>
            </div>

            <div class="t9-company-area">
                <?php if ($arabic_company !== ''): ?>
                <div class="t9-arabic-company"><?php echo $h_br($arabic_company); ?></div>
                <?php endif; ?>
                <?php if (($print_settings['header_company_name'] ?? '1') === '1'): ?>
                <div class="t9-company-name"><?php echo $h_br($company_display); ?></div>
                <?php endif; ?>
                <?php if ($footer_address !== ''): ?>
                <div class="t9-company-address"><?php echo $h_br($footer_address); ?></div>
                <?php endif; ?>
                <?php
                $t9_show_mob = (($print_settings['header_phone'] ?? '1') === '1' && $footer_phone !== '');
                $t9_show_tel = ($header_landline !== '');
                if ($t9_show_mob || $t9_show_tel):
                ?>
                <div class="t9-company-contact-line">
                    <?php if ($t9_show_mob): ?><span>Mob: <?php echo $h($footer_phone); ?></span><?php endif; ?>
                    <?php if ($t9_show_tel): ?><span>Tel: <?php echo $h($header_landline); ?></span><?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="t9-qr-box">
                <?php if ($has_qr): ?>
                <img src="<?php echo $h($qr_src); ?>" alt="QR">
                <?php else: ?>
                <div class="t9-qr-ph">QR<br>CODE</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="t9-tax-middle">
            <?php if (($print_settings['header_invoice_title'] ?? '1') === '1'): ?>
            <div class="t9-tax-title-box">
                <div class="arabic">فاتورة ضريبية</div>
                <strong><?php echo $h($doc_title_t9); ?></strong>
            </div>
            <?php endif; ?>
            <?php if (($print_settings['header_gst_number'] ?? '1') === '1' && $company_trn_t9 !== ''): ?>
            <div class="t9-trn">TRN: <?php echo $h($company_trn_t9); ?></div>
            <?php endif; ?>
        </div>

        <div class="t9-invoice-info">
            <div class="t9-info-line no-date">
                <div style="display:flex; width:55%; align-items:center;">
                    <span>No.</span>
                    <span class="t9-fill"><?php echo $h($doc_no ?? ''); ?></span>
                </div>
                <div style="display:flex; width:40%; align-items:center; justify-content:flex-end;">
                    <span>Date :</span>
                    <span class="t9-date-dots"><?php echo $h($doc_date_t9); ?></span>
                </div>
            </div>
            <div class="t9-info-line">
                <span>Name of Customer:</span>
                <span class="t9-fill"><?php echo $h($party_name ?? ''); ?></span>
                <span class="t9-customer-arabic">اسم المشتري</span>
            </div>
            <div class="t9-info-line">
                <span>Mobile:</span>
                <span class="t9-fill"><?php echo $h($cust_mobile); ?></span>
            </div>
        </div>
    </div>

    <div class="t9-items-body-wrap">
        <div class="t9-watermark">
            <span style="font-size:18px;">♛</span>
            <span class="wr"><?php echo $h($logo_initial); ?></span>
            <?php echo $h(strtoupper(substr($company_plain !== '' ? $company_plain : 'ROYAL DESIGN', 0, 18))); ?>
        </div>

        <table class="t9-items-table">
            <colgroup>
                <col class="qty-col">
                <col class="desc-col">
                <col class="gram-col">
                <col class="ct-col">
                <col class="amount-col">
            </colgroup>
            <thead>
                <tr>
                    <th><span class="arabic-head">الكمية</span>Qty.</th>
                    <th><span class="arabic-head">البيان</span>DESCRIPTION</th>
                    <th><span class="arabic-head">جرام</span>Grams</th>
                    <th><span class="arabic-head">عيار الذهب</span>C.T.</th>
                    <th>
                        <span class="arabic-head">المبلغ</span>
                        Amount<br>
                        <span style="font-size:7px;"><?php echo $h($amount_curr); ?></span>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php
                $min_pad = max(6, 12 - count($item_lines));
                if (empty($item_lines)):
                ?>
                <tr class="t9-pad-row">
                    <td class="qty-col">&nbsp;</td>
                    <td class="desc-col">&nbsp;</td>
                    <td class="gram-col">&nbsp;</td>
                    <td class="ct-col">&nbsp;</td>
                    <td class="amount-col">&nbsp;</td>
                </tr>
                <?php
                    for ($pi = 1; $pi < $min_pad; $pi++):
                ?>
                <tr class="t9-pad-row">
                    <td class="qty-col"></td>
                    <td class="desc-col"></td>
                    <td class="gram-col"></td>
                    <td class="ct-col"></td>
                    <td class="amount-col"></td>
                </tr>
                <?php
                    endfor;
                else:
                    foreach ($item_lines as $ln):
                ?>
                <tr class="t9-item-row">
                    <td class="qty-col"><?php echo $h($fmt_qty($ln['qty'])); ?></td>
                    <td class="desc-col"><?php echo $h($ln['desc']); ?></td>
                    <td class="gram-col"><?php echo $h($fmt_wt($ln['grams'])); ?></td>
                    <td class="ct-col"><?php echo $ln['ct'] !== '' ? $h($ln['ct']) : ''; ?></td>
                    <td class="amount-col"><?php echo $h($fmt_amt($ln['amount'])); ?></td>
                </tr>
                <?php
                    endforeach;
                    for ($pi = 0; $pi < $min_pad; $pi++):
                ?>
                <tr class="t9-pad-row">
                    <td class="qty-col"></td>
                    <td class="desc-col"></td>
                    <td class="gram-col"></td>
                    <td class="ct-col"></td>
                    <td class="amount-col"></td>
                </tr>
                <?php
                    endfor;
                endif;
                ?>
            </tbody>
            <tfoot>
                <tr class="t9-payment-footer-row">
                    <td colspan="2" class="t9-amount-words-cell">
                        <div class="t9-amount-words">
                            <div class="t9-amount-words-label">
                                Amount in Words / <span class="t9-arabic-sign">المبلغ بالكلمات</span>
                            </div>
                            <div class="t9-amount-words-value">
                                <?php echo $h($amount_curr); ?> <?php echo $h($amount_words_t9); ?>
                            </div>
                            <?php if (!empty($t9_payment_lines)): ?>
                            <div class="t9-payment-breakdown">
                                <?php foreach ($t9_payment_lines as $pl): ?>
                                <div class="t9-payment-breakdown-row">
                                    <?php echo $h($pl['label']); ?> : <span class="val"><?php echo $h($fmt_amt($pl['amount'])); ?><?php echo !empty($pl['detail']) ? $h($pl['detail']) : ''; ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td colspan="3" class="t9-payment-total-cell">
                        <table class="t9-totals-table">
                            <colgroup>
                                <col style="width:22.5%">
                                <col style="width:22.5%">
                                <col style="width:55%">
                            </colgroup>
                            <tr>
                                <td colspan="2" class="t9-total-label">TOTAL</td>
                                <td class="t9-total-value"><?php echo $h($fmt_amt($subtotal_t9)); ?></td>
                            </tr>
                            <tr>
                                <td colspan="2" class="t9-total-label">VAT 5%</td>
                                <td class="t9-total-value"><?php echo $h($fmt_amt($vat_t9)); ?></td>
                            </tr>
                            <tr>
                                <td colspan="2" class="t9-total-label">NET TOTAL</td>
                                <td class="t9-total-value"><?php echo $h($fmt_amt($net_t9)); ?></td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <?php if ($show_terms): ?>
    <div class="t9-terms">
        <?php if ($terms_ar !== ''): ?>
        <div class="t9-terms-arabic"><?php echo $h($terms_ar); ?></div>
        <?php endif; ?>
        <div class="t9-terms-english"><?php echo $h($terms_en); ?></div>
    </div>
    <?php endif; ?>

    <?php if (($print_settings['footer_authorized_signature'] ?? '1') === '1'): ?>
    <div class="t9-signatures">
        <div class="t9-signature-section">
            <span>Customer’s Signature</span>
            <span class="t9-signature-line"></span>
            <span class="t9-arabic-sign">توقيع العميل</span>
        </div>
        <div class="t9-signature-section">
            <span>Signature</span>
            <span class="t9-signature-line"></span>
            <span class="t9-arabic-sign">التوقيع</span>
        </div>
    </div>
    <?php endif; ?>

    <div class="t9-footer">
        <div class="phone">
            <?php if (($print_settings['header_phone'] ?? '1') === '1' && $footer_phone !== ''): ?>
            <span class="phone-item"><span class="icon">☎</span><?php echo $h($footer_phone); ?></span>
            <?php endif; ?>
            <?php if ($header_landline !== ''): ?>
            <span class="phone-item"><span class="icon">☎</span><?php echo $h($header_landline); ?></span>
            <?php endif; ?>
        </div>
        <div class="address">
            <?php if ($footer_address !== ''): ?>
            <span class="icon">⌖</span><?php echo $h($footer_address); ?>
            <?php endif; ?>
        </div>
    </div>

</div>
</div>

<div class="invoice-btns no-print">
    <a href="javascript:window.print()">Print</a>
    <a href="<?php echo $h($back_url ?? 'sale-invoice.php'); ?>">Back</a>
</div>
