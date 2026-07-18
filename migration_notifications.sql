-- RAM-YUM STORE — additive migration for notification bell
-- Run AFTER migration_staff_fields.sql (needs staff_warnings to exist).
--
--   mysql -u root -p mms < migration_notifications.sql

USE `mms`;

-- read_at stays NULL until the staff member opens the notification bell —
-- that's what the unread badge count and unread highlight are based on.
ALTER TABLE `staff_warnings`
    ADD COLUMN `read_at` DATETIME NULL DEFAULT NULL AFTER `sent_by`;
