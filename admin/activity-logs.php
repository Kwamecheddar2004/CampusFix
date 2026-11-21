<?php
/**
 * CampusFix - Security & Activity Audit Log Viewer
 * Immutable security compliance ledger recording user logins, status changes, and administrative actions.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');

$user = current_user();
$pageTitle = 'Activity Logs';
$pageHeading = 'System Audit & Activity Logs';
$pageSubtitle = 'Immutable ledger recording logins, assignments, status changes, and security events';

$actionFilter = trim($_GET['action'] ?? '');
$search = trim($_GET['search'] ?? '');

$pdo = get_db_connection();

$sql = "
    SELECT a.*, u.full_name, u.email, u.role
    FROM activity_logs a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE 1=1
";
$params = [];

if (!empty($actionFilter)) {
    $sql .= " AND a.action = ?";
    $params[] = $actionFilter;
}

if (!empty($search)) {
    $sql .= " AND (u.full_name LIKE ? OR a.ip_address LIKE ? OR a.entity_type LIKE ? OR a.action LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql .= " ORDER BY a.id DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

include_once INCLUDES_PATH . 'header.php';
include_once INCLUDES_PATH . 'sidebar.php';
?>

<div class="d-flex flex-column flex-1 w-100">
    <?php include_once INCLUDES_PATH . 'navbar.php'; ?>

    <div class="container-fluid px-0">

        <!-- Toolbar -->
        <div class="cf-card mb-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                <div>
                    <h5 class="cf-card-title mb-1">Security Audit Trail</h5>
                    <small class="text-muted">Viewing latest <?= count($logs) ?> system events</small>
                </div>
            </div>

            <form method="GET" action="" class="row g-2 align-items-center">
                <div class="col-md-6">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search user name, IP address, action...">
                    </div>
                </div>

                <div class="col-md-4">
                    <select class="form-select form-select-sm" name="action" onchange="this.form.submit()">
                        <option value="">All Action Types</option>
                        <option value="user_login" <?= ($actionFilter === 'user_login') ? 'selected' : '' ?>>User Logins</option>
                        <option value="ticket_created" <?= ($actionFilter === 'ticket_created') ? 'selected' : '' ?>>Ticket Created</option>
                        <option value="ticket_assigned" <?= ($actionFilter === 'ticket_assigned') ? 'selected' : '' ?>>Ticket Assigned</option>
                        <option value="ticket_started" <?= ($actionFilter === 'ticket_started') ? 'selected' : '' ?>>Repair Started</option>
                        <option value="ticket_resolved" <?= ($actionFilter === 'ticket_resolved') ? 'selected' : '' ?>>Repair Resolved</option>
                        <option value="ticket_closed" <?= ($actionFilter === 'ticket_closed') ? 'selected' : '' ?>>Ticket Closed</option>
                        <option value="ticket_reopened" <?= ($actionFilter === 'ticket_reopened') ? 'selected' : '' ?>>Ticket Reopened</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <a href="<?= BASE_URL ?>admin/activity-logs.php" class="btn btn-light btn-sm w-100 border text-muted">
                        <i class="bi bi-arrow-counterclockwise"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Audit Table -->
        <div class="cf-card">
            <div class="table-responsive">
                <table class="cf-table">
                    <thead>
                        <tr>
                            <th>Event Timestamp</th>
                            <th>User / Operator</th>
                            <th>Action</th>
                            <th>Entity Type</th>
                            <th>Client IP</th>
                            <th>Metadata Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $l): ?>
                            <tr>
                                <td class="small text-muted font-monospace"><?= date('Y-m-d H:i:s', strtotime($l['created_at'])) ?></td>
                                <td>
                                    <?php if ($l['full_name']): ?>
                                        <div class="fw-bold text-dark small"><?= htmlspecialchars($l['full_name']) ?></div>
                                        <small class="badge bg-secondary-subtle text-secondary" style="font-size: 0.65rem; text-transform: uppercase;">
                                            <?= htmlspecialchars($l['role']) ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted small fst-italic">System Daemon</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-primary border font-monospace" style="font-size: 0.75rem;">
                                        <?= htmlspecialchars($l['action']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-muted small text-uppercase fw-semibold" style="font-size: 0.7rem;">
                                        <?= htmlspecialchars($l['entity_type']) ?> #<?= htmlspecialchars((string)($l['entity_id'] ?? '—')) ?>
                                    </span>
                                </td>
                                <td><code class="small text-muted"><?= htmlspecialchars($l['ip_address'] ?? '127.0.0.1') ?></code></td>
                                <td>
                                    <?php if (!empty($l['details'])): ?>
                                        <pre class="mb-0 bg-light p-1 rounded small border font-monospace" style="font-size: 0.7rem; max-width: 320px; max-height: 48px; overflow: auto;"><?= htmlspecialchars($l['details']) ?></pre>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php include_once INCLUDES_PATH . 'footer.php'; ?>

