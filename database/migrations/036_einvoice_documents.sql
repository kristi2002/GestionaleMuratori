-- Fatturazione elettronica lifecycle ledger (conservazione / audit): one row per
-- invoice recording the generated XML, the optional signed .p7m, the transmission
-- status and any SdI identifier/receipt. The stored file paths point into the
-- Storage disk. Certified 10-year conservazione is the provider's; this keeps the
-- firm's own durable record and drives the invoice's e-invoice status badge.
-- Statements are separated by a semicolon followed by a newline.

CREATE TABLE einvoice_documents (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_id     BIGINT UNSIGNED NOT NULL,
    format         VARCHAR(5)   NOT NULL DEFAULT 'FPR12',
    progressivo    VARCHAR(10)  NULL,
    status         ENUM('generated','signed','sent','delivered','rejected','error') NOT NULL DEFAULT 'generated',
    xml_path       VARCHAR(255) NULL,
    signed_path    VARCHAR(255) NULL,
    sdi_identifier VARCHAR(60)  NULL,
    message        TEXT NULL,
    prepared_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_einvoice_invoice (invoice_id),
    CONSTRAINT fk_einvoice_invoice FOREIGN KEY (invoice_id) REFERENCES project_invoices (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
