/**
 * Column drag-reorder + resize (class acr-col-table).
 * Requires Sortable 1.x (load Sortable.min.js before this file).
 */
(function (global) {
    'use strict';

    var STYLE_ID = 'auragold-col-reorder-css';

    function injectCss() {
        if (document.getElementById(STYLE_ID)) return;
        var s = document.createElement('style');
        s.id = STYLE_ID;
        s.textContent = [
            'table.acr-col-table { border-collapse: collapse; }',
            'table.acr-col-table thead th,',
            'table.acr-col-table tbody td,',
            'table.acr-col-table tfoot td { border-left: 1px solid #cbd5e1; }',
            'table.acr-col-table thead th:first-child,',
            'table.acr-col-table tbody td:first-child,',
            'table.acr-col-table tfoot td:first-child { border-left: none; }',
            'table.acr-col-table thead th.acr-th-reorder,',
            'table.acr-col-table thead th.acr-th-resizable { position: relative !important; }',
            'table.acr-col-table thead th .acr-th-inner {',
            '  display: inline-flex; align-items: center; gap: 0.35rem;',
            '  max-width: calc(100% - 14px); vertical-align: middle; cursor: grab;',
            '  touch-action: none; -webkit-user-select: none; user-select: none;',
            '}',
            'table.acr-col-table thead th .acr-th-inner:active { cursor: grabbing; }',
            'table.acr-col-table thead th .acr-th-drag {',
            '  display: inline-flex; align-items: center; justify-content: center;',
            '  vertical-align: middle; cursor: grab; color: #c9a962;',
            '  line-height: 1; flex-shrink: 0; touch-action: none;',
            '  pointer-events: auto; position: relative; z-index: 2;',
            '}',
            'table.acr-col-table thead th .acr-th-drag .feather,',
            'table.acr-col-table thead th .acr-th-drag svg { width: 0.95rem; height: 0.95rem; }',
            'table.acr-col-table thead th .acr-th-drag:active { cursor: grabbing; }',
            'table.acr-col-table thead th.acr-th-reorder { cursor: default; }',
            'table.acr-col-table thead th.acr-sortable-drag { opacity: 0.95; background: #fff7ed !important; }',
            'table.acr-col-table thead th .acr-th-resize {',
            '  position: absolute; right: 0; top: 0; bottom: 0; width: 12px;',
            '  cursor: col-resize; z-index: 8; touch-action: none; pointer-events: auto;',
            '  background: linear-gradient(90deg, transparent, rgba(201, 169, 98, 0.28));',
            '}',
            'table.acr-col-table thead th .acr-th-resize:hover {',
            '  background: rgba(201, 169, 98, 0.35);',
            '}',
            'table.acr-col-table thead th.acr-col-resizing { user-select: none; }',
            '.acr-sortable-ghost { opacity: 0.45; }',
            '.acr-sortable-chosen { opacity: 0.9; }',
            '.acr-col-scroll-dragging { overflow-x: hidden !important; cursor: grabbing !important; }'
        ].join('\n');
        document.head.appendChild(s);
    }

    function theadRow(table) {
        return table.querySelector('thead tr');
    }

    function scrollParentEl(table) {
        var el = table.parentElement;
        while (el && el !== document.body) {
            var st = window.getComputedStyle(el);
            var ox = st.overflowX;
            if (ox === 'auto' || ox === 'scroll' || ox === 'overlay') {
                return el;
            }
            el = el.parentElement;
        }
        return null;
    }

    function ensureDataCols(table) {
        var trh = theadRow(table);
        if (!trh) return;
        var n = trh.cells.length;
        var i;
        for (i = 0; i < n; i++) {
            var th = trh.cells[i];
            if (!th.getAttribute('data-col')) {
                th.setAttribute('data-col', 'c' + i);
            }
        }
        var keys = [];
        for (i = 0; i < n; i++) {
            keys.push(trh.cells[i].getAttribute('data-col'));
        }
        function syncTr(tr) {
            if (tr.querySelector('td[colspan]')) return;
            var tds = tr.querySelectorAll('td');
            if (tds.length !== n) return;
            for (var j = 0; j < n; j++) {
                if (!tds[j].getAttribute('data-col')) {
                    tds[j].setAttribute('data-col', keys[j]);
                }
            }
        }
        table.querySelectorAll('tbody tr').forEach(syncTr);
        table.querySelectorAll('tfoot tr').forEach(syncTr);
    }

    function getOrder(table) {
        var tr = theadRow(table);
        return Array.prototype.map.call(tr.querySelectorAll('th[data-col]'), function (th) {
            return th.getAttribute('data-col');
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

    function applyOrder(table, order) {
        var tr = theadRow(table);
        if (!tr) return;
        var map = {};
        tr.querySelectorAll('th[data-col]').forEach(function (th) {
            map[th.getAttribute('data-col')] = th;
        });
        order.forEach(function (k) {
            if (map[k]) tr.appendChild(map[k]);
        });

        function syncTr(trel) {
            if (trel.querySelector('td[colspan]')) return;
            var by = {};
            trel.querySelectorAll('td[data-col]').forEach(function (td) {
                var k = td.getAttribute('data-col');
                if (k && by[k] == null) by[k] = td;
            });
            order.forEach(function (k) {
                if (by[k]) trel.appendChild(by[k]);
            });
        }
        table.querySelectorAll('tbody tr').forEach(syncTr);
        table.querySelectorAll('tfoot tr').forEach(syncTr);
    }

    function thMinWidth(th, fallback) {
        var attr = th.getAttribute('data-acr-min');
        if (attr != null && attr !== '') {
            var n = parseInt(attr, 10);
            if (!isNaN(n) && n > 0) return n;
        }
        var st = th.style.minWidth || window.getComputedStyle(th).minWidth;
        if (st && st !== 'auto' && st !== '0px') {
            var p = parseInt(st, 10);
            if (!isNaN(p) && p > 0) return p;
        }
        return fallback || 60;
    }

    function applyWidths(table, widths, minWidthDefault) {
        if (!widths || typeof widths !== 'object') return;
        table.querySelectorAll('thead th[data-col]').forEach(function (th) {
            var k = th.getAttribute('data-col');
            if (!k || widths[k] == null) return;
            var floor = thMinWidth(th, minWidthDefault);
            var px = Math.max(floor, parseInt(widths[k], 10) || 0);
            th.style.width = px + 'px';
            th.style.minWidth = px + 'px';
            table.querySelectorAll('tbody td[data-col="' + k + '"], tfoot td[data-col="' + k + '"]').forEach(function (td) {
                td.style.minWidth = px + 'px';
            });
        });
    }

    function collectWidths(table) {
        var w = {};
        table.querySelectorAll('thead th[data-col]').forEach(function (th) {
            var k = th.getAttribute('data-col');
            if (k) w[k] = Math.round(th.getBoundingClientRect().width);
        });
        return w;
    }

    function loadJson(key) {
        try {
            var raw = localStorage.getItem(key);
            if (!raw) return null;
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    }

    function loadOrder(key) {
        var o = loadJson(key);
        return Array.isArray(o) ? o : null;
    }

    function loadWidths(key) {
        var o = loadJson(key);
        return o && typeof o === 'object' && !Array.isArray(o) ? o : null;
    }

    function saveOrder(key, order) {
        try {
            localStorage.setItem(key, JSON.stringify(order));
        } catch (e) {}
    }

    function saveWidths(key, widths) {
        try {
            localStorage.setItem(key, JSON.stringify(widths));
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

    function enhanceHeaders(table, fixedFirst, fixedKey, fixedLast, fixedKeys) {
        var tr = theadRow(table);
        if (!tr) return;
        var ths = tr.querySelectorAll('th');
        var lastIdx = ths.length - 1;
        var fixedKeySet = {};
        if (fixedKeys && fixedKeys.length) {
            fixedKeys.forEach(function (k) { if (k) fixedKeySet[k] = true; });
        }
        ths.forEach(function (th, idx) {
            if (th._acrEnhanced) return;
            th._acrEnhanced = true;
            var k = th.getAttribute('data-col');
            var isFixed = (fixedFirst && (idx === 0 || (fixedKey && k === fixedKey)))
                || (fixedLast && idx === lastIdx)
                || (k && fixedKeySet[k]);
            th.classList.toggle('acr-th-fixed', isFixed);
            th.classList.toggle('acr-th-reorder', !isFixed);

            var inner = th.querySelector('.acr-th-inner');
            if (!inner) {
                inner = document.createElement('span');
                inner.className = 'acr-th-inner';
                var nodes = Array.prototype.slice.call(th.childNodes);
                nodes.forEach(function (node) {
                    if (node.nodeType === 1 && node.classList
                        && (node.classList.contains('acr-th-resize') || node.classList.contains('acr-th-drag'))) {
                        return;
                    }
                    inner.appendChild(node);
                });
                th.insertBefore(inner, th.firstChild);
            }

            if (!isFixed) {
                th.classList.add('acr-th-resizable');
                if (!inner.querySelector('.acr-th-drag')) {
                    var drag = document.createElement('span');
                    drag.className = 'acr-th-drag';
                    drag.title = 'Drag to reorder column';
                    drag.innerHTML = '<i class="feather icon-move" aria-hidden="true"></i>';
                    inner.insertBefore(drag, inner.firstChild);
                }
                if (!th.querySelector('.acr-th-resize')) {
                    var resize = document.createElement('span');
                    resize.className = 'acr-th-resize';
                    resize.title = 'Drag to resize column';
                    resize.setAttribute('aria-hidden', 'true');
                    th.appendChild(resize);
                }
            }
        });
    }

    function resolveDragHandle(table, dragHandle) {
        if (dragHandle && String(dragHandle).trim()) {
            return String(dragHandle).trim();
        }
        if (table.querySelector('thead th .acr-th-inner')) {
            return '.acr-th-inner';
        }
        return 'th.acr-th-reorder';
    }

    function bindResize(table, widthsKey, minWidthDefault) {
        if (table._acrResizeBound) return;
        table._acrResizeBound = true;
        table.addEventListener('mousedown', function (e) {
            var handle = e.target.closest ? e.target.closest('.acr-th-resize') : null;
            if (!handle || !table.contains(handle)) return;
            e.preventDefault();
            e.stopPropagation();
            if (e.stopImmediatePropagation) e.stopImmediatePropagation();
            var th = handle.closest('th');
            if (!th) return;
            var startX = e.clientX;
            var startW = th.getBoundingClientRect().width;
            var minW = thMinWidth(th, minWidthDefault);
            function onMove(e2) {
                var dx = e2.clientX - startX;
                var w = Math.max(minW, Math.round(startW + dx));
                th.style.width = w + 'px';
                th.style.minWidth = w + 'px';
                var col = th.getAttribute('data-col');
                if (col) {
                    table.querySelectorAll('tbody td[data-col="' + col + '"], tfoot td[data-col="' + col + '"]').forEach(function (td) {
                        td.style.minWidth = w + 'px';
                        td.style.width = w + 'px';
                    });
                }
            }
            function onUp() {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
                document.body.style.cursor = '';
                th.classList.remove('acr-col-resizing');
                if (widthsKey) saveWidths(widthsKey, collectWidths(table));
            }
            th.classList.add('acr-col-resizing');
            document.body.style.cursor = 'col-resize';
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        }, true);
    }

    function bindSortable(table, storageKey, fixedFirst, fixedKey, fixedLast, dragHandle) {
        if (typeof Sortable === 'undefined') return;
        var tr = theadRow(table);
        if (!tr) return;
        if (tr._acrSortable) {
            tr._acrSortable.destroy();
            tr._acrSortable = null;
        }
        var scrollEl = scrollParentEl(table);
        var lastGood;
        function refreshLastGood() {
            lastGood = getOrder(table).slice();
        }
        refreshLastGood();
        var handleSel = resolveDragHandle(table, dragHandle);
        tr._acrSortable = Sortable.create(tr, {
            animation: 150,
            handle: handleSel,
            draggable: 'th.acr-th-reorder',
            filter: function (evt, target) {
                var hit = (evt && evt.target) ? evt.target : target;
                if (hit && hit.closest && hit.closest('.acr-th-resize')) {
                    return true;
                }
                if (!target) return true;
                if (target.classList && (target.classList.contains('acr-th-fixed') || target.classList.contains('acr-th-resize'))) {
                    return true;
                }
                if (target.closest && target.closest('.acr-th-resize, .acr-th-fixed')) {
                    return true;
                }
                // Skip columns currently hidden via show/hide settings.
                var th = target.closest ? target.closest('th') : null;
                if (th && (th.style.display === 'none' || window.getComputedStyle(th).display === 'none')) {
                    return true;
                }
                return false;
            },
            preventOnFilter: true,
            forceFallback: true,
            fallbackOnBody: true,
            fallbackTolerance: 3,
            fallbackClass: 'acr-sortable-drag',
            direction: 'horizontal',
            swapThreshold: 0.65,
            bubbleScroll: true,
            scroll: scrollEl || true,
            scrollSensitivity: 60,
            scrollSpeed: 16,
            ghostClass: 'acr-sortable-ghost',
            chosenClass: 'acr-sortable-chosen',
            dragClass: 'acr-sortable-drag',
            onStart: function () {
                if (scrollEl) scrollEl.classList.add('acr-col-scroll-dragging');
            },
            onEnd: function () {
                if (scrollEl) scrollEl.classList.remove('acr-col-scroll-dragging');
                var order = getOrder(table);
                var ok = validPermutation(order, lastGood);
                if (fixedFirst) {
                    var fk = fixedKey || theadRow(table).cells[0].getAttribute('data-col');
                    if (order[0] !== fk) ok = false;
                }
                if (fixedLast && order.length) {
                    var lastKey = lastGood[lastGood.length - 1];
                    if (order[order.length - 1] !== lastKey) ok = false;
                }
                if (!ok) {
                    applyOrder(table, lastGood);
                    refreshLastGood();
                    return;
                }
                applyOrder(table, order);
                saveOrder(storageKey, order);
                refreshLastGood();
            }
        });
    }

    function refresh(table) {
        if (!table || table.tagName !== 'TABLE' || !table._acrOpts) return;
        var opts = table._acrOpts;
        ensureDataCols(table);
        applyOrder(table, getOrder(table));
        var w = loadWidths(opts.widthsStorageKey);
        if (w) applyWidths(table, w, opts.minWidth || 60);
        enhanceHeaders(table, !!opts.fixedFirst, opts.fixedKey || null, !!opts.fixedLast, opts.fixedKeys || null);
        bindSortable(table, opts.storageKey, !!opts.fixedFirst, opts.fixedKey || null, !!opts.fixedLast, opts.dragHandle || null);
    }

    /**
     * @param {string|HTMLElement} selector
     * @param {{ storageKey: string, widthsStorageKey?: string, fixedFirst?: boolean, fixedKey?: string, fixedLast?: boolean, fixedKeys?: string[], minWidth?: number }} opts
     */
    function init(selector, opts) {
        opts = opts || {};
        if (!opts.storageKey) return;
        var table = typeof selector === 'string' ? document.querySelector(selector) : selector;
        if (!table || table.tagName !== 'TABLE') return;

        opts.widthsStorageKey = opts.widthsStorageKey || (opts.storageKey + '_widths');
        opts.minWidth = opts.minWidth || 60;
        table._acrOpts = opts;

        injectCss();
        table.classList.add('acr-col-table');

        var theadRows = table.querySelectorAll('thead tr');
        if (theadRows.length !== 1) {
            return;
        }

        ensureDataCols(table);
        var defaultOrder = getOrder(table);
        var saved = loadOrder(opts.storageKey);
        var order = normalizeOrder(saved, defaultOrder);
        if (opts.fixedLast && order.length && defaultOrder.length) {
            var lastKey = defaultOrder[defaultOrder.length - 1];
            order = order.filter(function (k) { return k !== lastKey; });
            order.push(lastKey);
        }
        if (validPermutation(order, defaultOrder)) {
            applyOrder(table, order);
        }

        enhanceHeaders(table, !!opts.fixedFirst, opts.fixedKey || null, !!opts.fixedLast, opts.fixedKeys || null);
        if (typeof global.feather !== 'undefined' && typeof global.feather.replace === 'function') {
            global.feather.replace({ scope: table });
        }
        bindResize(table, opts.widthsStorageKey, opts.minWidth);
        bindSortable(table, opts.storageKey, !!opts.fixedFirst, opts.fixedKey || null, !!opts.fixedLast, opts.dragHandle || null);

        var savedW = loadWidths(opts.widthsStorageKey);
        if (savedW) applyWidths(table, savedW, opts.minWidth);
    }

    global.AuragoldColReorder = { init: init, refresh: refresh };
})(typeof window !== 'undefined' ? window : this);
