<?php
/**
 * CampusFix - Role-Aware Dynamic Sidebar Partial
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/auth_guard.php';

$user = current_user();
$role = $user['role'] ?? 'student';
$currentScript = basename($_SERVER['PHP_SELF']);
?>
<aside class="app-sidebar">
    <!-- Brand Header -->
    <a href="<?= BASE_URL ?>" class="sidebar-brand">
        <span class="brand-icon"><i class="bi bi-tools"></i></span>
        <span>CampusFix</span>
    </a>

    <!-- Navigation Menu -->
    <ul class="sidebar-menu">
        <li class="menu-header">Overview</li>
        
        <?php if ($role === 'student'): ?>
            <li class="menu-item">
                <a href="<?= BASE_URL ?>student/dashboard.php" class="menu-link <?= ($currentScript === 'dashboard.php') ? 'active' : '' ?>">
                    <i class="bi bi-grid-1x2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="<?= BASE_URL ?>student/report-fault.php" class="menu-link <?= ($currentScript === 'report-fault.php') ? 'active' : '' ?>">
                    <i class="bi bi-plus-circle"></i>
                    <span>Report Fault</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="<?= BASE_URL ?>student/my-tickets.php" class="menu-link <?= ($currentScript === 'my-tickets.php' || $currentScript === 'ticket-details.php') ? 'active' : '' ?>">
                    <i class="bi bi-ticket-perforated"></i>
                    <span>My Reports</span>
                </a>
            </li>

        <?php elseif ($role === 'technician'): ?>
            <li class="menu-item">
                <a href="<?= BASE_URL ?>technician/dashboard.php" class="menu-link <?= ($currentScript === 'dashboard.php') ? 'active' : '' ?>">
                    <i class="bi bi-grid-1x2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="<?= BASE_URL ?>technician/assigned-tickets.php" class="menu-link <?= ($currentScript === 'assigned-tickets.php' || $currentScript === 'ticket-details.php') ? 'active' : '' ?>">
                    <i class="bi bi-briefcase"></i>
                    <span>Assigned Jobs</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="<?= BASE_URL ?>technician/work-history.php" class="menu-link <?= ($currentScript === 'work-history.php') ? 'active' : '' ?>">
                    <i class="bi bi-clock-history"></i>
                    <span>Work History</span>
                </a>
            </li>

        <?php elseif ($role === 'admin'): ?>
            <li class="menu-item">
                <a href="<?= BASE_URL ?>admin/dashboard.php" class="menu-link <?= ($currentScript === 'dashboard.php') ? 'active' : '' ?>">
                    <i class="bi bi-grid-1x2"></i>
                    <span>Admin Dashboard</span>
                </a>
            </li>
            <li class="menu-header">Incident Management</li>
            <li class="menu-item">
                <a href="<?= BASE_URL ?>admin/all-tickets.php" class="menu-link <?= ($currentScript === 'all-tickets.php' || $currentScript === 'ticket-details.php' || $currentScript === 'assign-ticket.php') ? 'active' : '' ?>">
                    <i class="bi bi-kanban"></i>
                    <span>Master Triage Desk</span>
                </a>
            </li>
            <li class="menu-header">Administration</li>
            <li class="menu-item">
                <a href="<?= BASE_URL ?>admin/users.php" class="menu-link <?= ($currentScript === 'users.php') ? 'active' : '' ?>">
                    <i class="bi bi-people"></i>
                    <span>User Management</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="<?= BASE_URL ?>admin/categories.php" class="menu-link <?= ($currentScript === 'categories.php') ? 'active' : '' ?>">
                    <i class="bi bi-tags"></i>
                    <span>Fault Categories</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="<?= BASE_URL ?>admin/locations.php" class="menu-link <?= ($currentScript === 'locations.php') ? 'active' : '' ?>">
                    <i class="bi bi-geo-alt"></i>
                    <span>Campus Locations</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="<?= BASE_URL ?>admin/analytics.php" class="menu-link <?= ($currentScript === 'analytics.php') ? 'active' : '' ?>">
                    <i class="bi bi-graph-up-arrow"></i>
                    <span>Analytics & Reports</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="<?= BASE_URL ?>admin/notification-settings.php" class="menu-link <?= ($currentScript === 'notification-settings.php') ? 'active' : '' ?>">
                    <i class="bi bi-sliders"></i>
                    <span>Notification Settings</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="<?= BASE_URL ?>admin/activity-logs.php" class="menu-link <?= ($currentScript === 'activity-logs.php') ? 'active' : '' ?>">
                    <i class="bi bi-shield-check"></i>
                    <span>Activity Audit Logs</span>
                </a>
            </li>
        <?php endif; ?>

        <li class="menu-header">Account</li>
        <li class="menu-item">
            <a href="<?= BASE_URL ?>auth/logout.php" class="menu-link text-danger-emphasis">
                <i class="bi bi-box-arrow-right text-danger"></i>
                <span>Sign Out</span>
            </a>
        </li>
    </ul>

    <!-- Sidebar User Footer -->
    <?php if ($user): ?>
    <div class="sidebar-footer">
        <div class="user-avatar">
            <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
        </div>
        <div class="overflow-hidden flex-1">
            <div class="fw-semibold text-white text-truncate small"><?= htmlspecialchars($user['full_name']) ?></div>
            <div class="text-secondary text-truncate" style="font-size: 0.75rem; text-transform: capitalize;">
                <span class="badge bg-secondary-subtle text-light px-1"><?= htmlspecialchars($user['role']) ?></span>
            </div>
        </div>
    </div>
    <?php endif; ?>
</aside>

