-- RAM-YUM STORE — additive migration for User Access Control upgrade
-- Run this AFTER login_db.sql (or against your existing `mms` DB).
-- Unlike login_db.sql, this does NOT drop/recreate `users` — it only adds
-- columns, so existing accounts and data are kept.
--
--   mysql -u root -p mms < migration_staff_fields.sql

USE `mms`;

-- --------------------------------------------------------
-- New staff-profile columns on `users`
-- birth_date is a full date (not just month) — age is calculated
-- from it on the fly in PHP, so we don't store a separate age column
-- that would go stale.
-- shift_start / shift_end assume a same-day shift (e.g. 08:00–16:00).
-- If you run overnight shifts (e.g. 22:00–06:00) the shortfall check
-- in user-access-control.php will need a small tweak — flag it if so.
-- --------------------------------------------------------
ALTER TABLE `users`
    ADD COLUMN `full_name`   VARCHAR(150) NULL DEFAULT NULL AFTER `email`,
    ADD COLUMN `birth_date`  DATE         NULL DEFAULT NULL AFTER `full_name`,
    ADD COLUMN `phone`       VARCHAR(30)  NULL DEFAULT NULL AFTER `birth_date`,
    ADD COLUMN `address`     VARCHAR(255) NULL DEFAULT NULL AFTER `phone`,
    ADD COLUMN `shift_start` TIME         NULL DEFAULT NULL AFTER `address`,
    ADD COLUMN `shift_end`   TIME         NULL DEFAULT NULL AFTER `shift_start`;

-- --------------------------------------------------------
-- Table: staff_warnings
-- One row per warning an admin sends to a staff member (e.g. for not
-- meeting their assigned shift hours). Kept append-only, same spirit
-- as login_audit — never edited or deleted, just accumulated history.
-- --------------------------------------------------------
DROP TABLE IF EXISTS `staff_warnings`;
CREATE TABLE `staff_warnings` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11) NOT NULL,
  `message`    TEXT NOT NULL,
  `sent_by`    INT(11) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_created` (`user_id`, `created_at`),
  CONSTRAINT `fk_warnings_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_warnings_sent_by` FOREIGN KEY (`sent_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
