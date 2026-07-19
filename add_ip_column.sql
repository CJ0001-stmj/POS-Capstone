-- Adds IP tracking to login_audit for security review.
-- Run once: mysql -u root -p mms < add_ip_column.sql

USE `mms`;

ALTER TABLE `login_audit`
  ADD COLUMN `ip_address` VARCHAR(45) NULL DEFAULT NULL AFTER `email`
      COMMENT 'Client IP at login attempt time (IPv4 or IPv6). NULL for rows recorded before this column existed.';
