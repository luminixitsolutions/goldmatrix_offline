-- GoldMatrix performance indexes (safe to run multiple times; skip errors if index exists).
-- Run once on each operational branch DB and registry if needed.

-- Permission lookups (sidebar / menu checks)
ALTER TABLE `tbl_user_permission_grants`
  ADD INDEX `idx_user_branch_granted` (`user_id`, `branch_id`, `granted`);

-- Notification unread badge
ALTER TABLE `tbl_auragold_notifications`
  ADD INDEX `idx_notifications_unread` (`read_at`, `created_at`);

-- Common list filters
ALTER TABLE `tbl_sale_orders`
  ADD INDEX `idx_sale_orders_due_date` (`due_date`);

ALTER TABLE `tbl_sale_invoices`
  ADD INDEX `idx_sale_invoices_due_date` (`due_date`);

ALTER TABLE `tbl_purchase_invoices`
  ADD INDEX `idx_purchase_invoices_due_date` (`due_date`);

ALTER TABLE `tbl_users`
  ADD INDEX `idx_users_status` (`status`);

ALTER TABLE `tbl_accounting_financial_years`
  ADD INDEX `idx_fy_status_active` (`status`, `is_active`);
