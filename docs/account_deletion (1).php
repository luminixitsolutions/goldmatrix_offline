<?php
/**
 * FlyPhones B2B account deletion API.
 * Upload to: /admin2/b2bapi/account_deletion.php
 *
 * PHP 7.3 compatible. Do not use mysqli_stmt::get_result().
 * Actions: status | send_otp | delete | deactivate (alias of delete)
 * Reason is optional.
 */
header('Content-Type: application/json; charset=utf-8');
http_response_code(200);

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(200);
        }
        echo json_encode(array(
            'status' => 0,
            'message' => 'Server error while processing account deletion.',
            'show_price' => 0,
            'mask_price' => 1,
        ));
    }
});

require_once __DIR__ . '/config.php';

function b2b_db()
{
    foreach (array('con', 'conn', 'mysqli', 'connection', 'db', 'mysqli_con') as $name) {
        if (isset($GLOBALS[$name]) && $GLOBALS[$name] instanceof mysqli) {
            return $GLOBALS[$name];
        }
    }
    b2b_json(array('status' => 0, 'message' => 'Database connection not found'));
}

function b2b_json($payload)
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

function b2b_esc(mysqli $db, $value)
{
    return $db->real_escape_string((string) $value);
}

function b2b_columns(mysqli $db, $table)
{
    $cols = array();
    $safe = str_replace('`', '', $table);
    $res = $db->query('SHOW COLUMNS FROM `' . $safe . '`');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cols[strtolower($row['Field'])] = $row['Field'];
        }
    }
    return $cols;
}

function b2b_pick($cols, $candidates)
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
    $sql = "SELECT TABLE_NAME
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND COLUMN_NAME IN ('uid', 'Phone', 'phone', 'Fname', 'EmailId')
            GROUP BY TABLE_NAME
            HAVING SUM(COLUMN_NAME = 'uid') > 0
               AND (
                    SUM(COLUMN_NAME IN ('Phone', 'phone')) > 0
                    OR SUM(COLUMN_NAME = 'Fname') > 0
               )
            ORDER BY
              SUM(COLUMN_NAME IN ('Phone', 'phone')) DESC,
              SUM(COLUMN_NAME = 'Fname') DESC,
              TABLE_NAME ASC
            LIMIT 1";
    $res = $db->query($sql);
    if ($res && ($row = $res->fetch_assoc()) && !empty($row['TABLE_NAME'])) {
        return $row['TABLE_NAME'];
    }

    foreach (array(
        'customers', 'b2b_customers', 'customer', 'tbl_customers',
        'tbl_customer', 'b2b_user', 'b2b_users', 'users', 'user',
    ) as $table) {
        $cols = b2b_columns($db, $table);
        if (!$cols) {
            continue;
        }
        if (b2b_pick($cols, array('uid')) && b2b_pick($cols, array('Phone', 'phone', 'Fname'))) {
            return $table;
        }
    }

    b2b_json(array('status' => 0, 'message' => 'Customer table not found'));
}

function b2b_find_customer(mysqli $db, $table, $cols, $customerId)
{
    $idCol = b2b_pick($cols, array('uid', 'Id', 'id', 'customer_id', 'CID'));
    if ($idCol === null) {
        b2b_json(array('status' => 0, 'message' => 'Customer id column not found'));
    }
    $safeTable = str_replace('`', '', $table);
    $safeIdCol = str_replace('`', '', $idCol);
    $safeId = b2b_esc($db, $customerId);
    $res = $db->query("SELECT * FROM `{$safeTable}` WHERE `{$safeIdCol}` = '{$safeId}' LIMIT 1");
    if (!$res) {
        b2b_json(array('status' => 0, 'message' => 'Unable to load customer'));
    }
    $row = $res->fetch_assoc();
    return array($idCol, $row);
}

function b2b_is_active($row, $cols)
{
    if (isset($row['account_deleted']) && (string) $row['account_deleted'] === '1') {
        return false;
    }
    if (isset($row['deleted_at']) && trim((string) $row['deleted_at']) !== '' && $row['deleted_at'] !== '0000-00-00 00:00:00') {
        return false;
    }
    $statusCol = b2b_pick($cols, array('account_status', 'Status', 'status', 'Active', 'active', 'is_active'));
    if ($statusCol === null) {
        return true;
    }
    $value = strtolower(trim((string) $row[$statusCol]));
    return !in_array($value, array('0', '2', 'deleted', 'inactive', 'disabled', 'no'), true);
}

function b2b_phone($row, $cols)
{
    $col = b2b_pick($cols, array('Phone', 'phone', 'mobile', 'Username', 'username'));
    return $col ? trim((string) $row[$col]) : '';
}

function b2b_make_otp()
{
    return str_pad((string) mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
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
    b2b_json(array('status' => 0, 'message' => 'customer_id is required'));
}

$table = b2b_customer_table($db);
$cols = b2b_columns($db, $table);
list($idCol, $customer) = b2b_find_customer($db, $table, $cols, $customerId);
if (!$customer) {
    b2b_json(array('status' => 0, 'message' => 'Customer not found'));
}

$accountActive = b2b_is_active($customer, $cols) ? 1 : 0;
$phone = b2b_phone($customer, $cols);

if ($action === 'status') {
    b2b_json(array(
        'status' => 1,
        'account_active' => $accountActive,
        'has_pending_request' => 0,
        'message' => $accountActive === 1 ? '' : 'This account has been deleted and can no longer be used.',
    ));
}

if ($action === 'send_otp') {
    if ($accountActive !== 1) {
        b2b_json(array(
            'status' => 0,
            'account_active' => 0,
            'message' => 'This account has already been deleted.',
        ));
    }
    b2b_json(array(
        'status' => 1,
        'otp' => b2b_make_otp(),
        'phone' => $phone,
        'message' => 'OTP sent',
    ));
}

if ($action !== 'delete') {
    b2b_json(array('status' => 0, 'message' => 'Invalid action'));
}

if ($accountActive !== 1) {
    b2b_json(array(
        'status' => 0,
        'account_active' => 0,
        'message' => 'This account has already been deleted.',
    ));
}

$enteredOtp = b2b_post('YourOtp');
$serverOtp = b2b_post('getotp');
if ($enteredOtp === '' || $serverOtp === '') {
    b2b_json(array('status' => 0, 'message' => 'Please enter OTP'));
}
if ($enteredOtp !== $serverOtp) {
    b2b_json(array('status' => 0, 'message' => 'Invalid OTP'));
}

$reason = b2b_post('reason');
$now = date('Y-m-d H:i:s');
$anonPhone = 'deleted_' . $customerId;
$anonEmail = 'deleted_' . $customerId . '@deleted.invalid';

$sets = array();
$addSet = function ($candidates, $value) use (&$sets, $cols, $db) {
    $col = b2b_pick($cols, $candidates);
    if ($col === null) {
        return;
    }
    $sets[] = '`' . str_replace('`', '', $col) . "` = '" . b2b_esc($db, $value) . "'";
};

$addSet(array('Fname', 'fname', 'first_name', 'name'), 'Deleted');
$addSet(array('Lname', 'lname', 'last_name'), 'User');
$addSet(array('Phone', 'phone', 'mobile', 'Username', 'username'), $anonPhone);
$addSet(array('EmailId', 'email', 'Email'), $anonEmail);
$addSet(array('Address', 'address'), '');
$addSet(array('City', 'city'), '');
$addSet(array('State', 'state'), '');
$addSet(array('Pincode', 'pincode', 'zip'), '');
$addSet(array('photo', 'profile_photo', 'image', 'profile_image', 'Photo'), '');
$addSet(array('fcm_token', 'device_token', 'push_token'), '');
$addSet(array('token', 'auth_token', 'session_token', 'api_token'), '');
$addSet(array('GST', 'gst', 'gst_no'), '');
$addSet(array('Company', 'company', 'ShopName', 'shop_name'), '');
$addSet(array('deleted_at'), $now);
$addSet(array('account_deleted'), '1');
$addSet(array('deletion_reason'), $reason);

$statusCol = b2b_pick($cols, array('account_status', 'Status', 'status', 'Active', 'active', 'is_active'));
if ($statusCol !== null) {
    $sets[] = '`' . str_replace('`', '', $statusCol) . "` = '0'";
}

if (!$sets) {
    b2b_json(array('status' => 0, 'message' => 'Unable to anonymize customer record'));
}

$safeTable = str_replace('`', '', $table);
$safeIdCol = str_replace('`', '', $idCol);
$safeId = b2b_esc($db, $customerId);
$sql = "UPDATE `{$safeTable}` SET " . implode(', ', $sets) . " WHERE `{$safeIdCol}` = '{$safeId}' LIMIT 1";
$ok = $db->query($sql);
if (!$ok) {
    b2b_json(array('status' => 0, 'message' => 'Unable to delete account. Please try again.'));
}

b2b_ensure_audit($db);
$safeReason = b2b_esc($db, $reason);
$db->query("INSERT INTO account_deletion_requests (customer_id, reason, deleted_at) VALUES ('{$safeId}', '{$safeReason}', '{$now}')");

b2b_json(array(
    'status' => 1,
    'account_active' => 0,
    'message' => 'Your account has been permanently deleted.',
));
