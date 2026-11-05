<?php
/**
 * Swastya Setu - Automated Test Suite
 * Validates database connectivity, schemas, auth hashing, AI Flask endpoint, and CRUD logic.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$isCli = (php_sapi_name() === 'cli');
$passed = 0;
$failed = 0;

function assertTest($description, $condition) {
    global $passed, $failed, $isCli;
    if ($condition) {
        $passed++;
        if ($isCli) {
            echo " [PASS] $description\n";
        } else {
            echo "<div style='color: green;'><strong>[PASS]</strong> " . htmlspecialchars($description) . "</div>";
        }
    } else {
        $failed++;
        if ($isCli) {
            echo " [FAIL] $description\n";
        } else {
            echo "<div style='color: red;'><strong>[FAIL]</strong> " . htmlspecialchars($description) . "</div>";
        }
    }
}

if (!$isCli) {
    echo "<!DOCTYPE html><html><head><title>Swastya Setu Test Suite</title></head><body style='font-family: sans-serif; padding: 20px;'>";
    echo "<h2>Swastya Setu Automated Verification Suite</h2>";
}

echo "=== SWASTYA SETU TEST SUITE ===\n\n";

// 1. Database Connection
try {
    $db = getDB();
    assertTest("Database connection established via PDO", $db instanceof PDO);
} catch (Exception $e) {
    assertTest("Database connection established via PDO", false);
}

// 2. Table Verification
$requiredTables = [
    'users', 'user_profiles', 'hospitals', 'doctors', 'pharmacies', 
    'medicines', 'appointments', 'predictions', 'prescriptions', 
    'prescription_items', 'orders', 'order_items', 'ambulance_requests', 'notifications'
];

foreach ($requiredTables as $tbl) {
    try {
        $cnt = $db->query("SELECT 1 FROM {$tbl} LIMIT 1");
        assertTest("Table `{$tbl}` exists and accessible", $cnt !== false);
    } catch (Exception $e) {
        assertTest("Table `{$tbl}` exists and accessible", false);
    }
}

// 3. User Authentication & Password Hashing Verification
$users = [
    ['admin@swastyasetu.in', 'Admin@123', 'admin'],
    ['patient@swastyasetu.in', 'Patient@123', 'patient'],
    ['apollo@swastyasetu.in', 'Hospital@123', 'hospital'],
    ['pharmacy@swastyasetu.in', 'Pharmacy@123', 'pharmacy']
];

foreach ($users as [$email, $password, $expectedRole]) {
    $st = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $st->execute([$email]);
    $u = $st->fetch();
    $pwdMatch = ($u && password_verify($password, $u['password_hash']));
    $roleMatch = ($u && $u['role'] === $expectedRole);
    assertTest("Auth check for '{$email}' (role: {$expectedRole})", $pwdMatch && $roleMatch);
}

// 4. AI Flask Microservice & Fallback Check
$ch = curl_init(AI_SERVICE_URL . '/predict');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['symptoms' => ['fever', 'cough']]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 3);
$aiRes = curl_exec($ch);
$aiCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$aiData = json_decode($aiRes, true);
$aiSuccess = ($aiCode === 200 && isset($aiData['primary_condition']) && !empty($aiData['precautions']));
assertTest("AI Disease Prediction endpoint returns top condition and precautions", $aiSuccess);

// 5. Prescription Directory Verification
$rxDir = UPLOAD_DIR;
$rxDirExists = is_dir($rxDir);
$rxDirProtected = file_exists($rxDir . '/.htaccess');
assertTest("Prescriptions upload directory exists and is secured", $rxDirExists && $rxDirProtected);

// 6. Test Doctor Slots
$docSlots = $db->query("SELECT available_time_slots FROM doctors LIMIT 1")->fetchColumn();
assertTest("Doctor available time slots are populated", !empty($docSlots));

// Summary
echo "\n=========================================\n";
echo "Total Tests Run: " . ($passed + $failed) . " | Passed: {$passed} | Failed: {$failed}\n";
echo "=========================================\n";

if (!$isCli) {
    echo "<hr><h4>Summary: {$passed} Passed, {$failed} Failed</h4></body></html>";
}
