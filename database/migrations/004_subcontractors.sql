-- Subcontractors: a new role and portal subject (portal itself lands in a later phase).
-- Statements are separated by a semicolon followed by a newline (see database/migrate.php).

CREATE TABLE subcontractors (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(190) NOT NULL,
    vat_or_tax_id   VARCHAR(50)  NULL,
    email           VARCHAR(190) NULL,
    phone           VARCHAR(50)  NULL,
    notes           TEXT NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add the subcontractor role and the optional link from a login to its company.
ALTER TABLE users
    MODIFY COLUMN role ENUM('admin','worker','client','subcontractor') NOT NULL;

ALTER TABLE users
    ADD COLUMN subcontractor_id BIGINT UNSIGNED NULL AFTER client_id,
    ADD KEY idx_users_subcontractor_id (subcontractor_id),
    ADD CONSTRAINT fk_users_subcontractor FOREIGN KEY (subcontractor_id) REFERENCES subcontractors (id) ON DELETE SET NULL;

-- M:N assignment of subcontractors to the projects they may access.
CREATE TABLE project_subcontractors (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id          BIGINT UNSIGNED NOT NULL,
    subcontractor_id    BIGINT UNSIGNED NOT NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_project_subcontractor (project_id, subcontractor_id),
    KEY idx_project_subcontractors_sub (subcontractor_id),
    CONSTRAINT fk_project_subcontractors_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
    CONSTRAINT fk_project_subcontractors_sub FOREIGN KEY (subcontractor_id) REFERENCES subcontractors (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
