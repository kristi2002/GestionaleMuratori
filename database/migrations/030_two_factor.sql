-- Two-factor authentication (TOTP, RFC 6238). totp_secret is the base32 shared
-- secret; totp_enabled gates the login second step. Recovery codes (one-time,
-- sha256-hashed) let a user in if they lose their authenticator device.
ALTER TABLE users
    ADD COLUMN totp_secret VARCHAR(64) NULL,
    ADD COLUMN totp_enabled TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE user_recovery_codes (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    BIGINT UNSIGNED NOT NULL,
    code_hash  CHAR(64) NOT NULL,
    used_at    DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_recovery_user (user_id),
    CONSTRAINT fk_recovery_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
