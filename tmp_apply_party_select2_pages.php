<?php
/**
 * One-time helper: wire auragold party Select2 on voucher pages.
 */
$root = __DIR__;
require_once $root . '/includes/auragold_party_select2.php';

$pages = [
    'pos-sale-invoice.php' => ['placeholder' => 'Select customer...', 'show_billing_state' => true],
    'sale-quotations.php' => ['placeholder' => 'Select customer...', 'show_billing_state' => true],
    'sale-return.php' => ['placeholder' => 'Select customer...', 'show_billing_state' => true],
    'purchase-invoice.php' => ['placeholder' => 'Select supplier...', 'add_btn_title' => 'Add / Edit Supplier', 'show_billing_state' => true],
    'purchase-quotation.php' => ['placeholder' => 'Select supplier...', 'add_btn_title' => 'Add / Edit Supplier', 'show_billing_state' => true],
    'purchase-return.php' => ['placeholder' => 'Select supplier...', 'add_btn_title' => 'Add / Edit Supplier', 'show_billing_state' => true],
    'payment-voucher.php' => ['placeholder' => 'Select customer...', 'show_billing_state' => false],
    'receipt-voucher.php' => ['placeholder' => 'Select customer...', 'show_billing_state' => false],
    'advance-payment.php' => ['placeholder' => 'Select customer...', 'show_billing_state' => true],
    'old-jewelry-scrap-invoice.php' => ['placeholder' => 'Select customer...', 'show_billing_state' => true],
    'credit-note.php' => ['placeholder' => 'Select customer...', 'show_billing_state' => false],
    'debit-note.php' => ['placeholder' => 'Select customer...', 'show_billing_state' => false],
    'consignment-in.php' => ['placeholder' => 'Select customer...', 'show_billing_state' => true],
    'consignment-out.php' => ['placeholder' => 'Select customer...', 'show_billing_state' => true],
    'sale-order.php' => ['placeholder' => 'Select customer...', 'show_billing_state' => true],
    'repair-order.php' => ['placeholder' => 'Select customer...', 'show_billing_state' => true],
];

function party_markup(array $opts): string
{
    ob_start();
    auragold_party_select2_field($opts);
    return ob_get_clean();
}

foreach ($pages as $file => $opts) {
    $path = $root . '/' . $file;
    if (!is_file($path)) {
        echo "SKIP missing: $file\n";
        continue;
    }
    $content = file_get_contents($path);
    $orig = $content;

    if (strpos($content, 'auragold_party_select2.php') === false) {
        $content = preg_replace(
            '/(require_once\s+[\'"]config\.php[\'"];)/',
            "$1\nrequire_once __DIR__ . '/includes/auragold_party_select2.php';",
            $content,
            1
        );
    }

    $patterns = [
        // with billing state
        '#<div style="position: relative;">\s*<input type="text" class="form-control form-control-sm" id="customerName"[^>]*>\s*<input type="hidden" id="customerId"[^>]*>\s*<input type="hidden" id="customerBillingState"[^>]*>\s*<i class="feather icon-plus[^"]*"[^>]*id="addCustomerBtn"[^>]*></i>\s*<div id="customerSuggestions"[^>]*></div>\s*</div>#s',
        // without billing state
        '#<div style="position: relative;">\s*<input type="text" class="form-control form-control-sm" id="customerName"[^>]*>\s*<input type="hidden" id="customerId"[^>]*>\s*<i class="feather icon-plus[^"]*"[^>]*id="addCustomerBtn"[^>]*></i>\s*<div id="customerSuggestions"[^>]*></div>\s*</div>#s',
    ];

    $markup = party_markup($opts);
    $replaced = false;
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $markup, $content, 1);
            $replaced = true;
            break;
        }
    }

    if (!$replaced) {
        echo "WARN no field match: $file\n";
        continue;
    }

    if (strpos($content, 'auragold_echo_party_select2_assets') === false) {
        $content = preg_replace(
            '/(<\?php include [\'"]footer-script\.php[\'"]; \?>)/',
            "<?php auragold_echo_party_select2_assets(); ?>\n$1",
            $content,
            1
        );
    }

    if ($content !== $orig) {
        file_put_contents($path, $content);
        echo "OK $file\n";
    } else {
        echo "UNCHANGED $file\n";
    }
}

echo "Done.\n";
