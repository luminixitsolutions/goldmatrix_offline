<?php
/**
 * Prefill Material Issue / Receive Department + Name from linked Job Work Order (Assign To).
 */
if (!function_exists('auragold_material_apply_jobwork_assignee')) {
    /**
     * @param array<string,mixed> $edit_order
     */
    function auragold_material_apply_jobwork_assignee(
        $conn,
        array &$edit_order,
        bool $from_repair,
        int $source_jobwork_order_id,
        int $source_repair_jwo_id,
        int $order_id_param
    ): void {
        if (!$conn || empty($edit_order) || !is_array($edit_order)) {
            return;
        }

        $linked = null;
        $force = ($source_jobwork_order_id > 0 || $source_repair_jwo_id > 0);

        if ($from_repair) {
            $tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_repair_jobwork_orders'");
            $ok = ($tbl && mysqli_num_rows($tbl) > 0);
            if ($tbl) {
                mysqli_free_result($tbl);
            }
            if (!$ok) {
                return;
            }
            if ($source_repair_jwo_id > 0) {
                $linked = getRecord(
                    'SELECT department_id, department_user_id, priority, status FROM tbl_repair_jobwork_orders WHERE id = '
                    . (int) $source_repair_jwo_id . ' LIMIT 1'
                );
            } elseif ($order_id_param > 0) {
                $linked = getRecord(
                    'SELECT department_id, department_user_id, priority, status FROM tbl_repair_jobwork_orders WHERE repair_order_id = '
                    . (int) $order_id_param . ' ORDER BY id DESC LIMIT 1'
                );
            }
        } else {
            $tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_orders'");
            $ok = ($tbl && mysqli_num_rows($tbl) > 0);
            if ($tbl) {
                mysqli_free_result($tbl);
            }
            if (!$ok) {
                return;
            }
            if ($source_jobwork_order_id > 0) {
                $linked = getRecord(
                    'SELECT department_id, department_user_id, priority, status FROM tbl_jobwork_orders WHERE id = '
                    . (int) $source_jobwork_order_id . ' LIMIT 1'
                );
            } elseif ($order_id_param > 0) {
                $linked = getRecord(
                    'SELECT department_id, department_user_id, priority, status FROM tbl_jobwork_orders WHERE sale_order_id = '
                    . (int) $order_id_param . ' ORDER BY id DESC LIMIT 1'
                );
            }
        }

        if (!$linked || !is_array($linked)) {
            return;
        }

        $has_user = isset($edit_order['department_user_id'])
            && $edit_order['department_user_id'] !== ''
            && $edit_order['department_user_id'] !== null
            && (int) $edit_order['department_user_id'] > 0;
        $has_dept = isset($edit_order['department_id'])
            && $edit_order['department_id'] !== ''
            && $edit_order['department_id'] !== null
            && (int) $edit_order['department_id'] > 0;

        if ($force || !$has_user) {
            if (isset($linked['department_user_id']) && $linked['department_user_id'] !== '' && $linked['department_user_id'] !== null) {
                $edit_order['department_user_id'] = (int) $linked['department_user_id'];
            }
        }
        if ($force || !$has_dept) {
            if (isset($linked['department_id']) && $linked['department_id'] !== '' && $linked['department_id'] !== null) {
                $edit_order['department_id'] = (int) $linked['department_id'];
            }
        }
    }
}
