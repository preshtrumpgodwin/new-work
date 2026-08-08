<?php
/**
 * Database Connection File
 * 
 * Establishes a PDO connection to MySQL with automatic database initialization
 * and sets the timezone to Africa/Lagos (Nigeria Time, UTC+1).
 */

// Set the default timezone to Africa/Lagos
date_default_timezone_set('Africa/Lagos');

// Database configuration — reads from environment variables when set (production),
// falling back to local XAMPP/MAMP defaults for local development only.
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'zetaphase_cloud');

$pdo = null;

try {
    // Try connecting to MySQL directly
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );

    // Set MySQL session timezone to Africa/Lagos
    $pdo->exec("SET time_zone = '+01:00'");

} catch (PDOException $e) {
    // Store error and redirect to beautiful error page
    $_SESSION['db_error'] = $e->getMessage();
    require_once __DIR__ . '/../db-error.php';
    exit;
}