<?php
/**
 * Swastya Setu - Patient Notifications Center
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
$user = currentUser();
$db = getDB();

// Mark all as read action
if (isset($_GET['mark_all_read'])) {
    $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$user['id']]);
    setFlash('info', 'All notifications marked as read.');
    header('Location: ' . BASE_URL . '/user/notifications.php');
    exit;
}

$stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 30");
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll();

$pageTitle = 'Notifications & Medical Alerts';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="content-wrapper">
        <div class="container-fluid p-0">
            
            <?= displayFlash() ?>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h2 class="fw-bold mb-1"><i class="fa-solid fa-bell text-primary me-2"></i> Notifications & Medical Alerts</h2>
                    <p class="text-muted small mb-0">Stay updated on your prescriptions, doctor appointments, and medical orders.</p>
                </div>
                <?php if (!empty($notifications)): ?>
                    <a href="notifications.php?mark_all_read=1" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                        <i class="fa-solid fa-check-double me-1"></i> Mark All as Read
                    </a>
                <?php endif; ?>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-0">
                    <?php if (empty($notifications)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fa-solid fa-bell-slash fs-2 opacity-50 mb-2"></i>
                            <p>No notifications at this time.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($notifications as $n): ?>
                                <div class="list-group-item p-3 d-flex align-items-start gap-3 <?= !$n['is_read'] ? 'bg-primary-subtle bg-opacity-10' : '' ?>">
                                    <div class="p-2 rounded-circle bg-primary-subtle text-primary">
                                        <i class="fa-solid fa-circle-info"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <strong class="text-dark small"><?= htmlspecialchars($n['title']) ?></strong>
                                            <small class="text-muted" style="font-size: 0.75rem;"><?= formatDate($n['created_at'], true) ?></small>
                                        </div>
                                        <p class="text-muted small mb-1"><?= htmlspecialchars($n['message']) ?></p>
                                        <?php if (!empty($n['link'])): ?>
                                            <a href="<?= BASE_URL ?>/<?= ltrim($n['link'], '/') ?>" class="small fw-semibold text-primary text-decoration-none">
                                                View Details &rarr;
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
