-- Login rate limiting + authentication audit trail (gap S2/S10).
-- Every login attempt is recorded; failures within a sliding window block
-- further attempts for the same email or source IP.

CREATE TABLE login_attempts (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email           VARCHAR(190) NOT NULL,
    ip              VARCHAR(45) NOT NULL,
    succeeded       TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_attempts_email (email, attempted_at),
    KEY idx_login_attempts_ip (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
