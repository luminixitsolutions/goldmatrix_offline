<?php
/**
 * Branch-local product settings (sub-branch overrides) + tbl_product_tax.branch_id.
 */

if (!function_exists('auragold_ensure_product_branch_local_schema')) {
    function auragold_ensure_product_branch_local_schema(mysqli $conn) {
        static $done = [];
        $key = spl_object_hash($conn);
        if (!empty($done[$key])) {
            return true;
        }

        $t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_product_branch_settings'");
        if ($t && mysqli_num_rows($t) === 0) {
            mysqli_free_result($t);
            @mysqli_query(
                $conn,
                "CREATE TABLE IF NOT EXISTS tbl_product_branch_settings (
                    product_id INT NOT NULL,
                    branch_id INT NOT NULL,
                    category_id INT NULL,
                    is_stock_item TINYINT NOT NULL DEFAULT 1,
                    is_barcode TINYINT NOT NULL DEFAULT 1,
                    updated_at DATETIME NULL,
                    PRIMARY KEY (product_id, branch_id),
                    KEY branch_id (branch_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
            );
        } elseif ($t) {
            mysqli_free_result($t);
        }

        $c = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_product_tax LIKE 'branch_id'");
        if ($c && mysqli_num_rows($c) === 0) {
            mysqli_free_result($c);
            @mysqli_query($conn, "ALTER TABLE tbl_product_tax ADD COLUMN branch_id INT NULL DEFAULT NULL AFTER product_id");
            @mysqli_query($conn, "ALTER TABLE tbl_product_tax ADD KEY product_branch_tax (product_id, branch_id)");
        } elseif ($c) {
            mysqli_free_result($c);
        }

        auragold_ensure_product_vendor_id_column($conn);
        auragold_ensure_product_branch_is_barcode_column($conn);
        auragold_ensure_product_characteristics_carat_varchar($conn);

        $done[$key] = true;
        return true;
    }
}

if (!function_exists('auragold_ensure_product_characteristics_carat_varchar')) {
    /**
     * tbl_product_characteristics.carat must store karat labels (22K, 24K), not numeric decimals.
     */
    function auragold_ensure_product_characteristics_carat_varchar(mysqli $conn): void
    {
        static $done = [];
        $key = spl_object_hash($conn);
        if (!empty($done[$key])) {
            return;
        }

        $t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_product_characteristics'");
        if (!$t || mysqli_num_rows($t) === 0) {
            if ($t) {
                mysqli_free_result($t);
            }
            $done[$key] = true;
            return;
        }
        mysqli_free_result($t);

        $c = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_product_characteristics LIKE 'carat'");
        if (!$c || mysqli_num_rows($c) === 0) {
            if ($c) {
                mysqli_free_result($c);
            }
            $done[$key] = true;
            return;
        }
        $col = mysqli_fetch_assoc($c);
        mysqli_free_result($c);
        if (!$col) {
            $done[$key] = true;
            return;
        }

        $type = strtolower(trim((string) ($col['Type'] ?? '')));
        if ($type === '' || strpos($type, 'varchar') !== false || strpos($type, 'char') !== false || strpos($type, 'text') !== false) {
            $done[$key] = true;
            return;
        }

        @mysqli_query(
            $conn,
            "ALTER TABLE tbl_product_characteristics
             MODIFY COLUMN carat VARCHAR(50) NULL DEFAULT NULL
             COMMENT 'Karat label from tbl_carat (e.g. 22K, 24K)'"
        );

        $done[$key] = true;
    }
}

if (!function_exists('auragold_product_characteristics_carat_sql_value')) {
    /** @return string SQL fragment: NULL | '22K' | '24' */
    function auragold_product_characteristics_carat_sql_value(mysqli $conn, string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return 'NULL';
        }

        auragold_ensure_product_characteristics_carat_varchar($conn);

        $col = function_exists('getRecord')
            ? getRecord("SHOW COLUMNS FROM tbl_product_characteristics WHERE Field = 'carat' LIMIT 1")
            : null;
        $type = strtolower(trim((string) ($col['Type'] ?? 'varchar')));

        $value = $raw;
        if (strpos($type, 'decimal') !== false || strpos($type, 'int') !== false || strpos($type, 'float') !== false || strpos($type, 'double') !== false) {
            if (preg_match('/^([\d.]+)/', $raw, $m)) {
                $value = $m[1];
            } elseif (is_numeric($raw)) {
                $value = $raw;
            } else {
                $value = '0';
            }
        }

        return "'" . esc($value) . "'";
    }
}

if (!function_exists('auragold_ensure_product_branch_is_barcode_column')) {
    function auragold_ensure_product_branch_is_barcode_column(mysqli $conn) {
        static $done = [];
        $key = spl_object_hash($conn);
        if (!empty($done[$key])) {
            return true;
        }
        $t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_product_branch_settings'");
        if (!$t || mysqli_num_rows($t) === 0) {
            if ($t) {
                mysqli_free_result($t);
            }
            $done[$key] = true;
            return true;
        }
        mysqli_free_result($t);
        $c = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_product_branch_settings LIKE 'is_barcode'");
        if ($c && mysqli_num_rows($c) === 0) {
            mysqli_free_result($c);
            @mysqli_query(
                $conn,
                'ALTER TABLE tbl_product_branch_settings ADD COLUMN is_barcode TINYINT NOT NULL DEFAULT 1 AFTER is_stock_item'
            );
        } elseif ($c) {
            mysqli_free_result($c);
        }
        $done[$key] = true;
        return true;
    }
}

if (!function_exists('auragold_product_branch_is_barcode_enabled')) {
    /**
     * Whether product opening should assign barcodes for this product on the given branch.
     * Defaults to enabled (1) when no branch row exists (legacy products).
     */
    function auragold_product_branch_is_barcode_enabled(mysqli $conn, int $product_id, int $branch_id = 0): bool {
        if ($product_id <= 0) {
            return true;
        }
        auragold_ensure_product_branch_local_schema($conn);
        $c = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_product_branch_settings LIKE 'is_barcode'");
        $has_col = ($c && mysqli_num_rows($c) > 0);
        if ($c) {
            mysqli_free_result($c);
        }
        if (!$has_col) {
            return true;
        }
        if ($branch_id <= 0 && function_exists('auragold_effective_branch_id')) {
            $branch_id = (int) auragold_effective_branch_id();
        }
        if ($branch_id <= 0) {
            return true;
        }
        $row = null;
        if (function_exists('getRecord')) {
            $row = getRecord(
                'SELECT is_barcode FROM tbl_product_branch_settings WHERE product_id = '
                . (int) $product_id . ' AND branch_id = ' . (int) $branch_id . ' LIMIT 1'
            );
        }
        if (!$row) {
            return true;
        }
        return (int) ($row['is_barcode'] ?? 1) === 1;
    }
}

if (!function_exists('auragold_ensure_product_vendor_id_column')) {
    function auragold_ensure_product_vendor_id_column(mysqli $conn) {
        static $done = [];
        $key = spl_object_hash($conn);
        if (!empty($done[$key])) {
            return true;
        }
        $t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_products'");
        if (!$t || mysqli_num_rows($t) === 0) {
            if ($t) {
                mysqli_free_result($t);
            }
            $done[$key] = true;
            return true;
        }
        mysqli_free_result($t);
        $c = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_products LIKE 'vendor_id'");
        if ($c && mysqli_num_rows($c) === 0) {
            mysqli_free_result($c);
            @mysqli_query($conn, 'ALTER TABLE tbl_products ADD COLUMN vendor_id INT NULL DEFAULT NULL AFTER article');
            @mysqli_query($conn, 'ALTER TABLE tbl_products ADD KEY idx_product_vendor_id (vendor_id)');
        } elseif ($c) {
            mysqli_free_result($c);
        }
        $done[$key] = true;
        return true;
    }
}

if (!function_exists('auragold_ensure_tbl_product_branches_is_active')) {
    /**
     * Soft allocation: is_active=0 means linked to the branch but not yet "turned on" in the activation UI.
     */
    function auragold_ensure_tbl_product_branches_is_active(mysqli $conn) {
        static $done = [];
        $k = spl_object_hash($conn);
        if (!empty($done[$k])) {
            return;
        }
        $t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_product_branches'");
        if (!$t || mysqli_num_rows($t) === 0) {
            if ($t) {
                mysqli_free_result($t);
            }
            $done[$k] = true;
            return;
        }
        mysqli_free_result($t);
        $c = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_product_branches LIKE 'is_active'");
        if ($c && mysqli_num_rows($c) === 0) {
            mysqli_free_result($c);
            @mysqli_query(
                $conn,
                'ALTER TABLE tbl_product_branches ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER branch_id'
            );
        } elseif ($c) {
            mysqli_free_result($c);
        }
        $done[$k] = true;
    }
}

if (!function_exists('auragold_tbl_product_branches_has_is_active')) {
    function auragold_tbl_product_branches_has_is_active(mysqli $conn) {
        static $cache = [];
        $k = spl_object_hash($conn);
        if (isset($cache[$k])) {
            return $cache[$k];
        }
        $c = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_product_branches LIKE 'is_active'");
        $cache[$k] = ($c && mysqli_num_rows($c) > 0);
        if ($c) {
            mysqli_free_result($c);
        }
        return $cache[$k];
    }
}

if (!function_exists('auragold_main_branch_id_for_product_seed')) {
    /**
     * Registry main branch id for the given branch (self if already main).
     */
    function auragold_main_branch_id_for_product_seed(int $branch_id) {
        if ($branch_id <= 0) {
            return 0;
        }
        $br = getRecordMaster('SELECT main_branch_id FROM tbl_branches WHERE id = ' . (int) $branch_id . ' LIMIT 1');
        if (!$br) {
            return 0;
        }
        $mb = (int) ($br['main_branch_id'] ?? 0);
        return $mb > 0 ? $mb : (int) $branch_id;
    }
}

if (!function_exists('auragold_product_opening_reconcile_sub_allocations')) {
    /**
     * Ensure each sub-branch under the main has a tbl_product_branches row for this product.
     * New rows: creating branch active (1), other subs inactive (0). Existing rows are left unchanged.
     */
    function auragold_product_opening_reconcile_sub_allocations(mysqli $conn, int $product_id, int $main_branch_id, int $creating_branch_id) {
        if ($product_id <= 0 || $main_branch_id <= 0 || !auragold_tbl_product_branches_has_is_active($conn)) {
            return;
        }
        $subs = getListMaster(
            'SELECT id FROM tbl_branches WHERE main_branch_id = ' . (int) $main_branch_id . ' AND status = 1'
        );
        if (empty($subs) || !is_array($subs)) {
            return;
        }
        $pid = (int) $product_id;
        foreach ($subs as $row) {
            $sid = (int) ($row['id'] ?? 0);
            if ($sid <= 0) {
                continue;
            }
            $ex = mysqli_query(
                $conn,
                'SELECT id FROM tbl_product_branches WHERE product_id = ' . $pid . ' AND branch_id = ' . $sid . ' LIMIT 1'
            );
            $has = ($ex && mysqli_num_rows($ex) > 0);
            if ($ex) {
                mysqli_free_result($ex);
            }
            if ($has) {
                continue;
            }
            $act = ($sid === (int) $creating_branch_id) ? 1 : 0;
            $sql = 'INSERT INTO tbl_product_branches (product_id, branch_id, is_active) VALUES (' . $pid . ', ' . $sid . ', ' . $act . ')';
            if (!mysqli_query($conn, $sql)) {
                throw new Exception('Sub-branch product allocation failed: ' . mysqli_error($conn));
            }
        }
    }
}
