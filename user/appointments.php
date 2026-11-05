<?php
/**
 * Swastya Setu - Doctor Appointments & Booking Portal
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('patient');
$user = currentUser();

$error = '';
$selectedHospId = isset($_GET['hospital_id']) ? (int)$_GET['hospital_id'] : 0;
$selectedDocId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;

// Handle Appointment Booking Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'book') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($token)) {
        $error = 'Security session expired. Please refresh.';
    } else {
        $doctorId = (int)($_POST['doctor_id'] ?? 0);
        $apptDate = trim($_POST['appointment_date'] ?? '');
        $timeSlot = trim($_POST['time_slot'] ?? '');
        $reason = trim($_POST['reason'] ?? '');

        if (!$doctorId || empty($apptDate) || empty($timeSlot)) {
            $error = 'Please select a doctor, appointment date, and time slot.';
        } elseif (strtotime($apptDate) < strtotime(date('Y-m-d'))) {
            $error = 'Appointment date cannot be in the past.';
        } else {
            try {
                $db = getDB();
                // Get hospital id from doctor
                $docStmt = $db->prepare("SELECT hospital_id, name FROM doctors WHERE id = ? AND is_active = 1");
                $docStmt->execute([$doctorId]);
                $doctorInfo = $docStmt->fetch();

                if (!$doctorInfo) {
                    $error = 'Selected doctor is not available for appointments.';
                } else {
                    $hospitalId = $doctorInfo['hospital_id'];
                    $ins = $db->prepare("INSERT INTO appointments (patient_id, doctor_id, hospital_id, appointment_date, time_slot, reason, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
                    $ins->execute([$user['id'], $doctorId, $hospitalId, $apptDate, $timeSlot, $reason]);

                    createNotification(
                        $user['id'],
                        'Appointment Requested',
                        "Your appointment request with {$doctorInfo['name']} on {$apptDate} ({$timeSlot}) was submitted. Awaiting hospital confirmation.",
                        'user/appointments.php'
                    );

                    setFlash('success', "Appointment request submitted successfully with {$doctorInfo['name']}!");
                    header('Location: ' . BASE_URL . '/user/appointments.php');
                    exit;
                }
            } catch (Exception $e) {
                $error = 'Error booking appointment: ' . $e->getMessage();
            }
        }
    }
}

// Handle Appointment Cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    $token = $_POST['csrf_token'] ?? '';
    if (verifyCSRFToken($token)) {
        $cancelId = (int)($_POST['appointment_id'] ?? 0);
        try {
            $db = getDB();
            $upd = $db->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ? AND patient_id = ? AND status = 'pending'");
            $upd->execute([$cancelId, $user['id']]);
            if ($upd->rowCount() > 0) {
                setFlash('info', 'Your appointment request has been cancelled.');
            }
        } catch (Exception $e) {}
        header('Location: ' . BASE_URL . '/user/appointments.php');
        exit;
    }
} elseif (isset($_GET['cancel_id'])) {
    $cancelId = (int)$_GET['cancel_id'];
    try {
        $db = getDB();
        $upd = $db->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ? AND patient_id = ? AND status = 'pending'");
        $upd->execute([$cancelId, $user['id']]);
        if ($upd->rowCount() > 0) {
            setFlash('info', 'Your appointment request has been cancelled.');
        }
    } catch (Exception $e) {}
    header('Location: ' . BASE_URL . '/user/appointments.php');
    exit;
}

// Fetch Active Doctors
$doctors = [];
try {
    $db = getDB();
    $sql = "SELECT d.*, h.name AS hospital_name, h.city AS hospital_city 
            FROM doctors d 
            JOIN hospitals h ON d.hospital_id = h.id 
            WHERE d.is_active = 1";
    $params = [];
    if ($selectedHospId > 0) {
        $sql .= " AND d.hospital_id = ?";
        $params[] = $selectedHospId;
    }
    $sql .= " ORDER BY d.name ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $doctors = $stmt->fetchAll();
} catch (Exception $e) {}

// Fetch Patient's Existing Appointments
$myAppointments = [];
try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT a.*, d.name AS doctor_name, d.specialization, d.consultation_fee, h.name AS hospital_name, h.address AS hospital_address
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.id
        JOIN hospitals h ON a.hospital_id = h.id
        WHERE a.patient_id = ?
        ORDER BY a.appointment_date DESC, a.id DESC
    ");
    $stmt->execute([$user['id']]);
    $myAppointments = $stmt->fetchAll();
} catch (Exception $e) {}

$pageTitle = 'Doctor Appointments & Teleconsultations';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="content-wrapper">
        <div class="container-fluid p-0">
            
            <?= displayFlash() ?>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h2 class="fw-bold mb-1"><i class="fa-solid fa-calendar-check text-primary me-2"></i> Doctor Consultations & Appointments</h2>
                    <p class="text-muted small mb-0">Schedule clinic visits and video consultations with top medical specialists.</p>
                </div>
                <button type="button" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#bookApptModal">
                    <i class="fa-solid fa-plus me-1"></i> Book New Consultation
                </button>
            </div>

            <!-- Booking Modal -->
            <div class="modal fade" id="bookApptModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg rounded-4">
                        <div class="modal-header border-bottom py-3">
                            <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-calendar-plus text-primary me-2"></i> Book Doctor Consultation</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST" action="appointments.php">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="book">
                            
                            <div class="modal-body p-4">
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold text-muted">Select Specialist Doctor *</label>
                                    <select name="doctor_id" id="modalDoctorSelect" class="form-select rounded-3" required onchange="updateSlots()">
                                        <option value="">-- Choose Doctor --</option>
                                        <?php foreach ($doctors as $d): ?>
                                            <option value="<?= $d['id'] ?>" data-slots="<?= htmlspecialchars($d['available_time_slots']) ?>" <?= ($selectedDocId == $d['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($d['name']) ?> (<?= htmlspecialchars($d['specialization']) ?>) - <?= htmlspecialchars($d['hospital_name']) ?> [<?= formatPrice($d['consultation_fee']) ?>]
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-muted">Appointment Date *</label>
                                        <input type="date" name="appointment_date" class="form-control rounded-3" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-muted">Preferred Time Slot *</label>
                                        <select name="time_slot" id="modalTimeSlotSelect" class="form-select rounded-3" required>
                                            <option value="10:00 AM">10:00 AM</option>
                                            <option value="11:30 AM">11:30 AM</option>
                                            <option value="02:00 PM">02:00 PM</option>
                                            <option value="04:30 PM">04:30 PM</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-semibold text-muted">Symptoms / Reason for Visit</label>
                                    <textarea name="reason" class="form-control rounded-3" rows="3" placeholder="Briefly describe your symptoms or reason for consulting the doctor..."></textarea>
                                </div>
                            </div>
                            
                            <div class="modal-footer border-top p-3">
                                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">Confirm Booking</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Existing Appointments List -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-list-check text-primary me-2"></i> Your Scheduled Appointments</h5>
                    <span class="badge bg-primary-subtle text-primary rounded-pill px-3"><?= count($myAppointments) ?> Total</span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($myAppointments)): ?>
                        <div class="text-center py-5">
                            <i class="fa-solid fa-calendar-xmark fs-1 text-muted opacity-50 mb-3"></i>
                            <h6 class="text-dark">No Appointments Booked Yet</h6>
                            <p class="text-muted small">Choose a specialist doctor below and schedule your consultation in minutes.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light small text-muted">
                                    <tr>
                                        <th class="ps-4">Doctor & Hospital</th>
                                        <th>Date & Slot</th>
                                        <th>Fee</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                        <th class="text-end pe-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($myAppointments as $appt): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class="fa-solid fa-user-doctor fs-3 text-primary"></i>
                                                    <div>
                                                        <strong class="text-dark d-block"><?= htmlspecialchars($appt['doctor_name']) ?></strong>
                                                        <small class="text-muted"><?= htmlspecialchars($appt['specialization']) ?> • <?= htmlspecialchars($appt['hospital_name']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark small"><?= formatDate($appt['appointment_date']) ?></div>
                                                <small class="text-muted"><i class="fa-solid fa-clock me-1"></i> <?= htmlspecialchars($appt['time_slot']) ?></small>
                                            </td>
                                            <td class="fw-bold small"><?= formatPrice($appt['consultation_fee']) ?></td>
                                            <td class="small text-muted" style="max-width: 200px;">
                                                <span class="d-inline-block text-truncate" style="max-width: 180px;">
                                                    <?= htmlspecialchars($appt['reason'] ?: 'Routine Checkup') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?= getStatusBadge($appt['status']) ?>
                                            </td>
                                            <td class="text-end pe-4">
                                                <?php if ($appt['status'] === 'pending'): ?>
                                                    <form method="POST" action="appointments.php" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this appointment request?')">
                                                        <?= csrfField() ?>
                                                        <input type="hidden" name="action" value="cancel">
                                                        <input type="hidden" name="appointment_id" value="<?= (int)$appt['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3">Cancel</button>
                                                    </form>
                                                <?php elseif ($appt['status'] === 'confirmed'): ?>
                                                    <button class="btn btn-sm btn-success rounded-pill px-3" onclick="alert('Teleconsultation video link will be active 15 minutes before the slot time!')">
                                                        <i class="fa-solid fa-video me-1"></i> Join Call
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted small">No actions</span>
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

            <!-- Specialist Doctors Directory -->
            <h5 class="fw-bold mb-3"><i class="fa-solid fa-user-doctor text-primary me-2"></i> Verified Specialist Doctors</h5>
            <div class="row g-4">
                <?php foreach ($doctors as $doc): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card border-0 shadow-sm rounded-4 h-100 hover-lift">
                            <div class="card-body p-4 d-flex flex-column">
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <div class="p-3 rounded-circle bg-primary-subtle text-primary">
                                        <i class="fa-solid fa-user-doctor fs-3"></i>
                                    </div>
                                    <div>
                                        <h5 class="fw-bold text-dark mb-0"><?= htmlspecialchars($doc['name']) ?></h5>
                                        <span class="badge bg-info-subtle text-info fw-semibold"><?= htmlspecialchars($doc['specialization']) ?></span>
                                    </div>
                                </div>

                                <p class="small text-muted mb-2">
                                    <i class="fa-solid fa-graduation-cap text-secondary me-1"></i> <?= htmlspecialchars($doc['qualification']) ?> (<?= $doc['experience_years'] ?> Yrs Exp)
                                </p>
                                <p class="small text-muted mb-2">
                                    <i class="fa-solid fa-hospital text-secondary me-1"></i> <?= htmlspecialchars($doc['hospital_name']) ?>
                                </p>
                                <p class="small text-muted mb-3">
                                    <i class="fa-solid fa-clock text-secondary me-1"></i> Slots: <?= htmlspecialchars($doc['available_time_slots']) ?>
                                </p>

                                <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                                    <div class="fw-bold fs-6 text-primary"><?= formatPrice($doc['consultation_fee']) ?></div>
                                    <button class="btn btn-primary btn-sm rounded-pill px-3 fw-semibold shadow-sm" onclick="openBookingFor(<?= $doc['id'] ?>, '<?= htmlspecialchars(addslashes($doc['available_time_slots'])) ?>')">
                                        <i class="fa-solid fa-calendar-plus me-1"></i> Book Slot
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
    </div>
</div>

<script>
    function openBookingFor(docId, slotsStr) {
        const select = document.getElementById('modalDoctorSelect');
        select.value = docId;
        updateSlots();
        const modal = new bootstrap.Modal(document.getElementById('bookApptModal'));
        modal.show();
    }

    function updateSlots() {
        const select = document.getElementById('modalDoctorSelect');
        const selectedOption = select.options[select.selectedIndex];
        const slotsStr = selectedOption.getAttribute('data-slots') || '';
        const slotSelect = document.getElementById('modalTimeSlotSelect');
        slotSelect.innerHTML = '';

        if (slotsStr) {
            const slots = slotsStr.split(',');
            slots.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.trim();
                opt.innerText = s.trim();
                slotSelect.appendChild(opt);
            });
        } else {
            ['10:00 AM', '11:30 AM', '02:00 PM', '04:30 PM'].forEach(s => {
                const opt = document.createElement('option');
                opt.value = s;
                opt.innerText = s;
                slotSelect.appendChild(opt);
            });
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
