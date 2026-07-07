-- Project detail page (Progetti → "Apri"): attached documents and
-- issued/linked billing invoices per project.

CREATE TABLE project_documents (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id    BIGINT UNSIGNED NOT NULL,
    title         VARCHAR(150) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path     VARCHAR(255) NOT NULL,
    mime_type     VARCHAR(100) NOT NULL,
    size_bytes    INT UNSIGNED NOT NULL,
    uploaded_by   BIGINT UNSIGNED NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_project_documents_project (project_id),
    CONSTRAINT fk_project_documents_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
    CONSTRAINT fk_project_documents_user FOREIGN KEY (uploaded_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_invoices (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id  BIGINT UNSIGNED NOT NULL,
    number      VARCHAR(100) NOT NULL,
    issue_date  DATE NOT NULL,
    amount      DECIMAL(12,2) DEFAULT NULL,
    status      ENUM('draft','issued','paid') NOT NULL DEFAULT 'issued',
    note        VARCHAR(255) DEFAULT NULL,
    created_by  BIGINT UNSIGNED NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_project_invoices_project (project_id),
    CONSTRAINT fk_project_invoices_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
    CONSTRAINT fk_project_invoices_user FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
