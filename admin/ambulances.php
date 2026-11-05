<?php
/**
 * Swastya Setu - Administrator Emergency SOS & Ambulance Log
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');
$db = getDB();

if (isset($_GET['action']) && isset($_GET['id'])) {
    $reqId = (int)$_GET['id'];
    $act = $_GET['action'];
    $newStatus = match($act) {
        'dispatch' => 'dispatched',
        'resolve'  => 'resolved',
        'cancel'   => 'cancelled',
        default    => 'pending'
    };

    $db->prepare("UPDATE ambulance_requests SET status = ? WHERE id = ?")->execute([$newStatus, $reqId]);
    setFlash('info', "Emergency Request #SOS-{$reqId} marked as {$newStatus}.");
    header('Location: ' . BASE_URL . '/admin/ambulances.php');
    exit;
}

$requests = $db->query("SELECT * FROM ambulance_requests ORDER BY id DESC")->fetchAll();

$pageTitle = 'Emergency Ambulance SOS Dispatch Log';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="content-wrapper">
        <div class="container-fluid p-0">
            
            <?= displayFlash() ?>

            <div class="mb-4">
                <h2 class="fw-bold mb-1"><i class="fa-solid fa-truck-medical text-danger me-2"></i> Emergency 108 SOS Dispatch Monitor</h2>
                <p class="text-muted small mb-0">Live audit of patient SOS alerts, GPS coordinates, and ambulance response logs.</p>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-0">
                    <?php if (empty($requests)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fa-solid fa-bell-slash fs-2 opacity-50 mb-2"></i>
                            <p>No emergency SOS broadcasts logged.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light small text-muted">
                                    <tr>
                                        <th class="ps-4">SOS ID & Time</th>
                                        <th>Patient Name</th>
                                        <th>Emergency Category</th>
                                        <th>GPS Location & Address</th>
                                        <th>Status</th>
                                        <th class="text-end pe-4">Dispatcher Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($requests as $r): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <strong class="text-danger">#SOS-<?= $r['id'] ?></strong>
                                                <div class="small text-muted"><?= formatDate($r['created_at'], true) ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark"><?= htmlspecialchars($r['patient_name']) ?></div>
                                                <small class="text-muted"><i class="fa-solid fa-phone me-1"></i> <?= htmlspecialchars($r['contact_phone']) ?></small>
                                            </td>
                                            <td class="small fw-semibold text-dark"><?= htmlspecialchars($r['emergency_type']) ?></td>
                                            <td class="small text-muted" style="max-width: 260px;">
                                                <div class="text-truncate"><?= htmlspecialchars($r['address']) ?></div>
                                                <a href="https://www.google.com/maps?q=<?= $r['latitude'] ?>,<?= $r['longitude'] ?>" target="_blank" class="small text-primary text-decoration-none">
                                                    <i class="fa-solid fa-location-crosshairs me-1"></i> Lat: <?= $r['latitude'] ?>, Lng: <?= $r['longitude'] ?>
                                                </a>
                                            </td>
                                            <td><?= getStatusBadge($r['status']) ?></td>
                                            <td class="text-end pe-4">
                                                <?php if ($r['status'] === 'pending'): ?>
                                                    <a href="ambulances.php?action=dispatch&id=<?= $r['id'] ?>" class="btn btn-sm btn-danger rounded-pill px-3 fw-semibold">
                                                        Dispatch ALS
                                                    </a>
                                                <?php elseif ($r['status'] === 'dispatched'): ?>
                                                    <a href="ambulances.php?action=resolve&id=<?= $r['id'] ?>" class="btn btn-sm btn-success rounded-pill px-3">
                                                        Mark Resolved
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted small">Resolved</span>
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
