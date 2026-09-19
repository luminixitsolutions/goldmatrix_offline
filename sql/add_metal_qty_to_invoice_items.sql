-- Add metal_qty and metal_weight to item tables so Metal Qty (e.g. 100) saves and loads correctly on edit.
-- Run once per table. If a column already exists, you will get "Duplicate column"; that is safe to ignore.
-- Save scripts (e.g. save-purchase-invoice.php, save-sale-return.php) also add these columns at runtime if missing.

-- Purchase Invoice items
ALTER TABLE tbl_purchase_invoice_items ADD COLUMN metal_qty DECIMAL(12,2) DEFAULT 1.00 AFTER quantity;
ALTER TABLE tbl_purchase_invoice_items ADD COLUMN metal_weight DECIMAL(12,4) DEFAULT 0.0000 AFTER metal_qty;

-- Sale Return items
ALTER TABLE tbl_sale_return_items ADD COLUMN metal_qty DECIMAL(12,2) DEFAULT 1.00 AFTER quantity;
ALTER TABLE tbl_sale_return_items ADD COLUMN metal_weight DECIMAL(12,4) DEFAULT 0.0000 AFTER metal_qty;

-- Sale Invoice items
ALTER TABLE tbl_sale_invoice_items ADD COLUMN metal_qty DECIMAL(12,2) DEFAULT 1.00 AFTER quantity;
ALTER TABLE tbl_sale_invoice_items ADD COLUMN metal_weight DECIMAL(12,4) DEFAULT 0.0000 AFTER metal_qty;

-- Purchase Quotation items
ALTER TABLE tbl_purchase_quotation_items ADD COLUMN metal_qty DECIMAL(12,2) DEFAULT 1.00 AFTER quantity;
ALTER TABLE tbl_purchase_quotation_items ADD COLUMN metal_weight DECIMAL(12,4) DEFAULT 0.0000 AFTER metal_qty;

-- Sale Quotation items
ALTER TABLE tbl_sale_quotation_items ADD COLUMN metal_qty DECIMAL(12,2) DEFAULT 1.00 AFTER quantity;
ALTER TABLE tbl_sale_quotation_items ADD COLUMN metal_weight DECIMAL(12,4) DEFAULT 0.0000 AFTER metal_qty;
