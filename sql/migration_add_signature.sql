-- Add public signature field for hacker-style post footers
-- Safe to re-run: ignores error if column already exists
-- MySQL 8.x:
ALTER TABLE users
    ADD COLUMN signature TEXT NULL
    COMMENT 'Public signature shown under topics/replies'
    AFTER display_name;
