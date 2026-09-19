<?php 
session_start();
require_once 'config.php';

// Get filters
$search = isset($_GET['search']) ? esc($_GET['search']) : '';
$customer_type_id = isset($_GET['customer_type_id']) ? (int)$_GET['customer_type_id'] : 0;
$country_id = isset($_GET['country_id']) ? (int)$_GET['country_id'] : 0;
$nationality_id = isset($_GET['nationality_id']) ? (int)$_GET['nationality_id'] : 0;
$has_aml = isset($_GET['has_aml']) ? esc($_GET['has_aml']) : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$offset = ($page - 1) * $per_page;

// Get master data for filters
$customer_types = getList("SELECT * FROM tbl_customer_types WHERE status = 1 ORDER BY name ASC");
$countries = getList("SELECT * FROM tbl_countries WHERE status = 1 ORDER BY name ASC");
$nationalities = getList("SELECT * FROM tbl_nationalities WHERE status = 1 ORDER BY name ASC");

// Get column preferences from session or use defaults
$default_columns = [
    'name' => true,
    'contact' => true,
    'email_id' => true,
    'identity_no' => true,
    'national_id' => true,
    'trade_no' => true,
    'special_day' => true,
    'dob' => true,
    'registration' => true,
    'customer_type' => true,
    'country' => true,
    'nationality' => true,
    'billing_address' => true,
    'state' => true,
    'nominee' => true,
    'info' => true,
    'aml' => true,
    'action' => true
];

$column_preferences = isset($_SESSION['customer_report_columns']) ? $_SESSION['customer_report_columns'] : $default_columns;

// Merge with defaults to ensure all columns exist
$column_preferences = array_merge($default_columns, $column_preferences);

include 'header-script.php';
include 'sidebar.php';
?>

<div class="layout-container">
    <div class="main-content">
        <div class="page-container">
            <!-- Page Header -->
            <div class="page-header-bar">
                <div style="flex: 1; min-width: 0; overflow: hidden;">Customer Report</div>
                <div class="page-header-actions" style="flex-shrink: 0; display: flex; gap: 6px;">
                    <button class="btn-icon" onclick="openFilterModal()" title="Filter">
                        <i class="feather icon-filter"></i>
                        <?php 
                        $filter_count = 0;
                        if (!empty($search)) $filter_count++;
                        if (!empty($customer_type_id)) $filter_count++;
                        if (!empty($country_id)) $filter_count++;
                        if (!empty($nationality_id)) $filter_count++;
                        if (!empty($has_aml)) $filter_count++;
                        if ($filter_count > 0): ?>
                        <span class="badge"><?php echo $filter_count; ?></span>
                        <?php endif; ?>
                    </button>
                    <button class="btn-icon" onclick="location.reload()" title="Refresh">
                        <i class="feather icon-refresh-cw"></i>
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

            <!-- Data Table -->
            <div class="card">
                <div class="card-body" style="padding: 15px;">
                    <div class="table-responsive" style="max-height: calc(100vh - 280px); overflow-x: auto !important; overflow-y: auto !important; width: 100%; position: relative; border: 1px solid #e2e8f0; border-radius: 4px; background: #fff;">
                        <table id="customerReportTable" class="table table-striped table-bordered" style="margin: 0; width: max-content; min-width: 100%;">
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
                        <label>Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="form-control" placeholder="Search by name, contact, email, identity no..." style="padding: 4px 8px; font-size: 11px; height: 28px;">
                    </div>
                </div>
                
                <div class="filter-form-row">
                    <div class="filter-form-group">
                        <label>Customer Type</label>
                        <select name="customer_type_id" class="form-control">
                            <option value="">Select Customer Type</option>
                            <?php foreach ($customer_types as $ct): ?>
                            <option value="<?php echo $ct['id']; ?>" <?php echo $customer_type_id == $ct['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ct['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-form-group">
                        <label>Country</label>
                        <select name="country_id" class="form-control">
                            <option value="">Select Country</option>
                            <?php foreach ($countries as $country): ?>
                            <option value="<?php echo $country['id']; ?>" <?php echo $country_id == $country['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($country['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="filter-form-row">
                    <div class="filter-form-group">
                        <label>Nationality</label>
                        <select name="nationality_id" class="form-control">
                            <option value="">Select Nationality</option>
                            <?php foreach ($nationalities as $nationality): ?>
                            <option value="<?php echo $nationality['id']; ?>" <?php echo $nationality_id == $nationality['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($nationality['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-form-group">
                        <label>AML Status</label>
                        <select name="has_aml" class="form-control">
                            <option value="">All</option>
                            <option value="1" <?php echo $has_aml == '1' ? 'selected' : ''; ?>>Yes</option>
                            <option value="0" <?php echo $has_aml == '0' ? 'selected' : ''; ?>>No</option>
                        </select>
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
}

.page-header-bar > div:first-child {
    font-size: 12px;
    font-weight: 600;
    color: #1e293b;
}

.page-header-actions {
    display: flex;
    gap: 6px;
    align-items: center;
    flex-wrap: nowrap;
    min-width: fit-content;
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

#customerReportTable {
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

.action-buttons {
    display: flex;
    gap: 4px;
}

.action-btn {
    padding: 4px 8px;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    background: #fff;
    color: #ffffff;
    cursor: pointer;
    font-size: 11px;
    transition: all 0.2s;
}

.action-btn:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
}

.action-btn.edit {
    color: #11294b;
}

.action-btn.view {
    color: #059669;
}
</style>

<script>
// Define all columns in order
const columnDefinitions = [
    { key: 'name', label: 'Name' },
    { key: 'contact', label: 'Contact' },
    { key: 'email_id', label: 'Email ID' },
    { key: 'identity_no', label: 'Identity No.' },
    { key: 'national_id', label: 'National ID' },
    { key: 'trade_no', label: 'Trade No.' },
    { key: 'special_day', label: 'Special Day' },
    { key: 'dob', label: 'DOB' },
    { key: 'registration', label: 'Registration' },
    { key: 'customer_type', label: 'Customer Type' },
    { key: 'country', label: 'Country' },
    { key: 'nationality', label: 'Nationality' },
    { key: 'billing_address', label: 'Billing Address' },
    { key: 'state', label: 'State' },
    { key: 'nominee', label: 'Nominee' },
    { key: 'info', label: 'Info' },
    { key: 'aml', label: 'Aml' },
    { key: 'action', label: 'Action' }
];

let currentPage = <?php echo $page; ?>;
let currentPerPage = <?php echo $per_page; ?>;
let currentSortColumn = '';
let currentSortOrder = 'asc';

// Load data on page load
$(document).ready(function() {
    // Render initial table headers based on column preferences
    renderInitialHeaders();
    
    loadCustomerReportData();
});

function renderInitialHeaders() {
    const headerRow = $('#tableHeaderRow');
    const columnPrefs = <?php echo json_encode($column_preferences); ?>;
    
    headerRow.empty();
    
    columnDefinitions.forEach(col => {
        if (columnPrefs[col.key] !== false) { // Default to true if not set
            headerRow.append(`<th class="sortable" data-column="${col.key}">${col.label} <i class="feather icon-chevrons-up-down"></i></th>`);
        }
    });
}

function loadCustomerReportData() {
    const params = new URLSearchParams(window.location.search);
    params.set('page', currentPage);
    params.set('per_page', currentPerPage);
    if (currentSortColumn) {
        params.set('sort', currentSortColumn);
        params.set('order', currentSortOrder);
    }
    
    $('#tableBody').html('<tr><td colspan="100%" style="text-align: center; padding: 40px;"><div style="color: #64748b;">Loading data...</div></td></tr>');
    
    fetch('ajax/get-customer-report.php?' + params.toString())
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
        if (columnPrefs[col.key] !== false) { // Default to true if not set
            headerRow.append(`<th class="sortable" data-column="${col.key}">${col.label} <i class="feather icon-chevrons-up-down"></i></th>`);
        }
    });
    
    // Render table rows
    data.forEach(row => {
        const tr = $('<tr>');
        
        columnDefinitions.forEach(col => {
            if (columnPrefs[col.key] !== false) { // Default to true if not set
                let value = row[col.key] || '';
                
                // Special handling for action column
                if (col.key === 'action') {
                    value = `<div class="action-buttons">
                        <button class="action-btn edit" onclick="editCustomer(${row.id})" title="Edit">
                            <i class="feather icon-edit"></i>
                        </button>
                        <button class="action-btn view" onclick="viewCustomer(${row.id})" title="View">
                            <i class="feather icon-eye"></i>
                        </button>
                    </div>`;
                    tr.append($('<td>').html(value));
                } else {
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
    loadCustomerReportData();
}

function openFilterModal() {
    document.getElementById('filterModal').classList.add('active');
}

function closeFilterModal() {
    document.getElementById('filterModal').classList.remove('active');
}

function clearFilters() {
    window.location.href = 'customer-report.php';
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
    fetch('ajax/save-customer-report-columns.php', {
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
    fetch('ajax/save-customer-report-columns.php', {
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

function editCustomer(id) {
    window.location.href = 'customer.php?id=' + id;
}

function viewCustomer(id) {
    window.location.href = 'customer.php?id=' + id + '&view=1';
}

function exportToExcel() {
    // Get current table data
    const table = document.getElementById('customerReportTable');
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
    link.setAttribute('download', 'customer-report-' + dateStr + '.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

function exportToPDF() {
    alert('PDF export functionality will be implemented soon');
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
</script>
