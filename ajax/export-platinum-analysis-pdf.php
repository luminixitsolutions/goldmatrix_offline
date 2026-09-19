<?php

/**
 * Platinum Analysis — Current Stock / Stock Details PDF (same data as Excel export).
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
require_once __DIR__ . '/../includes/platinum_analysis_export_data.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$data = auragold_platinum_analysis_export_fetch();
if ($data['error'] !== '') {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain; charset=utf-8');
    echo $data['error'];
    exit;
}

$rows = $data['rows'];
$totals = $data['totals'];
$tab = $data['tab'];
$titles = auragold_platinum_analysis_export_headers();

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
$periodLine = $tab === 'stock-details'
    ? 'Platinum Analysis — Stock Details (up to 15,000 rows)'
    : 'Platinum Analysis — Current Stock (up to 15,000 rows)';

$tg = static function (array $tt, string $key): float {
    foreach ($tt as $k => $v) {
        if (strcasecmp((string) $k, $key) === 0) {
            return (float) $v;
        }
    }
    return 0.0;
};

$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
body { font-family: DejaVu Sans, sans-serif; font-size: 8px; margin: 0; padding: 6px; }
.banner { background:#4472C4;color:#fff;text-align:center;font-weight:bold;padding:8px 4px;font-size:11px; }
.license { color:#C62828;padding:3px 2px;font-size:8px; }
.period { background:#F8CBAD;text-align:center;font-weight:bold;padding:6px 3px;font-size:9px;color:#1F2937; }
table { width:100%; border-collapse: collapse; margin-top: 4px; }
th { background:#E2EFDA; border:1px solid #000; padding:2px 1px; font-size:7px; text-align:center; font-weight:bold; }
td { border:1px solid #000; padding:1px; font-size:7px; vertical-align: top; }
.peach { background:#F8CBAD; text-align:right; }
.totalrow td { background:#4472C4; color:#fff; font-weight:bold; border:1px solid #000; font-size:7px; }
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
    $vals = auragold_platinum_analysis_export_flat_row($row, $tab);
    $html .= '<tr>';
    foreach ($vals as $idx => $c) {
        $cls = ($idx >= 2) ? ' class="peach"' : '';
        $disp = is_scalar($c) ? (string) $c : '';
        if ($idx === 2) {
            $disp = number_format((float) $c, 0, '.', '');
        } elseif (($idx >= 3 && $idx <= 4) || ($idx >= 7 && $idx <= 8)) {
            $disp = number_format((float) $c, 3, '.', '');
        } elseif ($idx === 9) {
            $disp = number_format((float) $c, 2, '.', '');
        }
        $html .= '<td' . $cls . '>' . htmlspecialchars($disp, ENT_QUOTES, 'UTF-8') . '</td>';
    }
    $html .= '</tr>';
}

$html .= '<tr class="totalrow">';
$html .= '<td colspan="2" style="text-align:right">Total</td>';
$html .= '<td style="text-align:center">' . htmlspecialchars(number_format($tg($totals, 'total_qty'), 0, '.', ''), ENT_QUOTES, 'UTF-8') . '</td>';
$html .= '<td style="text-align:center">' . htmlspecialchars(number_format($tg($totals, 'total_gross_weight'), 3, '.', ''), ENT_QUOTES, 'UTF-8') . '</td>';
$html .= '<td style="text-align:center">' . htmlspecialchars(number_format($tg($totals, 'total_pure_weight'), 3, '.', ''), ENT_QUOTES, 'UTF-8') . '</td>';
$html .= '<td></td><td></td>';
$html .= '<td style="text-align:center">' . htmlspecialchars(number_format($tg($totals, 'total_net_weight'), 3, '.', ''), ENT_QUOTES, 'UTF-8') . '</td>';
$html .= '<td style="text-align:center">' . htmlspecialchars(number_format($tg($totals, 'total_stone_weight'), 3, '.', ''), ENT_QUOTES, 'UTF-8') . '</td>';
$html .= '<td style="text-align:center">' . htmlspecialchars(number_format($tg($totals, 'total_purchase_amount'), 2, '.', ''), ENT_QUOTES, 'UTF-8') . '</td>';
$html .= '</tr></tbody></table></body></html>';

$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isRemoteEnabled', false);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$fnameSuffix = $tab === 'stock-details' ? 'Stock_Details' : 'Current_Stock';
$fname = 'Platinum_Analysis_' . $fnameSuffix . '_' . date('Y-m-d') . '.pdf';
while (ob_get_level()) {
    ob_end_clean();
}
$dompdf->stream($fname, ['Attachment' => true]);
exit;
