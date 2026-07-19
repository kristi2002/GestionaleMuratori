-- Per-intervention work timers: a worker starts/stops a timer on a specific job,
-- giving job-level duration (and, with the worker's hourly_rate, a per-intervention
-- labor estimate). This is DISTINCT from site_attendance (the per-cantiere legal
-- clock-in that feeds the project P&L) — kept separate to avoid double-counting.
-- ended_at NULL means the timer is currently running.
CREATE TABLE intervention_time_entries (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    intervention_id BIGINT UNSIGNED NOT NULL,
    user_id         BIGINT UNSIGNED NULL,
    started_at      DATETIME NOT NULL,
    ended_at        DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_time_entries_intervention (intervention_id),
    KEY idx_time_entries_user_open (user_id, ended_at),
    CONSTRAINT fk_time_entries_intervention FOREIGN KEY (intervention_id) REFERENCES interventions (id) ON DELETE CASCADE,
    CONSTRAINT fk_time_entries_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
