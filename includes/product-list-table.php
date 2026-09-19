<?php
/**
 * Renders the shared Product List card (settings, table, footer).
 *
 * Before include, set:
 *   $product_list_prefs_page (required) — unique key for ajax/get-column-preferences.php
 *     e.g. 'sale-invoice-product-table', 'purchase-invoice-product-table'
 *
 * Optional:
 *   $product_list_grand_total_column — data-column key for "Grand Total:" label (default: 'product')
 */
if (!isset($product_list_prefs_page) || $product_list_prefs_page === '') {
    $product_list_prefs_page = 'shared-product-table';
}
if (!isset($product_list_grand_total_column)) {
    $product_list_grand_total_column = 'product';
}
if (!isset($product_list_no_image_src)) {
    $product_list_no_image_src = 'no_image.jpg';
}

require_once __DIR__ . '/product-list-table-columns-data.php';

$productListColumns = $product_list_table_columns;
?>
<link rel="stylesheet" href="assets/css/product-list-invoice-layout.css?v=5">
<!-- Product List Table (shared: admin/includes/product-list-table.php) -->
<div class="card mb-4 product-list-card">
    <div class="card-body product-list-card-body">
        <div class="table-header-wrapper">
            <h6 style="margin: 0; font-size: 0.9rem; font-weight: 700; color: #1e293b;">Product List <span class="text-muted" style="font-size: 0.75rem; font-weight: normal;">— Drag column headers to reorder; order is saved</span></h6>
            <div class="table-settings-wrapper">
                <button class="table-settings-btn" id="tableSettingsBtn" type="button">
                    <i class="feather icon-settings"></i>
                </button>
                <div class="table-settings-dropdown pl-column-picker" id="tableSettingsDropdown">
                    <div class="table-settings-dropdown-head">
                        <div class="pl-column-picker-title-row">
                            <div class="pl-column-picker-title-main">
                                <button type="button" class="pl-column-picker-mini-btn" data-pl-action="clear-search" title="Clear search">X</button>
                                <button type="button" class="pl-column-picker-mini-btn" data-pl-action="pick-all" title="Show all columns">P</button>
                                <span class="pl-column-picker-title-icon" aria-hidden="true"><i class="feather icon-settings"></i></span>
                                <span class="pl-column-picker-title-text">Columns</span>
                            </div>
                            <button type="button" class="pl-column-picker-close" aria-label="Close">&times;</button>
                        </div>
                        <div class="table-settings-search">
                            <input type="text" id="tableSettingsSearch" class="pl-column-picker-search" placeholder="Search" autocomplete="off">
                        </div>
                    </div>
                    <div class="table-settings-dropdown-body">
                    <?php
                    $prevPlGroup = null;
                    foreach ($productListColumns as $col):
                        $g = $col[2] ?? '';
                        if ($g !== '' && $g !== $prevPlGroup) {
                            $gt = $product_list_table_group_labels[$g] ?? $g;
                            $gid = 'pl-grp-' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $g);
                            echo '<div class="table-settings-section-title table-settings-group-header">';
                            echo '<input type="checkbox" class="table-settings-group-checkbox" id="' . htmlspecialchars($gid) . '" data-pl-group-toggle="' . htmlspecialchars($g) . '" checked title="Show or hide all columns in this group">';
                            echo '<label for="' . htmlspecialchars($gid) . '" class="table-settings-group-title-label">' . htmlspecialchars($gt) . '</label>';
                            echo '</div>';
                            $prevPlGroup = $g;
                        }
                    ?>
                    <div class="table-settings-item" data-pl-group="<?php echo htmlspecialchars($g); ?>">
                        <input type="checkbox" id="col-<?php echo htmlspecialchars($col[0]); ?>" data-column="<?php echo htmlspecialchars($col[0]); ?>" checked>
                        <label for="col-<?php echo htmlspecialchars($col[0]); ?>"><?php echo htmlspecialchars($col[1]); ?></label>
                    </div>
                    <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="table-responsive product-list-table-responsive">
            <table class="table table-bordered product-table">
                <thead>
                    <tr class="product-table-header-columns">
                        <?php
                        $prevThGroup = null;
                        foreach ($productListColumns as $col):
                            $gth = $col[2] ?? '';
                            $gStart = ($gth !== '' && $gth !== $prevThGroup) ? ' product-col-group-start' : '';
                            $prevThGroup = $gth;
                        ?>
                        <th class="draggable-column<?php echo $gStart; ?>" data-column="<?php echo htmlspecialchars($col[0]); ?>" data-pl-group="<?php echo htmlspecialchars($gth); ?>"<?php echo $col[0] === 'photo' ? ' style="min-width: 70px;"' : ''; ?> title="Drag to reorder columns"><?php echo htmlspecialchars($col[1]); ?></th>
                        <?php endforeach; ?>
                        <th style="width: 80px; text-align: center;">
                            <i class="feather icon-settings" style="cursor: pointer;"></i>
                        </th>
                    </tr>
                </thead>
                <tbody id="productTableBody">
                    <tr class="no-drag">
                        <td colspan="<?php echo count($productListColumns) + 1; ?>" class="text-center text-muted py-4" id="emptyRowCell">No Rows To Show</td>
                    </tr>
                </tbody>
                <tfoot id="productTableFooter" style="display: none;">
                    <tr style="background: #f8fafc; font-weight: 600;">
                        <?php
                        $footerIdByCol = ['quantity'=>'footerQuantity','gross-wt'=>'footerGrossWt','less-wt'=>'footerLessWt','purity'=>'footerPurity','final-wt'=>'footerFinalWt','net-wt'=>'footerNetWt','purity-wt'=>'footerPureWt','metal-weight'=>'footerMetalWeight','wastage-wt'=>'footerWastageWt','alloy-wt'=>'footerAlloyWt','making-amount'=>'footerMakingAmount','stone-amount'=>'footerStoneCharges','other-amount'=>'footerOtherCharges','diamond-amount'=>'footerDiamondValue','rate'=>'footerRate','metal-value'=>'footerMetalValue','discount'=>'footerDiscount','purchase-amount'=>'footerPurchaseAmount','sale-amount'=>'footerSaleAmount','sale-amount-with'=>'footerSaleAmountWith','reverse'=>'footerReverse','tax'=>'footerTax','amount'=>'footerAmount','net-amt'=>'footerNetAmt','net-amt-tax'=>'footerNetAmtTax'];
                        $prevFtGroup = null;
                        foreach ($productListColumns as $col):
                            $fid = isset($footerIdByCol[$col[0]]) ? $footerIdByCol[$col[0]] : '';
                            $isFirst = ($col[0] === $product_list_grand_total_column);
                            $gf = $col[2] ?? '';
                            $fgStart = ($gf !== '' && $gf !== $prevFtGroup) ? ' product-col-group-start' : '';
                            $prevFtGroup = $gf;
                        ?>
                        <td <?php echo $fid ? 'id="'.htmlspecialchars($fid).'"' : ''; ?><?php if ($fgStart !== '') echo ' class="'.htmlspecialchars(trim($fgStart)).'"'; ?> data-column="<?php echo htmlspecialchars($col[0]); ?>" style="text-align: right; color: #11294b;"><?php echo $isFirst ? 'Grand Total:' : ($fid ? '0.00' : ''); ?></td>
                        <?php endforeach; ?>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<script>
(function() {
    var boot = {
        pageColumnPrefs: <?php echo json_encode($product_list_prefs_page, JSON_UNESCAPED_UNICODE); ?>,
        colCount: <?php echo (int)count($product_list_table_columns); ?>,
        columnKeys: <?php echo json_encode($product_list_table_column_keys, JSON_UNESCAPED_UNICODE); ?>,
        columnGroup: <?php echo json_encode($product_list_table_column_group_map, JSON_UNESCAPED_UNICODE); ?>,
        noImageSrc: <?php echo json_encode($product_list_no_image_src, JSON_UNESCAPED_UNICODE); ?>
    };
    window.PRODUCT_LIST_TABLE_BOOT = boot;
    window.PRODUCT_LIST_COLUMN_GROUP = boot.columnGroup;
    window.SALE_INVOICE_PL_COL_COUNT = boot.colCount;
    window.PRODUCT_LIST_PL_COL_COUNT = boot.colCount;
    window.PRODUCT_LIST_COLUMNS = boot.columnKeys.slice();
})();
</script>
