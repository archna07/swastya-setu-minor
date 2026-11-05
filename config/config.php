<?php
/**
 * Swastya Setu - Configuration Settings
 * Environment-aware configuration with secure session management and security headers.
 */

// Load .env variables if .env file exists
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            [$key, $val] = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val, " \t\n\r\0\x0B\"'");
            if (!getenv($key) && $key !== '') {
                putenv("{$key}={$val}");
                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
            }
        }
    }
}

// Environment Constants
defined('APP_ENV') or define('APP_ENV', getenv('APP_ENV') ?: 'development');
defined('APP_DEBUG') or define('APP_DEBUG', filter_var(getenv('APP_DEBUG') ?: (APP_ENV === 'development'), FILTER_VALIDATE_BOOLEAN));

// Application Constants
defined('APP_NAME') or define('APP_NAME', 'Swastya Setu');
defined('APP_TAGLINE') or define('APP_TAGLINE', 'Connecting Healthcare, Simplifying Lives.');
defined('APP_VERSION') or define('APP_VERSION', '2.1.0');

// Base URL calculation (works under http://localhost/Swastya-setu-app, localhost:8000, or virtual host)
if (!defined('BASE_URL')) {
    if (getenv('BASE_URL')) {
        define('BASE_URL', rtrim(getenv('BASE_URL'), '/'));
    } else {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $baseDir = rtrim($scriptDir, '/');
        
        $subfolders = ['/user', '/hospital', '/pharmacy', '/admin', '/api', '/config', '/includes'];
        foreach ($subfolders as $sub) {
            if (substr($baseDir, -strlen($sub)) === $sub) {
                $baseDir = substr($baseDir, 0, -strlen($sub));
            }
        }
        
        define('BASE_URL', $protocol . $host . $baseDir);
    }
}

// Uploads Directory Configuration
defined('UPLOAD_DIR') or define('UPLOAD_DIR', __DIR__ . '/../uploads/prescriptions/');
defined('MAX_UPLOAD_SIZE') or define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB

// Flask AI Service URL
defined('AI_SERVICE_URL') or define('AI_SERVICE_URL', getenv('AI_SERVICE_URL') ?: 'http://127.0.0.1:5000');

// Secure Session Initialization
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    
    session_start();

    // Session Idle Timeout (30 minutes)
    $timeout = (int)(getenv('SESSION_LIFETIME') ?: 1800);
    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $timeout)) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['LAST_ACTIVITY'] = time();
}
