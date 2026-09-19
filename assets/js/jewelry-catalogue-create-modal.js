/**
 * Jewellery catalogue create — Bill of Material product selection modal (same UI as sale invoice).
 */
(function () {
    'use strict';

    var currentMetalId = null;
    var currentMetalName = '';
    var emptyRowHtmlCache = null;
    var bomEditIndex = -1;
    var bomEditShowAllRows = false;
    var modalAddBtnDefaultHtml = '';
    var modalAddBtn2DefaultHtml = '';

    function syncWindowMetalTabState() {
        window.currentMetalId = currentMetalId;
        window.currentMetalName = currentMetalName;
    }

    window.productModalColumnVisibilityByTab = window.productModalColumnVisibilityByTab || {};
    window.productModalOriginalHeaderHtml = window.productModalOriginalHeaderHtml || {};

    function escHtml(s) {
        if (typeof window.escapeHtml === 'function') return window.escapeHtml(s);
        if (s == null) return '';
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function populateSelect(selectElement, data, valueField, textField, placeholder) {
        if (!selectElement) return;
        placeholder = placeholder || 'Select';
        selectElement.innerHTML = '';
        var ph = document.createElement('option');
        ph.value = '';
        ph.textContent = placeholder;
        selectElement.appendChild(ph);
        (data || []).forEach(function (item) {
            var option = document.createElement('option');
            option.value = item[valueField];
            option.textContent = item[textField];
            if (item.purity) option.setAttribute('data-purity', item.purity);
            selectElement.appendChild(option);
        });
    }
    window.populateSelect = populateSelect;

    function isDiamondMetalName(name) {
        if (typeof window.isDiamondStonesMetalDisplayName === 'function' && window.isDiamondStonesMetalDisplayName(name)) {
            return true;
        }
        if (typeof window.isLoosOrLooseDiamondMetalDisplayName === 'function' && window.isLoosOrLooseDiamondMetalDisplayName(name)) {
            return true;
        }
        return String(name || '').toLowerCase().indexOf('diamond') !== -1;
    }

    function applyTabMetalModalClass(modal, metalName) {
        if (!modal) return;
        var isDiamond = isDiamondMetalName(metalName);
        if (isDiamond) {
            modal.classList.remove('product-modal-metal-tab');
        } else {
            modal.classList.add('product-modal-metal-tab');
        }
        var filterRow = document.getElementById('modalDiamondCategoryFilterRow');
        if (filterRow) {
            filterRow.style.display = isDiamond ? '' : 'none';
        }
    }

    /**
     * Show/hide modal columns per active metal tab (Gold vs Diamond & Stones).
     */
    function applyProductModalColumnVisibilityForTab(tabKey) {
        if (tabKey === undefined || tabKey === null) return;
        var tk = tabKey === '' ? '' : String(tabKey);
        var settingsDropdown = document.getElementById('modalTableSettingsDropdown');
        var table = document.getElementById('productListTable');
        if (!settingsDropdown || !table) return;

        var isDiamondTab = typeof window.isDiamondTabActive === 'function' && window.isDiamondTabActive();
        var diamondVisibleSet = {};
        if (typeof window.DIAMOND_TAB_VISIBLE_COLUMNS !== 'undefined' && window.DIAMOND_TAB_VISIBLE_COLUMNS) {
            window.DIAMOND_TAB_VISIBLE_COLUMNS.forEach(function (col) { diamondVisibleSet[col] = 1; });
        }
        var savedDiamond = window.productModalColumnVisibilityByTab &&
            (window.productModalColumnVisibilityByTab[tk] || window.productModalColumnVisibilityByTab[tabKey]);
        var prefs;
        if (isDiamondTab) {
            prefs = Object.assign({}, diamondVisibleSet, savedDiamond && typeof savedDiamond === 'object' ? savedDiamond : {});
        } else {
            prefs = typeof window.mergeProductModalMetalTabPrefs === 'function'
                ? window.mergeProductModalMetalTabPrefs(tk, tabKey)
                : (window.productModalColumnVisibilityByTab[tk] || window.productModalColumnVisibilityByTab[tabKey]);
        }
        var diamondGroupColumns = typeof window.getDiamondGroupColumnKeys === 'function'
            ? window.getDiamondGroupColumnKeys()
            : ['pkt-wt', 'pkt-less-wt', 'gross-wt', 'stone-weight', 'less-wt', 'net-wt', 'quantity', 'rate', 'amount'];

        function modalTabColumnShouldShow(columnName) {
            if (!isDiamondTab && columnName === 'category') return false;
            if (!isDiamondTab && diamondGroupColumns.indexOf(columnName) !== -1) return false;
            if (isDiamondTab) {
                if (prefs && Object.prototype.hasOwnProperty.call(prefs, columnName)) {
                    return prefs[columnName] === 1;
                }
                return diamondVisibleSet[columnName] === 1;
            }
            if (prefs && Object.prototype.hasOwnProperty.call(prefs, columnName)) {
                return prefs[columnName] === 1;
            }
            return true;
        }

        var columnKeys = typeof window.getProductModalHeaderColumnKeys === 'function'
            ? window.getProductModalHeaderColumnKeys()
            : [];
        if (!columnKeys.length) {
            var kmap = {};
            table.querySelectorAll('thead [data-column]').forEach(function (el) {
                var c = el.getAttribute('data-column');
                if (c) kmap[c] = true;
            });
            columnKeys = Object.keys(kmap);
        }
        columnKeys.forEach(function (col) {
            var show = modalTabColumnShouldShow(col);
            if (typeof window.toggleColumnVisibility === 'function') {
                window.toggleColumnVisibility(col, show);
            }
        });

        settingsDropdown.querySelectorAll('input[type="checkbox"][data-column]').forEach(function (checkbox) {
            var columnName = checkbox.getAttribute('data-column');
            checkbox.checked = modalTabColumnShouldShow(columnName);
        });
        if (typeof window.syncProductModalColumnGroupMasterCheckboxes === 'function') {
            window.syncProductModalColumnGroupMasterCheckboxes();
        }

        var groupHeaderRow = table.querySelector('thead tr:first-child');
        var diamondGroupHeader = groupHeaderRow ? groupHeaderRow.querySelector('th[data-group="diamond-group"]') : null;
        if (isDiamondTab) {
            if (diamondGroupHeader) {
                diamondGroupHeader.style.display = '';
                diamondGroupHeader.classList.remove('hidden');
            }
        } else if (diamondGroupHeader) {
            diamondGroupHeader.style.display = 'none';
            diamondGroupHeader.classList.add('hidden');
            diamondGroupHeader.setAttribute('colspan', '1');
        }

        if (!isDiamondTab && typeof window.applyMetalGroupHeaderLabelsToGrids === 'function') {
            window.applyMetalGroupHeaderLabelsToGrids();
        }

        if (typeof window.syncProductModalColumnLayoutAfterToggle === 'function') {
            window.syncProductModalColumnLayoutAfterToggle();
        } else if (typeof window.fixProductModalHeader === 'function') {
            window.fixProductModalHeader();
        }
    }
    window.applyProductModalColumnVisibilityForTab = applyProductModalColumnVisibilityForTab;

    function getEmptyRowHtml() {
        if (emptyRowHtmlCache) return emptyRowHtmlCache;
        if (window.JCC_MODAL_EMPTY_ROW_HTML) {
            emptyRowHtmlCache = String(window.JCC_MODAL_EMPTY_ROW_HTML).trim();
            return emptyRowHtmlCache;
        }
        return '';
    }

    function filterProductsByMetal(metalId) {
        var tbody = document.getElementById('productListBody');
        if (!tbody) return;
        if (bomEditShowAllRows) {
            showAllModalProductRows();
            return;
        }
        var allRows = tbody.querySelectorAll('tr.product-row');
        var visibleCount = 0;
        allRows.forEach(function (row) {
            var rowMetalId = row.getAttribute('data-metal-id');
            if (!rowMetalId || rowMetalId === String(metalId)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        var placeholderRow = tbody.querySelector('tr.no-category-products-placeholder');
        if (visibleCount === 0) {
            tbody.querySelectorAll('tr:not(.product-row)').forEach(function (tr) {
                if (!tr.classList.contains('no-category-products-placeholder')) tr.remove();
            });
            if (!placeholderRow) {
                var tr = document.createElement('tr');
                tr.className = 'no-category-products-placeholder';
                tr.innerHTML = '<td colspan="73" class="text-center text-muted py-4">No products in this tab. Click Add Product to add a row.</td>';
                tbody.appendChild(tr);
            }
        } else if (placeholderRow) {
            placeholderRow.remove();
            tbody.querySelectorAll('tr:not(.product-row)').forEach(function (tr) { tr.remove(); });
        }
    }

    function showAllModalProductRows() {
        var tbody = document.getElementById('productListBody');
        if (!tbody) return;
        var visibleCount = 0;
        tbody.querySelectorAll('tr.product-row').forEach(function (row) {
            row.style.display = '';
            visibleCount++;
        });
        var placeholderRow = tbody.querySelector('tr.no-category-products-placeholder');
        if (visibleCount === 0) {
            if (!placeholderRow) {
                var tr = document.createElement('tr');
                tr.className = 'no-category-products-placeholder';
                tr.innerHTML = '<td colspan="73" class="text-center text-muted py-4">No products in this tab. Click Add Product to add a row.</td>';
                tbody.appendChild(tr);
            }
        } else if (placeholderRow) {
            placeholderRow.remove();
        }
    }

    function modalDataMetalId(md) {
        if (!md || typeof md !== 'object') return 0;
        var mid = parseInt(md.metal_id, 10) || 0;
        if (mid > 0) return mid;
        var mn = String(md.metal_name || '').trim().toLowerCase();
        if (mn && Array.isArray(window.metals)) {
            for (var i = 0; i < window.metals.length; i++) {
                var m = window.metals[i];
                var dn = String(m.display_name || m.system_name || m.name || '').trim().toLowerCase();
                if (!dn) continue;
                if (dn === mn || mn.indexOf(dn) !== -1 || dn.indexOf(mn) !== -1) {
                    return parseInt(m.id, 10) || 0;
                }
            }
        }
        var desc = String(md.product_name || md.description || '').trim();
        var dash = desc.indexOf(' - ');
        if (dash !== -1) {
            var tail = desc.slice(dash + 3).trim().toLowerCase();
            if (tail && Array.isArray(window.metals)) {
                for (var j = 0; j < window.metals.length; j++) {
                    var m2 = window.metals[j];
                    var dn2 = String(m2.display_name || m2.system_name || m2.name || '').trim().toLowerCase();
                    if (!dn2) continue;
                    if (dn2 === tail || tail.indexOf(dn2) !== -1 || dn2.indexOf(tail) !== -1) {
                        return parseInt(m2.id, 10) || 0;
                    }
                }
            }
        }
        return 0;
    }

    function resolveEditModalTabMetalId(bomRow, fallbackMetalId) {
        fallbackMetalId = parseInt(fallbackMetalId, 10) || 0;
        var rows = bomRowToModalRows(bomRow);
        var counts = {};
        var order = [];
        rows.forEach(function (md) {
            var mid = modalDataMetalId(md);
            if (mid > 0) {
                counts[mid] = (counts[mid] || 0) + 1;
                if (order.indexOf(mid) === -1) order.push(mid);
            }
        });
        if (order.length === 1) return order[0];
        if (order.length > 1) {
            var best = order[0];
            var bestN = counts[best] || 0;
            order.forEach(function (mid) {
                if ((counts[mid] || 0) > bestN) {
                    best = mid;
                    bestN = counts[mid];
                }
            });
            return best;
        }
        if (fallbackMetalId > 0) return fallbackMetalId;
        var metalSel = document.getElementById('jccMetal');
        if (metalSel && metalSel.value) {
            return parseInt(metalSel.value, 10) || 0;
        }
        return 0;
    }

    function bomRowHasMixedMetals(bomRow) {
        var rows = bomRowToModalRows(bomRow);
        var seen = {};
        var n = 0;
        rows.forEach(function (md) {
            var mid = modalDataMetalId(md);
            if (mid > 0 && !seen[mid]) {
                seen[mid] = true;
                n++;
            }
        });
        return n > 1;
    }

    function initCategoryTabs() {
        var modal = document.getElementById('productSelectionModal');
        if (!modal) return;

        if (!modal._jccCategoryTabsInited) {
            modal._jccCategoryTabsInited = true;
            modal.addEventListener('click', function (e) {
                var btn = e.target.closest('.category-tab-btn');
                if (!btn || !modal.contains(btn)) return;
                e.preventDefault();
                e.stopPropagation();
                modal.querySelectorAll('.category-tab-btn').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                currentMetalId = btn.getAttribute('data-metal-id');
                currentMetalName = btn.getAttribute('data-metal-name') || '';
                syncWindowMetalTabState();
                if (bomEditIndex >= 0) {
                    bomEditShowAllRows = false;
                }
                filterProductsByMetal(currentMetalId);
                applyTabMetalModalClass(modal, currentMetalName);
                applyProductModalColumnVisibilityForTab(currentMetalId || '');
            });
        }

        var metalSel = document.getElementById('jccMetal');
        var preMetal = '';
        if (bomEditIndex >= 0 && currentMetalId) {
            preMetal = String(currentMetalId);
        } else if (metalSel && metalSel.value) {
            preMetal = metalSel.value;
        }
        var tabBtn = preMetal
            ? modal.querySelector('.category-tab-btn[data-metal-id="' + preMetal + '"]')
            : modal.querySelector('.category-tab-btn.active');
        if (tabBtn && bomEditIndex < 0) {
            modal.querySelectorAll('.category-tab-btn').forEach(function (b) { b.classList.remove('active'); });
            tabBtn.classList.add('active');
            currentMetalId = tabBtn.getAttribute('data-metal-id');
            currentMetalName = tabBtn.getAttribute('data-metal-name') || '';
            syncWindowMetalTabState();
            filterProductsByMetal(currentMetalId);
            applyTabMetalModalClass(modal, currentMetalName);
            applyProductModalColumnVisibilityForTab(currentMetalId || '');
        }
    }

    function setBomEditMode(editing) {
        var btn = document.getElementById('modalAddBtn');
        var btn2 = document.getElementById('modalAddBtn2');
        if (btn) {
            if (!modalAddBtnDefaultHtml) modalAddBtnDefaultHtml = btn.innerHTML;
            btn.innerHTML = editing
                ? '<i class="feather icon-save"></i> Update (Shift + A)'
                : modalAddBtnDefaultHtml;
            if (typeof feather !== 'undefined' && feather.replace) feather.replace({ scope: btn });
        }
        if (btn2) {
            if (!modalAddBtn2DefaultHtml) modalAddBtn2DefaultHtml = btn2.innerHTML;
            btn2.innerHTML = editing
                ? '<i class="feather icon-save"></i> Update (Shift + A)'
                : modalAddBtn2DefaultHtml;
            if (typeof feather !== 'undefined' && feather.replace) feather.replace({ scope: btn2 });
        }
    }

    function resetBomEditMode() {
        bomEditIndex = -1;
        bomEditShowAllRows = false;
        setBomEditMode(false);
    }

    function activateMetalTab(metalId) {
        var modal = document.getElementById('productSelectionModal');
        if (!modal || metalId == null || metalId === '') return;
        var tabBtn = modal.querySelector('.category-tab-btn[data-metal-id="' + String(metalId) + '"]');
        if (!tabBtn) return;
        modal.querySelectorAll('.category-tab-btn').forEach(function (b) { b.classList.remove('active'); });
        tabBtn.classList.add('active');
        currentMetalId = tabBtn.getAttribute('data-metal-id');
        currentMetalName = tabBtn.getAttribute('data-metal-name') || '';
        syncWindowMetalTabState();
        filterProductsByMetal(currentMetalId);
        applyTabMetalModalClass(modal, currentMetalName);
        applyProductModalColumnVisibilityForTab(currentMetalId || '');
    }

    function bomItemToModalData(item) {
        if (!item || typeof item !== 'object') return null;
        if (item._modal && typeof item._modal === 'object') return Object.assign({}, item._modal);
        var desc = String(item.description || '').trim();
        var productName = desc;
        var metalName = '';
        var dash = desc.indexOf(' - ');
        if (dash !== -1) {
            productName = desc.slice(0, dash).trim();
            metalName = desc.slice(dash + 3).trim();
        }
        return {
            product_name: productName,
            metal_name: metalName,
            barcode: item.barcode || '',
            design_no: item.design_no || '',
            quantity: item.quantity != null && item.quantity !== '' ? item.quantity : '1',
            gross_wt: item.gross_wt,
            final_wt: item.final_wt,
            net_wt: item.net_wt,
            pure_wt: item.pure_wt,
            making_amount: item.making,
            tax: item.tax,
            category: item.variants || ''
        };
    }

    function bomRowToModalRows(bomRow) {
        if (!bomRow || typeof bomRow !== 'object') return [];
        if (Array.isArray(bomRow.group_items) && bomRow.group_items.length) {
            return bomRow.group_items.map(function (gi) {
                return bomItemToModalData(gi);
            }).filter(Boolean);
        }
        if (bomRow._modal && typeof bomRow._modal === 'object') {
            return [Object.assign({}, bomRow._modal)];
        }
        var one = bomItemToModalData(bomRow);
        return one ? [one] : [];
    }

    function populateModalFromBomRow(bomRow, defaultMetalId) {
        var rows = bomRowToModalRows(bomRow);
        var tbody = document.getElementById('productListBody');
        if (!tbody) return;
        var tabMetalId = resolveEditModalTabMetalId(bomRow, defaultMetalId);
        var mixedMetals = bomRowHasMixedMetals(bomRow);
        bomEditShowAllRows = bomEditIndex >= 0 && mixedMetals && rows.length > 0;
        if (!rows.length) {
            if (tabMetalId > 0) activateMetalTab(tabMetalId);
            tbody.innerHTML = '<tr><td colspan="73" class="text-center text-muted py-4">Click "Add Product" to add rows, then press Update (Shift + A).</td></tr>';
            return;
        }
        if (tabMetalId > 0) {
            activateMetalTab(tabMetalId);
        }
        tbody.innerHTML = '';
        rows.forEach(function (md) {
            addEmptyProductRow();
            var row = tbody.querySelector('tr.product-row:last-child');
            if (row && typeof window.applyModalRowDataToSelectionRow === 'function') {
                window.applyModalRowDataToSelectionRow(row, md);
            }
        });
        if (bomEditShowAllRows) {
            showAllModalProductRows();
        } else if (tabMetalId > 0) {
            filterProductsByMetal(tabMetalId);
        }
        var gn = document.getElementById('modalGroupName');
        if (gn && bomRow.description) {
            gn.value = String(bomRow.description);
        }
    }

    function showProductModalShell() {
        var modal = document.getElementById('productSelectionModal');
        if (!modal) {
            alert('Product modal not loaded. Please refresh the page.');
            return false;
        }
        if (typeof jQuery !== 'undefined' && jQuery.fn.modal) {
            jQuery('#productSelectionModal').modal('show');
        } else {
            modal.style.display = 'block';
            modal.classList.add('show');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('modal-open');
        }
        try {
            if (bomEditIndex < 0) initCategoryTabs();
        } catch (e1) { /* ignore */ }
        return true;
    }

    function clearModalFields() {
        ['modalProductBarcode', 'modalProductCode', 'modalProductDesignNo'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.value = '';
        });
        var qty = document.getElementById('modalProductQty');
        if (qty) qty.value = '';
        var gn = document.getElementById('modalGroupName');
        if (gn) gn.value = '';
        var cm = document.getElementById('modalComment');
        if (cm) cm.value = '';
    }

    function openProductModal() {
        resetBomEditMode();
        if (!showProductModalShell()) return;
        clearModalFields();
        var tbody = document.getElementById('productListBody');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="73" class="text-center text-muted py-4">Click "Add Product" to add rows, then press ADD (Shift + A) to add to Bill of Material.</td></tr>';
        }
    }

    function openProductModalForEdit(bomRow, index) {
        bomEditIndex = typeof index === 'number' ? index : -1;
        setBomEditMode(true);
        if (!showProductModalShell()) return;
        clearModalFields();
        var metalSel = document.getElementById('jccMetal');
        var defaultMetalId = metalSel && metalSel.value ? parseInt(metalSel.value, 10) : 0;
        populateModalFromBomRow(bomRow || {}, defaultMetalId);
    }
    window.openProductModal = openProductModal;
    window.jccOpenBomProductModal = openProductModal;
    window.jccOpenBomProductModalForEdit = openProductModalForEdit;

    function hideProductModal() {
        var modal = document.getElementById('productSelectionModal');
        if (!modal) return;
        if (typeof jQuery !== 'undefined' && jQuery.fn.modal) {
            jQuery('#productSelectionModal').modal('hide');
        }
        modal.style.display = 'none';
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        document.querySelectorAll('.modal-backdrop').forEach(function (b) { b.remove(); });
        resetBomEditMode();
    }
    window.hideProductModal = hideProductModal;

    function addEmptyProductRow() {
        var tbody = document.getElementById('productListBody');
        if (!tbody) return;
        tbody.querySelectorAll('tr:not(.product-row)').forEach(function (tr) { tr.remove(); });

        var row = document.createElement('tr');
        row.className = 'product-row';
        row.setAttribute('data-product-id', '');
        row.setAttribute('data-characteristic-id', '');
        row.setAttribute('data-metal-id', currentMetalId || '');

        var html = getEmptyRowHtml();
        if (!html) {
            alert('Could not load product row template.');
            return;
        }
        row.innerHTML = html;
        tbody.appendChild(row);

        if (typeof window.reorderModalRowCellsToMatchHeader === 'function') {
            window.reorderModalRowCellsToMatchHeader(row);
        }

        var carats = window.carats || [];
        var locations = window.locations || [];
        var categories = window.categories || [];
        var isDiamond = typeof window.isDiamondTabActive === 'function' && window.isDiamondTabActive();

        var caratSelect = row.querySelector('.carat-select');
        if (caratSelect) {
            if (typeof window.populateCaratSelectForModalRow === 'function') {
                window.populateCaratSelectForModalRow(caratSelect, row);
            } else {
                populateSelect(caratSelect, carats, 'id', 'name', 'Select Karat');
            }
        }
        var locationSelect = row.querySelector('.location-select');
        if (locationSelect) populateSelect(locationSelect, locations, 'id', 'name', 'Select Location');
        row.querySelectorAll('.product-category-select').forEach(function (sel) {
            populateSelect(sel, categories, 'id', 'name', 'Select Category');
        });
        var categorySelect = row.querySelector('[data-column="category"] select');
        if (categorySelect && typeof window.populateCategorySelectForModal === 'function') {
            window.populateCategorySelectForModal(categorySelect, isDiamond);
        }
        var calculationSelect = row.querySelector('[data-column="calculation"] select');
        if (calculationSelect) {
            if (typeof window.applyCalculationSelectOptionsForTab === 'function') {
                window.applyCalculationSelectOptionsForTab(calculationSelect, isDiamond);
            } else if (typeof window.applyCalculationSelectOptionsForRow === 'function') {
                window.applyCalculationSelectOptionsForRow(calculationSelect, row, isDiamond);
            }
        }
        if (typeof window.auragoldPopulateModalSpecSelectsForRow === 'function') {
            window.auragoldPopulateModalSpecSelectsForRow(row);
        }
        if (typeof window.addModalRowCalculationListeners === 'function') {
            window.addModalRowCalculationListeners(row);
        }
        if (typeof window.calculateModalRowNetWeight === 'function') {
            window.calculateModalRowNetWeight(row);
        }
        applyProductModalColumnVisibilityForTab(currentMetalId || '');
        if (typeof window.bindProductModalRowSearchHandlers === 'function') {
            window.bindProductModalRowSearchHandlers(row);
        }
    }
    window.addEmptyProductRow = addEmptyProductRow;

    window.deleteProductRowFromModal = function (icon) {
        var row = icon && icon.closest ? icon.closest('tr') : null;
        if (row) row.remove();
    };
    window.editProductRowInTable = function () { /* row is editable inline */ };

    function modalRowToBomItem(md) {
        if (!md) return null;
        var productName = String(md.product_name || '').trim();
        if (!productName && md.product_id && window.products) {
            (window.products || []).forEach(function (p) {
                if (String(p.id) === String(md.product_id)) productName = String(p.name || '').trim();
            });
        }
        if (productName && md.metal_name) {
            productName = productName + ' - ' + md.metal_name;
        } else if (!productName && md.metal_name) {
            productName = String(md.metal_name).trim();
        }
        var variants = md.category || md.product_category_id || md.carat || '';
        if (md.metal_id && window.metals) {
            var mid = String(md.metal_id);
            (window.metals || []).forEach(function (m) {
                if (String(m.id) === mid) variants = (variants ? variants + ' | ' : '') + (m.display_name || m.name || '');
            });
        }
        return {
            variants: variants,
            barcode: md.barcode || '',
            description: productName,
            quantity: md.quantity != null && md.quantity !== '' ? String(md.quantity) : '1',
            gross_wt: md.gross_wt != null ? String(md.gross_wt) : '',
            final_wt: md.final_wt != null ? String(md.final_wt) : (md.net_wt != null ? String(md.net_wt) : ''),
            net_wt: md.net_wt != null ? String(md.net_wt) : '',
            pure_wt: md.pure_wt != null ? String(md.pure_wt) : '',
            making: md.making_amount != null ? String(md.making_amount) : (md.net_amt_tax != null ? String(md.net_amt_tax) : ''),
            design_no: md.design_no || '',
            tax: md.tax != null ? String(md.tax) : '',
            _modal: md
        };
    }

    function addModalRowsToBom() {
        var allProductRows = document.querySelectorAll('#productListBody .product-row');
        if (!allProductRows.length) {
            alert('No products to add. Click Add Product first.');
            return;
        }
        var productRows = Array.prototype.filter.call(allProductRows, function (row) {
            return row && row.style.display !== 'none';
        });
        if (!productRows.length) {
            alert('No products in the current metal tab.');
            return;
        }
        if (typeof window.calculateModalRowNetWeight === 'function') {
            productRows.forEach(function (r) { window.calculateModalRowNetWeight(r); });
        }
        var items = [];
        productRows.forEach(function (row) {
            if (typeof window.getModalRowDataFromRow !== 'function') return;
            var md = window.getModalRowDataFromRow(row, true);
            var bom = modalRowToBomItem(md);
            if (bom) items.push(bom);
        });
        if (!items.length) {
            alert('Could not read product rows.');
            return;
        }
        if (bomEditIndex >= 0
            && window.JCC_BOM_BRIDGE
            && typeof window.JCC_BOM_BRIDGE.replaceBomItemsAt === 'function') {
            window.JCC_BOM_BRIDGE.replaceBomItemsAt(bomEditIndex, items);
        } else if (window.JCC_BOM_BRIDGE && typeof window.JCC_BOM_BRIDGE.appendBomItems === 'function') {
            window.JCC_BOM_BRIDGE.appendBomItems(items);
        }
        hideProductModal();
    }

    function bindModalUi() {
        var addProductBtn = document.getElementById('addProductRowBtn');
        if (addProductBtn) {
            addProductBtn.addEventListener('click', function (e) {
                e.preventDefault();
                addEmptyProductRow();
            });
        }
        var modalAddBtn = document.getElementById('modalAddBtn');
        if (modalAddBtn) {
            modalAddBtn.addEventListener('click', function (e) {
                e.preventDefault();
                addModalRowsToBom();
            });
        }
        var modalAddBtn2 = document.getElementById('modalAddBtn2');
        if (modalAddBtn2) {
            modalAddBtn2.addEventListener('click', function (e) {
                e.preventDefault();
                addModalRowsToBom();
            });
        }
        if (typeof jQuery !== 'undefined') {
            jQuery(document).on('shown.bs.modal', '#productSelectionModal', function () {
                if (bomEditIndex < 0) {
                    initCategoryTabs();
                } else if (currentMetalId) {
                    var modalEl = document.getElementById('productSelectionModal');
                    applyTabMetalModalClass(modalEl, currentMetalName);
                    applyProductModalColumnVisibilityForTab(currentMetalId || '');
                    if (bomEditShowAllRows) showAllModalProductRows();
                    else filterProductsByMetal(currentMetalId);
                }
                syncWindowMetalTabState();
                if (typeof window.applyMetalGroupHeaderLabelsToGrids === 'function') {
                    window.applyMetalGroupHeaderLabelsToGrids();
                }
            });
            jQuery(document).on('hidden.bs.modal', '#productSelectionModal', function () {
                resetBomEditMode();
            });
            jQuery(document).on('click', '.add-category-icon, .add-product-category-icon', function (e) {
                e.stopPropagation();
                e.preventDefault();
                jQuery('#categoryCreationModal').modal('show');
                if (typeof window.loadParentCategories === 'function') {
                    window.loadParentCategories();
                }
            });
            jQuery(document).on('click', '.add-product-icon', function (e) {
                if (e.target.closest('th[data-column="product"]')) return;
                e.stopPropagation();
                jQuery('#productCreationModal').modal('show');
            });
        }

        document.addEventListener('click', function (e) {
            var plus = e.target.closest('#productSelectionModal .product-modal-th-inner .feather.icon-plus');
            if (!plus || plus.classList.contains('add-product-icon') ||
                plus.classList.contains('add-category-icon') ||
                plus.classList.contains('add-product-category-icon') ||
                plus.classList.contains('add-location-icon')) return;
            var th = plus.closest('th[data-column]');
            if (!th) return;
            var col = th.getAttribute('data-column');
            if (col !== 'product') return;
            e.stopPropagation();
            e.preventDefault();
            var tbody = document.getElementById('productListBody');
            var row = tbody ? tbody.querySelector('tr.product-row:last-child') : null;
            if (row && typeof window.openProductSearchModal === 'function') {
                window.openProductSearchModal(row);
            }
        }, true);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindModalUi);
    } else {
        bindModalUi();
    }
})();
