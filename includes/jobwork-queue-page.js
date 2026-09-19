/**
 * Jobwork Queue standalone page — shared behaviour with manufacturing-process Jobwork Queue modal.
 * Expects: window.mpDepartments, window.mpDepartmentUsers, window.JWQ_ORDER_LINE_COL_KEYS,
 * window.JWQ_INWARD_STOCK_MODAL_KEYS, window.JWQ_PAGE_MODE === true,
 * window.JWQ_LINE_COL_STORAGE, window.JWQ_INWARD_COL_STORAGE
 */
function openPaymentModal(type) {
    var modalMap = {
        'cash': '#cashPaymentModal',
        'bank': '#bankPaymentModal',
        'cheque': '#chequePaymentModal',
        'upi': '#upiPaymentModal',
        'card': '#cardPaymentModal',
        'metal-exchange': '#metalExchangeModal',
        'scrap': '#scrapPaymentModal'
    };
    var modalId = modalMap[type];
    if (!modalId || typeof window.jQuery === 'undefined' || !window.jQuery.fn.modal) {
        return;
    }
    var summaryBalanceAmtEl = document.getElementById('summaryBalanceAmt');
    var balanceAmt = summaryBalanceAmtEl ? parseFloat(summaryBalanceAmtEl.textContent.replace(/,/g, '')) || 0 : 0;
    var amountToShow = balanceAmt > 0 ? balanceAmt.toFixed(2) : '0.00';
    if (type === 'cash') {
        var cashAmountEl = document.getElementById('cashAmount');
        if (cashAmountEl) cashAmountEl.value = amountToShow;
    } else if (type === 'bank') {
        var bankAmountEl = document.getElementById('bankAmount');
        if (bankAmountEl) bankAmountEl.value = amountToShow;
    } else if (type === 'cheque') {
        var chequeAmountEl = document.getElementById('chequeAmount');
        if (chequeAmountEl) chequeAmountEl.value = amountToShow;
    } else if (type === 'upi') {
        var upiAmountEl = document.getElementById('upiAmount');
        if (upiAmountEl) upiAmountEl.value = amountToShow;
    } else if (type === 'card') {
        var cardAmountEl = document.getElementById('cardAmount');
        if (cardAmountEl) cardAmountEl.value = amountToShow;
    } else if (type === 'metal-exchange') {
        var metalExchangeAmountEl = document.getElementById('metalExchangeAmount');
        if (metalExchangeAmountEl) metalExchangeAmountEl.value = amountToShow;
    } else if (type === 'scrap') {
        var scrapAmountEl = document.getElementById('scrapAmount');
        if (scrapAmountEl) scrapAmountEl.value = amountToShow;
    }
    window.jQuery(modalId).modal('show');
}

function savePayment(type) {
    if (typeof window.jwqSaveMetalExchangePayment === 'function' && window.jwqSaveMetalExchangePayment(type)) {
        return;
    }
    if (typeof window.jQuery === 'undefined' || !window.jQuery.fn.modal) {
        return;
    }
    var modalMap = {
        'cash': '#cashPaymentModal',
        'bank': '#bankPaymentModal',
        'cheque': '#chequePaymentModal',
        'upi': '#upiPaymentModal',
        'card': '#cardPaymentModal',
        'metal-exchange': '#metalExchangeModal',
        'scrap': '#scrapPaymentModal'
    };
    var sel = modalMap[type];
    if (sel) {
        window.jQuery(sel).modal('hide');
    }
}

function jwqPaymentTypeFromIcon(el) {
    if (!el || !el.classList) return 'cash';
    if (el.classList.contains('payment-cash')) return 'cash';
    if (el.classList.contains('payment-bank')) return 'bank';
    if (el.classList.contains('payment-cheque')) return 'cheque';
    if (el.classList.contains('payment-mobile')) return 'upi';
    if (el.classList.contains('payment-card')) return 'card';
    if (el.classList.contains('payment-exchange')) return 'metal-exchange';
    if (el.classList.contains('payment-jewelry')) return 'scrap';
    if (el.classList.contains('payment-diamond')) return 'scrap';
    if (el.classList.contains('payment-stone')) return 'metal-exchange';
    if (el.classList.contains('payment-other')) return 'cash';
    return 'cash';
}

function initJwqPaymentIcons() {
    var wrap = document.getElementById('jwqPaymentIcons');
    if (!wrap) return;
    wrap.addEventListener('click', function (e) {
        var icon = e.target.closest('.payment-icon');
        if (!icon || !wrap.contains(icon)) return;
        e.preventDefault();
        openPaymentModal(jwqPaymentTypeFromIcon(icon));
    });
}

function initColumnManager(config) {
    var table = document.getElementById(config.tableId);
    var panel = document.getElementById(config.panelId);
    var toggleBtn = document.querySelector(config.toggleSelector);
    var searchInput = document.getElementById(config.searchId);
    var listContainer = document.getElementById(config.listId);
    if (!table || !panel || !toggleBtn || !listContainer) return;

    function applyHiddenColumns(hiddenCols) {
        table.querySelectorAll('th[data-col], td[data-col]').forEach(function (el) {
            var col = el.getAttribute('data-col');
            if (hiddenCols.indexOf(col) >= 0) el.classList.add('col-hidden');
            else el.classList.remove('col-hidden');
        });
    }

    function readHiddenFromStorage() {
        try {
            var raw = localStorage.getItem(config.storageKey);
            return raw ? JSON.parse(raw) : [];
        } catch (e) {
            return [];
        }
    }

    function saveHiddenToStorage(hiddenCols) {
        try {
            localStorage.setItem(config.storageKey, JSON.stringify(hiddenCols));
        } catch (e) {}
    }

    function collectHiddenFromCheckboxes() {
        var hidden = [];
        listContainer.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
            if (!cb.checked) hidden.push(cb.getAttribute('data-col'));
        });
        return hidden;
    }

    function syncCheckboxesFromHidden(hiddenCols) {
        listContainer.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
            var col = cb.getAttribute('data-col');
            cb.checked = hiddenCols.indexOf(col) === -1;
        });
    }

    function positionPanel() {
        if (config.panelLayout === 'inline') {
            panel.style.position = 'static';
            panel.style.left = '';
            panel.style.top = '';
            panel.style.width = '100%';
            panel.style.maxWidth = '';
            return;
        }
        if (config.panelPosition === 'absolute') {
            panel.style.position = 'absolute';
            panel.style.left = 'auto';
            panel.style.right = '0';
            panel.style.top = '100%';
            panel.style.bottom = 'auto';
            panel.style.marginTop = '6px';
            return;
        }
        panel.style.position = 'fixed';
        var btnRect = toggleBtn.getBoundingClientRect();
        var panelWidth = panel.offsetWidth || 250;
        var panelHeight = panel.offsetHeight || 280;
        var gap = 6;
        var left = btnRect.right - panelWidth;
        var top = btnRect.bottom + gap;

        if (left < 8) left = 8;
        if (left + panelWidth > window.innerWidth - 8) {
            left = window.innerWidth - panelWidth - 8;
        }

        if (top + panelHeight > window.innerHeight - 8) {
            top = btnRect.top - panelHeight - gap;
            if (top < 8) top = 8;
        }

        panel.style.left = left + 'px';
        panel.style.top = top + 'px';
    }

    var initialHidden = readHiddenFromStorage();
    syncCheckboxesFromHidden(initialHidden);
    applyHiddenColumns(initialHidden);

    listContainer.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var hidden = collectHiddenFromCheckboxes();
            saveHiddenToStorage(hidden);
            applyHiddenColumns(hidden);
        });
    });

    toggleBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        document.querySelectorAll('.columns-panel.show').forEach(function (p) {
            if (p.id !== config.panelId) p.classList.remove('show');
        });
        var willShow = !panel.classList.contains('show');
        panel.classList.toggle('show');
        if (willShow) positionPanel();
    });

    var closeBtn = panel.querySelector('[data-close-panel]');
    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            panel.classList.remove('show');
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            var term = (searchInput.value || '').toLowerCase().trim();
            listContainer.querySelectorAll('[data-label]').forEach(function (row) {
                var labelText = row.getAttribute('data-label') || '';
                row.style.display = labelText.indexOf(term) >= 0 ? '' : 'none';
            });
        });
    }

    window.addEventListener('resize', function () {
        if (panel.classList.contains('show') && config.panelLayout !== 'inline') positionPanel();
    });
}

document.addEventListener('click', function (e) {
    if (!e.target.closest('.columns-panel') && !e.target.closest('.head-setting-btn')) {
        document.querySelectorAll('.columns-panel.show').forEach(function (p) {
            p.classList.remove('show');
        });
    }
});

function jwqEsc(s) {
    if (s == null || s === '') return '';
    var d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
}

/** Escape for double-quoted HTML attributes (data-* on Diamonds used rows). */
function jwqAttrEsc(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/"/g, '&quot;');
}

function jwqNum3(v) {
    var n = parseFloat(v);
    if (isNaN(n)) return '0.000';
    return n.toFixed(3);
}

function jwqNumOptDash3(v) {
    if (v == null || v === '') return '—';
    var n = parseFloat(v);
    if (isNaN(n)) return '—';
    return n.toFixed(3);
}

function jwqNumOptDash2(v) {
    if (v == null || v === '') return '—';
    var n = parseFloat(v);
    if (isNaN(n)) return '—';
    return n.toFixed(2);
}

function jwqInputEsc(v) {
    return String(v == null ? '' : v)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function jwqLineFieldEl(tr, field) {
    if (!tr || !field) {
        return null;
    }
    var el = tr.querySelector('[data-field="' + field + '"]');
    if (el) {
        return el;
    }
    return tr.querySelector('[data-col-input="' + field + '"]');
}

function jwqLineFieldNum(tr, field) {
    var el = jwqLineFieldEl(tr, field);
    if (!el || el.value == null || el.value === '') {
        return NaN;
    }
    return parseFloat(String(el.value).replace(/,/g, ''));
}

function jwqLineOrigTotalWt(it) {
    it = it || {};
    var fw = parseFloat(it.final_weight);
    if (isFinite(fw) && fw > 0) {
        return fw;
    }
    var nw = parseFloat(it.net_weight);
    if (isFinite(nw) && nw > 0) {
        return nw;
    }
    var gw = parseFloat(it.gross_weight);
    if (isFinite(gw) && gw > 0) {
        return gw;
    }
    return 0;
}

function jwqLineOrigMetalWt(it) {
    it = it || {};
    var nw = parseFloat(it.net_weight);
    if (isFinite(nw) && nw > 0) {
        return nw;
    }
    return jwqLineOrigTotalWt(it);
}

/**
 * Carrying total — prefers queue_display_total_wt from API (matches job card).
 */
function jwqLineQueueCarryingTotalWt(it) {
    it = it || {};
    var formulaTot = jwqItemFormulaTotalWt(it);
    if (Object.prototype.hasOwnProperty.call(it, 'queue_display_total_wt')) {
        var qnS = it.queue_display_total_wt != null ? String(it.queue_display_total_wt).replace(/,/g, '') : '';
        var qnStrict = parseFloat(qnS);
        if (isFinite(qnStrict)) {
            if (formulaTot > qnStrict + 0.0001) {
                return formulaTot;
            }
            return qnStrict;
        }
    }
    var disp = jwqLineQueueDisplayTotalWt(it);
    if (formulaTot > disp + 0.0001) {
        return formulaTot;
    }
    return disp;
}

/**
 * Baseline weight for auto-loss: max(metal, carrying total).
 */
function jwqLineAutoLossBaselineWt(it) {
    var m = jwqLineOrigMetalWt(it);
    var t = jwqLineQueueCarryingTotalWt(it);
    if (isFinite(m) && isFinite(t)) {
        return Math.max(m, t);
    }
    if (isFinite(m) && m > 0) {
        return m;
    }
    if (isFinite(t) && t > 0) {
        return t;
    }
    return 0;
}

/** Order-line diamond pool (stored diamond or total − metal) for syncing header diamond vs material grid. */
function jwqOrderDiamondPoolWtFromItem(it) {
    it = it || {};
    var dStored = NaN;
    if (it.diamond_wt != null && it.diamond_wt !== '') {
        dStored = parseFloat(it.diamond_wt);
    }
    if (!isFinite(dStored) || dStored <= 0) {
        dStored = parseFloat(it.diamond_weight != null ? it.diamond_weight : NaN);
    }
    if (isFinite(dStored) && dStored > 0) {
        return dStored;
    }
    var tw0 = jwqLineOrigTotalWt(it);
    var mw0 = jwqLineOrigMetalWt(it);
    if (isFinite(tw0) && isFinite(mw0) && tw0 > mw0 + 0.0000001) {
        return tw0 - mw0;
    }
    return 0;
}

function jwqLineServerLossGrams(it) {
    it = it || {};
    if (it.queue_display_loss_wt != null && String(it.queue_display_loss_wt).trim() !== '') {
        var qx = parseFloat(String(it.queue_display_loss_wt).replace(/,/g, ''));
        if (isFinite(qx) && qx > 0.00001) {
            return qx;
        }
    }
    var gRaw = it.gold_loss_1;
    var lRaw = it.loss_wt;
    var g = NaN;
    if (gRaw !== null && gRaw !== undefined && String(gRaw).trim() !== '') {
        g = parseFloat(String(gRaw).replace(/,/g, ''));
    }
    if (isFinite(g) && Math.abs(g) > 0.00001) {
        return g;
    }
    if (lRaw !== null && lRaw !== undefined && String(lRaw).trim() !== '') {
        var l = parseFloat(String(lRaw).replace(/,/g, ''));
        if (isFinite(l)) {
            return l;
        }
    }
    return isFinite(g) ? g : 0;
}

function jwqReconcileLineLossFromWeights() {
    var tbody = document.getElementById('jwqOrderLinesBody');
    if (!tbody) {
        return;
    }
    tbody.querySelectorAll('tr[data-item-id]').forEach(function (tr) {
        var lossEl = jwqLineFieldEl(tr, 'loss');
        if (!lossEl) {
            return;
        }
        var mw = jwqLineFieldNum(tr, 'metal_wt');
        var tw = jwqLineFieldNum(tr, 'total_wt');
        var dw = jwqLineFieldNum(tr, 'diamond_wt');
        if (!isFinite(dw) || dw < 0) {
            dw = 0;
        }
        var carrying = parseFloat(String(tr.getAttribute('data-orig-total-wt') || '').replace(/,/g, ''));
        if (isFinite(carrying) && isFinite(tw) && carrying > tw + 0.0001) {
            var tInp = jwqLineFieldEl(tr, 'total_wt');
            if (tInp) {
                tInp.value = jwqNum3(carrying);
            }
            tw = carrying;
        }
        if (typeof jwqClearStaleTransferInferredLoss === 'function') {
            jwqClearStaleTransferInferredLoss(tr, mw, dw, carrying);
        }
        var cur = jwqLineFieldNum(tr, 'loss');
        if (isFinite(cur) && cur > 0.00001) {
            return;
        }
        var saved = parseFloat(String(tr.getAttribute('data-jwq-saved-loss-grams') || '').replace(/,/g, ''));
        if (isFinite(saved) && saved > 0.00001) {
            lossEl.value = jwqNumOptDash3(saved);
            return;
        }
        if (!isFinite(mw) || !isFinite(tw)) {
            return;
        }
        var implied = mw + dw - tw;
        if (implied > 0.00001 && implied < 500000) {
            if (isFinite(carrying) && carrying > tw + 0.0001 && implied > carrying - mw - dw + 0.05) {
                /* Do not infer loss that would drop total below the job carrying weight. */
            } else {
                lossEl.value = jwqNumOptDash3(implied);
            }
        }
    });
}
window.jwqReconcileLineLossFromWeights = jwqReconcileLineLossFromWeights;

function jwqClearStaleTransferInferredLoss(tr, mw, dw, carrying) {
    if (!tr || typeof jwqIsNormalTransferQueueMode !== 'function' || !jwqIsNormalTransferQueueMode()) {
        return 0;
    }
    var savedLoss = parseFloat(String(tr.getAttribute('data-jwq-saved-loss-grams') || '').replace(/,/g, ''));
    if (isFinite(savedLoss) && savedLoss > 0.00001) {
        return savedLoss;
    }
    var lossEl = jwqLineFieldEl(tr, 'loss');
    var lw = lossEl ? jwqLineFieldNum(tr, 'loss') : 0;
    if (!isFinite(lw) || lw < 0) {
        lw = 0;
    }
    if (lw <= 0.00001) {
        return 0;
    }
    if (!isFinite(mw) || mw < 0) {
        mw = 0;
    }
    if (!isFinite(dw) || dw < 0) {
        dw = 0;
    }
    var formula = mw - lw + dw;
    var stale = formula < mw - 0.0001
        || (isFinite(carrying) && carrying > formula + 0.0001);
    if (stale) {
        if (lossEl) {
            lossEl.value = jwqNumOptDash3(0);
        }
        return 0;
    }
    return lw;
}
window.jwqClearStaleTransferInferredLoss = jwqClearStaleTransferInferredLoss;

function jwqResolveTransferCarryingTotal(tr) {
    if (!tr) {
        return 0;
    }
    var carrying = parseFloat(String(tr.getAttribute('data-orig-total-wt') || '').replace(/,/g, ''));
    if (!isFinite(carrying) || carrying <= 0.0000001) {
        carrying = jwqLineFieldNum(tr, 'total_wt');
    }
    return isFinite(carrying) && carrying > 0 ? carrying : 0;
}
window.jwqResolveTransferCarryingTotal = jwqResolveTransferCarryingTotal;

/** Transfer: Total Wt = job carrying total (fixed); Metal Wt = Total − Diamond. */
function jwqApplyTransferLineCarryingTotalWt(tr) {
    if (!tr || !tr.getAttribute('data-item-id')) {
        return;
    }
    if (typeof window.jwqIsWeightExtraMetalUi === 'function' && window.jwqIsWeightExtraMetalUi()) {
        return;
    }
    if (typeof window.jwqIsAddWeightMode === 'function' && window.jwqIsAddWeightMode()) {
        return;
    }
    if (typeof window.jwqIsReduceWeightMode === 'function' && window.jwqIsReduceWeightMode()) {
        return;
    }
    var tInp = jwqLineFieldEl(tr, 'total_wt');
    var mInp = jwqLineFieldEl(tr, 'metal_wt');
    if (!tInp || !mInp) {
        return;
    }
    var totalFix = jwqResolveTransferCarryingTotal(tr);
    if (totalFix <= 0.0000001) {
        return;
    }
    var dw = jwqLineFieldNum(tr, 'diamond_wt');
    if (!isFinite(dw) || dw < 0) {
        dw = 0;
    }
    if (dw > totalFix + 0.0001) {
        dw = totalFix;
        var dInp = jwqLineFieldEl(tr, 'diamond_wt');
        if (dInp) {
            dInp.value = jwqNum3(dw);
        }
    }
    var mw = Math.max(0, totalFix - dw);
    if (typeof jwqClearStaleTransferInferredLoss === 'function') {
        jwqClearStaleTransferInferredLoss(tr, mw, dw, totalFix);
    }
    mInp.value = jwqNum3(mw);
    tInp.value = jwqNum3(totalFix);
    tr.setAttribute('data-base-metal-wt', String(mw));
    tr.removeAttribute('data-jwq-freeze-weights');
    tr.removeAttribute('data-jwq-total-manual');
}
window.jwqApplyTransferLineCarryingTotalWt = jwqApplyTransferLineCarryingTotalWt;

function jwqApplyTransferLineCarryingTotalAll() {
    var tbody = document.getElementById('jwqOrderLinesBody');
    if (!tbody) {
        return;
    }
    tbody.querySelectorAll('tr[data-item-id]').forEach(function (tr) {
        jwqApplyTransferLineCarryingTotalWt(tr);
    });
}
window.jwqApplyTransferLineCarryingTotalAll = jwqApplyTransferLineCarryingTotalAll;

function jwqLineQueueDisplayTotalWt(it) {
    it = it || {};
    if (it.queue_display_total_wt != null && String(it.queue_display_total_wt).trim() !== '') {
        var qn0 = parseFloat(String(it.queue_display_total_wt).replace(/,/g, ''));
        if (isFinite(qn0)) {
            return qn0;
        }
    }
    var f = parseFloat(it.final_weight);
    if (isFinite(f) && f > 0.0000001) {
        return f;
    }
    var n = parseFloat(it.net_weight);
    var g = parseFloat(it.gross_weight);
    var lo = typeof jwqLineServerLossGrams === 'function' ? jwqLineServerLossGrams(it) : 0;
    if (!isFinite(lo) || lo < 0) {
        lo = 0;
    }
    var d = NaN;
    if (it.diamond_wt != null && it.diamond_wt !== '') {
        d = parseFloat(String(it.diamond_wt).replace(/,/g, ''));
    } else if (it.diamond_weight != null && it.diamond_weight !== '') {
        d = parseFloat(String(it.diamond_weight).replace(/,/g, ''));
    }
    if (!isFinite(d) || d < 0) {
        d = 0;
    }
    var metal = 0;
    if (isFinite(n) && n > 0.0000001) {
        metal = n;
    } else if (isFinite(g) && g > 0.0000001) {
        metal = g;
    }
    return Math.max(0, metal - lo + d);
}

/** metal_wt − loss + diamond_wt (same as PHP fallback when final_weight is not authoritative). */
function jwqComputedTotalMetalLossDiamondFromItem(it) {
    it = it || {};
    var n = parseFloat(it.net_weight);
    var g = parseFloat(it.gross_weight);
    var lo = typeof jwqLineServerLossGrams === 'function' ? jwqLineServerLossGrams(it) : 0;
    if (!isFinite(lo) || lo < 0) {
        lo = 0;
    }
    var d = NaN;
    if (it.diamond_wt != null && it.diamond_wt !== '') {
        d = parseFloat(String(it.diamond_wt).replace(/,/g, ''));
    } else if (it.diamond_weight != null && it.diamond_weight !== '') {
        d = parseFloat(String(it.diamond_weight).replace(/,/g, ''));
    }
    if (!isFinite(d) || d < 0) {
        d = 0;
    }
    var metal = 0;
    if (isFinite(n) && n > 0.0000001) {
        metal = n;
    } else if (isFinite(g) && g > 0.0000001) {
        metal = g;
    }
    return Math.max(0, metal - lo + d);
}

/** Saved final_weight differs from metal−loss+diamond ⇒ user locked display total (e.g. 8 vs 9). */
function jwqItemTotalManuallyLockedFromServer(it) {
    it = it || {};
    var f = parseFloat(it.final_weight);
    if (!isFinite(f) || f <= 0.0000001) {
        return false;
    }
    var comp = jwqComputedTotalMetalLossDiamondFromItem(it);
    if (!isFinite(comp)) {
        return false;
    }
    return Math.abs(f - comp) > 0.05;
}

/** Guard: programmatic loss/total updates must not re-enter live weight handlers (circular input events). */
window.__jwqIsRecalculatingWeight = false;

/**
 * Live line weights — always: Total = Metal − Loss + Diamond.
 * - Edit total → Metal = Total − Diamond; Loss reconciled (≥ 0)
 * - Edit metal / loss / diamond → Total = Metal − Loss + Diamond
 */
function jwqLineFormulaTotal(mw, lw, dw) {
    if (!isFinite(mw) || mw < 0) {
        mw = 0;
    }
    if (!isFinite(lw) || lw < 0) {
        lw = 0;
    }
    if (!isFinite(dw) || dw < 0) {
        dw = 0;
    }
    var t = mw - lw + dw;
    return isFinite(t) && t >= 0 ? t : 0;
}

function jwqLineStoredTotalMismatch(tr, dwOptional) {
    var tw = jwqLineFieldNum(tr, 'total_wt');
    var mw = jwqLineFieldNum(tr, 'metal_wt');
    var lw = jwqLineFieldNum(tr, 'loss');
    var dw = isFinite(dwOptional) ? dwOptional : jwqLineFieldNum(tr, 'diamond_wt');
    if (!isFinite(lw) || lw < 0) {
        lw = 0;
    }
    if (!isFinite(mw) || mw < 0) {
        mw = 0;
    }
    if (!isFinite(dw) || dw < 0) {
        dw = 0;
    }
    var expected = jwqLineFormulaTotal(mw, lw, dw);
    return !isFinite(tw) || Math.abs(tw - expected) > 0.05;
}

function jwqItemFormulaTotalWt(it) {
    it = it || {};
    var mw = jwqLineOrigMetalWt(it);
    var lo = typeof jwqLineServerLossGrams === 'function' ? jwqLineServerLossGrams(it) : 0;
    if (!isFinite(lo) || lo < 0) {
        lo = 0;
    }
    var dw = NaN;
    if (it.diamond_wt != null && it.diamond_wt !== '') {
        dw = parseFloat(String(it.diamond_wt).replace(/,/g, ''));
    }
    if (!isFinite(dw) || dw <= 0) {
        dw = parseFloat(it.diamond_weight != null ? it.diamond_weight : NaN);
    }
    if (!isFinite(dw) || dw < 0) {
        dw = 0;
    }
    return jwqLineFormulaTotal(mw, lo, dw);
}

function jwqLineLiveRecalcWeights(tr, sourceField) {
    if (!tr || window.__jwqIsRecalculatingWeight) {
        return;
    }
    if (tr.getAttribute('data-jwq-freeze-weights') === '1' && !jwqLineStoredTotalMismatch(tr)) {
        var addExtraFreeze = typeof window.jwqIsWeightExtraMetalUi === 'function' && window.jwqIsWeightExtraMetalUi();
        if (!addExtraFreeze) {
            return;
        }
    }
    var mInp = jwqLineFieldEl(tr, 'metal_wt');
    var dInp = jwqLineFieldEl(tr, 'diamond_wt');
    var lInp = jwqLineFieldEl(tr, 'loss');
    var tInp = jwqLineFieldEl(tr, 'total_wt');
    if (!mInp || !tInp) {
        return;
    }
    var mw = jwqLineFieldNum(tr, 'metal_wt');
    var dw = dInp ? jwqLineFieldNum(tr, 'diamond_wt') : 0;
    var lw = lInp ? jwqLineFieldNum(tr, 'loss') : 0;
    if (!isFinite(mw) || mw < 0) {
        mw = 0;
    }
    if (!isFinite(dw) || dw < 0) {
        dw = 0;
    }
    if (!isFinite(lw) || lw < 0) {
        lw = 0;
    }
    window.__jwqIsRecalculatingWeight = true;
    try {
        if (sourceField === 'total_wt') {
            var tRead = jwqLineFieldEl(tr, 'total_wt');
            var twRaw = tRead ? parseFloat(String(tRead.value || '').replace(/,/g, '')) : NaN;
            if (!isFinite(twRaw)) {
                return;
            }
            if (typeof window.jwqIsAddWeightMode === 'function' && window.jwqIsAddWeightMode()) {
                if (typeof window.jwqApplyAddWeightManualMetalPreview === 'function') {
                    window.jwqApplyAddWeightManualMetalPreview(tr);
                }
                return;
            }
            if (typeof window.jwqIsReduceWeightMode === 'function' && window.jwqIsReduceWeightMode()) {
                if (typeof window.jwqApplyReduceWeightManualMetalPreview === 'function') {
                    window.jwqApplyReduceWeightManualMetalPreview(tr);
                }
                return;
            }
            var tw = twRaw < 0 ? 0 : twRaw;
            var fromDeptEl = document.getElementById('jwqFromDept');
            var fdAuto = fromDeptEl ? parseInt(fromDeptEl.value || '0', 10) : 0;
            if (jwqDeptHasAutoLoss(fdAuto)) {
                var baselineTw = jwqAutoLossBaselineForRow(tr);
                var lossAuto = baselineTw - tw;
                if (!isFinite(lossAuto) || lossAuto < 0) {
                    lossAuto = 0;
                }
                if (lInp) {
                    lInp.value = jwqNumOptDash3(lossAuto);
                }
                tr.setAttribute('data-jwq-total-manual', '1');
            } else {
                var newMetal = tw - dw;
                if (!isFinite(newMetal) || newMetal < 0) {
                    newMetal = 0;
                }
                mInp.value = jwqNum3(newMetal);
                tr.setAttribute('data-base-metal-wt', String(newMetal));
                if (lInp) {
                    var lossVal = newMetal + dw - tw;
                    if (!isFinite(lossVal) || lossVal < 0) {
                        lossVal = 0;
                    }
                    lInp.value = jwqNumOptDash3(lossVal);
                }
                tr.setAttribute('data-jwq-total-manual', '1');
            }
        } else {
            if (sourceField === 'metal_wt') {
                tr.setAttribute('data-base-metal-wt', String(mw));
            }
            if (typeof window.jwqIsAddWeightMode === 'function' && window.jwqIsAddWeightMode() && sourceField === 'metal_wt') {
                if (typeof window.jwqApplyAddWeightManualMetalPreview === 'function') {
                    window.jwqApplyAddWeightManualMetalPreview(tr);
                }
                return;
            }
            if (typeof window.jwqIsReduceWeightMode === 'function' && window.jwqIsReduceWeightMode() && sourceField === 'metal_wt') {
                if (typeof window.jwqApplyReduceWeightManualMetalPreview === 'function') {
                    window.jwqApplyReduceWeightManualMetalPreview(tr);
                }
                return;
            }
            if (typeof jwqIsNormalTransferQueueMode === 'function' && jwqIsNormalTransferQueueMode()) {
                if (sourceField === 'diamond_wt') {
                    tr.setAttribute('data-jwq-diamond-manual', '1');
                }
                if (typeof jwqApplyTransferLineCarryingTotalWt === 'function') {
                    jwqApplyTransferLineCarryingTotalWt(tr);
                }
                return;
            }
            var newTotal = mw - lw + dw;
            if (!isFinite(newTotal) || newTotal < 0) {
                newTotal = 0;
            }
            tInp.value = jwqNum3(newTotal);
            tr.removeAttribute('data-jwq-total-manual');
        }
        var tw2 = jwqLineFieldNum(tr, 'total_wt');
        var lw2 = lInp ? jwqLineFieldNum(tr, 'loss') : 0;
        var mw2 = jwqLineFieldNum(tr, 'metal_wt');
        var dw2 = dInp ? jwqLineFieldNum(tr, 'diamond_wt') : 0;
        if (!isFinite(lw2) || lw2 < 0) {
            lw2 = 0;
        }
        if (!isFinite(dw2) || dw2 < 0) {
            dw2 = 0;
        }
        if (!isFinite(mw2) || mw2 < 0) {
            mw2 = 0;
        }
        if (isFinite(tw2) && isFinite(mw2) && isFinite(lw2) && isFinite(dw2)) {
            var expected = mw2 - lw2 + dw2;
            if (isFinite(expected) && Math.abs(tw2 - expected) < 0.05) {
                tr.removeAttribute('data-jwq-total-manual');
            }
        }
    } finally {
        window.__jwqIsRecalculatingWeight = false;
    }
}
window.jwqLineLiveRecalcWeights = jwqLineLiveRecalcWeights;

/** @deprecated Use jwqLineLiveRecalcWeights; kept for callers expecting old name. */
function jwqRefreshComputedTotalWt(tr) {
    jwqLineLiveRecalcWeights(tr, 'metal_wt');
}
window.jwqRefreshComputedTotalWt = jwqRefreshComputedTotalWt;

function jwqCellRawByKey(key, it, orderRef) {
    it = it || {};
    switch (key) {
        case 'design_no':
            return it.design_no != null ? String(it.design_no) : '';
        case 'tag_no':
            return it.barcode != null ? String(it.barcode) : '';
        case 'description':
            return it.product_name != null ? String(it.product_name) : '';
        case 'order_no':
            return orderRef != null ? String(orderRef) : '';
        case 'total_wt':
            return jwqNum3(jwqLineQueueCarryingTotalWt(it));
        case 'metal_wt': {
            if (typeof jwqIsNormalTransferQueueMode === 'function' && jwqIsNormalTransferQueueMode()) {
                var carryM = jwqLineQueueCarryingTotalWt(it);
                if (!isFinite(carryM) || carryM < 0) {
                    carryM = 0;
                }
                return jwqNum3(carryM);
            }
            return jwqNum3(jwqLineOrigMetalWt(it));
        }
        case 'diamond_wt': {
            if (typeof jwqIsNormalTransferQueueMode === 'function' && jwqIsNormalTransferQueueMode()) {
                return jwqNum3(0);
            }
            var dStored = NaN;
            if (it.diamond_wt != null && it.diamond_wt !== '') {
                dStored = parseFloat(it.diamond_wt);
            }
            if (!isFinite(dStored) || dStored <= 0) {
                dStored = parseFloat(it.diamond_weight != null ? it.diamond_weight : NaN);
            }
            if (isFinite(dStored) && dStored > 0) {
                return jwqNum3(dStored);
            }
            var tw0 = jwqLineOrigTotalWt(it);
            var mw0 = jwqLineOrigMetalWt(it);
            if (isFinite(tw0) && isFinite(mw0) && tw0 > mw0 + 0.0000001) {
                return jwqNum3(tw0 - mw0);
            }
            return jwqNum3(0);
        }
        case 'total_purity':
            return jwqNum3(it.purity);
        case 'karat':
            return it.carat != null ? String(it.carat) : '';
        case 'total_qty':
            var jqq = parseFloat(it.quantity);
            if (isFinite(jqq) && jqq > 0) {
                return jwqNum3(jqq);
            }
            return jwqNum3(jqq);
        case 'price':
            return jwqNumOptDash2(it.rate);
        case 'dust_wastage_wt':
            if (it.wastage_wt != null && it.wastage_wt !== '') {
                return jwqNum3(it.wastage_wt);
            }
            return jwqNum3(0);
        case 'loss': {
            if (Object.prototype.hasOwnProperty.call(it, 'queue_display_loss_wt')) {
                var qlossS = it.queue_display_loss_wt != null ? String(it.queue_display_loss_wt).replace(/,/g, '') : '';
                var qxStrict = parseFloat(qlossS);
                if (isFinite(qxStrict) && qxStrict >= 0) {
                    return jwqNumOptDash3(qxStrict);
                }
            }
            var gRaw = it.gold_loss_1;
            var lRaw = it.loss_wt;
            var g = NaN;
            if (gRaw !== null && gRaw !== undefined && String(gRaw).trim() !== '') {
                g = parseFloat(String(gRaw).replace(/,/g, ''));
            }
            if (isFinite(g) && Math.abs(g) > 0.00001) {
                return jwqNumOptDash3(g);
            }
            if (lRaw !== null && lRaw !== undefined && String(lRaw).trim() !== '') {
                var l = parseFloat(String(lRaw).replace(/,/g, ''));
                if (isFinite(l)) {
                    return jwqNumOptDash3(l);
                }
            }
            if (isFinite(g)) {
                return jwqNumOptDash3(g);
            }
            return jwqNumOptDash3(0);
        }
        case 'profit':
            return jwqNumOptDash2(it.profit != null ? it.profit : it.net_amount);
        case 'expected_wt':
            return jwqNum3(it.expected_wt != null ? it.expected_wt : it.gross_weight);
        case 'product':
            return it.product_name != null ? String(it.product_name) : '';
        case 'requested_wt':
            return jwqNumOptDash3(it.requested_wt != null ? it.requested_wt : it.requested);
        case 'requested_purity':
            return jwqNumOptDash3(it.requested_purity);
        case 'alloy_wt':
            return jwqNumOptDash3(it.alloy_wt);
        case 'damage_qty':
            return jwqNumOptDash3(it.damage_qty != null ? it.damage_qty : it.damage_quantity);
        case 'damage_wt':
            return jwqNumOptDash3(it.damage_wt != null ? it.damage_wt : it.damage_weight);
        default:
            return '';
    }
}

function jwqIsNumericCol(key) {
    var numeric = ['total_wt', 'metal_wt', 'diamond_wt', 'total_purity', 'total_qty', 'dust_wastage_wt', 'loss', 'expected_wt', 'requested_wt', 'requested_purity', 'alloy_wt', 'damage_qty', 'damage_wt', 'price', 'profit'];
    return numeric.indexOf(key) >= 0;
}

/** Allow digits and at most one decimal point (empty allowed while typing). */
function jwqSanitizeDecimalInputValue(raw) {
    if (raw == null) {
        return '';
    }
    var s = String(raw).replace(/,/g, '');
    if (s === '') {
        return '';
    }
    var out = '';
    var dot = false;
    for (var i = 0; i < s.length; i++) {
        var c = s.charAt(i);
        if (c >= '0' && c <= '9') {
            out += c;
        } else if (c === '.' && !dot) {
            dot = true;
            out += c;
        }
    }
    return out;
}

function jwqDecimalInputGuardKeydown(e) {
    var inp = e.target;
    if (!inp || !inp.classList || !inp.classList.contains('jwq-cell-input--decimal')) {
        return;
    }
    var k = e.key;
    if (!k || k.length !== 1) {
        return;
    }
    if (e.ctrlKey || e.metaKey || e.altKey) {
        return;
    }
    if (k >= '0' && k <= '9') {
        return;
    }
    if (k === '.' && String(inp.value).indexOf('.') < 0) {
        return;
    }
    e.preventDefault();
}

function jwqDecimalInputGuardInput(e) {
    var inp = e.target;
    if (!inp || !inp.classList || !inp.classList.contains('jwq-cell-input--decimal')) {
        return;
    }
    var clean = jwqSanitizeDecimalInputValue(inp.value);
    if (clean !== inp.value) {
        inp.value = clean;
    }
}

function jwqDecimalInputGuardPaste(e) {
    var inp = e.target;
    if (!inp || !inp.classList || !inp.classList.contains('jwq-cell-input--decimal')) {
        return;
    }
    e.preventDefault();
    var clip = (e.clipboardData || window.clipboardData).getData('text');
    var clean = jwqSanitizeDecimalInputValue(clip);
    if (clean === '') {
        return;
    }
    var start = typeof inp.selectionStart === 'number' ? inp.selectionStart : inp.value.length;
    var end = typeof inp.selectionEnd === 'number' ? inp.selectionEnd : start;
    var merged = jwqSanitizeDecimalInputValue(String(inp.value).slice(0, start) + clean + String(inp.value).slice(end));
    inp.value = merged;
    inp.dispatchEvent(new Event('input', { bubbles: true }));
}

function initJwqDecimalInputGuards() {
    var root = document.getElementById('jwqModalOverlay');
    if (!root || root._jwqDecimalGuardBound) {
        return;
    }
    root._jwqDecimalGuardBound = true;
    root.addEventListener('keydown', jwqDecimalInputGuardKeydown);
    root.addEventListener('input', jwqDecimalInputGuardInput);
    root.addEventListener('paste', jwqDecimalInputGuardPaste);
}
window.initJwqDecimalInputGuards = initJwqDecimalInputGuards;

function jwqKaratSelectHtml(raw) {
    if (!window.mpCaratOptions || !Array.isArray(window.mpCaratOptions)) {
        window.mpCaratOptions = [];
    }
    var sel = String(raw == null ? '' : raw).trim();
    var opts = window.mpCaratOptions || [];
    var parts = ['<select class="jwq-cell-input" data-col-input="karat" data-field="karat">'];
    parts.push('<option value="">' + jwqEsc('-- Select --') + '</option>');
    var found = false;
    opts.forEach(function (c) {
        if (!c) return;
        var name = c.name != null ? String(c.name).trim() : '';
        if (name === '') return;
        var isSel = sel !== '' && name === sel;
        if (isSel) found = true;
        parts.push('<option value="' + jwqInputEsc(name) + '"' + (isSel ? ' selected' : '') + '>' + jwqEsc(name) + '</option>');
    });
    if (sel !== '' && !found) {
        parts.push('<option value="' + jwqInputEsc(sel) + '" selected>' + jwqEsc(sel) + '</option>');
    }
    parts.push('</select>');
    return parts.join('');
}

function jwqCellByKey(key, it, orderRef) {
    var raw = jwqCellRawByKey(key, it, orderRef);
    var weightExtra = typeof window.jwqIsWeightExtraMetalUi === 'function' && window.jwqIsWeightExtraMetalUi();
    if (weightExtra && (key === 'metal_wt' || key === 'total_wt' || key === 'diamond_wt')) {
        raw = jwqNum3(0);
    }
    if (key === 'order_no') {
        return jwqEsc(raw);
    }
    if (key === 'tag_no' || key === 'description' || key === 'total_wt') {
        return '<input class="jwq-cell-input jwq-cell-input--readonly" data-col-input="' + jwqEsc(key) + '" data-field="' + jwqEsc(key) + '" type="text" value="' + jwqInputEsc(raw) + '" readonly tabindex="-1">';
    }
    if (key === 'karat') {
        return jwqKaratSelectHtml(raw);
    }
    var cls = 'jwq-cell-input';
    var attrs = ' type="text"';
    if (jwqIsNumericCol(key)) {
        cls += ' jwq-cell-input--decimal';
        attrs += ' inputmode="decimal" autocomplete="off"';
    }
    return '<input class="' + cls + '" data-col-input="' + jwqEsc(key) + '" data-field="' + jwqEsc(key) + '"' + attrs + ' value="' + jwqInputEsc(raw) + '">';
}

function jwqBuildLineRowHtml(it, orderRef) {
    var keys = window.JWQ_ORDER_LINE_COL_KEYS || [];
    var trAttrs = '';
    var iid = 0;
    if (it) {
        iid = parseInt(it.id != null ? it.id : (it.item_id != null ? it.item_id : 0), 10) || 0;
    }
    if (iid > 0) {
        var addExtraRow = typeof window.jwqIsWeightExtraMetalUi === 'function' && window.jwqIsWeightExtraMetalUi();
        var owAuto = jwqLineQueueCarryingTotalWt(it);
        if (!isFinite(owAuto) || owAuto <= 0.0000001) {
            owAuto = jwqLineAutoLossBaselineWt(it);
        }
        var omAuto = jwqLineOrigMetalWt(it);
        var odAuto = jwqOrderDiamondPoolWtFromItem(it);
        var poolW = jwqOrderDiamondPoolWtFromItem(it);
        var slg = typeof jwqLineServerLossGrams === 'function' ? jwqLineServerLossGrams(it) : 0;
        trAttrs =
            ' data-item-id="' +
            iid +
            '"' +
            (owAuto > 0.0000001 ? ' data-orig-total-wt="' + String(owAuto) + '"' : '') +
            (omAuto > 0.0000001 ? ' data-jwq-orig-metal-wt="' + String(omAuto) + '"' : '') +
            (isFinite(odAuto) ? ' data-jwq-orig-diamond-wt="' + String(odAuto) + '"' : '') +
            ' data-jwq-saved-loss-grams="' +
            String(isFinite(slg) ? slg : 0) +
            '"' +
            ' data-jwq-order-diamond-wt="' +
            jwqNum3(Math.max(0, isFinite(poolW) ? poolW : 0)) +
            '"' +
            (!addExtraRow &&
            it &&
            Object.prototype.hasOwnProperty.call(it, 'queue_display_total_wt') &&
            (function () {
                var qn = parseFloat(String(it.queue_display_total_wt).replace(/,/g, ''));
                var fx = jwqItemFormulaTotalWt(it);
                return isFinite(qn) && isFinite(fx) && Math.abs(qn - fx) < 0.05;
            })()
                ? ' data-jwq-freeze-weights="1"'
                : '') +
            (it && typeof jwqItemTotalManuallyLockedFromServer === 'function' && jwqItemTotalManuallyLockedFromServer(it)
                ? ' data-jwq-total-manual="1"'
                : '') +
            (it && it.gross_weight != null && String(it.gross_weight).trim() !== ''
                ? ' data-jwq-orig-gross="' + jwqEsc(String(it.gross_weight)) + '"'
                : '') +
            (it && it.net_weight != null && String(it.net_weight).trim() !== ''
                ? ' data-jwq-orig-net="' + jwqEsc(String(it.net_weight)) + '"'
                : '') +
            (it && it.final_weight != null && String(it.final_weight).trim() !== ''
                ? ' data-jwq-orig-final="' + jwqEsc(String(it.final_weight)) + '"'
                : '');
    }
    var tds = keys.map(function (k) {
        return '<td data-col="' + k + '">' + jwqCellByKey(k, it, orderRef) + '</td>';
    });
    return '<tr' + trAttrs + '>' + tds.join('') + '</tr>';
}

function jwqDeptHasAutoLoss(deptId) {
    var id = parseInt(deptId, 10) || 0;
    if (id < 1) {
        return false;
    }
    var list = window.mpDepartments || [];
    for (var i = 0; i < list.length; i++) {
        var d = list[i];
        if (parseInt(d.id, 10) === id) {
            var al = d.auto_loss;
            if (al === true || al === 1 || al === '1') {
                return true;
            }
            if (typeof al === 'string' && al.toLowerCase() === 'on') {
                return true;
            }
            return parseInt(al, 10) === 1;
        }
    }
    return false;
}

function jwqLineColStorageKey() {
    return window.JWQ_LINE_COL_STORAGE || 'jobwork_queue_page_jwq_order_lines_hidden_columns';
}

function jwqAutoLossBaselineForRow(tr) {
    if (!tr) {
        return 0;
    }
    var orig = parseFloat(String(tr.getAttribute('data-orig-total-wt') || '').replace(/,/g, ''));
    if (isFinite(orig) && orig > 0.0000001) {
        return orig;
    }
    var origM = parseFloat(String(tr.getAttribute('data-jwq-orig-metal-wt') || '').replace(/,/g, ''));
    var dwCur = jwqLineFieldNum(tr, 'diamond_wt');
    if (!isFinite(dwCur) || dwCur < 0) {
        dwCur = 0;
    }
    if (isFinite(origM) && origM > 0.0000001) {
        return origM + dwCur;
    }
    var mwCur = jwqLineFieldNum(tr, 'metal_wt');
    if (isFinite(mwCur) && mwCur > 0.0000001) {
        return mwCur + dwCur;
    }
    return 0;
}

function jwqAutoLossResolveTarget(tr, sourceField, baseline) {
    var ewEl = jwqLineFieldEl(tr, 'expected_wt');
    var twEl = jwqLineFieldEl(tr, 'total_wt');
    var mwEl = jwqLineFieldEl(tr, 'metal_wt');
    var ew = ewEl ? parseFloat(String(ewEl.value || '').replace(/,/g, '')) : NaN;
    var tw = twEl ? parseFloat(String(twEl.value || '').replace(/,/g, '')) : NaN;
    var mw = mwEl ? parseFloat(String(mwEl.value || '').replace(/,/g, '')) : NaN;
    var eps = 0.0000001;
    if (sourceField === 'expected_wt' && isFinite(ew) && ew > eps) {
        return { target: ew, mode: 'expected' };
    }
    if (sourceField === 'metal_wt' && isFinite(mw) && mw > eps) {
        return { target: mw, mode: 'metal' };
    }
    if (sourceField === 'refresh' || sourceField === '') {
        if (isFinite(mw) && mw > eps && mw < baseline - eps) {
            return { target: mw, mode: 'metal' };
        }
        if (isFinite(ew) && ew > eps && ew < baseline - eps) {
            return { target: ew, mode: 'expected' };
        }
        if (isFinite(tw) && tw > eps && tw < baseline - eps) {
            return { target: tw, mode: 'total' };
        }
        return null;
    }
    if (isFinite(mw) && mw > eps && mw < baseline - eps) {
        return { target: mw, mode: 'metal' };
    }
    if (isFinite(ew) && ew > eps && ew < baseline - eps) {
        return { target: ew, mode: 'expected' };
    }
    if (isFinite(tw) && tw > eps && tw < baseline - eps) {
        return { target: tw, mode: 'total' };
    }
    return null;
}

function jwqMaybeApplyAutoLoss(tr, sourceField) {
    sourceField = sourceField || '';
    if (!tr || !tr.getAttribute('data-item-id')) {
        return;
    }
    if (typeof window.jwqIsWeightExtraMetalUi === 'function' && window.jwqIsWeightExtraMetalUi()) {
        return;
    }
    if (typeof window.jwqIsAddWeightMode === 'function' && window.jwqIsAddWeightMode()) {
        return;
    }
    if (typeof window.jwqIsReduceWeightMode === 'function' && window.jwqIsReduceWeightMode()) {
        return;
    }
    var fromDept = document.getElementById('jwqFromDept');
    var fd = fromDept ? parseInt(fromDept.value || '0', 10) : 0;
    if (!jwqDeptHasAutoLoss(fd)) {
        return;
    }
    var baseline = jwqAutoLossBaselineForRow(tr);
    if (!isFinite(baseline) || baseline <= 0.0000001) {
        return;
    }
    var resolved = jwqAutoLossResolveTarget(tr, sourceField, baseline);
    if (!resolved || !isFinite(resolved.target) || resolved.target >= baseline - 0.0000001) {
        return;
    }
    if (sourceField !== 'expected_wt' && sourceField !== 'refresh' && sourceField !== 'metal_wt') {
        var savedLossGrams = parseFloat(String(tr.getAttribute('data-jwq-saved-loss-grams') || '').replace(/,/g, ''));
        if (isFinite(savedLossGrams) && savedLossGrams > 0.00001) {
            return;
        }
    }
    var loss = baseline - resolved.target;
    if (loss < 0) {
        loss = 0;
    }
    var lossEl = jwqLineFieldEl(tr, 'loss');
    var twEl = jwqLineFieldEl(tr, 'total_wt');
    var mwEl = jwqLineFieldEl(tr, 'metal_wt');
    if (lossEl) {
        lossEl.value = loss > 0.0000001 ? jwqNumOptDash3(loss) : jwqNumOptDash3(0);
    }
    if (!twEl) {
        return;
    }
    var dwSync = jwqLineFieldNum(tr, 'diamond_wt');
    if (!isFinite(dwSync) || dwSync < 0) {
        dwSync = 0;
    }
    window.__jwqIsRecalculatingWeight = true;
    try {
        if (resolved.mode === 'expected') {
            var mwOrig = parseFloat(String(tr.getAttribute('data-jwq-orig-metal-wt') || '').replace(/,/g, ''));
            if (!isFinite(mwOrig) || mwOrig <= 0.0000001) {
                mwOrig = baseline;
            }
            if (mwEl) {
                mwEl.value = jwqNum3(mwOrig);
            }
            twEl.value = jwqNum3(jwqLineFormulaTotal(mwOrig, loss, dwSync));
        } else {
            twEl.value = jwqNum3(resolved.target);
        }
        tr.removeAttribute('data-jwq-total-manual');
        tr.removeAttribute('data-jwq-freeze-weights');
    } finally {
        window.__jwqIsRecalculatingWeight = false;
    }
}

function jwqRefreshAutoLossAllRows() {
    var tbody = document.getElementById('jwqOrderLinesBody');
    if (!tbody) {
        return;
    }
    tbody.querySelectorAll('tr[data-item-id]').forEach(function (tr) {
        jwqMaybeApplyAutoLoss(tr, 'refresh');
    });
}

/** Roll orphan item id 0 and stale item ids into first visible order line (same for plus and used-pick maps). */
function jwqRollMaterialDiamondOrphanIntoFirst(byItem, tbody) {
    if (!byItem || !tbody) {
        return;
    }
    var firstRowId = null;
    tbody.querySelectorAll('tr[data-item-id]').forEach(function (tr) {
        var rowItemId = parseInt(tr.getAttribute('data-item-id') || '0', 10);
        if (firstRowId === null && rowItemId > 0) {
            firstRowId = rowItemId;
        }
    });
    if (firstRowId !== null && byItem[0] > 0) {
        byItem[firstRowId] = (byItem[firstRowId] || 0) + byItem[0];
        delete byItem[0];
    }
    if (firstRowId !== null) {
        var visibleIds = {};
        tbody.querySelectorAll('tr[data-item-id]').forEach(function (tr) {
            var rid = parseInt(tr.getAttribute('data-item-id') || '0', 10);
            if (rid > 0) {
                visibleIds[rid] = true;
            }
        });
        var unmatched = 0;
        Object.keys(byItem).forEach(function (k) {
            var kid = parseInt(k || '0', 10);
            if (kid > 0 && !visibleIds[kid]) {
                unmatched += byItem[kid] || 0;
                delete byItem[kid];
            }
        });
        if (unmatched > 0) {
            byItem[firstRowId] = (byItem[firstRowId] || 0) + unmatched;
        }
    }
}

/** Prefer the material tbody under #jwqMatTableWrap when present (standalone jobwork-queue page). */
function jwqGetJobworkMaterialBody() {
    var wrap = document.getElementById('jwqMatTableWrap');
    if (wrap) {
        var tb = wrap.querySelector('#jwqMaterialBody');
        if (tb) {
            return tb;
        }
    }
    var overlay = document.getElementById('jwqModalOverlay');
    if (overlay) {
        var tb2 = overlay.querySelector('#jwqMaterialBody');
        if (tb2) {
            return tb2;
        }
    }
    return document.getElementById('jwqMaterialBody');
}

/** Last-known issue weight for a stock line (from mp-get-jobwork-queue-diamonds). */
function jwqDiamondIssueWeightFromServerByStockId(stockId) {
    var sid = parseInt(String(stockId != null ? stockId : '0'), 10) || 0;
    if (sid < 1) {
        return 0;
    }
    var srv = window.__jwqLastDiamondServerItems || [];
    var i;
    var r;
    var iw;
    for (i = 0; i < srv.length; i++) {
        r = srv[i];
        if (!r) {
            continue;
        }
        if ((parseInt(String(r.stock_id != null ? r.stock_id : '0'), 10) || 0) !== sid) {
            continue;
        }
        iw = parseFloat(String((r.weight_out != null ? r.weight_out : (r.weight != null ? r.weight : '')) || '').replace(/,/g, '')) || 0;
        if (iw > 0.0000001) {
            return iw;
        }
    }
    return 0;
}

/**
 * Display weight on the material row; for picks from "(i) Diamonds used" fall back to data-weight then server issue
 * so reduce-to-zero on the grid still releases stock and syncs Diamond Wt.
 */
function jwqMaterialDiamondRowEffectiveWeight(tr) {
    if (!tr) {
        return 0;
    }
    var wtTd = tr.querySelector('.jwq-mat-wt');
    var tds = tr.querySelectorAll('td');
    var wtTxt = wtTd ? wtTd.textContent : (tds[2] ? tds[2].textContent : '');
    var wt = parseFloat(String(wtTxt || '').replace(/,/g, '')) || 0;
    if (wt <= 0.0000001 && tr.dataset && tr.dataset.weight) {
        wt = parseFloat(String(tr.dataset.weight).replace(/,/g, '')) || 0;
    }
    if (wt <= 0.0000001 && tr.getAttribute('data-jwq-from-used-modal') === '1') {
        var sid = parseInt(tr.getAttribute('data-stock-id') || (tr.dataset && tr.dataset.stockId) || '0', 10) || 0;
        wt = jwqDiamondIssueWeightFromServerByStockId(sid);
    }
    return wt;
}

/**
 * Per jobwork line item: diamond material weights split for line sync.
 * plus = rows from stock / server grid; usedPick = rows tagged from "Diamonds used" (already on job — reduces header pool).
 */
function jwqMaterialDiamondWeightByItemSplit(matBody, tbody) {
    var plus = {};
    var usedPick = {};
    if (!matBody || !tbody) {
        return { plus: plus, usedPick: usedPick };
    }
    matBody.querySelectorAll('.jwq-material-diamond-row').forEach(function (tr) {
        if (tr.getAttribute('data-jwq-exclude-from-line-sync') === '1') {
            return;
        }
        var itemId = parseInt(tr.getAttribute('data-jobwork-item-id') || '0', 10);
        if (itemId < 1) {
            itemId = 0;
        }
        var wt = jwqMaterialDiamondRowEffectiveWeight(tr);
        if (wt <= 0) {
            return;
        }
        var bucket = tr.getAttribute('data-jwq-from-used-modal') === '1' ? usedPick : plus;
        bucket[itemId] = (bucket[itemId] || 0) + wt;
    });
    jwqRollMaterialDiamondOrphanIntoFirst(plus, tbody);
    jwqRollMaterialDiamondOrphanIntoFirst(usedPick, tbody);
    return { plus: plus, usedPick: usedPick };
}

/** Stock ids and barcodes on the diamond material grid (any dept). */
function jwqStockIdsOnMaterialDiamondGrid(matBody) {
    var sids = {};
    var barcodes = {};
    if (!matBody) {
        return { sids: sids, barcodes: barcodes };
    }
    matBody.querySelectorAll('.jwq-material-diamond-row').forEach(function (tr) {
        var sid = parseInt(tr.getAttribute('data-stock-id') || tr.dataset.stockId || '0', 10) || 0;
        if (sid > 0) {
            sids[sid] = true;
        }
        var bc = String(tr.getAttribute('data-barcode') || '').trim().toUpperCase();
        if (!bc) {
            var tds = tr.querySelectorAll('td');
            var t1 = tds[1] ? String(tds[1].textContent || '').trim() : '';
            var sepIdx = t1.indexOf(' — ');
            if (sepIdx === -1) {
                sepIdx = t1.indexOf('\u2014');
            }
            if (sepIdx === -1) {
                sepIdx = t1.indexOf(' - ');
            }
            bc = (sepIdx > 0 ? t1.slice(0, sepIdx).trim() : t1).toUpperCase();
        }
        if (bc) {
            barcodes[bc] = true;
        }
    });
    return { sids: sids, barcodes: barcodes };
}

function jwqIssueStockSidSetFromServer(serverRows) {
    var s = {};
    if (!Array.isArray(serverRows)) {
        return s;
    }
    serverRows.forEach(function (r) {
        if (!r || String(r.row_source || '') === 'line_fallback' || jwqDiamondUsedRowIsSummary(r)) {
            return;
        }
        var sid = parseInt(String(r.stock_id != null ? r.stock_id : '0'), 10) || 0;
        var bc = String(r.barcode || '').trim();
        if (sid < 1 || bc === '' || bc === '—') {
            return;
        }
        s[sid] = true;
    });
    return s;
}

/** Plus (non–used-modal) diamond rows whose stock is already on this job's issue ledger — already part of BOM pool; must not add again via +sumPlus. */
function jwqMaterialDiamondPlusIssuedByItem(matBody, tbody, issueSidSet) {
    var by = {};
    if (!matBody || !tbody) {
        return by;
    }
    if (!issueSidSet || typeof issueSidSet !== 'object') {
        return by;
    }
    matBody.querySelectorAll('.jwq-material-diamond-row').forEach(function (tr) {
        if (tr.getAttribute('data-jwq-exclude-from-line-sync') === '1') {
            return;
        }
        if (tr.getAttribute('data-jwq-from-used-modal') === '1') {
            return;
        }
        var sid = parseInt(tr.getAttribute('data-stock-id') || tr.dataset.stockId || '0', 10) || 0;
        if (!issueSidSet[sid]) {
            return;
        }
        var itemId = parseInt(tr.getAttribute('data-jobwork-item-id') || '0', 10) || 0;
        if (itemId < 1) {
            itemId = 0;
        }
        var wt = jwqMaterialDiamondRowEffectiveWeight(tr);
        if (wt <= 0) {
            return;
        }
        by[itemId] = (by[itemId] || 0) + wt;
    });
    jwqRollMaterialDiamondOrphanIntoFirst(by, tbody);
    return by;
}

/**
 * Weight of barcoded diamond issues still "open" (listed in Diamonds used) — not on the material grid.
 * When issued diamonds are not on the material grid, BOM line diamond must still drop by this amount.
 */
function jwqRecomputeOpenIssueDiamondWtByItem(serverRows) {
    var matBody = jwqGetJobworkMaterialBody();
    var tbody = document.getElementById('jwqOrderLinesBody');
    var by = {};
    if (!tbody) {
        window.__jwqOpenIssueDiamondWtByItem = by;
        return;
    }
    if (!Array.isArray(serverRows)) {
        serverRows = [];
    }
    var onGrid = jwqStockIdsOnMaterialDiamondGrid(matBody);
    serverRows.forEach(function (r) {
        if (!r || String(r.row_source || '') === 'line_fallback' || jwqDiamondUsedRowIsSummary(r)) {
            return;
        }
        var sid = parseInt(String(r.stock_id != null ? r.stock_id : '0'), 10) || 0;
        var bc = String(r.barcode || '').trim();
        var bcU = bc.toUpperCase();
        if (sid < 1 || bc === '' || bc === '—') {
            return;
        }
        if (onGrid.sids[sid]) {
            return;
        }
        if (bcU && onGrid.barcodes[bcU]) {
            return;
        }
        var wRaw = r.weight_out != null ? r.weight_out : (r.weight != null ? r.weight : '');
        var wNum = parseFloat(String(wRaw).replace(/,/g, '')) || 0;
        if (wNum <= 0.0000001) {
            return;
        }
        var itemId = parseInt(String(r.jobwork_order_item_id != null ? r.jobwork_order_item_id : '0'), 10) || 0;
        by[itemId] = (by[itemId] || 0) + wNum;
    });
    jwqRollMaterialDiamondOrphanIntoFirst(by, tbody);
    window.__jwqOpenIssueDiamondWtByItem = by;
}

function jwqInitLineBaseDiamondWt(tr, dInp, tInp, mInp, sumMat) {
    var curD = parseFloat(String(dInp.value || '').replace(/,/g, '')) || 0;
    var inferred = 0;
    if (tInp && mInp) {
        var tw0 = parseFloat(tInp.value);
        var mw0 = parseFloat(mInp.value);
        if (isFinite(tw0) && isFinite(mw0) && tw0 > mw0 + 0.0000001) {
            inferred = tw0 - mw0;
        }
    }
    var baseDiamond = 0;
    if (curD > 0.0000001) {
        if (sumMat <= curD + 0.0000001) {
            baseDiamond = curD - sumMat;
        } else {
            baseDiamond = curD;
        }
    } else if (inferred > 0.0000001 && sumMat <= 0.0000001) {
        baseDiamond = inferred;
    }
    if (baseDiamond < 0) {
        baseDiamond = 0;
    }
    tr.setAttribute('data-jwq-base-diamond-wt', jwqNum3(baseDiamond));
    return baseDiamond;
}

function jwqRefreshLineDiamondBaseFromUi(tr) {
    var matBody = jwqGetJobworkMaterialBody();
    var tbody = document.getElementById('jwqOrderLinesBody');
    var dInp = typeof jwqLineFieldEl === 'function' ? jwqLineFieldEl(tr, 'diamond_wt') : null;
    if (!matBody || !tbody || !dInp) {
        return;
    }
    var split = jwqMaterialDiamondWeightByItemSplit(matBody, tbody);
    var itemId = parseInt(tr.getAttribute('data-item-id') || '0', 10);
    var sumPlus = split.plus[itemId] || 0;
    var D = parseFloat(String(dInp.value || '').replace(/,/g, '')) || 0;
    tr.setAttribute('data-jwq-base-diamond-wt', jwqNum3(Math.max(0, D - sumPlus)));
}

/** Refresh __jwqLastDiamondServerItems from DB (no material grid rebuild), then callback — keeps open-issue weights in sync before save / sync. */
function jwqFetchDiamondServerItemsThen(callback) {
    var jwo = document.getElementById('jwqCurrentJwoId');
    var jwoId = jwo ? (parseInt(jwo.value || '0', 10) || 0) : 0;
    if (jwoId < 1) {
        if (typeof callback === 'function') {
            callback();
        }
        return;
    }
    fetch('ajax/mp-get-jobwork-queue-diamonds.php?jobwork_order_id=' + encodeURIComponent(String(jwoId)), { credentials: 'same-origin' })
        .then(function (r) {
            return r.json();
        })
        .then(function (d) {
            var rows = (d && d.ok && Array.isArray(d.items)) ? d.items : [];
            window.__jwqLastDiamondServerItems = rows.filter(function (row) {
                return typeof jwqIsServerDiamondRowRemovedFromUi !== 'function' || !jwqIsServerDiamondRowRemovedFromUi(row);
            });
            if (typeof callback === 'function') {
                callback();
            }
        })
        .catch(function () {
            if (typeof callback === 'function') {
                callback();
            }
        });
}
window.jwqFetchDiamondServerItemsThen = jwqFetchDiamondServerItemsThen;

function jwqIsNormalTransferQueueMode() {
    if (typeof window.jwqIsWeightExtraMetalUi === 'function' && window.jwqIsWeightExtraMetalUi()) {
        return false;
    }
    if (typeof window.jwqIsAddWeightMode === 'function' && window.jwqIsAddWeightMode()) {
        return false;
    }
    if (typeof window.jwqIsReduceWeightMode === 'function' && window.jwqIsReduceWeightMode()) {
        return false;
    }
    return true;
}
window.jwqIsNormalTransferQueueMode = jwqIsNormalTransferQueueMode;

function jwqSyncOrderLineDiamondWtFromMaterialTable() {
    var tbody = document.getElementById('jwqOrderLinesBody');
    var matBody = jwqGetJobworkMaterialBody();
    if (!tbody || !matBody) {
        return;
    }
    jwqRecomputeOpenIssueDiamondWtByItem(window.__jwqLastDiamondServerItems || []);
    var split = jwqMaterialDiamondWeightByItemSplit(matBody, tbody);
    var byPlus = split.plus;
    var byUsedPick = split.usedPick;
    var issueSidSet = jwqIssueStockSidSetFromServer(window.__jwqLastDiamondServerItems || []);
    var byPlusIssued = jwqMaterialDiamondPlusIssuedByItem(matBody, tbody, issueSidSet);
    tbody.querySelectorAll('tr[data-item-id]').forEach(function (tr) {
        var addExtraSyncEarly = typeof window.jwqIsWeightExtraMetalUi === 'function' && window.jwqIsWeightExtraMetalUi();
        if (addExtraSyncEarly) {
            var dInpEarly = jwqLineFieldEl(tr, 'diamond_wt');
            if (dInpEarly) {
                dInpEarly.value = jwqNum3(0);
            }
            if (typeof window.jwqIsAddWeightMode === 'function' && window.jwqIsAddWeightMode()
                && typeof window.jwqApplyAddWeightManualMetalPreview === 'function') {
                window.jwqApplyAddWeightManualMetalPreview(tr);
            } else if (typeof window.jwqIsReduceWeightMode === 'function' && window.jwqIsReduceWeightMode()
                && typeof window.jwqApplyReduceWeightManualMetalPreview === 'function') {
                window.jwqApplyReduceWeightManualMetalPreview(tr);
            }
            return;
        }
        var itemId = parseInt(tr.getAttribute('data-item-id') || '0', 10);
        var dInp = jwqLineFieldEl(tr, 'diamond_wt');
        if (!dInp) {
            return;
        }
        var sumPlus = byPlus[itemId] || 0;
        var sumUsedPick = byUsedPick[itemId] || 0;
        var sumOpenIssued = 0;
        var oiMap = window.__jwqOpenIssueDiamondWtByItem;
        if (oiMap && typeof oiMap === 'object') {
            sumOpenIssued = oiMap[itemId] || 0;
        }
        var gridDiamondWt = sumPlus + sumUsedPick;
        var tInp = jwqLineFieldEl(tr, 'total_wt');
        var mInp = jwqLineFieldEl(tr, 'metal_wt');
        var orderPool = parseFloat(tr.getAttribute('data-jwq-order-diamond-wt'));
        if (!isFinite(orderPool) || orderPool < 0) {
            orderPool = 0;
        }
        if (tr.getAttribute('data-jwq-diamond-manual') === '1') {
            /* User typed Diamond Wt: only real issued/grid diamonds may change it, not the BOM pool gap. */
            orderPool = 0;
        }
        if (orderPool < 0.0000001) {
            var twInf = jwqLineFieldNum(tr, 'total_wt');
            var mwInf = jwqLineFieldNum(tr, 'metal_wt');
            var gap = (isFinite(twInf) && isFinite(mwInf) && twInf > mwInf + 0.0000001) ? (twInf - mwInf) : 0;
            var onJobPre = sumOpenIssued + gridDiamondWt;
            if (gap > 0.0000001 && onJobPre > 0.0000001 && Math.abs(gap - onJobPre) < 0.05) {
                /* total − metal already equals issued + grid; do not treat as a separate BOM pool (avoids double-count). */
                orderPool = 0;
            } else if (gap > 0.0000001) {
                orderPool = gap;
            }
        }
        if (orderPool < 0.0000001 && sumUsedPick > 0.0000001) {
            var dCurBoot = parseFloat(String(dInp.value || '').replace(/,/g, '')) || 0;
            var pIssBoot = byPlusIssued[itemId] || 0;
            orderPool = sumUsedPick + dCurBoot - sumPlus + pIssBoot;
            if (orderPool < sumUsedPick - 0.00001) {
                orderPool = sumUsedPick;
            }
            /* Do not persist heuristic pool on the row: writing data-jwq-order-diamond-wt caused
             * orderPool to grow across syncs (feedback with total_wt = metal + diamond). */
        }
        var sumPlusIssued = byPlusIssued[itemId] || 0;
        var twLine = jwqLineFieldNum(tr, 'total_wt');
        var mwLine = jwqLineFieldNum(tr, 'metal_wt');
        var onJobDiamond = sumOpenIssued + gridDiamondWt;
        var twExplained =
            isFinite(twLine) &&
            isFinite(mwLine) &&
            onJobDiamond > 0.0000001 &&
            Math.abs(twLine - mwLine - onJobDiamond) < 0.05;
        var twMetalEqual =
            isFinite(twLine) && isFinite(mwLine) && Math.abs(twLine - mwLine) < 0.0000001;
        var poolNegligible = orderPool < 0.0000001;
        var noBomDiamondPool = poolNegligible && twMetalEqual;
        var dWt;
        if (twExplained) {
            /* total_wt already matches metal + diamonds on this job — use that, not pool − open (avoids duplicate grams). */
            dWt = onJobDiamond;
        } else if (noBomDiamondPool && (sumOpenIssued > 0.0000001 || gridDiamondWt > 0.0000001)) {
            /* No implicit diamond mass (total ≈ metal): show actual diamond weight = open issues + grid rows. */
            dWt = onJobDiamond;
        } else {
            dWt = orderPool - sumOpenIssued - sumUsedPick - sumPlusIssued + sumPlus;
        }
        if (!isFinite(dWt) || dWt < 0) {
            dWt = 0;
        }
        /* total_wt − metal_wt can lag behind the grid after adding a row; do not undercut grid + open issue sum. */
        var lineGap = (isFinite(twLine) && isFinite(mwLine) && twLine > mwLine + 0.0000001) ? (twLine - mwLine) : 0;
        if (onJobDiamond > dWt + 0.0001 && onJobDiamond > lineGap + 0.0001) {
            dWt = onJobDiamond;
        }
        if (typeof jwqIsNormalTransferQueueMode === 'function' && jwqIsNormalTransferQueueMode()
            && tr.getAttribute('data-jwq-diamond-manual') !== '1'
            && onJobDiamond < 0.0000001) {
            dWt = 0;
        }
        var prevDWt = jwqLineFieldNum(tr, 'diamond_wt');
        if (!isFinite(prevDWt) || prevDWt < 0) {
            prevDWt = 0;
        }
        dInp.value = jwqNum3(dWt);
        var diamondChanged = Math.abs(prevDWt - dWt) > 0.0001;

        var mismatch = jwqLineStoredTotalMismatch(tr, dWt);
        if (tr.getAttribute('data-jwq-freeze-weights') === '1' && !mismatch && !diamondChanged) {
            return;
        }
        if (tr.getAttribute('data-jwq-total-manual') === '1' && !diamondChanged && !mismatch) {
            return;
        }
        if (typeof window.jwqIsAddWeightMode === 'function' && window.jwqIsAddWeightMode()) {
            var addExtraSync = typeof window.jwqIsAddWeightExtraMetalUi === 'function' && window.jwqIsAddWeightExtraMetalUi();
            if (addExtraSync) {
                if (typeof window.jwqApplyAddWeightManualMetalPreview === 'function') {
                    window.jwqApplyAddWeightManualMetalPreview(tr);
                }
                return;
            }
            var addManual = parseFloat(tr.getAttribute('data-jwq-manual-add-grams') || '');
            var addOrigT = parseFloat(tr.getAttribute('data-jwq-orig-total-before-add') || tr.getAttribute('data-orig-total-wt') || '');
            if ((isFinite(addManual) && addManual > 0.0001)
                || (isFinite(twLine) && isFinite(addOrigT) && twLine > addOrigT + 0.0001)) {
                if (typeof window.jwqApplyAddWeightManualMetalPreview === 'function') {
                    window.jwqApplyAddWeightManualMetalPreview(tr);
                }
                return;
            }
        }

        if (tInp && mInp) {
            window.__jwqIsRecalculatingWeight = true;
            try {
                if (typeof jwqIsNormalTransferQueueMode === 'function' && jwqIsNormalTransferQueueMode()) {
                    var totalFix = jwqResolveTransferCarryingTotal(tr);
                    if (totalFix <= 0.0000001) {
                        totalFix = jwqLineFieldNum(tr, 'total_wt');
                    }
                    if (!isFinite(dWt) || dWt < 0) {
                        dWt = 0;
                    }
                    if (isFinite(totalFix) && dWt > totalFix + 0.0001) {
                        dWt = totalFix;
                        dInp.value = jwqNum3(dWt);
                    }
                    var newMetal = Math.max(0, (isFinite(totalFix) ? totalFix : 0) - dWt);
                    if (typeof jwqClearStaleTransferInferredLoss === 'function') {
                        jwqClearStaleTransferInferredLoss(tr, newMetal, dWt, totalFix);
                    }
                    mInp.value = jwqNum3(newMetal);
                    if (isFinite(totalFix) && totalFix > 0.0000001) {
                        tInp.value = jwqNum3(totalFix);
                    }
                    tr.setAttribute('data-base-metal-wt', String(newMetal));
                    tr.removeAttribute('data-jwq-total-manual');
                    tr.removeAttribute('data-jwq-freeze-weights');
                } else {
                var baseMetal = jwqLineFieldNum(tr, 'metal_wt');
                if (!isFinite(baseMetal) || baseMetal < 0) {
                    baseMetal = parseFloat(tr.getAttribute('data-base-metal-wt') || '');
                }
                if (baseMetal < 0.0000001 && dWt > 0.0000001) {
                    var origM = parseFloat(tr.getAttribute('data-orig-total-wt') || '');
                    if (isFinite(origM) && origM > 0.0000001) {
                        baseMetal = origM;
                    }
                }
                if (!isFinite(baseMetal) || baseMetal < 0) {
                    baseMetal = 0;
                }
                tr.setAttribute('data-base-metal-wt', String(baseMetal));
                var lossNum = jwqLineFieldNum(tr, 'loss');
                if (!isFinite(lossNum) || lossNum < 0) {
                    lossNum = 0;
                }
                var carryingOrig = parseFloat(tr.getAttribute('data-orig-total-wt') || '');
                if (typeof jwqClearStaleTransferInferredLoss === 'function') {
                    lossNum = jwqClearStaleTransferInferredLoss(tr, baseMetal, dWt, carryingOrig);
                }
                var newTotal = baseMetal + dWt - lossNum;
                if (typeof jwqIsNormalTransferQueueMode === 'function' && jwqIsNormalTransferQueueMode()
                    && isFinite(carryingOrig) && carryingOrig > newTotal + 0.0001) {
                    newTotal = carryingOrig;
                }
                if (!isFinite(newTotal) || newTotal < 0) {
                    newTotal = 0;
                }
                mInp.value = jwqNum3(baseMetal);
                tInp.value = jwqNum3(newTotal);
                tr.removeAttribute('data-jwq-total-manual');
                tr.removeAttribute('data-jwq-freeze-weights');
                }
            } finally {
                window.__jwqIsRecalculatingWeight = false;
            }
        }
    });
    if (typeof jwqRefreshAutoLossAllRows === 'function') {
        jwqRefreshAutoLossAllRows();
    }
    if (typeof jwqApplyTransferLineCarryingTotalAll === 'function') {
        jwqApplyTransferLineCarryingTotalAll();
    }
}
window.jwqSyncOrderLineDiamondWtFromMaterialTable = jwqSyncOrderLineDiamondWtFromMaterialTable;

function jwqCollectQueueLinePayload() {
    var tbody = document.getElementById('jwqOrderLinesBody');
    if (!tbody) {
        return [];
    }
    var out = [];
    tbody.querySelectorAll('tr[data-item-id]').forEach(function (tr) {
        var id = parseInt(tr.getAttribute('data-item-id'), 10);
        if (!id) {
            return;
        }
        var total_wt = jwqLineFieldNum(tr, 'total_wt');
        var metal_wt = jwqLineFieldNum(tr, 'metal_wt');
        var diamond_wt = jwqLineFieldNum(tr, 'diamond_wt');
        var dust_wastage_wt = jwqLineFieldNum(tr, 'dust_wastage_wt');
        var loss = jwqLineFieldNum(tr, 'loss');
        if (!isFinite(total_wt)) {
            return;
        }
        if (!isFinite(metal_wt)) {
            metal_wt = total_wt;
        }
        if (!isFinite(diamond_wt) || diamond_wt < 0) {
            diamond_wt = 0;
        }
        var line = { item_id: id, total_wt: total_wt, metal_wt: metal_wt, diamond_wt: diamond_wt };
        if (isFinite(dust_wastage_wt) && dust_wastage_wt >= 0) {
            line.dust_wastage_wt = dust_wastage_wt;
        }
        if (isFinite(loss) && loss >= 0) {
            line.loss = loss;
        }
        out.push(line);
    });
    return out;
}

function jwqDebugLogQueueLinesBeforeSave() {
    if (!window.console || typeof console.log !== 'function') {
        return;
    }
    var tbody = document.getElementById('jwqOrderLinesBody');
    if (!tbody) {
        return;
    }
    tbody.querySelectorAll('tr[data-item-id]').forEach(function (tr) {
        var id = parseInt(tr.getAttribute('data-item-id'), 10) || 0;
        var og = tr.getAttribute('data-jwq-orig-gross');
        var on = tr.getAttribute('data-jwq-orig-net');
        var of = tr.getAttribute('data-jwq-orig-final');
        var mw = jwqLineFieldNum(tr, 'metal_wt');
        var dw = jwqLineFieldNum(tr, 'diamond_wt');
        var lw = jwqLineFieldNum(tr, 'loss');
        var tw = jwqLineFieldNum(tr, 'total_wt');
        var calc = (isFinite(mw) ? mw : 0) + (isFinite(dw) ? dw : 0) - (isFinite(lw) ? lw : 0);
        if (!isFinite(calc) || calc < 0) {
            calc = 0;
        }
        console.log('JWQ_SAVE_DEBUG_ROW', {
            item_id: id,
            gross_weight: og,
            net_weight: on,
            final_weight: of,
            metal_wt: mw,
            diamond_wt: dw,
            loss_wt: lw,
            total_wt_input: tw,
            calculated_total_wt: calc
        });
    });
}

function jwqApplyStoredLineColumnVisibility() {
    try {
        var raw = localStorage.getItem(jwqLineColStorageKey());
        var hidden = raw ? JSON.parse(raw) : [];
        var table = document.getElementById('jwqOrderLinesTable');
        if (!table) return;
        table.querySelectorAll('th[data-col], td[data-col]').forEach(function (el) {
            var col = el.getAttribute('data-col');
            if (hidden.indexOf(col) >= 0) el.classList.add('col-hidden');
            else el.classList.remove('col-hidden');
        });
        if (typeof AuragoldColReorder !== 'undefined' && typeof AuragoldColReorder.refresh === 'function') {
            AuragoldColReorder.refresh(table);
        }
    } catch (e) {}
}

function initJwqOrderLinesColReorderResize() {
    if (typeof AuragoldColReorder === 'undefined') return;
    var table = document.getElementById('jwqOrderLinesTable');
    if (!table) return;
    var base = (window.JWQ_LINE_COL_STORAGE || 'jobwork_queue_page_jwq_order_lines_hidden_columns')
        .replace(/_hidden_columns$/, '');
    AuragoldColReorder.init(table, {
        storageKey: base + '_column_order',
        widthsStorageKey: base + '_column_widths',
        minWidth: 72
    });
}

function jwqFillDeptSelect(sel, placeholder) {
    if (!sel) return;
    sel.innerHTML = '';
    var o = document.createElement('option');
    o.value = '';
    o.textContent = placeholder || '-- Select --';
    sel.appendChild(o);
    (window.mpDepartments || []).forEach(function (d) {
        var op = document.createElement('option');
        op.value = String(d.id);
        op.textContent = d.dept_name || d.name || '';
        sel.appendChild(op);
    });
}

function jwqFillUserSelectForDept(sel, deptId) {
    if (!sel) return;
    sel.innerHTML = '';
    var o = document.createElement('option');
    o.value = '';
    o.textContent = '-- Select --';
    sel.appendChild(o);
    var users = (window.mpDepartmentUsers && window.mpDepartmentUsers[deptId]) ? window.mpDepartmentUsers[deptId] : [];
    if (!Array.isArray(users)) users = [];
    users.forEach(function (u) {
        var op = document.createElement('option');
        op.value = String(u.id);
        op.textContent = u.name || '';
        sel.appendChild(op);
    });
}

/** Load Jobwork Queue no. from server (Bill Series); updates title, hidden field, and boot button data attribute. */
function jwqFetchQueueNoForDisplay(jwoId, queueEl, queueHid, bootBtn) {
    if (!jwoId || jwoId < 1) return;
    fetch('ajax/mp-get-jobwork-queue-no.php?jobwork_order_id=' + encodeURIComponent(String(jwoId)), { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data || !data.ok) return;
            var q = (data.jobwork_queue_no || '').trim();
            if (!q) return;
            if (queueEl) queueEl.textContent = q;
            if (queueHid) queueHid.value = q;
            if (bootBtn && bootBtn.setAttribute) {
                bootBtn.setAttribute('data-jobwork-queue-no', q);
            }
            document.querySelectorAll('.jwq-order-boot[data-jwo-id="' + jwoId + '"]').forEach(function (b) {
                b.setAttribute('data-jobwork-queue-no', q);
            });
        })
        .catch(function () {});
}

function jwqGetOrderRefForLines() {
    var boot = document.getElementById('jwqDynamicBoot');
    var pb = document.getElementById('jwqPageBootstrapBtn');
    var jn = boot ? (boot.getAttribute('data-jobwork-no') || '').trim() : '';
    var sn = boot ? (boot.getAttribute('data-sale-order-no') || '').trim() : '';
    if (!jn && pb) {
        jn = (pb.getAttribute('data-jobwork-no') || '').trim();
        sn = (pb.getAttribute('data-sale-order-no') || '').trim();
    }
    var ref = (jn || sn || '').trim();
    return ref || '—';
}

function jwqGetFirstProductHint() {
    var boot = document.getElementById('jwqDynamicBoot');
    var pb = document.getElementById('jwqPageBootstrapBtn');
    var fp = boot ? (boot.getAttribute('data-first-product') || '').trim() : '';
    if (!fp && pb) {
        fp = (pb.getAttribute('data-first-product') || '').trim();
    }
    return fp;
}

function jwqRefreshServerLoadedMaterialDiamonds() {
    var matBody = jwqGetJobworkMaterialBody();
    if (!matBody) {
        return;
    }
    matBody.querySelectorAll('.jwq-material-diamond-row[data-jwq-mat-server-loaded="1"]').forEach(function (tr) {
        tr.remove();
    });
    var jwoId = parseInt(String(window.__jwqCurrentJwoId != null ? window.__jwqCurrentJwoId : '0'), 10) || 0;
    if (jwoId < 1) {
        var hid = document.getElementById('jwqCurrentJwoId');
        jwoId = hid ? (parseInt(hid.value || '0', 10) || 0) : 0;
    }
    if (jwoId > 0 && typeof jwqLoadSavedDiamondRowsForModal === 'function') {
        jwqLoadSavedDiamondRowsForModal(jwoId);
    }
}

/**
 * Refresh diamond issue snapshot from DB for sync / save. Does not put issued stones on #jwqMaterialBody —
 * that list is only for diamonds the user adds via Existing / Add Diamond; full ledger stays on (i) Diamonds used.
 */
function jwqLoadSavedDiamondRowsForModal(jobworkOrderId) {
    var jwoId = parseInt(jobworkOrderId || '0', 10);
    if (jwoId < 1) return;
    var matBody = jwqGetJobworkMaterialBody();
    if (!matBody) return;
    fetch('ajax/mp-get-jobwork-queue-diamonds.php?jobwork_order_id=' + encodeURIComponent(String(jwoId)), { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            var rows = (d && d.ok && Array.isArray(d.items)) ? d.items : [];
            window.__jwqLastDiamondServerItems = rows.slice();
            matBody.querySelectorAll('.jwq-material-diamond-row[data-jwq-mat-server-loaded="1"]').forEach(function (tr) {
                tr.remove();
            });
            if (!matBody.querySelector('.jwq-material-diamond-row') && !matBody.querySelector('.jwq-material-metal-row')) {
                if (!matBody.querySelector('.jwq-mat-empty')) {
                    matBody.innerHTML = '<tr><td colspan="8" class="jwq-mat-empty">No Rows To Show</td></tr>';
                }
            } else {
                var emptyRow = matBody.querySelector('.jwq-mat-empty');
                if (emptyRow) {
                    emptyRow.remove();
                }
            }
            if (typeof window.jwqSyncOrderLineDiamondWtFromMaterialTable === 'function') {
                window.jwqSyncOrderLineDiamondWtFromMaterialTable();
            }
            if (typeof jwqRefreshMatDiamondTotalFooter === 'function') {
                jwqRefreshMatDiamondTotalFooter();
            }
        })
        .catch(function () {});
}

/** Load order lines into #jwqOrderLinesBody (same source as Job Work Order). */
function jwqLoadOrderLinesIntoTable(jwoId, orderRef, firstProduct) {
    var tbody = document.getElementById('jwqOrderLinesBody');
    var colCount = (window.JWQ_ORDER_LINE_COL_KEYS && window.JWQ_ORDER_LINE_COL_KEYS.length) ? window.JWQ_ORDER_LINE_COL_KEYS.length : 21;
    if (tbody) {
        tbody.innerHTML = '<tr><td colspan="' + colCount + '" style="text-align:center;color:#94a3b8;padding:16px;">Loading…</td></tr>';
    }
    fetch('ajax/mp-jobwork-order-items.php?id=' + encodeURIComponent(String(jwoId)), { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!tbody) return;
            tbody.innerHTML = '';
            var rows = (data && data.ok && data.items) ? data.items : [];
            if (rows.length) {
                rows.forEach(function (it) {
                    tbody.insertAdjacentHTML('beforeend', jwqBuildLineRowHtml(it, orderRef));
                });
            } else {
                var ph = {
                    design_no: '—',
                    barcode: '—',
                    product_name: firstProduct || '—',
                    carat: '—',
                    final_weight: 0,
                    net_weight: 0,
                    purity: 0,
                    quantity: 0,
                    rate: null,
                    less_weight: 0,
                    diamond_weight: 0,
                    gross_weight: 0
                };
                tbody.insertAdjacentHTML('beforeend', jwqBuildLineRowHtml(ph, orderRef));
            }
            jwqApplyStoredLineColumnVisibility();
            if (typeof jwqReconcileLineLossFromWeights === 'function') {
                jwqReconcileLineLossFromWeights();
            }
            if (typeof jwqRefreshAutoLossAllRows === 'function' && !(window.__jwqAddWeightExtraMetalUi || window.__jwqReduceWeightExtraMetalUi)) {
                jwqRefreshAutoLossAllRows();
            }
            if (typeof window.jwqSnapshotAddWeightOrigTotals === 'function') {
                window.jwqSnapshotAddWeightOrigTotals(true);
            } else if (typeof window.jwqInitAddWeightOrigTotals === 'function') {
                window.jwqInitAddWeightOrigTotals();
            }
            if (typeof window.jwqSnapshotReduceWeightOrigTotals === 'function') {
                window.jwqSnapshotReduceWeightOrigTotals(true);
            } else if (typeof window.jwqInitReduceWeightOrigTotals === 'function') {
                window.jwqInitReduceWeightOrigTotals();
            }
            if (typeof window.jwqInitAddWeightExtraMetalUi === 'function') {
                window.jwqInitAddWeightExtraMetalUi();
            }
            if (typeof window.jwqInitReduceWeightExtraMetalUi === 'function') {
                window.jwqInitReduceWeightExtraMetalUi();
            }
            if (typeof jwqSyncOrderLineDiamondWtFromMaterialTable === 'function') {
                jwqSyncOrderLineDiamondWtFromMaterialTable();
            }
            if (typeof window.jwqInitAddWeightOrigTotals === 'function') {
                window.jwqInitAddWeightOrigTotals();
            }
            if (typeof jwqLoadSavedDiamondRowsForModal === 'function') {
                jwqLoadSavedDiamondRowsForModal(jwoId);
            }
        })
        .catch(function () {
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="' + colCount + '" style="text-align:center;color:#dc2626;padding:12px;">Could not load lines</td></tr>';
            }
        });
}

/** After server creates a draft JWO for "Add row" on a blank queue, sync boot + header + search. */
function jwqApplyDraftJwoFromServer(data) {
    if (!data || !data.jobwork_order_id) return;
    var boot = document.getElementById('jwqDynamicBoot');
    if (boot) {
        boot.setAttribute('data-jwo-id', String(data.jobwork_order_id));
        boot.setAttribute('data-jobwork-no', String(data.jobwork_no || ''));
        boot.setAttribute('data-jobwork-queue-no', String(data.jobwork_queue_no || ''));
        boot.setAttribute('data-sale-order-no', '');
        boot.setAttribute('data-dept-id', '0');
        boot.setAttribute('data-dept-name', '');
        boot.setAttribute('data-user-id', '0');
        boot.setAttribute('data-worker-name', '');
        boot.setAttribute('data-customer', '');
        boot.setAttribute('data-first-product', '');
        boot.setAttribute('data-manufacturing-seconds', '0');
    }
    var hid = document.getElementById('jwqCurrentJwoId');
    if (hid) hid.value = String(data.jobwork_order_id);
    var qel = document.getElementById('jwqModalQueueNo');
    var qh = document.getElementById('jwqJobworkQueueNo');
    var qn = (data.jobwork_queue_no || '').trim();
    if (qel) qel.textContent = qn || '—';
    if (qh) qh.value = qn;
    var inp = document.getElementById('jwqSearchInput');
    if (inp) {
        inp.value = qn || (data.jobwork_no || '').trim() || ('#' + data.jobwork_order_id);
    }
}

function jwqApplyItemToBootButton(btn, it) {
    if (!btn || !it) return;
    var id = parseInt(it.id, 10) || 0;
    btn.setAttribute('data-jwo-id', String(id));
    btn.setAttribute('data-jobwork-queue-no', String(it.jobwork_queue_no || '').trim());
    btn.setAttribute('data-jobwork-no', String(it.jobwork_no || '').trim());
    btn.setAttribute('data-sale-order-no', String(it.sale_order_no || '').trim());
    btn.setAttribute('data-dept-id', String(it.department_id != null ? it.department_id : 0));
    btn.setAttribute('data-dept-name', String(it.dept_name || '').trim());
    btn.setAttribute('data-user-id', String(it.department_user_id != null ? it.department_user_id : 0));
    btn.setAttribute('data-worker-name', String(it.worker_name || '').trim());
    btn.setAttribute('data-customer', String(it.customer_name || '').trim());
    btn.setAttribute('data-first-product', String(it.first_product || '').trim());
    var mfg = parseInt(it.manufacturing_time_seconds, 10);
    if (isNaN(mfg) || mfg < 0) mfg = 0;
    btn.setAttribute('data-manufacturing-seconds', String(mfg));
}

function initJwqOrderSearch() {
    if (!window.JWQ_PAGE_MODE) return;
    var input = document.getElementById('jwqSearchInput');
    var box = document.getElementById('jwqSearchSuggestions');
    var boot = document.getElementById('jwqDynamicBoot');
    if (!input || !box || !boot) return;
    var tmr = null;
    function hideSuggestions() {
        box.classList.remove('show');
        box.innerHTML = '';
    }
    function render(items) {
        box.innerHTML = '';
        if (!items.length) {
            box.innerHTML = '<div class="jwq-suggestion-item" style="cursor:default;color:#94a3b8;">No job work orders found</div>';
            box.classList.add('show');
            return;
        }
        items.forEach(function (it) {
            var div = document.createElement('div');
            div.className = 'jwq-suggestion-item';
            div.setAttribute('role', 'option');
            var qn = (it.jobwork_queue_no || '').trim();
            var jn = (it.jobwork_no || '').trim();
            var cust = (it.customer_name || '').trim();
            var line1 = qn || jn || ('#' + it.id);
            var parts = [];
            if (jn && qn && jn !== qn) parts.push(jn);
            if (cust) parts.push(cust);
            var line2 = parts.join(' · ');
            div.innerHTML = '<div class="jwq-suggestion-primary">' + jwqEsc(line1) + '</div>' +
                (line2 ? '<div class="jwq-suggestion-meta">' + jwqEsc(line2) + '</div>' : '');
            div.addEventListener('mousedown', function (e) {
                e.preventDefault();
            });
            div.addEventListener('click', function () {
                jwqApplyItemToBootButton(boot, it);
                input.value = line1 + (cust ? (' · ' + cust) : '');
                hideSuggestions();
                jwqOpenModal(boot);
            });
            box.appendChild(div);
        });
        box.classList.add('show');
    }
    input.addEventListener('input', function () {
        clearTimeout(tmr);
        var q = (input.value || '').trim();
        if (q.length < 1) {
            hideSuggestions();
            return;
        }
        tmr = setTimeout(function () {
            fetch('ajax/mp-search-jobwork-orders.php?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || !data.ok) {
                        hideSuggestions();
                        return;
                    }
                    render(data.items || []);
                })
                .catch(function () {
                    hideSuggestions();
                });
        }, 280);
    });
    document.addEventListener('click', function (e) {
        if (!input.contains(e.target) && !box.contains(e.target)) {
            hideSuggestions();
        }
    });
}

function jwqSetNowDateTime() {
    var now = new Date();
    var dEl = document.getElementById('jwqDate');
    var tEl = document.getElementById('jwqTime');
    if (dEl) {
        var y = now.getFullYear();
        function pad2(n) { return (n < 10 ? '0' : '') + n; }
        dEl.value = y + '-' + pad2(now.getMonth() + 1) + '-' + pad2(now.getDate());
    }
    if (tEl) {
        var hh = now.getHours();
        var mm = now.getMinutes();
        var ss = now.getSeconds();
        function pad(n) { return (n < 10 ? '0' : '') + n; }
        tEl.value = pad(hh) + ':' + pad(mm) + ':' + pad(ss);
    }
}

function jwqOpenModal(btn, opts) {
    opts = opts || {};
    window.__jwqAddWeightExtraMetalUi = !!(opts.forWeight && opts.weightMode === 'add');
    window.__jwqReduceWeightExtraMetalUi = !!(opts.forWeight && opts.weightMode === 'reduce');
    window.__jwqPrefillLineWeights = !(window.__jwqAddWeightExtraMetalUi || window.__jwqReduceWeightExtraMetalUi);
    var overlay = document.getElementById('jwqModalOverlay');
    if (!overlay) return;
    var jwoId = parseInt(btn.getAttribute('data-jwo-id') || '0', 10);
    var queueFromSeries = (btn.getAttribute('data-jobwork-queue-no') || '').trim();
    var queueEl = document.getElementById('jwqModalQueueNo');
    var queueHid = document.getElementById('jwqJobworkQueueNo');
    if (queueEl) queueEl.textContent = queueFromSeries || '—';
    if (queueHid) queueHid.value = queueFromSeries;
    if (jwoId > 0 && !queueFromSeries) {
        jwqFetchQueueNoForDisplay(jwoId, queueEl, queueHid, btn);
    }
    var hid = document.getElementById('jwqCurrentJwoId');
    if (hid) hid.value = jwoId > 0 ? String(jwoId) : '';
    window.__jwqCurrentJwoId = jwoId > 0 ? jwoId : 0;
    var prevOpenJwoRm = parseInt(String(window.__jwqOpenModalLastJwoIdForRemoved || '0'), 10) || 0;
    jwqEnsureRemovedDiamondIssuesArray();
    if (jwoId > 0 && prevOpenJwoRm !== jwoId) {
        window.jwqRemovedDiamondIssues = [];
    }
    window.__jwqOpenModalLastJwoIdForRemoved = jwoId > 0 ? jwoId : prevOpenJwoRm;

    var currentDept = parseInt(btn.getAttribute('data-dept-id') || '0', 10);
    var currentUser = parseInt(btn.getAttribute('data-user-id') || '0', 10);

    var fromDept = document.getElementById('jwqFromDept');
    var fromUser = document.getElementById('jwqFromUser');
    var toDeptSel = document.getElementById('jwqToDept');
    var toUserSel = document.getElementById('jwqToUser');

    jwqFillDeptSelect(fromDept);
    jwqFillDeptSelect(toDeptSel);
    if (opts.forWeight && opts.weightMode === 'add') {
        /* Add Weight: weight comes INTO the current department → To Dept = existing dept, From Dept = user chooses source */
        if (fromDept) fromDept.value = '';
        jwqFillUserSelectForDept(fromUser, 0);
        if (fromUser) fromUser.value = '';
        if (toDeptSel) toDeptSel.value = currentDept > 0 ? String(currentDept) : '';
        jwqFillUserSelectForDept(toUserSel, currentDept);
        if (toUserSel) toUserSel.value = currentUser > 0 ? String(currentUser) : '';
    } else if (opts.forWeight && opts.weightMode === 'reduce') {
        if (fromDept) fromDept.value = currentDept > 0 ? String(currentDept) : '';
        jwqFillUserSelectForDept(fromUser, currentDept);
        if (fromUser) fromUser.value = currentUser > 0 ? String(currentUser) : '';
        if (toDeptSel) toDeptSel.value = '';
        jwqFillUserSelectForDept(toUserSel, 0);
        if (toUserSel) toUserSel.value = '';
    } else {
        if (fromDept) fromDept.value = currentDept > 0 ? String(currentDept) : '';
        jwqFillUserSelectForDept(fromUser, currentDept);
        if (fromUser) fromUser.value = currentUser > 0 ? String(currentUser) : '';

        if (toDeptSel) toDeptSel.value = '';
        jwqFillUserSelectForDept(toUserSel, 0);
        if (toUserSel) toUserSel.value = '';
    }

    jwqSetNowDateTime();
    var timerDisp = document.getElementById('jwqTotalTimeDisplay');
    if (timerDisp) {
        var tshow = '00:00:00';
        var secAttr = btn.getAttribute('data-manufacturing-seconds');
        if (secAttr !== null && secAttr !== '') {
            var ts = parseInt(secAttr, 10);
            if (!isNaN(ts) && ts >= 0) {
                var h = Math.floor(ts / 3600);
                var m = Math.floor((ts % 3600) / 60);
                var s = ts % 60;
                function z(n) { return (n < 10 ? '0' : '') + n; }
                tshow = z(h) + ':' + z(m) + ':' + z(s);
            }
        }
        timerDisp.textContent = tshow;
    }

    var jobNo = btn.getAttribute('data-jobwork-no') || '';
    var saleNo = btn.getAttribute('data-sale-order-no') || '';
    var orderRef = (jobNo || saleNo || '').trim() || '—';
    var firstProduct = btn.getAttribute('data-first-product') || '';

    jwqLoadOrderLinesIntoTable(jwoId, orderRef, firstProduct);

    var matBody = jwqGetJobworkMaterialBody();
    if (matBody) {
        matBody.innerHTML = '<tr><td colspan="8" class="jwq-mat-empty">No Rows To Show</td></tr>';
    }
    var matTot = document.getElementById('jwqMatTotalWt');
    if (matTot) matTot.textContent = '0.00';

    if (typeof window.jwqToggleWeightStrip === 'function') {
        if (opts.forWeight) {
            window.jwqToggleWeightStrip(true, opts.weightMode || 'reduce');
        } else {
            window.jwqToggleWeightStrip(false);
        }
    }

    if (!window.JWQ_PAGE_MODE) {
        overlay.classList.add('show');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }
}

function jwqResolveMatRowStockId(tr) {
    var attrs = ['data-stock-id', 'data-id', 'data-diamond-stock-id'];
    var i, n, v;
    for (i = 0; i < attrs.length; i++) {
        v = tr.getAttribute(attrs[i]);
        if (v) {
            n = parseInt(v, 10);
            if (n > 0) {
                return n;
            }
        }
    }
    var inps = tr.querySelectorAll('input[name="stock_id"],input[name*="stock_id"],input[name*="[stock_id]"],input[name="material_stock_id"]');
    for (i = 0; i < inps.length; i++) {
        n = parseInt(String(inps[i].value || '').trim(), 10);
        if (n > 0) {
            return n;
        }
    }
    var cbs = tr.querySelectorAll('input[type="checkbox"]');
    for (i = 0; i < cbs.length; i++) {
        v = String(cbs[i].value || '').trim();
        if (v) {
            n = parseInt(v, 10);
            if (n > 0) {
                return n;
            }
        }
    }
    return 0;
}

function jwqResolveMatRowBarcode(tr, tds) {
    var b = (tr.getAttribute('data-barcode') || '').trim();
    if (b) {
        return b;
    }
    var tdBc = tr.querySelector('td[data-col="barcode_no"],td[data-col="barcode"]');
    if (tdBc) {
        b = String(tdBc.textContent || '').trim();
        if (b) {
            return b;
        }
    }
    var inp = tr.querySelector('input[name*="barcode"],input[name*="Barcode"],input.jwq-mat-barcode');
    if (inp && inp.value) {
        return String(inp.value).trim();
    }
    if (tds && tds.length > 1) {
        var t1 = String(tds[1].textContent || '').trim();
        var sepIdx = t1.indexOf(' — ');
        if (sepIdx === -1) {
            sepIdx = t1.indexOf('\u2014');
        }
        if (sepIdx === -1) {
            sepIdx = t1.indexOf(' - ');
        }
        if (sepIdx > 0) {
            var firstPart = t1.slice(0, sepIdx).trim();
            if (firstPart && /^[A-Za-z0-9\-_.]{2,}$/.test(firstPart)) {
                return firstPart;
            }
        }
        if (/^[A-Za-z0-9\-_.]{3,}$/.test(t1) && t1.length < 80) {
            return t1;
        }
    }
    return '';
}

function jwqDiamondUsedRowIsSummary(r) {
    var p = String(r && r.product_name != null ? r.product_name : '').trim();
    return p.indexOf('Diamond total on line') >= 0 || p.indexOf('no per-piece') >= 0 || p.indexOf('no barcoded') >= 0 || p.indexOf('Diamond on line') >= 0;
}

/** Full issue ledger for (i) modal — includes barcodes also on the material grid so the list matches all diamonds issued on the job. Collapse duplicate barcode issue rows (keep latest id). */
function jwqFilterDiamondUsedModalRows(all) {
    var list = Array.isArray(all) ? all.slice() : [];
    var rest = [];
    var pick = [];
    list.forEach(function (r) {
        if (!r) {
            return;
        }
        if (String(r.row_source || '') === 'line_fallback' || jwqDiamondUsedRowIsSummary(r)) {
            rest.push(r);
            return;
        }
        var sid = parseInt(String(r.stock_id != null ? r.stock_id : '0'), 10) || 0;
        var bc = String(r.barcode || '').trim();
        if (sid > 0 && bc !== '' && bc !== '—') {
            pick.push(r);
        } else {
            rest.push(r);
        }
    });
    var byBc = {};
    pick.forEach(function (r) {
        var bc = String(r.barcode || '').trim().toUpperCase();
        var idn = parseInt(String(r.id != null ? r.id : '0'), 10) || 0;
        var prev = byBc[bc];
        var pid = prev ? (parseInt(String(prev.id != null ? prev.id : '0'), 10) || 0) : -1;
        if (!prev || idn >= pid) {
            byBc[bc] = r;
        }
    });
    var vals = Object.keys(byBc).map(function (k) {
        return byBc[k];
    });
    vals.sort(function (a, b) {
        return (parseInt(String(b.id != null ? b.id : '0'), 10) || 0) - (parseInt(String(a.id != null ? a.id : '0'), 10) || 0);
    });
    return rest.concat(vals);
}

function jwqEnsureRemovedDiamondIssuesArray() {
    if (!Array.isArray(window.jwqRemovedDiamondIssues)) {
        window.jwqRemovedDiamondIssues = [];
    }
    return window.jwqRemovedDiamondIssues;
}

function jwqIsServerDiamondRowRemovedFromUi(r) {
    if (!r) {
        return false;
    }
    var list = jwqEnsureRemovedDiamondIssuesArray();
    var rid = parseInt(String(r.id != null ? r.id : '0'), 10) || 0;
    var sid = parseInt(String(r.stock_id != null ? r.stock_id : '0'), 10) || 0;
    var bc = String(r.barcode || '').trim().toUpperCase();
    var i;
    for (i = 0; i < list.length; i++) {
        var x = list[i];
        if (!x || typeof x !== 'object') {
            continue;
        }
        var xid = parseInt(String(x.issue_id != null ? x.issue_id : '0'), 10) || 0;
        var xsid = parseInt(String(x.stock_id != null ? x.stock_id : '0'), 10) || 0;
        var xbc = String(x.barcode || '').trim().toUpperCase();
        if (xid > 0 && rid > 0 && xid === rid) {
            return true;
        }
        if (xsid > 0 && sid > 0 && xsid === sid && (xbc === '' || bc === '' || xbc === bc)) {
            return true;
        }
    }
    return false;
}

function jwqRefreshMatDiamondTotalFooter() {
    var matBody = jwqGetJobworkMaterialBody();
    if (!matBody) {
        return;
    }
    var wrap = matBody.closest('#jwqMatTableWrap') || matBody.closest('.jwq-mat-table-wrap') || matBody.closest('#jwqModalOverlay');
    var tot = wrap ? wrap.querySelector('#jwqMatTotalWt') : document.getElementById('jwqMatTotalWt');
    if (!tot) {
        return;
    }
    var sum = 0;
    matBody.querySelectorAll('tr.jwq-material-diamond-row').forEach(function (xtr) {
        var tdwt = xtr.querySelector('.jwq-mat-wt');
        sum += parseFloat(String(tdwt ? tdwt.textContent : '').replace(/,/g, '')) || 0;
    });
    tot.textContent = sum.toFixed(2);
}

function jwqRemoveUsedDiamondFromUi(btn) {
    if (!btn) {
        return;
    }
    var trModal = btn.closest('tr');
    var issueId = parseInt(btn.getAttribute('data-issue-id') || '0', 10) || 0;
    var stockId = parseInt(btn.getAttribute('data-stock-id') || '0', 10) || 0;
    var barcode = String(btn.getAttribute('data-barcode') || '').trim();
    var wRem = parseFloat(String(btn.getAttribute('data-weight') || '0').replace(/,/g, '')) || 0;
    var qRem = parseFloat(String(btn.getAttribute('data-qty') || '0').replace(/,/g, '')) || 0;
    if (wRem <= 0.0000001) {
        return;
    }
    var itemId = parseInt(btn.getAttribute('data-jobwork-order-item-id') || '0', 10) || 0;
    var list = jwqEnsureRemovedDiamondIssuesArray();
    var dup = false;
    var j;
    for (j = 0; j < list.length; j++) {
        var ex = list[j];
        if (!ex) {
            continue;
        }
        if (issueId > 0 && (parseInt(String(ex.issue_id || '0'), 10) || 0) === issueId) {
            dup = true;
            break;
        }
        if (stockId > 0 && (parseInt(String(ex.stock_id || '0'), 10) || 0) === stockId
            && String(ex.barcode || '').trim().toUpperCase() === barcode.toUpperCase()) {
            dup = true;
            break;
        }
    }
    if (!dup) {
        list.push({
            issue_id: issueId,
            stock_id: stockId,
            barcode: barcode,
            weight: wRem,
            qty: qRem > 0 ? qRem : 1
        });
    }
    if (trModal && trModal.parentNode) {
        trModal.parentNode.removeChild(trModal);
    }
    var matBody = jwqGetJobworkMaterialBody();
    if (matBody && (stockId > 0 || barcode)) {
        var bcu = barcode.toUpperCase();
        matBody.querySelectorAll('.jwq-material-diamond-row').forEach(function (mtr) {
            var msid = parseInt(mtr.getAttribute('data-stock-id') || mtr.dataset.stockId || '0', 10) || 0;
            var mbc = String(mtr.getAttribute('data-barcode') || '').trim().toUpperCase();
            if ((stockId > 0 && msid === stockId) || (bcu && mbc === bcu)) {
                mtr.remove();
            }
        });
        if (!matBody.querySelector('.jwq-material-diamond-row') && !matBody.querySelector('.jwq-material-metal-row')) {
            matBody.innerHTML = '<tr><td colspan="8" class="jwq-mat-empty">No Rows To Show</td></tr>';
        }
    }
    var tbody = document.getElementById('jwqOrderLinesBody');
    var trLine = null;
    if (itemId > 0 && tbody) {
        trLine = tbody.querySelector('tr[data-item-id="' + String(itemId) + '"]');
    }
    if (!trLine && tbody) {
        trLine = tbody.querySelector('tr[data-item-id]');
    }
    if (trLine) {
        var dInp = typeof jwqLineFieldEl === 'function' ? jwqLineFieldEl(trLine, 'diamond_wt') : null;
        var tInp = typeof jwqLineFieldEl === 'function' ? jwqLineFieldEl(trLine, 'total_wt') : null;
        var dw = dInp ? (parseFloat(String(dInp.value || '').replace(/,/g, '')) || 0) : 0;
        var tw = tInp ? (parseFloat(String(tInp.value || '').replace(/,/g, '')) || 0) : 0;
        dw = Math.max(0, dw - wRem);
        tw = Math.max(0, tw - wRem);
        if (dInp) {
            dInp.value = typeof jwqNum3 === 'function' ? jwqNum3(dw) : String(dw);
        }
        if (tInp) {
            tInp.value = typeof jwqNum3 === 'function' ? jwqNum3(tw) : String(tw);
        }
    }
    jwqRefreshMatDiamondTotalFooter();
    var srv = window.__jwqLastDiamondServerItems;
    if (Array.isArray(srv)) {
        window.__jwqLastDiamondServerItems = srv.filter(function (row) {
            return !jwqIsServerDiamondRowRemovedFromUi(row);
        });
    }
    if (typeof jwqSyncOrderLineDiamondWtFromMaterialTable === 'function') {
        jwqSyncOrderLineDiamondWtFromMaterialTable();
    }
}
window.jwqRemoveUsedDiamondFromUi = jwqRemoveUsedDiamondFromUi;

function jwqRenderDiamondUsedModalBody(rows) {
    var tb = document.getElementById('jwqDiamondUsedModalBody');
    if (!tb) return;
    var list = Array.isArray(rows) ? rows : [];
    if (!list.length) {
        tb.innerHTML = '<tr><td colspan="8" class="text-center text-muted p-3">No diamonds used yet.</td></tr>';
        return;
    }
    tb.innerHTML = list.map(function (r) {
        var pRaw = String(r && r.product_name != null ? r.product_name : '').trim();
        var sidNum = parseInt(String(r.stock_id != null ? r.stock_id : '0'), 10) || 0;
        var issueIdNum = parseInt(String(r && r.id != null ? r.id : '0'), 10) || 0;
        var itemIdNum = parseInt(String(r && r.jobwork_order_item_id != null ? r.jobwork_order_item_id : '0'), 10) || 0;
        var bcRaw = String(r && r.barcode != null ? r.barcode : '').trim();
        var isLineFb = String(r.row_source || '') === 'line_fallback';
        var isSummary = pRaw.indexOf('Diamond total on line') >= 0 || pRaw.indexOf('no per-piece') >= 0 || pRaw.indexOf('no barcoded') >= 0;
        var wRaw = r && r.weight_out != null ? r.weight_out : (r && r.weight != null ? r.weight : '');
        var wNum = parseFloat(String(wRaw).replace(/,/g, ''));
        var pickable = !isLineFb && !isSummary && sidNum > 0 && bcRaw !== '' && bcRaw !== '—' && isFinite(wNum) && wNum > 0.0000001;
        var barcode = jwqEsc(r && r.barcode != null ? r.barcode : '');
        var product = jwqEsc(r && r.product_name != null ? r.product_name : '');
        var weight = (typeof jwqNum3 === 'function' && isFinite(wNum)) ? jwqEsc(jwqNum3(wNum)) : jwqEsc(wRaw);
        var qRaw = r && r.qty_out != null ? r.qty_out : (r && r.qty != null ? r.qty : '');
        var qNum = parseFloat(String(qRaw).replace(/,/g, ''));
        var qty = (typeof jwqNum3 === 'function' && isFinite(qNum)) ? jwqEsc(jwqNum3(qNum)) : jwqEsc(qRaw);
        var addDeptNm = String(r && r.added_by_dept_name != null ? r.added_by_dept_name : '').trim();
        var addUserNm = String(r && r.added_by_user_name != null ? r.added_by_user_name : '').trim();
        var addDeptCell = addDeptNm ? jwqEsc(addDeptNm) : (function () {
            var id = parseInt(String(r && r.added_by_dept_id != null ? r.added_by_dept_id : '0'), 10) || 0;
            return id > 0 ? jwqEsc('Dept #' + id) : '—';
        }());
        var addUserCell = addUserNm ? jwqEsc(addUserNm) : (function () {
            var id = parseInt(String(r && r.added_by_user_id != null ? r.added_by_user_id : '0'), 10) || 0;
            return id > 0 ? jwqEsc('User #' + id) : '—';
        }());
        var issued = jwqEsc(r && r.created_at != null ? r.created_at : '(pending save)');
        var qStore = isFinite(qNum) && qNum > 0 ? qNum : 1;
        var wStore = isFinite(wNum) && wNum > 0 ? wNum : 0;
        var trCls = pickable ? ' jwq-diamond-used-pickable' : '';
        var trStyle = pickable ? 'cursor:pointer;' : '';
        var titlePick = pickable ? ' title="Click row to add to diamond material list (reduces line Diamond Wt; save to update stock)"' : '';
        var removeBtn = '';
        if (pickable && (issueIdNum > 0 || sidNum > 0)) {
            removeBtn = '<td class="text-center jwq-remove-used-diamond-wrap" style="width:44px;">'
                + '<button type="button" class="btn btn-link btn-sm text-danger p-0 jwq-remove-used-diamond"'
                + ' data-issue-id="' + String(issueIdNum > 0 ? issueIdNum : '') + '"'
                + ' data-stock-id="' + String(sidNum) + '"'
                + ' data-barcode="' + jwqAttrEsc(bcRaw) + '"'
                + ' data-weight="' + String(wStore) + '"'
                + ' data-qty="' + String(qStore) + '"'
                + ' data-jobwork-order-item-id="' + String(itemIdNum > 0 ? itemIdNum : '') + '"'
                + ' title="Remove from job and return stock (save to persist)" aria-label="Remove used diamond">'
                + '<i class="feather icon-trash-2"></i></button></td>';
        } else {
            removeBtn = '<td class="text-muted text-center">—</td>';
        }
        return '<tr class="' + trCls.trim() + '" style="' + trStyle + '"' + titlePick + ' data-jwq-used-stock-id="' + (pickable ? String(sidNum) : '') + '" data-jwq-used-barcode="' + jwqAttrEsc(bcRaw) + '" data-jwq-used-weight="' + String(wStore) + '" data-jwq-used-qty="' + String(qStore) + '" data-jwq-used-product="' + jwqAttrEsc(pRaw) + '">'
            + '<td>' + (barcode || '—') + '</td>'
            + '<td>' + (product || '—') + '</td>'
            + '<td class="text-right">' + (weight || '0') + '</td>'
            + '<td class="text-right">' + (qty || '0') + '</td>'
            + '<td>' + addDeptCell + '</td>'
            + '<td>' + addUserCell + '</td>'
            + '<td>' + (issued || '—') + '</td>'
            + removeBtn
            + '</tr>';
    }).join('');
}

function jwqOpenDiamondUsedModal() {
    var jwoId = parseInt(window.__jwqCurrentJwoId || '0', 10) || 0;
    if (jwoId < 1) {
        var hidJwo = document.getElementById('jwqCurrentJwoId');
        jwoId = hidJwo ? (parseInt(hidJwo.value || '0', 10) || 0) : 0;
    }
    var tb = document.getElementById('jwqDiamondUsedModalBody');
    if (tb) {
        tb.innerHTML = '<tr><td colspan="8" class="text-center text-muted p-3">Loading…</td></tr>';
    }
    if (typeof window.jQuery !== 'undefined' && window.jQuery.fn.modal) {
        window.jQuery('#jwqDiamondUsedModal').modal('show');
    }

    function mergeAndRender(serverRows) {
        var srvRaw = Array.isArray(serverRows) ? serverRows : [];
        var srv = srvRaw.filter(function (row) {
            return !jwqIsServerDiamondRowRemovedFromUi(row);
        });
        window.__jwqLastDiamondServerItems = srv.slice();
        var serverHasIssueRows = srv.some(function (r) {
            if (!r || String(r.row_source || '') === 'line_fallback') {
                return false;
            }
            var sid = parseInt(String(r.stock_id != null ? r.stock_id : '0'), 10) || 0;
            var bc = String(r.barcode || '').trim();
            return sid > 0 && bc !== '';
        });
        var seen = {};
        var all = [];

        function srvDedupeKey(r) {
            var issueId = (r && r.id != null) ? parseInt(r.id, 10) : 0;
            if (issueId > 0) {
                return 'issue:' + issueId;
            }
            var sid = String(r.stock_id != null ? r.stock_id : '0');
            var bc = String(r.barcode || '').trim();
            var w = String(r.weight_out != null ? r.weight_out : (r.weight != null ? r.weight : ''));
            var iid = String((r && r.jobwork_order_item_id != null) ? r.jobwork_order_item_id : '');
            return 'row:' + sid + ':' + bc + ':' + w + ':item:' + iid;
        }

        srv.forEach(function (r) {
            var k = srvDedupeKey(r);
            if (seen[k]) {
                return;
            }
            seen[k] = true;
            all.push(r);
        });

        function serverCoversMatRow(sidNum, barcode, wn) {
            if (sidNum < 1) {
                return false;
            }
            var bb = String(barcode || '').trim();
            var i;
            for (i = 0; i < srv.length; i++) {
                var row = srv[i];
                var rsid = parseInt(String(row && row.stock_id != null ? row.stock_id : '0'), 10) || 0;
                if (rsid !== sidNum) {
                    continue;
                }
                var rb = String(row.barcode || '').trim();
                if (rb && bb && rb !== bb) {
                    continue;
                }
                var rw = parseFloat(row.weight_out != null ? row.weight_out : (row.weight != null ? row.weight : '')) || 0;
                if (Math.abs(rw - wn) < 0.0005) {
                    return true;
                }
            }
            return false;
        }

        var matGridRef = jwqGetJobworkMaterialBody();
        if (matGridRef) {
            matGridRef.querySelectorAll('.jwq-material-diamond-row').forEach(function (tr) {
            var tds = tr.querySelectorAll('td');
            if (tds.length < 2) {
                return;
            }
            var sidNum = parseInt(tr.getAttribute('data-stock-id') || tr.dataset.stockId || '0', 10) || 0;
            var wn = jwqMaterialDiamondRowEffectiveWeight(tr);
            if (wn <= 0) {
                return;
            }
            var barcode = String(tr.getAttribute('data-barcode') || tr.dataset.barcode || '').trim();
            if (serverCoversMatRow(sidNum, barcode, wn)) {
                return;
            }
            var woutStr = typeof jwqNum3 === 'function' ? jwqNum3(wn) : String(wn);
            var sid = sidNum > 0 ? String(sidNum) : '';
            var dedupe = sid !== '' ? ('mat:sid:' + sid + ':' + barcode + ':' + woutStr) : ('mat:pending:' + barcode + ':' + woutStr);
            if (seen[dedupe]) {
                return;
            }
            seen[dedupe] = true;
            all.push({
                stock_id: sid || '0',
                barcode: barcode,
                product_name: String(tr.dataset.productName || '').trim() || (tds[1] ? String(tds[1].textContent || '').trim() : ''),
                weight_out: woutStr,
                qty_out: tds[4] ? String(tds[4].textContent || '').trim() : '',
                added_by_dept_id: parseInt(String(tr.dataset.addedByDeptId || tr.getAttribute('data-added-by-dept-id') || '0'), 10) || 0,
                added_by_user_id: parseInt(String(tr.dataset.addedByUserId || tr.getAttribute('data-added-by-user-id') || '0'), 10) || 0,
                added_by_dept_name: String(tr.dataset.addedByDeptName || '').trim(),
                added_by_user_name: String(tr.dataset.addedByUserName || '').trim(),
                created_at: '(pending — save transfer to record in stock)'
            });
        });
        }

        if (all.length < 1 && !serverHasIssueRows) {
            document.querySelectorAll('#jwqOrderLinesBody tr[data-item-id]').forEach(function (tr) {
                var dInp = typeof jwqLineFieldEl === 'function' ? jwqLineFieldEl(tr, 'diamond_wt') : tr.querySelector('[data-field="diamond_wt"],[data-col-input="diamond_wt"]');
                if (!dInp) {
                    return;
                }
                var dw = parseFloat(String(dInp.value || '').replace(/,/g, '')) || 0;
                if (dw <= 0.0000001) {
                    return;
                }
                var tagInp = typeof jwqLineFieldEl === 'function' ? jwqLineFieldEl(tr, 'tag_no') : tr.querySelector('[data-field="tag_no"],[data-col-input="tag_no"]');
                var tagNo = tagInp ? String(tagInp.value || '').trim() : '';
                var descInp = typeof jwqLineFieldEl === 'function' ? jwqLineFieldEl(tr, 'description') : tr.querySelector('[data-field="description"],[data-col-input="description"]');
                var desc = descInp ? String(descInp.value || '').trim() : '';
                var lbl = (tagNo || desc || ('Item #' + (tr.getAttribute('data-item-id') || '?'))).trim();
                all.push({
                    stock_id: 0,
                    barcode: '—',
                    product_name: 'Diamond total on line (no per-piece rows yet): ' + lbl,
                    weight_out: jwqNum3(dw),
                    qty_out: '—',
                    added_by_dept_id: 0,
                    added_by_user_id: 0,
                    added_by_dept_name: '',
                    added_by_user_name: '',
                    created_at: 'Add diamonds in the material grid and save to log each barcode in stock.'
                });
            });
        }

        all = jwqFilterDiamondUsedModalRows(all);

        jwqRenderDiamondUsedModalBody(all);
        /* Do not sync line Diamond Wt / Total here: opening "(i)" is read-only; sync was feeding
         * total_wt = metal + diamond and re-inferring pools so each click could inflate weights. */
    }

    if (jwoId < 1) {
        mergeAndRender([]);
        return;
    }
    var url = 'ajax/mp-get-jobwork-queue-diamonds.php?jobwork_order_id=' + encodeURIComponent(String(jwoId));
    fetch(url, { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            mergeAndRender((d && d.ok && Array.isArray(d.items)) ? d.items : []);
        })
        .catch(function () {
            mergeAndRender([]);
        });
}

function jwqMaterialTableLooksLikeItHasDiamondRows() {
    var body = jwqGetJobworkMaterialBody();
    if (!body) {
        return false;
    }
    if (body.querySelector('.jwq-material-diamond-row')) {
        return true;
    }
    var trs = body.querySelectorAll('tr');
    var k;
    for (k = 0; k < trs.length; k++) {
        var tr = trs[k];
        if (tr.classList.contains('jwq-mat-empty')) {
            continue;
        }
        if (tr.querySelector('td[colspan]')) {
            continue;
        }
        var tds = tr.querySelectorAll('td');
        if (tds.length < 2) {
            continue;
        }
        if (tr.getAttribute('data-jwq-mat-from-diamond') === '1') {
            return true;
        }
        if (jwqResolveMatRowStockId(tr) > 0) {
            return true;
        }
        var cat = String(tds[0].textContent || '').trim().toLowerCase();
        if (cat.indexOf('diamond') !== -1) {
            return true;
        }
        var wtEl = tr.querySelector('.jwq-mat-wt');
        var w = wtEl ? (parseFloat(String(wtEl.textContent || '').replace(/,/g, '')) || 0) : 0;
        if (w > 0.0000001) {
            return true;
        }
    }
    return false;
}

function jwqMaterialBodyHasVisibleDataRows() {
    var body = jwqGetJobworkMaterialBody();
    if (!body) {
        return false;
    }
    var trs = body.querySelectorAll('tr');
    var k;
    for (k = 0; k < trs.length; k++) {
        var tr = trs[k];
        if (tr.classList.contains('jwq-mat-empty')) {
            continue;
        }
        if (tr.querySelector('td[colspan]')) {
            continue;
        }
        if (tr.querySelectorAll('td').length >= 2) {
            return true;
        }
    }
    return false;
}

function jwqCollectMaterialDiamondStockForSave() {
    var out = [];
    var fallbackItemId = 0;
    var firstLineTr = document.querySelector('#jwqOrderLinesBody tr[data-item-id]');
    if (firstLineTr) {
        fallbackItemId = parseInt(firstLineTr.getAttribute('data-item-id') || '0', 10) || 0;
    }
    if (fallbackItemId < 1) {
        var qLines = typeof jwqCollectQueueLinePayload === 'function' ? jwqCollectQueueLinePayload() : [];
        if (Array.isArray(qLines) && qLines.length > 0) {
            fallbackItemId = parseInt(qLines[0].item_id || '0', 10) || 0;
        }
    }
    var body = jwqGetJobworkMaterialBody();
    if (!body) {
        return out;
    }
    var seen = new Set();
    body.querySelectorAll('.jwq-material-diamond-row').forEach(function (row) {
        var stockId = parseInt(row.dataset.stockId || '0', 10) || 0;
        var barcode = String(row.dataset.barcode || '').trim();
        if (!stockId || !barcode) {
            return;
        }
        if (seen.has(stockId)) {
            return;
        }
        seen.add(stockId);
        var weight = jwqMaterialDiamondRowEffectiveWeight(row);
        var qty = parseFloat(row.dataset.qty || '0') || 0;
        if (qty <= 0) {
            var tds = row.querySelectorAll('td');
            qty = parseFloat(String(tds[4] ? tds[4].textContent : '').replace(/,/g, '')) || 0;
        }
        if (weight <= 0) {
            return;
        }
        var itemId = parseInt(row.getAttribute('data-jobwork-item-id') || '0', 10) || 0;
        if (itemId < 1) {
            itemId = fallbackItemId;
        }
        var addedByDept = parseInt(row.getAttribute('data-added-by-dept-id') || '0', 10) || 0;
        var addedByUser = parseInt(row.getAttribute('data-added-by-user-id') || '0', 10) || 0;
        if (typeof window.jwqIsAddWeightMode === 'function' && window.jwqIsAddWeightMode()) {
            addedByDept = 0;
            addedByUser = 0;
        }
        var payload = {
            stock_id: stockId,
            jobwork_order_item_id: itemId > 0 ? itemId : 0,
            barcode: barcode,
            product_name: String(row.dataset.productName || '').trim(),
            weight: weight,
            qty: qty > 0 ? qty : 0,
            added_by_dept_id: addedByDept,
            added_by_user_id: addedByUser,
            diamond_category: String(row.getAttribute('data-diamond-category') || 'Diamond').trim() || 'Diamond'
        };
        if (row.getAttribute('data-jwq-from-used-modal') === '1') {
            payload.from_used_diamond_modal = true;
        }
        if (window.console && typeof console.log === 'function') {
            console.log('DIAMOND SAVE ROW', payload);
        }
        out.push(payload);
    });
    if (out.length < 1 && jwqMaterialTableLooksLikeItHasDiamondRows()) {
        if (window.console && typeof console.error === 'function') {
            console.error('JWQ_COLLECT_DIAMOND: add diamonds via Existing stock so rows get class jwq-material-diamond-row and data-stock-id / data-barcode.');
        }
    }
    return out;
}

function jwqCloseModal() {
    if (window.JWQ_PAGE_MODE) {
        if (typeof window.jwqToggleWeightStrip === 'function') {
            window.jwqToggleWeightStrip(false);
        }
        return;
    }
    var overlay = document.getElementById('jwqModalOverlay');
    if (!overlay) return;
    if (typeof window.jwqToggleWeightStrip === 'function') {
        window.jwqToggleWeightStrip(false);
    }
    var jwqPanel = document.getElementById('jwqColumnsPanel');
    if (jwqPanel) jwqPanel.classList.remove('show');
    overlay.classList.remove('show');
    overlay.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

/** After Jobwork Queue Save: optional print — jobwork slip only */
function mpJwqPromptPrintAfterSave(jwoId) {
    var idStr = String(jwoId || '');
    var slipUrl = 'manufacturing-jobwork-slip-print.php?id=' + encodeURIComponent(idStr) + '&autoprint=1';

    function openPrints() {
        window.open(slipUrl, '_blank', 'noopener,noreferrer');
    }

    function finish() {
        jwqCloseModal();
    }

    if (typeof swal === 'function') {
        swal({
            title: 'Print bill',
            text: 'Do you want to print invoice?',
            type: 'info',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            cancelButtonText: 'No',
            confirmButtonClass: 'confirm',
            cancelButtonClass: 'cancel',
            customClass: 'mp-print-bill-swal'
        }, function (isConfirm) {
            if (isConfirm) {
                openPrints();
            }
            finish();
        });
    } else {
        if (confirm('Do you want to print invoice?')) {
            openPrints();
        }
        finish();
    }
}

function jwqInwardModalDateTimeStr() {
    var now = new Date();
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    return pad(now.getDate()) + '-' + pad(now.getMonth() + 1) + '-' + now.getFullYear() + ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
}

function jwqInwardColStorageKey() {
    return window.JWQ_INWARD_COL_STORAGE || 'jobwork_queue_page_jwq_inward_modal_hidden_columns';
}

function jwqApplyStoredInwardModalColumnVisibility() {
    try {
        var raw = localStorage.getItem(jwqInwardColStorageKey());
        var hidden = raw ? JSON.parse(raw) : [];
        var table = document.getElementById('jwqInwardStockTable');
        if (!table) return;
        table.querySelectorAll('th[data-col], td[data-col]').forEach(function (el) {
            var col = el.getAttribute('data-col');
            if (hidden.indexOf(col) >= 0) el.classList.add('col-hidden');
            else el.classList.remove('col-hidden');
        });
    } catch (e) {}
}

function jwqInwardStockGetVisibleColumnThs() {
    var table = document.getElementById('jwqInwardStockTable');
    if (!table) return [];
    var tr = table.querySelector('thead tr');
    if (!tr) return [];
    return Array.prototype.slice.call(tr.querySelectorAll('th[data-col]')).filter(function (th) {
        return !th.classList.contains('col-hidden');
    });
}

function jwqInwardStockExportExcel() {
    var ths = jwqInwardStockGetVisibleColumnThs();
    if (!ths.length) return;
    var lines = [];
    lines.push(ths.map(function (th) {
        return '"' + String(th.textContent || '').replace(/"/g, '""') + '"';
    }).join(','));
    var table = document.getElementById('jwqInwardStockTable');
    if (!table) return;
    table.querySelectorAll('tbody tr').forEach(function (tr) {
        var row = ths.map(function (th) {
            var col = th.getAttribute('data-col');
            var td = tr.querySelector('td[data-col="' + col + '"]');
            var txt = td ? String(td.textContent || '') : '';
            return '"' + txt.replace(/"/g, '""') + '"';
        });
        lines.push(row.join(','));
    });
    var blob = new Blob(['\ufeff' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'inward-stock.csv';
    a.click();
    URL.revokeObjectURL(a.href);
}

function jwqInwardStockExportPdf() {
    var ths = jwqInwardStockGetVisibleColumnThs();
    if (!ths.length) return;
    var table = document.getElementById('jwqInwardStockTable');
    if (!table) return;
    var theadHtml = '<tr>' + ths.map(function (th) {
        return '<th>' + jwqEsc(th.textContent || '') + '</th>';
    }).join('') + '</tr>';
    var bodyHtml = '';
    table.querySelectorAll('tbody tr').forEach(function (tr) {
        bodyHtml += '<tr>';
        ths.forEach(function (th) {
            var col = th.getAttribute('data-col');
            var td = tr.querySelector('td[data-col="' + col + '"]');
            bodyHtml += '<td>' + (td ? jwqEsc(td.textContent || '') : '') + '</td>';
        });
        bodyHtml += '</tr>';
    });
    var title = (document.getElementById('jwqInwardStockModalTitle') || {}).textContent || 'Inward Stock';
    var w = window.open('', '_blank');
    if (!w) return;
    w.document.write('<!DOCTYPE html><html><head><meta charset="utf-8"><title>' + jwqEsc(title) + '</title>');
    w.document.write('<style>body{font-family:Segoe UI,Arial,sans-serif;padding:16px;} table{border-collapse:collapse;width:100%;font-size:12px;} th,td{border:1px solid #ccc;padding:6px;} th{background:#eef2f8;}</style>');
    w.document.write('</head><body><h2 style="font-size:16px;margin:0 0 12px;">' + jwqEsc(title) + '</h2>');
    w.document.write('<table><thead>' + theadHtml + '</thead><tbody>' + bodyHtml + '</tbody></table></body></html>');
    w.document.close();
    w.focus();
    w.print();
    w.close();
}

function jwqFillInwardStockDetailsModal(which) {
    which = which || 'from';
    var keys = window.JWQ_INWARD_STOCK_MODAL_KEYS || [];
    var ctx = document.getElementById('jwqInwardStockModalContext');
    if (ctx) ctx.textContent = which === 'to' ? '(To)' : '(From)';

    var jwoId = (document.getElementById('jwqCurrentJwoId') || {}).value || '';
    var qHidden = document.getElementById('jwqJobworkQueueNo');
    var queueNo = (qHidden && qHidden.value) ? String(qHidden.value).trim() : '';
    if (!queueNo && jwoId) {
        queueNo = 'JWQ-' + jwoId;
    }
    if (!queueNo) {
        queueNo = '—';
    }

    var jwqMap = {};
    var tr = null;
    document.querySelectorAll('#jwqOrderLinesBody tr').forEach(function (r) {
        if (!tr && r.querySelector('td[data-col]')) tr = r;
    });
    if (tr) {
        tr.querySelectorAll('td[data-col]').forEach(function (td) {
            var k = td.getAttribute('data-col');
            if (!k) return;
            var inp = td.querySelector('[data-field="' + k + '"], [data-col-input="' + k + '"]');
            if (inp && inp.value != null) {
                jwqMap[k] = String(inp.value).trim();
            } else {
                jwqMap[k] = (td.textContent || '').trim();
            }
        });
    }

    var descCombined = ((jwqMap.description || '') + ' ' + (jwqMap.product || '')).trim();
    var metal = '—';
    if (/gold/i.test(descCombined)) metal = 'Gold';
    else if (/silver/i.test(descCombined)) metal = 'Silver';
    else if (/platinum/i.test(descCombined)) metal = 'Platinum';

    var cellByKey = {};
    keys.forEach(function (k) { cellByKey[k] = '—'; });

    cellByKey.queue_no = queueNo;
    cellByKey.comment = '';
    cellByKey.product_name = jwqMap.product || jwqMap.description || '—';
    cellByKey.active = '';
    cellByKey.image_urls = '';
    cellByKey.against_queue = '';
    cellByKey.against_invoice = jwqMap.order_no || '—';
    cellByKey.metal = metal;
    cellByKey.description = jwqMap.description || '—';
    cellByKey.dust_wastage_wt = jwqMap.dust_wastage_wt && jwqMap.dust_wastage_wt !== '—' ? jwqMap.dust_wastage_wt : '0';
    cellByKey.loss_wt = jwqMap.loss && jwqMap.loss !== '—' ? jwqMap.loss : '0';
    cellByKey.total_wt = jwqMap.total_wt || '—';
    cellByKey.metal_wt = jwqMap.metal_wt || '—';
    cellByKey.diamond_wt = jwqMap.diamond_wt || '—';
    cellByKey.purity_wt = jwqMap.total_purity || '—';
    cellByKey.carat_name = jwqMap.karat || '—';
    cellByKey.profit_wt = jwqMap.profit && jwqMap.profit !== '—' ? jwqMap.profit : '0';
    cellByKey.tag_no = jwqMap.tag_no || '—';
    cellByKey.total_quantity = jwqMap.total_qty || '—';
    cellByKey.date_time = jwqInwardModalDateTimeStr();

    var html = '<tr>';
    keys.forEach(function (k) {
        var v = cellByKey[k] != null ? String(cellByKey[k]) : '—';
        html += '<td data-col="' + k + '">' + jwqEsc(v) + '</td>';
    });
    html += '</tr>';

    var tbody = document.getElementById('jwqInwardStockBody');
    if (tbody) tbody.innerHTML = html;

    jwqApplyStoredInwardModalColumnVisibility();

    if (typeof window.jQuery !== 'undefined' && window.jQuery.fn.modal) {
        window.jQuery('#jwqInwardStockModal').modal('show');
    }
}

function jwqHandleOrderLineWeightInput(e) {
    if (window.__jwqIsRecalculatingWeight) {
        return;
    }
    var tbody = e.currentTarget;
    if (!tbody) {
        return;
    }
    var inp = e.target.closest(
        '.jwq-cell-input[data-field="metal_wt"], .jwq-cell-input[data-field="loss"], .jwq-cell-input[data-field="expected_wt"], .jwq-cell-input[data-col-input="metal_wt"], .jwq-cell-input[data-col-input="loss"], .jwq-cell-input[data-col-input="expected_wt"]'
    );
    if (inp && tbody.contains(inp)) {
        var tr = inp.closest('tr');
        if (tr) {
            tr.removeAttribute('data-jwq-freeze-weights');
        }
        var f = inp.getAttribute('data-field') || inp.getAttribute('data-col-input');
        if (tr && f === 'expected_wt') {
            tr.removeAttribute('data-jwq-saved-loss-grams');
            if (typeof jwqMaybeApplyAutoLoss === 'function') {
                jwqMaybeApplyAutoLoss(tr, 'expected_wt');
            }
            return;
        }
        if (tr && f === 'metal_wt') {
            tr.removeAttribute('data-jwq-saved-loss-grams');
        }
        if (tr && f === 'metal_wt') {
            var mv = parseFloat(String(inp.value || '').replace(/,/g, ''));
            if (isFinite(mv) && mv >= 0) {
                tr.setAttribute('data-base-metal-wt', String(mv));
            }
        }
        if (tr && typeof jwqLineLiveRecalcWeights === 'function' && f) {
            jwqLineLiveRecalcWeights(tr, f);
        }
        if (tr && typeof jwqMaybeApplyAutoLoss === 'function') {
            jwqMaybeApplyAutoLoss(tr, f);
        }
        return;
    }
    var inD = e.target.closest('.jwq-cell-input[data-field="diamond_wt"], .jwq-cell-input[data-col-input="diamond_wt"]');
    if (inD && tbody.contains(inD)) {
        var trd = inD.closest('tr');
        if (trd) {
            trd.removeAttribute('data-jwq-freeze-weights');
            /* User typed the diamond weight — later syncs must not resurrect it from the BOM pool gap. */
            trd.setAttribute('data-jwq-diamond-manual', '1');
        }
        if (trd && typeof jwqRefreshLineDiamondBaseFromUi === 'function') {
            jwqRefreshLineDiamondBaseFromUi(trd);
        }
        if (trd && typeof jwqLineLiveRecalcWeights === 'function') {
            jwqLineLiveRecalcWeights(trd, 'diamond_wt');
        }
        if (trd && typeof jwqMaybeApplyAutoLoss === 'function') {
            jwqMaybeApplyAutoLoss(trd);
        }
    }
}
window.jwqHandleOrderLineWeightInput = jwqHandleOrderLineWeightInput;

function initJobworkQueueModal() {
    var grid = document.getElementById('mpJobCardsGrid');
    var overlay = document.getElementById('jwqModalOverlay');
    if (!overlay || overlay._jwqBound) return;
    overlay._jwqBound = true;
    initJwqDecimalInputGuards();

    document.addEventListener('click', function (e) {
        var infoBtn = e.target && e.target.closest ? e.target.closest('.jwq-diamond-used-info-btn') : null;
        if (!infoBtn || !overlay.contains(infoBtn)) return;
        e.preventDefault();
        jwqOpenDiamondUsedModal();
    });

    overlay.addEventListener('click', function (e) {
        var folderBtn = e.target.closest('.jwq-inward-folder-btn');
        if (folderBtn && overlay.contains(folderBtn)) {
            e.preventDefault();
            jwqFillInwardStockDetailsModal(folderBtn.getAttribute('data-which') || 'from');
        }
    });

    if (grid) {
        grid.addEventListener('click', function (e) {
            var btn = e.target.closest('.mp-jwq-open-btn');
            if (!btn || !grid.contains(btn)) return;
            e.preventDefault();
            jwqOpenModal(btn);
        });
    }

    var closeBtn = document.getElementById('jwqModalClose');
    if (closeBtn) closeBtn.addEventListener('click', jwqCloseModal);
    if (!window.JWQ_PAGE_MODE) {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) jwqCloseModal();
        });
    }

    if (typeof window.jQuery !== 'undefined' && window.jQuery.fn.modal) {
        window.jQuery('#jwqDiamondUsedModal').off('hidden.bs.modal.jwqDiamondSync').on('hidden.bs.modal.jwqDiamondSync', function () {
            if (typeof jwqFetchDiamondServerItemsThen === 'function') {
                jwqFetchDiamondServerItemsThen(function () {
                    if (typeof jwqSyncOrderLineDiamondWtFromMaterialTable === 'function') {
                        jwqSyncOrderLineDiamondWtFromMaterialTable();
                    }
                });
            }
        });
    }

    var jwqLinesTbodyInit = document.getElementById('jwqOrderLinesBody');
    if (jwqLinesTbodyInit && !jwqLinesTbodyInit._jwqAutoLossBound) {
        jwqLinesTbodyInit._jwqAutoLossBound = true;
        jwqLinesTbodyInit.addEventListener('input', jwqHandleOrderLineWeightInput);
        jwqLinesTbodyInit.addEventListener('change', jwqHandleOrderLineWeightInput);
    }
    if (jwqLinesTbodyInit && !jwqLinesTbodyInit._jwqDiamondMaterialLineBound) {
        jwqLinesTbodyInit._jwqDiamondMaterialLineBound = true;
        jwqLinesTbodyInit.addEventListener('focusin', function (e) {
            var tr = e.target && e.target.closest ? e.target.closest('tr[data-item-id]') : null;
            if (!tr || !jwqLinesTbodyInit.contains(tr)) {
                return;
            }
            var iid = parseInt(tr.getAttribute('data-item-id') || '0', 10) || 0;
            if (iid > 0) {
                window.__jwqDiamondMaterialItemId = iid;
            }
        });
    }

    var fromDept = document.getElementById('jwqFromDept');
    var fromUser = document.getElementById('jwqFromUser');
    var toDept = document.getElementById('jwqToDept');
    var toUser = document.getElementById('jwqToUser');
    if (fromDept && fromUser) {
        fromDept.addEventListener('change', function () {
            var id = parseInt(fromDept.value || '0', 10);
            jwqFillUserSelectForDept(fromUser, id);
            if (typeof window.jwqIsAddWeightMode === 'function' && window.jwqIsAddWeightMode()) {
                if (typeof window.jwqRefreshAddWeightLinePreviews === 'function') {
                    window.jwqRefreshAddWeightLinePreviews();
                }
            } else if (typeof jwqRefreshAutoLossAllRows === 'function') {
                jwqRefreshAutoLossAllRows();
            }
            if (typeof jwqRefreshServerLoadedMaterialDiamonds === 'function') {
                jwqRefreshServerLoadedMaterialDiamonds();
            }
        });
    }
    if (toDept && toUser) {
        toDept.addEventListener('change', function () {
            var id = parseInt(toDept.value || '0', 10);
            jwqFillUserSelectForDept(toUser, id);
        });
    }

    var btnSave = document.getElementById('jwqBtnSave');
    if (btnSave) {
        btnSave.addEventListener('click', function () {
            var jwo = document.getElementById('jwqCurrentJwoId');
            var id = jwo ? parseInt(jwo.value || '0', 10) : 0;
            if (id < 1) {
                alert('Select a job work order from the list above, or open this page with ?jwo_id=');
                return;
            }
            var toD = document.getElementById('jwqToDept');
            var toU = document.getElementById('jwqToUser');
            var toDeptId = toD ? parseInt(toD.value || '0', 10) : 0;
            var fromDRef = document.getElementById('jwqFromDept');
            var fromURef = document.getElementById('jwqFromUser');
            var reduceOnly = typeof window.jwqIsReduceWeightMode === 'function' && window.jwqIsReduceWeightMode();
            var addOnly = typeof window.jwqIsAddWeightMode === 'function' && window.jwqIsAddWeightMode();
            var toUserVal = toU && toU.value ? toU.value : '';
            if (toDeptId < 1) {
                alert(reduceOnly
                    ? 'Please select To Dept to receive the reduced weight.'
                    : 'Please select destination department (To Dept.). The job will move to that department after save.');
                return;
            }
            if (reduceOnly) {
                var metalReduceCheck = typeof window.jwqCollectMaterialMetalReduceForSave === 'function'
                    ? window.jwqCollectMaterialMetalReduceForSave()
                    : [];
                var manualReduceCheck = typeof window.jwqComputeManualReduceWeightGrams === 'function'
                    ? window.jwqComputeManualReduceWeightGrams()
                    : 0;
                if ((!metalReduceCheck || metalReduceCheck.length === 0) && manualReduceCheck <= 0) {
                    alert('Enter reduce weight in Metal Wt (e.g. 3 g reduces from current total), or add M. Exch. rows.');
                    return;
                }
                var sumReduceWt = typeof window.jwqSumMaterialMetalReduceWt === 'function' ? window.jwqSumMaterialMetalReduceWt() : 0;
                var totalReduceCheck = sumReduceWt + manualReduceCheck;
                var firstLineTr = document.querySelector('#jwqOrderLinesBody tr[data-item-id]');
                if (firstLineTr && totalReduceCheck > 0) {
                    var origLineT = parseFloat(firstLineTr.getAttribute('data-jwq-orig-total-before-reduce') || firstLineTr.getAttribute('data-orig-total-wt') || '');
                    if (!isFinite(origLineT) && typeof jwqLineFieldNum === 'function') {
                        origLineT = jwqLineFieldNum(firstLineTr, 'total_wt');
                    }
                    if (isFinite(origLineT) && totalReduceCheck > origLineT + 0.0001) {
                        alert('Total reduce weight (' + totalReduceCheck.toFixed(3) + ' g) cannot exceed line total weight (' + origLineT.toFixed(3) + ' g).');
                        return;
                    }
                }
            }
            if (addOnly) {
                var metalAddCheck = typeof window.jwqCollectMaterialMetalAddForSave === 'function'
                    ? window.jwqCollectMaterialMetalAddForSave()
                    : [];
                var manualAddCheck = typeof window.jwqComputeManualAddWeightGrams === 'function'
                    ? window.jwqComputeManualAddWeightGrams()
                    : 0;
                if ((!metalAddCheck || metalAddCheck.length === 0) && manualAddCheck <= 0) {
                    alert('Enter extra metal weight in Metal Wt (e.g. 3 g adds to current total), or add M. Exch. rows.');
                    return;
                }
                if (manualAddCheck > 0) {
                    var fromDeptIdAdd = fromDRef ? parseInt(fromDRef.value || '0', 10) : 0;
                    if (fromDeptIdAdd < 1) {
                        alert('Select From Dept to deduct manual add weight.');
                        if (fromDRef) {
                            fromDRef.focus();
                        }
                        return;
                    }
                    if (typeof window.mpReadInwardBalanceWt === 'function') {
                        var fromUserIdAdd = fromURef ? parseInt(fromURef.value || '0', 10) : 0;
                        var selDept = typeof selectedDeptId !== 'undefined' && selectedDeptId != null ? parseInt(selectedDeptId, 10) : 0;
                        var selUser = typeof selectedUserId !== 'undefined' && selectedUserId != null ? parseInt(selectedUserId, 10) : 0;
                        var balanceFromSameDept = (selDept < 1 || selDept === fromDeptIdAdd)
                            && (selUser < 1 || fromUserIdAdd < 1 || selUser === fromUserIdAdd);
                        if (balanceFromSameDept) {
                            var deptBalance = window.mpReadInwardBalanceWt();
                            if (deptBalance !== null && manualAddCheck > deptBalance + 0.0001) {
                                alert('Insufficient balance in From Dept (available ' + deptBalance.toFixed(3) + ' g, need ' + manualAddCheck.toFixed(3) + ' g).');
                                return;
                            }
                        }
                    }
                }
            }
            var runSave = function () {
                var manualAddGramsForSave = 0;
                var manualReduceGramsForSave = 0;
                if (addOnly && typeof window.jwqComputeManualAddWeightGrams === 'function') {
                    manualAddGramsForSave = window.jwqComputeManualAddWeightGrams();
                }
                if (reduceOnly && typeof window.jwqComputeManualReduceWeightGrams === 'function') {
                    manualReduceGramsForSave = window.jwqComputeManualReduceWeightGrams();
                }
                if (reduceOnly && typeof window.jwqSyncOrderLineTotalWtFromMetalReduceTable === 'function') {
                    window.jwqSyncOrderLineTotalWtFromMetalReduceTable();
                }
                if (addOnly && typeof window.jwqFinalizeAddWeightLineTotalsBeforeSave === 'function') {
                    window.jwqFinalizeAddWeightLineTotalsBeforeSave();
                }
                if (reduceOnly && typeof window.jwqFinalizeReduceWeightLineTotalsBeforeSave === 'function') {
                    window.jwqFinalizeReduceWeightLineTotalsBeforeSave();
                }
                if (typeof jwqSyncOrderLineDiamondWtFromMaterialTable === 'function') {
                    jwqSyncOrderLineDiamondWtFromMaterialTable();
                }
                if (typeof jwqDebugLogQueueLinesBeforeSave === 'function') {
                    jwqDebugLogQueueLinesBeforeSave();
                }
                var fd = new FormData();
                fd.append('jobwork_order_id', String(id));
                fd.append('to_dept_id', String(toDeptId));
                if (toUserVal) {
                    fd.append('to_user_id', toUserVal);
                }
                if (reduceOnly) {
                    fd.append('reduce_only', '1');
                }
                if (addOnly) {
                    fd.append('add_only', '1');
                }
                if (fromDRef && fromDRef.value) {
                    fd.append('from_dept_id', String(fromDRef.value));
                }
                if (fromURef && fromURef.value) {
                    fd.append('from_user_id', String(fromURef.value));
                }
                var lines = typeof jwqCollectQueueLinePayload === 'function' ? jwqCollectQueueLinePayload() : [];
                fd.append('queue_lines', JSON.stringify(lines));
                var dstock = typeof jwqCollectMaterialDiamondStockForSave === 'function' ? jwqCollectMaterialDiamondStockForSave() : [];
                var metalReduceSave = reduceOnly && typeof window.jwqCollectMaterialMetalReduceForSave === 'function'
                    ? window.jwqCollectMaterialMetalReduceForSave()
                    : [];
                var metalAddSave = addOnly && typeof window.jwqCollectMaterialMetalAddForSave === 'function'
                    ? window.jwqCollectMaterialMetalAddForSave()
                    : [];
                if (window.console && typeof console.log === 'function') {
                    console.log('JWQ_SAVE_LINES', lines);
                    console.log('JWQ_SAVE_DIAMONDS', dstock);
                    if (reduceOnly) {
                        console.log('JWQ_SAVE_METAL_REDUCE', metalReduceSave);
                    }
                    if (addOnly) {
                        console.log('JWQ_SAVE_METAL_ADD', metalAddSave);
                    }
                }
                if ((!dstock || dstock.length === 0)
                    && (!metalReduceSave || metalReduceSave.length === 0)
                    && (!metalAddSave || metalAddSave.length === 0)
                    && typeof jwqMaterialBodyHasVisibleDataRows === 'function'
                    && jwqMaterialBodyHasVisibleDataRows()) {
                    alert('Jobwork Queue: diamond save payload is empty but the material table has rows. Check the console (JWQ_SAVE_DIAMONDS).');
                }
                // Per-piece diamond lines from .jwq-material-diamond-row only (see jwqCollectMaterialDiamondStockForSave).
                fd.append('jwq_diamond_stock_lines', JSON.stringify(dstock));
                if (reduceOnly && metalReduceSave && metalReduceSave.length > 0) {
                    fd.append('metal_exchange_reduce_lines', JSON.stringify(metalReduceSave));
                }
                if (addOnly && metalAddSave && metalAddSave.length > 0) {
                    fd.append('metal_exchange_add_lines', JSON.stringify(metalAddSave));
                }
                if (addOnly) {
                    fd.append('manual_add_weight_grams', String(manualAddGramsForSave));
                }
                if (reduceOnly) {
                    fd.append('manual_reduce_weight_grams', String(manualReduceGramsForSave));
                }
                if (typeof jwqEnsureRemovedDiamondIssuesArray === 'function' && jwqEnsureRemovedDiamondIssuesArray().length > 0) {
                    fd.append('removed_diamond_issues', JSON.stringify(window.jwqRemovedDiamondIssues));
                }
                fetch('ajax/mp-save-jobwork-queue.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function (r) {
                        return r.text().then(function (txt) {
                            return { status: r.status, text: txt };
                        });
                    })
                    .then(function (resp) {
                        var data = null;
                        try {
                            data = JSON.parse(resp.text || '{}');
                        } catch (e) {
                            var raw = String(resp.text || '').trim();
                            alert('Save failed (invalid server response): ' + (raw ? raw.slice(0, 320) : ('HTTP ' + resp.status)));
                            return;
                        }
                        if (!data.ok) {
                            alert(data.message || 'Save failed');
                            return;
                        }
                        window.jwqRemovedDiamondIssues = [];
                        if (data.jobwork_queue_no) {
                            var qel = document.getElementById('jwqModalQueueNo');
                            var qh = document.getElementById('jwqJobworkQueueNo');
                            if (qel) {
                                qel.textContent = data.jobwork_queue_no;
                            }
                            if (qh) {
                                qh.value = data.jobwork_queue_no;
                            }
                            document.querySelectorAll('.jwq-order-boot[data-jwo-id="' + id + '"]').forEach(function (b) {
                                b.setAttribute('data-jobwork-queue-no', data.jobwork_queue_no);
                            });
                        }
                        if (typeof window.mpUpdateJobCardAfterTransfer === 'function') {
                            window.mpUpdateJobCardAfterTransfer(id, data);
                        }
                        if (typeof window.mpReloadManufacturingQueueTable === 'function') {
                            window.mpReloadManufacturingQueueTable();
                        }
                        if (typeof mpJwqPromptPrintAfterSave === 'function') {
                            mpJwqPromptPrintAfterSave(id);
                        } else if (window.JWQ_PAGE_MODE) {
                            alert(data.message || 'Saved.');
                        } else {
                            jwqCloseModal();
                        }
                    })
                    .catch(function () {
                        alert('Save failed');
                    });
            };
            if (typeof jwqFetchDiamondServerItemsThen === 'function') {
                jwqFetchDiamondServerItemsThen(runSave);
            } else {
                runSave();
            }
        });
    }

    var btnCat = document.getElementById('jwqBtnCatalogue');
    if (btnCat) {
        btnCat.addEventListener('click', function () {
            alert('Create Catalogue — link this to your catalogue flow when ready.');
        });
    }

    var btnAddLine = document.getElementById('jwqBtnAddLine');
    if (btnAddLine) {
        btnAddLine.addEventListener('click', function () {
            var jwo = document.getElementById('jwqCurrentJwoId');
            var id = jwo ? parseInt(jwo.value || '0', 10) : 0;
            var fd = new FormData();
            fd.append('jobwork_order_id', id > 0 ? String(id) : '0');
            btnAddLine.disabled = true;
            fetch('ajax/mp-add-jobwork-order-line.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || !data.ok) {
                        alert((data && data.message) || 'Could not add row');
                        return;
                    }
                    if (data.created_jobwork_order) {
                        jwqApplyDraftJwoFromServer(data);
                    }
                    var loadId = parseInt(data.jobwork_order_id, 10) || id;
                    jwqLoadOrderLinesIntoTable(loadId, jwqGetOrderRefForLines(), jwqGetFirstProductHint());
                })
                .catch(function () {
                    alert('Could not add row');
                })
                .finally(function () {
                    btnAddLine.disabled = false;
                });
        });
    }

    var btnNew = document.getElementById('jwqBtnNew');
    if (btnNew && window.JWQ_PAGE_MODE) {
        btnNew.addEventListener('click', function () {
            window.location.href = 'jobwork-queue.php';
        });
    }

    var btnBom = document.getElementById('jwqBtnBom');
    var btnOrd = document.getElementById('jwqBtnOrder');
    if (btnBom) btnBom.addEventListener('click', function () { alert('BOM — connect to bill of materials.'); });
    if (btnOrd) btnOrd.addEventListener('click', function () { alert('Order — connect to order lookup.'); });

    var imgBox = document.getElementById('jwqImagesBox');
    var imgInput = document.getElementById('jwqImagesInput');
    if (imgBox && imgInput) {
        imgBox.addEventListener('click', function () { imgInput.click(); });
        imgInput.addEventListener('change', function () { /* store or upload files */ });
    }

    var btnPrint = document.getElementById('jwqBtnPrint');
    if (btnPrint) {
        btnPrint.addEventListener('click', function () {
            window.print();
        });
    }

    document.addEventListener('keydown', function (e) {
        if (window.JWQ_PAGE_MODE) return;
        if (e.key === 'Escape' && overlay.classList.contains('show')) jwqCloseModal();
    });
}

document.addEventListener('DOMContentLoaded', function () {
    initColumnManager({
        tableId: 'jwqOrderLinesTable',
        panelId: 'jwqColumnsPanel',
        toggleSelector: '.jwq-settings-toggle',
        searchId: 'jwqColumnsSearch',
        listId: 'jwqColumnsList',
        storageKey: window.JWQ_LINE_COL_STORAGE || 'jobwork_queue_page_jwq_order_lines_hidden_columns',
        panelLayout: 'inline'
    });
    initJwqOrderLinesColReorderResize();

    initColumnManager({
        tableId: 'jwqInwardStockTable',
        panelId: 'jwqInwardStockColumnsPanel',
        toggleSelector: '#jwqInwardStockBtnColumns',
        searchId: 'jwqInwardStockColumnsSearch',
        listId: 'jwqInwardStockColumnsList',
        storageKey: window.JWQ_INWARD_COL_STORAGE || 'jobwork_queue_page_jwq_inward_modal_hidden_columns',
        panelPosition: 'absolute'
    });

    var jwqInEx = document.getElementById('jwqInwardStockBtnExcel');
    var jwqInPdf = document.getElementById('jwqInwardStockBtnPdf');
    if (jwqInEx) jwqInEx.addEventListener('click', function (e) { e.preventDefault(); jwqInwardStockExportExcel(); });
    if (jwqInPdf) jwqInPdf.addEventListener('click', function (e) { e.preventDefault(); jwqInwardStockExportPdf(); });

    initJwqDecimalInputGuards();
    initJobworkQueueModal();
    initJwqPaymentIcons();

    initJwqOrderSearch();

    var boot = document.getElementById('jwqPageBootstrapBtn');
    if (boot && boot.getAttribute('data-jwo-id')) {
        jwqOpenModal(boot);
    } else if (window.JWQ_PAGE_MODE) {
        var prev = (window.JWQ_PREVIEW_QUEUE_NO && String(window.JWQ_PREVIEW_QUEUE_NO).trim()) || '';
        if (prev) {
            var qel = document.getElementById('jwqModalQueueNo');
            var qh = document.getElementById('jwqJobworkQueueNo');
            if (qel) {
                qel.textContent = prev;
                qel.setAttribute('title', 'Next number in series; final number is saved with the job work order.');
            }
            if (qh) qh.value = prev;
        }
        var tbodyInit = document.getElementById('jwqOrderLinesBody');
        if (tbodyInit && tbodyInit.children.length === 0) {
            var ph0 = {
                design_no: '—',
                barcode: '—',
                product_name: '—',
                carat: '—',
                final_weight: 0,
                net_weight: 0,
                purity: 0,
                quantity: 0,
                rate: null,
                less_weight: 0,
                diamond_weight: 0,
                gross_weight: 0
            };
            tbodyInit.insertAdjacentHTML('beforeend', jwqBuildLineRowHtml(ph0, '—'));
            jwqApplyStoredLineColumnVisibility();
        }
    }
});
