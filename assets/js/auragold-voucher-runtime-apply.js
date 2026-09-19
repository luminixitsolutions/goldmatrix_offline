/**
 * Apply voucher-type.php runtime settings on transaction pages (metals filtered in PHP;
 * this file handles payment icons + field visibility).
 */
(function (window) {
    'use strict';

    function closestFieldColumn(el) {
        if (!el) return null;
        return el.closest(
            '.col-md-1,.col-md-2,.col-md-3,.col-md-4,.col-md-5,.col-md-6,.col-md-8,.col-lg-3,.col-lg-4,.col-lg-6,.col-lg-8'
        );
    }

    function hideFieldBySelector(selector) {
        var el = document.querySelector(selector);
        if (!el) return;
        var wrap = closestFieldColumn(el);
        if (wrap) wrap.style.display = 'none';
    }

    /** If key missing in fv, treat as visible (backward compatible). */
    function fvHidden(fv, key) {
        if (!fv || typeof fv !== 'object') return false;
        if (fv[key] === undefined || fv[key] === null) return false;
        return parseInt(fv[key], 10) !== 1;
    }

    var SALE_INVOICE_FIELD_MAP = {
        reference_no: '#refNo',
        sales_person: '#salesPerson',
        currency: '#currency',
        against_of: '#againstOf',
        due_date: '#dueDate'
    };

    function applySaleInvoiceFieldVisibility(fv) {
        if (!fv) return;
        Object.keys(SALE_INVOICE_FIELD_MAP).forEach(function (key) {
            if (fvHidden(fv, key)) hideFieldBySelector(SALE_INVOICE_FIELD_MAP[key]);
        });
        if (shouldHideFixingTypeField(fv)) hideFieldBySelector('#fixingType');
    }

    function shouldHideFixingTypeField(fv) {
        if (!fv) return false;
        if (Object.prototype.hasOwnProperty.call(fv, 'show_billing_type')) {
            return parseInt(fv.show_billing_type, 10) !== 1;
        }
        if (Object.prototype.hasOwnProperty.call(fv, 'fixing_type')) {
            return parseInt(fv.fixing_type, 10) !== 1;
        }
        return false;
    }

    var PAYMENT_ICON_MAP = [
        ['cash', '.payment-cash'],
        ['metal_exchange', '.payment-exchange'],
        ['bank', '.payment-bank'],
        ['scrap', '.payment-jewelry'],
        ['cheque', '.payment-cheque'],
        ['add_diamond', '.payment-diamond'],
        ['upi', '.payment-mobile'],
        ['add_stone', '.payment-stone'],
        ['card', '.payment-card'],
        ['add_old_jewellery', '.payment-other']
    ];

    function applyPaymentIcons(root, pb) {
        if (!root || !pb || typeof pb !== 'object') return;
        PAYMENT_ICON_MAP.forEach(function (pair) {
            var key = pair[0];
            var sel = pair[1];
            if (!Object.prototype.hasOwnProperty.call(pb, key)) return;
            if (parseInt(pb[key], 10) !== 1) {
                var node = root.querySelector(sel);
                if (node) node.style.display = 'none';
            }
        });
    }

    function applyTransactionVoucherRuntime(cfg) {
        cfg = cfg || {};
        if (cfg.payment_buttons) {
            var payRoot = document.querySelector('.payment-icons');
            if (payRoot) applyPaymentIcons(payRoot, cfg.payment_buttons);
        }
        if (cfg.field_visibility) {
            applySaleInvoiceFieldVisibility(cfg.field_visibility);
        }
    }

    window.AuragoldVoucherRuntime = {
        /** Sales Invoice + Purchase Invoice (same header ids: #refNo, #currency, …). */
        applySaleInvoice: applyTransactionVoucherRuntime,
        applyPurchaseInvoice: applyTransactionVoucherRuntime,
        applyTransactionVoucherRuntime: applyTransactionVoucherRuntime,
        applyPaymentIcons: applyPaymentIcons,
        applySaleInvoiceFieldVisibility: applySaleInvoiceFieldVisibility
    };
})(window);
