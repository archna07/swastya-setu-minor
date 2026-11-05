<?php
/**
 * Swastya Setu - Administrator Hospital Management & Approvals
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');
$db = getDB();

// Handle Approve / Reject / Delete Actions with CSRF Protection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['id'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (verifyCSRFToken($token)) {
        $id = (int)$_POST['id'];
        $act = $_POST['action'];

        if ($act === 'approve') {
            $stmt = $db->prepare("UPDATE hospitals SET status = 'approved' WHERE id = ?");
            $stmt->execute([$id]);
            setFlash('success', "Hospital #{$id} has been approved.");
        } elseif ($act === 'reject') {
            $stmt = $db->prepare("UPDATE hospitals SET status = 'rejected' WHERE id = ?");
            $stmt->execute([$id]);
            setFlash('warning', "Hospital #{$id} registration has been rejected.");
        } elseif ($act === 'delete') {
            $stmt = $db->prepare("DELETE FROM hospitals WHERE id = ?");
            $stmt->execute([$id]);
            setFlash('danger', "Hospital #{$id} record removed from database.");
        }
        header('Location: ' . BASE_URL . '/admin/hospitals.php');
        exit;
    }
}

$hospitals = $db->query("SELECT * FROM hospitals ORDER BY id DESC")->fetchAll();

$pageTitle = 'Manage Hospitals & Approvals';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="content-wrapper">
        <div class="container-fluid p-0">
            
            <?= displayFlash() ?>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-1"><i class="fa-solid fa-hospital text-primary me-2"></i> Hospital Partner Approvals & Network</h2>
                    <p class="text-muted small mb-0">Review hospital registrations, verify medical licenses, and audit available ICU beds.</p>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-muted">
                                <tr>
                                    <th class="ps-4">ID & Hospital Name</th>
                                    <th>Category</th>
                                    <th>City & Phone</th>
                                    <th>Beds Available</th>
                                    <th>Facilities</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Admin Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($hospitals as $h): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <strong class="text-dark d-block"><?= htmlspecialchars($h['name']) ?></strong>
                                            <small class="text-muted"><?= htmlspecialchars($h['address']) ?></small>
                                        </td>
                                        <td><span class="badge bg-light text-dark border"><?= $h['hospital_type'] ?></span></td>
                                        <td>
                                            <div class="small fw-semibold"><?= htmlspecialchars($h['city']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($h['phone']) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-success-subtle text-success fw-bold px-2 py-1">
                                                <?= $h['available_beds'] ?> / <?= $h['total_beds'] ?> Beds
                                            </span>
                                        </td>
                                        <td class="small text-muted">
                                            <?= $h['emergency_services'] ? '<span class="text-danger me-1">24x7</span>' : '' ?>
                                            <?= $h['icu_available'] ? '<span class="text-info me-1">ICU</span>' : '' ?>
                                            <?= $h['blood_bank'] ? '<span class="text-danger">Blood</span>' : '' ?>
                                        </td>
                                        <td><?= getStatusBadge($h['status']) ?></td>
                                        <td class="text-end pe-4">
                                            <?php if ($h['status'] === 'pending'): ?>
                                                <form method="POST" action="hospitals.php" class="d-inline">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="approve">
                                                    <input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-success rounded-pill px-3">Approve</button>
                                                </form>
                                                <form method="POST" action="hospitals.php" class="d-inline ms-1">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="reject">
                                                    <input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-warning rounded-pill px-3">Reject</button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" action="hospitals.php" class="d-inline ms-1" onsubmit="return confirm('Are you sure you want to delete this hospital record?')">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
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
