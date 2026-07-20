-- Tracciabilità dei flussi finanziari (Legge 136/2010): public-contract codes.
-- CIG (Codice Identificativo Gara, 10 chars) and CUP (Codice Unico di Progetto,
-- 15 chars) live on the project (the contract) and are carried onto each invoice;
-- the FatturaPA builder emits them under DatiOrdineAcquisto / DatiContratto.
-- Statements are separated by a semicolon followed by a newline.

ALTER TABLE projects
    ADD COLUMN cig VARCHAR(15) NULL AFTER invoice_reference,
    ADD COLUMN cup VARCHAR(15) NULL AFTER cig;

ALTER TABLE project_invoices
    ADD COLUMN cig VARCHAR(15) NULL AFTER number,
    ADD COLUMN cup VARCHAR(15) NULL AFTER cig;
