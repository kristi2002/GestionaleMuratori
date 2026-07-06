-- Giornale dei Lavori: per-project daily log with weather, equipment, and closure lock.
-- Statements are separated by a semicolon followed by a newline (see database/migrate.php).

CREATE TABLE daily_logs (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id          BIGINT UNSIGNED NOT NULL,
    log_date            DATE NOT NULL,
    weather_text        VARCHAR(190) NULL,
    weather_code        INT NULL,
    temp_min            DECIMAL(5,2) NULL,
    temp_max            DECIMAL(5,2) NULL,
    workers_present     INT UNSIGNED NULL,
    work_done           TEXT NULL,
    notes               TEXT NULL,
    created_by          BIGINT UNSIGNED NOT NULL,
    is_closed           TINYINT(1) NOT NULL DEFAULT 0,
    closed_at           DATETIME NULL,
    closed_by           BIGINT UNSIGNED NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_daily_log_project_date (project_id, log_date),
    KEY idx_daily_logs_created_by (created_by),
    CONSTRAINT fk_daily_logs_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
    CONSTRAINT fk_daily_logs_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT fk_daily_logs_closed_by FOREIGN KEY (closed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE equipment (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(190) NOT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE daily_log_equipment (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    daily_log_id    BIGINT UNSIGNED NOT NULL,
    equipment_id    BIGINT UNSIGNED NOT NULL,
    note            VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_daily_log_equipment (daily_log_id, equipment_id),
    KEY idx_daily_log_equipment_equipment (equipment_id),
    CONSTRAINT fk_daily_log_equipment_log FOREIGN KEY (daily_log_id) REFERENCES daily_logs (id) ON DELETE CASCADE,
    CONSTRAINT fk_daily_log_equipment_equipment FOREIGN KEY (equipment_id) REFERENCES equipment (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
