<?php
/**
 * CampusFix - Technician Work Order Dashboard
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/functions.php';

// Enforce Technician Role
require_role('technician');

$user = current_user();
$pageTitle = 'Technician Portal';
$pageHeading = 'Field Technician Dashboard';
$pageSubtitle = 'Assigned jobs, diagnostics, repair updates & completion records';

// Live Technician Metrics
$totalAssigned = 0;
$pendingStart = 0;
$inProgress = 0;
$resolvedJobs = 0;
$urgentJobs = 0;
$assignedTickets = [];

try {
    $pdo = get_db_connection();

    // 1. KPI Counts
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as pending_start_cnt,
            SUM(CASE WHEN status IN ('in_progress', 'reopened') THEN 1 ELSE 0 END) as in_progress_cnt,
            SUM(CASE WHEN status IN ('resolved', 'closed') THEN 1 ELSE 0 END) as resolved_cnt,
            SUM(CASE WHEN (priority = 'urgent' OR severity = 'critical') AND status NOT IN ('resolved', 'closed') THEN 1 ELSE 0 END) as urgent_cnt
        FROM tickets 
        WHERE assigned_to = ?
    ");
    $stmt->execute([$user['id']]);
    $kpis = $stmt->fetch();

    $totalAssigned = (int) ($kpis['total'] ?? 0);
    $pendingStart  = (int) ($kpis['pending_start_cnt'] ?? 0);
    $inProgress    = (int) ($kpis['in_progress_cnt'] ?? 0);
    $resolvedJobs  = (int) ($kpis['resolved_cnt'] ?? 0);
    $urgentJobs    = (int) ($kpis['urgent_cnt'] ?? 0);

    // 2. Current Active Assigned Tickets
    $tStmt = $pdo->prepare("
        SELECT t.*, c.name as category_name, c.icon as category_icon,
               l.building_name, l.floor, l.room_number,
               r.full_name as reporter_name, r.phone as reporter_phone
        FROM tickets t
        JOIN categories c ON t.category_id = c.id
        JOIN campus_locations l ON t.location_id = l.id
        JOIN users r ON t.reporter_id = r.id
        WHERE t.assigned_to = ? AND t.status NOT IN ('closed')
        ORDER BY 
            CASE WHEN t.priority = 'urgent' THEN 1
                 WHEN t.severity = 'critical' THEN 2
                 WHEN t.status = 'reopened' THEN 3
                 WHEN t.status = 'in_progress' THEN 4
                 ELSE 5 END,
            t.created_at ASC
        LIMIT 12
    ");
    $tStmt->execute([$user['id']]);
    $assignedTickets = $tStmt->fetchAll();

} catch (Exception $e) {
    flash_message('danger', 'Error loading technician data: ' . $e->getMessage());
}

include_once INCLUDES_PATH . 'header.php';
include_once INCLUDES_PATH . 'sidebar.php';
?>

<div class="d-flex flex-column flex-1 w-100">
    <?php include_once INCLUDES_PATH . 'navbar.php'; ?>

    <div class="container-fluid px-0">

        <!-- Technician Header Banner -->
        <div class="p-3 p-md-4 rounded-3 mb-4 bg-white border shadow-sm">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-primary text-white rounded-3 p-3 fs-3 d-flex align-items-center justify-content-center" style="width: 58px; height: 58px;">
                        <i class="bi bi-tools"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold text-dark mb-1">Technician Workspace: <?= htmlspecialchars($user['full_name']) ?></h4>
                        <div class="d-flex flex-wrap gap-2 align-items-center small text-muted">
                            <span class="badge bg-secondary-subtle text-secondary border"><?= htmlspecialchars($user['user_code']) ?></span>
                            <span>•</span>
                            <span>Unit: <strong><?= htmlspecialchars($user['department'] ?? 'Technical Services') ?></strong></span>
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= BASE_URL ?>technician/work-history.php" class="btn btn-outline-secondary btn-sm fw-semibold">
                        <i class="bi bi-clock-history me-1"></i> Completed History
                    </a>
                </div>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="kpi-card">
                    <div class="kpi-icon blue"><i class="bi bi-briefcase"></i></div>
                    <div class="kpi-content">
                        <h6>Total Work Orders</h6>
                        <div class="kpi-value"><?= $totalAssigned ?></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="kpi-card">
                    <div class="kpi-icon amber"><i class="bi bi-box-arrow-in-right"></i></div>
                    <div class="kpi-content">
                        <h6>Ready to Start</h6>
                        <div class="kpi-value"><?= $pendingStart ?></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="kpi-card">
                    <div class="kpi-icon purple"><i class="bi bi-wrench-adjustable"></i></div>
                    <div class="kpi-content">
                        <h6>Currently In Progress</h6>
                        <div class="kpi-value"><?= $inProgress ?></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="kpi-card">
                    <div class="kpi-icon red"><i class="bi bi-exclamation-triangle"></i></div>
                    <div class="kpi-content">
                        <h6>Urgent Incidents</h6>
                        <div class="kpi-value text-danger"><?= $urgentJobs ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assigned Work Queue -->
        <div class="cf-card">
            <div class="cf-card-header">
                <div>
                    <h5 class="cf-card-title">Assigned Field Jobs Queue</h5>
                    <small class="text-muted">Sorted by urgency and creation timestamp</small>
                </div>
                <a href="<?= BASE_URL ?>technician/assigned-tickets.php" class="btn btn-outline-secondary btn-sm fw-medium">
                    View Full Table
                </a>
            </div>

            <?php if (empty($assignedTickets)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon text-success"><i class="bi bi-check-circle"></i></div>
                    <h5 class="empty-state-title">All Caught Up!</h5>
                    <p class="empty-state-text">
                        You have zero active assigned tickets requiring diagnosis or repair. New assignments from administration will appear here automatically.
                    </p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="cf-table">
                        <thead>
                            <tr>
                                <th>Ticket #</th>
                                <th>Problem Details</th>
                                <th>Building & Room</th>
                                <th>Reporter Contact</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th class="text-end">Work Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assignedTickets as $ticket): ?>
                                <tr>
                                    <td>
                                        <a href="<?= BASE_URL ?>technician/ticket-details.php?id=<?= $ticket['id'] ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                                            <?= htmlspecialchars($ticket['ticket_number']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="p-1 rounded bg-light border text-primary">
                                                <i class="bi <?= htmlspecialchars($ticket['category_icon']) ?>"></i>
                                            </span>
                                            <div>
                                                <div class="fw-semibold text-dark text-truncate" style="max-width: 250px;">
                                                    <?= htmlspecialchars($ticket['title']) ?>
                                                </div>
                                                <small class="text-muted"><?= htmlspecialchars($ticket['category_name']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small fw-medium text-dark"><?= htmlspecialchars($ticket['building_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($ticket['floor']) ?> - <?= htmlspecialchars($ticket['room_number'] ?? 'N/A') ?></small>
                                    </td>
                                    <td>
                                        <div class="small fw-medium text-dark"><?= htmlspecialchars($ticket['reporter_name']) ?></div>
                                        <small class="text-muted font-monospace"><?= htmlspecialchars($ticket['reporter_phone'] ?? 'No phone') ?></small>
                                    </td>
                                    <td><?= render_priority_badge($ticket['priority']) ?></td>
                                    <td><?= render_status_badge($ticket['status']) ?></td>
                                    <td class="text-end">
                                        <a href="<?= BASE_URL ?>technician/ticket-details.php?id=<?= $ticket['id'] ?>" class="btn btn-primary btn-sm fw-medium px-3">
                                            <i class="bi bi-gear-fill me-1"></i> Update
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

