/* Job Card Print standalone page — shared behaviour with manufacturing-process.php drawer */
(function () {
    'use strict';

    var LS_HIST = 'job_card_print_jcp_history_hidden_columns';
    var LS_SUM = 'job_card_print_jcp_summary_hidden_columns';

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.columns-panel') && !e.target.closest('.head-setting-btn')) {
            document.querySelectorAll('.columns-panel.show').forEach(function (p) {
                p.classList.remove('show');
            });
        }
    });

    function initColumnManager(config) {
        var table = document.getElementById(config.tableId);
        var panel = document.getElementById(config.panelId);
        var toggleBtn = document.querySelector(config.toggleSelector);
        var searchInput = document.getElementById(config.searchId);
        var listContainer = document.getElementById(config.listId);
        if (!table || !panel || !toggleBtn || !listContainer) return;

        function applyHiddenColumns(hiddenCols) {
            table.querySelectorAll('th[data-col], td[data-col]').forEach(function (el) {
                var col = el.getAttribute('data-col');
                if (hiddenCols.indexOf(col) >= 0) el.classList.add('col-hidden');
                else el.classList.remove('col-hidden');
            });
        }

        function readHiddenFromStorage() {
            try {
                var raw = localStorage.getItem(config.storageKey);
                return raw ? JSON.parse(raw) : [];
            } catch (e) {
                return [];
            }
        }

        function saveHiddenToStorage(hiddenCols) {
            try {
                localStorage.setItem(config.storageKey, JSON.stringify(hiddenCols));
            } catch (e2) {}
        }

        function collectHiddenFromCheckboxes() {
            var hidden = [];
            listContainer.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
                if (!cb.checked) hidden.push(cb.getAttribute('data-col'));
            });
            return hidden;
        }

        function syncCheckboxesFromHidden(hiddenCols) {
            listContainer.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
                var col = cb.getAttribute('data-col');
                cb.checked = hiddenCols.indexOf(col) === -1;
            });
        }

        function positionPanel() {
            if (config.panelPosition === 'bottom') {
                panel.style.position = 'fixed';
                panel.style.top = 'auto';
                panel.style.bottom = '0';
                panel.style.marginTop = '0';
                panel.style.marginBottom = '0';
                panel.style.borderRadius = '12px 12px 0 0';
                panel.style.maxHeight = 'min(55vh, 440px)';
                var drawer = document.getElementById('mpJobCardPrintDrawer');
                if (drawer && drawer.classList.contains('open')) {
                    var r = drawer.getBoundingClientRect();
                    panel.style.left = Math.round(r.left) + 'px';
                    panel.style.width = Math.round(r.width) + 'px';
                    panel.style.right = 'auto';
                } else {
                    panel.style.left = '0';
                    panel.style.width = '100%';
                    panel.style.right = '0';
                }
                return;
            }
            if (config.panelPosition === 'absolute') {
                panel.style.position = 'absolute';
                panel.style.left = 'auto';
                panel.style.right = '0';
                panel.style.top = '100%';
                panel.style.bottom = 'auto';
                panel.style.marginTop = '6px';
                return;
            }
            panel.style.position = 'fixed';
            var btnRect = toggleBtn.getBoundingClientRect();
            var panelWidth = panel.offsetWidth || 250;
            var panelHeight = panel.offsetHeight || 280;
            var gap = 6;
            var left = btnRect.right - panelWidth;
            var top = btnRect.bottom + gap;
            if (left < 8) left = 8;
            if (left + panelWidth > window.innerWidth - 8) {
                left = window.innerWidth - panelWidth - 8;
            }
            if (top + panelHeight > window.innerHeight - 8) {
                top = btnRect.top - panelHeight - gap;
                if (top < 8) top = 8;
            }
            panel.style.left = left + 'px';
            panel.style.top = top + 'px';
        }

        var initialHidden = readHiddenFromStorage();
        syncCheckboxesFromHidden(initialHidden);
        applyHiddenColumns(initialHidden);

        listContainer.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
            cb.addEventListener('change', function () {
                var hidden = collectHiddenFromCheckboxes();
                saveHiddenToStorage(hidden);
                applyHiddenColumns(hidden);
            });
        });

        toggleBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            document.querySelectorAll('.columns-panel.show').forEach(function (p) {
                if (p.id !== config.panelId) p.classList.remove('show');
            });
            var willShow = !panel.classList.contains('show');
            panel.classList.toggle('show');
            if (willShow) positionPanel();
        });

        var closeBtn = panel.querySelector('[data-close-panel]');
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                panel.classList.remove('show');
            });
        }

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                var term = (searchInput.value || '').toLowerCase().trim();
                listContainer.querySelectorAll('label[data-label]').forEach(function (row) {
                    var labelText = row.getAttribute('data-label') || '';
                    row.style.display = labelText.indexOf(term) >= 0 ? '' : 'none';
                });
            });
        }

        window.addEventListener('resize', function () {
            if (panel.classList.contains('show')) positionPanel();
        });
    }

    function jwqEsc(s) {
        if (s == null || s === '') return '';
        var d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }

    function jwqInputEsc(v) {
        return String(v == null ? '' : v)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function mpParseStockNumeric(val) {
        if (val == null || val === '' || val === '—') {
            return null;
        }
        var s = String(val).replace(/,/g, '').trim();
        var n = parseFloat(s);
        return isNaN(n) ? null : n;
    }

    function mpFetchManufacturingQueueRows(jobworkOrderId) {
        var url = 'ajax/mp-manufacturing-queue-table.php';
        var jid = jobworkOrderId != null ? parseInt(jobworkOrderId, 10) : 0;
        if (jid > 0) {
            url += '?jobwork_order_id=' + encodeURIComponent(String(jid));
        }
        return fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                return (data && data.ok && data.rows) ? data.rows : [];
            });
    }

    function formatMpJobCardHMS(totalSeconds) {
        var sec = parseInt(totalSeconds, 10);
        if (isNaN(sec) || sec < 0) {
            sec = 0;
        }
        var h = Math.floor(sec / 3600);
        var m = Math.floor((sec % 3600) / 60);
        var s = sec % 60;
        function z(n) { return (n < 10 ? '0' : '') + n; }
        return h + 'H ' + z(m) + 'M ' + z(s) + 'S';
    }

    window.__mpJcpSortedRows = [];
    window.__mpJcpMfgSeconds = 0;

    function mpJcpHistoryNumericKeys() {
        return ['qty', 'gross_wt', 'other_wt', 'loss_wt', 'profit_wt', 'gold_wt', 'diamond_wt', 'price', 'metal_wt', 'changed_wt'];
    }

    function mpJcpHistoryColIsNumeric(key) {
        return mpJcpHistoryNumericKeys().indexOf(key) >= 0;
    }

    function mpJcpDeptFlowText(row) {
        row = row || {};
        var df = row.department_flow != null ? String(row.department_flow).trim() : '';
        if (df !== '') {
            return df;
        }
        var parts = [];
        var st = String(row.stock_flow_type || '').trim();
        if (st) {
            parts.push(st);
        }
        var dn = String(row.department_name || '').trim();
        if (dn) {
            parts.push(dn);
        }
        var un = String(row.user_name || '').trim();
        if (un) {
            parts.push(un);
        }
        var cm = String(row.comment || '').trim();
        if (cm && parts.indexOf(cm) < 0) {
            parts.push(cm);
        }
        return parts.length ? parts.join(' · ') : '—';
    }

    function mpJcpHistoryCellRaw(row, key, mfgFallback) {
        row = row || {};
        var ev = String(row.weight_event || '').trim();
        var rec = mpParseStockNumeric(row.receive_wt);
        switch (key) {
            case 'active':
                return row.active != null ? String(row.active) : '—';
            case 'date':
                return row.date_time != null ? String(row.date_time) : '—';
            case 'sr_no':
                return row.queue_no != null ? String(row.queue_no) : '—';
            case 'description':
                return row.description != null ? String(row.description) : '—';
            case 'qty':
                return row.total_quantity != null ? String(row.total_quantity) : '—';
            case 'gross_wt':
                return row.total_wt != null ? String(row.total_wt) : '—';
            case 'other_wt':
                return row.dust_wastage_wt != null ? String(row.dust_wastage_wt) : '—';
            case 'loss_wt':
                return row.loss_wt != null ? String(row.loss_wt) : '—';
            case 'profit_wt':
                return row.profit_wt != null ? String(row.profit_wt) : '—';
            case 'gold_wt':
                return row.metal_wt != null ? String(row.metal_wt) : '—';
            case 'diamond_wt': {
                if (row.diamond_wt != null && String(row.diamond_wt).trim() !== '' && String(row.diamond_wt).trim() !== '—') {
                    return String(row.diamond_wt);
                }
                if (row.diamond_weight != null && String(row.diamond_weight).trim() !== '' && String(row.diamond_weight).trim() !== '—') {
                    return String(row.diamond_weight);
                }
                return '—';
            }
            case 'spent_time': {
                var ms = row.manufacturing_seconds != null ? parseInt(row.manufacturing_seconds, 10) : 0;
                if (!isFinite(ms) || ms < 1) {
                    ms = parseInt(mfgFallback, 10) || 0;
                }
                return formatMpJobCardHMS(ms);
            }
            case 'price':
                if (row.price != null && String(row.price).trim() !== '') {
                    return String(row.price);
                }
                return '—';
            case 'metal_wt':
                return row.purity_wt != null ? String(row.purity_wt) : '—';
            case 'dept_flow':
                return mpJcpDeptFlowText(row);
            case 'changed_wt':
                return row.total_wt != null ? String(row.total_wt) : '—';
            case 'is_add_weight':
                return ev === 'add' ? 'Yes' : 'No';
            case 'is_return_weight':
                return (rec !== null && rec > 0.000001) ? 'Yes' : 'No';
            default:
                return '—';
        }
    }

    function mpJcpParseRowDate(s) {
        if (s == null || s === '' || s === '—') {
            return 0;
        }
        var str = String(s);
        var m = str.match(/^(\d{2})-(\d{2})-(\d{4})\s+(\d{2}):(\d{2}):(\d{2})/);
        if (m) {
            return new Date(+m[3], +m[2] - 1, +m[1], +m[4], +m[5], +m[6]).getTime();
        }
        var t = Date.parse(str);
        return isNaN(t) ? 0 : t;
    }

    function mpJcpFmtIsoSlash(iso) {
        var raw = String(iso || '').trim();
        if (!raw) {
            return '—';
        }
        var p = raw.split('-');
        if (p.length === 3) {
            return p[2] + '/' + p[1] + '/' + p[0];
        }
        return raw;
    }

    function mpJcpRenderBarcode(tag) {
        var svg = document.getElementById('mpJcpBarcodeSvg');
        var blk = document.getElementById('mpJcpBarcodeBlock');
        var lbl = document.getElementById('mpJcpBarcodeText');
        if (lbl) {
            lbl.textContent = tag || '—';
        }
        if (!svg) {
            return;
        }
        while (svg.firstChild) {
            svg.removeChild(svg.firstChild);
        }
        var t = String(tag || '').trim();
        if (!t || t === '—') {
            if (blk) {
                blk.style.display = 'none';
            }
            return;
        }
        if (blk) {
            blk.style.display = '';
        }
        if (typeof JsBarcode === 'function') {
            try {
                JsBarcode(svg, t, { format: 'code128', displayValue: false, height: 44, margin: 2, width: 1.6 });
            } catch (e1) {
                svg.innerHTML = '';
            }
        }
    }

    function mpJcpBuildSummaryRows(historyRows, mfgSeconds) {
        var byDept = {};
        (historyRows || []).forEach(function (row) {
            var flowSrc = String(row.flow_source != null ? row.flow_source : '').trim();
            if (flowSrc === 'department_transfer') {
                var srcDept = String(row.src_department_name != null ? row.src_department_name : '').trim();
                var lossN = mpParseStockNumeric(row.loss_wt);
                if (srcDept && srcDept !== '—' && lossN !== null && lossN > 0.000001) {
                    if (!byDept[srcDept]) {
                        byDept[srcDept] = { issue: 0, ret: 0 };
                    }
                    byDept[srcDept].issue += lossN;
                }
                return;
            }
            var dname = String(row.department_name != null ? row.department_name : '').trim() || '—';
            if (!byDept[dname]) {
                byDept[dname] = { issue: 0, ret: 0 };
            }
            var iw = mpParseStockNumeric(row.issue_wt);
            var rw = mpParseStockNumeric(row.receive_wt);
            if (iw !== null) {
                byDept[dname].issue += iw;
            }
            if (rw !== null) {
                byDept[dname].ret += rw;
            }
        });
        var ms = parseInt(mfgSeconds, 10) || 0;
        var timeDisp = formatMpJobCardHMS(ms);
        return Object.keys(byDept).sort().map(function (k) {
            var o = byDept[k];
            return {
                department: k,
                issue_wt: o.issue,
                return_wt: o.ret,
                actual_loss: o.issue - o.ret,
                spent_time: timeDisp
            };
        });
    }

    function mpJcpReadHiddenCols() {
        try {
            var raw = localStorage.getItem(LS_HIST);
            var h = raw ? JSON.parse(raw) : [];
            return Array.isArray(h) ? h : [];
        } catch (e2) {
            return [];
        }
    }

    function mpJcpReadHiddenColsSummary() {
        try {
            var raw = localStorage.getItem(LS_SUM);
            var h = raw ? JSON.parse(raw) : [];
            return Array.isArray(h) ? h : [];
        } catch (e3) {
            return [];
        }
    }

    function mpJcpApplyHistoryColVisibility() {
        var table = document.getElementById('mpJcpHistoryTable');
        if (!table) {
            return;
        }
        var hidden = mpJcpReadHiddenCols();
        table.querySelectorAll('th[data-col], td[data-col]').forEach(function (el) {
            var col = el.getAttribute('data-col');
            if (hidden.indexOf(col) >= 0) {
                el.classList.add('col-hidden');
            } else {
                el.classList.remove('col-hidden');
            }
        });
    }

    function mpJcpApplySummaryColVisibility() {
        var table = document.getElementById('mpJcpSummaryTable');
        if (!table) {
            return;
        }
        var hidden = mpJcpReadHiddenColsSummary();
        table.querySelectorAll('th[data-col], td[data-col]').forEach(function (el) {
            var col = el.getAttribute('data-col');
            if (hidden.indexOf(col) >= 0) {
                el.classList.add('col-hidden');
            } else {
                el.classList.remove('col-hidden');
            }
        });
    }

    function mpJcpUpdateRowSelClass(tr, on) {
        if (!tr) {
            return;
        }
        tr.classList.toggle('mp-jcp-unsel', !on);
    }

    function mpJcpSyncHistorySelectAll() {
        var table = document.getElementById('mpJcpHistoryTable');
        var headCb = document.getElementById('mpJcpHistorySelectAll');
        if (!table || !headCb) {
            return;
        }
        var boxes = table.querySelectorAll('.mp-jcp-row-chk');
        var n = boxes.length;
        var c = 0;
        boxes.forEach(function (b) {
            if (b.checked) {
                c++;
            }
        });
        headCb.checked = n > 0 && c === n;
        headCb.indeterminate = c > 0 && c < n;
    }

    function mpJcpRecalcHistoryFooter(sorted, mfgFallback) {
        var table = document.getElementById('mpJcpHistoryTable');
        var tfoot = document.getElementById('mpJcpHistoryFoot');
        if (!table || !tfoot) {
            return;
        }
        tfoot.innerHTML = '';
        var cols = window.MP_JCP_HISTORY_COLUMNS || [];
        if (!sorted.length) {
            return;
        }
        var hidden = mpJcpReadHiddenCols();
        var tr = document.createElement('tr');
        var td0 = document.createElement('td');
        td0.setAttribute('data-col', '_sel');
        td0.innerHTML = '<strong>Total (selected)</strong>';
        tr.appendChild(td0);
        cols.forEach(function (c) {
            var td = document.createElement('td');
            td.setAttribute('data-col', c.key);
            if (hidden.indexOf(c.key) >= 0) {
                tr.appendChild(td);
                return;
            }
            if (mpJcpHistoryColIsNumeric(c.key)) {
                var sum = 0;
                var any = false;
                table.querySelectorAll('#mpJcpHistoryBody tr').forEach(function (r) {
                    if (r.classList.contains('mp-jcp-unsel')) {
                        return;
                    }
                    var drow = r._mpJcpDataRow;
                    if (!drow) {
                        return;
                    }
                    var raw = mpJcpHistoryCellRaw(drow, c.key, mfgFallback);
                    var n = mpParseStockNumeric(raw);
                    if (n !== null) {
                        sum += n;
                        any = true;
                    }
                });
                td.className = 'num';
                td.innerHTML = '<strong>' + jwqEsc(any ? sum.toFixed(3) : '—') + '</strong>';
            }
            tr.appendChild(td);
        });
        tfoot.appendChild(tr);
        mpJcpApplyHistoryColVisibility();
    }

    function mpJcpFillTables(historyRows, mfgSeconds) {
        var thead = document.getElementById('mpJcpHistoryHead');
        var tbody = document.getElementById('mpJcpHistoryBody');
        var tfoot = document.getElementById('mpJcpHistoryFoot');
        var shead = document.getElementById('mpJcpSummaryHead');
        var sbody = document.getElementById('mpJcpSummaryBody');
        var sfoot = document.getElementById('mpJcpSummaryFoot');
        var mfgFb = parseInt(mfgSeconds, 10) || 0;
        window.__mpJcpMfgSeconds = mfgFb;
        if (tbody) {
            tbody.innerHTML = '';
        }
        if (tfoot) {
            tfoot.innerHTML = '';
        }
        if (sbody) {
            sbody.innerHTML = '';
        }
        if (sfoot) {
            sfoot.innerHTML = '';
        }
        var cols = window.MP_JCP_HISTORY_COLUMNS || [];
        var scols = window.MP_JCP_SUMMARY_COLUMNS || [];
        if (thead) {
            var hr = document.createElement('tr');
            var th0 = document.createElement('th');
            th0.setAttribute('data-col', '_sel');
            th0.style.width = '40px';
            th0.style.textAlign = 'center';
            th0.innerHTML = '<input type="checkbox" id="mpJcpHistorySelectAll" checked title="Select all">';
            hr.appendChild(th0);
            cols.forEach(function (c) {
                var th = document.createElement('th');
                th.setAttribute('data-col', c.key);
                th.textContent = c.label;
                if (mpJcpHistoryColIsNumeric(c.key)) {
                    th.className = 'num';
                }
                hr.appendChild(th);
            });
            thead.innerHTML = '';
            thead.appendChild(hr);
        }
        if (shead) {
            var sr = document.createElement('tr');
            scols.forEach(function (c) {
                var th = document.createElement('th');
                th.setAttribute('data-col', c.key);
                th.textContent = c.label;
                if (c.key !== 'department') {
                    th.className = 'num';
                }
                sr.appendChild(th);
            });
            shead.innerHTML = '';
            shead.appendChild(sr);
        }
        var sorted = (historyRows || []).slice().sort(function (a, b) {
            return mpJcpParseRowDate(a.date_time) - mpJcpParseRowDate(b.date_time);
        });
        window.__mpJcpSortedRows = sorted;
        var colCount = 1 + cols.length;
        if (!sorted.length && tbody) {
            var emptyTr = document.createElement('tr');
            emptyTr.innerHTML = '<td colspan="' + colCount + '" style="text-align:center;color:#64748b;padding:20px;">No job queue history for this order yet.</td>';
            tbody.appendChild(emptyTr);
            mpJcpApplyHistoryColVisibility();
            mpJcpApplySummaryColVisibility();
            return;
        }
        sorted.forEach(function (row) {
            var tr = document.createElement('tr');
            tr._mpJcpDataRow = row;
            var tdChk = document.createElement('td');
            tdChk.setAttribute('data-col', '_sel');
            tdChk.style.textAlign = 'center';
            tdChk.innerHTML = '<input type="checkbox" class="mp-jcp-row-chk" checked aria-label="Select row">';
            tr.appendChild(tdChk);
            cols.forEach(function (c) {
                var td = document.createElement('td');
                td.setAttribute('data-col', c.key);
                var raw = mpJcpHistoryCellRaw(row, c.key, mfgFb);
                if (c.key === 'description') {
                    td.innerHTML = '<span class="mp-jcp-desc-link">' + jwqEsc(raw) + '</span>';
                } else if (c.key === 'dept_flow') {
                    td.innerHTML = '<span class="mp-jcp-dept-flow-txt">' + jwqEsc(raw) + '</span>';
                } else {
                    td.textContent = raw;
                }
                if (mpJcpHistoryColIsNumeric(c.key)) {
                    td.className = 'num';
                }
                tr.appendChild(td);
            });
            if (tbody) {
                tbody.appendChild(tr);
            }
        });
        mpJcpRecalcHistoryFooter(sorted, mfgFb);
        var summaries = mpJcpBuildSummaryRows(sorted, mfgFb);
        var tIss = 0;
        var tRet = 0;
        var tLoss = 0;
        summaries.forEach(function (s) {
            var tr = document.createElement('tr');
            scols.forEach(function (c) {
                var td = document.createElement('td');
                td.setAttribute('data-col', c.key);
                var val = s[c.key];
                if (c.key === 'department') {
                    td.textContent = val != null ? String(val) : '—';
                } else if (c.key === 'spent_time') {
                    td.textContent = val != null ? String(val) : '—';
                    td.className = 'num';
                } else {
                    td.className = 'num';
                    td.textContent = typeof val === 'number' ? val.toFixed(3) : '—';
                }
                tr.appendChild(td);
            });
            if (sbody) {
                sbody.appendChild(tr);
            }
            tIss += s.issue_wt;
            tRet += s.return_wt;
            tLoss += s.actual_loss;
        });
        if (sfoot && summaries.length) {
            var sf = document.createElement('tr');
            scols.forEach(function (c) {
                var td = document.createElement('td');
                td.setAttribute('data-col', c.key);
                if (c.key === 'department') {
                    td.innerHTML = '<strong>Total</strong>';
                } else if (c.key === 'spent_time') {
                    td.className = 'num';
                    td.innerHTML = '<strong>' + jwqEsc(formatMpJobCardHMS(mfgFb)) + '</strong>';
                } else {
                    td.className = 'num';
                    var tot = c.key === 'issue_wt' ? tIss : (c.key === 'return_wt' ? tRet : tLoss);
                    td.innerHTML = '<strong>' + jwqEsc(tot.toFixed(3)) + '</strong>';
                }
                sf.appendChild(td);
            });
            sfoot.appendChild(sf);
        }
        mpJcpApplyHistoryColVisibility();
        mpJcpApplySummaryColVisibility();
        mpJcpSyncHistorySelectAll();
    }

    window.__mpJcpLastRows = null;

    function mpJcpApplyCardToDrawer(card, historyRows) {
        window.__mpJcpLastCard = card || null;
        window.__mpJcpLastRows = historyRows || [];
        var cust = (card.getAttribute('data-customer-name') || '').trim();
        if (!cust) {
            var n1 = card.querySelector('.names .n1');
            cust = n1 ? n1.textContent.trim() : '—';
        }
        var elCust = document.getElementById('mpJcpCustomerName');
        if (elCust) {
            elCust.textContent = cust || '—';
        }
        var od = document.getElementById('mpJcpOrderDate');
        var dd = document.getElementById('mpJcpDueDate');
        if (od) {
            od.textContent = mpJcpFmtIsoSlash(card.getAttribute('data-order-date'));
        }
        if (dd) {
            dd.textContent = mpJcpFmtIsoSlash(card.getAttribute('data-due-date'));
        }
        var jn = (card.getAttribute('data-jobwork-no') || '').trim();
        var sn = (card.getAttribute('data-sale-order-no') || '').trim();
        var refParts = [];
        if (jn) {
            refParts.push(jn);
        }
        if (sn) {
            refParts.push(sn);
        }
        var refEl = document.getElementById('mpJcpRefNo');
        if (refEl) {
            refEl.textContent = refParts.length ? refParts.join(', ') : '—';
        }
        var tag = (card.getAttribute('data-tag-no') || '').trim();
        var tagInp = document.getElementById('mpJcpTagInput');
        if (tagInp) {
            tagInp.value = tag;
        }
        mpJcpRenderBarcode(tag);
        var mfg = parseInt(card.getAttribute('data-manufacturing-seconds') || '0', 10);
        var ts = document.getElementById('mpJcpTimeSpent');
        if (ts) {
            ts.textContent = formatMpJobCardHMS(mfg);
        }
        var imgMount = document.getElementById('mpJcpImagesMount');
        if (imgMount) {
            var im = card.querySelector('.mp-jwo-card-img');
            if (im && im.getAttribute('src')) {
                imgMount.innerHTML = '<img src="' + jwqInputEsc(im.getAttribute('src')) + '" alt="">';
            } else {
                imgMount.innerHTML = '<span class="mp-jcp-img-empty">No Images To Display!</span>';
            }
        }
        mpJcpFillTables(historyRows, mfg);
    }

    function mpJcpPopulateColumnPanelLists() {
        var hList = document.getElementById('mpJcpHistoryColumnsList');
        var sList = document.getElementById('mpJcpSummaryColumnsList');
        if (hList && !hList.getAttribute('data-populated')) {
            hList.setAttribute('data-populated', '1');
            (window.MP_JCP_HISTORY_COLUMNS || []).forEach(function (c) {
                var lab = document.createElement('label');
                lab.setAttribute('data-label', String(c.label || '').toLowerCase());
                var cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.setAttribute('data-col', c.key);
                cb.checked = true;
                var sp = document.createElement('span');
                sp.textContent = c.label;
                lab.appendChild(cb);
                lab.appendChild(sp);
                hList.appendChild(lab);
            });
        }
        if (sList && !sList.getAttribute('data-populated')) {
            sList.setAttribute('data-populated', '1');
            (window.MP_JCP_SUMMARY_COLUMNS || []).forEach(function (c) {
                var lab = document.createElement('label');
                lab.setAttribute('data-label', String(c.label || '').toLowerCase());
                var cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.setAttribute('data-col', c.key);
                cb.checked = true;
                var sp = document.createElement('span');
                sp.textContent = c.label;
                lab.appendChild(cb);
                lab.appendChild(sp);
                sList.appendChild(lab);
            });
        }
    }

    function mpJcpGetVisibleHistoryColumns() {
        var hidden = mpJcpReadHiddenCols();
        return (window.MP_JCP_HISTORY_COLUMNS || []).filter(function (c) {
            return hidden.indexOf(c.key) < 0;
        });
    }

    function mpJcpCollectSelectedDataRows() {
        var out = [];
        document.querySelectorAll('#mpJcpHistoryBody tr').forEach(function (tr) {
            if (tr.classList.contains('mp-jcp-unsel')) {
                return;
            }
            if (tr._mpJcpDataRow) {
                out.push(tr._mpJcpDataRow);
            }
        });
        return out;
    }

    function mpJcpFmtPrintHeaderDate(iso) {
        var p = String(iso || '').trim().split('-');
        if (p.length !== 3) {
            return '—';
        }
        var mo = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        var mi = parseInt(p[1], 10) - 1;
        if (mi < 0 || mi > 11) {
            return '—';
        }
        var day = parseInt(p[2], 10);
        return (day < 10 ? '0' : '') + day + '-' + mo[mi] + '-' + p[0];
    }

    function mpJcpFmtPrintRowDateTime(s) {
        var m = String(s || '').match(/^(\d{2})-(\d{2})-(\d{4})\s+(\d{2}):(\d{2})/);
        if (!m) {
            return s || '—';
        }
        var mo = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        var h = parseInt(m[4], 10);
        var ampm = h >= 12 ? 'PM' : 'AM';
        var h12 = h % 12;
        if (h12 === 0) {
            h12 = 12;
        }
        return m[1] + ' ' + mo[parseInt(m[2], 10) - 1] + ' ' + String(m[3]).slice(-2) + ' / ' + h12 + ':' + m[5] + ' ' + ampm;
    }

    function mpJcpRenderPrintSheet() {
        var shell = document.getElementById('mpJcpPrintSheet');
        if (!shell) {
            return;
        }
        var card = window.__mpJcpLastCard;
        var selected = mpJcpCollectSelectedDataRows();
        if (!selected.length && window.__mpJcpSortedRows && window.__mpJcpSortedRows.length) {
            selected = window.__mpJcpSortedRows.slice();
        }
        var mfg = window.__mpJcpMfgSeconds || 0;
        var cust = '—';
        var odIso = '';
        var ddIso = '';
        var tag = '';
        var refs = '—';
        var jn = '';
        var sn = '';
        var desc = '—';
        var designNo = '—';
        var imgSrc = '';
        if (card) {
            cust = (card.getAttribute('data-customer-name') || '').trim();
            if (!cust) {
                var n1c = card.querySelector('.names .n1');
                cust = n1c ? n1c.textContent.trim() : '—';
            }
            odIso = (card.getAttribute('data-order-date') || '').trim();
            ddIso = (card.getAttribute('data-due-date') || '').trim();
            tag = (card.getAttribute('data-tag-no') || '').trim();
            jn = (card.getAttribute('data-jobwork-no') || '').trim();
            sn = (card.getAttribute('data-sale-order-no') || '').trim();
            desc = (card.getAttribute('data-line-desc') || '').trim();
            var imc = card.querySelector('.mp-jwo-card-img');
            if (imc && imc.getAttribute('src')) {
                imgSrc = imc.getAttribute('src');
            }
        }
        var refEl = document.getElementById('mpJcpRefNo');
        if (refEl && refEl.textContent.trim() && refEl.textContent.trim() !== '—') {
            refs = refEl.textContent.trim().replace(/\s*,\s*/g, ' | ');
        } else if (jn || sn) {
            refs = [jn, sn].filter(Boolean).join(' | ');
        }
        selected = selected.slice().sort(function (a, b) {
            return mpJcpParseRowDate(a.date_time) - mpJcpParseRowDate(b.date_time);
        });
        selected.forEach(function (r) {
            var d = r.design_no != null ? String(r.design_no).trim() : '';
            if (d !== '' && d !== '—' && designNo === '—') {
                designNo = d;
            }
        });
        if (!desc || desc === '—') {
            var fr = selected[0];
            if (fr && fr.description) {
                desc = String(fr.description);
            }
        }
        var odPr = mpJcpFmtPrintHeaderDate(odIso);
        var ddPr = mpJcpFmtPrintHeaderDate(ddIso);
        var photoInner = imgSrc ? '<img src="' + jwqInputEsc(imgSrc) + '" alt="">' : '';
        var histRows = selected.map(function (row) {
            var flow = row.department_flow != null ? String(row.department_flow) : mpJcpDeptFlowText(row);
            return '<tr>'
                + '<td>' + jwqEsc(mpJcpFmtPrintRowDateTime(row.date_time)) + '</td>'
                + '<td>' + jwqEsc(row.description != null ? row.description : '—') + '</td>'
                + '<td class="jcp-flow">' + jwqEsc(flow) + '</td>'
                + '<td class="num">' + jwqEsc(row.total_quantity != null ? row.total_quantity : '—') + '</td>'
                + '<td class="num">' + jwqEsc(row.total_wt != null ? row.total_wt : '—') + '</td>'
                + '<td class="num">' + jwqEsc(row.dust_wastage_wt != null ? row.dust_wastage_wt : '—') + '</td>'
                + '<td class="num">' + jwqEsc(row.loss_wt != null ? row.loss_wt : '—') + '</td>'
                + '<td class="num">' + jwqEsc(row.profit_wt != null ? row.profit_wt : '—') + '</td>'
                + '</tr>';
        }).join('');
        if (!histRows) {
            histRows = '<tr><td colspan="8" style="text-align:center">—</td></tr>';
        }
        var summaries = mpJcpBuildSummaryRows(selected, mfg);
        var tIss = 0;
        var tRet = 0;
        var tLoss = 0;
        var sumRows = summaries.map(function (s) {
            tIss += s.issue_wt;
            tRet += s.return_wt;
            tLoss += s.actual_loss;
            return '<tr>'
                + '<td>' + jwqEsc(s.department) + '</td>'
                + '<td class="num">' + jwqEsc(s.issue_wt.toFixed(3)) + '</td>'
                + '<td class="num">' + jwqEsc(s.return_wt.toFixed(3)) + '</td>'
                + '<td class="num">' + jwqEsc(s.actual_loss.toFixed(3)) + '</td>'
                + '<td class="num">' + jwqEsc(s.spent_time != null ? s.spent_time : formatMpJobCardHMS(mfg)) + '</td>'
                + '</tr>';
        }).join('');
        var sumFoot = summaries.length
            ? '<tr><td><strong>Total</strong></td>'
                + '<td class="num"><strong>' + jwqEsc(tIss.toFixed(3)) + '</strong></td>'
                + '<td class="num"><strong>' + jwqEsc(tRet.toFixed(3)) + '</strong></td>'
                + '<td class="num"><strong>' + jwqEsc(tLoss.toFixed(3)) + '</strong></td>'
                + '<td class="num"><strong>' + jwqEsc(formatMpJobCardHMS(mfg)) + '</strong></td></tr>'
            : '';
        var tagDisp = tag ? ('#' + tag) : '—';
        shell.innerHTML = '<div class="jcp-doc">'
            + '<div class="jcp-h1">Job Card</div>'
            + '<table class="jcp-head">'
            + '<tr>'
            + '<td rowspan="3" class="jcp-photo">' + photoInner + '</td>'
            + '<td class="jcp-lbl">Customer</td>'
            + '<td colspan="3">' + jwqEsc(cust) + '</td>'
            + '<td rowspan="3" class="jcp-bc"><div class="jcp-tag-num">' + jwqEsc(tagDisp) + '</div><svg id="mpJcpPrintBarcodeSvg" xmlns="http://www.w3.org/2000/svg"></svg></td>'
            + '</tr>'
            + '<tr><td class="jcp-lbl">Date</td><td>' + jwqEsc(odPr) + '</td><td class="jcp-lbl">Due Date</td><td>' + jwqEsc(ddPr) + '</td></tr>'
            + '<tr><td class="jcp-lbl">References</td><td>' + jwqEsc(refs) + '</td><td class="jcp-lbl">Design No</td><td>' + jwqEsc(designNo) + '</td></tr>'
            + '<tr class="jcp-desc-bar"><td colspan="6"><strong>Description:</strong> ' + jwqEsc(desc) + '</td></tr>'
            + '</table>'
            + '<div class="jcp-thumbs"><div class="jcp-thumb"></div><div class="jcp-thumb"></div><div class="jcp-thumb"></div><div class="jcp-thumb"></div></div>'
            + '<table class="jcp-data"><thead><tr>'
            + '<th>Date Time</th><th>Description</th><th>Department Flow</th><th class="num">Qty</th><th class="num">Gross Wt</th><th class="num">Other Wt</th><th class="num">Diff Wt.</th><th class="num">Ceramic/Profit</th>'
            + '</tr></thead><tbody>' + histRows + '</tbody></table>'
            + '<table class="jcp-data"><thead><tr>'
            + '<th>Department</th><th class="num">Issue Weight</th><th class="num">Return Weight</th><th class="num">Actual Loss</th><th class="num">Spent Time</th>'
            + '</tr></thead><tbody>' + sumRows + '</tbody><tfoot>' + sumFoot + '</tfoot></table>'
            + '<div class="jcp-sigs">'
            + '<div><div>Quality Check By</div><div class="jcp-line"></div></div>'
            + '<div><div>Supervised By</div><div class="jcp-line"></div></div>'
            + '<div><div>Approved By</div><div class="jcp-line"></div></div>'
            + '</div></div>';
        var svg = document.getElementById('mpJcpPrintBarcodeSvg');
        if (svg && tag && typeof JsBarcode === 'function') {
            try {
                while (svg.firstChild) {
                    svg.removeChild(svg.firstChild);
                }
                JsBarcode(svg, tag, { format: 'code128', displayValue: false, height: 44, margin: 2, width: 1.5 });
            } catch (ePb) {}
        }
    }

    function jcpBuildCardFromResolve(d) {
        var el = document.createElement('div');
        el.setAttribute('data-customer-name', d.customer_name || '');
        el.setAttribute('data-order-date', d.order_date_iso || '');
        el.setAttribute('data-due-date', d.due_date_iso || '');
        el.setAttribute('data-jobwork-no', d.jobwork_no_disp || '');
        el.setAttribute('data-sale-order-no', d.sale_order_no_disp || '');
        el.setAttribute('data-tag-no', d.tag_no || '');
        el.setAttribute('data-manufacturing-seconds', String(d.manufacturing_time_seconds || 0));
        el.setAttribute('data-line-desc', d.line_description || '');
        var img = (d.image_url || '').trim();
        if (img) {
            el.innerHTML = '<img class="mp-jwo-card-img" src="' + jwqInputEsc(img) + '" alt="">';
        }
        return el;
    }

    function jcpLoadByTag() {
        var inp = document.getElementById('mpJcpTagInput');
        var tag = inp ? inp.value.trim() : '';
        if (!tag) {
            alert('Enter a tag number.');
            return;
        }
        var tbody = document.getElementById('mpJcpHistoryBody');
        var loadCols = (window.MP_JCP_HISTORY_COLUMNS || []).length + 1;
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="' + loadCols + '" style="text-align:center;color:#64748b;padding:16px;">Loading…</td></tr>';
        }
        fetch('ajax/mp-resolve-jobwork-by-tag.php?tag=' + encodeURIComponent(tag), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok || !data.jobwork_order_id) {
                    alert(data && data.message ? data.message : 'Tag not found.');
                    if (tbody) {
                        tbody.innerHTML = '<tr><td colspan="' + loadCols + '" style="text-align:center;color:#64748b;padding:20px;">No rows to show.</td></tr>';
                    }
                    return;
                }
                var card = jcpBuildCardFromResolve(data);
                var jid = parseInt(data.jobwork_order_id, 10);
                mpFetchManufacturingQueueRows(jid)
                    .then(function (rows) {
                        mpJcpApplyCardToDrawer(card, rows);
                    })
                    .catch(function () {
                        mpJcpApplyCardToDrawer(card, []);
                    });
            })
            .catch(function () {
                alert('Lookup failed.');
            });
    }

    function initJobCardPrintPageControls() {
        var histTable = document.getElementById('mpJcpHistoryTable');
        if (histTable && !histTable._mpJcpDelegated) {
            histTable._mpJcpDelegated = true;
            histTable.addEventListener('change', function (e) {
                var t = e.target;
                if (t.id === 'mpJcpHistorySelectAll') {
                    histTable.querySelectorAll('.mp-jcp-row-chk').forEach(function (c) {
                        c.checked = t.checked;
                        mpJcpUpdateRowSelClass(c.closest('tr'), c.checked);
                    });
                    mpJcpRecalcHistoryFooter(window.__mpJcpSortedRows || [], window.__mpJcpMfgSeconds || 0);
                    t.indeterminate = false;
                    return;
                }
                if (t.classList.contains('mp-jcp-row-chk')) {
                    mpJcpUpdateRowSelClass(t.closest('tr'), t.checked);
                    mpJcpSyncHistorySelectAll();
                    mpJcpRecalcHistoryFooter(window.__mpJcpSortedRows || [], window.__mpJcpMfgSeconds || 0);
                }
            });
        }
        var histColList = document.getElementById('mpJcpHistoryColumnsList');
        if (histColList && !histColList._mpJcpFooterHook) {
            histColList._mpJcpFooterHook = true;
            histColList.addEventListener('change', function () {
                setTimeout(function () {
                    mpJcpRecalcHistoryFooter(window.__mpJcpSortedRows || [], window.__mpJcpMfgSeconds || 0);
                }, 0);
            });
        }
        var printBtn = document.getElementById('mpJcpPrintBtn');
        var exportBtn = document.getElementById('mpJcpExportBtn');
        var loadBtn = document.getElementById('mpJcpLoadTagBtn');
        var tagInp = document.getElementById('mpJcpTagInput');
        if (printBtn && !printBtn._mpJcpBound) {
            printBtn._mpJcpBound = true;
            printBtn.addEventListener('click', function () {
                mpJcpRenderPrintSheet();
                setTimeout(function () {
                    window.print();
                }, 200);
            });
        }
        if (exportBtn && !exportBtn._mpJcpBound) {
            exportBtn._mpJcpBound = true;
            exportBtn.addEventListener('click', function () {
                var selected = mpJcpCollectSelectedDataRows();
                if (!selected.length) {
                    alert('Select at least one row to export.');
                    return;
                }
                var vis = mpJcpGetVisibleHistoryColumns();
                if (!vis.length) {
                    alert('Show at least one column in Job Queue History.');
                    return;
                }
                var mfg = window.__mpJcpMfgSeconds || 0;
                function escCsv(v) {
                    var s = String(v != null ? v : '');
                    if (/[",\n]/.test(s)) {
                        return '"' + s.replace(/"/g, '""') + '"';
                    }
                    return s;
                }
                var header = vis.map(function (c) {
                    return escCsv(c.label);
                });
                var lines = [header.join(',')];
                selected.forEach(function (row) {
                    var cells = vis.map(function (c) {
                        return escCsv(mpJcpHistoryCellRaw(row, c.key, mfg));
                    });
                    lines.push(cells.join(','));
                });
                var blob = new Blob(['\uFEFF' + lines.join('\n')], { type: 'text/csv;charset=utf-8' });
                var a = document.createElement('a');
                var tin = document.getElementById('mpJcpTagInput');
                var fname = 'job-queue-' + (tin && tin.value ? tin.value.replace(/\s+/g, '_') : 'export') + '.csv';
                a.href = URL.createObjectURL(blob);
                a.download = fname;
                a.click();
                URL.revokeObjectURL(a.href);
            });
        }
        if (loadBtn && !loadBtn._jcpBound) {
            loadBtn._jcpBound = true;
            loadBtn.addEventListener('click', function () {
                jcpLoadByTag();
            });
        }
        if (tagInp && !tagInp._jcpEnterBound) {
            tagInp._jcpEnterBound = true;
            tagInp.addEventListener('keydown', function (ev) {
                if (ev.key === 'Enter') {
                    ev.preventDefault();
                    jcpLoadByTag();
                }
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (!document.getElementById('mpJobCardPrintDrawer')) {
            return;
        }
        var drawer = document.getElementById('mpJobCardPrintDrawer');
        if (drawer) {
            drawer.classList.add('open');
        }
        mpJcpPopulateColumnPanelLists();
        initColumnManager({
            tableId: 'mpJcpHistoryTable',
            panelId: 'mpJcpHistoryColumnsPanel',
            toggleSelector: '#mpJobCardPrintDrawer .mp-jcp-history-cols-toggle',
            searchId: 'mpJcpHistoryColumnsSearch',
            listId: 'mpJcpHistoryColumnsList',
            storageKey: LS_HIST,
            panelPosition: 'absolute'
        });
        initColumnManager({
            tableId: 'mpJcpSummaryTable',
            panelId: 'mpJcpSummaryColumnsPanel',
            toggleSelector: '#mpJobCardPrintDrawer .mp-jcp-summary-cols-toggle',
            searchId: 'mpJcpSummaryColumnsSearch',
            listId: 'mpJcpSummaryColumnsList',
            storageKey: LS_SUM,
            panelPosition: 'absolute'
        });
        initJobCardPrintPageControls();
        try {
            var params = new URLSearchParams(window.location.search);
            var t = params.get('tag');
            if (t && document.getElementById('mpJcpTagInput')) {
                document.getElementById('mpJcpTagInput').value = t;
                jcpLoadByTag();
            }
        } catch (err) {
            console.warn('job-card-print tag param', err);
        }
    });
})();
