-- RAM-YUM STORE - Pending Orders schema
-- Orders land here from wherever customers place them (not this admin
-- app). orders.php is now a pure receivables queue: cashier/admin pick
-- a pending row, collect cash, and it becomes a real `sales` row.
-- Run against the same `mms` database, e.g.:
--   mysql -u root -p mms < pending_orders.sql

USE `mms`;

DROP TABLE IF EXISTS `pending_order_items`;
DROP TABLE IF EXISTS `pending_orders`;

CREATE TABLE `pending_orders` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_no` VARCHAR(30) NOT NULL,
  `customer_name` VARCHAR(150) NULL DEFAULT NULL,
  `customer_contact` VARCHAR(50) NULL DEFAULT NULL,
  `notes` VARCHAR(255) NULL DEFAULT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `promotion_name` VARCHAR(150) NULL DEFAULT NULL,
  `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `item_count` INT(11) NOT NULL DEFAULT 0,
  `status` ENUM('pending','completed','cancelled') NOT NULL DEFAULT 'pending',
  `processed_by_email` VARCHAR(255) NULL DEFAULT NULL,
  `processed_at` DATETIME NULL DEFAULT NULL,
  `sale_id` INT(11) NULL DEFAULT NULL COMMENT 'set once processed - links to the resulting sales row',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pending_orders_order_no` (`order_no`),
  KEY `idx_pending_orders_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `pending_order_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `pending_order_id` INT(11) NOT NULL,
  `product_id` INT(11) NULL DEFAULT NULL,
  `product_name` VARCHAR(150) NOT NULL,
  `unit_price` DECIMAL(10,2) NOT NULL,
  `quantity` INT(11) NOT NULL,
  `line_total` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_poi_order` (`pending_order_id`),
  KEY `idx_poi_product` (`product_id`),
  CONSTRAINT `fk_poi_order` FOREIGN KEY (`pending_order_id`) REFERENCES `pending_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_poi_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Note: stock is assumed already deducted by whatever puts rows in here
-- (mirrors how reservations already deduct on creation) - orders.php's
-- processing step only collects payment / restores stock on cancel.
