<?php
/**
 * Swastya Setu - Pharmacy Profile & Service Settings
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
    // Default placeholder for new partner
    $pharmacy = [
        'id' => 0,
        'name' => $user['name'] ?? '',
        'owner_name' => $user['name'] ?? '',
        'license_number' => 'DL-PH-' . rand(1000, 9999),
        'address' => '',
        'city' => 'New Delhi',
        'phone' => $user['phone'] ?? '',
        'email' => $user['email'] ?? '',
        'is_24x7' => 1,
        'home_delivery' => 1,
        'discount_info' => '15% Off on Prescriptions'
    ];
}
$pharmacyId = (int)$pharmacy['id'];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($token)) {
        $error = 'Security session expired. Please refresh.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $owner = trim($_POST['owner_name'] ?? '');
        $license = trim($_POST['license_number'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $is24x7 = isset($_POST['is_24x7']) ? 1 : 0;
        $isOpen = isset($_POST['is_open']) ? 1 : 0;
        $delivery = isset($_POST['home_delivery']) ? 1 : 0;
        $discount = trim($_POST['discount_info'] ?? '');
        $lat = (float)($_POST['latitude'] ?? 28.6250);
        $lng = (float)($_POST['longitude'] ?? 77.2150);

        if (empty($name) || empty($phone) || empty($address)) {
            $error = 'Pharmacy name, phone, and address are required.';
        } else {
            try {
                $upd = $db->prepare("UPDATE pharmacies SET name = ?, owner_name = ?, license_number = ?, phone = ?, city = ?, address = ?, is_24x7 = ?, is_open = ?, home_delivery = ?, discount_info = ?, latitude = ?, longitude = ? WHERE id = ?");
                $upd->execute([$name, $owner, $license, $phone, $city, $address, $is24x7, $isOpen, $delivery, $discount, $lat, $lng, $pharmacyId]);
                
                setFlash('success', 'Pharmacy profile and delivery settings updated successfully.');
                header('Location: ' . BASE_URL . '/pharmacy/profile.php');
                exit;
            } catch (Exception $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Refresh record
$pharmStmt->execute([$user['id']]);
$pharmacy = $pharmStmt->fetch() ?: $pharmacy;

$pageTitle = 'Pharmacy Profile & Operational Hours';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="content-wrapper">
        <div class="container-fluid p-0">
            
            <?= displayFlash() ?>

            <div class="mb-4">
                <h2 class="fw-bold mb-1"><i class="fa-solid fa-store text-primary me-2"></i> Pharmacy Profile & Settings</h2>
                <p class="text-muted small mb-0">Update store details, delivery radius, operating hours, and customer discounts.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger rounded-4 shadow-sm mb-4"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                        <form method="POST" action="profile.php">
                            <?= csrfField() ?>
                            
                            <h5 class="fw-bold text-dark mb-3">Store Information</h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Pharmacy Name *</label>
                                    <input type="text" name="name" class="form-control rounded-3" value="<?= htmlspecialchars($pharmacy['name']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Pharmacist / Owner Name *</label>
                                    <input type="text" name="owner_name" class="form-control rounded-3" value="<?= htmlspecialchars($pharmacy['owner_name']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Drug License Number</label>
                                    <input type="text" name="license_number" class="form-control rounded-3" value="<?= htmlspecialchars($pharmacy['license_number']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Phone Number *</label>
                                    <input type="text" name="phone" class="form-control rounded-3" value="<?= htmlspecialchars($pharmacy['phone']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">City</label>
                                    <input type="text" name="city" class="form-control rounded-3" value="<?= htmlspecialchars($pharmacy['city']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Discount Offer Text</label>
                                    <input type="text" name="discount_info" class="form-control rounded-3" value="<?= htmlspecialchars($pharmacy['discount_info']) ?>" placeholder="e.g. 20% off on all Generics">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-semibold text-muted">Store Shop Address *</label>
                                    <textarea name="address" class="form-control rounded-3" rows="2" required><?= htmlspecialchars($pharmacy['address']) ?></textarea>
                                </div>
                            </div>

                            <h5 class="fw-bold text-dark mb-3">Operational Services</h5>
                            <div class="row g-3 mb-4">
                                <div class="col-12">
                                    <div class="d-flex flex-wrap gap-4 pt-1">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="is_open" id="swOpen" <?= $pharmacy['is_open'] ? 'checked' : '' ?>>
                                            <label class="form-check-label small fw-semibold" for="swOpen">Store Currently Open</label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="is_24x7" id="sw247" <?= $pharmacy['is_24x7'] ? 'checked' : '' ?>>
                                            <label class="form-check-label small fw-semibold" for="sw247">24x7 Operational</label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="home_delivery" id="swDel" <?= $pharmacy['home_delivery'] ? 'checked' : '' ?>>
                                            <label class="form-check-label small fw-semibold" for="swDel">Home Delivery Service Active</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <h5 class="fw-bold text-dark mb-3">Map GPS Coordinates (Leaflet.js)</h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Latitude</label>
                                    <input type="text" name="latitude" class="form-control rounded-3" value="<?= $pharmacy['latitude'] ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Longitude</label>
                                    <input type="text" name="longitude" class="form-control rounded-3" value="<?= $pharmacy['longitude'] ?>">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Save Pharmacy Details
                            </button>
                        </form>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white text-center">
                        <div class="d-inline-flex p-3 rounded-circle bg-teal-subtle text-teal mb-3" style="background: #e0f2f1; color: #00897B;">
                            <i class="fa-solid fa-prescription-bottle-medical fs-1"></i>
                        </div>
                        <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($pharmacy['name']) ?></h4>
                        <span class="badge bg-success-subtle text-success rounded-pill px-3 py-1 mb-3">Licensed Partner</span>
                        <p class="text-muted small mb-0"><i class="fa-solid fa-location-dot me-1 text-danger"></i> <?= htmlspecialchars($pharmacy['address']) ?></p>
                        <hr>
                        <div class="text-start small text-muted">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Owner:</span> <strong class="text-dark"><?= htmlspecialchars($pharmacy['owner_name']) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>License:</span> <strong class="text-dark"><?= htmlspecialchars($pharmacy['license_number']) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Home Delivery:</span> <strong class="text-success"><?= $pharmacy['home_delivery'] ? 'Enabled' : 'Disabled' ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Status:</span> <strong class="text-primary text-capitalize"><?= $pharmacy['status'] ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
