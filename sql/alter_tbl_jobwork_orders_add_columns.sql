-- Add department_id and priority to tbl_jobwork_orders (for Job Work Order form)
-- Run this after create_tbl_jobwork_orders.sql if you use Department and Priority fields.

ALTER TABLE `tbl_jobwork_orders`
  ADD COLUMN `department_id` int(11) DEFAULT NULL AFTER `customer_name`,
  ADD COLUMN `priority` varchar(30) DEFAULT 'Medium' AFTER `status`;
