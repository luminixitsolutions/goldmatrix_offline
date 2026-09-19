/**
 * Opens diamond/stone stock modals from payment icons (capture phase — avoids treating them as "other" payment).
 */
(function () {
    'use strict';
    document.addEventListener(
        'click',
        function (ev) {
            var d = ev.target.closest && ev.target.closest('.payment-icon.payment-diamond');
            if (d && typeof window.openSaleOrderDiamondStockModal === 'function') {
                ev.preventDefault();
                ev.stopImmediatePropagation();
                window._editingPaymentId = null;
                window.openSaleOrderDiamondStockModal();
                return;
            }
            var s = ev.target.closest && ev.target.closest('.payment-icon.payment-stone');
            if (s && typeof window.openSaleOrderStoneStockModal === 'function') {
                ev.preventDefault();
                ev.stopImmediatePropagation();
                window._editingPaymentId = null;
                window.openSaleOrderStoneStockModal();
            }
        },
        true
    );
})();
