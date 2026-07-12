-- Per-user keyboard-shortcut overrides for the admin navigation shortcuts.
-- Stored as a small JSON object of {action: key} overrides on top of the
-- defaults in App\Support\Shortcuts (empty/NULL = all defaults).
ALTER TABLE users
    ADD COLUMN shortcuts TEXT NULL AFTER subcontractor_id;
