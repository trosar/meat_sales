<?php

// 1. Load .env file if it exists (for local dev)
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue;
        
        // Split by the first '=' found
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            putenv(trim($name) . '=' . trim($value));
        }
    }
}

session_start();

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}


// 3. Database connection using ONLY Environment Variables
$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$db   = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$tab_prefix = getenv('DB_TAB_PREFIX');
$charset = 'utf8mb4';

$store_is_prod = strtolower(getenv('STORE_IS_PROD') === 'true'); //If place order actually writes to DB or not
$store_is_open = strtolower(getenv('STORE_IS_OPEN') === 'true'); //Homepage shows items or not
$styles_version = "115"; // Increment this to force browsers to reload CSS when needed

// Basic validation: stop if variables are missing
if (!$host || !$user || !$pass) {
    die("Environment variables are not configured.");
}

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     // Error is logged internally, but hidden from the public for security
     error_log($e->getMessage());
     die("Database connection failed.");
}

/**
 * Formats a UTC database date string into local time (Pacific)
 * Default format: "Apr 15, 2026 10:30 PM"
 */
function formatLocalDate($dateStr, $format = 'M j, Y g:i A') {
    if (!$dateStr) return '';
    
    try {
        $date = new DateTime($dateStr, new DateTimeZone('UTC'));
        $date->setTimezone(new DateTimeZone('America/Los_Angeles'));
        return $date->format($format);
    } catch (Exception $e) {
        return $dateStr; // Fallback to raw string if parsing fails
    }
}
?>
