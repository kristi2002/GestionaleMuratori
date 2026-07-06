-- Multi-site inventory: stock locations, location-aware ledger, per-location balances.
-- Statements are separated by a semicolon followed by a newline (see database/migrate.php).
-- Keep every comment on its own line: the runner only strips full-line -- comments.

CREATE TABLE stock_locations (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(190) NOT NULL,
    kind        ENUM('warehouse','site') NOT NULL DEFAULT 'site',
    project_id  BIGINT UNSIGNED NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_stock_locations_project (project_id),
    CONSTRAINT fk_stock_locations_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The default main warehouse. id=1 is the implicit location of every pre-existing
-- movement and the value that warehouse_items.qty_in_stock tracks.
INSERT INTO stock_locations (id, name, kind, project_id, is_active)
VALUES (1, 'Magazzino Centrale', 'warehouse', NULL, 1);

-- Location-aware ledger. Existing rows default to the main warehouse (id=1).
ALTER TABLE stock_movements
    ADD COLUMN location_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER item_id,
    ADD KEY idx_movements_location (location_id),
    ADD CONSTRAINT fk_movements_location FOREIGN KEY (location_id) REFERENCES stock_locations (id) ON DELETE RESTRICT;

-- Transfer movements move stock between locations: transfer_out (source) / transfer_in (destination).
ALTER TABLE stock_movements
    MODIFY COLUMN type ENUM('in','out','reserve','release','adjustment','transfer_in','transfer_out') NOT NULL;

-- Per-(item, location) balance cache. Analogue of warehouse_items.qty_in_stock,
-- recomputed from the ledger; never written without a matching movement row.
CREATE TABLE stock_balances (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_id     BIGINT UNSIGNED NOT NULL,
    location_id BIGINT UNSIGNED NOT NULL,
    qty         DECIMAL(12,3) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_stock_balances_item_location (item_id, location_id),
    KEY idx_stock_balances_location (location_id),
    CONSTRAINT fk_stock_balances_item FOREIGN KEY (item_id) REFERENCES warehouse_items (id) ON DELETE CASCADE,
    CONSTRAINT fk_stock_balances_location FOREIGN KEY (location_id) REFERENCES stock_locations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional unit cost for accountant export and S.A.L. pricing (added later phases).
ALTER TABLE warehouse_items
    ADD COLUMN unit_cost DECIMAL(12,4) NULL AFTER reorder_level;

-- Backfill balances from the existing ledger, per (item, location).
-- Sign convention mirrors WarehouseItemModel::recomputeStock (transfer_in/out included).
INSERT INTO stock_balances (item_id, location_id, qty)
SELECT item_id, location_id, COALESCE(SUM(CASE
        WHEN type IN ('in', 'release', 'transfer_in') THEN qty
        WHEN type IN ('reserve', 'transfer_out') THEN -qty
        WHEN type = 'adjustment' THEN qty
        ELSE 0
    END), 0)
FROM stock_movements
GROUP BY item_id, location_id;
