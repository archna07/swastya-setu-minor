<?php
/**
 * Swastya Setu - Administrator Pharmacy Approvals & Registry
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['id'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (verifyCSRFToken($token)) {
        $id = (int)$_POST['id'];
        $act = $_POST['action'];

        if ($act === 'approve') {
            $stmt = $db->prepare("UPDATE pharmacies SET status = 'approved' WHERE id = ?");
            $stmt->execute([$id]);
            setFlash('success', "Pharmacy #{$id} has been approved.");
        } elseif ($act === 'reject') {
            $stmt = $db->prepare("UPDATE pharmacies SET status = 'rejected' WHERE id = ?");
            $stmt->execute([$id]);
            setFlash('warning', "Pharmacy #{$id} has been rejected.");
        } elseif ($act === 'delete') {
            $stmt = $db->prepare("DELETE FROM pharmacies WHERE id = ?");
            $stmt->execute([$id]);
            setFlash('danger', "Pharmacy #{$id} deleted.");
        }
        header('Location: ' . BASE_URL . '/admin/pharmacies.php');
        exit;
    }
}

$pharmacies = $db->query("SELECT * FROM pharmacies ORDER BY id DESC")->fetchAll();

$pageTitle = 'Manage Pharmacies & Approvals';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="content-wrapper">
        <div class="container-fluid p-0">
            
            <?= displayFlash() ?>

            <div class="mb-4">
                <h2 class="fw-bold mb-1"><i class="fa-solid fa-store text-primary me-2"></i> Pharmacy Partner Approvals</h2>
                <p class="text-muted small mb-0">Audit retail drug licenses, delivery capabilities, and verify partner pharmacy accounts.</p>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-muted">
                                <tr>
                                    <th class="ps-4">Pharmacy Name</th>
                                    <th>Owner & License</th>
                                    <th>City & Phone</th>
                                    <th>Services</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pharmacies as $p): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <strong class="text-dark d-block"><?= htmlspecialchars($p['name']) ?></strong>
                                            <small class="text-muted"><?= htmlspecialchars($p['address']) ?></small>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold"><?= htmlspecialchars($p['owner_name']) ?></div>
                                            <small class="text-muted">Lic: <?= htmlspecialchars($p['license_number']) ?></small>
                                        </td>
                                        <td>
                                            <div class="small"><?= htmlspecialchars($p['city']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($p['phone']) ?></small>
                                        </td>
                                        <td class="small">
                                            <?= $p['is_24x7'] ? '<span class="badge bg-danger-subtle text-danger me-1">24x7</span>' : '' ?>
                                            <?= $p['home_delivery'] ? '<span class="badge bg-success-subtle text-success">Delivery</span>' : '' ?>
                                        </td>
                                        <td><?= getStatusBadge($p['status']) ?></td>
                                        <td class="text-end pe-4">
                                            <?php if ($p['status'] === 'pending'): ?>
                                                <form method="POST" action="pharmacies.php" class="d-inline">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="approve">
                                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-success rounded-pill px-3">Approve</button>
                                                </form>
                                                <form method="POST" action="pharmacies.php" class="d-inline ms-1">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="reject">
                                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-warning rounded-pill px-3">Reject</button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" action="pharmacies.php" class="d-inline ms-1" onsubmit="return confirm('Are you sure you want to delete this pharmacy record?')">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
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
