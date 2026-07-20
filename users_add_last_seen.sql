-- RAM-YUM STORE - migration: heartbeat column for accurate, real-time
-- "Cashier active status" on the dashboard. Run once:
--   mysql -u root -p mms < users_add_last_seen.sql
--
-- Without this, "active" could only mean "logged in at some point
-- today" (login_audit has no logout event), which stayed green all day
-- even after someone left. last_seen is refreshed on every authenticated
-- page load (see the idle-timeout check block in dashboard.php), so it
-- reflects real, current activity instead of just the login moment.

USE `mms`;

ALTER TABLE `users`
  ADD COLUMN `last_seen` DATETIME NULL DEFAULT NULL
    COMMENT 'Heartbeat — refreshed on every authenticated page load, used to tell if someone is really active right now vs. just logged in earlier today'
    AFTER `locked_until`;

CREATE INDEX `idx_users_last_seen` ON `users` (`last_seen`);
