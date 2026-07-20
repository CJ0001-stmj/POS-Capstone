CREATE TABLE purchase_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_no VARCHAR(30) NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(150) NOT NULL,
    sku VARCHAR(50) NOT NULL,
    supplier_name VARCHAR(150) NOT NULL,
    quantity_requested INT NOT NULL,
    notes TEXT NULL,

    -- pending: just landed, waiting on this module's own validation pass
    -- forwarded: validated, handed off to inventory, waiting on their update
    -- fulfilled: inventory confirmed the stock update
    -- declined: inventory turned it down
    -- either resolved state still needs its outcome relayed back to the
    -- supplier - see supplier_notified_at below.
    status ENUM('pending','forwarded','fulfilled','declined') NOT NULL DEFAULT 'pending',

    requested_by_id INT NOT NULL,
    requested_by_email VARCHAR(150) NOT NULL,

    -- who on this side validated the request and pushed it to inventory
    forwarded_by_email VARCHAR(150) NULL,
    forwarded_at DATETIME NULL,

    -- inventory's answer, once it comes back
    resolution_notes VARCHAR(255) NULL,
    resolved_at DATETIME NULL,

    -- when the supplier was told the outcome
    supplier_notified_at DATETIME NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pr_status (status),
    INDEX idx_pr_requested_by (requested_by_id),
    INDEX idx_pr_product (product_id)
);

-- stock_movements already exists (reservation_create.php / stock-monitoring.php
-- use it) - if its `reason` column is an ENUM, widen it so the fulfilled-by-
-- inventory path can log 'purchase_request' too. Skip this if reason is just VARCHAR.
ALTER TABLE stock_movements
    MODIFY reason ENUM('restock','reservation','purchase_request') NOT NULL;