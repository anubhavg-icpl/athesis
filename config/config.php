<?php
/**
 * Main Configuration File
 * Global settings and constants for the forum system
 */

// Session will be started by secure_session_start() in security.php

// Auto-detect base path
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Calculate base path: path from document root to project root
// Config file is in config/ directory, so project root is parent of config/
$config_dir = __DIR__; // This is the config/ directory
$project_root = dirname($config_dir); // This is the project root

// Get the document root
$document_root = $_SERVER['DOCUMENT_ROOT'] ?? '';
$document_root = str_replace('\\', '/', $document_root); // Normalize for Windows
$project_root = str_replace('\\', '/', $project_root); // Normalize for Windows

// Get the path relative to document root
if (!empty($document_root) && strpos($project_root, $document_root) === 0) {
    // Project is inside document root
    $base_path = substr($project_root, strlen($document_root));
    $base_path = str_replace('\\', '/', $base_path);
    $base_path = trim($base_path, '/\\');
    $base_path = !empty($base_path) ? '/' . $base_path : '';
} else {
    // Project might be outside document root or we can't determine
    // Try to get from script name
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    if (!empty($script_name)) {
        $script_dir = dirname($script_name);
        $base_path = dirname($script_dir); // Go up from public/ or auth/ etc to project root
        $base_path = str_replace('\\', '/', $base_path);
        $base_path = ($base_path === '/' || $base_path === '.') ? '' : $base_path;
    } else {
        $base_path = '';
    }
}

// Site configuration
define('SITE_NAME', 'Athesis');
define('SITE_URL', $protocol . '://' . $host . $base_path);
define('BASE_PATH', $base_path);
define('SITE_DESCRIPTION', 'Athesis — sparse community forum and blog. Anyone can read. Members write.');

// Security settings
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// Pagination settings
define('TOPICS_PER_PAGE', 10);
define('REPLIES_PER_PAGE', 20);
define('POSTS_PER_PAGE', 10);

// File upload settings (for future use)
define('MAX_UPLOAD_SIZE', 2097152); // 2MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);

// Analytics (Phase 4) — set env or leave empty to disable
// PLAUSIBLE_DOMAIN=example.com  or  GA_MEASUREMENT_ID=G-XXXX
define('PLAUSIBLE_DOMAIN', getenv('PLAUSIBLE_DOMAIN') ?: '');
define('GA_MEASUREMENT_ID', getenv('GA_MEASUREMENT_ID') ?: '');

// Include database configuration
require_once __DIR__ . '/database.php';

// Include security configuration
require_once __DIR__ . '/security.php';

// Include common functions
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/blog.php';

// Set timezone
date_default_timezone_set('UTC');

// Error reporting: show in browser only when explicitly debugging; off by default (production-safe)
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('display_errors', (getenv('APP_DEBUG') === '1') ? 1 : 0);
