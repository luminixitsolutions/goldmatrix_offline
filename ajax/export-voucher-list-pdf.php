<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_require_login.php';
require_once __DIR__ . '/../includes/auragold_voucher_list_query.php';
require_once __DIR__ . '/../includes/auragold_voucher_list_export_data.php';
require_once __DIR__ . '/../vendor/autoload.php';

auragold_require_login_or_exit();

use Dompdf\Dompdf;
use Dompdf\Options;

$kind = isset($_GET['kind']) && $_GET['kind'] === 'receipt' ? 'receipt' : 'payment';
$filters = auragold_voucher_list_filter_from_request();
$rows = auragold_voucher_list_export_rows($conn, $kind, $filters);
$title = auragold_voucher_list_export_title($kind);
$shopName = defined('COMPANY_NAME') ? (string) COMPANY_NAME : 'Gold Matrix';

$html = '<html><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:9px;color:#111}
h2{margin:0 0 4px;font-size:14px} .sub{color:#555;margin-bottom:10px;font-size:9px}
table{width:100%;border-collapse:collapse} th,td{border:1px solid #ccc;padding:4px 5px;text-align:left}
th{background:#11294b;color:#fff;font-size:8px} .num{text-align:right}
</style></head><body>';
$html .= '<h2>' . htmlspecialchars($shopName) . '</h2>';
$html .= '<div class="sub">' . htmlspecialchars($title) . ' — ' . date('d/m/Y H:i') . '</div>';
$html .= '<table><thead><tr>
<th>Sr</th><th>Date</th><th>Voucher No</th><th>Ledger</th><th>Branch</th><th>Ref No</th><th>Against</th><th>Total</th>
</tr></thead><tbody>';

$sr = 1;
foreach ($rows as $row) {
    $html .= '<tr>';
    $html .= '<td>' . $sr++ . '</td>';
    $html .= '<td>' . htmlspecialchars(substr((string) ($row['voucher_date'] ?? ''), 0, 10)) . '</td>';
    $html .= '<td>' . htmlspecialchars($row['voucher_no'] ?? '') . '</td>';
    $html .= '<td>' . htmlspecialchars($row['customer_name'] ?? '') . '</td>';
    $html .= '<td>' . htmlspecialchars($row['branch_name'] ?? '') . '</td>';
    $html .= '<td>' . htmlspecialchars($row['ref_no'] ?? '') . '</td>';
    $html .= '<td>' . htmlspecialchars($row['against'] ?? '') . '</td>';
    $html .= '<td class="num">' . number_format((float) ($row['total_amount'] ?? 0), 2) . '</td>';
    $html .= '</tr>';
}
if ($sr === 1) {
    $html .= '<tr><td colspan="8" style="text-align:center;color:#666">No records</td></tr>';
}
$html .= '</tbody></table></body></html>';

$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$filename = ($kind === 'receipt' ? 'receipt-voucher-list' : 'payment-voucher-list') . '-' . date('Y-m-d') . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
exit;
