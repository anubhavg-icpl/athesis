-- Athesis database setup
-- Forum + blog tables for the Athesis project

-- Create database (IF NOT EXISTS: works for manual import AND Docker entrypoint init)
CREATE DATABASE IF NOT EXISTS php_forum CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE php_forum;

-- Users table for authentication and user management
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    signature TEXT NULL COMMENT 'Public hacker-style signature shown under posts',
    join_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    user_role ENUM('user', 'moderator', 'admin') DEFAULT 'user',
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_active (is_active)
);

-- Topics table for forum topics/threads
CREATE TABLE topics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_locked BOOLEAN DEFAULT FALSE,
    is_pinned BOOLEAN DEFAULT FALSE,
    view_count INT DEFAULT 0,
    reply_count INT DEFAULT 0,
    last_reply_at TIMESTAMP NULL,
    last_reply_user_id INT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (last_reply_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_created_at (created_at),
    INDEX idx_user_id (user_id),
    INDEX idx_pinned_created (is_pinned, created_at),
    INDEX idx_last_reply (last_reply_at),
    FULLTEXT idx_title_content (title, content)
);

-- Replies table for topic responses
CREATE TABLE replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    topic_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    parent_reply_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_deleted BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_reply_id) REFERENCES replies(id) ON DELETE SET NULL,
    INDEX idx_topic_id (topic_id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_parent_reply (parent_reply_id),
    INDEX idx_topic_created (topic_id, created_at)
);

-- User sessions table for session management
CREATE TABLE user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL DEFAULT (CURRENT_TIMESTAMP + INTERVAL 24 HOUR),
    ip_address VARCHAR(45),
    user_agent TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_expires (expires_at)
);

-- Insert sample admin user (password: admin123)
INSERT INTO users (username, email, password_hash, display_name, signature, user_role) VALUES 
('admin', 'admin@forum.local', '$2y$10$vju93sETWrhLhpk6dy1MkOdGXP41kLLvVAPy20L6yiHgL3i8wegOC', 'Administrator',
 '0xroot · trust nothing
verify everything · stay curious', 'admin');

-- Insert sample topics and replies for testing
INSERT INTO topics (title, content, user_id) VALUES 
('Welcome to the Forum!', 'This is the first topic in our new forum. Feel free to introduce yourself and start discussions!', 1),
('Forum Rules and Guidelines', 'Please read and follow these important guidelines to maintain a friendly and productive community environment.', 1);

INSERT INTO replies (topic_id, user_id, content) VALUES 
(1, 1, 'Thanks for joining our community! We look forward to your contributions.'),
(2, 1, 'Remember to be respectful and constructive in all your interactions.');

-- Update topic reply counts
UPDATE topics SET reply_count = 1, last_reply_at = NOW(), last_reply_user_id = 1 WHERE id IN (1, 2);

-- Phase 1 professional blog schema
USE php_forum;

CREATE TABLE IF NOT EXISTS blog_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS blog_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS blog_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(280) NOT NULL UNIQUE,
    excerpt TEXT NULL,
    content MEDIUMTEXT NOT NULL,
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    meta_title VARCHAR(255) NULL,
    meta_description VARCHAR(320) NULL,
    featured_image VARCHAR(500) NULL,
    view_count INT NOT NULL DEFAULT 0,
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE SET NULL,
    INDEX idx_status_published (status, published_at),
    INDEX idx_category (category_id),
    FULLTEXT idx_search (title, excerpt, content)
);

CREATE TABLE IF NOT EXISTS blog_post_tags (
    post_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (post_id, tag_id),
    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES blog_tags(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS blog_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NULL,
    author_name VARCHAR(100) NULL,
    content TEXT NOT NULL,
    is_approved TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_post_created (post_id, created_at)
);

INSERT IGNORE INTO blog_categories (id, name, slug, description) VALUES
(1, 'Guides', 'guides', 'How-tos and deep dives'),
(2, 'News', 'news', 'Updates and announcements'),
(3, 'Notes', 'notes', 'Short field notes');

INSERT IGNORE INTO blog_tags (id, name, slug) VALUES
(1, 'odyssey', 'odyssey'),
(2, 'security', 'security'),
(3, 'php', 'php');
