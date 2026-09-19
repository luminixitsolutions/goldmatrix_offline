/**
 * Prefill Share Holders → uploaded documents from ajax/get-customer.php (share_holder_documents).
 * Works with or without customer-creation-modal-common.js (uses its addShareHolderDocumentUploadRow when present).
 */
(function (window) {
    'use strict';

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
        if (!tb || !hint) {
            return;
        }
        hint.style.display = tb.querySelectorAll('tr').length ? 'none' : 'block';
    }

    if (typeof window.shareHolderDocumentRowSeqPrefill === 'undefined') {
        window.shareHolderDocumentRowSeqPrefill = 0;
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

    function addShareHolderDocumentUploadRowFallback(opts) {
        opts = opts || {};
        var tbody = document.getElementById('shareHolderDocumentsTableBody');
        if (!tbody) {
            return null;
        }
        window.shareHolderDocumentRowSeqPrefill += 1;
        var rid = 'sh_doc_pf_' + window.shareHolderDocumentRowSeqPrefill;
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

    if (typeof window.removeShareHolderDocumentUploadRow !== 'function') {
        window.removeShareHolderDocumentUploadRow = function (btn) {
            var tr = btn.closest('tr');
            if (tr) {
                tr.remove();
            }
            refreshShareHolderDocumentsEmptyHint();
        };
    }

    function clearDocumentsTableOnly() {
        var tbody = document.getElementById('shareHolderDocumentsTableBody');
        if (tbody) {
            tbody.innerHTML = '';
        }
        var fi = document.getElementById('shareHolderFileInput');
        if (fi) {
            fi.value = '';
        }
        var legacy = document.getElementById('shareHolderFileList');
        if (legacy) {
            legacy.innerHTML = '';
        }
        if (typeof window.shareHolderFiles !== 'undefined' && Array.isArray(window.shareHolderFiles)) {
            window.shareHolderFiles.length = 0;
        }
        refreshShareHolderDocumentsEmptyHint();
    }

    /**
     * @param {object} c customer object from get-customer.php
     */
    function fillCustomerShareHolderDocumentsFromCustomer(c) {
        if (typeof window.clearShareHolderDocumentsUi === 'function') {
            window.clearShareHolderDocumentsUi();
        } else {
            clearDocumentsTableOnly();
        }

        var docs = [];
        if (c && Array.isArray(c.share_holder_documents)) {
            docs = c.share_holder_documents;
        } else if (c && c.share_holder_documents && typeof c.share_holder_documents === 'string') {
            try {
                docs = JSON.parse(c.share_holder_documents);
            } catch (e2) {
                docs = [];
            }
        }
        if (!Array.isArray(docs)) {
            docs = [];
        }

        var addRow =
            typeof window.addShareHolderDocumentUploadRow === 'function'
                ? window.addShareHolderDocumentUploadRow.bind(window)
                : addShareHolderDocumentUploadRowFallback;

        docs.forEach(function (d) {
            if (!d || !d.path) {
                return;
            }
            var exp = d.expiry_date != null ? String(d.expiry_date) : '';
            addRow({
                existing: true,
                path: d.path,
                originalName: d.name || d.original_name || '',
                documentTypeId: d.document_type_id != null ? d.document_type_id : '',
                expiry: exp,
            });
        });
    }

    window.fillCustomerShareHolderDocumentsFromCustomer = fillCustomerShareHolderDocumentsFromCustomer;

    /* Voucher-style pages: no global fillCustomerForm — load party + documents when opening ledger modal. */
    if (typeof window.jQuery !== 'undefined') {
        window.jQuery(document).on('shown.bs.modal', '#customerCreationModal', function () {
            if (typeof window.fillCustomerForm === 'function') {
                return;
            }
            var partyEl = document.getElementById('customerId');
            if (!partyEl || !partyEl.value) {
                return;
            }
            var cid = parseInt(String(partyEl.value), 10);
            if (!(cid > 0)) {
                return;
            }
            var ledgerEl = document.getElementById('ledgerCustomerId');
            if (ledgerEl && String(ledgerEl.value || '').trim() !== '') {
                return;
            }
            fetch('ajax/get-customer.php?customer_id=' + encodeURIComponent(String(cid)), {
                method: 'GET',
                credentials: 'same-origin',
            })
                .then(function (r) {
                    return r.json();
                })
                .then(function (data) {
                    if (!data || !(data.status === 'success' || data.success === true) || !data.customer) {
                        return;
                    }
                    var c = data.customer;
                    if (ledgerEl) {
                        ledgerEl.value = c.id || '';
                    }
                    var n = document.getElementById('ledgerName');
                    if (n && c.name) {
                        n.value = c.name;
                    }
                    fillCustomerShareHolderDocumentsFromCustomer(c);
                })
                .catch(function () {});
        });
    }
})(window);
