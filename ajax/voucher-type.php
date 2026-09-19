<?php
session_start();
require_once "../config.php";

header('Content-Type: application/json; charset=utf-8');

$user = $_SESSION['Admin']['id'] ?? 0;
$action = $_POST['action'] ?? '';

function voucher_payment_defaults() {
    return [
        'cash' => 1,
        'metal_exchange' => 1,
        'bank' => 1,
        'scrap' => 1,
        'cheque' => 1,
        'add_diamond' => 1,
        'upi' => 1,
        'add_stone' => 1,
        'card' => 1,
        'add_old_jewellery' => 1,
    ];
}

function voucher_payment_table_exists($conn) {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $q = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_voucher_payment_buttons'");
    $cached = $q && mysqli_num_rows($q) > 0;
    if ($q) {
        mysqli_free_result($q);
    }
    return $cached;
}

/** Cache: [ "tbl.col" => bool ] */
function auragold_table_has_column($conn, $table, $column) {
    static $cache = [];
    $key = $table . '.' . $column;
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $t = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $c = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($t === '' || $c === '') {
        return $cache[$key] = false;
    }
    $q = @mysqli_query($conn, "SHOW COLUMNS FROM `$t` LIKE '$c'");
    $cache[$key] = $q && mysqli_num_rows($q) > 0;
    if ($q) {
        mysqli_free_result($q);
    }
    return $cache[$key];
}

/** Safe JSON for browser (invalid UTF-8 in DB rows must not break the response). */
function voucher_type_ajax_json_encode($payload) {
    $flags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $json = json_encode($payload, $flags);
    if ($json !== false) {
        return $json;
    }
    $payload['data'] = null;
    $payload['metal_allocations'] = [];
    $payload['tax_allocations'] = [];
    $payload['field_visibility'] = null;
    $payload['payment_buttons'] = voucher_payment_defaults();
    $payload['status'] = 'error';
    $payload['message'] = 'Could not encode response (check DB text encoding).';
    return json_encode($payload, $flags);
}

if ($action === "get") {
    // Do not run ALTER/migrations on GET — can time out or fail and break JSON parsing in the browser.
    // Schema updates run on add/update only.
    if (!isset($conn) || !($conn instanceof mysqli)) {
        echo json_encode(['status' => 'error', 'message' => 'Database connection not available.']);
        exit;
    }
    $id = intval($_POST['id']);
    $settings_bid = function_exists('auragold_voucher_type_settings_resolve_branch_id')
        ? auragold_voucher_type_settings_resolve_branch_id($_POST['branch_id'] ?? 0)
        : 0;

    $voucher = getRecord("SELECT * FROM tbl_voucher_types WHERE id='$id'");
    if (!$voucher) {
        echo json_encode(["status" => "error", "message" => "Voucher type not found"]);
        exit;
    }

    $maWhere = "voucher_type_id='$id'";
    if (auragold_table_has_column($conn, 'tbl_voucher_metal_allocations', 'branch_id')) {
        $maWhere .= " AND branch_id='" . (int) $settings_bid . "'";
    }
    $metalAllocations = getList("SELECT * FROM tbl_voucher_metal_allocations WHERE $maWhere");

    $taWhere = "voucher_type_id='$id'";
    if (auragold_table_has_column($conn, 'tbl_voucher_tax_allocations', 'branch_id')) {
        $taWhere .= " AND branch_id='" . (int) $settings_bid . "'";
    }
    $taxAllocations = getList("SELECT * FROM tbl_voucher_tax_allocations WHERE $taWhere");

    $fvWhere = "voucher_type_id='$id'";
    if (auragold_table_has_column($conn, 'tbl_voucher_field_visibility', 'branch_id')) {
        $fvWhere .= " AND branch_id='" . (int) $settings_bid . "'";
    }
    $fieldVisibility = getRecord("SELECT * FROM tbl_voucher_field_visibility WHERE $fvWhere LIMIT 1");

    $paymentButtons = voucher_payment_defaults();
    if (voucher_payment_table_exists($conn)) {
        $pbWhere = "voucher_type_id='$id'";
        if (auragold_table_has_column($conn, 'tbl_voucher_payment_buttons', 'branch_id')) {
            $pbWhere .= " AND branch_id='" . (int) $settings_bid . "'";
        }
        $pr = getRecord("SELECT * FROM tbl_voucher_payment_buttons WHERE $pbWhere LIMIT 1");
        if ($pr && is_array($pr)) {
            foreach ($paymentButtons as $k => $_) {
                if (isset($pr[$k])) {
                    $paymentButtons[$k] = (int) $pr[$k];
                }
            }
        }
    }

    echo voucher_type_ajax_json_encode([
        "status" => "success",
        "data" => $voucher,
        "settings_branch_id" => (int) $settings_bid,
        "metal_allocations" => $metalAllocations,
        "tax_allocations" => $taxAllocations,
        "field_visibility" => $fieldVisibility,
        "payment_buttons" => $paymentButtons,
    ]);
    exit;
}

if ($action === "add" || $action === "update") {
    if (function_exists('auragold_ensure_voucher_type_child_tables_branch_scope')) {
        auragold_ensure_voucher_type_child_tables_branch_scope($conn);
    }
    $settings_bid = function_exists('auragold_voucher_type_settings_resolve_branch_id')
        ? auragold_voucher_type_settings_resolve_branch_id($_POST['branch_id'] ?? 0)
        : 0;

    $id = $action === "update" ? intval($_POST['id']) : 0;
    $name = esc($_POST['name']);
    $method_of_numbering = esc($_POST['method_of_numbering']);
    $type_of_voucher = esc($_POST['type_of_voucher']);
    $calculate_amount_by = esc($_POST['calculate_amount_by']);
    $calculate_wastage_by = esc($_POST['calculate_wastage_by']);
    $fixing_type = esc($_POST['fixing_type'] ?? 'Standard');
    if (!in_array($fixing_type, ['Standard', 'Hedging'], true)) {
        $fixing_type = 'Standard';
    }
    $calculate_loss_by = esc($_POST['calculate_loss_by']);
    $billing_type = esc($_POST['billing_type'] ?? 'standard');
    $do_not_apply_on_stock = intval($_POST['do_not_apply_on_stock'] ?? 0);
    $sales_persons_mandatory = intval($_POST['sales_persons_mandatory'] ?? 0);
    $create_auto_journal_voucher = intval($_POST['create_auto_journal_voucher'] ?? 0);
    $metal_unfix = intval($_POST['metal_unfix'] ?? 0);
    $internal_unfix = intval($_POST['internal_unfix'] ?? 0);
    $do_not_allow_0_amount = intval($_POST['do_not_allow_0_amount'] ?? 0);
    $payment_mandatory = intval($_POST['payment_mandatory'] ?? 0);
    $calculate_markup_on_sale = intval($_POST['calculate_markup_on_sale'] ?? 0);
    $enable_item_fast_fields = intval($_POST['enable_item_fast_fields'] ?? 0);

    if ($action === "add") {
        $insCols = 'name, method_of_numbering, type_of_voucher, calculate_amount_by, calculate_wastage_by, fixing_type, calculate_loss_by';
        $insVals = "'$name', '$method_of_numbering', '$type_of_voucher', '$calculate_amount_by', '$calculate_wastage_by', '$fixing_type', '$calculate_loss_by'";
        if (auragold_table_has_column($conn, 'tbl_voucher_types', 'billing_type')) {
            $insCols .= ', billing_type';
            $insVals .= ", '$billing_type'";
        }
        $insCols .= ', do_not_apply_on_stock, sales_persons_mandatory, create_auto_journal_voucher, metal_unfix, internal_unfix, do_not_allow_0_amount, payment_mandatory, calculate_markup_on_sale';
        $insVals .= ", '$do_not_apply_on_stock', '$sales_persons_mandatory', '$create_auto_journal_voucher', '$metal_unfix', '$internal_unfix', '$do_not_allow_0_amount', '$payment_mandatory', '$calculate_markup_on_sale'";
        if (auragold_table_has_column($conn, 'tbl_voucher_types', 'enable_item_fast_fields')) {
            $insCols .= ', enable_item_fast_fields';
            $insVals .= ", '$enable_item_fast_fields'";
        }
        $insCols .= ', created_by, created_at';
        $insVals .= ", '$user', NOW()";
        $sqlIns = "INSERT INTO tbl_voucher_types ($insCols) VALUES ($insVals)";
        if (!mysqli_query($conn, $sqlIns)) {
            echo json_encode(['status' => 'error', 'message' => 'Save failed: ' . mysqli_error($conn)]);
            exit;
        }
        $voucher_id = mysqli_insert_id($conn);
    } else {
        $setParts = [
            "name='$name'", "method_of_numbering='$method_of_numbering'", "type_of_voucher='$type_of_voucher'",
            "calculate_amount_by='$calculate_amount_by'", "calculate_wastage_by='$calculate_wastage_by'",
            "fixing_type='$fixing_type'", "calculate_loss_by='$calculate_loss_by'",
        ];
        if (auragold_table_has_column($conn, 'tbl_voucher_types', 'billing_type')) {
            $setParts[] = "billing_type='$billing_type'";
        }
        $setParts = array_merge($setParts, [
            "do_not_apply_on_stock='$do_not_apply_on_stock'", "sales_persons_mandatory='$sales_persons_mandatory'",
            "create_auto_journal_voucher='$create_auto_journal_voucher'", "metal_unfix='$metal_unfix'",
            "internal_unfix='$internal_unfix'", "do_not_allow_0_amount='$do_not_allow_0_amount'",
            "payment_mandatory='$payment_mandatory'", "calculate_markup_on_sale='$calculate_markup_on_sale'",
        ]);
        if (auragold_table_has_column($conn, 'tbl_voucher_types', 'enable_item_fast_fields')) {
            $setParts[] = "enable_item_fast_fields='$enable_item_fast_fields'";
        }
        $setParts[] = "modified_by='$user'";
        $setParts[] = 'updated_at=NOW()';
        $sqlUp = 'UPDATE tbl_voucher_types SET ' . implode(',', $setParts) . " WHERE id='$id'";
        if (!mysqli_query($conn, $sqlUp)) {
            echo json_encode(['status' => 'error', 'message' => 'Save failed: ' . mysqli_error($conn)]);
            exit;
        }
        $voucher_id = $id;

        $delMa = "DELETE FROM tbl_voucher_metal_allocations WHERE voucher_type_id='$voucher_id'";
        if (auragold_table_has_column($conn, 'tbl_voucher_metal_allocations', 'branch_id')) {
            $delMa .= " AND branch_id='" . (int) $settings_bid . "'";
        }
        mysqli_query($conn, $delMa);

        $delTa = "DELETE FROM tbl_voucher_tax_allocations WHERE voucher_type_id='$voucher_id'";
        if (auragold_table_has_column($conn, 'tbl_voucher_tax_allocations', 'branch_id')) {
            $delTa .= " AND branch_id='" . (int) $settings_bid . "'";
        }
        mysqli_query($conn, $delTa);

        $delFv = "DELETE FROM tbl_voucher_field_visibility WHERE voucher_type_id='$voucher_id'";
        if (auragold_table_has_column($conn, 'tbl_voucher_field_visibility', 'branch_id')) {
            $delFv .= " AND branch_id='" . (int) $settings_bid . "'";
        }
        mysqli_query($conn, $delFv);

        if (voucher_payment_table_exists($conn)) {
            $delPb = "DELETE FROM tbl_voucher_payment_buttons WHERE voucher_type_id='$voucher_id'";
            if (auragold_table_has_column($conn, 'tbl_voucher_payment_buttons', 'branch_id')) {
                $delPb .= " AND branch_id='" . (int) $settings_bid . "'";
            }
            mysqli_query($conn, $delPb);
        }
    }

    $metal_allocations = [];
    if (isset($_POST['metal_allocations'])) {
        if (is_string($_POST['metal_allocations'])) {
            $metal_allocations = json_decode($_POST['metal_allocations'], true);
        } else {
            $metal_allocations = $_POST['metal_allocations'];
        }
    }

    if (is_array($metal_allocations) && count($metal_allocations) > 0) {
        foreach ($metal_allocations as $ma) {
            $metal_id = intval($ma['metal_id'] ?? 0);
            $discount = floatval($ma['discount'] ?? 0);
            if ($metal_id > 0) {
                if (auragold_table_has_column($conn, 'tbl_voucher_metal_allocations', 'branch_id')) {
                    $sb = (int) $settings_bid;
                    mysqli_query($conn, "
                        INSERT INTO tbl_voucher_metal_allocations (voucher_type_id, branch_id, metal_id, discount, created_at)
                        VALUES ('$voucher_id', '$sb', '$metal_id', '$discount', NOW())
                    ");
                } else {
                    mysqli_query($conn, "
                        INSERT INTO tbl_voucher_metal_allocations (voucher_type_id, metal_id, discount, created_at)
                        VALUES ('$voucher_id', '$metal_id', '$discount', NOW())
                    ");
                }
            }
        }
    }

    $tax_allocations = [];
    if (isset($_POST['tax_allocations'])) {
        if (is_string($_POST['tax_allocations'])) {
            $tax_allocations = json_decode($_POST['tax_allocations'], true);
        } else {
            $tax_allocations = $_POST['tax_allocations'];
        }
    }

    if (is_array($tax_allocations) && count($tax_allocations) > 0) {
        foreach ($tax_allocations as $ta) {
            $tax_id = intval($ta['tax_id'] ?? 0);
            if ($tax_id > 0) {
                if (auragold_table_has_column($conn, 'tbl_voucher_tax_allocations', 'branch_id')) {
                    $sb = (int) $settings_bid;
                    mysqli_query($conn, "
                        INSERT INTO tbl_voucher_tax_allocations (voucher_type_id, branch_id, tax_id, created_at)
                        VALUES ('$voucher_id', '$sb', '$tax_id', NOW())
                    ");
                } else {
                    mysqli_query($conn, "
                        INSERT INTO tbl_voucher_tax_allocations (voucher_type_id, tax_id, created_at)
                        VALUES ('$voucher_id', '$tax_id', NOW())
                    ");
                }
            }
        }
    }

    $field_visibility = [];
    if (isset($_POST['field_visibility'])) {
        if (is_string($_POST['field_visibility'])) {
            $field_visibility = json_decode($_POST['field_visibility'], true);
        } else {
            $field_visibility = $_POST['field_visibility'];
        }
    }

    if (is_array($field_visibility)) {
        $reference_no = intval($field_visibility['reference_no'] ?? 0);
        $sales_person = intval($field_visibility['sales_person'] ?? 0);
        $currency = intval($field_visibility['currency'] ?? 0);
        $against_of = intval($field_visibility['against_of'] ?? 0);
        $layaways = intval($field_visibility['layaways'] ?? 0);
        $due_date = intval($field_visibility['due_date'] ?? 0);
        $fixing_type_field = intval($field_visibility['fixing_type'] ?? 0);
        $show_billing_type = intval($field_visibility['show_billing_type'] ?? 0);
        $show_metal_unfix = intval($field_visibility['show_metal_unfix'] ?? 0);
        $show_payment_term = intval($field_visibility['show_payment_term'] ?? 0);
        $show_unfix = intval($field_visibility['show_unfix'] ?? 0);
        $show_shipping_method = intval($field_visibility['show_shipping_method'] ?? 0);
        $show_barcode_no = intval($field_visibility['show_barcode_no'] ?? 0);
        $show_ounce_rate = intval($field_visibility['show_ounce_rate'] ?? 0);
        $show_lead_source = intval($field_visibility['show_lead_source'] ?? 0);
        $show_design_no = intval($field_visibility['show_design_no'] ?? 0);
        $show_product_code = intval($field_visibility['show_product_code'] ?? 0);
        $show_dmd_or_nam_unfix = intval($field_visibility['show_dmd_or_nam_unfix'] ?? 0);
        $show_update_tax_dropdown = intval($field_visibility['show_update_tax_dropdown'] ?? 0);

        $sbFv = (int) $settings_bid;
        $fvHasBranch = auragold_table_has_column($conn, 'tbl_voucher_field_visibility', 'branch_id');
        if (auragold_table_has_column($conn, 'tbl_voucher_field_visibility', 'show_billing_type')) {
            if ($fvHasBranch) {
                $sqlFv = "
                INSERT INTO tbl_voucher_field_visibility 
                (voucher_type_id, branch_id, reference_no, sales_person, currency, against_of, layaways, due_date, fixing_type,
                 show_billing_type, show_metal_unfix, show_payment_term, show_unfix, show_shipping_method,
                 show_barcode_no, show_ounce_rate, show_lead_source, show_design_no, show_product_code,
                 show_dmd_or_nam_unfix, show_update_tax_dropdown, created_at)
                VALUES 
                ('$voucher_id', '$sbFv', '$reference_no', '$sales_person', '$currency', '$against_of', '$layaways', '$due_date', '$fixing_type_field',
                 '$show_billing_type', '$show_metal_unfix', '$show_payment_term', '$show_unfix', '$show_shipping_method',
                 '$show_barcode_no', '$show_ounce_rate', '$show_lead_source', '$show_design_no', '$show_product_code',
                 '$show_dmd_or_nam_unfix', '$show_update_tax_dropdown', NOW())
                ";
            } else {
                $sqlFv = "
                INSERT INTO tbl_voucher_field_visibility 
                (voucher_type_id, reference_no, sales_person, currency, against_of, layaways, due_date, fixing_type,
                 show_billing_type, show_metal_unfix, show_payment_term, show_unfix, show_shipping_method,
                 show_barcode_no, show_ounce_rate, show_lead_source, show_design_no, show_product_code,
                 show_dmd_or_nam_unfix, show_update_tax_dropdown, created_at)
                VALUES 
                ('$voucher_id', '$reference_no', '$sales_person', '$currency', '$against_of', '$layaways', '$due_date', '$fixing_type_field',
                 '$show_billing_type', '$show_metal_unfix', '$show_payment_term', '$show_unfix', '$show_shipping_method',
                 '$show_barcode_no', '$show_ounce_rate', '$show_lead_source', '$show_design_no', '$show_product_code',
                 '$show_dmd_or_nam_unfix', '$show_update_tax_dropdown', NOW())
                ";
            }
        } else {
            $legacyFix = ($show_billing_type || $fixing_type_field) ? 1 : 0;
            if ($fvHasBranch) {
                $sqlFv = "
                INSERT INTO tbl_voucher_field_visibility 
                (voucher_type_id, branch_id, reference_no, sales_person, currency, against_of, layaways, due_date, fixing_type, created_at)
                VALUES 
                ('$voucher_id', '$sbFv', '$reference_no', '$sales_person', '$currency', '$against_of', '$layaways', '$due_date', '$legacyFix', NOW())
                ";
            } else {
                $sqlFv = "
                INSERT INTO tbl_voucher_field_visibility 
                (voucher_type_id, reference_no, sales_person, currency, against_of, layaways, due_date, fixing_type, created_at)
                VALUES 
                ('$voucher_id', '$reference_no', '$sales_person', '$currency', '$against_of', '$layaways', '$due_date', '$legacyFix', NOW())
                ";
            }
        }
        if (!mysqli_query($conn, $sqlFv)) {
            echo json_encode(['status' => 'error', 'message' => 'Field visibility save failed: ' . mysqli_error($conn)]);
            exit;
        }
    }

    if (voucher_payment_table_exists($conn)) {
        $pb = voucher_payment_defaults();
        $raw = $_POST['payment_buttons'] ?? '{}';
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($pb as $k => $def) {
                    if (isset($decoded[$k])) {
                        $pb[$k] = intval($decoded[$k]) ? 1 : 0;
                    }
                }
            }
        }
        $cash = intval($pb['cash']);
        $metal_exchange = intval($pb['metal_exchange']);
        $bank = intval($pb['bank']);
        $scrap = intval($pb['scrap']);
        $cheque = intval($pb['cheque']);
        $add_diamond = intval($pb['add_diamond']);
        $upi = intval($pb['upi']);
        $add_stone = intval($pb['add_stone']);
        $card = intval($pb['card']);
        $add_old_jewellery = intval($pb['add_old_jewellery']);
        $sbPb = (int) $settings_bid;
        if (auragold_table_has_column($conn, 'tbl_voucher_payment_buttons', 'branch_id')) {
            $okPb = mysqli_query($conn, "
                INSERT INTO tbl_voucher_payment_buttons 
                (voucher_type_id, branch_id, cash, metal_exchange, bank, scrap, cheque, add_diamond, upi, add_stone, card, add_old_jewellery, updated_at)
                VALUES 
                ('$voucher_id', '$sbPb', '$cash', '$metal_exchange', '$bank', '$scrap', '$cheque', '$add_diamond', '$upi', '$add_stone', '$card', '$add_old_jewellery', NOW())
            ");
        } else {
            $okPb = mysqli_query($conn, "
                INSERT INTO tbl_voucher_payment_buttons 
                (voucher_type_id, cash, metal_exchange, bank, scrap, cheque, add_diamond, upi, add_stone, card, add_old_jewellery, updated_at)
                VALUES 
                ('$voucher_id', '$cash', '$metal_exchange', '$bank', '$scrap', '$cheque', '$add_diamond', '$upi', '$add_stone', '$card', '$add_old_jewellery', NOW())
            ");
        }
        if (!$okPb) {
            echo json_encode(['status' => 'error', 'message' => 'Payment buttons save failed: ' . mysqli_error($conn)]);
            exit;
        }
    }

    echo json_encode(["status" => "success", "id" => $voucher_id]);
    exit;
}

if ($action === "delete") {
    $id = intval($_POST['id']);
    mysqli_query($conn, "UPDATE tbl_voucher_types SET status=0, modified_by='$user', updated_at=NOW() WHERE id='$id'");
    echo json_encode(["status" => "success"]);
    exit;
}

echo json_encode(["status" => "error", "message" => "Invalid action"]);
