<?php
/**
 * Swastya Setu - Pharmacy Prescription Verification & Quoting
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
$reviewRxId = (int)($_GET['review_id'] ?? 0);
$reviewRx = null;

if ($reviewRxId > 0) {
    $stmt = $db->prepare("SELECT p.*, u.name AS patient_name, u.phone AS patient_phone, u.email AS patient_email FROM prescriptions p JOIN users u ON p.patient_id = u.id WHERE p.id = ? AND p.pharmacy_id = ?");
    $stmt->execute([$reviewRxId, $pharmacyId]);
    $reviewRx = $stmt->fetch();
}

// Handle Prescription Approval & Pricing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($token)) {
        $error = 'Security session expired. Please refresh.';
    } else {
        $rxId = (int)($_POST['prescription_id'] ?? 0);
        $act = $_POST['action'];

        if ($act === 'approve') {
            $itemNames = $_POST['item_name'] ?? [];
            $itemDosages = $_POST['item_dosage'] ?? [];
            $itemQtys = $_POST['item_qty'] ?? [];
            $itemPrices = $_POST['item_price'] ?? [];
            $pharmacyNotes = trim($_POST['pharmacy_notes'] ?? '');

            if (empty($itemNames)) {
                $error = 'Please add at least one medicine item to the quote.';
            } else {
                try {
                    $db->beginTransaction();
                    $subtotal = 0;

                    // Delete any previous items if re-quoting
                    $db->prepare("DELETE FROM prescription_items WHERE prescription_id = ?")->execute([$rxId]);

                    $insItem = $db->prepare("INSERT INTO prescription_items (prescription_id, medicine_name, dosage, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)");

                    for ($i = 0; $i < count($itemNames); $i++) {
                        $name = trim($itemNames[$i]);
                        $dosage = trim($itemDosages[$i] ?? 'As directed');
                        $qty = max(1, (int)($itemQtys[$i] ?? 1));
                        $uPrice = (float)($itemPrices[$i] ?? 0);
                        $totPrice = $qty * $uPrice;
                        $subtotal += $totPrice;

                        if (!empty($name)) {
                            $insItem->execute([$rxId, $name, $dosage, $qty, $uPrice, $totPrice]);
                        }
                    }

                    $delivery = 25.00;
                    $grandTotal = $subtotal + $delivery;

                    $upd = $db->prepare("UPDATE prescriptions SET status = 'approved', pharmacy_notes = ?, total_amount = ? WHERE id = ? AND pharmacy_id = ?");
                    $upd->execute([$pharmacyNotes, $grandTotal, $rxId, $pharmacyId]);

                    // Notify Patient
                    $pStmt = $db->prepare("SELECT patient_id FROM prescriptions WHERE id = ?");
                    $pStmt->execute([$rxId]);
                    $patientId = $pStmt->fetchColumn();

                    createNotification(
                        $patientId,
                        'Prescription Approved & Quoted',
                        "Pharmacy '{$pharmacy['name']}' has verified Rx #RX-{$rxId} and generated an itemized quote of " . formatPrice($grandTotal) . ". Confirm order now.",
                        'user/my_prescriptions.php'
                    );

                    $db->commit();
                    setFlash('success', "Prescription #RX-{$rxId} verified and approved. Quote sent to patient!");
                    header('Location: ' . BASE_URL . '/pharmacy/prescriptions.php');
                    exit;

                } catch (Exception $e) {
                    if ($db->inTransaction()) $db->rollBack();
                    $error = 'Error approving prescription: ' . $e->getMessage();
                }
            }
        } elseif ($act === 'reject') {
            $rejectNotes = trim($_POST['pharmacy_notes'] ?? 'Prescription unclear or unfulfillable.');
            $upd = $db->prepare("UPDATE prescriptions SET status = 'rejected', pharmacy_notes = ? WHERE id = ? AND pharmacy_id = ?");
            $upd->execute([$rejectNotes, $rxId, $pharmacyId]);

            $pStmt = $db->prepare("SELECT patient_id FROM prescriptions WHERE id = ?");
            $pStmt->execute([$rxId]);
            $patientId = $pStmt->fetchColumn();

            createNotification(
                $patientId,
                'Prescription Request Rejected',
                "Rx #RX-{$rxId} could not be fulfilled: {$rejectNotes}",
                'user/my_prescriptions.php'
            );

            setFlash('danger', "Prescription #RX-{$rxId} marked as rejected.");
            header('Location: ' . BASE_URL . '/pharmacy/prescriptions.php');
            exit;
        }
    }
}

// Fetch Pharmacy's In-Stock Medicines for Fast Selection
$invStmt = $db->prepare("SELECT name, price FROM medicines WHERE pharmacy_id = ? AND stock_quantity > 0 ORDER BY name ASC");
$invStmt->execute([$pharmacyId]);
$inventory = $invStmt->fetchAll();

// Fetch Prescriptions List
$rxListStmt = $db->prepare("
    SELECT p.*, u.name AS patient_name, u.phone AS patient_phone
    FROM prescriptions p
    JOIN users u ON p.patient_id = u.id
    WHERE p.pharmacy_id = ?
    ORDER BY p.id DESC
");
$rxListStmt->execute([$pharmacyId]);
$prescriptions = $rxListStmt->fetchAll();

$pageTitle = 'Prescription Verification Queue';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="content-wrapper">
        <div class="container-fluid p-0">
            
            <?= displayFlash() ?>

            <div class="mb-4">
                <h2 class="fw-bold mb-1"><i class="fa-solid fa-file-signature text-primary me-2"></i> Prescription Verification & Quotes</h2>
                <p class="text-muted small mb-0">Inspect uploaded prescription documents, enter available medicines with pricing, and approve or reject.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger rounded-4 shadow-sm mb-4"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Verification & Quoting Modal / Panel if review_id is active -->
            <?php if ($reviewRx): ?>
                <div class="card border-0 shadow-lg rounded-4 mb-4 border-start border-primary border-5">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-magnifying-glass-plus text-primary me-2"></i> Reviewing Prescription #RX-<?= $reviewRx['id'] ?></h5>
                        <a href="prescriptions.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3">&times; Close Review</a>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        
                        <div class="row g-4 mb-4">
                            <div class="col-md-6">
                                <h6 class="fw-bold text-dark mb-2">Patient Details</h6>
                                <p class="mb-1"><strong>Name:</strong> <?= htmlspecialchars($reviewRx['patient_name']) ?></p>
                                <p class="mb-1"><strong>Phone:</strong> <?= htmlspecialchars($reviewRx['patient_phone']) ?></p>
                                <p class="mb-1"><strong>Delivery Address:</strong> <?= htmlspecialchars($reviewRx['delivery_address']) ?></p>
                                <?php if (!empty($reviewRx['patient_notes'])): ?>
                                    <p class="mb-0 text-muted small"><strong>Patient Note:</strong> <?= htmlspecialchars($reviewRx['patient_notes']) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <h6 class="fw-bold text-dark mb-2">Uploaded Document</h6>
                                <p class="mb-2 text-muted small">Uploaded on <?= formatDate($reviewRx['created_at'], true) ?></p>
                                <a href="<?= BASE_URL ?>/view_prescription.php?id=<?= $reviewRx['id'] ?>" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-4 shadow-sm">
                                    <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Open Prescription File
                                </a>
                            </div>
                        </div>

                        <!-- Pharmacy Pricing Form -->
                        <form method="POST" action="prescriptions.php">
                            <?= csrfField() ?>
                            <input type="hidden" name="prescription_id" value="<?= $reviewRx['id'] ?>">

                            <h6 class="fw-bold text-dark mb-3">Itemized Medicine Quote (Enter Available Medicines)</h6>
                            
                            <div id="medicineRowsContainer">
                                <div class="row g-2 mb-2 med-item-row align-items-center">
                                    <div class="col-md-4">
                                        <input type="text" name="item_name[]" class="form-control rounded-3" placeholder="Medicine Name (e.g. Amoxicillin 500mg)" required>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="text" name="item_dosage[]" class="form-control rounded-3" placeholder="Dosage (e.g. 1 tab twice daily)">
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" name="item_qty[]" class="form-control rounded-3" value="1" min="1" placeholder="Qty">
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" step="0.01" name="item_price[]" class="form-control rounded-3" placeholder="Unit Price ₹" required>
                                    </div>
                                    <div class="col-md-1 text-center">
                                        <button type="button" class="btn btn-sm btn-outline-danger rounded-circle" onclick="removeRow(this)"><i class="fa-solid fa-trash"></i></button>
                                    </div>
                                </div>
                            </div>

                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 mb-4" onclick="addRow()">
                                <i class="fa-solid fa-plus me-1"></i> Add Another Medicine
                            </button>

                            <div class="mb-4">
                                <label class="form-label small fw-semibold text-muted">Pharmacist Notes / Instructions to Patient</label>
                                <textarea name="pharmacy_notes" class="form-control rounded-3" rows="2" placeholder="e.g. All prescribed medicines are available. Delivery within 35 minutes."></textarea>
                            </div>

                            <div class="d-flex gap-3">
                                <button type="submit" name="action" value="approve" class="btn btn-success rounded-pill px-4 fw-semibold shadow-sm">
                                    <i class="fa-solid fa-circle-check me-1"></i> Approve & Send Quote
                                </button>
                                <button type="submit" name="action" value="reject" class="btn btn-danger rounded-pill px-4 fw-semibold shadow-sm" onclick="return confirmAction('Reject this prescription request?')">
                                    <i class="fa-solid fa-ban me-1"></i> Reject Request
                                </button>
                            </div>
                        </form>

                    </div>
                </div>
            <?php endif; ?>

            <!-- Prescriptions Queue Table -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-0">
                    <?php if (empty($prescriptions)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fa-solid fa-inbox fs-2 opacity-50 mb-2"></i>
                            <p>No prescription requests received yet.</p>
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
                                        <th>Quoted Amount</th>
                                        <th>Status</th>
                                        <th class="text-end pe-4">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($prescriptions as $p): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <strong class="text-dark">#RX-<?= $p['id'] ?></strong>
                                                <div class="small text-muted"><?= formatDate($p['created_at'], true) ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark"><?= htmlspecialchars($p['patient_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($p['patient_phone']) ?></small>
                                            </td>
                                            <td class="small text-muted" style="max-width: 220px;">
                                                <span class="d-inline-block text-truncate" style="max-width: 200px;">
                                                    <?= htmlspecialchars($p['delivery_address']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="<?= BASE_URL ?>/view_prescription.php?id=<?= $p['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                                                    <i class="fa-solid fa-file me-1"></i> View File
                                                </a>
                                            </td>
                                            <td class="fw-bold text-dark small">
                                                <?= $p['total_amount'] > 0 ? formatPrice($p['total_amount']) : 'Pending Quote' ?>
                                            </td>
                                            <td><?= getStatusBadge($p['status']) ?></td>
                                            <td class="text-end pe-4">
                                                <?php if (in_array($p['status'], ['uploaded', 'under_review'])): ?>
                                                    <a href="prescriptions.php?review_id=<?= $p['id'] ?>" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold shadow-sm">
                                                        Review & Quote
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted small">Reviewed</span>
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

<script>
    function addRow() {
        const container = document.getElementById('medicineRowsContainer');
        const div = document.createElement('div');
        div.className = 'row g-2 mb-2 med-item-row align-items-center';
        div.innerHTML = `
            <div class="col-md-4">
                <input type="text" name="item_name[]" class="form-control rounded-3" placeholder="Medicine Name" required>
            </div>
            <div class="col-md-3">
                <input type="text" name="item_dosage[]" class="form-control rounded-3" placeholder="Dosage">
            </div>
            <div class="col-md-2">
                <input type="number" name="item_qty[]" class="form-control rounded-3" value="1" min="1">
            </div>
            <div class="col-md-2">
                <input type="number" step="0.01" name="item_price[]" class="form-control rounded-3" placeholder="Price ₹" required>
            </div>
            <div class="col-md-1 text-center">
                <button type="button" class="btn btn-sm btn-outline-danger rounded-circle" onclick="removeRow(this)"><i class="fa-solid fa-trash"></i></button>
            </div>
        `;
        container.appendChild(div);
    }

    function removeRow(btn) {
        const rows = document.querySelectorAll('.med-item-row');
        if (rows.length > 1) {
            btn.closest('.med-item-row').remove();
        } else {
            alert('At least one medicine item is required.');
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
