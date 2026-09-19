/**
 * Add Product modal (common-modal.php): vendor dropdown + right-side ledger drawer.
 */
(function ($) {
    'use strict';

    function selectOptionByLabel(selectEl, label) {
        if (!selectEl || !label) return;
        const want = String(label).trim().toLowerCase();
        for (let i = 0; i < selectEl.options.length; i++) {
            const opt = selectEl.options[i];
            if (!opt.value) continue;
            if (opt.text.trim().toLowerCase() === want) {
                selectEl.selectedIndex = i;
                return;
            }
        }
    }

    window.AURAGOLD_VENDOR_SAVE_SUCCESS_MSG = 'Record or Account Created Successfully';

    window.updateProductVendorDropdown = function (vendorId, vendorName) {
        const ids = ['productVendor', 'productVendorSelect'];
        ids.forEach(function (id) {
            const select = document.getElementById(id);
            if (!select) return;
            let exists = false;
            for (let i = 0; i < select.options.length; i++) {
                if (String(select.options[i].value) === String(vendorId)) {
                    exists = true;
                    break;
                }
            }
            if (!exists) {
                const opt = document.createElement('option');
                opt.value = vendorId;
                opt.textContent = vendorName;
                select.appendChild(opt);
            }
            select.value = String(vendorId);
        });
        document.querySelectorAll('#productCreationForm select[name="vendor_id"]').forEach(function (select) {
            let exists = false;
            for (let i = 0; i < select.options.length; i++) {
                if (String(select.options[i].value) === String(vendorId)) {
                    exists = true;
                    break;
                }
            }
            if (!exists) {
                const opt = document.createElement('option');
                opt.value = vendorId;
                opt.textContent = vendorName;
                select.appendChild(opt);
            }
            select.value = String(vendorId);
        });
    };

    window.openProductVendorModalForAdd = function () {
        window.auragoldVendorSaveTarget = 'product';
        if (typeof window.clearCustomerForm === 'function') {
            window.clearCustomerForm();
        }
        if (typeof window.setCustomerModalMode === 'function') {
            window.setCustomerModalMode('add');
        }
        const label = document.getElementById('customerCreationModalLabel');
        if (label) label.textContent = 'Add Vendor';
        selectOptionByLabel(document.getElementById('customerType'), 'supplier');
        selectOptionByLabel(document.getElementById('ledgerGroup'), 'Sundry Creditors');
        selectOptionByLabel(document.getElementById('ledgerSundryDebtors'), 'Sundry Creditors');
        const $modal = $('#customerCreationModal');
        $modal.appendTo('body');
        $modal.modal({ backdrop: true, keyboard: true, show: true });
    };

    $(document).ready(function () {
        $(document).on('click', '.cm-add-vendor', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof window.openProductVendorModalForAdd === 'function') {
                window.openProductVendorModalForAdd();
            }
        });
    });
})(jQuery);
