<?php
/**
 * Template 7 – Export / Manufacturer Invoice (GEM Shop style).
 * Light blue headers, orange title, BILL TO / SHIP TO, DESCRIPTION | HSN | PCS | GMS | PRICE | AMOUNT.
 */
if (!isset($items) || !is_array($items)) {
    $items = [];
}
$print_settings = is_array($print_settings ?? null) ? $print_settings : [];

$h = static function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};

$gs_blue = '#D9E1F2';
$gs_orange = '#F79646';

$company_gstin = trim((string) ($print_settings['company_gst'] ?? ''));
if ($company_gstin === '') {
    $company_gstin = trim((string) ($company_trn ?? ''));
}
$company_pan = trim((string) ($print_settings['company_pan'] ?? ''));
$company_tagline = trim((string) ($print_settings['t7_company_tagline'] ?? ''));
$min_rows = (int) ($print_settings['t7_min_item_rows'] ?? 15);
if ($min_rows < 1) {
    $min_rows = 1;
}
if ($min_rows > 40) {
    $min_rows = 40;
}

$doc_date_gs = $doc_date ?? '';
if (!empty($invoice['invoice_date'])) {
    $doc_date_gs = date('d-M-y', strtotime($invoice['invoice_date']));
} elseif (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', (string) $doc_date_gs, $md)) {
    $doc_date_gs = date('d-M-y', strtotime($md[3] . '-' . $md[2] . '-' . $md[1]));
}

$cust_address_lines = [];
$cust_gstin = '';
if (!empty($invoice['customer_id']) && isset($conn) && $conn) {
    $crow = @getRecord('SELECT * FROM tbl_customers WHERE id = ' . (int) $invoice['customer_id'] . ' LIMIT 1');
    if (is_array($crow)) {
        $addr = trim((string) ($crow['address'] ?? $crow['address_line'] ?? ''));
        if ($addr !== '') {
            $cust_address_lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n|,/', $addr)));
        }
        $city = trim((string) ($crow['city'] ?? ''));
        $state = trim((string) ($crow['state'] ?? ''));
        $pin = trim((string) ($crow['pincode'] ?? $crow['zip'] ?? ''));
        $loc = trim(implode(' ', array_filter([$city, $state, $pin ? '-' . $pin : ''])));
        if ($loc !== '') {
            $cust_address_lines[] = $loc;
        }
        $cust_gstin = trim((string) ($crow['gstin'] ?? $crow['gst_no'] ?? ''));
    }
}
if (empty($cust_address_lines) && !empty($party_name)) {
    $cust_address_lines[] = (string) $party_name;
}

$bank_name = trim((string) ($print_settings['t7_bank_name'] ?? ''));
$bank_account_no = trim((string) ($print_settings['t7_bank_account_no'] ?? ''));
$bank_ifsc = trim((string) ($print_settings['t7_bank_ifsc'] ?? ''));
$bank_account_name = trim((string) ($print_settings['t7_bank_account_name'] ?? $company_name ?? ''));
if ($bank_name === '' && function_exists('auragold_settings_branch_id')) {
    $bid = auragold_settings_branch_id();
    if ($bid > 0) {
        $br = null;
        if (function_exists('getRecordMaster')) {
            $br = getRecordMaster('SELECT bank_name, bank_account_no, bank_ifsc, name FROM tbl_branches WHERE id = ' . (int) $bid . ' LIMIT 1');
        }
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
            if ($bank_account_name === '' || $bank_account_name === ($company_name ?? '')) {
                $acct = trim((string) ($br['name'] ?? ''));
                if ($acct !== '') {
                    $bank_account_name = $acct;
                }
            }
        }
    }
}

$po_number = trim((string) ($invoice['against_of'] ?? ''));
$order_id = trim((string) ($invoice['ref_no'] ?? ''));

$subtotal_gs = (float) ($total_before_vat ?? 0);
if ($subtotal_gs <= 0) {
    $subtotal_gs = (float) ($subtotal ?? 0) - (float) ($discount_amt ?? 0) + (float) ($additional_amt ?? 0);
}
$gst_total = (float) ($tax_amount ?? 0);
$gst_half = $gst_total / 2.0;
$pct_each = 0.0;
if ($subtotal_gs > 0.0001 && $gst_total > 0) {
    $pct_each = ($gst_half / $subtotal_gs) * 100.0;
}
$pct_fmt = rtrim(rtrim(number_format($pct_each, 2, '.', ''), '0'), '.');

$thank_you = trim((string) ($print_settings['thank_you_message'] ?? ''));
if ($thank_you === '') {
    $thank_you = 'Thank you for your business!';
}

$doc_title_gs = trim((string) ($print_settings['invoice_title'] ?? ''));
if ($doc_title_gs === '') {
    $doc_title_gs = 'Invoice';
}

$currency_code = strtoupper(trim((string) ($currency ?? 'INR')));
$gs_money = static function ($n, $dec = 2) use ($h) {
    return $h(number_format((float) $n, $dec, '.', ''));
};

$pad_rows = max(0, $min_rows - count($items));
?>
<style>
.invoice.inv-gemshop {
    font-family: Arial, Helvetica, sans-serif !important;
    font-size: 12px !important;
    color: #000 !important;
    background: #fff !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    max-width: 100% !important;
    margin: 0 auto !important;
    padding: 0 !important;
    overflow: visible !important;
}
.invoice.inv-gemshop .gs-container {
    width: 100%;
    max-width: 210mm;
    margin: 0 auto;
    padding: 12px 14px;
    box-sizing: border-box;
    background: #fff;
}
.invoice.inv-gemshop .gs-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 10px;
}
.invoice.inv-gemshop .gs-company {
    flex: 1;
    min-width: 0;
}
.invoice.inv-gemshop .gs-company-head {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 6px;
}
.invoice.inv-gemshop .gs-logo {
    width: 52px;
    height: 52px;
    object-fit: contain;
}
.invoice.inv-gemshop .gs-logo-ph {
    width: 52px;
    height: 52px;
    background: <?php echo $gs_blue; ?>;
    color: #1a365d;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 18px;
    border: 1px solid #000;
}
.invoice.inv-gemshop .gs-company-name {
    font-size: 22px;
    font-weight: 700;
    color: #2c5282;
    letter-spacing: 0.5px;
    margin: 0;
    line-height: 1.1;
}
.invoice.inv-gemshop .gs-tagline {
    font-size: 10px;
    color: #333;
    margin-top: 2px;
}
.invoice.inv-gemshop .gs-company-lines {
    font-size: 11px;
    line-height: 1.45;
    margin-top: 4px;
}
.invoice.inv-gemshop .gs-company-lines b {
    font-weight: 700;
}
.invoice.inv-gemshop .gs-title-area {
    text-align: right;
    flex-shrink: 0;
}
.invoice.inv-gemshop .gs-invoice-title {
    font-size: 36px;
    font-weight: 700;
    color: <?php echo $gs_orange; ?>;
    line-height: 1;
    margin-bottom: 6px;
    letter-spacing: 0.5px;
}
.invoice.inv-gemshop .gs-meta-table {
    border-collapse: collapse;
    font-size: 11px;
    margin-left: auto;
}
.invoice.inv-gemshop .gs-meta-table th,
.invoice.inv-gemshop .gs-meta-table td {
    border: 1px solid #000;
    padding: 4px 10px;
    text-align: center;
    vertical-align: middle;
}
.invoice.inv-gemshop .gs-meta-table th {
    background: <?php echo $gs_blue; ?>;
    color: #000 !important;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 10px;
}
.invoice.inv-gemshop .gs-meta-table .gs-meta-full {
    text-align: left;
    font-weight: 700;
    background: <?php echo $gs_blue; ?>;
    color: #000 !important;
}
.invoice.inv-gemshop .gs-address-row {
    display: flex;
    gap: 0;
    margin-bottom: 0;
    border: 1px solid #000;
    border-bottom: none;
}
.invoice.inv-gemshop .gs-bill-ship {
    flex: 1;
    min-width: 0;
}
.invoice.inv-gemshop .gs-bill-ship + .gs-bill-ship {
    border-left: 1px solid #000;
}
.invoice.inv-gemshop .gs-box-header {
    background: <?php echo $gs_blue; ?>;
    color: #000 !important;
    font-weight: 700;
    font-size: 11px;
    padding: 5px 8px;
    border-bottom: 1px solid #000;
    text-transform: uppercase;
}
.invoice.inv-gemshop .gs-box-body {
    padding: 8px;
    font-size: 11px;
    line-height: 1.45;
    min-height: 72px;
}
.invoice.inv-gemshop .gs-items-table {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #000;
    margin-bottom: 0;
}
.invoice.inv-gemshop .gs-items-table th,
.invoice.inv-gemshop .gs-items-table td {
    border: 1px solid #000;
    padding: 5px 8px;
    font-size: 11px;
    vertical-align: top;
}
.invoice.inv-gemshop .gs-items-table th {
    background: <?php echo $gs_blue; ?>;
    color: #000 !important;
    font-weight: 700;
    text-transform: uppercase;
    text-align: center;
    font-size: 10px;
}
.invoice.inv-gemshop .gs-items-table td.gs-desc {
    text-align: left;
}
.invoice.inv-gemshop .gs-items-table td.gs-num {
    text-align: center;
}
.invoice.inv-gemshop .gs-items-table td.gs-amt {
    text-align: right;
}
.invoice.inv-gemshop .gs-items-table tr.gs-pad-row td {
    height: 20px;
}
.invoice.inv-gemshop .gs-thanks {
    font-style: italic;
    font-size: 11px;
    padding: 8px 4px 10px;
    border-left: 1px solid #000;
    border-right: 1px solid #000;
}
.invoice.inv-gemshop .gs-footer-row {
    display: flex;
    align-items: flex-start;
    border: 1px solid #000;
    border-top: none;
}
.invoice.inv-gemshop .gs-bank,
.invoice.inv-gemshop .gs-account {
    flex: 1;
    padding: 8px 10px;
    font-size: 11px;
    line-height: 1.5;
    min-height: 80px;
}
.invoice.inv-gemshop .gs-account {
    border-left: 1px solid #000;
    text-align: center;
}
.invoice.inv-gemshop .gs-totals {
    width: 220px;
    flex-shrink: 0;
    border-left: 1px solid #000;
}
.invoice.inv-gemshop .gs-totals table {
    width: 100%;
    border-collapse: collapse;
}
.invoice.inv-gemshop .gs-totals td {
    padding: 5px 8px;
    font-size: 11px;
    border-bottom: 1px solid #000;
}
.invoice.inv-gemshop .gs-totals td:first-child {
    font-weight: 700;
    text-transform: uppercase;
    text-align: left;
}
.invoice.inv-gemshop .gs-totals td:last-child {
    text-align: right;
}
.invoice.inv-gemshop .gs-totals tr.gs-total-row td {
    background: <?php echo $gs_blue; ?>;
    color: #000 !important;
    font-weight: 700;
    border-bottom: none;
}
.invoice.inv-gemshop .gs-totals tr.gs-total-row td:first-child {
    text-transform: none;
}
@media print {
    .invoice.inv-gemshop .gs-container { padding: 0; }
}
</style>
<div class="invoice inv-gemshop template_7">
<div class="gs-container">

    <div class="gs-top">
        <div class="gs-company">
            <div class="gs-company-head">
                <?php if (($print_settings['header_company_logo'] ?? '1') === '1'): ?>
                    <?php if (!empty($has_logo)): ?>
                    <img src="<?php echo $h($company_logo); ?>" alt="" class="gs-logo">
                    <?php else: ?>
                    <div class="gs-logo-ph"><?php
                        $words = preg_split('/\s+/', trim($company_name ?? ''), 2);
                        echo $h(strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : substr($words[0], 1, 1))));
                    ?></div>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (($print_settings['header_company_name'] ?? '1') === '1'): ?>
                <div>
                    <h1 class="gs-company-name"><?php echo $h($company_name ?? ''); ?></h1>
                    <?php if ($company_tagline !== ''): ?>
                    <div class="gs-tagline"><?php echo $h($company_tagline); ?></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="gs-company-lines">
                <?php if (!empty($company_address)): ?>
                <?php foreach (array_filter(array_map('trim', preg_split('/\r\n|\r|\n|,/', $company_address))) as $line): ?>
                <?php echo $h($line); ?><br>
                <?php endforeach; ?>
                <?php endif; ?>
                <?php if (!empty($company_email)): ?>E-mail: <?php echo $h($company_email); ?><br><?php endif; ?>
                <?php if ($company_pan !== ''): ?><b>TIN NO. : <?php echo $h($company_pan); ?></b><br><?php endif; ?>
                <?php if (($print_settings['header_gst_number'] ?? '1') === '1' && $company_gstin !== ''): ?>
                <b>GST NO(ARN NO)-<?php echo $h($company_gstin); ?></b>
                <?php endif; ?>
            </div>
        </div>
        <div class="gs-title-area">
            <?php if (($print_settings['header_invoice_title'] ?? '1') === '1'): ?>
            <div class="gs-invoice-title"><?php echo $h($doc_title_gs); ?></div>
            <?php endif; ?>
            <table class="gs-meta-table">
                <tr>
                    <th>INVOICE #</th>
                    <th>DATE</th>
                </tr>
                <tr>
                    <td><?php echo $h($doc_no ?? ''); ?></td>
                    <td><?php echo $h($doc_date_gs); ?></td>
                </tr>
                <tr>
                    <td colspan="2" class="gs-meta-full">PO NUMBER</td>
                </tr>
                <tr>
                    <td colspan="2"><?php echo $po_number !== '' ? $h($po_number) : '&nbsp;'; ?></td>
                </tr>
                <tr>
                    <td colspan="2" class="gs-meta-full">Order ID</td>
                </tr>
                <tr>
                    <td colspan="2"><?php echo $order_id !== '' ? $h($order_id) : '&nbsp;'; ?></td>
                </tr>
            </table>
        </div>
    </div>

    <div class="gs-address-row">
        <div class="gs-bill-ship">
            <div class="gs-box-header">BILL TO</div>
            <div class="gs-box-body">
                <strong><?php echo $h($party_name ?? ''); ?></strong><br>
                <?php foreach ($cust_address_lines as $line): ?>
                <?php echo $h($line); ?><br>
                <?php endforeach; ?>
                <?php if ($cust_gstin !== ''): ?><br><strong>GST NO-(<?php echo $h($cust_gstin); ?></strong><?php endif; ?>
            </div>
        </div>
        <div class="gs-bill-ship">
            <div class="gs-box-header">SHIP TO</div>
            <div class="gs-box-body">
                <strong><?php echo $h($party_name ?? ''); ?></strong><br>
                <?php foreach ($cust_address_lines as $line): ?>
                <?php echo $h($line); ?><br>
                <?php endforeach; ?>
                <?php if ($cust_gstin !== ''): ?><br><strong>GST NO-(ARN NO)- <?php echo $h($cust_gstin); ?></strong><?php endif; ?>
            </div>
        </div>
    </div>

    <table class="gs-items-table">
        <thead>
            <tr>
                <th style="width:38%;">DESCRIPTION</th>
                <th style="width:12%;">HSN Code</th>
                <th style="width:8%;">PCS</th>
                <th style="width:10%;">GMS</th>
                <th style="width:14%;">PRICE</th>
                <th style="width:18%;">AMOUNT</th>
            </tr>
        </thead>
        <tbody>
        <?php
        foreach ($items as $it) {
            $pname = !empty($it['product_name']) ? $it['product_name'] : ('Product #' . ($it['product_id'] ?? ''));
            $hsn = trim((string) ($it['hsn'] ?? $it['hsn_code'] ?? $it['barcode'] ?? ''));
            $pcs = (float) ($it['quantity'] ?? 1);
            $gms = (float) ($it['net_weight'] ?? $it['final_weight'] ?? $it['gross_weight'] ?? 0);
            $rate = (float) ($it['rate'] ?? 0);
            $line_amt = (float) ($it['net_amount'] ?? $it['amount'] ?? 0);
            if ($line_amt <= 0) {
                $line_amt = (float) ($it['net_amt_with_tax'] ?? 0) - (float) ($it['tax_amount'] ?? 0);
            }
            ?>
            <tr>
                <td class="gs-desc"><?php echo $h($pname); ?></td>
                <td class="gs-num"><?php echo $hsn !== '' ? $h($hsn) : '&nbsp;'; ?></td>
                <td class="gs-num"><?php echo $pcs > 0 ? $h(rtrim(rtrim(number_format($pcs, 2, '.', ''), '0'), '.')) : '0'; ?></td>
                <td class="gs-num"><?php echo $gms > 0 ? $h(number_format($gms, 0, '.', '')) : '0'; ?></td>
                <td class="gs-amt"><?php echo $rate > 0 ? $gs_money($rate) : '&nbsp;'; ?></td>
                <td class="gs-amt"><?php echo $gs_money($line_amt); ?></td>
            </tr>
            <?php
        }
        for ($pi = 0; $pi < $pad_rows; $pi++) {
            echo '<tr class="gs-pad-row"><td class="gs-desc">&nbsp;</td><td class="gs-num">&nbsp;</td><td class="gs-num">&nbsp;</td><td class="gs-num">&nbsp;</td><td class="gs-amt">&nbsp;</td><td class="gs-amt">&nbsp;</td></tr>';
        }
        if (empty($items)) {
            echo '<tr><td colspan="6" class="gs-desc text-center">No items</td></tr>';
        }
        ?>
        </tbody>
    </table>

    <?php if (($print_settings['footer_thank_you_message'] ?? '1') === '1'): ?>
    <div class="gs-thanks"><?php echo $h($thank_you); ?></div>
    <?php endif; ?>

    <div class="gs-footer-row">
        <div class="gs-bank">
            <strong>Bank details:-</strong><br>
            <?php if ($bank_name !== ''): ?><?php echo $h($bank_name); ?><br><?php endif; ?>
            <?php if ($bank_account_no !== ''): ?><?php echo $h($bank_account_no); ?><?php endif; ?>
        </div>
        <div class="gs-account">
            <?php if ($bank_account_name !== ''): ?>Account name:- <?php echo $h($bank_account_name); ?><br><?php endif; ?>
            <?php if ($bank_account_no !== ''): ?>Account Number:- <?php echo $h($bank_account_no); ?><br><?php endif; ?>
            <?php if ($bank_ifsc !== ''): ?>IFSC:- <?php echo $h($bank_ifsc); ?><?php endif; ?>
        </div>
        <div class="gs-totals">
            <table>
                <tr>
                    <td>SUBTOTAL</td>
                    <td><?php echo $gs_money($subtotal_gs); ?></td>
                </tr>
                <tr>
                    <td>SGST(<?php echo $h($pct_fmt); ?>%):</td>
                    <td><?php echo $gs_money($gst_half); ?></td>
                </tr>
                <tr>
                    <td>CGST(<?php echo $h($pct_fmt); ?>%):</td>
                    <td><?php echo $gs_money($gst_half); ?></td>
                </tr>
                <tr class="gs-total-row">
                    <td><strong><?php echo $h($currency_code); ?></strong></td>
                    <td><strong><?php echo $gs_money((float) ($grand_total ?? 0)); ?></strong></td>
                </tr>
            </table>
        </div>
    </div>

</div>
</div>

<div class="invoice-btns no-print">
    <a href="javascript:window.print()">Print</a>
    <a href="<?php echo $h($back_url ?? 'sale-invoice.php'); ?>">Back</a>
</div>
