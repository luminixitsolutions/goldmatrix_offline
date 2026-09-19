/**
 * Two-level column reorder for salesperson performance grid:
 * 1) Drag group headers (row 1) to reorder whole category blocks.
 * 2) Drag metric headers (row 2) within a group — order mirrors to all product groups (same 6 metrics).
 *    SCHEME (2 cols) reorders independently.
 * Requires Sortable 1.x.
 */
(function (global) {
    'use strict';

    var STYLE_ID = 'auragold-sp-col-reorder-css';

    function injectCss() {
        if (document.getElementById(STYLE_ID)) return;
        var s = document.createElement('style');
        s.id = STYLE_ID;
        s.textContent = [
            'table.sp-col-reorder-table { border-collapse: collapse; }',
            'table.sp-col-reorder-table thead th,',
            'table.sp-col-reorder-table tbody td { border-left: 1px solid #cbd5e1; }',
            'table.sp-col-reorder-table thead tr:first-child th:first-child,',
            'table.sp-col-reorder-table tbody td:first-child { border-left: none; }',
            'table.sp-col-reorder-table thead th .sp-group-drag,',
            'table.sp-col-reorder-table thead th .sp-metric-drag {',
            '  display: inline-flex; align-items: center; justify-content: center;',
            '  vertical-align: middle; margin-left: 0.35rem; cursor: grab; color: #c9a962;',
            '  line-height: 1; flex-shrink: 0;',
            '}',
            'table.sp-col-reorder-table thead th .sp-group-drag .feather,',
            'table.sp-col-reorder-table thead th .sp-metric-drag .feather { width: 0.95rem; height: 0.95rem; }',
            'table.sp-col-reorder-table thead th .sp-group-drag:active,',
            'table.sp-col-reorder-table thead th .sp-metric-drag:active { cursor: grabbing; }',
            'table.sp-col-reorder-table thead th.sp-group-top { position: relative !important; }',
            'table.sp-col-reorder-table thead tr:nth-child(2) th { position: relative !important; }',
            '.sp-sortable-ghost { opacity: 0.45; }',
            '.sp-sortable-chosen { opacity: 0.9; }'
        ].join('\n');
        document.head.appendChild(s);
    }

    function loadState(key) {
        try {
            var raw = localStorage.getItem(key);
            if (!raw) return null;
            var o = JSON.parse(raw);
            return o && typeof o === 'object' ? o : null;
        } catch (e) {
            return null;
        }
    }

    function saveState(key, state) {
        try {
            localStorage.setItem(key, JSON.stringify(state));
        } catch (e) {}
    }

    function getGroupOrderRow1(row1) {
        var out = [];
        row1.querySelectorAll('th[data-sp-group]').forEach(function (th) {
            out.push(th.getAttribute('data-sp-group'));
        });
        return out;
    }

    function getMetricOrderForGroup(row2, groupSlug) {
        var out = [];
        row2.querySelectorAll('th[data-sp-group="' + groupSlug + '"]').forEach(function (th) {
            out.push(th.getAttribute('data-sp-metric'));
        });
        return out;
    }

    function normalizeGroupOrder(saved, defaults) {
        if (!saved || !saved.length) return defaults.slice();
        var set = {};
        defaults.forEach(function (g) { set[g] = true; });
        var out = [];
        saved.forEach(function (g) {
            if (set[g]) {
                out.push(g);
                delete set[g];
            }
        });
        defaults.forEach(function (g) {
            if (set[g]) out.push(g);
        });
        return out;
    }

    function normalizeMetricOrder(saved, defaults) {
        return normalizeGroupOrder(saved, defaults);
    }

    function reflowRow2AndBody(table, groupOrder, metricOrder, schemeMetricOrder) {
        var thead = table.tHead;
        if (!thead || thead.rows.length < 2) return;
        var row2 = thead.rows[1];
        var tbody = table.tBodies[0];
        if (!tbody) return;

        var orderedTh = [];
        groupOrder.forEach(function (g) {
            var ms = g === '_scheme' ? schemeMetricOrder : metricOrder;
            ms.forEach(function (m) {
                var th = row2.querySelector('th[data-sp-group="' + g + '"][data-sp-metric="' + m + '"]');
                if (th) orderedTh.push(th);
            });
        });
        orderedTh.forEach(function (th) {
            row2.appendChild(th);
        });

        tbody.querySelectorAll('tr').forEach(function (tr) {
            var nameTd = tr.querySelector('td[data-sp-fixed="1"][data-sp-role="name"]');
            var billsTd = tr.querySelector('td[data-sp-fixed="1"][data-sp-role="bills"]');
            if (!nameTd || !billsTd) return;
            var orderedTd = [];
            groupOrder.forEach(function (g) {
                var ms = g === '_scheme' ? schemeMetricOrder : metricOrder;
                ms.forEach(function (m) {
                    var td = tr.querySelector('td[data-sp-group="' + g + '"][data-sp-metric="' + m + '"]');
                    if (td) orderedTd.push(td);
                });
            });
            tr.appendChild(nameTd);
            tr.appendChild(billsTd);
            orderedTd.forEach(function (td) {
                tr.appendChild(td);
            });
        });
    }

    function readDefaults(table) {
        var dg = table.getAttribute('data-sp-default-groups');
        var dm = table.getAttribute('data-sp-default-metrics');
        var ds = table.getAttribute('data-sp-default-scheme-metrics');
        var groups = dg ? JSON.parse(dg) : [];
        var metrics = dm ? JSON.parse(dm) : [];
        var scheme = ds ? JSON.parse(ds) : [];
        return { groups: groups, metrics: metrics, scheme: scheme };
    }

    function firstProductSlug(groupOrder) {
        for (var i = 0; i < groupOrder.length; i++) {
            if (groupOrder[i] !== '_scheme') return groupOrder[i];
        }
        return null;
    }

    /**
     * @param {string|HTMLElement} selector
     * @param {{ storageKey: string }} opts
     */
    function init(selector, opts) {
        opts = opts || {};
        var table = typeof selector === 'string' ? document.querySelector(selector) : selector;
        if (!table || table.tagName !== 'TABLE' || typeof Sortable === 'undefined') return;

        var defaults = readDefaults(table);
        if (!defaults.groups.length) return;

        injectCss();
        table.classList.add('sp-col-reorder-table');

        var storageKey = opts.storageKey || 'auragold_sp_perf_columns';
        var saved = loadState(storageKey);
        var groupOrder = normalizeGroupOrder(saved && saved.groups, defaults.groups);
        var metricOrder = normalizeMetricOrder(saved && saved.metrics, defaults.metrics);
        var schemeMetricOrder = normalizeMetricOrder(saved && saved.schemeMetrics, defaults.scheme);

        reflowRow2AndBody(table, groupOrder, metricOrder, schemeMetricOrder);

        var row1 = table.tHead.rows[0];
        var row2 = table.tHead.rows[1];

        function persist() {
            var go = getGroupOrderRow1(row1);
            var prod = firstProductSlug(go) || defaults.groups[0];
            saveState(storageKey, {
                groups: go,
                metrics: getMetricOrderForGroup(row2, prod),
                schemeMetrics: getMetricOrderForGroup(row2, '_scheme')
            });
        }

        var lastGroupOrder = getGroupOrderRow1(row1).slice();
        if (row1._spGroupSortable) {
            row1._spGroupSortable.destroy();
            row1._spGroupSortable = null;
        }
        row1._spGroupSortable = Sortable.create(row1, {
            animation: 150,
            handle: '.sp-group-drag',
            draggable: 'th[data-sp-group]',
            filter: '[data-sp-fixed]',
            preventOnFilter: false,
            ghostClass: 'sp-sortable-ghost',
            chosenClass: 'sp-sortable-chosen',
            onEnd: function () {
                var order = getGroupOrderRow1(row1);
                var ok = order.length === lastGroupOrder.length;
                if (ok) {
                    var need = {};
                    lastGroupOrder.forEach(function (g) { need[g] = 0; });
                    order.forEach(function (g) {
                        if (Object.prototype.hasOwnProperty.call(need, g)) need[g]++;
                    });
                    ok = lastGroupOrder.every(function (g) { return need[g] === 1; });
                }
                if (!ok) {
                    lastGroupOrder.forEach(function (g) {
                        var th = row1.querySelector('th[data-sp-group="' + g + '"]');
                        if (th) row1.appendChild(th);
                    });
                    var prod0 = firstProductSlug(lastGroupOrder) || defaults.groups[0];
                    reflowRow2AndBody(
                        table,
                        lastGroupOrder,
                        getMetricOrderForGroup(row2, prod0),
                        getMetricOrderForGroup(row2, '_scheme')
                    );
                    return;
                }
                var prod = firstProductSlug(order) || defaults.groups[0];
                reflowRow2AndBody(
                    table,
                    order,
                    getMetricOrderForGroup(row2, prod),
                    getMetricOrderForGroup(row2, '_scheme')
                );
                lastGroupOrder = order.slice();
                persist();
            }
        });

        var prodInit = firstProductSlug(groupOrder) || defaults.groups[0];
        var lastMetricSnapshot = JSON.stringify({
            m: getMetricOrderForGroup(row2, prodInit),
            s: getMetricOrderForGroup(row2, '_scheme')
        });
        if (row2._spMetricSortable) {
            row2._spMetricSortable.destroy();
            row2._spMetricSortable = null;
        }
        row2._spMetricSortable = Sortable.create(row2, {
            animation: 150,
            handle: '.sp-metric-drag',
            draggable: 'th[data-sp-group][data-sp-metric]',
            ghostClass: 'sp-sortable-ghost',
            chosenClass: 'sp-sortable-chosen',
            onMove: function (evt) {
                var dg = evt.dragged.getAttribute('data-sp-group');
                var rel = evt.related;
                if (!rel || rel.tagName !== 'TH') return false;
                var rg = rel.getAttribute('data-sp-group');
                return dg === rg;
            },
            onEnd: function () {
                var go = getGroupOrderRow1(row1);
                var prod = firstProductSlug(go) || defaults.groups[0];
                var newM = getMetricOrderForGroup(row2, prod);
                var newS = getMetricOrderForGroup(row2, '_scheme');
                var snap = JSON.stringify({ m: newM, s: newS });
                if (snap !== lastMetricSnapshot) {
                    reflowRow2AndBody(table, go, newM, newS);
                    lastMetricSnapshot = JSON.stringify({
                        m: getMetricOrderForGroup(row2, firstProductSlug(go) || defaults.groups[0]),
                        s: getMetricOrderForGroup(row2, '_scheme')
                    });
                    persist();
                }
            }
        });
    }

    global.AuragoldSalespersonColReorder = { init: init };
})(typeof window !== 'undefined' ? window : this);
