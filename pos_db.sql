-- RAM-YUM STORE - Point of Sale schema
-- Run against the same `mms` database used by login_db.sql, e.g.:
--   mysql -u root -p mms < pos_db.sql

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

USE `mms`;

-- --------------------------------------------------------
-- Table: categories
-- --------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `icon` VARCHAR(50) NOT NULL DEFAULT 'fa-tags',
  `sort_order` INT(11) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: promotions
-- Simple storewide percentage-off promos. A promo is "live" when
-- is_active = 1 and the current moment falls within [starts_at, ends_at]
-- (either bound may be NULL = open-ended). Scope is kept as an ENUM so
-- per-category/per-product promos can be added later without a schema
-- rewrite; pos_checkout.php currently only understands 'storewide'.
-- --------------------------------------------------------
DROP TABLE IF EXISTS `promotions`;
CREATE TABLE `promotions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `scope` ENUM('storewide') NOT NULL DEFAULT 'storewide',
  `discount_percent` DECIMAL(5,2) NOT NULL COMMENT 'e.g. 10.00 = 10% off',
  `starts_at` DATETIME NULL DEFAULT NULL,
  `ends_at` DATETIME NULL DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_promotions_active_window` (`is_active`, `starts_at`, `ends_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: products
-- --------------------------------------------------------
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `category_id` INT(11) NOT NULL,
  `sku` VARCHAR(50) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `cost` DECIMAL(10,2) NULL DEFAULT NULL,
  `stock_qty` INT(11) NOT NULL DEFAULT 0,
  `low_stock_threshold` INT(11) NOT NULL DEFAULT 10,
  `image` VARCHAR(255) NULL DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_sku` (`sku`),
  KEY `idx_products_category` (`category_id`),
  KEY `idx_products_stock` (`stock_qty`),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: sales
-- One row per completed (or voided) transaction.
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sales`;
CREATE TABLE `sales` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `receipt_no` VARCHAR(30) NOT NULL,
  `cashier_id` INT(11) NULL DEFAULT NULL,
  `cashier_email` VARCHAR(255) NULL DEFAULT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `promotion_id` INT(11) NULL DEFAULT NULL,
  `promotion_name` VARCHAR(150) NULL DEFAULT NULL COMMENT 'snapshotted so receipts stay accurate if the promo is later edited/deleted',
  `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `amount_received` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `change_due` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` ENUM('cash','gcash','card') NOT NULL DEFAULT 'cash',
  `item_count` INT(11) NOT NULL DEFAULT 0,
  `status` ENUM('completed','voided') NOT NULL DEFAULT 'completed',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sales_receipt_no` (`receipt_no`),
  KEY `idx_sales_created` (`created_at`),
  KEY `idx_sales_cashier` (`cashier_id`),
  KEY `idx_sales_promotion` (`promotion_id`),
  CONSTRAINT `fk_sales_promotion` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: sale_items
-- Line items per sale. Product name/price are snapshotted so historical
-- receipts stay accurate even if a product is later renamed/repriced.
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sale_items`;
CREATE TABLE `sale_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `sale_id` INT(11) NOT NULL,
  `product_id` INT(11) NULL DEFAULT NULL,
  `product_name` VARCHAR(150) NOT NULL,
  `unit_price` DECIMAL(10,2) NOT NULL,
  `quantity` INT(11) NOT NULL,
  `line_total` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sale_items_sale` (`sale_id`),
  KEY `idx_sale_items_product` (`product_id`),
  CONSTRAINT `fk_sale_items_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sale_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: stock_movements
-- Append-only audit trail of every stock change (sale, restock, manual
-- adjustment). Lets you reconstruct stock history / support M2's needs.
-- --------------------------------------------------------
DROP TABLE IF EXISTS `stock_movements`;
CREATE TABLE `stock_movements` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `product_id` INT(11) NOT NULL,
  `change_qty` INT(11) NOT NULL COMMENT 'negative for sales/deductions, positive for restocks',
  `reason` ENUM('sale','restock','adjustment','void') NOT NULL,
  `reference_id` INT(11) NULL DEFAULT NULL COMMENT 'e.g. sale_id when reason = sale/void',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_stock_movements_product` (`product_id`),
  CONSTRAINT `fk_stock_movements_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Seed: categories
-- --------------------------------------------------------
INSERT INTO `categories` (`name`, `icon`, `sort_order`) VALUES
('Ramen & Noodles',      'fa-bowl-food',    1),
('Snacks',               'fa-cookie-bite',  2),
('Beverages',            'fa-mug-hot',      3),
('Sauces & Condiments',  'fa-jar',          4),
('Frozen & Instant',     'fa-snowflake',    5),
('Kimchi & Sides',       'fa-pepper-hot',   6);

-- --------------------------------------------------------
-- Seed: promotions (one open-ended storewide promo, active now, so the
-- POS has something to auto-apply out of the box. Deactivate/delete once
-- your real promotions module is managing these rows.)
-- --------------------------------------------------------
INSERT INTO `promotions` (`name`, `scope`, `discount_percent`, `starts_at`, `ends_at`, `is_active`) VALUES
('Storewide Sale', 'storewide', 10.00, NULL, NULL, 1);

-- --------------------------------------------------------
-- Seed: products (some stock levels intentionally low to demo the
-- low-stock widget; low_stock_threshold = 10 across the board)
-- --------------------------------------------------------
INSERT INTO `products` (`category_id`, `sku`, `name`, `price`, `cost`, `stock_qty`, `low_stock_threshold`) VALUES
(1, 'RN-001', 'Shin Ramyun Spicy Cup',          65.00, 42.00, 48, 10),
(1, 'RN-002', 'Buldak Hot Chicken Ramen',       72.00, 48.00,  8, 10),
(1, 'RN-003', 'Jin Ramen Mild',                 58.00, 38.00, 35, 10),
(1, 'RN-004', 'Nissin Yakisoba Noodles',        60.00, 40.00, 22, 10),
(2, 'SN-001', 'Honey Butter Chips',             85.00, 55.00, 30, 10),
(2, 'SN-002', 'Pocky Chocolate Sticks',         55.00, 34.00,  6, 10),
(2, 'SN-003', 'Melona Bar (Melon)',             40.00, 24.00, 18, 10),
(2, 'SN-004', 'Choco Pie 12pk',                150.00, 98.00, 14, 10),
(3, 'BV-001', 'Milkis Soda Can',                50.00, 30.00, 40, 10),
(3, 'BV-002', 'Pocari Sweat 500ml',             55.00, 34.00,  9, 10),
(3, 'BV-003', 'Barley Tea (Boricha)',           48.00, 28.00, 26, 10),
(3, 'BV-004', 'Ramune Original 200ml',          65.00, 40.00, 20, 10),
(4, 'SC-001', 'Gochujang Paste 500g',          210.00,140.00, 15, 10),
(4, 'SC-002', 'Soy Sauce (Sempio) 500ml',      120.00, 78.00, 24, 10),
(4, 'SC-003', 'Sesame Oil 320ml',              180.00,120.00,  5, 10),
(4, 'SC-004', 'Gochugaru Chili Flakes 200g',   160.00,105.00, 12, 10),
(5, 'FZ-001', 'Frozen Mandu Dumplings 1kg',    280.00,190.00, 10, 10),
(5, 'FZ-002', 'Frozen Tteokbokki Rice Cake',   135.00, 88.00,  7, 10),
(5, 'FZ-003', 'Frozen Gyoza 500g',             165.00,110.00, 19, 10),
(5, 'FZ-004', 'Instant Japchae Kit',           145.00, 96.00, 16, 10),
(6, 'KM-001', 'Cabbage Kimchi 500g',           190.00,120.00, 13, 10),
(6, 'KM-002', 'Radish Kimchi (Kkakdugi) 400g', 175.00,112.00,  4, 10),
(6, 'KM-003', 'Pickled Radish (Danmuji) 300g',  95.00, 60.00, 21, 10),
(6, 'KM-004', 'Seasoned Seaweed (Gim) 20pk',    80.00, 50.00, 28, 10);

-- --------------------------------------------------------
-- Seed: a few historical sales so the "Best Sellers" widget has data
-- to rank on out of the box. Safe to delete once real sales exist.
-- --------------------------------------------------------
-- item_count = total unit quantity across all lines in the sale
INSERT INTO `sales` (`receipt_no`, `cashier_email`, `subtotal`, `discount`, `total`, `amount_received`, `change_due`, `payment_method`, `item_count`, `created_at`) VALUES
('RY-DEMO-00001', 'cj@gmail.com', 260.00, 0.00, 260.00, 300.00, 40.00, 'cash',  4, DATE_SUB(NOW(), INTERVAL 2 DAY)),
('RY-DEMO-00002', 'cj@gmail.com', 143.00, 0.00, 143.00, 200.00, 57.00, 'cash',  3, DATE_SUB(NOW(), INTERVAL 1 DAY)),
('RY-DEMO-00003', 'cj@gmail.com', 425.00, 0.00, 425.00, 500.00, 75.00, 'cash',  7, NOW());

INSERT INTO `sale_items` (`sale_id`, `product_id`, `product_name`, `unit_price`, `quantity`, `line_total`) VALUES
(1, 1, 'Shin Ramyun Spicy Cup',       65.00, 2, 130.00),
(1, 9, 'Milkis Soda Can',             50.00, 1,  50.00),
(1, 24,'Seasoned Seaweed (Gim) 20pk', 80.00, 1,  80.00),
(2, 5, 'Honey Butter Chips',          85.00, 1,  85.00),
(2, 9, 'Milkis Soda Can',             50.00, 1,  50.00),
(2, 23,'Pickled Radish (Danmuji) 300g', 8.00, 1,   8.00),
(3, 1, 'Shin Ramyun Spicy Cup',       65.00, 4, 260.00),
(3, 9, 'Milkis Soda Can',             50.00, 1,  50.00),
(3, 6, 'Pocky Chocolate Sticks',      55.00, 1,  55.00),
(3, 4, 'Nissin Yakisoba Noodles',     60.00, 1,  60.00);

-- (Danmuji unit_price above is a one-off promo price snapshot from that
-- historical sale, distinct from the product's current catalog price —
-- illustrates why sale_items stores its own price snapshot.)

COMMIT;