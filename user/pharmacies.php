<?php
/**
 * Swastya Setu - Pharmacy Locator (Leaflet.js + OpenStreetMap)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('patient');
$user = currentUser();

$search = trim($_GET['search'] ?? '');
$filter24x7 = isset($_GET['is_24x7']) ? 1 : 0;
$filterOpen = isset($_GET['is_open']) ? 1 : 0;
$filterDelivery = isset($_GET['home_delivery']) ? 1 : 0;

$pharmacies = [];

try {
    $db = getDB();
    $sql = "SELECT * FROM pharmacies WHERE status = 'approved'";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (name LIKE ? OR city LIKE ? OR address LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }

    if ($filter24x7) {
        $sql .= " AND is_24x7 = 1";
    }

    if ($filterOpen) {
        $sql .= " AND is_open = 1";
    }

    if ($filterDelivery) {
        $sql .= " AND home_delivery = 1";
    }

    $sql .= " ORDER BY is_24x7 DESC, name ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $pharmacies = $stmt->fetchAll();
} catch (Exception $e) {
    $pharmacies = [];
}

$pageTitle = 'Pharmacy Locator & Medicine Express Delivery';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="content-wrapper">
        <div class="container-fluid p-0">
            
            <?= displayFlash() ?>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h2 class="fw-bold mb-1"><i class="fa-solid fa-store text-primary me-2"></i> Verified Pharmacy Locator</h2>
                    <p class="text-muted small mb-0">Locate 24/7 pharmacies near you, upload prescriptions, and order doorstep medicine delivery.</p>
                </div>
                <a href="<?= BASE_URL ?>/user/upload_prescription.php" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">
                    <i class="fa-solid fa-file-arrow-up me-1"></i> Upload Prescription
                </a>
            </div>

            <!-- Search & Filter Controls -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <form method="GET" action="pharmacies.php">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Search Pharmacy or Area</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                                    <input type="text" name="search" class="form-control border-start-0" placeholder="e.g. Apollo Pharmacy, Tata 1mg, Connaught Place..." value="<?= htmlspecialchars($search) ?>">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="d-flex flex-wrap align-items-center gap-3 pt-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_24x7" id="f24x7" value="1" <?= $filter24x7 ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="f24x7">24x7 Open</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_open" id="fOpen" value="1" <?= $filterOpen ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="fOpen">Open Now</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="home_delivery" id="fDelivery" value="1" <?= $filterDelivery ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="fDelivery">Home Delivery</label>
                                    </div>
                                    <button type="submit" class="btn btn-primary rounded-pill px-3 ms-auto">
                                        <i class="fa-solid fa-filter me-1"></i> Apply
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Leaflet.js Interactive Pharmacy Map -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark small"><i class="fa-solid fa-map-location-dot text-primary me-2"></i> Pharmacies Map Radar (OpenStreetMap)</span>
                    <button class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="locateUser()"><i class="fa-solid fa-crosshairs me-1"></i> My Location</button>
                </div>
                <div id="pharmacyMap" style="height: 380px; width: 100%;"></div>
            </div>

            <!-- Pharmacies Cards Grid -->
            <h5 class="fw-bold mb-3">Verified Pharmacies (<?= count($pharmacies) ?> Available)</h5>

            <?php if (empty($pharmacies)): ?>
                <div class="card border-0 shadow-sm rounded-4 text-center p-5">
                    <i class="fa-solid fa-store-slash fs-1 text-muted opacity-50 mb-3"></i>
                    <h5 class="text-dark">No Pharmacies Found</h5>
                    <p class="text-muted small">Try adjusting your search criteria or resetting the filters.</p>
                    <a href="pharmacies.php" class="btn btn-outline-primary rounded-pill px-4 mx-auto">Reset Filters</a>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($pharmacies as $p): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card border-0 shadow-sm rounded-4 h-100 d-flex flex-column hover-lift">
                                <div class="card-body p-4 d-flex flex-column">
                                    
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <span class="badge bg-success-subtle text-success rounded-pill px-3 py-1">
                                            <?= $p['is_open'] ? 'Open Now' : 'Closed' ?>
                                        </span>
                                        <?php if ($p['is_24x7']): ?>
                                            <span class="badge bg-danger-subtle text-danger rounded-pill px-2 py-1 small">
                                                <i class="fa-solid fa-clock me-1"></i> 24x7
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($p['name']) ?></h5>
                                    <small class="text-muted mb-2">Pharmacist: <?= htmlspecialchars($p['owner_name']) ?> (Lic: <?= htmlspecialchars($p['license_number']) ?>)</small>
                                    <p class="text-muted small mb-2"><i class="fa-solid fa-location-dot text-danger me-1"></i> <?= htmlspecialchars($p['address']) ?>, <?= htmlspecialchars($p['city']) ?></p>

                                    <?php if ($p['home_delivery']): ?>
                                        <div class="small text-success fw-semibold mb-2">
                                            <i class="fa-solid fa-truck-fast me-1"></i> Home Delivery Available (30-45 Mins)
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($p['discount_info'])): ?>
                                        <div class="small text-primary fw-medium mb-3">
                                            <i class="fa-solid fa-tag me-1"></i> <?= htmlspecialchars($p['discount_info']) ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="mt-auto pt-3 border-top d-flex gap-2">
                                        <a href="<?= BASE_URL ?>/user/upload_prescription.php?pharmacy_id=<?= $p['id'] ?>" class="btn btn-primary btn-sm rounded-pill flex-grow-1 fw-semibold">
                                            <i class="fa-solid fa-file-arrow-up me-1"></i> Upload Rx
                                        </a>
                                        <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $p['latitude'] ?>,<?= $p['longitude'] ?>" target="_blank" class="btn btn-outline-secondary btn-sm rounded-pill px-3" title="Get Directions">
                                            <i class="fa-solid fa-diamond-turn-right"></i>
                                        </a>
                                        <a href="tel:<?= htmlspecialchars($p['phone']) ?>" class="btn btn-outline-success btn-sm rounded-pill px-3" title="Call Pharmacy">
                                            <i class="fa-solid fa-phone"></i>
                                        </a>
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const defaultLat = 28.6250;
        const defaultLng = 77.2150;
        const map = L.map('pharmacyMap').setView([defaultLat, defaultLng], 12);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        const pharmaciesData = <?= json_encode($pharmacies) ?>;
        const markersGroup = L.featureGroup().addTo(map);

        pharmaciesData.forEach(p => {
            const lat = parseFloat(p.latitude) || defaultLat;
            const lng = parseFloat(p.longitude) || defaultLng;

            const popupContent = `
                <div style="min-width: 180px; font-family: 'Inter', sans-serif;">
                    <strong style="color: #1E88E5; font-size: 14px;">${p.name}</strong><br>
                    <small style="color: #666;">${p.address}</small><br>
                    <div style="margin: 6px 0; font-size: 12px; color: #2e7d32; font-weight: bold;">
                        ${p.home_delivery ? 'Home Delivery Available' : 'In-store Pickup'}
                    </div>
                    <a href="upload_prescription.php?pharmacy_id=${p.id}" style="display: block; text-align: center; background: #1E88E5; color: white; padding: 4px 8px; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: bold; margin-top: 6px;">
                        Upload Prescription
                    </a>
                </div>
            `;

            L.marker([lat, lng]).bindPopup(popupContent).addTo(markersGroup);
        });

        if (pharmaciesData.length > 0) {
            map.fitBounds(markersGroup.getBounds().pad(0.1));
        }

        window.locateUser = function() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(pos => {
                    map.setView([pos.coords.latitude, pos.coords.longitude], 14);
                    L.circleMarker([pos.coords.latitude, pos.coords.longitude], { color: 'green', radius: 8 }).addTo(map).bindPopup("You are here").openPopup();
                });
            }
        };
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
