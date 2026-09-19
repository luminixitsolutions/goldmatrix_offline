<?php
/**
 * Premium Retail Layout – large header, card-style sections, prominent total block.
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
.inv-premium { font-family: 'Segoe UI', system-ui, sans-serif; }
.inv-premium .pr-hero { padding: 32px 28px; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff; text-align: center; }
.inv-premium .pr-hero h1 { margin: 0; font-size: 32px; font-weight: 700; letter-spacing: 2px; }
.inv-premium .pr-hero .pr-tagline { margin-top: 8px; font-size: 13px; opacity: 0.9; }
.inv-premium .pr-hero .pr-trn { display: inline-block; margin-top: 12px; padding: 6px 14px; background: rgba(255,255,255,0.15); border-radius: 6px; font-size: 12px; font-weight: 600; }
.inv-premium .pr-doc-title { text-align: center; padding: 14px; background: #f59e0b; color: #0f172a; font-size: 18px; font-weight: 700; letter-spacing: 3px; }
.inv-premium .pr-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; padding: 24px 28px; }
.inv-premium .pr-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
.inv-premium .pr-card h3 { margin: 0 0 16px 0; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #64748b; }
.inv-premium .pr-card .row { margin-bottom: 10px; font-size: 14px; }
.inv-premium .pr-card .row:last-child { margin-bottom: 0; }
.inv-premium .pr-card .lbl { color: #64748b; margin-right: 8px; }
.inv-premium .pr-table-section { padding: 0 28px 24px; }
.inv-premium .pr-table-section table { width: 100%; border-collapse: collapse; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.inv-premium .pr-table-section th { background: #1e293b; color: #fff; padding: 14px 16px; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }
.inv-premium .pr-table-section th.text-right, .inv-premium .pr-table-section td.text-right { text-align: right; }
.inv-premium .pr-table-section td { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; }
.inv-premium .pr-table-section tbody tr:nth-child(even) { background: #f8fafc; }
.inv-premium .pr-grand-box { margin: 0 28px 28px; padding: 28px; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff; border-radius: 16px; text-align: right; box-shadow: 0 8px 24px rgba(15,23,42,0.3); }
.inv-premium .pr-grand-box .label { font-size: 14px; opacity: 0.9; margin-bottom: 4px; }
.inv-premium .pr-grand-box .amount { font-size: 28px; font-weight: 700; letter-spacing: 1px; }
.inv-premium .pr-grand-box .words { margin-top: 12px; font-size: 12px; font-style: italic; opacity: 0.95; }
.inv-premium .pr-footer-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; padding: 0 28px 28px; }
.inv-premium .pr-footer-cards .pr-card { padding: 16px; }
</style>
<div class="invoice inv-premium <?php echo (function_exists('invoicePrintIsThermal') && invoicePrintIsThermal($layout_type ?? '')) ? ' invoice-thermal' : ''; ?>">
    <?php if (($print_settings['header_section_enabled'] ?? '1') === '1'): ?>
    <div class="pr-hero">
        <?php if (($print_settings['header_company_logo'] ?? '1') === '1' && !empty($has_logo)): ?>
        <img src="<?php echo htmlspecialchars($company_logo); ?>" alt="" style="max-height: 70px; margin-bottom: 12px; background: #fff; padding: 8px; border-radius: 10px;">
        <?php endif; ?>
        <?php if (($print_settings['header_company_name'] ?? '1') === '1'): ?>
        <h1><?php echo htmlspecialchars($company_name); ?></h1>
        <p class="pr-tagline"><?php echo htmlspecialchars($company_address); ?><?php if (!empty($company_phone)): ?> · <?php echo htmlspecialchars($company_phone); ?><?php endif; ?><?php if (!empty($company_email)): ?> · <?php echo htmlspecialchars($company_email); ?><?php endif; ?></p>
        <?php if (($print_settings['header_gst_number'] ?? '1') === '1'): ?>
        <span class="pr-trn">TRN: <?php echo htmlspecialchars($company_trn); ?></span>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if (($print_settings['header_invoice_title'] ?? '1') === '1'): ?>
    <div class="pr-doc-title"><?php echo !empty($print_settings['invoice_title']) ? htmlspecialchars($print_settings['invoice_title']) : ($doc_title ?? 'TAX INVOICE'); ?></div>
    <?php endif; ?>

    <div class="pr-cards">
        <div class="pr-card">
            <h3>Customer</h3>
            <div class="row"><span class="lbl"><?php echo $t['mr_ms']; ?></span><?php echo htmlspecialchars($party_name ?? ''); ?></div>
            <div class="row"><span class="lbl"><?php echo $t['customer_no']; ?></span><?php echo htmlspecialchars($party_ref ?? ''); ?></div>
        </div>
        <div class="pr-card">
            <h3>Document</h3>
            <div class="row"><span class="lbl"><?php echo $t['invoice_no']; ?></span><?php echo htmlspecialchars($doc_no ?? ''); ?></div>
            <div class="row"><span class="lbl"><?php echo $t['date']; ?></span><?php echo $doc_date ?? ''; ?> <?php echo $doc_time ?? ''; ?></div>
            <div class="row"><span class="lbl"><?php echo $person_label ?? $t['salesman']; ?></span><?php echo htmlspecialchars($person_value ?? ''); ?></div>
        </div>
    </div>

    <?php if (!empty($gold_rates) && is_array($gold_rates)): ?>
    <div style="padding:0 28px 20px;display:flex;justify-content:center;gap:24px;flex-wrap:wrap;font-size:13px;font-weight:600;color:#1e293b;">
        <?php foreach ($gold_rates as $k => $v): ?><span><?php echo $k; ?>: <?php echo number_format((float)$v, 2); ?></span><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="pr-table-section">
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

    <div class="pr-grand-box">
        <div class="label"><?php echo $t['balance_amount']; ?></div>
        <div class="amount"><?php echo $currency_symbol; ?> <?php echo number_format((float)($balance_amt ?? 0), 2); ?></div>
        <div class="words"><?php echo $t['amount_in_words']; ?>: <?php echo $currency_symbol; ?> <?php echo htmlspecialchars($amount_words ?? ''); ?></div>
    </div>

    <div class="pr-footer-cards">
        <div class="pr-card">
            <h3>Summary</h3>
            <?php foreach ($summary_row_order as $row_key): ?>
            <?php if ($row_key === 'balance_amount') { continue; } ?>
            <?php if ($row_key === 'total'): ?>
            <div class="row"><?php echo $t['total']; ?>: Wt <?php echo number_format((float)($total_weight ?? 0), 3); ?> · <?php echo $currency_symbol; ?> <?php echo number_format((float)($grand_total ?? 0), 2); ?></div>
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
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <div class="pr-card">
            <h3><?php echo !empty($print_settings['authorized_signature']) ? htmlspecialchars($print_settings['authorized_signature']) : 'Authorized Signature'; ?></h3>
            <div class="row"><?php echo htmlspecialchars($company_name); ?></div>
            <div class="row" style="margin-top:12px;"><?php echo !empty($print_settings['thank_you_message']) ? htmlspecialchars($print_settings['thank_you_message']) : 'Thank you for your business.'; ?></div>
        </div>
    </div>
    <?php if (!empty($doc_comment)): ?>
    <div style="margin:0 28px 28px;padding:16px;background:#f8fafc;border-radius:12px;font-size:13px;"><strong><?php echo $t['comment']; ?>:</strong> <?php echo nl2br(htmlspecialchars($doc_comment)); ?></div>
    <?php endif; ?>
</div>

<div class="invoice-btns no-print">
    <a href="javascript:window.print()">Print</a>
    <a href="<?php echo htmlspecialchars($back_url ?? 'sale-invoice.php'); ?>">Back</a>
</div>
