-- Extra profile fields for the operaio/user detail page: job title, contact
-- phone, hire date (drives tenure), and an optional avatar image. The avatar is
-- stored via the Storage disk and served only through a permission-checked
-- controller (never as a static file), like photos and signatures.
ALTER TABLE users
    ADD COLUMN job_title   VARCHAR(120) NULL AFTER name,
    ADD COLUMN phone       VARCHAR(40)  NULL AFTER email,
    ADD COLUMN hire_date   DATE         NULL AFTER phone,
    ADD COLUMN avatar_path VARCHAR(255) NULL AFTER hire_date;
