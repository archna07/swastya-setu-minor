<?php
/**
 * Swastya Setu - Multi-Role Authentication Login
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// If already logged in, redirect directly to role dashboard
if (isLoggedIn()) {
    $role = currentRole();
    $dest = match($role) {
        'hospital' => BASE_URL . '/hospital/dashboard.php',
        'pharmacy' => BASE_URL . '/pharmacy/dashboard.php',
        'admin'    => BASE_URL . '/admin/dashboard.php',
        default    => BASE_URL . '/user/dashboard.php'
    };
    header("Location: {$dest}");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    // Basic Brute-Force Rate Limiting
    $failedAttempts = (int)($_SESSION['failed_login_attempts'] ?? 0);
    $lastAttemptTime = (int)($_SESSION['last_failed_login_time'] ?? 0);
    if ($failedAttempts >= 5 && (time() - $lastAttemptTime) < 300) {
        $remaining = 300 - (time() - $lastAttemptTime);
        $error = "Too many failed sign-in attempts. For security, please wait {$remaining} seconds before retrying.";
    } elseif (!verifyCSRFToken($token)) {
        $error = 'Security session expired. Please refresh and try again.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Please enter both your email address and password.';
    } else {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['status'] === 'pending') {
                    $error = 'Your registration is currently under review by the Administrator. Please wait for approval.';
                } elseif ($user['status'] === 'rejected') {
                    $error = 'Your account registration was rejected by the administrator. Contact support.';
                } else {
                    // Prevent session fixation
                    session_regenerate_id(true);
                    unset($_SESSION['failed_login_attempts'], $_SESSION['last_failed_login_time']);

                    // Valid credentials - initialize session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['user'] = [
                        'id' => $user['id'],
                        'name' => $user['name'],
                        'email' => $user['email'],
                        'phone' => $user['phone'],
                        'role' => $user['role'],
                        'status' => $user['status']
                    ];

                    setFlash('success', "Welcome back, {$user['name']}!");

                    $redirect = match($user['role']) {
                        'hospital' => BASE_URL . '/hospital/dashboard.php',
                        'pharmacy' => BASE_URL . '/pharmacy/dashboard.php',
                        'admin'    => BASE_URL . '/admin/dashboard.php',
                        default    => BASE_URL . '/user/dashboard.php'
                    };
                    header("Location: {$redirect}");
                    exit;
                }
            } else {
                $_SESSION['failed_login_attempts'] = $failedAttempts + 1;
                $_SESSION['last_failed_login_time'] = time();
                $error = 'Invalid email or password. Please try again.';
            }
        } catch (Exception $e) {
            error_log("Authentication failure exception: " . $e->getMessage());
            $error = 'An error occurred during authentication. Please try again.';
        }
    }
}

$pageTitle = 'Sign In to Portal';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5 my-auto">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-5">
            
            <?= displayFlash() ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm" role="alert">
                    <i class="fa-solid fa-circle-exclamation me-2"></i> <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="p-4 p-md-5 bg-white">
                    <div class="text-center mb-4">
                        <div class="d-inline-flex p-3 rounded-circle bg-primary-subtle text-primary mb-3">
                            <i class="fa-solid fa-heart-pulse fs-2"></i>
                        </div>
                        <h2 class="fw-bold mb-1">Welcome Back</h2>
                        <p class="text-muted small">Sign in to access your healthcare portal</p>
                    </div>

                    <form method="POST" action="login.php">
                        <?= csrfField() ?>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted rounded-start-3"><i class="fa-solid fa-envelope"></i></span>
                                <input type="email" name="email" id="login-email" class="form-input form-control border-start-0 rounded-end-3 py-2" placeholder="name@swastyasetu.in" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <label class="form-label small fw-semibold text-muted">Password</label>
                                <a href="forgot_password.php" class="small text-decoration-none text-primary">Forgot Password?</a>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted rounded-start-3"><i class="fa-solid fa-lock"></i></span>
                                <input type="password" name="password" id="login-password" class="form-input form-control border-start-0 rounded-end-3 py-2" placeholder="••••••••" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 rounded-3 fw-semibold shadow-sm mt-2">
                            <i class="fa-solid fa-arrow-right-to-bracket me-2"></i> Sign In to Account
                        </button>
                    </form>

                    <!-- Fast One-Click Demo Logins for Evaluation -->
                    <div class="mt-4 pt-3 border-top text-center">
                        <small class="text-muted fw-bold text-uppercase d-block mb-2" style="font-size: 0.72rem; letter-spacing: 0.5px;">Fast Demo Credentials (Click to Fill)</small>
                        <div class="d-flex flex-wrap gap-2 justify-content-center">
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="fillCreds('patient@swastyasetu.in', 'Patient@123')">
                                <i class="fa-solid fa-user me-1 text-primary"></i> Patient
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="fillCreds('apollo@swastyasetu.in', 'Hospital@123')">
                                <i class="fa-solid fa-hospital me-1 text-success"></i> Hospital
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="fillCreds('pharmacy@swastyasetu.in', 'Pharmacy@123')">
                                <i class="fa-solid fa-store me-1 text-info"></i> Pharmacy
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="fillCreds('admin@swastyasetu.in', 'Admin@123')">
                                <i class="fa-solid fa-shield-halved me-1 text-danger"></i> Admin
                            </button>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <span class="small text-muted">Don't have an account yet? </span>
                        <a href="register.php" class="small fw-bold text-primary text-decoration-none">Create Account</a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    function fillCreds(email, pass) {
        document.getElementById('login-email').value = email;
        document.getElementById('login-password').value = pass;
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
