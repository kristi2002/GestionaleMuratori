-- Scope notifications to a recipient. Until now the feed was admin-global: every
-- row was shared by the admin role and generated only by the scheduler. A NULL
-- user_id keeps that exact behaviour (the admin/global feed), so all existing rows
-- and the scheduler are unchanged; a non-NULL user_id addresses one user (e.g. a
-- client portal user notified that a quote was sent). ON DELETE CASCADE drops a
-- user's notifications with the user.
ALTER TABLE notifications
    ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER id,
    ADD CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE;

CREATE INDEX idx_notifications_user_read ON notifications (user_id, is_read);
