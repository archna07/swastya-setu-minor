<?php
/**
 * Swastya Setu - Patient / User Dashboard
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('patient');
$user = currentUser();
$db = getDB();

// Fetch Patient Medical Profile
$profStmt = $db->prepare("SELECT * FROM user_profiles WHERE user_id = ? LIMIT 1");
$profStmt->execute([$user['id']]);
$profile = $profStmt->fetch() ?: [];

// Fetch Counts from MySQL with prepared statements
$stPred = $db->prepare("SELECT COUNT(*) FROM predictions WHERE user_id = ?");
$stPred->execute([$user['id']]);
$totalPreds = (int)$stPred->fetchColumn();

$stAppt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ?");
$stAppt->execute([$user['id']]);
$totalAppts = (int)$stAppt->fetchColumn();

$stRx = $db->prepare("SELECT COUNT(*) FROM prescriptions WHERE patient_id = ?");
$stRx->execute([$user['id']]);
$totalPrescriptions = (int)$stRx->fetchColumn();

$stOrd = $db->prepare("SELECT COUNT(*) FROM orders WHERE patient_id = ?");
$stOrd->execute([$user['id']]);
$totalOrders = (int)$stOrd->fetchColumn();

// Fetch Upcoming Appointment
$apptStmt = $db->prepare("
    SELECT a.*, d.name AS doctor_name, d.specialization, h.name AS hospital_name, h.address AS hospital_address
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN hospitals h ON a.hospital_id = h.id
    WHERE a.patient_id = ? AND a.appointment_date >= CURDATE() AND a.status IN ('pending', 'confirmed')
    ORDER BY a.appointment_date ASC LIMIT 1
");
$apptStmt->execute([$user['id']]);
$upcomingAppt = $apptStmt->fetch();

// Fetch Recent Medicine Order
$orderStmt = $db->prepare("
    SELECT o.*, ph.name AS pharmacy_name
    FROM orders o
    JOIN pharmacies ph ON o.pharmacy_id = ph.id
    WHERE o.patient_id = ?
    ORDER BY o.id DESC LIMIT 1
");
$orderStmt->execute([$user['id']]);
$recentOrder = $orderStmt->fetch();

// Fetch Recent Notifications with prepared statement
$notifStmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 5");
$notifStmt->execute([$user['id']]);
$notifs = $notifStmt->fetchAll();

$pageTitle = 'Patient Health Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="content-wrapper">
        <div class="container-fluid p-0">
            
            <?= displayFlash() ?>

            <!-- Welcome Card -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 text-white p-4" style="background: linear-gradient(135deg, #1E88E5 0%, #26C6DA 100%);">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h2 class="fw-bold mb-1">Welcome back, <?= htmlspecialchars($user['name']) ?>! 👋</h2>
                        <p class="mb-0 opacity-90">
                            Blood Group: <strong class="badge bg-white text-primary px-2 py-1"><?= htmlspecialchars($profile['blood_group'] ?? 'O+') ?></strong> 
                            • Emergency Contact: <?= htmlspecialchars($profile['emergency_contact_phone'] ?? '+91 9876543210') ?>
                        </p>
                    </div>
                    <a href="<?= BASE_URL ?>/user/profile.php" class="btn btn-outline-light rounded-pill px-4 fw-semibold">
                        <i class="fa-solid fa-id-card me-1"></i> Edit Medical Passport
                    </a>
                </div>
            </div>

            <!-- Health Summary & Metrics Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                        <span class="small text-muted fw-semibold d-block mb-1">AI Diagnoses</span>
                        <div class="d-flex align-items-center justify-content-between">
                            <h3 class="fw-bold mb-0 text-primary"><?= $totalPreds ?></h3>
                            <i class="fa-solid fa-wand-magic-sparkles text-primary fs-3 opacity-25"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                        <span class="small text-muted fw-semibold d-block mb-1">Doctor Appointments</span>
                        <div class="d-flex align-items-center justify-content-between">
                            <h3 class="fw-bold mb-0 text-success"><?= $totalAppts ?></h3>
                            <i class="fa-solid fa-calendar-check text-success fs-3 opacity-25"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                        <span class="small text-muted fw-semibold d-block mb-1">Prescriptions Uploaded</span>
                        <div class="d-flex align-items-center justify-content-between">
                            <h3 class="fw-bold mb-0 text-info"><?= $totalPrescriptions ?></h3>
                            <i class="fa-solid fa-file-prescription text-info fs-3 opacity-25"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                        <span class="small text-muted fw-semibold d-block mb-1">Medicine Orders</span>
                        <div class="d-flex align-items-center justify-content-between">
                            <h3 class="fw-bold mb-0 text-warning"><?= $totalOrders ?></h3>
                            <i class="fa-solid fa-truck-fast text-warning fs-3 opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Medical Actions -->
            <h5 class="fw-bold mb-3"><i class="fa-solid fa-bolt text-warning me-2"></i> Quick Healthcare Actions</h5>
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <a href="<?= BASE_URL ?>/user/prediction.php" class="card border-0 shadow-sm rounded-4 p-3 text-center text-decoration-none hover-lift bg-white">
                        <i class="fa-solid fa-stethoscope fs-2 text-primary mb-2"></i>
                        <strong class="text-dark d-block">Predict Disease</strong>
                        <small class="text-muted">AI Symptom Checker</small>
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="<?= BASE_URL ?>/user/hospitals.php" class="card border-0 shadow-sm rounded-4 p-3 text-center text-decoration-none hover-lift bg-white">
                        <i class="fa-solid fa-hospital fs-2 text-info mb-2"></i>
                        <strong class="text-dark d-block">Search Hospitals</strong>
                        <small class="text-muted">Live Bed Tracker</small>
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="<?= BASE_URL ?>/user/pharmacies.php" class="card border-0 shadow-sm rounded-4 p-3 text-center text-decoration-none hover-lift bg-white">
                        <i class="fa-solid fa-store fs-2 text-success mb-2"></i>
                        <strong class="text-dark d-block">Find Pharmacies</strong>
                        <small class="text-muted">24x7 Express Hubs</small>
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="<?= BASE_URL ?>/user/upload_prescription.php" class="card border-0 shadow-sm rounded-4 p-3 text-center text-decoration-none hover-lift bg-white">
                        <i class="fa-solid fa-file-arrow-up fs-2 text-warning mb-2"></i>
                        <strong class="text-dark d-block">Upload Rx</strong>
                        <small class="text-muted">Home Delivery Quote</small>
                    </a>
                </div>
            </div>

            <div class="row g-4">
                
                <!-- Upcoming Appointments & Orders Section -->
                <div class="col-lg-7">
                    
                    <!-- Upcoming Appointment Box -->
                    <div class="card border-0 shadow-sm rounded-4 mb-4">
                        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-calendar-check text-primary me-2"></i> Next Consultation</h5>
                            <a href="<?= BASE_URL ?>/user/appointments.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">View All &rarr;</a>
                        </div>
                        <div class="card-body p-4">
                            <?php if ($upcomingAppt): ?>
                                <div class="d-flex align-items-start gap-3">
                                    <div class="p-3 rounded-circle bg-primary-subtle text-primary">
                                        <i class="fa-solid fa-user-doctor fs-3"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <h6 class="fw-bold text-dark mb-0"><?= htmlspecialchars($upcomingAppt['doctor_name']) ?></h6>
                                            <?= getStatusBadge($upcomingAppt['status']) ?>
                                        </div>
                                        <p class="text-muted small mb-1"><?= htmlspecialchars($upcomingAppt['specialization']) ?> • <?= htmlspecialchars($upcomingAppt['hospital_name']) ?></p>
                                        <div class="small fw-semibold text-primary">
                                            <i class="fa-solid fa-calendar me-1"></i> <?= formatDate($upcomingAppt['appointment_date']) ?> at <?= htmlspecialchars($upcomingAppt['time_slot']) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fa-solid fa-calendar-xmark fs-2 opacity-50 mb-2"></i>
                                    <p class="small mb-2">No upcoming consultations booked.</p>
                                    <a href="<?= BASE_URL ?>/user/appointments.php" class="btn btn-sm btn-primary rounded-pill px-3">Book Doctor Visit</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Order Status Box -->
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-truck-fast text-primary me-2"></i> Active Delivery Order</h5>
                            <a href="<?= BASE_URL ?>/user/my_orders.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">Track Orders &rarr;</a>
                        </div>
                        <div class="card-body p-4">
                            <?php if ($recentOrder): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong class="text-dark">#<?= htmlspecialchars($recentOrder['order_number']) ?></strong>
                                    <?= getStatusBadge($recentOrder['order_status']) ?>
                                </div>
                                <p class="text-muted small mb-2">Pharmacy: <?= htmlspecialchars($recentOrder['pharmacy_name']) ?> • Total: <strong class="text-primary"><?= formatPrice($recentOrder['total_amount']) ?></strong> (COD)</p>
                                <a href="<?= BASE_URL ?>/user/my_orders.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                    View Live Progress Timeline &rarr;
                                </a>
                            <?php else: ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fa-solid fa-box-open fs-2 opacity-50 mb-2"></i>
                                    <p class="small mb-2">No current medicine orders.</p>
                                    <a href="<?= BASE_URL ?>/user/upload_prescription.php" class="btn btn-sm btn-primary rounded-pill px-3">Upload Prescription</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

                <!-- Recent Notifications & Vitals Box -->
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                        <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-bell text-info me-2"></i> System Notifications</h5>
                        <?php if (empty($notifs)): ?>
                            <p class="text-muted small mb-0">No notifications at this time.</p>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-3">
                                <?php foreach ($notifs as $n): ?>
                                    <div class="p-2 rounded-3 bg-light border small">
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($n['title']) ?></div>
                                        <div class="text-muted" style="font-size: 0.8rem;"><?= htmlspecialchars($n['message']) ?></div>
                                        <small class="text-muted mt-1 d-block" style="font-size: 0.72rem;"><?= formatDate($n['created_at'], true) ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="card border-0 shadow-sm rounded-4 p-4 bg-light border">
                        <h6 class="fw-bold text-danger mb-2"><i class="fa-solid fa-truck-medical me-1"></i> Need Emergency Assistance?</h6>
                        <p class="small text-muted mb-3">Trigger an immediate 108 Emergency Ambulance broadcast with your GPS coordinates.</p>
                        <a href="<?= BASE_URL ?>/user/sos.php" class="btn btn-danger btn-sm rounded-pill px-4 fw-semibold shadow-sm w-100">
                            <i class="fa-solid fa-phone-volume me-1"></i> Trigger 108 Emergency SOS
                        </a>
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
