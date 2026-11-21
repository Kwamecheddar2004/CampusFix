<?php
/**
 * CampusFix - Administrator Ticket Triage & Technician Dispatch Modal/Page
 * Assigns or reassigns field technicians, sets priority levels, and records dispatch notes.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');

$user = current_user();
$ticketId = (int) ($_GET['id'] ?? 0);

if ($ticketId <= 0) {
    flash_message('danger', 'Invalid ticket identifier.');
    header("Location: " . BASE_URL . "admin/all-tickets.php");
    exit;
}

$pdo = get_db_connection();

// 1. Fetch Ticket Info
$stmt = $pdo->prepare("
    SELECT t.*, c.name as category_name, c.icon as category_icon, c.department_in_charge,
           l.building_name, l.room_number,
           r.full_name as reporter_name, r.email as reporter_email,
           tech.full_name as current_technician_name
    FROM tickets t
    JOIN categories c ON t.category_id = c.id
    JOIN campus_locations l ON t.location_id = l.id
    JOIN users r ON t.reporter_id = r.id
    LEFT JOIN users tech ON t.assigned_to = tech.id
    WHERE t.id = ?
    LIMIT 1
");
$stmt->execute([$ticketId]);
$ticket = $stmt->fetch();

if (!$ticket) {
    flash_message('danger', 'Ticket not found.');
    header("Location: " . BASE_URL . "admin/all-tickets.php");
    exit;
}

// 2. Fetch Active Technicians along with their current active workload
$techStmt = $pdo->query("
    SELECT u.id, u.full_name, u.email, u.phone, u.department,
           COUNT(CASE WHEN t.status IN ('assigned', 'in_progress', 'reopened') THEN 1 END) as active_jobs
    FROM users u
    LEFT JOIN tickets t ON u.id = t.assigned_to
    WHERE u.role = 'technician' AND u.status = 'active'
    GROUP BY u.id, u.full_name, u.email, u.phone, u.department
    ORDER BY active_jobs ASC, u.full_name ASC
");
$technicians = $techStmt->fetchAll();

// 3. Handle Assignment POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();

    $technicianId = (int) ($_POST['technician_id'] ?? 0);
    $priority     = strtolower(trim($_POST['priority'] ?? $ticket['priority']));
    $instructions = trim($_POST['instructions'] ?? '');

    $allowedPriorities = ['low', 'medium', 'high', 'urgent'];
    if (!in_array($priority, $allowedPriorities, true)) {
        $priority = 'medium';
    }

    if ($technicianId <= 0) {
        flash_message('danger', 'Please choose a technician from the roster.');
    } else {
        try {
            $pdo->beginTransaction();

            $oldStatus = $ticket['status'];
            $newStatus = ($oldStatus === 'in_progress') ? 'in_progress' : 'assigned';

            // Update ticket table
            $upStmt = $pdo->prepare("
                UPDATE tickets 
                SET assigned_to = ?, priority = ?, status = ?, assigned_at = NOW(), updated_at = NOW()
                WHERE id = ?
            ");
            $upStmt->execute([$technicianId, $priority, $newStatus, $ticketId]);

            // Insert into ticket_assignments table
            $assignStmt = $pdo->prepare("
                INSERT INTO ticket_assignments (ticket_id, technician_id, assigned_by, assigned_at, status, instructions)
                VALUES (?, ?, ?, NOW(), 'active', ?)
            ");
            $assignStmt->execute([$ticketId, $technicianId, $user['id'], $instructions]);

            // Insert into ticket_status_history table
            $techNameStmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
            $techNameStmt->execute([$technicianId]);
            $techName = $techNameStmt->fetchColumn() ?: 'Technician';

            $histStmt = $pdo->prepare("
                INSERT INTO ticket_status_history (ticket_id, old_status, new_status, changed_by, notes, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $histNotes = "Assigned to {$techName} (Priority: " . ucfirst($priority) . ")";
            if (!empty($instructions)) {
                $histNotes .= " - Notes: " . substr($instructions, 0, 100);
            }
            $histStmt->execute([$ticketId, $oldStatus, $newStatus, $user['id'], $histNotes]);

            // Dispatch In-App Notification to Technician
            $notifTechStmt = $pdo->prepare("
                INSERT INTO notifications (user_id, ticket_id, title, message, channel, status, created_at)
                VALUES (?, ?, ?, ?, 'in_app', 'sent', NOW())
            ");
            $notifTechStmt->execute([
                $technicianId,
                $ticketId,
                "New Job Assigned ({$ticket['ticket_number']})",
                "Admin {$user['full_name']} dispatched ticket '{$ticket['title']}' to you with {$priority} priority."
            ]);

            // Dispatch In-App Notification to Reporter
            $notifUserStmt = $pdo->prepare("
                INSERT INTO notifications (user_id, ticket_id, title, message, channel, status, created_at)
                VALUES (?, ?, ?, ?, 'in_app', 'sent', NOW())
            ");
            $notifUserStmt->execute([
                $ticket['reporter_id'],
                $ticketId,
                "Technician Assigned ({$ticket['ticket_number']})",
                "Your fault report has been assigned to technician {$techName}."
            ]);

            // Security Audit Log
            log_activity($user['id'], 'ticket_assigned', 'ticket', $ticketId, [
                'technician_id' => $technicianId,
                'priority'      => $priority
            ]);

            $pdo->commit();

            flash_message('success', "Ticket {$ticket['ticket_number']} dispatched to {$techName} successfully!");
            header("Location: " . BASE_URL . "admin/all-tickets.php");
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash_message('danger', 'Assignment error: ' . $e->getMessage());
        }
    }
}

$pageTitle = "Dispatch Ticket: {$ticket['ticket_number']}";
$pageHeading = "Technician Dispatch & Triage";
$pageSubtitle = "Assign certified technician, adjust SLA priority, and provide work instructions";

include_once INCLUDES_PATH . 'header.php';
include_once INCLUDES_PATH . 'sidebar.php';
?>

<div class="d-flex flex-column flex-1 w-100">
    <?php include_once INCLUDES_PATH . 'navbar.php'; ?>

    <div class="container-fluid px-0">
        <div class="row justify-content-center">
            <div class="col-xl-8 col-lg-10">

                <div class="cf-card">
                    <div class="cf-card-header">
                        <div>
                            <h5 class="cf-card-title mb-1">Dispatching: <?= htmlspecialchars($ticket['ticket_number']) ?></h5>
                            <small class="text-muted"><?= htmlspecialchars($ticket['title']) ?></small>
                        </div>
                        <a href="<?= BASE_URL ?>admin/all-tickets.php" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left"></i> Return
                        </a>
                    </div>

                    <!-- Problem Overview Snippet -->
                    <div class="p-3 bg-light rounded-3 mb-4 border">
                        <div class="row g-2 small">
                            <div class="col-sm-6">
                                <span class="text-muted d-block">Location</span>
                                <strong class="text-dark"><?= htmlspecialchars($ticket['building_name']) ?> (<?= htmlspecialchars($ticket['room_number'] ?? 'General') ?>)</strong>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted d-block">Category</span>
                                <strong class="text-dark"><?= htmlspecialchars($ticket['category_name']) ?></strong> (<?= htmlspecialchars($ticket['department_in_charge']) ?>)
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted d-block">Reporter</span>
                                <span class="text-dark"><?= htmlspecialchars($ticket['reporter_name']) ?> (<?= htmlspecialchars($ticket['reporter_email']) ?>)</span>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted d-block">Reported Severity</span>
                                <?= render_severity_badge($ticket['severity']) ?>
                            </div>
                        </div>
                        <div class="mt-2 pt-2 border-top small text-dark">
                            <strong>Problem Description:</strong> <?= htmlspecialchars($ticket['description']) ?>
                        </div>
                    </div>

                    <form method="POST" action="">
                        <?= csrf_field() ?>

                        <!-- Select Technician -->
                        <div class="mb-4">
                            <label for="technician_id" class="form-label small fw-bold text-dark">
                                Select Qualified Field Technician <span class="text-danger">*</span>
                            </label>
                            <div class="row g-3">
                                <?php foreach ($technicians as $t): ?>
                                    <div class="col-md-6">
                                        <label class="p-3 border rounded-3 w-100 d-block cursor-pointer bg-light position-relative">
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <div class="d-flex align-items-center gap-2">
                                                    <input type="radio" name="technician_id" value="<?= $t['id'] ?>" class="form-check-input" <?= ($ticket['assigned_to'] == $t['id']) ? 'checked' : '' ?> required>
                                                    <strong class="text-dark"><?= htmlspecialchars($t['full_name']) ?></strong>
                                                </div>
                                                <span class="badge <?= ($t['active_jobs'] > 3) ? 'bg-danger' : 'bg-primary' ?> rounded-pill" style="font-size: 0.7rem;">
                                                    <?= $t['active_jobs'] ?> Active Jobs
                                                </span>
                                            </div>
                                            <div class="small text-muted ps-4">
                                                <div><?= htmlspecialchars($t['department'] ?? 'Technical Artisan') ?></div>
                                                <div class="font-monospace" style="font-size: 0.75rem;"><?= htmlspecialchars($t['phone'] ?? $t['email']) ?></div>
                                            </div>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Priority Adjustment -->
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-dark">
                                Administrative Priority / SLA Threshold
                            </label>
                            <div class="row g-2">
                                <div class="col-3">
                                    <label class="p-2 border rounded-3 text-center w-100 cursor-pointer bg-light">
                                        <input type="radio" name="priority" value="low" class="form-check-input d-block mx-auto mb-1" <?= ($ticket['priority'] === 'low') ? 'checked' : '' ?>>
                                        <small class="fw-semibold text-secondary">Low (72h)</small>
                                    </label>
                                </div>
                                <div class="col-3">
                                    <label class="p-2 border rounded-3 text-center w-100 cursor-pointer bg-light">
                                        <input type="radio" name="priority" value="medium" class="form-check-input d-block mx-auto mb-1" <?= ($ticket['priority'] === 'medium') ? 'checked' : '' ?>>
                                        <small class="fw-semibold text-primary">Medium (24h)</small>
                                    </label>
                                </div>
                                <div class="col-3">
                                    <label class="p-2 border rounded-3 text-center w-100 cursor-pointer bg-light">
                                        <input type="radio" name="priority" value="high" class="form-check-input d-block mx-auto mb-1" <?= ($ticket['priority'] === 'high') ? 'checked' : '' ?>>
                                        <small class="fw-semibold text-warning-emphasis">High (12h)</small>
                                    </label>
                                </div>
                                <div class="col-3">
                                    <label class="p-2 border rounded-3 text-center w-100 cursor-pointer bg-light border-danger">
                                        <input type="radio" name="priority" value="urgent" class="form-check-input d-block mx-auto mb-1" <?= ($ticket['priority'] === 'urgent') ? 'checked' : '' ?>>
                                        <small class="fw-bold text-danger">Urgent (4h)</small>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Instructions to Technician -->
                        <div class="mb-4">
                            <label for="instructions" class="form-label small fw-bold text-dark">
                                Work Instructions / Diagnostic Directives <small class="text-muted fw-normal">(Optional)</small>
                            </label>
                            <textarea class="form-control" name="instructions" id="instructions" rows="3" 
                                      placeholder="e.g. Please bring replacement RJ45 connectors and console cable; isolate sub-panel Q3 breaker before working..."></textarea>
                        </div>

                        <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                            <a href="<?= BASE_URL ?>admin/all-tickets.php" class="btn btn-light border btn-sm">Cancel</a>
                            <button type="submit" class="btn btn-primary fw-semibold btn-sm px-4">
                                <i class="bi bi-send-check me-1"></i> Dispatch Ticket Now
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include_once INCLUDES_PATH . 'footer.php'; ?>

