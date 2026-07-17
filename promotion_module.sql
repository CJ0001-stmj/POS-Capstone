-- RAM-YUM STORE - Promotion Engine schema
-- Additive migration on top of pos_db.sql. Run once against the same `mms`
-- database:
--   mysql -u root -p mms < promotion_module.sql

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

USE `mms`;

-- --------------------------------------------------------
-- products: signals the auto-promotion engine reads to decide what
-- qualifies for "Near Expiration" / "Replaced by Newer Model".
-- ("Slow Selling" needs no new column - it's derived purely from the
-- sales history that already exists.)
-- --------------------------------------------------------
ALTER TABLE `products`
  ADD COLUMN `expiry_date` DATE NULL DEFAULT NULL
      COMMENT 'Optional. Set for perishable/dated stock so the engine can flag it as it nears expiry.'
      AFTER `low_stock_threshold`,
  ADD COLUMN `is_superseded` TINYINT(1) NOT NULL DEFAULT 0
      COMMENT 'Manually flagged when a newer model/version/flavor has replaced this product and remaining stock should be cleared.'
      AFTER `expiry_date`;

-- --------------------------------------------------------
-- promotions: broaden scope to allow per-product (not just storewide)
-- promos, and tag *why* a promotion exists so the POS can label it.
-- --------------------------------------------------------
ALTER TABLE `promotions`
  MODIFY COLUMN `scope` ENUM('storewide','product') NOT NULL DEFAULT 'storewide',
  ADD COLUMN `reason` ENUM('manual','near_expiration','slow_selling','replaced_model') NOT NULL DEFAULT 'manual'
      COMMENT 'Why the discount exists. "manual" = admin-created for an arbitrary reason (e.g. a storewide sale).'
      AFTER `name`,
  ADD COLUMN `auto_generated` TINYINT(1) NOT NULL DEFAULT 0
      COMMENT '1 = owned/maintained by the promotion engine scan (products.php -> Run Analytics Scan). Manual edits to these are overwritten on the next scan.'
      AFTER `reason`,
  ADD COLUMN `notes` VARCHAR(255) NULL DEFAULT NULL AFTER `discount_percent`;

-- --------------------------------------------------------
-- Table: promotion_products
-- Which specific products a scope='product' promotion applies to.
-- Unused for scope='storewide' promotions.
-- --------------------------------------------------------
DROP TABLE IF EXISTS `promotion_products`;
CREATE TABLE `promotion_products` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `promotion_id` INT(11) NOT NULL,
  `product_id` INT(11) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_promo_product` (`promotion_id`, `product_id`),
  KEY `idx_promo_products_product` (`product_id`),
  CONSTRAINT `fk_promo_products_promotion` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_promo_products_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- sale_items: snapshot which promotion (if any) discounted each line,
-- and by how much, so receipts/reports stay accurate historically even
-- as promotions change later. line_total already reflects the
-- post-discount amount; discount_amount is broken out for reporting.
-- --------------------------------------------------------
ALTER TABLE `sale_items`
  ADD COLUMN `promotion_id` INT(11) NULL DEFAULT NULL AFTER `product_id`,
  ADD COLUMN `promotion_name` VARCHAR(150) NULL DEFAULT NULL AFTER `unit_price`,
  ADD COLUMN `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `quantity`,
  ADD KEY `idx_sale_items_promotion` (`promotion_id`),
  ADD CONSTRAINT `fk_sale_items_promotion` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`) ON DELETE SET NULL;

COMMIT;