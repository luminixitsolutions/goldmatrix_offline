<?php

/**
 * Gold & Silver stock list — PDF (tab filter: gold | silver | all).
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

$tab = isset($_GET['tab']) ? strtolower(trim((string) $_GET['tab'])) : 'gold';
if (!in_array($tab, ['gold', 'silver', 'all'], true)) {
    $tab = 'gold';
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/gold_silver_stock_list_fetch.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$result = auragold_gold_silver_stock_list_fetch($conn, $tab);
if ($result['error'] !== '') {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain; charset=utf-8');
    echo strip_tags($result['error']);
    exit;
}

$rows = $result['rows'];
$titles = auragold_gold_silver_export_headers();

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
$periodLine = 'Gold & Silver — ' . auragold_gold_silver_export_tab_period_line($tab);

$nCols = count($titles);
$sumIdxWt = [8, 9, 10, 11, 12, 14, 15, 16];
$sumIdxMoney = [22, 23, 24, 25, 26, 27, 28];
$tot = array_fill(0, $nCols, 0.0);
$totHas = array_fill(0, $nCols, false);

$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
body { font-family: DejaVu Sans, sans-serif; font-size: 7px; margin: 0; padding: 6px; }
.banner { background:#4472C4;color:#fff;text-align:center;font-weight:bold;padding:8px 4px;font-size:11px; }
.license { color:#C62828;padding:3px 2px;font-size:8px; }
.period { background:#F8CBAD;text-align:center;font-weight:bold;padding:6px 3px;font-size:9px;color:#1F2937; }
table { width:100%; border-collapse: collapse; margin-top: 4px; }
th { background:#E2EFDA; border:1px solid #000; padding:2px 1px; font-size:6px; text-align:center; font-weight:bold; }
td { border:1px solid #000; padding:1px; font-size:6px; vertical-align: top; }
.peach { background:#F8CBAD; text-align:right; }
.totalrow td { background:#4472C4; color:#fff; font-weight:bold; border:1px solid #000; font-size:6px; }
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
    $vals = auragold_gold_silver_export_flat_row($row);
    foreach ($sumIdxWt as $si) {
        if (isset($vals[$si]) && is_numeric($vals[$si])) {
            $tot[$si] += (float) $vals[$si];
            $totHas[$si] = true;
        }
    }
    foreach ($sumIdxMoney as $si) {
        if (isset($vals[$si]) && is_numeric($vals[$si])) {
            $tot[$si] += (float) $vals[$si];
            $totHas[$si] = true;
        }
    }
    $html .= '<tr>';
    foreach ($vals as $idx => $c) {
        $cls = '';
        if (($idx >= 8 && $idx <= 16 && $idx !== 13) || ($idx >= 22 && $idx <= 28)) {
            $cls = ' class="peach"';
        }
        $disp = is_scalar($c) ? (string) $c : '';
        if (is_int($c) || is_float($c) || (is_string($c) && $c !== '' && is_numeric($c))) {
            $disp = is_string($c) ? $c : (string) $c;
        }
        $html .= '<td' . $cls . '>' . htmlspecialchars($disp, ENT_QUOTES, 'UTF-8') . '</td>';
    }
    $html .= '</tr>';
}

$html .= '<tr class="totalrow">';
$html .= '<td colspan="8" style="text-align:right">Total</td>';
for ($i = 8; $i <= 16; ++$i) {
    if ($i === 13) {
        $html .= '<td></td>';
    } elseif ($totHas[$i]) {
        $dec = ($i === 12) ? 2 : 3;
        $html .= '<td style="text-align:center">' . htmlspecialchars(number_format($tot[$i], $dec, '.', ''), ENT_QUOTES, 'UTF-8') . '</td>';
    } else {
        $html .= '<td></td>';
    }
}
for ($i = 17; $i <= 21; ++$i) {
    $html .= '<td></td>';
}
for ($i = 22; $i <= 28; ++$i) {
    if ($totHas[$i]) {
        $html .= '<td style="text-align:center">' . htmlspecialchars(number_format($tot[$i], 2, '.', ''), ENT_QUOTES, 'UTF-8') . '</td>';
    } else {
        $html .= '<td></td>';
    }
}
$html .= '</tr></tbody></table></body></html>';

$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isRemoteEnabled', false);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$slug = auragold_gold_silver_export_file_slug($tab);
$fname = 'Gold_Silver_Stock_' . $slug . '_' . date('Y-m-d') . '.pdf';
while (ob_get_level()) {
    ob_end_clean();
}
$dompdf->stream($fname, ['Attachment' => true]);
exit;
