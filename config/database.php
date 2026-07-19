<?php
/**
 * Database Configuration — PostgreSQL (+ pgvector).
 * Connection settings come from DB_* env (see docker-compose.yml). No MySQL fallback.
 */

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'athesis');
define('DB_USER', getenv('DB_USER') ?: 'athesis');
define('DB_PASS', getenv('DB_PASS') ?: '');

// PDO options for a secure connection
$pdo_options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $dsn = 'pgsql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $pdo_options);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

function getDB() {
    global $pdo;
    return $pdo;
}
