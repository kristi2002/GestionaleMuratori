-- Site attendance register (Progetti → Apri → Presenze), absence-by-default:
-- every assigned worker counts as present ("Lavorato") on every day; only the
-- exceptions — absences — are stored. Toggling a day inserts/deletes a row.
-- Replaces the per-worker calendar (worker_attendance stays for history).

CREATE TABLE project_absences (
    project_id   BIGINT UNSIGNED NOT NULL,
    user_id      BIGINT UNSIGNED NOT NULL,
    absence_date DATE NOT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (project_id, user_id, absence_date),
    KEY idx_project_absences_user (user_id),
    CONSTRAINT fk_project_absences_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
    CONSTRAINT fk_project_absences_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
