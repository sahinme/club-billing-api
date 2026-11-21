-- Runs once, when the database volume is created.
-- A separate database for the automated tests, so tests never touch the demo data.
CREATE DATABASE IF NOT EXISTS club_billing_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON club_billing_test.* TO 'club_billing'@'%';
