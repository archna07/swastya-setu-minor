<?php
/**
 * Swastya Setu - Administrator Medicine Orders Oversight
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');
$db = getDB();

$orders = $db->query("
    SELECT o.*, u.name AS patient_name, u.phone AS patient_phone, ph.name AS pharmacy_name
    FROM orders o
    JOIN users u ON o.patient_id = u.id
    JOIN pharmacies ph ON o.pharmacy_id = ph.id
    ORDER BY o.id DESC
")->fetchAll();

$pageTitle = 'Global Medicine Orders';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="content-wrapper">
        <div class="container-fluid p-0">
            
            <?= displayFlash() ?>

            <div class="mb-4">
                <h2 class="fw-bold mb-1"><i class="fa-solid fa-cart-shopping text-primary me-2"></i> System-Wide Medicine Orders</h2>
                <p class="text-muted small mb-0">Overview of all prescription-based and OTC deliveries across all network pharmacies.</p>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-muted">
                                <tr>
                                    <th class="ps-4">Order Number</th>
                                    <th>Date</th>
                                    <th>Patient</th>
                                    <th>Fulfilling Pharmacy</th>
                                    <th>Delivery Address</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $o): ?>
                                    <tr>
                                        <td class="ps-4"><strong class="text-dark">#<?= htmlspecialchars($o['order_number']) ?></strong></td>
                                        <td class="small text-muted"><?= formatDate($o['created_at'], true) ?></td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= htmlspecialchars($o['patient_name']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($o['patient_phone']) ?></small>
                                        </td>
                                        <td class="small fw-semibold text-dark"><?= htmlspecialchars($o['pharmacy_name']) ?></td>
                                        <td class="small text-muted" style="max-width: 220px;">
                                            <span class="d-inline-block text-truncate" style="max-width: 200px;">
                                                <?= htmlspecialchars($o['delivery_address']) ?>
                                            </span>
                                        </td>
                                        <td class="fw-bold text-dark small"><?= formatPrice($o['total_amount']) ?></td>
                                        <td><?= getStatusBadge($o['order_status']) ?></td>
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
