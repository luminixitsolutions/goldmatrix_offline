-- Set Software menu lock settings (single row id=1)
CREATE TABLE IF NOT EXISTS `tbl_auragold_set_software_menu_lock` (
  `id` tinyint unsigned NOT NULL DEFAULT 1,
  `menu_password_hash` varchar(255) DEFAULT NULL COMMENT 'bcrypt — unlock locked Set Software menus',
  `locked_menus_json` longtext DEFAULT NULL COMMENT 'JSON array of menu keys',
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `tbl_auragold_set_software_menu_lock` (`id`) VALUES (1);
