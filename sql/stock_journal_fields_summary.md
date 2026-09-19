# Stock Journal Table Structure Summary

## Current Table Fields (tbl_stock_journal)

### Required Fields (NOT NULL):
- `id` - Auto increment primary key
- `sj_invoice_no` - Stock Journal invoice number (UNIQUE)
- `item_id` - Reference to tbl_purchase_invoice_items.id (FK)
- `invoice_id` - Reference to tbl_purchase_invoices.id (FK)
- `sj_date` - Stock journal date

### Optional Fields:
- `invoice_no` - Purchase invoice number for reference
- `barcode` - varchar(100) ✅ **NOW BEING SAVED**
- `code` - varchar(100)
- `product_id` - int(11)
- `product_characteristic_id` - int(11)
- `product_name` - varchar(255)
- `metal_id` - int(11)
- `metal_type` - varchar(50)
- `quantity` - decimal(10,2) DEFAULT 1.00
- `gross_weight` - decimal(10,3) DEFAULT 0.000
- `less_weight` - decimal(10,3) DEFAULT 0.000
- `net_weight` - decimal(10,3) DEFAULT 0.000
- `purity` - decimal(10,2) DEFAULT 0.00
- `purity_weight` - decimal(10,3) DEFAULT 0.000
- `pure_weight` - decimal(10,3) DEFAULT 0.000
- `final_weight` - decimal(10,3) DEFAULT 0.000
- `rate` - decimal(15,2) DEFAULT 0.00
- `amount` - decimal(15,2) DEFAULT 0.00
- `making_amount` - decimal(15,2) DEFAULT 0.00
- `tax_amount` - decimal(15,2) DEFAULT 0.00
- `net_amount` - decimal(15,2) DEFAULT 0.00
- `net_amt_with_tax` - decimal(15,2) DEFAULT 0.00
- `group_name` - varchar(255)
- `comment` - text
- `status` - varchar(20) DEFAULT 'active'
- `created_by` - int(11)
- `created_at` - datetime
- `updated_at` - datetime

## Fields Being Collected But NOT in Table:

The following fields are collected from the form but are NOT stored in the database table:
- `stone_charges` - Not in table
- `other_charges` - Not in table
- `diamond_value` - Not in table
- `gemstone_value` - Not in table
- `metal_value` - Not in table (but `rate` is stored)
- `discount` - Not in table
- `stone_amount` - Not in table
- `other_amount` - Not in table
- `diamond_amount` - Not in table
- `purchase_amount` - Not in table
- `sale_amount` - Not in table
- `sale_amount_with` - Not in table
- `reverse` - Not in table
- `design_no` - Not in table

## Fix Applied:

✅ **Barcode is now being saved correctly** - Fixed JavaScript to extract barcode from text content instead of input field.

## Recommendation:

If you need to save the additional fields listed above, you would need to add them to the table structure using an ALTER TABLE statement.

