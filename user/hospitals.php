<?php
/**
 * Swastya Setu - Interactive Hospital Locator (Leaflet.js + OpenStreetMap)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('patient');
$user = currentUser();

// Search & Filter parameters
$search = trim($_GET['search'] ?? '');
$type = $_GET['type'] ?? 'all';
$filterEmergency = isset($_GET['emergency']) ? 1 : 0;
$filterICU = isset($_GET['icu']) ? 1 : 0;
$filterBloodBank = isset($_GET['blood_bank']) ? 1 : 0;

$hospitals = [];

try {
    $db = getDB();
    $sql = "SELECT * FROM hospitals WHERE status = 'approved'";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (name LIKE ? OR city LIKE ? OR address LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }

    if ($type !== 'all' && in_array($type, ['Govt', 'Private'])) {
        $sql .= " AND hospital_type = ?";
        $params[] = $type;
    }

    if ($filterEmergency) {
        $sql .= " AND emergency_services = 1";
    }

    if ($filterICU) {
        $sql .= " AND icu_available = 1";
    }

    if ($filterBloodBank) {
        $sql .= " AND blood_bank = 1";
    }

    $sql .= " ORDER BY available_beds DESC, name ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $hospitals = $stmt->fetchAll();
} catch (Exception $e) {
    $hospitals = [];
}

$pageTitle = 'Find Hospitals & Live Bed Tracker';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="content-wrapper">
        <div class="container-fluid p-0">
            
            <?= displayFlash() ?>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h2 class="fw-bold mb-1"><i class="fa-solid fa-hospital text-primary me-2"></i> Hospital & Bed Locator</h2>
                    <p class="text-muted small mb-0">Search verified hospitals, check real-time bed availability, and book specialist consultations.</p>
                </div>
                <a href="<?= BASE_URL ?>/user/appointments.php" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">
                    <i class="fa-solid fa-calendar-plus me-1"></i> Book Appointment
                </a>
            </div>

            <!-- Filter & Search Controls -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <form method="GET" action="hospitals.php">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">Search by Name or City</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                                    <input type="text" name="search" class="form-control border-start-0" placeholder="e.g. Apollo, AIIMS, Delhi..." value="<?= htmlspecialchars($search) ?>">
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label small fw-semibold text-muted">Hospital Category</label>
                                <select name="type" class="form-select">
                                    <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>All (Government & Private)</option>
                                    <option value="Govt" <?= $type === 'Govt' ? 'selected' : '' ?>>Government Only</option>
                                    <option value="Private" <?= $type === 'Private' ? 'selected' : '' ?>>Private Only</option>
                                </select>
                            </div>

                            <div class="col-md-5">
                                <div class="d-flex flex-wrap align-items-center gap-3 pt-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="emergency" id="fEmergency" value="1" <?= $filterEmergency ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="fEmergency">24x7 Emergency</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="icu" id="fICU" value="1" <?= $filterICU ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="fICU">ICU Available</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="blood_bank" id="fBlood" value="1" <?= $filterBloodBank ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="fBlood">Blood Bank</label>
                                    </div>
                                    <button type="submit" class="btn btn-primary rounded-pill px-3 ms-auto">
                                        <i class="fa-solid fa-filter me-1"></i> Filter
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Leaflet.js Interactive Map Container -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark small"><i class="fa-solid fa-map-location-dot text-primary me-2"></i> Live Hospital Map (OpenStreetMap)</span>
                    <button class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="locateUser()"><i class="fa-solid fa-crosshairs me-1"></i> My Location</button>
                </div>
                <div id="hospitalMap" style="height: 380px; width: 100%;"></div>
            </div>

            <!-- Hospital Cards Grid -->
            <h5 class="fw-bold mb-3">Available Hospitals (<?= count($hospitals) ?> Found)</h5>

            <?php if (empty($hospitals)): ?>
                <div class="card border-0 shadow-sm rounded-4 text-center p-5">
                    <i class="fa-solid fa-hospital fs-1 text-muted opacity-50 mb-3"></i>
                    <h5 class="text-dark">No Hospitals Matched Your Filters</h5>
                    <p class="text-muted small">Try searching with broader parameters or clear your filter selections.</p>
                    <a href="hospitals.php" class="btn btn-outline-primary rounded-pill px-4 mx-auto">Reset Filters</a>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($hospitals as $hosp): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card border-0 shadow-sm rounded-4 h-100 d-flex flex-column hover-lift">
                                <div class="card-body p-4 d-flex flex-column">
                                    
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <span class="badge <?= $hosp['hospital_type'] === 'Govt' ? 'bg-primary-subtle text-primary' : 'bg-purple-subtle text-purple' ?> rounded-pill px-3 py-1" style="<?= $hosp['hospital_type'] === 'Private' ? 'background: #f3e5f5; color: #7b1fa2;' : '' ?>">
                                            <?= htmlspecialchars($hosp['hospital_type']) ?> Hospital
                                        </span>
                                        <span class="badge bg-success-subtle text-success rounded-pill px-2 py-1 small">
                                            <i class="fa-solid fa-bed me-1"></i> <?= $hosp['available_beds'] ?> Beds Open
                                        </span>
                                    </div>

                                    <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($hosp['name']) ?></h5>
                                    <p class="text-muted small mb-2"><i class="fa-solid fa-location-dot text-danger me-1"></i> <?= htmlspecialchars($hosp['city']) ?> • <?= htmlspecialchars($hosp['address']) ?></p>

                                    <!-- Facilities Badges -->
                                    <div class="d-flex flex-wrap gap-1 mb-3">
                                        <?php if ($hosp['emergency_services']): ?>
                                            <span class="badge bg-danger-subtle text-danger small"><i class="fa-solid fa-truck-medical me-1"></i> 24x7 Emergency</span>
                                        <?php endif; ?>
                                        <?php if ($hosp['icu_available']): ?>
                                            <span class="badge bg-info-subtle text-info small"><i class="fa-solid fa-heart-pulse me-1"></i> ICU Available</span>
                                        <?php endif; ?>
                                        <?php if ($hosp['blood_bank']): ?>
                                            <span class="badge bg-danger-subtle text-danger small"><i class="fa-solid fa-droplet me-1"></i> Blood Bank</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="mt-auto pt-3 border-top d-flex gap-2">
                                        <a href="<?= BASE_URL ?>/user/appointments.php?hospital_id=<?= $hosp['id'] ?>" class="btn btn-primary btn-sm rounded-pill flex-grow-1 fw-semibold">
                                            <i class="fa-solid fa-calendar-check me-1"></i> Book Visit
                                        </a>
                                        <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $hosp['latitude'] ?>,<?= $hosp['longitude'] ?>" target="_blank" class="btn btn-outline-secondary btn-sm rounded-pill px-3" title="Get Directions">
                                            <i class="fa-solid fa-diamond-turn-right"></i>
                                        </a>
                                        <a href="tel:<?= htmlspecialchars($hosp['phone']) ?>" class="btn btn-outline-success btn-sm rounded-pill px-3" title="Call Hospital">
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

<!-- Leaflet Map Initialization Script -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Map centered around New Delhi coordinates
        const defaultLat = 28.6139;
        const defaultLng = 77.2090;
        const map = L.map('hospitalMap').setView([defaultLat, defaultLng], 12);

        // OpenStreetMap Tile Layer (Free, No API Key needed)
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        // Hospital Markers Data from PHP MySQL
        const hospitalsData = <?= json_encode($hospitals) ?>;
        const markersGroup = L.featureGroup().addTo(map);

        hospitalsData.forEach(hosp => {
            const lat = parseFloat(hosp.latitude) || defaultLat;
            const lng = parseFloat(hosp.longitude) || defaultLng;

            const popupContent = `
                <div style="min-width: 180px; font-family: 'Inter', sans-serif;">
                    <strong style="color: #1E88E5; font-size: 14px;">${hosp.name}</strong><br>
                    <small style="color: #666;">${hosp.address}, ${hosp.city}</small><br>
                    <div style="margin: 6px 0; font-size: 12px;">
                        <span style="background: #e8f5e9; color: #2e7d32; padding: 2px 6px; border-radius: 4px; font-weight: bold;">
                            ${hosp.available_beds} Beds Open
                        </span>
                    </div>
                    <a href="appointments.php?hospital_id=${hosp.id}" style="display: block; text-align: center; background: #1E88E5; color: white; padding: 4px 8px; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: bold; margin-top: 6px;">
                        Book Appointment
                    </a>
                </div>
            `;

            const marker = L.marker([lat, lng])
                .bindPopup(popupContent)
                .addTo(markersGroup);
        });

        if (hospitalsData.length > 0) {
            map.fitBounds(markersGroup.getBounds().pad(0.1));
        }

        window.locateUser = function() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(position => {
                    const uLat = position.coords.latitude;
                    const uLng = position.coords.longitude;
                    map.setView([uLat, uLng], 14);
                    L.circleMarker([uLat, uLng], { color: 'blue', radius: 8 }).addTo(map).bindPopup("Your Current Location").openPopup();
                }, () => {
                    alert('Geolocation access denied. Displaying standard city map.');
                });
            } else {
                alert('Geolocation is not supported by your browser.');
            }
        };
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
