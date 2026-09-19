/**
 * Shared Previous Balance UI: fetch from ajax/get-customer-balance.php (ledger CL rules),
 * format Amount / Gold / Silver / Diamond / Gemstone like sale-invoice.
 * Configure per page via window.PB_PAGE_CONFIG before this script loads, or call PrevBalanceUI.init(cfg).
 */
(function (global) {
    'use strict';

    function resolveAjaxUrl() {
        if (global.PB_AJAX_BASE) {
            return String(global.PB_AJAX_BASE).replace(/\/$/, '') + '/get-customer-balance.php';
        }
        return 'ajax/get-customer-balance.php';
    }

    function formatAmount(el, value) {
        if (!el) return;
        var n = typeof value === 'number' ? value : parseFloat(value) || 0;
        el.setAttribute('data-original-balance', n.toFixed(2));
        if (n < 0) {
            el.textContent = '';
            var inner = document.createElement('span');
            inner.style.color = '#dc2626';
            inner.textContent = '(-' + Math.abs(n).toFixed(2) + ')';
            el.appendChild(inner);
        } else {
            el.textContent = n.toFixed(2);
        }
    }

    function formatMetal(el, value, decimals, dataAttr) {
        if (!el) return;
        var d = decimals || 3;
        var n = typeof value === 'number' ? value : parseFloat(value) || 0;
        el.setAttribute(dataAttr, n.toFixed(d));
        if (n < 0) {
            el.textContent = '';
            var inner = document.createElement('span');
            inner.style.color = '#dc2626';
            inner.textContent = '(-' + Math.abs(n).toFixed(d) + ')';
            el.appendChild(inner);
        } else {
            el.textContent = n.toFixed(d);
        }
    }

    function clearPanel() {
        var ids = ['previousBalanceAmount', 'previousBalanceGold', 'previousBalanceSilver', 'previousBalanceDiamond', 'previousBalanceGemstone'];
        var attrs = ['data-original-balance', 'data-original-gold', 'data-original-silver', 'data-original-diamond', 'data-original-gemstone'];
        var decs = [2, 3, 3, 3, 3];
        for (var i = 0; i < ids.length; i++) {
            var el = document.getElementById(ids[i]);
            if (!el) continue;
            if (i === 0) formatAmount(el, 0);
            else formatMetal(el, 0, decs[i], attrs[i]);
        }
        var usePrevChk = document.getElementById('usePreviousBalanceCheck');
        var useAmountRow = document.getElementById('previousBalanceUseAmountRow');
        var useAmountInput = document.getElementById('previousBalanceUseAmount');
        if (usePrevChk) usePrevChk.checked = false;
        if (useAmountRow) useAmountRow.classList.remove('is-open');
        if (useAmountInput) {
            useAmountInput.value = '0.00';
            useAmountInput.removeAttribute('max');
        }
    }

    function buildQueryParams(cfg) {
        var partyNameEl = cfg.partyNameSelector ? document.querySelector(cfg.partyNameSelector) : null;
        var partyIdEl = cfg.partyIdSelector ? document.querySelector(cfg.partyIdSelector) : null;
        var partyName = partyNameEl ? String(partyNameEl.value || '').trim() : '';
        var partyId = partyIdEl ? String(partyIdEl.value || '').trim() : '';
        if (!partyName && partyId && partyIdEl && partyIdEl.tagName === 'SELECT') {
            var selOpt = partyIdEl.options[partyIdEl.selectedIndex];
            if (selOpt && selOpt.value === partyId) {
                partyName = String(selOpt.text || selOpt.textContent || '').trim();
            }
            if (!partyName && typeof jQuery !== 'undefined' && jQuery(partyIdEl).hasClass('select2-hidden-accessible')) {
                var s2data = jQuery(partyIdEl).select2('data');
                if (s2data && s2data.length) {
                    partyName = String(s2data[0].name || s2data[0].text || '').trim();
                }
            }
        }
        var params = new URLSearchParams();
        if (partyId) params.set('customer_id', partyId);
        if (partyName) params.set('customer_name', partyName);
        var bt = (cfg.balanceType || 'customer').toLowerCase();
        params.set('type', bt === 'supplier' ? 'supplier' : 'customer');
        if (cfg.ledgerClBalance !== false) {
            params.set('ledger_cl_balance', '1');
        }
        if (cfg.purchaseLedgerPrevBalance) {
            params.set('purchase_ledger_prev_balance', '1');
        }
        var bid = cfg.branchId;
        if (bid === undefined || bid === null || bid === '') {
            bid = global.AURAGOLD_WORKING_BRANCH_ID;
        }
        if (bid !== undefined && bid !== null && String(bid) !== '') {
            var n = parseInt(String(bid), 10);
            if (!isNaN(n) && n > 0) {
                params.set('branch_id', String(n));
            }
        }
        return { partyName: partyName, partyId: partyId, params: params };
    }

    var loadDebounceTimer = null;
    var loadDebounceMs = 120;

    function loadImmediate(cfg) {
        cfg = cfg || global.PB_PAGE_CONFIG || {};
        var partyNameEl = cfg.partyNameSelector ? document.querySelector(cfg.partyNameSelector) : document.getElementById('customerName');
        if (!partyNameEl) {
            console.warn('PrevBalanceUI: party name field not found');
            return Promise.resolve();
        }
        var q = buildQueryParams(cfg);
        // Match ajax/get-customer-balance.php: either customer_id or customer_name is enough. Do not zero the panel
        // when only the name field is momentarily empty (autocomplete / focus race) while #customerId is already set.
        if (!q.partyName && !q.partyId) {
            if (typeof global.__pbLoadGeneration !== 'number') global.__pbLoadGeneration = 0;
            global.__pbLoadGeneration += 1;
            clearPanel();
            var pelClear = document.getElementById('previousBalancePanelLoader');
            if (pelClear) {
                pelClear.classList.remove('pb-is-loading');
                pelClear.setAttribute('aria-hidden', 'true');
            }
            if (typeof cfg.onAfterClear === 'function') cfg.onAfterClear();
            return Promise.resolve();
        }

        if (typeof global.__pbLoadGeneration !== 'number') global.__pbLoadGeneration = 0;
        global.__pbLoadGeneration += 1;
        var myGen = global.__pbLoadGeneration;

        var url = resolveAjaxUrl() + '?' + q.params.toString();
        if (typeof global.__pbBalanceFetchPending !== 'number') {
            global.__pbBalanceFetchPending = 0;
        }
        global.__pbBalanceFetchPending += 1;
        var pel = document.getElementById('previousBalancePanelLoader');
        if (pel) {
            pel.classList.add('pb-is-loading');
            pel.setAttribute('aria-hidden', 'false');
        }
        if (typeof cfg.onBeforeLoad === 'function') {
            cfg.onBeforeLoad();
        }
        var fetchOpts = { credentials: 'same-origin' };
        if (typeof AbortController !== 'undefined') {
            var abortCtrl = new AbortController();
            fetchOpts.signal = abortCtrl.signal;
            setTimeout(function () {
                abortCtrl.abort();
            }, 60000);
        }
        return fetch(url, fetchOpts)
            .then(function (r) {
                if (!r.ok) throw new Error('Network error');
                return r.text();
            })
            .then(function (text) {
                var data;
                try {
                    data = JSON.parse(text);
                } catch (parseErr) {
                    throw new Error('Invalid balance response');
                }
                return data;
            })
            .then(function (data) {
                if (myGen !== global.__pbLoadGeneration) return;
                var prevBalanceAmtEl = document.getElementById('previousBalanceAmount');
                var prevBalanceGoldEl = document.getElementById('previousBalanceGold');
                var prevBalanceSilverEl = document.getElementById('previousBalanceSilver');
                var prevBalanceDiamondEl = document.getElementById('previousBalanceDiamond');
                var prevBalanceGemstoneEl = document.getElementById('previousBalanceGemstone');
                if (data.status === 'success' && (data.balance || data.original_balance)) {
                    var rawAmount = data.original_balance ? parseFloat(data.original_balance.amount) || 0 : parseFloat(data.balance.amount) || 0;
                    var gold = data.original_balance ? parseFloat(data.original_balance.gold) || 0 : parseFloat(data.balance.gold) || 0;
                    var silver = data.original_balance ? parseFloat(data.original_balance.silver) || 0 : parseFloat(data.balance.silver) || 0;
                    var diamond = data.original_balance ? parseFloat(data.original_balance.diamond) || 0 : parseFloat(data.balance.diamond) || 0;
                    var gemstone = data.original_balance ? parseFloat(data.original_balance.gemstone) || 0 : parseFloat(data.balance.gemstone) || 0;
                    if (prevBalanceAmtEl) formatAmount(prevBalanceAmtEl, rawAmount);
                    if (prevBalanceGoldEl) formatMetal(prevBalanceGoldEl, gold, 3, 'data-original-gold');
                    if (prevBalanceSilverEl) formatMetal(prevBalanceSilverEl, silver, 3, 'data-original-silver');
                    if (prevBalanceDiamondEl) formatMetal(prevBalanceDiamondEl, diamond, 3, 'data-original-diamond');
                    if (prevBalanceGemstoneEl) formatMetal(prevBalanceGemstoneEl, gemstone, 3, 'data-original-gemstone');
                    var useAmountInput = document.getElementById('previousBalanceUseAmount');
                    if (useAmountInput) {
                        var availableToUse = Math.abs(rawAmount);
                        useAmountInput.setAttribute('max', availableToUse.toFixed(2));
                        var chk = document.getElementById('usePreviousBalanceCheck');
                        if (chk && !chk.checked) useAmountInput.value = '0.00';
                    }
                } else {
                    if (prevBalanceAmtEl) formatAmount(prevBalanceAmtEl, 0);
                    if (prevBalanceGoldEl) formatMetal(prevBalanceGoldEl, 0, 3, 'data-original-gold');
                    if (prevBalanceSilverEl) formatMetal(prevBalanceSilverEl, 0, 3, 'data-original-silver');
                    if (prevBalanceDiamondEl) formatMetal(prevBalanceDiamondEl, 0, 3, 'data-original-diamond');
                    if (prevBalanceGemstoneEl) formatMetal(prevBalanceGemstoneEl, 0, 3, 'data-original-gemstone');
                }
                if (typeof cfg.onAfterLoad === 'function') cfg.onAfterLoad();
            })
            .catch(function (err) {
                if (myGen !== global.__pbLoadGeneration) return;
                console.error('PrevBalanceUI load:', err);
                clearPanel();
                if (typeof cfg.onAfterLoad === 'function') cfg.onAfterLoad();
            })
            .finally(function () {
                if (myGen !== global.__pbLoadGeneration) {
                    global.__pbBalanceFetchPending = Math.max(
                        0,
                        (typeof global.__pbBalanceFetchPending === 'number' ? global.__pbBalanceFetchPending : 1) - 1
                    );
                    return;
                }
                global.__pbBalanceFetchPending = Math.max(
                    0,
                    (typeof global.__pbBalanceFetchPending === 'number' ? global.__pbBalanceFetchPending : 1) - 1
                );
                if (global.__pbBalanceFetchPending === 0) {
                    if (typeof cfg.onAfterLoadAlways === 'function') {
                        try {
                            cfg.onAfterLoadAlways();
                        } catch (eCb) {
                            console.warn('PrevBalanceUI onAfterLoadAlways:', eCb);
                        }
                    }
                    /* Always clear panel spinner when no fetches left (avoids stuck "Loading balance…" if a page omits onAfterLoadAlways or a callback throws). */
                    var pel = document.getElementById('previousBalancePanelLoader');
                    if (pel) {
                        pel.classList.remove('pb-is-loading');
                        pel.setAttribute('aria-hidden', 'true');
                    }
                }
            });
    }

    function load(cfg) {
        cfg = cfg || global.PB_PAGE_CONFIG || {};
        clearTimeout(loadDebounceTimer);
        return new Promise(function (resolve) {
            loadDebounceTimer = setTimeout(function () {
                Promise.resolve(loadImmediate(cfg)).then(resolve);
            }, loadDebounceMs);
        });
    }

    function shouldSkipAutoLoad(cfg) {
        if (cfg.skipIfEditMode && global.isPurchaseInvoiceEditMode) return true;
        if (typeof cfg.skipAutoLoad === 'function' && cfg.skipAutoLoad()) return true;
        return false;
    }

    function init(cfg) {
        cfg = cfg || global.PB_PAGE_CONFIG || {};
        var merged = {
            partyNameSelector: cfg.partyNameSelector || '#customerName',
            partyIdSelector: cfg.partyIdSelector || '#customerId',
            balanceType: cfg.balanceType || 'customer',
            branchId: cfg.branchId !== undefined && cfg.branchId !== null && cfg.branchId !== ''
                ? cfg.branchId
                : global.AURAGOLD_WORKING_BRANCH_ID,
            ledgerClBalance: cfg.ledgerClBalance !== false,
            purchaseLedgerPrevBalance: !!cfg.purchaseLedgerPrevBalance,
            onBeforeLoad: cfg.onBeforeLoad,
            onAfterLoad: cfg.onAfterLoad,
            onAfterLoadAlways: cfg.onAfterLoadAlways,
            onAfterClear: cfg.onAfterClear,
            skipIfEditMode: cfg.skipIfEditMode !== false,
            skipAutoLoad: cfg.skipAutoLoad
        };

        global.loadCustomerBalance = function () {
            return loadImmediate(merged);
        };

        function partyFieldOrIdReady() {
            if (shouldSkipAutoLoad(merged)) return false;
            var nameEl = document.querySelector(merged.partyNameSelector);
            var idEl = merged.partyIdSelector ? document.querySelector(merged.partyIdSelector) : null;
            var name = nameEl ? String(nameEl.value || '').trim() : '';
            var pid = idEl ? String(idEl.value || '').trim() : '';
            return !!(name || pid);
        }

        var partyField = document.querySelector(merged.partyNameSelector);
        if (partyField) {
            partyField.addEventListener('blur', function () {
                if (partyFieldOrIdReady()) load(merged);
            });
            partyField.addEventListener('change', function () {
                if (partyFieldOrIdReady()) load(merged);
            });
            var inputTimeout;
            partyField.addEventListener('input', function () {
                clearTimeout(inputTimeout);
                inputTimeout = setTimeout(function () {
                    if (partyFieldOrIdReady()) load(merged);
                }, 1000);
            });
        }

        var partyIdField = merged.partyIdSelector ? document.querySelector(merged.partyIdSelector) : null;
        if (partyIdField) {
            partyIdField.addEventListener('change', function () {
                if (partyFieldOrIdReady()) load(merged);
            });
        }
        if (typeof jQuery !== 'undefined' && merged.partyIdSelector) {
            jQuery(document).off('change.auragoldPrevBalancePartyId').on('change.auragoldPrevBalancePartyId', merged.partyIdSelector, function () {
                if (partyFieldOrIdReady()) load(merged);
            });
        }

        // Checkbox + "Amount to use" row: bound globally in bindPreviousBalanceUseAmountUi() (see bottom)

        function tryLoad(delayMs) {
            setTimeout(function () {
                if (partyFieldOrIdReady()) load(merged);
            }, delayMs || 0);
        }
        tryLoad(500);
        tryLoad(1500);
        global.addEventListener('load', function () {
            setTimeout(function () {
                if (partyFieldOrIdReady()) load(merged);
            }, 200);
        });
    }

    function registerFormatAliases() {
        var F = formatAmount;
        var M = formatMetal;
        global.formatSalePreviousBalanceAmount = F;
        global.formatSalePreviousBalanceMetal = M;
        global.formatPurchasePreviousBalanceAmount = F;
        global.formatPurchasePreviousBalanceMetal = M;
        global.formatVoucherPreviousBalanceAmount = F;
        global.formatVoucherPreviousBalanceMetal = M;
        global.formatPrevBalanceAmount = F;
        global.formatPrevBalanceMetal = M;
    }

    var PrevBalanceUI = {
        formatAmount: formatAmount,
        formatMetal: formatMetal,
        load: load,
        loadImmediate: loadImmediate,
        clearPanel: clearPanel,
        init: init,
        registerFormatAliases: registerFormatAliases
    };

    global.PrevBalanceUI = PrevBalanceUI;
    registerFormatAliases();

    /**
     * Show #previousBalanceUseAmountRow when #usePreviousBalanceCheck is checked; hide otherwise.
     * Runs on all pages that include this script (even when PB_AUTO_INIT is false).
     */
    function bindPreviousBalanceUseAmountUi() {
        var chk = document.getElementById('usePreviousBalanceCheck');
        var row = document.getElementById('previousBalanceUseAmountRow');
        var inp = document.getElementById('previousBalanceUseAmount');
        if (!chk) return;

        function fireAfterLoad() {
            var cfg = global.PB_PAGE_CONFIG || {};
            if (typeof cfg.onAfterLoad === 'function') cfg.onAfterLoad();
        }

        function syncRowVisibility() {
            if (row) {
                if (chk.checked) row.classList.add('is-open');
                else row.classList.remove('is-open');
            }
            if (!chk.checked && inp) {
                inp.value = '0.00';
            }
        }

        chk.addEventListener('change', function () {
            syncRowVisibility();
            fireAfterLoad();
        });

        if (inp) {
            inp.addEventListener('input', fireAfterLoad);
            inp.addEventListener('change', fireAfterLoad);
        }

        syncRowVisibility();
    }

    function runWhenDomReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    runWhenDomReady(bindPreviousBalanceUseAmountUi);

    if (global.PB_PAGE_CONFIG && global.PB_AUTO_INIT !== false) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () {
                init(global.PB_PAGE_CONFIG);
            });
        } else {
            init(global.PB_PAGE_CONFIG);
        }
    }
})(typeof window !== 'undefined' ? window : this);
