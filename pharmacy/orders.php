<?php
/**
 * Swastya Setu - Pharmacy Medicine Orders & Delivery Fulfillment
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('pharmacy');
$user = currentUser();
$db = getDB();

$pharmStmt = $db->prepare("SELECT * FROM pharmacies WHERE user_id = ? LIMIT 1");
$pharmStmt->execute([$user['id']]);
$pharmacy = $pharmStmt->fetch();

if (!$pharmacy) {
    setFlash('warning', 'No verified pharmacy facility is linked to your account.');
    header('Location: ' . BASE_URL . '/pharmacy/profile.php');
    exit;
}
$pharmacyId = (int)$pharmacy['id'];

// Update Order Status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($token)) {
        setFlash('danger', 'Security session expired. Please refresh.');
    } else {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $newStatus = trim($_POST['order_status'] ?? '');

        $validStatuses = ['order_confirmed', 'preparing', 'out_for_delivery', 'delivered', 'cancelled'];
        if (in_array($newStatus, $validStatuses)) {
            $upd = $db->prepare("UPDATE orders SET order_status = ? WHERE id = ? AND pharmacy_id = ?");
            $upd->execute([$newStatus, $orderId, $pharmacyId]);

            // Notify Patient
            $ptStmt = $db->prepare("SELECT patient_id, order_number FROM orders WHERE id = ?");
            $ptStmt->execute([$orderId]);
            $ordInfo = $ptStmt->fetch();
            if ($ordInfo) {
                $statusLabels = [
                    'order_confirmed'  => 'Order Confirmed',
                    'preparing'        => 'Preparing Order at Pharmacy',
                    'out_for_delivery' => 'Out for Home Delivery',
                    'delivered'        => 'Delivered to Doorstep',
                    'cancelled'        => 'Order Cancelled'
                ];
                $label = $statusLabels[$newStatus] ?? $newStatus;

                createNotification(
                    $ordInfo['patient_id'],
                    "Order Update: {$label}",
                    "Your medicine order #{$ordInfo['order_number']} is now: {$label}.",
                    'user/my_orders.php'
                );
            }

            setFlash('success', "Order #{$ordInfo['order_number']} status updated to " . ucwords(str_replace('_', ' ', $newStatus)));
            header('Location: ' . BASE_URL . '/pharmacy/orders.php');
            exit;
        }
    }
}

// Fetch Orders using prepared statement
$ordListStmt = $db->prepare("
    SELECT o.*, u.name AS patient_name, u.phone AS patient_phone
    FROM orders o
    JOIN users u ON o.patient_id = u.id
    WHERE o.pharmacy_id = ?
    ORDER BY o.id DESC
");
$ordListStmt->execute([$pharmacyId]);
$orders = $ordListStmt->fetchAll();

$pageTitle = 'Pharmacy Delivery Fulfillment Center';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="content-wrapper">
        <div class="container-fluid p-0">
            
            <?= displayFlash() ?>

            <div class="mb-4">
                <h2 class="fw-bold mb-1"><i class="fa-solid fa-truck-ramp-box text-primary me-2"></i> Medicine Delivery Fulfillment</h2>
                <p class="text-muted small mb-0">Update delivery progress, dispatch couriers, and track completed medicine orders.</p>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-0">
                    <?php if (empty($orders)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fa-solid fa-boxes-packing fs-2 opacity-50 mb-2"></i>
                            <p>No confirmed medicine orders yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light small text-muted">
                                    <tr>
                                        <th class="ps-4">Order ID & Date</th>
                                        <th>Patient Details</th>
                                        <th>Delivery Address</th>
                                        <th>Amount</th>
                                        <th>Payment</th>
                                        <th>Current Status</th>
                                        <th class="text-end pe-4">Update Progress</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $o): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <strong class="text-dark">#<?= htmlspecialchars($o['order_number']) ?></strong>
                                                <div class="small text-muted"><?= formatDate($o['created_at'], true) ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark"><?= htmlspecialchars($o['patient_name']) ?></div>
                                                <small class="text-muted"><i class="fa-solid fa-phone me-1"></i> <?= htmlspecialchars($o['patient_phone']) ?></small>
                                            </td>
                                            <td class="small text-muted" style="max-width: 200px;">
                                                <span class="d-inline-block text-truncate" style="max-width: 180px;">
                                                    <?= htmlspecialchars($o['delivery_address']) ?>
                                                </span>
                                            </td>
                                            <td class="fw-bold text-dark"><?= formatPrice($o['total_amount']) ?></td>
                                            <td><span class="badge bg-light text-dark border">COD</span></td>
                                            <td><?= getStatusBadge($o['order_status']) ?></td>
                                            <td class="text-end pe-4">
                                                <form method="POST" action="orders.php" class="d-inline-flex gap-2 align-items-center">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                                    
                                                    <select name="order_status" class="form-select form-select-sm rounded-pill" style="width: auto;">
                                                        <option value="order_confirmed" <?= $o['order_status'] === 'order_confirmed' ? 'selected' : '' ?>>Order Confirmed</option>
                                                        <option value="preparing" <?= $o['order_status'] === 'preparing' ? 'selected' : '' ?>>Preparing Order</option>
                                                        <option value="out_for_delivery" <?= $o['order_status'] === 'out_for_delivery' ? 'selected' : '' ?>>Out for Delivery</option>
                                                        <option value="delivered" <?= $o['order_status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                                        <option value="cancelled" <?= $o['order_status'] === 'cancelled' ? 'selected' : '' ?>>Cancel</option>
                                                    </select>
                                                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold">Save</button>
                                                </form>
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
