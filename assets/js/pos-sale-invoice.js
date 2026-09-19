/**
 * POS Sale Invoice — compact billing; posts same payload shape as sale-invoice.php to save handler.
 */
(function () {
    'use strict';

    var cfg = window.POS_SALE_INVOICE_PAGE || {};
    var SAVE_URL = cfg.saveUrl || 'ajax/pos_invoice_save.php';
    var BARCODE_URL = cfg.barcodeUrl || 'ajax/get-product-by-barcode.php';
    var NAME_SEARCH_URL = cfg.nameSearchUrl || 'ajax/pos_product_search.php';
    var CUSTOMER_SEARCH_URL = cfg.customerSearchUrl || 'ajax/search-customers.php';
    var PRINT_URL = cfg.printUrl || 'sale-invoice-print.php';

    var rows = [];
    var rowSeq = 1;
    var saving = false;
    var suggestTarget = null;
    var suggestItems = [];
    var suggestIndex = -1;
    var customerSuggestTimer = null;

    function $(id) {
        return document.getElementById(id);
    }

    function posFmt(n) {
        var x = parseFloat(n);
        if (!isFinite(x)) {
            x = 0;
        }
        return x.toFixed(2);
    }

    function closeSuggest() {
        var el = $('posSuggestBox');
        if (el) {
            el.style.display = 'none';
            el.innerHTML = '';
        }
        suggestTarget = null;
        suggestItems = [];
        suggestIndex = -1;
    }

    function showSuggest(anchorId, html) {
        var box = $('posSuggestBox');
        if (!box) {
            return;
        }
        box.innerHTML = html;
        box.style.display = html ? 'block' : 'none';
        var anchor = $(anchorId);
        if (anchor) {
            anchor.appendChild(box);
        }
    }

    function parseNum(v) {
        var n = parseFloat(String(v).replace(/,/g, ''));
        return isFinite(n) ? n : 0;
    }

    function lineAmounts(r) {
        var qty = Math.max(0, parseNum(r.qty));
        var rate = Math.max(0, parseNum(r.rate));
        var disc = Math.max(0, parseNum(r.discount));
        var gstPct = Math.max(0, parseNum(r.gstPercent));
        var gross = qty * rate;
        var afterDisc = Math.max(0, gross - disc);
        var tax = afterDisc * (gstPct / 100);
        var total = afterDisc + tax;
        return { gross: gross, afterDisc: afterDisc, tax: tax, total: total };
    }

    function recalcTotals() {
        var sub = 0;
        var discSum = 0;
        var gstSum = 0;
        var grand = 0;
        rows.forEach(function (r) {
            var L = lineAmounts(r);
            sub += L.gross;
            discSum += Math.min(L.gross, parseNum(r.discount));
            gstSum += L.tax;
            grand += L.total;
        });
        var elSub = $('posSumSubtotal');
        var elDisc = $('posSumDiscount');
        var elGst = $('posSumGst');
        var elGrand = $('posSumGrand');
        if (elSub) {
            elSub.textContent = posFmt(sub);
        }
        if (elDisc) {
            elDisc.textContent = posFmt(discSum);
        }
        if (elGst) {
            elGst.textContent = posFmt(gstSum);
        }
        if (elGrand) {
            elGrand.textContent = posFmt(grand);
        }
        var paidEl = $('posPaidAmount');
        var paid = paidEl ? parseNum(paidEl.value) : 0;
        var bal = grand - paid;
        var balEl = $('posBalanceAmt');
        if (balEl) {
            balEl.textContent = posFmt(bal);
        }
    }

    function weightsFromProduct(p) {
        var gw = parseNum(p.metal_weight);
        if (!(gw > 0)) {
            gw = parseNum(p.opening_weight);
        }
        if (!(gw > 0)) {
            gw = parseNum(p.gross_weight);
        }
        if (!(gw > 0)) {
            gw = parseNum(p.final_weight);
        }
        var nw = parseNum(p.net_weight);
        if (!(nw > 0)) {
            nw = gw;
        }
        var fw = parseNum(p.final_weight);
        if (!(fw > 0)) {
            fw = nw;
        }
        var purity = parseNum(p.purity || p.opening_purity);
        var pw = parseNum(p.pure_weight || p.purity_weight);
        if (!(pw > 0) && nw > 0 && purity > 0) {
            pw = purity <= 1 ? nw * purity : purity <= 100 ? nw * (purity / 100) : nw * (purity / 1000);
        }
        if (!(pw > 0)) {
            pw = nw;
        }
        return { gross: gw, net: nw, final: fw, pure: pw, purity: purity };
    }

    function defaultGstPercent(p) {
        var t = parseNum(p.total_tax_percent);
        if (t > 0) {
            return t;
        }
        return parseNum(p.vat_value);
    }

    function addRowFromProductPayload(p, qtyOverride) {
        var w = weightsFromProduct(p);
        var pid = parseInt(p.id || p.product_id, 10) || 0;
        var cid = parseInt(p.characteristic_id, 10) || 0;
        if (!cid && p.characteristic_id) {
            cid = parseInt(String(p.characteristic_id), 10) || 0;
        }
        var barcode = String(p.barcode || '').trim();
        var name = String(p.name || p.product_name || '').trim();
        var rate = parseNum(p.rate);
        var qty = qtyOverride != null ? Math.max(0.0001, parseNum(qtyOverride)) : 1;
        var gst = defaultGstPercent(p);
        var mid = parseInt(p.metal_id, 10) || 0;
        var dcat = String(p.diamond_category || '').trim();
        rows.push({
            _id: rowSeq++,
            productId: pid,
            characteristicId: cid,
            metalId: mid,
            barcode: barcode,
            productName: name,
            qty: qty,
            rate: rate,
            discount: 0,
            gstPercent: gst,
            diamondCategory: dcat,
            _grossWeight: w.gross,
            _netWeight: w.net,
            _finalWeight: w.final,
            _pureWeight: w.pure,
            _purity: w.purity
        });
        renderTable();
        recalcTotals();
    }

    function renderTable() {
        var tb = $('posItemsBody');
        if (!tb) {
            return;
        }
        tb.innerHTML = '';
        var i = 0;
        rows.forEach(function (r) {
            i += 1;
            var L = lineAmounts(r);
            var tr = document.createElement('tr');
            tr.setAttribute('data-rid', String(r._id));
            tr.innerHTML =
                '<td class="text-center">' +
                i +
                '</td>' +
                '<td><span class="text-monospace small">' +
                escapeHtml(r.barcode) +
                '</span></td>' +
                '<td><small>' +
                escapeHtml(r.productName) +
                '</small></td>' +
                '<td><input type="number" step="0.001" min="0.001" class="form-control form-control-sm js-pcell" data-k="qty" data-rid="' +
                r._id +
                '" value="' +
                r.qty +
                '"></td>' +
                '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm js-pcell" data-k="rate" data-rid="' +
                r._id +
                '" value="' +
                r.rate +
                '"></td>' +
                '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm js-pcell" data-k="discount" data-rid="' +
                r._id +
                '" value="' +
                r.discount +
                '"></td>' +
                '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm js-pcell" data-k="gstPercent" data-rid="' +
                r._id +
                '" value="' +
                r.gstPercent +
                '"></td>' +
                '<td class="text-right font-weight-bold js-line-total">' +
                posFmt(L.total) +
                '</td>' +
                '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger py-0 js-remove" data-rid="' +
                r._id +
                '">&times;</button></td>';
            tb.appendChild(tr);
        });
        tb.querySelectorAll('.js-pcell').forEach(function (inp) {
            inp.addEventListener('input', onCellInput);
            inp.addEventListener('change', onCellInput);
        });
        tb.querySelectorAll('.js-remove').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var rid = parseInt(btn.getAttribute('data-rid'), 10);
                rows = rows.filter(function (x) {
                    return x._id !== rid;
                });
                renderTable();
                recalcTotals();
            });
        });
    }

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function onCellInput(ev) {
        var inp = ev.target;
        var rid = parseInt(inp.getAttribute('data-rid'), 10);
        var k = inp.getAttribute('data-k');
        var r = rows.filter(function (x) {
            return x._id === rid;
        })[0];
        if (!r || !k) {
            return;
        }
        r[k] = parseNum(inp.value);
        var tr = inp.closest ? inp.closest('tr') : null;
        if (tr) {
            var L = lineAmounts(r);
            var td = tr.querySelector('.js-line-total');
            if (td) {
                td.textContent = posFmt(L.total);
            }
        }
        recalcTotals();
    }

    function fetchJson(url, cb) {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) {
                return;
            }
            try {
                var j = JSON.parse(xhr.responseText || '{}');
                cb(null, j);
            } catch (e) {
                cb(e, null);
            }
        };
        xhr.send();
    }

    function postSave(fd, cb) {
        if (typeof jQuery !== 'undefined' && jQuery.ajax) {
            jQuery
                .ajax({
                    url: SAVE_URL,
                    type: 'POST',
                    data: fd,
                    processData: false,
                    contentType: false,
                    dataType: 'json'
                })
                .done(function (resp) {
                    cb(null, resp);
                })
                .fail(function (xhr) {
                    cb(new Error(xhr.statusText || 'Request failed'), null);
                });
            return;
        }
        var xhr = new XMLHttpRequest();
        xhr.open('POST', SAVE_URL, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) {
                return;
            }
            try {
                var j = JSON.parse(xhr.responseText || '{}');
                cb(null, j);
            } catch (e) {
                cb(e, null);
            }
        };
        xhr.send(fd);
    }

    function buildItemsPayload() {
        return rows.map(function (r) {
            var L = lineAmounts(r);
            var qty = Math.max(0.0001, parseNum(r.qty));
            var gw = parseNum(r._grossWeight);
            var nw = parseNum(r._netWeight);
            var fw = parseNum(r._finalWeight);
            var pw = parseNum(r._pureWeight);
            if (!(gw > 0)) {
                gw = qty;
            }
            if (!(nw > 0)) {
                nw = gw;
            }
            if (!(fw > 0)) {
                fw = nw;
            }
            if (!(pw > 0)) {
                pw = nw;
            }
            return {
                product_id: String(r.productId),
                characteristic_id: r.characteristicId ? String(r.characteristicId) : '',
                metal_id: r.metalId ? String(r.metalId) : '',
                barcode: r.barcode,
                product_name: r.productName,
                calculation_type: 'POS',
                diamond_category: r.diamondCategory || '',
                category: r.diamondCategory || '',
                carat: '',
                quantity: qty,
                metal_qty: qty,
                metal_weight: gw,
                gross_weight: gw,
                less_weight: 0,
                purity: parseNum(r._purity),
                purity_weight: pw,
                final_weight: fw,
                net_weight: nw,
                pure_weight: pw,
                rate: parseNum(r.rate),
                metal_rate: parseNum(r.rate),
                making: 0,
                making_amount: 0,
                making_type: 'Fix',
                making_rate: 0,
                making_discount_amt: 0,
                making_actual_value: 0,
                making_cost: 0,
                design_no: '',
                tax: L.tax,
                amount: L.afterDisc,
                net_amount: L.afterDisc,
                net_amt_with_tax: L.total,
                stone_charges: 0,
                stone_amount: 0,
                stone_weight: 0,
                other_charges: 0,
                other_amount: 0,
                diamond_value: 0,
                diamond_amount: 0,
                gemstone_value: 0,
                metal_value: 0,
                metal_cost: 0,
                discount: parseNum(r.discount),
                purchase_amount: 0,
                sale_amount: 0,
                sale_amount_with: 0,
                reverse: 0,
                merge_group_index: 0
            };
        });
    }

    function buildOrderFormData() {
        var customerName = ($('posCustomerName') && $('posCustomerName').value.trim()) || '';
        var customerId = ($('posCustomerId') && $('posCustomerId').value) || '';
        var orderNo = ($('posInvoiceNo') && $('posInvoiceNo').value.trim()) || cfg.nextOrderNo || '';
        var orderDate = ($('posInvoiceDate') && $('posInvoiceDate').value) || '';
        var currency = ($('posCurrency') && $('posCurrency').value) || cfg.defaultCurrency || 'AED';
        var payMode = ($('posPaymentMode') && $('posPaymentMode').value) || 'Cash';
        var grand = parseNum($('posSumGrand') ? $('posSumGrand').textContent : 0);
        var sub = parseNum($('posSumSubtotal') ? $('posSumSubtotal').textContent : 0);
        var discTot = parseNum($('posSumDiscount') ? $('posSumDiscount').textContent : 0);
        var paid = $('posPaidAmount') ? parseNum($('posPaidAmount').value) : 0;
        var balance = grand - paid;
        var items = buildItemsPayload();
        var payments = [];
        if (paid > 0.00001) {
            payments.push({
                payment_type: payMode,
                deposit_into: payMode === 'Bank' ? 'Bank' : payMode,
                transaction_no: '',
                cheque_date: null,
                purity_carat: '',
                amount: paid,
                previous_balance_amount: 0,
                current_order_amount: paid,
                diamond_category: '',
                quantity: 0
            });
        }
        var fd = new FormData();
        fd.append('order_no', orderNo);
        fd.append('order_id', '0');
        fd.append('customer_id', String(customerId || '0'));
        fd.append('customer_name', customerName);
        fd.append('customer_billing_state', ($('posCustomerState') && $('posCustomerState').value) || '');
        fd.append('customer_gstin', '');
        fd.append('against_of', '');
        fd.append('currency', currency);
        fd.append('ref_no', '');
        fd.append('sales_person', cfg.salesPerson || '');
        fd.append('order_date', orderDate);
        fd.append('due_date', orderDate);
        fd.append('layaways', '');
        fd.append('fixing_type', 'Standard');
        fd.append('group_name', '');
        fd.append('comment', ($('posOrderComment') && $('posOrderComment').value.trim()) || '');
        fd.append('payment_comments', '[]');
        fd.append('enable_eway_bill', '0');
        fd.append('eway_vehicle_no', '');
        fd.append('eway_trans_distance', '0');
        fd.append('eway_distance_km', '0');
        fd.append('eway_from_pincode', '');
        fd.append('eway_to_pincode', '');
        fd.append('eway_trans_mode', '1');
        fd.append('eway_transporter_name', '');
        fd.append('eway_transporter_id', '');
        fd.append('eway_trans_doc_no', '');
        fd.append('eway_trans_doc_date', '');
        fd.append('eway_vehicle_type', 'R');
        fd.append('eway_sandbox_force_sample_payload', '0');
        fd.append('previous_balance', '0');
        fd.append('previous_gold', '0');
        fd.append('previous_silver', '0');
        fd.append('previous_diamond', '0');
        fd.append('previous_gemstone', '0');
        fd.append('subtotal', String(sub));
        fd.append('additional_amt', '0');
        fd.append('net_total', String(sub));
        fd.append('reward_points', '0');
        fd.append('coupon_code', '');
        fd.append('coupon_discount', '0');
        fd.append('discount_amt', String(discTot));
        fd.append('discount_percent', '0');
        fd.append('redeem_points', '0');
        fd.append('grand_total', String(grand));
        fd.append('advance_payment', '0');
        fd.append('metal_amt', '0');
        fd.append('round_off', '0');
        fd.append('paid_amt', String(paid));
        fd.append('balance_amt', String(balance));
        fd.append('adjusted_balance_used', '0');
        fd.append('use_previous_balance', '0');
        fd.append('previous_balance_used_amt', '0');
        fd.append('making_amount_for_sale_fixing', '0');
        fd.append('items', JSON.stringify(items));
        fd.append('payments', JSON.stringify(payments));
        return fd;
    }

    function posEwayContinue(resp, done) {
        if (!resp) {
            done();
            return;
        }
        var eb = resp.eway_bill;
        var eway = resp.eway;
        var ewayAttempted = eway && eway.skipped === false;
        if (!ewayAttempted && eb && (eb.status === 'skipped' || eb.status == null) && !resp.final_payload_sent_to_api) {
            done();
            return;
        }
        if (typeof window.showSaleInvoiceEwayUserFeedback === 'function') {
            window.showSaleInvoiceEwayUserFeedback(resp, done);
        } else {
            done();
        }
    }

    function saveInvoice(printAfter) {
        if (saving) {
            return;
        }
        var cn = $('posCustomerName') && $('posCustomerName').value.trim();
        if (!cn) {
            alert('Please enter customer name or mobile search.');
            $('posCustomerName') && $('posCustomerName').focus();
            return;
        }
        if (!rows.length) {
            alert('Add at least one line item.');
            $('posBarcodeInput') && $('posBarcodeInput').focus();
            return;
        }
        saving = true;
        var fd = buildOrderFormData();
        postSave(fd, function (err, resp) {
            saving = false;
            if (err || !resp) {
                alert('Save failed: ' + (err && err.message ? err.message : 'Unknown error'));
                return;
            }
            if (resp.status !== 'success') {
                alert(resp.message || 'Save failed');
                return;
            }
            var invoiceId = resp.invoice_id || resp.order_id;
            posEwayContinue(resp, function () {
                if (printAfter && invoiceId) {
                    window.open(PRINT_URL + '?id=' + encodeURIComponent(String(invoiceId)), '_blank', 'width=1200,height=800');
                }
                window.location.href = 'pos-sale-invoice.php';
            });
        });
    }

    function commitBarcode() {
        var inp = $('posBarcodeInput');
        if (!inp) {
            return;
        }
        var raw = inp.value.trim();
        if (!raw) {
            return;
        }
        closeSuggest();
        var url = BARCODE_URL + '?barcode=' + encodeURIComponent(raw);
        fetchJson(url, function (err, data) {
            if (err || !data) {
                alert('Barcode lookup failed.');
                return;
            }
            if (data.success && data.product) {
                addRowFromProductPayload(data.product, null);
                inp.value = '';
                inp.focus();
                return;
            }
            if (data.success && data.products && data.products.length) {
                addRowFromProductPayload(data.products[0], null);
                if (data.products.length > 1) {
                    alert('Multiple lines for this tag — added first line. Use full Sale Invoice for merged lines.');
                }
                inp.value = '';
                inp.focus();
                return;
            }
            alert(data.message || 'Product not found');
            inp.select();
        });
    }

    function commitProductNameSearch() {
        var inp = $('posProductSearch');
        if (!inp) {
            return;
        }
        var raw = inp.value.trim();
        if (raw.length < 2) {
            return;
        }
        var url = NAME_SEARCH_URL + '?q=' + encodeURIComponent(raw);
        fetchJson(url, function (err, data) {
            if (err || !data || data.status !== 'success') {
                alert('Product search failed.');
                return;
            }
            var list = data.products || [];
            if (!list.length) {
                alert('No products match "' + raw + '".');
                return;
            }
            if (list.length === 1) {
                var x = list[0];
                addRowFromProductPayload(
                    {
                        id: x.product_id,
                        characteristic_id: x.characteristic_id,
                        barcode: x.barcode,
                        name: x.product_name,
                        rate: x.rate,
                        metal_id: x.metal_id,
                        opening_weight: x.opening_weight,
                        opening_purity: x.opening_purity,
                        final_weight: x.final_weight,
                        diamond_category: x.diamond_category,
                        total_tax_percent: x.gst_percent
                    },
                    null
                );
                inp.value = '';
                $('posBarcodeInput') && $('posBarcodeInput').focus();
                return;
            }
            suggestTarget = 'name';
            suggestItems = list;
            suggestIndex = 0;
            var html = list
                .map(function (p, idx) {
                    return (
                        '<div class="pos-suggest-item' +
                        (idx === 0 ? ' active' : '') +
                        '" data-idx="' +
                        idx +
                        '">' +
                        escapeHtml(p.product_name) +
                        (p.barcode ? ' <span class="text-muted">(' + escapeHtml(p.barcode) + ')</span>' : '') +
                        '</div>'
                    );
                })
                .join('');
            showSuggest('posProductSearchWrap', html);
            bindSuggestClicks('name');
        });
    }

    function bindSuggestClicks(kind) {
        var box = $('posSuggestBox');
        if (!box) {
            return;
        }
        box.querySelectorAll('.pos-suggest-item').forEach(function (node) {
            node.addEventListener('mousedown', function (e) {
                e.preventDefault();
                var idx = parseInt(node.getAttribute('data-idx'), 10);
                pickSuggest(idx, kind);
            });
        });
    }

    function pickSuggest(idx, kind) {
        if (kind === 'name' && suggestItems[idx]) {
            var x = suggestItems[idx];
            addRowFromProductPayload(
                {
                    id: x.product_id,
                    characteristic_id: x.characteristic_id,
                    barcode: x.barcode,
                    name: x.product_name,
                    rate: x.rate,
                    metal_id: x.metal_id,
                    opening_weight: x.opening_weight,
                    opening_purity: x.opening_purity,
                    final_weight: x.final_weight,
                    diamond_category: x.diamond_category,
                    total_tax_percent: x.gst_percent
                },
                null
            );
            var ps = $('posProductSearch');
            if (ps) {
                ps.value = '';
            }
            closeSuggest();
            $('posBarcodeInput') && $('posBarcodeInput').focus();
        }
        if (kind === 'customer' && suggestItems[idx]) {
            var c = suggestItems[idx];
            if ($('posCustomerId')) {
                $('posCustomerId').value = String(c.id || '');
            }
            if ($('posCustomerName')) {
                $('posCustomerName').value = c.name || '';
            }
            if ($('posCustomerState')) {
                $('posCustomerState').value = c.billing_state || '';
            }
            closeSuggest();
            $('posBarcodeInput') && $('posBarcodeInput').focus();
        }
    }

    function buildCustomerSuggestHtml(list) {
        return list
            .map(function (c, idx) {
                return (
                    '<div class="pos-suggest-item' +
                    (idx === 0 ? ' active' : '') +
                    '" data-idx="' +
                    idx +
                    '">' +
                    escapeHtml(c.display_text || c.name || '') +
                    '</div>'
                );
            })
            .join('');
    }

    /**
     * @param {boolean} autoPickSingle - true for Enter/Tab: one match fills field; false while typing: always show list
     */
    function runCustomerSearch(autoPickSingle) {
        var inp = $('posCustomerName');
        if (!inp) {
            return;
        }
        var raw = inp.value.trim();
        if (raw.length < 2) {
            closeSuggest();
            return;
        }
        var url = CUSTOMER_SEARCH_URL + '?q=' + encodeURIComponent(raw);
        fetchJson(url, function (err, data) {
            if (err || !data || data.status !== 'success') {
                closeSuggest();
                return;
            }
            var list = data.customers || [];
            if (!list.length) {
                closeSuggest();
                return;
            }
            if (autoPickSingle && list.length === 1) {
                suggestItems = list;
                pickSuggest(0, 'customer');
                return;
            }
            suggestTarget = 'customer';
            suggestItems = list;
            suggestIndex = 0;
            showSuggest('posCustomerWrap', buildCustomerSuggestHtml(list));
            bindSuggestClicks('customer');
        });
    }

    function scheduleCustomerSearch() {
        if (customerSuggestTimer) {
            clearTimeout(customerSuggestTimer);
        }
        customerSuggestTimer = setTimeout(function () {
            customerSuggestTimer = null;
            runCustomerSearch(false);
        }, 280);
    }

    function commitCustomerSearch() {
        runCustomerSearch(true);
    }

    function setPaymentMode(mode) {
        var sel = $('posPaymentMode');
        if (sel) {
            sel.value = mode;
        }
        document.querySelectorAll('.pos-pay-icon').forEach(function (btn) {
            btn.classList.toggle('active', btn.getAttribute('data-pay') === mode);
        });
    }

    function wirePaymentIcons() {
        var wrap = $('posPaymentIcons');
        if (!wrap) {
            return;
        }
        wrap.querySelectorAll('.pos-pay-icon').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var m = btn.getAttribute('data-pay') || 'Cash';
                setPaymentMode(m);
            });
        });
    }

    function holdDraft() {
        try {
            var draft = {
                customerName: $('posCustomerName') ? $('posCustomerName').value : '',
                customerId: $('posCustomerId') ? $('posCustomerId').value : '',
                customerState: $('posCustomerState') ? $('posCustomerState').value : '',
                rows: rows,
                paid: $('posPaidAmount') ? $('posPaidAmount').value : '',
                currency: $('posCurrency') ? $('posCurrency').value : '',
                comment: $('posOrderComment') ? $('posOrderComment').value : '',
                paymentMode: $('posPaymentMode') ? $('posPaymentMode').value : 'Cash'
            };
            localStorage.setItem('pos_sale_hold_draft', JSON.stringify(draft));
            alert('Bill held locally on this device.');
        } catch (e) {
            alert('Could not save hold.');
        }
    }

    function restoreHold() {
        try {
            var s = localStorage.getItem('pos_sale_hold_draft');
            if (!s) {
                return false;
            }
            var d = JSON.parse(s);
            if (!d || !d.rows) {
                return false;
            }
            if ($('posCustomerName')) {
                $('posCustomerName').value = d.customerName || '';
            }
            if ($('posCustomerId')) {
                $('posCustomerId').value = d.customerId || '';
            }
            if ($('posCustomerState')) {
                $('posCustomerState').value = d.customerState || '';
            }
            if ($('posPaidAmount') && d.paid != null) {
                $('posPaidAmount').value = d.paid;
            }
            if ($('posCurrency') && d.currency) {
                $('posCurrency').value = d.currency;
            }
            if ($('posOrderComment') && d.comment != null) {
                $('posOrderComment').value = d.comment;
            }
            if (d.paymentMode) {
                setPaymentMode(d.paymentMode);
            }
            rows = d.rows;
            renderTable();
            recalcTotals();
            return true;
        } catch (e) {
            return false;
        }
    }

    function clearAll() {
        if (!confirm('Clear this bill?')) {
            return;
        }
        rows = [];
        if ($('posCustomerName')) {
            $('posCustomerName').value = '';
        }
        if ($('posCustomerId')) {
            $('posCustomerId').value = '';
        }
        if ($('posCustomerState')) {
            $('posCustomerState').value = '';
        }
        if ($('posPaidAmount')) {
            $('posPaidAmount').value = '';
        }
        if ($('posOrderComment')) {
            $('posOrderComment').value = '';
        }
        setPaymentMode('Cash');
        renderTable();
        recalcTotals();
        $('posBarcodeInput') && $('posBarcodeInput').focus();
    }

    function wire() {
        var bc = $('posBarcodeInput');
        if (bc) {
            bc.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter' && e.key !== 'Tab') {
                    return;
                }
                if (!bc.value.trim()) {
                    return;
                }
                e.preventDefault();
                commitBarcode();
            });
        }
        var ps = $('posProductSearch');
        if (ps) {
            ps.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter' && e.key !== 'Tab') {
                    return;
                }
                if (ps.value.trim().length < 2) {
                    return;
                }
                e.preventDefault();
                commitProductNameSearch();
            });
        }
        var cn = $('posCustomerName');
        if (cn) {
            cn.addEventListener('input', function () {
                if (cn.value.trim().length < 2) {
                    if (customerSuggestTimer) {
                        clearTimeout(customerSuggestTimer);
                        customerSuggestTimer = null;
                    }
                    closeSuggest();
                    return;
                }
                scheduleCustomerSearch();
            });
            cn.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter' && e.key !== 'Tab') {
                    return;
                }
                if (cn.value.trim().length < 2) {
                    return;
                }
                if (customerSuggestTimer) {
                    clearTimeout(customerSuggestTimer);
                    customerSuggestTimer = null;
                }
                e.preventDefault();
                commitCustomerSearch();
            });
        }
        document.addEventListener('click', function (e) {
            if (!e.target.closest) {
                return;
            }
            if (e.target.closest('#posSuggestBox') || e.target.closest('.pos-relative')) {
                return;
            }
            closeSuggest();
        });
        var paid = $('posPaidAmount');
        if (paid) {
            paid.addEventListener('input', recalcTotals);
            paid.addEventListener('change', recalcTotals);
        }
        var btnSave = $('posBtnSave');
        if (btnSave) {
            btnSave.addEventListener('click', function () {
                saveInvoice(false);
            });
        }
        var btnPrint = $('posBtnSavePrint');
        if (btnPrint) {
            btnPrint.addEventListener('click', function () {
                saveInvoice(true);
            });
        }
        var btnHold = $('posBtnHold');
        if (btnHold) {
            btnHold.addEventListener('click', holdDraft);
        }
        var btnClear = $('posBtnClear');
        if (btnClear) {
            btnClear.addEventListener('click', clearAll);
        }
        var btnBack = $('posBtnBack');
        if (btnBack) {
            btnBack.addEventListener('click', function () {
                window.location.href = 'sale-invoice.php';
            });
        }
        var btnRestore = $('posBtnRestoreHold');
        if (btnRestore) {
            btnRestore.addEventListener('click', function () {
                if (restoreHold()) {
                    alert('Restored held bill.');
                } else {
                    alert('No held bill found.');
                }
            });
        }
        var btnNew = $('posBtnNew');
        if (btnNew) {
            btnNew.addEventListener('click', function () {
                window.location.href = 'pos-sale-invoice.php';
            });
        }
        wirePaymentIcons();
    }

    document.addEventListener('DOMContentLoaded', function () {
        wire();
        recalcTotals();
        var bc = $('posBarcodeInput');
        if (bc) {
            setTimeout(function () {
                bc.focus();
            }, 100);
        }
    });
})();
