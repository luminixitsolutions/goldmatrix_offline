<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/includes/auragold_department_schema.php';
auragold_ensure_department_schema($conn);

// Standard ledger groups for dropdown
$ledger_groups = [
    ['id' => 1, 'name' => 'Sundry Debtors'],
    ['id' => 2, 'name' => 'Sundry Creditors'],
    ['id' => 3, 'name' => 'Bank Accounts'],
    ['id' => 4, 'name' => 'Cash'],
    ['id' => 5, 'name' => 'Sales'],
    ['id' => 6, 'name' => 'Purchase'],
    ['id' => 7, 'name' => 'Direct Expenses'],
    ['id' => 8, 'name' => 'Indirect Expenses'],
    ['id' => 9, 'name' => 'Direct Income'],
    ['id' => 10, 'name' => 'Indirect Income'],
    ['id' => 11, 'name' => 'Fixed Assets'],
    ['id' => 12, 'name' => 'Current Assets'],
    ['id' => 13, 'name' => 'Current Liabilities'],
    ['id' => 14, 'name' => 'Investment'],
];

// Sundry Debtors/Creditors options (Sundry Creditors first = default for Job Workers)
$sundry_options = [
    ['id' => 2, 'name' => 'Sundry Creditors'],
    ['id' => 1, 'name' => 'Sundry Debtors'],
];

// Get customer types from database
$customer_types = [];
$customer_types_result = @mysqli_query($conn, "SELECT id, name FROM tbl_customer_types WHERE status = 1 ORDER BY name ASC");
if ($customer_types_result) {
    while ($row = mysqli_fetch_assoc($customer_types_result)) {
        $customer_types[] = $row;
    }
}

// Find Job Worker customer type ID
$job_worker_type_id = 0;
foreach ($customer_types as $ct) {
    if (strtolower($ct['name']) === 'job worker') {
        $job_worker_type_id = (int)$ct['id'];
        break;
    }
}

// Get countries
$countries = [];
$countries_result = @mysqli_query($conn, "SELECT id, name FROM tbl_countries WHERE status = 1 ORDER BY name ASC");
if ($countries_result) {
    while ($row = mysqli_fetch_assoc($countries_result)) {
        $countries[] = $row;
    }
}
$countries_ledger = $countries;
require_once __DIR__ . '/includes/international-dial-codes.php';

// Get nationalities
$nationalities = [];
$nationalities_result = @mysqli_query($conn, "SELECT id, name FROM tbl_nationalities WHERE status = 1 ORDER BY name ASC");
if ($nationalities_result) {
    while ($row = mysqli_fetch_assoc($nationalities_result)) {
        $nationalities[] = $row;
    }
}

function tableExists($conn, $table_name) {
    $table_name = mysqli_real_escape_string($conn, $table_name);
    $res = @mysqli_query($conn, "SHOW TABLES LIKE '$table_name'");
    $exists = ($res && mysqli_num_rows($res) > 0);
    if ($res) {
        mysqli_free_result($res);
    }
    return $exists;
}

/** Map legacy process_type values to current Process options. */
function department_normalize_process_type($process) {
    $p = trim((string) $process);
    if ($p === 'Manufacturing Outsource') {
        return 'Manufacturing Outsource';
    }
    return 'Manufacturing Inhouse';
}

function department_process_type_label($process) {
    return department_normalize_process_type($process);
}

$department_allowed_process = ['Manufacturing Inhouse', 'Manufacturing Outsource'];

$flash_success = isset($_SESSION['department_success']) ? $_SESSION['department_success'] : '';
$flash_error = isset($_SESSION['department_error']) ? $_SESSION['department_error'] : '';
unset($_SESSION['department_success'], $_SESSION['department_error']);

$error_message = '';
$success_message = $flash_success;

$departments_table_exists = tableExists($conn, 'tbl_departments');
$users_table_exists = tableExists($conn, 'tbl_department_users');
$map_table_exists = tableExists($conn, 'tbl_department_user_map');

$dept_id_from_query = isset($_GET['dept_id']) ? (int)$_GET['dept_id'] : 0;
$selected_department_id = $dept_id_from_query;

$form = [
    'dept_name' => '',
    'short_code' => '',
    'progress_percent' => '',
    'type' => 'Wt. Wise',
    'process' => 'Manufacturing Inhouse',
    'auto_loss' => 'On',
    'auto_profit' => 'On',
    'calculate_stock' => 0,
    'exclude_jobcard' => 0,
];

$new_user_form = [
    'user_name' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_department'])) {
        $form['dept_name'] = isset($_POST['dept_name']) ? trim($_POST['dept_name']) : '';
        $form['short_code'] = isset($_POST['short_code']) ? trim($_POST['short_code']) : '';
        $form['progress_percent'] = isset($_POST['progress_percent']) ? trim($_POST['progress_percent']) : '';
        $form['type'] = isset($_POST['type']) ? trim($_POST['type']) : 'Wt. Wise';
        $form['process'] = department_normalize_process_type(isset($_POST['process']) ? trim($_POST['process']) : 'Manufacturing Inhouse');
        $form['auto_loss'] = isset($_POST['auto_loss']) ? trim($_POST['auto_loss']) : 'On';
        $form['auto_profit'] = isset($_POST['auto_profit']) ? trim($_POST['auto_profit']) : 'On';
        $form['calculate_stock'] = isset($_POST['calculate_stock']) ? 1 : 0;
        $form['exclude_jobcard'] = isset($_POST['exclude_jobcard']) ? 1 : 0;

        $allowed_types = ['Wt. Wise', 'Against of Weight'];
        $allowed_process = $department_allowed_process;
        $allowed_switch = ['On', 'Off'];

        if ($form['dept_name'] === '') {
            $error_message = 'Department name is required.';
        } elseif ($form['short_code'] === '') {
            $error_message = 'Short code is required.';
        } elseif (!in_array($form['type'], $allowed_types, true)) {
            $error_message = 'Invalid type selected.';
        } elseif (!in_array($form['process'], $allowed_process, true)) {
            $error_message = 'Invalid process selected.';
        } elseif (!in_array($form['auto_loss'], $allowed_switch, true) || !in_array($form['auto_profit'], $allowed_switch, true)) {
            $error_message = 'Invalid On/Off value selected.';
        } elseif ($form['progress_percent'] !== '' && !is_numeric($form['progress_percent'])) {
            $error_message = 'Progress In % must be numeric.';
        } elseif (!$departments_table_exists) {
            $error_message = 'Department table could not be created. Check database permissions.';
        } else {
            $dept_name = esc($form['dept_name']);
            $short_code = esc($form['short_code']);
            $progress_percent = ($form['progress_percent'] === '') ? 'NULL' : (float)$form['progress_percent'];
            $type = esc($form['type']);
            $process = esc($form['process']);
            $auto_loss = ($form['auto_loss'] === 'On') ? 1 : 0;
            $auto_profit = ($form['auto_profit'] === 'On') ? 1 : 0;
            $calculate_stock = (int)$form['calculate_stock'];
            $exclude_jobcard = (int)$form['exclude_jobcard'];

            $edit_department_id = isset($_POST['edit_department_id']) ? (int)$_POST['edit_department_id'] : 0;
            $editing_row = null;
            if ($edit_department_id > 0) {
                $editing_row = getRecord("SELECT id FROM tbl_departments WHERE id = $edit_department_id AND status = 1 LIMIT 1");
            }

            if ($edit_department_id > 0 && !$editing_row) {
                $error_message = 'Department not found or inactive.';
            } else {
                if ($edit_department_id > 0) {
                    $existing = getRecord("SELECT id FROM tbl_departments WHERE short_code = '$short_code' AND status = 1 AND id != $edit_department_id LIMIT 1");
                } else {
                    $existing = getRecord("SELECT id FROM tbl_departments WHERE short_code = '$short_code' AND status = 1 LIMIT 1");
                }
                if ($existing) {
                    $error_message = 'Short code already exists.';
                } elseif ($edit_department_id > 0) {
                    $update_sql = "
                        UPDATE tbl_departments SET
                            dept_name = '$dept_name',
                            short_code = '$short_code',
                            department_type = '$type',
                            process_type = '$process',
                            auto_loss = $auto_loss,
                            auto_profit = $auto_profit,
                            calculate_stock = $calculate_stock,
                            progress_percent = $progress_percent,
                            exclude_jobcard_summary = $exclude_jobcard,
                            updated_at = NOW()
                        WHERE id = $edit_department_id AND status = 1
                    ";
                    if (mysqli_query($conn, $update_sql)) {
                        $_SESSION['department_success'] = 'Department updated successfully.';
                        header('Location: department.php');
                        exit;
                    } else {
                        $error_message = 'Failed to update department.';
                    }
                } else {
                    $insert_sql = "
                        INSERT INTO tbl_departments
                        (dept_name, short_code, department_type, process_type, auto_loss, auto_profit, calculate_stock, progress_percent, exclude_jobcard_summary, status, created_at, updated_at)
                        VALUES
                        ('$dept_name', '$short_code', '$type', '$process', $auto_loss, $auto_profit, $calculate_stock, $progress_percent, $exclude_jobcard, 1, NOW(), NOW())
                    ";

                    if (mysqli_query($conn, $insert_sql)) {
                        $_SESSION['department_success'] = 'Department saved successfully.';
                        header('Location: department.php');
                        exit;
                    } else {
                        $error_message = 'Failed to save department.';
                    }
                }
            }
        }
    }

    if (isset($_POST['save_department_users'])) {
        $selected_department_id = isset($_POST['selected_department_id']) ? (int)$_POST['selected_department_id'] : 0;
        $selected_user_ids = isset($_POST['department_user_ids']) && is_array($_POST['department_user_ids']) ? $_POST['department_user_ids'] : [];

        if (!$users_table_exists || !$map_table_exists) {
            $error_message = 'Department user tables could not be created. Check database permissions.';
        } elseif ($selected_department_id <= 0) {
            $error_message = 'Please select a department first.';
        } else {
            mysqli_query($conn, "UPDATE tbl_department_user_map SET status = 0, updated_at = NOW() WHERE department_id = $selected_department_id");

            foreach ($selected_user_ids as $uid) {
                $user_id = (int)$uid;
                if ($user_id <= 0) {
                    continue;
                }

                $existing_map = getRecord("SELECT id FROM tbl_department_user_map WHERE department_id = $selected_department_id AND user_id = $user_id LIMIT 1");
                if ($existing_map) {
                    mysqli_query($conn, "UPDATE tbl_department_user_map SET status = 1, updated_at = NOW() WHERE id = " . (int)$existing_map['id']);
                } else {
                    mysqli_query($conn, "INSERT INTO tbl_department_user_map (department_id, user_id, status, created_at, updated_at) VALUES ($selected_department_id, $user_id, 1, NOW(), NOW())");
                }
            }

            $_SESSION['department_success'] = 'Department users saved successfully.';
            header('Location: department.php?dept_id=' . $selected_department_id);
            exit;
        }
    }

    if (isset($_POST['add_department_user'])) {
        $new_user_form['user_name'] = isset($_POST['user_name']) ? trim($_POST['user_name']) : '';
        $selected_department_id = isset($_POST['selected_department_id']) ? (int)$_POST['selected_department_id'] : $selected_department_id;

        if (!$users_table_exists) {
            $error_message = 'Department users table could not be created. Check database permissions.';
        } elseif ($new_user_form['user_name'] === '') {
            $error_message = 'User name is required.';
        } else {
            $user_name = esc($new_user_form['user_name']);
            $existing_user = getRecord("SELECT id FROM tbl_department_users WHERE user_name = '$user_name' AND status = 1 LIMIT 1");

            if ($existing_user) {
                $error_message = 'User already exists.';
            } else {
                $ins_user = "INSERT INTO tbl_department_users (user_name, status, created_at, updated_at) VALUES ('$user_name', 1, NOW(), NOW())";
                if (mysqli_query($conn, $ins_user)) {
                    $_SESSION['department_success'] = 'User created successfully.';
                    $redirect_id = $selected_department_id > 0 ? '?dept_id=' . $selected_department_id : '';
                    header('Location: department.php' . $redirect_id);
                    exit;
                } else {
                    $error_message = 'Failed to create user.';
                }
            }
        }
    }
}

if ($flash_error !== '' && $error_message === '') {
    $error_message = $flash_error;
}

/** Department list: only rows from tbl_departments (no hardcoded seed). */
$departments = [];
if ($departments_table_exists) {
    $departments = getList(
        'SELECT id, dept_name, short_code, department_type, process_type, auto_loss, auto_profit, '
        . 'calculate_stock, progress_percent, exclude_jobcard_summary, status, created_at, updated_at '
        . 'FROM tbl_departments WHERE status = 1 ORDER BY dept_name ASC, id ASC'
    );
    if (!is_array($departments)) {
        $departments = [];
    }
}

if ($selected_department_id <= 0 && !empty($departments)) {
    $selected_department_id = (int)$departments[0]['id'];
}

$selected_department = null;
foreach ($departments as $drow) {
    if ((int)$drow['id'] === $selected_department_id) {
        $selected_department = $drow;
        break;
    }
}

if ($dept_id_from_query > 0 && $selected_department === null && !empty($departments)) {
    $selected_department_id = (int)$departments[0]['id'];
    foreach ($departments as $drow) {
        if ((int)$drow['id'] === $selected_department_id) {
            $selected_department = $drow;
            break;
        }
    }
}

$form_edit_department_id = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_department'])) {
    $form_edit_department_id = isset($_POST['edit_department_id']) ? (int)$_POST['edit_department_id'] : 0;
} elseif ($dept_id_from_query > 0) {
    foreach ($departments as $drow) {
        if ((int)$drow['id'] === $dept_id_from_query) {
            $form_edit_department_id = $dept_id_from_query;
            break;
        }
    }
}

if (!($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_department'])) && $form_edit_department_id > 0) {
    foreach ($departments as $drow) {
        if ((int)$drow['id'] !== $form_edit_department_id) {
            continue;
        }
        $form['dept_name'] = $drow['dept_name'];
        $form['short_code'] = $drow['short_code'];
        $form['type'] = $drow['department_type'];
        $form['process'] = department_normalize_process_type($drow['process_type']);
        $form['auto_loss'] = ((int)$drow['auto_loss'] === 1) ? 'On' : 'Off';
        $form['auto_profit'] = ((int)$drow['auto_profit'] === 1) ? 'On' : 'Off';
        $form['calculate_stock'] = (int)$drow['calculate_stock'];
        $form['exclude_jobcard'] = (int)$drow['exclude_jobcard_summary'];
        if ($drow['progress_percent'] !== null && $drow['progress_percent'] !== '') {
            $form['progress_percent'] = is_numeric($drow['progress_percent']) ? (string)$drow['progress_percent'] : '';
        } else {
            $form['progress_percent'] = '';
        }
        break;
    }
}

$users = [];
if ($users_table_exists) {
    $users = getList("SELECT id, user_name FROM tbl_department_users WHERE status = 1 ORDER BY user_name ASC");
}

// Get Job Worker type customers from tbl_customers
$job_worker_users = [];
if ($job_worker_type_id > 0) {
    $job_worker_users = getList("SELECT id, name FROM tbl_customers WHERE customer_type_id = $job_worker_type_id AND status = 1 ORDER BY name ASC");
}

$assigned_user_ids = [];
if ($map_table_exists && $selected_department_id > 0) {
    $assigned_rows = getList("SELECT user_id FROM tbl_department_user_map WHERE department_id = $selected_department_id AND status = 1");
    foreach ($assigned_rows as $arow) {
        $assigned_user_ids[] = (int)$arow['user_id'];
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Department - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?> Software</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <?php include 'header-script.php'; ?>
    <link rel="stylesheet" href="assets/css/mfg-pages-mobile.css">
</head>

<style>
html, body {
    overflow-x: hidden !important;
    background: #f2f3f7;
    /* font-family: "Segoe UI", Arial, sans-serif; */
}

.layout-content {
    overflow-y: auto;
}

.container-fluid {
    padding: 8px 10px 10px !important;
}

.department-page {
    border: 1px solid #d7dce8;
    border-radius: 10px;
    background: #f5f6fb;
    overflow: hidden;
}

.department-top-bar {
    height: 34px;
    border-bottom: 1px solid #d7dce8;
    background: #f9faff;
    position: relative;
}

.department-title-chip {
    position: absolute;
    right: 10px;
    top: 6px;
    background: #ece6ff;
    color: #5b40a8;
    border: 1px solid #d4c7ff;
    border-radius: 0 16px 16px 0;
    padding: 3px 16px;
    font-size: 12px;
    font-weight: 600;
}

.department-form-wrap {
    padding: 10px 10px 0;
    border-bottom: 1px solid #d7dce8;
}

.alert-inline {
    margin-bottom: 10px;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
}

.alert-success {
    background: #e6f7ec;
    color: #1c7a46;
    border: 1px solid #b9e7c9;
}

.alert-error {
    background: #fff0f0;
    color: #b42318;
    border: 1px solid #ffc7c7;
}

.department-form-grid {
    display: grid;
    grid-template-columns: 1.15fr 1fr;
    gap: 14px;
}

.form-label {
    min-width: 105px;
    margin: 0;
    font-size: 12px;
    font-weight: 600;
    color: #4a5675;
}

.required {
    color: #e5484d;
}

.field-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
}

.field-row input[type="text"],
.field-row input[type="number"] {
    width: 100%;
    height: 32px;
    border: 1px solid #cfd6e6;
    border-radius: 9px;
    padding: 0 10px;
    font-size: 12px;
    background: #fff;
    color: #394766;
}

.field-row input:focus {
    border-color: #9f8ae9;
    outline: none;
    box-shadow: 0 0 0 2px rgba(123, 96, 214, 0.12);
}

.short-code-line {
    display: grid;
    grid-template-columns: 1fr 24px 1fr;
    gap: 8px;
    width: 100%;
}

.calc-btn {
    width: 24px;
    height: 24px;
    border: 1px solid #bcaaff;
    background: #f4efff;
    color: #6e53c9;
    border-radius: 6px;
    line-height: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}

.checkbox-line {
    display: flex;
    align-items: center;
    gap: 20px;
    margin: 2px 0 10px 113px;
}

.checkbox-line label {
    margin: 0;
    font-size: 12px;
    color: #5d6784;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.options-grid {
    display: grid;
    grid-template-columns: 110px 1fr;
    row-gap: 10px;
    column-gap: 6px;
}

.options-title {
    margin: 0;
    font-size: 12px;
    font-weight: 600;
    color: #4a5675;
}

.radio-line {
    display: flex;
    align-items: center;
    gap: 18px;
    flex-wrap: wrap;
}

.radio-line label {
    margin: 0;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #4f5a78;
}

.department-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 0 0 10px;
}

.btn-department {
    border-radius: 6px;
    height: 32px;
    padding: 0 14px;
    font-size: 12px;
    font-weight: 600;
}

.btn-outline-pink {
    border: 1px solid #ff7ccf;
    background: #ffeefe;
    color: #db2ca3;
}

.btn-outline-pink:hover {
    background: #ffe4fb;
}

.btn-outline-purple {
    border: 1px solid #8f7bda;
    background: #f4f0ff;
    color: #553eaf;
}

.btn-outline-purple:hover {
    background: #eee8ff;
}

.department-panels {
    display: grid;
    grid-template-columns: 3fr 1fr;
    min-height: 520px;
}

.panel-box {
    border-right: 1px solid #d7dce8;
    display: flex;
    flex-direction: column;
    background: #f7f8fc;
}

.panel-box:last-child {
    border-right: 0;
}

.panel-title {
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 10px;
    font-size: 16px;
    font-weight: 700;
    color: #5a40aa;
    border-bottom: 1px solid #d7dce8;
    background: #f5f2ff;
}

.selected-department-note {
    font-size: 11px;
    color: #4f5a78;
    margin-left: 8px;
    font-weight: 600;
}

.table-wrap {
    overflow: auto;
}

.table {
    width: 100%;
    margin: 0;
    border-collapse: collapse;
    font-size: 13px;
    color: #3e4c6c;
}

.table thead th {
    background: #eef1f8;
    border-bottom: 1px solid #d7dce8;
    border-right: 1px solid #d7dce8;
    padding: 6px 8px;
    font-weight: 700;
    white-space: nowrap;
}

.table thead th:last-child {
    border-right: 0;
}

.table tbody td {
    border-bottom: 1px solid #dfe3ef;
    border-right: 1px solid #dfe3ef;
    padding: 5px 8px;
    vertical-align: middle;
}

.table tbody td:last-child {
    border-right: 0;
}

.table tbody tr:nth-child(odd) {
    background: #e8edf6;
}

.table tbody tr:nth-child(even) {
    background: #f6f8fd;
}

.name-link {
    color: #4f52a8;
    font-weight: 700;
    text-decoration: none;
    font-size: 12px;
}

.name-link:hover {
    text-decoration: underline;
}

.icon-stack {
    display: inline-flex;
    gap: 8px;
    color: #7a88a8;
    font-size: 13px;
}

.icon-stack .delete {
    color: #ef6a6a;
}

.icon-stack a.department-edit-link {
    color: #11294b;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
}

.icon-stack a.department-edit-link:hover {
    color: #4f52a8;
}

.drag-handle {
    color: #9ba6bf;
    letter-spacing: 1px;
    font-size: 12px;
}

.department-empty {
    flex: 1;
    background: #f7f8fc;
}

.empty-row {
    text-align: center;
    color: #6d7896;
    font-weight: 600;
    padding: 12px;
}

.user-panel-actions {
    display: inline-flex;
    gap: 8px;
    align-items: center;
}

.btn-small-save {
    height: 28px;
    min-width: 68px;
    padding: 0 12px;
    border: 1px solid #8f7bda;
    background: #f4f0ff;
    color: #553eaf;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 700;
}

.btn-plus {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: 1px solid #bfc8dc;
    background: #e5eaf4;
    color: #8a95b2;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    font-weight: 700;
    line-height: 1;
}

.user-list-form {
    display: block;
}

.modal-header.user-modal-header {
    background: linear-gradient(90deg, #5f82f4 0%, #7647aa 100%);
    color: #fff;
}

.modal-header.user-modal-header .close {
    color: #fff;
    opacity: 1;
}

@media (max-width: 1100px) {
    .department-form-grid {
        grid-template-columns: 1fr;
    }
    .checkbox-line {
        margin-left: 0;
        flex-wrap: wrap;
    }
    .department-panels {
        grid-template-columns: 1fr;
    }
    .panel-box {
        border-right: 0;
        border-bottom: 1px solid #d7dce8;
    }
}
</style>

<body class="mfg-page department-page">
<?php include 'sidebar.php'; ?>

<div class="layout-content">
    <div class="container-fluid flex-grow-1" style="padding-top:0;padding-bottom:0;">
        <div class="department-page">
           

            <div class="department-form-wrap">
                <?php if ($success_message !== ''): ?>
                    <div class="alert-inline alert-success"><?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>
                <?php if ($error_message !== ''): ?>
                    <div class="alert-inline alert-error"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>

                <form id="departmentForm" method="POST">
                    <input type="hidden" name="edit_department_id" value="<?php echo (int)$form_edit_department_id; ?>">
                    <div class="department-form-grid">
                        <div>
                            <div class="field-row">
                                <label class="form-label">Name<span class="required">*</span></label>
                                <input type="text" name="dept_name" id="dept_name" value="<?php echo htmlspecialchars($form['dept_name']); ?>">
                            </div>

                            <div class="field-row">
                                <label class="form-label">Short Code<span class="required">*</span></label>
                                <div class="short-code-line">
                                    <input type="text" name="short_code" id="short_code" value="<?php echo htmlspecialchars($form['short_code']); ?>">
                                    <button class="calc-btn" type="button"><i class="feather icon-calculator"></i></button>
                                    <input type="number" step="0.01" name="progress_percent" id="progress_percent" placeholder="Progress In %" value="<?php echo htmlspecialchars($form['progress_percent']); ?>">
                                </div>
                            </div>

                            <div class="checkbox-line">
                                <label><input type="checkbox" id="calculate_stock" name="calculate_stock" <?php echo ((int)$form['calculate_stock'] === 1) ? 'checked' : ''; ?>> Calculate Stock</label>
                                <label><input type="checkbox" id="exclude_jobcard" name="exclude_jobcard" <?php echo ((int)$form['exclude_jobcard'] === 1) ? 'checked' : ''; ?>> Exclude in Jobcard Summary</label>
                            </div>
                        </div>

                        <div class="options-grid">
                            <p class="options-title">Type</p>
                            <div class="radio-line">
                                <label><input type="radio" id="type_wt_wise" name="type" value="Wt. Wise" <?php echo ($form['type'] === 'Wt. Wise') ? 'checked' : ''; ?>> Wt. Wise</label>
                                <label><input type="radio" id="type_against" name="type" value="Against of Weight" <?php echo ($form['type'] === 'Against of Weight') ? 'checked' : ''; ?>> Against of Weight</label>
                            </div>

                            <p class="options-title">Process</p>
                            <div class="radio-line">
                                <label><input type="radio" id="process_manufacturing_inhouse" name="process" value="Manufacturing Inhouse" <?php echo ($form['process'] === 'Manufacturing Inhouse') ? 'checked' : ''; ?>> Manufacturing Inhouse</label>
                                <label><input type="radio" id="process_manufacturing_outsource" name="process" value="Manufacturing Outsource" <?php echo ($form['process'] === 'Manufacturing Outsource') ? 'checked' : ''; ?>> Manufacturing Outsource</label>
                            </div>

                            <p class="options-title">Auto Loss</p>
                            <div class="radio-line">
                                <label><input type="radio" id="auto_loss_on" name="auto_loss" value="On" <?php echo ($form['auto_loss'] === 'On') ? 'checked' : ''; ?>> On</label>
                                <label><input type="radio" id="auto_loss_off" name="auto_loss" value="Off" <?php echo ($form['auto_loss'] === 'Off') ? 'checked' : ''; ?>> Off</label>
                            </div>

                            <p class="options-title">Auto Profit</p>
                            <div class="radio-line">
                                <label><input type="radio" id="auto_profit_on" name="auto_profit" value="On" <?php echo ($form['auto_profit'] === 'On') ? 'checked' : ''; ?>> On</label>
                                <label><input type="radio" id="auto_profit_off" name="auto_profit" value="Off" <?php echo ($form['auto_profit'] === 'Off') ? 'checked' : ''; ?>> Off</label>
                            </div>
                        </div>
                    </div>

                    <div class="department-actions">
                        <button type="button" class="btn-department btn-outline-pink" onclick="window.location.href='department.php'">New Department</button>
                        <button type="submit" name="save_department" value="1" class="btn-department btn-outline-purple">Save</button>
                    </div>
                </form>
            </div>

            <div class="department-panels">
                <div class="panel-box">
                    <div class="panel-title">Department List</div>
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width:28px;"></th>
                                    <th>Name</th>
                                    <th>Short Code</th>
                                    <th>Type</th>
                                    <th>Process</th>
                                    <th>Auto Loss</th>
                                    <th>Calculate Stock</th>
                                    <th>Progress In %</th>
                                    <th>Auto Profit</th>
                                    <th>Exclude In Jo...</th>
                                    <th style="width:72px;"></th>
                                </tr>
                            </thead>
                            <tbody id="departmentList">
                                <?php if (empty($departments)): ?>
                                    <tr><td colspan="11" class="empty-row">No departments found</td></tr>
                                <?php else: ?>
                                    <?php foreach ($departments as $row): ?>
                                        <tr class="department-row">
                                            <td><span class="drag-handle">::: </span></td>
                                            <td><a class="name-link" href="department.php?dept_id=<?php echo (int)$row['id']; ?>"><?php echo htmlspecialchars($row['dept_name']); ?></a></td>
                                            <td><?php echo htmlspecialchars($row['short_code']); ?></td>
                                            <td><?php echo htmlspecialchars($row['department_type']); ?></td>
                                            <td><?php echo htmlspecialchars(department_process_type_label($row['process_type'])); ?></td>
                                            <td><?php echo ((int)$row['auto_loss'] === 1) ? 'On' : 'Off'; ?></td>
                                            <td><input type="checkbox" <?php echo ((int)$row['calculate_stock'] === 1) ? 'checked' : ''; ?> disabled></td>
                                            <td><?php echo ($row['progress_percent'] !== null) ? htmlspecialchars($row['progress_percent']) : ''; ?></td>
                                            <td><?php echo ((int)$row['auto_profit'] === 1) ? 'On' : 'Off'; ?></td>
                                            <td><input type="checkbox" <?php echo ((int)$row['exclude_jobcard_summary'] === 1) ? 'checked' : ''; ?> disabled></td>
                                            <td>
                                                <span class="icon-stack">
                                                    <a class="department-edit-link" href="department.php?dept_id=<?php echo (int)$row['id']; ?>" title="Edit department"><i class="feather icon-edit-2"></i></a>
                                                    <i class="feather icon-trash-2 delete" title="Delete"></i>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="department-empty"></div>
                </div>

                <div class="panel-box">
                    <div class="panel-title">
                        <div>
                            User List
                            <?php if ($selected_department): ?>
                                <span class="selected-department-note">(<?php echo htmlspecialchars($selected_department['dept_name']); ?>)</span>
                            <?php endif; ?>
                        </div>
                        <div class="user-panel-actions">
                            <button type="submit" form="userAssignForm" class="btn-small-save" name="save_department_users" value="1">Save</button>
                        </div>
                    </div>

                    <form id="userAssignForm" method="POST" class="user-list-form">
                        <input type="hidden" name="selected_department_id" value="<?php echo (int)$selected_department_id; ?>">
                        <div class="table-wrap">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th style="width:30px;"></th>
                                        <th>User Name</th>
                                        <th style="width:58px; text-align:center;">
                                            <button type="button" class="btn-plus" data-toggle="modal" data-target="#customerCreationModal" onclick="openJobWorkerModal()">+</button>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody id="userList">
                                    <?php if (empty($job_worker_users)): ?>
                                        <tr><td colspan="3" class="empty-row">No users found</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($job_worker_users as $urow): ?>
                                            <?php $checked = in_array((int)$urow['id'], $assigned_user_ids, true); ?>
                                            <tr>
                                                <td><input type="checkbox" name="department_user_ids[]" value="<?php echo (int)$urow['id']; ?>" <?php echo $checked ? 'checked' : ''; ?>></td>
                                                <td><?php echo htmlspecialchars($urow['name']); ?></td>
                                                <td><span class="icon-stack"><i class="feather icon-settings"></i><i class="feather icon-printer"></i></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>

                    <div class="department-empty"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/customer-creation-modal-only.php'; ?>
<?php include 'footer-script.php'; ?>
<script>var nationalities = <?php echo json_encode($nationalities); ?>;</script>
<script src="assets/js/customer-creation-modal-common.js"></script>
<script>
window.AURAGOLD_CUSTOMER_SAVE_ON_SUCCESS = function (data) {
    alert(data.message || "Customer saved successfully!");
    if (window.jQuery) window.jQuery("#customerCreationModal").modal("hide");
    if (typeof clearCustomerForm === "function") clearCustomerForm();
    var q = <?php echo json_encode(($selected_department_id > 0) ? ("?dept_id=" . (int)$selected_department_id) : ""); ?>;
    window.location.href = "department.php" + q;
    return false;
};
function applyDefaultLedgerCustomerType(typeName) {
    var sel = document.getElementById("customerType");
    if (!sel || !typeName) return;
    var want = String(typeName).trim().toLowerCase();
    for (var i = 0; i < sel.options.length; i++) {
        var opt = sel.options[i];
        if (!opt.value) continue;
        if (opt.text.trim().toLowerCase() === want) {
            sel.selectedIndex = i;
            return;
        }
    }
}
function openJobWorkerModal() {
    clearCustomerForm();
    setCustomerModalMode("add");
    applyDefaultLedgerCustomerType("Job Worker");
}
function clearForm() {
    document.getElementById("departmentForm").reset();
    document.getElementById("type_wt_wise").checked = true;
    document.getElementById("process_manufacturing_inhouse").checked = true;
    document.getElementById("auto_loss_on").checked = true;
    document.getElementById("auto_profit_on").checked = true;
}
</script>

<style>
.modal.right .modal-dialog {
    position: fixed;
    margin: auto;
    right: 0;
    top: 0;
    height: 100%;
    transform: translate3d(0%, 0, 0);
}
.modal.right .modal-content {
    height: 100%;
    overflow-y: auto;
}
.modal.right.fade .modal-dialog {
    right: -100%;
    transition: opacity 0.3s linear, right 0.3s ease-out;
}
.modal.right.fade.show .modal-dialog {
    right: 0;
}
</style>

</body>
</html>
