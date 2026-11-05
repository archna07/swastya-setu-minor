<?php
/**
 * Swastya Setu - Hospital Management Dashboard
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('hospital');
$user = currentUser();

$db = getDB();

// Get Hospital info linked to user
$hospStmt = $db->prepare("SELECT * FROM hospitals WHERE user_id = ? LIMIT 1");
$hospStmt->execute([$user['id']]);
$hospital = $hospStmt->fetch();

if (!$hospital) {
    $pageTitle = 'Hospital Profile Pending';
    require_once __DIR__ . '/../includes/header.php';
    echo "<div class='container py-5 text-center'>
            <div class='card shadow-sm p-5 max-w-600 mx-auto rounded-4'>
                <i class='fa-solid fa-hospital-user text-warning fs-1 mb-3'></i>
                <h3 class='fw-bold'>Hospital Record Pending</h3>
                <p class='text-muted'>No verified hospital registry is currently linked to your account. Please complete your hospital registration profile or contact support.</p>
                <a href='" . BASE_URL . "/hospital/profile.php' class='btn btn-primary rounded-pill px-4'>Complete Profile</a>
            </div>
          </div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$hospitalId = (int)$hospital['id'];

// Quick Stats using Prepared Statements
$stDoc = $db->prepare("SELECT COUNT(*) FROM doctors WHERE hospital_id = ?");
$stDoc->execute([$hospitalId]);
$totalDoctors = (int)$stDoc->fetchColumn();

$stAppt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE hospital_id = ?");
$stAppt->execute([$hospitalId]);
$totalAppts = (int)$stAppt->fetchColumn();

$stPend = $db->prepare("SELECT COUNT(*) FROM appointments WHERE hospital_id = ? AND status = 'pending'");
$stPend->execute([$hospitalId]);
$pendingAppts = (int)$stPend->fetchColumn();

$stConf = $db->prepare("SELECT COUNT(*) FROM appointments WHERE hospital_id = ? AND status = 'confirmed'");
$stConf->execute([$hospitalId]);
$confirmedAppts = (int)$stConf->fetchColumn();

// Handle Quick Accept/Reject Action from Dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['appt_id'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (verifyCSRFToken($token)) {
        $apptId = (int)$_POST['appt_id'];
        $newStatus = ($_POST['action'] === 'confirm') ? 'confirmed' : 'rejected';
        
        $upd = $db->prepare("UPDATE appointments SET status = ? WHERE id = ? AND hospital_id = ?");
        $upd->execute([$newStatus, $apptId, $hospitalId]);

        // Notify patient
        $ptStmt = $db->prepare("SELECT patient_id FROM appointments WHERE id = ?");
        $ptStmt->execute([$apptId]);
        $ptId = $ptStmt->fetchColumn();
        if ($ptId) {
            createNotification(
                $ptId,
                "Appointment " . ucfirst($newStatus),
                "Your appointment with {$hospital['name']} has been {$newStatus}.",
                'user/appointments.php'
            );
        }

        setFlash('success', "Appointment #{$apptId} marked as {$newStatus}.");
        header('Location: ' . BASE_URL . '/hospital/dashboard.php');
        exit;
    }
}

// Recent Appointments
$stmtRecent = $db->prepare("
    SELECT a.*, u.name AS patient_name, u.phone AS patient_phone, d.name AS doctor_name, d.specialization
    FROM appointments a
    JOIN users u ON a.patient_id = u.id
    JOIN doctors d ON a.doctor_id = d.id
    WHERE a.hospital_id = ?
    ORDER BY a.id DESC LIMIT 8
");
$stmtRecent->execute([$hospitalId]);
$recentAppts = $stmtRecent->fetchAll();

$pageTitle = 'Hospital Management Center';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="content-wrapper">
        <div class="container-fluid p-0">
            
            <?= displayFlash() ?>

            <!-- Welcome Banner -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 text-white p-4" style="background: linear-gradient(135deg, #1E88E5, #1565C0);">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h2 class="fw-bold mb-1"><?= htmlspecialchars($hospital['name']) ?> 🏥</h2>
                        <p class="mb-0 opacity-75"><i class="fa-solid fa-location-dot me-1"></i> <?= htmlspecialchars($hospital['city']) ?> • Bed Capacity: <?= $hospital['available_beds'] ?> Available / <?= $hospital['total_beds'] ?> Total</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="<?= BASE_URL ?>/hospital/manage_doctors.php" class="btn btn-light rounded-pill px-3 fw-semibold text-primary">
                            <i class="fa-solid fa-user-plus me-1"></i> Add Doctor
                        </a>
                        <a href="<?= BASE_URL ?>/hospital/profile.php" class="btn btn-outline-light rounded-pill px-3 fw-semibold">
                            <i class="fa-solid fa-bed me-1"></i> Update Beds
                        </a>
                    </div>
                </div>
            </div>

            <!-- Stats Bar -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                        <span class="small text-muted fw-semibold d-block mb-1">Available Beds</span>
                        <div class="d-flex align-items-center justify-content-between">
                            <h3 class="fw-bold mb-0 text-success"><?= $hospital['available_beds'] ?></h3>
                            <i class="fa-solid fa-bed text-success fs-3 opacity-25"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                        <span class="small text-muted fw-semibold d-block mb-1">Total Doctors</span>
                        <div class="d-flex align-items-center justify-content-between">
                            <h3 class="fw-bold mb-0 text-primary"><?= $totalDoctors ?></h3>
                            <i class="fa-solid fa-user-doctor text-primary fs-3 opacity-25"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                        <span class="small text-muted fw-semibold d-block mb-1">Pending Requests</span>
                        <div class="d-flex align-items-center justify-content-between">
                            <h3 class="fw-bold mb-0 text-warning"><?= $pendingAppts ?></h3>
                            <i class="fa-solid fa-clock text-warning fs-3 opacity-25"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                        <span class="small text-muted fw-semibold d-block mb-1">Confirmed Visits</span>
                        <div class="d-flex align-items-center justify-content-between">
                            <h3 class="fw-bold mb-0 text-info"><?= $confirmedAppts ?></h3>
                            <i class="fa-solid fa-calendar-check text-info fs-3 opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Appointments Table -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-calendar-days text-primary me-2"></i> Patient Appointments Queue</h5>
                    <a href="<?= BASE_URL ?>/hospital/appointments.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">View All &rarr;</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recentAppts)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fa-solid fa-calendar-xmark fs-2 opacity-50 mb-2"></i>
                            <p>No appointments recorded for this hospital.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light small text-muted">
                                    <tr>
                                        <th class="ps-4">Patient Name</th>
                                        <th>Assigned Doctor</th>
                                        <th>Date & Slot</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                        <th class="text-end pe-4">Review Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentAppts as $row): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($row['patient_name']) ?></div>
                                                <small class="text-muted"><i class="fa-solid fa-phone me-1"></i> <?= htmlspecialchars($row['patient_phone']) ?></small>
                                            </td>
                                            <td>
                                                <span class="fw-semibold text-dark small"><?= htmlspecialchars($row['doctor_name']) ?></span>
                                                <small class="d-block text-muted"><?= htmlspecialchars($row['specialization']) ?></small>
                                            </td>
                                            <td class="small">
                                                <div class="fw-semibold text-dark"><?= formatDate($row['appointment_date']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($row['time_slot']) ?></small>
                                            </td>
                                            <td class="small text-muted"><?= htmlspecialchars($row['reason'] ?: 'Routine Checkup') ?></td>
                                            <td><?= getStatusBadge($row['status']) ?></td>
                                            <td class="text-end pe-4">
                                                <?php if ($row['status'] === 'pending'): ?>
                                                    <form method="POST" action="dashboard.php" class="d-inline">
                                                        <?= csrfField() ?>
                                                        <input type="hidden" name="action" value="confirm">
                                                        <input type="hidden" name="appt_id" value="<?= (int)$row['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-success rounded-pill px-3">Accept</button>
                                                    </form>
                                                    <form method="POST" action="dashboard.php" class="d-inline ms-1">
                                                        <?= csrfField() ?>
                                                        <input type="hidden" name="action" value="reject">
                                                        <input type="hidden" name="appt_id" value="<?= (int)$row['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3">Reject</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-muted small">Updated</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
