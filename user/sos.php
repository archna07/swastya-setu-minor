<?php
/**
 * Swastya Setu - 108 Emergency Ambulance & SOS Dispatch Module
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('patient');
$user = currentUser();
$db = getDB();

$profStmt = $db->prepare("SELECT * FROM user_profiles WHERE user_id = ? LIMIT 1");
$profStmt->execute([$user['id']]);
$profile = $profStmt->fetch() ?: [];

// Handle SOS Broadcast Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'trigger_sos') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($token)) {
        setFlash('danger', 'Security session expired. Please refresh.');
    } else {
        $contactPhone = trim($_POST['contact_phone'] ?? $user['phone']);
        $emergencyType = trim($_POST['emergency_type'] ?? 'Cardiac / Acute Respiratory Emergency');
        $address = trim($_POST['address'] ?? ($profile['address'] ?? 'Connaught Place, New Delhi'));
        $lat = (float)($_POST['latitude'] ?? 28.6139);
        $lng = (float)($_POST['longitude'] ?? 77.2090);

        try {
            $ins = $db->prepare("
                INSERT INTO ambulance_requests (user_id, patient_name, contact_phone, latitude, longitude, address, emergency_type, status, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'Immediate 108 ALS Ambulance requested.')
            ");
            $ins->execute([$user['id'], $user['name'], $contactPhone, $lat, $lng, $address, $emergencyType]);
            $reqId = $db->lastInsertId();

            createNotification(
                $user['id'],
                'Emergency SOS Dispatched',
                "Emergency 108 SOS Request #SOS-{$reqId} has been logged. Emergency services notified.",
                'user/sos.php'
            );

            setFlash('danger', "🚨 EMERGENCY SOS BROADCAST LOGGED (#SOS-{$reqId})! Coordinates shared with 108 Network.");
            header('Location: ' . BASE_URL . '/user/sos.php');
            exit;

        } catch (Exception $e) {
            setFlash('danger', 'Error logging emergency request: ' . $e->getMessage());
        }
    }
}

// Fetch Nearby Hospitals with 24x7 Emergency Services
$emergencyHospitals = $db->query("SELECT * FROM hospitals WHERE status = 'approved' AND emergency_services = 1 ORDER BY available_beds DESC LIMIT 4")->fetchAll();

// Fetch Recent SOS Requests for this user
$stmt = $db->prepare("SELECT * FROM ambulance_requests WHERE user_id = ? ORDER BY id DESC LIMIT 5");
$stmt->execute([$user['id']]);
$myRequests = $stmt->fetchAll();

$pageTitle = 'Emergency 108 SOS & Ambulance Network';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="content-wrapper">
        <div class="container-fluid p-0">
            
            <?= displayFlash() ?>

            <!-- Emergency Banner -->
            <div class="card border-0 shadow-lg rounded-4 mb-4 text-white p-4 p-md-5" style="background: linear-gradient(135deg, #D32F2F, #B71C1C);">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <div class="d-inline-flex align-items-center gap-2 bg-white text-danger px-3 py-1 rounded-pill small fw-bold mb-3">
                            <i class="fa-solid fa-triangle-exclamation"></i> National Emergency Medical Response
                        </div>
                        <h1 class="fw-bold mb-2 display-6">Emergency 108 Ambulance Dispatch</h1>
                        <p class="lead fs-6 opacity-90 mb-4">
                            One-tap emergency broadcast immediately logs your GPS coordinates, shares medical allergies & blood group, and alerts nearest trauma center units.
                        </p>
                        
                        <form method="POST" action="sos.php" class="d-inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="trigger_sos">
                            <input type="hidden" name="latitude" id="geoLat" value="28.6139">
                            <input type="hidden" name="longitude" id="geoLng" value="77.2090">
                            <input type="hidden" name="address" value="<?= htmlspecialchars($profile['address'] ?? 'Connaught Place, New Delhi') ?>">
                            <input type="hidden" name="contact_phone" value="<?= htmlspecialchars($user['phone']) ?>">

                            <button type="submit" class="btn btn-warning btn-lg rounded-pill px-5 py-3 fw-bold shadow-lg text-dark pulse-sos" onclick="return confirm('Trigger 108 Emergency SOS broadcast now?')">
                                <i class="fa-solid fa-phone-volume me-2 fs-4"></i> Trigger 108 Rapid SOS Dispatch
                            </button>
                        </form>
                    </div>

                    <div class="col-lg-4 text-center">
                        <div class="p-3 rounded-4 bg-white text-dark shadow-sm">
                            <i class="fa-solid fa-truck-medical text-danger" style="font-size: 3.5rem;"></i>
                            <h5 class="fw-bold text-dark mt-2 mb-1">Direct Helplines</h5>
                            <div class="display-6 fw-bold text-danger">108 / 112</div>
                            <small class="text-muted d-block mt-2">National 24x7 Toll-Free Medical Hotline</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Academic Demonstration Disclaimer -->
            <div class="alert alert-warning border-0 rounded-4 shadow-sm p-3 mb-4 d-flex align-items-center gap-3">
                <i class="fa-solid fa-circle-exclamation text-warning fs-3"></i>
                <div class="small text-dark">
                    <strong>Academic Project Notice:</strong> This emergency module demonstrates full-stack request logging and hospital coordination for an MCA project. In an actual life-threatening medical emergency, call <strong>108</strong> (Ambulance) or <strong>112</strong> (National Helpline) directly from your phone.
                </div>
            </div>

            <!-- Emergency Health Passport & Nearby Facilities Grid -->
            <div class="row g-4 mb-4">
                
                <!-- Patient Medical Passport (Crucial for first responders) -->
                <div class="col-md-5">
                    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                        <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-id-card-clip text-primary me-2"></i> Patient Emergency Passport</h5>
                        
                        <div class="p-3 rounded-3 bg-light border mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted small">Blood Group:</span>
                                <span class="badge bg-danger rounded-pill px-3 py-1 fs-6"><?= htmlspecialchars($profile['blood_group'] ?? 'O+') ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted small">Emergency Contact:</span>
                                <strong class="text-dark small"><?= htmlspecialchars($profile['emergency_contact_phone'] ?? '+91 9876543210') ?></strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted small">Known Allergies:</span>
                                <strong class="text-danger small"><?= htmlspecialchars($profile['allergies'] ?: 'None Reported') ?></strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted small">Insurance Policy:</span>
                                <strong class="text-dark small"><?= htmlspecialchars($profile['insurance_info'] ?: 'Star Health (#SH-90812)') ?></strong>
                            </div>
                        </div>

                        <a href="<?= BASE_URL ?>/user/profile.php" class="btn btn-outline-primary btn-sm rounded-pill w-100">
                            Update Emergency Medical Details &rarr;
                        </a>
                    </div>
                </div>

                <!-- 24x7 Trauma Centers & Network Hospitals -->
                <div class="col-md-7">
                    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                        <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-hospital text-danger me-2"></i> Nearest 24x7 Emergency Trauma Centers</h5>
                        
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($emergencyHospitals as $hosp): ?>
                                <div class="p-3 rounded-3 bg-light border d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong class="text-dark d-block"><?= htmlspecialchars($hosp['name']) ?></strong>
                                        <small class="text-muted"><i class="fa-solid fa-location-dot text-danger me-1"></i> <?= htmlspecialchars($hosp['city']) ?> • <?= $hosp['available_beds'] ?> ICU Beds Available</small>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a href="tel:<?= htmlspecialchars($hosp['phone']) ?>" class="btn btn-danger btn-sm rounded-pill px-3 fw-bold">
                                            <i class="fa-solid fa-phone me-1"></i> Call 24x7
                                        </a>
                                        <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $hosp['latitude'] ?>,<?= $hosp['longitude'] ?>" target="_blank" class="btn btn-outline-secondary btn-sm rounded-pill px-2">
                                            <i class="fa-solid fa-diamond-turn-right"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Logged Emergency Requests Table -->
            <?php if (!empty($myRequests)): ?>
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i> Your Emergency Request Log</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light small text-muted">
                                    <tr>
                                        <th class="ps-4">Request ID</th>
                                        <th>Timestamp</th>
                                        <th>Emergency Category</th>
                                        <th>Coordinates / Address</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($myRequests as $req): ?>
                                        <tr>
                                            <td class="ps-4"><strong>#SOS-<?= $req['id'] ?></strong></td>
                                            <td class="small text-muted"><?= formatDate($req['created_at'], true) ?></td>
                                            <td class="small fw-semibold"><?= htmlspecialchars($req['emergency_type']) ?></td>
                                            <td class="small text-muted"><?= htmlspecialchars($req['address']) ?> (<?= $req['latitude'] ?>, <?= $req['longitude'] ?>)</td>
                                            <td><?= getStatusBadge($req['status']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
    // Automatically retrieve high-accuracy geolocation coordinates if permitted
    document.addEventListener('DOMContentLoaded', () => {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(pos => {
                document.getElementById('geoLat').value = pos.coords.latitude;
                document.getElementById('geoLng').value = pos.coords.longitude;
            });
        }
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
