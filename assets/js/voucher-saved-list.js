/**
 * Saved voucher list (Contra / Journal) — pagination, column drag, export, edit, delete.
 */
(function ($) {
    'use strict';
    if (!$) return;

    var stateByRoot = {};

    function escHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function getColKey(col) {
        return col.key || col.field || '';
    }

    function getColumnOrderFromThead(tr) {
        if (!tr) return [];
        var keys = [];
        tr.querySelectorAll('th[data-column]').forEach(function (th) {
            var k = th.getAttribute('data-column');
            if (k) keys.push(k);
        });
        return keys;
    }

    function reorderRowCells(row, orderedKeys) {
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

    function reorderTableColumns(table, orderedKeys) {
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
        table.querySelectorAll('tbody tr[data-voucher-id]').forEach(function (row) {
            reorderRowCells(row, orderedKeys);
        });
    }

    function mergeOrderWithDefaults(savedOrder, defaultKeys) {
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

    function defaultColumnKeys(cfg) {
        var keys = ['sr-no'];
        (cfg.columns || []).forEach(function (col) {
            keys.push(getColKey(col));
        });
        keys.push('actions');
        return keys;
    }

    function applySavedColumnOrder(cfg) {
        var table = document.querySelector(cfg.tableSelector || '.saved-voucher-table');
        if (!table) return;
        var defaults = defaultColumnKeys(cfg);
        var storageKey = cfg.columnOrderKey || 'savedVoucherListColumnOrder';
        var saved = null;
        try {
            saved = JSON.parse(localStorage.getItem(storageKey) || 'null');
        } catch (e) {
            saved = null;
        }
        var order = mergeOrderWithDefaults(saved, defaults);
        reorderTableColumns(table, order);
    }

    function initColumnDrag(cfg, state) {
        if (typeof Sortable === 'undefined') return;
        var table = document.querySelector(cfg.tableSelector || '.saved-voucher-table');
        if (!table) return;
        var tr = table.querySelector('thead tr');
        if (!tr) return;
        if (state.sortable) {
            try { state.sortable.destroy(); } catch (e) { /* ignore */ }
            state.sortable = null;
        }
        state.sortable = Sortable.create(tr, {
            animation: 150,
            handle: '.svl-col-drag-h',
            draggable: 'th[data-column]',
            filter: 'th[data-column="actions"]',
            onEnd: function () {
                var keys = getColumnOrderFromThead(tr);
                table.querySelectorAll('tbody tr[data-voucher-id]').forEach(function (row) {
                    reorderRowCells(row, keys);
                });
                if (cfg.columnOrderKey) {
                    try {
                        localStorage.setItem(cfg.columnOrderKey, JSON.stringify(keys));
                    } catch (e2) { /* ignore */ }
                }
            }
        });
    }

    function renderRow(cfg, voucher, rowNum) {
        var cols = '<td data-column="sr-no">' + rowNum + '</td>';
        (cfg.columns || []).forEach(function (col) {
            var key = getColKey(col);
            var val = '';
            if (col.field && voucher[col.field] != null) {
                val = voucher[col.field];
            }
            if (col.format === 'amount') {
                val = parseFloat(val || 0).toFixed(2);
            }
            if (col.format === 'date' && val) {
                val = String(val).substring(0, 10);
            }
            var cls = col.align === 'right' ? ' class="text-right"' : '';
            cols += '<td data-column="' + escHtml(key) + '"' + cls + '>' + escHtml(val) + '</td>';
        });
        cols += '<td data-column="actions" class="text-center saved-voucher-actions">' +
            '<button type="button" class="btn btn-sm btn-link saved-voucher-edit-btn" data-voucher-id="' + voucher.id + '" title="Edit"><i class="feather icon-edit-2"></i></button>' +
            '<button type="button" class="btn btn-sm btn-link text-danger saved-voucher-delete-btn" data-voucher-id="' + voucher.id + '" title="Delete"><i class="feather icon-trash-2"></i></button>' +
            '</td>';
        return '<tr data-voucher-id="' + voucher.id + '">' + cols + '</tr>';
    }

    function updatePagination(cfg, state) {
        var $footer = $(cfg.root).find(cfg.paginationSelector || '.saved-voucher-pagination');
        if (!$footer.length) return;
        var totalPages = Math.max(1, Math.ceil(state.total / state.pageSize));
        if (state.page > totalPages) state.page = totalPages;
        var start = state.total === 0 ? 0 : ((state.page - 1) * state.pageSize) + 1;
        var end = Math.min(state.page * state.pageSize, state.total);
        $footer.find('.svl-page-info').text(
            state.total === 0
                ? 'No records'
                : ('Showing ' + start + '–' + end + ' of ' + state.total + ' · Page ' + state.page + ' of ' + totalPages)
        );
        $footer.find('.svl-page-prev').prop('disabled', state.page <= 1);
        $footer.find('.svl-page-next').prop('disabled', state.page >= totalPages);
        $footer.find('.svl-page-size').val(String(state.pageSize));
    }

    function loadSavedVoucherList(cfg, page) {
        var $card = $(cfg.root);
        if (!$card.length) return;
        var state = stateByRoot[cfg.root] || {};
        stateByRoot[cfg.root] = state;
        state.page = page || state.page || 1;
        state.pageSize = state.pageSize || cfg.pageSize || 10;

        var $tbody = $card.find(cfg.bodySelector || 'tbody');
        var colspan = (cfg.columns || []).length + 2;

        $.ajax({
            url: cfg.getUrl,
            method: 'GET',
            data: { page: state.page, limit: state.pageSize },
            dataType: 'json',
            success: function (response) {
                state.total = parseInt(response.total, 10) || 0;
                if (response.page) state.page = parseInt(response.page, 10) || state.page;
                updatePagination(cfg, state);

                if (response.status !== 'success' || !response.vouchers || !response.vouchers.length) {
                    $tbody.html('<tr class="no-rows"><td colspan="' + colspan + '" class="text-center text-muted py-3">No Rows To Show</td></tr>');
                    return;
                }
                var html = '';
                var startNum = ((state.page - 1) * state.pageSize) + 1;
                response.vouchers.forEach(function (v, i) {
                    html += renderRow(cfg, v, startNum + i);
                });
                $tbody.html(html);
                applySavedColumnOrder(cfg);
                if (window.feather && typeof window.feather.replace === 'function') {
                    try { window.feather.replace(); } catch (e) { /* ignore */ }
                }
            },
            error: function () {
                $tbody.html('<tr class="no-rows"><td colspan="' + colspan + '" class="text-center text-muted py-3">Could not load records</td></tr>');
            }
        });
    }

    function closeExportMenu(cfg) {
        $(cfg.root).find('.voucher-list-export-menu').removeClass('show');
    }

    window.initVoucherSavedList = function (cfg) {
        if (!cfg || !cfg.root) return;
        var state = { page: 1, pageSize: cfg.pageSize || 10, total: 0 };
        stateByRoot[cfg.root] = state;

        applySavedColumnOrder(cfg);
        initColumnDrag(cfg, state);
        loadSavedVoucherList(cfg, 1);

        var $card = $(cfg.root);

        $card.on('click', '.svl-page-prev', function (e) {
            e.preventDefault();
            if (state.page > 1) loadSavedVoucherList(cfg, state.page - 1);
        });
        $card.on('click', '.svl-page-next', function (e) {
            e.preventDefault();
            var totalPages = Math.max(1, Math.ceil(state.total / state.pageSize));
            if (state.page < totalPages) loadSavedVoucherList(cfg, state.page + 1);
        });
        $card.on('change', '.svl-page-size', function () {
            state.pageSize = parseInt($(this).val(), 10) || 10;
            state.page = 1;
            loadSavedVoucherList(cfg, 1);
        });

        $card.on('click', cfg.exportBtn || '.svl-export-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $menu = $card.find(cfg.exportMenu || '.voucher-list-export-menu');
            $('.voucher-list-export-menu').not($menu).removeClass('show');
            $menu.toggleClass('show');
        });

        $(document).on('click.svlExport', function () {
            closeExportMenu(cfg);
        });
        $card.on('click', cfg.exportMenu || '.voucher-list-export-menu', function (e) {
            e.stopPropagation();
        });

        $card.on('click', '.js-svl-export-excel', function (e) {
            e.preventDefault();
            e.stopPropagation();
            closeExportMenu(cfg);
            if (cfg.exportExcelUrl) window.location.href = cfg.exportExcelUrl;
        });
        $card.on('click', '.js-svl-export-pdf', function (e) {
            e.preventDefault();
            e.stopPropagation();
            closeExportMenu(cfg);
            if (cfg.exportPdfUrl) window.location.href = cfg.exportPdfUrl;
        });

        $card.on('click', '.saved-voucher-edit-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var id = $(this).data('voucher-id');
            if (id && cfg.editPage) {
                window.location.href = cfg.editPage + '?id=' + id;
            }
        });

        $card.on('click', '.saved-voucher-delete-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var id = $(this).data('voucher-id');
            var msg = cfg.deleteConfirm || 'Are you sure you want to delete this voucher?';
            if (!id || !confirm(msg)) return;
            var $btn = $(this);
            $btn.prop('disabled', true);
            $.ajax({
                url: cfg.deleteUrl,
                method: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function (response) {
                    if (response.status === 'success') {
                        if (response.message) alert(response.message);
                        if (String(window.location.search).indexOf('id=' + id) !== -1) {
                            window.location.href = cfg.editPage;
                        } else {
                            loadSavedVoucherList(cfg, state.page);
                        }
                    } else {
                        alert(response.message || 'Failed to delete voucher');
                        $btn.prop('disabled', false);
                    }
                },
                error: function () {
                    alert('Error deleting voucher');
                    $btn.prop('disabled', false);
                }
            });
        });

        $card.on('click', 'tbody tr[data-voucher-id]', function (e) {
            if ($(e.target).closest('.saved-voucher-actions').length) return;
            var id = $(this).data('voucher-id');
            if (id && cfg.editPage) {
                window.location.href = cfg.editPage + '?id=' + id;
            }
        });
    };
})(window.jQuery);
