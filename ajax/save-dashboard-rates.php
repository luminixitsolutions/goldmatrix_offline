<?php
require_once dirname(__DIR__) . '/includes/session_init.php';
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/dashboard_metal_rates_branch_schema.php';
require_once dirname(__DIR__) . '/includes/auragold_branch_data_scope.php';
require_once dirname(__DIR__) . '/includes/dashboard_carat_master.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired. Please log in again.']);
    exit;
}

if (!$conn) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON body']);
    exit;
}

$metal = isset($data['metal']) ? strtolower(trim((string) $data['metal'])) : '';
$core_metals = ['gold', 'silver', 'platinum', 'diamond'];
$metal_ok = in_array($metal, $core_metals, true) || preg_match('/^mext_\d+$/', $metal) === 1;
if (!$metal_ok) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid metal']);
    exit;
}

$allowed_carat_labels = auragold_dashboard_carat_labels_for_save_validation($conn, $metal);
if ($allowed_carat_labels === []) {
    echo json_encode(['status' => 'error', 'message' => 'No carat labels configured for this metal. Add rows in Masters → Carat (metal-wise).']);
    exit;
}

$chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_dashboard_metal_rates'");
if (!$chk || mysqli_num_rows($chk) === 0) {
    if ($chk) {
        mysqli_free_result($chk);
    }
    echo json_encode([
        'status' => 'error',
        'message' => 'Rate tables not found. Run admin/sql/create_tbl_dashboard_metal_rates.sql in MySQL first.',
    ]);
    exit;
}
mysqli_free_result($chk);

auragold_ensure_dashboard_metal_rates_branch_columns($conn);
$has_branch_col = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_dashboard_metal_rates', 'branch_id');
$requested_branch = isset($data['branch_id']) ? (int) $data['branch_id'] : 0;
$branch_id = auragold_resolve_branch_id_for_session($requested_branch);

$rows_in = isset($data['rows']) && is_array($data['rows']) ? $data['rows'] : [];
if (count($rows_in) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'No rows to save']);
    exit;
}

$source_url = isset($data['source_url']) ? trim((string) $data['source_url']) : '';
$ounce_raw = isset($data['ounce_rate']) ? $data['ounce_rate'] : '0';
$ounce_val = $metal === 'diamond' ? 0.0 : (float) str_replace([',', ' '], '', (string) $ounce_raw);

mysqli_begin_transaction($conn);

try {
    if ($has_branch_col) {
        $stmtMeta = mysqli_prepare(
            $conn,
            'INSERT INTO tbl_dashboard_metal_meta (metal, branch_id, source_url, ounce_rate, updated_at) VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE source_url = VALUES(source_url), ounce_rate = VALUES(ounce_rate), updated_at = NOW()'
        );
        if (!$stmtMeta) {
            throw new RuntimeException(mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmtMeta, 'sisd', $metal, $branch_id, $source_url, $ounce_val);
        if (!mysqli_stmt_execute($stmtMeta)) {
            throw new RuntimeException(mysqli_stmt_error($stmtMeta));
        }
        mysqli_stmt_close($stmtMeta);
    } else {
        $stmtMeta = mysqli_prepare(
            $conn,
            'INSERT INTO tbl_dashboard_metal_meta (metal, source_url, ounce_rate, updated_at) VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE source_url = VALUES(source_url), ounce_rate = VALUES(ounce_rate), updated_at = NOW()'
        );
        if (!$stmtMeta) {
            throw new RuntimeException(mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmtMeta, 'ssd', $metal, $source_url, $ounce_val);
        if (!mysqli_stmt_execute($stmtMeta)) {
            throw new RuntimeException(mysqli_stmt_error($stmtMeta));
        }
        mysqli_stmt_close($stmtMeta);
    }

    $m_esc = mysqli_real_escape_string($conn, $metal);
    $sort = 0;
    foreach ($rows_in as $row) {
        if (!is_array($row)) {
            continue;
        }
        $carat = isset($row['carat']) ? trim((string) $row['carat']) : '';
        if (!in_array($carat, $allowed_carat_labels, true)) {
            throw new RuntimeException('Invalid carat label: ' . $carat);
        }

        $rate_val = (float) str_replace([',', ' '], '', (string) ($row['rate'] ?? '0'));
        $conv_raw = isset($row['conv']) ? $row['conv'] : '1';
        $conv_val = (float) str_replace([',', ' '], '', (string) $conv_raw);
        if ($conv_val <= 0) {
            $conv_val = 1.0;
        }

        $prem_null = true;
        $prem_val = 0.0;
        if (isset($row['sell_premium']) && $row['sell_premium'] !== '' && $row['sell_premium'] !== null) {
            $prem_val = (float) str_replace([',', ' '], '', (string) $row['sell_premium']);
            $prem_null = false;
        }

        $sort++;
        $c_esc = mysqli_real_escape_string($conn, $carat);
        $bid_sql = $has_branch_col ? (int) $branch_id : 0;

        if ($has_branch_col) {
            if ($prem_null) {
                $sql = "INSERT INTO tbl_dashboard_metal_rates (branch_id, metal, carat_label, rate, sell_premium, conversion_rate, sort_order, updated_at)
                    VALUES ($bid_sql, '$m_esc', '$c_esc', $rate_val, NULL, $conv_val, $sort, NOW())
                    ON DUPLICATE KEY UPDATE rate = VALUES(rate), sell_premium = NULL, conversion_rate = VALUES(conversion_rate), sort_order = VALUES(sort_order), updated_at = NOW()";
            } else {
                $sql = "INSERT INTO tbl_dashboard_metal_rates (branch_id, metal, carat_label, rate, sell_premium, conversion_rate, sort_order, updated_at)
                    VALUES ($bid_sql, '$m_esc', '$c_esc', $rate_val, $prem_val, $conv_val, $sort, NOW())
                    ON DUPLICATE KEY UPDATE rate = VALUES(rate), sell_premium = VALUES(sell_premium), conversion_rate = VALUES(conversion_rate), sort_order = VALUES(sort_order), updated_at = NOW()";
            }
        } elseif ($prem_null) {
            $sql = "INSERT INTO tbl_dashboard_metal_rates (metal, carat_label, rate, sell_premium, conversion_rate, sort_order, updated_at)
                VALUES ('$m_esc', '$c_esc', $rate_val, NULL, $conv_val, $sort, NOW())
                ON DUPLICATE KEY UPDATE rate = VALUES(rate), sell_premium = NULL, conversion_rate = VALUES(conversion_rate), sort_order = VALUES(sort_order), updated_at = NOW()";
        } else {
            $sql = "INSERT INTO tbl_dashboard_metal_rates (metal, carat_label, rate, sell_premium, conversion_rate, sort_order, updated_at)
                VALUES ('$m_esc', '$c_esc', $rate_val, $prem_val, $conv_val, $sort, NOW())
                ON DUPLICATE KEY UPDATE rate = VALUES(rate), sell_premium = VALUES(sell_premium), conversion_rate = VALUES(conversion_rate), sort_order = VALUES(sort_order), updated_at = NOW()";
        }

        if (!mysqli_query($conn, $sql)) {
            throw new RuntimeException(mysqli_error($conn));
        }
    }

    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

require_once dirname(__DIR__) . '/includes/dashboard_rate_history_schema.php';
auragold_dashboard_append_metal_rate_history($conn, $metal, $rows_in, $has_branch_col ? $branch_id : 0);

$now = date('Y-m-d H:i:s');
echo json_encode(['status' => 'ok', 'message' => 'Rates saved', 'saved_at' => $now]);
