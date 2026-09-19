-- =============================================================================
-- Table: tbl_user_column_preferences
-- Purpose: Store user-specific column order and visibility for tables (e.g.
--          Product List, Product Selection modal) so preferences persist on reload.
-- Used by: sale-quotations.php, sale-invoice.php, get-column-preferences.php,
--          save-product-modal-column-preferences.php, etc.
-- =============================================================================

-- Create table only if it does not exist (safe to run multiple times)
CREATE TABLE IF NOT EXISTS `tbl_user_column_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `page_name` varchar(100) NOT NULL DEFAULT 'product-opening',
  `tab_key` varchar(50) NOT NULL DEFAULT '' COMMENT 'Tab: e.g. Gold, Silver, Platinum (metal_id) or main',
  `column_key` varchar(50) NOT NULL,
  `column_order` int(11) NOT NULL DEFAULT 0,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_page_tab_column` (`user_id`,`page_name`,`tab_key`,`column_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
