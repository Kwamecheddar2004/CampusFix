<?php
/**
 * CampusFix - Top Navbar Header Partial
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/functions.php';

$user = current_user();
$unreadNotifs = $user ? get_unread_notifications_count($user['id']) : 0;
?>
<header class="app-header">
    <div class="d-flex align-items-center gap-3">
        <button class="btn-sidebar-toggle" aria-label="Toggle Navigation">
            <i class="bi bi-list"></i>
        </button>
        <div class="d-none d-md-block">
            <h5 class="fw-bold mb-0 text-dark"><?= $pageHeading ?? 'Dashboard' ?></h5>
            <small class="text-muted"><?= $pageSubtitle ?? APP_TAGLINE ?></small>
        </div>
    </div>

    <div class="d-flex align-items-center gap-3">
        <?php if ($user && $user['role'] === 'student'): ?>
            <a href="<?= BASE_URL ?>student/report-fault.php" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2 fw-semibold px-3 shadow-sm">
                <i class="bi bi-plus-lg"></i>
                <span class="d-none d-sm-inline">Report Problem</span>
            </a>
        <?php endif; ?>

        <!-- Notification Bell -->
        <?php if ($user): ?>
        <div class="dropdown">
            <button class="btn btn-light position-relative rounded-circle p-2 border" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-bell text-secondary"></i>
                <?php if ($unreadNotifs > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">
                        <?= $unreadNotifs ?>
                    </span>
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width: 280px; padding: 0.5rem 0;">
                <li class="dropdown-header fw-bold text-dark d-flex justify-content-between align-items-center">
                    <span>Notifications</span>
                    <span class="badge bg-primary rounded-pill"><?= $unreadNotifs ?> new</span>
                </li>
                <li><hr class="dropdown-divider my-1"></li>
                <?php if ($unreadNotifs === 0): ?>
                    <li class="px-3 py-3 text-center text-muted small">
                        <i class="bi bi-bell-slash text-secondary d-block fs-4 mb-1"></i>
                        No unread notifications
                    </li>
                <?php else: ?>
                    <li class="px-3 py-2 small text-secondary">
                        You have <?= $unreadNotifs ?> pending alert(s). Check your ticket dashboard for details.
                    </li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- User Profile Dropdown -->
        <div class="dropdown">
            <button class="btn btn-light d-flex align-items-center gap-2 border px-2 py-1 rounded-pill" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; font-size: 0.85rem;">
                    <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                </div>
                <span class="d-none d-md-inline fw-semibold small text-dark"><?= htmlspecialchars(explode(' ', $user['full_name'])[0]) ?></span>
                <i class="bi bi-chevron-down text-muted small"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 py-2" style="min-width: 200px;">
                <li class="px-3 py-2 border-bottom">
                    <div class="fw-bold text-dark small"><?= htmlspecialchars($user['full_name']) ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($user['email']) ?></div>
                    <span class="badge bg-primary-subtle text-primary border border-primary mt-1" style="font-size: 0.7rem; text-transform: uppercase;">
                        <?= htmlspecialchars($user['role']) ?>
                    </span>
                </li>
                <li><a class="dropdown-item py-2 small" href="<?= BASE_URL ?>auth/logout.php"><i class="bi bi-box-arrow-right text-danger me-2"></i> Sign Out</a></li>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</header>
<main class="app-main p-3 p-md-4">
    <!-- Flash Messages -->
    <?php
    $flashes = get_flash_messages();
    if (!empty($flashes)):
        foreach ($flashes as $f):
            $alertType = ($f['type'] === 'error') ? 'danger' : $f['type'];
    ?>
        <div class="alert alert-<?= htmlspecialchars($alertType) ?> alert-dismissible fade show shadow-sm" role="alert">
            <?= htmlspecialchars($f['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php
        endforeach;
    endif;
    ?>

