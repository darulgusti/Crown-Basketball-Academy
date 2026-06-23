<?php
/**
 * Database Configuration and Connection
 * Crown Basketball Academy
 */

// Read environment variables (useful for Vercel deployment) or fallback to local constants
$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$dbName = getenv('DB_NAME') ?: 'crown_basketball';
$dbCharset = getenv('DB_CHARSET') ?: 'utf8mb4';

define('DB_HOST', $dbHost);
define('DB_PORT', $dbPort);
define('DB_USER', $dbUser);
define('DB_PASS', $dbPass);
define('DB_NAME', $dbName);
define('DB_CHARSET', $dbCharset);

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
            
            // Enable SSL/TLS for TiDB Cloud connections
            if (strpos(DB_HOST, 'tidbcloud.com') !== false) {
                // Resolve SSL constants dynamically to support PHP 8.5+ deprecations and fallback for older PHP versions
                $sslCaKey = defined('Pdo\\Mysql::ATTR_SSL_CA') 
                    ? constant('Pdo\\Mysql::ATTR_SSL_CA') 
                    : (defined('PDO::MYSQL_ATTR_SSL_CA') ? PDO::MYSQL_ATTR_SSL_CA : 1011);
                    
                $sslVerifyKey = defined('Pdo\\Mysql::ATTR_SSL_VERIFY_SERVER_CERT') 
                    ? constant('Pdo\\Mysql::ATTR_SSL_VERIFY_SERVER_CERT') 
                    : (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT') ? PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT : 1014);

                $caPaths = [
                    '/etc/ssl/certs/ca-certificates.crt',
                    '/etc/pki/tls/certs/ca-bundle.crt',
                    '/etc/ssl/ca-bundle.pem',
                    '/etc/ssl/cert.pem'
                ];
                foreach ($caPaths as $path) {
                    if (file_exists($path)) {
                        $options[$sslCaKey] = $path;
                        break;
                    }
                }
                if (!isset($options[$sslCaKey])) {
                    $options[$sslVerifyKey] = false;
                }
            }
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // In a production environment, you might want to log this and show a generic error
            die("Database connection failed: " . htmlspecialchars($e->getMessage()));
        }
    }
    
    return $pdo;
}
