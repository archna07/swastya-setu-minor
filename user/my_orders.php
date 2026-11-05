<?php
/**
 * Swastya Setu - Patient Medicine Orders & Live Delivery Timeline
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('patient');
$user = currentUser();
$db = getDB();

// Fetch Patient Orders
$stmt = $db->prepare("
    SELECT o.*, ph.name AS pharmacy_name, ph.phone AS pharmacy_phone, ph.address AS pharmacy_address
    FROM orders o
    JOIN pharmacies ph ON o.pharmacy_id = ph.id
    WHERE o.patient_id = ?
    ORDER BY o.id DESC
");
$stmt->execute([$user['id']]);
$orders = $stmt->fetchAll();

// Timeline Steps Definition
$timelineSteps = [
    'uploaded'         => ['label' => 'Prescription Uploaded',    'icon' => 'fa-file-arrow-up',  'step' => 1],
    'under_review'     => ['label' => 'Under Review',            'icon' => 'fa-clipboard-check', 'step' => 2],
    'approved'         => ['label' => 'Approved & Quoted',       'icon' => 'fa-circle-check',   'step' => 3],
    'order_confirmed'  => ['label' => 'Order Confirmed (COD)',   'icon' => 'fa-receipt',        'step' => 4],
    'preparing'        => ['label' => 'Preparing Order',         'icon' => 'fa-boxes-packing',  'step' => 5],
    'out_for_delivery' => ['label' => 'Out for Delivery',        'icon' => 'fa-truck-fast',     'step' => 6],
    'delivered'        => ['label' => 'Delivered to Doorstep',   'icon' => 'fa-house-circle-check', 'step' => 7]
];

function getActiveStepIndex(string $status): int {
    return match($status) {
        'order_confirmed'  => 4,
        'preparing'        => 5,
        'out_for_delivery' => 6,
        'delivered'        => 7,
        'cancelled'        => 0,
        default            => 4
    };
}

$pageTitle = 'Medicine Orders & Delivery Tracking';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="content-wrapper">
        <div class="container-fluid p-0">
            
            <?= displayFlash() ?>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h2 class="fw-bold mb-1"><i class="fa-solid fa-truck-fast text-primary me-2"></i> Medicine Delivery Tracking</h2>
                    <p class="text-muted small mb-0">Follow real-time status progression from pharmacy preparation to doorstep delivery.</p>
                </div>
                <a href="<?= BASE_URL ?>/user/upload_prescription.php" class="btn btn-outline-primary rounded-pill px-4 fw-semibold shadow-sm">
                    <i class="fa-solid fa-file-arrow-up me-1"></i> Upload New Prescription
                </a>
            </div>

            <?php if (empty($orders)): ?>
                <div class="card border-0 shadow-sm rounded-4 text-center p-5">
                    <i class="fa-solid fa-box-open fs-1 text-muted opacity-50 mb-3"></i>
                    <h5 class="text-dark">No Active Delivery Orders</h5>
                    <p class="text-muted small">Upload your prescription to receive quotes and track your doorstep delivery.</p>
                    <a href="upload_prescription.php" class="btn btn-primary rounded-pill px-4 mx-auto">Upload Prescription Now</a>
                </div>
            <?php else: ?>
                <div class="d-flex flex-column gap-4">
                    <?php foreach ($orders as $ord): 
                        $currentStep = getActiveStepIndex($ord['order_status']);
                        
                        // Fetch ordered items
                        $itemStmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
                        $itemStmt->execute([$ord['id']]);
                        $orderItems = $itemStmt->fetchAll();
                    ?>
                        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                            <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <div>
                                    <strong class="fs-5 text-dark me-2">#<?= htmlspecialchars($ord['order_number']) ?></strong>
                                    <?= getStatusBadge($ord['order_status']) ?>
                                </div>
                                <div class="small text-muted">
                                    Placed on <?= formatDate($ord['created_at'], true) ?> • Payment: <strong class="text-dark">Cash on Delivery (COD)</strong>
                                </div>
                            </div>

                            <div class="card-body p-4">
                                <div class="row g-4">
                                    
                                    <!-- Visual Status Timeline -->
                                    <div class="col-lg-7">
                                        <h6 class="fw-bold text-dark mb-4"><i class="fa-solid fa-timeline text-primary me-2"></i> Fulfillment Progress Timeline</h6>

                                        <div class="timeline ps-3 position-relative" style="border-left: 3px solid #E2E8F0; margin-left: 12px;">
                                            
                                            <!-- Step 1-3 Completed by Default once order is placed -->
                                            <div class="mb-4 position-relative">
                                                <div class="position-absolute rounded-circle bg-success text-white d-flex align-items-center justify-content-center" style="left: -23px; top: 0; width: 22px; height: 22px; font-size: 0.65rem;">
                                                    <i class="fa-solid fa-check"></i>
                                                </div>
                                                <strong class="text-dark small d-block">1. Prescription Uploaded & Verified</strong>
                                                <small class="text-muted">Doctor prescription inspected by pharmacist.</small>
                                            </div>

                                            <div class="mb-4 position-relative">
                                                <div class="position-absolute rounded-circle <?= $currentStep >= 4 ? 'bg-success text-white' : 'bg-light border text-muted' ?> d-flex align-items-center justify-content-center" style="left: -23px; top: 0; width: 22px; height: 22px; font-size: 0.65rem;">
                                                    <i class="fa-solid fa-check"></i>
                                                </div>
                                                <strong class="text-dark small d-block">2. Order Confirmed (COD)</strong>
                                                <small class="text-muted">Quote approved and dispatched to pharmacy counter.</small>
                                            </div>

                                            <div class="mb-4 position-relative">
                                                <div class="position-absolute rounded-circle <?= $currentStep >= 5 ? 'bg-success text-white' : ($currentStep == 4 ? 'bg-primary text-white pulse-glow' : 'bg-light border text-muted') ?> d-flex align-items-center justify-content-center" style="left: -23px; top: 0; width: 22px; height: 22px; font-size: 0.65rem;">
                                                    <i class="fa-solid <?= $currentStep >= 5 ? 'fa-check' : 'fa-box' ?>"></i>
                                                </div>
                                                <strong class="text-dark small d-block">3. Preparing Medicines at Pharmacy</strong>
                                                <small class="text-muted"><?= htmlspecialchars($ord['pharmacy_name']) ?> is packing your medicines.</small>
                                            </div>

                                            <div class="mb-4 position-relative">
                                                <div class="position-absolute rounded-circle <?= $currentStep >= 6 ? 'bg-success text-white' : ($currentStep == 5 ? 'bg-primary text-white pulse-glow' : 'bg-light border text-muted') ?> d-flex align-items-center justify-content-center" style="left: -23px; top: 0; width: 22px; height: 22px; font-size: 0.65rem;">
                                                    <i class="fa-solid <?= $currentStep >= 6 ? 'fa-check' : 'fa-motorcycle' ?>"></i>
                                                </div>
                                                <strong class="text-dark small d-block">4. Out for Delivery</strong>
                                                <small class="text-muted">Rider assigned and enroute to your location.</small>
                                            </div>

                                            <div class="position-relative">
                                                <div class="position-absolute rounded-circle <?= $currentStep >= 7 ? 'bg-success text-white' : 'bg-light border text-muted' ?> d-flex align-items-center justify-content-center" style="left: -23px; top: 0; width: 22px; height: 22px; font-size: 0.65rem;">
                                                    <i class="fa-solid fa-house-chimney-check"></i>
                                                </div>
                                                <strong class="text-dark small d-block">5. Delivered to Doorstep</strong>
                                                <small class="text-muted"><?= $currentStep >= 7 ? 'Successfully delivered.' : 'Estimated arrival: 30-45 mins' ?></small>
                                            </div>

                                        </div>
                                    </div>

                                    <!-- Order Items & Billing Summary -->
                                    <div class="col-lg-5">
                                        <div class="p-3 rounded-4 bg-light border h-100">
                                            <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-receipt text-primary me-2"></i> Bill & Delivery Summary</h6>
                                            
                                            <div class="small mb-3">
                                                <div><strong>Fulfilling Pharmacy:</strong> <?= htmlspecialchars($ord['pharmacy_name']) ?></div>
                                                <div class="text-muted">Phone: <?= htmlspecialchars($ord['pharmacy_phone']) ?></div>
                                                <div class="text-muted mt-1"><i class="fa-solid fa-location-dot text-danger me-1"></i> <?= htmlspecialchars($ord['delivery_address']) ?></div>
                                            </div>

                                            <div class="table-responsive border rounded-3 bg-white mb-3">
                                                <table class="table table-sm table-borderless mb-0 small">
                                                    <thead class="table-light border-bottom text-muted">
                                                        <tr>
                                                            <th>Item</th>
                                                            <th>Qty</th>
                                                            <th class="text-end">Total</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($orderItems as $it): ?>
                                                            <tr>
                                                                <td class="fw-semibold text-dark"><?= htmlspecialchars($it['medicine_name']) ?></td>
                                                                <td><?= $it['quantity'] ?></td>
                                                                <td class="text-end fw-bold"><?= formatPrice($it['total_price']) ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                        <tr class="border-top text-muted">
                                                            <td colspan="2">Medicine Subtotal</td>
                                                            <td class="text-end"><?= formatPrice($ord['subtotal']) ?></td>
                                                        </tr>
                                                        <tr class="text-muted">
                                                            <td colspan="2">Express Home Delivery</td>
                                                            <td class="text-end">₹<?= number_format($ord['delivery_charges'], 2) ?></td>
                                                        </tr>
                                                        <tr class="border-top fw-bold fs-6 text-primary">
                                                            <td colspan="2">Total Payable (COD)</td>
                                                            <td class="text-end"><?= formatPrice($ord['total_amount']) ?></td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>

                                            <div class="d-flex align-items-center justify-content-between p-2 rounded-3 bg-white border">
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class="fa-solid fa-truck text-primary fs-4"></i>
                                                    <div class="small">
                                                        <strong class="d-block text-dark">Express Courier</strong>
                                                        <span class="text-muted">Pay ₹<?= number_format($ord['total_amount'], 2) ?> on delivery</span>
                                                    </div>
                                                </div>
                                                <a href="tel:<?= htmlspecialchars($ord['pharmacy_phone']) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                                    <i class="fa-solid fa-phone me-1"></i> Contact
                                                </a>
                                            </div>

                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
