<?php
/**
 * CampusFix - General Business Logic, Helpers & Badges
 * Provides formatting, audit logging, and data retrieval functions.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth_guard.php';

/**
 * Render visual Bootstrap badge for ticket status
 *
 * @param string $status
 * @return string
 */
function render_status_badge(string $status): string
{
    $statusMap = [
        'pending'     => ['class' => 'bg-warning text-dark', 'icon' => 'bi-clock-history', 'label' => 'Pending'],
        'assigned'    => ['class' => 'bg-info text-dark',    'icon' => 'bi-person-check',  'label' => 'Assigned'],
        'in_progress' => ['class' => 'bg-primary text-white', 'icon' => 'bi-tools',         'label' => 'In Progress'],
        'resolved'    => ['class' => 'bg-success text-white', 'icon' => 'bi-check2-circle', 'label' => 'Resolved'],
        'closed'      => ['class' => 'bg-secondary text-white', 'icon' => 'bi-lock',        'label' => 'Closed'],
        'reopened'    => ['class' => 'bg-danger text-white',  'icon' => 'bi-arrow-clockwise', 'label' => 'Reopened'],
        'cancelled'   => ['class' => 'bg-dark text-white',    'icon' => 'bi-x-circle',      'label' => 'Cancelled']
    ];

    $cfg = $statusMap[strtolower($status)] ?? ['class' => 'bg-secondary text-white', 'icon' => 'bi-question-circle', 'label' => ucfirst($status)];
    return sprintf(
        '<span class="badge %s px-2 py-1 rounded-pill fw-semibold d-inline-flex align-items-center gap-1"><i class="bi %s"></i> %s</span>',
        $cfg['class'],
        $cfg['icon'],
        htmlspecialchars($cfg['label'])
    );
}

/**
 * Render visual badge for severity / impact level
 *
 * @param string $severity
 * @return string
 */
function render_severity_badge(string $severity): string
{
    $map = [
        'low'      => ['class' => 'border border-secondary text-secondary', 'label' => 'Low Impact'],
        'medium'   => ['class' => 'border border-primary text-primary',       'label' => 'Medium Impact'],
        'high'     => ['class' => 'border border-warning text-warning-emphasis bg-warning-subtle', 'label' => 'High Impact'],
        'critical' => ['class' => 'border border-danger text-danger bg-danger-subtle fw-bold',   'label' => 'Critical Hazard']
    ];

    $cfg = $map[strtolower($severity)] ?? ['class' => 'badge bg-secondary', 'label' => ucfirst($severity)];
    return sprintf('<span class="badge %s px-2 py-1 rounded-2">%s</span>', $cfg['class'], htmlspecialchars($cfg['label']));
}

/**
 * Render badge for administrative dispatch priority
 *
 * @param string $priority
 * @return string
 */
function render_priority_badge(string $priority): string
{
    $map = [
        'low'    => ['class' => 'bg-light text-muted border', 'label' => 'Low Priority'],
        'medium' => ['class' => 'bg-info-subtle text-info-emphasis border border-info', 'label' => 'Medium Priority'],
        'high'   => ['class' => 'bg-warning-subtle text-warning-emphasis border border-warning', 'label' => 'High Priority'],
        'urgent' => ['class' => 'bg-danger text-white fw-bold', 'label' => 'URGENT SLA']
    ];

    $cfg = $map[strtolower($priority)] ?? ['class' => 'badge bg-secondary', 'label' => ucfirst($priority)];
    return sprintf('<span class="badge %s px-2 py-1 rounded-2">%s</span>', $cfg['class'], htmlspecialchars($cfg['label']));
}

/**
 * Convert datetime string to human-friendly "time ago"
 *
 * @param string|null $datetime
 * @return string
 */
function time_ago(?string $datetime): string
{
    if (empty($datetime)) {
        return 'Never';
    }

    $timestamp = strtotime($datetime);
    if (!$timestamp) {
        return $datetime;
    }

    $difference = time() - $timestamp;

    if ($difference < 60) {
        return 'Just now';
    } elseif ($difference < 3600) {
        $mins = floor($difference / 60);
        return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($difference < 86400) {
        $hours = floor($difference / 3600);
        return $hours . ' hr' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($difference < 604800) {
        $days = floor($difference / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y \a\t g:i A', $timestamp);
    }
}

/**
 * Generate formatted unique ticket number (CF-YYYY-XXXX)
 *
 * @param PDO $pdo
 * @return string
 */
function generate_ticket_number(PDO $pdo): string
{
    $year = date('Y');
    $prefix = "CF-{$year}-";

    // Query highest ticket index for current year
    $stmt = $pdo->prepare("SELECT ticket_number FROM tickets WHERE ticket_number LIKE :prefix ORDER BY id DESC LIMIT 1");
    $stmt->execute(['prefix' => "{$prefix}%"]);
    $lastTicket = $stmt->fetchColumn();

    if ($lastTicket) {
        $lastNumber = (int) substr($lastTicket, strlen($prefix));
        $nextNumber = $lastNumber + 1;
    } else {
        $nextNumber = 1;
    }

    return sprintf("%s%04d", $prefix, $nextNumber);
}

/**
 * Log activity in activity_logs table for security compliance
 *
 * @param int|null $userId
 * @param string $action
 * @param string $entityType
 * @param int|null $entityId
 * @param array|null $details
 * @return bool
 */
function log_activity(?int $userId, string $action, string $entityType, ?int $entityId = null, ?array $details = null): bool
{
    try {
        $pdo = get_db_connection();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);
        $json = $details ? json_encode($details) : null;

        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, ip_address, user_agent, details, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        return $stmt->execute([$userId, $action, $entityType, $entityId, $ip, $ua, $json]);
    } catch (Exception $e) {
        error_log("Failed to record activity log: " . $e->getMessage());
        return false;
    }
}

/**
 * Retrieve unread notification count for a specific user
 *
 * @param int $userId
 * @return int
 */
function get_unread_notifications_count(int $userId): int
{
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

