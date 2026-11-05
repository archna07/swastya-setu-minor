<?php
/**
 * Swastya Setu - Access-Controlled Prescription Streamer
 * Ensures files cannot be accessed without authenticated ownership.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
$user = currentUser();
$db = getDB();

$rxId = (int)($_GET['id'] ?? 0);
if (!$rxId) {
    http_response_code(400);
    die("Invalid prescription request identifier.");
}

$stmt = $db->prepare("SELECT p.*, ph.user_id AS pharmacy_owner_user_id FROM prescriptions p JOIN pharmacies ph ON p.pharmacy_id = ph.id WHERE p.id = ? LIMIT 1");
$stmt->execute([$rxId]);
$rx = $stmt->fetch();

if (!$rx) {
    http_response_code(404);
    die("Prescription record not found.");
}

// Ownership Authorization Check
$isPatientOwner = ($rx['patient_id'] == $user['id']);
$isPharmacyOwner = ($user['role'] === 'pharmacy' && $rx['pharmacy_owner_user_id'] == $user['id']);
$isAdmin = ($user['role'] === 'admin');

if (!$isPatientOwner && !$isPharmacyOwner && !$isAdmin) {
    http_response_code(403);
    die("Access Denied: You do not have authorization to view this medical prescription document.");
}

// Path Traversal Protection
$fileName = basename($rx['file_path']);
$filePath = UPLOAD_DIR . $fileName;

if (!file_exists($filePath)) {
    http_response_code(404);
    die("Prescription file is missing from secure storage.");
}

$mime = $rx['file_type'] ?: mime_content_type($filePath);

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: inline; filename="' . addslashes($rx['original_filename']) . '"');
header('Cache-Control: private, max-age=3600');

readfile($filePath);
exit;
