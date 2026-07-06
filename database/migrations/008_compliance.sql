-- Scadenzario Sicurezza: safety/compliance documents with expiry tracking.
-- Statements are separated by a semicolon followed by a newline (see database/migrate.php).
-- subject_type/subject_id is a polymorphic reference (worker/company/subcontractor/project);
-- no FK because the target table varies by subject_type.

CREATE TABLE compliance_documents (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    subject_type    ENUM('worker','company','subcontractor','project') NOT NULL,
    subject_id      BIGINT UNSIGNED NULL,
    doc_type        ENUM('DURC','POS','PSC','patente_crediti','visita_medica','formazione','assicurazione','other') NOT NULL,
    reference       VARCHAR(190) NULL,
    issue_date      DATE NULL,
    expiry_date     DATE NULL,
    credits         INT NULL,
    file_path       VARCHAR(255) NULL,
    notes           TEXT NULL,
    created_by      BIGINT UNSIGNED NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_compliance_expiry (expiry_date),
    KEY idx_compliance_subject (subject_type, subject_id),
    CONSTRAINT fk_compliance_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
