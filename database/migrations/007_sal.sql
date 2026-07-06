-- Generatore di S.A.L. (Stato Avanzamento Lavori): progress documents and line items.
-- Statements are separated by a semicolon followed by a newline (see database/migrate.php).

CREATE TABLE sal_documents (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id      BIGINT UNSIGNED NOT NULL,
    number          INT UNSIGNED NOT NULL,
    period_from     DATE NULL,
    period_to       DATE NULL,
    description     TEXT NULL,
    amount          DECIMAL(12,2) NOT NULL DEFAULT 0,
    status          ENUM('draft','issued','signed') NOT NULL DEFAULT 'draft',
    pdf_path        VARCHAR(255) NULL,
    signature_path  VARCHAR(255) NULL,
    created_by      BIGINT UNSIGNED NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    issued_at       DATETIME NULL,
    signed_at       DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sal_project_number (project_id, number),
    KEY idx_sal_created_by (created_by),
    CONSTRAINT fk_sal_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
    CONSTRAINT fk_sal_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sal_lines (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sal_id          BIGINT UNSIGNED NOT NULL,
    description     VARCHAR(255) NOT NULL,
    qty             DECIMAL(12,3) NOT NULL DEFAULT 0,
    unit            VARCHAR(20) NULL,
    unit_price      DECIMAL(12,4) NOT NULL DEFAULT 0,
    amount          DECIMAL(12,2) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_sal_lines_sal (sal_id),
    CONSTRAINT fk_sal_lines_sal FOREIGN KEY (sal_id) REFERENCES sal_documents (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
