-- RAM-YUM STORE - Remove "Out of Season" from the Promotion Engine
-- Only needed if you already ran the original promotion_module.sql
-- (the one that included 'out_of_season'). Run once:
--   mysql -u root -p mms < remove_out_of_season.sql
--
-- Safe to run even if you never had any out-of-season promotions -
-- the DELETEs will just affect zero rows.

START TRANSACTION;
USE `mms`;

-- Unlink and remove any auto-generated (or manual) "out of season"
-- promotions. Related sale_items keep their historical promotion_name
-- text; only the promotion_id link is cleared (FK is ON DELETE SET NULL).
DELETE pp FROM `promotion_products` pp
  JOIN `promotions` p ON p.id = pp.promotion_id
  WHERE p.reason = 'out_of_season';

DELETE FROM `promotions` WHERE `reason` = 'out_of_season';

-- Narrow the reason enum back down now that no rows reference it.
ALTER TABLE `promotions`
  MODIFY COLUMN `reason` ENUM('manual','near_expiration','slow_selling','replaced_model') NOT NULL DEFAULT 'manual';

-- Optional cleanup: the season_tags column is no longer read by anything.
-- Comment this out if you'd rather keep the column around.
ALTER TABLE `products` DROP COLUMN `season_tags`;

COMMIT;
