-- Database-backed sessions so a deploy/container restart no longer logs everyone
-- out (file sessions live inside the container and are wiped on redeploy). The
-- handler (App\Support\DatabaseSessionHandler) reads/writes this table; gc prunes
-- rows past the idle lifetime.
-- Statements are separated by a semicolon followed by a newline.

CREATE TABLE sessions (
    id            VARCHAR(128) NOT NULL,
    payload       MEDIUMBLOB   NOT NULL,
    last_activity INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sessions_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
