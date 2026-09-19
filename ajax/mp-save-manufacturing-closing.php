<?php
/**
 * Save manufacturing closing: persist snapshot, clear queue rows for department + worker.
 */
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'message' => 'Invalid request']);
    exit;
}

$adminId = isset($_SESSION['Admin']['id']) ? (int) $_SESSION['Admin']['id'] : (isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0);
if ($adminId < 1) {
    echo json_encode(['ok' => false, 'message' => 'Please log in.']);
    exit;
}

function mp_closing_float($v) {
    if ($v === null || $v === '') {
        return null;
    }
    $n = (float) str_replace(',', '', (string) $v);
    return is_finite($n) ? $n : null;
}

function mp_closing_int($v) {
    $n = (int) $v;
    return $n >= 0 ? $n : 0;
}

$department_id = isset($_POST['department_id']) ? (int) $_POST['department_id'] : 0;
$department_user_id = isset($_POST['department_user_id']) ? (int) $_POST['department_user_id'] : 0;
if ($department_id < 1 || $department_user_id < 1) {
    echo json_encode(['ok' => false, 'message' => 'Department and user are required.']);
    exit;
}

$closing_date = isset($_POST['closing_date']) ? trim((string) $_POST['closing_date']) : '';
if ($closing_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $closing_date)) {
    echo json_encode(['ok' => false, 'message' => 'Valid closing date is required.']);
    exit;
}

$branch_id = isset($_POST['branch_id']) ? (int) $_POST['branch_id'] : 0;
$branch_id = $branch_id > 0 ? $branch_id : null;

$loss_wt = mp_closing_float($_POST['loss_wt'] ?? null);
$gold_rate = mp_closing_float($_POST['gold_rate'] ?? null);
$gold_loss_value = mp_closing_float($_POST['gold_loss_value'] ?? null);
$purity_per = mp_closing_float($_POST['purity_per'] ?? null);
$purity_wt = mp_closing_float($_POST['purity_wt'] ?? null);
$work_done_kg = mp_closing_float($_POST['work_done_kg'] ?? null);
$avg_loss_per_kg = mp_closing_float($_POST['avg_loss_per_kg'] ?? null);
$inward_wt = mp_closing_float($_POST['inward_wt'] ?? null);
$outward_wt = mp_closing_float($_POST['outward_wt'] ?? null);
$recovery_wt = mp_closing_float($_POST['recovery_wt'] ?? null);
if ($recovery_wt === null) {
    $recovery_wt = 0.0;
}
$closing_wt = mp_closing_float($_POST['closing_wt'] ?? null);
$production_wt = mp_closing_float($_POST['production_wt'] ?? null);
$metal_weight = mp_closing_float($_POST['metal_weight'] ?? null);
$difference_loss = mp_closing_float($_POST['difference_loss'] ?? null);
$final_loss = mp_closing_float($_POST['final_loss'] ?? null);
$loss_percent = mp_closing_float($_POST['loss_percent'] ?? null);

$closed_jobs = mp_closing_int($_POST['closed_jobs'] ?? 0);
$processed_jobs = mp_closing_int($_POST['processed_jobs'] ?? 0);
$total_jobs = mp_closing_int($_POST['total_jobs'] ?? 0);

$deptEsc = (int) $department_id;
$userEsc = (int) $department_user_id;
$dateEsc = mysqli_real_escape_string($conn, $closing_date);

$createSql = "CREATE TABLE IF NOT EXISTS `tbl_manufacturing_closing` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `department_id` int(11) unsigned NOT NULL,
  `department_user_id` int(11) unsigned NOT NULL,
  `branch_id` int(11) unsigned DEFAULT NULL,
  `closing_date` date NOT NULL,
  `loss_wt` decimal(18,6) DEFAULT NULL,
  `gold_rate` decimal(18,6) DEFAULT NULL,
  `gold_loss_value` decimal(18,6) DEFAULT NULL,
  `purity_per` decimal(18,6) DEFAULT NULL,
  `purity_wt` decimal(18,6) DEFAULT NULL,
  `work_done_kg` decimal(18,6) DEFAULT NULL,
  `avg_loss_per_kg` decimal(18,6) DEFAULT NULL,
  `inward_wt` decimal(18,6) DEFAULT NULL,
  `outward_wt` decimal(18,6) DEFAULT NULL,
  `recovery_wt` decimal(18,6) DEFAULT NULL,
  `closing_wt` decimal(18,6) DEFAULT NULL,
  `production_wt` decimal(18,6) DEFAULT NULL,
  `difference_loss` decimal(18,6) DEFAULT NULL,
  `final_loss` decimal(18,6) DEFAULT NULL,
  `loss_percent` decimal(18,6) DEFAULT NULL,
  `closed_jobs` int(11) unsigned NOT NULL DEFAULT 0,
  `processed_jobs` int(11) unsigned NOT NULL DEFAULT 0,
  `total_jobs` int(11) unsigned NOT NULL DEFAULT 0,
  `metal_weight` decimal(18,6) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `created_by` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_dept_user` (`department_id`,`department_user_id`),
  KEY `idx_closing_date` (`closing_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
@mysqli_query($conn, $createSql);

function mp_closing_sql_num($v) {
    global $conn;
    if ($v === null) {
        return 'NULL';
    }
    return "'" . mysqli_real_escape_string($conn, (string) $v) . "'";
}

@mysqli_query($conn, 'START TRANSACTION');

try {
    $chkW = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_weight_adjustments'");
    $hasW = ($chkW && mysqli_num_rows($chkW) > 0);
    if ($chkW) {
        mysqli_free_result($chkW);
    }
    if ($hasW) {
        $delW = "
            DELETE w FROM tbl_jobwork_weight_adjustments w
            INNER JOIN tbl_jobwork_orders j ON j.id = w.jobwork_order_id
            WHERE COALESCE(w.source_department_id, j.department_id) = {$deptEsc}
            AND (
                CASE
                    WHEN w.source_user_id IS NOT NULL AND w.source_user_id > 0 THEN w.source_user_id
                    ELSE COALESCE(j.department_user_id, 0)
                END
            ) = {$userEsc}
        ";
        if (!@mysqli_query($conn, $delW)) {
            throw new Exception('Could not clear weight adjustments.');
        }
    }

    $chkA = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_queue_activity'");
    $hasA = ($chkA && mysqli_num_rows($chkA) > 0);
    if ($chkA) {
        mysqli_free_result($chkA);
    }
    if ($hasA) {
        $delA = "
            DELETE FROM tbl_jobwork_queue_activity
            WHERE (IFNULL(to_dept_id,0) = {$deptEsc} AND IFNULL(to_user_id,0) = {$userEsc})
               OR (IFNULL(from_dept_id,0) = {$deptEsc} AND IFNULL(from_user_id,0) = {$userEsc})
        ";
        if (!@mysqli_query($conn, $delA)) {
            throw new Exception('Could not clear queue activity.');
        }
    }

    $chkMi = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_material_issues'");
    $chkMii = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_material_issue_items'");
    $hasMi = ($chkMi && mysqli_num_rows($chkMi) > 0 && $chkMii && mysqli_num_rows($chkMii) > 0);
    if ($chkMi) {
        mysqli_free_result($chkMi);
    }
    if ($chkMii) {
        mysqli_free_result($chkMii);
    }
    if ($hasMi) {
        $ids = [];
        $qIds = @mysqli_query($conn, "SELECT id FROM tbl_material_issues WHERE department_id = {$deptEsc} AND department_user_id = {$userEsc}");
        if ($qIds) {
            while ($r = mysqli_fetch_assoc($qIds)) {
                $ids[] = (int) $r['id'];
            }
            mysqli_free_result($qIds);
        }
        if (count($ids) > 0) {
            $in = implode(',', array_map('intval', $ids));
            if (!@mysqli_query($conn, "DELETE FROM tbl_material_issue_items WHERE material_issue_id IN ({$in})")) {
                throw new Exception('Could not clear material issue lines.');
            }
            if (!@mysqli_query($conn, "DELETE FROM tbl_material_issues WHERE id IN ({$in})")) {
                throw new Exception('Could not clear material issues.');
            }
        }
    }

    $brSql = $branch_id !== null ? (int) $branch_id : 'NULL';
    $ins = "INSERT INTO tbl_manufacturing_closing (
        department_id, department_user_id, branch_id, closing_date,
        loss_wt, gold_rate, gold_loss_value, purity_per, purity_wt, work_done_kg, avg_loss_per_kg,
        inward_wt, outward_wt, recovery_wt, closing_wt, production_wt,
        difference_loss, final_loss, loss_percent,
        closed_jobs, processed_jobs, total_jobs, metal_weight,
        created_at, created_by
    ) VALUES (
        {$deptEsc}, {$userEsc}, {$brSql}, '{$dateEsc}',
        " . mp_closing_sql_num($loss_wt) . ", " . mp_closing_sql_num($gold_rate) . ", " . mp_closing_sql_num($gold_loss_value) . ",
        " . mp_closing_sql_num($purity_per) . ", " . mp_closing_sql_num($purity_wt) . ", " . mp_closing_sql_num($work_done_kg) . ", " . mp_closing_sql_num($avg_loss_per_kg) . ",
        " . mp_closing_sql_num($inward_wt) . ", " . mp_closing_sql_num($outward_wt) . ", " . mp_closing_sql_num($recovery_wt) . ", " . mp_closing_sql_num($closing_wt) . ", " . mp_closing_sql_num($production_wt) . ",
        " . mp_closing_sql_num($difference_loss) . ", " . mp_closing_sql_num($final_loss) . ", " . mp_closing_sql_num($loss_percent) . ",
        {$closed_jobs}, {$processed_jobs}, {$total_jobs}, " . mp_closing_sql_num($metal_weight) . ",
        NOW(), {$adminId}
    )";

    if (!@mysqli_query($conn, $ins)) {
        throw new Exception('Could not save closing record.');
    }
    $newId = (int) mysqli_insert_id($conn);

    @mysqli_query($conn, 'COMMIT');
    echo json_encode(['ok' => true, 'id' => $newId, 'message' => 'Closing saved and stock cleared for this department and worker.']);
} catch (Exception $e) {
    @mysqli_query($conn, 'ROLLBACK');
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
