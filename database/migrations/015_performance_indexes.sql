-- Performance indexes for the status/date filters and ledger recompute paths that
-- currently table-scan. All additive; MySQL 8 has no CREATE INDEX IF NOT EXISTS, but
-- migrations run once (tracked in the migrations table), so plain CREATE INDEX is safe.
-- Statements are separated by a semicolon followed by a newline (see database/migrate.php).

-- Admin interventions list filters by status; dashboard counts open/by-status.
CREATE INDEX idx_interventions_status ON interventions (status);

-- Dashboard 14-day "completed" trend + worker "Completati" tab both range over completed_at.
CREATE INDEX idx_interventions_completed_at ON interventions (completed_at);

-- Balance recompute reads the ledger per (item, location); a composite serves it
-- directly instead of relying on the single-column item/location indexes.
CREATE INDEX idx_movements_item_location ON stock_movements (item_id, location_id);

-- The warehouse ledger detail lists movements newest-first.
CREATE INDEX idx_movements_created_at ON stock_movements (created_at);

-- Billing lists filter invoices and S.A.L. documents by workflow status.
CREATE INDEX idx_project_invoices_status ON project_invoices (status);

CREATE INDEX idx_sal_documents_status ON sal_documents (status);
