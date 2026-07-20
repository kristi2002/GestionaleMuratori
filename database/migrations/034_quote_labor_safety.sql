-- Preventivi: explicit costo manodopera + oneri della sicurezza breakout.
-- D.Lgs. 36/2023 art. 41 c.14 requires labour cost and safety charges to be
-- stated separately (and excluded from the discount base) in public tenders; it
-- is also established practice in private quotes and feeds the DURC di congruità.
-- Statements are separated by a semicolon followed by a newline.

ALTER TABLE quotes
    ADD COLUMN costo_manodopera DECIMAL(12,2) NULL AFTER vat_rate,
    ADD COLUMN oneri_sicurezza  DECIMAL(12,2) NULL AFTER costo_manodopera;
