/**
 * Reliable close for Cash/Bank/Cheque/UPI/Card/Metal/Scrap payment modals (Bootstrap 4 jQuery + BS5 fallback).
 */
(function () {
    'use strict';

    if (window.__auragoldPaymentModalCommonLoaded) {
        return;
    }
    window.__auragoldPaymentModalCommonLoaded = true;

    var PAYMENT_MODAL_MAP = {
        cash: '#cashPaymentModal',
        bank: '#bankPaymentModal',
        cheque: '#chequePaymentModal',
        upi: '#upiPaymentModal',
        card: '#cardPaymentModal',
        'metal-exchange': '#metalExchangeModal',
        scrap: '#scrapPaymentModal'
    };

    window.AURAGOLD_PAYMENT_MODAL_SELECTORS = PAYMENT_MODAL_MAP;

    function resolvePaymentModalSelector(typeOrSelector) {
        var key = String(typeOrSelector || '').trim();
        if (!key) {
            return '';
        }
        if (key.charAt(0) === '#') {
            return key;
        }
        return PAYMENT_MODAL_MAP[key] || '';
    }

    function cleanupBodyIfNoOpenModals() {
        var openModals = document.querySelectorAll('.modal.show');
        if (openModals.length) {
            return;
        }
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('padding-right');
        document.body.style.removeProperty('overflow');
        document.querySelectorAll('.modal-backdrop').forEach(function (bd) {
            if (bd && bd.parentNode) {
                bd.parentNode.removeChild(bd);
            }
        });
    }

    function forceHideModalElement(modalEl) {
        if (!modalEl) {
            return;
        }
        modalEl.classList.remove('show');
        modalEl.setAttribute('aria-hidden', 'true');
        modalEl.removeAttribute('aria-modal');
        modalEl.style.display = 'none';
        cleanupBodyIfNoOpenModals();
    }

    window.auragoldClosePaymentModal = function (typeOrSelector) {
        var sel = resolvePaymentModalSelector(typeOrSelector);
        if (!sel) {
            return;
        }
        var modalEl = document.querySelector(sel);
        if (!modalEl) {
            return;
        }

        var cleaned = false;
        function finishCleanup() {
            if (cleaned) {
                return;
            }
            cleaned = true;
            forceHideModalElement(modalEl);
        }

        if (typeof jQuery !== 'undefined' && jQuery.fn && jQuery.fn.modal) {
            jQuery(modalEl).one('hidden.bs.modal.auragoldPayClose', finishCleanup);
            jQuery(modalEl).modal('hide');
            window.setTimeout(finishCleanup, 450);
            return;
        }

        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            var inst = bootstrap.Modal.getInstance(modalEl);
            if (!inst) {
                try {
                    inst = new bootstrap.Modal(modalEl);
                } catch (e) {
                    inst = null;
                }
            }
            if (inst) {
                modalEl.addEventListener('hidden.bs.modal', finishCleanup, { once: true });
                inst.hide();
                window.setTimeout(finishCleanup, 450);
                return;
            }
        }

        finishCleanup();
    };
})();
