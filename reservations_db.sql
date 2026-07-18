-- RAM-YUM STORE - Orders & Reservations schema
-- Run AFTER pos_db.sql, against the same `mms` database:
--   mysql -u root -p mms < reservations_db.sql

USE `mms`;

-- stock_movements already has a `reason` ENUM ('sale','restock','adjustment','void').
-- A reservation deducts stock immediately (same as a sale would), and a
-- cancelled reservation puts that stock back — both need their own reason
-- so the audit trail stays honest about why the number moved.
ALTER TABLE `stock_movements`
  MODIFY `reason` ENUM('sale','restock','adjustment','void','reservation','reservation_release') NOT NULL;

-- --------------------------------------------------------
-- Table: reservations
-- A "set aside" transaction: stock is deducted right away (it's spoken
-- for) but payment hasn't happened yet. Staff fulfills it later by
-- ringing it up at the counter, or cancels it, which puts the stock back.
-- --------------------------------------------------------
DROP TABLE IF EXISTS `reservation_items`;
DROP TABLE IF EXISTS `reservations`;
CREATE TABLE `reservations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `reservation_no` VARCHAR(30) NOT NULL,
  `customer_name` VARCHAR(150) NOT NULL,
  `customer_contact` VARCHAR(100) NULL DEFAULT NULL,
  `notes` VARCHAR(255) NULL DEFAULT NULL,
  `staff_id` INT(11) NULL DEFAULT NULL,
  `staff_email` VARCHAR(255) NULL DEFAULT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `promotion_id` INT(11) NULL DEFAULT NULL,
  `promotion_name` VARCHAR(150) NULL DEFAULT NULL,
  `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `item_count` INT(11) NOT NULL DEFAULT 0,
  `status` ENUM('reserved','fulfilled','cancelled') NOT NULL DEFAULT 'reserved',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fulfilled_at` TIMESTAMP NULL DEFAULT NULL,
  `fulfilled_sale_id` INT(11) NULL DEFAULT NULL COMMENT 'sales.id this reservation was rung up as, once processed at POS',
  `cancelled_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reservations_no` (`reservation_no`),
  KEY `idx_reservations_status` (`status`),
  KEY `idx_reservations_created` (`created_at`),
  CONSTRAINT `fk_reservations_promotion` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_reservations_sale` FOREIGN KEY (`fulfilled_sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `reservation_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `reservation_id` INT(11) NOT NULL,
  `product_id` INT(11) NULL DEFAULT NULL,
  `product_name` VARCHAR(150) NOT NULL,
  `unit_price` DECIMAL(10,2) NOT NULL,
  `promotion_id` INT(11) NULL DEFAULT NULL,
  `promotion_name` VARCHAR(150) NULL DEFAULT NULL,
  `quantity` INT(11) NOT NULL,
  `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `line_total` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_reservation_items_reservation` (`reservation_id`),
  KEY `idx_reservation_items_product` (`product_id`),
  CONSTRAINT `fk_reservation_items_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reservation_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;