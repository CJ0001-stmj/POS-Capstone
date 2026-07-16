-- RAM-YUM STORE - Point of Sale schema
-- Run against the same `mms` database as login_db.sql, e.g.:
--   mysql -u root -p mms < pos_db.sql

USE `mms`;

-- --------------------------------------------------------
-- Table: categories
-- Product groupings shown as tabs in the POS screen.
-- --------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `icon` VARCHAR(50) NOT NULL DEFAULT 'fa-tag', -- Font Awesome class, no "fa-solid" prefix
  `sort_order` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: products
-- One row per sellable item. `stock_qty` is the live on-hand count;
-- it is decremented atomically at checkout (see transaction below).
-- --------------------------------------------------------
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `category_id` INT(11) NOT NULL,
  `sku` VARCHAR(40) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `stock_qty` INT(11) NOT NULL DEFAULT 0,
  `low_stock_threshold` INT(11) NOT NULL DEFAULT 10,
  `image` VARCHAR(255) NULL DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_sku` (`sku`),
  KEY `idx_products_category` (`category_id`),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: transactions
-- One row per completed sale/checkout. Totals are snapshotted at
-- checkout time so historical receipts never change even if product
-- prices change later.
-- --------------------------------------------------------
DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `transaction_code` VARCHAR(30) NOT NULL, -- e.g. RY-20260715-000123
  `cashier_email` VARCHAR(255) NULL DEFAULT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL,
  `discount_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `amount_received` DECIMAL(10,2) NOT NULL,
  `change_due` DECIMAL(10,2) NOT NULL,
  `payment_method` ENUM('cash','gcash','card') NOT NULL DEFAULT 'cash',
  `item_count` INT(11) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_transactions_code` (`transaction_code`),
  KEY `idx_transactions_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: transaction_items
-- Line items for a transaction. Product name/price are snapshotted
-- so a later product edit/deletion never corrupts an old receipt.
-- --------------------------------------------------------
DROP TABLE IF EXISTS `transaction_items`;
CREATE TABLE `transaction_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` INT(11) NOT NULL,
  `product_id` INT(11) NULL DEFAULT NULL,
  `product_name` VARCHAR(150) NOT NULL,
  `unit_price` DECIMAL(10,2) NOT NULL,
  `quantity` INT(11) NOT NULL,
  `line_total` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_items_transaction` (`transaction_id`),
  KEY `idx_items_product` (`product_id`),
  CONSTRAINT `fk_items_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: stock_movements
-- Append-only ledger of every stock change (sale, restock, manual
-- adjustment). Lets low-stock and "best seller" reporting be
-- reconstructed/audited without touching `products.stock_qty` directly.
-- --------------------------------------------------------
DROP TABLE IF EXISTS `stock_movements`;
CREATE TABLE `stock_movements` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `product_id` INT(11) NOT NULL,
  `change_qty` INT(11) NOT NULL, -- negative for sales, positive for restock
  `reason` ENUM('sale','restock','adjustment') NOT NULL,
  `reference_id` INT(11) NULL DEFAULT NULL, -- transactions.id when reason = 'sale'
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_stockmove_product` (`product_id`),
  CONSTRAINT `fk_stockmove_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ==========================================================
-- Demo seed data
-- ==========================================================

INSERT INTO `categories` (`name`, `icon`, `sort_order`) VALUES
('Ramen & Noodles', 'fa-bowl-food', 1),
('Rice & Meals', 'fa-plate-wheat', 2),
('Snacks & Sides', 'fa-cookie-bite', 3),
('Beverages', 'fa-bottle-water', 4),
('Sauces & Seasonings', 'fa-jar', 5),
('Frozen & Fresh', 'fa-snowflake', 6);

INSERT INTO `products` (`category_id`, `sku`, `name`, `price`, `cost`, `stock_qty`, `low_stock_threshold`, `is_active`) VALUES
(1, 'RM-001', 'Shin Ramyun (Spicy)',           68.00, 42.00, 45, 15, 1),
(1, 'RM-002', 'Jin Ramen (Mild)',              65.00, 40.00, 38, 15, 1),
(1, 'RM-003', 'Kimchi Ramen Bowl',             85.00, 52.00,  8, 15, 1),
(1, 'RM-004', 'Buldak Hot Chicken Ramen',      75.00, 46.00,  6, 15, 1),
(1, 'RM-005', 'Udon Noodle Pack',              90.00, 55.00, 24, 10, 1),
(2, 'RC-001', 'Korean Rice Cake (Tteokbokki)', 120.00, 78.00, 18, 10, 1),
(2, 'RC-002', 'Japanese Curry Rice Kit',       110.00, 70.00, 22, 10, 1),
(2, 'RC-003', 'Onigiri Rice Set',              95.00, 60.00,  5, 10, 1),
(3, 'SN-001', 'Seaweed Snack (Original)',      45.00, 22.00, 60, 20, 1),
(3, 'SN-002', 'Korean Corn Dog',               60.00, 34.00, 12, 15, 1),
(3, 'SN-003', 'Mochi Bites (Assorted)',        70.00, 40.00,  9, 12, 1),
(3, 'SN-004', 'Shrimp Chips',                  40.00, 18.00, 33, 15, 1),
(4, 'BV-001', 'Milkis Soda',                   55.00, 30.00, 40, 15, 1),
(4, 'BV-002', 'Ramune Soda (Original)',        50.00, 28.00, 28, 15, 1),
(4, 'BV-003', 'Green Tea Bottle',              45.00, 24.00,  7, 15, 1),
(5, 'SC-001', 'Gochujang Paste 250g',          135.00, 88.00, 20, 8, 1),
(5, 'SC-002', 'Soy Sauce (Japanese) 500ml',    95.00, 58.00, 26, 8, 1),
(5, 'SC-003', 'Furikake Rice Seasoning',       80.00, 46.00, 14, 10, 1),
(6, 'FZ-001', 'Frozen Gyoza (12pcs)',          145.00, 92.00, 16, 10, 1),
(6, 'FZ-002', 'Frozen Mandu (Korean Dumpling)',150.00, 95.00,  4, 10, 1);

-- Sample completed transactions so the Best Sellers panel has
-- something to rank on a fresh install. Safe to delete in production.
INSERT INTO `transactions` (`transaction_code`, `cashier_email`, `subtotal`, `discount_total`, `total_amount`, `amount_received`, `change_due`, `payment_method`, `item_count`, `created_at`) VALUES
('RY-DEMO-000001', 'cj@gmail.com', 304.00, 0.00, 304.00, 350.00, 46.00, 'cash', 3, NOW() - INTERVAL 6 DAY),
('RY-DEMO-000002', 'cj@gmail.com', 188.00, 0.00, 188.00, 200.00, 12.00, 'cash', 2, NOW() - INTERVAL 4 DAY),
('RY-DEMO-000003', 'cj@gmail.com', 256.00, 0.00, 256.00, 300.00, 44.00, 'gcash', 3, NOW() - INTERVAL 2 DAY),
('RY-DEMO-000004', 'cj@gmail.com', 143.00, 0.00, 143.00, 150.00,  7.00, 'cash', 2, NOW() - INTERVAL 1 DAY);

INSERT INTO `transaction_items` (`transaction_id`, `product_id`, `product_name`, `unit_price`, `quantity`, `line_total`) VALUES
(1, 1, 'Shin Ramyun (Spicy)', 68.00, 3, 204.00),
(1, 9, 'Seaweed Snack (Original)', 45.00, 1, 45.00),
(1, 13, 'Milkis Soda', 55.00, 1, 55.00),
(2, 1, 'Shin Ramyun (Spicy)', 68.00, 1, 68.00),
(2, 4, 'Buldak Hot Chicken Ramen', 75.00, 1, 75.00),
(2, 9, 'Seaweed Snack (Original)', 45.00, 1, 45.00),
(3, 1, 'Shin Ramyun (Spicy)', 68.00, 2, 136.00),
(3, 4, 'Buldak Hot Chicken Ramen', 75.00, 1, 75.00),
(3, 9, 'Seaweed Snack (Original)', 45.00, 1, 45.00),
(4, 1, 'Shin Ramyun (Spicy)', 68.00, 1, 68.00),
(4, 4, 'Buldak Hot Chicken Ramen', 75.00, 1, 75.00);

INSERT INTO `stock_movements` (`product_id`, `change_qty`, `reason`, `reference_id`, `created_at`) VALUES
(1, -3, 'sale', 1, NOW() - INTERVAL 6 DAY),
(9, -1, 'sale', 1, NOW() - INTERVAL 6 DAY),
(13, -1, 'sale', 1, NOW() - INTERVAL 6 DAY),
(1, -1, 'sale', 2, NOW() - INTERVAL 4 DAY),
(4, -1, 'sale', 2, NOW() - INTERVAL 4 DAY),
(9, -1, 'sale', 2, NOW() - INTERVAL 4 DAY),
(1, -2, 'sale', 3, NOW() - INTERVAL 2 DAY),
(4, -1, 'sale', 3, NOW() - INTERVAL 2 DAY),
(9, -1, 'sale', 3, NOW() - INTERVAL 2 DAY),
(1, -1, 'sale', 4, NOW() - INTERVAL 1 DAY),
(4, -1, 'sale', 4, NOW() - INTERVAL 1 DAY);