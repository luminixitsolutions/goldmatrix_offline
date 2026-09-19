/**
 * Due Date helpers — keep blank by default; only use a real saved date when present.
 */
(function (window, document) {
    'use strict';

    function pad2(n) {
        return (n < 10 ? '0' : '') + n;
    }

    function todayYmd() {
        var d = new Date();
        return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
    }

    function isValidYmd(raw) {
        var s = String(raw == null ? '' : raw).trim();
        if (s.length >= 10) s = s.substring(0, 10);
        if (!/^\d{4}-\d{2}-\d{2}$/.test(s) || s === '0000-00-00') return false;
        var p = s.split('-');
        var y = parseInt(p[0], 10);
        var m = parseInt(p[1], 10);
        var day = parseInt(p[2], 10);
        if (!y || m < 1 || m > 12 || day < 1 || day > 31) return false;
        var dt = new Date(y, m - 1, day);
        return dt.getFullYear() === y && dt.getMonth() === m - 1 && dt.getDate() === day;
    }

    /**
     * Return valid Y-m-d, or '' when empty/invalid (do not force today).
     * Optional fallback only used when provided and valid.
     */
    function normalizeDueDate(raw, fallback) {
        var s = String(raw == null ? '' : raw).trim();
        if (s.length >= 10) s = s.substring(0, 10);
        if (isValidYmd(s)) return s;
        if (fallback != null && fallback !== '') {
            var f = String(fallback).trim();
            if (f.length >= 10) f = f.substring(0, 10);
            if (isValidYmd(f)) return f;
        }
        return '';
    }

    /** Current #dueDate value, or '' if blank/invalid (never auto-fills today). */
    function getDueDateValue() {
        var el = document.getElementById('dueDate');
        var v = el ? String(el.value || '').trim() : '';
        return normalizeDueDate(v);
    }

    /** Clear invalid values (e.g. 0000-00-00) so the field shows blank. */
    function ensureDueDateBlankIfInvalid() {
        var el = document.getElementById('dueDate');
        if (!el) return;
        var v = String(el.value || '').trim();
        if (v !== '' && !isValidYmd(v)) {
            el.value = '';
        }
    }

    window.auragoldTodayYmd = todayYmd;
    window.auragoldNormalizeDueDate = normalizeDueDate;
    window.auragoldGetDueDateValue = getDueDateValue;
    window.auragoldEnsureDueDateDefault = ensureDueDateBlankIfInvalid;

    function boot() {
        ensureDueDateBlankIfInvalid();
        setTimeout(ensureDueDateBlankIfInvalid, 50);
        setTimeout(ensureDueDateBlankIfInvalid, 300);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})(window, document);
