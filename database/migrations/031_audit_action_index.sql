-- audit_log grows one row per data mutation — the fastest-growing table. The admin
-- audit view filters WHERE action = ? and sorts by created_at, and the per-action
-- count pills GROUP BY action. This composite serves both so they stop table-scanning
-- as the log grows.
CREATE INDEX idx_audit_action_created ON audit_log (action, created_at);
