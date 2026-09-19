/**
 * Share Holders gear → manage Document Types (tbl_document_types via ajax/document-type.php).
 */
(function (window) {
    'use strict';

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function getTypes() {
        if (!Array.isArray(window.ledgerDocumentTypes)) {
            window.ledgerDocumentTypes = [];
        }
        return window.ledgerDocumentTypes;
    }

    function sortTypes() {
        getTypes().sort(function (a, b) {
            return String(a.name || '').localeCompare(String(b.name || ''), undefined, { sensitivity: 'base' });
        });
    }

    function buildOptionsHtml(selectedId) {
        var opts = '<option value="">Select document type</option>';
        getTypes().forEach(function (dt) {
            var sel =
                selectedId != null && String(selectedId) !== '' && String(dt.id) === String(selectedId)
                    ? ' selected'
                    : '';
            opts +=
                '<option value="' +
                escapeHtml(dt.id) +
                '"' +
                sel +
                '>' +
                escapeHtml(dt.name) +
                '</option>';
        });
        return opts;
    }

    function refreshShareHolderDocTypeSelects() {
        var selects = document.querySelectorAll('select.share-holder-doc-type');
        Array.prototype.forEach.call(selects, function (sel) {
            var cur = sel.value;
            sel.innerHTML = buildOptionsHtml(cur);
            if (cur) {
                sel.value = cur;
            }
        });
    }

    function renderSettingsTable() {
        var tbody = document.getElementById('ledgerDtSettingsTableBody');
        if (!tbody) return;
        var types = getTypes();
        if (!types.length) {
            tbody.innerHTML =
                '<tr><td colspan="2" class="text-center text-muted" style="padding:0.75rem;">No document types yet.</td></tr>';
            return;
        }
        var html = '';
        types.forEach(function (dt) {
            var id = dt.id;
            var name = dt.name || '';
            html +=
                '<tr data-id="' +
                escapeHtml(id) +
                '">' +
                '<td style="padding:0.45rem 0.65rem;vertical-align:middle;">' +
                escapeHtml(name) +
                '</td>' +
                '<td class="text-center" style="padding:0.35rem;vertical-align:middle;white-space:nowrap;">' +
                '<a href="javascript:void(0)" class="text-primary mr-2 ledger-dt-edit" data-id="' +
                escapeHtml(id) +
                '" data-name="' +
                escapeHtml(name) +
                '" title="Edit"><i class="feather icon-edit"></i></a>' +
                '<a href="javascript:void(0)" class="text-danger ledger-dt-delete" data-id="' +
                escapeHtml(id) +
                '" title="Delete"><i class="feather icon-trash-2"></i></a>' +
                '</td></tr>';
        });
        tbody.innerHTML = html;
    }

    function resetSettingsForm() {
        var idEl = document.getElementById('ledgerDtSettingsEditId');
        var nameEl = document.getElementById('ledgerDtSettingsName');
        var btn = document.getElementById('ledgerDtSettingsSaveBtn');
        if (idEl) idEl.value = '';
        if (nameEl) nameEl.value = '';
        if (btn) btn.textContent = 'Save';
    }

    function openLedgerDocumentTypeSettings() {
        resetSettingsForm();
        sortTypes();
        renderSettingsTable();
        if (typeof window.jQuery === 'undefined') {
            alert('Unable to open settings.');
            return;
        }
        var $ = window.jQuery;
        var $m = $('#ledgerDocumentTypeSettingsModal');
        if (!$m.length) {
            alert('Document type settings modal not found.');
            return;
        }
        $m.modal({ backdrop: 'static', keyboard: true, show: true });
    }

    function ajaxDocumentType(data, onOk) {
        if (typeof window.jQuery === 'undefined') return;
        window.jQuery.ajax({
            url: 'ajax/document-type.php',
            type: 'POST',
            dataType: 'json',
            data: data,
            success: function (res) {
                if (res && res.status === 'success') {
                    onOk(res);
                } else {
                    alert((res && res.message) || 'Request failed');
                }
            },
            error: function () {
                alert('Request failed');
            },
        });
    }

    function saveDocumentTypeFromSettings() {
        var idEl = document.getElementById('ledgerDtSettingsEditId');
        var nameEl = document.getElementById('ledgerDtSettingsName');
        var id = idEl ? String(idEl.value || '').trim() : '';
        var name = nameEl ? String(nameEl.value || '').trim() : '';
        if (!name) {
            alert('Document type name is required');
            if (nameEl) nameEl.focus();
            return;
        }
        ajaxDocumentType(
            {
                action: id ? 'update' : 'add',
                id: id,
                name: name,
            },
            function (res) {
                var types = getTypes();
                if (id) {
                    for (var i = 0; i < types.length; i++) {
                        if (String(types[i].id) === String(res.id || id)) {
                            types[i].name = name;
                            break;
                        }
                    }
                } else {
                    types.push({ id: res.id, name: name });
                }
                sortTypes();
                resetSettingsForm();
                renderSettingsTable();
                refreshShareHolderDocTypeSelects();
            }
        );
    }

    function editDocumentTypeInSettings(id, name) {
        var idEl = document.getElementById('ledgerDtSettingsEditId');
        var nameEl = document.getElementById('ledgerDtSettingsName');
        var btn = document.getElementById('ledgerDtSettingsSaveBtn');
        if (idEl) idEl.value = id;
        if (nameEl) {
            nameEl.value = name;
            nameEl.focus();
        }
        if (btn) btn.textContent = 'Update';
    }

    function deleteDocumentTypeFromSettings(id) {
        if (!confirm('Delete this document type?')) return;
        ajaxDocumentType({ action: 'delete', id: id }, function () {
            window.ledgerDocumentTypes = getTypes().filter(function (dt) {
                return String(dt.id) !== String(id);
            });
            resetSettingsForm();
            renderSettingsTable();
            refreshShareHolderDocTypeSelects();
        });
    }

    window.openLedgerDocumentTypeSettings = openLedgerDocumentTypeSettings;
    window.refreshShareHolderDocTypeSelects = refreshShareHolderDocTypeSelects;

    if (typeof window.jQuery !== 'undefined') {
        window.jQuery(function ($) {
            $(document).on('click', '#shareHolderDocTypeSettingsBtn, .ccm-sh-settings-btn', function (e) {
                e.preventDefault();
                e.stopPropagation();
                openLedgerDocumentTypeSettings();
            });
            $(document).on('click', '#ledgerDtSettingsSaveBtn', function (e) {
                e.preventDefault();
                saveDocumentTypeFromSettings();
            });
            $(document).on('keydown', '#ledgerDtSettingsName', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    saveDocumentTypeFromSettings();
                }
            });
            $(document).on('click', '#ledgerDtSettingsTableBody .ledger-dt-edit', function (e) {
                e.preventDefault();
                editDocumentTypeInSettings($(this).data('id'), $(this).attr('data-name') || '');
            });
            $(document).on('click', '#ledgerDtSettingsTableBody .ledger-dt-delete', function (e) {
                e.preventDefault();
                deleteDocumentTypeFromSettings($(this).data('id'));
            });
            /* Keep parent customer modal usable after nested modal closes */
            $('#ledgerDocumentTypeSettingsModal').on('hidden.bs.modal', function () {
                if ($('#customerCreationModal').hasClass('show')) {
                    $('body').addClass('modal-open');
                }
            });
        });
    }
})(window);
