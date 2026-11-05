<?php
/**
 * Swastya Setu - Master Footer Template
 */

require_once __DIR__ . '/../config/config.php';
?>

<!-- Floating Emergency SOS Button (Present across pages) -->
<a href="<?= BASE_URL ?>/user/sos.php" class="floating-sos-btn" title="Emergency 108 SOS Dispatch">
    <i class="fa-solid fa-truck-medical"></i>
</a>

<!-- Unified Footer -->
<footer class="bg-white border-top py-4 mt-auto">
    <div class="container-fluid px-4">
        <div class="row g-4 align-items-center">
            <div class="col-md-4 text-center text-md-start">
                <div class="d-flex align-items-center gap-2 justify-content-center justify-content-md-start mb-2">
                    <i class="fa-solid fa-heart-pulse text-primary fs-4"></i>
                    <strong class="fs-5 text-dark"><?= APP_NAME ?></strong>
                </div>
                <p class="text-muted small mb-0"><?= APP_TAGLINE ?></p>
                <small class="text-muted">&copy; <?= date('Y') ?> Swastya Setu. All rights reserved. (MCA Project Demo)</small>
            </div>
            
            <div class="col-md-4 text-center">
                <div class="p-2 rounded-3 bg-light d-inline-block text-start border">
                    <div class="fw-bold text-danger small mb-1"><i class="fa-solid fa-phone-volume me-1"></i> National Emergency Helplines</div>
                    <div class="small text-muted">Ambulance: <strong class="text-dark">108</strong> | National Helpline: <strong class="text-dark">112</strong></div>
                    <div class="small text-muted">Women Helpline: <strong class="text-dark">1091</strong> | Mental Health (KIRAN): <strong class="text-dark">1800-599-0019</strong></div>
                </div>
            </div>

            <div class="col-md-4 text-center text-md-end">
                <div class="d-flex gap-3 justify-content-center justify-content-md-end text-muted small">
                    <a href="<?= BASE_URL ?>/index.php" class="text-decoration-none text-muted">Home</a>
                    <a href="<?= BASE_URL ?>/user/hospitals.php" class="text-decoration-none text-muted">Hospitals</a>
                    <a href="<?= BASE_URL ?>/user/pharmacies.php" class="text-decoration-none text-muted">Pharmacies</a>
                    <a href="<?= BASE_URL ?>/user/prediction.php" class="text-decoration-none text-muted">AI Predictor</a>
                </div>
                <div class="mt-2 text-muted" style="font-size: 0.75rem;">
                    Built with PHP, MySQL, Flask AI, Leaflet.js & Bootstrap 5
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Leaflet.js JS for Interactive Maps -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<!-- Chart.js for Dashboards & Analytics -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Global Script for Confirmations & Utilities -->
<script>
    function confirmAction(message) {
        return confirm(message || 'Are you sure you want to perform this action?');
    }

    // Auto-dismiss alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(alert => {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                if (bsAlert) bsAlert.close();
            });
        }, 5000);
    });
</script>
</body>
</html>
