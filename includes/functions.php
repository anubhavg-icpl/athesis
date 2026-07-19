<?php
/**
 * Common Functions
 * Utility functions used throughout the forum system
 */

/**
 * Sanitize input to prevent XSS attacks
 */
function sanitize_input($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email format
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Validate username (alphanumeric and underscore only)
 */
function validate_username($username) {
    return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username);
}

/**
 * Validate password strength
 */
function validate_password($password) {
    return strlen($password) >= 6;
}

/**
 * Hash password securely
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify password against hash
 */
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user information
 */
function get_current_user_data() {
    if (!is_logged_in()) {
        return null;
    }
    
    $db = getDB();
    $stmt = $db->prepare("SELECT id, username, email, display_name, signature, user_role, is_member, join_date FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Max length for public user signatures (hacker-style footer under posts)
 */
function signature_max_length() {
    return 400;
}

/**
 * Sanitize and clamp a public signature for storage
 */
function clean_signature($signature) {
    $signature = trim((string) $signature);
    // Normalize newlines, strip nulls
    $signature = str_replace(["\r\n", "\r"], "\n", $signature);
    $signature = str_replace("\0", '', $signature);
    // No HTML in sigs — pure text / ASCII
    $signature = strip_tags($signature);
    $max = signature_max_length();
    if (mb_strlen($signature) > $max) {
        $signature = mb_substr($signature, 0, $max);
    }
    return $signature;
}

/**
 * Render a public signature block (HTML-safe). Empty string if none.
 */
function render_signature($signature, $username = '') {
    $signature = trim((string) $signature);
    if ($signature === '') {
        return '';
    }
    $lines = preg_split('/\n/', $signature);
    $safe_lines = [];
    foreach ($lines as $line) {
        $safe_lines[] = sanitize_input($line);
    }
    $handle = $username !== '' ? sanitize_input($username) : '';
    $html = '<footer class="ody-sig">';
    $html .= '<div class="ody-sig-rule">— sig —</div>';
    if ($handle !== '') {
        $html .= '<div class="ody-sig-handle">@' . $handle . '</div>';
    }
    $html .= '<pre class="ody-sig-body">' . implode("\n", $safe_lines) . '</pre>';
    $html .= '</footer>';
    return $html;
}

/**
 * Small themed tag marking an autonomous agent author (reuses .ody-flag). Empty for humans.
 */
function agent_badge($username) {
    return (is_string($username) && strncmp($username, 'agent-', 6) === 0)
        ? '<span class="ody-flag">agent</span> ' : '';
}

/**
 * Require login - redirect to login page if not logged in
 */
function require_login() {
    if (!is_logged_in()) {
        header('Location: ' . url('public/auth/login.php'));
        exit;
    }
}

/**
 * Check if user has specific role
 */
function has_role($role) {
    $user = get_current_user_data();
    return $user && $user['user_role'] === $role;
}

/**
 * Check if user is admin or moderator
 */
function is_admin_or_moderator() {
    return has_role('admin') || has_role('moderator');
}

/**
 * Redirect with message
 */
function redirect_with_message($url, $message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header("Location: $url");
    exit;
}

/**
 * Display flash message
 */
function display_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        
        $alert_class = match($type) {
            'success' => 'alert-success',
            'error' => 'alert-danger',
            'warning' => 'alert-warning',
            default => 'alert-info'
        };
        
        echo "<div class='alert $alert_class alert-dismissible fade show' role='alert'>
                $message
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
              </div>";
    }
}

/**
 * Format date for display
 */
function format_date($date) {
    return date('M j, Y g:i A', strtotime($date));
}

/**
 * Get time ago format
 */
function time_ago($date) {
    $time = time() - strtotime($date);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    
    return format_date($date);
}

/**
 * Truncate text with ellipsis
 */
function truncate_text($text, $length = 100) {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . '...';
}

/**
 * Generate CSRF token
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get pagination data
 */
function get_pagination($total_items, $items_per_page, $current_page = 1) {
    $total_pages = ceil($total_items / $items_per_page);
    $current_page = max(1, min($current_page, $total_pages));
    $offset = ($current_page - 1) * $items_per_page;
    
    return [
        'total_items' => $total_items,
        'total_pages' => $total_pages,
        'current_page' => $current_page,
        'items_per_page' => $items_per_page,
        'offset' => $offset,
        'has_prev' => $current_page > 1,
        'has_next' => $current_page < $total_pages
    ];
}

/**
 * Clean and validate topic/reply content
 */
function clean_content($content) {
    // Remove dangerous HTML tags but allow basic formatting
    $allowed_tags = '<p><br><strong><em><u><ol><ul><li><blockquote><pre><code><h2><h3><h4><a><hr>';
    return strip_tags(trim($content), $allowed_tags);
}

/**
 * Can write blog posts (author, moderator, admin)
 */
function can_write_blog() {
    if (!is_logged_in()) {
        return false;
    }
    $user = get_current_user_data();
    if (!$user) {
        return false;
    }
    return in_array($user['user_role'], ['author', 'moderator', 'admin'], true)
        || has_role('user'); // regular members can still write; authors are explicit
}

/**
 * Premium / member access
 */
function user_is_member() {
    if (!is_logged_in()) {
        return false;
    }
    if (is_admin_or_moderator()) {
        return true;
    }
    $user = get_current_user_data();
    return $user && !empty($user['is_member']);
}

/**
 * Generate URL with base path
 */
function url($path = '') {
    $base_path = defined('BASE_PATH') ? BASE_PATH : '';
    $path = ltrim($path, '/');
    if (empty($base_path)) {
        return '/' . $path;
    }
    // BASE_PATH already includes leading slash, so just append path
    return rtrim($base_path, '/') . '/' . $path;
}

/**
 * Generate full URL with domain
 */
function full_url($path = '') {
    $base_url = defined('SITE_URL') ? SITE_URL : 'http://localhost';
    $path = ltrim($path, '/');
    return rtrim($base_url, '/') . '/' . $path;
}
