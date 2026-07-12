-- Lightweight audit trail: who did what, when. user_name/ip are snapshots so the
-- record survives even if the user is later removed (hence no FK on user_id).
CREATE TABLE audit_log (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    BIGINT UNSIGNED NULL,
    user_name  VARCHAR(190) NULL,
    action     VARCHAR(40) NOT NULL,
    entity     VARCHAR(40) NOT NULL,
    entity_id  BIGINT UNSIGNED NULL,
    summary    VARCHAR(255) NULL,
    ip         VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_created (created_at),
    KEY idx_audit_entity (entity, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
