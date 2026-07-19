CREATE TABLE intervention_tasks (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    intervention_id BIGINT UNSIGNED NOT NULL,
    label           VARCHAR(255) NOT NULL,
    is_done         TINYINT(1) NOT NULL DEFAULT 0,
    position        INT NOT NULL DEFAULT 0,
    done_by         BIGINT UNSIGNED NULL,
    done_at         DATETIME NULL,
    created_by      BIGINT UNSIGNED NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_intervention_tasks_intervention (intervention_id),
    CONSTRAINT fk_intervention_tasks_intervention FOREIGN KEY (intervention_id) REFERENCES interventions (id) ON DELETE CASCADE,
    CONSTRAINT fk_intervention_tasks_done_by FOREIGN KEY (done_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_intervention_tasks_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
