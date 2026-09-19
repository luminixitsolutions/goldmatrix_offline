<?php
/**
 * Print view for Metal ↔ Amount utility vouchers (tbl_metal_amount_conversions).
 * Print layout uses invoice print settings: metal_to_amount | amount_to_metal.
 */
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auragold_require_login.php';
auragold_require_login_or_exit();
require_once __DIR__ . '/includes/ensure_metal_amount_conversion.php';

$conv_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($conv_id <= 0) {
    die('Invalid document');
}
if (!empty($conn) && $conn instanceof mysqli) {
    auragold_ensure_metal_amount_conversion_table($conn);
}

$row = getRecord('SELECT * FROM tbl_metal_amount_conversions WHERE id = ' . (int) $conv_id . ' AND status = 1 LIMIT 1');
if (!$row) {
    die('Record not found');
}

$dir = (string) ($row['direction'] ?? '');
$document_type = $dir === 'amount_to_metal' ? 'amount_to_metal' : 'metal_to_amount';
$defs = function_exists('getInvoicePrintSettingsDefaults') ? getInvoicePrintSettingsDefaults() : [];
$ps = function_exists('getInvoicePrintSettingsForDocument') ? getInvoicePrintSettingsForDocument($document_type) : [];
$print_settings = is_array($ps) && !empty($ps) ? array_merge($defs, $ps) : $defs;

$title_default = function_exists('getInvoicePrintDefaultDocumentTitle') ? getInvoicePrintDefaultDocumentTitle($document_type) : 'VOUCHER';
$doc_label = $dir === 'amount_to_metal' ? 'Amount to Metal' : 'Metal to Amount';

$company_name = defined('COMPANY_NAME') ? COMPANY_NAME : (isset($Proj_Title) ? $Proj_Title : 'Gold Matrix');
$company_trn = defined('COMPANY_TRN') ? COMPANY_TRN : '';
$company_address = defined('COMPANY_ADDRESS') ? COMPANY_ADDRESS : '';
$company_phone = defined('COMPANY_PHONE') ? COMPANY_PHONE : '';
$company_email = defined('COMPANY_EMAIL') ? COMPANY_EMAIL : '';
$company_logo = defined('COMPANY_LOGO') ? COMPANY_LOGO : 'assets/img/logo.png';
if (!empty($print_settings['company_name'])) {
    $company_name = $print_settings['company_name'];
}
if (!empty($print_settings['company_address'])) {
    $company_address = $print_settings['company_address'];
}
if (!empty($print_settings['company_gst'])) {
    $company_trn = $print_settings['company_gst'];
}
if (isset($print_settings['company_phone'])) {
    $company_phone = $print_settings['company_phone'];
}
if (isset($print_settings['company_email'])) {
    $company_email = $print_settings['company_email'];
}
if (!empty($print_settings['company_logo_path'])) {
    $company_logo = $print_settings['company_logo_path'];
}
$logo_path = dirname(__FILE__) . '/' . $company_logo;
$has_logo = file_exists($logo_path);
$layout_type = isset($print_settings['layout_type']) ? normalizeInvoicePrintLayoutType($print_settings['layout_type']) : 'A4';
$page_orientation = isset($print_settings['page_orientation']) ? normalizeInvoicePrintPageOrientation($print_settings['page_orientation']) : 'portrait';
$design_template = $print_settings['design_template'] ?? 'template_1';
$vno = (string) ($row['trans_no'] ?? '');
$vdate = !empty($row['trans_date']) ? date('d-m-Y H:i', strtotime((string) $row['trans_date'])) : '';
$metal = strtoupper((string) ($row['metal_type'] ?? ''));
$wt = (float) ($row['metal_weight'] ?? 0);
$rate = (float) ($row['rate'] ?? 0);
$amount = (float) ($row['amount'] ?? 0);
$comment = trim((string) ($row['comment'] ?? ''));

function h($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
$inv_title = $print_settings['invoice_title'] ?? '';
$badge_title = $inv_title !== '' ? $inv_title : $title_default;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo h($vno !== '' ? $vno : $doc_label); ?></title>
    <style>
        <?php if (function_exists('getInvoicePrintLayoutInlineCss')) {
            echo getInvoicePrintLayoutInlineCss($layout_type, $page_orientation);
        } ?>
        body { font-family: Roboto, 'Segoe UI', sans-serif; margin: 0; padding: 24px; color: #1e293b; background: #f8fafc; }
        .invoice { max-width: 210mm; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .inv-header { background: linear-gradient(135deg, #1a365d 0%, #2c5282 50%, #1a365d 100%); color: #fff; padding: 20px 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
        .inv-header-left { display: flex; align-items: center; gap: 16px; }
        .inv-logo { width: 64px; height: 64px; object-fit: contain; background: #fff; border-radius: 8px; padding: 4px; }
        .inv-logo-placeholder { width: 64px; height: 64px; background: linear-gradient(135deg, #d4af37 0%, #f4e4a6 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: bold; color: #1a365d; }
        .inv-company h1 { margin: 0; font-size: 22px; }
        .inv-trn { background: rgba(255,255,255,0.15); padding: 6px 12px; border-radius: 6px; font-size: 12px; }
        .inv-tax-badge { background: linear-gradient(135deg, #c5a864 0%, #a8894a 100%); color: #11294b; text-align: center; padding: 12px; font-size: 17px; font-weight: 700; }
        .sheet { padding: 20px 24px 28px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 20px; font-size: 14px; margin-bottom: 20px; }
        .grid div { border-bottom: 1px solid #f1f5f9; padding: 6px 0; }
        .grid strong { color: #64748b; min-width: 100px; display: inline-block; }
        .mac-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .mac-table th { background: #11294b; color: #fff; text-align: left; padding: 10px; }
        .mac-table td { border-bottom: 1px solid #e2e8f0; padding: 10px; }
        .mac-table td.num { text-align: right; }
        .no-print { margin-bottom: 14px; }
        .btn { background: #11294b; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .comment { margin-top: 16px; padding: 10px; background: #f8fafc; border-radius: 6px; }
        @media print { body { background: #fff; padding: 0; } .no-print { display: none; } .invoice { box-shadow: none; } }
    </style>
    <?php if (function_exists('getInvoicePrintTemplateCss')): ?>
    <style><?php echo getInvoicePrintTemplateCss($design_template); ?></style>
    <?php endif; ?>
</head>
<body<?php if (function_exists('invoicePrintBodyPaddingAttr')) echo invoicePrintBodyPaddingAttr($print_settings); ?>>
    <div class="no-print">
        <button type="button" class="btn" onclick="window.print()">Print</button>
    </div>
    <div class="invoice <?php echo h($design_template); ?>">
        <?php if (($print_settings['header_section_enabled'] ?? '1') === '1'): ?>
        <div class="inv-header">
            <div class="inv-header-left">
                <?php if (($print_settings['header_company_logo'] ?? '1') === '1'): ?>
                    <?php if (!empty($has_logo)): ?>
                    <img src="<?php echo h($company_logo); ?>" alt="" class="inv-logo">
                    <?php else: ?>
                    <div class="inv-logo-placeholder">G</div>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (($print_settings['header_company_name'] ?? '1') === '1'): ?>
                <div class="inv-company">
                    <h1><?php echo h($company_name); ?></h1>
                    <p style="margin:4px 0 0;font-size:12px;opacity:0.9;"><?php echo h($company_address); ?><?php if ($company_phone): ?> · <?php echo h($company_phone); ?><?php endif; ?></p>
                </div>
                <?php endif; ?>
            </div>
            <?php if (($print_settings['header_gst_number'] ?? '1') === '1' && $company_trn !== ''): ?>
            <div class="inv-trn">TRN: <?php echo h($company_trn); ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="inv-tax-badge"><?php echo h($badge_title); ?> — <?php echo h($vno); ?></div>
        <div class="sheet">
            <div class="grid">
                <div><strong>Document</strong> <?php echo h($doc_label); ?></div>
                <div><strong>No.</strong> <?php echo h($vno); ?></div>
                <div><strong>Date</strong> <?php echo h($vdate); ?></div>
                <div><strong>Customer</strong> <?php echo h($row['customer_name'] ?? ''); ?></div>
            </div>
            <table class="mac-table">
                <thead>
                    <tr>
                        <th>Metal</th>
                        <th class="num">Weight / ct</th>
                        <th class="num">Rate</th>
                        <th class="num">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php echo h($metal); ?></td>
                        <td class="num"><?php echo number_format($wt, 4); ?></td>
                        <td class="num"><?php echo number_format($rate, 2); ?></td>
                        <td class="num"><?php echo number_format($amount, 2); ?></td>
                    </tr>
                </tbody>
            </table>
            <?php if ($comment !== ''): ?>
            <div class="comment"><strong>Comment</strong> — <?php echo h($comment); ?></div>
            <?php endif; ?>
            <?php if (($print_settings['footer_authorized_signature'] ?? '1') === '1'): ?>
            <div style="margin-top:28px;text-align:right;border-top:1px solid #e2e8f0;padding-top:10px;">
                <span style="font-size:12px;color:#64748b;"><?php echo h($print_settings['authorized_signature'] ?? 'Authorized Signature'); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
