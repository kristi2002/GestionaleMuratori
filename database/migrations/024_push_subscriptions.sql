-- Web Push subscriptions (VAPID). One row per browser/device a user has opted into
-- push notifications on. endpoint is the push-service URL we POST to; p256dh/auth are
-- the client keys (RFC 8291) — stored now so an encrypted-payload upgrade needs no
-- migration, though the current sender uses contentless ("tickle") pushes. The unique
-- endpoint means re-subscribing the same device updates the row instead of duplicating.
-- ON DELETE CASCADE drops a user's subscriptions with the user.
CREATE TABLE push_subscriptions (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id      BIGINT UNSIGNED NOT NULL,
    endpoint     VARCHAR(500) NOT NULL,
    p256dh       VARCHAR(255) NOT NULL,
    auth         VARCHAR(255) NOT NULL,
    user_agent   VARCHAR(255) NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NULL,
    UNIQUE KEY uq_push_endpoint (endpoint(191)),
    KEY idx_push_user (user_id),
    CONSTRAINT fk_push_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
