<?php
/**
 * Save endpoint for old-jewellery-scrap-stock-in.php (Stock In → inventory + stocked tab).
 */
define('AURAGOLD_RUN_OLD_JEWELRY_SCRAP_SAVE_INTERNAL', true);
define('AURAGOLD_OJ_SCRAP_SAVE_SOURCE', 'stock_in');
require __DIR__ . '/save-old-jewelry-scrap-invoice-internal.php';
