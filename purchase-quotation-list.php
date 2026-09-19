<?php
session_start();
require_once 'config.php';

if (empty($_SESSION['Admin'])) {
    header('Location: login.php');
    exit;
}

$saved = isset($_GET['saved']) && $_GET['saved'] === '1';
$rows = getList("
    SELECT id, quotation_no, supplier_name, quotation_date, grand_total, currency, status
    FROM tbl_purchase_quotations
    WHERE status IS NULL OR LOWER(TRIM(status)) NOT IN ('deleted')
    ORDER BY id DESC
    LIMIT 300
");
if (!is_array($rows)) {
    $rows = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Purchase Quotations</title>
    <?php include 'header-script.php'; ?>
    <style>
        body { padding: 1.5rem; background: #f8fafc; }
        .pq-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.75rem; }
        .pq-table-wrap { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.08); overflow: auto; }
        table.pq-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        table.pq-table th, table.pq-table td { padding: 0.6rem 0.75rem; border-bottom: 1px solid #e2e8f0; text-align: left; }
        table.pq-table th { background: #f1f5f9; font-weight: 600; }
        table.pq-table tr:hover { background: #fafafa; }
    </style>
</head>
<body>
    <?php if ($saved): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Purchase Quotation Created Successfully
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
    <?php endif; ?>
    <div class="pq-toolbar">
        <h4 class="mb-0">Purchase Quotations</h4>
        <a class="btn btn-primary btn-sm" href="purchase-quotation.php">New Purchase Quotation</a>
    </div>
    <div class="pq-table-wrap">
        <table class="pq-table">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Date</th>
                    <th>Supplier</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $id = (int) ($r['id'] ?? 0);
                    $qno = htmlspecialchars((string) ($r['quotation_no'] ?? ''));
                    $sn = htmlspecialchars((string) ($r['supplier_name'] ?? ''));
                    $dt = !empty($r['quotation_date']) ? htmlspecialchars(date('d-m-Y', strtotime($r['quotation_date']))) : '';
                    $gt = isset($r['grand_total']) ? number_format((float) $r['grand_total'], 2) : '0.00';
                    $cur = htmlspecialchars((string) ($r['currency'] ?? ''));
                    $st = htmlspecialchars((string) ($r['status'] ?? ''));
                    ?>
                    <tr>
                        <td><?php echo $qno; ?></td>
                        <td><?php echo $dt; ?></td>
                        <td><?php echo $sn; ?></td>
                        <td><?php echo $cur ? $cur . ' ' : ''; ?><?php echo $gt; ?></td>
                        <td><?php echo $st; ?></td>
                        <td><a class="btn btn-sm btn-outline-secondary" href="purchase-quotation.php?id=<?php echo $id; ?>">Open</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (count($rows) === 0): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No purchase quotations yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
