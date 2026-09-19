<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['action']) || $_POST['action'] !== 'save_ledger') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$name = esc($_POST['name'] ?? '');
$mobile_no = esc($_POST['mobile_no'] ?? '');
if ($name === '') {
    echo json_encode(['success' => false, 'message' => 'Ledger name is required']);
    exit;
}
if ($mobile_no === '') {
    echo json_encode(['success' => false, 'message' => 'Mobile number is required']);
    exit;
}
$customer_type_id = (int)($_POST['customer_type_id'] ?? 0);
if ($customer_type_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Customer type is required']);
    exit;
}

$alternate_name = esc($_POST['alternate_name'] ?? '');
$first_name = esc($_POST['first_name'] ?? '');
$last_name = esc($_POST['last_name'] ?? '');
$mobile_country_code = esc($_POST['mobile_country_code'] ?? '91');
$phone_no = esc($_POST['phone_no'] ?? '');
$mail_id = esc($_POST['mail_id'] ?? '');
$identity_no = esc($_POST['identity_no'] ?? '');
$national_id = esc($_POST['national_id'] ?? '');
$trade_no = esc($_POST['trade_no'] ?? '');
$identity_issue_date = !empty($_POST['identity_issue_date']) ? esc($_POST['identity_issue_date']) : null;
$identity_expiry_date = !empty($_POST['identity_expiry_date']) ? esc($_POST['identity_expiry_date']) : null;
$special_day = !empty($_POST['special_day']) ? esc($_POST['special_day']) : null;
$registration_no = esc($_POST['registration_no'] ?? '');
$registration_date = !empty($_POST['registration_date']) ? esc($_POST['registration_date']) : null;
$nationality_id = (int)($_POST['nationality_id'] ?? 0);
$country_id = (int)($_POST['country_id'] ?? 0);
$group_id = (int)($_POST['group_id'] ?? 0);
$sundry_debtors_id = (int)($_POST['sundry_debtors_id'] ?? 1);
$ledger_name_capital = isset($_POST['ledger_name_capital']) ? 1 : 0;
$kyc = isset($_POST['kyc']) ? 1 : 0;
$aml = isset($_POST['aml']) ? 1 : 0;
$bill_to_bill = (int)($_POST['bill_to_bill'] ?? 0);
$billing_address1 = esc($_POST['billing_address1'] ?? '');
$billing_address2 = esc($_POST['billing_address2'] ?? '');
$billing_country = esc($_POST['billing_country'] ?? '');
$billing_state = esc($_POST['billing_state'] ?? '');
$billing_zip_code = esc($_POST['billing_zip_code'] ?? '');
$shipping_address1 = esc($_POST['shipping_address1'] ?? '');
$shipping_address2 = esc($_POST['shipping_address2'] ?? '');
$shipping_country = esc($_POST['shipping_country'] ?? '');
$shipping_state = esc($_POST['shipping_state'] ?? '');
$shipping_zip_code = esc($_POST['shipping_zip_code'] ?? '');
$bank_account_no = esc($_POST['bank_account_no'] ?? '');
$bank_name = esc($_POST['bank_name'] ?? '');
$bank_ifsc_code = esc($_POST['bank_ifsc_code'] ?? '');
$bank_branch = esc($_POST['bank_branch'] ?? '');
$notes = esc($_POST['notes'] ?? '');
$opening_balance = (float)($_POST['opening_balance'] ?? 0);
$opening_crdr = ($_POST['opening_crdr'] ?? 'Cr') === 'Dr' ? 'Dr' : 'Cr';

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

// Check duplicate name
$exists = getRecord("SELECT id FROM tbl_customers WHERE name = '" . $name . "' AND status = 1 LIMIT 1");
if ($exists) {
    echo json_encode(['success' => false, 'message' => 'A ledger with this name already exists.']);
    exit;
}

// Ledger photo (optional)
$ledger_photo = '';
if (isset($_FILES['ledger_photo']) && $_FILES['ledger_photo']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = '../uploads/customers/';
    if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);
    $ext = strtolower(pathinfo($_FILES['ledger_photo']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
        $fname = 'customer_' . time() . '_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($_FILES['ledger_photo']['tmp_name'], $upload_dir . $fname)) {
            $ledger_photo = 'uploads/customers/' . $fname;
        }
    }
}

$date1 = null;
$date2 = null;
$item_tax_json = '{}';
$share_holders_json = '[]';
$share_holder_documents_json = '[]';

$sql = "INSERT INTO tbl_customers 
(name, alternate_name, first_name, last_name, mobile_country_code, mobile_no, phone_no, mail_id, 
 identity_no, national_id, trade_no, identity_issue_date, identity_expiry_date, special_day, 
 customer_type_id, registration_no, registration_date, nationality_id, country_id, 
 date1, date2, group_id, sundry_debtors_id,
 ledger_name_capital, kyc, aml, bill_to_bill,
 billing_address1, billing_address2, billing_country, billing_state, billing_zip_code,
 shipping_address1, shipping_address2, shipping_country, shipping_state, shipping_zip_code,
bank_account_no, bank_name, bank_ifsc_code, bank_branch, notes, item_tax_data, share_holders_data, share_holder_documents, ledger_photo, created_at)
VALUES 
('$name', '$alternate_name', '$first_name', '$last_name', '$mobile_country_code', '$mobile_no', '$phone_no', '$mail_id',
 '$identity_no', '$national_id', '$trade_no',
 " . ($identity_issue_date ? "'$identity_issue_date'" : "NULL") . ", " . ($identity_expiry_date ? "'$identity_expiry_date'" : "NULL") . ", " . ($special_day ? "'$special_day'" : "NULL") . ",
 $customer_type_id, '$registration_no', " . ($registration_date ? "'$registration_date'" : "NULL") . ",
 $nationality_id, $country_id,
 NULL, NULL,
 $group_id, $sundry_debtors_id,
 $ledger_name_capital, $kyc, $aml, $bill_to_bill,
 '$billing_address1', '$billing_address2', '$billing_country', '$billing_state', '$billing_zip_code',
 '$shipping_address1', '$shipping_address2', '$shipping_country', '$shipping_state', '$shipping_zip_code',
 '$bank_account_no', '$bank_name', '$bank_ifsc_code', '$bank_branch', '$notes', '$item_tax_json', '$share_holders_json', '$share_holder_documents_json', '$ledger_photo', NOW())";

if (!mysqli_query($conn, $sql)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save ledger: ' . mysqli_error($conn)]);
    exit;
}

$customer_id = mysqli_insert_id($conn);

// Opening balance entry in tbl_customer_ledger
if ($opening_balance > 0) {
    $debit_amount = $opening_crdr === 'Dr' ? $opening_balance : 0;
    $credit_amount = $opening_crdr === 'Cr' ? $opening_balance : 0;
    $balance_amount = $opening_crdr === 'Dr' ? $opening_balance : -$opening_balance;
    $today = date('Y-m-d');
    $ledger_sql = "INSERT INTO tbl_customer_ledger 
(customer_id, customer_name, transaction_type, transaction_id, transaction_no, transaction_date, 
 debit_amount, credit_amount, debit_gold, credit_gold, debit_silver, credit_silver,
 balance_amount, balance_gold, balance_silver, description, reference_no, against_ledger, against_invoice_no, status, created_by, created_at)
VALUES 
($customer_id, '$name', 'opening', 0, 'OPENING', '$today',
 $debit_amount, $credit_amount, 0, 0, 0, 0,
 $balance_amount, 0, 0, 'Opening balance', '', '', '', 1, $user_id, NOW())";
    @mysqli_query($conn, $ledger_sql);
}

echo json_encode(['success' => true, 'message' => 'Ledger saved successfully.', 'customer_id' => $customer_id, 'customer_name' => $name]);
