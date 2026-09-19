<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_product_catalog_scope.php';
require_once __DIR__ . '/../includes/auragold_product_branch_local_schema.php';
require_once __DIR__ . '/../includes/auragold_subbranch_product_local_sync.php';

header('Content-Type: application/json; charset=utf-8');

if (!($conn instanceof mysqli)) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection not available.']);
    exit;
}

try {
    $branchConn = $conn;
    $payload = auragold_with_product_catalog_conn($branchConn, static function (array $ctx) use ($branchConn) {
        if (empty($ctx['is_sub']) || (int) ($ctx['sub_branch_id'] ?? 0) <= 0) {
            return ['status' => 'error', 'message' => 'This action is only available for a sub-branch.'];
        }

        $main_id = (int) ($ctx['main_branch_id'] ?? 0);
        $sub_id  = (int) ($ctx['sub_branch_id'] ?? 0);
        if ($main_id <= 0) {
            return ['status' => 'error', 'message' => 'Parent main branch not found.'];
        }

        global $conn;
        $tb = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_product_branches'");
        if (!$tb || mysqli_num_rows($tb) === 0) {
            if ($tb) {
                mysqli_free_result($tb);
            }
            return ['status' => 'error', 'message' => 'tbl_product_branches is missing in the product catalog database.'];
        }
        mysqli_free_result($tb);
        auragold_ensure_tbl_product_branches_is_active($conn);
        $pb_soft = auragold_tbl_product_branches_has_is_active($conn);

        $active_ids = [];
        $payload = null;
        $raw = file_get_contents('php://input');
        if ($raw !== '' && $raw !== false) {
            $payload = json_decode($raw, true);
        }
        if (is_array($payload) && isset($payload['active_product_ids']) && is_array($payload['active_product_ids'])) {
            foreach ($payload['active_product_ids'] as $pid) {
                $pid = (int) $pid;
                if ($pid > 0) {
                    $active_ids[] = $pid;
                }
            }
        } elseif (isset($_POST['active_product_ids']) && is_array($_POST['active_product_ids'])) {
            foreach ($_POST['active_product_ids'] as $pid) {
                $pid = (int) $pid;
                if ($pid > 0) {
                    $active_ids[] = $pid;
                }
            }
        }
        $active_ids = array_values(array_unique($active_ids));

        $scope = auragold_sql_products_scope_for_branch($main_id);
        $main_rows = getList("SELECT id FROM tbl_products WHERE ($scope) AND status IN (0, 1)");
        $main_ids = [];
        foreach ($main_rows as $mr) {
            $main_ids[] = (int) $mr['id'];
        }
        $main_ids = array_values(array_unique($main_ids));

        $active_allowed = array_values(array_intersect($active_ids, $main_ids));
        $to_remove = array_values(array_diff($main_ids, $active_allowed));

        mysqli_begin_transaction($conn);

        foreach ($to_remove as $pid) {
            $pid = (int) $pid;
            if ($pb_soft) {
                if (!mysqli_query($conn, "UPDATE tbl_product_branches SET is_active = 0 WHERE branch_id = $sub_id AND product_id = $pid")) {
                    mysqli_rollback($conn);
                    return ['status' => 'error', 'message' => mysqli_error($conn)];
                }
            } else {
                if (!mysqli_query($conn, "DELETE FROM tbl_product_branches WHERE branch_id = $sub_id AND product_id = $pid")) {
                    mysqli_rollback($conn);
                    return ['status' => 'error', 'message' => mysqli_error($conn)];
                }
            }
        }
        foreach ($active_allowed as $pid) {
            $pid = (int) $pid;
            if ($pb_soft) {
                if (!mysqli_query(
                    $conn,
                    "INSERT INTO tbl_product_branches (product_id, branch_id, is_active) VALUES ($pid, $sub_id, 1)
                        ON DUPLICATE KEY UPDATE is_active = 1"
                )) {
                    mysqli_rollback($conn);
                    return ['status' => 'error', 'message' => mysqli_error($conn)];
                }
            } else {
                if (!mysqli_query(
                    $conn,
                    "INSERT IGNORE INTO tbl_product_branches (product_id, branch_id) VALUES ($pid, $sub_id)"
                )) {
                    mysqli_rollback($conn);
                    return ['status' => 'error', 'message' => mysqli_error($conn)];
                }
            }
        }
        mysqli_commit($conn);

        $local_synced = 0;
        $local_errors = [];
        if (auragold_subbranch_catalog_and_local_differ($conn, $branchConn)) {
            foreach ($active_allowed as $pid) {
                try {
                    auragold_sync_subbranch_product_local_from_catalog($conn, $branchConn, (int) $pid, $sub_id, true);
                    $local_synced++;
                } catch (Throwable $syncEx) {
                    $local_errors[] = (int) $pid . ': ' . $syncEx->getMessage();
                }
            }
            foreach ($to_remove as $pid) {
                try {
                    auragold_sync_subbranch_product_local_from_catalog($conn, $branchConn, (int) $pid, $sub_id, false);
                } catch (Throwable $syncEx) {
                    $local_errors[] = (int) $pid . ': ' . $syncEx->getMessage();
                }
            }
        }

        $message = 'Active products for this branch have been updated.';
        if ($local_synced > 0) {
            $message .= ' Local branch database records were created/updated for ' . $local_synced . ' product(s).';
        }
        if ($local_errors !== []) {
            $message .= ' Some local copies failed: ' . implode('; ', array_slice($local_errors, 0, 3));
        }

        return [
            'status' => 'success',
            'message' => $message,
            'activated' => count($active_allowed),
            'local_synced' => $local_synced,
        ];
    });
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

echo json_encode($payload);
