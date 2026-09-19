/**
 * Auto-select Sales Person (#salesPerson) when empty and the logged-in user is in the list.
 * Candidates come from window.AURAGOLD_LOGIN_SP_CANDIDATES (set in footer-script.php).
 */
(function (window, document) {
    'use strict';

    function candidates() {
        var c = window.AURAGOLD_LOGIN_SP_CANDIDATES;
        if (!c) return [];
        if (typeof c === 'string') {
            c = c ? [c] : [];
        }
        if (!Array.isArray(c)) return [];
        return c.map(function (x) { return String(x || '').trim(); }).filter(Boolean);
    }

    function findOptionValue(selectEl, candList) {
        if (!selectEl || !selectEl.options) return '';
        var i, j, opt, ov, cand;
        for (j = 0; j < candList.length; j++) {
            cand = candList[j];
            for (i = 0; i < selectEl.options.length; i++) {
                opt = selectEl.options[i];
                ov = String(opt.value || '').trim();
                if (ov === '') continue;
                if (ov.toLowerCase() === cand.toLowerCase()) {
                    return ov;
                }
            }
        }
        return '';
    }

    function applyDefaultSalesPerson(force) {
        var list = candidates();
        if (!list.length) return false;
        var el = document.getElementById('salesPerson');
        if (!el || el.tagName !== 'SELECT') return false;
        if (el.disabled || el.readOnly) return false;
        var cur = String(el.value || '').trim();
        if (!force && cur !== '') return false;
        var match = findOptionValue(el, list);
        if (!match) return false;
        if (cur === match) return true;
        el.value = match;
        try {
            if (typeof window.jQuery !== 'undefined') {
                var $el = window.jQuery(el);
                if ($el.length) {
                    $el.val(match).trigger('change');
                }
            } else {
                el.dispatchEvent(new Event('change', { bubbles: true }));
            }
        } catch (e) {}
        return true;
    }

    window.auragoldApplyDefaultSalesPerson = applyDefaultSalesPerson;

    function boot() {
        applyDefaultSalesPerson(false);
        setTimeout(function () { applyDefaultSalesPerson(false); }, 50);
        setTimeout(function () { applyDefaultSalesPerson(false); }, 300);
        setTimeout(function () { applyDefaultSalesPerson(false); }, 800);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    // After "New +" / reset, re-apply when the field is cleared
    document.addEventListener('click', function (e) {
        var t = e.target;
        if (!t || !t.closest) return;
        var btn = t.closest('a,button,[role="button"],.btn');
        if (!btn) return;
        var id = String(btn.id || '').toLowerCase();
        var cls = String(btn.className || '').toLowerCase();
        var txt = String(btn.textContent || '').toLowerCase().replace(/\s+/g, ' ');
        var looksNew = id.indexOf('new') !== -1
            || cls.indexOf('new') !== -1
            || /\bnew\b/.test(txt)
            || /\breset\b/.test(txt);
        if (!looksNew) return;
        setTimeout(function () { applyDefaultSalesPerson(true); }, 150);
        setTimeout(function () { applyDefaultSalesPerson(true); }, 500);
    }, true);
})(window, document);
