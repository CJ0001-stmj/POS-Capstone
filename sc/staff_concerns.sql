-- RAM-YUM STORE - Staff Concerns Inbox schema
-- Additive migration on top of pos_db.sql / promotion_module.sql. Run once
-- against the same `mms` database:
--   mysql -u root -p mms < staff_concerns.sql

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

USE `mms`;

-- --------------------------------------------------------
-- Table: staff_concerns
-- Any logged-in staff member can submit a concern/complaint/report.
-- Admins/managers triage it from the Audit & Access > Inbox page.
-- `submitted_by` snapshots the email too, so the concern still reads
-- fine even if the user account is later deleted.
-- --------------------------------------------------------
DROP TABLE IF EXISTS `staff_concerns`;
CREATE TABLE `staff_concerns` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `submitted_by` INT(11) NULL DEFAULT NULL,
  `submitted_by_email` VARCHAR(255) NOT NULL,
  `subject` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `status` ENUM('open','in_review','resolved') NOT NULL DEFAULT 'open',
  `resolved_by` INT(11) NULL DEFAULT NULL,
  `resolution_notes` VARCHAR(500) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_concerns_status_created` (`status`, `created_at`),
  KEY `idx_concerns_submitted_by` (`submitted_by`),
  CONSTRAINT `fk_concerns_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_concerns_resolved_by` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;
