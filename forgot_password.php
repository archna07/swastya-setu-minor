<?php
/**
 * Swastya Setu - Secure Password Recovery
 * Requires registered email and verified phone number match to prevent unauthorized account takeover.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$message = '';
$msgType = 'danger';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($token)) {
        $message = 'Security session expired. Please refresh the page and try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (empty($email) || empty($phone) || empty($newPass)) {
            $message = 'Please provide your registered email, contact phone, and new password.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
        } elseif (strlen($newPass) < 8 || !preg_match('/[A-Za-z]/', $newPass) || !preg_match('/[0-9]/', $newPass)) {
            $message = 'Password must be at least 8 characters long and contain both letters and numbers.';
        } elseif ($newPass !== $confirmPass) {
            $message = 'New password and confirmation password do not match.';
        } else {
            try {
                $db = getDB();
                // Normalize phone search (compare last 10 digits to accommodate country code variations)
                $cleanPhoneInput = preg_replace('/[^0-9]/', '', $phone);
                $last10 = substr($cleanPhoneInput, -10);

                $stmt = $db->prepare("SELECT id, phone FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                $verified = false;
                if ($user) {
                    $userPhoneClean = preg_replace('/[^0-9]/', '', $user['phone']);
                    if (substr($userPhoneClean, -10) === $last10) {
                        $verified = true;
                    }
                }

                if ($verified) {
                    $hash = password_hash($newPass, PASSWORD_BCRYPT);
                    $upd = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $upd->execute([$hash, $user['id']]);

                    createNotification(
                        $user['id'],
                        'Security Alert: Password Changed',
                        'Your Swastya Setu account password was reset successfully.',
                        'login.php'
                    );

                    setFlash('success', 'Your password has been successfully reset! You can now log in.');
                    header('Location: ' . BASE_URL . '/login.php');
                    exit;
                } else {
                    // Prevent user enumeration: Show uniform message
                    $message = 'If the provided email and phone number match an active account, your password has been updated. Please verify your details.';
                }
            } catch (Exception $e) {
                error_log("Password reset exception: " . $e->getMessage());
                $message = 'An error occurred during password recovery. Please contact administrator support.';
            }
        }
    }
}

$pageTitle = 'Secure Password Recovery';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <div class="card border-0 shadow-lg rounded-4 p-4 p-md-5 bg-white">
                <div class="text-center mb-4">
                    <div class="d-inline-flex p-3 rounded-circle bg-warning-subtle text-warning mb-3">
                        <i class="fa-solid fa-shield-halved fs-2"></i>
                    </div>
                    <h2 class="fw-bold mb-1">Reset Password</h2>
                    <p class="text-muted small">Verify your registered account details to establish a new secure password</p>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?= $msgType ?> rounded-3 small">
                        <i class="fa-solid fa-circle-exclamation me-1"></i> <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="forgot_password.php">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Registered Email Address</label>
                        <input type="email" name="email" class="form-control rounded-3" placeholder="name@domain.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Registered Phone Number</label>
                        <input type="tel" name="phone" class="form-control rounded-3" placeholder="+91 9876543210" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">New Password (Min 8 chars, letters & numbers)</label>
                        <input type="password" name="new_password" class="form-control rounded-3" placeholder="••••••••" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-muted">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control rounded-3" placeholder="••••••••" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 rounded-3 fw-semibold shadow-sm">
                        <i class="fa-solid fa-key me-2"></i> Verify & Reset Password
                    </button>
                </form>

                <div class="text-center mt-4">
                    <a href="login.php" class="small fw-semibold text-decoration-none">&larr; Back to Login</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
