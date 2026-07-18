-- RAM-YUM STORE - login schema
-- Run this against your MariaDB/MySQL server (e.g. via phpMyAdmin > Import,
-- or `mysql -u root -p mms < schema.sql`).

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `mms` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `mms`;

-- --------------------------------------------------------
-- Table: users
-- Holds real login credentials. Passwords are NEVER stored in plain text -
-- `password_hash` is generated with PHP's password_hash() (bcrypt).
-- --------------------------------------------------------

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'manager', 'cashier') NOT NULL DEFAULT 'cashier',
  `failed_attempts` INT(11) NOT NULL DEFAULT 0,
  `locked_until` DATETIME NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Seed accounts — CHANGE THESE PASSWORDS after first login.
-- Hashes below made with bcrypt, cost 10 (same algo PHP password_hash()
-- uses) — password_verify() in login.php reads em fine.
--
--   cj@gmail.con  / cj123   (admin)   <-- heads up, ".con" not ".com", fix if typo
--   cj2@gmail.com / cj456   (cashier)
-- --------------------------------------------------------
INSERT INTO `users` (`email`, `password_hash`, `role`) VALUES
('cj@gmail.com',  '$2b$10$hk.8sWnqskzHmy0GQiuhHuXIeIOk2MSBaMi.2Py4itLd16VJi/b0i', 'admin'),
('cj2@gmail.com', '$2b$10$iGmx8ypqPQOYsal/1Auqae4MM1v8U318shgdSGhja1q8YFnqu5jkK', 'cashier');
-- One row per login attempt (success or failure). Append-only -
-- the API never updates or deletes rows here. Passwords are never
-- written to this table, only the outcome.
-- --------------------------------------------------------

DROP TABLE IF EXISTS `login_audit`;
CREATE TABLE `login_audit` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `success` TINYINT(1) NOT NULL,
  `reason` ENUM(
    'success',
    'invalid_email_format',
    'user_not_found',
    'invalid_password',
    'account_locked'
  ) NOT NULL,
  `ip_address` VARCHAR(45) NULL DEFAULT NULL,
  `user_agent` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email_created` (`email`, `created_at`),
  KEY `idx_success_created` (`success`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Already have a `mms` DB with data you don't want to drop? Run this
-- instead of the DROP/CREATE `users` block above:
--
--   ALTER TABLE `users`
--     ADD COLUMN `role` ENUM('admin','manager','cashier') NOT NULL DEFAULT 'cashier'
--     AFTER `password_hash`;
--   UPDATE `users` SET `role` = 'cashier'; -- then hand-set admins/managers
--
-- Shift sales on the cashier dashboard (dashboard.php) filter `sales` by
-- `cashier_id`. If your `sales` table uses a different column name for
-- "which staff member rang this up" (e.g. `user_id`), update the query
-- in dashboard.php to match, or add the column:
--
--   ALTER TABLE `sales` ADD COLUMN `cashier_id` INT(11) NULL AFTER `id`,
--     ADD CONSTRAINT `fk_sales_cashier` FOREIGN KEY (`cashier_id`) REFERENCES `users`(`id`);
-- --------------------------------------------------------

COMMIT;