<?php
/**
 * Swastya Setu - Dynamic Role-Aware Sidebar Component
 */

require_once __DIR__ . '/auth.php';

$role = currentRole() ?? 'patient';
$currentScript = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar d-none d-lg-block">
    
    <?php if ($role === 'patient'): ?>
        <div class="text-uppercase text-muted fw-bold mb-2 ps-2" style="font-size: 0.75rem; letter-spacing: 0.5px;">Core Services</div>
        <a href="<?= BASE_URL ?>/user/dashboard.php" class="sidebar-item <?= $currentScript === 'dashboard.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-house-chimney-medical"></i> Dashboard
        </a>
        <a href="<?= BASE_URL ?>/user/prediction.php" class="sidebar-item <?= $currentScript === 'prediction.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-wand-magic-sparkles"></i> AI Diagnostics
        </a>
        <a href="<?= BASE_URL ?>/user/hospitals.php" class="sidebar-item <?= $currentScript === 'hospitals.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-hospital"></i> Find Hospitals
        </a>
        <a href="<?= BASE_URL ?>/user/appointments.php" class="sidebar-item <?= $currentScript === 'appointments.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-calendar-check"></i> Doctor Visits
        </a>

        <div class="text-uppercase text-muted fw-bold mt-4 mb-2 ps-2" style="font-size: 0.75rem; letter-spacing: 0.5px;">Pharmacy & Delivery</div>
        <a href="<?= BASE_URL ?>/user/pharmacies.php" class="sidebar-item <?= $currentScript === 'pharmacies.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-store"></i> Pharmacy Locator
        </a>
        <a href="<?= BASE_URL ?>/user/upload_prescription.php" class="sidebar-item <?= $currentScript === 'upload_prescription.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-file-arrow-up"></i> Upload Prescription
        </a>
        <a href="<?= BASE_URL ?>/user/my_prescriptions.php" class="sidebar-item <?= $currentScript === 'my_prescriptions.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-prescription"></i> My Prescriptions
        </a>
        <a href="<?= BASE_URL ?>/user/my_orders.php" class="sidebar-item <?= $currentScript === 'my_orders.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-truck-fast"></i> Order Tracking
        </a>

        <div class="text-uppercase text-muted fw-bold mt-4 mb-2 ps-2" style="font-size: 0.75rem; letter-spacing: 0.5px;">Health & Safety</div>
        <a href="<?= BASE_URL ?>/user/profile.php" class="sidebar-item <?= $currentScript === 'profile.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-id-card"></i> Health Profile
        </a>
        <a href="<?= BASE_URL ?>/user/sos.php" class="sidebar-item text-danger <?= $currentScript === 'sos.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-truck-medical text-danger"></i> Emergency 108 SOS
        </a>

    <?php elseif ($role === 'hospital'): ?>
        <div class="text-uppercase text-muted fw-bold mb-2 ps-2" style="font-size: 0.75rem; letter-spacing: 0.5px;">Hospital Admin</div>
        <a href="<?= BASE_URL ?>/hospital/dashboard.php" class="sidebar-item <?= $currentScript === 'dashboard.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-gauge"></i> Overview
        </a>
        <a href="<?= BASE_URL ?>/hospital/manage_doctors.php" class="sidebar-item <?= $currentScript === 'manage_doctors.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-user-doctor"></i> Manage Doctors
        </a>
        <a href="<?= BASE_URL ?>/hospital/appointments.php" class="sidebar-item <?= $currentScript === 'appointments.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-calendar-days"></i> Appointments Queue
        </a>
        <a href="<?= BASE_URL ?>/hospital/profile.php" class="sidebar-item <?= $currentScript === 'profile.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-hospital-user"></i> Hospital Profile & Beds
        </a>

    <?php elseif ($role === 'pharmacy'): ?>
        <div class="text-uppercase text-muted fw-bold mb-2 ps-2" style="font-size: 0.75rem; letter-spacing: 0.5px;">Pharmacy Portal</div>
        <a href="<?= BASE_URL ?>/pharmacy/dashboard.php" class="sidebar-item <?= $currentScript === 'dashboard.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-gauge"></i> Dashboard
        </a>
        <a href="<?= BASE_URL ?>/pharmacy/inventory.php" class="sidebar-item <?= $currentScript === 'inventory.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-pills"></i> Medicine Inventory
        </a>
        <a href="<?= BASE_URL ?>/pharmacy/prescriptions.php" class="sidebar-item <?= $currentScript === 'prescriptions.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-file-prescription"></i> Rx Verification Queue
        </a>
        <a href="<?= BASE_URL ?>/pharmacy/orders.php" class="sidebar-item <?= $currentScript === 'orders.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-truck-ramp-box"></i> Delivery Fulfillment
        </a>
        <a href="<?= BASE_URL ?>/pharmacy/profile.php" class="sidebar-item <?= $currentScript === 'profile.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-store"></i> Pharmacy Profile
        </a>

    <?php elseif ($role === 'admin'): ?>
        <div class="text-uppercase text-muted fw-bold mb-2 ps-2" style="font-size: 0.75rem; letter-spacing: 0.5px;">Master Admin</div>
        <a href="<?= BASE_URL ?>/admin/dashboard.php" class="sidebar-item <?= $currentScript === 'dashboard.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-chart-line"></i> Analytics & Stats
        </a>
        <a href="<?= BASE_URL ?>/admin/users.php" class="sidebar-item <?= $currentScript === 'users.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-users"></i> Patient Accounts
        </a>
        <a href="<?= BASE_URL ?>/admin/hospitals.php" class="sidebar-item <?= $currentScript === 'hospitals.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-hospital"></i> Hospital Approvals
        </a>
        <a href="<?= BASE_URL ?>/admin/pharmacies.php" class="sidebar-item <?= $currentScript === 'pharmacies.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-store"></i> Pharmacy Approvals
        </a>
        <a href="<?= BASE_URL ?>/admin/appointments.php" class="sidebar-item <?= $currentScript === 'appointments.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-calendar-check"></i> System Appointments
        </a>
        <a href="<?= BASE_URL ?>/admin/orders.php" class="sidebar-item <?= $currentScript === 'orders.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-cart-flatbed"></i> Medicine Orders
        </a>
        <a href="<?= BASE_URL ?>/admin/ambulances.php" class="sidebar-item <?= $currentScript === 'ambulances.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-truck-medical"></i> Emergency SOS Logs
        </a>
    <?php endif; ?>

    <div class="mt-4 pt-3 border-top">
        <a href="<?= BASE_URL ?>/logout.php" class="sidebar-item text-danger">
            <i class="fa-solid fa-arrow-right-from-bracket text-danger"></i> Sign Out
        </a>
    </div>
</aside>
