<?php
session_start();
require_once __DIR__ . "/../config.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

$user_id = isset($_SESSION['Admin']['id']) ? (int)$_SESSION['Admin']['id'] : (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0);
if ($user_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

$page_name = isset($_POST['page_name']) ? trim($_POST['page_name']) : 'purchase-invoice-product-modal';
$tab_key = isset($_POST['tab_key']) ? trim($_POST['tab_key']) : '';
$page_name_esc = mysqli_real_escape_string($conn, $page_name);
$tab_key_esc = mysqli_real_escape_string($conn, $tab_key);

/**
 * Detect tab_key column reliably (SHOW COLUMNS LIKE can fail on some hosts / collations).
 */
function sj_user_column_prefs_has_tab_key($conn) {
    $dbEsc = '';
    if ($r = mysqli_query($conn, 'SELECT DATABASE() AS d')) {
        $row = mysqli_fetch_assoc($r);
        if (!empty($row['d'])) {
            $dbEsc = mysqli_real_escape_string($conn, $row['d']);
        }
    }
    if ($dbEsc !== '') {
        $q = "SELECT 1 FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = '$dbEsc'
              AND TABLE_NAME = 'tbl_user_column_preferences'
              AND COLUMN_NAME = 'tab_key'
              LIMIT 1";
        $res = @mysqli_query($conn, $q);
        if ($res && mysqli_fetch_row($res)) {
            return true;
        }
    }
    $res2 = @mysqli_query($conn, "SHOW COLUMNS FROM `tbl_user_column_preferences` LIKE 'tab_key'");
    return ($res2 && mysqli_num_rows($res2) > 0);
}

$has_tab_key = sj_user_column_prefs_has_tab_key($conn);

/**
 * Optional per-column width (px); safe ALTER if missing.
 */
function sj_user_column_prefs_ensure_width_column($conn) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $r = @mysqli_query($conn, "SHOW COLUMNS FROM `tbl_user_column_preferences` LIKE 'column_width_px'");
    if ($r && mysqli_num_rows($r) > 0) {
        mysqli_free_result($r);
        return;
    }
    if ($r) {
        mysqli_free_result($r);
    }
    @mysqli_query(
        $conn,
        "ALTER TABLE `tbl_user_column_preferences` ADD COLUMN `column_width_px` SMALLINT UNSIGNED NULL DEFAULT NULL COMMENT 'Optional width in pixels' AFTER `is_visible`"
    );
}

sj_user_column_prefs_ensure_width_column($conn);

function sj_user_column_prefs_has_width_column($conn) {
    $r = @mysqli_query($conn, "SHOW COLUMNS FROM `tbl_user_column_preferences` LIKE 'column_width_px'");
    $ok = $r && mysqli_num_rows($r) > 0;
    if ($r) {
        mysqli_free_result($r);
    }
    return $ok;
}

$has_width_col = sj_user_column_prefs_has_width_column($conn);

/** @var array<string, int|null> */
$widths_post = [];
if (isset($_POST['widths']) && is_string($_POST['widths']) && $_POST['widths'] !== '') {
    $wd = json_decode($_POST['widths'], true);
    if (is_array($wd)) {
        foreach ($wd as $k => $v) {
            if (!is_string($k) || $k === '') {
                continue;
            }
            if ($v === null || $v === '' || !is_numeric($v)) {
                $widths_post[$k] = null;
                continue;
            }
            $px = (int) $v;
            if ($px < 40) {
                $px = 40;
            }
            if ($px > 1200) {
                $px = 1200;
            }
            $widths_post[$k] = $px;
        }
    }
}

/** Optional visibility overrides when saving order (checkboxes + reorder in one request). */
$pref_vis_override = null;
if (isset($_POST['preferences']) && is_string($_POST['preferences']) && $_POST['preferences'] !== '') {
    $pvo = json_decode($_POST['preferences'], true);
    if (is_array($pvo)) {
        $pref_vis_override = $pvo;
    }
}

/** Explicit column order as JSON array (preferred — object key order from json_decode is unreliable across clients). */
$order_keys = null;
if (isset($_POST['order_keys']) && is_string($_POST['order_keys']) && $_POST['order_keys'] !== '') {
    $decoded_keys = json_decode($_POST['order_keys'], true);
    if (is_array($decoded_keys) && count($decoded_keys) > 0) {
        $order_keys = $decoded_keys;
    }
}

if ($order_keys !== null) {
    $vis_lookup = [];
    $tab_clause = $has_tab_key ? " AND COALESCE(tab_key,'') = '" . $tab_key_esc . "'" : '';
    $vq = "SELECT column_key, is_visible FROM tbl_user_column_preferences WHERE user_id = $user_id AND page_name = '$page_name_esc'" . $tab_clause;
    $vr = @mysqli_query($conn, $vq);
    while ($vr && ($row = mysqli_fetch_assoc($vr))) {
        $vis_lookup[$row['column_key']] = (int)$row['is_visible'];
    }

    mysqli_begin_transaction($conn);
    try {
        $order = 0;
        foreach ($order_keys as $column_key) {
            if (!is_string($column_key) || $column_key === '') {
                continue;
            }
            $col_esc = mysqli_real_escape_string($conn, $column_key);
            $vis = 1;
            if ($pref_vis_override !== null && array_key_exists($column_key, $pref_vis_override)) {
                $pv = $pref_vis_override[$column_key];
                $vis = (int) (is_bool($pv) ? $pv : ($pv === 1 || $pv === '1' || $pv === true));
            } elseif (isset($vis_lookup[$column_key])) {
                $vis = $vis_lookup[$column_key];
            }

            $w_ins = 'NULL';
            if ($has_width_col && array_key_exists($column_key, $widths_post)) {
                $wp = $widths_post[$column_key];
                $w_ins = ($wp === null) ? 'NULL' : (string) (int) $wp;
            }

            if ($has_tab_key) {
                if ($has_width_col) {
                    $sql = "INSERT INTO `tbl_user_column_preferences`
                            (`user_id`, `page_name`, `tab_key`, `column_key`, `column_order`, `is_visible`, `column_width_px`, `created_at`)
                            VALUES ($user_id, '$page_name_esc', '$tab_key_esc', '$col_esc', $order, $vis, $w_ins, NOW())
                            ON DUPLICATE KEY UPDATE `is_visible` = $vis, `column_order` = $order, `column_width_px` = COALESCE(VALUES(`column_width_px`), `column_width_px`)";
                } else {
                    $sql = "INSERT INTO `tbl_user_column_preferences`
                            (`user_id`, `page_name`, `tab_key`, `column_key`, `column_order`, `is_visible`, `created_at`)
                            VALUES ($user_id, '$page_name_esc', '$tab_key_esc', '$col_esc', $order, $vis, NOW())
                            ON DUPLICATE KEY UPDATE `is_visible` = $vis, `column_order` = $order";
                }
            } else {
                if ($has_width_col) {
                    $sql = "INSERT INTO `tbl_user_column_preferences`
                            (`user_id`, `page_name`, `column_key`, `column_order`, `is_visible`, `column_width_px`, `created_at`)
                            VALUES ($user_id, '$page_name_esc', '$col_esc', $order, $vis, $w_ins, NOW())
                            ON DUPLICATE KEY UPDATE `is_visible` = $vis, `column_order` = $order, `column_width_px` = COALESCE(VALUES(`column_width_px`), `column_width_px`)";
                } else {
                    $sql = "INSERT INTO `tbl_user_column_preferences`
                            (`user_id`, `page_name`, `column_key`, `column_order`, `is_visible`, `created_at`)
                            VALUES ($user_id, '$page_name_esc', '$col_esc', $order, $vis, NOW())
                            ON DUPLICATE KEY UPDATE `is_visible` = $vis, `column_order` = $order";
                }
            }

            if (!mysqli_query($conn, $sql)) {
                throw new Exception('Save failed: ' . mysqli_error($conn));
            }
            $order++;
        }

        if ($order === 0) {
            throw new Exception('No valid column keys in order_keys');
        }

        mysqli_commit($conn);
        echo json_encode([
            'status' => 'success',
            'message' => 'Column preferences saved',
            'saved_rows' => $order,
            'has_tab_key' => $has_tab_key,
            'used_order_keys' => true,
        ]);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

$preferences = isset($_POST['preferences']) ? json_decode($_POST['preferences'], true) : null;

if (!is_array($preferences) || $preferences === []) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid preferences']);
    exit;
}

// Must be a JSON object (column_key => visibility), not a JSON array [0,1,2,...]
$prefKeys = array_keys($preferences);
if ($prefKeys !== [] && $prefKeys === range(0, count($preferences) - 1)) {
    echo json_encode(['status' => 'error', 'message' => 'Preferences must be a JSON object mapping column_key to visibility']);
    exit;
}

mysqli_begin_transaction($conn);
try {
    $order = 0;
    foreach ($preferences as $column_key => $is_visible) {
        if (!is_string($column_key) || $column_key === '') {
            continue;
        }
        $col_esc = mysqli_real_escape_string($conn, $column_key);
        $vis = (int)(is_bool($is_visible) ? $is_visible : ($is_visible === 1 || $is_visible === '1' || $is_visible === true));

        $w_ins = 'NULL';
        if ($has_width_col && array_key_exists($column_key, $widths_post)) {
            $wp = $widths_post[$column_key];
            $w_ins = ($wp === null) ? 'NULL' : (string) (int) $wp;
        }

        if ($has_tab_key) {
            if ($has_width_col) {
                $sql = "INSERT INTO `tbl_user_column_preferences`
                        (`user_id`, `page_name`, `tab_key`, `column_key`, `column_order`, `is_visible`, `column_width_px`, `created_at`)
                        VALUES ($user_id, '$page_name_esc', '$tab_key_esc', '$col_esc', $order, $vis, $w_ins, NOW())
                        ON DUPLICATE KEY UPDATE `is_visible` = $vis, `column_order` = $order, `column_width_px` = COALESCE(VALUES(`column_width_px`), `column_width_px`)";
            } else {
                $sql = "INSERT INTO `tbl_user_column_preferences`
                        (`user_id`, `page_name`, `tab_key`, `column_key`, `column_order`, `is_visible`, `created_at`)
                        VALUES ($user_id, '$page_name_esc', '$tab_key_esc', '$col_esc', $order, $vis, NOW())
                        ON DUPLICATE KEY UPDATE `is_visible` = $vis, `column_order` = $order";
            }
        } else {
            if ($has_width_col) {
                $sql = "INSERT INTO `tbl_user_column_preferences`
                        (`user_id`, `page_name`, `column_key`, `column_order`, `is_visible`, `column_width_px`, `created_at`)
                        VALUES ($user_id, '$page_name_esc', '$col_esc', $order, $vis, $w_ins, NOW())
                        ON DUPLICATE KEY UPDATE `is_visible` = $vis, `column_order` = $order, `column_width_px` = COALESCE(VALUES(`column_width_px`), `column_width_px`)";
            } else {
                $sql = "INSERT INTO `tbl_user_column_preferences`
                        (`user_id`, `page_name`, `column_key`, `column_order`, `is_visible`, `created_at`)
                        VALUES ($user_id, '$page_name_esc', '$col_esc', $order, $vis, NOW())
                        ON DUPLICATE KEY UPDATE `is_visible` = $vis, `column_order` = $order";
            }
        }

        if (!mysqli_query($conn, $sql)) {
            throw new Exception('Save failed: ' . mysqli_error($conn));
        }
        $order++;
    }

    if ($order === 0) {
        throw new Exception('No valid column keys in preferences');
    }

    mysqli_commit($conn);
    echo json_encode([
        'status' => 'success',
        'message' => 'Column preferences saved',
        'saved_rows' => $order,
        'has_tab_key' => $has_tab_key,
    ]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
