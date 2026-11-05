<?php
/**
 * Swastya Setu - Patient My Prescriptions & Quotes Portal
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('patient');
$user = currentUser();
$db = getDB();

// Handle Order Confirmation (Cash on Delivery)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_order') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($token)) {
        setFlash('danger', 'Security session expired. Please try again.');
    } else {
        $rxId = (int)($_POST['prescription_id'] ?? 0);

        // Fetch prescription
        $rxStmt = $db->prepare("SELECT * FROM prescriptions WHERE id = ? AND patient_id = ? AND status = 'approved'");
        $rxStmt->execute([$rxId, $user['id']]);
        $rx = $rxStmt->fetch();

        if ($rx) {
            try {
                $db->beginTransaction();

                // Check if order already exists for this prescription
                $chkOrd = $db->prepare("SELECT id, order_number FROM orders WHERE prescription_id = ?");
                $chkOrd->execute([$rxId]);
                $existingOrder = $chkOrd->fetch();

                if ($existingOrder) {
                    $db->rollBack();
                    setFlash('info', "An order (#{$existingOrder['order_number']}) has already been confirmed for this prescription.");
                    header('Location: ' . BASE_URL . '/user/my_orders.php');
                    exit;
                }

                $orderNum = 'ORD-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
                $delivery = 25.00;
                $total = (float)$rx['total_amount'];
                $subtotal = max(0, $total - $delivery);

                $insOrd = $db->prepare("
                    INSERT INTO orders (prescription_id, patient_id, pharmacy_id, order_number, subtotal, delivery_charges, total_amount, delivery_address, payment_method, order_status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'COD', 'order_confirmed')
                ");
                $insOrd->execute([
                    $rxId,
                    $user['id'],
                    $rx['pharmacy_id'],
                    $orderNum,
                    $subtotal,
                    $delivery,
                    $total,
                    $rx['delivery_address']
                ]);
                $orderId = $db->lastInsertId();

                // Copy prescription items to order items if present
                $itemStmt = $db->prepare("SELECT * FROM prescription_items WHERE prescription_id = ?");
                $itemStmt->execute([$rxId]);
                $items = $itemStmt->fetchAll();
                $insItem = $db->prepare("INSERT INTO order_items (order_id, medicine_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");
                foreach ($items as $item) {
                    $insItem->execute([$orderId, $item['medicine_name'], $item['quantity'], $item['unit_price'], $item['total_price']]);
                }

                // Notify Pharmacy
                $pUserStmt = $db->prepare("SELECT user_id FROM pharmacies WHERE id = ?");
                $pUserStmt->execute([$rx['pharmacy_id']]);
                $pharmUserId = $pUserStmt->fetchColumn();
                if ($pharmUserId) {
                    createNotification(
                        $pharmUserId,
                        'Medicine Order Confirmed',
                        "Patient {$user['name']} confirmed Order #{$orderNum} with Cash on Delivery (COD).",
                        'pharmacy/orders.php'
                    );
                }

                $db->commit();
                setFlash('success', "Order #{$orderNum} placed successfully! The pharmacy is preparing your medicine package.");
                header('Location: ' . BASE_URL . '/user/my_orders.php');
                exit;

            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                setFlash('danger', 'Error confirming order: ' . $e->getMessage());
            }
        }
    }
}

// Fetch Patient Prescriptions with Pharmacy details
$stmt = $db->prepare("
    SELECT p.*, ph.name AS pharmacy_name, ph.phone AS pharmacy_phone, ph.city AS pharmacy_city,
           (SELECT COUNT(*) FROM orders o WHERE o.prescription_id = p.id) AS has_order,
           (SELECT o.order_number FROM orders o WHERE o.prescription_id = p.id LIMIT 1) AS linked_order_number
    FROM prescriptions p
    JOIN pharmacies ph ON p.pharmacy_id = ph.id
    WHERE p.patient_id = ?
    ORDER BY p.id DESC
");
$stmt->execute([$user['id']]);
$prescriptions = $stmt->fetchAll();

$pageTitle = 'My Prescriptions & Pharmacy Quotes';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="content-wrapper">
        <div class="container-fluid p-0">
            
            <?= displayFlash() ?>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h2 class="fw-bold mb-1"><i class="fa-solid fa-file-prescription text-primary me-2"></i> My Prescriptions & Quotes</h2>
                    <p class="text-muted small mb-0">Track verification status, review itemized pharmacy pricing, and confirm home delivery orders.</p>
                </div>
                <a href="<?= BASE_URL ?>/user/upload_prescription.php" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">
                    <i class="fa-solid fa-cloud-arrow-up me-1"></i> Upload Another Rx
                </a>
            </div>

            <?php if (empty($prescriptions)): ?>
                <div class="card border-0 shadow-sm rounded-4 text-center p-5">
                    <i class="fa-solid fa-receipt fs-1 text-muted opacity-50 mb-3"></i>
                    <h5 class="text-dark">No Prescriptions Uploaded Yet</h5>
                    <p class="text-muted small">Upload your doctor's prescription to receive quotes and doorstep medicine delivery.</p>
                    <a href="upload_prescription.php" class="btn btn-primary rounded-pill px-4 mx-auto">Upload Prescription Now</a>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($prescriptions as $p): 
                        // Fetch quoted items if approved
                        $items = [];
                        if ($p['status'] === 'approved') {
                            $itStmt = $db->prepare("SELECT * FROM prescription_items WHERE prescription_id = ?");
                            $itStmt->execute([$p['id']]);
                            $items = $itStmt->fetchAll();
                        }
                    ?>
                        <div class="col-lg-6">
                            <div class="card border-0 shadow-sm rounded-4 h-100 <?= $p['status'] === 'approved' ? 'border-start border-success border-4' : '' ?>">
                                <div class="card-body p-4 d-flex flex-column">
                                    
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <span class="badge bg-light text-dark border me-1">#RX-<?= $p['id'] ?></span>
                                            <?= getStatusBadge($p['status']) ?>
                                        </div>
                                        <small class="text-muted"><?= formatDate($p['created_at'], true) ?></small>
                                    </div>

                                    <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($p['pharmacy_name']) ?></h5>
                                    <p class="text-muted small mb-2"><i class="fa-solid fa-location-dot me-1 text-danger"></i> Delivery To: <?= htmlspecialchars($p['delivery_address']) ?></p>

                                    <?php if (!empty($p['pharmacy_notes'])): ?>
                                        <div class="alert alert-light border rounded-3 p-2 small mb-3">
                                            <strong><i class="fa-solid fa-comment-medical text-primary me-1"></i> Pharmacist Note:</strong>
                                            <?= htmlspecialchars($p['pharmacy_notes']) ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- If Approved: Display Itemized Medicine Quote -->
                                    <?php if ($p['status'] === 'approved' && !empty($items)): ?>
                                        <div class="table-responsive mb-3 border rounded-3 bg-light">
                                            <table class="table table-sm table-borderless mb-0 small">
                                                <thead class="text-muted border-bottom">
                                                    <tr>
                                                        <th>Medicine</th>
                                                        <th>Dosage</th>
                                                        <th>Qty</th>
                                                        <th class="text-end">Price</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($items as $item): ?>
                                                        <tr>
                                                            <td class="fw-semibold text-dark"><?= htmlspecialchars($item['medicine_name']) ?></td>
                                                            <td class="text-muted"><?= htmlspecialchars($item['dosage']) ?></td>
                                                            <td><?= $item['quantity'] ?></td>
                                                            <td class="text-end fw-bold"><?= formatPrice($item['total_price']) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                    <tr class="border-top text-muted">
                                                        <td colspan="3">Delivery Charges</td>
                                                        <td class="text-end">₹25.00</td>
                                                    </tr>
                                                    <tr class="fw-bold fs-6 text-primary">
                                                        <td colspan="3">Total Payable (COD)</td>
                                                        <td class="text-end"><?= formatPrice($p['total_amount']) ?></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>

                                    <div class="mt-auto pt-3 border-top d-flex flex-wrap gap-2 justify-content-between align-items-center">
                                        <a href="<?= BASE_URL ?>/view_prescription.php?id=<?= $p['id'] ?>" target="_blank" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                                            <i class="fa-solid fa-file-pdf me-1"></i> View Upload
                                        </a>

                                        <?php if ($p['status'] === 'approved'): ?>
                                            <?php if ($p['has_order']): ?>
                                                <a href="<?= BASE_URL ?>/user/my_orders.php" class="btn btn-outline-success btn-sm rounded-pill px-3 fw-semibold">
                                                    <i class="fa-solid fa-truck-fast me-1"></i> Track Order (<?= htmlspecialchars($p['linked_order_number']) ?>)
                                                </a>
                                            <?php else: ?>
                                                <form method="POST" action="my_prescriptions.php" class="d-inline">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="confirm_order">
                                                    <input type="hidden" name="prescription_id" value="<?= $p['id'] ?>">
                                                    <button type="submit" class="btn btn-success btn-sm rounded-pill px-3 fw-semibold shadow-sm" onclick="return confirmAction('Confirm order with Cash on Delivery (COD)?')">
                                                        <i class="fa-solid fa-check-circle me-1"></i> Confirm Order (COD)
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php elseif ($p['status'] === 'uploaded'): ?>
                                            <span class="badge bg-warning-subtle text-warning small px-3 py-2 rounded-pill">
                                                <i class="fa-solid fa-hourglass-half me-1"></i> Under Review by Pharmacist
                                            </span>
                                        <?php elseif ($p['status'] === 'rejected'): ?>
                                            <span class="badge bg-danger-subtle text-danger small px-3 py-2 rounded-pill">
                                                <i class="fa-solid fa-circle-xmark me-1"></i> Request Declined
                                            </span>
                                        <?php endif; ?>
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
