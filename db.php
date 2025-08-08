<?php
$env = [];
if (file_exists('.env')) {
    $env = parse_ini_file('.env');
}


$host = $env['SUPABASE_HOST'] ;
$port = '6543';
$dbname = 'postgres';
$username = $env['SUPABASE_USER'];
$password = $env['SUPABASE_PASSWORD'];


$dsn = "pgsql:host=$host;port=$port;dbname=$dbname";


$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
   
    $pdo = new PDO($dsn, $username, $password, $options);
    
    
    
} catch (PDOException $e) {
    
    error_log("Database connection failed: " . $e->getMessage());
    
    
    die("Database connection failed. Please try again later.");
}


function getDBConnection() {
    global $pdo;
    return $pdo;
}


function closeDBConnection() {
    global $pdo;
    $pdo = null;
}


register_shutdown_function('closeDBConnection');
?>
