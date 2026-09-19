<?php
/**
 * Account Ledger list export — CSV, Excel, PDF (all filtered rows).
 */
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Dompdf\Dompdf;
use Dompdf\Options;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_require_login.php';
require_once __DIR__ . '/../includes/account_ledger_list_data.php';

auragold_require_login_or_exit();

$format = strtolower(trim((string) ($_GET['format'] ?? 'csv')));
if (!in_array($format, ['csv', 'excel', 'pdf'], true)) {
    $format = 'csv';
}

$isTemplate = isset($_GET['template']) && in_array(strtolower(trim((string) $_GET['template'])), ['1', 'true', 'yes'], true);

if ($isTemplate) {
    require_once __DIR__ . '/../includes/account_ledger_fixed.php';
    $headers = ['Ledger', 'Contact', 'Group', 'Branch Name', 'Opening Balance', 'Cr/Dr'];
    $body = auragold_account_ledger_fixed_template_rows();
    $footer = [];
    $grand_total = 0;
    $dateStamp = date('Y-m-d');
    $baseName = 'Account_Ledger_Import_Template';
} else {
    $data = auragold_account_ledger_fetch_rows($conn, $_GET);
    $rows = $data['rows'] ?? [];
    $grand_total = (float) ($data['grand_total'] ?? 0);

    $headers = ['Sr No', 'Ledger', 'Contact', 'Group', 'Branch Name', 'Opening Balance', 'Cr/Dr'];
    $body = [];
    $sr = 1;
    foreach ($rows as $row) {
        $body[] = [
            $sr++,
            (string) ($row['ledger_name'] ?? ''),
            (string) ($row['contact'] ?? ''),
            (string) ($row['group_name'] ?? ''),
            (string) ($row['branch_name'] ?? ''),
            number_format((float) ($row['opening_balance'] ?? 0), 3, '.', ''),
            (string) ($row['crdr'] ?? ''),
        ];
    }
    $footer = [
        '',
        'Grand Total',
        '',
        '',
        '',
        number_format(abs($grand_total), 3, '.', ''),
        $grand_total >= 0 ? 'Dr' : 'Cr',
    ];
    $dateStamp = date('Y-m-d');
    $baseName = 'Account_Ledger_' . $dateStamp;
}

if ($isTemplate && $format !== 'excel') {
    $format = 'excel';
}

if ($format === 'csv') {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $baseName . '.csv"');
    header('Cache-Control: max-age=0');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, $headers);
    foreach ($body as $line) {
        fputcsv($out, $line);
    }
    if ($footer !== []) {
        fputcsv($out, $footer);
    }
    fclose($out);
    exit;
}

if ($format === 'excel') {
    require_once __DIR__ . '/../vendor/autoload.php';

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Account Ledger');
    $nCols = count($headers);
    $lastCol = chr(64 + min($nCols, 26));

    if ($isTemplate) {
        $hdrRow = 1;
        foreach ($headers as $i => $h) {
            $col = chr(65 + $i);
            $sheet->setCellValue($col . $hdrRow, $h);
        }
    } else {
        $sheet->setCellValue('A1', 'Account Ledger');
        $sheet->mergeCells('A1:' . $lastCol . '1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A2', 'Exported: ' . date('d/m/Y H:i'));
        $sheet->mergeCells('A2:' . $lastCol . '2');
        $hdrRow = 4;
        foreach ($headers as $i => $h) {
            $col = chr(65 + $i);
            $sheet->setCellValue($col . $hdrRow, $h);
        }
    }
    $sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->getFont()->setBold(true);
    $sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->getFill()
        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1EDFF');

    $r = $hdrRow + 1;
    foreach ($body as $line) {
        foreach ($line as $i => $val) {
            $col = chr(65 + $i);
            $sheet->setCellValue($col . $r, $val);
        }
        ++$r;
    }
    if ($footer !== []) {
        foreach ($footer as $i => $val) {
            $col = chr(65 + $i);
            $sheet->setCellValue($col . $r, $val);
        }
        $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->getFont()->setBold(true);
        $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
    }

    foreach (range('A', $lastCol) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    $sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $r)->getBorders()->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN);

    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $baseName . '.xlsx"');
    header('Cache-Control: max-age=0');
    (new Xlsx($spreadsheet))->save('php://output');
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';

$shopName = defined('COMPANY_NAME') ? (string) COMPANY_NAME : 'Gold Matrix';
$html = '<html><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:9px;color:#111}
h2{margin:0 0 4px;font-size:14px}.sub{color:#555;margin-bottom:10px;font-size:9px}
table{width:100%;border-collapse:collapse} th,td{border:1px solid #ccc;padding:4px 5px;text-align:left}
th{background:#11294b;color:#fff;font-size:8px}.num{text-align:right}
tfoot td{font-weight:bold;background:#f1edff}
</style></head><body>';
$html .= '<h2>' . htmlspecialchars($shopName) . '</h2>';
$html .= '<div class="sub">Account Ledger — ' . date('d/m/Y H:i') . '</div>';
$html .= '<table><thead><tr>';
foreach ($headers as $h) {
    $html .= '<th>' . htmlspecialchars($h) . '</th>';
}
$html .= '</tr></thead><tbody>';
if ($body === []) {
    $html .= '<tr><td colspan="7" style="text-align:center;color:#666">No records</td></tr>';
}
foreach ($body as $line) {
    $html .= '<tr>';
    foreach ($line as $i => $val) {
        $cls = ($i === 5) ? ' class="num"' : '';
        $html .= '<td' . $cls . '>' . htmlspecialchars((string) $val) . '</td>';
    }
    $html .= '</tr>';
}
$html .= '</tbody>';
if ($footer !== []) {
    $html .= '<tfoot><tr>';
    foreach ($footer as $i => $val) {
        $cls = ($i === 5) ? ' class="num"' : '';
        $html .= '<td' . $cls . '>' . htmlspecialchars((string) $val) . '</td>';
    }
    $html .= '</tr></tfoot>';
}
$html .= '</table></body></html>';

$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream($baseName . '.pdf', ['Attachment' => true]);
exit;
