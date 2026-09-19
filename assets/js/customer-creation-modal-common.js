/**
 * Customer creation modal (same behavior as sale-invoice.php).
 * Requires: jQuery, Bootstrap modal, global `nationalities` array from page.
 */
var selectedCustomerId = null;

function previewLedgerPhoto(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function (e) {
            var prev = document.getElementById('ledgerPhotoPreview');
            var img = document.getElementById('ledgerPhotoImg');
            if (prev) prev.style.display = 'block';
            if (img) img.src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function handleNameInput(input) {
    var nameValue = input.value;
    var capitalCheckbox = document.getElementById('ledgerNameCapital');
    if (capitalCheckbox && capitalCheckbox.checked) {
        input.value = nameValue.toUpperCase();
    }
    var nameParts = nameValue.trim().split(/\s+/);
    var firstNameField = document.getElementById('ledgerFirstName');
    var lastNameField = document.getElementById('ledgerLastName');
    if (nameParts.length > 0) {
        if (firstNameField) firstNameField.value = nameParts[0];
        if (nameParts.length > 1 && lastNameField) {
            lastNameField.value = nameParts[nameParts.length - 1];
        } else if (nameParts.length === 1 && lastNameField) {
            lastNameField.value = '';
        }
    }
}

var shareHolderRowIndex = 0;
var shareHoldersData = [];
/** @deprecated kept for legacy pages; new UI uses table rows + _ledgerShFile */
var shareHolderFiles = [];
var shareHolderDocumentRowSeq = 0;

function escapeHtmlLedger(s) {
    return String(s == null ? '' : s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function ledgerDocumentTypeSelectOptions(selectedId) {
    var opts = '<option value="">Select document type</option>';
    var types = Array.isArray(window.ledgerDocumentTypes) ? window.ledgerDocumentTypes : [];
    types.forEach(function (dt) {
        var sel =
            selectedId != null && String(selectedId) !== '' && String(dt.id) === String(selectedId)
                ? ' selected'
                : '';
        opts +=
            '<option value="' +
            escapeHtmlLedger(dt.id) +
            '"' +
            sel +
            '>' +
            escapeHtmlLedger(dt.name) +
            '</option>';
    });
    return opts;
}

function refreshShareHolderDocumentsEmptyHint() {
    var tb = document.getElementById('shareHolderDocumentsTableBody');
    var hint = document.getElementById('shareHolderDocumentsEmptyHint');
    if (!tb || !hint) return;
    hint.style.display = tb.querySelectorAll('tr').length ? 'none' : 'block';
}

function shareHolderDocFilenameCellHtml(nameVal, pathVal, file) {
    var label =
        nameVal ||
        (pathVal ? String(pathVal).split('/').pop() : '') ||
        (file && file.name ? file.name : '') ||
        'file';
    var href = '';
    if (pathVal) {
        href = String(pathVal).replace(/^\/+/, '');
    } else if (file && typeof URL !== 'undefined' && typeof URL.createObjectURL === 'function') {
        href = URL.createObjectURL(file);
    }
    if (href) {
        return (
            '<a class="share-holder-doc-filename" href="' +
            escapeHtmlLedger(href) +
            '" target="_blank" rel="noopener noreferrer" title="View file" ' +
            'style="color:#2563eb;text-decoration:underline;word-break:break-all;" ' +
            'onclick="event.stopPropagation();">' +
            escapeHtmlLedger(label) +
            '</a>'
        );
    }
    return (
        '<span class="share-holder-doc-filename" style="word-break:break-all;">' +
        escapeHtmlLedger(label) +
        '</span>'
    );
}

function addShareHolderDocumentUploadRow(opts) {
    opts = opts || {};
    var tbody = document.getElementById('shareHolderDocumentsTableBody');
    if (!tbody) return null;
    shareHolderDocumentRowSeq += 1;
    var rid = 'sh_doc_' + shareHolderDocumentRowSeq;
    var tr = document.createElement('tr');
    tr.className = 'share-holder-doc-row';
    tr.id = rid;
    tr.setAttribute('data-existing', opts.existing ? '1' : '0');
    var pathVal = opts.path ? String(opts.path) : '';
    var nameVal = opts.originalName ? String(opts.originalName) : '';
    var expiryVal = opts.expiry ? String(opts.expiry).substring(0, 10) : '';
    var tid = opts.documentTypeId != null && opts.documentTypeId !== '' ? opts.documentTypeId : '';

    tr.innerHTML =
        '<td style="text-align:center;vertical-align:middle;">' +
        '<button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeShareHolderDocumentUploadRow(this)" title="Remove">' +
        '<i class="feather icon-trash-2"></i></button></td>' +
        '<td><select class="form-control share-holder-doc-type" name="share_holder_doc_type_id[]" required ' +
        'style="font-size:0.8rem;padding:0.35rem;height:auto;">' +
        ledgerDocumentTypeSelectOptions(tid) +
        '</select></td>' +
        '<td style="vertical-align:middle;">' +
        shareHolderDocFilenameCellHtml(nameVal, pathVal, opts.file || null) +
        '<input type="hidden" name="share_holder_doc_existing_path[]" value="' +
        escapeHtmlLedger(pathVal) +
        '">' +
        '<input type="hidden" name="share_holder_doc_existing_name[]" value="' +
        escapeHtmlLedger(nameVal) +
        '">' +
        '</td>' +
        '<td><input type="date" class="form-control share-holder-doc-expiry" name="share_holder_doc_expiry[]" value="' +
        escapeHtmlLedger(expiryVal) +
        '" style="font-size:0.8rem;padding:0.35rem;height:auto;"></td>';

    tbody.appendChild(tr);
    if (!opts.existing && opts.file) {
        tr._ledgerShFile = opts.file;
    }
    refreshShareHolderDocumentsEmptyHint();
    return tr;
}

function removeShareHolderDocumentUploadRow(btn) {
    var tr = btn.closest('tr');
    if (tr) tr.remove();
    refreshShareHolderDocumentsEmptyHint();
}

function clearShareHolderDocumentsUi() {
    var tbody = document.getElementById('shareHolderDocumentsTableBody');
    if (tbody) tbody.innerHTML = '';
    var fi = document.getElementById('shareHolderFileInput');
    if (fi) fi.value = '';
    var legacy = document.getElementById('shareHolderFileList');
    if (legacy) legacy.innerHTML = '';
    shareHolderFiles = [];
    refreshShareHolderDocumentsEmptyHint();
}

function addShareHolderRow() {
    shareHolderRowIndex++;
    var tbody = document.getElementById('shareHoldersTableBody');
    if (!tbody) {
        alert('Share Holders table not found. Please refresh the page.');
        return;
    }
    var row = document.createElement('tr');
    row.id = 'shareHolderRow_' + shareHolderRowIndex;
    row.setAttribute('data-row-index', shareHolderRowIndex);
    var nationalityOptions = '<option value="">Select Nationality</option>';
    if (typeof nationalities !== 'undefined' && Array.isArray(nationalities)) {
        nationalities.forEach(function (nationality) {
            nationalityOptions += '<option value="' + nationality.id + '">' + nationality.name + '</option>';
        });
    }
    row.innerHTML =
        '<td><input type="text" class="form-control" name="share_holders[' + shareHolderRowIndex + '][name]" placeholder="Enter name" style="font-size: 0.85rem; padding: 0.4rem 0.6rem; height: 32px; border: 1px solid #e2e8f0;"></td>' +
        '<td><select class="form-control" name="share_holders[' + shareHolderRowIndex + '][nationality_id]" style="font-size: 0.85rem; padding: 0.4rem 0.6rem; height: 32px; border: 1px solid #e2e8f0;">' +
        nationalityOptions +
        '</select></td>' +
        '<td><input type="number" class="form-control" name="share_holders[' + shareHolderRowIndex + '][share_percentage]" placeholder="0.00" step="0.01" min="0" max="100" style="font-size: 0.85rem; padding: 0.4rem 0.6rem; height: 32px; border: 1px solid #e2e8f0; text-align: right;"></td>' +
        '<td style="text-align: center;"><button type="button" class="btn btn-sm delete-share-holder" onclick="deleteShareHolderRow(' + shareHolderRowIndex + ')" style="background: transparent; border: none; color: #ef4444; padding: 0.25rem; cursor: pointer;"><i class="feather icon-trash-2" style="font-size: 0.9rem;"></i></button></td>';
    tbody.appendChild(row);
    shareHoldersData.push({ row_index: shareHolderRowIndex, name: '', nationality_id: '', share_percentage: '' });
}

function deleteShareHolderRow(rowIndex) {
    if (!confirm('Are you sure you want to delete this share holder?')) return;
    var row = document.getElementById('shareHolderRow_' + rowIndex);
    if (row) row.remove();
    shareHoldersData = shareHoldersData.filter(function (item) {
        return item.row_index !== rowIndex;
    });
}

function sortShareHoldersTable(columnIndex) {
    var tbody = document.getElementById('shareHoldersTableBody');
    if (!tbody) return;
    var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
    rows.sort(function (a, b) {
        var aVal, bVal;
        if (columnIndex === 0) {
            aVal = (a.querySelector('input[type="text"]') && a.querySelector('input[type="text"]').value) || '';
            bVal = (b.querySelector('input[type="text"]') && b.querySelector('input[type="text"]').value) || '';
            return aVal.localeCompare(bVal);
        }
        if (columnIndex === 1) {
            var as = a.querySelector('select');
            var bs = b.querySelector('select');
            aVal = (as && as.selectedOptions[0] && as.selectedOptions[0].text) || '';
            bVal = (bs && bs.selectedOptions[0] && bs.selectedOptions[0].text) || '';
            return aVal.localeCompare(bVal);
        }
        aVal = parseFloat((a.querySelector('input[type="number"]') && a.querySelector('input[type="number"]').value) || 0);
        bVal = parseFloat((b.querySelector('input[type="number"]') && b.querySelector('input[type="number"]').value) || 0);
        return aVal - bVal;
    });
    rows.forEach(function (r) {
        tbody.appendChild(r);
    });
}

function handleShareHolderFileDrop(event) {
    event.preventDefault();
    var uploadArea = document.getElementById('shareHolderDocumentUpload');
    if (uploadArea) uploadArea.style.borderColor = '#cbd5e1';
    handleShareHolderFiles(event.dataTransfer.files);
}

function handleShareHolderFileSelect(input) {
    handleShareHolderFiles(input.files);
}

function handleShareHolderFiles(files) {
    var tbody = document.getElementById('shareHolderDocumentsTableBody');
    if (!tbody || !files || !files.length) {
        /* legacy layout */
        var fileListLegacy = document.getElementById('shareHolderFileList');
        if (!fileListLegacy || !files || !files.length) return;
        Array.prototype.forEach.call(files, function (file) {
            shareHolderFiles.push(file);
            var fileItem = document.createElement('div');
            fileItem.className = 'share-holder-file-item';
            fileItem.style.cssText =
                'display: flex; align-items: center; justify-content: space-between; padding: 0.5rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; margin-bottom: 0.5rem;';
            fileItem.innerHTML =
                '<div style="display: flex; align-items: center; gap: 0.5rem;"><i class="feather icon-file" style="color: #c5a864;"></i><span style="font-size: 0.85rem; color: #334155;">' +
                escapeHtmlLedger(file.name) +
                '</span><span style="font-size: 0.75rem; color: #94a3b8;">(' +
                (file.size / 1024).toFixed(2) +
                ' KB)</span></div><button type="button" onclick="removeShareHolderFileLegacy(this)" style="background: transparent; border: none; color: #ef4444; cursor: pointer; padding: 0.25rem;"><i class="feather icon-x" style="font-size: 0.9rem;"></i></button>';
            fileListLegacy.appendChild(fileItem);
        });
        return;
    }
    Array.prototype.forEach.call(files, function (file) {
        addShareHolderDocumentUploadRow({ existing: false, file: file, originalName: file.name });
    });
}

function removeShareHolderFileLegacy(button) {
    var fileItem = button.closest('.share-holder-file-item');
    if (!fileItem) return;
    var nameSpan = fileItem.querySelector('span');
    var fileName = nameSpan ? nameSpan.textContent.trim() : '';
    shareHolderFiles = shareHolderFiles.filter(function (file) {
        return file.name !== fileName;
    });
    fileItem.remove();
}

function removeShareHolderFile(button) {
    removeShareHolderFileLegacy(button);
}

function clearCustomerForm() {
    var form = document.getElementById('customerCreationForm');
    if (form) form.reset();
    var hiddenId = document.getElementById('ledgerCustomerId');
    if (hiddenId) hiddenId.value = '';
    var prev = document.getElementById('ledgerPhotoPreview');
    var input = document.getElementById('ledgerPhotoInput');
    if (prev) prev.style.display = 'none';
    if (input) input.value = '';
    var shareHoldersBody = document.getElementById('shareHoldersTableBody');
    if (shareHoldersBody) shareHoldersBody.innerHTML = '';
    shareHolderRowIndex = 0;
    shareHoldersData = [];
    clearShareHolderDocumentsUi();
    if (typeof window.clearNomineesUi === 'function') {
        window.clearNomineesUi();
    }
}

function setCustomerModalMode(mode) {
    var label = document.getElementById('customerCreationModalLabel');
    var saveBtn = document.getElementById('customerModalSaveBtn');
    if (mode === 'edit') {
        if (label) label.textContent = 'Edit Customer';
        if (saveBtn) saveBtn.textContent = 'Update';
    } else {
        if (label) label.textContent = 'Ledger Details';
        if (saveBtn) saveBtn.textContent = 'Save';
    }
}

function openCustomerModalForAdd() {
    clearCustomerForm();
    setCustomerModalMode('add');
    if (window.jQuery) window.jQuery('#customerCreationModal').modal('show');
}

async function openCustomerModalForEdit(customerId) {
    clearCustomerForm();
    setCustomerModalMode('edit');
    if (window.jQuery) window.jQuery('#customerCreationModal').modal('show');
    try {
        var res = await fetch('ajax/get-customer.php?customer_id=' + encodeURIComponent(customerId), { method: 'GET' });
        var data = await res.json();
        if (!data || !(data.status === 'success' || data.success === true) || !data.customer) {
            alert('Failed to load customer details');
            return;
        }
        fillCustomerForm(data.customer);
    } catch (err) {
        console.error(err);
        alert('Error loading customer details');
    }
}

function fillCustomerForm(c) {
    var hiddenId = document.getElementById('ledgerCustomerId');
    if (hiddenId) hiddenId.value = c.id || '';
    function setVal(id, val) {
        var el = document.getElementById(id);
        if (el != null) el.value = val != null && val !== undefined ? val : '';
    }
    setVal('ledgerName', c.name || '');
    setVal('ledgerAlternateName', c.alternate_name || '');
    setVal('ledgerFirstName', c.first_name || '');
    setVal('ledgerLastName', c.last_name || '');
    setVal('mobileCountryCode', c.mobile_country_code || '971');
    setVal('ledgerMobileNo', c.mobile_no || '');
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
    setVal('billingZipCode', c.billing_zip_code || '');
    setVal('shippingAddress1', c.shipping_address1 || '');
    setVal('shippingAddress2', c.shipping_address2 || '');
    setVal('shippingCountry', c.shipping_country || '');
    setVal('shippingState', c.shipping_state || '');
    setVal('shippingZipCode', c.shipping_zip_code || '');
    setVal('bankAccountNo', c.bank_account_no || '');
    setVal('bankName', c.bank_name || '');
    setVal('bankIfscCode', c.bank_ifsc_code || '');
    setVal('bankBranch', c.bank_branch || '');
    setVal('ledgerNotes', c.notes || '');
    var cap = document.getElementById('ledgerNameCapital');
    if (cap) cap.checked = String(c.ledger_name_capital || '0') === '1';
    var kyc = document.getElementById('ledgerKYC');
    if (kyc) kyc.checked = String(c.kyc || '0') === '1';
    var aml = document.getElementById('ledgerAML');
    if (aml) aml.checked = String(c.aml || '0') === '1';
    var btbYes = document.getElementById('billToBillYes');
    var btbNo = document.getElementById('billToBillNo');
    var btb = String(c.bill_to_bill || '0') === '1';
    if (btbYes) btbYes.checked = btb;
    if (btbNo) btbNo.checked = !btb;
    if (c.ledger_photo) {
        var p = document.getElementById('ledgerPhotoPreview');
        var img = document.getElementById('ledgerPhotoImg');
        if (p && img) {
            p.style.display = 'block';
            img.src = c.ledger_photo;
        }
    }
    try {
        var body = document.getElementById('shareHoldersTableBody');
        if (body) body.innerHTML = '';
        shareHolderRowIndex = 0;
        shareHoldersData = [];
        var holders = Array.isArray(c.share_holders) ? c.share_holders : [];
        holders.forEach(function (h) {
            addShareHolderRow();
            var row = document.getElementById('shareHolderRow_' + shareHolderRowIndex);
            if (row) {
                var nameInput = row.querySelector('input[type="text"]');
                var natSel = row.querySelector('select');
                var perInput = row.querySelector('input[type="number"]');
                if (nameInput) nameInput.value = h.name || '';
                if (natSel) natSel.value = h.nationality_id || '';
                if (perInput) perInput.value = h.share_percentage != null ? h.share_percentage : '';
            }
        });
    } catch (e) {
        console.error('Share holders prefill failed', e);
    }
    try {
        if (typeof window.fillCustomerShareHolderDocumentsFromCustomer === 'function') {
            window.fillCustomerShareHolderDocumentsFromCustomer(c);
        }
    } catch (e3) {
        console.error('Share holder documents prefill failed', e3);
    }
    try {
        if (typeof window.fillCustomerNomineesFromCustomer === 'function') {
            window.fillCustomerNomineesFromCustomer(c);
        }
    } catch (eNom) {
        console.error('Nominee prefill failed', eNom);
    }
    try {
        if (typeof window.auragoldApplyCustomerItemTaxFromCustomer === 'function') {
            window.auragoldApplyCustomerItemTaxFromCustomer(c);
        }
    } catch (e4) {
        console.error('Item tax prefill failed', e4);
    }
}

function saveCustomer(ev) {
    var form = (typeof window.auragoldGetCustomerCreationForm === 'function')
        ? window.auragoldGetCustomerCreationForm()
        : document.getElementById('customerCreationForm');
    if (!form) return;
    if (typeof window.auragoldValidateCustomerCreationForm === 'function') {
        var validation = window.auragoldValidateCustomerCreationForm();
        if (!validation || !validation.ok) return;
    } else if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    var formData = new FormData(form);

    var docRows = document.querySelectorAll('#shareHolderDocumentsTableBody tr.share-holder-doc-row');
    var docErr = false;
    docRows.forEach(function (row) {
        var sel = row.querySelector('.share-holder-doc-type');
        if (!sel || !String(sel.value || '').trim()) docErr = true;
    });
    if (docErr) {
        alert('Please select a document type for each document row.');
        return;
    }

    docRows.forEach(function (row) {
        if (row.getAttribute('data-existing') === '1') return;
        var f = row._ledgerShFile;
        if (f instanceof File) {
            formData.append('share_holder_documents[]', f);
        }
    });

    var saveBtn = (ev && ev.target) ? ev.target : ((typeof window.auragoldGetCustomerCreationField === 'function')
        ? window.auragoldGetCustomerCreationField('customerModalSaveBtn')
        : document.getElementById('customerModalSaveBtn'));
    var originalText = saveBtn ? saveBtn.innerHTML : '';
    if (saveBtn) {
        saveBtn.innerHTML = '<i class="feather icon-loader spin"></i> Saving...';
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
                if (typeof window.AURAGOLD_CUSTOMER_SAVE_ON_SUCCESS === 'function') {
                    try {
                        if (window.AURAGOLD_CUSTOMER_SAVE_ON_SUCCESS(data) === false) {
                            return;
                        }
                    } catch (eHook) {
                        console.error('AURAGOLD_CUSTOMER_SAVE_ON_SUCCESS', eHook);
                    }
                }
                alert(data.message || 'Customer created successfully!');
                if (window.jQuery) window.jQuery('#customerCreationModal').modal('hide');
                if (data.customer_id) {
                    window.auragoldApplySavedCustomerToPartyDropdown(data);
                }
                if (data.gstin != null && String(data.gstin).trim() !== '') {
                    var cg = document.getElementById('customerGstin');
                    if (cg) cg.value = String(data.gstin).trim().toUpperCase();
                }
                clearCustomerForm();
            } else {
                alert('Error: ' + (data.message || 'Failed to create customer'));
            }
        })
        .catch(function (error) {
            console.error('Error:', error);
            alert('Error saving customer: ' + error.message);
        })
        .finally(function () {
            if (saveBtn) {
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
            }
        });
}

(function () {
    if (typeof window.jQuery === 'undefined') return;
    var $ = window.jQuery;
    $(document).ready(function () {
        $(document).on('change', '#ledgerNameCapital', function () {
            var nameField = document.getElementById('ledgerName');
            if (nameField && this.checked) nameField.value = nameField.value.toUpperCase();
        });
        $(document).on('input', '#ledgerName', function () {
            var capitalCheckbox = document.getElementById('ledgerNameCapital');
            if (capitalCheckbox && capitalCheckbox.checked) this.value = this.value.toUpperCase();
        });
        $(document).on('click', '#addShareHolderBtn', function (e) {
            e.preventDefault();
            addShareHolderRow();
        });
        $(document).on('click', '#addCustomerBtn, .add-customer-icon', function (e) {
            e.stopPropagation();
            e.preventDefault();
            var cid = parseInt(($('#customerId').val() || selectedCustomerId || 0).toString(), 10) || 0;
            if (cid > 0) openCustomerModalForEdit(cid);
            else openCustomerModalForAdd();
        });
    });
})();
