<?php
/**
 * Swastya Setu - Administrator Global Appointments Monitor
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');
$db = getDB();

$appts = $db->query("
    SELECT a.*, u.name AS patient_name, u.phone AS patient_phone, d.name AS doctor_name, d.specialization, h.name AS hospital_name
    FROM appointments a
    JOIN users u ON a.patient_id = u.id
    JOIN doctors d ON a.doctor_id = d.id
    JOIN hospitals h ON a.hospital_id = h.id
    ORDER BY a.id DESC
")->fetchAll();

$pageTitle = 'Global Appointments Registry';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="content-wrapper">
        <div class="container-fluid p-0">
            
            <?= displayFlash() ?>

            <div class="mb-4">
                <h2 class="fw-bold mb-1"><i class="fa-solid fa-calendar-check text-primary me-2"></i> System-Wide Doctor Appointments</h2>
                <p class="text-muted small mb-0">Global audit of all patient consultations, hospital schedules, and booking statuses.</p>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-muted">
                                <tr>
                                    <th class="ps-4">Appt ID</th>
                                    <th>Patient</th>
                                    <th>Doctor</th>
                                    <th>Hospital</th>
                                    <th>Date & Slot</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appts as $a): ?>
                                    <tr>
                                        <td class="ps-4"><strong>#APT-<?= $a['id'] ?></strong></td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= htmlspecialchars($a['patient_name']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($a['patient_phone']) ?></small>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= htmlspecialchars($a['doctor_name']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($a['specialization']) ?></small>
                                        </td>
                                        <td class="small text-muted"><?= htmlspecialchars($a['hospital_name']) ?></td>
                                        <td class="small">
                                            <strong><?= formatDate($a['appointment_date']) ?></strong>
                                            <div class="text-muted"><?= htmlspecialchars($a['time_slot']) ?></div>
                                        </td>
                                        <td><?= getStatusBadge($a['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
