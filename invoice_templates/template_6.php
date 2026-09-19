<?php
/**
 * Template 6 – Formal B&W retail (Naveen-style). Markup/classes aligned with static reference HTML.
 */
if (!isset($items) || !is_array($items)) {
    $items = [];
}
$print_settings = is_array($print_settings ?? null) ? $print_settings : [];
$t6 = function_exists('getInvoicePrintTemplate6Options') ? getInvoicePrintTemplate6Options($print_settings) : [
    'show_item_vertical_lines' => false,
    'show_currency_on_amounts' => false,
    'rates_banner_format' => '',
    'column_labels' => [],
    'label_gold_total' => 'Total Gold:',
    'label_silver_total' => 'Total Silver:',
    'label_total_before_gst' => 'Total Value before GST',
    'label_cgst' => 'CGST @ {pct} %',
    'label_sgst' => 'SGST @ {pct} %',
    'label_total_with_gst' => 'Total Value with GST',
    'label_bank_transfer' => 'BANK TRANSFER',
    'label_cash' => 'Cash',
    'label_last_balance' => 'Last Amount Balance',
    'label_current_balance' => 'Current Amount Balance',
    'balance_suffix' => ' Dr',
    'min_item_rows' => 12,
];

$h = static function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};

$cur_sym = isset($currency_symbol) ? trim((string) $currency_symbol) : '';
$t6_money = static function ($n, $dec = 0) use ($t6, $h, $cur_sym) {
    $s = number_format((float) $n, $dec, '.', '');
    if (!empty($t6['show_currency_on_amounts']) && $cur_sym !== '') {
        return $h($cur_sym) . ' ' . $h($s);
    }
    return $h($s);
};

$t6_default_terms = <<<'TXT'
Goods once sold will not be taken back exchange only
We are not responsible any kind of breakage
22 KT. Gold Jewellery 8% Less On Net Weight In cash
100% Exchange For Net Gold Weight
All other Taxes As Per Govt. Rules Buy Or Sales Time
All Disputes Are Subject To Noida Jurisdiction
This Is A Computer Generated Bill Signature Is Not Required
TXT;
$terms_display = trim((string) ($print_settings['terms_conditions'] ?? ''));
if ($terms_display === '') {
    $terms_display = $t6_default_terms;
}

$company_pan = trim((string) ($print_settings['company_pan'] ?? ''));
$company_gstin = trim((string) ($print_settings['company_gst'] ?? ''));
if ($company_gstin === '') {
    $company_gstin = trim((string) ($company_trn ?? ''));
}
$seller_state_code = '';
if (strlen($company_gstin) >= 2 && ctype_digit(substr($company_gstin, 0, 2))) {
    $seller_state_code = substr($company_gstin, 0, 2);
}

$doc_date_naveen = $doc_date ?? '';
if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', (string) $doc_date_naveen, $md)) {
    $doc_date_naveen = $md[1] . '-' . $md[2] . '-' . $md[3];
}

$cust_mobile = '';
$cust_address = '-';
$cust_gstin = '';
$cust_state_code = '';
if (!empty($invoice['customer_id']) && isset($conn) && $conn) {
    $crow = @getRecord('SELECT * FROM tbl_customers WHERE id = ' . (int) $invoice['customer_id'] . ' LIMIT 1');
    if (is_array($crow)) {
        $cust_mobile = trim((string) ($crow['mobile_no'] ?? $crow['phone'] ?? $crow['mobile'] ?? ''));
        $addr = trim((string) ($crow['address'] ?? $crow['address_line'] ?? ''));
        if ($addr !== '') {
            $cust_address = $addr;
        }
        $cust_gstin = trim((string) ($crow['gstin'] ?? ''));
        if (strlen($cust_gstin) >= 2 && ctype_digit(substr($cust_gstin, 0, 2))) {
            $cust_state_code = substr($cust_gstin, 0, 2);
        }
    }
}

$silver_rate_txt = '—';
if (isset($tbl_settings) && is_array($tbl_settings) && array_key_exists('silver_rate', $tbl_settings) && $tbl_settings['silver_rate'] !== '' && $tbl_settings['silver_rate'] !== null) {
    $silver_rate_txt = number_format((float) $tbl_settings['silver_rate'], 2, '.', '');
}
$gold_22k_txt = number_format((float) ($gold_rates['22K'] ?? 0), 2, '.', '');
$gold_22k_plain = preg_replace('/[^0-9.]/', '', $gold_22k_txt);
if (trim((string) ($t6['rates_banner_format'] ?? '')) !== '') {
    $nv_rates_banner = str_replace(
        ['{silver_rate}', '{gold_22k}'],
        [$silver_rate_txt, $gold_22k_plain],
        (string) $t6['rates_banner_format']
    );
} else {
    $nv_rates_banner = 'SILVER RATE-' . $silver_rate_txt . ' GOLD RATE 22K-' . $gold_22k_plain;
}

$tot_gold_wt = 0.0;
$tot_gold_amt = 0.0;
$tot_silver_wt = 0.0;
$tot_silver_amt = 0.0;
foreach ($items as $it) {
    $cat = strtolower((string) ($it['category_name'] ?? ''));
    $nw = (float) ($it['net_weight'] ?? $it['final_weight'] ?? 0);
    $lineTot = (float) ($it['net_amt_with_tax'] ?? $it['amount'] ?? 0);
    if (strpos($cat, 'silver') !== false) {
        $tot_silver_wt += $nw;
        $tot_silver_amt += $lineTot;
    } else {
        $tot_gold_wt += $nw;
        $tot_gold_amt += $lineTot;
    }
}

$pct_each = 0.0;
if ((float) ($total_before_vat ?? 0) > 0.0001 && (float) ($tax_amount ?? 0) > 0) {
    $pct_each = ((float) $tax_amount / 2.0) / (float) $total_before_vat * 100.0;
}
$pct_each_fmt = number_format($pct_each, 2, '.', '');
$gst_half = (float) ($tax_amount ?? 0) / 2.0;

$prev_balance_amt = isset($invoice['previous_balance']) ? (float) $invoice['previous_balance'] : 0.0;
$curr_balance_amt = (float) ($balance_amt ?? 0);

$nv_min_rows = (int) ($t6['min_item_rows'] ?? 12);
if ($nv_min_rows < 1) {
    $nv_min_rows = 1;
}
$nv_pad = max(0, $nv_min_rows - count($items));
$t6_inv_class = 'invoice template_6 inv-naveen';
if (!empty($t6['show_item_vertical_lines'])) {
    $t6_inv_class .= ' t6-show-item-vertical-lines';
}
$cl = $t6['column_labels'] ?? [];
?>
<div class="<?php echo $h($t6_inv_class); ?>">
<div class="bill-container">

    <div class="header flex">
        <div>
            <b>PAN:</b> <?php echo $company_pan !== '' ? $h($company_pan) : '—'; ?><br>
            <b>GSTIN:</b> <?php echo $company_gstin !== '' ? $h($company_gstin) : '—'; ?><br>
            <b>STATE CODE:</b> <?php echo $seller_state_code !== '' ? $h($seller_state_code) : '—'; ?>
        </div>
        <div class="right">
            <b>BillNo:</b> <?php echo $h($doc_no ?? ''); ?><br>
            <b>Date:</b> <?php echo $h($doc_date_naveen); ?><br><br>
            <b>PAN:</b> <br>
            <b>GSTN</b> <?php echo $cust_gstin !== '' ? $h($cust_gstin) : ''; ?>
        </div>
    </div>

    <div class="box">
        <div class="flex">
            <div>
                <b>Name:</b> <?php echo $h($party_name ?? ''); ?><br>
                <b>Add:</b> <?php echo $h($cust_address); ?><br>
                <b>Mob:</b> <?php echo $cust_mobile !== '' ? $h($cust_mobile) : '—'; ?>
            </div>
            <div class="right">
                <b>STATE CODE:</b> <?php echo $cust_state_code !== '' ? $h($cust_state_code) : '—'; ?>
            </div>
        </div>
        <div class="bold t6-rate-in-box" style="margin-top:8px;"><?php echo $h($nv_rates_banner); ?></div>
    </div>

    <table class="t6-items-table">
        <thead>
            <tr>
                <th><?php echo $h($cl['sno'] ?? 'SNo'); ?></th>
                <th><?php echo $h($cl['tag_no'] ?? 'TagNo'); ?></th>
                <th><?php echo $h($cl['item'] ?? 'Item'); ?></th>
                <th><?php echo $h($cl['hsn'] ?? 'HSNCode'); ?></th>
                <th><?php echo $h($cl['gross_wt'] ?? 'GrossWt'); ?></th>
                <th><?php echo $h($cl['net_wt'] ?? 'NetWt'); ?></th>
                <th><?php echo $h($cl['dia_wt'] ?? 'DiaWt'); ?></th>
                <th><?php echo $h($cl['cst_wt'] ?? 'CstWt'); ?></th>
                <th><?php echo $h($cl['amt'] ?? 'Amt'); ?></th>
                <th><?php echo $h($cl['tot_amt'] ?? 'TotAmt'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php
        $sr_nv = 0;
        $t6_item_count = count($items);
        foreach ($items as $it) {
            $sr_nv++;
            $pname = !empty($it['product_name']) ? $it['product_name'] : ('Product #' . ($it['product_id'] ?? ''));
            $tag = !empty($it['design_no']) ? $it['design_no'] : (!empty($it['barcode']) ? $it['barcode'] : '');
            $huid_line = trim((string) ($it['barcode'] ?? ''));
            $item_cell = $h($pname);
            if ($huid_line !== '') {
                $item_cell .= '<br>HUID-' . $h($huid_line);
            }
            $hsn = '';
            $gross = (float) ($it['gross_weight'] ?? 0);
            $net = (float) ($it['net_weight'] ?? $it['final_weight'] ?? 0);
            $dia = (float) ($it['stone_weight'] ?? $it['carat'] ?? 0);
            $cst = (float) ($it['less_weight'] ?? 0);
            $net_amt = (float) ($it['net_amount'] ?? $it['amount'] ?? 0);
            $tot_line = (float) ($it['net_amt_with_tax'] ?? ($net_amt + (float) ($it['tax_amount'] ?? 0)));
            $t6_tr_cls = ($nv_pad > 0 && $sr_nv === $t6_item_count) ? ' class="t6-last-data-row"' : '';
            ?>
            <tr<?php echo $t6_tr_cls; ?>>
                <td><?php echo (int) $sr_nv; ?></td>
                <td><?php echo $h($tag); ?></td>
                <td><?php echo $item_cell; ?></td>
                <td><?php echo $hsn !== '' ? $h($hsn) : '—'; ?></td>
                <td class="right"><?php echo $h(number_format($gross, 3, '.', '')); ?></td>
                <td class="right"><?php echo $h(number_format($net, 3, '.', '')); ?></td>
                <td class="right"><?php echo $h(number_format($dia, 2, '.', '')); ?></td>
                <td class="right"><?php echo $h(number_format($cst, 2, '.', '')); ?></td>
                <td class="right"><?php echo $t6_money($net_amt, 0); ?></td>
                <td class="right"><?php echo $t6_money($tot_line, 0); ?></td>
            </tr>
            <?php
        }
        for ($pi = 0; $pi < $nv_pad; $pi++) {
            echo '<tr class="t6-pad-row">';
            for ($c = 0; $c < 10; $c++) {
                echo '<td>&nbsp;</td>';
            }
            echo '</tr>';
        }
        ?>
        </tbody>
    </table>

    <div class="summary">
    <table>
        <tbody>
            <tr>
                <td colspan="5"></td>
                <td><b><?php echo $h($t6['label_gold_total'] ?? 'Total Gold:'); ?></b></td>
                <td class="right"><?php echo $h(number_format($tot_gold_wt, 3, '.', '')); ?></td>
                <td></td>
                <td></td>
                <td class="right"><?php echo $t6_money($tot_gold_amt, 0); ?></td>
            </tr>
            <tr>
                <td colspan="5"></td>
                <td><b><?php echo $h($t6['label_silver_total'] ?? 'Total Silver:'); ?></b></td>
                <td class="right"><?php echo $h(number_format($tot_silver_wt, 3, '.', '')); ?></td>
                <td></td>
                <td></td>
                <td class="right"><?php echo $t6_money($tot_silver_amt, 0); ?></td>
            </tr>
            <tr>
                <td colspan="8"></td>
                <td><b><?php echo $h($t6['label_total_before_gst'] ?? 'Total Value before GST'); ?></b></td>
                <td class="right"><?php echo $t6_money((float) ($total_before_vat ?? 0), 0); ?></td>
            </tr>
            <tr>
                <td colspan="8"></td>
                <td><?php echo $h(str_replace('{pct}', $pct_each_fmt, (string) ($t6['label_cgst'] ?? 'CGST @ {pct} %'))); ?></td>
                <td class="right"><?php echo $t6_money($gst_half, 0); ?></td>
            </tr>
            <tr>
                <td colspan="8"></td>
                <td><?php echo $h(str_replace('{pct}', $pct_each_fmt, (string) ($t6['label_sgst'] ?? 'SGST @ {pct} %'))); ?></td>
                <td class="right"><?php echo $t6_money($gst_half, 0); ?></td>
            </tr>
            <tr>
                <td colspan="8"></td>
                <td><b><?php echo $h($t6['label_total_with_gst'] ?? 'Total Value with GST'); ?></b></td>
                <td class="right bold"><?php echo $t6_money((float) ($grand_total ?? 0), 0); ?></td>
            </tr>
            <?php
            $bank_amt = (float) ($payment_totals['bank'] ?? 0);
            if ($bank_amt > 0.0001) {
                ?>
            <tr>
                <td colspan="8"></td>
                <td><?php echo $h($t6['label_bank_transfer'] ?? 'BANK TRANSFER'); ?></td>
                <td class="right"><?php echo $t6_money($bank_amt, 0); ?></td>
            </tr>
                <?php
            }
            $cash_amt = (float) ($payment_totals['cash'] ?? 0);
            if ($cash_amt > 0.0001) {
                ?>
            <tr>
                <td colspan="8"></td>
                <td><?php echo $h($t6['label_cash'] ?? 'Cash'); ?></td>
                <td class="right"><?php echo $t6_money($cash_amt, 0); ?></td>
            </tr>
                <?php
            }
            $__pay_sum = (float) ($payment_totals['cash'] ?? 0) + (float) ($payment_totals['bank'] ?? 0) + (float) ($payment_totals['cheque'] ?? 0)
                + (float) ($payment_totals['upi'] ?? 0) + (float) ($payment_totals['card'] ?? 0) + (float) ($payment_totals['metal_exchange'] ?? 0);
            $__paid = (float) ($paid_amt ?? 0);
            if ($__pay_sum < 0.0001 && $__paid > 0.0001 && (float) ($payment_totals['scrap'] ?? 0) < 0.0001) {
                ?>
            <tr>
                <td colspan="8"></td>
                <td><?php echo $h($t6['label_cash'] ?? 'Cash'); ?></td>
                <td class="right"><?php echo $t6_money($__paid, 0); ?></td>
            </tr>
                <?php
            }
            ?>
            <tr>
                <td colspan="10">(<?php echo $h($amount_words ?? ''); ?>)</td>
            </tr>
        </tbody>
    </table>
    </div>

    <div class="small">
        <?php echo $h($t6['label_last_balance'] ?? 'Last Amount Balance'); ?> <?php echo $h(number_format($prev_balance_amt, 2, '.', ',')); ?><?php echo $h($t6['balance_suffix'] ?? ' Dr'); ?> <br>
        <?php echo $h($t6['label_current_balance'] ?? 'Current Amount Balance'); ?> <?php echo $h(number_format($curr_balance_amt, 2, '.', ',')); ?><?php echo $h($t6['balance_suffix'] ?? ' Dr'); ?>
    </div>

    <?php if (($print_settings['footer_terms_conditions'] ?? '1') === '1'): ?>
    <div class="terms">
        <b>TERMS AND CONDITIONS :-</b><br>
        <?php echo nl2br($h($terms_display)); ?>
    </div>
    <?php endif; ?>

    <div class="footer flex" style="margin-top:20px;">
        <div>
            Customer Signature :-
        </div>
        <div class="right">
            <?php echo !empty($print_settings['authorized_signature']) ? nl2br($h($print_settings['authorized_signature'])) : ''; ?><br><br>
            For <?php echo $h($company_name ?? ''); ?>
        </div>
    </div>

</div>
</div>

<div class="invoice-btns no-print">
    <a href="javascript:window.print()">Print</a>
    <a href="<?php echo $h($back_url ?? 'sale-invoice.php'); ?>">Back</a>
</div>
