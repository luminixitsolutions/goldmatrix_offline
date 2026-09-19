/**
 * Drag-reorder for Chart of Account numeric grid (.coa-h-nums / .coa-nums).
 * Requires Sortable 1.x before this file.
 */
(function (global) {
    'use strict';

    var STYLE_ID = 'auragold-coa-col-reorder-css';

    function injectCss() {
        if (document.getElementById(STYLE_ID)) return;
        var s = document.createElement('style');
        s.id = STYLE_ID;
        s.textContent = [
            '.coa-h-nums > span.coa-col-reorder { position: relative; display: flex; align-items: center; justify-content: flex-end; gap: 0.35rem; }',
            '.coa-h-nums .acr-th-drag {',
            '  display: inline-flex; align-items: center; justify-content: center;',
            '  cursor: grab; color: #c9a962; line-height: 1; flex-shrink: 0;',
            '}',
            '.coa-h-nums .acr-th-drag .feather { width: 0.95rem; height: 0.95rem; }',
            '.coa-h-nums .acr-th-drag:active { cursor: grabbing; }',
            '.acr-coa-ghost { opacity: 0.45; }',
            '.acr-coa-chosen { opacity: 0.9; }'
        ].join('\n');
        document.head.appendChild(s);
    }

    function getOrder(head) {
        return Array.prototype.map.call(head.querySelectorAll(':scope > span[data-coa-col]'), function (el) {
            return el.getAttribute('data-coa-col');
        });
    }

    function normalizeOrder(saved, currentKeys) {
        if (!saved || !saved.length) return currentKeys.slice();
        var set = {};
        currentKeys.forEach(function (k) { set[k] = true; });
        var out = [];
        saved.forEach(function (k) {
            if (set[k]) {
                out.push(k);
                delete set[k];
            }
        });
        currentKeys.forEach(function (k) {
            if (set[k]) out.push(k);
        });
        return out;
    }

    function applyOrder(head, order) {
        var map = {};
        head.querySelectorAll(':scope > span[data-coa-col]').forEach(function (el) {
            map[el.getAttribute('data-coa-col')] = el;
        });
        order.forEach(function (k) {
            if (map[k]) head.appendChild(map[k]);
        });
        var panel = head.closest('.coa-panel');
        if (!panel) return;
        panel.querySelectorAll('.coa-nums').forEach(function (row) {
            var by = {};
            row.querySelectorAll(':scope > span[data-coa-col]').forEach(function (el) {
                var k = el.getAttribute('data-coa-col');
                if (k && by[k] == null) by[k] = el;
            });
            order.forEach(function (k) {
                if (by[k]) row.appendChild(by[k]);
            });
        });
    }

    function enhanceHeader(head, fixedFirst) {
        var spans = head.querySelectorAll(':scope > span');
        spans.forEach(function (sp, idx) {
            var isFixed = !!fixedFirst && idx === 0;
            sp.classList.toggle('coa-col-fixed', isFixed);
            sp.classList.toggle('coa-col-reorder', !isFixed);
            if (isFixed || sp.querySelector('.acr-th-drag')) return;
            var drag = document.createElement('span');
            drag.className = 'acr-th-drag';
            drag.title = 'Drag to reorder columns';
            drag.innerHTML = '<i class="feather icon-move" aria-hidden="true"></i>';
            sp.appendChild(drag);
        });
    }

    function loadOrder(key) {
        try {
            var raw = localStorage.getItem(key);
            if (!raw) return null;
            var o = JSON.parse(raw);
            return Array.isArray(o) ? o : null;
        } catch (e) {
            return null;
        }
    }

    function saveOrder(key, order) {
        try {
            localStorage.setItem(key, JSON.stringify(order));
        } catch (e) {}
    }

    function validPermutation(order, keys) {
        if (order.length !== keys.length) return false;
        var need = {};
        keys.forEach(function (k) { need[k] = 0; });
        order.forEach(function (k) {
            if (Object.prototype.hasOwnProperty.call(need, k)) need[k]++;
        });
        return keys.every(function (k) { return need[k] === 1; });
    }

    /**
     * @param {{ storageKey: string, headerSelector?: string, fixedFirst?: boolean }} opts
     */
    function init(opts) {
        opts = opts || {};
        if (typeof Sortable === 'undefined') return;
        var head = document.querySelector(opts.headerSelector || '.coa-h-nums');
        if (!head) return;

        injectCss();

        var spans = head.querySelectorAll(':scope > span');
        var defaultKeys = ['o', 'd', 'c', 'cl'];
        Array.prototype.forEach.call(spans, function (sp, i) {
            if (!sp.getAttribute('data-coa-col')) {
                sp.setAttribute('data-coa-col', defaultKeys[i] || ('x' + i));
            }
        });

        enhanceHeader(head, opts.fixedFirst !== false);

        var defaultOrder = getOrder(head);
        var saved = loadOrder(opts.storageKey);
        var order = normalizeOrder(saved, defaultOrder);
        if (validPermutation(order, defaultOrder)) {
            applyOrder(head, order);
        }

        var lastGood = getOrder(head).slice();
        if (head._acrCoaSortable) {
            head._acrCoaSortable.destroy();
            head._acrCoaSortable = null;
        }
        head._acrCoaSortable = Sortable.create(head, {
            animation: 150,
            handle: '.acr-th-drag',
            draggable: 'span.coa-col-reorder',
            filter: '.coa-col-fixed',
            preventOnFilter: false,
            ghostClass: 'acr-coa-ghost',
            chosenClass: 'acr-coa-chosen',
            onEnd: function () {
                var ord = getOrder(head);
                var ok = validPermutation(ord, lastGood);
                if (opts.fixedFirst !== false) {
                    var fk = head.querySelector(':scope > span') && head.querySelector(':scope > span').getAttribute('data-coa-col');
                    if (ord[0] !== fk) ok = false;
                }
                if (!ok) {
                    applyOrder(head, lastGood);
                    lastGood = getOrder(head).slice();
                    return;
                }
                applyOrder(head, ord);
                saveOrder(opts.storageKey, ord);
                lastGood = getOrder(head).slice();
            }
        });
    }

    global.AuragoldCoaColReorder = { init: init };
})(typeof window !== 'undefined' ? window : this);
