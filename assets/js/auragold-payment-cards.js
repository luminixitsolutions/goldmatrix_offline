/**
 * Auragold — payment lines as cards (shared across invoice / quotation / return / etc.).
 * Expects: #paymentTableBody, #paymentTableFooter, global `payments` array, window.editPayment, window.deletePayment.
 */
(function (global) {
    'use strict';

    function escAttr(s) {
        return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }
    function escHtml(s) {
        return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    }
    function currencyCode() {
        var sel = document.getElementById('currency');
        if (sel && sel.value) return String(sel.value).trim();
        return 'AED';
    }
    function cardHeaderTitle(type) {
        if (type === 'cash') return 'Cash';
        if (type === 'bank') return 'Bank';
        if (type === 'cheque') return 'Cheque';
        if (type === 'upi') return 'UPI';
        if (type === 'card') return 'Card';
        if (type === 'metal-exchange') return 'MetalExchange';
        if (type === 'scrap') return 'Scrap';
        return 'Other';
    }
    function saveTypeLabel(type) {
        if (type === 'cash') return 'Cash';
        if (type === 'bank') return 'Bank';
        if (type === 'cheque') return 'Cheque';
        if (type === 'upi') return 'UPI';
        if (type === 'card') return 'Card';
        if (type === 'metal-exchange') return 'M. Exch.';
        if (type === 'scrap') return 'Scrap';
        return 'Other';
    }
    function cardSubtitle(p) {
        var t = p.type;
        if (t === 'metal-exchange') {
            return String(p.metal_exchange_product_name || p.deposit_into || '').trim() || 'Metal exchange';
        }
        if (t === 'scrap') {
            return String(p.scrap_product_name || '').trim() || 'Scrap';
        }
        if (t === 'cheque') {
            var bits = [String(p.deposit_into || '').trim(), String(p.transaction_no || '').trim()].filter(Boolean);
            return bits.join(' · ') || 'Cheque';
        }
        if (t === 'bank' || t === 'upi' || t === 'card') {
            return String(p.deposit_into || p.transaction_no || '').trim() || '—';
        }
        if (t === 'cash') {
            return String(p.deposit_into || '').trim() || 'Cash';
        }
        return String(p.deposit_into || p.transaction_no || '').trim() || '—';
    }
    function metalWeightBadge(p) {
        if (p.type !== 'metal-exchange') return '';
        var w = parseFloat(p.metal_exchange_gross_wt);
        if (isNaN(w) || w <= 0) {
            w = parseFloat(p.quantity);
        }
        if (isNaN(w) || w <= 0) return '';
        var ws = (Math.abs(w - Math.round(w)) < 1e-6) ? String(Math.round(w)) : String(w);
        return '<span class="pos-payment-card-wt" title="Gross wt / qty"><i class="feather icon-layers"></i> ' + escHtml(ws) + '</span>';
    }

    function buildCardElement(payment) {
        var row = document.createElement('div');
        row.className = 'pos-payment-card';
        row.id = payment.id;
        row.setAttribute('data-payment-id', payment.id);
        row.setAttribute('data-payment-type', payment.type || 'cash');
        row.setAttribute('data-deposit-into', payment.deposit_into || '');
        row.setAttribute('data-transaction-no', payment.transaction_no || '');
        row.setAttribute('data-cheque-date', payment.cheque_date || '');
        row.setAttribute('data-purity-carat', payment.purity_carat != null ? String(payment.purity_carat) : '');
        row.setAttribute('data-diamond-category', payment.diamond_category || '');
        row.setAttribute('data-quantity', payment.quantity != null && payment.quantity !== '' ? String(payment.quantity) : '0');
        if ((payment.type || '') === 'metal-exchange') {
            row.setAttribute('data-me-metal-id', payment.metal_exchange_metal_id != null ? String(payment.metal_exchange_metal_id) : '');
            var mePc = payment.metal_exchange_characteristic_id != null ? payment.metal_exchange_characteristic_id : payment.metal_exchange_product_id;
            row.setAttribute('data-me-characteristic-id', mePc != null ? String(mePc) : '');
            row.setAttribute('data-me-product-id', payment.metal_exchange_product_id != null ? String(payment.metal_exchange_product_id) : '');
            row.setAttribute('data-me-product-name', payment.metal_exchange_product_name != null ? String(payment.metal_exchange_product_name) : '');
            row.setAttribute('data-me-gross-wt', payment.metal_exchange_gross_wt != null ? String(payment.metal_exchange_gross_wt) : '');
            row.setAttribute('data-me-purity-wt', payment.metal_exchange_purity_wt != null ? String(payment.metal_exchange_purity_wt) : '');
            row.setAttribute('data-me-rate', payment.metal_exchange_rate != null ? String(payment.metal_exchange_rate) : '');
            row.setAttribute('data-me-item-code', payment.metal_exchange_item_code != null ? String(payment.metal_exchange_item_code) : '');
            row.setAttribute('data-me-stock-id', payment.mi_stock_id != null ? String(payment.mi_stock_id) : '');
            row.setAttribute('data-me-purity-carat', payment.purity_carat != null ? String(payment.purity_carat) : '');
        }
        var previousBalanceAmount = parseFloat(payment.previous_balance_amount || 0);
        var curAmt = parseFloat(payment.amount || 0);
        if ((payment.type || '') === 'metal-exchange' && typeof global.jwoMetalExchangeDisplayAmount === 'function') {
            var meDisp = global.jwoMetalExchangeDisplayAmount(payment);
            if (meDisp > 0.00001) {
                curAmt = meDisp;
            }
        }
        var totalPaymentAmount = curAmt + previousBalanceAmount;
        row.setAttribute('data-previous-balance-amount', previousBalanceAmount.toFixed(2));
        row.setAttribute('data-current-order-amount', curAmt.toFixed(2));
        var hdr = cardHeaderTitle(payment.type);
        var sub = cardSubtitle(payment);
        var cc = currencyCode();
        var amtTitle = 'Current: ' + curAmt.toFixed(2) + ', Previous balance: ' + previousBalanceAmount.toFixed(2);
        var wtHtml = metalWeightBadge(payment);
        var isSoMeReadonly = !!payment.readonly_from_sale_order && (payment.type || '') === 'metal-exchange';
        if (isSoMeReadonly) {
            row.setAttribute('data-readonly-sale-order-me', '1');
            row.classList.add('pos-payment-card--so-me-readonly');
        }
        var actionHtml = isSoMeReadonly
            ? '<span class="text-muted small" title="From sale order">SO</span>'
            : '<button type="button" class="btn-edit" title="Edit"><i class="feather icon-edit-2"></i></button>' +
              '<button type="button" class="btn-delete" title="Delete"><i class="feather icon-trash-2"></i></button>';
        row.innerHTML =
            '<div class="pos-payment-card-hd">' +
            '<span class="pos-payment-card-title">' + escHtml(hdr) + '</span>' +
            '<div class="pos-payment-card-actions">' +
            actionHtml +
            '</div></div>' +
            '<div class="pos-payment-card-bd">' +
            '<div class="pos-payment-card-desc">' + escHtml(sub) + '</div>' +
            '<div class="pos-payment-card-amtrow">' +
            '<span data-payment-amount title="' + escAttr(amtTitle) + '">(' + escHtml(cc) + ') ' + escHtml(totalPaymentAmount.toFixed(2)) + '</span>' +
            wtHtml +
            '</div></div>';
        return row;
    }

    function refreshCard(payment) {
        var el = document.getElementById(payment.id);
        if (!el || !el.parentNode) return;
        el.parentNode.replaceChild(buildCardElement(payment), el);
    }

    function syncMetalExchangePaymentFromCard(p) {
        if (!p || (p.type || '') !== 'metal-exchange') {
            return p;
        }
        var card = p.id ? document.getElementById(p.id) : null;
        if (!card) {
            return p;
        }
        var merged = Object.assign({}, p);
        var attrs = [
            ['metal_exchange_metal_id', 'data-me-metal-id'],
            ['metal_exchange_product_id', 'data-me-product-id'],
            ['metal_exchange_product_name', 'data-me-product-name'],
            ['metal_exchange_gross_wt', 'data-me-gross-wt'],
            ['metal_exchange_purity_wt', 'data-me-purity-wt'],
            ['metal_exchange_rate', 'data-me-rate'],
            ['metal_exchange_item_code', 'data-me-item-code'],
            ['mi_stock_id', 'data-me-stock-id']
        ];
        attrs.forEach(function (pair) {
            var v = card.getAttribute(pair[1]);
            if (v !== null && String(v).trim() !== '') {
                merged[pair[0]] = v;
            }
        });
        var pc = card.getAttribute('data-me-purity-carat');
        if (pc !== null && String(pc).trim() !== '') {
            merged.purity_carat = pc;
        }
        var meCid = card.getAttribute('data-me-characteristic-id');
        if (meCid !== null && String(meCid).trim() !== '') {
            merged.metal_exchange_characteristic_id = meCid;
            merged.metal_exchange_product_id = meCid;
        } else if (!merged.metal_exchange_characteristic_id && merged.metal_exchange_product_id) {
            merged.metal_exchange_characteristic_id = merged.metal_exchange_product_id;
        }
        if (!merged.deposit_into) {
            merged.deposit_into = 'Metal Exchange';
        }
        return merged;
    }

    function collectPaymentsForSave() {
        var arr = typeof global.payments !== 'undefined' && Array.isArray(global.payments) ? global.payments : [];
        var out = [];
        arr.forEach(function (p) {
            var row = syncMetalExchangePaymentFromCard(p);
            var prevBalAmt = parseFloat(row.previous_balance_amount) || 0;
            var currentOrderAmt = parseFloat(row.amount) || 0;
            var totalAmt = currentOrderAmt + prevBalAmt;
            /* Every line the user added to `payments` is saved — amount may be 0 (metal exchange, scrap, placeholder). */
            var rowOut = Object.assign({}, row, {
                type: row.type || '',
                readonly_from_sale_order: !!row.readonly_from_sale_order,
                jwo_client_metal_exchange: !!row.jwo_client_metal_exchange,
                mi_client_metal_exchange: !!row.mi_client_metal_exchange,
                mi_stock_id: row.mi_stock_id != null && row.mi_stock_id !== '' ? String(row.mi_stock_id) : '',
                payment_type: saveTypeLabel(row.type),
                deposit_into: row.deposit_into || '',
                transaction_no: row.transaction_no || '',
                cheque_date: row.cheque_date || null,
                purity_carat: row.purity_carat != null ? String(row.purity_carat) : '',
                amount: totalAmt,
                previous_balance_amount: prevBalAmt,
                current_order_amount: currentOrderAmt,
                diamond_category: row.diamond_category || '',
                quantity: parseFloat(row.quantity) || 0,
                metal_exchange_metal_id: row.metal_exchange_metal_id != null ? String(row.metal_exchange_metal_id) : '',
                metal_exchange_characteristic_id: row.metal_exchange_characteristic_id != null
                    ? String(row.metal_exchange_characteristic_id)
                    : (row.metal_exchange_product_id != null ? String(row.metal_exchange_product_id) : ''),
                metal_exchange_product_id: row.metal_exchange_product_id != null ? String(row.metal_exchange_product_id) : '',
                metal_exchange_product_name: row.metal_exchange_product_name != null ? String(row.metal_exchange_product_name) : '',
                metal_exchange_gross_wt: row.metal_exchange_gross_wt != null ? String(row.metal_exchange_gross_wt) : '',
                metal_exchange_purity_wt: row.metal_exchange_purity_wt != null ? String(row.metal_exchange_purity_wt) : '',
                metal_exchange_rate: row.metal_exchange_rate != null ? String(row.metal_exchange_rate) : '',
                metal_exchange_item_code: row.metal_exchange_item_code != null ? String(row.metal_exchange_item_code) : ''
            });
            if (
                document.body
                && document.body.classList.contains('material-issue-page')
                && (rowOut.type || '') === 'metal-exchange'
                && !rowOut.readonly_from_sale_order
                && !rowOut.jwo_client_metal_exchange
            ) {
                rowOut.mi_client_metal_exchange = true;
                rowOut.mi_metal_exchange = 1;
            }
            if (rowOut.mi_stock_id != null && rowOut.mi_stock_id !== '') {
                rowOut.mi_stock_id = String(rowOut.mi_stock_id);
            }
            out.push(rowOut);
        });
        return out;
    }

    function updateFooterTotals() {
        var rows = document.querySelectorAll('#paymentTableBody .pos-payment-card');
        var totalAmount = 0;
        var totalQuantity = 0;
        rows.forEach(function (row) {
            var elAmt = row.querySelector('[data-payment-amount]');
            var amt = parseFloat(String((elAmt && elAmt.textContent) || '0').replace(/[^\d.-]/g, '')) || 0;
            var qty = parseFloat(row.getAttribute('data-quantity') || 0) || 0;
            if (!isNaN(amt)) totalAmount += amt;
            if (!isNaN(qty)) totalQuantity += qty;
        });
        if (isNaN(totalAmount)) totalAmount = 0;
        if (isNaN(totalQuantity)) totalQuantity = 0;
        var ta = document.getElementById('paymentTotalAmount');
        var tq = document.getElementById('paymentTotalQuantity');
        if (ta) ta.textContent = totalAmount.toFixed(2);
        if (tq) tq.textContent = totalQuantity.toFixed(2);
    }

    var NS = global.AuragoldPaymentCards = global.AuragoldPaymentCards || {};
    NS.buildCardElement = buildCardElement;
    NS.refreshCard = refreshCard;
    NS.collectPaymentsForSave = collectPaymentsForSave;
    NS.updateFooterTotals = updateFooterTotals;
    NS.saveTypeLabel = saveTypeLabel;

    global.buildPosPaymentCardElement = buildCardElement;
    global.refreshPosPaymentCard = refreshCard;
    global.collectPosPaymentsForSave = collectPaymentsForSave;

    if (!global._auragoldPaymentCardDocClick) {
        global._auragoldPaymentCardDocClick = true;
        document.addEventListener('click', function (ev) {
            var host = document.getElementById('paymentTableBody');
            if (!host || !host.contains(ev.target)) return;
            var t = ev.target.closest && ev.target.closest('.pos-payment-card .btn-delete, .pos-payment-card .btn-edit');
            if (!t) return;
            var card = t.closest('.pos-payment-card');
            if (!card || !host.contains(card)) return;
            var pid = card.getAttribute('data-payment-id') || card.id;
            if (!pid) return;
            ev.preventDefault();
            ev.stopPropagation();
            if (t.classList.contains('btn-delete')) {
                if (typeof global.deletePayment === 'function') global.deletePayment(pid);
            } else if (t.classList.contains('btn-edit') && typeof global.editPayment === 'function') {
                global.editPayment(pid);
            }
        });
    }
})(typeof window !== 'undefined' ? window : this);
