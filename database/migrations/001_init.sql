-- Schema for the Field Service & Construction Management System (spec section 3).
-- All tables InnoDB / utf8mb4. ENUM values stay in English (translated in the view layer).
-- Statements are separated by a semicolon followed by a newline.

CREATE TABLE clients (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(190) NOT NULL,
    vat_or_tax_id   VARCHAR(50)  NULL,
    email           VARCHAR(190) NULL,
    phone           VARCHAR(50)  NULL,
    address         VARCHAR(255) NULL,
    notes           TEXT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(190) NOT NULL,
    email           VARCHAR(190) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    role            ENUM('admin','worker','client') NOT NULL,
    client_id       BIGINT UNSIGNED NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_client_id (client_id),
    CONSTRAINT fk_users_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE projects (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id           BIGINT UNSIGNED NOT NULL,
    name                VARCHAR(190) NOT NULL,
    location            VARCHAR(255) NULL,
    lat                 DECIMAL(10,7) NULL,
    lng                 DECIMAL(10,7) NULL,
    start_date          DATE NOT NULL,
    end_date            DATE NULL,
    invoice_reference   VARCHAR(100) NULL,
    status              ENUM('active','on_hold','closed') NOT NULL DEFAULT 'active',
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_projects_client_id (client_id),
    CONSTRAINT fk_projects_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE warehouse_items (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(190) NOT NULL,
    sku             VARCHAR(80) NULL,
    unit            ENUM('pcs','kg','m','l','box') NOT NULL DEFAULT 'pcs',
    qty_in_stock    DECIMAL(12,3) NOT NULL DEFAULT 0,
    reorder_level   DECIMAL(12,3) NOT NULL DEFAULT 0,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_warehouse_sku (sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE interventions (
    id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id              BIGINT UNSIGNED NOT NULL,
    assigned_worker_id      BIGINT UNSIGNED NULL,
    title                   VARCHAR(190) NOT NULL,
    description             TEXT NULL,
    scheduled_date          DATE NULL,
    scheduled_start_time    TIME NULL,
    status                  ENUM('pending','in_progress','on_hold','completed','cancelled') NOT NULL DEFAULT 'pending',
    started_at              DATETIME NULL,
    completed_at            DATETIME NULL,
    client_signature_path   VARCHAR(255) NULL,
    completion_notes        TEXT NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_interventions_project_id (project_id),
    KEY idx_interventions_worker_id (assigned_worker_id),
    KEY idx_interventions_scheduled_date (scheduled_date),
    CONSTRAINT fk_interventions_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
    CONSTRAINT fk_interventions_worker FOREIGN KEY (assigned_worker_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE intervention_status_history (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    intervention_id     BIGINT UNSIGNED NOT NULL,
    from_status         ENUM('pending','in_progress','on_hold','completed','cancelled') NULL,
    to_status           ENUM('pending','in_progress','on_hold','completed','cancelled') NOT NULL,
    changed_by          BIGINT UNSIGNED NOT NULL,
    changed_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_status_history_intervention (intervention_id),
    CONSTRAINT fk_status_history_intervention FOREIGN KEY (intervention_id) REFERENCES interventions (id) ON DELETE CASCADE,
    CONSTRAINT fk_status_history_user FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE intervention_materials (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    intervention_id     BIGINT UNSIGNED NOT NULL,
    item_id             BIGINT UNSIGNED NOT NULL,
    qty_planned         DECIMAL(12,3) NOT NULL,
    qty_used            DECIMAL(12,3) NULL,
    is_reserved         TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_materials_intervention (intervention_id),
    KEY idx_materials_item (item_id),
    CONSTRAINT fk_materials_intervention FOREIGN KEY (intervention_id) REFERENCES interventions (id) ON DELETE CASCADE,
    CONSTRAINT fk_materials_item FOREIGN KEY (item_id) REFERENCES warehouse_items (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_movements (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_id             BIGINT UNSIGNED NOT NULL,
    type                ENUM('in','out','reserve','release','adjustment') NOT NULL,
    qty                 DECIMAL(12,3) NOT NULL,
    intervention_id     BIGINT UNSIGNED NULL,
    user_id             BIGINT UNSIGNED NOT NULL,
    note                VARCHAR(255) NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_movements_item (item_id),
    KEY idx_movements_intervention (intervention_id),
    CONSTRAINT fk_movements_item FOREIGN KEY (item_id) REFERENCES warehouse_items (id) ON DELETE RESTRICT,
    CONSTRAINT fk_movements_intervention FOREIGN KEY (intervention_id) REFERENCES interventions (id) ON DELETE SET NULL,
    CONSTRAINT fk_movements_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE photos (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    intervention_id     BIGINT UNSIGNED NOT NULL,
    project_id          BIGINT UNSIGNED NOT NULL,
    type                ENUM('before','during','after') NOT NULL,
    file_path           VARCHAR(255) NOT NULL,
    thumb_path          VARCHAR(255) NULL,
    uploaded_by         BIGINT UNSIGNED NOT NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_photos_intervention (intervention_id),
    KEY idx_photos_project (project_id),
    CONSTRAINT fk_photos_intervention FOREIGN KEY (intervention_id) REFERENCES interventions (id) ON DELETE CASCADE,
    CONSTRAINT fk_photos_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
    CONSTRAINT fk_photos_user FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
