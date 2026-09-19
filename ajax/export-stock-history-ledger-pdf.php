<?php

/**
 * Stock History (ledger) — PDF export (same filters as ledger page).
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_require_login.php';
auragold_require_login_or_exit();

if (!isset($conn) || !($conn instanceof mysqli)) {
    header('HTTP/1.1 500 Internal Server Error');
    echo 'No database connection';
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/stock_history_ledger_fetch.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$data = auragold_stock_history_ledger_fetch($conn, $_GET);
if ($data['err'] !== '') {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain; charset=utf-8');
    echo $data['err'];
    exit;
}

$rows = $data['rows'];
$tot_qty = $data['tot_qty'];
$tot_gross = $data['tot_gross'];
$tot_pure = $data['tot_pure'];
$adv_date_from = $data['adv_date_from'];
$adv_date_to = $data['adv_date_to'];

$shopName = defined('COMPANY_NAME') ? (string) COMPANY_NAME : 'Gold Matrix';
$licenseNo = '';
$targetBranchId = 0;
if (function_exists('auragold_effective_branch_id')) {
    $targetBranchId = (int) auragold_effective_branch_id();
}
if ($targetBranchId <= 0 && function_exists('auragold_my_profile_target_branch_id')) {
    $targetBranchId = (int) auragold_my_profile_target_branch_id();
}
if ($targetBranchId > 0 && function_exists('getRecordMaster') && isset($conn_master) && $conn_master instanceof mysqli) {
    $hasBizLic = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn_master, 'tbl_branches', 'business_license_no');
    if ($hasBizLic) {
        $br = getRecordMaster(
            'SELECT name, IFNULL(business_license_no,\'\') AS business_license_no, gst_no, pan_no FROM tbl_branches WHERE id = ' . $targetBranchId . ' LIMIT 1'
        );
    } else {
        $br = getRecordMaster('SELECT name, gst_no, pan_no FROM tbl_branches WHERE id = ' . $targetBranchId . ' LIMIT 1');
    }
    if (is_array($br)) {
        $nm = trim((string) ($br['name'] ?? ''));
        if ($nm !== '') {
            $shopName = $nm;
        }
        if ($hasBizLic) {
            $licenseNo = trim((string) ($br['business_license_no'] ?? ''));
        }
        if ($licenseNo === '') {
            $licenseNo = trim((string) ($br['gst_no'] ?? ''));
        }
        if ($licenseNo === '') {
            $licenseNo = trim((string) ($br['pan_no'] ?? ''));
        }
    }
}

$licenseLine = 'Business License No - ' . ($licenseNo !== '' ? $licenseNo : '—');

$periodLine = 'Stock History (Ledger)';
if ($adv_date_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $adv_date_from)) {
    $f = str_replace('-', '/', $adv_date_from);
    if ($adv_date_to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $adv_date_to)) {
        $t = str_replace('-', '/', $adv_date_to);
        $periodLine .= ' From :- ' . $f . ' To :- ' . $t;
    } else {
        $periodLine .= ' From :- ' . $f;
    }
} elseif ($adv_date_to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $adv_date_to)) {
    $periodLine .= ' To :- ' . str_replace('-', '/', $adv_date_to);
}

$titles = [
    'Date', 'Barcode No', 'RFID', 'Against Invoice No', 'Voucher Type', 'Location', 'Invoice No.',
    'Against Voucher Type', 'Branch', 'Qty.', 'Gross Wt', 'Pure Wt.', 'Product Name', 'Metal', 'Category', 'Article',
];

$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
body { font-family: DejaVu Sans, sans-serif; font-size: 9px; margin: 0; padding: 8px; }
.banner { background:#4472C4;color:#fff;text-align:center;font-weight:bold;padding:10px 6px;font-size:13px; }
.license { color:#C62828;padding:4px 2px;font-size:10px; }
.period { background:#F8CBAD;text-align:center;font-weight:bold;padding:7px 4px;font-size:11px;color:#1F2937; }
table { width:100%; border-collapse: collapse; margin-top: 6px; }
th { background:#E2EFDA; border:1px solid #000; padding:4px 2px; font-size:8px; text-align:center; font-weight:bold; }
td { border:1px solid #000; padding:3px 2px; font-size:8px; vertical-align: top; }
.peach { background:#F8CBAD; text-align:right; }
.totalrow td { background:#4472C4; color:#fff; font-weight:bold; border:1px solid #000; }
</style></head><body>';
$html .= '<div class="banner">' . htmlspecialchars(strtoupper($shopName), ENT_QUOTES, 'UTF-8') . '</div>';
$html .= '<div class="license">' . htmlspecialchars($licenseLine, ENT_QUOTES, 'UTF-8') . '</div>';
$html .= '<div class="period">' . htmlspecialchars($periodLine, ENT_QUOTES, 'UTF-8') . '</div>';
$html .= '<table><thead><tr>';
foreach ($titles as $t) {
    $html .= '<th>' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '</th>';
}
$html .= '</tr></thead><tbody>';

foreach ($rows as $row) {
    $d = !empty($row['sj_date']) ? $row['sj_date'] : '';
    $dShow = $d ? date('d-m-Y', strtotime((string) $d)) : '';
    $son = trim((string) ($row['sale_order_no'] ?? ''));
    $voucher = auragold_stock_history_ledger_voucher_display(trim((string) ($row['voucher_type'] ?? '')));
    $jwn = trim((string) ($row['jobwork_no'] ?? ''));

    $cells = [
        $dShow,
        trim((string) ($row['barcode'] ?? '')),
        trim((string) ($row['rfid'] ?? '')),
        $son !== '' ? $son : '—',
        $voucher,
        trim((string) ($row['location'] ?? '')),
        trim((string) ($row['doc_invoice_no'] ?? '')),
        $jwn !== '' ? $jwn : '—',
        trim((string) ($row['branch_name'] ?? '')),
        number_format((float) ($row['qty'] ?? 0), 0, '.', ''),
        number_format((float) ($row['gross_wt'] ?? 0), 3, '.', ''),
        number_format((float) ($row['pure_wt'] ?? 0), 3, '.', ''),
        trim((string) ($row['product_name'] ?? '')),
        trim((string) ($row['metal_name'] ?? '')),
        trim((string) ($row['category_name'] ?? '')),
        trim((string) ($row['article'] ?? '')),
    ];
    $html .= '<tr>';
    foreach ($cells as $idx => $c) {
        $cls = ($idx >= 9 && $idx <= 11) ? ' class="peach"' : '';
        $html .= '<td' . $cls . '>' . htmlspecialchars((string) $c, ENT_QUOTES, 'UTF-8') . '</td>';
    }
    $html .= '</tr>';
}

$html .= '<tr class="totalrow">';
$html .= '<td colspan="9" style="text-align:right">Total</td>';
$html .= '<td style="text-align:center">' . htmlspecialchars(number_format($tot_qty, 0, '.', ''), ENT_QUOTES, 'UTF-8') . '</td>';
$html .= '<td style="text-align:center">' . htmlspecialchars(number_format($tot_gross, 3, '.', ''), ENT_QUOTES, 'UTF-8') . '</td>';
$html .= '<td style="text-align:center">' . htmlspecialchars(number_format($tot_pure, 3, '.', ''), ENT_QUOTES, 'UTF-8') . '</td>';
$html .= '<td colspan="4"></td>';
$html .= '</tr></tbody></table></body></html>';

$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isRemoteEnabled', false);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

while (ob_get_level()) {
    ob_end_clean();
}
$fname = 'Stock_History_Ledger_' . date('Y-m-d') . '.pdf';
$dompdf->stream($fname, ['Attachment' => true]);
exit;
