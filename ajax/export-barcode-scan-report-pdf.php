<?php
/**
 * Barcode Scan Report — PDF export (landscape).
 * GET: from_date, to_date, status?, q?|search?
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_require_login.php';
require_once __DIR__ . '/../includes/auragold_barcode_scan_report_data.php';
require_once __DIR__ . '/../vendor/autoload.php';

auragold_require_login_or_exit();

if (!isset($conn) || !($conn instanceof mysqli)) {
    header('HTTP/1.1 500 Internal Server Error');
    echo 'No database connection';
    exit;
}

use Dompdf\Dompdf;
use Dompdf\Options;

$fetch = auragold_barcode_scan_report_fetch($conn, auragold_barcode_scan_report_filters_from_request());
if (empty($fetch['success'])) {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain; charset=utf-8');
    echo $fetch['error'] ?? 'Export failed';
    exit;
}

$rows = $fetch['rows'];
$summary = $fetch['summary'];
$fd = $fetch['from_date'];
$td = $fetch['to_date'];
$status = isset($_GET['status']) ? trim((string) $_GET['status']) : '';

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
$periodLine = 'Barcode Scan Report';
if ($fd !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fd)) {
    $f = str_replace('-', '/', $fd);
    if ($td !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $td)) {
        $t = str_replace('-', '/', $td);
        $periodLine .= ' From :- ' . $f . ' To :- ' . $t;
    } else {
        $periodLine .= ' From :- ' . $f;
    }
}
if ($status !== '') {
    $periodLine .= ' | Status: ' . ucfirst(strtolower($status));
}

$cols = auragold_barcode_scan_report_columns();

$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
body { font-family: DejaVu Sans, sans-serif; font-size: 7px; margin: 0; padding: 6px; color: #111; }
.banner { background:#4472C4;color:#fff;text-align:center;font-weight:bold;padding:8px 4px;font-size:12px; }
.license { color:#C62828;padding:3px 2px;font-size:9px; }
.period { background:#F8CBAD;text-align:center;font-weight:bold;padding:6px 4px;font-size:10px;color:#1F2937; }
table { width:100%; border-collapse: collapse; margin-top: 5px; table-layout: fixed; }
th { background:#E2EFDA; border:1px solid #000; padding:3px 2px; font-size:6.5px; text-align:center; font-weight:bold; word-wrap:break-word; }
td { border:1px solid #000; padding:2px 2px; font-size:6.5px; vertical-align: top; word-wrap:break-word; }
.num { text-align:right; }
.totalrow td { background:#4472C4; color:#fff; font-weight:bold; border:1px solid #000; }
.badge-matched { color:#166534; font-weight:bold; }
.badge-unknown { color:#991b1b; font-weight:bold; }
</style></head><body>';
$html .= '<div class="banner">' . htmlspecialchars(strtoupper($shopName), ENT_QUOTES, 'UTF-8') . '</div>';
$html .= '<div class="license">' . htmlspecialchars($licenseLine, ENT_QUOTES, 'UTF-8') . '</div>';
$html .= '<div class="period">' . htmlspecialchars($periodLine, ENT_QUOTES, 'UTF-8') . '</div>';
$html .= '<table><thead><tr>';
foreach ($cols as $c) {
    $html .= '<th>' . htmlspecialchars((string) ($c['label'] ?? ''), ENT_QUOTES, 'UTF-8') . '</th>';
}
$html .= '</tr></thead><tbody>';

if ($rows === []) {
    $html .= '<tr><td colspan="' . count($cols) . '" style="text-align:center;color:#666;padding:12px">No scan records for selected filters.</td></tr>';
} else {
    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($cols as $c) {
            $key = (string) ($c['key'] ?? '');
            $val = isset($row[$key]) ? (string) $row[$key] : '';
            if ($key === 'scan_status') {
                $val = (string) ($row['scan_status_label'] ?? ($val === 'unknown' ? 'Unknown' : 'Matched'));
            }
            $cls = !empty($c['numeric']) ? ' class="num"' : '';
            if ($key === 'scan_status') {
                $stCls = strtolower((string) ($row['scan_status'] ?? '')) === 'unknown' ? 'badge-unknown' : 'badge-matched';
                $html .= '<td' . $cls . '><span class="' . $stCls . '">' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '</span></td>';
            } else {
                $html .= '<td' . $cls . '>' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '</td>';
            }
        }
        $html .= '</tr>';
    }
}

$html .= '<tr class="totalrow">';
foreach ($cols as $c) {
    $key = (string) ($c['key'] ?? '');
    if ($key === 'product_name') {
        $html .= '<td style="text-align:right">Total</td>';
    } elseif ($key === 'qty') {
        $html .= '<td class="num">' . htmlspecialchars(number_format((float) ($summary['total_qty'] ?? 0), 4, '.', ''), ENT_QUOTES, 'UTF-8') . '</td>';
    } elseif ($key === 'gross_wt') {
        $html .= '<td class="num">' . htmlspecialchars(number_format((float) ($summary['total_gross_wt'] ?? 0), 4, '.', ''), ENT_QUOTES, 'UTF-8') . '</td>';
    } elseif ($key === 'net_wt') {
        $html .= '<td class="num">' . htmlspecialchars(number_format((float) ($summary['total_net_wt'] ?? 0), 4, '.', ''), ENT_QUOTES, 'UTF-8') . '</td>';
    } elseif ($key === 'final_wt') {
        $html .= '<td class="num">' . htmlspecialchars(number_format((float) ($summary['total_final_wt'] ?? 0), 4, '.', ''), ENT_QUOTES, 'UTF-8') . '</td>';
    } else {
        $html .= '<td></td>';
    }
}
$html .= '</tr>';
$html .= '</tbody></table></body></html>';

$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$filename = 'Barcode_Scan_Report_' . date('d_m_Y') . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
exit;
