<?php
/**
 * CampusFix - Executive Administration Command Dashboard
 * Calculates live database aggregates, incident categories, recurring problems, and technician loads.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/functions.php';

// Enforce Administrator Role
require_role('admin');

$user = current_user();
$pageTitle = 'Admin Portal';
$pageHeading = 'Administrator Command Center';
$pageSubtitle = 'Master incident overview, technician dispatching, and campus facility analytics';

// Real Database KPI Aggregates
$totalTickets = 0;
$pendingTriage = 0;
$activeRepairs = 0;
$resolvedTickets = 0;
$urgentIncidents = 0;
$recentSubmissions = [];
$technicians = [];

try {
    $pdo = get_db_connection();

    // 1. Master KPI Query
    $kpiStmt = $pdo->query("
        SELECT 
            COUNT(*) as total_cnt,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_cnt,
            SUM(CASE WHEN status IN ('assigned', 'in_progress', 'reopened') THEN 1 ELSE 0 END) as active_cnt,
            SUM(CASE WHEN status IN ('resolved', 'closed') THEN 1 ELSE 0 END) as resolved_cnt,
            SUM(CASE WHEN priority = 'urgent' OR severity = 'critical' THEN 1 ELSE 0 END) as urgent_cnt
        FROM tickets
    ");
    $kpiData = $kpiStmt->fetch();

    $totalTickets    = (int) ($kpiData['total_cnt'] ?? 0);
    $pendingTriage   = (int) ($kpiData['pending_cnt'] ?? 0);
    $activeRepairs   = (int) ($kpiData['active_cnt'] ?? 0);
    $resolvedTickets = (int) ($kpiData['resolved_cnt'] ?? 0);
    $urgentIncidents = (int) ($kpiData['urgent_cnt'] ?? 0);

    // 2. Recent Submissions Across Campus
    $recentStmt = $pdo->query("
        SELECT t.*, c.name as category_name, c.icon as category_icon,
               l.building_name, l.room_number,
               r.full_name as reporter_name,
               tech.full_name as technician_name
        FROM tickets t
        JOIN categories c ON t.category_id = c.id
        JOIN campus_locations l ON t.location_id = l.id
        JOIN users r ON t.reporter_id = r.id
        LEFT JOIN users tech ON t.assigned_to = tech.id
        ORDER BY t.created_at DESC
        LIMIT 10
    ");
    $recentSubmissions = $recentStmt->fetchAll();

    // 3. Technician Workload Roster
    $techStmt = $pdo->query("
        SELECT u.id, u.full_name, u.department, u.email,
               COUNT(t.id) as total_assigned,
               SUM(CASE WHEN t.status IN ('assigned', 'in_progress', 'reopened') THEN 1 ELSE 0 END) as active_jobs
        FROM users u
        LEFT JOIN tickets t ON u.id = t.assigned_to
        WHERE u.role = 'technician' AND u.status = 'active'
        GROUP BY u.id, u.full_name, u.department, u.email
        ORDER BY active_jobs DESC
    ");
    $technicians = $techStmt->fetchAll();

} catch (Exception $e) {
    flash_message('danger', 'Error loading administrative metrics: ' . $e->getMessage());
}

include_once INCLUDES_PATH . 'header.php';
include_once INCLUDES_PATH . 'sidebar.php';
?>

<div class="d-flex flex-column flex-1 w-100">
    <?php include_once INCLUDES_PATH . 'navbar.php'; ?>

    <div class="container-fluid px-0">

        <!-- Admin Quick Actions Bar -->
        <div class="p-3 p-md-4 rounded-3 mb-4 bg-white border shadow-sm">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <h4 class="fw-bold text-dark mb-1">Administrative Overview</h4>
                    <p class="text-muted small mb-0">System Status: <span class="badge bg-success-subtle text-success border border-success"><i class="bi bi-circle-fill" style="font-size: 0.5rem;"></i> Operational</span></p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= BASE_URL ?>admin/all-tickets.php" class="btn btn-primary fw-semibold btn-sm shadow-sm">
                        <i class="bi bi-kanban me-1"></i> Triage Queue (<?= $pendingTriage ?>)
                    </a>
                    <a href="<?= BASE_URL ?>admin/analytics.php" class="btn btn-outline-secondary btn-sm fw-semibold">
                        <i class="bi bi-graph-up me-1"></i> Visual Analytics
                    </a>
                    <a href="<?= BASE_URL ?>admin/users.php" class="btn btn-outline-secondary btn-sm fw-semibold">
                        <i class="bi bi-people me-1"></i> Users
                    </a>
                </div>
            </div>
        </div>

        <!-- KPI Statistic Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl">
                <div class="kpi-card">
                    <div class="kpi-icon blue"><i class="bi bi-files"></i></div>
                    <div class="kpi-content">
                        <h6>Total Reports</h6>
                        <div class="kpi-value"><?= $totalTickets ?></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl">
                <div class="kpi-card">
                    <div class="kpi-icon amber"><i class="bi bi-hourglass-top"></i></div>
                    <div class="kpi-content">
                        <h6>Pending Triage</h6>
                        <div class="kpi-value text-warning-emphasis"><?= $pendingTriage ?></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl">
                <div class="kpi-card">
                    <div class="kpi-icon purple"><i class="bi bi-tools"></i></div>
                    <div class="kpi-content">
                        <h6>Active Repairs</h6>
                        <div class="kpi-value"><?= $activeRepairs ?></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl">
                <div class="kpi-card">
                    <div class="kpi-icon green"><i class="bi bi-check2-all"></i></div>
                    <div class="kpi-content">
                        <h6>Resolved</h6>
                        <div class="kpi-value text-success"><?= $resolvedTickets ?></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl">
                <div class="kpi-card">
                    <div class="kpi-icon red"><i class="bi bi-bell-fill"></i></div>
                    <div class="kpi-content">
                        <h6>Urgent Incidents</h6>
                        <div class="kpi-value text-danger"><?= $urgentIncidents ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <!-- Recent Submissions Table -->
            <div class="col-xl-8">
                <div class="cf-card mb-0 h-100">
                    <div class="cf-card-header">
                        <div>
                            <h5 class="cf-card-title">Recent Fault Submissions</h5>
                            <small class="text-muted">Master campus incident ledger</small>
                        </div>
                        <a href="<?= BASE_URL ?>admin/all-tickets.php" class="btn btn-outline-secondary btn-sm fw-medium">View All</a>
                    </div>

                    <?php if (empty($recentSubmissions)): ?>
                        <div class="empty-state py-4">
                            <i class="bi bi-folder2-open empty-state-icon"></i>
                            <h6 class="empty-state-title">No Incident Reports in Database</h6>
                            <p class="empty-state-text">Import <code>database/campusfix.sql</code> to load demonstration incidents.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="cf-table">
                                <thead>
                                    <tr>
                                        <th>Ticket #</th>
                                        <th>Category & Title</th>
                                        <th>Location</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Assigned To</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentSubmissions as $row): ?>
                                        <tr>
                                            <td>
                                                <a href="<?= BASE_URL ?>admin/ticket-details.php?id=<?= $row['id'] ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                                                    <?= htmlspecialchars($row['ticket_number']) ?>
                                                </a>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark text-truncate" style="max-width: 200px;">
                                                    <?= htmlspecialchars($row['title']) ?>
                                                </div>
                                                <small class="text-muted"><?= htmlspecialchars($row['category_name']) ?></small>
                                            </td>
                                            <td>
                                                <div class="small fw-medium text-dark"><?= htmlspecialchars($row['building_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($row['room_number'] ?? 'General') ?></small>
                                            </td>
                                            <td><?= render_priority_badge($row['priority']) ?></td>
                                            <td><?= render_status_badge($row['status']) ?></td>
                                            <td>
                                                <?php if ($row['technician_name']): ?>
                                                    <span class="small fw-medium text-dark"><?= htmlspecialchars($row['technician_name']) ?></span>
                                                <?php else: ?>
                                                    <a href="<?= BASE_URL ?>admin/assign-ticket.php?id=<?= $row['id'] ?>" class="badge bg-warning-subtle text-warning-emphasis border border-warning text-decoration-none">
                                                        <i class="bi bi-person-plus"></i> Assign
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <a href="<?= BASE_URL ?>admin/ticket-details.php?id=<?= $row['id'] ?>" class="btn btn-light btn-sm border px-2 py-1">
                                                    <i class="bi bi-pencil-square"></i>
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

            <!-- Technician Workload Roster -->
            <div class="col-xl-4">
                <div class="cf-card mb-0 h-100">
                    <div class="cf-card-header">
                        <div>
                            <h5 class="cf-card-title">Technician Active Workload</h5>
                            <small class="text-muted">Current job allocation</small>
                        </div>
                        <a href="<?= BASE_URL ?>admin/users.php?role=technician" class="btn btn-outline-secondary btn-sm fw-medium">Manage</a>
                    </div>

                    <?php if (empty($technicians)): ?>
                        <p class="text-muted small">No active technicians found.</p>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($technicians as $t): ?>
                                <div class="p-3 rounded-3 border bg-light d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 40px; height: 40px;">
                                            <?= strtoupper(substr($t['full_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold text-dark small"><?= htmlspecialchars($t['full_name']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($t['department'] ?? 'Technical Artisan') ?></small>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge <?= ($t['active_jobs'] > 3) ? 'bg-danger' : 'bg-primary' ?> rounded-pill px-2 py-1">
                                            <?= $t['active_jobs'] ?> Active
                                        </span>
                                        <div class="text-muted" style="font-size: 0.7rem;"><?= $t['total_assigned'] ?> Total</div>
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

<?php include_once INCLUDES_PATH . 'footer.php'; ?>

