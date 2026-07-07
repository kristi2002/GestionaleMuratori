-- Sidebar sections "Preventivi" and "Spese":
--   quotes / quote_lines — estimates with line items, printable as PDF;
--   expenses — running costs outside materials (worker meals, fuel,
--   vehicle upkeep, work clothing, other).

CREATE TABLE quotes (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id   BIGINT UNSIGNED NOT NULL,
    project_id  BIGINT UNSIGNED NULL,
    number      VARCHAR(100) NOT NULL,
    title       VARCHAR(190) NOT NULL,
    quote_date  DATE NOT NULL,
    valid_until DATE NULL,
    status      ENUM('draft','sent','accepted','rejected','expired') NOT NULL DEFAULT 'draft',
    vat_rate    DECIMAL(5,2) NOT NULL DEFAULT 22.00,
    notes       TEXT NULL,
    created_by  BIGINT UNSIGNED NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_quotes_client (client_id),
    KEY idx_quotes_project (project_id),
    KEY idx_quotes_status (status),
    CONSTRAINT fk_quotes_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE,
    CONSTRAINT fk_quotes_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE SET NULL,
    CONSTRAINT fk_quotes_user FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quote_lines (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    quote_id    BIGINT UNSIGNED NOT NULL,
    description VARCHAR(255) NOT NULL,
    qty         DECIMAL(12,3) NOT NULL DEFAULT 1,
    unit        VARCHAR(20) NULL,
    unit_price  DECIMAL(12,2) NOT NULL DEFAULT 0,
    sort_order  INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_quote_lines_quote (quote_id),
    CONSTRAINT fk_quote_lines_quote FOREIGN KEY (quote_id) REFERENCES quotes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE expenses (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    expense_date DATE NOT NULL,
    category     ENUM('meals','fuel','vehicle','clothing','other') NOT NULL,
    description  VARCHAR(255) NOT NULL,
    amount       DECIMAL(12,2) NOT NULL,
    worker_id    BIGINT UNSIGNED NULL,
    project_id   BIGINT UNSIGNED NULL,
    note         VARCHAR(255) NULL,
    created_by   BIGINT UNSIGNED NOT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_expenses_date (expense_date),
    KEY idx_expenses_category (category),
    KEY idx_expenses_worker (worker_id),
    KEY idx_expenses_project (project_id),
    CONSTRAINT fk_expenses_worker FOREIGN KEY (worker_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_expenses_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE SET NULL,
    CONSTRAINT fk_expenses_user FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
