<?php
session_start();
require_once 'config.php';

$consignment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($consignment_id <= 0) {
    die('Invalid consignment ID');
}

$c = getRecord("SELECT * FROM tbl_consignment_in WHERE id = $consignment_id");
if (!$c) {
    die('Consignment In not found');
}

$items = getList("SELECT * FROM tbl_consignment_in_items WHERE consignment_id = $consignment_id ORDER BY id ASC");
$currency = !empty($c['currency']) ? htmlspecialchars($c['currency']) : 'AED';
$cdate = !empty($c['consignment_date']) ? $c['consignment_date'] : '';
$title = defined('COMPANY_NAME') ? COMPANY_NAME : (isset($Proj_Title) ? $Proj_Title : 'Consignment In');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo htmlspecialchars($title); ?> — Consignment In <?php echo htmlspecialchars($c['consignment_no'] ?? ''); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; color: #111; }
        h1 { font-size: 1.25rem; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 0.85rem; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f5f5f5; }
        .meta { margin: 12px 0; line-height: 1.6; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <h1>Consignment In</h1>
    <div class="meta">
        <div><strong>No:</strong> <?php echo htmlspecialchars($c['consignment_no'] ?? ''); ?></div>
        <div><strong>Date:</strong> <?php echo htmlspecialchars($cdate); ?></div>
        <div><strong>Party:</strong> <?php echo htmlspecialchars($c['customer_name'] ?? ''); ?></div>
        <?php if (!empty($c['against_of'])): ?>
        <div><strong>Against:</strong> <?php echo htmlspecialchars($c['against_of']); ?></div>
        <?php endif; ?>
        <div><strong>Grand Total:</strong> <?php echo $currency; ?> <?php echo number_format((float)($c['grand_total'] ?? 0), 2); ?></div>
    </div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Product</th>
                <th>Barcode</th>
                <th>Qty</th>
                <th>Gross Wt</th>
                <th>Net Wt</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $i = 0;
            foreach ($items as $row) {
                $i++;
                ?>
                <tr>
                    <td><?php echo $i; ?></td>
                    <td><?php echo htmlspecialchars($row['product_name'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($row['barcode'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars((string)($row['quantity'] ?? '')); ?></td>
                    <td><?php echo number_format((float)($row['gross_weight'] ?? 0), 3); ?></td>
                    <td><?php echo number_format((float)($row['net_weight'] ?? 0), 3); ?></td>
                    <td><?php echo number_format((float)($row['net_amt_with_tax'] ?? $row['amount'] ?? 0), 2); ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
    <p class="no-print" style="margin-top: 24px;"><button type="button" onclick="window.print()">Print</button></p>
</body>
</html>
