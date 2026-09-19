-- Optional: add department / job worker (Name) to Repair Job Work Order master.
-- Also applied automatically on save via ajax/save-repair-jobwork-order.php

ALTER TABLE tbl_repair_jobwork_orders
  ADD COLUMN department_id INT NULL DEFAULT NULL AFTER status;

ALTER TABLE tbl_repair_jobwork_orders
  ADD COLUMN department_user_id INT NULL DEFAULT NULL AFTER department_id;

ALTER TABLE tbl_repair_jobwork_orders
  ADD COLUMN priority VARCHAR(30) NULL DEFAULT NULL AFTER department_user_id;
