-- Materials logged directly on a project (Progetti → "Apri" → Materiali),
-- alongside the ones recorded through interventions.

CREATE TABLE project_materials (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id  BIGINT UNSIGNED NOT NULL,
    item_id     BIGINT UNSIGNED NOT NULL,
    qty         DECIMAL(12,3) NOT NULL,
    note        VARCHAR(255) DEFAULT NULL,
    created_by  BIGINT UNSIGNED NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_project_materials_project (project_id),
    KEY idx_project_materials_item (item_id),
    CONSTRAINT fk_project_materials_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
    CONSTRAINT fk_project_materials_item FOREIGN KEY (item_id) REFERENCES warehouse_items (id),
    CONSTRAINT fk_project_materials_user FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
