-- Table for stock journal item images (multiple images per barcode)
CREATE TABLE IF NOT EXISTS `tbl_stock_journal_images` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `item_id` INT(11) NOT NULL DEFAULT 0,
  `barcode_no` VARCHAR(100) NOT NULL DEFAULT '',
  `image_path` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_item_barcode` (`item_id`, `barcode_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
