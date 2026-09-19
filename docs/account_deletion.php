<?php
/**
 * FlyPhones B2B account deletion API.
 * Upload to: /admin2/b2bapi/account_deletion.php
 *
 * Actions: status | send_otp | delete | deactivate (alias of delete)
 * Reason is optional. Empty reason does not block deletion.
 *
 * Personal data is anonymized. Order/payment/invoice rows are kept.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';

function b2b_db()
{
    foreach (['con', 'conn', 'mysqli', 'connection', 'db'] as $name) {
        if (isset($GLOBALS[$name]) && $GLOBALS[$name] instanceof mysqli) {
            return $GLOBALS[$name];
        }
    }
    b2b_json(['status' => 0, 'message' => 'Database connection not found']);
}

function b2b_json(array $payload)
{
    if (isset($GLOBALS['show_price'])) {
        $payload['show_price'] = (int) $GLOBALS['show_price'];
    } elseif (!isset($payload['show_price'])) {
        $payload['show_price'] = 0;
    }
    if (isset($GLOBALS['mask_price'])) {
        $payload['mask_price'] = (int) $GLOBALS['mask_price'];
    } elseif (!isset($payload['mask_price'])) {
        $payload['mask_price'] = 1;
    }
    echo json_encode($payload);
    exit;
}

function b2b_post($key, $default = '')
{
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
}

function b2b_table_exists(mysqli $db, $table)
{
    $table = $db->real_escape_string($table);
    $res = $db->query("SHOW TABLES LIKE '{$table}'");
    return $res && $res->num_rows > 0;
}

function b2b_columns(mysqli $db, $table)
{
    $cols = [];
    $res = $db->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cols[strtolower($row['Field'])] = $row['Field'];
        }
    }
    return $cols;
}

function b2b_pick(array $cols, array $candidates)
{
    foreach ($candidates as $name) {
        $key = strtolower($name);
        if (isset($cols[$key])) {
            return $cols[$key];
        }
    }
    return null;
}

function b2b_customer_table(mysqli $db)
{
    foreach (['customers', 'b2b_customers', 'customer', 'tbl_customers'] as $table) {
        if (b2b_table_exists($db, $table)) {
            return $table;
        }
    }
    b2b_json(['status' => 0, 'message' => 'Customer table not found']);
}

function b2b_find_customer(mysqli $db, $table, array $cols, $customerId)
{
    $idCol = b2b_pick($cols, ['uid', 'Id', 'id', 'customer_id', 'CID']);
    if ($idCol === null) {
        b2b_json(['status' => 0, 'message' => 'Customer id column not found']);
    }
    $stmt = $db->prepare("SELECT * FROM `{$table}` WHERE `{$idCol}` = ? LIMIT 1");
    $stmt->bind_param('s', $customerId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return [$idCol, $row];
}

function b2b_is_active(array $row, array $cols)
{
    if (isset($row['account_deleted']) && (string) $row['account_deleted'] === '1') {
        return false;
    }
    if (isset($row['deleted_at']) && trim((string) $row['deleted_at']) !== '' && $row['deleted_at'] !== '0000-00-00 00:00:00') {
        return false;
    }
    $statusCol = b2b_pick($cols, ['account_status', 'Status', 'status', 'Active', 'active', 'is_active']);
    if ($statusCol === null) {
        return true;
    }
    $value = strtolower(trim((string) $row[$statusCol]));
    return !in_array($value, ['0', '2', 'deleted', 'inactive', 'disabled', 'no'], true);
}

function b2b_phone(array $row, array $cols)
{
    $col = b2b_pick($cols, ['Phone', 'phone', 'mobile', 'Username', 'username']);
    return $col ? trim((string) $row[$col]) : '';
}

function b2b_ensure_audit(mysqli $db)
{
    $db->query(
        "CREATE TABLE IF NOT EXISTS account_deletion_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id VARCHAR(64) NOT NULL,
            reason TEXT NULL,
            deleted_at DATETIME NOT NULL,
            INDEX (customer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

$db = b2b_db();
$action = strtolower(b2b_post('action', 'status'));
if ($action === 'deactivate') {
    $action = 'delete';
}

$customerId = b2b_post('customer_id');
if ($customerId === '') {
    b2b_json(['status' => 0, 'message' => 'customer_id is required']);
}

$table = b2b_customer_table($db);
$cols = b2b_columns($db, $table);
list($idCol, $customer) = b2b_find_customer($db, $table, $cols, $customerId);
if (!$customer) {
    b2b_json(['status' => 0, 'message' => 'Customer not found']);
}

$accountActive = b2b_is_active($customer, $cols) ? 1 : 0;
$phone = b2b_phone($customer, $cols);

if ($action === 'status') {
    b2b_json([
        'status' => 1,
        'account_active' => $accountActive,
        'has_pending_request' => 0,
        'message' => $accountActive === 1 ? '' : 'This account has been deleted and can no longer be used.',
    ]);
}

if ($action === 'send_otp') {
    if ($accountActive !== 1) {
        b2b_json([
            'status' => 0,
            'account_active' => 0,
            'message' => 'This account has already been deleted.',
        ]);
    }
    $otp = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    b2b_json([
        'status' => 1,
        'otp' => $otp,
        'phone' => $phone,
        'message' => 'OTP sent',
    ]);
}

if ($action !== 'delete') {
    b2b_json(['status' => 0, 'message' => 'Invalid action']);
}

if ($accountActive !== 1) {
    b2b_json([
        'status' => 0,
        'account_active' => 0,
        'message' => 'This account has already been deleted.',
    ]);
}

$enteredOtp = b2b_post('YourOtp');
$serverOtp = b2b_post('getotp');
if ($enteredOtp === '' || $serverOtp === '') {
    b2b_json(['status' => 0, 'message' => 'Please enter OTP']);
}
if ($enteredOtp !== $serverOtp) {
    b2b_json(['status' => 0, 'message' => 'Invalid OTP']);
}

$reason = b2b_post('reason');
$now = date('Y-m-d H:i:s');
$anonPhone = 'deleted_' . $customerId;
$anonEmail = 'deleted_' . $customerId . '@deleted.invalid';

$assignments = [];
$params = [];
$types = '';

$setIf = function ($candidates, $value, $type = 's') use (&$assignments, &$params, &$types, $cols) {
    $col = b2b_pick($cols, $candidates);
    if ($col === null) {
        return;
    }
    $assignments[] = "`{$col}` = ?";
    $params[] = $value;
    $types .= $type;
};

$setIf(['Fname', 'fname', 'first_name', 'name'], 'Deleted');
$setIf(['Lname', 'lname', 'last_name'], 'User');
$setIf(['Phone', 'phone', 'mobile', 'Username', 'username'], $anonPhone);
$setIf(['EmailId', 'email', 'Email'], $anonEmail);
$setIf(['Address', 'address'], '');
$setIf(['City', 'city'], '');
$setIf(['State', 'state'], '');
$setIf(['Pincode', 'pincode', 'zip'], '');
$setIf(['photo', 'profile_photo', 'image', 'profile_image', 'Photo'], '');
$setIf(['fcm_token', 'device_token', 'push_token'], '');
$setIf(['token', 'auth_token', 'session_token', 'api_token'], '');
$setIf(['GST', 'gst', 'gst_no'], '');
$setIf(['Company', 'company', 'ShopName', 'shop_name'], '');
$setIf(['deleted_at'], $now);
$setIf(['account_deleted'], '1');
$setIf(['deletion_reason'], $reason);

$statusCol = b2b_pick($cols, ['account_status', 'Status', 'status', 'Active', 'active', 'is_active']);
if ($statusCol !== null) {
    $assignments[] = "`{$statusCol}` = ?";
    $params[] = '0';
    $types .= 's';
}

if (!$assignments) {
    b2b_json(['status' => 0, 'message' => 'Unable to anonymize customer record']);
}

$sql = "UPDATE `{$table}` SET " . implode(', ', $assignments) . " WHERE `{$idCol}` = ? LIMIT 1";
$params[] = $customerId;
$types .= 's';

$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    b2b_json(['status' => 0, 'message' => 'Unable to delete account. Please try again.']);
}

b2b_ensure_audit($db);
$log = $db->prepare('INSERT INTO account_deletion_requests (customer_id, reason, deleted_at) VALUES (?, ?, ?)');
if ($log) {
    $log->bind_param('sss', $customerId, $reason, $now);
    $log->execute();
    $log->close();
}

b2b_json([
    'status' => 1,
    'account_active' => 0,
    'message' => 'Your account has been permanently deleted.',
]);
