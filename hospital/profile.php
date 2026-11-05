<?php
/**
 * Swastya Setu - Hospital Profile & Facility Management
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('hospital');
$user = currentUser();
$db = getDB();

$hospStmt = $db->prepare("SELECT * FROM hospitals WHERE user_id = ? LIMIT 1");
$hospStmt->execute([$user['id']]);
$hospital = $hospStmt->fetch();

if (!$hospital) {
    // Initialize default record for new hospital partner
    $hospital = [
        'id' => 0,
        'name' => $user['name'] ?? '',
        'city' => 'New Delhi',
        'address' => '',
        'phone' => $user['phone'] ?? '',
        'email' => $user['email'] ?? '',
        'hospital_type' => 'Private',
        'emergency_services' => 1,
        'icu_available' => 1,
        'blood_bank' => 1,
        'available_beds' => 20,
        'total_beds' => 50,
        'latitude' => 28.6139,
        'longitude' => 77.2090
    ];
}
$hospitalId = (int)$hospital['id'];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($token)) {
        $error = 'Security session expired. Please refresh.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $totalBeds = (int)($_POST['total_beds'] ?? 50);
        $availBeds = (int)($_POST['available_beds'] ?? 10);
        $emergency = isset($_POST['emergency_services']) ? 1 : 0;
        $icu = isset($_POST['icu_available']) ? 1 : 0;
        $blood = isset($_POST['blood_bank']) ? 1 : 0;
        $lat = (float)($_POST['latitude'] ?? 28.6139);
        $lng = (float)($_POST['longitude'] ?? 77.2090);

        if (empty($name) || empty($phone) || empty($address)) {
            $error = 'Hospital name, phone, and address are required.';
        } else {
            try {
                $upd = $db->prepare("UPDATE hospitals SET name = ?, phone = ?, city = ?, address = ?, total_beds = ?, available_beds = ?, emergency_services = ?, icu_available = ?, blood_bank = ?, latitude = ?, longitude = ? WHERE id = ?");
                $upd->execute([$name, $phone, $city, $address, $totalBeds, $availBeds, $emergency, $icu, $blood, $lat, $lng, $hospitalId]);
                
                setFlash('success', 'Hospital profile and bed capacity updated successfully.');
                header('Location: ' . BASE_URL . '/hospital/profile.php');
                exit;
            } catch (Exception $e) {
                $error = 'Database update error: ' . $e->getMessage();
            }
        }
    }
}

// Refresh hospital record
$hospStmt->execute([$user['id']]);
$hospital = $hospStmt->fetch() ?: $hospital;

$pageTitle = 'Hospital Profile & Bed Capacity';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="content-wrapper">
        <div class="container-fluid p-0">
            
            <?= displayFlash() ?>

            <div class="mb-4">
                <h2 class="fw-bold mb-1"><i class="fa-solid fa-hospital-user text-primary me-2"></i> Hospital Profile & Facilities</h2>
                <p class="text-muted small mb-0">Manage hospital details, emergency facility flags, and real-time bed capacity.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger rounded-4 shadow-sm mb-4"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                        <form method="POST" action="profile.php">
                            <?= csrfField() ?>
                            
                            <h5 class="fw-bold text-dark mb-3">General Information</h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-8">
                                    <label class="form-label small fw-semibold text-muted">Hospital Name *</label>
                                    <input type="text" name="name" class="form-control rounded-3" value="<?= htmlspecialchars($hospital['name']) ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold text-muted">Phone Number *</label>
                                    <input type="text" name="phone" class="form-control rounded-3" value="<?= htmlspecialchars($hospital['phone']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">City</label>
                                    <input type="text" name="city" class="form-control rounded-3" value="<?= htmlspecialchars($hospital['city']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Contact Email</label>
                                    <input type="email" class="form-control rounded-3 bg-light" value="<?= htmlspecialchars($hospital['email']) ?>" readonly>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-semibold text-muted">Full Address *</label>
                                    <textarea name="address" class="form-control rounded-3" rows="2" required><?= htmlspecialchars($hospital['address']) ?></textarea>
                                </div>
                            </div>

                            <h5 class="fw-bold text-dark mb-3">Live Bed Capacity & Emergency Services</h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Total Bed Capacity</label>
                                    <input type="number" name="total_beds" class="form-control rounded-3" value="<?= $hospital['total_beds'] ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Currently Available Beds *</label>
                                    <input type="number" name="available_beds" class="form-control rounded-3 fw-bold text-success" value="<?= $hospital['available_beds'] ?>" required>
                                </div>
                                <div class="col-12">
                                    <div class="d-flex flex-wrap gap-4 pt-2">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="emergency_services" id="swEm" <?= $hospital['emergency_services'] ? 'checked' : '' ?>>
                                            <label class="form-check-label small fw-semibold" for="swEm">24x7 Emergency Services</label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="icu_available" id="swICU" <?= $hospital['icu_available'] ? 'checked' : '' ?>>
                                            <label class="form-check-label small fw-semibold" for="swICU">ICU Unit Active</label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="blood_bank" id="swBlood" <?= $hospital['blood_bank'] ? 'checked' : '' ?>>
                                            <label class="form-check-label small fw-semibold" for="swBlood">Blood Bank Available</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <h5 class="fw-bold text-dark mb-3">Map GPS Coordinates (OpenStreetMap)</h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Latitude</label>
                                    <input type="text" name="latitude" class="form-control rounded-3" value="<?= $hospital['latitude'] ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Longitude</label>
                                    <input type="text" name="longitude" class="form-control rounded-3" value="<?= $hospital['longitude'] ?>">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Save Changes
                            </button>
                        </form>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white text-center">
                        <div class="d-inline-flex p-3 rounded-circle bg-primary-subtle text-primary mb-3">
                            <i class="fa-solid fa-hospital fs-1"></i>
                        </div>
                        <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($hospital['name']) ?></h4>
                        <span class="badge bg-success-subtle text-success rounded-pill px-3 py-1 mb-3">Verified Partner</span>
                        <p class="text-muted small mb-0"><i class="fa-solid fa-location-dot me-1 text-danger"></i> <?= htmlspecialchars($hospital['address']) ?></p>
                        <hr>
                        <div class="text-start small text-muted">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Hospital Type:</span> <strong class="text-dark"><?= $hospital['hospital_type'] ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Available Beds:</span> <strong class="text-success"><?= $hospital['available_beds'] ?> / <?= $hospital['total_beds'] ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Status:</span> <strong class="text-primary text-capitalize"><?= $hospital['status'] ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
