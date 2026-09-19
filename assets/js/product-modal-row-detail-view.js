/**
 * Product Selection modal — eye icon opens a form view of all row columns with live calculations.
 */
/** Nested overlays (#productSearchModal, row detail) must opt out of Bootstrap focus trap on #productSelectionModal. */
(function () {
    var GUARD_KEY = '__auragoldBootstrapModalFocusGuard';
    if (window.auragoldSuspendBootstrapModalFocusTrap) {
        return;
    }
    window.auragoldSuspendBootstrapModalFocusTrap = function (allowSelector) {
        allowSelector = String(allowSelector || '').trim();
        if (typeof jQuery !== 'undefined') {
            jQuery(document).off('focusin.modal focusin.bs.modal');
        }
        if (window[GUARD_KEY]) {
            document.removeEventListener('focusin', window[GUARD_KEY], true);
        }
        window[GUARD_KEY] = function (e) {
            if (!allowSelector) {
                return;
            }
            var root = document.querySelector(allowSelector);
            if (root && root.contains(e.target)) {
                e.stopImmediatePropagation();
            }
        };
        document.addEventListener('focusin', window[GUARD_KEY], true);
    };
    window.auragoldResumeBootstrapModalFocusTrap = function () {
        if (window[GUARD_KEY]) {
            document.removeEventListener('focusin', window[GUARD_KEY], true);
            window[GUARD_KEY] = null;
        }
        if (typeof jQuery === 'undefined') {
            return;
        }
        var productModal = document.getElementById('productSelectionModal');
        if (!productModal) {
            return;
        }
        var isOpen = productModal.classList.contains('show') || productModal.style.display === 'block';
        if (!isOpen) {
            return;
        }
        var inst = jQuery(productModal).data('bs.modal');
        if (inst && typeof inst._enforceFocus === 'function') {
            inst._enforceFocus();
        }
    };
})();

(function () {
    'use strict';

    if (window.__auragoldProductRowDetailViewLoaded) return;
    window.__auragoldProductRowDetailViewLoaded = true;

    var MODAL_ID = 'productRowDetailModal';
    var SKIP_COLUMNS = { checkbox: 1, photo: 1, images: 1, actions: 1 };
    var GROUP_LABELS = {
        'basic-information': 'Basic Information',
        'diamond-group': 'Diamond group',
        'metal-group': 'Metal group',
        'request-final-group': 'Request & Final Wt.',
        'platinum-group': 'Platinum (group)',
        'discount-group': 'Discount (group)',
        'making-group': 'Making (group)',
        'minimum-group': 'Minimum',
        'stone-group': 'Stone group',
        'amounts': 'Amounts',
        'other-charge-group': 'Other Charge (group)',
        'cert-spec-group': 'Certificate & spec',
        'hallmark': 'Hallmark',
        'net-reverse': 'Net Amt+Tax / Reverse',
        'extra-fields-group': 'Extra Fields'
    };

    function escapeHtml(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function getProductRowFromTrigger(el) {
        if (!el) return null;
        var row = el.closest ? el.closest('tr.product-row') : null;
        return row && row.classList.contains('product-row') ? row : null;
    }

    function getColumnGroupId(colKey) {
        if (!colKey) return 'other';
        if (colKey.indexOf('extra-field-') === 0) return 'extra-fields-group';
        var groups = window.PRODUCT_MODAL_COLUMN_GROUPS || {};
        var keys = Object.keys(groups);
        for (var i = 0; i < keys.length; i++) {
            var gid = keys[i];
            var cols = groups[gid];
            if (cols && cols.indexOf(colKey) !== -1) return gid;
        }
        return 'other';
    }

    function getColumnLabel(table, colKey, cell) {
        if (table) {
            var th = table.querySelector('thead th[data-column="' + colKey + '"]');
            if (th) {
                var label = (th.textContent || '').replace(/\s+/g, ' ').trim();
                if (label) return label;
            }
        }
        if (cell && cell.getAttribute('data-extra-field-id')) {
            var efTh = table && table.querySelector('thead th[data-extra-field-id="' + cell.getAttribute('data-extra-field-id') + '"]');
            if (efTh) {
                var efLabel = (efTh.textContent || '').replace(/\s+/g, ' ').trim();
                if (efLabel) return efLabel;
            }
        }
        var diamond = window.DIAMOND_TAB_HEADER_LABELS && window.DIAMOND_TAB_HEADER_LABELS[colKey];
        if (diamond) return diamond;
        var metal = window.METAL_GROUP_HEADER_LABELS && window.METAL_GROUP_HEADER_LABELS[colKey];
        if (metal) return metal;
        return colKey.replace(/-/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
    }

    function getRowDetailColumnKeys(row, table) {
        var order = typeof window.getProductModalTheadDataColumnOrder === 'function'
            ? window.getProductModalTheadDataColumnOrder(table)
            : [];
        var cols = [];
        var seen = {};
        order.forEach(function (k) {
            if (seen[k]) return;
            if (!isColumnVisibleForDetailModal(table, row, k)) return;
            if (row.querySelector('td[data-column="' + k + '"]')) {
                cols.push(k);
                seen[k] = true;
            }
        });
        row.querySelectorAll('td[data-column]').forEach(function (td) {
            var k = td.getAttribute('data-column');
            if (!k || seen[k]) return;
            if (!isColumnVisibleForDetailModal(table, row, k)) return;
            cols.push(k);
            seen[k] = true;
        });
        return cols;
    }

    /** Columns that were readonly in the table when the detail modal opened. */
    var readonlyColsAtOpen = {};
    var detailRecalcTimer = null;
    var activeDetailColKey = null;
    var detailFormSnapshot = {};

    function isMobileDetailView() {
        return window.matchMedia('(max-width: 767.98px)').matches;
    }

    /** Match product modal table visibility (Gold tab hides diamond-group + category). */
    function isColumnVisibleForDetailModal(table, row, colKey) {
        if (SKIP_COLUMNS[colKey]) return false;
        var cell = row.querySelector('td[data-column="' + colKey + '"]');
        if (!cell) return false;
        if (cell.classList.contains('hidden')) return false;
        if (table) {
            var th = table.querySelector('thead th[data-column="' + colKey + '"]');
            if (th && th.classList.contains('hidden')) return false;
        }
        if (typeof window.isDiamondTabActive === 'function' && !window.isDiamondTabActive()) {
            if (colKey === 'category') return false;
            var dg = typeof window.getDiamondGroupColumnKeys === 'function'
                ? window.getDiamondGroupColumnKeys()
                : [];
            if (dg.indexOf(colKey) !== -1) return false;
        }
        return true;
    }

    function isCellReadonly(cell) {
        var input = cell ? cell.querySelector('input, textarea, select') : null;
        return !!(input && (input.readOnly || input.disabled));
    }

    function getProductDisplayName(row, cell) {
        var cellEl = cell || (row ? row.querySelector('td[data-column="product"]') : null);
        if (!cellEl && row) {
            cellEl = row.querySelector('[data-column="product"]');
        }
        if (cellEl) {
            var input = cellEl.querySelector('input:not([type="checkbox"])');
            if (input) {
                var iv = String(input.value || '').trim();
                if (iv) return iv;
            }
            var link = cellEl.querySelector('a');
            if (link) {
                var lv = String(link.textContent || '').trim();
                if (lv) return lv;
            }
            var tv = String(cellEl.textContent || '').trim();
            if (tv && tv !== '—' && tv !== '-') return tv;
        }
        if (row) {
            var stored = String(row.getAttribute('data-product-name') || '').trim();
            if (stored) return stored;
        }
        return '';
    }

    function getDetailCellValue(cell, colKey, row) {
        var v = readCellValue(cell);
        if (v === '—' || v === '-') v = '';
        if (!v && colKey === 'product') {
            v = getProductDisplayName(row, cell);
        }
        if (!v && colKey === 'barcode' && row) {
            v = String(row.getAttribute('data-barcode') || '').trim();
        }
        return v;
    }

    function ensureRowCellEditable(cell, colKey, value) {
        if (!cell) return null;
        var select = cell.querySelector('select');
        if (select) {
            select.disabled = false;
            if (value != null) select.value = String(value);
            return select;
        }
        var input = cell.querySelector('input:not([type="checkbox"]), textarea');
        if (input) {
            input.readOnly = false;
            input.disabled = false;
            input.removeAttribute('readonly');
            if (value != null) input.value = String(value);
            return input;
        }
        input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control form-control-sm';
        input.style.fontSize = '0.7rem';
        input.value = value != null ? String(value) : readCellValue(cell);
        cell.innerHTML = '';
        cell.appendChild(input);
        return input;
    }

    function ensureRowCellInput(cell, colKey, value) {
        return ensureRowCellEditable(cell, colKey, value);
    }

    function readCellValue(cell) {
        if (!cell) return '';
        var select = cell.querySelector('select');
        if (select) return select.value;
        var input = cell.querySelector('input, textarea');
        if (input) {
            if (input.type === 'checkbox') return input.checked ? '1' : '0';
            return input.value;
        }
        return (cell.textContent || '').trim();
    }

    function buildFormControl(cell, colKey, row) {
        var select = cell.querySelector('select');
        if (select) {
            var sel = document.createElement('select');
            sel.className = 'form-control form-control-sm product-row-detail-modal__input';
            sel.setAttribute('data-column', colKey);
            sel.disabled = false;
            Array.prototype.forEach.call(select.options, function (opt) {
                var o = document.createElement('option');
                o.value = opt.value;
                o.textContent = opt.textContent;
                if (opt.selected) o.selected = true;
                sel.appendChild(o);
            });
            return sel;
        }
        var srcInput = cell.querySelector('input, textarea');
        if (srcInput) {
            if (srcInput.type === 'checkbox') {
                var wrap = document.createElement('div');
                wrap.className = 'custom-control custom-checkbox mt-1';
                var cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.className = 'custom-control-input';
                cb.id = 'prd-detail-' + colKey.replace(/[^a-z0-9_-]/gi, '_');
                cb.checked = !!srcInput.checked;
                cb.setAttribute('data-column', colKey);
                var lbl = document.createElement('label');
                lbl.className = 'custom-control-label';
                lbl.setAttribute('for', cb.id);
                lbl.textContent = 'Yes';
                wrap.appendChild(cb);
                wrap.appendChild(lbl);
                return wrap;
            }
        }
        var inp = document.createElement('input');
        inp.type = 'text';
        inp.className = 'form-control form-control-sm product-row-detail-modal__input';
        inp.value = getDetailCellValue(cell, colKey, row);
        inp.setAttribute('data-column', colKey);
        inp.readOnly = false;
        inp.disabled = false;
        inp.removeAttribute('readonly');
        inp.setAttribute('autocomplete', 'off');
        if (colKey === 'product') {
            var productLabel = getProductDisplayName(row, cell);
            inp.value = productLabel;
            inp.readOnly = true;
            inp.style.cursor = 'pointer';
            inp.classList.add('product-row-detail-modal__input--picker');
            if (!productLabel) {
                inp.setAttribute('placeholder', 'Tap to select product');
            }
        }
        return inp;
    }

    var PRODUCT_PICKER_COLUMNS = { product: 1 };

    function bindProductPickerFields(row, form) {
        if (!row || !form) return;
        Object.keys(PRODUCT_PICKER_COLUMNS).forEach(function (colKey) {
            var ctrl = form.querySelector('[data-column="' + colKey + '"]');
            if (!ctrl || ctrl.dataset.pickerBound === '1') return;
            ctrl.dataset.pickerBound = '1';
            ctrl.readOnly = true;
            ctrl.style.cursor = 'pointer';
            ctrl.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (typeof window.openProductSearchModal === 'function') {
                    window.openProductSearchModal(row);
                }
            });
            ctrl.addEventListener('focus', function (e) {
                e.preventDefault();
                ctrl.blur();
            });
        });
    }

    function getFormControlFromWrap(wrap) {
        if (!wrap) return null;
        if (wrap.matches('input, select, textarea')) return wrap;
        return wrap.querySelector('input[data-column], select[data-column], textarea[data-column]');
    }

    function recalcProductRow(row) {
        if (!row) return;
        row.removeAttribute('data-preserve-line-amounts');
        if (typeof window.calculateModalRowNetWeight === 'function') {
            window.calculateModalRowNetWeight(row);
        } else if (typeof window.calculateRowAmounts === 'function') {
            window.calculateRowAmounts(row);
        }
    }

    function shouldRecalcAfterDetailSync(colKey) {
        if (colKey === 'barcode') return false;
        if (readonlyColsAtOpen[colKey]) return false;
        return true;
    }

    function syncFormControlToRow(row, colKey, control, opts) {
        opts = opts || {};
        if (PRODUCT_PICKER_COLUMNS[colKey]) return;
        var cell = row.querySelector('td[data-column="' + colKey + '"]');
        if (!cell || !control) return;
        var code = String(control.value || '').trim();

        row.setAttribute('data-detail-modal-sync', '1');
        try {
            if (control.type === 'checkbox') {
                var cellCb = cell.querySelector('input[type="checkbox"]');
                if (cellCb) {
                    cellCb.checked = control.checked;
                    cellCb.dispatchEvent(new Event('change', { bubbles: true }));
                }
                return;
            }
            if (control.tagName === 'SELECT') {
                var cellSelect = ensureRowCellEditable(cell, colKey, control.value);
                if (cellSelect && !opts.skipRecalc) {
                    cellSelect.dispatchEvent(new Event('change', { bubbles: true }));
                }
            } else if (colKey === 'barcode') {
                ensureRowCellEditable(cell, colKey, code);
                if (code) row.setAttribute('data-barcode', code);
                else row.removeAttribute('data-barcode');
            } else {
                ensureRowCellEditable(cell, colKey, control.value);
            }
            if (!opts.skipRecalc && shouldRecalcAfterDetailSync(colKey)) {
                recalcProductRow(row);
            }
        } finally {
            row.removeAttribute('data-detail-modal-sync');
        }
    }

    function refreshFormFromRow(row, formEl, skipColKey) {
        if (!row || !formEl) return;
        formEl.querySelectorAll('[data-column]').forEach(function (ctrl) {
            var col = ctrl.getAttribute('data-column');
            if (!col || (skipColKey && col === skipColKey)) return;
            var cell = row.querySelector('td[data-column="' + col + '"]');
            if (!cell) return;
            var cellSelect = cell.querySelector('select');
            var cellInput = cell.querySelector('input, textarea');
            if (ctrl.type === 'checkbox') {
                if (cellInput && cellInput.type === 'checkbox') ctrl.checked = cellInput.checked;
            } else if (cellSelect && ctrl.tagName === 'SELECT') {
                ctrl.value = cellSelect.value;
            } else if (col === 'product') {
                var productLabel = getProductDisplayName(row, cell);
                ctrl.value = productLabel;
                if (productLabel) {
                    ctrl.removeAttribute('placeholder');
                } else if (!ctrl.getAttribute('placeholder')) {
                    ctrl.setAttribute('placeholder', 'Tap to select product');
                }
            } else if (cellInput) {
                ctrl.value = cellInput.value;
            } else {
                ctrl.value = readCellValue(cell);
            }
        });
    }

    function runDetailModalRecalc(row, form, skipColKey) {
        if (!row) return;
        recalcProductRow(row);
        if (form) refreshFormFromRow(row, form, skipColKey || activeDetailColKey);
    }

    function scheduleDetailModalRecalc(row, form) {
        if (detailRecalcTimer) window.clearTimeout(detailRecalcTimer);
        detailRecalcTimer = window.setTimeout(function () {
            detailRecalcTimer = null;
            runDetailModalRecalc(row, form, activeDetailColKey);
        }, 120);
    }

    function flushDetailModalRecalc(row, form) {
        if (detailRecalcTimer) {
            window.clearTimeout(detailRecalcTimer);
            detailRecalcTimer = null;
        }
        runDetailModalRecalc(row, form, activeDetailColKey);
    }

    function syncCategoryTabsToDetailModal() {
        var container = document.getElementById('productRowDetailCategoryTabs');
        var source = document.querySelector('#productSelectionModal .product-category-tabs');
        if (!container || !source) {
            return;
        }
        container.innerHTML = '';
        var clone = source.cloneNode(true);
        clone.querySelectorAll('.category-tab-btn').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var metalId = btn.getAttribute('data-metal-id');
                var orig = source.querySelector('.category-tab-btn[data-metal-id="' + metalId + '"]');
                if (orig) {
                    closeProductRowDetailModal();
                    orig.click();
                }
            });
        });
        container.appendChild(clone);
    }

    function snapshotDetailFormValues(form) {
        detailFormSnapshot = {};
        if (!form) return;
        form.querySelectorAll('[data-column]').forEach(function (ctrl) {
            var col = ctrl.getAttribute('data-column');
            if (!col) return;
            if (ctrl.type === 'checkbox') {
                detailFormSnapshot[col] = ctrl.checked;
            } else {
                detailFormSnapshot[col] = ctrl.value;
            }
        });
    }

    function clearDetailForm() {
        var row = window.__auragoldCurrentDetailRow;
        var form = getActiveDetailForm();
        if (!row || !form) return;
        form.querySelectorAll('[data-column]').forEach(function (ctrl) {
            var col = ctrl.getAttribute('data-column');
            if (!col || !(col in detailFormSnapshot)) return;
            if (ctrl.type === 'checkbox') {
                ctrl.checked = !!detailFormSnapshot[col];
            } else {
                ctrl.value = detailFormSnapshot[col] == null ? '' : String(detailFormSnapshot[col]);
            }
            syncFormControlToRow(row, col, ctrl, { skipRecalc: true });
        });
        flushDetailModalRecalc(row, form);
    }

    function syncAllDetailFormFieldsToRow(row) {
        var form = getActiveDetailForm();
        if (!row || !form) return;
        form.querySelectorAll('[data-column]').forEach(function (ctrl) {
            var col = ctrl.getAttribute('data-column');
            if (!col) return;
            syncFormControlToRow(row, col, ctrl, { skipRecalc: true });
        });
    }

    function ensureModalShell() {
        if (document.getElementById(MODAL_ID)) return;
        var html = ''
            + '<div id="' + MODAL_ID + '" class="product-row-detail-modal" aria-hidden="true">'
            + '  <div class="product-row-detail-modal__dialog" role="dialog" aria-modal="true">'
            + '    <div class="product-row-detail-modal__header">'
            + '      <button type="button" class="product-row-detail-modal__back" aria-label="Back"><i class="feather icon-chevron-left"></i></button>'
            + '      <h5 class="product-row-detail-modal__title">Product Details</h5>'
            + '      <button type="button" class="product-row-detail-modal__close" aria-label="Close">&times;</button>'
            + '    </div>'
            + '    <div class="product-row-detail-modal__tabs" id="productRowDetailCategoryTabs"></div>'
            + '    <div class="product-row-detail-modal__body">'
            + '      <div id="productRowDetailForm" class="product-row-detail-modal__form"></div>'
            + '    </div>'
            + '    <div class="product-row-detail-modal__footer product-row-detail-modal__footer--desktop">'
            + '      <button type="button" class="btn btn-secondary btn-sm" data-action="close">Close</button>'
            + '      <button type="button" class="btn btn-primary btn-sm" data-action="done" style="background:#11294b;border-color:#11294b;">Update &amp; Close</button>'
            + '    </div>'
            + '    <div class="product-row-detail-modal__footer product-row-detail-modal__footer--mobile">'
            + '      <button type="button" class="product-row-detail-modal__btn-clear" data-action="clear">Clear</button>'
            + '      <button type="button" class="product-row-detail-modal__btn-save" data-action="done">Save</button>'
            + '    </div>'
            + '  </div>'
            + '</div>';
        document.body.insertAdjacentHTML('beforeend', html);

        var modal = document.getElementById(MODAL_ID);
        modal.querySelector('.product-row-detail-modal__close').addEventListener('click', closeProductRowDetailModal);
        modal.querySelector('.product-row-detail-modal__back').addEventListener('click', closeProductRowDetailModal);
        modal.querySelector('[data-action="close"]').addEventListener('click', closeProductRowDetailModal);
        modal.querySelector('[data-action="clear"]').addEventListener('click', clearDetailForm);
        modal.querySelectorAll('[data-action="done"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var row = window.__auragoldCurrentDetailRow;
                if (row) {
                    if (detailRecalcTimer) {
                        window.clearTimeout(detailRecalcTimer);
                        detailRecalcTimer = null;
                    }
                    syncAllDetailFormFieldsToRow(row);
                    recalcProductRow(row);
                }
                closeProductRowDetailModal();
            });
        });
        modal.addEventListener('click', function (e) {
            if (e.target === modal && !isMobileDetailView()) closeProductRowDetailModal();
        });
        document.addEventListener('keydown', function (e) {
            var m = document.getElementById(MODAL_ID);
            if (!m || !m.classList.contains('is-open')) return;
            if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation();
                closeProductRowDetailModal();
            }
        }, true);
    }

    function getActiveDetailForm() {
        if (isMobileDetailView()) {
            var inline = document.getElementById('productModalMobileForm');
            if (inline && inline.children.length) return inline;
        }
        return document.getElementById('productRowDetailForm');
    }

    function setMobileModalTitle(text) {
        var title = document.getElementById('productSelectionModalLabel');
        if (title) title.textContent = text;
    }

    function resetMobileInlineProductForm() {
        var form = document.getElementById('productModalMobileForm');
        var empty = document.getElementById('productModalMobileFormEmpty');
        var footer = document.getElementById('productModalMobileFormFooter');
        var productModal = document.getElementById('productSelectionModal');
        if (form) form.innerHTML = '';
        if (empty) empty.hidden = true;
        if (footer) footer.hidden = true;
        if (productModal) productModal.classList.remove('product-modal-mobile-editing');
        setMobileModalTitle('Add Item');
        document.querySelectorAll('#productListBody .product-row-mobile-active').forEach(function (r) {
            r.classList.remove('product-row-mobile-active');
        });
    }

    function bindDetailFormControls(row, form, colKey, inputEl) {
        if (readonlyColsAtOpen[colKey]) {
            inputEl.classList.add('product-row-detail-modal__input--calculated');
        }
        (function (col, el) {
            el.addEventListener('focus', function () {
                activeDetailColKey = col;
            });
            el.addEventListener('input', function () {
                syncFormControlToRow(row, col, el, { skipRecalc: true });
                scheduleDetailModalRecalc(row, form);
            });
            el.addEventListener('change', function () {
                syncFormControlToRow(row, col, el, { skipRecalc: true });
                flushDetailModalRecalc(row, form);
            });
            el.addEventListener('blur', function () {
                syncFormControlToRow(row, col, el, { skipRecalc: true });
                flushDetailModalRecalc(row, form);
                if (activeDetailColKey === col) activeDetailColKey = null;
            });
            el.addEventListener('click', function (e) {
                e.stopPropagation();
            });
            el.addEventListener('mousedown', function (e) {
                e.stopPropagation();
            });
        })(colKey, inputEl);
    }

    function populateProductDetailForm(row, form, options) {
        options = options || {};
        if (!row || !form) return form;

        form.innerHTML = '';
        window.__auragoldCurrentDetailRow = row;
        readonlyColsAtOpen = {};

        var table = row.closest('table');
        var cols = getRowDetailColumnKeys(row, table);
        var lastGroup = null;
        var hideSections = !!options.hideSections;

        cols.forEach(function (colKey) {
            var cell = row.querySelector('td[data-column="' + colKey + '"]');
            if (!cell) return;
            if (isCellReadonly(cell)) {
                readonlyColsAtOpen[colKey] = true;
            }

            var groupId = getColumnGroupId(colKey);
            if (!hideSections && groupId !== lastGroup) {
                lastGroup = groupId;
                var groupTitle = GROUP_LABELS[groupId] || (groupId === 'other' ? 'Other' : groupId);
                var section = document.createElement('div');
                section.className = 'product-row-detail-modal__section-title';
                section.textContent = groupTitle;
                form.appendChild(section);
            } else if (hideSections) {
                lastGroup = groupId;
            }

            var fieldWrap = document.createElement('div');
            fieldWrap.className = 'product-row-detail-modal__field';

            var label = document.createElement('label');
            label.className = 'product-row-detail-modal__label';
            label.textContent = getColumnLabel(table, colKey, cell);

            var control = buildFormControl(cell, colKey, row);
            fieldWrap.appendChild(label);
            fieldWrap.appendChild(control);
            form.appendChild(fieldWrap);

            var inputEl = getFormControlFromWrap(control);
            if (!inputEl) return;
            bindDetailFormControls(row, form, colKey, inputEl);
        });

        runDetailModalRecalc(row, form);
        snapshotDetailFormValues(form);
        bindProductPickerFields(row, form);
        return form;
    }

    function exitMobileProductDetails() {
        saveMobileInlineProductForm();
        resetMobileInlineProductForm();
    }

    function patchMobileFormProductField(row) {
        if (!isMobileDetailView() || !row) return;
        var form = document.getElementById('productModalMobileForm');
        if (!form) return;
        var ctrl = form.querySelector('[data-column="product"]');
        if (!ctrl) return;
        var name = getProductDisplayName(row);
        ctrl.value = name;
        if (name) {
            ctrl.removeAttribute('placeholder');
        } else if (!ctrl.getAttribute('placeholder')) {
            ctrl.setAttribute('placeholder', 'Tap to select product');
        }
    }

    function refreshMobileInlineProductFormIfOpen(row) {
        if (!isMobileDetailView() || !row) return;
        var productModal = document.getElementById('productSelectionModal');
        if (!productModal || !productModal.classList.contains('product-modal-mobile-editing')) return;
        window.__auragoldCurrentDetailRow = row;
        window.setTimeout(function () {
            openMobileInlineProductForm(row);
            patchMobileFormProductField(row);
        }, 0);
    }

    /** Desktop Row Details modal — sync form fields after product pick / row populate. */
    function refreshProductRowDetailFormIfOpen(row) {
        if (!row || isMobileDetailView()) return;
        var modal = document.getElementById(MODAL_ID);
        if (!modal || !modal.classList.contains('is-open')) return;
        if (window.__auragoldCurrentDetailRow !== row) return;
        var form = document.getElementById('productRowDetailForm');
        if (!form) return;
        refreshFormFromRow(row, form);
        var productLabel = getProductDisplayName(row) || readCellValue(row.querySelector('td[data-column="barcode"]')) || 'Row';
        var titleEl = modal.querySelector('.product-row-detail-modal__title');
        if (titleEl) titleEl.textContent = 'Row Details — ' + productLabel;
    }

    function openMobileInlineProductForm(row) {
        if (!row || !isMobileDetailView()) return false;

        var form = document.getElementById('productModalMobileForm');
        var empty = document.getElementById('productModalMobileFormEmpty');
        var footer = document.getElementById('productModalMobileFormFooter');
        var productModal = document.getElementById('productSelectionModal');
        if (!form) return false;

        document.querySelectorAll('#productListBody .product-row, #productListTablePage .product-row').forEach(function (r) {
            r.classList.toggle('product-row-mobile-active', r === row);
        });

        populateProductDetailForm(row, form, { hideSections: true });

        if (empty) empty.hidden = true;
        if (footer) footer.hidden = false;
        if (productModal) productModal.classList.add('product-modal-mobile-editing');
        setMobileModalTitle('Product Details');

        return true;
    }

    function saveMobileInlineProductForm() {
        var row = window.__auragoldCurrentDetailRow;
        var form = document.getElementById('productModalMobileForm');
        if (!row || !form) return;
        if (detailRecalcTimer) {
            window.clearTimeout(detailRecalcTimer);
            detailRecalcTimer = null;
        }
        syncAllDetailFormFieldsToRow(row);
        recalcProductRow(row);
        snapshotDetailFormValues(form);
    }

    /** Mobile Product Details Save: sync row, add to main Product List table, return to Add Item (modal stays open). */
    function commitMobileProductDetailsSave() {
        var row = window.__auragoldCurrentDetailRow;
        if (!row) return { ok: false, reason: 'no_row' };

        saveMobileInlineProductForm();

        var productId = row.getAttribute('data-product-id');
        if (!productId) {
            var productName = getProductDisplayName(row);
            if (!productName) {
                alert('Please select a product before saving.');
                return { ok: false, reason: 'no_product' };
            }
        }

        if (typeof window.commitProductSelectionModalToMainTable === 'function') {
            var result = window.commitProductSelectionModalToMainTable({
                closeModal: false,
                notifyEmpty: true
            });
            if (result && result.ok) {
                window.__auragoldCurrentDetailRow = null;
                resetMobileInlineProductForm();
            }
            return result || { ok: false };
        }

        exitMobileProductDetails();
        return { ok: true };
    }

    /** Bootstrap #productSelectionModal traps focus — block it while row detail modal is open. */
    function suspendParentModalFocusTrap() {
        if (typeof window.auragoldSuspendBootstrapModalFocusTrap === 'function') {
            window.auragoldSuspendBootstrapModalFocusTrap('#' + MODAL_ID);
        }
    }

    function resumeParentModalFocusTrap() {
        if (typeof window.auragoldResumeBootstrapModalFocusTrap === 'function') {
            window.auragoldResumeBootstrapModalFocusTrap();
        }
    }

    function closeProductRowDetailModal() {
        var modal = document.getElementById(MODAL_ID);
        if (!modal) return;
        if (detailRecalcTimer) {
            window.clearTimeout(detailRecalcTimer);
            detailRecalcTimer = null;
        }
        activeDetailColKey = null;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('product-row-detail-modal-open');
        resumeParentModalFocusTrap();
        window.__auragoldCurrentDetailRow = null;
        var form = document.getElementById('productRowDetailForm');
        if (form) form.innerHTML = '';
    }

    function openProductRowDetailModal(trigger) {
        var row = getProductRowFromTrigger(trigger);
        if (!row) return;

        if (isMobileDetailView() && openMobileInlineProductForm(row)) {
            return;
        }

        var existingShell = document.getElementById(MODAL_ID);
        if (existingShell && !existingShell.querySelector('.product-row-detail-modal__back')) {
            existingShell.remove();
        }
        ensureModalShell();
        var form = document.getElementById('productRowDetailForm');
        if (!form) return;

        populateProductDetailForm(row, form, { hideSections: false });

        var productLabel = readCellValue(row.querySelector('td[data-column="product"]')) || readCellValue(row.querySelector('td[data-column="barcode"]')) || 'Row';
        var titleEl = document.querySelector('#' + MODAL_ID + ' .product-row-detail-modal__title');
        if (titleEl) titleEl.textContent = 'Row Details — ' + productLabel;

        var modal = document.getElementById(MODAL_ID);
        if (modal.parentNode !== document.body) {
            document.body.appendChild(modal);
        } else {
            document.body.appendChild(modal);
        }
        suspendParentModalFocusTrap();
        document.body.classList.add('product-row-detail-modal-open');
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');

        var dialog = modal.querySelector('.product-row-detail-modal__dialog');
        if (dialog && !dialog.hasAttribute('tabindex')) {
            dialog.setAttribute('tabindex', '-1');
        }
        window.setTimeout(function () {
            var firstInput = form.querySelector('input.product-row-detail-modal__input, select.product-row-detail-modal__input, textarea.product-row-detail-modal__input');
            if (firstInput && typeof firstInput.focus === 'function') {
                try { firstInput.focus(); firstInput.select && firstInput.select(); } catch (err) { /* ignore */ }
            } else if (dialog && typeof dialog.focus === 'function') {
                try { dialog.focus(); } catch (err2) { /* ignore */ }
            }
        }, 0);
    }

    function ensureProductRowViewIcon(row) {
        if (!row || !row.classList.contains('product-row')) return;
        var actions = row.querySelector('td[data-column="actions"]');
        if (!actions) return;
        if (actions.querySelector('.product-row-view-btn')) return;

        var eye = document.createElement('i');
        eye.className = 'feather icon-eye product-row-view-btn';
        eye.setAttribute('role', 'button');
        eye.setAttribute('tabindex', '0');
        eye.title = 'View / Edit all columns';
        eye.style.cssText = 'cursor:pointer;font-size:0.8rem;color:#11294b;margin-right:8px;';
        eye.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            openProductRowDetailModal(eye);
        });
        actions.insertBefore(eye, actions.firstChild);
    }

    function scanProductRowsForViewIcons(root) {
        var scope = root || document;
        scope.querySelectorAll('#productListBody .product-row, #productListTablePage .product-row').forEach(ensureProductRowViewIcon);
    }

    function injectStyles() {
        var cssText = ''
            + '.product-row-detail-modal{position:fixed;inset:0;background:rgba(15,23,42,.6);z-index:10850 !important;display:none;align-items:center;justify-content:center;padding:16px;pointer-events:auto;}'
            + 'body.product-row-detail-modal-open #productSelectionModal.modal{pointer-events:none !important;}'
            + 'body.product-row-detail-modal-open .modal-backdrop{pointer-events:none !important;}'
            + '.product-row-detail-modal.is-open{display:flex;}'
            + 'body.product-row-detail-modal-open .product-row-detail-modal.is-open{z-index:10850 !important;}'
            + '.product-row-detail-modal__dialog{background:#fff;border-radius:10px;width:min(1200px,96vw);max-height:92vh;display:flex;flex-direction:column;box-shadow:0 20px 50px rgba(0,0,0,.35);position:relative;z-index:10851;}'
            + '.product-row-detail-modal__header{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid #e2e8f0;}'
            + '.product-row-detail-modal__title{margin:0;font-size:1rem;color:#11294b;font-weight:600;}'
            + '.product-row-detail-modal__close{background:none;border:none;font-size:1.5rem;line-height:1;color:#64748b;cursor:pointer;}'
            + '.product-row-detail-modal__body{flex:1;overflow:auto;padding:16px 18px;}'
            + '.product-row-detail-modal__form{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px 16px;align-items:start;}'
            + '.product-row-detail-modal__section-title{grid-column:1/-1;margin:8px 0 0;padding:8px 10px;background:#f8fafc;border-left:3px solid #c5a864;font-size:0.78rem;font-weight:700;color:#11294b;text-transform:uppercase;letter-spacing:.03em;}'
            + '.product-row-detail-modal__section-title:first-child{margin-top:0;}'
            + '.product-row-detail-modal__field{display:flex;flex-direction:column;gap:4px;}'
            + '.product-row-detail-modal__label{font-size:0.72rem;font-weight:600;color:#475569;margin:0;}'
            + '.product-row-detail-modal__form input.product-row-detail-modal__input,'
            + '.product-row-detail-modal__form select.product-row-detail-modal__input,'
            + '.product-row-detail-modal__form textarea.product-row-detail-modal__input{'
            + 'display:block;width:100%;min-height:32px;border:1px solid #cbd5e1 !important;'
            + 'background:#fff !important;color:#11294b !important;'
            + 'padding:0.35rem 0.5rem !important;border-radius:4px;'
            + 'pointer-events:auto !important;-webkit-user-select:text !important;user-select:text !important;'
            + 'cursor:text;box-sizing:border-box;}'
            + '.product-row-detail-modal__form select.product-row-detail-modal__input{cursor:pointer;}'
            + '.product-row-detail-modal__form input.product-row-detail-modal__input:focus,'
            + '.product-row-detail-modal__form select.product-row-detail-modal__input:focus{'
            + 'border-color:#c5a864 !important;outline:none;'
            + 'box-shadow:0 0 0 2px rgba(197,168,100,.25) !important;}'
            + '.product-row-detail-modal__form .product-row-detail-modal__input--calculated{'
            + 'background:#f8fafc !important;color:#334155 !important;}'
            + '.product-row-detail-modal__footer{display:flex;justify-content:flex-end;gap:8px;padding:12px 18px;border-top:1px solid #e2e8f0;}'
            + '#productSelectionModal td[data-column="actions"] .product-row-view-btn:hover{color:#c5a864;}'
            + '#productSelectionModal td[data-column="actions"] .icon-edit:hover{color:#11294b;}'
            + 'body.product-row-detail-modal-open #productSearchModal{z-index:10900 !important;}';
        var css = document.getElementById('product-row-detail-modal-css');
        if (!css) {
            css = document.createElement('style');
            css.id = 'product-row-detail-modal-css';
            document.head.appendChild(css);
        }
        css.textContent = cssText;
    }

    function init() {
        injectStyles();
        bindEditIconOpensDetailModal();
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.product-row-view-btn');
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();
            openProductRowDetailModal(btn);
        });

        document.addEventListener('click', function (e) {
            if (!isMobileDetailView()) return;
            var row = e.target.closest('#productListBody .product-row, #productListTablePage .product-row');
            if (!row) return;
            if (e.target.closest('td[data-column="checkbox"], td[data-column="actions"], .product-row-view-btn')) return;
            if (e.target.closest('input, select, textarea, button, a, label')) return;
            e.preventDefault();
            e.stopPropagation();
            openProductRowDetailModal(row);
        }, true);

        document.addEventListener('click', function (e) {
            if (!isMobileDetailView()) return;
            var btn = e.target.closest('#addProductRowBtn, [id$="addProductRowBtn"]');
            if (btn) {
                window.__auragoldAutoOpenNextDetailRow = true;
            }
        }, true);

        document.addEventListener('click', function (e) {
            if (e.target.closest('[data-action="mobile-clear"]')) {
                e.preventDefault();
                clearDetailForm();
            }
            if (e.target.closest('[data-action="mobile-save"]')) {
                e.preventDefault();
                e.stopPropagation();
                commitMobileProductDetailsSave();
            }
        });

        if (typeof jQuery !== 'undefined') {
            jQuery(document).on('shown.bs.modal', '#productSelectionModal', function () {
                scanProductRowsForViewIcons(document.getElementById('productListBody'));
            });
            jQuery(document).on('hidden.bs.modal', '#productSelectionModal', function () {
                resetMobileInlineProductForm();
                window.__auragoldAutoOpenNextDetailRow = false;
            });
        }

        document.addEventListener('click', function (e) {
            var back = e.target.closest('#productSelectionModal .product-modal-mobile-back');
            if (!back || !isMobileDetailView()) return;
            e.preventDefault();
            e.stopPropagation();
            var productModal = document.getElementById('productSelectionModal');
            if (productModal && productModal.classList.contains('product-modal-mobile-editing')) {
                exitMobileProductDetails();
                return;
            }
            if (typeof jQuery !== 'undefined') {
                jQuery('#productSelectionModal').modal('hide');
            }
        }, true);

        var listBody = document.getElementById('productListBody');
        if (listBody && typeof MutationObserver !== 'undefined') {
            var obs = new MutationObserver(function (mutations) {
                mutations.forEach(function (m) {
                    Array.prototype.forEach.call(m.addedNodes || [], function (node) {
                        if (!node || node.nodeType !== 1) return;
                        if (node.classList && node.classList.contains('product-row')) {
                            ensureProductRowViewIcon(node);
                            if (window.__auragoldAutoOpenNextDetailRow && isMobileDetailView()) {
                                window.__auragoldAutoOpenNextDetailRow = false;
                                window.setTimeout(function () {
                                    openProductRowDetailModal(node);
                                }, 150);
                            }
                        } else if (node.querySelectorAll) {
                            node.querySelectorAll('.product-row').forEach(function (prow) {
                                ensureProductRowViewIcon(prow);
                                if (window.__auragoldAutoOpenNextDetailRow && isMobileDetailView()) {
                                    window.__auragoldAutoOpenNextDetailRow = false;
                                    window.setTimeout(function () {
                                        openProductRowDetailModal(prow);
                                    }, 150);
                                }
                            });
                        }
                    });
                });
            });
            obs.observe(listBody, { childList: true, subtree: true });
        }

        scanProductRowsForViewIcons(document);
        patchEditProductRowRedirect();
    }

    /** Route legacy edit icon / editProductRowInTable() to the styled row detail modal. */
    function patchEditProductRowRedirect() {
        window.editProductRowInTable = function (rowElement) {
            openProductRowDetailModal(rowElement);
        };
        window.showEditRowModal = function () {
            /* deprecated — use openProductRowDetailModal */
        };
        window.closeEditRowModal = function () {
            closeProductRowDetailModal();
            var legacy = document.getElementById('editRowModal');
            if (legacy) legacy.remove();
            window.currentEditingRow = null;
        };
    }

    function bindEditIconOpensDetailModal() {
        document.addEventListener('click', function (e) {
            var editBtn = e.target.closest('#productSelectionModal td[data-column="actions"] .icon-edit');
            if (!editBtn || editBtn.classList.contains('product-row-view-btn')) return;
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            openProductRowDetailModal(editBtn);
        }, true);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.openProductRowDetailModal = openProductRowDetailModal;
    window.closeProductRowDetailModal = closeProductRowDetailModal;
    window.openMobileInlineProductForm = openMobileInlineProductForm;
    window.resetMobileInlineProductForm = resetMobileInlineProductForm;
    window.exitMobileProductDetails = exitMobileProductDetails;
    window.commitMobileProductDetailsSave = commitMobileProductDetailsSave;
    window.refreshMobileInlineProductFormIfOpen = refreshMobileInlineProductFormIfOpen;
    window.refreshProductRowDetailFormIfOpen = refreshProductRowDetailFormIfOpen;
    window.patchMobileFormProductField = patchMobileFormProductField;
    window.ensureProductRowViewIcon = ensureProductRowViewIcon;
    window.scanProductRowsForViewIcons = scanProductRowsForViewIcons;
})();
