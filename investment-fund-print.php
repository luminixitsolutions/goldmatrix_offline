<?php
/**
 * Standalone EMI RECEIVE VOUCHER print page for Investment / Layaways Fund.
 * Data is passed from investment-fund.php via localStorage (auragold_if_print_payload_v1).
 */
session_start();
require_once __DIR__ . '/config.php';

$emi_voucher_company_legal = isset($Proj_Title) ? trim((string) $Proj_Title) : 'Aura Gold';
if (defined('COMPANY_LEGAL_NAME') && is_string(COMPANY_LEGAL_NAME) && trim(COMPANY_LEGAL_NAME) !== '') {
    $emi_voucher_company_legal = trim(COMPANY_LEGAL_NAME);
}
$if_trn = defined('COMPANY_TRN') ? (string) COMPANY_TRN : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EMI Receive Voucher — Print</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 12px;
            font-family: 'Times New Roman', Times, Georgia, serif;
            font-size: 11px;
            color: #000;
            background: #fff;
        }
        .if-print-toolbar {
            font-family: system-ui, sans-serif;
            font-size: 12px;
            margin-bottom: 12px;
            padding: 8px 10px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }
        .if-print-toolbar button {
            padding: 6px 12px;
            cursor: pointer;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            background: #fff;
        }
        .if-print-toolbar button:hover { background: #e2e8f0; }
        @media print {
            .if-print-toolbar { display: none !important; }
            body { padding: 0; }
        }
        .if-print-error {
            font-family: system-ui, sans-serif;
            padding: 24px;
            color: #64748b;
        }
        #ifPrintRoot {
            max-width: 210mm;
            margin: 0 auto;
        }
        .if-emi-voucher-page {
            max-width: 210mm;
            margin: 0 auto;
            padding: 10mm 12mm;
            box-sizing: border-box;
            page-break-after: always;
        }
        .if-emi-voucher-page:last-child { page-break-after: auto; }
        .if-emi-voucher-header { position: relative; margin-bottom: 10px; }
        .if-emi-trn { font-size: 11px; margin-bottom: 6px; }
        .if-emi-title {
            text-align: center;
            font-weight: 700;
            font-size: 17px;
            letter-spacing: 0.06em;
            font-family: 'Times New Roman', Times, Georgia, serif;
        }
        .if-emi-two-col { display: flex; gap: 8px; margin-bottom: 8px; }
        .if-emi-box {
            flex: 1;
            border: 1px solid #000;
            padding: 6px 8px;
            min-height: 88px;
        }
        .if-emi-field-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 6px;
            font-size: 10px;
            line-height: 1.35;
        }
        .if-emi-field-row:last-child { margin-bottom: 0; }
        .if-emi-lbl { flex: 1 1 50%; max-width: 52%; }
        .if-emi-lbl-ar { font-size: 9px; color: #222; }
        .if-emi-val-inline {
            flex: 1 1 48%;
            text-align: right;
            font-weight: 700;
            font-size: 11px;
            word-break: break-word;
        }
        .if-emi-pay-table,
        .if-emi-pay-table td,
        .if-emi-pay-table th {
            border: 1px solid #000;
            border-collapse: collapse;
        }
        .if-emi-pay-table {
            width: 100%;
            margin-bottom: 8px;
            table-layout: fixed;
        }
        .if-emi-pay-table th,
        .if-emi-pay-table td {
            padding: 5px 6px;
            vertical-align: top;
            text-align: left;
        }
        .if-emi-pay-table th {
            font-weight: 700;
            font-size: 9px;
            line-height: 1.25;
            text-align: center;
        }
        .if-emi-pay-table td.if-emi-amt { text-align: right; font-weight: 600; }
        .if-emi-pay-table tbody tr.if-emi-pay-data td { padding-bottom: 36px; }
        .if-emi-lower-wrap {
            display: flex;
            gap: 8px;
            align-items: flex-start;
            margin-bottom: 8px;
        }
        .if-emi-lower-left { flex: 1.15; min-width: 0; }
        .if-emi-lower-right { flex: 0.85; min-width: 120px; }
        .if-emi-inwords {
            border: 1px solid #000;
            padding: 6px 8px;
            margin-bottom: 8px;
            font-size: 10px;
            line-height: 1.4;
        }
        .if-emi-subtable,
        .if-emi-subtable td,
        .if-emi-subtable th {
            border: 1px solid #000;
            border-collapse: collapse;
            width: 100%;
        }
        .if-emi-subtable th,
        .if-emi-subtable td { padding: 3px 5px; font-size: 9px; }
        .if-emi-totals td { font-size: 10px; }
        .if-emi-totals td:last-child { text-align: right; font-weight: 600; }
        .if-emi-footer-band { margin-top: 14px; padding-top: 6px; }
        .if-emi-footer-confirm {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            flex-wrap: wrap;
            gap: 8px;
            font-size: 10px;
            margin-bottom: 28px;
        }
        .if-emi-company-name { font-weight: 700; font-size: 12px; }
        .if-emi-sig-row { display: flex; gap: 8px; justify-content: space-between; }
        .if-emi-sig-cell { flex: 1; text-align: center; min-width: 0; }
        .if-emi-sig-line {
            border-bottom: 1px solid #000;
            height: 28px;
            margin: 0 4px 4px;
        }
        .if-emi-sig-lbl { font-size: 8px; line-height: 1.25; color: #111; }
    </style>
    <script>
        window.IF_PRINT_BOOT = <?php echo json_encode([
            'trn' => $if_trn,
            'companyLegal' => $emi_voucher_company_legal,
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    </script>
</head>
<body>
    <div class="if-print-toolbar no-print">
        <button type="button" onclick="window.print()">Print</button>
        <button type="button" onclick="window.close()">Close</button>
        <span style="color:#64748b;">Use your browser print dialog (Ctrl+P) to save as PDF.</span>
    </div>
    <div id="ifPrintRoot"></div>
    <script src="assets/js/investment-fund-voucher-print.js"></script>
</body>
</html>
