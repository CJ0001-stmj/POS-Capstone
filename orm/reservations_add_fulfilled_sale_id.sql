-- RAM-YUM STORE - migration: link a fulfilled reservation to its sale
-- Only needed if reservations_db.sql was already run before this change
-- (i.e. you have real reservation data and don't want to DROP/CREATE
-- reservations again). Run once:
--   mysql -u root -p mms < reservations_add_fulfilled_sale_id.sql

USE `mms`;

ALTER TABLE `reservations`
  ADD COLUMN `fulfilled_sale_id` INT(11) NULL DEFAULT NULL
    COMMENT 'sales.id this reservation was rung up as, once processed at POS'
    AFTER `fulfilled_at`,
  ADD CONSTRAINT `fk_reservations_sale` FOREIGN KEY (`fulfilled_sale_id`)
    REFERENCES `sales` (`id`) ON DELETE SET NULL;
