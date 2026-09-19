<?php
/**
 * Read-only view: auto-generated sale receipt voucher (tbl_sale_receipt_vouchers).
 * Linked from account ledger / transaction report when transaction_type = sale_receipt_voucher.
 */
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config.php';

if (function_exists('auragold_ensure_tbl_sale_receipt_vouchers')) {
    auragold_ensure_tbl_sale_receipt_vouchers($conn);
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id < 1) {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid voucher id.');
}

$v = getRecord('SELECT * FROM tbl_sale_receipt_vouchers WHERE id = ' . $id . ' LIMIT 1');
if (!$v) {
    header('HTTP/1.1 404 Not Found');
    exit('Sale receipt voucher not found.');
}

$items = getList('SELECT * FROM tbl_sale_receipt_voucher_items WHERE sale_receipt_voucher_id = ' . $id . ' ORDER BY id ASC');
if (!is_array($items)) {
    $items = [];
}

$sale_invoice_no = trim((string) ($v['sale_invoice_no'] ?? ''));
$si_link = '';
if ($sale_invoice_no !== '') {
    $ino_esc = mysqli_real_escape_string($conn, $sale_invoice_no);
    $si = getRecord("SELECT id FROM tbl_sale_invoices WHERE invoice_no = '$ino_esc' LIMIT 1");
    if ($si && !empty($si['id'])) {
        $si_link = 'sale-invoice.php?id=' . (int) $si['id'];
    }
    if ($si_link === '') {
        $psi = getRecord("SELECT id FROM tbl_pos_sale_invoices WHERE invoice_no = '$ino_esc' LIMIT 1");
        if ($psi && !empty($psi['id'])) {
            $si_link = 'pos-sale-invoice.php?id=' . (int) $psi['id'];
        }
    }
}

$page_title = 'Sale Receipt Voucher';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-4">
    <h4 class="mb-3"><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></h4>
    <div class="card mb-3">
        <div class="card-body">
            <p class="mb-1"><strong>No.:</strong> <?php echo htmlspecialchars((string) ($v['voucher_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
            <p class="mb-1"><strong>Date:</strong> <?php echo htmlspecialchars((string) ($v['voucher_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
            <p class="mb-1"><strong>Customer:</strong> <?php echo htmlspecialchars((string) ($v['customer_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
            <p class="mb-1"><strong>Sale invoice:</strong> <?php echo htmlspecialchars($sale_invoice_no, ENT_QUOTES, 'UTF-8'); ?></p>
            <p class="mb-1"><strong>Total amount:</strong> <?php echo htmlspecialchars((string) ($v['total_amount'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars((string) ($v['currency'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
            <?php if ($si_link !== ''): ?>
                <a class="btn btn-primary btn-sm mt-2" href="<?php echo htmlspecialchars($si_link, ENT_QUOTES, 'UTF-8'); ?>">Open linked sale invoice</a>
            <?php endif; ?>
            <a class="btn btn-outline-secondary btn-sm mt-2 ml-1" href="javascript:history.back()">Back</a>
        </div>
    </div>
    <h6 class="mb-2">Payment lines</h6>
    <table class="table table-sm table-bordered bg-white">
        <thead class="thead-light">
            <tr>
                <th>Type</th>
                <th>Deposit into</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td><?php echo htmlspecialchars((string) ($it['payment_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($it['deposit_into'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="text-right"><?php echo htmlspecialchars((string) ($it['amount'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($items)): ?>
                <tr><td colspan="3" class="text-muted">No lines.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
