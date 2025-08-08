<?php
/**
 * Database Connection Configuration
 * PostgreSQL connection using PDO
 */

// Load environment variables from .env file
$env = [];
if (file_exists('.env')) {
    $env = parse_ini_file('.env');
}

// Database configuration for Supabase
$host = $env['SUPABASE_HOST'] ;
$port = '6543';
$dbname = 'postgres';
$username = $env['SUPABASE_USER'];
$password = $env['SUPABASE_PASSWORD'];

// DSN (Data Source Name) for PostgreSQL
$dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

// PDO options for better error handling and security
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    // Create PDO instance
    $pdo = new PDO($dsn, $username, $password, $options);
    
    // Optional: Set timezone if needed
    // $pdo->exec("SET timezone = 'UTC'");
    
} catch (PDOException $e) {
    // Log error (in production, log to file instead of displaying)
    error_log("Database connection failed: " . $e->getMessage());
    
    // Display user-friendly error message
    die("Database connection failed. Please try again later.");
}

/**
 * Helper function to get database connection
 * @return PDO
 */
function getDBConnection() {
    global $pdo;
    return $pdo;
}

/**
 * Helper function to close database connection
 */
function closeDBConnection() {
    global $pdo;
    $pdo = null;
}

// Optional: Register shutdown function to close connection
register_shutdown_function('closeDBConnection');
?>
