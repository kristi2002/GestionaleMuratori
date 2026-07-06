-- Geolocated photo evidence: capture coordinates and time on uploaded photos (feature lands later).
-- Statements are separated by a semicolon followed by a newline (see database/migrate.php).

ALTER TABLE photos
    ADD COLUMN lat DECIMAL(10,7) NULL AFTER thumb_path,
    ADD COLUMN lng DECIMAL(10,7) NULL AFTER lat,
    ADD COLUMN captured_at DATETIME NULL AFTER lng;
