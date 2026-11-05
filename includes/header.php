<?php
/**
 * Swastya Setu - Master Header Template
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$user = currentUser();
$unreadNotifCount = 0;
if ($user) {
    try {
        $db = getDB();
        $st = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $st->execute([$user['id']]);
        $unreadNotifCount = (int)$st->fetchColumn();
    } catch (Exception $e) {}
}

$pageTitle = $pageTitle ?? 'Smart Healthcare Assistance Platform';

if (!headers_sent()) {
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | <?= APP_NAME ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Leaflet.js CSS for Interactive Maps -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    
    <!-- Swastya Setu Custom Design System -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/styles.css">
    
    <style>
        /* Modern Glass & Custom Overrides */
        :root {
            --primary: #1E88E5;
            --primary-dark: #1565C0;
            --secondary: #42A5F5;
            --accent: #26C6DA;
            --bg-light: #F7FAFC;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light);
            color: #2C3E50;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
        }
        .app-header {
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
            position: sticky;
            top: 0;
            z-index: 1050;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        }
        .brand-logo {
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }
        .brand-logo i {
            font-size: 1.8rem;
            color: var(--primary);
        }
        .brand-logo span span {
            color: var(--accent);
        }
        .nav-link {
            font-weight: 500;
            color: #4A5568;
            padding: 0.5rem 0.9rem !important;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .nav-link:hover, .nav-link.active {
            color: var(--primary) !important;
            background: #E3F2FD;
        }
        .sidebar {
            width: 260px;
            background: #FFFFFF;
            border-right: 1px solid #E2E8F0;
            min-height: calc(100vh - 72px);
            padding: 1.5rem 1rem;
            flex-shrink: 0;
        }
        .sidebar-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            color: #4A5568;
            font-weight: 500;
            text-decoration: none;
            margin-bottom: 4px;
            transition: all 0.2s;
        }
        .sidebar-item i {
            width: 22px;
            text-align: center;
            font-size: 1.1rem;
            color: #718096;
        }
        .sidebar-item:hover {
            background: #F7FAFC;
            color: var(--primary);
        }
        .sidebar-item:hover i {
            color: var(--primary);
        }
        .sidebar-item.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            box-shadow: 0 4px 12px rgba(30, 136, 229, 0.25);
        }
        .sidebar-item.active i {
            color: white;
        }
        .content-wrapper {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
        }
        .floating-sos-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1090;
            width: 62px;
            height: 62px;
            border-radius: 50%;
            background: linear-gradient(135deg, #E53935, #D32F2F);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            box-shadow: 0 8px 24px rgba(229, 57, 53, 0.4);
            border: 3px solid #FFFFFF;
            text-decoration: none;
            transition: all 0.25s;
            animation: pulse-sos 2s infinite;
        }
        .floating-sos-btn:hover {
            transform: scale(1.1);
            color: white;
        }
        @keyframes pulse-sos {
            0% { box-shadow: 0 0 0 0 rgba(229, 57, 53, 0.6); }
            70% { box-shadow: 0 0 0 16px rgba(229, 57, 53, 0); }
            100% { box-shadow: 0 0 0 0 rgba(229, 57, 53, 0); }
        }
        .leaflet-container {
            border-radius: 16px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
            z-index: 1;
        }
    </style>
</head>
<body>

<!-- Header Navigation -->
<header class="app-header py-2 px-3 px-md-4">
    <div class="container-fluid d-flex align-items-center justify-content-between">
        
        <!-- Logo -->
        <a href="<?= BASE_URL ?>/index.php" class="brand-logo">
            <i class="fa-solid fa-heart-pulse"></i>
            <span>Swastya <span>Setu</span></span>
        </a>

        <!-- Middle Quick Navigation Links -->
        <nav class="d-none d-lg-flex align-items-center gap-1">
            <a href="<?= BASE_URL ?>/index.php" class="nav-link">Home</a>
            <a href="<?= BASE_URL ?>/user/prediction.php" class="nav-link">AI Diagnostics</a>
            <a href="<?= BASE_URL ?>/user/hospitals.php" class="nav-link">Hospitals</a>
            <a href="<?= BASE_URL ?>/user/pharmacies.php" class="nav-link">Pharmacies</a>
            <a href="<?= BASE_URL ?>/user/appointments.php" class="nav-link">Doctors</a>
            <a href="<?= BASE_URL ?>/user/sos.php" class="nav-link text-danger fw-bold"><i class="fa-solid fa-truck-medical me-1"></i> Emergency SOS</a>
        </nav>

        <!-- Right User Actions -->
        <div class="d-flex align-items-center gap-2">
            <!-- Emergency SOS trigger button -->
            <a href="<?= BASE_URL ?>/user/sos.php" class="btn btn-danger btn-sm rounded-pill px-3 py-2 fw-semibold d-none d-sm-inline-flex align-items-center gap-2 shadow-sm">
                <i class="fa-solid fa-phone-volume"></i> 108 SOS
            </a>

            <?php if ($user): ?>
                <!-- Notifications -->
                <a href="<?= BASE_URL ?>/user/notifications.php" class="btn btn-light btn-sm rounded-circle p-2 position-relative text-secondary shadow-sm" title="Notifications">
                    <i class="fa-solid fa-bell"></i>
                    <?php if ($unreadNotifCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">
                            <?= $unreadNotifCount ?>
                        </span>
                    <?php endif; ?>
                </a>

                <!-- User Dropdown Menu -->
                <div class="dropdown">
                    <button class="btn btn-light btn-sm rounded-pill px-3 py-1 d-flex align-items-center gap-2 shadow-sm border dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa-solid fa-circle-user text-primary" style="font-size: 1.3rem;"></i>
                        <span class="fw-semibold d-none d-md-inline"><?= htmlspecialchars(explode(' ', $user['name'])[0]) ?></span>
                        <span class="badge bg-primary-subtle text-primary text-uppercase" style="font-size: 0.68rem;"><?= $user['role'] ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-4 mt-2 p-2" style="min-width: 220px;">
                        <li class="px-3 py-2 border-bottom mb-1">
                            <small class="text-muted d-block">Signed in as</small>
                            <span class="fw-bold d-block text-truncate"><?= htmlspecialchars($user['name']) ?></span>
                            <small class="badge bg-info-subtle text-info text-capitalize"><?= $user['role'] ?> Portal</small>
                        </li>
                        
                        <?php if ($user['role'] === 'patient'): ?>
                            <li><a class="dropdown-item rounded-3 py-2" href="<?= BASE_URL ?>/user/dashboard.php"><i class="fa-solid fa-gauge me-2 text-primary"></i> Dashboard</a></li>
                            <li><a class="dropdown-item rounded-3 py-2" href="<?= BASE_URL ?>/user/profile.php"><i class="fa-solid fa-id-card me-2 text-primary"></i> Health Profile</a></li>
                            <li><a class="dropdown-item rounded-3 py-2" href="<?= BASE_URL ?>/user/my_prescriptions.php"><i class="fa-solid fa-file-prescription me-2 text-primary"></i> Prescriptions</a></li>
                            <li><a class="dropdown-item rounded-3 py-2" href="<?= BASE_URL ?>/user/my_orders.php"><i class="fa-solid fa-truck-fast me-2 text-primary"></i> Medicine Orders</a></li>
                        <?php elseif ($user['role'] === 'hospital'): ?>
                            <li><a class="dropdown-item rounded-3 py-2" href="<?= BASE_URL ?>/hospital/dashboard.php"><i class="fa-solid fa-gauge me-2 text-primary"></i> Hospital Panel</a></li>
                            <li><a class="dropdown-item rounded-3 py-2" href="<?= BASE_URL ?>/hospital/manage_doctors.php"><i class="fa-solid fa-user-doctor me-2 text-primary"></i> Manage Doctors</a></li>
                            <li><a class="dropdown-item rounded-3 py-2" href="<?= BASE_URL ?>/hospital/appointments.php"><i class="fa-solid fa-calendar-check me-2 text-primary"></i> Appointments</a></li>
                        <?php elseif ($user['role'] === 'pharmacy'): ?>
                            <li><a class="dropdown-item rounded-3 py-2" href="<?= BASE_URL ?>/pharmacy/dashboard.php"><i class="fa-solid fa-gauge me-2 text-primary"></i> Pharmacy Panel</a></li>
                            <li><a class="dropdown-item rounded-3 py-2" href="<?= BASE_URL ?>/pharmacy/inventory.php"><i class="fa-solid fa-pills me-2 text-primary"></i> Inventory</a></li>
                            <li><a class="dropdown-item rounded-3 py-2" href="<?= BASE_URL ?>/pharmacy/prescriptions.php"><i class="fa-solid fa-file-prescription me-2 text-primary"></i> Rx Orders</a></li>
                        <?php elseif ($user['role'] === 'admin'): ?>
                            <li><a class="dropdown-item rounded-3 py-2" href="<?= BASE_URL ?>/admin/dashboard.php"><i class="fa-solid fa-gauge me-2 text-primary"></i> Admin Center</a></li>
                            <li><a class="dropdown-item rounded-3 py-2" href="<?= BASE_URL ?>/admin/hospitals.php"><i class="fa-solid fa-hospital me-2 text-primary"></i> Hospital Approvals</a></li>
                            <li><a class="dropdown-item rounded-3 py-2" href="<?= BASE_URL ?>/admin/pharmacies.php"><i class="fa-solid fa-store me-2 text-primary"></i> Pharmacy Approvals</a></li>
                        <?php endif; ?>

                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item rounded-3 py-2 text-danger fw-semibold" href="<?= BASE_URL ?>/logout.php"><i class="fa-solid fa-arrow-right-from-bracket me-2"></i> Sign Out</a></li>
                    </ul>
                </div>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/login.php" class="btn btn-outline-primary btn-sm rounded-pill px-3 py-2 fw-semibold">Login</a>
                <a href="<?= BASE_URL ?>/register.php" class="btn btn-primary btn-sm rounded-pill px-3 py-2 fw-semibold shadow-sm">Register</a>
            <?php endif; ?>

        </div>
    </div>
</header>
