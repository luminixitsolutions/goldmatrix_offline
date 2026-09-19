<?php
session_start();
require_once 'config.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);

$voucher_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($voucher_id <= 0) {
    die('Invalid voucher ID');
}

$voucher = getRecord("SELECT * FROM tbl_payment_vouchers WHERE id = $voucher_id");
if (!$voucher) {
    die('Payment voucher not found');
}

$items = getList("
    SELECT pvi.*,
        COALESCE(p.name, '') AS product_name,
        COALESCE(m.display_name, m.system_name, '') AS metal_name
    FROM tbl_payment_voucher_items pvi
    LEFT JOIN tbl_products p ON pvi.product_id = p.id
    LEFT JOIN tbl_metal m ON pvi.metal_id = m.id
    WHERE pvi.voucher_id = $voucher_id
    ORDER BY pvi.id ASC
");

$document_type = 'payment_voucher';
$defs = function_exists('getInvoicePrintSettingsDefaults') ? getInvoicePrintSettingsDefaults() : [];
$ps = function_exists('getInvoicePrintSettingsForDocument') ? getInvoicePrintSettingsForDocument($document_type) : [];
$print_settings = is_array($ps) && !empty($ps) ? array_merge($defs, $ps) : $defs;

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

$currency = !empty($voucher['currency']) ? $voucher['currency'] : 'USD';
$vdate = !empty($voucher['voucher_date']) ? date('d-m-Y', strtotime($voucher['voucher_date'])) : '';

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo h($voucher['voucher_no'] ?? 'PV'); ?> — Payment Voucher</title>
    <style>
        <?php if (function_exists('getInvoicePrintLayoutInlineCss')) {
            echo getInvoicePrintLayoutInlineCss($layout_type, $page_orientation);
        } ?>
        body { font-family: Roboto, 'Segoe UI', sans-serif; margin: 0; padding: 24px; color: #1e293b; background: #f8fafc; }
        .invoice { max-width: 210mm; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .inv-header { background: linear-gradient(135deg, #1a365d 0%, #2c5282 50%, #1a365d 100%); color: #fff; padding: 24px 28px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
        .inv-header-left { display: flex; align-items: center; gap: 20px; }
        .inv-logo { width: 70px; height: 70px; object-fit: contain; background: #fff; border-radius: 10px; padding: 6px; }
        .inv-logo-placeholder { width: 70px; height: 70px; background: linear-gradient(135deg, #d4af37 0%, #f4e4a6 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: bold; color: #1a365d; }
        .inv-company h1 { margin: 0; font-size: 26px; font-weight: 700; }
        .inv-company .tagline { margin: 4px 0 0 0; font-size: 11px; opacity: 0.9; }
        .inv-trn { background: rgba(255,255,255,0.15); padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 600; }
        .inv-tax-badge { background: linear-gradient(135deg, #d4af37 0%, #c9a227 100%); color: #1a365d; text-align: center; padding: 12px; font-size: 18px; font-weight: 700; }
        .sheet { padding: 24px 28px 32px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 24px; font-size: 14px; margin-bottom: 24px; }
        .grid div { border-bottom: 1px solid #f1f5f9; padding: 6px 0; }
        .grid strong { color: #475569; display: inline-block; min-width: 120px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { background: #11294b; color: #fff; text-align: left; padding: 10px 8px; }
        td { border-bottom: 1px solid #e2e8f0; padding: 8px; vertical-align: top; }
        tfoot td { font-weight: 600; background: #f8fafc; }
        .no-print { margin-bottom: 16px; }
        .btn { background: #11294b; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .comment { margin-top: 20px; padding: 12px; background: #f8fafc; border-radius: 6px; font-size: 14px; }
        .inv-footer { margin-top: 24px; padding-top: 16px; border-top: 2px solid #e2e8f0; font-size: 13px; }
        .inv-signature { margin-top: 24px; text-align: right; }
        .inv-signature-line { border-top: 2px solid #1a365d; width: 200px; margin-left: auto; padding-top: 8px; font-size: 11px; color: #718096; }
        @media print {
            body { background: #fff; padding: 0; }
            .no-print { display: none !important; }
            .invoice { box-shadow: none; }
        }
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
        <?php if (($print_settings['header_section_enabled'] ?? '1') === '1' && (($print_settings['header_company_logo'] ?? '1') === '1' || ($print_settings['header_company_name'] ?? '1') === '1' || ($print_settings['header_gst_number'] ?? '1') === '1')): ?>
        <div class="inv-header">
            <div class="inv-header-left">
                <?php if (($print_settings['header_company_logo'] ?? '1') === '1'): ?>
                    <?php if (!empty($has_logo)): ?>
                    <img src="<?php echo h($company_logo); ?>" alt="" class="inv-logo">
                    <?php else: ?>
                    <div class="inv-logo-placeholder"><?php
                        $words = preg_split('/\s+/', trim($company_name), 2);
                        echo strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : substr($words[0], 1, 1)));
                    ?></div>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (($print_settings['header_company_name'] ?? '1') === '1'): ?>
                <div class="inv-company">
                    <h1><?php echo h($company_name); ?></h1>
                    <?php if (($print_settings['header_phone'] ?? '1') === '1'): ?>
                    <p class="tagline"><?php echo h($company_address); ?><?php if ($company_phone !== ''): ?> &bull; <?php echo h($company_phone); ?><?php endif; ?><?php if ($company_email !== ''): ?> &bull; <?php echo h($company_email); ?><?php endif; ?></p>
                    <?php else: ?>
                    <p class="tagline"><?php echo h($company_address); ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php if (($print_settings['header_gst_number'] ?? '1') === '1' && $company_trn !== ''): ?>
            <div class="inv-trn">TRN: <?php echo h($company_trn); ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (($print_settings['header_invoice_title'] ?? '1') === '1'): ?>
        <div class="inv-tax-badge"><?php echo h(!empty($print_settings['invoice_title']) ? $print_settings['invoice_title'] : 'Payment Voucher'); ?> — <?php echo h($voucher['voucher_no'] ?? ''); ?></div>
        <?php endif; ?>

        <div class="sheet">
            <?php if (($print_settings['header_company_name'] ?? '1') !== '1'): ?>
            <h1 style="margin: 0 0 8px; font-size: 22px; color: #11294b;"><?php echo h($company_name); ?></h1>
            <p style="color:#64748b;font-size:14px;margin-bottom:24px;">Payment Voucher — <?php echo h($voucher['voucher_no'] ?? ''); ?></p>
            <?php endif; ?>

            <div class="grid">
                <div><strong>Date</strong> <?php echo h($vdate); ?></div>
                <div><strong>Customer</strong> <?php echo h($voucher['customer_name'] ?? ''); ?></div>
                <?php if (!empty($voucher['ref_no'])): ?>
                <div><strong>Ref. No.</strong> <?php echo h($voucher['ref_no']); ?></div>
                <?php endif; ?>
                <?php if (!empty($voucher['sales_person'])): ?>
                <div><strong>Sales person</strong> <?php echo h($voucher['sales_person']); ?></div>
                <?php endif; ?>
                <div><strong>Currency</strong> <?php echo h($currency); ?></div>
                <?php if (!empty($voucher['voucher_type'])): ?>
                <div><strong>Voucher type</strong> <?php echo h($voucher['voucher_type']); ?></div>
                <?php endif; ?>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Payment type</th>
                        <th>Deposit into / details</th>
                        <th style="text-align:right">Amount (<?php echo h($currency); ?>)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $i = 0;
                    foreach ($items as $row) {
                        $i++;
                        $ptype = $row['payment_type'] ?? '';
                        $detail_parts = array_filter([
                            $row['deposit_into'] ?? '',
                            $row['transaction_no'] ?? '',
                            $row['diamond_category'] ?? '',
                            !empty($row['product_name']) ? $row['product_name'] : '',
                            !empty($row['metal_name']) ? $row['metal_name'] : '',
                            ($row['purity_carat'] ?? '') !== '' ? 'Purity: ' . $row['purity_carat'] : '',
                            (float)($row['purity_wt'] ?? 0) > 0 ? 'Wt: ' . number_format((float)$row['purity_wt'], 3) : '',
                        ]);
                        $details = implode(' · ', $detail_parts);
                        $amt = (float)($row['amount'] ?? 0);
                        ?>
                    <tr>
                        <td><?php echo $i; ?></td>
                        <td><?php echo h($ptype); ?></td>
                        <td><?php echo h($details); ?></td>
                        <td style="text-align:right"><?php echo $ptype === 'Metal' && $amt <= 0 ? '—' : number_format($amt, 2); ?></td>
                    </tr>
                    <?php } ?>
                    <?php if (empty($items)): ?>
                    <tr><td colspan="4" style="text-align:center;color:#94a3b8;">No line items</td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" style="text-align:right">Total amount</td>
                        <td style="text-align:right"><?php echo number_format((float)($voucher['total_amount'] ?? 0), 2); ?></td>
                    </tr>
                    <?php if ((float)($voucher['total_gold'] ?? 0) != 0 || (float)($voucher['total_silver'] ?? 0) != 0): ?>
                    <tr>
                        <td colspan="3" style="text-align:right">Metal (Gold / Silver wt.)</td>
                        <td style="text-align:right"><?php echo number_format((float)($voucher['total_gold'] ?? 0), 3); ?> / <?php echo number_format((float)($voucher['total_silver'] ?? 0), 3); ?></td>
                    </tr>
                    <?php endif; ?>
                </tfoot>
            </table>

            <?php if (!empty($voucher['comment'])): ?>
            <div class="comment"><strong>Comment</strong><br><?php echo nl2br(h($voucher['comment'])); ?></div>
            <?php endif; ?>

            <div class="inv-footer">
                <?php if (($print_settings['footer_terms_conditions'] ?? '1') === '1' && !empty($print_settings['terms_conditions'])): ?>
                <div style="margin-bottom: 12px;"><?php echo nl2br(h($print_settings['terms_conditions'])); ?></div>
                <?php endif; ?>
                <?php if (($print_settings['footer_thank_you_message'] ?? '1') === '1'): ?>
                <div style="font-weight: 600; color: #1a365d;"><?php echo !empty($print_settings['thank_you_message']) ? h($print_settings['thank_you_message']) : 'Thank you for your business.'; ?></div>
                <?php endif; ?>
                <?php if (($print_settings['footer_authorized_signature'] ?? '1') === '1'): ?>
                <div class="inv-signature">
                    <div class="inv-signature-line"><?php echo !empty($print_settings['authorized_signature']) ? h($print_settings['authorized_signature']) : 'Authorized Signature'; ?></div>
                    <div style="font-size: 12px; font-weight: 600; color: #1a365d; margin-top: 4px;"><?php echo h($company_name); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
