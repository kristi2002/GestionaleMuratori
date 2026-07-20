-- Real fiscal invoice: line items with per-line IVA + Natura, plus the document-level
-- fiscal fields FatturaPA needs (TipoDocumento, ritenuta d'acconto, bollo, split payment).
-- The legacy single `amount` stays as the cached grand total so existing KPI/summary
-- queries and the simple create-from-SAL/quote flows keep working unchanged; an invoice
-- becomes "fiscal" (e-invoiceable) once it has lines and the fields below are set.
-- Statements are separated by a semicolon followed by a newline.

ALTER TABLE project_invoices
    ADD COLUMN document_type    VARCHAR(4)   NOT NULL DEFAULT 'TD01' AFTER status,
    ADD COLUMN imponibile       DECIMAL(12,2) NULL AFTER document_type,
    ADD COLUMN imposta          DECIMAL(12,2) NULL AFTER imponibile,
    ADD COLUMN ritenuta_rate    DECIMAL(5,2)  NULL AFTER imposta,
    ADD COLUMN ritenuta_amount  DECIMAL(12,2) NULL AFTER ritenuta_rate,
    ADD COLUMN ritenuta_tipo    VARCHAR(4)    NULL AFTER ritenuta_amount,
    ADD COLUMN ritenuta_causale VARCHAR(2)    NULL AFTER ritenuta_tipo,
    ADD COLUMN bollo            DECIMAL(6,2)  NULL AFTER ritenuta_causale,
    ADD COLUMN split_payment    TINYINT(1)    NOT NULL DEFAULT 0 AFTER bollo,
    ADD COLUMN payment_method   VARCHAR(4)    NOT NULL DEFAULT 'MP05' AFTER split_payment,
    ADD COLUMN payment_iban     VARCHAR(34)   NULL AFTER payment_method,
    ADD COLUMN payment_due      DATE          NULL AFTER payment_iban;

-- Per-line detail (DettaglioLinee). natura is required when vat_rate = 0
-- (e.g. reverse charge N6.x in edilizia); line_total = qty * unit_price.
CREATE TABLE invoice_lines (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_id  BIGINT UNSIGNED NOT NULL,
    description VARCHAR(255) NOT NULL,
    qty         DECIMAL(12,3) NOT NULL DEFAULT 1,
    unit        VARCHAR(20)  NULL,
    unit_price  DECIMAL(12,4) NOT NULL DEFAULT 0,
    vat_rate    DECIMAL(5,2)  NOT NULL DEFAULT 22,
    natura      VARCHAR(4)   NULL,
    line_total  DECIMAL(12,2) NOT NULL DEFAULT 0,
    sort_order  INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_invoice_lines_invoice (invoice_id),
    CONSTRAINT fk_invoice_lines_invoice FOREIGN KEY (invoice_id) REFERENCES project_invoices (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
