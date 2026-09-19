/**
 * Product Opening — category & vendor (supplier) modal helpers.
 */
(function ($) {
    'use strict';

    function previewLedgerPhoto(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function (e) {
                const prev = document.getElementById('ledgerPhotoPreview');
                const img = document.getElementById('ledgerPhotoImg');
                if (prev) prev.style.display = 'block';
                if (img) img.src = e.target.result;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    function handleNameInput(input) {
        const nameValue = input.value;
        const capitalCheckbox = document.getElementById('ledgerNameCapital');
        if (capitalCheckbox && capitalCheckbox.checked) {
            input.value = nameValue.toUpperCase();
        }
        const nameParts = nameValue.trim().split(/\s+/);
        const firstNameField = document.getElementById('ledgerFirstName');
        const lastNameField = document.getElementById('ledgerLastName');
        if (nameParts.length > 0) {
            if (firstNameField) firstNameField.value = nameParts[0];
            if (lastNameField) {
                lastNameField.value = nameParts.length > 1 ? nameParts[nameParts.length - 1] : '';
            }
        }
    }

    function sortShareHoldersTable() {}
    function addShareHolderRow() {}
    function deleteShareHolderRow() {}
    function handleShareHolderFileDrop(event) {
        if (event) event.preventDefault();
    }
    function handleShareHolderFileSelect() {}
    function removeShareHolderFile() {}

    window.previewLedgerPhoto = previewLedgerPhoto;
    window.handleNameInput = handleNameInput;
    window.sortShareHoldersTable = sortShareHoldersTable;
    window.addShareHolderRow = addShareHolderRow;
    window.deleteShareHolderRow = deleteShareHolderRow;
    window.handleShareHolderFileDrop = handleShareHolderFileDrop;
    window.handleShareHolderFileSelect = handleShareHolderFileSelect;
    window.removeShareHolderFile = removeShareHolderFile;

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

    function clearCustomerForm() {
        const form = document.getElementById('customerCreationForm');
        if (form) form.reset();
        const hiddenId = document.getElementById('ledgerCustomerId');
        if (hiddenId) hiddenId.value = '';
        const prev = document.getElementById('ledgerPhotoPreview');
        if (prev) prev.style.display = 'none';
        const photoInput = document.getElementById('ledgerPhotoInput');
        if (photoInput) photoInput.value = '';
        const shareHoldersBody = document.getElementById('shareHoldersTableBody');
        if (shareHoldersBody) shareHoldersBody.innerHTML = '';
        const fileList = document.getElementById('shareHolderFileList');
        if (fileList) fileList.innerHTML = '';
    }

    function applyDefaultLedgerCustomerType(typeName) {
        selectOptionByLabel(document.getElementById('customerType'), typeName);
    }

    function setCustomerModalMode(mode) {
        const label = document.getElementById('customerCreationModalLabel');
        const saveBtn = document.getElementById('customerModalSaveBtn');
        window._ledgerCustomerModalMode = (mode === 'edit') ? 'edit' : 'add';
        if (mode === 'edit') {
            if (label) label.textContent = 'Edit Vendor';
            if (saveBtn) saveBtn.textContent = 'Update';
        } else {
            if (label) label.textContent = 'Add Vendor';
            if (saveBtn) saveBtn.textContent = 'Save';
        }
    }

    function getSelectedVendorId() {
        const select = document.getElementById('productVendorSelect') || document.querySelector('select[name="vendor_id"]');
        if (!select) return 0;
        return parseInt($(select).val() || select.value || '0', 10) || 0;
    }

    function fillVendorForm(c) {
        if (!c) return;
        const hiddenId = document.getElementById('ledgerCustomerId');
        if (hiddenId) hiddenId.value = c.id || '';
        function setVal(id, val) {
            const el = document.getElementById(id);
            if (el != null) el.value = val != null && val !== undefined ? val : '';
        }
        setVal('ledgerName', c.name || '');
        setVal('ledgerAlternateName', c.alternate_name || '');
        setVal('ledgerFirstName', c.first_name || '');
        setVal('ledgerLastName', c.last_name || '');
        setVal('mobileCountryCode', c.mobile_country_code || '91');
        setVal('ledgerMobileNo', c.mobile_no || '');
        setVal('phoneCountryCode', c.phone_country_code || '91');
        setVal('ledgerPhoneNo', c.phone_no || '');
        setVal('ledgerMailId', c.mail_id || '');
        setVal('ledgerIdentityNo', c.identity_no || '');
        setVal('ledgerNationalId', c.national_id || '');
        setVal('ledgerTradeNo', c.trade_no || '');
        setVal('identityIssueDate', c.identity_issue_date || '');
        setVal('identityExpiryDate', c.identity_expiry_date || '');
        setVal('specialDay', c.special_day || '');
        setVal('customerType', c.customer_type_id || '');
        setVal('registrationNo', c.registration_no || '');
        setVal('registrationDate', c.registration_date || '');
        setVal('ledgerGstin', c.gstin || '');
        setVal('nationality', c.nationality_id || '');
        setVal('country', c.country_id || '');
        setVal('ledgerGroup', c.group_id || '');
        setVal('ledgerSundryDebtors', c.sundry_debtors_id || '');
        setVal('billingAddress1', c.billing_address1 || '');
        setVal('billingAddress2', c.billing_address2 || '');
        setVal('billingCountry', c.billing_country || '');
        setVal('billingState', c.billing_state || '');
        setVal('billingCity', c.billing_city || '');
        setVal('billingZipCode', c.billing_zip_code || '');
        setVal('shippingAddress1', c.shipping_address1 || '');
        setVal('shippingAddress2', c.shipping_address2 || '');
        setVal('shippingCountry', c.shipping_country || '');
        setVal('shippingState', c.shipping_state || '');
        setVal('shippingCity', c.shipping_city || '');
        setVal('shippingZipCode', c.shipping_zip_code || '');
        setVal('bankAccountNo', c.bank_account_no || '');
        setVal('bankName', c.bank_name || '');
        setVal('bankIfscCode', c.bank_ifsc_code || '');
        setVal('bankBranch', c.bank_branch || '');
        setVal('ledgerNotes', c.notes || '');
        const cap = document.getElementById('ledgerNameCapital');
        if (cap) cap.checked = String(c.ledger_name_capital || '0') === '1';
        const kyc = document.getElementById('ledgerKYC');
        if (kyc) kyc.checked = String(c.kyc || '0') === '1';
        const aml = document.getElementById('ledgerAML');
        if (aml) aml.checked = String(c.aml || '0') === '1';
        const btbYes = document.getElementById('billToBillYes');
        const btbNo = document.getElementById('billToBillNo');
        const btb = String(c.bill_to_bill || '0') === '1';
        if (btbYes) btbYes.checked = btb;
        if (btbNo) btbNo.checked = !btb;
        if (c.ledger_photo) {
            const prev = document.getElementById('ledgerPhotoPreview');
            const img = document.getElementById('ledgerPhotoImg');
            if (prev && img) {
                prev.style.display = 'block';
                img.src = c.ledger_photo;
            }
        }
        if (typeof window.prefillLedgerHeaderAsync === 'function') {
            window.prefillLedgerHeaderAsync(c);
        }
        try {
            if (typeof window.fillCustomerShareHolderDocumentsFromCustomer === 'function') {
                window.fillCustomerShareHolderDocumentsFromCustomer(c);
            }
        } catch (e) {
            console.error('Share holder documents prefill failed', e);
        }
    }

    function showVendorModal() {
        const $modal = $('#customerCreationModal');
        $modal.appendTo('body');
        $modal.modal({
            backdrop: true,
            keyboard: true,
            show: true
        });
    }

    function openVendorModalForAdd() {
        clearCustomerForm();
        setCustomerModalMode('add');
        applyDefaultLedgerCustomerType('supplier');
        selectOptionByLabel(document.getElementById('ledgerGroup'), 'Sundry Creditors');
        selectOptionByLabel(document.getElementById('ledgerSundryDebtors'), 'Sundry Creditors');
        showVendorModal();
    }

    function openVendorModalForEdit(vendorId) {
        vendorId = parseInt(vendorId, 10) || 0;
        if (vendorId <= 0) {
            openVendorModalForAdd();
            return;
        }
        clearCustomerForm();
        const hiddenId = document.getElementById('ledgerCustomerId');
        if (hiddenId) hiddenId.value = String(vendorId);
        setCustomerModalMode('edit');
        showVendorModal();
        fetch('ajax/get-customer.php?customer_id=' + encodeURIComponent(vendorId), { method: 'GET' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data || !(data.status === 'success' || data.success === true) || !data.customer) {
                    alert('Failed to load vendor details');
                    return;
                }
                fillVendorForm(data.customer);
            })
            .catch(function (err) {
                console.error(err);
                alert('Error loading vendor details');
            });
    }

    function updatePoCategoryDropdown(categoryId, categoryName) {
        const select = document.querySelector('select[name="category_id"]');
        if (!select) return;
        let exists = false;
        for (let i = 0; i < select.options.length; i++) {
            if (String(select.options[i].value) === String(categoryId)) {
                exists = true;
                break;
            }
        }
        if (!exists) {
            const opt = document.createElement('option');
            opt.value = categoryId;
            opt.textContent = categoryName;
            select.appendChild(opt);
        }
        select.value = String(categoryId);
    }

    function updatePoVendorDropdown(vendorId, vendorName) {
        const select = document.getElementById('productVendorSelect') || document.querySelector('select[name="vendor_id"]');
        if (!select) return;
        const $select = $(select);
        const idStr = String(vendorId);
        if ($select.find('option[value="' + idStr.replace(/"/g, '\\"') + '"]').length === 0) {
            const opt = new Option(vendorName, vendorId, true, true);
            $select.append(opt);
        }
        $select.val(idStr).trigger('change');
    }

    function initProductVendorSelect2() {
        const $sel = $('#productVendorSelect');
        if (!$sel.length || $sel.prop('disabled')) {
            return;
        }
        if ($sel.hasClass('select2-hidden-accessible')) {
            $sel.select2('destroy');
        }
        $sel.select2({
            placeholder: 'Select Vendor',
            allowClear: true,
            width: '100%',
            minimumResultsForSearch: 0,
            dropdownCssClass: 'po-vendor-select2-dropdown',
            containerCssClass: 'po-vendor-select2-dropdown',
            language: {
                noResults: function () { return 'No vendor found'; },
                searching: function () { return 'Searching...'; }
            }
        });
    }

    window.initProductVendorSelect2 = initProductVendorSelect2;
    window.updatePoVendorDropdown = updatePoVendorDropdown;

    function clearCategoryForm() {
        const form = document.getElementById('categoryCreationForm');
        if (form) form.reset();
        const active = document.getElementById('categoryIsActive');
        if (active) active.checked = true;
    }

    function loadParentCategories() {
        fetch('ajax/category.php?action=list')
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.status !== 'success' || !data.categories) return;
                const parentSelect = document.getElementById('categoryParentId');
                if (!parentSelect) return;
                parentSelect.innerHTML = '';
                const noneOpt = document.createElement('option');
                noneOpt.value = '0';
                noneOpt.textContent = 'None';
                parentSelect.appendChild(noneOpt);
                data.categories.forEach(function (cat) {
                    const opt = document.createElement('option');
                    opt.value = cat.id;
                    opt.textContent = cat.name;
                    parentSelect.appendChild(opt);
                });
            })
            .catch(function (err) {
                console.error('Error loading parent categories:', err);
            });
    }

    window.clearCustomerForm = clearCustomerForm;
    window.openVendorModalForAdd = openVendorModalForAdd;
    window.openVendorModalForEdit = openVendorModalForEdit;
    window.openCustomerModalForEdit = openVendorModalForEdit;
    window.clearCategoryForm = clearCategoryForm;
    window.loadParentCategories = loadParentCategories;

    window.saveCategory = function () {
        const nameEl = document.getElementById('categoryName');
        const name = nameEl ? nameEl.value.trim() : '';
        if (!name) {
            alert('Category name is required');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('name', name);
        formData.append('short_code', (document.getElementById('categoryShortCode') || {}).value || '');
        formData.append('parent_id', (document.getElementById('categoryParentId') || {}).value || 0);
        formData.append('min_qty', (document.getElementById('categoryMinQty') || {}).value || 0);
        formData.append('max_qty', (document.getElementById('categoryMaxQty') || {}).value || 0);
        formData.append('min_wt', (document.getElementById('categoryMinWt') || {}).value || 0);
        formData.append('max_wt', (document.getElementById('categoryMaxWt') || {}).value || 0);
        formData.append('is_active', document.getElementById('categoryIsActive') && document.getElementById('categoryIsActive').checked ? 1 : 0);

        const saveBtn = event && event.target ? event.target : null;
        const originalText = saveBtn ? saveBtn.innerHTML : '';
        if (saveBtn) {
            saveBtn.innerHTML = 'Saving...';
            saveBtn.disabled = true;
        }

        fetch('ajax/category.php', { method: 'POST', body: formData })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.status === 'success') {
                    alert(data.message || 'Category added successfully!');
                    $('#categoryCreationModal').modal('hide');
                    clearCategoryForm();
                    updatePoCategoryDropdown(data.id, data.name);
                } else {
                    alert('Error: ' + (data.message || 'Failed to create category'));
                }
            })
            .catch(function (error) {
                console.error('Error:', error);
                alert('Error saving category: ' + error.message);
            })
            .finally(function () {
                if (saveBtn) {
                    saveBtn.innerHTML = originalText;
                    saveBtn.disabled = false;
                }
            });
    };

    window.saveCustomer = function () {
        const form = document.getElementById('customerCreationForm');
        if (!form || !form.checkValidity()) {
            if (form) form.reportValidity();
            return;
        }

        const mobileNo = (document.getElementById('ledgerMobileNo') || {}).value || '';
        if (!String(mobileNo).trim()) {
            alert('Mobile number is required');
            document.getElementById('ledgerMobileNo').focus();
            return;
        }

        const ledgerIdEl = document.getElementById('ledgerCustomerId');
        const isNewCustomer = !ledgerIdEl || !String(ledgerIdEl.value || '').trim();
        const customerTypeEl = document.getElementById('customerType');
        if (isNewCustomer && customerTypeEl && !String(customerTypeEl.value || '').trim()) {
            alert('Customer type is required');
            customerTypeEl.focus();
            return;
        }

        const formData = new FormData(form);
        const saveBtn = event && event.target ? event.target : null;
        const originalText = saveBtn ? saveBtn.innerHTML : '';
        if (saveBtn) {
            saveBtn.innerHTML = 'Saving...';
            saveBtn.disabled = true;
        }

        fetch('customer-save.php', { method: 'POST', body: formData })
            .then(function (response) {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.text().then(function (text) {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('JSON parse error:', text);
                        throw new Error('Invalid JSON response from server');
                    }
                });
            })
            .then(function (data) {
                if (data.status === 'success' || data.success === true) {
                    alert(data.message || ((document.getElementById('ledgerCustomerId') || {}).value ? 'Vendor updated successfully' : 'Record or Account Created Successfully'));
                    $('#customerCreationModal').modal('hide');
                    if (data.customer_id && data.customer_name) {
                        updatePoVendorDropdown(data.customer_id, data.customer_name);
                    }
                    clearCustomerForm();
                } else {
                    alert('Error: ' + (data.message || 'Failed to save vendor'));
                }
            })
            .catch(function (error) {
                console.error('Error:', error);
                alert('Error saving vendor: ' + error.message);
            })
            .finally(function () {
                if (saveBtn) {
                    saveBtn.innerHTML = originalText;
                    saveBtn.disabled = false;
                }
            });
    };

    $(document).ready(function () {
        initProductVendorSelect2();

        $(document).on('click', '.po-add-category', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $('#categoryCreationModal').modal('show');
            loadParentCategories();
        });

        $(document).on('click', '.po-add-vendor', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const vendorId = getSelectedVendorId();
            if (vendorId > 0) {
                openVendorModalForEdit(vendorId);
            } else {
                openVendorModalForAdd();
            }
        });

        $(document).on('change', '#ledgerNameCapital', function () {
            const nameField = document.getElementById('ledgerName');
            if (nameField && this.checked) {
                nameField.value = nameField.value.toUpperCase();
            }
        });

        $(document).on('input', '#ledgerName', function () {
            const capitalCheckbox = document.getElementById('ledgerNameCapital');
            if (capitalCheckbox && capitalCheckbox.checked) {
                this.value = this.value.toUpperCase();
            }
        });
    });
})(jQuery);
