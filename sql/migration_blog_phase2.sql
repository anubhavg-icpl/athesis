-- Phase 2: editorial (schedule, revisions, media)
USE php_forum;

-- Expand status + schedule field
ALTER TABLE blog_posts
    MODIFY COLUMN status ENUM('draft', 'published', 'scheduled') NOT NULL DEFAULT 'draft';

-- scheduled_at: when a scheduled post should go live
-- MySQL may error if column exists — run once
ALTER TABLE blog_posts
    ADD COLUMN scheduled_at TIMESTAMP NULL AFTER published_at;

CREATE TABLE IF NOT EXISTS blog_revisions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NULL,
    title VARCHAR(255) NOT NULL,
    excerpt TEXT NULL,
    content MEDIUMTEXT NOT NULL,
    meta_title VARCHAR(255) NULL,
    meta_description VARCHAR(320) NULL,
    featured_image VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_post_rev (post_id, created_at)
);

CREATE TABLE IF NOT EXISTS blog_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    size_bytes INT NOT NULL DEFAULT 0,
    url_path VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_media_user (user_id, created_at)
);
