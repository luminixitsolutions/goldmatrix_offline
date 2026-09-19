-- Expense Category Master Table
-- Run this script to create tbl_expense_categories and insert default categories
-- Used in Expense Items "Category" field: click to open modal and select from list

CREATE TABLE IF NOT EXISTS `tbl_expense_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT 'Display name (e.g. Inter Branch Account ABU DHABI)',
  `type` varchar(100) DEFAULT NULL COMMENT 'Type in parentheses (e.g. Branch /Divisions)',
  `sort_order` int(11) DEFAULT 0,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `name` (`name`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert categories from reference (Name + Type format)
INSERT IGNORE INTO `tbl_expense_categories` (`name`, `type`, `sort_order`, `status`) VALUES
('Inter Branch Account ABU DHABI', 'Branch /Divisions', 1, 1),
('OFFICE CASH', 'Cash-in Hand', 2, 1),
('TARIFF', 'Duties& Taxes', 3, 1),
('CHASE BANK', 'Bank OD A/C', 4, 1),
('John smoth', 'Sales Account', 5, 1),
('Mujahid', 'Sales Account', 6, 1),
('KUMAL SANU', 'Current Assets', 7, 1),
('Inter Branch Account Main Branch', 'Branch /Divisions', 8, 1),
('Fund Installment Gold Scheme Interest', 'Indirect Expenses', 9, 1),
('Fund Transfer Redeem', 'Indirect Expenses', 10, 1);
