-- Buoni d'Ordine (purchase orders): the first supplier-facing document set.
-- suppliers — the counterparty (materials vendors), kept separate from subcontractors.
-- purchase_orders / purchase_order_lines — a numbered order with line items, printable
--   as PDF and (Phase 2) receivable into stock. project_id ties the order to a cantiere
--   from day one so per-site material cost can be reported.
-- Statements are separated by a semicolon followed by a newline (see database/migrate.php).
-- Keep every comment on its own line: the runner only strips full-line -- comments.

CREATE TABLE suppliers (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(190) NOT NULL,
    vat_or_tax_id   VARCHAR(50)  NULL,
    email           VARCHAR(190) NULL,
    phone           VARCHAR(50)  NULL,
    address         VARCHAR(255) NULL,
    notes           TEXT NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_orders (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    supplier_id   BIGINT UNSIGNED NOT NULL,
    project_id    BIGINT UNSIGNED NULL,
    location_id   BIGINT UNSIGNED NOT NULL DEFAULT 1,
    number        VARCHAR(100) NOT NULL,
    title         VARCHAR(190) NOT NULL,
    order_date    DATE NOT NULL,
    expected_date DATE NULL,
    status        ENUM('draft','sent','confirmed','partially_received','received','cancelled') NOT NULL DEFAULT 'draft',
    vat_rate      DECIMAL(5,2) NOT NULL DEFAULT 22.00,
    notes         TEXT NULL,
    created_by    BIGINT UNSIGNED NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_purchase_orders_supplier (supplier_id),
    KEY idx_purchase_orders_project (project_id),
    KEY idx_purchase_orders_status (status),
    CONSTRAINT fk_purchase_orders_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE CASCADE,
    CONSTRAINT fk_purchase_orders_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE SET NULL,
    CONSTRAINT fk_purchase_orders_location FOREIGN KEY (location_id) REFERENCES stock_locations (id) ON DELETE RESTRICT,
    CONSTRAINT fk_purchase_orders_user FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_order_lines (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    purchase_order_id BIGINT UNSIGNED NOT NULL,
    item_id           BIGINT UNSIGNED NULL,
    description       VARCHAR(255) NOT NULL,
    qty               DECIMAL(12,3) NOT NULL DEFAULT 1,
    unit              VARCHAR(20) NULL,
    unit_price        DECIMAL(12,2) NOT NULL DEFAULT 0,
    sort_order        INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_po_lines_order (purchase_order_id),
    KEY idx_po_lines_item (item_id),
    CONSTRAINT fk_po_lines_order FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_po_lines_item FOREIGN KEY (item_id) REFERENCES warehouse_items (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Receiving a delivery writes a type='in' movement per line; this column ties that
-- inbound stock back to the ordering document. qty received is always summed from
-- these ledger rows, never cached on the line (the ledger stays the source of truth).
ALTER TABLE stock_movements
    ADD COLUMN purchase_order_line_id BIGINT UNSIGNED NULL AFTER intervention_id,
    ADD KEY idx_movements_po_line (purchase_order_line_id),
    ADD CONSTRAINT fk_movements_po_line FOREIGN KEY (purchase_order_line_id) REFERENCES purchase_order_lines (id) ON DELETE SET NULL;
