<?php
/**
 * Database Configuration and Connection
 * Crown Basketball Academy
 */

// Database configuration settings
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'crown_basketball');
define('DB_CHARSET', 'utf8mb4');

/**
 * Gets a PDO database connection instance.
 *
 * @return PDO
 */
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = sprintf(
                "mysql:host=%s;port=%s;dbname=%s;charset=%s",
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_CHARSET
            );
            
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // In a production environment, you might want to log this and show a generic error
            die("Database connection failed: " . htmlspecialchars($e->getMessage()));
        }
    }
    
    return $pdo;
}
