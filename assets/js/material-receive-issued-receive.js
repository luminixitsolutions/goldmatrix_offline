/**
 * Material Receive: partial receive of diamonds/stones issued on Material Issue.
 */
(function () {
    'use strict';

    function mrFmtNum(n, dec) {
        var x = parseFloat(n);
        if (!isFinite(x)) {
            return '';
        }
        return x.toFixed(dec != null ? dec : 3);
    }

    function mrIsMaterialReceive() {
        var cfg = window.AURAGOLD_VOUCHER_DS || {};
        return (cfg.voucherKind || '') === 'material_receive';
    }

    function mrSaleOrderId() {
        var el = document.getElementById('jwoSaleOrderId');
        var id = el ? parseInt(el.getAttribute('data-sale-order-id') || '0', 10) : 0;
        if (id < 1 && window.jwoSaleOrderIdParam) {
            id = parseInt(String(window.jwoSaleOrderIdParam), 10) || 0;
        }
        return id;
    }

    function mrRefreshIssuedFromServer(cb) {
        var soid = mrSaleOrderId();
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
                if (data && data.ok) {
                    window.MATERIAL_RECEIVE_ISSUED_DIAMONDS = Array.isArray(data.diamonds) ? data.diamonds : [];
                    window.MATERIAL_RECEIVE_ISSUED_STONES = Array.isArray(data.stones) ? data.stones : [];
                    if (typeof window.mrApplyMetalExchangeFromServer === 'function') {
                        window.mrApplyMetalExchangeFromServer(data);
                    } else {
                        var incomingMe = Array.isArray(data.metal_exchange) ? data.metal_exchange : [];
                        if (incomingMe.length > 0) {
                            window.MATERIAL_RECEIVE_ISSUED_METAL_EXCHANGE = incomingMe;
                        }
                    }
                    window.__materialIssueReferenceDiamondRows = window.MATERIAL_RECEIVE_ISSUED_DIAMONDS.slice();
                    window.__materialIssueReferenceStoneRows = window.MATERIAL_RECEIVE_ISSUED_STONES.slice();
                }
                if (typeof window.mrRenderMaterialReceiveIssuedMetalExchange === 'function') {
                    window.mrRenderMaterialReceiveIssuedMetalExchange();
                }
                if (typeof window.auragoldMaterialReceiveApplyIssuedFromSaleOrder === 'function') {
                    window.auragoldMaterialReceiveApplyIssuedFromSaleOrder();
                }
                if (typeof window.mrUpdateIssuedReceiveTabCounts === 'function') {
                    window.mrUpdateIssuedReceiveTabCounts();
                }
                if (typeof window.mrAutoSelectIssuedReceiveTab === 'function') {
                    window.mrAutoSelectIssuedReceiveTab();
                }
                if (typeof cb === 'function') {
                    cb();
                }
            })
            .catch(function () {
                if (typeof cb === 'function') {
                    cb();
                }
            });
    }

    function mrPendingDsKey(line) {
        return (
            String(parseInt(line.source_issue_id, 10) || 0) +
            '|' +
            String(parseInt(line.product_characteristic_id, 10) || parseInt(line.stock_id, 10) || 0)
        );
    }

    function mrUpsertPending(pendingKey, line) {
        if (!Array.isArray(window[pendingKey])) {
            window[pendingKey] = [];
        }
        var key = mrPendingDsKey(line);
        var list = window[pendingKey];
        var found = -1;
        for (var i = 0; i < list.length; i++) {
            if (mrPendingDsKey(list[i]) === key) {
                found = i;
                break;
            }
        }
        if (found >= 0) {
            list[found] = line;
        } else {
            list.push(line);
        }
    }

    function mrResolveDiamondMetalId() {
        var metals = Array.isArray(window.metals) ? window.metals : [];
        for (var i = 0; i < metals.length; i++) {
            var m = metals[i] || {};
            var dn = String(m.display_name || m.system_name || '').toLowerCase();
            if (dn.indexOf('diamond') >= 0) {
                return parseInt(m.id, 10) || 0;
            }
        }
        return 0;
    }

    function mrDsProductCategoryFilter(gem) {
        return gem === 'stone' ? 'GemStones' : 'Diamonds';
    }

    function mrDsGemLabel(gem) {
        return gem === 'stone' ? 'Gemstone' : 'Diamond';
    }

    function mrCollectSelectedDsForModal(gem) {
        var isDiamond = gem === 'diamond';
        var tbodyId = isDiamond ? 'saleOrderDiamondLinesTbody' : 'saleOrderStoneLinesTbody';
        var tbody = document.getElementById(tbodyId);
        if (!tbody) {
            return [];
        }
        var items = [];
        tbody.querySelectorAll('tr.mr-issued-receive-row').forEach(function (tr) {
            var chk = tr.querySelector('.mr-issued-receive-chk');
            if (!chk || !chk.checked) {
                return;
            }
            var bal = parseFloat(tr.getAttribute('data-balance-weight') || '0') || 0;
            if (bal <= 0.0000001) {
                return;
            }
            var inp = tr.querySelector('.mr-issued-receive-wt');
            var recvWt = inp ? parseFloat(inp.value) : bal;
            if (!isFinite(recvWt) || recvWt <= 0.0000001) {
                recvWt = bal;
            }
            if (recvWt > bal + 0.0001) {
                window.alert(
                    'Receive weight cannot exceed balance (' +
                        mrFmtNum(bal, 3) +
                        ') for ' +
                        (tr.getAttribute('data-barcode') || '')
                );
                return;
            }
            items.push({
                gem: gem,
                issueLineId: parseInt(tr.getAttribute('data-issue-line-id') || '0', 10),
                sourceStockId: parseInt(tr.getAttribute('data-stock-id') || '0', 10),
                barcode: tr.getAttribute('data-barcode') || '',
                productName: tr.getAttribute('data-product-name') || '',
                category: tr.getAttribute('data-category') || '',
                issuedQty: parseFloat(tr.getAttribute('data-issued-qty') || '0') || 0,
                issuedWt: parseFloat(tr.getAttribute('data-issued-weight') || '0') || 0,
                recvWt: recvWt,
                bal: bal
            });
        });
        return items;
    }

    function mrBuildPendingLineFromDsItem(item, target) {
        var recvWt = parseFloat(item.recvWt) || 0;
        var recvQty = 0;
        if (item.issuedQty > 0.0000001 && item.issuedWt > 0.0000001) {
            recvQty = Math.round(item.issuedQty * (recvWt / item.issuedWt) * 10000) / 10000;
        }
        var targetName = target && target.productName ? target.productName : item.productName || '';
        var issuedName = item.productName || '';
        var displayName = targetName;
        if (targetName && issuedName && targetName !== issuedName) {
            displayName = issuedName + ' → ' + targetName;
        }
        var line = {
            source_issue_id: item.issueLineId,
            source_stock_id: item.sourceStockId,
            stock_id: target && target.stockId ? parseInt(target.stockId, 10) || 0 : 0,
            product_characteristic_id: target && target.productId ? parseInt(target.productId, 10) || 0 : 0,
            barcode: item.barcode || '',
            product_name: targetName,
            mr_issued_product_name: issuedName,
            mr_display_product: displayName,
            allocate_weight: recvWt,
            allocate_qty: recvQty
        };
        if (item.gem === 'stone') {
            line.stone_category = item.category || '';
        } else {
            line.diamond_category = item.category || '';
        }
        return line;
    }

    function mrEnsureDsModalInBody() {
        var modalEl = document.getElementById('mrDsReceiveTargetModal');
        if (modalEl && modalEl.parentNode !== document.body) {
            document.body.appendChild(modalEl);
        }
        return modalEl;
    }

    function mrShowDsReceiveTargetModal(item, index, total) {
        var modalEl = mrEnsureDsModalInBody();
        if (!modalEl || !item) {
            return false;
        }
        modalEl._mrDsCurrentItem = item;
        modalEl._mrDsGem = item.gem || 'diamond';
        var gem = modalEl._mrDsGem;
        var gemEl = document.getElementById('mrDsReceiveTargetGem');
        var titleEl = document.getElementById('mrDsReceiveTargetModalTitle');
        var labelEl = document.getElementById('mrDsReceiveTargetProductLabel');
        var issuedTextEl = document.getElementById('mrDsReceiveTargetIssuedText');
        var balTextEl = document.getElementById('mrDsReceiveTargetBalanceText');
        var prodInput = document.getElementById('mrDsReceiveTargetProductInput');
        var prodId = document.getElementById('mrDsReceiveTargetProductId');
        var wtInp = document.getElementById('mrDsReceiveTargetWt');
        var hintEl = document.getElementById('mrDsReceiveTargetQueueHint');
        var confirmBtn = document.getElementById('mrDsReceiveTargetConfirmBtn');
        if (gemEl) {
            gemEl.value = gem;
        }
        if (titleEl) {
            titleEl.textContent = 'Receive ' + mrDsGemLabel(gem).toLowerCase() + ' into stock';
        }
        if (labelEl) {
            labelEl.innerHTML =
                mrDsGemLabel(gem) + ' product <span class="text-danger">*</span> <span class="text-muted font-weight-normal">(' +
                mrDsProductCategoryFilter(gem) +
                ' only)</span>';
        }
        var issuedParts = [];
        if (item.productName) {
            issuedParts.push(item.productName);
        }
        if (item.barcode) {
            issuedParts.push(item.barcode);
        }
        if (item.category) {
            issuedParts.push(item.category);
        }
        if (issuedTextEl) {
            issuedTextEl.textContent = issuedParts.length ? issuedParts.join(' · ') : '—';
        }
        if (balTextEl) {
            balTextEl.textContent = mrFmtNum(item.bal, 3);
        }
        if (prodInput) {
            prodInput.value = item.productName || '';
            prodInput.placeholder = 'Search ' + mrDsGemLabel(gem).toLowerCase() + ' product...';
        }
        if (prodId) {
            prodId.value = '';
        }
        if (wtInp) {
            wtInp.value = mrFmtNum(item.recvWt, 3);
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
        if (confirmBtn) {
            confirmBtn.className = gem === 'stone' ? 'btn btn-sm btn-success' : 'btn btn-sm btn-primary';
        }
        if (typeof window.jQuery !== 'undefined' && window.jQuery.fn && window.jQuery.fn.modal) {
            var $modal = window.jQuery('#mrDsReceiveTargetModal');
            $modal.off('shown.bs.modal.mrDsReceive').on('shown.bs.modal.mrDsReceive', function () {
                var wtEl = document.getElementById('mrDsReceiveTargetWt');
                if (wtEl && !wtEl.disabled) {
                    wtEl.focus();
                    wtEl.select();
                }
            });
            $modal.modal('show');
        } else {
            modalEl.style.display = 'block';
            modalEl.classList.add('show');
        }
        return true;
    }

    function mrHideDsReceiveTargetModal() {
        if (typeof window.jQuery !== 'undefined' && window.jQuery.fn && window.jQuery.fn.modal) {
            window.jQuery('#mrDsReceiveTargetModal').modal('hide');
        } else {
            var modalEl = document.getElementById('mrDsReceiveTargetModal');
            if (modalEl) {
                modalEl.style.display = 'none';
                modalEl.classList.remove('show');
            }
        }
    }

    function mrFinishDsReceiveModalQueue(gem) {
        window.__mrDsReceiveModalQueue = [];
        window.__mrDsReceiveModalIndex = 0;
        mrHideDsReceiveTargetModal();
        if (gem === 'diamond' && typeof window.renderSaleOrderDiamondLinesPanel === 'function') {
            window.renderSaleOrderDiamondLinesPanel();
        }
        if (gem === 'stone' && typeof window.renderSaleOrderStoneLinesPanel === 'function') {
            window.renderSaleOrderStoneLinesPanel();
        }
        if (typeof window.mrUpdateIssuedReceiveTabCounts === 'function') {
            window.mrUpdateIssuedReceiveTabCounts();
        }
    }

    function mrConfirmDsReceiveTarget() {
        var modalEl = document.getElementById('mrDsReceiveTargetModal');
        var item = modalEl && modalEl._mrDsCurrentItem;
        if (!item) {
            return;
        }
        var gem = item.gem || modalEl._mrDsGem || 'diamond';
        var prodIdEl = document.getElementById('mrDsReceiveTargetProductId');
        var prodInput = document.getElementById('mrDsReceiveTargetProductInput');
        var wtInp = document.getElementById('mrDsReceiveTargetWt');
        var pcid = parseInt(prodIdEl && prodIdEl.value, 10) || 0;
        var recvWt = parseFloat(wtInp && wtInp.value) || 0;
        if (pcid < 1) {
            window.alert('Select a ' + mrDsGemLabel(gem).toLowerCase() + ' product from the list.');
            return;
        }
        if (recvWt <= 0.0000001) {
            window.alert('Enter receive weight.');
            return;
        }
        if (recvWt > item.bal + 0.0001) {
            window.alert('Receive weight cannot exceed balance (' + mrFmtNum(item.bal, 3) + ').');
            return;
        }
        item.recvWt = recvWt;
        var productName = (prodInput && prodInput.value) ? String(prodInput.value).split(' (')[0].trim() : '';
        var pendingKey = gem === 'diamond' ? '__pendingSaleOrderDiamondLines' : '__pendingSaleOrderStoneLines';
        mrUpsertPending(
            pendingKey,
            mrBuildPendingLineFromDsItem(item, {
                productId: pcid,
                stockId: 0,
                productName: productName
            })
        );
        var queue = window.__mrDsReceiveModalQueue || [];
        var nextIndex = (window.__mrDsReceiveModalIndex || 0) + 1;
        window.__mrDsReceiveModalIndex = nextIndex;
        if (nextIndex < queue.length) {
            mrShowDsReceiveTargetModal(queue[nextIndex], nextIndex, queue.length);
            return;
        }
        mrFinishDsReceiveModalQueue(gem);
    }

    function mrOpenDsReceiveModal(gem) {
        var items = mrCollectSelectedDsForModal(gem);
        if (items.length < 1) {
            window.alert('Select at least one issued line with balance and enter receive weight.');
            return 0;
        }
        window.__mrDsReceiveModalQueue = items;
        window.__mrDsReceiveModalIndex = 0;
        if (!mrShowDsReceiveTargetModal(items[0], 0, items.length)) {
            return mrQueueSelectedIssued(gem);
        }
        return items.length;
    }

    function mrInitDsReceiveTargetModal() {
        var modalEl = mrEnsureDsModalInBody();
        if (!modalEl || modalEl._mrDsModalBound) {
            return;
        }
        modalEl._mrDsModalBound = true;
        var prodInput = document.getElementById('mrDsReceiveTargetProductInput');
        var prodIdEl = document.getElementById('mrDsReceiveTargetProductId');
        var listEl = document.getElementById('mrDsReceiveTargetProductList');
        var confirmBtn = document.getElementById('mrDsReceiveTargetConfirmBtn');
        var searchTimer;
        function currentGem() {
            var modal = document.getElementById('mrDsReceiveTargetModal');
            return (modal && modal._mrDsGem) || (document.getElementById('mrDsReceiveTargetGem') || {}).value || 'diamond';
        }
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
            if (!listEl || !prodInput) {
                return;
            }
            var mid = mrResolveDiamondMetalId();
            var gem = currentGem();
            var cat = mrDsProductCategoryFilter(gem);
            var q = (prodInput.value || '').trim();
            if (!mid) {
                listEl.innerHTML = '<div class="p-2 text-muted small">Diamond &amp; Stones metal not configured.</div>';
                listEl.style.display = 'block';
                return;
            }
            listEl.innerHTML = '<div class="p-2 text-muted small">Loading...</div>';
            listEl.style.display = 'block';
            var url =
                'ajax/get-products-by-metal.php?metal_id=' +
                encodeURIComponent(String(mid)) +
                '&diamond_category=' +
                encodeURIComponent(cat) +
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
                searchProducts();
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
            confirmBtn.addEventListener('click', mrConfirmDsReceiveTarget);
        }
    }

    function mrQueueSelectedIssued(gem) {
        var isDiamond = gem === 'diamond';
        var tbodyId = isDiamond ? 'saleOrderDiamondLinesTbody' : 'saleOrderStoneLinesTbody';
        var tbody = document.getElementById(tbodyId);
        if (!tbody) {
            return 0;
        }
        var pendingKey = isDiamond ? '__pendingSaleOrderDiamondLines' : '__pendingSaleOrderStoneLines';
        var added = 0;
        tbody.querySelectorAll('tr.mr-issued-receive-row').forEach(function (tr) {
            var chk = tr.querySelector('.mr-issued-receive-chk');
            if (!chk || !chk.checked) {
                return;
            }
            var bal = parseFloat(tr.getAttribute('data-balance-weight') || '0') || 0;
            if (bal <= 0.0000001) {
                return;
            }
            var inp = tr.querySelector('.mr-issued-receive-wt');
            var recvWt = inp ? parseFloat(inp.value) : bal;
            if (!isFinite(recvWt) || recvWt <= 0.0000001) {
                recvWt = bal;
            }
            if (recvWt > bal + 0.0001) {
                window.alert(
                    'Receive weight cannot exceed balance (' + mrFmtNum(bal, 3) + ') for ' + (tr.getAttribute('data-barcode') || '')
                );
                recvWt = bal;
            }
            var issuedQty = parseFloat(tr.getAttribute('data-issued-qty') || '0') || 0;
            var issuedWt = parseFloat(tr.getAttribute('data-issued-weight') || '0') || 0;
            var recvQty = 0;
            if (issuedQty > 0.0000001 && issuedWt > 0.0000001) {
                recvQty = Math.round(issuedQty * (recvWt / issuedWt) * 10000) / 10000;
            }
            mrUpsertPending(pendingKey, {
                source_issue_id: parseInt(tr.getAttribute('data-issue-line-id') || '0', 10),
                source_stock_id: parseInt(tr.getAttribute('data-stock-id') || '0', 10),
                stock_id: parseInt(tr.getAttribute('data-stock-id') || '0', 10),
                barcode: tr.getAttribute('data-barcode') || '',
                product_name: tr.getAttribute('data-product-name') || '',
                diamond_category: isDiamond ? tr.getAttribute('data-category') || '' : '',
                stone_category: !isDiamond ? tr.getAttribute('data-category') || '' : '',
                allocate_weight: recvWt,
                allocate_qty: recvQty,
            });
            added++;
        });
        if (added < 1) {
            window.alert('Select at least one issued line with balance and enter receive weight.');
            return 0;
        }
        if (isDiamond && typeof window.renderSaleOrderDiamondLinesPanel === 'function') {
            window.renderSaleOrderDiamondLinesPanel();
        }
        if (!isDiamond && typeof window.renderSaleOrderStoneLinesPanel === 'function') {
            window.renderSaleOrderStoneLinesPanel();
        }
        return added;
    }

    function mrIssuedStatusBadge(st, queued) {
        var label = 'To receive';
        var cls = 'mr-issued-badge mr-issued-badge--to-receive';
        if (st === 'fully_received') {
            label = 'Received';
            cls = 'mr-issued-badge mr-issued-badge--received';
        } else if (st === 'partial') {
            label = 'Partial receive';
            cls = 'mr-issued-badge mr-issued-badge--partial';
        } else if (queued) {
            label = 'Queued';
            cls = 'mr-issued-badge mr-issued-badge--queued';
        }
        return '<span class="' + cls + '">' + label + '</span>';
    }

    window.mrBuildIssuedEmptyHtml = function (kind) {
        var text =
            kind === 'stone'
                ? 'No gemstones issued on Material Issue for this order.'
                : kind === 'metal'
                  ? 'No metal exchange lines yet.'
                  : 'No diamonds issued on Material Issue for this order.';
        return (
            '<div class="mr-issued-empty mr-issued-empty--compact">' +
            '<p class="mr-issued-empty-title mb-0">' +
            text +
            ' Issue on Material Issue first, then receive here.</p></div>'
        );
    };

    window.mrAppendIssuedEmptyRow = function (tbody, kind, colSpan) {
        if (!tbody) {
            return;
        }
        var tr = document.createElement('tr');
        tr.className = 'mr-issued-empty-row';
        var td = document.createElement('td');
        td.colSpan = colSpan || 9;
        td.innerHTML =
            typeof window.mrBuildIssuedEmptyHtml === 'function'
                ? window.mrBuildIssuedEmptyHtml(kind)
                : 'No issued lines yet.';
        tr.appendChild(td);
        tbody.appendChild(tr);
    };

    function mrBindIssuedRow(tr, r, gem) {
        var bal = parseFloat(r.balance_weight != null ? r.balance_weight : 0) || 0;
        var issuedWt = parseFloat(r.issued_weight != null ? r.issued_weight : r.weight) || 0;
        var recvWt = parseFloat(r.received_weight != null ? r.received_weight : 0) || 0;
        var issueId = parseInt(r.issue_line_id != null ? r.issue_line_id : r.issue_id || r.id, 10) || 0;
        tr.className = 'mr-issued-receive-row';
        tr.setAttribute('data-issue-line-id', String(issueId));
        tr.setAttribute('data-stock-id', String(parseInt(r.stock_id, 10) || 0));
        tr.setAttribute('data-barcode', r.barcode || '');
        tr.setAttribute('data-product-name', r.product_name || '');
        tr.setAttribute(
            'data-category',
            gem === 'diamond' ? r.diamond_category || '' : r.stone_category || ''
        );
        tr.setAttribute('data-issued-weight', String(issuedWt));
        tr.setAttribute('data-issued-qty', String(parseFloat(r.issued_qty != null ? r.issued_qty : r.qty) || 0));
        tr.setAttribute('data-balance-weight', String(bal));
        if (bal <= 0.0000001) {
            tr.style.opacity = '0.65';
        }

        var tdChk = document.createElement('td');
        tdChk.className = 'text-center';
        var chk = document.createElement('input');
        chk.type = 'checkbox';
        chk.className = 'mr-issued-receive-chk';
        chk.disabled = bal <= 0.0000001;
        tdChk.appendChild(chk);
        tr.appendChild(tdChk);

        function tdText(txt, cls) {
            var td = document.createElement('td');
            if (cls) {
                td.className = cls;
            }
            td.textContent = txt;
            tr.appendChild(td);
            return td;
        }

        tdText(r.barcode || '');
        tdText(r.product_name || '');
        tdText(gem === 'diamond' ? r.diamond_category || '' : r.stone_category || '');
        tdText(mrFmtNum(issuedWt, 3), 'text-right');
        tdText(mrFmtNum(recvWt, 3), 'text-right');
        tdText(mrFmtNum(bal, 3), 'text-right');

        var tdInp = document.createElement('td');
        tdInp.className = 'text-right';
        var inp = document.createElement('input');
        inp.type = 'number';
        inp.step = '0.001';
        inp.min = '0';
        inp.max = String(bal > 0 ? bal : 0);
        inp.className = 'form-control form-control-sm text-right mr-issued-receive-wt';
        inp.style.maxWidth = '88px';
        inp.style.display = 'inline-block';
        inp.value = bal > 0.0000001 ? String(bal) : '0';
        inp.disabled = bal <= 0.0000001;
        tdInp.appendChild(inp);
        tr.appendChild(tdInp);

        var tdSt = document.createElement('td');
        var st = r.reference_status || 'to_receive';
        tdSt.innerHTML = mrIssuedStatusBadge(st, tr.getAttribute('data-queued-receive') === '1');
        tr.appendChild(tdSt);
    }

    window.mrRenderIssuedDiamondRows = function (tbody, issuedRef) {
        if (!tbody || !mrIsMaterialReceive()) {
            return false;
        }
        issuedRef.forEach(function (r) {
            var tr = document.createElement('tr');
            mrBindIssuedRow(tr, r, 'diamond');
            tbody.appendChild(tr);
        });
        return issuedRef.length > 0;
    };

    window.mrRenderIssuedStoneRows = function (tbody, issuedRef) {
        if (!tbody || !mrIsMaterialReceive()) {
            return false;
        }
        issuedRef.forEach(function (r) {
            var tr = document.createElement('tr');
            mrBindIssuedRow(tr, r, 'stone');
            tbody.appendChild(tr);
        });
        return issuedRef.length > 0;
    };

    function mrBindToolbar() {
        var dHdr = document.getElementById('mrReceiveDiamondHdrChk');
        var sHdr = document.getElementById('mrReceiveStoneHdrChk');
        var dAll = document.getElementById('mrReceiveDiamondSelectAll');
        var sAll = document.getElementById('mrReceiveStoneSelectAll');
        var dBtn = document.getElementById('mrReceiveDiamondQueueBtn');
        var sBtn = document.getElementById('mrReceiveStoneQueueBtn');

        function toggleAll(gem, on) {
            var tbody = document.getElementById(
                gem === 'diamond' ? 'saleOrderDiamondLinesTbody' : 'saleOrderStoneLinesTbody'
            );
            if (!tbody) {
                return;
            }
            tbody.querySelectorAll('.mr-issued-receive-chk:not(:disabled)').forEach(function (c) {
                c.checked = !!on;
            });
        }

        if (dHdr && !dHdr._mrBound) {
            dHdr._mrBound = true;
            dHdr.addEventListener('change', function () {
                toggleAll('diamond', dHdr.checked);
            });
        }
        if (sHdr && !sHdr._mrBound) {
            sHdr._mrBound = true;
            sHdr.addEventListener('change', function () {
                toggleAll('stone', sHdr.checked);
            });
        }
        if (dAll && !dAll._mrBound) {
            dAll._mrBound = true;
            dAll.addEventListener('click', function () {
                toggleAll('diamond', true);
            });
        }
        if (sAll && !sAll._mrBound) {
            sAll._mrBound = true;
            sAll.addEventListener('click', function () {
                toggleAll('stone', true);
            });
        }
        if (dBtn && !dBtn._mrBound) {
            dBtn._mrBound = true;
            dBtn.addEventListener('click', function () {
                mrOpenDsReceiveModal('diamond');
            });
        }
        if (sBtn && !sBtn._mrBound) {
            sBtn._mrBound = true;
            sBtn.addEventListener('click', function () {
                mrOpenDsReceiveModal('stone');
            });
        }
    }

    var _origOnSave = window.auragoldVoucherDiamondStoneOnSaveSuccess;
    window.auragoldVoucherDiamondStoneOnSaveSuccess = function (savedId) {
        if (typeof _origOnSave === 'function') {
            _origOnSave(savedId);
        }
        if (!mrIsMaterialReceive()) {
            return;
        }
        mrRefreshIssuedFromServer(function () {
            if (typeof window.auragoldMaterialReceiveApplyIssuedFromSaleOrder === 'function') {
                window.auragoldMaterialReceiveApplyIssuedFromSaleOrder();
            }
        });
    };

    var _mrIssuedInitDone = false;
    function mrInitIssuedReferenceOnLoad() {
        if (_mrIssuedInitDone) {
            return;
        }
        _mrIssuedInitDone = true;
        mrInitDsReceiveTargetModal();
        mrBindToolbar();
        mrInitIssuedReceiveTabs();
        if (mrIsMaterialReceive() && mrSaleOrderId() > 0) {
            mrRefreshIssuedFromServer();
        }
    }

    function mrIssuedTabPaneId(tab) {
        if (tab === 'Stone') {
            return 'saleOrderStoneLinesCard';
        }
        if (tab === 'Metal') {
            return 'mrIssuedTabMetal';
        }
        return 'saleOrderDiamondLinesCard';
    }

    function mrIssuedTabLineCount(tab) {
        if (tab === 'Diamond') {
            var dRef = Array.isArray(window.__materialIssueReferenceDiamondRows)
                ? window.__materialIssueReferenceDiamondRows.length
                : 0;
            var dPend = Array.isArray(window.__pendingSaleOrderDiamondLines)
                ? window.__pendingSaleOrderDiamondLines.length
                : 0;
            var dSaved = Array.isArray(window.__saleOrderDiamondIssueRows)
                ? window.__saleOrderDiamondIssueRows.length
                : 0;
            return dRef + dPend + dSaved;
        }
        if (tab === 'Stone') {
            var sRef = Array.isArray(window.__materialIssueReferenceStoneRows)
                ? window.__materialIssueReferenceStoneRows.length
                : 0;
            var sPend = Array.isArray(window.__pendingSaleOrderStoneLines)
                ? window.__pendingSaleOrderStoneLines.length
                : 0;
            var sSaved = Array.isArray(window.__saleOrderStoneIssueRows)
                ? window.__saleOrderStoneIssueRows.length
                : 0;
            return sRef + sPend + sSaved;
        }
        var meRows = Array.isArray(window.MATERIAL_RECEIVE_ISSUED_METAL_EXCHANGE)
            ? window.MATERIAL_RECEIVE_ISSUED_METAL_EXCHANGE.length
            : 0;
        var mePend = Array.isArray(window.__pendingMaterialReceiveMetalExchange)
            ? window.__pendingMaterialReceiveMetalExchange.length
            : 0;
        return meRows + mePend;
    }

    function mrSwitchIssuedTab(tab, focusOnly) {
        var paneId = mrIssuedTabPaneId(tab);
        document.querySelectorAll('.mr-issued-tab-pane').forEach(function (pane) {
            pane.classList.toggle('active', pane.id === paneId);
        });
        document.querySelectorAll('.mr-issued-tab-btn').forEach(function (btn) {
            var isActive = btn.getAttribute('data-mr-tab') === tab;
            btn.classList.toggle('active', isActive);
            btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
        if (tab === 'Metal' && typeof window.mrRenderMaterialReceiveIssuedMetalExchange === 'function') {
            window.mrRenderMaterialReceiveIssuedMetalExchange();
        }
        if (!focusOnly && typeof window.mrOnIssuedTabSwitched === 'function') {
            window.mrOnIssuedTabSwitched(tab);
        }
    }

    function mrUpdateIssuedTabCounts() {
        ['Diamond', 'Stone', 'Metal'].forEach(function (tab) {
            var el = document.getElementById('mrIssuedTabCount' + tab);
            if (!el) {
                return;
            }
            var n = mrIssuedTabLineCount(tab);
            el.textContent = String(n);
            el.classList.toggle('mr-issued-tab-count--has', n > 0);
        });
    }

    function mrAutoSelectIssuedTab() {
        if (!mrIsMaterialReceive()) {
            return;
        }
        var order = ['Metal', 'Diamond', 'Stone'];
        for (var i = 0; i < order.length; i++) {
            if (mrIssuedTabLineCount(order[i]) > 0) {
                mrSwitchIssuedTab(order[i], true);
                return;
            }
        }
        mrSwitchIssuedTab('Diamond', true);
    }

    function mrInitIssuedReceiveTabs() {
        if (!mrIsMaterialReceive()) {
            return;
        }
        document.querySelectorAll('.mr-issued-tab-btn').forEach(function (btn) {
            if (btn._mrTabBound) {
                return;
            }
            btn._mrTabBound = true;
            btn.addEventListener('click', function () {
                mrSwitchIssuedTab(btn.getAttribute('data-mr-tab') || 'Diamond', true);
            });
        });
        mrUpdateIssuedTabCounts();
        mrAutoSelectIssuedTab();
    }

    window.mrUpdateIssuedReceiveTabCounts = mrUpdateIssuedTabCounts;
    window.mrAutoSelectIssuedReceiveTab = mrAutoSelectIssuedTab;

    document.addEventListener('DOMContentLoaded', mrInitIssuedReferenceOnLoad);

    window.mrRefreshMaterialReceiveIssuedReference = mrRefreshIssuedFromServer;
})();
