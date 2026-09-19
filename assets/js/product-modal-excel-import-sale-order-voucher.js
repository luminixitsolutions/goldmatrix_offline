/**
 * Product Selection modal: Excel import (sale_order voucher preview + commit to main table).
 * Requires page to define window.saleOrderExcelProductToItemProduct and window.commitProductSelectionModalToMainTable
 * before this script loads. Optional: window.AURAGOLD_EXCEL_IMPORT_VOUCHER (default 'sale_order'),
 * window.AURAGOLD_EXCEL_IMPORT_DATE_INPUT_ID (default 'orderDate').
 */
(function () {
    'use strict';

    function getProductModalExcelSampleUrl() {
        var link = document.querySelector('.js-product-modal-excel-sample-download');
        var base = (link && link.getAttribute('data-sample-base'))
            ? link.getAttribute('data-sample-base')
            : 'ajax/download-stock-journal-excel-sample.php?voucher=sale_order';
        var mid = '';
        if (typeof currentMetalId !== 'undefined' && currentMetalId !== null && String(currentMetalId) !== '') {
            mid = String(currentMetalId);
        } else {
            var activeTab = document.querySelector('#productSelectionModal .category-tab-btn.active');
            if (activeTab) {
                mid = activeTab.getAttribute('data-metal-id') || '';
            }
        }
        var url = base;
        if (mid) {
            url += (url.indexOf('?') >= 0 ? '&' : '?') + 'metal_id=' + encodeURIComponent(mid);
        }
        var diamondFilter = document.getElementById('modalDiamondCategoryFilter');
        var filterRow = document.getElementById('modalDiamondCategoryFilterRow');
        if (diamondFilter && filterRow && filterRow.style.display !== 'none' && diamondFilter.value) {
            url += '&diamond_category=' + encodeURIComponent(diamondFilter.value);
        }
        return url;
    }

    function syncProductModalExcelSampleLink() {
        document.querySelectorAll('.js-product-modal-excel-sample-download').forEach(function (a) {
            a.href = getProductModalExcelSampleUrl();
        });
    }

    window.syncProductModalExcelSampleLink = syncProductModalExcelSampleLink;

    function bindProductModalExcelImport() {
        var fileInput = document.getElementById('productModalExcelImportFile');
        if (!fileInput || fileInput.getAttribute('data-auragold-excel-import-bound') === '1') {
            return;
        }
        fileInput.setAttribute('data-auragold-excel-import-bound', '1');

        syncProductModalExcelSampleLink();

        document.addEventListener('click', function (e) {
            var sampleLink = e.target.closest('.js-product-modal-excel-sample-download');
            if (sampleLink) {
                sampleLink.href = getProductModalExcelSampleUrl();
                return;
            }
            var trigger = e.target.closest('.js-product-modal-excel-import-trigger');
            if (!trigger) return;
            e.preventDefault();
            fileInput.value = '';
            fileInput.click();
        });

        fileInput.addEventListener('change', function () {
            if (!fileInput.files || !fileInput.files.length) return;

            var voucher = (typeof window.AURAGOLD_EXCEL_IMPORT_VOUCHER === 'string' && window.AURAGOLD_EXCEL_IMPORT_VOUCHER.trim())
                ? window.AURAGOLD_EXCEL_IMPORT_VOUCHER.trim()
                : 'sale_order';
            var dateFieldId = (typeof window.AURAGOLD_EXCEL_IMPORT_DATE_INPUT_ID === 'string' && window.AURAGOLD_EXCEL_IMPORT_DATE_INPUT_ID.trim())
                ? window.AURAGOLD_EXCEL_IMPORT_DATE_INPUT_ID.trim()
                : 'orderDate';

            var fd = new FormData();
            fd.append('excel_file', fileInput.files[0]);
            fd.append('voucher', voucher);
            fd.append('preview_only', '1');

            var midImp = (typeof currentMetalId !== 'undefined' && currentMetalId) ? String(currentMetalId) : '';
            if (!midImp) {
                var activeTabImp = document.querySelector('#productSelectionModal .category-tab-btn.active');
                if (activeTabImp) {
                    midImp = activeTabImp.getAttribute('data-metal-id') || '';
                }
            }
            if (midImp) {
                fd.append('metal_id', midImp);
            }
            var od = document.getElementById(dateFieldId);
            if (od && od.value) fd.append('date', od.value);
            var gn = document.getElementById('modalGroupName');
            if (gn && gn.value) fd.append('group_name', gn.value);
            var cm = document.getElementById('modalComment');
            if (cm && cm.value) fd.append('comment', cm.value);

            var loaderEl = document.getElementById('productModalExcelImportLoader');
            function setExcelImportBusy(on) {
                document.querySelectorAll('.product-modal-excel-import-wrap .dropdown-toggle').forEach(function (btn) {
                    btn.disabled = !!on;
                });
                if (loaderEl) {
                    if (on) {
                        loaderEl.classList.add('is-visible');
                        loaderEl.setAttribute('aria-hidden', 'false');
                    } else {
                        loaderEl.classList.remove('is-visible');
                        loaderEl.setAttribute('aria-hidden', 'true');
                    }
                }
            }
            setExcelImportBusy(true);

            var mapPair = window.saleOrderExcelProductToItemProduct;
            var commitFn = window.commitProductSelectionModalToMainTable;

            fetch('ajax/import-stock-journal-excel.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) {
                    return r.text().then(function (text) {
                        var data = null;
                        try {
                            data = text ? JSON.parse(text) : null;
                        } catch (err) {
                            var s = (text || '').replace(/\s+/g, ' ').trim().slice(0, 300);
                            throw new Error(s || 'Server did not return JSON (HTTP ' + r.status + ')');
                        }
                        if (!data) {
                            throw new Error('Empty response (HTTP ' + r.status + ')');
                        }
                        return data;
                    });
                })
                .then(function (data) {
                    if (data.status === 'success' || data.status === true) {
                        var rows = data.products || [];
                        if (!rows.length) {
                            alert('No importable rows found. Each row needs Metal Qty or Gross Wt., and a valid Barcode or Product ID + Characteristic ID.');
                            return;
                        }
                        var tbody = document.getElementById('productListBody');
                        if (tbody) {
                            tbody.querySelectorAll('tr:not(.product-row)').forEach(function (tr) { tr.remove(); });
                        }
                        var n = 0;
                        var skipped = 0;
                        rows.forEach(function (p) {
                            var pair = typeof mapPair === 'function' ? mapPair(p) : null;
                            if (!pair || !pair.item.product_id || !pair.item.product_characteristic_id) {
                                skipped++;
                                return;
                            }
                            if (typeof addProductRowToSelectionTable === 'function') {
                                addProductRowToSelectionTable(pair.item, pair.product);
                                n++;
                            }
                        });
                        if (typeof updateJewelleryDiamondCaratFromDiamondAndGemstone === 'function') {
                            updateJewelleryDiamondCaratFromDiamondAndGemstone();
                        }
                        if (n > 0) {
                            var commitRes = typeof commitFn === 'function'
                                ? commitFn({
                                    forceNewBarcodes: true,
                                    forceSeparateRows: true,
                                    closeModal: true,
                                    notifyEmpty: false
                                })
                                : null;
                            if (commitRes && commitRes.ok) {
                                if (skipped > 0 && typeof console !== 'undefined' && console.log) {
                                    console.log('Excel import: ' + skipped + ' row(s) skipped.');
                                }
                            } else if (commitRes && commitRes.reason === 'edit_mode') {
                                /* alert already shown in commit */
                            } else if (!commitRes || !commitRes.ok) {
                                if (skipped > 0) {
                                    alert('Imported ' + n + ' line(s) into the modal. ' + skipped + ' row(s) skipped. Use Add (Shift + A) to add to the order.');
                                } else {
                                    alert(data.message || 'Import complete. Use Add (Shift + A) to add lines to the order.');
                                }
                            }
                        } else {
                            alert('No lines were added. Check Barcode or Product ID + Characteristic ID on each row.');
                        }
                        return;
                    }
                    alert(data.message || 'Import failed');
                })
                .catch(function (err) {
                    alert(err && err.message ? err.message : 'Import request failed');
                })
                .finally(function () {
                    setExcelImportBusy(false);
                });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindProductModalExcelImport);
    } else {
        bindProductModalExcelImport();
    }
})();
