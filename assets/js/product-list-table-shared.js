/**
 * Shared Product List grid: row cell HTML, column visibility, drag-reorder + DB prefs.
 * Requires window.PRODUCT_LIST_TABLE_BOOT (injected by includes/product-list-table.php).
 * Load after jQuery. escapeHtml from product-modal-add-item-common.js if present.
 */
(function () {
    'use strict';

    function plEscapeHtml(text) {
        if (text === null || text === undefined) return '';
        if (typeof window.escapeHtml === 'function') return window.escapeHtml(text);
        var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, function (m) { return map[m]; });
    }

    function getBoot() {
        return window.PRODUCT_LIST_TABLE_BOOT || null;
    }

    function productListTableEmptyColspan() {
        var b = getBoot();
        var n = b && typeof b.colCount === 'number' ? b.colCount : 73;
        return n + 1;
    }
    window.productListTableEmptyColspan = productListTableEmptyColspan;
    window.saleInvoiceProductListEmptyColspan = productListTableEmptyColspan;

    function getProductListRowCells(data, opts) {
        opts = opts || {};
        var groupImage = opts.groupImage || '';
        function val(k) { return data[k] != null && data[k] !== '' ? data[k] : ''; }
        function fmtNum(v, d) { d = d === undefined ? 2 : d; return (parseFloat(v) || 0).toFixed(d); }
        function cell(col, content, style, cls) {
            style = style || 'text-align: right; color: #11294b;';
            if (col === 'product') { style = 'cursor: pointer; color: #11294b;'; cls = (cls || '') + ' product-select-cell'; }
            if (col === 'photo') style = 'text-align: center; vertical-align: middle;';
            if (col === 'barcode') style = 'text-align: center;';
            return '<td data-column="' + col + '"' + (cls ? ' class="' + cls.trim() + '"' : '') + ' style="' + style + '">' + content + '</td>';
        }
        var cells = [];
        var colToKey = { 'id': 'product_id', 'product': 'product_name', 'short-code': 'short_code', 'voucher-type': 'voucher_type', 'design-no': 'design_no', 'pkt-wt': 'pkt_wt', 'pkt-less-wt': 'pkt_less_wt', 'requested-purity': 'requested_purity', 'gross-wt': 'gross_wt', 'less-wt': 'less_wt', 'gold-loss1': 'gold_loss1', 'gold-loss2': 'gold_loss2', 'setting-charge': 'setting_charge', 'net-wt': 'net_wt', 'purity-wt': 'pure_wt', 'wastage-per': 'wastage_per', 'wastage-wt': 'wastage_wt', 'final-wt': 'final_wt', 'alloy-wt': 'alloy_wt', 'metal-value': 'metal_value', 'metal-cost': 'metal_cost', 'metal-rate': 'metal_rate', 'metal-loss-value': 'metal_loss_value', 'discount-type': 'discount_type', 'discount-per': 'discount_per', 'discount-amount': 'discount_amount', 'making-type': 'making_type', 'making-rate': 'making_rate', 'making-amount': 'making_amount', 'making-cost': 'making_cost', 'making-discount-amt': 'making_discount_amt', 'making-actual-value': 'making_actual_value', 'min-price': 'min_price', 'minimum': 'minimum', 'minimum-code': 'minimum_code', 'stone-charge-type': 'stone_charge_type', 'stone-weight': 'stone_weight', 'stone-rate': 'stone_rate', 'stone-amount': 'stone_amount', 'stone-cost': 'stone_cost', 'diamond-amount': 'diamond_amount', 'diamond-carat': 'diamond_carat', 'carat': 'carat_name', 'purchase-amount': 'purchase_amount', 'sale-amount': 'sale_amount', 'sale-amount-with': 'sale_amount_with', 'net-amt': 'net_amt', 'tax-type': 'tax_type', 'tax-percent': 'tax_percent', 'other-charge-type': 'other_charge_type', 'other-weight': 'other_weight', 'other-rate': 'other_rate', 'other-info': 'other_info', 'other-amount': 'other_amount', 'hallmark-amount': 'hallmark_amount', 'hallmark-rate': 'hallmark_rate', 'net-amt-tax': 'net_amt_tax', 'calculation': 'calculation_type', 'location': 'location_name', 'metal-qty': 'metal_qty', 'metal-weight': 'metal_weight' };
        var PRODUCT_LIST_COLUMNS = (getBoot() && getBoot().columnKeys) ? getBoot().columnKeys : (window.PRODUCT_LIST_COLUMNS || []);
        var rawOrder = (typeof window.getProductTableColumnOrder === 'function') ? window.getProductTableColumnOrder() : null;
        var colOrder = (rawOrder && rawOrder.length > 0) ? rawOrder : PRODUCT_LIST_COLUMNS;
        for (var i = 0; i < colOrder.length; i++) {
            var col = colOrder[i];
            var key = colToKey[col] || col.replace(/-/g, '_');
            var content = '';
            if (col === 'photo') {
                var noImg = (getBoot() && getBoot().noImageSrc) ? String(getBoot().noImageSrc) : 'no_image.jpg';
                var giRaw = groupImage != null ? String(groupImage).trim() : '';
                if (!giRaw || giRaw === '—' || giRaw === '-' || giRaw === '–') {
                    giRaw = '';
                }
                if (giRaw && !/^https?:\/\//i.test(giRaw) && giRaw.indexOf('/') === -1 && giRaw.indexOf('.') === -1 && giRaw.indexOf('data:') !== 0) {
                    giRaw = '';
                }
                var src = giRaw || noImg;
                var onerr = ' onerror="this.onerror=null;this.src=' + JSON.stringify(noImg) + '"';
                content = '<img src="' + plEscapeHtml(src) + '" alt="" style="max-width: 50px; max-height: 50px; object-fit: contain;"' + (giRaw ? onerr : '') + '>';
            } else if (col === 'id') {
                content = '<span style="font-size: 0.75rem; color: #11294b;">' + plEscapeHtml(data.product_id != null && data.product_id !== '' ? String(data.product_id) : '') + '</span>';
            } else if (col === 'product') {
                content = '<a href="javascript:void(0)" style="color: #11294b; text-decoration: underline;">' + plEscapeHtml(val(key)) + '</a>';
            } else if (col === 'quantity' || col === 'gross-wt' || col === 'less-wt' || col === 'purity' || col === 'final-wt') {
                var v = col === 'quantity' ? fmtNum(data[key], 2) : fmtNum(data[key], 3);
                var f = col === 'quantity' ? 'quantity' : (col === 'gross-wt' ? 'gross_wt' : col === 'less-wt' ? 'less_wt' : col === 'purity' ? 'purity' : 'final_wt');
                content = '<input type="number" class="form-control form-control-sm editable-field" data-field="' + f + '" value="' + v + '" step="' + (col === 'quantity' ? '0.01' : '0.001') + '" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">';
            } else if (col === 'design-no') {
                content = '<input type="text" class="form-control form-control-sm editable-field" data-field="design_no" value="' + plEscapeHtml(val(key)) + '" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">';
            } else if (col === 'making-amount') {
                content = '<input type="number" class="form-control form-control-sm editable-field" data-field="making" value="' + fmtNum(data[key] || data.making_amount, 2) + '" step="0.01" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">';
            } else if (col === 'stone-amount' || col === 'other-amount' || col === 'diamond-amount') {
                var field = col === 'stone-amount' ? 'stone_charges' : col === 'other-amount' ? 'other_charges' : 'diamond_value';
                content = '<input type="number" class="form-control form-control-sm editable-field" data-field="' + field + '" value="' + fmtNum(data[key] || data.stone_amount || data.other_amount || data.diamond_amount, 2) + '" step="0.01" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">';
            } else if (col === 'tax') {
                content = '<input type="number" class="form-control form-control-sm editable-field" data-field="tax" value="' + fmtNum(data[key], 2) + '" step="0.01" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">';
            } else if (col === 'amount') {
                content = '<span style="font-weight: 600;">' + plEscapeHtml(fmtNum(data[key], 2)) + '</span>';
            } else if (col === 'metal-qty') {
                content = '<span style="font-size: 0.75rem; color: #11294b;">' + plEscapeHtml(fmtNum(data.metal_qty != null && data.metal_qty !== '' ? data.metal_qty : 1, 2)) + '</span>';
            } else if (col === 'metal-weight') {
                content = '<span style="font-size: 0.75rem; color: #11294b;">' + plEscapeHtml(fmtNum(data.metal_weight, 3)) + '</span>';
            } else if (col === 'barcode') {
                content = '<span style="font-size: 0.75rem; color: #11294b; font-weight: 500;">' + plEscapeHtml(data[key] != null ? String(data[key]) : '') + '</span>';
            } else if (col === 'diamond-carat') {
                // Diamond carat (ct) — never metal karat label (22K / 24K)
                var diaCt = data.diamond_carat;
                if (diaCt == null || diaCt === '') diaCt = data.stone_weight;
                content = fmtNum(diaCt, 3);
            } else if (col === 'carat') {
                // Metal karat label (24K / 22K)
                var caratDisp = data.carat_name || data.carat || data.carat_id || '';
                content = plEscapeHtml(caratDisp != null && caratDisp !== '' ? String(caratDisp) : '');
            } else if (col === 'location') {
                var locDisp = data.location_name || data.location || data.location_id || '';
                content = plEscapeHtml(locDisp != null && locDisp !== '' ? String(locDisp) : '');
            } else {
                var isWeight = (key.indexOf('_wt') !== -1 || key === 'pkt_wt' || key === 'pkt_less_wt' || key === 'wastage_wt' || key === 'alloy_wt');
                var isNumeric = (key.indexOf('amount') !== -1 || key.indexOf('value') !== -1 || key.indexOf('rate') !== -1 || key === 'quantity' || key === 'tax' || key === 'purity' || key === 'requested_purity' || key === 'requested' || key === 'tax_percent' || key === 'wastage_per' || key === 'metal_loss_value' || key === 'making_discount_amt' || key === 'making_actual_value' || key === 'minimum' || key === 'stone_weight');
                if (isWeight) content = fmtNum(data[key], 3);
                else if (isNumeric) content = fmtNum(data[key], 2);
                else content = plEscapeHtml(data[key] != null && data[key] !== '' ? String(data[key]) : '');
            }
            var grp = (typeof window.PRODUCT_LIST_COLUMN_GROUP !== 'undefined' && window.PRODUCT_LIST_COLUMN_GROUP[col]) ? window.PRODUCT_LIST_COLUMN_GROUP[col] : '';
            var prevColKey = i > 0 ? colOrder[i - 1] : null;
            var prevGrp = prevColKey && window.PRODUCT_LIST_COLUMN_GROUP ? window.PRODUCT_LIST_COLUMN_GROUP[prevColKey] : null;
            var groupCls = (grp && grp !== prevGrp) ? 'product-col-group-start' : '';
            cells.push(cell(col, content, undefined, groupCls));
        }
        return cells;
    }
    window.getProductListRowCells = getProductListRowCells;

    function isDiamondCompositeComponentCategory(cat) {
        var c = String(cat || '').trim().toLowerCase();
        return c === 'diamonds' || c === 'gemstones' || c === 'gemstone';
    }

    function findJewelleryCompositeAnchor(modalRowsData) {
        if (!modalRowsData || !modalRowsData.length) return null;
        var jewellery = null;
        var hasComponent = false;
        for (var i = 0; i < modalRowsData.length; i++) {
            var c = String((modalRowsData[i] && modalRowsData[i].category) || '').trim();
            if (c.toLowerCase() === 'jewellery') jewellery = modalRowsData[i];
            if (isDiamondCompositeComponentCategory(c)) hasComponent = true;
        }
        return (jewellery && hasComponent) ? jewellery : null;
    }

    /**
     * Jewellery + Diamonds/GemStones composite: invoice/product-list line must use the Jewellery
     * row values. Diamonds already roll into Jewellery (D.Weight / Carat / amounts); summing
     * component rows double-counts gross/less/net/qty (e.g. Less Wt = jewellery D.Wt + each diamond D.Wt).
     *
     * @param {object} merged
     * @param {Array<object>} modalRowsData
     * @param {object} [opts]
     * @param {function(object):number} [opts.quantityFromLine]
     * @param {boolean} [opts.saleMode]  when true, also set sale_amount / sale_amount_with from jewellery net
     * @returns {boolean}
     */
    function applyDiamondJewelleryCompositeRollup(merged, modalRowsData, opts) {
        opts = opts || {};
        var jewelleryRow = findJewelleryCompositeAnchor(modalRowsData);
        if (!jewelleryRow || !merged) return false;

        var num = function (v) {
            var n = parseFloat(v);
            return isNaN(n) ? 0 : n;
        };
        var copyNum = function (key) {
            if (jewelleryRow[key] == null || jewelleryRow[key] === '') return;
            merged[key] = num(jewelleryRow[key]);
        };

        var qtyFromLine = typeof opts.quantityFromLine === 'function' ? opts.quantityFromLine : null;
        if (qtyFromLine) {
            var q = qtyFromLine(jewelleryRow);
            merged.quantity = q > 0 ? q : num(jewelleryRow.quantity);
        } else {
            var mq = num(jewelleryRow.metal_qty);
            merged.quantity = mq > 0 ? mq : num(jewelleryRow.quantity);
        }

        // Weights / qty — jewellery already includes rolled-up D.Weight & diamond carat
        ['metal_qty', 'metal_weight', 'gross_wt', 'less_wt', 'net_wt', 'pure_wt', 'final_wt',
            'stone_weight', 'pkt_wt', 'pkt_less_wt', 'rate', 'metal_rate', 'purity',
            'gold_loss1', 'gold_loss2', 'metal_loss_value', 'wastage_wt', 'wastage_per', 'alloy_wt',
            'setting_charge', 'hallmark_amount', 'hallmark_rate', 'reverse', 'making_rate',
            'discount_per', 'discount_amount', 'tax_percent'].forEach(copyNum);
        if (!(merged.metal_qty > 0)) merged.metal_qty = merged.quantity || 1;

        // Modal stone-weight = Diamond Carat; less-wt = D.Weight (total diamond wt)
        var diaCarat = num(jewelleryRow.diamond_carat);
        if (!(diaCarat > 0)) diaCarat = num(jewelleryRow.stone_weight);
        merged.diamond_carat = diaCarat;
        if (!(num(merged.stone_weight) > 0) && diaCarat > 0) merged.stone_weight = diaCarat;
        merged.diamond_wt = num(jewelleryRow.diamond_wt);
        if (!(merged.diamond_wt > 0)) merged.diamond_wt = num(jewelleryRow.less_wt);

        var jNetAmt = num(jewelleryRow.net_amt);
        var jNetAmtTax = num(jewelleryRow.net_amt_tax);
        if (jNetAmtTax <= 0) jNetAmtTax = jNetAmt;
        merged.net_amt = jNetAmt;
        merged.net_amt_tax = jNetAmtTax;
        merged.amount = jNetAmt;
        merged.purchase_amount = jNetAmt;
        merged.purchase_amount_with = jNetAmtTax;
        if (opts.saleMode) {
            merged.sale_amount = jNetAmt;
            merged.sale_amount_with = jNetAmtTax;
        }

        ['metal_value', 'other_amount', 'making_amount', 'discount', 'stone_amount', 'diamond_amount', 'tax',
            'fc_amount', 'mark_up_amount', 'mark_up_per', 'stone_cost', 'stone_rate'].forEach(copyNum);

        merged.carat = jewelleryRow.carat_id != null && jewelleryRow.carat_id !== ''
            ? jewelleryRow.carat_id
            : (jewelleryRow.carat != null && jewelleryRow.carat !== '' ? jewelleryRow.carat : merged.carat);
        merged.carat_id = merged.carat;
        merged.carat_name = jewelleryRow.carat_name || jewelleryRow.carat || merged.carat_name || '';
        if (jewelleryRow.calculation_type) merged.calculation_type = jewelleryRow.calculation_type;
        if (jewelleryRow.making_type) merged.making_type = jewelleryRow.making_type;
        if (jewelleryRow.tax_type) merged.tax_type = jewelleryRow.tax_type;
        if (jewelleryRow.category) merged.category = jewelleryRow.category;
        if (jewelleryRow.product_id) merged.product_id = jewelleryRow.product_id;
        if (jewelleryRow.characteristic_id) merged.characteristic_id = jewelleryRow.characteristic_id;
        if (jewelleryRow.location_id != null && jewelleryRow.location_id !== '') {
            merged.location = jewelleryRow.location_id;
            merged.location_id = jewelleryRow.location_id;
        }
        if (jewelleryRow.location_name) merged.location_name = jewelleryRow.location_name;
        if (jewelleryRow.design_no != null) merged.design_no = jewelleryRow.design_no;

        return true;
    }
    window.applyDiamondJewelleryCompositeRollup = applyDiamondJewelleryCompositeRollup;
    window.findJewelleryCompositeAnchor = findJewelleryCompositeAnchor;

    /**
     * Rebuild a Product List row from Product Selection modal line(s).
     * Used by Edit → ADD so Carat and all other columns write back (not a partial patch).
     *
     * @param {string} rowId
     * @param {Array<object>} modalRowsData  from getModalRowDataFromRow
     * @param {object} [opts]
     * @param {function(object):number} [opts.quantityFromLine]
     * @returns {boolean}
     */
    function rebuildProductListRowFromModalRows(rowId, modalRowsData, opts) {
        opts = opts || {};
        var row = document.getElementById(rowId);
        if (!row || !modalRowsData || !modalRowsData.length) return false;

        var qtyFromLine = typeof opts.quantityFromLine === 'function'
            ? opts.quantityFromLine
            : function (d) {
                var mq = parseFloat(d && d.metal_qty);
                if (!isNaN(mq) && mq > 0) return mq;
                return parseFloat(d && d.quantity) || 0;
            };

        var first = modalRowsData[0] || {};
        var productNames = modalRowsData.map(function (d) { return (d.product_name || '').trim(); }).filter(Boolean);
        var merged = Object.assign({}, first);
        merged.product_name = productNames.length ? productNames.join(' + ') : ((modalRowsData.length > 1) ? (modalRowsData.length + ' items') : (first.product_name || ''));
        merged.barcode = (first.barcode || '').trim();
        merged.quantity = 0;
        merged.gross_wt = 0;
        merged.less_wt = 0;
        merged.net_wt = 0;
        merged.pure_wt = 0;
        merged.final_wt = 0;
        merged.amount = 0;
        merged.discount = 0;
        merged.making_amount = 0;
        merged.stone_amount = 0;
        merged.other_amount = 0;
        merged.diamond_amount = 0;
        merged.tax = 0;
        merged.net_amt = 0;
        merged.net_amt_tax = 0;
        merged.metal_value = 0;
        merged.metal_qty = 0;
        merged.metal_weight = 0;
        merged.purchase_amount = 0;
        merged.sale_amount = 0;
        merged.sale_amount_with = 0;
        merged.reverse = 0;
        merged.hallmark_amount = 0;
        merged.gold_loss1 = 0;
        merged.gold_loss2 = 0;
        merged.wastage_wt = 0;
        merged.stone_weight = 0;

        modalRowsData.forEach(function (d) {
            merged.quantity += qtyFromLine(d);
            merged.gross_wt += parseFloat(d.gross_wt) || 0;
            merged.less_wt += parseFloat(d.less_wt) || 0;
            merged.net_wt += parseFloat(d.net_wt) || 0;
            merged.pure_wt += parseFloat(d.pure_wt) || 0;
            merged.final_wt += parseFloat(d.final_wt) || 0;
            merged.amount += parseFloat(d.amount) || 0;
            merged.discount += parseFloat(d.discount) || 0;
            merged.making_amount += parseFloat(d.making_amount) || 0;
            merged.stone_amount += parseFloat(d.stone_amount) || 0;
            merged.other_amount += parseFloat(d.other_amount) || 0;
            merged.diamond_amount += parseFloat(d.diamond_amount) || 0;
            merged.tax += parseFloat(d.tax) || 0;
            merged.net_amt += parseFloat(d.net_amt) || 0;
            merged.net_amt_tax += parseFloat(d.net_amt_tax) || 0;
            merged.metal_value += parseFloat(d.metal_value) || 0;
            merged.metal_qty += parseFloat(d.metal_qty) || 0;
            merged.metal_weight += parseFloat(d.metal_weight) || 0;
            merged.purchase_amount += parseFloat(d.purchase_amount) || 0;
            merged.sale_amount += parseFloat(d.sale_amount) || 0;
            merged.sale_amount_with += parseFloat(d.sale_amount_with) || 0;
            merged.reverse += parseFloat(d.reverse) || 0;
            merged.hallmark_amount += parseFloat(d.hallmark_amount) || 0;
            merged.gold_loss1 += parseFloat(d.gold_loss1) || 0;
            merged.gold_loss2 += parseFloat(d.gold_loss2) || 0;
            merged.wastage_wt += parseFloat(d.wastage_wt) || 0;
            merged.stone_weight += parseFloat(d.stone_weight) || 0;
        });

        // Keep non-summed fields from first line (carat, location, rates, types, etc.)
        merged.purity = parseFloat(first.purity) || 0;
        merged.rate = parseFloat(first.rate) || 0;
        merged.metal_rate = parseFloat(first.metal_rate) || 0;
        merged.carat = first.carat_id != null && first.carat_id !== '' ? first.carat_id : (first.carat || '');
        merged.carat_id = merged.carat;
        merged.carat_name = first.carat_name || first.carat || '';
        merged.location = first.location_id != null && first.location_id !== '' ? first.location_id : (first.location || '');
        merged.location_id = merged.location;
        merged.location_name = first.location_name || first.location || '';
        merged.design_no = first.design_no || '';
        merged.calculation_type = first.calculation_type || 'Rate X Gross Wt';
        merged.making_type = first.making_type || 'Fix';
        merged.making_rate = parseFloat(first.making_rate) || 0;
        merged.wastage_per = parseFloat(first.wastage_per) || 0;
        merged.tax_type = first.tax_type || '';
        merged.tax_percent = parseFloat(first.tax_percent) || 0;
        merged.category = first.category || '';
        if (!(merged.metal_qty > 0)) merged.metal_qty = qtyFromLine(first) || 1;

        // Diamond jewellery rollup: use Jewellery line for weights + amounts (avoid double-count)
        applyDiamondJewelleryCompositeRollup(merged, modalRowsData, {
            quantityFromLine: qtyFromLine,
            saleMode: !!opts.saleMode
        });

        // Ensure diamond carat / wt fields always present for Product List columns
        if (!(parseFloat(merged.diamond_carat) > 0)) {
            merged.diamond_carat = parseFloat(merged.stone_weight) || 0;
        }
        if (!(parseFloat(merged.diamond_wt) > 0)) {
            merged.diamond_wt = parseFloat(merged.less_wt) || 0;
        }
        if (!(parseFloat(merged.stone_weight) > 0) && parseFloat(merged.diamond_carat) > 0) {
            merged.stone_weight = parseFloat(merged.diamond_carat) || 0;
        }

        var normalized = modalRowsData.map(function (d) {
            var o = Object.assign({}, d);
            var pq = qtyFromLine(d);
            if (pq > 0) o.quantity = pq;
            if (o.carat_name == null || o.carat_name === '') o.carat_name = o.carat || '';
            if (o.carat_id == null || o.carat_id === '') o.carat_id = o.carat || '';
            return o;
        });

        row.setAttribute('data-group-items', JSON.stringify(normalized));
        row.setAttribute('data-product-id', merged.product_id || first.product_id || '');
        row.setAttribute('data-characteristic-id', merged.characteristic_id || first.characteristic_id || '');
        row.setAttribute('data-purity', String(merged.purity || 0));
        row.setAttribute('data-rate', String(merged.rate || 0));
        row.setAttribute('data-barcode', merged.barcode || '');
        row.setAttribute('data-calculation-type', merged.calculation_type || 'Rate X Gross Wt');
        if (merged.carat_id != null && merged.carat_id !== '') {
            row.setAttribute('data-carat-id', String(merged.carat_id));
        }

        var tabKey = (typeof window.currentMetalId !== 'undefined' && window.currentMetalId !== null) ? String(window.currentMetalId) : '';
        var groupImagePayload = (window.productModalGroupImageByTab && window.productModalGroupImageByTab[tabKey])
            || first.group_image
            || row.getAttribute('data-group-image')
            || '';
        if (groupImagePayload) {
            row.setAttribute(
                'data-group-image',
                (typeof groupImagePayload === 'object' && groupImagePayload != null)
                    ? JSON.stringify(groupImagePayload)
                    : groupImagePayload
            );
        }
        var primaryUrl = typeof window.getGroupImagePrimary === 'function'
            ? window.getGroupImagePrimary(groupImagePayload)
            : (typeof groupImagePayload === 'string' ? groupImagePayload : '');

        var actionCell = '<td><div class="action-btns">'
            + '<button type="button" class="btn-edit" onclick="editProductRow(\'' + rowId + '\')" title="Edit"><i class="feather icon-edit"></i></button>'
            + '<button type="button" class="btn-delete" onclick="deleteProductRow(\'' + rowId + '\')" title="Delete"><i class="feather icon-trash-2"></i></button>'
            + '</div></td>';
        row.innerHTML = getProductListRowCells(merged, { groupImage: primaryUrl }).join('') + actionCell;

        if (typeof window.applyProductListColumnVisibilityToRow === 'function') {
            window.applyProductListColumnVisibilityToRow(row);
        }
        if (typeof window.addRowCalculationListeners === 'function') {
            window.addRowCalculationListeners(row);
        }
        if (typeof window.auragoldRefreshProductTableRowPhotoFromJournal === 'function') {
            window.auragoldRefreshProductTableRowPhotoFromJournal(row);
        }
        var productCell = row.querySelector('[data-column="product"]');
        if (productCell && typeof window.editProductRow === 'function') {
            productCell.addEventListener('click', function (e) {
                if (e.target.tagName !== 'A') window.editProductRow(rowId);
            });
        }
        if (typeof window.updateSummaryRow === 'function') window.updateSummaryRow();
        if (typeof window.updateSummaryPanel === 'function') window.updateSummaryPanel();
        return true;
    }
    window.rebuildProductListRowFromModalRows = rebuildProductListRowFromModalRows;

    function setProductListRowInlineLocked(row, locked) {
        if (!row) return;
        row.classList.toggle('product-list-row-locked', !!locked);
        row.querySelectorAll('.editable-field').forEach(function (inp) {
            inp.readOnly = !!locked;
            if (locked) inp.setAttribute('tabindex', '-1');
            else inp.removeAttribute('tabindex');
        });
    }
    window.setProductListRowInlineLocked = setProductListRowInlineLocked;

    // Column visibility (per-page localStorage key)
    (function () {
        var b = getBoot();
        var pageKey = (b && b.pageColumnPrefs) ? String(b.pageColumnPrefs) : 'default';
        var STORAGE_KEY = 'auragold_product_list_column_visibility_' + pageKey;
        var settingsBtn = document.getElementById('tableSettingsBtn');
        var settingsDropdown = document.getElementById('tableSettingsDropdown');
        var checkboxes = settingsDropdown ? settingsDropdown.querySelectorAll('input[type="checkbox"][data-column]') : [];

        function saveColumnVisibility() {
            var state = {};
            checkboxes.forEach(function (cb) {
                var col = cb.getAttribute('data-column');
                if (col) state[col] = cb.checked;
            });
            try { localStorage.setItem(STORAGE_KEY, JSON.stringify(state)); } catch (e) {}
        }

        function updateEmptyRowColspan() {
            var emptyRowCell = document.getElementById('emptyRowCell');
            if (!emptyRowCell) return;
            var visibleFromPicker = Array.from(checkboxes).filter(function (cb) { return cb.checked; }).length;
            var colspan = visibleFromPicker > 0 ? (visibleFromPicker + 1) : 0;
            if (colspan <= 1) {
                var headerRow = document.querySelector('.product-table thead tr.product-table-header-columns');
                if (headerRow) {
                    colspan = headerRow.querySelectorAll('th:not(.hidden)').length;
                }
            }
            if (colspan <= 1 && typeof productListTableEmptyColspan === 'function') {
                colspan = productListTableEmptyColspan();
            }
            if (colspan <= 1) colspan = 74;
            emptyRowCell.setAttribute('colspan', String(colspan));
        }

        function syncPlGroupToggle(groupKey) {
            if (groupKey === null || groupKey === undefined || !settingsDropdown) return;
            var toggle = settingsDropdown.querySelector('input[data-pl-group-toggle="' + groupKey + '"]');
            if (!toggle) return;
            var inputs = settingsDropdown.querySelectorAll('.table-settings-item[data-pl-group="' + groupKey + '"] input[data-column]');
            var n = 0;
            var on = 0;
            inputs.forEach(function (inp) { n++; if (inp.checked) on++; });
            if (n === 0) return;
            toggle.indeterminate = on > 0 && on < n;
            toggle.checked = on === n;
        }

        function syncAllPlGroupToggles() {
            if (!settingsDropdown) return;
            settingsDropdown.querySelectorAll('input[data-pl-group-toggle]').forEach(function (t) {
                syncPlGroupToggle(t.getAttribute('data-pl-group-toggle'));
            });
        }

        function applySavedColumnVisibility() {
            var state = null;
            try {
                var raw = localStorage.getItem(STORAGE_KEY);
                if (raw) state = JSON.parse(raw);
            } catch (e) {}
            if (!state || typeof state !== 'object') {
                syncAllPlGroupToggles();
                updateEmptyRowColspan();
                return;
            }
            checkboxes.forEach(function (checkbox) {
                var columnName = checkbox.getAttribute('data-column');
                if (!columnName) return;
                var isVisible = state[columnName];
                if (typeof isVisible !== 'boolean') return;
                checkbox.checked = isVisible;
                document.querySelectorAll('.product-table th[data-column="' + columnName + '"]').forEach(function (header) {
                    if (isVisible) header.classList.remove('hidden'); else header.classList.add('hidden');
                });
                document.querySelectorAll('.product-table td[data-column="' + columnName + '"]').forEach(function (cell) {
                    if (isVisible) cell.classList.remove('hidden'); else cell.classList.add('hidden');
                });
            });
            updateEmptyRowColspan();
            syncAllPlGroupToggles();
        }

        window.applyProductListColumnVisibilityToRow = function (row) {
            if (!row || !row.querySelectorAll) return;
            var state = {};
            checkboxes.forEach(function (cb) {
                var col = cb.getAttribute('data-column');
                if (col) state[col] = cb.checked;
            });
            if (Object.keys(state).length === 0) {
                try {
                    var raw = localStorage.getItem(STORAGE_KEY);
                    if (raw) state = JSON.parse(raw);
                } catch (e) {}
            }
            row.querySelectorAll('td[data-column]').forEach(function (cell) {
                var col = cell.getAttribute('data-column');
                if (!col) return;
                var isVisible = (state && typeof state[col] === 'boolean') ? state[col] : true;
                if (isVisible) cell.classList.remove('hidden'); else cell.classList.add('hidden');
            });
        };

        if (!settingsBtn || !settingsDropdown) return;

        function runTableSettingsSearchFilter() {
            var tableSearchInput = document.getElementById('tableSettingsSearch');
            var searchTerm = tableSearchInput ? tableSearchInput.value.toLowerCase().trim() : '';
            settingsDropdown.querySelectorAll('.table-settings-item').forEach(function (item) {
                var label = item.querySelector('label');
                if (label) {
                    if (searchTerm === '' || label.textContent.toLowerCase().includes(searchTerm)) item.classList.remove('hidden');
                    else item.classList.add('hidden');
                }
            });
            settingsDropdown.querySelectorAll('.table-settings-section-title').forEach(function (titleEl) {
                var el = titleEl.nextElementSibling;
                var any = false;
                while (el && !el.classList.contains('table-settings-section-title')) {
                    if (el.classList.contains('table-settings-item') && !el.classList.contains('hidden')) any = true;
                    el = el.nextElementSibling;
                }
                titleEl.style.display = (searchTerm === '' || any) ? '' : 'none';
            });
        }

        if (!settingsBtn || !settingsDropdown) {
            updateEmptyRowColspan();
            return;
        }

        applySavedColumnVisibility();

        var closeBtn = settingsDropdown.querySelector('.pl-column-picker-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                settingsDropdown.classList.remove('show');
            });
        }

        settingsDropdown.querySelectorAll('[data-pl-action]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var action = btn.getAttribute('data-pl-action');
                if (action === 'clear-search') {
                    var searchInput = document.getElementById('tableSettingsSearch');
                    if (searchInput) searchInput.value = '';
                    var scrollBody = settingsDropdown.querySelector('.table-settings-dropdown-body');
                    if (scrollBody) scrollBody.scrollTop = 0;
                    runTableSettingsSearchFilter();
                } else if (action === 'pick-all') {
                    checkboxes.forEach(function (cb) {
                        cb.checked = true;
                        var columnName = cb.getAttribute('data-column');
                        if (!columnName) return;
                        document.querySelectorAll('.product-table th[data-column="' + columnName + '"]').forEach(function (header) {
                            header.classList.remove('hidden');
                        });
                        document.querySelectorAll('.product-table td[data-column="' + columnName + '"]').forEach(function (cell) {
                            cell.classList.remove('hidden');
                        });
                    });
                    updateEmptyRowColspan();
                    saveColumnVisibility();
                    syncAllPlGroupToggles();
                }
            });
        });

        settingsBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            settingsDropdown.classList.toggle('show');
            var searchInput = document.getElementById('tableSettingsSearch');
            if (searchInput && settingsDropdown.classList.contains('show')) {
                searchInput.value = '';
                var scrollBody = settingsDropdown.querySelector('.table-settings-dropdown-body');
                if (scrollBody) scrollBody.scrollTop = 0;
                settingsDropdown.querySelectorAll('.table-settings-item').forEach(function (item) { item.classList.remove('hidden'); });
                settingsDropdown.querySelectorAll('.table-settings-section-title').forEach(function (t) { t.style.display = ''; });
            }
        });

        document.addEventListener('click', function (e) {
            if (!settingsBtn.contains(e.target) && !settingsDropdown.contains(e.target)) {
                settingsDropdown.classList.remove('show');
                var searchInput = document.getElementById('tableSettingsSearch');
                if (searchInput) {
                    searchInput.value = '';
                    settingsDropdown.querySelectorAll('.table-settings-item').forEach(function (item) { item.classList.remove('hidden'); });
                    settingsDropdown.querySelectorAll('.table-settings-section-title').forEach(function (t) { t.style.display = ''; });
                }
            }
        });

        checkboxes.forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                var columnName = this.getAttribute('data-column');
                var isVisible = this.checked;
                document.querySelectorAll('.product-table th[data-column="' + columnName + '"]').forEach(function (header) {
                    if (isVisible) header.classList.remove('hidden'); else header.classList.add('hidden');
                });
                document.querySelectorAll('.product-table td[data-column="' + columnName + '"]').forEach(function (cell) {
                    if (isVisible) cell.classList.remove('hidden'); else cell.classList.add('hidden');
                });
                updateEmptyRowColspan();
                saveColumnVisibility();
                var row = this.closest('.table-settings-item');
                var g = row && row.getAttribute('data-pl-group');
                if (g !== null && g !== '') syncPlGroupToggle(g);
            });
        });

        settingsDropdown.querySelectorAll('input[data-pl-group-toggle]').forEach(function (toggle) {
            toggle.addEventListener('change', function () {
                var g = this.getAttribute('data-pl-group-toggle');
                var on = this.checked;
                this.indeterminate = false;
                var inputs = settingsDropdown.querySelectorAll('.table-settings-item[data-pl-group="' + g + '"] input[data-column]');
                inputs.forEach(function (inp) {
                    inp.checked = on;
                    var columnName = inp.getAttribute('data-column');
                    document.querySelectorAll('.product-table th[data-column="' + columnName + '"]').forEach(function (header) {
                        if (on) header.classList.remove('hidden'); else header.classList.add('hidden');
                    });
                    document.querySelectorAll('.product-table td[data-column="' + columnName + '"]').forEach(function (cell) {
                        if (on) cell.classList.remove('hidden'); else cell.classList.add('hidden');
                    });
                });
                updateEmptyRowColspan();
                saveColumnVisibility();
            });
        });

        var tableSearchInput = document.getElementById('tableSettingsSearch');
        if (tableSearchInput) {
            tableSearchInput.addEventListener('input', function () {
                runTableSettingsSearchFilter();
            });
        }
    })();

    // Column drag + DB order
    (function () {
        if (typeof jQuery === 'undefined') return;
        var b = getBoot();
        if (!b || !b.pageColumnPrefs) return;

        var table = document.querySelector('.product-table');
        if (!table) return;

        var thead = table.querySelector('thead tr.product-table-header-columns');
        var tbody = document.getElementById('productTableBody');
        var tfoot = document.getElementById('productTableFooter');
        var tfootRow = tfoot ? tfoot.querySelector('tr') : null;
        if (!thead || !tbody) return;

        var PAGE_COLUMN_PREFS = b.pageColumnPrefs;
        var TAB_MAIN = 'main';

        var draggedColumn = null;
        var draggedColumnIndex = null;
        var dropTargetColumn = null;
        var dropPositionRight = false;

        function getDraggableColumns() {
            return thead.querySelectorAll('th.draggable-column');
        }
        function getColumnIndex(th) {
            return Array.from(thead.children).indexOf(th);
        }
        function clearColumnDropHighlight() {
            getDraggableColumns().forEach(function (col) {
                col.classList.remove('drag-over-column', 'drag-over-column-right');
            });
            dropTargetColumn = null;
        }

        function reorderColumns(dragIndex, dropIndex) {
            var rows = [thead].concat(Array.from(tbody.querySelectorAll('tr')));
            if (tfootRow) rows.push(tfootRow);
            rows.forEach(function (row) {
                var cells = Array.from(row.children);
                var draggedCell = cells[dragIndex];
                if (draggedCell) {
                    cells.splice(dragIndex, 1);
                    cells.splice(dropIndex, 0, draggedCell);
                    cells.forEach(function (cell) { row.appendChild(cell); });
                }
            });
            saveProductTableColumnOrder();
        }

        function getCurrentColumnOrder() {
            return Array.from(getDraggableColumns()).map(function (th) { return th.getAttribute('data-column'); });
        }

        function saveProductTableColumnOrder() {
            var order = getCurrentColumnOrder();
            if (!order.length) return;
            var prefs = {};
            order.forEach(function (k) { prefs[k] = 1; });
            jQuery.ajax({
                url: 'ajax/save-product-modal-column-preferences.php',
                type: 'POST',
                data: {
                    page_name: PAGE_COLUMN_PREFS,
                    tab_key: TAB_MAIN,
                    preferences: JSON.stringify(prefs)
                },
                dataType: 'json'
            }).fail(function () {});
        }

        function applySavedColumnOrder(orderedKeys) {
            if (!orderedKeys || !orderedKeys.length) return;
            var rows = [thead].concat(Array.from(tbody.querySelectorAll('tr')));
            if (tfootRow) rows.push(tfootRow);
            rows.forEach(function (row) {
                var cells = Array.from(row.children);
                var fixed = cells.length ? cells[cells.length - 1] : null;
                var map = {};
                var dataCells = fixed ? cells.slice(0, -1) : cells;
                dataCells.forEach(function (cell) {
                    var key = cell.getAttribute('data-column');
                    if (key) map[key] = cell;
                });
                orderedKeys.forEach(function (k) { if (map[k]) row.appendChild(map[k]); });
                if (fixed) row.appendChild(fixed);
            });
        }

        function onColumnMouseMove(e) {
            if (!draggedColumn || !thead) return;
            var el = document.elementFromPoint(e.clientX, e.clientY);
            var th = el ? el.closest('th.draggable-column') : null;
            clearColumnDropHighlight();
            if (!th || th.parentNode !== thead || th === draggedColumn) return;
            dropTargetColumn = th;
            var rect = th.getBoundingClientRect();
            var colMiddle = rect.left + rect.width / 2;
            dropPositionRight = e.clientX >= colMiddle;
            if (dropPositionRight) th.classList.add('drag-over-column-right'); else th.classList.add('drag-over-column');
        }
        function onColumnMouseUp() {
            if (!draggedColumn || !thead) {
                finishColumnDrag();
                return;
            }
            if (dropTargetColumn && dropTargetColumn !== draggedColumn) {
                var dropIndex = getColumnIndex(dropTargetColumn);
                var dragIndex = draggedColumnIndex;
                var finalDropIndex = dropIndex;
                if (dropPositionRight && dragIndex < dropIndex) finalDropIndex = dropIndex + 1;
                else if (!dropPositionRight && dragIndex > dropIndex) finalDropIndex = dropIndex;
                else if (dropPositionRight && dragIndex > dropIndex) finalDropIndex = dropIndex + 1;
                else finalDropIndex = dropIndex;
                reorderColumns(dragIndex, finalDropIndex);
            }
            finishColumnDrag();
        }
        function finishColumnDrag() {
            if (draggedColumn) draggedColumn.classList.remove('dragging-column');
            draggedColumn = null;
            draggedColumnIndex = null;
            clearColumnDropHighlight();
            document.removeEventListener('mousemove', onColumnMouseMove);
            document.removeEventListener('mouseup', onColumnMouseUp);
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
        }

        thead.addEventListener('mousedown', function (e) {
            var th = e.target.closest('th.draggable-column');
            if (!th) return;
            e.preventDefault();
            draggedColumn = th;
            draggedColumnIndex = getColumnIndex(th);
            th.classList.add('dragging-column');
            document.body.style.cursor = 'grabbing';
            document.body.style.userSelect = 'none';
            document.addEventListener('mousemove', onColumnMouseMove);
            document.addEventListener('mouseup', onColumnMouseUp);
        });

        function loadAndApplyColumnOrder() {
            jQuery.ajax({
                url: 'ajax/get-column-preferences.php',
                type: 'POST',
                data: { page_name: PAGE_COLUMN_PREFS },
                dataType: 'json'
            }).done(function (res) {
                if (res.status !== 'success' || !res.preferences || !res.preferences.length) return;
                var currentOrder = getCurrentColumnOrder();
                var savedOrder = res.preferences.map(function (p) { return p.column_key; });
                var merged = savedOrder.slice();
                currentOrder.forEach(function (k) { if (merged.indexOf(k) === -1) merged.push(k); });
                applySavedColumnOrder(merged);
            });
        }

        window.getProductTableColumnOrder = getCurrentColumnOrder;

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', loadAndApplyColumnOrder);
        } else {
            loadAndApplyColumnOrder();
        }
    })();
})();
