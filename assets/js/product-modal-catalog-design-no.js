/**
 * Jewellery catalogue Design No. dropdown in product selection modal (sale invoice, etc.).
 */
(function () {
    'use strict';

    if (window.__auragoldCatalogDesignNoAssetLoaded) {
        return;
    }
    window.__auragoldCatalogDesignNoAssetLoaded = true;

    var designListLoaded = false;
    var designListLoading = false;
    var catalogFetchInFlight = false;
    var select2LoadPromise = null;
    var catalogTemplate = null;
    var qtyApplyInFlight = false;
    /** Max catalogue copies per DOM batch — keeps the browser responsive. */
    var MAX_BATCH_SIZE = 10;
    var CATALOGUE_QTY_MAX = MAX_BATCH_SIZE;
    var CATALOGUE_QTY_BUILD_CHUNK_MS = 0;
    var BATCH_CONFIRM_MODAL_ID = 'catalogQtyBatchConfirmModal';

    function yieldToBrowser() {
        return new Promise(function (resolve) {
            requestAnimationFrame(function () {
                setTimeout(resolve, 0);
            });
        });
    }

    function updateCatalogLoadOverlaySub(text) {
        var overlay = getTableOverlay();
        if (!overlay) return;
        var sub = overlay.querySelector('.catalog-design-load-overlay__sub');
        if (sub) sub.textContent = String(text || '');
    }

    function updateCatalogLoadOverlayTitle(text) {
        var overlay = getTableOverlay();
        if (!overlay) return;
        var titleEl = overlay.querySelector('.catalog-design-load-overlay__text');
        if (titleEl) titleEl.textContent = String(text || 'Please wait…');
    }

    function getCatalogVoucherLabel() {
        var path = String(window.location.pathname || '').toLowerCase();
        if (path.indexOf('sale-order') !== -1) return 'Sale Order';
        if (path.indexOf('sale-invoice') !== -1) return 'Sale Invoice';
        if (path.indexOf('purchase-order') !== -1) return 'Purchase Order';
        if (path.indexOf('purchase-invoice') !== -1) return 'Purchase Invoice';
        return 'order';
    }

    function normalizeCatalogQty(raw) {
        var s = String(raw == null ? '' : raw).trim();
        if (s === '') return NaN;
        var q = parseInt(s, 10);
        if (isNaN(q) || q < 1) return NaN;
        return q;
    }

    /**
     * @returns {{ ok: boolean, qty: number, empty?: boolean }}
     */
    function parseCatalogQty(rawQty, opts) {
        opts = opts || {};
        var notify = opts.notify !== false;
        var qty = normalizeCatalogQty(rawQty);
        if (isNaN(qty)) {
            if (notify) {
                alert('Enter a valid quantity.');
            }
            return { ok: false, qty: NaN, empty: true };
        }
        return { ok: true, qty: qty };
    }

    function getDesignSelect() {
        return document.getElementById('modalProductDesignNo');
    }

    function getProductListBody() {
        return document.getElementById('productListBody');
    }

    function getTableOverlay() {
        return document.getElementById('catalogDesignLoadOverlay');
    }

    function getAddRowFn() {
        if (typeof window.addEmptyProductRow === 'function') return window.addEmptyProductRow;
        return null;
    }

    function ensureAutoAddToOrderChecked() {
        var cb = document.getElementById('modalCatalogAutoAddToOrder');
        if (cb) cb.checked = true;
    }

    function isAutoAddToOrderChecked() {
        var cb = document.getElementById('modalCatalogAutoAddToOrder');
        if (!cb) return true;
        return !!cb.checked;
    }

    function maybeAutoCommitCatalogueToOrder(totalQty, numBatches) {
        /* Items stay visible in the modal — user clicks Add (Shift + A) to commit. */
        numBatches = numBatches || 1;
        ensureCatalogueModalRowsVisible();
    }

    function setCatalogBatchUiDisabled(disabled) {
        ['modalProductQty', 'modalProductQtyApplyBtn'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.disabled = !!disabled;
        });
        var addBtn = document.getElementById('modalAddBtn');
        if (addBtn) addBtn.disabled = !!disabled;
        setDesignSelectBusy(disabled);
    }

    function ensureBatchConfirmModal() {
        if (document.getElementById(BATCH_CONFIRM_MODAL_ID)) return;
        var html = ''
            + '<div id="' + BATCH_CONFIRM_MODAL_ID + '" class="catalog-qty-batch-confirm" aria-hidden="true">'
            + '  <div class="catalog-qty-batch-confirm__dialog" role="dialog" aria-modal="true">'
            + '    <p class="catalog-qty-batch-confirm__message" id="catalogQtyBatchConfirmMessage"></p>'
            + '    <div class="catalog-qty-batch-confirm__actions">'
            + '      <button type="button" class="btn btn-secondary btn-sm" data-action="cancel">Cancel</button>'
            + '      <button type="button" class="btn btn-primary btn-sm" data-action="continue" style="background:#11294b;border-color:#11294b;">Continue</button>'
            + '    </div>'
            + '  </div>'
            + '</div>';
        document.body.insertAdjacentHTML('beforeend', html);
        if (!document.getElementById('catalog-qty-batch-confirm-css')) {
            var css = document.createElement('style');
            css.id = 'catalog-qty-batch-confirm-css';
            css.textContent = ''
                + '.catalog-qty-batch-confirm{position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:10900;display:none;align-items:center;justify-content:center;padding:16px;}'
                + '.catalog-qty-batch-confirm.is-open{display:flex;}'
                + '.catalog-qty-batch-confirm__dialog{background:#fff;border-radius:8px;padding:20px;max-width:420px;width:96%;box-shadow:0 16px 40px rgba(0,0,0,.25);}'
                + '.catalog-qty-batch-confirm__message{margin:0 0 16px;font-size:0.9rem;color:#1e293b;line-height:1.5;white-space:pre-line;}'
                + '.catalog-qty-batch-confirm__actions{display:flex;justify-content:flex-end;gap:8px;}';
            document.head.appendChild(css);
        }
    }

    function confirmCatalogBatchDialog(totalQty) {
        ensureBatchConfirmModal();
        var numBatches = Math.ceil(totalQty / MAX_BATCH_SIZE);
        var modal = document.getElementById(BATCH_CONFIRM_MODAL_ID);
        var msg = document.getElementById('catalogQtyBatchConfirmMessage');
        if (msg) {
            msg.textContent = ''
                + 'You are adding ' + totalQty + ' items.\n\n'
                + 'For performance reasons, items will be created in batches of ' + MAX_BATCH_SIZE + '.\n\n'
                + totalQty + ' items = ' + numBatches + ' batch' + (numBatches === 1 ? '' : 'es') + '.\n\n'
                + 'Continue?';
        }
        return new Promise(function (resolve) {
            if (!modal) {
                resolve(false);
                return;
            }
            function cleanup(result) {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                modal.querySelector('[data-action="continue"]').removeEventListener('click', onContinue);
                modal.querySelector('[data-action="cancel"]').removeEventListener('click', onCancel);
                modal.removeEventListener('click', onBackdrop);
                resolve(result);
            }
            function onContinue(e) {
                e.preventDefault();
                cleanup(true);
            }
            function onCancel(e) {
                e.preventDefault();
                cleanup(false);
            }
            function onBackdrop(e) {
                if (e.target === modal) cleanup(false);
            }
            modal.querySelector('[data-action="continue"]').addEventListener('click', onContinue);
            modal.querySelector('[data-action="cancel"]').addEventListener('click', onCancel);
            modal.addEventListener('click', onBackdrop);
            document.body.appendChild(modal);
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
        });
    }

    function $designSelect() {
        var sel = getDesignSelect();
        if (!sel || typeof jQuery === 'undefined') return null;
        return jQuery(sel);
    }

    function hasSelect2() {
        return typeof jQuery !== 'undefined' && jQuery.fn && jQuery.fn.select2;
    }

    function ensureSelect2Loaded() {
        if (hasSelect2()) return Promise.resolve(true);
        if (select2LoadPromise) return select2LoadPromise;

        select2LoadPromise = new Promise(function (resolve) {
            if (typeof jQuery === 'undefined') {
                resolve(false);
                return;
            }
            if (!document.getElementById('auragold-select2-css')) {
                var link = document.createElement('link');
                link.id = 'auragold-select2-css';
                link.rel = 'stylesheet';
                link.href = 'assets/libs/select2/select2.css';
                document.head.appendChild(link);
            }
            if (document.getElementById('auragold-select2-js')) {
                document.getElementById('auragold-select2-js').addEventListener('load', function () {
                    resolve(hasSelect2());
                });
                return;
            }
            var script = document.createElement('script');
            script.id = 'auragold-select2-js';
            script.src = 'assets/libs/select2/select2.js';
            script.onload = function () { resolve(hasSelect2()); };
            script.onerror = function () { resolve(false); };
            document.head.appendChild(script);
        });

        return select2LoadPromise;
    }

    function destroyDesignSelect2() {
        var $s = $designSelect();
        if (!$s || !hasSelect2()) return;
        if ($s.hasClass('select2-hidden-accessible')) {
            $s.off('change.catalogDesign');
            $s.select2('destroy');
        }
    }

    function initDesignSelect2() {
        var sel = getDesignSelect();
        if (!sel || !hasSelect2()) return;
        var $s = jQuery(sel);
        if ($s.hasClass('select2-hidden-accessible')) return;

        var $modal = jQuery('#productSelectionModal');
        $s.select2({
            placeholder: 'Select design no',
            allowClear: true,
            width: '100%',
            dropdownParent: $modal.length ? $modal : jQuery('body'),
            minimumResultsForSearch: 0
        });
        $s.on('change.catalogDesign', function () {
            onDesignNoChange();
        });
    }

    function syncDesignSelect2Value(value) {
        var sel = getDesignSelect();
        var $s = $designSelect();
        if (!sel || !$s || !hasSelect2() || !$s.hasClass('select2-hidden-accessible')) return;
        sel._catalogSuppressChange = true;
        $s.val(value || '').trigger('change.select2');
        sel._catalogSuppressChange = false;
    }

    function setDesignSelectBusy(busy) {
        var sel = getDesignSelect();
        if (!sel) return;
        sel.disabled = !!busy;
        var $s = $designSelect();
        if ($s && hasSelect2() && $s.hasClass('select2-hidden-accessible')) {
            $s.prop('disabled', !!busy).trigger('change.select2');
        }
    }

    function showPleaseWait(designNo) {
        var dn = String(designNo || '').trim();
        setDesignSelectBusy(true);
        var overlay = getTableOverlay();
        if (overlay) {
            overlay.classList.add('is-visible');
            overlay.setAttribute('aria-hidden', 'false');
            updateCatalogLoadOverlayTitle('Please wait…');
            var sub = overlay.querySelector('.catalog-design-load-overlay__sub');
            if (sub) {
                sub.textContent = dn ? ('Loading design ' + dn + '…') : 'Loading catalogue items…';
            }
            void overlay.offsetHeight;
        }
    }

    function hidePleaseWait() {
        setDesignSelectBusy(false);
        var overlay = getTableOverlay();
        if (overlay) {
            overlay.classList.remove('is-visible');
            overlay.setAttribute('aria-hidden', 'true');
        }
    }

    function populateDesignSelect(items, keepValue) {
        var sel = getDesignSelect();
        if (!sel) return Promise.resolve();

        var prev = keepValue ? (sel.value || '') : '';
        destroyDesignSelect2();
        sel._catalogSuppressChange = true;
        sel.innerHTML = '';
        var ph = document.createElement('option');
        ph.value = '';
        ph.textContent = 'Select design no';
        sel.appendChild(ph);
        (items || []).forEach(function (it) {
            if (!it || !it.design_no) return;
            var opt = document.createElement('option');
            opt.value = String(it.design_no);
            opt.textContent = it.label || it.design_no;
            if (it.id) opt.setAttribute('data-catalogue-id', String(it.id));
            if (it.metal_id) opt.setAttribute('data-metal-id', String(it.metal_id));
            sel.appendChild(opt);
        });
        if (prev) {
            sel.value = prev;
            if (sel.value !== prev) sel.value = '';
        }
        sel._catalogSuppressChange = false;

        return ensureSelect2Loaded().then(function (ok) {
            if (!ok) return;
            initDesignSelect2();
            if (prev && sel.value === prev) {
                syncDesignSelect2Value(prev);
            }
        });
    }

    function loadDesignNumbers(force) {
        var sel = getDesignSelect();
        if (!sel) return Promise.resolve();
        if (catalogFetchInFlight) return Promise.resolve();
        if (designListLoaded && !force) return Promise.resolve();
        if (designListLoading) return Promise.resolve();

        designListLoading = true;
        return fetch('ajax/list-jewelry-catalogue-design-nos.php', {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                designListLoading = false;
                if (!data || !data.success) return;
                designListLoaded = true;
                return populateDesignSelect(data.items || [], true);
            })
            .catch(function () {
                designListLoading = false;
            });
    }

    function switchModalMetalTab(metalId) {
        if (!metalId) return;
        var modal = document.getElementById('productSelectionModal');
        if (!modal) return;
        var btn = modal.querySelector('.category-tab-btn[data-metal-id="' + metalId + '"]');
        if (btn) {
            btn.click();
            return;
        }
        if (typeof window.filterProductsByMetal === 'function') {
            window.filterProductsByMetal(String(metalId));
        }
    }

    function showCatalogEmptyMessage(text) {
        var tbody = getProductListBody();
        if (!tbody) return;
        tbody.innerHTML = ''
            + '<tr class="catalog-design-empty">'
            + '<td colspan="73" class="text-center text-muted py-4">' + text + '</td>'
            + '</tr>';
    }

    function clearProductListBlank() {
        var tbody = getProductListBody();
        if (tbody) tbody.innerHTML = '';
    }

    function resetProductListToDefault() {
        var tbody = getProductListBody();
        if (!tbody) return;
        tbody.innerHTML = ''
            + '<tr>'
            + '<td colspan="73" class="text-center text-muted py-4">Click "Add Product" button to add products for billing...</td>'
            + '</tr>';
    }

    function resolveRowMetalId(md, catalogueMetalId) {
        if (md && md.metal_id != null && String(md.metal_id).trim() !== '' && String(md.metal_id) !== '0') {
            return String(md.metal_id);
        }
        if (catalogueMetalId) return String(catalogueMetalId);
        return '';
    }

    function getCatalogQty() {
        var inp = document.getElementById('modalProductQty');
        return normalizeCatalogQty(inp && inp.value);
    }

    function cloneCatalogueRowData(md) {
        try {
            return JSON.parse(JSON.stringify(md || {}));
        } catch (e) {
            var copy = {};
            Object.keys(md || {}).forEach(function (k) { copy[k] = md[k]; });
            return copy;
        }
    }

    function setModalRowBarcode(row, barcode) {
        if (!row) return;
        var code = String(barcode || '').trim();
        if (code) row.setAttribute('data-barcode', code);
        else row.removeAttribute('data-barcode');
        var inp = row.querySelector('[data-column="barcode"] input');
        if (inp) inp.value = code;
    }

    function collectUsedBarcodesSeed() {
        if (typeof window.collectUsedBarcodesForInvoiceRows === 'function') {
            return window.collectUsedBarcodesForInvoiceRows(null) || [];
        }
        return [];
    }

    function appendOneCatalogueSet(template, copyIdx, totalQty, designNo, catalogueMetalId, addRowFn, tbody, metalsUsed) {
        var setRows = [];
        (template.rows || []).forEach(function (md) {
            var rowData = cloneCatalogueRowData(md);
            rowData.barcode = (totalQty > 1) ? '' : (md.barcode || '');
            addRowFn();
            var row = tbody.querySelector('.product-row:last-of-type');
            if (!row) return;
            var rowMetal = resolveRowMetalId(rowData, catalogueMetalId);
            if (rowMetal) {
                row.setAttribute('data-metal-id', rowMetal);
                metalsUsed[rowMetal] = true;
            }
            if (designNo) row.setAttribute('data-catalogue-design', designNo);
            row.setAttribute('data-catalogue-set-index', String(copyIdx));
            window.applyModalRowDataToSelectionRow(row, rowData);
            if (totalQty > 1) setModalRowBarcode(row, '');
            setRows.push({ row: row, data: rowData });
        });
        return setRows;
    }

    /**
     * @param {Object} [options]
     * @param {boolean} [options.clearFirst=true]
     * @param {number} [options.startCopyIdx=0]
     * @param {number} [options.totalQty]
     * @param {Object} [options.metalsUsed]
     */
    function buildCatalogueSetsAsync(template, qty, tbody, addRowFn, designNo, catalogueMetalId, options) {
        options = options || {};
        var clearFirst = options.clearFirst !== false;
        var startCopyIdx = options.startCopyIdx || 0;
        var totalQty = options.totalQty != null ? options.totalQty : qty;
        var metalsUsed = options.metalsUsed || {};
        var endCopyIdx = startCopyIdx + qty;

        return new Promise(function (resolve, reject) {
            if (clearFirst) tbody.innerHTML = '';
            var allSetRowGroups = [];
            var copyIdx = startCopyIdx;

            function step() {
                if (copyIdx >= endCopyIdx) {
                    resolve({ allSetRowGroups: allSetRowGroups, metalsUsed: metalsUsed });
                    return;
                }
                try {
                    var setRows = appendOneCatalogueSet(
                        template, copyIdx, totalQty, designNo, catalogueMetalId, addRowFn, tbody, metalsUsed
                    );
                    if (setRows.length) allSetRowGroups.push(setRows);
                } catch (err) {
                    reject(err);
                    return;
                }
                copyIdx++;
                setTimeout(step, CATALOGUE_QTY_BUILD_CHUNK_MS);
            }

            step();
        });
    }

    function assignBarcodesToCatalogueSets(allSetRowGroups, usedSeed) {
        return new Promise(function (resolve) {
            if (!allSetRowGroups || !allSetRowGroups.length) {
                resolve(usedSeed ? usedSeed.slice() : collectUsedBarcodesSeed());
                return;
            }
            var used = usedSeed ? usedSeed.slice() : collectUsedBarcodesSeed();
            var setIdx = 0;

            function nextSet() {
                if (setIdx >= allSetRowGroups.length) {
                    if (typeof window.syncDiamondTabSharedBarcodes === 'function') {
                        window.syncDiamondTabSharedBarcodes();
                    }
                    resolve(used);
                    return;
                }
                var setRows = allSetRowGroups[setIdx];
                if (!setRows || !setRows.length) {
                    setIdx++;
                    nextSet();
                    return;
                }
                var md = setRows[0].data || {};
                var existing = String(md.barcode || '').trim();
                if (existing) {
                    setRows.forEach(function (item) {
                        item.data.barcode = existing;
                        setModalRowBarcode(item.row, existing);
                    });
                    if (used.indexOf(existing) === -1) used.push(existing);
                    setIdx++;
                    nextSet();
                    return;
                }
                if (typeof window.resolveBarcodePrefixDigitForModal !== 'function'
                    || typeof window.getNextBarcodeFromServer !== 'function') {
                    setIdx++;
                    nextSet();
                    return;
                }
                window.resolveBarcodePrefixDigitForModal(md, function (prefix, digit) {
                    window.getNextBarcodeFromServer({ prefix: prefix, digit: digit, used: used.slice() }, function (barcode) {
                        if (barcode) {
                            used.push(barcode);
                            setRows.forEach(function (item) {
                                item.data.barcode = barcode;
                                setModalRowBarcode(item.row, barcode);
                            });
                        }
                        setIdx++;
                        nextSet();
                    });
                });
            }

            nextSet();
        });
    }

    function finalizeCatalogueRowsInModal(template, metalsUsed) {
        var catalogueMetalId = template.metal_id || 0;
        var firstMetal = Object.keys(metalsUsed || {})[0] || (catalogueMetalId ? String(catalogueMetalId) : '');
        if (firstMetal) {
            switchModalMetalTab(firstMetal);
        } else if (typeof window.filterProductsByMetal === 'function') {
            var active = document.querySelector('#productSelectionModal .category-tab-btn.active');
            var mid = active ? active.getAttribute('data-metal-id') : '';
            window.filterProductsByMetal(mid || '');
        }
        ensureCatalogueModalRowsVisible();
        var gn = document.getElementById('modalGroupName');
        if (gn && template.title) gn.value = template.title;
    }

    function ensureCatalogueModalRowsVisible() {
        var tbody = getProductListBody();
        if (!tbody) return;
        tbody.querySelectorAll('.product-row').forEach(function (row) {
            row.style.display = '';
        });
        tbody.querySelectorAll('.no-category-products-placeholder, .catalog-design-empty').forEach(function (tr) {
            tr.remove();
        });
    }

    function updateBatchProgressOverlay(designNo, batchNum, batchTotal, completedSets, totalQty) {
        updateCatalogLoadOverlayTitle(
            designNo ? ('Loading design ' + designNo + '…') : 'Creating items…'
        );
        updateCatalogLoadOverlaySub(
            'Creating items…\n'
            + 'Batch ' + batchNum + '/' + batchTotal + '\n'
            + completedSets + ' / ' + totalQty + ' completed'
        );
    }

    function runCatalogueMultiBatchApply(template, totalQty) {
        if (!template || !template.rows || !template.rows.length) {
            return Promise.resolve();
        }
        if (qtyApplyInFlight) return Promise.resolve();

        var addRowFn = getAddRowFn();
        var tbody = getProductListBody();
        if (!addRowFn || !tbody || typeof window.applyModalRowDataToSelectionRow !== 'function') {
            alert('Product modal is not ready. Please refresh the page.');
            return Promise.resolve();
        }

        var designNo = String(template.design_no || '').trim();
        var catalogueMetalId = template.metal_id || 0;
        var numBatches = Math.ceil(totalQty / MAX_BATCH_SIZE);
        var metalsUsed = {};
        var usedBarcodes = collectUsedBarcodesSeed();

        qtyApplyInFlight = true;
        setCatalogBatchUiDisabled(true);
        clearProductListBlank();
        showPleaseWait(designNo || 'catalogue');
        updateCatalogLoadOverlayTitle(designNo ? ('Loading design ' + designNo + '…') : 'Creating items…');
        updateCatalogLoadOverlaySub('Creating items…\nBatch 1/' + numBatches + '\n0 / ' + totalQty + ' completed');

        function processBatch(batchIndex) {
            if (batchIndex >= numBatches) {
                return Promise.resolve();
            }
            var start = batchIndex * MAX_BATCH_SIZE;
            var batchQty = Math.min(MAX_BATCH_SIZE, totalQty - start);
            var completedAfter = Math.min(start + batchQty, totalQty);

            updateBatchProgressOverlay(designNo, batchIndex + 1, numBatches, completedAfter, totalQty);

            return yieldToBrowser().then(function () {
                return buildCatalogueSetsAsync(template, batchQty, tbody, addRowFn, designNo, catalogueMetalId, {
                    clearFirst: false,
                    startCopyIdx: start,
                    totalQty: totalQty,
                    metalsUsed: metalsUsed
                });
            }).then(function (built) {
                updateCatalogLoadOverlaySub(
                    'Assigning barcodes…\n'
                    + 'Batch ' + (batchIndex + 1) + '/' + numBatches + '\n'
                    + completedAfter + ' / ' + totalQty + ' completed'
                );
                return assignBarcodesToCatalogueSets(built.allSetRowGroups, usedBarcodes).then(function (updatedUsed) {
                    usedBarcodes = updatedUsed || usedBarcodes;
                });
            }).then(function () {
                return yieldToBrowser().then(function () {
                    return processBatch(batchIndex + 1);
                });
            });
        }

        return processBatch(0).then(function () {
            if (!tbody.querySelector('.product-row')) {
                throw new Error('Catalogue rows could not be added to the product table.');
            }
            finalizeCatalogueRowsInModal(template, metalsUsed);
            maybeAutoCommitCatalogueToOrder(totalQty, numBatches);
        }).catch(function (err) {
            var msg = (err && err.message) ? String(err.message) : 'Could not apply catalogue quantity.';
            showCatalogEmptyMessage(msg);
            alert(msg);
        }).then(function () {
            qtyApplyInFlight = false;
            setCatalogBatchUiDisabled(false);
            hidePleaseWait();
        });
    }

    function applyCatalogueQtyToModal(template, qty) {
        if (!template || !template.rows || !template.rows.length) {
            return Promise.resolve();
        }
        if (qtyApplyInFlight) return Promise.resolve();

        var addRowFn = getAddRowFn();
        var tbody = getProductListBody();
        if (!addRowFn || !tbody || typeof window.applyModalRowDataToSelectionRow !== 'function') {
            alert('Product modal is not ready. Please refresh the page.');
            return Promise.resolve();
        }

        var qtyCheck = parseCatalogQty(qty, { notify: false });
        if (!qtyCheck.ok) {
            return Promise.resolve();
        }
        qty = qtyCheck.qty;

        var designNo = String(template.design_no || '').trim();
        var catalogueMetalId = template.metal_id || 0;
        qtyApplyInFlight = true;
        setCatalogBatchUiDisabled(true);
        showPleaseWait(designNo || 'catalogue');

        return yieldToBrowser().then(function () {
            return buildCatalogueSetsAsync(template, qty, tbody, addRowFn, designNo, catalogueMetalId, {
                clearFirst: true,
                startCopyIdx: 0,
                totalQty: qty
            });
        }).then(function (built) {
            var allSetRowGroups = built.allSetRowGroups;
            var metalsUsed = built.metalsUsed;

            if (!tbody.querySelector('.product-row')) {
                throw new Error('Catalogue rows could not be added to the product table.');
            }

            updateCatalogLoadOverlaySub('Assigning barcodes…');

            return assignBarcodesToCatalogueSets(allSetRowGroups).then(function () {
                finalizeCatalogueRowsInModal(template, metalsUsed);
                maybeAutoCommitCatalogueToOrder(qty, 1);
            });
        }).catch(function (err) {
            var msg = (err && err.message) ? String(err.message) : 'Could not apply catalogue quantity.';
            showCatalogEmptyMessage(msg);
            alert(msg);
        }).then(function () {
            qtyApplyInFlight = false;
            setCatalogBatchUiDisabled(false);
            hidePleaseWait();
        });
    }

    function onQtyApplyClick() {
        if (qtyApplyInFlight) return;
        if (catalogFetchInFlight) {
            alert('Loading design catalogue, please wait…');
            return;
        }
        if (!catalogTemplate || !catalogTemplate.rows || !catalogTemplate.rows.length) {
            alert('Select a Design No. first, then enter Qty and press Enter.');
            return;
        }
        var check = parseCatalogQty(getCatalogQty(), { notify: true });
        if (!check.ok) return;

        if (check.qty > MAX_BATCH_SIZE) {
            confirmCatalogBatchDialog(check.qty).then(function (confirmed) {
                if (confirmed) {
                    runCatalogueMultiBatchApply(catalogTemplate, check.qty);
                }
            });
            return;
        }
        applyCatalogueQtyToModal(catalogTemplate, check.qty);
    }

    function onQtyInputKeydown(e) {
        if (!e || (e.key !== 'Enter' && e.keyCode !== 13)) return;
        e.preventDefault();
        e.stopPropagation();
        onQtyApplyClick();
    }

    function bindQtyApplyButton() {
        var btn = document.getElementById('modalProductQtyApplyBtn');
        if (btn && !btn._catalogQtyBound) {
            btn._catalogQtyBound = true;
            btn.addEventListener('click', onQtyApplyClick);
        }
        var qtyInp = document.getElementById('modalProductQty');
        if (qtyInp && !qtyInp._catalogQtyEnterBound) {
            qtyInp._catalogQtyEnterBound = true;
            qtyInp.addEventListener('keydown', onQtyInputKeydown);
        }
    }

    function loadCatalogueIntoModal(designNo) {
        designNo = String(designNo || '').trim();
        if (!designNo) return Promise.resolve();
        if (catalogFetchInFlight) return Promise.resolve();

        var addRowFn = getAddRowFn();
        if (!addRowFn || typeof window.applyModalRowDataToSelectionRow !== 'function') {
            hidePleaseWait();
            alert('Product modal is not ready. Please refresh the page.');
            return Promise.resolve();
        }

        catalogFetchInFlight = true;
        clearProductListBlank();
        showPleaseWait(designNo);

        return yieldToBrowser().then(function () {
            return fetch(
                'ajax/get-jewelry-catalogue-for-modal.php?design_no=' + encodeURIComponent(designNo),
                { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }
            );
        })
            .then(function (r) {
                return r.text().then(function (text) {
                    var data = null;
                    try {
                        data = text ? JSON.parse(text) : null;
                    } catch (parseErr) {
                        var snippet = String(text || '').replace(/\s+/g, ' ').trim();
                        if (snippet.length > 220) {
                            snippet = snippet.slice(0, 220) + '…';
                        }
                        throw new Error(snippet || ('Server returned HTTP ' + r.status));
                    }
                    if (!r.ok && (!data || !data.message)) {
                        throw new Error((data && data.message) || ('Server returned HTTP ' + r.status));
                    }
                    return data;
                });
            })
            .then(function (data) {
                catalogFetchInFlight = false;
                hidePleaseWait();

                if (!data || !data.success) {
                    var failMsg = (data && data.message) || 'Could not load catalogue.';
                    showCatalogEmptyMessage(failMsg);
                    alert(failMsg);
                    return;
                }

                var rows = data.modal_rows || [];
                if (!rows.length) {
                    var dn = String(designNo || '').trim();
                    var hint = dn
                        ? ('Design ' + dn + ' has no Bill of Material lines. Open Product Catalogue, edit this design, and add products under Bill of Material — or ensure title, weight, and amount are saved on the catalogue.')
                        : 'This catalogue has no Bill of Material items.';
                    showCatalogEmptyMessage(hint);
                    alert(hint);
                    return;
                }

                catalogTemplate = {
                    design_no: designNo,
                    rows: rows,
                    metal_id: data.metal_id || 0,
                    title: data.title || '',
                    group_image: data.group_image || ''
                };

                var catalogueGroupImage = data.group_image || '';
                if (!catalogueGroupImage) {
                    for (var gi = 0; gi < rows.length; gi++) {
                        if (rows[gi].group_image && String(rows[gi].group_image).trim()) {
                            catalogueGroupImage = rows[gi].group_image;
                            break;
                        }
                    }
                }
                if (catalogueGroupImage) {
                    window.productModalGroupImageByTab = window.productModalGroupImageByTab || {};
                    var tabKey = data.metal_id ? String(data.metal_id) : '';
                    var parsedGi = (typeof window.auragoldCoParseGroupImageAttr === 'function')
                        ? window.auragoldCoParseGroupImageAttr(catalogueGroupImage)
                        : catalogueGroupImage;
                    if (parsedGi) {
                        window.productModalGroupImageByTab[tabKey] = parsedGi;
                    }
                }

                clearProductListBlank();
            })
            .catch(function (err) {
                catalogFetchInFlight = false;
                hidePleaseWait();
                var msg = (err && err.message) ? String(err.message) : 'Could not load catalogue design.';
                showCatalogEmptyMessage(msg);
                alert(msg);
            });
    }

    function onDesignNoChange() {
        var sel = getDesignSelect();
        if (!sel || sel._catalogSuppressChange) return;
        var val = (sel.value || '').trim();
        if (!val) {
            if (catalogFetchInFlight) return;
            catalogTemplate = null;
            hidePleaseWait();
            resetProductListToDefault();
            var qtyInp = document.getElementById('modalProductQty');
            if (qtyInp) qtyInp.value = '';
            return;
        }
        loadCatalogueIntoModal(val);
    }

    function bindDesignNoUi() {
        var sel = getDesignSelect();
        if (!sel || sel._catalogDesignBound) return;
        sel._catalogDesignBound = true;

        ensureSelect2Loaded().then(function (ok) {
            if (ok) {
                initDesignSelect2();
                return;
            }
            sel.addEventListener('change', onDesignNoChange);
        });
    }

    function onModalShown() {
        designListLoaded = false;
        hidePleaseWait();
        ensureAutoAddToOrderChecked();
        bindDesignNoUi();
        bindQtyApplyButton();
        loadDesignNumbers(true);
    }

    function init() {
        ensureAutoAddToOrderChecked();
        bindDesignNoUi();
        bindQtyApplyButton();
        if (typeof jQuery !== 'undefined') {
            jQuery(document).on('shown.bs.modal', '#productSelectionModal', onModalShown);
            jQuery(document).on('hidden.bs.modal', '#productSelectionModal', function () {
                catalogFetchInFlight = false;
                qtyApplyInFlight = false;
                catalogTemplate = null;
                setCatalogBatchUiDisabled(false);
                hidePleaseWait();
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.auragoldLoadJewelryCatalogDesignNumbers = loadDesignNumbers;
    window.auragoldLoadJewelryCatalogIntoModal = loadCatalogueIntoModal;
    window.auragoldApplyCatalogueQtyToModal = function (qty) {
        if (!catalogTemplate) return Promise.resolve();
        return applyCatalogueQtyToModal(catalogTemplate, qty != null ? qty : getCatalogQty());
    };
    window.auragoldRunCatalogueMultiBatchApply = runCatalogueMultiBatchApply;
    window.auragoldHideCatalogDesignLoader = hidePleaseWait;
    window.auragoldShowCatalogDesignLoader = showPleaseWait;
})();
