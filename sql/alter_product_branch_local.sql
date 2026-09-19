-- Branch-local product settings (applied automatically by auragold_ensure_product_branch_local_schema on product-opening save).
-- Run manually if preferred instead of auto-ALTER.

CREATE TABLE IF NOT EXISTS tbl_product_branch_settings (
  product_id INT NOT NULL,
  branch_id INT NOT NULL,
  category_id INT NULL,
  is_stock_item TINYINT NOT NULL DEFAULT 1,
  is_barcode TINYINT NOT NULL DEFAULT 1,
  updated_at DATETIME NULL,
  PRIMARY KEY (product_id, branch_id),
  KEY branch_id (branch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE tbl_product_tax ADD COLUMN branch_id INT NULL DEFAULT NULL AFTER product_id;
ALTER TABLE tbl_product_tax ADD KEY product_branch_tax (product_id, branch_id);
