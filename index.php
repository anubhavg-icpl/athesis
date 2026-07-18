<?php
/**
 * Athesis - Root Redirect
 * Redirects users to the public directory where the main application is located
 */

require_once __DIR__ . '/config/config.php';

// Redirect to the public directory
header('Location: ' . url('public/index.php'));
exit();
?>