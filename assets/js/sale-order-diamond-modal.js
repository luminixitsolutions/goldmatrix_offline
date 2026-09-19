/**
 * Sale Order — Add Diamond modal: column layout, resize, drag-reorder, editable wt/qty, Add / Allocate.
 */
(function () {
    'use strict';

    /** Lines queued before first Save — applied server-side on save (stock deducted then). */
    if (!Array.isArray(window.__pendingSaleOrderDiamondLines)) {
        window.__pendingSaleOrderDiamondLines = [];
    }

    if (!Array.isArray(window.__saleOrderDiamondIssueRows)) {
        window.__saleOrderDiamondIssueRows = [];
    }

    var SO_DIM_MIDDLE_KEYS_DEFAULT = [
        'active', 'item_code', 'barcode_no', 'style', 'diamond_category', 'calculation_type',
        'product', 'weight', 'diamond_carat', 'quantity', 'rate', 'certificate_no',
        'cut', 'color', 'seivesize', 'size', 'shape', 'quality'
    ];

    var SO_DIM_LABELS = {
        row_chk: '',
        active: 'Active',
        item_code: 'Item Code',
        barcode_no: 'Barcode No',
        style: 'Style',
        diamond_category: 'Diamond Category',
        calculation_type: 'Calculation Type',
        product: 'Product',
        weight: 'Weight',
        diamond_carat: 'Diamond Carat',
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

    var SO_DIM_COL_ORDER_KEY = 'saleOrderDiamondColOrder';
    var SO_DIM_COL_WIDTH_KEY = 'saleOrderDiamondColWidths';

    var _saleOrderDiamondResizeState = null;
    window._saleOrderDiamondLastItems = null;

    function getCurrentVoucherKindForDiamond() {
        var cfg = window.AURAGOLD_VOUCHER_DS || {};
        return cfg.voucherKind || 'sale_order';
    }

    /** Job work / material issue: queue diamonds until document Save (no immediate stock API). */
    function voucherDefersDiamondDeductionToSave() {
        var kind = getCurrentVoucherKindForDiamond();
        return (
            kind === 'jobwork_order' ||
            kind === 'jobwork_invoice' ||
            kind === 'material_issue' ||
            kind === 'material_receive'
        );
    }

    function saleOrderDiamondHasQueuedOrAllocatedLines() {
        var pend = (window.__pendingSaleOrderDiamondLines && window.__pendingSaleOrderDiamondLines.length) || 0;
        var saved = (window.__saleOrderDiamondIssueRows && window.__saleOrderDiamondIssueRows.length) || 0;
        return pend > 0 || saved > 0;
    }

    function saleOrderDiamondSelectedBarcodeMap() {
        var map = {};
        (window.__pendingSaleOrderDiamondLines || []).forEach(function (ln) {
            var bc = saleOrderDiamondNormalizeBc(ln.barcode);
            if (bc) {
                map[bc] = true;
            }
        });
        (window.__saleOrderDiamondIssueRows || []).forEach(function (r) {
            var bc = saleOrderDiamondNormalizeBc(r.barcode);
            if (bc) {
                map[bc] = true;
            }
        });
        return map;
    }

    function saleOrderDiamondNormalizeBc(bc) {
        return String(bc || '').trim().toLowerCase();
    }

    /** @returns {{kind:'pending'|'saved', index:number, issueId?:number, data:Object}|null} */
    function saleOrderDiamondFindOnOrder(bc) {
        var key = saleOrderDiamondNormalizeBc(bc);
        if (!key) {
            return null;
        }
        var pend = window.__pendingSaleOrderDiamondLines || [];
        var i;
        for (i = 0; i < pend.length; i++) {
            if (saleOrderDiamondNormalizeBc(pend[i].barcode) === key) {
                return { kind: 'pending', index: i, data: pend[i] };
            }
        }
        var saved = window.__saleOrderDiamondIssueRows || [];
        for (i = 0; i < saved.length; i++) {
            if (saleOrderDiamondNormalizeBc(saved[i].barcode) === key) {
                return {
                    kind: 'saved',
                    index: i,
                    issueId: parseInt(saved[i].issue_id != null ? saved[i].issue_id : saved[i].id, 10) || 0,
                    data: saved[i]
                };
            }
        }
        return null;
    }

    function saleOrderDiamondIsOnOrder(bc) {
        return !!saleOrderDiamondFindOnOrder(bc);
    }

    function saleOrderDiamondUpsertPendingLine(line) {
        var key = saleOrderDiamondNormalizeBc(line.barcode);
        var pend = window.__pendingSaleOrderDiamondLines || [];
        var replaced = false;
        var i;
        for (i = 0; i < pend.length; i++) {
            if (saleOrderDiamondNormalizeBc(pend[i].barcode) === key) {
                pend[i] = line;
                replaced = true;
                break;
            }
        }
        if (!replaced) {
            pend.push(line);
        }
        window.__pendingSaleOrderDiamondLines = pend;
    }

    function saleOrderDiamondRemovePendingByBarcode(bc) {
        var key = saleOrderDiamondNormalizeBc(bc);
        window.__pendingSaleOrderDiamondLines = (window.__pendingSaleOrderDiamondLines || []).filter(function (ln) {
            return saleOrderDiamondNormalizeBc(ln.barcode) !== key;
        });
    }

    function saleOrderDiamondDeleteSavedIssue(issueId, orderId) {
        return fetch('ajax/delete-voucher-diamond-issue.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                voucher_kind: getCurrentVoucherKindForDiamond(),
                voucher_id: orderId,
                issue_id: issueId
            })
        }).then(function (r) {
            return r.json();
        });
    }

    /** Remove from pending queue and/or saved allocation (returns Promise). */
    function saleOrderDiamondRemoveFromOrder(bc, orderId) {
        var key = saleOrderDiamondNormalizeBc(bc);
        var savedHit = null;
        var saved = window.__saleOrderDiamondIssueRows || [];
        var j;
        for (j = 0; j < saved.length; j++) {
            if (saleOrderDiamondNormalizeBc(saved[j].barcode) === key) {
                savedHit = saved[j];
                break;
            }
        }
        saleOrderDiamondRemovePendingByBarcode(bc);
        var issueId = savedHit
            ? parseInt(savedHit.issue_id != null ? savedHit.issue_id : savedHit.id, 10) || 0
            : 0;
        if (issueId > 0 && orderId > 0) {
            return saleOrderDiamondDeleteSavedIssue(issueId, orderId).then(function (res) {
                if (res && res.ok) {
                    window.__saleOrderDiamondIssueRows = (window.__saleOrderDiamondIssueRows || []).filter(function (row) {
                        return saleOrderDiamondNormalizeBc(row.barcode) !== key;
                    });
                    return true;
                }
                window.alert((res && res.message) || 'Could not remove diamond allocation.');
                return false;
            });
        }
        return Promise.resolve(true);
    }

    function saleOrderDiamondAfterSyncUi() {
        refreshSaleOrderDiamondModalBanner();
        renderSaleOrderDiamondLinesPanel();
        if (typeof window.refreshSaleOrderDiamondStockTableView === 'function') {
            window.refreshSaleOrderDiamondStockTableView();
        }
        saleOrderDiamondUpdateCheckAllHeader();
    }

    function saleOrderDiamondUpdateCheckAllHeader() {
        var all = document.getElementById('saleOrderDiamondCheckAll');
        if (!all) {
            return;
        }
        var chks = document.querySelectorAll('#saleOrderDiamondStockTableBody .so-diamond-row-chk');
        if (!chks.length) {
            all.checked = false;
            return;
        }
        var every = true;
        chks.forEach(function (c) {
            if (!c.checked) {
                every = false;
            }
        });
        all.checked = every;
    }

    function saleOrderDiamondApplyRowOrderState(tr, it) {
        var onOrder = saleOrderDiamondFindOnOrder(it.barcode);
        var chk = tr.querySelector('.so-diamond-row-chk');
        if (chk) {
            chk.checked = !!onOrder;
        }
        if (!onOrder) {
            return;
        }
        var wEl = tr.querySelector('.so-dim-inp-weight');
        var qEl = tr.querySelector('.so-dim-inp-qty');
        var w = 0;
        var q = 0;
        if (onOrder.kind === 'pending') {
            w = parseFloat(onOrder.data.allocate_weight) || 0;
            q = parseFloat(onOrder.data.allocate_qty) || 0;
        } else {
            w = parseFloat(onOrder.data.weight) || 0;
            q = parseFloat(onOrder.data.qty) || 0;
        }
        if (wEl && w > 0) {
            wEl.value = saleOrderDiamondFmtNum(w) || String(w);
        }
        if (qEl && q > 0) {
            qEl.value = saleOrderDiamondFmtNum(q) || String(q);
        }
    }

    function saleOrderDiamondSyncModalSelection(opts) {
        opts = opts || {};
        var orderId = getCurrentSaleOrderIdForDiamond();
        var allocBtn = document.getElementById('saleOrderDiamondAllocateBtn');

        function finish() {
            if (opts.lockFooter && allocBtn) {
                allocBtn.disabled = false;
            }
            if (typeof opts.onFinally === 'function') {
                opts.onFinally();
            }
        }

        if (opts.lockFooter && allocBtn) {
            allocBtn.disabled = true;
        }

        var toUpsert = [];
        var toRemoveBc = [];

        document.querySelectorAll('#saleOrderDiamondStockTableBody tr[data-stock-id]').forEach(function (tr) {
            var chk = tr.querySelector('.so-diamond-row-chk');
            var bc = tr.getAttribute('data-barcode') || '';
            if (!chk || !bc) {
                return;
            }
            if (chk.checked) {
                var line = saleOrderDiamondCollectLine(tr);
                if (line) {
                    toUpsert.push(line);
                }
            } else if (saleOrderDiamondIsOnOrder(bc)) {
                toRemoveBc.push(bc);
            }
        });

        if (!toUpsert.length && !toRemoveBc.length) {
            alert('Check diamonds to add, or uncheck rows already on the order to remove them.');
            finish();
            return;
        }

        var chain = Promise.resolve();
        toRemoveBc.forEach(function (bc) {
            chain = chain.then(function () {
                return saleOrderDiamondRemoveFromOrder(bc, orderId);
            });
        });

        chain
            .then(function () {
                if (orderId <= 0 || voucherDefersDiamondDeductionToSave()) {
                    toUpsert.forEach(function (line) {
                        saleOrderDiamondUpsertPendingLine(line);
                    });
                    window.__saleOrderDiamondShowSelectedOnly = false;
                    saleOrderDiamondAfterSyncUi();
                    if (opts.closeOnSuccess && typeof window.jQuery !== 'undefined') {
                        window.jQuery('#saleOrderDiamondStockModal').modal('hide');
                    }
                    finish();
                    return;
                }
                if (toUpsert.length) {
                    saleOrderDiamondPostAllocate(toUpsert, {
                        closeOnSuccess: opts.closeOnSuccess,
                        lockFooter: opts.lockFooter,
                        onFinally: opts.onFinally,
                        skipRemovePass: true
                    });
                    return;
                }
                saleOrderDiamondAfterSyncUi();
                if (orderId > 0 && typeof window.refreshSaleOrderDiamondIssuesFromServer === 'function') {
                    window.refreshSaleOrderDiamondIssuesFromServer(orderId);
                }
                if (typeof window.loadSaleOrderDiamondStockRows === 'function') {
                    window.loadSaleOrderDiamondStockRows();
                }
                if (opts.closeOnSuccess && typeof window.jQuery !== 'undefined') {
                    window.jQuery('#saleOrderDiamondStockModal').modal('hide');
                }
                finish();
            })
            .catch(function () {
                window.alert('Network error while updating diamonds.');
                finish();
            });
    }

    function buildSelectedDiamondRowsFromQueues() {
        var byBc = {};
        function put(bc, row) {
            var key = String(bc || '').trim().toLowerCase();
            if (!key) {
                return;
            }
            byBc[key] = row;
        }
        (window.__saleOrderDiamondIssueRows || []).forEach(function (r) {
            put(r.barcode, {
                stock_id: parseInt(r.stock_id, 10) || 0,
                barcode: r.barcode || '',
                product_name: r.product_name || '',
                diamond_category: r.diamond_category || '',
                current_weight: parseFloat(r.weight) || 0,
                current_qty: parseFloat(r.qty) || 0,
                rate: parseFloat(r.rate) || 0,
                active: 'Allocated'
            });
        });
        (window.__pendingSaleOrderDiamondLines || []).forEach(function (ln) {
            put(ln.barcode, {
                stock_id: parseInt(ln.stock_id, 10) || 0,
                barcode: ln.barcode || '',
                product_name: ln.product_name || '',
                diamond_category: ln.diamond_category || '',
                current_weight: parseFloat(ln.allocate_weight) || 0,
                current_qty: parseFloat(ln.allocate_qty) || 0,
                rate: 0,
                active: 'Pending'
            });
        });
        var rows = [];
        Object.keys(byBc).forEach(function (k) {
            rows.push(byBc[k]);
        });
        rows.sort(function (a, b) {
            return String(a.barcode || '').localeCompare(String(b.barcode || ''));
        });
        return rows;
    }

    function applySaleOrderDiamondStockViewFilter(items) {
        if (!window.__saleOrderDiamondShowSelectedOnly) {
            return items;
        }
        var sel = saleOrderDiamondSelectedBarcodeMap();
        if (!Object.keys(sel).length) {
            return items;
        }
        var filtered = (items || []).filter(function (it) {
            var bc = String(it.barcode || '').trim().toLowerCase();
            return !!(bc && sel[bc]);
        });
        if (filtered.length) {
            return filtered;
        }
        return buildSelectedDiamondRowsFromQueues();
    }

    function ensureSaleOrderDiamondShowAllButton() {
        var btn = document.getElementById('saleOrderDiamondShowAllStockBtn');
        if (btn) {
            return btn;
        }
        var anchor = document.getElementById('saleOrderDiamondStockHint');
        if (!anchor || !anchor.parentNode) {
            return null;
        }
        btn = document.createElement('button');
        btn.type = 'button';
        btn.id = 'saleOrderDiamondShowAllStockBtn';
        btn.className = 'btn btn-sm btn-outline-secondary mb-2';
        btn.style.display = 'none';
        btn.textContent = 'Show all stock';
        anchor.parentNode.insertBefore(btn, anchor);
        if (!btn._soDimBound) {
            btn._soDimBound = true;
            btn.addEventListener('click', function () {
                window.__saleOrderDiamondShowSelectedOnly = !window.__saleOrderDiamondShowSelectedOnly;
                if (window.__saleOrderDiamondShowSelectedOnly && !saleOrderDiamondHasQueuedOrAllocatedLines()) {
                    window.__saleOrderDiamondShowSelectedOnly = false;
                }
                refreshSaleOrderDiamondModalBanner();
                refreshSaleOrderDiamondStockTableView();
            });
        }
        return btn;
    }

    function updateSaleOrderDiamondViewToggleUi() {
        var btn = ensureSaleOrderDiamondShowAllButton();
        if (!btn) {
            return;
        }
        var showSel = !!window.__saleOrderDiamondShowSelectedOnly;
        var hasSel = saleOrderDiamondHasQueuedOrAllocatedLines();
        btn.style.display = hasSel ? '' : 'none';
        btn.textContent = showSel ? 'Show all stock' : 'Show selected only';
    }

    function refreshSaleOrderDiamondStockTableView() {
        var items = window._saleOrderDiamondLastItemsFull || window._saleOrderDiamondLastItems || [];
        var view = applySaleOrderDiamondStockViewFilter(items);
        renderSaleOrderDiamondRows(view);
        updateSaleOrderDiamondViewToggleUi();
    }

    function getCurrentSaleOrderIdForDiamond() {
        var cfg = window.AURAGOLD_VOUCHER_DS || {};
        var param = cfg.urlIdParam || 'id';
        var urlParams = new URLSearchParams(window.location.search);
        var urlId = parseInt(urlParams.get(param) || '0', 10);
        var kind = cfg.voucherKind || 'sale_order';

        /** Job work / material issue: ?id= may be sale order id when creating from SO — only trust URL when document id mode. */
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

        /** Job work invoice: voucher_id is tbl_jobwork_invoices.id — never use sale_order_id from URL. */
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

    function refreshSaleOrderDiamondModalBanner() {
        var oid = getCurrentSaleOrderIdForDiamond();
        var kind = getCurrentVoucherKindForDiamond();
        var isSaleOrder = kind === 'sale_order';
        var deferSave = voucherDefersDiamondDeductionToSave();
        var lbl = document.getElementById('saleOrderDiamondStockOrderLabel');
        var hint = document.getElementById('saleOrderDiamondStockHint');
        var allocBtn = document.getElementById('saleOrderDiamondAllocateBtn');
        var pend = (window.__pendingSaleOrderDiamondLines && window.__pendingSaleOrderDiamondLines.length) || 0;
        var saved = (window.__saleOrderDiamondIssueRows && window.__saleOrderDiamondIssueRows.length) || 0;
        if (allocBtn) {
            allocBtn.textContent = deferSave ? 'Add selected to order' : 'Allocate to order';
        }
        if (lbl) {
            if (deferSave) {
                var parts = [];
                if (oid > 0) {
                    parts.push('Document #' + oid);
                }
                if (pend > 0) {
                    parts.push(pend + ' pending — stock deducts when you Save');
                }
                if (saved > 0) {
                    parts.push(saved + ' already allocated');
                }
                lbl.textContent = parts.length
                    ? parts.join(' · ')
                    : 'Select diamonds below — stock deducts when you Save the job work order.';
            } else {
                lbl.textContent = oid
                    ? (isSaleOrder
                        ? ('Sale order id ' + oid + ' — Add deducts stock now; Save still saves the order.')
                        : ('Saved document #' + oid + ' — Add deducts stock now; Save still saves the document.'))
                    : (pend > 0
                        ? pend + ' diamond line(s) queued — stock deducts when you Save.'
                        : 'Pick diamonds and Add / Allocate — stock deducts when you Save (before Save they stay queued).');
            }
        }
        if (hint) {
            if (deferSave) {
                hint.textContent = window.__saleOrderDiamondShowSelectedOnly
                    ? 'Showing selected diamonds only. Click “Show all stock” to pick more from inventory.'
                    : 'Check rows to add (checkbox stays checked). Uncheck and click Add selected to remove from the Diamonds table. Stock deducts when you Save.';
            } else {
                hint.textContent = oid
                    ? ''
                    : (isSaleOrder
                        ? 'No saved order yet: diamonds are held until you click Save on the sale order.'
                        : 'No saved document yet: diamonds are held until you Save.');
            }
        }
        updateSaleOrderDiamondViewToggleUi();
    }

    function saleOrderDiamondEscapeHtml(s) {
        if (s == null || s === '') return '';
        var d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }

    function saleOrderDiamondFmtNum(v) {
        var n = parseFloat(v);
        if (!isFinite(n)) {
            return '';
        }
        var x = Math.round(n * 10000) / 10000;
        return String(x);
    }

    /** Trash outline (Feather-style), muted gold — built with DOM so it survives inside table rows. */
    function saleOrderDiamondCreateTrashSvg() {
        var ns = 'http://www.w3.org/2000/svg';
        var svg = document.createElementNS(ns, 'svg');
        svg.setAttribute('width', '18');
        svg.setAttribute('height', '18');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', '#b8974a');
        svg.setAttribute('stroke-width', '2');
        svg.setAttribute('stroke-linecap', 'round');
        svg.setAttribute('stroke-linejoin', 'round');
        svg.setAttribute('aria-hidden', 'true');
        svg.style.display = 'block';

        function poly(points) {
            var el = document.createElementNS(ns, 'polyline');
            el.setAttribute('points', points);
            svg.appendChild(el);
        }
        function path(d) {
            var el = document.createElementNS(ns, 'path');
            el.setAttribute('d', d);
            svg.appendChild(el);
        }
        function line(x1, y1, x2, y2) {
            var el = document.createElementNS(ns, 'line');
            el.setAttribute('x1', x1);
            el.setAttribute('y1', y1);
            el.setAttribute('x2', x2);
            el.setAttribute('y2', y2);
            svg.appendChild(el);
        }
        poly('3 6 5 6 21 6');
        path('M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2');
        line('10', '11', '10', '17');
        line('14', '11', '14', '17');
        return svg;
    }

    /** Append delete column using DOM (avoid tr.innerHTML — browsers often drop nested buttons/SVG in table rows). */
    function saleOrderDiamondAppendRemoveTd(tr, mode, payload) {
        var td = document.createElement('td');
        td.className = 'text-center align-middle';
        var btnStyle =
            'min-width:38px;min-height:34px;padding:4px 8px;line-height:1;border:1px solid #ddd6e8;border-radius:6px;background:#f5f3ff;cursor:pointer;box-sizing:border-box;';

        if (mode === 'pending') {
            var idx = payload.idx != null ? parseInt(payload.idx, 10) : -1;
            var btnP = document.createElement('button');
            btnP.type = 'button';
            btnP.className = 'btn btn-sm so-diamond-line-remove';
            btnP.setAttribute('title', 'Remove');
            btnP.setAttribute('data-remove-mode', 'pending');
            btnP.setAttribute('data-pending-index', String(idx));
            btnP.setAttribute('aria-label', 'Remove diamond');
            btnP.style.cssText = btnStyle;
            btnP.appendChild(saleOrderDiamondCreateTrashSvg());
            td.appendChild(btnP);
            tr.appendChild(td);
            return;
        }

        var issueId = payload.issueId != null ? parseInt(payload.issueId, 10) : 0;
        if (!(issueId > 0)) {
            tr.appendChild(td);
            return;
        }
        var btnS = document.createElement('button');
        btnS.type = 'button';
        btnS.className = 'btn btn-sm so-diamond-line-remove';
        btnS.setAttribute('title', 'Remove allocation');
        btnS.setAttribute('data-remove-mode', 'saved');
        btnS.setAttribute('data-issue-id', String(issueId));
        btnS.setAttribute('aria-label', 'Remove diamond');
        btnS.style.cssText = btnStyle;
        btnS.appendChild(saleOrderDiamondCreateTrashSvg());
        td.appendChild(btnS);
        tr.appendChild(td);
    }

    function saleOrderDiamondTdAppendText(tr, text, className) {
        var td = document.createElement('td');
        if (className) {
            td.className = className;
        }
        td.textContent = text != null ? String(text) : '';
        tr.appendChild(td);
    }

    function bindSaleOrderDiamondLinesPanelRemove() {
        if (window._soDiamondLineRemoveBound) {
            return;
        }
        window._soDiamondLineRemoveBound = true;
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.so-diamond-line-remove');
            if (!btn || btn.disabled) {
                return;
            }
            var mode = btn.getAttribute('data-remove-mode') || '';
            if (mode === 'pending') {
                var pi = parseInt(btn.getAttribute('data-pending-index') || '-1', 10);
                var pend = Array.isArray(window.__pendingSaleOrderDiamondLines)
                    ? window.__pendingSaleOrderDiamondLines
                    : [];
                if (pi < 0 || pi >= pend.length) {
                    return;
                }
                if (!window.confirm('Remove this diamond from the queue?')) {
                    return;
                }
                pend.splice(pi, 1);
                window.__pendingSaleOrderDiamondLines = pend;
                refreshSaleOrderDiamondModalBanner();
                renderSaleOrderDiamondLinesPanel();
                if (typeof window.refreshSaleOrderDiamondStockTableView === 'function') {
                    window.refreshSaleOrderDiamondStockTableView();
                } else if (typeof window.loadSaleOrderDiamondStockRows === 'function') {
                    window.loadSaleOrderDiamondStockRows();
                }
                return;
            }
            if (mode === 'saved') {
                var issueId = parseInt(btn.getAttribute('data-issue-id') || '0', 10);
                var oid = getCurrentSaleOrderIdForDiamond();
                if (issueId < 1) {
                    return;
                }
                if (!(oid > 0)) {
                    window.alert('Save the document first, then you can remove allocations.');
                    return;
                }
                if (!window.confirm('Remove this diamond allocation and return stock to available?')) {
                    return;
                }
                btn.disabled = true;
                fetch('ajax/delete-voucher-diamond-issue.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        voucher_kind: getCurrentVoucherKindForDiamond(),
                        voucher_id: oid,
                        issue_id: issueId
                    })
                })
                    .then(function (r) {
                        return r.json();
                    })
                    .then(function (res) {
                        btn.disabled = false;
                        if (res && res.ok) {
                            refreshSaleOrderDiamondIssuesFromServer(oid);
                            if (typeof window.loadSaleOrderDiamondStockRows === 'function') {
                                window.loadSaleOrderDiamondStockRows();
                            }
                        } else {
                            window.alert((res && res.message) || 'Could not remove allocation.');
                        }
                    })
                    .catch(function () {
                        btn.disabled = false;
                        window.alert('Network error.');
                    });
            }
        });
    }

    function auragoldDsUsageTablesAlwaysVisible() {
        var cfg = window.AURAGOLD_VOUCHER_DS || {};
        var k = cfg.voucherKind || '';
        return (
            k === 'jobwork_order' ||
            k === 'jobwork_invoice' ||
            k === 'material_issue' ||
            k === 'material_receive'
        );
    }

    function renderSaleOrderDiamondLinesPanel() {
        var card = document.getElementById('saleOrderDiamondLinesCard');
        var tbody = card ? card.querySelector('tbody') : null;
        if (!tbody) {
            tbody = document.getElementById('saleOrderDiamondLinesTbody');
        }
        if (!tbody) {
            return;
        }
        var issuedRef =
            getCurrentVoucherKindForDiamond() === 'material_receive' &&
            Array.isArray(window.__materialIssueReferenceDiamondRows)
                ? window.__materialIssueReferenceDiamondRows
                : [];
        var saved = Array.isArray(window.__saleOrderDiamondIssueRows) ? window.__saleOrderDiamondIssueRows : [];
        var pend = Array.isArray(window.__pendingSaleOrderDiamondLines) ? window.__pendingSaleOrderDiamondLines : [];
        tbody.innerHTML = '';
        if (saved.length === 0 && pend.length === 0 && issuedRef.length === 0) {
            if (card) {
                if (auragoldDsUsageTablesAlwaysVisible()) {
                    card.hidden = false;
                    if (
                        getCurrentVoucherKindForDiamond() === 'material_receive' &&
                        typeof window.mrAppendIssuedEmptyRow === 'function'
                    ) {
                        window.mrAppendIssuedEmptyRow(tbody, 'diamond', 9);
                    } else {
                        var trEmpty = document.createElement('tr');
                        var tdEmpty = document.createElement('td');
                        tdEmpty.colSpan = getCurrentVoucherKindForDiamond() === 'material_receive' ? 9 : 7;
                        tdEmpty.className = 'text-center text-muted py-3';
                        tdEmpty.textContent =
                            getCurrentVoucherKindForDiamond() === 'material_receive'
                                ? 'No diamonds issued on Material Issue for this sale order yet. After issue, lines appear here; tick lines and add receive weight.'
                                : 'No diamonds in this list yet. Click the Diamond icon above to choose barcode / stock.';
                        trEmpty.appendChild(tdEmpty);
                        tbody.appendChild(trEmpty);
                    }
                } else {
                    card.hidden = true;
                }
            }
            if (
                getCurrentVoucherKindForDiamond() === 'material_receive' &&
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
            typeof window.mrRenderIssuedDiamondRows === 'function' &&
            window.mrRenderIssuedDiamondRows(tbody, issuedRef)
        ) {
            /* interactive partial-receive rows */
        } else {
            issuedRef.forEach(function (r) {
                var q = r.qty != null ? r.qty : '';
                var w = r.weight != null ? r.weight : '';
                var tr = document.createElement('tr');
                tr.style.background = '#f8fafc';
                saleOrderDiamondTdAppendText(tr, r.barcode || '');
                saleOrderDiamondTdAppendText(tr, r.product_name || '');
                saleOrderDiamondTdAppendText(tr, r.diamond_category || '');
                saleOrderDiamondTdAppendText(tr, saleOrderDiamondFmtNum(q), 'text-right');
                saleOrderDiamondTdAppendText(tr, saleOrderDiamondFmtNum(w), 'text-right');
                var tdIss = document.createElement('td');
                tdIss.innerHTML =
                    '<span class="badge" style="background:#1e40af;font-size:0.7rem;">Issued</span>';
                tr.appendChild(tdIss);
                var tdEmptyDel = document.createElement('td');
                tr.appendChild(tdEmptyDel);
                tbody.appendChild(tr);
            });
        }
        saved.forEach(function (r) {
            var q = r.qty != null ? r.qty : '';
            var w = r.weight != null ? r.weight : '';
            var issueId = parseInt(r.issue_id != null ? r.issue_id : r.id, 10) || 0;
            var tr = document.createElement('tr');
            saleOrderDiamondTdAppendText(tr, r.barcode || '');
            saleOrderDiamondTdAppendText(tr, r.product_name || '');
            saleOrderDiamondTdAppendText(tr, r.diamond_category || '');
            saleOrderDiamondTdAppendText(tr, saleOrderDiamondFmtNum(q), 'text-right');
            saleOrderDiamondTdAppendText(tr, saleOrderDiamondFmtNum(w), 'text-right');
            var tdBadge = document.createElement('td');
            tdBadge.innerHTML =
                getCurrentVoucherKindForDiamond() === 'material_receive'
                    ? '<span class="mr-issued-badge mr-issued-badge--received">Received</span>'
                    : '<span class="badge badge-success" style="background:#166534;font-size:0.7rem;">Allocated</span>';
            tr.appendChild(tdBadge);
            saleOrderDiamondAppendRemoveTd(tr, 'saved', { issueId: issueId });
            tbody.appendChild(tr);
        });
        pend.forEach(function (r, pidx) {
            var q = r.allocate_qty != null ? r.allocate_qty : '';
            var w = r.allocate_weight != null ? r.allocate_weight : '';
            var prodLabel = r.mr_display_product || r.product_name || '';
            if (!r.mr_display_product && r.mr_issued_product_name && r.product_name && r.mr_issued_product_name !== r.product_name) {
                prodLabel = r.mr_issued_product_name + ' → ' + r.product_name;
            }
            var tr = document.createElement('tr');
            saleOrderDiamondTdAppendText(tr, r.barcode || '');
            saleOrderDiamondTdAppendText(tr, prodLabel);
            saleOrderDiamondTdAppendText(tr, r.diamond_category || '');
            saleOrderDiamondTdAppendText(tr, saleOrderDiamondFmtNum(q), 'text-right');
            saleOrderDiamondTdAppendText(tr, saleOrderDiamondFmtNum(w), 'text-right');
            var tdPend = document.createElement('td');
            tdPend.innerHTML =
                getCurrentVoucherKindForDiamond() === 'material_receive'
                    ? '<span class="mr-issued-badge mr-issued-badge--pending">Pending receive — saves on Save</span>'
                    : '<span class="badge badge-secondary" style="background:#fde047;color:#422006;font-size:0.7rem;">Pending — deducts on Save</span>';
            tr.appendChild(tdPend);
            if (getCurrentVoucherKindForDiamond() !== 'material_receive') {
                saleOrderDiamondAppendRemoveTd(tr, 'pending', { idx: pidx });
            } else {
                var tdEmptyDelP = document.createElement('td');
                tr.appendChild(tdEmptyDelP);
            }
            tbody.appendChild(tr);
        });
        if (
            getCurrentVoucherKindForDiamond() === 'material_receive' &&
            typeof window.mrUpdateIssuedReceiveTabCounts === 'function'
        ) {
            window.mrUpdateIssuedReceiveTabCounts();
        }
    }
        orderId = parseInt(orderId, 10) || 0;
        if (orderId < 1) {
            renderSaleOrderDiamondLinesPanel();
            return;
        }
        fetch(
            'ajax/list-voucher-diamond-issues.php?voucher_kind=' +
                encodeURIComponent(getCurrentVoucherKindForDiamond()) +
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
                    window.__saleOrderDiamondIssueRows = data.items;
                }
                renderSaleOrderDiamondLinesPanel();
            })
            .catch(function () {
                renderSaleOrderDiamondLinesPanel();
            });
    }

    function soDimNormalizeMiddleOrder(arr) {
        var def = SO_DIM_MIDDLE_KEYS_DEFAULT;
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

    function soDimGetMiddleOrder() {
        try {
            var raw = localStorage.getItem(SO_DIM_COL_ORDER_KEY);
            if (raw) {
                var parsed = JSON.parse(raw);
                if (Array.isArray(parsed) && parsed.length) {
                    return soDimNormalizeMiddleOrder(parsed);
                }
            }
        } catch (e) { /* ignore */ }
        return SO_DIM_MIDDLE_KEYS_DEFAULT.slice();
    }

    function soDimSaveMiddleOrder(keys) {
        try {
            localStorage.setItem(SO_DIM_COL_ORDER_KEY, JSON.stringify(soDimNormalizeMiddleOrder(keys)));
        } catch (e) { /* ignore */ }
    }

    function soDimGetWidths() {
        try {
            var raw = localStorage.getItem(SO_DIM_COL_WIDTH_KEY);
            if (raw) return JSON.parse(raw) || {};
        } catch (e) { /* ignore */ }
        return {};
    }

    function soDimSaveWidths(obj) {
        try {
            localStorage.setItem(SO_DIM_COL_WIDTH_KEY, JSON.stringify(obj || {}));
        } catch (e) { /* ignore */ }
    }

    function soDimFullOrder() {
        return ['row_chk'].concat(soDimGetMiddleOrder()).concat(['add_btn']);
    }

    function soDimColCount() {
        return soDimFullOrder().length;
    }

    function saleOrderDiamondCollectLine(tr) {
        var sid = parseInt(tr.getAttribute('data-stock-id'), 10);
        if (!sid) return null;
        var wEl = tr.querySelector('.so-dim-inp-weight');
        var qEl = tr.querySelector('.so-dim-inp-qty');
        var w = wEl ? parseFloat(wEl.value) : 0;
        var q = qEl ? parseFloat(qEl.value) : 0;
        if (!(w > 0) && !(q > 0)) return null;
        return {
            stock_id: sid,
            barcode: tr.getAttribute('data-barcode') || '',
            allocate_qty: q > 0 ? q : 0,
            allocate_weight: w > 0 ? w : 0,
            product_name: tr.getAttribute('data-product-name') || '',
            diamond_category: tr.getAttribute('data-category') || ''
        };
    }

    function saleOrderDiamondPostAllocate(lines, opts) {
        opts = opts || {};
        var orderId = getCurrentSaleOrderIdForDiamond();
        var allocBtn = document.getElementById('saleOrderDiamondAllocateBtn');
        function finishEarly() {
            if (opts.lockFooter && allocBtn) allocBtn.disabled = false;
            if (typeof opts.onFinally === 'function') opts.onFinally();
        }
        if (!lines || !lines.length) {
            alert('Set Weight and/or Quantity on selected rows (values must be greater than zero).');
            finishEarly();
            return;
        }
        if (orderId <= 0 || voucherDefersDiamondDeductionToSave()) {
            lines.forEach(function (line) {
                saleOrderDiamondUpsertPendingLine({
                    stock_id: line.stock_id,
                    barcode: line.barcode || '',
                    allocate_qty: line.allocate_qty,
                    allocate_weight: line.allocate_weight,
                    product_name: line.product_name || '',
                    diamond_category: line.diamond_category || ''
                });
            });
            window.__saleOrderDiamondShowSelectedOnly = false;
            finishEarly();
            saleOrderDiamondAfterSyncUi();
            if (opts.closeOnSuccess && typeof window.jQuery !== 'undefined') {
                window.jQuery('#saleOrderDiamondStockModal').modal('hide');
            }
            return;
        }
        if (opts.lockFooter && allocBtn) allocBtn.disabled = true;
        fetch('ajax/allocate-voucher-diamonds.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                voucher_kind: getCurrentVoucherKindForDiamond(),
                voucher_id: orderId,
                lines: lines
            })
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.ok) {
                    /* Show rows immediately; list refresh replaces with DB truth. */
                    if (!Array.isArray(window.__saleOrderDiamondIssueRows)) {
                        window.__saleOrderDiamondIssueRows = [];
                    }
                    lines.forEach(function (line) {
                        var q = line.allocate_qty != null ? parseFloat(line.allocate_qty) : 0;
                        var w = line.allocate_weight != null ? parseFloat(line.allocate_weight) : 0;
                        window.__saleOrderDiamondIssueRows.push({
                            barcode: line.barcode || '',
                            product_name: line.product_name || '',
                            diamond_category: line.diamond_category || '',
                            qty: isFinite(q) ? q : 0,
                            weight: isFinite(w) ? w : 0
                        });
                    });
                    renderSaleOrderDiamondLinesPanel();
                    refreshSaleOrderDiamondIssuesFromServer(orderId);
                    if (opts.closeOnSuccess && typeof window.jQuery !== 'undefined') {
                        window.jQuery('#saleOrderDiamondStockModal').modal('hide');
                    }
                    alert(res.message || 'Allocated.');
                    if (typeof window.loadSaleOrderDiamondStockRows === 'function') {
                        window.loadSaleOrderDiamondStockRows();
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

    function soDimReadMiddleKeysFromHeader(theadRow) {
        var keys = [];
        theadRow.querySelectorAll('th[data-col-key]').forEach(function (th) {
            var k = th.getAttribute('data-col-key');
            if (k && k !== 'row_chk' && k !== 'add_btn') keys.push(k);
        });
        return soDimNormalizeMiddleOrder(keys);
    }

    function renderSaleOrderDiamondHeader() {
        var thead = document.getElementById('saleOrderDiamondStockThead');
        if (!thead) return;
        var widths = soDimGetWidths();
        var order = soDimFullOrder();
        var tr = document.createElement('tr');

        order.forEach(function (key) {
            var th = document.createElement('th');
            th.setAttribute('data-col-key', key);
            th.className = 'so-dim-th position-relative align-middle';
            var w = widths[key];
            if (w) th.style.width = w + 'px';
            if (key === 'row_chk') {
                th.classList.add('so-dim-th-fixed');
                th.style.width = widths.row_chk || '44px';
                th.innerHTML = '<input type="checkbox" id="saleOrderDiamondCheckAll" title="Select all">';
            } else if (key === 'add_btn') {
                th.classList.add('so-dim-th-fixed');
                th.style.width = widths.add_btn || '76px';
                th.textContent = SO_DIM_LABELS.add_btn;
            } else {
                th.draggable = true;
                th.innerHTML =
                    '<span class="so-dim-drag-hint" title="Drag to reorder column">&#9776;</span>' +
                    '<span class="so-dim-th-label">' + saleOrderDiamondEscapeHtml(SO_DIM_LABELS[key] || key) + '</span>';
                th.addEventListener('dragstart', function (e) {
                    if (e.target.closest('.so-dim-resizer')) {
                        e.preventDefault();
                        return;
                    }
                    e.dataTransfer.setData('text/plain', key);
                    e.dataTransfer.effectAllowed = 'move';
                    th.classList.add('so-dim-dragging');
                });
                th.addEventListener('dragend', function () {
                    th.classList.remove('so-dim-dragging');
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
                    var theadRow = document.querySelector('#saleOrderDiamondStockThead tr');
                    if (!theadRow) return;
                    var cur = soDimReadMiddleKeysFromHeader(theadRow);
                    var i = cur.indexOf(src);
                    var j = cur.indexOf(dst);
                    if (i < 0 || j < 0) return;
                    var t = cur[i];
                    cur[i] = cur[j];
                    cur[j] = t;
                    soDimSaveMiddleOrder(cur);
                    renderSaleOrderDiamondHeader();
                    if (window._saleOrderDiamondLastItems) {
                        renderSaleOrderDiamondRows(window._saleOrderDiamondLastItems);
                    }
                });
            }
            var rz = document.createElement('span');
            rz.className = 'so-dim-resizer';
            rz.title = 'Resize column';
            rz.addEventListener('mousedown', function (e) {
                e.stopPropagation();
                e.preventDefault();
                _saleOrderDiamondResizeState = {
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

    function soDimOnResizeMove(e) {
        if (!_saleOrderDiamondResizeState) return;
        var st = _saleOrderDiamondResizeState;
        var dx = e.pageX - st.startX;
        var nw = Math.max(36, st.startW + dx);
        st.th.style.width = nw + 'px';
        st.th.style.minWidth = nw + 'px';
    }

    function soDimOnResizeEnd() {
        if (!_saleOrderDiamondResizeState) return;
        var st = _saleOrderDiamondResizeState;
        var wmap = soDimGetWidths();
        wmap[st.key] = st.th.offsetWidth;
        soDimSaveWidths(wmap);
        _saleOrderDiamondResizeState = null;
    }

    function renderSaleOrderDiamondRows(items) {
        var tbody = document.getElementById('saleOrderDiamondStockTableBody');
        if (!tbody) return;
        var order = soDimFullOrder();
        tbody.innerHTML = '';

        if (!items || !items.length) {
            var tr0 = document.createElement('tr');
            tr0.innerHTML = '<td colspan="' + soDimColCount() + '" class="text-center text-muted py-4">No diamond stock found.</td>';
            tbody.appendChild(tr0);
            return;
        }

        items.forEach(function (it, idx) {
            var tr = document.createElement('tr');
            var sid = parseInt(it.stock_id, 10) || 0;
            tr.setAttribute('data-stock-id', String(sid));
            tr.setAttribute('data-barcode', String(it.barcode || ''));
            tr.setAttribute('data-product-name', String(it.product_name || ''));
            tr.setAttribute('data-category', String(it.diamond_category || ''));

            var availWt = parseFloat(it.current_weight) || 0;
            var availQty = parseFloat(it.current_qty) || 0;
            var rate = parseFloat(it.rate) || 0;

            order.forEach(function (key) {
                var td = document.createElement('td');
                td.className = 'align-middle';
                td.setAttribute('data-col-key', key);
                if (key === 'row_chk') {
                    td.innerHTML = '<input type="checkbox" class="so-diamond-row-chk" id="soDimChk_' + sid + '_' + idx + '">';
                } else if (key === 'weight') {
                    td.className += ' text-right';
                    td.innerHTML =
                        '<input type="number" step="0.001" min="0" class="form-control form-control-sm so-dim-inp-weight text-right" value="' +
                        saleOrderDiamondEscapeHtml(availWt.toFixed(3)) +
                        '">';
                } else if (key === 'quantity') {
                    td.className += ' text-right';
                    td.innerHTML =
                        '<input type="number" step="0.01" min="0" class="form-control form-control-sm so-dim-inp-qty text-right" value="' +
                        saleOrderDiamondEscapeHtml(availQty.toFixed(2)) +
                        '">';
                } else if (key === 'rate') {
                    td.className += ' text-right';
                    td.textContent = rate.toFixed(2);
                } else if (key === 'add_btn') {
                    td.innerHTML =
                        '<button type="button" class="btn btn-sm text-white so-diamond-add-btn px-2 py-0" style="background:#5b21b6;font-size:11px;">Add</button>';
                } else if (key === 'item_code') {
                    td.textContent = it.article || '';
                } else if (key === 'barcode_no') {
                    td.textContent = it.barcode || '';
                } else if (key === 'product') {
                    td.textContent = it.product_name || '';
                } else if (key === 'diamond_category') {
                    td.textContent = it.diamond_category || '';
                } else if (key === 'style') {
                    td.textContent = it.style || '';
                } else if (key === 'calculation_type') {
                    td.textContent = it.calculation_type || '';
                } else if (key === 'diamond_carat') {
                    td.className += ' text-right';
                    td.textContent = it.diamond_carat != null ? String(it.diamond_carat) : '';
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
            saleOrderDiamondApplyRowOrderState(tr, it);
            tbody.appendChild(tr);
        });
        saleOrderDiamondUpdateCheckAllHeader();
    }

    function loadSaleOrderDiamondStockRows() {
        var tbody = document.getElementById('saleOrderDiamondStockTableBody');
        var q = document.getElementById('saleOrderDiamondStockSearch');
        var term = q ? String(q.value || '').trim() : '';
        if (!tbody) return;
        tbody.innerHTML =
            '<tr><td colspan="' + soDimColCount() + '" class="text-center text-muted py-3">Loading…</td></tr>';
        var url = 'ajax/list-diamond-stock-for-sale-order.php?_=' + (Date.now ? Date.now() : 0);
        if (term) url += '&search=' + encodeURIComponent(term);
        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok || !data.items) {
                    tbody.innerHTML =
                        '<tr><td colspan="' + soDimColCount() + '" class="text-center text-danger py-3">' +
                        saleOrderDiamondEscapeHtml(data.message || 'Could not load stock') +
                        '</td></tr>';
                    window._saleOrderDiamondLastItems = [];
                    return;
                }
                var items = (data.items || []).filter(function (it) {
                    var w = parseFloat(it.current_weight);
                    return isFinite(w) && w > 0.00001;
                });
                window._saleOrderDiamondLastItemsFull = items;
                window._saleOrderDiamondLastItems = items;
                renderSaleOrderDiamondHeader();
                refreshSaleOrderDiamondStockTableView();
            })
            .catch(function () {
                tbody.innerHTML =
                    '<tr><td colspan="' + soDimColCount() + '" class="text-center text-danger py-3">Network error loading stock.</td></tr>';
                window._saleOrderDiamondLastItems = [];
            });
    }

    function openSaleOrderDiamondStockModal() {
        if (typeof window.jQuery === 'undefined' || !window.jQuery.fn.modal) {
            alert('Bootstrap modal not available.');
            return;
        }
        /* Always open with full stock so more diamonds can be added anytime. */
        window.__saleOrderDiamondShowSelectedOnly = false;
        refreshSaleOrderDiamondModalBanner();
        var tEx = document.getElementById('saleOrderDiamondTabExisting');
        var tLo = document.getElementById('saleOrderDiamondTabLoose');
        var pEx = document.getElementById('saleOrderDiamondPanelExisting');
        var pLo = document.getElementById('saleOrderDiamondPanelLoose');
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
        window.jQuery('#saleOrderDiamondStockModal').modal('show');
        loadSaleOrderDiamondStockRows();
    }

    function bindSaleOrderDiamondStockModalUi() {
        var searchEl = document.getElementById('saleOrderDiamondStockSearch');
        var tmr = null;
        if (searchEl && !searchEl._soDimBound) {
            searchEl._soDimBound = true;
            searchEl.addEventListener('input', function () {
                clearTimeout(tmr);
                tmr = setTimeout(function () {
                    window.__saleOrderDiamondShowSelectedOnly = false;
                    loadSaleOrderDiamondStockRows();
                }, 320);
            });
        }

        ensureSaleOrderDiamondShowAllButton();

        var tEx = document.getElementById('saleOrderDiamondTabExisting');
        var tLo = document.getElementById('saleOrderDiamondTabLoose');
        var pEx = document.getElementById('saleOrderDiamondPanelExisting');
        var pLo = document.getElementById('saleOrderDiamondPanelLoose');
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
        if (tEx && !tEx._soDimBound) {
            tEx._soDimBound = true;
            tEx.addEventListener('click', activateExisting);
        }
        if (tLo && !tLo._soDimBound) {
            tLo._soDimBound = true;
            tLo.addEventListener('click', activateLoose);
        }

        var modalEl = document.getElementById('saleOrderDiamondStockModal');
        if (modalEl && !modalEl._soDimDeleg) {
            modalEl._soDimDeleg = true;
            modalEl.addEventListener('change', function (e) {
                if (e.target && e.target.id === 'saleOrderDiamondCheckAll') {
                    var on = e.target.checked;
                    document.querySelectorAll('#saleOrderDiamondStockTableBody .so-diamond-row-chk').forEach(function (c) {
                        c.checked = on;
                    });
                }
            });
            modalEl.addEventListener('click', function (e) {
                var btn = e.target.closest('.so-diamond-add-btn');
                if (!btn) return;
                var tr = btn.closest('tr');
                if (!tr) return;
                var chk = tr.querySelector('.so-diamond-row-chk');
                var bc = tr.getAttribute('data-barcode') || '';
                if (chk && !chk.checked && saleOrderDiamondIsOnOrder(bc)) {
                    btn.disabled = true;
                    saleOrderDiamondRemoveFromOrder(bc, getCurrentSaleOrderIdForDiamond())
                        .then(function (ok) {
                            if (ok) {
                                saleOrderDiamondAfterSyncUi();
                            }
                        })
                        .finally(function () {
                            btn.disabled = false;
                        });
                    return;
                }
                var line = saleOrderDiamondCollectLine(tr);
                if (!line) {
                    alert('Enter Weight and/or Quantity greater than zero.');
                    return;
                }
                btn.disabled = true;
                saleOrderDiamondPostAllocate([line], {
                    closeOnSuccess: false,
                    lockFooter: false,
                    onFinally: function () {
                        btn.disabled = false;
                        if (chk) {
                            chk.checked = true;
                        }
                    }
                });
            });
        }

        var allocBtn = document.getElementById('saleOrderDiamondAllocateBtn');
        if (allocBtn && !allocBtn._soDimBound) {
            allocBtn._soDimBound = true;
            allocBtn.addEventListener('click', function () {
                saleOrderDiamondSyncModalSelection({ closeOnSuccess: true, lockFooter: true });
            });
        }

        if (!window._soDimResizeDocBound) {
            window._soDimResizeDocBound = true;
            document.addEventListener('mousemove', soDimOnResizeMove);
            document.addEventListener('mouseup', soDimOnResizeEnd);
        }
    }

    window.initSaleOrderDiamondModal = function () {
        bindSaleOrderDiamondStockModalUi();
        bindSaleOrderDiamondLinesPanelRemove();
        renderSaleOrderDiamondLinesPanel();
        var cfg = window.AURAGOLD_VOUCHER_DS || {};
        var vid = parseInt(String(window.__auragoldVoucherDbId || '0'), 10) || 0;
        if (
            vid > 0 &&
            ((cfg.voucherKind || '') === 'jobwork_order' ||
                (cfg.voucherKind || '') === 'jobwork_invoice' ||
                (cfg.voucherKind || '') === 'material_issue' ||
                (cfg.voucherKind || '') === 'material_receive')
        ) {
            refreshSaleOrderDiamondIssuesFromServer(vid);
        }
    };
    window.openSaleOrderDiamondStockModal = openSaleOrderDiamondStockModal;
    window.loadSaleOrderDiamondStockRows = loadSaleOrderDiamondStockRows;
    window.refreshSaleOrderDiamondStockTableView = refreshSaleOrderDiamondStockTableView;
    window.refreshSaleOrderDiamondModalBanner = refreshSaleOrderDiamondModalBanner;
    window.renderSaleOrderDiamondLinesPanel = renderSaleOrderDiamondLinesPanel;
    window.refreshSaleOrderDiamondIssuesFromServer = refreshSaleOrderDiamondIssuesFromServer;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.initSaleOrderDiamondModal);
    } else {
        window.initSaleOrderDiamondModal();
    }
})();
