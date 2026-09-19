<?php

/**
 * Stock Receive — PDF: in transit + received tables (same data as on-screen report).
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
require_once __DIR__ . '/../includes/stock_transfer_pending_schema.php';
require_once __DIR__ . '/../includes/stock_receive_history_fetch.php';

use Dompdf\Dompdf;
use Dompdf\Options;

try {
    $stXferConn = auragold_stock_transfer_central_mysqli();
} catch (Throwable $e) {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Stock transfer database: ' . $e->getMessage();
    exit;
}

$result = auragold_stock_receive_history_fetch($stXferConn);
if ($result['error'] !== '') {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain; charset=utf-8');
    echo $result['error'];
    exit;
}

$pendingRows = $result['pending'];
$receivedRows = $result['received'];

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
$periodLine = 'Stock Receive — in transit (up to 5,000) and received into stock';

$titlesPending = [
    'Date', 'Staging No', 'Product Name', 'Receive at', 'From Branch', 'Barcode',
    'Net Wt', 'Gross Wt', 'Qty', 'Diamond', 'Stone Wt', 'Value', 'Metal Cost', 'Stone Cost', 'Making',
    'Against', 'Status',
];
$titlesRecv = [
    'Date', 'Receipt No', 'Product Name', 'Received at', 'From Branch', 'Barcode',
    'Net Wt', 'Gross Wt', 'Qty', 'Diamond', 'Stone Wt', 'Value', 'Metal Cost', 'Stone Cost', 'Making',
    'Against', 'Receipt status',
];

/**
 * @param array<int, array<string, mixed>> $rows
 * @param array<int, string> $titles
 * @return array{0: string, 1: float, 2: float, 3: float, 4: float, 5: float, 6: float, 7: float, 8: float, 9: float}
 */
function auragold_srh_pdf_table_html(array $rows, array $titles, string $kind): array
{
    $totNet = $totGross = $totQty = $totDia = $totStone = $totPur = $totMetal = $totStoneC = $totMaking = 0.0;

    $html = '<table><thead><tr>';
    foreach ($titles as $t) {
        $html .= '<th>' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '</th>';
    }
    $html .= '</tr></thead><tbody>';

    foreach ($rows as $row) {
        if ($kind === 'pending') {
            $d = !empty($row['transfer_date']) ? $row['transfer_date'] : ($row['created_at'] ?? '');
            $dShow = $d ? date('d/m/Y', strtotime((string) $d)) : '';
            $pendId = (int) ($row['pending_id'] ?? 0);
            $inv = 'STP-' . str_pad((string) $pendId, 6, '0', STR_PAD_LEFT);
            $cells = [
                $dShow,
                $inv,
                trim((string) ($row['product_name'] ?? '')),
                trim((string) ($row['to_branch_name'] ?? '')),
                trim((string) ($row['from_branch_name'] ?? '')),
                trim((string) ($row['barcode'] ?? '')),
                number_format((float) ($row['net_wt'] ?? 0), 3, '.', ''),
                number_format((float) ($row['gross_wt'] ?? 0), 3, '.', ''),
                number_format((float) ($row['qty'] ?? 0), 0, '.', ''),
                number_format((float) ($row['diamond_wt'] ?? 0), 3, '.', ''),
                number_format((float) ($row['stone_wt'] ?? 0), 3, '.', ''),
                number_format((float) ($row['purchase_value'] ?? 0), 2, '.', ''),
                number_format((float) ($row['metal_cost'] ?? 0), 2, '.', ''),
                number_format((float) ($row['stone_cost'] ?? 0), 2, '.', ''),
                number_format((float) ($row['making_cost'] ?? 0), 2, '.', ''),
                trim((string) ($row['against_ref'] ?? '')),
                'In transit',
            ];
        } else {
            $d = !empty($row['transaction_date']) ? $row['transaction_date'] : ($row['created_at'] ?? '');
            $dShow = $d ? date('d/m/Y', strtotime((string) $d)) : '';
            $recvId = (int) ($row['receive_stock_id'] ?? 0);
            $inv = 'SR-' . str_pad((string) $recvId, 6, '0', STR_PAD_LEFT);
            $cells = [
                $dShow,
                $inv,
                trim((string) ($row['product_name'] ?? '')),
                trim((string) ($row['to_branch_name'] ?? '')),
                trim((string) ($row['from_branch_name'] ?? '')),
                trim((string) ($row['barcode'] ?? '')),
                number_format((float) ($row['net_wt'] ?? 0), 3, '.', ''),
                number_format((float) ($row['gross_wt'] ?? 0), 3, '.', ''),
                number_format((float) ($row['qty'] ?? 0), 0, '.', ''),
                number_format((float) ($row['diamond_wt'] ?? 0), 3, '.', ''),
                number_format((float) ($row['stone_wt'] ?? 0), 3, '.', ''),
                number_format((float) ($row['purchase_value'] ?? 0), 2, '.', ''),
                number_format((float) ($row['metal_cost'] ?? 0), 2, '.', ''),
                number_format((float) ($row['stone_cost'] ?? 0), 2, '.', ''),
                number_format((float) ($row['making_cost'] ?? 0), 2, '.', ''),
                trim((string) ($row['against_ref'] ?? '')),
                auragold_stock_receive_received_status_label($row),
            ];
        }

        $totNet += (float) ($row['net_wt'] ?? 0);
        $totGross += (float) ($row['gross_wt'] ?? 0);
        $totQty += (float) ($row['qty'] ?? 0);
        $totDia += (float) ($row['diamond_wt'] ?? 0);
        $totStone += (float) ($row['stone_wt'] ?? 0);
        $totPur += (float) ($row['purchase_value'] ?? 0);
        $totMetal += (float) ($row['metal_cost'] ?? 0);
        $totStoneC += (float) ($row['stone_cost'] ?? 0);
        $totMaking += (float) ($row['making_cost'] ?? 0);

        $html .= '<tr>';
        foreach ($cells as $idx => $c) {
            $cls = ($idx >= 6 && $idx <= 14) ? ' class="peach"' : '';
            $html .= '<td' . $cls . '>' . htmlspecialchars((string) $c, ENT_QUOTES, 'UTF-8') . '</td>';
        }
        $html .= '</tr>';
    }

    $html .= '<tr class="totalrow">';
    $html .= '<td colspan="6" style="text-align:right">Total</td>';
    $html .= '<td style="text-align:center">' . htmlspecialchars(number_format($totNet, 3, '.', ''), ENT_QUOTES, 'UTF-8') . '</td>';
    $html .= '<td style="text-align:center">' . htmlspecialchars(number_format($totGross, 3, '.', ''), ENT_QUOTES, 'UTF-8') . '</td>';
    $html .= '<td style="text-align:center">' . htmlspecialchars(number_format($totQty, 0, '.', ''), ENT_QUOTES, 'UTF-8') . '</td>';
    $html .= '<td style="text-align:center">' . htmlspecialchars(number_format($totDia, 3, '.', ''), ENT_QUOTES, 'UTF-8') . '</td>';
    $html .= '<td style="text-align:center">' . htmlspecialchars(number_format($totStone, 3, '.', ''), ENT_QUOTES, 'UTF-8') . '</td>';
    $html .= '<td style="text-align:center">' . htmlspecialchars(number_format($totPur, 2, '.', ''), ENT_QUOTES, 'UTF-8') . '</td>';
    $html .= '<td style="text-align:center">' . htmlspecialchars(number_format($totMetal, 2, '.', ''), ENT_QUOTES, 'UTF-8') . '</td>';
    $html .= '<td style="text-align:center">' . htmlspecialchars(number_format($totStoneC, 2, '.', ''), ENT_QUOTES, 'UTF-8') . '</td>';
    $html .= '<td style="text-align:center">' . htmlspecialchars(number_format($totMaking, 2, '.', ''), ENT_QUOTES, 'UTF-8') . '</td>';
    $html .= '<td colspan="2"></td>';
    $html .= '</tr></tbody></table>';

    return [$html, $totNet, $totGross, $totQty, $totDia, $totStone, $totPur, $totMetal, $totStoneC, $totMaking];
}

[$htmlPend] = auragold_srh_pdf_table_html($pendingRows, $titlesPending, 'pending');
[$htmlRecv] = auragold_srh_pdf_table_html($receivedRows, $titlesRecv, 'received');

$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
body { font-family: DejaVu Sans, sans-serif; font-size: 8px; margin: 0; padding: 8px; }
.banner { background:#4472C4;color:#fff;text-align:center;font-weight:bold;padding:10px 6px;font-size:12px; }
.license { color:#C62828;padding:4px 2px;font-size:9px; }
.period { background:#F8CBAD;text-align:center;font-weight:bold;padding:7px 4px;font-size:10px;color:#1F2937; }
h3 { font-size:10px; margin:10px 0 4px; color:#1d2c4f; }
table { width:100%; border-collapse: collapse; margin-top: 4px; margin-bottom: 8px; }
th { background:#E2EFDA; border:1px solid #000; padding:3px 2px; font-size:7px; text-align:center; font-weight:bold; }
td { border:1px solid #000; padding:2px; font-size:7px; vertical-align: top; }
.peach { background:#F8CBAD; text-align:right; }
.totalrow td { background:#4472C4; color:#fff; font-weight:bold; border:1px solid #000; }
</style></head><body>';
$html .= '<div class="banner">' . htmlspecialchars(strtoupper($shopName), ENT_QUOTES, 'UTF-8') . '</div>';
$html .= '<div class="license">' . htmlspecialchars($licenseLine, ENT_QUOTES, 'UTF-8') . '</div>';
$html .= '<div class="period">' . htmlspecialchars($periodLine, ENT_QUOTES, 'UTF-8') . '</div>';
$html .= '<h3>In transit (staging)</h3>';
$html .= $htmlPend;
$html .= '<h3>Received into stock</h3>';
$html .= $htmlRecv;
$html .= '</body></html>';

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
$fname = 'Stock_Receive_History_' . date('Y-m-d') . '.pdf';
$dompdf->stream($fname, ['Attachment' => true]);
exit;
