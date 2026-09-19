/**
 * EMI RECEIVE VOUCHER renderer for investment / layaway funds.
 * Used by investment-fund-print.php (standalone print window).
 */
(function (window) {
    'use strict';

    function escapeHtml(s) {
        if (s == null) return '';
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function pad2Num(x) {
        return x < 10 ? '0' + x : String(x);
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
        return isNaN(n) ? String(val).trim() : n.toFixed(2);
    }

    function parseInstallmentCountFromRec(rec) {
        var n = rec.total_installments;
        if (n != null && String(n).trim() !== '') {
            var x = parseInt(n, 10);
            if (x > 0) return Math.min(240, x);
        }
        var d = (rec.duration || '').toString();
        var m = d.match(/(\d+)/);
        if (m) return Math.min(240, Math.max(1, parseInt(m[1], 10)));
        return 12;
    }

    function getPassbookPeriodCount(rec) {
        var t = (rec.inst_type || 'monthly').toLowerCase();
        if (t === 'lump') return 1;
        return parseInstallmentCountFromRec(rec);
    }

    function passbookScheduleDateYmd(rec, index) {
        var join = rec.joining_date;
        if (!join || String(join).trim() === '') return '';
        var t = (rec.inst_type || 'monthly').toLowerCase();
        if (t === 'weekly') {
            var d = new Date(join + 'T12:00:00');
            if (isNaN(d.getTime())) return '';
            d.setDate(d.getDate() + index * 7);
            return d.toISOString().slice(0, 10);
        }
        if (t === 'daily') {
            var d2 = new Date(join + 'T12:00:00');
            if (isNaN(d2.getTime())) return '';
            d2.setDate(d2.getDate() + index);
            return d2.toISOString().slice(0, 10);
        }
        var p = String(join).split('-');
        if (p.length !== 3) return '';
        var jy = parseInt(p[0], 10);
        var jm = parseInt(p[1], 10);
        var jd = parseInt(p[2], 10);
        if (!jy || !jm || !jd) return '';
        var total = jy * 12 + (jm - 1) + index;
        var year = Math.floor(total / 12);
        var month = total % 12;
        var lastDay = new Date(year, month + 1, 0).getDate();
        var day = Math.min(jd, lastDay);
        var mm = month + 1;
        return year + '-' + pad2Num(mm) + '-' + pad2Num(day);
    }

    function paymentTypeDisplayLabel(type) {
        var t = String(type || '').toLowerCase();
        if (t === 'cash') return 'Cash';
        if (t === 'bank') return 'Bank';
        if (t === 'cheque') return 'Cheque';
        if (t === 'upi') return 'UPI';
        if (t === 'card') return 'Card';
        if (t === 'metal-exchange') return 'M. Exch.';
        if (t === 'scrap') return 'Scrap';
        return type ? String(type) : '';
    }

    function numberToEnglishWordsInt(n) {
        n = Math.floor(Math.abs(Number(n) || 0));
        if (n === 0) return 'Zero';
        if (n >= 1000000000) return String(n);
        var ones = [
            '',
            'One',
            'Two',
            'Three',
            'Four',
            'Five',
            'Six',
            'Seven',
            'Eight',
            'Nine',
            'Ten',
            'Eleven',
            'Twelve',
            'Thirteen',
            'Fourteen',
            'Fifteen',
            'Sixteen',
            'Seventeen',
            'Eighteen',
            'Nineteen'
        ];
        var tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        function hundredsPart(num) {
            var parts = [];
            if (num >= 100) {
                parts.push(ones[Math.floor(num / 100)] + ' Hundred');
                num %= 100;
            }
            if (num >= 20) {
                parts.push(tens[Math.floor(num / 10)] + (num % 10 ? ' ' + ones[num % 10] : ''));
            } else if (num > 0) {
                parts.push(ones[num]);
            }
            return parts.join(' ').replace(/\s+/g, ' ').trim();
        }
        var chunks = [];
        if (n >= 1000000) {
            var mi = Math.floor(n / 1000000);
            chunks.push(hundredsPart(mi) + ' Million');
            n %= 1000000;
        }
        if (n >= 1000) {
            var th = Math.floor(n / 1000);
            chunks.push(hundredsPart(th) + ' Thousand');
            n %= 1000;
        }
        if (n > 0) chunks.push(hundredsPart(n));
        return chunks.join(' ').replace(/\s+/g, ' ').trim();
    }

    function amountToWordsAed(num) {
        var n = Math.floor(Math.abs(Number(num) || 0));
        return 'UAE Dirham ' + numberToEnglishWordsInt(n) + ' Only';
    }

    function formatDateUsMidnight(ymd) {
        if (!ymd || String(ymd).trim() === '') return '';
        var p = String(ymd).trim().split('-');
        if (p.length !== 3) return ymd;
        var y = parseInt(p[0], 10);
        var m = parseInt(p[1], 10);
        var d = parseInt(p[2], 10);
        if (isNaN(y) || isNaN(m) || isNaN(d)) return ymd;
        return m + '/' + d + '/' + y + ' 12:00:00 AM';
    }

    function formatDateUsMaturity(ymd) {
        if (!ymd || String(ymd).trim() === '') return '';
        var p = String(ymd).trim().split('-');
        if (p.length !== 3) return ymd;
        var y = parseInt(p[0], 10);
        var m = parseInt(p[1], 10);
        var d = parseInt(p[2], 10);
        if (isNaN(y) || isNaN(m) || isNaN(d)) return ymd;
        return m + '/' + d + '/' + y + ' 5:30:00 AM';
    }

    function formatMoney6(val) {
        if (val == null || String(val).trim() === '') return '0.000000';
        var n = parseFloat(String(val).replace(/,/g, ''));
        return isNaN(n) ? '0.000000' : n.toFixed(6);
    }

    function extractVoucherPayment(row) {
        var mode = row.pay_mode || '';
        var bank = '';
        var cardNo = '';
        var chequeNo = '';
        var chequeDt = '';
        var modeParts = [];
        if (row.payment_breakdown && Array.isArray(row.payment_breakdown) && row.payment_breakdown.length) {
            row.payment_breakdown.forEach(function (p) {
                var t = String(p.type || '').toLowerCase();
                var lab = paymentTypeDisplayLabel(p.type);
                if (lab && modeParts.indexOf(lab) === -1) modeParts.push(lab);
                if (t === 'bank') {
                    bank = (p.deposit_into || p.transaction_no || bank || '').trim() || bank;
                }
                if (t === 'card') {
                    cardNo = [p.card_no, p.transaction_no].filter(Boolean).join(' ').trim() || cardNo;
                }
                if (t === 'cheque') {
                    chequeNo = (p.transaction_no || chequeNo || '').trim() || chequeNo;
                    if (p.cheque_date) chequeDt = formatDateDDMMYYYY(p.cheque_date) || chequeDt;
                }
            });
            if (!mode && modeParts.length) mode = modeParts.join(', ');
        }
        if (!mode) mode = 'Cash';
        return { mode: mode, bank: bank, cardNo: cardNo, chequeNo: chequeNo, chequeDt: chequeDt };
    }

    function buildEmiVoucherPageHtml(rec, row, schedIx, opts) {
        opts = opts || {};
        var trn = opts.trn != null ? String(opts.trn) : '';
        var companyLegal = opts.companyLegal != null && String(opts.companyLegal).trim() !== '' ? String(opts.companyLegal) : 'Aura Gold';
        var n = getPassbookPeriodCount(rec);
        var pay = extractVoucherPayment(row);
        var amtNum = parseFloat(row.amount) || 0;
        var words = amountToWordsAed(amtNum);
        var voucherDate = formatDateUsMidnight(row.pay_date || row.entry_date || '');
        var maturityDate = formatDateUsMaturity(rec.maturity_date || '');
        var calYear = '';
        if (rec.joining_date && String(rec.joining_date).length >= 4) {
            calYear = String(rec.joining_date).slice(0, 4);
        }
        if (!calYear) {
            var sy = passbookScheduleDateYmd(rec, schedIx);
            if (sy && sy.length >= 4) calYear = sy.slice(0, 4);
        }
        var paidInstLabel = 'Paid Inst.: ' + (schedIx + 1) + '/' + n;
        var taxableVal = row.taxable != null && String(row.taxable).trim() !== '' ? formatMoney6(row.taxable) : formatMoney6(amtNum);
        var taxNum = parseFloat(row.tax);
        var taxDisp = !isNaN(taxNum) && taxNum !== 0 ? formatMoney6(row.tax) : '';
        var totalVal = formatMoney6(row.amount != null && String(row.amount).trim() !== '' ? row.amount : amtNum);
        return (
            '<div class="if-emi-voucher-page">' +
            '<div class="if-emi-voucher-header">' +
            '<div class="if-emi-trn"><strong>TRN :</strong> <span>' +
            escapeHtml(trn) +
            '</span></div>' +
            '<div class="if-emi-title">EMI RECEIVE VOUCHER</div>' +
            '</div>' +
            '<div class="if-emi-two-col">' +
            '<div class="if-emi-box">' +
            '<div class="if-emi-field-row">' +
            '<div class="if-emi-lbl">Customer Name <span class="if-emi-lbl-ar" dir="rtl">اسم العميل</span></div>' +
            '<div class="if-emi-val-inline">' +
            escapeHtml(rec.customer_name || '') +
            '</div></div>' +
            '<div class="if-emi-field-row">' +
            '<div class="if-emi-lbl">Address <span class="if-emi-lbl-ar" dir="rtl">عنوان</span></div>' +
            '<div class="if-emi-val-inline">' +
            escapeHtml(rec.address || '') +
            '</div></div>' +
            '<div class="if-emi-field-row">' +
            '<div class="if-emi-lbl">Mobile Number <span class="if-emi-lbl-ar" dir="rtl">رقم الهاتف</span></div>' +
            '<div class="if-emi-val-inline">' +
            escapeHtml(rec.contact_no || '') +
            '</div></div>' +
            '</div>' +
            '<div class="if-emi-box">' +
            '<div class="if-emi-field-row">' +
            '<div class="if-emi-lbl">Voucher No <span class="if-emi-lbl-ar" dir="rtl">رقم الفاتورة</span></div>' +
            '<div class="if-emi-val-inline">' +
            escapeHtml(rec.fund_no || '') +
            '</div></div>' +
            '<div class="if-emi-field-row">' +
            '<div class="if-emi-lbl">Date <span class="if-emi-lbl-ar" dir="rtl">تاريخ</span></div>' +
            '<div class="if-emi-val-inline">' +
            escapeHtml(voucherDate) +
            '</div></div>' +
            '<div class="if-emi-field-row">' +
            '<div class="if-emi-lbl">EMI Code <span class="if-emi-lbl-ar" dir="rtl">رمز إيمي</span></div>' +
            '<div class="if-emi-val-inline"></div></div>' +
            '<div class="if-emi-field-row">' +
            '<div class="if-emi-lbl">Date <span class="if-emi-lbl-ar" dir="rtl">تاريخ</span></div>' +
            '<div class="if-emi-val-inline">' +
            escapeHtml(maturityDate) +
            '</div></div>' +
            '<div class="if-emi-field-row">' +
            '<div class="if-emi-lbl">Sales Officer <span class="if-emi-lbl-ar" dir="rtl">موظف مبيعات</span></div>' +
            '<div class="if-emi-val-inline">' +
            escapeHtml(rec.sales_person || '') +
            '</div></div>' +
            '</div>' +
            '</div>' +
            '<table class="if-emi-pay-table"><thead><tr>' +
            '<th>Mode Of Payment<br><span dir="rtl">طريقة الدفع</span></th>' +
            '<th>Bank<br><span dir="rtl">بنك</span></th>' +
            '<th>Card No.<br><span dir="rtl">رقم البطاقة</span></th>' +
            '<th>Cheque No.<br><span dir="rtl">رقم الشيك</span></th>' +
            '<th>Cheque Date<br><span dir="rtl">التحقق من التاريخ</span></th>' +
            '<th>Amount<br><span dir="rtl">كمية</span></th>' +
            '</tr></thead><tbody><tr class="if-emi-pay-data">' +
            '<td>' +
            escapeHtml(pay.mode) +
            '</td>' +
            '<td>' +
            escapeHtml(pay.bank) +
            '</td>' +
            '<td>' +
            escapeHtml(pay.cardNo) +
            '</td>' +
            '<td>' +
            escapeHtml(pay.chequeNo) +
            '</td>' +
            '<td>' +
            escapeHtml(pay.chequeDt) +
            '</td>' +
            '<td class="if-emi-amt">' +
            escapeHtml(formatDetailMoney2(row.amount)) +
            '</td>' +
            '</tr></tbody></table>' +
            '<div class="if-emi-lower-wrap">' +
            '<div class="if-emi-lower-left">' +
            '<div class="if-emi-inwords"><strong>In Words :</strong> ' +
            escapeHtml(words) +
            '</div>' +
            '<table class="if-emi-subtable"><thead><tr>' +
            '<th>No Of Installment</th><th>Calender Code</th><th>Quantity</th><th>Amount</th>' +
            '</tr></thead><tbody><tr>' +
            '<td>' +
            escapeHtml(paidInstLabel) +
            '</td>' +
            '<td>' +
            escapeHtml(calYear) +
            '</td>' +
            '<td></td><td></td>' +
            '</tr></tbody></table>' +
            '</div>' +
            '<div class="if-emi-lower-right">' +
            '<table class="if-emi-subtable if-emi-totals"><tbody>' +
            '<tr><td><strong>Taxable Amount</strong></td><td>' +
            escapeHtml(taxableVal) +
            '</td></tr>' +
            '<tr><td><strong>Tax Amount</strong></td><td>' +
            escapeHtml(taxDisp) +
            '</td></tr>' +
            '<tr><td><strong>Total Amount</strong></td><td>' +
            escapeHtml(totalVal) +
            '</td></tr>' +
            '</tbody></table>' +
            '</div>' +
            '</div>' +
            '<div class="if-emi-footer-band">' +
            '<div class="if-emi-footer-confirm">' +
            '<span class="if-emi-confirmed">Confirmed on behalf of</span>' +
            '<span class="if-emi-company-name">' +
            escapeHtml(companyLegal) +
            '</span>' +
            '</div>' +
            '<div class="if-emi-sig-row">' +
            '<div class="if-emi-sig-cell">' +
            '<div class="if-emi-sig-line"></div>' +
            '<div class="if-emi-sig-lbl"><span dir="rtl">توقيع المتلقي</span><br>Receiver\'s Signature</div>' +
            '</div>' +
            '<div class="if-emi-sig-cell">' +
            '<div class="if-emi-sig-line"></div>' +
            '<div class="if-emi-sig-lbl"><span dir="rtl">تم الفحص بواسطة</span><br>Checked By</div>' +
            '</div>' +
            '<div class="if-emi-sig-cell">' +
            '<div class="if-emi-sig-line"></div>' +
            '<div class="if-emi-sig-lbl"><span dir="rtl">المفوض بالتوقيع</span><br>Authorised Signatory</div>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>'
        );
    }

    function renderInvestmentFundVouchersHtml(rec, items, boot) {
        boot = boot || {};
        var opts = {
            trn: boot.trn != null ? String(boot.trn) : '',
            companyLegal: boot.companyLegal != null ? String(boot.companyLegal) : ''
        };
        if (!rec || !items || !items.length) return '';
        return items
            .map(function (it) {
                return buildEmiVoucherPageHtml(rec, it.row, it.schedIx, opts);
            })
            .join('');
    }

    var STORAGE_KEY = 'auragold_if_print_payload_v1';

    function runPrintPage() {
        var boot = window.IF_PRINT_BOOT || {};
        var raw = null;
        try {
            raw = localStorage.getItem(STORAGE_KEY);
        } catch (e) {}
        var host = document.getElementById('ifPrintRoot');
        if (!host) return;

        if (!raw) {
            host.innerHTML =
                '<div class="if-print-error"><p>No print data found. Close this tab and try again from Investment Fund.</p></div>';
            return;
        }

        var data;
        try {
            data = JSON.parse(raw);
        } catch (e) {
            host.innerHTML = '<div class="if-print-error"><p>Invalid print data.</p></div>';
            return;
        }

        if (!data || !data.rec || !data.items || !data.items.length) {
            host.innerHTML = '<div class="if-print-error"><p>Nothing to print.</p></div>';
            return;
        }

        host.innerHTML = renderInvestmentFundVouchersHtml(data.rec, data.items, boot);

        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch (e2) {}

        setTimeout(function () {
            window.print();
        }, 250);
    }

    window.InvestmentFundVoucherPrint = {
        renderInvestmentFundVouchersHtml: renderInvestmentFundVouchersHtml,
        runPrintPage: runPrintPage,
        STORAGE_KEY: STORAGE_KEY
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', runPrintPage);
    } else {
        runPrintPage();
    }
})(window);
