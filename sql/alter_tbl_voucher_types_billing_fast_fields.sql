-- Voucher type: billing type dropdown + Enable Item Fast Fields (voucher-type.php)
-- Run once on existing databases.

ALTER TABLE `tbl_voucher_types`
  ADD COLUMN `billing_type` varchar(50) NOT NULL DEFAULT 'standard' AFTER `calculate_loss_by`,
  ADD COLUMN `enable_item_fast_fields` tinyint(1) NOT NULL DEFAULT 0 AFTER `calculate_markup_on_sale`;
