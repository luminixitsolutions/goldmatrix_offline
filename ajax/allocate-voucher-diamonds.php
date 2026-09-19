<?php

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_voucher_diamond_stock.php';

header('Content-Type: application/json; charset=utf-8');

$authed = (isset($_SESSION['Admin']['id']) && (int) $_SESSION['Admin']['id'] > 0)
    || (isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0);
if (!$authed) {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'message' => 'Invalid request']);
    exit;
}

$raw = isset($_POST['payload']) ? $_POST['payload'] : file_get_contents('php://input');
$data = null;
if (is_string($raw)) {
    $data = json_decode($raw, true);
}
if (!is_array($data)) {
    $data = $_POST;
}

$voucher_kind = isset($data['voucher_kind']) ? strtolower(trim((string) $data['voucher_kind'])) : '';
$voucher_id = isset($data['voucher_id']) ? (int) $data['voucher_id'] : (isset($data['order_id']) ? (int) $data['order_id'] : 0);
$lines_in = isset($data['lines']) && is_array($data['lines']) ? $data['lines'] : [];

if (!auragold_voucher_diamond_kind_valid($voucher_kind)) {
    echo json_encode(['ok' => false, 'message' => 'Invalid voucher type for diamond allocation.']);
    exit;
}

if ($voucher_id < 1) {
    echo json_encode(['ok' => false, 'message' => 'Save the document first, then allocate diamonds.']);
    exit;
}

$doc_no = '';
$doc_date = '';

if ($voucher_kind === 'sale_order') {
    $order = function_exists('getRecord')
        ? getRecord('SELECT id, order_no, order_date FROM tbl_sale_orders WHERE id = ' . $voucher_id . ' LIMIT 1')
        : null;
    if (!$order || empty($order['id'])) {
        echo json_encode(['ok' => false, 'message' => 'Sale order not found.']);
        exit;
    }
    $doc_no = trim((string) ($order['order_no'] ?? ''));
    $doc_date = trim((string) ($order['order_date'] ?? ''));
} elseif ($voucher_kind === 'sale_invoice') {
    $inv = function_exists('getRecord')
        ? getRecord('SELECT id, invoice_no, invoice_date FROM tbl_sale_invoices WHERE id = ' . $voucher_id . ' LIMIT 1')
        : null;
    if (!$inv || empty($inv['id'])) {
        echo json_encode(['ok' => false, 'message' => 'Sale invoice not found.']);
        exit;
    }
    $doc_no = trim((string) ($inv['invoice_no'] ?? ''));
    $doc_date = trim((string) ($inv['invoice_date'] ?? ''));
} elseif ($voucher_kind === 'pos_sale_invoice') {
    $inv = function_exists('getRecord')
        ? getRecord('SELECT id, invoice_no, invoice_date FROM tbl_pos_sale_invoices WHERE id = ' . $voucher_id . ' LIMIT 1')
        : null;
    if (!$inv || empty($inv['id'])) {
        echo json_encode(['ok' => false, 'message' => 'POS invoice not found.']);
        exit;
    }
    $doc_no = trim((string) ($inv['invoice_no'] ?? ''));
    $doc_date = trim((string) ($inv['invoice_date'] ?? ''));
} elseif ($voucher_kind === 'sale_quotation') {
    $r = function_exists('getRecord')
        ? getRecord('SELECT id, quotation_no, quotation_date FROM tbl_sale_quotations WHERE id = ' . $voucher_id . ' LIMIT 1')
        : null;
    if (!$r || empty($r['id'])) {
        echo json_encode(['ok' => false, 'message' => 'Sale quotation not found.']);
        exit;
    }
    $doc_no = trim((string) ($r['quotation_no'] ?? ''));
    $doc_date = trim((string) ($r['quotation_date'] ?? ''));
} elseif ($voucher_kind === 'purchase_invoice') {
    $r = function_exists('getRecord')
        ? getRecord('SELECT id, invoice_no, invoice_date FROM tbl_purchase_invoices WHERE id = ' . $voucher_id . ' LIMIT 1')
        : null;
    if (!$r || empty($r['id'])) {
        echo json_encode(['ok' => false, 'message' => 'Purchase invoice not found.']);
        exit;
    }
    $doc_no = trim((string) ($r['invoice_no'] ?? ''));
    $doc_date = trim((string) ($r['invoice_date'] ?? ''));
} elseif ($voucher_kind === 'purchase_quotation') {
    $r = function_exists('getRecord')
        ? getRecord('SELECT id, quotation_no, quotation_date FROM tbl_purchase_quotations WHERE id = ' . $voucher_id . ' LIMIT 1')
        : null;
    if (!$r || empty($r['id'])) {
        echo json_encode(['ok' => false, 'message' => 'Purchase quotation not found.']);
        exit;
    }
    $doc_no = trim((string) ($r['quotation_no'] ?? ''));
    $doc_date = trim((string) ($r['quotation_date'] ?? ''));
} elseif ($voucher_kind === 'payment_voucher') {
    $r = function_exists('getRecord')
        ? getRecord('SELECT id, voucher_no, voucher_date FROM tbl_payment_vouchers WHERE id = ' . $voucher_id . ' LIMIT 1')
        : null;
    if (!$r || empty($r['id'])) {
        echo json_encode(['ok' => false, 'message' => 'Payment voucher not found.']);
        exit;
    }
    $doc_no = trim((string) ($r['voucher_no'] ?? ''));
    $doc_date = trim((string) ($r['voucher_date'] ?? ''));
} elseif ($voucher_kind === 'receipt_voucher') {
    $r = function_exists('getRecord')
        ? getRecord('SELECT id, voucher_no, voucher_date FROM tbl_receipt_vouchers WHERE id = ' . $voucher_id . ' LIMIT 1')
        : null;
    if (!$r || empty($r['id'])) {
        echo json_encode(['ok' => false, 'message' => 'Receipt voucher not found.']);
        exit;
    }
    $doc_no = trim((string) ($r['voucher_no'] ?? ''));
    $doc_date = trim((string) ($r['voucher_date'] ?? ''));
} elseif ($voucher_kind === 'material_issue') {
    $r = function_exists('getRecord')
        ? getRecord('SELECT id, issue_no, issue_date FROM tbl_material_issues WHERE id = ' . $voucher_id . ' LIMIT 1')
        : null;
    if (!$r || empty($r['id'])) {
        echo json_encode(['ok' => false, 'message' => 'Material issue not found.']);
        exit;
    }
    $doc_no = trim((string) ($r['issue_no'] ?? ''));
    $doc_date = trim((string) ($r['issue_date'] ?? ''));
} elseif ($voucher_kind === 'material_receive') {
    $r = function_exists('getRecord')
        ? getRecord('SELECT id, material_receive_no, order_date FROM tbl_material_receives WHERE id = ' . $voucher_id . ' LIMIT 1')
        : null;
    if (!$r || empty($r['id'])) {
        echo json_encode(['ok' => false, 'message' => 'Material receive not found.']);
        exit;
    }
    $doc_no = trim((string) ($r['material_receive_no'] ?? ''));
    $doc_date = substr(trim((string) ($r['order_date'] ?? '')), 0, 10);
} elseif ($voucher_kind === 'jobwork_order') {
    $r = function_exists('getRecord')
        ? getRecord('SELECT id, jobwork_no, order_date FROM tbl_jobwork_orders WHERE id = ' . $voucher_id . ' LIMIT 1')
        : null;
    if (!$r || empty($r['id'])) {
        echo json_encode(['ok' => false, 'message' => 'Jobwork order not found.']);
        exit;
    }
    $doc_no = trim((string) ($r['jobwork_no'] ?? ''));
    $doc_date = trim((string) ($r['order_date'] ?? ''));
} elseif ($voucher_kind === 'consignment_in') {
    $r = function_exists('getRecord')
        ? getRecord('SELECT id, consignment_no, consignment_date FROM tbl_consignment_in WHERE id = ' . $voucher_id . ' LIMIT 1')
        : null;
    if (!$r || empty($r['id'])) {
        echo json_encode(['ok' => false, 'message' => 'Consignment in not found.']);
        exit;
    }
    $doc_no = trim((string) ($r['consignment_no'] ?? ''));
    $doc_date = trim((string) ($r['consignment_date'] ?? ''));
} elseif ($voucher_kind === 'consignment_out') {
    $r = function_exists('getRecord')
        ? getRecord('SELECT id, consignment_no, consignment_date FROM tbl_consignment_out WHERE id = ' . $voucher_id . ' LIMIT 1')
        : null;
    if (!$r || empty($r['id'])) {
        echo json_encode(['ok' => false, 'message' => 'Consignment out not found.']);
        exit;
    }
    $doc_no = trim((string) ($r['consignment_no'] ?? ''));
    $doc_date = trim((string) ($r['consignment_date'] ?? ''));
} elseif ($voucher_kind === 'sale_return') {
    $r = function_exists('getRecord') ? @getRecord('SELECT id, return_no, return_date FROM tbl_sale_returns WHERE id = ' . $voucher_id . ' LIMIT 1') : null;
    if ($r && !empty($r['id'])) {
        $doc_no = trim((string) ($r['return_no'] ?? ''));
        $doc_date = trim((string) ($r['return_date'] ?? ''));
    }
} elseif ($voucher_kind === 'purchase_return') {
    $r = function_exists('getRecord') ? @getRecord('SELECT id, return_no, return_date FROM tbl_purchase_returns WHERE id = ' . $voucher_id . ' LIMIT 1') : null;
    if ($r && !empty($r['id'])) {
        $doc_no = trim((string) ($r['return_no'] ?? ''));
        $doc_date = trim((string) ($r['return_date'] ?? ''));
    }
} elseif ($voucher_kind === 'advance_payment') {
    $r = function_exists('getRecord') ? @getRecord('SELECT id, voucher_no, voucher_date FROM tbl_advance_payments WHERE id = ' . $voucher_id . ' LIMIT 1') : null;
    if ($r && !empty($r['id'])) {
        $doc_no = trim((string) ($r['voucher_no'] ?? ''));
        $doc_date = trim((string) ($r['voucher_date'] ?? ''));
    }
} elseif ($voucher_kind === 'old_jewelry_scrap_invoice') {
    $r = function_exists('getRecord') ? @getRecord('SELECT id, invoice_no, invoice_date FROM tbl_old_jewelry_scrap_invoices WHERE id = ' . $voucher_id . ' LIMIT 1') : null;
    if (!$r || empty($r['id'])) {
        $r = function_exists('getRecord') ? @getRecord('SELECT id, invoice_no, invoice_date FROM tbl_old_jewellery_scrap_invoice WHERE id = ' . $voucher_id . ' LIMIT 1') : null;
    }
    if ($r && !empty($r['id'])) {
        $doc_no = trim((string) ($r['invoice_no'] ?? ''));
        $doc_date = trim((string) ($r['invoice_date'] ?? ''));
    }
} elseif ($voucher_kind === 'jobwork_invoice') {
    $r = function_exists('getRecord') ? @getRecord('SELECT id, invoice_no, invoice_date FROM tbl_jobwork_invoices WHERE id = ' . $voucher_id . ' LIMIT 1') : null;
    if ($r && !empty($r['id'])) {
        $doc_no = trim((string) ($r['invoice_no'] ?? ''));
        $doc_date = trim((string) ($r['invoice_date'] ?? ''));
    }
} elseif ($voucher_kind === 'old_jewellery_scrap_stock_in') {
    $r = function_exists('getRecord') ? @getRecord('SELECT id, stock_no, stock_date FROM tbl_old_jewellery_scrap_stock_in WHERE id = ' . $voucher_id . ' LIMIT 1') : null;
    if (!$r || empty($r['id'])) {
        $r = function_exists('getRecord') ? @getRecord('SELECT id, voucher_no, voucher_date FROM tbl_old_jewellery_scrap_stock_in WHERE id = ' . $voucher_id . ' LIMIT 1') : null;
    }
    if ($r && !empty($r['id'])) {
        $doc_no = trim((string) ($r['stock_no'] ?? $r['voucher_no'] ?? ''));
        $doc_date = trim((string) ($r['stock_date'] ?? $r['voucher_date'] ?? ''));
    }
}

if ($doc_no === '') {
    $doc_no = strtoupper(str_replace('_', '-', $voucher_kind)) . '-' . $voucher_id;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $doc_date)) {
    $doc_date = date('Y-m-d');
}

$lines = [];
foreach ($lines_in as $ln) {
    if (!is_array($ln)) {
        continue;
    }
    $sid = (int) ($ln['stock_id'] ?? 0);
    $qty = isset($ln['allocate_qty']) ? (float) $ln['allocate_qty'] : (isset($ln['qty']) ? (float) $ln['qty'] : 0);
    $wt = isset($ln['allocate_weight']) ? (float) $ln['allocate_weight'] : (isset($ln['weight']) ? (float) $ln['weight'] : 0);
    if ($sid < 1) {
        continue;
    }
    if ($qty <= 0 && $wt <= 0) {
        continue;
    }
    $lines[] = [
        'stock_id' => $sid,
        'barcode' => isset($ln['barcode']) ? trim((string) $ln['barcode']) : '',
        'qty' => $qty,
        'weight' => $wt,
        'product_name' => isset($ln['product_name']) ? trim((string) $ln['product_name']) : '',
        'diamond_category' => isset($ln['diamond_category']) ? trim((string) $ln['diamond_category']) : '',
    ];
}

if ($lines === []) {
    echo json_encode(['ok' => false, 'message' => 'Select at least one row with allocate quantity or weight.']);
    exit;
}

mysqli_begin_transaction($conn);
$tx_ok = true;
$tx_err = '';
try {
    $stats = auragold_voucher_apply_diamond_allocations($conn, $voucher_kind, $voucher_id, $lines, $doc_no, $doc_date, $tx_ok, $tx_err);
    if (!$tx_ok) {
        mysqli_rollback($conn);
        echo json_encode(['ok' => false, 'message' => $tx_err ?: 'Allocation failed']);
        exit;
    }
    mysqli_commit($conn);
    echo json_encode([
        'ok' => true,
        'message' => $stats['saved'] > 0
            ? ('Allocated ' . (int) $stats['saved'] . ' diamond line(s). Stock updated.')
            : 'Nothing allocated.',
        'saved' => (int) $stats['saved'],
    ]);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
