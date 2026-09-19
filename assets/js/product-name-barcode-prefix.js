/**
 * Barcode prefix comes from branch Barcode Prefix Setting only (data-metal-barcode-base on each row).
 * Product name is not merged into prefix.
 */
(function ($) {
    'use strict';
    if (!$) return;

    function getProductNameBarcodeSuffix() {
        return '';
    }

    function applyProductNameToBarcodePrefixesFromInput() {
        /* disabled — prefix stays at default from barcode prefix settings */
    }

    window.getProductNameBarcodeSuffix = getProductNameBarcodeSuffix;
    window.applyProductNameToBarcodePrefixesFromInput = applyProductNameToBarcodePrefixesFromInput;
})(window.jQuery);
