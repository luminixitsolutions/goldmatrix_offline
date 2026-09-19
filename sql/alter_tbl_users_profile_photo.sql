-- Optional: user avatar path (auto-added by auragold_ensure_tbl_users_profile_photo_column() on My Profile / sidebar load)
ALTER TABLE `tbl_users`
  ADD COLUMN `profile_photo` VARCHAR(500) NULL DEFAULT NULL COMMENT 'Relative path under admin/, e.g. uploads/user_profiles/1_xxx.jpg';
