/**
 * Sale Order — Add Stone modal: column layout, resize, drag-reorder, editable wt/qty, Add / Allocate.
 */
(function () {
    'use strict';

    /** Lines queued before first Save — applied server-side on save (stock deducted then). */
    if (!Array.isArray(window.__pendingSaleOrderStoneLines)) {
        window.__pendingSaleOrderStoneLines = [];
    }

    if (!Array.isArray(window.__saleOrderStoneIssueRows)) {
        window.__saleOrderStoneIssueRows = [];
    }

    var SO_STONE_MIDDLE_KEYS_DEFAULT = [
        'active', 'item_code', 'barcode_no', 'style', 'stone_category', 'calculation_type',
        'product', 'weight', 'stone_carat', 'quantity', 'rate', 'certificate_no',
        'cut', 'color', 'seivesize', 'size', 'shape', 'quality'
    ];

    var SO_STONE_LABELS = {
        row_chk: '',
        active: 'Active',
        item_code: 'Item Code',
        barcode_no: 'Barcode No',
        style: 'Style',
        stone_category: 'Stone Category',
        calculation_type: 'Calculation Type',
        product: 'Product',
        weight: 'Weight',
        stone_carat: 'Stone Carat',
        quantity: 'Quantity',
        rate: 'Rate',
        certificate_no: 'Certificate Number',
        cut: 'Cut',
        color: 'Color',
        seivesize: 'SeiveSize',
        size: 'Size',
        shape: 'Shape',
        quality: 'Quality',
        add_btn: 'Add'
    };

    var SO_STONE_COL_ORDER_KEY = 'saleOrderStoneColOrder';
    var SO_STONE_COL_WIDTH_KEY = 'saleOrderStoneColWidths';

    var _saleOrderStoneResizeState = null;
    window._saleOrderStoneLastItems = null;

    function getCurrentVoucherKindForStone() {
        var cfg = window.AURAGOLD_VOUCHER_DS || {};
        return cfg.voucherKind || 'sale_order';
    }

    function getCurrentSaleOrderIdForStone() {
        var cfg = window.AURAGOLD_VOUCHER_DS || {};
        var param = cfg.urlIdParam || 'id';
        var urlParams = new URLSearchParams(window.location.search);
        var urlId = parseInt(urlParams.get(param) || '0', 10);
        var kind = cfg.voucherKind || 'sale_order';

        if (kind === 'jobwork_order' || kind === 'material_issue' || kind === 'material_receive') {
            var edJwo = window.editUrlIdIsJobworkOrder === true || window.editUrlIdIsJobworkOrder === 'true';
            if (edJwo && urlId > 0) {
                return urlId;
            }
            var key = cfg.windowDbIdKey || '';
            if (key && typeof window[key] !== 'undefined') {
                var gj = parseInt(window[key], 10);
                if (gj > 0) {
                    return gj;
                }
            }
            var hid = document.getElementById('jwoCurrentId');
            if (hid) {
                var dj = parseInt(hid.getAttribute('data-jwo-id') || '0', 10);
                if (dj > 0) {
                    return dj;
                }
            }
            return 0;
        }

        if (kind === 'jobwork_invoice') {
            var keyJi = cfg.windowDbIdKey || '';
            if (keyJi && typeof window[keyJi] !== 'undefined') {
                var gji = parseInt(window[keyJi], 10);
                if (gji > 0) {
                    return gji;
                }
            }
            var hidJwi = document.getElementById('jwiCurrentId');
            if (hidJwi) {
                var dji = parseInt(hidJwi.getAttribute('data-jwi-id') || '0', 10);
                if (dji > 0) {
                    return dji;
                }
            }
            return 0;
        }

        if (urlId > 0) {
            return urlId;
        }
        var winKey = cfg.windowDbIdKey || '';
        if (winKey && typeof window[winKey] !== 'undefined') {
            var g = parseInt(window[winKey], 10);
            if (g > 0) {
                return g;
            }
        }
        var g2 = typeof window.__saleOrderDbId !== 'undefined' ? parseInt(window.__saleOrderDbId, 10) : 0;
        return g2 > 0 ? g2 : 0;
    }

    function refreshSaleOrderStoneModalBanner() {
        var oid = getCurrentSaleOrderIdForStone();
        var kind = getCurrentVoucherKindForStone();
        var isSaleOrder = kind === 'sale_order';
        var lbl = document.getElementById('saleOrderStoneStockOrderLabel');
        var hint = document.getElementById('saleOrderStoneStockHint');
        var pend = (window.__pendingSaleOrderStoneLines && window.__pendingSaleOrderStoneLines.length) || 0;
        if (lbl) {
            lbl.textContent = oid
                ? (isSaleOrder
                    ? ('Sale order id ' + oid + ' — Add deducts stock now; Save still saves the order.')
                    : ('Saved document #' + oid + ' — Add deducts stock now; Save still saves the document.'))
                : (pend > 0
                    ? pend + ' stone line(s) queued — stock deducts when you Save.'
                    : 'Pick stones and Add / Allocate — stock deducts when you Save (before Save they stay queued).');
        }
        if (hint) {
            hint.textContent = oid
                ? ''
                : (isSaleOrder
                    ? 'No saved order yet: stones are held until you click Save on the sale order.'
                    : 'No saved document yet: stones are held until you Save.');
        }
    }

    function saleOrderStoneEscapeHtml(s) {
        if (s == null || s === '') return '';
        var d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }

    function saleOrderStoneFmtNum(v) {
        var n = parseFloat(v);
        if (!isFinite(n)) {
            return '';
        }
        var x = Math.round(n * 10000) / 10000;
        return String(x);
    }

    function auragoldDsStoneUsageTablesAlwaysVisible() {
        var cfg = window.AURAGOLD_VOUCHER_DS || {};
        var k = cfg.voucherKind || '';
        return (
            k === 'jobwork_order' ||
            k === 'jobwork_invoice' ||
            k === 'material_issue' ||
            k === 'material_receive'
        );
    }

    function renderSaleOrderStoneLinesPanel() {
        var tbody = document.getElementById('saleOrderStoneLinesTbody');
        var card = document.getElementById('saleOrderStoneLinesCard');
        if (!tbody) {
            return;
        }
        var issuedRef =
            getCurrentVoucherKindForStone() === 'material_receive' &&
            Array.isArray(window.__materialIssueReferenceStoneRows)
                ? window.__materialIssueReferenceStoneRows
                : [];
        var saved = Array.isArray(window.__saleOrderStoneIssueRows) ? window.__saleOrderStoneIssueRows : [];
        var pend = Array.isArray(window.__pendingSaleOrderStoneLines) ? window.__pendingSaleOrderStoneLines : [];
        tbody.innerHTML = '';
        if (saved.length === 0 && pend.length === 0 && issuedRef.length === 0) {
            if (card) {
                if (auragoldDsStoneUsageTablesAlwaysVisible()) {
                    card.hidden = false;
                    if (
                        getCurrentVoucherKindForStone() === 'material_receive' &&
                        typeof window.mrAppendIssuedEmptyRow === 'function'
                    ) {
                        window.mrAppendIssuedEmptyRow(tbody, 'stone', 9);
                    } else {
                        var trEmpty = document.createElement('tr');
                        var tdEmpty = document.createElement('td');
                        tdEmpty.colSpan = getCurrentVoucherKindForStone() === 'material_receive' ? 8 : 6;
                        tdEmpty.className = 'text-center text-muted py-3';
                        tdEmpty.textContent =
                            getCurrentVoucherKindForStone() === 'material_receive'
                                ? 'No stones issued on Material Issue for this sale order yet. After issue, lines appear here; tick lines and add receive weight.'
                                : 'No gemstones / stones in this list yet. Click the Stone icon above to choose barcode / stock.';
                        trEmpty.appendChild(tdEmpty);
                        tbody.appendChild(trEmpty);
                    }
                } else {
                    card.hidden = true;
                }
            }
            if (
                getCurrentVoucherKindForStone() === 'material_receive' &&
                typeof window.mrUpdateIssuedReceiveTabCounts === 'function'
            ) {
                window.mrUpdateIssuedReceiveTabCounts();
            }
            return;
        }
        if (card) {
            card.hidden = false;
        }
        if (
            issuedRef.length > 0 &&
            typeof window.mrRenderIssuedStoneRows === 'function' &&
            window.mrRenderIssuedStoneRows(tbody, issuedRef)
        ) {
            /* interactive partial-receive rows */
        } else {
            issuedRef.forEach(function (r) {
                var q = r.qty != null ? r.qty : '';
                var w = r.weight != null ? r.weight : '';
                var tr = document.createElement('tr');
                tr.style.background = '#f8fafc';
                tr.innerHTML =
                    '<td>' +
                    saleOrderStoneEscapeHtml(r.barcode || '') +
                    '</td><td>' +
                    saleOrderStoneEscapeHtml(r.product_name || '') +
                    '</td><td>' +
                    saleOrderStoneEscapeHtml(r.stone_category || '') +
                    '</td><td class="text-right">' +
                    saleOrderStoneFmtNum(q) +
                    '</td><td class="text-right">' +
                    saleOrderStoneFmtNum(w) +
                    '</td><td><span class="badge" style="background:#1e40af;font-size:0.7rem;">Issued</span></td>';
                tbody.appendChild(tr);
            });
        }
        saved.forEach(function (r) {
            var q = r.qty != null ? r.qty : '';
            var w = r.weight != null ? r.weight : '';
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td>' +
                saleOrderStoneEscapeHtml(r.barcode || '') +
                '</td><td>' +
                saleOrderStoneEscapeHtml(r.product_name || '') +
                '</td><td>' +
                saleOrderStoneEscapeHtml(r.stone_category || '') +
                '</td><td class="text-right">' +
                saleOrderStoneFmtNum(q) +
                '</td><td class="text-right">' +
                saleOrderStoneFmtNum(w) +
                '</td><td><span class="' +
                (getCurrentVoucherKindForStone() === 'material_receive'
                    ? 'mr-issued-badge mr-issued-badge--received'
                    : 'badge badge-success') +
                '" style="' +
                (getCurrentVoucherKindForStone() === 'material_receive' ? '' : 'background:#166534;font-size:0.7rem;') +
                '">' +
                (getCurrentVoucherKindForStone() === 'material_receive' ? 'Received' : 'Allocated') +
                '</span></td>';
            tbody.appendChild(tr);
        });
        pend.forEach(function (r) {
            var q = r.allocate_qty != null ? r.allocate_qty : '';
            var w = r.allocate_weight != null ? r.allocate_weight : '';
            var prodLabel = r.mr_display_product || r.product_name || '';
            if (!r.mr_display_product && r.mr_issued_product_name && r.product_name && r.mr_issued_product_name !== r.product_name) {
                prodLabel = r.mr_issued_product_name + ' → ' + r.product_name;
            }
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td>' +
                saleOrderStoneEscapeHtml(r.barcode || '') +
                '</td><td>' +
                saleOrderStoneEscapeHtml(prodLabel) +
                '</td><td>' +
                saleOrderStoneEscapeHtml(r.stone_category || '') +
                '</td><td class="text-right">' +
                saleOrderStoneFmtNum(q) +
                '</td><td class="text-right">' +
                saleOrderStoneFmtNum(w) +
                '</td><td><span class="' +
                (getCurrentVoucherKindForStone() === 'material_receive'
                    ? 'mr-issued-badge mr-issued-badge--pending'
                    : 'badge badge-secondary') +
                '" style="' +
                (getCurrentVoucherKindForStone() === 'material_receive'
                    ? ''
                    : 'background:#fde047;color:#422006;font-size:0.7rem;') +
                '">' +
                (getCurrentVoucherKindForStone() === 'material_receive'
                    ? 'Pending receive — saves on Save'
                    : 'Pending — deducts on Save') +
                '</span></td>';
            tbody.appendChild(tr);
        });
        if (
            getCurrentVoucherKindForStone() === 'material_receive' &&
            typeof window.mrUpdateIssuedReceiveTabCounts === 'function'
        ) {
            window.mrUpdateIssuedReceiveTabCounts();
        }
    }

    function refreshSaleOrderStoneIssuesFromServer(orderId) {
        orderId = parseInt(orderId, 10) || 0;
        if (orderId < 1) {
            renderSaleOrderStoneLinesPanel();
            return;
        }
        fetch(
            'ajax/list-voucher-stone-issues.php?voucher_kind=' +
                encodeURIComponent(getCurrentVoucherKindForStone()) +
                '&voucher_id=' +
                encodeURIComponent(String(orderId)) +
                '&_=' +
                (Date.now ? Date.now() : 0)
        )
            .then(function (r) {
                return r.json();
            })
            .then(function (data) {
                if (data && data.ok && Array.isArray(data.items)) {
                    window.__saleOrderStoneIssueRows = data.items;
                }
                renderSaleOrderStoneLinesPanel();
            })
            .catch(function () {
                renderSaleOrderStoneLinesPanel();
            });
    }

    function soStoneNormalizeMiddleOrder(arr) {
        var def = SO_STONE_MIDDLE_KEYS_DEFAULT;
        var seen = {};
        var out = [];
        if (!Array.isArray(arr)) arr = [];
        arr.forEach(function (k) {
            if (def.indexOf(k) !== -1 && !seen[k]) {
                seen[k] = true;
                out.push(k);
            }
        });
        def.forEach(function (k) {
            if (!seen[k]) out.push(k);
        });
        return out;
    }

    function soStoneGetMiddleOrder() {
        try {
            var raw = localStorage.getItem(SO_STONE_COL_ORDER_KEY);
            if (raw) {
                var parsed = JSON.parse(raw);
                if (Array.isArray(parsed) && parsed.length) {
                    return soStoneNormalizeMiddleOrder(parsed);
                }
            }
        } catch (e) { /* ignore */ }
        return SO_STONE_MIDDLE_KEYS_DEFAULT.slice();
    }

    function soStoneSaveMiddleOrder(keys) {
        try {
            localStorage.setItem(SO_STONE_COL_ORDER_KEY, JSON.stringify(soStoneNormalizeMiddleOrder(keys)));
        } catch (e) { /* ignore */ }
    }

    function soStoneGetWidths() {
        try {
            var raw = localStorage.getItem(SO_STONE_COL_WIDTH_KEY);
            if (raw) return JSON.parse(raw) || {};
        } catch (e) { /* ignore */ }
        return {};
    }

    function soStoneSaveWidths(obj) {
        try {
            localStorage.setItem(SO_STONE_COL_WIDTH_KEY, JSON.stringify(obj || {}));
        } catch (e) { /* ignore */ }
    }

    function soStoneFullOrder() {
        return ['row_chk'].concat(soStoneGetMiddleOrder()).concat(['add_btn']);
    }

    function soStoneColCount() {
        return soStoneFullOrder().length;
    }

    function saleOrderStoneCollectLine(tr) {
        var sid = parseInt(tr.getAttribute('data-stock-id'), 10);
        if (!sid) return null;
        var wEl = tr.querySelector('.so-stone-inp-weight');
        var qEl = tr.querySelector('.so-stone-inp-qty');
        var w = wEl ? parseFloat(wEl.value) : 0;
        var q = qEl ? parseFloat(qEl.value) : 0;
        if (!(w > 0) && !(q > 0)) return null;
        return {
            stock_id: sid,
            barcode: tr.getAttribute('data-barcode') || '',
            allocate_qty: q > 0 ? q : 0,
            allocate_weight: w > 0 ? w : 0,
            product_name: tr.getAttribute('data-product-name') || '',
            stone_category: tr.getAttribute('data-category') || ''
        };
    }

    function saleOrderStonePostAllocate(lines, opts) {
        opts = opts || {};
        var orderId = getCurrentSaleOrderIdForStone();
        var allocBtn = document.getElementById('saleOrderStoneAllocateBtn');
        function finishEarly() {
            if (opts.lockFooter && allocBtn) allocBtn.disabled = false;
            if (typeof opts.onFinally === 'function') opts.onFinally();
        }
        if (!lines || !lines.length) {
            alert('Set Weight and/or Quantity on selected rows (values must be greater than zero).');
            finishEarly();
            return;
        }
        if (orderId <= 0) {
            lines.forEach(function (line) {
                window.__pendingSaleOrderStoneLines.push({
                    stock_id: line.stock_id,
                    barcode: line.barcode || '',
                    allocate_qty: line.allocate_qty,
                    allocate_weight: line.allocate_weight,
                    product_name: line.product_name || '',
                    stone_category: line.stone_category || ''
                });
            });
            finishEarly();
            refreshSaleOrderStoneModalBanner();
            renderSaleOrderStoneLinesPanel();
            if (opts.closeOnSuccess && typeof window.jQuery !== 'undefined') {
                window.jQuery('#saleOrderStoneStockModal').modal('hide');
            }
            if (typeof window.loadSaleOrderStoneStockRows === 'function') {
                window.loadSaleOrderStoneStockRows();
            }
            return;
        }
        if (opts.lockFooter && allocBtn) allocBtn.disabled = true;
        fetch('ajax/allocate-voucher-stones.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                voucher_kind: getCurrentVoucherKindForStone(),
                voucher_id: orderId,
                lines: lines
            })
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.ok) {
                    /* Show rows immediately; list refresh replaces with DB truth. */
                    if (!Array.isArray(window.__saleOrderStoneIssueRows)) {
                        window.__saleOrderStoneIssueRows = [];
                    }
                    lines.forEach(function (line) {
                        var q = line.allocate_qty != null ? parseFloat(line.allocate_qty) : 0;
                        var w = line.allocate_weight != null ? parseFloat(line.allocate_weight) : 0;
                        window.__saleOrderStoneIssueRows.push({
                            barcode: line.barcode || '',
                            product_name: line.product_name || '',
                            stone_category: line.stone_category || '',
                            qty: isFinite(q) ? q : 0,
                            weight: isFinite(w) ? w : 0
                        });
                    });
                    renderSaleOrderStoneLinesPanel();
                    refreshSaleOrderStoneIssuesFromServer(orderId);
                    if (opts.closeOnSuccess && typeof window.jQuery !== 'undefined') {
                        window.jQuery('#saleOrderStoneStockModal').modal('hide');
                    }
                    alert(res.message || 'Allocated.');
                    if (typeof window.loadSaleOrderStoneStockRows === 'function') {
                        window.loadSaleOrderStoneStockRows();
                    }
                } else {
                    alert(res.message || 'Allocation failed.');
                }
            })
            .catch(function () {
                alert('Network error during allocation.');
            })
            .finally(function () {
                if (opts.lockFooter && allocBtn) allocBtn.disabled = false;
                if (typeof opts.onFinally === 'function') opts.onFinally();
            });
    }

    function soStoneReadMiddleKeysFromHeader(theadRow) {
        var keys = [];
        theadRow.querySelectorAll('th[data-col-key]').forEach(function (th) {
            var k = th.getAttribute('data-col-key');
            if (k && k !== 'row_chk' && k !== 'add_btn') keys.push(k);
        });
        return soStoneNormalizeMiddleOrder(keys);
    }

    function renderSaleOrderStoneHeader() {
        var thead = document.getElementById('saleOrderStoneStockThead');
        if (!thead) return;
        var widths = soStoneGetWidths();
        var order = soStoneFullOrder();
        var tr = document.createElement('tr');

        order.forEach(function (key) {
            var th = document.createElement('th');
            th.setAttribute('data-col-key', key);
            th.className = 'so-stone-th position-relative align-middle';
            var w = widths[key];
            if (w) th.style.width = w + 'px';
            if (key === 'row_chk') {
                th.classList.add('so-stone-th-fixed');
                th.style.width = widths.row_chk || '44px';
                th.innerHTML = '<input type="checkbox" id="saleOrderStoneCheckAll" title="Select all">';
            } else if (key === 'add_btn') {
                th.classList.add('so-stone-th-fixed');
                th.style.width = widths.add_btn || '76px';
                th.textContent = SO_STONE_LABELS.add_btn;
            } else {
                th.draggable = true;
                th.innerHTML =
                    '<span class="so-stone-drag-hint" title="Drag to reorder column">&#9776;</span>' +
                    '<span class="so-stone-th-label">' + saleOrderStoneEscapeHtml(SO_STONE_LABELS[key] || key) + '</span>';
                th.addEventListener('dragstart', function (e) {
                    if (e.target.closest('.so-stone-resizer')) {
                        e.preventDefault();
                        return;
                    }
                    e.dataTransfer.setData('text/plain', key);
                    e.dataTransfer.effectAllowed = 'move';
                    th.classList.add('so-stone-dragging');
                });
                th.addEventListener('dragend', function () {
                    th.classList.remove('so-stone-dragging');
                });
                th.addEventListener('dragover', function (e) {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                });
                th.addEventListener('drop', function (e) {
                    e.preventDefault();
                    var src = e.dataTransfer.getData('text/plain');
                    var dst = key;
                    if (!src || src === dst || src === 'row_chk' || src === 'add_btn' || dst === 'row_chk' || dst === 'add_btn') return;
                    var theadRow = document.querySelector('#saleOrderStoneStockThead tr');
                    if (!theadRow) return;
                    var cur = soStoneReadMiddleKeysFromHeader(theadRow);
                    var i = cur.indexOf(src);
                    var j = cur.indexOf(dst);
                    if (i < 0 || j < 0) return;
                    var t = cur[i];
                    cur[i] = cur[j];
                    cur[j] = t;
                    soStoneSaveMiddleOrder(cur);
                    renderSaleOrderStoneHeader();
                    if (window._saleOrderStoneLastItems) {
                        renderSaleOrderStoneRows(window._saleOrderStoneLastItems);
                    }
                });
            }
            var rz = document.createElement('span');
            rz.className = 'so-stone-resizer';
            rz.title = 'Resize column';
            rz.addEventListener('mousedown', function (e) {
                e.stopPropagation();
                e.preventDefault();
                _saleOrderStoneResizeState = {
                    key: key,
                    th: th,
                    startX: e.pageX,
                    startW: th.offsetWidth
                };
            });
            th.appendChild(rz);
            tr.appendChild(th);
        });

        thead.innerHTML = '';
        thead.appendChild(tr);
    }

    function soStoneOnResizeMove(e) {
        if (!_saleOrderStoneResizeState) return;
        var st = _saleOrderStoneResizeState;
        var dx = e.pageX - st.startX;
        var nw = Math.max(36, st.startW + dx);
        st.th.style.width = nw + 'px';
        st.th.style.minWidth = nw + 'px';
    }

    function soStoneOnResizeEnd() {
        if (!_saleOrderStoneResizeState) return;
        var st = _saleOrderStoneResizeState;
        var wmap = soStoneGetWidths();
        wmap[st.key] = st.th.offsetWidth;
        soStoneSaveWidths(wmap);
        _saleOrderStoneResizeState = null;
    }

    function renderSaleOrderStoneRows(items) {
        var tbody = document.getElementById('saleOrderStoneStockTableBody');
        if (!tbody) return;
        var order = soStoneFullOrder();
        tbody.innerHTML = '';

        if (!items || !items.length) {
            var tr0 = document.createElement('tr');
            tr0.innerHTML = '<td colspan="' + soStoneColCount() + '" class="text-center text-muted py-4">No stone stock found for this filter. Try naming categories/products with stone/gem keywords, or widen filters in stone_stock_list_sql_include.php.</td>';
            tbody.appendChild(tr0);
            return;
        }

        items.forEach(function (it, idx) {
            var tr = document.createElement('tr');
            var sid = parseInt(it.stock_id, 10) || 0;
            tr.setAttribute('data-stock-id', String(sid));
            tr.setAttribute('data-barcode', String(it.barcode || ''));
            tr.setAttribute('data-product-name', String(it.product_name || ''));
            tr.setAttribute('data-category', String(it.stone_category || ''));

            var availWt = parseFloat(it.current_weight) || 0;
            var availQty = parseFloat(it.current_qty) || 0;
            var rate = parseFloat(it.rate) || 0;

            order.forEach(function (key) {
                var td = document.createElement('td');
                td.className = 'align-middle';
                td.setAttribute('data-col-key', key);
                if (key === 'row_chk') {
                    td.innerHTML = '<input type="checkbox" class="so-stone-row-chk" id="soStoneChk_' + sid + '_' + idx + '">';
                } else if (key === 'weight') {
                    td.className += ' text-right';
                    td.innerHTML =
                        '<input type="number" step="0.001" min="0" class="form-control form-control-sm so-stone-inp-weight text-right" value="' +
                        saleOrderStoneEscapeHtml(availWt.toFixed(3)) +
                        '">';
                } else if (key === 'quantity') {
                    td.className += ' text-right';
                    td.innerHTML =
                        '<input type="number" step="0.01" min="0" class="form-control form-control-sm so-stone-inp-qty text-right" value="' +
                        saleOrderStoneEscapeHtml(availQty.toFixed(2)) +
                        '">';
                } else if (key === 'rate') {
                    td.className += ' text-right';
                    td.textContent = rate.toFixed(2);
                } else if (key === 'add_btn') {
                    td.innerHTML =
                        '<button type="button" class="btn btn-sm text-white so-stone-add-btn px-2 py-0" style="background:#5b21b6;font-size:11px;">Add</button>';
                } else if (key === 'item_code') {
                    td.textContent = it.article || '';
                } else if (key === 'barcode_no') {
                    td.textContent = it.barcode || '';
                } else if (key === 'product') {
                    td.textContent = it.product_name || '';
                } else if (key === 'stone_category') {
                    td.textContent = it.stone_category || '';
                } else if (key === 'style') {
                    td.textContent = it.style || '';
                } else if (key === 'calculation_type') {
                    td.textContent = it.calculation_type || '';
                } else if (key === 'stone_carat') {
                    td.className += ' text-right';
                    td.textContent = it.stone_carat != null ? String(it.stone_carat) : '';
                } else if (key === 'certificate_no') {
                    td.textContent = it.certificate_no || '';
                } else if (key === 'cut') {
                    td.textContent = it.cut || '';
                } else if (key === 'color') {
                    td.textContent = it.color || '';
                } else if (key === 'seivesize') {
                    td.textContent = it.seivesize || '';
                } else if (key === 'size') {
                    td.textContent = it.size || '';
                } else if (key === 'shape') {
                    td.textContent = it.shape || '';
                } else if (key === 'quality') {
                    td.textContent = it.quality || '';
                } else if (key === 'active') {
                    td.textContent = it.active || '';
                } else {
                    td.textContent = '';
                }
                tr.appendChild(td);
            });
            tbody.appendChild(tr);
        });
    }

    function loadSaleOrderStoneStockRows() {
        var tbody = document.getElementById('saleOrderStoneStockTableBody');
        var q = document.getElementById('saleOrderStoneStockSearch');
        var term = q ? String(q.value || '').trim() : '';
        if (!tbody) return;
        tbody.innerHTML =
            '<tr><td colspan="' + soStoneColCount() + '" class="text-center text-muted py-3">Loading…</td></tr>';
        var url = 'ajax/list-stone-stock-for-sale-order.php?_=' + (Date.now ? Date.now() : 0);
        if (term) url += '&search=' + encodeURIComponent(term);
        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok || !data.items) {
                    tbody.innerHTML =
                        '<tr><td colspan="' + soStoneColCount() + '" class="text-center text-danger py-3">' +
                        saleOrderStoneEscapeHtml(data.message || 'Could not load stock') +
                        '</td></tr>';
                    window._saleOrderStoneLastItems = [];
                    return;
                }
                window._saleOrderStoneLastItems = data.items;
                renderSaleOrderStoneHeader();
                renderSaleOrderStoneRows(data.items);
            })
            .catch(function () {
                tbody.innerHTML =
                    '<tr><td colspan="' + soStoneColCount() + '" class="text-center text-danger py-3">Network error loading stock.</td></tr>';
                window._saleOrderStoneLastItems = [];
            });
    }

    function openSaleOrderStoneStockModal() {
        if (typeof window.jQuery === 'undefined' || !window.jQuery.fn.modal) {
            alert('Bootstrap modal not available.');
            return;
        }
        refreshSaleOrderStoneModalBanner();
        var tEx = document.getElementById('saleOrderStoneTabExisting');
        var tLo = document.getElementById('saleOrderStoneTabLoose');
        var pEx = document.getElementById('saleOrderStonePanelExisting');
        var pLo = document.getElementById('saleOrderStonePanelLoose');
        if (tEx && tLo) {
            tEx.classList.add('active');
            tEx.style.background = '#5b21b6';
            tEx.style.color = '#fff';
            tEx.style.borderColor = '#5b21b6';
            tLo.classList.remove('active');
            tLo.style.background = '';
            tLo.style.color = '';
            tLo.style.borderColor = '';
        }
        if (pEx) pEx.style.display = '';
        if (pLo) pLo.style.display = 'none';
        window.jQuery('#saleOrderStoneStockModal').modal('show');
        loadSaleOrderStoneStockRows();
    }

    function bindSaleOrderStoneStockModalUi() {
        var searchEl = document.getElementById('saleOrderStoneStockSearch');
        var tmr = null;
        if (searchEl && !searchEl._soStoneBound) {
            searchEl._soStoneBound = true;
            searchEl.addEventListener('input', function () {
                clearTimeout(tmr);
                tmr = setTimeout(loadSaleOrderStoneStockRows, 320);
            });
        }

        var tEx = document.getElementById('saleOrderStoneTabExisting');
        var tLo = document.getElementById('saleOrderStoneTabLoose');
        var pEx = document.getElementById('saleOrderStonePanelExisting');
        var pLo = document.getElementById('saleOrderStonePanelLoose');
        function activateExisting() {
            if (tEx && tLo) {
                tEx.classList.add('active');
                tEx.style.background = '#5b21b6';
                tEx.style.color = '#fff';
                tEx.style.borderColor = '#5b21b6';
                tLo.classList.remove('active');
                tLo.style.background = '';
                tLo.style.color = '';
                tLo.style.borderColor = '';
            }
            if (pEx) pEx.style.display = '';
            if (pLo) pLo.style.display = 'none';
        }
        function activateLoose() {
            if (tEx && tLo) {
                tLo.classList.add('active');
                tLo.style.background = '#5b21b6';
                tLo.style.color = '#fff';
                tLo.style.borderColor = '#5b21b6';
                tEx.classList.remove('active');
                tEx.style.background = '';
                tEx.style.color = '';
                tEx.style.borderColor = '';
            }
            if (pEx) pEx.style.display = 'none';
            if (pLo) pLo.style.display = '';
        }
        if (tEx && !tEx._soStoneBound) {
            tEx._soStoneBound = true;
            tEx.addEventListener('click', activateExisting);
        }
        if (tLo && !tLo._soStoneBound) {
            tLo._soStoneBound = true;
            tLo.addEventListener('click', activateLoose);
        }

        var modalEl = document.getElementById('saleOrderStoneStockModal');
        if (modalEl && !modalEl._soStoneDeleg) {
            modalEl._soStoneDeleg = true;
            modalEl.addEventListener('change', function (e) {
                if (e.target && e.target.id === 'saleOrderStoneCheckAll') {
                    var on = e.target.checked;
                    document.querySelectorAll('#saleOrderStoneStockTableBody .so-stone-row-chk').forEach(function (c) {
                        c.checked = on;
                    });
                }
            });
            modalEl.addEventListener('click', function (e) {
                var btn = e.target.closest('.so-stone-add-btn');
                if (!btn) return;
                var tr = btn.closest('tr');
                if (!tr) return;
                var line = saleOrderStoneCollectLine(tr);
                if (!line) {
                    alert('Enter Weight and/or Quantity greater than zero.');
                    return;
                }
                btn.disabled = true;
                saleOrderStonePostAllocate([line], {
                    closeOnSuccess: false,
                    lockFooter: false,
                    onFinally: function () {
                        btn.disabled = false;
                    }
                });
            });
        }

        var allocBtn = document.getElementById('saleOrderStoneAllocateBtn');
        if (allocBtn && !allocBtn._soStoneBound) {
            allocBtn._soStoneBound = true;
            allocBtn.addEventListener('click', function () {
                var lines = [];
                document.querySelectorAll('#saleOrderStoneStockTableBody tr[data-stock-id]').forEach(function (tr) {
                    var chk = tr.querySelector('.so-stone-row-chk');
                    if (!chk || !chk.checked) return;
                    var line = saleOrderStoneCollectLine(tr);
                    if (line) lines.push(line);
                });
                saleOrderStonePostAllocate(lines, { closeOnSuccess: true, lockFooter: true });
            });
        }

        if (!window._soStoneResizeDocBound) {
            window._soStoneResizeDocBound = true;
            document.addEventListener('mousemove', soStoneOnResizeMove);
            document.addEventListener('mouseup', soStoneOnResizeEnd);
        }
    }

    window.initSaleOrderStoneModal = function () {
        bindSaleOrderStoneStockModalUi();
        renderSaleOrderStoneLinesPanel();
        var cfg = window.AURAGOLD_VOUCHER_DS || {};
        var vid = parseInt(String(window.__auragoldVoucherDbId || '0'), 10) || 0;
        if (
            vid > 0 &&
            ((cfg.voucherKind || '') === 'jobwork_order' ||
                (cfg.voucherKind || '') === 'jobwork_invoice' ||
                (cfg.voucherKind || '') === 'material_issue' ||
                (cfg.voucherKind || '') === 'material_receive')
        ) {
            refreshSaleOrderStoneIssuesFromServer(vid);
        }
    };
    window.openSaleOrderStoneStockModal = openSaleOrderStoneStockModal;
    window.loadSaleOrderStoneStockRows = loadSaleOrderStoneStockRows;
    window.renderSaleOrderStoneLinesPanel = renderSaleOrderStoneLinesPanel;
    window.refreshSaleOrderStoneIssuesFromServer = refreshSaleOrderStoneIssuesFromServer;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.initSaleOrderStoneModal);
    } else {
        window.initSaleOrderStoneModal();
    }
})();
