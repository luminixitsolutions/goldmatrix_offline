-- Allow "no mode selected" (0) as default for new rows; existing saved mode_id values are unchanged.
ALTER TABLE `tbl_accounting_calculation_settings`
  MODIFY `mode_id` int(11) NOT NULL DEFAULT 0 COMMENT '0 = not chosen; else FK tbl_accounting_master_modes.id';
