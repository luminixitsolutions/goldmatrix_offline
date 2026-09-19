/**
 * Product Characteristics table: column drag-and-drop for Add Product modal (#productCreationModal).
 * Same group/sub-column behaviour as product-opening.php.
 */
(function ($) {
    'use strict';
    if (!$) return;

    var MODAL_ROOT = '#productCreationModal';
    var LS_KEY = 'productCreationModalPcFlatColumnOrder';
    var initialized = false;

    var PC_COL_GROUPS = {
        'purity-group': ['purity-sale', 'purity-purchase'],
        'wastage-group': ['wastage-sale', 'wastage-purchase'],
        'opening': ['opening-weight', 'opening-purity', 'opening-qty', 'opening-finalwt', 'opening-rate', 'opening-value'],
        'barcode-group': ['barcode-digits', 'barcode-prefix', 'barcode'],
        'styles': ['cut', 'shape', 'color', 'clarity', 'sieve', 'size', 'stylecode']
    };

    var PC_SUB_TO_GROUP = {};
    Object.keys(PC_COL_GROUPS).forEach(function (gk) {
        PC_COL_GROUPS[gk].forEach(function (sub) {
            PC_SUB_TO_GROUP[sub] = gk;
        });
    });

    function $root() {
        return $(MODAL_ROOT);
    }

    function pcColumnKeysFromHeaderTh($th) {
        var colspan = parseInt($th.attr('colspan'), 10) || 1;
        var rowspan = parseInt($th.attr('rowspan'), 10) || 1;
        var col = $th.data('col');
        if (!col) return [];
        var key = String(col);
        if (rowspan === 2) return [key];
        if (colspan > 1) {
            var g = PC_COL_GROUPS[key];
            return g ? g.slice() : [];
        }
        return [];
    }

    function getPcTableColumnOrderFromDom() {
        var order = [];
        $root().find('#headerRow1').children('th').each(function () {
            var $th = $(this);
            var rowspan = parseInt($th.attr('rowspan'), 10) || 1;
            var col = $th.data('col');
            if (!col) return;
            var key = String(col);
            if (rowspan === 2) {
                order.push(key);
            } else if ((parseInt($th.attr('colspan'), 10) || 1) > 1) {
                var g = PC_COL_GROUPS[key];
                if (g) g.forEach(function (k) { order.push(k); });
            }
        });
        return order;
    }

    function rebuildPcHeaderRow2() {
        var order = getPcTableColumnOrderFromDom();
        var $row2 = $root().find('#headerRow2');
        var map = {};
        $row2.children('th').each(function () {
            var k = $(this).data('col');
            if (k) map[String(k)] = this;
        });
        $row2.empty();
        order.forEach(function (k) {
            if (map[k]) $row2.append(map[k]);
        });
    }

    function applyPcBodyColumnOrder(order) {
        $root().find('.pc-table tbody tr').each(function () {
            var $row = $(this);
            var cellMap = {};
            $row.find('td[data-col]').each(function () {
                cellMap[String($(this).data('col'))] = this;
            });
            order.forEach(function (k) {
                if (cellMap[k]) $row.append(cellMap[k]);
            });
        });
    }

    function repairRow2GroupPairsAfterSort() {
        var $row2 = $root().find('#headerRow2');
        Object.keys(PC_COL_GROUPS).forEach(function (gk) {
            var keys = PC_COL_GROUPS[gk];
            if (!keys || keys.length < 2) return;
            var $lead = $row2.find('th[data-col="' + keys[0] + '"]');
            if (!$lead.length) return;
            keys.slice(1).forEach(function (k) {
                var $n = $row2.find('th[data-col="' + k + '"]');
                if ($n.length && $n[0] !== $lead[0].nextElementSibling) {
                    $n.insertAfter($lead);
                }
                $lead = $n;
            });
        });
    }

    function collapsedRow2GroupKeys() {
        var groups = [];
        var lastG = null;
        $root().find('#headerRow2').children('th').each(function () {
            var k = $(this).data('col');
            if (!k) return;
            var g = PC_SUB_TO_GROUP[String(k)];
            if (!g) return;
            if (g !== lastG) {
                groups.push(g);
                lastG = g;
            }
        });
        return groups;
    }

    function syncRow1GroupHeadersFromRow2Collapsed() {
        var collapsed = collapsedRow2GroupKeys();
        if (!collapsed.length) return;
        var $row1 = $root().find('#headerRow1');
        var slots = 0;
        $row1.children('th').each(function () {
            var colspan = parseInt($(this).attr('colspan'), 10) || 1;
            var rowspan = parseInt($(this).attr('rowspan'), 10) || 1;
            var c = $(this).data('col');
            if (rowspan !== 2 && colspan > 1 && PC_COL_GROUPS[String(c)]) slots++;
        });
        if (collapsed.length !== slots) return;
        var groupMap = {};
        $row1.children('th').each(function () {
            var $th = $(this);
            var c = $th.data('col');
            var colspan = parseInt($th.attr('colspan'), 10) || 1;
            var rowspan = parseInt($th.attr('rowspan'), 10) || 1;
            if (rowspan !== 2 && colspan > 1 && PC_COL_GROUPS[String(c)]) {
                groupMap[String(c)] = this;
            }
        });
        var gi = 0;
        var children = $row1.children('th').toArray();
        var newOrder = [];
        children.forEach(function (th) {
            var $th = $(th);
            var c = $th.data('col');
            var colspan = parseInt($th.attr('colspan'), 10) || 1;
            var rowspan = parseInt($th.attr('rowspan'), 10) || 1;
            if (rowspan === 2) {
                newOrder.push(th);
                return;
            }
            if (colspan > 1 && PC_COL_GROUPS[String(c)]) {
                if (gi < collapsed.length) {
                    var gk = collapsed[gi++];
                    var el = groupMap[gk];
                    if (el) newOrder.push(el);
                }
            } else {
                newOrder.push(th);
            }
        });
        newOrder.forEach(function (el) {
            $row1.append(el);
        });
    }

    function mergePcColumnOrder(savedFlat, fallbackFlat) {
        var seen = new Set();
        var out = [];
        (savedFlat || []).forEach(function (k) {
            if (k && !seen.has(k)) { seen.add(k); out.push(k); }
        });
        (fallbackFlat || []).forEach(function (k) {
            if (k && !seen.has(k)) { seen.add(k); out.push(k); }
        });
        return out;
    }

    function applyColumnOrderFromFlatKeys(flatOrder, skipSave) {
        var $headerRow = $root().find('#headerRow1');
        var merged = mergePcColumnOrder(flatOrder, getPcTableColumnOrderFromDom());
        var ths = $headerRow.children('th').get();
        function rank(th) {
            var keys = pcColumnKeysFromHeaderTh($(th));
            var minIdx = Infinity;
            keys.forEach(function (k) {
                var i = merged.indexOf(k);
                if (i !== -1 && i < minIdx) minIdx = i;
            });
            return minIdx === Infinity ? 999999 : minIdx;
        }
        var wrapped = ths.map(function (th, idx) { return { th: th, idx: idx }; });
        wrapped.sort(function (a, b) {
            var dr = rank(a.th) - rank(b.th);
            if (dr !== 0) return dr;
            return a.idx - b.idx;
        });
        wrapped.forEach(function (x) { $headerRow.append(x.th); });
        updateColumnOrder(!!skipSave);
    }

    function updateColumnOrder(skipSave) {
        rebuildPcHeaderRow2();
        var order = getPcTableColumnOrderFromDom();
        applyPcBodyColumnOrder(order);
        if (!skipSave) {
            try {
                localStorage.setItem(LS_KEY, JSON.stringify(order));
            } catch (e) { /* ignore */ }
        }
    }

    function onPcHeaderRow2SortEnd() {
        repairRow2GroupPairsAfterSort();
        syncRow1GroupHeadersFromRow2Collapsed();
        updateColumnOrder(false);
    }

    function resetToCorrectColumnOrder() {
        rebuildPcHeaderRow2();
        applyPcBodyColumnOrder(getPcTableColumnOrderFromDom());
    }

    function loadSavedColumnOrder() {
        try {
            var raw = localStorage.getItem(LS_KEY);
            if (!raw) return;
            var keys = JSON.parse(raw);
            if (Array.isArray(keys) && keys.length) {
                applyColumnOrderFromFlatKeys(keys, true);
            }
        } catch (e) { /* ignore */ }
    }

    function initPcColumnDragHandles() {
        $root().find('#headerRow1 th.pc-col-drag').each(function () {
            var $th = $(this);
            if ($th.find('.pc-col-drag-handle-main').length) return;
            $th.prepend('<span class="pc-col-drag-handle pc-col-drag-handle-main" title="Drag to reorder column"><i class="feather icon-move"></i></span>');
        });
        $root().find('#headerRow2 th').each(function () {
            var $th = $(this);
            var col = $th.data('col');
            if (!col || !PC_SUB_TO_GROUP[String(col)]) return;
            if ($th.find('.pc-col-drag-handle-sub').length) return;
            $th.prepend('<span class="pc-col-drag-handle pc-col-drag-handle-sub" title="Drag to move this column group"><i class="feather icon-move"></i></span>');
        });
        if (window.feather && typeof window.feather.replace === 'function') {
            try { window.feather.replace(); } catch (err) { /* ignore */ }
        }
    }

    function initProductCreationModalColumnDrag() {
        if (initialized) return true;
        if (typeof Sortable === 'undefined') return false;
        if (!$root().length) return false;

        var headerRow1 = $root().find('#headerRow1')[0];
        var headerRow2 = $root().find('#headerRow2')[0];
        var pcTbodyEl = $root().find('.pc-table tbody')[0];
        if (!headerRow1 || !headerRow2 || !pcTbodyEl) return false;

        initPcColumnDragHandles();
        resetToCorrectColumnOrder();
        loadSavedColumnOrder();

        new Sortable(headerRow1, {
            animation: 150,
            draggable: 'th.pc-col-drag',
            handle: '.pc-col-drag-handle-main',
            filter: '.pc-col-no-drag',
            preventOnFilter: true,
            forceFallback: true,
            fallbackOnBody: true,
            fallbackTolerance: 5,
            onStart: function (evt) {
                evt.item.classList.add('dragging');
                pcColumnKeysFromHeaderTh($(evt.item)).forEach(function (k) {
                    $root().find('.pc-table tbody td[data-col="' + k + '"]').addClass('dragging-cell');
                });
            },
            onEnd: function (evt) {
                evt.item.classList.remove('dragging');
                $root().find('.pc-table tbody td').removeClass('dragging-cell');
                updateColumnOrder(false);
            }
        });

        new Sortable(headerRow2, {
            animation: 150,
            draggable: 'th',
            handle: '.pc-col-drag-handle-sub',
            forceFallback: true,
            fallbackOnBody: true,
            fallbackTolerance: 5,
            onStart: function (evt) {
                evt.item.classList.add('dragging');
                var k = $(evt.item).data('col');
                var gk = k ? PC_SUB_TO_GROUP[String(k)] : null;
                var keys = (gk && PC_COL_GROUPS[gk]) ? PC_COL_GROUPS[gk].slice() : (k ? [String(k)] : []);
                keys.forEach(function (colKey) {
                    $root().find('.pc-table tbody td[data-col="' + colKey + '"]').addClass('dragging-cell');
                });
            },
            onEnd: function (evt) {
                evt.item.classList.remove('dragging');
                $root().find('.pc-table tbody td').removeClass('dragging-cell');
                onPcHeaderRow2SortEnd();
            }
        });

        new Sortable(pcTbodyEl, {
            animation: 150,
            handle: '.pc-row-drag-handle',
            ghostClass: 'pc-row-sortable-ghost'
        });

        initialized = true;
        return true;
    }

    window.initProductCreationModalColumnDrag = initProductCreationModalColumnDrag;
    window.resetProductCreationModalColumnOrder = resetToCorrectColumnOrder;

    $(document).ready(function () {
        var attempts = 0;
        var timer = setInterval(function () {
            if (initProductCreationModalColumnDrag() || ++attempts > 60) {
                clearInterval(timer);
            }
        }, 100);

        $(document).on('shown.bs.modal', MODAL_ROOT, function () {
            if (!initialized) {
                initProductCreationModalColumnDrag();
            } else {
                resetToCorrectColumnOrder();
            }
            initPcColumnDragHandles();
        });
    });
})(window.jQuery);
