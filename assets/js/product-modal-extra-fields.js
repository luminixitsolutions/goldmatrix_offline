/**
 * Dynamic "Extra Fields" column group in product selection modal (after Hallmark).
 * Requires window.AURAGOLD_EXTRA_FIELDS_BY_METAL from the page (metal => field defs).
 */
(function (global) {
    'use strict';

    var GROUP_KEY = 'extra-fields-group';
    var METAL_MAP = null;

    function escapeHtml(s) {
        if (s == null) return '';
        var d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }

    function escapeAttr(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;');
    }

    function getMetalMap() {
        if (METAL_MAP !== null) return METAL_MAP;
        METAL_MAP = (global.AURAGOLD_EXTRA_FIELDS_BY_METAL && typeof global.AURAGOLD_EXTRA_FIELDS_BY_METAL === 'object')
            ? global.AURAGOLD_EXTRA_FIELDS_BY_METAL
            : {};
        return METAL_MAP;
    }

    function colKeyForFieldId(id) {
        return 'extra-field-' + String(id);
    }

    function resolveFieldsForMetal(metalName) {
        if (!metalName) return [];
        var map = getMetalMap();
        if (map[metalName]) {
            return (map[metalName] || []).filter(function (f) { return f && Number(f.status) === 1; });
        }
        var want = String(metalName).toLowerCase();
        var keys = Object.keys(map);
        for (var i = 0; i < keys.length; i++) {
            if (keys[i].toLowerCase() === want) {
                return (map[keys[i]] || []).filter(function (f) { return f && Number(f.status) === 1; });
            }
        }
        return [];
    }

    function readRowExtraFieldValues(row) {
        var out = {};
        if (!row || !row.querySelectorAll) return out;
        row.querySelectorAll('td[data-extra-field-id]').forEach(function (td) {
            var id = td.getAttribute('data-extra-field-id');
            var inp = td.querySelector('input, select, textarea');
            if (id && inp) out[id] = inp.value || '';
        });
        return out;
    }

    function removeExtraFieldColumnsFromTable(table) {
        if (!table) return;
        table.querySelectorAll('thead tr:first-child th[data-group="' + GROUP_KEY + '"]').forEach(function (el) {
            el.parentNode.removeChild(el);
        });
        table.querySelectorAll('[data-column^="extra-field-"]').forEach(function (el) {
            el.parentNode.removeChild(el);
        });
    }

    function removeExtraFieldSettingsItems() {
        var dropdown = document.getElementById('modalTableSettingsDropdown')
            || document.getElementById('modalTableSettingsDropdownModal');
        if (!dropdown) return;
        dropdown.querySelectorAll('.table-settings-item[data-extra-field-settings="1"]').forEach(function (el) {
            el.parentNode.removeChild(el);
        });
        var block = dropdown.querySelector('.table-settings-group-block[data-group="' + GROUP_KEY + '"]');
        if (block) block.parentNode.removeChild(block);
    }

    function clearExtraFieldsFromColumnGroups() {
        var groups = global.PRODUCT_MODAL_COLUMN_GROUPS || {};
        if (groups[GROUP_KEY]) {
            delete groups[GROUP_KEY];
            global.PRODUCT_MODAL_COLUMN_GROUPS = groups;
        }
    }

    function buildGroupHeaderTh(groupDragHtml) {
        var th = document.createElement('th');
        th.setAttribute('colspan', '1');
        th.setAttribute('data-group', GROUP_KEY);
        th.className = 'product-modal-group-header-th';
        th.style.textAlign = 'center';
        th.innerHTML = (groupDragHtml || '') + '<span class="product-modal-group-label">Extra Fields</span>';
        return th;
    }

    function refreshGroupSortableIfReady() {
        if (typeof global.refreshProductModalGroupSortable === 'function') {
            global.refreshProductModalGroupSortable();
        }
    }

    function applyProductModalGroupColumnOrder(newGroupOrder) {
        var table = document.getElementById('productListTable');
        if (!table || !newGroupOrder || !newGroupOrder.length) return;
        var headerRow2 = table.querySelector('thead tr:nth-child(2)');
        if (!headerRow2) return;
        var columnGroups = global.PRODUCT_MODAL_COLUMN_GROUPS || {};
        var groupByCol = {};
        Object.keys(columnGroups).forEach(function (gk) {
            (columnGroups[gk] || []).forEach(function (c) { groupByCol[c] = gk; });
        });
        var newColumnOrder = [];
        newGroupOrder.forEach(function (gk) {
            headerRow2.querySelectorAll('th[data-column]').forEach(function (th) {
                var k = th.getAttribute('data-column');
                if (k && k !== 'actions' && groupByCol[k] === gk && newColumnOrder.indexOf(k) === -1) {
                    newColumnOrder.push(k);
                }
            });
        });
        headerRow2.querySelectorAll('th[data-column]').forEach(function (th) {
            var k = th.getAttribute('data-column');
            if (k && k !== 'actions' && newColumnOrder.indexOf(k) === -1) {
                newColumnOrder.push(k);
            }
        });
        var headerMap = {};
        Array.from(headerRow2.children).forEach(function (th) {
            var k = th.getAttribute('data-column');
            if (k) headerMap[k] = th;
        });
        newColumnOrder.forEach(function (k) {
            if (headerMap[k]) headerRow2.appendChild(headerMap[k]);
        });
        table.querySelectorAll('tbody tr.product-row').forEach(function (row) {
            if (typeof global.reorderModalRowCellsToMatchHeader === 'function') {
                global.reorderModalRowCellsToMatchHeader(row, table);
            }
        });
        if (typeof global.updateGroupHeaderVisibility === 'function') {
            global.updateGroupHeaderVisibility();
        }
        if (typeof global.syncProductModalColumnLayoutAfterToggle === 'function') {
            global.syncProductModalColumnLayoutAfterToggle();
        }
    }

    function installFallbackGroupSortableRefresh() {
        if (typeof global.refreshProductModalGroupSortable === 'function') return;
        global.auragoldApplyProductModalGroupColumnOrder = applyProductModalGroupColumnOrder;
        global.refreshProductModalGroupSortable = function auragoldFallbackRefreshProductModalGroupSortable() {
            var table = document.getElementById('productListTable');
            if (!table || typeof Sortable === 'undefined') return;
            if (typeof global.stampProductModalDataGroupOnCells === 'function') {
                global.stampProductModalDataGroupOnCells();
            }
            var headerRow1 = table.querySelector('thead tr:first-child');
            if (!headerRow1) return;
            if (headerRow1._auragoldExtraFieldsGroupSortable) {
                try { headerRow1._auragoldExtraFieldsGroupSortable.destroy(); } catch (e) {}
                headerRow1._auragoldExtraFieldsGroupSortable = null;
            }
            headerRow1._auragoldExtraFieldsGroupSortable = new Sortable(headerRow1, {
                animation: 150,
                forceFallback: true,
                fallbackOnBody: true,
                draggable: 'th[data-group]:not([data-group-locked])',
                handle: '.product-modal-group-drag-handle',
                filter: 'input,button,select,textarea,a,.add-category-icon,.add-product-category-icon,.add-product-icon,.add-location-icon',
                preventOnFilter: true,
                ghostClass: 'product-modal-group-sortable-ghost',
                dragClass: 'product-modal-group-sortable-drag-chosen',
                onEnd: function () {
                    var order = [];
                    headerRow1.querySelectorAll('th[data-group]').forEach(function (cell) {
                        var g = cell.getAttribute('data-group');
                        if (g) order.push(g);
                    });
                    applyProductModalGroupColumnOrder(order);
                    global.refreshProductModalGroupSortable();
                }
            });
        };
    }

    function getActiveMetalNameFromModal() {
        var tab = document.querySelector('.product-category-tabs .category-tab-btn.active')
            || document.querySelector('#productSelectionModal .category-tab-btn.active')
            || document.querySelector('.category-tab-btn.active');
        return tab ? (tab.getAttribute('data-metal-name') || '') : '';
    }

    function initExtraFieldsAutoWire() {
        if (global._auragoldExtraFieldsAutoWired) return;
        global._auragoldExtraFieldsAutoWired = true;
        installFallbackGroupSortableRefresh();

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.category-tab-btn');
            if (!btn) return;
            setTimeout(function () {
                syncProductModalExtraFields(btn.getAttribute('data-metal-name') || '');
            }, 0);
        }, true);

        function syncFromActiveTab() {
            syncProductModalExtraFields(getActiveMetalNameFromModal());
        }

        var modal = document.getElementById('productSelectionModal');
        if (modal) {
            modal.addEventListener('shown.bs.modal', function () {
                setTimeout(syncFromActiveTab, 60);
            });
        }
        if (typeof jQuery !== 'undefined') {
            jQuery(document).on('shown.bs.modal', '#productSelectionModal', function () {
                setTimeout(syncFromActiveTab, 60);
            });
        }

        var tbody = document.getElementById('productListBody');
        if (tbody && typeof MutationObserver !== 'undefined') {
            new MutationObserver(function (mutations) {
                mutations.forEach(function (m) {
                    for (var i = 0; i < m.addedNodes.length; i++) {
                        var node = m.addedNodes[i];
                        if (node.nodeType === 1 && node.classList && node.classList.contains('product-row')) {
                            appendExtraFieldCellsToModalRow(node, getActiveMetalNameFromModal());
                        }
                    }
                });
            }).observe(tbody, { childList: true });
        }

        if (modal && modal.classList.contains('show')) {
            syncFromActiveTab();
        }
        if (document.getElementById('productListTablePage')) {
            setTimeout(syncFromActiveTab, 80);
        }
    }

    function buildColumnHeaderTh(field, colDragHtml) {
        var col = colKeyForFieldId(field.id);
        var th = document.createElement('th');
        th.setAttribute('data-column', col);
        th.setAttribute('data-extra-field-id', String(field.id));
        th.setAttribute('data-group', GROUP_KEY);
        th.className = 'product-modal-th-cell';
        th.style.minWidth = '120px';
        th.title = field.display_name || '';
        th.innerHTML = '<div class="product-modal-th-inner">' + (colDragHtml || '')
            + '<span class="product-modal-th-label">' + escapeHtml(field.display_name || '') + '</span></div>';
        return th;
    }

    function buildCellTd(field, value) {
        var col = colKeyForFieldId(field.id);
        var td = document.createElement('td');
        td.setAttribute('data-column', col);
        td.setAttribute('data-extra-field-id', String(field.id));
        td.setAttribute('data-group', GROUP_KEY);
        var style = 'width: 120px; font-size: 0.7rem;';
        if (field.field_type === 'dropdown') {
            var sel = document.createElement('select');
            sel.className = 'form-control form-control-sm';
            sel.style.cssText = style;
            var empty = document.createElement('option');
            empty.value = '';
            empty.textContent = 'Select';
            sel.appendChild(empty);
            (field.dropdown_options || []).forEach(function (opt) {
                var o = document.createElement('option');
                o.value = opt;
                o.textContent = opt;
                if (value != null && String(value) === String(opt)) o.selected = true;
                sel.appendChild(o);
            });
            td.appendChild(sel);
        } else {
            var inp = document.createElement('input');
            inp.type = 'text';
            inp.className = 'form-control form-control-sm';
            inp.style.cssText = style;
            inp.value = value != null ? String(value) : '';
            td.appendChild(inp);
        }
        return td;
    }

    function upsertSettingsCheckboxes(fields) {
        var dropdown = document.getElementById('modalTableSettingsDropdown')
            || document.getElementById('modalTableSettingsDropdownModal');
        if (!dropdown || !fields.length) return;

        removeExtraFieldSettingsItems();

        var listHost = dropdown.querySelector('.table-settings-dropdown-body') || dropdown;
        var netAmtItem = dropdown.querySelector('input[data-column="net-amt-tax"]');
        var insertBefore = netAmtItem ? netAmtItem.closest('.table-settings-item') : null;

        var block = document.createElement('div');
        block.className = 'table-settings-group-block';
        block.setAttribute('data-group', GROUP_KEY);

        var groupRow = document.createElement('div');
        groupRow.className = 'table-settings-item table-settings-group-item';
        groupRow.setAttribute('data-group', GROUP_KEY);
        groupRow.setAttribute('data-extra-field-settings', '1');
        var groupId = 'modal-col-group-' + GROUP_KEY;
        groupRow.innerHTML = '<input type="checkbox" id="' + groupId + '" data-group="' + GROUP_KEY + '" checked>'
            + '<label for="' + groupId + '">Extra Fields</label>';
        block.appendChild(groupRow);

        fields.forEach(function (field) {
            var col = colKeyForFieldId(field.id);
            var item = document.createElement('div');
            item.className = 'table-settings-item table-settings-sub-column';
            item.setAttribute('data-group', GROUP_KEY);
            item.setAttribute('data-extra-field-settings', '1');
            var cid = 'modal-col-' + col;
            item.innerHTML = '<input type="checkbox" id="' + cid + '" data-column="' + col + '" checked>'
                + '<label for="' + cid + '">' + escapeHtml(field.display_name || col) + '</label>';
            block.appendChild(item);
        });

        if (insertBefore && insertBefore.parentNode) {
            insertBefore.parentNode.insertBefore(block, insertBefore);
        } else {
            listHost.appendChild(block);
        }

        if (typeof global.syncProductModalColumnGroupMasterCheckboxes === 'function') {
            global.syncProductModalColumnGroupMasterCheckboxes();
        }
    }

    function syncTableExtraFields(table, metalName) {
        if (!table) return;

        var savedByRow = [];
        table.querySelectorAll('tbody tr.product-row').forEach(function (row) {
            savedByRow.push({ row: row, values: readRowExtraFieldValues(row) });
        });

        removeExtraFieldColumnsFromTable(table);
        clearExtraFieldsFromColumnGroups();

        var fields = resolveFieldsForMetal(metalName);
        if (!fields.length) {
            if (typeof global.updateGroupHeaderVisibility === 'function') global.updateGroupHeaderVisibility();
            refreshGroupSortableIfReady();
            return;
        }

        var groupDrag = '<span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><i class="feather icon-move"></i></span>';
        var colDrag = '<span class="product-modal-col-drag-handle" title="Drag to reorder within this group (use the move icon on the group title row above to move the whole group)."><i class="feather icon-move"></i></span>';
        var row1 = table.querySelector('thead tr:first-child');
        var row2 = table.querySelector('thead tr:nth-child(2)');
        var hallmarkGroup = row1 ? row1.querySelector('th[data-group="hallmark"]') : null;
        var netReverseGroup = row1 ? row1.querySelector('th[data-group="net-reverse"]') : null;
        var hallmarkRateTh = row2 ? row2.querySelector('th[data-column="hallmark-rate"]') : null;

        if (!row1 || !row2 || !hallmarkGroup || !netReverseGroup || !hallmarkRateTh) {
            return;
        }

        var groupTh = buildGroupHeaderTh(groupDrag);
        groupTh.setAttribute('colspan', String(fields.length));
        netReverseGroup.parentNode.insertBefore(groupTh, netReverseGroup);

        var insertAfterTh = hallmarkRateTh;
        fields.forEach(function (field) {
            var th = buildColumnHeaderTh(field, colDrag);
            insertAfterTh.parentNode.insertBefore(th, insertAfterTh.nextSibling);
            insertAfterTh = th;
        });

        savedByRow.forEach(function (entry) {
            var anchor = entry.row.querySelector('td[data-column="hallmark-rate"]');
            if (!anchor) return;
            var insertAfterTd = anchor;
            fields.forEach(function (field) {
                var val = entry.values[String(field.id)] != null ? entry.values[String(field.id)] : '';
                var td = buildCellTd(field, val);
                insertAfterTd.parentNode.insertBefore(td, insertAfterTd.nextSibling);
                insertAfterTd = td;
            });
        });

        var groups = global.PRODUCT_MODAL_COLUMN_GROUPS || {};
        groups[GROUP_KEY] = fields.map(function (f) { return colKeyForFieldId(f.id); });
        global.PRODUCT_MODAL_COLUMN_GROUPS = groups;

        upsertSettingsCheckboxes(fields);

        if (typeof global.stampProductModalDataGroupOnCells === 'function') {
            global.stampProductModalDataGroupOnCells();
        }
        if (typeof global.updateGroupHeaderVisibility === 'function') {
            global.updateGroupHeaderVisibility();
        }
        savedByRow.forEach(function (entry) {
            if (typeof global.reorderModalRowCellsToMatchHeader === 'function') {
                global.reorderModalRowCellsToMatchHeader(entry.row, table);
            }
        });
        refreshGroupSortableIfReady();
    }

    function syncProductModalExtraFields(metalName) {
        var tables = (typeof global.getProductSelectionTables === 'function')
            ? global.getProductSelectionTables()
            : [document.getElementById('productListTable')].filter(Boolean);
        removeExtraFieldSettingsItems();
        tables.forEach(function (table) {
            syncTableExtraFields(table, metalName);
        });
        if (typeof global.applyProductModalColumnVisibilityForTab === 'function') {
            setTimeout(function () {
                var tab = document.querySelector('.product-category-tabs .category-tab-btn.active')
                    || document.querySelector('.category-tab-btn.active');
                var tabKey = tab ? (tab.getAttribute('data-metal-id') || '') : '';
                if (tabKey === '' && typeof global.sjCurrentMetalId !== 'undefined' && global.sjCurrentMetalId) {
                    tabKey = String(global.sjCurrentMetalId);
                }
                global.applyProductModalColumnVisibilityForTab(tabKey || 'main');
            }, 0);
        }
    }

    function appendExtraFieldCellsToModalRow(row, metalName, valuesMap) {
        if (!row) return;
        var table = row.closest ? row.closest('table') : null;
        var fields = resolveFieldsForMetal(metalName);
        if (!fields.length) return;
        var anchor = row.querySelector('td[data-column="hallmark-rate"]');
        if (!anchor) return;
        var insertAfter = anchor;
        fields.forEach(function (field) {
            if (row.querySelector('td[data-column="' + colKeyForFieldId(field.id) + '"]')) return;
            var val = (valuesMap && valuesMap[String(field.id)] != null) ? valuesMap[String(field.id)] : '';
            var td = buildCellTd(field, val);
            insertAfter.parentNode.insertBefore(td, insertAfter.nextSibling);
            insertAfter = td;
        });
        if (table && typeof global.reorderModalRowCellsToMatchHeader === 'function') {
            global.reorderModalRowCellsToMatchHeader(row, table);
        }
        if (typeof global.stampProductModalDataGroupOnCells === 'function') {
            global.stampProductModalDataGroupOnCells(row);
        }
    }

    function collectExtraFieldsFromRow(row) {
        var out = {};
        if (!row) return out;
        row.querySelectorAll('td[data-extra-field-id]').forEach(function (td) {
            var id = td.getAttribute('data-extra-field-id');
            var inp = td.querySelector('input, select, textarea');
            if (!id || !inp) return;
            out[id] = inp.value || '';
        });
        return out;
    }

    function extraFieldsFromModalItemData(d) {
        if (d && d.extra_fields && typeof d.extra_fields === 'object') {
            return d.extra_fields;
        }
        return {};
    }

    function extraFieldsFromProductTableRow(row) {
        if (!row) return {};
        try {
            var j = row.getAttribute('data-group-items');
            if (j) {
                var arr = JSON.parse(j);
                if (Array.isArray(arr)) {
                    for (var i = 0; i < arr.length; i++) {
                        var ef = extraFieldsFromModalItemData(arr[i]);
                        if (ef && Object.keys(ef).length) return ef;
                    }
                }
            }
        } catch (e) {}
        return collectExtraFieldsFromRow(row);
    }

    function applyExtraFieldsToModalRow(row, valuesMap, metalName) {
        if (!row || !valuesMap || typeof valuesMap !== 'object') return;
        var metal = metalName || getActiveMetalNameFromModal();
        appendExtraFieldCellsToModalRow(row, metal, valuesMap);
        Object.keys(valuesMap).forEach(function (fieldId) {
            var td = row.querySelector('td[data-extra-field-id="' + String(fieldId) + '"]');
            if (!td) return;
            var inp = td.querySelector('input, select, textarea');
            if (inp) inp.value = valuesMap[fieldId] != null ? String(valuesMap[fieldId]) : '';
        });
    }

    function enrichVoucherItemsExtraFields(items) {
        if (!items || !items.length) return items || [];
        var rows = document.querySelectorAll('#productTableBody tr:not(.no-drag)');
        var itemIdx = 0;
        for (var ri = 0; ri < rows.length && itemIdx < items.length; ri++) {
            var row = rows[ri];
            var groupJson = row.getAttribute('data-group-items');
            if (groupJson) {
                try {
                    var groupItems = JSON.parse(groupJson);
                    if (Array.isArray(groupItems) && groupItems.length) {
                        for (var gi = 0; gi < groupItems.length && itemIdx < items.length; gi++) {
                            items[itemIdx].extra_fields = extraFieldsFromModalItemData(groupItems[gi]);
                            itemIdx++;
                        }
                        continue;
                    }
                } catch (e) {}
            }
            items[itemIdx].extra_fields = extraFieldsFromProductTableRow(row);
            itemIdx++;
        }
        for (var j = 0; j < items.length; j++) {
            if (!items[j].extra_fields || typeof items[j].extra_fields !== 'object') {
                items[j].extra_fields = {};
            }
        }
        return items;
    }

    function installVoucherSaveExtraFieldsHooks() {
        if (global._auragoldExtraFieldsSaveHooked) return;
        global._auragoldExtraFieldsSaveHooked = true;

        if (typeof jQuery !== 'undefined' && jQuery.ajaxPrefilter) {
            jQuery.ajaxPrefilter(function (options) {
                var url = (options && options.url) ? String(options.url) : '';
                if (url.indexOf('ajax/save-') === -1 || !options.data) return;
                var data = options.data;
                if (typeof data === 'string') {
                    try { data = JSON.parse(data); } catch (e) { return; }
                }
                if (!data || !data.items) return;
                var items = data.items;
                if (typeof items === 'string') {
                    try { items = JSON.parse(items); } catch (e) { return; }
                }
                if (!Array.isArray(items)) return;
                enrichVoucherItemsExtraFields(items);
                if (typeof options.data.items === 'string') {
                    options.data.items = JSON.stringify(items);
                } else if (options.data && typeof options.data === 'object') {
                    options.data.items = items;
                }
            });
        }
    }

    global.auragoldSyncProductModalExtraFields = syncProductModalExtraFields;
    global.auragoldAppendExtraFieldCellsToModalRow = appendExtraFieldCellsToModalRow;
    global.auragoldCollectExtraFieldsFromRow = collectExtraFieldsFromRow;
    global.auragoldResolveExtraFieldsForMetal = resolveFieldsForMetal;
    global.auragoldExtraFieldsForSaveFromModalItem = extraFieldsFromModalItemData;
    global.auragoldExtraFieldsForSaveFromRow = extraFieldsFromProductTableRow;
    global.auragoldEnrichVoucherItemsExtraFields = enrichVoucherItemsExtraFields;
    global.auragoldApplyExtraFieldsToModalRow = applyExtraFieldsToModalRow;

    if (typeof global.getModalRowDataFromRow === 'function' && !global._auragoldExtraFieldsRowWrap) {
        global._auragoldExtraFieldsRowWrap = true;
        var origGetModalRowData = global.getModalRowDataFromRow;
        global.getModalRowDataFromRow = function (row, forSave) {
            var data = origGetModalRowData(row, forSave);
            if (data && typeof data === 'object') {
                data.extra_fields = collectExtraFieldsFromRow(row);
            }
            return data;
        };
    }

    if (typeof global.applyModalRowDataToSelectionRow === 'function' && !global._auragoldExtraFieldsApplyWrap) {
        global._auragoldExtraFieldsApplyWrap = true;
        var origApplyModalRow = global.applyModalRowDataToSelectionRow;
        global.applyModalRowDataToSelectionRow = function (row, md) {
            origApplyModalRow(row, md);
            if (row && md && md.extra_fields && typeof md.extra_fields === 'object') {
                applyExtraFieldsToModalRow(row, md.extra_fields);
            }
        };
    }

    installVoucherSaveExtraFieldsHooks();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initExtraFieldsAutoWire);
    } else {
        initExtraFieldsAutoWire();
    }
})(typeof window !== 'undefined' ? window : this);
