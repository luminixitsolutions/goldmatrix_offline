/**
 * Material Receive: receive metal exchange (gold/silver) issued on Material Issue.
 */
(function () {
    'use strict';

    window.__pendingMaterialReceiveMetalExchange = window.__pendingMaterialReceiveMetalExchange || [];

    function mrMeFmt(n, dec) {
        var x = parseFloat(n);
        if (!isFinite(x)) {
            return '';
        }
        return x.toFixed(dec != null ? dec : 3);
    }

    function mrMeNormalizePaymentRow(p) {
        if (!p || (p.type || '') !== 'metal-exchange') {
            return null;
        }
        var mid = parseInt(p.metal_exchange_metal_id || p.metal_id || '0', 10) || 0;
        var pcid = parseInt(p.metal_exchange_product_id || p.product_id || '0', 10) || 0;
        if (mid < 1 || pcid < 1) {
            return null;
        }
        var gw = parseFloat(p.metal_exchange_gross_wt || p.gross_weight || '0') || 0;
        var pw = parseFloat(p.metal_exchange_purity_wt || p.purity_weight || '0') || 0;
        if (gw <= 0.0000001) {
            gw = parseFloat(p.quantity || '0') || 0;
        }
        if (gw <= 0.0000001) {
            return null;
        }
        if (pw <= 0.0000001) {
            pw = gw;
        }
        return {
            issue_stock_id: parseInt(p.metal_exchange_source_stock_id || '0', 10) || 0,
            material_issue_id: 0,
            barcode: p.metal_exchange_item_code || p.item_code || '',
            product_name: p.metal_exchange_product_name || p.product_name || '',
            metal_id: mid,
            product_characteristic_id: pcid,
            metal_name: p.metal_name || 'Gold',
            issued_gross: gw,
            issued_pure: pw,
            issued_weight: gw,
            received_gross: 0,
            balance_gross: gw,
            balance_weight: gw,
            purity: p.purity_carat || p.purity || '',
            reference_status: 'to_receive',
            me_source: 'payment_card',
            issue_source_label: 'Payment card',
            from_payment: true
        };
    }

    function mrMeRows() {
        return Array.isArray(window.MATERIAL_RECEIVE_ISSUED_METAL_EXCHANGE)
            ? window.MATERIAL_RECEIVE_ISSUED_METAL_EXCHANGE.slice()
            : [];
    }

    /** Keep PHP-embedded rows when AJAX refresh returns empty (avoids flash-then-hide). */
    function mrApplyMetalExchangeFromServer(data) {
        if (!data || !data.ok) {
            return;
        }
        var incoming = Array.isArray(data.metal_exchange) ? data.metal_exchange : [];
        var current = mrMeRows();
        if (incoming.length > 0) {
            window.MATERIAL_RECEIVE_ISSUED_METAL_EXCHANGE = incoming;
        } else if (current.length > 0) {
            return;
        }
    }

    window.mrApplyMetalExchangeFromServer = mrApplyMetalExchangeFromServer;

    function mrMePendingKey(line) {
        return (
            String(line.metal_exchange_metal_id || line.metal_id || '') +
            '|' +
            String(line.metal_exchange_product_id || line.product_characteristic_id || '') +
            '|' +
            String(line.metal_exchange_item_code || line.barcode || '').toLowerCase() +
            '|' +
            String(line.source_issue_stock_id || line.issue_stock_id || '')
        );
    }

    function mrMeUpsertPending(line) {
        var list = window.__pendingMaterialReceiveMetalExchange;
        var key = mrMePendingKey(line);
        var found = -1;
        for (var i = 0; i < list.length; i++) {
            if (mrMePendingKey(list[i]) === key) {
                found = i;
                break;
            }
        }
        if (found >= 0) {
            list[found].metal_exchange_gross_wt = String(
                (parseFloat(list[found].metal_exchange_gross_wt) || 0) + (parseFloat(line.metal_exchange_gross_wt) || 0)
            );
            list[found].metal_exchange_purity_wt = String(
                (parseFloat(list[found].metal_exchange_purity_wt) || 0) + (parseFloat(line.metal_exchange_purity_wt) || 0)
            );
        } else {
            list.push(line);
        }
    }

    /** Modal / external: queue ME receive without showing a payment card. */
    function mrMeQueuePaymentLine(payment) {
        if (!payment || (payment.type || '') !== 'metal-exchange') {
            return;
        }
        var gw = parseFloat(payment.metal_exchange_gross_wt || '0') || 0;
        var pw = parseFloat(payment.metal_exchange_purity_wt || '0') || gw;
        if (gw <= 0.0000001) {
            return;
        }
        mrMeUpsertPending({
            metal_exchange_metal_id: payment.metal_exchange_metal_id || '',
            metal_exchange_product_id: payment.metal_exchange_product_id || '',
            metal_exchange_product_name: payment.metal_exchange_product_name || '',
            metal_exchange_gross_wt: String(gw),
            metal_exchange_purity_wt: String(pw),
            metal_exchange_rate: payment.metal_exchange_rate || '0',
            metal_exchange_item_code: payment.metal_exchange_item_code || '',
            metal_exchange_source_stock_id: payment.metal_exchange_source_stock_id || '',
            purity_carat: payment.purity_carat || '1',
            quantity: payment.quantity || 1
        });
    }

    function mrMePendingToSavePayments() {
        return (window.__pendingMaterialReceiveMetalExchange || []).map(function (line) {
            line = mrMeResolvePendingIds(Object.assign({}, line));
            return {
                type: 'metal-exchange',
                payment_type: 'metal-exchange',
                deposit_into: 'Metal Exchange',
                amount: 0,
                quantity: parseFloat(line.quantity) || 1,
                purity_carat: line.purity_carat != null ? String(line.purity_carat) : '1',
                metal_exchange_metal_id: line.metal_exchange_metal_id || '',
                metal_exchange_product_id: line.metal_exchange_product_id || '',
                metal_exchange_product_name: line.metal_exchange_product_name || '',
                metal_exchange_gross_wt: line.metal_exchange_gross_wt || '0',
                metal_exchange_purity_wt: line.metal_exchange_purity_wt || '0',
                metal_exchange_rate: line.metal_exchange_rate || '0',
                metal_exchange_item_code: line.metal_exchange_item_code || '',
                metal_exchange_source_stock_id: line.metal_exchange_source_stock_id || line.source_issue_stock_id || ''
            };
        });
    }

    window.mrMeQueuePaymentLine = mrMeQueuePaymentLine;
    window.mrMePendingMetalExchangeForSave = mrMePendingToSavePayments;

    function mrMeStatusBadge(st, queued) {
        if (queued) {
            return '<span class="mr-issued-badge mr-issued-badge--queued">Queued</span>';
        }
        var s = String(st || '').toLowerCase();
        if (s === 'fully_received' || s === 'received') {
            return '<span class="mr-issued-badge mr-issued-badge--received">Received</span>';
        }
        if (s === 'partial') {
            return '<span class="mr-issued-badge mr-issued-badge--partial">Partial receive</span>';
        }
        return '<span class="mr-issued-badge mr-issued-badge--to-receive">To receive</span>';
    }

    function mrMePendingReceiveBadgeHtml() {
        return '<span class="mr-issued-badge mr-issued-badge--pending">Pending receive — saves on Save</span>';
    }

    function mrMePendingMatchesRow(p, r) {
        var sid = parseInt(p.metal_exchange_source_stock_id || p.source_issue_stock_id || '0', 10) || 0;
        var rid = parseInt(r.issue_stock_id, 10) || 0;
        if (sid > 0 && rid > 0 && sid === rid) {
            return true;
        }
        var pbc = (p.mr_display_barcode || p.metal_exchange_item_code || '').toLowerCase();
        var rbc = (r.barcode || '').toLowerCase();
        return pbc !== '' && rbc !== '' && pbc === rbc;
    }

    function mrMeResolvePendingIds(line) {
        var mid = parseInt(line.metal_exchange_metal_id || '0', 10) || 0;
        var pcid = parseInt(line.metal_exchange_product_id || '0', 10) || 0;
        if (mid >= 1 && pcid >= 1) {
            return line;
        }
        var sid = parseInt(line.metal_exchange_source_stock_id || line.source_issue_stock_id || '0', 10) || 0;
        var bc = (line.metal_exchange_item_code || line.mr_display_barcode || '').toLowerCase();
        mrMeRows().forEach(function (r) {
            var rid = parseInt(r.issue_stock_id, 10) || 0;
            if (sid > 0 && rid === sid) {
                if (mid < 1 && r.metal_id) {
                    line.metal_exchange_metal_id = String(r.metal_id);
                }
                if (pcid < 1 && r.product_characteristic_id) {
                    line.metal_exchange_product_id = String(r.product_characteristic_id);
                }
                if (!line.metal_exchange_product_name && r.product_name) {
                    line.metal_exchange_product_name = r.product_name;
                }
                if (!line.mr_display_metal && r.metal_name) {
                    line.mr_display_metal = r.metal_name;
                }
                if (!line.mr_display_product && r.product_name) {
                    line.mr_display_product = r.product_name;
                }
                if (!line.mr_display_barcode && r.barcode) {
                    line.mr_display_barcode = r.barcode;
                }
            } else if (bc && (r.barcode || '').toLowerCase() === bc) {
                if (mid < 1 && r.metal_id) {
                    line.metal_exchange_metal_id = String(r.metal_id);
                }
                if (pcid < 1 && r.product_characteristic_id) {
                    line.metal_exchange_product_id = String(r.product_characteristic_id);
                }
            }
        });
        return line;
    }

    function mrMeAppendPendingRow(tbody, p) {
        var gw = parseFloat(p.metal_exchange_gross_wt) || 0;
        var pw = parseFloat(p.metal_exchange_purity_wt) || gw;
        var trP = document.createElement('tr');
        trP.className = 'mr-me-pending-receive-row';
        trP.style.background = '#fffbeb';
        var tdEmptyChk = document.createElement('td');
        tdEmptyChk.className = 'text-center';
        trP.appendChild(tdEmptyChk);
        var productLabel = p.mr_display_product || p.metal_exchange_product_name || '—';
        var pendCells = [
            p.mr_display_source || 'Material issue',
            p.mr_display_metal || p.metal_exchange_metal_name || '—',
            productLabel,
            p.mr_display_barcode || p.metal_exchange_item_code || '—',
            mrMeFmt(gw, 3),
            mrMeFmt(pw, 3),
            '—',
            mrMeFmt(gw, 3)
        ];
        pendCells.forEach(function (txt, idx) {
            var td = document.createElement('td');
            if (idx >= 3) {
                td.className = 'text-right';
            }
            td.textContent = txt;
            trP.appendChild(td);
        });
        var tdPendSt = document.createElement('td');
        tdPendSt.innerHTML = mrMePendingReceiveBadgeHtml();
        trP.appendChild(tdPendSt);
        tbody.appendChild(trP);
    }

    function mrMeBindRow(tr, r) {
        var bal = parseFloat(r.balance_gross != null ? r.balance_gross : r.balance_weight) || 0;
        var stockId = parseInt(r.issue_stock_id, 10) || 0;
        tr.setAttribute('data-issue-stock-id', String(stockId > 0 ? stockId : ''));
        tr.setAttribute('data-metal-id', String(r.metal_id || ''));
        tr.setAttribute('data-product-characteristic-id', String(r.product_characteristic_id || ''));
        tr.setAttribute('data-product-name', r.product_name || '');
        tr.setAttribute('data-metal-name', r.metal_name || '');
        tr.setAttribute('data-balance-gross', String(bal));
        tr.setAttribute('data-issued-gross', String(r.issued_gross || r.issued_weight || 0));
        tr.setAttribute('data-issued-pure', String(r.issued_pure || 0));
        tr.setAttribute('data-purity', String(r.purity != null ? r.purity : ''));
        tr.setAttribute('data-barcode', r.barcode || '');
    }

    function mrMeRenderTable() {
        var tbody = document.getElementById('mrIssuedMetalExchangeTbody');
        var emptyEl = document.getElementById('mrIssuedMetalExchangeEmpty');
        if (!tbody) {
            return;
        }
        var rows = mrMeRows().filter(function (r) {
            return r && typeof r === 'object';
        });
        var pending = (window.__pendingMaterialReceiveMetalExchange || []).slice();
        var pendingMatched = {};
        tbody.innerHTML = '';
        if (!rows.length && !pending.length) {
            if (emptyEl) {
                emptyEl.style.display = 'none';
            }
            if (typeof window.mrAppendIssuedEmptyRow === 'function') {
                window.mrAppendIssuedEmptyRow(tbody, 'metal', 10);
            } else if (emptyEl) {
                emptyEl.style.display = 'block';
            }
            return;
        }
        if (emptyEl) {
            emptyEl.style.display = 'none';
        }
        rows.forEach(function (r) {
            try {
                var bal = parseFloat(r.balance_gross != null ? r.balance_gross : r.balance_weight);
                if (!isFinite(bal)) {
                    bal = parseFloat(r.issued_gross != null ? r.issued_gross : r.issued_weight) || 0;
                }
                var tr = document.createElement('tr');
                tr.className = 'mr-me-issued-receive-row';
                mrMeBindRow(tr, r);
                var disabled = bal <= 0.0000001;
                if (disabled) {
                    tr.classList.add('text-muted');
                }
                var chk = document.createElement('input');
                chk.type = 'checkbox';
                chk.className = 'mr-me-issued-receive-chk';
                chk.disabled = disabled;
                var tdChk = document.createElement('td');
                tdChk.className = 'text-center';
                tdChk.appendChild(chk);
                tr.appendChild(tdChk);
                var cells = [
                    r.issue_source_label || r.me_source || 'Material issue',
                    r.metal_name || '—',
                    r.product_name || '—',
                    r.barcode || '—',
                    mrMeFmt(r.issued_gross || r.issued_weight, 3),
                    mrMeFmt(r.received_gross, 3),
                    mrMeFmt(bal, 3)
                ];
                cells.forEach(function (txt, idx) {
                    var td = document.createElement('td');
                    if (idx >= 3) {
                        td.className = 'text-right';
                    }
                    td.textContent = txt;
                    tr.appendChild(td);
                });
                var tdWt = document.createElement('td');
                tdWt.className = 'text-right';
                var inp = document.createElement('input');
                inp.type = 'number';
                inp.step = '0.001';
                inp.min = '0';
                inp.className = 'form-control form-control-sm text-right mr-me-issued-receive-wt';
                inp.value = disabled ? '' : mrMeFmt(bal, 3);
                inp.disabled = disabled;
                tdWt.appendChild(inp);
                tr.appendChild(tdWt);
                var tdSt = document.createElement('td');
                tdSt.innerHTML = mrMeStatusBadge(
                    r.reference_status,
                    tr.getAttribute('data-queued-receive') === '1'
                );
                tr.appendChild(tdSt);
                tbody.appendChild(tr);
                pending.forEach(function (p, pidx) {
                    if (!mrMePendingMatchesRow(p, r)) {
                        return;
                    }
                    pendingMatched[pidx] = true;
                    mrMeAppendPendingRow(tbody, p);
                });
            } catch (renderErr) {
                console.warn('Material Receive ME row render failed', renderErr, r);
            }
        });
        pending.forEach(function (p, pidx) {
            if (pendingMatched[pidx]) {
                return;
            }
            mrMeAppendPendingRow(tbody, p);
        });
        if (typeof window.mrUpdateIssuedReceiveTabCounts === 'function') {
            window.mrUpdateIssuedReceiveTabCounts();
        }
    }

    function mrMeBuildPendingLine(row, recvGross) {
        var issuedGross = parseFloat(row.getAttribute('data-issued-gross') || '0') || 0;
        var issuedPure = parseFloat(row.getAttribute('data-issued-pure') || '0') || 0;
        var recvPure = recvGross;
        if (issuedGross > 0.0000001 && issuedPure > 0.0000001) {
            recvPure = Math.round(issuedPure * (recvGross / issuedGross) * 10000) / 10000;
        }
        var srcLabel = '';
        if (row.cells && row.cells.length > 1) {
            srcLabel = (row.cells[1].textContent || '').trim();
        }
        return {
            metal_exchange_metal_id: row.getAttribute('data-metal-id') || '',
            metal_exchange_product_id: row.getAttribute('data-product-characteristic-id') || '',
            metal_exchange_product_name: row.getAttribute('data-product-name') || '',
            metal_exchange_gross_wt: String(recvGross),
            metal_exchange_purity_wt: String(recvPure),
            metal_exchange_rate: '0',
            metal_exchange_item_code: row.getAttribute('data-barcode') || '',
            metal_exchange_source_stock_id: row.getAttribute('data-issue-stock-id') || '',
            source_issue_stock_id: row.getAttribute('data-issue-stock-id') || '',
            purity_carat: row.getAttribute('data-purity') || '1',
            quantity: 1,
            mr_display_source: srcLabel || 'Material issue',
            mr_display_metal: row.getAttribute('data-metal-name') || '',
            mr_display_product: row.getAttribute('data-product-name') || '',
            mr_display_barcode: row.getAttribute('data-barcode') || ''
        };
    }

    function mrMeBuildPendingLineFromItem(item, recvGross, target) {
        var issuedGross = parseFloat(item.issuedGross || '0') || 0;
        var issuedPure = parseFloat(item.issuedPure || '0') || 0;
        var recvPure = recvGross;
        if (issuedGross > 0.0000001 && issuedPure > 0.0000001) {
            recvPure = Math.round(issuedPure * (recvGross / issuedGross) * 10000) / 10000;
        }
        var targetMetalId = target && target.metalId ? String(target.metalId) : String(item.metalId || '');
        var targetProductId = target && target.productId ? String(target.productId) : String(item.productId || '');
        var targetProductName = target && target.productName ? target.productName : item.productName || '';
        var targetMetalName = target && target.metalName ? target.metalName : item.metalName || '';
        var issuedProduct = item.productName || '';
        var displayProduct = targetProductName;
        if (targetProductName && issuedProduct && targetProductName !== issuedProduct) {
            displayProduct = issuedProduct + ' → ' + targetProductName;
        }
        return {
            metal_exchange_metal_id: targetMetalId,
            metal_exchange_product_id: targetProductId,
            metal_exchange_product_name: targetProductName,
            metal_exchange_metal_name: targetMetalName,
            metal_exchange_gross_wt: String(recvGross),
            metal_exchange_purity_wt: String(recvPure),
            metal_exchange_rate: '0',
            metal_exchange_item_code: item.barcode || '',
            metal_exchange_source_stock_id: item.issueStockId > 0 ? String(item.issueStockId) : '',
            source_issue_stock_id: item.issueStockId > 0 ? String(item.issueStockId) : '',
            purity_carat: item.purity || '1',
            quantity: 1,
            mr_display_source: item.sourceLabel || 'Material issue',
            mr_display_metal: targetMetalName,
            mr_display_product: displayProduct,
            mr_display_barcode: item.barcode || '',
            mr_issued_product_name: issuedProduct
        };
    }

    function mrMeCollectSelectedForModal() {
        var tbody = document.getElementById('mrIssuedMetalExchangeTbody');
        if (!tbody) {
            return [];
        }
        var items = [];
        tbody.querySelectorAll('tr.mr-me-issued-receive-row').forEach(function (tr) {
            var chk = tr.querySelector('.mr-me-issued-receive-chk');
            if (!chk || !chk.checked) {
                return;
            }
            var bal = parseFloat(tr.getAttribute('data-balance-gross') || '0') || 0;
            if (bal <= 0.0000001) {
                return;
            }
            var inp = tr.querySelector('.mr-me-issued-receive-wt');
            var recv = inp ? parseFloat(inp.value) : bal;
            if (!isFinite(recv) || recv <= 0.0000001) {
                recv = bal;
            }
            if (recv > bal + 0.0001) {
                window.alert(
                    'Receive weight cannot exceed balance (' +
                        mrMeFmt(bal, 3) +
                        ') for ' +
                        (tr.getAttribute('data-product-name') || 'metal')
                );
                return;
            }
            var stockId = parseInt(tr.getAttribute('data-issue-stock-id') || '0', 10);
            var barcode = (tr.getAttribute('data-barcode') || '').trim();
            if (stockId < 1 && !barcode) {
                window.alert('Invalid issued line (missing stock / barcode).');
                return;
            }
            var srcLabel = '';
            if (tr.cells && tr.cells.length > 1) {
                srcLabel = (tr.cells[1].textContent || '').trim();
            }
            items.push({
                issueStockId: stockId,
                barcode: barcode,
                recvWt: recv,
                bal: bal,
                metalId: tr.getAttribute('data-metal-id') || '',
                metalName: tr.getAttribute('data-metal-name') || '',
                productId: tr.getAttribute('data-product-characteristic-id') || '',
                productName: tr.getAttribute('data-product-name') || '',
                issuedGross: tr.getAttribute('data-issued-gross') || '0',
                issuedPure: tr.getAttribute('data-issued-pure') || '0',
                purity: tr.getAttribute('data-purity') || '1',
                sourceLabel: srcLabel
            });
        });
        return items;
    }

    function mrMeEnsureModalInBody() {
        var modalEl = document.getElementById('mrMeReceiveTargetModal');
        if (modalEl && modalEl.parentNode !== document.body) {
            document.body.appendChild(modalEl);
        }
        return modalEl;
    }

    function mrMeShowReceiveTargetModal(item, index, total) {
        var modalEl = mrMeEnsureModalInBody();
        if (!modalEl || !item) {
            return false;
        }
        modalEl._mrMeCurrentItem = item;
        var issuedTextEl = document.getElementById('mrMeReceiveTargetIssuedText');
        var balTextEl = document.getElementById('mrMeReceiveTargetBalanceText');
        var metalSel = document.getElementById('mrMeReceiveTargetMetal');
        var prodInput = document.getElementById('mrMeReceiveTargetProductInput');
        var prodId = document.getElementById('mrMeReceiveTargetProductId');
        var wtInp = document.getElementById('mrMeReceiveTargetWt');
        var hintEl = document.getElementById('mrMeReceiveTargetQueueHint');
        var issuedParts = [];
        if (item.metalName) {
            issuedParts.push(item.metalName);
        }
        if (item.productName) {
            issuedParts.push(item.productName);
        }
        if (item.barcode) {
            issuedParts.push(item.barcode);
        }
        if (issuedTextEl) {
            issuedTextEl.textContent = issuedParts.length ? issuedParts.join(' · ') : '—';
        }
        if (balTextEl) {
            balTextEl.textContent = mrMeFmt(item.bal, 3);
        }
        if (metalSel) {
            metalSel.value = item.metalId ? String(item.metalId) : '';
        }
        if (prodInput) {
            prodInput.value = item.productName || '';
        }
        if (prodId) {
            prodId.value = item.productId ? String(item.productId) : '';
        }
        if (wtInp) {
            wtInp.value = mrMeFmt(item.recvWt, 3);
            wtInp.setAttribute('max', String(item.bal));
        }
        if (hintEl) {
            if (total > 1) {
                hintEl.style.display = 'block';
                hintEl.textContent = 'Line ' + String(index + 1) + ' of ' + String(total);
            } else {
                hintEl.style.display = 'none';
                hintEl.textContent = '';
            }
        }
        if (typeof window.jQuery !== 'undefined' && window.jQuery.fn && window.jQuery.fn.modal) {
            var $modal = window.jQuery('#mrMeReceiveTargetModal');
            $modal.off('shown.bs.modal.mrMeReceive').on('shown.bs.modal.mrMeReceive', function () {
                var wtEl = document.getElementById('mrMeReceiveTargetWt');
                if (wtEl && !wtEl.disabled) {
                    wtEl.focus();
                    wtEl.select();
                }
            });
            $modal.modal('show');
        } else {
            modalEl.style.display = 'block';
            modalEl.classList.add('show');
            modalEl.setAttribute('aria-modal', 'true');
            modalEl.removeAttribute('aria-hidden');
            document.body.classList.add('modal-open');
            var wtEl = document.getElementById('mrMeReceiveTargetWt');
            if (wtEl) {
                wtEl.focus();
            }
        }
        return true;
    }

    function mrMeHideReceiveTargetModal() {
        if (typeof window.jQuery !== 'undefined' && window.jQuery.fn && window.jQuery.fn.modal) {
            window.jQuery('#mrMeReceiveTargetModal').modal('hide');
        } else {
            var modalEl = document.getElementById('mrMeReceiveTargetModal');
            if (modalEl) {
                modalEl.style.display = 'none';
                modalEl.classList.remove('show');
            }
        }
    }

    function mrMeFinishReceiveModalQueue() {
        window.__mrMeReceiveModalQueue = [];
        window.__mrMeReceiveModalIndex = 0;
        mrMeHideReceiveTargetModal();
        mrMeRenderTable();
        if (typeof window.updateSummaryPanel === 'function') {
            window.updateSummaryPanel();
        }
    }

    function mrMeConfirmReceiveTarget() {
        var modalEl = document.getElementById('mrMeReceiveTargetModal');
        var item = modalEl && modalEl._mrMeCurrentItem;
        if (!item) {
            return;
        }
        var metalSel = document.getElementById('mrMeReceiveTargetMetal');
        var prodIdEl = document.getElementById('mrMeReceiveTargetProductId');
        var prodInput = document.getElementById('mrMeReceiveTargetProductInput');
        var wtInp = document.getElementById('mrMeReceiveTargetWt');
        var mid = parseInt(metalSel && metalSel.value, 10) || 0;
        var pcid = parseInt(prodIdEl && prodIdEl.value, 10) || 0;
        var recv = parseFloat(wtInp && wtInp.value) || 0;
        if (mid < 1) {
            window.alert('Select metal.');
            return;
        }
        if (pcid < 1) {
            window.alert('Select a product from the list.');
            return;
        }
        if (recv <= 0.0000001) {
            window.alert('Enter receive weight.');
            return;
        }
        if (recv > item.bal + 0.0001) {
            window.alert('Receive weight cannot exceed balance (' + mrMeFmt(item.bal, 3) + ').');
            return;
        }
        var metalName = '';
        if (metalSel && metalSel.selectedIndex >= 0) {
            metalName = (metalSel.options[metalSel.selectedIndex].text || '').trim();
        }
        var productName = (prodInput && prodInput.value) ? String(prodInput.value).split(' (')[0].trim() : '';
        var line = mrMeResolvePendingIds(
            mrMeBuildPendingLineFromItem(item, recv, {
                metalId: mid,
                metalName: metalName,
                productId: pcid,
                productName: productName
            })
        );
        mrMeUpsertPending(line);
        var queue = window.__mrMeReceiveModalQueue || [];
        var nextIndex = (window.__mrMeReceiveModalIndex || 0) + 1;
        window.__mrMeReceiveModalIndex = nextIndex;
        if (nextIndex < queue.length) {
            mrMeShowReceiveTargetModal(queue[nextIndex], nextIndex, queue.length);
            return;
        }
        mrMeFinishReceiveModalQueue();
    }

    function mrMeOpenReceiveModal() {
        var items = mrMeCollectSelectedForModal();
        if (items.length < 1) {
            window.alert('Select at least one issued line with balance and enter receive weight.');
            return 0;
        }
        window.__mrMeReceiveModalQueue = items;
        window.__mrMeReceiveModalIndex = 0;
        if (!mrMeShowReceiveTargetModal(items[0], 0, items.length)) {
            return mrMeQueueSelected();
        }
        return items.length;
    }

    function mrMeInitReceiveTargetModal() {
        var modalEl = mrMeEnsureModalInBody();
        if (!modalEl || modalEl._mrMeModalBound) {
            return;
        }
        modalEl._mrMeModalBound = true;
        var metalSel = document.getElementById('mrMeReceiveTargetMetal');
        var prodInput = document.getElementById('mrMeReceiveTargetProductInput');
        var prodIdEl = document.getElementById('mrMeReceiveTargetProductId');
        var listEl = document.getElementById('mrMeReceiveTargetProductList');
        var confirmBtn = document.getElementById('mrMeReceiveTargetConfirmBtn');
        var searchTimer;
        function showProductList(products) {
            if (!listEl) {
                return;
            }
            listEl.innerHTML = '';
            listEl.style.display = 'block';
            if (!products || !products.length) {
                listEl.innerHTML = '<div class="p-2 text-muted small">No products found</div>';
                return;
            }
            products.forEach(function (p) {
                var div = document.createElement('div');
                div.className = 'mr-me-receive-product-item';
                div.textContent = (p.name || '') + (p.metal_name ? ' (' + p.metal_name + ')' : '');
                div.addEventListener('click', function () {
                    if (prodInput) {
                        prodInput.value = (p.name || '') + (p.metal_name ? ' (' + p.metal_name + ')' : '');
                    }
                    if (prodIdEl) {
                        prodIdEl.value = String(p.characteristic_id || p.id || '');
                    }
                    listEl.style.display = 'none';
                    listEl.innerHTML = '';
                });
                listEl.appendChild(div);
            });
        }
        function searchProducts() {
            if (!listEl || !metalSel || !prodInput) {
                return;
            }
            var mid = parseInt(metalSel.value, 10) || 0;
            var q = (prodInput.value || '').trim();
            if (!mid) {
                listEl.innerHTML = '<div class="p-2 text-muted small">Select metal first</div>';
                listEl.style.display = 'block';
                return;
            }
            listEl.innerHTML = '<div class="p-2 text-muted small">Loading...</div>';
            listEl.style.display = 'block';
            var url =
                'ajax/get-products-by-metal.php?metal_id=' +
                encodeURIComponent(String(mid)) +
                (q ? '&search=' + encodeURIComponent(q) : '');
            fetch(url)
                .then(function (r) {
                    return r.json();
                })
                .then(function (data) {
                    showProductList(data.success && data.products ? data.products : []);
                })
                .catch(function () {
                    listEl.innerHTML = '<div class="p-2 text-danger small">Error loading products</div>';
                });
        }
        if (prodInput) {
            prodInput.addEventListener('input', function () {
                clearTimeout(searchTimer);
                if (prodIdEl) {
                    prodIdEl.value = '';
                }
                searchTimer = setTimeout(searchProducts, 300);
            });
            prodInput.addEventListener('focus', function () {
                if (metalSel && metalSel.value) {
                    searchProducts();
                }
            });
        }
        if (metalSel) {
            metalSel.addEventListener('change', function () {
                if (prodInput) {
                    prodInput.value = '';
                }
                if (prodIdEl) {
                    prodIdEl.value = '';
                }
                if (listEl) {
                    listEl.style.display = 'none';
                    listEl.innerHTML = '';
                }
            });
        }
        document.addEventListener('click', function (e) {
            if (!listEl || listEl.style.display !== 'block') {
                return;
            }
            if (!listEl.contains(e.target) && e.target !== prodInput) {
                listEl.style.display = 'none';
            }
        });
        if (confirmBtn) {
            confirmBtn.addEventListener('click', mrMeConfirmReceiveTarget);
        }
    }

    function mrMeApplyPartialBalance(tr, recvGross) {
        var bal = parseFloat(tr.getAttribute('data-balance-gross') || '0') || 0;
        var newBal = Math.max(0, Math.round((bal - recvGross) * 1000) / 1000);
        tr.setAttribute('data-balance-gross', String(newBal));
        var cells = tr.cells;
        if (cells && cells.length >= 8) {
            cells[6].textContent = mrMeFmt(newBal, 3);
        }
        var inp = tr.querySelector('.mr-me-issued-receive-wt');
        if (inp) {
            inp.value = newBal > 0.0000001 ? mrMeFmt(newBal, 3) : '';
            inp.disabled = newBal <= 0.0000001;
        }
        var chk = tr.querySelector('.mr-me-issued-receive-chk');
        if (chk) {
            chk.checked = false;
            chk.disabled = newBal <= 0.0000001;
        }
        if (newBal <= 0.0000001) {
            tr.classList.add('text-muted');
        }
        var stCell = tr.cells[tr.cells.length - 1];
        if (stCell) {
            stCell.innerHTML = mrMeStatusBadge(newBal <= 0.0000001 ? 'fully_received' : 'partial', false);
        }
    }

    function mrMeQueueSelected() {
        var tbody = document.getElementById('mrIssuedMetalExchangeTbody');
        if (!tbody) {
            return 0;
        }
        var added = 0;
        tbody.querySelectorAll('tr.mr-me-issued-receive-row').forEach(function (tr) {
            var chk = tr.querySelector('.mr-me-issued-receive-chk');
            if (!chk || !chk.checked) {
                return;
            }
            var bal = parseFloat(tr.getAttribute('data-balance-gross') || '0') || 0;
            if (bal <= 0.0000001) {
                return;
            }
            var inp = tr.querySelector('.mr-me-issued-receive-wt');
            var recv = inp ? parseFloat(inp.value) : bal;
            if (!isFinite(recv) || recv <= 0.0000001) {
                recv = bal;
            }
            if (recv > bal + 0.0001) {
                window.alert(
                    'Receive weight cannot exceed balance (' + mrMeFmt(bal, 3) + ') for ' + (tr.getAttribute('data-product-name') || 'metal')
                );
                recv = bal;
            }
            var stockId = parseInt(tr.getAttribute('data-issue-stock-id') || '0', 10);
            var barcode = (tr.getAttribute('data-barcode') || '').trim();
            if (stockId < 1 && !barcode) {
                window.alert('Invalid issued line (missing stock / barcode).');
                return;
            }
            var line = mrMeResolvePendingIds(mrMeBuildPendingLine(tr, recv));
            mrMeUpsertPending(line);
            added++;
        });
        if (added < 1) {
            window.alert('Select at least one issued line with balance and enter receive weight.');
            return 0;
        }
        mrMeRenderTable();
        if (typeof window.updateSummaryPanel === 'function') {
            window.updateSummaryPanel();
        }
        return added;
    }

    function mrMeRefreshFromServer(cb) {
        var el = document.getElementById('jwoSaleOrderId');
        var soid = el ? parseInt(el.getAttribute('data-sale-order-id') || '0', 10) : 0;
        if (soid < 1 && window.jwoSaleOrderIdParam) {
            soid = parseInt(String(window.jwoSaleOrderIdParam), 10) || 0;
        }
        if (soid < 1) {
            if (typeof cb === 'function') {
                cb();
            }
            return;
        }
        var qs =
            'sale_order_id=' +
            encodeURIComponent(String(soid)) +
            (window.jwoFromRepair ? '&from_repair=1' : '') +
            '&_=' +
            (Date.now ? Date.now() : 0);
        fetch('ajax/list-material-receive-issued-reference.php?' + qs)
            .then(function (r) {
                if (!r.ok) {
                    return r.text().then(function (t) {
                        throw new Error(t || 'HTTP ' + r.status);
                    });
                }
                return r.json();
            })
            .then(function (data) {
                mrApplyMetalExchangeFromServer(data);
                mrMeRenderTable();
                if (typeof cb === 'function') {
                    cb();
                }
            })
            .catch(function (err) {
                console.warn('Material Receive ME list failed', err);
                if (typeof cb === 'function') {
                    cb();
                }
            });
    }

    function mrMeRefreshPaymentCardRows() {
        /* receive table is server-driven only; no payment-card merge */
    }

    function mrMeInit() {
        var cfg = window.AURAGOLD_VOUCHER_DS || {};
        if ((cfg.voucherKind || '') !== 'material_receive') {
            return;
        }
        mrMeRenderTable();
        if (typeof window.mrUpdateIssuedReceiveTabCounts === 'function') {
            window.mrUpdateIssuedReceiveTabCounts();
        }
        mrMeInitReceiveTargetModal();
        if (document.getElementById('mrReceiveMeQueueBtn') && document.getElementById('mrReceiveMeQueueBtn')._mrMeBound) {
            return;
        }
        var qBtn = document.getElementById('mrReceiveMeQueueBtn');
        if (qBtn) {
            qBtn._mrMeBound = true;
            qBtn.addEventListener('click', function () {
                mrMeOpenReceiveModal();
            });
        }
        var selBtn = document.getElementById('mrReceiveMeSelectAll');
        var hdr = document.getElementById('mrReceiveMeHdrChk');
        function toggleAll(on) {
            var tbody = document.getElementById('mrIssuedMetalExchangeTbody');
            if (!tbody) {
                return;
            }
            tbody.querySelectorAll('.mr-me-issued-receive-chk:not(:disabled)').forEach(function (c) {
                c.checked = !!on;
            });
        }
        if (selBtn) {
            selBtn.addEventListener('click', function () {
                toggleAll(true);
            });
        }
        if (hdr) {
            hdr.addEventListener('change', function () {
                toggleAll(hdr.checked);
            });
        }
    }

    document.addEventListener('DOMContentLoaded', mrMeInit);
    window.addEventListener('load', function () {
        mrMeInit();
    });
    window.mrRenderMaterialReceiveIssuedMetalExchange = mrMeRenderTable;
    window.mrRefreshMaterialReceiveIssuedMetalExchange = mrMeRefreshFromServer;
})();
