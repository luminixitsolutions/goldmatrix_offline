/**
 * Cheque Entry list — column show/hide, drag reorder, resize, export helpers.
 */
(function (window, $) {
    'use strict';

    var CE_PAGE_NAME = 'cheque-entry';
    var CE_TAB_KEY = 'cheque_list';
    var CE_TABLE_ID = 'chequeEntryTable';
    var CE_STORAGE_KEY = 'auragold_cheque_entry_columns_cheque-entry';

    var ceColumnDefinitions = [
        { key: 'sr-no', label: 'Sr.No' },
        { key: 'pdc-no', label: 'PDC No.' },
        { key: 'account-no', label: 'Account No' },
        { key: 'account-ledger', label: 'Account Ledger' },
        { key: 'bank-name', label: 'Bank Name.' },
        { key: 'cheque-no', label: 'Cheque No.' },
        { key: 'cheque-date', label: 'Cheque Date' },
        { key: 'pay-date', label: 'Pay Dt.' },
        { key: 'amount', label: 'Amount' },
        { key: 'branch-name', label: 'Branch Name' },
        { key: 'status', label: 'Status' },
        { key: 'bounced-cleared-date', label: 'Bounced/Cleared Date' },
        { key: 'against-voucher-no', label: 'Against Voucher No.' },
        { key: 'against-voucher-type', label: 'Against Voucher Type' },
        { key: 'nsf-fees', label: 'NSF Fees' },
        { key: 'recoverable', label: 'Recoverable' },
        { key: 'invoice-date', label: 'Invoice Date' },
        { key: 'reference-voucher-type', label: 'Refrence Voucher Type' },
        { key: 'ref-invoice-no', label: 'Ref Invoice No.' },
        { key: 'pdc-voucher-type', label: 'PDC VoucherType' },
        { key: 'actions', label: 'Action' }
    ];

    var CE_DEFAULT_WIDTHS = {
        'sr-no': 56,
        'pdc-no': 100,
        'account-no': 110,
        'account-ledger': 160,
        'bank-name': 130,
        'cheque-no': 110,
        'cheque-date': 108,
        'pay-date': 96,
        'amount': 100,
        'branch-name': 120,
        'status': 96,
        'bounced-cleared-date': 140,
        'against-voucher-no': 140,
        'against-voucher-type': 150,
        'nsf-fees': 90,
        'recoverable': 96,
        'invoice-date': 108,
        'reference-voucher-type': 150,
        'ref-invoice-no': 120,
        'pdc-voucher-type': 130,
        'actions': 88
    };

    var ceSortable = null;
    var ceSaveOrderTimer = null;
    var ceSaveWidthsTimer = null;

    function ceDefaultKeys() {
        return ceColumnDefinitions.map(function (c) { return c.key; });
    }

    function ceMergeOrderWithDefaults(savedOrder, defaultKeys) {
        var seen = Object.create(null);
        var out = [];
        (savedOrder || []).forEach(function (k) {
            if (!k || defaultKeys.indexOf(k) === -1 || seen[k]) return;
            seen[k] = true;
            out.push(k);
        });
        defaultKeys.forEach(function (k) {
            if (!seen[k]) out.push(k);
        });
        var ai = out.indexOf('actions');
        if (ai >= 0 && ai !== out.length - 1) {
            out.splice(ai, 1);
            out.push('actions');
        }
        return out;
    }

    function ceGetColumnOrderFromThead(tr) {
        if (!tr) return [];
        var keys = [];
        tr.querySelectorAll('th[data-column]').forEach(function (th) {
            var k = th.getAttribute('data-column');
            if (k) keys.push(k);
        });
        return keys;
    }

    function ceReorderRowCells(row, orderedKeys) {
        if (!row || !orderedKeys || !orderedKeys.length) return;
        var map = Object.create(null);
        row.querySelectorAll('td[data-column]').forEach(function (td) {
            var k = td.getAttribute('data-column');
            if (k) map[k] = td;
        });
        orderedKeys.forEach(function (k) {
            if (map[k]) row.appendChild(map[k]);
        });
    }

    function ceReorderTableColumns(tableId, orderedKeys) {
        var table = document.getElementById(tableId);
        if (!table || !orderedKeys || !orderedKeys.length) return;
        var thr = table.querySelector('thead tr');
        if (!thr) return;
        var hmap = Object.create(null);
        thr.querySelectorAll('th[data-column]').forEach(function (th) {
            var k = th.getAttribute('data-column');
            if (k) hmap[k] = th;
        });
        orderedKeys.forEach(function (k) {
            if (hmap[k]) thr.appendChild(hmap[k]);
        });
        var tbody = table.querySelector('tbody');
        if (tbody) {
            tbody.querySelectorAll('tr').forEach(function (row) {
                if (row.classList.contains('ce-empty-row')) return;
                ceReorderRowCells(row, orderedKeys);
            });
        }
    }

    function ceSaveColumnOrderToServer(orderedKeys) {
        if (!orderedKeys || !orderedKeys.length) return;
        try {
            localStorage.setItem('pv-col-order:' + CE_PAGE_NAME + ':' + CE_TAB_KEY, JSON.stringify(orderedKeys));
        } catch (e1) {}
        if (ceSaveOrderTimer) clearTimeout(ceSaveOrderTimer);
        ceSaveOrderTimer = setTimeout(function () {
            $.ajax({
                url: 'ajax/save-product-modal-column-preferences.php',
                method: 'POST',
                data: {
                    page_name: CE_PAGE_NAME,
                    tab_key: CE_TAB_KEY,
                    order_keys: JSON.stringify(orderedKeys)
                },
                dataType: 'json'
            });
        }, 400);
    }

    function ceLoadColumnOrder(callback) {
        var fallback = function () {
            try {
                var raw = localStorage.getItem('pv-col-order:' + CE_PAGE_NAME + ':' + CE_TAB_KEY);
                if (raw) {
                    var o = JSON.parse(raw);
                    if (Array.isArray(o) && o.length) {
                        callback(ceMergeOrderWithDefaults(o, ceDefaultKeys()));
                        return;
                    }
                }
            } catch (e2) {}
            callback(ceDefaultKeys());
        };
        $.ajax({
            url: 'ajax/get-column-preferences.php',
            method: 'POST',
            data: { page_name: CE_PAGE_NAME, tab_key: CE_TAB_KEY },
            dataType: 'json',
            success: function (res) {
                if (res && res.status === 'success' && res.preferences && res.preferences.length) {
                    var prefs = res.preferences.slice().sort(function (a, b) {
                        return (parseInt(a.column_order, 10) || 0) - (parseInt(b.column_order, 10) || 0);
                    });
                    var keys = prefs.map(function (p) { return p.column_key; }).filter(Boolean);
                    if (keys.length) {
                        callback(ceMergeOrderWithDefaults(keys, ceDefaultKeys()));
                        return;
                    }
                }
                fallback();
            },
            error: function () { fallback(); }
        });
    }

    function ceWidthsStorageKey() {
        return 'pv-col-widths:' + CE_PAGE_NAME + ':' + CE_TAB_KEY;
    }

    function ceLoadColumnWidths() {
        try {
            var raw = localStorage.getItem(ceWidthsStorageKey());
            if (!raw) return null;
            var o = JSON.parse(raw);
            return o && typeof o === 'object' ? o : null;
        } catch (eW) {
            return null;
        }
    }

    function ceSaveColumnWidths(widths) {
        try {
            localStorage.setItem(ceWidthsStorageKey(), JSON.stringify(widths));
        } catch (eS) {}
    }

    function ceMergeSavedWidthsWithDefaults(saved) {
        var out = {};
        var corrected = false;
        Object.keys(CE_DEFAULT_WIDTHS).forEach(function (k) {
            var defW = CE_DEFAULT_WIDTHS[k];
            var sw = saved && saved[k] != null ? parseInt(saved[k], 10) : 0;
            if (sw >= 60 && sw <= 2000) {
                out[k] = sw;
            } else {
                out[k] = defW;
                if (saved && saved[k] != null) corrected = true;
            }
        });
        return { widths: out, corrected: corrected };
    }

    function ceSetColumnWidthPx(table, colKey, px) {
        if (!table || !colKey) return;
        var n;
        if (colKey === 'actions') {
            n = Math.max(72, Math.min(160, Math.round(px)));
        } else {
            n = Math.max(48, Math.min(900, Math.round(px)));
        }
        table.querySelectorAll('th[data-column="' + colKey + '"], td[data-column="' + colKey + '"]').forEach(function (cell) {
            cell.style.width = n + 'px';
            cell.style.minWidth = n + 'px';
            cell.style.maxWidth = n + 'px';
        });
    }

    function ceApplyColumnWidths(tableId, widths) {
        var table = document.getElementById(tableId);
        if (!table || !widths || typeof widths !== 'object') return;
        Object.keys(widths).forEach(function (key) {
            var px = parseInt(widths[key], 10);
            if (key === 'actions') {
                if (px >= 60) ceSetColumnWidthPx(table, key, px);
                return;
            }
            if (!px || px < 48) return;
            ceSetColumnWidthPx(table, key, px);
        });
    }

    function ceCollectWidthsFromTable(tableId) {
        var table = document.getElementById(tableId);
        var out = {};
        if (!table) return out;
        table.querySelectorAll('thead th[data-column]').forEach(function (th) {
            var k = th.getAttribute('data-column');
            if (!k) return;
            var w = th.offsetWidth;
            if (k === 'actions') {
                if (w >= 60) out[k] = w;
            } else if (w >= 48) {
                out[k] = w;
            }
        });
        return out;
    }

    function ceDebouncedSaveColumnWidths(tableId) {
        if (ceSaveWidthsTimer) clearTimeout(ceSaveWidthsTimer);
        ceSaveWidthsTimer = setTimeout(function () {
            ceSaveColumnWidths(ceCollectWidthsFromTable(tableId));
        }, 350);
    }

    function ceWrapThLabelText(tableId) {
        var table = document.getElementById(tableId);
        if (!table) return;
        table.querySelectorAll('thead th[data-column]').forEach(function (th) {
            if (th.querySelector('.pv-th-inner')) return;
            var drag = th.querySelector('.pv-col-drag-h');
            var textNodes = [];
            var child = th.firstChild;
            while (child) {
                var next = child.nextSibling;
                if (child !== drag && child.nodeType === 3) {
                    textNodes.push(child);
                } else if (child !== drag && child.nodeType === 1 && !child.classList.contains('pv-col-drag-h') && !child.classList.contains('pv-col-resizer')) {
                    textNodes.push(child);
                }
                child = next;
            }
            var inner = document.createElement('span');
            inner.className = 'pv-th-inner';
            var txt = document.createElement('span');
            txt.className = 'pv-th-text';
            textNodes.forEach(function (n) {
                txt.appendChild(n);
            });
            if (drag) inner.appendChild(drag);
            inner.appendChild(txt);
            th.insertBefore(inner, th.firstChild);
        });
    }

    function ceInstallColumnResizers(tableId) {
        var table = document.getElementById(tableId);
        if (!table) return;
        table.querySelectorAll('thead th[data-column]').forEach(function (th) {
            var col = th.getAttribute('data-column');
            if (!col || th.querySelector('.pv-col-resizer')) return;
            var rz = document.createElement('span');
            rz.className = 'pv-col-resizer';
            rz.title = 'Drag to resize';
            th.appendChild(rz);
            rz.addEventListener('mousedown', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var startX = e.pageX;
                var startW = th.offsetWidth;
                function onMove(e2) {
                    ceSetColumnWidthPx(table, col, startW + (e2.pageX - startX));
                }
                function onUp() {
                    document.removeEventListener('mousemove', onMove);
                    document.removeEventListener('mouseup', onUp);
                    document.body.style.cursor = '';
                    ceDebouncedSaveColumnWidths(tableId);
                }
                document.body.style.cursor = 'col-resize';
                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onUp);
            });
        });
    }

    function ceInitColumnDrag() {
        var tr = document.querySelector('#' + CE_TABLE_ID + ' thead tr');
        if (!tr || typeof Sortable === 'undefined') return;
        if (ceSortable) {
            try { ceSortable.destroy(); } catch (eD) {}
            ceSortable = null;
        }
        ceSortable = Sortable.create(tr, {
            animation: 150,
            handle: '.pv-col-drag-h',
            draggable: 'th',
            filter: '[data-column="actions"]',
            preventOnFilter: false,
            onEnd: function () {
                var keys = ceGetColumnOrderFromThead(tr);
                document.querySelectorAll('#chequeEntryBody tr:not(.ce-empty-row)').forEach(function (row) {
                    ceReorderRowCells(row, keys);
                });
                ceApplyColumnVisibility();
                ceSaveColumnOrderToServer(keys);
            }
        });
    }

    function getColumnPreferences() {
        var saved = localStorage.getItem(CE_STORAGE_KEY);
        if (saved) {
            try {
                return JSON.parse(saved);
            } catch (e) {}
        }
        var defaults = {};
        ceColumnDefinitions.forEach(function (col) {
            defaults[col.key] = true;
        });
        return defaults;
    }

    function saveColumnPreferences(prefs) {
        localStorage.setItem(CE_STORAGE_KEY, JSON.stringify(prefs));
    }

    function openColumnsModal() {
        renderColumnsList();
        var modal = document.getElementById('ceColumnsModal');
        if (modal) {
            modal.style.display = 'flex';
            modal.classList.add('active');
        }
    }

    function closeColumnsModal() {
        var modal = document.getElementById('ceColumnsModal');
        if (modal) {
            modal.style.display = 'none';
            modal.classList.remove('active');
        }
    }

    function refreshColumns() {
        var defaults = {};
        ceColumnDefinitions.forEach(function (col) {
            defaults[col.key] = true;
        });
        saveColumnPreferences(defaults);
        ceApplyColumnVisibility();
        renderColumnsList();
    }

    function renderColumnsList() {
        var columnsList = document.getElementById('ceColumnsList');
        if (!columnsList) return;
        var columnPrefs = getColumnPreferences();
        columnsList.innerHTML = '';
        ceColumnDefinitions.forEach(function (col) {
            if (col.key === 'actions') return;
            var item = document.createElement('div');
            item.className = 'column-item';
            var isChecked = columnPrefs[col.key] !== false;
            item.innerHTML =
                '<input type="checkbox" id="ce_col_' + col.key + '" ' + (isChecked ? 'checked' : '') + '>'
                + '<label for="ce_col_' + col.key + '">' + col.label + '</label>';
            var cb = item.querySelector('input');
            cb.addEventListener('change', function () {
                toggleColumn(col.key, cb.checked);
            });
            columnsList.appendChild(item);
        });
    }

    function filterColumnsList() {
        var searchEl = document.getElementById('ceColumnSearch');
        var search = searchEl ? searchEl.value.toLowerCase() : '';
        document.querySelectorAll('#ceColumnsList .column-item').forEach(function (item) {
            var label = item.querySelector('label');
            var text = label ? label.textContent.toLowerCase() : '';
            item.style.display = text.indexOf(search) !== -1 ? 'flex' : 'none';
        });
    }

    function toggleColumn(key, visible) {
        var columnPrefs = getColumnPreferences();
        columnPrefs[key] = visible;
        saveColumnPreferences(columnPrefs);
        ceApplyColumnVisibility();
    }

    function ceApplyColumnVisibility() {
        var columnPrefs = getColumnPreferences();
        ceColumnDefinitions.forEach(function (col) {
            var isVisible = columnPrefs[col.key] !== false;
            var selector = '[data-column="' + col.key + '"]';
            document.querySelectorAll('#' + CE_TABLE_ID + ' th' + selector + ', #' + CE_TABLE_ID + ' td' + selector).forEach(function (el) {
                el.style.display = isVisible ? '' : 'none';
            });
        });
        var emptyRow = document.querySelector('#chequeEntryBody .ce-empty-row');
        if (emptyRow) {
            var visibleColumns = ceColumnDefinitions.filter(function (col) {
                return columnPrefs[col.key] !== false;
            }).length;
            var td = emptyRow.querySelector('td');
            if (td) td.setAttribute('colspan', visibleColumns);
        }
    }

    function getExportColumns() {
        var columnPrefs = getColumnPreferences();
        var tr = document.querySelector('#' + CE_TABLE_ID + ' thead tr');
        var order = tr ? ceGetColumnOrderFromThead(tr) : ceDefaultKeys();
        return order.filter(function (k) {
            return k !== 'actions' && columnPrefs[k] !== false;
        });
    }

    function refreshAfterDataLoad() {
        var tr = document.querySelector('#' + CE_TABLE_ID + ' thead tr');
        if (!tr) return;
        var keys = ceGetColumnOrderFromThead(tr);
        document.querySelectorAll('#chequeEntryBody tr:not(.ce-empty-row)').forEach(function (row) {
            ceReorderRowCells(row, keys);
        });
        ceApplyColumnVisibility();
    }

    function initGrid() {
        ceLoadColumnOrder(function (keys) {
            ceReorderTableColumns(CE_TABLE_ID, keys);
            ceWrapThLabelText(CE_TABLE_ID);
            var pwMerged = ceMergeSavedWidthsWithDefaults(ceLoadColumnWidths());
            ceApplyColumnWidths(CE_TABLE_ID, pwMerged.widths);
            if (pwMerged.corrected) {
                ceSaveColumnWidths(pwMerged.widths);
            }
            ceInstallColumnResizers(CE_TABLE_ID);
            ceInitColumnDrag();
            ceApplyColumnVisibility();
        });

        document.addEventListener('click', function (e) {
            var modal = document.getElementById('ceColumnsModal');
            if (modal && e.target === modal) closeColumnsModal();
        });

        $(document).on('mouseenter', '#' + CE_TABLE_ID + ' thead th[data-column]', function () {
            var th = this;
            var k = th.getAttribute('data-column');
            if (k === 'actions') return;
            var pt = th.querySelector('.pv-th-text');
            if (pt && pt.scrollWidth > pt.clientWidth) {
                th.title = (pt.textContent || '').replace(/\s+/g, ' ').trim();
            } else {
                th.removeAttribute('title');
            }
        });
    }

    window.ChequeEntryGrid = {
        init: initGrid,
        openColumnsModal: openColumnsModal,
        closeColumnsModal: closeColumnsModal,
        refreshColumns: refreshColumns,
        filterColumnsList: filterColumnsList,
        refreshAfterDataLoad: refreshAfterDataLoad,
        getExportColumns: getExportColumns,
        getColumnPreferences: getColumnPreferences,
        getColumnOrderFromThead: ceGetColumnOrderFromThead
    };

    window.openCeColumnsModal = openColumnsModal;
    window.closeCeColumnsModal = closeColumnsModal;
    window.refreshCeColumns = refreshColumns;
    window.filterCeColumns = filterColumnsList;

})(window, jQuery);
