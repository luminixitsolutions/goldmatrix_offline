/**
 * Ledger Nominee tab — add / remove / prefill rows (nominee_data JSON on tbl_customers).
 */
(function (window) {
    'use strict';

    if (typeof window.nomineeRowIndex === 'undefined') {
        window.nomineeRowIndex = 0;
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function relationshipOptionsHtml(selected) {
        var opts = '<option value="">Select</option>';
        var list = Array.isArray(window.ledgerNomineeRelationshipOptions)
            ? window.ledgerNomineeRelationshipOptions
            : ['Spouse', 'Son', 'Daughter', 'Father', 'Mother', 'Brother', 'Sister', 'Friend', 'Other'];
        list.forEach(function (rel) {
            var sel = selected && String(selected) === String(rel) ? ' selected' : '';
            opts += '<option value="' + escapeHtml(rel) + '"' + sel + '>' + escapeHtml(rel) + '</option>';
        });
        return opts;
    }

    function refreshNomineesEmptyHint() {
        var tb = document.getElementById('nomineesTableBody');
        var hint = document.getElementById('nomineesEmptyHint');
        if (!hint) return;
        hint.style.display = tb && tb.querySelectorAll('tr').length ? 'none' : 'block';
    }

    function addNomineeRow(data) {
        data = data || {};
        var tbody = document.getElementById('nomineesTableBody');
        if (!tbody) return null;
        window.nomineeRowIndex += 1;
        var idx = window.nomineeRowIndex;
        var tr = document.createElement('tr');
        tr.id = 'nomineeRow_' + idx;
        tr.className = 'nominee-row';
        tr.setAttribute('data-row-index', String(idx));
        var inputStyle =
            'font-size:0.85rem;padding:0.4rem 0.6rem;height:32px;border:1px solid #e2e8f0;';
        tr.innerHTML =
            '<td><input type="text" class="form-control" name="nominees[' +
            idx +
            '][name]" placeholder="Nominee name" value="' +
            escapeHtml(data.name || '') +
            '" style="' +
            inputStyle +
            '"></td>' +
            '<td><select class="form-control" name="nominees[' +
            idx +
            '][relationship]" style="' +
            inputStyle +
            '">' +
            relationshipOptionsHtml(data.relationship || '') +
            '</select></td>' +
            '<td><input type="text" class="form-control" name="nominees[' +
            idx +
            '][mobile]" placeholder="Mobile" value="' +
            escapeHtml(data.mobile || '') +
            '" inputmode="numeric" style="' +
            inputStyle +
            '"></td>' +
            '<td><input type="date" class="form-control" name="nominees[' +
            idx +
            '][dob]" value="' +
            escapeHtml(data.dob ? String(data.dob).substring(0, 10) : '') +
            '" style="' +
            inputStyle +
            '"></td>' +
            '<td><input type="text" class="form-control" name="nominees[' +
            idx +
            '][address]" placeholder="Address" value="' +
            escapeHtml(data.address || '') +
            '" style="' +
            inputStyle +
            '"></td>' +
            '<td style="text-align:center;vertical-align:middle;">' +
            '<button type="button" class="btn btn-sm delete-nominee" onclick="deleteNomineeRow(' +
            idx +
            ')" title="Remove" style="background:transparent;border:none;color:#ef4444;padding:0.25rem;cursor:pointer;">' +
            '<i class="feather icon-trash-2" style="font-size:0.9rem;"></i></button></td>';
        tbody.appendChild(tr);
        refreshNomineesEmptyHint();
        return tr;
    }

    function deleteNomineeRow(rowIndex) {
        var row = document.getElementById('nomineeRow_' + rowIndex);
        if (row) row.remove();
        refreshNomineesEmptyHint();
    }

    function clearNomineesUi() {
        var tbody = document.getElementById('nomineesTableBody');
        if (tbody) tbody.innerHTML = '';
        window.nomineeRowIndex = 0;
        refreshNomineesEmptyHint();
    }

    function fillCustomerNomineesFromCustomer(c) {
        clearNomineesUi();
        var list = [];
        if (c && Array.isArray(c.nominees)) {
            list = c.nominees;
        } else if (c && c.nominee_data) {
            if (Array.isArray(c.nominee_data)) {
                list = c.nominee_data;
            } else if (typeof c.nominee_data === 'string') {
                try {
                    list = JSON.parse(c.nominee_data);
                } catch (e) {
                    list = [];
                }
            }
        }
        if (!Array.isArray(list)) list = [];
        list.forEach(function (n) {
            if (!n) return;
            addNomineeRow({
                name: n.name || '',
                relationship: n.relationship || '',
                mobile: n.mobile || n.mobile_no || '',
                dob: n.dob || n.date_of_birth || '',
                address: n.address || '',
            });
        });
        refreshNomineesEmptyHint();
    }

    window.addNomineeRow = addNomineeRow;
    window.deleteNomineeRow = deleteNomineeRow;
    window.clearNomineesUi = clearNomineesUi;
    window.fillCustomerNomineesFromCustomer = fillCustomerNomineesFromCustomer;
    window.refreshNomineesEmptyHint = refreshNomineesEmptyHint;

    if (typeof window.jQuery !== 'undefined') {
        window.jQuery(function ($) {
            $(document).on('click', '#addNomineeBtn', function (e) {
                e.preventDefault();
                e.stopPropagation();
                addNomineeRow();
            });
            refreshNomineesEmptyHint();
        });
    } else {
        document.addEventListener('DOMContentLoaded', refreshNomineesEmptyHint);
    }
})(window);
