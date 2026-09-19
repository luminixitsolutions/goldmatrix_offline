-- Optional manual migration (auto-applied via auragold_ensure_tbl_metal_dashboard_images in PHP).

ALTER TABLE `tbl_metal`
  ADD COLUMN `dashboard_image_path` VARCHAR(512) NULL DEFAULT NULL COMMENT 'Relative to admin/' AFTER `system_name`,
  ADD COLUMN `dashboard_image_url` VARCHAR(1024) NULL DEFAULT NULL AFTER `dashboard_image_path`;
