<?php
/**
 * Barcode Management — all barcoded stock (present + sold), metal-wise tabs.
 */
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/barcode_management_fetch.php';

$tab = isset($_GET['tab']) ? strtolower(trim((string) $_GET['tab'])) : 'all';
$fetch = auragold_barcode_management_fetch($conn, $tab);
$rows = $fetch['rows'];
$load_error = $fetch['error'];
$bm_metals = $fetch['metals'];
$bm_active_tab = $fetch['active_tab'];
$bm_counts = $fetch['counts'];

$page_title = 'Barcode Management — ' . auragold_app_name();
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
    <?php include __DIR__ . '/header-script.php'; ?>
    <style>
        :root { --bm-navy: #11294b; --bm-gold: #c9a227; }
        .bm-wrap { padding: 16px 20px 28px; }
        .bm-head { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
        .bm-title { font-size: 1.2rem; font-weight: 700; color: var(--bm-navy); margin: 0 0 4px; }
        .bm-sub { font-size: 0.85rem; color: #64748b; margin: 0; }
        .bm-tabs { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
        .bm-tabs a {
            padding: 7px 14px; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff;
            color: var(--bm-navy); font-size: 0.82rem; font-weight: 600; text-decoration: none;
        }
        .bm-tabs a:hover { background: #fdf8f0; border-color: var(--bm-gold); }
        .bm-tabs a.active { background: var(--bm-navy); color: #fff; border-color: var(--bm-navy); }
        .bm-stats { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 12px; }
        .bm-stat {
            background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 14px;
            font-size: 0.8rem; color: #475569;
        }
        .bm-stat strong { color: var(--bm-navy); font-size: 1rem; margin-right: 4px; }
        .bm-toolbar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 12px; }
        .bm-toolbar input, .bm-toolbar select {
            border: 1px solid #cbd5e1; border-radius: 6px; padding: 7px 10px; font-size: 0.85rem;
        }
        .bm-toolbar input[type="search"] { min-width: 220px; }
        .bm-card {
            background: #fff; border: 1px solid #e2e8f0; border-radius: 10px;
            box-shadow: 0 2px 8px rgba(17, 41, 75, 0.06); overflow: hidden;
        }
        .bm-scroll { overflow: auto; max-height: calc(100vh - 280px); }
        .bm-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; white-space: nowrap; }
        .bm-table thead th {
            position: sticky; top: 0; z-index: 2; background: var(--bm-navy); color: #fff;
            padding: 9px 8px; font-weight: 700; border-bottom: 1px solid rgba(201, 162, 39, 0.35);
        }
        .bm-table tbody td { padding: 7px 8px; border-bottom: 1px solid #eef2f7; vertical-align: middle; }
        .bm-table tbody tr:nth-child(even) { background: #fcfdff; }
        .bm-table tbody tr.bm-hidden { display: none; }
        .bm-badge {
            display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 0.72rem; font-weight: 700;
        }
        .bm-badge-present { background: #dcfce7; color: #166534; }
        .bm-badge-sold { background: #fee2e2; color: #991b1b; }
        .bm-empty { padding: 32px; text-align: center; color: #94a3b8; }
        .bm-alert { padding: 12px 16px; border-radius: 8px; background: #fef2f2; color: #991b1b; margin-bottom: 12px; }
    </style>
</head>
<body>
<div class="layout-wrapper layout-2">
    <div class="layout-inner">
        <div class="layout-container">
            <div class="layout-content">
                <div class="container-fluid flex-grow-1" style="padding-top:0;">
                    <?php include __DIR__ . '/sidebar.php'; ?>

                    <div class="bm-wrap">
                        <div class="bm-head">
                            <div>
                                <h1 class="bm-title">Barcode Management</h1>
                                <p class="bm-sub">All barcoded stock — present and sold — grouped by metal.</p>
                            </div>
                        </div>

                        <div class="bm-tabs" role="tablist" aria-label="Metal tabs">
                            <a href="barcode-management.php?tab=all" class="<?php echo $bm_active_tab === 'all' ? 'active' : ''; ?>">All Metals</a>
                            <?php foreach ($bm_metals as $m): ?>
                                <?php
                                $mslug = (string) ($m['slug'] ?? '');
                                $mname = (string) ($m['name'] ?? '');
                                $mhref = 'barcode-management.php?' . http_build_query(['tab' => $mslug]);
                                ?>
                                <a href="<?php echo htmlspecialchars($mhref, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $bm_active_tab === $mslug ? 'active' : ''; ?>"><?php echo htmlspecialchars($mname, ENT_QUOTES, 'UTF-8'); ?></a>
                            <?php endforeach; ?>
                        </div>

                        <div class="bm-stats">
                            <div class="bm-stat"><strong><?php echo (int) ($bm_counts['total'] ?? 0); ?></strong> Total barcodes</div>
                            <div class="bm-stat"><strong><?php echo (int) ($bm_counts['present'] ?? 0); ?></strong> Present</div>
                            <div class="bm-stat"><strong><?php echo (int) ($bm_counts['sold'] ?? 0); ?></strong> Sold</div>
                        </div>

                        <?php if ($load_error !== ''): ?>
                            <div class="bm-alert"><?php echo $load_error; ?></div>
                        <?php endif; ?>

                        <div class="bm-toolbar">
                            <input type="search" id="bmSearch" placeholder="Search barcode, product, invoice…" aria-label="Search barcodes">
                            <select id="bmStatusFilter" aria-label="Filter by stock status">
                                <option value="all">All status</option>
                                <option value="Present">Present only</option>
                                <option value="Sold">Sold only</option>
                            </select>
                        </div>

                        <div class="bm-card">
                            <div class="bm-scroll">
                                <table class="bm-table" id="bmTable">
                                    <thead>
                                        <tr>
                                            <th>Stock Status</th>
                                            <th>Metal</th>
                                            <th>Barcode No</th>
                                            <th>Product Name</th>
                                            <th>Category</th>
                                            <th>Branch</th>
                                            <th>Gross Wt</th>
                                            <th>Net Wt</th>
                                            <th>Bal Qty</th>
                                            <th>Bal Wt</th>
                                            <th>Carat</th>
                                            <th>HUID No.</th>
                                            <th>Location</th>
                                            <th>Voucher Type</th>
                                            <th>Invoice No.</th>
                                            <th>Barcoded Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (empty($rows)): ?>
                                        <tr><td colspan="16" class="bm-empty">No barcoded stock found for this metal.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($rows as $r): ?>
                                            <?php
                                            $st = (string) ($r['barcode_stock_status'] ?? 'Sold');
                                            $gw = $r['sj_gross_weight'] ?? $r['opening_weight'] ?? null;
                                            $nw = $r['sj_net_weight'] ?? null;
                                            $carat = $r['pc_carat'] ?? $r['sj_karat'] ?? '';
                                            $barcoded = $r['sj_created_at'] ?? $r['stock_created_at'] ?? '';
                                            if ($barcoded !== '' && $barcoded !== null) {
                                                $barcoded = substr((string) $barcoded, 0, 19);
                                            }
                                            $search_blob = strtolower(implode(' ', array_filter([
                                                (string) ($r['barcode'] ?? ''),
                                                (string) ($r['product_name'] ?? ''),
                                                (string) ($r['category_display'] ?? ''),
                                                (string) ($r['invoice_no'] ?? ''),
                                                (string) ($r['metal_name'] ?? ''),
                                                (string) ($r['huid_no'] ?? ''),
                                            ])));
                                            ?>
                                            <tr data-bm-status="<?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>" data-bm-search="<?php echo htmlspecialchars($search_blob, ENT_QUOTES, 'UTF-8'); ?>">
                                                <td>
                                                    <span class="bm-badge <?php echo $st === 'Present' ? 'bm-badge-present' : 'bm-badge-sold'; ?>"><?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?></span>
                                                </td>
                                                <td><?php echo htmlspecialchars((string) ($r['metal_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><strong><?php echo htmlspecialchars((string) ($r['barcode'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                                <td><?php echo htmlspecialchars((string) ($r['product_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars((string) ($r['category_display'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars((string) ($r['branch_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars(gas_fmt_num($gw, 3), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars(gas_fmt_num($nw, 3), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars(gas_fmt_num($r['current_qty'] ?? null, 2), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars(gas_fmt_num($r['current_weight'] ?? null, 3), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars((string) $carat, ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars((string) ($r['huid_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars((string) ($r['sj_location'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars((string) ($r['voucher_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars((string) ($r['invoice_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars((string) $barcoded, ENT_QUOTES, 'UTF-8'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/footer-script.php'; ?>
<script>
(function () {
    var searchEl = document.getElementById('bmSearch');
    var statusEl = document.getElementById('bmStatusFilter');
    var rows = document.querySelectorAll('#bmTable tbody tr[data-bm-status]');

    function applyFilters() {
        var q = (searchEl && searchEl.value ? searchEl.value : '').toLowerCase().trim();
        var st = statusEl ? statusEl.value : 'all';
        rows.forEach(function (tr) {
            var okStatus = st === 'all' || tr.getAttribute('data-bm-status') === st;
            var blob = tr.getAttribute('data-bm-search') || '';
            var okSearch = q === '' || blob.indexOf(q) !== -1;
            tr.classList.toggle('bm-hidden', !(okStatus && okSearch));
        });
    }

    if (searchEl) searchEl.addEventListener('input', applyFilters);
    if (statusEl) statusEl.addEventListener('change', applyFilters);
})();
</script>
</body>
</html>
