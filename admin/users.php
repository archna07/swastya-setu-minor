<?php
/**
 * Swastya Setu - Administrator User / Patient Management
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');
$db = getDB();

$users = $db->query("
    SELECT u.*, p.blood_group, p.age, p.gender, p.city, p.emergency_contact_phone
    FROM users u
    LEFT JOIN user_profiles p ON u.id = p.user_id
    WHERE u.role = 'patient'
    ORDER BY u.id DESC
")->fetchAll();

$pageTitle = 'Registered Patient Accounts';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="content-wrapper">
        <div class="container-fluid p-0">
            
            <?= displayFlash() ?>

            <div class="mb-4">
                <h2 class="fw-bold mb-1"><i class="fa-solid fa-users text-primary me-2"></i> Patient Accounts & Medical Passports</h2>
                <p class="text-muted small mb-0">Oversee registered patient profiles, emergency contact details, and account status.</p>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-muted">
                                <tr>
                                    <th class="ps-4">User ID & Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Blood Group</th>
                                    <th>City</th>
                                    <th>Emergency Contact</th>
                                    <th>Joined</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold text-dark">#USR-<?= $u['id'] ?> <?= htmlspecialchars($u['name']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($u['gender'] ?? 'N/A') ?>, <?= $u['age'] ?? 'N/A' ?> Yrs</small>
                                        </td>
                                        <td><a href="mailto:<?= htmlspecialchars($u['email']) ?>" class="small text-decoration-none"><?= htmlspecialchars($u['email']) ?></a></td>
                                        <td class="small"><?= htmlspecialchars($u['phone']) ?></td>
                                        <td><span class="badge bg-danger rounded-pill px-2 py-1"><?= htmlspecialchars($u['blood_group'] ?? 'O+') ?></span></td>
                                        <td class="small"><?= htmlspecialchars($u['city'] ?? 'New Delhi') ?></td>
                                        <td class="small text-muted"><?= htmlspecialchars($u['emergency_contact_phone'] ?? 'N/A') ?></td>
                                        <td class="small text-muted"><?= formatDate($u['created_at']) ?></td>
                                        <td><?= getStatusBadge($u['status']) ?></td>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
