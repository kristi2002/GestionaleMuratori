-- Fiscal foundation for electronic invoicing (FatturaPA / SdI).
-- Adds the structured fiscal identity FatturaPA requires:
--   * company_settings — the CedentePrestatore (seller) profile, single row (id=1);
--   * clients          — CessionarioCommittente (buyer) fields: P.IVA vs C.F. split,
--                        SdI routing (codice destinatario / PEC), and address parts.
-- Statements are separated by a semicolon followed by a newline (see database/migrate.php).

-- Seller identity printed on invoices and serialized into the FatturaPA header.
-- Single-row table (id is fixed at 1); an admin edits it under "Dati Azienda".
CREATE TABLE company_settings (
    id                BIGINT UNSIGNED NOT NULL,
    denominazione     VARCHAR(190) NOT NULL DEFAULT '',
    partita_iva       VARCHAR(11)  NULL,
    codice_fiscale    VARCHAR(16)  NULL,
    regime_fiscale    VARCHAR(4)   NOT NULL DEFAULT 'RF01',
    indirizzo         VARCHAR(190) NULL,
    numero_civico     VARCHAR(20)  NULL,
    cap               VARCHAR(10)  NULL,
    comune            VARCHAR(120) NULL,
    provincia         VARCHAR(2)   NULL,
    nazione           VARCHAR(2)   NOT NULL DEFAULT 'IT',
    telefono          VARCHAR(50)  NULL,
    email             VARCHAR(190) NULL,
    pec               VARCHAR(190) NULL,
    -- IscrizioneREA (mandatory for S.r.l./S.p.A.): office province + REA number.
    rea_ufficio       VARCHAR(2)   NULL,
    rea_numero        VARCHAR(20)  NULL,
    capitale_sociale  DECIMAL(14,2) NULL,
    socio_unico       ENUM('SU','SM') NULL,
    stato_liquidazione ENUM('LS','LN') NOT NULL DEFAULT 'LN',
    iban              VARCHAR(34)  NULL,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed the single settings row so the model can always UPDATE it.
INSERT INTO company_settings (id, denominazione) VALUES (1, '');

-- Buyer (CessionarioCommittente) fiscal fields. vat_or_tax_id is kept as the legacy
-- free-text display field; partita_iva / codice_fiscale are the precise values the
-- XML builder reads. client_kind drives which identifier and address block apply.
ALTER TABLE clients
    ADD COLUMN client_kind         ENUM('business','private','pa') NOT NULL DEFAULT 'business' AFTER name,
    ADD COLUMN partita_iva         VARCHAR(11)  NULL AFTER vat_or_tax_id,
    ADD COLUMN codice_fiscale      VARCHAR(16)  NULL AFTER partita_iva,
    ADD COLUMN codice_destinatario VARCHAR(7)   NULL AFTER codice_fiscale,
    ADD COLUMN pec                 VARCHAR(190) NULL AFTER codice_destinatario,
    ADD COLUMN cap                 VARCHAR(10)  NULL AFTER address,
    ADD COLUMN comune              VARCHAR(120) NULL AFTER cap,
    ADD COLUMN provincia           VARCHAR(2)   NULL AFTER comune,
    ADD COLUMN nazione             VARCHAR(2)   NOT NULL DEFAULT 'IT' AFTER provincia;

-- Backfill the split identifiers from the legacy combined field: 11 digits → P.IVA,
-- a 16-char alphanumeric → codice fiscale. Anything else is left for manual entry.
UPDATE clients
   SET partita_iva = REPLACE(vat_or_tax_id, ' ', '')
 WHERE vat_or_tax_id REGEXP '^[[:space:]]*[0-9]{11}[[:space:]]*$';

UPDATE clients
   SET codice_fiscale = UPPER(REPLACE(vat_or_tax_id, ' ', ''))
 WHERE vat_or_tax_id REGEXP '^[[:space:]]*[A-Za-z0-9]{16}[[:space:]]*$';
