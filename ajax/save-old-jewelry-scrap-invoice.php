<?php
/**
 * Save endpoint for old-jewelry-scrap-invoice.php (draft / invoice screen).
 * Does not mirror lines into tbl_old_jewelry_stock or set is_stocked — use save-old-jewelry-scrap-stock-in.php for Stock In.
 */
define('AURAGOLD_RUN_OLD_JEWELRY_SCRAP_SAVE_INTERNAL', true);
define('AURAGOLD_OJ_SCRAP_SAVE_SOURCE', 'invoice');
require __DIR__ . '/save-old-jewelry-scrap-invoice-internal.php';
