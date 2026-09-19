<?php
/**
 * Detailed Jewellery Layout – weight/purity prominent, metal section, jewellery-focused table and blocks.
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
.inv-jewellery { font-family: Georgia, 'Times New Roman', serif; }
.inv-jewellery .jew-header { text-align: center; padding: 24px 20px; border-bottom: 4px double #b8860b; background: linear-gradient(180deg, #fffef7 0%, #fff 100%); }
.inv-jewellery .jew-header h1 { margin: 0; font-size: 28px; color: #5c4a00; font-weight: 700; letter-spacing: 1px; }
.inv-jewellery .jew-header .jew-trn { margin-top: 8px; font-size: 12px; color: #7c6b20; }
.inv-jewellery .jew-badge { text-align: center; padding: 10px; background: #5c4a00; color: #f4e4a6; font-size: 16px; font-weight: 700; letter-spacing: 3px; }
.inv-jewellery .jew-two-col { display: flex; gap: 24px; padding: 20px; flex-wrap: wrap; }
.inv-jewellery .jew-two-col > div { flex: 1; min-width: 260px; }
.inv-jewellery .jew-party { padding: 16px; background: #fffef7; border: 2px solid #e8dcb4; border-radius: 8px; }
.inv-jewellery .jew-party .label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #7c6b20; margin-bottom: 2px; }
.inv-jewellery .jew-party .value { font-size: 14px; font-weight: 600; color: #3d3500; }
.inv-jewellery .jew-meta { text-align: right; }
.inv-jewellery .jew-meta .row { margin-bottom: 6px; font-size: 13px; }
.inv-jewellery .jew-gold-bar { display: flex; justify-content: space-around; padding: 14px 20px; background: linear-gradient(90deg, #f4e4a6 0%, #e8dcb4 50%, #f4e4a6 100%); border-top: 1px solid #d4af37; border-bottom: 1px solid #d4af37; margin: 0 20px 16px; border-radius: 6px; font-weight: 700; color: #3d3500; font-size: 13px; }
.inv-jewellery .jew-table-wrap { margin: 0 20px 20px; border: 2px solid #e8dcb4; border-radius: 8px; overflow: hidden; }
.inv-jewellery .jew-table-wrap th { background: #5c4a00; color: #f4e4a6; padding: 10px 12px; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }
.inv-jewellery .jew-table-wrap th.text-right, .inv-jewellery .jew-table-wrap td.text-right { text-align: right; }
.inv-jewellery .jew-table-wrap td { padding: 10px 12px; border-bottom: 1px solid #e8dcb4; }
.inv-jewellery .jew-table-wrap tbody tr:nth-child(even) { background: #fffef7; }
.inv-jewellery .jew-metal-section { margin: 0 20px 20px; padding: 16px; background: #fffef7; border: 2px solid #e8dcb4; border-radius: 8px; }
.inv-jewellery .jew-metal-section h3 { margin: 0 0 12px 0; font-size: 14px; color: #5c4a00; text-transform: uppercase; letter-spacing: 1px; }
.inv-jewellery .jew-metal-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #e8dcb4; font-size: 13px; }
.inv-jewellery .jew-metal-row:last-child { border-bottom: none; }
.inv-jewellery .jew-totals { margin: 0 20px 20px; padding: 20px; background: #5c4a00; color: #f4e4a6; border-radius: 8px; }
.inv-jewellery .jew-totals .row { display: flex; justify-content: space-between; padding: 8px 0; font-size: 14px; }
.inv-jewellery .jew-totals .row.grand { font-size: 18px; font-weight: 700; margin-top: 8px; padding-top: 12px; border-top: 2px solid #f4e4a6; }
.inv-jewellery .jew-words { margin: 0 20px 16px; padding: 12px 16px; background: #fffef7; border-left: 4px solid #d4af37; font-style: italic; font-size: 13px; color: #3d3500; }
.inv-jewellery .jew-footer { margin: 0 20px 20px; padding-top: 16px; border-top: 2px solid #e8dcb4; text-align: right; font-size: 12px; color: #7c6b20; }
</style>
<div class="invoice inv-jewellery <?php echo (function_exists('invoicePrintIsThermal') && invoicePrintIsThermal($layout_type ?? '')) ? ' invoice-thermal' : ''; ?>">
    <?php if (($print_settings['header_section_enabled'] ?? '1') === '1'): ?>
    <div class="jew-header">
        <?php if (($print_settings['header_company_logo'] ?? '1') === '1' && !empty($has_logo)): ?>
        <img src="<?php echo htmlspecialchars($company_logo); ?>" alt="" style="max-height: 64px; margin-bottom: 8px;">
        <?php endif; ?>
        <?php if (($print_settings['header_company_name'] ?? '1') === '1'): ?>
        <h1><?php echo htmlspecialchars($company_name); ?></h1>
        <p style="margin:4px 0 0 0;font-size:12px;"><?php echo htmlspecialchars($company_address); ?><?php if (!empty($company_phone)): ?> · <?php echo htmlspecialchars($company_phone); ?><?php endif; ?></p>
        <?php if (($print_settings['header_gst_number'] ?? '1') === '1'): ?>
        <div class="jew-trn">TRN: <?php echo htmlspecialchars($company_trn); ?></div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if (($print_settings['header_invoice_title'] ?? '1') === '1'): ?>
    <div class="jew-badge"><?php echo !empty($print_settings['invoice_title']) ? htmlspecialchars($print_settings['invoice_title']) : ($doc_title ?? 'TAX INVOICE'); ?></div>
    <?php endif; ?>

    <div class="jew-two-col">
        <div class="jew-party">
            <div class="label"><?php echo $t['mr_ms']; ?></div>
            <div class="value"><?php echo htmlspecialchars($party_name ?? ''); ?></div>
            <div class="label" style="margin-top:10px;"><?php echo $t['customer_no']; ?></div>
            <div class="value"><?php echo htmlspecialchars($party_ref ?? ''); ?></div>
        </div>
        <div class="jew-meta">
            <div class="row"><strong><?php echo $t['invoice_no']; ?>:</strong> <?php echo htmlspecialchars($doc_no ?? ''); ?></div>
            <div class="row"><strong><?php echo $t['date']; ?>:</strong> <?php echo $doc_date ?? ''; ?> <?php echo $doc_time ?? ''; ?></div>
            <div class="row"><strong><?php echo $person_label ?? $t['salesman']; ?>:</strong> <?php echo htmlspecialchars($person_value ?? ''); ?></div>
        </div>
    </div>

    <?php if (!empty($gold_rates) && is_array($gold_rates)): ?>
    <div class="jew-gold-bar">
        <?php foreach ($gold_rates as $k => $v): ?>
        <span><?php echo $k; ?>: <?php echo number_format((float)$v, 2); ?></span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="jew-table-wrap">
    <table>
        <thead><tr>
            <?php foreach ((array)$selected_columns as $col_key): if (!isset($col_labels[$col_key])) continue; ?>
            <th<?php echo in_array($col_key, ['gross_weight','less_weight','net_weight','rate','making_charge','diamond_amount','stone_amount','discount','amount'], true) ? ' class="text-right"' : ''; ?>><?php echo htmlspecialchars($col_labels[$col_key]); ?></th>
            <?php endforeach; ?>
        </tr></thead>
        <tbody>
            <?php foreach ((array)$item_rows as $row): ?>
            <tr>
                <?php foreach ((array)$selected_columns as $col_key): if (!isset($col_labels[$col_key])) continue; ?>
                <td<?php echo in_array($col_key, ['gross_weight','less_weight','net_weight','rate','making_charge','diamond_amount','stone_amount','discount','amount'], true) ? ' class="text-right"' : ''; ?>><?php echo htmlspecialchars($row[$col_key] ?? ''); ?></td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($item_rows)): ?>
            <tr><td colspan="<?php echo count((array)$selected_columns); ?>" style="text-align:center;">No items</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>

    <div class="jew-metal-section">
        <h3>Metal &amp; Weight Summary</h3>
        <div class="jew-metal-row"><span>Total Gross Weight</span><span><?php echo number_format((float)($total_weight ?? 0), 3); ?></span></div>
        <div class="jew-metal-row"><span>Total Stone Weight</span><span><?php echo number_format((float)($total_stone_weight ?? 0), 2); ?></span></div>
        <div class="jew-metal-row"><span>Total Net Weight</span><span><?php echo number_format((float)($total_net_wt ?? 0), 3); ?></span></div>
        <div class="jew-metal-row"><span>Net Amount (before VAT)</span><span><?php echo $currency_symbol; ?> <?php echo number_format((float)($subtotal ?? 0) - (float)($discount_amt ?? 0) + (float)($additional_amt ?? 0), 2); ?></span></div>
    </div>

    <div class="jew-totals">
        <?php foreach ($summary_row_order as $row_key): ?>
        <?php if ($row_key === 'total'): ?>
        <div class="row"><span><?php echo $t['total']; ?></span><span>Wt <?php echo number_format((float)($total_weight ?? 0), 3); ?> · Net Amt <?php echo $currency_symbol; ?> <?php echo number_format((float)($subtotal ?? 0) - (float)($discount_amt ?? 0) + (float)($additional_amt ?? 0), 2); ?> · Total <?php echo $currency_symbol; ?> <?php echo number_format((float)($grand_total ?? 0), 2); ?></span></div>
        <?php elseif ($row_key === 'advance_amount'): ?>
        <div class="row"><span><?php echo $t['advance_amount']; ?></span><span><?php echo $currency_symbol; ?> <?php echo number_format((float)($advance_amt ?? 0), 2); ?></span></div>
        <?php elseif ($row_key === 'total_before_vat'): ?>
        <div class="row"><span><?php echo $t['total_before_vat']; ?></span><span><?php echo $currency_symbol; ?> <?php echo number_format((float)($total_before_vat ?? 0), 2); ?></span></div>
        <?php elseif ($row_key === 'vat_5_label'): ?>
        <div class="row"><span><?php echo $t['vat_5_label']; ?></span><span><?php echo $currency_symbol; ?> <?php echo number_format((float)($tax_amount ?? 0), 2); ?></span></div>
        <?php elseif ($row_key === 'total_including_vat'): ?>
        <div class="row"><span><?php echo $t['total_including_vat']; ?></span><span><?php echo $currency_symbol; ?> <?php echo number_format((float)($grand_total ?? 0), 2); ?></span></div>
        <?php elseif ($row_key === 'less_scrap'): ?>
        <div class="row"><span><?php echo $t['less_scrap']; ?></span><span><?php echo $currency_symbol; ?> <?php echo number_format((float)($scrap_amt ?? 0), 2); ?></span></div>
        <?php elseif ($row_key === 'balance_amount'): ?>
        <div class="row grand"><span><?php echo $t['balance_amount']; ?></span><span><?php echo $currency_symbol; ?> <?php echo number_format((float)($balance_amt ?? 0), 2); ?></span></div>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <div class="jew-words"><strong><?php echo $t['amount_in_words']; ?>:</strong> <?php echo $currency_symbol; ?> <?php echo htmlspecialchars($amount_words ?? ''); ?></div>

    <div class="jew-footer">
        <?php echo !empty($print_settings['thank_you_message']) ? htmlspecialchars($print_settings['thank_you_message']) : 'Thank you for your business.'; ?>
        <?php if (($print_settings['footer_authorized_signature'] ?? '1') === '1'): ?>
        · <?php echo !empty($print_settings['authorized_signature']) ? htmlspecialchars($print_settings['authorized_signature']) : 'Authorized Signature'; ?> · <?php echo htmlspecialchars($company_name); ?>
        <?php endif; ?>
    </div>
    <?php if (!empty($doc_comment)): ?>
    <div style="margin:0 20px 20px;padding:10px;background:#fffef7;border-radius:6px;font-size:12px;"><strong><?php echo $t['comment']; ?>:</strong> <?php echo nl2br(htmlspecialchars($doc_comment)); ?></div>
    <?php endif; ?>
</div>

<div class="invoice-btns no-print">
    <a href="javascript:window.print()">Print</a>
    <a href="<?php echo htmlspecialchars($back_url ?? 'sale-invoice.php'); ?>">Back</a>
</div>
