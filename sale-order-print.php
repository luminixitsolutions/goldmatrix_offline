<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/includes/invoice-print-currency-symbol.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
// Language from URL or from Invoice Print Settings
$print_settings_early = function_exists('getInvoicePrintSettingsForDocument') ? getInvoicePrintSettingsForDocument('sale_order') : [];
$invoice_secondary_early = isset($print_settings_early['invoice_secondary_language']) ? trim((string)$print_settings_early['invoice_secondary_language']) : '';
if (!in_array($invoice_secondary_early, ['hi', 'mr', 'ar'], true)) $invoice_secondary_early = '';
if (isset($_GET['lang'])) {
    $lang = strtolower(trim($_GET['lang']));
    $allowed_langs = function_exists('getInvoicePrintAllowedLanguages') ? getInvoicePrintAllowedLanguages('sale_order') : ['en'];
    if (!in_array($lang, $allowed_langs ?: ['en'], true)) $lang = 'en';
} else {
    $lang = ($invoice_secondary_early === 'ar') ? 'ar' : 'en';
}

if ($order_id <= 0) {
    die('Invalid order ID');
}

try {
    $order = getRecord("SELECT * FROM tbl_sale_orders WHERE id = $order_id");
    if (!$order) die('Order not found');
} catch (Exception $e) {
    die('Error loading order: ' . $e->getMessage());
}

$items = getList("
    SELECT soi.*, COALESCE(p.name, soi.product_name) as product_name, c.name as category_name
    FROM tbl_sale_order_items soi
    LEFT JOIN tbl_products p ON soi.product_id = p.id
    LEFT JOIN tbl_categories c ON p.category_id = c.id
    WHERE soi.order_id = $order_id ORDER BY soi.id ASC
");

$payments = getList("SELECT * FROM tbl_sale_order_payments WHERE order_id = $order_id ORDER BY id ASC");

$payment_totals = ['cash' => 0, 'bank' => 0, 'cheque' => 0, 'upi' => 0, 'card' => 0, 'metal_exchange' => 0, 'scrap' => 0];
foreach ($payments as $payment) {
    $pt = strtolower($payment['payment_type'] ?? 'cash');
    $amt = (float)($payment['amount'] ?? 0);
    if (isset($payment_totals[$pt])) $payment_totals[$pt] += $amt;
    else $payment_totals['cash'] += $amt;
}

$currency = !empty($order['currency']) ? trim((string) $order['currency']) : 'AED';
$currency_symbol = invoice_print_currency_symbol(isset($conn) ? $conn : null, $currency);

$order_date_raw = !empty($order['order_date']) ? strtotime($order['order_date']) : time();
$order_date = date('d/m/Y', $order_date_raw);
$order_time = date('h:i A', $order_date_raw);

$company_name = defined('COMPANY_NAME') ? COMPANY_NAME : (isset($Proj_Title) ? $Proj_Title : 'Gold Matrix');
$company_trn = defined('COMPANY_TRN') ? COMPANY_TRN : '100436638900003';
$company_address = defined('COMPANY_ADDRESS') ? COMPANY_ADDRESS : 'Dubai, UAE';
$company_phone = defined('COMPANY_PHONE') ? COMPANY_PHONE : '';
$company_email = defined('COMPANY_EMAIL') ? COMPANY_EMAIL : '';
$company_logo = defined('COMPANY_LOGO') ? COMPANY_LOGO : 'assets/img/logo.png';
$logo_path = dirname(__FILE__) . '/' . $company_logo;
$has_logo = file_exists($logo_path);

// Print settings for this document type (fallback to default when not set)
$print_settings = function_exists('getInvoicePrintSettingsForDocument') ? getInvoicePrintSettingsForDocument('sale_order') : [];
$defs = function_exists('getInvoicePrintSettingsDefaults') ? getInvoicePrintSettingsDefaults() : [];
$print_settings = is_array($print_settings) && !empty($print_settings) ? array_merge($defs, $print_settings) : $defs;
$selected_columns = isset($print_settings['sale_invoice_columns']) && is_array($print_settings['sale_invoice_columns']) ? $print_settings['sale_invoice_columns'] : ['sr_no','item_name','gross_weight','less_weight','net_weight','purity_karat','rate','amount'];
$layout_type = isset($print_settings['layout_type']) ? normalizeInvoicePrintLayoutType($print_settings['layout_type']) : 'A4';
$page_orientation = isset($print_settings['page_orientation']) ? normalizeInvoicePrintPageOrientation($print_settings['page_orientation']) : 'portrait';
$design_template = $print_settings['design_template'] ?? 'template_1';
if (!empty($print_settings['company_name'])) $company_name = $print_settings['company_name'];
if (!empty($print_settings['company_address'])) $company_address = $print_settings['company_address'];
if (!empty($print_settings['company_gst'])) $company_trn = $print_settings['company_gst'];
if (isset($print_settings['company_phone'])) $company_phone = $print_settings['company_phone'];
if (isset($print_settings['company_email'])) $company_email = $print_settings['company_email'];
if (!empty($print_settings['company_logo_path'])) {
    $company_logo = $print_settings['company_logo_path'];
    $logo_path = dirname(__FILE__) . '/' . $company_logo;
    $has_logo = file_exists($logo_path);
}

$gold_rates = ['24K' => 610.50, '22K' => 565.25, '21K' => 542.00, '18K' => 464.50];
$tbl_settings = null;
$tbl_check = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_settings'");
if ($tbl_check && mysqli_num_rows($tbl_check) > 0) {
    $tbl_settings = getRecord("SELECT * FROM tbl_settings LIMIT 1");
    if ($tbl_settings) {
        if (!empty($tbl_settings['gold_rate_24k'])) $gold_rates['24K'] = (float)$tbl_settings['gold_rate_24k'];
        if (!empty($tbl_settings['gold_rate_22k'])) $gold_rates['22K'] = (float)$tbl_settings['gold_rate_22k'];
        if (!empty($tbl_settings['gold_rate_21k'])) $gold_rates['21K'] = (float)$tbl_settings['gold_rate_21k'];
        if (!empty($tbl_settings['gold_rate_18k'])) $gold_rates['18K'] = (float)$tbl_settings['gold_rate_18k'];
    }
}

$subtotal = (float)($order['subtotal'] ?? 0);
$discount_amt = (float)($order['discount_amt'] ?? 0);
$additional_amt = (float)($order['additional_amt'] ?? 0);
$tax_amount = 0;
foreach ($items as $item) $tax_amount += (float)($item['tax_amount'] ?? 0);
$grand_total = (float)($order['grand_total'] ?? 0);
$paid_amt = (float)($order['paid_amt'] ?? 0);
$balance_amt = (float)($order['balance_amt'] ?? 0);
$advance_amt = (float)($order['advance_payment'] ?? 0);
$round_off = (float)($order['round_off'] ?? 0);
$scrap_amt = (float)($payment_totals['scrap'] ?? 0);
$total_before_vat = $grand_total - $tax_amount;
if ($total_before_vat < 0) $total_before_vat = 0;

$total_weight = 0;
$total_stone_weight = 0;
$total_net_wt = 0;
foreach ($items as $item) {
    $total_weight += (float)($item['gross_weight'] ?? 0);
    $total_stone_weight += (float)($item['stone_weight'] ?? $item['carat'] ?? 0);
    $total_net_wt += (float)($item['net_weight'] ?? $item['final_weight'] ?? 0);
}

$customer_ref = $order['ref_no'] ?? ($order['customer_id'] ?? '');
if (empty($customer_ref) && !empty($order['customer_id'])) {
    $cust = getRecord("SELECT ref_no, mobile_no FROM tbl_customers WHERE id = " . (int)$order['customer_id'] . " LIMIT 1");
    $customer_ref = $cust['ref_no'] ?? $cust['mobile_no'] ?? $order['customer_id'];
}

$L = [
    'en' => [
        'sale_order' => 'SALE ORDER', 'order_no' => 'Order No.', 'date' => 'Date', 'salesman' => 'Salesman',
        'mr_ms' => 'Mr./M/S', 'customer_no' => 'Customer No.', 'passport_id' => 'Passport / ID No.', 'phone_no' => 'Phone No.',
        'sr' => 'Sr', 'description' => 'Description', 'weight' => 'Weight', 'stone_weight' => 'Stone Weight',
        'net_wt' => 'Net Wt.', 'net_amt' => 'Net Amt', 'vat_5' => 'VAT 5%', 'total_amount' => 'Total Amount',
        'total' => 'TOTAL', 'advance_amount' => 'Advance Amount', 'total_before_vat' => 'Total Before VAT',
        'vat_5_label' => 'VAT 5%', 'total_including_vat' => 'Total Including VAT',
        'less_scrap' => 'Less: Scrap', 'balance_amount' => 'BALANCE AMOUNT',
        'amount_in_words' => 'Amount in Words', 'card' => 'Card', 'comment' => 'Comment',
        'design_no' => 'Design No', 'huid' => 'HUID', 'category' => 'Category', 'less_weight' => 'Less Wt', 'purity_karat' => 'Purity/Karat', 'rate' => 'Rate', 'making_charge' => 'Making', 'diamond_amount' => 'Diamond', 'stone_amount' => 'Stone', 'discount' => 'Discount',
    ],
    'hi' => [
        'sale_order' => 'बिक्री ऑर्डर', 'order_no' => 'ऑर्डर सं.', 'date' => 'तारीख', 'salesman' => 'विक्रेता',
        'mr_ms' => 'श्री/श्रीमती', 'customer_no' => 'ग्राहक सं.', 'passport_id' => 'पासपोर्ट / आईडी सं.', 'phone_no' => 'फोन सं.',
        'sr' => 'क्र.', 'description' => 'विवरण', 'weight' => 'वजन', 'stone_weight' => 'पत्थर वजन',
        'net_wt' => 'शुद्ध वजन', 'net_amt' => 'शुद्ध राशि', 'vat_5' => 'वैट 5%', 'total_amount' => 'कुल राशि',
        'total' => 'कुल', 'advance_amount' => 'अग्रिम राशि', 'total_before_vat' => 'वैट से पहले कुल',
        'vat_5_label' => 'वैट 5%', 'total_including_vat' => 'वैट सहित कुल',
        'less_scrap' => 'घटाया: स्क्रैप', 'balance_amount' => 'शेष राशि',
        'amount_in_words' => 'शब्दों में राशि', 'card' => 'कार्ड', 'comment' => 'टिप्पणी',
        'design_no' => 'डिज़ाइन नं.', 'huid' => 'HUID', 'category' => 'श्रेणी', 'less_weight' => 'कम वजन', 'purity_karat' => 'शुद्धता', 'rate' => 'दर', 'making_charge' => 'निर्माण', 'diamond_amount' => 'हीरा', 'stone_amount' => 'पत्थर', 'discount' => 'छूट',
    ],
    'mr' => [
        'sale_order' => 'विक्री ऑर्डर', 'order_no' => 'ऑर्डर क्र.', 'date' => 'तारीख', 'salesman' => 'विक्रेता',
        'mr_ms' => 'श्री/श्रीमती', 'customer_no' => 'ग्राहक क्र.', 'passport_id' => 'पासपोर्ट / आयडी क्र.', 'phone_no' => 'फोन क्र.',
        'sr' => 'क्र.', 'description' => 'विवरण', 'weight' => 'वजन', 'stone_weight' => 'दगड वजन',
        'net_wt' => 'निव्वळ वजन', 'net_amt' => 'निव्वळ रक्कम', 'vat_5' => 'व्हॅट 5%', 'total_amount' => 'एकूण रक्कम',
        'total' => 'एकूण', 'advance_amount' => 'अग्रिम रक्कम', 'total_before_vat' => 'व्हॅट आधी एकूण',
        'vat_5_label' => 'व्हॅट 5%', 'total_including_vat' => 'व्हॅट सह एकूण',
        'less_scrap' => 'वजा: स्क्रॅप', 'balance_amount' => 'शिल्लक रक्कम',
        'amount_in_words' => 'शब्दांमध्ये रक्कम', 'card' => 'कार्ड', 'comment' => 'टिप्पणी',
        'design_no' => 'डिझाइन क्र.', 'huid' => 'HUID', 'category' => 'श्रेणी', 'less_weight' => 'कमी वजन', 'purity_karat' => 'शुद्धता', 'rate' => 'दर', 'making_charge' => 'निर्मिती', 'diamond_amount' => 'हिरा', 'stone_amount' => 'दगड', 'discount' => 'सूट',
    ],
    'ar' => [
        'sale_order' => 'أمر البيع', 'order_no' => 'رقم الأمر', 'date' => 'التاريخ', 'salesman' => 'البائع',
        'mr_ms' => 'السيد / السيدة', 'customer_no' => 'رقم العميل', 'passport_id' => 'رقم الجواز', 'phone_no' => 'رقم التلفون',
        'sr' => 'رقم', 'description' => 'التفاصيل', 'weight' => 'الوزن', 'stone_weight' => 'حجر الوزن',
        'net_wt' => 'الصافي الوزن', 'net_amt' => 'إجمالي المبلغ', 'vat_5' => 'المضافة ضريبة', 'total_amount' => 'المبلغ',
        'total' => 'المجموع', 'advance_amount' => 'المبلغ المسبق', 'total_before_vat' => 'المجموع قبل الضريبة',
        'vat_5_label' => 'ضريبة القيمة %5', 'total_including_vat' => 'المجموع مع الضريبة',
        'less_scrap' => 'ناقص الخردة', 'balance_amount' => 'مبلغ الرصيد',
        'amount_in_words' => 'المبلغ بالكلمات', 'card' => 'البطاقة', 'comment' => 'تعليق',
        'design_no' => 'رقم التصميم', 'huid' => 'HUID', 'category' => 'الفئة', 'less_weight' => 'أقل الوزن', 'purity_karat' => 'العيار', 'rate' => 'السعر', 'making_charge' => 'صنع', 'diamond_amount' => 'الماس', 'stone_amount' => 'الحجر', 'discount' => 'خصم',
    ],
];
$t = array_merge($L['en'], $L[$lang] ?? []);
$col_labels = [
    'sr_no' => $t['sr'],
    'item_name' => $t['description'],
    'design_no' => $t['design_no'],
    'huid' => $t['huid'],
    'category' => $t['category'],
    'gross_weight' => $t['weight'],
    'less_weight' => $t['less_weight'],
    'net_weight' => $t['net_wt'],
    'purity_karat' => $t['purity_karat'],
    'rate' => $t['rate'],
    'making_charge' => $t['making_charge'],
    'diamond_amount' => $t['diamond_amount'],
    'stone_amount' => $t['stone_amount'],
    'discount' => $t['discount'],
    'amount' => $t['total_amount'],
];

function numberToWordsOrder($num, $currency = 'AED') {
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    $num = (int)round($num);
    if ($num == 0) return 'Zero Only';
    if ($num >= 1000000) return number_format($num) . ' Only';
    $words = '';
    if ($num >= 100000) { $words .= $ones[(int)($num/100000)] . ' Lakh '; $num %= 100000; }
    if ($num >= 1000) { $w = (int)($num/1000); $words .= ($w >= 100 ? $ones[(int)($w/100)] . ' Hundred ' : '') . ($w % 100 >= 20 ? $tens[(int)(($w%100)/10)] . ' ' . $ones[$w%10] : $ones[$w%100]) . ' Thousand '; $num %= 1000; }
    if ($num >= 100) { $words .= $ones[(int)($num/100)] . ' Hundred '; $num %= 100; }
    if ($num >= 20) { $words .= $tens[(int)($num/10)] . ' '; $num %= 10; }
    $words .= $ones[$num];
    return trim($words) . ' Only';
}
$amount_words = numberToWordsOrder($grand_total, $currency);
$invoice_secondary = isset($print_settings['invoice_secondary_language']) ? trim((string)$print_settings['invoice_secondary_language']) : '';
if (!in_array($invoice_secondary, ['hi', 'mr', 'ar'], true)) $invoice_secondary = '';
$bilingual = $invoice_secondary !== '';
$t2 = [];
if ($bilingual && isset($L[$invoice_secondary])) $t2 = array_merge($L['en'], $L[$invoice_secondary]);
$col_labels_secondary = [];
if ($bilingual && !empty($t2)) {
    $col_labels_secondary = [
        'sr_no' => $t2['sr'] ?? '', 'item_name' => $t2['description'] ?? '', 'design_no' => $t2['design_no'] ?? '',
        'huid' => $t2['huid'] ?? '', 'category' => $t2['category'] ?? '', 'gross_weight' => $t2['weight'] ?? '',
        'less_weight' => $t2['less_weight'] ?? '', 'net_weight' => $t2['net_wt'] ?? '',
        'purity_karat' => $t2['purity_karat'] ?? '', 'rate' => $t2['rate'] ?? '',
        'making_charge' => $t2['making_charge'] ?? '', 'diamond_amount' => $t2['diamond_amount'] ?? '',
        'stone_amount' => $t2['stone_amount'] ?? '', 'discount' => $t2['discount'] ?? '',
        'amount' => $t2['total_amount'] ?? '',
    ];
}
if (!isset($t['invoice_no'])) $t['invoice_no'] = $t['order_no'];

$item_rows = [];
$sr = 0;
foreach ($items as $item) {
    $sr++;
    $pname = !empty($item['product_name']) ? $item['product_name'] : ('Product #' . ($item['product_id'] ?? ''));
    $design_no = !empty($item['design_no']) ? $item['design_no'] : (!empty($item['barcode']) ? $item['barcode'] : '');
    $gross = (float)($item['gross_weight'] ?? 0);
    $less_wt = (float)($item['less_weight'] ?? 0);
    $stone_wt = (float)($item['stone_weight'] ?? $item['carat'] ?? 0);
    $net_wt = (float)($item['net_weight'] ?? $item['final_weight'] ?? 0);
    $rate = (float)($item['rate'] ?? 0);
    $making_amt = (float)($item['making_amount'] ?? 0);
    $diamond_amt = (float)($item['diamond_amount'] ?? 0);
    $stone_amt = (float)($item['stone_amount'] ?? 0);
    $item_discount = (float)($item['discount'] ?? 0);
    $net_amt = (float)($item['net_amount'] ?? $item['amount'] ?? 0);
    $tax_item = (float)($item['tax_amount'] ?? 0);
    $total_item = (float)($item['net_amt_with_tax'] ?? ($net_amt + $tax_item));
    $purity_karat = $item['purity'] ?? $item['carat'] ?? '';
    if (is_numeric($purity_karat)) $purity_karat = number_format((float)$purity_karat, 2);
    $category_name = $item['category_name'] ?? '';
    $huid = $item['barcode'] ?? '';
    $item_rows[] = [
        'sr_no' => $sr,
        'item_name' => $pname,
        'design_no' => $design_no,
        'huid' => $huid,
        'category' => $category_name,
        'gross_weight' => number_format($gross, 3),
        'less_weight' => number_format($less_wt, 3),
        'net_weight' => number_format($net_wt, 3),
        'purity_karat' => $purity_karat,
        'rate' => number_format($rate, 2),
        'making_charge' => number_format($making_amt, 2),
        'diamond_amount' => number_format($diamond_amt, 2),
        'stone_amount' => number_format($stone_amt, 2),
        'discount' => number_format($item_discount, 2),
        'amount' => number_format($total_item, 2),
    ];
}

$doc_no = $order['order_no'] ?? '';
$doc_date = $order_date;
$doc_time = $order_time;
$party_name = $order['customer_name'] ?? '';
$party_ref = $customer_ref;
$person_label = $t['salesman'];
$person_value = $order['sales_person'] ?? '';
$doc_comment = $order['comment'] ?? '';
$doc_title = !empty($print_settings['invoice_title']) ? $print_settings['invoice_title'] : $t['sale_order'];
$back_url = 'sale-order.php';
$document = $order;

$template = isset($_GET['template_preview']) ? trim((string)$_GET['template_preview']) : (function_exists('getInvoiceTemplateForDocument') ? getInvoiceTemplateForDocument('sale_order') : 'template_classic');
$structure_templates = function_exists('getInvoicePrintStructureTemplates') ? array_keys(getInvoicePrintStructureTemplates()) : ['template_classic'];
if (!in_array($template, $structure_templates, true)) {
    $template = 'template_classic';
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang === 'ar' ? 'ar' : 'en'; ?>" dir="<?php echo $lang === 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo $t['sale_order']; ?> - <?php echo htmlspecialchars($order['order_no']); ?></title>
  <style>
    <?php if (function_exists('getInvoicePrintLayoutInlineCss')) echo getInvoicePrintLayoutInlineCss($layout_type, $page_orientation); ?>

    * { box-sizing: border-box; }
    body { font-family: Roboto, 'Segoe UI', Tahoma, Arial, sans-serif; font-size: 12px; margin: 0; padding: 15px; color: #2d3748; background: #f7fafc; }
    .invoice { max-width: 210mm; margin: 0 auto; background: #fff; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border-radius: 12px; overflow: hidden; }
    .invoice.invoice-thermal { max-width: 80mm; font-size: 10px; padding: 8px; }
    .invoice.invoice-thermal .inv-header { padding: 12px 14px; }
    .invoice.invoice-thermal .inv-logo, .invoice.invoice-thermal .inv-logo-placeholder { width: 40px; height: 40px; font-size: 14px; }
    .invoice.invoice-thermal .inv-company h1 { font-size: 14px; }
    .invoice.invoice-thermal .inv-tax-badge { font-size: 12px; padding: 8px; }
    .invoice.invoice-thermal .inv-body { padding: 12px 14px; }
    .invoice.invoice-thermal th, .invoice.invoice-thermal td { padding: 4px 6px; font-size: 9px; }
    @media print { body { padding: 0; background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; } .invoice { box-shadow: none; } .no-print { display: none !important; } tbody tr:hover { background: inherit !important; } }
    
    .inv-header { background: linear-gradient(135deg, #1a365d 0%, #2c5282 50%, #1a365d 100%); color: #fff; padding: 24px 28px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
    .inv-header-left { display: flex; align-items: center; gap: 20px; }
    .inv-logo { width: 70px; height: 70px; object-fit: contain; background: #fff; border-radius: 10px; padding: 6px; }
    .inv-logo-placeholder { width: 70px; height: 70px; background: linear-gradient(135deg, #d4af37 0%, #f4e4a6 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: bold; color: #1a365d; }
    .inv-company { }
    .inv-company h1 { margin: 0; font-size: 26px; font-weight: 700; letter-spacing: 0.5px; text-shadow: 0 1px 2px rgba(0,0,0,0.2); }
    .inv-company .tagline { margin: 4px 0 0 0; font-size: 11px; opacity: 0.9; }
    .inv-trn { background: rgba(255,255,255,0.15); padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 600; }
    
    .inv-tax-badge { background: linear-gradient(135deg, #d4af37 0%, #c9a227 100%); color: #1a365d; text-align: center; padding: 12px; font-size: 18px; font-weight: 700; letter-spacing: 2px; }
    
    .inv-body { padding: 24px 28px; }
    .inv-info-row { display: flex; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 16px; }
    .inv-customer { background: linear-gradient(135deg, #f8fafc 0%, #edf2f7 100%); padding: 16px 20px; border-radius: 10px; border-left: 4px solid #2c5282; flex: 1; min-width: 220px; }
    .inv-customer .label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #718096; margin-bottom: 4px; }
    .inv-customer .value { font-size: 14px; font-weight: 600; color: #1a365d; }
    .inv-invoice-meta { text-align: right; }
    .inv-invoice-meta .meta-row { margin-bottom: 6px; font-size: 13px; }
    .inv-invoice-meta .meta-label { color: #718096; margin-right: 8px; }
    .inv-invoice-meta .meta-value { font-weight: 600; color: #1a365d; }
    
    .gold-rates { background: linear-gradient(90deg, #d4af37 0%, #f4e4a6 25%, #d4af37 50%, #f4e4a6 75%, #d4af37 100%); padding: 12px 20px; margin: 0 0 20px 0; display: flex; gap: 24px; flex-wrap: wrap; justify-content: center; border-radius: 8px; }
    .gold-rates span { font-weight: 700; color: #1a365d; font-size: 13px; }
    
    .inv-table-wrap { border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 20px; }
    table { width: 100%; border-collapse: collapse; }
    th { background: linear-gradient(135deg, #2c5282 0%, #1a365d 100%); color: #fff; padding: 10px 12px; text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
    td { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; }
    tbody tr:nth-child(even) { background: #f8fafc; }
    tbody tr:hover { background: #edf2f7; }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    
    .inv-summary { background: linear-gradient(135deg, #f8fafc 0%, #edf2f7 100%); padding: 18px 24px; border-radius: 10px; border: 1px solid #e2e8f0; margin-bottom: 20px; }
    .summary-row { display: flex; justify-content: space-between; padding: 8px 0; align-items: center; }
    .summary-row.highlight { background: linear-gradient(135deg, #1a365d 0%, #2c5282 100%); margin: 8px -24px -18px -24px; padding: 14px 24px; border-radius: 0 0 10px 10px; color: #fff; font-weight: 700; font-size: 15px; }
    .summary-label { font-weight: 600; color: #4a5568; }
    .summary-row.highlight .summary-label { color: #fff; }
    
    .inv-footer { margin-top: 24px; padding-top: 20px; border-top: 2px solid #e2e8f0; }
    .amount-words { background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); padding: 12px 16px; border-radius: 8px; border-left: 4px solid #d4af37; font-style: italic; margin-bottom: 12px; font-weight: 500; }
    .inv-signature { margin-top: 30px; text-align: right; }
    .inv-signature-line { border-top: 2px solid #1a365d; width: 180px; margin-left: auto; padding-top: 8px; font-size: 11px; color: #718096; }
    
    .invoice-btns { margin-top: 24px; display: flex; gap: 12px; }
    .invoice-btns a, .invoice-btns button { padding: 12px 24px; background: linear-gradient(135deg, #1a365d 0%, #2c5282 100%); color: #fff; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; font-size: 14px; font-weight: 600; box-shadow: 0 4px 12px rgba(26,54,93,0.3); }
    .invoice-btns a:hover, .invoice-btns button:hover { background: linear-gradient(135deg, #2c5282 0%, #1a365d 100%); transform: translateY(-1px); box-shadow: 0 6px 16px rgba(26,54,93,0.4); }
    .invoice-btns a:last-child { background: linear-gradient(135deg, #d4af37 0%, #c9a227 100%); color: #1a365d; box-shadow: 0 4px 12px rgba(212,175,55,0.3); }
    .invoice-btns a:last-child:hover { background: linear-gradient(135deg, #f4e4a6 0%, #d4af37 100%); }
    .inv-lang-secondary { font-size: 0.85em; opacity: 0.95; margin-top: 2px; }
    .inv-lang-secondary.rtl { direction: rtl; text-align: right; }
    th .inv-lang-secondary, .inv-customer .label .inv-lang-secondary { display: block; }
    .inv-th-primary { display: block; }
    .inv-th-secondary { display: block; margin-top: 2px; }
  </style>
  <?php if (function_exists('getInvoicePrintTemplateCss')): ?>
  <style><?php echo getInvoicePrintTemplateCss($design_template); ?></style>
  <?php endif; ?>
</head>
<body<?php if (function_exists('invoicePrintBodyPaddingAttr')) echo invoicePrintBodyPaddingAttr($print_settings); ?>>
<?php include(dirname(__FILE__) . '/invoice_templates/' . $template . '.php'); ?>
</body>
</html>
