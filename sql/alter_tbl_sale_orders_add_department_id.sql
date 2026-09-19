-- Link sale order to master department (tbl_departments.id)
-- Run once if column is missing. save-sale-order.php can also ADD COLUMN on first save.

ALTER TABLE `tbl_sale_orders`
  ADD COLUMN `department_id` int(11) DEFAULT NULL AFTER `customer_name`;
