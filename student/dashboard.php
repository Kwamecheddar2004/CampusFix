<?php
/**
 * CampusFix - Student / Reporter Dashboard
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/functions.php';

// Enforce Student Role
require_role('student');

$user = current_user();
$pageTitle = 'Student Portal';
$pageHeading = 'Student Dashboard';
$pageSubtitle = 'Report campus problems, monitor repair progress, and confirm fixes';

// Fetch Live Student Metrics from Database
$totalReports = 0;
$pendingReports = 0;
$inProgressReports = 0;
$resolvedReports = 0;
$recentTickets = [];

try {
    $pdo = get_db_connection();

    // 1. Metrics for current student
    $metricStmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_cnt,
            SUM(CASE WHEN status IN ('assigned', 'in_progress', 'reopened') THEN 1 ELSE 0 END) as in_progress_cnt,
            SUM(CASE WHEN status IN ('resolved', 'closed') THEN 1 ELSE 0 END) as resolved_cnt
        FROM tickets 
        WHERE reporter_id = ?
    ");
    $metricStmt->execute([$user['id']]);
    $metrics = $metricStmt->fetch();

    $totalReports = (int) ($metrics['total'] ?? 0);
    $pendingReports = (int) ($metrics['pending_cnt'] ?? 0);
    $inProgressReports = (int) ($metrics['in_progress_cnt'] ?? 0);
    $resolvedReports = (int) ($metrics['resolved_cnt'] ?? 0);

    // 2. Recent Submitted Tickets
    $ticketStmt = $pdo->prepare("
        SELECT t.*, c.name as category_name, c.icon as category_icon, c.color_code as category_color,
               l.building_name, l.room_number,
               u.full_name as technician_name
        FROM tickets t
        JOIN categories c ON t.category_id = c.id
        JOIN campus_locations l ON t.location_id = l.id
        LEFT JOIN users u ON t.assigned_to = u.id
        WHERE t.reporter_id = ?
        ORDER BY t.created_at DESC
        LIMIT 8
    ");
    $ticketStmt->execute([$user['id']]);
    $recentTickets = $ticketStmt->fetchAll();

} catch (Exception $e) {
    flash_message('danger', 'Unable to query database: ' . $e->getMessage());
}

include_once INCLUDES_PATH . 'header.php';
include_once INCLUDES_PATH . 'sidebar.php';
?>

<div class="d-flex flex-column flex-1 w-100">
    <?php include_once INCLUDES_PATH . 'navbar.php'; ?>

    <div class="container-fluid px-0">

        <!-- Welcome Banner -->
        <div class="p-4 rounded-3 mb-4 text-white shadow-sm" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border: 1px solid rgba(255,255,255,0.05);">
            <div class="row align-items-center g-3">
                <div class="col-lg-8">
                    <span class="badge bg-primary-subtle text-primary border border-primary mb-2">Student Helpdesk Portal</span>
                    <h3 class="fw-bold mb-1">Hello, <?= htmlspecialchars($user['full_name']) ?>!</h3>
                    <p class="text-light opacity-75 mb-0 small">
                        Department: <strong><?= htmlspecialchars($user['department'] ?? 'General Student') ?></strong> | Student Code: <code><?= htmlspecialchars($user['user_code']) ?></code>
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a href="<?= BASE_URL ?>student/report-fault.php" class="btn btn-primary btn-lg fw-semibold shadow-sm px-4">
                        <i class="bi bi-plus-circle me-1"></i> Report New Fault
                    </a>
                </div>
            </div>
        </div>

        <!-- KPI Statistic Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="kpi-card">
                    <div class="kpi-icon blue"><i class="bi bi-ticket-detailed"></i></div>
                    <div class="kpi-content">
                        <h6>Total Reported</h6>
                        <div class="kpi-value"><?= $totalReports ?></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="kpi-card">
                    <div class="kpi-icon amber"><i class="bi bi-hourglass-split"></i></div>
                    <div class="kpi-content">
                        <h6>Pending Triage</h6>
                        <div class="kpi-value"><?= $pendingReports ?></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="kpi-card">
                    <div class="kpi-icon purple"><i class="bi bi-tools"></i></div>
                    <div class="kpi-content">
                        <h6>In Progress</h6>
                        <div class="kpi-value"><?= $inProgressReports ?></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="kpi-card">
                    <div class="kpi-icon green"><i class="bi bi-check2-circle"></i></div>
                    <div class="kpi-content">
                        <h6>Resolved / Fixed</h6>
                        <div class="kpi-value"><?= $resolvedReports ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Submitted Tickets -->
        <div class="cf-card">
            <div class="cf-card-header">
                <div>
                    <h5 class="cf-card-title">My Recent Fault Reports</h5>
                    <small class="text-muted">Track status, assigned technicians and resolution details</small>
                </div>
                <a href="<?= BASE_URL ?>student/my-tickets.php" class="btn btn-outline-secondary btn-sm fw-medium">
                    View All (<?= $totalReports ?>)
                </a>
            </div>

            <?php if (empty($recentTickets)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="bi bi-clipboard2-check"></i></div>
                    <h5 class="empty-state-title">No Maintenance Reports Filed Yet</h5>
                    <p class="empty-state-text">
                        Notice an IT fault or physical damage on campus? Click the button below to alert our technical service teams.
                    </p>
                    <a href="<?= BASE_URL ?>student/report-fault.php" class="btn btn-primary fw-semibold">
                        <i class="bi bi-plus-lg me-1"></i> Report a Problem
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="cf-table">
                        <thead>
                            <tr>
                                <th>Ticket ID</th>
                                <th>Category & Title</th>
                                <th>Campus Location</th>
                                <th>Severity</th>
                                <th>Status</th>
                                <th>Assigned Tech</th>
                                <th>Reported</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentTickets as $ticket): ?>
                                <tr>
                                    <td>
                                        <a href="<?= BASE_URL ?>student/ticket-details.php?id=<?= $ticket['id'] ?>" class="fw-bold text-primary font-monospace text-decoration-none">
                                            <?= htmlspecialchars($ticket['ticket_number']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="p-1 rounded-2 bg-light border text-primary">
                                                <i class="bi <?= htmlspecialchars($ticket['category_icon']) ?>"></i>
                                            </span>
                                            <div>
                                                <div class="fw-semibold text-dark text-truncate" style="max-width: 260px;">
                                                    <?= htmlspecialchars($ticket['title']) ?>
                                                </div>
                                                <small class="text-muted"><?= htmlspecialchars($ticket['category_name']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-dark small fw-medium"><?= htmlspecialchars($ticket['building_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($ticket['room_number'] ?? 'General Area') ?></small>
                                    </td>
                                    <td><?= render_severity_badge($ticket['severity']) ?></td>
                                    <td><?= render_status_badge($ticket['status']) ?></td>
                                    <td>
                                        <?php if (!empty($ticket['technician_name'])): ?>
                                            <span class="small text-dark fw-medium d-inline-flex align-items-center gap-1">
                                                <i class="bi bi-person-circle text-primary"></i> <?= htmlspecialchars($ticket['technician_name']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small fst-italic">Awaiting Assignment</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted small"><?= time_ago($ticket['created_at']) ?></td>
                                    <td class="text-end">
                                        <a href="<?= BASE_URL ?>student/ticket-details.php?id=<?= $ticket['id'] ?>" class="btn btn-light btn-sm border fw-medium px-2 py-1">
                                            <i class="bi bi-eye"></i> Track
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

