<?php
/**
 * Swastya Setu - Pharmacy Medicine Inventory CRUD
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

$error = '';

// Add / Edit Medicine
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_med') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($token)) {
        $error = 'Security session expired. Please refresh.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? 'General');
        $desc = trim($_POST['description'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $stock = (int)($_POST['stock_quantity'] ?? 0);
        $rxReq = isset($_POST['prescription_required']) ? 1 : 0;
        $expiry = trim($_POST['expiry_date'] ?? date('Y-m-d', strtotime('+1 year')));

        if (empty($name) || $price <= 0) {
            $error = 'Valid medicine name and price are required.';
        } else {
            try {
                if (!empty($_POST['med_id'])) {
                    $medId = (int)$_POST['med_id'];
                    $upd = $db->prepare("UPDATE medicines SET name = ?, category = ?, description = ?, price = ?, stock_quantity = ?, prescription_required = ?, expiry_date = ? WHERE id = ? AND pharmacy_id = ?");
                    $upd->execute([$name, $category, $desc, $price, $stock, $rxReq, $expiry, $medId, $pharmacyId]);
                    setFlash('success', "Medicine '{$name}' updated successfully.");
                } else {
                    $ins = $db->prepare("INSERT INTO medicines (pharmacy_id, name, category, description, price, stock_quantity, prescription_required, expiry_date, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
                    $ins->execute([$pharmacyId, $name, $category, $desc, $price, $stock, $rxReq, $expiry]);
                    setFlash('success', "Medicine '{$name}' added to inventory.");
                }
                header('Location: ' . BASE_URL . '/pharmacy/inventory.php');
                exit;
            } catch (Exception $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Delete Medicine using prepared statement
if (isset($_GET['delete_id'])) {
    $delId = (int)$_GET['delete_id'];
    $delStmt = $db->prepare("DELETE FROM medicines WHERE id = ? AND pharmacy_id = ?");
    $delStmt->execute([$delId, $pharmacyId]);
    setFlash('info', 'Medicine item deleted from inventory.');
    header('Location: ' . BASE_URL . '/pharmacy/inventory.php');
    exit;
}

// Fetch Inventory using prepared statement
$invStmt = $db->prepare("SELECT * FROM medicines WHERE pharmacy_id = ? ORDER BY name ASC");
$invStmt->execute([$pharmacyId]);
$medicines = $invStmt->fetchAll();

$pageTitle = 'Pharmacy Medicine Inventory';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="content-wrapper">
        <div class="container-fluid p-0">
            
            <?= displayFlash() ?>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h2 class="fw-bold mb-1"><i class="fa-solid fa-pills text-primary me-2"></i> Medicine Inventory & Stock Control</h2>
                    <p class="text-muted small mb-0">Manage pharmaceuticals, pricing, expiry dates, and prescription requirements.</p>
                </div>
                <button type="button" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#medModal" onclick="resetMedModal()">
                    <i class="fa-solid fa-plus me-1"></i> Add Medicine
                </button>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger rounded-4 shadow-sm mb-4"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Add / Edit Modal -->
            <div class="modal fade" id="medModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content border-0 shadow-lg rounded-4">
                        <div class="modal-header border-bottom py-3">
                            <h5 class="modal-title fw-bold" id="medModalTitle"><i class="fa-solid fa-pills text-primary me-2"></i> Add Medicine to Stock</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST" action="inventory.php">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="save_med">
                            <input type="hidden" name="med_id" id="medIdField" value="">

                            <div class="modal-body p-4">
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <label class="form-label small fw-semibold text-muted">Medicine Name & Strength *</label>
                                        <input type="text" name="name" id="medName" class="form-control rounded-3" placeholder="e.g. Amoxicillin 500mg" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold text-muted">Category</label>
                                        <input type="text" name="category" id="medCategory" class="form-control rounded-3" placeholder="e.g. Antibiotic, Painkiller" value="General">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold text-muted">Selling Price (₹) *</label>
                                        <input type="number" step="0.01" name="price" id="medPrice" class="form-control rounded-3" placeholder="95.00" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold text-muted">Available Stock Units *</label>
                                        <input type="number" name="stock_quantity" id="medStock" class="form-control rounded-3" placeholder="100" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold text-muted">Expiry Date *</label>
                                        <input type="date" name="expiry_date" id="medExpiry" class="form-control rounded-3" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+1 year')) ?>" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold text-muted">Description / Dosage Notes</label>
                                        <textarea name="description" id="medDesc" class="form-control rounded-3" rows="2" placeholder="Indications, precautions, or storage conditions..."></textarea>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check form-switch pt-2">
                                            <input class="form-check-input" type="checkbox" name="prescription_required" id="medRxReq" value="1">
                                            <label class="form-check-label small fw-bold text-danger" for="medRxReq">
                                                <i class="fa-solid fa-file-prescription me-1"></i> Requires Doctor Prescription (Schedule H/X Drug)
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="modal-footer border-top p-3">
                                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">Save to Inventory</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Inventory Table -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-muted">
                                <tr>
                                    <th class="ps-4">Medicine Name</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Stock Level</th>
                                    <th>Prescription</th>
                                    <th>Expiry Date</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($medicines as $m): 
                                    $isExpired = strtotime($m['expiry_date']) < time();
                                    $isLowStock = $m['stock_quantity'] <= 15;
                                ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($m['name']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($m['description'] ?: 'No notes') ?></small>
                                        </td>
                                        <td><span class="badge bg-light text-secondary border"><?= htmlspecialchars($m['category']) ?></span></td>
                                        <td class="fw-bold text-dark"><?= formatPrice($m['price']) ?></td>
                                        <td>
                                            <span class="badge <?= $isLowStock ? 'bg-danger text-white' : 'bg-success-subtle text-success' ?> rounded-pill px-2 py-1">
                                                <?= $m['stock_quantity'] ?> in stock
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($m['prescription_required']): ?>
                                                <span class="badge bg-danger-subtle text-danger small"><i class="fa-solid fa-file-prescription me-1"></i> Rx Required</span>
                                            <?php else: ?>
                                                <span class="badge bg-info-subtle text-info small">OTC Drug</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($isExpired): ?>
                                                <span class="badge bg-danger text-white">Expired (<?= formatDate($m['expiry_date']) ?>)</span>
                                            <?php else: ?>
                                                <span class="small text-muted"><?= formatDate($m['expiry_date']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <button class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick='editMed(<?= json_encode($m) ?>)'>
                                                Edit
                                            </button>
                                            <a href="inventory.php?delete_id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-danger rounded-pill px-2 ms-1" onclick="return confirmAction('Delete this medicine item?')">
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

<script>
    function resetMedModal() {
        document.getElementById('medModalTitle').innerText = 'Add Medicine to Stock';
        document.getElementById('medIdField').value = '';
        document.getElementById('medName').value = '';
        document.getElementById('medCategory').value = 'General';
        document.getElementById('medPrice').value = '';
        document.getElementById('medStock').value = '100';
        document.getElementById('medDesc').value = '';
        document.getElementById('medRxReq').checked = false;
    }

    function editMed(med) {
        document.getElementById('medModalTitle').innerText = 'Edit Medicine Stock';
        document.getElementById('medIdField').value = med.id;
        document.getElementById('medName').value = med.name;
        document.getElementById('medCategory').value = med.category;
        document.getElementById('medPrice').value = med.price;
        document.getElementById('medStock').value = med.stock_quantity;
        document.getElementById('medExpiry').value = med.expiry_date;
        document.getElementById('medDesc').value = med.description || '';
        document.getElementById('medRxReq').checked = (med.prescription_required == 1);
        const modal = new bootstrap.Modal(document.getElementById('medModal'));
        modal.show();
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
