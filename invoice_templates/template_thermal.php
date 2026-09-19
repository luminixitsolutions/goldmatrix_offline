<?php
/**
 * Thermal Minimal Layout – narrow width, minimal spacing, simple line-by-line, small type (e.g. 80mm).
 */
if (function_exists('mergeInvoicePrintColumnLabels')) {
    $col_labels = mergeInvoicePrintColumnLabels($col_labels, $print_settings ?? []);
}
if (function_exists('applyInvoicePrintSummaryLabelOverrides')) {
    $t = applyInvoicePrintSummaryLabelOverrides($t, $print_settings ?? []);
}
$summary_row_order = function_exists('getInvoicePrintSummaryRowOrder') ? getInvoicePrintSummaryRowOrder($print_settings ?? []) : ['total', 'advance_amount', 'total_before_vat', 'vat_5_label', 'total_including_vat', 'less_scrap', 'balance_amount'];
?>
<style>
.inv-thermal { max-width: 80mm !important; margin: 0 auto !important; font-size: 10px !important; font-family: monospace, sans-serif !important; padding: 8px !important; }
.inv-thermal * { box-sizing: border-box; }
.inv-thermal .th-line { border-bottom: 1px dashed #999; padding: 4px 0; margin: 2px 0; }
.inv-thermal .th-title { text-align: center; font-weight: 700; font-size: 12px; margin-bottom: 4px; }
.inv-thermal .th-meta { font-size: 9px; }
.inv-thermal .th-table { width: 100%; font-size: 9px; }
.inv-thermal .th-table th { text-align: left; padding: 2px 4px; border-bottom: 1px solid #333; }
.inv-thermal .th-table td { padding: 2px 4px; border-bottom: 1px dotted #999; }
.inv-thermal .th-table .num { text-align: right; }
.inv-thermal .th-totals { margin-top: 8px; font-size: 10px; }
.inv-thermal .th-totals .row { display: flex; justify-content: space-between; padding: 2px 0; }
.inv-thermal .th-totals .grand { font-weight: 700; font-size: 11px; margin-top: 4px; padding-top: 4px; border-top: 2px solid #000; }
.inv-thermal .th-foot { margin-top: 8px; text-align: center; font-size: 9px; }
</style>
<div class="invoice inv-thermal">
    <?php if (($print_settings['header_section_enabled'] ?? '1') === '1'): ?>
    <div class="th-line th-title"><?php echo htmlspecialchars($company_name); ?></div>
    <?php if (!empty($company_trn)): ?>
    <div class="th-line th-meta">TRN: <?php echo htmlspecialchars($company_trn); ?></div>
    <?php endif; ?>
    <?php endif; ?>
    <?php if (($print_settings['header_invoice_title'] ?? '1') === '1'): ?>
    <div class="th-line th-title"><?php echo !empty($print_settings['invoice_title']) ? htmlspecialchars($print_settings['invoice_title']) : ($doc_title ?? 'INVOICE'); ?></div>
    <?php endif; ?>

    <div class="th-line th-meta">
        <?php echo $t['invoice_no']; ?>: <?php echo htmlspecialchars($doc_no ?? ''); ?><br>
        <?php echo $t['date']; ?>: <?php echo $doc_date ?? ''; ?> <?php echo $doc_time ?? ''; ?><br>
        <?php echo $t['mr_ms']; ?>: <?php echo htmlspecialchars($party_name ?? ''); ?><br>
        <?php echo $person_label ?? $t['salesman']; ?>: <?php echo htmlspecialchars($person_value ?? ''); ?>
    </div>

    <table class="th-table">
        <thead><tr>
            <?php foreach ((array)$selected_columns as $col_key): if (!isset($col_labels[$col_key])) continue; ?>
            <th<?php echo in_array($col_key, ['gross_weight','less_weight','net_weight','rate','making_charge','diamond_amount','stone_amount','discount','amount'], true) ? ' class="num"' : ''; ?>><?php echo htmlspecialchars($col_labels[$col_key]); ?></th>
            <?php endforeach; ?>
        </tr></thead>
        <tbody>
            <?php foreach ((array)$item_rows as $row): ?>
            <tr>
                <?php foreach ((array)$selected_columns as $col_key): if (!isset($col_labels[$col_key])) continue; ?>
                <td<?php echo in_array($col_key, ['gross_weight','less_weight','net_weight','rate','making_charge','diamond_amount','stone_amount','discount','amount'], true) ? ' class="num"' : ''; ?>><?php echo htmlspecialchars($row[$col_key] ?? ''); ?></td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="th-totals">
        <?php foreach ($summary_row_order as $row_key): ?>
        <?php if ($row_key === 'total'): ?>
        <div class="row"><?php echo $t['total']; ?>: <?php echo $currency_symbol; ?> <?php echo number_format((float)($grand_total ?? 0), 2); ?></div>
        <?php elseif ($row_key === 'advance_amount'): ?>
        <div class="row"><?php echo $t['advance_amount']; ?>: <?php echo $currency_symbol; ?> <?php echo number_format((float)($advance_amt ?? 0), 2); ?></div>
        <?php elseif ($row_key === 'total_before_vat'): ?>
        <div class="row"><?php echo $t['total_before_vat']; ?>: <?php echo $currency_symbol; ?> <?php echo number_format((float)($total_before_vat ?? 0), 2); ?></div>
        <?php elseif ($row_key === 'vat_5_label'): ?>
        <div class="row"><?php echo $t['vat_5_label']; ?>: <?php echo $currency_symbol; ?> <?php echo number_format((float)($tax_amount ?? 0), 2); ?></div>
        <?php elseif ($row_key === 'total_including_vat'): ?>
        <div class="row"><?php echo $t['total_including_vat']; ?>: <?php echo $currency_symbol; ?> <?php echo number_format((float)($grand_total ?? 0), 2); ?></div>
        <?php elseif ($row_key === 'less_scrap'): ?>
        <div class="row"><?php echo $t['less_scrap']; ?>: <?php echo $currency_symbol; ?> <?php echo number_format((float)($scrap_amt ?? 0), 2); ?></div>
        <?php elseif ($row_key === 'balance_amount'): ?>
        <div class="row grand"><?php echo $t['balance_amount']; ?>: <?php echo $currency_symbol; ?> <?php echo number_format((float)($balance_amt ?? 0), 2); ?></div>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <div class="th-foot">
        <?php echo $t['amount_in_words']; ?>: <?php echo htmlspecialchars($amount_words ?? ''); ?><br>
        <?php echo htmlspecialchars($company_name); ?><br>
        <?php echo !empty($print_settings['thank_you_message']) ? htmlspecialchars($print_settings['thank_you_message']) : 'Thank you'; ?>
    </div>
    <?php if (!empty($doc_comment)): ?>
    <div class="th-line" style="margin-top:6px;"><?php echo $t['comment']; ?>: <?php echo htmlspecialchars($doc_comment); ?></div>
    <?php endif; ?>
</div>

<div class="invoice-btns no-print">
    <a href="javascript:window.print()">Print</a>
    <a href="<?php echo htmlspecialchars($back_url ?? 'sale-invoice.php'); ?>">Back</a>
</div>
