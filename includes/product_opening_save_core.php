<?php
/**
 * Product opening save (product-opening.php → product-save.php).
 * Used for the working DB and, in sync mode, for other branch MySQL databases.
 */

if (!function_exists('auragold_ps_row')) {
    function auragold_ps_row(mysqli $conn, $sql) {
        $res = mysqli_query($conn, $sql);
        return ($res && mysqli_num_rows($res) > 0) ? mysqli_fetch_assoc($res) : null;
    }
}

if (!function_exists('auragold_sql_products_scope_for_branch')) {
    require_once __DIR__ . '/auragold_product_catalog_scope.php';
}

require_once __DIR__ . '/auragold_product_branch_local_schema.php';
require_once __DIR__ . '/auragold_metal_opening_fields_schema.php';

if (!function_exists('auragold_tbl_has_column')) {
    require_once __DIR__ . '/auragold_branch_data_scope.php';
}

/**
 * @param array $opts Optional: sync (bool), sync_product_id (int), sync_is_update (bool)
 * @return array{product_id:int,is_update:bool}
 */
function auragold_product_opening_save(mysqli $conn, array $post, array $opts = []) {
    $sync = !empty($opts['sync']);

    /* ================== PRODUCT MASTER ================== */

    $name           = esc($post['name'] ?? '');
    $alternate_name = esc($post['alternate_name'] ?? '');
    $article        = esc($post['article'] ?? '');
    $category_id    = isset($post['category_id']) && $post['category_id'] != '' ? (int)$post['category_id'] : 0;
    $vendor_id      = isset($post['vendor_id']) && $post['vendor_id'] != '' ? (int) $post['vendor_id'] : 0;

    auragold_ensure_product_branch_local_schema($conn);
    if (function_exists('auragold_ensure_opening_stock_weight_precision')) {
        auragold_ensure_opening_stock_weight_precision($conn);
    }

    $branch_ids = [];
    if (isset($post['branch_ids']) && is_array($post['branch_ids'])) {
        foreach ($post['branch_ids'] as $bid) {
            $bid = (int)$bid;
            if ($bid > 0) {
                $branch_ids[] = $bid;
            }
        }
    }

    if (empty($branch_ids)) {
        $branch_id = (int)($post['branch_id'] ?? 0);
        if ($branch_id > 0) {
            $branch_ids[] = $branch_id;
        }
    }
    if (empty($branch_ids)) {
        $sid = 0;
        if (!empty($_SESSION['working_branch_id'])) {
            $sid = (int) $_SESSION['working_branch_id'];
        } elseif (!empty($_SESSION['branch_id'])) {
            $sid = (int) $_SESSION['branch_id'];
        }
        if ($sid > 0) {
            $chk = getRecordMaster("SELECT id FROM tbl_branches WHERE id = $sid AND status = 1 LIMIT 1");
            if ($chk) {
                $branch_ids[] = $sid;
            }
        }
    }
    if (empty($branch_ids)) {
        $main = getRecordMaster("SELECT id FROM tbl_branches WHERE IFNULL(main_branch_id,0)=0 AND status = 1 ORDER BY id ASC LIMIT 1");
        if ($main) {
            $branch_ids[] = (int) $main['id'];
        }
    }

    // Branches chosen for catalog visibility (tbl_product_branches). Kept separate from $branch_ids used for characteristics (single working branch below).
    $catalog_branch_ids = array_values(array_unique(array_filter(array_map('intval', $branch_ids))));

    $working_sess = 0;
    if (!empty($_SESSION['working_branch_id'])) {
        $working_sess = (int) $_SESSION['working_branch_id'];
    } elseif (!empty($_SESSION['branch_id'])) {
        $working_sess = (int) $_SESSION['branch_id'];
    }

    $product_id_early_for_scope = isset($post['product_id']) ? (int) $post['product_id'] : 0;
    $is_update_early_for_scope = ($product_id_early_for_scope > 0);

    /** Sub-branch edit: save opening + characteristics only for this branch; do not rewrite global catalog for all branches. New products from a sub-branch are allowed (master row + allocation). */
    $sub_branch_scoped = false;
    if (!$sync && $working_sess > 0) {
        $wbr = getRecordMaster('SELECT main_branch_id FROM tbl_branches WHERE id = ' . $working_sess . ' LIMIT 1');
        if ($wbr && (int) ($wbr['main_branch_id'] ?? 0) > 0 && $is_update_early_for_scope) {
            $sub_branch_scoped = true;
            $branch_ids = [$working_sess];
            $catalog_branch_ids = [(int) $working_sess];
        }
    }

    /** Effective branch for all branch-scoped rows (characteristics, tax, tbl_product_branch_settings, stock). */
    $current_branch_id = $working_sess > 0 ? $working_sess : 0;
    if ($current_branch_id <= 0) {
        $mbr = getRecordMaster('SELECT id FROM tbl_branches WHERE IFNULL(main_branch_id,0)=0 AND status = 1 ORDER BY id ASC LIMIT 1');
        if ($mbr && !empty($mbr['id'])) {
            $current_branch_id = (int) $mbr['id'];
        }
    }
    // Product opening saves must touch only the logged-in branch, never every branch from legacy multi-select.
    if (!$sync && $current_branch_id > 0) {
        $branch_ids = [$current_branch_id];
    }
    // Catalog (tbl_product_branches): only the current logged-in branch — not every id from a legacy multi-select
    // and not every sub under a main. Main login → main row; sub login → that sub (master rows live in the main DB when routed there).
    if ($sub_branch_scoped && $working_sess > 0) {
        $catalog_branch_ids = [(int) $working_sess];
    } elseif (!$sync && $current_branch_id > 0) {
        $catalog_branch_ids = [(int) $current_branch_id];
    }

    $branch_ids_csv = implode(',', array_values(array_unique(array_filter(array_map('intval', $branch_ids)))));
    if ($branch_ids_csv === '') {
        $branch_ids_csv = '0';
    }

    $is_stock_item = isset($post['is_stock_item']) ? 1 : 0;
    $is_barcode    = isset($post['is_barcode']) ? 1 : 0;

    if ($name == '' || empty($branch_ids)) {
        throw new Exception("Required fields missing: Name and at least one Branch are required");
    }

    if ($sync) {
        $product_id = (int)($opts['sync_product_id'] ?? 0);
        $is_update  = !empty($opts['sync_is_update']);
        if ($product_id <= 0) {
            throw new Exception('Sync: invalid product id.');
        }
        $existing_product = auragold_ps_row($conn, "SELECT id FROM tbl_products WHERE name = '$name' AND status = 1");
        if ($existing_product) {
            $existing_id = (int)$existing_product['id'];
            if ($existing_id !== $product_id) {
                throw new Exception("A product with the name '$name' already exists in another branch database.");
            }
        }
    } else {
        $existing_product = auragold_ps_row($conn, "SELECT id FROM tbl_products WHERE name = '$name' AND status = 1");
        if ($existing_product) {
            $existing_id = (int)$existing_product['id'];
            if (!isset($post['product_id']) || (int)$post['product_id'] != $existing_id) {
                throw new Exception("A product with the name '$name' already exists. Please use a different name.");
            }
        }

        $product_id = isset($post['product_id']) ? (int)$post['product_id'] : 0;
        $is_update = ($product_id > 0);
    }

    if ($is_update) {
        if ($sub_branch_scoped) {
            // Characteristics are updated in place below (preserve ids for stock journal links).
        } else {
            $update_where = "WHERE id = $product_id AND status = 1";
            if (!$sync) {
                $wb = (int) ($_SESSION['working_branch_id'] ?? $_SESSION['branch_id'] ?? 0);
                if ($wb > 0 && $product_id > 0) {
                    $brow = getRecordMaster("SELECT main_branch_id FROM tbl_branches WHERE id = $wb LIMIT 1");
                    if ($brow && (int) ($brow['main_branch_id'] ?? 0) > 0) {
                        $main_b = (int) $brow['main_branch_id'];
                        $scope = auragold_sql_products_scope_for_branch($main_b);
                        $in_catalog = auragold_ps_row(
                            $conn,
                            "SELECT id FROM tbl_products WHERE id = $product_id AND ($scope) AND status IN (0, 1)"
                        );
                        if ($in_catalog) {
                            $update_where = "WHERE id = $product_id";
                        }
                    }
                }
            }

            // Shared product master: name / article / vendor. Category & stock are per-branch (tbl_product_branch_settings).
            $has_vendor_col = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_products', 'vendor_id');
            $vendor_sql = $has_vendor_col
                ? ', vendor_id = ' . ($vendor_id > 0 ? (string) $vendor_id : 'NULL')
                : '';
            $sql = "
                UPDATE tbl_products 
                SET name = '$name',
                    alternate_name = '$alternate_name',
                    article = '$article'
                    $vendor_sql,
                    updated_at = NOW()
                $update_where
            ";

            if (!mysqli_query($conn, $sql)) {
                throw new Exception("Product update failed: " . mysqli_error($conn));
            }
        }
    } else {
        $has_cat_col = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_products', 'category_id');
        $has_stk_col = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_products', 'is_stock_item');
        $has_vendor_col = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_products', 'vendor_id');
        $cat0          = (int) $category_id;
        $stk0          = (int) $is_stock_item;
        if ($sync) {
            $cols = ['id', 'name', 'alternate_name', 'article'];
            $vals = [(string) (int) $product_id, "'$name'", "'$alternate_name'", "'$article'"];
        } else {
            $cols = ['name', 'alternate_name', 'article'];
            $vals = ["'$name'", "'$alternate_name'", "'$article'"];
        }
        if ($has_vendor_col) {
            $cols[] = 'vendor_id';
            $vals[] = $vendor_id > 0 ? (string) $vendor_id : 'NULL';
        }
        if ($has_cat_col) {
            $cols[] = 'category_id';
            $vals[] = "'" . $cat0 . "'";
        }
        if ($has_stk_col) {
            $cols[] = 'is_stock_item';
            $vals[] = "'" . $stk0 . "'";
        }
        $cols[] = 'created_at';
        $vals[] = 'NOW()';
        $sql    = 'INSERT INTO tbl_products (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';

        if (!mysqli_query($conn, $sql)) {
            throw new Exception("Product insert failed: " . mysqli_error($conn));
        }

        if (!$sync) {
            $product_id = (int) mysqli_insert_id($conn);
        }
    }

    /* ================== BRANCH-LOCAL SETTINGS (category / stock) + PRODUCT TAX ================== */

    auragold_ensure_product_branch_local_schema($conn);
    if ($current_branch_id > 0) {
        $wbset = (int) $current_branch_id;
        $catIns = (int) $category_id;
        $catSql = $catIns > 0 ? (string) $catIns : 'NULL';
        $stkIns = (int) $is_stock_item;
        $bcIns  = (int) $is_barcode;
        $has_bc_col = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_product_branch_settings', 'is_barcode');
        $bc_sql = $has_bc_col ? ', is_barcode' : '';
        $bc_val = $has_bc_col ? ', ' . $bcIns : '';
        $bc_upd = $has_bc_col ? ",\n                is_barcode = VALUES(is_barcode)" : '';
        $sqlUpsert = "
            INSERT INTO tbl_product_branch_settings (product_id, branch_id, category_id, is_stock_item{$bc_sql}, updated_at)
            VALUES ($product_id, $wbset, $catSql, $stkIns{$bc_val}, NOW())
            ON DUPLICATE KEY UPDATE
                category_id = VALUES(category_id),
                is_stock_item = VALUES(is_stock_item){$bc_upd},
                updated_at = NOW()
        ";
        if (!mysqli_query($conn, $sqlUpsert)) {
            throw new Exception('Branch product settings save failed: ' . mysqli_error($conn));
        }
    }

    /* ================== PRODUCT ↔ BRANCH CATALOG (which branches see this product) ================== */
    if (!$sync && !$sub_branch_scoped && $product_id > 0 && !empty($catalog_branch_ids)) {
        $tb_pb = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_product_branches'");
        if ($tb_pb && mysqli_num_rows($tb_pb) > 0) {
            mysqli_free_result($tb_pb);
            auragold_ensure_tbl_product_branches_is_active($conn);
            $pb_has_active = auragold_tbl_product_branches_has_is_active($conn);

            if ($pb_has_active) {
                if (!$is_update) {
                    if (!mysqli_query($conn, 'DELETE FROM tbl_product_branches WHERE product_id = ' . (int) $product_id)) {
                        throw new Exception('Product branch catalog update failed: ' . mysqli_error($conn));
                    }
                }
                foreach ($catalog_branch_ids as $cbid) {
                    $cbid = (int) $cbid;
                    if ($cbid <= 0) {
                        continue;
                    }
                    $chk_b = getRecordMaster('SELECT id FROM tbl_branches WHERE id = ' . $cbid . ' AND status = 1 LIMIT 1');
                    if (!$chk_b) {
                        continue;
                    }
                    $pid = (int) $product_id;
                    $sql_pb = "INSERT INTO tbl_product_branches (product_id, branch_id, is_active) VALUES ($pid, $cbid, 1)
                        ON DUPLICATE KEY UPDATE is_active = 1";
                    if (!mysqli_query($conn, $sql_pb)) {
                        throw new Exception('Product branch catalog insert failed: ' . mysqli_error($conn));
                    }
                }
            } else {
                if (!mysqli_query($conn, 'DELETE FROM tbl_product_branches WHERE product_id = ' . (int) $product_id)) {
                    throw new Exception('Product branch catalog update failed: ' . mysqli_error($conn));
                }
                foreach ($catalog_branch_ids as $cbid) {
                    $cbid = (int) $cbid;
                    if ($cbid <= 0) {
                        continue;
                    }
                    $chk_b = getRecordMaster('SELECT id FROM tbl_branches WHERE id = ' . $cbid . ' AND status = 1 LIMIT 1');
                    if (!$chk_b) {
                        continue;
                    }
                    $sql_pb = 'INSERT IGNORE INTO tbl_product_branches (product_id, branch_id) VALUES (' . (int) $product_id . ', ' . $cbid . ')';
                    if (!mysqli_query($conn, $sql_pb)) {
                        throw new Exception('Product branch catalog insert failed: ' . mysqli_error($conn));
                    }
                }
            }
        } elseif ($tb_pb) {
            mysqli_free_result($tb_pb);
        }
    }

    if ($is_update && $current_branch_id > 0) {
        mysqli_query(
            $conn,
            'DELETE FROM tbl_product_tax WHERE product_id = ' . (int) $product_id . ' AND branch_id = ' . (int) $current_branch_id
        );
    }

    $tax_enabled = isset($post['tax_enabled']) && is_array($post['tax_enabled']) ? $post['tax_enabled'] : [];
    $tax_bid       = (int) $current_branch_id;

    if ($tax_bid > 0 && !empty($tax_enabled)) {
        auragold_ensure_product_branch_local_schema($conn);
        foreach ($tax_enabled as $tax_master_id => $on) {
            $tax_master_id = (int) $tax_master_id;
            if ($tax_master_id <= 0) {
                continue;
            }
            $tax_name_row = auragold_ps_row($conn, "SELECT name FROM tbl_tax_master WHERE id = $tax_master_id AND status = 1");
            if (!$tax_name_row) {
                continue;
            }
            $tax_type = esc($tax_name_row['name']);
            $tax_value = isset($post['tax_value'][$tax_master_id]) ? (float) $post['tax_value'][$tax_master_id] : 0;
            $calculation_mode = isset($post['tax_calculation_mode'][$tax_master_id]) ? esc($post['tax_calculation_mode'][$tax_master_id]) : 'Product Amount';
            $sql_tax = "INSERT INTO tbl_product_tax (product_id, branch_id, tax_type, tax_value, calculation_mode, status, created_at)
                VALUES ('$product_id', '$tax_bid', '$tax_type', '$tax_value', '$calculation_mode', 1, NOW())";
            if (!mysqli_query($conn, $sql_tax)) {
                throw new Exception("Product tax insert failed: " . mysqli_error($conn));
            }
        }
    } elseif ($tax_bid > 0 && empty($tax_enabled)) {
        // Legacy VAT / TAX BAH when no tax_master row selections are posted
        auragold_ensure_product_branch_local_schema($conn);
        $vat_enabled = isset($post['vat']) ? 1 : 0;
        $tax_bah_enabled = isset($post['tax_bah']) ? 1 : 0;
        if ($vat_enabled) {
            $vat_value = isset($post['vat_value']) ? (float) $post['vat_value'] : 5;
            $vat_calculation_mode = isset($post['vat_calculation_mode']) ? esc($post['vat_calculation_mode']) : 'Product Amount';
            $sql_vat = "INSERT INTO tbl_product_tax (product_id, branch_id, tax_type, tax_value, calculation_mode, status, created_at)
                VALUES ('$product_id', '$tax_bid', 'VAT', '$vat_value', '$vat_calculation_mode', 1, NOW())";
            if (!mysqli_query($conn, $sql_vat)) {
                throw new Exception("VAT tax insert failed: " . mysqli_error($conn));
            }
        }
        if ($tax_bah_enabled) {
            $tax_bah_value = isset($post['tax_bah_value']) ? (float) $post['tax_bah_value'] : 10;
            $tax_bah_calculation_mode = isset($post['tax_bah_calculation_mode']) ? esc($post['tax_bah_calculation_mode']) : 'Product Amount';
            $sql_tax_bah = "INSERT INTO tbl_product_tax (product_id, branch_id, tax_type, tax_value, calculation_mode, status, created_at)
                VALUES ('$product_id', '$tax_bid', 'TAX BAH', '$tax_bah_value', '$tax_bah_calculation_mode', 1, NOW())";
            if (!mysqli_query($conn, $sql_tax_bah)) {
                throw new Exception("TAX BAH insert failed: " . mysqli_error($conn));
            }
        }
    }

    /* ================== CHARACTERISTICS ROWS ================== */

        if (isset($post['row']) && is_array($post['row'])) {

        $kept_characteristic_ids = [];
        $existing_chars_by_id = [];
        $existing_chars_by_metal = [];
        $candidates_by_metal = [];
        $sj_count_by_char = [];
        if ($is_update && $product_id > 0) {
            $char_branch_filter = $current_branch_id > 0 ? ' AND branch_id = ' . (int) $current_branch_id : '';
            $existing_char_rows = getList(
                "SELECT id, metal_id, status FROM tbl_product_characteristics WHERE product_id = $product_id $char_branch_filter ORDER BY id ASC"
            );
            if (is_array($existing_char_rows)) {
                foreach ($existing_char_rows as $ecr) {
                    $eid = (int) ($ecr['id'] ?? 0);
                    $emid = (int) ($ecr['metal_id'] ?? 0);
                    if ($eid > 0) {
                        $existing_chars_by_id[$eid] = $ecr;
                    }
                }
                $candidates_by_metal = [];
                foreach ($existing_char_rows as $ecr) {
                    $eid = (int) ($ecr['id'] ?? 0);
                    $emid = (int) ($ecr['metal_id'] ?? 0);
                    if ($eid > 0 && $emid > 0) {
                        if (!isset($candidates_by_metal[$emid])) {
                            $candidates_by_metal[$emid] = [];
                        }
                        $candidates_by_metal[$emid][] = $eid;
                    }
                }
                $sj_rows = getList(
                    "SELECT product_characteristic_id, COUNT(*) AS cnt FROM tbl_stock_journal
                     WHERE product_id = $product_id AND status = 'active'
                       AND (item_id IS NULL OR item_id = 0)
                       AND product_characteristic_id > 0
                     GROUP BY product_characteristic_id"
                );
                if (is_array($sj_rows)) {
                    foreach ($sj_rows as $sr) {
                        $cid = (int) ($sr['product_characteristic_id'] ?? 0);
                        if ($cid > 0) {
                            $sj_count_by_char[$cid] = (int) ($sr['cnt'] ?? 0);
                        }
                    }
                }
                foreach ($candidates_by_metal as $emid => $ids) {
                    $best_id = $ids[0];
                    $best_sj = (int) ($sj_count_by_char[$best_id] ?? 0);
                    foreach ($ids as $cid) {
                        $sj_cnt = (int) ($sj_count_by_char[$cid] ?? 0);
                        if ($sj_cnt > $best_sj || ($sj_cnt === $best_sj && $cid < $best_id)) {
                            $best_id = $cid;
                            $best_sj = $sj_cnt;
                        }
                    }
                    $existing_chars_by_metal[$emid] = $best_id;
                }
            }
        }

        foreach ($post['row'] as $r) {

            if (!isset($r['is_selected'])) {
                continue;
            }

            $metal_name = esc($r['metal'] ?? '');

            if ($metal_name == '') {
                continue;
            }

            $metal = auragold_ps_row($conn, "SELECT id FROM tbl_metal WHERE display_name='$metal_name' AND status=1");
            if (!$metal) {
                continue;
            }

            $metal_id = (int)$metal['id'];

            $characteristic_id = 0;
            if ($is_update) {
                $post_char_id = isset($r['characteristic_id']) ? (int) $r['characteristic_id'] : 0;
                if ($post_char_id > 0 && isset($existing_chars_by_id[$post_char_id])) {
                    $characteristic_id = $post_char_id;
                } elseif (isset($existing_chars_by_metal[$metal_id])) {
                    $characteristic_id = (int) $existing_chars_by_metal[$metal_id];
                }
            }

            $serialized_barcode = isset($r['serialized_barcode']) ? 1 : 0;

            $hsn            = esc($r['hsn'] ?? '');
            $sku_code       = esc($r['sku_code'] ?? '');
            $making_on      = esc($r['making_on'] ?? '');

            $diamond_category = esc($r['diamond_category'] ?? '');

            $unit_id = isset($r['unit_id']) && $r['unit_id'] != '' ? (int)$r['unit_id'] : 0;
            $location_id = isset($r['location_id']) && $r['location_id'] != '' ? (int)$r['location_id'] : 0;

            $purity_sale_raw = trim((string) ($r['purity_sale'] ?? ''));
            $purity_sale = ($purity_sale_raw !== '' ? esc($purity_sale_raw) : null);
            $purity_purchase = isset($r['purity_purchase']) ? 1 : 0;

            $wastage_sale_raw = trim((string) ($r['wastage_sale'] ?? ''));
            $wastage_sale = ($wastage_sale_raw !== '' ? esc($wastage_sale_raw) : null);
            $wastage_purchase_raw = trim((string) ($r['wastage_purchase'] ?? ''));
            $wastage_purchase = ($wastage_purchase_raw !== '' ? esc($wastage_purchase_raw) : null);

            $wt_per_piece_raw = trim((string) ($r['wt_per_piece'] ?? ''));
            $wt_per_piece = ($wt_per_piece_raw !== '' ? esc($wt_per_piece_raw) : null);

            $carat_sql = auragold_product_characteristics_carat_sql_value($conn, (string) ($r['carat'] ?? ''));
            $discount_raw = trim((string) ($r['discount'] ?? ''));
            $discount = ($discount_raw !== '' ? esc($discount_raw) : '0');

            $opening_weight_raw = trim((string) ($r['opening_weight'] ?? ''));
            $opening_weight = ($opening_weight_raw !== '' ? esc($opening_weight_raw) : '0');
            $opening_purity_raw = trim((string) ($r['opening_purity'] ?? ''));
            $opening_purity = ($opening_purity_raw !== '' ? esc($opening_purity_raw) : '0');
            $opening_qty_raw = trim((string) ($r['opening_qty'] ?? ''));
            $opening_qty = ($opening_qty_raw !== '' ? esc($opening_qty_raw) : '0');

            $final_weight_raw = trim((string) ($r['final_weight'] ?? ''));
            $final_weight = ($final_weight_raw !== '' ? esc($final_weight_raw) : '0');
            $rate_raw = trim((string) ($r['rate'] ?? ''));
            $rate = ($rate_raw !== '' ? esc($rate_raw) : '0');
            $value_raw = trim((string) ($r['value'] ?? ''));
            $value = ($value_raw !== '' ? esc($value_raw) : '0');

            $barcode_digits = ($r['barcode_digits'] !== '' ? (int)$r['barcode_digits'] : 5);
            if ($barcode_digits < 1) {
                $barcode_digits = 5;
            }
            $barcode_prefix_raw = trim($r['barcode_prefix'] ?? '');
            if ($barcode_prefix_raw === '') {
                $barcode_prefix_raw = 'RN';
            }
            $barcode_prefix = esc($barcode_prefix_raw);

            $row_barcode = isset($r['barcode']) ? trim($r['barcode']) : '';
            $barcode_raw_value = '';
            if ($is_barcode) {
                $pl        = strlen($barcode_prefix_raw);
                $barcode_still_matches_prefix = ($row_barcode !== '' && $pl > 0
                    && strlen($row_barcode) >= $pl
                    && substr_compare($row_barcode, $barcode_prefix_raw, 0, $pl, false) === 0);
                if ($row_barcode === '' || !$barcode_still_matches_prefix) {
                    $barcode_raw_value = generateBarcode($conn, $barcode_prefix_raw, $barcode_digits, []);
                } else {
                    $barcode_raw_value = $row_barcode;
                }
            }
            $generated_barcode = ($barcode_raw_value !== '') ? esc($barcode_raw_value) : '';
            $stock_barcode_sql = ($generated_barcode !== '') ? "'$generated_barcode'" : 'NULL';

            $cut        = esc($r['cut'] ?? '');
            $shape      = esc($r['shape'] ?? '');
            $color      = esc($r['color'] ?? '');
            $clarity    = esc($r['clarity'] ?? '');
            $sieve      = esc($r['sieve'] ?? '');
            $size       = esc($r['size'] ?? '');
            $style_code = esc($r['style_code'] ?? '');

            foreach ($branch_ids as $branch_id) {
                $check_barcode_column = mysqli_query($conn, "SHOW COLUMNS FROM tbl_product_characteristics LIKE 'barcode'");
                $has_barcode_column = ($check_barcode_column && mysqli_num_rows($check_barcode_column) > 0);

                $check_unit_column = mysqli_query($conn, "SHOW COLUMNS FROM tbl_product_characteristics LIKE 'unit_id'");
                $has_unit_column = ($check_unit_column && mysqli_num_rows($check_unit_column) > 0);

                $check_location_column = mysqli_query($conn, "SHOW COLUMNS FROM tbl_product_characteristics LIKE 'location_id'");
                $has_location_column = ($check_location_column && mysqli_num_rows($check_location_column) > 0);

                $check_purity_sale_column = mysqli_query($conn, "SHOW COLUMNS FROM tbl_product_characteristics LIKE 'purity_sale'");
                $has_purity_sale_column = ($check_purity_sale_column && mysqli_num_rows($check_purity_sale_column) > 0);

                $check_purity_purchase_column = mysqli_query($conn, "SHOW COLUMNS FROM tbl_product_characteristics LIKE 'purity_purchase'");
                $has_purity_purchase_column = ($check_purity_purchase_column && mysqli_num_rows($check_purity_purchase_column) > 0);

                $check_wastage_sale_column = mysqli_query($conn, "SHOW COLUMNS FROM tbl_product_characteristics LIKE 'wastage_sale'");
                $has_wastage_sale_column = ($check_wastage_sale_column && mysqli_num_rows($check_wastage_sale_column) > 0);

                $check_wastage_purchase_column = mysqli_query($conn, "SHOW COLUMNS FROM tbl_product_characteristics LIKE 'wastage_purchase'");
                $has_wastage_purchase_column = ($check_wastage_purchase_column && mysqli_num_rows($check_wastage_purchase_column) > 0);

                $check_wt_per_piece_column = mysqli_query($conn, "SHOW COLUMNS FROM tbl_product_characteristics LIKE 'wt_per_piece'");
                $has_wt_per_piece_column = ($check_wt_per_piece_column && mysqli_num_rows($check_wt_per_piece_column) > 0);

                $fields = "
                    product_id,
                    branch_id,
                    metal_id,
                    is_selected,
                    serialized_barcode,

                    hsn,
                    sku_code,
                    making_on,
                    diamond_category";

                $values = "
                    '$product_id',
                    '$branch_id',
                    '$metal_id',
                    1,
                    '$serialized_barcode',

                    '$hsn',
                    '$sku_code',
                    '$making_on',
                    '$diamond_category'";

                if ($has_unit_column) {
                    $fields .= ",\n                    unit_id";
                    $values .= ",\n                    " . ($unit_id > 0 ? "'$unit_id'" : "NULL");
                }

                if ($has_location_column) {
                    $fields .= ",\n                    location_id";
                    $values .= ",\n                    " . ($location_id > 0 ? "'$location_id'" : "NULL");
                }

                if ($has_purity_sale_column) {
                    $fields .= ",\n                    purity_sale";
                    $values .= ",\n                    " . ($purity_sale !== null ? "'$purity_sale'" : "NULL");
                }

                if ($has_purity_purchase_column) {
                    $fields .= ",\n                    purity_purchase";
                    $values .= ",\n                    '$purity_purchase'";
                }

                if ($has_wastage_sale_column) {
                    $fields .= ",\n                    wastage_sale";
                    $values .= ",\n                    " . ($wastage_sale !== null ? "'$wastage_sale'" : "NULL");
                }

                if ($has_wastage_purchase_column) {
                    $fields .= ",\n                    wastage_purchase";
                    $values .= ",\n                    " . ($wastage_purchase !== null ? "'$wastage_purchase'" : "NULL");
                }

                if ($has_wt_per_piece_column) {
                    $fields .= ",\n                    wt_per_piece";
                    $values .= ",\n                    " . ($wt_per_piece !== null ? "'$wt_per_piece'" : "NULL");
                }

                $fields .= ",
                    carat,
                    discount,

                    opening_weight,
                    opening_purity,
                    opening_qty,

                    final_weight,
                    rate,
                    value,

                    barcode_digits,
                    barcode_prefix";

                $values .= ",
                    $carat_sql,
                    '$discount',

                    '$opening_weight',
                    '$opening_purity',
                    '$opening_qty',

                    '$final_weight',
                    '$rate',
                    '$value',

                    '$barcode_digits',
                    '$barcode_prefix'";

                if ($has_barcode_column && $generated_barcode) {
                    $fields .= ",\n                    barcode";
                    $values .= ",\n                    '$generated_barcode'";
                }

                $fields .= ",

                    cut,
                    shape,
                    color,
                    clarity,
                    sieve,
                    size,
                    style_code,
                    created_at";

                $values .= ",

                    '$cut',
                    '$shape',
                    '$color',
                    '$clarity',
                    '$sieve',
                    '$size',
                    '$style_code',
                    NOW()";

                if ($characteristic_id > 0) {
                    $set_parts = [
                        "branch_id = '$branch_id'",
                        "metal_id = '$metal_id'",
                        "is_selected = 1",
                        "status = 1",
                        "serialized_barcode = '$serialized_barcode'",
                        "hsn = '$hsn'",
                        "sku_code = '$sku_code'",
                        "making_on = '$making_on'",
                        "diamond_category = '$diamond_category'",
                    ];
                    if ($has_unit_column) {
                        $set_parts[] = 'unit_id = ' . ($unit_id > 0 ? "'$unit_id'" : 'NULL');
                    }
                    if ($has_location_column) {
                        $set_parts[] = 'location_id = ' . ($location_id > 0 ? "'$location_id'" : 'NULL');
                    }
                    if ($has_purity_sale_column) {
                        $set_parts[] = 'purity_sale = ' . ($purity_sale !== null ? "'$purity_sale'" : 'NULL');
                    }
                    if ($has_purity_purchase_column) {
                        $set_parts[] = "purity_purchase = '$purity_purchase'";
                    }
                    if ($has_wastage_sale_column) {
                        $set_parts[] = 'wastage_sale = ' . ($wastage_sale !== null ? "'$wastage_sale'" : 'NULL');
                    }
                    if ($has_wastage_purchase_column) {
                        $set_parts[] = 'wastage_purchase = ' . ($wastage_purchase !== null ? "'$wastage_purchase'" : 'NULL');
                    }
                    if ($has_wt_per_piece_column) {
                        $set_parts[] = 'wt_per_piece = ' . ($wt_per_piece !== null ? "'$wt_per_piece'" : 'NULL');
                    }
                    $set_parts[] = "carat = $carat_sql";
                    $set_parts[] = "discount = '$discount'";
                    $set_parts[] = "opening_weight = '$opening_weight'";
                    $set_parts[] = "opening_purity = '$opening_purity'";
                    $set_parts[] = "opening_qty = '$opening_qty'";
                    $set_parts[] = "final_weight = '$final_weight'";
                    $set_parts[] = "rate = '$rate'";
                    $set_parts[] = "value = '$value'";
                    $set_parts[] = "barcode_digits = '$barcode_digits'";
                    $set_parts[] = "barcode_prefix = '$barcode_prefix'";
                    if ($has_barcode_column && $generated_barcode) {
                        $set_parts[] = "barcode = '$generated_barcode'";
                    }
                    $set_parts[] = "cut = '$cut'";
                    $set_parts[] = "shape = '$shape'";
                    $set_parts[] = "color = '$color'";
                    $set_parts[] = "clarity = '$clarity'";
                    $set_parts[] = "sieve = '$sieve'";
                    $set_parts[] = "size = '$size'";
                    $set_parts[] = "style_code = '$style_code'";
                    $sql = "
                        UPDATE tbl_product_characteristics
                        SET " . implode(",\n                            ", $set_parts) . "
                        WHERE id = $characteristic_id AND product_id = $product_id
                    ";
                } else {
                    $sql = "
                INSERT INTO tbl_product_characteristics
                (
                    $fields
                )
                VALUES
                (
                    $values
                )
                ";
                }

                if (!mysqli_query($conn, $sql)) {
                    throw new Exception(
                        ($characteristic_id > 0 ? 'Characteristics update' : 'Characteristics insert')
                        . " failed for branch $branch_id: " . mysqli_error($conn)
                    );
                }

                if ($characteristic_id <= 0) {
                    $characteristic_id = (int) mysqli_insert_id($conn);
                }
                if ($characteristic_id > 0) {
                    $kept_characteristic_ids[$characteristic_id] = true;
                    if (isset($candidates_by_metal[$metal_id]) && is_array($candidates_by_metal[$metal_id])) {
                        foreach ($candidates_by_metal[$metal_id] as $orphan_cid) {
                            $orphan_cid = (int) $orphan_cid;
                            if ($orphan_cid > 0 && $orphan_cid !== $characteristic_id) {
                                mysqli_query(
                                    $conn,
                                    "UPDATE tbl_stock_journal SET product_characteristic_id = $characteristic_id
                                     WHERE product_characteristic_id = $orphan_cid AND product_id = $product_id
                                       AND status = 'active' AND (item_id IS NULL OR item_id = 0)"
                                );
                            }
                        }
                    }
                }

                // Opening stock in tbl_stock drives inventory reports (e.g. gold-silver-analysis). Sync whenever
                // this characteristic row is saved — not only when "Show In Stock" (is_stock_item) is checked.
                $existing_stock = auragold_ps_row(
                    $conn,
                    "SELECT id FROM tbl_stock WHERE product_characteristic_id = $characteristic_id AND stock_type = 'opening' ORDER BY id DESC LIMIT 1"
                );

                if ($existing_stock) {
                    $stock_id = (int) $existing_stock['id'];
                    $sql_stock = "
                        UPDATE tbl_stock SET
                            barcode = $stock_barcode_sql,
                            opening_weight = '$opening_weight',
                            opening_purity = '$opening_purity',
                            opening_qty = '$opening_qty',
                            final_weight = '$final_weight',
                            rate = '$rate',
                            value = '$value',
                            current_weight = '$opening_weight',
                            current_qty = '$opening_qty',
                            status = 1,
                            updated_at = NOW()
                        WHERE id = $stock_id
                        ";
                } else {
                    $sql_stock = "
                        INSERT INTO tbl_stock
                        (
                            product_id,
                            product_characteristic_id,
                            branch_id,
                            metal_id,
                            opening_weight,
                            opening_purity,
                            opening_qty,
                            final_weight,
                            rate,
                            value,
                            current_weight,
                            current_qty,
                            stock_type,
                            transaction_date,
                            barcode,
                            created_at
                        )
                        VALUES
                        (
                            '$product_id',
                            '$characteristic_id',
                            '$branch_id',
                            '$metal_id',
                            '$opening_weight',
                            '$opening_purity',
                            '$opening_qty',
                            '$final_weight',
                            '$rate',
                            '$value',
                            '$opening_weight',
                            '$opening_qty',
                            'opening',
                            CURDATE(),
                            $stock_barcode_sql,
                            NOW()
                        )
                        ";
                }

                if (!mysqli_query($conn, $sql_stock)) {
                    throw new Exception("Stock insert/update failed for branch $branch_id: " . mysqli_error($conn));
                }
            }
        }

        if ($is_update && $current_branch_id > 0) {
            $bstk = (int) $current_branch_id;
            if (!empty($kept_characteristic_ids)) {
                $keep_csv = implode(',', array_map('intval', array_keys($kept_characteristic_ids)));
                mysqli_query(
                    $conn,
                    "UPDATE tbl_product_characteristics SET status = 0 WHERE product_id = $product_id AND branch_id = $bstk AND id NOT IN ($keep_csv)"
                );
                mysqli_query(
                    $conn,
                    "UPDATE tbl_stock SET status = 0, updated_at = NOW() WHERE product_id = $product_id AND stock_type = 'opening' AND branch_id = $bstk AND product_characteristic_id NOT IN ($keep_csv)"
                );
            } else {
                mysqli_query(
                    $conn,
                    "UPDATE tbl_product_characteristics SET status = 0 WHERE product_id = $product_id AND branch_id = $bstk"
                );
                mysqli_query(
                    $conn,
                    "UPDATE tbl_stock SET status = 0, updated_at = NOW() WHERE product_id = $product_id AND stock_type = 'opening' AND branch_id = $bstk"
                );
            }
        }
    }

    return [
        'product_id'         => $product_id,
        'is_update'          => $is_update,
        'skip_branch_sync'   => $sub_branch_scoped,
    ];
}

/**
 * Current schema name for a mysqli connection.
 */
function auragold_mysql_current_database(mysqli $conn) {
    $r = mysqli_query($conn, "SELECT DATABASE() AS d");
    if ($r && $row = mysqli_fetch_assoc($r)) {
        return (string) ($row['d'] ?? '');
    }
    return '';
}

/**
 * One mysqli link per distinct db_name from tbl_branches (registry), when credentials work.
 *
 * @return array<int, array{db_name:string, link:mysqli}>
 */
function auragold_branch_mysql_connections_for_product_sync() {
    global $conn_master;
    if (!$conn_master) {
        return [];
    }
    $rows = getListMaster("SELECT * FROM tbl_branches");
    $seen = [];
    $out  = [];
    foreach ($rows as $row) {
        if (!auragold_tbl_branch_row_is_active($row)) {
            continue;
        }
        $cr = auragold_branch_row_db_credentials($row);
        if ($cr['db_name'] === '') {
            continue;
        }
        $k = strtolower($cr['db_name']);
        if (isset($seen[$k])) {
            continue;
        }
        $seen[$k] = true;
        $dbuser = $cr['db_user'] !== '' ? $cr['db_user'] : DB_USER;
        $dbpass = $cr['db_pass'];
        $link = @mysqli_connect(DB_HOST, $dbuser, $dbpass, $cr['db_name']);
        if (!$link) {
            continue;
        }
        mysqli_set_charset($link, 'utf8mb4');
        $out[] = ['db_name' => $cr['db_name'], 'link' => $link];
    }
    // Main / default app database (when not listed on a branch row with db_name)
    if (defined('DB_NAME')) {
        $dn = trim((string) DB_NAME);
        if ($dn !== '') {
            $k = strtolower($dn);
            if (!isset($seen[$k])) {
                $seen[$k] = true;
                $link = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, $dn);
                if ($link) {
                    mysqli_set_charset($link, 'utf8mb4');
                    $out[] = ['db_name' => $dn, 'link' => $link];
                }
            }
        }
    }
    return $out;
}
