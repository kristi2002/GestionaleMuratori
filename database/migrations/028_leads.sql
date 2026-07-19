-- Public "request a job" leads. Captured from the unauthenticated /request form and
-- worked in the admin inbox (new -> contacted -> converted/archived). On conversion a
-- clients row is created and linked via client_id (SET NULL if that client is later
-- deleted). Deliberately light: no auth, no stock/finance coupling.
CREATE TABLE leads (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(190) NOT NULL,
    email      VARCHAR(190) NULL,
    phone      VARCHAR(50)  NULL,
    message    TEXT NULL,
    source     VARCHAR(50)  NULL,
    status     ENUM('new','contacted','converted','archived') NOT NULL DEFAULT 'new',
    client_id  BIGINT UNSIGNED NULL,
    ip         VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_leads_status (status, created_at),
    CONSTRAINT fk_leads_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
