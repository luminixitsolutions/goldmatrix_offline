<?php
/**
 * Shared implementation for old jewelry scrap saves.
 * Loaded only via save-old-jewelry-scrap-invoice.php or save-old-jewelry-scrap-stock-in.php.
 */
if (!defined('AURAGOLD_RUN_OLD_JEWELRY_SCRAP_SAVE_INTERNAL')) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
    exit;
}

// Prevent any HTML/notice output from breaking JSON response
ob_start();
@ini_set('display_errors', 0);
error_reporting(0);

session_start();
require_once '../config.php';
require_once __DIR__ . '/../includes/auragold_ensure_exchange_rate_column.php';

require_once __DIR__ . '/../includes/auragold_metal_exchange_stock.php';
require_once __DIR__ . '/../includes/auragold_extra_fields_item_values.php';
require_once __DIR__ . '/../includes/old_jewelry_scrap_stock_balance.php';
require_once __DIR__ . '/../includes/invoice_item_unique_barcode.php';
require_once __DIR__ . '/../includes/next_product_stock_barcode.php';

// Discard any accidental output from includes (only cleanup once - do not call ob_clean again)
@ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

// Use esc() from config.php - do not redeclare (would cause 500)

$t1 = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_scrap_invoices'");
$t2 = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_scrap_invoice_items'");
$t3 = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_scrap_invoice_payments'");
if (!$t1 || mysqli_num_rows($t1) === 0 || !$t2 || mysqli_num_rows($t2) === 0 || !$t3 || mysqli_num_rows($t3) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Scrap invoice tables do not exist. Please run admin/sql/create_old_jewelry_scrap_invoice_tables.sql first.']);
    exit;
}
$t_oj_stock = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_stock'");
$oj_stock_table_ok = ($t_oj_stock && mysqli_num_rows($t_oj_stock) > 0);
if ($t_oj_stock) {
    mysqli_free_result($t_oj_stock);
}
$oj_items_has_is_stocked = false;
$oj_items_has_metal_id = false;
$oj_items_has_less_wt = false;
$c1 = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_old_jewelry_scrap_invoice_items LIKE 'is_stocked'");
if ($c1 && mysqli_num_rows($c1) > 0) {
    $oj_items_has_is_stocked = true;
}
if ($c1) {
    mysqli_free_result($c1);
}
$c2 = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_old_jewelry_scrap_invoice_items LIKE 'metal_id'");
if ($c2 && mysqli_num_rows($c2) > 0) {
    $oj_items_has_metal_id = true;
}
if ($c2) {
    mysqli_free_result($c2);
}
$c3 = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_old_jewelry_scrap_invoice_items LIKE 'less_wt'");
if ($c3 && mysqli_num_rows($c3) > 0) {
    $oj_items_has_less_wt = true;
}
if ($c3) {
    mysqli_free_result($c3);
}

if (!mysqli_begin_transaction($conn)) {
    echo json_encode(['status' => 'error', 'message' => 'Database transaction failed: ' . mysqli_error($conn)]);
    exit;
}

try {
    $oj_scrap_save_do_stock_mirror = (defined('AURAGOLD_OJ_SCRAP_SAVE_SOURCE') && AURAGOLD_OJ_SCRAP_SAVE_SOURCE === 'stock_in');

    // Accept both purchase-invoice form keys (order_id, order_no, order_date) and direct keys
    $invoice_id = isset($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : (isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0);
    $oj_invoice_existed = ($invoice_id > 0);
    $invoice_no = esc($_POST['invoice_no'] ?? $_POST['order_no'] ?? '');
    $customer_name = esc($_POST['customer_name'] ?? '');
    $ledger_customer_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
    $against_of = esc($_POST['against_of'] ?? '');
    $currency = esc($_POST['currency'] ?? 'USD');
    $exchange_rate = function_exists('auragold_read_posted_exchange_rate') ? auragold_read_posted_exchange_rate() : (float)($_POST['exchange_rate'] ?? $_POST['currency_rate'] ?? 1);
    if ($exchange_rate <= 0) { $exchange_rate = 1.0; }
    $__fx = function_exists('auragold_exchange_rate_sql_fragments') ? auragold_exchange_rate_sql_fragments($conn, 'tbl_old_jewelry_scrap_invoices') : ['has'=>false,'insert_col'=>'','insert_val'=>'','update'=>'','rate'=>1];
    $fx_update_sql = (!empty($__fx['has']) && !empty($__fx['name'])) ? ($__fx['name'] . ' = ' . (float)$exchange_rate . ', ') : '';
    $fx_insert_col_sql = (!empty($__fx['has']) && !empty($__fx['name'])) ? (', ' . $__fx['name']) : '';
    $fx_insert_val_sql = (!empty($__fx['has']) && !empty($__fx['name'])) ? (', ' . (float)$exchange_rate) : '';

    $currency_rate = function_exists('auragold_read_posted_exchange_rate') ? auragold_read_posted_exchange_rate() : (float)($_POST['currency_rate'] ?? $_POST['exchange_rate'] ?? 1);
    $ref_no = esc($_POST['ref_no'] ?? '');
    $sales_person = esc($_POST['sales_person'] ?? '');
    $invoice_date = esc($_POST['invoice_date'] ?? $_POST['order_date'] ?? date('Y-m-d'));
    $due_date = !empty($_POST['due_date']) ? esc($_POST['due_date']) : null;
    $fixing_type = esc($_POST['fixing_type'] ?? 'Standard');
    $ounce_rate = (float)($_POST['ounce_rate'] ?? 0);
    $previous_balance_amt = (float)($_POST['previous_balance_amt'] ?? $_POST['previous_balance'] ?? 0);
    $previous_balance_gold = (float)($_POST['previous_balance_gold'] ?? $_POST['previous_gold'] ?? 0);
    $previous_balance_silver = (float)($_POST['previous_balance_silver'] ?? $_POST['previous_silver'] ?? 0);
    $additional_amt = (float)($_POST['additional_amt'] ?? 0);
    $net_total = (float)($_POST['net_total'] ?? 0);
    $discount_amt = (float)($_POST['discount_amt'] ?? 0);
    $grand_total = (float)($_POST['grand_total'] ?? 0);
    $advance_payment = (float)($_POST['advance_payment'] ?? 0);
    $metal_amt = (float)($_POST['metal_amt'] ?? 0);
    $round_off = (float)($_POST['round_off'] ?? 0);
    $round_off_apply = isset($_POST['round_off_apply']) ? (int)$_POST['round_off_apply'] : 0;
    $paid_amt = (float)($_POST['paid_amt'] ?? 0);
    $balance_amt = (float)($_POST['balance_amt'] ?? 0);
    $comment = esc($_POST['comment'] ?? '');

    $items = isset($_POST['items']) ? $_POST['items'] : [];
    if (is_string($items)) $items = json_decode($items, true);
    if (!is_array($items)) $items = [];

    $payments = isset($_POST['payments']) ? $_POST['payments'] : [];
    if (is_string($payments)) $payments = json_decode($payments, true);
    if (!is_array($payments)) $payments = [];
    $metal_exchange_barcodes_out = [];

    if (empty($customer_name)) {
        throw new Exception('Name is required.');
    }

    if (empty($invoice_no)) {
        if (function_exists('getNextOldJewelryScrapInvoiceNo')) {
            $invoice_no = getNextOldJewelryScrapInvoiceNo($conn);
        } else {
            $last = getRecord("SELECT invoice_no FROM tbl_old_jewelry_scrap_invoices ORDER BY id DESC LIMIT 1");
            $next_num = 1;
            if ($last && !empty($last['invoice_no'])) {
                $next_num = (int) preg_replace('/[^0-9]/', '', $last['invoice_no']) + 1;
            }
            $invoice_no = 'OJB-' . $next_num;
        }
    }

    $created_by = isset($_SESSION['Admin']['id']) ? (int)$_SESSION['Admin']['id'] : 'NULL';

    if ($invoice_id > 0) {
        $exists = getRecord("SELECT id FROM tbl_old_jewelry_scrap_invoices WHERE id = $invoice_id");
        if (!$exists) throw new Exception('Invoice not found.');
        $dup = getRecord("SELECT id FROM tbl_old_jewelry_scrap_invoices WHERE invoice_no = '$invoice_no' AND id != $invoice_id" . auragold_doc_series_active_sql($conn, 'tbl_old_jewelry_scrap_invoices'));
        if ($dup) throw new Exception('Invoice number already exists.');

        $due_sql = $due_date ? "'$due_date'" : 'NULL';
        $upd = "UPDATE tbl_old_jewelry_scrap_invoices SET
            invoice_no = '$invoice_no',
            customer_name = '$customer_name',
            against_of = " . ($against_of ? "'$against_of'" : 'NULL') . ",
            currency = '$currency', $fx_update_sql
            currency_rate = $currency_rate,
            ref_no = " . ($ref_no ? "'$ref_no'" : 'NULL') . ",
            sales_person = " . ($sales_person ? "'$sales_person'" : 'NULL') . ",
            invoice_date = '$invoice_date',
            due_date = $due_sql,
            fixing_type = '$fixing_type',
            ounce_rate = $ounce_rate,
            previous_balance_amt = $previous_balance_amt,
            previous_balance_gold = $previous_balance_gold,
            previous_balance_silver = $previous_balance_silver,
            additional_amt = $additional_amt,
            net_total = $net_total,
            discount_amt = $discount_amt,
            grand_total = $grand_total,
            advance_payment = $advance_payment,
            metal_amt = $metal_amt,
            round_off = $round_off,
            round_off_apply = $round_off_apply,
            paid_amt = $paid_amt,
            balance_amt = $balance_amt,
            comment = " . ($comment ? "'$comment'" : 'NULL') . ",
            updated_at = NOW()
            WHERE id = $invoice_id";
        if (!mysqli_query($conn, $upd)) throw new Exception('Update failed: ' . mysqli_error($conn));
        mysqli_query($conn, "DELETE FROM tbl_old_jewelry_scrap_invoice_payments WHERE invoice_id = $invoice_id");
        if (!$oj_scrap_save_do_stock_mirror) {
            mysqli_query($conn, "DELETE FROM tbl_old_jewelry_scrap_invoice_items WHERE invoice_id = $invoice_id");
        }
    } else {
        $dup = getRecord("SELECT id FROM tbl_old_jewelry_scrap_invoices WHERE invoice_no = '$invoice_no'" . auragold_doc_series_active_sql($conn, 'tbl_old_jewelry_scrap_invoices'));
        if ($dup) throw new Exception('Invoice number already exists.');
        if (function_exists('auragold_release_soft_deleted_document_no')) {
            auragold_release_soft_deleted_document_no($conn, [['tbl_old_jewelry_scrap_invoices', 'invoice_no']], $invoice_no);
        }
        $due_sql = $due_date ? "'$due_date'" : 'NULL';
        $subtotal = (float)($_POST['subtotal'] ?? 0);
        $ins = "INSERT INTO tbl_old_jewelry_scrap_invoices (
            invoice_no, customer_name, against_of, currency, currency_rate, ref_no, sales_person,
            invoice_date, due_date, fixing_type, ounce_rate,
            previous_balance_amt, previous_balance_gold, previous_balance_silver,
            subtotal, additional_amt, net_total, discount_amt, grand_total,
            advance_payment, metal_amt, round_off, round_off_apply, paid_amt, balance_amt,
            comment, status, created_by
        ) VALUES (
            '$invoice_no','$customer_name'," . ($against_of ? "'$against_of'" : 'NULL') . ",'$currency',$currency_rate,
            " . ($ref_no ? "'$ref_no'" : 'NULL') . "," . ($sales_person ? "'$sales_person'" : 'NULL') . ",
            '$invoice_date',$due_sql,'$fixing_type',$ounce_rate,
            $previous_balance_amt,$previous_balance_gold,$previous_balance_silver,
            $subtotal,$additional_amt,$net_total,$discount_amt,$grand_total,
            $advance_payment,$metal_amt,$round_off,$round_off_apply,$paid_amt,$balance_amt,
            " . ($comment ? "'$comment'" : 'NULL') . ", 'draft', $created_by
        )";
        if (!mysqli_query($conn, $ins)) throw new Exception('Insert failed: ' . mysqli_error($conn));
        $invoice_id = (int)mysqli_insert_id($conn);
    }

    $branch_stock = 0;
    if (!empty($_SESSION['working_branch_id'])) {
        $branch_stock = (int)$_SESSION['working_branch_id'];
    } elseif (!empty($_SESSION['branch_id'])) {
        $branch_stock = (int)$_SESSION['branch_id'];
    }
    $group_name_post = esc($_POST['group_name'] ?? '');
    $against_voucher_esc = esc(trim($ref_no !== '' ? $ref_no : 'Scrap Stock In'));

    if ($oj_scrap_save_do_stock_mirror) {
        require_once __DIR__ . '/../includes/old_jewelry_scrap_stock_mirror_inventory.php';
    }

    // Map item keys from purchase-invoice form (gross_weight, product_name, etc.) or scrap form (gross_wt, description)
    $oj_invoice_used_barcodes = [];
    $saved_barcodes = [];
    foreach ($items as $it) {
        $resolved_barcode = auragold_resolve_unique_invoice_item_barcode($conn, $it, $oj_invoice_used_barcodes, (int) ($it['id'] ?? 0));
        if (is_string($resolved_barcode)) {
            $resolved_barcode = trim($resolved_barcode);
        } else {
            $resolved_barcode = '';
        }
        $pid_it = (int)($it['product_id'] ?? 0);
        $pcid_it = (int)($it['characteristic_id'] ?? $it['product_characteristic_id'] ?? 0);
        $mid_it = (int)($it['metal_id'] ?? 0);
        if ($resolved_barcode === '' && $oj_stock_table_ok && $oj_scrap_save_do_stock_mirror) {
            $bid_bc = $branch_stock > 0 ? $branch_stock : 1;
            $nb = auragold_next_product_stock_barcode($conn, $pid_it, $pcid_it, $mid_it, $bid_bc);
            if (!empty($nb['barcode'])) {
                $resolved_barcode = trim((string)$nb['barcode']);
            }
        }
        if ($resolved_barcode !== '') {
            $saved_barcodes[] = $resolved_barcode;
        }
        $barcode = $resolved_barcode !== '' ? esc($resolved_barcode) : '';
        $desc_raw = trim((string) ($it['description'] ?? $it['product_name'] ?? ''));
        $description = $desc_raw !== '' ? esc($desc_raw) : '';
        $gross_wt = (float)($it['gross_wt'] ?? $it['gross_weight'] ?? 0);
        $final_wt = (float)($it['final_wt'] ?? $it['final_weight'] ?? 0);
        $net_wt = (float)($it['net_wt'] ?? $it['net_weight'] ?? 0);
        $pure_wt = (float)($it['pure_wt'] ?? $it['pure_weight'] ?? 0);
        $making = (float)($it['making'] ?? $it['making_amount'] ?? 0);
        $tax = (float)($it['tax'] ?? 0);
        $amount = (float)($it['amount'] ?? 0);
        $net_amt = (float)($it['net_amt'] ?? $it['net_amount'] ?? 0);
        $quantity = (float)($it['quantity'] ?? 1);
        $diamond_wt = (float)($it['diamond_wt'] ?? $it['diamond_value'] ?? 0);
        $gemstone_wt = (float)($it['gemstone_wt'] ?? $it['gemstone_value'] ?? 0);
        $purity = (float)($it['purity'] ?? 0);
        $rate = (float)($it['rate'] ?? 0);
        $metal_rate_in = (float)($it['metal_rate'] ?? 0);
        if ($rate <= 0 && $metal_rate_in > 0) {
            $rate = $metal_rate_in;
        }
        $net_amt_wt = (float)($it['net_amt_wt'] ?? $it['net_amt_with_tax'] ?? 0);
        $less_wt_it = (float)($it['less_weight'] ?? $it['less_wt'] ?? 0);

        $exist_line_id = (int) ($it['id'] ?? 0);
        $stock_in_existing_line = ($invoice_id > 0 && $oj_scrap_save_do_stock_mirror && $exist_line_id > 0);
        if ($stock_in_existing_line) {
            $dbrow = getRecord("SELECT * FROM tbl_old_jewelry_scrap_invoice_items WHERE id = $exist_line_id AND invoice_id = $invoice_id LIMIT 1");
            if (!$dbrow) {
                throw new Exception('Scrap line not found for stock-in.');
            }
            $orig_gross_db = (float) ($dbrow['gross_wt'] ?? 0);
            $already_st = auragold_oj_scrap_stocked_gross_sum_for_line_including_single_line_orphans($conn, $invoice_id, $exist_line_id);
            $rem_g = max(0, $orig_gross_db - $already_st);
            $add_g = (float) ($it['gross_wt'] ?? $it['gross_weight'] ?? 0);
            if (round($add_g, 4) > round($rem_g, 4)) {
                throw new Exception('Gross weight to stock (' . round($add_g, 4) . ' g) cannot exceed remaining balance (' . round($rem_g, 4) . ' g) for this line.');
            }
            $upd_line = "UPDATE tbl_old_jewelry_scrap_invoice_items SET description = " . ($description ? "'$description'" : 'NULL');
            if ($oj_items_has_metal_id) {
                $upd_line .= ', metal_id = ' . ($mid_it > 0 ? $mid_it : 'NULL');
            }
            if ($resolved_barcode !== '') {
                $upd_line .= ", barcode = '" . mysqli_real_escape_string($conn, $resolved_barcode) . "'";
            }
            $upd_line .= " WHERE id = $exist_line_id AND invoice_id = $invoice_id";
            if (!mysqli_query($conn, $upd_line)) {
                throw new Exception('Item update failed: ' . mysqli_error($conn));
            }
            $new_item_id = $exist_line_id;
            $stock_row_ok = false;
            $gross_stock_move = $add_g;
            $gross_scale = $orig_gross_db > 0.00001 ? ($gross_stock_move / $orig_gross_db) : 0;
            $final_wt_stock = (float) ($dbrow['final_wt'] ?? 0) * $gross_scale;
            $net_wt_stock = (float) ($dbrow['net_wt'] ?? 0) * $gross_scale;
            $less_wt_stock = $oj_items_has_less_wt ? ((float) ($dbrow['less_wt'] ?? 0) * $gross_scale) : 0;
            $pure_wt_stock = (float) ($dbrow['pure_wt'] ?? 0) * $gross_scale;
            $net_amt_db = (float) ($dbrow['net_amt'] ?? 0);
            $amt_db = (float) ($dbrow['amount'] ?? 0);
            $amt_stock_line = ($net_amt_db > 0 ? $net_amt_db : $amt_db) * $gross_scale;
            if ($oj_scrap_save_do_stock_mirror && $oj_stock_table_ok && $gross_stock_move > 0.00001 && $resolved_barcode !== '') {
                $metal_disp = '';
                if ($mid_it > 0) {
                    $mr = getRecord("SELECT display_name FROM tbl_metal WHERE id = $mid_it LIMIT 1");
                    if ($mr && !empty($mr['display_name'])) {
                        $metal_disp = esc($mr['display_name']);
                    }
                }
                $loc_raw = trim((string)($it['location'] ?? ''));
                $location_esc = '';
                if ($loc_raw !== '') {
                    if (ctype_digit($loc_raw)) {
                        $lr = getRecord('SELECT name FROM tbl_location WHERE id = ' . (int)$loc_raw . ' LIMIT 1');
                        $location_esc = ($lr && !empty($lr['name'])) ? esc($lr['name']) : esc($loc_raw);
                    } else {
                        $location_esc = esc($loc_raw);
                    }
                }
                $category_esc = esc(trim((string)($it['category'] ?? $it['diamond_category'] ?? '')));
                $amt_stock = $amt_stock_line;
                $bc_sql = "'" . mysqli_real_escape_string($conn, $resolved_barcode) . "'";
                $inv_no_sql = "'" . mysqli_real_escape_string($conn, $invoice_no) . "'";
                $against_inv_sql = $against_of ? "'$against_of'" : 'NULL';
                $sql_stock = "
                    INSERT INTO tbl_old_jewelry_stock (
                        source_invoice_id, source_item_id, barcode, invoice_no, voucher_type, metal, product, location,
                        final_wt, gross_wt, purity, branch_id, less_wt, net_wt, amount, category, against_invoice_no, against_voucher,
                        group_name, comment, quantity, rate
                    ) VALUES (
                        $invoice_id, $new_item_id, $bc_sql, $inv_no_sql, 'Old Jewelry - Scrap', '$metal_disp', " . ($description ? "'$description'" : 'NULL') . ", " . ($location_esc !== '' ? "'$location_esc'" : 'NULL') . ",
                        $final_wt_stock, $gross_stock_move, $purity, " . ($branch_stock > 0 ? $branch_stock : 'NULL') . ", $less_wt_stock, $net_wt_stock, $amt_stock, " . ($category_esc !== '' ? "'$category_esc'" : 'NULL') . ", $against_inv_sql, '$against_voucher_esc',
                        " . ($group_name_post !== '' ? "'$group_name_post'" : "''") . ", " . ($comment ? "'$comment'" : 'NULL') . ", $quantity, $rate
                    )
                ";
                if (!mysqli_query($conn, $sql_stock)) {
                    throw new Exception('Stock row insert failed: ' . mysqli_error($conn));
                }
                $stock_row_ok = true;
                [$rpid, $rpcid, $rmid] = auragold_oj_scrap_resolve_line_product_ids($conn, $it, $mid_it, $branch_stock);
                $item_fb = ['description' => $desc_raw];
                $amt_line = $amt_stock_line;
                auragold_oj_scrap_mirror_tbl_stock_line(
                    $conn,
                    $rpid,
                    $rpcid,
                    $rmid,
                    $branch_stock,
                    $desc_raw,
                    $item_fb,
                    $resolved_barcode,
                    $gross_stock_move,
                    $net_wt_stock,
                    $final_wt_stock,
                    $purity,
                    $quantity,
                    $rate,
                    $amt_line
                );
                $loc_plain = trim((string) ($it['location'] ?? ''));
                if ($loc_plain !== '' && ctype_digit($loc_plain)) {
                    $lrr = getRecord('SELECT name FROM tbl_location WHERE id = ' . (int) $loc_plain . ' LIMIT 1');
                    if ($lrr && !empty($lrr['name'])) {
                        $loc_plain = (string) $lrr['name'];
                    }
                }
                $cat_plain = trim((string) ($it['category'] ?? $it['diamond_category'] ?? ''));
                $grp_plain = isset($_POST['group_name']) ? trim((string) $_POST['group_name']) : '';
                $cmt_plain = isset($_POST['comment']) ? trim((string) $_POST['comment']) : '';
                $sj_ref_id = (string) mysqli_insert_id($conn);
                auragold_oj_scrap_insert_stock_history_journal_line(
                    $conn,
                    $invoice_id,
                    $new_item_id,
                    $sj_ref_id,
                    $invoice_no,
                    $invoice_date,
                    $resolved_barcode,
                    $rpid,
                    $rpcid,
                    $rmid,
                    $desc_raw,
                    $gross_stock_move,
                    $less_wt_stock,
                    $net_wt_stock,
                    $final_wt_stock,
                    $pure_wt_stock,
                    $purity,
                    $quantity,
                    $rate,
                    $amt_line,
                    'Old Jewelry Scrap Stock In',
                    $grp_plain,
                    $cmt_plain,
                    $cat_plain,
                    $loc_plain
                );
            }
            if ($oj_items_has_is_stocked) {
                auragold_oj_scrap_sync_is_stocked_for_item($conn, $exist_line_id);
            }
            continue;
        }

        $ins_cols = 'invoice_id, barcode, description, gross_wt, final_wt, net_wt, pure_wt, making, tax, amount, net_amt, quantity, net_amt_wt, diamond_wt, gemstone_wt, purity, rate';
        $ins_vals = "$invoice_id," . ($barcode ? "'$barcode'" : 'NULL') . "," . ($description ? "'$description'" : 'NULL') . ",
            $gross_wt,$final_wt,$net_wt,$pure_wt,$making,$tax,$amount,$net_amt,$quantity,$net_amt_wt,$diamond_wt,$gemstone_wt,$purity,$rate";
        if ($oj_items_has_metal_id) {
            $ins_cols .= ', metal_id';
            $ins_vals .= ", " . ($mid_it > 0 ? $mid_it : 'NULL');
        }
        if ($oj_items_has_less_wt) {
            $ins_cols .= ', less_wt';
            $ins_vals .= ", $less_wt_it";
        }
        $ef_parts = auragold_extra_fields_item_insert_sql_parts($conn, 'tbl_old_jewelry_scrap_invoice_items', $it);
        $ins_cols .= $ef_parts['columns'];
        $ins_vals .= $ef_parts['values'];
        $qi = "INSERT INTO tbl_old_jewelry_scrap_invoice_items ($ins_cols) VALUES ($ins_vals)";
        if (!mysqli_query($conn, $qi)) {
            throw new Exception('Item insert failed: ' . mysqli_error($conn));
        }
        $new_item_id = (int)mysqli_insert_id($conn);

        $stock_row_ok = false;
        if ($oj_scrap_save_do_stock_mirror && $oj_stock_table_ok && $new_item_id > 0 && $barcode !== '') {
            $metal_disp = '';
            if ($mid_it > 0) {
                $mr = getRecord("SELECT display_name FROM tbl_metal WHERE id = $mid_it LIMIT 1");
                if ($mr && !empty($mr['display_name'])) {
                    $metal_disp = esc($mr['display_name']);
                }
            }
            $loc_raw = trim((string)($it['location'] ?? ''));
            $location_esc = '';
            if ($loc_raw !== '') {
                if (ctype_digit($loc_raw)) {
                    $lr = getRecord('SELECT name FROM tbl_location WHERE id = ' . (int)$loc_raw . ' LIMIT 1');
                    $location_esc = ($lr && !empty($lr['name'])) ? esc($lr['name']) : esc($loc_raw);
                } else {
                    $location_esc = esc($loc_raw);
                }
            }
            $category_esc = esc(trim((string)($it['category'] ?? $it['diamond_category'] ?? '')));
            $amt_stock = $net_amt > 0 ? $net_amt : $amount;
            $bc_sql = "'" . mysqli_real_escape_string($conn, $resolved_barcode) . "'";
            $inv_no_sql = "'" . mysqli_real_escape_string($conn, $invoice_no) . "'";
            $against_inv_sql = $against_of ? "'$against_of'" : 'NULL';
            $sql_stock = "
                INSERT INTO tbl_old_jewelry_stock (
                    source_invoice_id, source_item_id, barcode, invoice_no, voucher_type, metal, product, location,
                    final_wt, gross_wt, purity, branch_id, less_wt, net_wt, amount, category, against_invoice_no, against_voucher,
                    group_name, comment, quantity, rate
                ) VALUES (
                    $invoice_id, $new_item_id, $bc_sql, $inv_no_sql, 'Old Jewelry - Scrap', '$metal_disp', " . ($description ? "'$description'" : 'NULL') . ", " . ($location_esc !== '' ? "'$location_esc'" : 'NULL') . ",
                    $final_wt, $gross_wt, $purity, " . ($branch_stock > 0 ? $branch_stock : 'NULL') . ", $less_wt_it, $net_wt, $amt_stock, " . ($category_esc !== '' ? "'$category_esc'" : 'NULL') . ", $against_inv_sql, '$against_voucher_esc',
                    " . ($group_name_post !== '' ? "'$group_name_post'" : "''") . ", " . ($comment ? "'$comment'" : 'NULL') . ", $quantity, $rate
                )
            ";
            if (!mysqli_query($conn, $sql_stock)) {
                throw new Exception('Stock row insert failed: ' . mysqli_error($conn));
            }
            $stock_row_ok = true;

            [$rpid, $rpcid, $rmid] = auragold_oj_scrap_resolve_line_product_ids($conn, $it, $mid_it, $branch_stock);
            $item_fb = ['description' => $desc_raw];
            $amt_line = $net_amt > 0 ? $net_amt : $amount;
            auragold_oj_scrap_mirror_tbl_stock_line(
                $conn,
                $rpid,
                $rpcid,
                $rmid,
                $branch_stock,
                $desc_raw,
                $item_fb,
                $resolved_barcode,
                $gross_wt,
                $net_wt,
                $final_wt,
                $purity,
                $quantity,
                $rate,
                $amt_line
            );
            $loc_plain = trim((string) ($it['location'] ?? ''));
            if ($loc_plain !== '' && ctype_digit($loc_plain)) {
                $lrr = getRecord('SELECT name FROM tbl_location WHERE id = ' . (int) $loc_plain . ' LIMIT 1');
                if ($lrr && !empty($lrr['name'])) {
                    $loc_plain = (string) $lrr['name'];
                }
            }
            $cat_plain = trim((string) ($it['category'] ?? $it['diamond_category'] ?? ''));
            $grp_plain = isset($_POST['group_name']) ? trim((string) $_POST['group_name']) : '';
            $cmt_plain = isset($_POST['comment']) ? trim((string) $_POST['comment']) : '';
            auragold_oj_scrap_insert_stock_history_journal_line(
                $conn,
                $invoice_id,
                $new_item_id,
                (string) $new_item_id,
                $invoice_no,
                $invoice_date,
                $resolved_barcode,
                $rpid,
                $rpcid,
                $rmid,
                $desc_raw,
                $gross_wt,
                $less_wt_it,
                $net_wt,
                $final_wt,
                $pure_wt,
                $purity,
                $quantity,
                $rate,
                $amt_line,
                'Old Jewelry Scrap Stock In',
                $grp_plain,
                $cmt_plain,
                $cat_plain,
                $loc_plain
            );
        }

        if ($oj_scrap_save_do_stock_mirror && $oj_items_has_is_stocked && $new_item_id > 0 && $stock_row_ok) {
            auragold_oj_scrap_sync_is_stocked_for_item($conn, $new_item_id);
        }
    }

    $oj_pay_has_details = false;
    $_ojpdc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_old_jewelry_scrap_invoice_payments LIKE 'payment_details'");
    if ($_ojpdc && mysqli_num_rows($_ojpdc) > 0) {
        $oj_pay_has_details = true;
    } else {
        @mysqli_query($conn, "ALTER TABLE tbl_old_jewelry_scrap_invoice_payments ADD COLUMN payment_details TEXT NULL COMMENT 'JSON: scrap modal fields, metal, weights, etc.'");
        $_ojpdc2 = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_old_jewelry_scrap_invoice_payments LIKE 'payment_details'");
        $oj_pay_has_details = ($_ojpdc2 && mysqli_num_rows($_ojpdc2) > 0);
    }
    if ($_ojpdc) {
        mysqli_free_result($_ojpdc);
    }
    if (isset($_ojpdc2) && $_ojpdc2) {
        mysqli_free_result($_ojpdc2);
    }

    if ($oj_scrap_save_do_stock_mirror) {
        $__oj_me_rt = 'old_jewellery_scrap_stock_in_metal_exchange';
        $__oj_me_hist = 'Old Jewellery Scrap Stock In — Metal Exchange';
        $__oj_me_aud = 'ojstk_me';
        $__oj_me_pfx = 'OJSTK-ME-';
    } else {
        $__oj_me_rt = 'old_jewelry_scrap_invoice_metal_exchange';
        $__oj_me_hist = 'Old Jewelry Scrap Invoice — Metal Exchange';
        $__oj_me_aud = 'ojsi_me';
        $__oj_me_pfx = 'OJSI-ME-';
    }
    foreach ($payments as $__pme_ok) {
        if (!is_array($__pme_ok)) {
            continue;
        }
        $___mrg_oj = auragold_payment_merge_stored_details($__pme_ok);
        if (!auragold_payment_is_metal_exchange_inward($conn, $___mrg_oj)) {
            continue;
        }
        auragold_validate_metal_exchange_for_stock($conn, $___mrg_oj);
    }
    $__oj_me_has_ref = auragold_metal_exchange_document_init($conn, $oj_invoice_existed, (int) $invoice_id, $__oj_me_rt);

    foreach ($payments as $pay_seq => $pm) {
        if (!is_array($pm)) {
            continue;
        }
        $__pt_chk = strtolower(trim((string) ($pm['payment_type'] ?? '')));
        $__amt_chk = (float) ($pm['current_order_amount'] ?? $pm['amount'] ?? 0);
        if ($__amt_chk <= 0 && isset($pm['amount'])) {
            $__amt_chk = (float) $pm['amount'];
        }
        if (!auragold_should_persist_payment_row_with_metal_exchange($conn, $pm)) {
            if ($__pt_chk === '' && $__amt_chk <= 0) {
                continue;
            }
        }

        $pt = esc($pm['payment_type'] ?? '');
        $deposit = esc($pm['deposit_into'] ?? '');
        $trans_no = esc($pm['transaction_no'] ?? '');
        $cheque_dt = !empty($pm['cheque_date']) ? "'" . esc($pm['cheque_date']) . "'" : 'NULL';
        $purity_carat = esc($pm['purity_carat'] ?? '');
        $amt = $__amt_chk;
        $diamond_cat = esc($pm['diamond_category'] ?? '');
        $qty = (float) ($pm['quantity'] ?? 0);
        $card_no = esc($pm['card_no'] ?? '');
        if ($pt === '') {
            $pt = 'Cash';
        }
        $pm_for_json = $pm;
        unset($pm_for_json['id']);
        if (isset($pm_for_json['payment_details'])) {
            unset($pm_for_json['payment_details']);
        }
        $payment_details_esc = mysqli_real_escape_string($conn, json_encode($pm_for_json, JSON_UNESCAPED_UNICODE));
        $pd_col = $oj_pay_has_details ? ', payment_details' : '';
        $pd_val = $oj_pay_has_details ? ", '$payment_details_esc'" : '';
        $qp = "INSERT INTO tbl_old_jewelry_scrap_invoice_payments (invoice_id, payment_type, deposit_into, transaction_no, cheque_date, purity_carat, amount, diamond_category, quantity, card_no$pd_col) VALUES (
            $invoice_id,'$pt'," . ($deposit ? "'$deposit'" : 'NULL') . "," . ($trans_no ? "'$trans_no'" : 'NULL') . ",$cheque_dt," . ($purity_carat ? "'$purity_carat'" : 'NULL') . ",$amt," . ($diamond_cat ? "'$diamond_cat'" : 'NULL') . ",$qty," . ($card_no ? "'$card_no'" : 'NULL') . "$pd_val
        )";
        if (!mysqli_query($conn, $qp)) {
            throw new Exception('Payment insert failed: ' . mysqli_error($conn));
        }

        $oj_pm_m = auragold_payment_merge_stored_details($pm);
        auragold_post_metal_exchange_payment_to_stock(
            $conn,
            $__oj_me_rt,
            (int) $invoice_id,
            trim((string) $invoice_no),
            substr(trim((string) $invoice_date), 0, 10),
            $oj_pm_m,
            auragold_metal_exchange_default_branch_id(),
            is_int($pay_seq) ? $pay_seq : (int) $pay_seq,
            $__oj_me_has_ref,
            $__oj_me_hist,
            $__oj_me_aud,
            $__oj_me_pfx,
            $metal_exchange_barcodes_out
        );
    }

    // Customer ledger: standalone OJB only. If ref_no = PI:{id} (auto from purchase invoice), scrap is already posted as Payment Debit
    // on the party in save-purchase-invoice.php — do not post again here (avoids wrong Credit / double entries on customer ledger).
    $post_amt = (float)$grand_total;
    if ($post_amt <= 0) {
        foreach ($items as $__it) {
            $post_amt += (float)($__it['amount'] ?? $__it['net_amt'] ?? $__it['net_amount'] ?? 0);
        }
    }
    $oj_ledger_ref = getRecord("SELECT ref_no FROM tbl_old_jewelry_scrap_invoices WHERE id = $invoice_id LIMIT 1");
    $oj_ref_trim = trim((string) ($oj_ledger_ref['ref_no'] ?? ''));
    $ojb_linked_to_pi = (bool) preg_match('/^PI:\d+$/i', $oj_ref_trim);
    if ($ojb_linked_to_pi) {
        mysqli_query($conn, "
            DELETE FROM tbl_customer_ledger
            WHERE status = 1 AND transaction_id = $invoice_id
            AND transaction_type IN ('old_jewelry_scrap_invoice', 'old_jewelry_scrap_contra', 'Old Jewelry - Scrap Invoice')
        ");
        mysqli_query($conn, "
            DELETE FROM tbl_customer_ledger
            WHERE status = 1 AND customer_name = '$customer_name' AND transaction_no = '$invoice_no'
            AND transaction_type IN ('old_jewelry_scrap_invoice', 'Old Jewelry - Scrap Invoice')
        ");
    } elseif ($post_amt > 0) {
        $lcid = $ledger_customer_id;
        if ($lcid <= 0) {
            $cid_row = getRecord("SELECT id FROM tbl_customers WHERE name = '$customer_name' AND status = 1 LIMIT 1");
            if ($cid_row && !empty($cid_row['id'])) {
                $lcid = (int)$cid_row['id'];
            }
        }
        mysqli_query($conn, "
            DELETE FROM tbl_customer_ledger
            WHERE status = 1 AND transaction_id = $invoice_id
            AND transaction_type IN ('old_jewelry_scrap_invoice', 'old_jewelry_scrap_contra', 'Old Jewelry - Scrap Invoice')
        ");
        mysqli_query($conn, "
            DELETE FROM tbl_customer_ledger
            WHERE status = 1 AND customer_name = '$customer_name' AND transaction_no = '$invoice_no'
            AND transaction_type IN ('old_jewelry_scrap_invoice', 'Old Jewelry - Scrap Invoice')
        ");

        $last_bal = null;
        if ($lcid > 0) {
            $last_bal = getRecord("
                SELECT balance_amount, balance_gold, balance_silver
                FROM tbl_customer_ledger
                WHERE customer_id = $lcid AND status = 1
                ORDER BY transaction_date DESC, id DESC
                LIMIT 1
            ");
        }
        if (!$last_bal) {
            $last_bal = getRecord("
                SELECT balance_amount, balance_gold, balance_silver
                FROM tbl_customer_ledger
                WHERE customer_name = '$customer_name' AND status = 1
                ORDER BY transaction_date DESC, id DESC
                LIMIT 1
            ");
        }
        if (!$last_bal) {
            $last_bal = getRecord("SELECT balance_amount, balance_gold, balance_silver FROM tbl_customer_balance WHERE customer_name = '$customer_name' LIMIT 1");
        }
        $prev_amt = (float)($last_bal['balance_amount'] ?? 0);
        $prev_gold = (float)($last_bal['balance_gold'] ?? 0);
        $prev_silver = (float)($last_bal['balance_silver'] ?? 0);
        $new_party_bal = $prev_amt + $post_amt;

        $has_against = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customer_ledger LIKE 'against_ledger'");
        $ledger_has_against = ($has_against && mysqli_num_rows($has_against) > 0);
        if ($has_against) {
            mysqli_free_result($has_against);
        }
        $against_inv = $against_of !== '' ? $against_of : $invoice_no;
        $against_ledger_party = 'Purchase Account(' . number_format($post_amt, 2) . 'Dr)';
        $desc_party = 'Old Jewelry Scrap Invoice: ' . $invoice_no;
        $ref_sql_ledger = $ref_no !== '' ? "'$ref_no'" : 'NULL';
        $user_id_ins = (is_int($created_by) && $created_by > 0) ? (string)(int)$created_by : 'NULL';

        if ($ledger_has_against) {
            $sql_party = "
                INSERT INTO tbl_customer_ledger (
                    customer_id, customer_name, transaction_type, transaction_id, transaction_no,
                    transaction_date, debit_amount, credit_amount,
                    balance_amount, balance_gold, balance_silver,
                    description, reference_no, against_ledger, against_invoice_no,
                    status, created_by, created_at
                ) VALUES (
                    " . ($lcid > 0 ? $lcid : 0) . ",
                    '$customer_name',
                    'old_jewelry_scrap_invoice',
                    $invoice_id,
                    '$invoice_no',
                    '$invoice_date',
                    $post_amt,
                    0.00,
                    $new_party_bal,
                    $prev_gold,
                    $prev_silver,
                    '" . mysqli_real_escape_string($conn, $desc_party) . "',
                    $ref_sql_ledger,
                    '" . mysqli_real_escape_string($conn, $against_ledger_party) . "',
                    '" . mysqli_real_escape_string($conn, $against_inv) . "',
                    1,
                    " . $user_id_ins . ",
                    NOW()
                )
            ";
        } else {
            $sql_party = "
                INSERT INTO tbl_customer_ledger (
                    customer_id, customer_name, transaction_type, transaction_id, transaction_no,
                    transaction_date, debit_amount, credit_amount,
                    balance_amount, balance_gold, balance_silver,
                    description, reference_no, status, created_by, created_at
                ) VALUES (
                    " . ($lcid > 0 ? $lcid : 0) . ",
                    '$customer_name',
                    'old_jewelry_scrap_invoice',
                    $invoice_id,
                    '$invoice_no',
                    '$invoice_date',
                    $post_amt,
                    0.00,
                    $new_party_bal,
                    $prev_gold,
                    $prev_silver,
                    '" . mysqli_real_escape_string($conn, $desc_party) . "',
                    $ref_sql_ledger,
                    1,
                    " . $user_id_ins . ",
                    NOW()
                )
            ";
        }
        if (!mysqli_query($conn, $sql_party)) {
            throw new Exception('Customer ledger (scrap invoice) failed: ' . mysqli_error($conn));
        }

        $pa_prev = getRecord("
            SELECT balance_amount FROM tbl_customer_ledger
            WHERE customer_name = 'Purchase Account' AND status = 1
            ORDER BY transaction_date DESC, id DESC LIMIT 1
        ");
        $pa_bal = (float)($pa_prev['balance_amount'] ?? 0) - $post_amt;
        $pa_desc = 'Old Jewelry Scrap: ' . $invoice_no . ' — ' . $customer_name;
        $pa_against = mysqli_real_escape_string($conn, $customer_name . '(' . number_format($post_amt, 2) . 'Dr)');
        if ($ledger_has_against) {
            $sql_pa = "
                INSERT INTO tbl_customer_ledger (
                    customer_id, customer_name, transaction_type, transaction_id, transaction_no,
                    transaction_date, debit_amount, credit_amount,
                    balance_amount, balance_gold, balance_silver,
                    description, reference_no, against_ledger, against_invoice_no,
                    status, created_by, created_at
                ) VALUES (
                    0,
                    'Purchase Account',
                    'old_jewelry_scrap_contra',
                    $invoice_id,
                    '$invoice_no',
                    '$invoice_date',
                    0.00,
                    $post_amt,
                    $pa_bal,
                    0,
                    0,
                    '" . mysqli_real_escape_string($conn, $pa_desc) . "',
                    $ref_sql_ledger,
                    '$pa_against',
                    '$invoice_no',
                    1,
                    " . $user_id_ins . ",
                    NOW()
                )
            ";
        } else {
            $sql_pa = "
                INSERT INTO tbl_customer_ledger (
                    customer_id, customer_name, transaction_type, transaction_id, transaction_no,
                    transaction_date, debit_amount, credit_amount,
                    balance_amount, balance_gold, balance_silver,
                    description, reference_no, status, created_by, created_at
                ) VALUES (
                    0,
                    'Purchase Account',
                    'old_jewelry_scrap_contra',
                    $invoice_id,
                    '$invoice_no',
                    '$invoice_date',
                    0.00,
                    $post_amt,
                    $pa_bal,
                    0,
                    0,
                    '" . mysqli_real_escape_string($conn, $pa_desc) . "',
                    $ref_sql_ledger,
                    1,
                    " . $user_id_ins . ",
                    NOW()
                )
            ";
        }
        if (!mysqli_query($conn, $sql_pa)) {
            throw new Exception('Purchase Account ledger (scrap invoice) failed: ' . mysqli_error($conn));
        }

        $final_bal = getRecord("
            SELECT balance_amount, balance_gold, balance_silver
            FROM tbl_customer_ledger
            WHERE " . ($lcid > 0 ? "customer_id = $lcid" : "customer_name = '$customer_name'") . " AND status = 1
            ORDER BY transaction_date DESC, id DESC LIMIT 1
        ");
        if ($final_bal) {
            $fba = (float)($final_bal['balance_amount'] ?? 0);
            $fbg = (float)($final_bal['balance_gold'] ?? 0);
            $fbs = (float)($final_bal['balance_silver'] ?? 0);
            $bal_exist = null;
            if ($lcid > 0) {
                $bal_exist = getRecord("SELECT id FROM tbl_customer_balance WHERE customer_id = $lcid LIMIT 1");
            }
            if (!$bal_exist) {
                $bal_exist = getRecord("SELECT id FROM tbl_customer_balance WHERE customer_name = '$customer_name' LIMIT 1");
            }
            if ($bal_exist && !empty($bal_exist['id'])) {
                $upd_b = $lcid > 0
                    ? "UPDATE tbl_customer_balance SET balance_amount = $fba, balance_gold = $fbg, balance_silver = $fbs, last_transaction_date = '$invoice_date', last_updated = NOW() WHERE customer_id = $lcid"
                    : "UPDATE tbl_customer_balance SET balance_amount = $fba, balance_gold = $fbg, balance_silver = $fbs, last_transaction_date = '$invoice_date', last_updated = NOW() WHERE customer_name = '$customer_name'";
                mysqli_query($conn, $upd_b);
            } elseif ($lcid > 0) {
                mysqli_query($conn, "
                    INSERT INTO tbl_customer_balance (customer_id, customer_name, balance_amount, balance_gold, balance_silver, last_transaction_date, last_updated)
                    VALUES ($lcid, '$customer_name', $fba, $fbg, $fbs, '$invoice_date', NOW())
                ");
            } else {
                $bn = getRecord("SELECT id FROM tbl_customer_balance WHERE customer_name = '$customer_name' LIMIT 1");
                if ($bn && !empty($bn['id'])) {
                    mysqli_query($conn, "
                        UPDATE tbl_customer_balance SET balance_amount = $fba, balance_gold = $fbg, balance_silver = $fbs, last_transaction_date = '$invoice_date', last_updated = NOW()
                        WHERE customer_name = '$customer_name'
                    ");
                } else {
                    mysqli_query($conn, "
                        INSERT INTO tbl_customer_balance (customer_id, customer_name, balance_amount, balance_gold, balance_silver, last_transaction_date, last_updated)
                        VALUES (0, '$customer_name', $fba, $fbg, $fbs, '$invoice_date', NOW())
                    ");
                }
            }
        }
    }

    mysqli_commit($conn);

    require_once __DIR__ . '/../includes/auragold_notifications.php';
    $oj_label = $oj_scrap_save_do_stock_mirror ? 'Old Jewelry Scrap Stock In' : 'Old Jewelry Scrap Invoice';
    auragold_notify_document_saved($conn, [
        'label' => $oj_label,
        'verb' => $oj_invoice_existed ? 'updated' : 'created',
        'number' => $invoice_no,
        'party' => $customer_name,
        'doc_date' => $invoice_date,
        'due_date' => $due_date !== null ? (string) $due_date : '',
        'ref_id' => (int) $invoice_id,
    ]);

    $barcodes_unique = [];
    $barcode_seen = [];
    foreach ($saved_barcodes as $sb) {
        if (!isset($barcode_seen[$sb])) {
            $barcode_seen[$sb] = true;
            $barcodes_unique[] = $sb;
        }
    }
    echo json_encode([
        'status' => 'success',
        'message' => 'Saved successfully.',
        'invoice_id' => $invoice_id,
        'order_id' => $invoice_id,
        'invoice_no' => $invoice_no,
        'order_no' => $invoice_no,
        'barcodes' => $barcodes_unique,
        'new_barcodes' => $metal_exchange_barcodes_out,
    ]);
} catch (Exception $e) {
    if (isset($conn)) @mysqli_rollback($conn);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
