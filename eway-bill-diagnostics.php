<?php
/**
 * e-Way Bill: verify header-based credentials + list e-way fields on invoices.
 * Open: /admin/eway-bill-diagnostics.php
 */
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/api/eway_generate.php';

if (empty($_SESSION['Admin'])) {
    header('Location: index.php');
    exit;
}

$has_eway_cols = false;
$ew_chk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_invoices LIKE 'eway_bill_no'");
if ($ew_chk && mysqli_num_rows($ew_chk) > 0) {
    $has_eway_cols = true;
}
if ($ew_chk) {
    mysqli_free_result($ew_chk);
}

$recent = [];
$has_eway_status_col = false;
$es_chk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_invoices LIKE 'eway_status'");
if ($es_chk && mysqli_num_rows($es_chk) > 0) {
    $has_eway_status_col = true;
}
if ($es_chk) {
    mysqli_free_result($es_chk);
}
if ($has_eway_cols) {
    $extra_st = $has_eway_status_col ? ", IFNULL(eway_status,'') AS eway_status, IFNULL(eway_response,'') AS eway_response" : '';
    $recent = getList(
        "SELECT id, invoice_no, invoice_date, grand_total,
                IFNULL(customer_gstin,'') AS customer_gstin,
                IFNULL(eway_vehicle_no,'') AS eway_vehicle_no,
                IFNULL(eway_distance_km,'') AS eway_distance_km,
                IFNULL(eway_bill_no,'') AS eway_bill_no,
                eway_bill_date
                $extra_st
         FROM tbl_sale_invoices
         ORDER BY id DESC
         LIMIT 30"
    );
}

$test = null;
if (!empty($_GET['test']) && $_GET['test'] === '1') {
    $test = auragold_eway_test_authentication($conn, null);
}

if (!empty($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'eway_columns' => $has_eway_cols,
        'auth_test' => $test ?? auragold_eway_test_authentication($conn, null),
        'recent_invoices_sample' => array_slice(is_array($recent) ? $recent : [], 0, 5),
    ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRETTY_PRINT);
    exit;
}

$cred = auragold_eway_credentials();
$branch_gst = auragold_branch_gstin_for_eway($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>e-Way Bill diagnostics — <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        body { background: #f1f5f9; padding: 1.5rem; }
        .card { border: none; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        code { font-size: 0.85rem; }
    </style>
</head>
<body>
    <div class="container" style="max-width: 1100px;">
        <h4 class="mb-3">e-Way Bill diagnostics</h4>
        <p class="text-muted">WhiteBooks uses <strong>headers</strong> (email, ip_address, client_id, client_secret, gstin) — no separate auth API. Responses are logged to <code>admin/logs/eway_log.txt</code>.</p>

        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title">Configuration (masked)</h5>
                <ul class="mb-0">
                    <li><strong>Branch GSTIN</strong> (from <code>tbl_branches.gst_no</code> / <code>WHITEBOOKS_AUTH_GSTIN</code>): <code><?php echo htmlspecialchars($branch_gst !== '' ? $branch_gst : '(empty — set in branch profile)'); ?></code></li>
                    <li><strong>Email</strong> (header): <code><?php echo htmlspecialchars($cred['email'] !== '' ? auragold_eway_mask_secret($cred['email'], 4) : '(empty — set WHITEBOOKS_EMAIL in local file, or tbl_users.EmailId for logged-in user)'); ?></code></li>
                    <li><strong>ip_address</strong> (header): <code><?php echo htmlspecialchars($cred['ip_address'] !== '' ? $cred['ip_address'] : auragold_eway_server_ip()); ?></code></li>
                    <li><strong>Client ID</strong>: <code><?php echo htmlspecialchars(auragold_eway_mask_secret($cred['client_id'])); ?></code></li>
                    <li><strong>Client secret</strong>: <code><?php echo htmlspecialchars(auragold_eway_mask_secret($cred['client_secret'])); ?></code></li>
                    <li><strong>Generate URL</strong>: <code><?php echo htmlspecialchars($cred['generate_url']); ?></code></li>
                </ul>
                <a class="btn btn-primary btn-sm mt-3" href="eway-bill-diagnostics.php?test=1">Validate e-Way settings</a>
                <a class="btn btn-outline-secondary btn-sm mt-3 ml-1" href="eway-bill-diagnostics.php?format=json" target="_blank">Raw JSON</a>
                <a class="btn btn-outline-secondary btn-sm mt-3 ml-1" href="sale-invoice.php">Back to Sale Invoice</a>
            </div>
        </div>

        <?php if ($test !== null): ?>
        <div class="card mb-3 border-<?php echo $test['ok'] ? 'success' : 'danger'; ?>">
            <div class="card-body">
                <h5 class="card-title">Settings check</h5>
                <p class="mb-1"><strong><?php echo $test['ok'] ? 'OK' : 'Failed'; ?>:</strong> <?php echo htmlspecialchars($test['message']); ?></p>
                <?php if (!empty($test['http_code'])): ?>
                    <p class="mb-1 text-muted small">HTTP: <?php echo (int) $test['http_code']; ?></p>
                <?php endif; ?>
                <?php if (!empty($test['api_response']) && is_array($test['api_response'])): ?>
                    <details class="mt-2">
                        <summary>API response (no token values shown in full)</summary>
                        <pre class="small bg-light p-2 mt-2 mb-0" style="max-height: 220px; overflow:auto;"><?php
                            $safe = $test['api_response'];
                            if (isset($safe['authtoken'])) {
                                $safe['authtoken'] = '(received, length ' . strlen((string) $test['api_response']['authtoken']) . ')';
                            }
                            if (isset($safe['authToken'])) {
                                $safe['authToken'] = '(received)';
                            }
                            echo htmlspecialchars(json_encode($safe, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE));
                        ?></pre>
                    </details>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title">Database: e-Way columns</h5>
                <?php if ($has_eway_cols): ?>
                    <p class="text-success mb-0">Columns <code>eway_bill_no</code> / related exist on <code>tbl_sale_invoices</code>.</p>
                <?php else: ?>
                    <p class="text-warning mb-0">Columns not found yet — save a sale invoice once (migration runs) or run <code>admin/sql/alter_tbl_sale_invoices_eway_columns.sql</code>.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Last 30 sale invoices (e-Way fields)</h5>
                <?php if (!$has_eway_cols): ?>
                    <p class="text-muted">No e-Way columns — nothing to list.</p>
                <?php elseif (empty($recent)): ?>
                    <p class="text-muted">No invoices found.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered bg-white mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Invoice</th>
                                    <th>Date</th>
                                    <th>Grand total</th>
                                    <th>eway_bill_no</th>
                                    <th>eway_bill_date</th>
                                    <?php if ($has_eway_status_col): ?><th>eway_status</th><th>eway_response (trunc.)</th><?php endif; ?>
                                    <th>Vehicle / km</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent as $r): ?>
                                <tr>
                                    <td><?php echo (int) ($r['id'] ?? 0); ?></td>
                                    <td><a href="sale-invoice.php?id=<?php echo (int) ($r['id'] ?? 0); ?>"><?php echo htmlspecialchars((string) ($r['invoice_no'] ?? '')); ?></a></td>
                                    <td><?php echo htmlspecialchars(substr((string) ($r['invoice_date'] ?? ''), 0, 10)); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($r['grand_total'] ?? '')); ?></td>
                                    <td><strong><?php echo htmlspecialchars((string) ($r['eway_bill_no'] ?? '')); ?></strong></td>
                                    <td><?php echo htmlspecialchars(substr((string) ($r['eway_bill_date'] ?? ''), 0, 19)); ?></td>
                                    <?php if ($has_eway_status_col): ?>
                                    <td><?php echo htmlspecialchars((string) ($r['eway_status'] ?? '')); ?></td>
                                    <td><small class="text-muted"><?php echo htmlspecialchars(substr((string) ($r['eway_response'] ?? ''), 0, 120)); ?></small></td>
                                    <?php endif; ?>
                                    <td><?php
                                        $__v = trim((string) ($r['eway_vehicle_no'] ?? '') . ' / ' . (string) ($r['eway_distance_km'] ?? ''));
                                        echo htmlspecialchars($__v);
                                        ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="small text-muted mt-2 mb-0">If grand total ≥ ₹50,000 and API succeeded, <code>eway_bill_no</code> should be non-empty. If it stays empty, open browser Network tab on save and inspect JSON response key <code>eway</code>.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
