<?php 
session_start();
require_once 'config.php';

// Get filters
$date_range = isset($_GET['date_range']) ? esc($_GET['date_range']) : '';
$branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;
$ledger_name = isset($_GET['ledger_name']) ? esc($_GET['ledger_name']) : '';
$purchase_person = isset($_GET['purchase_person']) ? esc($_GET['purchase_person']) : '';
$voucher_type = isset($_GET['voucher_type']) ? esc($_GET['voucher_type']) : '';
$metal_type = isset($_GET['metal_type']) ? esc($_GET['metal_type']) : '';
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$carat_id = isset($_GET['carat_id']) ? (int)$_GET['carat_id'] : 0;
$currency = isset($_GET['currency']) ? esc($_GET['currency']) : '';
$above_amount = isset($_GET['above_amount']) ? (float)$_GET['above_amount'] : 0;
$barcode_no = isset($_GET['barcode_no']) ? esc($_GET['barcode_no']) : '';
$invoice_no = isset($_GET['invoice_no']) ? esc($_GET['invoice_no']) : '';
$gross_wt = isset($_GET['gross_wt']) ? esc($_GET['gross_wt']) : '';
$ledger_type = isset($_GET['ledger_type']) ? esc($_GET['ledger_type']) : '';
$comment = isset($_GET['comment']) ? esc($_GET['comment']) : '';

// Parse date range if provided
$from_date = '';
$to_date = '';
if (!empty($date_range)) {
    $dates = explode(' - ', $date_range);
    if (count($dates) == 2) {
        $from_date = trim($dates[0]);
        $to_date = trim($dates[1]);
    }
} else {
    $from_date = isset($_GET['from_date']) ? esc($_GET['from_date']) : '';
    $to_date = isset($_GET['to_date']) ? esc($_GET['to_date']) : '';
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$offset = ($page - 1) * $per_page;

// Get master data for filters
$branches = getListMaster("SELECT * FROM tbl_branches WHERE status = 1 ORDER BY name ASC");
$suppliers = getList("SELECT DISTINCT supplier_name FROM tbl_purchase_invoices WHERE supplier_name != '' ORDER BY supplier_name ASC");
$purchase_persons = getList("SELECT DISTINCT purchase_person FROM tbl_purchase_invoices WHERE purchase_person IS NOT NULL AND purchase_person != '' ORDER BY purchase_person ASC");
$products = getList("SELECT * FROM tbl_products WHERE status = 1 ORDER BY name ASC");
$categories = getList("SELECT * FROM tbl_categories WHERE status = 1 ORDER BY name ASC");
$carats = getList("SELECT * FROM tbl_carat WHERE status = 1 ORDER BY name ASC");
$metals = getList("SELECT * FROM tbl_metal WHERE status = 1 ORDER BY display_name ASC");
$locations = getList("SELECT * FROM tbl_location WHERE status = 1 ORDER BY name ASC");

// Get column preferences from session or use defaults
$default_columns = [
    'invoice_no' => true,
    'branch' => true,
    'date' => true,
    'barcode' => true,
    'product' => true,
    'location' => true,
    'gross_wt' => true,
    'final_wt' => true,
    'pcs' => true,
    'stone_wt' => true,
    'metal_amt' => true,
    'making_amt' => true,
    'stone_amt' => true,
    'purchase_amt' => true,
    'ledger_name' => true,
    'grand_total' => true,
    'discount' => true,
    'cash' => true,
    'bank' => true,
    'cheque' => true,
    'upi' => true,
    'round_off_value' => true,
    'card' => true,
    'metal_exch_amt' => true,
    'metal_exch_wt' => true,
    'old_jew_amt' => true,
    'old_jew_wt' => true,
    'balance_amt' => true,
    'comment' => true,
    'currency' => true,
    'category' => true
];

$column_preferences = isset($_SESSION['purchase_analysis_columns']) ? $_SESSION['purchase_analysis_columns'] : $default_columns;

// Merge with defaults to ensure all columns exist
$column_preferences = array_merge($default_columns, $column_preferences);

include 'header-script.php';
include 'sidebar.php';
?>

<div class="layout-container">
    <div class="main-content">
        <div class="page-container">
            <!-- Page Header -->
            <?php if (!$is_print_mode): ?>
            <div class="page-header-bar">
                <div style="flex: 1; min-width: 0; overflow: hidden;">Purchase Analysis</div>
                <div class="page-header-actions" style="flex-shrink: 0; display: flex; gap: 6px;">
                    <button class="btn-icon" onclick="openFilterModal()" title="Filter">
                        <i class="feather icon-filter"></i>
                        <?php 
                        $filter_count = 0;
                        if (!empty($date_range) || !empty($from_date) || !empty($to_date)) $filter_count++;
                        if (!empty($branch_id)) $filter_count++;
                        if (!empty($ledger_name)) $filter_count++;
                        if (!empty($purchase_person)) $filter_count++;
                        if (!empty($voucher_type)) $filter_count++;
                        if (!empty($metal_type)) $filter_count++;
                        if (!empty($product_id)) $filter_count++;
                        if (!empty($category_id)) $filter_count++;
                        if (!empty($carat_id)) $filter_count++;
                        if (!empty($currency)) $filter_count++;
                        if (!empty($above_amount)) $filter_count++;
                        if (!empty($barcode_no)) $filter_count++;
                        if (!empty($invoice_no)) $filter_count++;
                        if (!empty($gross_wt)) $filter_count++;
                        if (!empty($ledger_type)) $filter_count++;
                        if (!empty($comment)) $filter_count++;
                        if ($filter_count > 0): ?>
                        <span class="badge"><?php echo $filter_count; ?></span>
                        <?php endif; ?>
                    </button>
                    <button class="btn-icon" onclick="location.reload()" title="Refresh">
                        <i class="feather icon-refresh-cw"></i>
                    </button>
                    <button class="btn-icon" onclick="printPurchaseAnalysis()" title="Print">
                        <i class="feather icon-printer"></i>
                    </button>
                    <div class="dropdown">
                        <button class="btn-icon" onclick="event.stopPropagation(); this.nextElementSibling.classList.toggle('show')" title="Export">
                            <i class="feather icon-download"></i>
                        </button>
                        <div class="dropdown-menu" style="display: none;">
                            <a class="dropdown-item" href="#" onclick="exportToExcel()">Export to Excel</a>
                            <a class="dropdown-item" href="#" onclick="exportToPDF()">Export to PDF</a>
                        </div>
                    </div>
                    <button class="btn-icon" onclick="openColumnsModal()" title="Columns">
                        <i class="feather icon-settings"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <!-- Data Table -->
            <div class="card">
                <div class="card-body" style="padding: 15px;">
                    <div class="table-responsive" style="max-height: calc(100vh - 280px); overflow-x: auto !important; overflow-y: auto !important; width: 100%; position: relative; border: 1px solid #e2e8f0; border-radius: 4px; background: #fff;">
                        <table id="purchaseAnalysisTable" class="table table-striped table-bordered" style="margin: 0; width: max-content; min-width: 100%;">
                            <thead id="tableHead" style="position: sticky; top: 0; background: #fff; z-index: 10;">
                                <tr id="tableHeaderRow">
                                    <!-- Headers will be dynamically rendered by JavaScript -->
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                <tr>
                                    <td colspan="100%" style="text-align: center; padding: 40px;">
                                        <div style="color: #64748b;">Loading data...</div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="pagination-container" style="padding: 8px 12px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <span id="paginationInfo" style="font-size: 11px; color: #64748b;">Showing 0 to 0 of 0 entries</span>
                        </div>
                        <div class="pagination" id="pagination">
                            <!-- Pagination will be generated by JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
                <div class="filter-form-row">
                    <div class="filter-form-group full-width">
                        <label>Date Range</label>
                        <div class="date-range-input">
                            <input type="text" name="date_range" id="dateRange" value="<?php echo htmlspecialchars($date_range); ?>" class="form-control" placeholder="Select date range" style="padding: 4px 8px; font-size: 11px; height: 28px;">
                            <i class="feather icon-calendar"></i>
                            <i class="feather icon-refresh-cw" onclick="document.getElementById('dateRange').value='';" style="cursor: pointer;"></i>
                        </div>
                    </div>
                </div>
                
                <div class="filter-form-row">
                    <div class="filter-form-group">
                        <label>Branch</label>
                        <select name="branch_id" class="form-control">
                            <option value="">Select Branch</option>
                            <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo $branch['id']; ?>" <?php echo $branch_id == $branch['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($branch['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-form-group">
                        <label>Ledger Name</label>
                        <select name="ledger_name" class="form-control">
                            <option value="">Select Ledger Name</option>
                            <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?php echo htmlspecialchars($supplier['supplier_name']); ?>" <?php echo $ledger_name == $supplier['supplier_name'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="filter-form-row">
                    <div class="filter-form-group">
                        <label>Purchase Person</label>
                        <select name="purchase_person" class="form-control">
                            <option value="">Select Purchase Person</option>
                            <?php foreach ($purchase_persons as $pp): ?>
                            <option value="<?php echo htmlspecialchars($pp['purchase_person']); ?>" <?php echo $purchase_person == $pp['purchase_person'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($pp['purchase_person']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-form-group">
                        <label>Voucher Type</label>
                        <input type="text" name="voucher_type" value="<?php echo htmlspecialchars($voucher_type); ?>" class="form-control" placeholder="Enter voucher type">
                    </div>
                </div>
                
                <div class="filter-form-row">
                    <div class="filter-form-group">
                        <label>Metal Type</label>
                        <select name="metal_type" class="form-control">
                            <option value="">Select Metal Type</option>
                            <?php foreach ($metals as $metal): ?>
                            <option value="<?php echo $metal['id']; ?>" <?php echo $metal_type == $metal['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($metal['display_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-form-group">
                        <label>Product</label>
                        <select name="product_id" class="form-control">
                            <option value="">Select Product</option>
                            <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['id']; ?>" <?php echo $product_id == $product['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($product['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="filter-form-row">
                    <div class="filter-form-group">
                        <label>Category</label>
                        <select name="category_id" class="form-control">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo $category_id == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-form-group">
                        <label>Carat</label>
                        <select name="carat_id" class="form-control">
                            <option value="">Select Carat</option>
                            <?php foreach ($carats as $carat): ?>
                            <option value="<?php echo $carat['id']; ?>" <?php echo $carat_id == $carat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($carat['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="filter-form-row">
                    <div class="filter-form-group">
                        <label>Currency</label>
                        <select name="currency" class="form-control">
                            <option value="">Select Currency</option>
                            <option value="AED" <?php echo $currency == 'AED' ? 'selected' : ''; ?>>AED</option>
                            <option value="USD" <?php echo $currency == 'USD' ? 'selected' : ''; ?>>USD</option>
                            <option value="EUR" <?php echo $currency == 'EUR' ? 'selected' : ''; ?>>EUR</option>
                        </select>
                    </div>
                    <div class="filter-form-group">
                        <label>Above Amount</label>
                        <input type="number" name="above_amount" value="<?php echo $above_amount; ?>" class="form-control" placeholder="Enter amount" step="0.01">
                    </div>
                </div>
                
                <div class="filter-form-row">
                    <div class="filter-form-group">
                        <label>Barcode No.</label>
                        <input type="text" name="barcode_no" value="<?php echo htmlspecialchars($barcode_no); ?>" class="form-control" placeholder="Enter barcode">
                    </div>
                    <div class="filter-form-group">
                        <label>Invoice No.</label>
                        <input type="text" name="invoice_no" value="<?php echo htmlspecialchars($invoice_no); ?>" class="form-control" placeholder="Enter invoice number">
                    </div>
                </div>
                
                <div class="filter-form-row">
                    <div class="filter-form-group">
                        <label>Gross Wt</label>
                        <input type="text" name="gross_wt" value="<?php echo htmlspecialchars($gross_wt); ?>" class="form-control" placeholder="Enter gross weight">
                    </div>
                    <div class="filter-form-group">
                        <label>Ledger Type</label>
                        <input type="text" name="ledger_type" value="<?php echo htmlspecialchars($ledger_type); ?>" class="form-control" placeholder="Enter ledger type">
                    </div>
                </div>
                
                <div class="filter-form-row">
                    <div class="filter-form-group full-width">
                        <label>Comment</label>
                        <input type="text" name="comment" value="<?php echo htmlspecialchars($comment); ?>" class="form-control" placeholder="Enter comment">
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

<!-- Columns Modal -->
<div id="columnsModal" class="filter-modal">
    <div class="filter-modal-content" style="max-width: 500px;">
        <div class="filter-modal-header">
            <h5><i class="feather icon-settings"></i> Columns</h5>
            <div>
                <button class="filter-modal-close" onclick="refreshColumns()" title="Refresh" style="margin-right: 10px; background: none; border: none; color: #fff; font-size: 18px; cursor: pointer;">
                    <i class="feather icon-refresh-cw"></i>
                </button>
                <button class="filter-modal-close" onclick="closeColumnsModal()">&times;</button>
            </div>
        </div>
        <div class="filter-modal-body">
            <div style="margin-bottom: 10px;">
                <input type="text" id="columnSearch" class="form-control" placeholder="Search" onkeyup="filterColumns()" style="padding: 4px 8px; font-size: 11px; height: 28px;">
            </div>
            <div id="columnsList" style="max-height: 400px; overflow-y: auto;">
                <!-- Columns will be populated by JavaScript -->
            </div>
        </div>
    </div>
</div>

<?php include 'footer-script.php'; ?>

<style>
/* Layout Container */
.layout-container {
    padding: 20px;
    width: 100%;
    box-sizing: border-box;
    background: #f4f6fb;
    min-height: calc(100vh - 60px);
}

.main-content {
    width: 100%;
    max-width: 100%;
}

.page-container {
    width: 100%;
    max-width: 100%;
    padding: 0;
    background: #f4f6fb;
}

.page-header-bar {
    background: #fff;
    padding: 8px 15px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    border-radius: 6px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    overflow: visible;
}

.page-header-bar > div:first-child {
    font-size: 12px;
    font-weight: 600;
    color: #1e293b;
    flex: 1;
    min-width: 0;
    overflow: hidden;
}

.page-header-actions {
    display: flex !important;
    gap: 6px;
    align-items: center;
    flex-wrap: nowrap;
    min-width: fit-content;
    flex-shrink: 0;
    overflow: visible;
}

.btn-icon {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    padding: 5px 10px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    position: relative;
    transition: all 0.2s;
    font-size: 12px;
    min-width: 32px;
    height: 28px;
    flex-shrink: 0;
}

.btn-icon i {
    font-size: 12px;
    display: block;
}

.btn-icon:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.btn-icon .badge {
    position: absolute;
    top: -6px;
    right: -6px;
    background: #ef4444;
    color: #fff;
    border-radius: 10px;
    padding: 2px 6px;
    font-size: 10px;
    font-weight: 600;
}

.dropdown {
    position: relative;
}

.dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    margin-top: 5px;
    min-width: 150px;
    z-index: 100;
}

.dropdown-menu.show {
    display: block;
}

.dropdown-item {
    display: block;
    padding: 10px 15px;
    color: #ffffff;
    text-decoration: none;
    transition: background 0.2s;
}

.dropdown-item:hover {
    background: #f8fafc;
    color: #1e293b;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table-responsive {
    overflow-x: auto !important;
    overflow-y: auto !important;
    -webkit-overflow-scrolling: touch;
    width: 100%;
    position: relative;
}

#purchaseAnalysisTable {
    width: max-content;
    min-width: 100%;
    table-layout: auto;
}

.table th {
    background: #f8fafc;
    padding: 6px 8px;
    text-align: left;
    font-weight: 600;
    color: #ffffff;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
    font-size: 11px;
    line-height: 1.4;
    min-width: 80px;
}

.table td {
    padding: 6px 8px;
    border-bottom: 1px solid #e2e8f0;
    color: #64748b;
    white-space: nowrap;
    font-size: 11px;
    line-height: 1.4;
}

.table tbody tr:hover {
    background: #f8fafc;
}

.card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 10px;
    overflow: hidden;
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #e2e8f0;
}

.sortable {
    cursor: pointer;
    user-select: none;
}

.sortable:hover {
    background: #f1f5f9;
}

.pagination {
    display: flex;
    gap: 4px;
    align-items: center;
}

.pagination .page-link {
    padding: 4px 8px;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    color: #ffffff;
    text-decoration: none;
    transition: all 0.2s;
    font-size: 11px;
}

.pagination .page-link:hover:not(.disabled) {
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
    border-radius: 6px;
    padding: 0;
    width: 90%;
    max-width: 600px;
    max-height: 85vh;
    overflow: auto;
}

.filter-modal-header {
    background: #11294b;
    color: #fff;
    padding: 8px 12px;
    border-radius: 6px 6px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.filter-modal-header h5 {
    margin: 0;
    color: #fff;
    font-weight: 600;
    font-size: 11px;
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
    padding: 12px;
}

.filter-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 10px;
}

.filter-form-group {
    display: flex;
    flex-direction: column;
}

.filter-form-group.full-width {
    grid-column: 1 / -1;
}

.filter-form-group label {
    margin-bottom: 4px;
    font-weight: 500;
    color: #ffffff;
    font-size: 11px;
}

.filter-form-group input,
.filter-form-group select {
    padding: 4px 8px;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    font-size: 11px;
    width: 100%;
    height: 28px;
}

.date-range-input {
    position: relative;
}

.date-range-input input {
    padding: 4px 8px;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    font-size: 11px;
    height: 28px;
}

.date-range-input i {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    pointer-events: none;
}

.date-range-input i:last-child {
    right: 35px;
    pointer-events: all;
}

.filter-modal-footer {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #e2e8f0;
}

.btn-apply {
    background: #11294b;
    color: #fff;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
    transition: background 0.2s;
    font-size: 11px;
}

.btn-apply:hover {
    background: #4a2d6c;
}

.btn-clear {
    background: #ef4444;
    color: #fff;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
    transition: background 0.2s;
    font-size: 11px;
}

.btn-clear:hover {
    background: #dc2626;
}

#columnsList .column-item {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 4px 8px;
    border-bottom: 1px solid #f1f5f9;
}

#columnsList .column-item:hover {
    background: #f8fafc;
}

#columnsList .column-item input[type="checkbox"] {
    cursor: pointer;
    width: 14px;
    height: 14px;
}

#columnsList .column-item label {
    cursor: pointer;
    margin: 0;
    flex: 1;
    font-size: 11px;
    color: #ffffff;
}
    /* Print Styles */
    @media print {
        body {
            margin: 0;
            padding: 10px;
            background: #fff;
        }
        
        .layout-container {
            padding: 0 !important;
        }
        
        .page-header-bar,
        .page-header-actions,
        .btn-icon,
        .dropdown,
        .filter-modal,
        .columns-modal,
        .pagination-container {
            display: none !important;
        }
        
        .card {
            border: none !important;
            box-shadow: none !important;
            margin: 0 !important;
        }
        
        .card-body {
            padding: 0 !important;
        }
        
        .table-responsive {
            max-height: none !important;
            overflow: visible !important;
            border: 1px solid #000 !important;
        }
        
        table {
            width: 100% !important;
            font-size: 10px !important;
        }
        
        th, td {
            padding: 4px !important;
            border: 1px solid #000 !important;
        }
        
        th[data-column="print"],
        td[data-column="print"] {
            display: none !important;
        }
        
        thead {
            display: table-header-group !important;
        }
        
        tbody {
            display: table-row-group !important;
        }
    }
</style>

<script>
// Define all columns in order (shared between renderTable and renderColumnsList)
const columnDefinitions = [
    { key: 'invoice_no', label: 'Invoice No.' },
    { key: 'print', label: 'Print', isAction: true },
    { key: 'branch', label: 'Branch' },
    { key: 'date', label: 'Date' },
    { key: 'barcode', label: 'Barcode' },
    { key: 'product', label: 'Product' },
    { key: 'location', label: 'Location' },
    { key: 'gross_wt', label: 'Gross Wt' },
    { key: 'final_wt', label: 'Final Wt' },
    { key: 'pcs', label: 'Pcs' },
    { key: 'stone_wt', label: 'Stone Wt' },
    { key: 'metal_amt', label: 'Metal Amt.' },
    { key: 'making_amt', label: 'Making Amt.' },
    { key: 'stone_amt', label: 'Stone Amt.' },
    { key: 'purchase_amt', label: 'Purchase Amt.' },
    { key: 'ledger_name', label: 'Ledger Name' },
    { key: 'grand_total', label: 'Grand Total' },
    { key: 'discount', label: 'Discount' },
    { key: 'cash', label: 'Cash' },
    { key: 'bank', label: 'Bank' },
    { key: 'cheque', label: 'Cheque' },
    { key: 'upi', label: 'Upi' },
    { key: 'round_off_value', label: 'Round OFF Value' },
    { key: 'card', label: 'Card' },
    { key: 'metal_exch_amt', label: 'Metal Exch. Amt' },
    { key: 'metal_exch_wt', label: 'Metal Exch. Wt' },
    { key: 'old_jew_amt', label: 'Old Jew. Amt' },
    { key: 'old_jew_wt', label: 'Old Jew. Wt' },
    { key: 'balance_amt', label: 'Balance Amt.' },
    { key: 'comment', label: 'Comment' },
    { key: 'currency', label: 'Currency' },
    { key: 'category', label: 'Category' }
];

let currentPage = <?php echo $page; ?>;
let currentPerPage = <?php echo $per_page; ?>;
let currentSortColumn = '';
let currentSortOrder = 'asc';

// Load data on page load
$(document).ready(function() {
    // Render initial table headers based on column preferences
    renderInitialHeaders();
    
    loadPurchaseAnalysisData();
    
    // Initialize date range picker if available
    if (typeof $.fn.daterangepicker !== 'undefined') {
        $('#dateRange').daterangepicker({
            autoUpdateInput: false,
            locale: {
                format: 'DD-MM-YYYY',
                separator: ' - '
            }
        });
        
        $('#dateRange').on('apply.daterangepicker', function(ev, picker) {
            $(this).val(picker.startDate.format('DD-MM-YYYY') + ' - ' + picker.endDate.format('DD-MM-YYYY'));
        });
    }
});

function renderInitialHeaders() {
    const headerRow = $('#tableHeaderRow');
    const columnPrefs = <?php echo json_encode($column_preferences); ?>;
    
    headerRow.empty();
    
    columnDefinitions.forEach(col => {
        if (col.isAction || columnPrefs[col.key] !== false) { // Always show action columns, default to true if not set
            if (col.isAction) {
                headerRow.append(`<th style="width: 60px; text-align: center;" data-column="${col.key}">${col.label}</th>`);
            } else {
                headerRow.append(`<th class="sortable" data-column="${col.key}">${col.label} <i class="feather icon-chevrons-up-down"></i></th>`);
            }
        }
    });
}

function loadPurchaseAnalysisData() {
    const params = new URLSearchParams(window.location.search);
    params.set('page', currentPage);
    params.set('per_page', currentPerPage);
    if (currentSortColumn) {
        params.set('sort', currentSortColumn);
        params.set('order', currentSortOrder);
    }
    
    $('#tableBody').html('<tr><td colspan="100%" style="text-align: center; padding: 40px;"><div style="color: #64748b;">Loading data...</div></td></tr>');
    
    fetch('ajax/get-purchase-analysis.php?' + params.toString())
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                renderTable(data.data);
                renderPagination(data.pagination);
            } else {
                $('#tableBody').html('<tr><td colspan="100%" style="text-align: center; padding: 40px;"><div style="color: #ef4444;">Error: ' + (data.message || 'Failed to load data') + '</div></td></tr>');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            $('#tableBody').html('<tr><td colspan="100%" style="text-align: center; padding: 40px;"><div style="color: #ef4444;">Error loading data</div></td></tr>');
        });
}

function renderTable(data) {
    const tbody = $('#tableBody');
    const thead = $('#tableHead');
    tbody.empty();
    
    if (!data || data.length === 0) {
        tbody.html('<tr><td colspan="100%" style="text-align: center; padding: 40px;"><div style="color: #64748b;">No Rows To Show</div></td></tr>');
        return;
    }
    
    // Get current column preferences
    const columnPrefs = <?php echo json_encode($column_preferences); ?>;
    
    // Render table headers dynamically
    const headerRow = thead.find('tr');
    headerRow.empty();
    
    columnDefinitions.forEach(col => {
        if (col.isAction || columnPrefs[col.key] !== false) { // Always show action columns, default to true if not set
            if (col.isAction) {
                headerRow.append(`<th style="width: 60px; text-align: center;" data-column="${col.key}">${col.label}</th>`);
            } else {
                headerRow.append(`<th class="sortable" data-column="${col.key}">${col.label} <i class="feather icon-chevrons-up-down"></i></th>`);
            }
        }
    });
    
    // Render table rows
    data.forEach(row => {
        const tr = $('<tr>');
        
        columnDefinitions.forEach(col => {
            if (col.isAction || columnPrefs[col.key] !== false) { // Always show action columns, default to true if not set
                if (col.isAction && col.key === 'print') {
                    // Print button column
                    const invoiceId = row.invoice_id || '';
                    const printBtn = $('<button>')
                        .addClass('btn-icon')
                        .attr('title', 'Print Invoice')
                        .css({
                            'background': 'transparent',
                            'color': '#c5a864',
                            'border': 'none',
                            'padding': '4px 8px',
                            'border-radius': '4px',
                            'cursor': 'pointer',
                            'font-size': '12px'
                        })
                        .html('<i class="feather icon-printer"></i>')
                        .on('click', function(e) {
                            e.stopPropagation();
                            if (invoiceId) {
                                window.open('purchase-invoice-print.php?id=' + invoiceId, '_blank', 'width=1200,height=800');
                            } else {
                                alert('Invoice ID not found');
                            }
                        });
                    tr.append($('<td>').attr('data-column', 'print').css('text-align', 'center').append(printBtn));
                } else if (!col.isAction) {
                    let value = row[col.key] || '';
                    
                    // Format numeric values
                    if (['pcs', 'gross_wt', 'final_wt', 'metal_exch_wt', 'old_jew_wt', 'stone_wt'].includes(col.key)) {
                        value = value || '0.000';
                    } else if (['metal_amt', 'making_amt', 'stone_amt', 'purchase_amt', 'grand_total', 'discount', 'cash', 'bank', 'cheque', 'upi', 'card', 'metal_exch_amt', 'old_jew_amt', 'balance_amt', 'round_off_value'].includes(col.key)) {
                        value = value || '0.00';
                    }
                    
                    tr.append($('<td>').text(value));
                }
            }
        });
        
        tbody.append(tr);
    });
}

function renderPagination(pagination) {
    const paginationEl = $('#pagination');
    const infoEl = $('#paginationInfo');
    
    if (!pagination) {
        paginationEl.empty();
        infoEl.text('Showing 0 to 0 of 0 entries');
        return;
    }
    
    const { current_page, per_page, total, total_pages } = pagination;
    const start = total > 0 ? ((current_page - 1) * per_page) + 1 : 0;
    const end = Math.min(current_page * per_page, total);
    
    infoEl.text(`Showing ${start} to ${end} of ${total} entries`);
    
    paginationEl.empty();
    
    if (total_pages <= 1) return;
    
    // First page
    if (current_page > 1) {
        paginationEl.append(`<a href="#" class="page-link" onclick="goToPage(1); return false;">&lt;&lt;</a>`);
        paginationEl.append(`<a href="#" class="page-link" onclick="goToPage(${current_page - 1}); return false;">&lt;</a>`);
    }
    
    // Page numbers
    const startPage = Math.max(1, current_page - 2);
    const endPage = Math.min(total_pages, current_page + 2);
    
    for (let i = startPage; i <= endPage; i++) {
        const activeClass = i === current_page ? 'active' : '';
        paginationEl.append(`<a href="#" class="page-link ${activeClass}" onclick="goToPage(${i}); return false;">${i}</a>`);
    }
    
    // Last page
    if (current_page < total_pages) {
        paginationEl.append(`<a href="#" class="page-link" onclick="goToPage(${current_page + 1}); return false;">&gt;</a>`);
        paginationEl.append(`<a href="#" class="page-link" onclick="goToPage(${total_pages}); return false;">&gt;&gt;</a>`);
    }
}

function goToPage(page) {
    currentPage = page;
    loadPurchaseAnalysisData();
}

function openFilterModal() {
    document.getElementById('filterModal').classList.add('active');
}

function closeFilterModal() {
    document.getElementById('filterModal').classList.remove('active');
}

function clearFilters() {
    window.location.href = 'purchase-analysis.php';
}

function openColumnsModal() {
    renderColumnsList();
    document.getElementById('columnsModal').classList.add('active');
}

function closeColumnsModal() {
    document.getElementById('columnsModal').classList.remove('active');
}

function refreshColumns() {
    // Reset to defaults
    const defaultColumns = <?php echo json_encode($column_preferences); ?>;
    Object.keys(defaultColumns).forEach(key => {
        defaultColumns[key] = true;
    });
    saveColumnPreferences(defaultColumns);
    location.reload();
}

function renderColumnsList() {
    const columnsList = document.getElementById('columnsList');
    const columnPrefs = <?php echo json_encode($column_preferences); ?>;
    
    columnsList.innerHTML = '';
    
    columnDefinitions.forEach(col => {
        // Skip action columns (print button) from column visibility settings
        if (col.isAction) {
            return;
        }
        const item = document.createElement('div');
        item.className = 'column-item';
        const isChecked = columnPrefs[col.key] !== false; // Default to true if not set
        item.innerHTML = `
            <input type="checkbox" id="col_${col.key}" ${isChecked ? 'checked' : ''} onchange="toggleColumn('${col.key}', this.checked)">
            <label for="col_${col.key}">${col.label}</label>
        `;
        columnsList.appendChild(item);
    });
}

function filterColumns() {
    const search = document.getElementById('columnSearch').value.toLowerCase();
    const items = document.querySelectorAll('#columnsList .column-item');
    
    items.forEach(item => {
        const label = item.querySelector('label').textContent.toLowerCase();
        item.style.display = label.includes(search) ? 'flex' : 'none';
    });
}

function toggleColumn(key, visible) {
    // Get current preferences from the rendered table
    const columnPrefs = <?php echo json_encode($column_preferences); ?>;
    columnPrefs[key] = visible;
    saveColumnPreferences(columnPrefs);
    
    // Update session and reload
    fetch('ajax/save-purchase-analysis-columns.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            columns: columnPrefs
        })
    }).then(() => {
        location.reload();
    }).catch(err => {
        console.error('Error saving column preferences:', err);
        location.reload();
    });
}

function saveColumnPreferences(prefs) {
    // Save to session via AJAX
    fetch('ajax/save-purchase-analysis-columns.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            columns: prefs
        })
    }).catch(err => {
        console.error('Error saving column preferences:', err);
    });
}

function exportToExcel() {
    // Get current table data
    const table = document.getElementById('purchaseAnalysisTable');
    if (!table) {
        alert('No data to export');
        return;
    }
    
    // Get all visible rows
    const rows = table.querySelectorAll('tbody tr');
    if (rows.length === 0 || (rows.length === 1 && (rows[0].textContent.includes('No Rows To Show') || rows[0].textContent.includes('Loading')))) {
        alert('No data to export');
        return;
    }
    
    // Get visible column headers
    const headers = [];
    const headerRow = table.querySelectorAll('thead th');
    headerRow.forEach(th => {
        if (th.offsetParent !== null) { // Check if column is visible
            const headerText = th.textContent.trim().replace(/\s+/g, ' ').replace(/[^\w\s-.,]/g, '');
            headers.push(headerText);
        }
    });
    
    if (headers.length === 0) {
        alert('No columns to export');
        return;
    }
    
    // Build CSV content
    let csvContent = headers.join(',') + '\n';
    
    rows.forEach(row => {
        // Skip loading or empty rows
        const rowText = row.textContent.trim();
        if (rowText.includes('Loading') || rowText.includes('No Rows To Show') || rowText === '') {
            return;
        }
        
        const cells = row.querySelectorAll('td');
        const rowData = [];
        let cellIndex = 0;
        
        headerRow.forEach((th, index) => {
            if (th.offsetParent !== null) { // Only include visible columns
                const cell = cells[cellIndex];
                let cellText = cell ? cell.textContent.trim() : '';
                // Escape commas and quotes in CSV
                cellText = cellText.replace(/"/g, '""');
                if (cellText.includes(',') || cellText.includes('"') || cellText.includes('\n')) {
                    cellText = '"' + cellText + '"';
                }
                rowData.push(cellText);
                cellIndex++;
            }
        });
        
        if (rowData.length > 0) {
            csvContent += rowData.join(',') + '\n';
        }
    });
    
    // Create blob and download
    const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    const dateStr = new Date().toISOString().split('T')[0];
    link.setAttribute('download', 'purchase-analysis-' + dateStr + '.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

function exportToPDF() {
    alert('PDF export functionality will be implemented soon');
}

// Print purchase analysis
function printPurchaseAnalysis() {
    // Get current filters to pass to print page
    const urlParams = new URLSearchParams(window.location.search);
    const printUrl = 'purchase-analysis.php?' + urlParams.toString() + '&print=1';
    
    // Open print page in new window
    const printWindow = window.open(printUrl, '_blank', 'width=1200,height=800');
    
    // Wait for window to load, then trigger print
    if (printWindow) {
        printWindow.onload = function() {
            setTimeout(function() {
                printWindow.print();
            }, 500);
        };
    }
}

// Close modals when clicking outside
document.getElementById('filterModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeFilterModal();
    }
});

document.getElementById('columnsModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeColumnsModal();
    }
});

// Auto-print if in print mode
<?php if ($is_print_mode): ?>
window.addEventListener('load', function() {
    setTimeout(function() {
        window.print();
    }, 500);
});
<?php endif; ?>
</script>
