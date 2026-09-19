/**
 * Searchable customer / supplier Select2 for voucher pages.
 * Config: window.AURAGOLD_PARTY_SELECT2 = { placeholder, searchUrl, partyId, partyName, ... }
 */
(function () {
    'use strict';

    var skipNextPartyChange = false;

    function cfg() {
        var c = window.AURAGOLD_PARTY_SELECT2 || {};
        return {
            partyId: c.partyId || '#customerId',
            partyName: c.partyName || '#customerName',
            billingState: c.billingState || '#customerBillingState',
            gstin: c.gstin || '#customerGstin',
            wrapClass: c.wrapClass || 'auragold-party-select2-wrap',
            containerClass: c.containerClass || 'auragold-party-select2-container',
            dropdownClass: c.dropdownClass || 'auragold-party-select2-dropdown',
            searchUrl: c.searchUrl || 'ajax/search-customers.php',
            placeholder: c.placeholder || 'Select customer...',
            emptyLabel: c.emptyLabel || 'Customer',
            noResultsText: c.noResultsText || 'No account found',
            inputTooShortText: c.inputTooShortText || 'Type to search…'
        };
    }

    function hasSelect2() {
        return typeof jQuery !== 'undefined' && jQuery.fn && jQuery.fn.select2;
    }

    function $partySel() {
        if (typeof jQuery === 'undefined') return null;
        return jQuery(cfg().partyId);
    }

    function syncPartyNameHidden(name) {
        var el = document.querySelector(cfg().partyName);
        if (el) {
            el.value = String(name || '').trim();
        }
    }

    function applyPartyMeta(data) {
        var c = cfg();
        var billingState = data && data.billing_state != null ? String(data.billing_state) : '';
        var gstin = data && data.gstin != null ? String(data.gstin).trim().replace(/\s+/g, '').toUpperCase() : '';
        var cbs = document.querySelector(c.billingState);
        if (cbs) {
            cbs.value = billingState;
        }
        if (typeof window.customerState !== 'undefined') {
            window.customerState = billingState || '';
        }
        if (gstin) {
            var cg = document.querySelector(c.gstin);
            if (cg) {
                cg.value = gstin;
            }
        }
    }

    function runAfterPick(partyId, partyName, meta) {
        var pname = String(partyName || '').trim();
        var cid = partyId ? String(parseInt(partyId, 10)) : '';
        // Free-text customer (typed name + Enter, no ledger id yet).
        if ((!cid || cid === 'NaN' || cid === '0') && pname) {
            syncPartyNameHidden(pname);
            if (typeof window.selectedCustomerId !== 'undefined') {
                window.selectedCustomerId = null;
            }
            applyPartyMeta(meta || {});
            if (typeof window.updateSaleInvoiceAddItemButtonState === 'function') {
                window.updateSaleInvoiceAddItemButtonState();
            }
            if (window.AURAGOLD_PARTY_SELECT2 && typeof window.AURAGOLD_PARTY_SELECT2.onPick === 'function') {
                window.AURAGOLD_PARTY_SELECT2.onPick('0', pname, meta || {});
            }
            scheduleLoadCustomerBalance();
            return;
        }
        if (!cid || cid === 'NaN' || cid === '0') {
            syncPartyNameHidden('');
            if (typeof window.selectedCustomerId !== 'undefined') {
                window.selectedCustomerId = null;
            }
            applyPartyMeta({});
            if (typeof window.updateSaleInvoiceAddItemButtonState === 'function') {
                window.updateSaleInvoiceAddItemButtonState();
            }
            if (window.AURAGOLD_PARTY_SELECT2 && typeof window.AURAGOLD_PARTY_SELECT2.onClear === 'function') {
                window.AURAGOLD_PARTY_SELECT2.onClear();
            }
            scheduleLoadCustomerBalance();
            return;
        }

        syncPartyNameHidden(partyName || '');
        if (typeof window.selectedCustomerId !== 'undefined') {
            window.selectedCustomerId = cid;
        }
        applyPartyMeta(meta || {});

        if (typeof window.saleInvoiceApplyCustomerGstinFromServer === 'function') {
            window.saleInvoiceApplyCustomerGstinFromServer(cid);
        }
        if (typeof window.updateSaleInvoiceAddItemButtonState === 'function') {
            window.updateSaleInvoiceAddItemButtonState();
        }
        if (window.AURAGOLD_PARTY_SELECT2 && typeof window.AURAGOLD_PARTY_SELECT2.onPick === 'function') {
            window.AURAGOLD_PARTY_SELECT2.onPick(cid, partyName, meta || {});
        }

        scheduleLoadCustomerBalance();

        setTimeout(function () {
            if (typeof window.auragoldSaleInvoiceRefreshGstForAllRows === 'function') {
                window.auragoldSaleInvoiceRefreshGstForAllRows();
            }
        }, 100);
    }

    function destroyAuragoldPartySelect2() {
        var $s = $partySel();
        if (!$s || !$s.length || !hasSelect2()) return;
        if ($s.hasClass('select2-hidden-accessible')) {
            $s.off('.auragoldParty');
            $s.select2('destroy');
        }
    }

    function initAuragoldPartySelect2(overrideCfg) {
        if (overrideCfg) {
            window.AURAGOLD_PARTY_SELECT2 = jQuery.extend({}, window.AURAGOLD_PARTY_SELECT2 || {}, overrideCfg);
        }
        var c = cfg();
        var el = document.querySelector(c.partyId);
        if (!el || el.tagName !== 'SELECT' || !hasSelect2()) return;

        var emptyOpt = el.querySelector('option[value=""]');
        if (emptyOpt && emptyOpt.textContent) {
            var ph = String(emptyOpt.textContent).trim();
            if (ph) {
                c.placeholder = ph;
            }
            if (ph.toLowerCase().indexOf('supplier') >= 0) {
                c.emptyLabel = 'Supplier';
                c.noResultsText = 'No supplier found';
            }
        }

        var $s = jQuery(el);
        destroyAuragoldPartySelect2();

        var branchId = (typeof window.AURAGOLD_WORKING_BRANCH_ID !== 'undefined') ? window.AURAGOLD_WORKING_BRANCH_ID : '';

        $s.select2({
            placeholder: c.placeholder,
            allowClear: true,
            width: '100%',
            minimumInputLength: 0,
            dropdownParent: jQuery(document.body),
            containerCssClass: c.containerClass,
            dropdownCssClass: c.dropdownClass,
            language: {
                inputTooShort: function () { return c.inputTooShortText; },
                searching: function () { return 'Searching…'; },
                noResults: function () {
                    var term = getAuragoldPartySearchTerm();
                    if (term) {
                        return c.noResultsText + ' — press Enter to add new';
                    }
                    return c.noResultsText;
                }
            },
            ajax: {
                url: c.searchUrl,
                dataType: 'json',
                delay: 280,
                data: function (params) {
                    var payload = { q: params.term || '', format: 'select2' };
                    if (branchId) {
                        payload.branch_id = branchId;
                    }
                    return payload;
                },
                processResults: function (data) {
                    var rows = (data && data.results) ? data.results : [];
                    return {
                        results: rows,
                        pagination: { more: !!(data && data.pagination && data.pagination.more) }
                    };
                },
                cache: true
            },
            templateResult: function (item) {
                if (!item.id) return item.text;
                var name = item.name || item.text || '';
                var $wrap = jQuery('<div>');
                $wrap.append(jQuery('<div>').css({ fontWeight: '600' }).text(name));
                if (item.mobile_no) {
                    $wrap.append(jQuery('<div>').css({ fontSize: '0.8rem', color: '#64748b' }).text(item.mobile_no));
                }
                if (item.alternate_name) {
                    $wrap.append(jQuery('<div>').css({ fontSize: '0.75rem', color: '#94a3b8' }).text(item.alternate_name));
                }
                return $wrap;
            },
            templateSelection: function (item) {
                return item.name || item.text || item.id || '';
            }
        });

        $s.on('select2:select.auragoldParty', function (e) {
            var row = (e && e.params && e.params.data) ? e.params.data : {};
            var pid = String(row.id || $s.val() || '').trim();
            var pname = row.name || row.text || '';
            skipNextPartyChange = true;
            if ($s.hasClass('select2-hidden-accessible')) {
                $s.select2('close');
            }
            runAfterPick(pid, pname, row);
        });

        $s.on('select2:clear.auragoldParty', function () {
            skipNextPartyChange = true;
            runAfterPick('', '', {});
        });

        // Programmatic value changes (edit load, new customer save) — skip when user pick already handled.
        $s.on('change.auragoldParty', function () {
            if (skipNextPartyChange) {
                skipNextPartyChange = false;
                return;
            }
            var data = $s.select2('data');
            var row = (data && data.length) ? data[0] : null;
            var pid = String($s.val() || '').trim();
            if (!pid) {
                runAfterPick('', '', {});
                return;
            }
            if (pid === '0') {
                var opt0 = el.options[el.selectedIndex];
                var freeName = opt0 ? String(opt0.text || '').trim() : '';
                if (!freeName && row) {
                    freeName = String(row.name || row.text || '').trim();
                }
                runAfterPick('0', freeName, row || {});
                return;
            }
            var pname = row ? (row.name || row.text || '') : '';
            if (!pname && pid) {
                var opt = el.options[el.selectedIndex];
                pname = opt ? String(opt.text || '').trim() : '';
            }
            runAfterPick(pid, pname, row || {});
        });

        jQuery('.top-navbar').off('mouseenter.auragoldParty').on('mouseenter.auragoldParty', function () {
            if ($s.hasClass('select2-hidden-accessible')) {
                $s.select2('close');
            }
        });
    }

    function setAuragoldPartyValue(partyId, displayName, meta) {
        var c = cfg();
        var el = document.querySelector(c.partyId);
        if (!el || el.tagName !== 'SELECT') return;
        var pid = partyId ? String(parseInt(partyId, 10)) : '';
        if (!pid || pid === 'NaN' || pid === '0') {
            if (hasSelect2() && jQuery(el).hasClass('select2-hidden-accessible')) {
                jQuery(el).val(null).trigger('change');
            } else {
                el.value = '';
                syncPartyNameHidden('');
            }
            return;
        }

        var label = String(displayName || '').trim() || (c.emptyLabel + ' #' + pid);
        var $s = jQuery(el);
        var rowData = Object.assign({ id: pid, text: label, name: label }, meta || {});
        var $opt = $s.find('option[value="' + pid + '"]');
        if (!$opt.length) {
            $s.append(new Option(label, pid, true, true));
        } else {
            $opt.text(label);
        }
        if (hasSelect2() && $s.hasClass('select2-hidden-accessible')) {
            $s.val(pid);
            $s.trigger({
                type: 'select2:select',
                params: { data: rowData }
            });
            $s.trigger('change');
        } else {
            el.value = pid;
            syncPartyNameHidden(label);
            runAfterPick(pid, label, meta || {});
        }
    }

    function partySelectElement() {
        var el = document.querySelector(cfg().partyId);
        return (el && el.tagName === 'SELECT') ? el : null;
    }

    function partySelect2Instance() {
        var el = partySelectElement();
        if (!el || typeof jQuery === 'undefined' || !jQuery(el).hasClass('select2-hidden-accessible')) {
            return null;
        }
        var inst = jQuery(el).data('select2');
        return (inst && typeof inst.isOpen === 'function' && inst.isOpen()) ? inst : null;
    }

    /** Open Select2 container for the configured party select. */
    function partySelect2OpenContainer() {
        var inst = partySelect2Instance();
        return (inst && inst.$container && inst.$container.length) ? inst.$container[0] : null;
    }

    function isPartySelect2SearchField(fieldEl) {
        var inst = partySelect2Instance();
        if (!inst || !fieldEl || !inst.$dropdown || !inst.$dropdown.length) return false;
        return jQuery.contains(inst.$dropdown[0], fieldEl);
    }

    function partySelectHasSelectableHighlight() {
        var inst = partySelect2Instance();
        if (!inst || !inst.$results || !inst.$results.length) return false;
        var hl = inst.$results.find('.select2-results__option--highlighted').first();
        if (!hl.length) return false;
        if (hl.hasClass('select2-results__message')) return false;
        if (hl.attr('aria-disabled') === 'true') return false;
        return true;
    }

    function getAuragoldPartySearchTerm() {
        var inst = partySelect2Instance();
        if (!inst || !inst.$dropdown || !inst.$dropdown.length) return '';
        var $field = inst.$dropdown.find('.select2-search__field');
        return $field.length ? String($field.val() || '').trim() : '';
    }

    function setAuragoldPartyFreeTextName(name) {
        name = String(name || '').trim();
        if (!name) return;
        var el = partySelectElement();
        if (!el) return;
        var $s = jQuery(el);
        if ($s.hasClass('select2-hidden-accessible')) {
            $s.select2('close');
        }
        $s.find('option[value="0"]').remove();
        $s.append(new Option(name, '0', true, true));
        skipNextPartyChange = true;
        if ($s.hasClass('select2-hidden-accessible')) {
            $s.val('0').trigger({
                type: 'select2:select',
                params: { data: { id: '0', text: name, name: name } }
            });
        } else {
            el.value = '0';
            runAfterPick('0', name, {});
        }
    }

    function openNewPartyModalWithName(term) {
        term = String(term || '').trim();
        if (!term || !document.getElementById('customerCreationModal')) return;
        var $s = $partySel();
        if ($s && $s.hasClass('select2-hidden-accessible')) {
            $s.select2('close');
        }
        if (typeof window.openCustomerModalForAdd === 'function') {
            window.openCustomerModalForAdd(term);
            return;
        }
        if (typeof initNewLedgerModalDefaults === 'function') {
            initNewLedgerModalDefaults();
        } else if (typeof clearCustomerForm === 'function') {
            clearCustomerForm();
        }
        var modalEl = (typeof window.auragoldGetCustomerCreationModal === 'function')
            ? window.auragoldGetCustomerCreationModal()
            : document.getElementById('customerCreationModal');
        if (modalEl && typeof window.jQuery !== 'undefined') {
            window.jQuery(modalEl).modal('show');
        }
        setTimeout(function () {
            var ledgerNameField = (typeof window.auragoldGetCustomerCreationField === 'function')
                ? window.auragoldGetCustomerCreationField('ledgerName')
                : document.getElementById('ledgerName');
            if (ledgerNameField) {
                ledgerNameField.value = term;
                if (typeof handleNameInput === 'function') {
                    handleNameInput(ledgerNameField);
                }
                ledgerNameField.focus();
            }
        }, 300);
    }

    function scheduleLoadCustomerBalance() {
        var cfg = window.PB_PAGE_CONFIG || {};
        if (typeof cfg.skipAutoLoad === 'function' && cfg.skipAutoLoad()) {
            return;
        }
        if (cfg.skipIfEditMode && window.isPurchaseInvoiceEditMode) {
            return;
        }
        function attempt(retry) {
            if (typeof window.loadCustomerBalance === 'function') {
                window.loadCustomerBalance();
                return;
            }
            if (window.PrevBalanceUI && typeof window.PrevBalanceUI.loadImmediate === 'function') {
                window.PrevBalanceUI.loadImmediate(window.PB_PAGE_CONFIG || {});
                return;
            }
            if (retry < 30) {
                setTimeout(function () { attempt(retry + 1); }, 50);
            }
        }
        setTimeout(function () { attempt(0); }, 0);
    }

    function preloadFromHiddenFields() {
        var c = cfg();
        var el = document.querySelector(c.partyId);
        var nameEl = document.querySelector(c.partyName);
        if (!el || el.tagName !== 'SELECT' || !el.value) return;
        var nm = nameEl ? String(nameEl.value || '').trim() : '';
        if (el.value && (nm || el.options.length <= 2)) {
            setAuragoldPartyValue(el.value, nm);
        }
    }

    function bindLegacyCustomerNameTextEnter() {
        jQuery(document).off('keydown.auragoldPartyTextEnter').on('keydown.auragoldPartyTextEnter', '#customerName', function (e) {
            if (e.key !== 'Enter') return;
            var input = this;
            if (!input || input.tagName !== 'INPUT' || input.type === 'hidden' || input.readOnly || input.disabled) {
                return;
            }
            var c = cfg();
            var partyEl = document.querySelector(c.partyId);
            if (partyEl && partyEl.tagName === 'SELECT' && hasSelect2() && jQuery(partyEl).hasClass('select2-hidden-accessible')) {
                return;
            }
            var term = String(input.value || '').trim();
            var cidEl = document.querySelector('#customerId');
            var pid = cidEl ? String(cidEl.value || '').trim() : '';
            if (pid || !term) return;
            var suggestions = document.getElementById('customerSuggestions');
            if (suggestions && suggestions.style.display !== 'none') {
                var focused = suggestions.querySelector('.customer-suggestion-item.focused');
                if (focused) return;
                if (suggestions.querySelectorAll('.customer-suggestion-item').length > 0) {
                    return;
                }
            }
            if (!document.getElementById('customerCreationModal')) return;
            e.preventDefault();
            e.stopPropagation();
            if (suggestions) {
                suggestions.style.display = 'none';
            }
            openNewPartyModalWithName(term);
        });
    }

    function bindEnterOpensCustomerModal() {
        document.removeEventListener('keydown', onPartySelectEnterKey, true);
        document.addEventListener('keydown', onPartySelectEnterKey, true);
    }

    function onPartySelectEnterKey(e) {
        if (e.key !== 'Enter') return;
        var field = e.target;
        if (!field || !field.classList || !field.classList.contains('select2-search__field')) return;
        if (!isPartySelect2SearchField(field)) return;
        var term = String(field.value || '').trim() || getAuragoldPartySearchTerm();
        var el = partySelectElement();
        var pid = el ? String(el.value || '').trim() : '';
        if ((pid && pid !== '0') || !term) return;
        if (partySelectHasSelectableHighlight()) return;
        e.preventDefault();
        e.stopPropagation();
        if (window.AURAGOLD_PARTY_SELECT2 && typeof window.AURAGOLD_PARTY_SELECT2.onEnterNewParty === 'function') {
            window.AURAGOLD_PARTY_SELECT2.onEnterNewParty(term);
            return;
        }
        if (document.getElementById('customerCreationModal')) {
            openNewPartyModalWithName(term);
        } else {
            setAuragoldPartyFreeTextName(term);
        }
    }

    window.setAuragoldPartyFreeTextName = setAuragoldPartyFreeTextName;
    window.initAuragoldPartySelect2 = initAuragoldPartySelect2;
    window.destroyAuragoldPartySelect2 = destroyAuragoldPartySelect2;
    window.setAuragoldPartyValue = setAuragoldPartyValue;
    window.getAuragoldPartySearchTerm = getAuragoldPartySearchTerm;
    window.openNewPartyModalWithName = openNewPartyModalWithName;
    window.setSaleInvoiceCustomerValue = setAuragoldPartyValue;
    window.getSaleInvoiceCustomerSearchTerm = getAuragoldPartySearchTerm;
    window.initSaleInvoiceCustomerSelect2 = initAuragoldPartySelect2;
    window.destroySaleInvoiceCustomerSelect2 = destroyAuragoldPartySelect2;

    function bootAuragoldPartySelect2() {
        if (typeof jQuery === 'undefined' || !jQuery.fn || !jQuery.fn.select2) {
            setTimeout(bootAuragoldPartySelect2, 40);
            return;
        }
        jQuery(function () {
            initAuragoldPartySelect2();
            preloadFromHiddenFields();
            bindEnterOpensCustomerModal();
            bindLegacyCustomerNameTextEnter();
        });
    }

    bootAuragoldPartySelect2();
})();
