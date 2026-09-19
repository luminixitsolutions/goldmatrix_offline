/**
 * Standalone Installment Report page — uses same localStorage key as investment-fund.php.
 */
(function () {
    var IF_FUNDS_KEY = 'auragold_investment_funds_v1';
    var ifReportSelectedParty = null;
    var ifReportSelectedFundId = null;
    var ifReportSearchTimer = null;

    function loadFundRecords() {
        try {
            var raw = localStorage.getItem(IF_FUNDS_KEY);
            if (!raw) return [];
            var a = JSON.parse(raw);
            return Array.isArray(a) ? a : [];
        } catch (e) {
            return [];
        }
    }

    function escapeHtml(s) {
        if (s == null) return '';
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function pad2Num(x) {
        return x < 10 ? '0' + x : String(x);
    }

    function formatDateDisplay(ymd) {
        if (!ymd || String(ymd).trim() === '') return '—';
        var p = String(ymd).split('-');
        if (p.length !== 3) return ymd;
        return p[1] + '/' + p[2] + '/' + p[0];
    }

    function formatDateDDMMYYYY(ymd) {
        if (!ymd || String(ymd).trim() === '') return '';
        var p = String(ymd).trim().split('-');
        if (p.length !== 3) return ymd;
        var d = parseInt(p[2], 10);
        var m = parseInt(p[1], 10);
        var y = p[0];
        if (isNaN(d) || isNaN(m)) return ymd;
        return pad2Num(d) + '/' + pad2Num(m) + '/' + y;
    }

    function formatDetailMoney2(val) {
        if (val == null || String(val).trim() === '') return '0.00';
        var n = parseFloat(String(val).replace(/,/g, ''));
        if (isNaN(n)) return '0.00';
        return n.toFixed(2);
    }

    function formatMoney(n) {
        if (n == null || n === '') return '—';
        var x = Number(n);
        if (isNaN(x)) return '—';
        return x.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatInstTypeDisplay(v) {
        if (!v) return '—';
        var m = { monthly: 'Monthly', weekly: 'Weekly', daily: 'Daily', lump: 'Lump sum' };
        var k = String(v).toLowerCase();
        return m[k] || v;
    }

    function extractLocation(addr) {
        if (!addr || !String(addr).trim()) return '—';
        return String(addr).split(/\n|\r/)[0].trim() || '—';
    }

    function ifReportPartyKey(r) {
        var n = String(r.customer_name || '').trim();
        return n === '' ? '—' : n;
    }

    function getInstallmentReportPartyAggregates(records) {
        var map = {};
        records.forEach(function (r) {
            var k = ifReportPartyKey(r);
            map[k] = (map[k] || 0) + 1;
        });
        return Object.keys(map)
            .sort(function (a, b) {
                return a.localeCompare(b);
            })
            .map(function (name) {
                return { name: name, count: map[name] };
            });
    }

    function updateIfReportFilterBadge() {
        var inp = document.getElementById('ifReportSearchMain');
        var bd = document.getElementById('ifReportFilterBadge');
        if (!bd) return;
        var q = inp && inp.value.trim();
        if (q) {
            bd.textContent = '1';
            bd.classList.remove('d-none');
        } else {
            bd.classList.add('d-none');
        }
    }

    function filterInstallmentReportRecords(records) {
        var party = ifReportSelectedParty;
        var q =
            (document.getElementById('ifReportSearchMain') &&
                document.getElementById('ifReportSearchMain').value.trim().toLowerCase()) ||
            '';
        return records.filter(function (r) {
            if (party != null && party !== '') {
                if (ifReportPartyKey(r) !== party) return false;
            }
            if (!q) return true;
            var hay = [
                r.customer_name,
                r.contact_no,
                r.email,
                r.scheme_label,
                r.sales_person,
                r.fund_no,
                r.redemption_on
            ]
                .map(function (x) {
                    return String(x || '').toLowerCase();
                })
                .join(' ');
            return hay.indexOf(q) !== -1;
        });
    }

    function ifReportRedemptionCell(val) {
        if (val == null || String(val).trim() === '') return '—';
        var s = String(val).trim();
        if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return formatDateDDMMYYYY(s);
        return s;
    }

    function renderInstallmentReportPartyList() {
        var records = loadFundRecords();
        var agg = getInstallmentReportPartyAggregates(records);
        var filtEl = document.getElementById('ifReportPartyFilter');
        var filt = (filtEl && filtEl.value.trim().toLowerCase()) || '';
        var host = document.getElementById('ifReportPartyBody');
        var footer = document.getElementById('ifReportPartyFooter');
        if (!host) return;
        host.innerHTML = '';
        var allTr = document.createElement('tr');
        allTr.className =
            'if-report-party-row' + (ifReportSelectedParty === null ? ' if-report-party-row--active' : '');
        allTr.setAttribute('data-party', '*');
        allTr.innerHTML =
            '<td><strong>All</strong></td><td class="text-right"><strong>' +
            records.length +
            '</strong></td>';
        host.appendChild(allTr);
        var visible = 1;
        agg.forEach(function (item) {
            if (filt && item.name.toLowerCase().indexOf(filt) === -1) return;
            var tr = document.createElement('tr');
            tr.className =
                'if-report-party-row' +
                (ifReportSelectedParty === item.name ? ' if-report-party-row--active' : '');
            tr.setAttribute('data-party', item.name);
            tr.innerHTML =
                '<td>' +
                escapeHtml(item.name) +
                '</td><td class="text-right">' +
                item.count +
                '</td>';
            host.appendChild(tr);
            visible++;
        });
        if (footer) {
            footer.textContent = 'Showing ' + visible + ' entr' + (visible === 1 ? 'y' : 'ies');
        }
    }

    function hideReportDetailCard() {
        var card = document.getElementById('ifReportDetailCard');
        if (card) card.classList.add('d-none');
        ifReportSelectedFundId = null;
        var tb = document.getElementById('ifReportMainBody');
        if (tb) {
            tb.querySelectorAll('tr.if-report-row--selected').forEach(function (row) {
                row.classList.remove('if-report-row--selected');
            });
        }
    }

    function showReportDetailCard() {
        var card = document.getElementById('ifReportDetailCard');
        if (card) card.classList.remove('d-none');
    }

    function setReportDg(id, text, forceEmpty) {
        var el = document.getElementById(id);
        if (!el) return;
        var t = text != null && String(text).trim() !== '' ? String(text) : '—';
        if (forceEmpty) t = '—';
        el.textContent = t;
        el.classList.toggle('if-report-dg-empty', t === '—');
    }

    function populateReportDetailView(rec) {
        setReportDg('ifReportDvCustomerName', rec.customer_name);
        setReportDg('ifReportDvLocation', extractLocation(rec.address));
        setReportDg('ifReportDvPhone', rec.contact_no);
        setReportDg('ifReportDvSchemeName', rec.scheme_label);
        setReportDg('ifReportDvJoiningDt', rec.joining_date ? formatDateDisplay(rec.joining_date) : null);
        setReportDg('ifReportDvMaturityDt', rec.maturity_date ? formatDateDisplay(rec.maturity_date) : null);
        setReportDg('ifReportDvAmount', formatMoney(rec.inst_amt));
        setReportDg('ifReportDvInstType', formatInstTypeDisplay(rec.inst_type));
        var dur = rec.duration;
        if (!dur || String(dur).trim() === '') {
            var tot = rec.total_installments != null ? rec.total_installments : 12;
            dur = tot + ' Months';
        }
        setReportDg('ifReportDvDuration', dur);
        var red = rec.redemption_on;
        if (!red || String(red).trim() === '') {
            red = 'Amount (24k)';
        } else if (/^\d{4}-\d{2}-\d{2}$/.test(String(red).trim())) {
            red = ifReportRedemptionCell(red);
        }
        setReportDg('ifReportDvRedemption', red);
        setReportDg('ifReportDvAdvancedPayment', null, true);
        setReportDg('ifReportDvNominee', rec.nominee_name, !rec.nominee_name);
        setReportDg('ifReportDvEmail', rec.email, !rec.email);
        setReportDg('ifReportDvContactNo', rec.contact_no, !rec.contact_no);
        setReportDg('ifReportDvRelationType', rec.relation_type, !rec.relation_type);
        setReportDg('ifReportDvNationalId', rec.national_id, !rec.national_id);
    }

    function ifReportMainCellOpensDetail(td) {
        if (!td || td.parentElement.tagName !== 'TR') return false;
        var ci = td.cellIndex;
        return ci === 0 || ci === 3;
    }

    function openInstallmentEntryFromReport(rec) {
        if (!rec) return;
        window.location.href =
            'investment-fund.php?fund_id=' + encodeURIComponent(String(rec.id));
    }

    function renderInstallmentReportMain() {
        var records = loadFundRecords().slice().sort(function (a, b) {
            return new Date(b.saved_at || 0) - new Date(a.saved_at || 0);
        });
        records = filterInstallmentReportRecords(records);
        var tb = document.getElementById('ifReportMainBody');
        var foot = document.getElementById('ifReportMainFooter');
        if (!tb) return;
        tb.innerHTML = '';
        if (!records.length) {
            tb.innerHTML =
                '<tr><td colspan="12" class="text-center text-muted py-4">No Rows To Show</td></tr>';
            if (foot) foot.textContent = 'Showing 0 to 0 of 0 entries';
            hideReportDetailCard();
            return;
        }
        records.forEach(function (r) {
            var tr = document.createElement('tr');
            tr.setAttribute('data-fund-id', r.id);
            var paid = r.paid_installments != null ? r.paid_installments : 0;
            var tot = r.total_installments != null ? r.total_installments : 0;
            tr.innerHTML =
                '<td class="if-report-link-cell">' +
                escapeHtml(r.customer_name || '—') +
                '</td><td>' +
                escapeHtml(r.contact_no || '—') +
                '</td><td>' +
                escapeHtml(r.email || '—') +
                '</td><td class="if-report-link-cell">' +
                escapeHtml(r.scheme_label || '—') +
                '</td><td>' +
                escapeHtml(r.sales_person || '—') +
                '</td><td>' +
                escapeHtml(formatInstTypeDisplay(r.inst_type)) +
                '</td><td>' +
                escapeHtml(ifReportRedemptionCell(r.redemption_on)) +
                '</td><td>' +
                escapeHtml(r.joining_date ? formatDateDDMMYYYY(r.joining_date) : '—') +
                '</td><td>' +
                escapeHtml(r.maturity_date ? formatDateDDMMYYYY(r.maturity_date) : '—') +
                '</td><td>' +
                escapeHtml(r.fund_no || '—') +
                '</td><td>' +
                escapeHtml(String(paid) + '/' + String(tot)) +
                '</td><td class="text-right">' +
                escapeHtml(formatDetailMoney2(r.inst_amt)) +
                '</td>';
            if (ifReportSelectedFundId != null && String(r.id) === String(ifReportSelectedFundId)) {
                tr.classList.add('if-report-row--selected');
            }
            tb.appendChild(tr);
        });
        if (foot) foot.textContent = 'Showing 1 to ' + records.length + ' of ' + records.length + ' entries';
        if (ifReportSelectedFundId != null) {
            var sel = records.filter(function (x) {
                return String(x.id) === String(ifReportSelectedFundId);
            })[0];
            if (!sel) {
                hideReportDetailCard();
            } else {
                var card = document.getElementById('ifReportDetailCard');
                if (card && !card.classList.contains('d-none')) {
                    populateReportDetailView(sel);
                }
            }
        }
    }

    function renderInstallmentReport() {
        updateIfReportFilterBadge();
        renderInstallmentReportPartyList();
        renderInstallmentReportMain();
    }

    function exportInstallmentReportCsv() {
        var records = filterInstallmentReportRecords(
            loadFundRecords().slice().sort(function (a, b) {
                return new Date(b.saved_at || 0) - new Date(a.saved_at || 0);
            })
        );
        var headers = [
            'Customer',
            'Mobile',
            'Email',
            'Scheme Name',
            'Sale Person',
            'Inst. Type',
            'Redemption',
            'Joining',
            'Maturity',
            'Fund No',
            'Paid',
            'Inst. Amt'
        ];
        var lines = [headers.join(',')];
        records.forEach(function (r) {
            var paid = r.paid_installments != null ? r.paid_installments : 0;
            var tot = r.total_installments != null ? r.total_installments : 0;
            var row = [
                r.customer_name,
                r.contact_no,
                r.email,
                r.scheme_label,
                r.sales_person,
                formatInstTypeDisplay(r.inst_type),
                String(r.redemption_on || ''),
                r.joining_date || '',
                r.maturity_date || '',
                r.fund_no,
                paid + '/' + tot,
                r.inst_amt != null ? r.inst_amt : ''
            ].map(function (cell) {
                var s = String(cell == null ? '' : cell).replace(/"/g, '""');
                return '"' + s + '"';
            });
            lines.push(row.join(','));
        });
        var blob = new Blob([lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'installment-report.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(a.href);
    }

    function bindEvents() {
        var ifReportPartyBodyEl = document.getElementById('ifReportPartyBody');
        if (ifReportPartyBodyEl) {
            ifReportPartyBodyEl.addEventListener('click', function (e) {
                var tr = e.target.closest('.if-report-party-row');
                if (!tr) return;
                var p = tr.getAttribute('data-party');
                ifReportSelectedParty = p === '*' ? null : p;
                hideReportDetailCard();
                renderInstallmentReport();
            });
        }
        var ifReportPartyFilterEl = document.getElementById('ifReportPartyFilter');
        if (ifReportPartyFilterEl) {
            ifReportPartyFilterEl.addEventListener('input', function () {
                renderInstallmentReportPartyList();
            });
        }
        var ifReportSearchMainEl = document.getElementById('ifReportSearchMain');
        if (ifReportSearchMainEl) {
            ifReportSearchMainEl.addEventListener('input', function () {
                if (ifReportSearchTimer) clearTimeout(ifReportSearchTimer);
                ifReportSearchTimer = setTimeout(function () {
                    renderInstallmentReport();
                }, 200);
            });
        }
        var ifReportBtnRefreshEl = document.getElementById('ifReportBtnRefresh');
        if (ifReportBtnRefreshEl) {
            ifReportBtnRefreshEl.addEventListener('click', function () {
                renderInstallmentReport();
            });
        }
        var ifReportBtnFilterEl = document.getElementById('ifReportBtnFilter');
        if (ifReportBtnFilterEl) {
            ifReportBtnFilterEl.addEventListener('click', function () {
                var s = document.getElementById('ifReportSearchMain');
                if (s) s.focus();
            });
        }
        var ifReportExportCsvEl = document.getElementById('ifReportExportCsv');
        if (ifReportExportCsvEl) {
            ifReportExportCsvEl.addEventListener('click', function (e) {
                e.preventDefault();
                exportInstallmentReportCsv();
            });
        }
        var ifReportMainBodyEl = document.getElementById('ifReportMainBody');
        if (ifReportMainBodyEl) {
            ifReportMainBodyEl.addEventListener('click', function (e) {
                var td = e.target.closest('td');
                var tr = e.target.closest('tr[data-fund-id]');
                if (!tr || !td || !ifReportMainCellOpensDetail(td)) return;
                var id = tr.getAttribute('data-fund-id');
                var list = loadFundRecords();
                var rec = list.filter(function (x) {
                    return String(x.id) === String(id);
                })[0];
                if (!rec) return;
                ifReportSelectedFundId = rec.id;
                ifReportMainBodyEl.querySelectorAll('tr[data-fund-id]').forEach(function (row) {
                    row.classList.toggle('if-report-row--selected', row === tr);
                });
                populateReportDetailView(rec);
                showReportDetailCard();
            });
            ifReportMainBodyEl.addEventListener('dblclick', function (e) {
                var tr = e.target.closest('tr[data-fund-id]');
                if (!tr) return;
                var id = tr.getAttribute('data-fund-id');
                var list = loadFundRecords();
                var rec = list.filter(function (x) {
                    return String(x.id) === String(id);
                })[0];
                if (!rec) return;
                openInstallmentEntryFromReport(rec);
            });
        }
        var ifReportBtnOpenEntryEl = document.getElementById('ifReportBtnOpenEntry');
        if (ifReportBtnOpenEntryEl) {
            ifReportBtnOpenEntryEl.addEventListener('click', function () {
                if (ifReportSelectedFundId == null) return;
                var list = loadFundRecords();
                var rec = list.filter(function (x) {
                    return String(x.id) === String(ifReportSelectedFundId);
                })[0];
                openInstallmentEntryFromReport(rec);
            });
        }
        var ifReportBtnColumnsEl = document.getElementById('ifReportBtnColumns');
        if (ifReportBtnColumnsEl) {
            ifReportBtnColumnsEl.addEventListener('click', function () {
                alert('Column settings — coming soon.');
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            bindEvents();
            renderInstallmentReport();
        });
    } else {
        bindEvents();
        renderInstallmentReport();
    }
})();
