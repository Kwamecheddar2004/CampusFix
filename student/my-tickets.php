<?php
/**
 * CampusFix - Student My Reports List View
 * Searchable, filterable ledger of all incidents reported by the logged-in student.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/functions.php';

// Enforce Student Role
require_role('student');

$user = current_user();
$pageTitle = 'My Fault Reports';
$pageHeading = 'My Incident Reports';
$pageSubtitle = 'Review report history, status progressions, and technician notes';

// Filters & Search
$search = trim($_GET['search'] ?? '');
$statusFilter = strtolower(trim($_GET['status'] ?? 'all'));
$categoryFilter = (int) ($_GET['category'] ?? 0);

$tickets = [];
$categories = [];

try {
    $pdo = get_db_connection();
    $categories = $pdo->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

    $sql = "
        SELECT t.*, c.name as category_name, c.icon as category_icon,
               l.building_name, l.room_number,
               tech.full_name as technician_name
        FROM tickets t
        JOIN categories c ON t.category_id = c.id
        JOIN campus_locations l ON t.location_id = l.id
        LEFT JOIN users tech ON t.assigned_to = tech.id
        WHERE t.reporter_id = ?
    ";

    $params = [$user['id']];

    if (!empty($search)) {
        $sql .= " AND (t.ticket_number LIKE ? OR t.title LIKE ? OR t.description LIKE ?)";
        $term = "%{$search}%";
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    if ($statusFilter !== 'all' && !empty($statusFilter)) {
        $sql .= " AND t.status = ?";
        $params[] = $statusFilter;
    }

    if ($categoryFilter > 0) {
        $sql .= " AND t.category_id = ?";
        $params[] = $categoryFilter;
    }

    $sql .= " ORDER BY t.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll();

} catch (Exception $e) {
    flash_message('danger', 'Error querying ticket history: ' . $e->getMessage());
}

include_once INCLUDES_PATH . 'header.php';
include_once INCLUDES_PATH . 'sidebar.php';
?>

<div class="d-flex flex-column flex-1 w-100">
    <?php include_once INCLUDES_PATH . 'navbar.php'; ?>

    <div class="container-fluid px-0">

        <!-- Header Actions -->
        <div class="cf-card mb-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                <div>
                    <h5 class="cf-card-title mb-1">Fault Reports Log</h5>
                    <small class="text-muted">Viewing <?= count($tickets) ?> incident(s)</small>
                </div>
                <a href="<?= BASE_URL ?>student/report-fault.php" class="btn btn-primary btn-sm fw-semibold shadow-sm px-3">
                    <i class="bi bi-plus-lg me-1"></i> File New Report
                </a>
            </div>

            <!-- Filter & Search Toolbar -->
            <form method="GET" action="" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search ticket #, title, or keywords...">
                    </div>
                </div>

                <div class="col-md-3">
                    <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                        <option value="all" <?= ($statusFilter === 'all') ? 'selected' : '' ?>>All Statuses</option>
                        <option value="pending" <?= ($statusFilter === 'pending') ? 'selected' : '' ?>>Pending Triage</option>
                        <option value="assigned" <?= ($statusFilter === 'assigned') ? 'selected' : '' ?>>Assigned</option>
                        <option value="in_progress" <?= ($statusFilter === 'in_progress') ? 'selected' : '' ?>>In Progress</option>
                        <option value="resolved" <?= ($statusFilter === 'resolved') ? 'selected' : '' ?>>Resolved</option>
                        <option value="closed" <?= ($statusFilter === 'closed') ? 'selected' : '' ?>>Closed</option>
                        <option value="reopened" <?= ($statusFilter === 'reopened') ? 'selected' : '' ?>>Reopened</option>
                    </select>
                </div>

                <div class="col-md-3">
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
                    <a href="<?= BASE_URL ?>student/my-tickets.php" class="btn btn-light btn-sm w-100 border text-muted" title="Clear Filters">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </form>
        </div>

        <!-- Tickets Table -->
        <div class="cf-card">
            <?php if (empty($tickets)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="bi bi-search"></i></div>
                    <h5 class="empty-state-title">No Matching Fault Reports Found</h5>
                    <p class="empty-state-text">
                        We couldn't find any tickets matching your search query or status filter. Try clearing filters or submit a new report.
                    </p>
                    <a href="<?= BASE_URL ?>student/report-fault.php" class="btn btn-primary btn-sm fw-semibold">
                        <i class="bi bi-plus-circle me-1"></i> Submit Report
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
                                <th>Severity</th>
                                <th>Status</th>
                                <th>Technician</th>
                                <th>Date Filed</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $t): ?>
                                <tr>
                                    <td>
                                        <a href="<?= BASE_URL ?>student/ticket-details.php?id=<?= $t['id'] ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                                            <?= htmlspecialchars($t['ticket_number']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="p-1 rounded bg-light border text-primary">
                                                <i class="bi <?= htmlspecialchars($t['category_icon']) ?>"></i>
                                            </span>
                                            <div>
                                                <div class="fw-semibold text-dark text-truncate" style="max-width: 260px;">
                                                    <?= htmlspecialchars($t['title']) ?>
                                                </div>
                                                <small class="text-muted"><?= htmlspecialchars($t['category_name']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small fw-medium text-dark"><?= htmlspecialchars($t['building_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($t['room_number'] ?? 'General Area') ?></small>
                                    </td>
                                    <td><?= render_severity_badge($t['severity']) ?></td>
                                    <td><?= render_status_badge($t['status']) ?></td>
                                    <td>
                                        <?php if (!empty($t['technician_name'])): ?>
                                            <span class="small text-dark fw-medium d-inline-flex align-items-center gap-1">
                                                <i class="bi bi-person-check text-primary"></i> <?= htmlspecialchars($t['technician_name']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="small text-muted fst-italic">Pending Assignment</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted"><?= time_ago($t['created_at']) ?></td>
                                    <td class="text-end">
                                        <a href="<?= BASE_URL ?>student/ticket-details.php?id=<?= $t['id'] ?>" class="btn btn-light btn-sm border fw-semibold px-2 py-1">
                                            <i class="bi bi-eye"></i> Details
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

