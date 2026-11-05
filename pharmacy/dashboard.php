<?php
/**
 * Swastya Setu - Pharmacy Owner Dashboard
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('pharmacy');
$user = currentUser();
$db = getDB();

// Get Pharmacy Record
$pharmStmt = $db->prepare("SELECT * FROM pharmacies WHERE user_id = ? LIMIT 1");
$pharmStmt->execute([$user['id']]);
$pharmacy = $pharmStmt->fetch();

if (!$pharmacy) {
    $pageTitle = 'Pharmacy Setup Pending';
    require_once __DIR__ . '/../includes/header.php';
    echo "<div class='container py-5 text-center'>
            <div class='card shadow-sm p-5 max-w-600 mx-auto rounded-4'>
                <i class='fa-solid fa-prescription-bottle-medical text-warning fs-1 mb-3'></i>
                <h3 class='fw-bold'>Pharmacy Profile Pending</h3>
                <p class='text-muted'>No verified pharmacy partner profile is linked to your account. Please complete your registration details.</p>
                <a href='" . BASE_URL . "/pharmacy/profile.php' class='btn btn-primary rounded-pill px-4'>Complete Profile</a>
            </div>
          </div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$pharmacyId = (int)$pharmacy['id'];

// Quick Stats using Prepared Statements
$stM = $db->prepare("SELECT COUNT(*) FROM medicines WHERE pharmacy_id = ?");
$stM->execute([$pharmacyId]);
$totalMedicines = (int)$stM->fetchColumn();

$stRx = $db->prepare("SELECT COUNT(*) FROM prescriptions WHERE pharmacy_id = ? AND status IN ('uploaded', 'under_review')");
$stRx->execute([$pharmacyId]);
$pendingPrescriptions = (int)$stRx->fetchColumn();

$stOrd = $db->prepare("SELECT COUNT(*) FROM orders WHERE pharmacy_id = ?");
$stOrd->execute([$pharmacyId]);
$totalOrders = (int)$stOrd->fetchColumn();

$stRev = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE pharmacy_id = ? AND order_status != 'cancelled'");
$stRev->execute([$pharmacyId]);
$totalRevenue = (float)$stRev->fetchColumn();

$stLow = $db->prepare("SELECT COUNT(*) FROM medicines WHERE pharmacy_id = ? AND stock_quantity <= 15");
$stLow->execute([$pharmacyId]);
$lowStockCount = (int)$stLow->fetchColumn();

// Pending Prescriptions Queue using Prepared Statement
$rxStmt = $db->prepare("
    SELECT p.*, u.name AS patient_name, u.phone AS patient_phone
    FROM prescriptions p
    JOIN users u ON p.patient_id = u.id
    WHERE p.pharmacy_id = ? AND p.status IN ('uploaded', 'under_review')
    ORDER BY p.id DESC LIMIT 5
");
$rxStmt->execute([$pharmacyId]);
$pendingRxList = $rxStmt->fetchAll();

// Recent Medicine Orders using Prepared Statement
$ordStmt = $db->prepare("
    SELECT o.*, u.name AS patient_name, u.phone AS patient_phone
    FROM orders o
    JOIN users u ON o.patient_id = u.id
    WHERE o.pharmacy_id = ?
    ORDER BY o.id DESC LIMIT 5
");
$ordStmt->execute([$pharmacyId]);
$recentOrders = $ordStmt->fetchAll();

$pageTitle = 'Pharmacy Fulfillment Hub';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="content-wrapper">
        <div class="container-fluid p-0">
            
            <?= displayFlash() ?>

            <!-- Welcome Banner -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 text-white p-4" style="background: linear-gradient(135deg, #00897B, #004D40);">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h2 class="fw-bold mb-1"><?= htmlspecialchars($pharmacy['name']) ?> 💊</h2>
                        <p class="mb-0 opacity-75">
                            License: <?= htmlspecialchars($pharmacy['license_number']) ?> • <?= htmlspecialchars($pharmacy['city']) ?> • <?= $pharmacy['home_delivery'] ? 'Home Delivery Active' : 'Pickup Only' ?>
                        </p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="<?= BASE_URL ?>/pharmacy/prescriptions.php" class="btn btn-light rounded-pill px-3 fw-semibold text-teal shadow-sm" style="color: #004D40;">
                            <i class="fa-solid fa-file-prescription me-1"></i> Review Requests (<?= $pendingPrescriptions ?>)
                        </a>
                        <a href="<?= BASE_URL ?>/pharmacy/inventory.php" class="btn btn-outline-light rounded-pill px-3 fw-semibold">
                            <i class="fa-solid fa-plus me-1"></i> Add Medicine
                        </a>
                    </div>
                </div>
            </div>

            <!-- Stats Bar -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                        <span class="small text-muted fw-semibold d-block mb-1">Prescriptions to Review</span>
                        <div class="d-flex align-items-center justify-content-between">
                            <h3 class="fw-bold mb-0 text-warning"><?= $pendingPrescriptions ?></h3>
                            <i class="fa-solid fa-file-medical text-warning fs-3 opacity-25"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                        <span class="small text-muted fw-semibold d-block mb-1">Total Delivery Orders</span>
                        <div class="d-flex align-items-center justify-content-between">
                            <h3 class="fw-bold mb-0 text-primary"><?= $totalOrders ?></h3>
                            <i class="fa-solid fa-truck-fast text-primary fs-3 opacity-25"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                        <span class="small text-muted fw-semibold d-block mb-1">Total Revenue</span>
                        <div class="d-flex align-items-center justify-content-between">
                            <h3 class="fw-bold mb-0 text-success"><?= formatPrice($totalRevenue) ?></h3>
                            <i class="fa-solid fa-indian-rupee-sign text-success fs-3 opacity-25"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                        <span class="small text-muted fw-semibold d-block mb-1">Low Stock Alerts</span>
                        <div class="d-flex align-items-center justify-content-between">
                            <h3 class="fw-bold mb-0 text-danger"><?= $lowStockCount ?></h3>
                            <i class="fa-solid fa-triangle-exclamation text-danger fs-3 opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Prescriptions Section -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-file-signature text-primary me-2"></i> Incoming Prescription Verification Queue</h5>
                    <a href="<?= BASE_URL ?>/pharmacy/prescriptions.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">View All &rarr;</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($pendingRxList)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fa-solid fa-clipboard-check fs-2 opacity-50 mb-2"></i>
                            <p>No pending prescriptions awaiting verification.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light small text-muted">
                                    <tr>
                                        <th class="ps-4">Rx ID & Date</th>
                                        <th>Patient Name</th>
                                        <th>Delivery Address</th>
                                        <th>Prescription File</th>
                                        <th>Status</th>
                                        <th class="text-end pe-4">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingRxList as $rx): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <strong class="text-dark">#RX-<?= $rx['id'] ?></strong>
                                                <div class="small text-muted"><?= formatDate($rx['created_at'], true) ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark"><?= htmlspecialchars($rx['patient_name']) ?></div>
                                                <small class="text-muted"><i class="fa-solid fa-phone me-1"></i> <?= htmlspecialchars($rx['patient_phone']) ?></small>
                                            </td>
                                            <td class="small text-muted" style="max-width: 200px;">
                                                <span class="d-inline-block text-truncate" style="max-width: 180px;">
                                                    <?= htmlspecialchars($rx['delivery_address']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="<?= BASE_URL ?>/view_prescription.php?id=<?= $rx['id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                                    <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Inspect File
                                                </a>
                                            </td>
                                            <td><?= getStatusBadge($rx['status']) ?></td>
                                            <td class="text-end pe-4">
                                                <a href="<?= BASE_URL ?>/pharmacy/prescriptions.php?review_id=<?= $rx['id'] ?>" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold">
                                                    Review & Quote
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Orders Section -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-truck-fast text-primary me-2"></i> Recent Medicine Delivery Orders</h5>
                    <a href="<?= BASE_URL ?>/pharmacy/orders.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">Manage Orders &rarr;</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recentOrders)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fa-solid fa-box-open fs-2 opacity-50 mb-2"></i>
                            <p>No orders processed yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light small text-muted">
                                    <tr>
                                        <th class="ps-4">Order #</th>
                                        <th>Patient Name</th>
                                        <th>Total Bill</th>
                                        <th>Payment</th>
                                        <th>Fulfillment Status</th>
                                        <th class="text-end pe-4">Manage Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentOrders as $ord): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <strong class="text-dark"><?= htmlspecialchars($ord['order_number']) ?></strong>
                                                <div class="small text-muted"><?= formatDate($ord['created_at'], true) ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark"><?= htmlspecialchars($ord['patient_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($ord['patient_phone']) ?></small>
                                            </td>
                                            <td class="fw-bold text-dark"><?= formatPrice($ord['total_amount']) ?></td>
                                            <td><span class="badge bg-light text-dark border">COD</span></td>
                                            <td><?= getStatusBadge($ord['order_status']) ?></td>
                                            <td class="text-end pe-4">
                                                <a href="<?= BASE_URL ?>/pharmacy/orders.php?order_id=<?= $ord['id'] ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                                                    Update Status &rarr;
                                                </a>
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
