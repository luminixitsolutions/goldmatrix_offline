<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/includes/invoice-print-currency-symbol.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);

$return_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$lang = isset($_GET['lang']) ? strtolower(trim($_GET['lang'])) : 'en';
$allowed_langs = function_exists('getInvoicePrintAllowedLanguages') ? getInvoicePrintAllowedLanguages('purchase_return') : ['en'];
if (!in_array($lang, $allowed_langs, true)) $lang = 'en';

if ($return_id <= 0) {
    die('Invalid return ID');
}

$return = @getRecord("SELECT * FROM tbl_purchase_returns WHERE id = $return_id");
if (!$return) die('Purchase return not found');

$items = getList("SELECT pri.*, COALESCE(p.name, pri.product_name) as product_name FROM tbl_purchase_return_items pri LEFT JOIN tbl_products p ON pri.product_id = p.id WHERE pri.return_id = $return_id ORDER BY pri.id ASC");

// Print settings for this document type (fallback to default)
$print_settings = function_exists('getInvoicePrintSettingsForDocument') ? getInvoicePrintSettingsForDocument('purchase_return') : [];
$defs = function_exists('getInvoicePrintSettingsDefaults') ? getInvoicePrintSettingsDefaults() : [];
$print_settings = is_array($print_settings) && !empty($print_settings) ? array_merge($defs, $print_settings) : $defs;
$layout_type = isset($print_settings['layout_type']) ? normalizeInvoicePrintLayoutType($print_settings['layout_type']) : 'A4';
$page_orientation = isset($print_settings['page_orientation']) ? normalizeInvoicePrintPageOrientation($print_settings['page_orientation']) : 'portrait';
$design_template = $print_settings['design_template'] ?? 'template_1';

$company_name = defined('COMPANY_NAME') ? COMPANY_NAME : (isset($Proj_Title) ? $Proj_Title : 'Gold Matrix');
$company_trn = defined('COMPANY_TRN') ? COMPANY_TRN : '';
$company_address = defined('COMPANY_ADDRESS') ? COMPANY_ADDRESS : '';
$company_phone = defined('COMPANY_PHONE') ? COMPANY_PHONE : '';
$company_email = defined('COMPANY_EMAIL') ? COMPANY_EMAIL : '';
$company_logo = defined('COMPANY_LOGO') ? COMPANY_LOGO : 'assets/img/logo.png';
if (!empty($print_settings['company_name'])) $company_name = $print_settings['company_name'];
if (!empty($print_settings['company_address'])) $company_address = $print_settings['company_address'];
if (!empty($print_settings['company_gst'])) $company_trn = $print_settings['company_gst'];
if (isset($print_settings['company_phone'])) $company_phone = $print_settings['company_phone'];
if (isset($print_settings['company_email'])) $company_email = $print_settings['company_email'];
if (!empty($print_settings['company_logo_path'])) {
    $company_logo = $print_settings['company_logo_path'];
}
$logo_path = dirname(__FILE__) . '/' . $company_logo;
$has_logo = file_exists($logo_path);

$return_date = !empty($return['return_date']) ? date('d/m/Y', strtotime($return['return_date'])) : '';
$return_no = $return['return_no'] ?? '';
$grand_total = (float)($return['grand_total'] ?? 0);
$currency = !empty($return['currency']) ? trim((string) $return['currency']) : 'AED';
$doc_title = !empty($print_settings['invoice_title']) ? $print_settings['invoice_title'] : 'PURCHASE RETURN';
$currency_symbol = invoice_print_currency_symbol(isset($conn) ? $conn : null, $currency);
$return_time = !empty($return['return_date']) ? date('h:i A', strtotime($return['return_date'])) : '';
function numberToWordsReturn($num, $currency = 'AED') {
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
$amount_words = numberToWordsReturn($grand_total, $currency);
$t = ['invoice_no' => 'Return No.', 'date' => 'Date', 'salesman' => 'Purchase Person', 'mr_ms' => 'Supplier', 'customer_no' => 'Ref', 'passport_id' => 'ID', 'sr' => '#', 'description' => 'Description', 'weight' => 'Weight', 'stone_weight' => 'Stone', 'net_wt' => 'Net Wt', 'net_amt' => 'Net Amt', 'vat_5' => 'VAT', 'total_amount' => 'Amount', 'total' => 'Total', 'advance_amount' => 'Advance', 'total_before_vat' => 'Before VAT', 'vat_5_label' => 'VAT 5%', 'total_including_vat' => 'Incl. VAT', 'less_scrap' => 'Less Scrap', 'balance_amount' => 'Balance', 'amount_in_words' => 'Amount in Words', 'card' => 'Card', 'comment' => 'Comment', 'tax_invoice' => 'PURCHASE RETURN'];
$t2 = [];
$col_labels_secondary = [];
$invoice_secondary = '';
$bilingual = false;
$selected_columns = ['sr_no', 'item_name', 'amount'];
$col_labels = ['sr_no' => $t['sr'], 'item_name' => $t['description'], 'amount' => $t['total_amount']];
$item_rows = [];
$sr = 0;
foreach ($items as $it) {
    $sr++;
    $item_rows[] = ['sr_no' => $sr, 'item_name' => $it['product_name'] ?? 'Item', 'amount' => number_format((float)($it['net_amount'] ?? $it['amount'] ?? 0), 2)];
}
$gold_rates = [];
$subtotal = $grand_total;
$discount_amt = 0;
$additional_amt = 0;
$tax_amount = 0;
$advance_amt = 0;
$total_before_vat = $grand_total;
$scrap_amt = 0;
$balance_amt = $grand_total;
$total_weight = 0;
$total_stone_weight = 0;
$total_net_wt = 0;
$payment_totals = ['cash' => 0, 'bank' => 0, 'cheque' => 0, 'upi' => 0, 'card' => 0, 'metal_exchange' => 0, 'scrap' => 0];
$doc_no = $return_no;
$doc_date = $return_date;
$doc_time = $return_time;
$party_name = $return['supplier_name'] ?? '';
$party_ref = '';
$person_label = $t['salesman'];
$person_value = $return['purchase_person'] ?? '';
$doc_comment = $return['comment'] ?? '';
$back_url = 'purchase-return.php';
$document = $return;
$template = isset($_GET['template_preview']) ? trim((string)$_GET['template_preview']) : (function_exists('getInvoiceTemplateForDocument') ? getInvoiceTemplateForDocument('purchase_return') : 'template_classic');
$structure_templates = function_exists('getInvoicePrintStructureTemplates') ? array_keys(getInvoicePrintStructureTemplates()) : ['template_classic'];
if (!in_array($template, $structure_templates, true)) {
    $template = 'template_classic';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Purchase Return - <?php echo htmlspecialchars($return_no); ?></title>
  <style>
    <?php if (function_exists('getInvoicePrintLayoutInlineCss')) echo getInvoicePrintLayoutInlineCss($layout_type, $page_orientation); ?>

    * { box-sizing: border-box; }
    body { font-family: Roboto, 'Segoe UI', Tahoma, Arial, sans-serif; font-size: 12px; margin: 0; padding: 15px; color: #2d3748; background: #f7fafc; }
    .invoice { max-width: 210mm; margin: 0 auto; background: #fff; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border-radius: 12px; overflow: hidden; }
    .invoice.invoice-thermal { max-width: 80mm; font-size: 10px; padding: 8px; }
    .inv-header { background: linear-gradient(135deg, #1a365d 0%, #2c5282 100%); color: #fff; padding: 24px 28px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
    .inv-header-left { display: flex; align-items: center; gap: 20px; }
    .inv-logo { width: 70px; height: 70px; object-fit: contain; background: #fff; border-radius: 10px; padding: 6px; }
    .inv-company h1 { margin: 0; font-size: 26px; font-weight: 700; }
    .inv-tax-badge { background: linear-gradient(135deg, #d4af37 0%, #c9a227 100%); color: #1a365d; text-align: center; padding: 12px; font-size: 18px; font-weight: 700; }
    .inv-body { padding: 24px 28px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
    th { background: #2c5282; color: #fff; }
    .text-right { text-align: right; }
    .inv-footer { margin-top: 24px; padding-top: 20px; border-top: 2px solid #e2e8f0; }
    .inv-signature { margin-top: 20px; text-align: right; }
    .invoice-btns { margin-top: 24px; display: flex; gap: 12px; }
    .invoice-btns a { padding: 12px 24px; background: #1a365d; color: #fff; border-radius: 8px; text-decoration: none; font-weight: 600; }
    @media print { body { padding: 0; background: #fff; } .invoice { box-shadow: none; } .no-print { display: none !important; } }
  </style>
  <?php if (function_exists('getInvoicePrintTemplateCss')): ?>
  <style><?php echo getInvoicePrintTemplateCss($design_template); ?></style>
  <?php endif; ?>
</head>
<body<?php if (function_exists('invoicePrintBodyPaddingAttr')) echo invoicePrintBodyPaddingAttr($print_settings); ?>>
<?php include(dirname(__FILE__) . '/invoice_templates/' . $template . '.php'); ?>
</body>
</html>
