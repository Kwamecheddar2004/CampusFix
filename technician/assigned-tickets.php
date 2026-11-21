<?php
/**
 * CampusFix - Technician Assigned Tickets List
 * Filterable work order desk for field technicians.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('technician');

$user = current_user();
$pageTitle = 'Assigned Work Orders';
$pageHeading = 'Assigned Field Jobs';
$pageSubtitle = 'Inspect fault details, update repair statuses, and document solutions';

$search = trim($_GET['search'] ?? '');
$statusFilter = strtolower(trim($_GET['status'] ?? 'active'));
$priorityFilter = strtolower(trim($_GET['priority'] ?? 'all'));
$categoryFilter = (int) ($_GET['category'] ?? 0);

$tickets = [];
$categories = [];

try {
    $pdo = get_db_connection();
    $categories = $pdo->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

    $sql = "
        SELECT t.*, c.name as category_name, c.icon as category_icon,
               l.building_name, l.floor, l.room_number,
               r.full_name as reporter_name, r.phone as reporter_phone, r.email as reporter_email
        FROM tickets t
        JOIN categories c ON t.category_id = c.id
        JOIN campus_locations l ON t.location_id = l.id
        JOIN users r ON t.reporter_id = r.id
        WHERE t.assigned_to = ?
    ";

    $params = [$user['id']];

    if ($statusFilter === 'active') {
        $sql .= " AND t.status IN ('assigned', 'in_progress', 'reopened')";
    } elseif ($statusFilter !== 'all' && !empty($statusFilter)) {
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

    if (!empty($search)) {
        $sql .= " AND (t.ticket_number LIKE ? OR t.title LIKE ? OR l.building_name LIKE ? OR r.full_name LIKE ?)";
        $term = "%{$search}%";
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $sql .= " ORDER BY 
        CASE WHEN t.priority = 'urgent' THEN 1
             WHEN t.severity = 'critical' THEN 2
             WHEN t.status = 'reopened' THEN 3
             WHEN t.status = 'in_progress' THEN 4
             ELSE 5 END,
        t.created_at ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll();

} catch (Exception $e) {
    flash_message('danger', 'Error querying assigned work orders: ' . $e->getMessage());
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
                    <h5 class="cf-card-title mb-1">Work Order Dispatch Queue</h5>
                    <small class="text-muted">Showing <?= count($tickets) ?> assigned ticket(s)</small>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= BASE_URL ?>technician/work-history.php" class="btn btn-outline-secondary btn-sm fw-medium">
                        <i class="bi bi-clock-history me-1"></i> Completed History
                    </a>
                </div>
            </div>

            <form method="GET" action="" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search ticket #, title, building, or student...">
                    </div>
                </div>

                <div class="col-md-3">
                    <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                        <option value="active" <?= ($statusFilter === 'active') ? 'selected' : '' ?>>Active Jobs (Assigned/In Progress)</option>
                        <option value="all" <?= ($statusFilter === 'all') ? 'selected' : '' ?>>All Tickets</option>
                        <option value="assigned" <?= ($statusFilter === 'assigned') ? 'selected' : '' ?>>New / Ready to Start</option>
                        <option value="in_progress" <?= ($statusFilter === 'in_progress') ? 'selected' : '' ?>>Currently In Progress</option>
                        <option value="reopened" <?= ($statusFilter === 'reopened') ? 'selected' : '' ?>>Reopened</option>
                        <option value="resolved" <?= ($statusFilter === 'resolved') ? 'selected' : '' ?>>Resolved</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <select class="form-select form-select-sm" name="priority" onchange="this.form.submit()">
                        <option value="all" <?= ($priorityFilter === 'all') ? 'selected' : '' ?>>All Priorities</option>
                        <option value="urgent" <?= ($priorityFilter === 'urgent') ? 'selected' : '' ?>>Urgent SLA</option>
                        <option value="high" <?= ($priorityFilter === 'high') ? 'selected' : '' ?>>High</option>
                        <option value="medium" <?= ($priorityFilter === 'medium') ? 'selected' : '' ?>>Medium</option>
                        <option value="low" <?= ($priorityFilter === 'low') ? 'selected' : '' ?>>Low</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <select class="form-select form-select-sm" name="category" onchange="this.form.submit()">
                        <option value="0">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($categoryFilter == $cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-1">
                    <a href="<?= BASE_URL ?>technician/assigned-tickets.php" class="btn btn-light btn-sm w-100 border text-muted" title="Reset Filters">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </form>
        </div>

        <!-- Assigned Tickets Table -->
        <div class="cf-card">
            <?php if (empty($tickets)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon text-success"><i class="bi bi-check-circle"></i></div>
                    <h5 class="empty-state-title">No Assigned Tickets Found</h5>
                    <p class="empty-state-text">
                        There are currently no active work orders matching your filters. When new incidents are dispatched to you by administrators, they will appear here.
                    </p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="cf-table">
                        <thead>
                            <tr>
                                <th>Ticket #</th>
                                <th>Problem Details</th>
                                <th>Campus Location</th>
                                <th>Reporter Contact</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th class="text-end">Manage Job</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $t): ?>
                                <tr>
                                    <td>
                                        <a href="<?= BASE_URL ?>technician/ticket-details.php?id=<?= $t['id'] ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                                            <?= htmlspecialchars($t['ticket_number']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="p-1 rounded bg-light border text-primary">
                                                <i class="bi <?= htmlspecialchars($t['category_icon']) ?>"></i>
                                            </span>
                                            <div>
                                                <div class="fw-semibold text-dark text-truncate" style="max-width: 250px;">
                                                    <?= htmlspecialchars($t['title']) ?>
                                                </div>
                                                <small class="text-muted"><?= htmlspecialchars($t['category_name']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small fw-medium text-dark"><?= htmlspecialchars($t['building_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($t['floor']) ?> — <?= htmlspecialchars($t['room_number'] ?? 'N/A') ?></small>
                                    </td>
                                    <td>
                                        <div class="small fw-medium text-dark"><?= htmlspecialchars($t['reporter_name']) ?></div>
                                        <small class="text-primary font-monospace"><?= htmlspecialchars($t['reporter_phone'] ?? $t['reporter_email']) ?></small>
                                    </td>
                                    <td><?= render_priority_badge($t['priority']) ?></td>
                                    <td><?= render_status_badge($t['status']) ?></td>
                                    <td class="text-end">
                                        <a href="<?= BASE_URL ?>technician/ticket-details.php?id=<?= $t['id'] ?>" class="btn btn-primary btn-sm fw-semibold px-3">
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

