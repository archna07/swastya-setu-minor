<?php
/**
 * Swastya Setu - Manage Doctors (Hospital Panel)
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

$error = '';

// Add or Edit Doctor
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($token)) {
        $error = 'Security session expired. Please refresh.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $spec = trim($_POST['specialization'] ?? '');
        $qual = trim($_POST['qualification'] ?? '');
        $exp = (int)($_POST['experience_years'] ?? 5);
        $fee = (float)($_POST['consultation_fee'] ?? 500);
        $days = trim($_POST['available_days'] ?? 'Mon,Tue,Wed,Thu,Fri');
        $slots = trim($_POST['available_time_slots'] ?? '10:00 AM, 11:30 AM, 02:00 PM, 04:30 PM');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($name) || empty($spec) || empty($qual)) {
            $error = 'Doctor name, specialization, and qualifications are required.';
        } else {
            try {
                if (isset($_POST['doctor_id']) && !empty($_POST['doctor_id'])) {
                    $docId = (int)$_POST['doctor_id'];
                    $upd = $db->prepare("UPDATE doctors SET name = ?, specialization = ?, qualification = ?, experience_years = ?, consultation_fee = ?, available_days = ?, available_time_slots = ?, phone = ? WHERE id = ? AND hospital_id = ?");
                    $upd->execute([$name, $spec, $qual, $exp, $fee, $days, $slots, $phone, $docId, $hospitalId]);
                    setFlash('success', "Doctor {$name} details updated successfully.");
                } else {
                    $ins = $db->prepare("INSERT INTO doctors (hospital_id, name, specialization, qualification, experience_years, consultation_fee, available_days, available_time_slots, phone, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
                    $ins->execute([$hospitalId, $name, $spec, $qual, $exp, $fee, $days, $slots, $phone]);
                    setFlash('success', "Doctor {$name} added to hospital roster.");
                }
                header('Location: ' . BASE_URL . '/hospital/manage_doctors.php');
                exit;
            } catch (Exception $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Toggle Doctor Status
if (isset($_GET['toggle_id'])) {
    $docId = (int)$_GET['toggle_id'];
    $stmtT = $db->prepare("UPDATE doctors SET is_active = NOT is_active WHERE id = ? AND hospital_id = ?");
    $stmtT->execute([$docId, $hospitalId]);
    setFlash('info', 'Doctor status updated.');
    header('Location: ' . BASE_URL . '/hospital/manage_doctors.php');
    exit;
}

// Delete Doctor
if (isset($_GET['delete_id'])) {
    $docId = (int)$_GET['delete_id'];
    $stmtD = $db->prepare("DELETE FROM doctors WHERE id = ? AND hospital_id = ?");
    $stmtD->execute([$docId, $hospitalId]);
    setFlash('danger', 'Doctor removed from roster.');
    header('Location: ' . BASE_URL . '/hospital/manage_doctors.php');
    exit;
}

// Fetch Doctors
$stmt = $db->prepare("SELECT * FROM doctors WHERE hospital_id = ? ORDER BY id DESC");
$stmt->execute([$hospitalId]);
$doctors = $stmt->fetchAll();

$pageTitle = 'Manage Hospital Doctors';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="content-wrapper">
        <div class="container-fluid p-0">
            
            <?= displayFlash() ?>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h2 class="fw-bold mb-1"><i class="fa-solid fa-user-doctor text-primary me-2"></i> Manage Medical Staff</h2>
                    <p class="text-muted small mb-0">Register doctors, configure consultation fees, and set booking time slots.</p>
                </div>
                <button type="button" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#addDoctorModal">
                    <i class="fa-solid fa-plus me-1"></i> Add New Doctor
                </button>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger rounded-4 shadow-sm mb-4"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Add/Edit Doctor Modal -->
            <div class="modal fade" id="addDoctorModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content border-0 shadow-lg rounded-4">
                        <div class="modal-header border-bottom py-3">
                            <h5 class="modal-title fw-bold" id="docModalTitle"><i class="fa-solid fa-user-doctor text-primary me-2"></i> Add Doctor to Roster</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST" action="manage_doctors.php">
                            <?= csrfField() ?>
                            <input type="hidden" name="doctor_id" id="editDoctorId" value="">
                            
                            <div class="modal-body p-4">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-muted">Doctor Full Name *</label>
                                        <input type="text" name="name" id="docName" class="form-control rounded-3" placeholder="Dr. Ananya Roy" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-muted">Specialization *</label>
                                        <input type="text" name="specialization" id="docSpec" class="form-control rounded-3" placeholder="e.g. Cardiology, Neurology" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-muted">Medical Qualifications *</label>
                                        <input type="text" name="qualification" id="docQual" class="form-control rounded-3" placeholder="e.g. MBBS, MD, DM" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-semibold text-muted">Experience (Years)</label>
                                        <input type="number" name="experience_years" id="docExp" class="form-control rounded-3" value="10">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-semibold text-muted">Consultation Fee (₹)</label>
                                        <input type="number" name="consultation_fee" id="docFee" class="form-control rounded-3" value="700">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-muted">Available Days</label>
                                        <input type="text" name="available_days" id="docDays" class="form-control rounded-3" value="Mon,Tue,Wed,Thu,Fri">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-muted">Phone Number</label>
                                        <input type="tel" name="phone" id="docPhone" class="form-control rounded-3" placeholder="+91 9811000000">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold text-muted">Available Time Slots (Comma Separated)</label>
                                        <input type="text" name="available_time_slots" id="docSlots" class="form-control rounded-3" value="10:00 AM, 11:30 AM, 02:00 PM, 04:30 PM">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="modal-footer border-top p-3">
                                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">Save Doctor</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Doctors List Table -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-muted">
                                <tr>
                                    <th class="ps-4">Doctor</th>
                                    <th>Specialization</th>
                                    <th>Fee</th>
                                    <th>Working Days & Slots</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($doctors as $d): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($d['name']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($d['qualification']) ?> (<?= $d['experience_years'] ?> Yrs)</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary-subtle text-primary fw-semibold small"><?= htmlspecialchars($d['specialization']) ?></span>
                                        </td>
                                        <td class="fw-bold"><?= formatPrice($d['consultation_fee']) ?></td>
                                        <td class="small text-muted" style="max-width: 260px;">
                                            <div><strong>Days:</strong> <?= htmlspecialchars($d['available_days']) ?></div>
                                            <div class="text-truncate"><strong>Slots:</strong> <?= htmlspecialchars($d['available_time_slots']) ?></div>
                                        </td>
                                        <td>
                                            <span class="badge <?= $d['is_active'] ? 'bg-success' : 'bg-secondary' ?> rounded-pill px-2 py-1">
                                                <?= $d['is_active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="manage_doctors.php?toggle_id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-2" title="Toggle Status">
                                                <i class="fa-solid fa-power-off"></i>
                                            </a>
                                            <a href="manage_doctors.php?delete_id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-danger rounded-pill px-2 ms-1" onclick="return confirmAction('Delete this doctor record?')">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                        </td>
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
