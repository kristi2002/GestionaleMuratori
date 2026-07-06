-- Badge di Cantiere Digitale: on-site attendance with geolocation (feature lands later).
-- Statements are separated by a semicolon followed by a newline (see database/migrate.php).

CREATE TABLE site_attendance (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id          BIGINT UNSIGNED NOT NULL,
    user_id             BIGINT UNSIGNED NULL,
    subcontractor_id    BIGINT UNSIGNED NULL,
    person_name         VARCHAR(190) NOT NULL,
    entry_at            DATETIME NOT NULL,
    exit_at             DATETIME NULL,
    entry_lat           DECIMAL(10,7) NULL,
    entry_lng           DECIMAL(10,7) NULL,
    exit_lat            DECIMAL(10,7) NULL,
    exit_lng            DECIMAL(10,7) NULL,
    note                VARCHAR(255) NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_attendance_project_entry (project_id, entry_at),
    KEY idx_attendance_user_entry (user_id, entry_at),
    KEY idx_attendance_subcontractor (subcontractor_id),
    CONSTRAINT fk_attendance_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
    CONSTRAINT fk_attendance_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_attendance_subcontractor FOREIGN KEY (subcontractor_id) REFERENCES subcontractors (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
