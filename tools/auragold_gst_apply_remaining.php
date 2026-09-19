<?php
/**
 * One-time: apply GST page wiring to product voucher PHP files (same structure as purchase-invoice).
 */
$files = [
    'purchase-quotation.php',
    'purchase-return.php',
    'old-jewelry-scrap-invoice.php',
    'material-issue.php',
    'material-receive.php',
    'jobwork-order.php',
    'jobwork-invoice.php',
    'old-jewellery-scrap-stock-in.php',
];
$base = dirname(__DIR__);
$replacements = [
    [
        "require_once 'config.php';\r\n\r\n// Load Metals",
        "require_once 'config.php';\r\nrequire_once __DIR__ . '/includes/auragold-gst-page-vars.php';\r\n\r\n// Load Metals",
    ],
    [
        "require_once 'config.php';\n\n// Load Metals",
        "require_once 'config.php';\nrequire_once __DIR__ . '/includes/auragold-gst-page-vars.php';\n\n// Load Metals",
    ],
];
$cssOld = "    .add-item-link:hover {\r\n        background: linear-gradient(135deg, rgba(197, 168, 100, 0.15) 0%, rgba(197, 168, 100, 0.1) 100%);\r\n        color: #8b6f3a;\r\n        text-decoration: none;\r\n        transform: translateY(-2px);\r\n        border-color: #a68a4a;\r\n        box-shadow: 0 4px 8px rgba(197, 168, 100, 0.2);\r\n    }\r\n    .summary-panel {";
$cssNew = "    .add-item-link:hover {\r\n        background: linear-gradient(135deg, rgba(197, 168, 100, 0.15) 0%, rgba(197, 168, 100, 0.1) 100%);\r\n        color: #8b6f3a;\r\n        text-decoration: none;\r\n        transform: translateY(-2px);\r\n        border-color: #a68a4a;\r\n        box-shadow: 0 4px 8px rgba(197, 168, 100, 0.2);\r\n    }\r\n    .add-item-link.add-item-link--disabled,\r\n    .add-item-link.add-item-link--disabled:hover {\r\n        opacity: 0.45;\r\n        cursor: not-allowed !important;\r\n        pointer-events: none;\r\n        transform: none;\r\n        box-shadow: none;\r\n        border-color: #cbd5e1;\r\n        color: #94a3b8;\r\n        background: rgba(148, 163, 184, 0.08);\r\n    }\r\n    .add-item-link.add-item-link--disabled a {\r\n        cursor: not-allowed !important;\r\n        color: #94a3b8;\r\n        pointer-events: none;\r\n    }\r\n    .summary-panel {";

foreach ($files as $f) {
    $p = $base . DIRECTORY_SEPARATOR . $f;
    if (!is_file($p)) {
        echo "skip missing $f\n";
        continue;
    }
    $c = file_get_contents($p);
    $orig = $c;
    if (strpos($c, 'auragold-gst-page-vars') === false) {
        $c = str_replace($replacements[0][0], $replacements[0][1], $c);
        if ($c === $orig) {
            $c = str_replace($replacements[1][0], $replacements[1][1], $c);
        }
    }
    if (strpos($c, 'add-item-link--disabled') === false) {
        $c = str_replace($cssOld, $cssNew, $c);
    }
    $c = str_replace(
        '<input type="hidden" id="customerId" name="customer_id" value="">',
        '<input type="hidden" id="customerId" name="customer_id" value="">' . "\r\n" . '                                                        <input type="hidden" id="customerBillingState" name="customer_billing_state" value="">',
        $c
    );
    $c = str_replace(
        '<input type="hidden" id="customerId" name="customer_id" value="">' . "\n" . '                                                        <input type="hidden" id="customerBillingState"',
        '<input type="hidden" id="customerId" name="customer_id" value="">' . "\n" . '                                                        <input type="hidden" id="customerBillingState"',
        $c
    );
    // Dedupe if run twice
    $c = preg_replace(
        '/(<input type="hidden" id="customerBillingState"[^>]*>\s*){2,}/',
        '<input type="hidden" id="customerBillingState" name="customer_billing_state" value="">' . "\r\n",
        $c
    );

    $addOld = '<div class="add-item-link" id="addItemBtn" style="cursor: pointer;" onclick="if(typeof window.openProductModal===\'function\'){window.openProductModal();}return false;">' . "\r\n" . '                                        <a href="javascript:void(0)" onclick="event.preventDefault();event.stopPropagation();if(typeof window.openProductModal===\'function\'){window.openProductModal();}return false;"><i class="feather icon-plus"></i> Add Item (Shift + Q)</a>' . "\r\n" . '                                    </div>';
    $addNew = '<div class="add-item-link add-item-link--disabled" id="addItemBtn" role="button" aria-disabled="true" title="Select a customer first" style="cursor: not-allowed;">' . "\r\n" . '                                        <a href="javascript:void(0)" tabindex="-1" aria-disabled="true"><i class="feather icon-plus"></i> Add Item (Shift + Q)</a>' . "\r\n" . '                                    </div>';
    $c = str_replace($addOld, $addNew, $c);

    $c = str_replace(
        '<script src="assets/js/product-modal-add-item-common.js"></script>',
        '<script src="assets/js/product-modal-add-item-common.js"></script>' . "\r\n" . '<?php require __DIR__ . \'/includes/auragold-gst-page-bootstrap.php\'; ?>',
        $c
    );

    $c = str_replace(
        "            if (searchTerm.length < 2) {\r\n                suggestionsDiv.hide();\r\n                \$('#customerId').val('');\r\n                selectedCustomerId = null;\r\n                return;\r\n            }",
        "            if (searchTerm.length < 2) {\r\n                suggestionsDiv.hide();\r\n                \$('#customerId').val('');\r\n                selectedCustomerId = null;\r\n                var cbsClr = document.getElementById('customerBillingState');\r\n                if (cbsClr) cbsClr.value = '';\r\n                window.customerState = '';\r\n                if (typeof window.updateSaleInvoiceAddItemButtonState === 'function') window.updateSaleInvoiceAddItemButtonState();\r\n                return;\r\n            }",
        $c
    );

    $c = str_replace(
        '                            response.customers.forEach(function(customer) {
                                html += `
                                    <div class="customer-suggestion-item" 
                                         data-customer-id="${customer.id}" 
                                         data-customer-name="${customer.name}"
                                         style="padding: 0.75rem; cursor: pointer; border-bottom: 1px solid #f1f5f9; transition: background 0.2s;"
                                         onmouseover="this.style.background=\'#f8fafc\'" 
                                         onmouseout="this.style.background=\'#fff\'">',
        '                            response.customers.forEach(function(customer) {
                                var _bs = (customer.billing_state != null ? String(customer.billing_state) : \'\').replace(/"/g, \'&quot;\');
                                html += `
                                    <div class="customer-suggestion-item" 
                                         data-customer-id="${customer.id}" 
                                         data-customer-name="${customer.name}"
                                         data-billing-state="${_bs}"
                                         style="padding: 0.75rem; cursor: pointer; border-bottom: 1px solid #f1f5f9; transition: background 0.2s;"
                                         onmouseover="this.style.background=\'#f8fafc\'" 
                                         onmouseout="this.style.background=\'#fff\'">',
        $c
    );

    $c = str_replace(
        "        \$(document).on('click', '.customer-suggestion-item', function() {
            const customerId = \$(this).data('customer-id');
            const customerName = \$(this).data('customer-name');
            
            \$('#customerName').val(customerName);
            \$('#customerId').val(customerId);
            selectedCustomerId = customerId;
            \$('#customerSuggestions').hide();
            
            // Load customer balance when customer is selected (with small delay to ensure DOM is updated)
            setTimeout(function() {
                if (typeof loadCustomerBalance === 'function') {
                    loadCustomerBalance();
                }
            }, 100);
        });",
        "        \$(document).on('click', '.customer-suggestion-item', function() {
            const customerId = \$(this).data('customer-id');
            const customerName = \$(this).data('customer-name');
            const billingState = \$(this).attr('data-billing-state') || '';
            
            \$('#customerName').val(customerName);
            \$('#customerId').val(customerId);
            selectedCustomerId = customerId;
            var cbs = document.getElementById('customerBillingState');
            if (cbs) cbs.value = billingState;
            window.customerState = billingState || '';
            \$('#customerSuggestions').hide();
            if (typeof window.updateSaleInvoiceAddItemButtonState === 'function') {
                window.updateSaleInvoiceAddItemButtonState();
            }
            
            // Load customer balance when customer is selected (with small delay to ensure DOM is updated)
            setTimeout(function() {
                if (typeof loadCustomerBalance === 'function') {
                    loadCustomerBalance();
                }
                if (typeof window.auragoldSaleInvoiceRefreshGstForAllRows === 'function') {
                    window.auragoldSaleInvoiceRefreshGstForAllRows();
                }
            }, 100);
        });",
        $c
    );

    $c = str_replace(
        "        \$(document).on('click', '#addItemBtn, #addItemBtn a', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Add Item button/link clicked');
            // Just open modal without creating rows
            currentEditingRowId = null; // Clear editing state so it adds new row
            openProductModal();
        });",
        "        \$(document).on('click', '#addItemBtn, #addItemBtn a', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof window.saleInvoiceHasCustomerSelected === 'function' && !window.saleInvoiceHasCustomerSelected()) {
                alert('Please select a customer before adding items.');
                return;
            }
            currentEditingRowId = null;
            openProductModal();
        });",
        $c
    );

    $c = str_replace(
        "    document.addEventListener('keydown', function(e) {
        if (e.shiftKey && e.key === 'Q') {
            e.preventDefault();
            // Just open modal without creating rows
            currentEditingRowId = null; // Clear editing state so it adds new row
            openProductModal();
        }
    });",
        "    document.addEventListener('keydown', function(e) {
        if (e.shiftKey && (e.key === 'Q' || e.key === 'q')) {
            if (typeof window.saleInvoiceHasCustomerSelected === 'function' && !window.saleInvoiceHasCustomerSelected()) {
                e.preventDefault();
                alert('Please select a customer before adding items.');
                return;
            }
            e.preventDefault();
            currentEditingRowId = null;
            openProductModal();
        }
    });",
        $c
    );

    if ($c !== $orig) {
        file_put_contents($p, $c);
        echo "updated $f\n";
    } else {
        echo "no change $f\n";
    }
}
