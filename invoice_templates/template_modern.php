<?php
/**
 * Modern Compact Layout – single-row header, compact table, totals in 2-column grid, minimal footer.
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
.inv-modern { font-family: 'Segoe UI', system-ui, sans-serif; }
.inv-modern .mod-header { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 3px solid #2563eb; background: #fff; }
.inv-modern .mod-header-left { display: flex; align-items: center; gap: 16px; }
.inv-modern .mod-logo { width: 56px; height: 56px; object-fit: contain; border-radius: 8px; }
.inv-modern .mod-logo-ph { width: 56px; height: 56px; background: #2563eb; color: #fff; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 18px; }
.inv-modern .mod-company-name { font-size: 20px; font-weight: 700; color: #1e293b; margin: 0; }
.inv-modern .mod-doc-badge { background: #2563eb; color: #fff; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 14px; }
.inv-modern .mod-meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; padding: 16px 20px; margin-bottom: 16px; }
.inv-modern .mod-block { padding: 12px 16px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; }
.inv-modern .mod-block-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 4px; }
.inv-modern .mod-block-value { font-size: 14px; font-weight: 600; color: #1e293b; }
.inv-modern .mod-table-wrap { padding: 0 20px 16px; }
.inv-modern .mod-table-wrap table { width: 100%; border-collapse: collapse; font-size: 12px; }
.inv-modern .mod-table-wrap th { background: #1e293b; color: #fff; padding: 8px 10px; text-align: left; font-weight: 600; }
.inv-modern .mod-table-wrap th.text-right, .inv-modern .mod-table-wrap td.text-right { text-align: right; }
.inv-modern .mod-table-wrap td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; }
.inv-modern .mod-table-wrap tbody tr:nth-child(even) { background: #f8fafc; }
.inv-modern .mod-totals-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; padding: 16px 20px; }
.inv-modern .mod-total-box { padding: 12px 16px; background: #f1f5f9; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; }
.inv-modern .mod-total-box.grand { background: #2563eb; color: #fff; font-weight: 700; font-size: 15px; grid-column: 1 / -1; }
.inv-modern .mod-total-box.wide { grid-column: 1 / -1; flex-wrap: wrap; font-size: 11px; line-height: 1.35; align-items: flex-start; }
.inv-modern .mod-footer-compact { padding: 12px 20px; border-top: 1px solid #e2e8f0; font-size: 11px; color: #64748b; display: flex; justify-content: space-between; align-items: center; }
</style>
<div class="invoice inv-modern <?php echo (function_exists('invoicePrintIsThermal') && invoicePrintIsThermal($layout_type ?? '')) ? ' invoice-thermal' : ''; ?>">
    <?php if (($print_settings['header_section_enabled'] ?? '1') === '1'): ?>
    <div class="mod-header">
        <div class="mod-header-left">
            <?php if (($print_settings['header_company_logo'] ?? '1') === '1'): ?>
                <?php if (!empty($has_logo)): ?>
                <img src="<?php echo htmlspecialchars($company_logo); ?>" alt="" class="mod-logo">
                <?php else: ?>
                <div class="mod-logo-ph"><?php $w = preg_split('/\s+/', trim($company_name), 2); echo strtoupper(substr($w[0],0,1).(isset($w[1])?substr($w[1],0,1):substr($w[0],1,1))); ?></div>
                <?php endif; ?>
            <?php endif; ?>
            <div>
                <?php if (($print_settings['header_company_name'] ?? '1') === '1'): ?>
                <h1 class="mod-company-name"><?php echo htmlspecialchars($company_name); ?></h1>
                <p style="margin:2px 0 0 0;font-size:11px;color:#64748b;"><?php echo htmlspecialchars($company_address); ?><?php if (!empty($company_trn)): ?> · TRN: <?php echo htmlspecialchars($company_trn); ?><?php endif; ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php if (($print_settings['header_invoice_title'] ?? '1') === '1'): ?>
        <div class="mod-doc-badge"><?php echo !empty($print_settings['invoice_title']) ? htmlspecialchars($print_settings['invoice_title']) : ($doc_title ?? 'INVOICE'); ?></div>
        <?php endif; ?>
    </div>
    <?php elseif (($print_settings['header_invoice_title'] ?? '1') === '1'): ?>
    <div style="padding: 14px 20px; text-align: center; font-weight: 700; font-size: 16px; border-bottom: 2px solid #2563eb; color: #1e293b;"><?php echo !empty($print_settings['invoice_title']) ? htmlspecialchars($print_settings['invoice_title']) : ($doc_title ?? 'INVOICE'); ?></div>
    <?php endif; ?>

    <div class="mod-meta-grid">
        <div class="mod-block">
            <div class="mod-block-label"><?php echo $t['mr_ms']; ?></div>
            <div class="mod-block-value"><?php echo htmlspecialchars($party_name ?? ''); ?></div>
            <div class="mod-block-label" style="margin-top:8px;"><?php echo $t['customer_no']; ?></div>
            <div class="mod-block-value"><?php echo htmlspecialchars($party_ref ?? ''); ?></div>
        </div>
        <div class="mod-block">
            <div class="mod-block-label"><?php echo $t['invoice_no']; ?></div>
            <div class="mod-block-value"><?php echo htmlspecialchars($doc_no ?? ''); ?></div>
            <div class="mod-block-label" style="margin-top:8px;"><?php echo $t['date']; ?></div>
            <div class="mod-block-value"><?php echo $doc_date ?? ''; ?> <?php echo $doc_time ?? ''; ?></div>
            <div class="mod-block-label" style="margin-top:8px;"><?php echo $person_label ?? $t['salesman']; ?></div>
            <div class="mod-block-value"><?php echo htmlspecialchars($person_value ?? ''); ?></div>
        </div>
    </div>

    <?php if (!empty($gold_rates) && is_array($gold_rates)): ?>
    <div style="padding:0 20px 12px;display:flex;gap:16px;flex-wrap:wrap;font-size:12px;font-weight:600;color:#475569;">
        <?php foreach ($gold_rates as $k => $v): ?><span><?php echo $k; ?>: <?php echo number_format((float)$v,2); ?></span><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="mod-table-wrap">
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

    <div class="mod-totals-grid">
        <?php foreach ($summary_row_order as $row_key): ?>
        <?php if ($row_key === 'total'): ?>
        <div class="mod-total-box wide"><span><?php echo $t['total']; ?></span><span>Weight: <?php echo number_format((float)($total_weight ?? 0), 3); ?> | Stone: <?php echo number_format((float)($total_stone_weight ?? 0), 2); ?> | Net Wt: <?php echo number_format((float)($total_net_wt ?? 0), 3); ?> | Net Amt: <?php echo $currency_symbol; ?> <?php echo number_format((float)($subtotal ?? 0) - (float)($discount_amt ?? 0) + (float)($additional_amt ?? 0), 2); ?> | VAT: <?php echo $currency_symbol; ?> <?php echo number_format((float)($tax_amount ?? 0), 2); ?> | Total: <?php echo $currency_symbol; ?> <?php echo number_format((float)($grand_total ?? 0), 2); ?></span></div>
        <?php elseif ($row_key === 'advance_amount'): ?>
        <div class="mod-total-box"><span><?php echo $t['advance_amount']; ?></span><span><?php echo $currency_symbol; ?> <?php echo number_format((float)($advance_amt ?? 0), 2); ?></span></div>
        <?php elseif ($row_key === 'total_before_vat'): ?>
        <div class="mod-total-box"><span><?php echo $t['total_before_vat']; ?></span><span><?php echo $currency_symbol; ?> <?php echo number_format((float)($total_before_vat ?? 0), 2); ?></span></div>
        <?php elseif ($row_key === 'vat_5_label'): ?>
        <div class="mod-total-box"><span><?php echo $t['vat_5_label']; ?></span><span><?php echo $currency_symbol; ?> <?php echo number_format((float)($tax_amount ?? 0), 2); ?></span></div>
        <?php elseif ($row_key === 'total_including_vat'): ?>
        <div class="mod-total-box"><span><?php echo $t['total_including_vat']; ?></span><span><?php echo $currency_symbol; ?> <?php echo number_format((float)($grand_total ?? 0), 2); ?></span></div>
        <?php elseif ($row_key === 'less_scrap'): ?>
        <div class="mod-total-box"><span><?php echo $t['less_scrap']; ?></span><span><?php echo $currency_symbol; ?> <?php echo number_format((float)($scrap_amt ?? 0), 2); ?></span></div>
        <?php elseif ($row_key === 'balance_amount'): ?>
        <div class="mod-total-box grand"><span><?php echo $t['balance_amount']; ?></span><span><?php echo $currency_symbol; ?> <?php echo number_format((float)($balance_amt ?? 0), 2); ?></span></div>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <div class="mod-footer-compact">
        <span><?php echo $t['amount_in_words']; ?>: <?php echo $currency_symbol; ?> <?php echo htmlspecialchars($amount_words ?? ''); ?></span>
        <span><?php echo htmlspecialchars($company_name); ?> · <?php echo !empty($print_settings['authorized_signature']) ? htmlspecialchars($print_settings['authorized_signature']) : 'Authorized Signature'; ?></span>
    </div>
    <?php if (!empty($doc_comment)): ?>
    <div style="padding:8px 20px;font-size:11px;color:#64748b;border-top:1px solid #e2e8f0;"><?php echo $t['comment']; ?>: <?php echo nl2br(htmlspecialchars($doc_comment)); ?></div>
    <?php endif; ?>
</div>

<div class="invoice-btns no-print">
    <a href="javascript:window.print()">Print</a>
    <a href="<?php echo htmlspecialchars($back_url ?? 'sale-invoice.php'); ?>">Back</a>
</div>
