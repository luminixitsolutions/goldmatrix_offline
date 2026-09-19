<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['Admin']['id'])) {
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

$tableCheck = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_bill_series'");
if (!$tableCheck || mysqli_num_rows($tableCheck) === 0) {
    echo json_encode(['status' => false, 'message' => 'Bill series table not found. Please run admin/sql/create_tbl_bill_series.sql first.']);
    exit;
}

auragold_ensure_branch_id_on_settings_tables($conn);
$settings_bid = auragold_settings_branch_id();
$has_bs_branch = auragold_tbl_has_column($conn, 'tbl_bill_series', 'branch_id');
$scope_sql = ($has_bs_branch && $settings_bid > 0) ? (' AND branch_id = ' . (int) $settings_bid) : '';

// Get voucher_type_id for the record we're updating (to check lock)
if ($action === 'update' && $id > 0) {
    $existing = getRecord("SELECT voucher_type_id FROM tbl_bill_series WHERE id = $id AND status = 1$scope_sql LIMIT 1");
    if (!$existing) {
        echo json_encode(['status' => false, 'message' => 'Bill series not found.']);
        exit;
    }
    $voucher_type_id = (int)$existing['voucher_type_id'];
    $count = countBillsForVoucherType($conn, $voucher_type_id);
    if ($count > 0) {
        echo json_encode([
            'status' => false,
            'message' => 'Cannot update. Bills already generated for this voucher type.'
        ]);
        exit;
    }
}

if ($action === 'delete' && $id > 0) {
    $existing = getRecord("SELECT voucher_type_id FROM tbl_bill_series WHERE id = $id AND status = 1$scope_sql LIMIT 1");
    if (!$existing) {
        echo json_encode(['status' => false, 'message' => 'Bill series not found.']);
        exit;
    }
    $voucher_type_id = (int)$existing['voucher_type_id'];
    $count = countBillsForVoucherType($conn, $voucher_type_id);
    if ($count > 0) {
        echo json_encode([
            'status' => false,
            'message' => 'Cannot delete. Bills already generated for this voucher type.'
        ]);
        exit;
    }
    mysqli_query($conn, "UPDATE tbl_bill_series SET status = 0, updated_at = NOW() WHERE id = $id$scope_sql");
    echo json_encode(['status' => true, 'message' => 'Deleted.']);
    exit;
}

if ($action === 'update' && $id > 0) {
    $voucher_type_id = isset($_POST['voucher_type_id']) ? (int)$_POST['voucher_type_id'] : 0;
    $prefix = esc($_POST['prefix'] ?? '');
    $suffix = esc($_POST['suffix'] ?? '');
    $start_count = isset($_POST['start_count']) ? (int)$_POST['start_count'] : 0;
    $branch_id = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : 0;
    if ($branch_id <= 0) {
        $branch_id = $settings_bid;
    }

    if ($voucher_type_id <= 0) {
        echo json_encode(['status' => false, 'message' => 'Voucher type is required.']);
        exit;
    }

    // Prevent duplicate voucher type: another series (different id) already uses this voucher type (same branch)
    $dup_scope = ($has_bs_branch && $settings_bid > 0) ? (' AND branch_id = ' . (int) $settings_bid) : '';
    $existingOther = getRecord("SELECT id FROM tbl_bill_series WHERE status = 1 AND voucher_type_id = $voucher_type_id AND id != $id$dup_scope LIMIT 1");
    if ($existingOther) {
        echo json_encode(['status' => false, 'message' => 'This voucher type already has a bill series. Please edit the existing one.']);
        exit;
    }

    $branch_sql = ($branch_id > 0 && $has_bs_branch) ? (int) $branch_id : 'NULL';

    mysqli_query($conn, "
        UPDATE tbl_bill_series SET
            voucher_type_id = $voucher_type_id,
            branch_id = $branch_sql,
            prefix = '$prefix',
            suffix = '$suffix',
            start_count = $start_count,
            updated_at = NOW()
        WHERE id = $id AND status = 1$scope_sql
    ");
    if (mysqli_affected_rows($conn) > 0) {
        echo json_encode(['status' => true, 'message' => 'Bill series updated.']);
    } else {
        echo json_encode(['status' => false, 'message' => 'Update failed or no change.']);
    }
    exit;
}

if ($action === 'add') {
    $voucher_type_id = isset($_POST['voucher_type_id']) ? (int)$_POST['voucher_type_id'] : 0;
    $prefix = esc($_POST['prefix'] ?? '');
    $suffix = esc($_POST['suffix'] ?? '');
    $start_count = isset($_POST['start_count']) ? (int)$_POST['start_count'] : 0;
    $branch_id = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : 0;
    if ($branch_id <= 0) {
        $branch_id = $settings_bid;
    }
    $user = (int)$_SESSION['Admin']['id'];

    if ($voucher_type_id <= 0) {
        echo json_encode(['status' => false, 'message' => 'Voucher type is required.']);
        exit;
    }

    $dup_scope = ($has_bs_branch && $settings_bid > 0) ? (' AND branch_id = ' . (int) $settings_bid) : '';
    // Prevent duplicate: do not create another bill series for the same voucher type (same branch)
    $existing = getRecord("SELECT id FROM tbl_bill_series WHERE status = 1 AND voucher_type_id = $voucher_type_id$dup_scope LIMIT 1");
    if ($existing) {
        echo json_encode(['status' => false, 'message' => 'This voucher type already has a bill series. Please edit the existing one.']);
        exit;
    }

    $branch_sql = ($branch_id > 0 && $has_bs_branch) ? (int) $branch_id : 'NULL';

    mysqli_query($conn, "
        INSERT INTO tbl_bill_series
        (voucher_type_id, branch_id, prefix, suffix, start_count, status, created_by, created_at)
        VALUES
        ($voucher_type_id, $branch_sql, '$prefix', '$suffix', $start_count, 1, $user, NOW())
    ");
    $new_id = (int) mysqli_insert_id($conn);
    if ($new_id > 0) {
        echo json_encode(['status' => true, 'message' => 'Bill series created.', 'id' => $new_id]);
    } else {
        echo json_encode(['status' => false, 'message' => 'Create failed.']);
    }
    exit;
}

echo json_encode(['status' => false, 'message' => 'Invalid action.']);
