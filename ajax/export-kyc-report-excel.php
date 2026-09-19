<?php
/**
 * Customer KYC Report — styled .xlsx. Exports columns matching the table (visibility + order from client).
 * POST JSON: { columns: string[], search?, customer_type_id?, country_id?, nationality_id?, has_aml?, sort?, order? }
 * Legacy GET (no body): filters via $_GET; default column set = all except action.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_require_login.php';
require_once __DIR__ . '/../includes/customer_nominee_schema.php';
auragold_require_login_or_exit();
if (isset($conn) && $conn instanceof mysqli) {
    auragold_ensure_customer_nominee_column($conn);
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/auragold_excel_financial_banner.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** @var array<string,string> */
$column_labels = [
    'name'             => 'Name',
    'account_no'       => 'Account No',
    'first_name'       => 'First Name',
    'last_name'        => 'Last Name',
    'contact'          => 'Contact',
    'email_id'         => 'Email ID',
    'identity_no'      => 'Identity No.',
    'national_id'      => 'National ID',
    'trade_no'         => 'Trade No.',
    'special_day'      => 'Special Day',
    'dob'              => 'DOB',
    'registration'     => 'Registration',
    'customer_type'    => 'Customer Type',
    'country'          => 'Country',
    'nationality'      => 'Nationality',
    'billing_address'  => 'Billing Address',
    'state'            => 'State',
    'nominee'          => 'Nominee',
    'aml'              => 'Aml',
    'info'             => 'info',
];

$default_export_order = array_keys($column_labels);

$payload = null;
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $payload = json_decode((string) $raw, true);
}

if (is_array($payload)) {
    $search = isset($payload['search']) ? esc(trim((string) $payload['search'])) : '';
    $customer_type_id = isset($payload['customer_type_id']) ? (int) $payload['customer_type_id'] : 0;
    $country_id = isset($payload['country_id']) ? (int) $payload['country_id'] : 0;
    $nationality_id = isset($payload['nationality_id']) ? (int) $payload['nationality_id'] : 0;
    $has_aml = isset($payload['has_aml']) ? esc(trim((string) $payload['has_aml'])) : '';
    $sort_key = isset($payload['sort']) ? preg_replace('/[^a-z0-9_]/', '', (string) $payload['sort']) : 'name';
    $sort_order = isset($payload['order']) && strtolower((string) $payload['order']) === 'desc' ? 'DESC' : 'ASC';
    $columns_req = isset($payload['columns']) && is_array($payload['columns']) ? $payload['columns'] : null;
} else {
    $search = isset($_GET['search']) ? esc($_GET['search']) : '';
    $customer_type_id = isset($_GET['customer_type_id']) ? (int) $_GET['customer_type_id'] : 0;
    $country_id = isset($_GET['country_id']) ? (int) $_GET['country_id'] : 0;
    $nationality_id = isset($_GET['nationality_id']) ? (int) $_GET['nationality_id'] : 0;
    $has_aml = isset($_GET['has_aml']) ? esc($_GET['has_aml']) : '';
    $sort_key = isset($_GET['sort']) ? preg_replace('/[^a-z0-9_]/', '', $_GET['sort']) : 'name';
    $sort_order = isset($_GET['order']) && strtolower($_GET['order']) === 'desc' ? 'DESC' : 'ASC';
    $columns_req = null;
}

$allowed_keys = array_flip(array_keys($column_labels));
$export_cols = [];
if (is_array($columns_req)) {
    $seen = [];
    foreach ($columns_req as $item) {
        $k = preg_replace('/[^a-z0-9_]/', '', (string) $item);
        if ($k === '' || !isset($allowed_keys[$k]) || isset($seen[$k])) {
            continue;
        }
        $seen[$k] = true;
        $export_cols[] = $k;
    }
}
if ($export_cols === []) {
    $export_cols = $default_export_order;
}

$num_cols = count($export_cols);
if ($num_cols < 1) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No columns to export';
    exit;
}

$lastCol = Coordinate::stringFromColumnIndex($num_cols);

$sort_map = [
    'name'             => 'c.name',
    'account_no'       => 'c.id',
    'first_name'       => 'c.first_name',
    'last_name'        => 'c.last_name',
    'contact'          => 'c.mobile_no',
    'email_id'         => 'c.mail_id',
    'identity_no'      => 'c.identity_no',
    'national_id'      => 'c.national_id',
    'trade_no'         => 'c.trade_no',
    'special_day'      => 'c.special_day',
    'dob'              => 'c.date1',
    'registration'     => 'c.registration_no',
    'customer_type'    => 'ct.name',
    'country'          => 'co.name',
    'nationality'      => 'n.name',
    'billing_address'  => 'c.billing_address1',
    'state'            => 'c.billing_state',
    'nominee'          => 'nominee_sort',
    'aml'              => 'c.aml',
    'info'             => 'c.notes',
];

$order_col = $sort_map['name'];
if ($sort_key !== '' && isset($sort_map[$sort_key])) {
    $order_col = $sort_map[$sort_key];
}

$where_clause = 'c.status = 1';

if ($search !== '') {
    $where_clause .= " AND (
        c.name LIKE '%$search%'
        OR c.alternate_name LIKE '%$search%'
        OR c.first_name LIKE '%$search%'
        OR c.last_name LIKE '%$search%'
        OR c.mobile_no LIKE '%$search%'
        OR c.mail_id LIKE '%$search%'
        OR c.identity_no LIKE '%$search%'
        OR c.national_id LIKE '%$search%'
        OR c.trade_no LIKE '%$search%'
        OR CAST(c.id AS CHAR) LIKE '%$search%'
    )";
}
if ($customer_type_id > 0) {
    $where_clause .= " AND c.customer_type_id = $customer_type_id";
}
if ($country_id > 0) {
    $where_clause .= " AND c.country_id = $country_id";
}
if ($nationality_id > 0) {
    $where_clause .= " AND c.nationality_id = $nationality_id";
}
if ($has_aml !== '') {
    $where_clause .= ' AND c.aml = ' . (int) $has_aml;
}

$nominee_expr = "NULLIF(TRIM(COALESCE(
    JSON_UNQUOTE(JSON_EXTRACT(c.nominee_data, '\$[0].name')),
    JSON_UNQUOTE(JSON_EXTRACT(c.share_holders_data, '\$[0].name')),
    '')), '')";

$query = "
    SELECT
        c.id,
        c.name,
        c.first_name,
        c.last_name,
        CONCAT(TRIM(COALESCE(c.mobile_country_code, '')), ' ', TRIM(COALESCE(c.mobile_no, ''))) AS contact,
        c.mail_id AS email_id,
        c.identity_no,
        c.national_id,
        c.trade_no,
        c.special_day,
        c.date1 AS dob,
        c.registration_no,
        c.registration_date,
        COALESCE(ct.name, '') AS customer_type,
        COALESCE(co.name, '') AS country,
        COALESCE(n.name, '') AS nationality,
        TRIM(CONCAT(COALESCE(c.billing_address1, ''), ' ', COALESCE(c.billing_address2, ''))) AS billing_address,
        c.billing_state AS state,
        $nominee_expr AS nominee,
        $nominee_expr AS nominee_sort,
        c.notes AS info,
        CASE WHEN c.aml = 1 THEN 'Yes' ELSE 'No' END AS aml
    FROM tbl_customers c
    LEFT JOIN tbl_customer_types ct ON c.customer_type_id = ct.id
    LEFT JOIN tbl_countries co ON c.country_id = co.id
    LEFT JOIN tbl_nationalities n ON c.nationality_id = n.id
    WHERE $where_clause
    ORDER BY $order_col $sort_order
    LIMIT 50000
";

$data = getList($query);
if (!is_array($data)) {
    $data = [];
}

$formatted = [];
foreach ($data as $row) {
    $reg_parts = array_filter([
        trim((string) ($row['registration_no'] ?? '')),
        !empty($row['registration_date']) ? $row['registration_date'] : '',
    ]);
    $formatted[] = [
        'id'              => $row['id'],
        'account_no'      => (string) ($row['id'] ?? ''),
        'name'            => $row['name'] ?? '',
        'first_name'      => $row['first_name'] ?? '',
        'last_name'       => $row['last_name'] ?? '',
        'contact'         => trim(preg_replace('/\s+/', ' ', (string) ($row['contact'] ?? ''))),
        'email_id'        => $row['email_id'] ?? '',
        'identity_no'     => $row['identity_no'] ?? '',
        'national_id'     => $row['national_id'] ?? '',
        'trade_no'        => $row['trade_no'] ?? '',
        'special_day'     => $row['special_day'] ?? '',
        'dob'             => $row['dob'] ?? '',
        'registration'    => implode(' | ', $reg_parts),
        'customer_type'   => $row['customer_type'] ?? '',
        'country'         => $row['country'] ?? '',
        'nationality'     => $row['nationality'] ?? '',
        'billing_address' => $row['billing_address'] ?? '',
        'state'           => $row['state'] ?? '',
        'nominee'         => $row['nominee'] ?? '',
        'aml'             => $row['aml'] ?? '',
        'info'            => $row['info'] ?? '',
    ];
}

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

$periodFromSlash = date('Y/m/d');
$periodToSlash = date('Y/m/d');
if (!empty($_SESSION['financial_year']) && is_array($_SESSION['financial_year'])) {
    $fyS = trim((string) ($_SESSION['financial_year']['start_date'] ?? ''));
    $fyE = trim((string) ($_SESSION['financial_year']['end_date'] ?? ''));
    $tsS = $fyS !== '' ? strtotime($fyS) : false;
    $tsE = $fyE !== '' ? strtotime($fyE) : false;
    if ($tsS !== false) {
        $periodFromSlash = date('Y/m/d', $tsS);
    }
    if ($tsE !== false) {
        $periodToSlash = date('Y/m/d', $tsE);
    }
} else {
    $y = (int) date('Y');
    $periodFromSlash = $y . '/01/01';
    $periodToSlash = $y . '/12/31';
}

$periodLine = 'Customer KYC Report Report From :- ' . $periodFromSlash . ' To :- ' . $periodToSlash;

$thinBorder = [
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
    ],
];
$fillGreenHdr = [
    'fillType'   => Fill::FILL_SOLID,
    'startColor' => ['rgb' => 'C8E6C9'],
];

$width_hints = [
    'name'             => 28,
    'account_no'       => 12,
    'first_name'       => 18,
    'last_name'        => 16,
    'contact'          => 16,
    'email_id'         => 30,
    'identity_no'      => 14,
    'national_id'      => 14,
    'trade_no'         => 18,
    'special_day'      => 12,
    'dob'              => 12,
    'registration'     => 22,
    'customer_type'    => 16,
    'country'          => 14,
    'nationality'      => 14,
    'billing_address'  => 36,
    'state'            => 14,
    'nominee'          => 16,
    'aml'              => 8,
    'info'             => 28,
];

$spreadsheet = new Spreadsheet();
$spreadsheet->getDefaultStyle()->getFont()->setName('Calibri');
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('CUSTOMER KYC REPORT');

$hdrRow = auragold_excel_financial_banner_layout(
    $sheet,
    $lastCol,
    strtoupper($shopName),
    'Business License No -' . ($licenseNo !== '' ? ' ' . $licenseNo : ''),
    $periodLine,
    [
        'title_font'       => ['size' => 18],
        'title_row_height' => 36,
        'license_font'     => ['color' => ['rgb' => '000000'], 'bold' => false],
        'period_font'      => ['color' => ['rgb' => '000000']],
    ]
);

for ($i = 0; $i < $num_cols; $i++) {
    $key = $export_cols[$i];
    $col = Coordinate::stringFromColumnIndex($i + 1);
    $sheet->setCellValue($col . $hdrRow, $column_labels[$key] ?? $key);
}
$sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->applyFromArray($thinBorder);
$sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->getFont()->setBold(true);
$sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->getFill()->applyFromArray($fillGreenHdr);
$sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);

$r = $hdrRow + 1;
foreach ($formatted as $fr) {
    for ($i = 0; $i < $num_cols; $i++) {
        $key = $export_cols[$i];
        $col = Coordinate::stringFromColumnIndex($i + 1);
        $val = isset($fr[$key]) ? (string) $fr[$key] : '';
        $sheet->setCellValue($col . $r, $val);
    }
    $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->applyFromArray($thinBorder);
    $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
        ->setVertical(Alignment::VERTICAL_CENTER);
    ++$r;
}

for ($i = 0; $i < $num_cols; $i++) {
    $key = $export_cols[$i];
    $col = Coordinate::stringFromColumnIndex($i + 1);
    $w = $width_hints[$key] ?? 14;
    $sheet->getColumnDimension($col)->setWidth((float) $w);
}

$filename = 'Customer_KYC_Report_' . date('d_m_Y') . '.xlsx';
$filename = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $filename);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
