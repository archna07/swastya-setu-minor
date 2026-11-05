<?php
/**
 * Swastya Setu - Helper & Utility Functions
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

// Sanitize user inputs
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

// Flash Messages
function setFlash(string $type, string $message): void {
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function getFlash(): array {
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function displayFlash(): string {
    $flashes = getFlash();
    if (empty($flashes)) return '';
    
    $html = '<div class="flash-messages-container" style="margin-bottom: 1.5rem;">';
    foreach ($flashes as $f) {
        $alertClass = match($f['type']) {
            'success' => 'alert-success',
            'danger', 'error' => 'alert-danger',
            'warning' => 'alert-warning',
            default => 'alert-info'
        };
        $icon = match($f['type']) {
            'success' => 'fa-circle-check',
            'danger', 'error' => 'fa-circle-exclamation',
            'warning' => 'fa-triangle-exclamation',
            default => 'fa-circle-info'
        };
        $html .= "<div class='alert {$alertClass} alert-dismissible fade show shadow-sm' role='alert' style='border-radius: 12px;'>
            <i class='fa-solid {$icon} me-2'></i> " . htmlspecialchars($f['message']) . "
            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
        </div>";
    }
    $html .= '</div>';
    return $html;
}

// Push system notification to user
function createNotification(int $userId, string $title, string $message, ?string $link = null): bool {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, link, is_read) VALUES (?, ?, ?, ?, 0)");
        return $stmt->execute([$userId, $title, $message, $link]);
    } catch (Exception $e) {
        return false;
    }
}

// Format Price
function formatPrice($amount): string {
    return '₹' . number_format((float)$amount, 2);
}

// Format Date / Time
function formatDate($dateStr, $includeTime = false): string {
    if (!$dateStr) return 'N/A';
    $time = strtotime($dateStr);
    return $includeTime ? date('d M Y, h:i A', $time) : date('d M Y', $time);
}

// Status Badges
function getStatusBadge(string $status): string {
    $statusKey = strtolower(trim($status));
    $classes = [
        'active'          => 'bg-success text-white',
        'approved'        => 'bg-success text-white',
        'confirmed'       => 'bg-success text-white',
        'delivered'       => 'bg-success text-white',
        'completed'       => 'bg-success text-white',
        'pending'         => 'bg-warning text-dark',
        'under_review'    => 'bg-warning text-dark',
        'uploaded'        => 'bg-info text-white',
        'order_confirmed' => 'bg-primary text-white',
        'preparing'       => 'bg-info text-dark',
        'out_for_delivery'=> 'bg-primary text-white',
        'dispatched'      => 'bg-primary text-white',
        'rejected'        => 'bg-danger text-white',
        'cancelled'       => 'bg-secondary text-white',
        'resolved'        => 'bg-success text-white',
    ];
    $cls = $classes[$statusKey] ?? 'bg-secondary text-white';
    $label = ucwords(str_replace('_', ' ', $status));
    return "<span class='badge {$cls} px-2 py-1 rounded-pill'>{$label}</span>";
}
