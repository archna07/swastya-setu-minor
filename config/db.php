<?php
/**
 * Swastya Setu - Database Connection (PDO)
 * Robust connection manager with environment variable integration and secure error handling.
 */

require_once __DIR__ . '/config.php';

defined('DB_HOST') or define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
defined('DB_NAME') or define('DB_NAME', getenv('DB_NAME') ?: 'swastya_setu');
defined('DB_USER') or define('DB_USER', getenv('DB_USER') ?: 'root');
defined('DB_PASS') or define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '1234');

class Database {
    private static ?PDO $pdo = null;

    public static function getConnection(): PDO {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $host = DB_HOST;
        $db   = DB_NAME;
        $user = DB_USER;
        $configuredPass = DB_PASS;
        
        // In production, strictly use configured credentials
        // In local development, allow fallback passwords to accommodate standard XAMPP/MySQL installs
        $isProd = (defined('APP_ENV') && APP_ENV === 'production');
        $passwordsToTry = $isProd ? [$configuredPass] : array_unique([$configuredPass, '', '1234', 'root']);
        
        $lastException = null;

        foreach ($passwordsToTry as $pass) {
            try {
                $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ];
                self::$pdo = new PDO($dsn, $user, $pass, $options);
                return self::$pdo;
            } catch (PDOException $e) {
                // If database doesn't exist, attempt creation in development
                if ($e->getCode() == 1049 && !$isProd) {
                    try {
                        $dsnNoDb = "mysql:host={$host};charset=utf8mb4";
                        $serverPdo = new PDO($dsnNoDb, $user, $pass, [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                        ]);
                        $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                        
                        self::$pdo = new PDO("mysql:host={$host};dbname={$db};charset=utf8mb4", $user, $pass, [
                            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                            PDO::ATTR_EMULATE_PREPARES   => false,
                        ]);
                        return self::$pdo;
                    } catch (PDOException $ex) {
                        $lastException = $ex;
                        continue;
                    }
                }
                $lastException = $e;
            }
        }

        // Log detailed exception without leaking credentials
        error_log("Database connection error: " . ($lastException ? $lastException->getMessage() : "Unknown error"));

        $errDetails = (defined('APP_DEBUG') && APP_DEBUG) 
            ? "<p><strong>Debug details:</strong> " . htmlspecialchars($lastException ? $lastException->getMessage() : 'Unknown error') . "</p>" 
            : "<p>Please ensure database services are running and credentials in <code>.env</code> are valid.</p>";

        http_response_code(500);
        die("<div style='font-family: sans-serif; padding: 2rem; background: #fff5f5; border: 1px solid #feb2b2; margin: 2rem auto; max-width: 600px; border-radius: 8px;'>
            <h3 style='color: #c53030;'>Swastya Setu - Database Connection Error</h3>
            <p>Could not connect to MySQL server. Please verify database availability.</p>
            {$errDetails}
            <p><a href='" . BASE_URL . "/setup.php' style='display: inline-block; padding: 8px 16px; background: #1E88E5; color: white; text-decoration: none; border-radius: 4px; font-weight: bold;'>Run Database Setup</a></p>
        </div>");
    }
}

function getDB(): PDO {
    return Database::getConnection();
}
