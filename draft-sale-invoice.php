<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/includes/auragold_branch_data_scope.php';
require_once __DIR__ . '/includes/auragold_sale_invoice_draft_helpers.php';

if (empty($_SESSION['Admin'])) {
    header('Location: login.php');
    exit;
}

$saved = isset($_GET['saved']) && $_GET['saved'] === '1';

$filter_q = trim((string) ($_GET['q'] ?? ''));
$filter_date_from = trim((string) ($_GET['date_from'] ?? ''));
$filter_date_to = trim((string) ($_GET['date_to'] ?? ''));

$where = auragold_sale_invoice_draft_list_where_sql($conn, 'si');

if ($filter_q !== '') {
    $q_esc = esc($filter_q);
    $where .= " AND ("
        . "si.customer_name LIKE '%{$q_esc}%'"
        . " OR si.invoice_no LIKE '%{$q_esc}%'"
        . " OR CAST(si.id AS CHAR) LIKE '%{$q_esc}%'"
        . " OR CONCAT('DRAFT-', si.id) LIKE '%{$q_esc}%'"
        . ")";
}

if ($filter_date_from !== '') {
    $ts = strtotime($filter_date_from);
    if ($ts) {
        $where .= " AND si.invoice_date >= '" . date('Y-m-d', $ts) . "'";
    }
}

if ($filter_date_to !== '') {
    $ts = strtotime($filter_date_to);
    if ($ts) {
        $where .= " AND si.invoice_date <= '" . date('Y-m-d', $ts) . "'";
    }
}

$rows = getList("
    SELECT
        si.id,
        si.invoice_no,
        si.customer_name,
        si.invoice_date,
        si.grand_total,
        si.currency,
        si.status,
        si.updated_at,
        (SELECT COUNT(*) FROM tbl_sale_invoice_items WHERE invoice_id = si.id) AS item_count
    FROM tbl_sale_invoices si
    $where
    ORDER BY COALESCE(si.updated_at, si.created_at) DESC, si.id DESC
    LIMIT 300
");
if (!is_array($rows)) {
    $rows = [];
}

$row_count = count($rows);
$has_filters = ($filter_q !== '' || $filter_date_from !== '' || $filter_date_to !== '');
$page_title = 'Draft Invoices — ' . (function_exists('auragold_app_name') ? auragold_app_name() : 'GoldMatrix');
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <?php include __DIR__ . '/header-script.php'; ?>
    <style>
        :root {
            --dsi-navy: #0b2d50;
            --dsi-navy-mid: #11294b;
            --dsi-gold: #c5a864;
            --dsi-gold-cta: #d9a526;
            --dsi-border: #dde5ef;
            --dsi-text: #334155;
            --dsi-muted: #64748b;
        }
        .dsi-page { padding: 18px 22px 80px; max-width: 1400px; margin: 0 auto; }
        .dsi-head-row {
            display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between;
            gap: 14px; margin-bottom: 14px;
        }
        .dsi-head-row h1 { margin: 0 0 4px; font-size: 1.25rem; font-weight: 700; color: var(--dsi-navy); }
        .dsi-head-row .dsi-sub {
            margin: 0; font-size: 0.8125rem; color: var(--dsi-muted); max-width: 640px; line-height: 1.45;
        }
        .dsi-head-actions { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
        .dsi-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 6px;
            height: 38px; padding: 0 16px; border-radius: 8px; font-size: 0.8125rem; font-weight: 600;
            text-decoration: none; cursor: pointer; border: 1px solid transparent;
            transition: background 0.15s, border-color 0.15s, box-shadow 0.15s;
        }
        .dsi-btn-gold {
            background: linear-gradient(180deg, #e4b84a 0%, var(--dsi-gold-cta) 100%);
            border-color: #c49218; color: #fff; box-shadow: 0 2px 6px rgba(217, 165, 38, 0.28);
        }
        .dsi-btn-gold:hover { filter: brightness(1.04); color: #fff; text-decoration: none; }
        .dsi-btn-outline { background: #fff; border-color: var(--dsi-border); color: var(--dsi-navy); }
        .dsi-btn-outline:hover { background: #f8fafc; color: var(--dsi-navy); text-decoration: none; }
        .dsi-icon-btn { width: 38px; min-width: 38px; padding: 0; }
        .dsi-alert-wrap { margin-bottom: 12px; }
        .dsi-filter-card {
            background: #fff; border: 1px solid var(--dsi-border); border-radius: 10px;
            box-shadow: 0 1px 3px rgba(11, 45, 80, 0.05); padding: 12px 14px; margin-bottom: 12px;
        }
        .dsi-filter-form {
            display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px 12px;
        }
        .dsi-filter-field { flex: 1 1 180px; min-width: 0; }
        .dsi-filter-field label {
            display: block; font-size: 0.6875rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.04em; color: var(--dsi-muted); margin-bottom: 4px;
        }
        .dsi-filter-field input {
            width: 100%; height: 36px; border: 1px solid var(--dsi-border); border-radius: 7px;
            padding: 0 10px; font-size: 0.8125rem; color: var(--dsi-text); box-sizing: border-box;
        }
        .dsi-filter-field input:focus {
            outline: none; border-color: var(--dsi-gold); box-shadow: 0 0 0 3px rgba(197, 168, 100, 0.18);
        }
        .dsi-filter-actions { display: flex; align-items: center; gap: 8px; flex: 0 0 auto; }
        .dsi-card {
            background: #fff; border: 1px solid var(--dsi-border); border-radius: 10px;
            box-shadow: 0 1px 3px rgba(11, 45, 80, 0.05); overflow: hidden;
        }
        .dsi-table-wrap { overflow-x: auto; }
        table.dsi-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        table.dsi-table th, table.dsi-table td {
            padding: 0.65rem 0.85rem; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: middle;
        }
        table.dsi-table th {
            background: var(--dsi-navy); color: #fff; font-weight: 600; font-size: 0.6875rem;
            text-transform: uppercase; letter-spacing: 0.04em; white-space: nowrap;
        }
        table.dsi-table tbody tr:hover td { background: #fafbfc; }
        table.dsi-table tbody tr:nth-child(even) td { background: #fcfdfe; }
        table.dsi-table tbody tr:nth-child(even):hover td { background: #f5f8fb; }
        .dsi-ref { color: var(--dsi-muted); font-size: 0.8125rem; }
        .dsi-badge {
            display: inline-block; padding: 2px 8px; border-radius: 999px; background: #fff6e2;
            color: var(--dsi-navy); font-size: 0.625rem; font-weight: 700; text-transform: uppercase;
        }
        .dsi-btn-continue {
            display: inline-flex; align-items: center; height: 30px; padding: 0 12px; border-radius: 6px;
            font-size: 0.75rem; font-weight: 600; background: var(--dsi-navy); color: #fff; text-decoration: none;
        }
        .dsi-btn-continue:hover { background: var(--dsi-navy-mid); color: #fff; text-decoration: none; }
        .dsi-empty { text-align: center; color: var(--dsi-muted); padding: 2.5rem 1rem !important; font-style: italic; }
        .dsi-footer-bar {
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px;
            padding: 10px 14px; border-top: 1px solid var(--dsi-border); background: #fafbfc;
            font-size: 0.8125rem; color: var(--dsi-muted);
        }
        .dsi-count-badge {
            display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px;
            background: #eef4fb; color: var(--dsi-navy); font-size: 0.75rem; font-weight: 700;
        }
        @media (max-width: 767px) {
            .dsi-page { padding: 12px 12px 72px; }
            .dsi-filter-field { flex: 1 1 100%; }
            .dsi-filter-actions { width: 100%; }
            .dsi-filter-actions .dsi-btn { flex: 1; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="layout-content">
        <div class="container-fluid flex-grow-1" style="padding-top: 0; padding-bottom: 0;">
            <div class="dsi-page">
                <?php if ($saved): ?>
                    <div class="dsi-alert-wrap">
                        <div class="alert alert-success alert-dismissible fade show mb-0" role="alert">
                            Draft saved successfully.
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="dsi-head-row">
                    <div>
                        <h1>Draft Invoices</h1>
                        <p class="dsi-sub">Temporary saves without invoice number. Continue editing and click Save to assign the next invoice number.</p>
                    </div>
                    <div class="dsi-head-actions">
                        <button type="button" class="dsi-btn dsi-btn-outline dsi-icon-btn" id="dsiRefresh" title="Refresh">
                            <i class="feather icon-refresh-cw"></i>
                        </button>
                        <a class="dsi-btn dsi-btn-gold" href="sale-invoice.php">
                            <i class="feather icon-plus"></i> New Sales Invoice
                        </a>
                    </div>
                </div>

                <div class="dsi-filter-card">
                    <form class="dsi-filter-form" method="get" action="draft-sale-invoice.php" id="dsiFilterForm">
                        <div class="dsi-filter-field">
                            <label for="dsiFilterQ">Search</label>
                            <input type="text" id="dsiFilterQ" name="q" value="<?php echo htmlspecialchars($filter_q); ?>" placeholder="Draft ref, customer name…" autocomplete="off">
                        </div>
                        <div class="dsi-filter-field" style="flex: 0 1 150px;">
                            <label for="dsiFilterDateFrom">Date From</label>
                            <input type="date" id="dsiFilterDateFrom" name="date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                        </div>
                        <div class="dsi-filter-field" style="flex: 0 1 150px;">
                            <label for="dsiFilterDateTo">Date To</label>
                            <input type="date" id="dsiFilterDateTo" name="date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                        </div>
                        <div class="dsi-filter-actions">
                            <button type="submit" class="dsi-btn dsi-btn-gold">
                                <i class="feather icon-filter"></i> Apply
                            </button>
                            <?php if ($has_filters): ?>
                                <a href="draft-sale-invoice.php" class="dsi-btn dsi-btn-outline">Clear</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div class="dsi-card">
                    <div class="dsi-table-wrap">
                        <table class="dsi-table" id="dsiDraftTable">
                            <thead>
                                <tr>
                                    <th>Draft Ref</th>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Items</th>
                                    <th>Grand Total</th>
                                    <th>Last Updated</th>
                                    <th style="width: 110px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $r): ?>
                                    <?php
                                    $id = (int) ($r['id'] ?? 0);
                                    $rawRef = trim((string) ($r['invoice_no'] ?? ''));
                                    if ($rawRef === '' || !preg_match('/^DRAFT-/i', $rawRef)) {
                                        $rawRef = 'DRAFT-' . $id;
                                    }
                                    $ref = htmlspecialchars($rawRef);
                                    $cust = htmlspecialchars((string) ($r['customer_name'] ?? ''));
                                    if ($cust === '') {
                                        $cust = '—';
                                    }
                                    $dt = !empty($r['invoice_date']) ? htmlspecialchars(date('d-m-Y', strtotime($r['invoice_date']))) : '—';
                                    $upd = !empty($r['updated_at']) ? htmlspecialchars(date('d-m-Y H:i', strtotime($r['updated_at']))) : '—';
                                    $gt = isset($r['grand_total']) ? number_format((float) $r['grand_total'], 2) : '0.00';
                                    $cur = htmlspecialchars((string) ($r['currency'] ?? 'INR'));
                                    $items = (int) ($r['item_count'] ?? 0);
                                    ?>
                                    <tr>
                                        <td><span class="dsi-badge">Draft</span> <span class="dsi-ref"><?php echo $ref; ?></span></td>
                                        <td><?php echo $dt; ?></td>
                                        <td><?php echo $cust; ?></td>
                                        <td><?php echo $items; ?></td>
                                        <td><?php echo $cur . ' ' . $gt; ?></td>
                                        <td><?php echo $upd; ?></td>
                                        <td><a class="dsi-btn-continue" href="sale-invoice.php?id=<?php echo $id; ?>">Continue</a></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($row_count === 0): ?>
                                    <tr>
                                        <td colspan="7" class="dsi-empty">
                                            <?php echo $has_filters ? 'No draft invoices match your filters.' : 'No draft invoices yet.'; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="dsi-footer-bar">
                        <span>
                            <?php if ($row_count === 0): ?>
                                Showing 0 entries
                            <?php else: ?>
                                Showing 1 to <?php echo (int) $row_count; ?> of <?php echo (int) $row_count; ?> entries
                            <?php endif; ?>
                            <?php if ($has_filters): ?>
                                <span class="text-muted"> (filtered)</span>
                            <?php endif; ?>
                        </span>
                        <span class="dsi-count-badge">
                            <i class="feather icon-file-text"></i>
                            <?php echo (int) $row_count; ?> draft<?php echo $row_count === 1 ? '' : 's'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/footer-script.php'; ?>
    <script>
    (function () {
        var refreshBtn = document.getElementById('dsiRefresh');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function () { window.location.reload(); });
        }

        var form = document.getElementById('dsiFilterForm');
        var searchInput = document.getElementById('dsiFilterQ');
        if (searchInput && form) {
            searchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    form.submit();
                }
            });
        }

        ['dsiFilterDateFrom', 'dsiFilterDateTo'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', function () {
                    if (form) form.submit();
                });
            }
        });
    })();
    </script>
</body>
</html>
