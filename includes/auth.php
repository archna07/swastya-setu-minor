<?php
/**
 * Swastya Setu - Authentication & Authorization Guards
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

// Check if user is logged in
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Get logged-in user array
function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    
    if (isset($_SESSION['user'])) {
        return $_SESSION['user'];
    }

    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, name, email, phone, role, status, created_at FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION['user'] = $user;
            return $user;
        }
    } catch (Exception $e) {
        return null;
    }
    return null;
}

// Get user role
function currentRole(): ?string {
    return $_SESSION['role'] ?? null;
}

// Require login guard
function requireLogin(): void {
    if (!isLoggedIn()) {
        setFlash('warning', 'Please sign in to access this page.');
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

// Require specific role or array of roles
function requireRole($roles): void {
    requireLogin();
    $roles = is_array($roles) ? $roles : [$roles];
    $userRole = currentRole();

    if (!in_array($userRole, $roles)) {
        setFlash('danger', 'Access denied. You do not have permission to view that resource.');
        
        // Redirect to user's appropriate dashboard
        $redirectUrl = match($userRole) {
            'hospital' => BASE_URL . '/hospital/dashboard.php',
            'pharmacy' => BASE_URL . '/pharmacy/dashboard.php',
            'admin'    => BASE_URL . '/admin/dashboard.php',
            default    => BASE_URL . '/user/dashboard.php'
        };
        header('Location: ' . $redirectUrl);
        exit;
    }
}

// CSRF Protection
function generateCSRFToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField(): string {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}
