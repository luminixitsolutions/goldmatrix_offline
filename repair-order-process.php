<?php
session_start();
require_once 'config.php';

// Filters
$search = isset($_GET['search']) ? esc($_GET['search']) : '';
$from_date = isset($_GET['from_date']) ? esc($_GET['from_date']) : '';
$to_date = isset($_GET['to_date']) ? esc($_GET['to_date']) : '';
$order_no = isset($_GET['order_no']) ? esc($_GET['order_no']) : '';
$customer = isset($_GET['customer']) ? esc($_GET['customer']) : '';
$status_filter = isset($_GET['status']) ? esc($_GET['status']) : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 25;
$per_page = max(10, min(100, $per_page));
$offset = ($page - 1) * $per_page;

// Build WHERE clause (repair orders only)
$where = "1=1";
if (!empty($from_date)) $where .= " AND ro.order_date >= '$from_date'";
if (!empty($to_date)) $where .= " AND ro.order_date <= '$to_date'";
if (!empty($order_no)) $where .= " AND ro.order_no LIKE '%$order_no%'";
if (!empty($customer)) $where .= " AND ro.customer_name LIKE '%$customer%'";
if (!empty($status_filter)) $where .= " AND ro.status = '$status_filter'";
if (!empty($search)) {
    $where .= " AND (ro.order_no LIKE '%$search%' OR ro.customer_name LIKE '%$search%' OR roi.product_name LIKE '%$search%' OR roi.design_no LIKE '%$search%' OR roi.barcode LIKE '%$search%')";
}

// Get repair order items with order and product data
$items_query = "
    SELECT 
        roi.id as item_id,
        roi.order_id,
        roi.product_id,
        roi.product_characteristic_id,
        roi.barcode,
        roi.product_name,
        roi.design_no,
        roi.quantity,
        roi.gross_weight,
        roi.final_weight,
        roi.net_weight,
        roi.rate,
        roi.amount,
        roi.status as item_status,
        ro.order_no,
        ro.customer_name,
        ro.order_date,
        ro.due_date,
        ro.status as order_status,
        ro.sales_person,
        p.name as product_name_from_product,
        p.article,
        pc.sku_code as rfid_code
    FROM tbl_repair_order_items roi
    INNER JOIN tbl_repair_orders ro ON roi.order_id = ro.id
    LEFT JOIN tbl_products p ON roi.product_id = p.id
    LEFT JOIN tbl_product_characteristics pc ON roi.product_characteristic_id = pc.id
    WHERE $where
    ORDER BY ro.order_date DESC, ro.id DESC, roi.id ASC
";

$all_items = getList($items_query);

$items = [];
foreach ($all_items as $row) {
    $product_name = !empty($row['product_name']) ? $row['product_name'] : ($row['product_name_from_product'] ?? $row['article'] ?? 'N/A');
    $row['product_name_display'] = $product_name;
    $row['rfid_code'] = $row['rfid_code'] ?? '';
    $row['product_photo'] = '';
    $items[] = $row;
}

$total_records = count($items);
$total_pages = $total_records > 0 ? ceil($total_records / $per_page) : 1;
$page = max(1, min($page, $total_pages));
$offset = ($page - 1) * $per_page;
$items = array_slice($items, $offset, $per_page);

// Calculate total final weight
$total_final_wt = 0;
foreach ($all_items as $it) {
    $total_final_wt += (float)($it['final_weight'] ?? 0);
}

// Get branches for filter
$branches = getListMaster("SELECT id, name FROM tbl_branches WHERE status = 1 ORDER BY name ASC");
$default_branch = !empty($branches) ? $branches[0]['name'] : 'Main Branch';
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Repair Order Process - <?php echo $Proj_Title; ?></title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
<?php include 'header-script.php';?>
</head>
<style>
body { background: #f4f6fb; /* font-family: 'Segoe UI', Arial, sans-serif; */ }
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
.page-header-actions { display: flex; gap: 10px; align-items: center; }
.btn-process { background: #11294b; color: #fff; border: none; padding: 8px 20px; border-radius: 6px; font-weight: 600; cursor: pointer; }
.btn-process:hover { background: #4a2b7c; color: #fff; }
.toolbar { background: #fff; padding: 12px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
.toolbar-left, .toolbar-right { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.btn-filter { background: #fff; border: 1px solid #e2e8f0; color: #64748b; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; }
.btn-filter:hover { background: #f8fafc; border-color: #cbd5e1; }
.btn-export { background: #11294b; border: none; color: #fff; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; }
.btn-export:hover { background: #4a2b7c; color: #fff; }
.table-container { background: #fff; margin: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: auto; }
.table { width: 100%; margin: 0; font-size: 11px; border-collapse: collapse; }
.table thead th { background: #f1edff !important; font-weight: 600; color: #4d5673; padding: 10px; border: 1px solid #dee2e6; white-space: nowrap; text-align: center; }
.table tbody td { padding: 2px; border: 1px solid #dee2e6; vertical-align: middle; text-align: center; }
.table tbody tr:hover { background: #f8fafc; }
.status-badge { padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-block; }
.status-not-initiate, .status-draft { background: #6c757d; color: #fff; }
.status-processing { background: #11294b; color: #fff; }
.status-completed { background: #28a745; color: #fff; }
.status-rejected { background: #dc3545; color: #fff; }
.status-invoice { background: #17a2b8; color: #fff; }
.btn-action { padding: 6px 12px; font-size: 12px; border-radius: 4px; cursor: pointer; margin: 2px; border: none; font-weight: 600; text-decoration: none; display: inline-block; }
.btn-jobwork { background: #11294b; color: #fff; }
.btn-jobwork:hover { background: #4a2b7c; color: #fff; }
.btn-jobwork:disabled { background: #adb5bd; color: #fff; cursor: not-allowed; opacity: 0.9; }
.btn-catalogue { background: #e83e8c; color: #fff; }
.btn-catalogue:hover { background: #d62d7c; color: #fff; }
.img-thumb { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; background: #f1f5f9; }
.pagination-container { background: #fff; padding: 12px 20px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; margin: 0 20px 20px 20px; border-radius: 0 0 8px 8px; }
.info-icon { color: #64748b; cursor: pointer; font-size: 12px; }
/* Attach Image & Action Icons */
.col-attach-image { min-width: 90px; }
.btn-attach-img { width: 36px; height: 36px; border: 1px dashed #cbd5e1; border-radius: 6px; background: #f8fafc; color: #64748b; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; }
.btn-attach-img:hover { background: #e2e8f0; color: #11294b; border-color: #11294b; }
.btn-icon-action { width: 32px; height: 32px; padding: 0; border: 1px solid #e2e8f0; border-radius: 4px; background: #fff; color: #11294b; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; margin: 1px; font-size: 0.9rem; }
.btn-icon-action:hover { background: #f1edff; border-color: #11294b; }
.th-col-settings { position: relative; }
.btn-col-settings { width: 32px; height: 32px; padding: 0; border: none; background: transparent; color: #64748b; cursor: pointer; border-radius: 4px; }
.btn-col-settings:hover { background: #e2e8f0; color: #11294b; }
#columnSettingsModal .modal-dialog { max-width: 360px; }
#columnSettingsModal .form-check { padding: 6px 0; }
.sop-col-hidden { display: none !important; }
#columnSettingsModal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 1050; overflow-x: hidden; overflow-y: auto; }
#columnSettingsModal.show { display: block !important; }
#columnSettingsModal .modal-dialog { position: relative; margin: 1.75rem auto; max-width: 360px; }
#columnSettingsModal .modal-content { background: #fff; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
.modal-backdrop { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1040; }
</style>
<body>
<?php include 'sidebar.php'; ?>
<div class="layout-content">
<div class="container-fluid flex-grow-1" style="padding-top:0;padding-bottom:0;">
            <!-- Page Header -->
            <div class="page-header-bar">
                <span>Repair Order List</span>
                <div class="page-header-actions">
                    <a href="repair-order.php" class="btn-process">New Repair Order</a>
                </div>
            </div>

            <?php if (!empty($_GET['saved']) && !empty($_GET['jobwork_no'])): ?>
            <div class="alert alert-success alert-dismissible fade show mx-3 mt-2" role="alert">
                Repair Job Work Order <strong><?php echo htmlspecialchars($_GET['jobwork_no']); ?></strong> saved successfully.
                <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <?php endif; ?>

            <input type="file" id="attachImageInput" accept="image/*" style="display: none;">

            <!-- Toolbar -->
            <div class="toolbar">
                <div class="toolbar-left">
                    <button type="button" class="btn-filter"><i class="feather icon-filter"></i> Filter</button>
                    <select class="btn-filter">
                        <option>Export</option>
                    </select>
                    <button type="button" class="btn-filter">+ Import</button>
                    <select class="btn-filter">
                        <option>Action</option>
                    </select>
                </div>
                <div class="toolbar-right">
                    <form method="GET" class="d-flex gap-2 align-items-center">
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>" style="width: 200px;">
                        <input type="date" name="from_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($from_date); ?>">
                        <input type="date" name="to_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($to_date); ?>">
                        <button type="submit" class="btn-export">Search</button>
                    </form>
                </div>
            </div>

            <!-- Table -->
            <div class="table-container">
                <table class="table table-bordered table-hover text-center" id="orderProcessTable">
                    <thead>
                        <tr>
                            <th data-col="check"><input type="checkbox" id="selectAll"></th>
                            <th data-col="product">Product</th>
                            <th data-col="attach_image">Attach Image</th>
                            <th data-col="rfid">RFID Code</th>
                            <th data-col="order_no">Repair Order No.</th>
                            <th data-col="jobwork_order">Jobwork Order No.</th>
                            <th data-col="jobwork_invoice">Jobwork Invoice No</th>
                            <th data-col="customer">Customer Name</th>
                            <th data-col="current_dept">Current Dept.</th>
                            <th data-col="final_wt">Final Wt</th>
                            <th data-col="order_date">Order Date</th>
                            <th data-col="due_date">Due Date</th>
                            <th data-col="tag_no">Tag No</th>
                            <th data-col="status">Status</th>
                            <th data-col="ecom_order_no">Ecommerce Order No</th>
                            <th data-col="source">Source</th>
                            <th data-col="design_no">Design No.</th>
                            <th data-col="current_user">Current User</th>
                            <th data-col="ecom_status">Ecommerce Order Status</th>
                            <th data-col="sale_invoice">Invoice</th>
                            <th data-col="branch">Branch Name</th>
                            <th data-col="info">info</th>
                            <th data-col="action_icons">Actions</th>
                            <th data-col="action" class="th-col-settings">
                                <span>action</span>
                                <button type="button" class="btn-col-settings ml-1" id="btnColumnSettings" title="Show/Hide columns"><i class="feather icon-settings"></i></button>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): 
                            $order_status = strtolower(trim($item['order_status'] ?? 'draft'));
                            $status_class = 'status-not-initiate';
                            $status_label = 'Not Initiate';
                            if ($order_status === 'rejected') { $status_class = 'status-rejected'; $status_label = 'Rejected'; }
                            elseif ($order_status === 'completed') { $status_class = 'status-completed'; $status_label = 'Completed'; }
                            elseif ($order_status === 'processing') { $status_class = 'status-processing'; $status_label = 'Processing'; }
                            elseif (stripos($order_status, 'invoice') !== false) { $status_class = 'status-invoice'; $status_label = 'Invoice Created'; }
                            elseif ($order_status !== '' && $order_status !== 'draft') { $status_label = ucfirst($order_status); }
                            
                            $product_name = $item['product_name_display'] ?? 'N/A';
                            $img_src = !empty($item['product_photo']) ? $item['product_photo'] : '';
                            $tag_no = !empty($item['barcode']) ? $item['barcode'] : '-';
                            $order_date_fmt = !empty($item['order_date']) ? date('d-m-Y', strtotime($item['order_date'])) : '-';
                            $due_date_fmt = !empty($item['due_date']) ? date('d-m-Y', strtotime($item['due_date'])) : '-';
                            $final_wt = (float)($item['final_weight'] ?? 0);
                        ?>
                        <tr>
                            <td data-col="check"><input type="checkbox" class="row-checkbox"></td>
                            <td data-col="product">
                                <div class="d-flex align-items-center gap-2">
                                    <?php if ($img_src): ?>
                                    <img src="<?php echo htmlspecialchars($img_src); ?>" alt="" class="img-thumb">
                                    <?php else: ?>
                                    <div class="img-thumb d-flex align-items-center justify-content-center"><i class="feather icon-image text-muted"></i></div>
                                    <?php endif; ?>
                                    <span><?php echo htmlspecialchars($product_name); ?></span>
                                </div>
                            </td>
                            <td data-col="attach_image" class="col-attach-image">
                                <button type="button" class="btn-attach-img btn-attach-img-row" title="Attach image"><i class="feather icon-paperclip"></i></button>
                                <span class="attach-preview-wrap" style="display: none;"><img src="" alt="" class="img-thumb attach-preview-img" style="margin-left: 4px;"></span>
                            </td>
                            <td data-col="rfid"><?php echo htmlspecialchars($item['rfid_code'] ?: '-'); ?></td>
                            <td data-col="order_no"><a href="repair-order.php?id=<?php echo (int)$item['order_id']; ?>"><?php echo htmlspecialchars($item['order_no']); ?></a></td>
                            <td data-col="jobwork_order">-</td>
                            <td data-col="jobwork_invoice">-</td>
                            <td data-col="customer"><?php echo htmlspecialchars($item['customer_name'] ?? '-'); ?></td>
                            <td data-col="current_dept"><?php echo htmlspecialchars($default_branch); ?></td>
                            <td data-col="final_wt" class="text-right"><?php echo number_format($final_wt, 3); ?></td>
                            <td data-col="order_date"><?php echo $order_date_fmt; ?></td>
                            <td data-col="due_date"><?php echo $due_date_fmt; ?></td>
                            <td data-col="tag_no"><?php echo htmlspecialchars($tag_no); ?></td>
                            <td data-col="status"><span class="status-badge <?php echo $status_class; ?>"><?php echo $status_label; ?></span></td>
                            <td data-col="ecom_order_no">-</td>
                            <td data-col="source">AuraGold</td>
                            <td data-col="design_no"><?php echo htmlspecialchars($item['design_no'] ?? '-'); ?></td>
                            <td data-col="current_user"><?php echo htmlspecialchars($item['sales_person'] ?? '-'); ?></td>
                            <td data-col="ecom_status">NA</td>
                            <td data-col="sale_invoice">-</td>
                            <td data-col="branch"><?php echo htmlspecialchars($default_branch); ?></td>
                            <td data-col="info"><i class="feather icon-info info-icon" title="Info"></i></td>
                            <td data-col="action_icons">
                                <button type="button" class="btn-icon-action" title="Refresh"><i class="feather icon-refresh-cw"></i></button>
                                <button type="button" class="btn-icon-action" title="List"><i class="feather icon-list"></i></button>
                                <button type="button" class="btn-icon-action" title="Document"><i class="feather icon-file-text"></i></button>
                                <button type="button" class="btn-icon-action" title="User"><i class="feather icon-user"></i></button>
                                <button type="button" class="btn-icon-action" title="Download"><i class="feather icon-download"></i></button>
                                <button type="button" class="btn-icon-action" title="Forward"><i class="feather icon-arrow-right"></i></button>
                            </td>
                            <td data-col="action">
                                <a href="repair-order.php?id=<?php echo (int)$item['order_id']; ?>" class="btn-action btn-jobwork">View / Edit</a>
                                <a href="repair-job-work-order.php?repair_order_id=<?php echo (int)$item['order_id']; ?>" class="btn btn-primary btn-sm">Job Work Order</a>
                                <a href="jewelry-catalogue-create.php?order_kind=repair&amp;order_id=<?php echo (int) $item['order_id']; ?>&amp;item_id=<?php echo (int) $item['item_id']; ?>&amp;return=<?php echo rawurlencode('repair-order-process.php'); ?>" class="btn-action btn-catalogue" title="Create jewellery catalogue for this order line">Create Catalogue</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="24" class="text-center py-5 text-muted">No repair order items found</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                    <?php if (!empty($items)): ?>
                    <tfoot>
                        <tr class="table-footer-total">
                            <td colspan="9" class="text-right"><strong>Total:</strong></td>
                            <td data-col="final_wt" class="text-right"><strong><?php echo number_format($total_final_wt, 3); ?></strong></td>
                            <td colspan="14"></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>

            <!-- Pagination -->
            <div class="pagination-container">
                <div class="pagination-info">
                    Showing <?php echo $total_records > 0 ? $offset + 1 : 0; ?> to <?php echo min($offset + $per_page, $total_records); ?> of <?php echo $total_records; ?> entries
                </div>
                <div class="d-flex align-items-center gap-2">
                    <?php $qp = $_GET; unset($qp['per_page']); $qstr = http_build_query($qp); $qpre = $qstr ? $qstr . '&' : ''; ?>
                    <select class="form-control form-control-sm" style="width: auto;" onchange="location.href='?<?php echo $qpre; ?>per_page='+this.value+'&page=1'">
                        <option value="10" <?php echo $per_page == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="25" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25</option>
                        <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>100</option>
                    </select>
                    <nav>
                        <ul class="pagination mb-0">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">&laquo;</a>
                            </li>
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">&raquo;</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
</div>
</div>

<!-- Column Settings Modal (Show/Hide columns) -->
<div class="modal fade" id="columnSettingsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="feather icon-settings mr-2"></i> Show / Hide columns</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Toggle columns to show or hide in the table.</p>
                <div id="columnSettingsList"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="columnSettingsApply">Apply</button>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('selectAll')?.addEventListener('change', function() {
    document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = this.checked);
});

(function() {
    var COLUMN_KEYS = [
        { key: 'check', label: 'Checkbox' },
        { key: 'product', label: 'Product' },
        { key: 'attach_image', label: 'Attach Image' },
        { key: 'rfid', label: 'RFID Code' },
        { key: 'order_no', label: 'Repair Order No.' },
        { key: 'jobwork_order', label: 'Jobwork Order No.' },
        { key: 'jobwork_invoice', label: 'Jobwork Invoice No' },
        { key: 'customer', label: 'Customer Name' },
        { key: 'current_dept', label: 'Current Dept.' },
        { key: 'final_wt', label: 'Final Wt' },
        { key: 'order_date', label: 'Order Date' },
        { key: 'due_date', label: 'Due Date' },
        { key: 'tag_no', label: 'Tag No' },
        { key: 'status', label: 'Status' },
        { key: 'ecom_order_no', label: 'Ecommerce Order No' },
        { key: 'source', label: 'Source' },
        { key: 'design_no', label: 'Design No.' },
        { key: 'current_user', label: 'Current User' },
        { key: 'ecom_status', label: 'Ecommerce Order Status' },
        { key: 'sale_invoice', label: 'Invoice' },
        { key: 'branch', label: 'Branch Name' },
        { key: 'info', label: 'Info' },
        { key: 'action_icons', label: 'Actions (icons)' },
        { key: 'action', label: 'Action (View / Catalogue)' }
    ];
    var STORAGE_KEY = 'repair_order_process_visible_cols';

    function getStoredVisible() {
        try {
            var s = localStorage.getItem(STORAGE_KEY);
            if (s) return JSON.parse(s);
        } catch (e) {}
        return null;
    }

    function setStoredVisible(obj) {
        try { localStorage.setItem(STORAGE_KEY, JSON.stringify(obj)); } catch (e) {}
    }

    function applyColumnVisibility(visibleMap) {
        var table = document.getElementById('orderProcessTable');
        if (!table) return;
        COLUMN_KEYS.forEach(function(c) {
            var show = visibleMap[c.key] !== false;
            var sel = '[data-col="' + c.key + '"]';
            table.querySelectorAll('th' + sel + ', td' + sel).forEach(function(el) {
                el.classList.toggle('sop-col-hidden', !show);
            });
        });
    }

    function showColumnModal() {
        var modal = document.getElementById('columnSettingsModal');
        if (!modal) return;
        modal.classList.add('show');
        modal.style.display = 'block';
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        var backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        backdrop.id = 'columnSettingsBackdrop';
        document.body.appendChild(backdrop);
    }

    function hideColumnModal() {
        var modal = document.getElementById('columnSettingsModal');
        if (modal) {
            modal.classList.remove('show');
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
        }
        document.body.classList.remove('modal-open');
        var backdrop = document.getElementById('columnSettingsBackdrop');
        if (backdrop) backdrop.remove();
    }

    document.getElementById('btnColumnSettings')?.addEventListener('click', function(e) {
        e.preventDefault();
        var stored = getStoredVisible();
        var visible = (stored && typeof stored === 'object' && !Array.isArray(stored)) ? stored : {};
        var list = document.getElementById('columnSettingsList');
        if (!list) return;
        list.innerHTML = '';
        COLUMN_KEYS.forEach(function(c) {
            var isChecked = visible[c.key] !== false;
            var div = document.createElement('div');
            div.className = 'form-check';
            div.innerHTML = '<input type="checkbox" class="form-check-input col-visibility-cb" id="col_' + c.key + '" data-col="' + c.key + '" ' + (isChecked ? 'checked' : '') + '><label class="form-check-label" for="col_' + c.key + '">' + c.label + '</label>';
            list.appendChild(div);
        });
        if (typeof $ !== 'undefined' && $.fn.modal) {
            $('#columnSettingsModal').modal('show');
        } else {
            showColumnModal();
        }
    });

    document.getElementById('columnSettingsApply')?.addEventListener('click', function() {
        var visible = {};
        document.querySelectorAll('#columnSettingsModal .col-visibility-cb').forEach(function(cb) {
            var key = cb.getAttribute('data-col');
            if (key) visible[key] = !!cb.checked;
        });
        var atLeastOne = Object.keys(visible).some(function(k) { return visible[k]; });
        if (!atLeastOne) {
            alert('Please keep at least one column visible.');
            return;
        }
        setStoredVisible(visible);
        applyColumnVisibility(visible);
        if (typeof $ !== 'undefined' && $.fn.modal) {
            $('#columnSettingsModal').modal('hide');
        } else {
            hideColumnModal();
        }
    });

    document.querySelectorAll('#columnSettingsModal .close, #columnSettingsModal [data-dismiss="modal"], #columnSettingsModal .btn-secondary').forEach(function(btn) {
        if (btn) btn.addEventListener('click', hideColumnModal);
    });
    document.getElementById('columnSettingsModal')?.addEventListener('click', function(e) {
        if (e.target === this) hideColumnModal();
    });

    // Apply stored visibility on load only if valid and at least one column visible (never hide all)
    var stored = getStoredVisible();
    if (stored && typeof stored === 'object' && !Array.isArray(stored)) {
        var anyVisible = Object.keys(stored).some(function(k) { return stored[k]; });
        if (anyVisible) applyColumnVisibility(stored);
    }

    // Attach image: click button -> file picker -> show preview in cell
    var currentAttachCell = null;
    var attachInput = document.getElementById('attachImageInput');
    document.querySelectorAll('.btn-attach-img-row').forEach(function(btn) {
        btn.addEventListener('click', function() {
            currentAttachCell = this.closest('td');
            if (attachInput) attachInput.click();
        });
    });
    if (attachInput) {
        attachInput.addEventListener('change', function() {
            var cell = currentAttachCell;
            currentAttachCell = null;
            var file = this.files && this.files[0];
            this.value = '';
            if (!cell || !file || !file.type.match(/^image\//)) return;
            var url = URL.createObjectURL(file);
            var wrap = cell.querySelector('.attach-preview-wrap');
            var img = cell.querySelector('.attach-preview-img');
            if (wrap && img) {
                img.src = url;
                wrap.style.display = 'inline-block';
            }
        });
    }
})();
</script>
</body>
</html>
