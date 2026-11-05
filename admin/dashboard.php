<?php
/**
 * Swastya Setu - Master Administrator Control Center
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');
$user = currentUser();
$db = getDB();

// Statistics Queries
$totalPatients = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'patient'")->fetchColumn();
$totalHospitals = (int)$db->query("SELECT COUNT(*) FROM hospitals")->fetchColumn();
$totalPharmacies = (int)$db->query("SELECT COUNT(*) FROM pharmacies")->fetchColumn();
$totalDoctors = (int)$db->query("SELECT COUNT(*) FROM doctors")->fetchColumn();
$totalAppts = (int)$db->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
$totalOrders = (int)$db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalRevenue = (float)$db->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE order_status != 'cancelled'")->fetchColumn();
$pendingApprovals = (int)$db->query("SELECT (SELECT COUNT(*) FROM hospitals WHERE status = 'pending') + (SELECT COUNT(*) FROM pharmacies WHERE status = 'pending')")->fetchColumn();
$totalSOS = (int)$db->query("SELECT COUNT(*) FROM ambulance_requests")->fetchColumn();

// Pending Hospitals & Pharmacies Queue
$pendingHospitals = $db->query("SELECT * FROM hospitals WHERE status = 'pending' ORDER BY id DESC LIMIT 5")->fetchAll();
$pendingPharmacies = $db->query("SELECT * FROM pharmacies WHERE status = 'pending' ORDER BY id DESC LIMIT 5")->fetchAll();

$pageTitle = 'Master Administrator Portal & Analytics';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="content-wrapper">
        <div class="container-fluid p-0">
            
            <?= displayFlash() ?>

            <!-- Admin Banner -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 text-white p-4" style="background: linear-gradient(135deg, #1A237E, #283593);">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h2 class="fw-bold mb-1">Swastya Setu Platform Administration 🛡️</h2>
                        <p class="mb-0 opacity-75">Full platform telemetry, partner registrations, analytics, and service audits.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="<?= BASE_URL ?>/admin/hospitals.php" class="btn btn-light rounded-pill px-3 fw-semibold text-primary">
                            <i class="fa-solid fa-hospital me-1"></i> Hospitals (<?= $totalHospitals ?>)
                        </a>
                        <a href="<?= BASE_URL ?>/admin/pharmacies.php" class="btn btn-outline-light rounded-pill px-3 fw-semibold">
                            <i class="fa-solid fa-store me-1"></i> Pharmacies (<?= $totalPharmacies ?>)
                        </a>
                    </div>
                </div>
            </div>

            <!-- Analytics Stats Grid -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                        <span class="small text-muted fw-semibold d-block mb-1">Registered Patients</span>
                        <div class="d-flex align-items-center justify-content-between">
                            <h3 class="fw-bold mb-0 text-primary"><?= number_format($totalPatients) ?></h3>
                            <i class="fa-solid fa-users text-primary fs-3 opacity-25"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                        <span class="small text-muted fw-semibold d-block mb-1">Network Hospitals</span>
                        <div class="d-flex align-items-center justify-content-between">
                            <h3 class="fw-bold mb-0 text-success"><?= $totalHospitals ?></h3>
                            <i class="fa-solid fa-hospital text-success fs-3 opacity-25"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                        <span class="small text-muted fw-semibold d-block mb-1">Partner Pharmacies</span>
                        <div class="d-flex align-items-center justify-content-between">
                            <h3 class="fw-bold mb-0 text-info"><?= $totalPharmacies ?></h3>
                            <i class="fa-solid fa-store text-info fs-3 opacity-25"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                        <span class="small text-muted fw-semibold d-block mb-1">Medicine Orders</span>
                        <div class="d-flex align-items-center justify-content-between">
                            <h3 class="fw-bold mb-0 text-warning"><?= $totalOrders ?></h3>
                            <i class="fa-solid fa-cart-shopping text-warning fs-3 opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chart.js Analytics Visualizations -->
            <div class="row g-4 mb-4">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                        <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-chart-line text-primary me-2"></i> Monthly Healthcare Activity Trends</h5>
                        <canvas id="activityChart" style="max-height: 280px;"></canvas>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                        <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-chart-pie text-info me-2"></i> Partner Distribution</h5>
                        <canvas id="partnerPieChart" style="max-height: 240px;"></canvas>
                        <div class="text-center mt-3 small text-muted">
                            Hospitals: <strong><?= $totalHospitals ?></strong> | Pharmacies: <strong><?= $totalPharmacies ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Action Approvals & SOS Monitor -->
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-user-check text-success me-2"></i> Hospital Partner Approvals</h5>
                            <a href="<?= BASE_URL ?>/admin/hospitals.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">View All</a>
                        </div>
                        <?php if (empty($pendingHospitals)): ?>
                            <div class="text-center py-4 text-muted small">
                                <i class="fa-solid fa-circle-check fs-2 text-success opacity-50 mb-2"></i>
                                <p class="mb-0">All registered hospitals are reviewed & approved.</p>
                            </div>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-2">
                                <?php foreach ($pendingHospitals as $h): ?>
                                    <div class="p-2 rounded-3 bg-light border d-flex justify-content-between align-items-center small">
                                        <div>
                                            <strong><?= htmlspecialchars($h['name']) ?></strong>
                                            <span class="text-muted d-block"><?= htmlspecialchars($h['city']) ?> • Phone: <?= htmlspecialchars($h['phone']) ?></span>
                                        </div>
                                        <div class="d-flex gap-1">
                                            <a href="hospitals.php?action=approve&id=<?= $h['id'] ?>" class="btn btn-sm btn-success rounded-pill px-2">Approve</a>
                                            <a href="hospitals.php?action=reject&id=<?= $h['id'] ?>" class="btn btn-sm btn-danger rounded-pill px-2">Reject</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-truck-medical text-danger me-2"></i> Emergency SOS Broadcasts (<?= $totalSOS ?>)</h5>
                            <a href="<?= BASE_URL ?>/admin/ambulances.php" class="btn btn-sm btn-outline-danger rounded-pill px-3">SOS Center</a>
                        </div>
                        <p class="text-muted small">
                            Real-time tracking of patient emergency alerts, GPS coordinates, and 108 ambulance response logs across all active jurisdictions.
                        </p>
                        <div class="d-flex gap-3 mt-3">
                            <div class="p-3 rounded-3 bg-light border flex-grow-1 text-center">
                                <h4 class="fw-bold text-danger mb-0"><?= $totalSOS ?></h4>
                                <small class="text-muted">Total SOS Alerts</small>
                            </div>
                            <div class="p-3 rounded-3 bg-light border flex-grow-1 text-center">
                                <h4 class="fw-bold text-primary mb-0"><?= $totalAppts ?></h4>
                                <small class="text-muted">Appointments</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Line Chart for Activity
        const ctx = document.getElementById('activityChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct'],
                    datasets: [
                        {
                            label: 'Medicine Deliveries',
                            data: [12, 19, 25, 40, 55, 68, 85, 110, 142, <?= max(150, $totalOrders + 140) ?>],
                            borderColor: '#1E88E5',
                            backgroundColor: 'rgba(30, 136, 229, 0.1)',
                            fill: true,
                            tension: 0.3
                        },
                        {
                            label: 'Doctor Appointments',
                            data: [8, 14, 22, 35, 48, 56, 74, 95, 118, <?= max(120, $totalAppts + 115) ?>],
                            borderColor: '#26C6DA',
                            backgroundColor: 'rgba(38, 198, 218, 0.1)',
                            fill: true,
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }

        // Doughnut Chart for Partner Distribution
        const pieCtx = document.getElementById('partnerPieChart');
        if (pieCtx) {
            new Chart(pieCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Hospitals', 'Pharmacies', 'Ambulances'],
                    datasets: [{
                        data: [<?= max(1, $totalHospitals) ?>, <?= max(1, $totalPharmacies) ?>, 12],
                        backgroundColor: ['#1E88E5', '#26C6DA', '#FF5252']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
