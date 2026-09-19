<?php
/**
 * Classic Table Layout – full-width header, gold badge, customer/meta row, gold rates bar, full table, summary block, footer.
 * Expects: $doc_no, $doc_date, $doc_time, $party_name, $party_ref, $person_label, $person_value, $doc_comment,
 * $item_rows, $selected_columns, $col_labels, $col_labels_secondary, $print_settings, $company_*, $gold_rates,
 * $subtotal, $discount_amt, $additional_amt, $tax_amount, $grand_total, $advance_amt, $total_before_vat, $scrap_amt, $balance_amt,
 * $total_weight, $total_stone_weight, $total_net_wt, $amount_words, $currency_symbol, $payment_totals, $t, $t2, $bilingual,
 * $invoice_secondary, $document, $layout_type, $design_template, $back_url, $doc_title.
 */
if (function_exists('mergeInvoicePrintColumnLabels')) {
    $col_labels = mergeInvoicePrintColumnLabels($col_labels, $print_settings ?? []);
}
if (function_exists('applyInvoicePrintSummaryLabelOverrides')) {
    $t = applyInvoicePrintSummaryLabelOverrides($t, $print_settings ?? []);
}
$summary_row_order = function_exists('getInvoicePrintSummaryRowOrder') ? getInvoicePrintSummaryRowOrder($print_settings ?? []) : ['total', 'advance_amount', 'total_before_vat', 'vat_5_label', 'total_including_vat', 'less_scrap', 'balance_amount'];
/** Jewellery B&W Formal: always 12 tbody rows — pad with blank &nbsp; rows (1 item + 11 blanks, 2 items + 10 blanks, etc.). */
$inv_classic_min_body_rows = 0;
if (($design_template ?? '') === 'template_6' && (!function_exists('invoicePrintIsThermal') || !invoicePrintIsThermal($layout_type ?? ''))) {
    $inv_classic_min_body_rows = 12;
}
?>
<div class="invoice inv-classic <?php echo htmlspecialchars($design_template); ?><?php echo (function_exists('invoicePrintIsThermal') && invoicePrintIsThermal($layout_type)) ? ' invoice-thermal' : ''; ?>">
    <?php if (($print_settings['header_section_enabled'] ?? '1') === '1' && (($print_settings['header_company_logo'] ?? '1') === '1' || ($print_settings['header_company_name'] ?? '1') === '1' || ($print_settings['header_gst_number'] ?? '1') === '1' || ($print_settings['header_phone'] ?? '1') === '1')): ?>
    <div class="inv-header">
        <div class="inv-header-left">
            <?php if (($print_settings['header_company_logo'] ?? '1') === '1'): ?>
                <?php if (!empty($has_logo)): ?>
                <img src="<?php echo htmlspecialchars($company_logo); ?>" alt="<?php echo htmlspecialchars($company_name); ?>" class="inv-logo">
                <?php else: ?>
                <div class="inv-logo-placeholder"><?php
                    $words = preg_split('/\s+/', trim($company_name), 2);
                    echo strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : substr($words[0], 1, 1)));
                ?></div>
                <?php endif; ?>
            <?php endif; ?>
            <?php if (($print_settings['header_company_name'] ?? '1') === '1'): ?>
            <div class="inv-company">
                <h1><?php echo htmlspecialchars($company_name); ?></h1>
                <?php if (($print_settings['header_phone'] ?? '1') === '1'): ?>
                <p class="tagline"><?php echo htmlspecialchars($company_address); ?><?php if (!empty($company_phone)): ?> &bull; <?php echo htmlspecialchars($company_phone); ?><?php endif; ?><?php if (!empty($company_email)): ?> &bull; <?php echo htmlspecialchars($company_email); ?><?php endif; ?></p>
                <?php else: ?>
                <p class="tagline"><?php echo htmlspecialchars($company_address); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php if (($print_settings['header_gst_number'] ?? '1') === '1'): ?>
        <div class="inv-trn">TRN: <?php echo htmlspecialchars($company_trn); ?></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (($print_settings['header_invoice_title'] ?? '1') === '1'): ?>
    <div class="inv-tax-badge"><?php echo !empty($print_settings['invoice_title']) ? htmlspecialchars($print_settings['invoice_title']) : $doc_title; ?><?php if (!empty($t2['tax_invoice'])): ?> <span class="inv-lang-secondary <?php echo ($invoice_secondary ?? '') === 'ar' ? 'rtl' : ''; ?>"><?php echo $t2['tax_invoice']; ?></span><?php endif; ?></div>
    <?php endif; ?>

    <div class="inv-body">
    <div class="inv-info-row">
      <div class="inv-customer">
        <div class="label"><?php echo $t['mr_ms']; ?><?php if (!empty($t2['mr_ms'])): ?><span class="inv-lang-secondary <?php echo ($invoice_secondary ?? '') === 'ar' ? 'rtl' : ''; ?>"><?php echo $t2['mr_ms']; ?></span><?php endif; ?></div>
        <div class="value"><?php echo htmlspecialchars($party_name ?? ''); ?></div>
        <div class="label" style="margin-top: 10px;"><?php echo $t['customer_no']; ?><?php if (!empty($t2['customer_no'])): ?><span class="inv-lang-secondary <?php echo ($invoice_secondary ?? '') === 'ar' ? 'rtl' : ''; ?>"><?php echo $t2['customer_no']; ?></span><?php endif; ?></div>
        <div class="value"><?php echo htmlspecialchars($party_ref ?? ''); ?></div>
        <div class="label" style="margin-top: 6px;"><?php echo $t['passport_id']; ?><?php if (!empty($t2['passport_id'])): ?><span class="inv-lang-secondary <?php echo ($invoice_secondary ?? '') === 'ar' ? 'rtl' : ''; ?>"><?php echo $t2['passport_id']; ?></span><?php endif; ?></div>
        <div class="value"></div>
      </div>
      <div class="inv-invoice-meta">
        <div class="meta-row"><span class="meta-label"><?php echo $t['invoice_no']; ?><?php if (!empty($t2['invoice_no'])): ?> / <span class="inv-lang-secondary rtl"><?php echo $t2['invoice_no']; ?></span><?php endif; ?></span><span class="meta-value"><?php echo htmlspecialchars($doc_no ?? ''); ?></span></div>
        <div class="meta-row"><span class="meta-label"><?php echo $t['date']; ?><?php if (!empty($t2['date'])): ?> / <span class="inv-lang-secondary rtl"><?php echo $t2['date']; ?></span><?php endif; ?></span><span class="meta-value"><?php echo $doc_date ?? ''; ?>, <?php echo $doc_time ?? ''; ?></span></div>
        <div class="meta-row"><span class="meta-label"><?php echo $person_label ?? $t['salesman']; ?><?php if (!empty($t2['salesman'])): ?> / <span class="inv-lang-secondary rtl"><?php echo $t2['salesman']; ?></span><?php endif; ?></span><span class="meta-value"><?php echo htmlspecialchars($person_value ?? ''); ?></span></div>
      </div>
    </div>

    <?php if (!empty($gold_rates) && is_array($gold_rates)): ?>
    <div class="gold-rates">
      <?php foreach ($gold_rates as $k => $v): ?>
      <span><?php echo $k; ?> -<?php echo number_format((float)$v, 2); ?></span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="inv-table-wrap<?php echo (($design_template ?? '') === 'template_6') ? ' inv-table-wrap--fixed-12' : ''; ?>">
    <table>
      <thead>
        <tr>
          <?php foreach ((array)$selected_columns as $col_key): if (!isset($col_labels[$col_key])) continue; ?>
          <th<?php echo in_array($col_key, ['sr_no'], true) ? ' style="width: 30px;"' : ''; ?><?php echo in_array($col_key, ['gross_weight','less_weight','net_weight','rate','making_charge','diamond_amount','stone_amount','discount','amount'], true) ? ' class="text-right"' : ''; ?>><div class="inv-th-primary"><?php echo htmlspecialchars($col_labels[$col_key]); ?></div><?php if (!empty($col_labels_secondary[$col_key])): ?><div class="inv-th-secondary inv-lang-secondary <?php echo ($invoice_secondary ?? '') === 'ar' ? 'rtl' : ''; ?>"><?php echo htmlspecialchars($col_labels_secondary[$col_key]); ?></div><?php endif; ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php
        $__rows = (array) $item_rows;
        $__nrows = count($__rows);
        $__pad_upto = (int) $inv_classic_min_body_rows;
        $__last_item_needs_join = ($__pad_upto > 0 && $__nrows > 0 && $__nrows < $__pad_upto);
        $__ridx = 0;
        foreach ($__rows as $row):
            $__ridx++;
            $__tr_cls = ($__last_item_needs_join && $__ridx === $__nrows) ? ' class="inv-tr-before-pad-block"' : '';
        ?>
        <tr<?php echo $__tr_cls; ?>>
          <?php foreach ((array)$selected_columns as $col_key): if (!isset($col_labels[$col_key])) continue; ?>
          <td<?php echo in_array($col_key, ['gross_weight','less_weight','net_weight','rate','making_charge','diamond_amount','stone_amount','discount','amount'], true) ? ' class="text-right"' : ''; ?>><?php echo htmlspecialchars($row[$col_key] ?? ''); ?></td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($item_rows) && (int) $inv_classic_min_body_rows <= 0): ?>
        <tr><td colspan="<?php echo count((array)$selected_columns); ?>" class="text-center">No items</td></tr>
        <?php endif; ?>
        <?php
        if ((int) $inv_classic_min_body_rows > 0) {
            $__have = count((array) $item_rows);
            for ($__r = $__have; $__r < (int) $inv_classic_min_body_rows; $__r++) {
                echo '<tr class="inv-table-pad-row">';
                foreach ((array) $selected_columns as $col_key) {
                    if (!isset($col_labels[$col_key])) {
                        continue;
                    }
                    $tdClass = in_array($col_key, ['gross_weight', 'less_weight', 'net_weight', 'rate', 'making_charge', 'diamond_amount', 'stone_amount', 'discount', 'amount'], true) ? ' class="text-right"' : '';
                    echo '<td' . $tdClass . '>&nbsp;</td>';
                }
                echo "</tr>\n";
            }
        }
        ?>
      </tbody>
    </table>
    </div>

    <div class="inv-summary">
      <?php foreach ($summary_row_order as $row_key): ?>
      <?php if ($row_key === 'total'): ?>
      <div class="summary-row">
        <span class="summary-label"><?php echo $t['total']; ?><?php if (!empty($t2['total'])): ?> <span class="inv-lang-secondary <?php echo ($invoice_secondary ?? '') === 'ar' ? 'rtl' : ''; ?>"><?php echo $t2['total']; ?></span><?php endif; ?></span>
        <span>Weight: <?php echo number_format((float)($total_weight ?? 0), 3); ?> | Stone: <?php echo number_format((float)($total_stone_weight ?? 0), 2); ?> | Net Wt: <?php echo number_format((float)($total_net_wt ?? 0), 3); ?> | Net Amt: <?php echo $currency_symbol; ?> <?php echo number_format((float)($subtotal ?? 0) - (float)($discount_amt ?? 0) + (float)($additional_amt ?? 0), 2); ?> | VAT: <?php echo $currency_symbol; ?> <?php echo number_format((float)($tax_amount ?? 0), 2); ?> | Total: <?php echo $currency_symbol; ?> <?php echo number_format((float)($grand_total ?? 0), 2); ?></span>
      </div>
      <?php elseif ($row_key === 'advance_amount'): ?>
      <div class="summary-row">
        <span class="summary-label"><?php echo $t['advance_amount']; ?><?php if (!empty($t2['advance_amount'])): ?> <span class="inv-lang-secondary <?php echo ($invoice_secondary ?? '') === 'ar' ? 'rtl' : ''; ?>"><?php echo $t2['advance_amount']; ?></span><?php endif; ?></span>
        <span><?php echo $currency_symbol; ?> <?php echo number_format((float)($advance_amt ?? 0), 2); ?></span>
      </div>
      <?php elseif ($row_key === 'total_before_vat'): ?>
      <div class="summary-row">
        <span class="summary-label"><?php echo $t['total_before_vat']; ?><?php if (!empty($t2['total_before_vat'])): ?> <span class="inv-lang-secondary <?php echo ($invoice_secondary ?? '') === 'ar' ? 'rtl' : ''; ?>"><?php echo $t2['total_before_vat']; ?></span><?php endif; ?></span>
        <span><?php echo $currency_symbol; ?> <?php echo number_format((float)($total_before_vat ?? 0), 2); ?></span>
      </div>
      <?php elseif ($row_key === 'vat_5_label'): ?>
      <div class="summary-row">
        <span class="summary-label"><?php echo $t['vat_5_label']; ?><?php if (!empty($t2['vat_5_label'])): ?> <span class="inv-lang-secondary <?php echo ($invoice_secondary ?? '') === 'ar' ? 'rtl' : ''; ?>"><?php echo $t2['vat_5_label']; ?></span><?php endif; ?></span>
        <span><?php echo $currency_symbol; ?> <?php echo number_format((float)($tax_amount ?? 0), 2); ?></span>
      </div>
      <?php elseif ($row_key === 'total_including_vat'): ?>
      <div class="summary-row">
        <span class="summary-label"><?php echo $t['total_including_vat']; ?><?php if (!empty($t2['total_including_vat'])): ?> <span class="inv-lang-secondary <?php echo ($invoice_secondary ?? '') === 'ar' ? 'rtl' : ''; ?>"><?php echo $t2['total_including_vat']; ?></span><?php endif; ?></span>
        <span><?php echo $currency_symbol; ?> <?php echo number_format((float)($grand_total ?? 0), 2); ?></span>
      </div>
      <?php elseif ($row_key === 'less_scrap'): ?>
      <div class="summary-row">
        <span class="summary-label"><?php echo $t['less_scrap']; ?><?php if (!empty($t2['less_scrap'])): ?> <span class="inv-lang-secondary <?php echo ($invoice_secondary ?? '') === 'ar' ? 'rtl' : ''; ?>"><?php echo $t2['less_scrap']; ?></span><?php endif; ?></span>
        <span><?php echo $currency_symbol; ?> <?php echo number_format((float)($scrap_amt ?? 0), 2); ?></span>
      </div>
      <?php elseif ($row_key === 'balance_amount'): ?>
      <div class="summary-row highlight">
        <span class="summary-label"><?php echo $t['balance_amount']; ?><?php if (!empty($t2['balance_amount'])): ?> <span class="inv-lang-secondary <?php echo ($invoice_secondary ?? '') === 'ar' ? 'rtl' : ''; ?>"><?php echo $t2['balance_amount']; ?></span><?php endif; ?></span>
        <span><?php echo $currency_symbol; ?> <?php echo number_format((float)($balance_amt ?? 0), 2); ?></span>
      </div>
      <?php endif; ?>
      <?php endforeach; ?>
    </div>

    <div class="inv-footer">
      <?php if (($print_settings['footer_terms_conditions'] ?? '1') === '1'): ?>
      <div class="amount-words"><strong><?php echo $t['amount_in_words']; ?><?php if (!empty($t2['amount_in_words'])): ?> / <span class="inv-lang-secondary rtl"><?php echo $t2['amount_in_words']; ?></span><?php endif; ?>:</strong> <?php echo $currency_symbol; ?> <?php echo htmlspecialchars($amount_words ?? ''); ?></div>
      <?php
      $__inv_pay_lines = [
          ['key' => 'cash', 'label' => $t['cash'] ?? 'Cash', 'label2' => $t2['cash'] ?? ''],
          ['key' => 'card', 'label' => $t['card'] ?? 'Card', 'label2' => $t2['card'] ?? ''],
          ['key' => 'bank', 'label' => $t['bank'] ?? 'Bank', 'label2' => $t2['bank'] ?? ''],
          ['key' => 'upi', 'label' => $t['upi'] ?? 'UPI', 'label2' => $t2['upi'] ?? ''],
          ['key' => 'cheque', 'label' => $t['cheque'] ?? 'Cheque', 'label2' => $t2['cheque'] ?? ''],
          ['key' => 'metal_exchange', 'label' => $t['metal_exchange'] ?? 'Metal Exchange', 'label2' => $t2['metal_exchange'] ?? ''],
      ];
      $__inv_pay_any = false;
      foreach ($__inv_pay_lines as $__pl) {
          $__pam = (float) ($payment_totals[$__pl['key']] ?? 0);
          if ($__pam <= 0) {
              continue;
          }
          $__inv_pay_any = true;
          ?>
      <div><strong><?php echo htmlspecialchars($__pl['label']); ?><?php if (!empty($__pl['label2'])): ?> / <span class="inv-lang-secondary rtl"><?php echo htmlspecialchars($__pl['label2']); ?></span><?php endif; ?>:</strong> <?php echo $currency_symbol; ?> <?php echo number_format($__pam, 2); ?></div>
          <?php
      }
      if (!$__inv_pay_any && (float) ($paid_amt ?? 0) > 0.0001) {
          ?>
      <div><strong><?php echo htmlspecialchars($t['cash'] ?? 'Cash'); ?><?php if (!empty($t2['cash'])): ?> / <span class="inv-lang-secondary rtl"><?php echo htmlspecialchars($t2['cash']); ?></span><?php endif; ?>:</strong> <?php echo $currency_symbol; ?> <?php echo number_format((float) $paid_amt, 2); ?></div>
          <?php
      }
      ?>
      <?php if (!empty($print_settings['terms_conditions'])): ?>
      <div style="margin-top: 10px; padding: 10px; background: #f8fafc; border-radius: 6px;"><?php echo nl2br(htmlspecialchars($print_settings['terms_conditions'])); ?></div>
      <?php endif; ?>
      <?php if (!empty($doc_comment)): ?>
      <div style="margin-top: 10px; padding: 10px; background: #f8fafc; border-radius: 6px;"><strong><?php echo $t['comment']; ?><?php if (!empty($t2['comment'])): ?> / <span class="inv-lang-secondary rtl"><?php echo $t2['comment']; ?></span><?php endif; ?>:</strong> <?php echo nl2br(htmlspecialchars($doc_comment)); ?></div>
      <?php endif; ?>
      <?php endif; ?>
      <?php if (($print_settings['footer_thank_you_message'] ?? '1') === '1'): ?>
      <div style="margin-top: 12px; font-weight: 600; color: #1a365d;"><?php echo !empty($print_settings['thank_you_message']) ? htmlspecialchars($print_settings['thank_you_message']) : 'Thank you for your business.'; ?><?php if (!empty($bilingual) && ($invoice_secondary ?? '') === 'ar'): ?> <span class="inv-lang-secondary rtl">شكراً لتعاملكم.</span><?php elseif (!empty($bilingual) && ($invoice_secondary ?? '') === 'hi'): ?> <span class="inv-lang-secondary">आपके व्यवसाय के लिए धन्यवाद।</span><?php elseif (!empty($bilingual) && ($invoice_secondary ?? '') === 'mr'): ?> <span class="inv-lang-secondary">तुमच्या व्यवसायासाठी धन्यवाद.</span><?php endif; ?></div>
      <?php endif; ?>
      <?php if (($print_settings['footer_authorized_signature'] ?? '1') === '1'): ?>
      <div class="inv-signature">
        <div class="inv-signature-line"><?php echo !empty($print_settings['authorized_signature']) ? htmlspecialchars($print_settings['authorized_signature']) : 'Authorized Signature'; ?><?php if (!empty($bilingual) && ($invoice_secondary ?? '') === 'ar'): ?> <span class="inv-lang-secondary rtl">التوقيع المعتمد</span><?php endif; ?></div>
        <div style="font-size: 12px; font-weight: 600; color: #1a365d; margin-top: 4px;"><?php echo htmlspecialchars($company_name); ?></div>
      </div>
      <?php endif; ?>
    </div>
    <?php
    $banner_path = $print_settings['advertise_banner_path'] ?? '';
    $show_banner = ($print_settings['footer_show_banner'] ?? '0') === '1' && $banner_path !== '';
    if ($show_banner) {
        $banner_full = dirname(__FILE__) . '/../' . $banner_path;
        if (file_exists($banner_full)) {
            echo '<div class="inv-banner-wrap" style="margin-top: 24px; padding-top: 16px; border-top: 1px solid #e2e8f0; text-align: center;">';
            echo '<img src="' . htmlspecialchars($banner_path) . '?t=' . time() . '" alt="Banner" class="inv-banner-img" style="max-width: 100%; height: auto; object-fit: contain;">';
            echo '</div>';
        }
    }
    ?>
    </div>
</div>

<div class="invoice-btns no-print">
    <a href="javascript:window.print()">Print</a>
    <a href="<?php echo htmlspecialchars($back_url ?? 'sale-invoice.php'); ?>">Back</a>
</div>
