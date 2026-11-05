<?php
/**
 * Swastya Setu - One-Click Database Installer & Setup Script
 * Can be run via browser: http://localhost/Swastya-setu-app/setup.php
 * Or via CLI: php setup.php
 */

require_once __DIR__ . '/config/config.php';

$isCli = (php_sapi_name() === 'cli');

// Security Guard: Prevent public execution in production
if (!$isCli && defined('APP_ENV') && APP_ENV === 'production') {
    $setupKey = getenv('SETUP_KEY');
    if (!$setupKey || ($_GET['key'] ?? '') !== $setupKey) {
        http_response_code(403);
        die("Forbidden: Automated setup via web browser is disabled in production. Please run setup via CLI: php setup.php");
    }
}

function outputMsg($msg, $type = 'info') {
    global $isCli;
    if ($isCli) {
        echo "[$type] $msg\n";
    } else {
        $colors = [
            'success' => '#2e7d32',
            'danger'  => '#c62828',
            'warning' => '#ef6c00',
            'info'    => '#1565c0'
        ];
        $color = $colors[$type] ?? '#333';
        echo "<div style='color: {$color}; margin: 6px 0; font-family: monospace;'><strong>" . strtoupper($type) . ":</strong> " . htmlspecialchars($msg) . "</div>";
    }
}

if (!$isCli) {
    echo "<!DOCTYPE html><html><head><title>Swastya Setu - Database Setup</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f7fafc; padding: 2rem; color: #2d3748; }
        .setup-card { max-width: 700px; margin: 0 auto; background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); padding: 2rem; }
        h1 { color: #1E88E5; margin-top: 0; font-size: 1.8rem; }
        .btn { display: inline-block; padding: 10px 20px; background: #1E88E5; color: white; border-radius: 6px; text-decoration: none; font-weight: bold; margin-top: 1.5rem; }
    </style></head><body><div class='setup-card'>
    <h1>Swastya Setu - Automated Installer</h1>";
}

// 1. Connect to MySQL server
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$dbName = getenv('DB_NAME') ?: 'swastya_setu';
$configuredPass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '1234';

$isProd = (defined('APP_ENV') && APP_ENV === 'production');
$passwords = $isProd ? [$configuredPass] : array_unique([$configuredPass, '', '1234', 'root']);

$serverPdo = null;
$workingPassword = null;

foreach ($passwords as $p) {
    try {
        $serverPdo = new PDO("mysql:host={$host};charset=utf8mb4", $user, $p, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $workingPassword = $p;
        break;
    } catch (PDOException $e) {
        continue;
    }
}

if (!$serverPdo) {
    outputMsg("Could not connect to MySQL with any standard password (tried '', '1234', 'root'). Please check XAMPP MySQL service.", 'danger');
    if (!$isCli) echo "</div></body></html>";
    exit(1);
}

outputMsg("Connected to MySQL server successfully (authenticated).", 'success');

// 2. Create Database
try {
    $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    outputMsg("Database `{$dbName}` is ready.", 'success');
} catch (PDOException $e) {
    outputMsg("Failed to create database: " . $e->getMessage(), 'danger');
    exit(1);
}

// 3. Connect to swastya_setu database
$dbPdo = new PDO("mysql:host={$host};dbname={$dbName};charset=utf8mb4", $user, $workingPassword, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

// 4. Read and execute schema.sql
$schemaFile = __DIR__ . '/database/schema.sql';
if (!file_exists($schemaFile)) {
    outputMsg("schema.sql file not found at {$schemaFile}!", 'danger');
    exit(1);
}

$sqlContent = file_get_contents($schemaFile);

// Split statements by semicolon followed by newline
$statements = array_filter(array_map('trim', preg_split("/;[\r\n]+/", $sqlContent)));

$executed = 0;
foreach ($statements as $stmt) {
    if (empty($stmt)) continue;
    try {
        $dbPdo->exec($stmt);
        $executed++;
    } catch (PDOException $e) {
        // Ignore duplicate insert errors if already seeded
        if ($e->getCode() != 23000) {
            outputMsg("Notice on statement: " . $e->getMessage(), 'warning');
        }
    }
}
outputMsg("Executed {$executed} database schema and table definitions successfully.", 'success');

// 5. Update/Ensure Password Hashes for Demo Accounts
$defaultAccounts = [
    ['email' => 'admin@swastyasetu.in',    'role' => 'admin',    'name' => 'System Administrator',     'pass' => 'Admin@123',    'phone' => '+91 9999999999'],
    ['email' => 'patient@swastyasetu.in',  'role' => 'patient',  'name' => 'Rajesh Kumar',             'pass' => 'Patient@123',  'phone' => '+91 9876543210'],
    ['email' => 'apollo@swastyasetu.in',   'role' => 'hospital', 'name' => 'Apollo Hospital Admin',     'pass' => 'Hospital@123', 'phone' => '+91 9811223344'],
    ['email' => 'pharmacy@swastyasetu.in', 'role' => 'pharmacy', 'name' => 'Apollo Pharmacy Owner',   'pass' => 'Pharmacy@123', 'phone' => '+91 9822334455'],
];

foreach ($defaultAccounts as $acc) {
    $hash = password_hash($acc['pass'], PASSWORD_BCRYPT);
    $check = $dbPdo->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$acc['email']]);
    if ($row = $check->fetch()) {
        $upd = $dbPdo->prepare("UPDATE users SET password_hash = ?, status = 'active' WHERE id = ?");
        $upd->execute([$hash, $row['id']]);
    } else {
        $ins = $dbPdo->prepare("INSERT INTO users (name, email, password_hash, phone, role, status) VALUES (?, ?, ?, ?, ?, 'active')");
        $ins->execute([$acc['name'], $acc['email'], $hash, $acc['phone'], $acc['role']]);
    }
}
outputMsg("Default user accounts configured with valid bcrypt password hashes.", 'success');

// 6. Ensure Upload Directory exists with protection
$uploadDir = __DIR__ . '/uploads/prescriptions';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
// Add .htaccess inside uploads directory to forbid direct script execution
file_put_contents($uploadDir . '/.htaccess', "Options -Indexes\n<FilesMatch \"\.(php|php5|phtml|pl|py|jsp|asp|sh|cgi)$\">\nDeny from all\n</FilesMatch>");
file_put_contents($uploadDir . '/index.html', '<!DOCTYPE html><html><head><title>Access Denied</title></head><body>Directory listing is prohibited.</body></html>');
outputMsg("Uploads directory verified and secured at uploads/prescriptions/.", 'success');

// 7. Success message & login credentials summary
outputMsg("INSTALLATION COMPLETE! Swastya Setu is ready.", 'success');

if (!$isCli) {
    echo "
    <hr style='margin: 1.5rem 0; border: 0; border-top: 1px solid #e2e8f0;'>
    <h3>Default Login Credentials:</h3>
    <table style='width: 100%; border-collapse: collapse; margin-top: 1rem;'>
        <tr style='background: #edf2f7;'>
            <th style='padding: 8px; text-align: left;'>Role</th>
            <th style='padding: 8px; text-align: left;'>Email</th>
            <th style='padding: 8px; text-align: left;'>Password</th>
        </tr>
        <tr><td style='padding: 8px;'>Admin</td><td>admin@swastyasetu.in</td><td>Admin@123</td></tr>
        <tr style='background: #f7fafc;'><td style='padding: 8px;'>Patient</td><td>patient@swastyasetu.in</td><td>Patient@123</td></tr>
        <tr><td style='padding: 8px;'>Hospital</td><td>apollo@swastyasetu.in</td><td>Hospital@123</td></tr>
        <tr style='background: #f7fafc;'><td style='padding: 8px;'>Pharmacy</td><td>pharmacy@swastyasetu.in</td><td>Pharmacy@123</td></tr>
    </table>
    <a href='" . BASE_URL . "/login.php' class='btn'>Go to Login Page &rarr;</a>
    </div></body></html>";
}
