<?php
/**
 * CampusFix - Technician Work History
 * Displays completed, resolved, and confirmed jobs with student ratings.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('technician');

$user = current_user();
$pageTitle = 'Work History';
$pageHeading = 'Technician Work History';
$pageSubtitle = 'Completed jobs, resolution logs, and student satisfaction ratings';

$completedJobs = [];
$avgRating = 0;
$totalResolved = 0;

try {
    $pdo = get_db_connection();

    // Work history query
    $stmt = $pdo->prepare("
        SELECT t.*, c.name as category_name, c.icon as category_icon,
               l.building_name, l.room_number,
               r.full_name as reporter_name
        FROM tickets t
        JOIN categories c ON t.category_id = c.id
        JOIN campus_locations l ON t.location_id = l.id
        JOIN users r ON t.reporter_id = r.id
        WHERE t.assigned_to = ? AND t.status IN ('resolved', 'closed')
        ORDER BY t.resolved_at DESC
    ");
    $stmt->execute([$user['id']]);
    $completedJobs = $stmt->fetchAll();
    $totalResolved = count($completedJobs);

    // Calculate average rating
    $ratedJobs = array_filter($completedJobs, fn($j) => !empty($j['satisfaction_rating']));
    if (!empty($ratedJobs)) {
        $avgRating = array_sum(array_column($ratedJobs, 'satisfaction_rating')) / count($ratedJobs);
    }

} catch (Exception $e) {
    flash_message('danger', 'Error loading work history: ' . $e->getMessage());
}

include_once INCLUDES_PATH . 'header.php';
include_once INCLUDES_PATH . 'sidebar.php';
?>

<div class="d-flex flex-column flex-1 w-100">
    <?php include_once INCLUDES_PATH . 'navbar.php'; ?>

    <div class="container-fluid px-0">

        <!-- History Summary KPI Banner -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-md-4">
                <div class="kpi-card">
                    <div class="kpi-icon green"><i class="bi bi-patch-check"></i></div>
                    <div class="kpi-content">
                        <h6>Completed Jobs</h6>
                        <div class="kpi-value text-success"><?= $totalResolved ?></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-md-4">
                <div class="kpi-card">
                    <div class="kpi-icon amber"><i class="bi bi-star-fill"></i></div>
                    <div class="kpi-content">
                        <h6>Average Rating</h6>
                        <div class="kpi-value text-warning-emphasis"><?= number_format($avgRating, 1) ?> / 5.0</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-md-4">
                <div class="kpi-card">
                    <div class="kpi-icon blue"><i class="bi bi-person-check"></i></div>
                    <div class="kpi-content">
                        <h6>Technician Profile</h6>
                        <div class="small fw-bold text-dark mt-1"><?= htmlspecialchars($user['full_name']) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- History Table -->
        <div class="cf-card">
            <div class="cf-card-header">
                <div>
                    <h5 class="cf-card-title">Completed Maintenance Ledger</h5>
                    <small class="text-muted">Historical records of fixed issues</small>
                </div>
            </div>

            <?php if (empty($completedJobs)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon text-muted"><i class="bi bi-archive"></i></div>
                    <h5 class="empty-state-title">No Completed Jobs Yet</h5>
                    <p class="empty-state-text">
                        When you mark assigned work orders as Resolved, they will appear here with student ratings and audit records.
                    </p>
                    <a href="<?= BASE_URL ?>technician/assigned-tickets.php" class="btn btn-primary btn-sm fw-semibold">
                        View Current Jobs
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="cf-table">
                        <thead>
                            <tr>
                                <th>Ticket #</th>
                                <th>Category & Title</th>
                                <th>Location</th>
                                <th>Reporter</th>
                                <th>Resolved At</th>
                                <th>Student Rating</th>
                                <th class="text-end">Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($completedJobs as $job): ?>
                                <tr>
                                    <td>
                                        <a href="<?= BASE_URL ?>technician/ticket-details.php?id=<?= $job['id'] ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                                            <?= htmlspecialchars($job['ticket_number']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark text-truncate" style="max-width: 250px;">
                                            <?= htmlspecialchars($job['title']) ?>
                                        </div>
                                        <small class="text-muted"><?= htmlspecialchars($job['category_name']) ?></small>
                                    </td>
                                    <td>
                                        <div class="small fw-medium text-dark"><?= htmlspecialchars($job['building_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($job['room_number'] ?? 'General') ?></small>
                                    </td>
                                    <td class="small text-dark"><?= htmlspecialchars($job['reporter_name']) ?></td>
                                    <td class="small text-muted"><?= date('M j, Y g:i A', strtotime($job['resolved_at'])) ?></td>
                                    <td>
                                        <?php if (!empty($job['satisfaction_rating'])): ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning fw-bold">
                                                <?= $job['satisfaction_rating'] ?> ★
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small fst-italic">Pending Rating</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= BASE_URL ?>technician/ticket-details.php?id=<?= $job['id'] ?>" class="btn btn-light btn-sm border fw-medium px-2 py-1">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php include_once INCLUDES_PATH . 'footer.php'; ?>

