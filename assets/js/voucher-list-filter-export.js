/**
 * Payment / Receipt voucher list — advance filter modal + export menu.
 */
(function ($) {
    'use strict';
    if (!$) return;

    var activeFilters = {};

    function readFiltersFromForm() {
        return {
            date_from: ($('#vlfDateFrom').val() || '').trim(),
            date_to: ($('#vlfDateTo').val() || '').trim(),
            voucher_no: ($('#vlfVoucherNo').val() || '').trim(),
            branch_type: ($('#vlfBranchType').val() || '').trim(),
            ledger_name: ($('#vlfLedgerName').val() || '').trim(),
            against_voucher_type: ($('#vlfAgainstVoucherType').val() || '').trim(),
            against_invoice_no: ($('#vlfAgainstInvoiceNo').val() || '').trim(),
            account_no: ($('#vlfAccountNo').val() || '').trim()
        };
    }

    function writeFiltersToForm(filters) {
        filters = filters || {};
        $('#vlfDateFrom').val(filters.date_from || '');
        $('#vlfDateTo').val(filters.date_to || '');
        $('#vlfVoucherNo').val(filters.voucher_no || '');
        $('#vlfBranchType').val(filters.branch_type || '');
        $('#vlfLedgerName').val(filters.ledger_name || '');
        $('#vlfAgainstVoucherType').val(filters.against_voucher_type || '');
        $('#vlfAgainstInvoiceNo').val(filters.against_invoice_no || '');
        $('#vlfAccountNo').val(filters.account_no || '');
    }

    function filtersToQuery(filters) {
        var q = [];
        Object.keys(filters || {}).forEach(function (k) {
            var v = filters[k];
            if (v === '' || v === null || v === undefined || v === 0) return;
            q.push(encodeURIComponent(k) + '=' + encodeURIComponent(String(v)));
        });
        return q.join('&');
    }

    function appendQuery(url, qs) {
        if (!qs) return url || '';
        if (!url) return qs;
        return url + (url.indexOf('?') >= 0 ? '&' : '?') + qs;
    }

    function openFilterModal() {
        writeFiltersToForm(activeFilters);
        $('#voucherListAdvanceFilterModal').css('display', 'flex').attr('aria-hidden', 'false');
    }

    function closeFilterModal() {
        $('#voucherListAdvanceFilterModal').hide().attr('aria-hidden', 'true');
    }

    function closeExportMenu() {
        $('.voucher-list-export-menu').removeClass('show');
    }

    window.getVoucherListActiveFilters = function () {
        return $.extend({}, activeFilters);
    };

    window.initVoucherListFilterExport = function (cfg) {
        cfg = cfg || {};
        activeFilters = {};

        $(document).on('click', cfg.filterBtn || '#paymentListFilterBtn', function (e) {
            e.preventDefault();
            e.stopPropagation();
            closeExportMenu();
            openFilterModal();
        });

        $(document).on('click', '#voucherListAdvanceFilterClose', function (e) {
            e.preventDefault();
            closeFilterModal();
        });

        $('#voucherListAdvanceFilterModal').on('click', function (e) {
            if (e.target === this) closeFilterModal();
        });

        $(document).on('click', '#vlfApplyFilterBtn', function (e) {
            e.preventDefault();
            activeFilters = readFiltersFromForm();
            closeFilterModal();
            if (typeof cfg.onApplyFilter === 'function') {
                cfg.onApplyFilter($.extend({}, activeFilters));
            }
        });

        $(document).on('click', '#vlfClearFilterBtn', function (e) {
            e.preventDefault();
            activeFilters = {};
            writeFiltersToForm({});
            closeFilterModal();
            if (typeof cfg.onApplyFilter === 'function') {
                cfg.onApplyFilter({});
            }
        });

        $(document).on('click', cfg.exportBtn || '#paymentListExportBtn', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $menu = $(cfg.exportMenu || '#paymentListExportMenu');
            $('.voucher-list-export-menu').not($menu).removeClass('show');
            $menu.toggleClass('show');
        });

        $(document).on('click', function () {
            closeExportMenu();
        });

        $(document).on('click', cfg.exportMenu || '#paymentListExportMenu', function (e) {
            e.stopPropagation();
        });

        $(document).on('click', '.js-voucher-list-export-excel', function (e) {
            e.preventDefault();
            e.stopPropagation();
            closeExportMenu();
            var qs = filtersToQuery(activeFilters);
            var url = appendQuery(cfg.exportExcelUrl || '', qs);
            if (url) window.location.href = url;
        });

        $(document).on('click', '.js-voucher-list-export-pdf', function (e) {
            e.preventDefault();
            e.stopPropagation();
            closeExportMenu();
            var qs = filtersToQuery(activeFilters);
            var url = appendQuery(cfg.exportPdfUrl || '', qs);
            if (url) window.location.href = url;
        });
    };

    /**
     * Paginated load for Payment / Receipt list tables.
     * Returns { load(filters, page), getState() }.
     */
    window.initPaymentListPagination = function (cfg) {
        cfg = cfg || {};
        var state = { page: 1, pageSize: cfg.pageSize || 10, total: 0, filters: {} };
        var pagRoot = cfg.paginationRoot || '#paymentListPagination';
        var eventsBound = false;

        function readPageSizeFromDom() {
            var v = parseInt($(pagRoot).find('.payment-list-page-size').val(), 10);
            return (v > 0) ? v : state.pageSize;
        }

        function updatePaginationUI() {
            var $pg = $(pagRoot);
            if (!$pg.length) return;
            var totalPages = Math.max(1, Math.ceil(state.total / state.pageSize));
            if (state.page > totalPages) state.page = totalPages;
            var start = state.total === 0 ? 0 : ((state.page - 1) * state.pageSize) + 1;
            var end = Math.min(state.page * state.pageSize, state.total);
            $pg.find('.payment-list-page-info').text(
                state.total === 0
                    ? 'No records'
                    : ('Showing ' + start + '\u2013' + end + ' of ' + state.total + ' \u00b7 Page ' + state.page + ' of ' + totalPages)
            );
            var $prev = $pg.find('.payment-list-page-prev');
            var $next = $pg.find('.payment-list-page-next');
            var canPrev = state.page > 1;
            var canNext = state.page < totalPages;
            $prev.prop('disabled', !canPrev).toggleClass('disabled', !canPrev);
            $next.prop('disabled', !canNext).toggleClass('disabled', !canNext);
            $pg.find('.payment-list-page-size').val(String(state.pageSize));
        }

        function loadPage(filters, page) {
            state.filters = $.extend({}, filters || {});
            state.pageSize = readPageSizeFromDom();
            state.page = (typeof page === 'number' && page > 0) ? page : 1;

            var params = $.extend({ page: state.page, limit: state.pageSize }, state.filters);

            return $.ajax({
                url: cfg.getUrl,
                method: 'GET',
                data: params,
                dataType: 'json'
            }).done(function (response) {
                var t = parseInt(response.total, 10);
                state.total = isNaN(t) ? 0 : t;
                var p = parseInt(response.page, 10);
                if (!isNaN(p) && p > 0) state.page = p;
                var l = parseInt(response.limit, 10);
                if (!isNaN(l) && l > 0) state.pageSize = l;
                updatePaginationUI();
                var $wrap = $('.payment-list-table-wrap');
                if ($wrap.length) $wrap.scrollTop(0);
                if (typeof cfg.onSuccess === 'function') {
                    cfg.onSuccess(response, state);
                }
            }).fail(function (xhr, status, error) {
                if (typeof cfg.onError === 'function') {
                    cfg.onError(xhr, status, error);
                }
            });
        }

        function bindPaginationEvents() {
            if (eventsBound) return;
            eventsBound = true;
            $(document).on('click.voucherListPag', pagRoot + ' .payment-list-page-prev', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (state.page <= 1) return;
                loadPage(state.filters, state.page - 1);
            });
            $(document).on('click.voucherListPag', pagRoot + ' .payment-list-page-next', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var totalPages = Math.max(1, Math.ceil(state.total / state.pageSize));
                if (state.page >= totalPages) return;
                loadPage(state.filters, state.page + 1);
            });
            $(document).on('change.voucherListPag', pagRoot + ' .payment-list-page-size', function (e) {
                e.stopPropagation();
                loadPage(state.filters, 1);
            });
        }

        bindPaginationEvents();

        return {
            load: loadPage,
            getState: function () { return state; }
        };
    };
})(window.jQuery);
