<?php 
session_start();
require_once 'config.php';
require_once __DIR__ . '/includes/auragold_transaction_report_whatsapp.php';

/** Salesperson label for column 3: SP-{name}, or NA if empty (avoids double SP- prefix). */
function transaction_report_sp_display($raw) {
    $n = trim((string)$raw);
    if ($n === '') {
        return 'NA';
    }
    if (preg_match('/^SP-/i', $n)) {
        return $n;
    }
    return 'SP-' . $n;
}

/**
 * Header amount with fallbacks: grand_total → net_total → subtotal → item lines.
 * Sale quotations often store 0 in grand_total when the UI grand-total span includes a currency symbol.
 */
function transaction_report_coalesce_amount_sql($conn, string $headerTable, string $itemsTable = '', string $itemsFk = 'quotation_id'): string {
    $headerTable = preg_replace('/[^a-zA-Z0-9_]/', '', $headerTable);
    $parts = ['NULLIF(`' . $headerTable . '`.`grand_total`, 0)'];
    $hasCol = static function ($conn, string $table, string $col): bool {
        if (function_exists('auragold_tbl_has_column')) {
            return auragold_tbl_has_column($conn, $table, $col);
        }
        return true;
    };
    if ($hasCol($conn, $headerTable, 'net_total')) {
        $parts[] = 'NULLIF(`' . $headerTable . '`.`net_total`, 0)';
    }
    if ($hasCol($conn, $headerTable, 'subtotal')) {
        $parts[] = 'NULLIF(`' . $headerTable . '`.`subtotal`, 0)';
    }
    $itemsTable = preg_replace('/[^a-zA-Z0-9_]/', '', $itemsTable);
    $itemsFk = preg_replace('/[^a-zA-Z0-9_]/', '', $itemsFk);
    if ($itemsTable !== '' && $itemsFk !== '' && $conn instanceof mysqli) {
        $chk = @mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $itemsTable) . "'");
        $exists = $chk && mysqli_num_rows($chk) > 0;
        if ($chk) {
            mysqli_free_result($chk);
        }
        if ($exists) {
            $lineParts = [];
            foreach (['net_amt_with_tax', 'net_amount', 'amount'] as $col) {
                if ($hasCol($conn, $itemsTable, $col)) {
                    $lineParts[] = 'NULLIF(i.`' . $col . '`, 0)';
                }
            }
            if ($lineParts !== []) {
                $lineExpr = 'COALESCE(' . implode(', ', $lineParts) . ', 0)';
                $parts[] = '(SELECT COALESCE(SUM(' . $lineExpr . '), 0) FROM `' . $itemsTable . '` i WHERE i.`' . $itemsFk . '` = `' . $headerTable . '`.id)';
            }
        }
    }
    return 'COALESCE(' . implode(', ', $parts) . ', 0)';
}

/** Resolve tbl_branches.name for a branch id; em dash when unknown or unset. */
function transaction_report_branch_label(array $nameById, $branchId) {
    $bid = (int) $branchId;
    if ($bid <= 0) {
        return '—';
    }
    $n = $nameById[$bid] ?? '';
    return $n !== '' ? $n : '—';
}

/** Normalize filter date to Y-m-d (accepts Y-m-d, d-m-Y, d/m/Y). */
function transaction_report_normalize_date($raw): string {
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        return $raw;
    }
    if (preg_match('/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/', $raw, $m)) {
        return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
    }
    $ts = strtotime($raw);
    return $ts ? date('Y-m-d', $ts) : '';
}

/** Sort key for newest-saved-first (prefer updated/created datetime, else document date). */
function transaction_report_sort_ts($docDate = '', $savedAt = ''): string {
    $savedAt = trim((string) $savedAt);
    if ($savedAt !== '' && $savedAt !== '0000-00-00' && $savedAt !== '0000-00-00 00:00:00') {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $savedAt)) {
            return $savedAt . ' 00:00:00';
        }
        return $savedAt;
    }
    $docDate = trim((string) $docDate);
    if ($docDate !== '' && $docDate !== '0000-00-00') {
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $docDate, $m)) {
            $d = substr($docDate, 0, 10);
            return (strlen($docDate) > 10) ? $docDate : ($d . ' 00:00:00');
        }
        $ts = strtotime($docDate);
        if ($ts) {
            return date('Y-m-d H:i:s', $ts);
        }
    }
    return '1970-01-01 00:00:00';
}

/**
 * Fill sort_ts from each voucher table's updated_at/created_at so recently saved rows float to the top.
 *
 * @param list<array<string,mixed>> $rows
 */
function transaction_report_enrich_sort_timestamps(array &$rows, $conn): void {
    if (!($conn instanceof mysqli) || $rows === []) {
        return;
    }
    $typeTable = [
        'purchase_invoice' => 'tbl_purchase_invoices',
        'sale_invoice' => 'tbl_sale_invoices',
        'sale_order' => 'tbl_sale_orders',
        'jobwork_order' => 'tbl_jobwork_orders',
        'sale_return' => 'tbl_sale_returns',
        'purchase_return' => 'tbl_purchase_returns',
        'sale_quotation' => 'tbl_sale_quotations',
        'purchase_quotation' => 'tbl_purchase_quotations',
        'material_issue' => 'tbl_material_issues',
        'material_receive' => 'tbl_material_receives',
        'sale_fixing_direct' => 'tbl_sale_fixing_direct',
        'purchase_fixing_direct' => 'tbl_purchase_fixing_direct',
        'old_jewelry_scrap_invoice' => 'tbl_old_jewelry_scrap_invoices',
        'payment_voucher' => 'tbl_payment_vouchers',
        'receipt_voucher' => 'tbl_receipt_vouchers',
        'sale_receipt_voucher' => 'tbl_sale_receipt_vouchers',
        'advance_payment' => 'tbl_advance_payments',
        'contra_voucher' => 'tbl_contra_vouchers',
        'journal_voucher' => 'tbl_journal_vouchers',
        'metal_to_amount' => 'tbl_metal_amount_conversions',
        'amount_to_metal' => 'tbl_metal_amount_conversions',
        'credit_note' => 'tbl_credit_notes',
        'debit_note' => 'tbl_debit_notes',
        'repair_invoice' => 'tbl_repair_invoices',
        'repair_order' => 'tbl_repair_orders',
        'purchase_order' => 'tbl_purchase_orders',
        'consignment_in' => 'tbl_consignment_in',
        'consignment_out' => 'tbl_consignment_out',
    ];

    $byType = [];
    foreach ($rows as $i => $t) {
        $type = (string) ($t['type'] ?? '');
        $id = (int) ($t['id'] ?? 0);
        if ($type === '' || $id <= 0) {
            continue;
        }
        $byType[$type][$id] = $i;
    }

    $hasCol = static function ($conn, string $table, string $col): bool {
        if (function_exists('auragold_tbl_has_column')) {
            return auragold_tbl_has_column($conn, $table, $col);
        }
        $c = @mysqli_query($conn, "SHOW COLUMNS FROM `" . str_replace('`', '``', $table) . "` LIKE '" . mysqli_real_escape_string($conn, $col) . "'");
        $ok = $c && mysqli_num_rows($c) > 0;
        if ($c) {
            mysqli_free_result($c);
        }
        return $ok;
    };

    foreach ($byType as $type => $idToIdx) {
        $table = $typeTable[$type] ?? '';
        if ($table === '') {
            continue;
        }
        $tcheck = @mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $table) . "'");
        if (!$tcheck || mysqli_num_rows($tcheck) === 0) {
            if ($tcheck) {
                mysqli_free_result($tcheck);
            }
            continue;
        }
        mysqli_free_result($tcheck);

        $hasCreated = $hasCol($conn, $table, 'created_at');
        $hasUpdated = $hasCol($conn, $table, 'updated_at');
        if (!$hasCreated && !$hasUpdated) {
            continue;
        }
        if ($hasCreated && $hasUpdated) {
            $tsSql = "COALESCE("
                . "IF(updated_at IS NOT NULL AND updated_at >= '1000-01-01 00:00:00', updated_at, NULL), "
                . "IF(created_at IS NOT NULL AND created_at >= '1000-01-01 00:00:00', created_at, NULL)"
                . ") AS saved_at";
        } elseif ($hasUpdated) {
            $tsSql = "updated_at AS saved_at";
        } else {
            $tsSql = "created_at AS saved_at";
        }

        $ids = array_keys($idToIdx);
        foreach (array_chunk($ids, 400) as $chunk) {
            $in = implode(',', array_map('intval', $chunk));
            if ($in === '') {
                continue;
            }
            $rs = @mysqli_query($conn, "SELECT id, $tsSql FROM `$table` WHERE id IN ($in)");
            if (!$rs) {
                continue;
            }
            while ($row = mysqli_fetch_assoc($rs)) {
                $id = (int) ($row['id'] ?? 0);
                if ($id <= 0 || !isset($idToIdx[$id])) {
                    continue;
                }
                $idx = $idToIdx[$id];
                $rows[$idx]['created_at'] = (string) ($row['saved_at'] ?? '');
                $rows[$idx]['sort_ts'] = transaction_report_sort_ts($rows[$idx]['date'] ?? '', $row['saved_at'] ?? '');
            }
            mysqli_free_result($rs);
        }
    }

    foreach ($rows as $i => $t) {
        if (empty($t['sort_ts'])) {
            $rows[$i]['sort_ts'] = transaction_report_sort_ts($t['date'] ?? '', $t['created_at'] ?? '');
        }
    }
}

/**
 * Map Advance Filter "Type of Voucher" keys → transaction list type keys.
 *
 * @return list<string>
 */
function transaction_report_voucher_filter_to_types(array $keys): array {
    $map = [
        'purchase_invoice' => ['purchase_invoice'],
        'sale_invoice' => ['sale_invoice'],
        'sale_order' => ['sale_order'],
        'jobwork_order' => ['jobwork_order'],
        'payment' => ['payment_voucher'],
        'payment_voucher' => ['payment_voucher'],
        'receipt' => ['receipt_voucher', 'sale_receipt_voucher'],
        'receipt_voucher' => ['receipt_voucher'],
        'sale_receipt_voucher' => ['sale_receipt_voucher'],
        'advance' => ['advance_payment'],
        'advance_payment' => ['advance_payment'],
        'return' => ['sale_return', 'purchase_return'],
        'sale_return' => ['sale_return'],
        'purchase_return' => ['purchase_return'],
        'sale_quotation' => ['sale_quotation'],
        'purchase_quotation' => ['purchase_quotation'],
        'material_issue' => ['material_issue'],
        'material_receive' => ['material_receive'],
        'sale_fixing_direct' => ['sale_fixing_direct'],
        'purchase_fixing_direct' => ['purchase_fixing_direct'],
        'old_jewelry_scrap_invoice' => ['old_jewelry_scrap_invoice'],
        'metal_to_amount' => ['metal_to_amount'],
        'amount_to_metal' => ['amount_to_metal'],
        'contra_voucher' => ['contra_voucher'],
        'contra' => ['contra_voucher'],
        'journal_voucher' => ['journal_voucher'],
        'journal' => ['journal_voucher'],
        'credit_note' => ['credit_note'],
        'debit_note' => ['debit_note'],
        'repair_invoice' => ['repair_invoice'],
        'repair_order' => ['repair_order'],
        'purchase_order' => ['purchase_order'],
        'consignment_in' => ['consignment_in'],
        'consignment_out' => ['consignment_out'],
        'investment_fund_installment' => ['investment_fund_installment'],
        'investment_fund_transfer' => ['investment_fund_transfer'],
    ];
    $out = [];
    foreach ($keys as $k) {
        $k = trim((string) $k);
        if ($k === '') {
            continue;
        }
        if (isset($map[$k])) {
            foreach ($map[$k] as $t) {
                $out[] = $t;
            }
        } else {
            $out[] = $k;
        }
    }
    return array_values(array_unique($out));
}

// Get filters (multi-select via [] supported; legacy single values still work)
$date_range = isset($_GET['date_range']) ? esc($_GET['date_range']) : '';

$branch_ids = [];
if (isset($_GET['branch_id'])) {
    if (is_array($_GET['branch_id'])) {
        $branch_ids = array_values(array_unique(array_filter(array_map('intval', $_GET['branch_id']))));
    } else {
        $b = (int) $_GET['branch_id'];
        if ($b > 0) {
            $branch_ids = [$b];
        }
    }
}
$branch_id = count($branch_ids) === 1 ? (int) $branch_ids[0] : 0;

/**
 * Branch IDs used for SQL filtering (same idea as auragold_sql_and_branch_scope):
 * - Logged-in / working branch context (effective branch > 0): always scope to that branch only.
 * - Otherwise: optional explicit ?branch_id[]= from the filter form.
 * - When no branch filter and no working branch (e.g. main/HQ session): default to the registry main branch only
 *   so sub-branch invoices (e.g. branch_id = 1) do not appear mixed with the main office list.
 */
$tr_main_branch_id = function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0;
$tr_effective_branch_id = function_exists('auragold_effective_branch_id') ? auragold_effective_branch_id() : 0;
if ($tr_effective_branch_id > 0) {
    $tr_resolved_branch_ids = [$tr_effective_branch_id];
} elseif (!empty($branch_ids)) {
    $tr_resolved_branch_ids = $branch_ids;
} elseif ($tr_main_branch_id > 0) {
    $tr_resolved_branch_ids = [$tr_main_branch_id];
} else {
    $tr_resolved_branch_ids = [];
}
$tr_scope_includes_main_legacy = ($tr_main_branch_id > 0 && !empty($tr_resolved_branch_ids) && in_array($tr_main_branch_id, $tr_resolved_branch_ids, true));

$invoice_no_raw = isset($_GET['invoice_no']) ? trim((string) $_GET['invoice_no']) : '';
$invoice_no = $invoice_no_raw !== '' ? esc($invoice_no_raw) : '';

$bill_to_bill_raw = isset($_GET['bill_to_bill']) ? trim((string) $_GET['bill_to_bill']) : '';
$bill_to_bill = $bill_to_bill_raw !== '' ? esc($bill_to_bill_raw) : '';

$ledger_names_sel = [];
if (isset($_GET['ledger_name'])) {
    if (is_array($_GET['ledger_name'])) {
        foreach ($_GET['ledger_name'] as $n) {
            $n = trim((string) $n);
            if ($n !== '') {
                $ledger_names_sel[] = $n;
            }
        }
    } else {
        $n = trim((string) $_GET['ledger_name']);
        if ($n !== '') {
            $ledger_names_sel[] = $n;
        }
    }
}
$ledger_names_sel = array_values(array_unique($ledger_names_sel));
$ledger_name = count($ledger_names_sel) === 1 ? esc($ledger_names_sel[0]) : '';

$group_ids = [];
if (isset($_GET['group'])) {
    if (is_array($_GET['group'])) {
        foreach ($_GET['group'] as $g) {
            $g = trim((string) $g);
            if ($g !== '') {
                $group_ids[] = $g;
            }
        }
    } else {
        $g = trim((string) $_GET['group']);
        if ($g !== '') {
            $group_ids[] = $g;
        }
    }
}
$group_ids = array_values(array_unique($group_ids));
$group = count($group_ids) === 1 ? esc($group_ids[0]) : '';

$ledger_types_sel = [];
$allowed_ledger_types = ['Customer', 'Supplier', 'Account'];
if (isset($_GET['ledger_type'])) {
    if (is_array($_GET['ledger_type'])) {
        foreach ($_GET['ledger_type'] as $lt) {
            $lt = trim((string) $lt);
            if (in_array($lt, $allowed_ledger_types, true)) {
                $ledger_types_sel[] = $lt;
            }
        }
    } else {
        $lt = trim((string) $_GET['ledger_type']);
        if (in_array($lt, $allowed_ledger_types, true)) {
            $ledger_types_sel[] = $lt;
        }
    }
}
$ledger_types_sel = array_values(array_unique($ledger_types_sel));
$ledger_type = count($ledger_types_sel) === 1 ? esc($ledger_types_sel[0]) : '';

$against_inv_no_raw = isset($_GET['against_inv_no']) ? trim((string) $_GET['against_inv_no']) : '';
$against_inv_no = $against_inv_no_raw !== '' ? esc($against_inv_no_raw) : '';

$only_balance = isset($_GET['only_balance']) ? (int) $_GET['only_balance'] : 0;

// Parse date range if provided
$from_date = '';
$to_date = '';
if (!empty($date_range)) {
    $dates = explode(' - ', $date_range);
    if (count($dates) == 2) {
        $from_date = transaction_report_normalize_date(trim($dates[0]));
        $to_date = transaction_report_normalize_date(trim($dates[1]));
    }
} else {
    $from_date = transaction_report_normalize_date(isset($_GET['from_date']) ? (string) $_GET['from_date'] : '');
    $to_date = transaction_report_normalize_date(isset($_GET['to_date']) ? (string) $_GET['to_date'] : '');
}

// No default "today only" window — empty dates show all records (latest first).
// Previously defaulting to today hid July vouchers and looked like a broken filter.

$search_raw = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
$search = $search_raw !== '' ? esc($search_raw) : '';

// Active tab (default: transactions for unified list)
$active_tab = isset($_GET['tab']) ? esc($_GET['tab']) : 'transactions';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 25;
$offset = ($page - 1) * $per_page;

// Get branches — only those present for this shop / assigned to the user (hide other registry shops)
require_once __DIR__ . '/includes/branch_working_context.php';
if (!function_exists('auragold_um_parse_branch_ids_string')) {
    require_once __DIR__ . '/includes/user_management_schema.php';
}
$branches = getListMaster("SELECT id, name, main_branch_id FROM tbl_branches WHERE status = 1 ORDER BY name ASC");
if (!is_array($branches)) {
    $branches = [];
}
$tr_branch_list_scope_main = function_exists('auragold_branches_page_list_scope_main_id')
    ? (int) auragold_branches_page_list_scope_main_id()
    : 0;
if ($tr_branch_list_scope_main > 0) {
    $branches = array_values(array_filter($branches, static function ($b) use ($tr_branch_list_scope_main) {
        $id = (int) ($b['id'] ?? 0);
        $mb = (int) ($b['main_branch_id'] ?? 0);
        return $id === $tr_branch_list_scope_main || $mb === $tr_branch_list_scope_main;
    }));
}
$tr_assigned_branch_ids = [];
if (!empty($_SESSION['Admin']) && is_array($_SESSION['Admin'])) {
    foreach ($_SESSION['Admin'] as $_tr_uk => $_tr_uv) {
        if (strcasecmp((string) $_tr_uk, 'user_branch_ids') === 0) {
            $tr_assigned_branch_ids = auragold_um_parse_branch_ids_string((string) $_tr_uv);
            break;
        }
    }
}
if ($tr_assigned_branch_ids !== []) {
    $tr_assigned_allow = array_fill_keys($tr_assigned_branch_ids, true);
    if ($tr_effective_branch_id > 0) {
        $tr_assigned_allow[$tr_effective_branch_id] = true;
    }
    $branches = array_values(array_filter($branches, static function ($b) use ($tr_assigned_allow) {
        return isset($tr_assigned_allow[(int) ($b['id'] ?? 0)]);
    }));
}
if (function_exists('auragold_can_user_open_branch_row')) {
    $branches = array_values(array_filter($branches, static function ($b) {
        return auragold_can_user_open_branch_row($b);
    }));
}
$tr_allowed_branch_ids = [];
foreach ($branches as $_trbr) {
    $bid = (int) ($_trbr['id'] ?? 0);
    if ($bid > 0) {
        $tr_allowed_branch_ids[$bid] = $bid;
    }
}
if ($tr_allowed_branch_ids !== [] && !empty($tr_resolved_branch_ids)) {
    $tr_resolved_branch_ids = array_values(array_filter(
        array_map('intval', $tr_resolved_branch_ids),
        static function ($id) use ($tr_allowed_branch_ids) {
            return isset($tr_allowed_branch_ids[$id]);
        }
    ));
    if ($tr_resolved_branch_ids === [] && $tr_effective_branch_id > 0 && isset($tr_allowed_branch_ids[$tr_effective_branch_id])) {
        $tr_resolved_branch_ids = [$tr_effective_branch_id];
    }
}
$tr_scope_includes_main_legacy = ($tr_main_branch_id > 0 && !empty($tr_resolved_branch_ids) && in_array($tr_main_branch_id, $tr_resolved_branch_ids, true));
$tr_branch_name_by_id = [];
foreach ($branches as $_trbr) {
    $tr_branch_name_by_id[(int) ($_trbr['id'] ?? 0)] = (string) ($_trbr['name'] ?? '');
}
$ledger_has_branch_id = isset($conn) && $conn instanceof mysqli && function_exists('auragold_tbl_has_column')
    ? auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'branch_id') : false;

// Get unique ledger names from customer ledger
$ledger_names = getList("SELECT DISTINCT customer_name FROM tbl_customer_ledger WHERE status = 1 ORDER BY customer_name ASC");

// Ledger groups
$ledger_groups = [
    ['id' => 1, 'name' => 'Sundry Debtors'],
    ['id' => 2, 'name' => 'Sundry Creditors'],
    ['id' => 3, 'name' => 'Bank Accounts'],
    ['id' => 4, 'name' => 'Cash'],
    ['id' => 5, 'name' => 'Sales'],
    ['id' => 6, 'name' => 'Purchase'],
];

// Voucher types (Advance Filter — Type of Voucher / Against Voucher)
$voucher_types = [
    'sale_invoice' => 'Sale Invoice',
    'sale_order' => 'Sale Order',
    'jobwork_order' => 'Job Work Order',
    'purchase_invoice' => 'Purchase Invoice',
    'sale_return' => 'Sale Return',
    'purchase_return' => 'Purchase Return',
    'sale_quotation' => 'Sale Quotation',
    'purchase_quotation' => 'Purchase Quotation',
    'material_issue' => 'Material Issue',
    'material_receive' => 'Material Receive',
    'sale_fixing_direct' => 'Sale Fixing Direct',
    'purchase_fixing_direct' => 'Purchase Fixing Direct',
    'old_jewelry_scrap_invoice' => 'Old Jewelry Scrap Invoice',
    'payment_voucher' => 'Payment Voucher',
    'receipt_voucher' => 'Receipt Voucher',
    'sale_receipt_voucher' => 'Sale Receipt Voucher',
    'advance_payment' => 'Advance Payment',
    'metal_to_amount' => 'Metal to Amount',
    'amount_to_metal' => 'Amount to Metal',
    'contra_voucher' => 'Contra Voucher',
    'journal_voucher' => 'Journal Voucher',
    'credit_note' => 'Credit Note',
    'debit_note' => 'Debit Note',
    'repair_invoice' => 'Repair Invoice',
    'repair_order' => 'Repair Order',
    'purchase_order' => 'Purchase Order',
    'consignment_in' => 'Consignment In',
    'consignment_out' => 'Consignment Out',
    'investment_fund_installment' => 'Investment Fund Installment',
    'investment_fund_transfer' => 'Investment Fund Transfer',
    // Legacy short keys (kept for old saved filter links)
    'payment' => 'Payment',
    'receipt' => 'Receipt',
    'advance' => 'Advance',
    'return' => 'Return',
    'contra' => 'Contra Voucher',
    'journal' => 'Journal Voucher',
];

$filter_voucher_keys = [];
if (isset($_GET['voucher_type'])) {
    if (is_array($_GET['voucher_type'])) {
        foreach ($_GET['voucher_type'] as $vk) {
            $vk = trim((string) $vk);
            if ($vk !== '' && isset($voucher_types[$vk])) {
                $filter_voucher_keys[] = $vk;
            }
        }
    } else {
        $vk = trim((string) $_GET['voucher_type']);
        if ($vk !== '' && isset($voucher_types[$vk])) {
            $filter_voucher_keys[] = $vk;
        }
    }
}
$filter_voucher_keys = array_values(array_unique($filter_voucher_keys));
$voucher_type = count($filter_voucher_keys) === 1 ? esc($filter_voucher_keys[0]) : '';

$filter_against_voucher_keys = [];
if (isset($_GET['against_voucher_type'])) {
    if (is_array($_GET['against_voucher_type'])) {
        foreach ($_GET['against_voucher_type'] as $vk) {
            $vk = trim((string) $vk);
            if ($vk !== '' && isset($voucher_types[$vk])) {
                $filter_against_voucher_keys[] = $vk;
            }
        }
    } else {
        $vk = trim((string) $_GET['against_voucher_type']);
        if ($vk !== '' && isset($voucher_types[$vk])) {
            $filter_against_voucher_keys[] = $vk;
        }
    }
}
$filter_against_voucher_keys = array_values(array_unique($filter_against_voucher_keys));
$against_voucher_type = count($filter_against_voucher_keys) === 1 ? esc($filter_against_voucher_keys[0]) : '';

// Build WHERE clause (customer ledger list / balance tabs)
$where_clause = "l.status = 1";
$tt_conds = [];
if (!empty($ledger_types_sel)) {
    $parts = [];
    foreach ($ledger_types_sel as $lt) {
        $parts[] = "'" . esc($lt) . "'";
    }
    $tt_conds[] = 'l.transaction_type IN (' . implode(',', $parts) . ')';
}
if (!empty($filter_voucher_keys)) {
    $parts = [];
    foreach ($filter_voucher_keys as $vk) {
        $parts[] = "'" . esc($vk) . "'";
    }
    $tt_conds[] = 'l.transaction_type IN (' . implode(',', $parts) . ')';
}
if (count($tt_conds) === 1) {
    $where_clause .= ' AND ' . $tt_conds[0];
} elseif (count($tt_conds) === 2) {
    $where_clause .= ' AND (' . $tt_conds[0] . ' OR ' . $tt_conds[1] . ')';
}
if (!empty($from_date)) {
    $where_clause .= " AND l.transaction_date >= '$from_date'";
}
if (!empty($to_date)) {
    $where_clause .= " AND l.transaction_date <= '$to_date'";
}
if (!empty($invoice_no)) {
    $where_clause .= " AND l.transaction_no LIKE '%$invoice_no%'";
}
if (!empty($ledger_names_sel)) {
    $parts = [];
    foreach ($ledger_names_sel as $ln) {
        $parts[] = "'" . esc($ln) . "'";
    }
    $where_clause .= ' AND l.customer_name IN (' . implode(',', $parts) . ')';
}
if (!empty($against_inv_no)) {
    $where_clause .= " AND l.reference_no LIKE '%$against_inv_no%'";
}
if (!empty($search)) {
    $where_clause .= " AND (l.customer_name LIKE '%$search%' OR l.transaction_no LIKE '%$search%')";
}
// Branch filter fragment for unified voucher queries (only when column exists on that table).
// Always include NULL/0 branch_id so legacy rows without branch still appear.
$tr_branch_filter_sql = '';
if (!empty($tr_resolved_branch_ids)) {
    $ids = implode(',', array_map('intval', $tr_resolved_branch_ids));
    $tr_branch_filter_sql = " AND (branch_id IN ($ids) OR branch_id IS NULL OR branch_id = 0)";
}
if ($ledger_has_branch_id && !empty($tr_resolved_branch_ids)) {
    $ids = implode(',', array_map('intval', $tr_resolved_branch_ids));
    $where_clause .= " AND (l.branch_id IN ($ids) OR l.branch_id IS NULL OR l.branch_id = 0)";
}

// Transaction list types (for unified Transaction Report - like Jewelstep)
$transaction_list_types = [
    '' => 'All',
    'sale_invoice' => 'Sale Invoice',
    'sale_order' => 'Sale Order',
    'jobwork_order' => 'Job Work Order',
    'purchase_invoice' => 'Purchase Invoice',
    'sale_return' => 'Sale Return',
    'purchase_return' => 'Purchase Return',
    'sale_quotation' => 'Sale Quotation',
    'purchase_quotation' => 'Purchase Quotation',
    'material_issue' => 'Material Issue',
    'material_receive' => 'Material Receive',
    'sale_fixing_direct' => 'Sale Fixing Direct',
    'purchase_fixing_direct' => 'Purchase Fixing Direct',
    'old_jewelry_scrap_invoice' => 'Old Jewelry Scrap Invoice',
    'payment_voucher' => 'Payment Voucher',
    'receipt_voucher' => 'Receipt Voucher',
    'sale_receipt_voucher' => 'Sale Receipt Voucher',
    'advance_payment' => 'Advance Payment',
    'metal_to_amount' => 'Metal to Amount',
    'amount_to_metal' => 'Amount to Metal',
    'credit_note' => 'Credit Note',
    'debit_note' => 'Debit Note',
    'repair_invoice' => 'Repair Invoice',
    'repair_order' => 'Repair Order',
    'purchase_order' => 'Purchase Order',
    'consignment_in' => 'Consignment In',
    'consignment_out' => 'Consignment Out',
    'contra_voucher' => 'Contra Voucher',
    'journal_voucher' => 'Journal Voucher',
];

// Build unified transaction list (sale invoice, purchase invoice, sale return, purchase return, sale quotation, purchase quotation)
$all_transactions = [];
$transaction_voucher_filter = isset($_GET['transaction_voucher_type']) ? esc($_GET['transaction_voucher_type']) : '';

// Purchase Invoices
$pi_check = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_purchase_invoices'");
$pi_exists = $pi_check && mysqli_num_rows($pi_check) > 0;
if ($pi_check) mysqli_free_result($pi_check);
if ($pi_exists && ($transaction_voucher_filter === '' || $transaction_voucher_filter === 'purchase_invoice')) {
    $pi_has_br = auragold_tbl_has_column($conn, 'tbl_purchase_invoices', 'branch_id');
    $pi_where = "status != 'deleted'";
    if (!empty($from_date)) $pi_where .= " AND invoice_date >= '$from_date'";
    if (!empty($to_date)) $pi_where .= " AND invoice_date <= '$to_date'";
    if (!empty($search)) $pi_where .= " AND (invoice_no LIKE '%$search%' OR supplier_name LIKE '%$search%')";
    if ($pi_has_br && $tr_branch_filter_sql !== '') {
        $pi_where .= $tr_branch_filter_sql;
    }
    $pi_sel = 'id, invoice_no, supplier_name, invoice_date, grand_total, COALESCE(balance_amt, 0) as balance_amt, purchase_person, (SELECT TRIM(COALESCE(c.mail_id,\'\')) FROM tbl_customers c WHERE c.id = tbl_purchase_invoices.supplier_id AND (c.status IS NULL OR c.status = 1) LIMIT 1) AS party_email, (SELECT TRIM(COALESCE(NULLIF(TRIM(c.mobile_no),\'\'), NULLIF(TRIM(c.phone_no),\'\'), \'\')) FROM tbl_customers c WHERE c.id = tbl_purchase_invoices.supplier_id AND (c.status IS NULL OR c.status = 1) LIMIT 1) AS party_mobile';
    if ($pi_has_br) {
        $pi_sel .= ', branch_id';
    }
    $rows = getList("SELECT $pi_sel FROM tbl_purchase_invoices WHERE $pi_where ORDER BY invoice_date DESC, id DESC");
    foreach ($rows as $r) {
        $all_transactions[] = [
            'type' => 'purchase_invoice',
            'type_label' => 'PURCHASE INVOICE',
            'voucher_no' => $r['invoice_no'],
            'party_name' => $r['supplier_name'],
            'sales_person' => trim($r['purchase_person'] ?? ''),
            'date' => $r['invoice_date'],
            'amount' => (float)($r['grand_total'] ?? 0),
            'balance' => (float)($r['balance_amt'] ?? 0),
            'id' => (int)$r['id'],
            'link' => 'purchase-invoice.php?id=' . (int)$r['id'],
            'print_link' => 'purchase-invoice-print.php?id=' . (int)$r['id'],
            'branch_name' => $pi_has_br ? transaction_report_branch_label($tr_branch_name_by_id, $r['branch_id'] ?? 0) : '—',
            'party_email' => trim((string) ($r['party_email'] ?? '')),
            'party_mobile' => trim((string) ($r['party_mobile'] ?? '')),
        ];
    }
}

// Old Jewelry Scrap Invoices (includes auto-created OJB from purchase invoice scrap payment, ref_no = PI:{id})
$ojb_tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_scrap_invoices'");
$ojb_exists = $ojb_tbl && mysqli_num_rows($ojb_tbl) > 0;
if ($ojb_tbl) {
    mysqli_free_result($ojb_tbl);
}
if ($ojb_exists && ($transaction_voucher_filter === '' || $transaction_voucher_filter === 'old_jewelry_scrap_invoice')) {
    $ojb_has_br = auragold_tbl_has_column($conn, 'tbl_old_jewelry_scrap_invoices', 'branch_id');
    $ojb_where = "(oj.status IS NULL OR oj.status = '' OR LOWER(TRIM(oj.status)) NOT IN ('cancelled','deleted'))";
    if (!empty($from_date)) {
        $ojb_where .= " AND oj.invoice_date >= '$from_date'";
    }
    if (!empty($to_date)) {
        $ojb_where .= " AND oj.invoice_date <= '$to_date'";
    }
    if (!empty($search)) {
        $ojb_where .= " AND (oj.invoice_no LIKE '%$search%' OR oj.customer_name LIKE '%$search%' OR IFNULL(oj.against_of,'') LIKE '%$search%' OR IFNULL(oj.ref_no,'') LIKE '%$search%')";
    }
    if ($ojb_has_br && !empty($tr_resolved_branch_ids)) {
        $ids = implode(',', array_map('intval', $tr_resolved_branch_ids));
        if ($tr_scope_includes_main_legacy) {
            $ojb_where .= " AND (oj.branch_id IN ($ids) OR oj.branch_id IS NULL OR oj.branch_id = 0)";
        } else {
            $ojb_where .= " AND oj.branch_id IN ($ids)";
        }
    }
    // Linked OJB (ref_no PI:{id}): show scrap payment amount from purchase, not mistaken header copy of PI grand_total
    $pip_ojb_chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_purchase_invoice_payments'");
    $pip_ojb_exists = $pip_ojb_chk && mysqli_num_rows($pip_ojb_chk) > 0;
    if ($pip_ojb_chk) {
        mysqli_free_result($pip_ojb_chk);
    }
    $ojb_amt_expr = 'COALESCE(oj.grand_total, 0)';
    if ($pip_ojb_exists) {
        $ojb_amt_expr = "CASE WHEN oj.ref_no REGEXP '^PI:[0-9]+\$' THEN COALESCE((SELECT pip.amount FROM tbl_purchase_invoice_payments pip WHERE pip.invoice_id = CAST(SUBSTRING_INDEX(oj.ref_no, ':', -1) AS UNSIGNED) AND LOWER(TRIM(pip.payment_type)) = 'scrap' AND IFNULL(pip.status, 1) = 1 ORDER BY pip.id DESC LIMIT 1), COALESCE(oj.grand_total, 0)) ELSE COALESCE(oj.grand_total, 0) END";
    }
    $ojb_sel_extra = $ojb_has_br ? ', oj.branch_id' : '';
    $ojb_rows = getList("SELECT oj.id, oj.invoice_no, oj.customer_name, oj.invoice_date, ($ojb_amt_expr) AS grand_total, COALESCE(oj.balance_amt, oj.grand_total, 0) AS balance_amt, oj.against_of, oj.ref_no, oj.sales_person$ojb_sel_extra FROM tbl_old_jewelry_scrap_invoices oj WHERE $ojb_where ORDER BY oj.invoice_date DESC, oj.id DESC");
    if (is_array($ojb_rows)) {
        foreach ($ojb_rows as $r) {
            $against_pi = trim((string) ($r['against_of'] ?? ''));
            $ref_raw = trim((string) ($r['ref_no'] ?? ''));
            if ($against_pi === '' && $ref_raw !== '' && preg_match('/^PI:(\d+)$/i', $ref_raw, $m)) {
                $pid = (int) $m[1];
                if ($pid > 0) {
                    $pi_row = getRecord("SELECT invoice_no FROM tbl_purchase_invoices WHERE id = $pid LIMIT 1");
                    if ($pi_row && trim((string) ($pi_row['invoice_no'] ?? '')) !== '') {
                        $against_pi = trim((string) $pi_row['invoice_no']);
                    }
                }
            }
            $ref_display = 'NA';
            if ($ref_raw !== '' && !preg_match('/^PI:\d+$/i', $ref_raw)) {
                $ref_display = $ref_raw;
            } elseif ($against_pi !== '') {
                $ref_display = $against_pi;
            }
            $linked_from_purchase = (bool) preg_match('/^PI:\d+$/i', $ref_raw);
            $all_transactions[] = [
                'type' => 'old_jewelry_scrap_invoice',
                'type_label' => 'OLD JEWELRY SCRAP INVOICE',
                'voucher_no' => $r['invoice_no'],
                'against_pi' => $against_pi,
                'linked_from_purchase' => $linked_from_purchase,
                'party_name' => $r['customer_name'],
                'sales_person' => trim($r['sales_person'] ?? ''),
                'date' => $r['invoice_date'],
                'amount' => (float) ($r['grand_total'] ?? 0),
                'balance' => (float) ($r['balance_amt'] ?? 0),
                'id' => (int) $r['id'],
                'link' => 'old-jewelry-scrap-invoice.php?id=' . (int) $r['id'],
                'print_link' => 'old-jewelry-scrap-invoice-print.php?id=' . (int) $r['id'],
                'tx_ref_display' => $ref_display,
                'branch_name' => $ojb_has_br ? transaction_report_branch_label($tr_branch_name_by_id, $r['branch_id'] ?? 0) : '—',
            ];
        }
    }
}

// Sale Invoices
$si_check = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_invoices'");
$si_exists = $si_check && mysqli_num_rows($si_check) > 0;
if ($si_check) mysqli_free_result($si_check);
if ($si_exists && ($transaction_voucher_filter === '' || $transaction_voucher_filter === 'sale_invoice')) {
    $si_has_br = auragold_tbl_has_column($conn, 'tbl_sale_invoices', 'branch_id');
    $si_where = "status != 'deleted'";
    if (!empty($from_date)) $si_where .= " AND invoice_date >= '$from_date'";
    if (!empty($to_date)) $si_where .= " AND invoice_date <= '$to_date'";
    if (!empty($search)) $si_where .= " AND (invoice_no LIKE '%$search%' OR customer_name LIKE '%$search%')";
    if ($si_has_br && $tr_branch_filter_sql !== '') {
        $si_where .= $tr_branch_filter_sql;
    }
    $si_sel = 'id, invoice_no, customer_name, invoice_date, grand_total, COALESCE(balance_amt, 0) as balance_amt, sales_person, (SELECT TRIM(COALESCE(c.mail_id,\'\')) FROM tbl_customers c WHERE c.id = tbl_sale_invoices.customer_id AND (c.status IS NULL OR c.status = 1) LIMIT 1) AS party_email, (SELECT TRIM(COALESCE(NULLIF(TRIM(c.mobile_no),\'\'), NULLIF(TRIM(c.phone_no),\'\'), \'\')) FROM tbl_customers c WHERE c.id = tbl_sale_invoices.customer_id AND (c.status IS NULL OR c.status = 1) LIMIT 1) AS party_mobile';
    if ($si_has_br) {
        $si_sel .= ', branch_id';
    }
    $rows = getList("SELECT $si_sel FROM tbl_sale_invoices WHERE $si_where ORDER BY invoice_date DESC, id DESC");
    foreach ($rows as $r) {
        $all_transactions[] = [
            'type' => 'sale_invoice',
            'type_label' => 'SALE INVOICE',
            'voucher_no' => $r['invoice_no'],
            'party_name' => $r['customer_name'],
            'sales_person' => trim($r['sales_person'] ?? ''),
            'date' => $r['invoice_date'],
            'amount' => (float)($r['grand_total'] ?? 0),
            'balance' => (float)($r['balance_amt'] ?? 0),
            'id' => (int)$r['id'],
            'link' => 'sale-invoice.php?id=' . (int)$r['id'],
            'print_link' => 'sale-invoice-print.php?id=' . (int)$r['id'],
            'branch_name' => $si_has_br ? transaction_report_branch_label($tr_branch_name_by_id, $r['branch_id'] ?? 0) : '—',
            'party_email' => trim((string) ($r['party_email'] ?? '')),
            'party_mobile' => trim((string) ($r['party_mobile'] ?? '')),
        ];
    }
}

// Sale Orders
$so_check = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_orders'");
$so_exists = $so_check && mysqli_num_rows($so_check) > 0;
if ($so_check) mysqli_free_result($so_check);
if ($so_exists && ($transaction_voucher_filter === '' || $transaction_voucher_filter === 'sale_order')) {
    $so_has_br = auragold_tbl_has_column($conn, 'tbl_sale_orders', 'branch_id');
    $so_where = "(status != 'deleted' OR status IS NULL)";
    if (!empty($from_date)) $so_where .= " AND order_date >= '$from_date'";
    if (!empty($to_date)) $so_where .= " AND order_date <= '$to_date'";
    if (!empty($search)) $so_where .= " AND (order_no LIKE '%$search%' OR customer_name LIKE '%$search%')";
    if ($so_has_br && $tr_branch_filter_sql !== '') {
        $so_where .= $tr_branch_filter_sql;
    }
    $so_sel = 'id, order_no, customer_name, order_date, COALESCE(NULLIF(grand_total, 0), NULLIF(net_total, 0), NULLIF(subtotal, 0), 0) AS grand_total, COALESCE(balance_amt, 0) as balance_amt, sales_person, (SELECT TRIM(COALESCE(c.mail_id,\'\')) FROM tbl_customers c WHERE c.id = tbl_sale_orders.customer_id AND (c.status IS NULL OR c.status = 1) LIMIT 1) AS party_email, (SELECT TRIM(COALESCE(NULLIF(TRIM(c.mobile_no),\'\'), NULLIF(TRIM(c.phone_no),\'\'), \'\')) FROM tbl_customers c WHERE c.id = tbl_sale_orders.customer_id AND (c.status IS NULL OR c.status = 1) LIMIT 1) AS party_mobile';
    if ($so_has_br) {
        $so_sel .= ', branch_id';
    }
    $rows = getList("SELECT $so_sel FROM tbl_sale_orders WHERE $so_where ORDER BY order_date DESC, id DESC");
    foreach ($rows as $r) {
        $all_transactions[] = [
            'type' => 'sale_order',
            'type_label' => 'SALE ORDER',
            'voucher_no' => $r['order_no'],
            'party_name' => $r['customer_name'],
            'sales_person' => trim($r['sales_person'] ?? ''),
            'date' => $r['order_date'],
            'amount' => (float)($r['grand_total'] ?? 0),
            'balance' => (float)($r['balance_amt'] ?? 0),
            'id' => (int)$r['id'],
            'link' => 'sale-order.php?id=' . (int)$r['id'],
            'print_link' => 'sale-order-print.php?id=' . (int)$r['id'],
            'branch_name' => $so_has_br ? transaction_report_branch_label($tr_branch_name_by_id, $r['branch_id'] ?? 0) : '—',
            'party_email' => trim((string) ($r['party_email'] ?? '')),
            'party_mobile' => trim((string) ($r['party_mobile'] ?? '')),
        ];
    }
}

// Job Work Orders (against Sale Order)
$jwo_tr_check = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_orders'");
$jwo_tr_exists = $jwo_tr_check && mysqli_num_rows($jwo_tr_check) > 0;
if ($jwo_tr_check) {
    mysqli_free_result($jwo_tr_check);
}
if ($jwo_tr_exists && ($transaction_voucher_filter === '' || $transaction_voucher_filter === 'jobwork_order')) {
    $jwo_tr_has_br = auragold_tbl_has_column($conn, 'tbl_jobwork_orders', 'branch_id');
    $jwo_tr_where = "(status IS NULL OR status = '' OR LOWER(TRIM(status)) NOT IN ('deleted','cancelled'))";
    if (!empty($from_date)) {
        $jwo_tr_where .= " AND order_date >= '$from_date'";
    }
    if (!empty($to_date)) {
        $jwo_tr_where .= " AND order_date <= '$to_date'";
    }
    if (!empty($search)) {
        $jwo_tr_where .= " AND (jobwork_no LIKE '%$search%' OR customer_name LIKE '%$search%' OR sale_order_no LIKE '%$search%')";
    }
    if ($jwo_tr_has_br && $tr_branch_filter_sql !== '') {
        $jwo_tr_where .= $tr_branch_filter_sql;
    }
    $jwo_tr_sel = 'id, jobwork_no, sale_order_id, sale_order_no, customer_name, order_date, grand_total, status';
    if ($jwo_tr_has_br) {
        $jwo_tr_sel .= ', branch_id';
    }
    $jwo_tr_rows = getList("SELECT $jwo_tr_sel FROM tbl_jobwork_orders WHERE $jwo_tr_where ORDER BY order_date DESC, id DESC");
    if (is_array($jwo_tr_rows)) {
        foreach ($jwo_tr_rows as $r) {
            $sale_order_no = trim((string) ($r['sale_order_no'] ?? ''));
            $sale_order_id = (int) ($r['sale_order_id'] ?? 0);
            $so_link = $sale_order_id > 0 ? ('sale-order.php?id=' . $sale_order_id) : '';
            $all_transactions[] = [
                'type' => 'jobwork_order',
                'type_label' => 'JOB WORK ORDER',
                'voucher_no' => $r['jobwork_no'] ?? '',
                'party_name' => $r['customer_name'] ?? '',
                'sales_person' => 'NA',
                'date' => $r['order_date'] ?? '',
                'amount' => (float) ($r['grand_total'] ?? 0),
                'balance' => 0,
                'id' => (int) ($r['id'] ?? 0),
                'link' => 'jobwork-order.php?id=' . (int) ($r['id'] ?? 0),
                'print_link' => 'jobwork-order-print.php?id=' . (int) ($r['id'] ?? 0),
                'branch_name' => $jwo_tr_has_br ? transaction_report_branch_label($tr_branch_name_by_id, $r['branch_id'] ?? 0) : '—',
                'against_sale_order_no' => $sale_order_no,
                'against_sale_order_id' => $sale_order_id,
                'against_sale_order_link' => $so_link,
                'voucher_ex_col3' => $sale_order_no !== '' ? ('Against ' . $sale_order_no) : 'NA',
            ];
        }
    }
}

// Sale Returns
$sr_check = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_returns'");
$sr_exists = $sr_check && mysqli_num_rows($sr_check) > 0;
if ($sr_check) mysqli_free_result($sr_check);
if ($sr_exists && ($transaction_voucher_filter === '' || $transaction_voucher_filter === 'sale_return')) {
    $sr_has_br = auragold_tbl_has_column($conn, 'tbl_sale_returns', 'branch_id');
    $sr_where = "status != 'deleted'";
    if (!empty($from_date)) $sr_where .= " AND return_date >= '$from_date'";
    if (!empty($to_date)) $sr_where .= " AND return_date <= '$to_date'";
    if (!empty($search)) $sr_where .= " AND (return_no LIKE '%$search%' OR customer_name LIKE '%$search%')";
    if ($sr_has_br && $tr_branch_filter_sql !== '') {
        $sr_where .= $tr_branch_filter_sql;
    }
    $sr_sel = 'id, return_no, customer_name, return_date, grand_total, sales_person, (SELECT TRIM(COALESCE(c.mail_id,\'\')) FROM tbl_customers c WHERE c.id = tbl_sale_returns.customer_id AND (c.status IS NULL OR c.status = 1) LIMIT 1) AS party_email';
    if ($sr_has_br) {
        $sr_sel .= ', branch_id';
    }
    $rows = getList("SELECT $sr_sel FROM tbl_sale_returns WHERE $sr_where ORDER BY return_date DESC, id DESC");
    foreach ($rows as $r) {
        $all_transactions[] = [
            'type' => 'sale_return',
            'type_label' => 'SALE RETURN',
            'voucher_no' => $r['return_no'],
            'party_name' => $r['customer_name'],
            'sales_person' => trim($r['sales_person'] ?? ''),
            'date' => $r['return_date'],
            'amount' => (float)($r['grand_total'] ?? 0),
            'balance' => 0,
            'id' => (int)$r['id'],
            'link' => 'sale-return.php?id=' . (int)$r['id'],
            'print_link' => 'sale-return.php?id=' . (int)$r['id'] . '&print=1',
            'branch_name' => $sr_has_br ? transaction_report_branch_label($tr_branch_name_by_id, $r['branch_id'] ?? 0) : '—',
            'party_email' => trim((string) ($r['party_email'] ?? '')),
        ];
    }
}

// Purchase Returns
$pr_check = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_purchase_returns'");
$pr_exists = $pr_check && mysqli_num_rows($pr_check) > 0;
if ($pr_check) mysqli_free_result($pr_check);
if ($pr_exists && ($transaction_voucher_filter === '' || $transaction_voucher_filter === 'purchase_return')) {
    $pr_has_br = auragold_tbl_has_column($conn, 'tbl_purchase_returns', 'branch_id');
    $pr_where = "status != 'deleted'";
    if (!empty($from_date)) $pr_where .= " AND return_date >= '$from_date'";
    if (!empty($to_date)) $pr_where .= " AND return_date <= '$to_date'";
    if (!empty($search)) $pr_where .= " AND (return_no LIKE '%$search%' OR supplier_name LIKE '%$search%')";
    if ($pr_has_br && $tr_branch_filter_sql !== '') {
        $pr_where .= $tr_branch_filter_sql;
    }
    $pr_sel = 'id, return_no, supplier_name, return_date, grand_total, COALESCE(balance_amt, 0) as balance_amt, sales_person, (SELECT TRIM(COALESCE(c.mail_id,\'\')) FROM tbl_customers c WHERE c.id = tbl_purchase_returns.supplier_id AND (c.status IS NULL OR c.status = 1) LIMIT 1) AS party_email';
    if ($pr_has_br) {
        $pr_sel .= ', branch_id';
    }
    $rows = getList("SELECT $pr_sel FROM tbl_purchase_returns WHERE $pr_where ORDER BY return_date DESC, id DESC");
    foreach ($rows as $r) {
        $all_transactions[] = [
            'type' => 'purchase_return',
            'type_label' => 'PURCHASE RETURN',
            'voucher_no' => $r['return_no'],
            'party_name' => $r['supplier_name'],
            'sales_person' => trim($r['sales_person'] ?? ''),
            'date' => $r['return_date'],
            'amount' => (float)($r['grand_total'] ?? 0),
            'balance' => (float)($r['balance_amt'] ?? 0),
            'id' => (int)$r['id'],
            'link' => 'purchase-return.php?id=' . (int)$r['id'],
            'print_link' => 'purchase-return.php?id=' . (int)$r['id'] . '&print=1',
            'branch_name' => $pr_has_br ? transaction_report_branch_label($tr_branch_name_by_id, $r['branch_id'] ?? 0) : '—',
            'party_email' => trim((string) ($r['party_email'] ?? '')),
        ];
    }
}

// Sale Quotations
$sq_check = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_quotations'");
$sq_exists = $sq_check && mysqli_num_rows($sq_check) > 0;
if ($sq_check) mysqli_free_result($sq_check);
if ($sq_exists && ($transaction_voucher_filter === '' || $transaction_voucher_filter === 'sale_quotation')) {
    $sq_has_br = auragold_tbl_has_column($conn, 'tbl_sale_quotations', 'branch_id');
    $sq_where = "status != 'deleted'";
    if (!empty($from_date)) $sq_where .= " AND quotation_date >= '$from_date'";
    if (!empty($to_date)) $sq_where .= " AND quotation_date <= '$to_date'";
    if (!empty($search)) $sq_where .= " AND (quotation_no LIKE '%$search%' OR customer_name LIKE '%$search%')";
    if ($sq_has_br && $tr_branch_filter_sql !== '') {
        $sq_where .= $tr_branch_filter_sql;
    }
    $sq_amt_expr = transaction_report_coalesce_amount_sql($conn, 'tbl_sale_quotations', 'tbl_sale_quotation_items', 'quotation_id');
    $sq_bal_sel = auragold_tbl_has_column($conn, 'tbl_sale_quotations', 'balance_amt')
        ? 'COALESCE(balance_amt, 0) AS balance_amt'
        : '0 AS balance_amt';
    $sq_sel = "id, quotation_no, customer_name, quotation_date, ($sq_amt_expr) AS grand_total, $sq_bal_sel, sales_person, (SELECT TRIM(COALESCE(c.mail_id,'')) FROM tbl_customers c WHERE c.id = tbl_sale_quotations.customer_id AND (c.status IS NULL OR c.status = 1) LIMIT 1) AS party_email";
    if ($sq_has_br) {
        $sq_sel .= ', branch_id';
    }
    $rows = getList("SELECT $sq_sel FROM tbl_sale_quotations WHERE $sq_where ORDER BY quotation_date DESC, id DESC");
    foreach ($rows as $r) {
        $all_transactions[] = [
            'type' => 'sale_quotation',
            'type_label' => 'SALE QUOTATION',
            'voucher_no' => $r['quotation_no'],
            'party_name' => $r['customer_name'],
            'sales_person' => trim($r['sales_person'] ?? ''),
            'date' => $r['quotation_date'],
            'amount' => (float)($r['grand_total'] ?? 0),
            'balance' => (float)($r['balance_amt'] ?? 0),
            'id' => (int)$r['id'],
            'link' => 'sale-quotations.php?id=' . (int)$r['id'],
            'print_link' => 'sale-quotations.php?id=' . (int)$r['id'] . '&print=1',
            'branch_name' => $sq_has_br ? transaction_report_branch_label($tr_branch_name_by_id, $r['branch_id'] ?? 0) : '—',
            'party_email' => trim((string) ($r['party_email'] ?? '')),
        ];
    }
}

// Purchase Quotations
$pq_check = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_purchase_quotations'");
$pq_exists = $pq_check && mysqli_num_rows($pq_check) > 0;
if ($pq_check) mysqli_free_result($pq_check);
if ($pq_exists && ($transaction_voucher_filter === '' || $transaction_voucher_filter === 'purchase_quotation')) {
    $pq_has_br = auragold_tbl_has_column($conn, 'tbl_purchase_quotations', 'branch_id');
    $pq_where = "status != 'deleted'";
    if (!empty($from_date)) $pq_where .= " AND quotation_date >= '$from_date'";
    if (!empty($to_date)) $pq_where .= " AND quotation_date <= '$to_date'";
    if (!empty($search)) $pq_where .= " AND (quotation_no LIKE '%$search%' OR supplier_name LIKE '%$search%')";
    if ($pq_has_br && $tr_branch_filter_sql !== '') {
        $pq_where .= $tr_branch_filter_sql;
    }
    $pq_amt_expr = transaction_report_coalesce_amount_sql($conn, 'tbl_purchase_quotations', 'tbl_purchase_quotation_items', 'quotation_id');
    $pq_bal_sel = auragold_tbl_has_column($conn, 'tbl_purchase_quotations', 'balance_amt')
        ? 'COALESCE(balance_amt, 0) AS balance_amt'
        : '0 AS balance_amt';
    $pq_sel = "id, quotation_no, supplier_name, quotation_date, ($pq_amt_expr) AS grand_total, $pq_bal_sel, purchase_person, (SELECT TRIM(COALESCE(c.mail_id,'')) FROM tbl_customers c WHERE c.id = tbl_purchase_quotations.supplier_id AND (c.status IS NULL OR c.status = 1) LIMIT 1) AS party_email";
    if ($pq_has_br) {
        $pq_sel .= ', branch_id';
    }
    $rows = getList("SELECT $pq_sel FROM tbl_purchase_quotations WHERE $pq_where ORDER BY quotation_date DESC, id DESC");
    foreach ($rows as $r) {
        $all_transactions[] = [
            'type' => 'purchase_quotation',
            'type_label' => 'PURCHASE QUOTATION',
            'voucher_no' => $r['quotation_no'],
            'party_name' => $r['supplier_name'],
            'sales_person' => trim($r['purchase_person'] ?? ''),
            'date' => $r['quotation_date'],
            'amount' => (float)($r['grand_total'] ?? 0),
            'balance' => (float)($r['balance_amt'] ?? 0),
            'id' => (int)$r['id'],
            'link' => 'purchase-quotation.php?id=' . (int)$r['id'],
            'print_link' => 'purchase-quotation.php?id=' . (int)$r['id'] . '&print=1',
            'branch_name' => $pq_has_br ? transaction_report_branch_label($tr_branch_name_by_id, $r['branch_id'] ?? 0) : '—',
            'party_email' => trim((string) ($r['party_email'] ?? '')),
        ];
    }
}

// Material Issue (tbl_material_issues — stock/jobwork; no customer ledger posting)
$mi_tr_check = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_material_issues'");
$mi_tr_exists = $mi_tr_check && mysqli_num_rows($mi_tr_check) > 0;
if ($mi_tr_check) {
    mysqli_free_result($mi_tr_check);
}
if ($mi_tr_exists && ($transaction_voucher_filter === '' || $transaction_voucher_filter === 'material_issue')) {
    $mi_tr_has_br = auragold_tbl_has_column($conn, 'tbl_material_issues', 'branch_id');
    $mi_tr_where = "(status IS NULL OR status = '' OR LOWER(TRIM(status)) NOT IN ('deleted','cancelled'))";
    if (!empty($from_date)) {
        $mi_tr_where .= " AND order_date >= '$from_date'";
    }
    if (!empty($to_date)) {
        $mi_tr_where .= " AND order_date <= '$to_date'";
    }
    if (!empty($search)) {
        $mi_tr_where .= " AND (material_issue_no LIKE '%$search%' OR customer_name LIKE '%$search%' OR sale_order_no LIKE '%$search%')";
    }
    if ($mi_tr_has_br && $tr_branch_filter_sql !== '') {
        $mi_tr_where .= $tr_branch_filter_sql;
    }
    $mi_tr_sel = 'id, material_issue_no, customer_name, order_date, grand_total, status, sale_order_no';
    if ($mi_tr_has_br) {
        $mi_tr_sel .= ', branch_id';
    }
    $mi_tr_rows = getList("SELECT $mi_tr_sel FROM tbl_material_issues WHERE $mi_tr_where ORDER BY order_date DESC, id DESC");
    if (is_array($mi_tr_rows)) {
        foreach ($mi_tr_rows as $r) {
            $all_transactions[] = [
                'type' => 'material_issue',
                'type_label' => 'MATERIAL ISSUE',
                'voucher_no' => $r['material_issue_no'] ?? '',
                'party_name' => $r['customer_name'] ?? '',
                'sales_person' => 'NA',
                'date' => $r['order_date'] ?? '',
                'amount' => (float) ($r['grand_total'] ?? 0),
                'balance' => 0,
                'id' => (int) ($r['id'] ?? 0),
                'link' => 'material-issue.php?id=' . (int) ($r['id'] ?? 0),
                'print_link' => 'material-issue-print.php?id=' . (int) ($r['id'] ?? 0),
                'branch_name' => $mi_tr_has_br ? transaction_report_branch_label($tr_branch_name_by_id, $r['branch_id'] ?? 0) : '—',
            ];
        }
    }
}

// Material Receive (tbl_material_receives)
$mr_tr_check = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_material_receives'");
$mr_tr_exists = $mr_tr_check && mysqli_num_rows($mr_tr_check) > 0;
if ($mr_tr_check) {
    mysqli_free_result($mr_tr_check);
}
if ($mr_tr_exists && ($transaction_voucher_filter === '' || $transaction_voucher_filter === 'material_receive')) {
    $mr_tr_has_br = auragold_tbl_has_column($conn, 'tbl_material_receives', 'branch_id');
    $mr_tr_where = "(status IS NULL OR status = '' OR LOWER(TRIM(status)) NOT IN ('deleted','cancelled'))";
    if (!empty($from_date)) {
        $mr_tr_where .= " AND order_date >= '$from_date'";
    }
    if (!empty($to_date)) {
        $mr_tr_where .= " AND order_date <= '$to_date'";
    }
    if (!empty($search)) {
        $mr_tr_where .= " AND (material_receive_no LIKE '%$search%' OR customer_name LIKE '%$search%' OR sale_order_no LIKE '%$search%')";
    }
    if ($mr_tr_has_br && $tr_branch_filter_sql !== '') {
        $mr_tr_where .= $tr_branch_filter_sql;
    }
    $mr_tr_sel = 'id, material_receive_no, customer_name, order_date, grand_total, status, sale_order_no';
    if ($mr_tr_has_br) {
        $mr_tr_sel .= ', branch_id';
    }
    $mr_tr_rows = getList("SELECT $mr_tr_sel FROM tbl_material_receives WHERE $mr_tr_where ORDER BY order_date DESC, id DESC");
    if (is_array($mr_tr_rows)) {
        foreach ($mr_tr_rows as $r) {
            $all_transactions[] = [
                'type' => 'material_receive',
                'type_label' => 'MATERIAL RECEIVE',
                'voucher_no' => $r['material_receive_no'] ?? '',
                'party_name' => $r['customer_name'] ?? '',
                'sales_person' => 'NA',
                'date' => $r['order_date'] ?? '',
                'amount' => (float) ($r['grand_total'] ?? 0),
                'balance' => 0,
                'id' => (int) ($r['id'] ?? 0),
                'link' => 'material-receive.php?id=' . (int) ($r['id'] ?? 0),
                'print_link' => 'material-receive-print.php?id=' . (int) ($r['id'] ?? 0),
                'branch_name' => $mr_tr_has_br ? transaction_report_branch_label($tr_branch_name_by_id, $r['branch_id'] ?? 0) : '—',
            ];
        }
    }
}

// Sale Fixing Direct (tbl_sale_fixing_direct)
$sfd_check = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_fixing_direct'");
$sfd_exists = $sfd_check && mysqli_num_rows($sfd_check) > 0;
if ($sfd_check) mysqli_free_result($sfd_check);
if ($sfd_exists && ($transaction_voucher_filter === '' || $transaction_voucher_filter === 'sale_fixing_direct')) {
    $sfd_has_br = auragold_tbl_has_column($conn, 'tbl_sale_fixing_direct', 'branch_id');
    $sfd_where = "1=1";
    $sf_status_chk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_fixing_direct LIKE 'status'");
    if ($sf_status_chk && mysqli_num_rows($sf_status_chk) > 0) {
        $sfd_where .= " AND (status IS NULL OR LOWER(TRIM(status)) <> 'deleted')";
    }
    if ($sf_status_chk) {
        mysqli_free_result($sf_status_chk);
    }
    if (!empty($from_date)) $sfd_where .= " AND (fixing_date >= '$from_date' OR created_at >= '$from_date 00:00:00')";
    if (!empty($to_date)) $sfd_where .= " AND (fixing_date <= '$to_date' OR created_at <= '$to_date 23:59:59')";
    if (!empty($search)) $sfd_where .= " AND (ref_no LIKE '%$search%' OR customer_name LIKE '%$search%' OR against_of LIKE '%$search%')";
    if ($sfd_has_br && $tr_branch_filter_sql !== '') {
        $sfd_where .= $tr_branch_filter_sql;
    }
    $sfd_sel = 'id, ref_no, customer_name, fixing_date, created_at, total_amount, currency, fixing_type, against_of, sales_person';
    if ($sfd_has_br) {
        $sfd_sel .= ', branch_id';
    }
    $rows = getList("SELECT $sfd_sel FROM tbl_sale_fixing_direct WHERE $sfd_where ORDER BY COALESCE(fixing_date, created_at) DESC, id DESC");
    foreach ($rows as $r) {
        $sfd_date = $r['fixing_date'] ?? $r['created_at'] ?? '';
        if ($sfd_date && strlen($sfd_date) > 10) $sfd_date = substr($sfd_date, 0, 10);
        $against_of = isset($r['against_of']) ? trim($r['against_of']) : '';
        // Extract PI reference (e.g. "Fixing of PI-6" or "PI-6" -> "PI-6") so we can show SFD on top of that PI
        $against_pi = '';
        if (preg_match('/\b(PI-\d+)\b/i', $against_of, $m)) {
            $against_pi = $m[1];
        } elseif (preg_match('/\b(PRI\d+)\b/i', $against_of, $m)) {
            $against_pi = $m[1];
        }
        // Linked PI: grand_total (metal value) + purchase_person when SFD row has no sales_person
        $metal_value = null;
        $sfd_sp = trim($r['sales_person'] ?? '');
        if ($against_pi !== '' && $pi_exists) {
            $pi_esc = mysqli_real_escape_string($conn, trim($against_pi));
            $pi_row = getRecord("SELECT grand_total, purchase_person FROM tbl_purchase_invoices WHERE invoice_no = '" . $pi_esc . "' AND (status IS NULL OR status != 'deleted') LIMIT 1");
            if (!$pi_row) {
                $pi_row = getRecord("SELECT grand_total, purchase_person FROM tbl_purchase_invoices WHERE LOWER(TRIM(invoice_no)) = LOWER('" . $pi_esc . "') AND (status IS NULL OR status != 'deleted') LIMIT 1");
            }
            if ($pi_row) {
                $metal_value = (float)($pi_row['grand_total'] ?? 0);
                if ($sfd_sp === '' && trim((string)($pi_row['purchase_person'] ?? '')) !== '') {
                    $sfd_sp = trim($pi_row['purchase_person']);
                }
            }
        }
        $all_transactions[] = [
            'type' => 'sale_fixing_direct',
            'type_label' => 'SALE FIXING DIRECT',
            'voucher_no' => $r['ref_no'] ?? ('SFD-' . (int)$r['id']),
            'party_name' => $r['customer_name'] ?? '',
            'sales_person' => $sfd_sp,
            'date' => $sfd_date,
            'amount' => (float)($r['total_amount'] ?? 0),
            'metal_value' => $metal_value,
            'balance' => 0,
            'id' => (int)$r['id'],
            'link' => 'sale-fixing-direct.php?id=' . (int)$r['id'],
            'print_link' => 'sale-fixing-print.php?id=' . (int)$r['id'],
            'against_of' => $against_of,
            'against_pi' => $against_pi,
            'branch_name' => $sfd_has_br ? transaction_report_branch_label($tr_branch_name_by_id, $r['branch_id'] ?? 0) : '—',
        ];
    }
}

// Purchase Fixing Direct (tbl_purchase_fixing_direct) — created when Sale Invoice fixing type = Hedging
$pfd_check = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_purchase_fixing_direct'");
$pfd_exists = $pfd_check && mysqli_num_rows($pfd_check) > 0;
if ($pfd_check) {
    mysqli_free_result($pfd_check);
}
if ($pfd_exists && ($transaction_voucher_filter === '' || $transaction_voucher_filter === 'purchase_fixing_direct')) {
    $pfd_where = "1=1";
    $pfd_has_br = auragold_tbl_has_column($conn, 'tbl_purchase_fixing_direct', 'branch_id');
    $pfd_has_sale_si = false;
    $pf_si_col_chk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_fixing_direct LIKE 'sale_invoice_no'");
    if ($pf_si_col_chk && mysqli_num_rows($pf_si_col_chk) > 0) {
        $pfd_has_sale_si = true;
    }
    if ($pf_si_col_chk) {
        mysqli_free_result($pf_si_col_chk);
    }
    $pf_status_chk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_fixing_direct LIKE 'status'");
    if ($pf_status_chk && mysqli_num_rows($pf_status_chk) > 0) {
        $pfd_where .= " AND (status IS NULL OR LOWER(TRIM(status)) <> 'deleted')";
    }
    if ($pf_status_chk) {
        mysqli_free_result($pf_status_chk);
    }
    if (!empty($from_date)) {
        $pfd_where .= " AND (invoice_date >= '$from_date' OR fixing_date >= '$from_date' OR created_at >= '$from_date 00:00:00')";
    }
    if (!empty($to_date)) {
        $pfd_where .= " AND (invoice_date <= '$to_date' OR fixing_date <= '$to_date' OR created_at <= '$to_date 23:59:59')";
    }
    if (!empty($search)) {
        $pfd_where .= " AND (invoice_no LIKE '%$search%' OR ref_no LIKE '%$search%' OR customer_name LIKE '%$search%' OR supplier_name LIKE '%$search%' OR against_of LIKE '%$search%'";
        if ($pfd_has_sale_si) {
            $pfd_where .= " OR sale_invoice_no LIKE '%$search%'";
        }
        $pfd_where .= ")";
    }
    if ($pfd_has_br && $tr_branch_filter_sql !== '') {
        $pfd_where .= $tr_branch_filter_sql;
    }
    $rows = getList("SELECT * FROM tbl_purchase_fixing_direct WHERE $pfd_where ORDER BY COALESCE(fixing_date, invoice_date, created_at) DESC, id DESC");
    foreach ($rows as $r) {
        $pfd_date = $r['fixing_date'] ?? $r['invoice_date'] ?? $r['created_at'] ?? '';
        if ($pfd_date && strlen($pfd_date) > 10) {
            $pfd_date = substr($pfd_date, 0, 10);
        }
        $against_of = isset($r['against_of']) ? trim($r['against_of']) : '';
        $against_si = '';
        if ($against_of !== '' && preg_match('/Fixing of\s+(\S+)/i', $against_of, $m)) {
            $against_si = trim($m[1]);
        }
        $sale_inv_no = trim((string)($r['sale_invoice_no'] ?? ''));
        if ($against_si === '' && $sale_inv_no !== '') {
            $against_si = $sale_inv_no;
        }
        $voucher_ref = trim((string)($r['invoice_no'] ?? $r['ref_no'] ?? ''));
        if ($voucher_ref === '') {
            $voucher_ref = 'PFD-' . (int)$r['id'];
        }
        $party = trim((string)($r['customer_name'] ?? $r['supplier_name'] ?? ''));
        $amt = (float)($r['grand_total'] ?? $r['total_amount'] ?? $r['net_total'] ?? $r['subtotal'] ?? 0);
        $si_link_id = 0;
        if ($sale_inv_no !== '' && $si_exists) {
            $sn_esc = mysqli_real_escape_string($conn, $sale_inv_no);
            $si_row = getRecord("SELECT id FROM tbl_sale_invoices WHERE invoice_no = '$sn_esc' AND (status IS NULL OR status != 'deleted') LIMIT 1");
            if (!$si_row) {
                $si_row = getRecord("SELECT id FROM tbl_sale_invoices WHERE LOWER(TRIM(invoice_no)) = LOWER('$sn_esc') AND (status IS NULL OR status != 'deleted') LIMIT 1");
            }
            if ($si_row) {
                $si_link_id = (int)$si_row['id'];
            }
        }
        $si_link = $si_link_id > 0 ? ('sale-invoice.php?id=' . $si_link_id) : 'sale-invoice.php';
        $si_print = $si_link_id > 0 ? ('sale-invoice-print.php?id=' . $si_link_id) : 'sale-invoice-print.php';
        $all_transactions[] = [
            'type' => 'purchase_fixing_direct',
            'type_label' => 'PURCHASE FIXING DIRECT',
            'voucher_no' => $voucher_ref,
            'party_name' => $party,
            'sales_person' => trim((string)($r['sales_person'] ?? $r['purchase_person'] ?? '')),
            'date' => $pfd_date,
            'amount' => $amt,
            'balance' => 0,
            'id' => (int)$r['id'],
            'link' => $si_link,
            'print_link' => $si_print,
            'against_of' => $against_of,
            'against_si' => $against_si,
            'branch_name' => $pfd_has_br ? transaction_report_branch_label($tr_branch_name_by_id, $r['branch_id'] ?? 0) : '—',
        ];
    }
}

// Payment Vouchers
$pv_list_check = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_payment_vouchers'");
$pv_list_exists = $pv_list_check && mysqli_num_rows($pv_list_check) > 0;
if ($pv_list_check) {
    mysqli_free_result($pv_list_check);
}
if ($pv_list_exists && ($transaction_voucher_filter === '' || $transaction_voucher_filter === 'payment_voucher')) {
    $pv_l_has_br = auragold_tbl_has_column($conn, 'tbl_payment_vouchers', 'branch_id');
    $pv_l_where = "(status IS NULL OR LOWER(TRIM(COALESCE(status,''))) NOT IN ('deleted','cancelled'))";
    if (!empty($from_date)) {
        $pv_l_where .= " AND voucher_date >= '$from_date'";
    }
    if (!empty($to_date)) {
        $pv_l_where .= " AND voucher_date <= '$to_date'";
    }
    if (!empty($search)) {
        $pv_l_where .= " AND (voucher_no LIKE '%$search%' OR customer_name LIKE '%$search%' OR IFNULL(ref_no,'') LIKE '%$search%' OR IFNULL(against_of,'') LIKE '%$search%')";
    }
    if ($pv_l_has_br && $tr_branch_filter_sql !== '') {
        $pv_l_where .= $tr_branch_filter_sql;
    }
    $pv_l_sel = 'id, voucher_no, customer_name, voucher_date, COALESCE(total_amount,0) AS total_amount, sales_person, ref_no, against_of';
    if ($pv_l_has_br) {
        $pv_l_sel .= ', branch_id';
    }
    $pv_l_rows = getList("SELECT $pv_l_sel FROM tbl_payment_vouchers WHERE $pv_l_where ORDER BY voucher_date DESC, id DESC");
    if (is_array($pv_l_rows)) {
        foreach ($pv_l_rows as $r) {
            $rno = trim((string) ($r['ref_no'] ?? ''));
            $ago = trim((string) ($r['against_of'] ?? ''));
            $ex3 = $rno !== '' ? $rno : ($ago !== '' ? $ago : 'NA');
            if ($rno !== '' && $ago !== '' && strcasecmp($rno, $ago) !== 0) {
                $ex3 = $rno . ' · ' . $ago;
            }
            $all_transactions[] = [
                'type' => 'payment_voucher',
                'type_label' => 'PAYMENT VOUCHER',
                'voucher_no' => $r['voucher_no'] ?? '',
                'party_name' => $r['customer_name'] ?? '',
                'sales_person' => trim((string) ($r['sales_person'] ?? '')),
                'date' => $r['voucher_date'] ?? '',
                'amount' => (float) ($r['total_amount'] ?? 0),
                'balance' => 0,
                'id' => (int) ($r['id'] ?? 0),
                'link' => 'payment-voucher.php?id=' . (int) ($r['id'] ?? 0),
                'print_link' => 'payment-voucher-print.php?id=' . (int) ($r['id'] ?? 0),
                'branch_name' => $pv_l_has_br ? transaction_report_branch_label($tr_branch_name_by_id, $r['branch_id'] ?? 0) : '—',
                'voucher_ex_col3' => $ex3,
            ];
        }
    }
}

// Journal Vouchers
$jv_list_check = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_journal_vouchers'");
$jv_list_exists = $jv_list_check && mysqli_num_rows($jv_list_check) > 0;
if ($jv_list_check) {
    mysqli_free_result($jv_list_check);
}
if ($jv_list_exists && ($transaction_voucher_filter === '' || $transaction_voucher_filter === 'journal_voucher' || $transaction_voucher_filter === 'journal')) {
    $jv_l_has_br = auragold_tbl_has_column($conn, 'tbl_journal_vouchers', 'branch_id');
    $jv_l_where = "(status IS NULL OR LOWER(TRIM(COALESCE(status,''))) NOT IN ('deleted','cancelled'))";
    if (!empty($from_date)) {
        $jv_l_where .= " AND voucher_date >= '$from_date'";
    }
    if (!empty($to_date)) {
        $jv_l_where .= " AND voucher_date <= '$to_date'";
    }
    if (!empty($search)) {
        $jv_l_where .= " AND (
            voucher_no LIKE '%$search%'
            OR IFNULL(comment,'') LIKE '%$search%'
            OR EXISTS (
                SELECT 1 FROM tbl_journal_voucher_items jvi
                WHERE jvi.voucher_id = tbl_journal_vouchers.id AND jvi.status = 1
                AND jvi.account_ledger LIKE '%$search%'
            )
        )";
    }
    if ($jv_l_has_br && $tr_branch_filter_sql !== '') {
        $jv_l_where .= $tr_branch_filter_sql;
    }
    $jv_l_sel = 'id, voucher_no, voucher_date, COALESCE(debit_total, credit_total, 0) AS total_amount, comment';
    if ($jv_l_has_br) {
        $jv_l_sel .= ', branch_id';
    }
    $jv_l_rows = getList("SELECT $jv_l_sel FROM tbl_journal_vouchers WHERE $jv_l_where ORDER BY voucher_date DESC, id DESC");
    if (is_array($jv_l_rows)) {
        foreach ($jv_l_rows as $r) {
            $jv_id = (int) ($r['id'] ?? 0);
            $accts = '';
            $accts_detail = '';
            if ($jv_id > 0) {
                $acct_row = getRecord("SELECT GROUP_CONCAT(DISTINCT account_ledger ORDER BY id SEPARATOR ', ') AS accts FROM tbl_journal_voucher_items WHERE voucher_id = $jv_id AND status = 1");
                $accts = trim((string) ($acct_row['accts'] ?? ''));
                $det_row = getRecord("
                    SELECT GROUP_CONCAT(
                        CONCAT(account_ledger, ' ', cr_dr, ' ', FORMAT(COALESCE(amount,0), 2))
                        ORDER BY id SEPARATOR ' | '
                    ) AS det
                    FROM tbl_journal_voucher_items
                    WHERE voucher_id = $jv_id AND status = 1
                ");
                $accts_detail = trim((string) ($det_row['det'] ?? ''));
            }
            $cmt = trim((string) ($r['comment'] ?? ''));
            $ex3 = $accts_detail !== '' ? $accts_detail : ($cmt !== '' ? $cmt : 'NA');
            $all_transactions[] = [
                'type' => 'journal_voucher',
                'type_label' => 'JOURNAL VOUCHER',
                'voucher_no' => $r['voucher_no'] ?? '',
                'party_name' => $accts !== '' ? $accts : 'Journal',
                'sales_person' => 'NA',
                'date' => $r['voucher_date'] ?? '',
                'amount' => (float) ($r['total_amount'] ?? 0),
                'balance' => 0,
                'id' => $jv_id,
                'link' => 'journal-voucher.php?id=' . $jv_id,
                'print_link' => 'journal-voucher.php?id=' . $jv_id,
                'branch_name' => $jv_l_has_br ? transaction_report_branch_label($tr_branch_name_by_id, $r['branch_id'] ?? 0) : '—',
                'voucher_ex_col3' => $ex3,
            ];
        }
    }
}

// Contra Vouchers
$cv_list_check = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_contra_vouchers'");
$cv_list_exists = $cv_list_check && mysqli_num_rows($cv_list_check) > 0;
if ($cv_list_check) {
    mysqli_free_result($cv_list_check);
}
if ($cv_list_exists && ($transaction_voucher_filter === '' || $transaction_voucher_filter === 'contra_voucher' || $transaction_voucher_filter === 'contra')) {
    $cv_l_has_br = auragold_tbl_has_column($conn, 'tbl_contra_vouchers', 'branch_id');
    $cv_items_has_status = auragold_tbl_has_column($conn, 'tbl_contra_voucher_items', 'status');
    $cv_item_status_sql = $cv_items_has_status ? ' AND status = 1' : '';
    $cv_l_where = "(status IS NULL OR LOWER(TRIM(COALESCE(status,''))) NOT IN ('deleted','cancelled'))";
    if (!empty($from_date)) {
        $cv_l_where .= " AND voucher_date >= '$from_date'";
    }
    if (!empty($to_date)) {
        $cv_l_where .= " AND voucher_date <= '$to_date'";
    }
    if (!empty($search)) {
        $cv_l_where .= " AND (
            voucher_no LIKE '%$search%'
            OR IFNULL(comment,'') LIKE '%$search%'
            OR EXISTS (
                SELECT 1 FROM tbl_contra_voucher_items cvi
                WHERE cvi.voucher_id = tbl_contra_vouchers.id
                " . ($cv_items_has_status ? ' AND cvi.status = 1' : '') . "
                AND (cvi.bank_cash_ac LIKE '%$search%' OR IFNULL(cvi.ref_no,'') LIKE '%$search%')
            )
        )";
    }
    if ($cv_l_has_br && $tr_branch_filter_sql !== '') {
        $cv_l_where .= $tr_branch_filter_sql;
    }
    $cv_l_sel = 'id, voucher_no, voucher_date, COALESCE(total_amount,0) AS total_amount, comment';
    if ($cv_l_has_br) {
        $cv_l_sel .= ', branch_id';
    }
    $cv_l_rows = getList("SELECT $cv_l_sel FROM tbl_contra_vouchers WHERE $cv_l_where ORDER BY voucher_date DESC, id DESC");
    if (is_array($cv_l_rows)) {
        foreach ($cv_l_rows as $r) {
            $cv_id = (int) ($r['id'] ?? 0);
            $accts = '';
            $accts_detail = '';
            $display_amt = (float) ($r['total_amount'] ?? 0);
            if ($cv_id > 0) {
                $acct_row = getRecord("
                    SELECT GROUP_CONCAT(DISTINCT bank_cash_ac ORDER BY id SEPARATOR ', ') AS accts
                    FROM tbl_contra_voucher_items
                    WHERE voucher_id = $cv_id $cv_item_status_sql
                ");
                $accts = trim((string) ($acct_row['accts'] ?? ''));
                $det_row = getRecord("
                    SELECT
                        GROUP_CONCAT(
                            CONCAT(
                                bank_cash_ac, ' ',
                                CASE WHEN LOWER(transaction_type) = 'deposit' THEN 'Dr' ELSE 'Cr' END,
                                ' ', FORMAT(COALESCE(amount,0), 2)
                            )
                            ORDER BY id SEPARATOR ' | '
                        ) AS det,
                        COALESCE(SUM(CASE WHEN LOWER(transaction_type) = 'deposit' THEN amount ELSE 0 END), 0) AS dr_sum,
                        COALESCE(SUM(CASE WHEN LOWER(transaction_type) = 'withdrawal' THEN amount ELSE 0 END), 0) AS cr_sum
                    FROM tbl_contra_voucher_items
                    WHERE voucher_id = $cv_id $cv_item_status_sql
                ");
                $accts_detail = trim((string) ($det_row['det'] ?? ''));
                $dr_sum = (float) ($det_row['dr_sum'] ?? 0);
                $cr_sum = (float) ($det_row['cr_sum'] ?? 0);
                // Show one-side transfer amount (not Dr+Cr combined)
                if ($dr_sum > 0.00001 || $cr_sum > 0.00001) {
                    $display_amt = max($dr_sum, $cr_sum);
                } elseif ($display_amt > 0.00001 && $accts !== '' && substr_count($accts, ',') >= 1) {
                    // Legacy rows stored both sides in total_amount
                    $display_amt = round($display_amt / 2, 2);
                }
            }
            $cmt = trim((string) ($r['comment'] ?? ''));
            $ex3 = $accts_detail !== '' ? $accts_detail : ($cmt !== '' ? $cmt : 'NA');
            $all_transactions[] = [
                'type' => 'contra_voucher',
                'type_label' => 'CONTRA VOUCHER',
                'voucher_no' => $r['voucher_no'] ?? '',
                'party_name' => $accts !== '' ? $accts : 'Contra',
                'sales_person' => 'NA',
                'date' => $r['voucher_date'] ?? '',
                'amount' => $display_amt,
                'balance' => 0,
                'id' => $cv_id,
                'link' => 'contra-voucher.php?id=' . $cv_id,
                'print_link' => 'contra-voucher.php?id=' . $cv_id,
                'branch_name' => $cv_l_has_br ? transaction_report_branch_label($tr_branch_name_by_id, $r['branch_id'] ?? 0) : '—',
                'voucher_ex_col3' => $ex3,
            ];
        }
    }
}

// Receipt Vouchers
$rv_list_check = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_receipt_vouchers'");
$rv_list_exists = $rv_list_check && mysqli_num_rows($rv_list_check) > 0;
if ($rv_list_check) {
    mysqli_free_result($rv_list_check);
}
if ($rv_list_exists && ($transaction_voucher_filter === '' || $transaction_voucher_filter === 'receipt_voucher')) {
    $rv_l_has_br = auragold_tbl_has_column($conn, 'tbl_receipt_vouchers', 'branch_id');
    $rv_l_where = "(status IS NULL OR LOWER(TRIM(COALESCE(status,''))) NOT IN ('deleted','cancelled'))";
    $rv_l_where .= " AND COALESCE(voucher_type,'') <> 'Sale Invoice Payment'";
    if (!empty($from_date)) {
        $rv_l_where .= " AND voucher_date >= '$from_date'";
    }
    if (!empty($to_date)) {
        $rv_l_where .= " AND voucher_date <= '$to_date'";
    }
    if (!empty($search)) {
        $rv_l_where .= " AND (voucher_no LIKE '%$search%' OR customer_name LIKE '%$search%' OR IFNULL(ref_no,'') LIKE '%$search%' OR IFNULL(against_of,'') LIKE '%$search%')";
    }
    if ($rv_l_has_br && $tr_branch_filter_sql !== '') {
        $rv_l_where .= $tr_branch_filter_sql;
    }
    $rv_l_sel = 'id, voucher_no, customer_name, voucher_date, COALESCE(total_amount,0) AS total_amount, sales_person, ref_no, against_of';
    if ($rv_l_has_br) {
        $rv_l_sel .= ', branch_id';
    }
    $rv_l_rows = getList("SELECT $rv_l_sel FROM tbl_receipt_vouchers WHERE $rv_l_where ORDER BY voucher_date DESC, id DESC");
    if (is_array($rv_l_rows)) {
        foreach ($rv_l_rows as $r) {
            $rno = trim((string) ($r['ref_no'] ?? ''));
            $ago = trim((string) ($r['against_of'] ?? ''));
            $ex3 = $rno !== '' ? $rno : ($ago !== '' ? $ago : 'NA');
            if ($rno !== '' && $ago !== '' && strcasecmp($rno, $ago) !== 0) {
                $ex3 = $rno . ' · ' . $ago;
            }
            $all_transactions[] = [
                'type' => 'receipt_voucher',
                'type_label' => 'RECEIPT VOUCHER',
                'voucher_no' => $r['voucher_no'] ?? '',
                'party_name' => $r['customer_name'] ?? '',
                'sales_person' => trim((string) ($r['sales_person'] ?? '')),
                'date' => $r['voucher_date'] ?? '',
                'amount' => (float) ($r['total_amount'] ?? 0),
                'balance' => 0,
                'id' => (int) ($r['id'] ?? 0),
                'link' => 'receipt-voucher.php?id=' . (int) ($r['id'] ?? 0),
                'print_link' => 'receipt-voucher-print.php?id=' . (int) ($r['id'] ?? 0),
                'branch_name' => $rv_l_has_br ? transaction_report_branch_label($tr_branch_name_by_id, $r['branch_id'] ?? 0) : '—',
                'voucher_ex_col3' => $ex3,
            ];
        }
    }
}

// Sale Receipt Vouchers (auto from sale / POS invoice — tbl_sale_receipt_vouchers)
$srv_list_check = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_receipt_vouchers'");
$srv_list_exists = $srv_list_check && mysqli_num_rows($srv_list_check) > 0;
if ($srv_list_check) {
    mysqli_free_result($srv_list_check);
}
if ($srv_list_exists && ($transaction_voucher_filter === '' || $transaction_voucher_filter === 'sale_receipt_voucher')) {
    $srv_l_has_br = auragold_tbl_has_column($conn, 'tbl_sale_receipt_vouchers', 'branch_id');
    $srv_l_where = "(status IS NULL OR LOWER(TRIM(COALESCE(status,''))) NOT IN ('deleted','cancelled'))";
    if (!empty($from_date)) {
        $srv_l_where .= " AND voucher_date >= '$from_date'";
    }
    if (!empty($to_date)) {
        $srv_l_where .= " AND voucher_date <= '$to_date'";
    }
    if (!empty($search)) {
        $srv_l_where .= " AND (voucher_no LIKE '%$search%' OR customer_name LIKE '%$search%' OR IFNULL(sale_invoice_no,'') LIKE '%$search%')";
    }
    if ($srv_l_has_br && $tr_branch_filter_sql !== '') {
        $srv_l_where .= $tr_branch_filter_sql;
    }
    $srv_l_sel = 'id, voucher_no, customer_name, voucher_date, COALESCE(total_amount,0) AS total_amount, sales_person, sale_invoice_no';
    if ($srv_l_has_br) {
        $srv_l_sel .= ', branch_id';
    }
    $srv_l_rows = getList("SELECT $srv_l_sel FROM tbl_sale_receipt_vouchers WHERE $srv_l_where ORDER BY voucher_date DESC, id DESC");
    if (is_array($srv_l_rows)) {
        foreach ($srv_l_rows as $r) {
            $sino = trim((string) ($r['sale_invoice_no'] ?? ''));
            $ex3 = $sino !== '' ? $sino : 'NA';
            $all_transactions[] = [
                'type' => 'sale_receipt_voucher',
                'type_label' => 'SALE RECEIPT VOUCHER',
                'voucher_no' => $r['voucher_no'] ?? '',
                'party_name' => $r['customer_name'] ?? '',
                'sales_person' => trim((string) ($r['sales_person'] ?? '')),
                'date' => $r['voucher_date'] ?? '',
                'amount' => (float) ($r['total_amount'] ?? 0),
                'balance' => 0,
                'id' => (int) ($r['id'] ?? 0),
                'link' => 'sale-receipt-voucher.php?id=' . (int) ($r['id'] ?? 0),
                'print_link' => 'sale-receipt-voucher.php?id=' . (int) ($r['id'] ?? 0),
                'branch_name' => $srv_l_has_br ? transaction_report_branch_label($tr_branch_name_by_id, $r['branch_id'] ?? 0) : '—',
                'voucher_ex_col3' => $ex3,
                'no_delete_from_report' => true,
            ];
        }
    }
}

// Advance Payments
$ap_list_check = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_advance_payments'");
$ap_list_exists = $ap_list_check && mysqli_num_rows($ap_list_check) > 0;
if ($ap_list_check) {
    mysqli_free_result($ap_list_check);
}
if ($ap_list_exists && ($transaction_voucher_filter === '' || $transaction_voucher_filter === 'advance_payment')) {
    $ap_l_has_br = auragold_tbl_has_column($conn, 'tbl_advance_payments', 'branch_id');
    $ap_l_where = "(status IS NULL OR LOWER(TRIM(COALESCE(status,''))) NOT IN ('deleted','cancelled'))";
    if (!empty($from_date)) {
        $ap_l_where .= " AND voucher_date >= '$from_date'";
    }
    if (!empty($to_date)) {
        $ap_l_where .= " AND voucher_date <= '$to_date'";
    }
    if (!empty($search)) {
        $ap_l_where .= " AND (voucher_no LIKE '%$search%' OR customer_name LIKE '%$search%' OR IFNULL(ref_no,'') LIKE '%$search%' OR IFNULL(against_of,'') LIKE '%$search%')";
    }
    if ($ap_l_has_br && $tr_branch_filter_sql !== '') {
        $ap_l_where .= $tr_branch_filter_sql;
    }
    $ap_l_sel = 'id, voucher_no, customer_name, voucher_date, COALESCE(total_amount,0) AS total_amount, sales_person, ref_no, against_of';
    if ($ap_l_has_br) {
        $ap_l_sel .= ', branch_id';
    }
    $ap_l_rows = getList("SELECT $ap_l_sel FROM tbl_advance_payments WHERE $ap_l_where ORDER BY voucher_date DESC, id DESC");
    if (is_array($ap_l_rows)) {
        foreach ($ap_l_rows as $r) {
            $rno = trim((string) ($r['ref_no'] ?? ''));
            $ago = trim((string) ($r['against_of'] ?? ''));
            $ex3 = $rno !== '' ? $rno : ($ago !== '' ? $ago : 'NA');
            if ($rno !== '' && $ago !== '' && strcasecmp($rno, $ago) !== 0) {
                $ex3 = $rno . ' · ' . $ago;
            }
            $all_transactions[] = [
                'type' => 'advance_payment',
                'type_label' => 'ADVANCE PAYMENT',
                'voucher_no' => $r['voucher_no'] ?? '',
                'party_name' => $r['customer_name'] ?? '',
                'sales_person' => trim((string) ($r['sales_person'] ?? '')),
                'date' => $r['voucher_date'] ?? '',
                'amount' => (float) ($r['total_amount'] ?? 0),
                'balance' => 0,
                'id' => (int) ($r['id'] ?? 0),
                'link' => 'advance-payment.php?id=' . (int) ($r['id'] ?? 0),
                'print_link' => 'advance-payment-print.php?id=' . (int) ($r['id'] ?? 0),
                'branch_name' => $ap_l_has_br ? transaction_report_branch_label($tr_branch_name_by_id, $r['branch_id'] ?? 0) : '—',
                'voucher_ex_col3' => $ex3,
                'no_delete_from_report' => true,
            ];
        }
    }
}

// Metal ↔ Amount (Utilities)
$mac_tr_check = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_metal_amount_conversions'");
$mac_tr_exists = $mac_tr_check && mysqli_num_rows($mac_tr_check) > 0;
if ($mac_tr_check) {
    mysqli_free_result($mac_tr_check);
}
$mac_dir_filter = $transaction_voucher_filter;
if ($mac_tr_exists && ($mac_dir_filter === '' || $mac_dir_filter === 'metal_to_amount' || $mac_dir_filter === 'amount_to_metal')) {
    $mac_w2 = 'status = 1';
    if ($mac_dir_filter === 'metal_to_amount' || $mac_dir_filter === 'amount_to_metal') {
        $mac_w2 .= " AND direction = '" . esc($mac_dir_filter) . "'";
    }
    if (!empty($from_date)) {
        $mac_w2 .= " AND trans_date >= '$from_date'";
    }
    if (!empty($to_date)) {
        $mac_w2 .= " AND trans_date <= '$to_date 23:59:59'";
    }
    if (!empty($search)) {
        $mac_w2 .= " AND (trans_no LIKE '%$search%' OR customer_name LIKE '%$search%')";
    }
    if (auragold_tbl_has_column($conn, 'tbl_metal_amount_conversions', 'branch_id') && $tr_branch_filter_sql !== '') {
        $mac_w2 .= $tr_branch_filter_sql;
    }
    $mac_sel2 = 'id, trans_no, customer_name, trans_date, COALESCE(amount,0) AS amount, direction, metal_type, rate, metal_weight';
    if (auragold_tbl_has_column($conn, 'tbl_metal_amount_conversions', 'branch_id')) {
        $mac_sel2 .= ', branch_id';
    }
    $mac_rows = getList("SELECT $mac_sel2 FROM tbl_metal_amount_conversions WHERE $mac_w2 ORDER BY trans_date DESC, id DESC");
    if (is_array($mac_rows)) {
        foreach ($mac_rows as $r) {
            $d = (string) ($r['direction'] ?? '');
            if ($d === 'metal_to_amount') {
                $all_transactions[] = [
                    'type' => 'metal_to_amount',
                    'type_label' => 'METAL TO AMOUNT',
                    'voucher_no' => (string) ($r['trans_no'] ?? ''),
                    'party_name' => (string) ($r['customer_name'] ?? ''),
                    'sales_person' => 'NA',
                    'date' => $r['trans_date'] ?? '',
                    'amount' => (float) ($r['amount'] ?? 0),
                    'balance' => 0,
                    'id' => (int) ($r['id'] ?? 0),
                    'link' => 'metal-to-amount.php?id=' . (int) ($r['id'] ?? 0),
                    'print_link' => 'metal-amount-conversion-print.php?id=' . (int) ($r['id'] ?? 0),
                    'branch_name' => (auragold_tbl_has_column($conn, 'tbl_metal_amount_conversions', 'branch_id'))
                        ? transaction_report_branch_label($tr_branch_name_by_id, (int) ($r['branch_id'] ?? 0)) : '—',
                    'no_delete_from_report' => true,
                ];
            } elseif ($d === 'amount_to_metal') {
                $all_transactions[] = [
                    'type' => 'amount_to_metal',
                    'type_label' => 'AMOUNT TO METAL',
                    'voucher_no' => (string) ($r['trans_no'] ?? ''),
                    'party_name' => (string) ($r['customer_name'] ?? ''),
                    'sales_person' => 'NA',
                    'date' => $r['trans_date'] ?? '',
                    'amount' => (float) ($r['amount'] ?? 0),
                    'balance' => 0,
                    'id' => (int) ($r['id'] ?? 0),
                    'link' => 'amount-to-metal.php?id=' . (int) ($r['id'] ?? 0),
                    'print_link' => 'metal-amount-conversion-print.php?id=' . (int) ($r['id'] ?? 0),
                    'branch_name' => (auragold_tbl_has_column($conn, 'tbl_metal_amount_conversions', 'branch_id'))
                        ? transaction_report_branch_label($tr_branch_name_by_id, (int) ($r['branch_id'] ?? 0)) : '—',
                    'no_delete_from_report' => true,
                ];
            }
        }
    }
}

// Credit Notes
$cn_tr_check = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_credit_notes'");
$cn_tr_exists = $cn_tr_check && mysqli_num_rows($cn_tr_check) > 0;
if ($cn_tr_check) {
    mysqli_free_result($cn_tr_check);
}
if ($cn_tr_exists && ($transaction_voucher_filter === '' || $transaction_voucher_filter === 'credit_note')) {
    $cn_has_br = auragold_tbl_has_column($conn, 'tbl_credit_notes', 'branch_id');
    $cn_where = "(status IS NULL OR LOWER(TRIM(COALESCE(status,''))) NOT IN ('deleted','cancelled'))";
    if (!empty($from_date)) {
        $cn_where .= " AND credit_note_date >= '$from_date'";
    }
    if (!empty($to_date)) {
        $cn_where .= " AND credit_note_date <= '$to_date'";
    }
    if (!empty($search)) {
        $cn_where .= " AND (credit_note_no LIKE '%$search%' OR customer_name LIKE '%$search%' OR IFNULL(ref_no,'') LIKE '%$search%')";
    }
    if ($cn_has_br && $tr_branch_filter_sql !== '') {
        $cn_where .= $tr_branch_filter_sql;
    }
    $cn_sel = 'id, credit_note_no, customer_name, credit_note_date, COALESCE(grand_total,0) AS grand_total, COALESCE(balance_amt,0) AS balance_amt, sales_person';
    if ($cn_has_br) {
        $cn_sel .= ', branch_id';
    }
    $cn_rows = getList("SELECT $cn_sel FROM tbl_credit_notes WHERE $cn_where ORDER BY credit_note_date DESC, id DESC");
    if (is_array($cn_rows)) {
        foreach ($cn_rows as $r) {
            $all_transactions[] = [
                'type' => 'credit_note',
                'type_label' => 'CREDIT NOTE',
                'voucher_no' => $r['credit_note_no'] ?? '',
                'party_name' => $r['customer_name'] ?? '',
                'sales_person' => trim((string) ($r['sales_person'] ?? '')),
                'date' => $r['credit_note_date'] ?? '',
                'amount' => (float) ($r['grand_total'] ?? 0),
                'balance' => (float) ($r['balance_amt'] ?? 0),
                'id' => (int) ($r['id'] ?? 0),
                'link' => 'credit-note.php?id=' . (int) ($r['id'] ?? 0),
                'print_link' => 'credit-note.php?id=' . (int) ($r['id'] ?? 0),
                'branch_name' => $cn_has_br ? transaction_report_branch_label($tr_branch_name_by_id, $r['branch_id'] ?? 0) : '—',
            ];
        }
    }
}

// Debit Notes
$dn_tr_check = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_debit_notes'");
$dn_tr_exists = $dn_tr_check && mysqli_num_rows($dn_tr_check) > 0;
if ($dn_tr_check) {
    mysqli_free_result($dn_tr_check);
}
if ($dn_tr_exists && ($transaction_voucher_filter === '' || $transaction_voucher_filter === 'debit_note')) {
    $dn_has_br = auragold_tbl_has_column($conn, 'tbl_debit_notes', 'branch_id');
    $dn_where = "(status IS NULL OR LOWER(TRIM(COALESCE(status,''))) NOT IN ('deleted','cancelled'))";
    if (!empty($from_date)) {
        $dn_where .= " AND debit_note_date >= '$from_date'";
    }
    if (!empty($to_date)) {
        $dn_where .= " AND debit_note_date <= '$to_date'";
    }
    if (!empty($search)) {
        $dn_where .= " AND (debit_note_no LIKE '%$search%' OR customer_name LIKE '%$search%' OR IFNULL(ref_no,'') LIKE '%$search%')";
    }
    if ($dn_has_br && $tr_branch_filter_sql !== '') {
        $dn_where .= $tr_branch_filter_sql;
    }
    $dn_sel = 'id, debit_note_no, customer_name, debit_note_date, COALESCE(grand_total,0) AS grand_total, COALESCE(balance_amt,0) AS balance_amt, sales_person';
    if ($dn_has_br) {
        $dn_sel .= ', branch_id';
    }
    $dn_rows = getList("SELECT $dn_sel FROM tbl_debit_notes WHERE $dn_where ORDER BY debit_note_date DESC, id DESC");
    if (is_array($dn_rows)) {
        foreach ($dn_rows as $r) {
            $all_transactions[] = [
                'type' => 'debit_note',
                'type_label' => 'DEBIT NOTE',
                'voucher_no' => $r['debit_note_no'] ?? '',
                'party_name' => $r['customer_name'] ?? '',
                'sales_person' => trim((string) ($r['sales_person'] ?? '')),
                'date' => $r['debit_note_date'] ?? '',
                'amount' => (float) ($r['grand_total'] ?? 0),
                'balance' => (float) ($r['balance_amt'] ?? 0),
                'id' => (int) ($r['id'] ?? 0),
                'link' => 'debit-note.php?id=' . (int) ($r['id'] ?? 0),
                'print_link' => 'debit-note.php?id=' . (int) ($r['id'] ?? 0),
                'branch_name' => $dn_has_br ? transaction_report_branch_label($tr_branch_name_by_id, $r['branch_id'] ?? 0) : '—',
            ];
        }
    }
}

// Repair Invoices
$ri_tr_check = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_repair_invoices'");
$ri_tr_exists = $ri_tr_check && mysqli_num_rows($ri_tr_check) > 0;
if ($ri_tr_check) {
    mysqli_free_result($ri_tr_check);
}
if ($ri_tr_exists && ($transaction_voucher_filter === '' || $transaction_voucher_filter === 'repair_invoice')) {
    $ri_has_br = auragold_tbl_has_column($conn, 'tbl_repair_invoices', 'branch_id');
    $ri_where = "(status IS NULL OR LOWER(TRIM(COALESCE(status,''))) NOT IN ('deleted','cancelled'))";
    if (!empty($from_date)) {
        $ri_where .= " AND repair_invoice_date >= '$from_date'";
    }
    if (!empty($to_date)) {
        $ri_where .= " AND repair_invoice_date <= '$to_date'";
    }
    if (!empty($search)) {
        $ri_where .= " AND (repair_invoice_no LIKE '%$search%' OR customer_name LIKE '%$search%' OR IFNULL(ref_no,'') LIKE '%$search%')";
    }
    if ($ri_has_br && $tr_branch_filter_sql !== '') {
        $ri_where .= $tr_branch_filter_sql;
    }
    $ri_sel = 'id, repair_invoice_no, customer_name, repair_invoice_date, COALESCE(grand_total,0) AS grand_total, COALESCE(balance_amt,0) AS balance_amt, sales_person';
    if ($ri_has_br) {
        $ri_sel .= ', branch_id';
    }
    $ri_rows = getList("SELECT $ri_sel FROM tbl_repair_invoices WHERE $ri_where ORDER BY repair_invoice_date DESC, id DESC");
    if (is_array($ri_rows)) {
        foreach ($ri_rows as $r) {
            $all_transactions[] = [
                'type' => 'repair_invoice',
                'type_label' => 'REPAIR INVOICE',
                'voucher_no' => $r['repair_invoice_no'] ?? '',
                'party_name' => $r['customer_name'] ?? '',
                'sales_person' => trim((string) ($r['sales_person'] ?? '')),
                'date' => $r['repair_invoice_date'] ?? '',
                'amount' => (float) ($r['grand_total'] ?? 0),
                'balance' => (float) ($r['balance_amt'] ?? 0),
                'id' => (int) ($r['id'] ?? 0),
                'link' => 'repair-invoice.php?id=' . (int) ($r['id'] ?? 0),
                'print_link' => 'repair-invoice.php?id=' . (int) ($r['id'] ?? 0),
                'branch_name' => $ri_has_br ? transaction_report_branch_label($tr_branch_name_by_id, $r['branch_id'] ?? 0) : '—',
            ];
        }
    }
}

// Repair Orders
$ro_tr_check = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_repair_orders'");
$ro_tr_exists = $ro_tr_check && mysqli_num_rows($ro_tr_check) > 0;
if ($ro_tr_check) {
    mysqli_free_result($ro_tr_check);
}
if ($ro_tr_exists && ($transaction_voucher_filter === '' || $transaction_voucher_filter === 'repair_order')) {
    $ro_has_br = auragold_tbl_has_column($conn, 'tbl_repair_orders', 'branch_id');
    $ro_where = "(status IS NULL OR LOWER(TRIM(COALESCE(status,''))) NOT IN ('deleted','cancelled'))";
    if (!empty($from_date)) {
        $ro_where .= " AND order_date >= '$from_date'";
    }
    if (!empty($to_date)) {
        $ro_where .= " AND order_date <= '$to_date'";
    }
    if (!empty($search)) {
        $ro_where .= " AND (order_no LIKE '%$search%' OR customer_name LIKE '%$search%' OR IFNULL(ref_no,'') LIKE '%$search%')";
    }
    if ($ro_has_br && $tr_branch_filter_sql !== '') {
        $ro_where .= $tr_branch_filter_sql;
    }
    $ro_sel = 'id, order_no, customer_name, order_date, COALESCE(grand_total,0) AS grand_total, COALESCE(balance_amt,0) AS balance_amt, sales_person';
    if ($ro_has_br) {
        $ro_sel .= ', branch_id';
    }
    $ro_rows = getList("SELECT $ro_sel FROM tbl_repair_orders WHERE $ro_where ORDER BY order_date DESC, id DESC");
    if (is_array($ro_rows)) {
        foreach ($ro_rows as $r) {
            $all_transactions[] = [
                'type' => 'repair_order',
                'type_label' => 'REPAIR ORDER',
                'voucher_no' => $r['order_no'] ?? '',
                'party_name' => $r['customer_name'] ?? '',
                'sales_person' => trim((string) ($r['sales_person'] ?? '')),
                'date' => $r['order_date'] ?? '',
                'amount' => (float) ($r['grand_total'] ?? 0),
                'balance' => (float) ($r['balance_amt'] ?? 0),
                'id' => (int) ($r['id'] ?? 0),
                'link' => 'repair-order.php?id=' . (int) ($r['id'] ?? 0),
                'print_link' => 'repair-order.php?id=' . (int) ($r['id'] ?? 0),
                'branch_name' => $ro_has_br ? transaction_report_branch_label($tr_branch_name_by_id, $r['branch_id'] ?? 0) : '—',
            ];
        }
    }
}

// Purchase Orders
$po_tr_check = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_purchase_orders'");
$po_tr_exists = $po_tr_check && mysqli_num_rows($po_tr_check) > 0;
if ($po_tr_check) {
    mysqli_free_result($po_tr_check);
}
if ($po_tr_exists && ($transaction_voucher_filter === '' || $transaction_voucher_filter === 'purchase_order')) {
    $po_has_br = auragold_tbl_has_column($conn, 'tbl_purchase_orders', 'branch_id');
    $po_where = "(status IS NULL OR LOWER(TRIM(COALESCE(status,''))) NOT IN ('deleted','cancelled'))";
    if (!empty($from_date)) {
        $po_where .= " AND order_date >= '$from_date'";
    }
    if (!empty($to_date)) {
        $po_where .= " AND order_date <= '$to_date'";
    }
    if (!empty($search)) {
        $po_where .= " AND (order_no LIKE '%$search%' OR customer_name LIKE '%$search%' OR IFNULL(ref_no,'') LIKE '%$search%')";
    }
    if ($po_has_br && $tr_branch_filter_sql !== '') {
        $po_where .= $tr_branch_filter_sql;
    }
    $po_sel = 'id, order_no, customer_name, order_date, COALESCE(grand_total,0) AS grand_total, COALESCE(balance_amt,0) AS balance_amt, sales_person';
    if ($po_has_br) {
        $po_sel .= ', branch_id';
    }
    $po_rows = getList("SELECT $po_sel FROM tbl_purchase_orders WHERE $po_where ORDER BY order_date DESC, id DESC");
    if (is_array($po_rows)) {
        foreach ($po_rows as $r) {
            $all_transactions[] = [
                'type' => 'purchase_order',
                'type_label' => 'PURCHASE ORDER',
                'voucher_no' => $r['order_no'] ?? '',
                'party_name' => $r['customer_name'] ?? '',
                'sales_person' => trim((string) ($r['sales_person'] ?? '')),
                'date' => $r['order_date'] ?? '',
                'amount' => (float) ($r['grand_total'] ?? 0),
                'balance' => (float) ($r['balance_amt'] ?? 0),
                'id' => (int) ($r['id'] ?? 0),
                'link' => 'purchase-order.php?id=' . (int) ($r['id'] ?? 0),
                'print_link' => 'purchase-order.php?id=' . (int) ($r['id'] ?? 0),
                'branch_name' => $po_has_br ? transaction_report_branch_label($tr_branch_name_by_id, $r['branch_id'] ?? 0) : '—',
            ];
        }
    }
}

// Consignment In / Out
foreach (
    [
        ['tbl' => 'tbl_consignment_in', 'type' => 'consignment_in', 'label' => 'CONSIGNMENT IN', 'page' => 'consignment-in.php'],
        ['tbl' => 'tbl_consignment_out', 'type' => 'consignment_out', 'label' => 'CONSIGNMENT OUT', 'page' => 'consignment-out.php'],
    ] as $ciDef
) {
    $ci_tbl = $ciDef['tbl'];
    $ci_type = $ciDef['type'];
    $ci_check = @mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $ci_tbl) . "'");
    $ci_exists = $ci_check && mysqli_num_rows($ci_check) > 0;
    if ($ci_check) {
        mysqli_free_result($ci_check);
    }
    if (!$ci_exists || !($transaction_voucher_filter === '' || $transaction_voucher_filter === $ci_type)) {
        continue;
    }
    $ci_has_br = auragold_tbl_has_column($conn, $ci_tbl, 'branch_id');
    $ci_where = "(status IS NULL OR LOWER(TRIM(COALESCE(status,''))) NOT IN ('deleted','cancelled'))";
    if (!empty($from_date)) {
        $ci_where .= " AND consignment_date >= '$from_date'";
    }
    if (!empty($to_date)) {
        $ci_where .= " AND consignment_date <= '$to_date'";
    }
    if (!empty($search)) {
        $ci_where .= " AND (consignment_no LIKE '%$search%' OR customer_name LIKE '%$search%' OR IFNULL(ref_no,'') LIKE '%$search%')";
    }
    if ($ci_has_br && $tr_branch_filter_sql !== '') {
        $ci_where .= $tr_branch_filter_sql;
    }
    $ci_sel = 'id, consignment_no, customer_name, consignment_date, COALESCE(grand_total,0) AS grand_total, sales_person';
    if ($ci_has_br) {
        $ci_sel .= ', branch_id';
    }
    $ci_rows = getList("SELECT $ci_sel FROM `$ci_tbl` WHERE $ci_where ORDER BY consignment_date DESC, id DESC");
    if (!is_array($ci_rows)) {
        continue;
    }
    foreach ($ci_rows as $r) {
        $all_transactions[] = [
            'type' => $ci_type,
            'type_label' => $ciDef['label'],
            'voucher_no' => $r['consignment_no'] ?? '',
            'party_name' => $r['customer_name'] ?? '',
            'sales_person' => trim((string) ($r['sales_person'] ?? '')),
            'date' => $r['consignment_date'] ?? '',
            'amount' => (float) ($r['grand_total'] ?? 0),
            'balance' => 0,
            'id' => (int) ($r['id'] ?? 0),
            'link' => $ciDef['page'] . '?id=' . (int) ($r['id'] ?? 0),
            'print_link' => $ciDef['page'] . '?id=' . (int) ($r['id'] ?? 0),
            'branch_name' => $ci_has_br ? transaction_report_branch_label($tr_branch_name_by_id, $r['branch_id'] ?? 0) : '—',
            'no_delete_from_report' => true,
        ];
    }
}

// Investment Fund Installments (from tbl_customer_ledger — posted on installment save)
if ($transaction_voucher_filter === '' || $transaction_voucher_filter === 'investment_fund_installment') {
    $ifi_ledger_check = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_customer_ledger'");
    $ifi_ledger_exists = $ifi_ledger_check && mysqli_num_rows($ifi_ledger_check) > 0;
    if ($ifi_ledger_check) {
        mysqli_free_result($ifi_ledger_check);
    }
    if ($ifi_ledger_exists) {
        $ifi_has_br = auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'branch_id');
        $ifi_where = "l.status = 1 AND l.transaction_type = 'investment_fund_installment'
            AND LOWER(TRIM(COALESCE(l.customer_name,''))) != 'layaways fund'
            AND COALESCE(l.debit_amount, 0) > 0";
        if (!empty($from_date)) {
            $ifi_where .= " AND l.transaction_date >= '$from_date'";
        }
        if (!empty($to_date)) {
            $ifi_where .= " AND l.transaction_date <= '$to_date'";
        }
        if (!empty($search)) {
            $ifi_where .= " AND (l.transaction_no LIKE '%$search%' OR l.customer_name LIKE '%$search%' OR IFNULL(l.description,'') LIKE '%$search%' OR IFNULL(l.reference_no,'') LIKE '%$search%')";
        }
        if ($ifi_has_br && $tr_branch_filter_sql !== '') {
            // $tr_branch_filter_sql uses bare branch_id; qualify for ledger alias
            $ifi_where .= str_replace('branch_id', 'l.branch_id', $tr_branch_filter_sql);
        }
        $ifi_sel = 'l.id, l.transaction_no, l.customer_name, l.transaction_date, COALESCE(l.debit_amount,0) AS amount, l.description, l.reference_no, l.created_at';
        if ($ifi_has_br) {
            $ifi_sel .= ', l.branch_id';
        }
        $ifi_rows = getList("SELECT $ifi_sel FROM tbl_customer_ledger l WHERE $ifi_where ORDER BY l.transaction_date DESC, l.id DESC");
        if (is_array($ifi_rows)) {
            foreach ($ifi_rows as $r) {
                $txnNo = trim((string) ($r['transaction_no'] ?? ''));
                // transaction_no like IF-1-I3 → fund IF-1 for deep-link
                $fundNo = $txnNo;
                if (preg_match('/^(IF-\d+)-I\d+$/i', $txnNo, $mFund)) {
                    $fundNo = $mFund[1];
                }
                $ex3 = trim((string) ($r['reference_no'] ?? ''));
                if ($ex3 === '') {
                    $ex3 = $fundNo !== '' ? $fundNo : 'NA';
                }
                $desc = trim((string) ($r['description'] ?? ''));
                $all_transactions[] = [
                    'type' => 'investment_fund_installment',
                    'type_label' => 'INVESTMENT FUND INSTALLMENT',
                    'voucher_no' => $txnNo,
                    'party_name' => $r['customer_name'] ?? '',
                    'sales_person' => '',
                    'date' => $r['transaction_date'] ?? '',
                    'amount' => (float) ($r['amount'] ?? 0),
                    'balance' => 0,
                    'id' => (int) ($r['id'] ?? 0),
                    'link' => 'investment-fund.php?fund_no=' . rawurlencode($fundNo),
                    'print_link' => 'investment-fund.php?fund_no=' . rawurlencode($fundNo),
                    'branch_name' => $ifi_has_br ? transaction_report_branch_label($tr_branch_name_by_id, $r['branch_id'] ?? 0) : '—',
                    'voucher_ex_col3' => $ex3,
                    'created_at' => $r['created_at'] ?? '',
                    'no_delete_from_report' => true,
                    'party_email' => '',
                    'description_hint' => $desc,
                ];
            }
        }
    }
}

// Apply Advance Filter criteria that are not (fully) pushed into every voucher SQL.
if ($invoice_no_raw !== '') {
    $invNeedle = mb_strtolower($invoice_no_raw);
    $all_transactions = array_values(array_filter($all_transactions, static function ($t) use ($invNeedle) {
        $hay = mb_strtolower(
            trim((string) ($t['voucher_no'] ?? '')) . ' '
            . trim((string) ($t['voucher_ex_col3'] ?? '')) . ' '
            . trim((string) ($t['against_pi'] ?? '')) . ' '
            . trim((string) ($t['against_si'] ?? ''))
        );
        return $invNeedle === '' || strpos($hay, $invNeedle) !== false;
    }));
}
if (!empty($ledger_names_sel)) {
    $ledgerSet = [];
    foreach ($ledger_names_sel as $ln) {
        $ledgerSet[mb_strtolower(trim((string) $ln))] = true;
    }
    $all_transactions = array_values(array_filter($all_transactions, static function ($t) use ($ledgerSet) {
        $pn = mb_strtolower(trim((string) ($t['party_name'] ?? '')));
        if ($pn === '') {
            return false;
        }
        // Exact match (sale/purchase party name)
        if (isset($ledgerSet[$pn])) {
            return true;
        }
        // Contra / Journal may list multiple accounts: "Cash, BOI"
        foreach ($ledgerSet as $ln => $_) {
            if ($ln === '') {
                continue;
            }
            if ($pn === $ln || strpos($pn, $ln) !== false) {
                return true;
            }
        }
        return false;
    }));
}
if ($against_inv_no_raw !== '') {
    $agNeedle = mb_strtolower($against_inv_no_raw);
    $all_transactions = array_values(array_filter($all_transactions, static function ($t) use ($agNeedle) {
        $hay = mb_strtolower(
            trim((string) ($t['voucher_ex_col3'] ?? '')) . ' '
            . trim((string) ($t['against_pi'] ?? '')) . ' '
            . trim((string) ($t['against_si'] ?? '')) . ' '
            . trim((string) ($t['against_of'] ?? ''))
        );
        return strpos($hay, $agNeedle) !== false;
    }));
}
if (!empty($filter_voucher_keys) && $transaction_voucher_filter === '') {
    $allowedTypes = transaction_report_voucher_filter_to_types($filter_voucher_keys);
    if ($allowedTypes !== []) {
        $typeSet = array_fill_keys($allowedTypes, true);
        $all_transactions = array_values(array_filter($all_transactions, static function ($t) use ($typeSet) {
            return isset($typeSet[(string) ($t['type'] ?? '')]);
        }));
    }
}
if ($only_balance) {
    $all_transactions = array_values(array_filter($all_transactions, static function ($t) {
        return abs((float) ($t['balance'] ?? 0)) > 0.00001;
    }));
}

// Newest saved/updated records first (across every voucher module)
if (isset($conn) && $conn instanceof mysqli) {
    transaction_report_enrich_sort_timestamps($all_transactions, $conn);
    transaction_report_enrich_party_mobile($all_transactions, $conn);
} else {
    foreach ($all_transactions as $i => $t) {
        $all_transactions[$i]['sort_ts'] = transaction_report_sort_ts($t['date'] ?? '', $t['created_at'] ?? '');
    }
}
usort($all_transactions, static function ($a, $b) {
    $sa = (string) ($a['sort_ts'] ?? '');
    $sb = (string) ($b['sort_ts'] ?? '');
    if ($sa !== $sb) {
        return strcmp($sb, $sa);
    }
    $da = trim((string) ($a['date'] ?? ''));
    $db = trim((string) ($b['date'] ?? ''));
    if ($da !== $db) {
        return strcmp($db, $da);
    }
    return ((int) ($b['id'] ?? 0)) - ((int) ($a['id'] ?? 0));
});

// Reorder: show Sale Fixing Direct immediately above its linked Purchase Invoice (e.g. SF-1 "Fixing of PI-6" on top of PI-6)
$pi_voucher_nos = [];
foreach ($all_transactions as $t) {
    if (($t['type'] ?? '') === 'purchase_invoice') {
        $pi_voucher_nos[(string)($t['voucher_no'] ?? '')] = true;
    }
}
$reordered = [];
$placed_sfd_ids = [];
foreach ($all_transactions as $t) {
    if (($t['type'] ?? '') === 'sale_fixing_direct') {
        $against_pi = $t['against_pi'] ?? '';
        if ($against_pi !== '' && isset($pi_voucher_nos[$against_pi])) {
            // Will be inserted just before the matching PI
            continue;
        }
        $reordered[] = $t;
        $placed_sfd_ids[] = (int)($t['id'] ?? 0);
        continue;
    }
    if (($t['type'] ?? '') === 'purchase_invoice') {
        $voucher = (string)($t['voucher_no'] ?? '');
        foreach ($all_transactions as $s) {
            if (($s['type'] ?? '') === 'sale_fixing_direct' && (string)($s['against_pi'] ?? '') === $voucher && !in_array($s['id'], $placed_sfd_ids)) {
                $reordered[] = $s;
                $placed_sfd_ids[] = $s['id'];
            }
        }
        $reordered[] = $t;
        continue;
    }
    $reordered[] = $t;
}
foreach ($all_transactions as $t) {
    if (($t['type'] ?? '') === 'sale_fixing_direct' && !in_array($t['id'], $placed_sfd_ids)) {
        $reordered[] = $t;
    }
}
$all_transactions = $reordered;

// Purchase Fixing Direct: place above linked Sale Invoice (e.g. "Fixing of SPK14" above SPK14)
$si_voucher_keys = [];
foreach ($all_transactions as $t) {
    if (($t['type'] ?? '') === 'sale_invoice') {
        $si_voucher_keys[strtoupper(trim((string)($t['voucher_no'] ?? '')))] = true;
    }
}
$reordered_pfd = [];
$placed_pfd_ids = [];
foreach ($all_transactions as $t) {
    if (($t['type'] ?? '') === 'purchase_fixing_direct') {
        $asi = strtoupper(trim((string)($t['against_si'] ?? '')));
        if ($asi !== '' && isset($si_voucher_keys[$asi])) {
            continue;
        }
        $reordered_pfd[] = $t;
        $placed_pfd_ids[] = (int)($t['id'] ?? 0);
        continue;
    }
    if (($t['type'] ?? '') === 'sale_invoice') {
        $vn = strtoupper(trim((string)($t['voucher_no'] ?? '')));
        foreach ($all_transactions as $p) {
            if (($p['type'] ?? '') === 'purchase_fixing_direct'
                && strtoupper(trim((string)($p['against_si'] ?? ''))) === $vn
                && !in_array((int)($p['id'] ?? 0), $placed_pfd_ids, true)
            ) {
                $reordered_pfd[] = $p;
                $placed_pfd_ids[] = (int)$p['id'];
            }
        }
        $reordered_pfd[] = $t;
        continue;
    }
    $reordered_pfd[] = $t;
}
foreach ($all_transactions as $p) {
    if (($p['type'] ?? '') === 'purchase_fixing_direct' && !in_array((int)($p['id'] ?? 0), $placed_pfd_ids, true)) {
        $reordered_pfd[] = $p;
    }
}
$all_transactions = $reordered_pfd;

// Old Jewelry Scrap Invoice: place immediately above linked Purchase Invoice (e.g. OJB-1 vs PI-1)
$pi_keys_for_ojb = [];
foreach ($all_transactions as $t) {
    if (($t['type'] ?? '') === 'purchase_invoice') {
        $pi_keys_for_ojb[trim((string) ($t['voucher_no'] ?? ''))] = true;
    }
}
$reordered_ojb_pi = [];
$placed_ojb_ids = [];
foreach ($all_transactions as $t) {
    if (($t['type'] ?? '') === 'old_jewelry_scrap_invoice') {
        $ap = trim((string) ($t['against_pi'] ?? ''));
        if ($ap !== '' && isset($pi_keys_for_ojb[$ap])) {
            continue;
        }
        $reordered_ojb_pi[] = $t;
        $placed_ojb_ids[] = (int) ($t['id'] ?? 0);
        continue;
    }
    if (($t['type'] ?? '') === 'purchase_invoice') {
        $voucher = trim((string) ($t['voucher_no'] ?? ''));
        foreach ($all_transactions as $o) {
            if (($o['type'] ?? '') === 'old_jewelry_scrap_invoice'
                && trim((string) ($o['against_pi'] ?? '')) === $voucher
                && !in_array((int) ($o['id'] ?? 0), $placed_ojb_ids, true)
            ) {
                $reordered_ojb_pi[] = $o;
                $placed_ojb_ids[] = (int) $o['id'];
            }
        }
        $reordered_ojb_pi[] = $t;
        continue;
    }
    $reordered_ojb_pi[] = $t;
}
foreach ($all_transactions as $o) {
    if (($o['type'] ?? '') === 'old_jewelry_scrap_invoice' && !in_array((int) ($o['id'] ?? 0), $placed_ojb_ids, true)) {
        $reordered_ojb_pi[] = $o;
    }
}
$all_transactions = $reordered_ojb_pi;

// Pagination for transaction list
$transaction_total_records = count($all_transactions);
$transaction_total_pages = $transaction_total_records > 0 ? ceil($transaction_total_records / $per_page) : 1;
$transaction_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$transaction_offset = ($transaction_page - 1) * $per_page;
$transactions_list = array_slice($all_transactions, $transaction_offset, $per_page);

// Purchase invoices that still have an active Sale Fixing (PI delete disabled until SFD is removed)
$pi_invoice_nos_with_sfd = [];
if ($active_tab === 'transactions' && function_exists('auragold_pi_invoice_nos_with_active_sale_fixing')) {
    $pi_invoice_nos_with_sfd = auragold_pi_invoice_nos_with_active_sale_fixing();
}

// Sale invoices that still have an active Purchase Fixing (SI delete disabled until PFD is removed)
$si_invoice_nos_with_pfd = [];
if ($active_tab === 'transactions' && function_exists('auragold_si_invoice_nos_with_active_purchase_fixing')) {
    $si_invoice_nos_with_pfd = auragold_si_invoice_nos_with_active_purchase_fixing();
}

// Sale orders linked to Job Work Order(s) — delete/update blocked until JWQ + JWO removed
$so_ids_with_jwo = [];
$jwo_ids_with_queue = [];
if ($active_tab === 'transactions') {
    if (!function_exists('auragold_sale_order_ids_with_jobwork_orders') && is_file(__DIR__ . '/includes/auragold_sale_order_jobwork_lock.php')) {
        require_once __DIR__ . '/includes/auragold_sale_order_jobwork_lock.php';
    }
    if (function_exists('auragold_sale_order_ids_with_jobwork_orders')) {
        $so_ids_with_jwo = auragold_sale_order_ids_with_jobwork_orders($conn);
    }
    if (function_exists('auragold_jobwork_order_ids_with_queue_activity')) {
        $jwo_ids_with_queue = auragold_jobwork_order_ids_with_queue_activity($conn);
    }
}

// Initialize totals variables
$total_opening = 0;
$total_debit = 0;
$total_credit = 0;
$total_closing = 0;
$all_ledger_data = [];

// For Balance Amounts tab - Group by customer/ledger
if ($active_tab == 'balance') {
    // Get ledger summary grouped by customer (and branch when branch_id exists on ledger)
    if ($ledger_has_branch_id) {
        $ledger_query = "
            SELECT 
                l.customer_name as ledger_name,
                l.customer_id,
                l.branch_id,
                MAX(COALESCE(b.name, '—')) AS branch_name,
                COALESCE(SUM(l.debit_amount), 0) as total_debit,
                COALESCE(SUM(l.credit_amount), 0) as total_credit,
                COALESCE(SUM(l.debit_gold), 0) as total_debit_gold,
                COALESCE(SUM(l.credit_gold), 0) as total_credit_gold,
                COALESCE(SUM(l.debit_silver), 0) as total_debit_silver,
                COALESCE(SUM(l.credit_silver), 0) as total_credit_silver,
                CASE 
                    WHEN l.customer_id > 0 THEN 'Customer'
                    ELSE 'Account'
                END as ledger_type
            FROM tbl_customer_ledger l
            LEFT JOIN tbl_branches b ON b.id = l.branch_id
            WHERE $where_clause
            GROUP BY l.customer_id, l.customer_name, l.branch_id
            ORDER BY MAX(b.name) ASC, l.customer_name ASC
        ";
    } else {
        $ledger_query = "
            SELECT 
                l.customer_name as ledger_name,
                l.customer_id,
                '—' as branch_name,
                COALESCE(SUM(l.debit_amount), 0) as total_debit,
                COALESCE(SUM(l.credit_amount), 0) as total_credit,
                COALESCE(SUM(l.debit_gold), 0) as total_debit_gold,
                COALESCE(SUM(l.credit_gold), 0) as total_credit_gold,
                COALESCE(SUM(l.debit_silver), 0) as total_debit_silver,
                COALESCE(SUM(l.credit_silver), 0) as total_credit_silver,
                CASE 
                    WHEN l.customer_id > 0 THEN 'Customer'
                    ELSE 'Account'
                END as ledger_type
            FROM tbl_customer_ledger l
            WHERE $where_clause
            GROUP BY l.customer_id, l.customer_name
            ORDER BY l.customer_name ASC
        ";
    }
    
    $ledger_data = getList($ledger_query);
    
    // Calculate opening and closing balances for each ledger
    foreach ($ledger_data as &$ledger) {
        $customer_id = $ledger['customer_id'];
        $customer_name = $ledger['ledger_name'];
        
        // Get opening balance (balance before first transaction in period)
        $opening_query = "
            SELECT balance_amount, balance_gold, balance_silver
            FROM tbl_customer_ledger
            WHERE customer_id = $customer_id 
            AND customer_name = '$customer_name'
            AND status = 1
        ";
        if ($ledger_has_branch_id && array_key_exists('branch_id', $ledger)) {
            $opening_query .= ' AND branch_id = ' . (int) ($ledger['branch_id'] ?? 0);
        }
        if (!empty($from_date)) {
            $opening_query .= " AND transaction_date < '$from_date'";
        }
        $opening_query .= " ORDER BY transaction_date DESC, id DESC LIMIT 1";
        
        $opening_balance = getRecord($opening_query);
        
        $opening_amt = $opening_balance ? (float)$opening_balance['balance_amount'] : 0;
        $opening_gold = $opening_balance ? (float)$opening_balance['balance_gold'] : 0;
        $opening_silver = $opening_balance ? (float)$opening_balance['balance_silver'] : 0;
        
        // Calculate closing balance
        $closing_amt = $opening_amt + $ledger['total_debit'] - $ledger['total_credit'];
        $closing_gold = $opening_gold + $ledger['total_debit_gold'] - $ledger['total_credit_gold'];
        $closing_silver = $opening_silver + $ledger['total_debit_silver'] - $ledger['total_credit_silver'];
        
        // Determine Cr/Dr
        $opening_crdr = $opening_amt >= 0 ? 'Dr' : 'Cr';
        $closing_crdr = $closing_amt >= 0 ? 'Dr' : 'Cr';
        
        $ledger['opening_amt'] = abs($opening_amt);
        $ledger['opening_crdr'] = $opening_crdr;
        $ledger['opening_gold'] = $opening_gold;
        $ledger['opening_silver'] = $opening_silver;
        $ledger['closing_amt'] = abs($closing_amt);
        $ledger['closing_crdr'] = $closing_crdr;
        $ledger['closing_gold'] = $closing_gold;
        $ledger['closing_silver'] = $closing_silver;
    }
    unset($ledger);
    
    // Calculate totals (before pagination, from all data)
    $all_ledger_data = $ledger_data; // Keep full dataset for totals
    foreach ($all_ledger_data as $ledger) {
        $total_opening += ($ledger['opening_crdr'] == 'Dr' ? $ledger['opening_amt'] : -$ledger['opening_amt']);
        $total_debit += $ledger['total_debit'];
        $total_credit += $ledger['total_credit'];
        $total_closing += ($ledger['closing_crdr'] == 'Dr' ? $ledger['closing_amt'] : -$ledger['closing_amt']);
    }
    
    // Pagination
    $total_records = count($all_ledger_data);
    $total_pages = $total_records > 0 ? ceil($total_records / $per_page) : 1;
    $ledger_data = array_slice($all_ledger_data, $offset, $per_page);
    
} else {
    // View All Ledger tab - Show all transactions
    if ($ledger_has_branch_id) {
        $ledger_query = "
            SELECT 
                l.*,
                l.customer_name as ledger_name,
                l.transaction_date as date,
                CASE
                    WHEN l.transaction_type = 'payment' THEN COALESCE(
                        (SELECT pv.voucher_no FROM tbl_payment_vouchers pv WHERE pv.ref_no = l.transaction_no ORDER BY pv.id DESC LIMIT 1),
                        l.transaction_no
                    )
                    ELSE l.transaction_no
                END as invoice_no,
                COALESCE(l.against_ledger, '') as against_ledger,
                COALESCE(l.against_invoice_no, l.reference_no, '') as against_invoice_no,
                CASE 
                    WHEN l.transaction_type = 'purchase_invoice' THEN 'Purchase Invoice'
                    WHEN l.transaction_type = 'sale_order' THEN 'Sale Order'
                    WHEN l.transaction_type = 'payment' THEN 'Payment Voucher'
                    WHEN l.transaction_type = 'payment_voucher' THEN 'Payment Voucher'
                    WHEN l.transaction_type = 'receipt_voucher' THEN 'Receipt Voucher'
                    WHEN l.transaction_type = 'sale_receipt_voucher' THEN 'Sale Receipt Voucher'
                    WHEN l.transaction_type = 'contra_voucher' THEN 'Contra Voucher'
                    WHEN l.transaction_type = 'journal_voucher' THEN 'Journal Voucher'
                    WHEN l.transaction_type = 'receipt' THEN 'Receipt Voucher'
                    WHEN l.transaction_type = 'advance' THEN 'Advance'
                    WHEN l.transaction_type = 'return' THEN 'Return'
                    WHEN l.transaction_type = 'opening' THEN 'OPENING'
                    WHEN l.transaction_type = 'old_jewelry_scrap_invoice' THEN 'Old Jewelry - Scrap Invoice'
                    WHEN l.transaction_type = 'old_jewelry_scrap_contra' THEN 'Old Jewelry - Scrap Invoice'
                    WHEN l.transaction_type = 'investment_fund_installment' THEN 'Investment Fund Installment'
                    WHEN l.transaction_type = 'investment_fund_transfer' THEN 'Investment Fund Transfer'
                    ELSE l.transaction_type
                END as type_of_voucher,
                l.debit_amount,
                l.credit_amount,
                l.balance_amount as cl_amount,
                l.debit_gold as gold_debit_wt,
                l.credit_gold as gold_credit_wt,
                l.balance_gold as gold_cl_wt,
                COALESCE(b.name, '—') as branch_name,
                CASE 
                    WHEN l.customer_id > 0 THEN 'Customer'
                    ELSE 'Account'
                END as ledger_type
            FROM tbl_customer_ledger l
            LEFT JOIN tbl_branches b ON b.id = l.branch_id
            WHERE $where_clause
            ORDER BY l.transaction_date DESC, l.id DESC
        ";
    } else {
        $ledger_query = "
            SELECT 
                l.*,
                l.customer_name as ledger_name,
                l.transaction_date as date,
                CASE
                    WHEN l.transaction_type = 'payment' THEN COALESCE(
                        (SELECT pv.voucher_no FROM tbl_payment_vouchers pv WHERE pv.ref_no = l.transaction_no ORDER BY pv.id DESC LIMIT 1),
                        l.transaction_no
                    )
                    ELSE l.transaction_no
                END as invoice_no,
                COALESCE(l.against_ledger, '') as against_ledger,
                COALESCE(l.against_invoice_no, l.reference_no, '') as against_invoice_no,
                CASE 
                    WHEN l.transaction_type = 'purchase_invoice' THEN 'Purchase Invoice'
                    WHEN l.transaction_type = 'sale_order' THEN 'Sale Order'
                    WHEN l.transaction_type = 'payment' THEN 'Payment Voucher'
                    WHEN l.transaction_type = 'payment_voucher' THEN 'Payment Voucher'
                    WHEN l.transaction_type = 'receipt_voucher' THEN 'Receipt Voucher'
                    WHEN l.transaction_type = 'sale_receipt_voucher' THEN 'Sale Receipt Voucher'
                    WHEN l.transaction_type = 'contra_voucher' THEN 'Contra Voucher'
                    WHEN l.transaction_type = 'journal_voucher' THEN 'Journal Voucher'
                    WHEN l.transaction_type = 'receipt' THEN 'Receipt Voucher'
                    WHEN l.transaction_type = 'advance' THEN 'Advance'
                    WHEN l.transaction_type = 'return' THEN 'Return'
                    WHEN l.transaction_type = 'opening' THEN 'OPENING'
                    WHEN l.transaction_type = 'old_jewelry_scrap_invoice' THEN 'Old Jewelry - Scrap Invoice'
                    WHEN l.transaction_type = 'old_jewelry_scrap_contra' THEN 'Old Jewelry - Scrap Invoice'
                    WHEN l.transaction_type = 'investment_fund_installment' THEN 'Investment Fund Installment'
                    WHEN l.transaction_type = 'investment_fund_transfer' THEN 'Investment Fund Transfer'
                    ELSE l.transaction_type
                END as type_of_voucher,
                l.debit_amount,
                l.credit_amount,
                l.balance_amount as cl_amount,
                l.debit_gold as gold_debit_wt,
                l.credit_gold as gold_credit_wt,
                l.balance_gold as gold_cl_wt,
                CASE 
                    WHEN l.customer_id > 0 THEN 'Customer'
                    ELSE 'Account'
                END as ledger_type
            FROM tbl_customer_ledger l
            WHERE $where_clause
            ORDER BY l.transaction_date DESC, l.id DESC
        ";
    }
    
    $total_record = getRecord("SELECT COUNT(*) as total FROM tbl_customer_ledger l WHERE $where_clause");
    $total_records = $total_record ? (int)$total_record['total'] : 0;
    $total_pages = $total_records > 0 ? ceil($total_records / $per_page) : 1;
    
    $ledger_data = getList($ledger_query . " LIMIT $per_page OFFSET $offset");
    
    // Calculate totals for View All Ledger tab
    $total_debit_all = 0;
    $total_credit_all = 0;
    $total_cl_amount_all = 0;
    $total_gold_debit_wt_all = 0;
    $total_gold_credit_wt_all = 0;
    $total_gold_cl_wt_all = 0;
    
    // Get totals from all records (not just current page)
    $totals_query = "
        SELECT 
            SUM(l.debit_amount) as total_debit,
            SUM(l.credit_amount) as total_credit,
            MAX(l.balance_amount) as max_balance,
            SUM(l.debit_gold) as total_gold_debit,
            SUM(l.credit_gold) as total_gold_credit,
            MAX(l.balance_gold) as max_gold_balance
        FROM tbl_customer_ledger l
        WHERE $where_clause
    ";
    $totals_result = getRecord($totals_query);
    if ($totals_result) {
        $total_debit_all = (float)($totals_result['total_debit'] ?? 0);
        $total_credit_all = (float)($totals_result['total_credit'] ?? 0);
        // For closing amount, get the last balance
        $last_balance = getRecord("SELECT balance_amount, balance_gold FROM tbl_customer_ledger l WHERE $where_clause ORDER BY l.transaction_date DESC, l.id DESC LIMIT 1");
        $total_cl_amount_all = $last_balance ? (float)($last_balance['balance_amount'] ?? 0) : 0;
        $total_gold_debit_wt_all = (float)($totals_result['total_gold_debit'] ?? 0);
        $total_gold_credit_wt_all = (float)($totals_result['total_gold_credit'] ?? 0);
        $total_gold_cl_wt_all = $last_balance ? (float)($last_balance['balance_gold'] ?? 0) : 0;
    }
}

// Ensure total_records is always set
if (!isset($total_records)) {
    $total_records = 0;
}
if (!isset($total_pages)) {
    $total_pages = 1;
}

/**
 * Build URL query for pagination and tab links (supports array filter params).
 */
function tr_build_query(array $overrides = []) {
    global $active_tab, $per_page, $page, $transaction_page, $date_range, $from_date, $to_date,
        $invoice_no_raw, $tr_resolved_branch_ids, $bill_to_bill_raw, $ledger_names_sel, $group_ids,
        $ledger_types_sel, $filter_voucher_keys, $filter_against_voucher_keys,
        $against_inv_no_raw, $only_balance, $search_raw, $transaction_voucher_filter;
    $q = [];
    if (!empty($date_range)) {
        $q['date_range'] = $date_range;
    } else {
        if (!empty($from_date)) {
            $q['from_date'] = $from_date;
        }
        if (!empty($to_date)) {
            $q['to_date'] = $to_date;
        }
    }
    if ($invoice_no_raw !== '') {
        $q['invoice_no'] = $invoice_no_raw;
    }
    if (!empty($tr_resolved_branch_ids)) {
        $q['branch_id'] = $tr_resolved_branch_ids;
    }
    if ($bill_to_bill_raw !== '') {
        $q['bill_to_bill'] = $bill_to_bill_raw;
    }
    if (!empty($ledger_names_sel)) {
        $q['ledger_name'] = $ledger_names_sel;
    }
    if (!empty($group_ids)) {
        $q['group'] = $group_ids;
    }
    if (!empty($ledger_types_sel)) {
        $q['ledger_type'] = $ledger_types_sel;
    }
    if (!empty($filter_voucher_keys)) {
        $q['voucher_type'] = $filter_voucher_keys;
    }
    if (!empty($filter_against_voucher_keys)) {
        $q['against_voucher_type'] = $filter_against_voucher_keys;
    }
    if ($against_inv_no_raw !== '') {
        $q['against_inv_no'] = $against_inv_no_raw;
    }
    if ($only_balance) {
        $q['only_balance'] = 1;
    }
    if ($search_raw !== '') {
        $q['search'] = $search_raw;
    }
    if (!empty($transaction_voucher_filter)) {
        $q['transaction_voucher_type'] = $transaction_voucher_filter;
    }
    $q['per_page'] = $per_page;
    $q = array_merge($q, $overrides);
    if (!isset($q['tab'])) {
        $q['tab'] = $active_tab;
    }
    if (!isset($q['page'])) {
        $cur = ($active_tab === 'transactions' && isset($transaction_page)) ? (int) $transaction_page : (int) $page;
        $q['page'] = max(1, $cur);
    }
    return '?' . http_build_query($q);
}

?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Transaction Report - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?> Software</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
<?php
$AURAGOLD_REPORT_PAGE = true;
include 'header-script.php';
?>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
</head>

<style>
html, body {
    overflow-x: hidden !important;
    height: 100vh;
    background: #f4f6fb;
    
}

.layout-content {
    height: calc(100vh - 60px);
    overflow: hidden;
}

.container-fluid {
    height: 100%;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    padding: 0;
}

/* Page Header */
.page-header-bar {
    background: #11294b;
    color: #fff;
    padding: 12px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 600;
    font-size: 12px;
}

.page-header-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.page-header-actions .btn-icon {
    background: rgba(255,255,255,0.2);
    border: none;
    color: #fff;
    width: 32px;
    height: 32px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
}

.page-header-actions .btn-icon:hover {
    background: rgba(255,255,255,0.3);
}

.page-header-actions .badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #dc2626;
    color: #fff;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Tabs */
.tabs-container {
    background: #fff;
    border-bottom: 2px solid #e2e8f0;
    padding: 0 20px;
}

.tabs-list {
    display: flex;
    gap: 0;
    margin: 0;
    padding: 0;
    list-style: none;
}

.tabs-list li {
    margin: 0;
}

.tab-link {
    display: block;
    padding: 4px 10px;
    color: #64748b;
    text-decoration: none;
    border-bottom: 2px solid #c5a864;
    transition: all 0.2s;
    font-weight: 500;
}

.tab-link:hover {
    color: #11294b;
    background: #f8fafc;
}

.tab-link.active {
    color: #11294b;
    border-bottom-color: #11294b;
    font-weight: 600;
}

/* Toolbar */
.toolbar {
    background: #fff;
    padding: 12px 20px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.toolbar-left {
    display: flex;
    gap: 10px;
    align-items: center;
}

.toolbar-right {
    display: flex;
    gap: 10px;
    align-items: center;
}

.btn-filter {
    background: #fff;
    border: 1px solid #e2e8f0;
    color: #64748b;
    padding: 6px 12px;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
}

.btn-filter:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
}

/* Voucher type (Invoices & Vouchers tab) — custom chevron + height aligned with Apply */
.tr-toolbar-voucher-wrap {
    position: relative;
    min-width: 200px;
    max-width: min(300px, 100%);
}
.tr-toolbar-voucher-wrap .tr-toolbar-voucher-select {
    display: block;
    width: 100%;
    margin: 0;
    padding: 7px 36px 7px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 500;
    color: #11294b;
    background-color: #fff;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%234f6b8a' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    min-height: 34px;
    line-height: 1.35;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    transition: border-color 0.15s, box-shadow 0.15s;
}
.tr-toolbar-voucher-wrap .tr-toolbar-voucher-select:hover {
    border-color: #cbd5e1;
}
.tr-toolbar-voucher-wrap .tr-toolbar-voucher-select:focus {
    outline: none;
    border-color: #11294b;
    box-shadow: 0 0 0 2px rgba(17, 41, 75, 0.15);
}

/* Header export menu: inline display:none was blocking .show (menu never appeared) */
.page-header-actions .dropdown {
    position: relative;
}
.page-header-actions .dropdown > .dropdown-menu {
    display: none;
    position: absolute;
    right: 0;
    top: calc(100% + 4px);
    min-width: 180px;
    margin: 0;
    padding: 6px 0;
    list-style: none;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
    z-index: 1100;
}
.page-header-actions .dropdown > .dropdown-menu.show {
    display: block !important;
}
.page-header-actions .dropdown .dropdown-item {
    display: block;
    padding: 8px 16px;
    color: #334155;
    font-size: 12px;
    text-decoration: none;
    cursor: pointer;
}
.page-header-actions .dropdown .dropdown-item:hover {
    background: #f1f5f9;
    color: #11294b;
}

.btn-export {
    background: #11294b;
    border: none;
    color: #fff;
    padding: 6px 12px;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
}

.btn-export:hover {
    background: #4a2b7c;
}

/* Table Container */
.table-container {
    flex: 1;
    overflow: auto;
    background: #fff;
    margin: 4px;
    border-radius: 8px 8px 0 0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.table {
    width: 100%;
    margin: 0;
    font-size: 12px;
}




.table tbody tr:hover {
    background: #f8fafc;
}

.table tbody tr.total-row {
    background: #f1edff;
    font-weight: 600;
}

.table tbody tr.total-row td {
    border-top: 2px solid #e2e8f0;
    border-bottom: 2px solid #e2e8f0;
}

.btn-view-all {
    background: #11294b;
    color: #fff;
    border: none;
    padding: 4px 12px;
    border-radius: 4px;
    font-size: 12px;
    cursor: pointer;
    font-weight: 500;
}

.btn-view-all:hover {
    background: #4a2b7c;
}

.crdr-badge {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
}

.crdr-badge.dr {
    background: #fee2e2;
    color: #dc2626;
}

.crdr-badge.cr {
    background: #dbeafe;
    color: #2563eb;
}

/* Total Row in Footer */
.table-footer-total {
    background: #f1edff;
    font-weight: 600;
    border-top: 2px solid #e2e8f0;
}

.table-footer-total td {
    padding: 12px;
    border-bottom: 2px solid #e2e8f0;
}

/* Pagination */
.pagination-container {
    background: #fff;
    padding: 12px 20px;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 0 20px 20px 20px;
    border-radius: 0 0 8px 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.pagination-right {
    display: flex;
    gap: 10px;
    align-items: center;
}

.per-page-dropdown {
    position: relative;
}

.per-page-dropdown select {
    padding: 6px 30px 6px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    font-size: 12px;
    color: #64748b;
    background: #fff;
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    padding-right: 35px;
}

.per-page-dropdown select:hover {
    border-color: #cbd5e1;
}

.pagination-info {
    color: #64748b;
    font-size: 12px;
}

.pagination {
    display: flex;
    gap: 5px;
    align-items: center;
}

.pagination .page-link {
    padding: 6px 12px;
    border: 1px solid #e2e8f0;
    color: #64748b;
    text-decoration: none;
    border-radius: 4px;
    font-size: 12px;
}

.pagination .page-link:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
}

.pagination .page-link.active {
    background: #11294b;
    color: #fff;
    border-color: #11294b;
}

.pagination .page-link.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Filter Modal */
.filter-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.filter-modal.active {
    display: flex;
}

.filter-modal-content {
    background: #fff;
    border-radius: 8px;
    padding: 0;
    width: min(960px, calc(100vw - 32px));
    max-width: 960px;
    max-height: 90vh;
    overflow: visible;
}

.filter-modal-header {
    background: #11294b;
    color: #fff;
    padding: 15px 20px;
    border-radius: 8px 8px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0;
    border-bottom: none;
}

.filter-modal-header h5 {
    margin: 0;
    color: #fff;
    font-weight: 600;
}

.filter-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    color: #fff;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.filter-modal-close:hover {
    color: #f0f0f0;
}

.filter-modal-body {
    padding: 20px;
    overflow: visible;
}

#filterModal .mp-ms-panel {
    z-index: 1100;
}

#filterModal .filter-grid .tr-adv-section.filter-field-full {
    grid-column: 1 / -1;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
    margin: 12px 0 6px;
    padding-bottom: 6px;
    border-bottom: 1px solid #e2e8f0;
}

#filterModal .filter-grid .tr-adv-section.filter-field-full:first-child {
    margin-top: 0;
}

#filterModal .filter-field > .filter-field-label {
    margin: 0;
    color: #435474;
    font-weight: 600;
    font-size: 13px;
}



.filter-form-group.full-width {
    grid-column: 1 / -1;
}

.date-range-input {
    position: relative;
}

.date-range-input input {
    padding-right: 60px;
}

.date-range-icons {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    display: flex;
    gap: 5px;
}

.date-range-icons i {
    color: #64748b;
    cursor: pointer;
    font-size: 16px;
}

.date-range-icons i:hover {
    color: #11294b;
}


.filter-form-group {
    margin-bottom: 15px;
}


.filter-form-group input,
.filter-form-group select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 12px;
}

.filter-modal-footer {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #e2e8f0;
}

.btn-cancel {
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    color: #64748b;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
}

.btn-apply {
    background: linear-gradient(135deg, #11294b 0%, #7c5ba8 100%);
    border: none;
    color: #fff;
    padding: 8px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
}

.btn-apply:hover {
    background: linear-gradient(135deg, #4a2b7c 0%, #6c4b98 100%);
}

.btn-clear {
    background: #fff;
    border: 1px solid #ec4899;
    color: #ec4899;
    padding: 8px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
}

.btn-clear:hover {
    background: #fdf2f8;
}

/* Transaction list — Jewelstep-style 6 columns + borders */
.tabs-container + .toolbar {
    padding-top: 6px;
    padding-bottom: 6px;
}

.transaction-list-container {
    margin: 0;
    padding: 0;
    background: #f4f6fb;
    border-radius: 0;
    box-shadow: none;
}

.transaction-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 0 4px 8px;
}

.no-transactions {
    text-align: center;
    padding: 48px 20px;
    color: #64748b;
    font-size: 15px;
}

.transaction-card {
    position: relative;
    display: grid;
    grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr) minmax(0, 1fr) minmax(0, 0.85fr) minmax(0, 0.85fr) minmax(0, 1.15fr);
    align-items: stretch;
    column-gap: 0;
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    padding: 6px 0 10px;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
    box-sizing: border-box;
    transition: box-shadow 0.2s ease;
    overflow: hidden;
    min-width: 920px;
}

.transaction-card:hover {
    box-shadow: 0 3px 12px rgba(0, 0, 0, 0.08);
}

.transaction-col {
    padding: 4px 14px 8px;
    border-right: 1px solid #e0e0e0;
    min-width: 0;
}

.transaction-col:last-child {
    border-right: none;
}

.transaction-col-4,
.transaction-col-5 {
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.transaction-col-6 {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: flex-start;
}

.voucher-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.06em;
    color: #fff;
    margin-bottom: 4px;
}

/* Pink/magenta purchase badge (Jewelstep); other types stay distinct */
.voucher-purchase_invoice { background: #db2777; }
.voucher-sale_invoice { background: #059669; }
.voucher-sale_order { background: #2563eb; }
.voucher-jobwork_order { background: #6366f1; }
.voucher-sale_return { background: #d97706; }
.voucher-purchase_return { background: #be185d; }
.voucher-sale_quotation { background: #4f46e5; }
.voucher-purchase_quotation { background: #7c3aed; }
.voucher-material_issue { background: #b45309; }
.voucher-material_receive { background: #0ea5e9; }
.voucher-sale_fixing_direct { background: #ca8a04; }
.voucher-purchase_fixing_direct { background: #0d9488; }
.voucher-old_jewelry_scrap_invoice { background: #7e22ce; }
.voucher-payment_voucher { background: #0f766e; }
.voucher-receipt_voucher { background: #1d4ed8; }
.voucher-sale_receipt_voucher { background: #0f766e; }
.voucher-advance_payment { background: #9d174d; }
.voucher-metal_to_amount { background: #0f3d5c; }
.voucher-amount_to_metal { background: #155e75; }
.voucher-investment_fund_installment { background: #b45309; }
.voucher-investment_fund_transfer { background: #92400e; }
.voucher-journal_voucher { background: #4338ca; }
.voucher-contra_voucher { background: #0f766e; }

.invoice-label {
    display: block;
    font-size: 11px;
    font-weight: 500;
    color: #888888;
    margin-bottom: 2px;
}

.voucher-no .voucher-no-link {
    display: block;
    font-size: 18px;
    font-weight: 700;
    color: #111827;
    line-height: 1.25;
    letter-spacing: -0.02em;
    text-decoration: none;
    cursor: pointer;
}

.voucher-no .voucher-no-link:hover {
    color: #2563eb;
    text-decoration: underline;
}

.branch-name {
    font-size: 11px;
    color: #888888;
    margin-top: 4px;
}

.party-name {
    font-size: 14px;
    font-weight: 700;
    color: #1e1b4b;
    margin-bottom: 6px;
    line-height: 1.35;
}

.party-meta {
    font-size: 12px;
    color: #888888;
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 3px;
}

.party-meta i {
    color: #22c55e;
    flex-shrink: 0;
    width: 14px;
}

.party-meta + .party-meta i {
    color: #ef4444;
}

.col3-party {
    font-size: 14px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 6px;
    line-height: 1.3;
    word-break: break-word;
}

.col3-date {
    font-size: 12px;
    color: #888888;
    margin-bottom: 4px;
}

.col3-extra {
    font-size: 12px;
    color: #888888;
}

.trans-financials-item .label {
    display: block;
    font-size: 11px;
    color: #888888;
    margin-bottom: 2px;
}

.amount-value {
    display: block;
    font-size: 18px;
    font-weight: 700;
    color: #2563eb;
    line-height: 1.2;
}

.balance-value {
    display: block;
    font-size: 18px;
    font-weight: 700;
    color: #dc2626;
    line-height: 1.2;
}

.balance-value.negative {
    color: #b91c1c;
}

.transaction-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
    width: 100%;
}

.action-icon {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: 1px solid #d1d5db;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    background: #fff;
    text-decoration: none;
    transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}

.action-icon:hover {
    background: #f8fafc;
    border-color: #94a3b8;
    color: #334155;
}

.action-icon.btn-delete-transaction {
    cursor: pointer;
    font-size: inherit;
    padding: 0;
}

.action-icon.btn-delete-transaction:hover {
    background: #fef2f2;
    border-color: #fecaca;
    color: #dc2626;
}

.action-icon-disabled,
.action-icon:disabled {
    opacity: 0.45;
    cursor: not-allowed;
    pointer-events: none;
}

button.action-icon.action-icon-disabled {
    pointer-events: none;
}

button.action-icon {
    cursor: pointer;
    font-size: inherit;
    padding: 0;
}

.action-icon.action-icon-whatsapp {
    color: #25d366;
    border-color: #86efac;
}

.action-icon.action-icon-whatsapp:hover {
    background: #f0fdf4;
    border-color: #25d366;
    color: #128c7e;
}

.action-icon.action-icon-whatsapp i {
    font-size: 16px;
    line-height: 1;
}

.tr-whatsapp-wrap {
    position: relative;
    display: inline-flex;
    vertical-align: middle;
    z-index: 1;
}

.tr-whatsapp-wrap.is-open {
    z-index: 10050;
}

.tr-whatsapp-menu {
    display: none;
    position: fixed;
    min-width: 168px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 10px 28px rgba(17, 41, 75, 0.18);
    z-index: 10051;
    padding: 4px 0;
}

.tr-whatsapp-menu.is-open {
    display: block;
}

.tr-whatsapp-opt {
    display: block;
    width: 100%;
    text-align: left;
    border: none;
    background: transparent;
    padding: 8px 12px;
    font-size: 12px;
    font-weight: 600;
    color: #11294b;
    cursor: pointer;
    font-family: inherit;
}

.tr-whatsapp-opt:hover {
    background: #f0fdf4;
    color: #128c7e;
}

.tr-whatsapp-opt + .tr-whatsapp-opt {
    border-top: 1px solid #f1f5f9;
}

.action-icon.action-icon-sms i {
    font-size: 15px;
    line-height: 1;
}

.action-icon.action-icon-mail {
    color: #11294b;
    border-color: #cbd5e1;
}

.action-icon.action-icon-mail:hover:not(:disabled) {
    background: #eff6ff;
    border-color: #11294b;
    color: #0f172a;
}

.action-icon.action-icon-mail i {
    font-size: 16px;
    line-height: 1;
}

.voucher-no-static {
    display: block;
    font-size: 18px;
    font-weight: 700;
    color: #111827;
    line-height: 1.25;
    letter-spacing: -0.02em;
}

.transaction-list-wrap {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

@media (max-width: 1100px) {
    .transaction-card {
        min-width: 880px;
    }
}

/* Delete confirmation — custom modal (transaction report) */
.tr-delete-modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 10050;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px 16px;
    background: rgba(15, 23, 42, 0.48);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: opacity 0.22s ease, visibility 0.22s ease;
}
.tr-delete-modal-overlay.active {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
}
.tr-delete-modal {
    position: relative;
    width: 100%;
    max-width: 400px;
    background: #fff;
    border-radius: 18px;
    box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.28), 0 0 0 1px rgba(148, 163, 184, 0.12);
    padding: 2rem 1.75rem 1.75rem;
    text-align: center;
    transform: translateY(12px) scale(0.98);
    transition: transform 0.24s cubic-bezier(0.34, 1.2, 0.64, 1);
}
.tr-delete-modal-overlay.active .tr-delete-modal {
    transform: translateY(0) scale(1);
}
.tr-delete-modal-close {
    position: absolute;
    top: 14px;
    right: 14px;
    width: 36px;
    height: 36px;
    border: none;
    background: transparent;
    color: #94a3b8;
    font-size: 1.5rem;
    line-height: 1;
    cursor: pointer;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s ease, color 0.15s ease;
}
.tr-delete-modal-close:hover {
    background: #f1f5f9;
    color: #475569;
}
.tr-delete-modal-illustr {
    display: flex;
    justify-content: center;
    margin-bottom: 1.25rem;
}
.tr-delete-modal-illustr-doc {
    width: 72px;
    height: 72px;
    border-radius: 16px;
    background: linear-gradient(145deg, #e9d5ff 0%, #ddd6fe 45%, #c4b5fd 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 20px rgba(139, 92, 246, 0.22);
}
.tr-delete-modal-illustr-doc i {
    font-size: 28px;
    color: #5b21b6;
    stroke-width: 2;
}
.tr-delete-modal-title {
    font-size: 1.35rem;
    font-weight: 700;
    color: #11294b;
    margin: 0 0 0.5rem;
    letter-spacing: -0.02em;
}
.tr-delete-modal-desc {
    font-size: 0.95rem;
    line-height: 1.55;
    color: #64748b;
    margin: 0 0 1.5rem;
}
.tr-delete-modal-desc strong {
    color: #334155;
    font-weight: 600;
}
.tr-delete-modal-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
    flex-wrap: wrap;
}
.tr-delete-modal-btn {
    flex: 1;
    min-width: 120px;
    padding: 0.7rem 1.1rem;
    font-size: 0.95rem;
    font-weight: 700;
    border-radius: 12px;
    border: none;
    cursor: pointer;
    transition: transform 0.12s ease, box-shadow 0.12s ease, opacity 0.15s ease;
}
.tr-delete-modal-btn:active {
    transform: scale(0.98);
}
.tr-delete-modal-btn-primary {
    background: #11294b;
    color: #fff;
    box-shadow: 0 4px 14px rgba(17, 41, 75, 0.35);
}
.tr-delete-modal-btn-primary:hover {
    background: #0d1f3a;
    box-shadow: 0 6px 18px rgba(17, 41, 75, 0.4);
}
.tr-delete-modal-btn-secondary {
    background: #fdf2f8;
    color: #be185d;
    box-shadow: 0 1px 0 rgba(255, 255, 255, 0.8) inset;
}
.tr-delete-modal-btn-secondary:hover {
    background: #fce7f3;
    color: #9d174d;
}

/* Email compose modal (transaction report) */
.tr-mail-modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 10060;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px 16px;
    background: rgba(15, 23, 42, 0.48);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: opacity 0.22s ease, visibility 0.22s ease;
}
.tr-mail-modal-overlay.active {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
}
.tr-mail-modal {
    position: relative;
    width: 100%;
    max-width: 760px;
    max-height: calc(100vh - 48px);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.28), 0 0 0 1px rgba(148, 163, 184, 0.12);
    transform: translateY(12px) scale(0.98);
    transition: transform 0.24s cubic-bezier(0.34, 1.2, 0.64, 1);
}
.tr-mail-modal-overlay.active .tr-mail-modal {
    transform: translateY(0) scale(1);
}
.tr-mail-modal-close {
    position: absolute;
    top: 12px;
    right: 12px;
    width: 34px;
    height: 34px;
    border: none;
    border-radius: 8px;
    background: #f1f5f9;
    color: #475569;
    font-size: 1.25rem;
    line-height: 1;
    cursor: pointer;
    z-index: 2;
}
.tr-mail-modal-close:hover { background: #e2e8f0; color: #1e293b; }
.tr-mail-modal-header {
    padding: 1.1rem 1.25rem 0.75rem;
    border-bottom: 1px solid #e2e8f0;
    padding-right: 3rem;
}
.tr-mail-modal-title {
    margin: 0 0 4px;
    font-size: 1.05rem;
    font-weight: 700;
    color: #1e293b;
}
.tr-mail-modal-sub {
    margin: 0;
    font-size: 0.75rem;
    color: #64748b;
}
.tr-mail-modal-body {
    padding: 1rem 1.25rem;
    overflow-y: auto;
    flex: 1;
}
.tr-mail-field { margin-bottom: 12px; }
.tr-mail-field label {
    display: block;
    font-size: 0.72rem;
    font-weight: 600;
    color: #334155;
    margin-bottom: 4px;
}
.tr-mail-field input[type="text"],
.tr-mail-field input[type="email"] {
    width: 100%;
    padding: 7px 10px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 0.82rem;
}
.tr-mail-editor-wrap {
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    background: #fff;
}
.tr-mail-editor-wrap .ql-toolbar {
    border: none;
    border-bottom: 1px solid #e2e8f0;
    border-radius: 6px 6px 0 0;
    background: #f8fafc;
}
.tr-mail-editor-wrap .ql-container {
    border: none;
    min-height: 140px;
    font-size: 0.82rem;
}
.tr-mail-editor-wrap .ql-editor { min-height: 140px; }
.tr-mail-tabs {
    display: flex;
    gap: 6px;
    margin-bottom: 8px;
}
.tr-mail-tab {
    padding: 5px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    background: #f8fafc;
    font-size: 0.72rem;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
}
.tr-mail-tab.active {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
}
.tr-mail-preview-pane {
    display: none;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 12px 14px;
    min-height: 160px;
    background: #fafafa;
    font-size: 0.82rem;
    line-height: 1.5;
    color: #334155;
}
.tr-mail-preview-pane.active { display: block; }
.tr-mail-preview-subject {
    font-weight: 700;
    margin-bottom: 10px;
    padding-bottom: 8px;
    border-bottom: 1px dashed #cbd5e1;
    color: #1e293b;
}
.tr-mail-attach-note {
    margin-top: 8px;
    font-size: 0.7rem;
    color: #0369a1;
    background: #e0f2fe;
    border: 1px solid #bae6fd;
    border-radius: 6px;
    padding: 6px 10px;
}
.tr-mail-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 0.85rem 1.25rem 1.1rem;
    border-top: 1px solid #e2e8f0;
    background: #fafbfc;
}
.tr-mail-modal-btn {
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 0.78rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
}
.tr-mail-modal-btn-primary {
    background: #2563eb;
    color: #fff;
}
.tr-mail-modal-btn-primary:hover:not(:disabled) { background: #1d4ed8; }
.tr-mail-modal-btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.tr-mail-modal-btn-secondary {
    background: #f1f5f9;
    color: #475569;
}
.tr-mail-modal-btn-secondary:hover { background: #e2e8f0; }
.tr-mail-loading {
    text-align: center;
    padding: 2rem 1rem;
    color: #64748b;
    font-size: 0.85rem;
}
</style>

<body class="report-page">
<?php include 'sidebar.php'; ?>

<div class="layout-content">
<div class="container-fluid flex-grow-1" style="padding-top:0;padding-bottom:0;">

<!-- Page Header -->
<div class="page-header-bar">
    <div>Transaction Report</div>
    <div class="page-header-actions">
        <button class="btn-icon" onclick="openFilterModal()" title="Filter">
            <i class="feather icon-filter"></i>
            <?php 
            $filter_count = 0;
            if (!empty($date_range) || !empty($from_date) || !empty($to_date)) $filter_count++;
            if (!empty($invoice_no)) $filter_count++;
            // Count branch only when user explicitly chose it in Advance Filter
            if (!empty($branch_ids)) $filter_count++;
            if (!empty($bill_to_bill)) $filter_count++;
            if (!empty($ledger_names_sel)) $filter_count++;
            if (!empty($group_ids)) $filter_count++;
            if (!empty($ledger_types_sel)) $filter_count++;
            if (!empty($filter_voucher_keys)) $filter_count++;
            if (!empty($filter_against_voucher_keys)) $filter_count++;
            if (!empty($against_inv_no)) $filter_count++;
            if (!empty($only_balance)) $filter_count++;
            if (!empty($search)) $filter_count++;
            if ($active_tab == 'transactions' && !empty($transaction_voucher_filter)) $filter_count++;
            if ($filter_count > 0): ?>
            <span class="badge"><?php echo $filter_count; ?></span>
            <?php endif; ?>
        </button>
        <button class="btn-icon" onclick="location.reload()" title="Refresh">
            <i class="feather icon-refresh-cw"></i>
        </button>
        <div class="dropdown">
            <button type="button" class="btn-icon" onclick="event.stopPropagation(); (function(btn){ var m=btn.nextElementSibling; if(m) m.classList.toggle('show'); })(this)" title="Export">
                <i class="feather icon-download"></i>
            </button>
            <div class="dropdown-menu">
                <a class="dropdown-item" href="#" onclick="exportToExcel(); return false;">Export to Excel</a>
                <a class="dropdown-item" href="#" onclick="exportToPDF(); return false;">Export to PDF</a>
            </div>
        </div>
        <button class="btn-icon" title="Settings">
            <i class="feather icon-settings"></i>
        </button>
    </div>
</div>

<!-- Tabs -->
<div class="tabs-container">
    <ul class="tabs-list">
        <li>
            <a href="<?php echo htmlspecialchars(tr_build_query(['tab' => 'transactions', 'page' => 1])); ?>" 
               class="tab-link <?php echo $active_tab == 'transactions' ? 'active' : ''; ?>">
                Invoices &amp; Vouchers
            </a>
        </li>
        <li>
            <a href="<?php echo htmlspecialchars(tr_build_query(['tab' => 'balance', 'page' => 1])); ?>" 
               class="tab-link <?php echo $active_tab == 'balance' ? 'active' : ''; ?>">
                Balance Amounts
            </a>
        </li>
        <li>
            <a href="<?php echo htmlspecialchars(tr_build_query(['tab' => 'all', 'page' => 1])); ?>" 
               class="tab-link <?php echo $active_tab == 'all' ? 'active' : ''; ?>">
                View All Ledger
            </a>
        </li>
    </ul>
</div>

<?php if ($active_tab == 'transactions'): ?>
<!-- Transaction List (Jewelstep-style) -->
<div class="toolbar">
    <div class="toolbar-left">
        <form method="GET" class="d-flex align-items-center" style="gap: 8px;">
            <input type="hidden" name="tab" value="transactions">
            <input type="hidden" name="per_page" value="<?php echo $per_page; ?>">
            <?php if (!empty($from_date)) { ?><input type="hidden" name="from_date" value="<?php echo htmlspecialchars($from_date); ?>"><?php } ?>
            <?php if (!empty($to_date)) { ?><input type="hidden" name="to_date" value="<?php echo htmlspecialchars($to_date); ?>"><?php } ?>
            <?php if ($search_raw !== '') { ?><input type="hidden" name="search" value="<?php echo htmlspecialchars($search_raw); ?>"><?php } ?>
            <div class="tr-toolbar-voucher-wrap">
            <label for="trTransactionVoucherType" class="sr-only">Voucher type</label>
            <select name="transaction_voucher_type" id="trTransactionVoucherType" class="tr-toolbar-voucher-select" title="Voucher type">
                <?php foreach ($transaction_list_types as $val => $label): ?>
                <option value="<?php echo htmlspecialchars($val); ?>" <?php echo $transaction_voucher_filter === $val ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
            </select>
            </div>
            <button type="submit" class="btn-filter">Apply</button>
        </form>
        <button class="btn-icon" onclick="location.reload()" title="Refresh"><i class="feather icon-refresh-cw"></i></button>
    </div>
</div>

<div class="table-container transaction-list-container">
    <div class="transaction-list-wrap">
    <div class="transaction-list">
        <?php
        $tr_unified_voucher_types = ['payment_voucher', 'receipt_voucher', 'sale_receipt_voucher', 'advance_payment'];
        $tr_app_base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
            . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
            . str_replace('\\', '/', rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\'));
        ?>
        <?php if (!empty($transactions_list)): ?>
            <?php foreach ($transactions_list as $t): ?>
            <?php
                $is_sfd = (($t['type'] ?? '') === 'sale_fixing_direct');
                $is_pfd = (($t['type'] ?? '') === 'purchase_fixing_direct');
                $is_ojb = (($t['type'] ?? '') === 'old_jewelry_scrap_invoice');
                $is_jwo = (($t['type'] ?? '') === 'jobwork_order');
                $is_fixing_row = $is_sfd || $is_pfd;
                $voucher_key_upper = strtoupper(trim((string)($t['voucher_no'] ?? '')));
                $pi_delete_blocked = (($t['type'] ?? '') === 'purchase_invoice') && $voucher_key_upper !== '' && !empty($pi_invoice_nos_with_sfd[$voucher_key_upper]);
                $si_delete_blocked = (($t['type'] ?? '') === 'sale_invoice') && $voucher_key_upper !== '' && !empty($si_invoice_nos_with_pfd[$voucher_key_upper]);
                $so_delete_blocked = (($t['type'] ?? '') === 'sale_order') && !empty($so_ids_with_jwo[(int) ($t['id'] ?? 0)]);
                $jwo_delete_blocked = $is_jwo && (
                    !empty($jwo_ids_with_queue[(int) ($t['id'] ?? 0)])
                    || (function_exists('auragold_jobwork_order_has_invoice') && auragold_jobwork_order_has_invoice($conn, (int) ($t['id'] ?? 0)))
                );
                $ojb_delete_blocked = $is_ojb && !empty($t['linked_from_purchase']);
                $txn_delete_blocked = $pi_delete_blocked || $si_delete_blocked || $so_delete_blocked || $jwo_delete_blocked || $ojb_delete_blocked;
                $txn_delete_title = $pi_delete_blocked ? 'Delete the sale fixing first' : ($si_delete_blocked ? 'Delete the purchase fixing first' : ($so_delete_blocked ? 'Delete Job Work Order first (Transaction Report), then this Sale Order' : ($jwo_delete_blocked ? 'Delete Jobwork Queue records first (Manufacturing Process), then delete this Job Work Order' : ($ojb_delete_blocked ? 'Remove scrap payment on Purchase Invoice or delete the PI' : 'Delete'))));
                if (!empty($t['no_delete_from_report'])) {
                    $txn_delete_blocked = true;
                    $txn_delete_title = 'Open the voucher screen to delete or adjust this entry';
                }
                $is_unified_voucher = in_array($t['type'] ?? '', $tr_unified_voucher_types, true);
                $tr_mail_supported = in_array(($t['type'] ?? ''), ['sale_invoice', 'sale_order', 'sale_return', 'sale_quotation', 'purchase_invoice', 'purchase_return', 'purchase_quotation'], true);
                $tr_party_email = trim((string) ($t['party_email'] ?? ''));
                $tr_party_mobile = trim((string) ($t['party_mobile'] ?? ''));
                $tr_whatsapp_msg = auragold_tr_whatsapp_plain_message($t, $tr_app_base_url);
            ?>
            <div class="transaction-card">
                <div class="transaction-col transaction-col-1">
                    <span class="voucher-badge voucher-<?php echo htmlspecialchars($t['type']); ?>"><?php echo htmlspecialchars($t['type_label']); ?></span>
                    <span class="invoice-label"><?php echo $is_unified_voucher ? 'Voucher No' : 'Invoice No'; ?></span>
                    <div class="voucher-no"><?php 
                        $voucher_display = $t['voucher_no'] ?? '';
                        if (!empty($t['against_of']) && $is_fixing_row) {
                            $voucher_display .= ' (' . ($t['against_of']) . ')';
                        }
                        if ($is_fixing_row) {
                            echo '<span class="voucher-no-static">' . htmlspecialchars($voucher_display) . '</span>';
                        } elseif ($is_ojb) {
                            $vn_title = 'Edit';
                            $vn_base = htmlspecialchars($t['voucher_no'] ?? '');
                            $ap_ojb = trim((string) ($t['against_pi'] ?? ''));
                            if ($ap_ojb !== '') {
                                echo '<a href="' . htmlspecialchars($t['link']) . '" class="voucher-no-link" title="' . htmlspecialchars($vn_title) . '">' . $vn_base . ' <span style="color:#dc2626;font-weight:600;">(Against of ' . htmlspecialchars($ap_ojb) . ')</span></a>';
                            } else {
                                echo '<a href="' . htmlspecialchars($t['link']) . '" class="voucher-no-link" title="' . htmlspecialchars($vn_title) . '">' . $vn_base . '</a>';
                            }
                        } elseif ($is_jwo) {
                            $vn_title = 'Edit';
                            $vn_base = htmlspecialchars($t['voucher_no'] ?? '');
                            $aso = trim((string) ($t['against_sale_order_no'] ?? ''));
                            if ($aso !== '') {
                                echo '<a href="' . htmlspecialchars($t['link']) . '" class="voucher-no-link" title="' . htmlspecialchars($vn_title) . '">' . $vn_base . ' <span style="color:#dc2626;font-weight:600;">(Against of ' . htmlspecialchars($aso) . ')</span></a>';
                            } else {
                                echo '<a href="' . htmlspecialchars($t['link']) . '" class="voucher-no-link" title="' . htmlspecialchars($vn_title) . '">' . $vn_base . '</a>';
                            }
                        } else {
                            $vn_title = (($t['type'] ?? '') === 'sale_invoice' && !empty($si_delete_blocked))
                                ? 'Open invoice (save disabled until purchase fixing is deleted)'
                                : 'Edit';
                            echo '<a href="' . htmlspecialchars($t['link']) . '" class="voucher-no-link" title="' . htmlspecialchars($vn_title) . '">' . htmlspecialchars($voucher_display) . '</a>';
                        }
                    ?></div>
                    <div class="branch-name"><?php echo htmlspecialchars($t['branch_name'] ?? '—'); ?></div>
                </div>
                <div class="transaction-col transaction-col-2">
                    <div class="party-name"><?php echo htmlspecialchars($t['party_name']); ?></div>
                    <div class="party-meta" title="From customer / supplier master">
                        <i class="feather icon-phone"></i>
                        <?php if ($tr_party_mobile !== ''): ?>
                            <?php echo htmlspecialchars($tr_party_mobile); ?>
                        <?php else: ?>
                            NA
                        <?php endif; ?>
                    </div>
                    <div class="party-meta"><i class="feather icon-map-pin"></i> NA</div>
                    <?php if ($tr_mail_supported): ?>
                    <div class="party-meta party-meta-mail" title="From customer / supplier master (tbl_customers.mail_id)">
                        <i class="feather icon-mail"></i>
                        <?php if ($tr_party_email !== ''): ?>
                            <?php echo htmlspecialchars($tr_party_email); ?>
                        <?php else: ?>
                            <span class="text-muted" style="font-style:italic;">No email in master</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="transaction-col transaction-col-3">
                    <div class="col3-party"><?php echo htmlspecialchars(transaction_report_sp_display($t['sales_person'] ?? '')); ?></div>
                    <div class="col3-date"><?php echo $t['date'] ? date('d-m-Y', strtotime($t['date'])) : '—'; ?></div>
                    <div class="col3-extra"><?php
                        if ($is_ojb) {
                            echo htmlspecialchars($t['tx_ref_display'] ?? 'NA');
                        } elseif (!empty($t['voucher_ex_col3'])) {
                            echo htmlspecialchars($t['voucher_ex_col3']);
                        } elseif (!empty($t['against_of']) && $is_fixing_row) {
                            echo htmlspecialchars($t['against_of']);
                        } else {
                            echo 'NA';
                        }
                    ?></div>
                </div>
                <div class="transaction-col transaction-col-4">
                    <div class="trans-financials-item">
                        <?php if (($t['type'] ?? '') === 'purchase_invoice'): ?>
                        <span class="label">Total Amount</span>
                        <?php else: ?>
                        <span class="label">Amount</span>
                        <?php endif; ?>
                        <span class="amount-value"><?php echo number_format($t['amount'], 2); ?></span>
                    </div>
                </div>
                <div class="transaction-col transaction-col-5">
                    <div class="trans-financials-item">
                        <span class="label">Balance</span>
                        <?php
                        $bal = (($t['type'] ?? '') === 'purchase_invoice') ? (float)($t['amount'] ?? 0) : (float)($t['balance'] ?? 0);
                        $bal_signed = (isset($t['type']) && $t['type'] === 'purchase_return') ? -abs((float)($t['balance'] ?? 0)) : $bal;
                        $bal_neg = $bal_signed < 0;
                        $bal_txt = $bal_neg ? ('-' . number_format(abs($bal_signed), 2)) : number_format($bal_signed, 2);
                        ?>
                        <span class="balance-value<?php echo $bal_neg ? ' negative' : ''; ?>"><?php echo $bal_txt; ?></span>
                    </div>
                </div>
                <div class="transaction-col transaction-col-6">
                    <div class="transaction-actions">
                        <a href="<?php echo htmlspecialchars($t['link']); ?>" class="action-icon" title="View"><i class="feather icon-eye"></i></a>
                        <?php if ($is_fixing_row): ?>
                        <span class="action-icon action-icon-disabled" title="Edit not available for fixing direct"><i class="feather icon-edit"></i></span>
                        <?php else: ?>
                        <?php
                            $edit_title = (($t['type'] ?? '') === 'sale_invoice' && !empty($si_delete_blocked))
                                ? 'Edit (save disabled until purchase fixing is deleted)'
                                : ((($t['type'] ?? '') === 'sale_order' && !empty($so_delete_blocked))
                                    ? 'Edit (save disabled until Job Work Order is deleted)'
                                    : 'Edit');
                        ?>
                        <a href="<?php echo htmlspecialchars($t['link']); ?>" class="action-icon" title="<?php echo htmlspecialchars($edit_title); ?>"><i class="feather icon-edit"></i></a>
                        <?php endif; ?>
                        <a href="<?php echo htmlspecialchars($t['print_link']); ?>" target="_blank" class="action-icon" title="Print"><i class="feather icon-printer"></i></a>
                        <button type="button" class="action-icon btn-delete-transaction<?php echo $txn_delete_blocked ? ' action-icon-disabled' : ''; ?>" title="<?php echo htmlspecialchars($txn_delete_title); ?>" data-type="<?php echo htmlspecialchars($t['type']); ?>" data-id="<?php echo (int)$t['id']; ?>" data-voucher="<?php echo htmlspecialchars($t['voucher_no']); ?>"<?php echo $txn_delete_blocked ? ' disabled' : ''; ?>><i class="feather icon-trash-2"></i></button>
                        <div class="tr-whatsapp-wrap">
                            <button type="button" class="action-icon action-icon-whatsapp tr-whatsapp-toggle" title="WhatsApp"
                                data-party-mobile="<?php echo htmlspecialchars($tr_party_mobile, ENT_QUOTES, 'UTF-8'); ?>"
                                data-whatsapp-msg="<?php echo htmlspecialchars($tr_whatsapp_msg, ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="fab fa-whatsapp"></i>
                            </button>
                            <div class="tr-whatsapp-menu" role="menu">
                                <button type="button" class="tr-whatsapp-opt" data-mode="web" role="menuitem">WhatsApp Web</button>
                                <button type="button" class="tr-whatsapp-opt" data-mode="api" role="menuitem">WhatsApp API</button>
                            </div>
                        </div>
                        <button type="button" class="action-icon action-icon-sms" title="SMS" onclick="alert('API integration work in progress');"><i class="fas fa-sms"></i></button>
                        <button type="button" class="action-icon action-icon-mail<?php echo $tr_mail_supported ? '' : ' action-icon-disabled'; ?>" title="<?php
                            if (!$tr_mail_supported) {
                                echo 'Email not available for this voucher type';
                            } elseif ($tr_party_email !== '') {
                                echo 'Send email to ', htmlspecialchars($tr_party_email, ENT_QUOTES, 'UTF-8');
                            } else {
                                echo 'Send email — enter recipient in compose window';
                            }
                        ?>"<?php echo $tr_mail_supported ? '' : ' disabled'; ?> data-type="<?php echo htmlspecialchars($t['type']); ?>" data-id="<?php echo (int) $t['id']; ?>" data-party-email="<?php echo htmlspecialchars($tr_party_email, ENT_QUOTES, 'UTF-8'); ?>"><i class="feather icon-mail"></i></button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-transactions">No transactions found. Adjust filters, or add invoices, returns, quotations, old jewelry scrap, payment/receipt/advance vouchers.</div>
        <?php endif; ?>
    </div>
    </div>
</div>

<!-- Pagination for Transactions -->
<div class="pagination-container">
    <div class="pagination-info">
        Showing <?php echo $transaction_total_records > 0 ? $transaction_offset + 1 : 0; ?> to <?php echo min($transaction_offset + $per_page, $transaction_total_records); ?> of <?php echo $transaction_total_records; ?> entries
    </div>
    <div class="pagination-right">
        <div class="per-page-dropdown">
            <select onchange="changePerPageTransactions(this.value)">
                <option value="10" <?php echo $per_page == 10 ? 'selected' : ''; ?>>Show 10 Items</option>
                <option value="25" <?php echo $per_page == 25 ? 'selected' : ''; ?>>Show 25 Items</option>
                <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>Show 50 Items</option>
                <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>Show 100 Items</option>
            </select>
        </div>
        <div class="pagination">
            <?php if ($transaction_total_pages > 0): ?>
            <a href="<?php echo htmlspecialchars(tr_build_query(['tab' => 'transactions', 'page' => 1])); ?>" class="page-link <?php echo $transaction_page <= 1 ? 'disabled' : ''; ?>">&lt;&lt;</a>
            <a href="<?php echo htmlspecialchars(tr_build_query(['tab' => 'transactions', 'page' => max(1, $transaction_page - 1)])); ?>" class="page-link <?php echo $transaction_page <= 1 ? 'disabled' : ''; ?>">&lt;</a>
            <?php for ($i = max(1, $transaction_page - 2); $i <= min($transaction_total_pages, $transaction_page + 2); $i++): ?>
            <a href="<?php echo htmlspecialchars(tr_build_query(['tab' => 'transactions', 'page' => $i])); ?>" class="page-link <?php echo $i == $transaction_page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            <a href="<?php echo htmlspecialchars(tr_build_query(['tab' => 'transactions', 'page' => min($transaction_total_pages, $transaction_page + 1)])); ?>" class="page-link <?php echo $transaction_page >= $transaction_total_pages ? 'disabled' : ''; ?>">&gt;</a>
            <a href="<?php echo htmlspecialchars(tr_build_query(['tab' => 'transactions', 'page' => $transaction_total_pages])); ?>" class="page-link <?php echo $transaction_page >= $transaction_total_pages ? 'disabled' : ''; ?>">&gt;&gt;</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php else: ?>
<!-- DataTable Controls Bar -->
<div class="datatable-controls-bar" style="display: flex; justify-content: space-between; align-items: center; padding: 4px; background: #f8f9fa; border-bottom: 1px solid #e2e8f0; margin: 0 20px;">
    <div class="datatable-search" style="flex: 1;">
        <input type="text" id="customSearch" class="form-control" placeholder="Search in table..." style="max-width: 300px; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px;">
    </div>
    <div class="datatable-buttons" style="display: flex; gap: 10px;">
        <button id="exportExcelBtn" class="btn" style="background: #11294b; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 5px;">
            <i class="feather icon-download"></i> Export Excel
        </button>
        <button id="printBtn" class="btn" style="background: #11294b; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 5px;">
            <i class="feather icon-printer"></i> Print
        </button>
    </div>
</div>

<!-- Table Container -->
<div class="table-container">
    <table class="table" id="ledgerTable">
        <thead>
            <tr>
                <?php if ($active_tab == 'balance'): ?>
                    <th style="width: 100px;"></th>
                    <th>Ledger</th>
                    <th>Branch Name</th>
                    <th>Opening CrOrDr</th>
                    <th>Debit</th>
                    <th>Credit</th>
                    <th>Closing CrOrDr</th>
                    <th>Opening Wt</th>
                    <th>Debit Wt</th>
                    <th>Credit Wt</th>
                    <th>Closing Wt V...</th>
                    <th>Ledger Type</th>
                <?php else: ?>
                    <th style="width: 80px;"></th>
                    <th>Date</th>
                    <th>Ledger</th>
                    <th>Branch</th>
                    <th>Invoice No</th>
                    <th>Against Ledger</th>
                    <th>Against Invoice No</th>
                    <th>Type Of Voucher</th>
                    <th>Debit</th>
                    <th>Credit</th>
                    <th>CL. Amount</th>
                    <th>Gold Debit Wt</th>
                    <th>Gold Credit Wt</th>
                    <th>Gold CL. Wt</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if ($active_tab == 'balance'): ?>
                <?php if (!empty($ledger_data)): ?>
                    <?php foreach ($ledger_data as $ledger): ?>
                    <tr>
                        <td>
                            <button class="btn-view-all" onclick="viewLedgerDetails('<?php echo htmlspecialchars($ledger['ledger_name']); ?>', <?php echo $ledger['customer_id']; ?>)">
                                View All
                            </button>
                        </td>
                        <td><?php echo htmlspecialchars($ledger['ledger_name']); ?></td>
                        <td><?php echo htmlspecialchars($ledger['branch_name']); ?></td>
                        <td>
                            <?php echo number_format($ledger['opening_amt'], 2); ?> 
                            <span class="crdr-badge <?php echo strtolower($ledger['opening_crdr']); ?>">
                                <?php echo $ledger['opening_crdr']; ?>
                            </span>
                        </td>
                        <td><?php echo number_format($ledger['total_debit'], 2); ?></td>
                        <td><?php echo number_format($ledger['total_credit'], 2); ?></td>
                        <td>
                            <?php echo number_format($ledger['closing_amt'], 2); ?> 
                            <span class="crdr-badge <?php echo strtolower($ledger['closing_crdr']); ?>">
                                <?php echo $ledger['closing_crdr']; ?>
                            </span>
                        </td>
                        <td><?php echo number_format($ledger['opening_gold'] + $ledger['opening_silver'], 3); ?></td>
                        <td><?php echo number_format($ledger['total_debit_gold'] + $ledger['total_debit_silver'], 3); ?></td>
                        <td><?php echo number_format($ledger['total_credit_gold'] + $ledger['total_credit_silver'], 3); ?></td>
                        <td><?php echo number_format($ledger['closing_gold'] + $ledger['closing_silver'], 3); ?></td>
                        <td><?php echo htmlspecialchars($ledger['ledger_type']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="12" style="text-align: center; padding: 40px; color: #64748b;">
                            No ledger data found
                        </td>
                    </tr>
                <?php endif; ?>
            <?php else: ?>
                <!-- View All Ledger Tab -->
                <?php if (!empty($ledger_data)): ?>
                    <?php foreach ($ledger_data as $entry): ?>
                    <tr>
                        <td>
                            <?php 
                            $invoice_no_raw = $entry['invoice_no'] ?? '';
                            $transaction_type_raw = $entry['transaction_type'] ?? '';
                            $transaction_id = isset($entry['transaction_id']) ? (int)$entry['transaction_id'] : 0;
                            
                            // Use data attributes to avoid escaping issues with inline onclick
                            $invoice_no_attr = htmlspecialchars($invoice_no_raw, ENT_QUOTES, 'UTF-8');
                            $transaction_type_attr = htmlspecialchars($transaction_type_raw, ENT_QUOTES, 'UTF-8');
                            ?>
                            <button class="btn-view-all view-transaction-btn" 
                                    data-invoice-no="<?php echo $invoice_no_attr; ?>" 
                                    data-transaction-type="<?php echo $transaction_type_attr; ?>" 
                                    data-transaction-id="<?php echo $transaction_id; ?>">
                                View
                            </button>
                        </td>
                        <td><?php echo $entry['date'] ? date('d/m/Y', strtotime($entry['date'])) : ''; ?></td>
                        <td><?php echo htmlspecialchars($entry['ledger_name']); ?></td>
                        <td><?php echo htmlspecialchars($entry['branch_name'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($invoice_no_raw); ?></td>
                        <td><?php echo htmlspecialchars($entry['against_ledger'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($entry['against_invoice_no'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($entry['type_of_voucher'] ?? ''); ?></td>
                        <td style="color: #11294b;"><?php echo number_format($entry['debit_amount'], 2); ?></td>
                        <td style="color: #11294b;"><?php echo number_format($entry['credit_amount'], 2); ?></td>
                        <td style="background: #f1edff;"><?php echo number_format(abs($entry['cl_amount']), 2); ?></td>
                        <td><?php echo number_format($entry['gold_debit_wt'], 2); ?></td>
                        <td><?php echo number_format($entry['gold_credit_wt'], 2); ?></td>
                        <td style="background: #d1fae5;"><?php echo number_format($entry['gold_cl_wt'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <!-- Total Row for View All Ledger -->
                    <tr class="table-footer-total">
                        <td colspan="8"><strong>Total</strong></td>
                        <td><strong><?php echo number_format($total_debit_all, 2); ?></strong></td>
                        <td><strong><?php echo number_format($total_credit_all, 2); ?></strong></td>
                        <td><strong><?php echo number_format(abs($total_cl_amount_all), 2); ?></strong></td>
                        <td><strong><?php echo number_format($total_gold_debit_wt_all, 2); ?></strong></td>
                        <td><strong><?php echo number_format($total_gold_credit_wt_all, 2); ?></strong></td>
                        <td><strong><?php echo number_format($total_gold_cl_wt_all, 2); ?></strong></td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td colspan="14" style="text-align: center; padding: 40px; color: #64748b;">
                            No ledger entries found
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($active_tab == 'balance'): ?>
<!-- Total Row in Footer (Outside table-container) -->
<div style="background: #fff; margin: 0 20px; border-radius: 0 0 8px 8px; border-top: 2px solid #e2e8f0;">
    <table class="table" style="margin: 0;">
        <tbody>
            <tr class="table-footer-total">
                <td colspan="3" style="padding: 12px;"><strong>Total</strong></td>
                <td style="padding: 12px;">
                    <?php echo number_format(abs($total_opening), 2); ?> 
                    <span class="crdr-badge <?php echo $total_opening >= 0 ? 'dr' : 'cr'; ?>">
                        <?php echo $total_opening >= 0 ? 'Dr' : 'Cr'; ?>
                    </span>
                </td>
                <td style="padding: 12px;"><strong><?php echo number_format($total_debit, 2); ?></strong></td>
                <td style="padding: 12px;"><strong><?php echo number_format($total_credit, 2); ?></strong></td>
                <td style="padding: 12px;">
                    <?php echo number_format(abs($total_closing), 2); ?> 
                    <span class="crdr-badge <?php echo $total_closing >= 0 ? 'dr' : 'cr'; ?>">
                        <?php echo $total_closing >= 0 ? 'Dr' : 'Cr'; ?>
                    </span>
                </td>
                <td style="padding: 12px;">0.000</td>
                <td style="padding: 12px;">0.000</td>
                <td style="padding: 12px;">0.000</td>
                <td style="padding: 12px;">0.000</td>
                <td style="padding: 12px;"></td>
            </tr>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Pagination -->
<div class="pagination-container">
    <div class="pagination-info">
        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_records); ?> of <?php echo $total_records; ?> entries
    </div>
    <div class="pagination-right">
        <div class="per-page-dropdown">
            <select onchange="changePerPage(this.value)">
                <option value="5" <?php echo $per_page == 5 ? 'selected' : ''; ?>>Show 5 Items</option>
                <option value="10" <?php echo $per_page == 10 ? 'selected' : ''; ?>>Show 10 Items</option>
                <option value="25" <?php echo $per_page == 25 ? 'selected' : ''; ?>>Show 25 Items</option>
                <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>Show 50 Items</option>
                <option value="75" <?php echo $per_page == 75 ? 'selected' : ''; ?>>Show 75 Items</option>
                <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>Show 100 Items</option>
                <option value="999999" <?php echo $per_page >= 999999 ? 'selected' : ''; ?>>Show All Items</option>
            </select>
        </div>
        <div class="pagination">
            <?php if ($total_pages > 0): ?>
            <a href="<?php echo htmlspecialchars(tr_build_query(['page' => 1])); ?>"
               class="page-link <?php echo $page == 1 ? 'disabled' : ''; ?>">&lt;&lt;</a>
            <a href="<?php echo htmlspecialchars(tr_build_query(['page' => max(1, $page - 1)])); ?>"
               class="page-link <?php echo $page == 1 ? 'disabled' : ''; ?>">&lt;</a>
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <a href="<?php echo htmlspecialchars(tr_build_query(['page' => $i])); ?>"
               class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            <a href="<?php echo htmlspecialchars(tr_build_query(['page' => min($total_pages, $page + 1)])); ?>"
               class="page-link <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">&gt;</a>
            <a href="<?php echo htmlspecialchars(tr_build_query(['page' => $total_pages])); ?>"
               class="page-link <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">&gt;&gt;</a>
            <?php endif; ?>
        </div>
</div>

<?php endif; ?>
<!-- End Transactions tab / Table tab -->

</div>
</div>

<!-- Filter Modal -->
<div id="filterModal" class="filter-modal">
    <div class="filter-modal-content">
        <div class="filter-modal-header">
            <h5>Advance Filter</h5>
            <button class="filter-modal-close" onclick="closeFilterModal()">&times;</button>
        </div>
        <div class="filter-modal-body">
            <form method="GET" action="" id="filterForm">
                <input type="hidden" name="tab" value="<?php echo $active_tab; ?>">
                <?php if ($active_tab == 'transactions' && $transaction_voucher_filter !== ''): ?>
                <input type="hidden" name="transaction_voucher_type" value="<?php echo htmlspecialchars($transaction_voucher_filter); ?>">
                <?php endif; ?>
                <div class="filter-grid">
                    <div class="tr-adv-section filter-field-full">Date range</div>
                    <div class="filter-field">
                        <label for="filterFromDate">From Date</label>
                        <input type="date" name="from_date" id="filterFromDate" value="<?php echo htmlspecialchars($from_date); ?>" class="form-control" title="Click to open calendar">
                    </div>
                    <div class="filter-field">
                        <label for="filterToDate">To Date</label>
                        <input type="date" name="to_date" id="filterToDate" value="<?php echo htmlspecialchars($to_date); ?>" class="form-control" title="Click to open calendar">
                    </div>
                    <div class="filter-field">
                        <label class="filter-field-label">&nbsp;</label>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetDateRange()" title="Clear dates" style="height:34px;"><i class="feather icon-refresh-cw"></i> Clear</button>
                    </div>

                    <div class="tr-adv-section filter-field-full">Filters</div>
                    <div class="filter-field">
                        <label for="tr_inv_no">Invoice No.</label>
                        <input type="text" name="invoice_no" id="tr_inv_no" value="<?php echo htmlspecialchars($invoice_no_raw); ?>" class="form-control" placeholder="Enter invoice number" autocomplete="off">
                    </div>
                    <div class="filter-field">
                        <label class="filter-field-label">Branch</label>
                        <div class="mp-ms" data-mp-ms data-mp-label="Branches">
                            <button type="button" class="mp-ms-btn" aria-expanded="false">Branches</button>
                            <div class="mp-ms-panel">
                                <label class="mp-ms-all"<?php echo $tr_effective_branch_id > 0 ? ' style="display:none"' : ''; ?>><input type="checkbox" class="mp-ms-check-all"> Select All</label>
                                <input type="search" class="mp-ms-search" placeholder="Search" autocomplete="off">
                                <div class="mp-ms-list">
                                    <?php foreach ($branches as $branch): ?>
                                    <label class="mp-ms-opt"><input type="checkbox" name="branch_id[]" value="<?php echo (int) $branch['id']; ?>" <?php echo in_array((int) $branch['id'], $tr_resolved_branch_ids, true) ? 'checked' : ''; ?> <?php echo ($tr_effective_branch_id > 0 && (int) $branch['id'] !== $tr_effective_branch_id) ? 'disabled' : ''; ?>><span><?php echo htmlspecialchars($branch['name'] ?? ''); ?></span></label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="filter-field">
                        <label for="tr_bill_bill">Bill To Bill</label>
                        <select name="bill_to_bill" id="tr_bill_bill" class="form-control">
                            <option value="">Both</option>
                            <option value="yes" <?php echo $bill_to_bill_raw === 'yes' ? 'selected' : ''; ?>>Yes</option>
                            <option value="no" <?php echo $bill_to_bill_raw === 'no' ? 'selected' : ''; ?>>No</option>
                        </select>
                    </div>
                    <div class="filter-field">
                        <label class="filter-field-label">Ledger</label>
                        <div class="mp-ms" data-mp-ms data-mp-label="Ledgers">
                            <button type="button" class="mp-ms-btn" aria-expanded="false">Ledgers</button>
                            <div class="mp-ms-panel">
                                <label class="mp-ms-all"><input type="checkbox" class="mp-ms-check-all"> Select All</label>
                                <input type="search" class="mp-ms-search" placeholder="Search" autocomplete="off">
                                <div class="mp-ms-list">
                                    <?php foreach ($ledger_names as $name): ?>
                                    <?php $cn = isset($name['customer_name']) ? (string) $name['customer_name'] : ''; if ($cn === '') { continue; } ?>
                                    <label class="mp-ms-opt"><input type="checkbox" name="ledger_name[]" value="<?php echo htmlspecialchars($cn, ENT_QUOTES, 'UTF-8'); ?>" <?php echo in_array($cn, $ledger_names_sel, true) ? 'checked' : ''; ?>><span><?php echo htmlspecialchars($cn); ?></span></label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="filter-field">
                        <label class="filter-field-label">Group</label>
                        <div class="mp-ms" data-mp-ms data-mp-label="Groups">
                            <button type="button" class="mp-ms-btn" aria-expanded="false">Groups</button>
                            <div class="mp-ms-panel">
                                <label class="mp-ms-all"><input type="checkbox" class="mp-ms-check-all"> Select All</label>
                                <input type="search" class="mp-ms-search" placeholder="Search" autocomplete="off">
                                <div class="mp-ms-list">
                                    <?php foreach ($ledger_groups as $grp): ?>
                                    <label class="mp-ms-opt"><input type="checkbox" name="group[]" value="<?php echo (int) $grp['id']; ?>" <?php echo in_array((string) $grp['id'], $group_ids, true) ? 'checked' : ''; ?>><span><?php echo htmlspecialchars($grp['name'] ?? ''); ?></span></label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="filter-field">
                        <label class="filter-field-label">Ledger Type</label>
                        <div class="mp-ms" data-mp-ms data-mp-label="Ledger types">
                            <button type="button" class="mp-ms-btn" aria-expanded="false">Ledger types</button>
                            <div class="mp-ms-panel">
                                <label class="mp-ms-all"><input type="checkbox" class="mp-ms-check-all"> Select All</label>
                                <div class="mp-ms-list">
                                    <label class="mp-ms-opt"><input type="checkbox" name="ledger_type[]" value="Customer" <?php echo in_array('Customer', $ledger_types_sel, true) ? 'checked' : ''; ?>><span>Customer</span></label>
                                    <label class="mp-ms-opt"><input type="checkbox" name="ledger_type[]" value="Supplier" <?php echo in_array('Supplier', $ledger_types_sel, true) ? 'checked' : ''; ?>><span>Supplier</span></label>
                                    <label class="mp-ms-opt"><input type="checkbox" name="ledger_type[]" value="Account" <?php echo in_array('Account', $ledger_types_sel, true) ? 'checked' : ''; ?>><span>Account</span></label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="filter-field">
                        <label class="filter-field-label">Type of Voucher</label>
                        <div class="mp-ms" data-mp-ms data-mp-label="Voucher types">
                            <button type="button" class="mp-ms-btn" aria-expanded="false">Voucher types</button>
                            <div class="mp-ms-panel">
                                <label class="mp-ms-all"><input type="checkbox" class="mp-ms-check-all"> Select All</label>
                                <input type="search" class="mp-ms-search" placeholder="Search" autocomplete="off">
                                <div class="mp-ms-list">
                                    <?php foreach ($voucher_types as $key => $label): ?>
                                    <label class="mp-ms-opt"><input type="checkbox" name="voucher_type[]" value="<?php echo htmlspecialchars($key); ?>" <?php echo in_array($key, $filter_voucher_keys, true) ? 'checked' : ''; ?>><span><?php echo htmlspecialchars($label); ?></span></label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="filter-field">
                        <label class="filter-field-label">Against Voucher</label>
                        <div class="mp-ms" data-mp-ms data-mp-label="Against voucher">
                            <button type="button" class="mp-ms-btn" aria-expanded="false">Against voucher</button>
                            <div class="mp-ms-panel">
                                <label class="mp-ms-all"><input type="checkbox" class="mp-ms-check-all"> Select All</label>
                                <input type="search" class="mp-ms-search" placeholder="Search" autocomplete="off">
                                <div class="mp-ms-list">
                                    <?php foreach ($voucher_types as $key => $label): ?>
                                    <label class="mp-ms-opt"><input type="checkbox" name="against_voucher_type[]" value="<?php echo htmlspecialchars($key); ?>" <?php echo in_array($key, $filter_against_voucher_keys, true) ? 'checked' : ''; ?>><span><?php echo htmlspecialchars($label); ?></span></label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="filter-field">
                        <label for="tr_against_inv">Against Inv. No</label>
                        <input type="text" name="against_inv_no" id="tr_against_inv" value="<?php echo htmlspecialchars($against_inv_no_raw); ?>" class="form-control" placeholder="Enter invoice number" autocomplete="off">
                    </div>
                    <div class="filter-field">
                        <label class="filter-field-label">Options</label>
                        <label class="filter-field-checkbox-row">
                            <input type="checkbox" name="only_balance" value="1" <?php echo $only_balance ? 'checked' : ''; ?>>
                            Only Balance
                        </label>
                    </div>
                </div>

                <div class="filter-modal-footer">
                    <button type="submit" class="btn-apply">Apply Filter</button>
                    <button type="button" class="btn-clear" onclick="clearFilters()">Clear Filter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="trDeleteConfirmModal" class="tr-delete-modal-overlay" aria-hidden="true">
    <div class="tr-delete-modal" role="dialog" aria-modal="true" aria-labelledby="trDeleteModalTitle">
        <button type="button" class="tr-delete-modal-close" id="trDeleteModalClose" aria-label="Close">&times;</button>
        <div class="tr-delete-modal-illustr">
            <div class="tr-delete-modal-illustr-doc" aria-hidden="true">
                <i class="feather icon-trash-2"></i>
            </div>
        </div>
        <h2 id="trDeleteModalTitle" class="tr-delete-modal-title">Delete this record?</h2>
        <p class="tr-delete-modal-desc" id="trDeleteModalDesc">This will remove the transaction and related ledger, payments, and stock links. This cannot be undone.</p>
        <div class="tr-delete-modal-actions">
            <button type="button" class="tr-delete-modal-btn tr-delete-modal-btn-primary" id="trDeleteModalConfirm">Delete</button>
            <button type="button" class="tr-delete-modal-btn tr-delete-modal-btn-secondary" id="trDeleteModalCancel">Cancel</button>
        </div>
    </div>
</div>

<div id="trMailModal" class="tr-mail-modal-overlay" aria-hidden="true">
    <div class="tr-mail-modal" role="dialog" aria-modal="true" aria-labelledby="trMailModalTitle">
        <button type="button" class="tr-mail-modal-close" id="trMailModalClose" aria-label="Close">&times;</button>
        <div class="tr-mail-modal-header">
            <h2 id="trMailModalTitle" class="tr-mail-modal-title">Send email</h2>
            <p class="tr-mail-modal-sub" id="trMailModalSub">Loading…</p>
        </div>
        <div class="tr-mail-modal-body" id="trMailModalBody">
            <div class="tr-mail-loading" id="trMailModalLoading">Loading email template…</div>
            <div id="trMailModalForm" style="display:none;">
                <div class="tr-mail-field">
                    <label for="trMailTo">To</label>
                    <input type="email" id="trMailTo" placeholder="customer@example.com" autocomplete="off">
                </div>
                <div class="tr-mail-field">
                    <label for="trMailSubject">Subject</label>
                    <input type="text" id="trMailSubject" maxlength="500" autocomplete="off">
                </div>
                <div class="tr-mail-field">
                    <label>Message</label>
                    <div class="tr-mail-tabs">
                        <button type="button" class="tr-mail-tab active" data-tr-mail-tab="edit">Edit</button>
                        <button type="button" class="tr-mail-tab" data-tr-mail-tab="preview">Preview</button>
                    </div>
                    <div id="trMailEditPane">
                        <div class="tr-mail-editor-wrap">
                            <div id="trMailToolbar">
                                <span class="ql-formats">
                                    <button class="ql-bold"></button>
                                    <button class="ql-italic"></button>
                                    <button class="ql-underline"></button>
                                </span>
                                <span class="ql-formats">
                                    <button class="ql-list" value="ordered"></button>
                                    <button class="ql-list" value="bullet"></button>
                                </span>
                                <span class="ql-formats">
                                    <button class="ql-link"></button>
                                </span>
                                <span class="ql-formats">
                                    <button class="ql-clean"></button>
                                </span>
                            </div>
                            <div id="trMailEditor"></div>
                        </div>
                    </div>
                    <div id="trMailPreviewPane" class="tr-mail-preview-pane">
                        <div class="tr-mail-preview-subject" id="trMailPreviewSubject"></div>
                        <div id="trMailPreviewBody"></div>
                    </div>
                </div>
                <div class="tr-mail-attach-note" id="trMailAttachNote" style="display:none;">
                    <i class="feather icon-paperclip"></i> A PDF copy of the invoice will be attached when you send.
                </div>
            </div>
        </div>
        <div class="tr-mail-modal-footer">
            <button type="button" class="tr-mail-modal-btn tr-mail-modal-btn-secondary" id="trMailModalCancel">Cancel</button>
            <button type="button" class="tr-mail-modal-btn tr-mail-modal-btn-primary" id="trMailModalSend" disabled>Send email</button>
        </div>
    </div>
</div>

<script>
function openFilterModal() {
    document.getElementById('filterModal').classList.add('active');
}

function closeFilterModal() {
    document.getElementById('filterModal').classList.remove('active');
}

function trMpMsUpdateLabel(wrap) {
    var btn = wrap.querySelector('.mp-ms-btn');
    var list = wrap.querySelector('.mp-ms-list');
    var ph = wrap.getAttribute('data-mp-label') || 'Select';
    if (!btn || !list) return;
    var opts = list.querySelectorAll('input[type="checkbox"]');
    var checked = list.querySelectorAll('input[type="checkbox"]:checked');
    var n = checked.length;
    var total = opts.length;
    if (n === 0) {
        btn.textContent = ph;
    } else if (total && n === total) {
        btn.textContent = ph + ' (all)';
    } else {
        btn.textContent = ph + ' (' + n + ')';
    }
}

function initTrMpMultiSelectDropdowns(root) {
    root = root || document;
    root.querySelectorAll('[data-mp-ms]').forEach(function (wrap) {
        if (wrap._trMpMsInit) return;
        wrap._trMpMsInit = true;
        var btn = wrap.querySelector('.mp-ms-btn');
        var panel = wrap.querySelector('.mp-ms-panel');
        var search = wrap.querySelector('.mp-ms-search');
        var list = wrap.querySelector('.mp-ms-list');
        var allCb = wrap.querySelector('.mp-ms-check-all');

        function syncAll() {
            var opts = list.querySelectorAll('input[type="checkbox"]');
            var checked = list.querySelectorAll('input[type="checkbox"]:checked');
            if (allCb) {
                allCb.indeterminate = checked.length > 0 && checked.length < opts.length;
                allCb.checked = opts.length > 0 && checked.length === opts.length;
            }
            trMpMsUpdateLabel(wrap);
        }

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var wasOpen = panel.classList.contains('is-open');
            document.querySelectorAll('#filterModal .mp-ms-panel.is-open').forEach(function (p) {
                p.classList.remove('is-open');
            });
            document.querySelectorAll('#filterModal .mp-ms-btn').forEach(function (b) {
                b.setAttribute('aria-expanded', 'false');
            });
            if (!wasOpen) {
                panel.classList.add('is-open');
                btn.setAttribute('aria-expanded', 'true');
            }
        });

        if (allCb) {
            allCb.addEventListener('change', function () {
                var v = allCb.checked;
                list.querySelectorAll('.mp-ms-opt').forEach(function (lab) {
                    if (lab.style.display === 'none') return;
                    var cb = lab.querySelector('input[type="checkbox"]');
                    if (cb) cb.checked = v;
                });
                syncAll();
            });
        }
        list.addEventListener('change', function (e) {
            if (e.target && e.target.type === 'checkbox' && e.target !== allCb) syncAll();
        });

        if (search) {
            search.addEventListener('input', function () {
                var q = (search.value || '').toLowerCase().trim();
                list.querySelectorAll('.mp-ms-opt').forEach(function (lab) {
                    var t = (lab.textContent || '').toLowerCase();
                    lab.style.display = !q || t.indexOf(q) !== -1 ? '' : 'none';
                });
            });
        }
        syncAll();
    });

    if (!document._trMpMsDocClick) {
        document._trMpMsDocClick = true;
        document.addEventListener('click', function (e) {
            if (e.target.closest && e.target.closest('#filterModal .mp-ms')) return;
            document.querySelectorAll('#filterModal .mp-ms-panel.is-open').forEach(function (p) {
                p.classList.remove('is-open');
            });
            document.querySelectorAll('#filterModal .mp-ms-btn').forEach(function (b) {
                b.setAttribute('aria-expanded', 'false');
            });
        });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
        var ff = document.getElementById('filterForm');
        if (ff) initTrMpMultiSelectDropdowns(ff);
    });
} else {
    var ff = document.getElementById('filterForm');
    if (ff) initTrMpMultiSelectDropdowns(ff);
}

// Close modal when clicking outside
document.getElementById('filterModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeFilterModal();
    }
});

function changePerPage(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', value);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

function changePerPageTransactions(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('tab', 'transactions');
    url.searchParams.set('per_page', value);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

function openDatePicker() {
    var el = document.getElementById('filterFromDate');
    if (el) { el.focus(); el.showPicker && el.showPicker(); }
}

function resetDateRange() {
    var fromEl = document.getElementById('filterFromDate');
    var toEl = document.getElementById('filterToDate');
    if (fromEl) fromEl.value = '';
    if (toEl) toEl.value = '';
}

function clearFilters() {
    const cur = new URL(window.location.href);
    const tab = cur.searchParams.get('tab') || 'transactions';
    const perPage = cur.searchParams.get('per_page');
    const next = new URL(cur.origin + cur.pathname);
    next.searchParams.set('tab', tab);
    if (perPage) next.searchParams.set('per_page', perPage);
    window.location.href = next.toString();
}

function viewLedgerDetails(ledgerName, customerId) {
    // Redirect to detailed ledger view
    window.location.href = 'accountledger-report.php?tab=all&ledger_name=' + encodeURIComponent(ledgerName);
}

function viewTransactionDetails(invoiceNo, transactionType, transactionId) {
    // Convert to proper types and handle empty strings
    invoiceNo = (invoiceNo || '').toString().trim();
    transactionType = (transactionType || '').toString().trim();
    transactionId = parseInt(transactionId) || 0;
    
    console.log('viewTransactionDetails called:', {invoiceNo: invoiceNo, transactionType: transactionType, transactionId: transactionId});
    
    // Determine type from invoice number prefix first (most reliable)
    var isPurchase = false;
    var isSale = false;
    
    if (invoiceNo) {
        var upperInvoice = invoiceNo.toUpperCase();
        if (upperInvoice.startsWith('PI-')) {
            isPurchase = true;
        } else if (upperInvoice.startsWith('SO-')) {
            isSale = true;
        }
    }
    
    // If we couldn't determine from invoice number, try transaction type
    if (!isPurchase && !isSale) {
        if (transactionType === 'purchase_invoice') {
            isPurchase = true;
        } else if (transactionType === 'sale_order') {
            isSale = true;
        }
    }
    
    // If transaction_id is available and valid, and we know the type, use it directly
    if (transactionId && transactionId > 0 && (isPurchase || isSale)) {
        if (isPurchase) {
            window.location.href = 'purchase-invoice.php?id=' + transactionId;
            return;
        } else if (isSale) {
            window.location.href = 'sale-order.php?id=' + transactionId;
            return;
        }
    }
    
    // If we don't have invoice number, show error
    if (!invoiceNo) {
        alert('Invoice number is required to view details');
        return;
    }
    
    // Purchase Invoice - need to look up ID
    if (isPurchase) {
        // Make AJAX call to get invoice ID
        var url = 'ajax/get-invoice-id.php?invoice_no=' + encodeURIComponent(invoiceNo) + '&type=purchase';
        
        if (typeof jQuery !== 'undefined' && jQuery.ajax) {
            jQuery.ajax({
                url: url,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success' && response.invoice_id) {
                        window.location.href = 'purchase-invoice.php?id=' + response.invoice_id;
                    } else {
                        alert('Purchase invoice not found: ' + invoiceNo);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                    alert('Error looking up invoice: ' + invoiceNo);
                }
            });
        } else if (typeof fetch !== 'undefined') {
            // Use fetch API as fallback
            fetch(url)
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    if (data.status === 'success' && data.invoice_id) {
                        window.location.href = 'purchase-invoice.php?id=' + data.invoice_id;
                    } else {
                        alert('Purchase invoice not found: ' + invoiceNo);
                    }
                })
                .catch(function(error) {
                    console.error('Fetch error:', error);
                    alert('Error looking up invoice: ' + invoiceNo);
                });
        } else {
            // Final fallback: redirect with invoice number (page should handle lookup)
            window.location.href = 'purchase-invoice.php?invoice_no=' + encodeURIComponent(invoiceNo);
        }
    }
    // Sale Order - need to look up ID
    else if (isSale) {
        // Make AJAX call to get order ID
        var url = 'ajax/get-invoice-id.php?invoice_no=' + encodeURIComponent(invoiceNo) + '&type=sale';
        
        if (typeof jQuery !== 'undefined' && jQuery.ajax) {
            jQuery.ajax({
                url: url,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success' && response.order_id) {
                        window.location.href = 'sale-order.php?id=' + response.order_id;
                    } else {
                        alert('Sale order not found: ' + invoiceNo);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                    alert('Error looking up order: ' + invoiceNo);
                }
            });
        } else if (typeof fetch !== 'undefined') {
            // Use fetch API as fallback
            fetch(url)
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    if (data.status === 'success' && data.order_id) {
                        window.location.href = 'sale-order.php?id=' + data.order_id;
                    } else {
                        alert('Sale order not found: ' + invoiceNo);
                    }
                })
                .catch(function(error) {
                    console.error('Fetch error:', error);
                    alert('Error looking up order: ' + invoiceNo);
                });
        } else {
            // Final fallback: redirect with invoice number (page should handle lookup)
            window.location.href = 'sale-order.php?invoice_no=' + encodeURIComponent(invoiceNo);
        }
    } else {
        alert('Cannot determine invoice type. Invoice: ' + (invoiceNo || 'N/A') + ', Type: ' + (transactionType || 'N/A'));
    }
}

function exportToExcel() {
    alert('Export to Excel functionality will be implemented');
}

function exportToPDF() {
    alert('Export to PDF functionality will be implemented');
}

document.addEventListener('DOMContentLoaded', function() {
    trDeleteModalBind();
    trMailModalBind();
    // Add event listeners for view transaction buttons using event delegation
    document.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('view-transaction-btn')) {
            var btn = e.target;
            var invoiceNo = btn.getAttribute('data-invoice-no') || '';
            var transactionType = btn.getAttribute('data-transaction-type') || '';
            var transactionId = parseInt(btn.getAttribute('data-transaction-id')) || 0;
            
            viewTransactionDetails(invoiceNo, transactionType, transactionId);
        }
    });
});

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    const dropdowns = document.querySelectorAll('.dropdown-menu');
    dropdowns.forEach(dropdown => {
        if (!dropdown.previousElementSibling.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.remove('show');
        }
    });
});

// Delete transaction — custom confirm modal (no native confirm)
var trDeletePending = null;

function trDeleteModalShow(btn, type, id, voucher) {
    trDeletePending = { btn: btn, type: type, id: id, voucher: voucher };
    var overlay = document.getElementById('trDeleteConfirmModal');
    var desc = document.getElementById('trDeleteModalDesc');
    if (!overlay || !desc) return;
    if (voucher) {
        desc.textContent = '';
        desc.appendChild(document.createTextNode('This will remove the voucher and all related ledger entries, payments, stock history, and linked documents. '));
        var strong = document.createElement('strong');
        strong.textContent = 'Voucher: ';
        desc.appendChild(strong);
        desc.appendChild(document.createTextNode(voucher));
    } else {
        desc.textContent = 'This will remove the transaction and all related ledger entries, payments, and stock links. This cannot be undone.';
    }
    overlay.classList.add('active');
    overlay.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    var confirmBtn = document.getElementById('trDeleteModalConfirm');
    if (confirmBtn) confirmBtn.focus();
}

function trDeleteModalHide() {
    var overlay = document.getElementById('trDeleteConfirmModal');
    if (overlay) {
        overlay.classList.remove('active');
        overlay.setAttribute('aria-hidden', 'true');
    }
    document.body.style.overflow = '';
    trDeletePending = null;
}

function trDeleteModalBind() {
    var overlay = document.getElementById('trDeleteConfirmModal');
    if (!overlay) return;
    var closeBtn = document.getElementById('trDeleteModalClose');
    var cancelBtn = document.getElementById('trDeleteModalCancel');
    var confirmBtn = document.getElementById('trDeleteModalConfirm');
    function onOverlayClick(ev) {
        if (ev.target === overlay) trDeleteModalHide();
    }
    overlay.addEventListener('click', onOverlayClick);
    if (closeBtn) closeBtn.addEventListener('click', trDeleteModalHide);
    if (cancelBtn) cancelBtn.addEventListener('click', trDeleteModalHide);
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function () {
            if (!trDeletePending || !trDeletePending.btn) return;
            var btn = trDeletePending.btn;
            var type = trDeletePending.type;
            var id = trDeletePending.id;
            trDeleteModalHide();
            btn.disabled = true;
            var formData = new FormData();
            formData.append('type', type);
            formData.append('id', id);
            var req = new XMLHttpRequest();
            req.open('POST', 'ajax/delete-transaction.php');
            req.onload = function () {
                try {
                    var res = JSON.parse(req.responseText);
                    if (res.status === 'success') {
                        location.reload();
                        return;
                    } else {
                        alert(res.message || 'Delete failed');
                        btn.disabled = false;
                    }
                } catch (err) {
                    alert('Delete failed. Please try again.');
                    btn.disabled = false;
                }
            };
            req.onerror = function () {
                alert('Network error. Please try again.');
                btn.disabled = false;
            };
            req.send(formData);
        });
    }
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape' && overlay.classList.contains('active')) {
            ev.preventDefault();
            trDeleteModalHide();
        }
    });
}

document.addEventListener('click', function(e) {
    var btn = (e.target && typeof e.target.closest === 'function') ? e.target.closest('button.btn-delete-transaction') : null;
    if (!btn) return;
    e.preventDefault();
    if (btn.disabled) return;
    var type = btn.getAttribute('data-type');
    var id = btn.getAttribute('data-id');
    var voucher = btn.getAttribute('data-voucher') || '';
    if (!type || !id) return;
    trDeleteModalShow(btn, type, id, voucher);
});

document.addEventListener('click', function (e) {
    var mbtn = (e.target && typeof e.target.closest === 'function') ? e.target.closest('button.action-icon-mail') : null;
    if (!mbtn || mbtn.disabled) return;
    e.preventDefault();
    var type = mbtn.getAttribute('data-type');
    var id = mbtn.getAttribute('data-id');
    var pre = (mbtn.getAttribute('data-party-email') || '').trim();
    if (!type || !id) return;
    trMailModalOpen(type, id, pre, mbtn);
});

function trCloseAllWhatsAppMenus(exceptWrap) {
    document.querySelectorAll('.tr-whatsapp-menu.is-open').forEach(function (menu) {
        if (!exceptWrap || !exceptWrap.contains(menu)) {
            menu.classList.remove('is-open');
            menu.style.top = '';
            menu.style.right = '';
            menu.style.left = '';
        }
    });
    document.querySelectorAll('.tr-whatsapp-wrap.is-open').forEach(function (wrap) {
        if (!exceptWrap || wrap !== exceptWrap) {
            wrap.classList.remove('is-open');
        }
    });
}

function trPositionWhatsAppMenu(toggle, menu) {
    if (!toggle || !menu) return;
    var r = toggle.getBoundingClientRect();
    menu.style.top = Math.round(r.bottom + 4) + 'px';
    menu.style.left = 'auto';
    menu.style.right = Math.max(8, Math.round(window.innerWidth - r.right)) + 'px';
}

function trNormalizeWhatsAppPhone(raw) {
    var digits = String(raw || '').replace(/\D/g, '');
    if (!digits) return '';
    if (digits.length === 10) return '91' + digits;
    return digits;
}

function trBuildWhatsAppMessage(btn) {
    var msg = (btn.getAttribute('data-whatsapp-msg') || '').trim();
    if (msg) return msg;
    var party = (btn.getAttribute('data-party') || '').trim();
    var voucher = (btn.getAttribute('data-voucher') || '').trim();
    var typeLabel = (btn.getAttribute('data-type-label') || '').trim();
    var amount = (btn.getAttribute('data-amount') || '').trim();
    var parts = ['Dear ' + (party || 'Customer') + ','];
    if (typeLabel || voucher) {
        parts.push('Regarding your ' + (typeLabel || 'transaction') + (voucher ? ' (' + voucher + ')' : '') + '.');
    }
    if (amount) {
        parts.push('Amount: ₹' + amount + '.');
    }
    parts.push('— GoldMatrix');
    return parts.join('\n');
}

function trOpenWhatsAppWeb(btn) {
    var phone = trNormalizeWhatsAppPhone(btn.getAttribute('data-party-mobile'));
    var msg = trBuildWhatsAppMessage(btn);
    var url = phone
        ? ('https://wa.me/' + phone + '?text=' + encodeURIComponent(msg))
        : ('https://web.whatsapp.com/send?text=' + encodeURIComponent(msg));
    window.open(url, '_blank', 'noopener,noreferrer');
}

function trOpenWhatsAppApi(btn) {
    var msg = trBuildWhatsAppMessage(btn);
    var phone = trNormalizeWhatsAppPhone(btn.getAttribute('data-party-mobile'));
    var url = 'crm.php';
    var qs = [];
    if (phone) qs.push('contact_no=' + encodeURIComponent(phone));
    if (msg) qs.push('message=' + encodeURIComponent(msg));
    if (qs.length) url += '?' + qs.join('&');
    window.open(url, '_blank', 'noopener,noreferrer');
}

document.addEventListener('click', function (e) {
    var opt = (e.target && typeof e.target.closest === 'function') ? e.target.closest('.tr-whatsapp-opt') : null;
    if (opt) {
        e.preventDefault();
        e.stopPropagation();
        var wrap = opt.closest('.tr-whatsapp-wrap');
        var toggle = wrap ? wrap.querySelector('.tr-whatsapp-toggle') : null;
        if (!toggle) return;
        var mode = opt.getAttribute('data-mode');
        trCloseAllWhatsAppMenus();
        if (mode === 'api') {
            trOpenWhatsAppApi(toggle);
        } else {
            trOpenWhatsAppWeb(toggle);
        }
        return;
    }

    var waToggle = (e.target && typeof e.target.closest === 'function') ? e.target.closest('.tr-whatsapp-toggle') : null;
    if (waToggle) {
        e.preventDefault();
        e.stopPropagation();
        var waWrap = waToggle.closest('.tr-whatsapp-wrap');
        var menu = waWrap ? waWrap.querySelector('.tr-whatsapp-menu') : null;
        if (!menu) return;
        var willOpen = !menu.classList.contains('is-open');
        trCloseAllWhatsAppMenus(waWrap);
        if (willOpen) {
            trPositionWhatsAppMenu(waToggle, menu);
            menu.classList.add('is-open');
            waWrap.classList.add('is-open');
        }
        return;
    }

    if (!e.target.closest('.tr-whatsapp-wrap')) {
        trCloseAllWhatsAppMenus();
    }
});

window.addEventListener('resize', function () {
    document.querySelectorAll('.tr-whatsapp-wrap.is-open').forEach(function (wrap) {
        var toggle = wrap.querySelector('.tr-whatsapp-toggle');
        var menu = wrap.querySelector('.tr-whatsapp-menu');
        if (toggle && menu && menu.classList.contains('is-open')) {
            trPositionWhatsAppMenu(toggle, menu);
        }
    });
});

document.addEventListener('scroll', function () {
    document.querySelectorAll('.tr-whatsapp-wrap.is-open').forEach(function (wrap) {
        var toggle = wrap.querySelector('.tr-whatsapp-toggle');
        var menu = wrap.querySelector('.tr-whatsapp-menu');
        if (toggle && menu && menu.classList.contains('is-open')) {
            trPositionWhatsAppMenu(toggle, menu);
        }
    });
}, true);

// Email compose modal — template from Invoice Print Settings
var trMailQuill = null;
var trMailPending = null;

function trMailModalInitQuill() {
    if (trMailQuill || typeof Quill === 'undefined') return;
    var editorEl = document.getElementById('trMailEditor');
    if (!editorEl) return;
    trMailQuill = new Quill('#trMailEditor', {
        theme: 'snow',
        modules: { toolbar: '#trMailToolbar' }
    });
}

function trMailModalUpdatePreview() {
    var subjEl = document.getElementById('trMailSubject');
    var previewSubj = document.getElementById('trMailPreviewSubject');
    var previewBody = document.getElementById('trMailPreviewBody');
    if (previewSubj) {
        previewSubj.textContent = subjEl ? (subjEl.value || '(No subject)') : '';
    }
    if (previewBody) {
        previewBody.innerHTML = trMailQuill ? trMailQuill.root.innerHTML : '';
    }
}

function trMailModalSetTab(tab) {
    var editPane = document.getElementById('trMailEditPane');
    var previewPane = document.getElementById('trMailPreviewPane');
    document.querySelectorAll('[data-tr-mail-tab]').forEach(function (btn) {
        btn.classList.toggle('active', btn.getAttribute('data-tr-mail-tab') === tab);
    });
    if (tab === 'preview') {
        trMailModalUpdatePreview();
        if (editPane) editPane.style.display = 'none';
        if (previewPane) previewPane.classList.add('active');
    } else {
        if (editPane) editPane.style.display = '';
        if (previewPane) previewPane.classList.remove('active');
    }
}

function trMailModalHide() {
    var overlay = document.getElementById('trMailModal');
    if (overlay) {
        overlay.classList.remove('active');
        overlay.setAttribute('aria-hidden', 'true');
    }
    document.body.style.overflow = '';
    trMailPending = null;
    var sendBtn = document.getElementById('trMailModalSend');
    if (sendBtn) sendBtn.disabled = false;
}

function trMailModalOpen(type, id, partyEmail, sourceBtn) {
    trMailModalInitQuill();
    trMailPending = { type: type, id: parseInt(id, 10), btn: sourceBtn || null };
    var overlay = document.getElementById('trMailModal');
    var loading = document.getElementById('trMailModalLoading');
    var form = document.getElementById('trMailModalForm');
    var sub = document.getElementById('trMailModalSub');
    var sendBtn = document.getElementById('trMailModalSend');
    var attachNote = document.getElementById('trMailAttachNote');
    if (!overlay || !loading || !form) return;

    if (loading) loading.style.display = '';
    form.style.display = 'none';
    if (sendBtn) sendBtn.disabled = true;
    if (sub) sub.textContent = 'Loading template…';
    if (attachNote) attachNote.style.display = 'none';
    trMailModalSetTab('edit');
    overlay.classList.add('active');
    overlay.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';

    fetch('ajax/preview-transaction-email.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ type: type, id: parseInt(id, 10) })
    })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (!res || !res.ok) {
                trMailModalHide();
                alert((res && res.message) ? res.message : 'Could not load email template.');
                return;
            }
            loading.style.display = 'none';
            form.style.display = '';

            var toEl = document.getElementById('trMailTo');
            var subjEl = document.getElementById('trMailSubject');
            if (toEl) toEl.value = (partyEmail || res.recipient || '').trim();
            if (subjEl) subjEl.value = res.subject || '';
            if (trMailQuill) {
                trMailQuill.root.innerHTML = res.body || '';
            }
            if (sub) {
                var docLabel = res.document_type_label || type;
                var docNo = res.doc_no ? (' — ' + res.doc_no) : '';
                sub.textContent = docLabel + docNo + (res.template_used ? ' · Template from print settings' : ' · Default message');
            }
            if (!res.mail_ready && res.mail_config_message) {
                alert(res.mail_config_message + '\n\nYou can still enter a recipient and send after saving Mail Setting.');
            }
            if (attachNote && res.has_pdf_attachment) {
                attachNote.style.display = '';
            }
            if (sendBtn) sendBtn.disabled = false;
        })
        .catch(function () {
            trMailModalHide();
            alert('Network error. Please try again.');
        });
}

function trMailModalBind() {
    var overlay = document.getElementById('trMailModal');
    var closeBtn = document.getElementById('trMailModalClose');
    var cancelBtn = document.getElementById('trMailModalCancel');
    var sendBtn = document.getElementById('trMailModalSend');
    if (overlay) {
        overlay.addEventListener('click', function (ev) {
            if (ev.target === overlay) trMailModalHide();
        });
    }
    if (closeBtn) closeBtn.addEventListener('click', trMailModalHide);
    if (cancelBtn) cancelBtn.addEventListener('click', trMailModalHide);
    document.querySelectorAll('[data-tr-mail-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            trMailModalSetTab(btn.getAttribute('data-tr-mail-tab') || 'edit');
        });
    });
    if (sendBtn) {
        sendBtn.addEventListener('click', function () {
            if (!trMailPending) return;
            var toEl = document.getElementById('trMailTo');
            var subjEl = document.getElementById('trMailSubject');
            var to = toEl ? toEl.value.trim() : '';
            if (!to) {
                alert('Enter recipient email address.');
                if (toEl) toEl.focus();
                return;
            }
            var subject = subjEl ? subjEl.value.trim() : '';
            var body = trMailQuill ? trMailQuill.root.innerHTML : '';
            sendBtn.disabled = true;
            fetch('ajax/send-transaction-email.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({
                    type: trMailPending.type,
                    id: trMailPending.id,
                    to: to,
                    subject: subject,
                    body: body
                })
            })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    sendBtn.disabled = false;
                    if (res && res.ok) {
                        trMailModalHide();
                        var msg = res.message || 'Email sent.';
                        if (res.recipient && msg.indexOf(res.recipient) === -1) {
                            msg += '\n\nSent to: ' + res.recipient;
                        }
                        alert(msg);
                    } else {
                        alert((res && res.message) ? res.message : 'Could not send email.');
                    }
                })
                .catch(function () {
                    sendBtn.disabled = false;
                    alert('Network error. Please try again.');
                });
        });
    }
}
</script>

<?php include 'footer-script.php'; ?>

<!-- DataTable Functionality for Transaction Report -->
<script>
$(document).ready(function() {
    setTimeout(function() {
        initTransactionTable();
    }, 100);
});

function initTransactionTable() {
    var $table = $('#ledgerTable');
    if ($table.length === 0) {
        console.log('Table not found');
        return;
    }
    
    // Initialize search
    $('#customSearch').off('keyup change').on('keyup change', function() {
        var searchVal = $(this).val().toLowerCase();
        $table.find('tbody tr').each(function() {
            // Skip total row
            if ($(this).hasClass('table-footer-total')) {
                return;
            }
            var rowText = $(this).text().toLowerCase();
            if (rowText.indexOf(searchVal) > -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
    
    // Initialize sorting
    initTableSorting();
    
    // Connect export button
    $('#exportExcelBtn').off('click').on('click', function() {
        exportTableToExcel();
    });
    
    // Connect print button
    $('#printBtn').off('click').on('click', function() {
        printTable();
    });
    
    console.log('Transaction table initialized');
}

// Table sorting functionality
function initTableSorting() {
    var $table = $('#ledgerTable');
    var $headers = $table.find('thead th');
    var sortOrder = {};
    
    // Add sorting styles to headers
    $headers.each(function(index) {
        if (index > 0) { // Skip first column (View button)
            $(this).css('cursor', 'pointer');
            $(this).attr('data-sort-col', index);
            // Remove existing sort icons if any
            $(this).find('.sort-icon').remove();
            $(this).append(' <span class="sort-icon" style="font-size:10px;opacity:0.5;">↕</span>');
        }
    });
    
    // Click handler for sorting
    $headers.on('click', function() {
        var colIndex = $(this).index();
        if (colIndex === 0) return; // Don't sort first column
        
        var $tbody = $table.find('tbody');
        // Get rows except total row
        var rows = $tbody.find('tr').not('.table-footer-total').get();
        
        // Toggle sort order
        sortOrder[colIndex] = sortOrder[colIndex] === 'asc' ? 'desc' : 'asc';
        var isAsc = sortOrder[colIndex] === 'asc';
        
        // Update sort icons
        $headers.find('.sort-icon').text('↕').css('opacity', '0.5');
        $(this).find('.sort-icon').text(isAsc ? '↑' : '↓').css('opacity', '1');
        
        // Sort rows
        rows.sort(function(a, b) {
            var aVal = $(a).find('td').eq(colIndex).text().trim();
            var bVal = $(b).find('td').eq(colIndex).text().trim();
            
            // Try to parse as number
            var aNum = parseFloat(aVal.replace(/[^0-9.-]/g, ''));
            var bNum = parseFloat(bVal.replace(/[^0-9.-]/g, ''));
            
            if (!isNaN(aNum) && !isNaN(bNum)) {
                return isAsc ? aNum - bNum : bNum - aNum;
            }
            
            // String comparison
            return isAsc ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
        });
        
        // Reattach sorted rows (total row stays at bottom)
        var $totalRow = $tbody.find('tr.table-footer-total');
        $.each(rows, function(index, row) {
            $tbody.append(row);
        });
        if ($totalRow.length) {
            $tbody.append($totalRow);
        }
    });
    
    console.log('Table sorting initialized');
}

function exportTableToExcel() {
    var csv = [];
    var headers = [];
    
    // Get headers (skip first column which is View button)
    $('#ledgerTable thead th').each(function(i) {
        if (i > 0) {
            headers.push('"' + $(this).text().trim().replace(/"/g, '""').replace(/↕|↑|↓/g, '').trim() + '"');
        }
    });
    csv.push(headers.join(','));
    
    // Get visible rows data (skip total row for data, add at end)
    var totalRowData = null;
    $('#ledgerTable tbody tr:visible').each(function() {
        var rowData = [];
        var isTotal = $(this).hasClass('table-footer-total');
        $(this).find('td').each(function(i) {
            if (i > 0) {
                var cellText = $(this).text().trim().replace(/"/g, '""');
                rowData.push('"' + cellText + '"');
            }
        });
        if (rowData.length > 0) {
            if (isTotal) {
                totalRowData = rowData.join(',');
            } else {
                csv.push(rowData.join(','));
            }
        }
    });
    
    // Add total row at end if exists
    if (totalRowData) {
        csv.push(totalRowData);
    }
    
    // Create and download file
    var csvContent = csv.join('\n');
    var blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
    var link = document.createElement('a');
    var url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', 'Transaction_Report_' + new Date().toISOString().slice(0,10) + '.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    alert('Excel/CSV file exported successfully!');
}

function printTable() {
    var printContents = '<html><head><title>Transaction Report</title>';
    printContents += '<style>';
    printContents += 'body { font-family: Arial, sans-serif; font-size: 12px; }';
    printContents += 'table { width: 100%; border-collapse: collapse; margin-top: 20px; }';
    printContents += 'th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }';
    printContents += 'th { background-color: #11294b; color: white; }';
    printContents += 'tr:nth-child(even) { background-color: #f2f2f2; }';
    printContents += '.table-footer-total td { background-color: #e2e8f0; font-weight: bold; }';
    printContents += 'h1 { text-align: center; color: #11294b; }';
    printContents += '@media print { th { background-color: #11294b !important; -webkit-print-color-adjust: exact; } }';
    printContents += '</style></head><body>';
    printContents += '<h1>Transaction Report</h1>';
    printContents += '<p>Generated on: ' + new Date().toLocaleString() + '</p>';
    printContents += '<table>';
    
    // Header
    printContents += '<thead><tr>';
    $('#ledgerTable thead th').each(function(i) {
        if (i > 0) {
            printContents += '<th>' + $(this).text().replace(/↕|↑|↓/g, '').trim() + '</th>';
        }
    });
    printContents += '</tr></thead>';
    
    // Body
    printContents += '<tbody>';
    $('#ledgerTable tbody tr:visible').each(function() {
        var isTotal = $(this).hasClass('table-footer-total');
        printContents += '<tr' + (isTotal ? ' class="table-footer-total"' : '') + '>';
        $(this).find('td').each(function(i) {
            if (i > 0) {
                printContents += '<td>' + $(this).text() + '</td>';
            }
        });
        printContents += '</tr>';
    });
    printContents += '</tbody></table></body></html>';
    
    var printWindow = window.open('', '_blank');
    printWindow.document.write(printContents);
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
    printWindow.close();
}
</script>
</body>
</html>

