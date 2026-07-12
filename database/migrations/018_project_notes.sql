-- Per-project reminders / notes ("Promemoria") shown on the project detail page.
CREATE TABLE project_notes (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id  BIGINT UNSIGNED NOT NULL,
    body        VARCHAR(500) NOT NULL,
    due_date    DATE NULL,
    done        TINYINT(1) NOT NULL DEFAULT 0,
    created_by  BIGINT UNSIGNED NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_project_notes_project (project_id),
    CONSTRAINT fk_project_notes_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
    CONSTRAINT fk_project_notes_user   FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
