/**
 * Shared voucher currency + exchange-rate control.
 * Requires #currency and #currencyRate. Optional #summaryNetTotalLabel / #summaryGrandTotalLabel.
 * window.AURAGOLD_CURRENCY_RATES, window.AURAGOLD_BASE_CURRENCY set by page boot.
 */
(function (global) {
    'use strict';

    function rateForCurrency(code) {
        var key = String(code || '').trim().toUpperCase();
        if (!key) return 1;
        var map = global.AURAGOLD_CURRENCY_RATES || {};
        if (Object.prototype.hasOwnProperty.call(map, key)) {
            var n = parseFloat(map[key]);
            return isFinite(n) && n > 0 ? n : 1;
        }
        for (var k in map) {
            if (!Object.prototype.hasOwnProperty.call(map, k)) continue;
            if (String(k).toUpperCase() === key) {
                var v = parseFloat(map[k]);
                return isFinite(v) && v > 0 ? v : 1;
            }
        }
        return 1;
    }

    function applyCurrencyRate(force) {
        var sel = document.getElementById('currency');
        var rateEl = document.getElementById('currencyRate');
        if (!sel || !rateEl) return;
        var key = String(sel.value || '').trim().toUpperCase();
        var map = global.AURAGOLD_CURRENCY_RATES || {};
        var next = 1;
        if (key && Object.prototype.hasOwnProperty.call(map, key)) {
            var n = parseFloat(map[key]);
            next = (isFinite(n) && n > 0) ? n : 1;
        } else {
            next = rateForCurrency(sel.value);
        }
        var opt = sel.options[sel.selectedIndex];
        if (opt && String(opt.getAttribute('data-is-base') || '') === '1') {
            next = 1;
        }
        if (force || !rateEl.dataset.userEdited) {
            rateEl.value = (Math.round(next * 1000000) / 1000000).toString();
        }
        var editFlag = !!(global.isPurchaseInvoiceEditMode
            || global.isSaleInvoiceEditMode
            || global.isVoucherEditMode
            || global.isEditMode);
        var tbody = document.getElementById('productTableBody');
        var hasRows = tbody && tbody.querySelectorAll('tr:not(.no-drag)').length > 0;
        if (typeof global.updateSummaryPanel === 'function' && (!editFlag || hasRows || !tbody)) {
            global.updateSummaryPanel();
        } else {
            if (typeof global.auragoldUpdateVoucherCurrencyLabels === 'function') {
                global.auragoldUpdateVoucherCurrencyLabels();
            }
            var totalAmtEl = document.getElementById('summaryTotalDisplay');
            if (totalAmtEl && typeof global.auragoldApplyFxToTotalAmount === 'function') {
                var totalBase = parseFloat(totalAmtEl.getAttribute('data-base-amount') || totalAmtEl.textContent || '0');
                if (!isFinite(totalBase)) totalBase = 0;
                global.auragoldApplyFxToTotalAmount(totalBase);
            }
        }
    }

    function getCurrencySymbol(code) {
        var key = String(code || '').trim().toUpperCase();
        if (!key) return '';
        var map = global.AURAGOLD_CURRENCY_SYMBOLS || {};
        if (Object.prototype.hasOwnProperty.call(map, key) && map[key]) {
            return String(map[key]);
        }
        for (var k in map) {
            if (!Object.prototype.hasOwnProperty.call(map, k)) continue;
            if (String(k).toUpperCase() === key && map[k]) {
                return String(map[k]);
            }
        }
        return key;
    }

    function getSelectedCurrency() {
        return String((document.getElementById('currency') || {}).value || '').trim();
    }

    function getBaseCurrency() {
        return String(global.AURAGOLD_BASE_CURRENCY || '').trim();
    }

    function formatCurrencyDisplay(amount, code) {
        var sym = getCurrencySymbol(code);
        var n = parseFloat(amount);
        if (!isFinite(n)) n = 0;
        return sym ? (sym + ' ' + n.toFixed(2)) : n.toFixed(2);
    }

    function updateCurrencyLabels() {
        var base = getBaseCurrency();
        var selected = getSelectedCurrency();
        var netLabel = document.getElementById('summaryNetTotalLabel');
        var grandLabel = document.getElementById('summaryGrandTotalLabel');
        var totalLabel = document.getElementById('summaryTotalAmountLabel');
        if (netLabel) {
            netLabel.textContent = base ? ('Net Total (' + base + ')') : 'Net Total';
        }
        if (grandLabel) {
            grandLabel.textContent = base ? ('Grand Total (' + base + ')') : 'Grand Total';
        }
        if (totalLabel) {
            totalLabel.textContent = selected ? ('Total Amount (' + selected + ')') : 'Total Amount';
        }
        var symEl = document.getElementById('summaryTotalDisplaySymbol');
        if (symEl) {
            symEl.textContent = getCurrencySymbol(selected) || selected || getCurrencySymbol(base) || '';
        }
    }

    function readCurrencyRate() {
        var rateEl = document.getElementById('currencyRate');
        var currencyRate = parseFloat(rateEl && rateEl.value != null ? rateEl.value : 1);
        if (!isFinite(currencyRate) || currencyRate <= 0) currencyRate = 1;
        return currencyRate;
    }

    /**
     * Apply FX to a base-currency grand total amount.
     * Also sets data attributes and refreshes labels when summaryGrandTotal exists.
     */
    function applyFxToGrandTotal(grandTotalBase, summaryGrandTotalEl) {
        var currencyRate = readCurrencyRate();
        var base = getBaseCurrency();
        if (summaryGrandTotalEl) {
            summaryGrandTotalEl.textContent = formatCurrencyDisplay(grandTotalBase, base);
            summaryGrandTotalEl.setAttribute('data-base-amount', Number(grandTotalBase || 0).toFixed(2));
            summaryGrandTotalEl.setAttribute('data-currency-rate', String(currencyRate));
        }
        updateCurrencyLabels();
        return Number(grandTotalBase || 0);
    }

    function applyFxToTotalAmount(totalBase) {
        var currencyRate = readCurrencyRate();
        var selected = getSelectedCurrency();
        var converted = Number(totalBase || 0) * currencyRate;
        var symEl = document.getElementById('summaryTotalDisplaySymbol');
        var amtEl = document.getElementById('summaryTotalDisplay');
        if (symEl) {
            symEl.textContent = getCurrencySymbol(selected) || selected || getCurrencySymbol(getBaseCurrency()) || '';
        }
        if (amtEl) {
            amtEl.textContent = converted.toFixed(2);
            amtEl.setAttribute('data-base-amount', Number(totalBase || 0).toFixed(2));
        }
        updateCurrencyLabels();
        return converted;
    }

    function formatGrandTotalDisplay(grandTotalBase) {
        var el = document.getElementById('summaryGrandTotal');
        return applyFxToGrandTotal(Number(grandTotalBase || 0), el);
    }

    function restoreCurrencyRateFromOrder(order) {
        var rateEl = document.getElementById('currencyRate');
        if (!rateEl || !order) return;
        var er = order.exchange_rate != null && order.exchange_rate !== ''
            ? order.exchange_rate
            : (order.currency_rate != null && order.currency_rate !== '' ? order.currency_rate : '');
        if (er !== '' && er != null) {
            rateEl.value = er;
            rateEl.dataset.userEdited = '1';
        } else {
            applyCurrencyRate(true);
        }
        updateCurrencyLabels();
    }

    global.auragoldApplyVoucherCurrencyRate = applyCurrencyRate;
    global.auragoldUpdateVoucherCurrencyLabels = updateCurrencyLabels;
    global.auragoldReadVoucherCurrencyRate = readCurrencyRate;
    global.auragoldGetCurrencySymbol = getCurrencySymbol;
    global.auragoldFormatCurrencyDisplay = formatCurrencyDisplay;
    global.auragoldApplyFxToGrandTotal = applyFxToGrandTotal;
    global.auragoldApplyFxToTotalAmount = applyFxToTotalAmount;
    global.auragoldFormatGrandTotalDisplay = formatGrandTotalDisplay;
    global.auragoldRestoreVoucherCurrencyRate = restoreCurrencyRateFromOrder;
    // Back-compat aliases used by purchase-invoice.php
    global.auragoldApplyPurchaseCurrencyRate = applyCurrencyRate;
    global.auragoldUpdatePurchaseCurrencyLabels = updateCurrencyLabels;

    function onReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    onReady(function () {
        var sel = document.getElementById('currency');
        var rateEl = document.getElementById('currencyRate');
        if (!sel || !rateEl) return;
        var editFlag = !!(global.isPurchaseInvoiceEditMode
            || global.isSaleInvoiceEditMode
            || global.isVoucherEditMode
            || global.isEditMode);
        var saved = parseFloat(rateEl.value);
        var hasSaved = isFinite(saved) && saved > 0 && rateEl.value !== '' && rateEl.value !== '1';
        if (!editFlag || !hasSaved) {
            applyCurrencyRate(true);
        } else {
            updateCurrencyLabels();
        }
        if (!editFlag && typeof global.updateSummaryPanel === 'function') {
            global.updateSummaryPanel();
        } else {
            updateCurrencyLabels();
        }
        sel.addEventListener('change', function () {
            if (rateEl) delete rateEl.dataset.userEdited;
            applyCurrencyRate(true);
        });
        rateEl.addEventListener('input', function () {
            rateEl.dataset.userEdited = '1';
            if (typeof global.updateSummaryPanel === 'function') {
                global.updateSummaryPanel();
            }
        });
    });
})(typeof window !== 'undefined' ? window : this);
