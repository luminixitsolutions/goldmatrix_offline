/**
 * Shared GST line helpers (tbl_product_tax + owner vs customer state).
 * Expects window.AURAGOLD_GST_STATE_BY_NAME, window.ownerState, window.AURAGOLD_SALE_INVOICE_OWNER_STATE from PHP bootstrap.
 */
(function (global) {
    'use strict';

    global.auragoldGstLineTaxesFromProductPayload = function (p) {
        if (!p || !p.gst_tax_breakdown) return '';
        try {
            var b = p.gst_tax_breakdown;
            var a = [].concat(b.local_state || [], b.out_of_state || []);
            return JSON.stringify(a.map(function (t) {
                var dv = parseFloat(t.default_value) || 0;
                return {
                    name: String(t.name || ''),
                    percent: dv,
                    default_value: dv,
                    gst_supply_scope: String(t.gst_supply_scope || '').trim()
                };
            }));
        } catch (e) {
            return '';
        }
    };

    global.auragoldNormalizeProductTaxLineForGst = function (t) {
        if (!t || typeof t !== 'object') return null;
        var tt = String(t.tax_type != null ? t.tax_type : (t.name != null ? t.name : '')).trim();
        var pct = parseFloat(t.tax_value != null ? t.tax_value : (t.percent != null ? t.percent : t.default_value));
        if (isNaN(pct)) pct = 0;
        var tn = tt.toLowerCase();
        var scope = String(t.gst_supply_scope || '').trim();
        /* tax_type name overrides wrong/missing scope (Tax Master may default IGST to local). */
        if (tn === 'igst') scope = 'out_of_state';
        else if (tn === 'cgst' || tn === 'sgst') scope = 'local_state';
        return {
            name: tt !== '' ? tt : String(t.name || ''),
            tax_type: tt,
            tax_value: pct,
            percent: pct,
            default_value: pct,
            gst_supply_scope: scope
        };
    };

    global.auragoldGstProductTaxesFromProductPayload = function (p) {
        if (!p) return [];
        if (p.taxes && Array.isArray(p.taxes)) {
            if (p.taxes.length === 0) return [];
            return p.taxes.map(function (t) {
                return global.auragoldNormalizeProductTaxLineForGst(t);
            }).filter(function (x) { return x != null; });
        }
        if (!p.gst_tax_breakdown) return [];
        var b = p.gst_tax_breakdown;
        var a = [].concat(b.local_state || [], b.out_of_state || []);
        return a.map(function (t) {
            return global.auragoldNormalizeProductTaxLineForGst({
                name: t.name,
                tax_type: t.name,
                tax_value: t.default_value,
                default_value: t.default_value,
                gst_supply_scope: t.gst_supply_scope
            });
        }).filter(function (x) { return x != null; });
    };

    global.auragoldBindProductTaxesToRowFromApi = function (row, product) {
        if (!row) return;
        var p = product || {};
        var taxArr = [];
        if (p.taxes != null && Array.isArray(p.taxes)) {
            taxArr = p.taxes.map(function (t) {
                return typeof global.auragoldNormalizeProductTaxLineForGst === 'function'
                    ? global.auragoldNormalizeProductTaxLineForGst(t)
                    : t;
            }).filter(function (x) { return x != null; });
        } else if (typeof global.auragoldGstProductTaxesFromProductPayload === 'function') {
            taxArr = global.auragoldGstProductTaxesFromProductPayload(p) || [];
        }
        row.setAttribute('data-product-taxes', JSON.stringify(taxArr));
    };

    global.auragoldGstSetProductTaxesAttrOnRow = function (row, product) {
        global.auragoldBindProductTaxesToRowFromApi(row, product);
    };

    global.auragoldGetProductDetailsUrl = function (productId, characteristicId, metalId) {
        var pid = parseInt(String(productId != null ? productId : ''), 10);
        if (isNaN(pid) || pid <= 0) return '';
        var params = [];
        params.push('product_id=' + encodeURIComponent(String(pid)));
        var cid = parseInt(String(characteristicId != null ? characteristicId : ''), 10);
        if (!isNaN(cid) && cid > 0) params.push('characteristic_id=' + encodeURIComponent(String(cid)));
        var mid = parseInt(String(metalId != null ? metalId : ''), 10);
        if (!isNaN(mid) && mid > 0) params.push('metal_id=' + encodeURIComponent(String(mid)));
        return 'ajax/get-product-details.php?' + params.join('&');
    };

    /**
     * GST % from data-product-taxes: scope resolution + productTaxes;
     * same state → CGST+SGST only; different state → IGST; unknown → no double-counting CGST+SGST+IGST.
     */
    global.applyGSTForRow = function (row) {
        if (!row || !row.querySelector) return;
        var taxInput = row.querySelector('[data-column="tax-percent"] input');
        if (!taxInput) return;
        var taxes = [];
        try {
            taxes = JSON.parse(row.getAttribute('data-product-taxes') || '[]');
        } catch (e) {
            taxes = [];
        }
        if (!Array.isArray(taxes)) taxes = [];

        var custEl = document.getElementById('customerBillingState');
        var custStr = custEl ? String(custEl.value || '').trim() : '';
        if (!custStr) custStr = String((global.customerState != null ? global.customerState : '') || '').trim();
        var ownerStr = String((global.ownerState != null ? global.ownerState : '') || '').trim();
        if (!ownerStr && global.AURAGOLD_SALE_INVOICE_OWNER_STATE) {
            ownerStr = String(global.AURAGOLD_SALE_INVOICE_OWNER_STATE || '').trim();
        }
        function norm(x) { return String(x || '').trim().toLowerCase().replace(/\s+/g, ' '); }
        var map = global.AURAGOLD_GST_STATE_BY_NAME || {};
        var oN = norm(ownerStr);
        var cN = norm(custStr);
        var statesKnown = oN && cN;
        var isLocal = false;
        if (statesKnown) {
            var oid = map[oN];
            var cid = map[cN];
            isLocal = (oid != null && cid != null && oid === cid) || (oN === cN);
        }

        var localScopeSum = 0;
        var outScopeSum = 0;
        var masterList = (typeof global.productTaxes !== 'undefined' && global.productTaxes) ? global.productTaxes : [];
        function resolveScopeForLine(t) {
            var rawName = String(t.tax_type != null ? t.tax_type : (t.name != null ? t.name : '')).trim();
            var tn0 = rawName.toLowerCase();
            if (tn0 === 'igst') return 'out_of_state';
            if (tn0 === 'cgst' || tn0 === 'sgst') return 'local_state';
            var s = String(t.gst_supply_scope != null ? t.gst_supply_scope : '').trim();
            if (s === 'local_state' || s === 'out_of_state') return s;
            var i;
            for (i = 0; i < masterList.length; i++) {
                var m = masterList[i];
                if (!m || !m.name || !rawName) continue;
                if (String(m.name).trim().toLowerCase() === rawName.toLowerCase()) {
                    var mSc = String(m.gst_supply_scope != null ? m.gst_supply_scope : '').trim();
                    if (mSc === 'out_of_state' || mSc === 'local_state') return mSc;
                    if (rawName.toLowerCase() === 'igst') return 'out_of_state';
                    if (rawName.toLowerCase() === 'cgst' || rawName.toLowerCase() === 'sgst') return 'local_state';
                    return mSc === 'out_of_state' ? 'out_of_state' : 'local_state';
                }
            }
            if (typeof global.auragoldNormalizeProductTaxLineForGst === 'function') {
                var n2 = global.auragoldNormalizeProductTaxLineForGst(t);
                if (n2 && n2.gst_supply_scope) return String(n2.gst_supply_scope).trim();
            }
            if (rawName.toLowerCase() === 'igst') return 'out_of_state';
            if (rawName.toLowerCase() === 'cgst' || rawName.toLowerCase() === 'sgst') return 'local_state';
            return '';
        }
        function lineVal(t) {
            var v = parseFloat(t.tax_value != null ? t.tax_value : (t.percent != null ? t.percent : t.default_value));
            return isNaN(v) ? 0 : v;
        }
        function lineName(t) {
            return String(t.tax_type != null ? t.tax_type : (t.name != null ? t.name : '')).trim().toLowerCase();
        }
        var namedCgstSgst = 0;
        var namedIgst = 0;
        taxes.forEach(function (t) {
            var val = lineVal(t);
            var sc = resolveScopeForLine(t);
            if (sc === 'local_state') localScopeSum += val;
            else if (sc === 'out_of_state') outScopeSum += val;
            var nn = lineName(t);
            if (nn === 'cgst' || nn === 'sgst') namedCgstSgst += val;
            if (nn === 'igst') namedIgst += val;
        });

        var totalTax = 0;
        if (!statesKnown) {
            if (namedCgstSgst > 0 && namedIgst > 0) {
                totalTax = Math.max(namedCgstSgst, namedIgst);
            } else if (namedCgstSgst > 0) {
                totalTax = namedCgstSgst;
            } else if (namedIgst > 0) {
                totalTax = namedIgst;
            } else {
                totalTax = Math.max(localScopeSum, outScopeSum);
            }
        } else if (isLocal) {
            totalTax = (namedCgstSgst > 0) ? namedCgstSgst : localScopeSum;
        } else {
            totalTax = (namedIgst > 0) ? namedIgst : outScopeSum;
        }

        if (totalTax <= 0) {
            var effFb = typeof global.auragoldSaleInvoiceResolveGstEffectivePercent === 'function'
                ? global.auragoldSaleInvoiceResolveGstEffectivePercent(row) : NaN;
            if (!isNaN(effFb) && effFb > 0) {
                totalTax = effFb;
            } else {
                var Lg2 = parseFloat(row.getAttribute('data-gst-local-pct'));
                var Ig2 = parseFloat(row.getAttribute('data-gst-interstate-pct'));
                var Sg2 = parseFloat(row.getAttribute('data-gst-invoice-slab-pct'));
                var mli2 = Math.max(isNaN(Lg2) ? 0 : Lg2, isNaN(Ig2) ? 0 : Ig2);
                var mxg2 = mli2 > 0.0001 ? mli2 : (isNaN(Sg2) ? 0 : Sg2);
                if (mxg2 > 0) totalTax = mxg2;
            }
        }

        if (totalTax > 0) {
            taxInput.value = totalTax.toFixed(2);
            taxInput.dataset.baseGst = String(totalTax);
        } else {
            taxInput.value = '0.00';
            if (taxInput.dataset && taxInput.dataset.baseGst) delete taxInput.dataset.baseGst;
        }
    };

    global.afterRowAmountsCalculated = function (row) {
        if (typeof updateSummaryRow === 'function') updateSummaryRow();
        if (typeof updateSummaryPanel === 'function') updateSummaryPanel();
    };

    global.auragoldSaleInvoiceResolveGstEffectivePercent = function (row) {
        if (!row) return NaN;
        function parseAttrPct(attr) {
            var raw = row.getAttribute(attr);
            if (raw === null || raw === '') return NaN;
            var n = parseFloat(raw);
            return isNaN(n) ? NaN : n;
        }
        var loc = parseAttrPct('data-gst-local-pct');
        var inter = parseAttrPct('data-gst-interstate-pct');
        var slab = parseAttrPct('data-gst-invoice-slab-pct');
        var mli0 = Math.max(isNaN(loc) ? 0 : loc, isNaN(inter) ? 0 : inter);
        if (isNaN(slab) || slab <= 0) {
            slab = mli0;
        } else if (mli0 > 0.0001 && slab > mli0 + 0.0001) {
            /* Slab can be a legacy total (CGST+SGST+IGST); effective = max(local, interstate) only. */
            slab = mli0;
        }
        if (isNaN(slab) || slab <= 0) return NaN;
        var custEl = document.getElementById('customerBillingState');
        var custFromEl = custEl ? String(custEl.value || '').trim() : '';
        var owner = String((global.ownerState != null && String(global.ownerState).trim() !== '') ? global.ownerState : (global.AURAGOLD_SALE_INVOICE_OWNER_STATE || '')).trim();
        var cust = custFromEl || String(global.customerState != null ? global.customerState : '').trim();
        function norm(x) { return String(x || '').trim().toLowerCase().replace(/\s+/g, ' '); }
        var map = global.AURAGOLD_GST_STATE_BY_NAME || {};
        var sameState = null;
        if (owner && cust) {
            var oid = map[norm(owner)];
            var cid = map[norm(cust)];
            sameState = (oid != null && cid != null && oid === cid) || (norm(owner) === norm(cust));
        }
        if (sameState === null) return slab;
        if (sameState === true) return (!isNaN(loc) && loc > 0) ? loc : slab;
        return (!isNaN(inter) && inter > 0) ? inter : slab;
    };

    global.auragoldSaleInvoiceEnsureGstBaseDataset = function (row, taxPercentInput) {
        if (!taxPercentInput || !row) return;
        var eff = typeof global.auragoldSaleInvoiceResolveGstEffectivePercent === 'function'
            ? global.auragoldSaleInvoiceResolveGstEffectivePercent(row)
            : NaN;
        if (!isNaN(eff) && eff > 0) taxPercentInput.dataset.baseGst = String(eff);
    };

    global.getSaleInvoiceRowEffectiveTaxPercent = function (row, taxPercentInput) {
        var eff = typeof global.auragoldSaleInvoiceResolveGstEffectivePercent === 'function'
            ? global.auragoldSaleInvoiceResolveGstEffectivePercent(row)
            : NaN;
        if (!isNaN(eff) && eff > 0) {
            if (taxPercentInput && taxPercentInput.dataset) taxPercentInput.dataset.baseGst = String(eff);
            return eff;
        }
        if (row) {
            var Lg = parseFloat(row.getAttribute('data-gst-local-pct'));
            var Ig = parseFloat(row.getAttribute('data-gst-interstate-pct'));
            var Sg = parseFloat(row.getAttribute('data-gst-invoice-slab-pct'));
            var mli = Math.max(isNaN(Lg) ? 0 : Lg, isNaN(Ig) ? 0 : Ig);
            var mxg = mli > 0.0001 ? mli : (isNaN(Sg) ? 0 : Sg);
            if (mxg > 0) {
                if (taxPercentInput && taxPercentInput.dataset) taxPercentInput.dataset.baseGst = String(mxg);
                return mxg;
            }
        }
        if (taxPercentInput) {
            var tv = String(taxPercentInput.value || '').trim();
            if (tv.indexOf('+') !== -1) {
                var parts = tv.split('+');
                var a = parseFloat(parts[0].trim()) || 0;
                var b = parseFloat(parts[1] ? parts[1].trim() : '') || 0;
                if (a > 0 && b > 0) return a + b;
                if (a > 0) return a * 2;
            }
            var pf = parseFloat(tv);
            if (!isNaN(pf) && pf > 0) return pf;
        }
        return 0;
    };

    global.setSaleInvoiceGstTaxPercentDisplay = function (row, taxPercentInput) {
        if (!taxPercentInput || !row) return;
        if (typeof global.applyGSTForRow === 'function') {
            global.applyGSTForRow(row);
            return;
        }
        var eff = typeof global.getSaleInvoiceRowEffectiveTaxPercent === 'function'
            ? global.getSaleInvoiceRowEffectiveTaxPercent(row, taxPercentInput)
            : NaN;
        if (isNaN(eff) || eff <= 0) return;
        taxPercentInput.dataset.baseGst = String(eff);
        var custEl = document.getElementById('customerBillingState');
        var custFromEl = custEl ? String(custEl.value || '').trim() : '';
        var owner = String((global.ownerState != null && String(global.ownerState).trim() !== '') ? global.ownerState : (global.AURAGOLD_SALE_INVOICE_OWNER_STATE || '')).trim();
        var cust = custFromEl || String(global.customerState != null ? global.customerState : '').trim();
        function norm(x) { return String(x || '').trim().toLowerCase().replace(/\s+/g, ' '); }
        if (!owner || !cust) {
            taxPercentInput.value = eff.toFixed(2);
            taxPercentInput.setAttribute('title', 'GST base ' + eff.toFixed(2) + '% — select customer for local (CGST+SGST) vs IGST');
            return;
        }
        var map = global.AURAGOLD_GST_STATE_BY_NAME || {};
        var oid = map[norm(owner)];
        var cid = map[norm(cust)];
        var sameState = (oid != null && cid != null && oid === cid) || (norm(owner) === norm(cust));
        taxPercentInput.value = eff.toFixed(2);
        if (sameState) {
            taxPercentInput.setAttribute('title', 'GST ' + eff.toFixed(2) + '% — CGST + SGST (local)');
        } else {
            taxPercentInput.setAttribute('title', 'IGST ' + eff.toFixed(2) + '%');
        }
    };

    global.auragoldGstInvoiceSlabFromProductPayload = function (p) {
        if (!p) return '';
        var a = parseFloat(p.gst_local_percent) || 0;
        var b = parseFloat(p.gst_interstate_percent) || 0;
        var mx = Math.max(a, b);
        if (mx > 0) {
            return String(mx);
        }
        if (p.gst_invoice_slab_percent != null && p.gst_invoice_slab_percent !== '') {
            return String(p.gst_invoice_slab_percent);
        }
        return '';
    };

    global.auragoldSaleInvoiceRefreshGstForAllRows = function () {
        document.querySelectorAll('#productTableBody tr:not(.no-drag)').forEach(function (r) {
            if (typeof calculateRowAmounts === 'function') calculateRowAmounts(r);
        });
        document.querySelectorAll('#productListBody .product-row').forEach(function (r) {
            if (typeof calculateModalRowNetWeight === 'function') calculateModalRowNetWeight(r);
        });
    };

    global.saleInvoiceHasCustomerSelected = function () {
        var el = document.getElementById('customerId');
        if (!el) return false;
        var v = '';
        if (typeof global.jQuery !== 'undefined' && el.tagName === 'SELECT' && global.jQuery(el).hasClass('select2-hidden-accessible')) {
            v = String(global.jQuery(el).val() || '').trim();
        } else {
            v = String(el.value || '').trim();
        }
        if (v === '' && typeof global.selectedCustomerId !== 'undefined' && global.selectedCustomerId) {
            v = String(global.selectedCustomerId).trim();
        }
        return v !== '' && parseInt(v, 10) > 0;
    };

    global.updateSaleInvoiceAddItemButtonState = function () {
        var ok = global.saleInvoiceHasCustomerSelected();
        var addItemOk = !!global.AURAGOLD_ADD_ITEM_ALWAYS_ENABLED || ok;
        var btn = document.getElementById('addItemBtn');
        if (btn) {
            btn.classList.toggle('add-item-link--disabled', !addItemOk);
            btn.setAttribute('aria-disabled', addItemOk ? 'false' : 'true');
            btn.setAttribute('title', addItemOk ? 'Add lines to this document' : 'Select a customer first');
            btn.style.cursor = addItemOk ? '' : 'not-allowed';
            var a = btn.querySelector('a');
            if (a) {
                a.setAttribute('aria-disabled', addItemOk ? 'false' : 'true');
                a.setAttribute('tabindex', addItemOk ? '0' : '-1');
            }
        }
        ['siAddProductBtn'].forEach(function (id) {
            var b = document.getElementById(id);
            if (!b) return;
            b.disabled = !addItemOk;
            b.classList.toggle('si-disabled', !addItemOk);
            b.setAttribute('title', addItemOk ? 'Add product' : 'Select a customer first');
        });
        document.querySelectorAll('[data-require-customer="1"]').forEach(function (el) {
            el.disabled = !ok;
        });
    };

    if (global.jQuery) {
        global.jQuery(function ($) {
            $(document).on('click', '.customer-suggestion-item', function () {
                var bs = $(this).attr('data-billing-state') || '';
                var cbs = document.getElementById('customerBillingState');
                if (cbs) cbs.value = bs;
                global.customerState = bs;
                if (typeof global.updateSaleInvoiceAddItemButtonState === 'function') {
                    global.updateSaleInvoiceAddItemButtonState();
                }
                if (typeof global.auragoldSaleInvoiceRefreshGstForAllRows === 'function') {
                    global.auragoldSaleInvoiceRefreshGstForAllRows();
                }
            });
            $(document).on('input change', '#customerBillingState', function () {
                if (typeof global.auragoldSaleInvoiceRefreshGstForAllRows === 'function') {
                    global.auragoldSaleInvoiceRefreshGstForAllRows();
                }
            });
            $(document).on('change input', '#customerId', function () {
                if (typeof global.updateSaleInvoiceAddItemButtonState === 'function') {
                    global.updateSaleInvoiceAddItemButtonState();
                }
            });
            if (typeof global.updateSaleInvoiceAddItemButtonState === 'function') {
                global.updateSaleInvoiceAddItemButtonState();
            }
        });
    }
})(typeof window !== 'undefined' ? window : this);
