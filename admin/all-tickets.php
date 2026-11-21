<?php
/**
 * CampusFix - Administrator Master Triage & Incident Desk
 * Comprehensive listing of all tickets across all campus facilities with filtering and dispatching.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');

$user = current_user();
$pageTitle = 'Master Incident Desk';
$pageHeading = 'Incident Triage & Management Desk';
$pageSubtitle = 'Monitor, triage, assign technicians, and track campus repair SLAs';

$search = trim($_GET['search'] ?? '');
$statusFilter = strtolower(trim($_GET['status'] ?? 'all'));
$priorityFilter = strtolower(trim($_GET['priority'] ?? 'all'));
$categoryFilter = (int) ($_GET['category'] ?? 0);
$buildingFilter = trim($_GET['building'] ?? '');
$techFilter = (int) ($_GET['technician'] ?? 0);

$tickets = [];
$categories = [];
$buildings = [];
$technicians = [];

try {
    $pdo = get_db_connection();

    $categories  = $pdo->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
    $buildings   = $pdo->query("SELECT DISTINCT building_code, building_name FROM campus_locations WHERE is_active = 1 ORDER BY building_name ASC")->fetchAll();
    $technicians = $pdo->query("SELECT id, full_name FROM users WHERE role = 'technician' AND status = 'active' ORDER BY full_name ASC")->fetchAll();

    $sql = "
        SELECT t.*, c.name as category_name, c.icon as category_icon,
               l.building_name, l.room_number,
               r.full_name as reporter_name, r.email as reporter_email,
               tech.full_name as technician_name
        FROM tickets t
        JOIN categories c ON t.category_id = c.id
        JOIN campus_locations l ON t.location_id = l.id
        JOIN users r ON t.reporter_id = r.id
        LEFT JOIN users tech ON t.assigned_to = tech.id
        WHERE 1=1
    ";

    $params = [];

    if ($statusFilter !== 'all' && !empty($statusFilter)) {
        $sql .= " AND t.status = ?";
        $params[] = $statusFilter;
    }

    if ($priorityFilter !== 'all' && !empty($priorityFilter)) {
        $sql .= " AND t.priority = ?";
        $params[] = $priorityFilter;
    }

    if ($categoryFilter > 0) {
        $sql .= " AND t.category_id = ?";
        $params[] = $categoryFilter;
    }

    if (!empty($buildingFilter)) {
        $sql .= " AND l.building_code = ?";
        $params[] = $buildingFilter;
    }

    if ($techFilter > 0) {
        $sql .= " AND t.assigned_to = ?";
        $params[] = $techFilter;
    }

    if (!empty($search)) {
        $sql .= " AND (t.ticket_number LIKE ? OR t.title LIKE ? OR r.full_name LIKE ? OR l.building_name LIKE ?)";
        $term = "%{$search}%";
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $sql .= " ORDER BY 
        CASE WHEN t.status = 'pending' THEN 1
             WHEN t.priority = 'urgent' THEN 2
             WHEN t.severity = 'critical' THEN 3
             WHEN t.status = 'reopened' THEN 4
             ELSE 5 END,
        t.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll();

} catch (Exception $e) {
    flash_message('danger', 'Error querying incident desk: ' . $e->getMessage());
}

include_once INCLUDES_PATH . 'header.php';
include_once INCLUDES_PATH . 'sidebar.php';
?>

<div class="d-flex flex-column flex-1 w-100">
    <?php include_once INCLUDES_PATH . 'navbar.php'; ?>

    <div class="container-fluid px-0">

        <!-- Toolbar & Filter Card -->
        <div class="cf-card mb-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                <div>
                    <h5 class="cf-card-title mb-1">Master Incident Triage Desk</h5>
                    <small class="text-muted">Filtering <?= count($tickets) ?> campus ticket(s)</small>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= BASE_URL ?>admin/analytics.php" class="btn btn-outline-secondary btn-sm fw-semibold">
                        <i class="bi bi-graph-up me-1"></i> SLA & Hotspot Analytics
                    </a>
                </div>
            </div>

            <form method="GET" action="" class="row g-2 align-items-center">
                <div class="col-lg-3 col-md-6">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search ticket #, title, reporter...">
                    </div>
                </div>

                <div class="col-lg-2 col-md-3">
                    <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                        <option value="all" <?= ($statusFilter === 'all') ? 'selected' : '' ?>>All Statuses</option>
                        <option value="pending" <?= ($statusFilter === 'pending') ? 'selected' : '' ?>>Pending Triage</option>
                        <option value="assigned" <?= ($statusFilter === 'assigned') ? 'selected' : '' ?>>Assigned</option>
                        <option value="in_progress" <?= ($statusFilter === 'in_progress') ? 'selected' : '' ?>>In Progress</option>
                        <option value="reopened" <?= ($statusFilter === 'reopened') ? 'selected' : '' ?>>Reopened</option>
                        <option value="resolved" <?= ($statusFilter === 'resolved') ? 'selected' : '' ?>>Resolved</option>
                        <option value="closed" <?= ($statusFilter === 'closed') ? 'selected' : '' ?>>Closed</option>
                    </select>
                </div>

                <div class="col-lg-2 col-md-3">
                    <select class="form-select form-select-sm" name="priority" onchange="this.form.submit()">
                        <option value="all" <?= ($priorityFilter === 'all') ? 'selected' : '' ?>>All Priorities</option>
                        <option value="urgent" <?= ($priorityFilter === 'urgent') ? 'selected' : '' ?>>Urgent SLA</option>
                        <option value="high" <?= ($priorityFilter === 'high') ? 'selected' : '' ?>>High</option>
                        <option value="medium" <?= ($priorityFilter === 'medium') ? 'selected' : '' ?>>Medium</option>
                        <option value="low" <?= ($priorityFilter === 'low') ? 'selected' : '' ?>>Low</option>
                    </select>
                </div>

                <div class="col-lg-2 col-md-4">
                    <select class="form-select form-select-sm" name="building" onchange="this.form.submit()">
                        <option value="">All Buildings</option>
                        <?php foreach ($buildings as $b): ?>
                            <option value="<?= $b['building_code'] ?>" <?= ($buildingFilter === $b['building_code']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['building_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-lg-2 col-md-4">
                    <select class="form-select form-select-sm" name="technician" onchange="this.form.submit()">
                        <option value="0">All Technicians</option>
                        <?php foreach ($technicians as $tech): ?>
                            <option value="<?= $tech['id'] ?>" <?= ($techFilter == $tech['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tech['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-lg-1 col-md-4">
                    <a href="<?= BASE_URL ?>admin/all-tickets.php" class="btn btn-light btn-sm w-100 border text-muted" title="Reset Filters">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </form>
        </div>

        <!-- Master Incident Table -->
        <div class="cf-card">
            <?php if (empty($tickets)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="bi bi-folder-check"></i></div>
                    <h5 class="empty-state-title">No Incident Tickets Match Criteria</h5>
                    <p class="empty-state-text">Adjust your filters or search terms above to inspect other campus records.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="cf-table">
                        <thead>
                            <tr>
                                <th>Ticket #</th>
                                <th>Category & Title</th>
                                <th>Campus Location</th>
                                <th>Reporter</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Assigned Tech</th>
                                <th>Age</th>
                                <th class="text-end">Manage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $t): ?>
                                <tr>
                                    <td>
                                        <a href="<?= BASE_URL ?>admin/ticket-details.php?id=<?= $t['id'] ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                                            <?= htmlspecialchars($t['ticket_number']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="p-1 rounded bg-light border text-primary">
                                                <i class="bi <?= htmlspecialchars($t['category_icon']) ?>"></i>
                                            </span>
                                            <div>
                                                <div class="fw-semibold text-dark text-truncate" style="max-width: 230px;">
                                                    <?= htmlspecialchars($t['title']) ?>
                                                </div>
                                                <small class="text-muted"><?= htmlspecialchars($t['category_name']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small fw-medium text-dark"><?= htmlspecialchars($t['building_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($t['room_number'] ?? 'General') ?></small>
                                    </td>
                                    <td>
                                        <div class="small fw-medium text-dark"><?= htmlspecialchars($t['reporter_name']) ?></div>
                                        <small class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($t['reporter_email']) ?></small>
                                    </td>
                                    <td><?= render_priority_badge($t['priority']) ?></td>
                                    <td><?= render_status_badge($t['status']) ?></td>
                                    <td>
                                        <?php if (!empty($t['technician_name'])): ?>
                                            <span class="small text-dark fw-medium d-inline-flex align-items-center gap-1">
                                                <i class="bi bi-person-check-fill text-primary"></i> <?= htmlspecialchars($t['technician_name']) ?>
                                            </span>
                                        <?php else: ?>
                                            <a href="<?= BASE_URL ?>admin/assign-ticket.php?id=<?= $t['id'] ?>" class="btn btn-warning btn-sm py-0 px-2 fw-semibold" style="font-size: 0.75rem;">
                                                <i class="bi bi-person-plus"></i> Assign Tech
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted"><?= time_ago($t['created_at']) ?></td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?= BASE_URL ?>admin/assign-ticket.php?id=<?= $t['id'] ?>" class="btn btn-light border" title="Assign / Reassign">
                                                <i class="bi bi-person-gear"></i>
                                            </a>
                                            <a href="<?= BASE_URL ?>admin/ticket-details.php?id=<?= $t['id'] ?>" class="btn btn-light border" title="View & Edit">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                        </div>
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

