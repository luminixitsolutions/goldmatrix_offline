<?php
/**
 * KYC form for individuals — PDF output (Dompdf). Open from KYC report print action.
 */
declare(strict_types=1);

session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/branch_profile_schema.php';
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

function kyc_form_pdf_h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function kyc_form_pdf_fmt_date(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '' || $raw === '0000-00-00') {
        return '';
    }
    $ts = strtotime($raw);
    return $ts ? date('d/m/Y', $ts) : $raw;
}

$customer_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($customer_id <= 0) {
    http_response_code(400);
    echo 'Invalid customer id';
    exit;
}

$schema = __DIR__ . '/includes/document_types_schema.php';
if (is_file($schema)) {
    require_once $schema;
    if (function_exists('auragold_ensure_tbl_document_types') && isset($conn) && $conn instanceof mysqli) {
        auragold_ensure_tbl_document_types($conn);
    }
}

if (isset($conn_master) && $conn_master instanceof mysqli) {
    auragold_ensure_tbl_branches_profile_columns($conn_master);
}

$customer = getRecord(
    "SELECT c.*, COALESCE(n.name, '') AS nationality_name, COALESCE(ct.name, '') AS customer_type_name
     FROM tbl_customers c
     LEFT JOIN tbl_nationalities n ON c.nationality_id = n.id
     LEFT JOIN tbl_customer_types ct ON c.customer_type_id = ct.id
     WHERE c.id = $customer_id AND c.status = 1 LIMIT 1"
);

if (!$customer || !is_array($customer)) {
    http_response_code(404);
    echo 'Customer not found';
    exit;
}

$branch = [];
$bid = function_exists('auragold_effective_branch_id') ? (int) auragold_effective_branch_id() : 0;
if ($bid > 0 && !empty($conn_master) && function_exists('getRecordMaster')) {
    $branch = getRecordMaster(
        'SELECT name, email, phone, address, pincode, logo_path, website FROM tbl_branches WHERE id = ' . $bid . ' LIMIT 1'
    );
}
if (!is_array($branch)) {
    $branch = [];
}
if (trim((string) ($branch['name'] ?? '')) === '' && !empty($conn_master) && function_exists('getRecordMaster')) {
    $fallback = getRecordMaster(
        'SELECT name, email, phone, address, pincode, logo_path, website FROM tbl_branches WHERE IFNULL(main_branch_id,0) = 0 ORDER BY id ASC LIMIT 1'
    );
    if (is_array($fallback)) {
        $branch = $fallback;
    }
}

$company_name = trim((string) ($branch['name'] ?? ''));
if ($company_name === '') {
    $company_name = 'Company';
}
$company_email = trim((string) ($branch['email'] ?? ''));
$company_phone = trim((string) ($branch['phone'] ?? ''));
$company_po = trim((string) ($branch['pincode'] ?? ''));

$adminRoot = realpath(__DIR__) ?: __DIR__;
$logo_tag = '';
$logo_path = trim((string) ($branch['logo_path'] ?? ''));
if ($logo_path !== '') {
    $logo_norm = str_replace(['\\'], '/', $logo_path);
    $logo_norm = ltrim($logo_norm, '/');
    $full_logo = realpath($adminRoot . '/' . $logo_norm);
    if ($full_logo && is_readable($full_logo) && strpos($full_logo, $adminRoot) === 0) {
        $src = str_replace('\\', '/', substr($full_logo, strlen($adminRoot) + 1));
        $logo_tag = '<img class="hdr-logo" src="' . kyc_form_pdf_h($src) . '" alt="" />';
    }
}

$type_map = [];
$type_rows = getList('SELECT id, name FROM tbl_document_types WHERE status = 1');
if (!is_array($type_rows)) {
    $type_rows = [];
}
foreach ($type_rows as $tr) {
    $tid = (int) ($tr['id'] ?? 0);
    if ($tid > 0) {
        $type_map[$tid] = (string) ($tr['name'] ?? '');
    }
}

$docs_html = '';
$docs_raw = [];
if (!empty($customer['share_holder_documents'])) {
    $decoded = json_decode((string) $customer['share_holder_documents'], true);
    if (is_array($decoded)) {
        $docs_raw = $decoded;
    }
}
$row_count = 0;
foreach ($docs_raw as $d) {
    if (!is_array($d)) {
        continue;
    }
    $path = isset($d['path']) ? trim((string) $d['path']) : '';
    if ($path === '' || !preg_match('#^uploads/customers/share_holders/[A-Za-z0-9_.-]+$#', $path)) {
        continue;
    }
    $type_id = isset($d['document_type_id']) ? (int) $d['document_type_id'] : 0;
    $type_name = $type_id > 0 && isset($type_map[$type_id]) ? $type_map[$type_id] : '';
    if ($type_name === '') {
        $type_name = $type_id > 0 ? ('Type #' . $type_id) : '—';
    }
    $dname = isset($d['name']) ? (string) $d['name'] : basename($path);
    $expiry = isset($d['expiry_date']) && $d['expiry_date'] !== null && (string) $d['expiry_date'] !== ''
        ? (string) $d['expiry_date'] : '';
    $docs_html .= '<tr><td>' . kyc_form_pdf_h($type_name) . '</td><td>' . kyc_form_pdf_h($dname) . '</td><td>'
        . kyc_form_pdf_h(kyc_form_pdf_fmt_date($expiry)) . '</td></tr>';
    ++$row_count;
}
if ($row_count === 0) {
    $docs_html = '<tr><td colspan="3" style="text-align:center;color:#666;">No documents uploaded</td></tr>';
}

$c_name = trim((string) ($customer['name'] ?? ''));
$addr_parts = array_filter([
    trim((string) ($customer['billing_address1'] ?? '')),
    trim((string) ($customer['billing_address2'] ?? '')),
    trim((string) ($customer['billing_city'] ?? '')),
    trim((string) ($customer['billing_state'] ?? '')),
    trim((string) ($customer['billing_zip_code'] ?? '')),
    trim((string) ($customer['billing_country'] ?? '')),
], static function ($v) {
    return $v !== '';
});
$address = implode(', ', $addr_parts);
$mob = trim(trim((string) ($customer['mobile_country_code'] ?? '')) . ' ' . trim((string) ($customer['mobile_no'] ?? '')));
$mail = trim((string) ($customer['mail_id'] ?? ''));
$dob = kyc_form_pdf_fmt_date((string) ($customer['date1'] ?? ''));
$nationality = trim((string) ($customer['nationality_name'] ?? ''));

$id_no = trim((string) ($customer['national_id'] ?? ''));
if ($id_no === '') {
    $id_no = trim((string) ($customer['identity_no'] ?? ''));
}
$id_type_guess = trim((string) ($customer['national_id'] ?? '')) !== ''
    ? 'National ID' : (trim((string) ($customer['identity_no'] ?? '')) !== '' ? 'Identity' : '');

$id_issue = kyc_form_pdf_fmt_date((string) ($customer['identity_issue_date'] ?? ''));
$id_exp = kyc_form_pdf_fmt_date((string) ($customer['identity_expiry_date'] ?? ''));

$print_today = date('d/m/Y');

$e = static function (string $s): string {
    return kyc_form_pdf_h($s);
};

$html = '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
@page { margin: 12mm 14mm; }
body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 9.5pt;
    color: #111;
    margin: 0;
}
table { width: 100%; border-collapse: collapse; }
td, th {
    border: 1px solid #000;
    padding: 5px 7px;
    vertical-align: top;
}
th { background: #f0f0f0; font-weight: bold; }
.hdr-wrap {
    width: 100%;
    border-bottom: 3px solid #000;
    padding-bottom: 8px;
    margin-bottom: 10px;
}
.hdr-row { width: 100%; }
.hdr-left { width: 58%; vertical-align: top; }
.hdr-right { width: 42%; vertical-align: top; text-align: right; font-size: 9pt; }
.hdr-logo { width: 52px; height: 52px; object-fit: contain; display: block; margin-bottom: 4px; }
.co-name { font-size: 15pt; font-weight: bold; margin: 0 0 2px 0; }
.co-email { margin: 0; font-size: 9pt; }
.doc-title {
    text-align: center;
    font-size: 12pt;
    font-weight: bold;
    text-decoration: underline;
    margin: 10px 0 12px 0;
}
.two-col-label { width: 22%; font-weight: bold; background: #fafafa; }
.section-gap { margin-top: 12px; }
.decl { font-size: 8.5pt; line-height: 1.35; text-align: justify; margin: 8px 0; }
.sig-row td { border: none; padding-top: 14px; font-size: 9pt; }
.cb { font-family: DejaVu Sans, sans-serif; }
.docs-h { font-weight: bold; text-decoration: underline; margin: 16px 0 6px 0; }
</style>
</head>
<body>
<table class="hdr-wrap"><tr class="hdr-row">
<td class="hdr-left">'
. $logo_tag . '
<p class="co-name">' . $e($company_name) . '</p>
<p class="co-email">Email: ' . $e($company_email) . '</p>
</td>
<td class="hdr-right">
<div>Tel: ' . $e($company_phone) . '</div>
<div>P.O Box: ' . $e($company_po) . '</div>
<div style="margin-top:6px;font-weight:bold;">' . $e($company_name) . '</div>
</td>
</tr></table>

<div class="doc-title">KYC FORM FOR INDIVIDUALS</div>

<table>
<tr><td class="two-col-label">Name (as per ID)</td><td colspan="3">' . $e($c_name) . '</td></tr>
<tr><td class="two-col-label">Address</td><td colspan="3">' . $e($address) . '</td></tr>
<tr><td class="two-col-label">Mob No</td><td colspan="3">' . $e($mob) . '</td></tr>
<tr><td class="two-col-label">Email</td><td colspan="3">' . $e($mail) . '</td></tr>
<tr><td class="two-col-label">Date of Birth</td><td colspan="3">' . $e($dob) . '</td></tr>
<tr><td class="two-col-label">Nationality</td><td colspan="3">' . $e($nationality) . '</td></tr>
</table>

<table class="section-gap">
<tr>
<td style="width:22%;font-weight:bold;">Source of Fund</td><td style="width:28%;">&nbsp;</td>
<td style="width:22%;font-weight:bold;">Purpose of transaction</td><td style="width:28%;">&nbsp;</td>
</tr>
<tr>
<td style="font-weight:bold;">ID Type (Passport, EID)</td><td>' . $e($id_type_guess) . '</td>
<td style="font-weight:bold;">ID No</td><td>' . $e($id_no) . '</td>
</tr>
<tr>
<td style="font-weight:bold;">ID Issued Date</td><td>' . $e($id_issue) . '</td>
<td style="font-weight:bold;">ID Expiry Date</td><td>' . $e($id_exp) . '</td>
</tr>
</table>

<table class="section-gap">
<tr>
<td style="width:22%;font-weight:bold;">Company Name</td><td style="width:28%;">' . $e($company_name) . '</td>
<td style="width:22%;font-weight:bold;">Designation</td><td style="width:28%;">&nbsp;</td>
</tr>
</table>

<table class="section-gap">
<tr><td colspan="4">
<strong>PEP status:</strong> Are you a Politically Exposed Person or relative of a PEP?
<span class="cb"> Yes &#9744; No &#9744;</span>
</td></tr>
<tr><td colspan="4" style="padding-top:6px;">
<strong>Payment details:</strong>
<span class="cb"> Cash &#9744; Card &#9744; Online Transfer &#9744; Old Gold Exchange &#9744; Others __________</span>
</td></tr>
</table>

<p style="margin-top:12px;"><strong>DECLARATION:</strong></p>
<p class="decl">
I/We confirm that I/we have read and understood the applicable AML/CFT policy requirements and declare that the information provided in this form is true, correct, and complete to the best of my/our knowledge. I/We undertake to inform the company of any changes to this information without undue delay.
</p>
<p class="decl">
I/We understand that the company may verify the information provided and may refuse or discontinue business relationships where information is incomplete, inaccurate, or where required due diligence cannot be completed.
</p>

<table class="sig-row"><tr>
<td style="width:50%;">Date: ' . $e($print_today) . '</td>
<td style="width:50%;text-align:right;">
<div style="border-top:1px solid #000;width:220px;display:inline-block;text-align:center;padding-top:4px;">
Customer Signature (As per ID)
</div>
</td>
</tr></table>

<p class="docs-h">DOCUMENTS :</p>
<table>
<tr><th style="width:34%;">Document Type</th><th style="width:42%;">Document Name</th><th style="width:24%;">Expiry Date</th></tr>'
. $docs_html
. '</table>
</body>
</html>';

$options = new Options();
$options->setChroot([$adminRoot]);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$fn = 'KYC-' . preg_replace('/[^a-zA-Z0-9_-]+/', '_', $c_name ?: 'customer') . '-' . $customer_id . '.pdf';
$dompdf->stream($fn, ['Attachment' => false]);
