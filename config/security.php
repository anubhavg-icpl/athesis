<?php
/**
 * Security Configuration and Functions
 * Additional security measures for the forum system
 */

// Security headers
function set_security_headers() {
    // Prevent clickjacking
    header('X-Frame-Options: DENY');
    
    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // XSS Protection
    header('X-XSS-Protection: 1; mode=block');
    
    // Referrer Policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Content Security Policy (allow JetBrains Mono + Bootstrap CDN)
    $csp = "default-src 'self'; " .
           "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; " .
           "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; " .
           "font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com data:; " .
           "img-src 'self' data: https:; " .
           "connect-src 'self';";
    header("Content-Security-Policy: $csp");
    
    // HTTPS enforcement (uncomment in production)
    // if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    //     header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    // }
}

// Rate limiting
class RateLimiter {
    private static $attempts = [];
    
    public static function check($identifier, $max_attempts = 5, $time_window = 300) {
        $current_time = time();
        $key = md5($identifier);
        
        // Clean old attempts
        if (isset(self::$attempts[$key])) {
            self::$attempts[$key] = array_filter(self::$attempts[$key], function($timestamp) use ($current_time, $time_window) {
                return ($current_time - $timestamp) < $time_window;
            });
        } else {
            self::$attempts[$key] = [];
        }
        
        // Check if limit exceeded
        if (count(self::$attempts[$key]) >= $max_attempts) {
            return false;
        }
        
        return true;
    }
    
    public static function record($identifier) {
        $key = md5($identifier);
        if (!isset(self::$attempts[$key])) {
            self::$attempts[$key] = [];
        }
        self::$attempts[$key][] = time();
    }
}

// Input validation and sanitization
function validate_and_sanitize_input($input, $type = 'string', $max_length = null) {
    // Remove null bytes
    $input = str_replace(chr(0), '', $input);
    
    switch ($type) {
        case 'email':
            $input = filter_var($input, FILTER_SANITIZE_EMAIL);
            return filter_var($input, FILTER_VALIDATE_EMAIL) ? $input : false;
            
        case 'url':
            $input = filter_var($input, FILTER_SANITIZE_URL);
            return filter_var($input, FILTER_VALIDATE_URL) ? $input : false;
            
        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT);
            
        case 'float':
            return filter_var($input, FILTER_VALIDATE_FLOAT);
            
        case 'username':
            $input = preg_replace('/[^a-zA-Z0-9_]/', '', $input);
            return (strlen($input) >= 3 && strlen($input) <= 20) ? $input : false;
            
        case 'html':
            // Allow only safe HTML tags
            $allowed_tags = '<p><br><strong><em><u><ol><ul><li><blockquote><h1><h2><h3><h4><h5><h6>';
            $input = strip_tags($input, $allowed_tags);
            // Remove dangerous attributes
            $input = preg_replace('/(<[^>]+)\s+(on\w+|javascript:|vbscript:|data:)[^>]*>/i', '$1>', $input);
            break;
            
        default:
            $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }
    
    // Apply max length if specified
    if ($max_length && strlen($input) > $max_length) {
        $input = substr($input, 0, $max_length);
    }
    
    return $input;
}

// SQL injection prevention (additional layer)
function prepare_sql_statement($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        
        // Bind parameters with explicit types
        foreach ($params as $key => $value) {
            $type = PDO::PARAM_STR;
            if (is_int($value)) {
                $type = PDO::PARAM_INT;
            } elseif (is_bool($value)) {
                $type = PDO::PARAM_BOOL;
            } elseif (is_null($value)) {
                $type = PDO::PARAM_NULL;
            }
            
            if (is_numeric($key)) {
                $stmt->bindValue($key + 1, $value, $type);
            } else {
                $stmt->bindValue($key, $value, $type);
            }
        }
        
        return $stmt;
    } catch (PDOException $e) {
        error_log("SQL Error: " . $e->getMessage());
        throw new Exception("Database error occurred");
    }
}

// File upload security
function secure_file_upload($file, $allowed_types = ['jpg', 'jpeg', 'png', 'gif'], $max_size = 2097152) {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'error' => 'Invalid file upload'];
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'File too large'];
    }
    
    // Check file type
    $file_info = pathinfo($file['name']);
    $extension = strtolower($file_info['extension'] ?? '');
    
    if (!in_array($extension, $allowed_types)) {
        return ['success' => false, 'error' => 'Invalid file type'];
    }
    
    // Check MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowed_mimes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif'
    ];
    
    if (!isset($allowed_mimes[$extension]) || $mime_type !== $allowed_mimes[$extension]) {
        return ['success' => false, 'error' => 'File type mismatch'];
    }
    
    // Generate secure filename
    $secure_name = bin2hex(random_bytes(16)) . '.' . $extension;
    
    return [
        'success' => true,
        'filename' => $secure_name,
        'original_name' => $file['name'],
        'size' => $file['size'],
        'type' => $mime_type
    ];
}

// Session security
function secure_session_start() {
    // Only configure session if it hasn't started yet
    if (session_status() === PHP_SESSION_NONE) {
        // Session configuration
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', 1);
        ini_set('session.use_only_cookies', 1);
        
        // Start the session
        session_start();
    }
    
    // Regenerate session ID periodically
    if (!isset($_SESSION['last_regeneration'])) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
    
    // Check for session hijacking
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    
    if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $user_agent) {
        session_destroy();
        return false;
    }
    
    if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $ip_address) {
        session_destroy();
        return false;
    }
    
    $_SESSION['user_agent'] = $user_agent;
    $_SESSION['ip_address'] = $ip_address;
    
    return true;
}

// Password security
function generate_secure_password($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    
    return $password;
}

function check_password_strength($password) {
    $score = 0;
    $feedback = [];
    
    // Length check
    if (strlen($password) >= 8) {
        $score += 1;
    } else {
        $feedback[] = 'Password should be at least 8 characters long';
    }
    
    // Uppercase check
    if (preg_match('/[A-Z]/', $password)) {
        $score += 1;
    } else {
        $feedback[] = 'Password should contain uppercase letters';
    }
    
    // Lowercase check
    if (preg_match('/[a-z]/', $password)) {
        $score += 1;
    } else {
        $feedback[] = 'Password should contain lowercase letters';
    }
    
    // Number check
    if (preg_match('/[0-9]/', $password)) {
        $score += 1;
    } else {
        $feedback[] = 'Password should contain numbers';
    }
    
    // Special character check
    if (preg_match('/[^a-zA-Z0-9]/', $password)) {
        $score += 1;
    } else {
        $feedback[] = 'Password should contain special characters';
    }
    
    $strength = match($score) {
        0, 1 => 'Very Weak',
        2 => 'Weak',
        3 => 'Fair',
        4 => 'Good',
        5 => 'Strong'
    };
    
    return [
        'score' => $score,
        'strength' => $strength,
        'feedback' => $feedback
    ];
}

// Logging security events
function log_security_event($event_type, $details = [], $user_id = null) {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event_type' => $event_type,
        'user_id' => $user_id,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'details' => $details
    ];
    
    $log_file = __DIR__ . '/../logs/security.log';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
}

// Initialize security
set_security_headers();
secure_session_start();
