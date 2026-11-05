<?php
/**
 * Swastya Setu - Hospital Appointments Queue
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('hospital');
$user = currentUser();
$db = getDB();

$hospStmt = $db->prepare("SELECT * FROM hospitals WHERE user_id = ? LIMIT 1");
$hospStmt->execute([$user['id']]);
$hospital = $hospStmt->fetch();

if (!$hospital) {
    setFlash('warning', 'No verified hospital facility is linked to your account.');
    header('Location: ' . BASE_URL . '/hospital/profile.php');
    exit;
}
$hospitalId = (int)$hospital['id'];

// Filter by status
$statusFilter = $_GET['status'] ?? 'all';

// Handle Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['appt_id'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (verifyCSRFToken($token)) {
        $apptId = (int)$_POST['appt_id'];
        $act = $_POST['action'];
        $newStatus = match($act) {
            'confirm' => 'confirmed',
            'complete' => 'completed',
            'reject' => 'rejected',
            default => 'pending'
        };

        $upd = $db->prepare("UPDATE appointments SET status = ? WHERE id = ? AND hospital_id = ?");
        $upd->execute([$newStatus, $apptId, $hospitalId]);

        // Notify Patient
        $ptStmt = $db->prepare("SELECT patient_id FROM appointments WHERE id = ?");
        $ptStmt->execute([$apptId]);
        $ptId = $ptStmt->fetchColumn();
        if ($ptId) {
            createNotification(
                $ptId,
                "Appointment " . ucfirst($newStatus),
                "Your appointment at {$hospital['name']} has been marked as {$newStatus}.",
                'user/appointments.php'
            );
        }

        setFlash('success', "Appointment #{$apptId} updated to {$newStatus}.");
        header('Location: ' . BASE_URL . '/hospital/appointments.php');
        exit;
    }
}

$sql = "SELECT a.*, u.name AS patient_name, u.phone AS patient_phone, u.email AS patient_email, d.name AS doctor_name, d.specialization, d.consultation_fee
        FROM appointments a
        JOIN users u ON a.patient_id = u.id
        JOIN doctors d ON a.doctor_id = d.id
        WHERE a.hospital_id = ?";
$params = [$hospitalId];

if ($statusFilter !== 'all') {
    $sql .= " AND a.status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY a.appointment_date DESC, a.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

$pageTitle = 'Appointments Management Queue';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="content-wrapper">
        <div class="container-fluid p-0">
            
            <?= displayFlash() ?>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h2 class="fw-bold mb-1"><i class="fa-solid fa-calendar-check text-primary me-2"></i> Patient Appointment Bookings</h2>
                    <p class="text-muted small mb-0">Accept, reschedule, or complete consultation bookings for <?= htmlspecialchars($hospital['name']) ?>.</p>
                </div>
                
                <!-- Filter Pills -->
                <div class="d-flex gap-2">
                    <a href="appointments.php?status=all" class="btn btn-sm <?= $statusFilter === 'all' ? 'btn-primary' : 'btn-outline-secondary' ?> rounded-pill px-3">All</a>
                    <a href="appointments.php?status=pending" class="btn btn-sm <?= $statusFilter === 'pending' ? 'btn-warning text-dark' : 'btn-outline-secondary' ?> rounded-pill px-3">Pending</a>
                    <a href="appointments.php?status=confirmed" class="btn btn-sm <?= $statusFilter === 'confirmed' ? 'btn-success' : 'btn-outline-secondary' ?> rounded-pill px-3">Confirmed</a>
                    <a href="appointments.php?status=completed" class="btn btn-sm <?= $statusFilter === 'completed' ? 'btn-info text-white' : 'btn-outline-secondary' ?> rounded-pill px-3">Completed</a>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-0">
                    <?php if (empty($appointments)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fa-solid fa-calendar-xmark fs-2 opacity-50 mb-2"></i>
                            <p>No appointments found under the selected filter.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light small text-muted">
                                    <tr>
                                        <th class="ps-4">Appt ID & Date</th>
                                        <th>Patient Details</th>
                                        <th>Doctor & Specialization</th>
                                        <th>Fee</th>
                                        <th>Reason for Visit</th>
                                        <th>Status</th>
                                        <th class="text-end pe-4">Manage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appointments as $a): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <strong class="text-dark">#APT-<?= $a['id'] ?></strong>
                                                <div class="small text-muted"><?= formatDate($a['appointment_date']) ?> (<?= htmlspecialchars($a['time_slot']) ?>)</div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark"><?= htmlspecialchars($a['patient_name']) ?></div>
                                                <small class="text-muted"><i class="fa-solid fa-phone me-1"></i> <?= htmlspecialchars($a['patient_phone']) ?></small>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark"><?= htmlspecialchars($a['doctor_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($a['specialization']) ?></small>
                                            </td>
                                            <td class="fw-bold small"><?= formatPrice($a['consultation_fee']) ?></td>
                                            <td class="small text-muted" style="max-width: 200px;">
                                                <span class="d-inline-block text-truncate" style="max-width: 180px;">
                                                    <?= htmlspecialchars($a['reason'] ?: 'Routine Health Checkup') ?>
                                                </span>
                                            </td>
                                            <td><?= getStatusBadge($a['status']) ?></td>
                                            <td class="text-end pe-4">
                                                <?php if ($a['status'] === 'pending'): ?>
                                                    <form method="POST" action="appointments.php" class="d-inline">
                                                        <?= csrfField() ?>
                                                        <input type="hidden" name="action" value="confirm">
                                                        <input type="hidden" name="appt_id" value="<?= (int)$a['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-success rounded-pill px-3">Accept</button>
                                                    </form>
                                                    <form method="POST" action="appointments.php" class="d-inline ms-1">
                                                        <?= csrfField() ?>
                                                        <input type="hidden" name="action" value="reject">
                                                        <input type="hidden" name="appt_id" value="<?= (int)$a['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3">Reject</button>
                                                    </form>
                                                <?php elseif ($a['status'] === 'confirmed'): ?>
                                                    <form method="POST" action="appointments.php" class="d-inline">
                                                        <?= csrfField() ?>
                                                        <input type="hidden" name="action" value="complete">
                                                        <input type="hidden" name="appt_id" value="<?= (int)$a['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill px-3">Mark Done</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-muted small">Closed</span>
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
