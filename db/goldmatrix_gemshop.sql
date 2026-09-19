-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jul 13, 2026 at 02:15 PM
-- Server version: 10.11.18-MariaDB
-- PHP Version: 8.4.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `goldmatrix_gemshop`
--

-- --------------------------------------------------------

--
-- Table structure for table `invoice_fixing_mapping`
--

CREATE TABLE `invoice_fixing_mapping` (
  `id` int(11) NOT NULL,
  `source_type` varchar(32) NOT NULL,
  `source_transaction_id` int(11) NOT NULL,
  `source_invoice_no` varchar(64) DEFAULT NULL,
  `against_invoice_type` varchar(32) DEFAULT NULL,
  `against_invoice_id` int(11) DEFAULT NULL,
  `against_invoice_no` varchar(64) DEFAULT NULL,
  `fixing_type` varchar(32) DEFAULT 'Hedging',
  `metal_type` varchar(16) DEFAULT NULL,
  `fixing_weight` decimal(18,3) DEFAULT 0.000,
  `fixing_rate` decimal(18,4) DEFAULT 0.0000,
  `fixing_amount` decimal(18,2) DEFAULT 0.00,
  `status` tinyint(4) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_accounting_calculation_settings`
--

CREATE TABLE `tbl_accounting_calculation_settings` (
  `id` int(11) NOT NULL,
  `mode_id` int(11) NOT NULL DEFAULT 0 COMMENT '0 = not chosen; else FK tbl_accounting_master_modes.id',
  `amount_decimal` tinyint(4) NOT NULL DEFAULT 2,
  `amount_round` tinyint(1) NOT NULL DEFAULT 1,
  `weight_decimal` tinyint(4) NOT NULL DEFAULT 3,
  `weight_round` tinyint(1) NOT NULL DEFAULT 1,
  `percent_decimal` tinyint(4) NOT NULL DEFAULT 3,
  `percent_round` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_accounting_calculation_settings`
--

INSERT INTO `tbl_accounting_calculation_settings` (`id`, `mode_id`, `amount_decimal`, `amount_round`, `weight_decimal`, `weight_round`, `percent_decimal`, `percent_round`, `updated_at`) VALUES
(1, 0, 2, 1, 3, 1, 3, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_accounting_financial_years`
--

CREATE TABLE `tbl_accounting_financial_years` (
  `id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Current FY (radio)',
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_accounting_financial_years`
--

INSERT INTO `tbl_accounting_financial_years` (`id`, `start_date`, `end_date`, `is_active`, `status`, `created_at`, `updated_at`) VALUES
(1, '2025-04-01', '2026-03-31', 0, 1, '2026-04-15 13:40:13', NULL),
(2, '2026-04-01', '2027-03-31', 1, 1, '2026-04-15 13:40:13', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_accounting_master_modes`
--

CREATE TABLE `tbl_accounting_master_modes` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL COMMENT 'Display name for Modes dropdown',
  `code` varchar(50) DEFAULT NULL COMMENT 'Stable code for integrations',
  `sort_order` int(11) DEFAULT 0,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_accounting_master_modes`
--

INSERT INTO `tbl_accounting_master_modes` (`id`, `name`, `code`, `sort_order`, `status`, `created_at`) VALUES
(1, 'Last Purchase Rate', 'last_purchase_rate', 1, 1, '2026-04-15 13:39:45'),
(2, 'FIFO', 'fifo', 2, 1, '2026-04-15 13:39:45'),
(3, 'Average Cost', 'average_cost', 3, 1, '2026-04-15 13:39:45'),
(4, 'Low Cost', 'low_cost', 4, 1, '2026-04-15 13:39:45'),
(5, 'High Cost', 'high_cost', 5, 1, '2026-04-15 13:39:45');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_advance_payments`
--

CREATE TABLE `tbl_advance_payments` (
  `id` int(11) NOT NULL,
  `voucher_no` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `ref_no` varchar(100) DEFAULT NULL,
  `receipt_no` varchar(100) DEFAULT NULL,
  `voucher_type` varchar(50) DEFAULT NULL,
  `against` varchar(100) DEFAULT NULL,
  `sales_person` varchar(255) DEFAULT NULL,
  `against_of` varchar(100) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'USD',
  `voucher_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `layaways_id` int(11) DEFAULT NULL,
  `fixing_type` varchar(50) DEFAULT 'Standard',
  `previous_balance` decimal(15,2) DEFAULT 0.00,
  `previous_gold` decimal(10,3) DEFAULT 0.000,
  `previous_silver` decimal(10,3) DEFAULT 0.000,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `total_gold` decimal(10,3) DEFAULT 0.000,
  `total_silver` decimal(10,3) DEFAULT 0.000,
  `comment` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_advance_payment_items`
--

CREATE TABLE `tbl_advance_payment_items` (
  `id` int(11) NOT NULL,
  `voucher_id` int(11) NOT NULL,
  `payment_type` varchar(50) DEFAULT NULL,
  `diamond_category` varchar(100) DEFAULT NULL,
  `transaction_no` varchar(100) DEFAULT NULL,
  `deposit_into` varchar(100) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `cheque_date` date DEFAULT NULL,
  `weight` decimal(10,3) DEFAULT 0.000,
  `metal_id` int(11) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 0.00,
  `purity_carat` varchar(50) DEFAULT NULL,
  `purity_wt` decimal(10,3) DEFAULT 0.000,
  `amount` decimal(15,2) DEFAULT 0.00,
  `previous_balance_amount` decimal(15,2) DEFAULT 0.00,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_article`
--

CREATE TABLE `tbl_article` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `article_code` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `status` tinyint(4) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_auragold_i18n_cache`
--

CREATE TABLE `tbl_auragold_i18n_cache` (
  `locale` varchar(32) NOT NULL,
  `msg_key` varchar(191) NOT NULL,
  `msg_value` longtext DEFAULT NULL,
  `updated_at` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_auragold_mail_settings`
--

CREATE TABLE `tbl_auragold_mail_settings` (
  `id` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `smtp_host` varchar(255) NOT NULL DEFAULT '' COMMENT 'Outgoing server',
  `smtp_port` smallint(5) UNSIGNED NOT NULL DEFAULT 465,
  `smtp_encryption` varchar(8) NOT NULL DEFAULT 'ssl' COMMENT 'ssl, tls, none',
  `smtp_username` varchar(255) NOT NULL DEFAULT '',
  `smtp_password` varchar(512) DEFAULT NULL,
  `from_name` varchar(255) NOT NULL DEFAULT '',
  `from_email` varchar(255) NOT NULL DEFAULT '',
  `incoming_host` varchar(255) NOT NULL DEFAULT '' COMMENT 'IMAP/POP server (client reference)',
  `imap_port` smallint(5) UNSIGNED NOT NULL DEFAULT 993,
  `pop3_port` smallint(5) UNSIGNED NOT NULL DEFAULT 995,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_auragold_mobile_menu_settings`
--

CREATE TABLE `tbl_auragold_mobile_menu_settings` (
  `id` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `enabled_json` longtext DEFAULT NULL COMMENT 'JSON {modules:[], pages:[]}; NULL = all menus enabled',
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_auragold_mobile_menu_settings`
--

INSERT INTO `tbl_auragold_mobile_menu_settings` (`id`, `enabled_json`, `updated_at`) VALUES
(1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_auragold_notifications`
--

CREATE TABLE `tbl_auragold_notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `doc_kind` varchar(64) DEFAULT NULL,
  `ref_id` int(11) DEFAULT NULL,
  `dedupe_key` varchar(191) DEFAULT NULL,
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_auragold_referral_settings`
--

CREATE TABLE `tbl_auragold_referral_settings` (
  `branch_id` int(11) NOT NULL,
  `settings_json` longtext DEFAULT NULL COMMENT 'Referral rewards form payload',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_auragold_reward_coupons`
--

CREATE TABLE `tbl_auragold_reward_coupons` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `coupon_name` varchar(200) NOT NULL DEFAULT '',
  `coupon_code` varchar(80) NOT NULL DEFAULT '',
  `coupon_value` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `expiry_date` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_auragold_reward_point_settings`
--

CREATE TABLE `tbl_auragold_reward_point_settings` (
  `branch_id` int(11) NOT NULL,
  `settings_json` longtext DEFAULT NULL COMMENT 'Reward Point UI state (metal-wise blocks)',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_barcode_settings`
--

CREATE TABLE `tbl_barcode_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `label_size_preset` varchar(50) NOT NULL DEFAULT '100x18' COMMENT 'e.g. 100x18, 100x25, custom',
  `label_width_mm` decimal(10,2) NOT NULL DEFAULT 100.00,
  `label_height_mm` decimal(10,2) NOT NULL DEFAULT 18.00,
  `font_size` int(11) NOT NULL DEFAULT 12 COMMENT 'Label text font size in px',
  `show_product_name` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=show, 0=hide',
  `show_price` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=show, 0=hide',
  `show_barcode_number` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=show, 0=hide',
  `show_product_name_barcode` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=show product name on barcode layout',
  `show_product_name_qr` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=show product name on QR layout',
  `show_price_barcode` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=show price on barcode layout',
  `show_price_qr` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=show price on QR layout',
  `show_barcode_number_barcode` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=show barcode no. on barcode layout',
  `show_barcode_number_qr` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=show barcode no. on QR layout',
  `print_copies` int(11) NOT NULL DEFAULT 1 COMMENT 'Number of copies per label',
  `barcode_bar_width` tinyint(4) NOT NULL DEFAULT 2 COMMENT 'JsBarcode module width 1-10',
  `barcode_bar_height` smallint(6) NOT NULL DEFAULT 28 COMMENT 'JsBarcode module height px',
  `metal_type` varchar(50) DEFAULT NULL COMMENT 'e.g. Gold, Silver, Platinum',
  `is_default_print` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = default print layout for this metal_type (only one per metal+branch)',
  `design_layout` text DEFAULT NULL COMMENT 'JSON: barcode label design (field, left, top, prefix, suffix, font, font_size)',
  `design_layout_qr` longtext DEFAULT NULL COMMENT 'JSON label design for QR mode',
  `default_print_code_type` varchar(10) NOT NULL DEFAULT 'barcode' COMMENT 'barcode|qr — used when print URL has no code= param',
  `preview_image` varchar(255) DEFAULT NULL COMMENT 'Path to saved preview image e.g. uploads/barcode_settings/preview_1234567890.png',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_barcode_settings`
--

INSERT INTO `tbl_barcode_settings` (`id`, `branch_id`, `label_size_preset`, `label_width_mm`, `label_height_mm`, `font_size`, `show_product_name`, `show_price`, `show_barcode_number`, `show_product_name_barcode`, `show_product_name_qr`, `show_price_barcode`, `show_price_qr`, `show_barcode_number_barcode`, `show_barcode_number_qr`, `print_copies`, `barcode_bar_width`, `barcode_bar_height`, `metal_type`, `is_default_print`, `design_layout`, `design_layout_qr`, `default_print_code_type`, `preview_image`, `created_at`, `updated_at`) VALUES
(1, 1, 'custom', 100.00, 18.00, 11, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 26, 'Gold', 0, '{\"items\":[{\"type\":\"barcode_image\",\"left\":1.67,\"top\":4,\"width\":34.67,\"height\":8.67},{\"type\":\"text\",\"field\":\"GrossWt\",\"left\":52,\"top\":2,\"prefix\":\"GrossWt\",\"suffix\":\"\",\"font\":\"Arial\",\"font_size\":\"10\",\"pad_top\":0,\"pad_right\":0,\"pad_bottom\":0,\"pad_left\":0},{\"type\":\"text\",\"field\":\"Barcode\",\"left\":11,\"top\":13,\"prefix\":\"Barcode\",\"suffix\":\"\",\"font\":\"Arial\",\"font_size\":\"10\",\"pad_top\":0,\"pad_right\":0,\"pad_bottom\":0,\"pad_left\":0},{\"type\":\"text\",\"field\":\"CompanyName\",\"left\":7,\"top\":0,\"prefix\":\"Rokde Jewellers\",\"suffix\":\"\",\"font\":\"Arial\",\"font_size\":\"10\",\"pad_top\":0,\"pad_right\":0,\"pad_bottom\":0,\"pad_left\":0},{\"type\":\"text\",\"field\":\"MetalRate\",\"left\":72.67,\"top\":6.33,\"prefix\":\"MetalRate\",\"suffix\":\"\",\"font\":\"Arial\",\"font_size\":\"10\",\"pad_top\":0,\"pad_right\":0,\"pad_bottom\":0,\"pad_left\":0}],\"items2\":[{\"type\":\"barcode_image\",\"left\":0,\"top\":0,\"width\":33.33,\"height\":6}],\"barcode1_top\":4,\"barcode1_left\":1.67,\"barcode2_top\":0,\"barcode2_left\":0,\"barcode_bar_width\":1,\"barcode_bar_height\":26,\"label_pad_top\":7,\"label_pad_right\":0,\"label_pad_bottom\":0,\"label_pad_left\":0,\"barcode_left\":5,\"barcode_top\":12,\"barcode_position\":{\"left\":5,\"top\":12},\"layout_type\":\"barcode\",\"fields\":[{\"type\":\"barcode_image\",\"left\":1.67,\"top\":4,\"width\":34.67,\"height\":8.67},{\"type\":\"text\",\"field\":\"GrossWt\",\"left\":52,\"top\":2,\"prefix\":\"GrossWt\",\"suffix\":\"\",\"font\":\"Arial\",\"font_size\":\"10\",\"pad_top\":0,\"pad_right\":0,\"pad_bottom\":0,\"pad_left\":0},{\"type\":\"text\",\"field\":\"Barcode\",\"left\":11,\"top\":13,\"prefix\":\"Barcode\",\"suffix\":\"\",\"font\":\"Arial\",\"font_size\":\"10\",\"pad_top\":0,\"pad_right\":0,\"pad_bottom\":0,\"pad_left\":0},{\"type\":\"text\",\"field\":\"CompanyName\",\"left\":7,\"top\":0,\"prefix\":\"Rokde Jewellers\",\"suffix\":\"\",\"font\":\"Arial\",\"font_size\":\"10\",\"pad_top\":0,\"pad_right\":0,\"pad_bottom\":0,\"pad_left\":0},{\"type\":\"text\",\"field\":\"MetalRate\",\"left\":72.67,\"top\":6.33,\"prefix\":\"MetalRate\",\"suffix\":\"\",\"font\":\"Arial\",\"font_size\":\"10\",\"pad_top\":0,\"pad_right\":0,\"pad_bottom\":0,\"pad_left\":0}],\"fields2\":[{\"type\":\"barcode_image\",\"left\":0,\"top\":0,\"width\":33.33,\"height\":6}],\"layout_variant\":\"barcode\"}', '{\"items\":[{\"type\":\"barcode_image\",\"left\":2,\"top\":2,\"width\":10,\"height\":10.33},{\"type\":\"text\",\"field\":\"GrossWt\",\"left\":52,\"top\":2,\"prefix\":\"GrossWt\",\"suffix\":\"\",\"font\":\"Arial\",\"font_size\":\"10\",\"pad_top\":0,\"pad_right\":0,\"pad_bottom\":0,\"pad_left\":0},{\"type\":\"text\",\"field\":\"Barcode\",\"left\":17.33,\"top\":6.33,\"prefix\":\"Barcode\",\"suffix\":\"\",\"font\":\"Arial\",\"font_size\":\"10\",\"pad_top\":0,\"pad_right\":0,\"pad_bottom\":0,\"pad_left\":0},{\"type\":\"text\",\"field\":\"CompanyName\",\"left\":12.67,\"top\":2,\"prefix\":\"Rokde Jewellers\",\"suffix\":\"\",\"font\":\"Arial\",\"font_size\":\"10\",\"pad_top\":0,\"pad_right\":0,\"pad_bottom\":0,\"pad_left\":0},{\"type\":\"text\",\"field\":\"MetalRate\",\"left\":51.67,\"top\":9.67,\"prefix\":\"MetalRate\",\"suffix\":\"\",\"font\":\"Arial\",\"font_size\":\"10\",\"pad_top\":0,\"pad_right\":0,\"pad_bottom\":0,\"pad_left\":0}],\"items2\":[{\"type\":\"barcode_image\",\"left\":0,\"top\":0,\"width\":11.11,\"height\":10.33}],\"barcode1_top\":2,\"barcode1_left\":2,\"barcode2_top\":0,\"barcode2_left\":0,\"qr_width\":30,\"qr_height\":31,\"label_pad_top\":10,\"label_pad_right\":0,\"label_pad_bottom\":0,\"label_pad_left\":0,\"barcode_left\":6,\"barcode_top\":6,\"barcode_position\":{\"left\":6,\"top\":6},\"layout_type\":\"qr\",\"fields\":[{\"type\":\"barcode_image\",\"left\":2,\"top\":2,\"width\":10,\"height\":10.33},{\"type\":\"text\",\"field\":\"GrossWt\",\"left\":52,\"top\":2,\"prefix\":\"GrossWt\",\"suffix\":\"\",\"font\":\"Arial\",\"font_size\":\"10\",\"pad_top\":0,\"pad_right\":0,\"pad_bottom\":0,\"pad_left\":0},{\"type\":\"text\",\"field\":\"Barcode\",\"left\":17.33,\"top\":6.33,\"prefix\":\"Barcode\",\"suffix\":\"\",\"font\":\"Arial\",\"font_size\":\"10\",\"pad_top\":0,\"pad_right\":0,\"pad_bottom\":0,\"pad_left\":0},{\"type\":\"text\",\"field\":\"CompanyName\",\"left\":12.67,\"top\":2,\"prefix\":\"Rokde Jewellers\",\"suffix\":\"\",\"font\":\"Arial\",\"font_size\":\"10\",\"pad_top\":0,\"pad_right\":0,\"pad_bottom\":0,\"pad_left\":0},{\"type\":\"text\",\"field\":\"MetalRate\",\"left\":51.67,\"top\":9.67,\"prefix\":\"MetalRate\",\"suffix\":\"\",\"font\":\"Arial\",\"font_size\":\"10\",\"pad_top\":0,\"pad_right\":0,\"pad_bottom\":0,\"pad_left\":0}],\"fields2\":[{\"type\":\"barcode_image\",\"left\":0,\"top\":0,\"width\":11.11,\"height\":10.33}],\"layout_variant\":\"qr\"}', 'barcode', 'uploads/barcode_settings/preview_1776677503.png', '2026-02-25 16:25:12', '2026-04-20 15:01:43'),
(2, 47, '100x18', 100.00, 18.00, 12, 1, 1, 0, 1, 1, 1, 1, 0, 1, 1, 1, 19, 'Silver', 1, '{\"items\":[{\"type\":\"barcode_image\",\"left\":0.79,\"top\":0.79,\"width\":20.9,\"height\":5.03,\"display_width_mm\":20.9},{\"type\":\"text\",\"field\":\"GrossWt\",\"left\":20.63,\"top\":10.59,\"prefix\":\"GrossWt\",\"suffix\":\"\",\"font\":\"Arial\",\"font_size\":\"10\",\"pad_top\":0,\"pad_right\":0,\"pad_bottom\":0,\"pad_left\":0}],\"barcode1_top\":0.79,\"barcode1_left\":0.79,\"barcode_bar_width\":1,\"barcode_bar_height\":19,\"label_pad_top\":0,\"label_pad_right\":0,\"label_pad_bottom\":0,\"label_pad_left\":0,\"barcode_left\":3,\"barcode_top\":3,\"barcode_position\":{\"left\":3,\"top\":3},\"layout_type\":\"barcode\",\"preview_box1_left\":156,\"preview_box1_top\":132,\"fields\":[{\"type\":\"barcode_image\",\"left\":0.79,\"top\":0.79,\"width\":20.9,\"height\":5.03,\"display_width_mm\":20.9},{\"type\":\"text\",\"field\":\"GrossWt\",\"left\":20.63,\"top\":10.59,\"prefix\":\"GrossWt\",\"suffix\":\"\",\"font\":\"Arial\",\"font_size\":\"10\",\"pad_top\":0,\"pad_right\":0,\"pad_bottom\":0,\"pad_left\":0}],\"layout_variant\":\"barcode\"}', '{}', 'barcode', 'uploads/barcode_settings/preview_1779819802.png', '2026-05-18 17:33:34', '2026-05-26 23:53:22'),
(3, 47, '100x18', 100.00, 18.00, 12, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 34, 'Gold', 0, '{\"items\":[{\"type\":\"barcode_image\",\"left\":62.43,\"top\":2.38,\"width\":20.9,\"height\":9,\"display_width_mm\":20.9}],\"barcode1_top\":2.38,\"barcode1_left\":62.43,\"barcode_bar_width\":1,\"barcode_bar_height\":34,\"label_pad_top\":0,\"label_pad_right\":0,\"label_pad_bottom\":0,\"label_pad_left\":0,\"barcode_left\":236,\"barcode_top\":9,\"barcode_position\":{\"left\":236,\"top\":9},\"layout_type\":\"barcode\",\"preview_box1_left\":156,\"preview_box1_top\":132,\"fields\":[{\"type\":\"barcode_image\",\"left\":62.43,\"top\":2.38,\"width\":20.9,\"height\":9,\"display_width_mm\":20.9}],\"layout_variant\":\"barcode\"}', '{}', 'barcode', 'uploads/barcode_settings/preview_1779819767.png', '2026-05-25 18:48:30', '2026-05-26 23:52:47'),
(4, 47, '100x18', 100.00, 18.00, 12, 1, 1, 0, 1, 1, 1, 1, 1, 1, 1, 1, 19, 'Platinum', 0, '{\"items\":[{\"type\":\"barcode_image\",\"left\":59.33,\"top\":5,\"width\":26.33,\"height\":6.33}],\"barcode1_top\":5,\"barcode1_left\":59.33,\"barcode_bar_width\":1,\"barcode_bar_height\":19,\"label_pad_top\":0,\"label_pad_right\":0,\"label_pad_bottom\":0,\"label_pad_left\":0,\"barcode_left\":178,\"barcode_top\":15,\"barcode_position\":{\"left\":178,\"top\":15},\"layout_type\":\"barcode\",\"label_width_mm\":100,\"label_height_mm\":18,\"fields\":[{\"type\":\"barcode_image\",\"left\":59.33,\"top\":5,\"width\":26.33,\"height\":6.33}],\"layout_variant\":\"barcode\"}', NULL, 'barcode', 'uploads/barcode_settings/preview_1779715587.png', '2026-05-25 18:56:27', '2026-05-25 18:56:27'),
(5, 47, '120x50', 120.00, 50.00, 12, 1, 1, 0, 1, 1, 1, 1, 0, 1, 1, 1, 15, 'Gold', 0, '{\"items\":[{\"type\":\"barcode_image\",\"left\":3,\"top\":3,\"width\":14.33,\"height\":5,\"display_width_mm\":14.33},{\"type\":\"text\",\"field\":\"GrossWt\",\"left\":16.67,\"top\":28.67,\"prefix\":\"GrossWt\",\"suffix\":\"\",\"font\":\"Arial\",\"font_size\":\"10\",\"pad_top\":0,\"pad_right\":0,\"pad_bottom\":0,\"pad_left\":0}],\"items2\":[{\"type\":\"barcode_image\",\"left\":2.33,\"top\":2.67,\"width\":15.33,\"height\":5,\"display_width_mm\":15.33},{\"type\":\"text\",\"field\":\"GrossWt\",\"left\":18.33,\"top\":25,\"prefix\":\"GrossWt\",\"suffix\":\"\",\"font\":\"Arial\",\"font_size\":\"10\",\"pad_top\":0,\"pad_right\":0,\"pad_bottom\":0,\"pad_left\":0}],\"barcode1_top\":3,\"barcode1_left\":3,\"barcode2_top\":2.67,\"barcode2_left\":2.33,\"double_barcode_120x50\":true,\"double_barcode_dual_tag\":true,\"dual_label_preset\":\"120x50\",\"dual_tag_half_width_mm\":59,\"dual_tag_gap_mm\":2,\"dual_quadrant_width_mm\":20,\"dual_quadrant_height_mm\":25,\"barcode_bar_width\":1,\"barcode_bar_height\":15,\"label_pad_top\":0,\"label_pad_right\":0,\"label_pad_bottom\":0,\"label_pad_left\":0,\"barcode_left\":9,\"barcode_top\":9,\"barcode_position\":{\"left\":9,\"top\":9},\"layout_type\":\"barcode\",\"label_width_mm\":120,\"label_height_mm\":50,\"fields\":[{\"type\":\"barcode_image\",\"left\":3,\"top\":3,\"width\":14.33,\"height\":5,\"display_width_mm\":14.33},{\"type\":\"text\",\"field\":\"GrossWt\",\"left\":16.67,\"top\":28.67,\"prefix\":\"GrossWt\",\"suffix\":\"\",\"font\":\"Arial\",\"font_size\":\"10\",\"pad_top\":0,\"pad_right\":0,\"pad_bottom\":0,\"pad_left\":0}],\"fields2\":[{\"type\":\"barcode_image\",\"left\":2.33,\"top\":2.67,\"width\":15.33,\"height\":5,\"display_width_mm\":15.33},{\"type\":\"text\",\"field\":\"GrossWt\",\"left\":18.33,\"top\":25,\"prefix\":\"GrossWt\",\"suffix\":\"\",\"font\":\"Arial\",\"font_size\":\"10\",\"pad_top\":0,\"pad_right\":0,\"pad_bottom\":0,\"pad_left\":0}],\"layout_variant\":\"barcode\"}', '{}', 'barcode', 'uploads/barcode_settings/preview_1779791303.png', '2026-05-26 13:32:33', '2026-05-26 15:58:23'),
(6, 47, '82x38_2box', 82.00, 38.00, 12, 1, 1, 0, 1, 1, 1, 1, 0, 1, 1, 1, 28, 'Gold', 1, '{\"items\":[{\"type\":\"barcode_image\",\"left\":2.4,\"top\":0,\"width\":11.9,\"height\":9.5,\"display_width_mm\":11.9},{\"type\":\"text\",\"field\":\"Barcode\",\"left\":2.47,\"top\":6.52,\"prefix\":\"Barcode\",\"suffix\":\"\",\"font\":\"Arial\",\"font_size\":\"10\",\"pad_top\":0,\"pad_right\":0,\"pad_bottom\":0,\"pad_left\":0}],\"items2\":[{\"type\":\"barcode_image\",\"left\":3.8,\"top\":0.7,\"width\":12.7,\"height\":7.9,\"display_width_mm\":12.7},{\"type\":\"text\",\"field\":\"Barcode\",\"left\":4.11,\"top\":6.79,\"prefix\":\"Barcode\",\"suffix\":\"\",\"font\":\"Arial\",\"font_size\":\"10\",\"pad_top\":0,\"pad_right\":0,\"pad_bottom\":0,\"pad_left\":0}],\"barcode1_top\":0,\"barcode1_left\":2.47,\"barcode2_top\":0.27,\"barcode2_left\":3.56,\"layout_type\":\"82x38_2box\",\"sticker_82x38_2box\":true,\"barcode1\":{\"left_mm\":2.4,\"top_mm\":0,\"width_mm\":11.9,\"height_mm\":9.5},\"barcode2\":{\"left_mm\":3.8,\"top_mm\":0.7,\"width_mm\":12.7,\"height_mm\":7.9},\"box1\":{\"left_mm\":0,\"top_mm\":13,\"width_mm\":20,\"height_mm\":25,\"items\":[{\"type\":\"barcode_image\",\"left\":2.4,\"top\":0,\"width\":11.9,\"height\":9.5,\"display_width_mm\":11.9},{\"type\":\"text\",\"field\":\"Barcode\",\"left\":2.47,\"top\":6.52,\"prefix\":\"Barcode\",\"suffix\":\"\",\"font\":\"Arial\",\"font_size\":\"10\",\"pad_top\":0,\"pad_right\":0,\"pad_bottom\":0,\"pad_left\":0}]},\"box2\":{\"left_mm\":62,\"top_mm\":0,\"width_mm\":20,\"height_mm\":25,\"items\":[{\"type\":\"barcode_image\",\"left\":3.8,\"top\":0.7,\"width\":12.7,\"height\":7.9,\"display_width_mm\":12.7},{\"type\":\"text\",\"field\":\"Barcode\",\"left\":4.11,\"top\":6.79,\"prefix\":\"Barcode\",\"suffix\":\"\",\"font\":\"Arial\",\"font_size\":\"10\",\"pad_top\":0,\"pad_right\":0,\"pad_bottom\":0,\"pad_left\":0}]},\"box1_left_mm\":0,\"box1_top_mm\":13,\"box1_width_mm\":20,\"box1_height_mm\":25,\"box2_left_mm\":62,\"box2_top_mm\":0,\"box2_width_mm\":20,\"box2_height_mm\":25,\"box2_right_mm\":0,\"box_width_mm\":20,\"box_height_mm\":25,\"box1_barcode_width_mm\":11.9,\"box1_barcode_height_mm\":9.5,\"box1_barcode_left_mm\":2.4,\"box1_barcode_top_mm\":0,\"box2_barcode_width_mm\":12.7,\"box2_barcode_height_mm\":7.9,\"box2_barcode_left_mm\":3.8,\"box2_barcode_top_mm\":0.7,\"box_barcode_width_mm\":11.9,\"box_barcode_height_mm\":9.5,\"barcode_width_mm\":11.9,\"barcode_height_mm\":9.5,\"barcode_left_mm\":2.4,\"barcode_top_mm\":0,\"barcode_no_font_size\":7,\"barcode_no_margin_top_mm\":1,\"dual_quadrant_width_mm\":20,\"dual_quadrant_height_mm\":25,\"barcode_bar_width\":1,\"barcode_bar_height\":28,\"label_pad_top\":0,\"label_pad_right\":0,\"label_pad_bottom\":0,\"label_pad_left\":0,\"barcode_left\":9,\"barcode_top\":0,\"barcode_position\":{\"left\":9,\"top\":0},\"fields\":[{\"type\":\"barcode_image\",\"left\":2.47,\"top\":0,\"width\":11.78,\"height\":9.51,\"display_width_mm\":11.78},{\"type\":\"text\",\"field\":\"Barcode\",\"left\":2.47,\"top\":6.52,\"prefix\":\"Barcode\",\"suffix\":\"\",\"font\":\"Arial\",\"font_size\":\"10\",\"pad_top\":0,\"pad_right\":0,\"pad_bottom\":0,\"pad_left\":0}],\"fields2\":[{\"type\":\"barcode_image\",\"left\":3.56,\"top\":0.27,\"width\":12.6,\"height\":7.88,\"display_width_mm\":12.6},{\"type\":\"text\",\"field\":\"Barcode\",\"left\":4.11,\"top\":6.79,\"prefix\":\"Barcode\",\"suffix\":\"\",\"font\":\"Arial\",\"font_size\":\"10\",\"pad_top\":0,\"pad_right\":0,\"pad_bottom\":0,\"pad_left\":0}],\"layout_variant\":\"barcode\"}', '{}', 'barcode', 'uploads/barcode_settings/preview_1779819788.png', '2026-05-26 17:23:08', '2026-05-26 23:53:08'),
(7, 63, '100x18', 100.00, 18.00, 12, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 10, 'Gold', 0, '{\"items\":[{\"type\":\"barcode_image\",\"left\":8.73,\"top\":6.88,\"width\":20.9,\"height\":2.65,\"display_width_mm\":20.9}],\"barcode1_top\":6.88,\"barcode1_left\":8.73,\"barcode_bar_width\":1,\"barcode_bar_height\":10,\"label_pad_top\":0,\"label_pad_right\":0,\"label_pad_bottom\":0,\"label_pad_left\":0,\"barcode_left\":33,\"barcode_top\":26,\"barcode_position\":{\"left\":33,\"top\":26},\"layout_type\":\"barcode\",\"preview_box1_left\":214,\"preview_box1_top\":132,\"fields\":[{\"type\":\"barcode_image\",\"left\":8.73,\"top\":6.88,\"width\":20.9,\"height\":2.65,\"display_width_mm\":20.9}],\"layout_variant\":\"barcode\"}', '{}', 'barcode', 'uploads/barcode_settings/preview_1780721257.png', '2026-06-06 10:14:43', '2026-06-06 10:17:37');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_bill_series`
--

CREATE TABLE `tbl_bill_series` (
  `id` int(10) UNSIGNED NOT NULL,
  `voucher_type_id` int(11) NOT NULL COMMENT 'FK to tbl_voucher_types.id',
  `branch_id` int(11) DEFAULT NULL COMMENT 'Optional branch',
  `prefix` varchar(50) NOT NULL DEFAULT '',
  `suffix` varchar(50) NOT NULL DEFAULT '',
  `start_count` int(11) NOT NULL DEFAULT 0 COMMENT 'Bill series count from',
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_bill_series`
--

INSERT INTO `tbl_bill_series` (`id`, `voucher_type_id`, `branch_id`, `prefix`, `suffix`, `start_count`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(68, 1, 63, 'AP-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(69, 2, 63, 'A-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(70, 3, 63, 'AI-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(71, 4, 63, 'BOM-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(72, 5, 63, 'BE-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(73, 6, 63, 'EI-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(74, 7, 63, 'FT-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(75, 8, 63, 'FW-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(76, 9, 63, 'II-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(77, 10, 63, 'IF-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(78, 11, 63, 'JC-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(79, 12, 63, 'JWI-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(80, 13, 63, 'JWO-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(81, 14, 63, 'JWQ-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(82, 15, 63, 'JM-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(83, 16, 63, 'JV-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(84, 17, 63, 'L-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(85, 18, 63, 'LR-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(86, 19, 63, 'MI-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(87, 20, 63, 'PF-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(88, 21, 63, 'PFDI-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(89, 22, 63, 'PI-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(90, 23, 63, 'PO-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(91, 24, 63, 'PQ-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(92, 25, 63, 'PR-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(93, 26, 63, 'RV-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(94, 27, 63, 'RI-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(95, 28, 63, 'RO-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(96, 29, 63, 'RI29-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(97, 30, 63, 'RO30-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(98, 31, 63, 'SF-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(99, 32, 63, 'SFDI-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(100, 33, 63, 'SI-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(101, 34, 63, 'SO-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(102, 35, 63, 'SQ-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(103, 36, 63, 'SR-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(104, 37, 63, 'SV-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(105, 38, 63, 'SJ-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(106, 39, 63, 'STI-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(107, 40, 63, 'STO-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(108, 41, 63, 'UI-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(109, 42, 63, 'OJB-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(110, 43, 63, 'CQ-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(111, 44, 63, 'CI-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(112, 45, 63, 'CO-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(113, 46, 63, 'CV-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(114, 47, 63, 'CN-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(115, 48, 63, 'CA-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(116, 49, 63, 'DSV-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(117, 50, 63, 'DN-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(118, 51, 63, 'DN51-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(119, 52, 63, 'MI52-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(120, 53, 63, 'MO-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(121, 54, 63, 'MR-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(122, 55, 63, 'MR55-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(123, 56, 63, 'MSV-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(124, 57, 63, 'OJSI-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(125, 58, 63, 'OB-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(126, 59, 63, 'OS-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(127, 60, 63, 'PV-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(128, 61, 63, 'PC-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(129, 62, 63, 'PP-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(130, 63, 63, 'PR63-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(131, 64, 63, 'PS-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(132, 65, 63, 'P-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(133, 66, 63, 'TE-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(134, 67, 63, 'SRV-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL),
(135, 68, 63, 'PPV-', '', 1, 1, NULL, '2026-06-05 23:28:23', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_branches`
--

CREATE TABLE `tbl_branches` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `db_name` varchar(100) DEFAULT NULL,
  `db_users` varchar(100) DEFAULT NULL,
  `db_password` varchar(100) DEFAULT NULL,
  `main_branch_id` int(11) NOT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `username` varchar(100) DEFAULT NULL,
  `password` varchar(100) DEFAULT NULL,
  `allow_product_delete` tinyint(4) NOT NULL DEFAULT 0,
  `address` text DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `gst_no` varchar(50) DEFAULT NULL,
  `pan_no` varchar(25) DEFAULT NULL,
  `authorized_person` varchar(150) DEFAULT NULL,
  `bank_name` varchar(150) DEFAULT NULL,
  `bank_account_no` varchar(64) DEFAULT NULL,
  `bank_ifsc` varchar(20) DEFAULT NULL,
  `bank_branch` varchar(150) DEFAULT NULL,
  `location_area` varchar(255) DEFAULT NULL,
  `logo_path` varchar(500) DEFAULT NULL,
  `invoice_terms` text DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `profile_country_id` int(11) DEFAULT NULL,
  `profile_state_id` int(11) DEFAULT NULL,
  `profile_city_id` int(11) DEFAULT NULL,
  `profile_phone_country_code` varchar(10) DEFAULT NULL,
  `profile_base_currency_id` int(11) DEFAULT NULL,
  `pincode` varchar(10) DEFAULT NULL COMMENT 'Postal PIN (e-Way / GST)',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'Branch / server public IP (optional)',
  `subdomain_url` varchar(255) DEFAULT NULL COMMENT 'Branch host, e.g. pune.goldmatrix.com (optional)',
  `business_license_no` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_branches`
--

INSERT INTO `tbl_branches` (`id`, `name`, `code`, `db_name`, `db_users`, `db_password`, `main_branch_id`, `status`, `created_at`, `username`, `password`, `allow_product_delete`, `address`, `phone`, `email`, `gst_no`, `pan_no`, `authorized_person`, `bank_name`, `bank_account_no`, `bank_ifsc`, `bank_branch`, `location_area`, `logo_path`, `invoice_terms`, `website`, `profile_country_id`, `profile_state_id`, `profile_city_id`, `profile_phone_country_code`, `profile_base_currency_id`, `pincode`, `ip_address`, `subdomain_url`, `business_license_no`) VALUES
(63, 'GemShop', NULL, 'goldmatrix_gemshop', 'goldmatrix_gemshop', 'lndsGgZYh0KzP7vnNNXp', 0, 1, '2026-06-05 23:28:15', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'https://gemshop.goldmatrixsoft.com', 'https://gemshop.goldmatrixsoft.com', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_break_type`
--

CREATE TABLE `tbl_break_type` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `name` varchar(100) NOT NULL,
  `status` tinyint(4) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_calculation_modes`
--

CREATE TABLE `tbl_calculation_modes` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_calculation_modes`
--

INSERT INTO `tbl_calculation_modes` (`id`, `name`, `code`, `status`, `sort_order`, `created_at`) VALUES
(1, 'Product Amount', 'product_amount', 1, 1, '2025-12-26 16:34:46'),
(2, 'Invoice Amount', 'invoice_amount', 1, 2, '2025-12-26 16:34:46'),
(3, 'Hallmark Amount', 'hallmark_amount', 1, 3, '2025-12-26 16:34:46'),
(4, 'Making Charge Amount', 'making_charge_amount', 1, 4, '2025-12-26 16:34:46'),
(5, 'Metal Exchange Amount', 'metal_exchange_amount', 1, 5, '2025-12-26 16:34:46'),
(6, 'Gold Amount', 'gold_amount', 1, 6, '2025-12-26 16:34:46');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_campaign_group`
--

CREATE TABLE `tbl_campaign_group` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `name` varchar(100) NOT NULL,
  `status` tinyint(4) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_carat`
--

CREATE TABLE `tbl_carat` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `name` varchar(50) NOT NULL,
  `metal_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'tbl_metal.id',
  `purity` decimal(6,3) DEFAULT NULL,
  `purity_sales` decimal(12,3) DEFAULT NULL COMMENT 'Purity % for sales vouchers',
  `purity_purchase` decimal(12,3) DEFAULT NULL COMMENT 'Purity % for purchase vouchers',
  `purity_common` decimal(12,3) DEFAULT NULL COMMENT 'Purity % for common/stock',
  `purity_for` varchar(20) NOT NULL DEFAULT 'common' COMMENT 'sales|purchase|common — where this carat/purity appears',
  `description` varchar(255) DEFAULT NULL,
  `dashboard_image_path` varchar(512) DEFAULT NULL COMMENT 'Relative to admin/, e.g. uploads/metal-dashboard/x.jpg',
  `dashboard_image_url` varchar(1024) DEFAULT NULL COMMENT 'External image URL (optional)',
  `status` tinyint(4) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_cash_denomination`
--

CREATE TABLE `tbl_cash_denomination` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `type` varchar(20) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) NOT NULL,
  `status` tinyint(4) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_categories`
--

CREATE TABLE `tbl_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_cities`
--

CREATE TABLE `tbl_cities` (
  `id` int(11) NOT NULL,
  `state_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `comment` text DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_cities`
--

INSERT INTO `tbl_cities` (`id`, `state_id`, `name`, `comment`, `status`) VALUES
(1, 1, 'Abu Dhabi', NULL, 1),
(2, 1, 'Al Ain', NULL, 1),
(3, 1, 'Madinat Zayed', NULL, 1),
(4, 2, 'Dubai', NULL, 1),
(5, 3, 'Sharjah', NULL, 1),
(6, 3, 'Khor Fakkan', NULL, 1),
(7, 4, 'Ajman', NULL, 1),
(8, 5, 'Umm Al Quwain', NULL, 1),
(9, 6, 'Ras Al Khaimah', NULL, 1),
(10, 7, 'Fujairah', NULL, 1),
(11, 8, 'Mumbai', NULL, 1),
(12, 8, 'Pune', NULL, 1),
(13, 8, 'Nagpur', NULL, 1),
(14, 9, 'Ahmedabad', NULL, 1),
(15, 9, 'Surat', NULL, 1),
(16, 9, 'Vadodara', NULL, 1),
(17, 10, 'Kochi', NULL, 1),
(18, 10, 'Thiruvananthapuram', NULL, 1),
(19, 10, 'Kozhikode', NULL, 1),
(20, 11, 'Bengaluru', NULL, 1),
(21, 11, 'Mysuru', NULL, 1),
(22, 11, 'Mangaluru', NULL, 1),
(23, 12, 'Chennai', NULL, 1),
(24, 12, 'Coimbatore', NULL, 1),
(25, 12, 'Madurai', NULL, 1),
(26, 13, 'New Delhi', NULL, 1),
(27, 13, 'North Delhi', NULL, 1),
(28, 14, 'Kolkata', NULL, 1),
(29, 14, 'Howrah', NULL, 1),
(30, 15, 'Jaipur', NULL, 1),
(31, 15, 'Jodhpur', NULL, 1),
(32, 15, 'Udaipur', NULL, 1),
(33, 16, 'Lucknow', NULL, 1),
(34, 16, 'Kanpur', NULL, 1),
(35, 16, 'Noida', NULL, 1),
(36, 17, 'Hyderabad', NULL, 1),
(37, 17, 'Warangal', NULL, 1),
(38, 18, 'Visakhapatnam', NULL, 1),
(39, 18, 'Vijayawada', NULL, 1),
(40, 19, 'Ludhiana', NULL, 1),
(41, 19, 'Amritsar', NULL, 1),
(42, 20, 'Gurugram', NULL, 1),
(43, 20, 'Faridabad', NULL, 1),
(44, 21, 'Indore', NULL, 1),
(45, 21, 'Bhopal', NULL, 1),
(46, 22, 'Patna', NULL, 1),
(47, 22, 'Gaya', NULL, 1),
(48, 23, 'Bhubaneswar', NULL, 1),
(49, 23, 'Cuttack', NULL, 1),
(50, 24, 'Guwahati', NULL, 1),
(51, 24, 'Silchar', NULL, 1),
(52, 25, 'Other', NULL, 1),
(53, 26, 'Other', NULL, 1),
(54, 27, 'Other', NULL, 1),
(55, 28, 'Other', NULL, 1),
(56, 29, 'Other', NULL, 1),
(57, 30, 'Other', NULL, 1),
(58, 31, 'Other', NULL, 1),
(59, 32, 'Other', NULL, 1),
(60, 33, 'Other', NULL, 1),
(61, 34, 'Other', NULL, 1),
(62, 35, 'Other', NULL, 1),
(63, 36, 'Other', NULL, 1),
(64, 37, 'Other', NULL, 1),
(65, 38, 'Other', NULL, 1),
(66, 39, 'Other', NULL, 1),
(67, 40, 'Other', NULL, 1),
(68, 41, 'Other', NULL, 1),
(69, 42, 'Other', NULL, 1),
(70, 43, 'Other', NULL, 1),
(71, 44, 'Other', NULL, 1),
(72, 45, 'Other', NULL, 1),
(73, 46, 'Other', NULL, 1),
(74, 47, 'Other', NULL, 1),
(75, 48, 'Other', NULL, 1),
(76, 49, 'Other', NULL, 1),
(77, 50, 'Other', NULL, 1),
(78, 51, 'Other', NULL, 1),
(79, 52, 'Other', NULL, 1),
(80, 53, 'Other', NULL, 1),
(81, 54, 'Other', NULL, 1),
(82, 55, 'Other', NULL, 1),
(83, 56, 'Other', NULL, 1),
(84, 57, 'Other', NULL, 1),
(85, 58, 'Other', NULL, 1),
(86, 59, 'Other', NULL, 1),
(87, 60, 'Other', NULL, 1),
(88, 61, 'Other', NULL, 1),
(89, 62, 'Other', NULL, 1),
(90, 63, 'Other', NULL, 1),
(91, 64, 'Other', NULL, 1),
(92, 65, 'Other', NULL, 1),
(93, 66, 'Other', NULL, 1),
(94, 67, 'Other', NULL, 1),
(95, 68, 'Other', NULL, 1),
(96, 69, 'Other', NULL, 1),
(97, 70, 'Other', NULL, 1),
(98, 71, 'Other', NULL, 1),
(99, 72, 'Other', NULL, 1),
(100, 73, 'Other', NULL, 1),
(101, 74, 'Other', NULL, 1),
(102, 75, 'Other', NULL, 1),
(103, 76, 'Other', NULL, 1),
(104, 77, 'Other', NULL, 1),
(105, 78, 'Other', NULL, 1),
(106, 79, 'Other', NULL, 1),
(107, 80, 'Other', NULL, 1),
(108, 81, 'Other', NULL, 1),
(109, 82, 'Other', NULL, 1),
(110, 83, 'Other', NULL, 1),
(111, 84, 'Other', NULL, 1),
(112, 85, 'Other', NULL, 1),
(113, 86, 'Other', NULL, 1),
(114, 87, 'Other', NULL, 1),
(115, 88, 'Other', NULL, 1),
(116, 89, 'Other', NULL, 1),
(117, 90, 'Other', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_clarity`
--

CREATE TABLE `tbl_clarity` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `name` varchar(100) NOT NULL,
  `status` tinyint(4) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_collection`
--

CREATE TABLE `tbl_collection` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1 COMMENT '1=Active,0=Deleted',
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_color`
--

CREATE TABLE `tbl_color` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `name` varchar(100) NOT NULL,
  `status` tinyint(4) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_consignment_in`
--

CREATE TABLE `tbl_consignment_in` (
  `id` int(11) NOT NULL,
  `consignment_no` varchar(50) NOT NULL COMMENT 'CI-1, CI-2, etc.',
  `consignment_out_id` int(11) DEFAULT NULL COMMENT 'Reference to original consignment out record',
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `consignment_date` date NOT NULL,
  `ref_no` varchar(100) DEFAULT NULL,
  `against_of` varchar(100) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'AED',
  `fixing_type` varchar(50) DEFAULT 'Standard',
  `sales_person` varchar(255) DEFAULT NULL,
  `previous_balance` decimal(15,2) DEFAULT 0.00,
  `previous_gold` decimal(10,3) DEFAULT 0.000,
  `previous_silver` decimal(10,3) DEFAULT 0.000,
  `gross_total` decimal(15,2) DEFAULT 0.00,
  `discount_amount` decimal(15,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `grand_total` decimal(15,2) DEFAULT 0.00,
  `total_quantity` int(11) DEFAULT 0,
  `total_gross_weight` decimal(10,3) DEFAULT 0.000,
  `total_net_weight` decimal(10,3) DEFAULT 0.000,
  `total_pure_weight` decimal(10,3) DEFAULT 0.000,
  `comment` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'active' COMMENT 'active, cancelled',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_consignment_in_items`
--

CREATE TABLE `tbl_consignment_in_items` (
  `id` int(11) NOT NULL,
  `consignment_id` int(11) NOT NULL,
  `consignment_out_item_id` int(11) DEFAULT NULL COMMENT 'Reference to original consignment out item',
  `product_id` int(11) DEFAULT NULL,
  `product_characteristic_id` int(11) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `design_no` varchar(100) DEFAULT NULL,
  `huid_no` varchar(100) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `calculation_mode` varchar(50) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `metal_id` int(11) DEFAULT NULL,
  `carat` varchar(50) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `gross_weight` decimal(10,3) DEFAULT 0.000,
  `less_weight` decimal(10,3) DEFAULT 0.000,
  `net_weight` decimal(10,3) DEFAULT 0.000,
  `purity` decimal(10,4) DEFAULT 0.0000,
  `purity_weight` decimal(10,3) DEFAULT 0.000,
  `wastage_percent` decimal(10,2) DEFAULT 0.00,
  `wastage_weight` decimal(10,3) DEFAULT 0.000,
  `final_weight` decimal(10,3) DEFAULT 0.000,
  `pure_weight` decimal(10,3) DEFAULT 0.000,
  `rate` decimal(15,2) DEFAULT 0.00,
  `metal_value` decimal(15,2) DEFAULT 0.00,
  `amount` decimal(15,2) DEFAULT 0.00,
  `making_type` varchar(50) DEFAULT NULL,
  `making_rate` decimal(15,2) DEFAULT 0.00,
  `making_amount` decimal(15,2) DEFAULT 0.00,
  `stone_weight` decimal(10,3) DEFAULT 0.000,
  `stone_rate` decimal(15,2) DEFAULT 0.00,
  `stone_amount` decimal(15,2) DEFAULT 0.00,
  `diamond_amount` decimal(15,2) DEFAULT 0.00,
  `other_amount` decimal(15,2) DEFAULT 0.00,
  `discount_percent` decimal(10,2) DEFAULT 0.00,
  `discount_amount` decimal(15,2) DEFAULT 0.00,
  `tax_percent` decimal(10,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) DEFAULT 0.00,
  `net_amt_with_tax` decimal(15,2) DEFAULT 0.00,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_consignment_out`
--

CREATE TABLE `tbl_consignment_out` (
  `id` int(11) NOT NULL,
  `consignment_no` varchar(50) NOT NULL COMMENT 'CO-1, CO-2, etc.',
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `consignment_date` date NOT NULL,
  `ref_no` varchar(100) DEFAULT NULL,
  `against_of` varchar(100) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'AED',
  `fixing_type` varchar(50) DEFAULT 'Standard',
  `sales_person` varchar(255) DEFAULT NULL,
  `previous_balance` decimal(15,2) DEFAULT 0.00,
  `previous_gold` decimal(10,3) DEFAULT 0.000,
  `previous_silver` decimal(10,3) DEFAULT 0.000,
  `gross_total` decimal(15,2) DEFAULT 0.00,
  `discount_amount` decimal(15,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `grand_total` decimal(15,2) DEFAULT 0.00,
  `total_quantity` int(11) DEFAULT 0,
  `total_gross_weight` decimal(10,3) DEFAULT 0.000,
  `total_net_weight` decimal(10,3) DEFAULT 0.000,
  `total_pure_weight` decimal(10,3) DEFAULT 0.000,
  `comment` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'active' COMMENT 'active, cancelled, returned',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_consignment_out_items`
--

CREATE TABLE `tbl_consignment_out_items` (
  `id` int(11) NOT NULL,
  `consignment_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_characteristic_id` int(11) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `design_no` varchar(100) DEFAULT NULL,
  `huid_no` varchar(100) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `calculation_mode` varchar(50) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `metal_id` int(11) DEFAULT NULL,
  `carat` varchar(50) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `gross_weight` decimal(10,3) DEFAULT 0.000,
  `less_weight` decimal(10,3) DEFAULT 0.000,
  `net_weight` decimal(10,3) DEFAULT 0.000,
  `purity` decimal(10,4) DEFAULT 0.0000,
  `purity_weight` decimal(10,3) DEFAULT 0.000,
  `wastage_percent` decimal(10,2) DEFAULT 0.00,
  `wastage_weight` decimal(10,3) DEFAULT 0.000,
  `final_weight` decimal(10,3) DEFAULT 0.000,
  `pure_weight` decimal(10,3) DEFAULT 0.000,
  `rate` decimal(15,2) DEFAULT 0.00,
  `metal_value` decimal(15,2) DEFAULT 0.00,
  `amount` decimal(15,2) DEFAULT 0.00,
  `making_type` varchar(50) DEFAULT NULL,
  `making_rate` decimal(15,2) DEFAULT 0.00,
  `making_amount` decimal(15,2) DEFAULT 0.00,
  `stone_weight` decimal(10,3) DEFAULT 0.000,
  `stone_rate` decimal(15,2) DEFAULT 0.00,
  `stone_amount` decimal(15,2) DEFAULT 0.00,
  `diamond_amount` decimal(15,2) DEFAULT 0.00,
  `other_amount` decimal(15,2) DEFAULT 0.00,
  `discount_percent` decimal(10,2) DEFAULT 0.00,
  `discount_amount` decimal(15,2) DEFAULT 0.00,
  `tax_percent` decimal(10,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) DEFAULT 0.00,
  `net_amt_with_tax` decimal(15,2) DEFAULT 0.00,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_contra_vouchers`
--

CREATE TABLE `tbl_contra_vouchers` (
  `id` int(11) NOT NULL,
  `voucher_no` varchar(50) NOT NULL,
  `voucher_date` date NOT NULL,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `comment` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_contra_voucher_items`
--

CREATE TABLE `tbl_contra_voucher_items` (
  `id` int(11) NOT NULL,
  `voucher_id` int(11) NOT NULL,
  `bank_cash_ac` varchar(100) NOT NULL COMMENT 'Bank or Cash account name',
  `ref_no` varchar(100) DEFAULT NULL,
  `ref_date` date DEFAULT NULL,
  `transaction_type` varchar(20) NOT NULL DEFAULT 'withdrawal' COMMENT 'deposit or withdrawal',
  `amount` decimal(15,2) DEFAULT 0.00,
  `comment` text DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_counter`
--

CREATE TABLE `tbl_counter` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `name` varchar(100) NOT NULL,
  `location` varchar(100) DEFAULT NULL,
  `description` varchar(150) DEFAULT NULL,
  `status` tinyint(4) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_countries`
--

CREATE TABLE `tbl_countries` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(10) DEFAULT NULL,
  `code3` varchar(3) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_countries`
--

INSERT INTO `tbl_countries` (`id`, `name`, `code`, `code3`, `comment`, `status`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Afghanistan', 'AF', 'AFG', NULL, 1, 1, '2025-12-30 15:50:03', NULL),
(2, 'Albania', 'AL', 'ALB', NULL, 1, 2, '2025-12-30 15:50:03', NULL),
(3, 'Algeria', 'DZ', 'DZA', NULL, 1, 3, '2025-12-30 15:50:03', NULL),
(4, 'Angola', 'AO', 'AGO', NULL, 1, 4, '2025-12-30 15:50:03', NULL),
(5, 'Argentina', 'AR', 'ARG', NULL, 1, 5, '2025-12-30 15:50:03', NULL),
(6, 'Australia', 'AU', 'AUS', NULL, 1, 6, '2025-12-30 15:50:03', NULL),
(7, 'Austria', 'AT', 'AUT', NULL, 1, 7, '2025-12-30 15:50:03', NULL),
(8, 'Bahrain', 'BH', 'BHR', NULL, 1, 8, '2025-12-30 15:50:03', NULL),
(9, 'Bangladesh', 'BD', 'BGD', NULL, 1, 9, '2025-12-30 15:50:03', NULL),
(10, 'Belgium', 'BE', 'BEL', NULL, 1, 10, '2025-12-30 15:50:03', NULL),
(11, 'Brazil', 'BR', 'BRA', NULL, 1, 11, '2025-12-30 15:50:03', NULL),
(12, 'Canada', 'CA', 'CAN', NULL, 1, 12, '2025-12-30 15:50:03', NULL),
(13, 'China', 'CN', 'CHN', NULL, 1, 13, '2025-12-30 15:50:03', NULL),
(14, 'Egypt', 'EG', 'EGY', NULL, 1, 14, '2025-12-30 15:50:03', NULL),
(15, 'France', 'FR', 'FRA', NULL, 1, 15, '2025-12-30 15:50:03', NULL),
(16, 'Germany', 'DE', 'DEU', NULL, 1, 16, '2025-12-30 15:50:03', NULL),
(17, 'Ghana', 'GH', 'GHA', NULL, 1, 17, '2025-12-30 15:50:03', NULL),
(18, 'Greece', 'GR', 'GRC', NULL, 1, 18, '2025-12-30 15:50:03', NULL),
(19, 'Guinea-Bissau', 'GW', 'GNB', NULL, 1, 19, '2025-12-30 15:50:03', NULL),
(20, 'Guyana', 'GY', 'GUY', NULL, 1, 20, '2025-12-30 15:50:03', NULL),
(21, 'Haiti', 'HT', 'HTI', NULL, 1, 21, '2025-12-30 15:50:03', NULL),
(22, 'Heard Island and McDonald Islands', 'HM', 'HMD', NULL, 1, 22, '2025-12-30 15:50:03', NULL),
(23, 'Honduras', 'HN', 'HND', NULL, 1, 23, '2025-12-30 15:50:03', NULL),
(24, 'Hong Kong S.A.R.', 'HK', 'HKG', NULL, 1, 24, '2025-12-30 15:50:03', NULL),
(25, 'Hungary', 'HU', 'HUN', NULL, 1, 25, '2025-12-30 15:50:03', NULL),
(26, 'India', 'IN', 'IND', NULL, 1, 26, '2025-12-30 15:50:03', NULL),
(27, 'Indonesia', 'ID', 'IDN', NULL, 1, 27, '2025-12-30 15:50:03', NULL),
(28, 'Iran', 'IR', 'IRN', NULL, 1, 28, '2025-12-30 15:50:03', NULL),
(29, 'Iraq', 'IQ', 'IRQ', NULL, 1, 29, '2025-12-30 15:50:03', NULL),
(30, 'Ireland', 'IE', 'IRL', NULL, 1, 30, '2025-12-30 15:50:03', NULL),
(31, 'Italy', 'IT', 'ITA', NULL, 1, 31, '2025-12-30 15:50:03', NULL),
(32, 'Japan', 'JP', 'JPN', NULL, 1, 32, '2025-12-30 15:50:03', NULL),
(33, 'Jersey', 'JE', 'JEY', NULL, 1, 33, '2025-12-30 15:50:03', NULL),
(34, 'Jordan', 'JO', 'JOR', NULL, 1, 34, '2025-12-30 15:50:03', NULL),
(35, 'Kenya', 'KE', 'KEN', NULL, 1, 35, '2025-12-30 15:50:03', NULL),
(36, 'Kuwait', 'KW', 'KWT', NULL, 1, 36, '2025-12-30 15:50:03', NULL),
(37, 'Lebanon', 'LB', 'LBN', NULL, 1, 37, '2025-12-30 15:50:03', NULL),
(38, 'Libya', 'LY', 'LBY', NULL, 1, 38, '2025-12-30 15:50:03', NULL),
(39, 'Malaysia', 'MY', 'MYS', NULL, 1, 39, '2025-12-30 15:50:03', NULL),
(40, 'Mexico', 'MX', 'MEX', NULL, 1, 40, '2025-12-30 15:50:03', NULL),
(41, 'Morocco', 'MA', 'MAR', NULL, 1, 41, '2025-12-30 15:50:03', NULL),
(42, 'Nepal', 'NP', 'NPL', NULL, 1, 42, '2025-12-30 15:50:03', NULL),
(43, 'Netherlands', 'NL', 'NLD', NULL, 1, 43, '2025-12-30 15:50:03', NULL),
(44, 'New Zealand', 'NZ', 'NZL', NULL, 1, 44, '2025-12-30 15:50:03', NULL),
(45, 'Nigeria', 'NG', 'NGA', NULL, 1, 45, '2025-12-30 15:50:03', NULL),
(46, 'Oman', 'OM', 'OMN', NULL, 1, 46, '2025-12-30 15:50:03', NULL),
(47, 'Pakistan', 'PK', 'PAK', NULL, 1, 47, '2025-12-30 15:50:03', NULL),
(48, 'Palestine', 'PS', 'PSE', NULL, 1, 48, '2025-12-30 15:50:03', NULL),
(49, 'Philippines', 'PH', 'PHL', NULL, 1, 49, '2025-12-30 15:50:03', NULL),
(50, 'Qatar', 'QA', 'QAT', NULL, 1, 50, '2025-12-30 15:50:03', NULL),
(51, 'Russia', 'RU', 'RUS', NULL, 1, 51, '2025-12-30 15:50:03', NULL),
(52, 'Saudi Arabia', 'SA', 'SAU', NULL, 1, 52, '2025-12-30 15:50:03', NULL),
(53, 'Singapore', 'SG', 'SGP', NULL, 1, 53, '2025-12-30 15:50:03', NULL),
(54, 'South Africa', 'ZA', 'ZAF', NULL, 1, 54, '2025-12-30 15:50:03', NULL),
(55, 'South Korea', 'KR', 'KOR', NULL, 1, 55, '2025-12-30 15:50:03', NULL),
(56, 'Spain', 'ES', 'ESP', NULL, 1, 56, '2025-12-30 15:50:03', NULL),
(57, 'Sri Lanka', 'LK', 'LKA', NULL, 1, 57, '2025-12-30 15:50:03', NULL),
(58, 'Sudan', 'SD', 'SDN', NULL, 1, 58, '2025-12-30 15:50:03', NULL),
(59, 'Switzerland', 'CH', 'CHE', NULL, 1, 59, '2025-12-30 15:50:03', NULL),
(60, 'Syria', 'SY', 'SYR', NULL, 1, 60, '2025-12-30 15:50:03', NULL),
(61, 'Thailand', 'TH', 'THA', NULL, 1, 61, '2025-12-30 15:50:03', NULL),
(62, 'Tunisia', 'TN', 'TUN', NULL, 1, 62, '2025-12-30 15:50:03', NULL),
(63, 'Turkey', 'TR', 'TUR', NULL, 1, 63, '2025-12-30 15:50:03', NULL),
(64, 'Ukraine', 'UA', 'UKR', NULL, 1, 64, '2025-12-30 15:50:03', NULL),
(65, 'United Arab Emirates', 'AE', 'ARE', NULL, 1, 65, '2025-12-30 15:50:03', NULL),
(66, 'United Kingdom', 'GB', 'GBR', NULL, 1, 66, '2025-12-30 15:50:03', NULL),
(67, 'United States', 'US', 'USA', NULL, 1, 67, '2025-12-30 15:50:03', NULL),
(68, 'Yemen', 'YE', 'YEM', NULL, 1, 68, '2025-12-30 15:50:03', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_credit_card`
--

CREATE TABLE `tbl_credit_card` (
  `id` int(10) UNSIGNED NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `name` varchar(255) NOT NULL DEFAULT '',
  `account_group` varchar(255) NOT NULL DEFAULT '' COMMENT 'Account ledger name',
  `commission_account` varchar(255) NOT NULL DEFAULT '' COMMENT 'Commission ledger name',
  `commission_percent` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_credit_notes`
--

CREATE TABLE `tbl_credit_notes` (
  `id` int(11) NOT NULL,
  `credit_note_no` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `against_of` varchar(100) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'AED',
  `ref_no` varchar(100) DEFAULT NULL,
  `sales_person` varchar(255) DEFAULT NULL,
  `credit_note_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `layaways_id` int(11) DEFAULT NULL,
  `fixing_type` varchar(50) DEFAULT 'Standard',
  `previous_balance` decimal(15,2) DEFAULT 0.00,
  `previous_gold` decimal(15,2) DEFAULT 0.00,
  `previous_silver` decimal(15,2) DEFAULT 0.00,
  `subtotal` decimal(15,2) DEFAULT 0.00,
  `additional_amt` decimal(15,2) DEFAULT 0.00,
  `net_total` decimal(15,2) DEFAULT 0.00,
  `reward_points` decimal(15,2) DEFAULT 0.00,
  `coupon_code` varchar(50) DEFAULT NULL,
  `coupon_discount` decimal(15,2) DEFAULT 0.00,
  `discount_amt` decimal(15,2) DEFAULT 0.00,
  `redeem_points` decimal(15,2) DEFAULT 0.00,
  `grand_total` decimal(15,2) DEFAULT 0.00,
  `advance_payment` decimal(15,2) DEFAULT 0.00,
  `metal_amt` decimal(15,2) DEFAULT 0.00,
  `round_off` decimal(15,2) DEFAULT 0.00,
  `paid_amt` decimal(15,2) DEFAULT 0.00,
  `balance_amt` decimal(15,2) DEFAULT 0.00,
  `group_name` varchar(255) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_credit_note_items`
--

CREATE TABLE `tbl_credit_note_items` (
  `id` int(11) NOT NULL,
  `credit_note_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_characteristic_id` int(11) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `carat` varchar(50) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 1.00,
  `gross_weight` decimal(10,3) DEFAULT 0.000,
  `less_weight` decimal(10,3) DEFAULT 0.000,
  `purity` decimal(10,2) DEFAULT 0.00,
  `purity_weight` decimal(10,3) DEFAULT 0.000,
  `final_weight` decimal(10,3) DEFAULT 0.000,
  `net_weight` decimal(10,3) DEFAULT 0.000,
  `pure_weight` decimal(10,3) DEFAULT 0.000,
  `rate` decimal(15,2) DEFAULT 0.00,
  `making_amount` decimal(15,2) DEFAULT 0.00,
  `amount` decimal(15,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) DEFAULT 0.00,
  `net_amt_with_tax` decimal(15,2) DEFAULT 0.00,
  `design_no` varchar(100) DEFAULT NULL,
  `location_id` int(11) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_credit_note_payments`
--

CREATE TABLE `tbl_credit_note_payments` (
  `id` int(11) NOT NULL,
  `credit_note_id` int(11) NOT NULL,
  `payment_type` varchar(50) NOT NULL,
  `deposit_into` varchar(100) DEFAULT NULL,
  `transaction_no` varchar(100) DEFAULT NULL,
  `cheque_date` date DEFAULT NULL,
  `purity_carat` varchar(50) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `previous_balance_amount` decimal(15,2) DEFAULT 0.00,
  `current_order_amount` decimal(15,2) DEFAULT 0.00,
  `diamond_category` varchar(100) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 0.00,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_crm_contact_groups`
--

CREATE TABLE `tbl_crm_contact_groups` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_crm_contact_group_members`
--

CREATE TABLE `tbl_crm_contact_group_members` (
  `id` int(10) UNSIGNED NOT NULL,
  `group_id` int(10) UNSIGNED NOT NULL,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_crm_whatsapp_campaigns`
--

CREATE TABLE `tbl_crm_whatsapp_campaigns` (
  `id` int(10) UNSIGNED NOT NULL,
  `caption` varchar(500) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `contact_no` varchar(64) DEFAULT NULL,
  `message_body` mediumtext DEFAULT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `branch_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `created_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_crm_whatsapp_campaign_images`
--

CREATE TABLE `tbl_crm_whatsapp_campaign_images` (
  `id` int(10) UNSIGNED NOT NULL,
  `campaign_id` int(10) UNSIGNED NOT NULL,
  `image_path` varchar(512) NOT NULL,
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_currency`
--

CREATE TABLE `tbl_currency` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `name` varchar(50) NOT NULL,
  `decimal_places` int(11) DEFAULT 2,
  `symbol` varchar(20) DEFAULT NULL,
  `description` varchar(150) DEFAULT NULL,
  `is_base` tinyint(1) DEFAULT 0,
  `status` tinyint(4) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `tbl_currency`
--

INSERT INTO `tbl_currency` (`id`, `branch_id`, `name`, `decimal_places`, `symbol`, `description`, `is_base`, `status`, `created_by`, `modified_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'INR', 2, 'Rs', '', 1, 1, 1, NULL, '2026-04-17 12:52:34', NULL),
(2, 47, 'INR', 2, 'Rs', '', 1, 1, 1, NULL, '2026-05-05 12:39:23', NULL),
(3, 63, 'INR', 2, 'Rs', '', 1, 1, 1, NULL, '2026-06-05 18:02:10', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_currency_exchange_rate`
--

CREATE TABLE `tbl_currency_exchange_rate` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `currency_id` int(11) NOT NULL,
  `rate` decimal(12,6) NOT NULL,
  `description` varchar(150) DEFAULT NULL,
  `status` tinyint(4) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_customers`
--

CREATE TABLE `tbl_customers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `alternate_name` varchar(255) DEFAULT NULL,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `mobile_country_code` varchar(10) DEFAULT '971',
  `mobile_no` varchar(50) DEFAULT NULL,
  `phone_no` varchar(50) DEFAULT NULL,
  `mail_id` varchar(255) DEFAULT NULL,
  `identity_no` varchar(100) DEFAULT NULL,
  `national_id` varchar(100) DEFAULT NULL,
  `trade_no` varchar(100) DEFAULT NULL,
  `identity_issue_date` date DEFAULT NULL,
  `identity_expiry_date` date DEFAULT NULL,
  `special_day` date DEFAULT NULL,
  `customer_type_id` int(11) DEFAULT 0,
  `registration_no` varchar(100) DEFAULT NULL,
  `registration_date` date DEFAULT NULL,
  `nationality_id` int(11) DEFAULT 0,
  `country_id` int(11) DEFAULT 0,
  `date1` date DEFAULT NULL,
  `date2` date DEFAULT NULL,
  `group_id` int(11) DEFAULT 0,
  `sundry_debtors_id` int(11) DEFAULT 0,
  `ledger_name_capital` tinyint(1) DEFAULT 0,
  `kyc` tinyint(1) DEFAULT 0,
  `aml` tinyint(1) DEFAULT 0,
  `bill_to_bill` tinyint(1) DEFAULT 0,
  `billing_address1` text DEFAULT NULL,
  `billing_address2` text DEFAULT NULL,
  `billing_country` varchar(100) DEFAULT NULL,
  `billing_state` varchar(100) DEFAULT NULL,
  `billing_city` varchar(100) DEFAULT NULL,
  `billing_zip_code` varchar(20) DEFAULT NULL,
  `shipping_address1` text DEFAULT NULL,
  `shipping_address2` text DEFAULT NULL,
  `shipping_country` varchar(100) DEFAULT NULL,
  `shipping_state` varchar(100) DEFAULT NULL,
  `shipping_city` varchar(100) DEFAULT NULL,
  `shipping_zip_code` varchar(20) DEFAULT NULL,
  `bank_account_no` varchar(100) DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `bank_ifsc_code` varchar(50) DEFAULT NULL,
  `bank_branch` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `item_tax_data` text DEFAULT NULL,
  `ledger_photo` varchar(255) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `share_holders_data` text DEFAULT NULL,
  `share_holder_documents` text DEFAULT NULL,
  `phone_country_code` varchar(10) DEFAULT '971',
  `ledger_state_id` int(11) NOT NULL DEFAULT 0,
  `ledger_city_id` int(11) NOT NULL DEFAULT 0,
  `gstin` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_customers`
--

INSERT INTO `tbl_customers` (`id`, `name`, `alternate_name`, `first_name`, `last_name`, `mobile_country_code`, `mobile_no`, `phone_no`, `mail_id`, `identity_no`, `national_id`, `trade_no`, `identity_issue_date`, `identity_expiry_date`, `special_day`, `customer_type_id`, `registration_no`, `registration_date`, `nationality_id`, `country_id`, `date1`, `date2`, `group_id`, `sundry_debtors_id`, `ledger_name_capital`, `kyc`, `aml`, `bill_to_bill`, `billing_address1`, `billing_address2`, `billing_country`, `billing_state`, `billing_city`, `billing_zip_code`, `shipping_address1`, `shipping_address2`, `shipping_country`, `shipping_state`, `shipping_city`, `shipping_zip_code`, `bank_account_no`, `bank_name`, `bank_ifsc_code`, `bank_branch`, `notes`, `item_tax_data`, `ledger_photo`, `status`, `created_at`, `updated_at`, `share_holders_data`, `share_holder_documents`, `phone_country_code`, `ledger_state_id`, `ledger_city_id`, `gstin`) VALUES
(2, 'SRI', '', 'SRI', '', '91', '01', '', '', '', '', '', NULL, NULL, NULL, 11, '', NULL, 0, 26, NULL, NULL, 0, 2, 0, 0, 0, 0, '', '', 'India', 'Maharashtra', 'Nagpur', '', '', '', 'India', 'Maharashtra', 'Nagpur', '', '', '', '', '', '', '{\"AMOUNT\":{\"input_type\":\"VAT\",\"output_type\":\"VAT\"},\"Gold\":{\"input_type\":\"VAT\",\"output_type\":\"VAT\"},\"GOLD_MAKING\":{\"input_type\":\"VAT\",\"output_type\":\"VAT\"},\"Silver\":{\"input_type\":\"VAT\",\"output_type\":\"VAT\"},\"SILVER_MAKING\":{\"input_type\":\"VAT\",\"output_type\":\"VAT\"},\"Diamond_Stones\":{\"input_type\":\"VAT\",\"output_type\":\"VAT\"},\"Imitation_Watches\":{\"input_type\":\"VAT\",\"output_type\":\"VAT\"},\"LOOSE_DIAMOND\":{\"input_type\":\"VAT\",\"output_type\":\"VAT\"},\"CERTIFIED_DIAMOND\":{\"input_type\":\"VAT\",\"output_type\":\"VAT\"},\"Other_Services\":{\"input_type\":\"VAT\",\"output_type\":\"VAT\"}}', '', 1, '2026-06-06 12:20:15', NULL, '[]', '[]', '91', 8, 13, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_customer_advance_policy`
--

CREATE TABLE `tbl_customer_advance_policy` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `policy_name` varchar(150) NOT NULL,
  `days_duration` int(11) NOT NULL,
  `min_gold_percent` decimal(5,2) NOT NULL,
  `status` tinyint(4) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_customer_advance_vouchers`
--

CREATE TABLE `tbl_customer_advance_vouchers` (
  `id` int(11) NOT NULL,
  `voucher_no` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `ref_no` varchar(100) DEFAULT NULL,
  `voucher_type` varchar(50) DEFAULT NULL,
  `against` varchar(100) DEFAULT NULL,
  `sales_person` varchar(255) DEFAULT NULL,
  `against_of` varchar(100) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'AED',
  `voucher_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `layaways_id` int(11) DEFAULT NULL,
  `fixing_type` varchar(50) DEFAULT 'Standard',
  `previous_balance` decimal(15,2) DEFAULT 0.00,
  `previous_gold` decimal(10,3) DEFAULT 0.000,
  `previous_silver` decimal(10,3) DEFAULT 0.000,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `total_gold` decimal(10,3) DEFAULT 0.000,
  `total_silver` decimal(10,3) DEFAULT 0.000,
  `comment` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_customer_advance_voucher_items`
--

CREATE TABLE `tbl_customer_advance_voucher_items` (
  `id` int(11) NOT NULL,
  `voucher_id` int(11) NOT NULL,
  `payment_type` varchar(50) DEFAULT NULL,
  `diamond_category` varchar(100) DEFAULT NULL,
  `transaction_no` varchar(100) DEFAULT NULL,
  `deposit_into` varchar(100) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `cheque_date` date DEFAULT NULL,
  `weight` decimal(10,3) DEFAULT 0.000,
  `metal_id` int(11) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 0.00,
  `purity_carat` varchar(50) DEFAULT NULL,
  `purity_wt` decimal(10,3) DEFAULT 0.000,
  `amount` decimal(15,2) DEFAULT 0.00,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_customer_balance`
--

CREATE TABLE `tbl_customer_balance` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `balance_amount` decimal(15,2) DEFAULT 0.00,
  `balance_gold` decimal(10,3) DEFAULT 0.000,
  `balance_silver` decimal(10,3) DEFAULT 0.000,
  `last_transaction_date` date DEFAULT NULL,
  `last_updated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_customer_ledger`
--

CREATE TABLE `tbl_customer_ledger` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `branch_id` int(10) UNSIGNED DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `transaction_type` varchar(50) NOT NULL COMMENT 'sale_order, purchase_invoice, payment, receipt, advance, return',
  `transaction_id` int(11) DEFAULT NULL COMMENT 'ID of related transaction (order_id, invoice_id, etc.)',
  `transaction_no` varchar(100) DEFAULT NULL COMMENT 'Order/Invoice number',
  `transaction_date` date NOT NULL,
  `debit_amount` decimal(15,2) DEFAULT 0.00 COMMENT 'Amount customer owes (sale orders, purchases)',
  `credit_amount` decimal(15,2) DEFAULT 0.00 COMMENT 'Amount customer paid (payments, receipts)',
  `debit_gold` decimal(10,3) DEFAULT 0.000 COMMENT 'Gold weight customer owes',
  `credit_gold` decimal(10,3) DEFAULT 0.000 COMMENT 'Gold weight customer paid',
  `debit_gold_pure` decimal(10,3) DEFAULT 0.000 COMMENT 'Gold pure weight (debit)',
  `credit_gold_pure` decimal(10,3) DEFAULT 0.000 COMMENT 'Gold pure weight (credit)',
  `debit_silver` decimal(10,3) DEFAULT 0.000 COMMENT 'Silver weight customer owes',
  `credit_silver` decimal(10,3) DEFAULT 0.000 COMMENT 'Silver weight customer paid',
  `debit_diamond` decimal(12,3) NOT NULL DEFAULT 0.000,
  `credit_diamond` decimal(12,3) NOT NULL DEFAULT 0.000,
  `balance_amount` decimal(15,2) DEFAULT 0.00 COMMENT 'Running balance amount',
  `balance_gold` decimal(10,3) DEFAULT 0.000 COMMENT 'Running balance gold',
  `balance_gold_pure` decimal(10,3) DEFAULT 0.000 COMMENT 'Running balance gold pure',
  `balance_silver` decimal(10,3) DEFAULT 0.000 COMMENT 'Running balance silver',
  `balance_diamond` decimal(12,3) NOT NULL DEFAULT 0.000,
  `description` text DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `against_ledger` varchar(255) DEFAULT NULL COMMENT 'Against Ledger name with balance (e.g., ABC(640.00Dr))',
  `against_invoice_no` varchar(100) DEFAULT NULL COMMENT 'Against Invoice/Order number',
  `status` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_customer_ledger`
--

INSERT INTO `tbl_customer_ledger` (`id`, `customer_id`, `branch_id`, `customer_name`, `transaction_type`, `transaction_id`, `transaction_no`, `transaction_date`, `debit_amount`, `credit_amount`, `debit_gold`, `credit_gold`, `debit_gold_pure`, `credit_gold_pure`, `debit_silver`, `credit_silver`, `debit_diamond`, `credit_diamond`, `balance_amount`, `balance_gold`, `balance_gold_pure`, `balance_silver`, `balance_diamond`, `description`, `reference_no`, `against_ledger`, `against_invoice_no`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(2, 2, NULL, 'SRI', 'opening', 0, 'OPENING', '2026-06-06', 0.00, 0.00, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.00, 0.000, 0.000, 0.000, 0.000, 'Opening balance', '', '', '', 1, 1, '2026-06-06 12:20:15', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_customer_types`
--

CREATE TABLE `tbl_customer_types` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `name` varchar(100) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_customer_types`
--

INSERT INTO `tbl_customer_types` (`id`, `branch_id`, `name`, `code`, `status`, `sort_order`, `created_at`, `updated_at`) VALUES
(11, 63, 'Customer', 'CUSTOMER', 1, 1, '2026-06-05 23:28:23', NULL),
(12, 63, 'WholeSaler', 'WHOLESALER', 1, 2, '2026-06-05 23:28:23', NULL),
(13, 63, 'Job Worker', 'JOB_WORKER', 1, 3, '2026-06-05 23:28:23', NULL),
(14, 63, 'Employee', 'EMPLOYEE', 1, 4, '2026-06-05 23:28:23', NULL),
(15, 63, 'Sales Person', 'SALES_PERSON', 1, 5, '2026-06-05 23:28:23', NULL),
(16, 63, 'Supplier', 'SUPPLIER', 1, 6, '2026-06-05 23:28:23', NULL),
(17, 63, 'Qbo Account', 'QBO_ACCOUNT', 1, 7, '2026-06-05 23:28:23', NULL),
(18, 63, 'Retailer', 'RETAILER', 1, 8, '2026-06-05 23:28:23', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_cut`
--

CREATE TABLE `tbl_cut` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `name` varchar(100) NOT NULL,
  `status` tinyint(4) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_dashboard_metal_meta`
--

CREATE TABLE `tbl_dashboard_metal_meta` (
  `metal` varchar(20) NOT NULL COMMENT 'gold, silver, diamond',
  `branch_id` int(11) NOT NULL DEFAULT 0,
  `source_url` varchar(512) NOT NULL DEFAULT '',
  `ounce_rate` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_dashboard_metal_rates`
--

CREATE TABLE `tbl_dashboard_metal_rates` (
  `id` int(10) UNSIGNED NOT NULL,
  `branch_id` int(11) NOT NULL DEFAULT 0,
  `metal` varchar(20) NOT NULL COMMENT 'gold, silver, diamond',
  `carat_label` varchar(64) NOT NULL COMMENT 'e.g. 24K, 999, 0.30 ct',
  `rate` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `sell_premium` decimal(18,6) DEFAULT NULL,
  `conversion_rate` decimal(18,8) NOT NULL DEFAULT 1.00000000,
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_dashboard_metal_rate_history`
--

CREATE TABLE `tbl_dashboard_metal_rate_history` (
  `id` int(10) UNSIGNED NOT NULL,
  `branch_id` int(11) NOT NULL DEFAULT 0,
  `metal` varchar(24) NOT NULL DEFAULT '',
  `carat_label` varchar(64) NOT NULL DEFAULT '',
  `rate` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `recorded_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_day_reports`
--

CREATE TABLE `tbl_day_reports` (
  `id` int(11) NOT NULL,
  `report_date` date NOT NULL,
  `opening_amount` decimal(15,2) DEFAULT 0.00,
  `expected_amount` decimal(15,2) DEFAULT 0.00,
  `online_cheque_payment` decimal(15,2) DEFAULT 0.00,
  `closing_cash` decimal(15,2) DEFAULT 0.00,
  `cash_denomination` decimal(15,3) DEFAULT 0.000,
  `difference` decimal(15,2) DEFAULT 0.00,
  `report_data` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_debit_notes`
--

CREATE TABLE `tbl_debit_notes` (
  `id` int(11) NOT NULL,
  `debit_note_no` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `against_of` varchar(100) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'AED',
  `ref_no` varchar(100) DEFAULT NULL,
  `sales_person` varchar(255) DEFAULT NULL,
  `debit_note_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `layaways_id` int(11) DEFAULT NULL,
  `fixing_type` varchar(50) DEFAULT 'Standard',
  `previous_balance` decimal(15,2) DEFAULT 0.00,
  `previous_gold` decimal(15,2) DEFAULT 0.00,
  `previous_silver` decimal(15,2) DEFAULT 0.00,
  `subtotal` decimal(15,2) DEFAULT 0.00,
  `additional_amt` decimal(15,2) DEFAULT 0.00,
  `net_total` decimal(15,2) DEFAULT 0.00,
  `reward_points` decimal(15,2) DEFAULT 0.00,
  `coupon_code` varchar(50) DEFAULT NULL,
  `coupon_discount` decimal(15,2) DEFAULT 0.00,
  `discount_amt` decimal(15,2) DEFAULT 0.00,
  `redeem_points` decimal(15,2) DEFAULT 0.00,
  `grand_total` decimal(15,2) DEFAULT 0.00,
  `advance_payment` decimal(15,2) DEFAULT 0.00,
  `metal_amt` decimal(15,2) DEFAULT 0.00,
  `round_off` decimal(15,2) DEFAULT 0.00,
  `paid_amt` decimal(15,2) DEFAULT 0.00,
  `balance_amt` decimal(15,2) DEFAULT 0.00,
  `group_name` varchar(255) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_debit_note_items`
--

CREATE TABLE `tbl_debit_note_items` (
  `id` int(11) NOT NULL,
  `debit_note_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_characteristic_id` int(11) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `carat` varchar(50) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 1.00,
  `gross_weight` decimal(10,3) DEFAULT 0.000,
  `less_weight` decimal(10,3) DEFAULT 0.000,
  `purity` decimal(10,2) DEFAULT 0.00,
  `purity_weight` decimal(10,3) DEFAULT 0.000,
  `final_weight` decimal(10,3) DEFAULT 0.000,
  `net_weight` decimal(10,3) DEFAULT 0.000,
  `pure_weight` decimal(10,3) DEFAULT 0.000,
  `rate` decimal(15,2) DEFAULT 0.00,
  `making_amount` decimal(15,2) DEFAULT 0.00,
  `amount` decimal(15,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) DEFAULT 0.00,
  `net_amt_with_tax` decimal(15,2) DEFAULT 0.00,
  `design_no` varchar(100) DEFAULT NULL,
  `location_id` int(11) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_debit_note_payments`
--

CREATE TABLE `tbl_debit_note_payments` (
  `id` int(11) NOT NULL,
  `debit_note_id` int(11) NOT NULL,
  `payment_type` varchar(50) NOT NULL,
  `deposit_into` varchar(100) DEFAULT NULL,
  `transaction_no` varchar(100) DEFAULT NULL,
  `cheque_date` date DEFAULT NULL,
  `purity_carat` varchar(50) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `previous_balance_amount` decimal(15,2) DEFAULT 0.00,
  `current_order_amount` decimal(15,2) DEFAULT 0.00,
  `diamond_category` varchar(100) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 0.00,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_departments`
--

CREATE TABLE `tbl_departments` (
  `id` int(11) NOT NULL,
  `dept_name` varchar(120) NOT NULL,
  `short_code` varchar(40) NOT NULL,
  `department_type` varchar(40) NOT NULL DEFAULT 'Wt. Wise',
  `process_type` varchar(40) NOT NULL DEFAULT 'Manufacturing',
  `auto_loss` tinyint(1) NOT NULL DEFAULT 1,
  `auto_profit` tinyint(1) NOT NULL DEFAULT 1,
  `calculate_stock` tinyint(1) NOT NULL DEFAULT 0,
  `progress_percent` decimal(8,2) DEFAULT NULL,
  `exclude_jobcard_summary` tinyint(1) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_department_users`
--

CREATE TABLE `tbl_department_users` (
  `id` int(11) NOT NULL,
  `user_name` varchar(120) NOT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_department_user_map`
--

CREATE TABLE `tbl_department_user_map` (
  `id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_document_type`
--

CREATE TABLE `tbl_document_type` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `name` varchar(100) NOT NULL,
  `description` varchar(150) DEFAULT NULL,
  `status` tinyint(4) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_document_types`
--

CREATE TABLE `tbl_document_types` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `name` varchar(150) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_ewaybill_api_logs`
--

CREATE TABLE `tbl_ewaybill_api_logs` (
  `id` int(11) NOT NULL,
  `api_name` varchar(100) NOT NULL DEFAULT '',
  `request_url` text DEFAULT NULL,
  `request_headers` longtext DEFAULT NULL,
  `request_body` longtext DEFAULT NULL,
  `response_body` longtext DEFAULT NULL,
  `http_code` int(11) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_ewaybill_api_settings`
--

CREATE TABLE `tbl_ewaybill_api_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_ewaybill_api_tokens`
--

CREATE TABLE `tbl_ewaybill_api_tokens` (
  `id` int(11) NOT NULL,
  `gstin` varchar(20) NOT NULL DEFAULT '',
  `email` varchar(150) DEFAULT NULL,
  `username` varchar(100) NOT NULL DEFAULT '',
  `auth_token` text DEFAULT NULL,
  `sek` text DEFAULT NULL,
  `token_expiry` datetime DEFAULT NULL,
  `response_json` longtext DEFAULT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_ewaybill_generate_logs`
--

CREATE TABLE `tbl_ewaybill_generate_logs` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `invoice_no` varchar(100) DEFAULT NULL,
  `request_url` text DEFAULT NULL,
  `request_headers` longtext DEFAULT NULL,
  `request_body` longtext DEFAULT NULL,
  `response_body` longtext DEFAULT NULL,
  `http_code` int(11) DEFAULT NULL,
  `status_cd` varchar(10) DEFAULT NULL,
  `status_desc` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_expenses`
--

CREATE TABLE `tbl_expenses` (
  `id` int(11) NOT NULL,
  `expense_no` varchar(50) NOT NULL,
  `with_tax` tinyint(1) DEFAULT 1,
  `ledger_id` int(11) DEFAULT NULL,
  `ledger_name` varchar(255) NOT NULL,
  `against_of` varchar(255) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'INR',
  `exchange_rate` decimal(15,6) DEFAULT 1.000000,
  `expense_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `ref_no` varchar(100) DEFAULT NULL,
  `sales_person` varchar(255) DEFAULT NULL,
  `layaways` varchar(100) DEFAULT NULL,
  `fixing_type` varchar(50) DEFAULT 'Standard',
  `previous_balance` decimal(15,2) DEFAULT 0.00,
  `previous_gold` decimal(15,2) DEFAULT 0.00,
  `previous_silver` decimal(15,2) DEFAULT 0.00,
  `subtotal` decimal(15,2) DEFAULT 0.00,
  `net_total` decimal(15,2) DEFAULT 0.00,
  `discount_percent` decimal(10,2) DEFAULT 0.00,
  `discount_amt` decimal(15,2) DEFAULT 0.00,
  `grand_total` decimal(15,2) DEFAULT 0.00,
  `round_off` decimal(15,2) DEFAULT 0.00,
  `paid_amt` decimal(15,2) DEFAULT 0.00,
  `balance_amt` decimal(15,2) DEFAULT 0.00,
  `comment` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_expense_categories`
--

CREATE TABLE `tbl_expense_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL COMMENT 'Display name (e.g. Inter Branch Account ABU DHABI)',
  `type` varchar(100) DEFAULT NULL COMMENT 'Type in parentheses (e.g. Branch /Divisions)',
  `sort_order` int(11) DEFAULT 0,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_expense_items`
--

CREATE TABLE `tbl_expense_items` (
  `id` int(11) NOT NULL,
  `expense_id` int(11) NOT NULL,
  `category` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT 0.00,
  `tax_rate` decimal(10,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `tax_with_amount` decimal(15,2) DEFAULT 0.00,
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_expense_payments`
--

CREATE TABLE `tbl_expense_payments` (
  `id` int(11) NOT NULL,
  `expense_id` int(11) NOT NULL,
  `payment_type` varchar(50) NOT NULL,
  `deposit_into` varchar(100) DEFAULT NULL,
  `diamond_category` varchar(100) DEFAULT NULL,
  `transaction_no` varchar(100) DEFAULT NULL,
  `transfer_from` varchar(255) DEFAULT NULL,
  `cheque_date` date DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `card_no` varchar(50) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_extra_fields`
--

CREATE TABLE `tbl_extra_fields` (
  `id` int(10) UNSIGNED NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `metal_type` varchar(64) NOT NULL DEFAULT 'Gold',
  `display_name` varchar(255) NOT NULL DEFAULT '',
  `field_type` varchar(16) NOT NULL DEFAULT 'text' COMMENT 'text or dropdown',
  `dropdown_options_json` text DEFAULT NULL COMMENT 'JSON array of option strings',
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_gst_calculation_snapshot`
--

CREATE TABLE `tbl_gst_calculation_snapshot` (
  `id` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `snapshot_version` varchar(255) NOT NULL DEFAULT '' COMMENT 'GST revision marker',
  `refreshed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_investment_schemes`
--

CREATE TABLE `tbl_investment_schemes` (
  `id` int(11) NOT NULL,
  `scheme_name` varchar(255) NOT NULL,
  `redemption_on` varchar(50) DEFAULT NULL,
  `carat_id` int(11) DEFAULT NULL,
  `carat_label` varchar(255) DEFAULT NULL,
  `duration_value` int(11) NOT NULL DEFAULT 12,
  `duration_unit` varchar(20) NOT NULL DEFAULT 'Month',
  `installment_type` varchar(50) DEFAULT NULL,
  `installment_amt` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `minimum_amt_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `minimum_amt` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `bonus_rows` longtext DEFAULT NULL COMMENT 'JSON array; reserved for future use',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_invoice_print_settings`
--

CREATE TABLE `tbl_invoice_print_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `setting_type` varchar(50) NOT NULL DEFAULT 'default' COMMENT 'default, sale_invoice, purchase_invoice, ...',
  `setting_key` varchar(100) NOT NULL COMMENT 'e.g. sale_invoice_columns, header_company_logo, layout_type',
  `setting_value` text DEFAULT NULL COMMENT 'JSON or 1/0 for toggles',
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_ip_access_settings`
--

CREATE TABLE `tbl_ip_access_settings` (
  `id` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `ip_access_control_enabled` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_ip_whitelist`
--

CREATE TABLE `tbl_ip_whitelist` (
  `id` int(10) UNSIGNED NOT NULL,
  `entity_value` varchar(255) NOT NULL,
  `entry_type` varchar(32) NOT NULL DEFAULT 'IP',
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_jewelry_catalogue`
--

CREATE TABLE `tbl_jewelry_catalogue` (
  `id` int(11) NOT NULL,
  `metal_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL DEFAULT '',
  `short_desc` varchar(500) NOT NULL DEFAULT '',
  `full_desc` mediumtext DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `design_no` varchar(100) DEFAULT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `weight` decimal(15,4) DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `images_json` mediumtext DEFAULT NULL,
  `bom_json` mediumtext DEFAULT NULL,
  `fill_dmd_gms_rate` tinyint(1) NOT NULL DEFAULT 0,
  `sale_order_id` int(11) DEFAULT NULL,
  `sale_order_item_id` int(11) DEFAULT NULL,
  `repair_order_id` int(11) DEFAULT NULL,
  `repair_order_item_id` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_jobwork_invoices`
--

CREATE TABLE `tbl_jobwork_invoices` (
  `id` int(11) NOT NULL,
  `invoice_no` varchar(50) NOT NULL DEFAULT '',
  `jobwork_order_id` int(11) DEFAULT NULL,
  `repair_jobwork_order_id` int(11) DEFAULT NULL,
  `sale_order_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `grand_total` decimal(15,2) DEFAULT 0.00,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_jobwork_orders`
--

CREATE TABLE `tbl_jobwork_orders` (
  `id` int(11) NOT NULL,
  `jobwork_no` varchar(50) NOT NULL DEFAULT '',
  `jobwork_queue_no` varchar(50) NOT NULL DEFAULT '' COMMENT 'Jobwork Queue No from bill series (Jobwork Queue voucher)',
  `sale_order_id` int(11) NOT NULL,
  `sale_order_no` varchar(50) NOT NULL DEFAULT '',
  `customer_name` varchar(255) DEFAULT NULL,
  `department_id` varchar(100) DEFAULT NULL,
  `department_user_id` int(11) DEFAULT NULL,
  `order_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `grand_total` decimal(15,2) DEFAULT 0.00,
  `status` varchar(30) DEFAULT 'draft',
  `manufacturing_time_seconds` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Cumulative manufacturing time (seconds)',
  `priority` varchar(30) DEFAULT 'Medium',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `jobwork_queue_images` text DEFAULT NULL COMMENT 'Jobwork Queue gallery JSON'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_jobwork_order_comments`
--

CREATE TABLE `tbl_jobwork_order_comments` (
  `id` int(11) NOT NULL,
  `jobwork_order_id` int(11) NOT NULL,
  `comment_text` varchar(2000) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by_user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_jobwork_order_items`
--

CREATE TABLE `tbl_jobwork_order_items` (
  `id` int(11) NOT NULL,
  `jobwork_order_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_characteristic_id` int(11) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `design_no` varchar(100) DEFAULT NULL,
  `carat` varchar(50) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 1.00,
  `gross_weight` decimal(10,3) DEFAULT 0.000,
  `less_weight` decimal(10,3) DEFAULT 0.000,
  `purity` decimal(10,2) DEFAULT 0.00,
  `purity_weight` decimal(10,3) DEFAULT 0.000,
  `final_weight` decimal(10,3) DEFAULT 0.000,
  `net_weight` decimal(10,3) DEFAULT 0.000,
  `pure_weight` decimal(10,3) DEFAULT 0.000,
  `rate` decimal(15,2) DEFAULT 0.00,
  `making_amount` decimal(15,2) DEFAULT 0.00,
  `amount` decimal(15,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) DEFAULT 0.00,
  `net_amt_with_tax` decimal(15,2) DEFAULT 0.00,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_jobwork_queue_activity`
--

CREATE TABLE `tbl_jobwork_queue_activity` (
  `id` int(11) NOT NULL,
  `jobwork_order_id` int(11) NOT NULL,
  `jobwork_queue_no` varchar(50) NOT NULL DEFAULT '',
  `from_dept_id` int(11) DEFAULT NULL,
  `from_user_id` int(11) DEFAULT NULL,
  `to_dept_id` int(11) DEFAULT NULL,
  `to_user_id` int(11) DEFAULT NULL,
  `activity_action` varchar(32) DEFAULT NULL,
  `total_wt_after` decimal(12,4) DEFAULT NULL COMMENT 'Sum line wt after transfer',
  `total_qty_after` decimal(12,4) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_jobwork_queue_diamond_stock`
--

CREATE TABLE `tbl_jobwork_queue_diamond_stock` (
  `id` int(11) NOT NULL,
  `jobwork_order_id` int(11) NOT NULL,
  `jobwork_order_item_id` int(11) DEFAULT NULL,
  `stock_id` int(11) NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `weight_out` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `qty_out` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_jobwork_queue_diamond_stock_issue`
--

CREATE TABLE `tbl_jobwork_queue_diamond_stock_issue` (
  `id` int(11) NOT NULL,
  `jobwork_order_id` int(11) NOT NULL,
  `jobwork_order_item_id` int(11) DEFAULT NULL,
  `stock_id` int(11) NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `diamond_category` varchar(100) DEFAULT NULL,
  `weight` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `weight_out` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `qty_out` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `qty` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `from_dept_id` int(11) DEFAULT NULL,
  `to_dept_id` int(11) DEFAULT NULL,
  `from_user_id` int(11) DEFAULT NULL,
  `to_user_id` int(11) DEFAULT NULL,
  `added_by_dept_id` int(11) DEFAULT NULL,
  `added_by_user_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_jobwork_weight_adjustments`
--

CREATE TABLE `tbl_jobwork_weight_adjustments` (
  `id` int(11) NOT NULL,
  `jobwork_order_id` int(11) NOT NULL,
  `adjustment_type` enum('add','reduce') NOT NULL DEFAULT 'reduce',
  `weight_grams` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `remark` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by_user_id` int(11) DEFAULT NULL,
  `source_department_id` int(11) DEFAULT NULL,
  `source_user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_job_work_orders`
--

CREATE TABLE `tbl_job_work_orders` (
  `id` int(11) NOT NULL,
  `job_work_no` varchar(50) NOT NULL,
  `sale_order_id` int(11) NOT NULL,
  `sale_order_no` varchar(50) NOT NULL,
  `status` varchar(30) DEFAULT 'draft',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_journal_vouchers`
--

CREATE TABLE `tbl_journal_vouchers` (
  `id` int(11) NOT NULL,
  `voucher_no` varchar(50) NOT NULL,
  `voucher_date` date NOT NULL,
  `comment` text DEFAULT NULL,
  `credit_wt` decimal(15,4) DEFAULT 0.0000,
  `debit_wt` decimal(15,4) DEFAULT 0.0000,
  `debit_total` decimal(15,2) DEFAULT 0.00,
  `credit_total` decimal(15,2) DEFAULT 0.00,
  `status` varchar(20) DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_journal_voucher_items`
--

CREATE TABLE `tbl_journal_voucher_items` (
  `id` int(11) NOT NULL,
  `voucher_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK to tbl_branches',
  `branch_name` varchar(100) DEFAULT NULL COMMENT 'Denormalized branch name',
  `account_ledger` varchar(200) NOT NULL COMMENT 'Account ledger name',
  `cr_dr` varchar(10) NOT NULL DEFAULT 'Dr' COMMENT 'Cr or Dr',
  `against` varchar(200) DEFAULT NULL,
  `ref_no` varchar(100) DEFAULT NULL,
  `ref_date` date DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT 0.00,
  `metal` varchar(50) DEFAULT NULL,
  `purity_wt` decimal(15,4) DEFAULT 0.0000,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_loan_product_type`
--

CREATE TABLE `tbl_loan_product_type` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `name` varchar(100) NOT NULL,
  `status` tinyint(4) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_loan_reason`
--

CREATE TABLE `tbl_loan_reason` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `name` varchar(100) NOT NULL,
  `status` tinyint(4) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_location`
--

CREATE TABLE `tbl_location` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `name` varchar(100) DEFAULT NULL,
  `status` tinyint(4) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_login_blocklist`
--

CREATE TABLE `tbl_login_blocklist` (
  `id` int(10) UNSIGNED NOT NULL,
  `ip_address` varchar(64) NOT NULL,
  `username` varchar(128) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `attempt_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `last_attempt_at` datetime DEFAULT NULL,
  `blocked_until` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_manufacturing_closing`
--

CREATE TABLE `tbl_manufacturing_closing` (
  `id` int(10) UNSIGNED NOT NULL,
  `department_id` int(10) UNSIGNED NOT NULL,
  `department_user_id` int(10) UNSIGNED NOT NULL,
  `branch_id` int(10) UNSIGNED DEFAULT NULL,
  `closing_date` date NOT NULL,
  `loss_wt` decimal(18,6) DEFAULT NULL,
  `gold_rate` decimal(18,6) DEFAULT NULL,
  `gold_loss_value` decimal(18,6) DEFAULT NULL,
  `purity_per` decimal(18,6) DEFAULT NULL,
  `purity_wt` decimal(18,6) DEFAULT NULL,
  `work_done_kg` decimal(18,6) DEFAULT NULL,
  `avg_loss_per_kg` decimal(18,6) DEFAULT NULL,
  `inward_wt` decimal(18,6) DEFAULT NULL,
  `outward_wt` decimal(18,6) DEFAULT NULL,
  `recovery_wt` decimal(18,6) DEFAULT NULL,
  `closing_wt` decimal(18,6) DEFAULT NULL,
  `production_wt` decimal(18,6) DEFAULT NULL,
  `difference_loss` decimal(18,6) DEFAULT NULL,
  `final_loss` decimal(18,6) DEFAULT NULL,
  `loss_percent` decimal(18,6) DEFAULT NULL,
  `closed_jobs` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `processed_jobs` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_jobs` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `metal_weight` decimal(18,6) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_material_issues`
--

CREATE TABLE `tbl_material_issues` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `material_issue_no` varchar(50) NOT NULL DEFAULT '',
  `sale_order_id` int(11) DEFAULT NULL,
  `sale_order_no` varchar(50) NOT NULL DEFAULT '',
  `customer_name` varchar(255) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `order_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `grand_total` decimal(15,2) DEFAULT 0.00,
  `status` varchar(30) DEFAULT 'draft',
  `priority` varchar(30) DEFAULT 'Medium',
  `department_user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_material_issue_items`
--

CREATE TABLE `tbl_material_issue_items` (
  `id` int(11) NOT NULL,
  `material_issue_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_characteristic_id` int(11) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `design_no` varchar(100) DEFAULT NULL,
  `carat` varchar(50) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 1.00,
  `gross_weight` decimal(10,3) DEFAULT 0.000,
  `less_weight` decimal(10,3) DEFAULT 0.000,
  `purity` decimal(10,2) DEFAULT 0.00,
  `purity_weight` decimal(10,3) DEFAULT 0.000,
  `requested_purity` decimal(12,4) DEFAULT NULL,
  `requested_wt` decimal(12,4) DEFAULT NULL,
  `alloy_wt` decimal(12,4) DEFAULT NULL,
  `final_weight` decimal(10,3) DEFAULT 0.000,
  `net_weight` decimal(10,3) DEFAULT 0.000,
  `pure_weight` decimal(10,3) DEFAULT 0.000,
  `rate` decimal(15,2) DEFAULT 0.00,
  `making_amount` decimal(15,2) DEFAULT 0.00,
  `amount` decimal(15,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) DEFAULT 0.00,
  `net_amt_with_tax` decimal(15,2) DEFAULT 0.00,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_material_receives`
--

CREATE TABLE `tbl_material_receives` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `material_receive_no` varchar(50) NOT NULL DEFAULT '',
  `sale_order_id` int(11) NOT NULL,
  `sale_order_no` varchar(50) NOT NULL DEFAULT '',
  `customer_name` varchar(255) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `order_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `grand_total` decimal(15,2) DEFAULT 0.00,
  `status` varchar(30) DEFAULT 'draft',
  `priority` varchar(30) DEFAULT 'Medium',
  `department_user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_material_receive_items`
--

CREATE TABLE `tbl_material_receive_items` (
  `id` int(11) NOT NULL,
  `material_receive_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_characteristic_id` int(11) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `design_no` varchar(100) DEFAULT NULL,
  `carat` varchar(50) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 1.00,
  `gross_weight` decimal(10,3) DEFAULT 0.000,
  `less_weight` decimal(10,3) DEFAULT 0.000,
  `purity` decimal(10,2) DEFAULT 0.00,
  `purity_weight` decimal(10,3) DEFAULT 0.000,
  `final_weight` decimal(10,3) DEFAULT 0.000,
  `net_weight` decimal(10,3) DEFAULT 0.000,
  `pure_weight` decimal(10,3) DEFAULT 0.000,
  `rate` decimal(15,2) DEFAULT 0.00,
  `making_amount` decimal(15,2) DEFAULT 0.00,
  `amount` decimal(15,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) DEFAULT 0.00,
  `net_amt_with_tax` decimal(15,2) DEFAULT 0.00,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_metal`
--

CREATE TABLE `tbl_metal` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `display_name` varchar(100) NOT NULL,
  `hsn_code` varchar(20) DEFAULT NULL,
  `system_name` varchar(100) DEFAULT NULL,
  `dashboard_image_path` varchar(512) DEFAULT NULL COMMENT 'Relative to admin/',
  `dashboard_image_url` varchar(1024) DEFAULT NULL,
  `show_on_dashboard` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Live rates dashboard card + tabs',
  `status` tinyint(4) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `tbl_metal`
--

INSERT INTO `tbl_metal` (`id`, `branch_id`, `display_name`, `hsn_code`, `system_name`, `dashboard_image_path`, `dashboard_image_url`, `show_on_dashboard`, `status`, `created_by`, `modified_by`, `created_at`, `updated_at`) VALUES
(1, 63, 'Gold', '12345', 'ewew', 'uploads/metal-dashboard/metal_1_7e1b13a18e95.jpg', '', 1, 1, 0, 1, '2025-12-18 12:41:42', '2026-06-05 17:59:10'),
(2, 63, 'Silver', '', '', 'uploads/metal-dashboard/metal_2_dc696d061adf.webp', '', 1, 1, NULL, 1, '2025-12-26 12:04:11', '2026-06-05 17:59:13'),
(3, 63, 'Platinum', '', '', 'uploads/metal-dashboard/metal_3_b16a21729cb9.webp', '', 1, 1, NULL, 1, '2025-12-26 12:04:19', '2026-06-05 17:59:15'),
(4, 63, 'Diamond & Stones', '', '', 'uploads/metal-dashboard/metal_4_a5854f5d2096.jpg', '', 1, 1, NULL, 1, '2025-12-26 12:04:19', '2026-06-05 17:59:17'),
(5, 63, 'Imitation Or Watches', '', '', '', '', 0, 1, NULL, 1, '2025-12-26 12:04:29', '2026-06-05 17:59:20'),
(6, 63, 'Other Or Services', '', '', '', '', 0, 1, NULL, 1, '2025-12-26 12:04:29', '2026-06-05 17:59:29'),
(13, 63, 'Loose / Certified Diamond & Stones', '', '', NULL, '', 0, 1, 1, NULL, '2026-05-27 12:19:08', '2026-06-05 17:59:32');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_metal_amount_conversions`
--

CREATE TABLE `tbl_metal_amount_conversions` (
  `id` int(11) NOT NULL,
  `branch_id` int(10) UNSIGNED DEFAULT NULL,
  `customer_id` int(11) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `direction` enum('metal_to_amount','amount_to_metal') NOT NULL,
  `metal_type` varchar(32) NOT NULL,
  `metal_weight` decimal(16,4) NOT NULL DEFAULT 0.0000,
  `rate` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `amount` decimal(16,2) NOT NULL DEFAULT 0.00,
  `trans_date` datetime NOT NULL,
  `trans_no` varchar(64) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_nationalities`
--

CREATE TABLE `tbl_nationalities` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(10) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_nationalities`
--

INSERT INTO `tbl_nationalities` (`id`, `name`, `code`, `status`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Afghan', 'AF', 1, 1, '2026-06-05 23:29:59', NULL),
(2, 'Albanian', 'AL', 1, 2, '2026-06-05 23:29:59', NULL),
(3, 'Algerian', 'DZ', 1, 3, '2026-06-05 23:29:59', NULL),
(4, 'American', 'US', 1, 4, '2026-06-05 23:29:59', NULL),
(5, 'Argentine', 'AR', 1, 5, '2026-06-05 23:29:59', NULL),
(6, 'Australian', 'AU', 1, 6, '2026-06-05 23:29:59', NULL),
(7, 'Austrian', 'AT', 1, 7, '2026-06-05 23:29:59', NULL),
(8, 'Bangladeshi', 'BD', 1, 8, '2026-06-05 23:29:59', NULL),
(9, 'Belgian', 'BE', 1, 9, '2026-06-05 23:29:59', NULL),
(10, 'Brazilian', 'BR', 1, 10, '2026-06-05 23:29:59', NULL),
(11, 'British', 'GB', 1, 11, '2026-06-05 23:29:59', NULL),
(12, 'Canadian', 'CA', 1, 12, '2026-06-05 23:29:59', NULL),
(13, 'Chinese', 'CN', 1, 13, '2026-06-05 23:29:59', NULL),
(14, 'Egyptian', 'EG', 1, 14, '2026-06-05 23:29:59', NULL),
(15, 'Emirati', 'AE', 1, 15, '2026-06-05 23:29:59', NULL),
(16, 'Filipino', 'PH', 1, 16, '2026-06-05 23:29:59', NULL),
(17, 'French', 'FR', 1, 17, '2026-06-05 23:29:59', NULL),
(18, 'German', 'DE', 1, 18, '2026-06-05 23:29:59', NULL),
(19, 'Indian', 'IN', 1, 19, '2026-06-05 23:29:59', NULL),
(20, 'Indonesian', 'ID', 1, 20, '2026-06-05 23:29:59', NULL),
(21, 'Iranian', 'IR', 1, 21, '2026-06-05 23:29:59', NULL),
(22, 'Iraqi', 'IQ', 1, 22, '2026-06-05 23:29:59', NULL),
(23, 'Irish', 'IE', 1, 23, '2026-06-05 23:29:59', NULL),
(24, 'Italian', 'IT', 1, 24, '2026-06-05 23:29:59', NULL),
(25, 'Japanese', 'JP', 1, 25, '2026-06-05 23:29:59', NULL),
(26, 'Jordanian', 'JO', 1, 26, '2026-06-05 23:29:59', NULL),
(27, 'Kenyan', 'KE', 1, 27, '2026-06-05 23:29:59', NULL),
(28, 'Kuwaiti', 'KW', 1, 28, '2026-06-05 23:29:59', NULL),
(29, 'Lebanese', 'LB', 1, 29, '2026-06-05 23:29:59', NULL),
(30, 'Malaysian', 'MY', 1, 30, '2026-06-05 23:29:59', NULL),
(31, 'Mexican', 'MX', 1, 31, '2026-06-05 23:29:59', NULL),
(32, 'Moroccan', 'MA', 1, 32, '2026-06-05 23:29:59', NULL),
(33, 'Nepalese', 'NP', 1, 33, '2026-06-05 23:29:59', NULL),
(34, 'Nigerian', 'NG', 1, 34, '2026-06-05 23:29:59', NULL),
(35, 'Omani', 'OM', 1, 35, '2026-06-05 23:29:59', NULL),
(36, 'Pakistani', 'PK', 1, 36, '2026-06-05 23:29:59', NULL),
(37, 'Palestinian', 'PS', 1, 37, '2026-06-05 23:29:59', NULL),
(38, 'Qatari', 'QA', 1, 38, '2026-06-05 23:29:59', NULL),
(39, 'Russian', 'RU', 1, 39, '2026-06-05 23:29:59', NULL),
(40, 'Saudi Arabian', 'SA', 1, 40, '2026-06-05 23:29:59', NULL),
(41, 'Singaporean', 'SG', 1, 41, '2026-06-05 23:29:59', NULL),
(42, 'South African', 'ZA', 1, 42, '2026-06-05 23:29:59', NULL),
(43, 'South Korean', 'KR', 1, 43, '2026-06-05 23:29:59', NULL),
(44, 'Spanish', 'ES', 1, 44, '2026-06-05 23:29:59', NULL),
(45, 'Sri Lankan', 'LK', 1, 45, '2026-06-05 23:29:59', NULL),
(46, 'Sudanese', 'SD', 1, 46, '2026-06-05 23:29:59', NULL),
(47, 'Swiss', 'CH', 1, 47, '2026-06-05 23:29:59', NULL),
(48, 'Syrian', 'SY', 1, 48, '2026-06-05 23:29:59', NULL),
(49, 'Thai', 'TH', 1, 49, '2026-06-05 23:29:59', NULL),
(50, 'Tunisian', 'TN', 1, 50, '2026-06-05 23:29:59', NULL),
(51, 'Turkish', 'TR', 1, 51, '2026-06-05 23:29:59', NULL),
(52, 'Ukrainian', 'UA', 1, 52, '2026-06-05 23:29:59', NULL),
(53, 'Yemeni', 'YE', 1, 53, '2026-06-05 23:29:59', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_old_jewelry_scrap_invoices`
--

CREATE TABLE `tbl_old_jewelry_scrap_invoices` (
  `id` int(11) NOT NULL,
  `invoice_no` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `against_of` varchar(100) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'USD',
  `currency_rate` decimal(18,6) DEFAULT 1.000000,
  `ref_no` varchar(100) DEFAULT NULL,
  `sales_person` varchar(255) DEFAULT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `layaways_id` int(11) DEFAULT NULL,
  `fixing_type` varchar(50) DEFAULT 'Standard',
  `barcode` varchar(100) DEFAULT NULL,
  `ounce_rate` decimal(15,4) DEFAULT 0.0000,
  `previous_balance_amt` decimal(15,2) DEFAULT 0.00,
  `previous_balance_gold` decimal(15,4) DEFAULT 0.0000,
  `previous_balance_silver` decimal(15,4) DEFAULT 0.0000,
  `subtotal` decimal(15,2) DEFAULT 0.00,
  `additional_amt` decimal(15,2) DEFAULT 0.00,
  `net_total` decimal(15,2) DEFAULT 0.00,
  `discount_amt` decimal(15,2) DEFAULT 0.00,
  `grand_total` decimal(15,2) DEFAULT 0.00,
  `advance_payment` decimal(15,2) DEFAULT 0.00,
  `metal_amt` decimal(15,2) DEFAULT 0.00,
  `round_off` decimal(15,2) DEFAULT 0.00,
  `round_off_apply` tinyint(1) DEFAULT 0,
  `paid_amt` decimal(15,2) DEFAULT 0.00,
  `balance_amt` decimal(15,2) DEFAULT 0.00,
  `comment` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_old_jewelry_scrap_invoice_items`
--

CREATE TABLE `tbl_old_jewelry_scrap_invoice_items` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `metal_id` int(11) DEFAULT NULL,
  `gross_wt` decimal(15,4) DEFAULT 0.0000,
  `less_wt` decimal(15,4) DEFAULT 0.0000,
  `final_wt` decimal(15,4) DEFAULT 0.0000,
  `net_wt` decimal(15,4) DEFAULT 0.0000,
  `pure_wt` decimal(15,4) DEFAULT 0.0000,
  `making` decimal(15,2) DEFAULT 0.00,
  `tax` decimal(15,2) DEFAULT 0.00,
  `amount` decimal(15,2) DEFAULT 0.00,
  `net_amt` decimal(15,2) DEFAULT 0.00,
  `quantity` decimal(10,2) DEFAULT 1.00,
  `net_amt_wt` decimal(15,4) DEFAULT 0.0000,
  `diamond_wt` decimal(15,4) DEFAULT 0.0000,
  `gemstone_wt` decimal(15,4) DEFAULT 0.0000,
  `purity` decimal(10,2) DEFAULT 0.00,
  `rate` decimal(15,2) DEFAULT 0.00,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `is_stocked` tinyint(1) DEFAULT 0 COMMENT '1=stocked in',
  `stocked_at` datetime DEFAULT NULL,
  `stocked_branch_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_old_jewelry_scrap_invoice_payments`
--

CREATE TABLE `tbl_old_jewelry_scrap_invoice_payments` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `payment_type` varchar(50) NOT NULL,
  `deposit_into` varchar(100) DEFAULT NULL,
  `transaction_no` varchar(100) DEFAULT NULL,
  `cheque_date` date DEFAULT NULL,
  `purity_carat` varchar(50) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `diamond_category` varchar(100) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 0.00,
  `card_no` varchar(100) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `payment_details` text DEFAULT NULL COMMENT 'JSON: scrap modal fields, metal, weights, etc.'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_old_jewelry_stock`
--

CREATE TABLE `tbl_old_jewelry_stock` (
  `id` int(11) NOT NULL,
  `source_invoice_id` int(11) NOT NULL,
  `source_item_id` int(11) NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `invoice_no` varchar(50) NOT NULL,
  `voucher_type` varchar(100) DEFAULT 'Old Jewelry - Scrap',
  `metal` varchar(100) DEFAULT NULL,
  `product` varchar(500) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `final_wt` decimal(15,4) DEFAULT 0.0000,
  `gross_wt` decimal(15,4) DEFAULT 0.0000,
  `purity` decimal(10,2) DEFAULT 0.00,
  `branch_id` int(11) DEFAULT NULL,
  `less_wt` decimal(15,4) DEFAULT 0.0000,
  `net_wt` decimal(15,4) DEFAULT 0.0000,
  `amount` decimal(15,2) DEFAULT 0.00,
  `category` varchar(100) DEFAULT NULL,
  `against_invoice_no` varchar(100) DEFAULT NULL,
  `against_voucher` varchar(100) DEFAULT NULL,
  `group_name` varchar(255) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 1.00,
  `rate` decimal(15,2) DEFAULT 0.00,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_packet_type`
--

CREATE TABLE `tbl_packet_type` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `name` varchar(100) NOT NULL,
  `weight` decimal(10,3) DEFAULT 0.000,
  `status` tinyint(4) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_payment_vouchers`
--

CREATE TABLE `tbl_payment_vouchers` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `voucher_no` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `ref_no` varchar(100) DEFAULT NULL,
  `receipt_no` varchar(100) DEFAULT NULL,
  `voucher_type` varchar(50) DEFAULT NULL,
  `against` varchar(100) DEFAULT NULL,
  `sales_person` varchar(255) DEFAULT NULL,
  `against_of` varchar(100) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'AED',
  `currency_rate` decimal(15,6) DEFAULT 1.000000,
  `voucher_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `layaways_id` int(11) DEFAULT NULL,
  `fixing_type` varchar(50) DEFAULT 'Standard',
  `previous_balance` decimal(15,2) DEFAULT 0.00,
  `previous_gold` decimal(10,3) DEFAULT 0.000,
  `previous_silver` decimal(10,3) DEFAULT 0.000,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `total_gold` decimal(10,3) DEFAULT 0.000,
  `total_silver` decimal(10,3) DEFAULT 0.000,
  `comment` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_payment_voucher_items`
--

CREATE TABLE `tbl_payment_voucher_items` (
  `id` int(11) NOT NULL,
  `voucher_id` int(11) NOT NULL,
  `payment_type` varchar(50) DEFAULT NULL,
  `diamond_category` varchar(100) DEFAULT NULL,
  `transaction_no` varchar(100) DEFAULT NULL,
  `deposit_into` varchar(100) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `cheque_date` date DEFAULT NULL,
  `weight` decimal(10,3) DEFAULT 0.000,
  `metal_id` int(11) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 0.00,
  `purity_carat` varchar(50) DEFAULT NULL,
  `purity_wt` decimal(10,3) DEFAULT 0.000,
  `amount` decimal(15,2) DEFAULT 0.00,
  `previous_balance_amount` decimal(15,2) DEFAULT 0.00,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_pos_sale_invoices`
--

CREATE TABLE `tbl_pos_sale_invoices` (
  `id` int(11) NOT NULL,
  `invoice_no` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `against_of` varchar(100) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'AED',
  `ref_no` varchar(100) DEFAULT NULL,
  `sales_person` varchar(255) DEFAULT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `layaways_id` int(11) DEFAULT NULL,
  `fixing_type` varchar(50) DEFAULT 'Standard',
  `previous_balance` decimal(15,2) DEFAULT 0.00,
  `previous_gold` decimal(15,2) DEFAULT 0.00,
  `previous_silver` decimal(15,2) DEFAULT 0.00,
  `previous_diamond` decimal(12,3) DEFAULT 0.000,
  `previous_gemstone` decimal(12,3) DEFAULT 0.000,
  `subtotal` decimal(15,2) DEFAULT 0.00,
  `additional_amt` decimal(15,2) DEFAULT 0.00,
  `net_total` decimal(15,2) DEFAULT 0.00,
  `reward_points` decimal(15,2) DEFAULT 0.00,
  `coupon_code` varchar(50) DEFAULT NULL,
  `coupon_discount` decimal(15,2) DEFAULT 0.00,
  `discount_amt` decimal(15,2) DEFAULT 0.00,
  `discount_percent` decimal(10,2) DEFAULT 0.00,
  `redeem_points` decimal(15,2) DEFAULT 0.00,
  `grand_total` decimal(15,2) DEFAULT 0.00,
  `advance_payment` decimal(15,2) DEFAULT 0.00,
  `metal_amt` decimal(15,2) DEFAULT 0.00,
  `round_off` decimal(15,2) DEFAULT 0.00,
  `paid_amt` decimal(15,2) DEFAULT 0.00,
  `balance_amt` decimal(15,2) DEFAULT 0.00,
  `adjusted_balance_used` decimal(14,2) DEFAULT 0.00 COMMENT 'Amount of adjusted balance used in this invoice',
  `group_name` varchar(255) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `payment_comments` text DEFAULT NULL COMMENT 'JSON array of comments: [{text, added_by, added_at}]',
  `status` varchar(20) DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `use_previous_balance` tinyint(1) DEFAULT 0 COMMENT '1=used previous balance on this invoice',
  `previous_balance_used_amt` decimal(15,2) DEFAULT 0.00 COMMENT 'Amount used from previous balance (e.g. 500.00)',
  `gst_supply_mode` varchar(24) DEFAULT NULL COMMENT 'intrastate|interstate',
  `gst_cgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `gst_sgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `gst_igst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `customer_gstin` varchar(20) DEFAULT NULL,
  `eway_vehicle_no` varchar(32) DEFAULT NULL,
  `eway_distance_km` decimal(10,2) DEFAULT NULL,
  `eway_bill_no` varchar(50) DEFAULT NULL,
  `eway_bill_date` datetime DEFAULT NULL,
  `eway_status` varchar(32) DEFAULT NULL,
  `eway_response` text DEFAULT NULL,
  `eway_valid_upto` varchar(50) DEFAULT NULL,
  `eway_generated_at` datetime DEFAULT NULL,
  `eway_trans_mode` varchar(2) DEFAULT NULL,
  `eway_transporter_name` varchar(200) DEFAULT NULL,
  `eway_transporter_id` varchar(20) DEFAULT NULL,
  `eway_trans_doc_no` varchar(100) DEFAULT NULL,
  `eway_trans_doc_date` varchar(20) DEFAULT NULL,
  `eway_vehicle_type` varchar(1) DEFAULT NULL,
  `eway_enable` tinyint(1) NOT NULL DEFAULT 0,
  `eway_to_pincode` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_pos_sale_invoice_items`
--

CREATE TABLE `tbl_pos_sale_invoice_items` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order (drag-and-drop)',
  `product_id` int(11) NOT NULL,
  `product_characteristic_id` int(11) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `carat` varchar(50) DEFAULT NULL,
  `stone_weight` decimal(10,3) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 1.00,
  `metal_qty` decimal(12,2) DEFAULT 1.00,
  `metal_weight` decimal(12,4) DEFAULT 0.0000,
  `gross_weight` decimal(10,3) DEFAULT 0.000,
  `less_weight` decimal(10,3) DEFAULT 0.000,
  `purity` decimal(10,2) DEFAULT 0.00,
  `purity_weight` decimal(10,3) DEFAULT 0.000,
  `final_weight` decimal(10,3) DEFAULT 0.000,
  `net_weight` decimal(10,3) DEFAULT 0.000,
  `pure_weight` decimal(10,3) DEFAULT 0.000,
  `rate` decimal(15,2) DEFAULT 0.00,
  `metal_value` decimal(15,2) DEFAULT NULL,
  `metal_rate` decimal(15,2) DEFAULT NULL,
  `making_amount` decimal(15,2) DEFAULT 0.00,
  `amount` decimal(15,2) DEFAULT 0.00,
  `stone_amount` decimal(15,2) DEFAULT NULL,
  `diamond_amount` decimal(15,2) DEFAULT NULL,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) DEFAULT 0.00,
  `net_amt_with_tax` decimal(15,2) DEFAULT 0.00,
  `merge_group_index` int(10) UNSIGNED DEFAULT NULL COMMENT 'Same value = same product list row (merged modal lines)',
  `design_no` varchar(100) DEFAULT NULL,
  `calculation_type` varchar(100) DEFAULT NULL,
  `diamond_category` varchar(50) DEFAULT NULL,
  `location_id` int(11) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_pos_sale_invoice_payments`
--

CREATE TABLE `tbl_pos_sale_invoice_payments` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `payment_type` varchar(50) NOT NULL,
  `deposit_into` varchar(100) DEFAULT NULL,
  `transaction_no` varchar(100) DEFAULT NULL,
  `cheque_date` date DEFAULT NULL,
  `purity_carat` varchar(50) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `previous_balance_amount` decimal(15,2) DEFAULT 0.00,
  `current_order_amount` decimal(15,2) DEFAULT 0.00,
  `diamond_category` varchar(100) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 0.00,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `payment_details` text DEFAULT NULL COMMENT 'JSON copy of payment row (scrap weights, qty, etc.)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_products`
--

CREATE TABLE `tbl_products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `alternate_name` varchar(255) DEFAULT NULL,
  `article` varchar(100) DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `is_stock_item` tinyint(1) DEFAULT 1,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_products`
--

INSERT INTO `tbl_products` (`id`, `name`, `alternate_name`, `article`, `category_id`, `is_stock_item`, `status`, `created_at`, `updated_at`) VALUES
(1, 'EARRING', '', '', 0, 1, 0, '2026-06-06 12:18:03', '2026-06-15 18:53:19'),
(2, 'SILVER', '', '', 0, 1, 1, '2026-06-16 10:35:35', '2026-06-16 17:16:45');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_product_branches`
--

CREATE TABLE `tbl_product_branches` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_product_branches`
--

INSERT INTO `tbl_product_branches` (`id`, `product_id`, `branch_id`, `is_active`, `created_at`) VALUES
(3, 2, 63, 1, '2026-06-16 10:35:35');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_product_branch_settings`
--

CREATE TABLE `tbl_product_branch_settings` (
  `product_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `is_stock_item` tinyint(4) NOT NULL DEFAULT 1,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_product_branch_settings`
--

INSERT INTO `tbl_product_branch_settings` (`product_id`, `branch_id`, `category_id`, `is_stock_item`, `updated_at`) VALUES
(1, 63, NULL, 1, '2026-06-06 12:19:22'),
(2, 63, NULL, 1, '2026-06-16 17:16:46');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_product_characteristics`
--

CREATE TABLE `tbl_product_characteristics` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `metal_id` int(11) NOT NULL,
  `is_selected` tinyint(1) DEFAULT 0,
  `serialized_barcode` tinyint(1) DEFAULT 0,
  `hsn` varchar(50) DEFAULT NULL,
  `sku_code` varchar(100) DEFAULT NULL,
  `making_on` varchar(50) DEFAULT NULL,
  `diamond_category` varchar(100) DEFAULT NULL,
  `unit_id` int(11) DEFAULT NULL COMMENT 'Reference to tbl_unit.id',
  `location_id` int(11) DEFAULT NULL COMMENT 'Reference to tbl_location.id',
  `purity_sale` decimal(10,2) DEFAULT NULL COMMENT 'Purity percentage for sale',
  `purity_purchase` tinyint(1) DEFAULT 0 COMMENT 'Purchase purity enabled (1) or not (0)',
  `wastage_sale` decimal(10,2) DEFAULT NULL COMMENT 'Wastage percentage for sale',
  `wastage_purchase` decimal(10,2) DEFAULT NULL COMMENT 'Wastage percentage for purchase',
  `wt_per_piece` decimal(10,3) DEFAULT NULL COMMENT 'Weight per piece',
  `carat` decimal(10,2) DEFAULT 0.00,
  `discount` decimal(10,2) DEFAULT 0.00,
  `opening_weight` decimal(15,4) DEFAULT NULL,
  `opening_purity` decimal(10,4) DEFAULT NULL COMMENT 'Purity e.g. 0.999 for 99.9%',
  `opening_qty` decimal(15,4) DEFAULT NULL,
  `final_weight` decimal(15,4) DEFAULT NULL,
  `rate` decimal(15,4) DEFAULT NULL,
  `value` decimal(15,4) DEFAULT NULL,
  `barcode_digits` int(11) DEFAULT 0,
  `barcode_prefix` varchar(10) DEFAULT NULL,
  `cut` varchar(50) DEFAULT NULL,
  `shape` varchar(50) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `clarity` varchar(50) DEFAULT NULL,
  `sieve` varchar(50) DEFAULT NULL,
  `size` varchar(50) DEFAULT NULL,
  `style_code` varchar(100) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `barcode` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_product_characteristics`
--

INSERT INTO `tbl_product_characteristics` (`id`, `product_id`, `branch_id`, `metal_id`, `is_selected`, `serialized_barcode`, `hsn`, `sku_code`, `making_on`, `diamond_category`, `unit_id`, `location_id`, `purity_sale`, `purity_purchase`, `wastage_sale`, `wastage_purchase`, `wt_per_piece`, `carat`, `discount`, `opening_weight`, `opening_purity`, `opening_qty`, `final_weight`, `rate`, `value`, `barcode_digits`, `barcode_prefix`, `cut`, `shape`, `color`, `clarity`, `sieve`, `size`, `style_code`, `status`, `created_at`, `updated_at`, `barcode`) VALUES
(1, 1, 63, 2, 1, 0, '7113', '', 'Gross Wt', '', NULL, NULL, NULL, 0, NULL, NULL, NULL, 0.00, 0.00, 0.0000, 1.0000, 0.0000, 0.0000, 0.0000, 0.0000, 5, 'E', '', '', '', '', '', '', '', 0, '2026-06-06 12:18:03', '2026-06-06 12:19:22', 'E00001'),
(2, 1, 63, 2, 1, 0, '7113', '', 'Gross Wt', '', NULL, NULL, NULL, 0, NULL, NULL, NULL, 0.00, 0.00, 500.0000, 1.0000, 20.0000, 500.0000, 0.0000, 0.0000, 5, 'E', '', '', '', '', '', '', '', 0, '2026-06-06 12:19:22', '2026-06-15 18:53:19', 'E00001'),
(3, 2, 63, 2, 1, 0, '7113', '', 'Gross Wt', '', NULL, NULL, NULL, 0, NULL, NULL, NULL, 0.00, 0.00, 0.0000, 1.0000, 0.0000, 0.0000, 0.0000, 0.0000, 5, 'SV', '', '', '', '', '', '', '', 0, '2026-06-16 10:35:35', '2026-06-16 10:36:53', 'SV00001'),
(4, 2, 63, 2, 1, 0, '7113', '', 'Gross Wt', '', NULL, NULL, NULL, 0, NULL, NULL, NULL, 0.00, 0.00, 0.0000, 1.0000, 0.0000, 518.5000, 0.0000, 0.0000, 5, 'SV', '', '', '', '', '', '', '', 0, '2026-06-16 10:36:53', '2026-06-16 17:16:36', 'SV00001'),
(5, 2, 63, 2, 1, 0, '7113', '', 'Gross Wt', '', NULL, NULL, NULL, 0, NULL, NULL, NULL, 0.00, 0.00, 10000.0000, 1.0000, 10000.0000, 10000.0000, 0.0000, 0.0000, 5, 'SV', '', '', '', '', '', '', '', 0, '2026-06-16 17:16:36', '2026-06-16 17:16:45', 'SV00001'),
(6, 2, 63, 2, 1, 0, '7113', '', 'Gross Wt', '', NULL, NULL, NULL, 0, NULL, NULL, NULL, 0.00, 0.00, 10000.0000, 1.0000, 9997.0000, 10000.0000, 0.0000, 0.0000, 5, 'SV', '', '', '', '', '', '', '', 1, '2026-06-16 17:16:46', '2026-06-19 15:38:05', 'SV00001');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_product_tax`
--

CREATE TABLE `tbl_product_tax` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `tax_type` varchar(50) NOT NULL,
  `tax_value` decimal(10,2) DEFAULT 0.00,
  `calculation_mode` varchar(100) DEFAULT 'Product Amount',
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_purchase_fixing_direct`
--

CREATE TABLE `tbl_purchase_fixing_direct` (
  `id` int(11) NOT NULL,
  `invoice_no` varchar(50) NOT NULL COMMENT 'PFD voucher no',
  `ref_no` varchar(100) DEFAULT NULL COMMENT 'Same as invoice_no for reports',
  `supplier_id` int(11) DEFAULT NULL,
  `supplier_name` varchar(255) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `sale_invoice_no` varchar(64) DEFAULT NULL COMMENT 'Linked sale invoice (SPK14, SI-1, etc.)',
  `against_of` varchar(255) DEFAULT NULL COMMENT 'e.g. Fixing of SPK14',
  `currency` varchar(10) DEFAULT 'AED',
  `invoice_date` date NOT NULL,
  `fixing_date` date DEFAULT NULL,
  `fixing_type` varchar(50) DEFAULT 'Hedging',
  `subtotal` decimal(15,2) DEFAULT 0.00,
  `net_total` decimal(15,2) DEFAULT 0.00,
  `grand_total` decimal(15,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `paid_amt` decimal(15,2) DEFAULT 0.00,
  `balance_amt` decimal(15,2) DEFAULT 0.00,
  `status` varchar(20) DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_purchase_fixing_direct_items`
--

CREATE TABLE `tbl_purchase_fixing_direct_items` (
  `id` int(11) NOT NULL,
  `fixing_id` int(11) NOT NULL COMMENT 'tbl_purchase_fixing_direct.id',
  `metal_id` int(11) DEFAULT NULL,
  `gross_wt` decimal(10,3) DEFAULT 0.000,
  `purity_wt` decimal(10,3) DEFAULT 0.000,
  `rate` decimal(15,2) DEFAULT 0.00,
  `amount` decimal(15,2) DEFAULT 0.00,
  `purity` decimal(10,2) DEFAULT 1.00,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_purchase_invoices`
--

CREATE TABLE `tbl_purchase_invoices` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `invoice_no` varchar(50) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `supplier_name` varchar(255) NOT NULL,
  `against_of` varchar(100) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'AED',
  `ref_no` varchar(100) DEFAULT NULL,
  `purchase_person` varchar(255) DEFAULT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `layaways_id` int(11) DEFAULT NULL,
  `fixing_type` varchar(50) DEFAULT 'Standard',
  `previous_balance` decimal(15,2) DEFAULT 0.00,
  `previous_gold` decimal(15,2) DEFAULT 0.00,
  `previous_silver` decimal(15,2) DEFAULT 0.00,
  `previous_diamond` decimal(12,3) DEFAULT 0.000,
  `previous_gemstone` decimal(12,3) DEFAULT 0.000,
  `subtotal` decimal(15,2) DEFAULT 0.00,
  `additional_amt` decimal(15,2) DEFAULT 0.00,
  `net_total` decimal(15,2) DEFAULT 0.00,
  `reward_points` decimal(15,2) DEFAULT 0.00,
  `coupon_code` varchar(50) DEFAULT NULL,
  `coupon_discount` decimal(15,2) DEFAULT 0.00,
  `discount_amt` decimal(15,2) DEFAULT 0.00,
  `discount_percent` decimal(10,2) DEFAULT 0.00,
  `redeem_points` decimal(15,2) DEFAULT 0.00,
  `grand_total` decimal(15,2) DEFAULT 0.00,
  `advance_payment` decimal(15,2) DEFAULT 0.00,
  `metal_amt` decimal(15,2) DEFAULT 0.00,
  `round_off` decimal(15,2) DEFAULT 0.00,
  `paid_amt` decimal(15,2) DEFAULT 0.00,
  `balance_amt` decimal(15,2) DEFAULT 0.00,
  `group_name` varchar(255) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `payment_comments` text DEFAULT NULL COMMENT 'JSON array of comments: [{text, added_by, added_at}]',
  `status` varchar(20) DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `use_previous_balance` tinyint(1) DEFAULT 0 COMMENT '1=used previous balance on this invoice',
  `previous_balance_used_amt` decimal(15,2) DEFAULT 0.00 COMMENT 'Amount used from previous balance (e.g. 500.00)',
  `hedge_contract_ref` varchar(255) DEFAULT NULL COMMENT 'Hedge contract reference when fixing_type = Hedging',
  `hedge_date` date DEFAULT NULL COMMENT 'Hedge / locked rate date when fixing_type = Hedging'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_purchase_invoice_items`
--

CREATE TABLE `tbl_purchase_invoice_items` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `active` tinyint(1) DEFAULT 1 COMMENT 'Active status (1=active, 0=inactive)',
  `product_id` int(11) NOT NULL,
  `product_characteristic_id` int(11) DEFAULT NULL,
  `rfid` varchar(255) DEFAULT NULL COMMENT 'RFID Code',
  `voucher_type` varchar(255) DEFAULT NULL COMMENT 'Voucher Type ID',
  `barcode` varchar(100) DEFAULT NULL,
  `barcode_no` varchar(100) DEFAULT NULL COMMENT 'Tag barcode (may repeat across diamond composite lines)',
  `product_name` varchar(255) NOT NULL,
  `location_id` int(11) DEFAULT NULL COMMENT 'Location ID',
  `images` text DEFAULT NULL COMMENT 'JSON: primary path + array of image paths',
  `carat` varchar(50) DEFAULT NULL,
  `pkt_wt` decimal(10,3) DEFAULT 0.000 COMMENT 'Pkt. Wt.',
  `pkt_less_wt` decimal(10,3) DEFAULT 0.000 COMMENT 'Pkt. Less Wt.',
  `requested_purity` decimal(10,2) DEFAULT 0.00 COMMENT 'Requested Purity',
  `requested_wt` decimal(10,3) DEFAULT 0.000 COMMENT 'Requested Wt.',
  `quantity` decimal(10,2) DEFAULT 1.00,
  `metal_qty` decimal(12,2) DEFAULT 1.00,
  `metal_weight` decimal(12,4) DEFAULT 0.0000,
  `gross_weight` decimal(10,3) DEFAULT 0.000,
  `less_weight` decimal(10,3) DEFAULT 0.000,
  `gold_loss_wt` decimal(10,3) DEFAULT 0.000 COMMENT 'Gold Loss Wt.',
  `gold_loss_value` decimal(10,2) DEFAULT 0.00 COMMENT 'Gold Loss Value',
  `setting_charge` decimal(10,2) DEFAULT 0.00 COMMENT 'Setting Charge',
  `purity` decimal(10,2) DEFAULT 0.00,
  `purity_weight` decimal(10,3) DEFAULT 0.000,
  `wastage_per` decimal(10,2) DEFAULT 0.00 COMMENT 'Wastage Per.',
  `wastage_wt` decimal(10,3) DEFAULT 0.000 COMMENT 'Wastage Wt.',
  `final_weight` decimal(10,3) DEFAULT 0.000,
  `alloy_wt` decimal(10,3) DEFAULT 0.000 COMMENT 'Alloy Wt.',
  `net_weight` decimal(10,3) DEFAULT 0.000,
  `pure_weight` decimal(10,3) DEFAULT 0.000,
  `rate` decimal(15,2) DEFAULT 0.00,
  `metal_rate` decimal(15,2) DEFAULT NULL,
  `metal_value` decimal(10,2) DEFAULT 0.00 COMMENT 'Metal Value',
  `metal_cost` decimal(10,2) DEFAULT 0.00 COMMENT 'Metal Cost',
  `making_amount` decimal(15,2) DEFAULT 0.00,
  `stone_cost` decimal(10,2) DEFAULT 0.00 COMMENT 'Stone Cost',
  `making_actual_value` decimal(10,2) DEFAULT 0.00 COMMENT 'Making Actual Value',
  `making_cost` decimal(10,2) DEFAULT 0.00 COMMENT 'Making Cost',
  `min_price` decimal(10,2) DEFAULT 0.00 COMMENT 'Minimum Price',
  `minimum` decimal(10,2) DEFAULT 0.00 COMMENT 'Minimum Price Code',
  `stone_charge_type` varchar(255) DEFAULT NULL COMMENT 'Stone Charge Type',
  `stone_weight` decimal(10,3) DEFAULT 0.000 COMMENT 'Stone Weight',
  `stone_rate` decimal(10,2) DEFAULT 0.00 COMMENT 'Stone Rate',
  `stone_amount` decimal(10,2) DEFAULT 0.00 COMMENT 'Stone Amount',
  `diamond_amount` decimal(10,2) DEFAULT 0.00 COMMENT 'Diamond Amount',
  `purchase_amount` decimal(10,2) DEFAULT 0.00 COMMENT 'Purchase Amount',
  `sale_amount` decimal(10,2) DEFAULT 0.00 COMMENT 'Sale Amount',
  `sale_amount_with` decimal(10,2) DEFAULT 0.00 COMMENT 'Sale Amount With Tax',
  `amount` decimal(15,2) DEFAULT 0.00,
  `discount_type` varchar(255) DEFAULT NULL COMMENT 'Discount Type',
  `discount_per` decimal(10,2) DEFAULT 0.00 COMMENT 'Discount Per.',
  `discount_amount` decimal(10,2) DEFAULT 0.00 COMMENT 'Discount Amount',
  `discount` decimal(10,2) DEFAULT 0.00 COMMENT 'Discount',
  `discount_type2` varchar(255) DEFAULT NULL COMMENT 'Discount Type 2',
  `discount_per2` decimal(10,2) DEFAULT 0.00 COMMENT 'Discount Per. 2',
  `discount_amount2` decimal(10,2) DEFAULT 0.00 COMMENT 'Discount Amount 2',
  `discounted_amt` decimal(10,2) DEFAULT 0.00 COMMENT 'Discounted Amt.',
  `discounted_per` decimal(10,2) DEFAULT 0.00 COMMENT 'Discounted Per.',
  `making_type` varchar(255) DEFAULT NULL COMMENT 'Making Type',
  `making_rate` decimal(10,2) DEFAULT 0.00 COMMENT 'Making Rate',
  `making_discount_amt` decimal(10,2) DEFAULT 0.00 COMMENT 'Making Discount Amount',
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `other_charge_type` varchar(255) DEFAULT NULL COMMENT 'Other Charge Type',
  `other_weight` decimal(10,3) DEFAULT 0.000 COMMENT 'Other Weight',
  `other_rate` decimal(10,2) DEFAULT 0.00 COMMENT 'Other Rate',
  `other_info` varchar(255) DEFAULT NULL COMMENT 'Other Info',
  `other_amount` decimal(10,2) DEFAULT 0.00 COMMENT 'Other Amount',
  `hallmark_amount` decimal(10,2) DEFAULT 0.00 COMMENT 'Hallmark Amount',
  `hallmark_rate` decimal(10,2) DEFAULT 0.00 COMMENT 'HallMark Rate',
  `net_amount` decimal(15,2) DEFAULT 0.00,
  `tax` decimal(10,2) DEFAULT 0.00 COMMENT 'Tax',
  `net_amt_with_tax` decimal(15,2) DEFAULT 0.00,
  `reverse` decimal(10,2) DEFAULT 0.00 COMMENT 'Reverse',
  `merge_group_index` int(10) UNSIGNED DEFAULT NULL COMMENT 'Same value = same product list row (merged modal lines)',
  `design_no` varchar(100) DEFAULT NULL,
  `diamond_category` varchar(50) DEFAULT NULL,
  `huid` varchar(255) DEFAULT NULL COMMENT 'HUID No.',
  `category_id` int(11) DEFAULT NULL COMMENT 'Category ID',
  `calculation_type` varchar(255) DEFAULT NULL COMMENT 'Calculation Type',
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_purchase_invoice_payments`
--

CREATE TABLE `tbl_purchase_invoice_payments` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `payment_type` varchar(50) NOT NULL,
  `deposit_into` varchar(100) DEFAULT NULL,
  `transaction_no` varchar(100) DEFAULT NULL,
  `cheque_date` date DEFAULT NULL,
  `purity_carat` varchar(50) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `previous_balance_amount` decimal(15,2) DEFAULT 0.00 COMMENT 'Amount paid towards previous balance',
  `current_order_amount` decimal(15,2) DEFAULT 0.00 COMMENT 'Amount paid towards current order',
  `diamond_category` varchar(100) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 0.00,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `payment_details` text DEFAULT NULL COMMENT 'JSON copy of payment row (scrap weights, qty, etc.)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_purchase_orders`
--

CREATE TABLE `tbl_purchase_orders` (
  `id` int(11) NOT NULL,
  `order_no` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `against_of` varchar(100) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'AED',
  `ref_no` varchar(100) DEFAULT NULL,
  `sales_person` varchar(255) DEFAULT NULL,
  `order_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `layaways_id` int(11) DEFAULT NULL,
  `fixing_type` varchar(50) DEFAULT 'Standard',
  `previous_balance` decimal(15,2) DEFAULT 0.00,
  `previous_gold` decimal(15,2) DEFAULT 0.00,
  `previous_silver` decimal(15,2) DEFAULT 0.00,
  `subtotal` decimal(15,2) DEFAULT 0.00,
  `additional_amt` decimal(15,2) DEFAULT 0.00,
  `net_total` decimal(15,2) DEFAULT 0.00,
  `reward_points` decimal(15,2) DEFAULT 0.00,
  `coupon_code` varchar(50) DEFAULT NULL,
  `coupon_discount` decimal(15,2) DEFAULT 0.00,
  `discount_amt` decimal(15,2) DEFAULT 0.00,
  `redeem_points` decimal(15,2) DEFAULT 0.00,
  `grand_total` decimal(15,2) DEFAULT 0.00,
  `advance_payment` decimal(15,2) DEFAULT 0.00,
  `metal_amt` decimal(15,2) DEFAULT 0.00,
  `round_off` decimal(15,2) DEFAULT 0.00,
  `paid_amt` decimal(15,2) DEFAULT 0.00,
  `balance_amt` decimal(15,2) DEFAULT 0.00,
  `group_name` varchar(255) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_purchase_order_items`
--

CREATE TABLE `tbl_purchase_order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_characteristic_id` int(11) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `carat` varchar(50) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 1.00,
  `gross_weight` decimal(10,3) DEFAULT 0.000,
  `less_weight` decimal(10,3) DEFAULT 0.000,
  `purity` decimal(10,2) DEFAULT 0.00,
  `purity_weight` decimal(10,3) DEFAULT 0.000,
  `final_weight` decimal(10,3) DEFAULT 0.000,
  `net_weight` decimal(10,3) DEFAULT 0.000,
  `pure_weight` decimal(10,3) DEFAULT 0.000,
  `rate` decimal(15,2) DEFAULT 0.00,
  `making_amount` decimal(15,2) DEFAULT 0.00,
  `amount` decimal(15,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) DEFAULT 0.00,
  `net_amt_with_tax` decimal(15,2) DEFAULT 0.00,
  `design_no` varchar(100) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_purchase_order_payments`
--

CREATE TABLE `tbl_purchase_order_payments` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `payment_type` varchar(50) DEFAULT NULL,
  `deposit_into` varchar(100) DEFAULT NULL,
  `transaction_no` varchar(100) DEFAULT NULL,
  `cheque_date` date DEFAULT NULL,
  `purity_carat` varchar(50) DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT 0.00,
  `previous_balance_amount` decimal(15,2) DEFAULT 0.00,
  `diamond_category` varchar(100) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 0.00,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_purchase_quotations`
--

CREATE TABLE `tbl_purchase_quotations` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `quotation_no` varchar(50) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `supplier_name` varchar(255) NOT NULL,
  `against_of` varchar(100) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'USD',
  `rate` decimal(15,6) DEFAULT 1.000000,
  `ref_no` varchar(100) DEFAULT NULL,
  `purchase_person` varchar(255) DEFAULT NULL,
  `quotation_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `layaways_id` int(11) DEFAULT NULL,
  `fixing_type` varchar(50) DEFAULT 'Standard',
  `ounce_rate` decimal(15,2) DEFAULT 0.00,
  `unfix_dmd_gms` tinyint(1) DEFAULT 0,
  `unfix_metal` tinyint(1) DEFAULT 0,
  `unfix` tinyint(1) DEFAULT 0,
  `previous_balance` decimal(15,2) DEFAULT 0.00,
  `previous_gold` decimal(15,2) DEFAULT 0.00,
  `previous_silver` decimal(15,2) DEFAULT 0.00,
  `subtotal` decimal(15,2) DEFAULT 0.00,
  `additional_amt` decimal(15,2) DEFAULT 0.00,
  `net_total` decimal(15,2) DEFAULT 0.00,
  `discount_amt` decimal(15,2) DEFAULT 0.00,
  `grand_total` decimal(15,2) DEFAULT 0.00,
  `advance_payment` decimal(15,2) DEFAULT 0.00,
  `metal_amt` decimal(15,2) DEFAULT 0.00,
  `round_off` decimal(15,2) DEFAULT 0.00,
  `return_invoice` decimal(15,2) DEFAULT 0.00,
  `paid_amt` decimal(15,2) DEFAULT 0.00,
  `balance_amt` decimal(15,2) DEFAULT 0.00,
  `group_name` varchar(255) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `payment_comments` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `against_type` varchar(50) DEFAULT NULL,
  `against_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_purchase_quotation_items`
--

CREATE TABLE `tbl_purchase_quotation_items` (
  `id` int(11) NOT NULL,
  `quotation_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_characteristic_id` int(11) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `carat` varchar(50) DEFAULT NULL,
  `stone_weight` decimal(10,3) DEFAULT 0.000,
  `stone_rate` decimal(15,2) DEFAULT 0.00,
  `stone_amount` decimal(15,2) DEFAULT 0.00,
  `other_amount` decimal(15,2) DEFAULT 0.00,
  `quantity` decimal(10,2) DEFAULT 1.00,
  `returned_qty` decimal(10,2) NOT NULL DEFAULT 0.00,
  `pending_qty` decimal(10,2) DEFAULT NULL,
  `metal_qty` decimal(12,2) DEFAULT 1.00,
  `gross_weight` decimal(10,3) DEFAULT 0.000,
  `less_weight` decimal(10,3) DEFAULT 0.000,
  `final_weight` decimal(10,3) DEFAULT 0.000,
  `net_weight` decimal(10,3) DEFAULT 0.000,
  `metal_weight` decimal(10,3) DEFAULT 0.000,
  `pure_weight` decimal(10,3) DEFAULT 0.000,
  `purity` decimal(10,6) DEFAULT 0.000000,
  `making_amount` decimal(15,2) DEFAULT 0.00,
  `making_type` varchar(50) DEFAULT NULL,
  `making_rate` decimal(15,2) DEFAULT 0.00,
  `metal_rate` decimal(15,2) DEFAULT NULL,
  `metal_value` decimal(15,2) DEFAULT NULL,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `tax_type` varchar(50) DEFAULT 'no_tax',
  `amount` decimal(15,2) DEFAULT 0.00,
  `rate` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) DEFAULT 0.00,
  `purchase_amount` decimal(15,2) DEFAULT 0.00,
  `sale_amount` decimal(15,2) DEFAULT 0.00,
  `net_amt_weight` decimal(15,2) DEFAULT 0.00,
  `diamond_weight` decimal(10,3) DEFAULT 0.000,
  `gemstone_weight` decimal(10,3) DEFAULT 0.000,
  `diamond_amount` decimal(15,2) DEFAULT 0.00,
  `discount_type` varchar(50) DEFAULT NULL,
  `discount_per` decimal(15,2) DEFAULT 0.00,
  `discount_amount` decimal(15,2) DEFAULT 0.00,
  `design_no` varchar(100) DEFAULT NULL,
  `calculation_type` varchar(100) DEFAULT NULL,
  `diamond_category` varchar(50) DEFAULT NULL,
  `location_id` varchar(100) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_purchase_quotation_payments`
--

CREATE TABLE `tbl_purchase_quotation_payments` (
  `id` int(11) NOT NULL,
  `quotation_id` int(11) NOT NULL,
  `payment_type` varchar(50) NOT NULL,
  `diamond_category` varchar(100) DEFAULT NULL,
  `transaction_no` varchar(100) DEFAULT NULL,
  `transfer_from` varchar(100) DEFAULT NULL,
  `deposit_into` varchar(100) DEFAULT NULL,
  `product` varchar(255) DEFAULT NULL,
  `cheque_date` date DEFAULT NULL,
  `weight` decimal(10,3) DEFAULT 0.000,
  `metal` varchar(100) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 0.00,
  `purity_carat` varchar(50) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_purchase_returns`
--

CREATE TABLE `tbl_purchase_returns` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `return_no` varchar(50) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `supplier_name` varchar(255) NOT NULL,
  `against_of` varchar(100) DEFAULT NULL,
  `against_type` varchar(100) DEFAULT NULL,
  `against_id` int(11) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'USD',
  `ref_no` varchar(100) DEFAULT NULL,
  `sales_person` varchar(255) DEFAULT NULL,
  `return_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `layaways_id` int(11) DEFAULT NULL,
  `fixing_type` varchar(50) DEFAULT 'Standard',
  `ounce_rate` decimal(15,2) DEFAULT 0.00,
  `unfix_dmd_gms` tinyint(1) DEFAULT 0,
  `unfix_metal` tinyint(1) DEFAULT 0,
  `unfix` tinyint(1) DEFAULT 0,
  `previous_balance` decimal(15,2) DEFAULT 0.00,
  `previous_gold` decimal(15,2) DEFAULT 0.00,
  `previous_silver` decimal(15,2) DEFAULT 0.00,
  `subtotal` decimal(15,2) DEFAULT 0.00,
  `additional_amt` decimal(15,2) DEFAULT 0.00,
  `net_total` decimal(15,2) DEFAULT 0.00,
  `discount_amt` decimal(15,2) DEFAULT 0.00,
  `grand_total` decimal(15,2) DEFAULT 0.00,
  `advance_payment` decimal(15,2) DEFAULT 0.00,
  `metal_amt` decimal(15,2) DEFAULT 0.00,
  `round_off` decimal(15,2) DEFAULT 0.00,
  `credit_note` decimal(15,2) DEFAULT 0.00,
  `paid_amt` decimal(15,2) DEFAULT 0.00,
  `balance_amt` decimal(15,2) DEFAULT 0.00,
  `group_name` varchar(255) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_purchase_return_items`
--

CREATE TABLE `tbl_purchase_return_items` (
  `id` int(11) NOT NULL,
  `return_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_characteristic_id` int(11) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `carat` varchar(50) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 1.00,
  `gross_weight` decimal(10,3) DEFAULT 0.000,
  `less_weight` decimal(10,3) DEFAULT 0.000,
  `purity` decimal(10,2) DEFAULT 0.00,
  `purity_weight` decimal(10,3) DEFAULT 0.000,
  `final_weight` decimal(10,3) DEFAULT 0.000,
  `net_weight` decimal(10,3) DEFAULT 0.000,
  `pure_weight` decimal(10,3) DEFAULT 0.000,
  `rate` decimal(15,2) DEFAULT 0.00,
  `making_amount` decimal(15,2) DEFAULT 0.00,
  `amount` decimal(15,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) DEFAULT 0.00,
  `net_amt_with_tax` decimal(15,2) DEFAULT 0.00,
  `net_amt_weight` decimal(15,2) DEFAULT 0.00,
  `diamond_weight` decimal(10,3) DEFAULT 0.000,
  `gemstone_weight` decimal(10,3) DEFAULT 0.000,
  `diamond_amount` decimal(15,2) DEFAULT 0.00,
  `design_no` varchar(100) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_purchase_return_payments`
--

CREATE TABLE `tbl_purchase_return_payments` (
  `id` int(11) NOT NULL,
  `return_id` int(11) NOT NULL,
  `payment_type` varchar(50) NOT NULL,
  `diamond_category` varchar(100) DEFAULT NULL,
  `transaction_no` varchar(100) DEFAULT NULL,
  `transfer_from` varchar(100) DEFAULT NULL,
  `deposit_into` varchar(100) DEFAULT NULL,
  `product` varchar(255) DEFAULT NULL,
  `cheque_date` date DEFAULT NULL,
  `weight` decimal(10,3) DEFAULT 0.000,
  `metal` varchar(100) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 0.00,
  `purity_carat` varchar(50) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `previous_balance_amount` decimal(15,2) DEFAULT 0.00,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_receipt_vouchers`
--

CREATE TABLE `tbl_receipt_vouchers` (
  `id` int(11) NOT NULL,
  `voucher_no` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `ref_no` varchar(100) DEFAULT NULL,
  `receipt_no` varchar(100) DEFAULT NULL,
  `voucher_type` varchar(50) DEFAULT NULL,
  `against` varchar(100) DEFAULT NULL,
  `sales_person` varchar(255) DEFAULT NULL,
  `against_of` varchar(100) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'USD',
  `voucher_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `layaways_id` int(11) DEFAULT NULL,
  `fixing_type` varchar(50) DEFAULT 'Standard',
  `previous_balance` decimal(15,2) DEFAULT 0.00,
  `previous_gold` decimal(10,3) DEFAULT 0.000,
  `previous_silver` decimal(10,3) DEFAULT 0.000,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `total_gold` decimal(10,3) DEFAULT 0.000,
  `total_silver` decimal(10,3) DEFAULT 0.000,
  `comment` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_receipt_voucher_items`
--

CREATE TABLE `tbl_receipt_voucher_items` (
  `id` int(11) NOT NULL,
  `voucher_id` int(11) NOT NULL,
  `payment_type` varchar(50) DEFAULT NULL,
  `diamond_category` varchar(100) DEFAULT NULL,
  `transaction_no` varchar(100) DEFAULT NULL,
  `deposit_into` varchar(100) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `cheque_date` date DEFAULT NULL,
  `weight` decimal(10,3) DEFAULT 0.000,
  `metal_id` int(11) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 0.00,
  `purity_carat` varchar(50) DEFAULT NULL,
  `purity_wt` decimal(10,3) DEFAULT 0.000,
  `amount` decimal(15,2) DEFAULT 0.00,
  `previous_balance_amount` decimal(15,2) DEFAULT 0.00,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_remark`
--

CREATE TABLE `tbl_remark` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `name` varchar(150) NOT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_repair_invoices`
--

CREATE TABLE `tbl_repair_invoices` (
  `id` int(11) NOT NULL,
  `repair_invoice_no` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `against_of` varchar(100) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'AED',
  `ref_no` varchar(100) DEFAULT NULL,
  `sales_person` varchar(255) DEFAULT NULL,
  `repair_invoice_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `layaways_id` int(11) DEFAULT NULL,
  `fixing_type` varchar(50) DEFAULT 'Standard',
  `previous_balance` decimal(15,2) DEFAULT 0.00,
  `previous_gold` decimal(15,2) DEFAULT 0.00,
  `previous_silver` decimal(15,2) DEFAULT 0.00,
  `subtotal` decimal(15,2) DEFAULT 0.00,
  `additional_amt` decimal(15,2) DEFAULT 0.00,
  `net_total` decimal(15,2) DEFAULT 0.00,
  `reward_points` decimal(15,2) DEFAULT 0.00,
  `coupon_code` varchar(50) DEFAULT NULL,
  `coupon_discount` decimal(15,2) DEFAULT 0.00,
  `discount_amt` decimal(15,2) DEFAULT 0.00,
  `redeem_points` decimal(15,2) DEFAULT 0.00,
  `grand_total` decimal(15,2) DEFAULT 0.00,
  `advance_payment` decimal(15,2) DEFAULT 0.00,
  `metal_amt` decimal(15,2) DEFAULT 0.00,
  `round_off` decimal(15,2) DEFAULT 0.00,
  `paid_amt` decimal(15,2) DEFAULT 0.00,
  `balance_amt` decimal(15,2) DEFAULT 0.00,
  `group_name` varchar(255) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_repair_invoice_items`
--

CREATE TABLE `tbl_repair_invoice_items` (
  `id` int(11) NOT NULL,
  `repair_invoice_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_characteristic_id` int(11) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `carat` varchar(50) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 1.00,
  `gross_weight` decimal(10,3) DEFAULT 0.000,
  `less_weight` decimal(10,3) DEFAULT 0.000,
  `purity` decimal(10,2) DEFAULT 0.00,
  `purity_weight` decimal(10,3) DEFAULT 0.000,
  `final_weight` decimal(10,3) DEFAULT 0.000,
  `net_weight` decimal(10,3) DEFAULT 0.000,
  `pure_weight` decimal(10,3) DEFAULT 0.000,
  `rate` decimal(15,2) DEFAULT 0.00,
  `making_amount` decimal(15,2) DEFAULT 0.00,
  `amount` decimal(15,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) DEFAULT 0.00,
  `net_amt_with_tax` decimal(15,2) DEFAULT 0.00,
  `design_no` varchar(100) DEFAULT NULL,
  `location_id` int(11) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_repair_invoice_payments`
--

CREATE TABLE `tbl_repair_invoice_payments` (
  `id` int(11) NOT NULL,
  `repair_invoice_id` int(11) NOT NULL,
  `payment_type` varchar(50) NOT NULL,
  `deposit_into` varchar(100) DEFAULT NULL,
  `transaction_no` varchar(100) DEFAULT NULL,
  `cheque_date` date DEFAULT NULL,
  `purity_carat` varchar(50) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `previous_balance_amount` decimal(15,2) DEFAULT 0.00,
  `current_order_amount` decimal(15,2) DEFAULT 0.00,
  `diamond_category` varchar(100) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 0.00,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_repair_jobwork_orders`
--

CREATE TABLE `tbl_repair_jobwork_orders` (
  `id` int(11) NOT NULL,
  `jobwork_no` varchar(50) NOT NULL DEFAULT '',
  `repair_order_id` int(11) NOT NULL,
  `repair_order_no` varchar(50) NOT NULL DEFAULT '',
  `customer_name` varchar(255) DEFAULT NULL,
  `order_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `grand_total` decimal(15,2) DEFAULT 0.00,
  `status` varchar(30) DEFAULT 'draft',
  `department_id` int(11) DEFAULT NULL,
  `department_user_id` int(11) DEFAULT NULL,
  `priority` varchar(30) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_repair_jobwork_order_items`
--

CREATE TABLE `tbl_repair_jobwork_order_items` (
  `id` int(11) NOT NULL,
  `repair_jobwork_order_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_characteristic_id` int(11) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `design_no` varchar(100) DEFAULT NULL,
  `carat` varchar(50) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 1.00,
  `gross_weight` decimal(10,3) DEFAULT 0.000,
  `less_weight` decimal(10,3) DEFAULT 0.000,
  `purity` decimal(10,2) DEFAULT 0.00,
  `purity_weight` decimal(10,3) DEFAULT 0.000,
  `final_weight` decimal(10,3) DEFAULT 0.000,
  `net_weight` decimal(10,3) DEFAULT 0.000,
  `pure_weight` decimal(10,3) DEFAULT 0.000,
  `rate` decimal(15,2) DEFAULT 0.00,
  `making_amount` decimal(15,2) DEFAULT 0.00,
  `amount` decimal(15,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) DEFAULT 0.00,
  `net_amt_with_tax` decimal(15,2) DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_repair_material_issues`
--

CREATE TABLE `tbl_repair_material_issues` (
  `id` int(11) NOT NULL,
  `material_issue_no` varchar(50) NOT NULL DEFAULT '',
  `repair_order_id` int(11) NOT NULL,
  `repair_order_no` varchar(50) NOT NULL DEFAULT '',
  `customer_name` varchar(255) DEFAULT NULL,
  `order_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `grand_total` decimal(15,2) DEFAULT 0.00,
  `status` varchar(30) DEFAULT 'draft',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_repair_material_issue_items`
--

CREATE TABLE `tbl_repair_material_issue_items` (
  `id` int(11) NOT NULL,
  `repair_material_issue_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_characteristic_id` int(11) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `design_no` varchar(100) DEFAULT NULL,
  `carat` varchar(50) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 1.00,
  `gross_weight` decimal(10,3) DEFAULT 0.000,
  `less_weight` decimal(10,3) DEFAULT 0.000,
  `purity` decimal(10,2) DEFAULT 0.00,
  `purity_weight` decimal(10,3) DEFAULT 0.000,
  `final_weight` decimal(10,3) DEFAULT 0.000,
  `net_weight` decimal(10,3) DEFAULT 0.000,
  `pure_weight` decimal(10,3) DEFAULT 0.000,
  `rate` decimal(15,2) DEFAULT 0.00,
  `making_amount` decimal(15,2) DEFAULT 0.00,
  `amount` decimal(15,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) DEFAULT 0.00,
  `net_amt_with_tax` decimal(15,2) DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_repair_material_receives`
--

CREATE TABLE `tbl_repair_material_receives` (
  `id` int(11) NOT NULL,
  `material_receive_no` varchar(50) NOT NULL DEFAULT '',
  `repair_order_id` int(11) NOT NULL,
  `repair_order_no` varchar(50) NOT NULL DEFAULT '',
  `customer_name` varchar(255) DEFAULT NULL,
  `order_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `grand_total` decimal(15,2) DEFAULT 0.00,
  `status` varchar(30) DEFAULT 'draft',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_repair_material_receive_items`
--

CREATE TABLE `tbl_repair_material_receive_items` (
  `id` int(11) NOT NULL,
  `repair_material_receive_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_characteristic_id` int(11) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `design_no` varchar(100) DEFAULT NULL,
  `carat` varchar(50) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 1.00,
  `gross_weight` decimal(10,3) DEFAULT 0.000,
  `less_weight` decimal(10,3) DEFAULT 0.000,
  `purity` decimal(10,2) DEFAULT 0.00,
  `purity_weight` decimal(10,3) DEFAULT 0.000,
  `final_weight` decimal(10,3) DEFAULT 0.000,
  `net_weight` decimal(10,3) DEFAULT 0.000,
  `pure_weight` decimal(10,3) DEFAULT 0.000,
  `rate` decimal(15,2) DEFAULT 0.00,
  `making_amount` decimal(15,2) DEFAULT 0.00,
  `amount` decimal(15,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) DEFAULT 0.00,
  `net_amt_with_tax` decimal(15,2) DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_repair_orders`
--

CREATE TABLE `tbl_repair_orders` (
  `id` int(11) NOT NULL,
  `order_no` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `against_of` varchar(100) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'AED',
  `ref_no` varchar(100) DEFAULT NULL,
  `sales_person` varchar(255) DEFAULT NULL,
  `order_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `layaways_id` int(11) DEFAULT NULL,
  `fixing_type` varchar(50) DEFAULT 'Standard',
  `previous_balance` decimal(15,2) DEFAULT 0.00,
  `previous_gold` decimal(15,2) DEFAULT 0.00,
  `previous_silver` decimal(15,2) DEFAULT 0.00,
  `subtotal` decimal(15,2) DEFAULT 0.00,
  `additional_amt` decimal(15,2) DEFAULT 0.00,
  `net_total` decimal(15,2) DEFAULT 0.00,
  `reward_points` decimal(15,2) DEFAULT 0.00,
  `coupon_code` varchar(50) DEFAULT NULL,
  `coupon_discount` decimal(15,2) DEFAULT 0.00,
  `discount_amt` decimal(15,2) DEFAULT 0.00,
  `redeem_points` decimal(15,2) DEFAULT 0.00,
  `grand_total` decimal(15,2) DEFAULT 0.00,
  `advance_payment` decimal(15,2) DEFAULT 0.00,
  `metal_amt` decimal(15,2) DEFAULT 0.00,
  `round_off` decimal(15,2) DEFAULT 0.00,
  `paid_amt` decimal(15,2) DEFAULT 0.00,
  `balance_amt` decimal(15,2) DEFAULT 0.00,
  `group_name` varchar(255) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_repair_order_items`
--

CREATE TABLE `tbl_repair_order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_characteristic_id` int(11) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `carat` varchar(50) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 1.00,
  `gross_weight` decimal(10,3) DEFAULT 0.000,
  `less_weight` decimal(10,3) DEFAULT 0.000,
  `purity` decimal(10,2) DEFAULT 0.00,
  `purity_weight` decimal(10,3) DEFAULT 0.000,
  `final_weight` decimal(10,3) DEFAULT 0.000,
  `net_weight` decimal(10,3) DEFAULT 0.000,
  `pure_weight` decimal(10,3) DEFAULT 0.000,
  `rate` decimal(15,2) DEFAULT 0.00,
  `making_amount` decimal(15,2) DEFAULT 0.00,
  `amount` decimal(15,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) DEFAULT 0.00,
  `net_amt_with_tax` decimal(15,2) DEFAULT 0.00,
  `design_no` varchar(100) DEFAULT NULL,
  `location_id` int(11) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `images` text DEFAULT NULL COMMENT 'JSON: primary + image paths (repair order line photos)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_repair_order_payments`
--

CREATE TABLE `tbl_repair_order_payments` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `payment_type` varchar(50) NOT NULL,
  `deposit_into` varchar(100) DEFAULT NULL,
  `transaction_no` varchar(100) DEFAULT NULL,
  `cheque_date` date DEFAULT NULL,
  `purity_carat` varchar(50) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `previous_balance_amount` decimal(15,2) DEFAULT 0.00,
  `current_order_amount` decimal(15,2) DEFAULT 0.00,
  `diamond_category` varchar(100) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 0.00,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_roles`
--

CREATE TABLE `tbl_roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `role_name` varchar(128) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `account_ledger_assigned` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_sales_team_inventory_assign`
--

CREATE TABLE `tbl_sales_team_inventory_assign` (
  `id` int(10) UNSIGNED NOT NULL,
  `sales_person` varchar(255) NOT NULL,
  `branch_id` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `barcode_no` varchar(128) NOT NULL,
  `row_json` longtext DEFAULT NULL COMMENT 'Full grid row JSON for round-trip',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_sale_fixing_direct`
--

CREATE TABLE `tbl_sale_fixing_direct` (
  `id` int(11) NOT NULL,
  `ref_no` varchar(50) DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `sales_person` varchar(255) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `layaways` tinyint(1) DEFAULT 0,
  `against` varchar(50) DEFAULT NULL,
  `against_of` varchar(255) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'USD',
  `currency_rate` decimal(10,2) DEFAULT 1.00,
  `goz` decimal(10,2) DEFAULT 0.00,
  `fixing_date` date DEFAULT NULL,
  `fixing_type` varchar(50) DEFAULT 'Standard',
  `previous_balance` decimal(15,2) DEFAULT 0.00,
  `previous_gold` decimal(10,3) DEFAULT 0.000,
  `previous_silver` decimal(10,3) DEFAULT 0.000,
  `total_gross_wt` decimal(10,3) DEFAULT 0.000,
  `total_purity_wt` decimal(10,3) DEFAULT 0.000,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `comment` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_sale_fixing_direct_items`
--

CREATE TABLE `tbl_sale_fixing_direct_items` (
  `id` int(11) NOT NULL,
  `fixing_id` int(11) NOT NULL,
  `metal_id` int(11) NOT NULL,
  `gross_wt` decimal(10,3) DEFAULT 0.000,
  `purity_wt` decimal(10,3) DEFAULT 0.000,
  `rate` decimal(15,2) DEFAULT 0.00,
  `amount` decimal(15,2) DEFAULT 0.00,
  `purity` decimal(10,2) DEFAULT 1.00,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_sale_invoices`
--

CREATE TABLE `tbl_sale_invoices` (
  `id` int(11) NOT NULL,
  `invoice_no` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `against_of` varchar(100) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'AED',
  `ref_no` varchar(100) DEFAULT NULL,
  `sales_person` varchar(255) DEFAULT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `layaways_id` int(11) DEFAULT NULL,
  `fixing_type` varchar(50) DEFAULT 'Standard',
  `previous_balance` decimal(15,2) DEFAULT 0.00,
  `previous_gold` decimal(15,2) DEFAULT 0.00,
  `previous_silver` decimal(15,2) DEFAULT 0.00,
  `previous_diamond` decimal(12,3) DEFAULT 0.000,
  `previous_gemstone` decimal(12,3) DEFAULT 0.000,
  `subtotal` decimal(15,2) DEFAULT 0.00,
  `additional_amt` decimal(15,2) DEFAULT 0.00,
  `net_total` decimal(15,2) DEFAULT 0.00,
  `reward_points` decimal(15,2) DEFAULT 0.00,
  `coupon_code` varchar(50) DEFAULT NULL,
  `coupon_discount` decimal(15,2) DEFAULT 0.00,
  `discount_amt` decimal(15,2) DEFAULT 0.00,
  `discount_percent` decimal(10,2) DEFAULT 0.00,
  `redeem_points` decimal(15,2) DEFAULT 0.00,
  `grand_total` decimal(15,2) DEFAULT 0.00,
  `advance_payment` decimal(15,2) DEFAULT 0.00,
  `metal_amt` decimal(15,2) DEFAULT 0.00,
  `round_off` decimal(15,2) DEFAULT 0.00,
  `paid_amt` decimal(15,2) DEFAULT 0.00,
  `balance_amt` decimal(15,2) DEFAULT 0.00,
  `adjusted_balance_used` decimal(14,2) DEFAULT 0.00 COMMENT 'Amount of adjusted balance used in this invoice',
  `group_name` varchar(255) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `payment_comments` text DEFAULT NULL COMMENT 'JSON array of comments: [{text, added_by, added_at}]',
  `status` varchar(20) DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `use_previous_balance` tinyint(1) DEFAULT 0 COMMENT '1=used previous balance on this invoice',
  `previous_balance_used_amt` decimal(15,2) DEFAULT 0.00 COMMENT 'Amount used from previous balance (e.g. 500.00)',
  `gst_supply_mode` varchar(24) DEFAULT NULL COMMENT 'intrastate|interstate',
  `gst_cgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `gst_sgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `gst_igst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `customer_gstin` varchar(20) DEFAULT NULL,
  `eway_vehicle_no` varchar(32) DEFAULT NULL,
  `eway_distance_km` decimal(10,2) DEFAULT NULL,
  `eway_bill_no` varchar(50) DEFAULT NULL,
  `eway_bill_date` datetime DEFAULT NULL,
  `eway_status` varchar(32) DEFAULT NULL,
  `eway_response` text DEFAULT NULL,
  `eway_valid_upto` varchar(50) DEFAULT NULL,
  `eway_generated_at` datetime DEFAULT NULL,
  `eway_trans_mode` varchar(2) DEFAULT NULL,
  `eway_transporter_name` varchar(200) DEFAULT NULL,
  `eway_transporter_id` varchar(20) DEFAULT NULL,
  `eway_trans_doc_no` varchar(100) DEFAULT NULL,
  `eway_trans_doc_date` varchar(20) DEFAULT NULL,
  `eway_vehicle_type` varchar(1) DEFAULT NULL,
  `eway_enable` tinyint(1) NOT NULL DEFAULT 0,
  `eway_to_pincode` varchar(10) DEFAULT NULL,
  `eway_trans_distance` varchar(12) DEFAULT NULL,
  `eway_request_json` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_sale_invoice_items`
--

CREATE TABLE `tbl_sale_invoice_items` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order (drag-and-drop)',
  `product_id` int(11) NOT NULL,
  `product_characteristic_id` int(11) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `carat` varchar(50) DEFAULT NULL,
  `stone_weight` decimal(10,3) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 1.00,
  `metal_qty` decimal(12,2) DEFAULT 1.00,
  `metal_weight` decimal(12,4) DEFAULT 0.0000,
  `gross_weight` decimal(10,3) DEFAULT 0.000,
  `less_weight` decimal(10,3) DEFAULT 0.000,
  `purity` decimal(10,2) DEFAULT 0.00,
  `purity_weight` decimal(10,3) DEFAULT 0.000,
  `final_weight` decimal(10,3) DEFAULT 0.000,
  `net_weight` decimal(10,3) DEFAULT 0.000,
  `pure_weight` decimal(10,3) DEFAULT 0.000,
  `rate` decimal(15,2) DEFAULT 0.00,
  `metal_value` decimal(15,2) DEFAULT NULL,
  `metal_rate` decimal(15,2) DEFAULT NULL,
  `making_amount` decimal(15,2) DEFAULT 0.00,
  `amount` decimal(15,2) DEFAULT 0.00,
  `stone_amount` decimal(15,2) DEFAULT NULL,
  `diamond_amount` decimal(15,2) DEFAULT NULL,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) DEFAULT 0.00,
  `net_amt_with_tax` decimal(15,2) DEFAULT 0.00,
  `merge_group_index` int(10) UNSIGNED DEFAULT NULL COMMENT 'Same value = same product list row (merged modal lines)',
  `design_no` varchar(100) DEFAULT NULL,
  `calculation_type` varchar(100) DEFAULT NULL,
  `diamond_category` varchar(50) DEFAULT NULL,
  `location_id` int(11) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_sale_invoice_payments`
--

CREATE TABLE `tbl_sale_invoice_payments` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `payment_type` varchar(50) NOT NULL,
  `deposit_into` varchar(100) DEFAULT NULL,
  `transaction_no` varchar(100) DEFAULT NULL,
  `cheque_date` date DEFAULT NULL,
  `purity_carat` varchar(50) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `previous_balance_amount` decimal(15,2) DEFAULT 0.00,
  `current_order_amount` decimal(15,2) DEFAULT 0.00,
  `diamond_category` varchar(100) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 0.00,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `payment_details` text DEFAULT NULL COMMENT 'JSON copy of payment row (scrap weights, qty, etc.)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_sale_orders`
--

CREATE TABLE `tbl_sale_orders` (
  `id` int(11) NOT NULL,
  `order_no` varchar(50) DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `against_of` varchar(100) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'AED',
  `currency_rate` decimal(10,4) DEFAULT 1.0000,
  `ref_no` varchar(100) DEFAULT NULL,
  `sales_person` varchar(255) DEFAULT NULL,
  `order_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `layaways_id` int(11) DEFAULT NULL,
  `fixing_type` varchar(50) DEFAULT 'Standard',
  `previous_balance` decimal(10,2) DEFAULT 0.00,
  `previous_gold` decimal(10,3) DEFAULT 0.000,
  `previous_silver` decimal(10,3) DEFAULT 0.000,
  `subtotal` decimal(10,2) DEFAULT 0.00,
  `additional_amt` decimal(10,2) DEFAULT 0.00,
  `net_total` decimal(10,2) DEFAULT 0.00,
  `reward_points` decimal(10,2) DEFAULT 0.00,
  `coupon_code` varchar(100) DEFAULT NULL,
  `coupon_discount` decimal(10,2) DEFAULT 0.00,
  `discount_amt` decimal(10,2) DEFAULT 0.00,
  `redeem_points` decimal(10,2) DEFAULT 0.00,
  `grand_total` decimal(10,2) DEFAULT 0.00,
  `advance_payment` decimal(10,2) DEFAULT 0.00,
  `metal_amt` decimal(10,2) DEFAULT 0.00,
  `round_off` decimal(10,2) DEFAULT 0.00,
  `paid_amt` decimal(10,2) DEFAULT 0.00,
  `balance_amt` decimal(10,2) DEFAULT 0.00,
  `group_name` varchar(255) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_sale_order_diamond_stock_issue`
--

CREATE TABLE `tbl_sale_order_diamond_stock_issue` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `stock_id` int(11) NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `diamond_category` varchar(100) DEFAULT NULL,
  `weight` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `qty` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_sale_order_items`
--

CREATE TABLE `tbl_sale_order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_characteristic_id` int(11) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `carat` varchar(50) DEFAULT NULL,
  `quantity` decimal(10,3) DEFAULT 1.000,
  `gross_weight` decimal(10,3) DEFAULT 0.000,
  `less_weight` decimal(10,3) DEFAULT 0.000,
  `purity` decimal(10,2) DEFAULT 0.00,
  `purity_weight` decimal(10,3) DEFAULT 0.000,
  `final_weight` decimal(10,3) DEFAULT 0.000,
  `net_weight` decimal(10,3) DEFAULT 0.000,
  `pure_weight` decimal(10,3) DEFAULT 0.000,
  `rate` decimal(10,2) DEFAULT 0.00,
  `stone_charges` decimal(10,2) DEFAULT 0.00,
  `stone_amount` decimal(10,2) DEFAULT 0.00,
  `other_charges` decimal(10,2) DEFAULT 0.00,
  `other_amount` decimal(10,2) DEFAULT 0.00,
  `diamond_value` decimal(10,2) DEFAULT 0.00,
  `diamond_amount` decimal(10,2) DEFAULT 0.00,
  `gemstone_value` decimal(10,2) DEFAULT 0.00,
  `discount` decimal(10,2) DEFAULT 0.00,
  `metal_value` decimal(10,2) DEFAULT 0.00,
  `making_type` varchar(50) DEFAULT 'Fix',
  `making_rate` decimal(10,2) DEFAULT 0.00,
  `making_amount` decimal(10,2) DEFAULT 0.00,
  `making_cost` decimal(10,2) DEFAULT 0.00,
  `amount` decimal(10,2) DEFAULT 0.00,
  `tax_percent` decimal(10,2) DEFAULT 0.00,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `net_amount` decimal(10,2) DEFAULT 0.00,
  `net_amt_with_tax` decimal(10,2) DEFAULT 0.00,
  `purchase_amount` decimal(10,2) DEFAULT 0.00,
  `sale_amount` decimal(10,2) DEFAULT 0.00,
  `sale_amount_with` decimal(10,2) DEFAULT 0.00,
  `reverse` decimal(10,2) DEFAULT 0.00,
  `design_no` varchar(100) DEFAULT NULL,
  `metal_unfix` tinyint(1) DEFAULT 0,
  `unfix` tinyint(1) DEFAULT 0,
  `ounce_rate` tinyint(1) DEFAULT 0,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `images` text DEFAULT NULL COMMENT 'JSON: primary + image paths (sale order line photos)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_sale_order_payments`
--

CREATE TABLE `tbl_sale_order_payments` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `payment_type` varchar(50) NOT NULL,
  `deposit_into` varchar(255) DEFAULT NULL,
  `transaction_no` varchar(100) DEFAULT NULL,
  `cheque_date` date DEFAULT NULL,
  `purity_carat` varchar(50) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT 0.00,
  `previous_balance_amount` decimal(15,2) DEFAULT 0.00 COMMENT 'Amount paid towards previous balance',
  `diamond_category` varchar(100) DEFAULT NULL,
  `quantity` decimal(10,3) DEFAULT 0.000,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `payment_details` text DEFAULT NULL COMMENT 'JSON: scrap modal fields, etc.'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_sale_order_stone_stock_issue`
--

CREATE TABLE `tbl_sale_order_stone_stock_issue` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `stock_id` int(11) NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `stone_category` varchar(100) DEFAULT NULL,
  `weight` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `qty` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_sale_quotations`
--

CREATE TABLE `tbl_sale_quotations` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `quotation_no` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `against_of` varchar(100) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'AED',
  `ref_no` varchar(100) DEFAULT NULL,
  `sales_person` varchar(255) DEFAULT NULL,
  `quotation_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `layaways_id` int(11) DEFAULT NULL,
  `fixing_type` varchar(50) DEFAULT 'Standard',
  `previous_balance` decimal(15,2) DEFAULT 0.00,
  `previous_gold` decimal(15,2) DEFAULT 0.00,
  `previous_silver` decimal(15,2) DEFAULT 0.00,
  `subtotal` decimal(15,2) DEFAULT 0.00,
  `additional_amt` decimal(15,2) DEFAULT 0.00,
  `net_total` decimal(15,2) DEFAULT 0.00,
  `reward_points` decimal(15,2) DEFAULT 0.00,
  `coupon_code` varchar(50) DEFAULT NULL,
  `coupon_discount` decimal(15,2) DEFAULT 0.00,
  `discount_amt` decimal(15,2) DEFAULT 0.00,
  `redeem_points` decimal(15,2) DEFAULT 0.00,
  `grand_total` decimal(15,2) DEFAULT 0.00,
  `advance_payment` decimal(15,2) DEFAULT 0.00,
  `metal_amt` decimal(15,2) DEFAULT 0.00,
  `round_off` decimal(15,2) DEFAULT 0.00,
  `paid_amt` decimal(15,2) DEFAULT 0.00,
  `balance_amt` decimal(15,2) DEFAULT 0.00,
  `adjusted_balance_used` decimal(15,2) DEFAULT 0.00,
  `group_name` varchar(255) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `payment_comments` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'draft',
  `validity_days` int(11) DEFAULT 30,
  `expiry_date` date DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `against_type` varchar(50) DEFAULT NULL,
  `against_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_sale_quotation_items`
--

CREATE TABLE `tbl_sale_quotation_items` (
  `id` int(11) NOT NULL,
  `quotation_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_characteristic_id` int(11) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `carat` varchar(50) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 1.00,
  `metal_qty` decimal(12,2) DEFAULT 1.00,
  `metal_weight` decimal(12,4) DEFAULT 0.0000,
  `gross_weight` decimal(10,3) DEFAULT 0.000,
  `less_weight` decimal(10,3) DEFAULT 0.000,
  `purity` decimal(10,2) DEFAULT 0.00,
  `purity_weight` decimal(10,3) DEFAULT 0.000,
  `final_weight` decimal(10,3) DEFAULT 0.000,
  `net_weight` decimal(10,3) DEFAULT 0.000,
  `pure_weight` decimal(10,3) DEFAULT 0.000,
  `rate` decimal(15,2) DEFAULT 0.00,
  `metal_rate` decimal(15,2) DEFAULT NULL,
  `metal_value` decimal(15,2) DEFAULT NULL,
  `making_amount` decimal(15,2) DEFAULT 0.00,
  `amount` decimal(15,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) DEFAULT 0.00,
  `net_amt_with_tax` decimal(15,2) DEFAULT 0.00,
  `design_no` varchar(100) DEFAULT NULL,
  `diamond_category` varchar(50) DEFAULT NULL,
  `calculation_type` varchar(100) DEFAULT NULL,
  `location_id` int(11) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `diamond_amount` decimal(15,2) DEFAULT NULL,
  `stone_amount` decimal(15,2) DEFAULT NULL,
  `stone_weight` decimal(10,3) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_sale_quotation_payments`
--

CREATE TABLE `tbl_sale_quotation_payments` (
  `id` int(11) NOT NULL,
  `quotation_id` int(11) NOT NULL,
  `payment_type` varchar(50) NOT NULL,
  `deposit_into` varchar(100) DEFAULT NULL,
  `transaction_no` varchar(100) DEFAULT NULL,
  `cheque_date` date DEFAULT NULL,
  `purity_carat` varchar(50) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `previous_balance_amount` decimal(15,2) DEFAULT 0.00,
  `current_order_amount` decimal(15,2) DEFAULT 0.00,
  `diamond_category` varchar(100) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 0.00,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_sale_receipt_vouchers`
--

CREATE TABLE `tbl_sale_receipt_vouchers` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `voucher_no` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `sale_invoice_no` varchar(100) NOT NULL COMMENT 'tbl_sale_invoices.invoice_no / tbl_pos_sale_invoices.invoice_no',
  `against` varchar(100) DEFAULT 'Sale Invoice',
  `sales_person` varchar(255) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'AED',
  `voucher_date` date NOT NULL,
  `fixing_type` varchar(50) DEFAULT 'Standard',
  `previous_balance` decimal(15,2) DEFAULT 0.00,
  `previous_gold` decimal(10,3) DEFAULT 0.000,
  `previous_silver` decimal(10,3) DEFAULT 0.000,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `total_gold` decimal(10,3) DEFAULT 0.000,
  `total_silver` decimal(10,3) DEFAULT 0.000,
  `comment` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'saved',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_sale_receipt_voucher_items`
--

CREATE TABLE `tbl_sale_receipt_voucher_items` (
  `id` int(11) NOT NULL,
  `sale_receipt_voucher_id` int(11) NOT NULL,
  `payment_type` varchar(50) DEFAULT NULL,
  `diamond_category` varchar(100) DEFAULT NULL,
  `transaction_no` varchar(100) DEFAULT NULL,
  `deposit_into` varchar(100) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `cheque_date` date DEFAULT NULL,
  `weight` decimal(10,3) DEFAULT 0.000,
  `metal_id` int(11) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 0.00,
  `purity_carat` varchar(50) DEFAULT NULL,
  `purity_wt` decimal(10,3) DEFAULT 0.000,
  `amount` decimal(15,2) DEFAULT 0.00,
  `previous_balance_amount` decimal(15,2) DEFAULT 0.00,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_sale_returns`
--

CREATE TABLE `tbl_sale_returns` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `return_no` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `against_of` varchar(100) DEFAULT NULL,
  `against_type` varchar(50) DEFAULT NULL COMMENT 'Direct, Sale Invoice, Sale Quotation',
  `against_id` int(11) DEFAULT NULL COMMENT 'ID of selected Sale Invoice or Sale Quotation',
  `currency` varchar(10) DEFAULT 'USD',
  `rate` decimal(15,6) DEFAULT 1.000000,
  `ref_no` varchar(100) DEFAULT NULL,
  `sales_person` varchar(255) DEFAULT NULL,
  `return_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `layaways_id` int(11) DEFAULT NULL,
  `fixing_type` varchar(50) DEFAULT 'Standard',
  `ounce_rate` decimal(15,2) DEFAULT 0.00,
  `unfix_dmd_gms` tinyint(1) DEFAULT 0,
  `unfix_metal` tinyint(1) DEFAULT 0,
  `unfix` tinyint(1) DEFAULT 0,
  `previous_balance` decimal(15,2) DEFAULT 0.00,
  `previous_gold` decimal(15,2) DEFAULT 0.00,
  `previous_silver` decimal(15,2) DEFAULT 0.00,
  `subtotal` decimal(15,2) DEFAULT 0.00,
  `net_total` decimal(15,2) DEFAULT 0.00,
  `grand_total` decimal(15,2) DEFAULT 0.00,
  `round_off` decimal(15,2) DEFAULT 0.00,
  `credit_note` decimal(15,2) DEFAULT 0.00,
  `comment` text DEFAULT NULL,
  `payment_comments` text DEFAULT NULL COMMENT 'JSON: [{text, added_by, added_at}]',
  `status` varchar(20) DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_sale_return_items`
--

CREATE TABLE `tbl_sale_return_items` (
  `id` int(11) NOT NULL,
  `return_id` int(11) NOT NULL,
  `source_against_item_id` int(11) DEFAULT NULL,
  `product_id` int(11) NOT NULL,
  `product_characteristic_id` int(11) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `diamond_category` varchar(100) DEFAULT NULL COMMENT 'Diamonds, GemStones, Jewellery',
  `carat` varchar(50) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 1.00,
  `metal_qty` decimal(12,2) DEFAULT 1.00,
  `metal_weight` decimal(12,4) DEFAULT 0.0000,
  `gross_weight` decimal(10,3) DEFAULT 0.000,
  `final_weight` decimal(10,3) DEFAULT 0.000,
  `net_weight` decimal(10,3) DEFAULT 0.000,
  `pure_weight` decimal(10,3) DEFAULT 0.000,
  `making_amount` decimal(15,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `amount` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) DEFAULT 0.00,
  `net_amt_weight` decimal(15,2) DEFAULT 0.00,
  `diamond_weight` decimal(10,3) DEFAULT 0.000,
  `gemstone_weight` decimal(10,3) DEFAULT 0.000,
  `diamond_amount` decimal(15,2) DEFAULT 0.00,
  `calculation_type` varchar(100) DEFAULT NULL COMMENT 'Rate X Gross Wt, Carat X Rate, Fix, etc.',
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_sale_return_payments`
--

CREATE TABLE `tbl_sale_return_payments` (
  `id` int(11) NOT NULL,
  `return_id` int(11) NOT NULL,
  `payment_type` varchar(50) NOT NULL,
  `diamond_category` varchar(100) DEFAULT NULL,
  `transaction_no` varchar(100) DEFAULT NULL,
  `transfer_from` varchar(100) DEFAULT NULL,
  `deposit_into` varchar(100) DEFAULT NULL,
  `product` varchar(255) DEFAULT NULL,
  `cheque_date` date DEFAULT NULL,
  `weight` decimal(10,3) DEFAULT 0.000,
  `metal` varchar(100) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 0.00,
  `purity_carat` varchar(50) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_settings`
--

CREATE TABLE `tbl_settings` (
  `id` int(11) NOT NULL,
  `barcode_prefix` varchar(50) DEFAULT 'RG' COMMENT 'Prefix for generated barcodes',
  `barcode_digit_length` int(11) DEFAULT 5 COMMENT 'Digits after prefix',
  `branch_password_hash` varchar(255) DEFAULT NULL COMMENT 'bcrypt for + Branch modal',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `app_locale` varchar(32) DEFAULT 'en' COMMENT 'UI (Google / i18n code)',
  `app_ui_font_json` text DEFAULT NULL COMMENT 'Global UI font (Set Software)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_settings`
--

INSERT INTO `tbl_settings` (`id`, `barcode_prefix`, `barcode_digit_length`, `branch_password_hash`, `created_at`, `updated_at`, `app_locale`, `app_ui_font_json`) VALUES
(1, 'RG', 5, '$2y$10$hWtwE/OP5ei4WYJBabgc0emWEoVtNNRxvv0swEXikHHRKeVlsrwp.', '2026-04-16 13:55:26', '2026-04-16 13:58:56', 'en', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_shape`
--

CREATE TABLE `tbl_shape` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `name` varchar(100) NOT NULL,
  `status` tinyint(4) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_sieve_size`
--

CREATE TABLE `tbl_sieve_size` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `name` varchar(100) NOT NULL,
  `status` tinyint(4) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_size`
--

CREATE TABLE `tbl_size` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `name` varchar(100) NOT NULL,
  `description` varchar(150) DEFAULT NULL,
  `status` tinyint(4) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_states`
--

CREATE TABLE `tbl_states` (
  `id` int(11) NOT NULL,
  `country_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `comment` text DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_states`
--

INSERT INTO `tbl_states` (`id`, `country_id`, `name`, `comment`, `status`) VALUES
(1, 65, 'Abu Dhabi Emirate', NULL, 1),
(2, 65, 'Dubai Emirate', NULL, 1),
(3, 65, 'Sharjah Emirate', NULL, 1),
(4, 65, 'Ajman Emirate', NULL, 1),
(5, 65, 'Umm Al Quwain Emirate', NULL, 1),
(6, 65, 'Ras Al Khaimah Emirate', NULL, 1),
(7, 65, 'Fujairah Emirate', NULL, 1),
(8, 26, 'Maharashtra', NULL, 1),
(9, 26, 'Gujarat', NULL, 1),
(10, 26, 'Kerala', NULL, 1),
(11, 26, 'Karnataka', NULL, 1),
(12, 26, 'Tamil Nadu', NULL, 1),
(13, 26, 'Delhi', NULL, 1),
(14, 26, 'West Bengal', NULL, 1),
(15, 26, 'Rajasthan', NULL, 1),
(16, 26, 'Uttar Pradesh', NULL, 1),
(17, 26, 'Telangana', NULL, 1),
(18, 26, 'Andhra Pradesh', NULL, 1),
(19, 26, 'Punjab', NULL, 1),
(20, 26, 'Haryana', NULL, 1),
(21, 26, 'Madhya Pradesh', NULL, 1),
(22, 26, 'Bihar', NULL, 1),
(23, 26, 'Odisha', NULL, 1),
(24, 26, 'Assam', NULL, 1),
(25, 1, 'Other', NULL, 1),
(26, 2, 'Other', NULL, 1),
(27, 4, 'Other', NULL, 1),
(28, 5, 'Other', NULL, 1),
(29, 7, 'Other', NULL, 1),
(30, 6, 'Other', NULL, 1),
(31, 9, 'Other', NULL, 1),
(32, 10, 'Other', NULL, 1),
(33, 8, 'Other', NULL, 1),
(34, 11, 'Other', NULL, 1),
(35, 12, 'Other', NULL, 1),
(36, 59, 'Other', NULL, 1),
(37, 13, 'Other', NULL, 1),
(38, 16, 'Other', NULL, 1),
(39, 3, 'Other', NULL, 1),
(40, 14, 'Other', NULL, 1),
(41, 56, 'Other', NULL, 1),
(42, 15, 'Other', NULL, 1),
(43, 66, 'Other', NULL, 1),
(44, 17, 'Other', NULL, 1),
(45, 18, 'Other', NULL, 1),
(46, 19, 'Other', NULL, 1),
(47, 20, 'Other', NULL, 1),
(48, 24, 'Other', NULL, 1),
(49, 22, 'Other', NULL, 1),
(50, 23, 'Other', NULL, 1),
(51, 21, 'Other', NULL, 1),
(52, 25, 'Other', NULL, 1),
(53, 27, 'Other', NULL, 1),
(54, 30, 'Other', NULL, 1),
(55, 29, 'Other', NULL, 1),
(56, 28, 'Other', NULL, 1),
(57, 31, 'Other', NULL, 1),
(58, 33, 'Other', NULL, 1),
(59, 34, 'Other', NULL, 1),
(60, 32, 'Other', NULL, 1),
(61, 35, 'Other', NULL, 1),
(62, 55, 'Other', NULL, 1),
(63, 36, 'Other', NULL, 1),
(64, 37, 'Other', NULL, 1),
(65, 57, 'Other', NULL, 1),
(66, 38, 'Other', NULL, 1),
(67, 41, 'Other', NULL, 1),
(68, 40, 'Other', NULL, 1),
(69, 39, 'Other', NULL, 1),
(70, 45, 'Other', NULL, 1),
(71, 43, 'Other', NULL, 1),
(72, 42, 'Other', NULL, 1),
(73, 44, 'Other', NULL, 1),
(74, 46, 'Other', NULL, 1),
(75, 49, 'Other', NULL, 1),
(76, 47, 'Other', NULL, 1),
(77, 48, 'Other', NULL, 1),
(78, 50, 'Other', NULL, 1),
(79, 51, 'Other', NULL, 1),
(80, 52, 'Other', NULL, 1),
(81, 58, 'Other', NULL, 1),
(82, 53, 'Other', NULL, 1),
(83, 60, 'Other', NULL, 1),
(84, 61, 'Other', NULL, 1),
(85, 62, 'Other', NULL, 1),
(86, 63, 'Other', NULL, 1),
(87, 64, 'Other', NULL, 1),
(88, 67, 'Other', NULL, 1),
(89, 68, 'Other', NULL, 1),
(90, 54, 'Other', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_stock`
--

CREATE TABLE `tbl_stock` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_characteristic_id` int(11) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL COMMENT 'Barcode number for the stock entry',
  `branch_id` int(11) NOT NULL,
  `metal_id` int(11) DEFAULT NULL,
  `opening_weight` decimal(15,4) DEFAULT NULL,
  `opening_purity` decimal(10,4) DEFAULT NULL,
  `opening_qty` decimal(15,4) DEFAULT NULL,
  `final_weight` decimal(15,4) DEFAULT NULL,
  `rate` decimal(15,4) DEFAULT NULL,
  `value` decimal(15,4) DEFAULT NULL,
  `current_weight` decimal(15,4) DEFAULT NULL,
  `current_qty` decimal(15,4) DEFAULT NULL,
  `stock_type` varchar(50) DEFAULT 'opening',
  `transaction_date` date DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `source_stock_id` int(11) DEFAULT NULL,
  `stock_journal_id` int(11) DEFAULT NULL COMMENT 'Purchase invoice item_id (journal batch)',
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_stock`
--

INSERT INTO `tbl_stock` (`id`, `product_id`, `product_characteristic_id`, `barcode`, `branch_id`, `metal_id`, `opening_weight`, `opening_purity`, `opening_qty`, `final_weight`, `rate`, `value`, `current_weight`, `current_qty`, `stock_type`, `transaction_date`, `reference_id`, `reference_type`, `source_stock_id`, `stock_journal_id`, `status`, `created_at`, `updated_at`) VALUES
(3, 2, 3, 'SV00001', 63, 2, 0.0000, 1.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 'opening', '2026-06-16', NULL, NULL, NULL, NULL, 0, '2026-06-16 10:35:35', '2026-06-16 17:16:46'),
(4, 2, 4, 'SV00001', 63, 2, 518.5000, 1.0000, 218.0000, 518.5000, 0.0000, 0.0000, 518.5000, 218.0000, 'opening', '2026-06-16', NULL, NULL, NULL, NULL, 0, '2026-06-16 10:36:53', '2026-06-16 17:16:46'),
(5, 2, 4, 'TR0211', 63, 2, 14.9000, 1.0000, 5.0000, 14.9000, 0.0000, 745.0000, 0.0000, 0.0000, 'purchase', '2026-06-16', 1, 'stock_journal', NULL, NULL, 1, '2026-06-16 13:41:48', '2026-06-16 13:41:48'),
(6, 2, 4, 'TR0205', 63, 2, 503.6000, 1.0000, 213.0000, 503.6000, 0.0000, 25180.0000, 0.0000, 0.0000, 'purchase', '2026-06-16', 2, 'stock_journal', NULL, NULL, 1, '2026-06-16 13:41:48', '2026-06-16 13:41:48'),
(7, 2, 4, 'SV00001', 63, 2, 518.5000, 1.0000, 218.0000, 518.5000, 50.0000, 25925.0000, 518.5000, 218.0000, 'outward', '2026-06-16', 1, 'stock_journal', NULL, NULL, 1, '2026-06-16 13:41:48', NULL),
(8, 2, 5, 'SV00001', 63, 2, 10000.0000, 1.0000, 10000.0000, 10000.0000, 0.0000, 0.0000, 10000.0000, 10000.0000, 'opening', '2026-06-16', NULL, NULL, NULL, NULL, 0, '2026-06-16 17:16:36', '2026-06-16 17:16:46'),
(9, 2, 6, 'SV00001', 63, 2, 10000.0000, 1.0000, 10000.0000, 10000.0000, 0.0000, 0.0000, 10000.0000, 10000.0000, 'opening', '2026-06-16', NULL, NULL, NULL, NULL, 1, '2026-06-16 17:16:46', NULL),
(10, 2, 6, 'PD01360', 63, 2, 0.0000, 1.0000, 1.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 'purchase', '2026-06-19', 3, 'stock_journal', NULL, NULL, 1, '2026-06-19 15:38:05', '2026-06-19 15:38:05'),
(11, 2, 6, 'TR0294', 63, 2, 0.0000, 1.0000, 1.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 'purchase', '2026-06-19', 4, 'stock_journal', NULL, NULL, 1, '2026-06-19 15:38:05', '2026-06-19 15:38:05'),
(12, 2, 6, 'AVTR011', 63, 2, 0.0000, 1.0000, 1.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 'purchase', '2026-06-19', 5, 'stock_journal', NULL, NULL, 1, '2026-06-19 15:38:05', '2026-06-19 15:38:05'),
(13, 2, 6, 'SV00001', 63, 2, 0.0000, 1.0000, 3.0000, 0.0000, 0.0000, 0.0000, 0.0000, 3.0000, 'outward', '2026-06-19', 3, 'stock_journal', NULL, NULL, 1, '2026-06-19 15:38:05', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_stock_cross_transfer_log`
--

CREATE TABLE `tbl_stock_cross_transfer_log` (
  `id` bigint(20) NOT NULL,
  `source_branch_id` int(11) NOT NULL,
  `destination_branch_id` int(11) NOT NULL,
  `source_db` varchar(191) NOT NULL DEFAULT '',
  `destination_db` varchar(191) NOT NULL DEFAULT '',
  `barcode` varchar(100) DEFAULT NULL,
  `stock_id` int(11) NOT NULL COMMENT 'Source tbl_stock.id at time of transfer',
  `outward_stock_id` int(11) DEFAULT NULL COMMENT 'Source tbl_stock outward id',
  `destination_stock_id` bigint(20) DEFAULT NULL COMMENT 'New tbl_stock.id on destination DB when applicable',
  `move_qty` decimal(15,4) DEFAULT NULL,
  `move_wt` decimal(15,4) DEFAULT NULL,
  `transfer_date` date DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `status` varchar(32) NOT NULL DEFAULT 'completed'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_stock_journal`
--

CREATE TABLE `tbl_stock_journal` (
  `id` int(11) NOT NULL,
  `sj_invoice_no` varchar(50) NOT NULL COMMENT 'Stock Journal invoice number (SJ-1, SJ-2, etc.)',
  `item_id` int(11) DEFAULT NULL COMMENT 'Reference to tbl_purchase_invoice_items.id (NULL for product opening)',
  `invoice_id` int(11) DEFAULT NULL COMMENT 'Reference to tbl_purchase_invoices.id (NULL for product opening)',
  `invoice_no` varchar(50) DEFAULT NULL COMMENT 'Purchase invoice number for reference',
  `sj_date` date NOT NULL COMMENT 'Stock journal date',
  `barcode` varchar(100) DEFAULT NULL,
  `code` varchar(100) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_characteristic_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `metal_id` int(11) DEFAULT NULL,
  `metal_type` varchar(50) DEFAULT NULL COMMENT 'gold, silver, diamond, loose',
  `quantity` decimal(10,2) DEFAULT 1.00,
  `gross_weight` decimal(10,3) DEFAULT 0.000,
  `less_weight` decimal(10,3) DEFAULT 0.000,
  `net_weight` decimal(10,3) DEFAULT 0.000,
  `purity` decimal(10,2) DEFAULT 0.00,
  `purity_weight` decimal(10,3) DEFAULT 0.000,
  `pure_weight` decimal(10,3) DEFAULT 0.000,
  `final_weight` decimal(10,3) DEFAULT 0.000,
  `rate` decimal(15,2) DEFAULT 0.00,
  `amount` decimal(15,2) DEFAULT 0.00,
  `making_amount` decimal(15,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) DEFAULT 0.00,
  `net_amt_with_tax` decimal(15,2) DEFAULT 0.00,
  `group_name` varchar(255) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'active' COMMENT 'active, completed, cancelled',
  `created_by` int(11) DEFAULT NULL,
  `created_by_username` varchar(191) DEFAULT NULL COMMENT 'Login username at create',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `modified_by` int(11) DEFAULT NULL,
  `modified_by_username` varchar(191) DEFAULT NULL,
  `rfid_code` varchar(100) DEFAULT NULL,
  `voucher_type` varchar(50) DEFAULT NULL,
  `design_no` varchar(100) DEFAULT NULL,
  `huid_no` varchar(100) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `calculation` varchar(100) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `karat` decimal(10,2) DEFAULT NULL,
  `pkt_wt` decimal(10,3) DEFAULT NULL,
  `pkt_less_wt` decimal(10,3) DEFAULT NULL,
  `requested_purity` decimal(10,2) DEFAULT NULL,
  `requested` decimal(10,3) DEFAULT NULL,
  `gold_loss_1` decimal(10,3) DEFAULT NULL,
  `gold_loss_2` decimal(10,3) DEFAULT NULL,
  `setting_charge` decimal(15,2) DEFAULT NULL,
  `wastage_per` decimal(10,2) DEFAULT NULL,
  `wastage_wt` decimal(10,3) DEFAULT NULL,
  `alloy_wt` decimal(10,3) DEFAULT NULL,
  `metal_value` decimal(15,2) DEFAULT NULL,
  `metal_cost` decimal(15,2) DEFAULT NULL,
  `discount_type` varchar(50) DEFAULT NULL,
  `discount_per` decimal(10,2) DEFAULT NULL,
  `discount_amount` decimal(15,2) DEFAULT NULL,
  `discount` decimal(15,2) DEFAULT NULL,
  `making_type` varchar(50) DEFAULT NULL,
  `making_rate` decimal(10,2) DEFAULT NULL,
  `making_cost` decimal(15,2) DEFAULT NULL,
  `minimum_price` decimal(15,2) DEFAULT NULL,
  `stone_charge_type` varchar(50) DEFAULT NULL,
  `stone_weight` decimal(10,3) DEFAULT NULL,
  `stone_rate` decimal(10,2) DEFAULT NULL,
  `stone_amount` decimal(15,2) DEFAULT NULL,
  `stone_cost` decimal(15,2) DEFAULT NULL,
  `diamond_amount` decimal(15,2) DEFAULT NULL,
  `purchase_amount` decimal(15,2) DEFAULT NULL,
  `sale_amount` decimal(15,2) DEFAULT NULL,
  `other_charge_type` varchar(50) DEFAULT NULL,
  `other_weight` decimal(10,3) DEFAULT NULL,
  `other_rate` decimal(10,2) DEFAULT NULL,
  `other_info` varchar(255) DEFAULT NULL,
  `other_amount` decimal(15,2) DEFAULT NULL,
  `hallmark_amount` decimal(15,2) DEFAULT NULL,
  `hallmark_rate` decimal(10,2) DEFAULT NULL,
  `reverse` decimal(15,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_stock_journal`
--

INSERT INTO `tbl_stock_journal` (`id`, `sj_invoice_no`, `item_id`, `invoice_id`, `invoice_no`, `sj_date`, `barcode`, `code`, `product_id`, `product_characteristic_id`, `product_name`, `metal_id`, `metal_type`, `quantity`, `gross_weight`, `less_weight`, `net_weight`, `purity`, `purity_weight`, `pure_weight`, `final_weight`, `rate`, `amount`, `making_amount`, `tax_amount`, `net_amount`, `net_amt_with_tax`, `group_name`, `comment`, `status`, `created_by`, `created_by_username`, `created_at`, `updated_at`, `modified_by`, `modified_by_username`, `rfid_code`, `voucher_type`, `design_no`, `huid_no`, `category`, `calculation`, `location`, `karat`, `pkt_wt`, `pkt_less_wt`, `requested_purity`, `requested`, `gold_loss_1`, `gold_loss_2`, `setting_charge`, `wastage_per`, `wastage_wt`, `alloy_wt`, `metal_value`, `metal_cost`, `discount_type`, `discount_per`, `discount_amount`, `discount`, `making_type`, `making_rate`, `making_cost`, `minimum_price`, `stone_charge_type`, `stone_weight`, `stone_rate`, `stone_amount`, `stone_cost`, `diamond_amount`, `purchase_amount`, `sale_amount`, `other_charge_type`, `other_weight`, `other_rate`, `other_info`, `other_amount`, `hallmark_amount`, `hallmark_rate`, `reverse`) VALUES
(1, 'SJ-1-1', NULL, NULL, NULL, '2026-06-16', 'TR0211', NULL, 2, 4, 'SILVER - Silver', 2, NULL, 5.00, 14.900, 0.000, 14.900, 1.00, 14.900, 14.900, 0.000, 0.00, 745.00, 745.00, 0.00, 745.00, 745.00, NULL, NULL, 'active', 1, 'admin', '2026-06-16 13:41:48', NULL, NULL, NULL, NULL, 'product_opening', NULL, NULL, NULL, NULL, NULL, 0.00, 0.000, 0.000, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, 0.00, 'Per Gram', 50.00, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, 0.00, 745.00, 745.00, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00),
(2, 'SJ-1-2', NULL, NULL, NULL, '2026-06-16', 'TR0205', NULL, 2, 4, 'SILVER - Silver', 2, NULL, 213.00, 503.600, 0.000, 503.600, 1.00, 503.600, 503.600, 0.000, 0.00, 25180.00, 25180.00, 0.00, 25180.00, 25180.00, NULL, NULL, 'active', 1, 'admin', '2026-06-16 13:41:48', NULL, NULL, NULL, NULL, 'product_opening', NULL, NULL, NULL, NULL, NULL, 0.00, 0.000, 0.000, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, 0.00, 'Per Gram', 50.00, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, 0.00, 25180.00, 25180.00, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00),
(3, 'SJ-2-1', NULL, NULL, NULL, '2026-06-19', 'PD01360', NULL, 2, 6, 'SILVER - Silver', 2, NULL, 1.00, 0.000, 0.000, 0.000, 1.00, 0.000, 0.000, 0.000, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 'active', 1, 'admin', '2026-06-19 15:38:05', NULL, NULL, NULL, NULL, 'product_opening', NULL, NULL, NULL, NULL, NULL, 0.00, 0.000, 0.000, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, 0.00, 'Per Gram', 52.00, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, 0.00, 0.00, 0.00, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00),
(4, 'SJ-2-2', NULL, NULL, NULL, '2026-06-19', 'TR0294', NULL, 2, 6, 'SILVER - Silver', 2, NULL, 1.00, 0.000, 0.000, 0.000, 1.00, 0.000, 0.000, 0.000, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 'active', 1, 'admin', '2026-06-19 15:38:05', NULL, NULL, NULL, NULL, 'product_opening', NULL, NULL, NULL, NULL, NULL, 0.00, 0.000, 0.000, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, 0.00, 'Per Gram', 30.00, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, 0.00, 0.00, 0.00, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00),
(5, 'SJ-2-3', NULL, NULL, NULL, '2026-06-19', 'AVTR011', NULL, 2, 6, 'SILVER - Silver', 2, NULL, 1.00, 0.000, 0.000, 0.000, 1.00, 0.000, 0.000, 0.000, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 'active', 1, 'admin', '2026-06-19 15:38:05', NULL, NULL, NULL, NULL, 'product_opening', NULL, NULL, NULL, NULL, NULL, 0.00, 0.000, 0.000, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, 0.00, 'Per Gram', 25.00, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, 0.00, 0.00, 0.00, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_stock_journal_images`
--

CREATE TABLE `tbl_stock_journal_images` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL DEFAULT 0,
  `barcode_no` varchar(100) NOT NULL DEFAULT '',
  `image_path` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_stock_journal_images`
--

INSERT INTO `tbl_stock_journal_images` (`id`, `item_id`, `barcode_no`, `image_path`, `created_at`) VALUES
(1, 0, 'TR0211', 'uploads/stock_journal/20260616134148_93500ec5_temp_dwg_1781597497_1660_356030.png', '2026-06-16 13:41:48'),
(2, 0, 'TR0205', 'uploads/stock_journal/20260616134148_58009ec7_temp_dwg_1781597497_4978_d1e958.png', '2026-06-16 13:41:48'),
(3, 0, 'PD01360', 'uploads/stock_journal/20260619153805_af354038_temp_dwg_1781863649_3120_94aecf.png', '2026-06-19 15:38:05'),
(4, 0, 'TR0294', 'uploads/stock_journal/20260619153805_7ed315db_temp_dwg_1781863649_4346_89fa08.jpg', '2026-06-19 15:38:05'),
(5, 0, 'AVTR011', 'uploads/stock_journal/20260619153805_6a30a055_temp_dwg_1781863649_1860_8465b3.jpg', '2026-06-19 15:38:05');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_stock_transfer_pending`
--

CREATE TABLE `tbl_stock_transfer_pending` (
  `id` int(11) NOT NULL,
  `from_branch_id` int(11) NOT NULL,
  `to_branch_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_characteristic_id` int(11) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `metal_id` int(11) DEFAULT NULL,
  `opening_purity` decimal(15,4) DEFAULT NULL,
  `move_qty` decimal(15,4) NOT NULL,
  `move_wt` decimal(15,4) NOT NULL,
  `rate` decimal(15,4) DEFAULT NULL,
  `value` decimal(15,4) DEFAULT NULL,
  `transfer_date` date DEFAULT NULL,
  `source_stock_id` int(11) DEFAULT NULL,
  `outward_stock_id` int(11) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `received_stock_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `received_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_task_type`
--

CREATE TABLE `tbl_task_type` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `name` varchar(100) NOT NULL,
  `used_in` enum('Sales','Purchase','Both') DEFAULT 'Both',
  `status` tinyint(4) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_taxes`
--

CREATE TABLE `tbl_taxes` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `name` varchar(255) NOT NULL,
  `applicable_for` varchar(100) DEFAULT 'Product',
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_tax_master`
--

CREATE TABLE `tbl_tax_master` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `name` varchar(100) NOT NULL COMMENT 'Tax name (e.g. VAT, TAX BAH)',
  `default_value` decimal(10,2) DEFAULT 0.00 COMMENT 'Default % or value shown on product opening',
  `default_calculation_mode` varchar(100) DEFAULT 'Product Amount' COMMENT 'Default calculation mode name',
  `gst_supply_scope` varchar(32) NOT NULL DEFAULT 'local_state' COMMENT 'local_state=intra (CGST+SGST); out_of_state=inter (IGST)',
  `sort_order` int(11) DEFAULT 0,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_tax_master`
--

INSERT INTO `tbl_tax_master` (`id`, `branch_id`, `name`, `default_value`, `default_calculation_mode`, `gst_supply_scope`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES
(1, 47, 'SGST', 1.50, 'Product Amount', 'local_state', 1, 1, '2026-05-05 18:46:38', NULL),
(2, 47, 'CGST', 1.50, 'Product Amount', 'local_state', 2, 1, '2026-05-05 18:46:44', NULL),
(3, 47, 'IGST', 3.00, 'Product Amount', 'out_of_state', 3, 1, '2026-05-05 18:46:52', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_unit`
--

CREATE TABLE `tbl_unit` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `name` varchar(50) NOT NULL,
  `formal_name` varchar(100) NOT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_unit_conversion`
--

CREATE TABLE `tbl_unit_conversion` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `name` varchar(100) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `conversion_rate` decimal(10,4) NOT NULL,
  `quantity` decimal(10,4) DEFAULT 1.0000,
  `status` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_users`
--

CREATE TABLE `tbl_users` (
  `id` int(11) NOT NULL,
  `Fname` varchar(100) DEFAULT NULL,
  `Lname` varchar(100) DEFAULT NULL,
  `Username` varchar(100) DEFAULT NULL,
  `Phone` varchar(20) DEFAULT NULL,
  `EmailId` varchar(100) DEFAULT NULL,
  `Address` varchar(500) DEFAULT NULL,
  `Country` varchar(200) DEFAULT NULL,
  `Password` varchar(50) DEFAULT NULL,
  `Status` enum('1','0') NOT NULL,
  `user_role` varchar(64) DEFAULT 'Admin',
  `branch_labels` varchar(500) DEFAULT NULL,
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `user_branch_ids` varchar(500) DEFAULT NULL,
  `CreatedBy` int(11) NOT NULL,
  `CreatedDate` timestamp NULL DEFAULT current_timestamp(),
  `ModifiedBy` int(11) NOT NULL,
  `ModifiedDate` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `profile_photo` varchar(500) DEFAULT NULL COMMENT 'User avatar path under admin/',
  `menu_style` varchar(20) NOT NULL DEFAULT 'horizontal' COMMENT 'Main nav layout: horizontal|vertical'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `tbl_users`
--

INSERT INTO `tbl_users` (`id`, `Fname`, `Lname`, `Username`, `Phone`, `EmailId`, `Address`, `Country`, `Password`, `Status`, `user_role`, `branch_labels`, `two_factor_enabled`, `user_branch_ids`, `CreatedBy`, `CreatedDate`, `ModifiedBy`, `ModifiedDate`, `profile_photo`, `menu_style`) VALUES
(1, 'GemShop', '', 'admin', NULL, NULL, NULL, NULL, '12345', '1', 'Admin', 'GemShop', 0, '63', 0, '2026-06-05 17:58:23', 0, '2026-06-05 17:58:23', NULL, 'horizontal');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_user_column_preferences`
--

CREATE TABLE `tbl_user_column_preferences` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `page_name` varchar(100) NOT NULL DEFAULT 'product-opening',
  `tab_key` varchar(50) NOT NULL DEFAULT '' COMMENT 'Tab: 1=Gold, 2=Silver, 3=Platinum (metal_id)',
  `column_key` varchar(50) NOT NULL,
  `column_order` int(11) NOT NULL DEFAULT 0,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `column_width_px` smallint(5) UNSIGNED DEFAULT NULL COMMENT 'Optional width in pixels',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_user_permission_grants`
--

CREATE TABLE `tbl_user_permission_grants` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL DEFAULT 0,
  `perm_key` varchar(160) NOT NULL,
  `granted` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_voucher_diamond_stock_issue`
--

CREATE TABLE `tbl_voucher_diamond_stock_issue` (
  `id` int(11) NOT NULL,
  `voucher_kind` varchar(64) NOT NULL,
  `voucher_id` int(11) NOT NULL,
  `source_issue_id` int(11) DEFAULT NULL,
  `stock_id` int(11) NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `diamond_category` varchar(100) DEFAULT NULL,
  `weight` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `qty` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_voucher_field_visibility`
--

CREATE TABLE `tbl_voucher_field_visibility` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `voucher_type_id` int(11) NOT NULL,
  `reference_no` tinyint(1) DEFAULT 0,
  `sales_person` tinyint(1) DEFAULT 0,
  `currency` tinyint(1) DEFAULT 0,
  `against_of` tinyint(1) DEFAULT 0,
  `layaways` tinyint(1) DEFAULT 0,
  `due_date` tinyint(1) DEFAULT 0,
  `fixing_type` tinyint(1) DEFAULT 0,
  `show_billing_type` tinyint(1) NOT NULL DEFAULT 0,
  `show_metal_unfix` tinyint(1) NOT NULL DEFAULT 0,
  `show_payment_term` tinyint(1) NOT NULL DEFAULT 0,
  `show_unfix` tinyint(1) NOT NULL DEFAULT 0,
  `show_shipping_method` tinyint(1) NOT NULL DEFAULT 0,
  `show_barcode_no` tinyint(1) NOT NULL DEFAULT 0,
  `show_ounce_rate` tinyint(1) NOT NULL DEFAULT 0,
  `show_lead_source` tinyint(1) NOT NULL DEFAULT 0,
  `show_design_no` tinyint(1) NOT NULL DEFAULT 0,
  `show_product_code` tinyint(1) NOT NULL DEFAULT 0,
  `show_dmd_or_nam_unfix` tinyint(1) NOT NULL DEFAULT 0,
  `show_update_tax_dropdown` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_voucher_metal_allocations`
--

CREATE TABLE `tbl_voucher_metal_allocations` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `voucher_type_id` int(11) NOT NULL,
  `metal_id` int(11) NOT NULL,
  `discount` decimal(10,2) DEFAULT 0.00,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_voucher_payment_buttons`
--

CREATE TABLE `tbl_voucher_payment_buttons` (
  `voucher_type_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `cash` tinyint(1) NOT NULL DEFAULT 1,
  `metal_exchange` tinyint(1) NOT NULL DEFAULT 1,
  `bank` tinyint(1) NOT NULL DEFAULT 1,
  `scrap` tinyint(1) NOT NULL DEFAULT 1,
  `cheque` tinyint(1) NOT NULL DEFAULT 1,
  `add_diamond` tinyint(1) NOT NULL DEFAULT 1,
  `upi` tinyint(1) NOT NULL DEFAULT 1,
  `add_stone` tinyint(1) NOT NULL DEFAULT 1,
  `card` tinyint(1) NOT NULL DEFAULT 1,
  `add_old_jewellery` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_voucher_settings`
--

CREATE TABLE `tbl_voucher_settings` (
  `id` int(11) UNSIGNED NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `metal_wise` varchar(80) NOT NULL COMMENT 'Gold, Silver, Platinum, Diamond & Stones, Imitation Or Watches, Other Or Services',
  `minimum_amount_column` varchar(50) NOT NULL DEFAULT 'Amount',
  `reverse_calculation_result_column` varchar(50) NOT NULL DEFAULT 'MakingRate',
  `default_discount_type` varchar(50) NOT NULL DEFAULT 'On Amount',
  `default_calculation_type` varchar(50) NOT NULL DEFAULT 'Fix',
  `stock_availability_check_by` varchar(50) NOT NULL DEFAULT 'Carat',
  `wastage_wt_calculation` varchar(50) NOT NULL DEFAULT 'GoldWt' COMMENT 'GoldWt|FinalWt',
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_voucher_settings`
--

INSERT INTO `tbl_voucher_settings` (`id`, `branch_id`, `metal_wise`, `minimum_amount_column`, `reverse_calculation_result_column`, `default_discount_type`, `default_calculation_type`, `stock_availability_check_by`, `wastage_wt_calculation`, `updated_at`) VALUES
(13, 47, 'Gold', 'Amount', 'MakingRate', 'Fix', 'Carat X Rate', 'GrossWt', 'GoldWt', '2026-05-20 00:47:45'),
(14, 47, 'Silver', 'Amount', 'MakingRate', 'Fix', 'Fix', 'Carat', 'GoldWt', '2026-05-20 00:47:45'),
(15, 47, 'Platinum', 'Amount', 'MakingRate', 'Fix', 'Fix', 'Carat', 'GoldWt', '2026-05-20 00:47:45'),
(16, 47, 'Diamond & Stones', 'Amount', 'MakingRate', 'Fix', 'Fix', 'Carat', 'GoldWt', '2026-05-20 00:47:45'),
(17, 47, 'Imitation Or Watches', 'Amount', 'MakingRate', 'Fix', 'Fix', 'Carat', 'GoldWt', '2026-05-20 00:47:45'),
(18, 47, 'Other Or Services', 'Amount', 'MakingRate', 'Fix', 'Fix', 'Carat', 'GoldWt', '2026-05-20 00:47:45');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_voucher_stone_stock_issue`
--

CREATE TABLE `tbl_voucher_stone_stock_issue` (
  `id` int(11) NOT NULL,
  `voucher_kind` varchar(64) NOT NULL,
  `voucher_id` int(11) NOT NULL,
  `source_issue_id` int(11) DEFAULT NULL,
  `stock_id` int(11) NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `stone_category` varchar(100) DEFAULT NULL,
  `weight` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `qty` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_voucher_tax_allocations`
--

CREATE TABLE `tbl_voucher_tax_allocations` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `voucher_type_id` int(11) NOT NULL,
  `tax_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_voucher_types`
--

CREATE TABLE `tbl_voucher_types` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `method_of_numbering` varchar(255) DEFAULT NULL,
  `type_of_voucher` varchar(255) DEFAULT NULL,
  `calculate_amount_by` varchar(100) DEFAULT 'Rate X Gross Wt',
  `calculate_wastage_by` varchar(100) DEFAULT 'Net Wt',
  `fixing_type` varchar(50) DEFAULT 'Standard',
  `calculate_loss_by` varchar(100) DEFAULT 'Net Wt',
  `billing_type` varchar(50) NOT NULL DEFAULT 'standard',
  `do_not_apply_on_stock` tinyint(1) DEFAULT 0,
  `sales_persons_mandatory` tinyint(1) DEFAULT 0,
  `create_auto_journal_voucher` tinyint(1) DEFAULT 0,
  `metal_unfix` tinyint(1) DEFAULT 0,
  `internal_unfix` tinyint(1) DEFAULT 0,
  `do_not_allow_0_amount` tinyint(1) DEFAULT 0,
  `payment_mandatory` tinyint(1) DEFAULT 0,
  `calculate_markup_on_sale` tinyint(1) DEFAULT 0,
  `enable_item_fast_fields` tinyint(1) NOT NULL DEFAULT 0,
  `status` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_voucher_types`
--

INSERT INTO `tbl_voucher_types` (`id`, `name`, `method_of_numbering`, `type_of_voucher`, `calculate_amount_by`, `calculate_wastage_by`, `fixing_type`, `calculate_loss_by`, `billing_type`, `do_not_apply_on_stock`, `sales_persons_mandatory`, `create_auto_journal_voucher`, `metal_unfix`, `internal_unfix`, `do_not_allow_0_amount`, `payment_mandatory`, `calculate_markup_on_sale`, `enable_item_fast_fields`, `status`, `created_by`, `modified_by`, `created_at`, `updated_at`) VALUES
(1, 'Advance Payment', '1', 'Advance Payment', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 1, 1, 0, 1, 1, 0, 0, 0, 0, 1, NULL, 0, '2025-12-29 17:34:11', '2026-04-24 00:55:06'),
(2, 'Appraisal', '2', 'Appraisal', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, 1, '2025-12-29 17:34:11', '2026-01-04 22:08:43'),
(3, 'Assign Inventory', NULL, 'Assign Inventory', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2025-12-29 17:34:11', NULL),
(4, 'Bill Of Material', NULL, 'Bill Of Material', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2025-12-29 17:34:11', NULL),
(5, 'Broken Entry', NULL, 'Broken Entry', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2025-12-29 17:34:11', NULL),
(6, 'Expense Invoice', NULL, 'Expense Invoice', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2025-12-29 17:34:11', NULL),
(7, 'Fund Transfer', NULL, 'Fund Transfer', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2025-12-29 17:34:11', NULL),
(8, 'Fund Withdraw', NULL, 'Fund Withdraw', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2025-12-29 17:34:11', NULL),
(9, 'Income Invoice', NULL, 'Income Invoice', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2025-12-29 17:34:11', NULL),
(10, 'Investment Fund', NULL, 'Investment Fund', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2025-12-29 17:34:11', NULL),
(11, 'Jewelry Catalogue', NULL, 'Jewelry Catalogue', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2025-12-29 17:34:11', NULL),
(12, 'Jobwork Invoice', NULL, 'Jobwork Invoice', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2025-12-29 17:34:11', NULL),
(13, 'Jobwork Order', NULL, 'Jobwork Order', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2025-12-29 17:34:11', NULL),
(14, 'Jobwork Queue', NULL, 'Jobwork Queue', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2025-12-29 17:34:11', NULL),
(15, 'JobworkQueue Master', NULL, 'JobworkQueue Master', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2025-12-29 17:34:11', NULL),
(16, 'Journal Voucher', NULL, 'Journal Voucher', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2025-12-29 17:34:11', NULL),
(17, 'Loan', NULL, 'Loan', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2025-12-29 17:34:11', NULL),
(18, 'Loan Release', NULL, 'Loan Release', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2025-12-29 17:34:11', NULL),
(19, 'Material In', NULL, 'Material In', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2025-12-29 17:34:11', NULL),
(20, 'Purchase Fixing', NULL, 'Purchase Fixing', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2025-12-29 17:34:11', NULL),
(21, 'Purchase Fixing Direct Invoice', NULL, 'Purchase Fixing Direct Invoice', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2025-12-29 17:34:11', NULL),
(22, 'Purchase Invoice', NULL, 'Purchase Invoice', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2025-12-29 17:34:11', NULL),
(23, 'Purchase Order', NULL, 'Purchase Order', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2025-12-29 17:34:11', NULL),
(24, 'Purchase Quotation', NULL, 'Purchase Quotation', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2025-12-29 17:34:11', NULL),
(25, 'Purchase Return', NULL, 'Purchase Return', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2025-12-29 17:34:11', NULL),
(26, 'Receipt Voucher', NULL, 'Receipt Voucher', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2025-12-29 17:34:11', NULL),
(27, 'Rejection In', NULL, 'Rejection In', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2025-12-29 17:34:11', NULL),
(28, 'Rejection Out', NULL, 'Rejection Out', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2025-12-29 17:34:11', NULL),
(29, 'Repair Invoice', NULL, 'Repair Invoice', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2025-12-29 17:34:11', NULL),
(30, 'Repair Order', NULL, 'Repair Order', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2025-12-29 17:34:11', NULL),
(31, 'Sale Fixing', NULL, 'Sale Fixing', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2025-12-29 17:34:11', NULL),
(32, 'Sale Fixing Direct Invoice', NULL, 'Sale Fixing Direct Invoice', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2025-12-29 17:34:11', NULL),
(33, 'Sales Invoice', '1', 'Sales Invoice', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, 1, '2025-12-29 17:34:11', '2026-04-15 18:05:38'),
(34, 'Sales Order', '3', 'Sales Order', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, 1, '2025-12-29 17:34:11', '2026-01-06 16:17:46'),
(35, 'Sales Quotation', NULL, 'Sales Quotation', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2025-12-29 17:34:11', NULL),
(36, 'Sales Return', NULL, 'Sales Return', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2025-12-29 17:34:11', NULL),
(37, 'Service Voucher', NULL, 'Service Voucher', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2025-12-29 17:34:11', NULL),
(38, 'Stock Journal', NULL, 'Stock Journal', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2025-12-29 17:34:11', NULL),
(39, 'Stock Transfer In', NULL, 'Stock Transfer In', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2025-12-29 17:34:11', NULL),
(40, 'Stock Transfer Out', NULL, 'Stock Transfer Out', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2025-12-29 17:34:11', NULL),
(41, 'UnAssign Inventory', NULL, 'UnAssign Inventory', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2025-12-29 17:34:11', NULL),
(42, 'Old Jewellery Scrap Invoice', '1', 'Old Jewellery Scrap Invoice', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2026-04-14 13:52:30', NULL),
(43, 'Catalogue Quotation', NULL, 'Catalogue Quotation', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2026-04-15 17:29:07', NULL),
(44, 'Consignment In', NULL, 'Consignment In', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2026-04-15 17:29:07', NULL),
(45, 'Consignment Out', NULL, 'Consignment Out', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2026-04-15 17:29:07', NULL),
(46, 'Contra Voucher', NULL, 'Contra Voucher', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2026-04-15 17:29:07', NULL),
(47, 'Credit Note', NULL, 'Credit Note', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2026-04-15 17:29:07', NULL),
(48, 'Customer Advance', NULL, 'Customer Advance', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2026-04-15 17:29:07', NULL),
(49, 'Daily Salary Voucher', NULL, 'Daily Salary Voucher', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2026-04-15 17:29:07', NULL),
(50, 'Debit Note', NULL, 'Debit Note', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2026-04-15 17:29:07', NULL),
(51, 'Delivery Note', NULL, 'Delivery Note', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2026-04-15 17:29:07', NULL),
(52, 'Material Issue', NULL, 'Material Issue', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2026-04-15 17:29:07', NULL),
(53, 'Material Out', NULL, 'Material Out', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2026-04-15 17:29:07', NULL),
(54, 'Material Receipt', NULL, 'Material Receipt', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2026-04-15 17:29:07', NULL),
(55, 'Material Receive', NULL, 'Material Receive', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2026-04-15 17:29:07', NULL),
(56, 'Monthly Salary Voucher', NULL, 'Monthly Salary Voucher', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2026-04-15 17:29:07', NULL),
(57, 'Old Jewelry - Scrap Invoice', NULL, 'Old Jewelry - Scrap Invoice', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2026-04-15 17:29:07', NULL),
(58, 'Opening Balance', NULL, 'Opening Balance', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2026-04-15 17:29:07', NULL),
(59, 'Opening Stock', NULL, 'Opening Stock', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2026-04-15 17:29:07', NULL),
(60, 'Payment Voucher', NULL, 'Payment Voucher', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2026-04-15 17:29:08', NULL),
(61, 'PDC Clearance', NULL, 'PDC Clearance', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2026-04-15 17:29:08', NULL),
(62, 'PDC Payable', NULL, 'PDC Payable', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2026-04-15 17:29:08', NULL),
(63, 'PDC Receivable', NULL, 'PDC Receivable', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2026-04-15 17:29:08', NULL),
(64, 'Physical Stock', NULL, 'Physical Stock', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2026-04-15 17:29:08', NULL),
(65, 'POS', NULL, 'POS', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2026-04-15 17:29:08', NULL),
(66, 'Task / Event', NULL, 'Task / Event', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2026-04-15 17:29:08', NULL),
(67, 'Sale Receipt Voucher', NULL, 'Sale Receipt Voucher', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2026-05-14 14:27:08', NULL),
(68, 'Purchase Payment Voucher', NULL, 'Purchase Payment Voucher', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 'standard', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, '2026-05-14 14:27:08', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `invoice_fixing_mapping`
--
ALTER TABLE `invoice_fixing_mapping`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_source` (`source_type`,`source_transaction_id`),
  ADD KEY `idx_against` (`against_invoice_type`,`against_invoice_id`);

--
-- Indexes for table `tbl_accounting_calculation_settings`
--
ALTER TABLE `tbl_accounting_calculation_settings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mode_id` (`mode_id`);

--
-- Indexes for table `tbl_accounting_financial_years`
--
ALTER TABLE `tbl_accounting_financial_years`
  ADD PRIMARY KEY (`id`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_fy_range` (`start_date`,`end_date`),
  ADD KEY `idx_fy_status_active` (`status`,`is_active`);

--
-- Indexes for table `tbl_accounting_master_modes`
--
ALTER TABLE `tbl_accounting_master_modes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `status` (`status`),
  ADD KEY `sort_order` (`sort_order`);

--
-- Indexes for table `tbl_advance_payments`
--
ALTER TABLE `tbl_advance_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `voucher_no` (`voucher_no`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `voucher_date` (`voucher_date`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `tbl_advance_payment_items`
--
ALTER TABLE `tbl_advance_payment_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `voucher_id` (`voucher_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `metal_id` (`metal_id`);

--
-- Indexes for table `tbl_article`
--
ALTER TABLE `tbl_article`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_auragold_i18n_cache`
--
ALTER TABLE `tbl_auragold_i18n_cache`
  ADD PRIMARY KEY (`locale`,`msg_key`);

--
-- Indexes for table `tbl_auragold_mail_settings`
--
ALTER TABLE `tbl_auragold_mail_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_auragold_mobile_menu_settings`
--
ALTER TABLE `tbl_auragold_mobile_menu_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_auragold_notifications`
--
ALTER TABLE `tbl_auragold_notifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_dedupe_key` (`dedupe_key`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_unread` (`read_at`,`created_at` DESC),
  ADD KEY `idx_notifications_unread` (`read_at`,`created_at`);

--
-- Indexes for table `tbl_auragold_referral_settings`
--
ALTER TABLE `tbl_auragold_referral_settings`
  ADD PRIMARY KEY (`branch_id`);

--
-- Indexes for table `tbl_auragold_reward_coupons`
--
ALTER TABLE `tbl_auragold_reward_coupons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_coupon_branch` (`branch_id`),
  ADD KEY `idx_coupon_branch_code` (`branch_id`,`coupon_code`);

--
-- Indexes for table `tbl_auragold_reward_point_settings`
--
ALTER TABLE `tbl_auragold_reward_point_settings`
  ADD PRIMARY KEY (`branch_id`);

--
-- Indexes for table `tbl_barcode_settings`
--
ALTER TABLE `tbl_barcode_settings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_updated` (`updated_at`),
  ADD KEY `idx_barcode_settings_branch` (`branch_id`);

--
-- Indexes for table `tbl_bill_series`
--
ALTER TABLE `tbl_bill_series`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_voucher_type_id` (`voucher_type_id`),
  ADD KEY `idx_branch_id` (`branch_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `tbl_branches`
--
ALTER TABLE `tbl_branches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_break_type`
--
ALTER TABLE `tbl_break_type`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_calculation_modes`
--
ALTER TABLE `tbl_calculation_modes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `tbl_campaign_group`
--
ALTER TABLE `tbl_campaign_group`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_carat`
--
ALTER TABLE `tbl_carat`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_carat_status` (`status`),
  ADD KEY `idx_branch_id` (`branch_id`),
  ADD KEY `idx_carat_metal` (`metal_id`);

--
-- Indexes for table `tbl_cash_denomination`
--
ALTER TABLE `tbl_cash_denomination`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_categories`
--
ALTER TABLE `tbl_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_cities`
--
ALTER TABLE `tbl_cities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_city_state` (`state_id`);

--
-- Indexes for table `tbl_clarity`
--
ALTER TABLE `tbl_clarity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_collection`
--
ALTER TABLE `tbl_collection`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_collection_status` (`status`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_color`
--
ALTER TABLE `tbl_color`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_consignment_in`
--
ALTER TABLE `tbl_consignment_in`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `consignment_no` (`consignment_no`),
  ADD KEY `consignment_out_id` (`consignment_out_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `customer_name` (`customer_name`),
  ADD KEY `consignment_date` (`consignment_date`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `tbl_consignment_in_items`
--
ALTER TABLE `tbl_consignment_in_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `consignment_id` (`consignment_id`),
  ADD KEY `consignment_out_item_id` (`consignment_out_item_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `barcode` (`barcode`),
  ADD KEY `product_characteristic_id` (`product_characteristic_id`);

--
-- Indexes for table `tbl_consignment_out`
--
ALTER TABLE `tbl_consignment_out`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `consignment_no` (`consignment_no`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `customer_name` (`customer_name`),
  ADD KEY `consignment_date` (`consignment_date`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `tbl_consignment_out_items`
--
ALTER TABLE `tbl_consignment_out_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `consignment_id` (`consignment_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `barcode` (`barcode`),
  ADD KEY `product_characteristic_id` (`product_characteristic_id`);

--
-- Indexes for table `tbl_contra_vouchers`
--
ALTER TABLE `tbl_contra_vouchers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `voucher_no` (`voucher_no`),
  ADD KEY `voucher_date` (`voucher_date`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `tbl_contra_voucher_items`
--
ALTER TABLE `tbl_contra_voucher_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `voucher_id` (`voucher_id`);

--
-- Indexes for table `tbl_counter`
--
ALTER TABLE `tbl_counter`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_countries`
--
ALTER TABLE `tbl_countries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `code` (`code`);

--
-- Indexes for table `tbl_credit_card`
--
ALTER TABLE `tbl_credit_card`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_credit_card_branch` (`branch_id`),
  ADD KEY `idx_credit_card_status` (`status`);

--
-- Indexes for table `tbl_credit_notes`
--
ALTER TABLE `tbl_credit_notes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `credit_note_no` (`credit_note_no`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `credit_note_date` (`credit_note_date`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `tbl_credit_note_items`
--
ALTER TABLE `tbl_credit_note_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `credit_note_id` (`credit_note_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `product_characteristic_id` (`product_characteristic_id`),
  ADD KEY `barcode` (`barcode`);

--
-- Indexes for table `tbl_credit_note_payments`
--
ALTER TABLE `tbl_credit_note_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `credit_note_id` (`credit_note_id`);

--
-- Indexes for table `tbl_crm_contact_groups`
--
ALTER TABLE `tbl_crm_contact_groups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_crm_cg_created` (`created_at`);

--
-- Indexes for table `tbl_crm_contact_group_members`
--
ALTER TABLE `tbl_crm_contact_group_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_crm_cg_member` (`group_id`,`customer_id`),
  ADD KEY `idx_crm_cg_gid` (`group_id`),
  ADD KEY `idx_crm_cg_cust` (`customer_id`);

--
-- Indexes for table `tbl_crm_whatsapp_campaigns`
--
ALTER TABLE `tbl_crm_whatsapp_campaigns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_crm_wa_branch` (`branch_id`),
  ADD KEY `idx_crm_wa_created` (`created_at`);

--
-- Indexes for table `tbl_crm_whatsapp_campaign_images`
--
ALTER TABLE `tbl_crm_whatsapp_campaign_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_crm_wa_img_campaign` (`campaign_id`);

--
-- Indexes for table `tbl_currency`
--
ALTER TABLE `tbl_currency`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_currency_exchange_rate`
--
ALTER TABLE `tbl_currency_exchange_rate`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_customers`
--
ALTER TABLE `tbl_customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `name` (`name`),
  ADD KEY `mobile_no` (`mobile_no`),
  ADD KEY `mail_id` (`mail_id`),
  ADD KEY `group_id` (`group_id`),
  ADD KEY `customer_type_id` (`customer_type_id`),
  ADD KEY `nationality_id` (`nationality_id`),
  ADD KEY `country_id` (`country_id`);

--
-- Indexes for table `tbl_customer_advance_policy`
--
ALTER TABLE `tbl_customer_advance_policy`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_customer_advance_vouchers`
--
ALTER TABLE `tbl_customer_advance_vouchers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `voucher_no` (`voucher_no`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `voucher_date` (`voucher_date`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `tbl_customer_advance_voucher_items`
--
ALTER TABLE `tbl_customer_advance_voucher_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `voucher_id` (`voucher_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `metal_id` (`metal_id`);

--
-- Indexes for table `tbl_customer_balance`
--
ALTER TABLE `tbl_customer_balance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `customer_id` (`customer_id`),
  ADD KEY `balance_amount` (`balance_amount`);

--
-- Indexes for table `tbl_customer_ledger`
--
ALTER TABLE `tbl_customer_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `transaction_type` (`transaction_type`),
  ADD KEY `transaction_id` (`transaction_id`),
  ADD KEY `transaction_date` (`transaction_date`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_against_invoice_no` (`against_invoice_no`);

--
-- Indexes for table `tbl_customer_types`
--
ALTER TABLE `tbl_customer_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_cut`
--
ALTER TABLE `tbl_cut`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_dashboard_metal_meta`
--
ALTER TABLE `tbl_dashboard_metal_meta`
  ADD PRIMARY KEY (`metal`,`branch_id`);

--
-- Indexes for table `tbl_dashboard_metal_rates`
--
ALTER TABLE `tbl_dashboard_metal_rates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_branch_metal_carat` (`branch_id`,`metal`,`carat_label`),
  ADD KEY `idx_metal_sort` (`metal`,`sort_order`);

--
-- Indexes for table `tbl_dashboard_metal_rate_history`
--
ALTER TABLE `tbl_dashboard_metal_rate_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_metal_time` (`metal`,`recorded_at`),
  ADD KEY `idx_metal_carat_time` (`metal`,`carat_label`,`recorded_at`),
  ADD KEY `idx_branch_metal_time` (`branch_id`,`metal`,`recorded_at`);

--
-- Indexes for table `tbl_day_reports`
--
ALTER TABLE `tbl_day_reports`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `report_date` (`report_date`),
  ADD KEY `idx_report_date` (`report_date`);

--
-- Indexes for table `tbl_debit_notes`
--
ALTER TABLE `tbl_debit_notes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `debit_note_no` (`debit_note_no`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `debit_note_date` (`debit_note_date`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `tbl_debit_note_items`
--
ALTER TABLE `tbl_debit_note_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `debit_note_id` (`debit_note_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `product_characteristic_id` (`product_characteristic_id`),
  ADD KEY `barcode` (`barcode`);

--
-- Indexes for table `tbl_debit_note_payments`
--
ALTER TABLE `tbl_debit_note_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `debit_note_id` (`debit_note_id`);

--
-- Indexes for table `tbl_departments`
--
ALTER TABLE `tbl_departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_department_short_code` (`short_code`),
  ADD KEY `idx_department_status` (`status`);

--
-- Indexes for table `tbl_department_users`
--
ALTER TABLE `tbl_department_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_department_user_name` (`user_name`),
  ADD KEY `idx_department_user_status` (`status`);

--
-- Indexes for table `tbl_department_user_map`
--
ALTER TABLE `tbl_department_user_map`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_department_user_map` (`department_id`,`user_id`),
  ADD KEY `idx_department_map_department` (`department_id`),
  ADD KEY `idx_department_map_user` (`user_id`),
  ADD KEY `idx_department_map_status` (`status`);

--
-- Indexes for table `tbl_document_type`
--
ALTER TABLE `tbl_document_type`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_document_types`
--
ALTER TABLE `tbl_document_types`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_ewaybill_api_logs`
--
ALTER TABLE `tbl_ewaybill_api_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_ewaybill_api_settings`
--
ALTER TABLE `tbl_ewaybill_api_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_eway_setting_key` (`setting_key`);

--
-- Indexes for table `tbl_ewaybill_api_tokens`
--
ALTER TABLE `tbl_ewaybill_api_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_eway_gstin_username` (`gstin`,`username`);

--
-- Indexes for table `tbl_ewaybill_generate_logs`
--
ALTER TABLE `tbl_ewaybill_generate_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_expenses`
--
ALTER TABLE `tbl_expenses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `expense_no` (`expense_no`),
  ADD KEY `ledger_id` (`ledger_id`),
  ADD KEY `expense_date` (`expense_date`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `tbl_expense_categories`
--
ALTER TABLE `tbl_expense_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `name` (`name`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `tbl_expense_items`
--
ALTER TABLE `tbl_expense_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `expense_id` (`expense_id`);

--
-- Indexes for table `tbl_expense_payments`
--
ALTER TABLE `tbl_expense_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `expense_id` (`expense_id`);

--
-- Indexes for table `tbl_extra_fields`
--
ALTER TABLE `tbl_extra_fields`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_extra_fields_branch_metal` (`branch_id`,`metal_type`),
  ADD KEY `idx_extra_fields_metal_status` (`metal_type`,`status`);

--
-- Indexes for table `tbl_gst_calculation_snapshot`
--
ALTER TABLE `tbl_gst_calculation_snapshot`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_investment_schemes`
--
ALTER TABLE `tbl_investment_schemes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_active` (`active`),
  ADD KEY `idx_scheme_name` (`scheme_name`(100));

--
-- Indexes for table `tbl_invoice_print_settings`
--
ALTER TABLE `tbl_invoice_print_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_branch_setting_type_key` (`branch_id`,`setting_type`,`setting_key`),
  ADD KEY `idx_updated` (`updated_at`),
  ADD KEY `idx_setting_type` (`setting_type`),
  ADD KEY `idx_invoice_print_branch` (`branch_id`);

--
-- Indexes for table `tbl_ip_access_settings`
--
ALTER TABLE `tbl_ip_access_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_ip_whitelist`
--
ALTER TABLE `tbl_ip_whitelist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_whitelist_entity_type` (`entity_value`(191),`entry_type`);

--
-- Indexes for table `tbl_jewelry_catalogue`
--
ALTER TABLE `tbl_jewelry_catalogue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_jcat_barcode` (`barcode`),
  ADD KEY `idx_jcat_sale_item` (`sale_order_item_id`),
  ADD KEY `idx_jcat_repair_item` (`repair_order_item_id`);

--
-- Indexes for table `tbl_jobwork_invoices`
--
ALTER TABLE `tbl_jobwork_invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_repair_jwo` (`repair_jobwork_order_id`),
  ADD UNIQUE KEY `uniq_jwo_id` (`jobwork_order_id`),
  ADD KEY `invoice_no` (`invoice_no`);

--
-- Indexes for table `tbl_jobwork_orders`
--
ALTER TABLE `tbl_jobwork_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_order_id` (`sale_order_id`),
  ADD KEY `jobwork_no` (`jobwork_no`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `tbl_jobwork_order_comments`
--
ALTER TABLE `tbl_jobwork_order_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobwork_order_id` (`jobwork_order_id`);

--
-- Indexes for table `tbl_jobwork_order_items`
--
ALTER TABLE `tbl_jobwork_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobwork_order_id` (`jobwork_order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `tbl_jobwork_queue_activity`
--
ALTER TABLE `tbl_jobwork_queue_activity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobwork_order_id` (`jobwork_order_id`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `tbl_jobwork_queue_diamond_stock`
--
ALTER TABLE `tbl_jobwork_queue_diamond_stock`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_jwq_diamond_stock` (`jobwork_order_id`,`stock_id`),
  ADD KEY `idx_jwo` (`jobwork_order_id`),
  ADD KEY `idx_item` (`jobwork_order_item_id`);

--
-- Indexes for table `tbl_jobwork_queue_diamond_stock_issue`
--
ALTER TABLE `tbl_jobwork_queue_diamond_stock_issue`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_jwq_stock` (`jobwork_order_id`,`stock_id`),
  ADD KEY `idx_jwo` (`jobwork_order_id`),
  ADD KEY `idx_item` (`jobwork_order_item_id`),
  ADD KEY `idx_stock` (`stock_id`),
  ADD KEY `idx_jwo_item_stock` (`jobwork_order_id`,`jobwork_order_item_id`,`stock_id`);

--
-- Indexes for table `tbl_jobwork_weight_adjustments`
--
ALTER TABLE `tbl_jobwork_weight_adjustments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobwork_order_id` (`jobwork_order_id`),
  ADD KEY `adjustment_type` (`adjustment_type`);

--
-- Indexes for table `tbl_job_work_orders`
--
ALTER TABLE `tbl_job_work_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `job_work_no` (`job_work_no`),
  ADD KEY `sale_order_id` (`sale_order_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `tbl_journal_vouchers`
--
ALTER TABLE `tbl_journal_vouchers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `voucher_no` (`voucher_no`),
  ADD KEY `voucher_date` (`voucher_date`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `tbl_journal_voucher_items`
--
ALTER TABLE `tbl_journal_voucher_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `voucher_id` (`voucher_id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `tbl_loan_product_type`
--
ALTER TABLE `tbl_loan_product_type`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_loan_reason`
--
ALTER TABLE `tbl_loan_reason`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_location`
--
ALTER TABLE `tbl_location`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_location_status` (`status`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_login_blocklist`
--
ALTER TABLE `tbl_login_blocklist`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip` (`ip_address`),
  ADD KEY `idx_blocked_until` (`blocked_until`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `tbl_manufacturing_closing`
--
ALTER TABLE `tbl_manufacturing_closing`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_dept_user` (`department_id`,`department_user_id`),
  ADD KEY `idx_closing_date` (`closing_date`);

--
-- Indexes for table `tbl_material_issues`
--
ALTER TABLE `tbl_material_issues`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_order_id` (`sale_order_id`),
  ADD KEY `material_issue_no` (`material_issue_no`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_material_issue_items`
--
ALTER TABLE `tbl_material_issue_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `material_issue_id` (`material_issue_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `tbl_material_receives`
--
ALTER TABLE `tbl_material_receives`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_order_id` (`sale_order_id`),
  ADD KEY `material_receive_no` (`material_receive_no`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_material_receive_items`
--
ALTER TABLE `tbl_material_receive_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `material_receive_id` (`material_receive_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `tbl_metal`
--
ALTER TABLE `tbl_metal`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_metal_amount_conversions`
--
ALTER TABLE `tbl_metal_amount_conversions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `direction` (`direction`),
  ADD KEY `trans_date` (`trans_date`);

--
-- Indexes for table `tbl_nationalities`
--
ALTER TABLE `tbl_nationalities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `tbl_old_jewelry_scrap_invoices`
--
ALTER TABLE `tbl_old_jewelry_scrap_invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_no` (`invoice_no`),
  ADD KEY `invoice_date` (`invoice_date`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `tbl_old_jewelry_scrap_invoice_items`
--
ALTER TABLE `tbl_old_jewelry_scrap_invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`);

--
-- Indexes for table `tbl_old_jewelry_scrap_invoice_payments`
--
ALTER TABLE `tbl_old_jewelry_scrap_invoice_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`);

--
-- Indexes for table `tbl_old_jewelry_stock`
--
ALTER TABLE `tbl_old_jewelry_stock`
  ADD PRIMARY KEY (`id`),
  ADD KEY `source_invoice_id` (`source_invoice_id`),
  ADD KEY `source_item_id` (`source_item_id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `tbl_packet_type`
--
ALTER TABLE `tbl_packet_type`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_payment_vouchers`
--
ALTER TABLE `tbl_payment_vouchers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `voucher_no` (`voucher_no`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `voucher_date` (`voucher_date`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_payment_voucher_items`
--
ALTER TABLE `tbl_payment_voucher_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `voucher_id` (`voucher_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `metal_id` (`metal_id`);

--
-- Indexes for table `tbl_pos_sale_invoices`
--
ALTER TABLE `tbl_pos_sale_invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_no` (`invoice_no`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `invoice_date` (`invoice_date`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `tbl_pos_sale_invoice_items`
--
ALTER TABLE `tbl_pos_sale_invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `product_characteristic_id` (`product_characteristic_id`),
  ADD KEY `barcode` (`barcode`);

--
-- Indexes for table `tbl_pos_sale_invoice_payments`
--
ALTER TABLE `tbl_pos_sale_invoice_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`);

--
-- Indexes for table `tbl_products`
--
ALTER TABLE `tbl_products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `tbl_product_branches`
--
ALTER TABLE `tbl_product_branches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_branch` (`product_id`,`branch_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `tbl_product_branch_settings`
--
ALTER TABLE `tbl_product_branch_settings`
  ADD PRIMARY KEY (`product_id`,`branch_id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `tbl_product_characteristics`
--
ALTER TABLE `tbl_product_characteristics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `metal_id` (`metal_id`),
  ADD KEY `idx_unit_id` (`unit_id`),
  ADD KEY `idx_location_id` (`location_id`);

--
-- Indexes for table `tbl_product_tax`
--
ALTER TABLE `tbl_product_tax`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `product_branch_tax` (`product_id`,`branch_id`);

--
-- Indexes for table `tbl_purchase_fixing_direct`
--
ALTER TABLE `tbl_purchase_fixing_direct`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_no` (`invoice_no`),
  ADD KEY `ref_no` (`ref_no`),
  ADD KEY `invoice_date` (`invoice_date`),
  ADD KEY `fixing_date` (`fixing_date`),
  ADD KEY `idx_sale_invoice_no` (`sale_invoice_no`),
  ADD KEY `idx_against_of` (`against_of`(64));

--
-- Indexes for table `tbl_purchase_fixing_direct_items`
--
ALTER TABLE `tbl_purchase_fixing_direct_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fixing_id` (`fixing_id`),
  ADD KEY `idx_metal_id` (`metal_id`);

--
-- Indexes for table `tbl_purchase_invoices`
--
ALTER TABLE `tbl_purchase_invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_no` (`invoice_no`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `invoice_date` (`invoice_date`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_branch_id` (`branch_id`),
  ADD KEY `idx_purchase_invoices_due_date` (`due_date`);

--
-- Indexes for table `tbl_purchase_invoice_items`
--
ALTER TABLE `tbl_purchase_invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `product_characteristic_id` (`product_characteristic_id`);

--
-- Indexes for table `tbl_purchase_invoice_payments`
--
ALTER TABLE `tbl_purchase_invoice_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`);

--
-- Indexes for table `tbl_purchase_orders`
--
ALTER TABLE `tbl_purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_no` (`order_no`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `order_date` (`order_date`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `tbl_purchase_order_items`
--
ALTER TABLE `tbl_purchase_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `tbl_purchase_order_payments`
--
ALTER TABLE `tbl_purchase_order_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `tbl_purchase_quotations`
--
ALTER TABLE `tbl_purchase_quotations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `quotation_no` (`quotation_no`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `quotation_date` (`quotation_date`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_purchase_quotation_items`
--
ALTER TABLE `tbl_purchase_quotation_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quotation_id` (`quotation_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `product_characteristic_id` (`product_characteristic_id`),
  ADD KEY `barcode` (`barcode`);

--
-- Indexes for table `tbl_purchase_quotation_payments`
--
ALTER TABLE `tbl_purchase_quotation_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quotation_id` (`quotation_id`);

--
-- Indexes for table `tbl_purchase_returns`
--
ALTER TABLE `tbl_purchase_returns`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `return_no` (`return_no`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `return_date` (`return_date`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_purchase_return_items`
--
ALTER TABLE `tbl_purchase_return_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `return_id` (`return_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `product_characteristic_id` (`product_characteristic_id`),
  ADD KEY `barcode` (`barcode`);

--
-- Indexes for table `tbl_purchase_return_payments`
--
ALTER TABLE `tbl_purchase_return_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `return_id` (`return_id`);

--
-- Indexes for table `tbl_receipt_vouchers`
--
ALTER TABLE `tbl_receipt_vouchers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `voucher_no` (`voucher_no`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `voucher_date` (`voucher_date`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `tbl_receipt_voucher_items`
--
ALTER TABLE `tbl_receipt_voucher_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `voucher_id` (`voucher_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `metal_id` (`metal_id`);

--
-- Indexes for table `tbl_remark`
--
ALTER TABLE `tbl_remark`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_repair_invoices`
--
ALTER TABLE `tbl_repair_invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `repair_invoice_no` (`repair_invoice_no`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `repair_invoice_date` (`repair_invoice_date`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `tbl_repair_invoice_items`
--
ALTER TABLE `tbl_repair_invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `repair_invoice_id` (`repair_invoice_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `product_characteristic_id` (`product_characteristic_id`),
  ADD KEY `barcode` (`barcode`);

--
-- Indexes for table `tbl_repair_invoice_payments`
--
ALTER TABLE `tbl_repair_invoice_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `repair_invoice_id` (`repair_invoice_id`);

--
-- Indexes for table `tbl_repair_jobwork_orders`
--
ALTER TABLE `tbl_repair_jobwork_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `repair_order_id` (`repair_order_id`),
  ADD KEY `jobwork_no` (`jobwork_no`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `tbl_repair_jobwork_order_items`
--
ALTER TABLE `tbl_repair_jobwork_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `repair_jobwork_order_id` (`repair_jobwork_order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `tbl_repair_material_issues`
--
ALTER TABLE `tbl_repair_material_issues`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `repair_order_id` (`repair_order_id`),
  ADD KEY `material_issue_no` (`material_issue_no`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `tbl_repair_material_issue_items`
--
ALTER TABLE `tbl_repair_material_issue_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `repair_material_issue_id` (`repair_material_issue_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `tbl_repair_material_receives`
--
ALTER TABLE `tbl_repair_material_receives`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `repair_order_id` (`repair_order_id`),
  ADD KEY `material_receive_no` (`material_receive_no`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `tbl_repair_material_receive_items`
--
ALTER TABLE `tbl_repair_material_receive_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `repair_material_receive_id` (`repair_material_receive_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `tbl_repair_orders`
--
ALTER TABLE `tbl_repair_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_no` (`order_no`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `order_date` (`order_date`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `tbl_repair_order_items`
--
ALTER TABLE `tbl_repair_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `product_characteristic_id` (`product_characteristic_id`),
  ADD KEY `barcode` (`barcode`);

--
-- Indexes for table `tbl_repair_order_payments`
--
ALTER TABLE `tbl_repair_order_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `tbl_roles`
--
ALTER TABLE `tbl_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_tbl_roles_name` (`role_name`);

--
-- Indexes for table `tbl_sales_team_inventory_assign`
--
ALTER TABLE `tbl_sales_team_inventory_assign`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_barcode_global` (`barcode_no`(100)),
  ADD KEY `idx_sales_person` (`sales_person`(191));

--
-- Indexes for table `tbl_sale_fixing_direct`
--
ALTER TABLE `tbl_sale_fixing_direct`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ref_no` (`ref_no`),
  ADD KEY `idx_fixing_date` (`fixing_date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `tbl_sale_fixing_direct_items`
--
ALTER TABLE `tbl_sale_fixing_direct_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fixing_id` (`fixing_id`),
  ADD KEY `idx_metal_id` (`metal_id`);

--
-- Indexes for table `tbl_sale_invoices`
--
ALTER TABLE `tbl_sale_invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_no` (`invoice_no`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `invoice_date` (`invoice_date`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_sale_invoices_due_date` (`due_date`);

--
-- Indexes for table `tbl_sale_invoice_items`
--
ALTER TABLE `tbl_sale_invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `product_characteristic_id` (`product_characteristic_id`),
  ADD KEY `barcode` (`barcode`);

--
-- Indexes for table `tbl_sale_invoice_payments`
--
ALTER TABLE `tbl_sale_invoice_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`);

--
-- Indexes for table `tbl_sale_orders`
--
ALTER TABLE `tbl_sale_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `order_date` (`order_date`),
  ADD KEY `order_no` (`order_no`),
  ADD KEY `idx_sale_orders_due_date` (`due_date`);

--
-- Indexes for table `tbl_sale_order_diamond_stock_issue`
--
ALTER TABLE `tbl_sale_order_diamond_stock_issue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_so_order` (`order_id`),
  ADD KEY `idx_so_stock` (`stock_id`);

--
-- Indexes for table `tbl_sale_order_items`
--
ALTER TABLE `tbl_sale_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `product_characteristic_id` (`product_characteristic_id`);

--
-- Indexes for table `tbl_sale_order_payments`
--
ALTER TABLE `tbl_sale_order_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `tbl_sale_order_stone_stock_issue`
--
ALTER TABLE `tbl_sale_order_stone_stock_issue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_so_order_stone` (`order_id`),
  ADD KEY `idx_so_stock_stone` (`stock_id`);

--
-- Indexes for table `tbl_sale_quotations`
--
ALTER TABLE `tbl_sale_quotations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `quotation_no` (`quotation_no`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `quotation_date` (`quotation_date`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_sale_quotation_items`
--
ALTER TABLE `tbl_sale_quotation_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quotation_id` (`quotation_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `product_characteristic_id` (`product_characteristic_id`),
  ADD KEY `barcode` (`barcode`);

--
-- Indexes for table `tbl_sale_quotation_payments`
--
ALTER TABLE `tbl_sale_quotation_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quotation_id` (`quotation_id`);

--
-- Indexes for table `tbl_sale_receipt_vouchers`
--
ALTER TABLE `tbl_sale_receipt_vouchers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_srv_voucher_no` (`voucher_no`),
  ADD KEY `idx_srv_sale_invoice_no` (`sale_invoice_no`),
  ADD KEY `idx_srv_voucher_date` (`voucher_date`),
  ADD KEY `idx_srv_customer_id` (`customer_id`),
  ADD KEY `idx_srv_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_sale_receipt_voucher_items`
--
ALTER TABLE `tbl_sale_receipt_voucher_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_srvi_voucher_id` (`sale_receipt_voucher_id`),
  ADD KEY `idx_srvi_metal_id` (`metal_id`),
  ADD KEY `idx_srvi_product_id` (`product_id`);

--
-- Indexes for table `tbl_sale_returns`
--
ALTER TABLE `tbl_sale_returns`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `return_no` (`return_no`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `return_date` (`return_date`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_sale_return_items`
--
ALTER TABLE `tbl_sale_return_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `return_id` (`return_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `product_characteristic_id` (`product_characteristic_id`),
  ADD KEY `barcode` (`barcode`);

--
-- Indexes for table `tbl_sale_return_payments`
--
ALTER TABLE `tbl_sale_return_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `return_id` (`return_id`);

--
-- Indexes for table `tbl_settings`
--
ALTER TABLE `tbl_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_shape`
--
ALTER TABLE `tbl_shape`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_sieve_size`
--
ALTER TABLE `tbl_sieve_size`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_size`
--
ALTER TABLE `tbl_size`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_states`
--
ALTER TABLE `tbl_states`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_state_country` (`country_id`);

--
-- Indexes for table `tbl_stock`
--
ALTER TABLE `tbl_stock`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `metal_id` (`metal_id`),
  ADD KEY `product_characteristic_id` (`product_characteristic_id`),
  ADD KEY `idx_barcode` (`barcode`),
  ADD KEY `idx_stock_source_stock` (`source_stock_id`);

--
-- Indexes for table `tbl_stock_cross_transfer_log`
--
ALTER TABLE `tbl_stock_cross_transfer_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_src_stock` (`source_db`,`stock_id`),
  ADD KEY `idx_dest_bc` (`destination_db`,`destination_branch_id`,`barcode`(32)),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_outward_stock` (`outward_stock_id`);

--
-- Indexes for table `tbl_stock_journal`
--
ALTER TABLE `tbl_stock_journal`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sj_invoice_no` (`sj_invoice_no`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `metal_id` (`metal_id`),
  ADD KEY `sj_date` (`sj_date`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `tbl_stock_journal_images`
--
ALTER TABLE `tbl_stock_journal_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_item_barcode` (`item_id`,`barcode_no`);

--
-- Indexes for table `tbl_stock_transfer_pending`
--
ALTER TABLE `tbl_stock_transfer_pending`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status_to` (`status`,`to_branch_id`),
  ADD KEY `idx_outward` (`outward_stock_id`);

--
-- Indexes for table `tbl_task_type`
--
ALTER TABLE `tbl_task_type`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_taxes`
--
ALTER TABLE `tbl_taxes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_tax_master`
--
ALTER TABLE `tbl_tax_master`
  ADD PRIMARY KEY (`id`),
  ADD KEY `status` (`status`),
  ADD KEY `sort_order` (`sort_order`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_unit`
--
ALTER TABLE `tbl_unit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_unit_conversion`
--
ALTER TABLE `tbl_unit_conversion`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_users`
--
ALTER TABLE `tbl_users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_users_status` (`Status`);

--
-- Indexes for table `tbl_user_column_preferences`
--
ALTER TABLE `tbl_user_column_preferences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_page_tab_column` (`user_id`,`page_name`,`tab_key`,`column_key`),
  ADD KEY `idx_user_page` (`user_id`,`page_name`);

--
-- Indexes for table `tbl_user_permission_grants`
--
ALTER TABLE `tbl_user_permission_grants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_user_branch_perm` (`user_id`,`branch_id`,`perm_key`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_user_branch` (`user_id`,`branch_id`),
  ADD KEY `idx_user_branch_granted` (`user_id`,`branch_id`,`granted`);

--
-- Indexes for table `tbl_voucher_diamond_stock_issue`
--
ALTER TABLE `tbl_voucher_diamond_stock_issue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vd_kind_id` (`voucher_kind`,`voucher_id`),
  ADD KEY `idx_vd_stock` (`stock_id`),
  ADD KEY `idx_vd_source_issue` (`source_issue_id`);

--
-- Indexes for table `tbl_voucher_field_visibility`
--
ALTER TABLE `tbl_voucher_field_visibility`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_vt_branch_fv` (`voucher_type_id`,`branch_id`),
  ADD KEY `voucher_type_id` (`voucher_type_id`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_voucher_metal_allocations`
--
ALTER TABLE `tbl_voucher_metal_allocations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_vt_branch_metal` (`voucher_type_id`,`branch_id`,`metal_id`),
  ADD KEY `voucher_type_id` (`voucher_type_id`),
  ADD KEY `metal_id` (`metal_id`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_voucher_payment_buttons`
--
ALTER TABLE `tbl_voucher_payment_buttons`
  ADD PRIMARY KEY (`voucher_type_id`),
  ADD UNIQUE KEY `uk_vt_branch_pay` (`voucher_type_id`,`branch_id`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_voucher_settings`
--
ALTER TABLE `tbl_voucher_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_branch_metal` (`branch_id`,`metal_wise`),
  ADD KEY `idx_updated` (`updated_at`),
  ADD KEY `idx_voucher_settings_branch` (`branch_id`);

--
-- Indexes for table `tbl_voucher_stone_stock_issue`
--
ALTER TABLE `tbl_voucher_stone_stock_issue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vs_kind_id` (`voucher_kind`,`voucher_id`),
  ADD KEY `idx_vs_stock` (`stock_id`),
  ADD KEY `idx_vd_source_issue` (`source_issue_id`);

--
-- Indexes for table `tbl_voucher_tax_allocations`
--
ALTER TABLE `tbl_voucher_tax_allocations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_vt_branch_tax` (`voucher_type_id`,`branch_id`,`tax_id`),
  ADD KEY `voucher_type_id` (`voucher_type_id`),
  ADD KEY `tax_id` (`tax_id`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `tbl_voucher_types`
--
ALTER TABLE `tbl_voucher_types`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `invoice_fixing_mapping`
--
ALTER TABLE `invoice_fixing_mapping`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_accounting_calculation_settings`
--
ALTER TABLE `tbl_accounting_calculation_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_accounting_financial_years`
--
ALTER TABLE `tbl_accounting_financial_years`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_accounting_master_modes`
--
ALTER TABLE `tbl_accounting_master_modes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tbl_advance_payments`
--
ALTER TABLE `tbl_advance_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_advance_payment_items`
--
ALTER TABLE `tbl_advance_payment_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_article`
--
ALTER TABLE `tbl_article`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_auragold_notifications`
--
ALTER TABLE `tbl_auragold_notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_auragold_reward_coupons`
--
ALTER TABLE `tbl_auragold_reward_coupons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_barcode_settings`
--
ALTER TABLE `tbl_barcode_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `tbl_bill_series`
--
ALTER TABLE `tbl_bill_series`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=136;

--
-- AUTO_INCREMENT for table `tbl_branches`
--
ALTER TABLE `tbl_branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT for table `tbl_break_type`
--
ALTER TABLE `tbl_break_type`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_calculation_modes`
--
ALTER TABLE `tbl_calculation_modes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tbl_campaign_group`
--
ALTER TABLE `tbl_campaign_group`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_carat`
--
ALTER TABLE `tbl_carat`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_cash_denomination`
--
ALTER TABLE `tbl_cash_denomination`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_categories`
--
ALTER TABLE `tbl_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_cities`
--
ALTER TABLE `tbl_cities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=118;

--
-- AUTO_INCREMENT for table `tbl_clarity`
--
ALTER TABLE `tbl_clarity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_collection`
--
ALTER TABLE `tbl_collection`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_color`
--
ALTER TABLE `tbl_color`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_consignment_in`
--
ALTER TABLE `tbl_consignment_in`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_consignment_in_items`
--
ALTER TABLE `tbl_consignment_in_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_consignment_out`
--
ALTER TABLE `tbl_consignment_out`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_consignment_out_items`
--
ALTER TABLE `tbl_consignment_out_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_contra_vouchers`
--
ALTER TABLE `tbl_contra_vouchers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_contra_voucher_items`
--
ALTER TABLE `tbl_contra_voucher_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_counter`
--
ALTER TABLE `tbl_counter`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_countries`
--
ALTER TABLE `tbl_countries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `tbl_credit_card`
--
ALTER TABLE `tbl_credit_card`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_credit_notes`
--
ALTER TABLE `tbl_credit_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_credit_note_items`
--
ALTER TABLE `tbl_credit_note_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_credit_note_payments`
--
ALTER TABLE `tbl_credit_note_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_crm_contact_groups`
--
ALTER TABLE `tbl_crm_contact_groups`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_crm_contact_group_members`
--
ALTER TABLE `tbl_crm_contact_group_members`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_crm_whatsapp_campaigns`
--
ALTER TABLE `tbl_crm_whatsapp_campaigns`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_crm_whatsapp_campaign_images`
--
ALTER TABLE `tbl_crm_whatsapp_campaign_images`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_currency`
--
ALTER TABLE `tbl_currency`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbl_currency_exchange_rate`
--
ALTER TABLE `tbl_currency_exchange_rate`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_customers`
--
ALTER TABLE `tbl_customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_customer_advance_policy`
--
ALTER TABLE `tbl_customer_advance_policy`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_customer_advance_vouchers`
--
ALTER TABLE `tbl_customer_advance_vouchers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_customer_advance_voucher_items`
--
ALTER TABLE `tbl_customer_advance_voucher_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_customer_balance`
--
ALTER TABLE `tbl_customer_balance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_customer_ledger`
--
ALTER TABLE `tbl_customer_ledger`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_customer_types`
--
ALTER TABLE `tbl_customer_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `tbl_cut`
--
ALTER TABLE `tbl_cut`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_dashboard_metal_rates`
--
ALTER TABLE `tbl_dashboard_metal_rates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_dashboard_metal_rate_history`
--
ALTER TABLE `tbl_dashboard_metal_rate_history`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_day_reports`
--
ALTER TABLE `tbl_day_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_debit_notes`
--
ALTER TABLE `tbl_debit_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_debit_note_items`
--
ALTER TABLE `tbl_debit_note_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_debit_note_payments`
--
ALTER TABLE `tbl_debit_note_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_departments`
--
ALTER TABLE `tbl_departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_department_users`
--
ALTER TABLE `tbl_department_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_department_user_map`
--
ALTER TABLE `tbl_department_user_map`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_document_type`
--
ALTER TABLE `tbl_document_type`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_document_types`
--
ALTER TABLE `tbl_document_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_ewaybill_api_logs`
--
ALTER TABLE `tbl_ewaybill_api_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_ewaybill_api_settings`
--
ALTER TABLE `tbl_ewaybill_api_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_ewaybill_api_tokens`
--
ALTER TABLE `tbl_ewaybill_api_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_ewaybill_generate_logs`
--
ALTER TABLE `tbl_ewaybill_generate_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_expenses`
--
ALTER TABLE `tbl_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_expense_categories`
--
ALTER TABLE `tbl_expense_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_expense_items`
--
ALTER TABLE `tbl_expense_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_expense_payments`
--
ALTER TABLE `tbl_expense_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_extra_fields`
--
ALTER TABLE `tbl_extra_fields`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_investment_schemes`
--
ALTER TABLE `tbl_investment_schemes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_invoice_print_settings`
--
ALTER TABLE `tbl_invoice_print_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_ip_whitelist`
--
ALTER TABLE `tbl_ip_whitelist`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_jewelry_catalogue`
--
ALTER TABLE `tbl_jewelry_catalogue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_jobwork_invoices`
--
ALTER TABLE `tbl_jobwork_invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_jobwork_orders`
--
ALTER TABLE `tbl_jobwork_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_jobwork_order_comments`
--
ALTER TABLE `tbl_jobwork_order_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_jobwork_order_items`
--
ALTER TABLE `tbl_jobwork_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_jobwork_queue_activity`
--
ALTER TABLE `tbl_jobwork_queue_activity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_jobwork_queue_diamond_stock`
--
ALTER TABLE `tbl_jobwork_queue_diamond_stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_jobwork_queue_diamond_stock_issue`
--
ALTER TABLE `tbl_jobwork_queue_diamond_stock_issue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_jobwork_weight_adjustments`
--
ALTER TABLE `tbl_jobwork_weight_adjustments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_job_work_orders`
--
ALTER TABLE `tbl_job_work_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_journal_vouchers`
--
ALTER TABLE `tbl_journal_vouchers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_journal_voucher_items`
--
ALTER TABLE `tbl_journal_voucher_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_loan_product_type`
--
ALTER TABLE `tbl_loan_product_type`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_loan_reason`
--
ALTER TABLE `tbl_loan_reason`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_location`
--
ALTER TABLE `tbl_location`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_login_blocklist`
--
ALTER TABLE `tbl_login_blocklist`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_manufacturing_closing`
--
ALTER TABLE `tbl_manufacturing_closing`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_material_issues`
--
ALTER TABLE `tbl_material_issues`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_material_issue_items`
--
ALTER TABLE `tbl_material_issue_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_material_receives`
--
ALTER TABLE `tbl_material_receives`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_material_receive_items`
--
ALTER TABLE `tbl_material_receive_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_metal`
--
ALTER TABLE `tbl_metal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `tbl_metal_amount_conversions`
--
ALTER TABLE `tbl_metal_amount_conversions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_nationalities`
--
ALTER TABLE `tbl_nationalities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `tbl_old_jewelry_scrap_invoices`
--
ALTER TABLE `tbl_old_jewelry_scrap_invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_old_jewelry_scrap_invoice_items`
--
ALTER TABLE `tbl_old_jewelry_scrap_invoice_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_old_jewelry_scrap_invoice_payments`
--
ALTER TABLE `tbl_old_jewelry_scrap_invoice_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_old_jewelry_stock`
--
ALTER TABLE `tbl_old_jewelry_stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_packet_type`
--
ALTER TABLE `tbl_packet_type`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_payment_vouchers`
--
ALTER TABLE `tbl_payment_vouchers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_payment_voucher_items`
--
ALTER TABLE `tbl_payment_voucher_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_pos_sale_invoices`
--
ALTER TABLE `tbl_pos_sale_invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_pos_sale_invoice_items`
--
ALTER TABLE `tbl_pos_sale_invoice_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_pos_sale_invoice_payments`
--
ALTER TABLE `tbl_pos_sale_invoice_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_products`
--
ALTER TABLE `tbl_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_product_branches`
--
ALTER TABLE `tbl_product_branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tbl_product_characteristics`
--
ALTER TABLE `tbl_product_characteristics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tbl_product_tax`
--
ALTER TABLE `tbl_product_tax`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_purchase_fixing_direct`
--
ALTER TABLE `tbl_purchase_fixing_direct`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_purchase_fixing_direct_items`
--
ALTER TABLE `tbl_purchase_fixing_direct_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_purchase_invoices`
--
ALTER TABLE `tbl_purchase_invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_purchase_invoice_items`
--
ALTER TABLE `tbl_purchase_invoice_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_purchase_invoice_payments`
--
ALTER TABLE `tbl_purchase_invoice_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_purchase_orders`
--
ALTER TABLE `tbl_purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_purchase_order_items`
--
ALTER TABLE `tbl_purchase_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_purchase_order_payments`
--
ALTER TABLE `tbl_purchase_order_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_purchase_quotations`
--
ALTER TABLE `tbl_purchase_quotations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_purchase_quotation_items`
--
ALTER TABLE `tbl_purchase_quotation_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_purchase_quotation_payments`
--
ALTER TABLE `tbl_purchase_quotation_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_purchase_returns`
--
ALTER TABLE `tbl_purchase_returns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_purchase_return_items`
--
ALTER TABLE `tbl_purchase_return_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_purchase_return_payments`
--
ALTER TABLE `tbl_purchase_return_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_receipt_vouchers`
--
ALTER TABLE `tbl_receipt_vouchers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_receipt_voucher_items`
--
ALTER TABLE `tbl_receipt_voucher_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_remark`
--
ALTER TABLE `tbl_remark`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_repair_invoices`
--
ALTER TABLE `tbl_repair_invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_repair_invoice_items`
--
ALTER TABLE `tbl_repair_invoice_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_repair_invoice_payments`
--
ALTER TABLE `tbl_repair_invoice_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_repair_jobwork_orders`
--
ALTER TABLE `tbl_repair_jobwork_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_repair_jobwork_order_items`
--
ALTER TABLE `tbl_repair_jobwork_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_repair_material_issues`
--
ALTER TABLE `tbl_repair_material_issues`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_repair_material_issue_items`
--
ALTER TABLE `tbl_repair_material_issue_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_repair_material_receives`
--
ALTER TABLE `tbl_repair_material_receives`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_repair_material_receive_items`
--
ALTER TABLE `tbl_repair_material_receive_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_repair_orders`
--
ALTER TABLE `tbl_repair_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_repair_order_items`
--
ALTER TABLE `tbl_repair_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_repair_order_payments`
--
ALTER TABLE `tbl_repair_order_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_roles`
--
ALTER TABLE `tbl_roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_sales_team_inventory_assign`
--
ALTER TABLE `tbl_sales_team_inventory_assign`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_sale_fixing_direct`
--
ALTER TABLE `tbl_sale_fixing_direct`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_sale_fixing_direct_items`
--
ALTER TABLE `tbl_sale_fixing_direct_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_sale_invoices`
--
ALTER TABLE `tbl_sale_invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_sale_invoice_items`
--
ALTER TABLE `tbl_sale_invoice_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_sale_invoice_payments`
--
ALTER TABLE `tbl_sale_invoice_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_sale_orders`
--
ALTER TABLE `tbl_sale_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_sale_order_diamond_stock_issue`
--
ALTER TABLE `tbl_sale_order_diamond_stock_issue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_sale_order_items`
--
ALTER TABLE `tbl_sale_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_sale_order_payments`
--
ALTER TABLE `tbl_sale_order_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_sale_order_stone_stock_issue`
--
ALTER TABLE `tbl_sale_order_stone_stock_issue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_sale_quotations`
--
ALTER TABLE `tbl_sale_quotations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_sale_quotation_items`
--
ALTER TABLE `tbl_sale_quotation_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_sale_quotation_payments`
--
ALTER TABLE `tbl_sale_quotation_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_sale_receipt_vouchers`
--
ALTER TABLE `tbl_sale_receipt_vouchers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbl_sale_receipt_voucher_items`
--
ALTER TABLE `tbl_sale_receipt_voucher_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbl_sale_returns`
--
ALTER TABLE `tbl_sale_returns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_sale_return_items`
--
ALTER TABLE `tbl_sale_return_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_sale_return_payments`
--
ALTER TABLE `tbl_sale_return_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_settings`
--
ALTER TABLE `tbl_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_shape`
--
ALTER TABLE `tbl_shape`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_sieve_size`
--
ALTER TABLE `tbl_sieve_size`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_size`
--
ALTER TABLE `tbl_size`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_states`
--
ALTER TABLE `tbl_states`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;

--
-- AUTO_INCREMENT for table `tbl_stock`
--
ALTER TABLE `tbl_stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `tbl_stock_cross_transfer_log`
--
ALTER TABLE `tbl_stock_cross_transfer_log`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tbl_stock_journal`
--
ALTER TABLE `tbl_stock_journal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tbl_stock_journal_images`
--
ALTER TABLE `tbl_stock_journal_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tbl_stock_transfer_pending`
--
ALTER TABLE `tbl_stock_transfer_pending`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_task_type`
--
ALTER TABLE `tbl_task_type`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_taxes`
--
ALTER TABLE `tbl_taxes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_tax_master`
--
ALTER TABLE `tbl_tax_master`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbl_unit`
--
ALTER TABLE `tbl_unit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_unit_conversion`
--
ALTER TABLE `tbl_unit_conversion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_users`
--
ALTER TABLE `tbl_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_user_column_preferences`
--
ALTER TABLE `tbl_user_column_preferences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_user_permission_grants`
--
ALTER TABLE `tbl_user_permission_grants`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_voucher_diamond_stock_issue`
--
ALTER TABLE `tbl_voucher_diamond_stock_issue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_voucher_field_visibility`
--
ALTER TABLE `tbl_voucher_field_visibility`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_voucher_metal_allocations`
--
ALTER TABLE `tbl_voucher_metal_allocations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_voucher_settings`
--
ALTER TABLE `tbl_voucher_settings`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=217;

--
-- AUTO_INCREMENT for table `tbl_voucher_stone_stock_issue`
--
ALTER TABLE `tbl_voucher_stone_stock_issue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_voucher_tax_allocations`
--
ALTER TABLE `tbl_voucher_tax_allocations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_voucher_types`
--
ALTER TABLE `tbl_voucher_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tbl_pos_sale_invoice_items`
--
ALTER TABLE `tbl_pos_sale_invoice_items`
  ADD CONSTRAINT `fk_psi_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `tbl_pos_sale_invoices` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_pos_sale_invoice_payments`
--
ALTER TABLE `tbl_pos_sale_invoice_payments`
  ADD CONSTRAINT `fk_psp_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `tbl_pos_sale_invoices` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_sale_receipt_voucher_items`
--
ALTER TABLE `tbl_sale_receipt_voucher_items`
  ADD CONSTRAINT `fk_srvi_sale_receipt_voucher` FOREIGN KEY (`sale_receipt_voucher_id`) REFERENCES `tbl_sale_receipt_vouchers` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
