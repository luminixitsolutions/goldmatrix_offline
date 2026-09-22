/**
 * Ledger / customer creation modal — explicit required-field validation with alerts.
 * Scopes field lookups to the visible modal (avoids duplicate #ledgerName on same page).
 */
(function () {
    'use strict';

    function escapeFieldId(id) {
        return String(id || '').replace(/\\/g, '\\\\').replace(/"/g, '\\"');
    }

    window.auragoldGetCustomerCreationModal = function () {
        var modals = document.querySelectorAll('#customerCreationModal');
        var i;
        for (i = 0; i < modals.length; i++) {
            if (modals[i].classList.contains('show')) {
                return modals[i];
            }
        }
        if (modals.length) {
            return modals[modals.length - 1];
        }
        return null;
    };

    window.auragoldGetCustomerCreationField = function (fieldId) {
        if (!fieldId) {
            return null;
        }
        var modal = window.auragoldGetCustomerCreationModal();
        if (modal) {
            return modal.querySelector('[id="' + escapeFieldId(fieldId) + '"]');
        }
        return document.getElementById(fieldId);
    };

    window.auragoldGetCustomerCreationForm = function () {
        var modal = window.auragoldGetCustomerCreationModal();
        if (modal) {
            return modal.querySelector('#customerCreationForm');
        }
        return document.getElementById('customerCreationForm');
    };

    function fail(message, el, tabHref) {
        alert(message);
        if (tabHref && typeof window.jQuery !== 'undefined') {
            var modal = window.auragoldGetCustomerCreationModal();
            var tabRoot = modal || document;
            window.jQuery(tabRoot).find('#ledgerTabs a[href="' + tabHref + '"]').tab('show');
        }
        if (el && typeof el.focus === 'function') {
            setTimeout(function () {
                try {
                    el.focus();
                    if (typeof el.scrollIntoView === 'function') {
                        el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                    }
                } catch (e) { /* ignore */ }
            }, tabHref ? 220 : 0);
        }
        return { ok: false, message: message };
    }

    window.auragoldValidateCustomerCreationForm = function () {
        var nameEl = window.auragoldGetCustomerCreationField('ledgerName');
        if (!nameEl || !String(nameEl.value || '').trim()) {
            return fail('Name is required. Please fill in the customer name.', nameEl, null);
        }

        var mobileEl = window.auragoldGetCustomerCreationField('ledgerMobileNo');
        if (!mobileEl || !String(mobileEl.value || '').trim()) {
            return fail('Mobile number is required. Please enter mobile number.', mobileEl, null);
        }

        var ledgerIdEl = window.auragoldGetCustomerCreationField('ledgerCustomerId');
        var isNew = !ledgerIdEl || !String(ledgerIdEl.value || '').trim();
        var customerTypeEl = window.auragoldGetCustomerCreationField('customerType');
        if (isNew && customerTypeEl && !String(customerTypeEl.value || '').trim()) {
            return fail('Customer type is required. Please select customer type.', customerTypeEl, null);
        }

        var sundryEl = window.auragoldGetCustomerCreationField('ledgerSundryDebtors');
        if (sundryEl && !String(sundryEl.value || '').trim()) {
            return fail('Sundry Debtors is required. Please select a ledger group.', sundryEl, null);
        }

        var countryEl = window.auragoldGetCustomerCreationField('billingCountry');
        if (countryEl && !String(countryEl.value || '').trim()) {
            return fail('Billing country is required. Open Billing Address and select country.', countryEl, '#billing-address');
        }

        var stateEl = window.auragoldGetCustomerCreationField('billingState');
        if (stateEl && !String(stateEl.value || '').trim()) {
            return fail('Billing state is required. Open Billing Address and select state.', stateEl, '#billing-address');
        }

        return { ok: true, message: '' };
    };

    /**
     * Prefill Item Type Tax selects from customer.item_tax / item_tax_data.
     * Saved values were not restored on edit, so changes looked like they never saved.
     */
    window.auragoldApplyCustomerItemTaxFromCustomer = function (c) {
        if (!c) {
            return;
        }
        var tax = c.item_tax != null ? c.item_tax : c.item_tax_data;
        if (typeof tax === 'string') {
            try {
                tax = JSON.parse(tax);
            } catch (e) {
                tax = null;
            }
        }
        if (!tax || typeof tax !== 'object') {
            return;
        }

        var form = typeof window.auragoldGetCustomerCreationForm === 'function'
            ? window.auragoldGetCustomerCreationForm()
            : document.getElementById('customerCreationForm');
        if (!form) {
            return;
        }

        Object.keys(tax).forEach(function (itemKey) {
            var row = tax[itemKey];
            if (!row || typeof row !== 'object') {
                return;
            }
            var inSel = form.querySelector('select[name="item_tax[' + itemKey + '][input_type]"]');
            var outSel = form.querySelector('select[name="item_tax[' + itemKey + '][output_type]"]');
            if (inSel && row.input_type != null) {
                inSel.value = String(row.input_type);
            }
            if (outSel && row.output_type != null) {
                outSel.value = String(row.output_type);
            }
        });
    };

    // Ensure every page's fillCustomerForm also restores item tax after it runs.
    (function patchFillCustomerFormForItemTax() {
        var tries = 0;
        var timer = setInterval(function () {
            tries += 1;
            if (typeof window.fillCustomerForm === 'function' && !window.fillCustomerForm._auragoldItemTaxPatched) {
                var orig = window.fillCustomerForm;
                window.fillCustomerForm = function (c) {
                    var result = orig.apply(this, arguments);
                    try {
                        window.auragoldApplyCustomerItemTaxFromCustomer(c);
                    } catch (e) {
                        console.error('Item tax prefill failed', e);
                    }
                    return result;
                };
                window.fillCustomerForm._auragoldItemTaxPatched = true;
                clearInterval(timer);
            }
            if (tries > 120) {
                clearInterval(timer);
            }
        }, 50);
    })();
})();
