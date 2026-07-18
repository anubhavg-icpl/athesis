-- Phase 3 + 4: growth & scale
USE php_forum;

-- Series
CREATE TABLE IF NOT EXISTS blog_series (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL UNIQUE,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Post series + premium + part order
ALTER TABLE blog_posts
    ADD COLUMN series_id INT NULL AFTER category_id,
    ADD COLUMN series_order INT NOT NULL DEFAULT 0 AFTER series_id,
    ADD COLUMN is_premium TINYINT(1) NOT NULL DEFAULT 0 AFTER featured_image;

-- Newsletter
CREATE TABLE IF NOT EXISTS blog_subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    unsubscribed_at TIMESTAMP NULL
);

-- Membership (simple flag on users)
ALTER TABLE users
    ADD COLUMN is_member TINYINT(1) NOT NULL DEFAULT 0 AFTER user_role;

-- Expand roles with author (keep user as general)
ALTER TABLE users
    MODIFY COLUMN user_role ENUM('user', 'author', 'moderator', 'admin') NOT NULL DEFAULT 'user';

-- Comments: default pending for moderation (existing may stay approved)
ALTER TABLE blog_comments
    MODIFY COLUMN is_approved TINYINT(1) NOT NULL DEFAULT 0;

-- Contact messages
CREATE TABLE IF NOT EXISTS site_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO blog_series (id, title, slug, description) VALUES
(1, 'Getting Started', 'getting-started', 'Onboarding series for the platform');
