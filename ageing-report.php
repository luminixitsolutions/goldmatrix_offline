<?php
session_start();
require_once 'config.php';

$aging_date = isset($_GET['aging_date']) ? esc($_GET['aging_date']) : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $aging_date)) {
    $aging_date = date('Y-m-d');
}

$ageing_title = function_exists('auragold_t') ? auragold_t('rep.ageing') : 'Ageing Report';

$AURAGOLD_REPORT_PAGE = true;
include 'header-script.php';
include 'sidebar.php';
?>

<div class="layout-container ageing-report-page">
    <div class="main-content">
        <div class="page-container">
            <h1 class="sr-only"><?php echo htmlspecialchars($ageing_title, ENT_QUOTES, 'UTF-8'); ?></h1>

            <div class="ageing-shell">
            <div class="ageing-shell-top">
            <div class="ageing-tabs-row">
            <div class="ageing-tabs" role="tablist">
                <button type="button" class="ageing-tab active" data-tab="ledger" role="tab" aria-selected="true"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.tab_ledger'), ENT_QUOTES, 'UTF-8') : 'Ledger'; ?></button>
                <button type="button" class="ageing-tab" data-tab="stock" role="tab" aria-selected="false"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.tab_stock'), ENT_QUOTES, 'UTF-8') : 'Stock'; ?></button>
            </div>
            <div class="toolbar-actions ageing-tabs-row__actions">
                <button type="button" class="btn-icon-tight" id="btnToolbarRefresh" title="<?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.refresh'), ENT_QUOTES, 'UTF-8') : 'Refresh'; ?>">
                    <i class="feather icon-refresh-cw"></i>
                </button>
                <button type="button" class="btn-icon-tight ageing-toolbar-gear ledger-only" id="ledgerColGearBtn" title="<?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.column_settings'), ENT_QUOTES, 'UTF-8') : 'Column settings'; ?>" aria-label="<?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.column_settings'), ENT_QUOTES, 'UTF-8') : 'Column settings'; ?>">
                    <i class="feather icon-settings"></i>
                </button>
                <button type="button" class="btn-icon-tight ageing-toolbar-gear stock-only" id="stockColGearBtn" hidden title="<?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.column_settings'), ENT_QUOTES, 'UTF-8') : 'Column settings'; ?>" aria-label="<?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.column_settings'), ENT_QUOTES, 'UTF-8') : 'Column settings'; ?>">
                    <i class="feather icon-settings"></i>
                </button>
                <div class="ageing-export-dd">
                    <button type="button" class="btn-ageing-primary" id="btnExportToggle">
                        <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.export'), ENT_QUOTES, 'UTF-8') : 'Export'; ?>
                        <i class="feather icon-chevron-down"></i>
                    </button>
                    <div class="ageing-export-menu" id="exportMenu" role="menu" hidden>
                        <button type="button" class="ageing-export-item" id="exportExcel" role="menuitem">
                            <span class="ageing-export-ico ageing-export-ico--excel" aria-hidden="true"><i class="fas fa-file-excel"></i></span>
                            <span class="ageing-export-txt"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.export_menu_excel'), ENT_QUOTES, 'UTF-8') : 'Excel'; ?></span>
                        </button>
                        <button type="button" class="ageing-export-item" id="exportPdf" role="menuitem">
                            <span class="ageing-export-ico ageing-export-ico--pdf" aria-hidden="true"><i class="fas fa-file-pdf"></i></span>
                            <span class="ageing-export-txt"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.export_menu_pdf'), ENT_QUOTES, 'UTF-8') : 'PDF'; ?></span>
                        </button>
                    </div>
                </div>
            </div>
            </div>
            </div>

            <div class="ageing-toolbar">
                <div class="toolbar-inner">
                    <div class="field-group field-ledger-main ledger-only">
                        <label class="field-label" for="ledgerAccountInput"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.account_ledger'), ENT_QUOTES, 'UTF-8') : 'Account Ledger'; ?></label>
                        <div class="ageing-ac-wrap" id="ledgerAcWrap">
                            <input type="search" id="ledgerAccountInput" class="form-control-sm ageing-ac-input" placeholder="<?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.type_ledger_hint'), ENT_QUOTES, 'UTF-8') : 'Type to search account ledger…'; ?>" autocomplete="off" aria-autocomplete="list" aria-expanded="false" aria-controls="ledgerAccountList" role="combobox">
                            <input type="hidden" id="ledgerCustomerId" name="ledger_customer_id" value="">
                            <div class="ageing-ac-panel" id="ledgerAccountPanel" hidden>
                                <div class="ageing-ac-head"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.select_ledger_prompt'), ENT_QUOTES, 'UTF-8') : 'Select account ledger:'; ?></div>
                                <div class="ageing-ac-list" id="ledgerAccountList" role="listbox"></div>
                            </div>
                        </div>
                    </div>
                    <div class="field-group field-stock-main stock-only" hidden>
                        <label class="field-label" for="stockProductInput"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.item'), ENT_QUOTES, 'UTF-8') : 'Item / Product'; ?></label>
                        <div class="ageing-ac-wrap" id="stockProductAcWrap">
                            <input type="search" id="stockProductInput" class="form-control-sm ageing-ac-input" placeholder="<?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.type_product_hint'), ENT_QUOTES, 'UTF-8') : 'Type to search product…'; ?>" autocomplete="off" aria-autocomplete="list" aria-expanded="false" aria-controls="stockProductList" role="combobox">
                            <input type="hidden" id="stockProductId" name="stock_product_id" value="">
                            <div class="ageing-ac-panel" id="stockProductPanel" hidden>
                                <div class="ageing-ac-head"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.select_product_prompt'), ENT_QUOTES, 'UTF-8') : 'Select product:'; ?></div>
                                <div class="ageing-ac-list" id="stockProductList" role="listbox"></div>
                            </div>
                        </div>
                    </div>

                    <div class="field-group">
                        <label class="field-label" for="agingDateInput"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.aging_date'), ENT_QUOTES, 'UTF-8') : 'Aging Date'; ?></label>
                        <div class="date-refresh-row">
                            <input type="date" id="agingDateInput" class="form-control-sm" value="<?php echo htmlspecialchars($aging_date, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="button" class="btn-icon-tight" id="btnAgingDateReset" title="<?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.reset_date'), ENT_QUOTES, 'UTF-8') : 'Reset to today'; ?>">
                                <i class="feather icon-refresh-cw"></i>
                            </button>
                        </div>
                    </div>

                    <fieldset class="radio-fieldset ledger-only">
                        <legend class="sr-only"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.pr_type'), ENT_QUOTES, 'UTF-8') : 'Payable / Receivable'; ?></legend>
                        <label class="radio-pill"><input type="radio" name="pr_type" value="payable"> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.payable'), ENT_QUOTES, 'UTF-8') : 'Payable'; ?></label>
                        <label class="radio-pill"><input type="radio" name="pr_type" value="receivable" checked> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.receivable'), ENT_QUOTES, 'UTF-8') : 'Receivable'; ?></label>
                    </fieldset>

                    <fieldset class="radio-fieldset ledger-only">
                        <legend class="sr-only"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.grouping'), ENT_QUOTES, 'UTF-8') : 'Voucher / Ledger wise'; ?></legend>
                        <label class="radio-pill"><input type="radio" name="vl_wise" value="voucher" checked> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.voucher_wise'), ENT_QUOTES, 'UTF-8') : 'Voucher Wise'; ?></label>
                        <label class="radio-pill"><input type="radio" name="vl_wise" value="ledger"> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.ledger_wise'), ENT_QUOTES, 'UTF-8') : 'Ledger Wise'; ?></label>
                    </fieldset>

                    <div class="field-group flex-grow ledger-only">
                        <label class="field-label" for="ledgerTableSearch"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.search'), ENT_QUOTES, 'UTF-8') : 'Search'; ?></label>
                        <div class="search-box-inline">
                            <input type="search" id="ledgerTableSearch" class="form-control-sm" placeholder="<?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.search_table'), ENT_QUOTES, 'UTF-8') : 'Search…'; ?>">
                            <i class="feather icon-search"></i>
                        </div>
                    </div>
                    <div class="field-group flex-grow stock-only" hidden>
                        <label class="field-label" for="stockTableSearch"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.search'), ENT_QUOTES, 'UTF-8') : 'Search'; ?></label>
                        <div class="search-box-inline">
                            <input type="search" id="stockTableSearch" class="form-control-sm" placeholder="<?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.search_table'), ENT_QUOTES, 'UTF-8') : 'Search…'; ?>">
                            <i class="feather icon-search"></i>
                        </div>
                    </div>

                </div>
            </div>

            <div class="ageing-panel" data-panel="ledger">
                        <div class="table-responsive ageing-table-wrap">
                            <table class="table ageing-table ageing-table--colmgr" id="ledgerAgeingTable">
                                <thead>
                                    <tr id="ledgerAgeingHeadRow">
                                        <th class="ageing-col-head" data-col="ledger">
                                            <span class="ageing-col-drag" title="<?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.drag_column'), ENT_QUOTES, 'UTF-8') : 'Drag to reorder'; ?>"><i class="feather icon-move"></i></span>
                                            <span class="ageing-col-head-inner"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.col_account_ledger'), ENT_QUOTES, 'UTF-8') : 'Account Ledger'; ?></span>
                                            <span class="ageing-col-resizer" title="Resize"></span>
                                        </th>
                                        <th class="ageing-col-head" data-col="voucher">
                                            <span class="ageing-col-drag"><i class="feather icon-move"></i></span>
                                            <span class="ageing-col-head-inner"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.col_voucher_name'), ENT_QUOTES, 'UTF-8') : 'Voucher Name'; ?></span>
                                            <span class="ageing-col-resizer"></span>
                                        </th>
                                        <th class="ageing-col-head" data-col="acct_no">
                                            <span class="ageing-col-drag"><i class="feather icon-move"></i></span>
                                            <span class="ageing-col-head-inner"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.col_account_no'), ENT_QUOTES, 'UTF-8') : 'Account No'; ?></span>
                                            <span class="ageing-col-resizer"></span>
                                        </th>
                                        <th class="ageing-col-head" data-col="invoice">
                                            <span class="ageing-col-drag"><i class="feather icon-move"></i></span>
                                            <span class="ageing-col-head-inner"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.col_invoice_no'), ENT_QUOTES, 'UTF-8') : 'Invoice No.'; ?></span>
                                            <span class="ageing-col-resizer"></span>
                                        </th>
                                        <th class="ageing-col-head" data-col="date">
                                            <span class="ageing-col-drag"><i class="feather icon-move"></i></span>
                                            <span class="ageing-col-head-inner"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.col_date'), ENT_QUOTES, 'UTF-8') : 'Date'; ?></span>
                                            <span class="ageing-col-resizer"></span>
                                        </th>
                                        <th class="ageing-col-head th-num" data-col="d1">
                                            <span class="ageing-col-drag"><i class="feather icon-move"></i></span>
                                            <span class="ageing-col-head-inner"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.col_1_30'), ENT_QUOTES, 'UTF-8') : '1 to 30 Days'; ?></span>
                                            <span class="ageing-col-resizer"></span>
                                        </th>
                                        <th class="ageing-col-head th-num" data-col="d2">
                                            <span class="ageing-col-drag"><i class="feather icon-move"></i></span>
                                            <span class="ageing-col-head-inner"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.col_30_60'), ENT_QUOTES, 'UTF-8') : '30 to 60 Days'; ?></span>
                                            <span class="ageing-col-resizer"></span>
                                        </th>
                                        <th class="ageing-col-head th-num" data-col="d3">
                                            <span class="ageing-col-drag"><i class="feather icon-move"></i></span>
                                            <span class="ageing-col-head-inner"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.col_60_90'), ENT_QUOTES, 'UTF-8') : '60 to 90 Days'; ?></span>
                                            <span class="ageing-col-resizer"></span>
                                        </th>
                                        <th class="ageing-col-head th-num" data-col="d4">
                                            <span class="ageing-col-drag"><i class="feather icon-move"></i></span>
                                            <span class="ageing-col-head-inner"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.col_90_120'), ENT_QUOTES, 'UTF-8') : '90 to 120 Days'; ?></span>
                                            <span class="ageing-col-resizer"></span>
                                        </th>
                                        <th class="ageing-col-head th-num" data-col="d5">
                                            <span class="ageing-col-drag"><i class="feather icon-move"></i></span>
                                            <span class="ageing-col-head-inner"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.col_120_above'), ENT_QUOTES, 'UTF-8') : '120 Above'; ?></span>
                                            <span class="ageing-col-resizer"></span>
                                        </th>
                                        <th class="ageing-col-head th-num th-total" data-col="total">
                                            <span class="ageing-col-drag"><i class="feather icon-move"></i></span>
                                            <span class="ageing-col-head-inner"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.col_total'), ENT_QUOTES, 'UTF-8') : 'Total'; ?></span>
                                            <span class="ageing-col-resizer"></span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody id="ledgerAgeingBody">
                                    <tr class="empty-row">
                                        <td colspan="11" class="empty-msg" id="ledgerEmptyMsgCell"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.no_rows'), ENT_QUOTES, 'UTF-8') : 'No Rows To Show'; ?></td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="ageing-totals" id="ledgerAgeingFootRow"></tr>
                                </tfoot>
                            </table>
                        </div>
                        <div class="pagination-container ageing-pagination">
                            <div>
                                <span id="ledgerPaginationInfo" class="pagination-info"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.showing'), ENT_QUOTES, 'UTF-8') : 'Showing'; ?> 0 <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.to'), ENT_QUOTES, 'UTF-8') : 'to'; ?> 0 <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.of'), ENT_QUOTES, 'UTF-8') : 'of'; ?> 0 <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.entries'), ENT_QUOTES, 'UTF-8') : 'entries'; ?></span>
                            </div>
                            <div class="pagination-right">
                                <label class="per-page-label">
                                    <select id="ledgerPerPage" class="form-control-sm per-page-select">
                                        <option value="10">10</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                        <option value="0"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.show_all'), ENT_QUOTES, 'UTF-8') : 'Show All Items'; ?></option>
                                    </select>
                                </label>
                                <nav class="pagination ageing-pager" aria-label="Ledger pagination">
                                    <button type="button" class="page-btn" disabled data-go="first" title="<?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.first'), ENT_QUOTES, 'UTF-8') : 'First'; ?>"><i class="feather icon-chevrons-left"></i></button>
                                    <button type="button" class="page-btn" disabled data-go="prev" title="<?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.prev'), ENT_QUOTES, 'UTF-8') : 'Previous'; ?>"><i class="feather icon-chevron-left"></i></button>
                                    <button type="button" class="page-btn" disabled data-go="next" title="<?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.next'), ENT_QUOTES, 'UTF-8') : 'Next'; ?>"><i class="feather icon-chevron-right"></i></button>
                                    <button type="button" class="page-btn" disabled data-go="last" title="<?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.last'), ENT_QUOTES, 'UTF-8') : 'Last'; ?>"><i class="feather icon-chevrons-right"></i></button>
                                </nav>
                            </div>
                        </div>
            </div>

            <div class="ageing-panel hidden" data-panel="stock">
                        <div class="table-responsive ageing-table-wrap">
                            <table class="table ageing-table ageing-table--stock ageing-table--colmgr" id="stockAgeingTable">
                                <thead>
                                    <tr id="stockAgeingHeadRow">
                                        <th class="ageing-col-head" data-col="branch">
                                            <span class="ageing-col-drag" title="<?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.drag_column'), ENT_QUOTES, 'UTF-8') : 'Drag to reorder'; ?>"><i class="feather icon-move"></i></span>
                                            <span class="ageing-col-head-inner"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.col_branch'), ENT_QUOTES, 'UTF-8') : 'Branch'; ?></span>
                                            <span class="ageing-col-resizer" title="Resize"></span>
                                        </th>
                                        <th class="ageing-col-head" data-col="carat">
                                            <span class="ageing-col-drag"><i class="feather icon-move"></i></span>
                                            <span class="ageing-col-head-inner"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.col_carat'), ENT_QUOTES, 'UTF-8') : 'Carat'; ?></span>
                                            <span class="ageing-col-resizer"></span>
                                        </th>
                                        <th class="ageing-col-head" data-col="metal">
                                            <span class="ageing-col-drag"><i class="feather icon-move"></i></span>
                                            <span class="ageing-col-head-inner"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.col_metal'), ENT_QUOTES, 'UTF-8') : 'Metal'; ?></span>
                                            <span class="ageing-col-resizer"></span>
                                        </th>
                                        <th class="ageing-col-head" data-col="product_code">
                                            <span class="ageing-col-drag"><i class="feather icon-move"></i></span>
                                            <span class="ageing-col-head-inner"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.col_product_code'), ENT_QUOTES, 'UTF-8') : 'Product Code'; ?></span>
                                            <span class="ageing-col-resizer"></span>
                                        </th>
                                        <th class="ageing-col-head" data-col="rfid_code">
                                            <span class="ageing-col-drag"><i class="feather icon-move"></i></span>
                                            <span class="ageing-col-head-inner"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.col_rfid_code'), ENT_QUOTES, 'UTF-8') : 'RFID Code'; ?></span>
                                            <span class="ageing-col-resizer"></span>
                                        </th>
                                        <th class="ageing-col-head" data-col="barcode">
                                            <span class="ageing-col-drag"><i class="feather icon-move"></i></span>
                                            <span class="ageing-col-head-inner"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.col_barcode'), ENT_QUOTES, 'UTF-8') : 'Barcode'; ?></span>
                                            <span class="ageing-col-resizer"></span>
                                        </th>
                                        <th class="ageing-col-head th-num" data-col="qty">
                                            <span class="ageing-col-drag"><i class="feather icon-move"></i></span>
                                            <span class="ageing-col-head-inner"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.col_qty'), ENT_QUOTES, 'UTF-8') : 'Qty'; ?></span>
                                            <span class="ageing-col-resizer"></span>
                                        </th>
                                        <th class="ageing-col-head" data-col="location">
                                            <span class="ageing-col-drag"><i class="feather icon-move"></i></span>
                                            <span class="ageing-col-head-inner"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.col_location'), ENT_QUOTES, 'UTF-8') : 'Location'; ?></span>
                                            <span class="ageing-col-resizer"></span>
                                        </th>
                                        <th class="ageing-col-head th-num" data-col="age">
                                            <span class="ageing-col-drag"><i class="feather icon-move"></i></span>
                                            <span class="ageing-col-head-inner"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.col_age'), ENT_QUOTES, 'UTF-8') : 'Age'; ?></span>
                                            <span class="ageing-col-resizer"></span>
                                        </th>
                                        <th class="ageing-col-head th-num" data-col="gross_wt">
                                            <span class="ageing-col-drag"><i class="feather icon-move"></i></span>
                                            <span class="ageing-col-head-inner"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.col_gross_wt'), ENT_QUOTES, 'UTF-8') : 'Gross Wt'; ?></span>
                                            <span class="ageing-col-resizer"></span>
                                        </th>
                                        <th class="ageing-col-head th-num" data-col="purity_wt">
                                            <span class="ageing-col-drag"><i class="feather icon-move"></i></span>
                                            <span class="ageing-col-head-inner"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.col_purity_wt'), ENT_QUOTES, 'UTF-8') : 'Purity Wt'; ?></span>
                                            <span class="ageing-col-resizer"></span>
                                        </th>
                                        <th class="ageing-col-head th-num" data-col="net_wt">
                                            <span class="ageing-col-drag"><i class="feather icon-move"></i></span>
                                            <span class="ageing-col-head-inner"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.col_net_wt'), ENT_QUOTES, 'UTF-8') : 'Net Wt.'; ?></span>
                                            <span class="ageing-col-resizer"></span>
                                        </th>
                                        <th class="ageing-col-head th-num" data-col="final_wt">
                                            <span class="ageing-col-drag"><i class="feather icon-move"></i></span>
                                            <span class="ageing-col-head-inner"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.col_final_wt'), ENT_QUOTES, 'UTF-8') : 'Final Wt.'; ?></span>
                                            <span class="ageing-col-resizer"></span>
                                        </th>
                                        <th class="ageing-col-head" data-col="voucher_type">
                                            <span class="ageing-col-drag"><i class="feather icon-move"></i></span>
                                            <span class="ageing-col-head-inner"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.col_voucher_type'), ENT_QUOTES, 'UTF-8') : 'Voucher Type'; ?></span>
                                            <span class="ageing-col-resizer"></span>
                                        </th>
                                        <th class="ageing-col-head th-total" data-col="invoice_no">
                                            <span class="ageing-col-drag"><i class="feather icon-move"></i></span>
                                            <span class="ageing-col-head-inner"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.col_invoice_no'), ENT_QUOTES, 'UTF-8') : 'Invoice No.'; ?></span>
                                            <span class="ageing-col-resizer"></span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody id="stockAgeingBody">
                                    <tr class="empty-row">
                                        <td colspan="15" class="empty-msg" id="stockEmptyMsgCell"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.no_rows'), ENT_QUOTES, 'UTF-8') : 'No Rows To Show'; ?></td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="ageing-totals" id="stockAgeingFootRow"></tr>
                                </tfoot>
                            </table>
                        </div>
                        <div class="pagination-container ageing-pagination">
                            <div>
                                <span id="stockPaginationInfo" class="pagination-info"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.showing'), ENT_QUOTES, 'UTF-8') : 'Showing'; ?> 0 <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.to'), ENT_QUOTES, 'UTF-8') : 'to'; ?> 0 <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.of'), ENT_QUOTES, 'UTF-8') : 'of'; ?> 0 <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.entries'), ENT_QUOTES, 'UTF-8') : 'entries'; ?></span>
                            </div>
                            <div class="pagination-right">
                                <label class="per-page-label">
                                    <select id="stockPerPage" class="form-control-sm per-page-select">
                                        <option value="10">10</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                        <option value="0"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.show_all'), ENT_QUOTES, 'UTF-8') : 'Show All Items'; ?></option>
                                    </select>
                                </label>
                                <nav class="pagination ageing-pager stock-pager" aria-label="Stock pagination">
                                    <button type="button" class="page-btn" disabled data-go="first"><i class="feather icon-chevrons-left"></i></button>
                                    <button type="button" class="page-btn" disabled data-go="prev"><i class="feather icon-chevron-left"></i></button>
                                    <button type="button" class="page-btn" disabled data-go="next"><i class="feather icon-chevron-right"></i></button>
                                    <button type="button" class="page-btn" disabled data-go="last"><i class="feather icon-chevrons-right"></i></button>
                                </nav>
                            </div>
                        </div>
            </div>

            </div><!-- .ageing-shell -->
        </div>
    </div>
</div>

<div class="ageing-col-modal" id="ledgerColSettingsModal" hidden>
    <div class="ageing-col-modal__backdrop" id="ledgerColSettingsBackdrop"></div>
    <div class="ageing-col-modal__panel" role="dialog" aria-labelledby="ledgerColSettingsTitle">
        <div class="ageing-col-modal__head">
            <h2 class="ageing-col-modal__title" id="ledgerColSettingsTitle"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.col_settings_title'), ENT_QUOTES, 'UTF-8') : 'Show / hide columns'; ?></h2>
            <button type="button" class="ageing-col-modal__close" id="ledgerColSettingsCloseX" aria-label="<?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.columns_close'), ENT_QUOTES, 'UTF-8') : 'Close'; ?>">&times;</button>
        </div>
        <p class="ageing-col-modal__hint"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.col_settings_hint'), ENT_QUOTES, 'UTF-8') : 'Tick columns to show. Drag column headers in the table to reorder. Drag header edges to resize.'; ?></p>
        <ul class="ageing-col-modal__list" id="ledgerColSettingsList"></ul>
        <div class="ageing-col-modal__actions">
            <button type="button" class="ageing-col-modal__btn ageing-col-modal__btn--secondary" id="ledgerColSettingsReset"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.columns_reset'), ENT_QUOTES, 'UTF-8') : 'Reset'; ?></button>
            <button type="button" class="ageing-col-modal__btn ageing-col-modal__btn--primary" id="ledgerColSettingsCloseBtn"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.columns_close'), ENT_QUOTES, 'UTF-8') : 'Close'; ?></button>
        </div>
    </div>
</div>

<div class="ageing-col-modal" id="stockColSettingsModal" hidden>
    <div class="ageing-col-modal__backdrop" id="stockColSettingsBackdrop"></div>
    <div class="ageing-col-modal__panel" role="dialog" aria-labelledby="stockColSettingsTitle">
        <div class="ageing-col-modal__head">
            <h2 class="ageing-col-modal__title" id="stockColSettingsTitle"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.col_settings_title'), ENT_QUOTES, 'UTF-8') : 'Show / hide columns'; ?></h2>
            <button type="button" class="ageing-col-modal__close" id="stockColSettingsCloseX" aria-label="<?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.columns_close'), ENT_QUOTES, 'UTF-8') : 'Close'; ?>">&times;</button>
        </div>
        <p class="ageing-col-modal__hint"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.col_settings_hint'), ENT_QUOTES, 'UTF-8') : 'Tick columns to show. Drag column headers in the table to reorder. Drag header edges to resize.'; ?></p>
        <ul class="ageing-col-modal__list" id="stockColSettingsList"></ul>
        <div class="ageing-col-modal__actions">
            <button type="button" class="ageing-col-modal__btn ageing-col-modal__btn--secondary" id="stockColSettingsReset"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.columns_reset'), ENT_QUOTES, 'UTF-8') : 'Reset'; ?></button>
            <button type="button" class="ageing-col-modal__btn ageing-col-modal__btn--primary" id="stockColSettingsCloseBtn"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.columns_close'), ENT_QUOTES, 'UTF-8') : 'Close'; ?></button>
        </div>
    </div>
</div>

<?php include 'footer-script.php'; ?>

<style>
.ageing-report-page {
    --ageing-navy: #11294b;
    --ageing-navy-dark: #0c1d36;
    --ageing-gold: #c9a227;
    --ageing-gold-hover: #d4af37;
    --ageing-gold-muted: #f5eed9;
    --ageing-border: #c5cddf;
    --ageing-bg: #eef1f6;
    --ageing-total-row: #e8eef5;
}

/* Full width: use horizontal space under top nav */
.ageing-report-page.layout-container {
    padding: 10px clamp(6px, 0.9vw, 14px) 20px;
    width: 100%;
    max-width: 100vw;
    margin: 0;
    box-sizing: border-box;
    background: var(--ageing-bg);
    min-height: calc(100vh - 60px);
}
.ageing-report-page .main-content,
.ageing-report-page .page-container {
    width: 100%;
    max-width: 100%;
    margin: 0;
    padding: 0;
}

/* Single report card */
.ageing-shell {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    background: #fff;
    border: 1px solid var(--ageing-border);
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(17, 41, 75, 0.07);
    overflow: hidden;
}
.ageing-report-page .ageing-panel {
    min-width: 0;
}
.ageing-shell-top {
    padding: 14px 18px 0;
    background: #fff;
}
.ageing-tabs-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px 18px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--ageing-border);
    margin-bottom: 0;
}
.ageing-tabs-row .toolbar-actions {
    margin-left: auto;
    align-items: center;
    align-self: center;
}
.ageing-tabs-row__actions {
    flex-shrink: 0;
}

.ageing-tabs {
    display: inline-flex;
    gap: 0;
    margin: 0;
}
.ageing-tab {
    border: 2px solid var(--ageing-navy);
    background: #fff;
    color: var(--ageing-navy);
    padding: 9px 26px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s, color 0.15s, box-shadow 0.15s, border-color 0.15s;
}
.ageing-tab:first-child {
    border-radius: 8px 0 0 8px;
    border-right-width: 1px;
}
.ageing-tab:last-child {
    border-radius: 0 8px 8px 0;
    border-left-width: 1px;
    margin-left: -2px;
}
.ageing-tab.active {
    background: var(--ageing-navy);
    color: #fff;
    z-index: 1;
    border-color: var(--ageing-navy);
    box-shadow: inset 0 -3px 0 0 var(--ageing-gold), 0 3px 14px rgba(17, 41, 75, 0.22);
}
.ageing-tab:hover:not(.active) {
    background: var(--ageing-gold-muted);
}

/* Filter strip */
.ageing-toolbar {
    margin: 0;
    background: linear-gradient(180deg, #fbfcfe 0%, #f4f6fa 100%);
    border-bottom: 1px solid var(--ageing-border);
}
.toolbar-inner {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 14px 22px;
    padding: 16px 18px 18px;
}
.field-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
    min-width: 140px;
}
.field-group.field-ledger-main {
    flex: 1 1 280px;
    min-width: min(100%, 240px);
    max-width: none;
}
.field-group.field-stock-main {
    flex: 1 1 280px;
    min-width: min(100%, 240px);
    max-width: none;
}
.field-group.flex-grow {
    flex: 1 1 200px;
    min-width: 160px;
    max-width: none;
}
.field-label {
    font-size: 11px;
    font-weight: 700;
    color: #5c6b7a;
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.form-control-sm {
    height: 38px;
    padding: 7px 12px;
    border: 1px solid #c9d4e3;
    border-radius: 8px;
    font-size: 12px;
    color: #1e293b;
    background: #fff;
    box-shadow: 0 1px 2px rgba(17, 41, 75, 0.05);
    transition: border-color 0.15s, box-shadow 0.15s;
    box-sizing: border-box;
}
.ageing-report-page .form-control-sm:hover {
    border-color: #b8c5d8;
}
.ageing-report-page .form-control-sm:focus {
    outline: none;
    border-color: rgba(201, 162, 39, 0.85);
    box-shadow: 0 0 0 3px rgba(201, 162, 39, 0.22);
}
.search-select-wrap {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 0;
    width: 100%;
}
.search-select-wrap .ledger-select {
    width: 100%;
}

/* Account ledger autocomplete (typeahead) */
.ageing-ac-wrap {
    position: relative;
    width: 100%;
    min-width: 0;
}
.ageing-ac-input {
    width: 100%;
}
.ageing-ac-panel {
    position: absolute;
    left: 0;
    right: 0;
    top: calc(100% + 5px);
    z-index: 100;
    background: #fff;
    border: 1px solid var(--ageing-border);
    border-radius: 10px;
    box-shadow: 0 14px 36px rgba(17, 41, 75, 0.16);
    max-height: min(340px, 48vh);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.ageing-ac-panel[hidden] {
    display: none !important;
}
.ageing-ac-head {
    padding: 10px 14px;
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
    letter-spacing: 0.02em;
    border-bottom: 1px solid #eef2f7;
    background: linear-gradient(180deg, #fafbfc 0%, #f4f6f9 100%);
}
.ageing-ac-list {
    overflow-y: auto;
    max-height: 280px;
}
.ageing-ac-item {
    display: block;
    width: 100%;
    text-align: left;
    border: none;
    background: #fff;
    padding: 11px 14px;
    cursor: pointer;
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.12s;
}
.ageing-ac-item:last-child {
    border-bottom: none;
}
.ageing-ac-item:hover,
.ageing-ac-item.is-active {
    background: var(--ageing-gold-muted);
}
.ageing-ac-item-name {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #1e293b;
    line-height: 1.35;
}
.ageing-ac-item-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 4px;
    font-size: 12px;
    color: #64748b;
}
.ageing-ac-item-meta .feather {
    width: 14px;
    height: 14px;
    color: #c5a059;
    flex-shrink: 0;
}
.ageing-ac-empty {
    padding: 18px 14px;
    font-size: 12px;
    color: #94a3b8;
    text-align: center;
}
.ageing-ac-loading {
    padding: 14px;
    font-size: 12px;
    color: #94a3b8;
    text-align: center;
}

.date-refresh-row {
    display: flex;
    align-items: center;
    gap: 8px;
}
.date-refresh-row input[type="date"] {
    min-width: 148px;
    flex: 1;
}
.btn-icon-tight {
    width: 38px;
    height: 38px;
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #c9d4e3;
    border-radius: 8px;
    background: #fff;
    cursor: pointer;
    color: #475569;
    box-shadow: 0 1px 2px rgba(17, 41, 75, 0.05);
    transition: border-color 0.15s, color 0.15s, background 0.15s;
}
.btn-icon-tight:hover {
    background: #f8fafc;
    border-color: var(--ageing-gold);
    color: var(--ageing-navy);
}
.radio-fieldset {
    border: none;
    padding: 8px 14px;
    margin: 0;
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    align-items: center;
    align-self: flex-end;
    background: rgba(255, 255, 255, 0.85);
    border-radius: 8px;
    border: 1px solid #e1e8f0;
}
.radio-pill {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-size: 12px;
    font-weight: 500;
    color: #334155;
    cursor: pointer;
    margin: 0;
}
.radio-pill input[type="radio"] {
    width: 15px;
    height: 15px;
    accent-color: var(--ageing-gold);
    cursor: pointer;
}
.search-box-inline {
    position: relative;
}
.search-box-inline input {
    width: 100%;
    padding-right: 34px;
}
.search-box-inline .feather {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    width: 16px;
    height: 16px;
    color: #94a3b8;
    pointer-events: none;
}
.toolbar-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}
.ageing-toolbar-gear .feather {
    width: 18px;
    height: 18px;
    stroke-width: 2.2px;
    color: var(--ageing-gold);
}
.ageing-toolbar-gear:hover .feather {
    color: var(--ageing-navy);
}
.btn-ageing-primary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    height: 38px;
    padding: 0 18px;
    background: var(--ageing-navy);
    color: #fff;
    border: 2px solid var(--ageing-gold);
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 2px 10px rgba(17, 41, 75, 0.3);
    transition: background 0.15s, border-color 0.15s, transform 0.1s;
}
.btn-ageing-primary:hover {
    background: var(--ageing-navy-dark);
    border-color: var(--ageing-gold-hover);
}
.btn-ageing-primary:active {
    transform: translateY(1px);
}
/* Export — navy + gold (logo palette) */
.ageing-export-dd .btn-ageing-primary {
    background: var(--ageing-navy);
    border: 2px solid var(--ageing-gold);
    box-shadow: 0 2px 10px rgba(17, 41, 75, 0.32);
}
.ageing-export-dd .btn-ageing-primary:hover {
    background: var(--ageing-navy-dark);
    border-color: var(--ageing-gold-hover);
}
.ageing-export-dd {
    position: relative;
}
.ageing-export-menu {
    position: absolute;
    top: 100%;
    left: 0;
    right: auto;
    margin-top: 6px;
    min-width: 196px;
    background: #fff;
    border: 1px solid var(--ageing-border);
    border-radius: 10px;
    box-shadow: 0 12px 32px rgba(17, 41, 75, 0.14);
    z-index: 1050;
    padding: 8px 0;
    overflow: hidden;
}
/* Not Bootstrap .dropdown-menu — that rule forces display:none until .show; we toggle the [hidden] attribute only */
.ageing-export-menu:not([hidden]) {
    display: block;
}
.ageing-export-item {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    margin: 0;
    border: none;
    background: transparent;
    padding: 11px 16px;
    font-size: 13px;
    font-weight: 500;
    color: #334155;
    cursor: pointer;
    text-align: left;
    font-family: inherit;
    line-height: 1.3;
    transition: background 0.12s;
}
.ageing-export-item:hover {
    background: var(--ageing-gold-muted);
    color: #1e293b;
}
.ageing-export-ico {
    width: 28px;
    height: 28px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 1.35rem;
    line-height: 1;
}
.ageing-export-ico--excel {
    color: #1d6f42;
}
.ageing-export-ico--pdf {
    color: #e5252a;
}
.ageing-export-txt {
    flex: 1;
}

/* Table — navy header (match reference) */
.ageing-table-wrap {
    display: block;
    max-height: calc(100vh - 300px);
    overflow-x: auto;
    overflow-y: auto;
    min-width: 0;
    width: 100%;
    max-width: 100%;
    background: #fff;
    scrollbar-gutter: stable;
    -webkit-overflow-scrolling: touch;
}
.ageing-table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
    font-size: 12px;
}
.ageing-table--colmgr {
    table-layout: fixed;
}
.ageing-table.ageing-table--stock.ageing-table--colmgr {
    table-layout: fixed;
    width: max(100%, 1280px);
    min-width: 1280px;
}
.ageing-table thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: var(--ageing-navy);
    padding: 11px 12px;
    text-align: left;
    font-weight: 600;
    color: #fff;
    border-bottom: none;
    white-space: nowrap;
    box-shadow: 0 1px 0 rgba(0, 0, 0, 0.12);
}
.ageing-col-head {
    position: relative;
    padding-right: 14px;
    vertical-align: middle;
}
.ageing-col-drag {
    display: inline-block;
    vertical-align: middle;
    margin-right: 5px;
    cursor: grab;
    opacity: 0.88;
    line-height: 0;
    user-select: none;
    touch-action: none;
}
.ageing-table thead .ageing-col-drag {
    opacity: 1;
}
.ageing-table thead .ageing-col-drag .feather {
    width: 15px;
    height: 15px;
    color: var(--ageing-gold);
}
.ageing-col-drag:active {
    cursor: grabbing;
}
.ageing-col-head-inner {
    vertical-align: middle;
}
.ageing-col-head.ageing-col-dragging {
    opacity: 0.55;
}
.ageing-col-head.ageing-col-drop-target {
    box-shadow: inset 0 0 0 2px rgba(255, 255, 255, 0.75);
}
.ageing-col-resizer {
    position: absolute;
    right: 0;
    top: 0;
    bottom: 0;
    width: 8px;
    cursor: col-resize;
    z-index: 3;
    user-select: none;
}
.ageing-col-hidden {
    display: none !important;
}
.ageing-table .th-num {
    text-align: right;
}
.ageing-table thead .th-sort .sort-icons {
    opacity: 1;
    color: rgba(255, 255, 255, 0.9);
    vertical-align: middle;
    margin-left: 2px;
}
.ageing-table thead .th-sort .sort-icons .feather {
    width: 13px;
    height: 13px;
}
.ageing-table thead .th-gear-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin: 0 0 0 6px;
    padding: 2px;
    border: 0;
    background: transparent;
    color: #e8c547;
    opacity: 0.95;
    vertical-align: middle;
    cursor: pointer;
    line-height: 1;
}
.ageing-table thead .th-gear-btn:hover {
    opacity: 1;
}
.ageing-table thead .th-gear-btn .feather {
    width: 15px;
    height: 15px;
}
.ageing-table tbody td {
    padding: 10px 12px;
    border-bottom: 1px solid #eef2f7;
    color: #475569;
    background: #fff;
}
.ageing-table tbody .empty-msg {
    text-align: center;
    color: #94a3b8;
    padding: 52px 16px !important;
    font-size: 13px;
}
.ageing-totals td {
    background: var(--ageing-total-row);
    font-weight: 600;
    color: #1e293b;
    border-top: 1px solid #c5d0e0;
    padding: 11px 12px;
}
.ageing-totals .totals-label {
    text-align: right;
}
.ageing-col-modal {
    --ageing-navy: #11294b;
    --ageing-col-gold: #c9a227;
    position: fixed;
    inset: 0;
    z-index: 4000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
}
.ageing-col-modal[hidden] {
    display: none !important;
}
.ageing-col-modal__backdrop {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.45);
}
.ageing-col-modal__panel {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 400px;
    max-height: 90vh;
    overflow: auto;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 18px 48px rgba(15, 23, 42, 0.18);
    padding: 18px 20px 16px;
}
.ageing-col-modal__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 8px;
}
.ageing-col-modal__title {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 700;
    color: #1e293b;
}
.ageing-col-modal__close {
    border: 0;
    background: transparent;
    font-size: 1.5rem;
    line-height: 1;
    color: #64748b;
    cursor: pointer;
    padding: 0 4px;
}
.ageing-col-modal__hint {
    margin: 0 0 12px;
    font-size: 12px;
    color: #64748b;
    line-height: 1.45;
}
.ageing-col-modal__list {
    list-style: none;
    margin: 0;
    padding: 0;
}
.ageing-col-modal__list li {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
    color: #334155;
}
.ageing-col-modal__list li:last-child {
    border-bottom: 0;
}
.ageing-col-modal__list input {
    flex-shrink: 0;
}
.ageing-col-modal__actions {
    display: flex;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 16px;
    padding-top: 14px;
    border-top: 1px solid #e8e4d9;
}
/* Column modal actions — navy + gold accent (match inventory Columns popover style) */
.ageing-col-modal__btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 38px;
    padding: 0 20px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    font-family: inherit;
    line-height: 1.2;
    cursor: pointer;
    transition: background 0.15s, color 0.15s, border-color 0.15s, box-shadow 0.15s, transform 0.08s;
    -webkit-appearance: none;
    appearance: none;
}
.ageing-col-modal__btn--secondary {
    background: #fffef8;
    color: var(--ageing-navy);
    border: 1px solid rgba(17, 41, 75, 0.45);
    box-shadow: 0 1px 2px rgba(17, 41, 75, 0.06);
}
.ageing-col-modal__btn--secondary:hover {
    background: #fff;
    border-color: var(--ageing-navy);
    box-shadow: 0 3px 10px rgba(17, 41, 75, 0.1);
}
.ageing-col-modal__btn--primary {
    background: var(--ageing-navy);
    color: #fff !important;
    border: 2px solid var(--ageing-col-gold);
    box-shadow: 0 2px 8px rgba(17, 41, 75, 0.35), 0 0 0 1px rgba(201, 162, 39, 0.35);
}
.ageing-col-modal__btn--primary:hover {
    background: #0d2038;
    border-color: #d4b03a;
    box-shadow: 0 4px 14px rgba(17, 41, 75, 0.4);
}
.ageing-col-modal__btn--primary:active,
.ageing-col-modal__btn--secondary:active {
    transform: translateY(1px);
}
.hidden {
    display: none !important;
}
.ageing-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    padding: 12px 18px;
    border-top: 1px solid var(--ageing-border);
    background: #f9fafc;
}
.pagination-info {
    font-size: 11px;
    color: #64748b;
}
.pagination-right {
    display: flex;
    align-items: center;
    gap: 14px;
}
.per-page-select {
    min-width: 140px;
}
.ageing-pager {
    display: flex;
    gap: 5px;
}
.ageing-pager .page-btn {
    width: 34px;
    height: 34px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #c9d4e3;
    border-radius: 8px;
    background: #fff;
    color: #475569;
    cursor: pointer;
    transition: border-color 0.15s, color 0.15s, background 0.15s;
}
.ageing-pager .page-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}
.ageing-pager .page-btn:not(:disabled):hover {
    background: #fff;
    border-color: var(--ageing-gold);
    color: var(--ageing-navy);
}
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    border: 0;
}
</style>

<script>
window.AURAGOLD_AGEING_NO_ROWS = <?php echo json_encode(function_exists('auragold_t') ? auragold_t('ageing.no_rows') : 'No Rows To Show'); ?>;
window.AURAGOLD_AGEING_AC_EMPTY = <?php echo json_encode(function_exists('auragold_t') ? auragold_t('ageing.ac_empty') : 'No matching ledgers'); ?>;
window.AURAGOLD_AGEING_AC_SEARCHING = <?php echo json_encode(function_exists('auragold_t') ? auragold_t('ageing.ac_searching') : 'Searching…'); ?>;
window.AURAGOLD_AGEING_EXPORT_ERR = <?php echo json_encode(function_exists('auragold_t') ? auragold_t('ageing.export_failed') : 'Could not export Excel. Please try again.'); ?>;
window.AURAGOLD_AGEING_TOTAL_LBL = <?php echo json_encode(function_exists('auragold_t') ? auragold_t('ageing.total') : 'Total'); ?>;
window.AURAGOLD_AGEING_PRODUCT_AC_EMPTY = <?php echo json_encode(function_exists('auragold_t') ? auragold_t('ageing.product_ac_empty') : 'No matching products'); ?>;
(function () {
    var activeTab = 'ledger';
    var ledgerSearchTimer = null;
    var ledgerAcAbort = null;
    var ledgerAccountFilterDebounce = null;
    var stockProductSearchTimer = null;
    var stockProductAbort = null;

    function toggleExportMenu(show) {
        var m = document.getElementById('exportMenu');
        if (!m) return;
        if (show === undefined) show = m.hasAttribute('hidden');
        if (show) m.removeAttribute('hidden');
        else m.setAttribute('hidden', '');
    }

    var btnExportToggle = document.getElementById('btnExportToggle');
    if (btnExportToggle) {
        btnExportToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            var m = document.getElementById('exportMenu');
            toggleExportMenu(m && m.hasAttribute('hidden'));
        });
    }
    document.addEventListener('click', function () {
        var m = document.getElementById('exportMenu');
        if (m && !m.hasAttribute('hidden')) m.setAttribute('hidden', '');
    });

    var exportExcelBtn = document.getElementById('exportExcel');
    if (exportExcelBtn) {
        exportExcelBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            toggleExportMenu(false);
            runAgeingExcelExport();
        });
    }
    var exportPdfBtn = document.getElementById('exportPdf');
    if (exportPdfBtn) {
        exportPdfBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            toggleExportMenu(false);
        });
    }

    var exportMenuEl = document.getElementById('exportMenu');
    if (exportMenuEl) {
        exportMenuEl.addEventListener('click', function (e) { e.stopPropagation(); });
    }

    function parseExportNum(txt) {
        var n = parseFloat(String(txt || '').replace(/,/g, '').trim());
        return isNaN(n) ? 0 : n;
    }

    function collectLedgerExportPayload() {
        function txt(tr, key) {
            var td = tr.querySelector('td[data-col="' + key + '"]');
            if (td) return td.textContent.trim();
            return '';
        }
        function numFrom(tr, key) {
            var td = tr.querySelector('td[data-col="' + key + '"]');
            if (td) return parseExportNum(td.textContent);
            return 0;
        }
        var rows = [];
        document.querySelectorAll('#ledgerAgeingTable tbody tr').forEach(function (tr) {
            if (tr.classList.contains('empty-row')) return;
            var tds = tr.querySelectorAll('td');
            if (tr.querySelector('td[data-col]')) {
                rows.push({
                    account_ledger: txt(tr, 'ledger'),
                    voucher_name: txt(tr, 'voucher'),
                    account_no: txt(tr, 'acct_no'),
                    invoice_no: txt(tr, 'invoice'),
                    date: txt(tr, 'date'),
                    d1: numFrom(tr, 'd1'),
                    d2: numFrom(tr, 'd2'),
                    d3: numFrom(tr, 'd3'),
                    d4: numFrom(tr, 'd4'),
                    d5: numFrom(tr, 'd5'),
                    total: numFrom(tr, 'total')
                });
                return;
            }
            if (tds.length < 11) return;
            rows.push({
                account_ledger: tds[0].textContent.trim(),
                voucher_name: tds[1].textContent.trim(),
                account_no: tds[2].textContent.trim(),
                invoice_no: tds[3].textContent.trim(),
                date: tds[4].textContent.trim(),
                d1: parseExportNum(tds[5].textContent),
                d2: parseExportNum(tds[6].textContent),
                d3: parseExportNum(tds[7].textContent),
                d4: parseExportNum(tds[8].textContent),
                d5: parseExportNum(tds[9].textContent),
                total: parseExportNum(tds[10].textContent)
            });
        });
        var totals = [
            parseExportNum(document.getElementById('ledgerSum1') && document.getElementById('ledgerSum1').textContent),
            parseExportNum(document.getElementById('ledgerSum2') && document.getElementById('ledgerSum2').textContent),
            parseExportNum(document.getElementById('ledgerSum3') && document.getElementById('ledgerSum3').textContent),
            parseExportNum(document.getElementById('ledgerSum4') && document.getElementById('ledgerSum4').textContent),
            parseExportNum(document.getElementById('ledgerSum5') && document.getElementById('ledgerSum5').textContent),
            parseExportNum(document.getElementById('ledgerSumT') && document.getElementById('ledgerSumT').textContent)
        ];
        return { rows: rows, totals: totals };
    }

    function collectStockExportPayload() {
        function txt(tr, key) {
            var td = tr.querySelector('td[data-col="' + key + '"]');
            if (td) return td.textContent.trim();
            return '';
        }
        function numFrom(tr, key) {
            var td = tr.querySelector('td[data-col="' + key + '"]');
            if (td) return parseExportNum(td.textContent);
            return 0;
        }
        var rows = [];
        document.querySelectorAll('#stockAgeingTable tbody tr').forEach(function (tr) {
            if (tr.classList.contains('empty-row')) return;
            if (tr.querySelector('td[data-col]')) {
                rows.push({
                    branch: txt(tr, 'branch'),
                    carat: txt(tr, 'carat'),
                    metal: txt(tr, 'metal'),
                    product_code: txt(tr, 'product_code'),
                    rfid_code: txt(tr, 'rfid_code'),
                    barcode: txt(tr, 'barcode'),
                    qty: numFrom(tr, 'qty'),
                    location: txt(tr, 'location'),
                    age: numFrom(tr, 'age'),
                    gross_wt: numFrom(tr, 'gross_wt'),
                    purity_wt: numFrom(tr, 'purity_wt'),
                    net_wt: numFrom(tr, 'net_wt'),
                    final_wt: numFrom(tr, 'final_wt'),
                    voucher_type: txt(tr, 'voucher_type'),
                    invoice_no: txt(tr, 'invoice_no')
                });
                return;
            }
            var tds = tr.querySelectorAll('td');
            if (tds.length < 15) return;
            rows.push({
                branch: tds[0].textContent.trim(),
                carat: tds[1].textContent.trim(),
                metal: tds[2].textContent.trim(),
                product_code: tds[3].textContent.trim(),
                rfid_code: tds[4].textContent.trim(),
                barcode: tds[5].textContent.trim(),
                qty: parseExportNum(tds[6].textContent),
                location: tds[7].textContent.trim(),
                age: parseExportNum(tds[8].textContent),
                gross_wt: parseExportNum(tds[9].textContent),
                purity_wt: parseExportNum(tds[10].textContent),
                net_wt: parseExportNum(tds[11].textContent),
                final_wt: parseExportNum(tds[12].textContent),
                voucher_type: tds[13].textContent.trim(),
                invoice_no: tds[14].textContent.trim()
            });
        });
        var totals = [
            parseExportNum(document.getElementById('stockSum1') && document.getElementById('stockSum1').textContent),
            parseExportNum(document.getElementById('stockSum2') && document.getElementById('stockSum2').textContent),
            parseExportNum(document.getElementById('stockSum3') && document.getElementById('stockSum3').textContent),
            parseExportNum(document.getElementById('stockSum4') && document.getElementById('stockSum4').textContent),
            parseExportNum(document.getElementById('stockSum5') && document.getElementById('stockSum5').textContent)
        ];
        return { rows: rows, totals: totals };
    }

    function runAgeingExcelExport() {
        var agingInp = document.getElementById('agingDateInput');
        var agingDate = agingInp ? agingInp.value : '';
        if (!agingDate || !/^\d{4}-\d{2}-\d{2}$/.test(agingDate)) {
            alert(typeof window.AURAGOLD_AGEING_EXPORT_ERR === 'string' ? window.AURAGOLD_AGEING_EXPORT_ERR : 'Export failed.');
            return;
        }
        var pack = activeTab === 'stock' ? collectStockExportPayload() : collectLedgerExportPayload();
        var body = {
            tab: activeTab === 'stock' ? 'stock' : 'ledger',
            aging_date: agingDate,
            rows: pack.rows,
            totals: pack.totals
        };
        fetch('ajax/export-ageing-report-excel.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
            credentials: 'same-origin'
        })
            .then(function (res) {
                if (!res.ok) throw new Error('bad');
                var ct = (res.headers.get('Content-Type') || '').toLowerCase();
                if (ct.indexOf('spreadsheetml') === -1 && ct.indexOf('octet-stream') === -1) throw new Error('bad');
                return res.blob();
            })
            .then(function (blob) {
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = activeTab === 'stock' ? 'Ageing_Report_Stock_' + agingDate + '.xlsx' : 'Ageing_Report_' + agingDate + '.xlsx';
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
            })
            .catch(function () {
                alert(typeof window.AURAGOLD_AGEING_EXPORT_ERR === 'string' ? window.AURAGOLD_AGEING_EXPORT_ERR : 'Export failed.');
            });
    }

    function hideLedgerAcPanel() {
        var panel = document.getElementById('ledgerAccountPanel');
        var inp = document.getElementById('ledgerAccountInput');
        if (panel) {
            panel.hidden = true;
        }
        if (inp) {
            inp.setAttribute('aria-expanded', 'false');
        }
    }

    function showLedgerAcLoading() {
        var list = document.getElementById('ledgerAccountList');
        if (!list) return;
        list.innerHTML = '';
        var loading = document.createElement('div');
        loading.className = 'ageing-ac-loading';
        loading.textContent = typeof window.AURAGOLD_AGEING_AC_SEARCHING === 'string' ? window.AURAGOLD_AGEING_AC_SEARCHING : 'Searching…';
        list.appendChild(loading);
    }

    function renderLedgerSuggestions(ledgers) {
        var list = document.getElementById('ledgerAccountList');
        if (!list) return;
        list.innerHTML = '';
        if (!ledgers || ledgers.length === 0) {
            var empty = document.createElement('div');
            empty.className = 'ageing-ac-empty';
            empty.textContent = typeof window.AURAGOLD_AGEING_AC_EMPTY === 'string' ? window.AURAGOLD_AGEING_AC_EMPTY : 'No matching ledgers';
            list.appendChild(empty);
            return;
        }
        ledgers.forEach(function (row) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ageing-ac-item';
            btn.setAttribute('role', 'option');
            var nameEl = document.createElement('span');
            nameEl.className = 'ageing-ac-item-name';
            nameEl.textContent = row.name || '';
            btn.appendChild(nameEl);
            if (row.mobile_no) {
                var meta = document.createElement('span');
                meta.className = 'ageing-ac-item-meta';
                var ic = document.createElement('i');
                ic.className = 'feather icon-phone';
                ic.setAttribute('aria-hidden', 'true');
                meta.appendChild(ic);
                meta.appendChild(document.createTextNode(String(row.mobile_no)));
                btn.appendChild(meta);
            }
            btn.addEventListener('click', function () {
                selectLedgerRow(row);
            });
            list.appendChild(btn);
        });
    }

    function selectLedgerRow(row) {
        var inp = document.getElementById('ledgerAccountInput');
        var hid = document.getElementById('ledgerCustomerId');
        if (inp) {
            inp.value = row.name || '';
        }
        if (hid) {
            hid.value = row.id ? String(row.id) : '';
        }
        hideLedgerAcPanel();
    }

    function fetchLedgerSuggestions(q) {
        if (ledgerAcAbort) {
            ledgerAcAbort.abort();
        }
        var panel = document.getElementById('ledgerAccountPanel');
        var inp = document.getElementById('ledgerAccountInput');
        if (!panel || !inp) return;
        if ((q || '').trim().length < 1) {
            panel.hidden = true;
            inp.setAttribute('aria-expanded', 'false');
            return;
        }
        panel.hidden = false;
        inp.setAttribute('aria-expanded', 'true');
        showLedgerAcLoading();
        ledgerAcAbort = (typeof AbortController !== 'undefined') ? new AbortController() : null;
        var url = 'ajax/search-ledger-accounts.php?q=' + encodeURIComponent(q.trim());
        var opts = { credentials: 'same-origin' };
        if (ledgerAcAbort) {
            opts.signal = ledgerAcAbort.signal;
        }
        fetch(url, opts)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.status === 'success' && Array.isArray(data.ledgers)) {
                    renderLedgerSuggestions(data.ledgers);
                } else {
                    renderLedgerSuggestions([]);
                }
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') return;
                renderLedgerSuggestions([]);
            });
    }

    var ledgerAccountInput = document.getElementById('ledgerAccountInput');
    var ledgerAcWrap = document.getElementById('ledgerAcWrap');
    if (ledgerAccountInput) {
        ledgerAccountInput.addEventListener('input', function () {
            var hid = document.getElementById('ledgerCustomerId');
            if (hid) hid.value = '';
            clearTimeout(ledgerSearchTimer);
            clearTimeout(ledgerAccountFilterDebounce);
            var v = ledgerAccountInput.value || '';
            if (v.trim().length < 1) {
                hideLedgerAcPanel();
                ledgerAccountFilterDebounce = setTimeout(function () {
                    ledgerDataState.page = 1;
                    loadLedgerReport();
                }, 400);
                return;
            }
            ledgerSearchTimer = setTimeout(function () {
                fetchLedgerSuggestions(v);
            }, 280);
            ledgerAccountFilterDebounce = setTimeout(function () {
                ledgerDataState.page = 1;
                loadLedgerReport();
            }, 520);
        });
        ledgerAccountInput.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                hideLedgerAcPanel();
            }
        });
    }
    function hideStockProductPanel() {
        var panel = document.getElementById('stockProductPanel');
        var inp = document.getElementById('stockProductInput');
        if (panel) {
            panel.hidden = true;
        }
        if (inp) {
            inp.setAttribute('aria-expanded', 'false');
        }
    }

    function showStockProductLoading() {
        var list = document.getElementById('stockProductList');
        if (!list) return;
        list.innerHTML = '';
        var loading = document.createElement('div');
        loading.className = 'ageing-ac-loading';
        loading.textContent = typeof window.AURAGOLD_AGEING_AC_SEARCHING === 'string' ? window.AURAGOLD_AGEING_AC_SEARCHING : 'Searching…';
        list.appendChild(loading);
    }

    function renderStockProductSuggestions(items) {
        var list = document.getElementById('stockProductList');
        if (!list) return;
        list.innerHTML = '';
        if (!items || items.length === 0) {
            var empty = document.createElement('div');
            empty.className = 'ageing-ac-empty';
            empty.textContent = typeof window.AURAGOLD_AGEING_PRODUCT_AC_EMPTY === 'string' ? window.AURAGOLD_AGEING_PRODUCT_AC_EMPTY : 'No matching products';
            list.appendChild(empty);
            return;
        }
        items.forEach(function (row) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ageing-ac-item';
            btn.setAttribute('role', 'option');
            var nameEl = document.createElement('span');
            nameEl.className = 'ageing-ac-item-name';
            nameEl.textContent = row.name || '';
            btn.appendChild(nameEl);
            if (row.article) {
                var meta = document.createElement('span');
                meta.className = 'ageing-ac-item-meta';
                meta.textContent = String(row.article);
                btn.appendChild(meta);
            }
            btn.addEventListener('click', function () {
                selectStockProductRow(row);
            });
            list.appendChild(btn);
        });
    }

    function selectStockProductRow(row) {
        var inp = document.getElementById('stockProductInput');
        var hid = document.getElementById('stockProductId');
        if (inp) {
            inp.value = row.name || '';
        }
        if (hid) {
            hid.value = row.id ? String(row.id) : '';
        }
        hideStockProductPanel();
    }

    function fetchStockProductSuggestions(q) {
        if (stockProductAbort) {
            stockProductAbort.abort();
        }
        var panel = document.getElementById('stockProductPanel');
        var inp = document.getElementById('stockProductInput');
        if (!panel || !inp) return;
        panel.hidden = false;
        inp.setAttribute('aria-expanded', 'true');
        showStockProductLoading();
        stockProductAbort = (typeof AbortController !== 'undefined') ? new AbortController() : null;
        var url = 'ajax/search-ageing-stock-items.php?q=' + encodeURIComponent((q || '').trim());
        var opts = { credentials: 'same-origin' };
        if (stockProductAbort) {
            opts.signal = stockProductAbort.signal;
        }
        fetch(url, opts)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || data.status !== 'success' || !data.items) {
                    renderStockProductSuggestions([]);
                    return;
                }
                renderStockProductSuggestions(data.items);
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') return;
                renderStockProductSuggestions([]);
            });
    }

    document.addEventListener('click', function (e) {
        if (ledgerAcWrap && !ledgerAcWrap.contains(e.target)) {
            hideLedgerAcPanel();
        }
        var stockAcWrap = document.getElementById('stockProductAcWrap');
        if (stockAcWrap && !stockAcWrap.contains(e.target)) {
            hideStockProductPanel();
        }
    });

    var stockProductInput = document.getElementById('stockProductInput');
    if (stockProductInput) {
        stockProductInput.addEventListener('input', function () {
            var hid = document.getElementById('stockProductId');
            if (hid) hid.value = '';
            clearTimeout(stockProductSearchTimer);
            var v = stockProductInput.value || '';
            if (v.trim().length < 1) {
                hideStockProductPanel();
                return;
            }
            stockProductSearchTimer = setTimeout(function () {
                fetchStockProductSuggestions(v);
            }, 280);
        });
        stockProductInput.addEventListener('focus', function () {
            clearTimeout(stockProductSearchTimer);
            fetchStockProductSuggestions(stockProductInput.value || '');
        });
        stockProductInput.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                hideStockProductPanel();
            }
        });
    }

    function formatStockFooterCell(colKey, raw) {
        var isWt = colKey === 'gross_wt' || colKey === 'purity_wt' || colKey === 'net_wt' || colKey === 'final_wt';
        var t = String(raw == null ? '' : raw).replace(/,/g, '').trim();
        var n = parseFloat(t);
        if (isNaN(n)) n = 0;
        if (n === 0) return '0';
        if (isWt) return n.toFixed(2);
        if (colKey === 'qty' || colKey === 'age') return String(Math.round(n));
        return t === '' ? '0' : String(raw);
    }

    function setTab(tab) {
        activeTab = tab;
        document.querySelectorAll('.ageing-tab').forEach(function (b) {
            var on = b.getAttribute('data-tab') === tab;
            b.classList.toggle('active', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        document.querySelectorAll('.ageing-panel').forEach(function (p) {
            p.classList.toggle('hidden', p.getAttribute('data-panel') !== tab);
        });
        document.querySelectorAll('.ledger-only').forEach(function (el) {
            el.hidden = tab !== 'ledger';
        });
        document.querySelectorAll('.stock-only').forEach(function (el) {
            el.hidden = tab !== 'stock';
        });
        hideLedgerAcPanel();
        hideStockProductPanel();
    }

    document.querySelectorAll('.ageing-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setTab(btn.getAttribute('data-tab'));
        });
    });

    var LEDGER_COL_STORAGE_KEY = 'auragold_ageing_ledger_cols_v1';
    var LEDGER_COL_DEFAULT_ORDER = ['ledger', 'voucher', 'acct_no', 'invoice', 'date', 'd1', 'd2', 'd3', 'd4', 'd5', 'total'];
    var LEDGER_TXT_KEYS = ['ledger', 'voucher', 'acct_no', 'invoice', 'date'];
    var LEDGER_NUM_KEYS = ['d1', 'd2', 'd3', 'd4', 'd5', 'total'];
    var LEDGER_SUM_IDS = { d1: 'ledgerSum1', d2: 'ledgerSum2', d3: 'ledgerSum3', d4: 'ledgerSum4', d5: 'ledgerSum5', total: 'ledgerSumT' };

    var STOCK_COL_STORAGE_KEY = 'auragold_ageing_stock_cols_v1';
    var STOCK_COL_DEFAULT_ORDER = ['branch', 'carat', 'metal', 'product_code', 'rfid_code', 'barcode', 'qty', 'location', 'age', 'gross_wt', 'purity_wt', 'net_wt', 'final_wt', 'voucher_type', 'invoice_no'];
    var STOCK_SUM_IDS = { qty: 'stockSum1', gross_wt: 'stockSum2', purity_wt: 'stockSum3', net_wt: 'stockSum4', final_wt: 'stockSum5' };

    function getLedgerHeadOrder() {
        var row = document.getElementById('ledgerAgeingHeadRow');
        if (!row) return [];
        var out = [];
        row.querySelectorAll('th[data-col]').forEach(function (th) {
            out.push(th.getAttribute('data-col'));
        });
        return out;
    }

    function normalizeLedgerStoredOrder(arr) {
        if (!arr || !Array.isArray(arr) || arr.length !== LEDGER_COL_DEFAULT_ORDER.length) return null;
        var set = {};
        for (var i = 0; i < arr.length; i++) set[arr[i]] = true;
        for (var j = 0; j < LEDGER_COL_DEFAULT_ORDER.length; j++) {
            if (!set[LEDGER_COL_DEFAULT_ORDER[j]]) return null;
        }
        return arr.slice();
    }

    function toggleLedgerColHidden(key, hide) {
        document.querySelectorAll('#ledgerAgeingTable th[data-col="' + key + '"], #ledgerAgeingTable td[data-col="' + key + '"]').forEach(function (el) {
            el.classList.toggle('ageing-col-hidden', !!hide);
        });
    }

    function countLedgerVisibleCols() {
        return getLedgerHeadOrder().filter(function (k) {
            var th = document.querySelector('#ledgerAgeingHeadRow th[data-col="' + k + '"]');
            return th && !th.classList.contains('ageing-col-hidden');
        }).length;
    }

    function reorderLedgerHeaders(order) {
        var row = document.getElementById('ledgerAgeingHeadRow');
        if (!row) return;
        var map = {};
        row.querySelectorAll('th[data-col]').forEach(function (th) {
            map[th.getAttribute('data-col')] = th;
        });
        var frag = document.createDocumentFragment();
        order.forEach(function (k) {
            if (map[k]) frag.appendChild(map[k]);
        });
        row.appendChild(frag);
    }

    function reorderLedgerCellsInRow(tr, order) {
        if (tr.classList.contains('empty-row')) return;
        var map = {};
        tr.querySelectorAll('td[data-col]').forEach(function (td) {
            map[td.getAttribute('data-col')] = td;
        });
        if (Object.keys(map).length === 0) return;
        var frag = document.createDocumentFragment();
        order.forEach(function (k) {
            if (map[k]) frag.appendChild(map[k]);
        });
        tr.appendChild(frag);
    }

    function reorderLedgerColumns(order) {
        reorderLedgerHeaders(order);
        document.querySelectorAll('#ledgerAgeingTable tbody tr').forEach(function (tr) {
            reorderLedgerCellsInRow(tr, order);
        });
    }

    function rebuildLedgerFooter() {
        var footRow = document.getElementById('ledgerAgeingFootRow');
        if (!footRow) return;
        var saved = {};
        LEDGER_NUM_KEYS.forEach(function (k) {
            var id = LEDGER_SUM_IDS[k];
            var el = document.getElementById(id);
            if (el) saved[k] = el.textContent;
        });
        var order = getLedgerHeadOrder();
        var vis = order.filter(function (k) {
            var th = document.querySelector('#ledgerAgeingHeadRow th[data-col="' + k + '"]');
            return th && !th.classList.contains('ageing-col-hidden');
        });
        var textVis = vis.filter(function (k) { return LEDGER_TXT_KEYS.indexOf(k) >= 0; });
        var numVis = vis.filter(function (k) { return LEDGER_NUM_KEYS.indexOf(k) >= 0; });
        footRow.innerHTML = '';
        var tdLabel = document.createElement('td');
        tdLabel.colSpan = Math.max(1, textVis.length);
        tdLabel.className = 'totals-label';
        tdLabel.textContent = typeof window.AURAGOLD_AGEING_TOTAL_LBL === 'string' ? window.AURAGOLD_AGEING_TOTAL_LBL : 'Total';
        footRow.appendChild(tdLabel);
        numVis.forEach(function (k) {
            var td = document.createElement('td');
            td.className = 'th-num';
            td.setAttribute('data-col', k);
            td.id = LEDGER_SUM_IDS[k];
            td.textContent = saved.hasOwnProperty(k) ? saved[k] : '0';
            footRow.appendChild(td);
        });
    }

    function syncLedgerEmptyColspan() {
        var cell = document.getElementById('ledgerEmptyMsgCell');
        if (!cell) return;
        var n = document.querySelectorAll('#ledgerAgeingHeadRow th[data-col]:not(.ageing-col-hidden)').length;
        cell.colSpan = Math.max(1, n);
    }

    function saveLedgerColState() {
        var hidden = {};
        getLedgerHeadOrder().forEach(function (k) {
            var th = document.querySelector('#ledgerAgeingHeadRow th[data-col="' + k + '"]');
            if (th && th.classList.contains('ageing-col-hidden')) hidden[k] = true;
        });
        var widths = {};
        getLedgerHeadOrder().forEach(function (k) {
            var th = document.querySelector('#ledgerAgeingHeadRow th[data-col="' + k + '"]');
            if (th && th.style && th.style.width) widths[k] = th.style.width;
        });
        try {
            localStorage.setItem(LEDGER_COL_STORAGE_KEY, JSON.stringify({
                order: getLedgerHeadOrder(),
                hidden: hidden,
                widths: widths
            }));
        } catch (err) {}
    }

    function ledgerThPlainLabel(th) {
        var inner = th.querySelector('.ageing-col-head-inner');
        if (!inner) return (th.textContent || '').replace(/\s+/g, ' ').trim();
        var clone = inner.cloneNode(true);
        clone.querySelectorAll('.sort-icons, .th-gear-btn').forEach(function (n) { n.remove(); });
        return (clone.textContent || '').replace(/\s+/g, ' ').trim();
    }

    function openLedgerColModal() {
        var modal = document.getElementById('ledgerColSettingsModal');
        var list = document.getElementById('ledgerColSettingsList');
        if (!modal || !list) return;
        list.innerHTML = '';
        getLedgerHeadOrder().forEach(function (k) {
            var th = document.querySelector('#ledgerAgeingHeadRow th[data-col="' + k + '"]');
            if (!th) return;
            var li = document.createElement('li');
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.checked = !th.classList.contains('ageing-col-hidden');
            cb.setAttribute('data-col-key', k);
            cb.addEventListener('change', function () {
                var key = cb.getAttribute('data-col-key');
                if (!key) return;
                if (!cb.checked) {
                    if (countLedgerVisibleCols() === 1 && th && !th.classList.contains('ageing-col-hidden')) {
                        cb.checked = true;
                        return;
                    }
                    toggleLedgerColHidden(key, true);
                } else {
                    toggleLedgerColHidden(key, false);
                }
                rebuildLedgerFooter();
                syncLedgerEmptyColspan();
                saveLedgerColState();
            });
            var lab = document.createElement('label');
            lab.style.cursor = 'pointer';
            lab.style.flex = '1';
            lab.htmlFor = '';
            lab.textContent = ledgerThPlainLabel(th);
            lab.addEventListener('click', function (e) {
                e.preventDefault();
                cb.checked = !cb.checked;
                cb.dispatchEvent(new Event('change', { bubbles: true }));
            });
            li.appendChild(cb);
            li.appendChild(lab);
            list.appendChild(li);
        });
        modal.hidden = false;
    }

    function closeLedgerColModal() {
        var modal = document.getElementById('ledgerColSettingsModal');
        if (modal) modal.hidden = true;
    }

    function getStockHeadOrder() {
        var row = document.getElementById('stockAgeingHeadRow');
        if (!row) return [];
        var out = [];
        row.querySelectorAll('th[data-col]').forEach(function (th) {
            out.push(th.getAttribute('data-col'));
        });
        return out;
    }

    function normalizeStockStoredOrder(arr) {
        if (!arr || !Array.isArray(arr) || arr.length !== STOCK_COL_DEFAULT_ORDER.length) return null;
        var set = {};
        for (var si = 0; si < arr.length; si++) set[arr[si]] = true;
        for (var sj = 0; sj < STOCK_COL_DEFAULT_ORDER.length; sj++) {
            if (!set[STOCK_COL_DEFAULT_ORDER[sj]]) return null;
        }
        return arr.slice();
    }

    function toggleStockColHidden(key, hide) {
        document.querySelectorAll('#stockAgeingTable th[data-col="' + key + '"], #stockAgeingTable td[data-col="' + key + '"]').forEach(function (el) {
            el.classList.toggle('ageing-col-hidden', !!hide);
        });
    }

    function countStockVisibleCols() {
        return getStockHeadOrder().filter(function (k) {
            var th = document.querySelector('#stockAgeingHeadRow th[data-col="' + k + '"]');
            return th && !th.classList.contains('ageing-col-hidden');
        }).length;
    }

    function reorderStockHeaders(order) {
        var row = document.getElementById('stockAgeingHeadRow');
        if (!row) return;
        var map = {};
        row.querySelectorAll('th[data-col]').forEach(function (th) {
            map[th.getAttribute('data-col')] = th;
        });
        var frag = document.createDocumentFragment();
        order.forEach(function (k) {
            if (map[k]) frag.appendChild(map[k]);
        });
        row.appendChild(frag);
    }

    function reorderStockCellsInRow(tr, order) {
        if (tr.classList.contains('empty-row')) return;
        var map = {};
        tr.querySelectorAll('td[data-col]').forEach(function (td) {
            map[td.getAttribute('data-col')] = td;
        });
        if (Object.keys(map).length === 0) return;
        var frag = document.createDocumentFragment();
        order.forEach(function (k) {
            if (map[k]) frag.appendChild(map[k]);
        });
        tr.appendChild(frag);
    }

    function reorderStockColumns(order) {
        reorderStockHeaders(order);
        document.querySelectorAll('#stockAgeingTable tbody tr').forEach(function (tr) {
            reorderStockCellsInRow(tr, order);
        });
    }

    function rebuildStockFooter() {
        var footRow = document.getElementById('stockAgeingFootRow');
        if (!footRow) return;
        var saved = {};
        Object.keys(STOCK_SUM_IDS).forEach(function (k) {
            var id = STOCK_SUM_IDS[k];
            var el = document.getElementById(id);
            if (el) saved[k] = el.textContent;
        });
        var order = getStockHeadOrder();
        var vis = order.filter(function (k) {
            var th = document.querySelector('#stockAgeingHeadRow th[data-col="' + k + '"]');
            return th && !th.classList.contains('ageing-col-hidden');
        });
        var nonSum = vis.filter(function (k) { return !STOCK_SUM_IDS[k]; });
        var sumVis = vis.filter(function (k) { return STOCK_SUM_IDS[k]; });
        footRow.innerHTML = '';
        var tdLabel = document.createElement('td');
        tdLabel.colSpan = Math.max(1, nonSum.length);
        tdLabel.className = 'totals-label';
        tdLabel.textContent = typeof window.AURAGOLD_AGEING_TOTAL_LBL === 'string' ? window.AURAGOLD_AGEING_TOTAL_LBL : 'Total';
        footRow.appendChild(tdLabel);
        sumVis.forEach(function (k) {
            var td = document.createElement('td');
            td.className = 'th-num';
            td.setAttribute('data-col', k);
            td.id = STOCK_SUM_IDS[k];
            var prev = saved.hasOwnProperty(k) ? saved[k] : '0';
            td.textContent = formatStockFooterCell(k, prev);
            footRow.appendChild(td);
        });
    }

    function syncStockEmptyColspan() {
        var cell = document.getElementById('stockEmptyMsgCell');
        if (!cell) return;
        var n = document.querySelectorAll('#stockAgeingHeadRow th[data-col]:not(.ageing-col-hidden)').length;
        cell.colSpan = Math.max(1, n);
    }

    function saveStockColState() {
        var hidden = {};
        getStockHeadOrder().forEach(function (k) {
            var th = document.querySelector('#stockAgeingHeadRow th[data-col="' + k + '"]');
            if (th && th.classList.contains('ageing-col-hidden')) hidden[k] = true;
        });
        var widths = {};
        getStockHeadOrder().forEach(function (k) {
            var th = document.querySelector('#stockAgeingHeadRow th[data-col="' + k + '"]');
            if (th && th.style && th.style.width) widths[k] = th.style.width;
        });
        try {
            localStorage.setItem(STOCK_COL_STORAGE_KEY, JSON.stringify({
                order: getStockHeadOrder(),
                hidden: hidden,
                widths: widths
            }));
        } catch (errS) {}
    }

    function openStockColModal() {
        var modal = document.getElementById('stockColSettingsModal');
        var list = document.getElementById('stockColSettingsList');
        if (!modal || !list) return;
        list.innerHTML = '';
        getStockHeadOrder().forEach(function (k) {
            var th = document.querySelector('#stockAgeingHeadRow th[data-col="' + k + '"]');
            if (!th) return;
            var li = document.createElement('li');
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.checked = !th.classList.contains('ageing-col-hidden');
            cb.setAttribute('data-col-key', k);
            cb.addEventListener('change', function () {
                var key = cb.getAttribute('data-col-key');
                if (!key) return;
                if (!cb.checked) {
                    if (countStockVisibleCols() === 1 && th && !th.classList.contains('ageing-col-hidden')) {
                        cb.checked = true;
                        return;
                    }
                    toggleStockColHidden(key, true);
                } else {
                    toggleStockColHidden(key, false);
                }
                rebuildStockFooter();
                syncStockEmptyColspan();
                saveStockColState();
            });
            var lab = document.createElement('label');
            lab.style.cursor = 'pointer';
            lab.style.flex = '1';
            lab.htmlFor = '';
            lab.textContent = ledgerThPlainLabel(th);
            lab.addEventListener('click', function (e) {
                e.preventDefault();
                cb.checked = !cb.checked;
                cb.dispatchEvent(new Event('change', { bubbles: true }));
            });
            li.appendChild(cb);
            li.appendChild(lab);
            list.appendChild(li);
        });
        modal.hidden = false;
    }

    function closeStockColModal() {
        var modal = document.getElementById('stockColSettingsModal');
        if (modal) modal.hidden = true;
    }

    function initStockColumnManager() {
        var headRow = document.getElementById('stockAgeingHeadRow');
        if (!headRow) return;

        var stateS = {};
        try {
            var rawS = localStorage.getItem(STOCK_COL_STORAGE_KEY);
            if (rawS) stateS = JSON.parse(rawS) || {};
        } catch (eS1) {
            stateS = {};
        }

        var ordS = normalizeStockStoredOrder(stateS.order);
        if (ordS) reorderStockColumns(ordS);

        if (stateS.hidden && typeof stateS.hidden === 'object') {
            STOCK_COL_DEFAULT_ORDER.forEach(function (k) {
                if (stateS.hidden[k]) toggleStockColHidden(k, true);
            });
        }
        if (countStockVisibleCols() === 0) {
            STOCK_COL_DEFAULT_ORDER.forEach(function (k) { toggleStockColHidden(k, false); });
        }

        if (stateS.widths && typeof stateS.widths === 'object') {
            Object.keys(stateS.widths).forEach(function (k) {
                var th = document.querySelector('#stockAgeingHeadRow th[data-col="' + k + '"]');
                if (th && stateS.widths[k]) th.style.width = stateS.widths[k];
            });
        }

        rebuildStockFooter();
        syncStockEmptyColspan();

        headRow.querySelectorAll('th[data-col]').forEach(function (th) {
            var handle = th.querySelector('.ageing-col-drag');
            if (!handle) return;

            function clearDropHighlightsS() {
                headRow.querySelectorAll('.ageing-col-drop-target').forEach(function (x) {
                    x.classList.remove('ageing-col-drop-target');
                });
            }

            function thFromPointS(clientX, clientY) {
                var el = document.elementFromPoint(clientX, clientY);
                if (!el || !el.closest) return null;
                var t = el.closest('#stockAgeingHeadRow th[data-col]');
                return t || null;
            }

            handle.addEventListener('pointerdown', function (e) {
                if (e.button !== 0) return;
                var dragFromKey = th.getAttribute('data-col');
                if (!dragFromKey) return;
                e.preventDefault();
                th.classList.add('ageing-col-dragging');
                try {
                    handle.setPointerCapture(e.pointerId);
                } catch (errCapS) {}

                function onMoveS(ev) {
                    clearDropHighlightsS();
                    var over = thFromPointS(ev.clientX, ev.clientY);
                    if (over && over.getAttribute('data-col') !== dragFromKey) {
                        over.classList.add('ageing-col-drop-target');
                    }
                }

                function onEndS(ev) {
                    th.classList.remove('ageing-col-dragging');
                    clearDropHighlightsS();
                    try {
                        handle.releasePointerCapture(ev.pointerId);
                    } catch (errRelS) {}
                    handle.removeEventListener('pointermove', onMoveS);
                    handle.removeEventListener('pointerup', onEndS);
                    handle.removeEventListener('pointercancel', onEndS);

                    var over = thFromPointS(ev.clientX, ev.clientY);
                    var toKey = over && over.getAttribute('data-col');
                    if (!toKey || toKey === dragFromKey) return;
                    var order = getStockHeadOrder().slice();
                    var i = order.indexOf(dragFromKey);
                    var j = order.indexOf(toKey);
                    if (i < 0 || j < 0) return;
                    order.splice(i, 1);
                    order.splice(j, 0, dragFromKey);
                    reorderStockColumns(order);
                    saveStockColState();
                    rebuildStockFooter();
                    syncStockEmptyColspan();
                }

                handle.addEventListener('pointermove', onMoveS);
                handle.addEventListener('pointerup', onEndS);
                handle.addEventListener('pointercancel', onEndS);
            });

            var resizer = th.querySelector('.ageing-col-resizer');
            if (resizer) {
                resizer.addEventListener('mousedown', function (e) {
                    e.stopPropagation();
                    e.preventDefault();
                    var startX = e.clientX;
                    var startW = th.getBoundingClientRect().width;
                    function onMoveR(ev) {
                        var w = Math.max(48, startW + (ev.clientX - startX));
                        th.style.width = w + 'px';
                    }
                    function onUpR() {
                        document.removeEventListener('mousemove', onMoveR);
                        document.removeEventListener('mouseup', onUpR);
                        saveStockColState();
                    }
                    document.addEventListener('mousemove', onMoveR);
                    document.addEventListener('mouseup', onUpR);
                });
            }
        });

        var gearS = document.getElementById('stockColGearBtn');
        if (gearS) {
            gearS.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                toggleExportMenu(false);
                openStockColModal();
            });
        }
        var bdS = document.getElementById('stockColSettingsBackdrop');
        if (bdS) bdS.addEventListener('click', closeStockColModal);
        var cxS = document.getElementById('stockColSettingsCloseX');
        if (cxS) cxS.addEventListener('click', closeStockColModal);
        var cbS = document.getElementById('stockColSettingsCloseBtn');
        if (cbS) cbS.addEventListener('click', closeStockColModal);
        var rstS = document.getElementById('stockColSettingsReset');
        if (rstS) {
            rstS.addEventListener('click', function () {
                try { localStorage.removeItem(STOCK_COL_STORAGE_KEY); } catch (eS2) {}
                reorderStockColumns(STOCK_COL_DEFAULT_ORDER.slice());
                STOCK_COL_DEFAULT_ORDER.forEach(function (k) { toggleStockColHidden(k, false); });
                headRow.querySelectorAll('th[data-col]').forEach(function (th) { th.style.width = ''; });
                rebuildStockFooter();
                syncStockEmptyColspan();
                closeStockColModal();
            });
        }
    }

    function initLedgerColumnManager() {
        var headRow = document.getElementById('ledgerAgeingHeadRow');
        if (!headRow) return;

        var state = {};
        try {
            var raw = localStorage.getItem(LEDGER_COL_STORAGE_KEY);
            if (raw) state = JSON.parse(raw) || {};
        } catch (e1) {
            state = {};
        }

        var ord = normalizeLedgerStoredOrder(state.order);
        if (ord) reorderLedgerColumns(ord);

        if (state.hidden && typeof state.hidden === 'object') {
            LEDGER_COL_DEFAULT_ORDER.forEach(function (k) {
                if (state.hidden[k]) toggleLedgerColHidden(k, true);
            });
        }
        if (countLedgerVisibleCols() === 0) {
            LEDGER_COL_DEFAULT_ORDER.forEach(function (k) { toggleLedgerColHidden(k, false); });
        }

        if (state.widths && typeof state.widths === 'object') {
            Object.keys(state.widths).forEach(function (k) {
                var th = document.querySelector('#ledgerAgeingHeadRow th[data-col="' + k + '"]');
                if (th && state.widths[k]) th.style.width = state.widths[k];
            });
        }

        rebuildLedgerFooter();
        syncLedgerEmptyColspan();

        headRow.querySelectorAll('th[data-col]').forEach(function (th) {
            var handle = th.querySelector('.ageing-col-drag');
            if (!handle) return;

            function clearDropHighlights() {
                headRow.querySelectorAll('.ageing-col-drop-target').forEach(function (x) {
                    x.classList.remove('ageing-col-drop-target');
                });
            }

            function thFromPoint(clientX, clientY) {
                var el = document.elementFromPoint(clientX, clientY);
                if (!el || !el.closest) return null;
                var t = el.closest('#ledgerAgeingHeadRow th[data-col]');
                return t || null;
            }

            handle.addEventListener('pointerdown', function (e) {
                if (e.button !== 0) return;
                var dragFromKey = th.getAttribute('data-col');
                if (!dragFromKey) return;
                e.preventDefault();
                th.classList.add('ageing-col-dragging');
                try {
                    handle.setPointerCapture(e.pointerId);
                } catch (errCap) {}

                function onMove(ev) {
                    clearDropHighlights();
                    var over = thFromPoint(ev.clientX, ev.clientY);
                    if (over && over.getAttribute('data-col') !== dragFromKey) {
                        over.classList.add('ageing-col-drop-target');
                    }
                }

                function onEnd(ev) {
                    th.classList.remove('ageing-col-dragging');
                    clearDropHighlights();
                    try {
                        handle.releasePointerCapture(ev.pointerId);
                    } catch (errRel) {}
                    handle.removeEventListener('pointermove', onMove);
                    handle.removeEventListener('pointerup', onEnd);
                    handle.removeEventListener('pointercancel', onEnd);

                    var over = thFromPoint(ev.clientX, ev.clientY);
                    var toKey = over && over.getAttribute('data-col');
                    if (!toKey || toKey === dragFromKey) return;
                    var order = getLedgerHeadOrder().slice();
                    var i = order.indexOf(dragFromKey);
                    var j = order.indexOf(toKey);
                    if (i < 0 || j < 0) return;
                    order.splice(i, 1);
                    order.splice(j, 0, dragFromKey);
                    reorderLedgerColumns(order);
                    saveLedgerColState();
                    rebuildLedgerFooter();
                    syncLedgerEmptyColspan();
                }

                handle.addEventListener('pointermove', onMove);
                handle.addEventListener('pointerup', onEnd);
                handle.addEventListener('pointercancel', onEnd);
            });

            var resizer = th.querySelector('.ageing-col-resizer');
            if (resizer) {
                resizer.addEventListener('mousedown', function (e) {
                    e.stopPropagation();
                    e.preventDefault();
                    var startX = e.clientX;
                    var startW = th.getBoundingClientRect().width;
                    function onMove(ev) {
                        var w = Math.max(48, startW + (ev.clientX - startX));
                        th.style.width = w + 'px';
                    }
                    function onUp() {
                        document.removeEventListener('mousemove', onMove);
                        document.removeEventListener('mouseup', onUp);
                        saveLedgerColState();
                    }
                    document.addEventListener('mousemove', onMove);
                    document.addEventListener('mouseup', onUp);
                });
            }
        });

        var gear = document.getElementById('ledgerColGearBtn');
        if (gear) {
            gear.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                toggleExportMenu(false);
                openLedgerColModal();
            });
        }
        var bd = document.getElementById('ledgerColSettingsBackdrop');
        if (bd) bd.addEventListener('click', closeLedgerColModal);
        var cx = document.getElementById('ledgerColSettingsCloseX');
        if (cx) cx.addEventListener('click', closeLedgerColModal);
        var cb = document.getElementById('ledgerColSettingsCloseBtn');
        if (cb) cb.addEventListener('click', closeLedgerColModal);
        var rst = document.getElementById('ledgerColSettingsReset');
        if (rst) {
            rst.addEventListener('click', function () {
                try { localStorage.removeItem(LEDGER_COL_STORAGE_KEY); } catch (e2) {}
                reorderLedgerColumns(LEDGER_COL_DEFAULT_ORDER.slice());
                LEDGER_COL_DEFAULT_ORDER.forEach(function (k) { toggleLedgerColHidden(k, false); });
                headRow.querySelectorAll('th[data-col]').forEach(function (th) { th.style.width = ''; });
                rebuildLedgerFooter();
                syncLedgerEmptyColspan();
                closeLedgerColModal();
            });
        }
        document.addEventListener('keydown', function (ev) {
            if (ev.key !== 'Escape') return;
            var m = document.getElementById('ledgerColSettingsModal');
            if (m && !m.hidden) closeLedgerColModal();
            var ms = document.getElementById('stockColSettingsModal');
            if (ms && !ms.hidden) closeStockColModal();
        });
    }

    initLedgerColumnManager();
    initStockColumnManager();

    var ledgerDataState = { page: 1, totalPages: 1, total: 0, loading: false };
    var stockDataState = { page: 1, totalPages: 1, total: 0, loading: false };
    var ledgerSearchDebounceTimer = null;
    var stockSearchDebounceTimer = null;

    function fmtMoneyCells(n) {
        var x = typeof n === 'number' ? n : parseFloat(String(n == null ? '' : n).replace(/,/g, ''));
        if (isNaN(x)) {
            x = 0;
        }
        try {
            return x.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        } catch (eLc) {
            return x.toFixed(2);
        }
    }

    function formatLedgerDisplayDate(iso) {
        if (!iso) return '';
        var p = String(iso).split(/[T\s\-]/);
        if (p.length >= 3) {
            var y = parseInt(p[0], 10);
            var m = parseInt(p[1], 10);
            var d = parseInt(p[2], 10);
            if (!isNaN(y) && !isNaN(m) && !isNaN(d)) {
                return (d < 10 ? '0' : '') + d + '-' + (m < 10 ? '0' : '') + m + '-' + y;
            }
        }
        return String(iso);
    }

    function getAgingDateValue() {
        var el = document.getElementById('agingDateInput');
        var v = el ? String(el.value || '').trim() : '';
        if (!v || !/^\d{4}-\d{2}-\d{2}$/.test(v)) {
            return null;
        }
        return v;
    }

    function applyLedgerTotals(t) {
        if (!t) {
            t = { d1: 0, d2: 0, d3: 0, d4: 0, d5: 0, total: 0 };
        }
        var map = { d1: 'ledgerSum1', d2: 'ledgerSum2', d3: 'ledgerSum3', d4: 'ledgerSum4', d5: 'ledgerSum5', total: 'ledgerSumT' };
        Object.keys(map).forEach(function (k) {
            var el = document.getElementById(map[k]);
            if (el) {
                el.textContent = fmtMoneyCells(t[k] != null ? t[k] : 0);
            }
        });
    }

    function applyStockTotals(t) {
        if (!t) {
            t = {};
        }
        var keys = ['qty', 'gross_wt', 'purity_wt', 'net_wt', 'final_wt'];
        keys.forEach(function (k) {
            var id = STOCK_SUM_IDS[k];
            var el = id ? document.getElementById(id) : null;
            if (el) {
                el.textContent = formatStockFooterCell(k, t[k] != null ? String(t[k]) : '0');
            }
        });
    }

    function renderLedgerBody(rows) {
        var tbody = document.getElementById('ledgerAgeingBody');
        if (!tbody) return;
        var order = getLedgerHeadOrder().slice();
        var msg = typeof window.AURAGOLD_AGEING_NO_ROWS === 'string' ? window.AURAGOLD_AGEING_NO_ROWS : 'No Rows To Show';
        var visCount = document.querySelectorAll('#ledgerAgeingHeadRow th[data-col]:not(.ageing-col-hidden)').length || order.length || 11;
        if (!rows || rows.length === 0) {
            tbody.innerHTML = '<tr class="empty-row"><td colspan="' + Math.max(1, visCount) + '" class="empty-msg" id="ledgerEmptyMsgCell">' + msg + '</td></tr>';
            rebuildLedgerFooter();
            syncLedgerEmptyColspan();
            return;
        }
        tbody.innerHTML = '';
        rows.forEach(function (r) {
            var tr = document.createElement('tr');
            order.forEach(function (k) {
                var td = document.createElement('td');
                td.setAttribute('data-col', k);
                var th = document.querySelector('#ledgerAgeingHeadRow th[data-col="' + k + '"]');
                if (th && th.classList.contains('ageing-col-hidden')) {
                    td.classList.add('ageing-col-hidden');
                }
                var txt = '';
                if (k === 'ledger') {
                    txt = r.ledger || '';
                } else if (k === 'voucher') {
                    txt = r.voucher || '';
                } else if (k === 'acct_no') {
                    txt = r.acct_no != null ? String(r.acct_no) : '';
                } else if (k === 'invoice') {
                    txt = r.invoice || '';
                } else if (k === 'date') {
                    txt = formatLedgerDisplayDate(r.date || '');
                } else if (k === 'd1' || k === 'd2' || k === 'd3' || k === 'd4' || k === 'd5' || k === 'total') {
                    txt = fmtMoneyCells(r[k] != null ? r[k] : 0);
                    td.className = 'th-num';
                }
                td.textContent = txt;
                tr.appendChild(td);
            });
            reorderLedgerCellsInRow(tr, order);
            tbody.appendChild(tr);
        });
        rebuildLedgerFooter();
        syncLedgerEmptyColspan();
    }

    function renderStockBody(rows) {
        var tbody = document.getElementById('stockAgeingBody');
        if (!tbody) return;
        var order = getStockHeadOrder().slice();
        var msg = typeof window.AURAGOLD_AGEING_NO_ROWS === 'string' ? window.AURAGOLD_AGEING_NO_ROWS : 'No Rows To Show';
        var visCount = document.querySelectorAll('#stockAgeingHeadRow th[data-col]:not(.ageing-col-hidden)').length || order.length || 15;
        if (!rows || rows.length === 0) {
            tbody.innerHTML = '<tr class="empty-row"><td colspan="' + Math.max(1, visCount) + '" class="empty-msg" id="stockEmptyMsgCell">' + msg + '</td></tr>';
            rebuildStockFooter();
            syncStockEmptyColspan();
            applyStockTotals({ qty: 0, gross_wt: 0, purity_wt: 0, net_wt: 0, final_wt: 0 });
            return;
        }
        tbody.innerHTML = '';
        rows.forEach(function (r) {
            var tr = document.createElement('tr');
            order.forEach(function (k) {
                var td = document.createElement('td');
                td.setAttribute('data-col', k);
                var th = document.querySelector('#stockAgeingHeadRow th[data-col="' + k + '"]');
                if (th && th.classList.contains('ageing-col-hidden')) {
                    td.classList.add('ageing-col-hidden');
                }
                var txt = '';
                if (k === 'branch') {
                    txt = r.branch || '';
                } else if (k === 'carat') {
                    txt = r.carat != null ? String(r.carat) : '';
                } else if (k === 'metal') {
                    txt = r.metal || '';
                } else if (k === 'product_code') {
                    txt = r.product_code || '';
                } else if (k === 'rfid_code') {
                    txt = r.rfid_code || '';
                } else if (k === 'barcode') {
                    txt = r.barcode || '';
                } else if (k === 'qty') {
                    txt = r.qty != null ? String(r.qty) : '0';
                    td.className = 'th-num';
                } else if (k === 'location') {
                    txt = r.location || '';
                } else if (k === 'age') {
                    txt = r.age != null ? String(r.age) : '0';
                    td.className = 'th-num';
                } else if (k === 'gross_wt' || k === 'purity_wt' || k === 'net_wt' || k === 'final_wt') {
                    var wv = r[k];
                    txt = wv != null ? String(wv) : '0';
                    td.className = 'th-num';
                } else if (k === 'voucher_type') {
                    txt = r.voucher_type || '';
                } else if (k === 'invoice_no') {
                    txt = r.invoice_no || '';
                }
                td.textContent = txt;
                tr.appendChild(td);
            });
            reorderStockCellsInRow(tr, order);
            tbody.appendChild(tr);
        });
        rebuildStockFooter();
        syncStockEmptyColspan();
    }

    function updateLedgerPager() {
        var info = document.getElementById('ledgerPaginationInfo');
        var perEl = document.getElementById('ledgerPerPage');
        var per = perEl ? parseInt(perEl.value, 10) : 25;
        var start = ledgerDataState.total === 0 ? 0 : (ledgerDataState.page - 1) * (per === 0 ? ledgerDataState.total : per) + 1;
        var end = ledgerDataState.total === 0 ? 0 : Math.min(ledgerDataState.page * (per === 0 ? ledgerDataState.total : per), ledgerDataState.total);
        if (per === 0) {
            start = ledgerDataState.total === 0 ? 0 : 1;
            end = ledgerDataState.total;
        }
        if (info) {
            info.textContent = 'Showing ' + start + ' to ' + end + ' of ' + ledgerDataState.total + ' entries';
        }
        var nav = document.querySelector('.ageing-panel[data-panel="ledger"] .ageing-pager');
        if (!nav) return;
        var atFirst = ledgerDataState.page <= 1;
        var atLast = ledgerDataState.page >= ledgerDataState.totalPages;
        var b0 = nav.querySelector('[data-go="first"]');
        var b1 = nav.querySelector('[data-go="prev"]');
        var b2 = nav.querySelector('[data-go="next"]');
        var b3 = nav.querySelector('[data-go="last"]');
        if (b0) b0.disabled = atFirst;
        if (b1) b1.disabled = atFirst;
        if (b2) b2.disabled = atLast || ledgerDataState.total === 0;
        if (b3) b3.disabled = atLast || ledgerDataState.total === 0;
    }

    function updateStockPager() {
        var info = document.getElementById('stockPaginationInfo');
        var perEl = document.getElementById('stockPerPage');
        var per = perEl ? parseInt(perEl.value, 10) : 25;
        var start = stockDataState.total === 0 ? 0 : (stockDataState.page - 1) * (per === 0 ? stockDataState.total : per) + 1;
        var end = stockDataState.total === 0 ? 0 : Math.min(stockDataState.page * (per === 0 ? stockDataState.total : per), stockDataState.total);
        if (per === 0) {
            start = stockDataState.total === 0 ? 0 : 1;
            end = stockDataState.total;
        }
        if (info) {
            info.textContent = 'Showing ' + start + ' to ' + end + ' of ' + stockDataState.total + ' entries';
        }
        var nav = document.querySelector('.ageing-panel[data-panel="stock"] .ageing-pager');
        if (!nav) return;
        var atFirst = stockDataState.page <= 1;
        var atLast = stockDataState.page >= stockDataState.totalPages;
        var b0 = nav.querySelector('[data-go="first"]');
        var b1 = nav.querySelector('[data-go="prev"]');
        var b2 = nav.querySelector('[data-go="next"]');
        var b3 = nav.querySelector('[data-go="last"]');
        if (b0) b0.disabled = atFirst;
        if (b1) b1.disabled = atFirst;
        if (b2) b2.disabled = atLast || stockDataState.total === 0;
        if (b3) b3.disabled = atLast || stockDataState.total === 0;
    }

    function buildLedgerQuery() {
        var ag = getAgingDateValue();
        if (!ag) {
            return null;
        }
        var q = new URLSearchParams();
        q.set('tab', 'ledger');
        q.set('aging_date', ag);
        q.set('page', String(ledgerDataState.page));
        var pp = document.getElementById('ledgerPerPage');
        var perv = pp ? pp.value : '25';
        q.set('per_page', perv === '0' ? 'all' : perv);
        var pr = document.querySelector('input[name="pr_type"]:checked');
        q.set('pr_type', pr ? pr.value : 'payable');
        var vl = document.querySelector('input[name="vl_wise"]:checked');
        q.set('vl_wise', vl ? vl.value : 'voucher');
        var hid = document.getElementById('ledgerCustomerId');
        if (hid && hid.value) {
            q.set('ledger_customer_id', hid.value);
        }
        var lacInp = document.getElementById('ledgerAccountInput');
        if (lacInp && lacInp.value.trim()) {
            q.set('account_ledger', lacInp.value.trim());
        }
        var sch = document.getElementById('ledgerTableSearch');
        if (sch && sch.value.trim()) {
            q.set('search', sch.value.trim());
        }
        return q;
    }

    function buildStockQuery() {
        var ag = getAgingDateValue();
        if (!ag) {
            return null;
        }
        var q = new URLSearchParams();
        q.set('tab', 'stock');
        q.set('aging_date', ag);
        q.set('page', String(stockDataState.page));
        var pp = document.getElementById('stockPerPage');
        var perv = pp ? pp.value : '25';
        q.set('per_page', perv === '0' ? 'all' : perv);
        var spid = document.getElementById('stockProductId');
        if (spid && spid.value) {
            q.set('stock_product_id', spid.value);
        }
        var sch = document.getElementById('stockTableSearch');
        if (sch && sch.value.trim()) {
            q.set('search', sch.value.trim());
        }
        return q;
    }

    function loadLedgerReport() {
        if (ledgerDataState.loading) {
            return;
        }
        var q = buildLedgerQuery();
        if (!q) {
            renderLedgerBody([]);
            applyLedgerTotals({});
            ledgerDataState.total = 0;
            ledgerDataState.totalPages = 1;
            ledgerDataState.page = 1;
            updateLedgerPager();
            return;
        }
        ledgerDataState.loading = true;
        var url = 'ajax/get-ageing-report.php?' + q.toString();
        fetch(url, { credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (j) {
                ledgerDataState.loading = false;
                if (!j || j.status !== 'success') {
                    renderLedgerBody([]);
                    applyLedgerTotals({});
                    ledgerDataState.total = 0;
                    ledgerDataState.totalPages = 1;
                    updateLedgerPager();
                    return;
                }
                var pag = j.pagination || {};
                ledgerDataState.total = parseInt(pag.total, 10) || 0;
                ledgerDataState.totalPages = parseInt(pag.total_pages, 10) || 1;
                ledgerDataState.page = parseInt(pag.current_page, 10) || 1;
                renderLedgerBody(j.data || []);
                applyLedgerTotals(j.totals || {});
                updateLedgerPager();
            })
            .catch(function () {
                ledgerDataState.loading = false;
                renderLedgerBody([]);
                applyLedgerTotals({});
                ledgerDataState.total = 0;
                ledgerDataState.totalPages = 1;
                updateLedgerPager();
            });
    }

    function loadStockReport() {
        if (stockDataState.loading) {
            return;
        }
        var q = buildStockQuery();
        if (!q) {
            renderStockBody([]);
            stockDataState.total = 0;
            stockDataState.totalPages = 1;
            stockDataState.page = 1;
            updateStockPager();
            return;
        }
        stockDataState.loading = true;
        var url = 'ajax/get-ageing-report.php?' + q.toString();
        fetch(url, { credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (j) {
                stockDataState.loading = false;
                if (!j || j.status !== 'success') {
                    renderStockBody([]);
                    stockDataState.total = 0;
                    stockDataState.totalPages = 1;
                    updateStockPager();
                    return;
                }
                var pag = j.pagination || {};
                stockDataState.total = parseInt(pag.total, 10) || 0;
                stockDataState.totalPages = parseInt(pag.total_pages, 10) || 1;
                stockDataState.page = parseInt(pag.current_page, 10) || 1;
                renderStockBody(j.data || []);
                applyStockTotals(j.totals || {});
                updateStockPager();
            })
            .catch(function () {
                stockDataState.loading = false;
                renderStockBody([]);
                stockDataState.total = 0;
                stockDataState.totalPages = 1;
                updateStockPager();
            });
    }

    function goLedgerPage(where) {
        if (where === 'first') ledgerDataState.page = 1;
        else if (where === 'prev') ledgerDataState.page = Math.max(1, ledgerDataState.page - 1);
        else if (where === 'next') ledgerDataState.page = Math.min(ledgerDataState.totalPages, ledgerDataState.page + 1);
        else if (where === 'last') ledgerDataState.page = ledgerDataState.totalPages;
        loadLedgerReport();
    }

    function goStockPage(where) {
        if (where === 'first') stockDataState.page = 1;
        else if (where === 'prev') stockDataState.page = Math.max(1, stockDataState.page - 1);
        else if (where === 'next') stockDataState.page = Math.min(stockDataState.totalPages, stockDataState.page + 1);
        else if (where === 'last') stockDataState.page = stockDataState.totalPages;
        loadStockReport();
    }

    function refreshReport() {
        var lac = document.getElementById('ledgerAccountInput');
        var lid = document.getElementById('ledgerCustomerId');
        if (lac) lac.value = '';
        if (lid) lid.value = '';
        hideLedgerAcPanel();
        var spi = document.getElementById('stockProductInput');
        var spid = document.getElementById('stockProductId');
        if (spi) spi.value = '';
        if (spid) spid.value = '';
        hideStockProductPanel();
        var lsch = document.getElementById('ledgerTableSearch');
        if (lsch) lsch.value = '';
        var ssch = document.getElementById('stockTableSearch');
        if (ssch) ssch.value = '';
        ledgerDataState.page = 1;
        stockDataState.page = 1;
        loadLedgerReport();
        loadStockReport();
    }

    var btnToolbarRefresh = document.getElementById('btnToolbarRefresh');
    if (btnToolbarRefresh) btnToolbarRefresh.addEventListener('click', refreshReport);

    var agingInp = document.getElementById('agingDateInput');
    if (agingInp) {
        agingInp.addEventListener('change', function () {
            ledgerDataState.page = 1;
            stockDataState.page = 1;
            loadLedgerReport();
            loadStockReport();
        });
    }

    document.querySelectorAll('input[name="pr_type"]').forEach(function (r) {
        r.addEventListener('change', function () {
            ledgerDataState.page = 1;
            loadLedgerReport();
        });
    });
    document.querySelectorAll('input[name="vl_wise"]').forEach(function (r) {
        r.addEventListener('change', function () {
            ledgerDataState.page = 1;
            loadLedgerReport();
        });
    });

    var lpp = document.getElementById('ledgerPerPage');
    if (lpp) {
        lpp.addEventListener('change', function () {
            ledgerDataState.page = 1;
            loadLedgerReport();
        });
    }
    var spp = document.getElementById('stockPerPage');
    if (spp) {
        spp.addEventListener('change', function () {
            stockDataState.page = 1;
            loadStockReport();
        });
    }

    var lschInp = document.getElementById('ledgerTableSearch');
    if (lschInp) {
        lschInp.addEventListener('input', function () {
            clearTimeout(ledgerSearchDebounceTimer);
            ledgerSearchDebounceTimer = setTimeout(function () {
                ledgerDataState.page = 1;
                loadLedgerReport();
            }, 400);
        });
    }
    var sschInp = document.getElementById('stockTableSearch');
    if (sschInp) {
        sschInp.addEventListener('input', function () {
            clearTimeout(stockSearchDebounceTimer);
            stockSearchDebounceTimer = setTimeout(function () {
                stockDataState.page = 1;
                loadStockReport();
            }, 400);
        });
    }

    var ledgerPagerNav = document.querySelector('.ageing-panel[data-panel="ledger"] .ageing-pager');
    if (ledgerPagerNav) {
        ledgerPagerNav.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-go]');
            if (!btn || btn.disabled) return;
            goLedgerPage(btn.getAttribute('data-go'));
        });
    }
    var stockPagerNav = document.querySelector('.ageing-panel[data-panel="stock"] .ageing-pager');
    if (stockPagerNav) {
        stockPagerNav.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-go]');
            if (!btn || btn.disabled) return;
            goStockPage(btn.getAttribute('data-go'));
        });
    }

    var origSetTab = setTab;
    setTab = function (tab) {
        origSetTab(tab);
        if (tab === 'ledger') {
            loadLedgerReport();
        } else if (tab === 'stock') {
            loadStockReport();
        }
    };

    var _selLed = selectLedgerRow;
    selectLedgerRow = function (row) {
        _selLed(row);
        ledgerDataState.page = 1;
        loadLedgerReport();
    };
    var _selStk = selectStockProductRow;
    selectStockProductRow = function (row) {
        _selStk(row);
        stockDataState.page = 1;
        loadStockReport();
    };

    var btnAgingDateResetEl = document.getElementById('btnAgingDateReset');
    if (btnAgingDateResetEl) {
        btnAgingDateResetEl.addEventListener('click', function () {
            var d = new Date();
            var iso = d.toISOString().slice(0, 10);
            var agingInput = document.getElementById('agingDateInput');
            if (agingInput) agingInput.value = iso;
            ledgerDataState.page = 1;
            stockDataState.page = 1;
            loadLedgerReport();
            loadStockReport();
        });
    }

    loadLedgerReport();
    updateStockPager();
})();
</script>
