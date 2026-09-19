<?php
$files = [
    'sale-return.php',
    'purchase-quotation.php',
    'purchase-return.php',
    'old-jewelry-scrap-invoice.php',
    'material-issue.php',
    'material-receive.php',
    'jobwork-order.php',
    'jobwork-invoice.php',
    'old-jewellery-scrap-stock-in.php',
];
$taxOld = <<<'EOT'
        // Update Tax % from product opening VAT (product-wise)
        const taxPercentInput = row.querySelector('[data-column="tax-percent"] input');
        if (taxPercentInput) {
            var taxPct = (product.total_tax_percent != null && product.total_tax_percent !== '') ? product.total_tax_percent : ((product.vat_value != null && product.vat_value !== '') ? product.vat_value : '');
            if (taxPct !== '') taxPercentInput.value = taxPct;
        }
EOT;
$taxNew = <<<'EOT'
        // GST: data-* + data-gst-line-taxes from product; tax % from tbl_product_tax + owner vs customer state
        const taxPercentInput = row.querySelector('[data-column="tax-percent"] input');
        row.setAttribute('data-gst-local-pct', (product.gst_local_percent != null && product.gst_local_percent !== '') ? String(product.gst_local_percent) : '');
        row.setAttribute('data-gst-interstate-pct', (product.gst_interstate_percent != null && product.gst_interstate_percent !== '') ? String(product.gst_interstate_percent) : '');
        row.setAttribute('data-gst-invoice-slab-pct', typeof window.auragoldGstInvoiceSlabFromProductPayload === 'function' ? window.auragoldGstInvoiceSlabFromProductPayload(product) : '');
        if (product.gst_tax_breakdown && typeof window.auragoldGstLineTaxesFromProductPayload === 'function') {
            var ltJson = window.auragoldGstLineTaxesFromProductPayload(product);
            if (ltJson) row.setAttribute('data-gst-line-taxes', ltJson);
            else row.removeAttribute('data-gst-line-taxes');
        } else {
            row.removeAttribute('data-gst-line-taxes');
        }
        if (typeof window.auragoldGstSetProductTaxesAttrOnRow === 'function') {
            window.auragoldGstSetProductTaxesAttrOnRow(row, product);
        }
        if (taxPercentInput && typeof window.setSaleInvoiceGstTaxPercentDisplay === 'function') {
            window.setSaleInvoiceGstTaxPercentDisplay(row, taxPercentInput);
        }
EOT;
$base = dirname(__DIR__);
foreach ($files as $f) {
    $p = $base . DIRECTORY_SEPARATOR . $f;
    if (!is_file($p)) {
        echo "missing $f\n";
        continue;
    }
    $c = file_get_contents($p);
    if (strpos($c, $taxOld) === false) {
        echo "$f: tax block not found\n";
        continue;
    }
    $c = str_replace($taxOld, $taxNew, $c);
    file_put_contents($p, $c);
    echo "ok $f\n";
}
