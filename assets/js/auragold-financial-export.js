/**
 * Client-side Excel (UTF-8 CSV) and PDF (print dialog) for Financial Statement pages.
 * Markup: <details data-fs-root="#selector" data-fs-file="basename" data-fs-title="Title" data-fs-mode="optional">
 *   <a href="#" class="fs-export-xls">Excel</a>
 *   <a href="#" class="fs-export-pdf">PDF</a>
 * </details>
 * Optional: data-fs-dynamic="tax-return" resolves tables from active tab.
 */
(function () {
    'use strict';

    function cellText(el) {
        if (!el) return '';
        return el.innerText.replace(/\r/g, ' ').replace(/\n/g, ' ').replace(/\s+/g, ' ').trim();
    }

    function csvEscape(s) {
        s = s == null ? '' : String(s);
        if (/[",\n]/.test(s)) {
            return '"' + s.replace(/"/g, '""') + '"';
        }
        return s;
    }

    function tableToCsv(table) {
        var lines = [];
        var trs = table.querySelectorAll('tr');
        trs.forEach(function (tr) {
            var cells = tr.querySelectorAll('th,td');
            if (!cells.length) return;
            var parts = [];
            for (var i = 0; i < cells.length; i++) {
                parts.push(csvEscape(cellText(cells[i])));
            }
            if (parts.every(function (p) { return !p || p === '""'; })) return;
            lines.push(parts.join(','));
        });
        return lines.join('\n');
    }

    function downloadCsv(csv, filename) {
        var blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
        var a = document.createElement('a');
        var url = URL.createObjectURL(blob);
        a.href = url;
        a.download = filename;
        a.style.visibility = 'hidden';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    function escHtml(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function printHtml(title, innerHtml) {
        var css =
            'body{font-family:Roboto,Arial,sans-serif;font-size:11px;margin:14px;color:#1e293b}' +
            'h1{font-size:15px;margin:0 0 10px;color:#11294b}' +
            'table{border-collapse:collapse;width:100%;margin-bottom:16px}' +
            'th,td{border:1px solid #cbd5e1;padding:5px 7px}' +
            'th{background:#11294b;color:#fff;font-weight:600;text-align:left}' +
            '.bs-num{text-align:right}';
        var doc =
            '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' +
            escHtml(title) +
            '</title><style>' +
            css +
            '</style></head><body><h1>' +
            escHtml(title) +
            '</h1>' +
            innerHtml +
            '</body></html>';
        var w = window.open('', '_blank');
        if (!w) {
            alert('Please allow pop-ups to export PDF (print).');
            return;
        }
        w.document.write(doc);
        w.document.close();
        w.focus();
        setTimeout(function () {
            w.print();
        }, 300);
    }

    function collectTables(rootSel) {
        if (!rootSel || !rootSel.trim()) return [];
        var parts = rootSel.split(',').map(function (p) { return p.trim(); }).filter(Boolean);
        var tables = [];
        parts.forEach(function (sel) {
            var el = document.querySelector(sel);
            if (!el) return;
            if (el.tagName === 'TABLE') {
                tables.push(el);
            } else {
                el.querySelectorAll('table').forEach(function (t) {
                    tables.push(t);
                });
            }
        });
        return tables;
    }

    function balanceSheetRows(root) {
        var rows = [['Section', 'Label', 'Amount']];
        if (!root) return rows;
        root.querySelectorAll('.bs-col-liability .bs-panel, .bs-col-asset .bs-panel').forEach(function (panel) {
            var sec = cellText(panel.querySelector('.bs-panel-h')) || 'Section';
            panel.querySelectorAll('.bs-line').forEach(function (line) {
                var lbl = line.querySelector('.bs-lbl');
                var val = line.querySelector('.bs-val');
                if (lbl && val) {
                    rows.push([sec, cellText(lbl), cellText(val)]);
                }
            });
        });
        return rows;
    }

    function balanceSheetToCsv(root) {
        return balanceSheetRows(root)
            .map(function (r) {
                return r.map(csvEscape).join(',');
            })
            .join('\n');
    }

    function balanceSheetToPrintHtml(root) {
        var rows = balanceSheetRows(root);
        var h = '<table><thead><tr><th>Section</th><th>Label</th><th class="bs-num">Amount</th></tr></thead><tbody>';
        for (var i = 1; i < rows.length; i++) {
            h +=
                '<tr><td>' +
                escHtml(rows[i][0]) +
                '</td><td>' +
                escHtml(rows[i][1]) +
                '</td><td class="bs-num">' +
                escHtml(rows[i][2]) +
                '</td></tr>';
        }
        h += '</tbody></table>';
        return h;
    }

    function coaRows(root) {
        var rows = [['Account / group', 'Opening', 'Debit', 'Credit', 'Closing']];
        if (!root) return rows;
        root.querySelectorAll('.coa-scroll .coa-row, .coa-foot .coa-row').forEach(function (row) {
            var n = row.querySelector('.coa-name');
            var nums = row.querySelectorAll('.coa-nums .coa-num');
            var line = [cellText(n)];
            for (var i = 0; i < 4; i++) {
                line.push(nums[i] ? cellText(nums[i]) : '');
            }
            rows.push(line);
        });
        return rows;
    }

    function coaToCsv(root) {
        return coaRows(root)
            .map(function (r) {
                return r.map(csvEscape).join(',');
            })
            .join('\n');
    }

    function coaToPrintHtml(root) {
        var data = coaRows(root);
        var h = '<table><thead><tr>';
        data[0].forEach(function (c) {
            h += '<th>' + escHtml(c) + '</th>';
        });
        h += '</tr></thead><tbody>';
        for (var i = 1; i < data.length; i++) {
            h += '<tr>';
            data[i].forEach(function (c, j) {
                h += '<td' + (j > 0 ? ' class="bs-num"' : '') + '>' + escHtml(c) + '</td>';
            });
            h += '</tr>';
        }
        h += '</tbody></table>';
        return h;
    }

    function taxReturnTables() {
        var tab = 'tax-return';
        try {
            var m = /[?&]tab=([^&]+)/.exec(window.location.search);
            if (m) tab = decodeURIComponent(m[1]);
        } catch (e) {}
        if (tab === 'planet-reconciliation') {
            var p = document.querySelector('#trPlanetLedger');
            return p ? [p] : [];
        }
        if (tab === 'input' || tab === 'output') {
            var io = document.querySelector('#trIoLedger');
            return io ? [io] : [];
        }
        var a = document.querySelector('#trTaxTable1');
        var b = document.querySelector('#trTaxTable2');
        return [a, b].filter(Boolean);
    }

    function runExcel(dd) {
        var mode = dd.getAttribute('data-fs-mode') || '';
        var dyn = dd.getAttribute('data-fs-dynamic') || '';
        var file = (dd.getAttribute('data-fs-file') || 'export').replace(/[^a-z0-9_-]+/gi, '-');
        var title = dd.getAttribute('data-fs-title') || file;
        var dateStr = new Date().toISOString().split('T')[0];
        var fname = file + '-' + dateStr + '.csv';

        if (dyn === 'tax-return') {
            var tt = taxReturnTables();
            if (!tt.length) {
                alert('No table to export on this tab.');
                return;
            }
            var csvParts = tt.map(function (t, idx) {
                return (idx ? '\n' : '') + tableToCsv(t);
            });
            downloadCsv(csvParts.join('\n'), fname);
            return;
        }

        if (mode === 'balance-sheet') {
            var bs = document.querySelector(dd.getAttribute('data-fs-root') || '#bsRoot');
            if (!bs) {
                alert('Nothing to export.');
                return;
            }
            downloadCsv(balanceSheetToCsv(bs), fname);
            return;
        }

        if (mode === 'chart-of-account') {
            var coa = document.querySelector(dd.getAttribute('data-fs-root') || '.coa-panel');
            if (!coa) {
                alert('Nothing to export.');
                return;
            }
            downloadCsv(coaToCsv(coa), fname);
            return;
        }

        var rootSel = dd.getAttribute('data-fs-root') || '';
        var tables = collectTables(rootSel);
        if (!tables.length) {
            alert('No data table found to export.');
            return;
        }
        var csvAll = tables
            .map(function (t, idx) {
                return (idx ? '\n' : '') + tableToCsv(t);
            })
            .join('\n');
        downloadCsv(csvAll, fname);
    }

    function runPdf(dd) {
        var mode = dd.getAttribute('data-fs-mode') || '';
        var dyn = dd.getAttribute('data-fs-dynamic') || '';
        var title = dd.getAttribute('data-fs-title') || 'Report';
        var html = '';

        if (dyn === 'tax-return') {
            var tt = taxReturnTables();
            if (!tt.length) {
                alert('No table to export on this tab.');
                return;
            }
            tt.forEach(function (t, idx) {
                if (idx) html += '<div style="height:14px"></div>';
                html += t.outerHTML;
            });
            printHtml(title, html);
            return;
        }

        if (mode === 'balance-sheet') {
            var bs = document.querySelector(dd.getAttribute('data-fs-root') || '#bsRoot');
            if (!bs) {
                alert('Nothing to export.');
                return;
            }
            printHtml(title, balanceSheetToPrintHtml(bs));
            return;
        }

        if (mode === 'chart-of-account') {
            var coa = document.querySelector(dd.getAttribute('data-fs-root') || '.coa-panel');
            if (!coa) {
                alert('Nothing to export.');
                return;
            }
            printHtml(title, coaToPrintHtml(coa));
            return;
        }

        var tables = collectTables(dd.getAttribute('data-fs-root') || '');
        if (!tables.length) {
            alert('No data table found to export.');
            return;
        }
        tables.forEach(function (t, idx) {
            if (idx) html += '<div style="height:14px"></div>';
            html += t.outerHTML;
        });
        printHtml(title, html);
    }

    document.addEventListener('click', function (e) {
        var xls = e.target.closest('.fs-export-xls');
        var pdf = e.target.closest('.fs-export-pdf');
        if (!xls && !pdf) return;
        e.preventDefault();
        var dd = (xls || pdf).closest('details[data-fs-root], details[data-fs-dynamic]');
        if (!dd) return;
        if (xls) runExcel(dd);
        else runPdf(dd);
        dd.removeAttribute('open');
    });
})();
